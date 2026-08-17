<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        abort_unless($request->user()?->canDo($permission), 403, 'Acesso não autorizado.');

        return $next($request);
    }
}
