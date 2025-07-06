<?php

namespace App\Http\Controllers;

use App\Models\Grupo;
use App\Models\Prestamo;
use App\Models\Cuota;
use App\Models\User;
use App\Models\Datos;
use Illuminate\Http\Request;

class FiltrarPrestamosController extends Controller
{
    // Fetch all groups
    public function getGroups()
    {
        $groups = Grupo::with('asesor.datos')
            ->where('estado', 'activo')
            ->get()
            ->map(function ($group) {
                return [
                    'idGrupo' => $group->idGrupo,
                    'nombre' => $group->nombre,
                    'asesor' => $group->asesor->datos->nombre . ' ' . 
                               $group->asesor->datos->apellidoPaterno . ' ' . 
                               $group->asesor->datos->apellidoMaterno
                ];
            });

        return response()->json($groups);
    }

    // Fetch all advisors
    public function getAdvisors()
    {
        $asesores = User::with('datos')
            ->whereHas('rol', function ($query) {
                $query->where('nombre', 'asesor');
            })
            ->where('estado', 1)
            ->get()
            ->map(function ($asesor) {
                return [
                    'id' => $asesor->idUsuario,
                    'nombre' => $asesor->datos->nombre,
                    'apellidoPaterno' => $asesor->datos->apellidoPaterno,
                    'apellidoMaterno' => $asesor->datos->apellidoMaterno,
                    'apellidoConyuge' => $asesor->datos->apellidoConyuge
                ];
            });

        return response()->json($asesores);
    }

    public function getClients(Request $request)
    {
        $groupId = $request->input('group_id');
        $advisorId = $request->input('advisor_id');
        $search = $request->input('search');
        $withLoans = filter_var($request->input('with_loans', false), FILTER_VALIDATE_BOOLEAN);

        $query = User::with(['datos', 'prestamos'])
            ->whereHas('rol', function ($query) {
                $query->where('nombre', 'cliente');
            })
            ->where('estado', 1);

        // Apply group filter
        if ($groupId) {
            $query->whereHas('prestamos', function ($query) use ($groupId) {
                $query->where('idGrupo', $groupId);
            });
        }

        // Apply advisor filter
        if ($advisorId) {
            $query->whereHas('prestamos', function ($query) use ($advisorId) {
                $query->where('idAsesor', $advisorId);
            });
        }

        // Apply search filter
        if ($search) {
            $query->whereHas('datos', function ($query) use ($search) {
                $search = trim($search);
                $query->where('dni', 'LIKE', "%{$search}%")
                    ->orWhere('nombre', 'LIKE', "%{$search}%")
                    ->orWhere('apellidoPaterno', 'LIKE', "%{$search}%")
                    ->orWhere('apellidoMaterno', 'LIKE', "%{$search}%");
            });
        }

        $clients = $query->get()->map(function ($client) use ($advisorId) {
            // Filter loans by advisor if advisorId is provided
            $prestamos = $client->prestamos;
            if ($advisorId) {
                $prestamos = $prestamos->where('idAsesor', $advisorId);
            }

            return [
                'idUsuario' => $client->idUsuario,
                'nombre' => $client->datos->nombre,
                'apellidoPaterno' => $client->datos->apellidoPaterno,
                'apellidoMaterno' => $client->datos->apellidoMaterno,
                'dni' => $client->datos->dni,
                'prestamoCount' => $prestamos->count()
            ];
        });

        // Filter out clients with zero loans if with_loans is true
        if ($withLoans) {
            $clients = $clients->filter(function ($client) {
                return $client['prestamoCount'] > 0;
            })->values();
        }

        return response()->json($clients);
    }

