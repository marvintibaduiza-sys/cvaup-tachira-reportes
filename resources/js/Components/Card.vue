<script setup>
defineProps({
    title: { type: String, default: '' },
    padding: { type: String, default: 'md' }, // none | sm | md | lg
});
</script>

<template>
    <div class="bg-white rounded-lg border border-slate-200 shadow-sm">
        <div v-if="title || $slots.header" class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-800">{{ title }}</h3>
            <slot name="header" />
        </div>
        <div :class="{
            'p-0': padding === 'none',
            'p-3': padding === 'sm',
            'p-5': padding === 'md',
            'p-7': padding === 'lg',
        }">
            <slot />
        </div>
    </div>
</template>
