<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Service;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Headcounts for each service, and which members were there.
 */
class AttendanceController extends Controller
{
    public function index(): View
    {
        return view('admin.attendance.index', [
            'services' => Service::withCount('members')->orderByDesc('held_on')->paginate(15),
            'sundays' => Service::where('kind', 'sunday')->orderByDesc('held_on')->take(8)->get()->reverse()->values(),
            'kinds' => Service::KINDS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $service = Service::create($data);

        return redirect()->route('admin.attendance.show', $service)->with('status', 'Service recorded. Tick the members who were there.');
    }

    public function show(Request $request, Service $service): View
    {
        $present = $service->members()->pluck('members.id')->all();

        return view('admin.attendance.show', [
            'service' => $service,
            'kinds' => Service::KINDS,
            'present' => $present,
            'members' => Member::active()->search($request->query('q'))
                ->when($request->filled('unit'), fn ($q) => $q->where('unit_id', $request->integer('unit')))
                ->orderBy('name')->get(['id', 'name', 'phone', 'unit_id']),
            'units' => Unit::orderBy('name')->get(),
            'filters' => $request->only(['q', 'unit']),
        ]);
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        $service->update($this->validated($request, $service));

        return back()->with('status', 'Numbers saved.');
    }

    /**
     * Save who was present among the members shown on the page (others are left as they were).
     */
    public function mark(Request $request, Service $service): RedirectResponse
    {
        $data = $request->validate([
            'shown' => ['array'],
            'shown.*' => ['integer'],
            'present' => ['array'],
            'present.*' => ['integer', Rule::in($request->input('shown', []))],
        ]);
        $shown = Member::active()->whereKey($data['shown'] ?? [])->pluck('id');
        $present = $shown->intersect($data['present'] ?? []);

        DB::transaction(function () use ($service, $shown, $present) {
            $service->members()->detach($shown->diff($present)->all());
            $service->members()->syncWithoutDetaching($present->all());

            // Everyone shown gets their "last attended" recalculated from what's recorded.
            foreach ($shown as $id) {
                $last = DB::table('member_service')->join('services', 'services.id', '=', 'member_service.service_id')
                    ->where('member_id', $id)->max('services.held_on');
                Member::whereKey($id)->update(['last_attended_on' => $last ? substr($last, 0, 10) : null]);
            }
        });

        return back()->with('status', $present->count().' marked present.');
    }

    public function destroy(Service $service): RedirectResponse
    {
        $ids = $service->members()->pluck('members.id');
        $service->delete();
        foreach ($ids as $id) {
            $last = DB::table('member_service')->join('services', 'services.id', '=', 'member_service.service_id')->where('member_id', $id)->max('services.held_on');
            Member::whereKey($id)->update(['last_attended_on' => $last ? substr($last, 0, 10) : null]);
        }

        return redirect()->route('admin.attendance.index')->with('status', 'Service removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Service $service = null): array
    {
        $data = $request->validate([
            'held_on' => ['required', 'date', 'before_or_equal:today'],
            'kind' => ['required', Rule::in(array_keys(Service::KINDS))],
            'title' => ['nullable', 'string', 'max:120'],
            'in_person' => ['required', 'integer', 'min:0', 'max:100000'],
            'online' => ['required', 'integer', 'min:0', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $taken = Service::whereDate('held_on', $data['held_on'])->where('kind', $data['kind'])->when($service, fn ($q) => $q->whereKeyNot($service->id))->exists();
        if ($taken) {
            throw ValidationException::withMessages(['held_on' => 'That service is already recorded. Open it to change the numbers.']);
        }

        return $data;
    }
}
