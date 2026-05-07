<script setup>
import { ref, computed } from 'vue';
import Icon from '@/Components/Icon.vue';

/**
 * MultiFotoUploader — gestiona hasta 3 fotos por reporte.
 *
 * Maneja DOS conjuntos:
 *  - existentes: ya guardadas en BD ({id, url, nombre_original}). Se pueden marcar para eliminar.
 *  - nuevas: archivos a subir (File[]).
 *
 * Total efectivo = existentes (no eliminadas) + nuevas. Máx 3.
 *
 * v-model:nuevas → File[]
 * v-model:eliminar → number[] (IDs de existentes a eliminar)
 *
 * Props:
 *  - existentes: Array<{id, url, nombre_original, orden}>
 */
const props = defineProps({
    nuevas: { type: Array, default: () => [] },          // v-model archivos nuevos
    eliminar: { type: Array, default: () => [] },        // v-model IDs de existentes a borrar
    existentes: { type: Array, default: () => [] },      // fotos ya en BD
    maxTotal: { type: Number, default: 3 },
    maxSizeMB: { type: Number, default: 5 },
    error: { type: String, default: '' },
});

const emit = defineEmits(['update:nuevas', 'update:eliminar']);

const dragOver = ref(false);
const localError = ref('');
const fileInput = ref(null);

const existentesActivas = computed(() =>
    props.existentes.filter((e) => !props.eliminar.includes(e.id)),
);

const totalEfectivo = computed(() => existentesActivas.value.length + props.nuevas.length);
const disponibles = computed(() => Math.max(0, props.maxTotal - totalEfectivo.value));

const handleFiles = (fileList) => {
    localError.value = '';
    if (!fileList || fileList.length === 0) return;

    const archivos = Array.from(fileList);
    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    const nuevosValidos = [];

    for (const file of archivos) {
        if (nuevosValidos.length >= disponibles.value) {
            localError.value = `Solo puedes agregar ${disponibles.value} foto(s) más (máximo ${props.maxTotal} en total).`;
            break;
        }
        if (!allowedTypes.includes(file.type)) {
            localError.value = `${file.name}: formato no permitido. Usa JPG, PNG o WebP.`;
            continue;
        }
        if (file.size > props.maxSizeMB * 1024 * 1024) {
            localError.value = `${file.name}: pesa más de ${props.maxSizeMB} MB.`;
            continue;
        }
        nuevosValidos.push(file);
    }

    if (nuevosValidos.length > 0) {
        emit('update:nuevas', [...props.nuevas, ...nuevosValidos]);
    }
};

const onChange = (e) => handleFiles(e.target.files);

const onDrop = (e) => {
    e.preventDefault();
    dragOver.value = false;
    handleFiles(e.dataTransfer.files);
};

const onDragOver = (e) => {
    e.preventDefault();
    dragOver.value = true;
};

const onDragLeave = () => {
    dragOver.value = false;
};

const triggerFileInput = () => fileInput.value?.click();

const removeNueva = (idx) => {
    const newList = [...props.nuevas];
    newList.splice(idx, 1);
    emit('update:nuevas', newList);
};

const marcarExistenteEliminar = (id) => {
    emit('update:eliminar', [...props.eliminar, id]);
};

const desmarcarExistenteEliminar = (id) => {
    emit('update:eliminar', props.eliminar.filter((x) => x !== id));
};

const previewNueva = (file) => URL.createObjectURL(file);
</script>

<template>
    <div>
        <!-- Contador -->
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs text-slate-500">
                {{ totalEfectivo }} de {{ maxTotal }} fotos
            </span>
            <span v-if="totalEfectivo === maxTotal" class="text-xs text-amber-600 font-medium">
                Máximo alcanzado
            </span>
        </div>

        <!-- Grid de fotos -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3">
            <!-- Fotos existentes activas -->
            <div
                v-for="foto in existentesActivas"
                :key="`exist-${foto.id}`"
                class="relative group border border-slate-200 rounded-md overflow-hidden aspect-square bg-slate-50"
            >
                <img :src="foto.url" :alt="foto.nombre_original" class="w-full h-full object-cover" />
                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/40 transition-colors flex items-end p-2">
                    <button
                        type="button"
                        @click="marcarExistenteEliminar(foto.id)"
                        class="opacity-0 group-hover:opacity-100 transition-opacity text-xs bg-red-600 text-white px-2 py-1 rounded"
                    >
                        <Icon name="trash" :size="12" class="inline" />
                        Eliminar
                    </button>
                </div>
                <span class="absolute top-1 left-1 text-[10px] bg-black/60 text-white px-1.5 py-0.5 rounded">
                    Existente #{{ foto.orden }}
                </span>
            </div>

            <!-- Fotos existentes marcadas para eliminar (con opción de desmarcar) -->
            <div
                v-for="id in eliminar"
                :key="`del-${id}`"
                class="relative border-2 border-red-300 border-dashed rounded-md aspect-square bg-red-50 flex flex-col items-center justify-center p-3 text-center"
            >
                <Icon name="trash" :size="20" class="text-red-500" />
                <p class="text-xs text-red-700 mt-2 font-medium">Marcada para eliminar</p>
                <button
                    type="button"
                    @click="desmarcarExistenteEliminar(id)"
                    class="text-[11px] text-red-700 underline mt-1"
                >
                    Deshacer
                </button>
            </div>

            <!-- Fotos nuevas -->
            <div
                v-for="(file, idx) in nuevas"
                :key="`new-${idx}`"
                class="relative group border-2 border-green-300 rounded-md overflow-hidden aspect-square bg-green-50"
            >
                <img :src="previewNueva(file)" :alt="file.name" class="w-full h-full object-cover" />
                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/40 transition-colors flex items-end p-2">
                    <button
                        type="button"
                        @click="removeNueva(idx)"
                        class="opacity-0 group-hover:opacity-100 transition-opacity text-xs bg-red-600 text-white px-2 py-1 rounded"
                    >
                        <Icon name="close" :size="12" class="inline" />
                        Quitar
                    </button>
                </div>
                <span class="absolute top-1 left-1 text-[10px] bg-green-700 text-white px-1.5 py-0.5 rounded">
                    Nueva
                </span>
            </div>

            <!-- Slot de subir más -->
            <div
                v-if="disponibles > 0"
                @drop="onDrop"
                @dragover="onDragOver"
                @dragleave="onDragLeave"
                @click="triggerFileInput"
                :class="[
                    'border-2 border-dashed rounded-md aspect-square flex flex-col items-center justify-center cursor-pointer transition-colors p-3 text-center',
                    dragOver
                        ? 'border-cvaup-primary bg-green-50'
                        : 'border-slate-300 hover:border-cvaup-primary hover:bg-slate-50',
                ]"
            >
                <Icon name="upload" :size="24" class="text-slate-400" />
                <p class="text-xs text-slate-600 mt-2">
                    <span class="font-medium text-cvaup-primary">Subir foto</span>
                </p>
                <p class="text-[10px] text-slate-400 mt-0.5">JPG/PNG/WebP · máx {{ maxSizeMB }}MB</p>
            </div>
        </div>

        <input
            ref="fileInput"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            multiple
            @change="onChange"
            class="hidden"
        />

        <p v-if="error || localError" class="text-xs text-red-600 mt-1">{{ error || localError }}</p>

        <p class="text-[11px] text-slate-400 mt-2">
            Las imágenes se comprimen automáticamente al guardar (WebP, máx 1200px ancho, calidad 80).
        </p>
    </div>
</template>
