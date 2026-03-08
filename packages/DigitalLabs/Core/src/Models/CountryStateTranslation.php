<?php

namespace DigitalLabs\Core\Models;

use Illuminate\Database\Eloquent\Model;
use DigitalLabs\Core\Contracts\CountryStateTranslation as CountryStateTranslationContract;

class CountryStateTranslation extends Model implements CountryStateTranslationContract
{
    public $timestamps = false;

    protected $fillable = ['default_name'];
}
