<?php

namespace Database\Factories;

use App\Models\BusinessPartner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessPartner>
 */
class BusinessPartnerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '07'.fake()->numerify('########'),
            'address' => fake()->city(),
            'notes' => null,
            'is_active' => true,
        ];
    }
}
