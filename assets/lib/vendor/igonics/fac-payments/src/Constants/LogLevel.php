<?php

namespace FacPayments\Constants;

class LogLevel {
    const ALL   = 0;
    const TRACE = 1;
    const INFO  = 2;
    const DEBUG = 3;
    const WARN =  4;
    const ERROR = 5;

    public static function getLogLevels(){
        $oClass = new \ReflectionClass(__CLASS__);
        return $oClass->getConstants();
    }

    public static function isValid($value){
        return in_array(
            $value,
            array_values(static::getLogLevels())
        );
    }

    public static function getValid($value,$default=4){
        if(static::isValid($value)){
            return $value;
        }
        $possibleValues = static::getLogLevels();
        if(isset($possibleValues[strtoupper($value)])){
            return $possibleValues[strtoupper($value)];
        }
        return static::isValid($default) ? $default : 4;
    }
}