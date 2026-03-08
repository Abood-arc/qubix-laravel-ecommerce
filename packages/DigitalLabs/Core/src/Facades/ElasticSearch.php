<?php

namespace DigitalLabs\Core\Facades;

use Illuminate\Support\Facades\Facade;
use DigitalLabs\Core\ElasticSearch as BaseElasticSearch;

class ElasticSearch extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return BaseElasticSearch::class;
    }
}
