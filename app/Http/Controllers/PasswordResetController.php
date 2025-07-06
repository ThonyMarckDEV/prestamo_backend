<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\PasswordResetEmail;
use Carbon\Carbon;

class PasswordResetController extends Controller
{

    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'dni' => 'required|string|max:9',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'DNI inválido',
                'errors' => $validator->errors(),
            ], 400);
        }

        // Find user by DNI through datos
        $user = User::with(['datos', 'datos.contactos'])
            ->whereHas('datos', function ($query) use ($request) {
                $query->where('dni', $request->dni);
            })
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'DNI no existe',
            ], 404);
        }

        // Check if user role is not client (idRol != 2)
        if ($user->idRol !== 2) {
            return response()->json([
                'message' => 'Si no eres cliente y olvidaste tu contraseña, pídele al administrador que la cambie.',
            ], 403);
        }

        $contacto = $user->datos->contactos->where('tipo', 'PRINCIPAL')->first() ?? $user->datos->contactos->first();
        if (!$contacto || !$contacto->email) {
            return response()->json([
                'message' => 'No se encontró un correo asociado. Contacta al soporte.',
            ], 400);
        }

        // Generate reset token
        $resetToken = Str::random(60);
        $expiresAt = now()->addMinutes(10);

        // Delete any existing tokens for this user
        DB::table('password_reset_tokens')
            ->where('idUsuario', $user->idUsuario)
            ->delete();

        // Store new reset token
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
        Mail::to($contacto->email)->send(new PasswordResetEmail($user, $resetUrl));

        return response()->json([
            'message' => 'Se ha enviado un enlace de restablecimiento a tu correo. El enlace es válido por 10 minutos.',
        ], 200);
    }

    public function showResetForm($idUsuario, $token)
    {
        $resetToken = DB::table('password_reset_tokens')
            ->where('idUsuario', $idUsuario)
            ->where('token', $token)
            ->first();

        if (!$resetToken) {
            return view('reset-password', [
                'error' => 'Enlace de restablecimiento inválido.',
                'idUsuario' => $idUsuario,
                'token' => $token,
            ]);
        }

        if (Carbon::parse($resetToken->expires_at)->isPast()) {
            return view('reset-password', [
                'error' => 'Enlace de restablecimiento expirado. Solicita un nuevo enlace.',
                'idUsuario' => $idUsuario,
                'token' => $token,
            ]);
        }

        return view('reset-password', [
            'idUsuario' => $idUsuario,
            'token' => $token,
        ]);
    }

    public function reset(Request $request, $idUsuario, $token)
    {
        $resetToken = DB::table('password_reset_tokens')
            ->where('idUsuario', $idUsuario)
            ->where('token', $token)
            ->first();

        if (!$resetToken || Carbon::parse($resetToken->expires_at)->isPast()) {
            return redirect()->route('password.reset.form', ['idUsuario' => $idUsuario, 'token' => $token])
                ->with('error', 'Enlace de restablecimiento inválido o expirado.');
        }

        $user = User::with('datos')->find($idUsuario);
        if (!$user) {
            return redirect()->route('password.reset.form', ['idUsuario' => $idUsuario, 'token' => $token])
                ->with('error', 'Usuario no encontrado.');
        }

        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // Check if the new password matches the user's DNI
        $dni = $user->datos->dni ?? '';
        if ($request->password === $dni) {
            return redirect()->back()
                ->withErrors(['password' => 'La contraseña no puede ser tu DNI.'])
                ->withInput();
        }

        // Update password
        $user->password = Hash::make($request->password);
        $user->save();

        // Delete reset token
        DB::table('password_reset_tokens')
            ->where('idUsuario', $user->idUsuario)
            ->delete();

        return redirect()->route('password.reset.form', ['idUsuario' => $idUsuario, 'token' => $token])
            ->with('success', 'Contraseña cambiada exitosamente. Por favor, inicia sesión.');
    }
}
