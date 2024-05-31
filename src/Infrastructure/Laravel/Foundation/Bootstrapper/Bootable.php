<?php

namespace Larapi\Infrastructure\Laravel\Foundation\Bootstrapper;

use Larapi\Infrastructure\Laravel\Contracts\Application;

interface Bootable
{
    public function bootstrap(Application $app): void;
}