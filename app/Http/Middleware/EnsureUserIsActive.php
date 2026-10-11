<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user()?->is_active) {
            if (! $request->user()->isAdmin() && $request->user()->blocked_reason !== null) {
                throw new ApiException('This account is blocked. Please contact the shop.', 403, [], 'CUSTOMER_BLOCKED');
            }

            throw new ApiException('Your account is deactivated.', 403);
        }

        return $next($request);
    }
}
