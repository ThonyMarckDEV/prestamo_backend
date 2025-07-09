<?php

namespace App\Http\Controllers;

use App\Models\Grupo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GrupoController extends Controller
{
    /**
     * Listar grupos con paginación y búsqueda
     */
    public function index(Request $request)
    {
        try {
            $search = $request->query('search', '');
            $perPage = $request->query('per_page', 5);
            $page = $request->query('page', 1);

            $query = Grupo::with('asesor.datos')
                ->where('estado', 'activo');

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'LIKE', "%{$search}%")
                      ->orWhere('descripcion', 'LIKE', "%{$search}%")
                      ->orWhereHas('asesor.datos', function ($q) use ($search) {
                          $q->where('nombre', 'LIKE', "%{$search}%")
                            ->orWhere('apellidoPaterno', 'LIKE', "%{$search}%")
                            ->orWhere('apellidoMaterno', 'LIKE', "%{$search}%")
                            ->orWhere('dni', 'LIKE', "%{$search}%");
                      });
                });
            }

            $grupos = $query->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'grupos' => $grupos->items(),
                'current_page' => $grupos->currentPage(),
                'total_pages' => $grupos->lastPage(),
                'total_items' => $grupos->total(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error en GrupoController@index: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al listar grupos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener un grupo específico
     */
    public function show($id)
    {
        try {
            $grupo = Grupo::with('asesor.datos')->findOrFail($id);
            return response()->json([
                'success' => true,
                'grupo' => $grupo
            ]);
        } catch (\Exception $e) {
            Log::error('Error en GrupoController@show: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el grupo',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Crear un nuevo grupo
     */
    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'nombre' => 'required|string|max:255',
                'idAsesor' => 'required|exists:usuarios,idUsuario',
                'fecha_creacion' => 'required|date',
                'descripcion' => 'nullable|string',
            ]);

            $grupo = Grupo::create([
                'nombre' => $validatedData['nombre'],
                'idAsesor' => $validatedData['idAsesor'],
                'fecha_creacion' => $validatedData['fecha_creacion'],
                'descripcion' => $validatedData['descripcion'] ?? null,
                'estado' => 'activo'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Grupo creado correctamente',
                'idGrupo' => $grupo->idGrupo
            ]);
        } catch (\Exception $e) {
            Log::error('Error en GrupoController@store: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el grupo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un grupo existente
     */
    public function update(Request $request, $id)
    {
        try {
            $validatedData = $request->validate([
                'nombre' => 'required|string|max:255',
                'idAsesor' => 'required|exists:usuarios,idUsuario',
                'fecha_creacion' => 'required|date',
                'descripcion' => 'nullable|string',
                'estado' => 'required|in:activo,inactivo'
            ]);

            $grupo = Grupo::findOrFail($id);
            $grupo->update([
                'nombre' => $validatedData['nombre'],
                'idAsesor' => $validatedData['idAsesor'],
                'fecha_creacion' => $validatedData['fecha_creacion'],
                'descripcion' => $validatedData['descripcion'] ?? null,
                'estado' => $validatedData['estado']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Grupo actualizado correctamente',
                'idGrupo' => $grupo->idGrupo
            ]);
        } catch (\Exception $e) {
            Log::error('Error en GrupoController@update: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el grupo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

   /**
     * Eliminar un grupo
     */
    public function destroy($id)
    {
        try {
            $grupo = Grupo::withCount('prestamos')->findOrFail($id);

            if ($grupo->prestamos_count > 0) {
                throw new \Exception("No se puede eliminar el grupo porque está asociado a {$grupo->prestamos_count} préstamo(s).");
            }

            $grupo->delete();

            return response()->json([
                'success' => true,
                'message' => 'Grupo eliminado correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error en GrupoController@destroy: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el grupo',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
?>