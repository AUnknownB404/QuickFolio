<?php

namespace Database\Factories;

use App\Models\Portfolio;
use App\Models\PortfolioProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PortfolioProfile>
 */
class PortfolioProfileFactory extends Factory
{
    protected $model = PortfolioProfile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'portfolio_id' => Portfolio::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'headline' => fake()->jobTitle(),
            'about' => fake()->paragraph(),
            'location' => fake()->city(),
            'phone' => fake()->phoneNumber(),
            'profile_image' => null,
            'resume' => null,
        ];
    }
}
