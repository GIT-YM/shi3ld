<?php

namespace App\Http\Middleware;

use App\Services\BlogAttribution;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CaptureBlogAttribution
{
    public function __construct(private BlogAttribution $attribution) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->attribution->capture($request);

        return $next($request);
    }
}
