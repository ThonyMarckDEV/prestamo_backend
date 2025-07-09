<?php

namespace App\Http\Controllers;

use Dompdf\Dompdf;
use App\Models\ClienteAval;
use App\Models\Datos;
use App\Models\Direccion;
use App\Models\Prestamo;
use App\Models\Cuota;
use App\Models\Pago;
use App\Models\EstadoPrestamo;
use App\Models\User;
use App\Models\CuentaBancaria;
use App\Models\Contacto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Barryvdh\DomPDF\Facade as PDF;
use Illuminate\Support\Facades\Validator;

class PrestamoController extends Controller
{
  public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            Log::info('store: Raw request', ['request' => $request->all()]);

            $validator = Validator::make($request->all(), [
                'idCliente' => 'required|exists:usuarios,idUsuario',
                'idGrupo' => 'nullable|exists:grupos,idGrupo',
                'credito' => 'required|numeric|min:1',
                'frecuencia' => 'required|in:semanal,catorcenal,quincenal,mensual',
                'interes' => 'required|numeric|min:0',
                'cuotas' => 'required|integer|min:1',
                'cuota' => 'required|numeric|min:0',
                'total' => 'required|numeric|min:0',
                'modalidad' => 'required|in:nuevo,rcs,rss',
                'situacion' => 'nullable|string',
                'fecha' => 'required|date',
                'fechaInicio' => 'required|date',
                'fechaGeneracion' => 'required|date',
                'idAsesor' => 'required|exists:usuarios,idUsuario',
                'idProducto' => 'required|exists:productos,idProducto',
                'abonado_por' => 'nullable|in:CUENTA CORRIENTE,CAJA CHICA',
            ]);

            if ($validator->fails()) {
                Log::error('store: Validation failed', ['errors' => $validator->errors()]);
                throw new \Illuminate\Validation\ValidationException($validator);
            }

            $validatedData = $validator->validated();

            Log::info('store: Validated data', ['idGrupo' => $validatedData['idGrupo'], 'idProducto' => $validatedData['idProducto']]);

            // Obtener datos del cliente
            $usuario = User::where('idUsuario', $validatedData['idCliente'])->first();

            // Verificar si el usuario está inactivo
            if (!$usuario->estado) {
                throw new \Exception("El cliente {$validatedData['idCliente']} está inactivo y no puede recibir un préstamo.");
            }

            $clienteInfo = "ID {$validatedData['idCliente']}";
            if ($usuario && $usuario->idDatos) {
                $datosCliente = Datos::where('idDatos', $usuario->idDatos)->first();
                if ($datosCliente) {
                    $clienteInfo = "{$datosCliente->nombre} {$datosCliente->apellido} (DNI: {$datosCliente->dni})";
                }
            }

            // Verificar préstamo activo
            $prestamoExistente = Prestamo::where('idCliente', $validatedData['idCliente'])
                ->where('estado', 'activo')
                ->first();

