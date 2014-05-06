<?php

// Bootstrap before each Testcase

require_once __DIR__ . '/../../vendor/autoload.php';

// Set used environment varibles as PHP constanst to be used in tests
$browserName = getenv('BROWSER_NAME');
if (!$browserName) {
    throw new Exception('BROWSER_NAME environment variable must be defined');
}
define('BROWSER_NAME', $browserName);

$lmcEnv = getenv('LMC_ENV');
if (!$lmcEnv) {
    throw new Exception('LMC_ENV environment variable must be defined');
}
define('LMC_ENV', $lmcEnv);
