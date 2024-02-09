<?php

namespace FacPayments\Factories;

use FacPayments\Repositories\InMemory\InMemoryCountryRepository;

class CountryFactory {

    protected static $instance=null;

    /**
     * @return FacPayments\Contracts\Repositories\ICountryRepository
     */
    public static function create(array $config = []){
        if(null==static::$instance){
            static::$instance = new InMemoryCountryRepository();
        }
        return static::$instance;
    }
}