            if ($prestamoExistente) {
                if ($validatedData['modalidad'] === 'nuevo' || $validatedData['modalidad'] === 'rss') {
                    throw new \Exception("El cliente {$clienteInfo} ya tiene un préstamo activo.");
                }
                if ($validatedData['modalidad'] === 'rcs') {
                    $ultimaCuota = $prestamoExistente->cuotas()
                        ->orderBy('numero_cuota', 'desc')
                        ->first();
                    $cuotasPendientes = $prestamoExistente->cuotas()
                        ->where('estado', 'pendiente')
                        ->where('numero_cuota', '<', $ultimaCuota->numero_cuota)
                        ->count();

                    Log::info('store: Checking pending cuotas', [
                        'idPrestamo' => $prestamoExistente->idPrestamo,
                        'ultimaCuota' => $ultimaCuota->numero_cuota,
                        'cuotasPendientes' => $cuotasPendientes
                    ]);

                    if ($cuotasPendientes > 0) {
                        throw new \Exception("El cliente {$clienteInfo} tiene cuotas pendientes.");
                    }

                    $montoPendiente = $ultimaCuota->estado === 'pendiente' ? $ultimaCuota->monto : 0;
                    $prestamoExistente->update(['estado' => 'cancelado']);

                    // Actualizar el último registro en estados_prestamo para el préstamo existente
                    $ultimoEstado = EstadoPrestamo::where('idPrestamo', $prestamoExistente->idPrestamo)
                        ->orderBy('fecha_actualizacion', 'desc')
                        ->first();

                    if ($ultimoEstado) {
                        $ultimoEstado->update([
                            'estado' => 'cancelado',
                            'fecha_actualizacion' => now(),
                            'observacion' => 'Cancelado automáticamente al generar nuevo préstamo RCS'
                        ]);
                    } else {
                        // Si no existe un registro en estados_prestamo, crear uno
                        EstadoPrestamo::create([
                            'idPrestamo' => $prestamoExistente->idPrestamo,
                            'estado' => 'cancelado',
                            'fecha_actualizacion' => now(),
                            'idUsuario' => $validatedData['idCliente'],
                            'observacion' => 'Cancelado automáticamente al generar nuevo préstamo RCS'
                        ]);
                    }

                    if ($ultimaCuota->estado === 'pendiente') {
                        $ultimaCuota->update([
                            'estado' => 'pagado',
                            'fecha_pago' => now()
                        ]);

                        $validatedData['credito'] -= $montoPendiente;
                        $validatedData['total'] = $validatedData['credito'] + ($validatedData['credito'] * $validatedData['interes'] / 100);
                        $validatedData['cuota'] = $validatedData['total'] / $validatedData['cuotas'];
                    }
                }
            }

            $prestamo = Prestamo::create([
                'idCliente' => $validatedData['idCliente'],
                'idGrupo' => $validatedData['idGrupo'] ? (int) $validatedData['idGrupo'] : null,
                'monto' => $validatedData['credito'],
                'interes' => $validatedData['interes'],
                'total' => $validatedData['total'],
                'cuotas' => $validatedData['cuotas'],
                'valor_cuota' => $validatedData['cuota'],
                'frecuencia' => $validatedData['frecuencia'],
                'modalidad' => $validatedData['modalidad'],
                'situacion' => $validatedData['situacion'] ?? null,
                'fecha_generacion' => $validatedData['fechaGeneracion'],
                'fecha_inicio' => $validatedData['fechaInicio'],
                'idAsesor' => $validatedData['idAsesor'],
                'idProducto' => $validatedData['idProducto'],
                'abonado_por' => $validatedData['abonado_por'] ?? null,
                'estado' => 'activo'
            ]);

            Log::info('store: Created prestamo', ['idPrestamo' => $prestamo->idPrestamo, 'idGrupo' => $prestamo->idGrupo, 'idProducto' => $prestamo->idProducto]);

            if (!$prestamo->idPrestamo) {
                throw new \Exception('No se pudo crear el préstamo en la base de datos');
            }

            $this->generarCronogramaCuotas($prestamo);

            EstadoPrestamo::create([
                'idPrestamo' => $prestamo->idPrestamo,
                'estado' => 'vigente',
                'fecha_actualizacion' => now(),
                'idUsuario' =>  $validatedData['idCliente']
            ]);

