<?php

namespace DigitalLabs\CartRule\Providers;

use DigitalLabs\Core\Providers\CoreModuleServiceProvider;

class ModuleServiceProvider extends CoreModuleServiceProvider
{
    /**
     * Models.
     *
     * @var array
     */
    protected $models = [
        \DigitalLabs\CartRule\Models\CartRule::class,
        \DigitalLabs\CartRule\Models\CartRuleCoupon::class,
        \DigitalLabs\CartRule\Models\CartRuleCouponUsage::class,
        \DigitalLabs\CartRule\Models\CartRuleCustomer::class,
        \DigitalLabs\CartRule\Models\CartRuleTranslation::class,
    ];
}
