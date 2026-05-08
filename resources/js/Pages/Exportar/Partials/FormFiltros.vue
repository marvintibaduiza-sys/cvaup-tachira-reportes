<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import axios from 'axios';
import FormField from '@/Components/FormField.vue';
import Icon from '@/Components/Icon.vue';
import MultiSelect from '@/Components/MultiSelect.vue';

/**
 * FormFiltros — formulario de filtros compartido entre PDF y CSV.
 *
 * Provee:
 *  - Filtros (rango fechas, técnico, municipio, parroquia cascada, estado)
 *  - Preview en vivo del conteo (fetch debounced a /exportar/preview)
 *  - Slot para el botón de submit (cada página tiene texto distinto)
 *
 * v-model:filters → objeto reactivo con los filtros seleccionados.
 */
const props = defineProps({
    filters: { type: Object, required: true },        // v-model
    lookups: { type: Object, required: true },        // { tecnicos: [], municipios: [] }
    submitLabel: { type: String, default: 'Generar' },
    submitting: { type: Boolean, default: false },
    showComparativa: { type: Boolean, default: false }, // solo para PDF, no aplica a CSV
    // Cuando se usa embebido en pantalla unificada (Generar Reportes con 2 botones PDF/Excel),
    // ocultar el botón interno de submit. La pantalla padre maneja los botones reales.
    hideSubmit: { type: Boolean, default: false },
});

const emit = defineEmits(['update:filters', 'submit']);

const inputClass = 'w-full px-3 py-2 text-sm border border-slate-300 rounded-md focus:border-cvaup-primary focus:ring focus:ring-cvaup-primary/20 outline-none transition';

// Opciones del multi-select de "Estados del reporte"
// El MultiSelect requiere objetos con {id, label}, así que convertimos los string-states.
const estadoOpciones = [
    { id: 'completo', label: 'Completo' },
    { id: 'incompleto', label: 'Incompleto' },
    { id: 'borrador', label: 'Borrador' },
];

// Cascada de parroquias cuando cambian los municipios seleccionados.
// Multi-select: el filtro municipio_ids es un array. Las parroquias mostradas
// son la UNIÓN de las parroquias de TODOS los municipios seleccionados.
const parroquias = ref([]);
const loadingParroquias = ref(false);

const loadParroquias = async (municipioIds) => {
    if (!Array.isArray(municipioIds) || municipioIds.length === 0) {
        parroquias.value = [];
        return;
    }
    loadingParroquias.value = true;
    try {
        // Backend acepta `municipio_ids[]` (nuevo) y mantiene compat con `municipio_id` (singular)
        const params = new URLSearchParams();
        municipioIds.forEach((id) => params.append('municipio_ids[]', id));
        const { data } = await axios.get('/api/parroquias?' + params.toString());
        parroquias.value = data;
    } catch (e) {
        parroquias.value = [];
    } finally {
        loadingParroquias.value = false;
    }
};

// Reset parroquias seleccionadas cuando cambian los municipios elegidos.
// Si quito un municipio, también quito las parroquias de ese municipio que estaban seleccionadas.
watch(() => props.filters.municipio_ids, async (newVal) => {
    await loadParroquias(newVal);
    // Filtrar parroquias seleccionadas que ya no pertenecen a los municipios actuales
    const validIds = parroquias.value.map((p) => p.id);
    props.filters.parroquia_ids = (props.filters.parroquia_ids ?? []).filter((id) => validIds.includes(id));
}, { deep: true });

/**
 * UX inteligente: cuando el usuario agrega un técnico al filtro,
 * pre-selecciona automáticamente los municipios donde ese técnico está asignado.
 *
 * Reglas:
 *  - SOLO agrega (nunca quita automáticamente). El usuario decide qué quitar.
 *  - Solo dispara para técnicos NUEVOS (no para los que ya estaban en la lista).
 *  - Sin duplicados (Set).
 *  - Si el técnico no tiene municipios asignados, no agrega nada.
 *
 * Las parroquias siguen siendo 100% manuales — el sistema NO las pre-selecciona.
 */
watch(() => props.filters.tecnico_ids, (newIds, oldIds) => {
    const previos = new Set(oldIds ?? []);
    const nuevos = (newIds ?? []).filter((id) => !previos.has(id));
    if (nuevos.length === 0) return;

    // Calcular municipios a agregar = unión de municipios asignados a los técnicos NUEVOS
    const municipiosActuales = new Set(props.filters.municipio_ids ?? []);
    for (const tecnicoId of nuevos) {
        const tecnico = (props.lookups.tecnicos ?? []).find((t) => t.id === tecnicoId);
        const ids = tecnico?.municipios_ids ?? [];
        for (const mid of ids) {
            municipiosActuales.add(mid);
        }
    }

    // Solo actualizar si hubo agregados reales (evita loop reactivo)
    const finalArr = Array.from(municipiosActuales);
    if (finalArr.length !== (props.filters.municipio_ids?.length ?? 0)) {
        props.filters.municipio_ids = finalArr;
    }
}, { deep: false });

