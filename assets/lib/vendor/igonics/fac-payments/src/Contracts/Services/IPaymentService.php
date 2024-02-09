<?php

namespace FacPayments\Contracts\Services;

use FacPayments\Entities\Requests\PaymentRequest;
use FacPayments\Entities\Requests\AuthRequest;
use FacPayments\Entities\Requests\CaptureRequest;
use FacPayments\Entities\Requests\SaleRequest;
use FacPayments\Entities\Requests\RefundRequest;
use FacPayments\Entities\Requests\VoidRequest;
use FacPayments\Entities\Requests\TokenizationRequest;

interface IPaymentService{

    /**
     * @return FacPayments\Entities\Requests\PaymentResponse
     */
    function authorize(AuthRequest $request);

    /**
     * @return FacPayments\Entities\Requests\PaymentResponse
     */
    function capture(CaptureRequest $request);

    /**
     * @return FacPayments\Entities\Requests\PaymentResponse
     */
    function authorizeAndCapture(SaleRequest $request);

    /**
     * @return FacPayments\Entities\Requests\PaymentResponse
     */
    function refund(RefundRequest $request);

    /**
     * @return FacPayments\Entities\Requests\PaymentResponse
     */
    function void(VoidRequest $request);

    /**
     * @return FacPayments\Entities\Requests\AuthPaymentResponse
     */
    function tokenize(TokenizationRequest $request);

    /**
     * @return FacPayments\Entities\Requests\PaymentResponse
     */
    function confirmTransaction(array $responseData = []);

    
}