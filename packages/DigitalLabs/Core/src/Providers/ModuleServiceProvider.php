<?php

namespace DigitalLabs\Core\Providers;

class ModuleServiceProvider extends CoreModuleServiceProvider
{
    /**
     * Models.
     *
     * @var array
     */
    protected $models = [
        \DigitalLabs\Core\Models\Channel::class,
        \DigitalLabs\Core\Models\CoreConfig::class,
        \DigitalLabs\Core\Models\Country::class,
        \DigitalLabs\Core\Models\CountryState::class,
        \DigitalLabs\Core\Models\CountryStateTranslation::class,
        \DigitalLabs\Core\Models\CountryTranslation::class,
        \DigitalLabs\Core\Models\Currency::class,
        \DigitalLabs\Core\Models\CurrencyExchangeRate::class,
        \DigitalLabs\Core\Models\Locale::class,
        \DigitalLabs\Core\Models\SubscribersList::class,
        \DigitalLabs\Core\Models\Visit::class,
    ];
}
