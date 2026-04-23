<?php

define('BASE_PATH', dirname(__DIR__));

// Composer autoloader
require_once BASE_PATH . '/vendor/autoload.php';

// Load framework classes
spl_autoload_register(function ($class) {
    $paths = [
        BASE_PATH . "/core/{$class}.php",
        BASE_PATH . "/app/Controllers/{$class}.php",
        BASE_PATH . "/app/Models/{$class}.php",
        BASE_PATH . "/app/Services/{$class}.php",
    ];
    foreach ($paths as $p) {
        if (file_exists($p)) {
            require_once $p;
            return;
        }
    }
});

// Load scraper classes
require_once BASE_PATH . '/app/Services/JobScraper.php';
require_once BASE_PATH . '/app/Services/AdditionalScrapers.php';
