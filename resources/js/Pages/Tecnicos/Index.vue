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

// debounce inline (evita dependencia de lodash)
const debounce = (fn, wait = 300) => {
    let t;
    return (...args) => {
        clearTimeout(t);
        t = setTimeout(() => fn.apply(this, args), wait);
    };
};

const props = defineProps({
    tecnicos: { type: Object, required: true }, // paginated
    filters: { type: Object, default: () => ({ q: '', estado: 'todos' }) },
    totales: { type: Object, default: () => ({ activos: 0, inactivos: 0 }) },
});

// Filtros locales (debounced para search)
const localFilters = ref({ ...props.filters });

const applyFilters = () => {
    router.get(route('tecnicos.index'), localFilters.value, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const debouncedSearch = debounce(applyFilters, 300);

watch(() => localFilters.value.q, debouncedSearch);
watch(() => localFilters.value.estado, applyFilters);

// Eliminar
const confirmDelete = ref(null); // tecnico a eliminar

const performDelete = () => {
    if (!confirmDelete.value) return;
    router.delete(route('tecnicos.destroy', confirmDelete.value.id), {
        preserveScroll: true,
        onFinish: () => (confirmDelete.value = null),
    });
};

// Toggle estado
const toggleEstado = (tecnico) => {
    router.patch(route('tecnicos.toggle', tecnico.id), {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Listado de Técnicos" />

    <AuthenticatedLayout title="Listado de Técnicos">
        <!-- Header con totales y CTA -->
        <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
            <div class="flex items-center gap-3">
                <Badge type="activo" size="md">{{ totales.activos }} activos</Badge>
                <Badge type="inactivo" size="md">{{ totales.inactivos }} inactivos</Badge>
            </div>
            <Link
                href="/tecnicos/crear"
                class="inline-flex items-center gap-2 px-4 py-2 bg-cvaup-primary text-white text-sm font-semibold rounded-md hover:bg-cvaup-secondary transition-colors"
            >
                <Icon name="plus" :size="18" />
                Registrar Técnico
            </Link>
        </div>

        <Card padding="none">
            <!-- Filtros -->
            <div class="p-4 border-b border-slate-200 flex flex-wrap items-center gap-3">
                <div class="relative flex-1 min-w-[240px]">
                    <Icon
                        name="search"
                        :size="16"
                        class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"
                    />
                    <input
                        v-model="localFilters.q"
                        type="text"
                        placeholder="Buscar por nombre o cédula..."
                        class="w-full pl-9 pr-3 py-2 text-sm border border-slate-300 rounded-md focus:border-cvaup-primary focus:ring focus:ring-cvaup-primary/20 outline-none transition"
                    />
                </div>
                <select
                    v-model="localFilters.estado"
                    class="px-3 py-2 text-sm border border-slate-300 rounded-md focus:border-cvaup-primary focus:ring focus:ring-cvaup-primary/20 outline-none transition"
                >
                    <option value="todos">Todos</option>
                    <option value="activo">Solo activos</option>
                    <option value="inactivo">Solo inactivos</option>
                </select>
            </div>

            <!-- Tabla -->
            <div v-if="tecnicos.data.length === 0">
                <EmptyState icon="users">
                    <div class="space-y-3">
                        <p>No hay técnicos {{ filters.estado !== 'todos' ? `en estado ${filters.estado}` : 'registrados' }}.</p>
                        <Link
                            v-if="filters.estado === 'todos' && !filters.q"
                            href="/tecnicos/crear"
                            class="inline-block text-sm text-cvaup-primary hover:underline"
                        >
                            Registra el primero →
                        </Link>
                    </div>
                </EmptyState>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr class="text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <th class="px-4 py-3 font-semibold">Nombre y Cédula</th>
                            <th class="px-4 py-3 font-semibold">Contacto</th>
                            <th class="px-4 py-3 font-semibold">Especialidad</th>
                            <th class="px-4 py-3 font-semibold text-center">Reportes</th>
                            <th class="px-4 py-3 font-semibold text-center">Estado</th>
                            <th class="px-4 py-3 font-semibold text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="t in tecnicos.data" :key="t.id" class="hover:bg-slate-50 transition-colors">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div
                                        v-if="t.foto_url"
                                        class="w-9 h-9 rounded-full bg-slate-200 overflow-hidden shrink-0"
                                    >
                                        <img :src="t.foto_url" :alt="t.nombre_apellido" class="w-full h-full object-cover" />
                                    </div>
                                    <div
                                        v-else
                                        class="w-9 h-9 rounded-full bg-cvaup-primary text-white flex items-center justify-center text-sm font-semibold shrink-0"
                                    >
                                        {{ t.nombre_apellido.charAt(0) }}
                                    </div>
                                    <div class="min-w-0">
                                        <Link
                                            :href="`/tecnicos/${t.id}`"
                                            class="font-medium text-slate-800 hover:text-cvaup-primary transition-colors truncate block"
                                        >
                                            {{ t.nombre_apellido }}
                                        </Link>
                                        <div class="text-xs text-slate-500">{{ t.cedula }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                <div v-if="t.telefono">{{ t.telefono }}</div>
                                <div v-else class="text-slate-400 text-xs">—</div>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                <div v-if="t.especialidad">{{ t.especialidad }}</div>
                                <div v-else class="text-slate-400 text-xs">—</div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-block tabular-nums font-semibold text-slate-700">{{ t.total_reportes }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <Badge :type="t.estado === 'activo' ? 'activo' : 'inactivo'" size="sm" />
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-1">
                                    <Link
                                        :href="`/tecnicos/${t.id}`"
                                        class="p-1.5 text-slate-500 hover:text-cvaup-primary hover:bg-slate-100 rounded transition-colors"
                                        title="Ver perfil"
                                    >
                                        <Icon name="eye" :size="16" />
                                    </Link>
                                    <Link
                                        :href="`/tecnicos/${t.id}/editar`"
                                        class="p-1.5 text-slate-500 hover:text-cvaup-primary hover:bg-slate-100 rounded transition-colors"
                                        title="Editar"
                                    >
                                        <Icon name="edit" :size="16" />
                                    </Link>
                                    <button
                                        type="button"
                                        @click="toggleEstado(t)"
                                        :title="t.estado === 'activo' ? 'Desactivar' : 'Activar'"
                                        class="p-1.5 text-slate-500 hover:text-cvaup-accent hover:bg-slate-100 rounded transition-colors"
                                    >
                                        <Icon :name="t.estado === 'activo' ? 'lock' : 'check'" :size="16" />
                                    </button>
                                    <button
                                        type="button"
                                        @click="confirmDelete = t"
                                        :disabled="t.total_reportes > 0"
                                        :title="t.total_reportes > 0 ? 'Tiene reportes — solo desactivable' : 'Eliminar'"
                                        :class="[
                                            'p-1.5 rounded transition-colors',
                                            t.total_reportes > 0
                                                ? 'text-slate-300 cursor-not-allowed'
                                                : 'text-slate-500 hover:text-red-600 hover:bg-red-50',
                                        ]"
                                    >
                                        <Icon name="trash" :size="16" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination v-if="tecnicos.data.length > 0" :meta="tecnicos" />
        </Card>

        <ConfirmDialog
            :show="confirmDelete !== null"
            title="Eliminar técnico"
            :message="`¿Estás seguro de eliminar a ${confirmDelete?.nombre_apellido}? Esta acción se puede revertir desde la base de datos (soft delete) pero no desde la UI.`"
            confirm-label="Sí, eliminar"
            cancel-label="Cancelar"
            variant="danger"
            @confirm="performDelete"
            @cancel="confirmDelete = null"
        />
    </AuthenticatedLayout>
</template>
