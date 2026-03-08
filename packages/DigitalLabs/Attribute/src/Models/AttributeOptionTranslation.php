<?php

namespace DigitalLabs\Attribute\Models;

use Illuminate\Database\Eloquent\Model;
use DigitalLabs\Attribute\Contracts\AttributeOptionTranslation as AttributeOptionTranslationContract;

class AttributeOptionTranslation extends Model implements AttributeOptionTranslationContract
{
    public $timestamps = false;

    protected $fillable = ['label'];
}
