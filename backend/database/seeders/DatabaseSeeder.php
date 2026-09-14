<?php

namespace Database\Seeders;

use App\Enums\TicketPriority;
use App\Models\Agent;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $customers = Customer::factory()->count(4)->create();
        $agents = Agent::factory()->count(3)->create();

        Ticket::factory()
            ->count(4)
            ->recycle($customers)
            ->recycle($agents)
            ->create();

        Ticket::factory()
            ->inProgress()
            ->recycle($customers)
            ->recycle($agents)
            ->create([
                'subject' => 'Cannot reset password after SSO change',
                'priority' => TicketPriority::High,
            ]);

        Ticket::factory()
            ->unassigned()
            ->recycle($customers)
            ->create([
                'subject' => 'New customer onboarding checklist',
                'priority' => TicketPriority::Medium,
            ]);

        Ticket::factory()
            ->escalated()
            ->recycle($customers)
            ->recycle($agents)
            ->count(2)
            ->create();

        Ticket::factory()
            ->closed()
            ->recycle($customers)
            ->recycle($agents)
            ->count(2)
            ->create([
                'priority' => TicketPriority::Low,
            ]);
    }
}