    public function getLoans(Request $request)
    {
        try {
            $dni = $request->input('dni');
            $clientId = $request->input('client_id');
            $nombre = $request->input('nombre');
            $apellidos = $request->input('apellidos');
            $advisorId = $request->input('advisor_id');

            $query = Prestamo::with(['cliente.datos', 'asesor.datos', 'estados']);

            if ($dni) {
                $query->whereHas('cliente.datos', function ($query) use ($dni) {
                    $query->where('dni', $dni);
                });
            }

            if ($clientId) {
                $query->where('idCliente', $clientId);
            }

            if ($advisorId) {
                $query->where('idAsesor', $advisorId);
            }

            if ($nombre || $apellidos) {
                $query->whereHas('cliente.datos', function ($query) use ($nombre, $apellidos) {
                    if ($nombre) {
                        $nombre = trim($nombre);
                        $query->where(function ($q) use ($nombre) {
                            $q->where('nombre', 'LIKE', "%{$nombre}%");
                        });
                    }

                    if ($apellidos) {
                        $apellidos = trim($apellidos);
                        $query->where(function ($q) use ($apellidos) {
                            $q->where('apellidoPaterno', 'LIKE', "%{$apellidos}%")
                            ->orWhere('apellidoMaterno', 'LIKE', "%{$apellidos}%")
                            ->orWhere('apellidoConyuge', 'LIKE', "%{$apellidos}%");
                        });
                    }
                });
            }

            $loans = $query->get()->map(function ($loan) {
                $estadoPrestamo = $loan->estados->sortByDesc('fecha_actualizacion')->first();
                $estado = $estadoPrestamo ? $estadoPrestamo->estado : 'vigente';

                return [
                    'idPrestamo' => $loan->idPrestamo,
                    'abonado_por' => $loan->abonado_por ?? '-',
                    'producto' => ($loan->producto && $loan->producto->nombre && $loan->producto->rango_tasa) 
                        ? $loan->producto->nombre . ' ' . $loan->producto->rango_tasa 
                        : '-',
                    'monto' => $loan->monto,
                    'total' => $loan->total,
                    'cuotas' => $loan->cuotas,
                    'valor_cuota' => $loan->valor_cuota,
                    'frecuencia' => $loan->frecuencia,
                    'fecha_inicio' => $loan->fecha_inicio,
                    'estado' => $estado,
                    'cliente' => $loan->cliente->datos->nombre . ' ' . 
                                $loan->cliente->datos->apellidoPaterno . ' ' . 
                                $loan->cliente->datos->apellidoMaterno,
                    'asesor' => $loan->asesor->datos->nombre . ' ' . 
                            $loan->asesor->datos->apellidoPaterno . ' ' . 
                            $loan->asesor->datos->apellidoMaterno
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $loans
            ]);
        } catch (\Exception $e) {
            \Log::error('Error en PagoController@getLoans: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los préstamos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Fetch installments by loan ID
    public function getInstallments(Request $request)
    {
        $loanId = $request->input('loan_id');
        $estado = $request->input('estado');

        $query = Cuota::with(['prestamo.cliente.datos', 'prestamo.asesor.datos'])
            ->where('idPrestamo', $loanId);

        if ($estado) {
            $query->where('estado', $estado);
        }

        $cuotas = $query->get()->map(function ($cuota) {
            return [
                'idCuota' => $cuota->idCuota,
                'numero_cuota' => $cuota->numero_cuota,
                'monto' => $cuota->monto,
                'capital' => $cuota->capital,
                'interes' => $cuota->interes,
                'otros' => ($cuota->monto - $cuota->capital - $cuota->interes - ($cuota->mora ?? 0)),
                'fecha_vencimiento' => $cuota->fecha_vencimiento,
                'estado' => $cuota->estado,
                'dias_mora' => $cuota->dias_mora ?? 0, // Return dias_mora directly
                'mora' => $cuota->mora ?? 0, // Return mora directly
                'mora_reducida' => $cuota->mora_reducida,
                'reduccion_mora_aplicada' => $cuota->reduccion_mora_aplicada,
                'observaciones' => $cuota->observaciones ?? '-',
                'cliente_nombre' => $cuota->prestamo->cliente->datos->nombre . ' ' . 
                                  $cuota->prestamo->cliente->datos->apellidoPaterno . ' ' . 
                                  $cuota->prestamo->cliente->datos->apellidoMaterno,
                'cliente_dni' => $cuota->prestamo->cliente->datos->dni,
                'asesor_nombre' => $cuota->prestamo->asesor->datos->nombre . ' ' . 
                                 $cuota->prestamo->asesor->datos->apellidoPaterno . ' ' . 
                                 $cuota->prestamo->asesor->datos->apellidoMaterno
            ];
        });

        return response()->json($cuotas);
    }
}