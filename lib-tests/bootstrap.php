<?php

date_default_timezone_set('Europe/Prague');

/** @var \Composer\Autoload\ClassLoader $loader */
$loader = require __DIR__ . '/../vendor/autoload.php';

$loader->addPsr4('Lmc\\Steward\\', __DIR__);

// Define global constant required by Lmc\Steward\Console\Command
define('STEWARD_BASE_DIR', __DIR__ . '/Console/Command/Fixtures');
