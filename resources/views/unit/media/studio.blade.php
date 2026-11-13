@extends('layouts.unit', ['title' => 'Live studio', 'active' => 'studio'])

@php
    $overlay = route('studio.overlay', $screen->token);
    $countdownDefault = $nextService?->starts_at?->format('Y-m-d\TH:i') ?? now()->addMinutes(15)->format('Y-m-d\TH:i');
@endphp

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Live studio</h1>
            <span class="page-head__sub">Put scenes, name captions, scriptures and a ticker over the stream, and bring in guests. OBS shows it all through the overlay link.</span>
        </div>
        <div class="actions">
            <form method="POST" action="{{ route('unit.studio.clear', $unit) }}" data-studio>
                @csrf
                <button type="submit" class="btn btn--danger">Clear everything</button>
            </form>
        </div>
    </header>
    <p class="flash" role="status" data-studio-status hidden></p>

    <div class="row">
        <section class="box grow">
            <h2>Preview <span class="field__hint" style="font-weight: 500">What OBS lays over the cameras</span></h2>
            <div class="studio-preview">
                <iframe src="{{ $overlay }}" title="Overlay preview" tabindex="-1" loading="lazy"></iframe>
            </div>
            <p class="box__note" style="margin: 0">
                On screen:
                <span class="pill pill--live" data-onair="scene" @unless ($screen->scene) hidden @endunless>Scene</span>
                <span class="pill pill--live" data-onair="caption" @unless ($screen->lower_third) hidden @endunless>Caption</span>
                <span class="pill pill--live" data-onair="scripture" @unless ($screen->scripture) hidden @endunless>Scripture</span>
                <span class="pill pill--live" data-onair="ticker" @unless ($screen->ticker) hidden @endunless>Ticker</span>
                <span class="pill pill--live" data-onair="logo" @unless ($screen->show_logo) hidden @endunless>Logo</span>
            </p>
        </section>

        <section class="box" style="flex: 0 1 340px">
            <h2>Scenes</h2>
            <form method="POST" action="{{ route('unit.studio.scene', $unit) }}" class="form" data-studio>
                @csrf
                <label class="field">Count down to
                    <input type="datetime-local" name="countdown_to" value="{{ $countdownDefault }}">
                </label>
                <label class="field">Words under the title (optional)
                    <input type="text" name="scene_text" maxlength="200" placeholder="e.g. Sunday Service · Join us live">
                </label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px">
                    <button type="submit" name="scene" value="starting" class="btn btn--gold btn--xs">Starting soon</button>
                    <button type="submit" name="scene" value="brb" class="btn btn--navy btn--xs">Be right back</button>
                    <button type="submit" name="scene" value="ending" class="btn btn--navy btn--xs">Thanks for watching</button>
                    <button type="submit" name="scene" value="message" class="btn btn--navy btn--xs">Show the words</button>
                </div>
                <button type="submit" name="scene" value="" class="btn btn--ghost">Back to the cameras</button>
            </form>
            <form method="POST" action="{{ route('unit.studio.logo', $unit) }}" data-studio>
                @csrf
                <button type="submit" class="btn btn--ghost btn--xs">Logo in the corner: on / off</button>
            </form>
        </section>
    </div>

    <div class="row">
        <section class="box grow">
            <h2>Name caption</h2>
            <form method="POST" action="{{ route('unit.studio.caption', $unit) }}" class="form" data-studio>
                @csrf
                <div class="form-row">
                    <label class="field">Name <input type="text" name="name" maxlength="120" placeholder="e.g. Pastor Tunde Bakare"></label>
                    <label class="field">Title (optional) <input type="text" name="title" maxlength="120" placeholder="e.g. Ministering"></label>
                </div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center">
                    <button type="submit" class="btn btn--gold btn--xs">Show</button>
                    <button type="submit" name="action" value="hide" formnovalidate class="btn btn--ghost btn--xs">Hide</button>
                    <label class="login__remember"><input type="checkbox" name="save" value="1">Save for next time</label>
                </div>
            </form>
            @if ($captions->isNotEmpty())
                <div class="studio-presets">
                    @foreach ($captions as $p)
                        <div class="studio-preset">
                            <form method="POST" action="{{ route('unit.studio.caption', $unit) }}" data-studio>
                                @csrf
                                <input type="hidden" name="name" value="{{ $p->line1 }}">
                                <input type="hidden" name="title" value="{{ $p->line2 }}">
                                <button type="submit" class="studio-preset__show"><strong>{{ $p->line1 }}</strong>@if ($p->line2)<span>{{ $p->line2 }}</span>@endif</button>
                            </form>
                            <form method="POST" action="{{ route('unit.studio.presets.destroy', [$unit, $p]) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="studio-preset__remove" aria-label="Remove {{ $p->line1 }}">×</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="box grow">
            <h2>Scripture</h2>
            <form method="POST" action="{{ route('unit.studio.scripture', $unit) }}" class="form" data-studio>
                @csrf
                <label class="field">Reference <input type="text" name="reference" maxlength="120" placeholder="e.g. John 3:16 (KJV)"></label>
                <label class="field">Verse <textarea name="text" maxlength="600" rows="3" placeholder="For God so loved the world…"></textarea></label>
                <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center">
                    <button type="submit" class="btn btn--gold btn--xs">Show</button>
                    <button type="submit" name="action" value="hide" formnovalidate class="btn btn--ghost btn--xs">Hide</button>
                    <label class="login__remember"><input type="checkbox" name="save" value="1">Save for this service</label>
                </div>
            </form>
            @if ($scriptures->isNotEmpty())
                <div class="studio-presets">
                    @foreach ($scriptures as $p)
                        <div class="studio-preset">
                            <form method="POST" action="{{ route('unit.studio.scripture', $unit) }}" data-studio>
                                @csrf
                                <input type="hidden" name="reference" value="{{ $p->line1 }}">
                                <input type="hidden" name="text" value="{{ $p->line2 }}">
                                <button type="submit" class="studio-preset__show"><strong>{{ $p->line1 }}</strong><span>{{ \Illuminate\Support\Str::limit($p->line2, 60) }}</span></button>
                            </form>
                            <form method="POST" action="{{ route('unit.studio.presets.destroy', [$unit, $p]) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="studio-preset__remove" aria-label="Remove {{ $p->line1 }}">×</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    <section class="box">
        <h2>Ticker</h2>
        <form method="POST" action="{{ route('unit.studio.ticker', $unit) }}" class="form" data-studio>
            @csrf
            <label class="field">Scrolling along the bottom
                <input type="text" name="ticker" maxlength="500" value="{{ $screen->ticker }}" placeholder="e.g. Give online at royaltribe.org/give · Midweek service Wednesday 6pm · Welcome to everyone watching!">
            </label>
            <div style="display: flex; gap: 8px">
                <button type="submit" class="btn btn--gold btn--xs">Run</button>
                <button type="submit" name="action" value="hide" formnovalidate class="btn btn--ghost btn--xs">Hide</button>
            </div>
        </form>
    </section>

    <div class="row">
        <section class="box grow">
            <h2>Remote guests</h2>
            <p class="box__note" style="margin: 0">For a guest minister or a testimony from elsewhere. Send the guest their link: it opens their camera in the browser on a phone or laptop, with nothing to install. Then add their OBS link in OBS as a browser source, and they appear like a camera. This uses VDO.Ninja, a free service that connects the guest's camera straight to OBS.</p>
            <form method="POST" action="{{ route('unit.studio.guests.store', $unit) }}" class="form-row">
                @csrf
                <label class="field">Guest's name
                    <input type="text" name="name" required maxlength="80" placeholder="e.g. Pastor Grace from London">
                    @error('name') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <div style="align-self: end"><button type="submit" class="btn btn--navy">Make links</button></div>
            </form>
            @foreach ($guests as $g)
                <div class="list-item" style="padding: 10px 0">
                    <span class="list-item__main">
                        <span class="list-item__title">{{ $g->name }}</span>
                        <span class="list-item__meta">Made {{ $g->created_at->diffForHumans() }}</span>
                    </span>
                    <button type="button" class="btn btn--gold btn--xs" data-copy="{{ $g->guestUrl() }}">Copy guest's link</button>
                    <a href="https://wa.me/?text={{ rawurlencode('Here is your link to join our live stream. Open it on your phone or laptop and allow the camera: '.$g->guestUrl()) }}" target="_blank" rel="noopener" class="btn btn--ghost btn--xs">WhatsApp it</a>
                    <button type="button" class="btn btn--ghost btn--xs" data-copy="{{ $g->obsUrl() }}">Copy OBS link</button>
                    <form method="POST" action="{{ route('unit.studio.guests.destroy', [$unit, $g]) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn--danger btn--xs">Remove</button>
                    </form>
                </div>
            @endforeach
        </section>

        <section class="box" style="flex: 0 1 380px">
            <h2>Set up OBS once</h2>
            <ol class="box__note" style="margin: 0; padding-left: 18px; display: grid; gap: 6px">
                <li>In OBS, add a <strong>Browser</strong> source to your service scene, above the cameras.</li>
                <li>Paste the overlay link, set the width to 1920 and the height to 1080.</li>
                <li>Stream to YouTube with the key on <a href="{{ route('unit.setup.index', $unit) }}">Service setup &amp; streaming</a>. Everything you show here appears on the stream.</li>
            </ol>
            <button type="button" class="btn btn--navy btn--xs" data-copy="{{ $overlay }}">Copy overlay link</button>
            <p class="box__note" style="margin: 0">Keep this link private: anyone with it can see the overlay.</p>
            @if ($canLead)
                <form method="POST" action="{{ route('unit.studio.regenerate', $unit) }}" data-confirm="Make a new overlay link? OBS will need the new one.">
                    @csrf
                    <button type="submit" class="btn btn--ghost btn--xs">Make a new link</button>
                </form>
            @endif
        </section>
    </div>
@endsection
