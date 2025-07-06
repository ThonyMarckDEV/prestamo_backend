<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Prestamo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class CapturaAbonoController extends Controller
{
    /**
     * Upload a payment capture (captura de abono) for a loan.
     */
    public function uploadCapturaAbono(Request $request, $idCliente, $idPrestamo)
    {
        $validator = Validator::make($request->all(), [
            'captura' => 'required|image|mimes:jpeg,png,jpg|max:2048', // Max 2MB
        ], [
            'captura.required' => 'Debe proporcionar una imagen.',
            'captura.image' => 'El archivo debe ser una imagen.',
            'captura.mimes' => 'La imagen debe ser de tipo JPEG, PNG o JPG.',
            'captura.max' => 'La imagen no puede exceder los 2MB.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Errores de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        // Verify user and loan exist
        $user = User::find($idCliente);
        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }
        $prestamo = Prestamo::where('idPrestamo', $idPrestamo)
            ->where('idCliente', $idCliente)
            ->first();
        if (!$prestamo) {
            return response()->json(['message' => 'Préstamo no encontrado'], 404);
        }

        try {
            $file = $request->file('captura');
            $directory = "clientes/{$idCliente}/prestamos/{$idPrestamo}/abono";
            $filename = 'captura_abono.' . $file->getClientOriginalExtension();
            $path = $file->storeAs($directory, $filename, 'public');

            // Return relative URL
            $relativeUrl = "/storage/{$path}";

            Log::info('Captura de abono subida con éxito', [
                'idCliente' => $idCliente,
                'idPrestamo' => $idPrestamo,
                'filename' => $filename,
                'path' => $relativeUrl
            ]);

            return response()->json([
                'message' => 'Captura de abono subida con éxito',
                'captura_url' => $relativeUrl
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al subir captura de abono', [
                'exception' => $e->getMessage(),
                'idCliente' => $idCliente,
                'idPrestamo' => $idPrestamo
            ]);
            return response()->json([
                'message' => 'Error al subir la captura. Contacte a soporte.'
            ], 500);
        }
    }

    /**
     * Retrieve the payment capture for a loan.
     */
    public function getCapturaAbono($idCliente, $idPrestamo)
    {
        // Verify user and loan exist
        $user = User::find($idCliente);
        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }
        $prestamo = Prestamo::where('idPrestamo', $idPrestamo)
            ->where('idCliente', $idCliente)
            ->first();
        if (!$prestamo) {
            return response()->json(['message' => 'Préstamo no encontrado'], 404);
        }

        $directory = "clientes/{$idCliente}/prestamos/{$idPrestamo}/abono";
        $files = Storage::disk('public')->files($directory);
        $capturaFile = collect($files)->first(function ($file) {
            return preg_match('/captura_abono\.(jpg|jpeg|png)$/i', $file);
        });

        if (!$capturaFile) {
            return response()->json([
                'message' => 'No hay captura de abono disponible'
            ], 404);
        }

        // Return relative URL
        $relativeUrl = "/storage/{$capturaFile}";

        return response()->json([
            'message' => 'Captura encontrada',
            'captura_url' => $relativeUrl
        ], 200);
    }


    /**
     * Delete the payment capture.
     */
    public function deleteCapturaAbono($idCliente, $idPrestamo)
    {
        // Verify user and loan exist
        $user = User::find($idCliente);
        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }
        $prestamo = Prestamo::where('idPrestamo', $idPrestamo)
            ->where('idCliente', $idCliente)
            ->first();
        if (!$prestamo) {
            return response()->json(['message' => 'Préstamo no encontrado'], 404);
        }

        $directory = "clientes/{$idCliente}/prestamos/{$idPrestamo}/abono";
        $files = Storage::disk('public')->files($directory);
        $capturaFile = collect($files)->first(function ($file) {
            return preg_match('/captura_abono\.(jpg|jpeg|png)$/i', $file);
        });

        if (!$capturaFile) {
            return response()->json([
                'message' => 'No hay captura de abono para eliminar'
            ], 404);
        }

        try {
            Storage::disk('public')->delete($capturaFile);
            Log::info('Captura de abono eliminada con éxito', [
                'idCliente' => $idCliente,
                'idPrestamo' => $idPrestamo,
                'file' => $capturaFile
            ]);

            return response()->json([
                'message' => 'Captura de abono eliminada con éxito'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al eliminar captura de abono', [
                'exception' => $e->getMessage(),
                'idCliente' => $idCliente,
                'idPrestamo' => $idPrestamo
            ]);
            return response()->json([
                'message' => 'Error al eliminar la captura. Contacte a soporte.'
            ], 500);
        }
    }
}