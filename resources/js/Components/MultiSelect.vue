<script setup>
import { computed, ref } from 'vue';
import Icon from '@/Components/Icon.vue';

/**
 * MultiSelect — selector múltiple con búsqueda y agrupación opcional.
 *
 * Props:
 *  - options: array de { id, label, group? } o { id, nombre, estado? }
 *  - modelValue: array de IDs seleccionados
 *  - placeholder, searchPlaceholder
 *  - groupBy: clave de agrupación (ej: 'group' o 'estado'); null = sin grupos
 *  - labelField: campo a mostrar (default 'label' o 'nombre')
 */
const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Selecciona uno o varios' },
    searchPlaceholder: { type: String, default: 'Buscar…' },
    groupBy: { type: String, default: null },
    labelField: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const search = ref('');

const labelKey = computed(() => props.labelField ?? (props.options[0]?.label !== undefined ? 'label' : 'nombre'));

const filteredOptions = computed(() => {
    if (!search.value.trim()) return props.options;
    const s = search.value.toLowerCase();
    return props.options.filter(
        (o) =>
            o[labelKey.value]?.toLowerCase().includes(s) ||
            (props.groupBy && o[props.groupBy]?.toLowerCase().includes(s)),
    );
});

const grouped = computed(() => {
    if (!props.groupBy) return [{ name: null, items: filteredOptions.value }];
    const groups = {};
    filteredOptions.value.forEach((o) => {
        const k = o[props.groupBy] ?? '— Sin grupo —';
        if (!groups[k]) groups[k] = [];
        groups[k].push(o);
    });
    return Object.entries(groups)
        .sort(([a], [b]) => a.localeCompare(b))
        .map(([name, items]) => ({ name, items }));
});

const selectedOptions = computed(() => props.options.filter((o) => props.modelValue.includes(o.id)));

const isSelected = (id) => props.modelValue.includes(id);

const toggle = (id) => {
    const newValue = isSelected(id) ? props.modelValue.filter((v) => v !== id) : [...props.modelValue, id];
    emit('update:modelValue', newValue);
};

const removeOne = (id) => {
    emit(
        'update:modelValue',
        props.modelValue.filter((v) => v !== id),
    );
};

const clearAll = () => emit('update:modelValue', []);
</script>

<template>
    <div class="relative">
        <!-- Trigger -->
        <button
            type="button"
            @click="open = !open"
            class="w-full min-h-[40px] px-3 py-2 text-left bg-white border border-slate-300 rounded-md hover:border-slate-400 focus:border-cvaup-primary focus:ring focus:ring-cvaup-primary/20 outline-none transition flex items-center gap-2 flex-wrap"
        >
            <template v-if="selectedOptions.length === 0">
                <span class="text-slate-400 text-sm">{{ placeholder }}</span>
            </template>
            <template v-else>
                <span
                    v-for="opt in selectedOptions"
                    :key="opt.id"
                    class="inline-flex items-center gap-1 bg-green-50 text-green-800 border border-green-200 rounded px-2 py-0.5 text-xs"
                >
                    {{ opt[labelKey] }}
                    <span
                        @click.stop="removeOne(opt.id)"
                        class="hover:text-red-600 cursor-pointer text-sm leading-none"
                    >×</span>
                </span>
            </template>
            <span class="ml-auto">
                <Icon name="chevron-down" :size="16" :class="open ? 'rotate-180 transition-transform' : 'transition-transform'" />
            </span>
        </button>

        <!-- Dropdown -->
        <Transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="transform opacity-0 scale-95"
            enter-to-class="transform opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="transform opacity-100 scale-100"
            leave-to-class="transform opacity-0 scale-95"
        >
            <div
                v-if="open"
                class="absolute z-30 mt-1 w-full bg-white border border-slate-200 rounded-md shadow-lg max-h-72 overflow-auto"
            >
                <div class="sticky top-0 bg-white border-b border-slate-100 p-2 flex items-center gap-2">
                    <Icon name="search" :size="14" class="text-slate-400" />
                    <input
                        v-model="search"
                        :placeholder="searchPlaceholder"
                        class="flex-1 text-sm border-none outline-none placeholder-slate-400"
                    />
                    <button
                        v-if="modelValue.length > 0"
                        type="button"
                        @click="clearAll"
                        class="text-[11px] text-red-600 hover:underline"
                    >Limpiar</button>
                </div>

                <div v-if="filteredOptions.length === 0" class="p-4 text-center text-sm text-slate-400">
                    Sin resultados
                </div>

                <template v-for="g in grouped" :key="g.name ?? '_'">
                    <div v-if="g.name" class="px-3 py-1.5 text-[10px] uppercase tracking-wider text-slate-400 bg-slate-50 sticky top-[40px]">
                        {{ g.name }}
                    </div>
                    <button
                        v-for="opt in g.items"
                        :key="opt.id"
                        type="button"
                        @click="toggle(opt.id)"
                        :class="[
                            'w-full text-left px-3 py-2 text-sm flex items-center gap-2 transition-colors',
                            isSelected(opt.id) ? 'bg-green-50 text-green-800' : 'hover:bg-slate-50 text-slate-700',
                        ]"
                    >
                        <span
                            :class="[
                                'w-4 h-4 border rounded flex items-center justify-center shrink-0',
                                isSelected(opt.id) ? 'bg-cvaup-primary border-cvaup-primary' : 'border-slate-300',
                            ]"
                        >
                            <Icon v-if="isSelected(opt.id)" name="check" :size="12" class="text-white" />
                        </span>
                        <span class="flex-1">{{ opt[labelKey] }}</span>
                    </button>
                </template>
            </div>
        </Transition>

        <!-- Click outside to close -->
        <div v-if="open" @click="open = false" class="fixed inset-0 z-20"></div>
    </div>
</template>
