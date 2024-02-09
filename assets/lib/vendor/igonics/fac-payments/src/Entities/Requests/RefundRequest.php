<?php

namespace FacPayments\Entities\Requests;

class RefundRequest extends BaseAuthPaymentRequest implements PaymentModificationRequest{
    public $refund=true;
}