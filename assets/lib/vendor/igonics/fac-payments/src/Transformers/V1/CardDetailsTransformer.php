<?php

namespace FacPayments\Transformers\V1;

use FacPayments\Entities\Payments\Card;

class CardDetailsTransformer {
    public static function transformCard(Card $card=null){
        if($card == null)return [];
        
        return [
            'CardNumber' => $card->getCardNumber(),
            'CardCVV2' => $card->getCardCvv(),
            'CardExpiryDate' =>  substr($card->getCardExpiration(),2,4).substr($card->getCardExpiration(),0,2),
            // 'DocumentNumber'=>0,
            'Installments'=>0,
            'IssueNumber'=>'',
            'StartDate'=>''
        ];
    }

}
