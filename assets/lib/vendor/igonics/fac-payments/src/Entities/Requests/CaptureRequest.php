<?php

namespace FacPayments\Entities\Requests;

class CaptureRequest extends PaymentRequest implements PaymentModificationRequest{
    public $transactionIdentifier; //String
    public $totalAmount; //int
    public $tipAmount; //int
    public $taxAmount; //int
    public $otherAmount; //int
    public $externalIdentifier; //String
    public $externalGroupIdentifier; //String

    public function getOrderIdentifier(){
        return $this->externalIdentifier;
    }

    public function setOrderIdentifier($value){
        $this->externalIdentifier=$value;
        return $this;
    }

    
}