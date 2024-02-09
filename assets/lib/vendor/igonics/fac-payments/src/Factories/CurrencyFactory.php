<?php

namespace FacPayments\Factories;

use FacPayments\Repositories\InMemory\InMemoryCurrencyRepository;

class CurrencyFactory {

    protected static $instance=null;

    /**
     * @return FacPayments\Contracts\Repositories\ICurrencyRepository
     */
    public static function create(array $config = []){
        if(null==static::$instance){
            static::$instance = new InMemoryCurrencyRepository();
        }
        return static::$instance;
    }
}