<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Card from '@/Components/Card.vue';
import Icon from '@/Components/Icon.vue';
import FormFiltros from '@/Pages/Exportar/Partials/FormFiltros.vue';

/**
 * Generar Reportes — pantalla unificada PDF + Excel.
 *
 * Reemplaza las dos pantallas anteriores (Exportar/Pdf.vue y Exportar/Excel.vue)
 * por UNA sola pantalla donde:
 *  - El admin configura los filtros UNA vez
 *  - Al final hay DOS botones: "Generar PDF" (verde) y "Descargar Excel" (esmeralda)
 *  - Las opciones específicas del PDF (comparativa, incluir gráficas) solo aplican al PDF
 *
 * Razón del cambio: simplificar el sidebar (1 item en vez de 2) y reducir clicks.
 */
defineProps({
    lookups: { type: Object, required: true },
});

// Multi-select: cada filtro de tipo lista es ahora un ARRAY de IDs.
// Vacío = "todos" (sin filtro). Backend valida con whereIn.
const filters = ref({
    desde: '',
    hasta: '',
    tecnico_ids: [],
    municipio_ids: [],
    parroquia_ids: [],
    estados_reporte: [],
    // Específicos del PDF (Excel los ignora):
    comparar: false,
    desde_comparacion: '',
    hasta_comparacion: '',
    incluir_graficas: false,
});

const submitting = ref(false);
const ultimoFormato = ref(null); // 'pdf' o 'excel' — para mostrar feedback

/**
 * Submit POST con form HTML real (no Inertia) para que el navegador maneje
 * la descarga del binario correctamente.
 *
 * @param {string} formato 'pdf' o 'excel'
 */
const submit = (formato) => {
    submitting.value = true;
    ultimoFormato.value = formato;

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = formato === 'pdf' ? '/generar-reportes/pdf' : '/generar-reportes/excel';
    form.target = '_blank';

    // CSRF
    const csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = '_token';
    csrf.value = document.querySelector('meta[name="csrf-token"]')?.content
        || document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1]
        || '';
    form.appendChild(csrf);

    // Filtros como inputs hidden. Maneja:
    //  - strings/numbers/booleans: 1 input simple
    //  - arrays (multi-select): N inputs con `name=key[]` (estilo Laravel)
    Object.entries(filters.value).forEach(([k, v]) => {
        // Skip vacíos: null, '', false, arrays vacíos
        if (v === null || v === '' || v === false) return;
        if (Array.isArray(v) && v.length === 0) return;
        // Para Excel, omitir campos exclusivos de PDF para no enviar ruido
        if (formato === 'excel' && ['comparar', 'desde_comparacion', 'hasta_comparacion', 'incluir_graficas'].includes(k)) return;

        if (Array.isArray(v)) {
            // Multi-select: emitir cada valor como input separado con name=key[]
            v.forEach((item) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `${k}[]`;
                input.value = item;
                form.appendChild(input);
            });
        } else {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = k;
            input.value = v === true ? '1' : v;
            form.appendChild(input);
        }
    });

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);

    // Restablecer estado tras un breve delay
    setTimeout(() => {
        submitting.value = false;
        ultimoFormato.value = null;
    }, 1500);
};
</script>

<template>
    <Head title="Generar Reportes" />

    <AuthenticatedLayout title="Generar Reportes">
        <div class="max-w-3xl mx-auto space-y-4">
            <!-- Info contextual -->
            <div class="bg-cvaup-primary/10 border border-cvaup-primary/30 text-slate-800 rounded-md p-4 text-sm flex items-start gap-3">
                <Icon name="reportes" :size="20" class="text-cvaup-primary mt-0.5 shrink-0" />
                <div>
                    <p class="font-semibold">Generar reportes consolidados</p>
                    <p class="mt-1 text-xs leading-relaxed text-slate-600">
                        Configura los filtros una sola vez y elige el formato de salida al final:
                        <strong class="text-cvaup-primary">PDF</strong> para entrega oficial firmable
                        (con cintillo institucional, opcionalmente con comparativa y gráficas),
                        o <strong class="text-emerald-700">Excel</strong> para análisis posterior
                        (filtros automáticos, freeze pane, tipos nativos).
                        <br><br>
                        Si no aplicas filtros, se exporta el listado completo.
                    </p>
                </div>
            </div>

            <Card title="Filtros">
                <!--
                    El partial FormFiltros emite el evento @submit cuando el admin hace
                    click en su botón interno. Pero para pantalla unificada, el botón
                    interno se oculta (custom-buttons) y los botones reales son los 2 de abajo.
                -->
                <FormFiltros
                    v-model:filters="filters"
                    :lookups="lookups"
                    :show-comparativa="true"
                    :hide-submit="true"
                />

                <!-- Botones de acción ÚNICOS al final -->
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-3 pt-5 mt-4 border-t border-slate-200">
                    <!--
                        Botones con paleta característica de cada formato:
                        - Excel: verde institucional CVAUP (paleta del sistema)
                        - PDF: rojo característico de Adobe PDF (universalmente reconocido)
                    -->

                    <!-- Botón Excel (verde CVAUP) -->
                    <button
                        type="button"
                        @click="submit('excel')"
                        :disabled="submitting"
                        class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-cvaup-primary text-white font-semibold text-sm rounded-md hover:bg-cvaup-secondary transition-colors disabled:opacity-50 disabled:cursor-not-allowed shadow-sm"
                    >
                        <Icon name="exportar" :size="16" />
                        <span>{{ submitting && ultimoFormato === 'excel' ? 'Descargando...' : 'Descargar Excel' }}</span>
                    </button>

                    <!-- Botón PDF (rojo Adobe) -->
                    <button
                        type="button"
                        @click="submit('pdf')"
                        :disabled="submitting"
                        class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-red-600 text-white font-semibold text-sm rounded-md hover:bg-red-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed shadow-sm"
                    >
                        <Icon name="reportes" :size="16" />
                        <span>{{ submitting && ultimoFormato === 'pdf' ? 'Descargando...' : 'Descargar PDF' }}</span>
                    </button>
                </div>

                <p class="text-[11px] text-slate-500 mt-3 text-right">
                    💡 Las opciones de comparativa y gráficas solo aplican al formato PDF.
                </p>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
