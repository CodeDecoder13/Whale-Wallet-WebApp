<?php

namespace Tests\Feature;

use App\Actions\HandleChatMessage;
use App\Jobs\ParseReceipt;
use App\Models\Card;
use App\Models\IncomeSource;
use App\Models\Receipt;
use App\Models\User;
use App\Services\BudgetSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IncomeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo('2026-03-10 09:00:00');
        Storage::fake('local');
    }

    private function user(): User
    {
        return User::factory()->create(['ai_consent_at' => now()]);
    }

    private function proof(User $user, array $attrs = []): Receipt
    {
        return $user->receipts()->create($attrs + ['status' => 'parsed', 'purpose' => 'income', 'path' => "receipts/{$user->id}/p.jpg", 'parsed_json' => []]);
    }

    private function body(Receipt $r, array $over = []): array
    {
        return $over + ['receipt_id' => $r->id, 'amount' => '28000', 'received_on' => '2026-03-05', 'reference' => 'REF-001'];
    }

    public function test_received_income_replaces_the_baseline_for_that_month_only()
    {
        $user = $this->user();
        IncomeSource::factory()->create(['user_id' => $user->id, 'name' => 'Income', 'amount' => 3_500_000]);
        $this->actingAs($user);

        $this->assertSame(3_500_000, (new BudgetSummary)->for($user)['income']);

        $this->post(route('income.store'), $this->body($this->proof($user)))->assertRedirect();
        $this->post(route('income.store'), $this->body($this->proof($user), ['amount' => '1500.50', 'name' => 'Bonus', 'reference' => 'REF-002']))->assertRedirect();
        $this->post(route('income.store'), $this->body($this->proof($user), ['amount' => '99999', 'received_on' => '2026-02-27', 'reference' => 'REF-003']))->assertRedirect();

        $this->assertSame(2_950_050, (new BudgetSummary)->for($user)['income']);
        $salary = IncomeSource::where('reference', 'REF-001')->sole();
        $this->assertSame(['Salary', '2026-03', 'confirmed'], [$salary->name, $salary->month, Receipt::find($salary->receipt_id)->status]);
    }

    public function test_a_proof_a_unique_reference_and_a_parsed_income_receipt_are_required()
    {
        [$user, $other] = [$this->user(), $this->user()];
        $this->actingAs($user);

        $this->post(route('income.store'), $this->body($this->proof($other)))->assertSessionHasErrors('receipt_id'); // someone else's proof
        $this->post(route('income.store'), $this->body($this->proof($user), ['reference' => '']))->assertSessionHasErrors('reference');
        $this->post(route('income.store'), $this->body($this->proof($user), ['received_on' => '2026-04-01']))->assertSessionHasErrors('received_on');

        $this->post(route('income.store'), $this->body($this->proof($user)))->assertRedirect();
        $this->post(route('income.store'), $this->body($this->proof($user)))->assertSessionHasErrors(['reference' => 'This reference number was already logged.']);

        $this->post(route('income.store'), $this->body($this->proof($user, ['purpose' => 'expense']), ['reference' => 'REF-9']))->assertStatus(409);
        $this->post(route('income.store'), $this->body($this->proof($user, ['status' => 'failed']), ['reference' => 'REF-8']))->assertStatus(409);
        $this->assertSame(1, IncomeSource::whereNotNull('month')->count());
    }

    public function test_removing_income_deletes_its_proof_and_baseline_cannot_be_removed()
    {
        $user = $this->user();
        $baseline = IncomeSource::factory()->create(['user_id' => $user->id, 'name' => 'Income', 'amount' => 1]);
        $proof = $this->proof($user);
        Storage::disk('local')->put($proof->path, 'x');
        $this->actingAs($user)->post(route('income.store'), $this->body($proof));
        $row = IncomeSource::where('reference', 'REF-001')->sole();

        $this->delete(route('income.destroy', $baseline->id))->assertNotFound();
        $this->delete(route('income.destroy', $row->id))->assertRedirect();

        Storage::disk('local')->assertMissing($proof->path);
        $this->assertSame(0, Receipt::where('purpose', 'income')->count());
        $this->assertSame(1, IncomeSource::count());
    }

    public function test_income_proofs_stay_out_of_the_receipts_page_and_cannot_be_confirmed_as_expenses()
    {
        $user = $this->user();
        $proof = $this->proof($user);

        $this->actingAs($user)->get(route('transactions.index'))->assertInertia(fn ($page) => $page->has('receipts', 0));
        $this->get(route('dashboard'))->assertRedirect(); // no card yet: onboarding comes first
        $this->postJson(route('receipts.confirm', $proof->id), ['merchant' => 'x', 'amount' => '1', 'occurred_at' => '2026-03-01'])->assertStatus(422);
    }

    public function test_income_can_be_typed_in_without_a_screenshot_or_reference()
    {
        $user = $this->user();
        $this->actingAs($user);

        $this->post(route('income.store'), ['amount' => '1200', 'received_on' => '2026-03-05', 'name' => 'Freelance'])->assertRedirect();

        $row = IncomeSource::where('name', 'Freelance')->sole();
        $this->assertSame([120_000, '2026-03', null, null], [$row->amount, $row->month, $row->reference, $row->receipt_id]);

        // a proof still needs its reference number
        $this->post(route('income.store'), ['receipt_id' => $this->proof($user)->id, 'amount' => '1', 'received_on' => '2026-03-05'])->assertSessionHasErrors('reference');
    }

    public function test_forecast_counts_side_income_that_landed_outside_the_cash_cards()
    {
        $user = $this->user();
        Card::factory()->for($user)->create(['balance' => 100_000]);
        $credit = Card::factory()->for($user)->create(['type' => 'credit', 'balance' => 0]);
        $user->update(['pay_dates' => [30]]);
        $this->actingAs($user);

        $before = app(\App\Services\Forecast\ForecastLoader::class)->for($user)['series'][0]['balance'];
        $this->post(route('income.store'), ['kind' => 'side', 'amount' => '500', 'received_on' => '2026-03-05', 'card_id' => $credit->id])->assertRedirect();

        $this->assertSame($before + 50_000, app(\App\Services\Forecast\ForecastLoader::class)->for($user)['series'][0]['balance']);
    }

    public function test_side_income_is_added_on_top_and_never_replaces_the_baseline()
    {
        $user = $this->user();
        IncomeSource::factory()->create(['user_id' => $user->id, 'name' => 'Income', 'amount' => 3_500_000]);
        $this->actingAs($user);

        $this->post(route('income.store'), ['kind' => 'side', 'amount' => '500', 'received_on' => '2026-03-05'])->assertRedirect();
        $this->assertSame(3_550_000, (new BudgetSummary)->for($user)['income']); // baseline + side
        $this->assertSame('Side income', IncomeSource::where('kind', 'side')->sole()->name);

        $this->post(route('income.store'), $this->body($this->proof($user), ['amount' => '30000']))->assertRedirect();
        $this->assertSame(3_050_000, (new BudgetSummary)->for($user)['income']); // salary replaces the baseline, side stays
    }

    public function test_side_money_typed_in_chat_is_filed_as_side_income()
    {
        $this->actingAs($this->user());

        $r = $this->postJson(route('chat.messages'), ['text' => 'got side hustle money 800'])->assertOk();
        $this->postJson(route('chat.confirm', $r->json('id')))->assertOk();

        $this->assertSame(['side', 80_000], [IncomeSource::whereNotNull('month')->sole()->kind, IncomeSource::whereNotNull('month')->sole()->amount]);
    }

    public function test_income_goes_into_the_chosen_card_and_comes_back_out_when_removed()
    {
        $user = $this->user();
        $card = Card::factory()->for($user)->create(['balance' => 100_000]);
        $this->actingAs($user);

        $this->post(route('income.store'), ['amount' => '500', 'received_on' => '2026-03-05', 'kind' => 'side'])->assertSessionHasErrors('card_id'); // a card is required once you have one
        $this->post(route('income.store'), ['amount' => '500', 'received_on' => '2026-03-05', 'kind' => 'side', 'card_id' => $card->id])->assertRedirect();
        $this->assertSame(150_000, $card->fresh()->balance);

        $this->delete(route('income.destroy', IncomeSource::where('kind', 'side')->sole()->id))->assertRedirect();
        $this->assertSame(100_000, $card->fresh()->balance);
    }

    public function test_chat_asks_which_card_received_the_income()
    {
        $user = $this->user();
        [$gcash] = [Card::factory()->for($user)->create(['name' => 'GCash', 'balance' => 0]), Card::factory()->for($user)->create(['name' => 'BDO', 'balance' => 0])];
        $this->actingAs($user);

        $r = $this->postJson(route('chat.messages'), ['text' => 'side hustle 800'])->assertOk();
        $this->assertSame(['card'], $r->json('draft.missing'));
        $this->assertEqualsCanonicalizing(['GCash', 'BDO'], $r->json('quick_replies'));

        $r = $this->postJson(route('chat.messages'), ['text' => 'GCash'])->assertOk();
        $this->assertSame([[], 80_000, 'GCash'], [$r->json('draft.missing'), $r->json('draft.amount'), $r->json('draft.card')]);
        $this->postJson(route('chat.confirm', $r->json('id')))->assertOk();
        $this->assertSame(80_000, $gcash->fresh()->balance);
    }

    public function test_a_typed_salary_in_chat_saves_with_one_tap()
    {
        $user = $this->user();
        $this->actingAs($user);

        $r = $this->postJson(route('chat.messages'), ['text' => 'I got my salary 25k'])->assertOk();
        $this->postJson(route('chat.confirm', $r->json('id')))->assertOk();

        $row = IncomeSource::whereNotNull('month')->sole();
        $this->assertSame([2_500_000, '2026-03'], [$row->amount, $row->month]);
        $this->postJson(route('chat.confirm', $r->json('id')))->assertStatus(422); // not twice
    }

    public function test_chat_asks_how_much_then_for_a_screenshot_and_files_it_as_a_salary_proof()
    {
        Queue::fake();
        $this->actingAs($this->user());

        $r = $this->postJson(route('chat.messages'), ['text' => 'I got my salary today'])->assertOk();
        $this->assertSame(['income', ['amount']], [$r->json('draft.kind'), $r->json('draft.missing')]);
        $this->assertStringContainsString('How much', $r->json('reply'));

        $r = $this->postJson(route('chat.messages'), ['text' => '25k'])->assertOk();
        $this->assertSame([2_500_000, []], [$r->json('draft.amount'), $r->json('draft.missing')]);
        $this->assertStringContainsString('screenshot', $r->json('reply'));

        $r = $this->postJson(route('chat.messages'), ['image' => UploadedFile::fake()->image('transfer.png')])->assertOk()->assertJsonPath('income', true);
        $proof = Receipt::findOrFail($r->json('receipt_id'));
        $this->assertSame(['income', 2_500_000], [$proof->purpose, $proof->claimed_amount]);
        Queue::assertPushed(ParseReceipt::class);

        // an ordinary receipt afterwards is still an expense
        $this->postJson(route('chat.messages'), ['text' => 'hello'])->assertOk();
        $r = $this->postJson(route('chat.messages'), ['image' => UploadedFile::fake()->image('r.png')])->assertJsonPath('income', false);
        $this->assertSame('expense', Receipt::findOrFail($r->json('receipt_id'))->purpose);
    }

    public function test_an_amount_in_the_first_message_skips_the_question_and_is_never_saved_as_an_expense()
    {
        $this->actingAs($this->user());

        $r = $this->postJson(route('chat.messages'), ['text' => 'received my 13th month pay 30,000'])->assertOk();
        $this->assertSame([3_000_000, []], [$r->json('draft.amount'), $r->json('draft.missing')]);
        $this->postJson(route('chat.confirm', $r->json('id')))->assertOk();
        $this->assertSame(3_000_000, IncomeSource::whereNotNull('month')->sole()->amount);
        $this->assertSame(0, \App\Models\Transaction::count()); // income, not an expense
    }
}
