<?php

namespace FacPayments\Services\V2;

use FacPayments\Services\V1\PaymentService as BasePaymentService;
use FacPayments\Entities\Requests\BaseAuthPaymentRequest;
use FacPayments\Entities\Requests\PaymentRequest;
use FacPayments\Entities\Requests\RiskMgmtRequest;
use FacPayments\Entities\Requests\AuthRequest;
use FacPayments\Entities\Requests\CaptureRequest;
use FacPayments\Entities\Requests\SaleRequest;
use FacPayments\Entities\Requests\RefundRequest;
use FacPayments\Entities\Requests\VoidRequest;
use FacPayments\Entities\Requests\TokenizationRequest;

use FacPayments\Entities\Responses\PaymentResponse;
use FacPayments\Entities\Responses\AuthPaymentResponse;
use FacPayments\Entities\Responses\ThreeDSecureResponse;

use FacPayments\Entities\Payments\ExtendedData;

use FacPayments\External\Helpers\Guid;
use FacPayments\Transformers\V2\BaseAuthPaymentRequestTransformer;



use FacPayments\Constants\TransactionType;

use FacPayments\Exceptions\ThreeDSecureDetailsNotProvidedException;
use FacPayments\Exceptions\CachedTransactionRequestNotAvailableException;
use FacPayments\Exceptions\ServiceNotAvailableForPublicUseException;


class PaymentService extends BasePaymentService {

    const TEST_BASE_POWERTRANZ_URL = 'https://staging.ptranz.com/';
    const LIVE_BASE_POWERTRANZ_URL = 'https://gateway.ptranz.com/';

    const TEST_BASE_POWERTRANZ_HOST = 'staging.ptranz.com';
    const LIVE_BASE_POWERTRANZ_HOST  = 'gateway.ptranz.com';

    const FAC_API_ENDPOINTS =
    [
        '3ds' => [
            'alive'=>		'/api/alive',
            'auth' =>		'/api/spi/auth',
            'sale' => 		'/api/spi/sale',
            'risk' => 	    '/api/spi/riskmgmt',
            'payment' => 	'/api/spi/payment',
            'capture' => 	'/api/capture',
            'refund' => 	'/api/refund',
            'void' => 		'/api/void',
        ],
        'non3ds' => [
            'alive'=>		'/api/alive',
            'auth' =>		'/api/auth',
            'sale' => 		'/api/sale',
            'risk' => 	    '/api/riskmgmt',
            'capture' => 	'/api/capture',
            'refund' => 	'/api/refund',
            'void' => 		'/api/void',
        ]
    ];

    

    public function getVersion(){
        return '2.0.0';
    }

    protected function getFullEndpointUrl($endpoint){
        return ($this->isTestMode() ? static::TEST_BASE_POWERTRANZ_URL : static::LIVE_BASE_POWERTRANZ_URL).$endpoint;
    }
    
    protected function getHost(){
        return ($this->isTestMode() ? static::TEST_BASE_POWERTRANZ_HOST : static::LIVE_BASE_POWERTRANZ_HOST);
    }

    protected function getRequestHeaders($additional=[]){
      return array_merge([
        'Accept' => 'application/json',
        'PowerTranz-PowerTranzId' => $this->config->merchantId,
        'PowerTranz-PowerTranzPassword' => $this->config->merchantPassword,
        'Content-Type' => 'application/json; charset=utf-8',
        'Host' => $this->getHost(),
        'Expect' => '100-continue',
        'Connection' => 'Keep-Alive',
    ],$additional);


  }
    /**
     * Ensure request is 3D Secure
     *
     * @return FacPayments\Entities\Requests\BaseAuthPaymentRequest
     */
    protected function prepare3DSecureAuthRequest(BaseAuthPaymentRequest $request,$includeTransactionId=true){
        //Generate Managed Transaction Id

        if($includeTransactionId)$request->setTransactionIdentifier(Guid::generate());
        
        //Ensure Request is 3D Secure [GDC - if no value is set]
        if(!isset($request->threeDSecure))
            $request->threeDSecure=true;


        $extendedData = $request->getExtendedData() ?? new ExtendedData();
        if($request->threeDSecure){
            $extendedData->setThreeDSecure([
                'ChallengeWindowSize' => 4,
                'ChallengeIndicator' => '01',
            ]);
            $request->setExtendedData($extendedData);
        }
        
        return $request;
    }

