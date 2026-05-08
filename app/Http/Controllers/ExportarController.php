<?php

namespace App\Http\Controllers;

use App\Exports\ReportesExport;
use App\Models\Municipio;
use App\Models\Reporte;
use App\Models\Tecnico;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportarController extends Controller
{
    /**
     * Pantalla UNIFICADA "Generar Reportes" — reemplaza pdfForm() y excelForm().
     *
     * Una sola pantalla con:
     *  - Filtros configurables (mismo FormFiltros que antes)
     *  - 2 botones al final: "Descargar Excel" y "Generar PDF"
     *
     * Razón: simplifica el sidebar (1 item vs 2) y reduce clicks del admin.
     */
    public function formUnificado(): InertiaResponse
    {
        return Inertia::render('GenerarReportes/Index', [
            'lookups' => $this->lookups(),
        ]);
    }


    /**
     * Genera y descarga el PDF con la lista de reportes filtrados.
     * Soporta opcionalmente una comparativa con período anterior (Fase 12.2).
     */
    public function pdfDownload(Request $request): HttpResponse
    {
        $filters = $this->validateFilters($request);
        $reportes = $this->buildQuery($filters)->get();

        $estadisticas = $this->calcularEstadisticas($reportes);
        $filtrosLegibles = $this->filtrosLegibles($filters);

        // Comparativa opcional con período anterior
        $comparativa = $this->construirComparativa($request, $filters, $estadisticas);

        // Resumen gráfico opcional (Fase 12.3) — agrega página final con CSS bar charts
        $graficas = $request->boolean('incluir_graficas')
            ? $this->construirDataGraficas($reportes)
            : null;

        $pdf = Pdf::loadView('pdf.lista-reportes', [
            'reportes' => $reportes,
            'filtrosLegibles' => $filtrosLegibles,
            'estadisticas' => $estadisticas,
            'totalReportes' => $reportes->count(),
            'comparativa' => $comparativa,
            'graficas' => $graficas,
        ])
            ->setPaper('letter', 'portrait')
            ->setOption('isRemoteEnabled', false);

        $filename = 'reportes-' . now()->format('Y-m-d-His') . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Genera y descarga un archivo .xlsx (Excel nativo) con todos los reportes filtrados.
     *
     * Ventajas vs CSV anterior:
     *  - UTF-8 nativo, sin mojibake con tildes/ñ en Excel español
     *  - Headers con styling institucional (verde CVAUP)
     *  - Anchos de columna auto-ajustados
     *  - Freeze pane + AutoFilter activado por default
     *  - Filas alternadas para legibilidad
     *  - Tipos nativos (números, fechas) — no todo es texto como en CSV
     */
    public function excelDownload(Request $request): BinaryFileResponse
    {
        $filters = $this->validateFilters($request);
        $reportes = $this->buildQuery($filters)->get();

        $filename = 'reportes-' . now()->format('Y-m-d-His') . '.xlsx';

        return Excel::download(new ReportesExport($reportes), $filename);
    }

    /**
     * Endpoint JSON para preview en vivo del conteo (consumido por el form Vue con debounce).
     */
    public function preview(Request $request): JsonResponse
    {
        $filters = $this->validateFilters($request);
        $count = $this->buildQuery($filters, withRelations: false)->count();

        return response()->json([
            'count' => $count,
            'filtros_legibles' => $this->filtrosLegibles($filters),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // Helpers privados
    // ─────────────────────────────────────────────────────────────────

    /**
     * Validación común de los filtros para todos los endpoints.
     *
     * Multi-select: tecnico_ids, municipio_ids, parroquia_ids, estados_reporte
     * son ARRAYS (vacío = "todos", sin filtro).
     */
    private function validateFilters(Request $request): array
    {
        return $request->validate([
            'desde' => 'nullable|date',
            'hasta' => 'nullable|date|after_or_equal:desde',

            // Multi-select arrays
            'tecnico_ids' => 'nullable|array',
            'tecnico_ids.*' => 'integer|exists:tecnicos,id',
            'municipio_ids' => 'nullable|array',
            'municipio_ids.*' => 'integer|exists:municipios,id',
            'parroquia_ids' => 'nullable|array',
            'parroquia_ids.*' => 'integer|exists:parroquias,id',
            'estados_reporte' => 'nullable|array',
            'estados_reporte.*' => 'in:completo,incompleto,borrador',

            // Comparativa opcional (Fase 12.2) — solo se valida si comparar=1
            'comparar' => 'nullable|boolean',
            'desde_comparacion' => 'nullable|date|required_with:comparar',
            'hasta_comparacion' => 'nullable|date|after_or_equal:desde_comparacion|required_with:comparar',
            // Resumen gráfico opcional (Fase 12.3)
            'incluir_graficas' => 'nullable|boolean',
        ]);
    }

    /**
     * Construye el query base aplicando filtros.
     * Reutilizado por pdfDownload, csvDownload y preview.
     */
    private function buildQuery(array $filters, bool $withRelations = true): Builder
    {
        $query = Reporte::query()->latest('fecha');

        if ($withRelations) {
            $query->with([
                // Cargamos nombre+apellido+tipo_documento+cedula+especialidades (BLOQUE 4 + 4.5)
                'tecnico:id,nombre,apellido,tipo_documento,cedula,especialidades',
                'municipio:id,nombre',
                'parroquia:id,nombre',
                'comuna:id,nombre',
                'consejoComunal:id,nombre',
                'fotos:id,reporte_id',
                // BLOQUE 5: pivotes con jerarquía para mostrar contexto en exports
                'comunasAdicionales:id,nombre,parroquia_id',
                'comunasAdicionales.parroquia:id,nombre,municipio_id',
                'comunasAdicionales.parroquia.municipio:id,nombre',
                'consejosComunalesAdicionales:id,nombre,comuna_id',
                'consejosComunalesAdicionales.comuna:id,nombre',
            ]);
        }

        if (!empty($filters['desde'])) {
            $query->whereDate('fecha', '>=', $filters['desde']);
        }
        if (!empty($filters['hasta'])) {
            $query->whereDate('fecha', '<=', $filters['hasta']);
        }

        // Multi-select: usar whereIn cuando hay 1+ elementos, omitir cuando vacío
        if (!empty($filters['tecnico_ids']) && \is_array($filters['tecnico_ids'])) {
            $query->whereIn('tecnico_id', $filters['tecnico_ids']);
        }
        if (!empty($filters['municipio_ids']) && \is_array($filters['municipio_ids'])) {
            $query->whereIn('municipio_id', $filters['municipio_ids']);
        }
        if (!empty($filters['parroquia_ids']) && \is_array($filters['parroquia_ids'])) {
            $query->whereIn('parroquia_id', $filters['parroquia_ids']);
        }
        if (!empty($filters['estados_reporte']) && \is_array($filters['estados_reporte'])) {
            $query->whereIn('estado_reporte', $filters['estados_reporte']);
        }

        return $query;
    }

    /**
     * Estadísticas agregadas del set filtrado — se muestran al final del PDF.
     */
    private function calcularEstadisticas($reportes): array
    {
        return [
            'total' => $reportes->count(),
            'completos' => $reportes->where('estado_reporte', 'completo')->count(),
            'incompletos' => $reportes->where('estado_reporte', 'incompleto')->count(),
            'borradores' => $reportes->where('estado_reporte', 'borrador')->count(),
            'total_personas_atendidas' => (int) $reportes->sum('cantidad_personas_atendidas'),
            'total_personas_a_beneficiar' => (int) $reportes->sum('cantidad_personas_a_beneficiar'),
            'total_participantes_acreditados' => (int) $reportes->sum('participantes_acreditados'),
            'tecnicos_distintos' => $reportes->pluck('tecnico_id')->filter()->unique()->count(),
            'municipios_distintos' => $reportes->pluck('municipio_id')->filter()->unique()->count(),
        ];
    }

    /**
     * Construye la sección comparativa si el usuario marcó el checkbox.
     * Devuelve null si no hay comparativa o no hay datos suficientes para hacerla.
     */
    private function construirComparativa(Request $request, array $filters, array $estadisticasActual): ?array
    {
        if (!$request->boolean('comparar')) {
            return null;
        }

        $desdeComp = $request->input('desde_comparacion');
        $hastaComp = $request->input('hasta_comparacion');

        if (empty($desdeComp) || empty($hastaComp)) {
            return null;
        }

        // Re-correr el query con las fechas de comparación, manteniendo el resto de filtros
        $filtersComp = $filters;
        $filtersComp['desde'] = $desdeComp;
        $filtersComp['hasta'] = $hastaComp;
        $reportesComp = $this->buildQuery($filtersComp)->get();
        $estadisticasComp = $this->calcularEstadisticas($reportesComp);

        // Calcular deltas % para cada métrica
        $deltas = [];
        foreach ($estadisticasActual as $key => $valActual) {
            $valAnterior = $estadisticasComp[$key] ?? 0;
            $deltas[$key] = $this->calcularDelta((int) $valActual, (int) $valAnterior);
        }

        return [
            'periodo_actual' => [
                'desde' => !empty($filters['desde']) ? Carbon::parse($filters['desde'])->format('d/m/Y') : 'inicio',
                'hasta' => !empty($filters['hasta']) ? Carbon::parse($filters['hasta'])->format('d/m/Y') : 'hoy',
                'estadisticas' => $estadisticasActual,
            ],
            'periodo_anterior' => [
                'desde' => Carbon::parse($desdeComp)->format('d/m/Y'),
                'hasta' => Carbon::parse($hastaComp)->format('d/m/Y'),
                'estadisticas' => $estadisticasComp,
            ],
            'deltas' => $deltas,
        ];
    }

    /**
     * Construye los datasets para las barras del PDF (Fase 12.3).
     *
     * Se calcula en PHP-land en vez de SQL agregadas porque ya tenemos la colección
     * cargada con relaciones — más simple y suficiente performance para los volúmenes
     * típicos institucionales (cientos, no millones de filas).
     *
     * Retorna 4 datasets — cada uno es array<{label, value, color?}>:
     *  - por_tecnico:    top 10 técnicos por # de reportes
     *  - por_municipio:  top 10 municipios por # de reportes
     *  - por_estado:     completos/incompletos/borradores con sus colores semánticos
     *  - por_dia:        reportes por día (últimos 14 días del set, máx 14 barras)
     */
    private function construirDataGraficas($reportes): array
    {
        return [
            'por_tecnico' => $reportes
                ->filter(fn ($r) => $r->tecnico !== null)
                ->groupBy(fn ($r) => $r->tecnico->nombre_apellido)
                ->map(fn ($group, $name) => ['label' => $name, 'value' => $group->count()])
                ->sortByDesc('value')
                ->take(10)
                ->values()
                ->all(),

            'por_municipio' => $reportes
                ->filter(fn ($r) => $r->municipio !== null)
                ->groupBy(fn ($r) => $r->municipio->nombre)
                ->map(fn ($group, $name) => ['label' => $name, 'value' => $group->count()])
                ->sortByDesc('value')
                ->take(10)
                ->values()
                ->all(),

            'por_estado' => [
                ['label' => 'Completos',   'value' => $reportes->where('estado_reporte', 'completo')->count(),  'color' => '#2E7D32'],
                ['label' => 'Incompletos', 'value' => $reportes->where('estado_reporte', 'incompleto')->count(), 'color' => '#F57F17'],
                ['label' => 'Borradores',  'value' => $reportes->where('estado_reporte', 'borrador')->count(),  'color' => '#546E7A'],
            ],

            'por_dia' => $reportes
                ->filter(fn ($r) => $r->fecha !== null)
                ->groupBy(fn ($r) => $r->fecha->format('d/m'))
                ->map(fn ($group, $fecha) => ['label' => $fecha, 'value' => $group->count()])
                ->sortKeys()
                ->take(-14) // últimas 14 fechas con actividad
                ->values()
                ->all(),
        ];
    }

    /**
     * Calcula el delta porcentual entre dos valores.
     * Devuelve un array con valor formateado, dirección (up/down/equal) y label legible.
     *
     * Casos especiales:
     *  - anterior = 0 y actual > 0 → "+∞" (nuevo desde cero)
     *  - anterior = 0 y actual = 0 → "0%" (sin cambios)
     */
    private function calcularDelta(int $actual, int $anterior): array
    {
        if ($anterior === 0) {
            if ($actual === 0) {
                return ['label' => '0%', 'direction' => 'equal', 'pct' => 0];
            }
            return ['label' => '+∞', 'direction' => 'up', 'pct' => null];
        }

        $pct = round((($actual - $anterior) / $anterior) * 100, 1);
        $direction = $pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'equal');
        $label = ($pct > 0 ? '+' : '') . $pct . '%';

        return ['label' => $label, 'direction' => $direction, 'pct' => $pct];
    }

    /**
     * Convierte filtros aplicados en un array de strings legibles para el subtítulo del PDF.
     * "Período: 01/05/2026 al 31/05/2026", "Técnicos: Pedro González, María López", etc.
     *
     * Multi-select: para listas de 1-3 elementos los lista; >3 los resume como "(N seleccionados)".
     */
    private function filtrosLegibles(array $filters): array
    {
        $textos = [];

        if (!empty($filters['desde']) || !empty($filters['hasta'])) {
            $desde = !empty($filters['desde']) ? Carbon::parse($filters['desde'])->format('d/m/Y') : '—';
            $hasta = !empty($filters['hasta']) ? Carbon::parse($filters['hasta'])->format('d/m/Y') : '—';
            $textos[] = "Período: {$desde} al {$hasta}";
        }

        if (!empty($filters['tecnico_ids']) && \is_array($filters['tecnico_ids'])) {
            // Tras BLOQUE 4 la columna nombre_apellido no existe — orderBy físico
            // por las dos columnas reales y pluck() del accessor virtual.
            $tecnicos = Tecnico::whereIn('id', $filters['tecnico_ids'])
                ->orderBy('nombre')
                ->orderBy('apellido')
                ->get();
            $label = $tecnicos->count() === 1 ? 'Técnico' : 'Técnicos';
            $valor = $tecnicos->count() <= 3
                ? $tecnicos->pluck('nombre_apellido')->implode(', ') // accessor concatenado
                : "{$tecnicos->count()} seleccionados";
            $textos[] = "{$label}: {$valor}";
        }

        if (!empty($filters['municipio_ids']) && \is_array($filters['municipio_ids'])) {
            $municipios = Municipio::whereIn('id', $filters['municipio_ids'])->orderBy('nombre')->get();
            $label = $municipios->count() === 1 ? 'Municipio' : 'Municipios';
            $valor = $municipios->count() <= 3
                ? $municipios->pluck('nombre')->implode(', ')
                : "{$municipios->count()} seleccionados";
            $textos[] = "{$label}: {$valor}";
        }

        if (!empty($filters['parroquia_ids']) && \is_array($filters['parroquia_ids'])) {
            $parroquias = \App\Models\Parroquia::whereIn('id', $filters['parroquia_ids'])->orderBy('nombre')->get();
            $label = $parroquias->count() === 1 ? 'Parroquia' : 'Parroquias';
            $valor = $parroquias->count() <= 3
                ? $parroquias->pluck('nombre')->implode(', ')
                : "{$parroquias->count()} seleccionadas";
            $textos[] = "{$label}: {$valor}";
        }

        if (!empty($filters['estados_reporte']) && \is_array($filters['estados_reporte'])) {
            $estados = collect($filters['estados_reporte'])->map(fn ($e) => ucfirst($e) . 's')->implode(', ');
            $label = \count($filters['estados_reporte']) === 1 ? 'Estado' : 'Estados';
            $textos[] = "{$label}: {$estados}";
        }

        return $textos;
    }

    /**
     * Lookups precargados para los selects del form.
     *
     * Por cada técnico también incluimos `municipios_ids` (los IDs de los municipios
     * que le están asignados vía tabla pivot tecnico_municipio). El frontend usa
     * esto para PRE-SELECCIONAR automáticamente los municipios cuando se agrega
     * un técnico al filtro — UX "sugerencia inteligente con override manual".
     */
    private function lookups(): array
    {
        return [
            // Lista de técnicos para el filtro. Tras BLOQUE 4 la columna nombre_apellido
            // ya NO existe — se ordena por nombre+apellido y el accessor concatena
            // automáticamente para mantener compatibilidad con el frontend.
            'tecnicos' => Tecnico::with('municipios:id')
                ->orderBy('nombre')
                ->orderBy('apellido')
                ->get(['id', 'nombre', 'apellido', 'tipo_documento', 'cedula'])
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'nombre_apellido' => $t->nombre_apellido,        // accessor concatenado
                    'documento_completo' => $t->documento_completo,  // ej. "V-12345678"
                    'cedula' => $t->cedula,
                    'municipios_ids' => $t->municipios->pluck('id')->all(),
                ]),
            'municipios' => Municipio::orderBy('nombre')->get(['id', 'nombre']),
        ];
    }
}
