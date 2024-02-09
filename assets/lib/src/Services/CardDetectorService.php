<?php

namespace FacPayments\External\Woocommerce\Services;

class CardDetectorService
{
    public function isVisa(string $card) 
    {
        return preg_match("/^4[0-9]{0,15}$/i", $card);
    }

    public function isMasterCard(string $card) 
    {
        return preg_match("/^5[1-5][0-9]{5,}|222[1-9][0-9]{3,}|22[3-9][0-9]{4,}|2[3-6][0-9]{5,}|27[01][0-9]{4,}|2720[0-9]{3,}$/i", $card);
    }

    public function isAmex(string $card) 
    {
        return preg_match("/^3$|^3[47][0-9]{0,13}$/i", $card);
    }

    public function isDiscover(string $card) 
    {
        return preg_match("/^6$|^6[05]$|^601[1]?$|^65[0-9][0-9]?$|^6(?:011|5[0-9]{2})[0-9]{0,12}$/i", $card);
    }

    public function isJCB(string $card) 
    {
        return preg_match("/^(?:2131|1800|35[0-9]{3})[0-9]{3,}$/i", $card);
    }

    public function isDinersClub(string $card) 
    {
        return preg_match("/^3(?:0[0-5]|[68][0-9])[0-9]{4,}$/i", $card);
    }

    /**
     * @return string
     */
    public function detect(string $card) 
    {
        $cardTypes = [
            'Visa',
            'Amex',
            'MasterCard',
            'Discover',
            'JCB',
            'DinersClub'
        ];
        foreach ($cardTypes as $cardType) {
            $method = 'is' . $cardType;
            if ($this->$method($card)) {
                return $cardType;
            }
        }
        return null;
    }
}