<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Listado de Ubicaciones — CVAUP Táchira</title>
    <style>
        @page { margin: 24px 36px 32px 36px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9pt;
            color: #1E293B;
            line-height: 1.4;
        }

        /* Header oficial — mismo patrón del PDF de reportes para branding consistente */
        .header {
            border-bottom: 2px solid #2E7D32;
            padding-bottom: 8px;
            margin: 0 0 14px 0;
        }
        .header table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; }
        .header .left { width: 70%; padding-right: 12px; }
        .header .right {
            width: 30%;
            text-align: right;
            font-size: 8.5pt;
            color: #64748B;
            padding-top: 6px;
        }
        .header .right strong { color: #1E293B; }
        .header .right .meta-line { margin-bottom: 4px; line-height: 1.35; }
        .cintillo { width: 100%; height: auto; display: block; }
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
        .filtros-aplicados strong { color: #1E293B; }
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

        /* Tabla */
        .tabla {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
        }
        .tabla thead th {
            background: #1B5E20;
            color: #fff;
            padding: 6px 5px;
            text-align: left;
            font-weight: bold;
            font-size: 8pt;
            border: 1px solid #1B5E20;
        }
        .tabla tbody td {
            padding: 5px;
            border: 1px solid #E2E8F0;
            vertical-align: top;
            font-weight: 400;
        }
        .tabla tbody tr:nth-child(even) td {
            background: #F8FAFC;
        }
        .tabla .num {
            text-align: center;
            white-space: nowrap;
        }

        /* Resumen */
        .resumen {
            margin-top: 14px;
            background: #F8FAFC;
            padding: 8px 10px;
            border-left: 3px solid #2E7D32;
            font-size: 8.5pt;
            color: #475569;
        }
        .resumen strong { color: #1E293B; }

        .vacio {
            text-align: center;
            padding: 40px 20px;
            color: #94A3B8;
            background: #F8FAFC;
            border: 1px dashed #CBD5E1;
            border-radius: 6px;
        }

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
    {{-- Header con cintillo institucional --}}
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
                    <div class="meta-line"><strong>Listado de Ubicaciones</strong></div>
                    <div class="meta-line">Generado: <strong>{{ now()->format('d/m/Y H:i') }}</strong></div>
                    <div class="meta-line">Total: <strong>{{ $totalFilas }}</strong> registro(s)</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="documento-titulo">Listado de Ubicaciones</div>
    <div class="filtros-aplicados">
        @if (count($filtrosLegibles) === 0)
            <em>Sin filtros aplicados — listado completo de toda la jerarquía: Estado › Municipio › Parroquia › Comuna › Consejo Comunal.</em>
        @else
            <strong>Filtrado por:</strong>
            @foreach ($filtrosLegibles as $f)
                <span class="pill">{{ $f }}</span>
            @endforeach
        @endif
    </div>

    {{-- Tabla --}}
    @if ($rows->count() === 0)
        <div class="vacio">
            <p><strong>No se encontraron ubicaciones con los filtros aplicados.</strong></p>
        </div>
    @else
        <table class="tabla">
            <thead>
                <tr>
                    <th style="width: 8%;">Estado</th>
                    <th style="width: 17%;">Municipio</th>
                    <th style="width: 17%;">Parroquia</th>
                    <th style="width: 22%;">Comuna</th>
                    <th style="width: 28%;">Consejo Comunal</th>
                    <th style="width: 8%;" class="num">Reportes</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $r)
                    <tr>
                        <td>{{ $r['estado'] ?? 'Táchira' }}</td>
                        <td>{{ $r['municipio'] ?? '—' }}</td>
                        <td>{{ $r['parroquia'] ?? '—' }}</td>
                        <td>{{ $r['comuna'] ?? '—' }}</td>
                        <td>{{ $r['consejo'] ?? '—' }}</td>
                        <td class="num">{{ $r['reportes_count'] ?? 0 }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="resumen">
            <strong>Resumen:</strong> {{ $rows->count() }} ubicación(es) en este listado
            @if (!empty($estadisticas))
                · <strong>{{ $estadisticas['municipios_distintos'] ?? 0 }}</strong> municipio(s) distinto(s)
                · <strong>{{ $estadisticas['con_reportes'] ?? 0 }}</strong> con reportes asociados
            @endif
        </div>
    @endif

    <div class="footer">
        Generado el {{ now()->format('d/m/Y H:i') }} · CVAUP Táchira — Sistema de Reportes Técnicos
    </div>
</body>
</html>
