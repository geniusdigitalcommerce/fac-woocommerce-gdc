<?php

namespace FacPayments\External\Woocommerce\Repositories;

use FacPayments\Repositories\Logging\BaseLoggingRepository;

class WoocommerceLoggingRepository extends BaseLoggingRepository{
    function trace($message){
        if($this->shouldTrace())
            \error_log($message);
    }
    function info($message){
        if($this->shouldInfo())
            \error_log($message);
    }
    function debug($message){
        if($this->shouldDebug())
            \error_log($message);
    }
    function warn($message){
        if($this->shouldWarn())
            \error_log($message);
    }
    function error($message,\Exception $e=null){
        if($this->shouldError()){
            \error_log($message);
            if($e){
                \error_log($e);
            }
        }
            
    }
}