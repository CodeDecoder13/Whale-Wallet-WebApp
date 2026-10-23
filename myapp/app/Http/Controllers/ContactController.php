<?php

namespace App\Http\Controllers;

use App\Mail\ContactReceived;
use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Throwable;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public const TOPICS = ['Support', 'Feedback', 'Privacy request', 'Business', 'Other'];

    public function show(): Response
    {
        return Inertia::render('Contact', ['topics' => self::TOPICS]);
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:120'],
            'topic' => ['required', Rule::in(self::TOPICS)],
            'message' => ['required', 'string', 'max:4000'],
        ]);

        // honeypot: real people leave the hidden "website" field empty; bots get the same thank-you and nothing is stored
        if (! $request->filled('website')) {
            $message = ContactMessage::create($d);

            // the message is already stored, so a mail outage must not turn the thank-you into an error
            try {
                Mail::to(config('mail.contact_to'))->send(new ContactReceived($message));
            } catch (Throwable $e) {
                report($e);
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Thanks, your message was sent.']);

        return back();
    }
}
