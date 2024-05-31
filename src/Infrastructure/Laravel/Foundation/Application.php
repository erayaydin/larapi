<?php

namespace Larapi\Infrastructure\Laravel\Foundation;

use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container as IlluminateContainer;
use Illuminate\Contracts\Foundation\Application as LaravelApplication;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Larapi\Infrastructure\Laravel\Contracts\Application as LarapiApplication;
use Larapi\Infrastructure\Laravel\Contracts\HandlesHttp;
use Larapi\Infrastructure\Laravel\Exceptions\ConfigInstanceNotFoundException;
use Larapi\Infrastructure\Laravel\Exceptions\EnvInstanceNotFoundException;
use Larapi\Infrastructure\Laravel\Exceptions\HttpKernelNotFoundException;
use Larapi\Infrastructure\Laravel\Exceptions\NotSupportedMethod;
use Psr\Container\ContainerInterface;

use function Illuminate\Filesystem\join_paths;

use const PHP_SAPI;

final class Application extends Container implements LarapiApplication, HandlesHttp
{
    /**
     * The Larapi version.
     *
     * @return string
     */
    const VERSION = '0.1.0-RC1';

    /**
     * Core class aliases in the container.
     */
    const CORE_CONTAINER_ALIASES = [
        'app' => [
            self::class,
            IlluminateContainer::class,
            LaravelApplication::class,
            LarapiApplication::class,
            ContainerInterface::class
        ],
    ];

    /**
     * The application namespace.
     *
     * @var string
     */
    const NAMESPACE = "Larapi\\";

    /**
     * Indicates if the application is running in the console.
     *
     * @var bool|null
     */
    private ?bool $isRunningInConsole = null;

    /**
     * All the registered service providers.
     *
     * @var array<string, ServiceProvider>
     */
    private array $serviceProviders = [];

    /**
     * The names of the loaded service providers.
     *
     * @var array
     */
    private array $loadedProviders = [];

    /**
     * The deferred services and their providers.
     *
     * @var array
     */
    private array $deferredServices = [];

    /**
     * Indicates if the application has "booted".
     *
     * @var bool
     */
    private bool $booted = false;

    /**
     * The array of booting callbacks.
     *
     * @var callable[]
     */
    private array $bootingCallbacks = [];

    /**
     * The array of booted callbacks.
     *
     * @var callable[]
     */
    private array $bootedCallbacks = [];

    /**
     * Indicates if the application has been bootstrapped before.
     *
     * @var bool
     */
    private bool $hasBeenBootstrapped = false;

    /**
     * The array of terminating callbacks.
     *
     * @var callable[]
     */
    private array $terminatingCallbacks = [];

    /**
     * Create a new Larapi application instance.
     *
     * @param string $basePath
     * @return void
     */
    public function __construct(
        private readonly string $basePath
    ) {
        $this->bindPaths();
        $this->registerBaseBindings();
        $this->registerBaseServiceProviders();
        $this->registerCoreContainerAliases();
    }

    /**
     * Get the version number of the application.
     *
     * @return string
     */
    public function version(): string
    {
        return Application::VERSION;
    }

    /**
     * Get the path to the application source directory.
     *
     * @param  string  $path
     * @return string
     */
    public function path(string $path = ''): string
    {
        return join_paths($this->basePath('src'), $path);
    }

    /**
     * Get the base path of the Larapi application.
     *
     * @param  string  $path
     * @return string
     */
    public function basePath($path = ''): string
    {
        return join_paths($this->basePath, $path);
    }

    /**
     * Get the path to the bootstrap directory.
     *
     * @param  string  $path
     * @return string
     */
    public function bootstrapPath($path = ''): string
    {
        return join_paths($this->basePath('bootstrap'), $path);
    }

    /**
     * Get the path to the application configuration files.
     *
     * @param string $path
     * @return never
     */
    public function configPath($path = ''): never
    {
        throw new NotSupportedMethod("`configPath` method not supported in Larapi.");
    }

