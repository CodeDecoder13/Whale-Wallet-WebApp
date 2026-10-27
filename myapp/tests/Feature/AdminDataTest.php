<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Category;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\Receipt;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminDataTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['admin_role' => 'owner'])->save();
        $this->actingAs($this->admin)->withSession(['admin_gate' => [$this->admin->id, now()->timestamp]]);
    }

    /** user_id is guarded on these models, so tests set it by hand. */
    private function owned(string $model, User $owner, array $attrs)
    {
        $row = new $model($attrs);
        $row->user_id = $owner->id;
        $row->save();

        return $row;
    }

    public function test_transactions_of_every_type_and_user_show_with_card_and_category_and_filter()
    {
        [$a, $b] = User::factory(2)->create();
        $food = $this->owned(Category::class, $a, ['name' => 'Food']);
        $cardA = $this->owned(Card::class, $a, ['name' => 'GCash', 'type' => 'ewallet', 'balance' => 0]);
        $cardB = $this->owned(Card::class, $b, ['name' => 'Maribank', 'type' => 'bank', 'balance' => 0]);
        $this->owned(Transaction::class, $a, ['card_id' => $cardA->id, 'category_id' => $food->id, 'type' => 'expense', 'amount' => 15_000, 'merchant' => 'Jollibee', 'occurred_at' => now(), 'source' => 'chat']);
        $this->owned(Transaction::class, $b, ['card_id' => $cardB->id, 'type' => 'income', 'amount' => 3_000_000, 'merchant' => 'Salary', 'occurred_at' => now()->subDay(), 'source' => 'manual']);
        $this->owned(Transaction::class, $b, ['card_id' => $cardB->id, 'type' => 'transfer', 'amount' => 50_000, 'merchant' => 'To savings', 'occurred_at' => now()->subDays(2), 'source' => 'manual']);

        $this->get(route('admin.transactions'))->assertInertia(fn (Assert $p) => $p
            ->has('rows.data', 3)->where('rows.data.0.merchant', 'Jollibee')->where('rows.data.0.card', 'GCash')->where('rows.data.0.category', 'Food')
            ->where('totals.expense.total', 15_000)->where('totals.income.count', 1)->where('totals.transfer.total', 50_000));

        $count = fn (array $q) => Assert::fromTestResponse($this->get(route('admin.transactions', $q)))->toArray()['props']['rows']['total'];
        $this->assertSame(1, $count(['user' => $b->id, 'type' => 'income']));
        $this->assertSame(1, $count(['source' => 'chat']));
        $this->assertSame(1, $count(['category' => 'food']));
        $this->assertSame(1, $count(['min' => '1000']));
        $this->assertSame(1, $count(['q' => 'savings']));

        $this->assertDatabaseHas('admin_audit', ['admin_id' => $this->admin->id, 'action' => 'viewed_transactions']);
    }

    public function test_csv_export_is_formula_safe_audited_and_owner_only()
    {
        $a = User::factory()->create();
        $card = $this->owned(Card::class, $a, ['name' => 'GCash', 'type' => 'ewallet', 'balance' => 0]);
        $this->owned(Transaction::class, $a, ['card_id' => $card->id, 'type' => 'expense', 'amount' => 12_550, 'merchant' => '=HYPERLINK("x")', 'occurred_at' => now(), 'source' => 'manual']);

        $csv = $this->get(route('admin.transactions.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('date,user,email,type,merchant,amount', $csv);
        $this->assertStringContainsString('"\'=HYPERLINK(""x"")",125.50,GCash', $csv);
        $this->assertDatabaseHas('admin_audit', ['action' => 'exported_transactions']);

        $support = User::factory()->create();
        $support->forceFill(['admin_role' => 'support'])->save();
        $this->actingAs($support)->withSession(['admin_gate' => [$support->id, now()->timestamp]]);
        $this->get(route('admin.transactions'))->assertNotFound();
        $this->get(route('admin.receipts'))->assertNotFound();
        $this->get(route('admin.chats'))->assertNotFound();
    }

    public function test_receipts_list_show_and_the_image_only_through_the_gated_audited_route()
    {
        Storage::fake();
        $a = User::factory()->create();
        Storage::put('receipts/1/x.png', 'fake-image-bytes');
        $done = $this->owned(Receipt::class, $a, ['status' => 'parsed', 'path' => 'receipts/1/x.png', 'parsed_json' => ['merchant' => 'Mercury Drug', 'amount' => 25_000]]);
        $stuck = $this->owned(Receipt::class, $a, ['status' => 'parsing']);
        Receipt::withoutGlobalScopes()->whereKey($stuck->id)->update(['updated_at' => now()->subMinutes(5)]);
        $this->owned(Receipt::class, $a, ['status' => 'failed', 'error' => 'blurry']);
        $this->owned(Transaction::class, $a, ['card_id' => $this->owned(Card::class, $a, ['name' => 'Cash', 'type' => 'cash', 'balance' => 0])->id, 'type' => 'expense', 'amount' => 25_000, 'merchant' => 'Mercury Drug', 'occurred_at' => now(), 'receipt_id' => $done->id]);

        $this->get(route('admin.receipts'))->assertInertia(fn (Assert $p) => $p->has('receipts.data', 3));
        $this->get(route('admin.receipts', ['status' => 'failed']))->assertInertia(fn (Assert $p) => $p->has('receipts.data', 1)->where('receipts.data.0.error', 'blurry'));
        $this->get(route('admin.receipts', ['stuck' => 1]))->assertInertia(fn (Assert $p) => $p->has('receipts.data', 1)->where('receipts.data.0.id', $stuck->id));

        $this->get(route('admin.receipts.show', $done->id))->assertInertia(fn (Assert $p) => $p
            ->where('receipt.draft.merchant', 'Mercury Drug')->where('receipt.has_image', true)->has('transactions', 1));

        $this->get(route('admin.receipts.image', $done->id))->assertOk()->assertHeader('Cache-Control');
        $this->assertDatabaseHas('admin_audit', ['action' => 'opened_receipt_image', 'target_user_id' => $a->id]);
        $this->get(route('admin.receipts.image', $stuck->id))->assertNotFound(); // no image kept

        // a normal user can never reach it, and without the gate even an admin is sent to the gate
        $this->actingAs($a)->get(route('admin.receipts.image', $done->id))->assertNotFound();
        $this->actingAs($this->admin)->flushSession();
        $this->get(route('admin.receipts.image', $done->id))->assertRedirect(route('admin.gate'));
    }

    public function test_conversations_are_listed_searchable_read_only_and_deleted_threads_stay_hidden()
    {
        $a = User::factory()->create();
        $kept = $this->owned(ChatThread::class, $a, ['title' => 'Budget help']);
        $gone = $this->owned(ChatThread::class, $a, ['title' => 'Secret']);
        $this->owned(ChatMessage::class, $a, ['chat_thread_id' => $kept->id, 'role' => 'user', 'content' => 'Can I afford a new phone?']);
        $this->owned(ChatMessage::class, $a, ['chat_thread_id' => $kept->id, 'role' => 'assistant', 'content' => 'Yes, with a loan.', 'draft_json' => ['merchant' => 'Phone', 'amount' => 1_000_000]]);
        $this->owned(ChatMessage::class, $a, ['chat_thread_id' => $gone->id, 'role' => 'user', 'content' => 'phone secret']);
        $gone->delete(); // the user deleted it from their history

        $this->get(route('admin.chats'))->assertInertia(fn (Assert $p) => $p->has('threads.data', 1)->where('threads.data.0.title', 'Budget help')->where('threads.data.0.messages', 2));
        $this->get(route('admin.chats', ['q' => 'PHONE']))->assertInertia(fn (Assert $p) => $p->has('threads.data', 1));
        $this->get(route('admin.chats.show', $kept->id))->assertInertia(fn (Assert $p) => $p
            ->has('messages', 2)->where('messages.1.draft.merchant', 'Phone'));
        $this->get(route('admin.chats.show', $gone->id))->assertNotFound();
        $this->assertDatabaseHas('admin_audit', ['action' => 'viewed_chat', 'target_user_id' => $a->id]);

        $this->post('/admin/chats', ['content' => 'x'])->assertStatus(405); // no way to write into a user's chat
    }
}
