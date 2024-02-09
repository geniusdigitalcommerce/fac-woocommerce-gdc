<?php

namespace FacPayments\Services\V1;

use FacPayments\Services\PaymentService as BasePaymentService;
use FacPayments\Entities\Requests\BaseAuthPaymentRequest;
use FacPayments\Entities\Requests\PaymentRequest;
use FacPayments\Entities\Requests\AuthRequest;
use FacPayments\Entities\Requests\CaptureRequest;
use FacPayments\Entities\Requests\SaleRequest;
use FacPayments\Entities\Requests\RefundRequest;
use FacPayments\Entities\Requests\VoidRequest;
use FacPayments\Entities\Requests\TokenizationRequest;

use FacPayments\Entities\Responses\PaymentResponse;
use FacPayments\Entities\Responses\AuthPaymentResponse;
use FacPayments\Entities\Responses\ThreeDSecureResponse;

use FacPayments\External\Helpers\Guid;
use FacPayments\External\Helpers\Xml;
use FacPayments\External\Helpers\Arr;


use FacPayments\Entities\Payments\ExtendedData;

use FacPayments\Transformers\V1\BaseAuthPaymentRequestTransformer;
use FacPayments\Transformers\V1\ModificationRequestTransformer;
use FacPayments\Transformers\V1\XMLPaymentResponseTransformer;

use FacPayments\Exceptions\ServiceNotAvailableForPublicUseException;

use SimpleXMLElement;



class PaymentService extends BasePaymentService {

    const TEST_FAC_BASE_URL='https://ecm.firstatlanticcommerce.com/';
    const LIVE_FAC_BASE_URL='https://marlin.firstatlanticcommerce.com/';
    
    public function getVersion(){
        return '1.0.0';
    }

    private function getFacUrl(){
        return ($this->isTestMode() ? static:: TEST_FAC_BASE_URL : LIVE_FAC_BASE_URL ).'PGServiceXML/';
    }

    // protected function getHppUrl(){
    //     return ($this->isTestMode() ? static:: TEST_FAC_BASE_URL : LIVE_FAC_BASE_URL ).'MerchantPages/' . (
    //         $this->config->getHppConfig() ? sprintf(
    //             "%s/%s/",
    //             $this->config->getHppConfig()->pageSet,
    //             $this->config->getHppConfig()->pageName
    //         ) : null
    //     );
    // }

    // protected function createSoapClient(){
    //     $url = $this->isTestMode() ? 'https://ecm.firstatlanticcommerce.com/PGService/Services.svc' : 'https://marlin.firstatlanticcommerce.com/PGService/Services.svc';
       
    //     return new \SoapClient($url.'?wsdl' , [
    //         'location' => $url,
    //         'soap_version'=>SOAP_1_1,
    //         'exceptions'=>0,
    //         'trace'=>1,
    //         'cache_wsdl'=>WSDL_CACHE_NONE
    //     ]);
    // }


    
  
    // private function generateRequestSignature($orderId,$merchantPassword){
    //     $hash = sha1(json_encode([
    //         'o'=>$orderId,
    //         'p'=>base64_encode("C".$merchantPassword."L")
    //     ]));
    //     return substr(base64_encode($hash),4,40);
    // }


