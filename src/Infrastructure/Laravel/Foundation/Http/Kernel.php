<?php

namespace Larapi\Infrastructure\Laravel\Foundation\Http;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use \Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Facade;
use Larapi\Infrastructure\Laravel\Contracts\Application;
use Larapi\Infrastructure\Laravel\Exceptions\ExceptionHandlerNotFoundException;
use Larapi\Infrastructure\Laravel\Exceptions\NotSupportedMethod;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class Kernel implements HttpKernel
{
    /**
     * The bootstrapper classes for the HTTP application.
     *
     * @var string[]
     */
    const BOOTSTRAPPERS = [
    ];

    /**
     * Create a new HTTP kernel instance.
     *
     * @param  Application  $app
     * @return void
     */
    public function __construct(
        private readonly Application $app,
    ) { }

    /**
     * Bootstrap the application for handling HTTP requests.
     *
     * @return void
     */
    public function bootstrap(): void
    {
        if (! $this->app->hasBeenBootstrapped()) {
            $this->app->bootstrapWith(Kernel::BOOTSTRAPPERS);
        }
    }

    /**
     * Handle an incoming HTTP request.
     *
     * @param Request $request
     * @return Response
     * @throws BindingResolutionException
     * @throws Throwable
     */
    public function handle($request): Response
    {
        try {
            $request->enableHttpMethodParameterOverride();

            $response = $this->sendRequestThroughRouter($request);
        } catch (Throwable $e) {
            $this->reportException($e);

            $response = $this->renderException($request, $e);
        }

        return $response;
    }

    /**
     * Perform any final actions for the request lifecycle.
     *
     * @param Request $request
     * @param Response $response
     * @return void
     */
    public function terminate($request, $response): void
    {
        $this->app->terminate();
    }

    /**
     * Get the application instance.
     *
     * @return never
     */
    public function getApplication(): never
    {
        throw new NotSupportedMethod("`getApplication` method for Http Kernel does not support in Larapi.");
    }

    /**
     * Send the given request through the middleware / router.
     *
     * @param  Request  $request
     * @return Response
     */
    private function sendRequestThroughRouter(Request $request): Response
    {
        $this->app->instance('request', $request);

        Facade::clearResolvedInstance('request');

        $this->bootstrap();

        return new Response("OK");
    }

    /**
     * Report the exception to the exception handler.
     *
     * @param Throwable $e
     * @return void
     * @throws BindingResolutionException
     * @throws ExceptionHandlerNotFoundException
     * @throws Throwable
     */
    private function reportException(Throwable $e): void
    {
        if (! $this->app->bound(ExceptionHandler::class)) {
            throw new ExceptionHandlerNotFoundException;
        }

        /** @var ExceptionHandler $handler */
        $handler = $this->app->make(ExceptionHandler::class);

        $handler->report($e);
    }

    /**
     * Render the exception to a response.
     *
     * @param Request $request
     * @param Throwable $e
     * @return Response
     * @throws BindingResolutionException
     * @throws Throwable
     */
    private function renderException(Request $request, Throwable $e): Response
    {
        if (! $this->app->bound(ExceptionHandler::class)) {
            throw new ExceptionHandlerNotFoundException;
        }

        /** @var ExceptionHandler $handler */
        $handler = $this->app->make(ExceptionHandler::class);

        return $handler->render($request, $e);
    }
}