    /**
     * Prepares Auth Payment Response and caches transaction details required for the future
     *
     * @return FacPayments\Entities\Responses\PaymentResponse
     */
    protected function prepareAuthPaymentResponseFromRequest(PaymentRequest $request,array $apiResult = null){
        $transactionId = $request->getTransactionIdentifier();
        //Prepare and extract response
        $response = new AuthPaymentResponse([
            'transactionIdentifier'=>$transactionId,
            'orderIdentifier'=>$request->getOrderIdentifier()
        ]);
        //$this->logger->debug('Actual Response for '.get_class($request).' request');
        //$this->logger->debug(json_encode($apiResult,JSON_PRETTY_PRINT));
        //$this->logger->debug('Api Result is null : '.(is_null($apiResult)?'Y':'N'));
        if($apiResult){
            if(!isset($apiResult['success']))$apiResult['success']=false;
            $apiResult['success']=$apiResult['success'] && !isset($apiResult['data']['Errors']);
            $response->setSuccess($apiResult['success'])
            ->setData(isset($apiResult['data']) ? $apiResult['data'] : null )
            ->setMessage(
                isset($apiResult['data']['Errors'][0]['Message']) ? $apiResult['data']['Errors'][0]['Message'] : (
                    isset($apiResult['data']['ResponseMessage']) ? $apiResult['data']['ResponseMessage'] : (
                        $apiResult['success'] ? 'Successful' : 'Request not successful'
                    )
                )
            )
            ->setErrorCode(
                !$apiResult['success']  ? (
                    isset($apiResult['data']['Errors'][0]['Code']) ? $apiResult['data']['Errors'][0]['Code'] : (
                        isset($apiResult['data']['IsoResponseCode']) ? $apiResult['data']['IsoResponseCode'] : 0
                    )
                ) : 0
            )
            ->setTotalAmount(
             isset($apiResult['data']['TotalAmount']) ? $apiResult['data']['TotalAmount'] : null
         );
            if(isset($apiResult['data']['RedirectData'])){
                $response->setRedirectUri($apiResult['data']['RedirectData']);
            }

            if($apiResult['success']){
                //Record Transaction for future verification
                $this->updateTransactionCache(
                    $transactionId,
                    'TransactionIdentifier',
                    $transactionId
                );
                //if( method_exists($request,'setSource')) $request->setSource(null); //do not serialize card source details
                $this->updateTransactionCache(
                    $transactionId,
                    'request',
                    serialize($request)
                );

                $this->updateTransactionCache(
                    $transactionId,
                    'OrderIdentifier',
                    $request->getOrderIdentifier()
                );
                if(isset($apiResult['data']['SpiToken'])){
                    $this->updateTransactionCache(
                        $transactionId,
                        'SpiToken',
                        $apiResult['data']['SpiToken']
                    );
                }

                if(
                    $request &&
                    property_exists($request,'recurring') &&
                    $request->recurring
                ){
                    $this->updateTransactionCache(
                        $transactionId,
                        'recurring',
                        true
                    );
                }
            }else{
                $this->logger->error("API Response Error");
                $this->logger->error(json_encode($apiResult,JSON_PRETTY_PRINT));
            }


        }


        return $response;
    }

    /**
     * @return FacPayments\Entities\Responses\PaymentResponse
     */
    function riskManagement(RiskMgmtRequest $request){
        // $request = $this->prepare3DSecureAuthRequest(new RiskMgmtRequest($request->toArray()));
        $request = $this->prepare3DSecureAuthRequest($request);

        //Send Request
        $apiEndPoint = $request->threeDSecure ? static::FAC_API_ENDPOINTS['3ds']['risk'] : static::FAC_API_ENDPOINTS['non3ds']['risk'];
        
        $apiResult = $this->httpPost(
            $this->getFullEndpointUrl($apiEndPoint),
            json_encode(
                BaseAuthPaymentRequestTransformer::transfromBaseAuthPaymentRequest($request,$this->config)
            ),
            $this->getRequestHeaders(),
            [
              'debug'=>$this->isDebugMode()
          ]
      );


        return $this->prepareAuthPaymentResponseFromRequest($request,$apiResult);

    }

