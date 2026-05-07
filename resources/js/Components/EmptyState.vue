<script setup>
import Icon from '@/Components/Icon.vue';

defineProps({
    icon: { type: String, default: 'image' },
    message: { type: String, default: 'No hay datos para mostrar.' },
});
</script>

<template>
    <div class="text-center py-12 px-6 text-slate-400">
        <div class="mb-3 opacity-50 inline-flex">
            <Icon :name="icon" :size="40" />
        </div>
        <div class="text-sm">
            <slot>{{ message }}</slot>
        </div>
    </div>
</template>
