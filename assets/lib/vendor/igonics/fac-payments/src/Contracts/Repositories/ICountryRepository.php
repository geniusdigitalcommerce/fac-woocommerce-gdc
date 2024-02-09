<?php

namespace FacPayments\Contracts\Repositories;

interface ICountryRepository {
    /**
     * Retrives Numeric Code For Country Code (2)
     * @return int
     */
    function getNumericCode($countryCode);

}