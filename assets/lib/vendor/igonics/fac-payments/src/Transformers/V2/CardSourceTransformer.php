<?php

namespace FacPayments\Transformers\V2;

use FacPayments\Entities\Payments\Card;

class CardSourceTransformer {
    public static function transfromCard(Card $card=null){
        if($card == null)return [];
        $expiryDate = $card->getCardExpiration();
        $month = null; $year = null;
        if(!empty($expiryDate)){
            $month = explode('/',$expiryDate)[0];
            $year = explode('/',$expiryDate)[1];
        }

        return [
            'CardPan' => $card->getCardNumber(),
            'CardCvv' => $card->getCardCvv(),
            'CardExpiration' => $year . $month,
            'CardholderName' => $card->getCardholderName(),
        ];
    }

}