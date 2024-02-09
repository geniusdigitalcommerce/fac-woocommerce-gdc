<?php

namespace FacPayments\Entities\Payments;

use FacPayments\Entities\Entity;

use FacPayments\Exceptions\Cards\InvalidCardCvvException;
use FacPayments\Exceptions\Cards\InvalidCardExpiryDateException;

class Card extends Entity {

    protected $cardCvv; //String
    protected $cardExpiration; //String
    protected $cardPan; //String
    protected $cardholderName; //String

    public $cardPresent; //boolean
    public $cardEmvFallback; //boolean
    public $manualEntry; //boolean
    public $debit; //boolean
    public $accountType; //String
    public $contactless; //boolean

    public $maskedPan; //String

    public $token; //String
    public $tokenType; //String
    public $cardTrack1Data; //String
    public $cardTrack2Data; //String
    public $cardTrack3Data; //String
    public $cardTrackData; //String
    public $encryptedCardTrack1Data; //String
    public $encryptedCardTrack2Data; //String
    public $encryptedCardTrack3Data; //String
    public $encryptedCardTrackData; //String
    public $ksn; //String
    public $encryptedPinBlock; //String
    public $pinBlockKsn; //String
    public $cardEmvData; //String



    public function getCardholderName(){
        return $this->cardholderName;
    }
    public function setCardholderName($value){
        $this->cardholderName = $value;
        return $this;
    }

    public function getCardCvv(){
        return $this->cardCvv;
    }
    /**
     * Update Card Cvv2
     *
     * A Valid CVV has 3 digits
     *
     * @throws FacPayments\Exceptions\Cards\InvalidCardCvvException
     * @return FacPayments\Entities\Card
     */
    public function setCardCvv($value){
        $cvv = trim($value);

        if(preg_match("^[0-9]{3,4}$", $cvv))
        {
            throw new InvalidCardCvvException($value);
        }
        $this->cardCvv = $cvv.'';
        return $this;
    }

    public function getCardExpiration(){
        return $this->cardExpiration;
    }
    /**
     * Update Card Expiry Date
     *
     *
     * @throws FacPayments\Exceptions\Cards\InvalidCardExpiryDateException
     * @return FacPayments\Entities\Card
     */
    public function setCardExpiration($value){
        $expiryDate = strtolower(trim($value));
        // if(
        //     !preg_match("/^[0-9]{2}\/[0-9]{2}$/",$expiryDate)
        // ){
        //     throw new InvalidCardExpiryDateException($value);
        // }
        $this->cardExpiration = $expiryDate;
        return $this;
    }

    public function getCardPan(){
        return $this->getCardNumber();
    }

    public function setCardPan($value){
        return $this->setCardNumber($value);
    }

    public function getCardNumber(){
        return $this->cardPan;
    }

    public function setCardNumber($value){
        $cardNumber = $value;//preg_replace('/[^0-9]/','',$value.'');
        $this->cardPan = $cardNumber;
        return $this;
    }

    public function setTokenizedPan($value){
        //$this->token = $value;
        $this->cardPan=$value;
        return $this;
    }


}
