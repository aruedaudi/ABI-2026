<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  ...$roles
     * @return mixed
     */
    public function handle($request, Closure $next, ...$roles)
    {
        $user = Auth::user();

        if (! $user) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Debes iniciar sesión para acceder.'], 401);
            }

            return redirect('login')->with('error', 'Debes iniciar sesión para acceder.');
        }

        $allowedRoles = collect($roles)
            ->flatMap(fn (string $role) => explode(',', $role))
            ->map(fn (string $role) => $this->normalizeRole($role))
            ->filter()
            ->unique()
            ->values();

        // Si no se especificaron roles, permitir acceso
        if ($allowedRoles->isEmpty()) {
            return $next($request);
        }

        // Comprobar si el usuario tiene alguno de los roles permitidos
        if (! $user->hasAnyRole($allowedRoles->toArray())) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'No tienes permiso para acceder a esta sección.'], 403);
            }

            return redirect('/home')->with('error', 'No tienes permiso para acceder a esta sección.');
        }

        return $next($request);
    }

    protected function normalizeRole(string $role): string
    {
        $role = mb_strtolower(trim($role));

        return match ($role) {
            'committe_leader' => 'committee_leader',
            default => $role,
        };
    }
}
