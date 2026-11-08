<?php

namespace DigitalLabs\Razorpay\Models;

use Illuminate\Database\Eloquent\Model;

class RazorpayPayment extends Model
{
    public const STATUS_CREATED = 'created';

    public const STATUS_PAID = 'paid';

    public const STATUS_REFUNDING = 'refunding';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_REFUND_FAILED = 'refund_failed';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'snapshot' => 'array',
    ];

    /**
     * Whether a refund has been started, finished or has failed.
     */
    public function isRefundStarted(): bool
    {
        return in_array($this->status, [
            self::STATUS_REFUNDING,
            self::STATUS_REFUNDED,
            self::STATUS_REFUND_FAILED,
        ]);
    }
}
