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
            'phone' => '+2782'.fake()->unique()->numerify('#######'),
            'pw' => 'secret123',
        ];
    }

    public function admin(): static
    {
        return $this->state(['status' => 'admin']);
    }

    /** A new registration the Club Secretary has not activated yet. */
    public function awaitingActivation(): static
    {
        return $this->state(['status' => User::AWAITING_ACTIVATION]);
    }

    public function withStatus(string $status): static
    {
        return $this->state(['status' => $status]);
    }
}
