<?php

namespace FacPayments\Transformers\V1;

use FacPayments\Entities\Responses\AuthPaymentResponse;
use FacPayments\External\Helpers\Arr;

class XMLPaymentResponseTransformer {
    public static function transform($authorizedResponse = null){
        $response = new AuthPaymentResponse(['apiVersion'=>1]);
        if(
            $authorizedResponse && 
            is_array($authorizedResponse) && 
            !empty($authorizedResponse)
        ){
            $success = Arr::get($authorizedResponse,'CreditCardTransactionResults.ResponseCode') == 1 ||
                       Arr::get($authorizedResponse,'ReasonCode') == 1101  || 
                       Arr::get($authorizedResponse,'ResponseCode') == 1 ;
            $response->setSuccess($success)
                     ->setOrderIdentifier(Arr::get($authorizedResponse,'OrderNumber'))
                     ->setTransactionIdentifier(Arr::get($authorizedResponse,'CreditCardTransactionResults.ReferenceNumber'))
                     ->setMessage(
                        !empty(Arr::get($authorizedResponse,'ReasonCodeDescription')) ?
                        Arr::get($authorizedResponse,'ReasonCodeDescription') : (
                            !empty(Arr::get($authorizedResponse,'CreditCardTransactionResults.ReasonCodeDescription')) ? 
                            Arr::get($authorizedResponse,'CreditCardTransactionResults.ReasonCodeDescription') :
                            'Transaction was '.($success ? 'successful' : 'not successful')
                        )
                     )
                     ->setTokenizedPan(Arr::get($authorizedResponse,'CreditCardTransactionResults.TokenizedPAN'))
                     ->setData($authorizedResponse);
        }else{
            $response->setData($authorizedResponse);
        }

        return $response;

    }
}