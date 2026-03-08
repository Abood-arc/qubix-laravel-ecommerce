<?php

namespace DigitalLabs\Marketing\Repositories;

use DigitalLabs\Core\Eloquent\Repository;

class URLRewriteRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return 'DigitalLabs\Marketing\Contracts\URLRewrite';
    }
}
