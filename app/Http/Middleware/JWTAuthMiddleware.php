<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Exception;

class JWTAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            // Obtener y validar el token
            $token = $this->getTokenFromRequest($request);
            if (!$token) {
                return $this->unauthorizedResponse('Token no proporcionado');
            }
            
            // Decodificar y validar el token
            $payload = $this->decodeAndValidateToken($token);
            
            // Verificar que no sea un token de refresco
            if ($this->isRefreshToken($payload)) {
                return $this->unauthorizedResponse('No se puede autenticar con un token de refresco');
            }
            
            // Verificar explícitamente la expiración del token
            if ($this->isTokenExpired($payload)) {
                return $this->unauthorizedResponse('Token expirado');
            }
            
            // Autenticar al usuario
            $user = $this->authenticateUser($payload);
            if (!$user) {
                return response()->json(['message' => 'Usuario no encontrado'], 404);
            }
            
            // Guardar payload en la solicitud para acceder desde otros middlewares
            $request->auth = $payload;
                
            return $next($request);
            
        } catch (ExpiredException $e) {
            return $this->unauthorizedResponse('Token expirado', $e->getMessage());
        } catch (SignatureInvalidException $e) {
            return $this->unauthorizedResponse('Token inválido', $e->getMessage());
        } catch (Exception $e) {
            return $this->unauthorizedResponse('Error en la autenticación', $e->getMessage());
        }
    }
    
    /**
     * Obtiene el token de la solicitud
     * 
     * @param Request $request
     * @return string|null
     */
    private function getTokenFromRequest(Request $request)
    {
        return $request->bearerToken();
    }
    
    /**
     * Decodifica y valida el token JWT
     * 
     * @param string $token
     * @return object
     */
    private function decodeAndValidateToken($token)
    {
        $secret = config('jwt.secret');
        return JWT::decode($token, new Key($secret, 'HS256'));
    }
    
    /**
     * Verifica si el token es un token de refresco
     * 
     * @param object $payload
     * @return bool
     */
    private function isRefreshToken($payload)
    {
        return isset($payload->type) && $payload->type === 'refresh';
    }
    
    /**
     * Verifica explícitamente si el token ha expirado
     * 
     * @param object $payload
     * @return bool
     */
    private function isTokenExpired($payload)
    {
        // Verificar que el token tiene un tiempo de expiración
        if (!isset($payload->exp)) {
            return true;
        }
        
        // Comparar el tiempo de expiración con el tiempo actual
        return time() >= $payload->exp;
    }
    
    /**
     * Autentica al usuario basado en el payload del token
     * 
     * @param object $payload
     * @return User|null
     */
    private function authenticateUser($payload)
    {
        // Buscar el usuario por ID
        $user = User::find($payload->sub);
        
        // Si el usuario existe, autenticarlo
        if ($user) {
            Auth::login($user);
        }
        
        return $user;
    }
    
    /**
     * Genera una respuesta de error de autenticación
     * 
     * @param string $message
     * @param string|null $error
     * @return \Illuminate\Http\JsonResponse
     */
    private function unauthorizedResponse($message, $error = null)
    {
        $response = ['message' => $message];
        
        if ($error) {
            $response['error'] = $error;
        }
        
        return response()->json($response, 401);
    }
}