<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Card from '@/Components/Card.vue';
import Form from '@/Pages/Tecnicos/Partials/Form.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    tecnico: { type: Object, required: true },
    municipios: { type: Array, default: () => [] },
});

// useForm con _method=PUT porque el navegador no envía PUT con multipart/form-data
const form = useForm({
    _method: 'put',
    nombre_apellido: props.tecnico.nombre_apellido,
    cedula: props.tecnico.cedula,
    telefono: props.tecnico.telefono ?? '',
    especialidad: props.tecnico.especialidad ?? '',
    estado: props.tecnico.estado,
    municipio_ids: [...props.tecnico.municipio_ids],
    foto: null,
    foto_url: props.tecnico.foto_url, // solo para mostrar en FotoUploader; no se envía
    eliminar_foto: false,
});

const submit = () => {
    form.post(route('tecnicos.update', props.tecnico.id), {
        forceFormData: true,
    });
};
</script>

<template>
    <Head :title="`Editar — ${tecnico.nombre_apellido}`" />

    <AuthenticatedLayout title="Editar Técnico">
        <div class="max-w-3xl mx-auto">
            <Card :title="`Editar ${tecnico.nombre_apellido}`">
                <Form
                    :form="form"
                    :municipios="municipios"
                    :is-edit="true"
                    :cancel-href="`/tecnicos/${tecnico.id}`"
                    submit-label="Guardar cambios"
                    @submit="submit"
                />
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
