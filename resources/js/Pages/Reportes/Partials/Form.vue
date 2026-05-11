<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import FormField from '@/Components/FormField.vue';
import UbicacionCascada from '@/Components/UbicacionCascada.vue';
import MultiFotoUploader from '@/Components/MultiFotoUploader.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import Icon from '@/Components/Icon.vue';

/**
 * Form de Reporte — compartido entre Create y Edit.
 * Estructura en 6 secciones según el spec.
 *
 * BLOQUE 5: agrega multi-selects para "otras comunas atendidas" y "otros CCs",
 * elimina los inputs manuales de cantidad — se calculan automáticamente.
 */
const props = defineProps({
    form: { type: Object, required: true },
    tecnicos: { type: Array, default: () => [] },
    municipios: { type: Array, default: () => [] },
    todasLasComunas: { type: Array, default: () => [] },           // BLOQUE 5
    todosLosConsejosComunales: { type: Array, default: () => [] }, // BLOQUE 5
    tiposActividad: { type: Array, default: () => [] }, // BLOQUE 8: ['Capacitación', 'Asesoría técnica', ...]
    fotosExistentes: { type: Array, default: () => [] }, // solo en edit
    isEdit: { type: Boolean, default: false },
    cancelHref: { type: String, default: '/reportes' },
});

const emit = defineEmits(['submit']);

const inputClass = 'w-full px-3 py-2 border border-slate-300 rounded-md focus:border-cvaup-primary focus:ring focus:ring-cvaup-primary/20 outline-none transition';
const textareaClass = inputClass + ' min-h-[80px]';

// Bridge entre el form y el componente UbicacionCascada
const ubicacion = computed({
    get: () => ({
        municipio_id: props.form.municipio_id,
        parroquia_id: props.form.parroquia_id,
        comuna_id: props.form.comuna_id,
        consejo_comunal_id: props.form.consejo_comunal_id,
    }),
    set: (val) => {
        props.form.municipio_id = val.municipio_id;
        props.form.parroquia_id = val.parroquia_id;
        props.form.comuna_id = val.comuna_id;
        props.form.consejo_comunal_id = val.consejo_comunal_id;
    },
});

const ubicacionErrors = computed(() => ({
    municipio_id: props.form.errors?.municipio_id,
    parroquia_id: props.form.errors?.parroquia_id,
    comuna_id: props.form.errors?.comuna_id,
    consejo_comunal_id: props.form.errors?.consejo_comunal_id,
}));

// ── BLOQUE 5: comunas y CCs adicionales ──────────────────────────
//
// La principal (form.comuna_id, form.consejo_comunal_id) NO debe aparecer
// como opción en el multi-select de adicionales — sería redundante.
// Filtramos las listas para excluirla.
const comunasParaMultiselect = computed(() =>
    (props.todasLasComunas || []).filter((c) => c.id !== props.form.comuna_id)
);
const ccsParaMultiselect = computed(() =>
    (props.todosLosConsejosComunales || []).filter((cc) => cc.id !== props.form.consejo_comunal_id)
);

// Cantidades calculadas automáticamente: 1 (principal) + count(adicionales).
// Se muestran como solo-lectura para que el usuario vea el efecto del multi-select.
const cantidadComunasCalculada = computed(() => {
    const principal = props.form.comuna_id ? 1 : 0;
    const adicionales = (props.form.comunas_adicionales_ids || []).length;
    return principal + adicionales;
});

const cantidadCCsCalculada = computed(() => {
    const principal = props.form.consejo_comunal_id ? 1 : 0;
    const adicionales = (props.form.consejos_comunales_adicionales_ids || []).length;
    return principal + adicionales;
});

const submitForm = (esBorrador = false) => {
    props.form.guardar_como_borrador = esBorrador;
    emit('submit');
};
</script>

