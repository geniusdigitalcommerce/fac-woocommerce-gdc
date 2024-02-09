<?php

namespace FacPayments\Entities\Payments;

use FacPayments\Entities\Entity;

class AccountInfo extends Entity {
    public $accountAgeIndicator; //String
    public $accountChangeDate; //String
    public $accountChangeIndicator; //String
    public $accountDate; //String
    public $accountPasswordChangeDate; //String
    public $accountPasswordChangeIndicator; //String
    public $accountPurchaseCount; //String
    public $accountProvisioningAttempts; //String
    public $accountDayTransactions; //String
    public $accountYearTransactions; //String
    public $paymentAccountAge; //String
    public $paymentAccountAgeIndicator; //String
    public $shipAddressUsageDate; //String
    public $shipAddressUsageIndicator; //String
    public $shipNameIndicator; //String
    public $suspiciousAccountActivity; //String
    public $accountIdentifier;

}