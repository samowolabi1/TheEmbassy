<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Fellowship;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Service units and house fellowships members belong to.
 */
class UnitController extends Controller
{
    public function index(): View
    {
        return view('admin.units', [
            'units' => Unit::withCount(['members' => fn ($q) => $q->active()])->orderBy('name')->get(),
            'fellowships' => Fellowship::withCount(['members' => fn ($q) => $q->active()])->orderBy('name')->get(),
        ]);
    }

    public function storeUnit(Request $request): RedirectResponse
    {
        $unit = Unit::create($this->unitData($request));

        return back()->with('status', "{$unit->name} added.");
    }

    public function updateUnit(Request $request, Unit $unit): RedirectResponse
    {
        $unit->update($this->unitData($request, $unit));

        return back()->with('status', "{$unit->name} saved.");
    }

    public function destroyUnit(Unit $unit): RedirectResponse
    {
        $unit->delete(); // members keep their record, without a unit

        return back()->with('status', "{$unit->name} removed.");
    }

    public function storeFellowship(Request $request): RedirectResponse
    {
        $fellowship = Fellowship::create($this->fellowshipData($request));

        return back()->with('status', "{$fellowship->name} added.");
    }

    public function updateFellowship(Request $request, Fellowship $fellowship): RedirectResponse
    {
        $fellowship->update($this->fellowshipData($request, $fellowship));

        return back()->with('status', "{$fellowship->name} saved.");
    }

    public function destroyFellowship(Fellowship $fellowship): RedirectResponse
    {
        $fellowship->delete();

        return back()->with('status', "{$fellowship->name} removed.");
    }

    /**
     * @return array<string, mixed>
     */
    private function unitData(Request $request, ?Unit $unit = null): array
    {
        return $request->validateWithBag('unit', [
            'name' => ['required', 'string', 'max:60', Rule::unique('units')->ignore($unit?->id)],
            'leader_name' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function fellowshipData(Request $request, ?Fellowship $fellowship = null): array
    {
        return $request->validateWithBag('fellowship', [
            'name' => ['required', 'string', 'max:60', Rule::unique('fellowships')->ignore($fellowship?->id)],
            'host_name' => ['nullable', 'string', 'max:100'],
            'area' => ['nullable', 'string', 'max:100'],
            'meets' => ['nullable', 'string', 'max:100'],
        ]);
    }
}
