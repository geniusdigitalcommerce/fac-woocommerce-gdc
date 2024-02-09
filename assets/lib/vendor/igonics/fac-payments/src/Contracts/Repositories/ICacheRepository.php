<?php

namespace FacPayments\Contracts\Repositories;

interface ICacheRepository {
    function put($key,$value,$timeInSeconds=10);
    function get($key,$defaultValue=null);
    function forget($key);
    function clear();
}