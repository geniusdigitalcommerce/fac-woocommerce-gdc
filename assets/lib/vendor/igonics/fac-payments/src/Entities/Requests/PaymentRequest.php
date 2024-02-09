<?php

namespace FacPayments\Entities\Requests;

use FacPayments\Entities\Entity;

abstract class PaymentRequest extends Entity {
    protected $transactionIdentifier;

    public function setTransactionIdentifier($value){
        $this->transactionIdentifier = $value;
        return $this;
    }

    public function getTransactionIdentifier(){
        return $this->transactionIdentifier;
    }

    public function getAmount(){
        return property_exists($this,'totalAmount') ? $this->totalAmount : (
            property_exists($this,'amount') ? $this->amount : 0
        );
    }

    public function getCurrencyCode(){
        return property_exists($this,'currencyCode') ? $this->currencyCode : 'USD';
    }

    public function getOrderNumber(){
        return property_exists($this,'orderIdentifier') && !empty($this->orderIdentifier) ? $this->orderIdentifier : (
            property_exists($this,'externalIdentifier') && !empty($this->externalIdentifier) ? $this->externalIdentifier : $this->transactionIdentifier
        );
    }

    


}