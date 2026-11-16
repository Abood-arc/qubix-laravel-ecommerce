<?php

namespace DigitalLabs\Sales\Models;

use DigitalLabs\Product\Type\AbstractType;
use DigitalLabs\Sales\Contracts\RefundItem as RefundItemContract;
use Illuminate\Database\Eloquent\Model;

class RefundItem extends Model implements RefundItemContract
{
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'additional' => 'array',
    ];

    /**
     * Retrieve type instance (the admin refund page reads each item's SKU through it,
     * as InvoiceItem and ShipmentItem already allow).
     */
    public function getTypeInstance(): AbstractType
    {
        return $this->order_item->getTypeInstance();
    }

    /**
     * Get the Refund record associated with the Refund item.
     */
    public function refund()
    {
        return $this->belongsTo(RefundProxy::modelClass());
    }

    /**
     * Get the order item record associated with the Refund item.
     */
    public function order_item()
    {
        return $this->belongsTo(OrderItemProxy::modelClass());
    }

    /**
     * Get the Refund record associated with the Refund item.
     */
    public function product()
    {
        return $this->morphTo();
    }

    /**
     * Get the child item record associated with the Refund item.
     */
    public function child()
    {
        return $this->hasOne(RefundItemProxy::modelClass(), 'parent_id');
    }
}
