<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Declaration;
use App\Models\Event;
use App\Models\Expense;
use App\Models\FirstTimer;
use App\Models\Fund;
use App\Models\Gift;
use App\Models\Member;
use App\Models\Notice;
use App\Models\PrayerRequest;
use App\Models\Service;
use App\Models\Shop;
use App\Models\TicketOrder;
use App\Models\TokenPayment;
use App\Models\VendorSubscription;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The overview from the design, showing only what the person's role covers.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $can = fn (string $area) => $user->canManage($area);
        $hour = now()->hour;
        $monthStart = now()->startOfMonth();

        $data = [
            'greeting' => match (true) {
                $hour < 12 => 'Good morning',
                $hour < 17 => 'Good afternoon',
                default => 'Good evening',
            },
            'name' => $user->name,
            'can' => $can,
            'tiles' => [],
            'todo' => [],
        ];

        if ($can('members')) {
            $data['tiles'][] = ['label' => 'Members', 'num' => number_format(Member::active()->count()), 'foot' => '+'.Member::active()->where('joined_on', '>=', $monthStart)->count().' this month', 'route' => route('admin.members.index'), 'navy' => true];
            $data['todo'][] = ['count' => Member::where('status', Member::PENDING)->count(), 'text' => 'Members waiting for approval', 'route' => route('admin.members.index', ['tab' => 'pending'])];
            $data['newMembers'] = Member::with('unit')->where('created_at', '>=', now()->subWeek())->latest()->take(4)->get();
        }
        if ($can('attendance')) {
            $data['tiles'][] = ['label' => 'First-timers ('.now()->format('M').')', 'num' => FirstTimer::where('visited_on', '>=', $monthStart)->count(), 'foot' => FirstTimer::where('visited_on', '>=', $monthStart)->whereNotNull('followed_up_at')->count().' followed up', 'route' => route('admin.first-timers.index')];
            $data['todo'][] = ['count' => FirstTimer::whereNull('followed_up_at')->count(), 'text' => 'First-timers not yet called', 'route' => route('admin.first-timers.index')];
            $data['sundays'] = Service::where('kind', 'sunday')->orderByDesc('held_on')->take(8)->get()->reverse()->values();
        }
        if ($can('finance')) {
            $giving = (int) Gift::paid()->where('given_on', '>=', $monthStart)->sum('amount');
            $lastMonth = (int) Gift::paid()->whereBetween('given_on', [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->setDay(min(now()->day, now()->subMonthNoOverflow()->daysInMonth))->endOfDay()])->sum('amount');
            $data['tiles'][] = ['label' => 'Giving ('.now()->format('M').')', 'num' => $this->naira($giving), 'foot' => $lastMonth ? sprintf('%+d%% vs same point last month', round(($giving - $lastMonth) * 100 / $lastMonth)) : 'Online and cash', 'route' => route('admin.finance.index')];

            $payers = Member::active()->with('tokenPlan', 'tokenPayments')->whereHas('tokenPlan', fn ($q) => $q->where('amount', '>', 0))->get();
            $behind = $payers->filter(fn (Member $m) => $m->tokenMonthsOwed() > 0)->count();
            $data['tiles'][] = ['label' => 'Tokens up to date', 'num' => $payers->isEmpty() ? '—' : round(($payers->count() - $behind) * 100 / $payers->count()).'%', 'foot' => $behind.' in arrears', 'route' => route('admin.finance.index').'#record-tokens'];

            Fund::ensureDefaults();
            $data['funds'] = Fund::active()->get()->map(fn (Fund $f) => ['name' => $f->name, 'total' => (int) $f->gifts()->paid()->where('given_on', '>=', $monthStart)->sum('amount')]);
            $data['tokensThisMonth'] = (int) TokenPayment::where('status', TokenPayment::PAID)->where('paid_at', '>=', $monthStart)->sum('amount');
            $data['latestGifts'] = Gift::with('fund')->paid()->latest('updated_at')->take(5)->get();
        }
        if ($can('market')) {
            $data['tiles'][] = ['label' => 'Market ('.now()->format('M').')', 'num' => $this->naira((int) VendorSubscription::where('status', VendorSubscription::ACTIVE)->where('created_at', '>=', $monthStart)->sum('amount')), 'foot' => ($listed = Shop::listed()->count()).' '.str('shop')->plural($listed).' listed', 'route' => route('admin.market.index')];
            $data['todo'][] = ['count' => Shop::where('status', Shop::PENDING)->count(), 'text' => 'Shop applications to review', 'route' => route('admin.market.index')];
        }
        if ($can('prayer')) {
            $data['todo'][] = ['count' => PrayerRequest::unread()->count(), 'text' => 'Unread prayer requests', 'route' => route('admin.prayer-requests.index', ['show' => 'unread'])];
            $data['latestPrayer'] = PrayerRequest::latest()->take(3)->get();
        }
        if ($can('expenses')) {
            $data['todo'][] = ['count' => Expense::where('status', Expense::PENDING)->count(), 'text' => 'Expenses to approve', 'route' => route('admin.expenses.index')];
        }
        if ($can('notices')) {
            $data['todo'][] = ['count' => Notice::where('status', Notice::IN_REVIEW)->count(), 'text' => 'Paid notices to review', 'route' => route('admin.notices.index')];
        }
        if ($can('messages')) {
            $data['todo'][] = ['count' => ContactMessage::whereNull('read_at')->count(), 'text' => 'Unread contact messages', 'route' => route('admin.messages.index', ['show' => 'unread'])];
        }
        if ($can('content')) {
            $data['declaration'] = Declaration::where('week_of', Declaration::weekStarting(today()))->exists();
        }
        if ($can('events')) {
            $data['events'] = Event::upcoming()->withCount(['tickets as booked_count' => fn ($q) => $q->whereHas('order', fn ($o) => $o->where('status', TicketOrder::CONFIRMED))])->take(3)->get();
        }

        return view('admin.dashboard', $data);
    }

    private function naira(int $kobo): string
    {
        return $kobo >= 100_000_000 ? '₦'.round($kobo / 100_000_000, 2).'m' : '₦'.number_format($kobo / 100);
    }
}
