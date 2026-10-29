<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockBrowser
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $isBrowser = $request->header('Sec-Fetch-Mode') === 'navigate'
            || str_contains((string) $request->header('Accept'), 'text/html');

        if ($isBrowser) {
            abort(404);
        }

        return $next($request);
    }
}
