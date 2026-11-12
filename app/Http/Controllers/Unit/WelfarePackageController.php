<?php

namespace App\Http\Controllers\Unit;

use App\Http\Controllers\Controller;
use App\Mail\WelfarePackageDecision;
use App\Models\PushSubscription;
use App\Models\Unit;
use App\Models\WelfarePackage;
use App\Models\WelfarePackageApplication;
use App\Services\Messaging\WebPush;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Welfare packages: the welfare unit sets them up, members apply, the unit decides and hands them out.
 */
class WelfarePackageController extends Controller
{
    public function index(Unit $unit): View
    {
        return view('unit.welfare.packages', [
            'unit' => $unit,
            'packages' => WelfarePackage::where('unit_id', $unit->id)
                ->withCount([
                    'applications',
                    'applications as pending_count' => fn ($q) => $q->where('status', 'pending'),
                    'applications as given_count' => fn ($q) => $q->whereIn('status', ['approved', 'collected']),
                ])
                ->latest('opens_at')->get(),
            'package' => new WelfarePackage(['opens_at' => now()->startOfHour(), 'closes_at' => now()->startOfHour()->addWeeks(2)]),
        ]);
    }

    public function store(Request $request, Unit $unit): RedirectResponse
    {
        $package = WelfarePackage::create([...$this->validated($request), 'unit_id' => $unit->id, 'created_by' => $request->user()->id]);

        return redirect()->route('unit.welfare.packages.show', [$unit, $package])->with('status', 'Package saved.'.($package->is_published ? ' Members can apply from their account.' : ''));
    }

    public function show(Request $request, Unit $unit, WelfarePackage $package): View
    {
        abort_unless($package->unit_id === $unit->id, 404);
        $status = array_key_exists((string) $request->query('status'), WelfarePackageApplication::STATUSES) ? $request->query('status') : null;
        $search = trim((string) $request->query('q'));

        return view('unit.welfare.package', [
            'unit' => $unit,
            'package' => $package,
            'status' => $status,
            'search' => $search,
            'applications' => $package->applications()->with('member.unit')
                ->when($status, fn ($q) => $q->where('status', $status))
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('reference', strtoupper($search))
                    ->orWhereHas('member', fn ($m) => $m->where('name', 'like', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%'))))
                ->orderByRaw("case status when 'pending' then 0 when 'approved' then 1 when 'waitlist' then 2 else 3 end")
                ->oldest()
                ->paginate(40)->withQueryString(),
            'counts' => $package->applications()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function update(Request $request, Unit $unit, WelfarePackage $package): RedirectResponse
    {
        abort_unless($package->unit_id === $unit->id, 404);
        $package->update($this->validated($request));

        return back()->with('status', 'Package saved.');
    }

    /**
     * Approve, waitlist or decline an application. Approving past the number of places is refused.
     */
    public function decide(Request $request, Unit $unit, WelfarePackage $package, WelfarePackageApplication $application, WebPush $push): RedirectResponse
    {
        abort_unless($package->unit_id === $unit->id && $application->welfare_package_id === $package->id, 404);
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'waitlist', 'declined'])],
            'decision_note' => ['nullable', 'string', 'max:300'],
        ]);
        if ($application->status === 'collected' || $application->status === 'withdrawn') {
            throw ValidationException::withMessages(['status' => 'This application is already '.$application->status.'.']);
        }
        if ($data['status'] === 'approved' && $application->status !== 'approved' && $package->placesLeft() === 0) {
            throw ValidationException::withMessages(['status' => 'All '.$package->slots.' places are taken. Put them on the waiting list, or add places to the package.']);
        }

        $application->update([...$data, 'decided_by' => $request->user()->id, 'decided_at' => now()]);
        $this->tell($application->fresh('member.user', 'package'), $push);

        return back()->with('status', $application->member->name.' · '.WelfarePackageApplication::STATUSES[$data['status']].'. They\'ve been told.');
    }

    /**
     * Mark a package as handed over, by the applicant's reference at the collection point.
     */
    public function collect(Request $request, Unit $unit, WelfarePackage $package): RedirectResponse
    {
        abort_unless($package->unit_id === $unit->id, 404);
        $data = $request->validate(['reference' => ['required', 'string', 'max:12']]);
        $application = $package->applications()->with('member')->where('reference', strtoupper(trim($data['reference'])))->first();

        if (! $application) {
            throw ValidationException::withMessages(['reference' => 'No application with that reference for this package.']);
        }
        if ($application->status === 'collected') {
            throw ValidationException::withMessages(['reference' => "{$application->member->name} already collected on {$application->collected_at->format('j M, g:i A')}."]);
        }
        if ($application->status !== 'approved') {
            throw ValidationException::withMessages(['reference' => "{$application->member->name}'s application isn't approved ({$application->statusLabel()})."]);
        }
        $application->update(['status' => 'collected', 'collected_at' => now()]);

        return back()->with('status', "✓ {$application->member->name} has collected the package.");
    }

    public function export(Unit $unit, WelfarePackage $package): StreamedResponse
    {
        abort_unless($package->unit_id === $unit->id, 404);

        return Csv::download(
            'welfare-package-'.$package->id.'.csv',
            ['reference', 'name', 'phone', 'household', 'status', 'applied', 'collected'],
            function ($write) use ($package) {
                $package->applications()->with('member')->oldest()->each(fn (WelfarePackageApplication $a) => $write([
                    $a->reference, $a->member?->name, $a->member?->phone, $a->household_size, $a->status,
                    $a->created_at->toDateTimeString(), $a->collected_at?->toDateTimeString(),
                ]));
            },
        );
    }

    /**
     * Email and notify the member about the decision.
     */
    private function tell(WelfarePackageApplication $application, WebPush $push): void
    {
        $member = $application->member;
        if ($member->email) {
            Mail::to($member->email)->send(new WelfarePackageDecision($application));
        }
        if ($member->user_id && $push->configured()) {
            $subscriptions = PushSubscription::where('user_id', $member->user_id)->get();
            defer(fn () => $push->send($subscriptions, [
                'title' => $application->package->title,
                'body' => $application->statusLabel().($application->status === 'approved' ? ' · Ref '.$application->reference : ''),
                'url' => route('account.welfare'),
                'tag' => 'welfare-package-'.$application->id,
            ]));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'items' => ['nullable', 'string', 'max:500'],
            'slots' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'opens_at' => ['required', 'date'],
            'closes_at' => ['required', 'date', 'after:opens_at'],
            'collection_details' => ['nullable', 'string', 'max:500'],
        ]);

        return [
            ...$data,
            'slots' => $data['slots'] ?? null,
            'opens_at' => Carbon::parse($data['opens_at']),
            'closes_at' => Carbon::parse($data['closes_at']),
            'is_published' => $request->boolean('is_published'),
        ];
    }
}
