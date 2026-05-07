<script setup>
const props = defineProps({
    modelValue: { type: [Boolean, String], default: false },
    valueOn: { type: [String, Boolean], default: true },
    valueOff: { type: [String, Boolean], default: false },
    labelOn: { type: String, default: 'Activo' },
    labelOff: { type: String, default: 'Inactivo' },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const isOn = () => props.modelValue === props.valueOn || props.modelValue === true;

const toggle = () => {
    if (props.disabled) return;
    emit('update:modelValue', isOn() ? props.valueOff : props.valueOn);
};
</script>

<template>
    <div class="inline-flex items-center gap-3">
        <button
            type="button"
            role="switch"
            :aria-checked="isOn()"
            :disabled="disabled"
            @click="toggle"
            :class="[
                'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus:outline-none focus:ring-2 focus:ring-cvaup-primary focus:ring-offset-2',
                isOn() ? 'bg-cvaup-primary' : 'bg-slate-300',
                disabled ? 'opacity-50 cursor-not-allowed' : '',
            ]"
        >
            <span
                :class="[
                    'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition',
                    isOn() ? 'translate-x-5' : 'translate-x-0',
                ]"
            />
        </button>
        <span class="text-sm text-slate-700">{{ isOn() ? labelOn : labelOff }}</span>
    </div>
</template>
