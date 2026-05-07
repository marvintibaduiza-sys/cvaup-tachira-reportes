<?php

namespace App\Services;

use App\Models\Comuna;
use App\Models\ConsejoComunal;
use App\Models\Estado;
use App\Models\Municipio;
use App\Models\Parroquia;
use Closure;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;

/**
 * UbicacionesImporter
 *
 * Servicio que importa la jerarquía completa de ubicaciones desde un archivo Excel.
 *
 * Estructura esperada del Excel (5 columnas, header en fila 1):
 *   A: Estado | B: Municipio | C: Parroquia | D: Comuna | E: Consejo Comunal
 *
 * Idempotente: usa firstOrCreate por nivel jerárquico → puedes re-correr el comando
 * sin duplicar nada (los repetidos van al contador "omitidos").
 *
 * Reutilizable: el comando CLI (ubicaciones:import) y el controller de Fase 9
 * llamarán al mismo método import().
 */
class UbicacionesImporter
{
    public const HEADERS_ESPERADOS = ['Estado', 'Municipio', 'Parroquia', 'Comuna', 'Consejo Comunal'];

    public array $stats;

    public function __construct()
    {
        $this->reset();
    }

    public function reset(): void
    {
        $this->stats = [
            'creados' => [
                'estados' => 0,
                'municipios' => 0,
                'parroquias' => 0,
                'comunas' => 0,
                'consejos' => 0,
            ],
            'omitidos' => [
                'estados' => 0,
                'municipios' => 0,
                'parroquias' => 0,
                'comunas' => 0,
                'consejos' => 0,
            ],
            'filas_procesadas' => 0,
            'filas_saltadas_vacias' => 0,
            'errores' => [],
        ];
    }

    /**
     * Importa el archivo Excel.
     *
     * @param  string  $filePath  Ruta absoluta al .xlsx
     * @param  bool  $dryRun  Si true, hace rollback al final (no persiste).
     * @param  Closure|null  $onProgress  Callback(rowNumber, stats) por cada fila procesada.
     */
    public function import(string $filePath, bool $dryRun = false, ?Closure $onProgress = null): array
    {
        $this->reset();

        if (!file_exists($filePath)) {
            throw new RuntimeException("Archivo no encontrado: {$filePath}");
        }

        $sheet = Excel::toArray([], $filePath)[0] ?? [];

        if (empty($sheet)) {
            throw new RuntimeException('La hoja del Excel está vacía.');
        }

        $this->validarHeaders($sheet[0]);

        $rows = array_slice($sheet, 1); // skip header

        $work = function () use ($rows, $onProgress) {
            foreach ($rows as $idx => $row) {
                $rowNumber = $idx + 2; // +2 porque empezamos en fila 2 (header en 1)
                $this->procesarFila($row, $rowNumber);
                $onProgress?->call($this, $rowNumber, $this->stats);
            }
        };

        if ($dryRun) {
            DB::beginTransaction();
            try {
                $work();
            } finally {
                DB::rollBack();
            }
        } else {
            DB::transaction($work);
        }

        return $this->stats;
    }

    private function validarHeaders(array $headerRow): void
    {
        $headers = array_map(fn ($v) => trim((string) ($v ?? '')), array_slice($headerRow, 0, 5));

        foreach (self::HEADERS_ESPERADOS as $i => $esperado) {
            $recibido = $headers[$i] ?? '';
            if (mb_strtolower($recibido) !== mb_strtolower($esperado)) {
                $col = chr(65 + $i);
                throw new RuntimeException(
                    "Cabecera incorrecta en columna {$col}. Esperaba '{$esperado}', recibí '{$recibido}'."
                );
            }
        }
    }

    /**
     * Sistema MONO-ESTADO: este sistema solo gestiona ubicaciones del estado Táchira.
     * El importer NO permite crear estados nuevos — si viene otro estado, error explícito.
     */
    private const ESTADO_OFICIAL = 'Táchira';

    /**
     * Normaliza un nombre de estado para comparación case-insensitive y sin tildes.
     * "Táchira" / "Tachira" / "TÁCHIRA" / "tachira" → "tachira"
     */
    private function normalizarEstado(string $value): string
    {
        return mb_strtolower(\Illuminate\Support\Str::ascii($value));
    }

    private function procesarFila(array $row, int $rowNumber): void
    {
        $estado = $this->limpiar($row[0] ?? null);
        $municipio = $this->limpiar($row[1] ?? null);
        $parroquia = $this->limpiar($row[2] ?? null);
        $comuna = $this->limpiar($row[3] ?? null);
        $cc = $this->limpiar($row[4] ?? null);

        // Fila completamente vacía → saltar silenciosamente
        if ($estado === '' && $municipio === '' && $parroquia === '' && $comuna === '' && $cc === '') {
            $this->stats['filas_saltadas_vacias']++;
            return;
        }

        // Fila parcialmente llena → error de data, no importar
        if ($estado === '' || $municipio === '' || $parroquia === '' || $comuna === '' || $cc === '') {
            $this->stats['errores'][] = sprintf(
                'Fila %d: campos incompletos (Estado="%s", Municipio="%s", Parroquia="%s", Comuna="%s", CC="%s")',
                $rowNumber, $estado, $municipio, $parroquia, $comuna, $cc
            );
            return;
        }

        // GUARD MONO-ESTADO (post-test 5.13): solo se admite Táchira.
        // Bloqueamos antes de crear nada para evitar contaminar la BD.
        if ($this->normalizarEstado($estado) !== $this->normalizarEstado(self::ESTADO_OFICIAL)) {
            $this->stats['errores'][] = sprintf(
                'Fila %d: solo se admite el estado "%s" (CVAUP Táchira es mono-estado). Recibido: "%s"',
                $rowNumber, self::ESTADO_OFICIAL, $estado
            );
            return;
        }

        // Usar SIEMPRE el estado oficial existente (no crear duplicados con typos).
        // firstOrCreate por si acaso la BD está fresca y aún no tiene el seed.
        $estadoModel = $this->firstOrCreate(
            Estado::class,
            ['nombre' => self::ESTADO_OFICIAL],
            'estados'
        );

        $municipioModel = $this->firstOrCreate(
            Municipio::class,
            ['estado_id' => $estadoModel->id, 'nombre' => $municipio],
            'municipios'
        );

        $parroquiaModel = $this->firstOrCreate(
            Parroquia::class,
            ['municipio_id' => $municipioModel->id, 'nombre' => $parroquia],
            'parroquias'
        );

        $comunaModel = $this->firstOrCreate(
            Comuna::class,
            ['parroquia_id' => $parroquiaModel->id, 'nombre' => $comuna],
            'comunas'
        );

        $this->firstOrCreate(
            ConsejoComunal::class,
            ['comuna_id' => $comunaModel->id, 'nombre' => $cc],
            'consejos'
        );

        $this->stats['filas_procesadas']++;
    }

    private function firstOrCreate(string $modelClass, array $attrs, string $stat): object
    {
        $existing = $modelClass::where($attrs)->first();
        if ($existing) {
            $this->stats['omitidos'][$stat]++;
            return $existing;
        }

        $this->stats['creados'][$stat]++;
        return $modelClass::create($attrs);
    }

    private function limpiar(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }
}
