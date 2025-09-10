<?php

// Test bootstrap file
require_once __DIR__ . '/../vendor/autoload.php';

// Set timezone for consistent test results
date_default_timezone_set('UTC');

// Load test environment variables if available
if (file_exists(__DIR__ . '/.env.test')) {
    $lines = file(__DIR__ . '/.env.test', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($name, $value) = explode('=', $line, 2);
            $_ENV[trim($name)] = trim($value, '"\'');
        }
    }
}