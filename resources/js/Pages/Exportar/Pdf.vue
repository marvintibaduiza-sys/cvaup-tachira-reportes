<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Card from '@/Components/Card.vue';
import Icon from '@/Components/Icon.vue';
import FormFiltros from '@/Pages/Exportar/Partials/FormFiltros.vue';

defineProps({
    lookups: { type: Object, required: true },
});

const filters = ref({
    desde: '',
    hasta: '',
    tecnico_id: null,
    municipio_id: null,
    parroquia_id: null,
    estado_reporte: 'todos',
    // Opcionales para Fase 12.2 (comparativa)
    comparar: false,
    desde_comparacion: '',
    hasta_comparacion: '',
    // Opcional para Fase 12.3 (resumen gráfico)
    incluir_graficas: false,
});

const submitting = ref(false);

/**
 * Submit POST con form HTML real (no Inertia) para que el navegador maneje
 * la descarga del binario PDF correctamente.
 */
const submit = () => {
    submitting.value = true;

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/exportar/pdf';
    form.target = '_blank'; // descarga sin perder la página de filtros

    // CSRF
    const csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = '_token';
    csrf.value = document.querySelector('meta[name="csrf-token"]')?.content
        || document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1]
        || '';
    form.appendChild(csrf);

    // Filtros como inputs hidden
    Object.entries(filters.value).forEach(([k, v]) => {
        if (v === null || v === '') return;
        // Boolean false NO se envía (Laravel lo trataría como string "false")
        if (v === false) return;
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = k;
        // Boolean true → "1" para que Laravel boolean() lo lea correcto
        input.value = v === true ? '1' : v;
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);

    // Restablecer estado después de un breve delay
    setTimeout(() => (submitting.value = false), 1500);
};
</script>

<template>
    <Head title="Exportar PDF" />

    <AuthenticatedLayout title="Exportar Reportes a PDF">
        <div class="max-w-3xl mx-auto space-y-4">
            <!-- Info contextual -->
            <div class="bg-green-50 border border-green-200 text-green-900 rounded-md p-4 text-sm flex items-start gap-3">
                <Icon name="reportes" :size="20" class="text-cvaup-primary mt-0.5 shrink-0" />
                <div>
                    <p class="font-semibold">Exportar listado de reportes a PDF</p>
                    <p class="mt-1 text-xs leading-relaxed">
                        Genera un PDF institucional con todos los reportes que coincidan con los filtros aplicados.
                        Incluye cintillo oficial, tabla de actividades y resumen estadístico al final.
                        Si no aplicas filtros se exporta el listado completo.
                    </p>
                </div>
            </div>

            <Card title="Filtros del reporte">
                <FormFiltros
                    v-model:filters="filters"
                    :lookups="lookups"
                    submit-label="Generar PDF"
                    :submitting="submitting"
                    :show-comparativa="true"
                    @submit="submit"
                />
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
