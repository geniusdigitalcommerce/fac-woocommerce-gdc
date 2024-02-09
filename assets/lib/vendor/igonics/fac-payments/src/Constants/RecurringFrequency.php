<?php

namespace FacPayments\Constants;

class RecurringFrequency {
    const DAILY='D';
    const WEEKLY='W';
    const FORTNIGHTLY='F'; //every 2 weeks
    const MONTHLY='M';
    const BIMONTHLY='E';
    const QUARTERLY='Q';
    const YEARLY='Y';

    public static function getRecurringFrequencies(){
        $oClass = new \ReflectionClass(__CLASS__);
        return $oClass->getConstants();
    }

    public static function isValid($frequency){
        return in_array(
            $frequency,
            array_values(static::getRecurringFrequencies())
        );
    }

    public static function getValid($frequency,$default='M'){
        if(static::isValid($frequency)){
            return $frequency;
        }
        $frequencies = static::getRecurringFrequencies();
        if(isset($frequencies[strtoupper($frequency)])){
            return $frequencies[strtoupper($frequency)];
        }
        return static::isValid($default) ? $default : 'M';
    }
}