<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Card from '@/Components/Card.vue';
import Icon from '@/Components/Icon.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import Modal from '@/Components/Modal.vue';
import TreeNode from '@/Components/TreeNode.vue';
import TablaUbicaciones from '@/Components/TablaUbicaciones.vue';

const props = defineProps({
    estado: { type: Object, default: null },
    municipios: { type: Array, default: () => [] },
    estadisticas: { type: Object, required: true },
});

// Estado local de los municipios (para refresh tras add/edit/delete)
const municipios = ref([...props.municipios]);

// Banner de éxito local — usado para feedback visual tras add/edit/delete
// (no usamos flash de Inertia porque las acciones son AJAX puro, no recargan página)
const successMessage = ref('');
const showSuccess = (msg) => {
    successMessage.value = msg;
    // Auto-ocultar después de 4 segundos
    setTimeout(() => { successMessage.value = ''; }, 4000);
};

// Tab activa: 'arbol' o 'tabla' — se persiste en localStorage para que el usuario no la pierda
const activeTab = ref(localStorage.getItem('ubicaciones-vista') || 'arbol');
const cambiarTab = (tab) => {
    activeTab.value = tab;
    localStorage.setItem('ubicaciones-vista', tab);
};

// ─── Búsqueda global ───
const searchQuery = ref('');
const searchResults = ref([]);
const searching = ref(false);
let searchTimer = null;

const debouncedSearch = () => {
    clearTimeout(searchTimer);
    if (searchQuery.value.length < 2) {
        searchResults.value = [];
        return;
    }
    searching.value = true;
    searchTimer = setTimeout(async () => {
        try {
            const { data } = await axios.get('/ubicaciones/buscar', { params: { q: searchQuery.value } });
            searchResults.value = data;
        } catch (e) {
            searchResults.value = [];
        } finally {
            searching.value = false;
        }
    }, 300);
};

watch(searchQuery, debouncedSearch);

const limpiarBusqueda = () => {
    searchQuery.value = '';
    searchResults.value = [];
};

// ─── Modal: editar nombre ───
const editModal = ref({ open: false, node: null, nivel: null, nombre: '', error: '' });

const onEdit = ({ node, nivel }) => {
    editModal.value = { open: true, node, nivel, nombre: node.nombre, error: '' };
};

const guardarEdicion = async () => {
    if (!editModal.value.node) return;
    editModal.value.error = '';
    try {
        await axios.put(`/ubicaciones/${editModal.value.nivel}/${editModal.value.node.id}`, {
            nombre: editModal.value.nombre,
        });
        const nombreNuevo = editModal.value.nombre.trim();
        editModal.value.node.nombre = nombreNuevo;
        editModal.value.open = false;
        // refresca municipios si fue municipio editado
        if (editModal.value.nivel === 'municipio') {
            const m = municipios.value.find(m => m.id === editModal.value.node.id);
            if (m) m.nombre = nombreNuevo;
        }
        showSuccess(`"${nombreNuevo}" actualizado correctamente.`);
    } catch (e) {
        editModal.value.error = e.response?.data?.message || 'Error guardando.';
    }
};

// ─── Modal: agregar hijo ───
const addModal = ref({ open: false, parent: null, childNivel: null, nombre: '', error: '' });

const onAddChild = ({ parent, childNivel }) => {
    addModal.value = { open: true, parent, childNivel, nombre: '', error: '' };
};

const onAddTopLevel = () => {
    addModal.value = {
        open: true,
        parent: { id: props.estado?.id, nombre: props.estado?.nombre },
        childNivel: 'municipio',
        nombre: '',
        error: '',
    };
};

const guardarNuevo = async () => {
    if (!addModal.value.nombre.trim()) {
        addModal.value.error = 'El nombre es obligatorio.';
        return;
    }
    addModal.value.error = '';
    try {
        const { data } = await axios.post(`/ubicaciones/${addModal.value.childNivel}`, {
            parent_id: addModal.value.parent.id,
            nombre: addModal.value.nombre,
        });
        const nombreCreado = data.nombre || addModal.value.nombre.trim();
        addModal.value.open = false;

        // Si fue un municipio nuevo, refrescar la lista
        if (addModal.value.childNivel === 'municipio') {
            municipios.value.push({
                id: data.id,
                nombre: data.nombre,
                parroquias_count: 0,
                reportes_count: 0,
            });
            municipios.value.sort((a, b) => a.nombre.localeCompare(b.nombre));
        }
        showSuccess(`"${nombreCreado}" creado correctamente.`);
        // Reload página para reflejar contadores actualizados (más confiable que sync manual)
        router.reload({ only: ['estadisticas'] });
    } catch (e) {
        addModal.value.error = e.response?.data?.message || 'Error creando.';
    }
};

