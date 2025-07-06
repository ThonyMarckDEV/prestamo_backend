<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRoleMW
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $role
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $role)
    {
        // Verificar que el usuario está autenticado y el payload está disponible
        if (!$request->auth || !isset($request->auth->rol)) {
            return response()->json([
                'message' => 'No autorizado'
            ], 403);
        }

        // Verificar que el rol coincida
        if ($request->auth->rol !== $role) {
            return response()->json([
                'message' => 'Acceso denegado. Se requiere rol: ' . $role
            ], 403);
        }

        return $next($request);
    }
}