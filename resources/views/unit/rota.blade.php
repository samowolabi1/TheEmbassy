@extends('layouts.unit', ['title' => 'Rota', 'active' => 'rota'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Rota</h1>
            <span class="page-head__sub">Who serves at each coming service. Members see their duties on their account page and get an email two days before.</span>
        </div>
    </header>

    @error('member_id') <p class="flash flash--error" role="alert">{{ $message }}</p> @enderror

    @forelse ($services as $service)
        <section class="box box--flush">
            <div class="box__head">
                <h2>{{ $service->label() }}</h2>
                <span class="box__note">{{ $service->starts_at?->format('l j M, g:i A') ?? $service->held_on->format('l j M') }}</span>
            </div>
            @foreach ($duties[$service->id] ?? [] as $duty)
                <div class="list-item">
                    <span class="list-item__main"><span class="list-item__title">{{ $duty->member->name }}</span><span class="list-item__meta">{{ $duty->role }}</span></span>
                    <form method="POST" action="{{ route('unit.rota.destroy', [$unit, $duty]) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn--ghost btn--xs">Remove<span class="sr-only"> {{ $duty->member->name }}</span></button>
                    </form>
                </div>
            @endforeach
            <form method="POST" action="{{ route('unit.rota.store', $unit) }}" class="form" style="flex-direction: row; flex-wrap: wrap; align-items: flex-end; padding: 16px 20px">
                @csrf
                <input type="hidden" name="service_id" value="{{ $service->id }}">
                <label class="field" style="flex: 1 1 200px; min-width: 0">Member
                    <select name="member_id" required>
                        <option value="">Choose…</option>
                        @foreach ($members as $m)
                            <option value="{{ $m->id }}">{{ $m->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field" style="flex: 1 1 200px; min-width: 0">Role <input type="text" name="role" required maxlength="60" placeholder="{{ $unit->isMedia() ? 'e.g. Camera 1' : 'e.g. Lead vocals' }}"></label>
                <button type="submit" class="btn btn--navy btn--xs">Add</button>
            </form>
        </section>
    @empty
        <section class="box"><p class="empty-state">No services scheduled yet. The church office adds them under Attendance.</p></section>
    @endforelse
@endsection
