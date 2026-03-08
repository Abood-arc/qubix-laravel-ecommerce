<?php

namespace DigitalLabs\Customer\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use DigitalLabs\Customer\Models\CompareItem;

class CompareItemFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = CompareItem::class;

    /**
     * Define the model's default state.
     *
     * @throws \Exception
     */
    public function definition(): array
    {
        return [];
    }
}
