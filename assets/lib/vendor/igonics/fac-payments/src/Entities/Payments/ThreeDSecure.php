<?php

namespace FacPayments\Entities\Payments;

use FacPayments\Entities\Entity;

class ThreeDSecure extends Entity {
    public $eci; //String
    public $cavv; //String
    public $xid; //String
    public $authenticationStatus; //String
    public $protocolVersion; //String
    public $dSTransId; //String
    public $challengeWindowSize; //int
    public $channelIndicator; //String
    public $riIndicator; //String
    public $challengeIndicator; //String
    public $authenticationIndicator; //String
    public $messageCategory; //String
    public $transactionType; //String
    protected $accountInfo; //AccountInfo

    public function setAccountInfo($value){
        $this->accountInfo = is_a($value,AccountInfo::class) ? $value : ( 
            is_array($value) ? new AccountInfo($value) : null
        );
        return $this;
    }

    public function getAccountInfo(){
        return $this->accountInfo;
    }

    

}