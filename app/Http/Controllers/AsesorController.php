<?php

namespace App\Http\Controllers;

use App\Models\Datos;
use App\Models\User;
use App\Models\Direccion;
use App\Models\Contacto;
use App\Models\CuentaBancaria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class AsesorController extends Controller
{

    /**
     * Obtener todos los asesores y usuarios con rol 4.
     */
    public function getAsesores(Request $request)
    {
        $asesores = User::whereIn('idRol', [3, 4])
            ->with([
                'datos' => function ($query) {
                    $query->with(['direcciones', 'contactos', 'cuentasBancarias']);
                },
                'rol'
            ])
            ->get();

        return response()->json([
            'message' => 'Asesores y usuarios con rol 4 obtenidos con éxito',
            'asesores' => $asesores
        ], 200);
    }

   /**
     * Registrar un nuevo empleado.
     */
    public function createAsesor(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // User access data
            'username' => 'required|string|unique:usuarios,username',
            'password' => 'required|string|min:5|confirmed',
            'idRol' => 'required|exists:roles,idRol',

            // Personal data
            'datos.nombre' => 'required|string|max:100',
            'datos.apellidoPaterno' => 'required|string|max:50',
            'datos.apellidoMaterno' => 'required|string|max:50',
            'datos.apellidoConyuge' => 'nullable|string|max:50',
            'datos.estadoCivil' => 'required|string|max:50',
            'datos.dni' => 'required|string|max:9|unique:datos,dni',
            'datos.fechaCaducidadDni' => 'required|date',
            'datos.ruc' => 'nullable|string|size:11|unique:datos,ruc',

            // Contactos
            'contactos' => 'required|array|min:1',
            'contactos.*.tipo' => 'required|string|in:PRINCIPAL,SECUNDARIO',
            'contactos.*.telefono' => 'required|string|max:9|unique:contactos,telefono',
            'contactos.*.telefonoDos' => 'nullable|string|max:9|unique:contactos,telefonoDos',
            'contactos.*.email' => 'required|email|unique:contactos,email',

            // Direcciones
            'direcciones' => 'required|array|min:1',
            'direcciones.*.tipo' => 'nullable|string|in:FISCAL,CORRESPONDENCIA',
            'direcciones.*.tipoVia' => 'nullable|string|max:100',
            'direcciones.*.nombreVia' => 'nullable|string|max:100',
            'direcciones.*.numeroMz' => 'nullable|string|max:50',
            'direcciones.*.urbanizacion' => 'required|string|max:100',
            'direcciones.*.departamento' => 'required|string|max:50',
            'direcciones.*.provincia' => 'required|string|max:50',
            'direcciones.*.distrito' => 'required|string|max:50',

            // Cuenta bancaria (financiera)
            'financiera.numeroCuenta' => 'required|string|max:20|unique:cuentas_bancarias,numeroCuenta',
            'financiera.cci' => 'nullable|string|size:20|unique:cuentas_bancarias,cci',
            'financiera.entidadFinanciera' => 'required|string|max:50',
        ], [
            'username.required' => 'El nombre de usuario es obligatorio.',
            'username.string' => 'El nombre de usuario debe ser una cadena de texto.',
            'username.unique' => 'El nombre de usuario ya está en uso.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.string' => 'La contraseña debe ser una cadena de texto.',
            'password.min' => 'La contraseña debe tener al menos 5 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'idRol.required' => 'El rol es obligatorio.',
            'idRol.exists' => 'El rol seleccionado no existe.',

            'datos.nombre.required' => 'El nombre es obligatorio.',
            'datos.nombre.max' => 'El nombre no puede exceder los 100 caracteres.',
            'datos.apellidoPaterno.required' => 'El apellido paterno es obligatorio.',
            'datos.apellidoPaterno.max' => 'El apellido paterno no puede exceder los 50 caracteres.',
            'datos.apellidoMaterno.required' => 'El apellido materno es obligatorio.',
            'datos.apellidoMaterno.max' => 'El apellido materno no puede exceder los 50 caracteres.',
            'datos.apellidoConyuge.max' => 'El apellido de cónyuge no puede exceder los 50 caracteres.',
            'datos.estadoCivil.required' => 'El estado civil es obligatorio.',
            'datos.estadoCivil.max' => 'El estado civil no puede exceder los 50 caracteres.',
            'datos.dni.required' => 'El DNI es obligatorio.',
            'datos.dni.max' => 'El DNI no puede exceder los 9 caracteres.',
            'datos.dni.unique' => 'El DNI ya está registrado.',
            'datos.fechaCaducidadDni.required' => 'La fecha de caducidad del DNI es obligatoria.',
            'datos.fechaCaducidadDni.date' => 'La fecha de caducidad del DNI debe ser una fecha válida.',
            'datos.ruc.size' => 'El RUC debe tener exactamente 11 caracteres.',
            'datos.ruc.unique' => 'El RUC ya está registrado.',

            'contactos.required' => 'Debe proporcionar al menos un contacto.',
            'contactos.min' => 'Debe proporcionar al menos un contacto.',
            'contactos.*.tipo.required' => 'El tipo de contacto es obligatorio.',
            'contactos.*.tipo.in' => 'El tipo de contacto debe ser PRINCIPAL o SECUNDARIO.',
            'contactos.*.telefono.required' => 'El teléfono del contacto es obligatorio.',
            'contactos.*.telefono.max' => 'El teléfono del contacto no puede exceder los 9 caracteres.',
            'contactos.*.telefono.unique' => 'El número de teléfono del contacto ya está registrado.',
            'contactos.*.telefonoDos.max' => 'El segundo teléfono del contacto no puede exceder los 9 caracteres.',
            'contactos.*.telefonoDos.unique' => 'El segundo número de teléfono del contacto ya está registrado.',
            'contactos.*.email.required' => 'El correo electrónico del contacto es obligatorio.',
            'contactos.*.email.email' => 'El correo electrónico del contacto debe ser válido.',
            'contactos.*.email.unique' => 'El correo electrónico del contacto ya está registrado.',

            'direcciones.required' => 'Debe proporcionar al menos una dirección.',
            'direcciones.min' => 'Debe proporcionar al menos una dirección.',
            'direcciones.*.tipo.in' => 'El tipo de dirección debe ser FISCAL o CORRESPONDENCIA.',
            'direcciones.*.tipoVia.max' => 'El tipo de vía no puede exceder los 100 caracteres.',
            'direcciones.*.nombreVia.max' => 'El nombre de la vía no puede exceder los 100 caracteres.',
            'direcciones.*.numeroMz.max' => 'El número o manzana no puede exceder los 50 caracteres.',
            'direcciones.*.urbanizacion.required' => 'La urbanización es obligatoria.',
            'direcciones.*.urbanizacion.max' => 'La urbanización no puede exceder los 100 caracteres.',
            'direcciones.*.departamento.required' => 'El departamento es obligatorio.',
            'direcciones.*.departamento.max' => 'El departamento no puede exceder los 50 caracteres.',
            'direcciones.*.provincia.required' => 'La provincia es obligatoria.',
            'direcciones.*.provincia.max' => 'La provincia no puede exceder los 50 caracteres.',
            'direcciones.*.distrito.required' => 'El distrito es obligatorio.',
            'direcciones.*.distrito.max' => 'El distrito no puede exceder los 50 caracteres.',

            'financiera.numeroCuenta.required' => 'El número de cuenta es obligatorio.',
            'financiera.numeroCuenta.max' => 'El número de cuenta no puede exceder los 20 caracteres.',
            'financiera.numeroCuenta.unique' => 'El número de cuenta ya está registrado.',
            'financiera.cci.size' => 'El CCI debe tener exactamente 20 caracteres.',
            'financiera.cci.unique' => 'El CCI ya está registrado.',
            'financiera.entidadFinanciera.required' => 'La entidad financiera es obligatoria.',
            'financiera.entidadFinanciera.max' => 'La entidad financiera no puede exceder los 50 caracteres.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Crear datos personales
            $datos = Datos::create([
                'nombre' => data_get($request, 'datos.nombre'),
                'apellidoPaterno' => data_get($request, 'datos.apellidoPaterno'),
                'apellidoMaterno' => data_get($request, 'datos.apellidoMaterno'),
                'apellidoConyuge' => data_get($request, 'datos.apellidoConyuge'),
                'estadoCivil' => data_get($request, 'datos.estadoCivil'),
                'dni' => data_get($request, 'datos.dni'),
                'fechaCaducidadDni' => data_get($request, 'datos.fechaCaducidadDni'),
                'ruc' => data_get($request, 'datos.ruc'),
            ]);

            // Crear direcciones
            foreach ($request->direcciones as $direccion) {
                Direccion::create([
                    'idDatos' => $datos->idDatos,
                    'tipo' => data_get($direccion, 'tipo'),
                    'tipoVia' => data_get($direccion, 'tipoVia'),
                    'nombreVia' => data_get($direccion, 'nombreVia'),
                    'numeroMz' => data_get($direccion, 'numeroMz'),
                    'urbanizacion' => data_get($direccion, 'urbanizacion'),
                    'departamento' => data_get($direccion, 'departamento'),
                    'provincia' => data_get($direccion, 'provincia'),
                    'distrito' => data_get($direccion, 'distrito')
                ]);
            }

            // Crear contactos
            foreach ($request->contactos as $contacto) {
                Contacto::create([
                    'idDatos' => $datos->idDatos,
                    'tipo' => data_get($contacto, 'tipo'),
                    'telefono' => data_get($contacto, 'telefono'),
                    'telefonoDos' => data_get($contacto, 'telefonoDos'),
                    'email' => data_get($contacto, 'email')
                ]);
            }

            // Crear cuenta bancaria
            CuentaBancaria::create([
                'idDatos' => $datos->idDatos,
                'numeroCuenta' => data_get($request, 'financiera.numeroCuenta'),
                'cci' => data_get($request, 'financiera.cci'),
                'entidadFinanciera' => data_get($request, 'financiera.entidadFinanciera')
            ]);

            // Crear usuario
            $usuario = User::create([
                'idUsuario' => (string) \Str::uuid(),
                'username' => $request->username,
                'password' => Hash::make($request->password),
                'idRol' => $request->idRol,
                'idDatos' => $datos->idDatos,
                'estado' => 1
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Empleado creado con éxito',
                'asesor' => $usuario->load([
                    'datos',
                    'datos.direcciones',
                    'datos.contactos',
                    'datos.cuentasBancarias'
                ])
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error al crear asesor', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $request->all()
            ]);
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear el asesor: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor.'
            ], 500);
        }
    }

    /**
     * Mostrar los detalles de un empleado específico.
     */
    public function show($idUsuario)
    {
        $asesor = User::with([
            'datos' => function ($query) {
                $query->with(['direcciones', 'contactos', 'cuentasBancarias']);
            },
            'rol'
        ])->find($idUsuario);

        if (!$asesor) {
            return response()->json(['message' => 'Empleado no encontrado'], 404);
        }

        return response()->json($asesor, 200);
    }
    public function updateAsesor(Request $request, $id)
    {
        // Obtenemos el usuario primero para poder excluirlo en la validación
        $usuario = User::findOrFail($id);
        $idDatos = $usuario->idDatos;

        // Definir reglas de validación
        $rules = [
            'username' => 'required|string|unique:usuarios,username,' . $id . ',idUsuario',
            'password' => 'nullable|string|min:5|confirmed',
            'datos.nombre' => 'required|string|max:100',
            'datos.apellidoPaterno' => 'required|string|max:50',
            'datos.apellidoMaterno' => 'required|string|max:50',
            'datos.apellidoConyuge' => 'nullable|string|max:50',
            'datos.estadoCivil' => 'required|string|max:50',
            'datos.dni' => 'required|string|max:9|unique:datos,dni,' . $idDatos . ',idDatos',
            'datos.fechaCaducidadDni' => 'required|date',
            'datos.ruc' => 'nullable|string|size:11|unique:datos,ruc,' . $idDatos . ',idDatos',
            'contactos' => 'required|array|min:1',
            'contactos.*.tipo' => 'required|string|in:PRINCIPAL,SECUNDARIO',
            'contactos.*.telefono' => 'required|string|max:9|unique:contactos,telefono,' . $idDatos . ',idDatos',
            'contactos.*.telefonoDos' => 'nullable|string|max:9',
            'contactos.*.email' => 'required|email|unique:contactos,email,' . $idDatos . ',idDatos',
            'direcciones' => 'required|array|min:1',
            'direcciones.*.tipo' => 'nullable|string|in:FISCAL,CORRESPONDENCIA',
            'direcciones.*.tipoVia' => 'nullable|string|max:100',
            'direcciones.*.nombreVia' => 'nullable|string|max:100',
            'direcciones.*.numeroMz' => 'nullable|string|max:50',
            'direcciones.*.urbanizacion' => 'required|string|max:100',
            'direcciones.*.departamento' => 'required|string|max:50',
            'direcciones.*.provincia' => 'required|string|max:50',
            'direcciones.*.distrito' => 'required|string|max:50',
            'financiera.numeroCuenta' => 'required|string|max:20|unique:cuentas_bancarias,numeroCuenta,' . $idDatos . ',idDatos',
            'financiera.cci' => 'nullable|string|size:20|unique:cuentas_bancarias,cci,' . $idDatos . ',idDatos',
            'financiera.entidadFinanciera' => 'required|string|max:50',
        ];

        // Mensajes de error personalizados
        $messages = [
            'username.required' => 'El nombre de usuario es obligatorio.',
            'username.string' => 'El nombre de usuario debe ser una cadena de texto.',
            'username.unique' => 'El nombre de usuario ya está en uso.',
            'password.min' => 'La contraseña debe tener al menos 5 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'datos.nombre.required' => 'El nombre es obligatorio.',
            'datos.nombre.max' => 'El nombre no puede exceder los 100 caracteres.',
            'datos.apellidoPaterno.required' => 'El apellido paterno es obligatorio.',
            'datos.apellidoPaterno.max' => 'El apellido paterno no puede exceder los 50 caracteres.',
            'datos.apellidoMaterno.required' => 'El apellido materno es obligatorio.',
            'datos.apellidoMaterno.max' => 'El apellido materno no puede exceder los 50 caracteres.',
            'datos.apellidoConyuge.max' => 'El apellido de cónyuge no puede exceder los 50 caracteres.',
            'datos.estadoCivil.required' => 'El estado civil es obligatorio.',
            'datos.estadoCivil.max' => 'El estado civil no puede exceder los 50 caracteres.',
            'datos.dni.required' => 'El DNI es obligatorio.',
            'datos.dni.max' => 'El DNI no puede exceder los 9 caracteres.',
            'datos.dni.unique' => 'El DNI ya está registrado.',
            'datos.fechaCaducidadDni.required' => 'La fecha de caducidad del DNI es obligatoria.',
            'datos.fechaCaducidadDni.date' => 'La fecha de caducidad del DNI debe ser una fecha válida.',
            'datos.ruc.size' => 'El RUC debe tener exactamente 11 caracteres.',
            'datos.ruc.unique' => 'El RUC ya está registrado.',
            'contactos.required' => 'Debe proporcionar al menos un contacto.',
            'contactos.min' => 'Debe proporcionar al menos un contacto.',
            'contactos.*.tipo.required' => 'El tipo de contacto es obligatorio.',
            'contactos.*.tipo.in' => 'El tipo de contacto debe ser PRINCIPAL o SECUNDARIO.',
            'contactos.*.telefono.required' => 'El teléfono del contacto es obligatorio.',
            'contactos.*.telefono.max' => 'El teléfono del contacto no puede exceder los 9 caracteres.',
            'contactos.*.telefono.unique' => 'El número de teléfono del contacto ya está registrado.',
            'contactos.*.telefonoDos.max' => 'El segundo teléfono del contacto no puede exceder los 9 caracteres.',
            'contactos.*.email.required' => 'El correo electrónico del contacto es obligatorio.',
            'contactos.*.email.email' => 'El correo electrónico del contacto debe ser válido.',
            'contactos.*.email.unique' => 'El correo electrónico del contacto ya está registrado.',
            'direcciones.required' => 'Debe proporcionar al menos una dirección.',
            'direcciones.min' => 'Debe proporcionar al menos una dirección.',
            'direcciones.*.tipo.in' => 'El tipo de dirección debe ser FISCAL o CORRESPONDENCIA.',
            'direcciones.*.tipoVia.max' => 'El tipo de vía no puede exceder los 100 caracteres.',
            'direcciones.*.nombreVia.max' => 'El nombre de la vía no puede exceder los 100 caracteres.',
            'direcciones.*.numeroMz.max' => 'El número o manzana no puede exceder los 50 caracteres.',
            'direcciones.*.urbanizacion.required' => 'La urbanización es obligatoria.',
            'direcciones.*.urbanizacion.max' => 'La urbanización no puede exceder los 100 caracteres.',
            'direcciones.*.departamento.required' => 'El departamento es obligatorio.',
            'direcciones.*.departamento.max' => 'El departamento no puede exceder los 50 caracteres.',
            'direcciones.*.provincia.required' => 'La provincia es obligatoria.',
            'direcciones.*.provincia.max' => 'La provincia no puede exceder los 50 caracteres.',
            'direcciones.*.distrito.required' => 'El distrito es obligatorio.',
            'direcciones.*.distrito.max' => 'El distrito no puede exceder los 50 caracteres.',
            'financiera.numeroCuenta.required' => 'El número de cuenta es obligatorio.',
            'financiera.numeroCuenta.max' => 'El número de cuenta no puede exceder los 20 caracteres.',
            'financiera.numeroCuenta.unique' => 'El número de cuenta ya está registrado.',
            'financiera.cci.size' => 'El CCI debe tener exactamente 20 caracteres.',
            'financiera.cci.unique' => 'El CCI ya está registrado.',
            'financiera.entidadFinanciera.required' => 'La entidad financiera es obligatoria.',
            'financiera.entidadFinanciera.max' => 'La entidad financiera no puede exceder los 50 caracteres.',
        ];

        // Crear el validador
        $validator = Validator::make($request->all(), $rules, $messages);

        // Verificar si la validación falla
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Errores de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Actualizar datos personales
            $datos = $usuario->datos;
            $datos->update([
                'nombre' => data_get($request, 'datos.nombre'),
                'apellidoPaterno' => data_get($request, 'datos.apellidoPaterno'),
                'apellidoMaterno' => data_get($request, 'datos.apellidoMaterno'),
                'apellidoConyuge' => data_get($request, 'datos.apellidoConyuge'),
                'estadoCivil' => data_get($request, 'datos.estadoCivil'),
                'dni' => data_get($request, 'datos.dni'),
                'fechaCaducidadDni' => data_get($request, 'datos.fechaCaducidadDni'),
                'ruc' => data_get($request, 'datos.ruc'),
            ]);

            $usuario->username = $request->username;

            if ($request->filled('password')) {
                $usuario->password = Hash::make($request->password);
            }

            $usuario->save();

            // Eliminar y recrear direcciones
            $datos->direcciones()->delete();
            foreach ($request->direcciones as $direccion) {
                Direccion::create([
                    'idDatos' => $datos->idDatos,
                    'tipo' => data_get($direccion, 'tipo'),
                    'tipoVia' => data_get($direccion, 'tipoVia'),
                    'nombreVia' => data_get($direccion, 'nombreVia'),
                    'numeroMz' => data_get($direccion, 'numeroMz'),
                    'urbanizacion' => data_get($direccion, 'urbanizacion'),
                    'departamento' => data_get($direccion, 'departamento'),
                    'provincia' => data_get($direccion, 'provincia'),
                    'distrito' => data_get($direccion, 'distrito')
                ]);
            }

            // Eliminar y recrear contactos
            $datos->contactos()->delete();
            foreach ($request->contactos as $contacto) {
                Contacto::create([
                    'idDatos' => $datos->idDatos,
                    'tipo' => data_get($contacto, 'tipo'),
                    'telefono' => data_get($contacto, 'telefono'),
                    'telefonoDos' => data_get($contacto, 'telefonoDos'),
                    'email' => data_get($contacto, 'email')
                ]);
            }

            // Eliminar y recrear cuenta bancaria
            $datos->cuentasBancarias()->delete();
            CuentaBancaria::create([
                'idDatos' => $datos->idDatos,
                'numeroCuenta' => data_get($request, 'financiera.numeroCuenta'),
                'cci' => data_get($request, 'financiera.cci'),
                'entidadFinanciera' => data_get($request, 'financiera.entidadFinanciera')
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Empleado actualizado con éxito',
                'asesor' => $usuario->load([
                    'datos',
                    'datos.direcciones',
                    'datos.contactos',
                    'datos.cuentasBancarias'
                ])
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar asesor', [
                'exception' => $e,
                'data' => $request->all()
            ]);
            return response()->json([
                'message' => 'Error al actualizar al empleado. Si el error persiste, contacte a soporte.',
            ], 500);
        }
    }

    /**
     * Actualizar el estado de un empleado.
     */
    public function updateStatusAsesor(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'estado' => 'required|in:activo,inactivo',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $asesor = User::find($id);
        if (!$asesor) {
            return response()->json([
                'message' => 'Empleado no encontrado'
            ], 404);
        }

        $asesor->estado = $request->estado === 'activo' ? 1 : 0;
        $asesor->save();

        return response()->json([
            'message' => 'Estado del empleado actualizado con éxito',
            'asesor' => $asesor
        ], 200);
    }
}
