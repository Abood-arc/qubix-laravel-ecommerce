<?php

namespace DigitalLabs\Customer\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use DigitalLabs\Core\Models\Address;
use DigitalLabs\Customer\Contracts\CustomerAddress as CustomerAddressContract;
use DigitalLabs\Customer\Database\Factories\CustomerAddressFactory;

class CustomerAddress extends Address implements CustomerAddressContract
{
    use HasFactory;

    /**
     * Define the customer address type.
     */
    public const ADDRESS_TYPE = 'customer';

    /**
     * Define the attributes of the customer address model.
     *
     * @var array default values
     */
    protected $attributes = [
        'address_type' => self::ADDRESS_TYPE,
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function boot(): void
    {
        static::addGlobalScope('address_type', static function (Builder $builder) {
            $builder->where('address_type', self::ADDRESS_TYPE);
        });

        parent::boot();
    }

    /**
     * Create a new factory instance for the model
     */
    protected static function newFactory(): Factory
    {
        return CustomerAddressFactory::new();
    }
}
