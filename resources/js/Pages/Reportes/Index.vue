<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Card from '@/Components/Card.vue';
import Badge from '@/Components/Badge.vue';
import Icon from '@/Components/Icon.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const debounce = (fn, wait = 300) => {
    let t;
    return (...args) => {
        clearTimeout(t);
        t = setTimeout(() => fn.apply(this, args), wait);
    };
};

const props = defineProps({
    reportes: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    lookups: { type: Object, default: () => ({ tecnicos: [], municipios: [] }) },
});

const localFilters = ref({ ...props.filters });

const apply = () => {
    const cleaned = { ...localFilters.value };
    Object.keys(cleaned).forEach((k) => {
        if (cleaned[k] === '' || cleaned[k] === null) delete cleaned[k];
    });
    router.get(route('reportes.index'), cleaned, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const debouncedApply = debounce(apply, 300);

watch(() => localFilters.value.q, debouncedApply);
watch(() => [
    localFilters.value.tecnico_id,
    localFilters.value.municipio_id,
    localFilters.value.estado_reporte,
    localFilters.value.desde,
    localFilters.value.hasta,
], apply);

const limpiarFiltros = () => {
    localFilters.value = { q: '', tecnico_id: null, municipio_id: null, parroquia_id: null, estado_reporte: 'todos', desde: '', hasta: '' };
};

// Eliminar
const confirmDelete = ref(null);

const performDelete = () => {
    if (!confirmDelete.value) return;
    router.delete(route('reportes.destroy', confirmDelete.value.id), {
        preserveScroll: true,
        onFinish: () => (confirmDelete.value = null),
    });
};

const inputClass = 'w-full px-3 py-2 text-sm border border-slate-300 rounded-md focus:border-cvaup-primary focus:ring focus:ring-cvaup-primary/20 outline-none transition';
</script>

<template>
    <Head title="Listado de Reportes" />

    <AuthenticatedLayout title="Listado de Reportes">
        <div class="flex items-center justify-between mb-4">
            <div class="text-sm text-slate-500">
                <span class="font-semibold text-slate-700">{{ reportes.total }}</span> reportes en total
            </div>
            <Link
                href="/reportes/crear"
                class="inline-flex items-center gap-2 px-4 py-2 bg-cvaup-primary text-white text-sm font-semibold rounded-md hover:bg-cvaup-secondary transition-colors"
            >
                <Icon name="plus" :size="18" />
                Nuevo Reporte
            </Link>
        </div>

        <Card padding="none">
            <!-- Filtros -->
            <div class="p-4 border-b border-slate-200 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="relative md:col-span-2">
                    <Icon name="search" :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                    <input
                        v-model="localFilters.q"
                        type="text"
                        placeholder="Buscar por título, lugar o resumen..."
                        :class="['pl-9', inputClass]"
                    />
                </div>
                <select v-model="localFilters.tecnico_id" :class="inputClass">
                    <option :value="null">Todos los técnicos</option>
                    <option v-for="t in lookups.tecnicos" :key="t.id" :value="t.id">{{ t.nombre_apellido }}</option>
                </select>
                <select v-model="localFilters.estado_reporte" :class="inputClass">
                    <option value="todos">Todos los estados</option>
                    <option value="completo">Completo</option>
                    <option value="incompleto">Incompleto</option>
                    <option value="borrador">Borrador</option>
                </select>
                <select v-model="localFilters.municipio_id" :class="inputClass">
                    <option :value="null">Todos los municipios</option>
                    <option v-for="m in lookups.municipios" :key="m.id" :value="m.id">{{ m.nombre }}</option>
                </select>
                <input v-model="localFilters.desde" type="date" :class="inputClass" placeholder="Desde" />
                <input v-model="localFilters.hasta" type="date" :class="inputClass" placeholder="Hasta" />
                <button
                    type="button"
                    @click="limpiarFiltros"
                    class="text-xs text-slate-500 hover:text-slate-700 underline self-center justify-self-start"
                >
                    Limpiar filtros
                </button>
            </div>

            <!-- Tabla -->
            <div v-if="reportes.data.length === 0">
                <EmptyState icon="reportes">
                    <div class="space-y-3">
                        <p>No hay reportes que coincidan con los filtros.</p>
                        <Link
                            href="/reportes/crear"
                            class="inline-block text-sm text-cvaup-primary hover:underline"
                        >
                            Crea el primero →
                        </Link>
                    </div>
                </EmptyState>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr class="text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <th class="px-4 py-3 font-semibold">Fecha</th>
                            <th class="px-4 py-3 font-semibold">Técnico</th>
                            <th class="px-4 py-3 font-semibold">Ubicación</th>
                            <th class="px-4 py-3 font-semibold">Tipo de actividad</th>
                            <th class="px-4 py-3 font-semibold text-center">Personas</th>
                            <th class="px-4 py-3 font-semibold text-center">Estado</th>
                            <th class="px-4 py-3 font-semibold text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="r in reportes.data" :key="r.id" class="hover:bg-slate-50 transition-colors">
                            <td class="px-4 py-3 whitespace-nowrap text-slate-700">{{ r.fecha }}</td>
                            <td class="px-4 py-3">
                                <Link v-if="r.tecnico" :href="`/tecnicos/${r.tecnico.id}`" class="font-medium text-slate-800 hover:text-cvaup-primary truncate block max-w-[180px]">
                                    {{ r.tecnico.nombre_apellido }}
                                </Link>
                                <span v-else class="text-slate-400 text-xs">—</span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                <div class="text-xs truncate max-w-[200px]">{{ r.municipio || '—' }}</div>
                                <div v-if="r.consejo_comunal" class="text-[10px] text-slate-400 truncate max-w-[200px]">{{ r.consejo_comunal }}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                <Link :href="`/reportes/${r.id}`" class="hover:opacity-80 inline-block max-w-[260px]">
                                    <span
                                        v-if="r.tipo_actividad"
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-cvaup-primary/10 text-cvaup-primary border border-cvaup-primary/20"
                                    >
                                        {{ r.tipo_actividad }}
                                    </span>
                                    <span v-else class="text-slate-400 text-xs">—</span>
                                </Link>
                                <div v-if="r.fotos_count > 0" class="text-[10px] text-slate-400 mt-1">
                                    <Icon name="image" :size="10" class="inline" /> {{ r.fotos_count }} foto(s)
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center text-slate-700 tabular-nums">
                                {{ r.cantidad_personas_atendidas ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <Badge :type="r.estado_reporte" size="sm" />
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-1">
                                    <Link
                                        :href="`/reportes/${r.id}`"
                                        class="p-1.5 text-slate-500 hover:text-cvaup-primary hover:bg-slate-100 rounded transition-colors"
                                        title="Ver detalle"
                                    >
                                        <Icon name="eye" :size="16" />
                                    </Link>
                                    <Link
                                        :href="`/reportes/${r.id}/editar`"
                                        class="p-1.5 text-slate-500 hover:text-cvaup-primary hover:bg-slate-100 rounded transition-colors"
                                        title="Editar"
                                    >
                                        <Icon name="edit" :size="16" />
                                    </Link>
                                    <button
                                        type="button"
                                        @click="confirmDelete = r"
                                        class="p-1.5 text-slate-500 hover:text-red-600 hover:bg-red-50 rounded transition-colors"
                                        title="Eliminar"
                                    >
                                        <Icon name="trash" :size="16" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination v-if="reportes.data.length > 0" :meta="reportes" />
        </Card>

        <ConfirmDialog
            :show="confirmDelete !== null"
            title="Eliminar reporte"
            :message="`¿Estás seguro de eliminar el reporte de tipo '${confirmDelete?.tipo_actividad ?? '—'}' del ${confirmDelete?.fecha}?`"
            confirm-label="Sí, eliminar"
            variant="danger"
            @confirm="performDelete"
            @cancel="confirmDelete = null"
        />
    </AuthenticatedLayout>
</template>
