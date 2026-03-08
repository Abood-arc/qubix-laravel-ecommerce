<?php

namespace DigitalLabs\SocialLogin\Providers;

use DigitalLabs\Core\Providers\CoreModuleServiceProvider;

class ModuleServiceProvider extends CoreModuleServiceProvider
{
    /**
     * Models.
     *
     * @var array
     */
    protected $models = [
        \DigitalLabs\SocialLogin\Models\CustomerSocialAccount::class,
    ];
}
