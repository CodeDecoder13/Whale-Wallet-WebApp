<?php

namespace Tests\Feature;

use App\Enums\RecurringKind;
use App\Models\Card;
use App\Models\Pod;
use App\Models\RecurringItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        Card::factory()->for($user)->create(); // users without cards are sent to onboarding
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_welcome_back_shows_once_per_login()
    {
        $user = User::factory()->create(['password' => bcrypt('P@ssw0rd12')]);
        Card::factory()->for($user)->create();

        $this->get(route('dashboard'))->assertRedirect(); // signed out
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'P@ssw0rd12']);

        $this->get(route('dashboard'))->assertInertia(fn ($page) => $page->where('welcome', true));
        $this->get(route('dashboard'))->assertInertia(fn ($page) => $page->where('welcome', false));
    }

    public function test_net_worth_counts_cards_and_pods_less_credit_owed_and_loans_left()
    {
        $this->travelTo('2026-03-10 09:00:00');
        $user = User::factory()->create(['pay_dates' => [15]]);
        $this->actingAs($user);
        Card::create(['name' => 'Bank', 'type' => 'bank', 'balance' => 100_000]);
        Card::create(['name' => 'Visa', 'type' => 'credit', 'balance' => -30_000]);
        Pod::create(['name' => 'Trip', 'target_amount' => 500_000, 'saved_amount' => 50_000]);
        // 2 payments left of 20,000 each
        RecurringItem::create(['name' => 'Phone', 'amount' => 20_000, 'cadence' => 'monthly', 'kind' => RecurringKind::Debt, 'installments' => 2, 'next_due_on' => '2026-03-20']);

        $this->get(route('dashboard'))->assertInertia(fn ($page) => $page->where('net_worth', ['assets' => 150_000, 'debts' => 70_000, 'total' => 80_000]));
    }
}
