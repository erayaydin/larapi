<?php

namespace Larapi\Infrastructure\Laravel\Foundation\Exceptions;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Reflector;
use Larapi\Infrastructure\Laravel\Exceptions\LoggerNotFoundException;
use Larapi\Infrastructure\Laravel\Exceptions\NotSupportedMethod;
use Psr\Log\LoggerInterface;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;
use WeakMap;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

final class Handler implements ExceptionHandler
{
    /**
     * The already reported exception map.
     *
     * @var WeakMap
     */
    private WeakMap $reportedExceptionMap;

    /**
     * A list of the exception types that should not be reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected array $dontReport = [];

    public function __construct(
        private readonly Container $container
    ) {
        $this->reportedExceptionMap = new WeakMap;
    }

    /**
     * Report or log an exception.
     *
     * @param Throwable $e
     * @return void
     *
     * @throws Throwable
     */
    public function report(Throwable $e): void
    {
        if (! $this->shouldReport($e)) {
            return;
        }

        $this->reportThrowable($e);
    }

    /**
     * Determine if the exception should be reported.
     *
     * @param Throwable $e
     * @return bool
     */
    public function shouldReport(Throwable $e): bool
    {
        return is_null(Arr::first($this->dontReport, fn ($type) => $e instanceof $type));
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  Request  $request
     * @param Throwable $e
     * @return Response
     *
     * @throws Throwable
     */
    public function render($request, Throwable $e): Response
    {
        if (method_exists($e, 'render')) {
            return $e->render($request);
        }

        if ($e instanceof Responsable) {
            return $e->toResponse($request);
        }

        return match (true) {
            $e instanceof HttpResponseException => $e->getResponse(),
            default => $this->renderExceptionResponse($request, $e),
        };
    }

    public function renderForConsole($output, Throwable $e): never
    {
        throw new NotSupportedMethod("Exception handling for console not supported yet.");
    }

    /**
     * Reports error based on report method on exception or to logger.
     *
     * @param Throwable $e
     * @return void
     *
     * @throws Throwable
     */
    private function reportThrowable(Throwable $e): void
    {
        $this->reportedExceptionMap[$e] = true;

        if (Reflector::isCallable($reportCallable = [$e, 'report']) &&
            $this->container->call($reportCallable) !== false) {
            return;
        }

        $this
            ->newLogger()
            ->error($e->getMessage(), $this->buildExceptionContext($e));
    }

    /**
     * Create a new logger instance.
     *
     * @return LoggerInterface
     * @throws BindingResolutionException
     */
    private function newLogger(): LoggerInterface
    {
        if (! $this->container->bound(LoggerInterface::class)) {
            throw new LoggerNotFoundException;
        }

        return $this->container->make(LoggerInterface::class);
    }

    /**
     * Create the context array for logging the given exception.
     *
     * @param  Throwable  $e
     * @return array
     */
    private function buildExceptionContext(Throwable $e): array
    {
        return array_merge(
            $this->exceptionContext($e),
            ['exception' => $e]
        );
    }

    /**
     * Get the default exception context variables for logging.
     *
     * @param Throwable $e
     * @return array
     */
    private function exceptionContext(Throwable $e): array
    {
        $context = [];

        if (method_exists($e, 'context')) {
            $context = $e->context();
        }

        return $context;
    }

    /**
     * Render a default exception response if any.
     *
     * @param Request $request
     * @param Throwable $e
     */
    private function renderExceptionResponse(Request $request, Throwable $e)
    {
        return $request->expectsJson()
            ? $this->prepareJsonResponse($e)
            : $this->prepareResponse($request, $e);
    }

    /**
     * Prepare a JSON response for the given exception.
     *
     * @param Throwable $e
     * @return JsonResponse
     */
    private function prepareJsonResponse(Throwable $e): JsonResponse
    {
        $statusCode = $e instanceof HttpExceptionInterface
            ? $e->getStatusCode()
            : 500;

        $headers = $e instanceof HttpExceptionInterface
            ? $e->getHeaders()
            : [];

        return new JsonResponse(
            $this->convertExceptionToArray($e),
            $statusCode,
            $headers,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * Prepare a response for the given exception.
     *
     * @param Request $request
     * @param Throwable $e
     * @return Response|RedirectResponse
     */
    private function prepareResponse(Request $request, Throwable $e): Response|RedirectResponse
    {
        // TODO: Check debug mode
        if (! $e instanceof HttpExceptionInterface) {
            return $this->toIlluminateResponse($this->convertExceptionToResponse($e), $e)->prepare($request);
        }

        return $this->toIlluminateResponse(
            $this->renderHttpException($e), $e
        )->prepare($request);
    }

    /**
     * Convert the given exception to an array.
     *
     * @param Throwable $e
     * @return array
     */
    private function convertExceptionToArray(Throwable $e): array
    {
        // TODO: Check debug mode
        return [
            'message' => $e->getMessage(),
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => collect($e->getTrace())->map(fn ($trace) => Arr::except($trace, ['args']))->all(),
        ];
    }

    /**
     * Create a Symfony response for the given exception.
     *
     * @param Throwable $e
     * @return SymfonyResponse
     */
    protected function convertExceptionToResponse(Throwable $e): SymfonyResponse
    {
        $statusCode = $e instanceof HttpExceptionInterface
            ? $e->getStatusCode()
            : 500;

        $headers = $e instanceof HttpExceptionInterface
            ? $e->getHeaders()
            : [];

        return new SymfonyResponse(
            $this->renderExceptionContent($e),
            $statusCode,
            $headers
        );
    }

    /**
     * Get the response content for the given exception.
     *
     * @param Throwable $e
     * @return string
     */
    private function renderExceptionContent(Throwable $e): string
    {
        return $this->renderExceptionWithSymfony($e);
    }

    /**
     * Render an exception to a string using Symfony.
     *
     * @param Throwable $e
     * @return string
     */
    private function renderExceptionWithSymfony(Throwable $e): string
    {
        // TODO: Check debug mode
        $renderer = new HtmlErrorRenderer(true);

        return $renderer->render($e)->getAsString();
    }

    /**
     * Map the given exception into an Illuminate response.
     *
     * @param SymfonyResponse $response
     * @param Throwable $e
     * @return Response|RedirectResponse
     */
    private function toIlluminateResponse(SymfonyResponse $response, Throwable $e): Response|RedirectResponse
    {
        if ($response instanceof SymfonyRedirectResponse) {
            $response = new RedirectResponse(
                $response->getTargetUrl(), $response->getStatusCode(), $response->headers->all()
            );
        } else {
            $response = new Response(
                $response->getContent(), $response->getStatusCode(), $response->headers->all()
            );
        }

        return $response->withException($e);
    }

    /**
     * Render the given HttpException.
     *
     * @param HttpExceptionInterface $e
     * @return SymfonyResponse
     */
    private function renderHttpException(HttpExceptionInterface $e): SymfonyResponse
    {
        return $this->convertExceptionToResponse($e);
    }
}