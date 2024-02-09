<?php

namespace FacPayments\Entities\Configuration;

use FacPayments\Exceptions\InvalidConfiguration;

class KountConfig extends Configuration{
    public $merchantId;
    public $enabled=true;
    public $testMode=true;

    public function __construct(array $config = []){
        parent::__construct($config);
    }
}