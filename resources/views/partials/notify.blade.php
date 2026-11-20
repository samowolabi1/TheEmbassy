{{--
    Pop-up messages, shown with Laravel Notify on every layout.

    Controllers can call notify()->success()->title('...')->send() directly.
    Most still flash ->with('status', '...'), and form errors come back in
    $errors; both are turned into a toast here, so every page shows them the
    same way. session()->now() keeps it to this page view only.
--}}
@php
    use Mckenziearts\Notify\Enums\NotificationModel;
    use Mckenziearts\Notify\Enums\NotificationType;

    $toast = function (NotificationType $type, string $title, ?string $message = null, int $duration = 6000) {
        session()->now('notify', [
            'message' => $message,
            'type' => $type,
            'icon' => $type->getDefaultIcon(NotificationModel::Toast),
            'model' => NotificationModel::Toast,
            'title' => $title,
            'duration' => $duration,
            'actions' => [],
        ]);
    };

    if (! session()->has('notify')) {
        $problem = session('payment_error') ?? session('booking_error');
        // Forms on busy pages keep their errors in their own named bag ("member", "visitor", "newsletter"...).
        $bag = collect($errors->getBags())->first(fn ($b) => $b->any());

        if ($bag) {
            $more = $bag->count() - 1;
            $title = $bag === $errors->getBag('newsletter') ? 'Newsletter' : 'Please check the form';
            $toast(NotificationType::Error, $title, $bag->first().($more > 0 ? ' (and '.$more.' more)' : ''), 9000);
        } elseif ($problem) {
            $toast(NotificationType::Error, 'That did not go through', $problem, 9000);
        } elseif (session('status')) {
            $toast(NotificationType::Success, session('status'));
        } elseif (session('subscribed')) {
            $toast(NotificationType::Success, 'Almost done', 'Check your inbox and confirm your email.');
        }
    }
@endphp
@if (session()->has('notify'))
    <link rel="stylesheet" href="{{ asset('vendor/mckenziearts/laravel-notify/dist/notify.css') }}">
    <x-notify::notify />
    <script src="{{ asset('vendor/mckenziearts/laravel-notify/dist/notify.js') }}" defer></script>
@endif
