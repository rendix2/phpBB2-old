<?php

use Dibi\Bridges\Tracy\Panel;
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

if ( !defined('IN_PHPBB') )
{
	die('Hacking attempt');
}

/**
 * enable working with session.... it was STILL MISSING
 */
session_start();

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
$nav_links = [];
$dss_seeded = false;
$gen_simple_header = false;

include $phpbb_root_path . 'config.php';

if (!defined('PHPBB_INSTALLED')) {
	header('Location: ' . $phpbb_root_path . 'install/install.php');
	exit;
}

include $phpbb_root_path . 'includes/constants.php';
include $phpbb_root_path . 'includes/Template.php';
include $phpbb_root_path . 'includes/Session.php';
include $phpbb_root_path . 'includes/Auth.php';
include $phpbb_root_path . 'includes/functions.php';
include $phpbb_root_path .'vendor/autoload.php';

// now we connect to database via dibi!
$connection = dibi::connect([
    'driver'   => 'PDO',
    'username' => $dbuser,
    'password' => $dbpasswd,
    'dsn'      => $dns
]);

$connection->connect();

// enable tracy
Debugger::enable();

// adds dibi into tracy
$panel = new Panel();
$panel->explain = true;
Panel::$maxLength = 10000;
$panel->register($connection);

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
} else {
    if (!empty($_ENV['REMOTE_ADDR'])) {
        $client_ip = $_ENV['REMOTE_ADDR'];
    } else {
        $client_ip = getenv('REMOTE_ADDR');
    }
}

$user_ip = encode_ip($client_ip);

//
// Setup forum wide options, if this fails
// then we output a CRITICAL_ERROR since
// basic forum information is not available
//

$board_config = dibi::select('*')
    ->from(CONFIG_TABLE)
    ->fetchPairs('config_name', 'config_value');

if (!$board_config) {
    message_die(CRITICAL_ERROR, 'Could not query config information');
}

if (file_exists('install') || file_exists('contrib')) {
	message_die(GENERAL_MESSAGE, 'Please_remove_install_contrib');
}

//
// Show 'Board is disabled' message if needed.
//
if ($board_config['board_disable'] && !defined('IN_ADMIN') && !defined('IN_LOGIN')) {
	message_die(GENERAL_MESSAGE, 'Board_disable', 'Information');
}

?>