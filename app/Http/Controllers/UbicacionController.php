<?php

namespace App\Http\Controllers;

use App\Exports\UbicacionesExport;
use App\Models\Comuna;
use App\Models\ConsejoComunal;
use App\Models\Estado;
use App\Models\Municipio;
use App\Models\Parroquia;
use App\Services\UbicacionesImporter;
use App\Support\SqlLike;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * UbicacionController — gestiona el árbol jerárquico de ubicaciones.
 *
 * Estado → Municipio → Parroquia → Comuna → Consejo Comunal
 *
 * Estrategia de carga: LAZY LOADING. La página inicial solo muestra los 29 municipios
 * de Táchira. Cuando el usuario expande uno, vía AJAX se traen sus parroquias.
 * Esto evita cargar los 2207 CC al abrir la página (sería pesado y lento).
 */
class UbicacionController extends Controller
{
    /**
     * Mapa de "tipo string" → modelo Eloquent + clave del padre.
     * Centraliza los metadatos para CRUD genérico por nivel.
     */
    private const TIPOS = [
        'municipio' => ['model' => Municipio::class, 'parent_key' => 'estado_id', 'children_relation' => 'parroquias'],
        'parroquia' => ['model' => Parroquia::class, 'parent_key' => 'municipio_id', 'children_relation' => 'comunas'],
        'comuna' => ['model' => Comuna::class, 'parent_key' => 'parroquia_id', 'children_relation' => 'consejosComunales'],
        'consejo' => ['model' => ConsejoComunal::class, 'parent_key' => 'comuna_id', 'children_relation' => null],
    ];

    public function __construct(private readonly UbicacionesImporter $importer)
    {
    }

    /**
     * Página principal del árbol jerárquico.
     */
    public function index(): InertiaResponse
    {
        $estado = Estado::orderBy('nombre')->first();

        // Si no hay estado cargado, igual renderiza para que el usuario vea estado vacío
        if (!$estado) {
            return Inertia::render('Ubicaciones/Index', [
                'estado' => null,
                'municipios' => [],
                'estadisticas' => $this->estadisticasGlobales(),
            ]);
        }

        // Carga inicial: solo municipios + sus conteos hijos (sin lazy load del primer nivel)
        $municipios = Municipio::where('estado_id', $estado->id)
            ->withCount(['parroquias', 'reportes'])
            ->orderBy('nombre')
            ->get()
            ->map(fn (Municipio $m) => [
                'id' => $m->id,
                'nombre' => $m->nombre,
                'parroquias_count' => $m->parroquias_count,
                'reportes_count' => $m->reportes_count,
            ]);

        return Inertia::render('Ubicaciones/Index', [
            'estado' => ['id' => $estado->id, 'nombre' => $estado->nombre],
            'municipios' => $municipios,
            'estadisticas' => $this->estadisticasGlobales(),
        ]);
    }

