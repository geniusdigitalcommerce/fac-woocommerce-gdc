<?php

namespace FacPayments\Entities\Responses;

use FacPayments\Entities\Payments\ThreeDSecure;

class ThreeDSecureResponse extends ThreeDSecure {
    public $fingerprintIndicator;
    public $dsTransId;
    public $responseCode;
    public $cardholderInfo;
}