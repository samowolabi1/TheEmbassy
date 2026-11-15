{{-- One line on a worker's or minister's training: not answered, what's still to do, or all done. --}}
@if (in_array($m->rank, ['worker', 'minister'], true))
    @if ($m->needsTrainingAnswers())
        <span class="training-status training-status--todo">Not answered yet</span>
    @elseif ($missing = $m->missingRequirements())
        <span class="training-status training-status--todo">Still to do:</span> <span class="training-status">{{ implode(', ', $missing) }}</span>
    @else
        <span class="training-status training-status--ok">✓ All done</span>
    @endif
@else
    <span class="training-status">—</span>
@endif
