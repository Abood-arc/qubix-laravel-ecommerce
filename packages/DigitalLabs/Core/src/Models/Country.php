<?php

namespace DigitalLabs\Core\Models;

use DigitalLabs\Core\Contracts\Country as CountryContract;
use DigitalLabs\Core\Eloquent\TranslatableModel;

class Country extends TranslatableModel implements CountryContract
{
    public $timestamps = false;

    public $translatedAttributes = ['name'];

    protected $with = ['translations'];

    /**
     * Get the States.
     */
    public function states()
    {
        return $this->hasMany(CountryStateProxy::modelClass());
    }
}
