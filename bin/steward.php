#!/usr/bin/env php
<?php
require_once 'vendor/autoload.php';

use Lmc\Steward\RunTestsCommand;
use Symfony\Component\Console\Application;

$application = new Application('Steward', '0.0.1');
$application->add(new RunTestsCommand());
$application->run();
