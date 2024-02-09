<?php

namespace FacPayments\Contracts\Repositories;

interface ICurrencyRepository {
    /**
     * Retrives Minor Unit For Currency Code (3)
     * @return int
     */
    function getMinorUnit($currencyCode);

    /**
     * Retrives Numeric Code For Currency Code (3)
     * @return int
     */
    function getNumericCode($currencyCode);

}