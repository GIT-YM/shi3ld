<?php

namespace Database\Factories;

use App\Models\ServiceEnquiry;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ServiceEnquiry> */
class ServiceEnquiryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference' => fake()->uuid(), 'service_type' => 'domestic',
            'name' => fake()->name(), 'email' => fake()->unique()->safeEmail(),
            'postcode' => 'SW1A 1AA', 'property_type' => 'house', 'frequency' => 'weekly',
            'preferred_contact' => 'email', 'requirements' => 'Please discuss a regular cleaning schedule.',
            'privacy_acknowledged_at' => now(),
        ];
    }
}
