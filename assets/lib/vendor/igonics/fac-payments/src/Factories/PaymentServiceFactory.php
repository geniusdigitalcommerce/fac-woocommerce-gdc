<?php

namespace FacPayments\Factories;

use FacPayments\Services\V2\PaymentService as V2PaymentService;
use FacPayments\Services\V1\PaymentService as V1PaymentService;

use FacPayments\Contracts\Repositories\ICacheRepository;
use FacPayments\Contracts\Repositories\ILoggingRepository;

use FacPayments\Repositories\Cache\JsonFileCacheRepository;
use FacPayments\Repositories\Logging\NullLoggingRepository;
use FacPayments\Repositories\Logging\ConsoleLoggingRepository;
use FacPayments\Repositories\Logging\HtmlLoggingRepository;
use FacPayments\Entities\Configuration\FacConfig;

use FacPayments\External\Helpers\Env;
use FacPayments\External\Helpers\Arr;

class PaymentServiceFactory {
    public static function create(array $config = []){

        $config = array_merge([
            'cache'=>'file',
            'logger'=>'null',
            'config'=>null,
            'version'=>2
        ],$config);

        

        $cache = null;
        $logger = null;
        $service = null;

        if(
            is_object($config['cache']) && 
            is_a($config['cache'],ICacheRepository::class)
        ){
            $cache = $config['cache'];
        }else{
            switch($config['cache']){
                case 'file': 
                default: $cache = new JsonFileCacheRepository();
                    break;
            }
        }

        if(
            is_object($config['logger']) && 
            is_a($config['logger'],ILoggingRepository::class)
        ){
            $logger = $config['logger'];
        }else{
            switch($config['logger']){
                case 'console':  $logger = new ConsoleLoggingRepository(); break;
                case 'html'   :  $logger = new HtmlLoggingRepository(); break;
                case 'null'   : 
                default       :  $logger = new NullLoggingRepository();
                    break;
            }
        }
        if(isset($config['loggerLogLevel'])){
            $logger->setLogLevel($config['loggerLogLevel']);
        }
        

        

        if($config['config'] != null && is_array($config['config'])){
            $config['config'] = new FacConfig($config['config']);
        }

        switch($config['version']){
            case 1: $service = new V1PaymentService(
                    $config['config'],
                    $logger,
                    $cache
                );
                break;
            case 2:
            default: $service = new V2PaymentService(
                $config['config'],
                $logger,
                $cache
            );
                break;
        }

        return $service;
    }

    protected static $configKeyMap = [
        
    ];

    

    public static function createConfigFromEnv(){
        $config = [];
        
        foreach(static::$configKeyMap as $envKey=>$configKey){
            $value = Env::get($envKey);
            if(!empty($value)){
                Arr::nestedAssignment($config,$configKey,$value);
            }
        }

        return $config;
    }

    public static function createFromEnv(){
        return static::create(
            static::createConfigFromEnv()
        );
    }
}