<?php

namespace App\Http\Controllers;

use App\Models\Datos;
use Illuminate\Http\Request;
use App\Models\Prestamo;
use App\Models\Cuota;
use App\Models\Pago;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MisPagosController extends Controller
{

    /**
     * Obtener los préstamos activos y sus cuotas pagadas o prepagadas del cliente autenticado
     */
    public function obtenerPrestamosCuotasPagadas(Request $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                $idCliente = auth()->id();
    
                // Verificar si existe el cliente
                $cliente = User::where('idUsuario', $idCliente)
                    ->where('idRol', 2)
                    ->first();
    
                if (!$cliente) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cliente no encontrado'
                    ], 404);
                }
    
                // Obtener préstamos activos del cliente
                $prestamos = Prestamo::where('idCliente', $idCliente)
                    ->whereIn('estado', ['activo'])
                    ->get();
    
                if ($prestamos->isEmpty()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No tienes préstamos activos'
                    ], 404);
                }
    
                $resultado = [];
    
                foreach ($prestamos as $prestamo) {
                    // Obtener cuotas pagadas o prepagadas
                    $cuotas = Cuota::where('idPrestamo', $prestamo->idPrestamo)
                        ->whereIn('estado', ['pagado', 'prepagado'])
                        ->orderBy('numero_cuota', 'asc')
                        ->get();
    
                    if ($cuotas->isNotEmpty()) {
                        $cuotasProcesadas = $cuotas->map(function ($cuota) {
                            // Obtener modalidad para cuotas pagadas o prepagadas
                            $modalidad = null;
                            if (in_array($cuota->estado, ['pagado', 'prepagado'])) {
                                $pago = Pago::where('idCuota', $cuota->idCuota)
                                    ->orderBy('idPago', 'desc')
                                    ->first();
                                $modalidad = $pago ? $pago->modalidad : null;
                            }
    
                            return [
                                'idCuota' => $cuota->idCuota,
                                'numero_cuota' => $cuota->numero_cuota,
                                'monto' => $cuota->monto,
                                'capital' => $cuota->capital,
                                'interes' => $cuota->interes,
                                'fecha_vencimiento' => $cuota->fecha_vencimiento,
                                'estado' => $cuota->estado,
                                'dias_mora' => $cuota->dias_mora,
                                'observaciones' => $cuota->observaciones,
                                'modalidad' => $modalidad,// Modalidad incluida dentro de cada cuota
                                'mora' => $cuota->cargo_mora ?? 0,
                                'mora_reducida' => $cuota->mora_reducida,
                                'reduccion_mora_aplicada' => $cuota->reduccion_mora_aplicada
                            ];
                        });
    
                        $resultado[] = [
                            'prestamo' => [
                                'idPrestamo' => $prestamo->idPrestamo,
                                'monto' => $prestamo->monto,
                                'frecuencia' => $prestamo->frecuencia,
                                'cuotas' => $prestamo->cuotas
                                // Se elimina el campo 'modalidad' del nivel de préstamo
                            ],
                            'cuotas' => $cuotasProcesadas
                        ];
                    }
                }
    
                return response()->json([
                    'success' => true,
                    'data' => [
                        'cliente' => $cliente,
                        'prestamos' => $resultado
                    ]
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Error en MisPagosController@obtenerPrestamosCuotasPagadas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener préstamos y cuotas pagadas',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    

      /**
     * Obtener la captura de pago por idCuota
     */
    public function obtenerCapturaPago(Request $request, $idCuota)
    {
        try {
            // Obtener la cuota
            $cuota = Cuota::findOrFail($idCuota);
            $prestamo = Prestamo::findOrFail($cuota->idPrestamo);

            // // Verificar que la cuota pertenece al cliente autenticado
            if ($prestamo->idCliente !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para acceder a esta cuota'
                ], 403);
            }

            // Directorio de la captura
            $directorio = "public/clientes/{$prestamo->idCliente}/prestamos/{$prestamo->idPrestamo}/cuotas/{$cuota->idCuota}/capturapago";
            $fullPath = storage_path('app/' . $directorio);

            // Buscar el archivo de captura (jpg, jpeg, png)
            $extensions = ['jpg', 'jpeg', 'png'];
            $capturaPath = null;
            foreach ($extensions as $ext) {
                $potentialPath = "{$fullPath}/capturapago.{$ext}";
                if (File::exists($potentialPath)) {
                    $capturaPath = "storage/clientes/{$prestamo->idCliente}/prestamos/{$prestamo->idPrestamo}/cuotas/{$cuota->idCuota}/capturapago/capturapago.{$ext}";
                    break;
                }
            }

            if (!$capturaPath) {
                return response()->json([
                    'success' => false,
                    'message' => 'Captura de pago no encontrada'
                ], 404);
            }

            Log::info('Captura de pago encontrada', ['path' => $capturaPath]);

            return response()->json([
                'success' => true,
                'capturapago_url' => asset($capturaPath)
            ]);
        } catch (\Exception $e) {
            Log::error('Error en MisPagosController@obtenerCapturaPago: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la captura de pago',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener el comprobante de pago por idCuota
     */
    public function obtenerComprobantePago(Request $request, $idCuota)
    {
        try {
            // Obtener la cuota
            $cuota = Cuota::findOrFail($idCuota);
            $prestamo = Prestamo::findOrFail($cuota->idPrestamo);

            // // Verificar que la cuota pertenece al cliente autenticado
            if ($prestamo->idCliente !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para acceder a esta cuota'
                ], 403);
            }

            // Obtener el pago asociado a la cuota
            $pago = Pago::where('idCuota', $idCuota)->first();
            if (!$pago) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró un pago asociado a esta cuota'
                ], 404);
            }

            // Directorio del comprobante
            $directorio = storage_path("app/public/clientes/{$prestamo->idCliente}/prestamos/{$prestamo->idPrestamo}/cuotas/{$cuota->idCuota}/comprobantepago");
            $fecha = Carbon::parse($pago->fecha_pago)->format('d-m-Y');
            $nombreArchivo = "comprobante-pago-{$pago->idPago}-{$fecha}.pdf";
            $comprobantePath = "{$directorio}/{$nombreArchivo}";

            if (!File::exists($comprobantePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comprobante de pago no encontrado'
                ], 404);
            }

            Log::info('Comprobante de pago encontrado', ['path' => $comprobantePath]);

            return response()->json([
                'success' => true,
                'comprobante_url' => asset("storage/clientes/{$prestamo->idCliente}/prestamos/{$prestamo->idPrestamo}/cuotas/{$cuota->idCuota}/comprobantepago/{$nombreArchivo}")
            ]);
        } catch (\Exception $e) {
            Log::error('Error en MisPagosController@obtenerComprobantePago: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el comprobante de pago',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}