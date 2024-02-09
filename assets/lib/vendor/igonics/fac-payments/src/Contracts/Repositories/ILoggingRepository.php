<?php

namespace FacPayments\Contracts\Repositories;

interface ILoggingRepository {
    function setLogLevel($level);
    function getLogLevel();
    function trace($message);
    function info($message);
    function debug($message);
    function warn($message);
    function error($message,\Exception $e=null);
}