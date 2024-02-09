<?php

namespace FacPayments\Entities\Payments;

use FacPayments\Entities\Entity;

class BrowserInfo extends Entity{
    public $acceptHeader; //String
    public $language; //String
    public $screenHeight; //String
    public $screenWidth; //String
    public $timeZone; //String
    public $userAgent; //String
    public $iP; //String
    public $javaEnabled; //boolean 
    public $javascriptEnabled; //boolean 
    public $colorDepth; //String

}