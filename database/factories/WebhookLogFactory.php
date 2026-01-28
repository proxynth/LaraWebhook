<?php

namespace Proxynth\Larawebhook\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Proxynth\Larawebhook\Models\WebhookLog;

class WebhookLogFactory extends Factory
{
    protected $model = WebhookLog::class;

    public function definition(): array
    {
        $services = ['stripe', 'github', 'slack'];
        $statuses = ['success', 'failed'];

        $service = $this->faker->randomElement($services);
        $status = $this->faker->randomElement($statuses);

        $events = [
            'stripe' => ['payment_intent.succeeded', 'payment_intent.failed', 'charge.succeeded'],
            'github' => ['push', 'pull_request', 'release'],
            'slack' => ['message.channels', 'app_mention'],
        ];

        return [
            'service' => $service,
            'event' => $this->faker->randomElement($events[$service]),
            'status' => $status,
            'payload' => [
                'id' => $this->faker->uuid,
                'type' => $this->faker->word,
                'data' => [
                    'amount' => $this->faker->numberBetween(10, 100),
                    'currency' => $this->faker->currencyCode,
                ],
            ],
            'error_message' => $status === 'failed' ? $this->faker->sentence() : null,
            'attempt' => $this->faker->numberBetween(0, 3),
            'created_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'updated_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }

    /**
     * Indicate that the webhook log is successful.
     */
    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'success',
            'error_message' => null,
        ]);
    }

    /**
     * Indicate that the webhook log has failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'error_message' => fake()->sentence(),
        ]);
    }

    /**
     * Set a specific service.
     */
    public function forService(string $service): static
    {
        return $this->state(fn (array $attributes) => [
            'service' => $service,
        ]);
    }
}
