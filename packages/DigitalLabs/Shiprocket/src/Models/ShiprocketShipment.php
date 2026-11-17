<?php

namespace DigitalLabs\Shiprocket\Models;

use DigitalLabs\Sales\Models\OrderProxy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The Shiprocket side of one order: booking progress, AWB and parcel status.
 */
class ShiprocketShipment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_BOOKING = 'booking';

    public const STATUS_BOOKED = 'booked';

    public const STATUS_PICKUP_SCHEDULED = 'pickup_scheduled';

    public const STATUS_IN_TRANSIT = 'in_transit';

    public const STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_RTO = 'rto';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_FAILED = 'failed';

    /**
     * Statuses after which webhooks no longer move the parcel on.
     */
    protected const TERMINAL = [self::STATUS_CANCELLED, self::STATUS_FAILED];

    protected const RANKS = [
        self::STATUS_PENDING => 0,
        self::STATUS_BOOKING => 1,
        self::STATUS_BOOKED => 2,
        self::STATUS_PICKUP_SCHEDULED => 3,
        self::STATUS_IN_TRANSIT => 4,
        self::STATUS_OUT_FOR_DELIVERY => 5,
        self::STATUS_DELIVERED => 6,
        self::STATUS_RTO => 7,
        self::STATUS_CANCELLED => 99,
        self::STATUS_FAILED => 99,
    ];

    protected $table = 'shiprocket_shipments';

    protected $fillable = [
        'order_id',
        'status',
        'generation',
        'shiprocket_order_id',
        'shipment_id',
        'channel_order_id',
        'awb_code',
        'courier_name',
        'label_url',
        'pickup_scheduled_at',
        'last_event',
        'failure_reason',
        'store_shipment_id',
    ];

    protected $casts = [
        'pickup_scheduled_at' => 'datetime',
        'generation' => 'integer',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'generation' => 0,
    ];

    public static function rank(string $status): int
    {
        return self::RANKS[$status] ?? -1;
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL, true);
    }

    /**
     * The courier has the parcel (in transit or later, including a return).
     */
    public function isPickedUp(): bool
    {
        return ! $this->isTerminal() && self::rank($this->status) >= self::RANKS[self::STATUS_IN_TRANSIT];
    }

    /**
     * Move the parcel forward. Webhooks can arrive late or out of order, so a status never
     * goes backwards and a cancelled booking is left alone.
     *
     * A failed booking is left alone too, unless the courier was booked before it failed
     * (it has an AWB): that parcel is real and is followed from `booked` on. A cancel
     * starts a new booking generation, like an admin cancel does.
     */
    public function advanceTo(string $status): bool
    {
        if ($this->status === self::STATUS_CANCELLED || self::rank($status) <= $this->progressRank()) {
            return false;
        }

        $this->status = $status;

        if ($status === self::STATUS_CANCELLED) {
            $this->generation++;
        }

        return $this->save();
    }

    /**
     * How far the parcel has got, for advanceTo(): a failed booking that holds an AWB
     * counts as booked, one without as finished.
     */
    protected function progressRank(): int
    {
        if ($this->status === self::STATUS_FAILED) {
            return filled($this->awb_code) ? self::RANKS[self::STATUS_BOOKED] : self::RANKS[self::STATUS_FAILED];
        }

        return self::rank($this->status);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(OrderProxy::modelClass(), 'order_id');
    }
}
