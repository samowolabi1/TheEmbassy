<?php

namespace App\Http\Controllers;

use App\Mail\NewContactMessage;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

class ContactController extends Controller
{
    public const SUBJECTS = ['General question', 'Visiting for the first time', 'Counselling or prayer', 'Partnership or sponsorship', 'Media or press', 'Something else'];

    public function create(): View
    {
        return view('pages.contact', ['subjects' => self::SUBJECTS]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'subject' => ['required', 'in:'.implode(',', self::SUBJECTS)],
            'message' => ['required', 'string', 'max:3000'],
        ]);

        // A hidden "website" field that people never see; bots that fill it in are quietly ignored.
        if (! $request->filled('website')) {
            $message = ContactMessage::create($data);

            if ($to = config('church.email')) {
                try {
                    Mail::to($to)->send(new NewContactMessage($message));
                } catch (Throwable $e) {
                    Log::error('Could not email contact message', ['message' => $message->id, 'error' => $e->getMessage()]);
                }
            }
        }

        return redirect()->route('contact')->with('sent', true);
    }
}
