<?php

namespace FacPayments\Entities\Payments;

use FacPayments\Entities\Entity;

class Recurring extends Entity{
    public $startDate; //String
    public $frequency; //String
    public $expiryDate; //String

}