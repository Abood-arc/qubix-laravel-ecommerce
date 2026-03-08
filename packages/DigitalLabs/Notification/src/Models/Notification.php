<?php

namespace DigitalLabs\Notification\Models;

use Illuminate\Database\Eloquent\Model;
use DigitalLabs\Notification\Contracts\Notification as NotificationContract;
use DigitalLabs\Sales\Models\OrderProxy;

class Notification extends Model implements NotificationContract
{
    protected $fillable = [
        'type',
        'read',
        'order_id',
    ];

    /**
     * Get Order Details.
     */
    public function order()
    {
        return $this->belongsTo(OrderProxy::modelClass());
    }
}
