<?php

namespace Tests\Feature;

use App\Contracts\AiProvider;
use App\Jobs\ParseReceipt;
use App\Models\Receipt;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Ai\FakeAiProvider;
use App\Services\Ai\GeminiProvider;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo('2026-03-10 09:00:00');
        Storage::fake('local');
    }

    private function demo(bool $consent = true): User
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'demo@example.com')->first();
        $user->forceFill(['ai_consent_at' => $consent ? now() : null])->save();

        return $user;
    }

    /** A queued receipt whose image is one of the fixtures, parsed by the fake provider. */
    private function queued(User $user, string $fixture = 'gcash-jollibee'): Receipt
    {
        $path = "receipts/{$user->id}/01-$fixture.png";
        Storage::disk('local')->put($path, file_get_contents(base_path("tests/Fixtures/receipts/$fixture.png")));

        return $user->receipts()->create(['path' => $path]);
    }

    private function parsed(User $user, string $fixture = 'gcash-jollibee'): Receipt
    {
        $receipt = $this->queued($user, $fixture);
        ParseReceipt::dispatchSync($receipt->id);

        return $receipt->fresh();
    }

    private function provider(array $raw): void
    {
        $this->app->instance(AiProvider::class, new class($raw) extends FakeAiProvider
        {
            public function __construct(private array $raw) {}

            public function parseReceipt(string $imagePath, array $categories): array
            {
                return $this->raw + parent::parseReceipt($imagePath, $categories);
            }
        });
    }

    private function confirmPayload(User $user, array $over = []): array
    {
        return $over + [
            'merchant' => 'Jollibee', 'amount' => 250, 'card_id' => $user->cards()->first()->id,
            'category_id' => $user->categories()->where('name', 'Food')->first()->id, 'occurred_at' => '2026-03-09', 'reference' => '5012 884 213',
        ];
    }

    public function test_ai_routes_are_blocked_until_consent()
    {
        Queue::fake();
        $user = $this->demo(consent: false);
        $this->actingAs($user);

        $this->postJson(route('receipts.store'), ['image' => UploadedFile::fake()->image('r.png')])
            ->assertStatus(422)->assertJsonPath('consent_url', route('tips.index'));
        $this->postJson(route('chat.messages'), ['text' => 'Jollibee 250'])->assertStatus(422);
        $this->post(route('receipts.store'), ['image' => UploadedFile::fake()->image('r.png')])->assertRedirect();

        Queue::assertNothingPushed();
        $this->assertSame(0, Receipt::withoutGlobalScopes()->count());
    }

    public function test_upload_stores_privately_and_queues_the_parse()
    {
        Queue::fake();
        $user = $this->demo();

        $this->actingAs($user)->postJson(route('receipts.store'), ['image' => UploadedFile::fake()->image('my receipt.png', 400, 600)])
            ->assertStatus(202)->assertJsonPath('status', 'queued')->assertJsonMissingPath('path');

        $receipt = Receipt::first();
        Queue::assertPushed(ParseReceipt::class, fn ($job) => $job->receiptId === $receipt->id);
        Storage::disk('local')->assertExists($receipt->path);
        Storage::disk('public')->assertMissing($receipt->path);
        $this->assertStringStartsWith("receipts/{$user->id}/", $receipt->path);
        $this->assertSame(0, Transaction::count());
    }

    public function test_upload_rejects_files_that_are_not_images()
    {
        Queue::fake();
        $this->actingAs($this->demo())->postJson(route('receipts.store'), ['image' => UploadedFile::fake()->create('r.pdf', 10, 'application/pdf')])
            ->assertStatus(422)->assertJsonValidationErrors('image');
        $this->actingAs(User::first())->postJson(route('receipts.store'), ['image' => UploadedFile::fake()->image('big.png')->size(9000)])
            ->assertStatus(422);
        Queue::assertNothingPushed();
    }

    public function test_job_turns_a_fixture_into_a_draft_and_saves_nothing()
    {
        $user = $this->demo();
        $receipt = $this->parsed($user);

        $this->assertSame('parsed', $receipt->status);
        $this->assertSame('Jollibee', $receipt->parsed_json['merchant']);
        $this->assertSame(25000, $receipt->parsed_json['amount']);
        $this->assertSame('2026-03-09', $receipt->parsed_json['occurred_at']);
        $this->assertSame('5012 884 213', $receipt->parsed_json['reference']);
        $this->assertSame('Food', $receipt->parsed_json['category']);
        $this->assertSame($user->cards()->first()->id, $receipt->parsed_json['card_id']);
        $this->assertSame([], $receipt->parsed_json['duplicate_of']);
        $this->assertStringEndsWith('.jpg', $receipt->path); // downscaled copy replaced the upload
        $this->assertSame(0, Transaction::withoutGlobalScopes()->count());
    }

    public function test_large_images_are_downscaled()
    {
        $user = $this->demo();
        $path = "receipts/{$user->id}/big-gcash-jollibee.png";
        Storage::disk('local')->put($path, UploadedFile::fake()->image('x.png', 3200, 1000)->getContent());
        $receipt = $user->receipts()->create(['path' => $path]);

        ParseReceipt::dispatchSync($receipt->id);

        [$w, $h] = getimagesize(Storage::disk('local')->path($receipt->fresh()->path));
        $this->assertSame([1280, 400], [$w, $h]);
    }

    public function test_polling_shows_status_and_draft_only()
    {
        $user = $this->demo();
        $receipt = $this->parsed($user);

        $this->actingAs($user)->getJson(route('receipts.show', $receipt))
            ->assertOk()->assertJsonPath('status', 'parsed')->assertJsonPath('draft.merchant', 'Jollibee')->assertJsonMissingPath('path');
    }

    public function test_confirm_creates_the_transaction_and_deletes_the_image()
    {
        $user = $this->demo();
        $receipt = $this->parsed($user);
        $path = $receipt->path;
        $balance = $user->cards()->first()->balance;
        $this->assertSame(0, Transaction::count() + Transaction::withoutGlobalScopes()->count());

        $this->actingAs($user)->postJson(route('receipts.confirm', $receipt), $this->confirmPayload($user, ['amount' => 255.5]))->assertOk();

        $t = Transaction::withoutGlobalScopes()->first();
        $this->assertSame(25550, $t->amount);
        $this->assertSame('receipt', $t->source->value);
        $this->assertSame($receipt->id, $t->receipt_id);
        $this->assertSame($user->id, $t->user_id);
        $this->assertSame($balance - 25550, $user->cards()->first()->balance);
        $this->assertSame('confirmed', $receipt->fresh()->status);
        Storage::disk('local')->assertMissing($path);
        $this->assertNull($receipt->fresh()->path);

        $this->postJson(route('receipts.confirm', $receipt), $this->confirmPayload($user))->assertStatus(409); // no double save
        $this->assertSame(1, Transaction::withoutGlobalScopes()->count());
    }

    public function test_confirm_keeps_the_image_when_the_setting_is_on()
    {
        $user = $this->demo();
        $this->actingAs($user)->post(route('receipts.keep'), ['keep' => true])->assertRedirect();
        $this->assertTrue($user->fresh()->keep_receipts);
        $receipt = $this->parsed($user);

        $this->postJson(route('receipts.confirm', $receipt), $this->confirmPayload($user))->assertOk();

        Storage::disk('local')->assertExists($receipt->path);
        $this->assertNotNull($receipt->fresh()->path);
    }

    public function test_duplicates_are_flagged_by_amount_and_merchant_within_48_hours()
    {
        $user = $this->demo();
        $card = $user->cards()->first()->id;
        $near = Transaction::factory()->for($user)->create(['card_id' => $card, 'merchant' => 'jollibee', 'amount' => 25000, 'occurred_at' => '2026-03-08 20:00:00']);
        Transaction::factory()->for($user)->create(['card_id' => $card, 'merchant' => 'Jollibee', 'amount' => 25000, 'occurred_at' => '2026-03-01 12:00:00']); // too old
        Transaction::factory()->for($user)->create(['card_id' => $card, 'merchant' => 'Jollibee', 'amount' => 25100, 'occurred_at' => '2026-03-09 12:00:00']); // other amount

        $this->assertSame([$near->id], $this->parsed($user)->parsed_json['duplicate_of']);
    }

    public function test_duplicates_are_flagged_by_reference()
    {
        $user = $this->demo();
        $old = Transaction::factory()->for($user)->create([
            'card_id' => $user->cards()->first()->id, 'merchant' => 'Something else', 'amount' => 1, 'occurred_at' => '2025-12-01', 'reference' => '5012 884 213',
        ]);

        $this->assertSame([$old->id], $this->parsed($user)->parsed_json['duplicate_of']);
    }

    public function test_another_users_transactions_are_not_duplicates()
    {
        $user = $this->demo();
        Transaction::factory()->create(['merchant' => 'Jollibee', 'amount' => 25000, 'occurred_at' => '2026-03-09 12:00:00', 'reference' => '5012 884 213']);

        $this->assertSame([], $this->parsed($user)->parsed_json['duplicate_of']);
    }

    public static function badReads(): array
    {
        return [
            'zero amount' => [['amount' => '0.00'], 'amount'],
            'negative amount' => [['amount' => '-50'], 'amount'],
            'no amount' => [['amount' => ''], 'amount'],
            'future date' => [['occurred_at' => '2026-03-20 10:00'], 'date'],
            'over a year old' => [['occurred_at' => '2025-03-01 10:00'], 'date'],
            'no date' => [['occurred_at' => ''], 'date'],
            'foreign currency' => [['currency' => 'USD'], 'peso'],
        ];
    }

    #[DataProvider('badReads')]
    public function test_implausible_reads_fail_with_a_safe_message(array $raw, string $word)
    {
        $user = $this->demo();
        $this->provider($raw);

        $receipt = $this->parsed($user);

        $this->assertSame('failed', $receipt->status);
        $this->assertStringContainsString($word, $receipt->error);
        $this->assertNull($receipt->parsed_json);
        $this->assertNull($receipt->path); // nothing worth keeping
    }

    public function test_provider_errors_never_reach_the_client()
    {
        $user = $this->demo();
        $this->app->instance(AiProvider::class, new class extends FakeAiProvider
        {
            public function parseReceipt(string $imagePath, array $categories): array
            {
                throw new RuntimeException('HTTP 429 RESOURCE_EXHAUSTED key=SECRET123 https://generativelanguage.googleapis.com/?key=SECRET123');
            }
        });

        $receipt = $this->parsed($user);
        $body = $this->actingAs($user)->getJson(route('receipts.show', $receipt))->assertOk()->getContent();

        $this->assertSame('failed', $receipt->status);
        $this->assertStringContainsString('out of free quota', $receipt->error);
        $this->assertStringContainsString('manually', $receipt->error);
        $this->assertStringNotContainsString('SECRET123', $body);
        $this->assertStringNotContainsString('googleapis', $body);
    }

    public function test_other_errors_get_a_generic_message()
    {
        $user = $this->demo();
        $this->app->instance(AiProvider::class, new class extends FakeAiProvider
        {
            public function parseReceipt(string $imagePath, array $categories): array
            {
                throw new RuntimeException('SQLSTATE boom SECRET123');
            }
        });

        $receipt = $this->parsed($user);

        $this->assertSame('failed', $receipt->status);
        $this->assertStringNotContainsString('SECRET123', $receipt->error);
    }

    public function test_receipts_belong_to_their_owner()
    {
        $mine = $this->demo();
        $theirs = $this->parsed(User::factory()->create(['ai_consent_at' => now()]));
        $this->actingAs($mine);

        $this->getJson(route('receipts.show', $theirs))->assertNotFound();
        $this->postJson(route('receipts.confirm', $theirs), $this->confirmPayload($mine))->assertNotFound();
        $this->deleteJson(route('receipts.destroy', $theirs))->assertNotFound();
        $this->assertSame('parsed', Receipt::withoutGlobalScopes()->find($theirs->id)->status);
        $this->assertSame(0, Transaction::withoutGlobalScopes()->count());
    }

    public function test_confirm_cannot_charge_another_users_card()
    {
        $user = $this->demo();
        $receipt = $this->parsed($user);
        $theirCard = User::factory()->create()->cards()->create(['name' => 'X', 'type' => 'cash']);

        $this->actingAs($user)->postJson(route('receipts.confirm', $receipt), $this->confirmPayload($user, ['card_id' => $theirCard->id]))
            ->assertStatus(422)->assertJsonValidationErrors('card_id');
    }

    public function test_discarding_deletes_the_image()
    {
        $user = $this->demo();
        $receipt = $this->parsed($user);

        $this->actingAs($user)->deleteJson(route('receipts.destroy', $receipt))->assertNoContent();

        Storage::disk('local')->assertMissing($receipt->path);
        $this->assertNull(Receipt::find($receipt->id));
    }

    public function test_upload_is_rate_limited_with_a_friendly_message()
    {
        Queue::fake();
        $this->actingAs($this->demo());

        for ($i = 0; $i < 10; $i++) {
            $this->postJson(route('receipts.store'), ['image' => UploadedFile::fake()->image('r.png')])->assertStatus(202);
        }

        $this->postJson(route('receipts.store'), ['image' => UploadedFile::fake()->image('r.png')])
            ->assertStatus(429)->assertJsonPath('manual_url', route('transactions.index'))->assertJsonPath('message', fn ($m) => str_contains($m, 'add the expense manually'));
    }

    public static function fixtures(): array
    {
        $expected = json_decode(file_get_contents(__DIR__.'/../Fixtures/receipts/expected.json'), true);

        return array_map(fn ($e) => [$e], array_combine(array_keys($expected), array_keys($expected)));
    }

    #[DataProvider('fixtures')]
    public function test_every_fixture_parses_to_its_expected_fields(string $stem)
    {
        $want = json_decode(file_get_contents(base_path('tests/Fixtures/receipts/expected.json')), true)[$stem];
        $user = $this->demo();

        $draft = $this->parsed($user, $stem)->parsed_json;

        $this->assertSame($want['merchant'], $draft['merchant']);
        $this->assertSame((int) round($want['amount'] * 100), $draft['amount']);
        $this->assertSame(substr($want['occurred_at'], 0, 10), $draft['occurred_at']);
        $this->assertSame($want['reference'], $draft['reference']);
    }

    public function test_receipts_page_lists_open_receipts_for_the_owner_only()
    {
        $user = $this->demo();
        $this->parsed($user);
        $this->parsed(User::factory()->create());

        $this->actingAs($user)->get(route('transactions.index'))->assertInertia(fn ($page) => $page->component('transactions/Index')
            ->where('consented', true)->where('keepReceipts', false)->has('receipts', 1)->where('receipts.0.status', 'parsed'));
    }

    public function test_eval_command_scores_every_fixture_and_explains_a_missing_key()
    {
        $this->artisan('receipts:eval')->expectsOutputToContain('merchant 8/8, amount 8/8, date 8/8, reference 8/8, category 8/8')->assertSuccessful();

        config(['ai.providers.gemini.key' => null]);
        $this->app->bind(AiProvider::class, GeminiProvider::class);
        $this->artisan('receipts:eval')->expectsOutputToContain('GEMINI_API_KEY is not set')->assertFailed();
    }

    public function test_the_old_receipts_page_opens_the_scan_dialog_on_transactions()
    {
        $this->actingAs($this->demo())->get(route('receipts.index'))->assertRedirect(route('transactions.index', ['scan' => 1]));
    }
}
