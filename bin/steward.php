#!/usr/bin/env php
<?php

namespace Lmc\Steward;

use Symfony\Component\Console\Application;
use Lmc\Steward\Command\InstallCommand;
use Lmc\Steward\Command\RunTestsCommand;

function requireIfExists($file)
{
    if (file_exists($file)) {
        return require_once $file;
    }
}

if (!requireIfExists(__DIR__ . '/../vendor/autoload.php') // when used directly
    && !requireIfExists(__DIR__ . '/../../../autoload.php') // when installed as dependency
) {
    die(
        'You must set up the project dependencies, run the following commands:' . PHP_EOL .
        'curl -sS https://getcomposer.org/installer | php' . PHP_EOL .
        'php composer.phar install' . PHP_EOL
    );
}

if (strpos(__DIR__, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false) { // when installed as dependency
    define('STEWARD_BASE_DIR', realpath(__DIR__ . '/../../../..'));
} else { // when used directly
    define('STEWARD_BASE_DIR', realpath(__DIR__ . '/..'));
}

$application = new Application('Steward', '0.0.1');
$application
    ->addCommands(
        [
            new RunTestsCommand(),
            new InstallCommand(),
        ]
    );

$application->run();
