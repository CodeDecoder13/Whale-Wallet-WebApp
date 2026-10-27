<?php

namespace Tests\Feature;

use App\Mail\VerifyCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function admin(string $role = 'owner'): User
    {
        $user = User::factory()->create(['pin' => Hash::make('482913')]);
        $user->forceFill(['admin_role' => $role])->save(); // not mass-assignable on purpose

        return $user;
    }

    private function giveCode(User $admin, string $code = '654321'): void
    {
        Cache::put("admin-otp:{$admin->id}", Hash::make($code), now()->addMinutes(10));
    }

    public function test_anyone_who_is_not_an_admin_gets_a_404_and_guests_are_sent_to_login()
    {
        $this->get('/admin')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/admin')->assertNotFound();
        $this->get('/admin/gate')->assertNotFound();
    }

    public function test_an_admin_without_the_gate_is_sent_to_it_and_with_it_gets_in_for_two_hours()
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin')->assertRedirect(route('admin.gate'));

        $this->giveCode($admin);
        $this->post(route('admin.gate.verify'), ['pin' => '482913', 'code' => '654321'])->assertRedirect(route('admin.home'));
        $this->get('/admin')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->travel(119)->minutes();
        $this->get('/admin')->assertOk();

        $this->travel(2)->minutes(); // past two hours
        $this->get('/admin')->assertRedirect(route('admin.gate'));
    }

    public function test_the_gate_needs_both_the_pin_and_the_code_and_locks_after_five_misses()
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $this->giveCode($admin);

        $this->post(route('admin.gate.verify'), ['pin' => '000000', 'code' => '654321'])->assertSessionHasErrors('code');
        $this->post(route('admin.gate.verify'), ['pin' => '482913', 'code' => '000000'])->assertSessionHasErrors('code');
        $this->get('/admin')->assertRedirect(route('admin.gate'));

        foreach (range(1, 3) as $_) {
            $this->post(route('admin.gate.verify'), ['pin' => '000000', 'code' => '000000']);
        }
        // locked: even the right pair is refused now
        $this->post(route('admin.gate.verify'), ['pin' => '482913', 'code' => '654321'])->assertSessionHasErrors('code');
        $this->get('/admin')->assertRedirect(route('admin.gate'));
    }

    public function test_a_code_works_once()
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $this->giveCode($admin);

        $this->post(route('admin.gate.verify'), ['pin' => '482913', 'code' => '654321'])->assertRedirect(route('admin.home'));
        $this->flushSession();
        $this->post(route('admin.gate.verify'), ['pin' => '482913', 'code' => '654321'])->assertSessionHasErrors('code');
    }

    public function test_requesting_a_code_emails_the_admin_and_needs_a_pin()
    {
        Mail::fake();
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.gate.code'))->assertSessionHasNoErrors();
        Mail::assertSent(VerifyCode::class, fn ($m) => $m->hasTo($admin->email) && $m->purpose === 'admin' && str_contains($m->render(), 'open the admin area'));

        $noPin = $this->admin();
        $noPin->forceFill(['pin' => null])->save();
        $this->actingAs($noPin)->post(route('admin.gate.code'))->assertSessionHasErrors('pin');
    }

    public function test_a_suspended_admin_is_locked_out_and_the_command_makes_an_owner_without_storing_the_pin_in_git()
    {
        $admin = $this->admin();
        $admin->forceFill(['suspended_at' => now()])->save();
        $this->actingAs($admin)->get('/admin/gate')->assertNotFound();

        $user = User::factory()->create(['email' => 'boss@example.com']);
        putenv('ADMIN_PIN=123456');
        Artisan::call('admin:make', ['email' => 'boss@example.com']);
        putenv('ADMIN_PIN');

        $this->assertSame('owner', $user->fresh()->admin_role);
        $this->assertTrue(Hash::check('123456', $user->fresh()->pin));
        $this->assertFalse(in_array('admin_role', (new User)->getFillable(), true));
    }
}
