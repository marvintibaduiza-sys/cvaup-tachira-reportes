<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Card from '@/Components/Card.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    estadisticas_actuales: { type: Object, required: true },
});

// 3 estados del flujo: 'upload' → 'preview' → 'success'
const step = ref('upload');

// Upload
const fileInput = ref(null);
const dragOver = ref(false);
const uploading = ref(false);
const uploadError = ref('');

// Preview state
const previewData = ref(null); // { token, stats, preview, archivo }
const confirming = ref(false);
const confirmError = ref('');

// Result state
const resultData = ref(null);

const handleFile = async (file) => {
    uploadError.value = '';
    if (!file) return;

    if (!['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'].includes(file.type)
        && !file.name.match(/\.(xlsx|xls)$/i)) {
        uploadError.value = 'Solo se permiten archivos .xlsx o .xls';
        return;
    }

    if (file.size > 10 * 1024 * 1024) {
        uploadError.value = `Archivo demasiado grande (${(file.size / 1024 / 1024).toFixed(1)} MB). Máximo 10 MB.`;
        return;
    }

    uploading.value = true;
    const formData = new FormData();
    formData.append('archivo', file);

    try {
        const { data } = await axios.post('/ubicaciones/importar/preview', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        previewData.value = data;
        step.value = 'preview';
    } catch (e) {
        uploadError.value = e.response?.data?.message || 'Error subiendo archivo. Verifica que sea un Excel válido.';
    } finally {
        uploading.value = false;
    }
};

const onChange = (e) => handleFile(e.target.files?.[0]);
const onDrop = (e) => {
    e.preventDefault();
    dragOver.value = false;
    handleFile(e.dataTransfer.files?.[0]);
};
const onDragOver = (e) => { e.preventDefault(); dragOver.value = true; };
const onDragLeave = () => { dragOver.value = false; };
const triggerInput = () => fileInput.value?.click();

const confirmarImportacion = async () => {
    confirming.value = true;
    confirmError.value = '';
    try {
        const { data } = await axios.post('/ubicaciones/importar/confirmar', {
            token: previewData.value.token,
        });
        resultData.value = data;
        step.value = 'success';
    } catch (e) {
        confirmError.value = e.response?.data?.message || 'Error en la importación.';
    } finally {
        confirming.value = false;
    }
};

const reiniciar = () => {
    step.value = 'upload';
    previewData.value = null;
    resultData.value = null;
    uploadError.value = '';
    confirmError.value = '';
    if (fileInput.value) fileInput.value.value = '';
};

// Computed para totales del preview
const totalCambios = computed(() => {
    if (!previewData.value) return 0;
    const c = previewData.value.stats.creados;
    return (c.estados ?? 0) + (c.municipios ?? 0) + (c.parroquias ?? 0) + (c.comunas ?? 0) + (c.consejos ?? 0);
});

const totalDuplicados = computed(() => {
    if (!previewData.value) return 0;
    const o = previewData.value.stats.omitidos;
    return (o.estados ?? 0) + (o.municipios ?? 0) + (o.parroquias ?? 0) + (o.comunas ?? 0) + (o.consejos ?? 0);
});
</script>

<template>
    <Head title="Importar Ubicaciones desde Excel" />

    <AuthenticatedLayout title="Importar Ubicaciones desde Excel">
        <div class="max-w-5xl mx-auto space-y-4">
            <!-- Stats actuales -->
            <Card>
                <p class="text-sm font-semibold text-slate-700 mb-2">Estado actual de la base de datos</p>
                <div class="flex items-center gap-2 text-xs flex-wrap">
                    <span class="bg-slate-100 rounded px-2 py-1">
                        <strong class="tabular-nums">{{ estadisticas_actuales.estados }}</strong> estados
                    </span>
                    <span class="bg-slate-100 rounded px-2 py-1">
                        <strong class="tabular-nums">{{ estadisticas_actuales.municipios }}</strong> municipios
                    </span>
                    <span class="bg-slate-100 rounded px-2 py-1">
                        <strong class="tabular-nums">{{ estadisticas_actuales.parroquias }}</strong> parroquias
                    </span>
                    <span class="bg-slate-100 rounded px-2 py-1">
                        <strong class="tabular-nums">{{ estadisticas_actuales.comunas }}</strong> comunas
                    </span>
                    <span class="bg-slate-100 rounded px-2 py-1">
                        <strong class="tabular-nums">{{ estadisticas_actuales.consejos }}</strong> consejos
                    </span>
                </div>
            </Card>

            <!-- Formato esperado del Excel -->
            <Card title="Formato del archivo Excel">
                <div class="text-sm text-slate-600 space-y-3">
                    <p>El archivo debe ser <strong>.xlsx</strong> o <strong>.xls</strong> con 5 columnas en este orden exacto:</p>
                    <table class="w-full text-xs border border-slate-200">
                        <thead class="bg-cvaup-primary text-white">
                            <tr>
                                <th class="px-3 py-2 text-left">A</th>
                                <th class="px-3 py-2 text-left">B</th>
                                <th class="px-3 py-2 text-left">C</th>
                                <th class="px-3 py-2 text-left">D</th>
                                <th class="px-3 py-2 text-left">E</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-t border-slate-200 bg-slate-50">
                                <td class="px-3 py-2 font-semibold">Estado</td>
                                <td class="px-3 py-2 font-semibold">Municipio</td>
                                <td class="px-3 py-2 font-semibold">Parroquia</td>
                                <td class="px-3 py-2 font-semibold">Comuna</td>
                                <td class="px-3 py-2 font-semibold">Consejo Comunal</td>
                            </tr>
                            <tr class="border-t border-slate-200 text-slate-500">
                                <td class="px-3 py-2">Táchira</td>
                                <td class="px-3 py-2">San Cristóbal</td>
                                <td class="px-3 py-2">La Concordia</td>
                                <td class="px-3 py-2">Comuna Pueblo Nuevo</td>
                                <td class="px-3 py-2">CC Barrio El Progreso</td>
                            </tr>
                        </tbody>
                    </table>
                    <ul class="text-xs space-y-1 list-disc pl-5">
                        <li>Fila 1 debe ser el encabezado (no se importa)</li>
                        <li>Si una comuna tiene varios CC, repite las columnas anteriores en cada fila</li>
                        <li>Los duplicados se omiten automáticamente</li>
                        <li>Cuidado con tildes y mayúsculas — "ANDRÉS BELLO" y "Andrés Bello" se consideran iguales</li>
                    </ul>
                    <a href="/ubicaciones/plantilla" class="inline-flex items-center gap-2 text-sm text-cvaup-primary hover:underline font-medium">
                        <Icon name="download" :size="14" />
                        Descargar plantilla vacía
                    </a>
                </div>
            </Card>

            <!-- ─── PASO 1: Upload ─── -->
            <Card v-if="step === 'upload'" title="1. Sube tu archivo Excel">
                <div
                    @drop="onDrop"
                    @dragover="onDragOver"
                    @dragleave="onDragLeave"
                    @click="triggerInput"
                    :class="[
                        'border-2 border-dashed rounded-lg p-8 text-center cursor-pointer transition-colors',
                        dragOver ? 'border-cvaup-primary bg-green-50' : 'border-slate-300 hover:border-cvaup-primary hover:bg-slate-50',
                    ]"
                >
                    <Icon name="upload" :size="40" class="text-slate-400 mx-auto" />
                    <p class="text-sm font-medium text-slate-700 mt-3">
                        <span class="text-cvaup-primary">Haz clic para subir</span> o arrastra un archivo Excel
                    </p>
                    <p class="text-xs text-slate-500 mt-1">.xlsx o .xls · máximo 10 MB</p>
                </div>

                <input ref="fileInput" type="file" accept=".xlsx,.xls" @change="onChange" class="hidden" />

                <div v-if="uploading" class="mt-3 text-sm text-cvaup-primary text-center">
                    <Icon name="upload" :size="16" class="inline animate-pulse" />
                    Procesando archivo...
                </div>
                <div v-if="uploadError" class="mt-3 text-sm text-red-600 bg-red-50 border border-red-200 rounded p-3">
                    {{ uploadError }}
                </div>
            </Card>

            <!-- ─── PASO 2: Preview + Confirmar ─── -->
            <Card v-if="step === 'preview'" :title="`2. Revisa y confirma — ${previewData.archivo.nombre}`">
                <!-- Resumen de cambios -->
                <div class="bg-cvaup-primary/5 border border-cvaup-primary/20 rounded p-4 mb-4">
                    <p class="font-semibold text-cvaup-primary mb-3">
                        Resumen de la importación (simulación)
                    </p>
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 text-center">
                        <div v-for="key in ['estados','municipios','parroquias','comunas','consejos']" :key="key">
                            <div class="text-2xl font-bold text-cvaup-primary tabular-nums">
                                +{{ previewData.stats.creados[key] ?? 0 }}
                            </div>
                            <div class="text-xs text-slate-500 capitalize">{{ key }}</div>
                            <div class="text-[10px] text-slate-400 mt-1 tabular-nums">
                                ({{ previewData.stats.omitidos[key] ?? 0 }} duplicados)
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-cvaup-primary/20 text-xs text-slate-600 grid grid-cols-2 gap-2">
                        <div>
                            <strong>Filas procesables:</strong> {{ previewData.stats.filas_procesadas }}
                        </div>
                        <div>
                            <strong>Filas vacías saltadas:</strong> {{ previewData.stats.filas_saltadas }}
                        </div>
                    </div>
                </div>

                <!-- Errores detectados (si los hay) -->
                <div v-if="previewData.stats.errores?.length > 0" class="bg-amber-50 border border-amber-200 rounded p-3 mb-4 text-xs">
                    <p class="font-semibold text-amber-900 mb-2">
                        ⚠️ {{ previewData.stats.errores.length }} advertencia(s) detectada(s):
                    </p>
                    <ul class="text-amber-900 space-y-0.5 max-h-24 overflow-y-auto">
                        <li v-for="(err, i) in previewData.stats.errores.slice(0, 10)" :key="i">• {{ err }}</li>
                    </ul>
                    <p v-if="previewData.stats.errores.length > 10" class="text-amber-700 mt-2">
                        ... y {{ previewData.stats.errores.length - 10 }} más
                    </p>
                </div>

                <!-- Preview tipo Excel -->
                <div class="mb-4">
                    <p class="text-sm font-semibold text-slate-700 mb-2">
                        Vista previa de filas ({{ previewData.preview.mostrando }} de {{ previewData.preview.total_rows_archivo }}):
                    </p>
                    <div class="overflow-x-auto border border-slate-200 rounded">
                        <table class="w-full text-xs">
                            <thead class="bg-slate-100 sticky top-0">
                                <tr>
                                    <th class="px-2 py-2 text-left text-[10px] uppercase tracking-wider text-slate-500 w-12">Fila</th>
                                    <th class="px-2 py-2 text-left text-[10px] uppercase tracking-wider text-slate-500">Estado</th>
                                    <th class="px-2 py-2 text-left text-[10px] uppercase tracking-wider text-slate-500">Municipio</th>
                                    <th class="px-2 py-2 text-left text-[10px] uppercase tracking-wider text-slate-500">Parroquia</th>
                                    <th class="px-2 py-2 text-left text-[10px] uppercase tracking-wider text-slate-500">Comuna</th>
                                    <th class="px-2 py-2 text-left text-[10px] uppercase tracking-wider text-slate-500">CC</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="row in previewData.preview.rows" :key="row.fila" class="hover:bg-slate-50">
                                    <td class="px-2 py-1.5 text-slate-400 tabular-nums">{{ row.fila }}</td>
                                    <td class="px-2 py-1.5 text-slate-700">{{ row.estado }}</td>
                                    <td class="px-2 py-1.5 text-slate-700">{{ row.municipio }}</td>
                                    <td class="px-2 py-1.5 text-slate-700">{{ row.parroquia }}</td>
                                    <td class="px-2 py-1.5 text-slate-700">{{ row.comuna }}</td>
                                    <td class="px-2 py-1.5 text-slate-700">{{ row.consejo }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="confirmError" class="text-sm text-red-600 bg-red-50 border border-red-200 rounded p-3 mb-3">
                    {{ confirmError }}
                </div>

                <!-- Acciones -->
                <div class="flex items-center justify-between gap-3 pt-3 border-t border-slate-200">
                    <button @click="reiniciar" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-md hover:bg-slate-100 transition-colors">
                        Subir otro archivo
                    </button>
                    <button
                        @click="confirmarImportacion"
                        :disabled="confirming"
                        class="px-5 py-2 text-sm font-semibold bg-cvaup-primary text-white rounded-md hover:bg-cvaup-secondary disabled:opacity-50 transition-colors inline-flex items-center gap-2"
                    >
                        <Icon name="check" :size="16" />
                        {{ confirming ? 'Importando...' : `Confirmar e importar ${totalCambios} nuevos` }}
                    </button>
                </div>
            </Card>

            <!-- ─── PASO 3: Resultado ─── -->
            <Card v-if="step === 'success'" title="3. Importación completada ✓">
                <div class="bg-green-50 border border-green-200 rounded p-4 mb-4">
                    <p class="font-semibold text-green-900 mb-3 flex items-center gap-2">
                        <Icon name="check" :size="18" />
                        Datos importados exitosamente
                    </p>
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 text-center">
                        <div v-for="key in ['estados','municipios','parroquias','comunas','consejos']" :key="key">
                            <div class="text-2xl font-bold text-green-700 tabular-nums">
                                +{{ resultData.stats.creados[key] ?? 0 }}
                            </div>
                            <div class="text-xs text-slate-500 capitalize">{{ key }}</div>
                        </div>
                    </div>
                </div>

                <div class="text-sm text-slate-600 space-y-2">
                    <p><strong>Filas procesadas:</strong> {{ resultData.stats.filas_procesadas }}</p>
                    <p><strong>Estado actual de la BD tras la importación:</strong></p>
                    <div class="flex items-center gap-2 text-xs flex-wrap">
                        <span class="bg-slate-100 rounded px-2 py-1">
                            <strong class="tabular-nums">{{ resultData.estadisticas_actuales.municipios }}</strong> municipios
                        </span>
                        <span class="bg-slate-100 rounded px-2 py-1">
                            <strong class="tabular-nums">{{ resultData.estadisticas_actuales.parroquias }}</strong> parroquias
                        </span>
                        <span class="bg-slate-100 rounded px-2 py-1">
                            <strong class="tabular-nums">{{ resultData.estadisticas_actuales.comunas }}</strong> comunas
                        </span>
                        <span class="bg-slate-100 rounded px-2 py-1">
                            <strong class="tabular-nums">{{ resultData.estadisticas_actuales.consejos }}</strong> consejos comunales
                        </span>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 mt-4">
                    <button @click="reiniciar" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-md hover:bg-slate-100 transition-colors">
                        Importar otro archivo
                    </button>
                    <Link
                        href="/ubicaciones"
                        class="px-5 py-2 text-sm font-semibold bg-cvaup-primary text-white rounded-md hover:bg-cvaup-secondary transition-colors inline-flex items-center gap-2"
                    >
                        <Icon name="ubicaciones" :size="16" />
                        Ver el árbol actualizado
                    </Link>
                </div>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
