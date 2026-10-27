<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\TicketOrder;
use App\Models\TicketType;
use App\Services\Payments\Payments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class TicketOrderController extends Controller
{
    /**
     * Book tickets. Free bookings are confirmed straight away; paid ones go to Paystack.
     */
    public function store(Request $request, Event $event, Payments $payments): RedirectResponse
    {
        abort_unless($event->is_published, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'integer', 'min:0', 'max:50'],
        ]);

        $wanted = collect($data['quantities'])->map(fn ($n) => (int) $n)->filter();
        if ($wanted->isEmpty()) {
            throw ValidationException::withMessages(['quantities' => 'Choose at least one ticket.']);
        }

        // Lock the ticket types so two people can't both take the last places.
        $order = DB::transaction(function () use ($event, $wanted, $data, $payments) {
            $types = $event->ticketTypes()->whereKey($wanted->keys())->lockForUpdate()->get()->keyBy('id');
            if ($types->count() !== $wanted->count()) {
                throw ValidationException::withMessages(['quantities' => 'One of those tickets is no longer available.']);
            }

            foreach ($wanted as $typeId => $count) {
                /** @var TicketType $type */
                $type = $types[$typeId];
                $remaining = $type->remaining();
                $problem = match (true) {
                    ! $type->onSale() => "{$type->name} tickets are no longer on sale.",
                    $count > $type->max_per_order => "You can book up to {$type->max_per_order} {$type->name} tickets at a time.",
                    $remaining !== null && $count > $remaining => "Only {$remaining} {$type->name} ".Str::plural('ticket', $remaining).' left.',
                    default => null,
                };
                if ($problem) {
                    throw ValidationException::withMessages(['quantities' => $problem]);
                }
            }

            if (! $payments->enabled() && $types->contains(fn (TicketType $t) => ! $t->isFree())) {
                throw ValidationException::withMessages(['quantities' => 'Paid tickets can\'t be bought online yet. Please contact the church office.']);
            }

            $order = $event->orders()->create([
                'code' => strtoupper(Str::random(8)),
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'total' => $wanted->sum(fn ($count, $typeId) => $types[$typeId]->price * $count),
            ]);

            foreach ($wanted as $typeId => $count) {
                foreach (range(1, $count) as $_) {
                    $order->tickets()->create(['ticket_type_id' => $typeId, 'code' => strtoupper(Str::random(10))]);
                }
            }

            if ($order->total === 0) {
                $order->confirm();
            }

            return $order;
        });

        if ($order->isConfirmed()) {
            return redirect()->to($order->url());
        }

        return $this->checkout($order, $payments);
    }

    /**
     * The buyer's tickets, with QR codes (signed link).
     */
    public function show(TicketOrder $order, Payments $payments): View
    {
        return view('events.tickets', [
            'order' => $order->load('event', 'tickets.type'),
            'payment' => $order->payments()->latest('id')->first(),
            'holdExpired' => ! $order->isConfirmed() && $order->created_at->lt(now()->subMinutes(TicketOrder::HOLD_MINUTES)),
            'paymentsEnabled' => $payments->enabled(),
        ]);
    }

    /**
     * Try paying again while the places are still held.
     */
    public function pay(TicketOrder $order, Payments $payments): RedirectResponse
    {
        abort_if($order->isConfirmed(), 404);

        if ($order->created_at->lt(now()->subMinutes(TicketOrder::HOLD_MINUTES))) {
            return redirect()->route('events.show', $order->event)
                ->with('booking_error', 'Your reserved places were released. Please book again.');
        }

        return $this->checkout($order, $payments);
    }

    private function checkout(TicketOrder $order, Payments $payments): RedirectResponse
    {
        try {
            return redirect()->away($payments->checkout($order, $order->total, $order->email));
        } catch (RuntimeException $e) {
            Log::error('Could not start ticket checkout', ['order' => $order->code, 'error' => $e->getMessage()]);

            return redirect()->to($order->url())->with('payment_error', 'We couldn\'t reach the payment page. Please try again in a moment.');
        }
    }
}
