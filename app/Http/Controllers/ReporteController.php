<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReporteRequest;
use App\Http\Requests\UpdateReporteRequest;
use App\Models\FotoReporte;
use App\Models\Municipio;
use App\Models\Reporte;
use App\Models\Tecnico;
use App\Services\PhotoCompressor;
use App\Support\SqlLike;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReporteController extends Controller
{
    public function __construct(private readonly PhotoCompressor $photoCompressor)
    {
    }

    /**
     * Listado paginado con filtros completos.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:255',
            'tecnico_id' => 'nullable|integer|exists:tecnicos,id',
            'municipio_id' => 'nullable|integer|exists:municipios,id',
            'parroquia_id' => 'nullable|integer|exists:parroquias,id',
            'estado_reporte' => 'nullable|in:completo,incompleto,borrador,todos',
            'desde' => 'nullable|date',
            'hasta' => 'nullable|date|after_or_equal:desde',
        ]);

        $query = Reporte::query()
            ->with([
                'tecnico:id,nombre_apellido,cedula',
                'municipio:id,nombre',
                'parroquia:id,nombre',
                'consejoComunal:id,nombre',
                'fotos:id,reporte_id,ruta',
            ])
            ->latest('fecha');

        if (!empty($filters['q'])) {
            // Escape de wildcards LIKE (post-auditoría MEDIUM #7).
            $q = SqlLike::escape(trim($filters['q']));
            $query->where(function ($w) use ($q) {
                $w->where('titulo_actividad', 'like', "%{$q}%")
                  ->orWhere('lugar', 'like', "%{$q}%")
                  ->orWhere('resumen_tematico', 'like', "%{$q}%");
            });
        }
        if (!empty($filters['tecnico_id'])) $query->where('tecnico_id', $filters['tecnico_id']);
        if (!empty($filters['municipio_id'])) $query->where('municipio_id', $filters['municipio_id']);
        if (!empty($filters['parroquia_id'])) $query->where('parroquia_id', $filters['parroquia_id']);

        $estado = $filters['estado_reporte'] ?? 'todos';
        if ($estado !== 'todos' && $estado !== null) {
            $query->where('estado_reporte', $estado);
        }
        if (!empty($filters['desde'])) $query->whereDate('fecha', '>=', $filters['desde']);
        if (!empty($filters['hasta'])) $query->whereDate('fecha', '<=', $filters['hasta']);

        $reportes = $query->paginate(15)->withQueryString()->through(fn (Reporte $r) => [
            'id' => $r->id,
            'fecha' => $r->fecha?->format('d/m/Y'),
            'tecnico' => $r->tecnico ? [
                'id' => $r->tecnico->id,
                'nombre_apellido' => $r->tecnico->nombre_apellido,
            ] : null,
            'municipio' => $r->municipio?->nombre,
            'parroquia' => $r->parroquia?->nombre,
            'consejo_comunal' => $r->consejoComunal?->nombre,
            'titulo_actividad' => $r->titulo_actividad,
            'participantes_acreditados' => $r->participantes_acreditados,
            'cantidad_personas_atendidas' => $r->cantidad_personas_atendidas,
            'estado_reporte' => $r->estado_reporte,
            'fotos_count' => $r->fotos->count(),
        ]);

        return Inertia::render('Reportes/Index', [
            'reportes' => $reportes,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'tecnico_id' => $filters['tecnico_id'] ?? null,
                'municipio_id' => $filters['municipio_id'] ?? null,
                'parroquia_id' => $filters['parroquia_id'] ?? null,
                'estado_reporte' => $estado,
                'desde' => $filters['desde'] ?? '',
                'hasta' => $filters['hasta'] ?? '',
            ],
            'lookups' => [
                'tecnicos' => Tecnico::activos()->orderBy('nombre_apellido')->get(['id', 'nombre_apellido']),
                'municipios' => Municipio::orderBy('nombre')->get(['id', 'nombre']),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Reportes/Create', [
            'tecnicos' => Tecnico::activos()->orderBy('nombre_apellido')->get(['id', 'nombre_apellido', 'cedula']),
            'municipios' => Municipio::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(StoreReporteRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $esBorrador = (bool) ($data['guardar_como_borrador'] ?? false);
        unset($data['guardar_como_borrador'], $data['fotos']);

        $reporte = DB::transaction(function () use ($data, $request, $esBorrador) {
            $reporte = Reporte::create($data);

            if ($request->hasFile('fotos')) {
                $orden = 1;
                foreach ($request->file('fotos') as $foto) {
                    $info = $this->photoCompressor->compressAndStore(
                        $foto,
                        "fotos/reportes/{$reporte->id}",
                        "foto-{$orden}"
                    );
                    FotoReporte::create([
                        'reporte_id' => $reporte->id,
                        'ruta' => $info['ruta'],
                        'nombre_original' => $info['nombre_original'],
                        'tamano_kb' => $info['tamano_kb'],
                        'orden' => $orden,
                    ]);
                    $orden++;
                    if ($orden > 3) break;
                }
            }

            $reporte->estado_reporte = $esBorrador ? 'borrador' : $reporte->calcularEstado();
            $reporte->save();

            return $reporte;
        });

        return redirect()
            ->route('reportes.show', $reporte)
            ->with('success', 'Reporte registrado correctamente.');
    }

    public function show(Reporte $reporte): Response
    {
        $reporte->load([
            'tecnico:id,nombre_apellido,cedula,foto_perfil',
            'municipio:id,nombre',
            'parroquia:id,nombre',
            'comuna:id,nombre',
            'consejoComunal:id,nombre',
            'fotos',
        ]);

        return Inertia::render('Reportes/Show', [
            'reporte' => $this->reporteToArray($reporte),
        ]);
    }

    public function edit(Reporte $reporte): Response
    {
        $reporte->load(['fotos:id,reporte_id,ruta,nombre_original,orden']);

        return Inertia::render('Reportes/Edit', [
            'reporte' => [
                'id' => $reporte->id,
                'tecnico_id' => $reporte->tecnico_id,
                'fecha' => $reporte->fecha?->format('Y-m-d'),
                'estado_reporte' => $reporte->estado_reporte,
                'municipio_id' => $reporte->municipio_id,
                'parroquia_id' => $reporte->parroquia_id,
                'comuna_id' => $reporte->comuna_id,
                'consejo_comunal_id' => $reporte->consejo_comunal_id,
                'cantidad_comunas_atendidas' => $reporte->cantidad_comunas_atendidas,
                'cantidad_consejos_comunales_atendidos' => $reporte->cantidad_consejos_comunales_atendidos,
                'lugar' => $reporte->lugar,
                'cantidad_personas_atendidas' => $reporte->cantidad_personas_atendidas,
                'cantidad_personas_a_beneficiar' => $reporte->cantidad_personas_a_beneficiar,
                'titulo_actividad' => $reporte->titulo_actividad,
                'nombre_cientifico_rubro' => $reporte->nombre_cientifico_rubro,
                'fecha_ejecucion' => $reporte->fecha_ejecucion?->format('Y-m-d'),
                'ponencia_responsable' => $reporte->ponencia_responsable,
                'material_apoyo' => $reporte->material_apoyo,
                'organizado_por' => $reporte->organizado_por,
                'aval_de' => $reporte->aval_de,
                'certificacion' => $reporte->certificacion,
                'participantes_acreditados' => $reporte->participantes_acreditados,
                'alcance_grupo' => $reporte->alcance_grupo,
                'resultado' => $reporte->resultado,
                'resumen_tematico' => $reporte->resumen_tematico,
                'fotos_existentes' => $reporte->fotos->map(fn ($f) => [
                    'id' => $f->id,
                    'url' => $f->url, // accessor → ruta autenticada via FotoController
                    'nombre_original' => $f->nombre_original,
                    'orden' => $f->orden,
                ]),
            ],
            'tecnicos' => Tecnico::activos()->orderBy('nombre_apellido')->get(['id', 'nombre_apellido', 'cedula']),
            'municipios' => Municipio::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function update(UpdateReporteRequest $request, Reporte $reporte): RedirectResponse
    {
        $data = $request->validated();
        $esBorrador = (bool) ($data['guardar_como_borrador'] ?? false);
        $fotosEliminar = $data['fotos_eliminar'] ?? [];
        unset($data['guardar_como_borrador'], $data['fotos'], $data['fotos_eliminar']);

        DB::transaction(function () use ($data, $request, $reporte, $esBorrador, $fotosEliminar) {
            $reporte->update($data);

            // Eliminar fotos marcadas
            if (!empty($fotosEliminar)) {
                $fotos = FotoReporte::where('reporte_id', $reporte->id)
                    ->whereIn('id', $fotosEliminar)
                    ->get();
                foreach ($fotos as $foto) {
                    $foto->delete(); // borra archivo también (ver booted() del modelo)
                }
            }

            // Agregar fotos nuevas (respetando max 3 totales)
            if ($request->hasFile('fotos')) {
                $existentes = $reporte->fotos()->count();
                $disponibles = max(0, 3 - $existentes);
                $maxOrden = (int) $reporte->fotos()->max('orden');

                $i = 0;
                foreach ($request->file('fotos') as $foto) {
                    if ($i >= $disponibles) break;
                    $orden = $maxOrden + 1 + $i;
                    $info = $this->photoCompressor->compressAndStore(
                        $foto,
                        "fotos/reportes/{$reporte->id}",
                        "foto-{$orden}"
                    );
                    FotoReporte::create([
                        'reporte_id' => $reporte->id,
                        'ruta' => $info['ruta'],
                        'nombre_original' => $info['nombre_original'],
                        'tamano_kb' => $info['tamano_kb'],
                        'orden' => $orden,
                    ]);
                    $i++;
                }
            }

            $reporte->refresh();
            $reporte->estado_reporte = $esBorrador ? 'borrador' : $reporte->calcularEstado();
            $reporte->save();
        });

        return redirect()
            ->route('reportes.show', $reporte)
            ->with('success', 'Reporte actualizado correctamente.');
    }

    public function destroy(Reporte $reporte): RedirectResponse
    {
        // Soft delete (las fotos NO se eliminan físicamente para permitir restore)
        $reporte->delete();

        return redirect()
            ->route('reportes.index')
            ->with('success', 'Reporte eliminado.');
    }

    /**
     * Genera y descarga el PDF individual del reporte.
     *
     * Estrategia: cargamos relaciones, anexamos el path absoluto a cada foto
     * (DomPDF necesita una ruta de filesystem para embebir imágenes locales,
     * NO una URL pública — esa requeriría isRemoteEnabled = true que abre CVE).
     */
    public function pdf(Reporte $reporte): HttpResponse
    {
        $reporte->load([
            'tecnico:id,nombre_apellido,cedula',
            'municipio:id,nombre',
            'parroquia:id,nombre',
            'comuna:id,nombre',
            'consejoComunal:id,nombre',
            'fotos' => fn ($q) => $q->orderBy('orden'),
        ]);

        // Adjuntar absolute_path a cada foto (resuelto desde el disk privado fotos_privadas).
        // DomPDF lee archivos del filesystem con file_get_contents, así que necesita path absoluto
        // — NO usa la ruta autenticada via FotoController (eso es solo para servir al navegador).
        $reporte->fotos->each(function ($foto) {
            $foto->absolute_path = storage_path('app/fotos-privadas/' . $foto->ruta);
        });

        $pdf = Pdf::loadView('pdf.reporte', ['reporte' => $reporte])
            ->setPaper('letter', 'portrait')
            ->setOption('isRemoteEnabled', false) // seguridad: NO permitir URLs externas
            ->setOption('isHtml5ParserEnabled', true);

        $filename = sprintf(
            'reporte_%s_%s.pdf',
            str_pad((string) $reporte->id, 6, '0', STR_PAD_LEFT),
            $reporte->fecha?->format('Y-m-d') ?? 'sin-fecha'
        );

        return $pdf->download($filename);
    }

    private function reporteToArray(Reporte $r): array
    {
        return [
            'id' => $r->id,
            'fecha' => $r->fecha?->format('d/m/Y'),
            'fecha_ejecucion' => $r->fecha_ejecucion?->format('d/m/Y'),
            'estado_reporte' => $r->estado_reporte,
            'tecnico' => $r->tecnico ? [
                'id' => $r->tecnico->id,
                'nombre_apellido' => $r->tecnico->nombre_apellido,
                'cedula' => $r->tecnico->cedula,
                'foto_url' => $r->tecnico->foto_perfil_url, // accessor autenticado
            ] : null,
            'ubicacion' => [
                'estado' => 'Táchira',
                'municipio' => $r->municipio?->nombre,
                'parroquia' => $r->parroquia?->nombre,
                'comuna' => $r->comuna?->nombre,
                'consejo_comunal' => $r->consejoComunal?->nombre,
            ],
            'metricas' => [
                'cantidad_comunas_atendidas' => $r->cantidad_comunas_atendidas,
                'cantidad_consejos_comunales_atendidos' => $r->cantidad_consejos_comunales_atendidos,
                'lugar' => $r->lugar,
                'cantidad_personas_atendidas' => $r->cantidad_personas_atendidas,
                'cantidad_personas_a_beneficiar' => $r->cantidad_personas_a_beneficiar,
            ],
            'actividad' => [
                'titulo_actividad' => $r->titulo_actividad,
                'nombre_cientifico_rubro' => $r->nombre_cientifico_rubro,
                'ponencia_responsable' => $r->ponencia_responsable,
                'material_apoyo' => $r->material_apoyo,
                'organizado_por' => $r->organizado_por,
                'aval_de' => $r->aval_de,
                'certificacion' => $r->certificacion,
            ],
            'impacto' => [
                'participantes_acreditados' => $r->participantes_acreditados,
                'alcance_grupo' => $r->alcance_grupo,
                'resultado' => $r->resultado,
            ],
            'resumen_tematico' => $r->resumen_tematico,
            'fotos' => $r->fotos->map(fn ($f) => [
                'id' => $f->id,
                'url' => $f->url, // accessor autenticado
                'nombre_original' => $f->nombre_original,
                'orden' => $f->orden,
            ]),
            'created_at' => $r->created_at?->format('d/m/Y H:i'),
            'updated_at' => $r->updated_at?->format('d/m/Y H:i'),
        ];
    }
}