    /**
     * @return FacPayments\Entities\Responses\PaymentResponse
     */
    function authorize(AuthRequest $request){
        $request = $this->prepare3DSecureAuthRequest($request);

        $transformedRequest  = BaseAuthPaymentRequestTransformer::transfromBaseAuthPaymentRequest($request,$this->config);
        //$this->logger->debug('Transformed Request Data for '.get_class($request).' request');
        //$this->logger->debug(json_encode($transformedRequest,JSON_PRETTY_PRINT));
        //Send Request
        $apiEndPoint = $request->threeDSecure ? static::FAC_API_ENDPOINTS['3ds']['auth'] : static::FAC_API_ENDPOINTS['non3ds']['auth'];
        $apiResult = $this->httpPost(
            $this->getFullEndpointUrl($apiEndPoint),
            json_encode($transformedRequest ),
            $this->getRequestHeaders(),
            [
              'debug'=>$this->isDebugMode()
          ]
      );


        return $this->prepareAuthPaymentResponseFromRequest($request,$apiResult);

    }



    /**
     * @return FacPayments\Entities\Responses\PaymentResponse
     */
    function authorizeAndCapture(SaleRequest $request){
     $request = $this->prepare3DSecureAuthRequest($request);

     $endpoints = static::FAC_API_ENDPOINTS;
     $apiEndPoint = $request->threeDSecure ? static::FAC_API_ENDPOINTS['3ds']['sale'] : static::FAC_API_ENDPOINTS['non3ds']['sale'];


     $transformedRequest = BaseAuthPaymentRequestTransformer::transfromBaseAuthPaymentRequest($request,$this->config);
        //$this->logger->debug('Transformed Request Data for '.get_class($request).' request');

        //$this->logger->debug('Request is being sent to: '.$this->getFullEndpointUrl($apiEndPoint)); 


         //$this->logger->debug('Request Headers:'.$this->getFullEndpointUrl($apiEndPoint));

        //$this->logger->debug(json_encode($this->getRequestHeaders(),JSON_PRETTY_PRINT));

        //$this->logger->debug('Request Data: ');

        //$this->logger->debug(json_encode($transformedRequest,JSON_PRETTY_PRINT));
        //Send Request
     $transformedRequest_encoded = json_encode($transformedRequest);
     $apiResult = $this->httpPost(
        $this->getFullEndpointUrl($apiEndPoint),
        $transformedRequest_encoded,
        $this->getRequestHeaders(),
        [
          'debug'=>$this->isDebugMode()
      ]
  );

        //$this->logger->debug('Result of API REquest: ');

        //$this->logger->debug(json_encode($apiResult,JSON_PRETTY_PRINT));

     return $this->prepareAuthPaymentResponseFromRequest($request,$apiResult);
 }

    /**
     * @return FacPayments\Entities\Responses\PaymentResponse
     */
    function capture(CaptureRequest $request){
        //Send Request
        $apiEndPoint =  static::FAC_API_ENDPOINTS['non3ds']['capture'];
        
        $apiResult = $this->httpPost(
            $this->getFullEndpointUrl($apiEndPoint),
            json_encode(
                $request->toArray(true)
            ),
            $this->getRequestHeaders(),
            [
              'debug'=>$this->isDebugMode()
          ]
      );

        return $this->prepareAuthPaymentResponseFromRequest($request,$apiResult);
    }
    /**
     * @return FacPayments\Entities\Responses\PaymentResponse
     */
    function refund(RefundRequest $request){
        //Send Request
        $apiEndPoint =  static::FAC_API_ENDPOINTS['non3ds']['refund'];
        
        $apiResult = $this->httpPost(
            $this->getFullEndpointUrl($apiEndPoint),
            json_encode(
                $request->toArray(true)
            ),
            $this->getRequestHeaders(),
            [
              'debug'=>$this->isDebugMode()
          ]
      );

        return $this->prepareAuthPaymentResponseFromRequest($request,$apiResult);
    }

