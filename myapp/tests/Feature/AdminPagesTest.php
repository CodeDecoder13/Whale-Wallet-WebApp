<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AdminAudit;
use App\Models\Card;
use App\Models\Receipt;
use App\Models\RecurringItem;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->create(['name' => 'Boss']);
        $this->admin->forceFill(['admin_role' => 'owner'])->save();
        // as if the PIN + code gate was just passed
        $this->actingAs($this->admin)->withSession(['admin_gate' => [$this->admin->id, now()->timestamp]]);
    }

    private function loan(User $owner, array $extra = []): RecurringItem
    {
        return $this->itemFor($owner, [
            'name' => 'Phone', 'amount' => 100_000, 'cadence' => 'monthly', 'kind' => 'debt',
            'installments' => 12, 'total_amount' => 1_200_000, 'next_due_on' => today()->addDays(3), ...$extra,
        ]);
    }

    /** user_id is guarded on the models (it comes from the signed-in user), so tests set it by hand. */
    private function itemFor(User $owner, array $attrs): RecurringItem
    {
        $item = new RecurringItem($attrs);
        $item->user_id = $owner->id;
        $item->save();

        return $item;
    }

    public function test_overview_counts_everyone_not_just_the_admin()
    {
        [$a, $b] = User::factory(2)->create();
        $card = Card::factory()->for($a)->create();
        Card::factory()->for($b)->create();
        Transaction::factory()->for($a)->create(['card_id' => $card->id, 'occurred_at' => now()]);
        $failed = new Receipt(['status' => 'failed']);
        $failed->user_id = $b->id;
        $failed->save();
        $this->loan($a);
        DB::table('sessions')->insert(['id' => 's1', 'user_id' => $a->id, 'payload' => '', 'last_activity' => now()->timestamp]);

        $this->get(route('admin.home'))->assertInertia(fn (Assert $p) => $p
            ->where('users.total', 3)->where('users.today', 3)->where('users.active_day', 1)
            ->where('money.cards', 2)->where('money.transactions_month', 1)->where('money.loans', 1)
            ->where('ai.receipts_week', 1)->where('ai.receipts_failed', 1));
    }

    public function test_user_list_searches_filters_and_counts_each_users_rows_without_the_admin_scope()
    {
        $ana = User::factory()->create(['name' => 'Ana Cruz', 'email' => 'ana@example.com']);
        User::factory()->unverified()->create(['name' => 'Ben Reyes']);
        $this->loan($ana);
        Card::factory()->for($ana)->create();

        $names = fn (array $q) => collect(Assert::fromTestResponse($this->get(route('admin.users', $q)))->toArray()['props']['users']['data']);

        $this->assertCount(3, $names([]));
        $this->assertSame(['Ana Cruz'], $names(['q' => 'ANA@'])->pluck('name')->all());
        $this->assertSame(['Ben Reyes'], $names(['filter' => 'unverified'])->pluck('name')->all());
        $row = $names(['q' => 'ana'])->first();
        $this->assertSame([1, 1], [$row['cards'], $row['loans']]);
    }

    public function test_opening_a_user_shows_their_loans_and_is_written_to_the_audit_log()
    {
        $ana = User::factory()->create();
        $this->loan($ana, ['name' => 'Car']);

        $this->get(route('admin.users.show', $ana))->assertInertia(fn (Assert $p) => $p
            ->where('profile.id', $ana->id)->where('items.0.name', 'Car')->where('items.0.remaining', 1_200_000));

        $this->assertDatabaseHas('admin_audit', ['admin_id' => $this->admin->id, 'action' => 'viewed_user', 'target_user_id' => $ana->id]);
        $this->assertSame(1, AdminAudit::count());
    }

    public function test_loans_page_lists_all_users_loans_with_totals_and_a_normal_user_still_cannot_see_the_page()
    {
        [$a, $b] = User::factory(2)->create();
        $this->loan($a);
        $late = $this->loan($b, ['name' => 'Bike', 'next_due_on' => today()->subDays(2)]);
        $this->itemFor($a, ['name' => 'Netflix', 'amount' => 54_900, 'cadence' => 'monthly', 'kind' => 'subscription', 'next_due_on' => today()->addDay()]);

        $this->get(route('admin.loans'))->assertInertia(fn (Assert $p) => $p
            ->has('items.data', 2)->where('totals.loans', 2)->where('totals.owed', 2_400_000)->where('totals.overdue_loans', 1)
            ->where('totals.subscriptions', 1)->where('totals.subs_monthly', 54_900));
        $this->get(route('admin.loans', ['kind' => 'subscription']))->assertInertia(fn (Assert $p) => $p->has('items.data', 1)->where('popular.0.name', 'netflix'));
        $this->assertNotNull($late);

        $this->actingAs($a)->get(route('admin.loans'))->assertNotFound();
    }

    public function test_activity_feed_records_sign_ins_scans_and_added_loans_and_prunes_after_90_days()
    {
        $user = User::factory()->create();
        event(new \Illuminate\Auth\Events\Login('web', $user, false));
        $this->actingAs($user)->post(route('subscriptions.store'), ['name' => 'Phone', 'amount' => '1200', 'kind' => 'debt', 'installments' => '12', 'cadence' => 'monthly', 'next_due_on' => today()->toDateString()]);

        $this->assertDatabaseHas('activity_logs', ['event' => 'login', 'user_id' => $user->id]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'debt_added', 'user_id' => $user->id]);

        $this->actingAs($this->admin)->get(route('admin.activity'))->assertInertia(fn (Assert $p) => $p->has('events.data', 2)->where('names', ['debt_added', 'login']));
        $this->get(route('admin.activity', ['event' => 'login']))->assertInertia(fn (Assert $p) => $p->has('events.data', 1));

        ActivityLog::where('event', 'login')->update(['created_at' => now()->subDays(91)]);
        $this->artisan('schedule:test', ['--name' => 'activity:prune'])->assertSuccessful();
        $this->assertDatabaseMissing('activity_logs', ['event' => 'login']);
    }
}
