<?php

namespace FacPayments\Transformers\V1;

use FacPayments\Entities\Requests\BaseAuthPaymentRequest;
use FacPayments\Entities\Configuration\FacConfig;
use FacPayments\Factories\CurrencyFactory;
use FacPayments\External\Helpers\Arr;
use FacPayments\External\Helpers\Guid;

class BaseAuthPaymentRequestTransformer {
    public static function transfromBaseAuthPaymentRequest(
        BaseAuthPaymentRequest $entry =null,
        FacConfig $config = null 
    ){
        if($entry == null)return [];

        $entryArr = $entry->toArray(true);
        $configArr = $config ? $config->toArray() : [];
        
        $orderNumber = $entry->getOrderIdentifier();//'FAC_' . $entry->getOrderIdentifier() . '_' . strtoupper(uniqid());
        $currencyRepo = CurrencyFactory::create();
        $currency = $currencyRepo->getNumericCode($entry->currencyCode);
        if(empty($currency)){
            $currency = '840';
            $currencyExponent = 2;
        }else{
            $currencyExponent = $currencyRepo->getMinorUnit($entry->currencyCode);
        }

        $amount = AmountTransformer::transformAmount(Arr::get($entryArr,'TotalAmount'));

        
        

        $transformedData = [
            'MerchantResponseUrl'=>Arr::get($entryArr,'ExtendedData.MerchantResponseUrl'),
            'CardHolderResponseURL'=> $config && $config->isHppEnabled() ?  Arr::get($entryArr,'ExtendedData.MerchantResponseUrl') : null,
            'BillingDetails'=>AddressTransformer::transformAddress($entry->getBillingAddress(),'BillTo'),
            'ShippingDetails'=>AddressTransformer::transformAddress($entry->getShippingAddress(),'ShipTo'),
            'CardDetails'=> $config && $config->isHppEnabled() ? null : CardDetailsTransformer::transformCard($entry->getSource()),
            'FraudDetails'=>FraudDetailsTransformer::transformRequest($entry,$config),
            'RecurringDetails'=>$entry->recurring ? RecurringDetailsTransformer::transformRecurring(
                $entry->getExtendedData()  ? 
                $entry->getExtendedData()->getRecurring() :
                null
            ): null,
            'ThreeDSecureDetails'=>[
                'AuthenticationResult'=>Arr::get($entryArr,'ExtendedData.ThreeDSecure.AuthenticationStatus') ?? 'Y',
                'CAVV'=>Arr::get($entryArr,'ExtendedData.ThreeDSecure.Cavv'),
                'ECIIndicator'=>Arr::get($entryArr,'ExtendedData.ThreeDSecure.Eci'),
                'TransactionStain'=>Guid::generate()
                //Arr::get($entryArr,'ExtendedData.ThreeDSecure.TransactionStain') ?? sha1($entry->transactionIdentifier)
            ],
            'ThreeDSecureAdditionalInfo'=>[
                'ProtocolVersion'=>Arr::get($entryArr,'ExtendedData.ThreeDSecure.ProtocolVersion') ?? '2.1.0',
                'DSTransId'=>Arr::get($entryArr,'ExtendedData.ThreeDSecure.DsTransId')
            ],
            'TransactionDetails'=>[
                'AcquirerId'=> Arr::get($configArr,'acquirerId'),
                'Amount'=>$amount,
                'Currency'=>$currency,
                'CurrencyExponent'=>$currencyExponent,
                'CustomData'=>'',
                'CustomerReference'=>Arr::get($entryArr,'ExtendedData.ThreeDSecure.AccountInfo.AccountIdenfier'),
                'IPAddress'=>Arr::get($entryArr,'ExtendedData.ThreeDSecure.BrowserInfo.IP'),
                'MerchantId'=>Arr::get(
                    $configArr,
                    'facpg2MerchantId',
                    Arr::get($configArr,'merchantId')
                ),
                'OrderNumber'=>$orderNumber,
                'Signature'=>static::generateFacSignature(
                    Arr::get(
                        $configArr,
                        'facpg2MerchantPassword',
                        Arr::get($configArr,'merchantPassword')
                    ),
                    Arr::get(
                        $configArr,
                        'facpg2MerchantId',
                        Arr::get($configArr,'merchantId')
                    ),
                    Arr::get($configArr,'acquirerId'),
                    $orderNumber,
                    $amount,
                    $currency
                ),
                'SignatureMethod'=>'SHA1',
                'TransactionCode'=>TransactionCodeTransformer::transformTransactionCode(
                    $entry,
                    $config
                ),
                'CustomerReference'=>Arr::get($entryAr2512r,'CustomerReferenceNo'),

            ]
        ];

        $transformedData = Arr::withoutEmptyValues($transformedData);
        
        return $transformedData;
    }

    // Signing an FAC FAC Authorize message
    public static function generateFacSignature($password, $facId, $acquirerId, $orderNumber, $amount, $currency)
    {
        
        $hash = sha1($password.$facId.$acquirerId.$orderNumber.$amount.$currency, true);
        return base64_encode($hash);
    }

    

    

}