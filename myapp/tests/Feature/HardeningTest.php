<?php

namespace Tests\Feature;

use App\Models\ChatThread;
use App\Models\Receipt;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/** M5: data export, deletion, chat wipe, correction log, no secrets in responses. */
class HardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-03-10 09:00:00');
    }

    private function demo(): User
    {
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
        $user = User::where('email', 'demo@example.com')->first();
        $user->forceFill(['ai_consent_at' => now()])->save();

        return $user;
    }

    private function upload(User $user): int
    {
        $photo = new UploadedFile(base_path('tests/Fixtures/receipts/gcash-jollibee.png'), 'gcash-jollibee.png', 'image/png', null, true);

        return $this->actingAs($user)->postJson('/receipts', ['image' => $photo])->json('id');
    }

    public function test_export_has_the_users_data_only_and_no_internal_paths()
    {
        $user = $this->demo();
        $other = User::factory()->create();
        $other->categories()->create(['name' => 'OtherSecretCategory']);
        $this->upload($user);

        $r = $this->actingAs($user)->get('/settings/data/export')->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="whale-wallet-export.json"');
        $json = $r->getContent();

        $this->assertStringContainsString('Jollibee', $json);
        $this->assertStringNotContainsString('OtherSecretCategory', $json);
        $this->assertStringNotContainsString('receipts\/', $json);
        $this->assertSame('demo@example.com', json_decode($json, true)['user']['email']);
    }

    public function test_clear_chat_only_wipes_my_history()
    {
        $user = $this->demo();
        $other = User::factory()->create();
        $user->chatThreads()->create()->messages()->forceCreate(['user_id' => $user->id, 'role' => 'user', 'content' => 'hi']);
        $other->chatThreads()->create();

        $this->actingAs($user)->deleteJson('/settings/data/chat')->assertNoContent();

        $this->assertSame(0, $user->chatThreads()->count());
        $this->assertSame(1, ChatThread::withoutGlobalScopes()->count());
    }

    public function test_deleting_the_account_removes_images_tokens_and_rows()
    {
        $user = $this->demo();
        $this->upload($user);
        $path = Receipt::first()->path;
        Storage::disk('local')->assertExists($path);
        $user->createToken('phone');

        $this->withToken($user->createToken('p')->plainTextToken)->deleteJson('/api/v1/me', ['password' => 'wrong'])->assertStatus(422);
        $this->deleteJson('/api/v1/me', ['password' => 'password'])->assertNoContent();

        Storage::disk('local')->assertMissing($path);
        $this->assertSame(0, PersonalAccessToken::count());
        $this->assertSame(0, Receipt::withoutGlobalScopes()->count());
        $this->assertNull(User::find($user->id));
    }

    public function test_confirm_logs_what_the_user_corrected()
    {
        $user = $this->demo();
        $id = $this->upload($user);
        $draft = Receipt::withoutGlobalScopes()->find($id)->parsed_json;

        $this->actingAs($user)->postJson("/receipts/$id/confirm", ['merchant' => $draft['merchant'], 'amount' => 255, 'card_id' => $user->cards()->first()->id, 'occurred_at' => $draft['occurred_at']])->assertOk();

        $c = Receipt::withoutGlobalScopes()->find($id)->corrections_json;
        $this->assertSame(['from' => '25000', 'to' => '25500'], $c['amount']);
        $this->assertArrayNotHasKey('merchant', $c);
    }

    public function test_the_gemini_key_never_reaches_a_response()
    {
        $user = $this->demo();
        config(['services.gemini.key' => 'sk-test-leak-canary']);

        foreach (['/dashboard', '/receipts', '/chat', '/settings/data/export'] as $url) {
            $this->actingAs($user)->get($url)->assertDontSee('sk-test-leak-canary', false);
        }
        $this->actingAs($user)->getJson('/api/v1/me')->assertDontSee('sk-test-leak-canary', false);
    }
}
