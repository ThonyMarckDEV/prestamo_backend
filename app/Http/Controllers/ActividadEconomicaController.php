<?php

namespace App\Http\Controllers;

use App\Models\Ciiu;
use App\Models\NoSensible;
use Illuminate\Http\Request;

class ActividadEconomicaController extends Controller
{
    /**
     * Listar actividades CIIU con filtros de búsqueda
     */
    public function listCiiu(Request $request)
    {
        $query = Ciiu::query();
        
        // Aplicar filtros de búsqueda si existen
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('codigo', 'LIKE', "%{$search}%")
                  ->orWhere('descripcion', 'LIKE', "%{$search}%");
            });
        }
        
        // Paginación opcional
        $perPage = $request->get('per_page', 10);
        
        return response()->json([
            'activity_ciiu' => $query->paginate($perPage)
        ]);
    }
    
    /**
     * Listar actividades no sensibles con filtros de búsqueda
     */
    public function listNoSensibles(Request $request)
    {
        $query = NoSensible::query();
        
        // Aplicar filtros de búsqueda si existen
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('sector', 'LIKE', "%{$search}%")
                  ->orWhere('actividad', 'LIKE', "%{$search}%");
            });
        }
        
        // Paginación opcional
        $perPage = $request->get('per_page', 10);
        
        return response()->json([
            'activity_no_sensible' => $query->paginate($perPage)
        ]);
    }
}