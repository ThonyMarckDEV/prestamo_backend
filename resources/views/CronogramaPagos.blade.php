<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cronograma de Pagos - FIC Sullana</title>
    <style>
        :root {
            --primary: #e30613;
            --secondary: #002E5B;
            --background-light: #f9fafb;
            --text-default: #1f2937;
            --text-muted: #6b7280;
            --font-base: 13px;
            --font-small: 11px;
            --border-radius: 8px;
        }

        @page {
            size: A4;
            margin: 2cm;
        }

        * {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            box-sizing: border-box;
        }

        body {
            font-size: var(--font-base);
            color: var(--text-default);
            margin: 0;
            padding: 0;
            background: #fff;
            line-height: 1.6;
        }

        .document {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            width: 100%;
            margin-bottom: 20px;
            display: flex;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding: 0;
        }

        .header-top h1 {
            font-size: 20px;
            font-weight: bold;
            color: var(--primary);
            margin: 0;
        }

        .title-section {
            padding-right: 20px;
        }

        .title-section h1 {
            font-size: 18px;
            font-weight: bold;
            color: #000;
            margin: 0;
            display: flex;
            text-transform: uppercase;
            line-height: 1;
        }

        .logo-section {
            flex-shrink: 0;
            text-align: right;
            display: flex;
            align-items: center;
        }

        .logo {
            max-height: 150px; /* Increased from 80px */
            max-width: 300px; /* Increased from 200px */
            width: auto;
            height: auto;
            display: block;
        }

        .center-header {
            text-align: center;
            width: 100%;
        }

        .header-sub-text {
            font-size: 12px;
            text-align: center;
            letter-spacing: 0.5px;
            color: #000;
            font-weight: 500;
        }

        .client-info-table td {
            vertical-align: top;
            padding: 10px;
            font-size: 11px;
        }

        .info-group {
            margin-bottom: 6px;
        }

        .info-label {
            font-size: 9px;
            color: var(--text-muted);
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .info-value {
            font-size: 11px;
            font-weight: 500;
            color: var(--text-default);
        }

        .highlight {
            padding: 8px;
            font-size: 11px;
            line-height: 1.3;
            border-radius: 6px;
            margin-top: 8px;
        }

        table.payment-schedule {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 10px;
            page-break-inside: avoid;
        }

        .payment-schedule th,
        .payment-schedule td {
            text-align: center;
            padding: 4px 6px;
            border: 1px solid #e5e7eb;
            word-wrap: break-word;
        }

        .payment-schedule thead {
            background-color: #f3f4f6;
        }

        .payment-schedule tfoot td {
            font-weight: bold;
            background-color: #f9fafb;
        }

        .observaciones {
            text-align: left;
            max-width: 200px;
            word-break: break-word;
        }

        .firma-bloque {
            font-size: 12px;
            margin-top: 40px;
            text-align: left;
            padding-left: 10px;
        }

        .firma-linea {
            border-top: 1px dashed #6b7280;
            width: 160px;
            margin: 25px 0 25px 0;
        }

        .footer-box {
            margin-top: 20px;
            border: 1px solid #d1d5db;
            border-radius: var(--border-radius);
            padding: 15px;
            display: flex;
            justify-content: center;
            gap: 40px;
            font-size: 12px;
            background-color: #f9fafb;
        }

        .disclaimer {
            font-size: 10px;
            color: var(--text-muted);
            margin-top: 25px;
            padding: 10px;
            background-color: #f9fafb;
            border-left: 3px solid var(--primary);
            text-align: justify;
        }

        .footer-box div {
            flex: 1;
            text-align: center;
        }

        .info-line {
            font-size: 11px;
            margin-bottom: 4px;
            line-height: 1.4;
        }
    </style>
</head>

<body>
    <div class="document">
        <div class="header-top">
            <div class="title-section">
                <h1>Cronograma de Pagos</h1>
            </div>
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
                <img src="{{ $base64 }}" alt="Logotipo Fic Sullana" class="logo">
            </div>
        </div>

        <div class="center-header">
            <div class="header-sub-text">
                CORPORACIÓN E INVERSIONES SULLANA LA PERLA DEL CHIRA S.A.C
            </div>
        </div>
    </div>

    <div class="client-info" style="margin-bottom: 10px;">
        <table style="width: 100%; font-size: 11px; margin-bottom: 10px; border-collapse: collapse;">
            <tr>
                <td style="width: 50%; padding-right: 10px; vertical-align: top;">
                    <div class="info-line"><strong>Solicitud No:</strong> {{ $prestamo->idPrestamo }}</div>
                    <div class="info-line"><strong>Cliente:</strong> {{ $datosCliente->nombre ?? '' }} {{ $datosCliente->apellidoPaterno ?? '' }} {{ $datosCliente->apellidoMaterno ?? '' }} {{ $datosCliente->apellidoConyuge ?? '' }}</div>
                    <div class="info-line"><strong>Producto:</strong>{{ ($prestamo->producto && $prestamo->producto->nombre && $prestamo->producto->rango_tasa) ? $prestamo->producto->nombre . ' ' . $prestamo->producto->rango_tasa : ' - ' }} </div>
                    @if($avalData)
                    <div class="info-line"><strong>Aval:</strong> {{ $avalData['nombre'] ?? '' }} {{ $avalData['apellidoPaterno'] ?? '' }} {{ $avalData['apellidoMaterno'] ?? '' }} {{ $avalData['apellidoConyuge'] ?? '' }}</div>
                    @endif
                    <br>
                    <div class="info-line"><strong>Importe desembolsado:</strong> S/ {{ number_format($prestamo->monto, 2) }}</div>
                    <div class="info-line"><strong>Total a Pagar:</strong> S/ {{ number_format($totalCuotas, 2) }}</div>
                    <div class="info-line"><strong>Fecha de emisión:</strong> {{ $fecha_actual }}</div>
                </td>
                <td style="width: 50%; padding-left: 10px; vertical-align: top;">
                    <div class="info-line"><strong>N° Cuotas:</strong> {{ $prestamo->cuotas }}</div>
                    <div class="info-line"><strong>Tasa de Interés:</strong> {{ number_format($prestamo->interes, 2) }}%</div>
                    <div class="info-line"><strong>Periodicidad:</strong> {{ ucfirst($prestamo->frecuencia) }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="payment-schedule">
        <thead>
            <tr>
                <th>N°</th>
                <th>VENCIMIENTO</th>
                <th>CAPITAL</th>
                <th>INTERÉS</th>
                <th>OTROS</th>
                <th>CUOTA</th>
                <th>ESTADO</th>
            </tr>
        </thead>
        <tbody>
            @php
            $totalCapital = 0;
            $totalInteres = 0;
            $totalOtros = 0;
            $totalCuotas = 0;
            @endphp
            @foreach($cuotas as $cuota)
            @php
            $otros = $cuota->monto - $cuota->capital - $cuota->interes;
            $totalCapital += $cuota->capital;
            $totalInteres += $cuota->interes;
            $totalOtros += $otros;
            $totalCuotas += $cuota->monto;
            @endphp
            <tr>
                <td>{{ $cuota->numero_cuota }}</td>
                <td>{{ \Carbon\Carbon::parse($cuota->fecha_vencimiento)->format('d/m/Y') }}</td>
                <td>{{ number_format($cuota->capital, 2) }}</td>
                <td>{{ number_format($cuota->interes, 2) }}</td>
                <td>{{ number_format($otros, 2) }}</td>
                <td>{{ number_format($cuota->monto, 2) }}</td>
                <td>{{ $cuota->estado }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2">TOTALES</td>
                <td>{{ number_format($totalCapital, 2) }}</td>
                <td>{{ number_format($totalInteres, 2) }}</td>
                <td>{{ number_format($totalOtros, 2) }}</td>
                <td>{{ number_format($totalCuotas, 2) }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>

    <div class="disclaimer">
        EL (LOS) CLIENTE(S) declara(n) expresamente que previamente a la celebración del contrato han recibido la información sobre las condiciones de su crédito (Tasa de interés compensatorio, comisiones y penalidades); copia de título valor, contrato de crédito por lo que firma en señal de conformidad.
    </div>
    <br><br>
    <div class="firma-bloque">
        <div class="firma-linea"></div>
        <p><strong>Nombres y Apellidos:</strong> {{ $datosCliente->nombre ?? '' }} {{ $datosCliente->apellidoPaterno ?? '' }} {{ $datosCliente->apellidoMaterno ?? '' }} {{ $datosCliente->apellidoConyuge ?? '' }}</p>
        <p><strong>DNI:</strong> {{ $datosCliente->dni ?? '---' }}</p>
        <p><strong>Dirección:</strong>
            @if(isset($direcciones['CORRESPONDENCIA']))
            {{ $direcciones['CORRESPONDENCIA']->tipoVia ?? '' }}
            {{ $direcciones['CORRESPONDENCIA']->nombreVia ?? '' }}
            {{ $direcciones['CORRESPONDENCIA']->numeroMz ?? '' }}
            {{ $direcciones['CORRESPONDENCIA']->urbanizacion ?? '' }},
            {{ $direcciones['CORRESPONDENCIA']->distrito ?? '' }},
            {{ $direcciones['CORRESPONDENCIA']->provincia ?? '' }},
            {{ $direcciones['CORRESPONDENCIA']->departamento ?? '' }}
            @else
            ---
            @endif
        </p>
    </div>
    </div>
</body>

</html>