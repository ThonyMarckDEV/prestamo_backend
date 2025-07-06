<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ficha del Cliente</title>
    <style>
        @page {
            size: A4;
            margin: 2cm;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            max-width: 21cm;
            margin: 0 auto;
            background: white;
        }

        .logo-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .logo-section img {
            height: 60px;
        }

        .title-section {
            text-align: right;
            flex-grow: 1;
        }

        .title-section h1 {
            color: #e60000;
            font-size: 20px;
            margin: 0;
        }

        h2 {
            background-color: #e60000;
            color: white;
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .section {
            border: 1px solid #ccc;
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 6px;
            page-break-inside: avoid;
        }

        .field-group {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        .field {
            flex: 1 1 45%;
            margin-right: 20px;
            margin-bottom: 8px;
        }

        .field label {
            font-weight: bold;
            display: block;
            font-size: 11px;
        }

        .field span {
            display: block;
            padding: 3px 0;
            border-bottom: 1px solid #ccc;
            font-size: 12px;
        }

        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
        }

        .signature-box {
            border-top: 1px solid #000;
            width: 45%;
            text-align: center;
            padding-top: 5px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="logo-header">
        <div class="logo-section">
            @php
                $path = storage_path('app/public/img/logo.png');
                $type = pathinfo($path, PATHINFO_EXTENSION);
                $mime = match(strtolower($type)) {
                    'svg' => 'image/svg+xml',
                    'png' => 'image/png',
                    'jpg', 'jpeg' => 'image/jpeg',
                    'gif' => 'image/gif',
                    default => 'application/octet-stream',
                };
                $data = file_get_contents($path);
                $base64 = 'data:' . $mime . ';base64,' . base64_encode($data);
            @endphp
            <img src="{{ $base64 }}" alt="Logotipo Fic Sullana">
        </div>
        <div class="title-section">
            <h1>Ficha y Registro del Cliente</h1>
        </div>
    </div>

    {{-- Sección 1 --}}
    <h2>1. DATOS PERSONALES DEL CLIENTE</h2>
    <div class="section">
        <div class="field-group">
            <div class="field"><label>DNI</label><span>{{ $datosCliente->dni ?? '-' }}</span></div>
            <div class="field"><label>RUC</label><span>{{ $datosCliente->ruc ?? '-' }}</span></div>
            <div class="field"><label>Nombres y Apellidos</label><span>{{ $datosCliente->nombre ?? '' }} {{ $datosCliente->apellidoPaterno ?? '' }} {{ $datosCliente->apellidoMaterno ?? '' }} {{ $datosCliente->apellidoConyuge ?? '' }}</span></div>
            <div class="field"><label>Estado Civil</label><span>{{ $datosCliente->estadoCivil ?? '-' }}</span></div>
            <div class="field"><label>Fecha Caducidad DNI</label><span>{{ $datosCliente->fechaCaducidadDni ?? '-' }}</span></div>
            <div class="field"><label>¿Expuesta políticamente?</label><span>{{ $datosCliente->Expuesta ? 'Sí' : 'No' }}</span></div>
            <div class="field"><label>¿Tiene aval?</label><span>{{ $datosCliente->aval ? 'Sí' : 'No' }}</span></div>
        </div>
    </div>

    {{-- Sección 2 --}}
    <h2>2. DIRECCIÓN FISCAL</h2>
    @php $direccionFiscal = collect($cliente->direcciones)->firstWhere('tipo', 'Fiscal'); @endphp
    <div class="section">
        <div class="field-group">
            @if(isset($direcciones['FISCAL']))
            <div class="field"><label>Tipo de Vía</label><span>{{ $direcciones['FISCAL']->tipoVia ?? '' }}</span></div>
            <div class="field"><label>Nombre de Vía</label><span>{{ $direcciones['FISCAL']->nombreVia ?? '' }}</span></div>
            <div class="field"><label>N° / Mz</label><span>{{ $direcciones['FISCAL']->numeroMz ?? '' }}</span></div>
            <div class="field"><label>Urbanización</label><span>{{ $direcciones['FISCAL']->urbanizacion ?? '' }}</span></div>
            <div class="field"><label>Departamento</label><span>{{ $direcciones['FISCAL']->distrito ?? '' }}</span></div>
            <div class="field"><label>Provincia</label><span>{{ $direcciones['FISCAL']->provincia ?? '' }}</span></div>
            <div class="field"><label>Distrito</label><span>{{ $direcciones['FISCAL']->departamento ?? '' }}</span></div>
            @else
            ---
            @endif
        </div>
    </div>

    {{-- Sección 3 --}}
    <h2>3. DIRECCIÓN DE CORRESPONDENCIA</h2>
    @php $direccionCorr = collect($cliente->direcciones)->firstWhere('tipo', 'Correspondencia'); @endphp
    <div class="section">
        <div class="field-group">
            @if(isset($direcciones['CORRESPONDENCIA']))
            <div class="field"><label>Tipo de Vía</label><span>{{ $direcciones['CORRESPONDENCIA']->tipoVia ?? '' }}</span></div>
            <div class="field"><label>Nombre de Vía</label><span>{{ $direcciones['CORRESPONDENCIA']->nombreVia ?? '' }}</span></div>
            <div class="field"><label>N° / Mz</label><span>{{ $direcciones['CORRESPONDENCIA']->numeroMz ?? '' }}</span></div>
            <div class="field"><label>Urbanización</label><span>{{ $direcciones['CORRESPONDENCIA']->urbanizacion ?? '' }}</span></div>
            <div class="field"><label>Departamento</label><span>{{ $direcciones['CORRESPONDENCIA']->distrito ?? '' }}</span></div>
            <div class="field"><label>Provincia</label><span>{{ $direcciones['CORRESPONDENCIA']->provincia ?? '' }}</span></div>
            <div class="field"><label>Distrito</label><span>{{ $direcciones['CORRESPONDENCIA']->departamento ?? '' }}</span></div>
            @else
            ---
            @endif
        </div>
    </div>

    {{-- Sección 4 --}}
    <h2>4. DATOS DE CONTACTO</h2>
    @foreach($cliente->contactos as $contacto)
    <div class="section">
        <div class="field-group">
            <div class="field"><label>Teléfono 1</label><span>{{ $contacto['telefono'] }}</span></div>
            <div class="field"><label>Teléfono 2</label><span>{{ $contacto['telefonoDos'] ?? '-' }}</span></div>
            <div class="field"><label>Email</label><span>{{ $contacto['email'] ?? '-' }}</span></div>
        </div>
    </div>
    @endforeach

    {{-- Sección 5 --}}
    <h2>5. INFORMACIÓN FINANCIERA</h2>
    @foreach($cliente->cuentasBancarias as $cuenta)
    <div class="section">
        <div class="field-group">
            <div class="field"><label>Entidad Financiera</label><span>{{ $cuenta['entidadFinanciera'] }}</span></div>
            <div class="field"><label>N° de Cuenta</label><span>{{ $cuenta['numeroCuenta'] }}</span></div>
            <div class="field"><label>CCI</label><span>{{ $cuenta['cci'] ?? '-' }}</span></div>
        </div>
    </div>
    @endforeach

    {{-- Sección 6 --}}
    <h2>6. ACTIVIDADES ECONÓMICAS DEL CLIENTE</h2>
    <div class="section">
        <div class="field-group">
            <div class="field"><label>Actividades No Sensibles</label><span>{{ $cliente->actividadesEconomicas['noSensibles'] }}</span></div>
            <div class="field"><label>Actividad CIIU</label><span>{{ $cliente->actividadesEconomicas['ciiu'] }}</span></div>
        </div>
    </div>

    {{-- Firmas --}}
    <div class="signature-section">
        <div class="signature-box">Firma del Cliente</div>
        <div class="signature-box">Huella</div>
    </div>
</body>
</html>
