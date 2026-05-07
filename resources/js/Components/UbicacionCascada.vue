<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import FormField from '@/Components/FormField.vue';
import Icon from '@/Components/Icon.vue';
import axios from 'axios';

/**
 * UbicacionCascada — selects en cascada para Estado→Municipio→Parroquia→Comuna→CC.
 *
 * El estado siempre es Táchira (precargado, no editable).
 * Las parroquias/comunas/CC se cargan vía /api/* on-demand.
 *
 * v-model con un objeto:
 *   {
 *     municipio_id, parroquia_id, comuna_id, consejo_comunal_id
 *   }
 */
const props = defineProps({
    modelValue: {
        type: Object,
        default: () => ({
            municipio_id: null,
            parroquia_id: null,
            comuna_id: null,
            consejo_comunal_id: null,
        }),
    },
    municipios: { type: Array, default: () => [] },
    errors: { type: Object, default: () => ({}) },
    required: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue']);

const parroquias = ref([]);
const comunas = ref([]);
const consejos = ref([]);
const loading = ref({ parroquias: false, comunas: false, consejos: false });

const local = ref({ ...props.modelValue });

// Sincronizar v-model
watch(local, (val) => emit('update:modelValue', { ...val }), { deep: true });
watch(() => props.modelValue, (val) => {
    if (JSON.stringify(val) !== JSON.stringify(local.value)) {
        local.value = { ...val };
    }
}, { deep: true });

// Cargar parroquias cuando cambia municipio
watch(() => local.value.municipio_id, async (newId, oldId) => {
    if (newId && newId !== oldId) {
        loading.value.parroquias = true;
        try {
            const { data } = await axios.get('/api/parroquias', { params: { municipio_id: newId } });
            parroquias.value = data;
        } finally {
            loading.value.parroquias = false;
        }
        // Reset niveles inferiores si cambió municipio
        if (oldId) {
            local.value.parroquia_id = null;
            local.value.comuna_id = null;
            local.value.consejo_comunal_id = null;
            comunas.value = [];
            consejos.value = [];
        }
    } else if (!newId) {
        parroquias.value = [];
        comunas.value = [];
        consejos.value = [];
    }
});

watch(() => local.value.parroquia_id, async (newId, oldId) => {
    if (newId && newId !== oldId) {
        loading.value.comunas = true;
        try {
            const { data } = await axios.get('/api/comunas', { params: { parroquia_id: newId } });
            comunas.value = data;
        } finally {
            loading.value.comunas = false;
        }
        if (oldId) {
            local.value.comuna_id = null;
            local.value.consejo_comunal_id = null;
            consejos.value = [];
        }
    } else if (!newId) {
        comunas.value = [];
        consejos.value = [];
    }
});

watch(() => local.value.comuna_id, async (newId, oldId) => {
    if (newId && newId !== oldId) {
        loading.value.consejos = true;
        try {
            const { data } = await axios.get('/api/consejos', { params: { comuna_id: newId } });
            consejos.value = data;
        } finally {
            loading.value.consejos = false;
        }
        if (oldId) {
            local.value.consejo_comunal_id = null;
        }
    } else if (!newId) {
        consejos.value = [];
    }
});

// Pre-cargar listas si modelValue ya viene con IDs (caso edición)
onMounted(async () => {
    if (local.value.municipio_id) {
        const { data } = await axios.get('/api/parroquias', { params: { municipio_id: local.value.municipio_id } });
        parroquias.value = data;
    }
    if (local.value.parroquia_id) {
        const { data } = await axios.get('/api/comunas', { params: { parroquia_id: local.value.parroquia_id } });
        comunas.value = data;
    }
    if (local.value.comuna_id) {
        const { data } = await axios.get('/api/consejos', { params: { comuna_id: local.value.comuna_id } });
        consejos.value = data;
    }
});

const selectClass = computed(() => 'w-full px-3 py-2 border border-slate-300 rounded-md focus:border-cvaup-primary focus:ring focus:ring-cvaup-primary/20 outline-none transition disabled:bg-slate-100 disabled:text-slate-400 disabled:cursor-not-allowed');

const ubicacionResumen = computed(() => {
    const parts = [];
    if (local.value.municipio_id) {
        const m = props.municipios.find((x) => x.id === local.value.municipio_id);
        if (m) parts.push(m.nombre);
    }
    if (local.value.parroquia_id) {
        const p = parroquias.value.find((x) => x.id === local.value.parroquia_id);
        if (p) parts.push(p.nombre);
    }
    if (local.value.comuna_id) {
        const c = comunas.value.find((x) => x.id === local.value.comuna_id);
        if (c) parts.push(c.nombre);
    }
    if (local.value.consejo_comunal_id) {
        const cc = consejos.value.find((x) => x.id === local.value.consejo_comunal_id);
        if (cc) parts.push(cc.nombre);
    }
    return parts;
});
</script>

<template>
    <div class="space-y-1">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-1">
            <!-- Estado (fijo Táchira) -->
            <FormField label="Estado" :required="required">
                <input type="text" value="Táchira" disabled :class="selectClass" />
            </FormField>

            <!-- Municipio -->
            <FormField label="Municipio" :required="required" :error="errors.municipio_id">
                <select v-model="local.municipio_id" :class="selectClass">
                    <option :value="null">— Selecciona municipio —</option>
                    <option v-for="m in municipios" :key="m.id" :value="m.id">{{ m.nombre }}</option>
                </select>
            </FormField>

            <!-- Parroquia -->
            <FormField label="Parroquia" :required="required" :error="errors.parroquia_id">
                <select
                    v-model="local.parroquia_id"
                    :disabled="!local.municipio_id || loading.parroquias"
                    :class="selectClass"
                >
                    <option :value="null">
                        {{ !local.municipio_id ? '— Selecciona un municipio primero —' : (loading.parroquias ? 'Cargando...' : '— Selecciona parroquia —') }}
                    </option>
                    <option v-for="p in parroquias" :key="p.id" :value="p.id">{{ p.nombre }}</option>
                </select>
            </FormField>

            <!-- Comuna -->
            <FormField label="Comuna" :required="required" :error="errors.comuna_id">
                <select
                    v-model="local.comuna_id"
                    :disabled="!local.parroquia_id || loading.comunas"
                    :class="selectClass"
                >
                    <option :value="null">
                        {{ !local.parroquia_id ? '— Selecciona una parroquia primero —' : (loading.comunas ? 'Cargando...' : '— Selecciona comuna —') }}
                    </option>
                    <option v-for="c in comunas" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                </select>
            </FormField>

            <!-- Consejo Comunal (full width) -->
            <FormField
                label="Consejo Comunal"
                :required="required"
                :error="errors.consejo_comunal_id"
                class="md:col-span-2"
            >
                <select
                    v-model="local.consejo_comunal_id"
                    :disabled="!local.comuna_id || loading.consejos"
                    :class="selectClass"
                >
                    <option :value="null">
                        {{ !local.comuna_id ? '— Selecciona una comuna primero —' : (loading.consejos ? 'Cargando...' : '— Selecciona consejo comunal —') }}
                    </option>
                    <option v-for="cc in consejos" :key="cc.id" :value="cc.id">{{ cc.nombre }}</option>
                </select>
            </FormField>
        </div>

        <!-- Resumen visual de la ubicación seleccionada -->
        <div
            v-if="ubicacionResumen.length > 0"
            class="mt-3 p-3 bg-green-50 border border-green-200 rounded-md flex items-center gap-2 text-sm text-green-800"
        >
            <Icon name="ubicaciones" :size="16" />
            <span class="font-medium">Ubicación:</span>
            <span>Táchira → {{ ubicacionResumen.join(' → ') }}</span>
        </div>
    </div>
</template>
