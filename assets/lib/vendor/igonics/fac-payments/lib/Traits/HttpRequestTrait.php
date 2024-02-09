<?php

namespace FacPayments\External\Traits;

trait HttpRequestTrait {


    protected function httpPost($apiUrl="",$payload="",$headers=[],$config=[]){
        $ch = curl_init( $apiUrl );
        curl_setopt($ch, CURLOPT_POST, 1);
        if(!empty($payload)){
            curl_setopt( $ch, CURLOPT_POSTFIELDS, $payload );
        }
        $headerData=[];
        if(count($headers)){
            
            foreach($headers as $key=>$value){
                $headerData[]=$key.': '.$value;
            }
            
    
            curl_setopt( $ch, CURLOPT_HTTPHEADER, $headerData);
            //var_dump($headerData);die;
        }
 
        //curl_setopt($ch, CURLOPT_HEADER, true);    // we want headers
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        # Return response instead of printing.
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        # Send request.
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errorNo = curl_errno($ch);
        curl_close($ch);
        
        $result = [
            'data'=>json_decode($response,true),
            'success'=> $httpCode == 200 && $response !== false
        ];

        if(isset($config) && isset($config['debug']) && $config['debug']==true){
            $result['url']=$apiUrl;
            $result['payload']=$payload;
            $result['raw_http_response']=(string)$response;
            $result['http_response_code']=$httpCode;
            $result['headers']=$headerData;
            $result['curl_error']=$error;
            $result['curl_error_no']=$errorNo;
        }

        return $result;
    }
}