<?php

namespace Tests\Feature;

use App\Contracts\AiProvider;
use App\Jobs\ParseReceipt;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Ai\FakeAiProvider;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo('2026-03-10 09:00:00'); // a Tuesday: this week started Monday the 9th
        Storage::fake('local');
    }

    private function demo(): User
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'demo@example.com')->first();
        $user->forceFill(['ai_consent_at' => now()])->save();

        return $user;
    }

    private function say(string $text): TestResponse
    {
        return $this->postJson(route('chat.messages'), ['text' => $text]);
    }

    private function spend(User $user, string $category, int $centavos, string $when): void
    {
        Transaction::factory()->for($user)->create([
            'card_id' => $user->cards()->first()->id, 'category_id' => $user->categories()->where('name', $category)->first()->id,
            'amount' => $centavos, 'occurred_at' => $when,
        ]);
    }

    public function test_quick_entry_needs_no_ai_and_saves_only_on_confirm()
    {
        $user = $this->demo();
        $this->app->instance(AiProvider::class, new class extends FakeAiProvider
        {
            public function chat(string $text, array $context): array
            {
                throw new RuntimeException('the AI must not be called for quick entries');
            }
        });

        $r = $this->actingAs($user)->say('Jollibee 250')->assertOk()
            ->assertJsonPath('draft.merchant', 'Jollibee')->assertJsonPath('draft.amount', 25000)->assertJsonPath('draft.card', 'GCash')->assertJsonPath('draft.missing', []);
        $this->assertSame(0, Transaction::count());

        $this->postJson(route('chat.confirm', $r->json('id')))->assertOk();

        $t = Transaction::first();
        $this->assertSame([25000, 'chat', 'Jollibee'], [$t->amount, $t->source->value, $t->merchant]);
        $this->assertSame(500_000 - 25000, $user->cards()->first()->balance);
        $this->postJson(route('chat.confirm', $r->json('id')))->assertStatus(422); // not twice
        $this->assertSame(1, Transaction::count());
    }

    public function test_a_sentence_becomes_a_draft_through_the_provider()
    {
        $user = $this->demo();

        $r = $this->actingAs($user)->say('I spent 1,250.50 at Mercury Drug')->assertOk()
            ->assertJsonPath('draft.merchant', 'Mercury Drug')->assertJsonPath('draft.amount', 125050);

        $this->assertSame(0, Transaction::count());
        $this->postJson(route('chat.confirm', $r->json('id')))->assertOk();
        $this->assertSame(125050, Transaction::first()->amount);
    }

    public function test_a_missing_field_gets_exactly_one_short_follow_up_and_the_answer_completes_it()
    {
        $user = $this->demo();
        $user->cards()->create(['name' => 'Maya', 'type' => 'ewallet', 'balance' => 100_000]);

        $r = $this->actingAs($user)->say('Jollibee 250')->assertOk()
            ->assertJsonPath('reply', 'Which card should I charge?')->assertJsonPath('draft.missing', ['card']);
        $this->assertEqualsCanonicalizing(['GCash', 'Maya'], $r->json('quick_replies'));
        $this->postJson(route('chat.confirm', $r->json('id')))->assertStatus(422); // incomplete drafts cannot be saved

        $done = $this->say('Maya')->assertOk()->assertJsonPath('draft.merchant', 'Jollibee')->assertJsonPath('draft.amount', 25000)
            ->assertJsonPath('draft.card', 'Maya')->assertJsonPath('draft.missing', []);
        $this->assertStringContainsString('₱250.00', $done->json('reply'));

        $this->postJson(route('chat.confirm', $done->json('id')))->assertOk();
        $this->assertSame('Maya', Transaction::first()->card->name);
    }

    public function test_how_much_food_this_week_comes_from_the_database()
    {
        $user = $this->demo();
        $this->spend($user, 'Food', 12_345, '2026-03-09 12:00:00');
        $this->spend($user, 'Food', 5_000, '2026-03-10 08:00:00');
        $this->spend($user, 'Food', 99_900, '2026-03-02 12:00:00'); // last week
        $this->spend($user, 'Transport', 7_700, '2026-03-09 12:00:00'); // other category

        $expected = Transaction::withoutGlobalScopes()->where('category_id', $user->categories()->where('name', 'Food')->value('id'))
            ->where('occurred_at', '>=', '2026-03-09')->sum('amount');
        $this->assertSame(17_345, (int) $expected);

        $reply = $this->actingAs($user)->say('how much food this week?')->assertOk()->json('reply');

        $this->assertSame('You spent ₱'.number_format($expected / 100, 2).' on Food this week.', $reply);
        $this->assertSame(0, ChatMessage::where('draft_json', '!=', null)->count());
    }

    public function test_other_questions_use_fixed_server_functions()
    {
        $user = $this->demo();
        $this->spend($user, 'Food', 10_000, '2026-03-09 12:00:00');

        $this->actingAs($user);
        $this->say('how much did I spend today?')->assertJsonPath('reply', 'You spent ₱0.00 today.');
        $this->say('how much did I spend this month?')->assertJsonPath('reply', 'You spent ₱100.00 this month.');
        $this->say('what are my subscriptions?')->assertJsonPath('reply', 'Your subscriptions cost ₱2,156.00 a month.');
        $this->say('how much is safe to spend?')->assertJsonPath('reply', fn ($r) => str_starts_with($r, 'You can spend about ₱'));
    }

    public function test_a_reply_with_an_invented_number_is_replaced_by_the_server_sentence()
    {
        $user = $this->demo();
        $this->spend($user, 'Food', 12_345, '2026-03-09 12:00:00');
        $this->app->instance(AiProvider::class, new class extends FakeAiProvider
        {
            public function phrase(string $question, array $facts): string
            {
                return 'You spent ₱9,999.00 on Food this week, 40% of your income.';
            }
        });

        $this->actingAs($user)->say('how much food this week?')->assertJsonPath('reply', 'You spent ₱123.45 on Food this week.');
    }

    public function test_a_reply_that_only_uses_the_given_numbers_is_kept()
    {
        $user = $this->demo();
        $this->spend($user, 'Food', 12_345, '2026-03-09 12:00:00');
        $this->app->instance(AiProvider::class, new class extends FakeAiProvider
        {
            public function phrase(string $question, array $facts): string
            {
                return "Food took ₱{$facts['total']} so far this week.";
            }
        });

        $this->actingAs($user)->say('how much food this week?')->assertJsonPath('reply', 'Food took ₱123.45 so far this week.');
    }

    public function test_small_talk_with_numbers_is_replaced_and_a_phrase_failure_falls_back()
    {
        $user = $this->demo();
        $this->app->instance(AiProvider::class, new class extends FakeAiProvider
        {
            public function chat(string $text, array $context): array
            {
                return $text === 'hi' ? ['intent' => 'chat', 'reply' => '', 'draft' => null, 'question' => null] : parent::chat($text, $context);
            }

            public function advise(string $text, array $facts, array $history): string
            {
                return 'Hello! Save ₱7,654,321 today.'; // a figure that is not in the user's data
            }

            public function phrase(string $question, array $facts): string
            {
                throw new RuntimeException('429 quota');
            }
        });
        $this->actingAs($user);

        $this->say('hi')->assertJsonPath('reply', fn ($r) => ! str_contains($r, 'Save') && str_contains($r, 'net worth'));
        $this->say('how much did I spend this month?')->assertJsonPath('reply', 'You spent ₱0.00 this month.'); // quota while wording still answers
    }

    public function test_an_unknown_category_is_not_guessed()
    {
        $this->app->instance(AiProvider::class, new class extends FakeAiProvider
        {
            public function chat(string $text, array $context): array
            {
                return ['intent' => 'question', 'reply' => '', 'draft' => null, 'question' => ['key' => 'spending_by_category', 'category' => 'Yachts', 'from' => '2026-03-09', 'to' => '2026-03-10', 'period' => 'this week']];
            }
        });

        $this->actingAs($this->demo())->say('how much on yachts this week?')
            ->assertJsonPath('reply', fn ($r) => str_contains($r, 'net worth')); // not a fixed question: Whale answers from the snapshot
    }

    public function test_provider_errors_never_reach_the_client()
    {
        $this->actingAs($this->demo());
        $this->app->instance(AiProvider::class, new class extends FakeAiProvider
        {
            public static string $message = '';

            public function chat(string $text, array $context): array
            {
                throw new RuntimeException(self::$message);
            }
        });

        $provider = app(AiProvider::class);

        $provider::$message = 'HTTP 429 quota exceeded key=SECRET123';
        $r = $this->say('hello there')->assertStatus(429)->assertJsonPath('manual_url', route('transactions.index'));
        $this->assertStringContainsString('manually', $r->json('message'));
        $this->assertStringNotContainsString('SECRET123', $r->getContent());

        $provider::$message = 'cURL error 7 at https://x/?key=SECRET123';
        $r = $this->say('hello there')->assertStatus(503);
        $this->assertStringNotContainsString('SECRET123', $r->getContent());
        $this->assertSame(0, Transaction::count());
    }

    public function test_chat_is_rate_limited_with_a_friendly_message()
    {
        $this->actingAs($this->demo());

        for ($i = 0; $i < 20; $i++) {
            $this->say('hello')->assertOk();
        }

        $this->say('hello')->assertStatus(429)->assertJsonPath('manual_url', route('transactions.index'))
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Too many requests'));
    }

    public function test_chat_threads_and_drafts_belong_to_their_owner()
    {
        $mine = $this->demo();
        $other = User::factory()->create();
        $other->cards()->create(['name' => 'Cash', 'type' => 'cash', 'balance' => 1000]);
        $thread = $other->chatThreads()->create();
        $draft = $thread->messages()->forceCreate([
            'user_id' => $other->id, 'role' => 'assistant', 'content' => 'x',
            'draft_json' => ['merchant' => 'A', 'amount' => 100, 'card_id' => 1, 'category_id' => null, 'occurred_at' => '2026-03-09', 'missing' => []],
        ]);
        $this->actingAs($mine);

        $this->postJson(route('chat.messages'), ['text' => 'Jollibee 250', 'thread_id' => $thread->id])->assertNotFound();
        $this->postJson(route('chat.confirm', $draft->id))->assertNotFound();
        $this->assertSame(0, ChatMessage::where('user_id', $mine->id)->count());
        $this->assertSame(0, Transaction::withoutGlobalScopes()->count());
        $this->assertSame(1, ChatThread::withoutGlobalScopes()->count());
    }

    public function test_the_chat_page_shows_only_my_messages_and_an_image_goes_to_the_receipt_queue()
    {
        Queue::fake();
        $user = $this->demo();
        $other = User::factory()->create();
        $other->chatThreads()->create()->messages()->forceCreate(['user_id' => $other->id, 'role' => 'user', 'content' => 'secret']);
        $this->actingAs($user);

        $r = $this->postJson(route('chat.messages'), ['image' => UploadedFile::fake()->image('r.png')])->assertOk();

        Queue::assertPushed(ParseReceipt::class, fn ($job) => $job->receiptId === $r->json('receipt_id'));
        $this->assertSame(0, Transaction::count());
        $this->get(route('chat.index'))->assertInertia(fn ($page) => $page->component('chat/Index')->where('consented', true)->has('messages', 2));
    }

    public function test_advice_sees_the_whole_snapshot_and_only_quotes_known_figures()
    {
        $user = $this->demo();
        $seen = null;
        $this->app->instance(AiProvider::class, new class($seen) extends FakeAiProvider
        {
            public function __construct(public &$seen) {}

            public function chat(string $text, array $context): array
            {
                return ['intent' => 'chat', 'reply' => '', 'draft' => null, 'question' => null];
            }

            public function advise(string $text, array $facts, array $history): string
            {
                $this->seen = $facts;

                return "Your net worth is ₱{$facts['net_worth']['total']}. Keep going.";
            }
        });

        $r = $this->actingAs($user)->say('what is my net worth?')->json('reply');

        $this->assertStringContainsString('net worth', $r);
        $this->assertEqualsCanonicalizing(['net_worth', 'monthly_income', 'monthly_bills_and_subscriptions', 'left_after_bills', 'spent_this_month', 'spent_last_month', 'left_this_month', 'safe_to_spend_per_day', 'days_to_payday', 'balance_at_payday', 'lowest_balance_before_payday', 'cards', 'savings_pods', 'spending_by_category_this_month', 'bills_and_subscriptions', 'completed_loans', 'loans'], array_keys($seen));
        $this->assertNotEmpty($seen['cards']);
    }
}
