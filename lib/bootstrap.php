<?php

// Bootstrap before each Testcase

date_default_timezone_set('Europe/Prague');

if (file_exists($autoload = __DIR__ . '/../vendor/autoload.php')) {
    require_once $autoload;
} elseif (file_exists($autoload = __DIR__ . '/../../../autoload.php')) {
    require_once $autoload;
}

// Set used environment variables as PHP constants to be used in tests
// TODO: rewrite to cycle to DRY
$browserName = getenv('BROWSER_NAME');
if (!$browserName) {
    throw new Exception('BROWSER_NAME environment variable must be defined');
}
define('BROWSER_NAME', $browserName);

$env = getenv('ENV');
if (!$env) {
    throw new Exception('ENV environment variable must be defined');
}
define('ENV', $env);

$serverUrl = getenv('SERVER_URL');
if (!$serverUrl) {
    throw new Exception('SERVER_URL environment variable must be defined');
}
define('SERVER_URL', $serverUrl);

$publishResults = getenv('PUBLISH_RESULTS');
if (!isset($publishResults)) {
    $publishResults = false;
}
define('PUBLISH_RESULTS', $publishResults);

$fixturesDir = getenv('FIXTURES_DIR');
if (!isset($fixturesDir)) {
    $logsDir = realpath(__DIR__ . '/../tests');
}
define('FIXTURES_DIR', $fixturesDir);

$logsDir = getenv('LOGS_DIR');
if (!isset($logsDir)) {
    $logsDir = realpath(__DIR__ . '/../logs');
}
define('LOGS_DIR', $logsDir);

$debug = getenv('DEBUG');
if (!isset($debug)) {
    $debug = false;
}
define('DEBUG', $debug);
