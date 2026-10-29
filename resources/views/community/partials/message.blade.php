{{-- One message in a community room. Every button is a small form, so it all works without JavaScript too. --}}
@php
    $author = $message->user;
    $mine = $author?->id === $viewer->id;
    $summary = $message->isRemoved() ? [] : $message->reactionSummary($viewer);
@endphp
<li id="m{{ $message->id }}" @class(['msg', 'msg--mine' => $mine, 'msg--removed' => $message->isRemoved()]) data-message-id="{{ $message->id }}" data-author="{{ $author?->communityName() }}">
    <span class="msg__avatar" aria-hidden="true">{{ $author?->initials() ?? '?' }}</span>
    <div class="msg__bubble">
        <div class="msg__head">
            <span class="msg__name">{{ $author?->communityName() ?? 'Former member' }}</span>
            @if ($author?->moderatesCommunity())
                <span class="msg__badge">Staff</span>
            @endif
            <time class="msg__time" datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->isToday() ? $message->created_at->format('g:i A') : $message->created_at->format('j M, g:i A') }}</time>
            @if ($message->edited_at && ! $message->isRemoved())
                <span class="msg__edited">edited</span>
            @endif
        </div>

        @if ($message->isRemoved())
            <p class="msg__body msg__body--removed">Message removed{{ $message->removed_by && $message->removed_by !== $message->user_id ? ' by a moderator' : '' }}.</p>
        @else
            @if ($message->replyTo)
                <a href="#m{{ $message->replyTo->id }}" class="msg__reply">
                    <strong>{{ $message->replyTo->user?->communityName() }}</strong>
                    <span>{{ $message->replyTo->isRemoved() ? 'Message removed' : \Illuminate\Support\Str::limit($message->replyTo->body ?? ($message->replyTo->poll ? 'Poll: '.$message->replyTo->poll['question'] : 'Photo'), 90) }}</span>
                </a>
            @endif

            @if ($message->body)
                <p class="msg__body">{{ $message->bodyHtml() }}</p>
            @endif

            @if ($message->image_path)
                <a href="{{ $message->imageUrl() }}" class="msg__image" target="_blank" rel="noopener">
                    <img src="{{ $message->thumbUrl() }}" alt="Photo from {{ $author?->communityName() }}" loading="lazy" decoding="async">
                </a>
            @endif

            @if ($message->poll)
                @php
                    $counts = $message->pollCounts();
                    $total = array_sum($counts);
                    $myVote = $message->votes->firstWhere('user_id', $viewer->id)?->option;
                    $closed = $message->pollClosed();
                @endphp
                <div class="poll">
                    <span class="poll__question">📊 {{ $message->poll['question'] }}</span>
                    @foreach ($message->poll['options'] as $i => $option)
                        @php $pct = $total ? round($counts[$i] / $total * 100) : 0; @endphp
                        <form method="POST" action="{{ route('community.messages.vote', $message) }}" data-community-action>
                            @csrf
                            <input type="hidden" name="option" value="{{ $i }}">
                            <button type="submit" @class(['poll__option', 'poll__option--mine' => $myVote === $i]) @disabled($closed) aria-pressed="{{ $myVote === $i ? 'true' : 'false' }}">
                                <span class="poll__bar" style="width: {{ $myVote !== null || $closed ? $pct : 0 }}%"></span>
                                <span class="poll__label">{{ $option }}</span>
                                @if ($myVote !== null || $closed)
                                    <span class="poll__pct">{{ $pct }}%</span>
                                @endif
                            </button>
                        </form>
                    @endforeach
                    <span class="poll__meta">{{ $total }} {{ \Illuminate\Support\Str::plural('vote', $total) }} · {{ $closed ? 'Closed' : 'Closes '.\Illuminate\Support\Carbon::parse($message->poll['closes_at'])->diffForHumans() }}@if ($myVote === null && ! $closed) · vote to see results @endif</span>
                </div>
            @endif

            @if ($summary)
                <div class="msg__reactions">
                    @foreach ($summary as $emoji => $r)
                        <form method="POST" action="{{ route('community.messages.react', $message) }}" data-community-action>
                            @csrf
                            <input type="hidden" name="emoji" value="{{ $emoji }}">
                            <button type="submit" @class(['reaction', 'reaction--mine' => $r['mine']]) aria-pressed="{{ $r['mine'] ? 'true' : 'false' }}" title="{{ $r['names'] }}">{{ $emoji }} <span>{{ $r['count'] }}</span></button>
                        </form>
                    @endforeach
                </div>
            @endif

            <div class="msg__actions">
                <button type="button" class="msg__action" data-reply="{{ $message->id }}" hidden>Reply</button>
                <details class="msg__menu">
                    <summary class="msg__action">React</summary>
                    <div class="msg__pop msg__pop--emoji">
                        @foreach ($reactions as $emoji)
                            <form method="POST" action="{{ route('community.messages.react', $message) }}" data-community-action>
                                @csrf
                                <input type="hidden" name="emoji" value="{{ $emoji }}">
                                <button type="submit" class="reaction reaction--pick" aria-label="React {{ $emoji }}">{{ $emoji }}</button>
                            </form>
                        @endforeach
                    </div>
                </details>
                @if ($message->canEdit($viewer))
                    <details class="msg__menu">
                        <summary class="msg__action">Edit</summary>
                        <form method="POST" action="{{ route('community.messages.update', $message) }}" class="msg__pop msg__edit" data-community-action>
                            @csrf
                            @method('PUT')
                            <label class="sr-only" for="edit-{{ $message->id }}">Edit message</label>
                            <textarea id="edit-{{ $message->id }}" name="body" maxlength="2000" rows="3" required>{{ $message->body }}</textarea>
                            <button type="submit" class="btn btn--navy btn--sm">Save</button>
                        </form>
                    </details>
                @endif
                @if ($message->canRemove($viewer))
                    <form method="POST" action="{{ route('community.messages.destroy', $message) }}" data-community-action data-confirm="Remove this message?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="msg__action">{{ $mine ? 'Delete' : 'Remove' }}</button>
                    </form>
                @endif
                @unless ($mine)
                    <details class="msg__menu">
                        <summary class="msg__action">Report</summary>
                        <form method="POST" action="{{ route('community.messages.report', $message) }}" class="msg__pop msg__edit" data-community-action data-report>
                            @csrf
                            <label class="msg__pop-label" for="report-{{ $message->id }}">What's wrong with it?</label>
                            <input id="report-{{ $message->id }}" type="text" name="reason" maxlength="300" required placeholder="e.g. rude, spam, not appropriate">
                            <button type="submit" class="btn btn--outline btn--sm">Report to moderators</button>
                        </form>
                    </details>
                @endunless
            </div>
        @endif
    </div>
</li>
