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
     * @param  \Closure  $next
     * @param  string  ...$roles
     * @return mixed
     */
    public function handle($request, Closure $next, ...$roles)
    {
        $user = Auth::user();

        if (! $user) {
            return redirect('/')->with('error', 'No tienes permiso para acceder a esta seccion.');
        }

        $allowedRoles = collect($roles)
            ->flatMap(fn (string $role) => explode(',', $role))
            ->map(fn (string $role) => $this->normalizeRole($role))
            ->filter()
            ->map(fn (string $role) => $this->normalizeRole($role))
            ->unique()
            ->values();

        if ($allowedRoles->isNotEmpty() && ! $allowedRoles->contains($this->normalizeRole((string) $user->role))) {
            return redirect('/')->with('error', 'No tienes permiso para acceder a esta seccion.');
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
