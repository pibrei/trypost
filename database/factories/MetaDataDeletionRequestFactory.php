<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MetaDataDeletionRequest\Status;
use App\Models\MetaDataDeletionRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MetaDataDeletionRequest>
 */
class MetaDataDeletionRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'confirmation_code' => Str::lower(Str::random(24)),
            'meta_user_id' => (string) $this->faker->randomNumber(9),
            'status' => Status::Completed,
            'affected_accounts' => [],
            'completed_at' => now(),
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (): array => ['status' => Status::Failed, 'completed_at' => null]);
    }
}