// ─── Confirmar eliminación ───
const deleteConfirm = ref({ open: false, node: null, nivel: null, error: '' });

const onDelete = ({ node, nivel }) => {
    deleteConfirm.value = { open: true, node, nivel, error: '' };
};

const ejecutarEliminacion = async () => {
    try {
        const { data } = await axios.delete(`/ubicaciones/${deleteConfirm.value.nivel}/${deleteConfirm.value.node.id}`);
        // Si fue municipio, sacar de la lista local
        if (deleteConfirm.value.nivel === 'municipio') {
            municipios.value = municipios.value.filter(m => m.id !== deleteConfirm.value.node.id);
        }
        deleteConfirm.value.open = false;
        // Banner verde de feedback (usa el mensaje del backend si existe)
        showSuccess(data?.message || 'Eliminado correctamente.');
        router.reload({ only: ['estadisticas'] });
    } catch (e) {
        deleteConfirm.value.error = e.response?.data?.message || 'Error eliminando.';
    }
};

const inputClass = 'w-full px-3 py-2 text-sm border border-slate-300 rounded-md focus:border-cvaup-primary focus:ring focus:ring-cvaup-primary/20 outline-none transition';

/**
 * Etiquetas con género gramatical correcto. Compartidas con TreeNode.vue.
 */
const tipoLabels = {
    municipio: { singular: 'municipio', articulo: 'Nuevo' },
    parroquia: { singular: 'parroquia', articulo: 'Nueva' },
    comuna: { singular: 'comuna', articulo: 'Nueva' },
    consejo: { singular: 'consejo comunal', articulo: 'Nuevo' },
};

const addModalTitle = computed(() => {
    const t = tipoLabels[addModal.value.childNivel];
    return t ? `${t.articulo} ${t.singular}` : 'Nueva ubicación';
});

const addModalLabel = computed(() => {
    const t = tipoLabels[addModal.value.childNivel];
    return t ? `Nombre ${t.articulo === 'Nuevo' ? 'del nuevo' : 'de la nueva'} ${t.singular}` : 'Nombre';
});

const editModalTitle = computed(() => {
    const t = tipoLabels[editModal.value.nivel];
    return t ? `Editar ${t.singular}` : 'Editar ubicación';
});

// Placeholder con ejemplos reales para guiar al admin
const addModalPlaceholder = computed(() => {
    const ejemplos = {
        municipio: 'Ej: San Cristóbal',
        parroquia: 'Ej: San Juan Bautista',
        comuna: 'Ej: Cordillera Andina Bolivariana',
        consejo: 'Ej: La Pradera I',
    };
    return ejemplos[addModal.value.childNivel] ?? 'Nombre';
});
</script>

