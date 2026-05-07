<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import Icon from '@/Components/Icon.vue';

/**
 * TreeNode — nodo recursivo del árbol de ubicaciones.
 *
 * Niveles posibles:
 *  - municipio  → al expandir, lazy-load de parroquias
 *  - parroquia  → al expandir, lazy-load de comunas
 *  - comuna     → al expandir, lazy-load de consejos
 *  - consejo    → hoja, no expandible
 *
 * Cada nodo soporta acciones inline:
 *  - Click chevron: expandir/colapsar (con lazy load la primera vez)
 *  - + : agregar hijo
 *  - ✎ : editar nombre
 *  - 🗑️ : eliminar (solo si no tiene hijos ni reportes)
 */
const props = defineProps({
    node: { type: Object, required: true },        // {id, nombre, children_count, reportes_count}
    nivel: { type: String, required: true },       // 'municipio' | 'parroquia' | 'comuna' | 'consejo'
    childNivel: { type: String, default: null },   // siguiente nivel (lo que se carga al expandir)
    expandedIds: { type: Set, default: () => new Set() }, // ids forzados a expandir desde fuera (búsqueda)
});

const emit = defineEmits(['edit', 'add-child', 'delete']);

const expanded = ref(props.expandedIds.has(`${props.nivel}-${props.node.id}`));
const children = ref([]);
const childrenLoaded = ref(false);
const loading = ref(false);

const isLeaf = computed(() => props.nivel === 'consejo');
const hasChildren = computed(() => props.node.children_count > 0);

// Color del icono según nivel (jerarquía visual)
const iconColor = computed(() => ({
    municipio: 'text-cvaup-primary',
    parroquia: 'text-cvaup-secondary',
    comuna: 'text-cvaup-accent',
    consejo: 'text-slate-500',
}[props.nivel]));

const labelNivel = computed(() => ({
    municipio: 'Municipio',
    parroquia: 'Parroquia',
    comuna: 'Comuna',
    consejo: 'CC',
}[props.nivel]));

/**
 * Etiquetas legibles para CADA NIVEL — singular, plural, artículo "Nuevo/Nueva".
 * Centraliza el género gramatical correcto en español para todos los textos del árbol.
 */
const tipoLabels = {
    municipio: { singular: 'municipio', plural: 'municipios', articulo: 'Nuevo' },
    parroquia: { singular: 'parroquia', plural: 'parroquias', articulo: 'Nueva' },
    comuna: { singular: 'comuna', plural: 'comunas', articulo: 'Nueva' },
    consejo: { singular: 'consejo comunal', plural: 'consejos comunales', articulo: 'Nuevo' },
};

/**
 * Conteo de hijos con el TIPO correcto: "3 parroquias" no "3 hijos".
 */
const childCountLabel = computed(() => {
    const count = props.node.children_count;
    if (count === undefined || count === null) return '';
    const label = tipoLabels[props.childNivel];
    if (!label) return '';
    return count === 1 ? label.singular : label.plural;
});

const childPluralLabel = computed(() => tipoLabels[props.childNivel]?.plural ?? '');
const childSingularLabel = computed(() => tipoLabels[props.childNivel]?.singular ?? '');
const childArticuloLabel = computed(() => tipoLabels[props.childNivel]?.articulo ?? 'Nuevo');

const toggle = async () => {
    if (isLeaf.value) return;
    expanded.value = !expanded.value;
    if (expanded.value && !childrenLoaded.value) {
        await loadChildren();
    }
};

const loadChildren = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get('/ubicaciones/children', {
            params: { nivel: props.childNivel, parent_id: props.node.id },
        });
        children.value = data;
        childrenLoaded.value = true;
    } catch (e) {
        children.value = [];
    } finally {
        loading.value = false;
    }
};

// Refresh: vuelve a cargar los hijos (después de agregar/eliminar uno)
const refreshChildren = async () => {
    childrenLoaded.value = false;
    if (expanded.value) await loadChildren();
};

// Bubble up de eventos de hijos para que el padre maneje refresh
const onChildEdited = (payload) => emit('edit', payload);
const onChildAdded = (payload) => {
    emit('add-child', payload);
    refreshChildren();
};
const onChildDeleted = (payload) => {
    emit('delete', payload);
    refreshChildren();
};

