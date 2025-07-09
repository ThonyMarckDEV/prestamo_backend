<?php

namespace App\Http\Controllers;

use App\Models\Grupo;
use App\Models\Prestamo;
use App\Models\Cuota;
use App\Models\User;
use App\Models\Datos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class FiltrarPrestamosController extends Controller
{
    // Fetch all groups
    public function getGroups(Request $request)
    {
        try {
            $grupos = Grupo::with('asesor.datos')
                ->where('estado', 'activo')
                ->get()
                ->map(function ($grupo) {
                    return [
                        'idGrupo' => $grupo->idGrupo,
                        'nombre' => $grupo->nombre,
                        'asesor' => $grupo->asesor && $grupo->asesor->datos
                            ? "{$grupo->asesor->datos->nombre} {$grupo->asesor->datos->apellidoPaterno} {$grupo->asesor->datos->apellidoMaterno}"
                            : 'Sin asesor'
                    ];
                });

            return response()->json($grupos);
        } catch (\Exception $e) {
            Log::error('Error en FiltrarPrestamosController@getGrupos: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error al cargar los grupos'
            ], 500);
        }
    }

    // Fetch all advisors
    public function getAdvisors(Request $request)
    {
        try {
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
                        'apellidoConyuge' => $asesor->datos->apellidoConyuge ?? null
                    ];
                });

            return response()->json(['asesores' => $asesores]);
        } catch (\Exception $e) {
            Log::error('Error en FiltrarPrestamosController@getAsesores: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error al cargar los asesores'
            ], 500);
        }
    }

    // Fetch clients with filters
    public function getClients(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'idGrupo' => 'nullable|exists:grupos,idGrupo',
                'idAsesor' => 'nullable|exists:usuarios,idUsuario',
                'search' => 'nullable|string|max:255',
                'with_loans' => 'nullable|in:0,1,true,false' // Allow string representations
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => $validator->errors()->first()
                ], 422);
            }

            $idGrupo = $request->input('idGrupo');
            $idAsesor = $request->input('idAsesor');
            $search = $request->input('search');
            $withLoans = filter_var($request->input('with_loans', false), FILTER_VALIDATE_BOOLEAN);

            $query = User::with(['datos', 'prestamos'])
                ->whereHas('rol', function ($query) {
                    $query->where('nombre', 'cliente');
                })
                ->where('estado', 1);

            if ($idGrupo) {
                $query->whereHas('prestamos', function ($query) use ($idGrupo) {
                    $query->where('idGrupo', $idGrupo);
                });
            }

            if ($idAsesor) {
                $query->whereHas('prestamos.grupo', function ($query) use ($idAsesor) {
                    $query->where('idAsesor', $idAsesor);
                });
            }

            if ($search) {
                $query->whereHas('datos', function ($query) use ($search) {
                    $search = trim($search);
                    $query->where('dni', 'LIKE', "%{$search}%")
                        ->orWhere('nombre', 'LIKE', "%{$search}%")
                        ->orWhere('apellidoPaterno', 'LIKE', "%{$search}%")
                        ->orWhere('apellidoMaterno', 'LIKE', "%{$search}%");
                });
            }

            $clientes = $query->get()->map(function ($cliente) use ($idAsesor) {
                $prestamos = $cliente->prestamos;
                if ($idAsesor) {
                    $prestamos = $prestamos->where('idAsesor', $idAsesor);
                }

                return [
                    'idUsuario' => $cliente->idUsuario,
                    'nombre' => $cliente->datos->nombre,
                    'apellidoPaterno' => $cliente->datos->apellidoPaterno,
                    'apellidoMaterno' => $cliente->datos->apellidoMaterno,
                    'dni' => $cliente->datos->dni,
                    'prestamoCount' => $prestamos->count()
                ];
            });

            if ($withLoans) {
                $clientes = $clientes->filter(function ($cliente) {
                    return $cliente['prestamoCount'] > 0;
                })->values();
            }

            if ($clientes->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'No se encontraron clientes para los filtros seleccionados'
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $clientes
            ]);
        } catch (\Exception $e) {
            Log::error('Error en FiltrarPrestamosController@getClientes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error al cargar los clientes'
            ], 500);
        }
    }

    // Fetch loans with filters
    public function getLoans(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'client_id' => 'nullable|exists:usuarios,idUsuario',
                'idAsesor' => 'nullable|exists:usuarios,idUsuario',
                'dni' => 'nullable|string|max:20',
                'nombre' => 'nullable|string|max:255',
                'apellidos' => 'nullable|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => $validator->errors()->first()
                ], 422);
            }

            $clientId = $request->input('client_id');
            $idAsesor = $request->input('idAsesor');
            $dni = $request->input('dni');
            $nombre = $request->input('nombre');
            $apellidos = $request->input('apellidos');

            $query = Prestamo::with(['cliente.datos', 'asesor.datos', 'estados', 'producto']);

            if ($clientId) {
                $query->where('idCliente', $clientId);
            }

            if ($idAsesor) {
                $query->where('idAsesor', $idAsesor);
            }

            if ($dni) {
                $query->whereHas('cliente.datos', function ($query) use ($dni) {
                    $query->where('dni', $dni);
                });
            }

            if ($nombre || $apellidos) {
                $query->whereHas('cliente.datos', function ($query) use ($nombre, $apellidos) {
                    if ($nombre) {
                        $nombre = trim($nombre);
                        $query->where('nombre', 'LIKE', "%{$nombre}%");
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

            $prestamos = $query->paginate(4)->through(function ($prestamo) {
                $estadoPrestamo = $prestamo->estados ? $prestamo->estados->sortByDesc('fecha_actualizacion')->first() : null;
                $estado = $estadoPrestamo ? $estadoPrestamo->estado : 'vigente';

                return [
                    'idPrestamo' => $prestamo->idPrestamo,
                    'abonado_por' => $prestamo->abonado_por ?? 'N/A',
                    'producto' => ($prestamo->producto && $prestamo->producto->nombre && $prestamo->producto->rango_tasa)
                        ? $prestamo->producto->nombre . ' ' . $prestamo->producto->rango_tasa
                        : '-',
                    'monto' => $prestamo->monto ?? 0,
                    'total' => $prestamo->total ?? 0,
                    'cuotas' => $prestamo->cuotas ?? 0,
                    'valor_cuota' => $prestamo->valor_cuota ?? 0,
                    'frecuencia' => $prestamo->frecuencia ?? 'N/A',
                    'fecha_inicio' => $prestamo->fecha_inicio ?? '-',
                    'estado' => $estado,
                    'cliente' => ($prestamo->cliente && $prestamo->cliente->datos)
                        ? "{$prestamo->cliente->datos->nombre} {$prestamo->cliente->datos->apellidoPaterno} {$prestamo->cliente->datos->apellidoMaterno}"
                        : 'Sin cliente',
                    'asesor' => ($prestamo->asesor && $prestamo->asesor->datos)
                        ? "{$prestamo->asesor->datos->nombre} {$prestamo->asesor->datos->apellidoPaterno} {$prestamo->asesor->datos->apellidoMaterno}"
                        : 'Sin asesor'
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $prestamos->items(),
                'current_page' => $prestamos->currentPage(),
                'last_page' => $prestamos->lastPage(),
                'total' => $prestamos->total()
            ]);
        } catch (\Exception $e) {
            Log::error('Error en FiltrarPrestamosController@getPrestamos: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error al cargar los préstamos'
            ], 500);
        }
    }

    // Fetch installments by loan ID
    public function getInstallments(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'loan_id' => 'required|exists:prestamos,idPrestamo',
                'estado' => 'nullable|in:pendiente,pagado,vence_hoy,vencido,prepagado'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => $validator->errors()->first()
                ], 422);
            }

            $loanId = $request->input('loan_id');
            $estado = $request->input('estado');

            $query = Cuota::with(['prestamo.cliente.datos', 'prestamo.asesor.datos'])
                ->where('idPrestamo', $loanId);

            if ($estado) {
                $query->where('estado', $estado);
            }

            $cuotas = $query->get()->map(function ($cuota) {
                $otros = $cuota->monto - $cuota->capital - $cuota->interes - ($cuota->mora ?? 0);
                return [
                    'idCuota' => $cuota->idCuota,
                    'numero_cuota' => $cuota->numero_cuota,
                    'monto' => $cuota->monto,
                    'capital' => $cuota->capital,
                    'interes' => $cuota->interes,
                    'otros' => $otros >= 0 ? $otros : 0,
                    'fecha_vencimiento' => $cuota->fecha_vencimiento,
                    'estado' => $cuota->estado,
                    'dias_mora' => $cuota->dias_mora ?? 0,
                    'mora' => $cuota->mora ?? 0,
                    'mora_reducida' => $cuota->mora_reducida ?? 0,
                    'reduccion_mora_aplicada' => $cuota->reduccion_mora_aplicada ?? 0,
                    'observaciones' => $cuota->observaciones ?? '-',
                    'cliente_nombre' => $cuota->prestamo->cliente && $cuota->prestamo->cliente->datos
                        ? "{$cuota->prestamo->cliente->datos->nombre} {$cuota->prestamo->cliente->datos->apellidoPaterno} {$cuota->prestamo->cliente->datos->apellidoMaterno}"
                        : 'Sin cliente',
                    'cliente_dni' => $cuota->prestamo->cliente && $cuota->prestamo->cliente->datos
                        ? $cuota->prestamo->cliente->datos->dni
                        : 'Sin DNI',
                    'asesor_nombre' => $cuota->prestamo->asesor && $cuota->prestamo->asesor->datos
                        ? "{$cuota->prestamo->asesor->datos->nombre} {$cuota->prestamo->asesor->datos->apellidoPaterno} {$cuota->prestamo->asesor->datos->apellidoMaterno}"
                        : 'Sin asesor'
                ];
            });

            if ($cuotas->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'No se encontraron cuotas para el préstamo seleccionado'
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $cuotas
            ]);
        } catch (\Exception $e) {
            Log::error('Error en FiltrarPrestamosController@getCuotas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error al filtrar las cuotas'
            ], 500);
        }
    }
}