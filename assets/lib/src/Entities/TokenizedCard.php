<?php

namespace FacPayments\External\Woocommerce\Entities;

use FacPayments\Entities\Entity;

/**
 * Entity to store tokenized card details 
 * 
 * Also references - https://developer.woocommerce.com/2016/04/04/payment-token-api-in-2-6/
 */
class TokenizedCard extends Entity {
    public $id;
    public $userId;
    public $token;
    public $gatewayId='wcfac';
    public $last4;
    public $expiryYear;
    public $expiryMonth;
    public $cardType; //eg. Visa , Mastercard
    public $tokenType;
    public $checkValue;
    public $status;
}