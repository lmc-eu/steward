<?php declare(strict_types=1);

// Bootstrap before each Testcase

if (file_exists($autoload = __DIR__ . '/../vendor/autoload.php')) {
    require_once $autoload;
} elseif (file_exists($autoload = __DIR__ . '/../../../autoload.php')) {
    require_once $autoload;
}
