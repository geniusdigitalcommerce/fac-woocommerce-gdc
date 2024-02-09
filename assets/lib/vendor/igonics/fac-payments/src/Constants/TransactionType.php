<?php

namespace FacPayments\Constants;

class TransactionType {
    const AUTH            = 1;
    const SALE            = 2;
    const CAPTURE         = 3;
    const VOID            = 4;
    const REFUND          = 5;
    const RISK_MANAGEMENT = 8;
}