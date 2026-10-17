<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Category;
use App\Models\Pod;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TransactionEditTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function transfer(string $from, string $to, int $pesos): Transaction
    {
        $this->post(route('transactions.transfer'), ['from' => $from, 'to' => $to, 'amount' => $pesos])->assertRedirect();

        return Transaction::latest('id')->first();
    }

    public function test_delete_expense_restores_balance()
    {
        $card = Card::factory()->for($this->user)->create(['balance' => 100_000]);
        $this->post(route('transactions.store'), ['merchant' => 'X', 'amount' => '250', 'card_id' => $card->id, 'occurred_at' => '2026-03-01', 'source' => 'manual']);
        $this->assertSame(75_000, $card->fresh()->balance);

        $this->delete(route('transactions.destroy', Transaction::first()))->assertRedirect();

        $this->assertSame(100_000, $card->fresh()->balance);
        $this->assertSame(0, Transaction::count());
    }

    public function test_delete_transfer_reverses_every_direction()
    {
        $a = Card::factory()->for($this->user)->create(['balance' => 100_000]);
        $b = Card::factory()->for($this->user)->create(['balance' => 100_000]);
        $pod = Pod::factory()->for($this->user)->create(['saved_amount' => 50_000]);

        foreach ([["card:{$a->id}", "card:{$b->id}"], ["card:{$a->id}", "pod:{$pod->id}"], ["pod:{$pod->id}", "card:{$b->id}"]] as [$from, $to]) {
            $t = $this->transfer($from, $to, 100);
            $this->delete(route('transactions.destroy', $t));
        }

        $this->assertSame([100_000, 100_000, 50_000], [$a->fresh()->balance, $b->fresh()->balance, $pod->fresh()->saved_amount]);
        $this->assertSame(0, Transaction::count());
    }

    public function test_edit_amount_and_card_adjusts_both_cards()
    {
        $old = Card::factory()->for($this->user)->create(['balance' => 100_000]);
        $new = Card::factory()->for($this->user)->create(['balance' => 100_000]);
        $t = Transaction::factory()->for($this->user)->create(['card_id' => $old->id, 'amount' => 20_000]);
        $old->decrement('balance', 20_000);

        $this->put(route('transactions.update', $t), ['merchant' => 'Y', 'amount' => '300', 'card_id' => $new->id, 'occurred_at' => '2026-03-01', 'source' => 'manual'])->assertRedirect();
        $this->assertSame([100_000, 70_000, 30_000], [$old->fresh()->balance, $new->fresh()->balance, $t->fresh()->amount]);

        $this->put(route('transactions.update', $t), ['merchant' => 'Y', 'amount' => '100', 'card_id' => $new->id, 'occurred_at' => '2026-03-01', 'source' => 'manual']);
        $this->assertSame(90_000, $new->fresh()->balance);
    }

    public function test_transfers_cannot_be_edited()
    {
        $a = Card::factory()->for($this->user)->create(['balance' => 100_000]);
        $pod = Pod::factory()->for($this->user)->create();
        $t = $this->transfer("card:{$a->id}", "pod:{$pod->id}", 10);

        $this->put(route('transactions.update', $t), ['merchant' => 'Y', 'amount' => '1', 'card_id' => $a->id, 'occurred_at' => '2026-03-01', 'source' => 'manual'])->assertStatus(422);
    }

    public function test_another_users_transaction_is_404()
    {
        $theirs = Transaction::factory()->create();
        $card = Card::factory()->for($this->user)->create();

        $this->put(route('transactions.update', $theirs), ['merchant' => 'Y', 'amount' => '1', 'card_id' => $card->id, 'occurred_at' => '2026-03-01', 'source' => 'manual'])->assertNotFound();
        $this->delete(route('transactions.destroy', $theirs))->assertNotFound();
        $this->assertModelExists($theirs);
    }

    public function test_category_crud_and_delete_keeps_transactions()
    {
        $this->post(route('categories.store'), ['name' => 'Pets'])->assertRedirect();
        $cat = Category::sole();
        $this->put(route('categories.update', $cat), ['name' => 'Pet care']);
        $this->assertSame('Pet care', $cat->fresh()->name);
        $this->post(route('categories.store'), ['name' => 'Pet care'])->assertSessionHasErrors('name');

        $t = Transaction::factory()->for($this->user)->create(['category_id' => $cat->id]);
        $this->delete(route('categories.destroy', $cat));

        $this->assertNull($t->fresh()->category_id);
        $this->get(route('categories.index'))->assertInertia(fn (Assert $p) => $p->has('categories', 0));
    }

    public function test_categories_are_per_user()
    {
        $theirs = Category::factory()->create();

        $this->put(route('categories.update', $theirs), ['name' => 'x'])->assertNotFound();
        $this->delete(route('categories.destroy', $theirs))->assertNotFound();
        $this->post(route('categories.store'), ['name' => $theirs->name])->assertSessionHasNoErrors(); // same name as another user's is fine
    }

    public function test_markdown_export()
    {
        $card = Card::factory()->for($this->user)->create(['name' => 'GCash']);
        Transaction::factory()->for($this->user)->create(['card_id' => $card->id, 'merchant' => 'A|B', 'amount' => 125_050]);
        Transaction::factory()->create(); // another user's row

        $md = $this->get(route('transactions.export-md'))->assertOk()->streamedContent();

        $this->assertStringContainsString('| date | type | merchant | amount | card |', $md);
        $this->assertStringContainsString('A\|B | 1250.50 | GCash', $md);
        $this->assertSame(3, substr_count(trim($md), "\n") + 1); // header, divider, one row
    }

    public function test_card_limit_inputs_for_the_80_percent_warning()
    {
        $card = Card::factory()->for($this->user)->create(['monthly_limit' => 100_000]);
        Transaction::factory()->for($this->user)->create(['card_id' => $card->id, 'amount' => 80_000, 'occurred_at' => now()]);
        Transaction::factory()->for($this->user)->create(['card_id' => $card->id, 'amount' => 50_000, 'occurred_at' => now()->subMonths(2)]);

        $this->get(route('cards.index'))->assertInertia(fn (Assert $p) => $p
            ->where('cards.0.spent', 80_000)->where('cards.0.monthly_limit', 100_000));
    }
}
