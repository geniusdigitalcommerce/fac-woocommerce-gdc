<?php

namespace FacPayments\Entities\Responses;

class PaymentResponse extends Response {
    protected $transactionIdentifier;
    protected $orderIdentifier;
    protected $errorCode=0;
    protected $redirectUri=null;
    protected $apiVersion = 2;
    protected $totalAmount;

    public function setOrderIdentifier($value){
        $this->orderIdentifier = $value;
        return $this;
    }

    public function getOrderIdentifier(){
        return $this->orderIdentifier;
    }

    public function setTransactionIdentifier($value){
        $this->transactionIdentifier = $value;
        return $this;
    }

    public function getTransactionIdentifier(){
        return $this->transactionIdentifier;
    }

    public function setApiVersion($value){
        $this->apiVersion = $value;
        return $this;
    }

    public function getApiVersion(){
        return $this->apiVersion;
    }

    public function setErrorCode($value){
        $this->errorCode = intval($value);
        return $this;
    }

    public function getErrorCode(){
        return $this->errorCode;
    }

    public function requiresRedirect(){
        return !empty($this->redirectUri);
    }

    public function setRedirectUri($value){
        $this->redirectUri=$value;
        return $this;
    }

    public function getRedirectUri(){
        return $this->redirectUri;
    }

    public function setTotalAmount($value){
        $this->totalAmount=($value);
        return $this;
    }

    public function getTotalAmount(){
        return $this->totalAmount;
    }

    
}