// Preview en vivo del conteo (debounced)
const previewCount = ref(null);
const previewLoading = ref(false);
let previewTimer = null;

const fetchPreview = async () => {
    previewLoading.value = true;
    try {
        const { data } = await axios.get('/generar-reportes/preview', { params: cleanFilters() });
        previewCount.value = data.count;
    } catch (e) {
        previewCount.value = null;
    } finally {
        previewLoading.value = false;
    }
};

const cleanFilters = () => {
    const cleaned = { ...props.filters };
    Object.keys(cleaned).forEach((k) => {
        const v = cleaned[k];
        // Eliminar vacíos: null, '', false, arrays vacíos
        if (v === '' || v === null || v === false) delete cleaned[k];
        if (Array.isArray(v) && v.length === 0) delete cleaned[k];
    });
    return cleaned;
};

const debouncedFetchPreview = () => {
    clearTimeout(previewTimer);
    previewTimer = setTimeout(fetchPreview, 350);
};

watch(() => props.filters, debouncedFetchPreview, { deep: true });

onMounted(() => {
    fetchPreview();
    if (props.filters.municipio_id) loadParroquias(props.filters.municipio_id);
});

const limpiar = () => {
    // Multi-select: arrays vacíos. Fechas: strings vacíos. Booleans: false.
    if ('desde' in props.filters) props.filters.desde = '';
    if ('hasta' in props.filters) props.filters.hasta = '';
    if ('tecnico_ids' in props.filters) props.filters.tecnico_ids = [];
    if ('municipio_ids' in props.filters) props.filters.municipio_ids = [];
    if ('parroquia_ids' in props.filters) props.filters.parroquia_ids = [];
    if ('estados_reporte' in props.filters) props.filters.estados_reporte = [];
    if ('comparar' in props.filters) props.filters.comparar = false;
    if ('desde_comparacion' in props.filters) props.filters.desde_comparacion = '';
    if ('hasta_comparacion' in props.filters) props.filters.hasta_comparacion = '';
    if ('incluir_graficas' in props.filters) props.filters.incluir_graficas = false;
};

const submit = () => emit('submit');

/**
 * Auto-cálculo del período de comparación cuando el usuario marca el checkbox:
 * toma el rango actual (desde/hasta) y calcula el rango EQUIVALENTE de N días anteriores.
 *
 * Ej: actual = 01/05 al 31/05 (31 días) → comparación = 01/04 al 30/04 (mismos 31 días anteriores)
 */
const periodoActualValido = computed(() => Boolean(props.filters.desde && props.filters.hasta));

const calcularPeriodoAnterior = () => {
    if (!periodoActualValido.value) return;
    const desde = new Date(props.filters.desde);
    const hasta = new Date(props.filters.hasta);
    const dias = Math.round((hasta - desde) / (1000 * 60 * 60 * 24));

    // Hasta del período anterior = un día antes del desde actual
    const hastaComp = new Date(desde);
    hastaComp.setDate(hastaComp.getDate() - 1);

    // Desde del período anterior = N días antes del hasta_comp
    const desdeComp = new Date(hastaComp);
    desdeComp.setDate(desdeComp.getDate() - dias);

    props.filters.desde_comparacion = desdeComp.toISOString().slice(0, 10);
    props.filters.hasta_comparacion = hastaComp.toISOString().slice(0, 10);
};

// Watcher: cuando se marca el checkbox, auto-calcular si hay período principal definido
watch(() => props.filters.comparar, (val) => {
    if (val && periodoActualValido.value && !props.filters.desde_comparacion) {
        calcularPeriodoAnterior();
    }
});

// Watcher: si el usuario cambia el rango principal Y la comparativa está activa, re-calcular
watch(() => [props.filters.desde, props.filters.hasta], () => {
    if (props.filters.comparar && periodoActualValido.value) {
        calcularPeriodoAnterior();
    }
});
</script>

