<?php

namespace FacPayments\Transformers\V1;

use FacPayments\Entities\Requests\PaymentModificationRequest;
use FacPayments\Entities\Configuration\FacConfig;
use FacPayments\Factories\CurrencyFactory;

use FacPayments\Entities\Requests\CaptureRequest;
use FacPayments\Entities\Requests\VoidRequest;
use FacPayments\Entities\Requests\RefundRequest;

class ModificationRequestTransformer {
    public static function transfrom(
        PaymentModificationRequest $entry =null,
        FacConfig $config = null 
    ){
        if(!$entry)return null;
        $currencyRepo = CurrencyFactory::create();

        $currency = $currencyRepo->getNumericCode($entry->getCurrencyCode());
        if(empty($currency)){
            $currency = '840';
            $currencyExponent = 2;
        }else{
            $currencyExponent = $currencyRepo->getMinorUnit($entry->getCurrencyCode());
        }

        $transformedData = array(
            'ModificationType' => static::getModificationTypeFromRequest($entry),
            'AcquirerId' => $config ? $config->acquirerId : null,
            'Password' => $config && !empty($config->facpg2MerchantPassword) ? $config->facpg2MerchantPassword : (
                $config && !empty($config->merchantPassword) ? $config->merchantPassword : null
            ),
            'MerchantId' => $config && !empty($config->facpg2MerchantId) ? $config->facpg2MerchantId : (
                $config && !empty($config->merchantId) ? $config->merchantId : null
            ),
            'OrderNumber' => $entry->getOrderNumber(),
            'Amount' => AmountTransformer::transformAmount($entry->getAmount()),
            'CurrencyExponent' => $currencyExponent
        );

        return $transformedData;
    }

    protected static function getModificationTypeFromRequest($request){
        if($request && is_object($request)){
            switch(get_class($request)){
                case CaptureRequest::class : return 1;
                case RefundRequest::class  : return 2;
                case VoidRequest::class    : return $request->cancelRecurring ? 4: 3;
            }
        }
        return 0;
    }
}