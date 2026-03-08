<?php

namespace DigitalLabs\Marketing\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DigitalLabs\Marketing\Contracts\Event as EventContract;
use DigitalLabs\Marketing\Database\Factories\EventFactory;

class Event extends Model implements EventContract
{
    use HasFactory;

    /**
     * Define the models table name
     *
     * @var string
     */
    protected $table = 'marketing_events';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'description',
        'date',
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return EventFactory::new();
    }
}
