<script setup>
import { computed } from 'vue';
import FormField from '@/Components/FormField.vue';
import FotoUploader from '@/Components/FotoUploader.vue';
import Toggle from '@/Components/Toggle.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { Link } from '@inertiajs/vue3';

/**
 * Form compartido entre Create y Edit.
 *
 * El padre maneja `useForm()` de Inertia y se lo pasa por v-model:form.
 */
const props = defineProps({
    form: { type: Object, required: true },
    municipios: { type: Array, default: () => [] },
    isEdit: { type: Boolean, default: false },
    cancelHref: { type: String, default: '/tecnicos' },
    submitLabel: { type: String, default: 'Guardar' },
});

const emit = defineEmits(['submit']);

const estadoBoolean = computed({
    get: () => props.form.estado === 'activo',
    set: (val) => (props.form.estado = val ? 'activo' : 'inactivo'),
});

const submitForm = () => emit('submit');
</script>

<template>
    <form @submit.prevent="submitForm" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-1">
            <!-- Nombre -->
            <FormField label="Nombre y Apellido" required :error="form.errors.nombre_apellido">
                <input
                    v-model="form.nombre_apellido"
                    type="text"
                    class="w-full px-3 py-2 border border-slate-300 rounded-md focus:border-cvaup-primary focus:ring focus:ring-cvaup-primary/20 outline-none transition"
                    placeholder="Ej: María González"
                    autofocus
                />
            </FormField>

            <!-- Cédula -->
            <FormField
                label="Cédula"
                required
                :error="form.errors.cedula"
                hint="Formato venezolano: V-XX.XXX.XXX (ej: V-18.456.789)"
            >
                <input
                    v-model="form.cedula"
                    type="text"
                    class="w-full px-3 py-2 border border-slate-300 rounded-md focus:border-cvaup-primary focus:ring focus:ring-cvaup-primary/20 outline-none transition"
                    placeholder="V-18.456.789"
                />
            </FormField>

            <!-- Teléfono -->
            <FormField label="Teléfono" :error="form.errors.telefono">
                <input
                    v-model="form.telefono"
                    type="text"
                    class="w-full px-3 py-2 border border-slate-300 rounded-md focus:border-cvaup-primary focus:ring focus:ring-cvaup-primary/20 outline-none transition"
                    placeholder="0414-1234567"
                />
            </FormField>

            <!-- Especialidad -->
            <FormField label="Especialidad" :error="form.errors.especialidad">
                <input
                    v-model="form.especialidad"
                    type="text"
                    class="w-full px-3 py-2 border border-slate-300 rounded-md focus:border-cvaup-primary focus:ring focus:ring-cvaup-primary/20 outline-none transition"
                    placeholder="Ej: Horticultura, Fruticultura"
                />
            </FormField>

            <!-- Estado -->
            <FormField label="Estado" required :error="form.errors.estado">
                <Toggle v-model="estadoBoolean" />
            </FormField>
        </div>

        <!-- Zonas asignadas (multi-select municipios) -->
        <FormField
            label="Zonas asignadas (municipios)"
            :error="form.errors.municipio_ids"
            hint="Selecciona uno o varios municipios. Las parroquias, comunas y consejos comunales quedan implícitos."
        >
            <MultiSelect
                v-model="form.municipio_ids"
                :options="municipios"
                placeholder="Selecciona uno o varios municipios..."
                search-placeholder="Buscar municipio..."
                label-field="nombre"
            />
        </FormField>

        <!-- Foto de perfil -->
        <FotoUploader
            v-model="form.foto"
            :current-url="form.foto_url ?? null"
            :error="form.errors.foto"
            label="Foto de perfil (opcional)"
            @remove-current="form.eliminar_foto = true"
        />

        <!-- Acciones -->
        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200">
            <Link
                :href="cancelHref"
                class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-md hover:bg-slate-100 transition-colors"
            >
                Cancelar
            </Link>
            <button
                type="submit"
                :disabled="form.processing"
                class="px-5 py-2 text-sm font-semibold bg-cvaup-primary text-white rounded-md hover:bg-cvaup-secondary disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
                {{ form.processing ? 'Guardando...' : submitLabel }}
            </button>
        </div>
    </form>
</template>
