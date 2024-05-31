<?php

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Larapi\Infrastructure\Laravel\Foundation\Application;
use Larapi\Infrastructure\Laravel\Foundation\Http\Kernel;

// Register the Composer autoloader...
require __DIR__ . '/../vendor/autoload.php';

// Initialize application...
$app = new Application(dirname(__DIR__));

// Register the http kernel...
$app->singleton(HttpKernel::class, Kernel::class);

// Handle http request...
/** @noinspection PhpUnhandledExceptionInspection */
$app->handleRequest(Request::capture());
