<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\WebAuthnCredential;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WebAuthnCredential>
 */
final class WebAuthnCredentialFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = WebAuthnCredential::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'authenticatable_type' => User::class,
            'authenticatable_id' => User::factory(),
            'id' => fake()->uuid(),
            'user_id' => fake()->uuid(),
            'alias' => fake()->words(2, true),
            'counter' => 0,
            'rp_id' => config('app.url'),
            'origin' => config('app.url'),
            'transports' => json_encode(['internal']),
            'aaguid' => fake()->uuid(),
            'public_key' => base64_encode(random_bytes(64)),
            'attestation_format' => 'none',
            'certificates' => null,
            'disabled_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Indicate that the credential is disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn(array $attributes) => [
            'disabled_at' => now(),
        ]);
    }

    /**
     * Indicate that the credential is enabled.
     */
    public function enabled(): static
    {
        return $this->state(fn(array $attributes) => [
            'disabled_at' => null,
        ]);
    }
}
