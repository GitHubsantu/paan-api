<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user || ! $user->isAdmin() || ! $user->tokenCan('admin')) {
            throw new ApiException('You are not authorized to perform this action.', 403);
        }

        return $next($request);
    }
}
