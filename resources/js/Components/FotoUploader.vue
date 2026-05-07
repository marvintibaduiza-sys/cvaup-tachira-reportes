<script setup>
import { ref, computed } from 'vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    modelValue: { type: [File, null], default: null }, // archivo seleccionado
    currentUrl: { type: String, default: null },        // URL existente (en edición)
    accept: { type: String, default: 'image/jpeg,image/png,image/webp' },
    maxSizeMB: { type: Number, default: 5 },
    label: { type: String, default: 'Foto' },
    error: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'remove-current']);

const dragOver = ref(false);
const localPreview = ref(null);
const localError = ref('');
const fileInput = ref(null);

const previewUrl = computed(() => localPreview.value || props.currentUrl);
const hasPreview = computed(() => !!previewUrl.value);

const handleFile = (file) => {
    localError.value = '';

    if (!file) return;

    if (!props.accept.split(',').includes(file.type)) {
        localError.value = 'Formato no permitido. Usa JPG, PNG o WebP.';
        return;
    }

    const sizeMB = file.size / (1024 * 1024);
    if (sizeMB > props.maxSizeMB) {
        localError.value = `La imagen pesa ${sizeMB.toFixed(1)} MB. Máximo permitido: ${props.maxSizeMB} MB.`;
        return;
    }

    localPreview.value = URL.createObjectURL(file);
    emit('update:modelValue', file);
};

const onChange = (e) => handleFile(e.target.files?.[0]);

const onDrop = (e) => {
    e.preventDefault();
    dragOver.value = false;
    handleFile(e.dataTransfer.files?.[0]);
};

const onDragOver = (e) => {
    e.preventDefault();
    dragOver.value = true;
};

const onDragLeave = () => {
    dragOver.value = false;
};

const triggerFileInput = () => fileInput.value?.click();

const removeFile = () => {
    localPreview.value = null;
    if (fileInput.value) fileInput.value.value = '';
    emit('update:modelValue', null);
    emit('remove-current'); // notifica al padre que el usuario quiere eliminar foto existente
};
</script>

<template>
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ label }}</label>

        <div
            v-if="!hasPreview"
            @drop="onDrop"
            @dragover="onDragOver"
            @dragleave="onDragLeave"
            @click="triggerFileInput"
            :class="[
                'border-2 border-dashed rounded-lg p-6 text-center cursor-pointer transition-colors',
                dragOver ? 'border-cvaup-primary bg-green-50' : 'border-slate-300 hover:border-cvaup-primary hover:bg-slate-50',
            ]"
        >
            <Icon name="upload" :size="32" class="text-slate-400 mx-auto" />
            <p class="text-sm text-slate-600 mt-2">
                <span class="font-medium text-cvaup-primary">Haz clic para subir</span> o arrastra una imagen
            </p>
            <p class="text-[11px] text-slate-400 mt-1">JPG, PNG o WebP (máx. {{ maxSizeMB }} MB)</p>
        </div>

        <div v-else class="flex items-start gap-4 p-4 border border-slate-200 rounded-lg bg-slate-50">
            <img :src="previewUrl" alt="Preview" class="w-24 h-24 object-cover rounded-md border border-slate-200" />
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-slate-800">
                    {{ localPreview ? 'Imagen nueva seleccionada' : 'Imagen actual' }}
                </p>
                <p class="text-xs text-slate-500 mt-1">Se comprimirá automáticamente al guardar (WebP, máx 1200px).</p>
                <div class="flex gap-2 mt-2">
                    <button
                        type="button"
                        @click="triggerFileInput"
                        class="text-xs text-cvaup-primary hover:underline"
                    >
                        Reemplazar
                    </button>
                    <button
                        type="button"
                        @click="removeFile"
                        class="text-xs text-red-600 hover:underline"
                    >
                        Eliminar
                    </button>
                </div>
            </div>
        </div>

        <input
            ref="fileInput"
            type="file"
            :accept="accept"
            @change="onChange"
            class="hidden"
        />

        <p v-if="error || localError" class="text-xs text-red-600 mt-1">{{ error || localError }}</p>
    </div>
</template>