    /**
     * @return FacPayments\Entities\Responses\PaymentResponse
     */
    function void(VoidRequest $request){

        $apiEndPoint =  static::FAC_API_ENDPOINTS['non3ds']['void'];
        
        $apiResult = $this->httpPost(
            $this->getFullEndpointUrl($apiEndPoint),
            json_encode(
                $request->toArray(true)
            ),
            $this->getRequestHeaders(),
            [
              'debug'=>$this->isDebugMode()
          ]
      );

        if($apiResult['success'] && isset($apiResult['data']['IsoResponseCode'])){
            $apiResult['success'] = $apiResult['success'] && $apiResult['data']['IsoResponseCode']==0;
        }

        return $this->prepareAuthPaymentResponseFromRequest($request,$apiResult);
    }

    /**
     * @return FacPayments\Entities\Responses\PaymentResponse
     */
    public function confirmTransaction(array $responseData = []){
      /**
       * TODO:
       *   1. [/] Verify that we sent this transaction from the transaction id
       *   2. [ ] If kount enabled, we must use the old api
       *   3. [ ] If recurring request, we must use the old api
       */
      $response = new AuthPaymentResponse(['message'=>'Not Confirmed']);
      if(
        isset($responseData['Response']) && is_string($responseData['Response'])
    ){
        $responseData['Response'] = json_decode($responseData['Response'],true);
    }
      //$this->logger->debug('Received response data : '.json_encode($responseData,JSON_PRETTY_PRINT));
    $transactionId = isset($responseData['TransactionIdentifier']) ?
    $responseData['TransactionIdentifier'] : (
       is_array($responseData['Response']) && isset($responseData['Response']['TransactionIdentifier']) ?
       $responseData['Response']['TransactionIdentifier'] :
       null
   );
    $spiToken =  isset($responseData['SpiToken']) ? $responseData['SpiToken'] : (
      is_array($responseData['Response']) && isset($responseData['Response']['SpiToken']) ? $responseData['Response']['SpiToken'] : null
  );
    $success = in_array( $responseData['Response']['IsoResponseCode'],['3D0','3D1','00']) &&
    !(isset($responseData['Response']['Errors']) && count($responseData['Response']['Errors'])>0
);
    $message = (
        isset($responseData['Response']['ResponseMessage']) ?  $responseData['Response']['ResponseMessage'] : null
    ).' '.(
        isset($responseData['Response']['Errors']) && count($responseData['Response']['Errors'])>0 ? $responseData['Response']['Errors'][0]['Message'] : null
    );
    if(empty($message)){
      $message = $success ? 'Successful' : 'Not Successful';
  }
  $response->setSuccess($success)
  ->setMessage($message)
  ->setTransactionIdentifier($transactionId)
  ->setTokenizedPan(isset($responseData['Response']['PanToken']) ? $responseData['Response']['PanToken'] : null)
  ->setData($responseData['Response'])
  ->setTransactionIdentifier(isset($responseData['Response']['TransactionIdentifier']) ? $responseData['Response']['TransactionIdentifier'] : null)
  ->setOrderIdentifier(isset($responseData['Response']['OrderIdentifier']) ? $responseData['Response']['OrderIdentifier'] : null)
  ->setErrorCode(
    isset($responseData['Response']['Errors']) && count($responseData['Response']['Errors'])>0 ?
    $responseData['Response']['Errors'][0]['Code'] :0
)
  ->setTotalAmount(
    isset($responseData['Response']['TotalAmount']) ? $responseData['Response']['TotalAmount'] : null
);
      //Is this a valid transaction issued by our payment service
  if(
          //$success &&
      $spiToken &&
      $transactionId &&
      $this->getTransactionDetailFromCache($transactionId,'SpiToken') == $spiToken
  ){
    $this->logger->trace('Recognized successful auth transaction - '.$transactionId);
            //do not process risk management requests
    if(
        isset($responseData['Response']['TransactionType']) &&
        $responseData['Response']['TransactionType'] == TransactionType::RISK_MANAGEMENT
    ){
        return $response;

    }
            //process using old api if kount/recurring
    if(
        $this->isKountEnabled() ||
        $this->getTransactionDetailFromCache($transactionId,'recurring')
    ){
        $this->logger->trace('Recognized successful auth transaction as recurring - '.$transactionId);
        $request = @unserialize($this->getTransactionDetailFromCache($transactionId,'request'));
        $this->forgetTransaction($transactionId);
        if(!$request || !is_object($request)){
            throw new CachedTransactionRequestNotAvailableException;
        }
                //must have 3D Secure details
        if($success && isset($responseData['Response']['RiskManagement']['ThreeDSecure'])){
            $this->logger->trace('Extracting 3DS Risk Management Data from response - '.$transactionId);
                    //extract 3DS response and update request
            $threeDSecureResponse = new ThreeDSecureResponse(
                array_merge(
                    (
                        $request->getExtendedData() &&
                        $request->getExtendedData()->getThreeDSecure() ?
                        $request->getExtendedData()->getThreeDSecure()->toArray(true) : []
                    ),
                    $responseData['Response']['RiskManagement']['ThreeDSecure']
                ),
                [],
                true
            );
                    //now prepare and send request using old api
            $extendedData = $request->getExtendedData() ?? ExtendedData();
            $extendedData->setThreeDSecure($threeDSecureResponse);
            $request->setExtendedData($extendedData);
            if(isset($responseData['Response']['RiskManagement']['CvvResponseCode'])){
                $request->CvvResponseCode = $responseData['Response']['RiskManagement']['CvvResponseCode'];
            }
            if(!is_a($request,RiskMgmtRequest::class)){

                $response= $this->confirm3DSRequest($spiToken);
                        // $tokenizeResponse = $this->riskManagement($request);
                        // $response->setRedirectUri(
                        //     $tokenizeResponse->getRedirectUri()
                        // );
                        // $response->additionalResponse = $tokenizeResponse;
                        //pass request to old api
                $this->logger->trace('Processing transaction using old api - '.$transactionId);
                $v1ApiResponse = $this->processUsingFACPG2($request);
                $response->setSuccess(
                    $response->isSuccessful() && $v1ApiResponse->isSuccessful()
                )
                ->setMessage(
                    sprintf(
                        "V2 - %s | V1 - %s",
                        $response->getMessage(),
                        $v1ApiResponse->getMessage()
                    )
                )
                ->setData(
                    array_merge(
                        json_decode(json_encode($response->getData()),true),
                        [
                            'V1_Response'=>$v1ApiResponse->toArray()
                        ]
                    )
                );

            }else{

            }



        }else{
            throw new ThreeDSecureDetailsNotProvidedException("Kount/Recurring Confirmations Require 3D Secure Response");
        }

                //throw new \Exception("Kount/Recurring Confirmations Not Yet Implemented");
    }else{
        $response = $this->confirm3DSRequest($spiToken);
    }

}else{
    $response->setSuccess(false)
    ->setMessage('Unable to verify transaction');
}

return $response;
}

