<?php

namespace Database\Factories;

use App\Models\ServicePage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ServicePage> */
class ServicePageFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'slug' => Str::slug($title), 'category' => 'domestic', 'title' => $title,
            'eyebrow' => 'Domestic cleaning', 'summary' => fake()->sentence(),
            'description' => fake()->paragraph(), 'tasks' => ['Surface cleaning'],
            'extras' => [], 'preparation' => 'Discuss access before the visit.',
            'frequency' => 'One-off or regular', 'faqs' => [], 'published' => true,
        ];
    }
}
