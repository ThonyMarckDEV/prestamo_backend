<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=58mm, initial-scale=1.0">
    <title>Comprobante de Pago</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        @page {
            size: 58mm auto;
            margin: 1mm 1mm;
        }

        html,
        body {
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Roboto', Arial, Verdana, sans-serif;
            width: 47mm;
            max-width: 47mm;
            padding: 2mm;
            font-size: 7.5px;
            line-height: 1.2;
            color: #111;
            background: #fff;
        }

        @media print {
            html,
            body,
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }

        .header {
            text-align: center;
            margin-bottom: 5px;
            border-bottom: 1px dashed #000;
            padding-bottom: 3px;
        }

        .logo {
            max-width: 45mm;
            max-height: 15mm;
            display: block;
            margin: 0 auto 3px;
        }

        .title {
            font-size: 10px;
            font-weight: bold;
            margin: 2px 0;
        }

        .subtitle {
            font-size: 8px;
            margin: 0 0;
            word-wrap: break-word;
        }

        .bold {
            font-weight: bold;
        }

        .content {
            margin-bottom: 5px;
        }

        .section {
            margin-bottom: 5px;
            border-bottom: 1px dashed #000;
            padding-bottom: 3px;
        }

        .section-title {
            font-weight: bold;
            font-size: 8px;
            text-align: center;
            margin-bottom: 2px;
        }

        .item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1.5px;
            word-wrap: break-word;
        }

        .label {
            font-weight: bold;
            flex: 0 0 46%;
            color: #222;
        }

        .value {
            flex: 0 0 54%;
            text-align: right;
            color: #222;
            word-break: break-all;
        }

        .footer {
            margin-top: 5px;
            text-align: center;
            font-size: 5.5px;
            padding-top: 3px;
            border-top: 1px dashed #000;
            color: #444;
        }

        .centered {
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        @php
            $path = storage_path('app/public/img/logonegro.png');
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

        <div class="centered">
            <img src="{{ $base64 }}" alt="Logotipo Fic Sullana" class="logo">
        </div>
        <div class="subtitle">CORPORACIÓN E INVERSIONES</div>
        <div class="subtitle">PRESTAMO DEMO</div>
        <div class="subtitle bold">COMPROBANTE DE PAGO</div>
    </div>

    <div class="content">
        <div class="section">
            <div class="item">
                <span class="label">Registrado:</span>
                <span class="value">{{ $usuario->username }}</span>
            </div>
            <div class="item">
                <span class="label">Fecha:</span>
                <span class="value">{{ $fecha }}</span>
            </div>
            <div class="item">
                <span class="label">Hora:</span>
                <span class="value">{{ $hora }}</span>
            </div>
            <div class="item">
                <span class="label">N° Operación:</span>
                <span class="value">{{ str_pad($pago->idPago, 6, '0', STR_PAD_LEFT) }}</span>
            </div>
            <div class="item">
                <span class="label">N° Ope. Abo.:</span>
                <span class="value">{{ $pago->numero_operacion ?? 'N/A' }}</span>
            </div>
        </div>
        <div class="section">
            <div class="item">
                <span class="label">N° Préstamo:</span>
                <span class="value">{{ str_pad($prestamo->idPrestamo, 6, '0', STR_PAD_LEFT) }}</span>
            </div>
            <div class="item">
                <span class="label">Cliente:</span>
                <span class="value">{{ Str::limit($datosCliente->nombre . ' ' . $datosCliente->apellidoPaterno . ' ' . $datosCliente->apellidoMaterno . ' ' . $datosCliente->apellidoConyuge, 40) }}</span>
            </div>
            <div class="item">
                <span class="label">DNI:</span>
                <span class="value">{{ $datosCliente->dni }}</span>
            </div>
            <div class="item">
                <span class="label">Monto:</span>
                <span class="value bold">S/ {{ number_format($pago->monto_pagado, 2) }}</span>
            </div>
            <div class="item">
                <span class="label">Mora:</span>
                <span class="value">
                    S/ {{ number_format($mora, 2) }}
                    @if ($reduccion_mora_aplicada)
                        (-{{ $mora_reducida }}%)
                    @endif
                </span>
            </div>
            <div class="item">
                <span class="label">Fec. Venc.:</span>
                <span class="value">{{ \Carbon\Carbon::parse($cuota->fecha_vencimiento)->format('d/m/Y') }}</span>
            </div>
            <div class="item">
                <span class="label">Cuota:</span>
                <span class="value bold">{{ $cuota->numero_cuota }} de {{ $prestamo->cuotas }}</span>
            </div>
        </div>
        <div class="section">
            <div class="section-title">SOLIDEZ Y CONFIANZA</div>
        </div>
    </div>
    <div class="footer">
        <p>Este comprobante certifica el pago realizado.</p>
        <p>Conserve este documento para cualquier consulta.</p>
    </div>
</body>
</html>
