<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatCard from '@/Components/StatCard.vue';
import Card from '@/Components/Card.vue';
import Badge from '@/Components/Badge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Icon from '@/Components/Icon.vue';
import ReportesPorTecnicoChart from '@/Components/Charts/ReportesPorTecnicoChart.vue';
import ReportesPorDiaChart from '@/Components/Charts/ReportesPorDiaChart.vue';
import DistribucionMunicipioChart from '@/Components/Charts/DistribucionMunicipioChart.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            tecnicosActivos: 0,
            reportesMes: 0,
            reportesSemana: 0,
            personasAtendidasMes: 0,
        }),
    },
    semaforo: { type: Array, default: () => [] }, // [{tecnico_id, nombre, cedula, color, ultimo_reporte}]
    alertas: { type: Array, default: () => [] }, // [{tecnico_id, nombre, dias, ultimo_reporte, severidad}]
    charts: {
        type: Object,
        default: () => ({
            reportesPorTecnico: [],
            reportesPorDia: [],
            reportesPorMunicipio: [],
        }),
    },
});

const semaforoColors = {
    verde: { bg: 'bg-green-500', label: 'Reportó completo' },
    amarillo: { bg: 'bg-yellow-400', label: 'Reportó incompleto' },
    rojo: { bg: 'bg-red-500', label: 'Sin reportar' },
    gris: { bg: 'bg-slate-400', label: 'Fin de semana' },
};

const alertasOrdenadas = computed(() =>
    [...props.alertas].sort((a, b) => (b.dias ?? 0) - (a.dias ?? 0)),
);

// ─── Export PDF del resumen ejecutivo ──────────────────────────────────
// Refs hacia los 3 componentes Chart para capturar sus canvases con toBase64Image().
const refTendencia = ref(null);
const refTecnicos = ref(null);
const refMunicipios = ref(null);

// Estado del botón export (loading + error visible al usuario)
const exportando = ref(false);
const errorExport = ref('');

