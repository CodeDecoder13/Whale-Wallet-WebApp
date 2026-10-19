<?php

namespace Tests\Feature;

use App\Actions\ManageByChat;
use App\Enums\RecurringKind;
use App\Models\Card;
use App\Models\RecurringItem;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ChatManageTest extends TestCase
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

    private function item(string $name, RecurringKind $kind, int $amount = 61_900): RecurringItem
    {
        return RecurringItem::create(['name' => $name, 'amount' => $amount, 'cadence' => 'monthly', 'kind' => $kind, 'next_due_on' => '2026-03-17']);
    }

    private function say(string $text): TestResponse
    {
        return $this->postJson(route('chat.messages'), ['text' => $text]);
    }

    private function confirm(TestResponse $r): TestResponse
    {
        return $this->postJson(route('chat.confirm', $r->json('id')));
    }

    public function test_cancel_a_subscription_with_a_reason_keeps_it_on_file()
    {
        $netflix = $this->item('Zoomflix', RecurringKind::Subscription);

        $r = $this->say('cancel Zoomflix because too expensive')->assertOk();
        $this->assertSame(['manage', 'cancel_subscription', 'Cancel subscription'], [$r->json('draft.kind'), $r->json('draft.action'), $r->json('draft.label')]);
        $this->assertNull($netflix->fresh()->cancelled_at, 'nothing changes before the user confirms');

        $this->confirm($r)->assertOk()->assertJsonPath('message', 'Zoomflix cancelled. You can renew it from Bills & Subs.');
        $this->assertSame('too expensive', $netflix->fresh()->cancel_reason);

        $r = $this->say('renew Zoomflix');
        $this->confirm($r)->assertOk();
        $this->assertNull($netflix->fresh()->cancelled_at);
    }

    public function test_edit_a_bill_amount_and_delete_it_after_confirming()
    {
        $rent = $this->item('Zoomlease', RecurringKind::Bill, 800_000);

        $r = $this->say('change Zoomlease to 8500');
        $this->assertContains('Amount ₱8,000.00 → ₱8,500.00', $r->json('draft.lines'));
        $this->confirm($r);
        $this->assertSame(850_000, $rent->fresh()->amount);

        $r = $this->say('delete Zoomlease');
        $this->assertTrue($r->json('draft.destructive'));
        $this->confirm($r);
        $this->assertNull(RecurringItem::find($rent->id));
    }

    public function test_add_a_subscription_needs_name_amount_and_date()
    {
        $this->say('add Zoomify subscription 149')->assertJsonPath('draft', null);

        $r = $this->say('add Zoomify subscription 149 on 2026-04-05');
        $this->confirm($r)->assertOk();
        $this->assertSame([14_900, RecurringKind::Subscription], [RecurringItem::where('name', 'Zoomify')->value('amount'), RecurringItem::where('name', 'Zoomify')->first()->kind]);
    }

    public function test_edit_and_delete_an_expense_keeps_card_balances_right()
    {
        $card = Card::first();
        $tx = Transaction::factory()->for($this->user)->create(['card_id' => $card->id, 'merchant' => 'Zorbee', 'amount' => 25_000, 'type' => 'expense', 'occurred_at' => '2026-03-09']);
        $card->update(['balance' => 100_000]);

        $this->confirm($this->say('change Zorbee to 300'));
        $this->assertSame([30_000, 95_000], [$tx->fresh()->amount, $card->fresh()->balance]);

        $this->confirm($this->say('delete Zorbee'));
        $this->assertNull(Transaction::find($tx->id));
        $this->assertSame(125_000, $card->fresh()->balance);
    }

    public function test_one_message_can_hold_several_requests()
    {
        $this->item('Zoomflix', RecurringKind::Subscription);
        $this->item('Zoomlease', RecurringKind::Bill);

        $r = $this->say('cancel Zoomflix and delete Zoomlease')->assertOk();

        $this->assertSame('cancel_subscription', $r->json('draft.action'));
        $this->assertSame('delete_recurring', $r->json('extra.0.draft.action'));
        $this->assertCount(1, $r->json('extra'));
        $this->assertNotSame($r->json('id'), $r->json('extra.0.id'));
    }

    public function test_chats_get_a_title_can_be_started_fresh_and_are_soft_deleted()
    {
        $first = $this->say('Zorbee 250')->json('thread_id');
        $new = $this->postJson(route('chat.threads.store'))->assertCreated()->json('id');
        $this->assertNotSame($first, $new);

        $this->get(route('chat.index', ['thread' => $first]))->assertInertia(fn ($page) => $page
            ->where('thread_id', $first)->has('threads', 2)->where('threads', fn ($t) => collect($t)->pluck('title')->contains('Zorbee 250')));

        $this->patchJson(route('chat.threads.update', $first), ['title' => 'Groceries'])->assertOk()->assertJsonPath('title', 'Groceries');
        $this->patchJson(route('chat.threads.update', $first), ['title' => ''])->assertJsonValidationErrors('title');

        $this->deleteJson(route('chat.threads.destroy', $first))->assertNoContent();
        $this->assertSoftDeleted('chat_threads', ['id' => $first]);
        $this->assertGreaterThan(0, \App\Models\ChatMessage::withoutGlobalScopes()->where('chat_thread_id', $first)->count(), 'messages stay');
        $this->get(route('chat.index'))->assertInertia(fn ($page) => $page->has('threads', 1));

        $theirs = User::factory()->create()->chatThreads()->create();
        $this->deleteJson(route('chat.threads.destroy', $theirs->id))->assertNotFound();
    }

    public function test_asking_what_is_due_shows_today_else_3_days_else_7_days()
    {
        RecurringItem::query()->delete();
        $at = fn (string $name, string $due, RecurringKind $kind = RecurringKind::Bill, array $o = []) => RecurringItem::create(['name' => $name, 'amount' => 100_000, 'cadence' => 'monthly', 'kind' => $kind, 'next_due_on' => $due] + $o);

        $this->say("what's my due today?")->assertJsonPath('draft', null)->assertJsonPath('reply', 'Nothing is due today, in the next 3 days or in the next 7 days. You are clear.');

        $far = $at('Zoomfar', '2026-03-16');
        $r = $this->say('whats my due today?');
        $this->assertSame(['dues', 'in the next 7 days'], [$r->json('draft.kind'), $r->json('draft.window')]);
        $this->assertStringContainsString('Nothing is due today', $r->json('reply'));

        $near = $at('Zoomnear', '2026-03-12', RecurringKind::Subscription);
        $this->assertSame('in the next 3 days', $this->say('what is due?')->json('draft.window'));

        $loan = $at('Zoomloan', '2026-03-10', RecurringKind::Debt, ['installments' => 3]);
        $r = $this->say('show my dues today');
        $this->assertSame(['today', ['Zoomloan']], [$r->json('draft.window'), collect($r->json('draft.items'))->pluck('name')->all()]);

        $this->postJson(route('chat.confirm', $r->json('id')))->assertStatus(422);

        // a paid-off loan is archived, never "due"
        $loan->update(['installments' => 0]);
        $this->assertSame('in the next 3 days', $this->say("what's my due today?")->json('draft.window'));
    }

    public function test_the_model_cannot_reach_another_users_rows()
    {
        $theirs = new RecurringItem(['name' => 'Secret', 'amount' => 100, 'cadence' => 'monthly', 'kind' => RecurringKind::Subscription, 'next_due_on' => '2026-03-17']);
        $theirs->user_id = User::factory()->create()->id;
        $theirs->save();

        $out = app(ManageByChat::class)->draft(['type' => 'delete_recurring', 'target_id' => $theirs->id]);

        $this->assertArrayNotHasKey('draft', $out);
        $this->assertNotNull(RecurringItem::withoutGlobalScopes()->find($theirs->id));
    }
}
