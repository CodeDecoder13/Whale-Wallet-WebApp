<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Pod;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PodFundTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo('2026-03-10 09:00:00');
        $this->seed(DatabaseSeeder::class);
        $this->user = User::where('email', 'demo@example.com')->first();
        $this->actingAs($this->user);
    }

    public function test_adding_money_moves_it_from_the_card_with_its_reference_and_receipt()
    {
        $card = Card::first();
        $card->update(['balance' => 500_000]);
        $pod = Pod::create(['name' => 'Trip', 'target_amount' => 1_000_000, 'card_id' => $card->id]);
        $receipt = $this->user->receipts()->create(['purpose' => 'expense', 'status' => 'parsed', 'parsed_json' => []]);

        $this->post(route('pods.fund', $pod), ['card_id' => $card->id, 'amount' => '1500', 'paid_on' => '2026-03-09', 'reference' => 'GC-9', 'receipt_id' => $receipt->id])->assertRedirect();

        $this->assertSame([350_000, 150_000], [$card->fresh()->balance, $pod->fresh()->saved_amount]);
        $t = Transaction::where('to_pod_id', $pod->id)->sole();
        $this->assertSame(['GC-9', $receipt->id, '2026-03-09'], [$t->reference, $t->receipt_id, $t->occurred_at->toDateString()]);
        $this->assertSame('confirmed', $receipt->fresh()->status);

        $this->get(route('pods.index'))->assertInertia(fn ($page) => $page->where('pods.0.transfers.0.reference', 'GC-9')->where('pods.0.transfers.0.in', true));
    }

    public function test_it_refuses_an_empty_card_someone_elses_card_and_a_future_date()
    {
        $card = Card::first();
        $card->update(['balance' => 1_000]);
        $pod = Pod::create(['name' => 'Trip', 'target_amount' => 1_000_000]);
        $theirs = Card::factory()->for(User::factory()->create())->create();

        $this->post(route('pods.fund', $pod), ['card_id' => $card->id, 'amount' => '500', 'paid_on' => '2026-03-10'])->assertSessionHasErrors('amount');
        $this->post(route('pods.fund', $pod), ['card_id' => $theirs->id, 'amount' => '1', 'paid_on' => '2026-03-10'])->assertSessionHasErrors('card_id');
        $this->post(route('pods.fund', $pod), ['card_id' => $card->id, 'amount' => '1', 'paid_on' => '2026-04-01'])->assertSessionHasErrors('paid_on');
        $this->assertSame(0, $pod->fresh()->saved_amount);
    }

    public function test_the_mobile_api_adds_money_to_a_pod_in_centavos()
    {
        $card = Card::first();
        $card->update(['balance' => 500_000]);
        $pod = Pod::create(['name' => 'Trip', 'target_amount' => 1_000_000]);
        \Laravel\Sanctum\Sanctum::actingAs($this->user);

        $this->postJson("/api/v1/pods/{$pod->id}/fund", ['card_id' => $card->id, 'amount' => 150_000, 'paid_on' => '2026-03-09', 'reference' => 'GC-1'])
            ->assertCreated()->assertJsonPath('data.to_pod_id', $pod->id)->assertJsonPath('data.amount', 150_000)->assertJsonPath('data.reference', 'GC-1')->assertJsonPath('data.card_name', $card->name);

        $this->assertSame([350_000, 150_000], [$card->fresh()->balance, $pod->fresh()->saved_amount]);
        $this->postJson("/api/v1/pods/{$pod->id}/fund", ['card_id' => $card->id, 'amount' => 99_999_999, 'paid_on' => '2026-03-09'])->assertStatus(422);
    }

    public function test_a_pod_remembers_its_card()
    {
        $card = Card::first();
        $this->post(route('pods.store'), ['name' => 'Gadget', 'target_amount' => '5000', 'saved_amount' => '0', 'monthly_amount' => '0', 'card_id' => $card->id])->assertRedirect();

        $this->assertSame($card->id, Pod::where('name', 'Gadget')->value('card_id'));
    }
}
