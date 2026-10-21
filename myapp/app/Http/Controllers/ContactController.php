<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
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
            ContactMessage::create($d);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Thanks, your message was sent.']);

        return back();
    }
}
