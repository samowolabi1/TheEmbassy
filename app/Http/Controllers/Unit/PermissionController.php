<?php

namespace App\Http\Controllers\Unit;

use App\Http\Controllers\Controller;
use App\Models\PermissionRequest;
use App\Models\Unit;
use App\Services\People\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The unit head's view of their workers' permission requests.
 */
class PermissionController extends Controller
{
    public function index(Unit $unit): View
    {
        $base = PermissionRequest::forUnits([$unit->id])->with('member', 'service', 'answeredBy.member');

        $upcoming = (clone $base)->upcoming()->where('status', '!=', 'cancelled')->orderBy('on_date')->orderByRaw("status = 'pending' desc")->get();
        $past = (clone $base)->where('on_date', '<', today()->toDateString())->where('on_date', '>=', today()->subDays(60)->toDateString())->latest('on_date')->get();

        return view('unit.permissions', [
            'unit' => $unit,
            'upcoming' => $upcoming,
            'past' => $past,
            'rota' => PermissionRequest::rotaFor($upcoming->concat($past)),
        ]);
    }

    public function answer(Request $request, Unit $unit, PermissionRequest $permission, Permissions $permissions): RedirectResponse
    {
        abort_unless($permission->member->inUnit($unit), 404);
        $data = $request->validate(['status' => ['required', Rule::in(['granted', 'declined'])], 'note' => ['nullable', 'string', 'max:500']]);
        abort_if($permission->status === 'cancelled', 422);
        $permissions->answer($permission, $request->user(), $data['status'], $data['note'] ?? null);

        return back()->with('status', $permission->member->firstName().' has been told.');
    }
}
