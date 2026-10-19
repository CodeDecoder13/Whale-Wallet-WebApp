<?php

namespace Tests\Feature;

use App\Enums\RecurringKind;
use App\Models\Card;
use App\Models\RecurringItem;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayBillTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo('2026-03-10 09:00:00');
        $this->seed(DatabaseSeeder::class);
        $this->user = User::where('email', 'demo@example.com')->first();
        $this->user->forceFill(['ai_consent_at' => now()])->save();
        $this->actingAs($this->user);
    }

    private function item(array $o): RecurringItem
    {
        return RecurringItem::create(['name' => 'Converge', 'amount' => 160_000, 'cadence' => 'monthly', 'kind' => RecurringKind::Bill, 'next_due_on' => '2026-03-21', ...$o]);
    }

    public function test_paying_a_bill_logs_an_expense_and_moves_the_due_date()
    {
        $bill = $this->item([]);
        $card = Card::first();
        $before = $card->balance;

        $this->post(route('subscriptions.pay', $bill->id), ['card_id' => $card->id, 'amount' => '1600', 'paid_on' => '2026-03-10'])->assertRedirect();

        $tx = Transaction::where('recurring_item_id', $bill->id)->sole();
        $this->assertSame([160_000, 'Converge', $before - 160_000], [$tx->amount, $tx->merchant, $card->fresh()->balance]);
        $this->assertSame('2026-04-21', $bill->fresh()->next_due_on->toDateString());

        $this->get(route('subscriptions.index'))->assertInertia(fn ($page) => $page->where('items', fn ($items) => collect($items)->firstWhere('id', $bill->id)['last_paid'] === '2026-03-10'));
    }

    public function test_paying_with_a_scanned_receipt_keeps_its_reference_and_closes_the_receipt()
    {
        $bill = $this->item([]);
        $receipt = $this->user->receipts()->create(['purpose' => 'expense', 'status' => 'parsed', 'parsed_json' => []]);

        $this->post(route('subscriptions.pay', $bill->id), ['card_id' => Card::first()->id, 'amount' => '1600', 'paid_on' => '2026-03-10', 'reference' => 'GC-123', 'receipt_id' => $receipt->id])->assertRedirect();

        $tx = Transaction::where('recurring_item_id', $bill->id)->sole();
        $this->assertSame(['GC-123', $receipt->id, 'confirmed'], [$tx->reference, $tx->receipt_id, $receipt->fresh()->status]);

        // the same receipt cannot pay twice
        $this->post(route('subscriptions.pay', $bill->id), ['card_id' => Card::first()->id, 'amount' => '1600', 'paid_on' => '2026-03-10', 'receipt_id' => $receipt->id])->assertStatus(422);
    }

    public function test_a_cancelled_subscription_stays_on_file_stops_counting_and_can_be_renewed()
    {
        $sub = $this->item(['kind' => RecurringKind::Subscription]);
        $this->post(route('subscriptions.cancel', $sub->id), [])->assertSessionHasErrors('reason');
        $this->post(route('subscriptions.cancel', $sub->id), ['reason' => 'Too expensive'])->assertRedirect();

        $this->assertSame('Too expensive', $sub->fresh()->cancel_reason);
        $this->assertSame(0, RecurringItem::active()->whereKey($sub->id)->count());
        $this->post(route('subscriptions.pay', $sub->id), ['card_id' => Card::first()->id, 'amount' => '1600', 'paid_on' => '2026-03-10'])->assertStatus(422);

        $this->post(route('subscriptions.renew', $sub->id))->assertRedirect();
        $this->assertNull($sub->fresh()->cancelled_at);

        // a bill cannot be cancelled, and deleting keeps past payments
        $bill = $this->item([]);
        $this->post(route('subscriptions.cancel', $bill->id), ['reason' => 'x'])->assertStatus(422);
        $this->post(route('subscriptions.pay', $bill->id), ['card_id' => Card::first()->id, 'amount' => '1600', 'paid_on' => '2026-03-10']);
        $this->delete(route('subscriptions.destroy', $bill->id));
        $this->assertSame(1, Transaction::where('merchant', 'Converge')->whereNull('recurring_item_id')->count());
    }

    public function test_cannot_pay_from_someone_elses_card_or_in_the_future()
    {
        $bill = $this->item([]);
        $theirs = Card::factory()->for(User::factory()->create())->create();

        $this->post(route('subscriptions.pay', $bill->id), ['card_id' => $theirs->id, 'amount' => '1', 'paid_on' => '2026-03-10'])->assertSessionHasErrors('card_id');
        $this->post(route('subscriptions.pay', $bill->id), ['card_id' => Card::first()->id, 'amount' => '1', 'paid_on' => '2026-04-01'])->assertSessionHasErrors('paid_on');
        $this->assertSame(0, Transaction::where('recurring_item_id', $bill->id)->count());
    }

    public function test_a_loan_is_paid_off_after_its_last_payment()
    {
        $loan = $this->item(['kind' => RecurringKind::Debt, 'name' => 'Phone', 'installments' => 2]);
        $pay = fn () => $this->post(route('subscriptions.pay', $loan->id), ['card_id' => Card::first()->id, 'amount' => '1600', 'paid_on' => '2026-03-10']);

        $pay()->assertRedirect();
        $this->assertSame([1, '2026-04-21'], [$loan->fresh()->installments, $loan->fresh()->next_due_on->toDateString()]);

        $pay()->assertRedirect();
        $this->assertSame(0, $loan->fresh()->installments);
        $this->get(route('subscriptions.index'))->assertInertia(fn ($page) => $page->where('items', fn ($items) => collect($items)->firstWhere('id', $loan->id) === [...collect($items)->firstWhere('id', $loan->id), 'left' => 0, 'monthly' => 0]));

        $pay()->assertStatus(422);
        $this->assertSame(2, Transaction::where('recurring_item_id', $loan->id)->count());
    }

    public function test_whale_chat_marks_a_bill_as_paid_only_on_confirm()
    {
        $bill = $this->item(['name' => 'Converge - Wifi']);
        $card = Card::first();

        $r = $this->postJson(route('chat.messages'), ['text' => "paid converge from {$card->name}"])->assertOk();
        $this->assertSame([$bill->id, 160_000, []], [$r->json('draft.recurring_id'), $r->json('draft.amount'), $r->json('draft.missing')]);
        $this->assertSame(0, Transaction::where('recurring_item_id', $bill->id)->count());

        $this->postJson(route('chat.confirm', $r->json('id')))->assertOk();
        $this->assertSame(1, Transaction::where('recurring_item_id', $bill->id)->count());
        $this->assertSame('2026-04-21', $bill->fresh()->next_due_on->toDateString());
        $this->postJson(route('chat.confirm', $r->json('id')))->assertStatus(422);
    }
}
