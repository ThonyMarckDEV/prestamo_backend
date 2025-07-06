<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Prestamo;
use App\Models\Cuota;
use App\Models\EstadoPrestamo;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReprogramacionPrestamoController extends Controller
{
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
     * Reprogramar un préstamo
     */
    public function reprogramarPrestamo(Request $request)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validate([
                'idPrestamo' => 'required|exists:prestamos,idPrestamo',
                'tasa_interes' => 'required|numeric|min:1|max:5',
                'observaciones' => 'nullable|string|max:255',
            ]);

            $prestamo = Prestamo::findOrFail($validatedData['idPrestamo']);
            
            // Log para verificar datos iniciales
            Log::info('Iniciando reprogramación', [
                'idPrestamo' => $prestamo->idPrestamo,
                'frecuencia' => $prestamo->frecuencia,
                'cuotas' => $prestamo->cuotas,
            ]);

            // Check if client is disabled
            $cliente = User::findOrFail($prestamo->idCliente);
            if ($cliente->estado === 3) {
                throw new \Exception('El cliente está inhabilitado y no puede reprogramar préstamos');
            }

            if ($prestamo->estado !== 'activo') {
                throw new \Exception('El préstamo no está activo y no puede ser reprogramado');
            }

            // Check if loan has already been reprogrammed
            $estadoPrestamo = EstadoPrestamo::where('idPrestamo', $prestamo->idPrestamo)
                ->orderBy('fecha_actualizacion', 'desc')
                ->first();

            if (!$estadoPrestamo) {
                throw new \Exception('No se encontró registro en estado_prestamos para el préstamo');
            }

            // Recuperar todas las cuotas para inspección
            $cuotasTotales = Cuota::where('idPrestamo', $prestamo->idPrestamo)
                ->orderBy('numero_cuota')
                ->get();

            // Log todas las cuotas para depuración
            Log::info('Todas las cuotas del préstamo', [
                'cuotas' => $cuotasTotales->map(function ($cuota) {
                    return [
                        'numero_cuota' => $cuota->numero_cuota,
                        'fecha_vencimiento' => $cuota->fecha_vencimiento,
                        'monto' => $cuota->monto,
                        'capital' => $cuota->capital,
                        'interes' => $cuota->interes,
                        'estado' => $cuota->estado,
                        'dias_mora' => $cuota->dias_mora,
                    ];
                })->toArray(),
            ]);

            // Recuperar solo cuotas no pagadas (pendiente, vence_hoy, vencido)
            $cuotas = Cuota::where('idPrestamo', $prestamo->idPrestamo)
                ->whereIn('estado', ['pendiente', 'vence_hoy', 'vencido'])
                ->orderBy('numero_cuota')
                ->get();

            if ($cuotas->isEmpty()) {
                throw new \Exception('No hay cuotas pendientes para reprogramar');
            }

            // Log advertencia si hay más cuotas de las esperadas
            if ($cuotasTotales->count() != $prestamo->cuotas) {
                Log::warning('Número de cuotas totales no coincide con prestamo->cuotas', [
                    'cuotas_encontradas' => $cuotasTotales->count(),
                    'cuotas_esperadas' => $prestamo->cuotas,
                    'cuotas_no_pagadas' => $cuotas->count(),
                ]);
            }

            // Log estado de cuotas no pagadas antes de la actualización
            Log::info('Cuotas no pagadas antes de la reprogramación', [
                'cuotas' => $cuotas->map(function ($cuota) {
                    return [
                        'numero_cuota' => $cuota->numero_cuota,
                        'fecha_vencimiento' => $cuota->fecha_vencimiento,
                        'monto' => $cuota->monto,
                        'capital' => $cuota->capital,
                        'interes' => $cuota->interes,
                        'estado' => $cuota->estado,
                        'dias_mora' => $cuota->dias_mora,
                    ];
                })->toArray(),
            ]);

            $maxDiasMora = $cuotas->max('dias_mora');
            if ($maxDiasMora > 8) {
                throw new \Exception('El préstamo no es elegible para reprogramación debido a más de 8 días de mora');
            }

            // Calculate total amount for unpaid cuotas including mora
            $montoTotal = $cuotas->sum(function ($cuota) {
                $rangoDias = $this->determinarRangoDias($cuota->dias_mora);
                $cargoMora = $this->obtenerCargoMora($rangoDias, $cuota->monto);
                return $cuota->capital + $cuota->interes + $cargoMora;
            });

            // Log para verificar monto total
            Log::info('Monto total calculado para cuotas no pagadas', ['monto_total' => $montoTotal]);

            // Apply new interest rate
            $nuevaTasa = $validatedData['tasa_interes'] / 100;
            $nuevoTotal = $montoTotal * (1 + $nuevaTasa);

            // Set new start date based on the first unpaid cuota's fecha_vencimiento
            $primeraCuotaNoPagada = $cuotas->first();
            if (!$primeraCuotaNoPagada) {
                throw new \Exception('No se encontró la primera cuota no pagada');
            }

            // Establecer la nueva fecha base avanzando la frecuencia desde la primera cuota no pagada
            $frecuencia = $prestamo->frecuencia;
            $fechaInicio = Carbon::parse($primeraCuotaNoPagada->fecha_vencimiento)->startOfDay();

            // Avanzar la fecha según la frecuencia
            if ($frecuencia === 'semanal') {
                $fechaInicio->addWeek(); // Avanza 1 semana
            } elseif ($frecuencia === 'catorcenal') {
                $fechaInicio->addDays(14); // Avanza 14 días
            } elseif ($frecuencia === 'mensual') {
                $fechaInicio->addMonth(); // Avanza 1 mes
            }

            // Log para verificar fecha base
            Log::info('Fecha base establecida para reprogramación', [
                'fecha_original' => $primeraCuotaNoPagada->fecha_vencimiento,
                'nueva_fecha_inicio' => $fechaInicio->toDateString(),
                'frecuencia' => $frecuencia,
                'reprogramacion' => ($estadoPrestamo->veces_reprogramado ?? 0) + 1,
            ]);

            // Update loan with new cuotas count
            $numeroCuotas = $cuotas->count();
            $prestamo->cuotas = $numeroCuotas;
            $prestamo->interes = $validatedData['tasa_interes'];
            $prestamo->total = $nuevoTotal;
            $prestamo->modalidad = 'RCS';
            $prestamo->fecha_inicio = $fechaInicio;
            $prestamo->valor_cuota = $nuevoTotal / $numeroCuotas;
            $prestamo->save();

            // Update only unpaid cuotas
            $valorCuota = $nuevoTotal / $numeroCuotas;
            $capitalPorCuota = $montoTotal / $numeroCuotas;
            $interesPorCuota = ($nuevoTotal - $montoTotal) / $numeroCuotas;
            $vecesReprogramado = ($estadoPrestamo->veces_reprogramado ?? 0);

            foreach ($cuotas as $index => $cuota) {
                // Calcular la fecha de vencimiento esperada
                $fechaVencimiento = $fechaInicio->copy();
                if ($frecuencia === 'semanal') {
                    $fechaVencimiento->addWeeks($index);
                } elseif ($frecuencia === 'catorcenal') {
                    $fechaVencimiento->addDays(14 * $index);
                } elseif ($frecuencia === 'mensual') {
                    $fechaVencimiento->addMonths($index);
                }
                $fechaVencimiento = $fechaVencimiento->toDateString();

                // Log para depuración
                Log::info('Actualizando cuota no pagada', [
                    'numero_cuota' => $cuota->numero_cuota,
                    'fecha_vencimiento' => $fechaVencimiento,
                    'monto' => $valorCuota,
                    'capital' => $capitalPorCuota,
                    'interes' => $interesPorCuota,
                    'reprogramacion' => $vecesReprogramado + 1,
                ]);

                // Actualizar cuota
                $cuota->update([
                    'monto' => $valorCuota,
                    'capital' => $capitalPorCuota,
                    'interes' => $interesPorCuota,
                    'fecha_vencimiento' => $fechaVencimiento,
                    'estado' => 'pendiente',
                    'dias_mora' => 0,
                    'observaciones' => 'Cuota actualizada por reprogramación #' . ($vecesReprogramado + 1),
                ]);
            }

            // Log estado de todas las cuotas después de la actualización
            $cuotasDespues = Cuota::where('idPrestamo', $prestamo->idPrestamo)
                ->orderBy('numero_cuota')
                ->get();
            Log::info('Cuotas después de la reprogramación', [
                'cuotas' => $cuotasDespues->map(function ($cuota) {
                    return [
                        'numero_cuota' => $cuota->numero_cuota,
                        'fecha_vencimiento' => $cuota->fecha_vencimiento,
                        'monto' => $cuota->monto,
                        'capital' => $cuota->capital,
                        'interes' => $cuota->interes,
                        'estado' => $cuota->estado,
                    ];
                })->toArray(),
            ]);

            // Update estado_prestamo
            $estadoPrestamo->update([
                'estado' => 'reprogramado',
                'veces_reprogramado' => $vecesReprogramado + 1,
                'fecha_actualizacion' => now(),
                'observacion' => 'Préstamo reprogramado con nueva tasa de ' . $validatedData['tasa_interes'] . '%. Veces reprogramado: ' . ($vecesReprogramado + 1) .
                                ($validatedData['observaciones'] ? ' Observaciones: ' . $validatedData['observaciones'] : ''),
                'idUsuario' => auth()->id()
            ]);

            DB::commit();
            Log::info('Transacción confirmada');

            return response()->json([
                'success' => true,
                'message' => 'Préstamo reprogramado correctamente',
                'data' => ['prestamo' => $prestamo->load('cuotas')]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en ReprogramacionPrestamoController@reprogramarPrestamo: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al reprogramar el préstamo',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}