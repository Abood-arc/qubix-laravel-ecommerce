<?php

namespace DigitalLabs\Core\Repositories;

use DigitalLabs\Core\Eloquent\Repository;

class ExchangeRateRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return 'DigitalLabs\Core\Contracts\CurrencyExchangeRate';
    }
}
