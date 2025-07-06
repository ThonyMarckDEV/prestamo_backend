<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Datos;
use App\Models\Direccion;
use App\Models\Contacto;
use App\Models\CuentaBancaria;
use App\Models\ActividadEconomica;
use App\Models\Ciiu;
use App\Models\NoSensible;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    /**
     * Obtener todos los clientes
     */
    public function getClients()
    {
        // Obtener todos los clientes (rol cliente - id 2) con sus relaciones
        $clientes = User::with([
            'datos',
            'datos.direcciones',
            'datos.contactos',
            'datos.cuentasBancarias',
            'datos.actividadesEconomicas',
            'datos.actividadesEconomicas.ciiu',
            'datos.actividadesEconomicas.noSensible'
        ])
            ->where('idRol', 2)
            ->get();

        return response()->json([
            'message' => 'Clientes obtenidos con éxito',
            'clientes' => $clientes
        ], 200);
    }

    public function createClient(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'datos.nombre' => 'required|string|max:100',
            'datos.apellidoPaterno' => 'required|string|max:50',
            'datos.apellidoMaterno' => 'required|string|max:50',
            'datos.apellidoConyuge' => 'nullable|string|max:50',
            'datos.estadoCivil' => 'required|string|max:50',
            'datos.dni' => 'required|string|max:9|unique:datos,dni',
            'datos.fechaCaducidadDni' => 'required|date|after:today',
            'datos.ruc' => 'nullable|string|size:11|unique:datos,ruc',
            'datos.expuesta' => 'required|boolean',
            'datos.aval' => 'required|boolean',
            'direcciones' => 'required|array|min:1',
            'direcciones.*.tipoVia' => 'nullable|string',
            'direcciones.*.nombreVia' => 'nullable|string',
            'direcciones.*.numeroMz' => 'nullable|string',
            'contactos' => 'required|array|min:1',
            'contactos.*.telefono' => 'required|string|max:9|unique:contactos,telefono',
            'contactos.*.telefonoDos' => 'nullable|string|max:9',
            'contactos.*.email' => 'nullable|email|max:100|unique:contactos,email',
            'cuentasBancarias' => 'required|array|min:1',
            'cuentasBancarias.*.numeroCuenta' => 'required|string|max:20|unique:cuentas_bancarias,numeroCuenta',
            'cuentasBancarias.*.cci' => 'nullable|string|size:20|unique:cuentas_bancarias,cci',
            'cuentasBancarias.*.entidadFinanciera' => 'required|string|max:50',
            'actividadesEconomicas.noSensibles' => 'required|array|size:1',
            'actividadesEconomicas.noSensibles.*' => 'required|exists:no_sensibles,idNoSensible',
            'actividadesEconomicas.ciiu' => 'required|array|size:1',
            'actividadesEconomicas.ciiu.*' => 'required|exists:ciiu,idCiiu',
        ], [
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
            'datos.expuesta.required' => 'El campo persona expuesta políticamente es obligatorio.',
            'datos.expuesta.boolean' => 'El campo persona expuesta políticamente debe ser SÍ o NO.',
            'datos.aval.required' => 'El campo aval es obligatorio.',
            'datos.aval.boolean' => 'El campo aval debe ser SÍ o NO.',
            'direcciones.required' => 'Debe proporcionar al menos una dirección.',
            'direcciones.min' => 'Debe proporcionar al menos una dirección.',
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
            'contactos.required' => 'Debe proporcionar al menos un contacto.',
            'contactos.min' => 'Debe proporcionar al menos un contacto.',
            'contactos.*.tipo.required' => 'El tipo de contacto es obligatorio.',
            'contactos.*.tipo.in' => 'El tipo de contacto debe ser PRINCIPAL o SECUNDARIO.',
            'contactos.*.telefono.required' => 'El teléfono del contacto es obligatorio.',
            'contactos.*.telefono.max' => 'El teléfono del contacto no puede exceder los 9 caracteres.',
            'contactos.*.telefono.unique' => 'El número de teléfono del contacto ya está registrado.',
            'contactos.*.telefonoDos.max' => 'El segundo teléfono del contacto no puede exceder los 9 caracteres.',
            'contactos.*.email.email' => 'El correo electrónico del contacto debe ser válido.',
            'contactos.*.email.max' => 'El correo electrónico del contacto no puede exceder los 100 caracteres.',
            'contactos.*.email.unique' => 'El correo electrónico del contacto ya está registrado.',
            'cuentasBancarias.required' => 'Debe proporcionar al menos una cuenta bancaria.',
            'cuentasBancarias.min' => 'Debe proporcionar al menos una cuenta bancaria.',
            'cuentasBancarias.*.numeroCuenta.required' => 'El número de cuenta es obligatorio.',
            'cuentasBancarias.*.numeroCuenta.max' => 'El número de cuenta no puede exceder los 20 caracteres.',
            'cuentasBancarias.*.numeroCuenta.unique' => 'El número de cuenta ya está registrado.',
            'cuentasBancarias.*.cci.size' => 'El CCI debe tener exactamente 20 caracteres.',
            'cuentasBancarias.*.cci.unique' => 'El CCI ya está registrado.',
            'cuentasBancarias.*.entidadFinanciera.required' => 'La entidad financiera es obligatoria.',
            'cuentasBancarias.*.entidadFinanciera.max' => 'La entidad financiera no puede exceder los 50 caracteres.',
            'actividadesEconomicas.noSensibles.required' => 'La actividad económica no sensible es obligatoria.',
            'actividadesEconomicas.noSensibles.size' => 'Debe proporcionar exactamente una actividad no sensible.',
            'actividadesEconomicas.noSensibles.*.exists' => 'La actividad no sensible seleccionada no es válida.',
            'actividadesEconomicas.ciiu.required' => 'La actividad económica CIIU es obligatoria.',
            'actividadesEconomicas.ciiu.size' => 'Debe proporcionar exactamente una actividad CIIU.',
            'actividadesEconomicas.ciiu.*.exists' => 'La actividad CIIU seleccionada no es válida.',
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
                'nombre' => $request->datos['nombre'],
                'apellidoPaterno' => $request->datos['apellidoPaterno'],
                'apellidoMaterno' => $request->datos['apellidoMaterno'],
                'apellidoConyuge' => $request->datos['apellidoConyuge'] ?? null,
                'estadoCivil' => $request->datos['estadoCivil'],
                'dni' => $request->datos['dni'],
                'fechaCaducidadDni' => $request->datos['fechaCaducidadDni'],
                'ruc' => $request->datos['ruc'] ?? null,
                'expuesta' => $request->datos['expuesta'],
                'aval' => $request->datos['aval']
            ]);

            // Crear direcciones
            foreach ($request->direcciones as $direccion) {
                Direccion::create([
                    'idDatos' => $datos->idDatos,
                    'tipo' => $direccion['tipo'] ?? null,
                    'tipoVia' => $direccion['tipoVia'] ?? null,
                    'nombreVia' => $direccion['nombreVia'] ?? null,
                    'numeroMz' => $direccion['numeroMz'] ?? null,
                    'urbanizacion' => $direccion['urbanizacion'],
                    'departamento' => $direccion['departamento'],
                    'provincia' => $direccion['provincia'],
                    'distrito' => $direccion['distrito']
                ]);
            }

            // Crear contactos
            foreach ($request->contactos as $contacto) {
                Contacto::create([
                    'idDatos' => $datos->idDatos,
                    'tipo' => $contacto['tipo'],
                    'telefono' => $contacto['telefono'],
                    'telefonoDos' => $contacto['telefonoDos'] ?? null,
                    'email' => $contacto['email'] ?? null
                ]);
            }

            // Crear cuentas bancarias
            foreach ($request->cuentasBancarias as $cuenta) {
                CuentaBancaria::create([
                    'idDatos' => $datos->idDatos,
                    'numeroCuenta' => $cuenta['numeroCuenta'],
                    'cci' => $cuenta['cci'] ?? null,
                    'entidadFinanciera' => $cuenta['entidadFinanciera']
                ]);
            }

            // Simplified actividades económicas creation
            ActividadEconomica::create([
                'idDatos' => $datos->idDatos,
                'idNoSensible' => $request->actividadesEconomicas['noSensibles'][0],
                'idCiiu' => $request->actividadesEconomicas['ciiu'][0],
            ]);

            // Crear usuario
            $usuario = User::create([
                'username' => $request->datos['dni'],
                'password' => Hash::make($request->datos['dni']),
                'idRol' => 2,
                'idDatos' => $datos->idDatos,
                'estado' => 1
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Cliente creado con éxito',
                'cliente' => $usuario->load([
                    'datos',
                    'datos.direcciones',
                    'datos.contactos',
                    'datos.cuentasBancarias',
                    'datos.actividadesEconomicas',
                    'datos.actividadesEconomicas.ciiu',
                    'datos.actividadesEconomicas.noSensible'
                ])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear el cliente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateClient(Request $request, $id)
    {
        $usuario = User::findOrFail($id);
        $datos = $usuario->datos;

        $validator = Validator::make($request->all(), [
            'datos.nombre' => 'required|string|max:100',
            'datos.apellidoPaterno' => 'required|string|max:50',
            'datos.apellidoMaterno' => 'required|string|max:50',
            'datos.apellidoConyuge' => 'nullable|string|max:50',
            'datos.estadoCivil' => 'required|string|max:50',
            'datos.dni' => ['required', 'string', 'max:9', Rule::unique('datos', 'dni')->ignore($datos->idDatos, 'idDatos')],
            'datos.fechaCaducidadDni' => 'required|date',
            'datos.ruc' => ['nullable', 'string', 'size:11', Rule::unique('datos', 'ruc')->ignore($datos->idDatos, 'idDatos')->whereNotNull('ruc')],
            'datos.expuesta' => 'required|boolean',
            'datos.aval' => 'required|boolean',
            'direcciones' => 'required|array|min:1',
            'direcciones.*.tipoVia' => 'nullable|string|max:100',
            'direcciones.*.nombreVia' => 'nullable|string|max:100',
            'direcciones.*.numeroMz' => 'nullable|string|max:50',
            'direcciones.*.urbanizacion' => 'required|string|max:100',
            'direcciones.*.departamento' => 'required|string|max:50',
            'direcciones.*.provincia' => 'required|string|max:50',
            'direcciones.*.distrito' => 'required|string|max:50',
            'contactos' => 'required|array|min:1',
            'contactos.*.tipo' => 'required|string|in:PRINCIPAL,SECUNDARIO',
            'contactos.*.telefono' => 'required|string|max:9|unique:contactos,telefono,' . $datos->idDatos . ',idDatos',
            'contactos.*.telefonoDos' => 'nullable|string|max:9',
            'contactos.*.email' => 'nullable|email|max:100|unique:contactos,email,' . $datos->idDatos . ',idDatos',
            'cuentasBancarias' => 'required|array|min:1',
            'cuentasBancarias.*.numeroCuenta' => 'required|string|max:20|unique:cuentas_bancarias,numeroCuenta,' . $datos->idDatos . ',idDatos',
            'cuentasBancarias.*.cci' => 'nullable|string|size:20|unique:cuentas_bancarias,cci,' . $datos->idDatos . ',idDatos',
            'cuentasBancarias.*.entidadFinanciera' => 'required|string|max:50',
            'actividadesEconomicas.noSensibles' => 'required|array|size:1',
            'actividadesEconomicas.noSensibles.*' => 'required|exists:no_sensibles,idNoSensible',
            'actividadesEconomicas.ciiu' => 'required|array|size:1',
            'actividadesEconomicas.ciiu.*' => 'required|exists:ciiu,idCiiu',
        ], [
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
            'datos.expuesta.required' => 'El campo persona expuesta políticamente es obligatorio.',
            'datos.expuesta.boolean' => 'El campo persona expuesta políticamente debe ser SÍ o NO.',
            'datos.aval.required' => 'El campo aval es obligatorio.',
            'datos.aval.boolean' => 'El campo aval debe ser SÍ o NO.',
            'direcciones.required' => 'Debe proporcionar al menos una dirección.',
            'direcciones.min' => 'Debe proporcionar al menos una dirección.',
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
            'contactos.required' => 'Debe proporcionar al menos un contacto.',
            'contactos.min' => 'Debe proporcionar al menos un contacto.',
            'contactos.*.tipo.required' => 'El tipo de contacto es obligatorio.',
            'contactos.*.tipo.in' => 'El tipo de contacto debe ser PRINCIPAL o SECUNDARIO.',
            'contactos.*.telefono.required' => 'El teléfono del contacto es obligatorio.',
            'contactos.*.telefono.max' => 'El teléfono del contacto no puede exceder los 9 caracteres.',
            'contactos.*.telefono.unique' => 'El número de teléfono del contacto ya está registrado.',
            'contactos.*.telefonoDos.max' => 'El segundo teléfono del contacto no puede exceder los 9 caracteres.',
            'contactos.*.email.email' => 'El correo electrónico del contacto debe ser válido.',
            'contactos.*.email.max' => 'El correo electrónico del contacto no puede exceder los 100 caracteres.',
            'contactos.*.email.unique' => 'El correo electrónico del contacto ya está registrado.',
            'cuentasBancarias.required' => 'Debe proporcionar al menos una cuenta bancaria.',
            'cuentasBancarias.min' => 'Debe proporcionar al menos una cuenta bancaria.',
            'cuentasBancarias.*.numeroCuenta.required' => 'El número de cuenta es obligatorio.',
            'cuentasBancarias.*.numeroCuenta.max' => 'El número de cuenta no puede exceder los 20 caracteres.',
            'cuentasBancarias.*.numeroCuenta.unique' => 'El número de cuenta ya está registrado.',
            'cuentasBancarias.*.cci.size' => 'El CCI debe tener exactamente 20 caracteres.',
            'cuentasBancarias.*.cci.unique' => 'El CCI ya está registrado.',
            'cuentasBancarias.*.entidadFinanciera.required' => 'La entidad financiera es obligatoria.',
            'cuentasBancarias.*.entidadFinanciera.max' => 'La entidad financiera no puede exceder los 50 caracteres.',
            'actividadesEconomicas.noSensibles.required' => 'La actividad económica no sensible es obligatoria.',
            'actividadesEconomicas.noSensibles.size' => 'Debe proporcionar exactamente una actividad no sensible.',
            'actividadesEconomicas.noSensibles.*.exists' => 'La actividad no sensible seleccionada no es válida.',
            'actividadesEconomicas.ciiu.required' => 'La actividad económica CIIU es obligatoria.',
            'actividadesEconomicas.ciiu.size' => 'Debe proporcionar exactamente una actividad CIIU.',
            'actividadesEconomicas.ciiu.*.exists' => 'La actividad CIIU seleccionada no es válida.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Errores de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Actualizar datos personales
            $datos->update([
                'nombre' => $request->datos['nombre'],
                'apellidoPaterno' => $request->datos['apellidoPaterno'],
                'apellidoMaterno' => $request->datos['apellidoMaterno'],
                'apellidoConyuge' => $request->datos['apellidoConyuge'] ?? null,
                'estadoCivil' => $request->datos['estadoCivil'],
                'dni' => $request->datos['dni'],
                'fechaCaducidadDni' => $request->datos['fechaCaducidadDni'],
                'ruc' => $request->datos['ruc'] ?? null,
                'expuesta' => $request->datos['expuesta'],
                'aval' => $request->datos['aval']
            ]);

            // Eliminar y recrear direcciones
            $datos->direcciones()->delete();
            foreach ($request->direcciones as $direccion) {
                Direccion::create([
                    'idDatos' => $datos->idDatos,
                    'tipo' => $direccion['tipo'] ?? null,
                    'tipoVia' => $direccion['tipoVia'] ?? null,
                    'nombreVia' => $direccion['nombreVia'] ?? null,
                    'numeroMz' => $direccion['numeroMz'] ?? null,
                    'urbanizacion' => $direccion['urbanizacion'],
                    'departamento' => $direccion['departamento'],
                    'provincia' => $direccion['provincia'],
                    'distrito' => $direccion['distrito']
                ]);
            }

            // Eliminar y recrear contactos
            $datos->contactos()->delete();
            foreach ($request->contactos as $contacto) {
                Contacto::create([
                    'idDatos' => $datos->idDatos,
                    'tipo' => $contacto['tipo'],
                    'telefono' => $contacto['telefono'],
                    'telefonoDos' => $contacto['telefonoDos'] ?? null,
                    'email' => $contacto['email'] ?? null
                ]);
            }

            // Eliminar y recrear cuentas bancarias
            $datos->cuentasBancarias()->delete();
            foreach ($request->cuentasBancarias as $cuenta) {
                CuentaBancaria::create([
                    'idDatos' => $datos->idDatos,
                    'numeroCuenta' => $cuenta['numeroCuenta'],
                    'cci' => $cuenta['cci'] ?? null,
                    'entidadFinanciera' => $cuenta['entidadFinanciera']
                ]);
            }

            // Eliminar actividades económicas existentes
            $datos->actividadesEconomicas()->delete();

            // Crear relación de actividad económica
            ActividadEconomica::create([
                'idDatos' => $datos->idDatos,
                'idNoSensible' => $request->actividadesEconomicas['noSensibles'][0],
                'idCiiu' => $request->actividadesEconomicas['ciiu'][0],
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Cliente actualizado con éxito',
                'cliente' => $usuario->load([
                    'datos',
                    'datos.direcciones',
                    'datos.contactos',
                    'datos.cuentasBancarias',
                    'datos.actividadesEconomicas',
                    'datos.actividadesEconomicas.ciiu',
                    'datos.actividadesEconomicas.noSensible'
                ])
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar cliente', [
                'exception' => $e,
                'data' => $request->all()
            ]);
            return response()->json([
                'message' => 'Error al actualizar el cliente. Si el error persiste, contacte a soporte.',
            ], 500);
        }
    }

    /**
     * Actualizar el estado de un cliente
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'estado' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $cliente = User::where('idRol', 2)->find($id);

        if (!$cliente) {
            return response()->json([
                'message' => 'Cliente no encontrado'
            ], 404);
        }

        $cliente->estado = $request->estado;
        $cliente->save();

        return response()->json([
            'message' => 'Estado del cliente actualizado con éxito',
            'cliente' => $cliente
        ], 200);
    }
}
