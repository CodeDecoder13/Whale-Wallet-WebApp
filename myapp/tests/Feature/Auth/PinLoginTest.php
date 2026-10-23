<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PinLoginTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return tap(User::factory()->create(), fn ($u) => $u->forceFill(['pin' => Hash::make('123456')])->save());
    }

    public function test_a_pin_logs_in_instead_of_the_password_and_the_password_still_works(): void
    {
        $user = $this->user();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => '123456', 'mode' => 'pin'])->assertRedirect();
        $this->assertAuthenticatedAs($user);

        auth()->logout();
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_pin_is_not_a_password_and_a_password_is_not_a_pin(): void
    {
        $user = $this->user();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => '123456'])->assertSessionHasErrors();
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password', 'mode' => 'pin'])->assertSessionHasErrors();
        $this->assertGuest();
    }

    public function test_five_wrong_pins_lock_the_pin_but_not_the_password(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class); // the login rate limit would trip first; this tests the PIN's own lock
        $user = $this->user();

        foreach (range(1, 5) as $i) {
            $this->post(route('login.store'), ['email' => $user->email, 'password' => '000000', 'mode' => 'pin']);
        }
        $this->post(route('login.store'), ['email' => $user->email, 'password' => '123456', 'mode' => 'pin'])->assertSessionHasErrors();
        $this->assertGuest();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_setting_and_removing_a_pin_from_settings(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

        $this->put(route('user-pin.update'), ['pin' => '654321', 'pin_confirmation' => '654321'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('654321', $user->fresh()->pin));

        $this->put(route('user-pin.update'), ['pin' => '12ab', 'pin_confirmation' => '12ab'])->assertSessionHasErrors('pin');

        $this->put(route('user-pin.update'), [])->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->pin);
    }
}