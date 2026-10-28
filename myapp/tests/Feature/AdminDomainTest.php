<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** ADMIN_DOMAIN set: the admin lives on its own host and only there. */
class AdminDomainTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'admin.example.test';

    protected function setUp(): void
    {
        putenv('ADMIN_DOMAIN='.self::HOST); // before the app boots, because the routes are registered from it
        parent::setUp();
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        putenv('ADMIN_DOMAIN');
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['admin_role' => 'owner'])->save();

        return $admin;
    }

    private function onAdmin(string $path): string
    {
        return 'http://'.self::HOST.$path;
    }

    public function test_the_admin_routes_use_the_admin_host_and_the_main_host_has_no_admin()
    {
        // host and path only: the test environment's APP_URL adds a port
        $this->assertSame([self::HOST, '/users', '/'], [parse_url(route('admin.users'), PHP_URL_HOST), parse_url(route('admin.users'), PHP_URL_PATH), parse_url(route('admin.home'), PHP_URL_PATH) ?? '/']);

        $this->actingAs($this->admin())->withSession(['admin_gate' => [1, now()->timestamp]]);
        $this->get('http://localhost/admin')->assertNotFound();
        $this->get('http://localhost/admin/users')->assertNotFound();
    }

    public function test_a_guest_on_the_admin_host_is_sent_to_log_in_on_that_host()
    {
        $this->get($this->onAdmin('/'))->assertRedirect($this->onAdmin('/login'));
        $this->get($this->onAdmin('/login'))->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_the_admin_host_serves_the_admin_and_not_the_user_app_and_the_main_host_still_does()
    {
        $admin = $this->admin();
        $this->actingAs($admin)->withSession(['admin_gate' => [$admin->id, now()->timestamp]]);

        // '/' and '/transactions' exist on both hosts; each host answers with its own page
        $this->get($this->onAdmin('/'))->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertInertia(fn ($p) => $p->component('admin/Home'));
        $this->get($this->onAdmin('/transactions'))->assertOk()->assertInertia(fn ($p) => $p->component('admin/Transactions'));
        $this->get('http://localhost/')->assertInertia(fn ($p) => $p->component('Welcome'));

        // the user app and sign-up are not served on the admin host
        $this->get($this->onAdmin('/dashboard'))->assertRedirect('/');
        $this->get($this->onAdmin('/register'))->assertRedirect('/');
        $this->get($this->onAdmin('/settings/profile'))->assertRedirect('/');
    }

    public function test_the_admin_host_has_its_own_login_page_that_only_admins_can_use()
    {
        $this->get($this->onAdmin('/login'))->assertInertia(fn ($p) => $p->component('admin/Login'));
        $this->get('http://localhost/login')->assertInertia(fn ($p) => $p->component('auth/Login'));

        $normal = User::factory()->create();
        $this->post($this->onAdmin('/login'), ['email' => $normal->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $admin = $this->admin();
        $this->post($this->onAdmin('/login'), ['email' => $admin->email, 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($admin);

        // the same normal user still signs in on the main site
        auth()->logout();
        $this->post('http://localhost/login', ['email' => $normal->email, 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($normal);
    }

    public function test_the_gate_works_on_the_admin_host_and_a_normal_user_gets_a_404_there()
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get($this->onAdmin('/'))->assertRedirect($this->onAdmin('/gate'));
        $this->get($this->onAdmin('/gate'))->assertOk();

        $this->actingAs(User::factory()->create())->get($this->onAdmin('/'))->assertNotFound();
    }
}
