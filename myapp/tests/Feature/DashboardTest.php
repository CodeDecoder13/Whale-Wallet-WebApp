<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        Card::factory()->for($user)->create(); // users without cards are sent to onboarding
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_welcome_back_shows_once_per_login()
    {
        $user = User::factory()->create(['password' => bcrypt('P@ssw0rd12')]);
        Card::factory()->for($user)->create();

        $this->get(route('dashboard'))->assertRedirect(); // signed out
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'P@ssw0rd12']);

        $this->get(route('dashboard'))->assertInertia(fn ($page) => $page->where('welcome', true));
        $this->get(route('dashboard'))->assertInertia(fn ($page) => $page->where('welcome', false));
    }
}
