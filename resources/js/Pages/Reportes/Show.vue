<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Card from '@/Components/Card.vue';
import Badge from '@/Components/Badge.vue';
import Icon from '@/Components/Icon.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import Lightbox from '@/Components/Lightbox.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    reporte: { type: Object, required: true },
});

const confirmDelete = ref(false);

const performDelete = () => {
    router.delete(route('reportes.destroy', props.reporte.id));
    confirmDelete.value = false;
};

// Lightbox
const lightboxOpen = ref(false);
const lightboxIndex = ref(0);

const openLightbox = (idx) => {
    lightboxIndex.value = idx;
    lightboxOpen.value = true;
};

// Descarga PDF — abre en pestaña nueva (DomPDF entrega application/pdf)
const downloadPdf = () => {
    window.open(`/reportes/${props.reporte.id}/pdf`, '_blank');
};
</script>

<template>
    <Head :title="`Reporte #${reporte.id} — ${reporte.actividad.tipo || 'Reporte'}`" />

    <AuthenticatedLayout :title="`Reporte #${reporte.id}`">
        <div class="max-w-5xl mx-auto space-y-6">
            <!-- Header -->
            <Card padding="lg">
                <div class="flex items-start justify-between flex-wrap gap-4">
                    <div class="min-w-0 flex-1">
                        <h2 class="text-xl font-bold text-slate-800 mb-2">
                            {{ reporte.actividad.tipo || 'Reporte' }}
                        </h2>
                        <div class="flex items-center gap-3 flex-wrap text-sm text-slate-600">
                            <span class="inline-flex items-center gap-1.5">
                                <Icon name="calendar" :size="14" />
                                {{ reporte.fecha }}
                            </span>
                            <Badge :type="reporte.estado_reporte" />
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <Link
                            href="/reportes"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-white text-slate-700 border border-slate-300 rounded-md hover:bg-slate-100 transition-colors"
                        >
                            <Icon name="back" :size="14" /> Volver
                        </Link>
                        <button
                            type="button"
                            @click="downloadPdf"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-cvaup-accent text-slate-900 rounded-md hover:bg-yellow-600 transition-colors"
                            title="Generar y descargar PDF de este reporte"
                        >
                            <Icon name="download" :size="14" /> Exportar PDF
                        </button>
                        <Link
                            :href="`/reportes/${reporte.id}/editar`"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-cvaup-primary text-white rounded-md hover:bg-cvaup-secondary transition-colors"
                        >
                            <Icon name="edit" :size="14" /> Editar
                        </Link>
                        <button
                            type="button"
                            @click="confirmDelete = true"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-red-600 bg-white border border-red-200 rounded-md hover:bg-red-50 transition-colors"
                        >
                            <Icon name="trash" :size="14" /> Eliminar
                        </button>
                    </div>
                </div>
            </Card>

            <!-- Técnico responsable -->
            <Card title="Técnico responsable">
                <Link v-if="reporte.tecnico" :href="`/tecnicos/${reporte.tecnico.id}`" class="flex items-center gap-3 group">
                    <div
                        v-if="reporte.tecnico.foto_url"
                        class="w-12 h-12 rounded-full bg-slate-200 overflow-hidden shrink-0"
                    >
                        <img :src="reporte.tecnico.foto_url" :alt="reporte.tecnico.nombre_apellido" class="w-full h-full object-cover" />
                    </div>
                    <div
                        v-else
                        class="w-12 h-12 rounded-full bg-cvaup-primary text-white flex items-center justify-center font-semibold shrink-0"
                    >
                        {{ reporte.tecnico.nombre_apellido.charAt(0) }}
                    </div>
                    <div>
                        <div class="font-medium text-slate-800 group-hover:text-cvaup-primary transition-colors">{{ reporte.tecnico.nombre_apellido }}</div>
                        <!-- BLOQUE 4: tipo de documento como pill + cédula sin puntos -->
                        <div class="flex items-center gap-1.5 mt-0.5">
                            <span
                                v-if="reporte.tecnico.tipo_documento"
                                class="inline-flex items-center justify-center min-w-[22px] h-4 px-1 rounded bg-cvaup-primary/10 text-cvaup-primary text-[10px] font-bold border border-cvaup-primary/20"
                                :title="`Tipo: ${reporte.tecnico.tipo_documento}`"
                            >
                                {{ reporte.tecnico.tipo_documento }}
                            </span>
                            <span class="text-xs text-slate-500 tabular-nums">{{ reporte.tecnico.cedula }}</span>
                        </div>
                    </div>
                </Link>
            </Card>

            <!-- Ubicación -->
            <Card title="Ubicación">
                <!-- Jerarquía principal -->
                <div class="flex items-center gap-2 text-sm flex-wrap">
                    <span class="bg-slate-100 px-2.5 py-1 rounded text-slate-700">{{ reporte.ubicacion.estado }}</span>
                    <Icon name="chevron-right" :size="14" class="text-slate-400" />
                    <span class="bg-slate-100 px-2.5 py-1 rounded text-slate-700">{{ reporte.ubicacion.municipio || '—' }}</span>
                    <Icon name="chevron-right" :size="14" class="text-slate-400" />
                    <span class="bg-slate-100 px-2.5 py-1 rounded text-slate-700">{{ reporte.ubicacion.parroquia || '—' }}</span>
                    <Icon name="chevron-right" :size="14" class="text-slate-400" />
                    <span class="bg-slate-100 px-2.5 py-1 rounded text-slate-700">{{ reporte.ubicacion.comuna || '—' }}</span>
                    <Icon name="chevron-right" :size="14" class="text-slate-400" />
                    <span class="bg-cvaup-primary/10 text-cvaup-primary border border-cvaup-primary/30 px-2.5 py-1 rounded font-medium">
                        {{ reporte.ubicacion.consejo_comunal || '—' }}
                    </span>
                </div>
                <div v-if="reporte.metricas.lugar" class="mt-3 text-sm text-slate-600">
                    <span class="text-slate-500">Lugar específico:</span> {{ reporte.metricas.lugar }}
                </div>

            </Card>

            <!-- Métricas -->
            <Card title="Métricas y atención">
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <div class="text-2xl font-bold text-slate-800 tabular-nums">{{ reporte.metricas.cantidad_personas_atendidas ?? '—' }}</div>
                        <div class="text-xs text-slate-500 mt-1">Personas atendidas</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-slate-800 tabular-nums">{{ reporte.metricas.cantidad_personas_a_beneficiar ?? '—' }}</div>
                        <div class="text-xs text-slate-500 mt-1">Personas a beneficiar</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-slate-800 tabular-nums">{{ reporte.metricas.cantidad_comunas_atendidas ?? '—' }}</div>
                        <div class="text-xs text-slate-500 mt-1">Comunas atendidas</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-slate-800 tabular-nums">{{ reporte.metricas.cantidad_consejos_comunales_atendidos ?? '—' }}</div>
                        <div class="text-xs text-slate-500 mt-1">Consejos atendidos</div>
                    </div>
                </div>
            </Card>

            <!-- Actividad realizada — BLOQUE 8 simplificación -->
            <Card title="Actividad realizada">
                <div class="space-y-3">
                    <div>
                        <div class="text-xs uppercase tracking-wider text-slate-400 mb-1">Tipo de actividad</div>
                        <span
                            class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-cvaup-primary/10 text-cvaup-primary border border-cvaup-primary/20"
                        >
                            {{ reporte.actividad.tipo || '—' }}
                        </span>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wider text-slate-400 mb-1">Descripción</div>
                        <div class="text-sm text-slate-700 whitespace-pre-wrap">
                            {{ reporte.actividad.descripcion || '—' }}
                        </div>
                    </div>
                </div>
            </Card>

            <!-- Galería con lightbox -->
            <Card title="Constancia fotográfica">
                <EmptyState v-if="reporte.fotos.length === 0" icon="image" message="Este reporte no tiene fotos." />
                <div v-else class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <button
                        v-for="(foto, idx) in reporte.fotos"
                        :key="foto.id"
                        type="button"
                        @click="openLightbox(idx)"
                        class="group relative block aspect-square rounded-md overflow-hidden border border-slate-200 hover:border-cvaup-primary transition-colors cursor-zoom-in"
                    >
                        <img :src="foto.url" :alt="foto.nombre_original" class="w-full h-full object-cover" />
                        <div class="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-colors flex items-center justify-center">
                            <Icon name="eye" :size="20" class="text-white opacity-0 group-hover:opacity-100 transition-opacity" />
                        </div>
                    </button>
                </div>
                <p v-if="reporte.fotos.length > 0" class="text-[11px] text-slate-400 mt-3">
                    Click en cualquier foto para ampliar. Usa ← → para navegar.
                </p>
            </Card>

            <!-- Metadata -->
            <div class="text-xs text-slate-400 text-center pt-2">
                Creado: {{ reporte.created_at }} · Última modificación: {{ reporte.updated_at }}
            </div>
        </div>

        <ConfirmDialog
            :show="confirmDelete"
            title="Eliminar reporte"
            :message="`¿Estás seguro de eliminar el reporte de tipo '${reporte.actividad.tipo ?? '—'}' del ${reporte.fecha}?`"
            confirm-label="Sí, eliminar"
            variant="danger"
            @confirm="performDelete"
            @cancel="confirmDelete = false"
        />

        <Lightbox
            v-model:show="lightboxOpen"
            :images="reporte.fotos"
            :initial-index="lightboxIndex"
        />
    </AuthenticatedLayout>
</template>
