<?php

namespace App\Http\Controllers\Unit;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceSetup;
use App\Models\Unit;
use App\Models\YouTubeConnection;
use App\Services\YouTube\YouTubeClient;
use App\Services\YouTube\YouTubeException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Connecting the church's YouTube channel with Google sign-in, and managing each service's broadcast.
 */
class YouTubeController extends Controller
{
    public function __construct(private YouTubeClient $youtube) {}

    /**
     * Off to Google's sign-in page. The state ties the reply to this person and this unit.
     */
    public function connect(Request $request, Unit $unit): RedirectResponse
    {
        if (! $this->youtube->configured()) {
            return back()->withErrors(['youtube' => 'An administrator needs to add the Google OAuth client ID and secret in Settings & roles first.']);
        }

        $state = Str::random(40);
        $request->session()->put('youtube_oauth', ['state' => $state, 'unit' => $unit->id]);

        return redirect()->away($this->youtube->authorizationUrl($state));
    }

    /**
     * Google sends the person back here (this address is registered in Google Cloud).
     */
    public function callback(Request $request): RedirectResponse
    {
        $pending = $request->session()->pull('youtube_oauth');
        abort_unless($pending && hash_equals($pending['state'], (string) $request->query('state')), 403, 'This sign-in link has expired. Start again from the media unit\'s setup page.');
        $unit = Unit::findOrFail($pending['unit']);
        abort_unless(Gate::allows('lead-media', $unit), 403);

        $back = redirect()->route('unit.setup.index', $unit);
        if ($request->filled('error')) {
            return $back->withErrors(['youtube' => $request->query('error') === 'access_denied' ? 'YouTube wasn\'t connected: access was not allowed.' : 'Google sign-in didn\'t finish ('.$request->query('error').').']);
        }

        try {
            $connection = $this->youtube->connect((string) $request->query('code'));
            $connection->forceFill(['connected_by' => $request->user()->id])->save();
            $this->youtube->ensureStream($connection);
        } catch (YouTubeException $e) {
            return $back->withErrors(['youtube' => $e->getMessage()]);
        }

        return $back->with('status', "Connected to {$connection->channel_title}. Broadcasts are now created on YouTube for each streamed service, and the stream key is saved below.");
    }

    public function disconnect(Unit $unit): RedirectResponse
    {
        if ($connection = YouTubeConnection::current()) {
            $this->youtube->disconnect($connection);
        }

        return back()->with('status', 'YouTube disconnected. Broadcasts already created stay on the channel.');
    }

    /**
     * Create (or try again to create) a service's broadcast now.
     */
    public function create(Unit $unit, Service $service): RedirectResponse
    {
        $setup = ServiceSetup::forService($service);
        if ($setup->youtube_managed) {
            return back()->withErrors(['youtube' => 'This service already has a YouTube broadcast.']);
        }

        return $this->attempt(fn ($c) => $this->youtube->createBroadcast($c, $setup), 'Broadcast created on YouTube.', $setup);
    }

    /**
     * Send the title and description to YouTube after editing them.
     */
    public function push(Unit $unit, Service $service): RedirectResponse
    {
        $setup = ServiceSetup::where('service_id', $service->id)->where('youtube_managed', true)->firstOrFail();

        return $this->attempt(fn ($c) => $this->youtube->updateBroadcast($c, $setup), 'Title and description updated on YouTube.', $setup);
    }

    public function destroy(Unit $unit, Service $service): RedirectResponse
    {
        $setup = ServiceSetup::where('service_id', $service->id)->where('youtube_managed', true)->firstOrFail();
        abort_if($setup->youtube_status === 'live', 422, 'The broadcast is live. Stop the stream in the encoder first.');

        return $this->attempt(fn ($c) => $this->youtube->deleteBroadcast($c, $setup), 'Broadcast removed from YouTube.', $setup);
    }

    private function attempt(callable $action, string $done, ServiceSetup $setup): RedirectResponse
    {
        $connection = YouTubeConnection::current();
        if (! $connection || ! $connection->isWorking()) {
            return back()->withErrors(['youtube' => 'Connect the church\'s YouTube channel first.']);
        }

        try {
            $action($connection);
        } catch (YouTubeException $e) {
            $setup->forceFill(['youtube_error' => $e->getMessage()])->save();

            return back()->withErrors(['youtube' => $e->getMessage()]);
        }

        return back()->with('status', $done);
    }
}
