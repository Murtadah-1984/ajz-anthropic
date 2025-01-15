<?php

namespace Database\Factories;

use App\Models\AssistantOutput;
use App\Models\AssistantRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class AssistantOutputFactory extends Factory
{
    protected $model = AssistantOutput::class;

    public function definition(): array
    {
        return [
            'assistant_role_id' => AssistantRole::factory(),
            'output' => $this->faker->paragraph(),
            'feedback_score' => $this->faker->numberBetween(1, 5),
            'metadata' => [
                'tokens' => $this->faker->numberBetween(100, 1000),
                'processing_time' => $this->faker->randomFloat(2, 0.1, 2.0)
            ],
            'generated_at' => Carbon::now(),
        ];
    }

    public function highPerforming(): self
    {
        return $this->state(fn (array $attributes) => [
            'feedback_score' => $this->faker->numberBetween(4, 5)
        ]);
    }

    public function lowPerforming(): self
    {
        return $this->state(fn (array $attributes) => [
            'feedback_score' => $this->faker->numberBetween(1, 2)
        ]);
    }
}