    /**
     * Get the path to the database directory.
     *
     * @param string $path
     * @return never
     */
    public function databasePath($path = ''): never
    {
        throw new NotSupportedMethod("`databasePath` method not supported in Larapi.");
    }

    /**
     * Get the path to the language files.
     *
     * @param string $path
     * @return never
     */
    public function langPath($path = ''): never
    {
        throw new NotSupportedMethod("`langPath` method not supported in Larapi.");
    }

    /**
     * Get the path to the public directory.
     *
     * @param  string  $path
     * @return string
     */
    public function publicPath($path = ''): string
    {
        return join_paths($this->basePath('public'), $path);
    }

    /**
     * Get the path to the resource directory.
     *
     * @param string $path
     * @return never
     */
    public function resourcePath($path = ''): never
    {
        throw new NotSupportedMethod("`resourcePath` method not supported in Larapi.");
    }

    /**
     * Get the path to the storage directory.
     *
     * @param  string  $path
     * @return string
     */
    public function storagePath($path = ''): string
    {
        return join_paths($this->basePath('storage'), $path);
    }

    /**
     * Get or check the current application environment.
     *
     * @param  string|array  ...$environments
     * @return string|bool
     */
    public function environment(...$environments): bool|string
    {
        if (! $this->bound('env'))
            throw new EnvInstanceNotFoundException;

        if (count($environments) > 0) {
            $patterns = is_array($environments[0]) ? $environments[0] : $environments;

            return Str::is($patterns, $this['env']);
        }

        return $this['env'];
    }

    /**
     * Determine if the application is running in the console.
     *
     * @return bool
     */
    public function runningInConsole(): bool
    {
        if ($this->isRunningInConsole === null) {
            $this->isRunningInConsole = PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
        }

        return $this->isRunningInConsole;
    }

    /**
     * Determine if the application is running unit tests.
     *
     * @return bool
     */
    public function runningUnitTests(): bool
    {
        return $this->bound('env') && $this['env'] === 'testing';
    }

    /**
     * Determine if the application is running with debug mode enabled.
     *
     * @return bool
     */
    public function hasDebugModeEnabled(): bool
    {
        return $this->bound('config') && $this['config']->get('app.debug');
    }

    /**
     * Get an instance of the maintenance mode manager implementation.
     *
     * @return never
     */
    public function maintenanceMode(): never
    {
        throw new NotSupportedMethod("`maintenanceMode` method not supported in Larapi.");
    }

    /**
     * Determine if the application is currently down for maintenance.
     *
     * @return bool
     */
    public function isDownForMaintenance(): false
    {
        return false;
    }

    /**
     * Register all the configured providers.
     *
     * @return void
     */
    public function registerConfiguredProviders()
    {
        // TODO: Implement registerConfiguredProviders() method.
    }

    /**
     * Register a service provider with the application.
     *
     * @param  ServiceProvider|string  $provider
     * @param  bool  $force
     * @return ServiceProvider
     */
    public function register($provider, $force = false): ServiceProvider
    {
        if (($registered = $this->getProvider($provider)) && ! $force) {
            return $registered;
        }

        // If the given "provider" is a string, we will resolve it, passing in the
        // application instance automatically for the developer. This is simply
        // a more convenient way of specifying your service provider classes.
        if (is_string($provider)) {
            $provider = $this->resolveProvider($provider);
        }

        $provider->register();

        // If there are bindings / singletons set as properties on the provider we
        // will spin through them and register them with the application, which
        // serves as a convenience layer while registering a lot of bindings.
        if (property_exists($provider, 'bindings')) {
            foreach ($provider->bindings as $key => $value) {
                $this->bind($key, $value);
            }
        }

        if (property_exists($provider, 'singletons')) {
            foreach ($provider->singletons as $key => $value) {
                $key = is_int($key) ? $value : $key;

                $this->singleton($key, $value);
            }
        }

        $this->markAsRegistered($provider);

        // If the application has already booted, we will call this boot method on
        // the provider class, so it has an opportunity to do its boot logic and
        // will be ready for any usage by this developer's application logic.
        if ($this->isBooted()) {
            $this->bootProvider($provider);
        }

        return $provider;
    }

