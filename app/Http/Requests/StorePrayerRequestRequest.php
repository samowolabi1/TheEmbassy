<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePrayerRequestRequest extends FormRequest
{
    /**
     * Anyone can send a prayer request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:100'],
            'contact' => ['nullable', 'string', 'max:150'],
            'request' => ['required', 'string', 'max:5000'],
            'is_private' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'request.required' => 'Please tell us what we can pray with you about.',
        ];
    }

    /**
     * Send people back to the form on the home page when something is missing.
     */
    protected function getRedirectUrl(): string
    {
        return route('home').'#prayer';
    }
}
