<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import axios from 'axios';
import Icon from '@/Components/Icon.vue';
import EmptyState from '@/Components/EmptyState.vue';

/**
 * TablaUbicaciones — vista plana paginada con filtros encadenados, búsqueda y ordenamiento.
 *
 * Ideal para análisis: "¿qué CC de Jáuregui tienen reportes?" → filtrar municipio + solo_con_reportes.
 */
const props = defineProps({
    municipios: { type: Array, default: () => [] },  // lista de municipios (precargada del padre)
});

const inputClass = 'px-3 py-2 text-sm border border-slate-300 rounded-md focus:border-cvaup-primary focus:ring focus:ring-cvaup-primary/20 outline-none transition';

// ─── Estado de filtros ───
const filters = ref({
    q: '',
    municipio_id: null,
    parroquia_id: null,
    comuna_id: null,
    solo_con_reportes: false,
    sort: 'municipio',
    dir: 'asc',
    page: 1,
    per_page: 25,
});

// ─── Datos paginados ───
const data = ref({ data: [], total: 0, current_page: 1, last_page: 1, per_page: 25, from: 0, to: 0 });
const loading = ref(false);

// ─── Cascada: cuando cambia municipio, cargar parroquias; cuando cambia parroquia, cargar comunas ───
const parroquias = ref([]);
const comunas = ref([]);
const loadingParroquias = ref(false);
const loadingComunas = ref(false);

const loadParroquias = async (municipioId) => {
    if (!municipioId) {
        parroquias.value = [];
        return;
    }
    loadingParroquias.value = true;
    try {
        const { data } = await axios.get('/api/parroquias', { params: { municipio_id: municipioId } });
        parroquias.value = data;
    } catch (e) {
        parroquias.value = [];
    } finally {
        loadingParroquias.value = false;
    }
};

const loadComunas = async (parroquiaId) => {
    if (!parroquiaId) {
        comunas.value = [];
        return;
    }
    loadingComunas.value = true;
    try {
        const { data } = await axios.get('/api/comunas', { params: { parroquia_id: parroquiaId } });
        comunas.value = data;
    } catch (e) {
        comunas.value = [];
    } finally {
        loadingComunas.value = false;
    }
};

// Watchers de cascada — resetean filtros hijos cuando cambia el padre
watch(() => filters.value.municipio_id, async (newVal) => {
    filters.value.parroquia_id = null;
    filters.value.comuna_id = null;
    await loadParroquias(newVal);
});
watch(() => filters.value.parroquia_id, async (newVal) => {
    filters.value.comuna_id = null;
    await loadComunas(newVal);
});

// ─── Fetch principal — debounced ───
let fetchTimer = null;
const fetchData = async () => {
    loading.value = true;
    try {
        const cleanFilters = { ...filters.value };
        Object.keys(cleanFilters).forEach((k) => {
            if (cleanFilters[k] === '' || cleanFilters[k] === null) delete cleanFilters[k];
            if (k === 'solo_con_reportes' && !cleanFilters[k]) delete cleanFilters[k];
        });
        const { data: response } = await axios.get('/ubicaciones/flat-list', { params: cleanFilters });
        data.value = response;
    } catch (e) {
        // silencioso — tabla queda con últimos datos válidos
    } finally {
        loading.value = false;
    }
};

const debouncedFetch = () => {
    clearTimeout(fetchTimer);
    fetchTimer = setTimeout(fetchData, 300);
};

// Watcher: cualquier cambio de filtro reinicia a la página 1 y refetch (debounced)
watch(
    () => [
        filters.value.q,
        filters.value.municipio_id,
        filters.value.parroquia_id,
        filters.value.comuna_id,
        filters.value.solo_con_reportes,
        filters.value.sort,
        filters.value.dir,
        filters.value.per_page,
    ],
    () => {
        filters.value.page = 1;
        debouncedFetch();
    },
);

// Watcher de página separado para que NO se debounce el cambio de página
watch(() => filters.value.page, fetchData);

onMounted(fetchData);

// ─── Acciones ───
const toggleSort = (column) => {
    if (filters.value.sort === column) {
        filters.value.dir = filters.value.dir === 'asc' ? 'desc' : 'asc';
    } else {
        filters.value.sort = column;
        filters.value.dir = 'asc';
    }
};

const sortIcon = (column) => {
    if (filters.value.sort !== column) return '';
    return filters.value.dir === 'asc' ? '▲' : '▼';
};

const limpiar = () => {
    filters.value = {
        q: '',
        municipio_id: null,
        parroquia_id: null,
        comuna_id: null,
        solo_con_reportes: false,
        sort: 'municipio',
        dir: 'asc',
        page: 1,
        per_page: 25,
    };
};

const irPagina = (n) => {
    if (n < 1 || n > data.value.last_page) return;
    filters.value.page = n;
};

