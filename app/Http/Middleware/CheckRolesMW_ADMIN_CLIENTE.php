<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRolesMW_ADMIN_CLIENTE
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$roles
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        // Si no se pasan roles como parámetros, permitimos los roles cliente y manager por defecto
        if (empty($roles)) {
            $roles = ['admin' ,'cliente' , 'auditor'];
        }

        // Verificar que el usuario está autenticado y el payload está disponible
        if (!$request->auth || !isset($request->auth->rol)) {
            return response()->json([
                'message' => 'No autorizado'
            ], 403);
        }

        // Verificar si el rol del usuario está en la lista de roles permitidos
        if (!in_array($request->auth->rol, $roles)) {
            return response()->json([
                'message' => 'Acceso denegado. Se requiere uno de estos roles: ' . implode(', ', $roles)
            ], 403);
        }

        return $next($request);
    }
}