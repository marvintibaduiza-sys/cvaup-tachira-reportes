<?php

namespace Database\Seeders;

use App\Models\Municipio;
use App\Models\Reporte;
use App\Models\Tecnico;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ReportesSeeder extends Seeder
{
    /**
     * Genera 15-20 reportes distribuidos en los últimos 30 días,
     * con variedad de estados (completo / incompleto / borrador).
     *
     * Respeta el constraint: 1 técnico = 1 reporte por fecha.
     * Solo crea reportes en días laborables (L-V).
     */
    public function run(): void
    {
        $tecnicos = Tecnico::activos()->get();
        if ($tecnicos->isEmpty()) {
            $this->command->warn('No hay técnicos activos. Saltando seed de reportes.');
            return;
        }

        // Tipos válidos de actividad (deben coincidir con Reporte::TIPOS_ACTIVIDAD).
        $tiposActividad = [
            'Capacitación',
            'Asesoría técnica',
            'Taller',
            'Visita técnica',
            'Otra',
        ];

        // Descripciones temáticas reutilizables para el campo descripcion_actividad.
        $descripcionesBase = [
            'Taller de compostaje comunitario con énfasis en separación de residuos orgánicos y aprovechamiento agrícola.',
            'Siembra de frutales en patios productivos, acompañamiento técnico en preparación del terreno y selección de especies.',
            'Diagnóstico de suelos para huertos urbanos: muestreo, pH y recomendaciones de enmienda.',
            'Capacitación en cultivo de hortalizas de ciclo corto bajo condiciones agroecológicas.',
            'Instalación de sistema de riego por goteo en parcela demostrativa.',
            'Poda y mantenimiento de frutales: técnicas de formación y sanidad vegetal.',
            'Elaboración de bioinsumos (biofertilizantes y bioplaguicidas) con productores locales.',
            'Visita técnica a parcela demostrativa para evaluación de avance del ciclo productivo.',
            'Capacitación en manejo integrado de plagas con énfasis en control biológico.',
            'Seguimiento a patios productivos: evaluación de producción y resolución de dudas técnicas.',
            'Jornada de siembra agroecológica con participación comunitaria.',
            'Inspección de unidades productivas y levantamiento de información para acompañamiento.',
            'Charla sobre seguridad alimentaria y producción comunal sostenible.',
        ];

        $hoy = Carbon::today();
        $reportesCreados = 0;
        $intentosMaximos = 60;
        $intento = 0;

        while ($reportesCreados < 18 && $intento < $intentosMaximos) {
            $intento++;

            $tecnico = $tecnicos->random();

            // Día aleatorio de los últimos 30, solo laborables
            $diasAtras = random_int(0, 29);
            $fecha = $hoy->copy()->subDays($diasAtras);
            if (!$fecha->isWeekday()) {
                continue;
            }

            // Respetar unique(tecnico_id, fecha)
            if (Reporte::where('tecnico_id', $tecnico->id)->whereDate('fecha', $fecha)->exists()) {
                continue;
            }

            // Elegir ubicación: priorizar municipios asignados al técnico
            $municipioIds = $tecnico->municipios()->pluck('municipios.id');
            if ($municipioIds->isEmpty()) {
                $municipioIds = Municipio::pluck('id');
            }
            $municipio = Municipio::with('parroquias.comunas.consejosComunales')
                ->whereIn('id', $municipioIds)
                ->inRandomOrder()
                ->first();

            $parroquia = $municipio->parroquias()->inRandomOrder()->first();
            if (!$parroquia) {
                continue;
            }

            $comuna = $parroquia->comunas()->inRandomOrder()->first();
            $consejo = $comuna ? $comuna->consejosComunales()->inRandomOrder()->first() : null;

            // Elegir estado del reporte (mezcla de completos, incompletos, borradores)
            $estadoRandom = match (random_int(1, 10)) {
                1, 2 => 'borrador',
                3, 4 => 'incompleto',
                default => 'completo',
            };

            $datos = [
                'tecnico_id' => $tecnico->id,
                'fecha' => $fecha->toDateString(),
                'estado_reporte' => $estadoRandom,
                'municipio_id' => $municipio->id,
                'parroquia_id' => $parroquia->id,
                'comuna_id' => $comuna?->id,
                'consejo_comunal_id' => $consejo?->id,
                'tipo_actividad' => $tiposActividad[array_rand($tiposActividad)],
                'descripcion_actividad' => $descripcionesBase[array_rand($descripcionesBase)],
            ];

            if ($estadoRandom !== 'borrador') {
                $datos = array_merge($datos, [
                    'cantidad_personas_atendidas' => random_int(8, 40),
                    'cantidad_personas_a_beneficiar' => random_int(30, 150),
                    'lugar' => 'Sector ' . ($consejo?->nombre ?? $comuna?->nombre ?? 'Centro'),
                ]);
            }

            if ($estadoRandom === 'completo') {
                $datos['cantidad_comunas_atendidas'] = 1;
                $datos['cantidad_consejos_comunales_atendidos'] = 1;
            }

            Reporte::create($datos);
            $reportesCreados++;
        }

        $this->command->info("Reportes creados: {$reportesCreados}");
    }
}
