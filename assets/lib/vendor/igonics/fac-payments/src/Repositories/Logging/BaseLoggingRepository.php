<?php

namespace FacPayments\Repositories\Logging;

use FacPayments\Constants\LogLevel;
use FacPayments\Contracts\Repositories\ILoggingRepository;

abstract class BaseLoggingRepository implements ILoggingRepository{
    private $logLevel = LogLevel::ERROR;

    public function setLogLevel($level){
        $this->logLevel = LogLevel::getValid($level);
    }
    public function getLogLevel(){
        return $this->logLevel;
    }

    protected function shouldTrace(){
        return $this->logLevel <= LogLevel::TRACE;
    }

    protected function shouldDebug(){
        return $this->logLevel <= LogLevel::DEBUG;
    }

    protected function shouldInfo(){
        return $this->logLevel <= LogLevel::INFO;
    }

    protected function shouldWarn(){
        return $this->logLevel <= LogLevel::WARN;
    }

    protected function shouldError(){
        return $this->logLevel <= LogLevel::ERROR;
    }


}