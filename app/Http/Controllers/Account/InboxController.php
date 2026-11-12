<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\Messaging\Inbox;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The member's messages: broadcasts, announcements and messages from their unit.
 */
class InboxController extends Controller
{
    public function index(Request $request, Inbox $inbox): View
    {
        $user = $request->user();
        $lastRead = $user->inbox_read_at;
        $user->forceFill(['inbox_read_at' => now()])->save();

        return view('account.inbox', [
            'items' => $inbox->items($user),
            'lastRead' => $lastRead,
            'smsOn' => $user->member?->notify_sms,
        ]);
    }
}
