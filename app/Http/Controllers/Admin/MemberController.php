<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Fellowship;
use App\Models\Member;
use App\Models\TokenTransfer;
use App\Models\Unit;
use App\Services\Care\FollowUps;
use App\Services\People\Logins;
use App\Services\People\Ranks;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            'members' => $this->filtered($request, $tab)->with('units', 'fellowship', 'user.tokenAccount')->orderBy('name')->paginate(25)->withQueryString(),
            'tab' => $tab,
            'tabs' => collect(self::TABS)->map(fn ($label, $key) => ['label' => $label, 'count' => Member::where('status', $key)->count()]),
            'units' => Unit::orderBy('name')->get(),
            'fellowships' => Fellowship::orderBy('name')->get(),
            'filters' => $request->only(['q', 'unit', 'fellowship', 'rank', 'training', 'login']),
            'withoutLogin' => Member::active()->whereNull('user_id')->whereNotNull('email')->where('email', '!=', '')->count(),
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
            'member' => $member->load('unit', 'units', 'fellowship', 'user.shop'),
            'tokenAccount' => $account = $member->user?->tokenAccount,
            'tokenHistory' => $account ? TokenTransfer::where(fn ($q) => $q->where('from_account_id', $account->id)->orWhere('to_account_id', $account->id))->latest('id')->take(8)->get() : collect(),
            'givingThisYear' => request()->user()->can('see-giving') ? (int) $member->gifts()->paid()->whereYear('given_on', now()->year)->sum('amount') : null,
            'recentServices' => $member->services()->orderByDesc('held_on')->take(5)->get(),
        ]);
    }

    /**
     * Send the member a link to set their password (making their login if they have none).
     */
    public function inviteLogin(Member $member, Logins $logins): RedirectResponse
    {
        $went = $logins->invite($member);

        return back()->with('status', $went
            ? 'Sent '.$member->firstName().' a link to choose a password, by '.implode(' and ', $went).'. It works for 7 days.'
            : 'The link couldn\'t be sent: check email is set up in Settings & roles, or give a temporary password instead.');
    }

    /**
     * Give the member a temporary password to pass on; they choose their own when they sign in.
     */
    public function temporaryPassword(Member $member, Logins $logins): RedirectResponse
    {
        $password = $logins->temporaryPassword($member);

        return redirect()->to(route('admin.members.show', $member).'#login')->with('tempPassword', $password)->with('status', 'Temporary password made for '.$member->firstName().'. It\'s shown once below.');
    }

    /**
     * After adding someone: send their link or make a temporary password, as chosen on the form.
     */
    public function giveLogin(Request $request, Member $member, string $status): RedirectResponse
    {
        $choice = in_array($request->input('login'), ['link', 'temporary', 'none'], true) ? $request->input('login') : 'none';
        $to = redirect()->route('admin.members.show', $member);
        try {
            if ($choice === 'link' && $member->email) {
                $went = app(Logins::class)->invite($member);

                return $to->with('status', $status.($went ? ' They\'ve been sent a link to choose a password.' : ' The login link couldn\'t be sent; try again from their profile.'));
            }
            if ($choice === 'temporary' || ($choice === 'link' && ! $member->email && $member->phone)) {
                return redirect()->to(route('admin.members.show', $member).'#login')->with('tempPassword', app(Logins::class)->temporaryPassword($member))
                    ->with('status', $status.($choice === 'link' ? ' They have no email, so here\'s a temporary password to pass on instead.' : ' Here\'s their temporary password.'));
            }
        } catch (ValidationException $e) {
            return $to->with('status', $status)->withErrors($e->errors());
        }

        return $to->with('status', $status);
    }

    /**
     * Links for every approved member with an email and no login yet.
     */
    public function inviteAll(Logins $logins): RedirectResponse
    {
        $members = Member::active()->whereNull('user_id')->whereNotNull('email')->where('email', '!=', '')->get();
        $sent = 0;
        foreach ($members as $member) {
            try {
                $logins->invite($member) && $sent++;
            } catch (ValidationException) {
                // e.g. their email is already used by someone else's login: skipped
            }
        }

        return back()->with('status', "Sent set-password links to {$sent} ".Str::plural('member', $sent).'.'.($members->count() > $sent ? ' '.($members->count() - $sent).' couldn\'t be sent; open their profiles to see why.' : ''));
    }

    public function create(): View
    {
        return view('admin.members.form', $this->formData(new Member(['joined_on' => today()])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $member = new Member(collect($data)->except('units')->all());
        $member->status = Member::ACTIVE;
        $member->save();
        $member->syncUnits($data['units'] ?? []);
        $member->forceFill(['training_answered_at' => now()])->save(); // the training ticks on the form are the answers
        $member->approve();

        return $this->giveLogin($request, $member, "{$member->name} added to the register.");
    }

    public function edit(Member $member): View
    {
        return view('admin.members.form', $this->formData($member));
    }

    public function update(Request $request, Member $member): RedirectResponse
    {
        $data = $this->validated($request, $member);
        $wasPending = $member->status === Member::PENDING;
        $rankBefore = $member->rank;
        $member->fill(collect($data)->except('units')->all());
        $member->status = $request->validate(['status' => ['required', Rule::in(array_keys(self::TABS))]])['status'];
        $member->save();
        // Units they lead stay: change the head or assistant on Units & fellowships first.
        $member->syncUnits([...($data['units'] ?? []), ...$member->ledUnits()->modelKeys()]);
        $member->forceFill(['training_answered_at' => now()])->save(); // the training ticks on the form are the answers
        if ($rankBefore === 'member' && $member->rank === 'worker' && ! $wasPending) {
            app(Ranks::class)->tell($member->fresh('unit', 'user'), 'member');
        }

        if ($member->status === Member::ACTIVE) {
            $member->approve(); // and their welcome tokens
            if ($wasPending) {
                app(FollowUps::class)->forNewMember($member);
            }
        }

        return redirect()->route('admin.members.show', $member)->with('status', 'Saved.');
    }

    public function approve(Member $member): RedirectResponse
    {
        $member->approve(welcome: true);

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
        $query = $this->filtered($request, $tab)->with('units', 'fellowship', 'user.tokenAccount')->orderBy('name');

        return Csv::download('members-'.now()->format('Y-m-d').'.csv',
            ['name', 'phone', 'email', 'gender', 'birthday', 'address', 'unit', 'fellowship', 'status', 'joined_on', 'tokens', 'role', 'baptised', 'believers_class', 'workers_training', 'sod', 'bible_college', 'last_attended_on'],
            function ($write) use ($query) {
                $query->each(fn (Member $m) => $write([
                    $m->name, $m->phone, $m->email, $m->gender, $m->birthday?->toDateString(), $m->address, $m->unitNames(), $m->fellowship?->name,
                    $m->status, $m->joined_on?->toDateString(), (int) $m->user?->tokenBalance(),
                    $m->rankLabel(), $m->baptised ? 'yes' : 'no', $m->believers_class ? 'yes' : 'no', $m->workers_training ? 'yes' : 'no', $m->sod ? 'yes' : 'no', $m->bible_college ? 'yes' : 'no', $m->last_attended_on?->toDateString(),
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
        if (! in_array('name', $header, true) && ! in_array('first_name', $header, true)) {
            fclose($handle);

            return back()->withErrors(['file' => 'The first row must be headings, including "first_name" and "last_name" (or one "name" column).']);
        }

        $units = Unit::pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [Str::lower($name) => $id]);
        $fellowships = Fellowship::pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [Str::lower($name) => $id]);
        $added = $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($header, array_pad(array_slice($row, 0, count($header)), count($header), null));
            $first = trim((string) ($data['first_name'] ?? ''));
            $last = trim((string) ($data['last_name'] ?? ''));
            $name = $first !== '' ? trim($first.' '.$last) : trim((string) ($data['name'] ?? ''));
            $email = Str::lower(trim((string) ($data['email'] ?? '')));
            $phone = trim((string) ($data['phone'] ?? ''));

            $duplicate = ($email !== '' && Member::where('email', $email)->exists()) || ($phone !== '' && Member::where('phone', $phone)->exists());
            if ($name === '' || mb_strlen($name) > 100 || $duplicate || ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL))) {
                $skipped++;

                continue;
            }

            $member = new Member([
                'name' => $name,
                ...($first !== '' ? ['first_name' => $first, 'last_name' => $last ?: null] : []),
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

    /**
     * A pastor makes a worker a minister, answering the minister's training questions, or makes
     * a minister not one. Workers come from units (see Member).
     */
    public function rank(Request $request, Member $member, Ranks $ranks): RedirectResponse
    {
        $to = $request->validate(['rank' => ['required', Rule::in(['minister', 'worker'])]])['rank'];
        $rules = [];
        if ($to === 'minister') {
            $rules['training'] = ['required', 'array'];
            foreach (Member::REQUIREMENTS['minister'] as $field) {
                $rules["training.{$field}"] = ['required', Rule::in(['0', '1'])];
            }
        }
        $answers = $request->validate($rules, ['training.required' => 'Answer the training questions.', 'training.*.required' => 'Answer each training question: done or not yet.'])['training'] ?? [];
        $ranks->change($member, $to, $request->user(), $answers);
        $member->refresh();
        $missing = $member->missingRequirements();

        return back()->with('status', "{$member->name} is now: ".$member->rankLine().'.'.($missing ? ' Still to do: '.implode(', ', $missing).'.' : ''));
    }

    private function filtered(Request $request, string $tab)
    {
        return Member::query()
            ->where('status', $tab)
            ->search($request->query('q'))
            ->when($request->filled('unit'), fn ($q) => $q->whereHas('units', fn ($u) => $u->whereKey($request->integer('unit'))))
            ->when($request->filled('fellowship'), fn ($q) => $q->where('fellowship_id', $request->integer('fellowship')))
            ->when(array_key_exists((string) $request->query('rank'), Member::RANKS), fn ($q) => $q->where('rank', $request->query('rank')))
            ->when($request->query('login') === 'none', fn ($q) => $q->whereNull('user_id'))
            ->when($request->query('login') === 'yes', fn ($q) => $q->whereNotNull('user_id'))
            ->when($request->query('training') === 'unanswered', fn ($q) => $q->whereIn('rank', ['worker', 'minister'])->whereNull('training_answered_at'))
            ->when($request->query('training') === 'missing', fn ($q) => $q->where(function ($q) {
                foreach (Member::REQUIREMENTS as $rank => $fields) {
                    $q->orWhere(fn ($r) => $r->where('rank', $rank)->where(fn ($f) => collect($fields)->each(fn ($field) => $f->orWhere($field, false))));
                }
            }));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Member $member = null): array
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'name' => ['nullable', 'string', 'max:121'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('members', 'email')->ignore($member?->id)],
            'gender' => ['required', Rule::in(['female', 'male'])],
            'birthday' => ['nullable', 'date', 'before:today'],
            'anniversary' => ['nullable', 'date', 'before_or_equal:today'],
            'address' => ['nullable', 'string', 'max:255'],
            'units' => ['nullable', 'array'],
            'units.*' => [Rule::exists('units', 'id')],
            'fellowship_id' => ['nullable', Rule::exists('fellowships', 'id')],
            'joined_on' => ['nullable', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], ['email.unique' => 'Someone on the register already has that email.']);

        foreach (array_keys(Member::MILESTONES) as $milestone) {
            $data[$milestone] = $request->boolean($milestone);
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
            'statuses' => self::TABS,
        ];
    }

    private function date(?string $value): ?string
    {
        if (! $value || trim($value) === '') {
            return null;
        }
        try {
            $value = trim($value);
            // Day and month only ("15/11", "15 Nov"): the year is optional.
            if (preg_match('#^(\d{1,2})[/.-](\d{1,2})$#', $value, $m)) {
                return checkdate((int) $m[2], (int) $m[1], Member::NO_BIRTH_YEAR) ? sprintf('%04d-%02d-%02d', Member::NO_BIRTH_YEAR, $m[2], $m[1]) : null;
            }
            $date = Carbon::parse(str_replace('/', '-', $value));
            if (! preg_match('/\d{4}/', $value)) {
                $date->year(Member::NO_BIRTH_YEAR);
            }

            return $date->isFuture() ? null : $date->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