<template>
    <div class="space-y-5">
        <!-- Rango de fechas -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <FormField label="Desde" hint="Fecha inicial del período (opcional)">
                <input v-model="filters.desde" type="date" :class="inputClass" />
            </FormField>
            <FormField label="Hasta" hint="Fecha final del período (opcional)">
                <input v-model="filters.hasta" type="date" :class="inputClass" />
            </FormField>
        </div>

        <!-- Técnico (multi-select) -->
        <FormField label="Técnico(s)" hint="Selecciona uno, varios, o ninguno = todos">
            <MultiSelect
                v-model="filters.tecnico_ids"
                :options="lookups.tecnicos"
                placeholder="Todos los técnicos"
                label-field="nombre_apellido"
            />
        </FormField>

        <!-- Cascada Municipio(s) → Parroquia(s) -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <FormField label="Municipio(s)" hint="Selecciona uno o varios">
                <MultiSelect
                    v-model="filters.municipio_ids"
                    :options="lookups.municipios"
                    placeholder="Todos los municipios"
                    label-field="nombre"
                />
            </FormField>
            <FormField
                label="Parroquia(s)"
                :hint="filters.municipio_ids?.length ? null : 'Selecciona un municipio primero'"
            >
                <MultiSelect
                    v-model="filters.parroquia_ids"
                    :options="parroquias"
                    :placeholder="loadingParroquias ? 'Cargando...' : (filters.municipio_ids?.length ? 'Todas las parroquias' : 'Sin opciones')"
                    label-field="nombre"
                />
            </FormField>
        </div>

        <!-- Estado del reporte (multi-select) -->
        <FormField label="Estado(s) del reporte" hint="Selecciona uno o varios; vacío = todos">
            <MultiSelect
                v-model="filters.estados_reporte"
                :options="estadoOpciones"
                placeholder="Todos los estados"
                label-field="label"
            />
        </FormField>

        <!-- Opciones avanzadas (solo para PDF) -->
        <div v-if="showComparativa" class="border-t border-slate-200 pt-4 space-y-3">
            <h3 class="text-[11px] uppercase tracking-wider text-slate-500 font-semibold">
                Opciones avanzadas
            </h3>

            <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                <input
                    type="checkbox"
                    v-model="filters.comparar"
                    class="rounded border-slate-300 text-cvaup-primary focus:ring-cvaup-primary"
                />
                <span class="text-sm text-slate-700">Incluir comparativa con período anterior</span>
            </label>

            <!-- Aviso si marca el checkbox sin tener rango principal definido -->
            <div
                v-if="filters.comparar && !periodoActualValido"
                class="ml-6 bg-amber-50 border border-amber-200 text-amber-900 rounded-md p-3 text-xs"
            >
                <Icon name="warning" :size="14" class="inline mr-1" />
                Para auto-calcular el período de comparación necesitas definir
                <strong>Desde</strong> y <strong>Hasta</strong> arriba.
                O ingresa el rango de comparación manualmente abajo.
            </div>

            <!-- Inputs de fecha para el período de comparación -->
            <div v-if="filters.comparar" class="ml-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <FormField label="Comparación: Desde">
                    <input
                        v-model="filters.desde_comparacion"
                        type="date"
                        :class="inputClass"
                    />
                </FormField>
                <FormField label="Comparación: Hasta">
                    <input
                        v-model="filters.hasta_comparacion"
                        type="date"
                        :class="inputClass"
                    />
                </FormField>
                <p class="col-span-1 md:col-span-2 text-[11px] text-slate-500 -mt-2">
                    {{ periodoActualValido
                        ? '✓ Auto-calculado en base al rango principal. Puedes editarlo si quieres comparar contra otro período.'
                        : 'Sin rango principal definido — ingresa estas fechas manualmente.' }}
                </p>
            </div>

            <!-- Checkbox 2: incluir resumen gráfico -->
            <label class="inline-flex items-center gap-2 cursor-pointer select-none mt-3">
                <input
                    type="checkbox"
                    v-model="filters.incluir_graficas"
                    class="rounded border-slate-300 text-cvaup-primary focus:ring-cvaup-primary"
                />
                <span class="text-sm text-slate-700">Incluir resumen gráfico</span>
            </label>
            <p v-if="filters.incluir_graficas" class="ml-6 text-[11px] text-slate-500">
                Agrega 1 página al final del PDF con gráficas de barras: top técnicos,
                top municipios y distribución por estado de los reportes filtrados.
            </p>
        </div>

        <!-- Preview + acciones -->
        <div class="flex items-center justify-between flex-wrap gap-4 pt-4 border-t border-slate-200">
            <div class="flex items-center gap-2 text-sm">
                <Icon name="filter" :size="16" class="text-slate-400" />
                <span class="text-slate-500">Vista previa:</span>
                <span v-if="previewLoading" class="text-slate-400">calculando...</span>
                <span v-else-if="previewCount !== null" class="font-bold text-cvaup-primary tabular-nums">
                    {{ previewCount }} reporte(s)
                </span>
                <span v-else class="text-slate-400">—</span>
            </div>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    @click="limpiar"
                    class="px-3 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-md hover:bg-slate-100 transition-colors"
                >
                    Limpiar filtros
                </button>
                <!-- Botón de submit interno: solo visible cuando el partial NO está embebido en pantalla unificada -->
                <button
                    v-if="!hideSubmit"
                    type="button"
                    @click="submit"
                    :disabled="submitting || previewCount === 0"
                    class="px-5 py-2 text-sm font-semibold bg-cvaup-primary text-white rounded-md hover:bg-cvaup-secondary disabled:opacity-50 disabled:cursor-not-allowed transition-colors inline-flex items-center gap-2"
                    :title="previewCount === 0 ? 'No hay reportes con esos filtros' : ''"
                >
                    <Icon name="download" :size="16" />
                    {{ submitting ? 'Generando...' : submitLabel }}
                </button>
            </div>
        </div>
    </div>
</template>
