<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Datos;
use Illuminate\Http\Request;
use App\Models\Prestamo;
use App\Models\Cuota;
use App\Models\Pago;
use App\Models\User;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PagoElectronicoController extends Controller
{

    /**
     * Evaluar las condiciones de una cuota según la hora y fecha de vencimiento
     */
    protected function evaluarCondicionesCuota($cuota, $currentDateTime, $isNextCuota = false)
    {
        Log::info('Evaluando condiciones de cuota', [
            'idCuota' => $cuota->idCuota,
            'currentDateTime' => $currentDateTime->toDateTimeString(),
            'fecha_vencimiento' => $cuota->fecha_vencimiento,
            'estado' => $cuota->estado,
            'isNextCuota' => $isNextCuota,
            'dias_mora' => $cuota->dias_mora,
            'mora_aplicada' => $cuota->mora_aplicada,
            'fecha_mora_aplicada' => $cuota->fecha_mora_aplicada,
            'cargo_mora' => $cuota->cargo_mora
        ]);

        $monto_a_pagar = $cuota->monto;
        $mensaje = '';
        $dias_mora = $cuota->dias_mora;
        $cargo_mora = $cuota->cargo_mora ?? 0; // Initialize with existing cargo_mora or 0

        // Si la cuota está pagada o prepagada, no se aplican validaciones
        if (in_array($cuota->estado, ['pagado', 'prepagado'])) {
            return [
                'monto_a_pagar' => 0,
                'mensaje' => '',
                'dias_mora' => $dias_mora,
                'mora' => $cuota->cargo_mora ?? 0
            ];
        }

        // Comparar fecha de vencimiento con la fecha actual
        $fecha_vencimiento = Carbon::parse($cuota->fecha_vencimiento);
        $hoy = $currentDateTime->copy()->startOfDay();
        $siguiente_dia = $hoy->copy()->addDay();

        // Iniciar una transacción para asegurar consistencia
        return DB::transaction(function () use ($cuota, $currentDateTime, $fecha_vencimiento, $hoy, $siguiente_dia, $isNextCuota, &$monto_a_pagar, &$mensaje, &$dias_mora, &$cargo_mora) {
            // Obtener el préstamo asociado
            $prestamo = DB::table('prestamos')->where('idPrestamo', $cuota->idPrestamo)->first();
            if (!$prestamo) {
                Log::error('Préstamo no encontrado', ['idPrestamo' => $cuota->idPrestamo]);
                throw new \Exception('Préstamo no encontrado');
            }

            // Calcular días de mora si la cuota está vencida
            if ($fecha_vencimiento->lt($siguiente_dia) && !$fecha_vencimiento->isSameDay($hoy)) {
                $dias_mora = $fecha_vencimiento->diffInDays($hoy);

                // Determinar si se debe aplicar una mora incremental
                $dias_mora_anterior = $cuota->dias_mora;

                if ($dias_mora > $dias_mora_anterior || !$cuota->mora_aplicada) {
                    $rango_dias_actual = $this->determinarRangoDias($dias_mora);
                    $rango_dias_anterior = $dias_mora_anterior > 0 ? $this->determinarRangoDias($dias_mora_anterior) : null;

                    // Obtener el cargo por mora actual basado en el monto del préstamo
                    $cargo_mora_actual = $this->obtenerCargoMora($rango_dias_actual, $prestamo->monto);

                    // Calcular el incremento en el cargo por mora
                    $cargo_mora_incremento = 0;
                    if ($dias_mora_anterior > 0 && $cuota->mora_aplicada) {
                        $cargo_mora_anterior = $this->obtenerCargoMora($rango_dias_anterior, $prestamo->monto);
                        $cargo_mora_incremento = $cargo_mora_actual - $cargo_mora_anterior;
                    } else {
                        $cargo_mora_incremento = $cargo_mora_actual;
                    }

                    // Acumular el incremento al cargo_mora existente
                    if ($cargo_mora_incremento > 0) {
                        $cargo_mora += $cargo_mora_incremento; // Sum to existing cargo_mora
                        $monto_a_pagar += $cargo_mora_incremento; // Only add the increment to monto_a_pagar
                        $cuota->monto = $monto_a_pagar;
                        $cuota->dias_mora = $dias_mora;
                        $cuota->estado = 'vencido';
                        $cuota->mora_aplicada = true;
                        $cuota->fecha_mora_aplicada = $currentDateTime;
                        $cuota->cargo_mora = $cargo_mora; // Store accumulated cargo_mora

                        // Actualizar observaciones
                        $cuota->observaciones = $cuota->observaciones ?
                            $cuota->observaciones . "; Mora incremental de {$cargo_mora_incremento} aplicada por {$dias_mora} días de mora (monto préstamo: {$prestamo->monto}, total cargo_mora: {$cargo_mora}) el " . $hoy->toDateTimeString() :
                            "Mora incremental de {$cargo_mora_incremento} aplicada por {$dias_mora} días de mora (monto préstamo: {$prestamo->monto}, total cargo_mora: {$cargo_mora}) el " . $hoy->toDateTimeString();

                        $cuota->save();

                        Log::info('Mora aplicada a cuota vencida', [
                            'idCuota' => $cuota->idCuota,
                            'idPrestamo' => $cuota->idPrestamo,
                            'cargo_mora_incremento' => $cargo_mora_incremento,
                            'cargo_mora_total' => $cargo_mora,
                            'dias_mora' => $dias_mora,
                            'monto_prestamo' => $prestamo->monto,
                            'cargo_mora_guardado' => $cuota->cargo_mora
                        ]);
                    }
                }

                Log::info('Cuota vencida procesada', [
                    'idCuota' => $cuota->idCuota,
                    'dias_mora' => $dias_mora,
                    'cargo_mora' => $cargo_mora,
                    'monto_a_pagar' => $monto_a_pagar,
                    'observaciones' => $cuota->observaciones
                ]);

                return [
                    'monto_a_pagar' => $monto_a_pagar,
                    'mensaje' => $cargo_mora > 0 ? "Cuota vencida con {$dias_mora} días de mora. Cargo por mora total: {$cargo_mora}" : "Cuota vencida con {$dias_mora} días de mora",
                    'dias_mora' => $dias_mora,
                    'mora' => $cargo_mora
                ];
            }

            // Si la fecha de vencimiento es hoy, actualizar estado a vence_hoy
            if ($fecha_vencimiento->isSameDay($hoy) && $cuota->estado === 'pendiente') {
                $cuota->estado = 'vence_hoy';
                $cuota->save();

                Log::info('Cuota actualizada a vence_hoy', [
                    'idCuota' => $cuota->idCuota,
                    'estado' => $cuota->estado
                ]);
            }

            // Si la cuota es la siguiente y se evalúa mora por horario
            if ($isNextCuota) {
                $hora = $currentDateTime->hour;
                if ($hora >= 18 && !$cuota->mora_aplicada) {
                    $dias_mora = 1;
                    $cuota->dias_mora = $dias_mora;
                    $cuota->mora_aplicada = true;
                    $cuota->fecha_mora_aplicada = $currentDateTime;

                    // Obtener cargo por mora para 1 día basado en el monto del préstamo
                    $cargo_mora = $this->obtenerCargoMora('1 día', $prestamo->monto);
                    $monto_a_pagar += $cargo_mora;
                    $cuota->monto = $monto_a_pagar;
                    $cuota->cargo_mora = $cargo_mora; // Store new cargo_mora (no accumulation)

                    $cuota->observaciones = $cuota->observaciones ?
                        $cuota->observaciones . "; Mora de {$cargo_mora} aplicada por cuota anterior después de las 6 PM (monto préstamo: {$prestamo->monto}) el " . $currentDateTime->toDateTimeString() :
                        "Mora de {$cargo_mora} aplicada por cuota anterior después de las 6 PM (monto préstamo: {$prestamo->monto}) el " . $currentDateTime->toDateTimeString();
                    $cuota->save();

                    Log::info('Mora aplicada a cuota siguiente', [
                        'idCuota' => $cuota->idCuota,
                        'idPrestamo' => $cuota->idPrestamo,
                        'cargo_mora' => $cargo_mora,
                        'dias_mora' => $dias_mora,
                        'mora_aplicada' => $cuota->mora_aplicada,
                        'fecha_mora_aplicada' => $cuota->fecha_mora_aplicada,
                        'cargo_mora_guardado' => $cuota->cargo_mora,
                        'observaciones' => $cuota->observaciones
                    ]);

                    $mensaje = "Mora de {$cargo_mora} aplicada por vencimiento de cuota anterior después de las 6 PM";
                    return [
                        'monto_a_pagar' => $monto_a_pagar,
                        'mensaje' => $mensaje,
                        'dias_mora' => $dias_mora,
                        'mora' => $cargo_mora
                    ];
                }
                return [
                    'monto_a_pagar' => $monto_a_pagar,
                    'mensaje' => $mensaje,
                    'dias_mora' => $dias_mora,
                    'mora' => $cargo_mora
                ];
            }

            // Si la fecha de vencimiento no es hoy
            if (!$fecha_vencimiento->isSameDay($hoy)) {
                return [
                    'monto_a_pagar' => $monto_a_pagar,
                    'mensaje' => '',
                    'dias_mora' => $dias_mora,
                    'mora' => $cargo_mora
                ];
            }

            // Si la cuota está pendiente o vence hoy
            if (in_array($cuota->estado, ['pendiente', 'vence_hoy'])) {
                $hora = $currentDateTime->hour;
                $minuto = $currentDateTime->minute;

                if ($hora >= 8 && ($hora < 15 || ($hora === 14 && $minuto <= 59))) {
                    $mensaje = '';
                } elseif ($hora >= 15 && ($hora < 18 || ($hora === 17 && $minuto <= 59))) {
                    if (!$cuota->ajuste_tarde_aplicado) {
                        $monto_a_pagar += 3;
                        $cuota->monto = $monto_a_pagar;
                        $cuota->ajuste_tarde_aplicado = true;
                        $cuota->fecha_ajuste_tarde = $currentDateTime;
                        $cuota->observaciones = $cuota->observaciones ?
                            $cuota->observaciones . '; Ajuste de 3 soles aplicado el ' . $currentDateTime->toDateTimeString() :
                            'Ajuste de 3 soles aplicado el ' . $currentDateTime->toDateTimeString();
                        $cuota->save();

                        Log::info('Ajuste de 3 soles aplicado', [
                            'idCuota' => $cuota->idCuota,
                            'idPrestamo' => $cuota->idPrestamo,
                            'monto' => $cuota->monto,
                            'ajuste_tarde_aplicado' => $cuota->ajuste_tarde_aplicado,
                            'fecha_ajuste_tarde' => $cuota->fecha_ajuste_tarde,
                            'observaciones' => $cuota->observaciones
                        ]);
                    }
                    $mensaje = 'Cuota ajustada por pago después de las 3 PM';
                } else {
                    $mensaje = 'Cuota vence hoy; mora se aplicará a la siguiente cuota después de las 6 PM';
                }
            }

            return [
                'monto_a_pagar' => max(0, $monto_a_pagar),
                'mensaje' => $mensaje,
                'dias_mora' => $dias_mora,
                'mora' => $cargo_mora
            ];
        });
    }

    /**
     * Determina el rango de días para consultar la tabla cargos_mora
     */
    protected function determinarRangoDias($dias_mora)
    {
        if ($dias_mora <= 30) {
            return "{$dias_mora} día" . ($dias_mora > 1 ? 's' : '');
        } elseif ($dias_mora <= 60) {
            return '31-60 días';
        } else {
            return '61-90 días';
        }
    }

    /**
     * Obtiene el cargo por mora según el rango de días y el monto del préstamo
     * @param string $rango_dias Rango de días de mora (ej. '1 día', '2 días', '31-60 días')
     * @param float $monto_prestamo Monto del préstamo (prestamos.monto)
     * @return float Cargo por mora
     */
    protected function obtenerCargoMora($rango_dias, $monto_prestamo)
    {
        $cargo = DB::table('cargos_mora')
            ->where('dias', $rango_dias)
            ->first();

        if (!$cargo) {
            Log::error('Cargo por mora no encontrado', ['rango_dias' => $rango_dias]);
            return 0;
        }

        if ($monto_prestamo >= 300 && $monto_prestamo <= 900) {
            return $cargo->monto_300_900;
        } elseif ($monto_prestamo >= 1000 && $monto_prestamo <= 1500) {
            return $cargo->monto_1000_1500;
        } elseif ($monto_prestamo >= 1600 && $monto_prestamo <= 2000) {
            return $cargo->monto_1600_2000;
        } elseif ($monto_prestamo >= 2100 && $monto_prestamo <= 2500) {
            return $cargo->monto_2100_2500;
        } elseif ($monto_prestamo >= 2501 && $monto_prestamo <= 3000) {
            return $cargo->monto_2501_3000;
        } elseif ($monto_prestamo >= 3001 && $monto_prestamo <= 3500) {
            return $cargo->monto_3001_3500;
        } elseif ($monto_prestamo >= 3501 && $monto_prestamo <= 4000) {
            return $cargo->monto_3501_4000;
        } elseif ($monto_prestamo >= 4001 && $monto_prestamo <= 4500) {
            return $cargo->monto_4001_4500;
        } elseif ($monto_prestamo >= 4501 && $monto_prestamo <= 5000) {
            return $cargo->monto_4501_5000;
        } elseif ($monto_prestamo >= 5001 && $monto_prestamo <= 5500) {
            return $cargo->monto_5001_5500;
        } elseif ($monto_prestamo >= 5501 && $monto_prestamo <= 6000) {
            return $cargo->monto_5501_6000;
        }

        Log::warning('Monto del préstamo fuera de los rangos definidos', ['monto_prestamo' => $monto_prestamo]);
        return 0;
    }

    /**
     * Obtener las cuotas del cliente autenticado
     */
    public function obtenerCuotasPendientes(Request $request)
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
                    ->where('estado', 'activo')
                    ->get();

                if ($prestamos->isEmpty()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No tienes préstamos activos'
                    ], 404);
                }

                $resultado = [];
                $currentDateTime = Carbon::now('America/Lima');

                foreach ($prestamos as $prestamo) {
                    // Obtener todas las cuotas del préstamo
                    $cuotas = Cuota::where('idPrestamo', $prestamo->idPrestamo)
                        ->orderBy('numero_cuota', 'asc')
                        ->get();

                    if ($cuotas->isNotEmpty()) {
                        $cuotasProcesadas = $cuotas->map(function ($cuota, $index) use ($prestamo, $currentDateTime, $cuotas) {
                            $isNextCuota = false;
                            if ($index > 0) {
                                $previousCuota = $cuotas[$index - 1];
                                $previousFechaVencimiento = Carbon::parse($previousCuota->fecha_vencimiento);
                                if (
                                    $previousFechaVencimiento->isSameDay($currentDateTime->startOfDay()) &&
                                    in_array($previousCuota->estado, ['pendiente', 'vence_hoy']) &&
                                    $currentDateTime->hour >= 18
                                ) {
                                    $isNextCuota = true;
                                }
                            }

                            $condiciones = $this->evaluarCondicionesCuota($cuota, $currentDateTime, $isNextCuota);

                            return [
                                'idCuota' => $cuota->idCuota,
                                'numero_cuota' => $cuota->numero_cuota,
                                'monto' => $cuota->monto,
                                'capital' => $cuota->capital,
                                'interes' => $cuota->interes,
                                'fecha_vencimiento' => $cuota->fecha_vencimiento,
                                'estado' => $cuota->estado,
                                'dias_mora' => $condiciones['dias_mora'],
                                'monto_a_pagar' => $condiciones['monto_a_pagar'],
                                'mensaje' => $condiciones['mensaje'],
                                'observaciones' => $cuota->observaciones,
                                'mora' => $cuota->cargo_mora ?? 0,
                                'mora_reducida' => $cuota->mora_reducida,
                                'reduccion_mora_aplicada' => $cuota->reduccion_mora_aplicada
                            ];
                        });

                        $resultado[] = [
                            'prestamo' => $prestamo,
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
            Log::error('Error en PagoElectronicoController@obtenerCuotasPendientes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener cuotas pendientes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Registrar pago de cuota con captura de pantalla
     */
    public function registrarPagoCuota(Request $request)
    {
        try {
            DB::beginTransaction();
            
            // Validar los datos del pago
            $validatedData = $request->validate([
                'idCuota' => 'required|exists:cuotas,idCuota',
                'monto_pagado' => 'required|numeric|min:0.01',
                'metodo_pago' => 'required|in:yape,plin,deposito',
                'capturapago' => 'required|image|mimes:jpeg,png,jpg|max:2048',
                'numero_operacion' => 'nullable|string|max:255',
                'observaciones' => 'nullable|string|max:255',
            ]);

            // Log para verificar si el archivo fue recibido
            Log::info('Archivo capturapago recibido', [
                'filename' => $request->file('capturapago') ? $request->file('capturapago')->getClientOriginalName() : 'No file',
                'size' => $request->file('capturapago') ? $request->file('capturapago')->getSize() : 0,
                'mime' => $request->file('capturapago') ? $request->file('capturapago')->getMimeType() : 'No mime'
            ]);

            // Verificar que el archivo exista
            if (!$request->hasFile('capturapago') || !$request->file('capturapago')->isValid()) {
                throw new \Exception('No se recibió un archivo válido para capturapago');
            }
            
            // Obtener la cuota
            $cuota = Cuota::findOrFail($validatedData['idCuota']);
            
            // Obtener el préstamo
            $prestamo = Prestamo::findOrFail($cuota->idPrestamo);
            
            // Verificar que la cuota no esté pagada
            if (in_array($cuota->estado, ['pagado', 'prepagado'])) {
                throw new \Exception('La cuota ya ha sido pagada');
            }
            
            // Validar que monto_pagado coincida con monto_a_pagar
            $condiciones = $this->evaluarCondicionesCuota($cuota, Carbon::now('America/Lima'));
            $monto_a_pagar = $condiciones['monto_a_pagar'];
            if (abs($validatedData['monto_pagado'] - $monto_a_pagar) > 0.01) {
                throw new \Exception('El monto pagado debe ser exactamente S/ ' . number_format($monto_a_pagar, 2));
            }
            
            // Crear el directorio
            $directorio = "public/clientes/{$prestamo->idCliente}/prestamos/{$prestamo->idPrestamo}/cuotas/{$cuota->idCuota}/capturapago";
            $fullPath = storage_path('app/' . $directorio);
            if (!File::exists($fullPath)) {
                Log::info('Creando directorio', ['path' => $fullPath]);
                File::makeDirectory($fullPath, 0755, true);
            } else {
                Log::info('Directorio ya existe', ['path' => $fullPath]);
            }

            // Verificar permisos de escritura
            if (!is_writable(dirname($fullPath))) {
                throw new \Exception('El directorio de almacenamiento no tiene permisos de escritura: ' . dirname($fullPath));
            }
            
            // Guardar la captura de pantalla con un nombre único usando timestamp
            $file = $request->file('capturapago');
            $extension = $file->getClientOriginalExtension();
            $timestamp = now()->format('Ymd_His'); // Formato: AñoMesDía_HoraMinutoSegundo
            $filename = "capturapago_{$timestamp}.{$extension}";
            $rutaComprobante = $directorio . '/' . $filename;

            // Guardar el archivo usando move para más control
            $saved = $file->move($fullPath, $filename);
            if (!$saved) {
                throw new \Exception('No se pudo guardar el archivo capturapago en ' . $rutaComprobante);
            }

            // Verificar que el archivo fue guardado
            if (!File::exists($fullPath . '/' . $filename)) {
                throw new \Exception('El archivo capturapago no se encuentra en la ruta esperada: ' . $rutaComprobante);
            }

            Log::info('Archivo capturapago guardado', [
                'path' => $rutaComprobante,
                'full_path' => $fullPath . '/' . $filename
            ]);
            
            // Registrar el pago
            $pago = new Pago();
            $pago->idCuota = $cuota->idCuota;
            $pago->numero_operacion = $validatedData['numero_operacion'] ?? null;
            $pago->monto_pagado = $validatedData['monto_pagado'];
            $pago->fecha_pago = now();
            $pago->observaciones = "Método: {$validatedData['metodo_pago']}; Número operación: " . 
                ($validatedData['numero_operacion'] ?? 'N/A') . 
                ($validatedData['observaciones'] ? "; {$validatedData['observaciones']}" : '');
            $pago->idUsuario = auth()->id();
            $pago->modalidad = 'virtual';
            $pago->save();
            
            // Actualizar estado de la cuota a prepagado
            $cuota->estado = 'prepagado';
            $cuota->save();
            
            // Verificar si todas las cuotas están pagadas
            $cuotasPendientes = Cuota::where('idPrestamo', $prestamo->idPrestamo)
                ->whereIn('estado', ['pendiente', 'vence_hoy', 'vencido'])
                ->count();
                
            if ($cuotasPendientes === 0) {
                $prestamo->estado = 'cancelado';
                $prestamo->save();
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Pago registrado correctamente, pendiente de aprobación',
                'data' => [
                    'pago' => $pago,
                    'cuota' => $cuota,
                    'capturapago_url' => Storage::url($rutaComprobante)
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en PagoElectronicoController@registrarPagoCuota: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar el pago: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

}