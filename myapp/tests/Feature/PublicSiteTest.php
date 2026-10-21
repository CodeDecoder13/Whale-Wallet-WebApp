<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    private const PAGES = ['home', 'about', 'pricing', 'docs', 'privacy', 'privacy.ai', 'privacy.security', 'privacy.processors', 'privacy.changes', 'privacy.policy', 'terms', 'cookies', 'contact'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_every_public_page_opens_for_guests_and_signed_in_users(): void
    {
        foreach (self::PAGES as $name) {
            $this->get(route($name))->assertOk();
            $this->actingAs(User::factory()->create())->get(route($name))->assertOk();
            auth()->logout();
        }
    }

    public function test_sitemap_lists_every_page_and_security_txt_has_a_contact(): void
    {
        $sitemap = $this->get('/sitemap.xml')->assertOk()->getContent();
        foreach (self::PAGES as $name) {
            $this->assertStringContainsString(route($name), $sitemap);
        }

        $this->get('/.well-known/security.txt')->assertOk()->assertSee('Contact: mailto:support@whalewallet.cloud');
    }

    public function test_responses_carry_security_headers(): void
    {
        $this->get(route('about'))->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Referrer-Policy');
    }

    public function test_contact_form_stores_a_message(): void
    {
        $this->post(route('contact.store'), ['name' => 'Ana', 'email' => 'ana@example.com', 'topic' => 'Support', 'message' => 'Hello'])->assertRedirect();

        $this->assertSame(1, ContactMessage::where('email', 'ana@example.com')->count());
    }

    public function test_contact_form_validates_and_ignores_bots(): void
    {
        $this->post(route('contact.store'), ['name' => 'Ana', 'email' => 'nope', 'topic' => 'Spam', 'message' => ''])
            ->assertSessionHasErrors(['email', 'topic', 'message']);

        $this->post(route('contact.store'), ['name' => 'Bot', 'email' => 'bot@example.com', 'topic' => 'Other', 'message' => 'buy', 'website' => 'http://x'])->assertRedirect();
        $this->assertSame(0, ContactMessage::count());
    }

    public function test_contact_form_is_throttled(): void
    {
        $d = ['name' => 'Ana', 'email' => 'ana@example.com', 'topic' => 'Other', 'message' => 'Hi'];
        foreach (range(1, 5) as $_) {
            $this->post(route('contact.store'), $d)->assertRedirect();
        }

        $this->post(route('contact.store'), $d)->assertTooManyRequests();
    }
}