    /**
     * Endpoint JSON para lazy-load de hijos. Trae los nodos de un nivel específico.
     * Llamado por TreeNode.vue cuando el usuario expande un nodo.
     */
    public function children(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nivel' => 'required|in:parroquia,comuna,consejo',
            'parent_id' => 'required|integer',
        ]);

        $children = match ($data['nivel']) {
            'parroquia' => Parroquia::where('municipio_id', $data['parent_id'])
                ->withCount(['comunas'])
                ->orderBy('nombre')
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'nombre' => $p->nombre,
                    'children_count' => $p->comunas_count,
                    'reportes_count' => 0, // parroquia no tiene reportes directos
                ]),

            'comuna' => Comuna::where('parroquia_id', $data['parent_id'])
                ->withCount(['consejosComunales'])
                ->orderBy('nombre')
                ->get()
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'nombre' => $c->nombre,
                    'children_count' => $c->consejos_comunales_count,
                    'reportes_count' => 0,
                ]),

            'consejo' => ConsejoComunal::where('comuna_id', $data['parent_id'])
                ->withCount('reportes')
                ->orderBy('nombre')
                ->get()
                ->map(fn ($cc) => [
                    'id' => $cc->id,
                    'nombre' => $cc->nombre,
                    'children_count' => 0, // hoja del árbol
                    'reportes_count' => $cc->reportes_count,
                ]),
        };

        return response()->json($children);
    }

    /**
     * Búsqueda global por nombre en cualquier nivel.
     * Devuelve hasta 30 resultados con breadcrumb completo (Estado > Municipio > ... > nombre encontrado).
     */
    public function buscar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => 'required|string|min:2|max:100',
        ]);

        // Escape de wildcards LIKE (post-auditoría MEDIUM #7): impide que `_` o `%`
        // en la query del usuario fuercen full table scans en consejos_comunales (2207 filas).
        $q = SqlLike::escape(trim($data['q']));
        $results = collect();

        $municipios = Municipio::with('estado')
            ->where('nombre', 'like', "%{$q}%")
            ->limit(10)
            ->get()
            ->map(fn ($m) => [
                'tipo' => 'municipio',
                'id' => $m->id,
                'nombre' => $m->nombre,
                'breadcrumb' => "{$m->estado->nombre} › {$m->nombre}",
                'parent_chain' => [['nivel' => 'estado', 'id' => $m->estado_id]],
            ]);
        $results = $results->concat($municipios);

        $parroquias = Parroquia::with('municipio.estado')
            ->where('nombre', 'like', "%{$q}%")
            ->limit(10)
            ->get()
            ->map(fn ($p) => [
                'tipo' => 'parroquia',
                'id' => $p->id,
                'nombre' => $p->nombre,
                'breadcrumb' => "{$p->municipio->estado->nombre} › {$p->municipio->nombre} › {$p->nombre}",
                'parent_chain' => [
                    ['nivel' => 'estado', 'id' => $p->municipio->estado_id],
                    ['nivel' => 'municipio', 'id' => $p->municipio_id],
                ],
            ]);
        $results = $results->concat($parroquias);

        $comunas = Comuna::with('parroquia.municipio.estado')
            ->where('nombre', 'like', "%{$q}%")
            ->limit(10)
            ->get()
            ->map(fn ($c) => [
                'tipo' => 'comuna',
                'id' => $c->id,
                'nombre' => $c->nombre,
                'breadcrumb' => "{$c->parroquia->municipio->estado->nombre} › {$c->parroquia->municipio->nombre} › {$c->parroquia->nombre} › {$c->nombre}",
                'parent_chain' => [
                    ['nivel' => 'municipio', 'id' => $c->parroquia->municipio_id],
                    ['nivel' => 'parroquia', 'id' => $c->parroquia_id],
                ],
            ]);
        $results = $results->concat($comunas);

        $consejos = ConsejoComunal::with('comuna.parroquia.municipio.estado')
            ->where('nombre', 'like', "%{$q}%")
            ->limit(15)
            ->get()
            ->map(fn ($cc) => [
                'tipo' => 'consejo',
                'id' => $cc->id,
                'nombre' => $cc->nombre,
                'breadcrumb' => "{$cc->comuna->parroquia->municipio->estado->nombre} › {$cc->comuna->parroquia->municipio->nombre} › {$cc->comuna->parroquia->nombre} › {$cc->comuna->nombre} › {$cc->nombre}",
                'parent_chain' => [
                    ['nivel' => 'municipio', 'id' => $cc->comuna->parroquia->municipio_id],
                    ['nivel' => 'parroquia', 'id' => $cc->comuna->parroquia_id],
                    ['nivel' => 'comuna', 'id' => $cc->comuna_id],
                ],
            ]);
        $results = $results->concat($consejos);

        return response()->json($results->take(30)->values()->all());
    }

    /**
     * Vista plana paginada para la pestaña "Tabla" del módulo Ubicaciones.
     *
     * Trae las 2207 CC con su contexto completo (Municipio › Parroquia › Comuna › CC)
     * + cantidad de reportes asociados. Soporta filtros encadenados, búsqueda libre,
     * ordenamiento por columna y paginación a 25 filas.
     */
    public function flatList(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:100',
            'municipio_id' => 'nullable|integer|exists:municipios,id',
            'parroquia_id' => 'nullable|integer|exists:parroquias,id',
            'comuna_id' => 'nullable|integer|exists:comunas,id',
            'solo_con_reportes' => 'nullable|boolean',
            'sort' => 'nullable|in:municipio,parroquia,comuna,consejo,reportes',
            'dir' => 'nullable|in:asc,desc',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:10|max:100',
        ]);

        $query = ConsejoComunal::query()
            ->join('comunas', 'consejos_comunales.comuna_id', '=', 'comunas.id')
            ->join('parroquias', 'comunas.parroquia_id', '=', 'parroquias.id')
            ->join('municipios', 'parroquias.municipio_id', '=', 'municipios.id')
            ->select([
                'consejos_comunales.id as cc_id',
                'consejos_comunales.nombre as consejo',
                'comunas.id as comuna_id',
                'comunas.nombre as comuna',
                'parroquias.id as parroquia_id',
                'parroquias.nombre as parroquia',
                'municipios.id as municipio_id',
                'municipios.nombre as municipio',
            ])
            // Subquery para conteo de reportes por CC (más confiable que withCount con joins)
            ->selectSub(
                fn ($q) => $q->from('reportes')
                    ->whereColumn('consejo_comunal_id', 'consejos_comunales.id')
                    ->whereNull('deleted_at')
                    ->selectRaw('count(*)'),
                'reportes_count'
            );

        // ─── Filtros ───
        if (!empty($filters['q'])) {
            // Escape de wildcards LIKE (post-auditoría MEDIUM #7): impide que `_` o `%`
            // en la query del usuario fuercen full table scans en consejos_comunales (2207 filas).
            $q = SqlLike::escape(trim($filters['q']));
            $query->where(function ($w) use ($q) {
                $w->where('consejos_comunales.nombre', 'like', "%{$q}%")
                  ->orWhere('comunas.nombre', 'like', "%{$q}%")
                  ->orWhere('parroquias.nombre', 'like', "%{$q}%")
                  ->orWhere('municipios.nombre', 'like', "%{$q}%");
            });
        }
        if (!empty($filters['municipio_id'])) {
            $query->where('municipios.id', $filters['municipio_id']);
        }
        if (!empty($filters['parroquia_id'])) {
            $query->where('parroquias.id', $filters['parroquia_id']);
        }
        if (!empty($filters['comuna_id'])) {
            $query->where('comunas.id', $filters['comuna_id']);
        }
        if (!empty($filters['solo_con_reportes'])) {
            $query->whereExists(fn ($q) => $q->from('reportes')
                ->whereColumn('consejo_comunal_id', 'consejos_comunales.id')
                ->whereNull('deleted_at'));
        }

        // ─── Ordenamiento ───
        $sort = $filters['sort'] ?? 'municipio';
        $dir = $filters['dir'] ?? 'asc';
        $sortColumn = match ($sort) {
            'municipio' => 'municipios.nombre',
            'parroquia' => 'parroquias.nombre',
            'comuna' => 'comunas.nombre',
            'consejo' => 'consejos_comunales.nombre',
            'reportes' => 'reportes_count',
            default => 'municipios.nombre',
        };
        $query->orderBy($sortColumn, $dir);

        // Orden secundario para resultados consistentes cuando hay empates
        if ($sort !== 'municipio') $query->orderBy('municipios.nombre');
        if ($sort !== 'parroquia') $query->orderBy('parroquias.nombre');
        if ($sort !== 'comuna') $query->orderBy('comunas.nombre');
        if ($sort !== 'consejo') $query->orderBy('consejos_comunales.nombre');

        $perPage = $filters['per_page'] ?? 25;
        $paginated = $query->paginate($perPage)->withQueryString();

        return response()->json($paginated);
    }

    /**
     * Exporta las ubicaciones a Excel (.xlsx) con cintillo institucional.
     * Acepta los MISMOS filtros que flatList — exporta solo lo que el usuario está viendo.
     */
    public function exportarExcel(Request $request): BinaryFileResponse
    {
        $filters = $this->validateFiltrosExport($request);

        // IMPORTANTE: Eloquent::toArray() — NO castear con (array) porque los atributos
        // viven en una propiedad protegida y el cast directo da keys raras o vacías.
        // El type hint explícito ayuda al static analyzer a saber qué método llamar.
        $rows = $this->buildExportQuery($filters)
            ->get()
            ->map(fn (ConsejoComunal $r) => $r->toArray());

        $filename = 'ubicaciones-' . now()->format('Y-m-d-His') . '.xlsx';
        $filtrosLegibles = $this->filtrosExportLegibles($filters);

        return Excel::download(new UbicacionesExport($rows, $filtrosLegibles), $filename);
    }

    /**
     * Crea un nodo nuevo en el nivel indicado.
     */
    public function store(Request $request, string $tipo): JsonResponse
    {
        $this->validarTipo($tipo);

        $config = self::TIPOS[$tipo];
        $modelClass = $config['model'];
        $parentKey = $config['parent_key'];

        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'parent_id' => 'required|integer',
        ]);

        // Validar que el padre exista
        $parentModel = match ($tipo) {
            'municipio' => Estado::find($data['parent_id']),
            'parroquia' => Municipio::find($data['parent_id']),
            'comuna' => Parroquia::find($data['parent_id']),
            'consejo' => Comuna::find($data['parent_id']),
        };
        if (!$parentModel) {
            return response()->json(['message' => 'El padre seleccionado no existe.'], 422);
        }

        // Anti-duplicado: el nombre debe ser único bajo ese padre
        $existe = $modelClass::where($parentKey, $data['parent_id'])
            ->whereRaw('LOWER(nombre) = ?', [Str::lower(trim($data['nombre']))])
            ->exists();
        if ($existe) {
            return response()->json([
                'message' => "Ya existe un/a {$tipo} con ese nombre bajo este padre.",
            ], 422);
        }

        $created = $modelClass::create([
            $parentKey => $data['parent_id'],
            'nombre' => trim($data['nombre']),
        ]);

        return response()->json([
            'id' => $created->id,
            'nombre' => $created->nombre,
            'children_count' => 0,
            'reportes_count' => 0,
        ], 201);
    }

    /**
     * Actualiza el nombre de un nodo.
     */
    public function update(Request $request, string $tipo, int $id): JsonResponse
    {
        $this->validarTipo($tipo);

        $config = self::TIPOS[$tipo];
        $modelClass = $config['model'];
        $parentKey = $config['parent_key'];

        $data = $request->validate([
            'nombre' => 'required|string|max:255',
        ]);

        $node = $modelClass::findOrFail($id);

        // Anti-duplicado: nombre único entre hermanos (mismo padre)
        $existe = $modelClass::where($parentKey, $node->{$parentKey})
            ->where('id', '!=', $id)
            ->whereRaw('LOWER(nombre) = ?', [Str::lower(trim($data['nombre']))])
            ->exists();
        if ($existe) {
            return response()->json([
                'message' => "Ya existe otro/a {$tipo} con ese nombre bajo este padre.",
            ], 422);
        }

        $node->update(['nombre' => trim($data['nombre'])]);

        return response()->json([
            'id' => $node->id,
            'nombre' => $node->nombre,
        ]);
    }

    /**
     * Elimina un nodo solo si NO tiene hijos NI reportes asociados.
     */
    public function destroy(string $tipo, int $id): JsonResponse
    {
        $this->validarTipo($tipo);

        $config = self::TIPOS[$tipo];
        $modelClass = $config['model'];
        $node = $modelClass::findOrFail($id);

        // Validación 1: no debe tener reportes asociados (a cualquier nivel)
        $tieneReportes = match ($tipo) {
            'municipio' => \App\Models\Reporte::where('municipio_id', $id)->exists(),
            'parroquia' => \App\Models\Reporte::where('parroquia_id', $id)->exists(),
            'comuna' => \App\Models\Reporte::where('comuna_id', $id)->exists(),
            'consejo' => \App\Models\Reporte::where('consejo_comunal_id', $id)->exists(),
        };
        if ($tieneReportes) {
            return response()->json([
                'message' => "No se puede eliminar este/a {$tipo} porque hay reportes asociados.",
            ], 422);
        }

        // Validación 2: no debe tener hijos (la cascada eliminaría jerarquía completa, demasiado riesgoso)
        if ($config['children_relation']) {
            $hijosCount = $node->{$config['children_relation']}()->count();
            if ($hijosCount > 0) {
                return response()->json([
                    'message' => "No se puede eliminar: este/a {$tipo} tiene {$hijosCount} hijos. Elimina primero los hijos.",
                ], 422);
            }
        }

        $node->delete();

        // Mensaje legible para que el frontend muestre el banner verde de éxito.
        $tipoLabel = match ($tipo) {
            'municipio' => 'Municipio',
            'parroquia' => 'Parroquia',
            'comuna' => 'Comuna',
            'consejo' => 'Consejo Comunal',
        };
        return response()->json([
            'ok' => true,
            'message' => "{$tipoLabel} \"{$node->nombre}\" eliminado correctamente.",
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Importación desde Excel
    // ─────────────────────────────────────────────────────────────────

    public function importarForm(): InertiaResponse
    {
        return Inertia::render('Ubicaciones/Importar', [
            'estadisticas_actuales' => $this->estadisticasGlobales(),
        ]);
    }

    /**
     * Sube el Excel + corre dry-run + retorna preview con stats.
     * Guarda el archivo temporalmente y devuelve un token para que el confirmar lo recoja.
     */
    public function importarPreview(Request $request): JsonResponse
    {
        $request->validate([
            // Solo .xlsx (post-auditoría MEDIUM #5): el formato .xls legacy (BIFF) tuvo
            // CVEs históricos de RCE en PhpSpreadsheet. .xlsx es el formato moderno y único soportado.
            // Tamaño reducido a 5 MB (suficiente para ~10k filas con headers normales).
            'archivo' => 'required|file|mimes:xlsx|max:5120',
        ]);

        // Guardar archivo temporal con token único
        $token = Str::random(40);
        $extension = $request->file('archivo')->getClientOriginalExtension();
        $tempPath = "import-temp/{$token}.{$extension}";
        $request->file('archivo')->storeAs('import-temp', "{$token}.{$extension}", 'local');

        // Path absoluto para el importer
        $absolutePath = storage_path("app/private/{$tempPath}");
        if (!file_exists($absolutePath)) {
            // En algunos disks Laravel 11 guarda en app/ no app/private/
            $absolutePath = storage_path("app/{$tempPath}");
        }

        if (!file_exists($absolutePath)) {
            return response()->json(['message' => 'No se pudo guardar el archivo temporal.'], 500);
        }

        try {
            // Dry-run: simula el import sin persistir cambios
            $stats = $this->importer->import($absolutePath, dryRun: true);

            // También extraer las primeras 50 filas como preview visual
            $preview = $this->extraerPreviewFilas($absolutePath);

            return response()->json([
                'token' => $token,
                'stats' => $stats,
                'preview' => $preview,
                'archivo' => [
                    'nombre' => $request->file('archivo')->getClientOriginalName(),
                    'tamano_kb' => round($request->file('archivo')->getSize() / 1024, 2),
                ],
            ]);
        } catch (\Throwable $e) {
            // Limpiar archivo si algo falló
            if (Storage::disk('local')->exists($tempPath)) {
                Storage::disk('local')->delete($tempPath);
            }
            // Log detallado server-side (incluyendo línea/archivo del error)
            // SIN exponer al cliente paths absolutos ni versiones de librerías.
            Log::error('[UbicacionesImport] preview falló', [
                'message' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);
            return response()->json([
                'message' => 'No se pudo procesar el archivo. Verifica que sea un .xlsx válido con los headers esperados.',
            ], 422);
        }
    }

    /**
     * Confirma la importación: lee el archivo del token y lo persiste de verdad.
     */
    public function importarConfirmar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => 'required|string|size:40',
        ]);

        // Buscar el archivo del token usando first-class callable syntax (PHP 8.1+)
        $disk = Storage::disk('local');
        $tempPath = collect(['xlsx', 'xls'])
            ->map(fn ($ext) => "import-temp/{$data['token']}.{$ext}")
            ->first($disk->exists(...));

        if (!$tempPath) {
            return response()->json(['message' => 'El archivo temporal expiró o no existe. Sube de nuevo.'], 422);
        }

        // Path absoluto
        $absolutePath = storage_path("app/private/{$tempPath}");
        if (!file_exists($absolutePath)) {
            $absolutePath = storage_path("app/{$tempPath}");
        }

        try {
            // Real import (sin dry-run)
            $stats = $this->importer->import($absolutePath, dryRun: false);

            // Limpiar archivo temporal
            Storage::disk('local')->delete($tempPath);

            return response()->json([
                'ok' => true,
                'stats' => $stats,
                'estadisticas_actuales' => $this->estadisticasGlobales(),
            ]);
        } catch (\Throwable $e) {
            // Log server-side completo, mensaje genérico al cliente.
            Log::error('[UbicacionesImport] confirmar falló', [
                'message' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);
            return response()->json([
                'message' => 'La importación no pudo completarse. Revisa el archivo y reintenta.',
            ], 500);
        }
    }

    /**
     * Descarga una plantilla XLSX vacía con los headers esperados.
     */
    public function plantillaDescargar()
    {
        $headers = ['Estado', 'Municipio', 'Parroquia', 'Comuna', 'Consejo Comunal'];
        $ejemploRow = ['Táchira', 'San Cristóbal', 'La Concordia', 'Comuna Pueblo Nuevo', 'CC Barrio El Progreso'];

        // Generar XLSX en memoria con PhpSpreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Ubicaciones');
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($ejemploRow, null, 'A2');

        // Style del header
        $sheet->getStyle('A1:E1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1B5E20']],
            'alignment' => ['horizontal' => 'center'],
        ]);
        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->getRowDimension(1)->setRowHeight(24);

        // Output como descarga
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'plantilla_ubicaciones_');
        $writer->save($tempFile);

        return response()->download(
            $tempFile,
            'plantilla-ubicaciones-cvaup-tachira.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Helpers privados
    // ─────────────────────────────────────────────────────────────────

    private function validarTipo(string $tipo): void
    {
        if (!isset(self::TIPOS[$tipo])) {
            abort(400, "Tipo de ubicación inválido: {$tipo}");
        }
    }

    private function estadisticasGlobales(): array
    {
        return [
            'estados' => Estado::count(),
            'municipios' => Municipio::count(),
            'parroquias' => Parroquia::count(),
            'comunas' => Comuna::count(),
            'consejos' => ConsejoComunal::count(),
        ];
    }

    /**
     * Validación común de filtros para los endpoints de exportación.
     * Reutiliza el mismo set que flatList (consistencia: lo que ves es lo que exportas).
     */
    private function validateFiltrosExport(Request $request): array
    {
        return $request->validate([
            'q' => 'nullable|string|max:100',
            'municipio_id' => 'nullable|integer|exists:municipios,id',
            'parroquia_id' => 'nullable|integer|exists:parroquias,id',
            'comuna_id' => 'nullable|integer|exists:comunas,id',
            'solo_con_reportes' => 'nullable|boolean',
        ]);
    }

    /**
     * Construye el query plano para los exports — sin paginación, trae TODO lo filtrado.
     * Es la misma estructura del flatList sin el ->paginate().
     */
    private function buildExportQuery(array $filters): Builder
    {
        $query = ConsejoComunal::query()
            ->join('comunas', 'consejos_comunales.comuna_id', '=', 'comunas.id')
            ->join('parroquias', 'comunas.parroquia_id', '=', 'parroquias.id')
            ->join('municipios', 'parroquias.municipio_id', '=', 'municipios.id')
            ->join('estados', 'municipios.estado_id', '=', 'estados.id')
            ->select([
                'estados.nombre as estado',
                'municipios.nombre as municipio',
                'parroquias.nombre as parroquia',
                'comunas.nombre as comuna',
                'consejos_comunales.nombre as consejo',
            ])
            ->selectSub(
                fn ($q) => $q->from('reportes')
                    ->whereColumn('consejo_comunal_id', 'consejos_comunales.id')
                    ->whereNull('deleted_at')
                    ->selectRaw('count(*)'),
                'reportes_count'
            )
            ->orderBy('municipios.nombre')
            ->orderBy('parroquias.nombre')
            ->orderBy('comunas.nombre')
            ->orderBy('consejos_comunales.nombre');

        if (!empty($filters['q'])) {
            // Escape de wildcards LIKE (post-auditoría MEDIUM #7).
            $q = SqlLike::escape(trim($filters['q']));
            $query->where(function ($w) use ($q) {
                $w->where('consejos_comunales.nombre', 'like', "%{$q}%")
                  ->orWhere('comunas.nombre', 'like', "%{$q}%")
                  ->orWhere('parroquias.nombre', 'like', "%{$q}%")
                  ->orWhere('municipios.nombre', 'like', "%{$q}%");
            });
        }
        if (!empty($filters['municipio_id'])) {
            $query->where('municipios.id', $filters['municipio_id']);
        }
        if (!empty($filters['parroquia_id'])) {
            $query->where('parroquias.id', $filters['parroquia_id']);
        }
        if (!empty($filters['comuna_id'])) {
            $query->where('comunas.id', $filters['comuna_id']);
        }
        if (!empty($filters['solo_con_reportes'])) {
            $query->whereExists(fn ($q) => $q->from('reportes')
                ->whereColumn('consejo_comunal_id', 'consejos_comunales.id')
                ->whereNull('deleted_at'));
        }

        return $query;
    }

    /**
     * Convierte filtros aplicados a strings legibles para mostrar en el subtítulo del export.
     */
    private function filtrosExportLegibles(array $filters): array
    {
        $textos = [];

        if (!empty($filters['q'])) {
            $textos[] = "Búsqueda: \"{$filters['q']}\"";
        }
        if (!empty($filters['municipio_id'])) {
            $m = Municipio::find($filters['municipio_id']);
            if ($m) $textos[] = "Municipio: {$m->nombre}";
        }
        if (!empty($filters['parroquia_id'])) {
            $p = Parroquia::find($filters['parroquia_id']);
            if ($p) $textos[] = "Parroquia: {$p->nombre}";
        }
        if (!empty($filters['comuna_id'])) {
            $c = Comuna::find($filters['comuna_id']);
            if ($c) $textos[] = "Comuna: {$c->nombre}";
        }
        if (!empty($filters['solo_con_reportes'])) {
            $textos[] = 'Solo CC con reportes asociados';
        }

        return $textos;
    }

    /**
     * Extrae las primeras 50 filas del Excel para preview visual.
     */
    private function extraerPreviewFilas(string $absolutePath): array
    {
        // Excel::toArray() espera un objeto Import — cuando solo queremos leer datos crudos
        // sin definir una Import class, pasamos un stdClass vacío. Maatwebsite lo ignora,
        // y el static analyzer queda satisfecho con el contrato del tipo "object".
        $sheets = Excel::toArray(new \stdClass(), $absolutePath);
        $rows = $sheets[0] ?? [];
        \array_shift($rows); // descarta header (no usamos el valor — solo recorremos las filas restantes)

        $preview = [];
        foreach (\array_slice($rows, 0, 50) as $idx => $row) {
            $preview[] = [
                'fila' => $idx + 2,
                'estado' => trim((string) ($row[0] ?? '')),
                'municipio' => trim((string) ($row[1] ?? '')),
                'parroquia' => trim((string) ($row[2] ?? '')),
                'comuna' => trim((string) ($row[3] ?? '')),
                'consejo' => trim((string) ($row[4] ?? '')),
            ];
        }

        return [
            'rows' => $preview,
            'total_rows_archivo' => \count($rows),
            'mostrando' => \count($preview),
        ];
    }
}
