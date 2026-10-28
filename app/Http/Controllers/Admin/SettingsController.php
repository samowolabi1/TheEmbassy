<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\View\View;

/**
 * Church details shown on the site, and who can use the admin dashboard.
 */
class SettingsController extends Controller
{
    public function edit(): View
    {
        $saved = Setting::values();

        return view('admin.settings', [
            'groups' => config('settings'),
            // What the site shows now: the saved value, or the default if it's a real value (not a placeholder).
            // Secrets are never sent back to the page; the form just says whether one is saved.
            'values' => collect(Setting::keys())->mapWithKeys(fn ($key) => [$key => in_array($key, Setting::secretKeys(), true) ? null : ($saved[$key] ?? $this->defaultFor($key))])->all(),
            'savedSecrets' => collect(Setting::secretKeys())->filter(fn ($key) => filled($saved[$key] ?? null) || filled(config($key)))->values()->all(),
            'staff' => User::whereNotNull('role')->orderBy('name')->get(),
            'roles' => config('roles.roles'),
            'areas' => config('roles.areas'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (config('settings') as $group) {
            foreach ($group['fields'] as $key => $field) {
                $rules[$this->inputName($key)] = [...match ($field['type']) {
                    'url' => ['nullable', 'url:http,https', 'max:500'],
                    'email' => ['nullable', 'email', 'max:255'],
                    'checkbox' => ['nullable', 'boolean'],
                    'textarea' => ['nullable', 'string', 'max:1000'],
                    'number' => ['nullable'],
                    'secret' => ['nullable', 'string', 'max:1000'],
                    default => ['nullable', 'string', 'max:255'],
                }, ...($field['rules'] ?? [])];
            }
        }
        $rules[$this->inputName('church.map_embed_url')][] = 'starts_with:https://www.google.com/maps/embed';
        $rules[$this->inputName('church.live.video_id')][] = 'regex:/^[A-Za-z0-9_-]{6,20}$/';

        $data = $request->validate($rules, [
            $this->inputName('church.map_embed_url').'.starts_with' => 'Use the link from Google Maps › Share › Embed a map (it starts with https://www.google.com/maps/embed).',
            $this->inputName('church.live.video_id').'.regex' => 'Paste just the video ID, e.g. dQw4w9WgXcQ.',
        ]);

        $values = [];
        foreach (Setting::secretKeys() as $key) {
            // Leave a saved secret alone unless a new one is typed in, or it's being removed.
            $value = $data[$this->inputName($key)] ?? null;
            if ($request->boolean('clear__'.$this->inputName($key))) {
                $values[$key] = null;
            } elseif (filled($value)) {
                $values[$key] = Crypt::encryptString($value);
            }
        }
        foreach (array_diff(Setting::keys(), Setting::secretKeys()) as $key) {
            $value = $data[$this->inputName($key)] ?? null;
            $type = collect(config('settings'))->firstWhere(fn ($g) => isset($g['fields'][$key]))['fields'][$key]['type'];
            $values[$key] = $type === 'checkbox' ? ($value ? '1' : '0') : $value;
        }
        Setting::putMany($values);

        return redirect()->route('admin.settings.edit')->with('status', 'Settings saved. The website shows the new details now.');
    }

    /**
     * Dots in field names would nest the input, so keys are sent with "__" instead.
     */
    private function inputName(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    private function defaultFor(string $key): ?string
    {
        $value = config($key);
        if (! is_scalar($value) || $value === '#' || preg_match('/\[[^\]]+\]/', (string) $value)) {
            return null;
        }

        return (string) $value;
    }
}
