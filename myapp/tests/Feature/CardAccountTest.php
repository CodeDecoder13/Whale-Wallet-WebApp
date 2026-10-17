<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CardAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_details_are_saved_encrypted_and_validated()
    {
        $this->actingAs(User::factory()->create());
        $card = ['name' => 'RCBC', 'type' => 'bank', 'balance' => '100', 'account_name' => 'Rhuzzel Paramio'];

        $this->post(route('cards.store'), [...$card, 'account_number' => 'abc'])->assertSessionHasErrors('account_number');
        $this->post(route('cards.store'), [...$card, 'qr' => 'https://evil.test/qr.png'])->assertSessionHasErrors('qr');
        $this->post(route('cards.store'), [...$card, 'account_number' => '1234 5678-90', 'qr' => 'data:image/png;base64,AAAA'])->assertRedirect();

        $saved = Card::sole();
        $this->assertSame(['Rhuzzel Paramio', '1234 5678-90'], [$saved->account_name, $saved->account_number]);
        $this->assertStringNotContainsString('1234', (string) DB::table('cards')->value('account_number'));
    }
}
