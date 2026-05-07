<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTecnicoRequest;
use App\Http\Requests\UpdateTecnicoRequest;
use App\Models\Municipio;
use App\Models\Tecnico;
use App\Services\PhotoCompressor;
use App\Support\SqlLike;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TecnicoController extends Controller
{
    public function __construct(private readonly PhotoCompressor $photoCompressor)
    {
    }

    /**
     * Listado paginado con filtros (estado, búsqueda).
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:255',
            'estado' => 'nullable|in:activo,inactivo,todos',
        ]);

        $query = Tecnico::query()->withCount('reportes');

        if (!empty($filters['q'])) {
            // Escape de wildcards LIKE (post-auditoría MEDIUM #7).
            $q = SqlLike::escape(trim($filters['q']));
            $query->where(function ($w) use ($q) {
                $w->where('nombre_apellido', 'like', "%{$q}%")
                  ->orWhere('cedula', 'like', "%{$q}%");
            });
        }

        $estado = $filters['estado'] ?? 'todos';
        if ($estado !== 'todos') {
            $query->where('estado', $estado);
        }

        $tecnicos = $query->orderBy('nombre_apellido')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Tecnico $t) => [
                'id' => $t->id,
                'nombre_apellido' => $t->nombre_apellido,
                'cedula' => $t->cedula,
                'telefono' => $t->telefono,
                'especialidad' => $t->especialidad,
                'estado' => $t->estado,
                'foto_url' => $t->foto_perfil_url, // accessor autenticado (CRITICAL #2)
                'total_reportes' => $t->reportes_count,
            ]);

        return Inertia::render('Tecnicos/Index', [
            'tecnicos' => $tecnicos,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'estado' => $estado,
            ],
            'totales' => [
                'activos' => Tecnico::activos()->count(),
                'inactivos' => Tecnico::inactivos()->count(),
            ],
        ]);
    }

    /**
     * Formulario de creación.
     */
    public function create(): Response
    {
        return Inertia::render('Tecnicos/Create', [
            'municipios' => $this->municipiosOptions(),
        ]);
    }

    /**
     * Persiste un nuevo técnico.
     */
    public function store(StoreTecnicoRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $municipioIds = $data['municipio_ids'] ?? [];
        unset($data['municipio_ids'], $data['foto']);

        $tecnico = DB::transaction(function () use ($data, $municipioIds, $request) {
            $tecnico = Tecnico::create($data);

            if ($request->hasFile('foto')) {
                $info = $this->photoCompressor->compressAndStore(
                    $request->file('foto'),
                    "fotos/tecnicos/{$tecnico->id}",
                    'perfil'
                );
                $tecnico->update(['foto_perfil' => $info['ruta']]);
            }

            if (!empty($municipioIds)) {
                $tecnico->municipios()->sync($municipioIds);
            }

            return $tecnico;
        });

        return redirect()
            ->route('tecnicos.show', $tecnico)
            ->with('success', "Técnico {$tecnico->nombre_apellido} registrado correctamente.");
    }

    /**
     * Perfil del técnico (datos + estadísticas).
     */
    public function show(Tecnico $tecnico): Response
    {
        $tecnico->load(['municipios:id,nombre', 'reportes' => fn ($q) => $q->latest('fecha')->limit(20)]);

        $municipiosTrabajados = $tecnico->reportes()
            ->join('municipios', 'reportes.municipio_id', '=', 'municipios.id')
            ->distinct()
            ->pluck('municipios.nombre');

        $promedioPersonas = $tecnico->reportes()->avg('cantidad_personas_atendidas');

        return Inertia::render('Tecnicos/Show', [
            'tecnico' => [
                'id' => $tecnico->id,
                'nombre_apellido' => $tecnico->nombre_apellido,
                'cedula' => $tecnico->cedula,
                'telefono' => $tecnico->telefono,
                'especialidad' => $tecnico->especialidad,
                'estado' => $tecnico->estado,
                'foto_url' => $tecnico->foto_perfil_url, // accessor autenticado (CRITICAL #2)
                'municipios_asignados' => $tecnico->municipios->map(fn ($m) => ['id' => $m->id, 'nombre' => $m->nombre]),
                'created_at' => $tecnico->created_at?->format('d/m/Y'),
            ],
            'estadisticas' => [
                'total_reportes' => $tecnico->reportes()->count(),
                'promedio_personas' => $promedioPersonas ? (int) round($promedioPersonas) : 0,
                'municipios_trabajados' => $municipiosTrabajados,
                'tasa_cumplimiento_mes' => $tecnico->tasaCumplimientoMesActual(),
                'ultimo_reporte' => $tecnico->reportes()->latest('fecha')->first()?->only(['id', 'fecha', 'titulo_actividad']),
            ],
            'reportes_recientes' => $tecnico->reportes->map(fn ($r) => [
                'id' => $r->id,
                'fecha' => $r->fecha?->format('d/m/Y'),
                'titulo_actividad' => $r->titulo_actividad,
                'estado_reporte' => $r->estado_reporte,
            ]),
        ]);
    }

    /**
     * Formulario de edición.
     */
    public function edit(Tecnico $tecnico): Response
    {
        $tecnico->load('municipios:id');

        return Inertia::render('Tecnicos/Edit', [
            'tecnico' => [
                'id' => $tecnico->id,
                'nombre_apellido' => $tecnico->nombre_apellido,
                'cedula' => $tecnico->cedula,
                'telefono' => $tecnico->telefono,
                'especialidad' => $tecnico->especialidad,
                'estado' => $tecnico->estado,
                'foto_url' => $tecnico->foto_perfil_url, // accessor autenticado (CRITICAL #2)
                'municipio_ids' => $tecnico->municipios->pluck('id'),
            ],
            'municipios' => $this->municipiosOptions(),
        ]);
    }

    /**
     * Actualiza un técnico existente.
     */
    public function update(UpdateTecnicoRequest $request, Tecnico $tecnico): RedirectResponse
    {
        $data = $request->validated();
        $municipioIds = $data['municipio_ids'] ?? [];
        $eliminarFoto = (bool) ($data['eliminar_foto'] ?? false);

        unset($data['municipio_ids'], $data['foto'], $data['eliminar_foto']);

        DB::transaction(function () use ($data, $municipioIds, $eliminarFoto, $request, $tecnico) {
            $tecnico->update($data);

            if ($eliminarFoto && $tecnico->foto_perfil) {
                $this->photoCompressor->delete($tecnico->foto_perfil);
                $tecnico->update(['foto_perfil' => null]);
            }

            if ($request->hasFile('foto')) {
                if ($tecnico->foto_perfil) {
                    $this->photoCompressor->delete($tecnico->foto_perfil);
                }
                $info = $this->photoCompressor->compressAndStore(
                    $request->file('foto'),
                    "fotos/tecnicos/{$tecnico->id}",
                    'perfil'
                );
                $tecnico->update(['foto_perfil' => $info['ruta']]);
            }

            $tecnico->municipios()->sync($municipioIds);
        });

        return redirect()
            ->route('tecnicos.show', $tecnico)
            ->with('success', 'Técnico actualizado correctamente.');
    }

    /**
     * Soft delete del técnico.
     * BLOQUEADO si tiene reportes (solo desactivar permitido).
     */
    public function destroy(Tecnico $tecnico): RedirectResponse
    {
        if ($tecnico->reportes()->exists()) {
            return back()->with(
                'error',
                'No se puede eliminar este técnico porque tiene reportes asociados. Desactívalo en su lugar.'
            );
        }

        $nombre = $tecnico->nombre_apellido;

        if ($tecnico->foto_perfil) {
            $this->photoCompressor->delete($tecnico->foto_perfil);
        }
        $tecnico->delete();

        return redirect()
            ->route('tecnicos.index')
            ->with('success', "Técnico {$nombre} eliminado.");
    }

    /**
     * Toggle activo/inactivo.
     */
    public function toggleEstado(Tecnico $tecnico): RedirectResponse
    {
        $nuevoEstado = $tecnico->estado === 'activo' ? 'inactivo' : 'activo';
        $tecnico->update(['estado' => $nuevoEstado]);

        return back()->with(
            'success',
            "Técnico {$tecnico->nombre_apellido} marcado como " . ($nuevoEstado === 'activo' ? 'activo' : 'inactivo') . '.'
        );
    }

    /**
     * Lista de municipios disponibles agrupados por estado para el multi-select.
     */
    private function municipiosOptions(): array
    {
        return Municipio::with('estado:id,nombre')
            ->orderBy('nombre')
            ->get()
            ->map(fn (Municipio $m) => [
                'id' => $m->id,
                'nombre' => $m->nombre,
                'estado' => $m->estado->nombre,
            ])
            ->all();
    }
}
