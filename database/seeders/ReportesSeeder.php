<?php

namespace Database\Seeders;

use App\Models\Comuna;
use App\Models\ConsejoComunal;
use App\Models\Municipio;
use App\Models\Parroquia;
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

        $rubrosCientificos = ['Citrus sinensis', 'Lactuca sativa', 'Persea americana', 'Solanum lycopersicum', 'N/A'];
        $titulos = [
            'Taller de compostaje comunitario',
            'Siembra de frutales en patios productivos',
            'Diagnóstico de suelos para huertos urbanos',
            'Capacitación en cultivo de hortalizas',
            'Instalación de sistema de riego por goteo',
            'Poda y mantenimiento de frutales',
            'Elaboración de bioinsumos',
            'Visita técnica a parcela demostrativa',
            'Capacitación en manejo integrado de plagas',
            'Seguimiento a patios productivos',
            'Jornada de siembra agroecológica',
            'Inspección de unidades productivas',
            'Charla sobre seguridad alimentaria',
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
                'titulo_actividad' => $titulos[array_rand($titulos)],
                'fecha_ejecucion' => $fecha->toDateString(),
            ];

            if ($estadoRandom !== 'borrador') {
                $datos = array_merge($datos, [
                    'cantidad_personas_atendidas' => random_int(8, 40),
                    'cantidad_personas_a_beneficiar' => random_int(30, 150),
                    'nombre_cientifico_rubro' => $rubrosCientificos[array_rand($rubrosCientificos)],
                    'fecha_ejecucion' => $fecha->toDateString(),
                    'ponencia_responsable' => 'Ing. ' . explode(' ', $tecnico->nombre_apellido)[0],
                    'organizado_por' => 'CVAUP Táchira',
                    'participantes_acreditados' => random_int(8, 30),
                    'alcance_grupo' => random_int(30, 120),
                    'lugar' => 'Sector ' . ($consejo?->nombre ?? $comuna?->nombre ?? 'Centro'),
                    'resultado' => 'Actividad ejecutada con la comunidad participante.',
                    'resumen_tematico' => 'Se desarrollaron actividades de formación y acompañamiento técnico en el área agrícola.',
                ]);
            }

            if ($estadoRandom === 'completo') {
                $datos['material_apoyo'] = 'Folletos impresos, presentación digital';
                $datos['aval_de'] = 'Ministerio de Agricultura Urbana';
                $datos['certificacion'] = 'Constancia de participación emitida';
                $datos['cantidad_comunas_atendidas'] = 1;
                $datos['cantidad_consejos_comunales_atendidos'] = 1;
            }

            Reporte::create($datos);
            $reportesCreados++;
        }

        $this->command->info("Reportes creados: {$reportesCreados}");
    }
}
