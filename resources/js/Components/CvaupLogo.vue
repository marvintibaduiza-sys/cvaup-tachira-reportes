<script setup>
defineProps({
    size: { type: [Number, String], default: 36 },
    showText: { type: Boolean, default: false },
});
</script>

<template>
    <div class="flex items-center gap-3">
        <img
            src="/images/cvaup-logo.png"
            alt="CVAUP Táchira"
            :style="{ width: `${size}px`, height: `${size}px` }"
            class="object-contain shrink-0"
        />
        <div v-if="showText" class="leading-tight">
            <div class="text-white font-bold tracking-wider text-[15px]">CVAUP</div>
            <div class="text-slate-400 text-[11px]">Táchira</div>
        </div>
    </div>
</template>
