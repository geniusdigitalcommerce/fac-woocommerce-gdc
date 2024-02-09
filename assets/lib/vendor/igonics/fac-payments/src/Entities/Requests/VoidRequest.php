<?php

namespace FacPayments\Entities\Requests;

class VoidRequest extends PaymentRequest implements PaymentModificationRequest {
    public $transactionIdentifier; //String
    public $externalIdentifier; //String
    public $externalGroupIdentifier; //String
    public $emvData; //String
    public $terminalCode; //String
    public $terminalSerialNumber; //String
    public $autoReversal; //boolean 
    public $cancelRecurring;
    public $totalAmount;

    public function getOrderIdentifier(){
        return $this->externalIdentifier;
    }

    public function setOrderIdentifier($value){
        $this->externalIdentifier=$value;
        return $this;
    }
}
