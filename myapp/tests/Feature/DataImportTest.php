<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Category;
use App\Models\IncomeSource;
use App\Models\Pod;
use App\Models\RecurringItem;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DataImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /** A source account with one of everything, exported as the user would download it. */
    private function exportOf(): string
    {
        $a = User::factory()->create(['pay_dates' => [15, 30], 'pay_cycle' => 'semi_monthly', 'keep_receipts' => true, 'digest_enabled' => false, 'digest_time' => '09:00']);
        $food = Category::factory()->for($a)->create(['name' => 'Food']);
        $gcash = Card::factory()->for($a)->create(['name' => 'GCash', 'type' => 'ewallet', 'balance' => 500_000, 'account_number' => '0917 123 4567']);
        $bdo = Card::factory()->for($a)->create(['name' => 'BDO', 'type' => 'bank', 'balance' => 900_000]);
        Pod::factory()->for($a)->create(['name' => 'Trip', 'saved_amount' => 20_000, 'card_id' => $gcash->id]);
        $bill = RecurringItem::factory()->for($a)->create(['name' => 'Converge', 'amount' => 160_000, 'cadence' => 'monthly', 'kind' => 'bill', 'card_id' => $bdo->id, 'category_id' => $food->id]);
        Transaction::factory()->for($a)->create(['card_id' => $gcash->id, 'category_id' => $food->id, 'merchant' => 'Jollibee', 'amount' => 25_000, 'type' => 'expense', 'occurred_at' => '2026-03-02 13:30:00', 'reference' => 'GC-1', 'recurring_item_id' => $bill->id]);
        Transaction::factory()->for($a)->create(['card_id' => $gcash->id, 'to_card_id' => $bdo->id, 'type' => 'transfer', 'amount' => 10_000, 'merchant' => null, 'category_id' => null, 'occurred_at' => '2026-03-03 09:00:00']);
        IncomeSource::factory()->for($a)->create(['name' => 'Salary', 'amount' => 2_800_000, 'month' => '2026-03', 'received_on' => '2026-03-05', 'reference' => 'REF-9', 'card_id' => $bdo->id]);

        return $this->actingAs($a)->get('/settings/data/export')->getContent();
    }

    private function import(User $to, string $json)
    {
        return $this->actingAs($to)->post('/settings/data/import', ['file' => UploadedFile::fake()->createWithContent('whale-wallet-export.json', $json)]);
    }

    public function test_an_export_loads_into_another_account_with_everything_linked()
    {
        $json = $this->exportOf();
        $b = User::factory()->create();

        $this->import($b, $json)->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($b);

        $this->assertSame(['BDO', 'GCash'], Card::orderBy('name')->pluck('name')->all());
        $gcash = Card::where('name', 'GCash')->first();
        $this->assertSame([500_000, '0917 123 4567'], [$gcash->balance, $gcash->account_number]); // balance as exported, not re-added from history
        $this->assertSame($gcash->id, Pod::where('name', 'Trip')->first()->card_id);

        $bill = RecurringItem::where('name', 'Converge')->first();
        $this->assertSame([Card::where('name', 'BDO')->first()->id, Category::where('name', 'Food')->first()->id], [$bill->card_id, $bill->category_id]);

        $jollibee = Transaction::where('merchant', 'Jollibee')->first();
        $this->assertSame([$gcash->id, $bill->id, '2026-03-02 13:30:00'], [$jollibee->card_id, $jollibee->recurring_item_id, $jollibee->occurred_at->format('Y-m-d H:i:s')]);
        $this->assertSame(Card::where('name', 'BDO')->first()->id, Transaction::where('type', 'transfer')->first()->to_card_id);

        $salary = IncomeSource::where('reference', 'REF-9')->first();
        $this->assertSame([Card::where('name', 'BDO')->first()->id, null], [$salary->card_id, $salary->receipt_id]);
        $this->assertSame([15, 30], $b->fresh()->pay_dates);
        $this->assertSame(['09:00', true], [$b->fresh()->digest_time, $b->fresh()->keep_receipts]);

        // the file carries the settings, every table the import reads, and the card number in plain text (the new server has its own key)
        $data = json_decode($json, true);
        $this->assertSame(['whale-wallet', 1], [$data['format'], $data['version']]);
        $this->assertEqualsCanonicalizing(['name', 'email', 'pay_dates', 'digest_enabled', 'digest_time', 'keep_receipts'], array_keys($data['user']));
        foreach (['cards', 'pods', 'categories', 'recurring_items', 'transactions', 'income_sources'] as $table) {
            $this->assertNotEmpty($data[$table], $table);
        }
    }

    public function test_importing_the_same_file_twice_adds_nothing_the_second_time()
    {
        $json = $this->exportOf();
        $b = User::factory()->create();

        $this->import($b, $json);
        $counts = fn () => [Card::count(), Pod::count(), Category::count(), RecurringItem::count(), Transaction::count(), IncomeSource::count()];
        $first = $counts();

        $this->import($b, $json)->assertSessionHasNoErrors();

        $this->assertSame($first, $counts());
    }

    public function test_it_never_touches_another_users_data_or_balances()
    {
        $json = $this->exportOf();
        $b = User::factory()->create();
        $mine = Card::factory()->for($b)->create(['name' => 'GCash', 'type' => 'ewallet', 'balance' => 111]);

        $this->import($b, $json);

        $this->assertSame(111, $mine->fresh()->balance); // an existing card is matched, its balance is left alone
        $this->assertSame(1, Card::withoutGlobalScopes()->where('user_id', $b->id)->where('name', 'GCash')->count());
    }

    public function test_a_bad_file_imports_nothing()
    {
        $b = User::factory()->create();

        $this->import($b, 'not json')->assertSessionHasErrors('file');
        $this->import($b, '{"hello": "world"}')->assertSessionHasErrors('file');
        $this->import($b, json_encode(['cards' => [['id' => 1, 'name' => 'X', 'type' => 'piggy', 'balance' => 5]]]))->assertSessionHasErrors('file');

        $this->actingAs($b);
        $this->assertSame(0, Card::count());
    }

    /** What the dashboard adds up from an account, so two accounts can be compared like prod and dev. */
    private function figures(): array
    {
        return [\App\Http\Controllers\DashboardController::netWorth(), (new \App\Services\BudgetSummary)->for(auth()->user()), auth()->user()->fresh()->pay_dates];
    }

    private function source(): array
    {
        $a = User::factory()->create(['pay_dates' => [21], 'pay_cycle' => 'monthly']);
        $card = Card::factory()->for($a)->create(['name' => 'GoTyme', 'type' => 'bank', 'balance' => 500_000]);
        // alike rows must all come across: two loans with the same name and amount, two purchases in the same minute
        foreach ([1, 2] as $_) {
            RecurringItem::factory()->for($a)->create(['name' => 'Loan', 'amount' => 300_000, 'cadence' => 'monthly', 'kind' => 'debt', 'installments' => 6, 'next_due_on' => now()->addDays(5)->toDateString(), 'card_id' => $card->id]);
            Transaction::factory()->for($a)->create(['card_id' => $card->id, 'merchant' => 'Parking', 'amount' => 5_000, 'type' => 'expense', 'occurred_at' => '2026-03-02 13:30:00']);
        }
        $this->actingAs($a);

        return [$this->figures(), $this->get('/settings/data/export')->getContent()];
    }

    public function test_alike_rows_all_come_across_and_the_dashboard_adds_up_the_same()
    {
        [$before, $json] = $this->source();
        $b = User::factory()->create();

        $this->import($b, $json)->assertSessionHasNoErrors();
        $this->actingAs($b);

        $this->assertSame($before, $this->figures());
        $this->assertSame([2, 2], [RecurringItem::count(), Transaction::count()]);

        $this->import($b, $json); // again: still two of each
        $this->assertSame([2, 2], [RecurringItem::count(), Transaction::count()]);
    }

    public function test_replace_makes_an_account_that_already_has_data_an_exact_copy()
    {
        [$before, $json] = $this->source();

        // the target already has its own payday, a stray loan and a card of its own: a merge would keep all of it
        $b = User::factory()->create(['pay_dates' => [22], 'pay_cycle' => 'monthly']);
        $stray = Card::factory()->for($b)->create(['name' => 'Old', 'balance' => 99]);
        RecurringItem::factory()->for($b)->create(['name' => 'Stray loan', 'amount' => 1_000_000, 'kind' => 'debt', 'installments' => 12, 'next_due_on' => now()->addDays(3)->toDateString(), 'card_id' => $stray->id]);

        $this->import($b, $json)->assertSessionHasNoErrors();
        $this->actingAs($b);
        $this->assertNotSame($before, $this->figures()); // merge: not the same

        $this->actingAs($b)->post('/settings/data/import', ['replace' => true, 'file' => UploadedFile::fake()->createWithContent('x.json', $json)])->assertSessionHasNoErrors();

        $this->assertSame($before, $this->figures());
        $this->assertSame(['GoTyme'], Card::pluck('name')->all());
    }
}
