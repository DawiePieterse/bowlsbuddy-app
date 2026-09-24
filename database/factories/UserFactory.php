<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'alias' => fake()->name(),
            'status' => 'enabled',
            'email' => fake()->unique()->safeEmail(),
            'pw' => 'secret123',
        ];
    }

    public function admin(): static
    {
        return $this->state(['status' => 'admin']);
    }

    public function withStatus(string $status): static
    {
        return $this->state(['status' => $status]);
    }
}
