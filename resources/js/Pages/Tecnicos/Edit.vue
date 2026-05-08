<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Card from '@/Components/Card.vue';
import Form from '@/Pages/Tecnicos/Partials/Form.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    tecnico: { type: Object, required: true },
    municipios: { type: Array, default: () => [] },
    tipos_documento: { type: Array, default: () => [] }, // BLOQUE 4.5: array simple ["V","E","J","G","P"]
    especialidades: { type: Array, default: () => [] },
});

// Para el title y header
const nombreCompleto = computed(() => `${props.tecnico.nombre} ${props.tecnico.apellido}`.trim());

// useForm con _method=PUT porque el navegador no envía PUT con multipart/form-data
const form = useForm({
    _method: 'put',
    nombre: props.tecnico.nombre,
    apellido: props.tecnico.apellido,
    tipo_documento: props.tecnico.tipo_documento,
    cedula: props.tecnico.cedula,
    telefono: props.tecnico.telefono ?? '',
    // BLOQUE 4.5: copia defensiva del array (evita mutación accidental del prop)
    especialidades: Array.isArray(props.tecnico.especialidades) ? [...props.tecnico.especialidades] : [],
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
    <Head :title="`Editar — ${nombreCompleto}`" />

    <AuthenticatedLayout title="Editar Técnico">
        <div class="max-w-3xl mx-auto">
            <Card :title="`Editar ${nombreCompleto}`">
                <Form
                    :form="form"
                    :municipios="municipios"
                    :tipos-documento="tipos_documento"
                    :especialidades="especialidades"
                    :is-edit="true"
                    :cancel-href="`/tecnicos/${tecnico.id}`"
                    submit-label="Guardar cambios"
                    @submit="submit"
                />
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