    /**
     * @return FacPayments\Entities\Responses\PaymentResponse
     */

    public function confirm3DSRequestInterface($spiToken) {
        // return $this->confirm3DSRequest($spiToken);
        $apiEndPoint =  static::FAC_API_ENDPOINTS['3ds']['payment'];
        $result = $this->httpPost(
            $this->getFullEndpointUrl($apiEndPoint),
            json_encode($spiToken),
            $this->getRequestHeaders([
                'Accept'=>'text/plain',
                'Content-Type'=>'application/json-patch+json'
            ]),
            ['debug'=>$this->isDebugMode()]
        );
        $result['success'] = $result['success'] &&
        !isset($result['data']['Errors']) &&
        isset($result['data']['IsoResponseCode']) &&
        $result['data']['IsoResponseCode']=="00";
        //$this->logger->debug('Confirm 3DS Raw Response');
        //$this->logger->debug(json_encode($result,JSON_PRETTY_PRINT));

        $paymentResponse = (new AuthPaymentResponse())
        ->setSuccess($result['success'])
        ->setData(isset($result['data']) ? $result['data'] : null )
        ->setMessage(isset($result['data']['ResponseMessage']) ? $result['data']['ResponseMessage'] : (
           $result['success'] ? 'Successful' : 'Request not successful'
       ))
        ->setErrorCode(
           !$result['success'] ? (
               isset($result['data']['Errors'][0]['Code']) ? $result['data']['Errors'][0]['Code'] : (
                   isset($result['data']['IsoResponseCode']) ? $result['data']['IsoResponseCode'] : 0
               )
           ) : 0
       )
        ->setTransactionIdentifier(
           isset($result['data']['TransactionIdentifier']) ? $result['data']['TransactionIdentifier'] : null
       )
        ->setOrderIdentifier(
           isset($result['data']['OrderIdentifier']) ? $result['data']['OrderIdentifier'] : null
       )
        ->setTokenizedPan(
            isset($result['data']['PanToken']) ?  $result['data']['PanToken'] : null
        );
        //$this->logger->debug('3DS Auth Payment Response');
        //$this->logger->debug(json_encode($paymentResponse->toArray(),JSON_PRETTY_PRINT));
        return $paymentResponse;

    }

