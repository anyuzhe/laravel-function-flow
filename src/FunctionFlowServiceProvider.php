<?php

namespace Anyuzhe\LaravelFunctionFlow;

use Anyuzhe\LaravelFunctionFlow\Flow;
use Anyuzhe\LaravelFunctionFlow\Mapping;
use Illuminate\Support\ServiceProvider;
use Anyuzhe\LaravelFunctionFlow\Console\GeneratorCommand;

class FunctionFlowServiceProvider extends ServiceProvider
{

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(
            'command.function-flow.generate',
            function ($app) {
                return new GeneratorCommand();
            }
        );

        $this->app->bind(Flow::class, function ($app) {
            $mapping = new Mapping(config('function-flow'));
            return new Flow($mapping,$app);
        });

        $this->commands('command.function-flow.generate');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['command.function-flow.generate',Flow::class];
    }
}
