<?php

namespace FacPayments\Transformers\V1;

use FacPayments\Entities\Requests\BaseAuthPaymentRequest;
use FacPayments\Entities\Configuration\FacConfig;

class FraudDetailsTransformer {
    public static function transformRequest(
        BaseAuthPaymentRequest $entry = null,
        FacConfig $config = null
    ){
        if(!$entry || !($config && $config->isKountEnabled()))return null;

      
        
        if(session_status() == PHP_SESSION_NONE){
            session_start();
        }
        $sessionId = session_id();
        
        
        return [
            'AuthResponseCode'=>'A',
            'AVSResponseCode'=>property_exists($entry,'AVSResponseCode') ? $entry->AVSResponseCode : null,
            'CVVResponseCode'=>property_exists($entry,'CVVResponseCode') ? $entry->CVVResponseCode : null,
            'SessionId'=>$sessionId,
        ];
    }
}