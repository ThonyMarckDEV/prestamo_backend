<?php

namespace App\Http\Controllers;

use App\Models\Datos;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CalculadoraController extends Controller
{
    /**
     * Obtener todos los clientes activos
     * @param Request $request
     * @return JsonResponse
     */
    public function getClients(Request $request): JsonResponse
    {
        $search = $request->input('search', '');
        
        $query = User::with([
            'datos',
            // 'datos.direcciones',
            'datos.contactos',
            // 'datos.cuentasBancarias',
            // 'datos.actividadesEconomicas'
        ])
            ->where('estado', 1) // Solo clientes activos
            ->where('idRol', 2); // Roles de cliente - Cambio de whereIn a where
        
        if (!empty($search)) {
            $query->whereHas('datos', function($q) use ($search) {
                $words = explode(' ', $search);
                
                foreach ($words as $word) {
                    $q->where(function($subQuery) use ($word) {
                        $subQuery->where('nombre', 'LIKE', "%{$word}%")
                            ->orWhere('apellidoPaterno', 'LIKE', "%{$word}%")
                            ->orWhere('apellidoMaterno', 'LIKE', "%{$word}%")
                            ->orWhere('apellidoConyuge', 'LIKE', "%{$word}%")
                            ->orWhere('dni', 'LIKE', "%{$word}%");
                    });
                }
            });
        }
        
        $clientes = $query->get();
        
        return response()->json([
            'message' => 'Clientes obtenidos con éxito',
            'clientes' => $clientes
        ], 200);
    }

     /**
     * Obtener asesores activos basado en el término de búsqueda
     * @param Request $request
     * @return JsonResponse
     */
    public function getAsesores(Request $request): JsonResponse
    {
        $search = $request->input('search', '');
        
        $query = User::with('datos')
            ->where('estado', 1) // Solo asesores activos
            ->where('idRol', 3); // Suponiendo que 3 es el ID del rol de asesor
        
        if (!empty($search)) {
            $query->whereHas('datos', function($q) use ($search) {
                $words = explode(' ', $search);
                
                foreach ($words as $word) {
                    $q->where(function($subQuery) use ($word) {
                        $subQuery->where('nombre', 'LIKE', "%{$word}%")
                            ->orWhere('apellidoPaterno', 'LIKE', "%{$word}%")
                            ->orWhere('apellidoMaterno', 'LIKE', "%{$word}%")
                            ->orWhere('apellidoConyuge', 'LIKE', "%{$word}%")
                            ->orWhere('dni', 'LIKE', "%{$word}%");
                    });
                }
            });
        }
        
        $asesores = $query->get();
        
        return response()->json([
            'message' => 'Asesores obtenidos con éxito',
            'asesores' => $asesores
        ], 200);
    }
    
  
}