    /**
     * Register a deferred provider and service.
     *
     * @param  string  $provider
     * @param  string|null  $service
     * @return void
     */
    public function registerDeferredProvider($provider, $service = null): void
    {
        // Once the provider that provides the deferred service has been registered we
        // will remove it from our local list of the deferred services with related
        // providers so that this container does not try to resolve it out again.
        if ($service) {
            unset($this->deferredServices[$service]);
        }

        $this->register($instance = new $provider($this));

        if (! $this->isBooted()) {
            $this->booting(function () use ($instance) {
                $this->bootProvider($instance);
            });
        }
    }

    /**
     * Resolve a service provider instance from the class name.
     *
     * @param  string  $provider
     * @return ServiceProvider
     */
    public function resolveProvider($provider): ServiceProvider
    {
        return new $provider($this);
    }

    /**
     * Boot the application's service providers.
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->isBooted()) {
            return;
        }

        // Once the application has booted we will also fire some "booted" callbacks
        // for any listeners that need to do work after this initial booting gets
        // finished. This is useful when ordering the boot-up processes we run.
        $this->fireCallbacks($this->bootingCallbacks);

        array_walk($this->serviceProviders, function ($p) {
            $this->bootProvider($p);
        });

        $this->booted = true;

        $this->fireCallbacks($this->bootedCallbacks);
    }

    /**
     * Register a new boot listener.
     *
     * @param  callable  $callback
     * @return void
     */
    public function booting($callback): void
    {
        $this->bootingCallbacks[] = $callback;
    }

    /**
     * Register a new "booted" listener.
     *
     * @param  callable  $callback
     * @return void
     */
    public function booted($callback): void
    {
        $this->bootedCallbacks[] = $callback;

        if ($this->isBooted()) {
            $callback($this);
        }
    }

    /**
     * Run the given array of bootstrap classes.
     *
     * @param string[] $bootstrappers
     * @return void
     * @throws BindingResolutionException
     */
    public function bootstrapWith(array $bootstrappers): void
    {
        $this->hasBeenBootstrapped = true;

        foreach ($bootstrappers as $bootstrapper) {
            $this->make($bootstrapper)->bootstrap($this);
        }
    }

    public function getLocale(): string
    {
        if (! $this->bound('config')) {
            throw new ConfigInstanceNotFoundException;
        }

        return $this['config']->get('app.locale');
    }

    /**
     * Get the application namespace.
     *
     * @return string
     */
    public function getNamespace(): string
    {
        return Application::NAMESPACE;
    }

    /**
     * Get the registered service provider instances if any exist.
     *
     * @param ServiceProvider|string  $provider
     * @return array
     */
    public function getProviders($provider): array
    {
        $name = is_string($provider) ? $provider : get_class($provider);

        return Arr::where($this->serviceProviders, fn ($value) => $value instanceof $name);
    }

    /**
     * Determine if the application has been bootstrapped before.
     *
     * @return bool
     */
    public function hasBeenBootstrapped(): bool
    {
        return $this->hasBeenBootstrapped;
    }

    /**
     * Load and boot all the remaining deferred providers.
     *
     * @return void
     */
    public function loadDeferredProviders(): void
    {
        // We will simply spin through each of the deferred providers and register each
        // one and boot them if the application has booted. This should make each of
        // the remaining services available to this application for immediate use.
        foreach ($this->deferredServices as $service => $provider) {
            $this->loadDeferredProvider($service);
        }

        $this->deferredServices = [];
    }

    public function setLocale($locale)
    {
        throw new NotSupportedMethod("`setLocale` method does not supported in Larapi.");
    }

    /**
     * Determine if middleware has been disabled for the application.
     *
     * @return bool
     */
    public function shouldSkipMiddleware(): false
    {
        return false;
    }

    /**
     * Register a terminating callback with the application.
     *
     * @param  callable|string  $callback
     * @return $this
     */
    public function terminating($callback): Application
    {
        $this->terminatingCallbacks[] = $callback;

        return $this;
    }

