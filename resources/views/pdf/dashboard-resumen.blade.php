<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Informe Ejecutivo del Dashboard — CVAUP Táchira</title>
    <style>
        /*
            DomPDF tiene soporte limitado de CSS. Usamos solo lo que SÍ soporta:
            tablas, márgenes simples, fuentes web seguras, colores HEX, imágenes inline.
            Layout coherente con pdf/reporte.blade.php y pdf/lista-reportes.blade.php
            (mismo cintillo, misma paleta, misma tipografía).
        */
        @page {
            margin: 24px 36px 28px 36px;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9.5pt;
            color: #1E293B;
            line-height: 1.4;
        }

        /* ── HEADER institucional con cintillo ─────────────────────────── */
        .header {
            border-bottom: 2px solid #2E7D32;
            padding-bottom: 8px;
            margin: 0 0 12px 0;
        }
        .header table {
            width: 100%;
            border-collapse: collapse;
        }
        .header td {
            vertical-align: top;
        }
        .header .left {
            width: 70%;
            padding-right: 12px;
        }
        .cintillo {
            width: 100%;
            height: auto;
            display: block;
        }
        .cintillo-fallback {
            font-size: 18pt;
            font-weight: bold;
            color: #1B5E20;
        }
        .subtitle {
            color: #64748B;
            font-size: 9pt;
            margin-top: 6px;
            line-height: 1.35;
        }
        .header .right {
            width: 30%;
            text-align: right;
            font-size: 8.5pt;
            color: #64748B;
            padding-top: 6px;
        }
        .header .right strong {
            color: #1E293B;
        }
        .header .right .meta-line {
            margin-bottom: 4px;
            line-height: 1.35;
        }

        /* ── TÍTULO del informe ────────────────────────────────────────── */
        .titulo-informe {
            font-size: 14pt;
            font-weight: bold;
            color: #1B5E20;
            margin: 4px 0 2px 0;
        }
        .titulo-informe-sub {
            font-size: 9pt;
            color: #64748B;
            margin: 0 0 10px 0;
        }

        /* ── TARJETAS DE STATS ─────────────────────────────────────────── */
        .stats-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px;
            margin-bottom: 12px;
        }
        .stats-grid td {
            width: 25%;
            border: 1px solid #E2E8F0;
            border-radius: 4px;
            padding: 8px 10px;
            background: #F8FAFC;
        }
        .stat-label {
            font-size: 7.5pt;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .stat-value {
            font-size: 18pt;
            font-weight: bold;
            color: #2E7D32;
            line-height: 1;
        }

        /* ── SECCIÓN de gráficos ───────────────────────────────────────── */
        .seccion {
            margin-top: 10px;
        }
        .seccion-titulo {
            font-size: 10pt;
            font-weight: bold;
            color: #1B5E20;
            border-left: 3px solid #2E7D32;
            padding-left: 8px;
            margin: 0 0 6px 0;
        }
        .chart-img {
            width: 100%;
            height: auto;
            display: block;
            border: 1px solid #E2E8F0;
            border-radius: 4px;
            padding: 4px;
            background: #FFFFFF;
        }

        /* Layout 2 columnas para Bar + Doughnut (mismo ancho) */
        .charts-row {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px;
        }
        .charts-row td {
            width: 50%;
            vertical-align: top;
        }
        .chart-img-medio {
            width: 100%;
            height: auto;
            display: block;
            border: 1px solid #E2E8F0;
            border-radius: 4px;
            padding: 4px;
            background: #FFFFFF;
        }

        /* ── SEMÁFORO de cumplimiento (resumen) ────────────────────────── */
        .semaforo-row {
            margin: 4px 0 8px 0;
            font-size: 9pt;
        }
        .pill {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            margin-right: 6px;
            font-size: 8pt;
        }
        .pill-verde { background: #DCFCE7; color: #166534; border: 1px solid #BBF7D0; }
        .pill-amarillo { background: #FEF9C3; color: #854D0E; border: 1px solid #FDE68A; }
        .pill-rojo { background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA; }
        .pill-gris { background: #F1F5F9; color: #475569; border: 1px solid #CBD5E1; }

        /* ── ALERTAS ────────────────────────────────────────────────────── */
        .alertas {
            margin-top: 8px;
        }
        .alerta {
            padding: 6px 10px;
            border-radius: 4px;
            margin-bottom: 4px;
            font-size: 9pt;
        }
        .alerta-danger {
            background: #FEE2E2;
            border-left: 3px solid #DC2626;
            color: #7F1D1D;
        }
        .alerta-warning {
            background: #FEF3C7;
            border-left: 3px solid #F59E0B;
            color: #78350F;
        }
        .alerta strong {
            color: inherit;
        }
        .alertas-empty {
            font-size: 9pt;
            color: #64748B;
            font-style: italic;
            padding: 6px 0;
        }

        /* ── FOOTER ─────────────────────────────────────────────────────── */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 7.5pt;
            color: #94A3B8;
            border-top: 1px solid #E2E8F0;
            padding-top: 4px;
        }
    </style>
</head>
<body>
    @php
        $cintilloPath = public_path('images/cintillo-institucional.png');
        $tieneCintillo = file_exists($cintilloPath);
    @endphp

    {{-- HEADER con cintillo institucional --}}
    <div class="header">
        <table>
            <tr>
                <td class="left">
                    @if ($tieneCintillo)
                        <img src="{{ $cintilloPath }}" alt="CVAUP Táchira" class="cintillo">
                    @else
                        <div class="cintillo-fallback">CVAUP TÁCHIRA</div>
                    @endif
                    <div class="subtitle">
                        Sistema de Reportes Técnicos · Corporación Venezolana de Agricultura Urbana y Periurbana Táchira
                    </div>
                </td>
                <td class="right">
                    <div class="meta-line"><strong>Informe ejecutivo</strong></div>
                    <div class="meta-line">Generado el {{ $fechaGeneracion->format('d/m/Y') }}</div>
                    <div class="meta-line">a las {{ $fechaGeneracion->format('H:i') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <h1 class="titulo-informe">Resumen ejecutivo del Dashboard</h1>
    <p class="titulo-informe-sub">
        Snapshot operativo al {{ $fechaGeneracion->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY') }}
    </p>

    {{-- STATS principales --}}
    <table class="stats-grid">
        <tr>
            <td>
                <div class="stat-label">Técnicos activos</div>
                <div class="stat-value">{{ number_format($stats['tecnicosActivos']) }}</div>
            </td>
            <td>
                <div class="stat-label">Reportes del mes</div>
                <div class="stat-value">{{ number_format($stats['reportesMes']) }}</div>
            </td>
            <td>
                <div class="stat-label">Reportes de la semana</div>
                <div class="stat-value">{{ number_format($stats['reportesSemana']) }}</div>
            </td>
            <td>
                <div class="stat-label">Personas atendidas</div>
                <div class="stat-value">{{ number_format($stats['personasAtendidasMes']) }}</div>
            </td>
        </tr>
    </table>

    {{-- SEMÁFORO resumen --}}
    <div class="seccion">
        <div class="seccion-titulo">Cumplimiento de hoy</div>
        <div class="semaforo-row">
            @if ($esFinDeSemana)
                <span class="pill pill-gris">Fin de semana — no se evalúa</span>
                <span style="color: #64748B; font-size: 8.5pt;">
                    {{ $totalTecnicos }} {{ $totalTecnicos === 1 ? 'técnico activo' : 'técnicos activos' }}
                </span>
            @else
                <span class="pill pill-verde">{{ $resumenSemaforo['verde'] }} completos</span>
                <span class="pill pill-amarillo">{{ $resumenSemaforo['amarillo'] }} incompletos</span>
                <span class="pill pill-rojo">{{ $resumenSemaforo['rojo'] }} sin reportar</span>
                <span style="color: #64748B; font-size: 8.5pt;">
                    de {{ $totalTecnicos }} técnicos activos
                </span>
            @endif
        </div>
    </div>

    {{-- TENDENCIA — gráfico full-width --}}
    <div class="seccion">
        <div class="seccion-titulo">Tendencia de reportes — últimos 30 días</div>
        <img src="{{ $chartTendencia }}" alt="Tendencia 30 días" class="chart-img" style="height: 140px;">
    </div>

    {{-- TOP TÉCNICOS + DISTRIBUCIÓN — 2 columnas --}}
    <div class="seccion">
        <table class="charts-row">
            <tr>
                <td>
                    <div class="seccion-titulo">Top técnicos del mes</div>
                    <img src="{{ $chartTecnicos }}" alt="Top técnicos" class="chart-img-medio" style="height: 140px;">
                </td>
                <td>
                    <div class="seccion-titulo">Distribución por municipio</div>
                    <img src="{{ $chartMunicipios }}" alt="Distribución municipal" class="chart-img-medio" style="height: 140px;">
                </td>
            </tr>
        </table>
    </div>

    {{-- ALERTAS críticas --}}
    <div class="seccion alertas">
        <div class="seccion-titulo">Alertas activas</div>
        @if (count($alertas) === 0)
            <div class="alertas-empty">Sin alertas. Todos los técnicos están al día.</div>
        @else
            @foreach ($alertas as $alerta)
                <div class="alerta {{ $alerta['severidad'] === 'danger' ? 'alerta-danger' : 'alerta-warning' }}">
                    <strong>{{ $alerta['nombre'] }}</strong> —
                    {{ $alerta['dias'] }} días laborables sin reportar.
                    Último: {{ $alerta['ultimo_reporte'] ?? 'Nunca' }}.
                    @if ($alerta['severidad'] === 'danger')
                        <strong>Crítico</strong>
                    @else
                        Aviso
                    @endif
                </div>
            @endforeach
        @endif
    </div>

    <div class="footer">
        Generado el {{ $fechaGeneracion->format('d/m/Y H:i') }} · CVAUP Táchira — Sistema de Reportes Técnicos
    </div>
</body>
</html>
