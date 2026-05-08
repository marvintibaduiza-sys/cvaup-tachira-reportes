<script setup>
import { computed, ref, watch } from 'vue';
import FormField from '@/Components/FormField.vue';
import FotoUploader from '@/Components/FotoUploader.vue';
import Toggle from '@/Components/Toggle.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { Link } from '@inertiajs/vue3';

/**
 * Form compartido entre Create y Edit.
 *
 * El padre maneja `useForm()` de Inertia y se lo pasa por v-model:form.
 *
 * Cambios estructurales (BLOQUE 4):
 *  - nombre y apellido como campos separados (antes: nombre_apellido único)
 *  - tipo_documento como selector con iniciales V/E/J/G/P (sin descripción larga —
 *    la admin pública venezolana entiende las iniciales)
 *  - cedula solo dígitos, sin puntos
 *
 * BLOQUE 4.5:
 *  - especialidades como MULTI-select (un técnico puede tener varias)
 *  - opción de agregar especialidades libres no listadas
 */
const props = defineProps({
    form: { type: Object, required: true },
    municipios: { type: Array, default: () => [] },
    tiposDocumento: { type: Array, default: () => [] },     // ["V", "E", "J", "G", "P"]
    especialidades: { type: Array, default: () => [] },     // ["Agronomía urbana", "Hidroponía", ...]
    isEdit: { type: Boolean, default: false },
    cancelHref: { type: String, default: '/tecnicos' },
    submitLabel: { type: String, default: 'Guardar' },
});

const emit = defineEmits(['submit']);

const inputClass = 'w-full px-3 py-2 border border-slate-300 rounded-md focus:border-cvaup-primary focus:ring focus:ring-cvaup-primary/20 outline-none transition';

const estadoBoolean = computed({
    get: () => props.form.estado === 'activo',
    set: (val) => (props.form.estado = val ? 'activo' : 'inactivo'),
});

// ── Especialidades: MULTI-select con opción de agregar custom ───────
//
// BLOQUE 4.5: un técnico puede tener una o varias especialidades.
//   - Lista predefinida (ESPECIALIDADES) como checkboxes
//   - Input adicional para agregar especialidades libres no listadas
//
// El form.especialidades es un Array<string>. Las custom no listadas
// aparecen también marcadas (porque van en form.especialidades).

const inputNuevaCustom = ref('');

// Especialidades CUSTOM que el técnico ya tiene pero no están en la lista predefinida.
// Las mostramos junto con las predefinidas, marcadas, para que se puedan toggle/quitar.
const especialidadesCustomActuales = computed(() => {
    const arr = props.form.especialidades || [];
    return arr.filter((e) => !props.especialidades.includes(e));
});

const toggleEspecialidad = (esp) => {
    const actuales = props.form.especialidades || [];
    if (actuales.includes(esp)) {
        props.form.especialidades = actuales.filter((e) => e !== esp);
    } else {
        props.form.especialidades = [...actuales, esp];
    }
};

const isEspecialidadSelected = (esp) => (props.form.especialidades || []).includes(esp);

const agregarCustom = () => {
    const v = inputNuevaCustom.value.trim();
    if (!v) return;
    const actuales = props.form.especialidades || [];
    if (actuales.includes(v)) {
        inputNuevaCustom.value = '';
        return;
    }
    props.form.especialidades = [...actuales, v];
    inputNuevaCustom.value = '';
};

const onEnterCustom = (e) => {
    // Enter dentro del input agrega — Enter NO debe enviar el form.
    e.preventDefault();
    agregarCustom();
};

// ── Cédula: normalización en vivo ───────────────────────────────────
// Mientras el usuario escribe, automáticamente quita puntos, guiones y espacios.
// Solo permite dígitos.
const onCedulaInput = (event) => {
    props.form.cedula = (event.target.value || '').replace(/\D/g, '');
};

const submitForm = () => emit('submit');
</script>

