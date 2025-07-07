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
use Illuminate\Support\Facades\Validator;

class PagoController extends Controller
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

        $fecha_vencimiento = Carbon::parse($cuota->fecha_vencimiento);
        $hoy = $currentDateTime->copy()->startOfDay();
        $siguiente_dia = $hoy->copy()->addDay();

        // Obtener el préstamo asociado
        $prestamo = DB::table('prestamos')->where('idPrestamo', $cuota->idPrestamo)->first();
        if (!$prestamo) {
            Log::error('Préstamo no encontrado', ['idPrestamo' => $cuota->idPrestamo]);
            throw new \Exception('Préstamo no encontrado');
        }

        // Cuota vencida: calcular días de mora y aplicar cargo
        if ($fecha_vencimiento->lt($siguiente_dia) && !$fecha_vencimiento->isSameDay($hoy)) {
            $dias_mora = $fecha_vencimiento->diffInDays($hoy);

            // Determinar si se debe aplicar una mora incremental
            $dias_mora_anterior = $cuota->dias_mora;

            if ($dias_mora > $dias_mora_anterior || !$cuota->mora_aplicada) {
                $rango_dias_actual = $this->determinarRangoDias($dias_mora);
                $rango_dias_anterior = $dias_mora_anterior > 0 ? $this->determinarRangoDias($dias_mora_anterior) : null;

                // Obtener cargo por mora actual basado en el monto del préstamo
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

                    $cuota->observaciones = $cuota->observaciones ?
                        $cuota->observaciones . "; Mora incremental de {$cargo_mora_incremento} aplicada por {$dias_mora} días de mora (monto préstamo: {$prestamo->monto}, total cargo_mora: {$cargo_mora}) el " . $hoy->toDateTimeString() :
                        "Mora incremental de {$cargo_mora_incremento} aplicada por {$dias_mora} días de mora (monto préstamo: {$prestamo->monto}, total cargo_mora: {$cargo_mora}) el " . $hoy->toDateTimeString();

                    $cuota->save();

                    Log::info('Mora aplicada a cuota vencida, sin incrementar prestamo.total', [
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
                'mora' => $cargo_mora // Return accumulated cargo_mora
            ];
        }

        // Cuota vence hoy: actualizar estado a vence_hoy
        if ($fecha_vencimiento->isSameDay($hoy) && $cuota->estado === 'pendiente') {
            $cuota->estado = 'vence_hoy';
            $cuota->save();

            Log::info('Cuota actualizada a vence_hoy, sin incrementar prestamo.total', [
                'idCuota' => $cuota->idCuota,
                'estado' => $cuota->estado
            ]);
        }

        // Cuota siguiente: aplicar mora por horario (después de las 6 PM)
        if ($isNextCuota) {
            $hora = $currentDateTime->hour;
            if ($hora >= 18 && !$cuota->mora_aplicada) {
                $dias_mora = 1;
                $cuota->dias_mora = $dias_mora;
                $cuota->mora_aplicada = true;
                $cuota->fecha_mora_aplicada = $currentDateTime;

                // Obtener cargo por mora para 1 día
                $cargo_mora = $this->obtenerCargoMora('1 día', $prestamo->monto);
                $monto_a_pagar += $cargo_mora;
                $cuota->monto = $monto_a_pagar;
                $cuota->cargo_mora = $cargo_mora; // Store new cargo_mora (no accumulation here)

                $cuota->observaciones = $cuota->observaciones ?
                    $cuota->observaciones . "; Mora de {$cargo_mora} aplicada por cuota anterior después de las 6 PM (monto préstamo: {$prestamo->monto}) el " . $currentDateTime->toDateTimeString() :
                    "Mora de {$cargo_mora} aplicada por cuota anterior después de las 6 PM (monto préstamo: {$prestamo->monto}) el " . $currentDateTime->toDateTimeString();
                $cuota->save();

                Log::info('Mora aplicada a cuota siguiente, sin incrementar prestamo.total', [
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

        // Cuota no vence hoy
        if (!$fecha_vencimiento->isSameDay($hoy)) {
            return [
                'monto_a_pagar' => $monto_a_pagar,
                'mensaje' => '',
                'dias_mora' => $dias_mora,
                'mora' => $cargo_mora
            ];
        }

        // Cuota pendiente o vence hoy: evaluar ajuste por horario
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

                    Log::info('Ajuste de 3 soles aplicado, sin incrementar prestamo.total', [
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
     * Obtener las cuotas pendientes de un cliente
     */
    public function obtenerCuotasPendientes($idCliente)
    {
        try {
            return DB::transaction(function () use ($idCliente) {
                $cliente = User::where('idUsuario', $idCliente)
                    ->where('idRol', 2)
                    ->first();

                if (!$cliente) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cliente no encontrado'
                    ], 404);
                }

                $prestamos = Prestamo::where('idCliente', $idCliente)
                    ->where('estado', 'activo')
                    ->get();

                if ($prestamos->isEmpty()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El cliente no tiene préstamos activos'
                    ], 404);
                }

                $resultado = [];
                $currentDateTime = Carbon::now('America/Lima');

                foreach ($prestamos as $prestamo) {
                    $cuotas = Cuota::where('idPrestamo', $prestamo->idPrestamo)
                        ->orderBy('numero_cuota', 'asc')
                        ->get();

                    if ($cuotas->isNotEmpty()) {
                        $cuotasConExcedente = $cuotas->map(function ($cuota, $index) use ($prestamo, $currentDateTime, $cuotas) {
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

                            $cuotaAnterior = Cuota::where('idPrestamo', $prestamo->idPrestamo)
                                ->where('numero_cuota', $cuota->numero_cuota - 1)
                                ->first();

                            $excedenteAnterior = 0;
                            $modalidad = null;

                            if ($cuotaAnterior) {
                                $pagoAnterior = Pago::where('idCuota', $cuotaAnterior->idCuota)
                                    ->where('excedente', '>', 0)
                                    ->orderBy('idPago', 'desc')
                                    ->first();
                                $excedenteAnterior = $pagoAnterior ? $pagoAnterior->excedente : 0;
                            }

                            if ($cuota->estado === 'pagado') {
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
                                'dias_mora' => $condiciones['dias_mora'],
                                'excedente_anterior' => $excedenteAnterior,
                                'monto_a_pagar' => $condiciones['monto_a_pagar'],
                                'mensaje' => $condiciones['mensaje'],
                                'mora' => $cuota->cargo_mora, // Use cargo_mora as mora
                                'mora_reducida' => $cuota->mora_reducida,
                                'reduccion_mora_aplicada' => $cuota->reduccion_mora_aplicada
                            ];
                        });

                        $resultado[] = [
                            'prestamo' => $prestamo,
                            'cuotas' => $cuotasConExcedente
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
            Log::error('Error en PagoController@obtenerCuotasPendientes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener cuotas pendientes',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Registrar pago de cuota (parcial o total)
     */
    public function registrarPagoCuota(Request $request)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validate([
                'idCuota' => 'required|exists:cuotas,idCuota',
                'idCliente' => 'required|exists:usuarios,idUsuario', // Validar idCliente
                'monto_pagado' => 'required|numeric|min:0.01',
                'numero_operacion' => 'nullable|string|max:255',
                'observaciones' => 'nullable|string|max:255',
            ]);

            $cuota = Cuota::findOrFail($validatedData['idCuota']);
            $prestamo = Prestamo::findOrFail($cuota->idPrestamo);

            // Verificar que el idCliente corresponde al cliente del préstamo
            if ($prestamo->idCliente != $validatedData['idCliente']) {
                throw new \Exception('El cliente especificado no coincide con el cliente del préstamo');
            }

            // Verificar que el idCliente pertenece a un usuario con rol de cliente
            $cliente = User::where('idUsuario', $validatedData['idCliente'])
                ->where('idRol', 2) // Asumiendo que idRol 2 es para clientes
                ->first();

            if (!$cliente) {
                throw new \Exception('Cliente no encontrado o no es un cliente válido');
            }

            if ($cuota->estado === 'pagado') {
                throw new \Exception('La cuota ya ha sido pagada');
            }

            $montoTotalPagado = $validatedData['monto_pagado'];
            $excedente = 0;

            $cuotaAnterior = Cuota::where('idPrestamo', $prestamo->idPrestamo)
                ->where('numero_cuota', $cuota->numero_cuota - 1)
                ->first();

            $excedenteAnterior = 0;
            if ($cuotaAnterior) {
                $pagoAnterior = Pago::where('idCuota', $cuotaAnterior->idCuota)
                    ->where('excedente', '>', 0)
                    ->orderBy('idPago', 'desc')
                    ->first();
                $excedenteAnterior = $pagoAnterior ? $pagoAnterior->excedente : 0;
            }

            $montoCuota = $cuota->monto;
            $montoRestante = max(0, $montoCuota - $excedenteAnterior);

            if ($montoTotalPagado > $montoRestante) {
                $excedente = $montoTotalPagado - $montoRestante;
            }

            $pago = new Pago();
            $pago->idCuota = $cuota->idCuota;
            $pago->monto_pagado = $montoTotalPagado;
            $pago->excedente = $excedente;
            $pago->modalidad = 'presencial';
            $pago->numero_operacion = $validatedData['numero_operacion'] ?? null;
            $pago->fecha_pago = now();

            $observaciones = "Número operación: " . ($validatedData['numero_operacion'] ?? 'N/A');
            if ($excedenteAnterior > 0) {
                $observaciones .= " - Aplicado excedente anterior de {$excedenteAnterior}";
                if ($pagoAnterior) {
                    $pagoAnterior->excedente = 0;
                    $pagoAnterior->observaciones .= " - Excedente aplicado en cuota #{$cuota->idCuota}";
                    $pagoAnterior->save();
                }
            }

            if ($validatedData['observaciones']) {
                $observaciones .= " - " . $validatedData['observaciones'];
            }

            $pago->observaciones = $observaciones;
            $pago->idUsuario = auth()->id();
            $pago->save();

            $montoTotalAplicado = $montoTotalPagado + $excedenteAnterior;
            $cuota->estado = ($montoTotalAplicado >= $montoCuota) ? 'pagado' : 'pendiente';
            $cuota->save();

            $cuotaAjustada = null;
            if ($excedente > 0) {
                Log::info("Aplicando excedente de {$excedente} para el préstamo {$prestamo->idPrestamo}");
                $cuotaAjustada = $this->aplicarExcedente($prestamo->idPrestamo, $excedente, $pago->idPago, $cuota->numero_cuota);
            }

            $cuotasPendientes = Cuota::where('idPrestamo', $prestamo->idPrestamo)
                ->whereIn('estado', ['pendiente', 'vence_hoy', 'vencido'])
                ->count();

            if ($cuotasPendientes === 0) {
                $prestamo->estado = 'cancelado';
                $prestamo->save();

                $estadoPrestamo = EstadoPrestamo::where('idPrestamo', $prestamo->idPrestamo)
                    ->orderBy('fecha_actualizacion', 'desc')
                    ->first();

                if (!$estadoPrestamo) {
                    throw new \Exception('No se encontró registro en estado_prestamos para el préstamo');
                }

                $estadoPrestamo->update([
                    'estado' => 'cancelado',
                    'fecha_actualizacion' => now(),
                    'observacion' => 'Préstamo cancelado por pago total de cuotas',
                    'idUsuario' => $validatedData['idCliente'] // Usar idCliente enviado
                ]);
            }

            $rutaPDF = $this->generarComprobantePago($pago, $cuota, $prestamo);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pago registrado correctamente',
                'data' => [
                    'pago' => $pago,
                    'cuota' => $cuota,
                    'excedente' => $excedente,
                    'cuota_ajustada' => $cuotaAjustada,
                    'prestamo_cancelado' => $cuotasPendientes === 0,
                    'comprobante_url' => $rutaPDF
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en PagoController@registrarPagoCuota: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar el pago',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function reducirMora(Request $request)
    {
        try {
            $request->validate([
                'idCuota' => 'required|exists:cuotas,idCuota',
                'porcentaje_reduccion' => 'required|numeric|min:1|max:100',
            ]);

            $cuota = Cuota::findOrFail($request->idCuota);

            if ($cuota->reduccion_mora_aplicada) {
                return response()->json([
                    'success' => false,
                    'message' => 'La reducción de mora ya ha sido aplicada para esta cuota.',
                ], 400);
            }

            $moraActual = $cuota->cargo_mora ?? 0;
            $montoActual = $cuota->monto;
            $porcentajeReduccion = $request->porcentaje_reduccion;
            $reduccion = $moraActual * ($porcentajeReduccion / 100);
            $nuevaMora = $moraActual - $reduccion;
            $nuevoMonto = max(0, $montoActual - $reduccion); // Ensure monto doesn't go negative

            $cuota->update([
                'cargo_mora' => $nuevaMora,
                'monto' => $nuevoMonto,
                'mora_reducida' => $porcentajeReduccion,
                'reduccion_mora_aplicada' => true,
                'fecha_mora_aplicada' => now(),
                'observaciones' => $cuota->observaciones ?
                    $cuota->observaciones . "; Reducción de mora de {$porcentajeReduccion}% aplicada, mora reducida de {$moraActual} a {$nuevaMora}, monto ajustado de {$montoActual} a {$nuevoMonto} el " . now()->toDateTimeString() :
                    "Reducción de mora de {$porcentajeReduccion}% aplicada, mora reducida de {$moraActual} a {$nuevaMora}, monto ajustado de {$montoActual} a {$nuevoMonto} el " . now()->toDateTimeString()
            ]);

            Log::info('Reducción de mora aplicada', [
                'idCuota' => $cuota->idCuota,
                'mora_anterior' => $moraActual,
                'mora_nueva' => $nuevaMora,
                'monto_anterior' => $montoActual,
                'monto_nuevo' => $nuevoMonto,
                'reduccion' => $reduccion,
                'porcentaje_reduccion' => $porcentajeReduccion,
                'observaciones' => $cuota->observaciones
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Reducción de mora aplicada correctamente.',
                'data' => $cuota->fresh(), // Return refreshed cuota to ensure latest data
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error en PagoController@reducirMora: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Error al aplicar la reducción de mora.',
            ], 500);
        }
    }

    /**
     * Confirmar pago prepagado
     */
    public function confirmarPagoPrepagado(Request $request, $idCuota)
    {
        try {

            DB::beginTransaction();

            $cuota = Cuota::findOrFail($idCuota);
            $prestamo = Prestamo::findOrFail($cuota->idPrestamo);

            if ($cuota->estado !== 'prepagado') {
                throw new \Exception('La cuota no está en estado prepagado');
            }

            $pago = Pago::where('idCuota', $cuota->idCuota)->first();
            if (!$pago) {
                throw new \Exception('No se encontró un pago asociado a esta cuota');
            }

            $cuota->estado = 'pagado';
            $cuota->observaciones = ($cuota->observaciones ?? '') .
                " - Pago prepagado confirmado el " . now()->toDateTimeString();
            $cuota->save();

            $rutaPDF = $this->generarComprobantePago($pago, $cuota, $prestamo);

            $cuotasPendientes = Cuota::where('idPrestamo', $prestamo->idPrestamo)
                ->whereIn('estado', ['pendiente', 'vence_hoy', 'vencido'])
                ->count();

            if ($cuotasPendientes === 0) {
                $prestamo->estado = 'cancelado';
                $prestamo->save();

                $estadoPrestamo = EstadoPrestamo::where('idPrestamo', $prestamo->idPrestamo)
                    ->orderBy('fecha_actualizacion', 'desc')
                    ->first();

                if (!$estadoPrestamo) {
                    throw new \Exception('No se encontró registro en estado_prestamos para el préstamo');
                }

                $estadoPrestamo->update([
                    'estado' => 'cancelado',
                    'fecha_actualizacion' => now(),
                    'observacion' => 'Préstamo cancelado por pago total de cuotas',
                    'idUsuario' => auth()->id()
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pago prepagado confirmado correctamente',
                'data' => [
                    'cuota' => $cuota,
                    'prestamo_cancelado' => $cuotasPendientes === 0,
                    'comprobante_url' => $rutaPDF
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en PagoController@confirmarPagoPrepagado: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al confirmar el pago prepagado',
                'error' => $e->getMessage()
            ], 500);
        }
    }

      /**
     * Rechazar un pago prepagado, eliminar la captura, eliminar el pago y cambiar el estado a pendiente.
     *
     * @param  Request  $request
     * @param  int  $idCuota
     * @return \Illuminate\Http\JsonResponse
     */
    public function rechazarPagoPrepagado(Request $request, $idCuota)
    {
        try {
            // Validar los datos de entrada
            $validator = Validator::make($request->all(), [
                'idCliente' => 'required|integer|exists:usuarios,idUsuario',
                'idPrestamo' => 'required|integer|exists:prestamos,idPrestamo',
                'idCuota' => 'required|integer|exists:cuotas,idCuota',
                'motivo' => 'required|string|min:3|max:500', // Validate motivo
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Datos de entrada inválidos',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $data = $validator->validated();

            // Verificar que idCuota coincide con el parámetro de la URL
            if ($data['idCuota'] != $idCuota) {
                return response()->json([
                    'message' => 'El ID de cuota en el cuerpo no coincide con el parámetro de la URL',
                ], 422);
            }

            // Iniciar transacción
            DB::beginTransaction();

            // Buscar la cuota
            $cuota = Cuota::findOrFail($idCuota);

            // Verificar que la cuota está en estado 'prepagado'
            if ($cuota->estado !== 'prepagado') {
                return response()->json([
                    'message' => 'La cuota no está en estado prepagado',
                ], 400);
            }

            // Buscar el préstamo para validar idCliente y idPrestamo
            $prestamo = Prestamo::findOrFail($data['idPrestamo']);
            if ($prestamo->idCliente != $data['idCliente'] || $cuota->idPrestamo != $data['idPrestamo']) {
                return response()->json([
                    'message' => 'Los datos de cliente o préstamo no coinciden con la cuota',
                ], 400);
            }

            // Definir el directorio de la captura de pago
            $directorio = storage_path("app/public/clientes/{$data['idCliente']}/prestamos/{$data['idPrestamo']}/cuotas/{$idCuota}/capturapago");

            // Eliminar los archivos en el directorio
            if (File::exists($directorio)) {
                File::deleteDirectory($directorio);
                Log::info("Directorio de captura de pago eliminado: {$directorio}");
            } else {
                Log::info("Directorio de captura de pago no encontrado: {$directorio}");
            }

            // Buscar el registro de pago asociado
            $pago = Pago::where('idCuota', $idCuota)->first();
            if ($pago) {
                // Actualizar observaciones del pago antes de eliminarlo
                $pago->observaciones = ($pago->observaciones ? $pago->observaciones . "\n" : '') .
                    "Pago rechazado por motivo: {$data['motivo']} el " . now()->format('d/m/Y H:i:s');
                $pago->save();
                $pago->delete();
                Log::info("Registro de pago eliminado para idCuota {$idCuota}");
            } else {
                Log::info("No se encontró registro de pago para idCuota {$idCuota}");
            }

            // Actualizar el estado de la cuota a 'pendiente'
            $cuota->estado = 'pendiente';
            $cuota->observaciones = ($cuota->observaciones ? $cuota->observaciones . "\n" : '') .
                "Pago prepagado rechazado por motivo: {$data['motivo']} el " . now()->format('d/m/Y H:i:s');
            $cuota->save();

            Log::info("Cuota {$idCuota} actualizada a estado 'pendiente'");

            DB::commit();

            return response()->json([
                'message' => 'Pago rechazado correctamente',
                'data' => [
                    'idCuota' => $cuota->idCuota,
                    'estado' => $cuota->estado,
                ],
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error al rechazar el pago prepagado para la cuota {$idCuota}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Error al rechazar el pago: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Aplicar el excedente actualizando el monto de la siguiente cuota
     */
    private function aplicarExcedente($idPrestamo, $excedente, $idPagoOriginal, $numeroCuotaActual)
    {
        $cuotaSiguiente = Cuota::where('idPrestamo', $idPrestamo)
            ->where('numero_cuota', $numeroCuotaActual + 1)
            ->whereIn('estado', ['pendiente', 'vence_hoy', 'vencido'])
            ->first();

        if (!$cuotaSiguiente) {
            Log::info("No se encontró cuota siguiente para aplicar excedente de {$excedente} en préstamo {$idPrestamo}");
            return null;
        }

        $montoOriginal = $cuotaSiguiente->monto;
        $nuevoMonto = max(0, $montoOriginal - $excedente);

        $cuotaSiguiente->monto = $nuevoMonto;
        $cuotaSiguiente->observaciones = ($cuotaSiguiente->observaciones ?? '') .
            " - Monto ajustado por excedente de {$excedente} soles del pago #{$idPagoOriginal} de la cuota #{$numeroCuotaActual}";
        $cuotaSiguiente->save();

        Log::info("Cuota {$cuotaSiguiente->idCuota} actualizada: monto original {$montoOriginal}, nuevo monto {$nuevoMonto}, excedente aplicado {$excedente}");

        return [
            'idCuota' => $cuotaSiguiente->idCuota,
            'numero_cuota' => $cuotaSiguiente->numero_cuota,
            'monto_anterior' => $montoOriginal,
            'monto_nuevo' => $nuevoMonto,
            'excedente_aplicado' => $excedente
        ];
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
            $usuario = User::with('datos')->findOrFail($pago->idUsuario);

            Log::info('generarComprobantePago: Datos cliente', [
                'idUsuario' => $cliente->idUsuario,
                'idDatos' => $cliente->idDatos,
                'datosCliente' => $datosCliente ? $datosCliente->toArray() : null
            ]);

            if (!$datosCliente) {
                throw new \Exception('No se encontraron datos del cliente');
            }
            $ahora = Carbon::now('America/Lima');
            $data = [
                'cliente' => $cliente,
                'datosCliente' => $datosCliente,
                'prestamo' => $prestamo,
                'cuota' => $cuota,
                'pago' => $pago,
                'fecha'    => $ahora->format('d/m/Y'),
                'hora'     => $ahora->format('h:i A'),
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

    /**
     * Obtener la captura de pago por idCuota
     */
    public function obtenerCapturaPago(Request $request, $idCuota)
    {
        try {
            // Obtener la cuota
            $cuota = Cuota::findOrFail($idCuota);
            $prestamo = Prestamo::findOrFail($cuota->idPrestamo);
            
            // Directorio de la captura
            $directorio = "public/clientes/{$prestamo->idCliente}/prestamos/{$prestamo->idPrestamo}/cuotas/{$cuota->idCuota}/capturapago";
            $fullPath = storage_path('app/' . $directorio);

            // Buscar cualquier archivo en el directorio con extensiones jpg, jpeg o png
            $extensions = ['jpg', 'jpeg', 'png'];
            $capturaPath = null;
            $files = File::files($fullPath); // Obtener todos los archivos en el directorio
            foreach ($files as $file) {
                if (in_array(strtolower($file->getExtension()), $extensions)) {
                    $filename = $file->getFilename();
                    $capturaPath = "storage/clientes/{$prestamo->idCliente}/prestamos/{$prestamo->idPrestamo}/cuotas/{$cuota->idCuota}/capturapago/{$filename}";
                    break; // Tomar el primer archivo válido encontrado
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

    /**
     * Buscar clientes por DNI, nombre o apellido
     */
    public function buscarClientes(Request $request)
    {
        try {
            $query = $request->input('query');

            if (empty($query)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debe ingresar un criterio de búsqueda'
                ], 400);
            }

            $clientes = User::where('idRol', 2)
                ->join('datos', 'usuarios.idDatos', '=', 'datos.idDatos')
                ->where(function ($q) use ($query) {
                    $q->where('datos.dni', 'like', '%' . $query . '%')
                        ->orWhere('datos.nombre', 'like', '%' . $query . '%')
                        ->orWhere('datos.apellidoPaterno', 'like', '%' . $query . '%')
                        ->orWhere('datos.apellidoMaterno', 'like', '%' . $query . '%');
                })
                ->select('usuarios.idUsuario', 'datos.dni', 'datos.nombre', 'datos.apellidoPaterno', 'datos.apellidoMaterno')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $clientes
            ]);
        } catch (\Exception $e) {
            Log::error('Error en PagoController@buscarClientes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al buscar clientes',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
