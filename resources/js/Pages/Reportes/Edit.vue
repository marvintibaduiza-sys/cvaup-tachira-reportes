<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Card from '@/Components/Card.vue';
import Form from '@/Pages/Reportes/Partials/Form.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    reporte: { type: Object, required: true },
    tecnicos: { type: Array, default: () => [] },
    municipios: { type: Array, default: () => [] },
    tipos_actividad: { type: Array, default: () => [] },
});

const form = useForm({
    _method: 'put',
    guardar_como_borrador: false,
    tecnico_id: props.reporte.tecnico_id,
    fecha: props.reporte.fecha,
    municipio_id: props.reporte.municipio_id,
    parroquia_id: props.reporte.parroquia_id,
    comuna_id: props.reporte.comuna_id,
    consejo_comunal_id: props.reporte.consejo_comunal_id,
    lugar: props.reporte.lugar ?? '',
    cantidad_personas_atendidas: props.reporte.cantidad_personas_atendidas,
    cantidad_personas_a_beneficiar: props.reporte.cantidad_personas_a_beneficiar,
    // BLOQUE 9: cantidades manuales (precargadas desde BD, fallback 1)
    cantidad_comunas_atendidas: props.reporte.cantidad_comunas_atendidas ?? 1,
    cantidad_consejos_comunales_atendidos: props.reporte.cantidad_consejos_comunales_atendidos ?? 1,
    // BLOQUE 8
    tipo_actividad: props.reporte.tipo_actividad ?? '',
    descripcion_actividad: props.reporte.descripcion_actividad ?? '',
    fotos: [],
    fotos_eliminar: [],
});

const submit = () => {
    form.post(route('reportes.update', props.reporte.id), {
        forceFormData: true,
    });
};
</script>

<template>
    <Head :title="`Editar reporte #${reporte.id}`" />

    <AuthenticatedLayout :title="`Editar Reporte #${reporte.id}`">
        <div class="max-w-5xl mx-auto">
            <Card :title="`Editando reporte de ${reporte.fecha}`">
                <Form
                    :form="form"
                    :tecnicos="tecnicos"
                    :municipios="municipios"
                    :tipos-actividad="tipos_actividad"
                    :fotos-existentes="reporte.fotos_existentes"
                    :is-edit="true"
                    :cancel-href="`/reportes/${reporte.id}`"
                    @submit="submit"
                />
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
