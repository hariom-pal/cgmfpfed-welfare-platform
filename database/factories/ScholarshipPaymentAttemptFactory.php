<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Scholarship\Enums\PaymentAttemptState;
use App\Models\ScholarshipApplication;
use App\Models\ScholarshipPaymentAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScholarshipPaymentAttempt>
 */
final class ScholarshipPaymentAttemptFactory extends Factory
{
    protected $model = ScholarshipPaymentAttempt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scholarship_application_id' => ScholarshipApplication::factory()->walletPending(),
            'wallet_transaction_id' => null,
            'payment_purpose' => 'vle_submission_fee',
            'payment_channel' => 'csc_wallet',
            'transaction_number' => 'FDGC'.$this->faker->unique()->numerify('########'),
            'amount' => 50,
            'payment_state' => PaymentAttemptState::Pending->value,
            'payment_requested_at' => now(),
            'payment_completed_at' => null,
            'failure_reason' => null,
            'attempt_number' => 1,
            'request_payload' => null,
            'response_payload' => null,
            'created_by' => User::factory(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'payment_state' => PaymentAttemptState::Completed->value,
            'payment_completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'payment_state' => PaymentAttemptState::Failed->value,
            'failure_reason' => 'Wallet payment failed',
        ]);
    }
}
