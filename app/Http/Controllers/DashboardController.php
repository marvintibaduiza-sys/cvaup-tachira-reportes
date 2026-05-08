<?php

namespace App\Http\Controllers;

use App\Models\Reporte;
use App\Models\Tecnico;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $hoy = Carbon::today();
        $esFinDeSemana = $hoy->isWeekend();

        return Inertia::render('Dashboard', [
            'stats' => $this->stats($hoy),
            'semaforo' => $this->semaforo($hoy, $esFinDeSemana),
            'alertas' => $this->alertas($hoy),
            'charts' => [
                'reportesPorTecnico' => $this->reportesPorTecnico($hoy),
                'reportesPorDia' => $this->reportesPorDia($hoy),
                'reportesPorMunicipio' => $this->reportesPorMunicipio($hoy),
            ],
        ]);
    }

    /**
     * Tarjetas resumen del Dashboard.
     */
    private function stats(Carbon $hoy): array
    {
        $inicioMes = $hoy->copy()->startOfMonth();
        $finMes = $hoy->copy()->endOfMonth();
        $inicioSemana = $hoy->copy()->startOfWeek();
        $finSemana = $hoy->copy()->endOfWeek();

        return [
            'tecnicosActivos' => Tecnico::activos()->count(),
            'reportesMes' => Reporte::whereBetween('fecha', [$inicioMes, $finMes])->count(),
            'reportesSemana' => Reporte::whereBetween('fecha', [$inicioSemana, $finSemana])->count(),
            'personasAtendidasMes' => (int) Reporte::whereBetween('fecha', [$inicioMes, $finMes])
                ->sum('cantidad_personas_atendidas'),
        ];
    }

    /**
     * Semáforo de cumplimiento de hoy.
     *
     *  verde = reportó hoy con reporte completo
     *  amarillo = reportó hoy pero incompleto
     *  rojo = no reportó hoy (siendo día laborable)
     *  gris = fin de semana (no se evalúa)
     */
    private function semaforo(Carbon $hoy, bool $esFinDeSemana): array
    {
        $tecnicos = Tecnico::activos()
            ->with(['reportes' => function ($q) use ($hoy) {
                $q->whereDate('fecha', $hoy)->latest('fecha');
            }])
            // Ordenamos por nombre+apellido (la columna nombre_apellido ya no existe — BLOQUE 4)
            ->orderBy('nombre')->orderBy('apellido')
            ->get();

        return $tecnicos->map(function (Tecnico $t) use ($hoy, $esFinDeSemana) {
            $color = 'rojo';

            if ($esFinDeSemana) {
                $color = 'gris';
            } else {
                $reporteHoy = $t->reportes->first();
                if ($reporteHoy) {
                    $color = $reporteHoy->estado_reporte === 'completo' ? 'verde' : 'amarillo';
                }
            }

            $ultimoReporte = $t->reportes()->latest('fecha')->value('fecha');

            return [
                'tecnico_id' => $t->id,
                'nombre' => $t->nombre_apellido,
                'cedula' => $t->cedula,
                'color' => $color,
                'ultimo_reporte' => $ultimoReporte?->format('d/m/Y'),
            ];
        })->all();
    }

    /**
     * Panel de alertas:
     *  warning = 3+ días laborables sin reportar
     *  danger  = 5+ días laborables sin reportar
     */
    private function alertas(Carbon $hoy): array
    {
        $tecnicos = Tecnico::activos()->with(['reportes' => fn ($q) => $q->latest('fecha')->limit(1)])->get();

        $alertas = [];
        foreach ($tecnicos as $t) {
            $ultimoReporte = $t->reportes->first();
            $diasLaborables = $this->contarDiasLaborablesDesde($ultimoReporte?->fecha, $hoy);

            if ($diasLaborables >= 5) {
                $severidad = 'danger';
            } elseif ($diasLaborables >= 3) {
                $severidad = 'warning';
            } else {
                continue;
            }

            $alertas[] = [
                'tecnico_id' => $t->id,
                'nombre' => $t->nombre_apellido,
                'dias' => $diasLaborables,
                'ultimo_reporte' => $ultimoReporte?->fecha?->format('d/m/Y'),
                'severidad' => $severidad,
            ];
        }

        return $alertas;
    }

    /**
     * Cuenta días laborables (L-V) entre fecha base y hoy (excluido hoy si no es laborable).
     * Si fechaBase es null, devuelve un valor alto para forzar alerta.
     */
    private function contarDiasLaborablesDesde(?Carbon $fechaBase, Carbon $hoy): int
    {
        if (!$fechaBase) {
            return 99; // nunca ha reportado
        }

        $count = 0;
        $cursor = $fechaBase->copy()->addDay();
        while ($cursor->lt($hoy)) {
            if ($cursor->isWeekday()) {
                $count++;
            }
            $cursor->addDay();
        }
        return $count;
    }

    // ─────────────────────────────────────────────────────────────────────
    //  CHARTS — datos agregados para visualizaciones (Chart.js)
    //  Toda la agregación ocurre en SQL, NO en PHP, para escalar con miles
    //  de reportes sin overhead de memoria.
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Top 10 técnicos por número de reportes en el mes actual.
     * Devuelve [['nombre' => ..., 'count' => N], ...] ordenado desc.
     */
    private function reportesPorTecnico(Carbon $hoy): array
    {
        $inicioMes = $hoy->copy()->startOfMonth();
        $finMes = $hoy->copy()->endOfMonth();

        // Concatenamos nombre + apellido en SQL (la columna nombre_apellido ya no existe — BLOQUE 4)
        return Reporte::query()
            ->select(
                DB::raw("CONCAT(tecnicos.nombre, ' ', tecnicos.apellido) as nombre"),
                DB::raw('COUNT(reportes.id) as count')
            )
            ->join('tecnicos', 'tecnicos.id', '=', 'reportes.tecnico_id')
            ->whereBetween('reportes.fecha', [$inicioMes, $finMes])
            ->groupBy('tecnicos.id', 'tecnicos.nombre', 'tecnicos.apellido')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'nombre' => $r->nombre,
                'count' => (int) $r->count,
            ])
            ->all();
    }

    /**
     * Reportes por día durante los últimos 30 días naturales.
     * IMPORTANTE: rellena los días con 0 para que la línea no tenga gaps visuales
     * (un day-axis sin un día se ve roto en Chart.js).
     *
     * Devuelve [['fecha' => 'YYYY-MM-DD', 'label' => '06/05', 'count' => N], ...]
     */
    private function reportesPorDia(Carbon $hoy): array
    {
        $inicio = $hoy->copy()->subDays(29)->startOfDay(); // 30 días incluyendo hoy
        $fin = $hoy->copy()->endOfDay();

        // 1. Conteo agrupado por fecha desde DB
        $conteos = Reporte::query()
            ->select(DB::raw('DATE(fecha) as fecha'), DB::raw('COUNT(*) as count'))
            ->whereBetween('fecha', [$inicio, $fin])
            ->groupBy(DB::raw('DATE(fecha)'))
            ->pluck('count', 'fecha')
            ->all();

        // 2. Generar serie completa de 30 días, rellenar con 0 los huecos
        $serie = [];
        $cursor = $inicio->copy();
        while ($cursor->lte($fin)) {
            $key = $cursor->format('Y-m-d');
            $serie[] = [
                'fecha' => $key,
                'label' => $cursor->format('d/m'),
                'count' => (int) ($conteos[$key] ?? 0),
            ];
            $cursor->addDay();
        }

        return $serie;
    }

    /**
     * Distribución de reportes del mes por municipio.
     * Devuelve [['municipio' => ..., 'count' => N], ...] top 10 desc.
     */
    private function reportesPorMunicipio(Carbon $hoy): array
    {
        $inicioMes = $hoy->copy()->startOfMonth();
        $finMes = $hoy->copy()->endOfMonth();

        return Reporte::query()
            ->select('municipios.nombre as municipio', DB::raw('COUNT(reportes.id) as count'))
            ->join('municipios', 'municipios.id', '=', 'reportes.municipio_id')
            ->whereBetween('reportes.fecha', [$inicioMes, $finMes])
            ->groupBy('municipios.id', 'municipios.nombre')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'municipio' => $r->municipio,
                'count' => (int) $r->count,
            ])
            ->all();
    }

    // ─────────────────────────────────────────────────────────────────────
    //  EXPORT — Informe ejecutivo del Dashboard como PDF (snapshot)
    //
    //  Patrón: Opción C (híbrida frontend/backend)
    //   1. El frontend captura los 3 charts con chart.toBase64Image() (Chart.js v4)
    //   2. POSTea las imágenes data:image/png;base64 a este endpoint
    //   3. El backend RECALCULA stats/alertas server-side (NO confía en el cliente)
    //   4. DomPDF compone con cintillo institucional + estructura formal
    //
    //  Por qué PDF (y no Excel): el dashboard es una narrativa visual de momento,
    //  no datos tabulares. Igual razonamiento que para reportes individuales.
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Genera un PDF resumen-ejecutivo del Dashboard con cintillo institucional.
     *
     * Recibe del cliente las 3 imágenes de charts (data URI base64) ya renderizadas
     * por Chart.js, las embebe en una vista Blade, y descarga el PDF.
     */
    public function exportarPdf(Request $request): HttpResponse
    {
        // El cliente manda data URIs como "data:image/png;base64,iVBORw0KGgoAAAANS..."
        // Validamos formato + tamaño máximo (~5 MB cada una) para evitar abuso.
        $request->validate([
            'chart_tendencia' => ['required', 'string', 'starts_with:data:image/png;base64,', 'max:5500000'],
            'chart_tecnicos' => ['required', 'string', 'starts_with:data:image/png;base64,', 'max:5500000'],
            'chart_municipios' => ['required', 'string', 'starts_with:data:image/png;base64,', 'max:5500000'],
        ], [
            'starts_with' => 'La imagen del gráfico no tiene un formato válido.',
        ]);

        $hoy = Carbon::today();
        $esFinDeSemana = $hoy->isWeekend();

        // SEGURIDAD: recalculamos stats/alertas server-side. NO usamos lo que mande
        // el cliente — un atacante podría falsificar números si confiáramos en el POST.
        $stats = $this->stats($hoy);
        $alertas = $this->alertas($hoy);
        $semaforo = $this->semaforo($hoy, $esFinDeSemana);

        // Resumen del semáforo: cuántos verdes / amarillos / rojos / grises hoy
        $resumenSemaforo = [
            'verde' => 0, 'amarillo' => 0, 'rojo' => 0, 'gris' => 0,
        ];
        foreach ($semaforo as $row) {
            $resumenSemaforo[$row['color']] = ($resumenSemaforo[$row['color']] ?? 0) + 1;
        }

        $pdf = Pdf::loadView('pdf.dashboard-resumen', [
            'stats' => $stats,
            'alertas' => $alertas,
            'resumenSemaforo' => $resumenSemaforo,
            'totalTecnicos' => \count($semaforo),
            'esFinDeSemana' => $esFinDeSemana,
            'fechaGeneracion' => now(),
            'chartTendencia' => $request->input('chart_tendencia'),
            'chartTecnicos' => $request->input('chart_tecnicos'),
            'chartMunicipios' => $request->input('chart_municipios'),
        ])
            ->setPaper('letter', 'portrait')
            ->setOption('isRemoteEnabled', false); // las imágenes son inline, no necesitamos red

        $filename = 'dashboard-resumen-' . now()->format('Y-m-d-His') . '.pdf';
        return $pdf->download($filename);
    }
}
