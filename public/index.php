<?php

use Larapi\Infrastructure\Laravel\Foundation\Application;

// Register the Composer autoloader...
require __DIR__ . '/../vendor/autoload.php';

// Initialize application...
$app = new Application(dirname(__DIR__));
