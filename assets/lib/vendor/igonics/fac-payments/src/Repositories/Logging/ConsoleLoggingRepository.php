<?php

namespace FacPayments\Repositories\Logging;


class ConsoleLoggingRepository extends BaseLoggingRepository{
    function trace($message){
        if($this->shouldTrace())
            print_r($message);
    }
    function info($message){
        if($this->shouldInfo())
            print_r($message);
    }
    function debug($message){
        if($this->shouldDebug())
            print_r($message);
    }
    function warn($message){
        if($this->shouldWarn())
            print_r($message);
    }
    function error($message,\Exception $e=null){
        if($this->shouldError()){
            print_r($message);
            if($e){
                print_r($e);
            }
        }
            
    }
}