    private function xmlCurlRequest($AuthorizeRequest, $service = 'Authorize') {
        $url = $this->getFacUrl() . $service;
        $authorizedResponse = array();
        $xml_payment_detail = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?><AuthorizeRequest xmlns=\"http://schemas.firstatlanticcommerce.com/gateway/data\"></AuthorizeRequest>");
        if ($service == 'HostedPagePreprocess' && $this->config->isHppEnabled()) {
            $xml_payment_detail = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?><HostedPagePreprocessRequest xmlns=\"http://schemas.firstatlanticcommerce.com/gateway/data\"></HostedPagePreprocessRequest>");
        }
        if ($service == 'Authorize3DS' && $this->config->enable3DS) {
            $xml_payment_detail = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?><Authorize3DSRequest xmlns=\"http://schemas.firstatlanticcommerce.com/gateway/data\"></Authorize3DSRequest>");
        }
        if ($service == 'HostedPageResults') {
            $xml_payment_detail = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?><string xmlns=\"http://schemas.firstatlanticcommerce.com/gateway/data\"></string>");
        }
        if ($service == 'TransactionModification') {
            $xml_payment_detail = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?><TransactionModificationRequest xmlns=\"http://schemas.firstatlanticcommerce.com/gateway/data\"></TransactionModificationRequest>");
        }

        Xml::updateXMLWithArray($xml_payment_detail,$AuthorizeRequest);
        $dom = new \DOMDocument("1.0");
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml_payment_detail->asXML());

        if ($dom->loadXML($xml_payment_detail->asXML())) {
            //$this->logger->debug('XML Request Body');
            //$this->logger->debug((string) $dom->saveXML());
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
            curl_setopt($ch, CURLOPT_POSTFIELDS, (string) $dom->saveXML());
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $response = curl_exec($ch);

            curl_close($ch);
            $authorizedResponse =  json_decode(json_encode(simplexml_load_string($response)),true);
        }
        return XMLPaymentResponseTransformer::transform($authorizedResponse);
    }

    /**
     * @return FacPayments\Entities\Requests\PaymentResponse
     */
    public function authorize(AuthRequest $request){
        //$this->logger->debug('Actual '.get_class($request));
        //$this->logger->debug($request->toArray(true));
        $requestData = BaseAuthPaymentRequestTransformer::transfromBaseAuthPaymentRequest(
            $request,
            $this->config
        );
        //$this->logger->debug('Transformed Request Data');
        //$this->logger->debug($requestData);
        $response = $this->xmlCurlRequest($requestData,'Authorize');
        //$this->logger->debug('Actual '.get_class($response));
        //$this->logger->debug($response->toArray(true));
        return $response;
    }

    /**
     * @return FacPayments\Entities\Requests\PaymentResponse
     */
    //TODO:
    /*
Special Recurring Response Codes
----
If you receive any of the following responsecodes for a recurring tranctoin the corresponding actions
should be taken as mandated by Visa:
R0 : Consider the transaction declined and cancel the recurring payment transaction. No further
recurring payments should be sent for this transaction
R1 : Consider the transaction declined and cancel all the recurring payment transaction for that card and
that merchant. No further recurring payments should be sent for this card from this merchant.
R3 : Consider the transaction declined and cancel all the recurring payment transaction for that card
from all merchants. No further recurring payments should be sent for this card from this acquirer ID.
    */
    public function capture(CaptureRequest $request){
        //$this->logger->debug('Actual '.get_class($request));
        //$this->logger->debug($request->toArray(true));
        $requestData = ModificationRequestTransformer::transfrom(
            $request,
            $this->config
        );
        //$this->logger->debug('Transformed Request Data');
        //$this->logger->debug($requestData);
        $response = $this->xmlCurlRequest($requestData,'TransactionModification');
        //$this->logger->debug('Actual '.get_class($response));
        //$this->logger->debug($response->toArray(true));
        return $response;
    }

    /**
     * @return FacPayments\Entities\Requests\PaymentResponse
     */
    public function authorizeAndCapture(SaleRequest $request){
        //$this->logger->debug('Actual '.get_class($request));
        //$this->logger->debug($request->toArray(true));
        $requestData = BaseAuthPaymentRequestTransformer::transfromBaseAuthPaymentRequest(
            $request,
            $this->config
        );
        //$this->logger->debug('Transformed Request Data');
        //$this->logger->debug($requestData);
        $response = $this->xmlCurlRequest($requestData,'Authorize');
        //$this->logger->debug('Actual '.get_class($response));
        //$this->logger->debug($response->toArray(true));
        
        return $response;
    }

    /**
     * @return FacPayments\Entities\Requests\PaymentResponse
     */
    public function refund(RefundRequest $request){
        //$this->logger->debug('Actual '.get_class($request));
        //$this->logger->debug($request->toArray(true));
        $requestData = ModificationRequestTransformer::transfrom(
            $request,
            $this->config
        );
        //$this->logger->debug('Transformed Request Data');
        //$this->logger->debug($requestData);
        $response = $this->xmlCurlRequest($requestData,'TransactionModification');
        //$this->logger->debug('Actual '.get_class($response));
        //$this->logger->debug($response->toArray(true));
        return $response;
    }

    /**
     * @return FacPayments\Entities\Requests\PaymentResponse
     */
    public function void(VoidRequest $request){
        //$this->logger->debug('Actual '.get_class($request));
        //$this->logger->debug($request->toArray(true));
        $requestData = ModificationRequestTransformer::transfrom(
            $request,
            $this->config
        );
        //$this->logger->debug('Transformed Request Data');
        //$this->logger->debug($requestData);
        $response = $this->xmlCurlRequest($requestData,'TransactionModification');
        //$this->logger->debug('Actual '.get_class($response));
        //$this->logger->debug($response->toArray(true));
        return $response;
    }

    /**
     * @return FacPayments\Entities\Requests\PaymentResponse
     */
    public function confirmTransaction(array $responseData = []){
        throw new ServiceNotAvailableForPublicUseException();
    }

    /**
     * @return FacPayments\Entities\Requests\AuthPaymentResponse
     */
    public function tokenize(TokenizationRequest $request){
        //$this->logger->debug('Actual '.get_class($request));
        //$this->logger->debug($request->toArray(true));
        $requestData = BaseAuthPaymentRequestTransformer::transfromBaseAuthPaymentRequest(
            $request,
            $this->config
        );
        //$this->logger->debug('Transformed Request Data');
        //$this->logger->debug($requestData);
        $response = $this->xmlCurlRequest($requestData,'Authorize');
        //$this->logger->debug('Actual '.get_class($response));
        //$this->logger->debug($response->toArray(true));
        return $response;
    }
    
}