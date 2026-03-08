<?php

namespace DigitalLabs\Installer\Database\Seeders;

use Illuminate\Database\Seeder;
use DigitalLabs\Installer\Database\Seeders\Attribute\DatabaseSeeder as AttributeSeeder;
use DigitalLabs\Installer\Database\Seeders\Category\DatabaseSeeder as CategorySeeder;
use DigitalLabs\Installer\Database\Seeders\CMS\DatabaseSeeder as CMSSeeder;
use DigitalLabs\Installer\Database\Seeders\Core\DatabaseSeeder as CoreSeeder;
use DigitalLabs\Installer\Database\Seeders\Customer\DatabaseSeeder as CustomerSeeder;
use DigitalLabs\Installer\Database\Seeders\Inventory\DatabaseSeeder as InventorySeeder;
use DigitalLabs\Installer\Database\Seeders\Shop\ThemeCustomizationTableSeeder as ShopSeeder;
use DigitalLabs\Installer\Database\Seeders\SocialLogin\DatabaseSeeder as SocialLoginSeeder;
use DigitalLabs\Installer\Database\Seeders\User\DatabaseSeeder as UserSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @param  array  $parameters
     * @return void
     */
    public function run($parameters = [])
    {
        $this->call(AttributeSeeder::class, false, ['parameters' => $parameters]);
        $this->call(CategorySeeder::class, false, ['parameters' => $parameters]);
        $this->call(CoreSeeder::class, false, ['parameters' => $parameters]);
        $this->call(CustomerSeeder::class, false, ['parameters' => $parameters]);
        $this->call(CMSSeeder::class, false, ['parameters' => $parameters]);
        $this->call(InventorySeeder::class, false, ['parameters' => $parameters]);
        $this->call(SocialLoginSeeder::class, false, ['parameters' => $parameters]);
        $this->call(ShopSeeder::class, false, ['parameters' => $parameters]);
        $this->call(UserSeeder::class, false, ['parameters' => $parameters]);
    }
}
