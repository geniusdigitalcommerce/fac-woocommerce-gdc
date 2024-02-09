<?php

namespace FacPayments\Entities\Requests;

//Marker Interface
interface PaymentModificationRequest {
    function getAmount();

    function getCurrencyCode();

    function getOrderNumber();
}