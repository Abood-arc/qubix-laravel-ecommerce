<?php

namespace DigitalLabs\CartRule\Models;

use Illuminate\Database\Eloquent\Model;
use DigitalLabs\CartRule\Contracts\CartRuleTranslation as CartRuleTranslationContract;

class CartRuleTranslation extends Model implements CartRuleTranslationContract
{
    public $timestamps = false;

    protected $fillable = ['label'];
}
