<?php

namespace FacPayments\External\Woocommerce\Repositories;

use FacPayments\External\Woocommerce\Entities\TokenizedCard;
use FacPayments\External\Helpers\Guid;

class TokenizedCardRepository {

    const USER_META_TOKENIZED_CARDS_KEY='__fac_tokenized_cards';
    /**
     * @return TokenizedCard[]
     */
    public function getUserCards($userId){
        return array_values(
            $this->getUserCardsFromDataStore($userId)
        );
    }

    /**
     * @param int $userId
     * @param int $tokenId
     * @return string
     */
    public function getUserCardToken($userId,$tokenId){
        $cards = $this->getUserCardsFromDataStore($userId);
        return isset($cards[$tokenId]) ? $cards[$tokenId]->token : null;
    }


    /**
     * @param int $userId
     * @param int $tokenId
     * @return TokenizedCard
     */
    public function getUserCard($userId,$tokenId){
        $cards = $this->getUserCardsFromDataStore($userId);
        return isset($cards[$tokenId]) ? $cards[$tokenId] : null;
    }


    /**
     * @return TokenizedCard
     */
    public function add(TokenizedCard $card){
        if(!empty($card->userId)){

            if (!isset($card->id)) {
                $card->id = $this->generateId($card);
            }
            $cards = $this->getUserCardsFromDataStore($card->userId);
            $cards[$card->id ] = $card;
            if(!add_user_meta($card->userId, static::USER_META_TOKENIZED_CARDS_KEY, $cards, true)){
                update_user_meta($card->userId, static::USER_META_TOKENIZED_CARDS_KEY, $cards);
            }
            return $card;
        }
        return null;
        
    }

    /**
     * @return bool
     */
    public function removeUserCard($userId, $id){
        $cards = $this->getUserCardsFromDataStore($userId);
        $success = isset($cards[$id]);
        unset($cards[$id]);
        if(!add_user_meta($userId, static::USER_META_TOKENIZED_CARDS_KEY, $cards, true)){
            update_user_meta($userId, static::USER_META_TOKENIZED_CARDS_KEY, $cards);
        }
        return $success;
    }

    protected function generateId(TokenizedCard $card){
        return sha1(Guid::generate());
    }

    protected function getUserCardsFromDataStore($userId){
        $cardData =get_user_meta($userId,static::USER_META_TOKENIZED_CARDS_KEY,true);
        return empty($cardData) ? [] : $cardData;
    }


}