<?php

namespace App\Http\Controllers;

use App\Models\ClienteAval;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClienteAvalesController extends Controller
{
    /**
     * Mostrar todas las asignaciones de avales a clientes
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $clienteAvales = ClienteAval::with(['cliente.datos', 'aval.datos'])->get();
        
        // Formatear los datos para incluir nombres y apellidos
        $formattedAvales = $clienteAvales->map(function($aval) {
            return [
                'id' => $aval->id,
                'idCliente' => $aval->idCliente,
                'idAval' => $aval->idAval,
                'cliente' => [
                    'idUsuario' => $aval->cliente->idUsuario,
                    'nombreCompleto' => $aval->cliente->datos ? $aval->cliente->datos->nombre . ' ' . $aval->cliente->datos->apellidoPaterno 
                     . ' ' . $aval->cliente->datos->apellidoMaterno . ' ' . ($aval->cliente->datos->apellidoConyuge ?? '') : $aval->cliente->username,
                ],
                'aval' => [
                    'idUsuario' => $aval->aval->idUsuario,
                    'nombreCompleto' => $aval->aval->datos ? $aval->aval->datos->nombre . ' ' . $aval->aval->datos->apellidoPaterno 
                     . ' ' . $aval->aval->datos->apellidoMaterno . ' ' . ($aval->aval->datos->apellidoConyuge ?? '') : $aval->aval->username,
                ],
                'created_at' => $aval->created_at,
                'updated_at' => $aval->updated_at,
            ];
        });
        
        return response()->json($formattedAvales);
    }

    /**
     * Obtener todos los clientes disponibles
     *
     * @return \Illuminate\Http\Response
     */
    public function getClientes()
    {
        $clientes = User::with('datos')
            ->whereHas('datos', function($query) {
                $query->where('aval', false); // Solo usuarios que no son avales
            })
            ->where('estado', 1)
            ->where('idRol', 2)
            ->get();
        
        // Formatear los datos para incluir nombres y apellidos
        $formattedClientes = $clientes->map(function($cliente) {
            return [
                'idUsuario' => $cliente->idUsuario,
                'datos' => $cliente->datos ? [
                    'nombre' => $cliente->datos->nombre,
                    'apellidoPaterno' => $cliente->datos->apellidoPaterno,
                    'apellidoMaterno' => $cliente->datos->apellidoMaterno,
                    'apellidoConyuge' => $cliente->datos->apellidoConyuge,
                    'estadoCivil' => $cliente->datos->estadoCivil,
                    'dni'=> $cliente->datos->dni,
                    'fechaCaducidadDni' => $cliente->datos->fechaCaducidadDni,
                    'nombreCompleto' => $cliente->datos->nombre . ' ' . $cliente->datos->apellidoPaterno . ' ' . $cliente->datos->apellidoMaterno . ' ' . ($cliente->datos->apellidoConyuge ?? ''),
                    'ruc'=> $cliente->datos->ruc
                ] : null
            ];
        });
        
        return response()->json($formattedClientes);
    }

    /**
     * Obtener todos los avales disponibles
     *
     * @return \Illuminate\Http\Response
     */
    public function getAvales()
    {
        $avales = User::with('datos')
            ->whereHas('datos', function($query) {
                $query->where('aval', true); // Solo usuarios que son avales
            })
            ->where('estado', 1)
            ->get();
            
        // Formatear los datos para incluir nombres y apellidos
        $formattedAvales = $avales->map(function($aval) {
            return [
                'idUsuario' => $aval->idUsuario,
                'datos' => $aval->datos ? [
                    'nombre' => $aval->datos->nombre,
                    'apellidoPaterno' => $aval->datos->apellidoPaterno,
                    'apellidoMaterno' => $aval->datos->apellidoMaterno,
                    'apellidoConyuge' => $aval->datos->apellidoConyuge,
                    'estadoCivil' => $aval->datos->estadoCivil,
                    'dni'=> $aval->datos->dni,
                    'fechaCaducidadDni' => $aval->datos->fechaCaducidadDni,
                    'apellido' => $aval->datos->apellido,
                    'nombreCompleto' => $aval->datos->nombre . ' ' . $aval->datos->apellidoPaterno . ' ' . $aval->datos->apellidoMaterno . ' ' . ($aval->datos->apellidoConyuge ?? ''),
                    'ruc'=> $aval->datos->ruc
                ] : null
            ];
        });
        
        return response()->json($formattedAvales);
    }

    /**
     * Crear una nueva asignación de aval a cliente
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'idCliente' => 'required|exists:usuarios,idUsuario',
            'idAval' => 'required|exists:usuarios,idUsuario',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }
        
        // Verificar si el cliente ya tiene un aval asignado
        $clienteConAval = ClienteAval::where('idCliente', $request->idCliente)->first();
        if ($clienteConAval) {
            return response()->json([
                'message' => 'Este cliente ya tiene un aval asignado',
            ], 422);
        }
        
        // Verificar si el aval es un usuario con propiedad aval=true
        $aval = User::with('datos')->find($request->idAval);
        if (!$aval || !$aval->datos || !$aval->datos->aval) {
            return response()->json([
                'message' => 'El usuario seleccionado no es un aval válido',
            ], 422);
        }
        
        // Verificar si el aval ya ha alcanzado el límite de clientes (máximo 2)
        $clientesDelAval = ClienteAval::where('idAval', $request->idAval)->count();
        if ($clientesDelAval >= 2) {
            return response()->json([
                'message' => 'Este aval ya ha alcanzado el límite máximo de 2 clientes',
            ], 422);
        }
        
        // Crear la asignación
        $clienteAval = ClienteAval::create([
            'idCliente' => $request->idCliente,
            'idAval' => $request->idAval,
        ]);
        
        return response()->json([
            'message' => 'Asignación creada correctamente',
            'clienteAval' => $clienteAval
        ], 201);
    }

    /**
     * Mostrar una asignación específica
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $clienteAval = ClienteAval::with(['cliente.datos', 'aval.datos'])->find($id);
        
        if (!$clienteAval) {
            return response()->json(['message' => 'Asignación no encontrada'], 404);
        }
        
        $formattedAval = [
            'id' => $clienteAval->id,
            'idCliente' => $clienteAval->idCliente,
            'idAval' => $clienteAval->idAval,
            'cliente' => [
                'idUsuario' => $clienteAval->cliente->idUsuario,
                'nombreCompleto' => $clienteAval->cliente->datos ? $clienteAval->cliente->datos->nombre . ' ' . $clienteAval->cliente->datos->apellidoPaterno
                 . ' ' .  $clienteAval->cliente->datos->apellidoMaterno . ' ' . ($clienteAval->cliente->datos->apellidoConyuge ?? '') : $clienteAval->cliente->username,
            ],
            'aval' => [
                'idUsuario' => $clienteAval->aval->idUsuario,
                'nombreCompleto' => $clienteAval->aval->datos ? $clienteAval->aval->datos->nombre . ' ' . $clienteAval->aval->datos->apellidoPaterno 
                 . ' ' . $clienteAval->aval->datos->apellidoMaterno . ' ' . ($clienteAval->aval->datos->apellidoConyuge ?? '') : $clienteAval->aval->username,
            ],
            'created_at' => $clienteAval->created_at,
            'updated_at' => $clienteAval->updated_at,
        ];

        return response()->json($formattedAval);
    }

    /**
     * Eliminar una asignación
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $clienteAval = ClienteAval::find($id);
        
        if (!$clienteAval) {
            return response()->json(['message' => 'Asignación no encontrada'], 404);
        }

        $clienteAval->delete();

        return response()->json(['message' => 'Asignación eliminada correctamente']);
    }
}