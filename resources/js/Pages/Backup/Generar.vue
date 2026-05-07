<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Card from '@/Components/Card.vue';
import Icon from '@/Components/Icon.vue';

defineProps({
    estadisticas: { type: Object, required: true },
});

const form = useForm({});

const generar = () => {
    form.post('/backups/generar', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Generar Respaldo" />

    <AuthenticatedLayout title="Generar Nuevo Respaldo">
        <div class="max-w-2xl mx-auto space-y-4">
            <!-- Banner explicativo -->
            <Card title="Generar respaldo del sistema">
                <div class="space-y-4 text-sm">
                    <div class="bg-cvaup-primary/5 border border-cvaup-primary/20 rounded-md p-4">
                        <p class="font-semibold text-cvaup-primary mb-2">¿Qué incluye el respaldo?</p>
                        <ul class="space-y-1.5 text-slate-700">
                            <li class="flex items-start gap-2">
                                <Icon name="check" :size="14" class="text-cvaup-primary mt-1 shrink-0" />
                                <span><strong>Base de datos completa</strong> (cvaup_tachira) — todas las tablas:
                                    técnicos, reportes, ubicaciones, fotos_reporte, etc.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <Icon name="check" :size="14" class="text-cvaup-primary mt-1 shrink-0" />
                                <span><strong>Fotos de los reportes</strong> (storage/app/public/fotos/) —
                                    todas las imágenes WebP comprimidas que respaldan las actividades de campo.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <Icon name="check" :size="14" class="text-cvaup-primary mt-1 shrink-0" />
                                <span><strong>Archivo de configuración</strong> (.env) — credenciales y configuración
                                    del entorno (cifrado en el .zip).</span>
                            </li>
                        </ul>
                    </div>

                    <div class="text-xs text-slate-500 leading-relaxed">
                        <p class="mb-2">
                            El respaldo se genera como un archivo <strong>.zip</strong> comprimido y se guarda
                            en <code class="bg-slate-100 px-1 rounded">storage/app/private/cvaup-tachira/</code>.
                        </p>
                        <p>
                            <strong>Tiempo estimado:</strong> 30 segundos a 2 minutos según volumen de fotos almacenadas.
                            La operación es <strong>síncrona</strong>: NO cierres esta página hasta que termine.
                        </p>
                    </div>

                    <div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-md p-3 text-xs flex items-start gap-2">
                        <Icon name="warning" :size="16" class="text-amber-700 mt-0.5 shrink-0" />
                        <p>
                            Hay actualmente <strong>{{ estadisticas.total_actual }}</strong> respaldo(s) almacenado(s).
                            Si al generar este respaldo se supera el límite de
                            <strong>{{ estadisticas.max_retenidos }}</strong>,
                            el más antiguo se eliminará automáticamente para liberar espacio.
                        </p>
                    </div>
                </div>
            </Card>

            <!-- Acciones -->
            <div class="flex items-center justify-end gap-3">
                <Link
                    href="/backups"
                    class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-md hover:bg-slate-100 transition-colors"
                >
                    Cancelar
                </Link>
                <button
                    type="button"
                    @click="generar"
                    :disabled="form.processing"
                    class="px-5 py-2 text-sm font-semibold bg-cvaup-primary text-white rounded-md hover:bg-cvaup-secondary disabled:opacity-50 disabled:cursor-not-allowed transition-colors inline-flex items-center gap-2"
                >
                    <Icon name="backup" :size="16" />
                    {{ form.processing ? 'Generando respaldo...' : 'Generar respaldo ahora' }}
                </button>
            </div>

            <p v-if="form.processing" class="text-center text-xs text-slate-500 italic">
                Espera mientras el sistema dump la BD y comprime las fotos. No cierres ni recargues la página.
            </p>
        </div>
    </AuthenticatedLayout>
</template>
