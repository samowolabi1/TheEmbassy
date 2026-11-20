<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * People forms ask for a first name and a last name. This joins them into
 * `name` too, so the existing checks and messages that use the full name
 * keep working.
 */
class CombineName
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasAny(['first_name', 'last_name'])) {
            $request->merge(['name' => trim(preg_replace('/\s+/', ' ', $request->input('first_name', '').' '.$request->input('last_name', '')))]);
        }

        return $next($request);
    }
}
