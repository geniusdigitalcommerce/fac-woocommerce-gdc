<?php

namespace FacPayments\Entities\Requests;

use FacPayments\Entities\Payments\Card;
use FacPayments\Entities\Payments\Address;
use FacPayments\Entities\Payments\ExtendedData;

class BaseAuthPaymentRequest extends PaymentRequest {
    public $tokenize;
    public $transactionIdentifier; //String
    public $totalAmount; //int
    public $tipAmount; //int
    public $taxAmount; //int
    public $otherAmount; //int
    public $currencyCode; //String
    public $currencyAlpha; //String
    public $localTime; //String
    public $localDate; //String
    public $addressVerification; //boolean 
    public $threeDSecure; //boolean 
    public $binCheck; //boolean 
    public $fraudCheck; //boolean 
    public $recurringInitial; //boolean 
    public $recurring; //boolean 
    public $cardOnFile=true; //boolean 
    public $accountVerification; //boolean 
    protected $source; //Card
    public $terminalId; //String
    public $terminalCode; //String
    public $terminalSerialNumber; //String
    public $externalIdentifier; //String
    public $externalBatchIdentifier; //String
    public $externalGroupIdentifier; //String
    public $orderIdentifier; //String
    protected $billingAddress; //BillingAddress
    protected $shippingAddress; //ShippingAddress
    public $addressMatch; //boolean 
    protected $extendedData; //ExtendedData
    public $customerReferenceNo;

    public function getOrderIdentifier(){
        return $this->orderIdentifier;
    }

    public function setOrderIdentifier($value){
        $this->orderIdentifier=$value;
        return $this;
    }

    public function setSource($value){
        $this->source = is_a($value,Card::class) ? $value : ( 
            is_array($value) ? new Card($value) : null
        );
        return $this;
    }

    public function getSource(){
        return $this->source;
    }

    public function setCard($value){
        return $this->setSource($value);
    }

    public function getCard(){
        return $this->getSource();
    }


    public function setBillingAddress($value){
        $this->billingAddress = is_a($value,Address::class) ? $value : ( 
            is_array($value) ? new Address($value) : null
        );
        return $this;
    }

    public function getBillingAddress(){
        return $this->billingAddress;
    }

    
    public function setShippingAddress($value){
        $this->shippingAddress = is_a($value,Address::class) ? $value : ( 
            is_array($value) ? new Address($value) : null
        );
        return $this;
    }

    public function getShippingAddress(){
        return $this->shippingAddress;
    }

    
    public function setExtendedData($value){
        $this->extendedData = is_a($value,ExtendedData::class) ? $value : ( 
            is_array($value) ? new ExtendedData($value) : null
        );
        return $this;
    }

    public function getExtendedData(){
        return $this->extendedData;
    }
    
}