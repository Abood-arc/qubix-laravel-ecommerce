<?php

namespace DigitalLabs\Core\Models;

use DigitalLabs\Core\Contracts\CountryState as CountryStateContract;
use DigitalLabs\Core\Eloquent\TranslatableModel;

class CountryState extends TranslatableModel implements CountryStateContract
{
    public $timestamps = false;

    public $translatedAttributes = ['default_name'];

    protected $with = ['translations'];

    /**
     * @return array
     */
    public function toArray()
    {
        $array = parent::toArray();

        $array['default_name'] = $this->default_name;

        return $array;
    }
}
