<script setup>
defineProps({
    label: { type: String, default: '' },
    required: { type: Boolean, default: false },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    for: { type: String, default: '' },
});
</script>

<template>
    <div class="mb-4">
        <label v-if="label" :for="$props.for" class="block text-sm font-medium text-slate-700 mb-1.5">
            {{ label }}
            <span v-if="required" class="text-red-500">*</span>
        </label>
        <slot />
        <p v-if="error" class="text-xs text-red-600 mt-1">{{ error }}</p>
        <p v-else-if="hint" class="text-xs text-slate-400 mt-1">{{ hint }}</p>
    </div>
</template>
