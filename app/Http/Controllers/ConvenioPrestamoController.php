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

class ConvenioPrestamoController extends Controller
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
     * Registrar un convenio (refinanciamiento)
     */
    public function registrarConvenio(Request $request)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validate([
                'idPrestamo' => 'required|exists:prestamos,idPrestamo',
                'solo_capital' => 'required|boolean',
                'observaciones' => 'nullable|string|max:255',
            ]);

            $prestamo = Prestamo::findOrFail($validatedData['idPrestamo']);
            $cliente = User::findOrFail($prestamo->idCliente);

            // Check if client is disabled
            if ($cliente->estado === 'inhabilitado') {
                throw new \Exception('El cliente está inhabilitado y no puede realizar convenios');
            }

            // Check refinanciamiento limit
            $estadoPrestamo = EstadoPrestamo::where('idPrestamo', $prestamo->idPrestamo)
                ->orderBy('fecha_actualizacion', 'desc')
                ->first();

            if (!$estadoPrestamo) {
                throw new \Exception('No se encontró registro en estado_prestamos para el préstamo');
            }

            if ($estadoPrestamo->veces_refinanciado >= 2) {
                // Disable client
                $cliente->update(['estado' => 3]);
                throw new \Exception('Límite de refinanciamientos alcanzado. El cliente ha sido inhabilitado.');
            }

            if ($prestamo->estado !== 'activo') {
                throw new \Exception('El préstamo no está activo y no puede ser refinanciado');
            }

            $cuotas = Cuota::where('idPrestamo', $prestamo->idPrestamo)
                ->whereIn('estado', ['pendiente', 'vence_hoy', 'vencido'])
                ->get();

            if ($cuotas->isEmpty()) {
                throw new \Exception('No hay cuotas pendientes para refinanciar');
            }

            // Calculate total amount (with or without interest)
            $montoTotal = $cuotas->sum(function ($cuota) use ($validatedData) {
                $rangoDias = $this->determinarRangoDias($cuota->dias_mora);
                $cargoMora = $this->obtenerCargoMora($rangoDias, $cuota->monto);
                return $validatedData['solo_capital'] 
                    ? $cuota->capital + $cargoMora 
                    : $cuota->capital + $cuota->interes + $cargoMora;
            });

            // Keep current interest rate
            $nuevoTotal = $montoTotal;
            $frecuencia = $prestamo->frecuencia;
            $fechaInicio = Carbon::now();
            if ($frecuencia === 'semanal') {
                $fechaInicio->addWeek();
            } elseif ($frecuencia === 'catorcenal') {
                $fechaInicio->addDays(14);
            } elseif ($frecuencia === 'mensual') {
                $fechaInicio->addMonth();
            }

            // Update loan
            $prestamo->total = $nuevoTotal;
            $prestamo->modalidad = 'RSS';
            $prestamo->fecha_inicio = $fechaInicio;
            $prestamo->save();

            // Delete existing cuotas
            Cuota::where('idPrestamo', $prestamo->idPrestamo)->delete();

            // Generate new schedule
            $numeroCuotas = $prestamo->cuotas;
            $valorCuota = $nuevoTotal / $numeroCuotas;
            $capitalPorCuota = $montoTotal / $numeroCuotas;
            $interesPorCuota = $validatedData['solo_capital'] ? 0 : ($nuevoTotal - $montoTotal) / $numeroCuotas;

            for ($i = 1; $i <= $numeroCuotas; $i++) {
                $fechaVencimiento = $fechaInicio->copy();
                if ($frecuencia === 'semanal') {
                    $fechaVencimiento->addWeeks($i);
                } elseif ($frecuencia === 'catorcenal') {
                    $fechaVencimiento->addDays(14 * $i);
                } elseif ($frecuencia === 'mensual') {
                    $fechaVencimiento->addMonths($i);
                }

                Cuota::create([
                    'idPrestamo' => $prestamo->idPrestamo,
                    'numero_cuota' => $i,
                    'monto' => $valorCuota,
                    'capital' => $capitalPorCuota,
                    'interes' => $interesPorCuota,
                    'fecha_vencimiento' => $fechaVencimiento,
                    'estado' => 'pendiente',
                    'dias_mora' => 0,
                    'observaciones' => 'Cuota generada por convenio' . ($validatedData['solo_capital'] ? ' (solo capital)' : ''),
                ]);
            }

            // Update estado_prestamo
            $estadoPrestamo->update([
                'estado' => 'refinanciado',
                'veces_refinanciado' => ($estadoPrestamo->veces_refinanciado ?? 0) + 1,
                'fecha_actualizacion' => now(),
                'observacion' => 'Préstamo refinanciado' . ($validatedData['solo_capital'] ? ' (solo capital)' : '') . 
                                '. Veces refinanciado: ' . (($estadoPrestamo->veces_refinanciado ?? 0) + 1) .
                                ($validatedData['observaciones'] ? ' Observaciones: ' . $validatedData['observaciones'] : ''),
                'idUsuario' => auth()->id()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Convenio registrado correctamente',
                'data' => ['prestamo' => $prestamo]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en ReprogramacionPrestamoController@registrarConvenio: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar el convenio',
                'error' => $e->getMessage()
            ], 500);
        }
    }
   
}