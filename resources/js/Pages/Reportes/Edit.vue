<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Card from '@/Components/Card.vue';
import Form from '@/Pages/Reportes/Partials/Form.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    reporte: { type: Object, required: true },
    tecnicos: { type: Array, default: () => [] },
    municipios: { type: Array, default: () => [] },
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
    cantidad_comunas_atendidas: props.reporte.cantidad_comunas_atendidas,
    cantidad_consejos_comunales_atendidos: props.reporte.cantidad_consejos_comunales_atendidos,
    lugar: props.reporte.lugar ?? '',
    cantidad_personas_atendidas: props.reporte.cantidad_personas_atendidas,
    cantidad_personas_a_beneficiar: props.reporte.cantidad_personas_a_beneficiar,
    titulo_actividad: props.reporte.titulo_actividad ?? '',
    nombre_cientifico_rubro: props.reporte.nombre_cientifico_rubro ?? '',
    fecha_ejecucion: props.reporte.fecha_ejecucion,
    ponencia_responsable: props.reporte.ponencia_responsable ?? '',
    material_apoyo: props.reporte.material_apoyo ?? '',
    organizado_por: props.reporte.organizado_por ?? '',
    aval_de: props.reporte.aval_de ?? '',
    certificacion: props.reporte.certificacion ?? '',
    participantes_acreditados: props.reporte.participantes_acreditados,
    alcance_grupo: props.reporte.alcance_grupo,
    resultado: props.reporte.resultado ?? '',
    resumen_tematico: props.reporte.resumen_tematico ?? '',
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
                    :fotos-existentes="reporte.fotos_existentes"
                    :is-edit="true"
                    :cancel-href="`/reportes/${reporte.id}`"
                    @submit="submit"
                />
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
