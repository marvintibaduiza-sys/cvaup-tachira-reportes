<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: { type: String, default: 'primary' }, // primary | secondary | danger | accent | ghost
    size: { type: String, default: 'md' }, // sm | md
    type: { type: String, default: 'button' },
    disabled: { type: Boolean, default: false },
    as: { type: String, default: 'button' }, // 'button' | 'a' (link)
});

const variantClasses = {
    primary: 'bg-cvaup-primary text-white border-cvaup-primary hover:bg-cvaup-secondary',
    secondary: 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100',
    danger: 'bg-white text-red-600 border-red-200 hover:bg-red-50',
    accent: 'bg-cvaup-accent text-slate-900 border-cvaup-accent hover:bg-yellow-600',
    ghost: 'bg-transparent text-slate-600 border-transparent hover:bg-slate-100',
};

const classes = computed(() => [
    'inline-flex items-center justify-center gap-1.5 font-medium border rounded-md transition-colors whitespace-nowrap disabled:opacity-50 disabled:cursor-not-allowed',
    props.size === 'sm' ? 'px-3 py-1.5 text-xs' : 'px-4 py-2 text-sm',
    variantClasses[props.variant] ?? variantClasses.primary,
]);
</script>

<template>
    <button v-if="as === 'button'" :type="type" :class="classes" :disabled="disabled">
        <slot />
    </button>
    <span v-else :class="classes">
        <slot />
    </span>
</template>
