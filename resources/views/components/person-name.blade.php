{{--
    First name and last name boxes, side by side in a form grid. The two are
    also joined into `name` by App\Http\Middleware\CombineName.
    oldForm: on pages with several forms, only refill from the form that was sent.
--}}
@props(['first' => null, 'last' => null, 'oldForm' => null, 'autocomplete' => true])
@php
    $useOld = $oldForm === null || old('_form') === $oldForm;
    $first = $useOld ? old('first_name', $first) : $first;
    $last = $useOld ? old('last_name', $last) : $last;
@endphp
<label class="field">First name
    <input type="text" name="first_name" value="{{ $first }}" required maxlength="60" autocomplete="{{ $autocomplete ? 'given-name' : 'off' }}">
    @error('first_name') <span class="field-error">{{ $message }}</span> @enderror
</label>
<label class="field">Last name
    <input type="text" name="last_name" value="{{ $last }}" required maxlength="60" autocomplete="{{ $autocomplete ? 'family-name' : 'off' }}">
    @error('last_name') <span class="field-error">{{ $message }}</span> @enderror
</label>
