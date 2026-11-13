@extends('layouts.unit', ['title' => $design->name, 'active' => 'birthdays'])

@php
    $previewUrl = route('unit.birthdays.preview', [$unit, 'design' => $design->key()]);
    $range = fn (string $key, string $label, int $min, int $max, string $unitLabel = '%') => compact('key', 'label', 'min', 'max', 'unitLabel');
@endphp

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <a href="{{ route('unit.birthdays.index', $unit) }}" class="box__link">← Birthday cards</a>
            <h1>Place the photo and name</h1>
            <span class="page-head__sub">Move the sliders and the preview follows, using your own name and photo as the example. Save when it looks right.</span>
        </div>
    </header>

    <form method="POST" action="{{ route('unit.birthdays.designs.update', [$unit, $design]) }}" class="row" data-design-editor data-preview="{{ $previewUrl }}">
        @csrf
        @method('PUT')
        <section class="box" style="flex: 0 1 420px">
            <img src="{{ $previewUrl }}" alt="Preview of the design" width="1080" height="1350" data-design-preview style="width: 100%; height: auto; border-radius: 16px; box-shadow: 0 10px 30px rgba(10, 29, 63, 0.18); transition: opacity 0.2s">
        </section>

        <section class="box grow form">
            <label class="field">Name of this design
                <input type="text" name="name" value="{{ $design->name }}" required maxlength="80">
            </label>

            <h2 style="margin-top: 6px">Photo</h2>
            <div class="form-row">
                <label class="field">Shape
                    <select name="layout[photo_shape]">
                        @foreach (['circle' => 'Circle', 'rounded' => 'Rounded square', 'none' => 'No photo'] as $value => $label)
                            <option value="{{ $value }}" @selected($layout['photo_shape'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field">Border colour
                    <span style="display: flex; gap: 10px; align-items: center">
                        <input type="color" name="layout[photo_border]" value="{{ $layout['photo_border'] ?? '#ffffff' }}" style="width: 56px; height: 40px; padding: 2px">
                        <label class="login__remember" style="font-weight: 600"><input type="checkbox" name="layout[photo_border_on]" value="1" @checked($layout['photo_border'])>Border</label>
                    </span>
                </label>
            </div>
            @foreach ([$range('photo_x', 'Across', 5, 95), $range('photo_y', 'Down', 5, 95), $range('photo_size', 'Size', 10, 90)] as $r)
                <label class="field">{{ $r['label'] }} <output data-for="{{ $r['key'] }}">{{ round($layout[$r['key']]) }}{{ $r['unitLabel'] }}</output>
                    <input type="range" name="layout[{{ $r['key'] }}]" min="{{ $r['min'] }}" max="{{ $r['max'] }}" step="0.5" value="{{ $layout[$r['key']] }}">
                </label>
            @endforeach

            <h2 style="margin-top: 6px">Name</h2>
            <div class="form-row">
                <label class="field">Font
                    <select name="layout[name_font]">
                        @foreach (\App\Services\Celebrations\BirthdayCard::NAME_FONTS as $key => [$file, $label])
                            <option value="{{ $key }}" @selected($layout['name_font'] === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field">Colour
                    <input type="color" name="layout[name_color]" value="{{ $layout['name_color'] }}" style="width: 56px; height: 40px; padding: 2px">
                </label>
            </div>
            <label class="field">Down <output data-for="name_y">{{ round($layout['name_y']) }}%</output>
                <input type="range" name="layout[name_y]" min="5" max="98" step="0.5" value="{{ $layout['name_y'] }}">
            </label>
            <label class="field">Size <output data-for="name_size">{{ $layout['name_size'] }}</output>
                <input type="range" name="layout[name_size]" min="24" max="160" step="1" value="{{ $layout['name_size'] }}">
            </label>
            <div style="display: flex; flex-wrap: wrap; gap: 6px 18px">
                <label class="login__remember"><input type="checkbox" name="layout[name_upper]" value="1" @checked($layout['name_upper'])>Capital letters</label>
                <label class="login__remember"><input type="checkbox" name="layout[name_shadow]" value="1" @checked($layout['name_shadow'])>Shadow</label>
            </div>

            <h2 style="margin-top: 6px">Under the name</h2>
            <div style="display: flex; flex-wrap: wrap; gap: 6px 18px; align-items: center">
                <label class="login__remember"><input type="checkbox" name="layout[show_date]" value="1" @checked($layout['show_date'])>The day (e.g. 13 NOVEMBER)</label>
                <label class="login__remember"><input type="checkbox" name="layout[show_message]" value="1" @checked($layout['show_message'])>The birthday message</label>
                <label class="field" style="flex-direction: row; align-items: center; gap: 8px">Colour
                    <input type="color" name="layout[date_color]" value="{{ $layout['date_color'] }}" style="width: 56px; height: 40px; padding: 2px">
                </label>
            </div>

            <div style="display: flex; gap: 8px; margin-top: 8px">
                <button type="submit" class="btn btn--gold">Save</button>
                <a href="{{ route('unit.birthdays.index', $unit) }}" class="btn btn--ghost">Cancel</a>
            </div>
        </section>
    </form>
@endsection
