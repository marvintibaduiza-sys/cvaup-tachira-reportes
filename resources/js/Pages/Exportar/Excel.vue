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
});

const submitting = ref(false);

const submit = () => {
    submitting.value = true;

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/exportar/excel';
    form.target = '_blank';

    const csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = '_token';
    csrf.value = document.querySelector('meta[name="csrf-token"]')?.content
        || document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1]
        || '';
    form.appendChild(csrf);

    Object.entries(filters.value).forEach(([k, v]) => {
        if (v === null || v === '') return;
        if (v === false) return;
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = k;
        input.value = v === true ? '1' : v;
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);

    setTimeout(() => (submitting.value = false), 1500);
};
</script>

<template>
    <Head title="Exportar a Excel" />

    <AuthenticatedLayout title="Exportar Reportes a Excel">
        <div class="max-w-3xl mx-auto space-y-4">
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-900 rounded-md p-4 text-sm flex items-start gap-3">
                <Icon name="exportar" :size="20" class="text-emerald-700 mt-0.5 shrink-0" />
                <div>
                    <p class="font-semibold">Exportar a Excel (.xlsx) — formato nativo Microsoft Office</p>
                    <p class="mt-1 text-xs leading-relaxed">
                        Descarga un archivo <strong>.xlsx</strong> con las 28 columnas de los reportes filtrados,
                        listo para abrir en Excel, LibreOffice Calc, Google Sheets o Numbers.
                        Incluye: encabezados con color institucional, anchos auto-ajustados,
                        filtros automáticos, freeze pane, y filas alternadas para legibilidad.
                        Tildes y ñ preservadas correctamente.
                    </p>
                </div>
            </div>

            <Card title="Filtros del archivo Excel">
                <FormFiltros
                    v-model:filters="filters"
                    :lookups="lookups"
                    submit-label="Descargar Excel"
                    :submitting="submitting"
                    @submit="submit"
                />
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
