<?php

namespace Larapi\Infrastructure\Laravel\Foundation\Bootstrapper;

use Illuminate\Config\Repository;
use Larapi\Infrastructure\Laravel\Contracts\Application;

final readonly class LoadConfiguration implements Bootable
{
    /**
     * Bootstrap the given application.
     *
     * @param Application $app
     * @return void
     */
    public function bootstrap(Application $app): void
    {
        $app->instance('config', new Repository([
            'logging' => [
                'default' => 'single',

                'channels' => [
                    'single' => [
                        'driver' => 'single',
                        'path' => $app->storagePath('logs/laravel.log'),
                        'level' => 'debug',
                        'replace_placeholders' => true,
                    ],
                ],
            ],
        ]));
    }
}