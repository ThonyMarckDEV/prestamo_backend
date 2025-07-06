<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Datos;
use Illuminate\Http\Request;
use App\Models\Prestamo;
use App\Models\Cuota;
use App\Models\Pago;
use App\Models\EstadoPrestamo;
use App\Models\User;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class PagarTotalPrestamoController extends Controller
{
    /**
     * Registrar cancelación total del préstamo
     */
    public function registrarCancelacionTotal(Request $request)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validate([
                'idPrestamo' => 'required|exists:prestamos,idPrestamo',
                'idCliente' => 'required|exists:usuarios,idUsuario',
                'monto_pagado' => 'required|numeric|min:0.01',
                'numero_operacion' => 'nullable|string|max:50',
                'observaciones' => 'nullable|string|max:255',
            ]);

            $prestamo = Prestamo::findOrFail($validatedData['idPrestamo']);

            // Verificar que el idCliente corresponde al cliente del préstamo
            if ($prestamo->idCliente != $validatedData['idCliente']) {
                throw new \Exception('El cliente especificado no coincide con el cliente del préstamo');
            }

            // Verificar que el idCliente pertenece a un usuario con rol de cliente
            $cliente = User::where('idUsuario', $validatedData['idCliente'])
                ->where('idRol', 2) // idRol 2 es para clientes
                ->first();

            if (!$cliente) {
                throw new \Exception('Cliente no encontrado o no es un cliente válido');
            }

            // Obtener cuotas pendientes
            $cuotasPendientes = Cuota::where('idPrestamo', $prestamo->idPrestamo)
                ->whereIn('estado', ['pendiente', 'vence_hoy', 'vencido'])
                ->get();

            if ($cuotasPendientes->isEmpty()) {
                throw new \Exception('No hay cuotas pendientes para cancelar');
            }

            // Log para depuración
            Log::info('Cuotas pendientes encontradas', [
                'idPrestamo' => $prestamo->idPrestamo,
                'cuotas' => $cuotasPendientes->toArray()
            ]);

            // Calcular monto total necesario usando el campo 'monto'
            $montoTotalRequerido = $cuotasPendientes->sum(function ($cuota) {
                return $cuota->monto ?? 0;
            });

            Log::info('Monto total requerido calculado', [
                'montoTotalRequerido' => $montoTotalRequerido,
                'monto_pagado' => $validatedData['monto_pagado']
            ]);

            if (abs($validatedData['monto_pagado'] - $montoTotalRequerido) > 0.01) {
                throw new \Exception('El monto pagado debe ser exactamente S/ ' . number_format($montoTotalRequerido, 2));
            }

            $comprobantes = [];

            // Procesar cada cuota pendiente
            foreach ($cuotasPendientes as $cuota) {
                $pago = new Pago();
                $pago->idCuota = $cuota->idCuota;
                $pago->monto_pagado = $cuota->monto;
                $pago->excedente = 0; // No hay excedentes
                $pago->modalidad = 'presencial';
                $pago->fecha_pago = now();
                
                $observaciones = "Cancelación total - Número operación: " . ($validatedData['numero_operacion'] ?? 'N/A');
                if ($validatedData['observaciones']) {
                    $observaciones .= " - " . $validatedData['observaciones'];
                }
                $pago->observaciones = $observaciones;
                $pago->numero_operacion = $validatedData['numero_operacion'] ?? null;
                $pago->idUsuario = $validatedData['idCliente'];
                $pago->save();

                // Actualizar estado de la cuota
                $cuota->estado = 'pagado';
                $cuota->save();

                // Generar comprobante
                $rutaPDF = $this->generarComprobantePago($pago, $cuota, $prestamo);
                $comprobantes[] = [
                    'idCuota' => $cuota->idCuota,
                    'numero_cuota' => $cuota->numero_cuota,
                    'comprobante_url' => $rutaPDF
                ];
            }

            // Actualizar estado del préstamo
            $prestamo->estado = 'cancelado';
            $prestamo->save();

            // Actualizar o crear estado en estados_prestamo
            $estadoPrestamo = EstadoPrestamo::where('idPrestamo', $prestamo->idPrestamo)
                ->orderBy('fecha_actualizacion', 'desc')
                ->first();

            if ($estadoPrestamo) {
                $estadoPrestamo->update([
                    'estado' => 'cancelado',
                    'fecha_actualizacion' => now(),
                    'observacion' => 'Préstamo cancelado por pago total',
                    'idUsuario' => $validatedData['idCliente']
                ]);
            } else {
                EstadoPrestamo::create([
                    'idPrestamo' => $prestamo->idPrestamo,
                    'estado' => 'cancelado',
                    'fecha_actualizacion' => now(),
                    'observacion' => 'Préstamo cancelado por pago total',
                    'idUsuario' => $validatedData['idCliente']
                ]);
            }

            DB::commit();

            Log::info('Cancelación total completada exitosamente', [
                'idPrestamo' => $prestamo->idPrestamo,
                'comprobantes' => $comprobantes
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Préstamo cancelado completamente',
                'data' => [
                    'prestamo' => $prestamo,
                    'comprobantes' => $comprobantes
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en PagarTotalPrestamoController@registrarCancelacionTotal: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cancelar el préstamo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar comprobante de pago
     */
    private function generarComprobantePago($pago, $cuota, $prestamo)
    {
        try {
            // Establecer explícitamente la zona horaria
            Carbon::setLocale('es');
            date_default_timezone_set('America/Lima');

            $cliente = User::findOrFail($prestamo->idCliente);
            $datosCliente = Datos::where('idDatos', $cliente->idDatos)->first();
            $usuario = User::findOrFail(auth()->id());

            Log::info('generarComprobantePago: Datos cliente', [
                'idUsuario' => $cliente->idUsuario,
                'idDatos' => $cliente->idDatos,
                'datosCliente' => $datosCliente ? $datosCliente->toArray() : null
            ]);

            if (!$datosCliente) {
                throw new \Exception('No se encontraron datos del cliente');
            }

            $now = Carbon::now();
            $data = [
                'cliente' => $cliente,
                'datosCliente' => $datosCliente,
                'prestamo' => $prestamo,
                'cuota' => $cuota,
                'pago' => $pago,
                'fecha' => $now->format('d/m/Y'),
                'hora' => $now->format('H:i:s'),
                'usuario' => $usuario,
                'mora' => $cuota->cargo_mora ?? 0,
                'mora_reducida' => $cuota->mora_reducida,
                'reduccion_mora_aplicada' => $cuota->reduccion_mora_aplicada
            ];

            $html = view('comprobante_pago', $data)->render();

            $options = new \Dompdf\Options();
            $options->set('dpi', 58);
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isFontSubsettingEnabled', true);

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html);
            $width = 48 * 2.83465;
            $height = 500 * 2.83465;

            $dompdf->setPaper([0, 0, $width, $height], 'portrait');
            $dompdf->render();

            $directorio = storage_path("app/public/clientes/{$prestamo->idCliente}/prestamos/{$prestamo->idPrestamo}/cuotas/{$cuota->idCuota}/comprobantepago");

            if (!File::exists($directorio)) {
                File::makeDirectory($directorio, 0755, true);
            }

            $fecha = Carbon::now()->format('d-m-Y');
            $nombreArchivo = "comprobante-pago-{$pago->idPago}-{$fecha}.pdf";
            $rutaArchivo = "{$directorio}/{$nombreArchivo}";

            file_put_contents($rutaArchivo, $dompdf->output());

            Log::info("Comprobante de pago generado correctamente para el pago {$pago->idPago}");

            return asset("storage/clientes/{$prestamo->idCliente}/prestamos/{$prestamo->idPrestamo}/cuotas/{$cuota->idCuota}/comprobantepago/{$nombreArchivo}");
        } catch (\Exception $e) {
            Log::error("Error al generar el comprobante de pago: " . $e->getMessage());
            throw $e;
        }
    }
}