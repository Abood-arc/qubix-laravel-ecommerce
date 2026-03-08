<?php

return [
    /**
     * Application service providers.
     */
    App\Providers\AppServiceProvider::class,

    /**
     * DigitalLabs's service providers.
     */
    DigitalLabs\Admin\Providers\AdminServiceProvider::class,
    DigitalLabs\Attribute\Providers\AttributeServiceProvider::class,
    DigitalLabs\BookingProduct\Providers\BookingProductServiceProvider::class,
    DigitalLabs\CMS\Providers\CMSServiceProvider::class,
    DigitalLabs\CartRule\Providers\CartRuleServiceProvider::class,
    DigitalLabs\CatalogRule\Providers\CatalogRuleServiceProvider::class,
    DigitalLabs\Category\Providers\CategoryServiceProvider::class,
    DigitalLabs\Checkout\Providers\CheckoutServiceProvider::class,
    DigitalLabs\Core\Providers\CoreServiceProvider::class,
    DigitalLabs\Core\Providers\EnvValidatorServiceProvider::class,
    DigitalLabs\Customer\Providers\CustomerServiceProvider::class,
    DigitalLabs\DataGrid\Providers\DataGridServiceProvider::class,
    DigitalLabs\DataTransfer\Providers\DataTransferServiceProvider::class,
    DigitalLabs\DebugBar\Providers\DebugBarServiceProvider::class,
    DigitalLabs\FPC\Providers\FPCServiceProvider::class,
    DigitalLabs\GDPR\Providers\GDPRServiceProvider::class,
    DigitalLabs\Installer\Providers\InstallerServiceProvider::class,
    DigitalLabs\Inventory\Providers\InventoryServiceProvider::class,
    DigitalLabs\MagicAI\Providers\MagicAIServiceProvider::class,
    DigitalLabs\Marketing\Providers\MarketingServiceProvider::class,
    DigitalLabs\Notification\Providers\NotificationServiceProvider::class,
    DigitalLabs\Payment\Providers\PaymentServiceProvider::class,
    DigitalLabs\Paypal\Providers\PaypalServiceProvider::class,
    DigitalLabs\Product\Providers\ProductServiceProvider::class,
    DigitalLabs\Rule\Providers\RuleServiceProvider::class,
    DigitalLabs\Sales\Providers\SalesServiceProvider::class,
    DigitalLabs\Shipping\Providers\ShippingServiceProvider::class,
    DigitalLabs\Shop\Providers\ShopServiceProvider::class,
    DigitalLabs\Sitemap\Providers\SitemapServiceProvider::class,
    DigitalLabs\SocialLogin\Providers\SocialLoginServiceProvider::class,
    DigitalLabs\SocialShare\Providers\SocialShareServiceProvider::class,
    DigitalLabs\Tax\Providers\TaxServiceProvider::class,
    DigitalLabs\Theme\Providers\ThemeServiceProvider::class,
    DigitalLabs\User\Providers\UserServiceProvider::class,
];
