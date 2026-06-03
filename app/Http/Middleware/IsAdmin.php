<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        // Kick them out to 403 Forbidden screen if not an admin account.
        if (!auth()->check() || !auth()->user()->is_admin) {
            abort(403, 'Admin access required. Please use the authorized portal.');
        }

        return $next($request);
    }
}