<template>
    <form @submit.prevent="submitForm" class="space-y-6">
        <!-- ─── Datos personales ─────────────────────────────────────── -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-1">
            <!-- Nombre -->
            <FormField label="Nombre" required :error="form.errors.nombre">
                <input
                    v-model="form.nombre"
                    type="text"
                    :class="inputClass"
                    placeholder="Ej: María"
                    autofocus
                />
            </FormField>

            <!-- Apellido -->
            <FormField label="Apellido" required :error="form.errors.apellido">
                <input
                    v-model="form.apellido"
                    type="text"
                    :class="inputClass"
                    placeholder="Ej: González Pérez"
                />
            </FormField>
        </div>

        <!-- ─── Documento + teléfono (compactos en una fila) ─────────── -->
        <!--
            Tipo doc, cédula y teléfono son campos cortos. Antes ocupaban 2 filas;
            ahora caben en una sola con grid de 12 columnas:
              - tipo_documento (2/12) — solo 1 carácter (V/E/J/G/P)
              - cedula (5/12) — 6-10 dígitos
              - telefono (5/12) — 11 dígitos con guion
            En mobile (<md) cada campo vuelve a ocupar el ancho completo.
        -->
        <div class="grid grid-cols-12 gap-x-4 gap-y-1">
            <!-- Tipo de documento — solo iniciales (V/E/J/G/P) -->
            <FormField
                label="Tipo"
                required
                :error="form.errors.tipo_documento"
                class="col-span-12 sm:col-span-3 md:col-span-2"
            >
                <select v-model="form.tipo_documento" :class="inputClass">
                    <option value="" disabled>—</option>
                    <option
                        v-for="codigo in tiposDocumento"
                        :key="codigo"
                        :value="codigo"
                    >
                        {{ codigo }}
                    </option>
                </select>
            </FormField>

            <!-- Cédula (solo dígitos) -->
            <FormField
                label="Cédula"
                required
                :error="form.errors.cedula"
                hint="Sin puntos ni guion"
                class="col-span-12 sm:col-span-9 md:col-span-5"
            >
                <input
                    :value="form.cedula"
                    @input="onCedulaInput"
                    type="text"
                    inputmode="numeric"
                    pattern="\d*"
                    :class="inputClass"
                    placeholder="18456789"
                    maxlength="10"
                />
            </FormField>

            <!-- Teléfono -->
            <FormField
                label="Teléfono"
                :error="form.errors.telefono"
                class="col-span-12 md:col-span-5"
            >
                <input
                    v-model="form.telefono"
                    type="text"
                    :class="inputClass"
                    placeholder="0414-1234567"
                />
            </FormField>
        </div>

        <!-- ─── Especialidades (multi-select) — BLOQUE 4.5 ──────────── -->
        <FormField
            label="Especialidades"
            :error="form.errors.especialidades || form.errors['especialidades.0']"
            hint="Marca todas las que apliquen. Si no está en la lista, escríbela abajo y pulsa «Agregar»."
        >
            <!-- Lista de chips toggle: predefinidas + custom existentes -->
            <div class="flex flex-wrap gap-2 mb-3">
                <button
                    v-for="esp in especialidades"
                    :key="esp"
                    type="button"
                    @click="toggleEspecialidad(esp)"
                    :class="[
                        'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium border transition-colors',
                        isEspecialidadSelected(esp)
                            ? 'bg-cvaup-primary text-white border-cvaup-primary hover:bg-cvaup-secondary'
                            : 'bg-white text-slate-600 border-slate-300 hover:bg-slate-50',
                    ]"
                >
                    <svg
                        v-if="isEspecialidadSelected(esp)"
                        class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                    </svg>
                    {{ esp }}
                </button>

                <!-- Custom existentes (no en la lista predefinida): se ven como verdes para distinguir -->
                <button
                    v-for="esp in especialidadesCustomActuales"
                    :key="`custom-${esp}`"
                    type="button"
                    @click="toggleEspecialidad(esp)"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium border transition-colors bg-emerald-600 text-white border-emerald-600 hover:bg-emerald-700"
                    :title="`Especialidad personalizada — clic para quitar`"
                >
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                    </svg>
                    {{ esp }}
                    <span class="text-[9px] opacity-80 ml-0.5">(personalizada)</span>
                </button>
            </div>

            <!-- Input para agregar especialidad custom -->
            <div class="flex items-center gap-2">
                <input
                    v-model="inputNuevaCustom"
                    @keydown.enter="onEnterCustom"
                    type="text"
                    :class="[inputClass, 'flex-1']"
                    placeholder="Otra especialidad (escribe y pulsa Enter o «Agregar»)"
                    maxlength="120"
                />
                <button
                    type="button"
                    @click="agregarCustom"
                    :disabled="!inputNuevaCustom.trim()"
                    class="px-3 py-2 text-xs font-semibold bg-emerald-600 text-white rounded-md hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors whitespace-nowrap"
                >
                    + Agregar
                </button>
            </div>

            <!-- Resumen visible para confirmar selección -->
            <p
                v-if="(form.especialidades || []).length > 0"
                class="mt-2 text-[11px] text-slate-500"
            >
                {{ (form.especialidades || []).length }} especialidad{{ (form.especialidades || []).length === 1 ? '' : 'es' }} seleccionada{{ (form.especialidades || []).length === 1 ? '' : 's' }}
            </p>
        </FormField>

        <!-- ─── Estado ──────────────────────────────────────────────── -->
        <FormField label="Estado" required :error="form.errors.estado">
            <Toggle v-model="estadoBoolean" />
        </FormField>

        <!-- ─── Zonas asignadas (multi-select municipios) ──────────── -->
        <FormField
            label="Zonas asignadas (municipios)"
            :error="form.errors.municipio_ids"
            hint="Selecciona uno o varios municipios. Estos se sugerirán automáticamente cuando este técnico aparezca en filtros de Generar Reportes."
        >
            <MultiSelect
                v-model="form.municipio_ids"
                :options="municipios"
                placeholder="Selecciona uno o varios municipios..."
                search-placeholder="Buscar municipio..."
                label-field="nombre"
            />
        </FormField>

        <!-- ─── Foto de perfil ──────────────────────────────────────── -->
        <FotoUploader
            v-model="form.foto"
            :current-url="form.foto_url ?? null"
            :error="form.errors.foto"
            label="Foto de perfil (opcional)"
            @remove-current="form.eliminar_foto = true"
        />

        <!-- ─── Acciones ────────────────────────────────────────────── -->
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
