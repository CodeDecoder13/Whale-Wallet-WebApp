<?php

namespace Tests\Feature;

use App\Actions\GenerateRecommendations;
use App\Contracts\AiProvider;
use App\Models\Recommendation;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Ai\FakeAiProvider;
use App\Services\Ai\GeminiProvider;
use Database\Seeders\DatabaseSeeder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\StructuredAnonymousAgent;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RecommendationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo('2026-03-10 09:00:00');
    }

    private function demo(bool $consent = true): User
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'demo@example.com')->first();
        $user->forceFill(['ai_consent_at' => $consent ? now() : null])->save();

        return $user;
    }

    public static function tips(): array
    {
        return [
            'plain' => ['You have 1250.50 left.', true],
            'thousands separator' => ['Keep ₱35,000 in mind.', true],
            'peso sign and centavos' => ['Fixed bills take ₱19,156.00.', true],
            'bare digits' => ['Food is 3200 this month.', true],
            'percent' => ['That is 38% of what you spent.', true],
            'no numbers' => ['Review your subscriptions.', true],
            'invented peso amount' => ['You could save ₱5,000 a month.', false],
            'invented percent' => ['That is 40% of your income.', false],
            'one bad number among good ones' => ['Spend ₱3,200 on food, not ₱3,201.', false],
            'known digits inside a bigger number' => ['You have ₱125,050.', false],
        ];
    }

    #[DataProvider('tips')]
    public function test_validator_checks_every_number(string $tip, bool $ok)
    {
        $input = ['income' => 35000.0, 'fixed' => 19156.0, 'safe' => 1250.5, 'percent' => 38, 'categories' => [['name' => 'Food', 'amount' => 3200.0]]];

        $this->assertSame($ok, GenerateRecommendations::citesOnlyKnownNumbers($tip, $input));
    }

    public function test_generation_saves_the_valid_tips_only()
    {
        $user = $this->demo();
        $this->app->instance(AiProvider::class, new class extends FakeAiProvider
        {
            public function recommend(array $aggregates): array
            {
                return ["Safe per day is {$aggregates['safe_to_spend_per_day']}.", 'Save ₱99,999 now.', 'Review subscriptions.'];
            }
        });

        $saved = app(GenerateRecommendations::class)->handle($user);

        $this->assertCount(2, $saved);
        $this->assertSame(2, Recommendation::withoutGlobalScopes()->where('user_id', $user->id)->count());
        $this->assertNotContains('Save ₱99,999 now.', $saved->pluck('text')->all());
    }

    public function test_fake_provider_tips_all_pass_the_validator()
    {
        $user = $this->demo();
        Transaction::factory()->for($user)->create(['type' => 'expense', 'amount' => 123_400, 'occurred_at' => now(), 'card_id' => $user->cards()->first()->id]);

        $tips = app(GenerateRecommendations::class)->handle($user);

        $this->assertGreaterThanOrEqual(3, $tips->count());
        $this->assertStringContainsString('₱1,234.00', $tips->pluck('text')->implode(' '));
    }

    public function test_wrong_tip_count_is_rejected()
    {
        $user = $this->demo();
        $this->app->instance(AiProvider::class, new class extends FakeAiProvider
        {
            public function recommend(array $aggregates): array
            {
                return ['Only one.'];
            }
        });

        $this->expectException(DomainException::class);
        app(GenerateRecommendations::class)->handle($user);
    }

    public function test_blocked_without_consent()
    {
        $user = $this->demo(consent: false);

        $this->actingAs($user)->post(route('tips.store'))->assertForbidden();
        $this->assertSame(0, Recommendation::withoutGlobalScopes()->count());

        $this->expectException(AuthorizationException::class);
        app(GenerateRecommendations::class)->handle($user);
    }

    public function test_consent_endpoint_records_the_time_once()
    {
        $user = $this->demo(consent: false);

        $this->actingAs($user)->post(route('consent.ai'))->assertRedirect();
        $first = $user->fresh()->ai_consent_at;
        $this->assertNotNull($first);

        $this->travel(1)->hours();
        $this->post(route('consent.ai'));
        $this->assertEquals($first, $user->fresh()->ai_consent_at);
    }

    public function test_generate_then_dismiss_through_http()
    {
        $user = $this->demo();

        $this->actingAs($user)->post(route('tips.store'))->assertRedirect();
        $tip = Recommendation::first();
        $this->assertNotNull($tip);

        $this->post(route('tips.dismiss', $tip->id))->assertRedirect();
        $this->assertNotNull($tip->fresh()->dismissed_at);
    }

    public function test_recommendations_are_user_scoped()
    {
        [$a, $b] = User::factory(2)->create();
        $theirs = Recommendation::factory()->for($b)->create();

        $this->actingAs($a)->post(route('tips.dismiss', $theirs->id))->assertNotFound();
        $this->assertNull(Recommendation::withoutGlobalScopes()->find($theirs->id)->dismissed_at);
    }

    public function test_only_aggregates_reach_the_provider()
    {
        $user = $this->demo();
        $seen = [];
        $this->app->instance(AiProvider::class, new class($seen) extends FakeAiProvider
        {
            public function __construct(public array &$seen) {}

            public function recommend(array $aggregates): array
            {
                $this->seen = $aggregates;

                return ['A.', 'B.', 'C.'];
            }
        });
        Transaction::factory()->for($user)->create([
            'type' => 'expense', 'merchant' => 'Jollibee Secret Branch', 'note' => 'private note', 'amount' => 25_000,
            'occurred_at' => now(), 'card_id' => $user->cards()->first()->id,
        ]);

        app(GenerateRecommendations::class)->handle($user);

        $json = json_encode($seen);
        $this->assertNotEmpty($seen);
        foreach (['Jollibee', 'private note', 'Netflix', 'Spotify', 'Rent', 'Loan', $user->email, $user->name] as $leak) {
            $this->assertStringNotContainsString($leak, $json);
        }
    }

    public function test_gemini_provider_uses_structured_output_without_the_network()
    {
        config(['ai.providers.gemini.key' => 'test-key', 'services.gemini.model' => 'gemini-test']);
        StructuredAnonymousAgent::fake([['tips' => ['One.', 'Two.', 'Three.']]]);

        $this->assertSame(['One.', 'Two.', 'Three.'], (new GeminiProvider)->recommend(['income' => 1]));
        StructuredAnonymousAgent::assertPrompted(fn ($prompt) => $prompt->model === 'gemini-test');
    }

    public function test_gemini_provider_without_a_key_explains_itself()
    {
        config(['ai.providers.gemini.key' => null]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('GEMINI_API_KEY');
        (new GeminiProvider)->recommend([]);
    }

    public function test_artisan_command_reports_a_missing_key_without_a_stack_trace()
    {
        $this->demo();
        config(['ai.providers.gemini.key' => null]);
        $this->app->bind(AiProvider::class, GeminiProvider::class);

        $this->artisan('recommendations:generate', ['email' => 'demo@example.com'])
            ->expectsOutputToContain('GEMINI_API_KEY is not set')
            ->assertFailed();
    }

    public function test_tips_page_and_dashboard_show_only_undismissed_tips()
    {
        $user = $this->demo();
        Recommendation::factory()->for($user)->create(['text' => 'Visible tip']);
        Recommendation::factory()->for($user)->create(['text' => 'Gone tip', 'dismissed_at' => now()]);
        $this->actingAs($user);

        $this->get(route('tips.index'))->assertInertia(fn ($page) => $page->component('tips/Index')
            ->has('tips', 1)->where('tips.0.text', 'Visible tip')->where('consented', true));
        $this->get(route('dashboard'))->assertInertia(fn ($page) => $page->has('tips', 1)->has('forecast.series'));
    }
}
