<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Card from '@/Components/Card.vue';
import Form from '@/Pages/Reportes/Partials/Form.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    tecnicos: { type: Array, default: () => [] },
    municipios: { type: Array, default: () => [] },
    tiposActividad: { type: Array, default: () => [] },
});

const today = new Date().toISOString().split('T')[0];

const form = useForm({
    guardar_como_borrador: false,
    tecnico_id: null,
    fecha: today,
    municipio_id: null,
    parroquia_id: null,
    comuna_id: null,
    consejo_comunal_id: null,
    lugar: '',
    cantidad_personas_atendidas: null,
    cantidad_personas_a_beneficiar: null,
    // BLOQUE 9: cantidades manuales (default 1 = solo el principal)
    cantidad_comunas_atendidas: 1,
    cantidad_consejos_comunales_atendidos: 1,
    // BLOQUE 8: actividad simplificada
    tipo_actividad: '',
    descripcion_actividad: '',
    fotos: [],
    fotos_eliminar: [],
});

const submit = () => {
    form.post(route('reportes.store'), {
        forceFormData: true,
    });
};
</script>

<template>
    <Head title="Nuevo Reporte" />

    <AuthenticatedLayout title="Nuevo Reporte">
        <div class="max-w-5xl mx-auto">
            <Card title="Datos del nuevo reporte">
                <Form
                    :form="form"
                    :tecnicos="tecnicos"
                    :municipios="municipios"
                    :tipos-actividad="tiposActividad"
                    @submit="submit"
                />
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
