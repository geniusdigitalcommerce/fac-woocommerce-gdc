<?php

namespace FacPayments\Transformers\V1;

use FacPayments\Entities\Requests\BaseAuthPaymentRequest;
use FacPayments\Entities\Requests\CaptureRequest;
use FacPayments\Entities\Requests\SaleRequest;
use FacPayments\Entities\Requests\AuthRequest;

use FacPayments\Entities\Configuration\FacConfig;

/*
The transaction code is a numeric value that allows any
combinations of the flags listed below to be included with the
transaction request by summing their corresponding value. For
example, to include AVS in the transaction and to tokenize the
card number, assign the sum of the corresponding values 1 and
128 to the transaction code. The valid codes for an
Authorization request are:
0 - None
1 - Include an AVS check in the transaction OR Flag as a $0
AVS verification only transaction
2 - **HOST SPECIFIC - Flag as a $0 AVS verification only
transaction
4 - Transaction has been previously 3D Secure Authenticated
the 3D Secure results will be included in the transaction.
8 - Flag as a single pass transaction (Authorization and
Capture as a single transaction)
64 - Flag as a 3DS Authenticate Only transaction (3DS-Only)
128 – Tokenize PAN (Request Token)
256 – Hosted Page Auth + 3DS (applies to Hosted Payment
Pages only)
512 – Fraud Check Only
1024 – Fraud Test
2048 – Subsequent Recurring – future recurring payments
4096 – Initial Recurring – First Payment in a recurring cycle
8192 - **HOST SPECIFIC – Initial Recurring for “Free-Trials”
*/
class TransactionCodeTransformer {
    public static function transformTransactionCode(
        BaseAuthPaymentRequest $entry =null,
        FacConfig $config = null,
        $transactionCode = 0
    ){
        
        if(!$entry)return $transactionCode;
        //1 - Include an AVS check in the transaction OR Flag as a $0 AVS verification only transaction
        if($config && $config->enableAVS == true){
            $transactionCode = $transactionCode + 1;
        }
        //4 - Transaction has been previously 3D Secure Authenticated
        if(
            $entry->getExtendedData() &&
            $entry->getExtendedData()->getThreeDSecure() &&
            property_exists($entry->getExtendedData()->getThreeDSecure(),'responseCode')
        ){
            $transactionCode = $transactionCode + 4;
        }

        //8 - Flag as a single pass transaction (i.e. Auth and Capture)
        if(is_a($entry,CaptureRequest::class) || is_a($entry,SaleRequest::class) ){
            $transactionCode = $transactionCode + 8;
        }
        //64 - Flag as a 3DS Authenticate Only transaction (3DS-Only)
        if(is_a($entry,AuthRequest::class)){
            $transactionCode = $transactionCode + 64;
        }
        //128 – Tokenize PAN (Request Token)
        if($config && $config->enableTokenization == true){
            $transactionCode = $transactionCode + 128;
        }
        //256 – Hosted Page Auth + 3DS (applies to Hosted PaymentPages only)
        if($config && $config->isHppEnabled()){
            $transactionCode = $transactionCode + 256;
        }
        //Recurring payments
        if(
            property_exists($entry,'recurring') &&
            $entry->recurring == true
        ){
            
            if(property_exists($entry,'recurringInitial')){
                //4096 – Initial Recurring – First Payment in a recurring cycle
                $transactionCode = $transactionCode + 4096;
            }else{
                //2048 – Subsequent Recurring – future recurring payments
                $transactionCode = $transactionCode + 2048;
            }
        }
        return $transactionCode;
    }
}