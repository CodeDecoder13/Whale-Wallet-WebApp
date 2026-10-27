<?php

namespace Tests\Feature;

use App\Enums\RecurringKind;
use App\Models\Card;
use App\Models\RecurringItem;
use App\Models\User;
use App\Services\BudgetSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo('2026-03-10 09:00:00');
    }

    private function item(array $attrs): RecurringItem
    {
        return RecurringItem::factory()->create($attrs);
    }

    /** Centavos per month, rounded half up. */
    public static function monthly(): array
    {
        return [
            'monthly' => ['monthly', 54_900, 54_900],
            'weekly' => ['weekly', 10_000, 43_333],
            'biweekly' => ['biweekly', 100_000, 216_667],
            'quarterly' => ['quarterly', 300_001, 100_000],
            'yearly' => ['yearly', 120_000, 10_000],
            'yearly half rounds up' => ['yearly', 6, 1],
        ];
    }

    #[DataProvider('monthly')]
    public function test_monthly_amount(string $cadence, int $amount, int $expected)
    {
        $this->assertSame($expected, $this->item(['cadence' => $cadence, 'amount' => $amount])->monthlyAmount());
    }

    /** Cadence, due date, expected date after rolling forward (today is 2026-03-10). */
    public static function rolls(): array
    {
        return [
            'monthly clamps to month end' => ['monthly', '2026-01-31', '2026-03-31'],
            'weekly' => ['weekly', '2026-03-01', '2026-03-15'],
            'biweekly' => ['biweekly', '2026-02-01', '2026-03-15'],
            'quarterly' => ['quarterly', '2025-09-05', '2026-06-05'],
            'yearly' => ['yearly', '2025-03-09', '2027-03-09'],
            'due today stays' => ['monthly', '2026-03-10', '2026-03-10'],
            'future stays' => ['monthly', '2026-04-02', '2026-04-02'],
        ];
    }

    #[DataProvider('rolls')]
    public function test_advance_rolls_forward_past_today(string $cadence, string $due, string $expected)
    {
        $item = $this->item(['cadence' => $cadence, 'next_due_on' => $due]);

        $item->advance();

        $this->assertSame($expected, $item->fresh()->next_due_on->toDateString());
    }

    public function test_scheduled_command_advances_overdue_items_of_every_user()
    {
        $overdue = $this->item(['next_due_on' => '2026-02-27']);
        $other = $this->item(['next_due_on' => '2026-03-01', 'cadence' => 'weekly']);
        $future = $this->item(['next_due_on' => '2026-03-27']);

        $this->artisan('recurring:advance')->assertSuccessful();

        $this->assertSame('2026-03-27', $overdue->fresh()->next_due_on->toDateString());
        $this->assertSame('2026-03-15', $other->fresh()->next_due_on->toDateString());
        $this->assertSame('2026-03-27', $future->fresh()->next_due_on->toDateString());
    }

    public function test_budget_summary_normalises_mixed_cadences()
    {
        $user = User::factory()->create();
        $user->incomeSources()->create(['name' => 'Pay', 'amount' => 3_000_000]);
        foreach ([['monthly', 800_000], ['weekly', 10_000], ['yearly', 120_000]] as [$cadence, $amount]) {
            $this->item(['user_id' => $user->id, 'cadence' => $cadence, 'amount' => $amount]);
        }

        // 800,000 + 43,333 + 10,000
        $this->assertSame(
            ['income' => 3_000_000, 'fixed' => 853_333, 'remaining' => 2_146_667],
            (new BudgetSummary)->for($user),
        );
    }

    public function test_index_lists_bills_subscriptions_and_loans_only()
    {
        $user = User::factory()->create();
        $mine = ['user_id' => $user->id, 'kind' => RecurringKind::Subscription];
        $this->item([...$mine, 'name' => 'Netflix', 'amount' => 54_900, 'next_due_on' => '2026-03-15']);
        $this->item([...$mine, 'name' => 'Domain', 'amount' => 120_000, 'cadence' => 'yearly', 'next_due_on' => '2026-09-01']);
        $this->item(['user_id' => $user->id, 'kind' => RecurringKind::Bill, 'name' => 'Rent', 'amount' => 800_000, 'next_due_on' => '2026-03-01']);
        $this->item(['user_id' => $user->id, 'kind' => RecurringKind::Debt, 'name' => 'Loan', 'next_due_on' => '2026-03-10']);
        $this->item(['user_id' => $user->id, 'kind' => RecurringKind::Installment, 'name' => 'Hidden']);
        $this->item([...$mine, 'user_id' => User::factory()->create()->id, 'name' => 'Theirs']);

        $this->actingAs($user)->get(route('subscriptions.index'))->assertInertia(fn (Assert $page) => $page
            ->component('subscriptions/Index')
            ->has('items', 4)
            ->where('items.0.name', 'Rent')
            ->where('items.0.kind', 'bill')
            ->where('items.3.monthly', 10_000));
    }

    public function test_create_update_delete_and_flip_kind()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post(route('subscriptions.store'), [
            'name' => 'Spotify', 'amount' => '149', 'cadence' => 'monthly', 'next_due_on' => '2026-03-20', 'kind' => 'subscription',
        ])->assertRedirect();

        $sub = RecurringItem::sole();
        $this->assertSame([14_900, RecurringKind::Subscription], [$sub->amount, $sub->kind]);

        $this->put(route('subscriptions.update', $sub->id), [
            'name' => 'Spotify Duo', 'amount' => '199', 'cadence' => 'yearly', 'next_due_on' => '2026-12-01', 'kind' => 'bill',
        ])->assertRedirect();
        $this->assertSame([19_900, 'yearly', RecurringKind::Bill], [$sub->fresh()->amount, $sub->fresh()->cadence, $sub->fresh()->kind]);

        $this->delete(route('subscriptions.destroy', $sub->id))->assertRedirect();
        $this->assertSame(0, RecurringItem::count());
    }

    public function test_loan_needs_a_payment_count_and_stops_after_it()
    {
        $this->actingAs(User::factory()->create());
        $loan = ['name' => 'Phone', 'amount' => '1000', 'cadence' => 'weekly', 'next_due_on' => '2026-03-10', 'kind' => 'debt'];

        $this->post(route('subscriptions.store'), $loan)->assertSessionHasErrors('installments');
        $this->post(route('subscriptions.store'), [...$loan, 'installments' => 361])->assertSessionHasErrors('installments');
        $this->post(route('subscriptions.store'), [...$loan, 'installments' => 3])->assertRedirect();

        $item = RecurringItem::sole();
        $this->assertSame(['monthly', 3], [$item->cadence, $item->installments]);
        $this->assertCount(3, $item->occurrences(Date::parse('2026-03-01'), Date::parse('2027-03-01')));

        $this->put(route('subscriptions.update', $item->id), [...$loan, 'kind' => 'bill', 'installments' => 3])->assertRedirect();
        $this->assertNull($item->fresh()->installments);
    }

    public function test_logo_must_be_a_small_image_data_uri()
    {
        $this->actingAs(User::factory()->create());
        $bill = ['name' => 'Gym', 'amount' => '500', 'cadence' => 'monthly', 'next_due_on' => '2026-03-10', 'kind' => 'bill'];

        $this->post(route('subscriptions.store'), [...$bill, 'logo' => 'https://evil.test/x.png'])->assertSessionHasErrors('logo');
        $this->post(route('subscriptions.store'), [...$bill, 'logo' => 'data:image/png;base64,AAAA'])->assertRedirect();
        $this->assertSame('data:image/png;base64,AAAA', RecurringItem::sole()->logo);
    }

    public function test_cannot_touch_another_users_item_or_an_installment_plan()
    {
        [$a, $b] = User::factory(2)->create();
        $theirs = $this->item(['user_id' => $b->id, 'kind' => RecurringKind::Subscription]);
        $debt = $this->item(['user_id' => $a->id, 'kind' => RecurringKind::Installment]);
        $this->actingAs($a);

        $this->delete(route('subscriptions.destroy', $theirs->id))->assertNotFound();
        $this->delete(route('subscriptions.destroy', $debt->id))->assertNotFound();
        $this->assertSame(2, RecurringItem::withoutGlobalScopes()->count());
    }

    public function test_forecast_page_skips_a_subscription()
    {
        $user = User::factory()->create(['pay_dates' => [15]]);
        Card::factory()->for($user)->create(['type' => 'bank', 'balance' => 1_000_000]);
        $sub = $this->item(['user_id' => $user->id, 'kind' => RecurringKind::Subscription, 'amount' => 54_900, 'next_due_on' => '2026-03-12']);
        $this->actingAs($user);

        $this->get(route('forecast'))->assertInertia(fn (Assert $page) => $page
            ->where('forecast.at_payday', 1_000_000 - 54_900)->where('forecast.days', 5));
        $this->get(route('forecast', ['skip' => $sub->id]))->assertInertia(fn (Assert $page) => $page
            ->where('forecast.at_payday', 1_000_000)->where('skipped', $sub->id));
    }
}
