<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureServicesStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->services_staff, 403);

        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