            $rutaPDF = $this->generarPDFCronograma($prestamo);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Préstamo registrado correctamente',
                'data' => $prestamo->load('cuotas', 'cliente', 'asesor', 'producto'),
                'pdf_cronograma' => $rutaPDF
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::error('Validation error in PrestamoController@store: ' . $e->getMessage(), ['errors' => $e->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in PrestamoController@store: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar el préstamo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function storeGroup(Request $request)
    {
        try {
            DB::beginTransaction();

            Log::info('storeGroup: Raw request', ['request' => $request->all()]);

            $validator = Validator::make($request->all(), [
                'prestamos' => 'required|array|min:1',
                'prestamos.*.idCliente' => 'required|exists:usuarios,idUsuario',
                'prestamos.*.idGrupo' => 'required|exists:grupos,idGrupo',
                'prestamos.*.credito' => 'required|numeric|min:1',
                'prestamos.*.frecuencia' => 'required|in:semanal,catorcenal,quincenal,mensual',
                'prestamos.*.interes' => 'required|numeric|min:0',
                'prestamos.*.cuotas' => 'required|integer|min:1',
                'prestamos.*.cuota' => 'required|numeric|min:0',
                'prestamos.*.total' => 'required|numeric|min:0',
                'prestamos.*.modalidad' => 'required|in:nuevo,rcs,rss',
                'prestamos.*.situacion' => 'nullable|string',
                'prestamos.*.fecha' => 'required|date',
                'prestamos.*.fechaInicio' => 'required|date',
                'prestamos.*.fechaGeneracion' => 'required|date',
                'prestamos.*.idAsesor' => 'required|exists:usuarios,idUsuario',
                'prestamos.*.idProducto' => 'required|exists:productos,idProducto',
                'prestamos.*.abonado_por' => 'nullable|in:CUENTA CORRIENTE,CAJA CHICA',
            ]);

            if ($validator->fails()) {
                Log::error('storeGroup: Validation failed', ['errors' => $validator->errors()]);
                throw new \Illuminate\Validation\ValidationException($validator);
            }

            $validatedData = $validator->validated();
            $prestamos = [];

            foreach ($validatedData['prestamos'] as $index => $prestamoData) {
                Log::info('storeGroup: Processing prestamo', [
                    'idCliente' => $prestamoData['idCliente'],
                    'idGrupo' => $prestamoData['idGrupo'],
                    'index' => $index,
                    'idProducto' => $prestamoData['idProducto']
                ]);

                // Obtener datos del cliente
                $usuario = User::where('idUsuario', $prestamoData['idCliente'])->first();

                if (!$usuario->estado) {
                    throw new \Exception("El cliente {$prestamoData['idCliente']} está inactivo y no puede recibir un préstamo.");
                }

                $clienteInfo = "ID {$prestamoData['idCliente']}";
                if ($usuario && $usuario->idDatos) {
                    $datosCliente = Datos::where('idDatos', $usuario->idDatos)->first();
                    if ($datosCliente) {
                        $clienteInfo = "{$datosCliente->nombre} {$datosCliente->apellido} (DNI: {$datosCliente->dni})";
                    }
                }

                // Verificar préstamo activo
                $prestamoExistente = Prestamo::where('idCliente', $prestamoData['idCliente'])
                    ->where('estado', 'activo')
                    ->first();

                if ($prestamoExistente) {
                    if ($prestamoData['modalidad'] === 'nuevo' || $prestamoData['modalidad'] === 'rss') {
                        throw new \Exception("El cliente {$clienteInfo} ya tiene un préstamo activo.");
                    }
                    if ($prestamoData['modalidad'] === 'rcs') {
                        $ultimaCuota = $prestamoExistente->cuotas()
                            ->orderBy('numero_cuota', 'desc')
                            ->first();
                        $cuotasPendientes = $prestamoExistente->cuotas()
                            ->where('estado', 'pendiente')
                            ->where('numero_cuota', '<', $ultimaCuota->numero_cuota)
                            ->count();

                        Log::info('storeGroup: Checking pending cuotas', [
                            'idPrestamo' => $prestamoExistente->idPrestamo,
                            'ultimaCuota' => $ultimaCuota->numero_cuota,
                            'cuotasPendientes' => $cuotasPendientes
                        ]);

                        if ($cuotasPendientes > 0) {
                            throw new \Exception("El cliente {$clienteInfo} tiene cuotas pendientes.");
                        }

                        $montoPendiente = $ultimaCuota->estado === 'pendiente' ? $ultimaCuota->monto : 0;
                        $prestamoExistente->update(['estado' => 'cancelado']);
                        
                        // Actualizar el último registro en estados_prestamo para el préstamo existente
                        $ultimoEstado = EstadoPrestamo::where('idPrestamo', $prestamoExistente->idPrestamo)
                            ->orderBy('fecha_actualizacion', 'desc')
                            ->first();

                        if ($ultimoEstado) {
                            $ultimoEstado->update([
                                'estado' => 'cancelado',
                                'fecha_actualizacion' => now(),
                                'observacion' => 'Cancelado automáticamente al generar nuevo préstamo RCS'
                            ]);
                        } else {
                            // Si no existe un registro en estados_prestamo, crear uno
                            EstadoPrestamo::create([
                                'idPrestamo' => $prestamoExistente->idPrestamo,
                                'estado' => 'cancelado',
                                'fecha_actualizacion' => now(),
                                'idUsuario' => $validatedData['idCliente'],
                                'observacion' => 'Cancelado automáticamente al generar nuevo préstamo RCS'
                            ]);
                        }

                        if ($ultimaCuota->estado === 'pendiente') {
                            $ultimaCuota->update([
                                'estado' => 'pagado',
                                'fecha_pago' => now()
                            ]);

                            $prestamoData['credito'] -= $montoPendiente;
                            $prestamoData['total'] = $prestamoData['credito'] + ($prestamoData['credito'] * $prestamoData['interes'] / 100);
                            $prestamoData['cuota'] = $prestamoData['total'] / $prestamoData['cuotas'];
                        }
                    }
                }

                $prestamo = Prestamo::create([
                    'idCliente' => $prestamoData['idCliente'],
                    'idGrupo' => (int) $prestamoData['idGrupo'],
                    'monto' => $prestamoData['credito'],
                    'interes' => $prestamoData['interes'],
                    'total' => $prestamoData['total'],
                    'cuotas' => $prestamoData['cuotas'],
                    'valor_cuota' => $prestamoData['cuota'],
                    'frecuencia' => $prestamoData['frecuencia'],
                    'modalidad' => $prestamoData['modalidad'],
                    'situacion' => $prestamoData['situacion'] ?? null,
                    'fecha_generacion' => $prestamoData['fechaGeneracion'],
                    'fecha_inicio' => $prestamoData['fechaInicio'],
                    'idAsesor' => $prestamoData['idAsesor'],
                    'idProducto' => $prestamoData['idProducto'],
                    'abonado_por' => $prestamoData['abonado_por'] ?? null,
                    'estado' => 'activo'
                ]);

                Log::info('storeGroup: Created prestamo', [
                    'idPrestamo' => $prestamo->idPrestamo,
                    'idGrupo' => $prestamo->idGrupo,
                    'idCliente' => $prestamo->idCliente,
                    'idProducto' => $prestamo->idProducto
                ]);

                if (!$prestamo->idPrestamo) {
                    throw new \Exception('No se pudo crear el préstamo en la base de datos');
                }

                $this->generarCronogramaCuotas($prestamo);

                EstadoPrestamo::create([
                    'idPrestamo' => $prestamo->idPrestamo,
                    'estado' => 'vigente',
                    'fecha_actualizacion' => now(),
                    'idUsuario' =>  $validatedData['idCliente']
                ]);

                $rutaPDF = $this->generarPDFCronograma($prestamo);
                $prestamos[] = [
                    'prestamo' => $prestamo->load('cuotas', 'cliente', 'asesor', 'producto'),
                    'pdf_cronograma' => $rutaPDF
                ];
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Préstamos grupales registrados correctamente',
                'data' => $prestamos
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::error('Validation error in PrestamoController@storeGroup: ' . $e->getMessage(), ['errors' => $e->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in PrestamoController@storeGroup: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar los préstamos grupales',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function generarCronogramaCuotas(Prestamo $prestamo)
    {
        $fechaInicio = Carbon::parse($prestamo->fecha_inicio);

        // Parámetros base
        $principal = $prestamo->monto;
        $cuotas = $prestamo->cuotas;
        $tasaInteres = $prestamo->interes / 100;
        $tasaOtros = 0.01;

        // 1. Monto de otros
        $montoOtros = $principal * $tasaOtros;

        // 2. Suma de monto
        $sumaDeMonto = $principal + $montoOtros;

        // 3. Interés sobre la suma de monto
        $interesTotal = $sumaDeMonto * $tasaInteres;

        // 4. Total a pagar
        $totalAPagar = $sumaDeMonto + $interesTotal;

        // 5. Por cuota
        $valorCuota = round($totalAPagar / $cuotas, 2);
        $capitalPorCuota = round($principal / $cuotas, 2);
        $otrosPorCuota = round($montoOtros / $cuotas, 2);
        $interesPorCuota = round($interesTotal / $cuotas, 2);

        for ($i = 1; $i <= $cuotas; $i++) {
            // Calcular la fecha según la frecuencia
            switch ($prestamo->frecuencia) {
                case 'semanal':
                    $fechaVencimiento = $fechaInicio->copy()->addWeeks($i);
                    break;
                case 'catorcenal':
                    $fechaVencimiento = $fechaInicio->copy()->addDays(14 * $i);
                    break;
                case 'quincenal':
                    $fechaVencimiento = $fechaInicio->copy()->addDays(15 * $i);
                    break;
                case 'mensual':
                    $fechaVencimiento = $fechaInicio->copy()->addMonths($i);
                    break;
                default:
                    $fechaVencimiento = $fechaInicio->copy()->addWeeks($i);
            }

            $cuota = new Cuota();
            $cuota->idPrestamo = $prestamo->idPrestamo;
            $cuota->numero_cuota = $i;
            $cuota->monto = $valorCuota;
            $cuota->capital = $capitalPorCuota;
            $cuota->interes = $interesPorCuota;
            $cuota->fecha_vencimiento = $fechaVencimiento;
            $cuota->estado = 'pendiente';
            $cuota->save();
        }
    }

       public function generarPDFCronograma($idPrestamo)
    {
        try {
            $prestamo = Prestamo::findOrFail($idPrestamo);
            $cliente = $prestamo->cliente;
            $datosCliente = Datos::where('idDatos', $cliente->idDatos)->first();
            if (!$datosCliente) {
                throw new \Exception('Datos del cliente no encontrados');
            }

            $cuotas = $prestamo->cuotas()->orderBy('numero_cuota', 'asc')->get();
            if ($cuotas->isEmpty()) {
                return response()->json(['message' => 'No se encontraron cuotas para este préstamo'], 404);
            }

            $direcciones = Direccion::where('idDatos', $cliente->idDatos)
                ->get()
                ->keyBy('tipo');
            $numeroCuenta = CuentaBancaria::where('idDatos', $cliente->idDatos)
                ->value('numeroCuenta');
            $telefono = Contacto::where('idDatos', $cliente->idDatos)
                ->where('tipo', 'telefono')
                ->value('telefono');
            $fechaInicio = Carbon::parse($prestamo->fecha_inicio)->format('d-m-Y');

            $avalData = null;
            $clienteAval = ClienteAval::where('idCliente', $cliente->idUsuario)->first();
            if ($clienteAval && $clienteAval->aval) {
                $avalUser = $clienteAval->aval;
                $avalDatos = Datos::where('idDatos', $avalUser->idDatos)->first();
                if ($avalDatos) {
                    $avalData = [
                        'nombre' => $avalDatos->nombre . ' ' . $avalDatos->apellido,
                        'dni' => $avalDatos->dni,
                    ];
                }
            }

            $baseDir = storage_path("app/public/clientes/{$cliente->idUsuario}/prestamos/{$prestamo->idPrestamo}");
            $cronogramaDir = "{$baseDir}/cronograma";
            $nombreArchivo = "cronogramapagos-prestamo-{$prestamo->idPrestamo}-{$fechaInicio}-" . time() . ".pdf";
            $rutaArchivo = "{$cronogramaDir}/{$nombreArchivo}";

            Log::info("Base directory: {$baseDir}");
            Log::info("Cronograma directory: {$cronogramaDir}");
            Log::info("PDF file path: {$rutaArchivo}");

            // Delete existing PDFs to avoid conflicts
            $existingFiles = glob("{$cronogramaDir}/*.pdf");
            foreach ($existingFiles as $file) {
                if (File::exists($file)) {
                    File::delete($file);
                    Log::info("Deleted existing PDF: {$file}");
                }
            }

            // Create cronograma directory if it doesn't exist
            if (!File::exists($cronogramaDir)) {
                if (!File::makeDirectory($cronogramaDir, 0755, true)) {
                    Log::error("Error al crear el directorio: {$cronogramaDir}");
                    throw new \Exception("No se pudo crear el directorio: {$cronogramaDir}");
                }
                Log::info("Directorio cronograma creado: {$cronogramaDir}");
            }

            // Check if directory is writable
            if (!is_writable($cronogramaDir)) {
                Log::error("El directorio no es escribible: {$cronogramaDir}");
                throw new \Exception("No se puede escribir en el directorio: {$cronogramaDir}");
            }

            $totalCapital = $cuotas->sum('capital');
            $totalInteres = $cuotas->sum('interes');
            $totalOtros = $cuotas->sum(function ($cuota) {
                return $cuota->monto - $cuota->capital - $cuota->interes;
            });
            $totalCuotas = $cuotas->sum('monto');
            $data = [
                'prestamo' => $prestamo,
                'cliente' => $cliente,
                'datosCliente' => $datosCliente,
                'direcciones' => $direcciones,
                'numeroCuenta' => $numeroCuenta,
                'telefono' => $telefono,
                'cuotas' => $cuotas,
                'fecha_actual' => Carbon::now()->format('d/m/Y'),
                'avalData' => $avalData,
                'totalCuotas' => $totalCuotas,
                'moneda' => $prestamo->moneda ?? 'SOLES',
                'simbolo' => $prestamo->simbolo ?? 'S/',
                'totalCapital' => $totalCapital,
                'totalInteres' => $totalInteres,
                'totalOtros' => $totalOtros,
                'totalCuotas' => $totalCuotas,
            ];

            $html = view('CronogramaPagos', $data)->render();
            $dompdf = new Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $written = file_put_contents($rutaArchivo, $dompdf->output());
            if ($written === false || !File::exists($rutaArchivo)) {
                Log::error("Error al escribir el PDF en: {$rutaArchivo}");
                throw new \Exception("No se pudo guardar el PDF en: {$rutaArchivo}");
            }
            Log::info("PDF escrito correctamente en: {$rutaArchivo}, tamaño: " . filesize($rutaArchivo) . " bytes");

            return response()->json([
                'message' => 'Cronograma generado exitosamente',
                'cuotas' => $cuotas->map(function ($cuota) {
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
                        'created_at' => $cuota->created_at,
                        'updated_at' => $cuota->updated_at,
                    ];
                })->toArray(),
                    'pdf_url' => secure_url("storage/clientes/{$cliente->idUsuario}/prestamos/{$prestamo->idPrestamo}/cronograma/{$nombreArchivo}"),
                ]);
        } catch (\Exception $e) {
            Log::error("Error al generar el cronograma de pagos: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Error al generar el cronograma: ' . $e->getMessage()], 500);
        }
    }



    public function index(Request $request)
    {
        $query = Prestamo::with(['cliente', 'asesor', 'cuotas']);
        if ($request->has('idCliente')) {
            $query->where('idCliente', $request->idCliente);
        }
        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->has('idAsesor')) {
            $query->where('idAsesor', $request->asesor_id);
        }

        $prestamos = $query->orderBy('created_at', 'desc')->paginate(10);
        return response()->json([
            'success' => true,
            'data' => $prestamos
        ]);
    }

    public function show($id)
    {
        $prestamo = Prestamo::with(['cliente', 'asesor', 'cuotas.pagos', 'estados'])
            ->findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => $prestamo
        ]);
    }

    public function actualizarEstadosCuotas()
    {
        try {
            $hoy = Carbon::now()->format('Y-m-d');
            Cuota::where('estado', 'pendiente')
                ->where('fecha_vencimiento', $hoy)
                ->update(['estado' => 'vence_hoy']);

            $cuotasVencidas = Cuota::where('estado', 'pendiente')
                ->orWhere('estado', 'vence_hoy')
                ->where('fecha_vencimiento', '<', $hoy)
                ->get();

            foreach ($cuotasVencidas as $cuota) {
                $fechaVencimiento = Carbon::parse($cuota->fecha_vencimiento);
                $diasMora = $fechaVencimiento->diffInDays(Carbon::now());
                $cuota->estado = 'vencido';
                $cuota->dias_mora = $diasMora;
                $cuota->save();

                if ($diasMora > 30) {
                    $prestamo = $cuota->prestamo;
                    $existeMora = EstadoPrestamo::where('idPrestamo', $prestamo->id)
                        ->where('estado', 'mora')
                        ->exists();

                    if (!$existeMora) {
                        $estado = new EstadoPrestamo();
                        $estado->prestamo_id = $prestamo->id;
                        $estado->estado = 'mora';
                        $estado->fecha_actualizacion = now();
                        $estado->observacion = 'Préstamo en mora por más de 30 días';
                        $estado->usuario_id = 1;
                        $estado->save();
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Estados de cuotas actualizados correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar estados de cuotas',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
