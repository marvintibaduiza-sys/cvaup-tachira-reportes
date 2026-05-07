<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    canLogin: { type: Boolean, default: true },
});

const page = usePage();
const isAuthenticated = computed(() => !!page.props.auth?.user);
</script>

<template>
    <Head title="CVAUP Táchira" />

    <div class="min-h-screen flex flex-col items-center justify-center bg-gradient-to-br from-cvaup-secondary via-cvaup-primary to-emerald-700 px-6 py-12">
        <div class="text-center text-white max-w-lg">
            <img
                src="/images/cvaup-logo.png"
                alt="CVAUP Táchira"
                class="w-32 h-32 object-contain drop-shadow-lg mx-auto"
            />
            <h1 class="mt-4 text-3xl font-bold tracking-wide">CVAUP Táchira</h1>
            <p class="mt-2 text-emerald-100 text-lg">Sistema de Reportes Técnicos</p>
            <p class="mt-1 text-emerald-200 text-sm">
                Corporación Venezolana de Agricultura Urbana y Periurbana — Táchira
            </p>

            <Link
                v-if="canLogin"
                :href="isAuthenticated ? '/dashboard' : '/login'"
                class="inline-block mt-8 px-8 py-3 bg-white text-cvaup-primary font-semibold rounded-md shadow-lg hover:bg-slate-50 transition-colors"
            >
                {{ isAuthenticated ? 'Ir al Dashboard' : 'Iniciar sesión' }}
            </Link>
        </div>
    </div>
</template>