    protected function confirm3DSRequest($spiToken){

        $apiEndPoint =  static::FAC_API_ENDPOINTS['3ds']['payment'];
        $result = $this->httpPost(
            $this->getFullEndpointUrl($apiEndPoint),
            json_encode($spiToken),
            $this->getRequestHeaders([
                'Accept'=>'text/plain',
                'Content-Type'=>'application/json-patch+json'
            ]),
            ['debug'=>$this->isDebugMode()]
        );
        $result['success'] = $result['success'] &&
        !isset($result['data']['Errors']) &&
        isset($result['data']['IsoResponseCode']) &&
        $result['data']['IsoResponseCode']=="00";
        //$this->logger->debug('Confirm 3DS Raw Response');
        //$this->logger->debug(json_encode($result,JSON_PRETTY_PRINT));

        $paymentResponse = (new AuthPaymentResponse())
        ->setSuccess($result['success'])
        ->setData(isset($result['data']) ? $result['data'] : null )
        ->setMessage(isset($result['data']['ResponseMessage']) ? $result['data']['ResponseMessage'] : (
           $result['success'] ? 'Successful' : 'Request not successful'
       ))
        ->setErrorCode(
           !$result['success'] ? (
               isset($result['data']['Errors'][0]['Code']) ? $result['data']['Errors'][0]['Code'] : (
                   isset($result['data']['IsoResponseCode']) ? $result['data']['IsoResponseCode'] : 0
               )
           ) : 0
       )
        ->setTransactionIdentifier(
           isset($result['data']['TransactionIdentifier']) ? $result['data']['TransactionIdentifier'] : null
       )
        ->setOrderIdentifier(
           isset($result['data']['OrderIdentifier']) ? $result['data']['OrderIdentifier'] : null
       )
        ->setTokenizedPan(
            isset($result['data']['PanToken']) ?  $result['data']['PanToken'] : null
        );
        //$this->logger->debug('3DS Auth Payment Response');
        //$this->logger->debug(json_encode($paymentResponse->toArray(),JSON_PRETTY_PRINT));
        return $paymentResponse;
    }

    /**
     * @return FacPayments\Entities\Requests\AuthPaymentResponse
     */
    public function tokenize(TokenizationRequest $request){
        return $this->riskManagement(
            new RiskMgmtRequest(
                $request->toArray()
            )
        );
    }

    /**
     * @return FacPayments\Entities\Responses\PaymentResponse
     */
    protected function processUsingFACPG2(PaymentRequest $request){
        $className = get_class($request);
        $response = new PaymentResponse([
            'message'=>$className.' not yet implemented in existing service'
        ]);
        switch($className){
            case AuthRequest::class    : $response= parent::authorize($request); break;
            case RiskMgmtRequest::class: $response= parent::authorize(new AuthRequest($request->toArray())); break;
            case CaptureRequest::class : $response= parent::capture($request); break;
            case SaleRequest::class    : $response= parent::authorizeAndCapture($request); break;
        }

        return $response;
    }



}
