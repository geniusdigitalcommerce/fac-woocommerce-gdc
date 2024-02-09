<?php

namespace FacPayments\Repositories\Logging;

class NullLoggingRepository extends BaseLoggingRepository {
    function trace($message){}
    function info($message){}
    function debug($message){ }
    function warn($message){ }
    function error($message,\Exception $e=null){
        if($this->shouldError()){
            if($e==null)throw new \Exception($message);
            throw $e;
        }     
    }
}