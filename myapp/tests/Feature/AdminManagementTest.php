<?php

namespace Tests\Feature;

use App\Mail\SupportNotice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Mail::fake();
        $this->owner = $this->admin('owner', 'Boss');
        $this->actingAs($this->owner)->withSession(['admin_gate' => [$this->owner->id, now()->timestamp]]);
    }

    private function admin(string $role, string $name = 'Someone'): User
    {
        $u = User::factory()->create(['name' => $name, 'pin' => Hash::make('482913')]);
        $u->forceFill(['admin_role' => $role])->save();

        return $u->fresh();
    }

    private function candidate(array $extra = []): User
    {
        return User::factory()->create(['name' => 'Ana Cruz', 'pin' => Hash::make('739205'), ...$extra]);
    }

    public function test_the_page_lists_admins_with_role_last_gate_and_alert_setting()
    {
        $support = $this->admin('support', 'Sam');
        $support->forceFill(['last_admin_gate_at' => now()->subHour()])->save();

        $this->get(route('admin.admins'))->assertInertia(fn (Assert $p) => $p
            ->has('admins', 2)->where('admins.0.role', 'owner')->where('admins.0.me', true)->where('admins.0.login_alerts', 'all')
            ->where('admins.1.name', 'Sam')->where('admins.1.login_alerts', null)->where('admins.1.last_gate_at', fn ($v) => $v !== null));
    }

    public function test_passing_the_gate_records_when()
    {
        $support = $this->admin('support');
        Cache::put("admin-otp:{$support->id}", Hash::make('654321'), now()->addMinutes(10));

        $this->actingAs($support)->post(route('admin.gate.verify'), ['pin' => '482913', 'code' => '654321'])->assertRedirect();
        $this->assertNotNull($support->fresh()->last_admin_gate_at);
    }

    public function test_adding_an_admin_sets_the_role_logs_it_and_emails_the_person_and_every_owner()
    {
        $other = $this->admin('owner', 'Second Owner');
        $ana = $this->candidate();

        $this->post(route('admin.admins.store'), ['email' => $ana->email, 'role' => 'support'])->assertSessionHasNoErrors();

        $this->assertSame('support', $ana->fresh()->admin_role);
        $this->assertDatabaseHas('admin_audit', ['admin_id' => $this->owner->id, 'action' => 'added_admin', 'target_user_id' => $ana->id]);
        Mail::assertSent(SupportNotice::class, fn ($m) => $m->hasTo($ana->email) && str_contains($m->render(), 'support access') && str_contains($m->render(), '/gate'));
        foreach ([$this->owner, $other] as $o) {
            Mail::assertSent(SupportNotice::class, fn ($m) => $m->hasTo($o->email) && str_contains($m->headline, 'Admin change'));
        }
    }

    public function test_adding_is_refused_with_a_clear_reason_for_each_kind_of_account()
    {
        $post = fn ($email, $role = 'support') => $this->post(route('admin.admins.store'), ['email' => $email, 'role' => $role]);

        $post('nobody@example.com')->assertSessionHasErrors(['email' => 'No one has an account with that email. They need to sign up first.']);
        $post($this->candidate(['email_verified_at' => null])->email)->assertSessionHasErrors(['email' => 'They have not verified their email yet.']);
        $noPin = $this->candidate(['pin' => null]);
        $post($noPin->email)->assertSessionHasErrors('email');
        $this->assertStringContainsString('login PIN', session('errors')->first('email'));
        $suspended = $this->candidate();
        $suspended->forceFill(['suspended_at' => now()])->save();
        $post($suspended->email)->assertSessionHasErrors(['email' => 'That account is suspended.']);
        $post($this->owner->email)->assertSessionHasErrors(['email' => 'They are already an admin.']);
        $post($this->candidate(['email' => 'ok@example.com'])->email, 'boss')->assertSessionHasErrors('role');

        $this->assertSame(1, User::whereNotNull('admin_role')->count());
    }

    public function test_changing_a_role_and_removing_work_and_are_logged_and_emailed()
    {
        $sam = $this->admin('support', 'Sam');

        $this->put(route('admin.admins.update', $sam->id), ['role' => 'owner'])->assertSessionHasNoErrors();
        $this->assertSame('owner', $sam->fresh()->admin_role);
        $this->assertDatabaseHas('admin_audit', ['action' => 'changed_admin_role', 'target_user_id' => $sam->id]);

        $this->put(route('admin.admins.update', $sam->id), ['role' => 'support']);
        $this->delete(route('admin.admins.destroy', $sam->id))->assertRedirect();

        $fresh = $sam->fresh();
        $this->assertNull($fresh->admin_role);
        $this->assertDatabaseHas('admin_audit', ['action' => 'removed_admin', 'target_user_id' => $sam->id]);
        Mail::assertSent(SupportNotice::class, fn ($m) => $m->hasTo($sam->email) && str_contains($m->render(), 'removed'));
        Mail::assertSent(SupportNotice::class, fn ($m) => $m->hasTo($this->owner->email) && str_contains($m->headline, 'no longer an admin'));

        // they can still use their normal account; the admin is a 404 for them now, gate or not
        $this->actingAs($fresh)->withSession(['admin_gate' => [$fresh->id, now()->timestamp]])->get(route('admin.support'))->assertNotFound();
        $this->get(route('cards.index'))->assertOk();
    }

    public function test_you_cannot_change_or_remove_yourself_so_an_owner_always_remains()
    {
        $this->put(route('admin.admins.update', $this->owner->id), ['role' => 'support'])->assertStatus(422);
        $this->delete(route('admin.admins.destroy', $this->owner->id))->assertStatus(422);

        $this->assertSame('owner', $this->owner->fresh()->admin_role);
        $this->assertSame(1, User::where('admin_role', 'owner')->count());

        // an owner can change another owner, and the actor stays an owner
        $other = $this->admin('owner');
        $this->put(route('admin.admins.update', $other->id), ['role' => 'support'])->assertSessionHasNoErrors();
        $this->assertSame(1, User::where('admin_role', 'owner')->count());

        // a person who is not an admin cannot be "removed" or "changed" here
        $this->delete(route('admin.admins.destroy', $this->candidate()->id))->assertNotFound();
    }

    public function test_only_owners_can_open_any_of_it()
    {
        $support = $this->admin('support');
        $ana = $this->candidate();
        $this->actingAs($support)->withSession(['admin_gate' => [$support->id, now()->timestamp]]);

        $this->get(route('admin.admins'))->assertNotFound();
        $this->post(route('admin.admins.store'), ['email' => $ana->email, 'role' => 'owner'])->assertNotFound();
        $this->put(route('admin.admins.update', $support->id), ['role' => 'owner'])->assertNotFound();
        $this->assertSame('support', $support->fresh()->admin_role);
        $this->assertNull($ana->fresh()->admin_role);
    }
}
