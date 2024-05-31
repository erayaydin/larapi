<?php

namespace Larapi\Infrastructure\Laravel\Foundation\Bootstrapper;

use Illuminate\Config\Repository;
use Larapi\Infrastructure\Laravel\Contracts\Application;

final readonly class LoadConfiguration implements Bootable
{
    const CONFIG = [];

    /**
     * Bootstrap the given application.
     *
     * @param Application $app
     * @return void
     */
    public function bootstrap(Application $app): void
    {
        $app->instance('config', new Repository(LoadConfiguration::CONFIG));
    }
}