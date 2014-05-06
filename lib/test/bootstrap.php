<?php

// Bootstrap before each Testcase

require_once __DIR__ . '/../../vendor/autoload.php';

// Set required first argument as BROWSER_NAME constant to by used in tests

$browserName = getenv('BROWSER_NAME');

if (!$browserName) {
    throw new Exception('No browser name passed to phpunit');
}

define('BROWSER_NAME', $browserName);
