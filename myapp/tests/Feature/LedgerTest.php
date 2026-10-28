<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Http\Controllers\DashboardController;
use App\Models\Card;
use App\Models\Pod;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LedgerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite(); // pages render without a built manifest
    }

    private function expense(User $user, Card $card, array $extra = [])
    {
        return $this->actingAs($user)->post(route('transactions.store'), [
            'merchant' => 'Jollibee',
            'amount' => '250.50', // pesos in, centavos stored
            'card_id' => $card->id,
            'occurred_at' => now()->toDateString(),
            'source' => 'manual',
            ...$extra,
        ]);
    }

    public function test_expense_is_charged_to_its_card()
    {
        $user = User::factory()->create();
        $card = Card::factory()->for($user)->create(['balance' => 100_000]);

        $this->expense($user, $card)->assertRedirect(route('transactions.index'));

        $this->assertSame(74_950, $card->fresh()->balance);
        $this->assertSame(25_050, Transaction::withoutGlobalScopes()->sole()->amount);
    }

    public function test_expense_cannot_use_another_users_card()
    {
        [$a, $b] = User::factory(2)->create();
        $theirs = Card::factory()->for($b)->create();

        $this->expense($a, $theirs)->assertSessionHasErrors('card_id');
    }

    public function test_transfer_moves_money_and_is_not_spending()
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'demo@example.com')->first();
        $card = $user->cards()->first(); // 5,000 pesos
        $pod = Pod::factory()->for($user)->create(['saved_amount' => 0]);

        $this->actingAs($user)->post(route('transactions.transfer'), [
            'from' => "card:{$card->id}", 'to' => "pod:{$pod->id}", 'amount' => '1000',
        ])->assertRedirect();

        $this->assertSame(400_000, $card->fresh()->balance);
        $this->assertSame(100_000, $pod->fresh()->saved_amount);
        $this->assertSame(TransactionType::Transfer, Transaction::withoutGlobalScopes()->sole()->type);

        $this->get(route('dashboard'))->assertInertia(fn (Assert $p) => $p
            ->where('summary.spent', 0)->where('summary.safe', 3_500_000));
    }

    public function test_transfer_rejects_overdraw()
    {
        $user = User::factory()->create();
        $card = Card::factory()->for($user)->create(['balance' => 1_000]);
        $pod = Pod::factory()->for($user)->create();

        $this->actingAs($user)->post(route('transactions.transfer'), [
            'from' => "card:{$card->id}", 'to' => "pod:{$pod->id}", 'amount' => '50',
        ])->assertSessionHasErrors('amount');
        $this->assertSame(1_000, $card->fresh()->balance);
    }

    public function test_ledger_filters()
    {
        $user = User::factory()->create();
        $gcash = Card::factory()->for($user)->create();
        $cash = Card::factory()->for($user)->create();
        Transaction::factory()->for($user)->create(['card_id' => $gcash->id, 'merchant' => 'Jollibee', 'occurred_at' => '2026-03-10']);
        Transaction::factory()->for($user)->create(['card_id' => $cash->id, 'merchant' => 'Grab', 'occurred_at' => '2026-04-10']);

        $merchants = fn (array $q) => Assert::fromTestResponse(
            $this->actingAs($user)->get(route('transactions.index', $q))
        )->toArray()['props']['transactions']['data'];

        $this->assertCount(2, $merchants([]));
        $this->assertSame('Jollibee', $merchants(['q' => 'jolli'])[0]['merchant']);
        $this->assertSame('Grab', $merchants(['from' => '2026-04-01'])[0]['merchant']);
        $this->assertCount(1, $merchants(['to' => '2026-03-31']));
        $this->assertSame('Grab', $merchants(['card_id' => $cash->id])[0]['merchant']);
    }

    public function test_quick_entry_returns_a_draft_and_saves_nothing()
    {
        $user = User::factory()->create();
        $card = Card::factory()->for($user)->create(['name' => 'GCash']);

        $this->actingAs($user)->post(route('transactions.quick'), ['text' => 'coffee 95 gcash'])
            ->assertRedirect(route('transactions.index'))
            ->assertSessionHas('draft', ['merchant' => 'coffee', 'amount' => 9500, 'card_id' => $card->id]);
        $this->actingAs($user)->post(route('transactions.quick'), ['text' => 'nonsense'])
            ->assertSessionHasErrors('text');
        $this->assertSame(0, Transaction::withoutGlobalScopes()->count());
    }

    public function test_csv_export()
    {
        $user = User::factory()->create();
        $card = Card::factory()->for($user)->create(['name' => 'GCash']);
        Transaction::factory()->for($user)->create(['card_id' => $card->id, 'merchant' => '=HYPERLINK("x")', 'amount' => 125_050]);
        Transaction::factory()->create(); // another user's row

        $csv = $this->actingAs($user)->get(route('transactions.export'))->assertOk()->streamedContent();

        $this->assertStringContainsString('date,type,merchant,amount,card,category,note,source', $csv);
        $this->assertStringContainsString('"\'=HYPERLINK(""x"")",1250.50,GCash', $csv);
        $this->assertSame(2, substr_count(trim($csv), "\n") + 1);
    }

    public function test_another_users_card_and_pod_are_404()
    {
        [$a, $b] = User::factory(2)->create();
        $card = Card::factory()->for($b)->create();
        $pod = Pod::factory()->for($b)->create();

        $this->actingAs($a);
        $this->delete(route('cards.destroy', $card))->assertNotFound();
        $this->put(route('pods.update', $pod), ['name' => 'x', 'target_amount' => 1, 'saved_amount' => 0, 'monthly_amount' => 0])->assertNotFound();
        $this->assertModelExists($card);
    }

    public function test_dashboard_for_demo_user()
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'demo@example.com')->first();

        $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $p) => $p
            ->where('summary.income', 3_500_000)
            ->where('summary.fixed', 1_915_600)->where('summary.paid_fixed', 0)
            ->where('summary.safe', 3_500_000)
            ->has('categories', 0));
    }

    public function test_dashboard_counts_only_expenses_and_lists_upcoming_bills()
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'demo@example.com')->first();
        $card = $user->cards()->first();
        $user->recurringItems()->update(['next_due_on' => today()->addDays(30)]);
        $user->recurringItems()->first()->update(['next_due_on' => today()->addDays(14)]);
        Transaction::factory()->for($user)->create(['card_id' => $card->id, 'amount' => 100_000, 'occurred_at' => now()]);
        Transaction::factory()->for($user)->create(['card_id' => $card->id, 'amount' => 900_000, 'type' => TransactionType::Income, 'occurred_at' => now()]);

        $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $p) => $p
            ->where('summary.spent', 100_000)
            ->where('summary.safe', 3_400_000)
            ->has('categories', 1)
            ->has('upcoming', 1));
    }

    public function test_safe_to_spend_is_income_left_minus_unpaid_bills_spread_to_payday()
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'demo@example.com')->first();
        $card = $user->cards()->first();
        $bill = $user->recurringItems()->first();
        Transaction::factory()->for($user)->create(['card_id' => $card->id, 'amount' => 100_000, 'occurred_at' => now(), 'recurring_item_id' => $bill->id]);

        // income 3,500,000 - spent 100,000 - unpaid bills (1,915,600 - 100,000 paid)
        $forecast = $this->actingAs($user)->get(route('dashboard'))->viewData('page')['props']['forecast'];
        $this->assertSame(intdiv(1_584_400, $forecast['days']), $forecast['safe_per_day']);
    }

    public function test_forecast_skip_list_only_offers_bills_not_yet_paid()
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'demo@example.com')->first();
        $bill = $user->recurringItems()->whereIn('kind', ['bill', 'subscription'])->first();
        $this->actingAs($user)->get(route('forecast'))->assertInertia(fn (Assert $p) => $p
            ->where('subscriptions', fn ($l) => collect($l)->contains('id', $bill->id)));

        Transaction::factory()->for($user)->create(['card_id' => $user->cards()->first()->id, 'occurred_at' => now(), 'recurring_item_id' => $bill->id]);

        $this->get(route('forecast'))->assertInertia(fn (Assert $p) => $p
            ->where('subscriptions', fn ($l) => ! collect($l)->contains('id', $bill->id)));
    }

    public function test_a_paid_bill_counts_once_and_an_unpaid_one_not_at_all()
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'demo@example.com')->first();
        $bill = $user->recurringItems()->first();
        $card = $user->cards()->first();
        Transaction::factory()->for($user)->create(['card_id' => $card->id, 'amount' => 100_000, 'occurred_at' => now(), 'recurring_item_id' => $bill->id]);

        $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $p) => $p
            ->where('summary.paid_fixed', 100_000)->where('summary.spent', 100_000)->where('summary.safe', 3_400_000));
    }

    public function test_a_loan_is_entered_as_a_total_and_net_worth_owes_what_is_left_not_total_x_months()
    {
        $user = User::factory()->create();
        $card = Card::factory()->for($user)->create(['type' => 'bank', 'balance' => 5_000_000]);
        $this->actingAs($user)->post(route('subscriptions.store'), [
            'name' => 'Car', 'amount' => '12000', 'kind' => 'debt', 'installments' => '18', 'cadence' => 'monthly', 'next_due_on' => today()->toDateString(),
        ])->assertSessionHasNoErrors();

        $loan = $user->recurringItems()->sole();
        $this->assertSame([1_200_000, 66_667, 18], [$loan->total_amount, $loan->amount, $loan->installments]);
        $this->assertSame(1_200_000, DashboardController::netWorth()['debts']);

        $this->post(route('subscriptions.pay', $loan->id), ['card_id' => $card->id, 'amount' => '666.67', 'paid_on' => today()->toDateString()])->assertSessionHasNoErrors();
        $this->assertSame(1_133_333, DashboardController::netWorth()['debts']);

        $this->post(route('subscriptions.store'), ['name' => 'Car2', 'amount' => '100', 'kind' => 'debt', 'installments' => '361', 'next_due_on' => today()->toDateString()])
            ->assertSessionHasErrors('installments');
    }

    public function test_new_user_without_cards_is_sent_to_onboarding_and_can_finish_it()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('onboarding.show'));
        $this->post(route('onboarding.store'), [
            'income' => '35000',
            'pay_dates' => [15, 30],
            'bills' => [['name' => 'Rent', 'amount' => '8000', 'day' => 1]],
            'card' => ['name' => 'GCash', 'type' => 'ewallet', 'balance' => '5000'],
            'pod' => ['name' => '', 'target_amount' => ''],
        ])->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))->assertInertia(fn (Assert $p) => $p
            ->where('summary.income', 3_500_000)->where('summary.fixed', 800_000));
        $this->assertSame(500_000, $user->cards()->sole()->balance);
        $this->assertSame(0, $user->pods()->count());
    }

    public function test_not_paid_yet_this_month_starts_income_next_month()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('onboarding.store'), [
            'income' => '35000',
            'paid_this_month' => false,
            'pay_dates' => [22],
            'bills' => [['name' => 'Rent', 'amount' => '8000', 'day' => 1]],
            'card' => ['name' => 'GCash', 'type' => 'ewallet', 'balance' => '0'],
        ])->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))->assertInertia(fn (Assert $p) => $p
            ->where('summary.income', 0)->where('summary.next_income', 3_500_000)->where('summary.safe', 0));

        $this->travelTo(now()->addMonth()->startOfMonth()->addDay());
        $this->get(route('dashboard'))->assertInertia(fn (Assert $p) => $p
            ->where('summary.income', 3_500_000)->where('summary.next_income', null));
    }
}
