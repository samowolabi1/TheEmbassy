<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReminderLog;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Shop;
use App\Services\Messaging\Reminders;
use App\Services\Messaging\WebPush;
use App\Services\Messaging\WhatsApp;
use App\Support\Notify;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Reminders & greetings: switch each automatic message on or off, set when it goes, how it's sent
 * and what it says, send yourself a test, and see what went out.
 */
class ReminderController extends Controller
{
    public function index(Reminders $reminders, WebPush $push, WhatsApp $whatsapp): View
    {
        $samples = [
            'service' => ['first_name' => 'Ada', 'service' => 'Sunday service', 'day' => 'today', 'time' => '9:00 AM', 'church' => config('church.short_name')],
            'greeting' => ['first_name' => 'Ada', 'service' => 'Sunday service', 'church' => config('church.short_name')],
            'feedback' => ['first_name' => 'Ada', 'service' => 'Sunday service', 'church' => config('church.short_name')],
            'market' => ['name' => 'Tolu', 'shop' => 'Tolu\'s Kitchen', 'market' => config('church.brand.market_name'), 'end_date' => now()->addWeek()->format('l j F'), 'days_left' => '7 days left'],
        ];
        $hours = Reminders::settings('service')['timing'];

        return view('admin.reminders', [
            'kinds' => collect(Reminders::KINDS)->map(fn ($k, $key) => [...$k, 'key' => $key, 'settings' => Reminders::settings($key), 'placeholders' => Reminders::PLACEHOLDERS[$key], 'sample' => $samples[$key],
                'sent' => ReminderLog::where('kind', $key)->where('created_at', '>=', now()->subDays(30))->count()]),
            'ways' => [
                'email' => ['label' => 'Email', 'on' => Notify::on('email'), 'ready' => true],
                'whatsapp' => ['label' => 'WhatsApp', 'on' => Notify::on('whatsapp'), 'ready' => $whatsapp->configured()],
                'push' => ['label' => 'App notification', 'on' => Notify::on('push'), 'ready' => $push->configured()],
            ],
            'upcoming' => Service::whereNotNull('starts_at')->where('starts_at', '>', now())->orderBy('starts_at')->take(4)->get()
                ->map(fn (Service $s) => ['service' => $s, 'remind_at' => $s->starts_at->copy()->subHours($hours), 'sent' => $s->reminder_sent_at]),
            'ending' => Shop::approved()->whereHas('subscriptions', fn ($q) => $q->where('status', 'active')->whereBetween('ends_at', [now(), now()->addDays(14)]))->get()
                ->map(fn (Shop $shop) => ['shop' => $shop, 'until' => $shop->subscribedUntil()])->filter(fn ($r) => $r['until'] && $r['until']->lte(now()->addDays(14)))->sortBy('until')->values(),
            'recent' => ReminderLog::selectRaw('kind, title, service_id, shop_id, min(created_at) as sent_at, count(*) as people')
                ->groupBy('kind', 'title', 'service_id', 'shop_id')->orderByDesc('sent_at')->take(12)->get(),
        ]);
    }

    public function update(Request $request, string $kind): RedirectResponse
    {
        abort_unless(isset(Reminders::KINDS[$kind]), 404);
        $meta = Reminders::KINDS[$kind];
        $data = $request->validateWithBag($kind, [
            'timing' => ['required', 'integer', 'min:'.($kind === 'market' ? 1 : 0), 'max:'.$meta['max']],
            'text' => ['required', 'string', 'max:450'],
        ], ['text.required' => 'Write the message.']);

        $prefix = "church.reminders.{$kind}.";
        $values = [
            $prefix.'enabled' => $request->boolean('enabled') ? '1' : '0',
            $prefix.$meta['timing'] => (string) $data['timing'],
            $prefix.'email' => $request->boolean('email') ? '1' : '0',
            $prefix.'whatsapp' => $request->boolean('whatsapp') ? '1' : '0',
            $prefix.'push' => $request->boolean('push') ? '1' : '0',
            $prefix.'text' => trim($data['text']),
        ];
        if ($kind === 'market') {
            $values[$prefix.'final'] = $request->boolean('final') ? '1' : '0';
            $values[$prefix.'lapsed'] = $request->boolean('lapsed') ? '1' : '0';
        }
        Setting::putMany($values);
        Setting::applyToConfig();

        return redirect()->to(route('admin.reminders.index').'#'.$kind)->with('status', $meta['label'].' saved.');
    }

    public function test(Request $request, string $kind, Reminders $reminders): RedirectResponse
    {
        abort_unless(isset(Reminders::KINDS[$kind]), 404);
        $went = $reminders->test($request->user(), $kind, Reminders::settings($kind));

        return redirect()->to(route('admin.reminders.index').'#'.$kind)->with('status', $went
            ? 'Test sent to you by '.collect($went)->join(', ', ' and ').'.'
            : 'Nothing could be sent to you: tick a way of sending that is switched on in Settings & roles, and make sure your account has an email (or a phone number for WhatsApp).');
    }
}
