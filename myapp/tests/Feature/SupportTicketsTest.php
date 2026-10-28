<?php

namespace Tests\Feature;

use App\Mail\SupportNotice;
use App\Models\SupportMessage;
use App\Models\SupportThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupportTicketsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Mail::fake();
        Storage::fake();
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['admin_role' => 'owner'])->save();
    }

    private function asAdmin(): static
    {
        return $this->actingAs($this->admin)->withSession(['admin_gate' => [$this->admin->id, now()->timestamp]]);
    }

    public function test_a_user_with_no_ticket_gets_topic_templates_and_picking_one_names_the_ticket()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('support.index'))->assertInertia(fn (Assert $p) => $p
            ->where('ticket', null)->has('templates', 6)->where('templates.0.key', 'balance')->where('templates.0.label', 'My safe-to-spend or balance looks wrong'));

        $this->post(route('support.store'), ['body' => "My safe-to-spend looks wrong.\nWhat I see: 0", 'template' => 'balance'])->assertRedirect();
        $this->assertSame('My safe-to-spend or balance looks wrong', SupportThread::sole()->subject);

        // no template: they just write, and the first words are the subject
        $free = User::factory()->create();
        $this->actingAs($free)->post(route('support.store'), ['body' => 'My cat walked on my phone and now I see ten wallets']);
        $this->assertSame('My cat walked on my phone and now I see ten wallets', SupportThread::withoutGlobalScopes()->where('user_id', $free->id)->sole()->subject);

        $this->post(route('support.store'), ['body' => 'x', 'template' => 'nonsense'])->assertSessionHasErrors('template');
    }

    public function test_either_side_can_attach_a_picture_or_pdf_and_only_the_right_people_can_open_it()
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('support.store'), ['body' => 'See my screen', 'file' => UploadedFile::fake()->image('screen.png', 200, 200)])->assertSessionHasNoErrors();

        $mine = SupportMessage::withoutGlobalScopes()->sole();
        $this->assertSame(['screen.png', 'image/png'], [$mine->attachment_name, $mine->attachment_mime]);
        Storage::assertExists($mine->attachment_path);

        // the user sees it; another user cannot; a guest is sent to log in
        $this->get(route('support.file', $mine->id))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('support.index'))->assertInertia(fn (Assert $p) => $p->where('messages.0.file.name', 'screen.png')->where('messages.0.file.image', true));
        $this->actingAs(User::factory()->create())->get(route('support.file', $mine->id))->assertNotFound();

        // the admin replies with a PDF; the user can open it, the admin opens the user's picture through the logged route
        $thread = SupportThread::withoutGlobalScopes()->sole();
        $this->asAdmin()->post(route('admin.support.reply', $thread->id), ['body' => 'Here is a guide', 'file' => UploadedFile::fake()->create('guide.pdf', 100, 'application/pdf')])->assertSessionHasNoErrors();
        $reply = SupportMessage::withoutGlobalScopes()->where('from_admin', true)->sole();
        $this->get(route('admin.support.file', $mine->id))->assertOk();
        $this->assertDatabaseHas('admin_audit', ['action' => 'opened_support_file', 'target_user_id' => $user->id]);

        $this->actingAs($user)->get(route('support.file', $reply->id))->assertOk()->assertHeader('Content-Disposition');
        $this->assertStringContainsString('attachment', $this->get(route('support.file', $reply->id))->headers->get('Content-Disposition')); // a PDF downloads, a picture shows

        // a message can be only a file
        $this->post(route('support.store'), ['file' => UploadedFile::fake()->image('only.jpg')])->assertSessionHasNoErrors();
        $this->assertSame(3, SupportMessage::withoutGlobalScopes()->count());
    }

    public function test_only_pictures_and_pdfs_up_to_5_mb_are_accepted_and_a_message_needs_text_or_a_file()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post(route('support.store'), ['body' => 'x', 'file' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')])->assertSessionHasErrors('file');
        $this->post(route('support.store'), ['body' => 'x', 'file' => UploadedFile::fake()->create('script.html', 10, 'text/html')])->assertSessionHasErrors('file');
        $this->post(route('support.store'), ['body' => 'x', 'file' => UploadedFile::fake()->create('big.pdf', 5121, 'application/pdf')])->assertSessionHasErrors('file');
        $this->post(route('support.store'), ['body' => '  '])->assertSessionHasErrors(['body', 'file']);
        $this->assertSame(0, SupportMessage::withoutGlobalScopes()->count());
        $this->post(route('support.store'), ['body' => 'x', 'file' => UploadedFile::fake()->create('ok.pdf', 5120, 'application/pdf')])->assertSessionHasNoErrors();
    }

    public function test_the_user_ends_a_ticket_and_the_admin_keeps_every_message_and_the_user_can_start_a_new_one()
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('support.store'), ['body' => 'First problem']);
        $first = SupportThread::withoutGlobalScopes()->sole();
        $this->asAdmin()->post(route('admin.support.reply', $first->id), ['body' => 'Fixed it']);

        $this->actingAs($user)->post(route('support.close', $first->id))->assertRedirect(route('support.index', ['new' => 1]));
        $first->refresh();
        $this->assertSame(['closed', 'user'], [$first->status, $first->closed_by]);
        $this->assertNotNull($first->closed_at);

        // the user's page: no open ticket, templates again, and the old ticket in their history
        $this->get(route('support.index'))->assertInertia(fn (Assert $p) => $p->where('ticket', null)->has('history', 1)->where('history.0.status', 'closed')->has('templates', 6));
        $this->get(route('support.index', ['ticket' => $first->id]))->assertInertia(fn (Assert $p) => $p->where('ticket.status', 'closed')->has('messages', 2));

        // the admin still has all of it, under Closed
        $this->asAdmin()->get(route('admin.support', ['filter' => 'closed', 'thread' => $first->id]))->assertInertia(fn (Assert $p) => $p
            ->has('threads', 1)->where('open.closed_by', 'user')->has('messages', 2)->where('messages.0.body', 'First problem'));
        $this->assertSame(2, SupportMessage::withoutGlobalScopes()->count());

        // a new problem is a new ticket; the old one is untouched
        $this->actingAs($user)->post(route('support.store'), ['body' => 'Second problem', 'template' => 'receipt']);
        $this->assertSame(2, SupportThread::withoutGlobalScopes()->count());
        $this->assertSame('closed', $first->fresh()->status);
        $this->assertSame(1, SupportThread::where('status', 'open')->count());
        Mail::assertSent(SupportNotice::class, fn ($m) => str_contains($m->headline, 'New support ticket from'));
    }

    public function test_a_user_cannot_end_someone_elses_ticket_and_the_admin_can_close_and_reopen_with_who_recorded()
    {
        $ana = User::factory()->create();
        $ben = User::factory()->create();
        $this->actingAs($ana)->post(route('support.store'), ['body' => 'Hello']);
        $thread = SupportThread::withoutGlobalScopes()->sole();

        $this->actingAs($ben)->post(route('support.close', $thread->id))->assertNotFound();
        $this->assertSame('open', $thread->fresh()->status);

        $this->asAdmin()->post(route('admin.support.status', $thread->id), ['status' => 'closed']);
        $this->assertSame(['closed', 'admin'], [$thread->fresh()->status, $thread->fresh()->closed_by]);
        $this->actingAs($ana)->get(route('support.index', ['ticket' => $thread->id]))->assertInertia(fn (Assert $p) => $p->where('ticket.closed_by', 'admin'));

        $this->asAdmin()->post(route('admin.support.status', $thread->id), ['status' => 'open']);
        $this->assertSame([null, null], [$thread->fresh()->closed_by, $thread->fresh()->closed_at]);
        $this->assertSame('open', $thread->fresh()->status);
    }

    public function test_closing_never_deletes_anything_and_files_stay_available_to_the_admin()
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('support.store'), ['body' => 'Receipt', 'file' => UploadedFile::fake()->image('r.png')]);
        $thread = SupportThread::withoutGlobalScopes()->sole();
        $message = SupportMessage::withoutGlobalScopes()->sole();

        $this->post(route('support.close', $thread->id));

        Storage::assertExists($message->attachment_path);
        $this->asAdmin()->get(route('admin.support.file', $message->id))->assertOk();
    }
}
