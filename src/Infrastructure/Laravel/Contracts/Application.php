<?php

namespace Larapi\Infrastructure\Laravel\Contracts;

use Illuminate\Contracts\Foundation\Application as LaravelApplication;

interface Application extends LaravelApplication
{
    /**
     * Get the path to the application source directory.
     *
     * @param  string  $path
     * @return string
     */
    public function path(string $path = ''): string;
}