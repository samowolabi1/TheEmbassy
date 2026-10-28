<?php

namespace App\Http\Controllers\Unit;

use App\Http\Controllers\Controller;
use App\Models\Duty;
use App\Models\Service;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Who in the unit serves at each upcoming service. Members see their duties on their account page
 * and get a reminder email two days before.
 */
class RotaController extends Controller
{
    public function index(Unit $unit): View
    {
        $services = Service::where('held_on', '>=', today()->startOfDay())->orderBy('held_on')->orderBy('starts_at')->take(8)->get();

        return view('unit.rota', [
            'unit' => $unit,
            'services' => $services,
            'duties' => $unit->duties()->with('member')->whereIn('service_id', $services->pluck('id'))->get()->groupBy('service_id'),
            'members' => $unit->members()->active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, Unit $unit): RedirectResponse
    {
        $data = $request->validate([
            'service_id' => ['required', Rule::exists('services', 'id')],
            'member_id' => ['required', Rule::exists('members', 'id')->where('unit_id', $unit->id)->where('status', 'active')],
            'role' => ['required', 'string', 'max:60'],
        ], ['member_id.exists' => 'Choose someone from this unit.']);

        if ($unit->duties()->where('service_id', $data['service_id'])->where('member_id', $data['member_id'])->exists()) {
            return back()->withErrors(['member_id' => 'They\'re already on the rota for that service.']);
        }
        $unit->duties()->create($data);

        return back()->with('status', 'Added to the rota.');
    }

    public function destroy(Unit $unit, Duty $duty): RedirectResponse
    {
        abort_unless($duty->unit_id === $unit->id, 404);
        $duty->delete();

        return back()->with('status', 'Removed from the rota.');
    }
}
