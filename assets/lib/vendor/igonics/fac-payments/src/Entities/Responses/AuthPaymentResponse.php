<?php

namespace FacPayments\Entities\Responses;

class AuthPaymentResponse extends PaymentResponse {
    protected $tokenizedPAN;
    protected $creditCardReferenceNumber;

    public function setTokenizedPan($value){
        $this->tokenizedPAN=$value;
        return $this;
    }

    public function getTokenizedPan(){
        return $this->tokenizedPAN;
    }

    public function setCreditCardReferenceNumber($value){
        $this->creditCardReferenceNumber=$value;
        return $this;
    }

    public function getCreditCardReferenceNumber(){
        return $this->creditCardReferenceNumber;
    }

}