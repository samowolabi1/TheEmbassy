<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Declaration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DeclarationController extends Controller
{
    /**
     * Save the declaration for a week. It shows on the home page from that Monday.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'week_of' => ['required', 'date'],
            'lines' => ['required', 'array', 'size:4'],
            'lines.*' => ['required', 'string', 'max:160'],
        ], [
            'lines.*.required' => 'Fill in all four lines.',
        ]);

        $weekOf = Declaration::weekStarting($data['week_of']);
        Declaration::updateOrCreate(['week_of' => $weekOf], ['lines' => array_values($data['lines'])]);

        $when = $weekOf->isSameDay(Declaration::weekStarting(today())) ? 'It is on the home page now.' : 'It goes on the home page on '.$weekOf->format('l j F').'.';

        return redirect()->route('admin.content', ['week' => $weekOf->toDateString()])
            ->with('status', 'Declaration for the week of '.$weekOf->format('j F').' saved. '.$when);
    }
}
