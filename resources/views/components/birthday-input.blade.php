{{--
    Birthday as day and month, with the year optional. The three boxes are
    joined into `birthday` by App\Http\Middleware\CombineBirthday.

    Use it as the birthday-input component, with :value="$member->birthday".
    oldForm: on pages with several forms, only refill from the form that was sent.
--}}
@props(['value' => null, 'label' => 'Birthday', 'oldForm' => null, 'hint' => 'For birthday wishes from the church. The year is optional.'])
@php
    $useOld = $oldForm === null || old('_form') === $oldForm;
    $date = $value ? \Illuminate\Support\Carbon::parse($value) : null;
    $pick = fn (string $part, $fallback) => $useOld ? old('birthday_'.$part, $fallback) : $fallback;
    $day = (int) $pick('day', $date?->day);
    $month = (int) $pick('month', $date?->month);
    $year = $pick('year', $date && $date->year !== \App\Models\Member::NO_BIRTH_YEAR ? $date->year : '');
    $id = 'bday-'.substr(md5(uniqid('', true)), 0, 6);
@endphp
<div {{ $attributes->merge(['class' => 'field birthday-input']) }} role="group" aria-labelledby="{{ $id }}">
    <span id="{{ $id }}">{{ $label }} (optional)</span>
    <div class="birthday-input__boxes">
        <select name="birthday_day" aria-label="Day">
            <option value="">Day</option>
            @for ($d = 1; $d <= 31; $d++)
                <option value="{{ $d }}" @selected($day === $d)>{{ $d }}</option>
            @endfor
        </select>
        <select name="birthday_month" aria-label="Month">
            <option value="">Month</option>
            @foreach (range(1, 12) as $m)
                <option value="{{ $m }}" @selected($month === $m)>{{ \Illuminate\Support\Carbon::create(2000, $m, 1)->format('F') }}</option>
            @endforeach
        </select>
        <input type="text" name="birthday_year" value="{{ $year }}" inputmode="numeric" pattern="(19|20)[0-9]{2}" maxlength="4" placeholder="Year" aria-label="Year (optional)" autocomplete="bday-year">
    </div>
    @if ($hint)<span class="field-hint field__hint">{{ $hint }}</span>@endif
    @error('birthday') <span class="field-error">{{ $message }}</span> @enderror
</div>
