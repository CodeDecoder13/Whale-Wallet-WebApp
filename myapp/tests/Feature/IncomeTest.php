<?php

namespace Tests\Feature;

use App\Actions\HandleChatMessage;
use App\Jobs\ParseReceipt;
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

        $this->post(route('income.store'), ['amount' => '100', 'received_on' => '2026-03-05', 'reference' => 'X'])->assertSessionHasErrors('receipt_id');
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

        $this->actingAs($user)->get(route('receipts.index'))->assertInertia(fn ($page) => $page->has('receipts', 0));
        $this->get(route('dashboard'))->assertRedirect(); // no card yet: onboarding comes first
        $this->postJson(route('receipts.confirm', $proof->id), ['merchant' => 'x', 'amount' => '1', 'occurred_at' => '2026-03-01'])->assertStatus(422);
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
        $this->postJson(route('chat.confirm', $r->json('id')))->assertStatus(422);
    }
}
