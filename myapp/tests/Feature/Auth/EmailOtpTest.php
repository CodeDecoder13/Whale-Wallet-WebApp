<?php

namespace Tests\Feature\Auth;

use App\Mail\VerifyCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailOtpTest extends TestCase
{
    use RefreshDatabase;

    private function sentCode(User $user): string
    {
        Mail::fake();
        $user->sendEmailVerificationNotification();
        $code = null;
        Mail::assertSent(VerifyCode::class, function ($m) use (&$code) {
            $code = $m->code;

            return true;
        });

        return $code;
    }

    public function test_registering_emails_a_code_and_the_right_code_verifies_and_opens_the_dashboard(): void
    {
        Mail::fake();
        $this->post(route('register.store'), ['name' => 'Ana Cruz', 'email' => 'ana@example.com', 'password' => 'password-123!', 'password_confirmation' => 'password-123!'])->assertRedirect();
        $user = User::where('email', 'ana@example.com')->sole();
        Mail::assertSent(VerifyCode::class, fn ($m) => $m->hasTo('ana@example.com'));

        $code = $this->sentCode($user);
        $this->actingAs($user)->post(route('verification.otp'), ['code' => $code])->assertRedirect(route('dashboard'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_a_wrong_code_is_rejected_and_five_misses_lock_the_code(): void
    {
        $user = User::factory()->unverified()->create();
        $code = $this->sentCode($user);
        $wrong = $code === '000000' ? '111111' : '000000';

        $this->actingAs($user)->post(route('verification.otp'), ['code' => $wrong])->assertSessionHasErrors('code');
        foreach (range(1, 4) as $i) {
            $this->post(route('verification.otp'), ['code' => $wrong]);
        }
        $this->post(route('verification.otp'), ['code' => $code])->assertSessionHasErrors('code'); // locked: send a new one
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }
}