<script setup>
import Icon from '@/Components/Icon.vue';

defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    icon: { type: String, default: 'chart-bar' },
    color: { type: String, default: '#2E7D32' }, // hex
});
</script>

<template>
    <div class="bg-white rounded-lg border border-slate-200 p-5 flex items-center gap-4 shadow-sm">
        <div
            class="w-12 h-12 rounded-lg flex items-center justify-center shrink-0"
            :style="{ backgroundColor: color + '20', color: color }"
        >
            <Icon :name="icon" :size="22" />
        </div>
        <div class="min-w-0">
            <div class="text-2xl font-bold text-slate-800 leading-tight tabular-nums">{{ value }}</div>
            <div class="text-xs text-slate-500 mt-0.5">{{ label }}</div>
        </div>
    </div>
</template>
