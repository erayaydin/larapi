<?php

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Log\LogServiceProvider;
use Larapi\Infrastructure\Laravel\Foundation\Application;
use Larapi\Infrastructure\Laravel\Foundation\Exceptions\Handler;
use Larapi\Infrastructure\Laravel\Foundation\Http\Kernel;

// Register the Composer autoloader...
require __DIR__ . '/../vendor/autoload.php';

// Initialize application...
$app = new Application(dirname(__DIR__));

// Register event service...
$app->register(new EventServiceProvider($app));

// Register log service...
$app->register(new LogServiceProvider($app));

// Register the http kernel...
$app->singleton(HttpKernel::class, Kernel::class);

// Register the exception handler...
$app->singleton(ExceptionHandler::class, Handler::class);

// Handle http request...
/** @noinspection PhpUnhandledExceptionInspection */
$app->handleRequest(Request::capture());
