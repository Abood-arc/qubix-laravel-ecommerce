<?php

namespace DigitalLabs\Customer\Repositories;

use DigitalLabs\Core\Eloquent\Repository;
use DigitalLabs\Customer\Contracts\Wishlist;

class WishlistRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return Wishlist::class;
    }
}
