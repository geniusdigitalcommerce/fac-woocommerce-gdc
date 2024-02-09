<?php

namespace FacPayments\Services;

use FacPayments\Contracts\Repositories\ILoggingRepository;
use FacPayments\Contracts\Repositories\ICacheRepository;
use FacPayments\Contracts\Services\IPaymentService;
use FacPayments\Entities\Configuration\FacConfig;
use FacPayments\External\Traits\HttpRequestTrait;

use FacPayments\Repositories\Logging\NullLoggingRepository;
use FacPayments\Repositories\Cache\JsonFileCacheRepository;

abstract class PaymentService implements IPaymentService {

    const DEFAULT_TRANSACTION_CACHE_TIME_IN_SECONDS=15;

    use HttpRequestTrait;
    /**
     * @instance FacPayments\Entities\Configuration\FacConfig
     */
    protected $config;

    /**
     * @instance FacPayments\Contracts\Repositories\ILoggingRepository;
     */
    protected $logger;

    /**
     * @instance FacPayments\Contracts\Repositories\ICacheRepository;
     */
    protected $cache;

    public function __construct(
        FacConfig $config = null,
        ILoggingRepository $logger = null,
        ICacheRepository $cache = null
    ){
        $this->config = $config ?? new FacConfig();
        $this->logger = $logger ?? new NullLoggingRepository();
        $this->cache = $cache ?? new JsonFileCacheRepository();
    }

    abstract function getVersion();

    protected function isDebugMode(){
        return !!$this->config->debugMode;
    }

    protected function isTestMode(){
        return !!$this->config->testMode;
    }

    protected function isKountEnabled(){
        return $this->config->isKountEnabled();
    }

    protected function isHppEnabled(){
        return $this->config->isHppEnabled();
    }

    protected function updateTransactionCache($transactionId,$key,$value){
        $details = $this->getTransactionFromCache($transactionId,[]);
        $details[$key]=$value;
        $this->cache->put(
            'TRANS_'.$transactionId,
            $details,
            (
                $this->config->transactionCacheTime || $this->config->transactionCacheTime === 0 ? 
                $this->config->transactionCacheTime : 
                static::DEFAULT_TRANSACTION_CACHE_TIME_IN_SECONDS
            )
        );
    }

    protected function forgetTransaction($transactionId){
        $this->cache->forget('TRANS_'.$transactionId);
    }

    protected function getTransactionDetailFromCache($transactionId,$key,$defaultValue=null){
        $details = $this->getTransactionFromCache($transactionId,[]);
        return isset($details[$key]) ?  $details[$key] : $defaultValue;
    }

    protected function getTransactionFromCache($transactionId){
        return $this->cache->get('TRANS_'.$transactionId,[]);
    }
}