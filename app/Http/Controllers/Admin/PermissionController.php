<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PermissionRequest;
use App\Services\People\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The pastors' view of every worker's permission requests.
 */
class PermissionController extends Controller
{
    public function index(Request $request): View
    {
        $show = $request->query('show') === 'past' ? 'past' : 'upcoming';
        $requests = PermissionRequest::with('member.units', 'service', 'answeredBy.member')
            ->when($show === 'upcoming', fn ($q) => $q->upcoming()->where('status', '!=', 'cancelled')->orderBy('on_date')->orderByRaw("status = 'pending' desc"))
            ->when($show === 'past', fn ($q) => $q->where('on_date', '<', today()->toDateString())->latest('on_date'))
            ->paginate(40)->withQueryString();

        return view('admin.permissions', [
            'show' => $show,
            'requests' => $requests,
            'rota' => PermissionRequest::rotaFor($requests->items()),
            'pending' => PermissionRequest::upcoming()->where('status', 'pending')->count(),
        ]);
    }

    public function answer(Request $request, PermissionRequest $permission, Permissions $permissions): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['granted', 'declined'])], 'note' => ['nullable', 'string', 'max:500']]);
        abort_if($permission->status === 'cancelled', 422);
        $permissions->answer($permission, $request->user(), $data['status'], $data['note'] ?? null);

        return back()->with('status', $permission->member->firstName().' has been told.');
    }
}
