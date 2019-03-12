<?php

namespace SitPHP\Services;

use SitPHP\Helpers\Collection;

class ServiceProvider
{
    protected static $services = [];
    protected static $services_by_tag = [];
    protected static $decorators = [];
    protected static $decorators_service = [];
    protected static $initializers = [];
    protected static $initializers_service = [];
    protected static $prepared = [];

    static function getService($service_name){
        $service = self::$services[$service_name] ?? null;
        // Service doesnt exist, return null
        if($service === null){
            return null;
        }
        if(!isset(self::$prepared[$service_name])){
            $service_class = self::decorateService($service_name);
            self::initializeService($service_name, $service_class);
            self::$prepared[$service_name] = $service_class;
        }

        return self::$prepared[$service_name];
    }

    static function setService(string $name, string $class, $tag = null){
        if(isset($tag)){
            if(is_array($tag)){
                $tags = $tag;
            } else if(is_string($tag)){
                $tags = [$tag];
            } else {
                throw new \InvalidArgumentException('Invalid $tag argument type : expected string or array');
            }
        } else {
            $tags = [];
        }
        $service_definition = [
            'name' => $name,
            'class' => $class,
            'tags' => $tags
        ];
        self::$services[$name] = $service_definition;
        foreach ($tags as $tag){
            self::$services_by_tag[$tag][$name] = $service_definition;
        }
    }

    static function removeService($name, bool $remove_decorators = true, bool $remove_initializers = true){
        $service = self::$services[$name] ?? null;
        if($service === null){
            return false;
        }
        foreach($service['tags'] as $tag){
            unset(self::$services_by_tag[$tag][$name]);
        }
        unset(self::$services[$name]);
        unset(self::$prepared[$name]);

        if($remove_decorators){
            self::removeDecorators($name);
        }
        if($remove_initializers){
            self::removeInitializers($name);
        }
        return true;
    }

    static function removeDecorators($service_name){
        unset(self::$decorators[$service_name]);
        foreach (self::$decorators_service as $decorator => $service){
            if($service_name == $service){
                unset(self::$decorators_service[$decorator]);
            }
        }
    }

    static function removeInitializers($service_name){
        unset(self::$initializers[$service_name]);
        foreach (self::$initializers_service as $initializer => $service){
            if($service_name == $service){
                unset(self::$initializers_service[$initializer]);
            }
        }
    }

    static function getServicesByTag(string $tag){
        $services = [];
        foreach(self::$services_by_tag[$tag] as $service){
            $services[] = self::getService($service['name']);
        }
        return $services;
    }

    static function addDecorator(string $service_name, $decorator, int $priority = null){
        if(isset(self::$prepared[$service_name])){
            throw new \LogicException('Cannot add decorator to service "'.$service_name.'" : service has already been retrieved');
        }

        if(!isset(self::$decorators[$service_name])){
            self::$decorators[$service_name] = new Collection();
        }
        /** @var Collection $decorators */
        $decorators =  self::$decorators[$service_name];
        if(is_array($decorator)){
            foreach ($decorator as $item){
                if(is_array($item)){
                    $class = $item['class'] ?? $item[0];
                    $priority = $item['priority'] ?? $item[1];
                } else {
                    $class = $item;
                    $priority = null;
                }
                self::addDecorator($service_name ,$class, $priority);
            }
        } else if(is_string($decorator)) {
            if(isset(self::$decorators_service[$decorator])){
                throw new \LogicException('Decorator is already in use for service "'.self::$decorators_service[$decorator].'"');
            }
            $decorators->add([
                'class' => $decorator,
                'priority' => $priority
            ]);
            $decorators->sortBy('priority', true);
            self::$decorators_service[$decorator] = $service_name;
        } else {
            throw new \InvalidArgumentException('Invalid $decorator argument type : expected string or array');
        }
    }

    static function addInitializer(string $service_name, $initializer, int $priority = null){
        if(isset(self::$prepared[$service_name])){
            throw new \LogicException('Cannot add initializer to service "'.$service_name.'" : service has already been retrieved');
        }

        if(!isset(self::$initializers[$service_name])){
            self::$initializers[$service_name] = new Collection();
        }
        /** @var Collection $initializers */
        $initializers =  self::$initializers[$service_name];
        if(is_array($initializer)){
            foreach ($initializer as $item){
                if(is_array($item)){
                    $class = $item['class'] ?? $item[0];
                    $priority = $item['priority'] ?? $item[1];
                } else {
                    $class = $item;
                    $priority = null;
                }
                self::addInitializer($service_name, $class, $priority);
            }
        } else if(is_string($initializer)) {
            if(isset(self::$initializers_service[$initializer])){
                throw new \LogicException('Initializer is already in use for service "'.self::$initializers_service[$initializer].'"');
            }
            $initializers->add([
                'class' => $initializer,
                'priority' => $priority
            ]);
            $initializers->sortBy('priority', true);
            self::$initializers_service[$initializer] = $service_name;
        } else {
            throw new \InvalidArgumentException('Invalid $initializer argument type : expected string or array');
        }
    }

    protected static function decorateService($service_name){
        $decorators = self::getDecorators($service_name);
        $service_class = self::$services[$service_name]['class'];
        /** @var Decorator $decorator */
        foreach ($decorators as $decorator){
            if(!is_subclass_of($decorator['class'], Decorator::class)){
                throw  new \InvalidArgumentException('Service "'.$service_name.'" could not be loaded : invalid decorator "'.$decorator['class'].'" (expected instance of '.Decorator::class.')');
            }
            $decorator['class']::initialize($service_class);
            $service_class = $decorator['class'];
        }
        return $service_class;
    }

    protected static function initializeService($service_name, $service_class){
        $initializers = self::getInitializers($service_name);
        foreach ($initializers as $initializer){
            if(!is_subclass_of($initializer['class'], Initializer::class)){
                throw  new \InvalidArgumentException('Service "'.$service_name.'" could not be loaded : invalid initializer "'.$initializer['class'].'" (expected instance of '.Initializer::class.')');
            }
            $initializer['class']::initialize($service_class);
        }
        foreach ($initializers as $initializer){
            if(method_exists($initializer['class'], 'setup')){
                $initializer['class']::setup();
            }
        }
        foreach ($initializers as $initializer){
            if(method_exists($initializer['class'], 'register')){
                $initializer['class']::register();
            }
        }
        foreach ($initializers as $initializer){
            if(method_exists($initializer['class'], 'tweak')){
                $initializer['class']::tweak();
            }
        }
    }

    protected static function getDecorators(string $service_name = null) {
        if(!isset($service_name)){
            return self::$decorators;
        }
        return self::$decorators[$service_name] ?? [];
    }

    protected static function getInitializers(string $service_name = null){
        if(!isset($service_name)){
            return self::$initializers;
        }
        return self::$initializers[$service_name] ?? [];
    }
}