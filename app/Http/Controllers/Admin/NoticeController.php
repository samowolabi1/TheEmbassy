<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NoticeController extends Controller
{
    /** Filter tabs and the query behind each. */
    private const FILTERS = ['review', 'live', 'expired', 'rejected', 'unpaid'];

    /**
     * Paid notices to review, plus what's live, finished, rejected or never paid for.
     */
    public function index(Request $request): View
    {
        $filter = in_array($request->query('show'), self::FILTERS, true) ? $request->query('show') : 'review';

        return view('admin.notices', [
            'notices' => $this->filtered($filter)->latest()->paginate(20)->withQueryString(),
            'filter' => $filter,
            'counts' => collect(self::FILTERS)->mapWithKeys(fn ($f) => [$f => $this->filtered($f)->count()]),
            'incomeThisMonth' => Payment::where('payable_type', (new Notice)->getMorphClass())
                ->where('status', Payment::SUCCESS)
                ->where('paid_at', '>=', now()->startOfMonth())
                ->sum('amount'),
        ]);
    }

    /**
     * Publish a paid notice for the days it was paid for.
     */
    public function approve(Notice $notice): RedirectResponse
    {
        abort_unless(in_array($notice->status, [Notice::IN_REVIEW, Notice::REJECTED], true) && $notice->paid_at, 422);

        $notice->approve();

        return back()->with('status', "\"{$notice->title}\" is live until ".$notice->ends_at->format('D j M').'.');
    }

    /**
     * Turn a notice down. The advertiser sees the reason on their status page.
     */
    public function reject(Request $request, Notice $notice): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], [
            'reason.required' => 'Give a short reason; the advertiser will see it.',
        ]);

        $notice->reject($data['reason']);

        return back()->with('status', "\"{$notice->title}\" rejected. Refund the payment from the Paystack dashboard if it was paid.");
    }

    /**
     * @return Builder<Notice>
     */
    private function filtered(string $filter): Builder
    {
        return match ($filter) {
            'review' => Notice::where('status', Notice::IN_REVIEW),
            'live' => Notice::where('status', Notice::APPROVED)->where('ends_at', '>', now()),
            'expired' => Notice::where('status', Notice::APPROVED)->where('ends_at', '<=', now()),
            'rejected' => Notice::where('status', Notice::REJECTED),
            'unpaid' => Notice::where('status', Notice::AWAITING_PAYMENT),
        };
    }
}
