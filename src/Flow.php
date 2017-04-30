<?php
/**
 * Created by PhpStorm.
 * User: 68067
 * Date: 2017-4-9
 * Time: 12:24
 */

namespace Anyuzhe\LaravelFunctionFlow;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class Flow
{
    protected $mapping;
    protected $parameters = [
        'next' => true,
        'skip' => false,
    ];
    protected $classes = [];
    protected $app;
    protected $lastFunc = null;
    protected $lastFuncIsGroup = false;

    public function __construct(Mapping $mapping,Container $app)
    {
        $this->mapping = $mapping;
        $this->app = $app;
    }

    public function setParam($key,$vue=null)
    {
        if(is_array($key)){
            foreach ($key as $k=>$v) {
                $this->parameters[$k] = $v;
            }
        }else{
            $this->parameters[$key] = $vue;
        }
        return $this;
    }


    public function getParam($key,$default=null)
    {
        if(is_array($key)){
            $res = null;
            foreach ($key as $k=>$v) {
                $res[$v] = isset($this->parameters[$v])?$this->parameters[$v]:$default;
            }
            return $res;
        }else{
            return isset($this->parameters[$key])?$this->parameters[$key]:$default;
        }
    }

    //设置最后执行的方法
    public function setLastFunc($func,$isGroup=false)
    {
        $this->lastFunc = $func;
        $this->lastFuncIsGroup = $isGroup;
        return $this;
    }

    //主要调用函数 进行方法 顺序化处理
    public function flow(array $array)
    {
        $mtime = explode(' ',microtime());
        $start_time = $mtime[1]+$mtime[0];

        foreach ($array as $key=>$value) {
            //是否进行下一个循环
            if($this->parameters['next']) {
                if($this->parameters['skip'] > 0){
//                if(is_numeric($this->parameters['skip']) && $this->parameters['skip'] > 0){
                    $this->parameters['skip']--;
                    continue; //跳转点  设置了跳转次数
                }
                $this->resolveFunc($value);
            }
        }
        if($this->lastFunc){
            if($this->lastFuncIsGroup){
                foreach ($this->lastFunc as $item) {
                    $this->resolveFunc($item);
                }
            }else{
                $this->resolveFunc($this->lastFunc);
            }
        }

        //计算运行时间
        $mtime = explode(' ',microtime());
        $ms = (($mtime[1]+$mtime[0]) - $start_time) * 1000;
        $this->parameters['run_time_ms'] = $ms;
        return $this->parameters;
    }

    protected function resolveFunc($value){
        //从类 映射表中 获取类
        $method_arr = explode('/', $value[0]);
        $method = count($method_arr) > 1 ? $method_arr[1] : $method_arr[0];
        if ($this->parameters['skip'] != false && $this->parameters['skip'] !== $method){
            return;//跳转点 设置了具体方法名
        }

        $class = $this->getClassObj($method_arr);
        $input = isset($value[1]) ? $value[1] : [];
        $parameters = $this->getParameter($input, $class, $method);//获取需要的参数

        $need = $this->checkCacheTime($value);
        if($need){
            $key = $this->getCacheKeyStr($parameters, $class, $method);
            $cache = Cache:: get($key);
        }else{
            $cache = null;
            $key = false;
        }

        if (!$cache) {
            $response = $this->dispatch($parameters, $class, $method);
            $this->saveResponse($response, $key, $need);
        } else {
            $this->saveResponse(json_decode($cache, true));
        }
    }

    //获取需要缓存的时间
    protected function checkCacheTime($arr)
    {
        if(count($arr)<3){
            return null;
        }
        $key = $arr[2];
        return $key?(int)$key:false;
    }

    protected function getCache($parameters, $class, $method)
    {
    }

    //获取 方法类 实例
    protected function getClassObj($method_arr)
    {
        $alias = $this->getClassKeyOrDefault($method_arr);
        if(!$alias){
            throw new \Exception("function-flow alias not find");
        }

        if(isset($this->classes[$alias]) && $this->classes[$alias]){
            return $this->classes[$alias];
        }
        $class_key = $this->mapping->get($alias);

        if(!$class_key){
            throw new \Exception("function-flow class not find");
        }

        if(class_exists($class_key)){
            $class = $this->app->make($class_key);
            $this->classes[$alias] = $class;
            return $class;
        }else{
            throw new \Exception("function-flow $class_key not class");
        }
    }

