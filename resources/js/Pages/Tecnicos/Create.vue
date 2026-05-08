<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Card from '@/Components/Card.vue';
import Form from '@/Pages/Tecnicos/Partials/Form.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    municipios: { type: Array, default: () => [] },
    tipos_documento: { type: Array, default: () => [] }, // BLOQUE 4.5: array simple ["V","E","J","G","P"]
    especialidades: { type: Array, default: () => [] },
});

const form = useForm({
    nombre: '',
    apellido: '',
    tipo_documento: 'V',  // default Venezolano (lo más común)
    cedula: '',
    telefono: '',
    especialidades: [],   // BLOQUE 4.5: ahora es array (multi-select)
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
                    :tipos-documento="tipos_documento"
                    :especialidades="especialidades"
                    submit-label="Registrar técnico"
                    @submit="submit"
                />
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
