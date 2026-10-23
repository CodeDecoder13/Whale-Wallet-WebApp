<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Mail\VerifyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Fortify\Features;
use Tests\TestCase;

class VerificationNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::emailVerification());
    }

    public function test_sends_verification_notification(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect(route('home'));

        Mail::assertSent(VerifyCode::class, fn ($m) => $m->hasTo($user->email) && preg_match('/^\d{6}$/', $m->code));
    }

    public function test_does_not_send_verification_notification_if_email_is_verified(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect(route('dashboard', absolute: false));

        Mail::assertNothingSent();
    }
}
