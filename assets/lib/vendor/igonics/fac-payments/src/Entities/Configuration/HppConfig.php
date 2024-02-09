<?php

namespace FacPayments\Entities\Configuration;

class HppConfig extends Configuration{
    public $pageSet;
    public $pageName;
    public $enabled=true;

    public function __construct(array $config = []){
        parent::__construct($config);
    }
}