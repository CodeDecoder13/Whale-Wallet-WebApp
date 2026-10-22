<?php

namespace Tests\Feature;

use App\Http\Controllers\DashboardController;
use App\Models\Card;
use App\Models\IncomeSource;
use App\Models\RecurringItem;
use App\Models\User;
use App\Services\BudgetSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HideBillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_a_hidden_bill_or_loan_stops_counting_until_it_is_shown_again()
    {
        $user = User::factory()->create();
        Card::factory()->for($user)->create(['balance' => 100_000]);
        IncomeSource::factory()->for($user)->create(['name' => 'Income', 'amount' => 3_000_000]);
        $rent = RecurringItem::factory()->for($user)->create(['name' => 'Rent', 'amount' => 800_000, 'cadence' => 'monthly', 'kind' => 'bill', 'next_due_on' => now()->addDays(3)->toDateString()]);
        $loan = RecurringItem::factory()->for($user)->create(['name' => 'Phone', 'amount' => 200_000, 'cadence' => 'monthly', 'kind' => 'debt', 'installments' => 6, 'next_due_on' => now()->addDays(4)->toDateString()]);
        $this->actingAs($user);

        $fixed = fn () => (new BudgetSummary)->for($user)['fixed'];
        $debts = fn () => DashboardController::netWorth()['debts'];
        $this->assertSame([1_000_000, 1_200_000], [$fixed(), $debts()]);

        $this->post(route('subscriptions.hide', $rent->id))->assertRedirect();
        $this->post(route('subscriptions.hide', $loan->id))->assertRedirect();
        $this->assertSame([0, 0], [$fixed(), $debts()]); // gone from the maths
        $this->get(route('dashboard'))->assertOk()->assertInertia(fn ($page) => $page->has('upcoming', 0)); // nothing due either

        // still on the Bills page, marked hidden, with what hiding takes off
        $this->get(route('subscriptions.index'))->assertInertia(fn ($page) => $page
            ->has('items', 2)
            ->where('items.0.hidden', true)
            ->where('items.0.monthly', 0)
            ->where('items.0.hidden_monthly', fn ($v) => $v > 0));

        $this->post(route('subscriptions.show', $rent->id))->assertRedirect();
        $this->post(route('subscriptions.show', $loan->id))->assertRedirect();
        $this->assertSame([1_000_000, 1_200_000], [$fixed(), $debts()]);
    }

    public function test_another_users_bill_cannot_be_hidden()
    {
        $other = RecurringItem::factory()->for(User::factory()->create())->create();

        $this->actingAs(User::factory()->create())->post(route('subscriptions.hide', $other->id))->assertNotFound();
    }
}
