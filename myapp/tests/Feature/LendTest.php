<?php

namespace Tests\Feature;

use App\Http\Controllers\DashboardController;
use App\Mail\LendNotice;
use App\Models\Card;
use App\Models\Lend;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LendTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Card $card;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo('2026-03-10 09:00:00');
        Mail::fake();
        $this->user = User::factory()->create(['name' => 'Rhuz', 'email' => 'rhuz@example.com']);
        $this->card = Card::factory()->for($this->user)->create(['type' => 'ewallet', 'balance' => 1_000_000]);
        $this->actingAs($this->user);
    }

    private function body(array $over = []): array
    {
        return $over + [
            'name' => 'Ana', 'email' => 'ana@example.com', 'amount' => '2500', 'card_id' => $this->card->id,
            'paid_on' => '2026-03-10', 'due_on' => '2026-03-20', 'remind_before' => 3, 'reference' => 'GC-77',
        ];
    }

    public function test_lending_takes_cash_off_the_card_keeps_a_transfer_and_emails_the_borrower()
    {
        $this->post(route('lends.store'), $this->body())->assertSessionHasNoErrors();

        $lend = Lend::sole();
        $this->assertSame(250_000, $lend->amount);
        $this->assertSame(750_000, $this->card->fresh()->balance);
        $this->assertSame('transfer', Transaction::sole()->type->value); // never counts as spending
        Mail::assertSent(LendNotice::class, fn ($m) => $m->hasTo('ana@example.com') && $m->hasReplyTo('rhuz@example.com') && $m->kind === 'new');
    }

    public function test_cannot_lend_more_than_the_card_holds_or_to_a_bad_email()
    {
        $this->post(route('lends.store'), $this->body(['amount' => '99999']))->assertSessionHasErrors('amount');
        $this->post(route('lends.store'), $this->body(['email' => 'nope']))->assertSessionHasErrors('email');
        $this->post(route('lends.store'), $this->body(['due_on' => '2026-03-01']))->assertSessionHasErrors('due_on');

        $this->assertSame(0, Lend::count());
        $this->assertSame(1_000_000, $this->card->fresh()->balance);
    }

    public function test_owed_cash_counts_as_an_asset_until_paid_back_once()
    {
        $this->post(route('lends.store'), $this->body());
        $before = DashboardController::netWorth();
        $this->assertSame(1_000_000, $before['assets']); // 750,000 on the card + 250,000 owed to you

        $lend = Lend::sole();
        $this->post(route('lends.paid', $lend), ['card_id' => $this->card->id]);
        $this->post(route('lends.paid', $lend), ['card_id' => $this->card->id]); // a double tap

        $this->assertSame(1_000_000, $this->card->fresh()->balance);
        $this->assertSame(1_000_000, DashboardController::netWorth()['assets']);
        $this->assertNotNull($lend->fresh()->paid_at);
    }

    public function test_reminders_go_on_the_agreed_day_the_due_date_and_weekly_when_overdue_but_never_twice_a_day()
    {
        $this->post(route('lends.store'), $this->body()); // due 20 Mar, remind 3 days before
        $lend = Lend::sole();
        $day = function (string $d) use ($lend) {
            $this->travelTo($d);

            return $lend->fresh()->reminderToday();
        };

        $this->assertNull($day('2026-03-15'));
        $this->assertSame('soon', $day('2026-03-17'));
        $this->assertSame('today', $day('2026-03-20'));
        $this->assertNull($day('2026-03-23'));
        $this->assertSame('overdue', $day('2026-03-27'));

        $this->artisan('lends:remind')->assertSuccessful();
        Mail::assertSent(LendNotice::class, fn ($m) => $m->kind === 'overdue');
        $this->artisan('lends:remind'); // same day again
        Mail::assertSent(LendNotice::class, 1 + 1); // the first email plus the one overdue reminder

        $lend->fresh()->update(['paid_at' => now()]);
        $this->assertNull($lend->fresh()->reminderToday());
    }

    public function test_other_users_cannot_see_or_change_your_lends()
    {
        $this->post(route('lends.store'), $this->body());
        $lend = Lend::sole();

        $this->actingAs(User::factory()->create());
        $this->delete(route('lends.destroy', $lend))->assertNotFound();
        $this->get(route('lends.index'))->assertOk();
        $this->assertSame(1, Lend::withoutGlobalScopes()->count());
    }
}
