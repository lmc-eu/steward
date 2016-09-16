<?php

date_default_timezone_set('Europe/Prague');

/** @var \Composer\Autoload\ClassLoader $loader */
$loader = require __DIR__ . '/../vendor/autoload.php';

$loader->addPsr4('Lmc\\Steward\\', __DIR__);

// Define global constant required by Lmc\Steward\Console\Command
if (!defined('STEWARD_BASE_DIR')) {
    define('STEWARD_BASE_DIR', realpath(__DIR__ . '/../'));
}
