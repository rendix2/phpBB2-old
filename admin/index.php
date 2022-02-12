<?php
/**
 *
 * Created by PhpStorm.
 * Filename: index.php
 * User: Tomáš Babický
 * Date: 03.03.2021
 * Time: 13:38
 */

$sep = DIRECTORY_SEPARATOR;

$container = require __DIR__ . $sep . '..' . $sep . 'app' . $sep . 'bootstrap.php';

$container->getByType(Nette\Application\Application::class)
    ->run();