defineExpose({ refreshChildren, toggle, expanded });
</script>

<template>
    <div>
        <!-- Fila del nodo -->
        <div
            class="group flex items-center gap-2 py-1.5 px-2 rounded hover:bg-slate-50 transition-colors"
        >
            <!-- Chevron expandible -->
            <button
                v-if="!isLeaf"
                type="button"
                @click="toggle"
                class="text-slate-400 hover:text-slate-700 transition-colors p-0.5"
                :title="expanded ? 'Colapsar' : 'Expandir'"
            >
                <Icon
                    name="chevron-right"
                    :size="14"
                    :class="['transition-transform duration-150', expanded ? 'rotate-90' : '']"
                />
            </button>
            <span v-else class="w-5 inline-block"></span>

            <!-- Icono del nivel -->
            <Icon name="ubicaciones" :size="16" :class="iconColor" />

            <!-- Nombre + counts (con tipo de hijo, no genérico "hijo") -->
            <div class="flex-1 min-w-0 flex items-baseline gap-2">
                <span class="font-medium text-slate-800 truncate">{{ node.nombre }}</span>
                <span v-if="!isLeaf && node.children_count >= 0" class="text-[10px] text-slate-400 tabular-nums shrink-0">
                    ({{ node.children_count }} {{ childCountLabel }})
                </span>
                <span v-if="isLeaf && node.reportes_count > 0" class="text-[10px] text-cvaup-primary font-semibold tabular-nums shrink-0">
                    {{ node.reportes_count }} reporte{{ node.reportes_count !== 1 ? 's' : '' }}
                </span>
            </div>

            <!-- Tag de tipo -->
            <span class="text-[9px] uppercase tracking-wider text-slate-400 shrink-0">{{ labelNivel }}</span>

            <!-- Acciones inline (visibles en hover) -->
            <div class="opacity-0 group-hover:opacity-100 transition-opacity flex items-center gap-1 shrink-0">
                <button
                    v-if="!isLeaf"
                    type="button"
                    @click="emit('add-child', { parent: node, childNivel })"
                    class="p-1 text-slate-400 hover:text-cvaup-primary hover:bg-slate-100 rounded transition-colors"
                    :title="`${childArticuloLabel} ${childSingularLabel} en ${node.nombre}`"
                >
                    <Icon name="plus" :size="14" />
                </button>
                <button
                    type="button"
                    @click="emit('edit', { node, nivel })"
                    class="p-1 text-slate-400 hover:text-cvaup-primary hover:bg-slate-100 rounded transition-colors"
                    title="Editar nombre"
                >
                    <Icon name="edit" :size="14" />
                </button>
                <button
                    type="button"
                    @click="emit('delete', { node, nivel })"
                    class="p-1 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors"
                    title="Eliminar"
                >
                    <Icon name="trash" :size="14" />
                </button>
            </div>
        </div>

        <!-- Hijos (renderizado recursivo) -->
        <div v-if="expanded && !isLeaf" class="ml-5 border-l border-slate-200 pl-2">
            <div v-if="loading" class="text-xs text-slate-400 italic py-2 px-2">
                Cargando...
            </div>
            <div v-else-if="children.length === 0 && childrenLoaded" class="text-xs text-slate-400 italic py-2 px-2">
                No hay {{ childPluralLabel }} registradas bajo este {{ nivel }}.
                <button
                    type="button"
                    @click="emit('add-child', { parent: node, childNivel })"
                    class="text-cvaup-primary hover:underline ml-1"
                >
                    + Agregar {{ childSingularLabel }}
                </button>
            </div>
            <TreeNode
                v-else
                v-for="child in children"
                :key="`${childNivel}-${child.id}`"
                :node="child"
                :nivel="childNivel"
                :child-nivel="childNivel === 'parroquia' ? 'comuna' : childNivel === 'comuna' ? 'consejo' : null"
                :expanded-ids="expandedIds"
                @edit="onChildEdited"
                @add-child="onChildAdded"
                @delete="onChildDeleted"
            />
        </div>
    </div>
</template>
