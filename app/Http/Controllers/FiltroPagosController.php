<?php

namespace App\Http\Controllers;

use App\Models\Cuota;
use App\Models\Datos;
use App\Models\Grupo;
use App\Models\Prestamo;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FiltroPagosController extends Controller
{
   
    public function filtrarPagos(Request $request)
    {
        try {
            // Validación de los parámetros de entrada
            $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'estado' => 'nullable|in:pendiente,pagado,vence_hoy,vencido,prepagado',
                'busquedaCliente' => 'nullable|string|max:255',
                'nombreGrupo' => 'nullable|string',
                'idAsesor' => 'nullable|integer|exists:usuarios,idUsuario',
                'abonadoPor' => 'nullable|in:CUENTA CORRIENTE,CAJA CHICA', // Add validation for abonadoPor
            ]);

            // Parseo de fechas
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $endDate = Carbon::parse($request->end_date)->endOfDay();

            // Consulta base
            $query = Cuota::query()
                ->whereBetween('fecha_vencimiento', [$startDate, $endDate])
                ->with(['prestamo.cliente.datos', 'prestamo.asesor.datos']);

            // Filtro por estado
            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }

            // Filtro por búsqueda de cliente
            if ($request->filled('busquedaCliente')) {
                $search = $request->input('busquedaCliente');
                $query->where(function ($q) use ($search) {
                    // Búsqueda por idCliente si el término es numérico
                    if (is_numeric($search)) {
                        $q->whereHas('prestamo.cliente', function ($subQ) use ($search) {
                            $subQ->where('idCliente', (int)$search);
                        });
                    }
                    // Búsqueda por DNI, nombre, apellidos o nombre completo
                    $q->orWhereHas('prestamo.cliente.datos', function ($subQ) use ($search) {
                        $subQ->where('dni', $search)
                            ->orWhere('nombre', 'LIKE', '%' . $search . '%')
                            ->orWhere('apellidoPaterno', 'LIKE', '%' . $search . '%')
                            ->orWhere('apellidoMaterno', 'LIKE', '%' . $search . '%')
                            ->orWhereRaw("CONCAT(nombre, ' ', apellidoPaterno, ' ', apellidoMaterno) LIKE ?", ['%' . $search . '%']);
                    });
                });
            }

            // Filtro por grupo
            if ($request->filled('nombreGrupo')) {
                $grupo = Grupo::where('nombre', $request->nombreGrupo)->first();
                if (!$grupo) {
                    return response()->json(['message' => 'Grupo no encontrado'], 404);
                }
                $query->whereHas('prestamo', function ($q) use ($grupo) {
                    $q->where('idGrupo', $grupo->idGrupo);
                });
            }

            // Filtro por asesor
            if ($request->filled('idAsesor')) {
                $query->whereHas('prestamo', function ($q) use ($request) {
                    $q->where('idAsesor', (int)$request->idAsesor);
                });
            }

            // Filtro por abonado_por
            if ($request->filled('abonadoPor')) {
                $query->whereHas('prestamo', function ($q) use ($request) {
                    $q->where('abonado_por', $request->abonadoPor);
                });
            }

            // Obtener y mapear resultados
            $cuotas = $query->get()->map(function ($cuota) {
                return [
                    'idCuota' => $cuota->idCuota,
                    'idPrestamo' => $cuota->idPrestamo,
                    'numero_cuota' => $cuota->numero_cuota,
                    'monto' => (float)$cuota->monto,
                    'capital' => (float)$cuota->capital,
                    'interes' => (float)$cuota->interes,
                    'otros' => isset($cuota->otros) ? (float)$cuota->otros : null,
                    'fecha_vencimiento' => Carbon::parse($cuota->fecha_vencimiento)->toIso8601String(),
                    'estado' => $cuota->estado,
                    'dias_mora' => $cuota->dias_mora ?? 0,
                    'observaciones' => $cuota->observaciones ?? '',
                    'cliente_nombre' => $cuota->prestamo->cliente->datos
                        ? trim($cuota->prestamo->cliente->datos->nombre . ' ' .
                            $cuota->prestamo->cliente->datos->apellidoPaterno . ' ' .
                            $cuota->prestamo->cliente->datos->apellidoMaterno . ' ' .
                            ($cuota->prestamo->cliente->datos->apellidoConyuge ?? ''))
                        : 'Sin cliente',
                    'cliente_dni' => $cuota->prestamo->cliente->datos
                        ? $cuota->prestamo->cliente->datos->dni
                        : null,
                    'asesor_nombre' => $cuota->prestamo->asesor && $cuota->prestamo->asesor->datos
                        ? trim($cuota->prestamo->asesor->datos->nombre . ' ' .
                            $cuota->prestamo->asesor->datos->apellidoPaterno . ' ' .
                            $cuota->prestamo->asesor->datos->apellidoMaterno . ' ' .
                            ($cuota->prestamo->asesor->datos->apellidoConyuge ?? ''))
                        : 'Sin asesor',
                    'mora' => $cuota->cargo_mora ?? 0,
                    'mora_reducida' => $cuota->mora_reducida ?? 0,
                    'reduccion_mora_aplicada' => $cuota->reduccion_mora_aplicada ?? false,
                    'abonado_por' => $cuota->prestamo->abonado_por ?? 'N/A'
                ];
            });

            return response()->json([
                'message' => $cuotas->isEmpty() ? 'No se encontraron pagos' : 'Pagos filtrados exitosamente',
                'cuotas' => $cuotas->toArray(),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Error de validación', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error("Error al filtrar pagos: " . $e->getMessage());
            return response()->json(['message' => 'Error al filtrar pagos: ' . $e->getMessage()], 500);
        }
    }

    public function getAsesores()
    {
        try {
            $asesores = User::where('idRol', 3)
                ->with('datos')
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->idUsuario,
                        'nombre' => $user->datos
                            ? trim($user->datos->nombre . ' ' . $user->datos->apellidoPaterno . ' ' . $user->datos->apellidoMaterno)
                            : 'Sin nombre',
                    ];
                });

            return response()->json([
                'message' => 'Asesores obtenidos exitosamente',
                'asesores' => $asesores->toArray(),
            ]);
        } catch (\Exception $e) {
            Log::error("Error al obtener asesores: " . $e->getMessage());
            return response()->json(['message' => 'Error al obtener asesores: ' . $e->getMessage()], 500);
        }
    }
}
?>
