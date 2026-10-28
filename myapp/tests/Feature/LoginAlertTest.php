<?php

namespace Tests\Feature;

use App\Mail\LoginAlertMail;
use App\Models\User;
use App\Services\LoginAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LoginAlertTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private const CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36';

    private const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Mail::fake();
        $this->owner = User::factory()->create();
        $this->owner->forceFill(['admin_role' => 'owner'])->save();
        $this->owner = $this->owner->fresh(); // reads the column defaults
    }

    /**
     * A test app keeps the after-response callbacks of every request and replays them on the next one, which a real
     * request never does. They are cleared after each request so the counts below are real.
     */
    private function afterRequest(): void
    {
        (new \ReflectionProperty($this->app, 'terminatingCallbacks'))->setValue($this->app, []);
    }

    private function signIn(User $user, string $ua = self::CHROME, string $ip = '203.0.113.7')
    {
        $response = $this->withHeaders(['User-Agent' => $ua])->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
        $this->afterRequest();

        return $response;
    }

    public function test_the_owner_gets_an_email_with_who_when_device_and_ip_and_a_link_to_the_user()
    {
        $ana = User::factory()->create(['name' => 'Ana Cruz']);

        $this->signIn($ana)->assertRedirect();

        Mail::assertSent(LoginAlertMail::class, 1);
        Mail::assertSent(LoginAlertMail::class, function ($m) use ($ana) {
            $html = $m->render();

            return $m->hasTo($this->owner->email) && $m->hasSubject('Sign-in: Ana Cruz (new device)')
                && str_contains($html, 'Ana Cruz just signed in') && str_contains($html, 'New device') && str_contains($html, 'Chrome on Windows')
                && str_contains($html, '203.0.113.7') && str_contains($html, 'Philippine time') && str_contains($html, route('admin.users.show', $ana))
                && str_contains($html, '/privacy-policy');
        });
        $this->assertDatabaseHas('login_devices', ['user_id' => $ana->id, 'label' => 'Chrome on Windows']);
    }

    public function test_a_reload_inside_ten_minutes_is_one_alert_then_a_known_device_is_not_new()
    {
        $ana = User::factory()->create();

        $this->signIn($ana);
        $this->post(route('logout'));
        $this->signIn($ana); // a second tab or reload: same browser and address
        Mail::assertSent(LoginAlertMail::class, 1);

        $this->post(route('logout'));
        $this->travel(11)->minutes();
        $this->signIn($ana);
        Mail::assertSent(LoginAlertMail::class, 2);
        Mail::assertSent(LoginAlertMail::class, fn ($m) => ! $m->newDevice); // the second one: seen before
    }

    public function test_new_devices_only_skips_a_usual_device_and_off_sends_nothing()
    {
        $ana = User::factory()->create();
        $this->owner->forceFill(['login_alerts' => 'new'])->save();

        $this->signIn($ana);                                  // new device: alert
        $this->post(route('logout'));
        $this->travel(11)->minutes();
        $this->signIn($ana);                                  // same device: no alert
        $this->post(route('logout'));
        Mail::assertSent(LoginAlertMail::class, 1);

        $this->signIn($ana, self::IPHONE, '198.51.100.4');    // another device: alert
        $this->post(route('logout'));
        Mail::assertSent(LoginAlertMail::class, 2);
        Mail::assertSent(LoginAlertMail::class, fn ($m) => $m->device === 'Safari on iPhone' && $m->newDevice);

        $this->owner->forceFill(['login_alerts' => 'off'])->save();
        $this->travel(11)->minutes();
        $this->signIn(User::factory()->create());
        Mail::assertSent(LoginAlertMail::class, 2);           // still 2
    }

    public function test_admins_own_sign_ins_support_staff_and_suspended_owners_are_left_out()
    {
        $support = User::factory()->create();
        $support->forceFill(['admin_role' => 'support'])->save();
        $suspended = User::factory()->create();
        $suspended->forceFill(['admin_role' => 'owner', 'suspended_at' => now()])->save();

        $this->signIn($this->owner);
        $this->post(route('logout'));
        $this->signIn($support);
        $this->post(route('logout'));
        Mail::assertNothingSent(); // admins signing in are not news

        $this->signIn(User::factory()->create());
        Mail::assertSent(LoginAlertMail::class, 1);                                   // only the active owner
        Mail::assertSent(LoginAlertMail::class, fn ($m) => $m->hasTo($this->owner->email));
        Mail::assertNotSent(LoginAlertMail::class, fn ($m) => $m->hasTo($support->email) || $m->hasTo($suspended->email));
    }

    public function test_a_mail_failure_never_reaches_the_user_who_signed_in()
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 1]);
        Mail::clearResolvedInstances();
        app('mail.manager')->purge();

        $ana = User::factory()->create();
        $this->signIn($ana)->assertRedirect();
        $this->assertAuthenticatedAs($ana);
    }

    public function test_the_owner_changes_the_setting_and_nobody_else_can()
    {
        $this->actingAs($this->owner)->withSession(['admin_gate' => [$this->owner->id, now()->timestamp]]);

        $this->get(route('admin.home'))->assertInertia(fn (Assert $p) => $p->where('loginAlerts', 'all'));
        $this->post(route('admin.login-alerts'), ['login_alerts' => 'new'])->assertSessionHasNoErrors();
        $this->assertSame('new', $this->owner->fresh()->login_alerts);
        $this->post(route('admin.login-alerts'), ['login_alerts' => 'sometimes'])->assertSessionHasErrors('login_alerts');
        $this->assertDatabaseHas('admin_audit', ['action' => 'changed_login_alerts']);

        $this->actingAs(User::factory()->create())->post(route('admin.login-alerts'), ['login_alerts' => 'off'])->assertNotFound();
    }

    public function test_the_device_label_reads_common_browsers()
    {
        $this->assertSame('Chrome on Windows', LoginAlert::device(self::CHROME));
        $this->assertSame('Safari on iPhone', LoginAlert::device(self::IPHONE));
        $this->assertSame('Firefox on Linux', LoginAlert::device('Mozilla/5.0 (X11; Linux x86_64; rv:127.0) Gecko/20100101 Firefox/127.0'));
        $this->assertSame('Edge on Windows', LoginAlert::device('Mozilla/5.0 (Windows NT 10.0) Chrome/126.0 Safari/537.36 Edg/126.0'));
        $this->assertSame('Browser on unknown device', LoginAlert::device(''));
    }
}
