<?php

namespace DigitalLabs\Category\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DigitalLabs\Category\Contracts\CategoryTranslation as CategoryTranslationContract;
use DigitalLabs\Category\Database\Factories\CategoryTranslationFactory;

class CategoryTranslation extends Model implements CategoryTranslationContract
{
    use HasFactory;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'description',
        'slug',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'locale_id',
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return CategoryTranslationFactory::new();
    }
}
