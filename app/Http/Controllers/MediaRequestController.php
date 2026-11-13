<?php

namespace App\Http\Controllers;

use App\Models\MediaRequest;
use App\Models\Unit;
use App\Services\Media\MediaDesk;
use App\Services\Media\MediaLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Asking the media unit for something, and following it through, for unit heads and staff.
 */
class MediaRequestController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('media-requests.index', [
            'requests' => MediaRequest::with('unit', 'assignee')->where('requested_by', $user->id)->latest()->get(),
            'units' => $user->isStaff() ? Unit::orderBy('name')->get(['id', 'name']) : $user->ledUnits(),
            'mediaUnit' => Unit::ofKind('media'),
        ]);
    }

    public function store(Request $request, MediaDesk $desk): RedirectResponse
    {
        $user = $request->user();
        $allowedUnits = ($user->isStaff() ? Unit::query() : Unit::whereKey($user->ledUnits()->modelKeys()))->pluck('id')->all();
        $data = $request->validate([
            'unit_id' => [$user->isStaff() ? 'nullable' : 'required', Rule::in($allowedUnits)],
            'type' => ['required', Rule::in(array_keys(MediaRequest::TYPES))],
            'title' => ['required', 'string', 'max:150'],
            'details' => ['required', 'string', 'max:5000'],
            'event_on' => ['nullable', 'date', 'after_or_equal:today'],
            'needed_by' => ['required', 'date', 'after_or_equal:today'],
            'files' => ['nullable', 'array', 'max:5'],
            'files.*' => [...MediaLibrary::fileRules(), 'max:25600'],
        ], [
            'unit_id.required' => 'Choose the unit this is for.',
            'files.max' => 'Attach up to 5 files.',
            'files.*.max' => 'Keep each attachment under 25 MB. Share bigger files as a link in the details.',
            'files.*.extensions' => 'That type of file can\'t be attached.',
        ]);

        $mediaRequest = $desk->submit($user, collect($data)->except('files')->all(), $request->file('files', []));

        return redirect()->route('media-requests.show', $mediaRequest)->with('status', 'Sent to the media team. Your reference is '.$mediaRequest->reference.'.');
    }

    public function show(Request $request, MediaRequest $mediaRequest): View
    {
        $this->authorizeRequester($request, $mediaRequest);

        return view('media-requests.show', [
            'r' => $mediaRequest->load('unit', 'assignee', 'files', 'requester'),
            'comments' => $mediaRequest->comments()->with('author.member')->where('is_internal', false)->get(),
        ]);
    }

    public function comment(Request $request, MediaRequest $mediaRequest, MediaDesk $desk): RedirectResponse
    {
        $this->authorizeRequester($request, $mediaRequest);
        $body = $request->validate(['body' => ['required', 'string', 'max:3000']])['body'];
        $desk->comment($mediaRequest, $request->user(), $body);

        return back()->with('status', 'Message sent to the media team.');
    }

    public function respond(Request $request, MediaRequest $mediaRequest, MediaDesk $desk): RedirectResponse
    {
        $this->authorizeRequester($request, $mediaRequest);
        $data = $request->validate([
            'answer' => ['required', Rule::in(['done', 'changes', 'cancel'])],
            'changes' => ['nullable', 'string', 'max:3000'],
        ]);

        match ($data['answer']) {
            'done' => $desk->respond($mediaRequest, $request->user(), true),
            'changes' => $desk->respond($mediaRequest, $request->user(), false, $data['changes'] ?? null),
            'cancel' => $desk->cancel($mediaRequest),
        };

        return back()->with('status', match ($data['answer']) {
            'done' => 'Marked done. Thank you!',
            'changes' => 'Sent back to the media team with your changes.',
            'cancel' => 'Request cancelled.',
        });
    }

    private function authorizeRequester(Request $request, MediaRequest $mediaRequest): void
    {
        abort_unless($mediaRequest->requested_by === $request->user()->id, 404);
    }
}
