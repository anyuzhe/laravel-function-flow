<?php
/**
 * Created by PhpStorm.
 * User: 68067
 * Date: 2017-4-9
 * Time: 12:27
 */

namespace Anyuzhe\LaravelFunctionFlow;
use Illuminate\Support\Arr;

class Mapping
{
    protected $mapping;

    public function __construct()
    {
        $this->mapping = config('function-flow');
    }

    public function get($key, $default = null)
    {
        return Arr::get($this->mapping, $key, $default);
    }

    public function has($key)
    {
        return Arr::has($this->mapping, $key);
    }

}