    /**
     * Terminate the application.
     *
     * @return void
     */
    public function terminate(): void
    {
        foreach ($this->terminatingCallbacks as $callback) {
            $this->call($callback);
        }
    }

    /**
     * Handle the incoming HTTP request and send the response to the client.
     *
     * @param Request $request
     * @return void
     * @throws BindingResolutionException
     * @throws HttpKernelNotFoundException
     */
    public function handleRequest(Request $request): void
    {
        if (! $this->bound(HttpKernel::class)) {
            throw new HttpKernelNotFoundException;
        }

        /** @var HttpKernel $kernel */
        $kernel = $this->make(HttpKernel::class);

        $response = $kernel->handle($request)->send();

        $kernel->terminate($request, $response);
    }

    /**
     * Bind all the application paths in the container.
     *
     * @return void
     */
    private function bindPaths(): void
    {
        $this->instance('path', $this->path());
        $this->instance('path.base', $this->basePath());
        $this->instance('path.public', $this->publicPath());
        $this->instance('path.storage', $this->storagePath());
        $this->instance('path.bootstrap', $this->bootstrapPath());
    }

    /**
     * Register the basic bindings into the container.
     *
     * @return void
     */
    private function registerBaseBindings(): void
    {
        Application::setInstance($this);

        $this->instance('app', $this);
        $this->instance(Container::class, $this);
    }

    /**
     * Register all the base service providers.
     *
     * @return void
     */
    private function registerBaseServiceProviders(): void
    {
        // TODO: register base services for all interfaces
    }

    /**
     * Register the core class aliases in the container.
     *
     * @return void
     */
    private function registerCoreContainerAliases(): void
    {
        foreach (Application::CORE_CONTAINER_ALIASES as $key => $aliases) {
            foreach ($aliases as $alias) {
                $this->alias($key, $alias);
            }
        }
    }

    /**
     * Get the registered service provider instance if it exists.
     *
     * @param ServiceProvider|string  $provider
     * @return ServiceProvider|null
     */
    private function getProvider(ServiceProvider|string $provider): ServiceProvider|null
    {
        $name = is_string($provider) ? $provider : get_class($provider);

        return $this->serviceProviders[$name] ?? null;
    }

    /**
     * Mark the given provider as registered.
     *
     * @param ServiceProvider $provider
     * @return void
     */
    private function markAsRegistered(ServiceProvider $provider): void
    {
        $class = get_class($provider);

        $this->serviceProviders[$class] = $provider;

        $this->loadedProviders[$class] = true;
    }

    /**
     * Determine if the application has booted.
     *
     * @return bool
     */
    private function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Boot the given service provider.
     *
     * @param ServiceProvider $provider
     * @return void
     */
    private function bootProvider(ServiceProvider $provider): void
    {
        $provider->callBootingCallbacks();

        if (method_exists($provider, 'boot')) {
            $this->call([$provider, 'boot']);
        }

        $provider->callBootedCallbacks();
    }

    /**
     * Load the provider for a deferred service.
     *
     * @param  string  $service
     * @return void
     */
    private function loadDeferredProvider(string $service): void
    {
        if (! $this->isDeferredService($service)) {
            return;
        }

        $provider = $this->deferredServices[$service];

        // If the service provider has not already been loaded and registered we can
        // register it with the application and remove the service from this list
        // of deferred services, since it will already be loaded on subsequent.
        if (! isset($this->loadedProviders[$provider])) {
            $this->registerDeferredProvider($provider, $service);
        }
    }

    /**
     * Determine if the given service is a deferred service.
     *
     * @param  string  $service
     * @return bool
     */
    private function isDeferredService(string $service): bool
    {
        return isset($this->deferredServices[$service]);
    }

    /**
     * Call the callbacks with the application parameter.
     *
     * @param  callable[]  $callbacks
     * @return void
     */
    private function fireCallbacks(array $callbacks): void
    {
        foreach ($callbacks as $callback) {
            $callback($this);
        }
    }
}