/**
 * URL de exportar a Excel — incluye los filtros actuales como query params.
 * NO incluye sort/dir/page/per_page porque al exportar queremos TODO el dataset filtrado,
 * no solo lo que ves en la página actual.
 *
 * Solo Excel: PDF se eliminó porque para datasets de ubicaciones (>2k filas)
 * DomPDF no escala. PDF queda reservado para reportes individuales (1 reporte = 1 documento oficial).
 */
const urlExportarExcel = () => {
    const params = new URLSearchParams();
    if (filters.value.q) params.set('q', filters.value.q);
    if (filters.value.municipio_id) params.set('municipio_id', filters.value.municipio_id);
    if (filters.value.parroquia_id) params.set('parroquia_id', filters.value.parroquia_id);
    if (filters.value.comuna_id) params.set('comuna_id', filters.value.comuna_id);
    if (filters.value.solo_con_reportes) params.set('solo_con_reportes', '1');
    const qs = params.toString();
    return `/ubicaciones/exportar/excel${qs ? '?' + qs : ''}`;
};

// Lista de páginas a mostrar (con elipsis si hay muchas)
const visiblePages = computed(() => {
    const total = data.value.last_page;
    const current = data.value.current_page;
    if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);

    const pages = [1];
    if (current > 3) pages.push('...');
    for (let i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) {
        pages.push(i);
    }
    if (current < total - 2) pages.push('...');
    pages.push(total);
    return pages;
});
</script>

