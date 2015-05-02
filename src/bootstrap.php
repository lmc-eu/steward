<?php

// Bootstrap before each Testcase

date_default_timezone_set('Europe/Prague');

if (file_exists($autoload = __DIR__ . '/../vendor/autoload.php')) {
    require_once $autoload;
} elseif (file_exists($autoload = __DIR__ . '/../../../autoload.php')) {
    require_once $autoload;
}
