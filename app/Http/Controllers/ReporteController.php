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
                // BLOQUE 4: nombre_apellido ya no existe como columna; cargamos las
                // columnas reales y el accessor se encarga de concatenar.
                'tecnico:id,nombre,apellido,tipo_documento,cedula',
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
                $w->where('tipo_actividad', 'like', "%{$q}%")
                  ->orWhere('lugar', 'like', "%{$q}%")
                  ->orWhere('descripcion_actividad', 'like', "%{$q}%");
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
            // BLOQUE 8: tipo_actividad (campo simplificado)
            'tipo_actividad' => $r->tipo_actividad,
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
                // BLOQUE 4: ordenamos por las columnas físicas (nombre, apellido) y
                // el accessor `nombre_apellido` se serializa al pasar por toArray().
                'tecnicos' => Tecnico::activos()
                    ->orderBy('nombre')->orderBy('apellido')
                    ->get(['id', 'nombre', 'apellido', 'tipo_documento', 'cedula'])
                    ->map(fn ($t) => [
                        'id' => $t->id,
                        'nombre_apellido' => $t->nombre_apellido,
                    ]),
                'municipios' => Municipio::orderBy('nombre')->get(['id', 'nombre']),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Reportes/Create', [
            'tecnicos' => Tecnico::activos()
                ->orderBy('nombre')->orderBy('apellido')
                ->get(['id', 'nombre', 'apellido', 'tipo_documento', 'cedula'])
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'nombre_apellido' => $t->nombre_apellido,
                    'cedula' => $t->cedula,
                    'documento_completo' => $t->documento_completo,
                ]),
            'municipios' => Municipio::orderBy('nombre')->get(['id', 'nombre']),
            // BLOQUE 5: catálogos completos para los multi-selects de adicionales.
            // Pueden ser de CUALQUIER municipio/parroquia, así que cargamos todo.
            'todasLasComunas' => $this->todasLasComunasParaMultiselect(),
            'todosLosConsejosComunales' => $this->todosLosCCsParaMultiselect(),
            // BLOQUE 8: catálogo de tipos para alimentar el dropdown del form
            'tipos_actividad' => \App\Models\Reporte::TIPOS_ACTIVIDAD,
        ]);
    }

    public function store(StoreReporteRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $esBorrador = (bool) ($data['guardar_como_borrador'] ?? false);

        // BLOQUE 5: extraemos los IDs adicionales antes de crear el modelo
        // (no son columnas, son pivotes — se manejan via sync()).
        $comunasAdicionales = $data['comunas_adicionales_ids'] ?? [];
        $ccsAdicionales = $data['consejos_comunales_adicionales_ids'] ?? [];

        unset(
            $data['guardar_como_borrador'],
            $data['fotos'],
            $data['comunas_adicionales_ids'],
            $data['consejos_comunales_adicionales_ids'],
            // Las cantidades se calculan automáticamente desde los pivotes — ignoramos lo que mande el cliente.
            $data['cantidad_comunas_atendidas'],
            $data['cantidad_consejos_comunales_atendidos'],
        );

        $reporte = DB::transaction(function () use ($data, $request, $esBorrador, $comunasAdicionales, $ccsAdicionales) {
            $reporte = Reporte::create($data);

            // BLOQUE 5: sync de los pivotes y recalculo de cantidades cacheadas.
            $reporte->comunasAdicionales()->sync($comunasAdicionales);
            $reporte->consejosComunalesAdicionales()->sync($ccsAdicionales);
            $reporte->recalcularCantidadesYGuardar();

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
            // BLOQUE 4: cargamos los campos físicos del técnico; el accessor concatena.
            'tecnico:id,nombre,apellido,tipo_documento,cedula,foto_perfil',
            'municipio:id,nombre',
            'parroquia:id,nombre',
            'comuna:id,nombre,parroquia_id',
            'consejoComunal:id,nombre,comuna_id',
            'fotos',
            // BLOQUE 5: pivotes con jerarquía denormalizada para mostrar contexto.
            'comunasAdicionales:id,nombre,parroquia_id',
            'comunasAdicionales.parroquia:id,nombre,municipio_id',
            'comunasAdicionales.parroquia.municipio:id,nombre',
            'consejosComunalesAdicionales:id,nombre,comuna_id',
            'consejosComunalesAdicionales.comuna:id,nombre,parroquia_id',
            'consejosComunalesAdicionales.comuna.parroquia:id,nombre,municipio_id',
            'consejosComunalesAdicionales.comuna.parroquia.municipio:id,nombre',
        ]);

        return Inertia::render('Reportes/Show', [
            'reporte' => $this->reporteToArray($reporte),
        ]);
    }

    public function edit(Reporte $reporte): Response
    {
        $reporte->load([
            'fotos:id,reporte_id,ruta,nombre_original,orden',
            // BLOQUE 5: cargamos solo los IDs de los pivotes (todos los datos vienen del catálogo)
            'comunasAdicionales:id',
            'consejosComunalesAdicionales:id',
        ]);

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
                // BLOQUE 5: IDs adicionales para preseleccionar
                'comunas_adicionales_ids' => $reporte->comunasAdicionales->pluck('id')->all(),
                'consejos_comunales_adicionales_ids' => $reporte->consejosComunalesAdicionales->pluck('id')->all(),
                'lugar' => $reporte->lugar,
                'cantidad_personas_atendidas' => $reporte->cantidad_personas_atendidas,
                'cantidad_personas_a_beneficiar' => $reporte->cantidad_personas_a_beneficiar,
                // BLOQUE 8: campos simplificados de actividad
                'tipo_actividad' => $reporte->tipo_actividad,
                'descripcion_actividad' => $reporte->descripcion_actividad,
                'fotos_existentes' => $reporte->fotos->map(fn ($f) => [
                    'id' => $f->id,
                    'url' => $f->url,
                    'nombre_original' => $f->nombre_original,
                    'orden' => $f->orden,
                ]),
            ],
            'tecnicos' => Tecnico::activos()
                ->orderBy('nombre')->orderBy('apellido')
                ->get(['id', 'nombre', 'apellido', 'tipo_documento', 'cedula'])
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'nombre_apellido' => $t->nombre_apellido,
                    'cedula' => $t->cedula,
                    'documento_completo' => $t->documento_completo,
                ]),
            'municipios' => Municipio::orderBy('nombre')->get(['id', 'nombre']),
            // BLOQUE 5: catálogos completos para los multi-selects
            'todasLasComunas' => $this->todasLasComunasParaMultiselect(),
            'todosLosConsejosComunales' => $this->todosLosCCsParaMultiselect(),
            // BLOQUE 8: catálogo de tipos para alimentar el dropdown del form
            'tipos_actividad' => \App\Models\Reporte::TIPOS_ACTIVIDAD,
        ]);
    }

    public function update(UpdateReporteRequest $request, Reporte $reporte): RedirectResponse
    {
        $data = $request->validated();
        $esBorrador = (bool) ($data['guardar_como_borrador'] ?? false);
        $fotosEliminar = $data['fotos_eliminar'] ?? [];

        // BLOQUE 5: extraemos los IDs adicionales antes de update() (son pivotes).
        $comunasAdicionales = $data['comunas_adicionales_ids'] ?? [];
        $ccsAdicionales = $data['consejos_comunales_adicionales_ids'] ?? [];

        unset(
            $data['guardar_como_borrador'],
            $data['fotos'],
            $data['fotos_eliminar'],
            $data['comunas_adicionales_ids'],
            $data['consejos_comunales_adicionales_ids'],
            $data['cantidad_comunas_atendidas'],
            $data['cantidad_consejos_comunales_atendidos'],
        );

        DB::transaction(function () use ($data, $request, $reporte, $esBorrador, $fotosEliminar, $comunasAdicionales, $ccsAdicionales) {
            $reporte->update($data);

            // BLOQUE 5: sync de pivotes — sync() reemplaza la lista completa
            // (agrega los nuevos, quita los desmarcados).
            $reporte->comunasAdicionales()->sync($comunasAdicionales);
            $reporte->consejosComunalesAdicionales()->sync($ccsAdicionales);

            // Eliminar fotos marcadas
            if (!empty($fotosEliminar)) {
                $fotos = FotoReporte::where('reporte_id', $reporte->id)
                    ->whereIn('id', $fotosEliminar)
                    ->get();
                /** @var FotoReporte $foto */
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

            // BLOQUE 5: recalcular cantidades cacheadas desde los pivotes
            $reporte->cantidad_comunas_atendidas = $reporte->calcularCantidadComunasAtendidas();
            $reporte->cantidad_consejos_comunales_atendidos = $reporte->calcularCantidadConsejosComunalesAtendidos();

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
            // BLOQUE 4 + 4.5: técnico con todos los campos institucionales + especialidades JSON.
            'tecnico:id,nombre,apellido,tipo_documento,cedula,especialidades',
            'municipio:id,nombre',
            'parroquia:id,nombre',
            'comuna:id,nombre',
            'consejoComunal:id,nombre',
            'fotos' => fn ($q) => $q->orderBy('orden'),
            // BLOQUE 5: pivotes con jerarquía denormalizada para mostrar contexto en el PDF
            'comunasAdicionales:id,nombre,parroquia_id',
            'comunasAdicionales.parroquia:id,nombre,municipio_id',
            'comunasAdicionales.parroquia.municipio:id,nombre',
            'consejosComunalesAdicionales:id,nombre,comuna_id',
            'consejosComunalesAdicionales.comuna:id,nombre',
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

        $filename = \sprintf(
            'reporte_%s_%s.pdf',
            \str_pad((string) $reporte->id, 6, '0', STR_PAD_LEFT),
            $reporte->fecha?->format('Y-m-d') ?? 'sin-fecha'
        );

        return $pdf->download($filename);
    }

    private function reporteToArray(Reporte $r): array
    {
        return [
            'id' => $r->id,
            'fecha' => $r->fecha?->format('d/m/Y'),
            'estado_reporte' => $r->estado_reporte,
            'tecnico' => $r->tecnico ? [
                'id' => $r->tecnico->id,
                'nombre_apellido' => $r->tecnico->nombre_apellido,
                'tipo_documento' => $r->tecnico->tipo_documento,
                'cedula' => $r->tecnico->cedula,
                'documento_completo' => $r->tecnico->documento_completo,
                'foto_url' => $r->tecnico->foto_perfil_url,
            ] : null,
            'ubicacion' => [
                'estado' => 'Táchira',
                'municipio' => $r->municipio?->nombre,
                'parroquia' => $r->parroquia?->nombre,
                'comuna' => $r->comuna?->nombre,
                'consejo_comunal' => $r->consejoComunal?->nombre,
                // BLOQUE 5: comunas y CCs adicionales con jerarquía
                'comunas_adicionales' => $r->comunasAdicionales->map(fn ($c) => [
                    'id' => $c->id,
                    'nombre' => $c->nombre,
                    'parroquia' => $c->parroquia?->nombre,
                    'municipio' => $c->parroquia?->municipio?->nombre,
                ]),
                'consejos_comunales_adicionales' => $r->consejosComunalesAdicionales->map(fn ($cc) => [
                    'id' => $cc->id,
                    'nombre' => $cc->nombre,
                    'comuna' => $cc->comuna?->nombre,
                    'parroquia' => $cc->comuna?->parroquia?->nombre,
                    'municipio' => $cc->comuna?->parroquia?->municipio?->nombre,
                ]),
            ],
            'metricas' => [
                'cantidad_comunas_atendidas' => $r->cantidad_comunas_atendidas,
                'cantidad_consejos_comunales_atendidos' => $r->cantidad_consejos_comunales_atendidos,
                'lugar' => $r->lugar,
                'cantidad_personas_atendidas' => $r->cantidad_personas_atendidas,
                'cantidad_personas_a_beneficiar' => $r->cantidad_personas_a_beneficiar,
            ],
            // BLOQUE 8: actividad simplificada — solo tipo + descripción
            'actividad' => [
                'tipo' => $r->tipo_actividad,
                'descripcion' => $r->descripcion_actividad,
            ],
            'fotos' => $r->fotos->map(fn ($f) => [
                'id' => $f->id,
                'url' => $f->url,
                'nombre_original' => $f->nombre_original,
                'orden' => $f->orden,
            ]),
            'created_at' => $r->created_at?->format('d/m/Y H:i'),
            'updated_at' => $r->updated_at?->format('d/m/Y H:i'),
        ];
    }

    /**
     * BLOQUE 5: Catálogo completo de comunas con su jerarquía (parroquia + municipio)
     * para alimentar el multi-select de "otras comunas atendidas".
     *
     * El label se enriquece con el municipio para evitar ambigüedad: dos parroquias
     * en distintos municipios pueden tener una comuna con el mismo nombre.
     * Formato: "Comuna X — Parroquia Y (Municipio Z)"
     */
    private function todasLasComunasParaMultiselect(): array
    {
        return \App\Models\Comuna::with(['parroquia:id,nombre,municipio_id', 'parroquia.municipio:id,nombre'])
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'parroquia_id'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'nombre' => $c->nombre,
                'parroquia' => $c->parroquia?->nombre,
                'municipio' => $c->parroquia?->municipio?->nombre,
                // Label compuesto que el frontend usa para mostrar y buscar
                'label' => \sprintf(
                    '%s — %s (%s)',
                    $c->nombre,
                    $c->parroquia?->nombre ?? '?',
                    $c->parroquia?->municipio?->nombre ?? '?'
                ),
            ])
            ->all();
    }

    /**
     * BLOQUE 5: Catálogo completo de consejos comunales con jerarquía completa.
     * Mismo razonamiento que las comunas — el label compuesto evita ambigüedad
     * cuando hay CCs con nombres similares en distintas parroquias.
     */
    private function todosLosCCsParaMultiselect(): array
    {
        return \App\Models\ConsejoComunal::with([
                'comuna:id,nombre,parroquia_id',
                'comuna.parroquia:id,nombre,municipio_id',
                'comuna.parroquia.municipio:id,nombre',
            ])
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'comuna_id'])
            ->map(fn ($cc) => [
                'id' => $cc->id,
                'nombre' => $cc->nombre,
                'comuna' => $cc->comuna?->nombre,
                'parroquia' => $cc->comuna?->parroquia?->nombre,
                'municipio' => $cc->comuna?->parroquia?->municipio?->nombre,
                'label' => \sprintf(
                    '%s — %s, %s (%s)',
                    $cc->nombre,
                    $cc->comuna?->nombre ?? '?',
                    $cc->comuna?->parroquia?->nombre ?? '?',
                    $cc->comuna?->parroquia?->municipio?->nombre ?? '?'
                ),
            ])
            ->all();
    }
}
