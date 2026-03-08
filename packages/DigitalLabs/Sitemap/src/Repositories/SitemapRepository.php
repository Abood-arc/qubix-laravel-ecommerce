<?php

namespace DigitalLabs\Sitemap\Repositories;

use DigitalLabs\Core\Eloquent\Repository;
use DigitalLabs\Sitemap\Contracts\Sitemap;

class SitemapRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return Sitemap::class;
    }
}
