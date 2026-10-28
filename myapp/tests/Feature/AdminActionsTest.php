<?php

namespace Tests\Feature;

use App\Mail\VerifyCode;
use App\Models\AdminAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['admin_role' => 'owner'])->save();
        $this->actingAs($this->admin)->withSession(['admin_gate' => [$this->admin->id, now()->timestamp]]);
    }

    public function test_suspending_signs_the_user_out_everywhere_blocks_every_way_back_in_and_is_audited()
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);
        DB::table('sessions')->insert(['id' => 's1', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp]);
        $user->createToken('phone');

        $this->post(route('admin.users.suspend', $user))->assertRedirect();

        $this->assertNotNull($user->fresh()->suspended_at);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseHas('admin_audit', ['admin_id' => $this->admin->id, 'action' => 'suspended_user', 'target_user_id' => $user->id]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'suspended', 'user_id' => $user->id]);

        // web: the right password says the account is suspended, a wrong one stays generic
        auth()->logout();
        $this->post(route('login.store'), ['email' => 'ana@example.com', 'password' => 'password'])->assertSessionHasErrors(['email' => 'This account is suspended. Contact support if you think this is a mistake.']);
        $this->assertGuest();
        $this->post(route('login.store'), ['email' => 'ana@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertStringNotContainsString('suspended', session('errors')->first('email'));

        // an old session that survived is ended on its next request
        $this->actingAs($user->fresh())->get(route('cards.index'))->assertRedirect('/login');
        $this->assertGuest();

        // mobile API login
        $this->postJson('/api/v1/auth/login', ['email' => 'ana@example.com', 'password' => 'password', 'device_name' => 'phone'])->assertForbidden();
    }

    public function test_unsuspending_lets_them_sign_in_again()
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);
        $user->forceFill(['suspended_at' => now()])->save();

        $this->post(route('admin.users.unsuspend', $user))->assertRedirect();
        $this->assertNull($user->fresh()->suspended_at);
        $this->assertDatabaseHas('admin_audit', ['action' => 'unsuspended_user', 'target_user_id' => $user->id]);

        auth()->logout();
        $this->post(route('login.store'), ['email' => 'ana@example.com', 'password' => 'password'])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_sign_out_everywhere_and_resend_code()
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();
        DB::table('sessions')->insert(['id' => 's1', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp]);

        $this->post(route('admin.users.sign-out', $user))->assertRedirect();
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertNull($user->fresh()->suspended_at); // signing out does not suspend

        $this->post(route('admin.users.resend-code', $user))->assertRedirect();
        $this->assertTrue(Cache::has("otp:{$user->id}"));
        Mail::assertSent(VerifyCode::class, fn ($m) => $m->hasTo($user->email));

        $verified = User::factory()->create();
        $this->post(route('admin.users.resend-code', $verified))->assertStatus(422);

        foreach (['signed_out_user', 'resent_verification_code'] as $action) {
            $this->assertDatabaseHas('admin_audit', ['action' => $action, 'target_user_id' => $user->id]);
        }
    }

    public function test_actions_never_reach_an_admin_account_not_even_your_own_and_are_owner_only()
    {
        $other = User::factory()->create();
        $other->forceFill(['admin_role' => 'support'])->save();

        $this->post(route('admin.users.suspend', $this->admin))->assertStatus(422);
        $this->post(route('admin.users.suspend', $other))->assertStatus(422);
        $this->assertNull($other->fresh()->suspended_at);

        $this->actingAs($other)->withSession(['admin_gate' => [$other->id, now()->timestamp]]);
        $this->post(route('admin.users.suspend', User::factory()->create()))->assertNotFound();
        $this->get(route('admin.audit'))->assertNotFound();
    }

    public function test_audit_page_lists_and_filters_and_has_no_way_to_edit_or_delete()
    {
        $ana = User::factory()->create();
        $ben = User::factory()->create();
        AdminAudit::record($this->admin, 'viewed_user', $ana);
        AdminAudit::record($this->admin, 'suspended_user', $ben, ['reason' => 'test']);

        $this->get(route('admin.audit'))->assertInertia(fn (Assert $p) => $p
            ->has('entries.data', 2)->where('entries.data.0.action', 'suspended_user')->where('entries.data.0.target.name', $ben->name)->where('actions', ['suspended_user', 'viewed_user']));
        $this->get(route('admin.audit', ['action' => 'viewed_user']))->assertInertia(fn (Assert $p) => $p->has('entries.data', 1));
        $this->get(route('admin.audit', ['user' => $ben->id]))->assertInertia(fn (Assert $p) => $p->has('entries.data', 1)->where('entries.data.0.meta.reason', 'test'));

        $this->delete('/admin/audit/1')->assertStatus(404);
        $this->post(route('admin.audit'))->assertStatus(405);
    }

    public function test_the_privacy_pages_say_staff_can_see_user_data_and_that_it_is_logged()
    {
        $policy = file_get_contents(resource_path('js/pages/legal/Privacy.vue'));
        $this->assertStringContainsString('Who at Whale Wallet can see your data', $policy);
        $this->assertStringContainsString('chats', $policy);
        $this->assertStringContainsString('cannot be edited', $policy);
        $this->assertStringContainsString('28 October 2026', file_get_contents(resource_path('js/pages/privacy/Changes.vue')));
        $this->assertStringContainsString('every access is logged', file_get_contents(resource_path('js/components/AiConsent.vue')));
    }
}
