#!/usr/bin/env php
<?php

namespace Lmc\Steward;

use Nette\Reflection\AnnotationsParser;
use Symfony\Component\Console\Application;
use Lmc\Steward\Console\Command\InstallCommand;
use Lmc\Steward\Console\Command\RunTestsCommand;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Finder\Finder;

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

$dispatcher = new EventDispatcher();
$application = new Application('Steward', '0.0.1');
$application->setDispatcher($dispatcher);

// Search for listeners
$listenerDirs = [];
// if custom EventListeners in this project exists
if (is_dir(STEWARD_BASE_DIR . '/lib/Console/EventListener')) {
    $listenerDirs[] = STEWARD_BASE_DIR . '/lib/Console/EventListener';
}
// if installed as dependency, use also default EventListeners
if (realpath(STEWARD_BASE_DIR . '/lib/Console/EventListener') != realpath(__DIR__ . '/../lib/Console/EventListener')) {
    $listenerDirs[] = __DIR__ . '/../lib/Console/EventListener';
}

$finder = new Finder();
$files = $finder->files()->in($listenerDirs)->name('*Listener.php');

$listeners = [];
foreach ($files as $file) {
    $listeners[] = key(AnnotationsParser::parsePhp(\file_get_contents($file->getRealpath())));
}

// Instantiate found Listeners and subscribe them to EventDispatcher
foreach ($listeners as $listener) {
    $r = new \ReflectionClass($listener);
    if ($r->implementsInterface('Symfony\\Component\\EventDispatcher\\EventSubscriberInterface') && !$r->isAbstract()) {
        $listenerInstance = $r->newInstance();
        $dispatcher->addSubscriber($listenerInstance);
    }
}

// Add Commands with injected EventDispatcher to the main console Application
$application->addCommands(
    [
        new RunTestsCommand($dispatcher),
        new InstallCommand($dispatcher),
    ]
);
$application->run();
