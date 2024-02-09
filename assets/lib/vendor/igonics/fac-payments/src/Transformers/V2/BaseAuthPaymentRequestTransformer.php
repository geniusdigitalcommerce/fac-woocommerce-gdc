<?php

namespace FacPayments\Transformers\V2;

use FacPayments\Entities\Requests\BaseAuthPaymentRequest;
use FacPayments\Entities\Configuration\FacConfig;
use FacPayments\External\Helpers\Arr;
use FacPayments\Factories\CurrencyFactory;
use FacPayments\Factories\CountryFactory;

class BaseAuthPaymentRequestTransformer {
    public static function transfromBaseAuthPaymentRequest(
        BaseAuthPaymentRequest $entry =null,
        FacConfig $config = null
    ){
        if($entry == null)return [];
        if($config){
            //$entry->threeDSecure=$config->enable3DS; 
            $entry->addressVerification = $config->enableAVS;
            $entry->tokenize = $config->enableTokenization;
            
        }
        if(empty($entry->currencyCode) || !is_numeric($entry->currencyCode) ){
            $currencyRepo = CurrencyFactory::create();
            //defaults to USD
            $entry->currencyCode = $currencyRepo->getNumericCode($entry->currencyCode) ?? '840';
        }
        
        $entry = $entry->toArray(true);
        if(isset($entry['TotalAmount']))
        {
            $entry["TotalAmount"] = (string) number_format((float)$entry["TotalAmount"], 2, '.', '');
        }
        //check country codes
        $countryRepo = null;
        foreach([
            'ShippingAddress.CountryCode',
            'BillingAddress.CountryCode'
        ] as $countryCodeField){
            $countryCode = Arr::get($entry,$countryCodeField);
            if(empty($countryCode) || !is_numeric($countryCode)){
                $countryRepo = $countryRepo ?? CountryFactory::create();
                Arr::nestedAssignment(
                    $entry,
                    $countryCodeField,
                    $countryRepo->getNumericCode($countryCode) ?? '840'
                );
            }
        }

        if($config && $config->isHppEnabled()){
            Arr::nestedAssignment($entry,'ExtendedData.HostedPage',[
                'PageSet'=> $config->getHppConfig()->pageSet,
                'PageName'=> $config->getHppConfig()->pageName
            ]); 
        }
        if(isset($entry['ExtendedData']['Recurring'])){
            // $entry['Meta']['Recurring']=$entry['ExtendedData']['Recurring'];
            unset($entry['ExtendedData']['Recurring']);
        }

        if( !$entry['ThreeDSecure']  )
        {
            unset($entry['ExtendedData']);
        }

        if( $entry['Source'] )
        {
            if( $entry['Source']['Token'] && $entry['Source']['CardExpiration'] )
            {
                unset($entry['Source']['CardExpiration']);
            }
                
        }

        return $entry;
    }

}