#!/usr/bin/env php
<?php

namespace Lmc\Steward;

require_once 'vendor/autoload.php';

use Symfony\Component\Console\Application;

$application = new Application('Steward', '0.0.1');
$application
    ->addCommands([
        new RunTestsCommand(),
        new InstallCommand(),
    ]);

$application->run();
