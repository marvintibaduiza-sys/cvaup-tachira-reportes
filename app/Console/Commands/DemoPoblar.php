<?php

namespace App\Console\Commands;

use App\Models\ConsejoComunal;
use App\Models\Reporte;
use App\Models\Tecnico;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Pobla la BD con datos ficticios para visualizar gráficos del Dashboard.
 *
 * SEGURIDAD — tres guardas en cascada:
 *  1. Bloquea ejecución en producción (config/app.php → APP_ENV=production)
 *  2. Pide confirmación interactiva al usuario antes de insertar
 *  3. Marca CADA registro creado con prefijo "[DEMO]" para poder identificarlo
 *     y eliminarlo después con `php artisan demo:limpiar`
 *
 * Uso típico (solo local/dev):
 *   php artisan demo:poblar
 *   php artisan demo:poblar --tecnicos=10 --reportes=150
 */
class DemoPoblar extends Command
{
    protected $signature = 'demo:poblar
                            {--tecnicos=5 : Cantidad de técnicos demo a crear si faltan}
                            {--reportes=80 : Cantidad de reportes demo a crear}
                            {--force : Saltar la confirmación interactiva (uso en CI/scripting)}';

    protected $description = '[SOLO DESARROLLO] Pobla la BD con técnicos y reportes ficticios para visualizar los charts del Dashboard. NO usar en producción.';

    /**
     * Marcador para identificar todos los registros creados por este comando.
     * Si cambias esto, actualízalo también en DemoLimpiar.
     */
    public const MARCADOR = '[DEMO]';

    /**
     * Cédulas demo: parten en 9999000001, 9999000002, etc.
     * Distinguibles a simple vista de cédulas venezolanas reales (que tienen 7-8 dígitos).
     */
    public const CEDULA_PREFIX = '9999';

    public function handle(): int
    {
        // ── GUARD 1: bloquear producción ─────────────────────────────────
        if (app()->isProduction()) {
            $this->error('❌ Este comando NO se puede ejecutar en producción.');
            $this->line('   Detectado APP_ENV=production en .env.');
            $this->line('   Si esto es un error, revisa tu configuración de entorno.');
            return self::FAILURE;
        }

        $cantidadTecnicos = (int) $this->option('tecnicos');
        $cantidadReportes = (int) $this->option('reportes');

        $this->info('🌱 Demo poblar — datos ficticios para visualización');
        $this->line('   Entorno: ' . app()->environment());
        $this->line('   Técnicos a crear (si faltan): ' . $cantidadTecnicos);
        $this->line('   Reportes a crear: ' . $cantidadReportes);
        $this->line('   Marcador: ' . self::MARCADOR . ' (en nombres y títulos)');
        $this->newLine();

        // ── GUARD 2: confirmación interactiva (saltable con --force) ─────
        if (!$this->option('force')) {
            if (!$this->confirm('¿Continuar?', false)) {
                $this->warn('Cancelado. No se modificó nada.');
                return self::SUCCESS;
            }
        }

        // ── PASO 1: crear técnicos demo si faltan ────────────────────────
        $tecnicosCreados = $this->crearTecnicosDemo($cantidadTecnicos);
        $this->info("✓ Técnicos demo disponibles: {$tecnicosCreados}");

        // ── PASO 2: crear reportes demo ──────────────────────────────────
        $reportesCreados = $this->crearReportesDemo($cantidadReportes);
        $this->info("✓ Reportes demo creados: {$reportesCreados}");

        $this->newLine();
        $this->line('────────────────────────────────────────────');
        $this->line('Para limpiar todo lo demo:  php artisan demo:limpiar');
        $this->line('────────────────────────────────────────────');

        return self::SUCCESS;
    }

