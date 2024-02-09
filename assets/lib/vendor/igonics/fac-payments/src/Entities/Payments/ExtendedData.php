<?php

namespace FacPayments\Entities\Payments;

use FacPayments\Entities\Entity;

class ExtendedData extends Entity{
    protected $secondaryAddress; //SecondaryAddress
    public $customData; //String
    public $level2CustomData; //String
    public $level3CustomData; //String
    protected $threeDSecure; //ThreeDSecure
    protected $recurring; //Recurring
    protected $browserInfo; //BrowserInfo
    public $merchantResponseUrl; //String
    protected $hostedPage; //HostedPage

    public function setSecondaryAddress($value){
        $this->secondaryAddress = is_a($value,Address::class) ? $value : ( 
            is_array($value) ? new Address($value) : null
        );
        return $this;
    }

    public function getSecondaryAddress(){
        return $this->secondaryAddress;
    }

    public function setThreeDSecure($value){
        $this->threeDSecure = is_a($value,ThreeDSecure::class) ? $value : ( 
            is_array($value) ? new ThreeDSecure($value) : null
        );
        return $this;
    }

    public function getThreeDSecure(){
        return $this->threeDSecure;
    }

    public function setRecurring($value){
        $this->recurring = is_a($value,Recurring::class) ? $value : ( 
            is_array($value) ? new Recurring($value) : null
        );
        return $this;
    }

    public function getRecurring(){
        return $this->recurring;
    }

    public function setBrowserInfo($value){
        $this->browserInfo = is_a($value,BrowserInfo::class) ? $value : ( 
            is_array($value) ? new BrowserInfo($value) : null
        );
        return $this;
    }

    public function getBrowserInfo(){
        return $this->browserInfo;
    }

    public function setHostedPage($value){
        $this->hostedPage = is_a($value,HostedPage::class) ? $value : ( 
            is_array($value) ? new HostedPage($value) : null
        );
        return $this;
    }

    public function getHostedPage(){
        return $this->hostedPage;
    }
}