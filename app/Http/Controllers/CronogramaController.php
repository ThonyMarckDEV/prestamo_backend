<?php

namespace App\Http\Controllers;

use App\Models\ClienteAval;
use App\Models\Contacto;
use App\Models\CuentaBancaria;
use App\Models\Datos;
use App\Models\Direccion;
use App\Models\Prestamo;
use App\Models\Cuota;
use App\Models\Grupo;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class CronogramaController extends Controller
{
   
    public function search(Request $request)
    {
        try {
            $searchTerm = trim($request->input('searchTerm'));
            $nombreGrupo = trim($request->input('nombreGrupo'));

            if (empty($searchTerm) && empty($nombreGrupo)) {
                return response()->json(['message' => 'El término de búsqueda o nombre del grupo es requerido'], 400);
            }

            $prestamos = collect();

            if ($nombreGrupo) {
                // Group search
                $grupo = Grupo::whereRaw('LOWER(nombre) = ?', [strtolower($nombreGrupo)])->first();
                if (!$grupo) {
                    return response()->json(['message' => 'No se encontró el grupo especificado'], 404);
                }

                $prestamos = Prestamo::where('idGrupo', $grupo->idGrupo)
                    ->where('estado', 'activo')
                    ->with(['grupo', 'cliente.datos'])
                    ->get();
            } elseif ($searchTerm) {
                // Client search
                $datos = Datos::query()
                    ->leftJoin('usuarios', 'datos.idDatos', '=', 'usuarios.idDatos')
                    ->where(function ($query) use ($searchTerm) {
                        $lowerSearch = strtolower($searchTerm);
                        $query->where('datos.dni', $searchTerm)
                            ->orWhere('datos.idDatos', $searchTerm)
                            ->orWhere('usuarios.idUsuario', $searchTerm)
                            ->orWhereRaw('LOWER(datos.nombre) LIKE ?', ["%{$lowerSearch}%"])
                            ->orWhereRaw('LOWER(datos.apellidoPaterno) LIKE ?', ["%{$lowerSearch}%"])
                            ->orWhereRaw('LOWER(datos.apellidoMaterno) LIKE ?', ["%{$lowerSearch}%"])
                            ->orWhereRaw('LOWER(CONCAT(datos.nombre, " ", datos.apellidoPaterno)) LIKE ?', ["%{$lowerSearch}%"])
                            ->orWhereRaw('LOWER(CONCAT(datos.nombre, " ", datos.apellidoMaterno)) LIKE ?', ["%{$lowerSearch}%"])
                            ->orWhereRaw('LOWER(CONCAT(datos.apellidoPaterno, " ", datos.apellidoMaterno)) LIKE ?', ["%{$lowerSearch}%"])
                            ->orWhereRaw('LOWER(CONCAT(datos.nombre, " ", datos.apellidoPaterno, " ", datos.apellidoMaterno)) LIKE ?', ["%{$lowerSearch}%"]);
                    })
                    ->select('datos.*', 'usuarios.idUsuario')
                    ->get();

                if ($datos->isEmpty()) {
                    return response()->json(['message' => 'No se encontraron clientes con los criterios proporcionados'], 404);
                }

                $clientIds = $datos->pluck('idUsuario')->filter()->unique();

                $prestamos = Prestamo::whereIn('idCliente', $clientIds)
                    ->where('estado', 'activo')
                    ->with(['grupo', 'cliente.datos'])
                    ->get();
            }

            if ($prestamos->isEmpty()) {
                return response()->json([
                    'message' => $nombreGrupo ? 'No se encontraron préstamos activos para este grupo' : 'No se encontraron préstamos activos para los clientes encontrados',
                    'prestamos' => [],
                ], 200);
            }

            // Map results
            $responseData = $prestamos->map(function ($prestamo) {
                $clienteDatos = $prestamo->cliente->datos;
                return [
                    'idPrestamo' => $prestamo->idPrestamo,
                    'monto' => $prestamo->monto,
                    'frecuencia' => $prestamo->frecuencia,
                    'cuotas' => $prestamo->cuotas,
                    'fecha_inicio' => $prestamo->fecha_inicio,
                    'cliente' => $clienteDatos ? trim("{$clienteDatos->nombre} {$clienteDatos->apellidoPaterno} {$clienteDatos->apellidoMaterno}") : null,
                    'dni' => $clienteDatos ? $clienteDatos->dni : null,
                    'grupo' => $prestamo->grupo ? $prestamo->grupo->nombre : null,
                ];
            });

            return response()->json([
                'prestamos' => $responseData->toArray(),
            ], 200);
        } catch (\Exception $e) {
            Log::error("Error al buscar préstamos: " . $e->getMessage());
            return response()->json(['message' => 'Error al buscar préstamos'], 500);
        }
    }

   public function searchByGroup(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'idGrupo' => 'required|exists:grupos,idGrupo',
            ]);

            $prestamos = Prestamo::with(['cliente.datos'])
                ->where('idGrupo', $validatedData['idGrupo'])
                ->where('estado', 'activo')
                ->get()
                ->map(function ($prestamo) {
                    return [
                        'idPrestamo' => $prestamo->idPrestamo,
                        'monto' => $prestamo->monto,
                        'frecuencia' => $prestamo->frecuencia,
                        'cuotas' => $prestamo->cuotas,
                        'fecha_inicio' => $prestamo->fecha_inicio,
                        'cliente' => $prestamo->cliente->datos ? 
                            "{$prestamo->cliente->datos->nombre} {$prestamo->cliente->datos->apellidoPaterno} {$prestamo->cliente->datos->apellidoMaterno}" : 
                            'Sin cliente',
                        'dni' => $prestamo->cliente->datos->dni ?? 'Sin DNI',
                    ];
                });

            if ($prestamos->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No se encontraron clientes con los criterios proporcionados',
                    'prestamos' => []
                ]);
            }

            return response()->json([
                'success' => true,
                'prestamos' => $prestamos
            ]);
        } catch (\Exception $e) {
            Log::error('Error en CronogramaController@searchByGroup: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Get all groups for dropdown
    public function getGroups()
    {
        try {
            $grupos = Grupo::where('estado', 'activo')->get(['idGrupo', 'nombre']);
            return response()->json($grupos->toArray());
        } catch (\Exception $e) {
            Log::error("Error al obtener grupos: " . $e->getMessage());
            return response()->json(['message' => 'Error al obtener grupos'], 500);
        }
    }
}
