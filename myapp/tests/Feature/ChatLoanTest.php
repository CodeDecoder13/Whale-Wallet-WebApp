<?php

namespace Tests\Feature;

use App\Enums\RecurringKind;
use App\Models\RecurringItem;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ChatLoanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo('2026-03-10 09:00:00');
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'demo@example.com')->first();
        $user->forceFill(['ai_consent_at' => now()])->save();
        $this->actingAs($user);
    }

    private function say(string $text): TestResponse
    {
        return $this->postJson(route('chat.messages'), ['text' => $text])->assertOk();
    }

    public function test_a_loan_asks_when_it_is_due_then_saves_only_on_confirm()
    {
        $before = RecurringItem::count();

        $r = $this->say('I have a loan to my brother 1k I need to pay it one time only');
        $this->assertSame(['loan', 100_000, 1, ['due']], [$r->json('draft.kind'), $r->json('draft.amount'), $r->json('draft.payments'), $r->json('draft.missing')]);
        $this->assertStringContainsString('due', $r->json('reply'));

        $r = $this->say('Oct 30');
        $this->assertSame(['2026-10-30', []], [$r->json('draft.occurred_at'), $r->json('draft.missing')]);
        $this->assertSame($before, RecurringItem::count());

        $r = $this->say('make it Nov 5');
        $this->assertSame('2026-11-05', $r->json('draft.occurred_at'));

        $this->postJson(route('chat.confirm', $r->json('id')))->assertOk();

        $loan = RecurringItem::latest('id')->first();
        $this->assertSame(['brother', 100_000, RecurringKind::Debt, 1, 'monthly', '2026-11-05'], [$loan->name, $loan->amount, $loan->kind, $loan->installments, $loan->cadence, $loan->next_due_on->toDateString()]);
        $this->postJson(route('chat.confirm', $r->json('id')))->assertStatus(422);
    }

    public function test_a_loan_without_a_term_or_date_asks_for_both()
    {
        $r = $this->say('utang to my sister 6000');
        $this->assertSame(['due', 'payments'], $r->json('draft.missing'));

        $r = $this->say('30');
        $this->assertSame(['2026-03-30', ['payments']], [$r->json('draft.occurred_at'), $r->json('draft.missing')]);
        $this->assertContains('6 months', $r->json('quick_replies'));

        $r = $this->say('6 months');
        $this->assertSame([6, '2026-03-30', []], [$r->json('draft.payments'), $r->json('draft.occurred_at'), $r->json('draft.missing')]);
    }
}
