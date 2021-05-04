<?php declare(strict_types=1);

$config = [];

if (PHP_VERSION_ID < 80000) {
    // CurlHandle return type hint is only valid for PHP 8+ and should be ignored otherwise
    $config['parameters']['ignoreErrors'][] = [
        'message' => '#has invalid type CurlHandle#',
        'path' => __DIR__ . '/src/Publisher/AbstractCloudPublisher.php',
    ];
}

return $config;