    //获取使用类名 对应的服务容器中的类名
    protected function getClassKeyOrDefault($method_arr)
    {
        if(count($method_arr)>1){
            if($this->mapping->has($method_arr[0])){
                return $method_arr[0];
            }
        }else{
            return 'Default';
        }
    }

    //保存输出参数
    public function saveResponse($response, $key=null, $time=0)
    {
        if($key){
            $cache_value = json_encode($response);
            if($time > 0){
                Cache::put($key, $cache_value, $time);
            }else{
                Cache::forever($key, $cache_value);
            }
        }
        if($response && is_array($response) && !empty($response)){
            $this->parameters = array_merge($this->parameters,$response);
        }
    }



    //--------------------------以下是方法相关的依赖解决方法-----------------------------------
    protected function dispatch($parameters, $class, $method)
    {
        if (method_exists($class, 'callAction')) {
            return $class->callAction($method, $parameters);
        }

        return $class->{$method}(...array_values($parameters));
    }

    protected function getCacheKeyStr($parameters, $class, $method)
    {
        foreach ($parameters as &$parameter) {
            if($parameter===null){
                $parameter = 'NULL';
            }elseif($parameter===false){
                $parameter = 'FALSE';
            }elseif($parameter===true){
                $parameter = 'TRUE';
            }elseif(is_array($parameter)){
                $parameter = json_encode($parameter);
            }elseif(is_callable($parameter) || is_object($parameter)){
                $parameter = 'OBJ';
            }
        }
        return get_class($class) . $method . implode('-',$parameters);
    }

    protected function getParameter($parameters, $class, $method)
    {
        $parameters = $this->resolveClassMethodDependencies(
            $this->parametersWithoutNulls($parameters), $class, $method
        );
        return $parameters;
    }

    protected function resolveClassMethodDependencies(array $parameters, $instance, $method)
    {
        if (! method_exists($instance, $method)) {
            throw new \Exception("function-flow method not find");
        }

        return $this->resolveMethodDependencies(
            $parameters, new \ReflectionMethod($instance, $method)
        );
    }

    //过滤没null的参数
    public function parametersWithoutNulls($parameters)
    {
        return array_filter($parameters, function ($p) {
            return ! is_null($p);
        });
    }

    //得到方法中的依赖
    public function resolveMethodDependencies(array $parameters, \ReflectionFunctionAbstract $reflector)
    {
        $results = [];

        $instanceCount = 0;

        foreach ($reflector->getParameters() as $key => $parameter) {
            $instance = $this->transformDependency(
                $parameter, $parameters
            );

            if (! is_null($instance)) {
                $instanceCount++;

                $results[] = $instance;
            } else {
                try {
                    $results[] = isset($parameters[$parameter->getName()])
                        ? $parameters[$parameter->getName()] : $parameter->getDefaultValue();
                }catch(\ReflectionException $e)
                {
                    $results[] = null;
                }
//判断引用传值代码   无效
//                try {
//                    if(isset($parameters[$parameter->getName()])){
//                        if($parameter->isPassedByReference()){
//                            $results[] = &$parameters[$parameter->getName()];
//                        }else{
//                            $results[] = $parameters[$parameter->getName()];
//                        }
//                    }else{
//                        $results[] = $parameter->getDefaultValue();
//                    }
//                }catch(\ReflectionException $e)
//                {
//                    $results[] = null;
//                }
            }
        }

        return $results;
    }

    protected function transformDependency(\ReflectionParameter $parameter, $parameters)
    {
        $class = $parameter->getClass();
        $name = $parameter->getName();

        // If the parameter has a type-hinted class, we will check to see if it is already in
        // the list of parameters. If it is we will just skip it as it is probably a model
        // binding and we do not want to mess with those; otherwise, we resolve it here.
        if ($class && ! $this->alreadyInParameters($class->name, $parameters)) {
            return $this->app->make($class->name);
        }elseif(isset($this->parameters[$name])){
            return $this->parameters[$name];
        }else{
            return null;
        }
    }

    protected function alreadyInParameters($class, array $parameters)
    {
        return ! is_null(Arr::first($parameters, function ($value) use ($class) {
            return $value instanceof $class;
        }));
    }
}