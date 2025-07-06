<?php

namespace App\Http\Middleware;

use Closure;

class CorsMiddleware
{
    public function handle($request, Closure $next)
    {
        $allowedOrigins = [
            'http://localhost:3000',
            'http://192.168.18.47:8005',
            'https://ficsullana.vercel.app'
        ];

        $origin = $request->headers->get('Origin');

        if (in_array($origin, $allowedOrigins)) {
            return $next($request)
                ->header('Access-Control-Allow-Origin', $origin)
                ->header('Access-Control-Allow-Methods', '*')
                ->header('Access-Control-Allow-Headers', '*')
                ->header('Access-Control-Allow-Credentials', 'false');
        }

        return $next($request);
    }
}
