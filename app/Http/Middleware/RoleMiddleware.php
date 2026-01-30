<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Vérifier si l'utilisateur est connecté
        if (!auth()->check()) {
            return redirect()->route('auth.login');
        }

        $user = auth()->user();
        
        // Vérifier si l'utilisateur a un rôle
        if (!$user->role) {
            abort(403, 'Aucun rôle assigné à votre compte.');
        }

        // Vérifier si l'utilisateur a l'un des rôles requis
        $userRole = $user->role->name;
        
        if (!in_array($userRole, $roles)) {
            abort(403, 'Vous n\'avez pas les permissions nécessaires pour accéder à cette page.');
        }

        return $next($request);
    }
}