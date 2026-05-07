<script setup>
//
</script>

<template>
    <div class="min-h-screen flex flex-col items-center justify-center bg-gradient-to-br from-cvaup-secondary via-cvaup-primary to-emerald-700 px-6 py-12">
        <div class="mb-6 flex flex-col items-center text-center">
            <img
                src="/images/cvaup-logo.png"
                alt="CVAUP Táchira"
                class="w-24 h-24 object-contain drop-shadow-lg"
            />
            <h1 class="mt-3 text-2xl font-bold text-white tracking-wide">CVAUP Táchira</h1>
            <p class="text-emerald-100 text-sm">Sistema de Reportes Técnicos</p>
        </div>

        <div class="w-full sm:max-w-md bg-white shadow-2xl rounded-xl px-8 py-8">
            <slot />
        </div>

        <p class="mt-6 text-emerald-100 text-xs text-center max-w-md">
            © {{ new Date().getFullYear() }} Corporación Venezolana de Agricultura Urbana y Periurbana — Táchira
        </p>
    </div>
</template>
