<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResetAccountTest extends TestCase
{
    use RefreshDatabase;

    private function userWithData(): array
    {
        $user = User::factory()->create(['pin' => Hash::make('123456')]);
        $card = Card::factory()->for($user)->create();
        Transaction::factory()->for($user)->create(['card_id' => $card->id]);
        Cache::put("reset-otp:{$user->id}", Hash::make('654321'), now()->addMinutes(10));

        return [$user, Card::factory()->create()]; // the second card belongs to someone else
    }

    public function test_reset_needs_both_the_pin_and_the_emailed_code_then_wipes_only_this_user()
    {
        [$user, $other] = $this->userWithData();
        $this->actingAs($user);

        $this->post(route('account-reset.store'), ['pin' => '000000', 'code' => '654321'])->assertSessionHasErrors('code');
        $this->post(route('account-reset.store'), ['pin' => '123456', 'code' => '000000'])->assertSessionHasErrors('code');
        $this->assertSame(1, Card::count());

        $this->post(route('account-reset.store'), ['pin' => '123456', 'code' => '654321'])->assertRedirect(route('dashboard'));

        $this->assertSame(0, Transaction::count());
        $this->assertSame(0, $user->cards()->count());
        $this->assertSame(count(Category::DEFAULTS), $user->categories()->count());
        $this->assertModelExists($other);
        $this->assertNotNull($user->fresh()->pin); // the login survives
    }

    public function test_a_code_only_works_once_and_a_user_without_a_pin_cannot_ask_for_one()
    {
        [$user] = $this->userWithData();
        $this->actingAs($user);
        $this->post(route('account-reset.store'), ['pin' => '123456', 'code' => '654321']);
        $this->post(route('account-reset.store'), ['pin' => '123456', 'code' => '654321'])->assertSessionHasErrors('code');

        $this->actingAs(User::factory()->create())->post(route('account-reset.code'))->assertStatus(422);
    }
}
