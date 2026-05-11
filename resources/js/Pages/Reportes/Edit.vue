<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Card from '@/Components/Card.vue';
import Form from '@/Pages/Reportes/Partials/Form.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    reporte: { type: Object, required: true },
    tecnicos: { type: Array, default: () => [] },
    municipios: { type: Array, default: () => [] },
    // BLOQUE 5: catálogos completos para multi-selects
    todasLasComunas: { type: Array, default: () => [] },
    todosLosConsejosComunales: { type: Array, default: () => [] },
    tiposActividad: { type: Array, default: () => [] },
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
    comunas_adicionales_ids: Array.isArray(props.reporte.comunas_adicionales_ids)
        ? [...props.reporte.comunas_adicionales_ids]
        : [],
    consejos_comunales_adicionales_ids: Array.isArray(props.reporte.consejos_comunales_adicionales_ids)
        ? [...props.reporte.consejos_comunales_adicionales_ids]
        : [],
    lugar: props.reporte.lugar ?? '',
    cantidad_personas_atendidas: props.reporte.cantidad_personas_atendidas,
    cantidad_personas_a_beneficiar: props.reporte.cantidad_personas_a_beneficiar,
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
                    :todas-las-comunas="todasLasComunas"
                    :todos-los-consejos-comunales="todosLosConsejosComunales"
                    :tipos-actividad="tiposActividad"
                    :fotos-existentes="reporte.fotos_existentes"
                    :is-edit="true"
                    :cancel-href="`/reportes/${reporte.id}`"
                    @submit="submit"
                />
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