<template>
    <div>
        <!-- Filtros -->
        <div class="bg-slate-50 border border-slate-200 rounded-md p-3 mb-3 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-2">
            <!-- Búsqueda libre -->
            <div class="relative md:col-span-2">
                <Icon name="search" :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                <input
                    v-model="filters.q"
                    type="text"
                    placeholder="Buscar por nombre (en cualquier columna)..."
                    :class="['pl-9 w-full', inputClass]"
                />
            </div>

            <!-- Cascada de selects -->
            <select v-model="filters.municipio_id" :class="['w-full', inputClass]">
                <option :value="null">Todos los municipios</option>
                <option v-for="m in municipios" :key="m.id" :value="m.id">{{ m.nombre }}</option>
            </select>

            <select
                v-model="filters.parroquia_id"
                :disabled="!filters.municipio_id || loadingParroquias"
                :class="['w-full', inputClass]"
            >
                <option :value="null">{{ loadingParroquias ? 'Cargando...' : 'Todas las parroquias' }}</option>
                <option v-for="p in parroquias" :key="p.id" :value="p.id">{{ p.nombre }}</option>
            </select>

            <select
                v-model="filters.comuna_id"
                :disabled="!filters.parroquia_id || loadingComunas"
                :class="['w-full', inputClass]"
            >
                <option :value="null">{{ loadingComunas ? 'Cargando...' : 'Todas las comunas' }}</option>
                <option v-for="c in comunas" :key="c.id" :value="c.id">{{ c.nombre }}</option>
            </select>

            <label class="inline-flex items-center gap-2 text-sm cursor-pointer select-none">
                <input
                    v-model="filters.solo_con_reportes"
                    type="checkbox"
                    class="rounded border-slate-300 text-cvaup-primary focus:ring-cvaup-primary"
                />
                <span class="text-slate-700">Solo CC con reportes</span>
            </label>

            <select v-model="filters.per_page" :class="['w-full', inputClass]">
                <option :value="25">25 por página</option>
                <option :value="50">50 por página</option>
                <option :value="100">100 por página</option>
            </select>

            <button
                type="button"
                @click="limpiar"
                class="text-xs text-slate-500 hover:text-slate-700 underline self-center justify-self-start"
            >
                Limpiar filtros
            </button>
        </div>

        <!-- Status bar + botones de exportar -->
        <div class="flex items-center justify-between text-xs text-slate-500 mb-2 px-1 flex-wrap gap-2">
            <div class="flex items-center gap-3">
                <span v-if="loading" class="italic">Cargando...</span>
                <span v-else>
                    Mostrando <strong class="tabular-nums">{{ data.from ?? 0 }}–{{ data.to ?? 0 }}</strong> de
                    <strong class="tabular-nums">{{ data.total }}</strong> consejos comunales
                </span>
                <span v-if="filters.q || filters.municipio_id || filters.parroquia_id || filters.comuna_id || filters.solo_con_reportes" class="text-cvaup-primary">
                    <Icon name="filter" :size="12" class="inline" /> Filtros activos
                </span>
            </div>

            <!-- Botón de exportar a Excel — respeta los filtros actuales (lo que ves es lo que exportas) -->
            <!-- PDF se quitó a propósito: para datasets de ubicaciones Excel es la herramienta correcta. -->
            <div class="flex items-center gap-1">
                <a
                    :href="urlExportarExcel()"
                    class="inline-flex items-center gap-1 px-2 py-1 bg-cvaup-primary text-white rounded text-[11px] font-semibold hover:bg-cvaup-secondary transition-colors"
                    title="Descargar Excel (.xlsx) con los filtros actuales"
                >
                    <Icon name="download" :size="12" />
                    Exportar Excel
                </a>
            </div>
        </div>

        <!-- Tabla -->
        <div class="border border-slate-200 rounded-md overflow-hidden bg-white">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-cvaup-primary text-white">
                        <tr class="text-left">
                            <th
                                @click="toggleSort('municipio')"
                                class="px-3 py-2 font-semibold text-[11px] uppercase tracking-wider cursor-pointer hover:bg-cvaup-secondary transition-colors select-none"
                            >
                                Municipio <span class="text-[9px]">{{ sortIcon('municipio') }}</span>
                            </th>
                            <th
                                @click="toggleSort('parroquia')"
                                class="px-3 py-2 font-semibold text-[11px] uppercase tracking-wider cursor-pointer hover:bg-cvaup-secondary transition-colors select-none"
                            >
                                Parroquia <span class="text-[9px]">{{ sortIcon('parroquia') }}</span>
                            </th>
                            <th
                                @click="toggleSort('comuna')"
                                class="px-3 py-2 font-semibold text-[11px] uppercase tracking-wider cursor-pointer hover:bg-cvaup-secondary transition-colors select-none"
                            >
                                Comuna <span class="text-[9px]">{{ sortIcon('comuna') }}</span>
                            </th>
                            <th
                                @click="toggleSort('consejo')"
                                class="px-3 py-2 font-semibold text-[11px] uppercase tracking-wider cursor-pointer hover:bg-cvaup-secondary transition-colors select-none"
                            >
                                Consejo Comunal <span class="text-[9px]">{{ sortIcon('consejo') }}</span>
                            </th>
                            <th
                                @click="toggleSort('reportes')"
                                class="px-3 py-2 font-semibold text-[11px] uppercase tracking-wider cursor-pointer hover:bg-cvaup-secondary transition-colors select-none text-center w-24"
                            >
                                Reportes <span class="text-[9px]">{{ sortIcon('reportes') }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="!loading && data.data.length === 0">
                            <td colspan="5" class="text-center py-12">
                                <EmptyState icon="ubicaciones" message="No se encontraron consejos comunales con esos filtros." />
                            </td>
                        </tr>
                        <tr
                            v-for="(row, idx) in data.data"
                            :key="row.cc_id"
                            :class="['hover:bg-slate-50 transition-colors', idx % 2 === 1 ? 'bg-slate-50/30' : '']"
                        >
                            <td class="px-3 py-2 text-slate-700">{{ row.municipio }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ row.parroquia }}</td>
                            <td class="px-3 py-2 text-slate-600 text-xs">{{ row.comuna }}</td>
                            <td class="px-3 py-2 text-slate-800 font-medium">{{ row.consejo }}</td>
                            <td class="px-3 py-2 text-center">
                                <span
                                    v-if="row.reportes_count > 0"
                                    class="inline-block px-2 py-0.5 bg-cvaup-primary/10 text-cvaup-primary font-semibold rounded tabular-nums text-xs"
                                >
                                    {{ row.reportes_count }}
                                </span>
                                <span v-else class="text-slate-300 text-xs">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Paginación -->
        <div v-if="data.last_page > 1" class="flex items-center justify-center gap-1 mt-3 text-xs">
            <button
                @click="irPagina(data.current_page - 1)"
                :disabled="data.current_page === 1"
                class="px-2 py-1 rounded border border-slate-300 text-slate-600 hover:bg-slate-100 disabled:opacity-30 disabled:cursor-not-allowed"
            >
                ‹ Anterior
            </button>
            <template v-for="(p, i) in visiblePages" :key="i">
                <span v-if="p === '...'" class="px-2 text-slate-400">…</span>
                <button
                    v-else
                    @click="irPagina(p)"
                    :class="[
                        'min-w-[28px] px-2 py-1 rounded border tabular-nums',
                        p === data.current_page
                            ? 'bg-cvaup-primary text-white border-cvaup-primary font-semibold'
                            : 'border-slate-300 text-slate-600 hover:bg-slate-100',
                    ]"
                >
                    {{ p }}
                </button>
            </template>
            <button
                @click="irPagina(data.current_page + 1)"
                :disabled="data.current_page === data.last_page"
                class="px-2 py-1 rounded border border-slate-300 text-slate-600 hover:bg-slate-100 disabled:opacity-30 disabled:cursor-not-allowed"
            >
                Siguiente ›
            </button>
        </div>
    </div>
</template>
