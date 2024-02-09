<?php

namespace FacPayments\Transformers\V1;

use FacPayments\Entities\Payments\Address;
use FacPayments\Factories\CountryFactory;

class AddressTransformer {
    public static function transformAddress(Address $address = null,$prefix=""){
        if(!$address){
            return null;
        }
        if(empty($address->countryCode) || !is_numeric($address->countryCode) ){
            $countryRepo = CountryFactory::create();
            //defaults to USA
            $address->countryCode = $countryRepo->getNumericCode($address->countryCode) ?? '840';
        }
        $data = [
            'Address'=>$address->line1,
            'Address2'=>$address->line2,
            'City'=>$address->city,
            'Country'=>$address->countryCode,
            'County'=>$address->county,
            'Email'=>$address->emailAddress,
            'FirstName'=>$address->firstName,
            'LastName'=>$address->lastName,
            'Mobile'=>$address->phoneNumber,
            'State'=>$address->state,
            'Telephone'=>$address->phoneNumber2,
            'ZipPostCode'=>$address->postalCode,
        ];

        $transformedData = [];
        foreach($data as $key => $value){
            if(!empty($value)){
                $transformedData[$prefix.$key]=$value;
            }
        }

        return $transformedData;
    }
}