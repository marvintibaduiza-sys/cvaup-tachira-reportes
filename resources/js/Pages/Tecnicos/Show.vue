<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Card from '@/Components/Card.vue';
import Badge from '@/Components/Badge.vue';
import Icon from '@/Components/Icon.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    tecnico: { type: Object, required: true },
    estadisticas: { type: Object, required: true },
    reportes_recientes: { type: Array, default: () => [] },
});

const initial = computed(() => props.tecnico.nombre_apellido?.charAt(0)?.toUpperCase() ?? 'T');

const tasaColor = computed(() => {
    const t = props.estadisticas.tasa_cumplimiento_mes ?? 0;
    if (t >= 80) return 'text-green-600';
    if (t >= 50) return 'text-yellow-600';
    return 'text-red-600';
});

const confirmDelete = ref(false);

const performDelete = () => {
    router.delete(route('tecnicos.destroy', props.tecnico.id));
    confirmDelete.value = false;
};

const toggleEstado = () => {
    router.patch(route('tecnicos.toggle', props.tecnico.id), {}, { preserveScroll: true });
};
</script>

<template>
    <Head :title="tecnico.nombre_apellido" />

    <AuthenticatedLayout :title="`Perfil — ${tecnico.nombre_apellido}`">
        <div class="max-w-5xl mx-auto space-y-6">
            <!-- Header con avatar + datos básicos -->
            <Card padding="lg">
                <div class="flex flex-col sm:flex-row items-start gap-6">
                    <div
                        v-if="tecnico.foto_url"
                        class="w-24 h-24 rounded-full bg-slate-200 overflow-hidden shrink-0"
                    >
                        <img :src="tecnico.foto_url" :alt="tecnico.nombre_apellido" class="w-full h-full object-cover" />
                    </div>
                    <div
                        v-else
                        class="w-24 h-24 rounded-full bg-cvaup-primary text-white flex items-center justify-center text-3xl font-bold shrink-0"
                    >
                        {{ initial }}
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between flex-wrap gap-3">
                            <div>
                                <h2 class="text-xl font-bold text-slate-800">{{ tecnico.nombre_apellido }}</h2>
                                <p class="text-sm text-slate-500">{{ tecnico.cedula }}</p>
                                <div class="mt-2">
                                    <Badge :type="tecnico.estado === 'activo' ? 'activo' : 'inactivo'" />
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <Link
                                    :href="`/tecnicos/${tecnico.id}/editar`"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-white text-slate-700 border border-slate-300 rounded-md hover:bg-slate-100 transition-colors"
                                >
                                    <Icon name="edit" :size="14" />
                                    Editar
                                </Link>
                                <button
                                    type="button"
                                    @click="toggleEstado"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-white text-slate-700 border border-slate-300 rounded-md hover:bg-slate-100 transition-colors"
                                >
                                    <Icon :name="tecnico.estado === 'activo' ? 'lock' : 'check'" :size="14" />
                                    {{ tecnico.estado === 'activo' ? 'Desactivar' : 'Activar' }}
                                </button>
                                <button
                                    type="button"
                                    @click="confirmDelete = true"
                                    :disabled="estadisticas.total_reportes > 0"
                                    :title="estadisticas.total_reportes > 0 ? 'Tiene reportes — usa Desactivar' : 'Eliminar'"
                                    :class="[
                                        'inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium border rounded-md transition-colors',
                                        estadisticas.total_reportes > 0
                                            ? 'text-slate-300 border-slate-200 cursor-not-allowed'
                                            : 'text-red-600 bg-white border-red-200 hover:bg-red-50',
                                    ]"
                                >
                                    <Icon name="trash" :size="14" />
                                    Eliminar
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-2 mt-4 text-sm">
                            <div>
                                <span class="text-slate-500">Teléfono:</span>
                                <span class="ml-2 text-slate-700">{{ tecnico.telefono || '—' }}</span>
                            </div>
                            <div>
                                <span class="text-slate-500">Especialidad:</span>
                                <span class="ml-2 text-slate-700">{{ tecnico.especialidad || '—' }}</span>
                            </div>
                            <div class="sm:col-span-2">
                                <span class="text-slate-500">Zonas asignadas:</span>
                                <span v-if="tecnico.municipios_asignados.length === 0" class="ml-2 text-slate-400">
                                    Sin zonas asignadas
                                </span>
                                <span v-else class="inline-flex flex-wrap gap-1 ml-2">
                                    <span
                                        v-for="m in tecnico.municipios_asignados"
                                        :key="m.id"
                                        class="bg-green-50 text-green-800 border border-green-200 rounded px-2 py-0.5 text-[11px]"
                                    >
                                        {{ m.nombre }}
                                    </span>
                                </span>
                            </div>
                            <div>
                                <span class="text-slate-500">Registrado:</span>
                                <span class="ml-2 text-slate-700">{{ tecnico.created_at }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </Card>

            <!-- Estadísticas -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <Card padding="md">
                    <div class="text-2xl font-bold text-slate-800 tabular-nums">{{ estadisticas.total_reportes }}</div>
                    <div class="text-xs text-slate-500 mt-1">Total de reportes</div>
                </Card>
                <Card padding="md">
                    <div class="text-2xl font-bold text-slate-800 tabular-nums">{{ estadisticas.promedio_personas }}</div>
                    <div class="text-xs text-slate-500 mt-1">Personas atendidas (promedio)</div>
                </Card>
                <Card padding="md">
                    <div :class="['text-2xl font-bold tabular-nums', tasaColor]">
                        {{ estadisticas.tasa_cumplimiento_mes }}%
                    </div>
                    <div class="text-xs text-slate-500 mt-1">Cumplimiento del mes</div>
                </Card>
                <Card padding="md">
                    <div class="text-2xl font-bold text-slate-800 tabular-nums">
                        {{ estadisticas.municipios_trabajados.length }}
                    </div>
                    <div class="text-xs text-slate-500 mt-1">Municipios atendidos</div>
                </Card>
            </div>

            <!-- Último reporte + municipios trabajados -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <Card title="Último reporte">
                    <div v-if="estadisticas.ultimo_reporte" class="space-y-2">
                        <div class="font-medium text-slate-800">{{ estadisticas.ultimo_reporte.titulo_actividad }}</div>
                        <div class="text-sm text-slate-500">
                            <Icon name="calendar" :size="14" class="inline" />
                            {{ estadisticas.ultimo_reporte.fecha }}
                        </div>
                    </div>
                    <EmptyState v-else icon="reportes" message="Aún no ha registrado reportes." />
                </Card>

                <Card title="Municipios donde ha trabajado">
                    <EmptyState v-if="estadisticas.municipios_trabajados.length === 0" icon="ubicaciones" message="Sin reportes aún." />
                    <div v-else class="flex flex-wrap gap-2">
                        <span
                            v-for="m in estadisticas.municipios_trabajados"
                            :key="m"
                            class="bg-slate-100 text-slate-700 rounded px-2.5 py-1 text-xs"
                        >
                            {{ m }}
                        </span>
                    </div>
                </Card>
            </div>

            <!-- Reportes recientes (placeholder hasta Fase 10) -->
            <Card title="Reportes recientes (últimos 20)">
                <EmptyState v-if="reportes_recientes.length === 0" icon="reportes">
                    <p>Aún no ha registrado reportes.</p>
                    <p class="text-[11px] mt-2">El listado completo y filtros por rango de fechas estarán disponibles cuando se complete el módulo de Reportes (Fase 10).</p>
                </EmptyState>
                <ul v-else class="divide-y divide-slate-100">
                    <li v-for="r in reportes_recientes" :key="r.id" class="py-3 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="font-medium text-slate-800 truncate">{{ r.titulo_actividad }}</div>
                            <div class="text-xs text-slate-500">{{ r.fecha }}</div>
                        </div>
                        <Badge :type="r.estado_reporte" size="sm" />
                    </li>
                </ul>
            </Card>
        </div>

        <ConfirmDialog
            :show="confirmDelete"
            title="Eliminar técnico"
            :message="`¿Estás seguro de eliminar a ${tecnico.nombre_apellido}?`"
            confirm-label="Sí, eliminar"
            variant="danger"
            @confirm="performDelete"
            @cancel="confirmDelete = false"
        />
    </AuthenticatedLayout>
</template>
