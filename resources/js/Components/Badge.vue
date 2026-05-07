<script setup>
import { computed } from 'vue';

const props = defineProps({
    type: { type: String, default: 'borrador' }, // completo | incompleto | borrador | activo | inactivo | info
    size: { type: String, default: 'md' },
});

const labels = {
    completo: 'Completo',
    incompleto: 'Incompleto',
    borrador: 'Borrador',
    activo: 'Activo',
    inactivo: 'Inactivo',
    info: 'Info',
};

const classMap = {
    completo: 'bg-green-50 text-green-800 border-green-200',
    incompleto: 'bg-yellow-50 text-yellow-800 border-yellow-200',
    borrador: 'bg-slate-100 text-slate-600 border-slate-300',
    activo: 'bg-green-50 text-green-800 border-green-200',
    inactivo: 'bg-slate-100 text-slate-500 border-slate-300',
    info: 'bg-blue-50 text-blue-800 border-blue-200',
};

const classes = computed(() => [
    'inline-block rounded-full border font-semibold',
    props.size === 'sm' ? 'px-2 py-0.5 text-[11px]' : 'px-2.5 py-1 text-xs',
    classMap[props.type] ?? classMap.borrador,
]);
</script>

<template>
    <span :class="classes">
        <slot>{{ labels[type] ?? type }}</slot>
    </span>
</template>
