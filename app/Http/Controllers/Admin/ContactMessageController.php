<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Messages from the Contact page.
 */
class ContactMessageController extends Controller
{
    public function index(Request $request): View
    {
        $unreadOnly = $request->query('show') === 'unread';

        return view('admin.messages', [
            'messages' => ContactMessage::query()->when($unreadOnly, fn ($q) => $q->whereNull('read_at'))->latest()->paginate(20)->withQueryString(),
            'filter' => $unreadOnly ? 'unread' : 'all',
            'counts' => ['all' => ContactMessage::count(), 'unread' => ContactMessage::whereNull('read_at')->count()],
        ]);
    }

    public function update(Request $request, ContactMessage $message): RedirectResponse
    {
        $message->forceFill(['read_at' => $request->boolean('read') ? now() : null])->save();

        return back();
    }

    public function destroy(ContactMessage $message): RedirectResponse
    {
        $message->delete();

        return back()->with('status', 'Message deleted.');
    }
}
