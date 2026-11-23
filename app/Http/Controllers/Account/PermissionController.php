<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\PermissionRequest;
use App\Models\Service;
use App\Services\People\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * A worker asking permission to miss a service or come late.
 */
class PermissionController extends Controller
{
    public function __construct(private Permissions $permissions) {}

    public function index(Request $request): View
    {
        $member = $request->user()->member;
        abort_unless(Permissions::canAsk($member), 403, 'Permission requests are for workers in a unit.');

        return view('account.permission', [
            'member' => $member,
            'services' => $this->permissions->upcomingServices(),
            'mine' => $member->permissionRequests()->with('service', 'answeredBy.member')->latest('on_date')->latest('id')->take(20)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $member = $request->user()->member;
        abort_unless(Permissions::canAsk($member), 403);

        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(PermissionRequest::KINDS))],
            'service_id' => ['nullable', Rule::exists('services', 'id')->where(fn ($q) => $q->where('held_on', '>=', today()->toDateString()))],
            'on_date' => ['required_without:service_id', 'nullable', 'date', 'after_or_equal:today'],
            'arrive_at' => ['nullable', 'date_format:H:i'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'on_date.required_without' => 'Choose the service, or the day if it isn\'t listed.',
            'reason.min' => 'Please say a little more about why.',
        ]);

        if ($data['service_id'] ?? null) {
            $data['on_date'] = Service::find($data['service_id'])->held_on->toDateString();
        }
        if ($data['kind'] !== 'late') {
            $data['arrive_at'] = null;
        }

        $taken = $member->permissionRequests()->whereDate('on_date', $data['on_date'])->whereIn('status', ['pending', 'granted'])
            ->when($data['service_id'] ?? null, fn ($q, $id) => $q->where('service_id', $id))->exists();
        if ($taken) {
            return back()->withInput()->withErrors(['service_id' => 'You already asked for that service. Cancel the first one to change it.']);
        }

        $this->permissions->ask($member, $data);

        return redirect()->route('account.permission')->with('status', 'Sent. Your HOD and the pastors will see it, and you\'ll hear back here.');
    }

    public function cancel(Request $request, PermissionRequest $permission): RedirectResponse
    {
        abort_unless($permission->member_id === $request->user()->member?->id, 403);
        abort_unless($permission->isCancellable(), 422);
        $permission->forceFill(['status' => 'cancelled'])->save();

        return back()->with('status', 'Cancelled.');
    }
}
