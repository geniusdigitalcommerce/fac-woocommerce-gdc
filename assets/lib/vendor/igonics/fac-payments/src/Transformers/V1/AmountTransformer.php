<?php

namespace FacPayments\Transformers\V1;

class AmountTransformer {
    public static function transformAmount(float $amount){
        return str_pad(
            number_format($amount, 2, '', ''), 
            12, 
            "0", 
            STR_PAD_LEFT
        );
    }
}