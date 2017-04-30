<?php

namespace Anyuzhe\LaravelFunctionFlow;

use Illuminate\Support\Facades\Facade;

class FlowFacade extends Facade
{
    /**
     * Get the binding in the IoC container
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return Flow::class;
    }
}