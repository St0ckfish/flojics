<?php

namespace Database\Factories;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Agent;
use App\Models\Customer;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'agent_id' => Agent::factory(),
            'subject' => fake()->sentence(6),
            'description' => fake()->optional()->paragraph(),
            'priority' => fake()->randomElement(TicketPriority::cases()),
            'status' => TicketStatus::Open,
            'escalated_at' => null,
        ];
    }

    public function unassigned(): static
    {
        return $this->state(fn () => [
            'agent_id' => null,
        ]);
    }

    public function escalated(): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::Escalated,
            'priority' => TicketPriority::Urgent,
            'escalated_at' => now()->subHours(2),
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::InProgress,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::Closed,
        ]);
    }
}
