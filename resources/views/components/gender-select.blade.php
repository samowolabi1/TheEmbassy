{{-- Gender, required on every form that adds or edits a person. --}}
@props(['value' => null, 'oldForm' => null])
@php
    $useOld = $oldForm === null || old('_form') === $oldForm;
    $value = $useOld ? old('gender', $value) : $value;
@endphp
<label class="field">Gender
    <select name="gender" required>
        <option value="" disabled @selected(! in_array($value, ['female', 'male'], true))>Choose</option>
        <option value="female" @selected($value === 'female')>Female</option>
        <option value="male" @selected($value === 'male')>Male</option>
    </select>
    @error('gender') <span class="field-error">{{ $message }}</span> @enderror
</label>