<template>
    <form @submit.prevent="submitForm(false)" class="space-y-8">
        <!-- ───── Sección 1: Datos Generales ───── -->
        <section>
            <header class="flex items-center gap-2 mb-4 pb-2 border-b border-slate-200">
                <span class="w-7 h-7 rounded-full bg-cvaup-primary text-white flex items-center justify-center text-xs font-bold">1</span>
                <h3 class="text-base font-semibold text-slate-800">Datos Generales</h3>
            </header>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-1">
                <FormField label="Fecha del reporte" required :error="form.errors.fecha" hint="Solo días laborables (lun-vie)">
                    <input v-model="form.fecha" type="date" :class="inputClass" :max="new Date().toISOString().split('T')[0]" />
                </FormField>

                <FormField label="Técnico" required :error="form.errors.tecnico_id">
                    <select v-model="form.tecnico_id" :class="inputClass">
                        <option :value="null">— Selecciona técnico —</option>
                        <option v-for="t in tecnicos" :key="t.id" :value="t.id">
                            {{ t.nombre_apellido }} ({{ t.cedula }})
                        </option>
                    </select>
                </FormField>
            </div>

            <UbicacionCascada
                v-model="ubicacion"
                :municipios="municipios"
                :errors="ubicacionErrors"
            />

            <!-- Lugar específico (ej: nombre de la casa comunal o referencia física).
                 Va inmediatamente después del consejo comunal sede porque conceptualmente
                 es la última pieza de la ubicación PRINCIPAL — el "punto exacto en el mapa". -->
            <FormField label="Lugar específico de la actividad" :error="form.errors.lugar" class="mt-3" hint="Punto exacto donde se realizó la actividad (ej: nombre de la casa comunal, plaza, escuela).">
                <input v-model="form.lugar" type="text" :class="inputClass" placeholder="Ej: Casa Comunal El Progreso" />
            </FormField>

            <!-- ── BLOQUE 5: Otras comunas y CCs atendidos en la misma jornada ── -->
            <!--
                Caso real: el técnico atiende 1 comuna principal + a veces visita
                otras comunas en la misma jornada. Pueden ser de CUALQUIER municipio
                (ej: actividad especial en otro territorio).
                Las cantidades se calculan automáticamente: 1 (principal) + count(adicionales).
            -->
            <div class="mt-4 p-4 bg-slate-50 rounded-lg border border-slate-200 space-y-4">
                <h4 class="text-xs font-semibold text-slate-600 uppercase tracking-wider">
                    Otros territorios atendidos en esta jornada (opcional)
                </h4>

                <FormField
                    label="Otras comunas atendidas"
                    :error="form.errors.comunas_adicionales_ids || form.errors['comunas_adicionales_ids.0']"
                    hint="Pueden ser de cualquier municipio. La comuna principal ya está contada y no aparece en la lista."
                >
                    <MultiSelect
                        v-model="form.comunas_adicionales_ids"
                        :options="comunasParaMultiselect"
                        placeholder="— Sin comunas adicionales —"
                        search-placeholder="Buscar por comuna, parroquia o municipio..."
                        label-field="label"
                    />
                </FormField>

                <FormField
                    label="Otros consejos comunales atendidos"
                    :error="form.errors.consejos_comunales_adicionales_ids || form.errors['consejos_comunales_adicionales_ids.0']"
                    hint="Pueden ser de cualquier parroquia. El CC principal ya está contado y no aparece en la lista."
                >
                    <MultiSelect
                        v-model="form.consejos_comunales_adicionales_ids"
                        :options="ccsParaMultiselect"
                        placeholder="— Sin consejos comunales adicionales —"
                        search-placeholder="Buscar por nombre, comuna o parroquia..."
                        label-field="label"
                    />
                </FormField>

                <!-- Resumen calculado: el sistema cuenta automáticamente -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-slate-200">
                    <div class="flex items-center justify-between bg-white rounded-md px-3 py-2 border border-slate-200">
                        <span class="text-xs text-slate-500">Total comunas atendidas:</span>
                        <span class="text-lg font-bold text-cvaup-primary tabular-nums">{{ cantidadComunasCalculada }}</span>
                    </div>
                    <div class="flex items-center justify-between bg-white rounded-md px-3 py-2 border border-slate-200">
                        <span class="text-xs text-slate-500">Total consejos comunales:</span>
                        <span class="text-lg font-bold text-cvaup-primary tabular-nums">{{ cantidadCCsCalculada }}</span>
                    </div>
                </div>
                <p class="text-[10px] text-slate-400 italic">
                    El sistema cuenta automáticamente: 1 (principal) + cantidad seleccionada.
                </p>
            </div>
        </section>

        <!-- ───── Sección 2: Atención ───── -->
        <section>
            <header class="flex items-center gap-2 mb-4 pb-2 border-b border-slate-200">
                <span class="w-7 h-7 rounded-full bg-cvaup-primary text-white flex items-center justify-center text-xs font-bold">2</span>
                <h3 class="text-base font-semibold text-slate-800">Atención</h3>
            </header>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-1">
                <FormField label="Cantidad de personas atendidas" :error="form.errors.cantidad_personas_atendidas">
                    <input v-model.number="form.cantidad_personas_atendidas" type="number" min="0" :class="inputClass" placeholder="0" />
                </FormField>
                <FormField label="Cantidad de personas a beneficiar" :error="form.errors.cantidad_personas_a_beneficiar">
                    <input v-model.number="form.cantidad_personas_a_beneficiar" type="number" min="0" :class="inputClass" placeholder="0" />
                </FormField>
            </div>
        </section>

        <!-- ───── Sección 3: Actividad — BLOQUE 8 simplificación ───── -->
        <section>
            <header class="flex items-center gap-2 mb-4 pb-2 border-b border-slate-200">
                <span class="w-7 h-7 rounded-full bg-cvaup-primary text-white flex items-center justify-center text-xs font-bold">3</span>
                <h3 class="text-base font-semibold text-slate-800">Actividad realizada</h3>
            </header>

            <div class="space-y-4">
                <FormField label="Tipo de actividad" required :error="form.errors.tipo_actividad">
                    <select v-model="form.tipo_actividad" :class="inputClass">
                        <option value="" disabled>— Selecciona el tipo —</option>
                        <option v-for="t in tiposActividad" :key="t" :value="t">
                            {{ t }}
                        </option>
                    </select>
                </FormField>

                <FormField
                    label="Descripción de la actividad"
                    required
                    :error="form.errors.descripcion_actividad"
                    hint="Detalla qué se hizo, metodología y resultados clave."
                >
                    <textarea
                        v-model="form.descripcion_actividad"
                        :class="textareaClass"
                        rows="5"
                        placeholder="Ej: Capacitación sobre técnicas de compostaje familiar. Se mostró el proceso paso a paso con material orgánico de la comunidad..."
                    ></textarea>
                </FormField>
            </div>
        </section>

        <!-- ───── Sección 4: Constancia Fotográfica ───── -->
        <section>
            <header class="flex items-center gap-2 mb-4 pb-2 border-b border-slate-200">
                <span class="w-7 h-7 rounded-full bg-cvaup-primary text-white flex items-center justify-center text-xs font-bold">4</span>
                <h3 class="text-base font-semibold text-slate-800">Constancia Fotográfica</h3>
                <span class="ml-2 text-xs text-slate-500">(opcional pero requerida para reporte completo)</span>
            </header>

            <MultiFotoUploader
                v-model:nuevas="form.fotos"
                v-model:eliminar="form.fotos_eliminar"
                :existentes="fotosExistentes"
                :error="form.errors.fotos"
            />
        </section>

        <!-- ───── Acciones ───── -->
        <div class="flex items-center justify-between gap-3 pt-4 border-t border-slate-200">
            <Link
                :href="cancelHref"
                class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-md hover:bg-slate-100 transition-colors"
            >
                <Icon name="back" :size="14" class="inline" /> Cancelar
            </Link>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    @click="submitForm(true)"
                    :disabled="form.processing"
                    class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-md hover:bg-slate-100 disabled:opacity-50 transition-colors"
                >
                    Guardar como borrador
                </button>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="px-5 py-2 text-sm font-semibold bg-cvaup-primary text-white rounded-md hover:bg-cvaup-secondary disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                    {{ form.processing ? 'Guardando...' : (isEdit ? 'Guardar cambios' : 'Guardar reporte') }}
                </button>
            </div>
        </div>
    </form>
</template>
