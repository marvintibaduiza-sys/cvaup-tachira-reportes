<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte #{{ $reporte->id }} — CVAUP Táchira</title>
    <style>
        /* DomPDF tiene soporte limitado de CSS — usamos lo que SÍ soporta:
           tablas, márgenes simples, fuentes web seguras, colores HEX. */
        @page {
            /* Único punto donde se define el margen horizontal —
               garantiza que cintillo, subtítulo y cuerpo arranquen TODOS en la misma línea X. */
            margin: 24px 36px 28px 36px;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10pt;
            color: #1E293B;
            line-height: 1.4;
        }
        .header {
            border-bottom: 2px solid #2E7D32;
            padding-bottom: 8px;
            margin: 0 0 14px 0; /* sin márgenes laterales — los maneja @page para alinear con el cuerpo */
        }
        .header table {
            width: 100%;
            border-collapse: collapse;
        }
        /* TOP-aligned para que el borde superior del cintillo y el de la metadata coincidan
           (con `middle` la metadata caía al centro del bloque izquierdo y se veía regado) */
        .header td {
            vertical-align: top;
        }
        /* Columna izquierda: cintillo institucional + subtítulo del sistema */
        .header .left {
            width: 70%;
            padding-right: 12px;
        }
        /* Cintillo institucional oficial (sustituye al título verde "CVAUP TÁCHIRA") */
        .cintillo {
            width: 100%;
            height: auto;
            display: block;
        }
        /* Fallback si el archivo del cintillo no existe en disco */
        .cintillo-fallback {
            font-size: 18pt;
            font-weight: bold;
            color: #1B5E20;
        }
        /* Subtítulo institucional debajo del cintillo */
        .subtitle {
            color: #64748B;
            font-size: 9pt;
            margin-top: 6px;
            line-height: 1.35;
        }
        /* Columna derecha: metadata del reporte específico */
        .header .right {
            width: 30%;
            text-align: right;
            font-size: 9pt;
            color: #64748B;
            /* Pequeño padding-top compensa el margen óptico interno del PNG del cintillo
               (la imagen tiene unos 5-6px de blanco arriba) y baja la 1ª línea para alinearse
               visualmente con el centro óptico del logo de la bandera */
            padding-top: 6px;
        }
        .header .right strong {
            color: #1E293B;
        }
        .header .right .meta-line {
            margin-bottom: 4px;
            line-height: 1.35;
        }
        .titulo {
            font-size: 14pt;
            font-weight: bold;
            color: #1E293B;
            margin: 10px 0 4px 0;
        }
        .meta {
            font-size: 8.5pt;
            color: #64748B;
            margin-bottom: 12px;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 8pt;
            font-weight: bold;
            border: 1px solid;
        }
        .badge-completo { background: #E8F5E9; color: #2E7D32; border-color: #A5D6A7; }
        .badge-incompleto { background: #FFF8E1; color: #F57F17; border-color: #FFE082; }
        .badge-borrador { background: #ECEFF1; color: #546E7A; border-color: #CFD8DC; }
        .section {
            margin-top: 14px;
            page-break-inside: avoid;
        }
        .section h2 {
            font-size: 10.5pt;
            font-weight: bold;
            color: #1B5E20;
            background: #F1F5F9;
            padding: 4px 8px;
            margin: 0 0 6px 0;
            border-left: 3px solid #2E7D32;
        }
        .data-grid {
            width: 100%;
            border-collapse: collapse;
        }
        .data-grid td {
            padding: 4px 8px;
            vertical-align: top;
            border-bottom: 1px solid #F1F5F9;
            font-size: 8.5pt;
            font-weight: 400; /* fuerza Regular — DejaVu Sans solo tiene 400 y 700 */
        }
        .data-grid .label {
            color: #64748B;
            width: 35%;
        }
        .data-grid .value {
            color: #1E293B;
            /* sin font-weight — hereda 400 de la regla padre (.data-grid td);
               la jerarquía visual la da el contraste de COLOR (#1E293B vs #64748B), no el peso. */
        }
        .ubicacion {
            font-size: 9pt;
            background: #F8FAFC;
            padding: 6px 10px;
            border-radius: 4px;
            border: 1px solid #E2E8F0;
        }
        .ubicacion strong {
            color: #1B5E20;
        }
        .metricas {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }
        .metricas td {
            text-align: center;
            padding: 8px 4px;
            border: 1px solid #E2E8F0;
            background: #FAFAFA;
            width: 25%;
        }
        .metricas .num {
            font-size: 14pt;
            font-weight: bold;
            color: #1B5E20;
        }
        .metricas .lbl {
            font-size: 7.5pt;
            color: #64748B;
            margin-top: 2px;
        }
        .resumen {
            background: #F8FAFC;
            padding: 8px 10px;
            border-left: 3px solid #2E7D32;
            font-size: 9pt;
            white-space: pre-wrap;
        }
        .fotos-grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        .fotos-grid td {
            width: 33.333%;
            padding: 4px;
            vertical-align: top;
            text-align: center;
        }
        .fotos-grid img {
            max-width: 100%;
            max-height: 220px;
            border: 1px solid #CBD5E1;
        }
        .fotos-grid .caption {
            font-size: 7pt;
            color: #94A3B8;
            margin-top: 2px;
        }
        .footer {
            position: fixed;
            bottom: -15px;
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
    {{-- Encabezado: layout de 2 columnas — cintillo+subtítulo a la izquierda, metadata a la derecha --}}
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
                    <div class="meta-line"><strong>Reporte N°</strong> {{ str_pad($reporte->id, 6, '0', STR_PAD_LEFT) }}</div>
                    <div class="meta-line">Fecha del reporte: <strong>{{ $reporte->fecha->format('d/m/Y') }}</strong></div>
                    <div>
                        @php $estadoClase = "badge-{$reporte->estado_reporte}"; @endphp
                        <span class="badge {{ $estadoClase }}">{{ ucfirst($reporte->estado_reporte) }}</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="titulo">{{ $reporte->tipo_actividad ?: 'Reporte' }}</div>
    <div class="meta">
        Fecha del reporte: <strong>{{ $reporte->fecha?->format('d/m/Y') ?? '—' }}</strong>
    </div>

    {{-- Técnico responsable — BLOQUE 4 + 4.5: estructura institucional completa --}}
    @if ($reporte->tecnico)
    <div class="section">
        <h2>Técnico responsable</h2>
        <table class="data-grid">
            <tr>
                <td class="label">Nombre</td>
                <td class="value">{{ $reporte->tecnico->nombre }}</td>
            </tr>
            <tr>
                <td class="label">Apellido</td>
                <td class="value">{{ $reporte->tecnico->apellido }}</td>
            </tr>
            <tr>
                <td class="label">Documento de identidad</td>
                <td class="value">{{ $reporte->tecnico->documento_completo }}</td>
            </tr>
            @php
                $especialidadesTecnico = is_array($reporte->tecnico->especialidades)
                    ? $reporte->tecnico->especialidades
                    : [];
            @endphp
            @if (count($especialidadesTecnico) > 0)
            <tr>
                <td class="label">Especialidades ({{ count($especialidadesTecnico) }})</td>
                <td class="value">{{ implode(', ', $especialidadesTecnico) }}</td>
            </tr>
            @endif
        </table>
    </div>
    @endif

    {{-- Ubicación principal + adicionales atendidos en la jornada (BLOQUE 5) --}}
    <div class="section">
        <h2>Ubicación</h2>
        <div class="ubicacion">
            <strong>Estado:</strong> Táchira
            &nbsp;›&nbsp;
            <strong>Municipio:</strong> {{ $reporte->municipio?->nombre ?? '—' }}
            &nbsp;›&nbsp;
            <strong>Parroquia:</strong> {{ $reporte->parroquia?->nombre ?? '—' }}
            <br>
            <strong>Comuna:</strong> {{ $reporte->comuna?->nombre ?? '—' }}
            &nbsp;›&nbsp;
            <strong>Consejo Comunal sede:</strong> {{ $reporte->consejoComunal?->nombre ?? '—' }}
            @if ($reporte->lugar)
                <br><strong>Lugar específico de la actividad:</strong> {{ $reporte->lugar }}
            @endif
        </div>

        {{-- BLOQUE 5: comunas adicionales atendidas en la misma jornada --}}
        @if ($reporte->comunasAdicionales->count() > 0)
        <div class="ubicacion" style="margin-top: 6px; background: #FAFAFA;">
            <strong>Otras comunas atendidas ({{ $reporte->comunasAdicionales->count() }}):</strong>
            <span style="color: #475569;">
                @foreach ($reporte->comunasAdicionales as $c)
                    {{ $c->nombre }}<span style="color: #94A3B8;"> — {{ $c->parroquia?->municipio?->nombre ?? '?' }}</span>{{ !$loop->last ? ', ' : '' }}
                @endforeach
            </span>
        </div>
        @endif

        {{-- BLOQUE 5: consejos comunales adicionales atendidos --}}
        @if ($reporte->consejosComunalesAdicionales->count() > 0)
        <div class="ubicacion" style="margin-top: 6px; background: #FAFAFA;">
            <strong>Otros consejos comunales atendidos ({{ $reporte->consejosComunalesAdicionales->count() }}):</strong>
            <span style="color: #475569;">
                @foreach ($reporte->consejosComunalesAdicionales as $cc)
                    {{ $cc->nombre }}<span style="color: #94A3B8;"> — {{ $cc->comuna?->nombre ?? '?' }}</span>{{ !$loop->last ? ', ' : '' }}
                @endforeach
            </span>
        </div>
        @endif
    </div>

    {{-- Métricas --}}
    <div class="section">
        <h2>Métricas y atención</h2>
        <table class="metricas">
            <tr>
                <td>
                    <div class="num">{{ $reporte->cantidad_personas_atendidas ?? '—' }}</div>
                    <div class="lbl">Personas atendidas</div>
                </td>
                <td>
                    <div class="num">{{ $reporte->cantidad_personas_a_beneficiar ?? '—' }}</div>
                    <div class="lbl">Personas a beneficiar</div>
                </td>
                <td>
                    <div class="num">{{ $reporte->cantidad_comunas_atendidas ?? '—' }}</div>
                    <div class="lbl">Total comunas atendidas</div>
                </td>
                <td>
                    <div class="num">{{ $reporte->cantidad_consejos_comunales_atendidos ?? '—' }}</div>
                    <div class="lbl">Total consejos atendidos</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Actividad realizada — BLOQUE 8 simplificación --}}
    <div class="section">
        <h2>Actividad realizada</h2>
        <table class="data-grid">
            <tr>
                <td class="label">Tipo de actividad</td>
                <td class="value">{{ $reporte->tipo_actividad ?: '—' }}</td>
            </tr>
            <tr>
                <td class="label">Descripción</td>
                <td class="value">{{ $reporte->descripcion_actividad ?: '—' }}</td>
            </tr>
        </table>
    </div>

    {{-- Constancia fotográfica --}}
    @if ($reporte->fotos->count() > 0)
    <div class="section">
        <h2>Constancia fotográfica</h2>
        <table class="fotos-grid">
            <tr>
                @foreach ($reporte->fotos as $foto)
                    <td>
                        @if ($foto->absolute_path && file_exists($foto->absolute_path))
                            <img src="{{ $foto->absolute_path }}" alt="Foto {{ $foto->orden }}">
                            <div class="caption">Foto {{ $foto->orden }}</div>
                        @else
                            <div class="caption">[Imagen no disponible]</div>
                        @endif
                    </td>
                @endforeach
            </tr>
        </table>
    </div>
    @endif

    {{-- Footer --}}
    <div class="footer">
        Generado el {{ now()->format('d/m/Y H:i') }} · CVAUP Táchira — Sistema de Reportes Técnicos
    </div>
</body>
</html>
