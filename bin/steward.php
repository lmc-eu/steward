#!/usr/bin/env php
<?php

namespace Lmc\Steward;

use Symfony\Component\Console\Application;

function requireIfExists($file)
{
    if (file_exists($file)) {
        return require_once $file;
    }
}

if (!requireIfExists(__DIR__ . '/../vendor/autoload.php')) {
    die(
        'You must set up the project dependencies, run the following commands:' . PHP_EOL .
        'curl -sS https://getcomposer.org/installer | php' . PHP_EOL .
        'php composer.phar install' . PHP_EOL
    );
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
