<?php
/**
 *
 * Created by PhpStorm.
 * Filename: bootstrap.php
 * User: Tomáš Babický
 * Date: 03.03.2021
 * Time: 13:39
 */

use Dibi\Bridges\Tracy\Panel;

$sep = DIRECTORY_SEPARATOR;

require __DIR__ . '/../vendor/autoload.php';

$configurator = new Nette\Configurator;

//$configurator->setDebugMode('23.75.345.200'); // enable for your remote IP
$configurator->enableTracy(__DIR__ . '/../log');

$configurator->setTimeZone('Europe/Prague');
$configurator->setTempDirectory(__DIR__ . '/../temp');

$configurator->createRobotLoader()
    ->addDirectory(__DIR__ . $sep .'..' )
    ->excludeDirectory(__DIR__ . $sep .'..' . $sep .'vendor')
    ->register();

$configurator->addConfig(__DIR__ . '/config/config.neon');

// now we connect to database via dibi!
$connection = dibi::connect(
    [
        'driver'   => Config::DATABASE_DRIVER,
        'username' => Config::DATABASE_USER,
        'password' => Config::DATABASE_PASSWORD,
        'dsn'      => Config::DATABASE_DNS,
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT
        ],
    ]
);

$connection->connect();

// adds dibi into tracy
$panel = new Panel();
$panel->explain = true;
Panel::$maxLength = 10000;
$panel->register($connection);

$container = $configurator->createContainer();

return $container;
