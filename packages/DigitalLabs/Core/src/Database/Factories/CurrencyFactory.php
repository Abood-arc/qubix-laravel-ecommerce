<?php

namespace DigitalLabs\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use DigitalLabs\Core\Enums\CurrencyPositionEnum;
use DigitalLabs\Core\Models\Currency;

class CurrencyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Currency::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->currencyCode,
            'name' => $this->faker->word,
            'decimal' => 2,
            'group_separator' => ',',
            'decimal_separator' => '.',
            'currency_position' => CurrencyPositionEnum::LEFT->value,
        ];
    }
}
