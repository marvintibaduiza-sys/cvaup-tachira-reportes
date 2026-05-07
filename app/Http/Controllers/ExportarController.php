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
     * Form de filtros para generar PDF.
     */
    public function pdfForm(): InertiaResponse
    {
        return Inertia::render('Exportar/Pdf', [
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
     * Form de filtros para generar archivo Excel.
     */
    public function excelForm(): InertiaResponse
    {
        return Inertia::render('Exportar/Excel', [
            'lookups' => $this->lookups(),
        ]);
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
     */
    private function validateFilters(Request $request): array
    {
        return $request->validate([
            'desde' => 'nullable|date',
            'hasta' => 'nullable|date|after_or_equal:desde',
            'tecnico_id' => 'nullable|integer|exists:tecnicos,id',
            'municipio_id' => 'nullable|integer|exists:municipios,id',
            'parroquia_id' => 'nullable|integer|exists:parroquias,id',
            'estado_reporte' => 'nullable|in:completo,incompleto,borrador,todos',
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
                'tecnico:id,nombre_apellido,cedula',
                'municipio:id,nombre',
                'parroquia:id,nombre',
                'comuna:id,nombre',
                'consejoComunal:id,nombre',
                'fotos:id,reporte_id',
            ]);
        }

        if (!empty($filters['desde'])) {
            $query->whereDate('fecha', '>=', $filters['desde']);
        }
        if (!empty($filters['hasta'])) {
            $query->whereDate('fecha', '<=', $filters['hasta']);
        }
        if (!empty($filters['tecnico_id'])) {
            $query->where('tecnico_id', $filters['tecnico_id']);
        }
        if (!empty($filters['municipio_id'])) {
            $query->where('municipio_id', $filters['municipio_id']);
        }
        if (!empty($filters['parroquia_id'])) {
            $query->where('parroquia_id', $filters['parroquia_id']);
        }

        $estado = $filters['estado_reporte'] ?? 'todos';
        if ($estado !== 'todos' && !empty($estado)) {
            $query->where('estado_reporte', $estado);
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
     * "Período: 01/05/2026 al 31/05/2026", "Técnico: Francy Ordoñez (V-...)", etc.
     */
    private function filtrosLegibles(array $filters): array
    {
        $textos = [];

        if (!empty($filters['desde']) || !empty($filters['hasta'])) {
            $desde = !empty($filters['desde']) ? Carbon::parse($filters['desde'])->format('d/m/Y') : '—';
            $hasta = !empty($filters['hasta']) ? Carbon::parse($filters['hasta'])->format('d/m/Y') : '—';
            $textos[] = "Período: {$desde} al {$hasta}";
        }

        if (!empty($filters['tecnico_id'])) {
            $tecnico = Tecnico::find($filters['tecnico_id']);
            if ($tecnico) {
                $textos[] = "Técnico: {$tecnico->nombre_apellido} ({$tecnico->cedula})";
            }
        }

        if (!empty($filters['municipio_id'])) {
            $municipio = Municipio::find($filters['municipio_id']);
            if ($municipio) {
                $textos[] = "Municipio: {$municipio->nombre}";
            }
        }

        if (!empty($filters['parroquia_id'])) {
            $parroquia = \App\Models\Parroquia::find($filters['parroquia_id']);
            if ($parroquia) {
                $textos[] = "Parroquia: {$parroquia->nombre}";
            }
        }

        $estado = $filters['estado_reporte'] ?? 'todos';
        if ($estado !== 'todos' && !empty($estado)) {
            $textos[] = "Estado: " . ucfirst($estado) . 's';
        }

        return $textos;
    }

    /**
     * Lookups precargados para los selects del form.
     */
    private function lookups(): array
    {
        return [
            'tecnicos' => Tecnico::orderBy('nombre_apellido')->get(['id', 'nombre_apellido', 'cedula']),
            'municipios' => Municipio::orderBy('nombre')->get(['id', 'nombre']),
        ];
    }
}
