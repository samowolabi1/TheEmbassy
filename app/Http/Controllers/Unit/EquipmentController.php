<?php

namespace App\Http\Controllers\Unit;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\EquipmentIssue;
use App\Models\EquipmentLoan;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The media unit's equipment: what there is, who has it, and what needs fixing.
 */
class EquipmentController extends Controller
{
    public function index(Request $request, Unit $unit): View
    {
        $category = array_key_exists($request->query('category'), Equipment::CATEGORIES) ? $request->query('category') : null;

        return view('unit.media.equipment', [
            'unit' => $unit,
            'category' => $category,
            'equipment' => Equipment::with('currentLoan.member')->withCount(['issues as open_issues' => fn ($q) => $q->where('status', 'open')])
                ->when($category, fn ($q) => $q->where('category', $category))->where('status', '!=', 'retired')->orderBy('category')->orderBy('name')->get(),
            'issues' => EquipmentIssue::with('equipment', 'reporter')->where('status', 'open')->latest()->get(),
            'loans' => EquipmentLoan::with('equipment', 'member')->whereNull('returned_at')->orderBy('due_on')->get(),
            'members' => $unit->members()->active()->orderBy('name')->get(['id', 'name']),
            'canLead' => Gate::allows('lead-media', $unit),
            'value' => (int) Equipment::where('status', '!=', 'retired')->sum('value'),
        ]);
    }

    public function store(Request $request, Unit $unit): RedirectResponse
    {
        $equipment = Equipment::create($this->validated($request));

        return back()->with('status', "{$equipment->name} added.");
    }

    public function update(Request $request, Unit $unit, Equipment $equipment): RedirectResponse
    {
        $equipment->update($this->validated($request, $equipment));
        $status = $request->validate(['status' => ['nullable', Rule::in(['available', 'repair', 'retired'])]])['status'] ?? null;
        if ($status && $equipment->status !== 'checked_out') {
            $equipment->forceFill(['status' => $status])->save();
        }

        return back()->with('status', "{$equipment->name} saved.");
    }

    public function checkOut(Request $request, Unit $unit, Equipment $equipment): RedirectResponse
    {
        abort_unless($equipment->status === 'available', 422, 'That item isn\'t available.');
        $data = $request->validate([
            'member_id' => ['required', Rule::exists('members', 'id')->where('status', 'active')],
            'due_on' => ['nullable', 'date', 'after_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($equipment, $data, $request) {
            $loan = $equipment->loans()->make([...$data, 'out_at' => now()]);
            $loan->issued_by = $request->user()->id;
            $loan->save();
            $equipment->forceFill(['status' => 'checked_out'])->save();
        });

        return back()->with('status', "{$equipment->name} checked out.");
    }

    public function checkIn(Request $request, Unit $unit, Equipment $equipment): RedirectResponse
    {
        $loan = $equipment->currentLoan ?? abort(422, 'That item isn\'t checked out.');
        $data = $request->validate([
            'condition' => ['required', Rule::in(['good', 'fair', 'faulty'])],
            'fault' => ['nullable', 'required_if:condition,faulty', 'string', 'max:2000'],
        ], ['fault.required_if' => 'Say what\'s wrong so it can be fixed.']);

        DB::transaction(function () use ($equipment, $loan, $data, $request) {
            $loan->forceFill(['returned_at' => now(), 'condition_in' => $data['condition']])->save();
            $equipment->forceFill(['status' => $data['condition'] === 'faulty' ? 'repair' : 'available', 'condition' => $data['condition']])->save();
            if ($data['condition'] === 'faulty') {
                $issue = $equipment->issues()->make(['description' => $data['fault']]);
                $issue->reported_by = $request->user()->id;
                $issue->save();
            }
        });

        return back()->with('status', "{$equipment->name} returned.");
    }

    /**
     * Anyone in the media team can report a fault.
     */
    public function report(Request $request, Unit $unit, Equipment $equipment): RedirectResponse
    {
        $data = $request->validate(['description' => ['required', 'string', 'max:2000']]);
        $issue = $equipment->issues()->make($data);
        $issue->reported_by = $request->user()->id;
        $issue->save();
        $equipment->forceFill(['condition' => 'faulty'])->save();

        return back()->with('status', 'Thanks, the fault on '.$equipment->name.' is logged.');
    }

    public function resolve(Request $request, Unit $unit, EquipmentIssue $issue): RedirectResponse
    {
        $data = $request->validate([
            'resolution' => ['required', 'string', 'max:255'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
        ]);
        $issue->forceFill(['status' => 'fixed', 'fixed_at' => now(), 'resolution' => $data['resolution'], 'cost' => isset($data['cost']) ? (int) round($data['cost'] * 100) : null])->save();
        $equipment = $issue->equipment;
        if ($equipment->issues()->where('status', 'open')->doesntExist()) {
            $equipment->forceFill(['condition' => 'good', 'status' => $equipment->status === 'repair' ? 'available' : $equipment->status])->save();
        }

        return back()->with('status', 'Marked as fixed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Equipment $equipment = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', Rule::in(array_keys(Equipment::CATEGORIES))],
            'tag' => ['nullable', 'string', 'max:30', Rule::unique('equipment', 'tag')->ignore($equipment?->id)],
            'serial' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:120'],
            'condition' => ['nullable', Rule::in(['good', 'fair', 'faulty'])],
            'purchased_on' => ['nullable', 'date', 'before_or_equal:today'],
            'value' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], ['tag.unique' => 'Another item already has that asset tag.']);

        return [...$data, 'condition' => $data['condition'] ?? 'good', 'value' => isset($data['value']) ? (int) round($data['value'] * 100) : null];
    }
}