<template>
    <Head title="Gestión de Ubicaciones" />

    <AuthenticatedLayout title="Gestión de Ubicaciones">
        <!-- Banner de éxito (auto-oculta a los 4s) — feedback tras add/edit/delete -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 -translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="successMessage"
                class="bg-green-50 border border-green-200 text-green-800 px-4 py-2.5 rounded-md text-sm mb-4 flex items-center gap-2"
            >
                <Icon name="check" :size="16" class="text-green-600" />
                <span>{{ successMessage }}</span>
            </div>
        </Transition>

        <!-- Header con stats + acciones -->
        <div class="flex items-center justify-between flex-wrap gap-3 mb-4">
            <div class="flex items-center gap-2 text-sm flex-wrap">
                <span class="bg-white border border-slate-200 rounded-md px-2.5 py-1">
                    <strong class="tabular-nums">{{ estadisticas.estados }}</strong> estado
                </span>
                <span class="bg-white border border-slate-200 rounded-md px-2.5 py-1">
                    <strong class="tabular-nums">{{ estadisticas.municipios }}</strong> municipios
                </span>
                <span class="bg-white border border-slate-200 rounded-md px-2.5 py-1">
                    <strong class="tabular-nums">{{ estadisticas.parroquias }}</strong> parroquias
                </span>
                <span class="bg-white border border-slate-200 rounded-md px-2.5 py-1">
                    <strong class="tabular-nums">{{ estadisticas.comunas }}</strong> comunas
                </span>
                <span class="bg-white border border-slate-200 rounded-md px-2.5 py-1">
                    <strong class="tabular-nums">{{ estadisticas.consejos }}</strong> consejos comunales
                </span>
            </div>

            <div class="flex items-center gap-2">
                <Link
                    href="/ubicaciones/importar"
                    class="inline-flex items-center gap-2 px-3 py-1.5 text-sm bg-white text-slate-700 border border-slate-300 rounded-md hover:bg-slate-50 transition-colors"
                >
                    <Icon name="upload" :size="16" />
                    Importar Excel
                </Link>
                <button
                    type="button"
                    @click="onAddTopLevel"
                    class="inline-flex items-center gap-2 px-3 py-1.5 text-sm bg-cvaup-primary text-white rounded-md hover:bg-cvaup-secondary transition-colors"
                    :disabled="!estado"
                >
                    <Icon name="plus" :size="16" />
                    Agregar municipio
                </button>
            </div>
        </div>

        <!-- Tabs de vista -->
        <div class="flex items-center gap-1 mb-3 border-b border-slate-200">
            <button
                type="button"
                @click="cambiarTab('arbol')"
                :class="[
                    'px-4 py-2 text-sm font-medium transition-colors flex items-center gap-2 -mb-px border-b-2',
                    activeTab === 'arbol'
                        ? 'text-cvaup-primary border-cvaup-primary'
                        : 'text-slate-500 border-transparent hover:text-slate-700',
                ]"
            >
                <Icon name="tree" :size="16" />
                Vista de árbol
            </button>
            <button
                type="button"
                @click="cambiarTab('tabla')"
                :class="[
                    'px-4 py-2 text-sm font-medium transition-colors flex items-center gap-2 -mb-px border-b-2',
                    activeTab === 'tabla'
                        ? 'text-cvaup-primary border-cvaup-primary'
                        : 'text-slate-500 border-transparent hover:text-slate-700',
                ]"
            >
                <Icon name="dashboard" :size="16" />
                Vista de tabla
            </button>
        </div>

        <!-- ─── TAB ÁRBOL ─── -->
        <div v-show="activeTab === 'arbol'">
            <!-- Buscador global -->
            <Card class="mb-4">
                <div class="relative">
                    <Icon name="search" :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Buscar municipio, parroquia, comuna o consejo comunal..."
                        :class="['pl-9 pr-9 w-full', inputClass]"
                    />
                    <button
                        v-if="searchQuery"
                        type="button"
                        @click="limpiarBusqueda"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700"
                    >
                        <Icon name="close" :size="14" />
                    </button>
                </div>

                <!-- Resultados de búsqueda -->
                <div v-if="searchQuery.length >= 2" class="mt-3 max-h-80 overflow-y-auto">
                    <div v-if="searching" class="text-xs text-slate-400 italic py-2">Buscando...</div>
                    <div v-else-if="searchResults.length === 0" class="text-xs text-slate-400 italic py-2">
                        No se encontraron resultados para "{{ searchQuery }}"
                    </div>
                    <ul v-else class="space-y-1">
                        <li
                            v-for="r in searchResults"
                            :key="`${r.tipo}-${r.id}`"
                            class="text-sm py-1.5 px-2 hover:bg-slate-50 rounded flex items-center gap-2"
                        >
                            <Icon name="ubicaciones" :size="14" class="text-cvaup-primary shrink-0" />
                            <span class="text-slate-600 text-xs flex-1 min-w-0 truncate">{{ r.breadcrumb }}</span>
                            <span class="text-[9px] uppercase tracking-wider text-slate-400 shrink-0">{{ r.tipo }}</span>
                        </li>
                    </ul>
                    <p v-if="searchResults.length === 30" class="text-[11px] text-slate-400 italic mt-2 text-center">
                        Mostrando primeros 30 resultados — refina la búsqueda para encontrar otros.
                    </p>
                </div>
            </Card>

            <!-- Árbol jerárquico -->
            <Card>
                <EmptyState v-if="!estado" icon="ubicaciones" message="No hay estado registrado. Importa un Excel para comenzar." />
                <div v-else>
                    <!-- Estado raíz (no expandible, contenedor) -->
                    <div class="flex items-center gap-2 py-2 px-2 mb-2 bg-cvaup-primary/5 rounded">
                        <Icon name="ubicaciones" :size="18" class="text-cvaup-primary" />
                        <span class="font-bold text-cvaup-primary">{{ estado.nombre }}</span>
                        <span class="text-[10px] uppercase tracking-wider text-cvaup-primary/70">Estado</span>
                        <span class="text-xs text-slate-500 ml-auto tabular-nums">{{ municipios.length }} municipios</span>
                    </div>

                    <EmptyState v-if="municipios.length === 0" icon="ubicaciones" message="Sin municipios registrados." />

                    <div v-else class="ml-3 border-l border-slate-200 pl-2">
                        <TreeNode
                            v-for="m in municipios"
                            :key="`municipio-${m.id}`"
                            :node="{ id: m.id, nombre: m.nombre, children_count: m.parroquias_count, reportes_count: m.reportes_count }"
                            nivel="municipio"
                            child-nivel="parroquia"
                            @edit="onEdit"
                            @add-child="onAddChild"
                            @delete="onDelete"
                        />
                    </div>
                </div>
            </Card>
        </div>

        <!-- ─── TAB TABLA ─── -->
        <div v-show="activeTab === 'tabla'">
            <Card>
                <TablaUbicaciones :municipios="municipios" />
            </Card>
        </div>

        <!-- Modal: editar nombre -->
        <Modal :show="editModal.open" max-width="md" @close="editModal.open = false">
            <!-- Header institucional con icono + título + close -->
            <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-200 bg-cvaup-primary/5">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-md bg-cvaup-primary/10 flex items-center justify-center">
                        <Icon name="edit" :size="16" class="text-cvaup-primary" />
                    </div>
                    <h2 class="text-sm font-semibold text-slate-800">{{ editModalTitle }}</h2>
                </div>
                <button
                    @click="editModal.open = false"
                    class="text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded p-1 transition-colors"
                    aria-label="Cerrar"
                >
                    <Icon name="close" :size="16" />
                </button>
            </div>

            <!-- Body con espaciado generoso y label claro -->
            <div class="px-5 py-5">
                <label class="block">
                    <span class="text-xs font-semibold text-slate-700 uppercase tracking-wide mb-2 block">
                        Nombre
                    </span>
                    <input
                        v-model="editModal.nombre"
                        type="text"
                        :class="inputClass"
                        autofocus
                        @keydown.enter="guardarEdicion"
                    />
                </label>
                <p v-if="editModal.error" class="text-xs text-red-600 mt-2 flex items-center gap-1.5">
                    <Icon name="warning" :size="12" />
                    {{ editModal.error }}
                </p>
            </div>

            <!-- Footer con border-top y botones bien definidos -->
            <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-slate-200 bg-slate-50 rounded-b-lg">
                <button
                    @click="editModal.open = false"
                    class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-md hover:bg-slate-100 transition-colors"
                >
                    Cancelar
                </button>
                <button
                    @click="guardarEdicion"
                    class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold bg-cvaup-primary text-white rounded-md hover:bg-cvaup-secondary transition-colors"
                >
                    <Icon name="check" :size="14" />
                    Guardar cambios
                </button>
            </div>
        </Modal>

        <!-- Modal: agregar hijo -->
        <Modal :show="addModal.open" max-width="md" @close="addModal.open = false">
            <!-- Header institucional -->
            <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-200 bg-cvaup-primary/5">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-md bg-cvaup-primary/10 flex items-center justify-center">
                        <Icon name="plus" :size="16" class="text-cvaup-primary" />
                    </div>
                    <h2 class="text-sm font-semibold text-slate-800">{{ addModalTitle }}</h2>
                </div>
                <button
                    @click="addModal.open = false"
                    class="text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded p-1 transition-colors"
                    aria-label="Cerrar"
                >
                    <Icon name="close" :size="16" />
                </button>
            </div>

            <!-- Body: contexto del padre + input -->
            <div class="px-5 py-5 space-y-4">
                <!-- Pill que muestra bajo qué nodo se está creando -->
                <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-slate-100 border border-slate-200 rounded-md text-xs">
                    <Icon name="tree" :size="12" class="text-slate-500" />
                    <span class="text-slate-500">Se creará bajo:</span>
                    <strong class="text-slate-800">{{ addModal.parent?.nombre }}</strong>
                </div>

                <label class="block">
                    <span class="text-xs font-semibold text-slate-700 uppercase tracking-wide mb-2 block">
                        {{ addModalLabel }}
                    </span>
                    <input
                        v-model="addModal.nombre"
                        type="text"
                        :class="inputClass"
                        :placeholder="addModalPlaceholder"
                        autofocus
                        @keydown.enter="guardarNuevo"
                    />
                </label>
                <p v-if="addModal.error" class="text-xs text-red-600 flex items-center gap-1.5">
                    <Icon name="warning" :size="12" />
                    {{ addModal.error }}
                </p>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-slate-200 bg-slate-50 rounded-b-lg">
                <button
                    @click="addModal.open = false"
                    class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-md hover:bg-slate-100 transition-colors"
                >
                    Cancelar
                </button>
                <button
                    @click="guardarNuevo"
                    class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold bg-cvaup-primary text-white rounded-md hover:bg-cvaup-secondary transition-colors"
                >
                    <Icon name="plus" :size="14" />
                    Crear
                </button>
            </div>
        </Modal>

        <!-- Confirmación de eliminación -->
        <ConfirmDialog
            :show="deleteConfirm.open"
            :title="`Eliminar ${deleteConfirm.nivel}`"
            :message="deleteConfirm.error || `¿Estás seguro de eliminar ${deleteConfirm.node?.nombre}? No se puede deshacer.`"
            confirm-label="Sí, eliminar"
            variant="danger"
            @confirm="ejecutarEliminacion"
            @cancel="deleteConfirm.open = false"
        />
    </AuthenticatedLayout>
</template>
