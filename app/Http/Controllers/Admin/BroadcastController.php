<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Broadcast;
use App\Services\Messaging\Broadcaster;
use App\Services\Messaging\WebPush;
use App\Services\Messaging\WhatsApp;
use App\Support\Notify;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Sending an announcement beyond the website: email, WhatsApp, app notification, or sharing to a WhatsApp group.
 */
class BroadcastController extends Controller
{
    public function create(Announcement $announcement, Broadcaster $broadcaster, WhatsApp $whatsapp, WebPush $push): View
    {
        $link = $announcement->url ?: route('home');
        $default = Str::limit($announcement->title.': '.$announcement->body, 400 - mb_strlen($link), '…').' '.$link;

        return view('admin.broadcast', [
            'announcement' => $announcement,
            'message' => $default,
            'reach' => $broadcaster->reach(),
            'ready' => $this->ready($whatsapp, $push),
            'history' => Broadcast::with('creator')->where('announcement_id', $announcement->id)->latest()->get(),
            'recent' => Broadcast::with('announcement', 'creator')->latest()->take(6)->get(),
        ]);
    }

    public function store(Request $request, Announcement $announcement, WhatsApp $whatsapp, WebPush $push): RedirectResponse
    {
        $ready = array_keys(array_filter($this->ready($whatsapp, $push)));
        $data = $request->validate([
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => [Rule::in($ready)],
            'message' => ['required', 'string', 'max:480'],
        ], [
            'channels.required' => 'Choose at least one way to send it.',
            'channels.*.in' => 'That way of sending is switched off or isn\'t set up yet.',
        ]);

        // Sending the same announcement twice in a day is usually a double click.
        $recent = Broadcast::where('announcement_id', $announcement->id)->where('created_at', '>=', now()->subDay())->exists();
        if ($recent && ! $request->boolean('again')) {
            return back()->withInput()->withErrors(['again' => 'This was already sent in the last 24 hours. Tick "Send it again" to confirm.']);
        }

        Broadcast::create([
            'announcement_id' => $announcement->id,
            'title' => Str::limit($announcement->title, 120, ''),
            'message' => $data['message'],
            'url' => $announcement->url ?: route('account.inbox'),
            'channels' => array_values(array_unique($data['channels'])),
            'status' => 'queued',
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.announcements.send', $announcement)
            ->with('status', 'Sending now. Emails, WhatsApp messages and notifications go out over the next few minutes; this page shows progress.');
    }

    /**
     * Which ways can be used now: switched on in Settings & roles, and set up.
     *
     * @return array{email: bool, whatsapp: bool, push: bool}
     */
    private function ready(WhatsApp $whatsapp, WebPush $push): array
    {
        return ['email' => Notify::on('email'), 'whatsapp' => $whatsapp->ready(), 'push' => Notify::on('push') && $push->configured()];
    }
}
