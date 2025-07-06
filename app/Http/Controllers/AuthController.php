<?php

namespace App\Http\Controllers;

use App\Mail\PasswordResetEmail;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTFactory;

/**
* @OA\Info(
*    title="FICSULLANA BACKEND DOCUMENTATION", 
*    version="1.0",
*    description="FICSULLANA BACKEND DOCUMENTATION"
* )
*
* @OA\Server(url="")
*/
class AuthController extends Controller
{

        /**
     * @OA\Post(
     *     path="/api/login",
     *     summary="Iniciar sesión",
     *     description="Autentica al usuario y devuelve un token JWT de acceso y refresh",
     *     operationId="login",
     *     tags={"AUTH CONTROLLER"},
     *     
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"username","password"},
     *             @OA\Property(property="username", type="string", example="usuario123"),
     *             @OA\Property(property="password", type="string", format="password", example="secreto123")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Login exitoso",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Login exitoso"),
     *             @OA\Property(property="access_token", type="string", example="eyJ0eXAiOiJKV1QiLCJhb..."),
     *             @OA\Property(property="refresh_token", type="string", example="eyJ0eXAiOiJKV1QiLCJhb..."),
     *             @OA\Property(property="token_type", type="string", example="bearer"),
     *             @OA\Property(property="expires_in", type="integer", example=3600)
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=400,
     *         description="Datos inválidos",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Datos inválidos"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 example={"username": {"El campo username es obligatorio."}, "password": {"El campo password es obligatorio."}}
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Credenciales incorrectas",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Usuario o contraseña incorrectos")
     *         )
     *     )
     * )
     */
    public function login(Request $request)
    {
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string',
            'remember_me' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors(),
            ], 400);
        }

        // Find the user by username, eager loading 'rol' and 'datos' with 'contactos'
        $user = User::with(['rol', 'datos.contactos'])->where('username', $request->username)->first();

        // If user doesn't exist or password is invalid
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Usuario o contraseña incorrectos',
            ], 401);
        }

        // Check if user status is active
        if ($user->estado !== 1) {
            return response()->json([
                'message' => 'Error: estado del usuario inactivo',
            ], 403);
        }

        // Check for existing valid reset token for clients (idRol = 2)
        if ($user->idRol === 2) {
            $existingToken = DB::table('password_reset_tokens')
                ->where('idUsuario', $user->idUsuario)
                ->where('expires_at', '>', now())
                ->first();

            if ($existingToken) {
                // Re-send email if token exists
                $resetUrl = config('app.url') . "/reset-password/{$user->idUsuario}/{$existingToken->token}";
                // Get email from contactos
                $contacto = $user->datos->contactos->where('tipo', 'PRINCIPAL')->first() ?? $user->datos->contactos->first();
                if ($contacto && $contacto->email) {
                    Mail::to($contacto->email)->send(new PasswordResetEmail($user, $resetUrl));
                    return response()->json([
                        'message' => 'Se ha reenviado un correo para cambiar tu contraseña por seguridad. El enlace es válido por 10 minutos.',
                    ], 403);
                } else {
                    return response()->json([
                        'message' => 'No se pudo enviar el correo de restablecimiento. Contacta al soporte para actualizar tu correo.',
                    ], 403);
                }
            }

            // Check if password matches DNI
            $dni = $user->datos->dni ?? '';
            if ($request->password === $dni) {
                // Generate reset token
                $resetToken = Str::random(60);
                $expiresAt = now()->addMinutes(10); // Changed to 10 minutes

                // Delete any existing tokens
                DB::table('password_reset_tokens')
                    ->where('idUsuario', $user->idUsuario)
                    ->delete();

                // Store reset token
                DB::table('password_reset_tokens')->insert([
                    'idUsuario' => $user->idUsuario,
                    'token' => $resetToken,
                    'ip_address' => $request->ip(),
                    'device' => $request->userAgent(),
                    'expires_at' => $expiresAt,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Send reset email
                $resetUrl = config('app.url') . "/reset-password/{$user->idUsuario}/{$resetToken}";
                $contacto = $user->datos->contactos->where('tipo', 'PRINCIPAL')->first() ?? $user->datos->contactos->first();
                if ($contacto && $contacto->email) {
                    Mail::to($contacto->email)->send(new PasswordResetEmail($user, $resetUrl));
                    return response()->json([
                        'message' => 'Se ha enviado un correo para cambiar tu contraseña por seguridad. El enlace es válido por 10 minutos.',
                    ], 403);
                } else {
                    return response()->json([
                        'message' => 'No se pudo enviar el correo de restablecimiento. Contacta al soporte para actualizar tu correo.',
                    ], 403);
                }
            }
        }

        // Generate access token with Firebase JWT (5 minutes)
        $now = time();
        $expiresIn = config('jwt.ttl') * 60;

        // Generate refresh token
        $rememberMe = $request->remember_me ?? false;
        $refreshTTL = $rememberMe ? 7 * 24 * 60 * 60 : 1 * 24 * 60 * 60;
        $secret = config('jwt.secret');

        // Access token
        $accessPayload = [
            'iss' => config('app.url'),
            'iat' => $now,
            'exp' => $now + $expiresIn,
            'nbf' => $now,
            'jti' => Str::random(16),
            'sub' => $user->idUsuario,
            'prv' => sha1(config('app.key')),
            'rol' => $user->rol->nombre,
            'username' => $user->username,
            'nombre' => $user->datos->nombre ?? 'N/A',
            'email' => $user->datos->contactos->first()->email ?? 'N/A',
        ];

        // Refresh token
        $refreshPayload = [
            'iss' => config('app.url'),
            'iat' => $now,
            'exp' => $now + $refreshTTL,
            'nbf' => $now,
            'jti' => Str::random(16),
            'sub' => $user->idUsuario,
            'prv' => sha1(config('app.key')),
            'type' => 'refresh',
            'rol' => $user->rol->nombre,
        ];

        // Generate tokens using Firebase JWT
        $accessToken = JWT::encode($accessPayload, $secret, 'HS256');
        $refreshToken = JWT::encode($refreshPayload, $secret, 'HS256');

        // Manage active sessions (max 1)
        $activeSessions = DB::table('refresh_tokens')
            ->where('idUsuario', $user->idUsuario)
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'asc')
            ->get();

        if ($activeSessions->count() >= 1) {
            // Delete the oldest session
            DB::table('refresh_tokens')
                ->where('idToken', $activeSessions->first()->idToken)
                ->delete();
        }

        // Insert new refresh token
        $refreshTokenId = DB::table('refresh_tokens')->insertGetId([
            'idUsuario' => $user->idUsuario,
            'refresh_token' => $refreshToken,
            'ip_address' => $request->ip(),
            'device' => $request->userAgent(),
            'expires_at' => date('Y-m-d H:i:s', $now + $refreshTTL),
            'created_at' => date('Y-m-d H:i:s', $now),
            'updated_at' => date('Y-m-d H:i:s', $now),
        ]);

        // Return response with tokens
        return response()->json([
            'message' => 'Login exitoso',
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'idRefreshToken' => $refreshTokenId,
        ], 200);
    }


    // Método para refrescar el token
    public function refresh(Request $request)
    {
        // Validar el refresh token
        $validator = Validator::make($request->all(), [
            'refresh_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Refresh token inválido',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            // Verificar el token con Firebase JWT
            $secret = config('jwt.secret');
            $payload = \Firebase\JWT\JWT::decode($request->refresh_token, new \Firebase\JWT\Key($secret, 'HS256'));
            
            // Verificar que sea un token de refresco
            if (!isset($payload->type) || $payload->type !== 'refresh') {
                return response()->json([
                    'message' => 'El token proporcionado no es un token de refresco',
                ], 401);
            }
            
            // Obtener el ID de usuario
            $userId = $payload->sub;
            $user = User::find($userId);
            
            if (!$user) {
                return response()->json([
                    'message' => 'Usuario no encontrado',
                ], 404);
            }
            
            // Generar un nuevo token de acceso con Firebase JWT
            $now = time();
            $expiresIn = config('jwt.ttl') * 60;
            
            // Crear payload del token de acceso con custom claims del usuario
            $accessPayload = [
                'iss' => config('app.url'),
                'iat' => $now,
                'exp' => $now + $expiresIn,
                'nbf' => $now,
                'jti' => Str::random(16),
                'sub' => $user->idUsuario,
                'prv' => sha1(config('app.key')),
                // Custom claims del usuario
                'rol' => $user->rol->nombre,
                'username' => $user->username,
                // Otros atributos del usuario que quieras incluir
                'nombre' => $user->datos->nombre, 
                'email' => $user->datos->email,
            ];
            
            // Generar nuevo token de acceso usando Firebase JWT
            $newToken = \Firebase\JWT\JWT::encode($accessPayload, $secret, 'HS256');
            
            return response()->json([
                'message' => 'Token actualizado',
                'access_token' => $newToken,
                'token_type' => 'bearer',
                'expires_in' => $expiresIn
            ], 200);
            
        } catch (\Firebase\JWT\ExpiredException $e) {
            return response()->json([
                'message' => 'Refresh token expirado'
            ], 401);
        } catch (\Firebase\JWT\SignatureInvalidException $e) {
            return response()->json([
                'message' => 'Refresh token inválido'
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al procesar el token',
                'error' => $e->getMessage()
            ], 500);
        }
    }


     // In your AuthController.php
    public function validateRefreshToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'refresh_token_id' => 'required|integer',
            'userID' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'valid' => false,
                'message' => 'Datos inválidos'
            ], 400);
        }

        try {
            // Buscar el token en la base de datos
            $refreshToken = DB::table('refresh_tokens')
                ->where('idToken', $request->refresh_token_id)
                ->where('idUsuario', $request->userID)
                ->first();

            if (!$refreshToken) {
                // No eliminar el token si no pertenece al usuario
                return response()->json([
                    'valid' => false,
                    'message' => 'Token no válido o no autorizado'
                ], 401); // Cambiado a 401 para indicar no autorizado
            }

            // Verificar si el token ha expirado
            if ($refreshToken->expires_at && now()->greaterThan($refreshToken->expires_at)) {
                // Eliminar el token solo si pertenece al usuario
                DB::table('refresh_tokens')
                    ->where('idToken', $request->refresh_token_id)
                    ->where('idUsuario', $request->userID)
                    ->delete();

                return response()->json([
                    'valid' => false,
                    'message' => 'Token expirado'
                ], 401); // Cambiado a 401 para indicar no autorizado
            }

            return response()->json([
                'valid' => true,
                'message' => 'Token válido'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error validating refresh token: ' . $e->getMessage());
            return response()->json([
                'valid' => false,
                'message' => 'Error al validar el token'
            ], 500);
        }
    }

    // Método para cerrar sesión
    public function logout(Request $request)
    {
        // Validate the request
        $request->validate([
            'idToken' => 'required|integer|exists:refresh_tokens,idToken',
        ]);

        // Delete the refresh token from the database
        $deleted = DB::table('refresh_tokens')
            ->where('idToken', $request->idToken)
            ->delete();

        if ($deleted) {
            return response()->json([
                'message' => 'OK',
            ], 200);
        }

        return response()->json([
            'message' => 'Error: No se encontró el token de refresco',
        ], 404);
    }
}
