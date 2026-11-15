{{-- The training questions for a worker or minister: each required class, done or not yet. --}}
@props(['rank' => 'worker', 'member' => null, 'name' => 'training'])
@php
    $questions = array_intersect_key(\App\Models\Member::MILESTONES, array_flip(\App\Models\Member::REQUIREMENTS[$rank] ?? []));
@endphp
<fieldset {{ $attributes->class('training-q') }}>
    <legend>Training {{ $rank === 'minister' ? 'a minister' : 'a worker' }} needs</legend>
    @foreach ($questions as $field => $label)
        @php
            $known = $member && ($member->{$field} || $member->training_answered_at) ? ($member->{$field} ? '1' : '0') : null;
            $value = old("{$name}.{$field}", $known);
        @endphp
        <div class="training-q__row" role="radiogroup" aria-label="{{ $label }}">
            <span class="training-q__label">{{ $label }}</span>
            <span class="training-q__choices">
                <label><input type="radio" name="{{ $name }}[{{ $field }}]" value="1" required @checked($value === '1')> Done</label>
                <label><input type="radio" name="{{ $name }}[{{ $field }}]" value="0" required @checked($value === '0')> Not yet</label>
            </span>
        </div>
    @endforeach
    @error($name.'.*') <span class="field-error">{{ $message }}</span> @enderror
    @error($name) <span class="field-error">{{ $message }}</span> @enderror
</fieldset>
