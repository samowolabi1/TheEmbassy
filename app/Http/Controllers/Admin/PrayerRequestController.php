<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PrayerRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrayerRequestController extends Controller
{
    /**
     * The prayer request inbox, newest first, filterable by unread or private.
     */
    public function index(Request $request): View
    {
        $filter = in_array($request->query('show'), ['unread', 'private'], true) ? $request->query('show') : 'all';

        $requests = PrayerRequest::query()
            ->when($filter === 'unread', fn ($q) => $q->unread())
            ->when($filter === 'private', fn ($q) => $q->where('is_private', true))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.prayer-requests', [
            'requests' => $requests,
            'filter' => $filter,
            'counts' => [
                'all' => PrayerRequest::count(),
                'unread' => PrayerRequest::unread()->count(),
                'private' => PrayerRequest::where('is_private', true)->count(),
            ],
        ]);
    }

    /**
     * Mark a request as read (prayed over), or back to unread.
     */
    public function update(Request $request, PrayerRequest $prayerRequest): RedirectResponse
    {
        $prayerRequest->forceFill(['read_at' => $request->boolean('read') ? now() : null])->save();

        return back();
    }

    /**
     * Delete a request for good.
     */
    public function destroy(PrayerRequest $prayerRequest): RedirectResponse
    {
        $prayerRequest->delete();

        return back()->with('status', 'Prayer request deleted.');
    }
}
