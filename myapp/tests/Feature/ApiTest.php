<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Device;
use App\Models\Pod;
use App\Models\Receipt;
use App\Models\Recommendation;
use App\Models\RecurringItem;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/** The mobile API at /api/v1: real bearer tokens through auth:sanctum, centavos in and out. */
class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
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

    /** Sends the next requests with a fresh token for $user. */
    private function as(User $user): static
    {
        return $this->bearer($user->createToken('test phone')->plainTextToken);
    }

    private function bearer(string $token): static
    {
        $this->app['auth']->forgetGuards(); // the sanctum guard remembers the last user within one test

        return $this->withToken($token);
    }

    // auth

    public function test_login_returns_a_token_named_after_the_device_and_me_works_with_it()
    {
        $user = $this->demo();

        $r = $this->postJson('/api/v1/auth/login', ['email' => 'demo@example.com', 'password' => 'password', 'device_name' => 'Pixel 8'])
            ->assertOk()->assertJsonPath('token_type', 'Bearer')->assertJsonPath('user.email', 'demo@example.com');

        $this->assertSame(['Pixel 8'], $user->tokens()->pluck('name')->all());

        $this->bearer($r->json('token'))->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.id', $user->id)->assertJsonMissingPath('data.password');

        // same device again replaces its token instead of piling up
        $this->postJson('/api/v1/auth/login', ['email' => 'demo@example.com', 'password' => 'password', 'device_name' => 'Pixel 8'])->assertOk();
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_login_rejects_wrong_credentials_and_bad_input()
    {
        $this->demo();

        $this->postJson('/api/v1/auth/login', ['email' => 'demo@example.com', 'password' => 'nope', 'device_name' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors('email');
        $this->postJson('/api/v1/auth/login', [])->assertStatus(422)->assertJsonValidationErrors(['email', 'password', 'device_name']);
    }

    public function test_login_refuses_accounts_with_two_factor_enabled()
    {
        User::factory()->withTwoFactor()->create(['email' => 'two@example.com']);

        $this->postJson('/api/v1/auth/login', ['email' => 'two@example.com', 'password' => 'password', 'device_name' => 'x'])
            ->assertStatus(422)->assertJsonPath('errors.email.0', fn ($m) => str_contains($m, 'does not support it yet'));
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_login_is_throttled()
    {
        $this->demo();

        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'demo@example.com', 'password' => 'bad', 'device_name' => 'x'])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'demo@example.com', 'password' => 'password', 'device_name' => 'x'])->assertStatus(429);
    }

    public function test_logout_revokes_the_current_token_only()
    {
        $user = $this->demo();
        $keep = $user->createToken('tablet')->plainTextToken;
        $this->as($user)->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->assertSame(['tablet'], $user->tokens()->pluck('name')->all());
        $this->bearer($keep)->getJson('/api/v1/me')->assertOk();
    }

    public function test_a_revoked_token_gets_401_json()
    {
        $user = $this->demo();
        $plain = $user->createToken('gone')->plainTextToken;
        $user->tokens()->delete();

        $this->bearer($plain)->getJson('/api/v1/me')->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_unauthenticated_requests_get_401_json_and_never_a_redirect()
    {
        foreach (['/api/v1/me', '/api/v1/dashboard', '/api/v1/cards', '/api/v1/transactions', '/api/v1/forecast'] as $url) {
            $this->get($url)->assertUnauthorized()->assertHeader('content-type', 'application/json')->assertJsonPath('message', 'Unauthenticated.');
        }
        $this->post('/api/v1/cards', [])->assertUnauthorized();
    }

    public function test_unverified_accounts_can_sign_in_but_get_403()
    {
        $user = User::factory()->unverified()->create();

        $this->as($user)->getJson('/api/v1/me')->assertOk();
        $this->getJson('/api/v1/dashboard')->assertForbidden();
    }

    public function test_a_browser_session_does_not_authenticate_the_api()
    {
        $this->actingAs(User::factory()->create())->getJson('/api/v1/me')->assertUnauthorized();
    }

    // dashboard and forecast

    public function test_dashboard_has_safe_to_spend_cards_pods_bills_and_tips_in_centavos()
    {
        $user = $this->demo();
        $user->pods()->create(['name' => 'Trip', 'target_amount' => 1_000_000, 'saved_amount' => 250_000]);
        $user->recommendations()->create(['text' => 'Spend less.']);

        $r = $this->as($user)->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonPath('cards.0.balance', 500_000)->assertJsonPath('pods.0.saved_amount', 250_000)
            ->assertJsonPath('recommendations.0.text', 'Spend less.')->assertJsonMissingPath('forecast.series');

        $this->assertIsInt($r->json('safe_to_spend_today'));
        $this->assertSame($r->json('forecast.safe_per_day'), $r->json('safe_to_spend_today'));
        $this->assertContains('iCloud', array_column($r->json('upcoming_bills'), 'name')); // due the 12th
        $this->assertSame(7_900, collect($r->json('upcoming_bills'))->firstWhere('name', 'iCloud')['amount']);
    }

    public function test_forecast_is_day_by_day_and_what_if_skip_frees_cash()
    {
        $user = $this->demo();
        $icloud = $user->recurringItems()->where('name', 'iCloud')->first();

        $base = $this->as($user)->getJson('/api/v1/forecast')->assertOk()->assertJsonStructure(['payday', 'days', 'series' => [['date', 'balance']], 'lowest' => ['date', 'amount'], 'at_payday', 'safe_per_day']);
        $skip = $this->getJson('/api/v1/forecast?skip='.$icloud->id)->assertOk()->assertJsonPath('skipped', $icloud->id);

        $this->assertSame($base->json('at_payday') + 7_900, $skip->json('at_payday'));
        $this->getJson('/api/v1/forecast?skip=abc')->assertStatus(422);
    }

    // CRUD, centavos

    public function test_cards_crud_takes_and_returns_centavos()
    {
        $user = $this->demo();
        $this->as($user);

        $id = $this->postJson('/api/v1/cards', ['name' => 'Maya', 'type' => 'ewallet', 'balance' => 123_456, 'monthly_limit' => 50_000])
            ->assertCreated()->assertJsonPath('data.balance', 123_456)->assertJsonPath('data.monthly_limit', 50_000)->json('data.id');
        $this->assertSame(123_456, Card::find($id)->balance); // stored as sent, not multiplied by 100

        $this->patchJson("/api/v1/cards/$id", ['name' => 'Maya 2', 'type' => 'ewallet', 'balance' => 99_900])->assertOk()->assertJsonPath('data.name', 'Maya 2');
        $this->getJson('/api/v1/cards')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("/api/v1/cards/$id")->assertOk()->assertJsonPath('data.spent', 0);
        $this->deleteJson("/api/v1/cards/$id")->assertNoContent();
        $this->assertNull(Card::find($id));
    }

    public function test_validation_errors_use_the_laravel_shape()
    {
        $this->as($this->demo());

        $this->postJson('/api/v1/cards', [])->assertStatus(422)->assertJsonValidationErrors(['name', 'type', 'balance'])->assertJsonStructure(['message', 'errors']);
        $this->postJson('/api/v1/pods', ['name' => 'x', 'target_amount' => 0, 'saved_amount' => 0, 'monthly_amount' => 0])->assertStatus(422)->assertJsonValidationErrors('target_amount');
        $this->postJson('/api/v1/categories', ['name' => 'Food'])->assertStatus(422)->assertJsonValidationErrors('name'); // seeded
    }

    public function test_pods_categories_and_recurring_items_crud()
    {
        $this->as($this->demo());

        $pod = $this->postJson('/api/v1/pods', ['name' => 'Trip', 'target_amount' => 1_000_000, 'saved_amount' => 0, 'monthly_amount' => 100_000, 'target_date' => '2026-12-01'])
            ->assertCreated()->assertJsonPath('data.target_amount', 1_000_000)->json('data.id');
        $this->patchJson("/api/v1/pods/$pod", ['name' => 'Trip', 'target_amount' => 2_000_000, 'saved_amount' => 5, 'monthly_amount' => 0])->assertOk()->assertJsonPath('data.target_amount', 2_000_000);
        $this->deleteJson("/api/v1/pods/$pod")->assertNoContent();

        $cat = $this->postJson('/api/v1/categories', ['name' => 'Pets'])->assertCreated()->json('data.id');
        $this->patchJson("/api/v1/categories/$cat", ['name' => 'Pets'])->assertOk(); // keeping its own name is fine
        $this->deleteJson("/api/v1/categories/$cat")->assertNoContent();

        $rent = $this->postJson('/api/v1/recurring-items', ['name' => 'Water', 'amount' => 120_000, 'cadence' => 'quarterly', 'next_due_on' => '2026-03-15', 'kind' => 'bill'])
            ->assertCreated()->assertJsonPath('data.amount', 120_000)->assertJsonPath('data.monthly_amount', 40_000)->assertJsonPath('data.kind', 'bill')->json('data.id');
        $this->patchJson("/api/v1/recurring-items/$rent", ['name' => 'Water', 'amount' => 150_000, 'cadence' => 'monthly', 'next_due_on' => '2026-03-16', 'kind' => 'bill'])->assertOk()->assertJsonPath('data.next_due_on', '2026-03-16');
        $this->getJson('/api/v1/recurring-items')->assertOk()->assertJsonCount(10, 'data');
        $this->postJson('/api/v1/recurring-items', ['name' => 'x', 'amount' => 1, 'cadence' => 'monthly', 'next_due_on' => '2026-03-15', 'kind' => 'gift'])->assertStatus(422)->assertJsonValidationErrors('kind');
        $this->deleteJson("/api/v1/recurring-items/$rent")->assertNoContent();
    }

    public function test_transactions_crud_adjusts_the_card_and_filters_paginate()
    {
        $user = $this->demo();
        $card = $user->cards()->first();
        $food = $user->categories()->where('name', 'Food')->first();
        $this->as($user);

        $id = $this->postJson('/api/v1/transactions', ['merchant' => 'Jollibee', 'amount' => 25_000, 'card_id' => $card->id, 'category_id' => $food->id, 'occurred_at' => '2026-03-09', 'source' => 'manual'])
            ->assertCreated()->assertJsonPath('data.amount', 25_000)->assertJsonPath('data.type', 'expense')->json('data.id');
        $this->assertSame(475_000, $card->fresh()->balance);

        $this->patchJson("/api/v1/transactions/$id", ['merchant' => 'Jollibee', 'amount' => 30_000, 'card_id' => $card->id, 'occurred_at' => '2026-03-09', 'source' => 'manual'])
            ->assertOk()->assertJsonPath('data.amount', 30_000);
        $this->assertSame(470_000, $card->fresh()->balance);

        $this->postJson('/api/v1/transactions', ['merchant' => 'Grab', 'amount' => 9_900, 'card_id' => $card->id, 'occurred_at' => '2026-02-01', 'source' => 'manual'])->assertCreated();

        $this->getJson('/api/v1/transactions?q=jolli')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.merchant', 'Jollibee');
        $this->getJson('/api/v1/transactions?from=2026-03-01&to=2026-03-31')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/transactions?category_id='.$food->id.'&card_id='.$card->id)->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/transactions?per_page=1')->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2)->assertJsonStructure(['links', 'meta']);
        $this->getJson('/api/v1/transactions?per_page=500')->assertStatus(422);
        $this->getJson("/api/v1/transactions/$id")->assertOk()->assertJsonPath('data.merchant', 'Jollibee');

        $this->deleteJson("/api/v1/transactions/$id")->assertNoContent();
        $this->assertSame(490_100, $card->fresh()->balance); // 500k - 9.9k left
    }

    public function test_transactions_reject_another_users_card_and_transfers_cannot_be_edited()
    {
        $user = $this->demo();
        $theirs = Card::factory()->create();
        $this->as($user);

        $this->postJson('/api/v1/transactions', ['merchant' => 'x', 'amount' => 100, 'card_id' => $theirs->id, 'occurred_at' => '2026-03-09', 'source' => 'manual'])
            ->assertStatus(422)->assertJsonValidationErrors('card_id');

        $pod = $user->pods()->create(['name' => 'Trip', 'target_amount' => 1_000_000]);
        $transfer = $this->postJson('/api/v1/transfers', ['from' => 'card:'.$user->cards()->first()->id, 'to' => 'pod:'.$pod->id, 'amount' => 50_000])
            ->assertCreated()->assertJsonPath('data.type', 'transfer')->assertJsonPath('data.amount', 50_000)->json('data.id');
        $this->assertSame(50_000, $pod->fresh()->saved_amount);
        $this->assertSame(450_000, $user->cards()->first()->balance);
        $this->patchJson("/api/v1/transactions/$transfer", ['merchant' => 'x', 'amount' => 1, 'card_id' => $user->cards()->first()->id, 'occurred_at' => '2026-03-09', 'source' => 'manual'])->assertStatus(422);

        $this->postJson('/api/v1/transfers', ['from' => 'card:'.$user->cards()->first()->id, 'to' => 'pod:'.$pod->id, 'amount' => 9_999_999])->assertStatus(422)->assertJsonValidationErrors('amount');
    }

    public function test_csv_export_is_the_users_own_rows()
    {
        $user = $this->demo();
        Transaction::factory()->for($user)->create(['card_id' => $user->cards()->first()->id, 'merchant' => 'Mine', 'amount' => 12_345]);
        Transaction::factory()->create(['merchant' => 'Theirs']);

        $csv = $this->as($user)->get('/api/v1/export/transactions.csv')->assertOk()->streamedContent();

        $this->assertStringContainsString('date,type,merchant,amount', $csv);
        $this->assertStringContainsString('Mine', $csv);
        $this->assertStringNotContainsString('Theirs', $csv);
    }

    // consent, receipts, chat, tips

    public function test_ai_endpoints_are_blocked_until_consent_then_open()
    {
        $user = $this->demo(consent: false);
        $this->as($user);
        $image = fn () => UploadedFile::fake()->image('r.png');

        $this->postJson('/api/v1/receipts', ['image' => $image()])->assertStatus(422)->assertJsonPath('consent_url', url('/api/v1/consent/ai'));
        $this->postJson('/api/v1/chat/messages', ['text' => 'Jollibee 250'])->assertStatus(422)->assertJsonPath('consent_url', url('/api/v1/consent/ai'));
        $this->postJson('/api/v1/recommendations/generate')->assertStatus(422);
        $this->assertSame(0, Receipt::count());

        $this->postJson('/api/v1/consent/ai')->assertOk()->assertJsonPath('ai_consent_at', fn ($at) => $at !== null);
        $this->postJson('/api/v1/chat/messages', ['text' => 'Jollibee 250'])->assertOk();
    }

    public function test_receipt_upload_poll_and_confirm_with_the_fake_provider()
    {
        $user = $this->demo();
        $card = $user->cards()->first();
        $photo = new UploadedFile(base_path('tests/Fixtures/receipts/gcash-jollibee.png'), 'gcash-jollibee.png', 'image/png', null, true);

        $id = $this->as($user)->postJson('/api/v1/receipts', ['image' => $photo])->assertStatus(202)->assertJsonStructure(['id', 'status'])->json('id');

        // queue is sync in tests, so the worker has already run
        $this->getJson("/api/v1/receipts/$id")->assertOk()->assertJsonPath('status', 'parsed')->assertJsonPath('draft.merchant', 'Jollibee')
            ->assertJsonPath('draft.amount', 25_000)->assertJsonPath('draft.duplicate_of', [])->assertJsonMissingPath('path');
        $this->assertSame(0, Transaction::count()); // nothing saved before confirm

        $this->postJson("/api/v1/receipts/$id/confirm", ['merchant' => 'Jollibee', 'amount' => 25_500, 'card_id' => $card->id, 'occurred_at' => '2026-03-09', 'reference' => '5012 884 213'])
            ->assertOk()->assertJsonPath('status', 'confirmed');

        $this->assertSame(25_500, Transaction::first()->amount);
        $this->assertSame(500_000 - 25_500, $card->fresh()->balance);
        $this->postJson("/api/v1/receipts/$id/confirm", ['merchant' => 'Jollibee', 'amount' => 25_500, 'card_id' => $card->id, 'occurred_at' => '2026-03-09'])->assertStatus(409);
        $this->postJson('/api/v1/receipts', ['image' => UploadedFile::fake()->create('r.pdf', 10, 'application/pdf')])->assertStatus(422)->assertJsonValidationErrors('image');
    }

    public function test_chat_logs_a_draft_and_confirm_saves_it_once()
    {
        $user = $this->demo();
        $this->as($user);

        $r = $this->postJson('/api/v1/chat/messages', ['text' => 'Jollibee 250'])->assertOk()
            ->assertJsonPath('draft.amount', 25_000)->assertJsonPath('draft.missing', [])->assertJsonStructure(['id', 'reply', 'draft']);
        $this->assertSame(0, Transaction::count());

        $this->postJson('/api/v1/chat/drafts/'.$r->json('id').'/confirm')->assertOk()->assertJsonPath('saved', true);
        $this->assertSame(25_000, Transaction::first()->amount);
        $this->postJson('/api/v1/chat/drafts/'.$r->json('id').'/confirm')->assertStatus(422);
        $this->postJson('/api/v1/chat/messages', [])->assertStatus(422)->assertJsonValidationErrors('text');
    }

    public function test_recommendations_generate_list_and_dismiss()
    {
        $user = $this->demo();
        $this->as($user);

        $tips = $this->postJson('/api/v1/recommendations/generate')->assertOk()->json('data');
        $this->assertGreaterThanOrEqual(3, count($tips));
        $this->getJson('/api/v1/recommendations')->assertOk()->assertJsonCount(count($tips), 'data');

        $this->postJson("/api/v1/recommendations/{$tips[0]['id']}/dismiss")->assertNoContent();
        $this->getJson('/api/v1/recommendations')->assertJsonCount(count($tips) - 1, 'data');
    }

    public function test_ai_endpoints_are_rate_limited_per_user()
    {
        $user = $this->demo();
        $this->as($user);

        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/v1/recommendations/generate')->assertOk();
        }
        $this->postJson('/api/v1/recommendations/generate')->assertStatus(429)->assertJsonStructure(['message']);

        // another user has their own allowance
        $other = User::factory()->create(['ai_consent_at' => now()]);
        $other->cards()->create(['name' => 'Cash', 'type' => 'cash', 'balance' => 1_000]);
        $this->as($other)->postJson('/api/v1/recommendations/generate')->assertStatus(200);
    }

    // devices

    public function test_devices_register_refresh_and_delete()
    {
        $user = $this->demo();
        $this->as($user);
        $body = ['expo_push_token' => 'ExponentPushToken[abc123]', 'platform' => 'android'];

        $id = $this->postJson('/api/v1/devices', $body)->assertCreated()->assertJsonPath('data.platform', 'android')->json('data.id');
        $this->postJson('/api/v1/devices', ['platform' => 'ios'] + $body)->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('data.platform', 'ios');
        $this->assertSame(1, Device::count());

        $this->postJson('/api/v1/devices', ['expo_push_token' => 'not-a-token', 'platform' => 'ios'])->assertStatus(422)->assertJsonValidationErrors('expo_push_token');
        $this->postJson('/api/v1/devices', ['expo_push_token' => 'ExpoPushToken[x]', 'platform' => 'web'])->assertStatus(422)->assertJsonValidationErrors('platform');

        $this->deleteJson("/api/v1/devices/$id")->assertNoContent();
        $this->assertSame(0, Device::count());
    }

    // isolation

    public function test_a_token_never_reaches_another_users_rows()
    {
        [$a, $b] = [$this->demo(), User::factory()->create(['ai_consent_at' => now()])];
        $card = $b->cards()->create(['name' => 'Theirs', 'type' => 'cash', 'balance' => 1_000]);
        $pod = $b->pods()->create(['name' => 'Theirs', 'target_amount' => 1_000]);
        $category = $b->categories()->create(['name' => 'Theirs']);
        $item = $b->recurringItems()->create(['name' => 'Theirs', 'amount' => 1_000, 'kind' => 'bill', 'next_due_on' => '2026-03-11']);
        $tx = Transaction::factory()->for($b)->create(['card_id' => $card->id]);
        $receipt = $b->receipts()->create(['status' => 'parsed', 'parsed_json' => ['merchant' => 'Theirs']]);
        $message = $b->chatThreads()->create()->messages()->forceCreate(['user_id' => $b->id, 'role' => 'assistant', 'content' => 'x', 'draft_json' => [
            'merchant' => 'Theirs', 'amount' => 100, 'card_id' => $card->id, 'category_id' => null, 'occurred_at' => '2026-03-09', 'missing' => [],
        ]]);
        $tip = Recommendation::factory()->for($b)->create();
        $device = Device::unguarded(fn () => Device::create(['user_id' => $b->id, 'expo_push_token' => 'ExponentPushToken[b]', 'platform' => 'ios']));
        $this->as($a);

        $this->getJson('/api/v1/cards')->assertJsonMissing(['name' => 'Theirs']);
        $this->getJson('/api/v1/transactions')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/recommendations')->assertJsonMissing(['id' => $tip->id]);
        $this->getJson('/api/v1/dashboard')->assertJsonMissing(['name' => 'Theirs']);

        $body = ['name' => 'Hacked', 'type' => 'cash', 'balance' => 1];
        foreach ([['cards', $card, $body], ['pods', $pod, ['name' => 'h', 'target_amount' => 1, 'saved_amount' => 0, 'monthly_amount' => 0]],
            ['categories', $category, ['name' => 'Hacked']], ['recurring-items', $item, ['name' => 'h', 'amount' => 1, 'cadence' => 'monthly', 'next_due_on' => '2026-03-15', 'kind' => 'bill']],
            ['transactions', $tx, ['merchant' => 'h', 'amount' => 1, 'card_id' => $a->cards()->first()->id, 'occurred_at' => '2026-03-09', 'source' => 'manual']]] as [$path, $row, $payload]) {
            $this->getJson("/api/v1/$path/{$row->id}")->assertNotFound();
            $this->patchJson("/api/v1/$path/{$row->id}", $payload)->assertNotFound();
            $this->deleteJson("/api/v1/$path/{$row->id}")->assertNotFound();
        }

        $this->getJson("/api/v1/receipts/{$receipt->id}")->assertNotFound();
        $this->postJson("/api/v1/receipts/{$receipt->id}/confirm", ['merchant' => 'h', 'amount' => 1, 'card_id' => $a->cards()->first()->id, 'occurred_at' => '2026-03-09'])->assertNotFound();
        $this->postJson("/api/v1/chat/drafts/{$message->id}/confirm")->assertNotFound();
        $this->postJson("/api/v1/recommendations/{$tip->id}/dismiss")->assertNotFound();
        $this->deleteJson("/api/v1/devices/{$device->id}")->assertNotFound();
        $this->postJson('/api/v1/transfers', ['from' => 'card:'.$card->id, 'to' => 'pod:'.$pod->id, 'amount' => 1])->assertNotFound();
        $this->postJson('/api/v1/transactions', ['merchant' => 'h', 'amount' => 1, 'card_id' => $card->id, 'occurred_at' => '2026-03-09', 'source' => 'manual'])->assertStatus(422);

        // nothing of B's changed
        $this->assertSame(1_000, $card->fresh()->balance);
        $this->assertSame('Theirs', $pod->fresh()->name);
        $this->assertSame('Theirs', $category->fresh()->name);
        $this->assertNotNull(Transaction::withoutGlobalScopes()->find($tx->id));
        $this->assertSame('parsed', Receipt::withoutGlobalScopes()->find($receipt->id)->status);
        $this->assertNull(Recommendation::withoutGlobalScopes()->find($tip->id)->dismissed_at);
        $this->assertNotNull(Device::withoutGlobalScopes()->find($device->id));
        $this->assertCount(0, RecurringItem::withoutGlobalScopes()->where('user_id', $a->id)->where('name', 'h')->get());
    }

    public function test_the_user_scope_applies_under_the_sanctum_guard_and_each_token_sees_its_own_user()
    {
        [$a, $b] = [User::factory()->create(), User::factory()->create()];
        Pod::factory()->for($a)->create(['name' => 'A pod']);
        Pod::factory()->for($b)->create(['name' => 'B pod']);

        $this->as($a)->getJson('/api/v1/pods')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'A pod');
        $this->as($b)->getJson('/api/v1/pods')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'B pod');
    }
}
