<?php

namespace Database\Factories;

use App\Models\User;
use App\Support\ApiTokenGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ApiToken>
 */
class ApiTokenFactory extends Factory
{
    /**
     * The plaintext token for the most recently made instance — factories
     * only ever store the hash, so tests need this to authenticate with it.
     */
    public string $plainTextToken = '';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $this->plainTextToken = ApiTokenGenerator::generate();

        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'token_hash' => ApiTokenGenerator::hash($this->plainTextToken),
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