const exportarResumenPdf = async () => {
    if (exportando.value) return;
    exportando.value = true;
    errorExport.value = '';

    try {
        // Capturamos las 3 imágenes. Si un chart está oculto/empty, su método retorna null
        // y el backend rechazará la petición con 422 — protección incluida.
        const chartTendencia = refTendencia.value?.toBase64Image();
        const chartTecnicos = refTecnicos.value?.toBase64Image();
        const chartMunicipios = refMunicipios.value?.toBase64Image();

        if (!chartTendencia || !chartTecnicos || !chartMunicipios) {
            errorExport.value = 'Faltan datos para exportar. Asegúrate de que los gráficos estén cargados.';
            return;
        }

        // POST con responseType: 'blob' para recibir el PDF directamente como archivo binario
        const response = await axios.post(
            '/dashboard/exportar/pdf',
            {
                chart_tendencia: chartTendencia,
                chart_tecnicos: chartTecnicos,
                chart_municipios: chartMunicipios,
            },
            { responseType: 'blob' },
        );

        // Forzar descarga del PDF en el navegador
        const url = URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }));
        const a = document.createElement('a');
        a.href = url;
        const fecha = new Date().toISOString().slice(0, 10);
        a.download = `dashboard-resumen-${fecha}.pdf`;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
    } catch (err) {
        // Si el backend respondió con error, response.data es un Blob del JSON de error.
        // Lo convertimos a texto y mostramos el mensaje legible.
        if (err.response?.data instanceof Blob) {
            try {
                const text = await err.response.data.text();
                const parsed = JSON.parse(text);
                errorExport.value = parsed.message || 'Error al generar el PDF.';
            } catch {
                errorExport.value = 'Error al generar el PDF.';
            }
        } else {
            errorExport.value = err.response?.data?.message || err.message || 'Error desconocido.';
        }
    } finally {
        exportando.value = false;
    }
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout title="Dashboard">
        <!--
            Barra de acciones del Dashboard.
            Botón "Exportar resumen PDF" → captura los 3 charts y descarga PDF institucional.
        -->
        <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
            <div class="text-xs text-slate-500">
                <Icon name="dashboard" :size="12" class="inline" />
                Vista general del sistema
            </div>
            <div class="flex items-center gap-2">
                <span v-if="errorExport" class="text-[11px] text-red-600 bg-red-50 px-2 py-1 rounded border border-red-200">
                    {{ errorExport }}
                </span>
                <button
                    type="button"
                    @click="exportarResumenPdf"
                    :disabled="exportando"
                    class="inline-flex items-center gap-2 px-3 py-1.5 bg-cvaup-primary text-white rounded text-xs font-semibold hover:bg-cvaup-secondary transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    title="Genera un PDF con cintillo institucional, stats, gráficos y alertas del momento"
                >
                    <Icon name="exportar" :size="13" />
                    {{ exportando ? 'Generando PDF...' : 'Exportar resumen PDF' }}
                </button>
            </div>
        </div>

        <!-- Tarjetas resumen -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <StatCard
                label="Técnicos activos"
                :value="stats.tecnicosActivos"
                icon="users"
                color="#2E7D32"
            />
            <StatCard
                label="Reportes del mes"
                :value="stats.reportesMes"
                icon="reportes"
                color="#1B5E20"
            />
            <StatCard
                label="Reportes de la semana"
                :value="stats.reportesSemana"
                icon="calendar"
                color="#F9A825"
            />
            <StatCard
                label="Personas atendidas (mes)"
                :value="stats.personasAtendidasMes"
                icon="heart"
                color="#43A047"
            />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
            <!-- Semáforo de cumplimiento (2/3) -->
            <Card title="Semáforo de cumplimiento (hoy)" class="lg:col-span-2">
                <template #header>
                    <div class="flex items-center gap-3 text-[11px] text-slate-500 flex-wrap">
                        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-green-500"></span>Completo</span>
                        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-yellow-400"></span>Incompleto</span>
                        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-red-500"></span>Sin reportar</span>
                        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-slate-400"></span>Fin de semana</span>
                    </div>
                </template>

                <EmptyState v-if="semaforo.length === 0" icon="users" message="No hay técnicos activos para evaluar." />

                <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <Link
                        v-for="row in semaforo"
                        :key="row.tecnico_id"
                        :href="`/tecnicos/${row.tecnico_id}`"
                        class="flex items-center gap-3 p-3 border border-slate-200 rounded-md hover:border-cvaup-primary hover:bg-cvaup-primary/5 transition-colors cursor-pointer"
                        :title="`Ver perfil del técnico ${row.nombre}`"
                    >
                        <span
                            class="w-3 h-3 rounded-full shrink-0"
                            :class="semaforoColors[row.color]?.bg ?? 'bg-slate-300'"
                            :title="semaforoColors[row.color]?.label"
                        />
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-slate-800 truncate">{{ row.nombre }}</div>
                            <div class="text-xs text-slate-500 truncate">{{ row.cedula }}</div>
                        </div>
                        <div class="text-right text-[11px] text-slate-500 shrink-0">
                            <div>{{ row.ultimo_reporte ?? 'Sin reportes' }}</div>
                        </div>
                    </Link>
                </div>
            </Card>

            <!-- Panel de alertas (1/3) -->
            <Card title="Alertas">
                <EmptyState v-if="alertasOrdenadas.length === 0" icon="warning" message="Sin alertas. Todos los técnicos están al día." />

                <ul v-else class="space-y-2">
                    <li
                        v-for="alerta in alertasOrdenadas"
                        :key="alerta.tecnico_id"
                    >
                        <!--
                            Cada alerta es clickeable y lleva al detalle del técnico.
                            Usamos <Link> de Inertia para navegación SPA (sin full reload).
                        -->
                        <Link
                            :href="`/tecnicos/${alerta.tecnico_id}`"
                            :class="[
                                'flex items-start gap-3 p-3 rounded-md border transition-all',
                                'hover:shadow-md hover:scale-[1.01] cursor-pointer',
                                alerta.severidad === 'danger'
                                    ? 'bg-red-50 border-red-200 hover:bg-red-100'
                                    : 'bg-orange-50 border-orange-200 hover:bg-orange-100',
                            ]"
                            :title="`Ver perfil del técnico ${alerta.nombre}`"
                        >
                            <Icon
                                name="warning"
                                :size="20"
                                :class="alerta.severidad === 'danger' ? 'text-red-600' : 'text-orange-600'"
                            />
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-semibold text-slate-800 truncate">{{ alerta.nombre }}</div>
                                <div class="text-xs text-slate-600">{{ alerta.dias }} días laborables sin reportar</div>
                                <div class="text-[11px] text-slate-500">Último: {{ alerta.ultimo_reporte ?? 'Nunca' }}</div>
                            </div>
                            <div class="flex flex-col items-end gap-1 shrink-0">
                                <Badge :type="alerta.severidad === 'danger' ? 'incompleto' : 'info'" size="sm">
                                    {{ alerta.severidad === 'danger' ? 'Crítico' : 'Aviso' }}
                                </Badge>
                                <Icon name="chevron-right" :size="14" class="text-slate-400" />
                            </div>
                        </Link>
                    </li>
                </ul>
            </Card>
        </div>

        <!--
            Gráficos estadísticos (Chart.js v4 + vue-chartjs).
            Layout: 2 columnas en desktop. La línea temporal ocupa todo el ancho
            porque su densidad de información lo necesita. Bar y Doughnut comparten fila.
        -->
        <div class="space-y-4">
            <!-- Línea temporal: full-width -->
            <Card title="Reportes por día — últimos 30 días">
                <template #header>
                    <span class="text-[11px] text-slate-500">
                        <Icon name="calendar" :size="11" class="inline" />
                        Tendencia diaria
                    </span>
                </template>
                <ReportesPorDiaChart ref="refTendencia" :data="charts.reportesPorDia" />
            </Card>

            <!-- Bar + Doughnut: 2 columnas en desktop, apiladas en mobile -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <Card title="Reportes por técnico — mes actual">
                    <template #header>
                        <span class="text-[11px] text-slate-500">Top 10</span>
                    </template>
                    <ReportesPorTecnicoChart ref="refTecnicos" :data="charts.reportesPorTecnico" />
                </Card>

                <Card title="Distribución por municipio — mes actual">
                    <template #header>
                        <span class="text-[11px] text-slate-500">Top 10</span>
                    </template>
                    <DistribucionMunicipioChart ref="refMunicipios" :data="charts.reportesPorMunicipio" />
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