    /**
     * Crea técnicos demo si la cantidad solicitada supera lo existente.
     * Si ya hay suficientes con marcador, no duplica — devuelve el total existente.
     */
    private function crearTecnicosDemo(int $cantidadDeseada): int
    {
        // Tras BLOQUE 4 la columna nombre_apellido NO existe — identificamos los
        // técnicos demo por la cédula con prefijo 9999 (ver self::CEDULA_PREFIX).
        // Es más robusto que buscar por nombre porque la cédula no se puede
        // editar accidentalmente desde la UI (es UNIQUE).
        $existentes = Tecnico::where('cedula', 'LIKE', self::CEDULA_PREFIX . '%')->count();
        $faltan = max(0, $cantidadDeseada - $existentes);

        if ($faltan === 0) {
            return $existentes;
        }

        // Pares (nombre, apellido) ficticios. El prefijo [DEMO] se añade al nombre
        // para que sea visible a simple vista en el listado de técnicos.
        $nombresFicticios = [
            ['Carlos', 'Ramírez'], ['María', 'González'], ['José', 'Pérez'], ['Ana', 'Rodríguez'],
            ['Luis', 'Martínez'], ['Carmen', 'Sánchez'], ['Pedro', 'López'], ['Laura', 'García'],
            ['Miguel', 'Hernández'], ['Patricia', 'Fernández'], ['Andrés', 'Castillo'], ['Yelitza', 'Mora'],
            ['Eduardo', 'Silva'], ['Daniela', 'Torres'], ['Roberto', 'Mendoza'],
        ];

        // Próxima cédula = max actual + 1, o el prefijo base si no hay
        $maxCedula = Tecnico::where('cedula', 'LIKE', self::CEDULA_PREFIX . '%')
            ->orderByDesc('cedula')
            ->value('cedula') ?? (self::CEDULA_PREFIX . '000000');

        $proximoNumero = ((int) substr($maxCedula, \strlen(self::CEDULA_PREFIX))) + 1;

        $bar = $this->output->createProgressBar($faltan);
        $bar->start();

        for ($i = 0; $i < $faltan; $i++) {
            [$nombre, $apellido] = $nombresFicticios[$i % \count($nombresFicticios)];
            $cedula = self::CEDULA_PREFIX . str_pad((string) ($proximoNumero + $i), 6, '0', STR_PAD_LEFT);

            Tecnico::create([
                'nombre' => self::MARCADOR . ' ' . $nombre,
                'apellido' => $apellido,
                'tipo_documento' => 'V',
                'cedula' => $cedula,
                'telefono' => '0414-' . random_int(1000000, 9999999),
                // BLOQUE 4.5: array de especialidades (puede tener varias)
                'especialidades' => ['Agronomía urbana'],
                'estado' => 'activo',
            ]);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        return $existentes + $faltan;
    }

    /**
     * Crea reportes demo distribuidos en días laborables de los últimos 30 días.
     * Respeta el constraint UNIQUE(tecnico_id, fecha): si toca un par ya usado, lo salta.
     */
    private function crearReportesDemo(int $cantidad): int
    {
        // Solo técnicos demo — NO contaminamos las estadísticas con técnicos reales.
        // Identificamos por cédula 9999% (más confiable que buscar por nombre tras BLOQUE 4).
        $tecnicos = Tecnico::where('cedula', 'LIKE', self::CEDULA_PREFIX . '%')->get();

        if ($tecnicos->isEmpty()) {
            $this->error('No hay técnicos demo disponibles. Aborta.');
            return 0;
        }

        // Pool de consejos comunales aleatorios (con jerarquía cargada para denormalizar)
        $consejos = ConsejoComunal::with('comuna.parroquia.municipio')
            ->inRandomOrder()
            ->limit(min(200, $cantidad))
            ->get();

        if ($consejos->isEmpty()) {
            $this->error('No hay consejos comunales en BD. Importa la jerarquía primero.');
            return 0;
        }

        // Generar fechas laborables candidatas en últimos 30 días
        $fechasLaborables = [];
        $cursor = Carbon::today()->subDays(29);
        $hoy = Carbon::today();
        while ($cursor->lte($hoy)) {
            if ($cursor->isWeekday()) {
                $fechasLaborables[] = $cursor->copy();
            }
            $cursor->addDay();
        }

        $titulosActividad = [
            'Capacitación en huerto familiar',
            'Asesoría técnica en cultivo de hortalizas',
            'Seguimiento a producción comunal',
            'Distribución de semillas',
            'Taller de compostaje',
            'Visita técnica de diagnóstico',
            'Acompañamiento a productores',
            'Inspección de cultivos',
        ];

        $rubros = [
            'Solanum lycopersicum (tomate)',
            'Capsicum annuum (pimentón)',
            'Lactuca sativa (lechuga)',
            'Phaseolus vulgaris (caraota)',
            'Zea mays (maíz)',
            'Solanum tuberosum (papa)',
        ];

        $bar = $this->output->createProgressBar($cantidad);
        $bar->start();

        $creados = 0;
        $intentos = 0;
        $maxIntentos = $cantidad * 5; // tope para evitar loop infinito si se acaban combinaciones

        while ($creados < $cantidad && $intentos < $maxIntentos) {
            $intentos++;

            /** @var Tecnico $tecnico */
            $tecnico = $tecnicos->random();
            $fecha = $fechasLaborables[array_rand($fechasLaborables)];
            /** @var ConsejoComunal $cc */
            $cc = $consejos->random();

            // Respetar UNIQUE(tecnico_id, fecha): si el par ya existe, intenta otro
            $existe = Reporte::where('tecnico_id', $tecnico->id)
                ->whereDate('fecha', $fecha)
                ->exists();

            if ($existe) {
                continue;
            }

            $personasAtendidas = random_int(5, 80);

            Reporte::create([
                'tecnico_id' => $tecnico->id,
                'fecha' => $fecha,
                'estado_reporte' => 'completo',
                'municipio_id' => $cc->comuna->parroquia->municipio_id,
                'parroquia_id' => $cc->comuna->parroquia_id,
                'comuna_id' => $cc->comuna_id,
                'consejo_comunal_id' => $cc->id,
                'lugar' => $cc->nombre,
                'cantidad_personas_atendidas' => $personasAtendidas,
                'cantidad_personas_a_beneficiar' => $personasAtendidas + random_int(0, 30),
                'titulo_actividad' => self::MARCADOR . ' ' . $titulosActividad[array_rand($titulosActividad)],
                'nombre_cientifico_rubro' => $rubros[array_rand($rubros)],
                'fecha_ejecucion' => $fecha,
            ]);

            $creados++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        if ($creados < $cantidad) {
            $this->warn("Solo se crearon {$creados} de {$cantidad} solicitados (combinaciones técnico-fecha agotadas).");
        }

        return $creados;
    }
}
