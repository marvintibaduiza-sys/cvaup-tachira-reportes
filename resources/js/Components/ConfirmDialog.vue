<script setup>
import { onMounted, onUnmounted, watch } from 'vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: 'Confirmar' },
    message: { type: String, default: '¿Estás seguro?' },
    confirmLabel: { type: String, default: 'Confirmar' },
    cancelLabel: { type: String, default: 'Cancelar' },
    variant: { type: String, default: 'danger' }, // danger | warning | info
});

const emit = defineEmits(['confirm', 'cancel']);

const variantConfig = {
    danger: { iconBg: 'bg-red-100', iconColor: 'text-red-600', icon: 'trash', confirmBtn: 'bg-red-600 hover:bg-red-700' },
    warning: { iconBg: 'bg-orange-100', iconColor: 'text-orange-600', icon: 'warning', confirmBtn: 'bg-orange-600 hover:bg-orange-700' },
    info: { iconBg: 'bg-blue-100', iconColor: 'text-blue-600', icon: 'eye', confirmBtn: 'bg-cvaup-primary hover:bg-cvaup-secondary' },
};

const v = () => variantConfig[props.variant] ?? variantConfig.danger;

const onKeydown = (e) => {
    if (!props.show) return;
    if (e.key === 'Escape') emit('cancel');
    if (e.key === 'Enter') emit('confirm');
};

watch(() => props.show, (val) => {
    document.body.style.overflow = val ? 'hidden' : '';
});

onMounted(() => document.addEventListener('keydown', onKeydown));
onUnmounted(() => {
    document.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition ease-out duration-150"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
                @click="emit('cancel')"
            >
                <div
                    @click.stop
                    class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden"
                >
                    <div class="p-6">
                        <div class="flex items-start gap-4">
                            <div :class="['w-10 h-10 rounded-full flex items-center justify-center shrink-0', v().iconBg]">
                                <Icon :name="v().icon" :size="20" :class="v().iconColor" />
                            </div>
                            <div class="flex-1">
                                <h3 class="text-base font-semibold text-slate-800">{{ title }}</h3>
                                <p class="text-sm text-slate-600 mt-2">{{ message }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-3 border-t border-slate-100 bg-slate-50 flex items-center justify-end gap-2">
                        <button
                            @click="emit('cancel')"
                            class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-md hover:bg-slate-100 transition-colors"
                        >
                            {{ cancelLabel }}
                        </button>
                        <button
                            @click="emit('confirm')"
                            :class="['px-4 py-2 text-sm font-medium text-white rounded-md transition-colors', v().confirmBtn]"
                        >
                            {{ confirmLabel }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
