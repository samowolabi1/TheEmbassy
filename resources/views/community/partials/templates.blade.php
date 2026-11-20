{{--
    Helpers for the work groups: fill a few boxes and they're written into the message, laid out
    the same way every time. The boxes have no names, so only the message is sent.
--}}
@php
    $shop = auth()->user()?->shop;
    $helpers = [
        'job-vacancies' => ['💼 Post a job', 'Fill in what you know and it\'s added to your message, ready to send.', [
            ['role', 'Job title', 'text', 'e.g. Sales representative', true, '📢 JOB VACANCY: %s'],
            ['company', 'Company or organisation', 'text', 'e.g. Bright Foods Ltd', false, '🏢 %s'],
            ['location', 'Where', 'text', 'e.g. Ikeja, Lagos, or Remote', false, '📍 %s'],
            ['type', 'Type', 'select', ['Full-time', 'Part-time', 'Contract', 'Internship', 'Remote', 'Volunteer'], false, '🕒 %s'],
            ['pay', 'Pay (optional)', 'text', 'e.g. ₦150,000 a month', false, '💰 %s'],
            ['needs', 'What they\'re looking for', 'textarea', 'e.g. OND/HND, 1 year in sales, good with customers', false, '📝 %s'],
            ['apply', 'How to apply', 'text', 'e.g. Send your CV to jobs@brightfoods.ng', true, '✉️ How to apply: %s'],
            ['closes', 'Closing date (optional)', 'date', null, false, '⏳ Closes %s'],
        ]],
        'business' => ['🏪 Share my business', 'Tell the Tribe what you do. It\'s added to your message, ready to send.', [
            ['name', 'Business name', 'text', 'e.g. Ada\'s Kitchen', true, '🏪 %s'],
            ['what', 'What you sell or do', 'textarea', 'e.g. Party jollof, small chops and cakes for events', true, '%s'],
            ['where', 'Where and delivery', 'text', 'e.g. Yaba, delivery across Lagos', false, '📍 %s'],
            ['contact', 'How to reach you', 'text', 'e.g. 0803 000 0000 (WhatsApp)', false, '📞 %s'],
            ['link', 'Link (optional)', 'text', $shop ? route('market.shop', $shop) : 'e.g. your Instagram or website', false, '🔗 %s', $shop ? route('market.shop', $shop) : null],
        ]],
        'career' => ['🎯 Share an opportunity', 'An internship, course, scholarship or programme others could use.', [
            ['title', 'What it is', 'text', 'e.g. Free data analysis course', true, '🎯 %s'],
            ['who', 'Who it\'s for', 'text', 'e.g. Graduates under 30', false, '👥 %s'],
            ['details', 'Details', 'textarea', 'e.g. 8 weeks online, certificate at the end', false, '📝 %s'],
            ['link', 'How to apply or find out more', 'text', 'e.g. a link or an email', true, '🔗 %s'],
            ['deadline', 'Deadline (optional)', 'date', null, false, '⏳ Deadline %s'],
        ]],
    ];
    $helper = $helpers[$room->slug] ?? null;
@endphp
@if ($helper)
    <details class="composer__poll composer__helper" data-template>
        <summary class="composer__poll-toggle">{{ $helper[0] }}</summary>
        <p class="composer__helper-note">{{ $helper[1] }}</p>
        <div class="composer__helper-grid">
            @foreach ($helper[2] as $f)
                <label class="field @if ($f[2] === 'textarea') composer__helper-wide @endif"><span>{{ $f[1] }}@if ($f[4]) <span aria-hidden="true" style="color: #a6531a">*</span>@endif</span>
                    @if ($f[2] === 'select')
                        <select data-line="{{ $f[5] }}">
                            <option value="">Choose…</option>
                            @foreach ($f[3] as $o)<option>{{ $o }}</option>@endforeach
                        </select>
                    @elseif ($f[2] === 'textarea')
                        <textarea rows="2" maxlength="600" placeholder="{{ $f[3] }}" data-line="{{ $f[5] }}" @if ($f[4]) data-needed="{{ $f[1] }}" @endif></textarea>
                    @else
                        <input type="{{ $f[2] }}" maxlength="200" @if ($f[3]) placeholder="{{ $f[3] }}" @endif value="{{ $f[6] ?? '' }}" data-line="{{ $f[5] }}" @if ($f[2] === 'date') data-date @endif @if ($f[4]) data-needed="{{ $f[1] }}" @endif>
                    @endif
                </label>
            @endforeach
        </div>
        <p class="field-error" data-template-error hidden></p>
        <button type="button" class="btn btn--gold btn--sm" data-template-fill>Add to my message</button>
    </details>
@endif
