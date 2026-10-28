<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Fellowship;
use App\Models\Member;
use App\Models\TokenPlan;
use App\Models\Unit;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * The church register.
 */
class MemberController extends Controller
{
    private const TABS = ['active' => 'Members', 'pending' => 'Waiting for approval', 'inactive' => 'Inactive'];

    public function index(Request $request): View
    {
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'active';

        return view('admin.members.index', [
            'members' => $this->filtered($request, $tab)->with('unit', 'fellowship', 'tokenPlan', 'tokenPayments')->orderBy('name')->paginate(25)->withQueryString(),
            'tab' => $tab,
            'tabs' => collect(self::TABS)->map(fn ($label, $key) => ['label' => $label, 'count' => Member::where('status', $key)->count()]),
            'units' => Unit::orderBy('name')->get(),
            'fellowships' => Fellowship::orderBy('name')->get(),
            'filters' => $request->only(['q', 'unit', 'fellowship']),
            'totals' => [
                'members' => Member::active()->count(),
                'units' => Unit::count(),
                'fellowships' => Fellowship::count(),
            ],
        ]);
    }

    public function show(Member $member): View
    {
        return view('admin.members.show', [
            'member' => $member->load('unit', 'fellowship', 'tokenPlan', 'tokenPayments', 'user.shop'),
            'givingThisYear' => (int) $member->gifts()->paid()->whereYear('given_on', now()->year)->sum('amount'),
            'recentServices' => $member->services()->orderByDesc('held_on')->take(5)->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.members.form', $this->formData(new Member(['joined_on' => today()])));
    }

    public function store(Request $request): RedirectResponse
    {
        $member = new Member($this->validated($request));
        $member->status = Member::ACTIVE;
        $member->save();
        $member->approve();

        return redirect()->route('admin.members.show', $member)->with('status', "{$member->name} added to the register.");
    }

    public function edit(Member $member): View
    {
        return view('admin.members.form', $this->formData($member));
    }

    public function update(Request $request, Member $member): RedirectResponse
    {
        $data = $this->validated($request, $member);
        $member->fill($data);
        $member->status = $request->validate(['status' => ['required', Rule::in(array_keys(self::TABS))]])['status'];
        $member->save();

        if ($member->status === Member::ACTIVE) {
            $member->approve(); // fills in plan and token start if they're missing
        }

        return redirect()->route('admin.members.show', $member)->with('status', 'Saved.');
    }

    public function approve(Member $member): RedirectResponse
    {
        $member->approve();

        return back()->with('status', "{$member->name} is now a member.");
    }

    public function destroy(Member $member): RedirectResponse
    {
        $member->delete();

        return redirect()->route('admin.members.index')->with('status', "{$member->name} removed from the register.");
    }

    public function export(Request $request): StreamedResponse
    {
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'active';
        $query = $this->filtered($request, $tab)->with('unit', 'fellowship', 'tokenPlan', 'tokenPayments')->orderBy('name');

        return Csv::download('members-'.now()->format('Y-m-d').'.csv',
            ['name', 'phone', 'email', 'gender', 'birthday', 'address', 'unit', 'fellowship', 'status', 'joined_on', 'token_plan', 'tokens', 'baptised', 'believers_class', 'workers_training', 'last_attended_on'],
            function ($write) use ($query) {
                $query->each(fn (Member $m) => $write([
                    $m->name, $m->phone, $m->email, $m->gender, $m->birthday?->toDateString(), $m->address, $m->unit?->name, $m->fellowship?->name,
                    $m->status, $m->joined_on?->toDateString(), $m->tokenPlan?->name, $m->tokenStatus(),
                    $m->baptised ? 'yes' : 'no', $m->believers_class ? 'yes' : 'no', $m->workers_training ? 'yes' : 'no', $m->last_attended_on?->toDateString(),
                ]));
            });
    }

    /**
     * Add many people at once from a spreadsheet. Columns are matched by their heading.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']], [
            'file.mimes' => 'Upload a .csv file (in Excel or Google Sheets: File › Save as / Download › CSV).',
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = array_map(fn ($h) => Str::snake(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), fgetcsv($handle) ?: []);
        if (! in_array('name', $header, true)) {
            fclose($handle);

            return back()->withErrors(['file' => 'The first row must be headings, including a "name" column.']);
        }

        $units = Unit::pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [Str::lower($name) => $id]);
        $fellowships = Fellowship::pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [Str::lower($name) => $id]);
        $added = $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($header, array_pad(array_slice($row, 0, count($header)), count($header), null));
            $name = trim((string) ($data['name'] ?? ''));
            $email = Str::lower(trim((string) ($data['email'] ?? '')));
            $phone = trim((string) ($data['phone'] ?? ''));

            $duplicate = ($email !== '' && Member::where('email', $email)->exists()) || ($phone !== '' && Member::where('phone', $phone)->exists());
            if ($name === '' || mb_strlen($name) > 100 || $duplicate || ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL))) {
                $skipped++;

                continue;
            }

            $member = new Member([
                'name' => $name,
                'email' => $email ?: null,
                'phone' => Str::limit($phone, 40, '') ?: null,
                'gender' => in_array(Str::lower((string) ($data['gender'] ?? '')), ['female', 'male'], true) ? Str::lower($data['gender']) : null,
                'birthday' => $this->date($data['birthday'] ?? null),
                'address' => Str::limit(trim((string) ($data['address'] ?? '')), 255, '') ?: null,
                'unit_id' => $units[Str::lower(trim((string) ($data['unit'] ?? '')))] ?? null,
                'fellowship_id' => $fellowships[Str::lower(trim((string) ($data['fellowship'] ?? '')))] ?? null,
                'joined_on' => $this->date($data['joined_on'] ?? null),
            ]);
            $member->status = Member::ACTIVE;
            $member->save();
            $member->approve();
            $added++;
        }
        fclose($handle);

        return redirect()->route('admin.members.index')->with('status', "Imported {$added} ".Str::plural('member', $added).'.'.($skipped ? " Skipped {$skipped} (no name, a bad email, or already on the register)." : ''));
    }

    private function filtered(Request $request, string $tab)
    {
        return Member::query()
            ->where('status', $tab)
            ->search($request->query('q'))
            ->when($request->filled('unit'), fn ($q) => $q->where('unit_id', $request->integer('unit')))
            ->when($request->filled('fellowship'), fn ($q) => $q->where('fellowship_id', $request->integer('fellowship')));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Member $member = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('members', 'email')->ignore($member?->id)],
            'gender' => ['nullable', Rule::in(['female', 'male'])],
            'birthday' => ['nullable', 'date', 'before:today'],
            'address' => ['nullable', 'string', 'max:255'],
            'unit_id' => ['nullable', Rule::exists('units', 'id')],
            'fellowship_id' => ['nullable', Rule::exists('fellowships', 'id')],
            'token_plan_id' => ['nullable', Rule::exists('token_plans', 'id')],
            'tokens_from' => ['nullable', 'date'],
            'joined_on' => ['nullable', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], ['email.unique' => 'Someone on the register already has that email.']);

        foreach (array_keys(Member::MILESTONES) as $milestone) {
            $data[$milestone] = $request->boolean($milestone);
        }
        if (! empty($data['tokens_from'])) {
            $data['tokens_from'] = Carbon::parse($data['tokens_from'])->startOfMonth();
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Member $member): array
    {
        return [
            'member' => $member,
            'units' => Unit::orderBy('name')->get(),
            'fellowships' => Fellowship::orderBy('name')->get(),
            'plans' => TokenPlan::orderByDesc('amount')->get(),
            'statuses' => self::TABS,
        ];
    }

    private function date(?string $value): ?string
    {
        if (! $value || trim($value) === '') {
            return null;
        }
        try {
            $date = Carbon::parse(str_replace('/', '-', trim($value)));

            return $date->isFuture() ? null : $date->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
