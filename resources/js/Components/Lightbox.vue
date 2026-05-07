<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import Icon from '@/Components/Icon.vue';

/**
 * Lightbox — visualizador full-screen de imágenes con navegación.
 *
 * Props:
 *  - show: Boolean (v-model:show controla apertura)
 *  - images: Array<{url, nombre_original, orden}>
 *  - initialIndex: Number — qué imagen mostrar al abrir (default 0)
 *
 * Soporta:
 *  - ← → para navegar (teclado)
 *  - Escape para cerrar
 *  - Click fuera de la imagen para cerrar
 *  - Botones de navegación visibles
 *  - Contador "X / N" en esquina
 */
const props = defineProps({
    show: { type: Boolean, default: false },
    images: { type: Array, default: () => [] },
    initialIndex: { type: Number, default: 0 },
});

const emit = defineEmits(['update:show']);

const currentIndex = ref(props.initialIndex);
const imageLoading = ref(true);

const currentImage = computed(() => props.images[currentIndex.value] ?? null);
const hasMultiple = computed(() => props.images.length > 1);

const close = () => emit('update:show', false);

const prev = () => {
    if (!hasMultiple.value) return;
    currentIndex.value = (currentIndex.value - 1 + props.images.length) % props.images.length;
    imageLoading.value = true;
};

const next = () => {
    if (!hasMultiple.value) return;
    currentIndex.value = (currentIndex.value + 1) % props.images.length;
    imageLoading.value = true;
};

const onImageLoad = () => (imageLoading.value = false);

const onKeydown = (e) => {
    if (!props.show) return;
    if (e.key === 'Escape') close();
    else if (e.key === 'ArrowLeft') prev();
    else if (e.key === 'ArrowRight') next();
};

watch(
    () => props.show,
    (val) => {
        document.body.style.overflow = val ? 'hidden' : '';
        if (val) {
            currentIndex.value = props.initialIndex;
            imageLoading.value = true;
        }
    },
);

onMounted(() => document.addEventListener('keydown', onKeydown));
onUnmounted(() => {
    document.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show && currentImage"
                class="fixed inset-0 z-[60] bg-black/95 flex items-center justify-center"
                @click.self="close"
            >
                <!-- Botón cerrar -->
                <button
                    type="button"
                    @click="close"
                    class="absolute top-4 right-4 text-white/70 hover:text-white transition-colors p-2 rounded-full hover:bg-white/10 z-10"
                    aria-label="Cerrar"
                >
                    <Icon name="close" :size="28" />
                </button>

                <!-- Contador -->
                <div
                    v-if="hasMultiple"
                    class="absolute top-4 left-4 text-white/80 text-sm bg-black/50 px-3 py-1.5 rounded-full backdrop-blur-sm z-10"
                >
                    {{ currentIndex + 1 }} / {{ images.length }}
                </div>

                <!-- Anterior -->
                <button
                    v-if="hasMultiple"
                    type="button"
                    @click.stop="prev"
                    class="absolute left-4 top-1/2 -translate-y-1/2 text-white/70 hover:text-white transition-colors p-3 rounded-full bg-black/40 hover:bg-black/60 backdrop-blur-sm z-10"
                    aria-label="Anterior"
                >
                    <Icon name="back" :size="24" />
                </button>

                <!-- Siguiente -->
                <button
                    v-if="hasMultiple"
                    type="button"
                    @click.stop="next"
                    class="absolute right-4 top-1/2 -translate-y-1/2 text-white/70 hover:text-white transition-colors p-3 rounded-full bg-black/40 hover:bg-black/60 backdrop-blur-sm z-10"
                    aria-label="Siguiente"
                >
                    <Icon name="chevron-right" :size="24" />
                </button>

                <!-- Spinner mientras carga -->
                <div v-if="imageLoading" class="absolute text-white/60 text-sm">
                    Cargando imagen...
                </div>

                <!-- Imagen -->
                <img
                    :src="currentImage.url"
                    :alt="currentImage.nombre_original"
                    @load="onImageLoad"
                    @click.stop
                    class="max-w-[90vw] max-h-[85vh] object-contain shadow-2xl rounded transition-opacity"
                    :class="imageLoading ? 'opacity-0' : 'opacity-100'"
                />

                <!-- Caption -->
                <div
                    v-if="currentImage.nombre_original"
                    class="absolute bottom-4 left-1/2 -translate-x-1/2 text-white/80 text-sm bg-black/50 px-4 py-2 rounded-full backdrop-blur-sm max-w-[90vw] truncate"
                >
                    {{ currentImage.nombre_original }}
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
