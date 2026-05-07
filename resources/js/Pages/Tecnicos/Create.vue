<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Card from '@/Components/Card.vue';
import Form from '@/Pages/Tecnicos/Partials/Form.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    municipios: { type: Array, default: () => [] },
});

const form = useForm({
    nombre_apellido: '',
    cedula: '',
    telefono: '',
    especialidad: '',
    estado: 'activo',
    municipio_ids: [],
    foto: null,
});

const submit = () => {
    form.post(route('tecnicos.store'), {
        forceFormData: true, // necesario por la foto
    });
};
</script>

<template>
    <Head title="Registrar Técnico" />

    <AuthenticatedLayout title="Registrar Técnico">
        <div class="max-w-3xl mx-auto">
            <Card title="Datos del nuevo técnico">
                <Form
                    :form="form"
                    :municipios="municipios"
                    submit-label="Registrar técnico"
                    @submit="submit"
                />
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
