<?php

namespace FacPayments\Entities\Configuration;

use FacPayments\Exceptions\InvalidConfigurationException;

class FacConfig extends Configuration {
    public $acquirerId;
    public $merchantId;
    public $merchantPassword;
    public $testMode=true;
    public $enable3DS=true;
    public $enableAVS;
    public $enableTokenization;
    public $paymentMode='authorize_capture';
    public $debugMode = false;
    public $transactionCacheTime;

    public $facpg2MerchantId;
    public $facpg2MerchantPassword;


    /**
     * @instance FacPayments\Entities\Configuration\KountConfig
     */
    protected $kountConfig;

    /**
     * @instance FacPayments\Entities\Configuration\HppConfig
     */
    protected $hppConfig;

   
    public function setKountConfig($value){
        $this->kountConfig = is_a($value,KountConfig::class) ? $value : ( 
            is_array($value) ? new KountConfig($value) : null
        );
        return $this;
    }

    public function getKountConfig(){
        return $this->kountConfig;
    }

    public function isKountEnabled(){
        return $this->kountConfig ? $this->kountConfig->enabled : false;
    }

    public function setHppConfig($value){
        $this->hppConfig = is_a($value,HppConfig::class) ? $value : ( 
            is_array($value) ? new HppConfig($value) : null
        );
        return $this;
    }

    public function getHppConfig(){
        return $this->hppConfig;
    }

    public function isHppEnabled(){
        return $this->hppConfig ? $this->hppConfig->enabled : false;
    }


}