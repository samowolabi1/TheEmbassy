<?php

namespace App\Http\Controllers;

use App\Models\EmpowermentApplication;
use App\Models\EmpowermentProgramme;
use App\Models\Member;
use App\Services\Empowerment\Notices;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The public youth empowerment page: the programme's banner and story, the choices on offer
 * and the application form.
 */
class EmpowermentController extends Controller
{
    public function current(Request $request): View
    {
        $programme = EmpowermentProgramme::current();

        return $programme ? $this->show($request, $programme) : view('empowerment.none');
    }

    public function show(Request $request, EmpowermentProgramme $programme): View
    {
        $preview = ! $programme->is_published;
        abort_if($preview && ! $request->user()?->canManage('empowerment'), 404);
        $member = $request->user()?->member;

        return view('empowerment.show', [
            'programme' => $programme,
            'tracks' => $programme->tracks()->where('is_active', true)->withCount('accepted')->get(),
            'questions' => $programme->questions()->get(),
            'member' => $member,
            'preview' => $preview,
            'applied' => session('applied') ? EmpowermentApplication::with('choices')->find(session('applied')) : null,
        ]);
    }

    public function apply(Request $request, EmpowermentProgramme $programme, Notices $notices): RedirectResponse
    {
        abort_unless($programme->isOpen(), 422, 'Applications for this programme aren\'t open.');
        if (filled($request->input('website'))) {
            return back(); // a bot filled the hidden field
        }
        $questions = $programme->questions()->get();
        $trackIds = $programme->tracks()->where('is_active', true)->pluck('id')->all();
        $needsBirthday = $programme->min_age || $programme->max_age;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'gender' => ['nullable', Rule::in(['female', 'male'])],
            'date_of_birth' => [$needsBirthday ? 'required' : 'nullable', 'date', 'before:today', 'after:1920-01-01'],
            'location' => ['nullable', 'string', 'max:150'],
            'choices' => ['required', 'array', 'min:1', 'max:'.$programme->max_choices],
            'choices.*' => ['nullable', 'distinct', Rule::in($trackIds)],
            'agree' => ['accepted'],
            ...$questions->flatMap->rules()->all(),
        ], [
            'choices.required' => 'Choose what you\'d like to do.',
            'choices.max' => 'Choose up to '.$programme->max_choices.'.',
            'choices.*.distinct' => 'Choose a different option for each choice.',
            'agree.accepted' => 'Please agree to how we use your details.',
            'date_of_birth.required' => 'Your date of birth tells us you\'re in the age range.',
            'answers.*.required' => 'Please answer this question.',
        ], collect($questions)->mapWithKeys(fn ($q) => ['answers.'.$q->key() => '"'.$q->label.'"'])->all());

        $choices = array_values(array_filter($data['choices']));
        if (! $choices) {
            throw ValidationException::withMessages(['choices' => 'Choose what you\'d like to do.']);
        }
        if ($needsBirthday && ($age = Carbon::parse($data['date_of_birth'])->age)
            && (($programme->min_age && $age < $programme->min_age) || ($programme->max_age && $age > $programme->max_age))) {
            throw ValidationException::withMessages(['date_of_birth' => $programme->ageRule().'. You can still ask the church office about other ways to get involved.']);
        }
        $phone = Member::internationalPhone($data['phone']);
        $already = $programme->applications()->get(['reference', 'phone'])->first(fn ($a) => $phone && Member::internationalPhone($a->phone) === $phone);
        if ($already) {
            throw ValidationException::withMessages(['phone' => "You've already applied with this number (reference {$already->reference}). We'll be in touch."]);
        }

        $application = DB::transaction(function () use ($programme, $data, $choices, $questions, $request) {
            $application = new EmpowermentApplication([
                ...collect($data)->only(['name', 'phone', 'email', 'gender', 'date_of_birth', 'location'])->all(),
                'answers' => $questions->mapWithKeys(fn ($q) => [$q->key() => $data['answers'][$q->key()] ?? null])->filter(fn ($v) => $v !== null && $v !== '' && $v !== [])->all(),
            ]);
            $application->empowerment_programme_id = $programme->id;
            $application->user_id = $request->user()?->id;
            $application->member_id = $request->user()?->member?->id;
            $application->save();
            foreach ($choices as $i => $trackId) {
                $application->choices()->attach($trackId, ['rank' => $i + 1]);
            }

            return $application;
        });
        $notices->received($application->load('choices', 'programme'));

        return redirect()->to(route('empowerment.show', $programme).'#apply')->with('applied', $application->id);
    }

    /**
     * An applicant's own view of their application, from the link in their emails.
     */
    public function status(EmpowermentApplication $application): View
    {
        return view('empowerment.status', ['application' => $application->load('programme', 'choices', 'track')]);
    }
}
