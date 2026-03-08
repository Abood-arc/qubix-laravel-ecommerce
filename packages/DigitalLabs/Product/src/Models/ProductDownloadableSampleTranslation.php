<?php

namespace DigitalLabs\Product\Models;

use Illuminate\Database\Eloquent\Model;
use DigitalLabs\Product\Contracts\ProductDownloadableSampleTranslation as ProductDownloadableSampleTranslationContract;

class ProductDownloadableSampleTranslation extends Model implements ProductDownloadableSampleTranslationContract
{
    public $timestamps = false;

    protected $fillable = ['title'];
}
