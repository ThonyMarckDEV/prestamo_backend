<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductosController extends Controller
{
    public function index(Request $request)
    {
        try {
            $search = $request->query('search', '');
            $perPage = $request->query('per_page', 5);

            $query = Producto::query();
            if ($search) {
                $query->where('idProducto', $search)
                      ->orWhere('nombre', 'LIKE', '%' . $search . '%');
            }

            $productos = $query->orderBy('idProducto', 'desc')->paginate($perPage);

            return response()->json([
                'message' => $productos->isEmpty() ? 'No se encontraron productos' : 'Productos obtenidos exitosamente',
                'productos' => $productos->items(),
                'current_page' => $productos->currentPage(),
                'total_pages' => $productos->lastPage(),
                'total_items' => $productos->total(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error al listar productos: ' . $e->getMessage());
            return response()->json(['message' => 'Error al listar productos'], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'nombre' => 'required|string|max:100|unique:productos',
                'rango_tasa' => 'required|string|max:50',
            ]);

            $producto = Producto::create($request->only(['nombre', 'rango_tasa']));

            return response()->json([
                'message' => 'Producto creado exitosamente',
                'producto' => $producto,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Error de validación', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Error al crear producto: ' . $e->getMessage());
            return response()->json(['message' => 'Error al crear producto'], 500);
        }
    }

    public function show($id)
    {
        try {
            $producto = Producto::findOrFail($id);
            return response()->json([
                'message' => 'Producto obtenido exitosamente',
                'producto' => $producto,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener producto: ' . $e->getMessage());
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'nombre' => 'required|string|max:100|unique:productos,nombre,' . $id,
                'rango_tasa' => 'required|string|max:50',
            ]);

            $producto = Producto::findOrFail($id);
            $producto->update($request->only(['nombre', 'rango_tasa']));

            return response()->json([
                'message' => 'Producto actualizado exitosamente',
                'producto' => $producto,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Error de validación', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Error al actualizar producto: ' . $e->getMessage());
            return response()->json(['message' => 'Error al actualizar producto'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $producto = Producto::findOrFail($id);
            $producto->delete();

            return response()->json(['message' => 'Producto eliminado exitosamente']);
        } catch (\Exception $e) {
            Log::error('Error al eliminar producto: ' . $e->getMessage());
            return response()->json(['message' => 'Error al eliminar producto'], 500);
        }
    }
}
?>