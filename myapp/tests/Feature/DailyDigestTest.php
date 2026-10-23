<?php

namespace Tests\Feature;

use App\Mail\DailyDigest;
use App\Models\User;
use App\Services\DailyDigest as Digest;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DailyDigestTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
        $this->user = User::where('email', 'demo@example.com')->first();
        $this->user->forceFill(['ai_consent_at' => now(), 'email_verified_at' => now(), 'digest_enabled' => true])->save();
        Mail::fake();
    }

    public function test_the_default_is_nine_am_philippine_time_and_it_goes_out_once_a_day()
    {
        $this->assertSame('09:00', $this->user->fresh()->digest_time);

        $this->travelTo('2026-03-10 00:50:00'); // 08:50 in Manila: not yet
        $this->assertSame(['refreshed' => 0, 'emailed' => 0], app(Digest::class)->run());

        $this->travelTo('2026-03-10 01:02:00'); // 09:02 in Manila
        $this->assertSame(['refreshed' => 1, 'emailed' => 1], app(Digest::class)->run());
        Mail::assertSent(DailyDigest::class, fn ($m) => $m->hasTo($this->user->email) && $m->tips->count() >= 3);
        Mail::assertSent(DailyDigest::class, fn ($m) => str_contains($m->render(), '/mascot/happy.png') && str_contains($m->render(), $m->tips->first()->text));
        $this->assertSame('2026-03-10', $this->user->fresh()->digest_sent_on->toDateString());
        $this->assertGreaterThanOrEqual(3, $this->user->recommendations()->whereNull('dismissed_at')->count(), 'the dashboard shows the same tips');

        $this->assertSame(['refreshed' => 0, 'emailed' => 0], app(Digest::class)->run());

        $this->travelTo('2026-03-11 01:02:00'); // the next day
        $this->assertSame(1, app(Digest::class)->run()['emailed']);
    }

    public function test_a_user_who_did_not_opt_in_still_gets_fresh_tips_but_no_email()
    {
        $this->user->forceFill(['digest_enabled' => false])->save();
        $this->travelTo('2026-03-10 01:02:00');

        $this->assertSame(['refreshed' => 1, 'emailed' => 0], app(Digest::class)->run());
        Mail::assertNothingSent();
    }

    public function test_no_ai_consent_or_unverified_email_means_no_tips_or_no_email()
    {
        $this->travelTo('2026-03-10 01:02:00');

        $this->user->forceFill(['email_verified_at' => null])->save();
        $this->assertSame(['refreshed' => 1, 'emailed' => 0], app(Digest::class)->run());

        $this->user->forceFill(['ai_consent_at' => null, 'digest_sent_on' => null])->save();
        $this->assertSame(['refreshed' => 0, 'emailed' => 0], app(Digest::class)->run());
    }

    public function test_the_time_and_opt_in_are_set_from_settings_and_validated()
    {
        $this->actingAs($this->user);

        $this->patch(route('profile.digest'), ['digest_enabled' => true, 'digest_time' => '7:5'])->assertSessionHasErrors('digest_time');
        $this->patch(route('profile.digest'), ['digest_enabled' => false, 'digest_time' => '18:30'])->assertRedirect();

        $this->assertSame([false, '18:30'], [$this->user->fresh()->digest_enabled, $this->user->fresh()->digest_time]);

        $this->travelTo('2026-03-10 01:02:00'); // 09:02 in Manila, before the 18:30 the user picked
        $this->assertSame(0, app(Digest::class)->run()['refreshed']);
    }
}
