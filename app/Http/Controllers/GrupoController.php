<?php

namespace App\Http\Controllers;

use App\Models\Grupo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GrupoController extends Controller
{
    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'nombre' => 'required|string|max:255',
                'idAsesor' => 'required|exists:usuarios,idUsuario',
                'fecha_creacion' => 'required|date',
                'descripcion' => 'nullable|string'
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
}