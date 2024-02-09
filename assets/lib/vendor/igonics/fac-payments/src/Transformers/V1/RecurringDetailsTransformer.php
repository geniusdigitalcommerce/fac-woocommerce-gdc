<?php

namespace FacPayments\Transformers\V1;

use FacPayments\Entities\Payments\Recurring;
use FacPayments\Constants\RecurringFrequency;
use FacPayments\External\Helpers\Date;
use DateTime;

class RecurringDetailsTransformer {
    public static function transformRecurring(Recurring $entry=null){
        
        if(!$entry)return null;
        
        $numberOfOccurrences = 0; 

        //Example Date format is “20130715”(YYYYMMDD).
        $executionDate = static::getFormattedDate($entry->startDate);
        if(!$executionDate ) return null;

        $frequency = RecurringFrequency::getValid(
            $entry->frequency,
            RecurringFrequency::MONTHLY
        );

        $endDate = static::getFormattedDate($entry->expiryDate);

        if($executionDate && $endDate){
            $numberOfOccurrences = static::getNumberOfOccurrencies(
                $executionDate,
                $endDate,
                $frequency
            );
        }
        
        return [
            'ExecutionDate'=>$executionDate,
            'Frequency'=>$frequency,
            'IsRecurring'=>$numberOfOccurrences > 0,
            'NumberOfRecurrences'=>$numberOfOccurrences
        ];
    }

    public static function getNumberOfOccurrencies($startDate,$endDate,$frequency='M'){
        $interval = 'm';
        switch($frequency){
            case 'D':$interval='d'; break;
            case 'W':
            case 'F':$interval='ww'; break;
            case 'M':
            case 'E':$interval='m'; break;
            case 'Q':$interval='q'; break;
            case 'Y':$interval='yyyy'; break;
        }
        $occurrencies = intval(
            Date::datediff($interval, strtotime($startDate), strtotime($endDate), true)
        );
        if($occurrencies > 0){
            if($frequency == 'F')$occurrencies = ceil($occurrencies/2);
            if($frequency == 'E')$occurrencies = $occurrencies*2;
        }else{
            $occurrencies=0;
        }
        

        return $occurrencies;
    }

    protected static function getFormattedDate($entryDate){
        $formattedDate = null;
        if(!$entryDate)return $formattedDate;

        if(is_a($entryDate,DateTime::class)){
            $formattedDate = $entryDate->format('Ymd');
        }
        if(is_int($entryDate))$entryDate=strval($entryDate);
        if(
            is_string($entryDate) && (
                preg_match("/^[0-9]{8}$/",$entryDate) ||
                preg_match("/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/",$entryDate)
            )
        ){
            
            try{
                $dateFormmattedDate = new DateTime($entryDate);
                if( 
                    in_array(
                        $entryDate,[
                            $dateFormmattedDate->format('Ymd'),
                            $dateFormmattedDate->format('Y-m-d')
                        ]
                    )
                ){
                    $formattedDate = $dateFormmattedDate->format('Ymd');
                }
            }catch(\Exception $e){
                //ignore invalid date
            }

        }
        return $formattedDate;
    }

    

}