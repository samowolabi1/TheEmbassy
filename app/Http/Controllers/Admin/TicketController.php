<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TicketController extends Controller
{
    /**
     * Find a ticket by the code printed under its QR code.
     */
    public function lookup(Request $request): RedirectResponse
    {
        $code = Str::upper(trim((string) $request->query('code')));
        $ticket = Ticket::firstWhere('code', $code);

        return $ticket
            ? redirect()->route('admin.tickets.show', $ticket)
            : back()->with('status', "No ticket with code {$code}.");
    }

    /**
     * What staff see when they scan a ticket's QR code at the door.
     */
    public function show(Ticket $ticket): View
    {
        return view('admin.events.ticket', ['ticket' => $ticket->load('order.event', 'type')]);
    }

    /**
     * Let them in. A ticket can only be checked in once.
     */
    public function checkIn(Ticket $ticket): RedirectResponse
    {
        abort_unless($ticket->order->isConfirmed(), 422, 'This booking is not paid.');

        if ($ticket->checked_in_at) {
            return redirect()->route('admin.tickets.show', $ticket);
        }

        $ticket->forceFill(['checked_in_at' => now()])->save();

        return redirect()->route('admin.tickets.show', $ticket)->with('status', 'Checked in. Welcome, '.$ticket->order->name.'!');
    }
}
