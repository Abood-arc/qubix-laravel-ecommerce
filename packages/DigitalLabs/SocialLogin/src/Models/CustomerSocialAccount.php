<?php

namespace DigitalLabs\SocialLogin\Models;

use Illuminate\Database\Eloquent\Model;
use DigitalLabs\Customer\Models\CustomerProxy;
use DigitalLabs\SocialLogin\Contracts\CustomerSocialAccount as CustomerSocialAccountContract;

class CustomerSocialAccount extends Model implements CustomerSocialAccountContract
{
    protected $fillable = [
        'customer_id',
        'provider_name',
        'provider_id',
    ];

    /**
     * Get the customer that belongs to the social aoount.
     */
    public function customer()
    {
        return $this->belongsTo(CustomerProxy::modelClass());
    }
}
