<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Fellowship;
use App\Models\Member;
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
            'units' => Unit::with('hod', 'assistant')->withCount(['members' => fn ($q) => $q->active()])->orderBy('name')->get(),
            'members' => Member::active()->orderBy('name')->get(['id', 'name']),
            'kinds' => Unit::KINDS,
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
        $data = $request->validateWithBag('unit', [
            'name' => ['required', 'string', 'max:60', Rule::unique('units')->ignore($unit?->id)],
            'kind' => ['nullable', Rule::in(array_keys(Unit::KINDS))],
            'leader_name' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'hod_member_id' => ['nullable', Rule::exists('members', 'id')->where('status', Member::ACTIVE)],
            'assistant_member_id' => ['nullable', Rule::exists('members', 'id')->where('status', Member::ACTIVE), 'different:hod_member_id'],
        ], ['assistant_member_id.different' => 'The assistant must be someone other than the HOD.']);

        // The HOD's name shows on the About page and the member's account.
        if (! empty($data['hod_member_id'])) {
            $data['leader_name'] = Member::find($data['hod_member_id'])->name;
        }

        return [...$data, 'kind' => $data['kind'] ?? 'general'];
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
