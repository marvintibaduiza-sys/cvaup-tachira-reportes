<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Listado de Reportes — CVAUP Táchira</title>
    <style>
        @page {
            margin: 24px 36px 32px 36px;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9pt;
            color: #1E293B;
            line-height: 1.4;
        }

        /* ───── Encabezado oficial (mismo patrón del PDF individual para branding consistente) ───── */
        .header {
            border-bottom: 2px solid #2E7D32;
            padding-bottom: 8px;
            margin: 0 0 14px 0;
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
            font-size: 8.5pt;
            margin-top: 6px;
            line-height: 1.35;
        }

        /* ───── Título del documento + filtros aplicados ───── */
        .documento-titulo {
            font-size: 13pt;
            font-weight: bold;
            color: #1E293B;
            margin: 6px 0 4px 0;
        }
        .filtros-aplicados {
            font-size: 8.5pt;
            color: #64748B;
            margin-bottom: 12px;
            line-height: 1.5;
        }
        .filtros-aplicados strong {
            color: #1E293B;
        }
        .filtros-aplicados .pill {
            display: inline-block;
            background: #F1F5F9;
            border: 1px solid #E2E8F0;
            padding: 2px 8px;
            border-radius: 10px;
            margin-right: 4px;
            margin-bottom: 3px;
            color: #475569;
            font-size: 8pt;
        }

        /* ───── Tabla de reportes ───── */
        .tabla-reportes {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
            margin-bottom: 14px;
        }
        .tabla-reportes thead th {
            background: #1B5E20;
            color: #fff;
            padding: 6px 5px;
            text-align: left;
            font-weight: bold;
            font-size: 8pt;
            border: 1px solid #1B5E20;
        }
        .tabla-reportes tbody td {
            padding: 5px;
            border: 1px solid #E2E8F0;
            vertical-align: top;
            font-weight: 400;
        }
        .tabla-reportes tbody tr:nth-child(even) td {
            background: #F8FAFC;
        }
        .tabla-reportes .num {
            text-align: center;
            white-space: nowrap;
        }
        .tabla-reportes .badge {
            display: inline-block;
            padding: 1px 5px;
            border-radius: 8px;
            font-size: 7pt;
            font-weight: bold;
            border: 1px solid;
        }
        .tabla-reportes .badge-completo { background: #E8F5E9; color: #2E7D32; border-color: #A5D6A7; }
        .tabla-reportes .badge-incompleto { background: #FFF8E1; color: #F57F17; border-color: #FFE082; }
        .tabla-reportes .badge-borrador { background: #ECEFF1; color: #546E7A; border-color: #CFD8DC; }

        /* ───── Resumen estadístico al final ───── */
        .resumen {
            margin-top: 16px;
            page-break-inside: avoid;
        }
        .resumen h2 {
            font-size: 11pt;
            font-weight: bold;
            color: #1B5E20;
            background: #F1F5F9;
            padding: 5px 10px;
            margin: 0 0 8px 0;
            border-left: 3px solid #2E7D32;
        }
        .resumen-grid {
            width: 100%;
            border-collapse: collapse;
        }
        .resumen-grid td {
            text-align: center;
            padding: 8px 4px;
            border: 1px solid #E2E8F0;
            background: #FAFAFA;
            width: 25%;
        }
        .resumen-grid .num {
            font-size: 14pt;
            font-weight: bold;
            color: #1B5E20;
        }
        .resumen-grid .lbl {
            font-size: 7.5pt;
            color: #64748B;
            margin-top: 2px;
        }
        .resumen-detalle {
            margin-top: 10px;
            font-size: 8.5pt;
            color: #475569;
            background: #F8FAFC;
            padding: 8px 10px;
            border-left: 3px solid #2E7D32;
        }
        .resumen-detalle strong {
            color: #1E293B;
        }

        /* ───── Comparativa con período anterior (Fase 12.2) ───── */
        .comparativa {
            margin-top: 18px;
            page-break-inside: avoid;
        }
        .comparativa h2 {
            font-size: 11pt;
            font-weight: bold;
            color: #1B5E20;
            background: #F1F5F9;
            padding: 5px 10px;
            margin: 0 0 8px 0;
            border-left: 3px solid #2E7D32;
        }
        .comparativa-periodos {
            background: #F8FAFC;
            padding: 8px 10px;
            font-size: 8.5pt;
            color: #475569;
            border: 1px solid #E2E8F0;
            margin-bottom: 8px;
        }
        .comparativa-periodos strong {
            color: #1E293B;
        }
        .comparativa-tabla {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
        }
        .comparativa-tabla thead th {
            background: #1B5E20;
            color: #fff;
            padding: 5px 8px;
            text-align: left;
            font-weight: bold;
            font-size: 8pt;
            border: 1px solid #1B5E20;
        }
        .comparativa-tabla tbody td {
            padding: 5px 8px;
            border: 1px solid #E2E8F0;
            font-weight: 400;
        }
        .comparativa-tabla tbody tr:nth-child(even) td {
            background: #F8FAFC;
        }
        .comparativa-tabla .num {
            text-align: right;
            font-weight: 400;
        }
        .comparativa-tabla .delta {
            text-align: right;
            font-weight: bold;
            white-space: nowrap;
        }
        /* Colores según dirección del delta */
        .comparativa-tabla .delta-up   { color: #2E7D32; }   /* verde — subió */
        .comparativa-tabla .delta-down { color: #C62828; }   /* rojo — bajó */
        .comparativa-tabla .delta-equal{ color: #64748B; }   /* gris — igual */

        /* ───── Resumen gráfico (Fase 12.3) — CSS bar charts ───── */
        .graficas-page {
            page-break-before: always;
        }
        .graficas-titulo {
            font-size: 14pt;
            font-weight: bold;
            color: #1B5E20;
            margin: 0 0 4px 0;
            border-bottom: 2px solid #2E7D32;
            padding-bottom: 4px;
        }
        .graficas-subtitulo {
            font-size: 8.5pt;
            color: #64748B;
            margin-bottom: 14px;
        }
        .grafica {
            margin-bottom: 18px;
            page-break-inside: avoid;
        }
        .grafica h3 {
            font-size: 10pt;
            font-weight: bold;
            color: #1B5E20;
            background: #F1F5F9;
            padding: 5px 10px;
            margin: 0 0 8px 0;
            border-left: 3px solid #2E7D32;
        }
        .bars-table {
            width: 100%;
            border-collapse: collapse;
        }
        .bars-table td {
            padding: 3px 4px;
            vertical-align: middle;
            font-size: 8.5pt;
            font-weight: 400;
        }
        .bars-table .bar-label {
            width: 32%;
            color: #1E293B;
            padding-right: 8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .bars-table .bar-track {
            width: 60%;
            background: #F1F5F9;
            border-radius: 3px;
            position: relative;
            height: 16px;
            padding: 0;
        }
        .bars-table .bar-fill {
            background: #2E7D32;
            height: 16px;
            border-radius: 3px;
            min-width: 1px;
        }
        .bars-table .bar-value {
            width: 8%;
            text-align: right;
            color: #1E293B;
            font-weight: bold;
            padding-left: 6px;
            white-space: nowrap;
        }
        .grafica-vacia {
            text-align: center;
            padding: 12px;
            color: #94A3B8;
            font-size: 8.5pt;
            background: #F8FAFC;
            border: 1px dashed #CBD5E1;
            border-radius: 4px;
        }

        /* ───── Estado vacío ───── */
        .vacio {
            text-align: center;
            padding: 40px 20px;
            color: #94A3B8;
            background: #F8FAFC;
            border: 1px dashed #CBD5E1;
            border-radius: 6px;
        }

        /* ───── Pie de página ───── */
        .footer {
            position: fixed;
            bottom: -18px;
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
    {{-- Encabezado: cintillo + metadata --}}
    @php $cintilloPath = public_path('images/cintillo-institucional.png'); @endphp
    <div class="header">
        <table>
            <tr>
                <td class="left">
                    @if (file_exists($cintilloPath))
                        <img src="{{ $cintilloPath }}" alt="República Bolivariana de Venezuela · Ministerio del Poder Popular para las Comunas · CVAUP" class="cintillo">
                    @else
                        <div class="cintillo-fallback">CVAUP TÁCHIRA</div>
                    @endif
                    <div class="subtitle">
                        Sistema de Reportes Técnicos · Corporación Venezolana de Agricultura Urbana y Periurbana Táchira
                    </div>
                </td>
                <td class="right">
                    <div class="meta-line"><strong>Listado de Reportes</strong></div>
                    <div class="meta-line">Generado: <strong>{{ now()->format('d/m/Y H:i') }}</strong></div>
                    <div class="meta-line">Total: <strong>{{ $totalReportes }}</strong> reporte(s)</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Título y filtros aplicados (subtítulo dinámico) --}}
    <div class="documento-titulo">Listado de Reportes Técnicos</div>
    <div class="filtros-aplicados">
        @if (count($filtrosLegibles) === 0)
            <em>Sin filtros aplicados — listado completo de todos los reportes registrados.</em>
        @else
            <strong>Filtrado por:</strong>
            @foreach ($filtrosLegibles as $f)
                <span class="pill">{{ $f }}</span>
            @endforeach
        @endif
    </div>

    {{-- Tabla de reportes o estado vacío --}}
    @if ($reportes->count() === 0)
        <div class="vacio">
            <p><strong>No se encontraron reportes que coincidan con los filtros aplicados.</strong></p>
            <p style="font-size: 8pt; margin-top: 6px;">
                Ajusta los filtros y vuelve a generar el reporte.
            </p>
        </div>
    @else
        <table class="tabla-reportes">
            <thead>
                <tr>
                    <th style="width: 8%;">Fecha</th>
                    <th style="width: 18%;">Técnico</th>
                    <th style="width: 14%;">Municipio</th>
                    <th style="width: 16%;">Consejo Comunal</th>
                    <th style="width: 24%;">Actividad</th>
                    <th style="width: 8%;" class="num">Atendidas</th>
                    <th style="width: 4%;" class="num">Fotos</th>
                    <th style="width: 8%;" class="num">Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($reportes as $r)
                    @php
                        $estadoClase = "badge-{$r->estado_reporte}";
                        // BLOQUE 5: cuántos territorios adicionales atendió en esta jornada
                        $extraComunas = $r->relationLoaded('comunasAdicionales') ? $r->comunasAdicionales->count() : 0;
                        $extraCCs = $r->relationLoaded('consejosComunalesAdicionales') ? $r->consejosComunalesAdicionales->count() : 0;
                    @endphp
                    <tr>
                        <td>{{ $r->fecha?->format('d/m/Y') ?? '—' }}</td>
                        <td>
                            {{-- BLOQUE 4: técnico + documento institucional (V-12345678) en línea secundaria --}}
                            {{ $r->tecnico?->nombre_apellido ?? '—' }}
                            @if ($r->tecnico?->documento_completo)
                                <div style="font-size: 7pt; color: #94A3B8; margin-top: 2px;">
                                    {{ $r->tecnico->documento_completo }}
                                </div>
                            @endif
                        </td>
                        <td>{{ $r->municipio?->nombre ?? '—' }}</td>
                        <td>
                            {{ $r->consejoComunal?->nombre ?? '—' }}
                            {{-- BLOQUE 5: indicador "+N" si hay comunas/CCs adicionales atendidos --}}
                            @if ($extraComunas > 0 || $extraCCs > 0)
                                @php
                                    $partes = [];
                                    if ($extraCCs > 0)     $partes[] = '+' . $extraCCs . ' CC' . ($extraCCs > 1 ? 's' : '');
                                    if ($extraComunas > 0) $partes[] = '+' . $extraComunas . ' comuna' . ($extraComunas > 1 ? 's' : '');
                                @endphp
                                <div style="font-size: 7pt; color: #2E7D32; margin-top: 2px;">
                                    {{ implode(' · ', $partes) }}
                                </div>
                            @endif
                        </td>
                        <td>{{ $r->titulo_actividad }}</td>
                        <td class="num">{{ $r->cantidad_personas_atendidas ?? '—' }}</td>
                        <td class="num">{{ $r->fotos->count() }}</td>
                        <td class="num"><span class="badge {{ $estadoClase }}">{{ ucfirst($r->estado_reporte) }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Resumen estadístico --}}
        <div class="resumen">
            <h2>Resumen estadístico</h2>
            <table class="resumen-grid">
                <tr>
                    <td>
                        <div class="num">{{ $estadisticas['total'] }}</div>
                        <div class="lbl">Total reportes</div>
                    </td>
                    <td>
                        <div class="num">{{ number_format($estadisticas['total_personas_atendidas']) }}</div>
                        <div class="lbl">Personas atendidas</div>
                    </td>
                    <td>
                        <div class="num">{{ number_format($estadisticas['total_participantes_acreditados']) }}</div>
                        <div class="lbl">Participantes acreditados</div>
                    </td>
                    <td>
                        <div class="num">{{ number_format($estadisticas['total_personas_a_beneficiar']) }}</div>
                        <div class="lbl">Personas a beneficiar</div>
                    </td>
                </tr>
            </table>

            <div class="resumen-detalle">
                <strong>Distribución por estado:</strong>
                {{ $estadisticas['completos'] }} completos
                · {{ $estadisticas['incompletos'] }} incompletos
                · {{ $estadisticas['borradores'] }} borradores
                <br>
                <strong>Cobertura:</strong>
                {{ $estadisticas['tecnicos_distintos'] }} técnico(s) distinto(s)
                · {{ $estadisticas['municipios_distintos'] }} municipio(s) distinto(s)
            </div>
        </div>

        {{-- ───── Comparativa con período anterior (Fase 12.2) ───── --}}
        @if (!empty($comparativa))
            @php
                $metricas = [
                    'total' => 'Total reportes',
                    'completos' => 'Reportes completos',
                    'incompletos' => 'Reportes incompletos',
                    'borradores' => 'Reportes borradores',
                    'total_personas_atendidas' => 'Personas atendidas',
                    'total_personas_a_beneficiar' => 'Personas a beneficiar',
                    'total_participantes_acreditados' => 'Participantes acreditados',
                    'tecnicos_distintos' => 'Técnicos distintos',
                    'municipios_distintos' => 'Municipios distintos',
                ];
            @endphp
            <div class="comparativa">
                <h2>Comparativa con período anterior</h2>
                <div class="comparativa-periodos">
                    <strong>Período actual:</strong> {{ $comparativa['periodo_actual']['desde'] }} al {{ $comparativa['periodo_actual']['hasta'] }}
                    &nbsp;·&nbsp;
                    <strong>Período anterior:</strong> {{ $comparativa['periodo_anterior']['desde'] }} al {{ $comparativa['periodo_anterior']['hasta'] }}
                </div>

                <table class="comparativa-tabla">
                    <thead>
                        <tr>
                            <th style="width: 50%;">Métrica</th>
                            <th style="width: 18%; text-align: right;">Período actual</th>
                            <th style="width: 18%; text-align: right;">Período anterior</th>
                            <th style="width: 14%; text-align: right;">Variación</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($metricas as $key => $label)
                            @php
                                $delta = $comparativa['deltas'][$key] ?? null;
                                $arrow = match ($delta['direction'] ?? 'equal') {
                                    'up' => '▲',
                                    'down' => '▼',
                                    default => '—',
                                };
                                $cls = 'delta-' . ($delta['direction'] ?? 'equal');
                            @endphp
                            <tr>
                                <td>{{ $label }}</td>
                                <td class="num">{{ number_format($comparativa['periodo_actual']['estadisticas'][$key] ?? 0) }}</td>
                                <td class="num">{{ number_format($comparativa['periodo_anterior']['estadisticas'][$key] ?? 0) }}</td>
                                <td class="delta {{ $cls }}">{{ $arrow }} {{ $delta['label'] ?? '0%' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif

    {{-- ───── Página final: Resumen gráfico (Fase 12.3) ───── --}}
    @if (!empty($graficas) && $reportes->count() > 0)
        @php
            // Helper inline: calcular el % de ancho de la barra basado en el valor máximo del dataset
            $renderBarras = function (array $dataset, ?string $colorOverride = null) {
                if (empty($dataset)) return '<div class="grafica-vacia">Sin datos para graficar.</div>';
                $max = max(array_column($dataset, 'value')) ?: 1;
                $html = '<table class="bars-table">';
                foreach ($dataset as $row) {
                    $pct = max(2, round(($row['value'] / $max) * 100, 1)); // mínimo 2% para que se vea aunque sea 1
                    $color = $colorOverride ?? ($row['color'] ?? '#2E7D32');
                    $label = htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8');
                    $value = number_format($row['value']);
                    $html .= "<tr>
                        <td class=\"bar-label\" title=\"{$label}\">{$label}</td>
                        <td class=\"bar-track\"><div class=\"bar-fill\" style=\"width: {$pct}%; background: {$color};\"></div></td>
                        <td class=\"bar-value\">{$value}</td>
                    </tr>";
                }
                $html .= '</table>';
                return $html;
            };
        @endphp

        <div class="graficas-page">
            <div class="graficas-titulo">Resumen Visual</div>
            <div class="graficas-subtitulo">
                Distribución gráfica de los {{ $totalReportes }} reportes incluidos en este listado.
                @if (count($filtrosLegibles) > 0)
                    Filtros aplicados: {{ implode(' · ', $filtrosLegibles) }}.
                @endif
            </div>

            {{-- Top técnicos --}}
            <div class="grafica">
                <h3>Top técnicos por cantidad de reportes</h3>
                {!! $renderBarras($graficas['por_tecnico']) !!}
            </div>

            {{-- Top municipios --}}
            <div class="grafica">
                <h3>Top municipios atendidos</h3>
                {!! $renderBarras($graficas['por_municipio']) !!}
            </div>

            {{-- Distribución por estado (cada barra con su propio color semántico) --}}
            <div class="grafica">
                <h3>Distribución por estado del reporte</h3>
                {!! $renderBarras($graficas['por_estado']) !!}
            </div>

            {{-- Reportes por día --}}
            <div class="grafica">
                <h3>Actividad por día (últimas 14 fechas con reportes)</h3>
                {!! $renderBarras($graficas['por_dia']) !!}
            </div>
        </div>
    @endif

    {{-- Pie --}}
    <div class="footer">
        Generado el {{ now()->format('d/m/Y H:i') }} · CVAUP Táchira — Sistema de Reportes Técnicos · página
    </div>
</body>
</html>
