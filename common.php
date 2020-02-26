<?php

use Dibi\Bridges\Tracy\Panel;
use Nette\Caching\Cache;
use Nette\Caching\Storages\FileStorage;
use Nette\Configurator;
use Tracy\Debugger;

/***************************************************************************
 *                                common.php
 *                            -------------------
 *   begin                : Saturday, Feb 23, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: common.php 5970 2006-05-26 17:46:59Z grahamje $
 *
 ***************************************************************************/

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 ***************************************************************************/

if (!defined('IN_PHPBB')) {
    die('Hacking attempt');
}

//
error_reporting  (E_ERROR | E_WARNING | E_PARSE); // This will NOT report uninitialized variables

// The following code (unsetting globals)
// Thanks to Matt Kavanagh and Stefan Esser for providing feedback as well as patch files

// Protect against GLOBALS tricks
if (isset($_POST['GLOBALS']) || isset($_FILES['GLOBALS']) || isset($_GET['GLOBALS']) || isset($_COOKIE['GLOBALS'])) {
	die('Hacking attempt');
}

// Protect against HTTP_SESSION_VARS tricks
if (isset($_SESSION) && !is_array($_SESSION)) {
	die('Hacking attempt');
}

$sep = DIRECTORY_SEPARATOR;

require_once $phpbb_root_path . 'vendor' . $sep . 'autoload.php';

$configurator = new Configurator();
$configurator->enableTracy(__DIR__ . $sep . 'log');
$configurator->setTempDirectory(__DIR__ . $sep . 'temp');

$configurator->createRobotLoader()
    ->addDirectory(__DIR__)
    ->register();

$configurator->addConfig(__DIR__ . $sep . 'app' . $sep . 'config' . $sep . 'config.neon');
$container = $configurator->createContainer();

//
// Define some basic configuration arrays this also prevents
// malicious rewriting of language and otherarray values via
// URI params
//
$board_config = [];
$userdata = [];
$theme = [];
$images = [];
$lang = [];
$gen_simple_header = false;

if (file_exists($phpbb_root_path . 'Config.php')) {
    require_once $phpbb_root_path . 'Config.php';
} else {
    header('Location: ' . $phpbb_root_path . 'install' . $sep . 'install.php');
    exit;
}

$classReflection = new ReflectionClass('Config');

if (!$classReflection->hasConstant('INSTALLED')) {
    header('Location: ' . $phpbb_root_path . 'install' . $sep . 'install.php');
	exit;
}

require_once $phpbb_root_path . 'includes' . $sep . 'constants.php';
require_once $phpbb_root_path . 'includes' . $sep . 'functions.php';

// now we connect to database via dibi!
$connection = dibi::connect([
    'driver'   => Config::DATABASE_DRIVER,
    'username' => Config::DATABASE_USER,
    'password' => Config::DATABASE_PASSWORD,
    'dsn'      => Config::DATABASE_DNS
]);

$connection->connect();

// adds dibi into tracy
$panel = new Panel();
$panel->explain = true;
Panel::$maxLength = 10000;
$panel->register($connection);

// cache
$storage = new FileStorage(__DIR__ . $sep . 'temp');
$cache   = new Cache($storage, Tables::CONFIG_TABLE);

// attachment mod
require_once $phpbb_root_path . 'attach_mod' . $sep . 'attachment_mod.php';

// enable tracy
Debugger::enable();
Debugger::$maxDepth = 5;
Debugger::$maxLength = 2000;
//Debugger::$strictMode = true;

// We do not need this any longer, unset for safety purposes
//unset($dbpasswd);

//
// Obtain and encode users IP
//
// I'm removing HTTP_X_FORWARDED_FOR ... this may well cause other problems such as
// private range IP's appearing instead of the guilty routable IP, tough, don't
// even bother complaining ... go scream and shout at the idiots out there who feel
// "clever" is doing harm rather than good ... karma is a great thing ... :)
//
if (!empty($_SERVER['REMOTE_ADDR'])) {
    $client_ip = $_SERVER['REMOTE_ADDR'];
} elseif (!empty($_ENV['REMOTE_ADDR'])) {
    $client_ip = $_ENV['REMOTE_ADDR'];
} else {
    $client_ip = getenv('REMOTE_ADDR');
}

$user_ip = encode_ip($client_ip);

//
// Setup forum wide options, if this fails
// then we output a CRITICAL_ERROR since
// basic forum information is not available
//

$boardConfigCached = $cache->load(Tables::CONFIG_TABLE);

if ($boardConfigCached !== null) {
    $board_config = $boardConfigCached;
} else {
    $board_config = dibi::select('*')
        ->from(Tables::CONFIG_TABLE)
        ->fetchPairs('config_name', 'config_value');

    $cache->save(Tables::CONFIG_TABLE, $board_config);
}

if (!$board_config) {
    message_die(CRITICAL_ERROR, 'Could not query config information');
}

if (Debugger::$productionMode && (file_exists('install') || file_exists('contrib'))) {
	message_die(GENERAL_MESSAGE, 'Please_remove_install_contrib');
}

//
// Show 'Board is disabled' message if needed.
//
if ($board_config['board_disable'] && !defined('IN_ADMIN') && !defined('IN_LOGIN')) {
	message_die(GENERAL_MESSAGE, 'Board_disable', 'Information');
}

?>