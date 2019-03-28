<?php

namespace SitPHP\Services;

use SitPHP\Helpers\Collection;

class ServiceProvider
{
    protected static $services = [];
    protected static $services_by_tag = [];
    protected static $modifiers = [];
    protected static $modifiers_service = [];
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
            $service_class = self::modifyService($service_name);
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

    static function removeService($name, bool $remove_modifiers = true, bool $remove_initializers = true){
        $service = self::$services[$name] ?? null;
        if($service === null){
            return false;
        }
        foreach($service['tags'] as $tag){
            unset(self::$services_by_tag[$tag][$name]);
        }
        unset(self::$services[$name]);
        unset(self::$prepared[$name]);

        if($remove_modifiers){
            self::removeModifiers($name);
        }
        if($remove_initializers){
            self::removeInitializers($name);
        }
        return true;
    }

    static function removeModifiers($service_name){
        unset(self::$modifiers[$service_name]);
        foreach (self::$modifiers_service as $modifier => $service){
            if($service_name == $service){
                unset(self::$modifiers_service[$modifier]);
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

    static function addModifier(string $service_name, $modifier, int $priority = null){
        if(isset(self::$prepared[$service_name])){
            throw new \LogicException('Cannot add modifier to service "'.$service_name.'" : service has already been retrieved');
        }

        if(!isset(self::$modifiers[$service_name])){
            self::$modifiers[$service_name] = new Collection();
        }
        /** @var Collection $modifiers */
        $modifiers =  self::$modifiers[$service_name];
        if(is_array($modifier)){
            foreach ($modifier as $item){
                if(is_array($item)){
                    $class = $item['class'] ?? $item[0];
                    $priority = $item['priority'] ?? $item[1];
                } else {
                    $class = $item;
                    $priority = null;
                }
                self::addModifier($service_name ,$class, $priority);
            }
        } else if(is_string($modifier)) {
            if(isset(self::$modifiers_service[$modifier])){
                throw new \LogicException('Modifier is already in use for service "'.self::$modifiers_service[$modifier].'"');
            }
            $modifiers->add([
                'class' => $modifier,
                'priority' => $priority
            ]);
            $modifiers->sortBy('priority', true);
            self::$modifiers_service[$modifier] = $service_name;
        } else {
            throw new \InvalidArgumentException('Invalid $modifier argument type : expected string or array');
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

    protected static function modifyService($service_name){
        $modifiers = self::getmodifiers($service_name);
        $service_class = self::$services[$service_name]['class'];
        /** @var Modifier $modifier */
        foreach ($modifiers as $modifier){
            if(!is_subclass_of($modifier['class'], Modifier::class)){
                throw  new \InvalidArgumentException('Service "'.$service_name.'" could not be loaded : invalid modifier "'.$modifier['class'].'" (expected instance of '.Modifier::class.')');
            }
            $modifier['class']::initialize($service_class);
            $service_class = $modifier['class'];
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

    protected static function getModifiers(string $service_name) {
        return self::$modifiers[$service_name] ?? [];
    }

    protected static function getAllModifiers(){
        return self::$modifiers;
    }

    protected static function getInitializers(string $service_name = null){
        return self::$initializers[$service_name] ?? [];
    }
    protected static function getAllInitializers(){
        return self::$initializers;
    }
}