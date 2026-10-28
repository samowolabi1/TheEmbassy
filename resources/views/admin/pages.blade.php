@extends('layouts.admin', ['title' => 'Pages', 'active' => 'pages'])

@php
    $leaders = old('leaders', collect($content['leaders'] ?? [])->map(fn ($l, $i) => [...$l, 'from' => $i])->all());
    $faqs = old('faqs', $content['faqs'] ?? []);
    // Room to add two more of each.
    $leaders = [...array_values($leaders), ['from' => null, 'name' => '', 'role' => '', 'swatch' => 'navy'], ['from' => null, 'name' => '', 'role' => '', 'swatch' => 'ice']];
    $faqs = [...array_values($faqs), ['q' => '', 'a' => ''], ['q' => '', 'a' => '']];
@endphp

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Pages</h1>
            <span class="page-head__sub">The words and photos on the About page and the home page testimony.</span>
        </div>
        <div class="actions">
            <a href="{{ $page === 'about' ? route('about') : route('home').'#prayer' }}" class="btn btn--outline" target="_blank">View on the website</a>
        </div>
    </header>

    <nav class="tabs" aria-label="Page">
        <a href="{{ route('admin.pages.edit', 'about') }}" class="tab" @if ($page === 'about') aria-current="page" @endif>About page</a>
        <a href="{{ route('admin.pages.edit', 'home') }}" class="tab" @if ($page === 'home') aria-current="page" @endif>Home page</a>
    </nav>

    @if ($errors->any())
        <p class="flash flash--error" role="alert">Please check the highlighted fields.</p>
    @endif

    <form method="POST" action="{{ route('admin.pages.update', $page) }}" enctype="multipart/form-data" class="stack" style="gap: 16px" data-unsaved-warning>
        @csrf
        @method('PUT')

        @if ($page === 'about')
            <section class="box form">
                <h2>Top of the page</h2>
                <label class="field">Headline
                    <input type="text" name="hero_title" value="{{ old('hero_title', $content['hero_title']) }}" required maxlength="120">
                    @error('hero_title') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="field">Introduction
                    <textarea name="hero_text" maxlength="400">{{ old('hero_text', $content['hero_text']) }}</textarea>
                    <span class="field__hint"><code>:church</code> becomes "{{ config('church.short_name') }}".</span>
                </label>
            </section>

            <section class="box form">
                <h2>Our story</h2>
                <label class="field">Heading
                    <input type="text" name="story_heading" value="{{ old('story_heading', $content['story_heading']) }}" required maxlength="120">
                </label>
                <label class="field">Story
                    <textarea name="story" maxlength="4000" style="height: 220px">{{ old('story', $content['story']) }}</textarea>
                    <span class="field__hint">Leave a blank line between paragraphs.</span>
                    @error('story') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <div class="form-row">
                    <div class="field">
                        <span>Photo</span>
                        @if ($content['story_photo'])
                            <img src="{{ \App\Models\PageContent::photoUrl($content['story_photo']) }}" alt="" class="cover-preview">
                            <label class="login__remember"><input type="checkbox" name="remove_story_photo" value="1">Remove this photo</label>
                        @endif
                        <input type="file" name="story_photo" accept="image/jpeg,image/png,image/webp" class="file-input">
                        @error('story_photo') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <label class="field">Describe the photo
                        <input type="text" name="story_photo_alt" value="{{ old('story_photo_alt', $content['story_photo_alt'] ?? '') }}" maxlength="200" placeholder="e.g. The Tribe worshipping on Sunday">
                    </label>
                </div>
            </section>

            <section class="box form">
                <h2>Mission, vision and values</h2>
                <div class="form-row">
                    <label class="field">Mission <textarea name="mission" maxlength="300">{{ old('mission', $content['mission']) }}</textarea></label>
                    <label class="field">Vision <textarea name="vision" maxlength="300">{{ old('vision', $content['vision']) }}</textarea></label>
                </div>
                <label class="field">Values
                    <textarea name="values" maxlength="1000">{{ old('values', implode("\n", $content['values'] ?? [])) }}</textarea>
                    <span class="field__hint">One per line, up to 10.</span>
                </label>
            </section>

            <section class="box form">
                <h2>Leaders</h2>
                <span class="box__note">Shown in this order. Clear a name to remove that leader.</span>
                @foreach ($leaders as $i => $leader)
                    <fieldset class="repeat-row">
                        <legend class="sr-only">Leader {{ $i + 1 }}</legend>
                        <input type="hidden" name="leaders[{{ $i }}][from]" value="{{ $leader['from'] ?? '' }}">
                        @php $photo = isset($leader['from']) && $leader['from'] !== '' ? ($content['leaders'][(int) $leader['from']]['photo'] ?? null) : null; @endphp
                        @if ($photo)
                            <img src="{{ \App\Models\PageContent::photoUrl($photo) }}" alt="" class="repeat-row__photo">
                        @else
                            <span class="repeat-row__photo bg-{{ $leader['swatch'] ?? 'navy' }}" aria-hidden="true"></span>
                        @endif
                        <div class="form-row grow">
                            <label class="field">Name <input type="text" name="leaders[{{ $i }}][name]" value="{{ $leader['name'] ?? '' }}" maxlength="100"></label>
                            <label class="field">Role
                                <input type="text" name="leaders[{{ $i }}][role]" value="{{ $leader['role'] ?? '' }}" maxlength="100">
                                @error("leaders.{$i}.role") <span class="field-error">{{ $message }}</span> @enderror
                            </label>
                            <label class="field">Colour behind photo
                                <select name="leaders[{{ $i }}][swatch]">
                                    @foreach ($swatches as $swatch)
                                        <option value="{{ $swatch }}" @selected(($leader['swatch'] ?? 'navy') === $swatch)>{{ ucfirst($swatch) }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <div class="field">
                                <span>Photo</span>
                                <input type="file" name="leaders[{{ $i }}][photo]" accept="image/jpeg,image/png,image/webp" class="file-input">
                                @if ($photo)
                                    <label class="login__remember"><input type="checkbox" name="leaders[{{ $i }}][remove_photo]" value="1">Remove photo</label>
                                @endif
                                @error("leaders.{$i}.photo") <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </fieldset>
                @endforeach
            </section>

            <section class="box form">
                <h2>What we believe</h2>
                <label class="field">Beliefs
                    <textarea name="beliefs" maxlength="3000" style="height: 180px">{{ old('beliefs', implode("\n", $content['beliefs'] ?? [])) }}</textarea>
                    <span class="field__hint">One per line.</span>
                </label>
                <span class="box__note">Units on the About page come from <a href="{{ route('admin.units.index') }}" class="box__link">Units &amp; fellowships</a>: give a unit a "What they do" line and it shows there.</span>
            </section>

            <section class="box form">
                <h2>Questions first-timers ask</h2>
                <span class="field__hint"><code>:length</code> and <code>:parking</code> become the service length and parking notes from Settings.</span>
                @foreach ($faqs as $i => $faq)
                    <fieldset class="repeat-row repeat-row--stack">
                        <legend class="sr-only">Question {{ $i + 1 }}</legend>
                        <label class="field">Question <input type="text" name="faqs[{{ $i }}][q]" value="{{ $faq['q'] ?? '' }}" maxlength="200"></label>
                        <label class="field">Answer
                            <textarea name="faqs[{{ $i }}][a]" maxlength="1000" style="height: 80px">{{ $faq['a'] ?? '' }}</textarea>
                            @error("faqs.{$i}.a") <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                    </fieldset>
                @endforeach
            </section>
        @else
            @php $t = $content['testimony']; @endphp
            <section class="box form">
                <h2>Testimony</h2>
                <span class="box__note">Shown beside the prayer request form on the home page. Ask the member before sharing their words.</span>
                <label class="field">Their words
                    <textarea name="quote" required maxlength="500">{{ old('quote', $t['quote']) }}</textarea>
                    @error('quote') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <div class="form-row">
                    <label class="field">Name
                        <input type="text" name="name" value="{{ old('name', $t['name']) }}" required maxlength="100">
                        @error('name') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">Initials <input type="text" name="initials" value="{{ old('initials', $t['initials'] ?? '') }}" maxlength="3" placeholder="From the name"></label>
                    <label class="field">Line under the name <input type="text" name="role" value="{{ old('role', $t['role'] ?? '') }}" maxlength="100" placeholder="e.g. Testimony · Media unit"></label>
                </div>
                <div class="field">
                    <span>Photo (optional)</span>
                    @if (! empty($t['photo']))
                        <img src="{{ \App\Models\PageContent::photoUrl($t['photo']) }}" alt="" class="repeat-row__photo">
                        <label class="login__remember"><input type="checkbox" name="remove_photo" value="1">Remove photo</label>
                    @endif
                    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="file-input">
                    @error('photo') <span class="field-error">{{ $message }}</span> @enderror
                </div>
            </section>
            <p class="box__note">Announcements and the declaration are under <a href="{{ route('admin.content') }}" class="box__link">Content &amp; announcements</a>; articles under <a href="{{ route('admin.posts.index') }}" class="box__link">Blog</a>.</p>
        @endif

        <div class="save-bar">
            <button type="submit" class="btn btn--navy">Save {{ $page === 'about' ? 'About page' : 'home page' }}</button>
        </div>
    </form>
@endsection
