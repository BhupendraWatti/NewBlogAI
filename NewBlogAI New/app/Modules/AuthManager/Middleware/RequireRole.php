<?php

namespace App\Modules\AuthManager\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (! $request->user()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $userRole = (int) $request->user()->role;

        // Convert the string array of roles to integers
        $allowedRoles = array_map('intval', $roles);

        if (! in_array($userRole, $allowedRoles, true)) {
            return response()->json([
                'message' => 'This action is unauthorized. Required role missing.',
            ], 403);
        }

        return $next($request);
    }
}
