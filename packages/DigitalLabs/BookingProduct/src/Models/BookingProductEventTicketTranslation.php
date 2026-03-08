<?php

namespace DigitalLabs\BookingProduct\Models;

use Illuminate\Database\Eloquent\Model;
use DigitalLabs\BookingProduct\Contracts\BookingProductEventTicketTranslation as BookingProductEventTicketTranslationContract;

class BookingProductEventTicketTranslation extends Model implements BookingProductEventTicketTranslationContract
{
    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'description',
    ];
}
