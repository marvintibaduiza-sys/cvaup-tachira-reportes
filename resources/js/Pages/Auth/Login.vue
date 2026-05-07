<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: { type: Boolean, default: false },
    status: { type: String, default: '' },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Iniciar Sesión" />

        <h2 class="text-xl font-bold text-slate-800 mb-1">Iniciar sesión</h2>
        <p class="text-sm text-slate-500 mb-6">Acceso para administradores autorizados</p>

        <div v-if="status" class="mb-4 text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <label for="email" class="block text-sm font-medium text-slate-700 mb-1.5">Correo electrónico</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    autofocus
                    autocomplete="username"
                    class="w-full px-3 py-2 border border-slate-300 rounded-md focus:border-cvaup-primary focus:ring focus:ring-cvaup-primary/20 outline-none transition"
                    placeholder="admin@cvaup-tachira.com"
                />
                <InputError class="mt-1" :message="form.errors.email" />
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-slate-700 mb-1.5">Contraseña</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    required
                    autocomplete="current-password"
                    class="w-full px-3 py-2 border border-slate-300 rounded-md focus:border-cvaup-primary focus:ring focus:ring-cvaup-primary/20 outline-none transition"
                    placeholder="••••••••"
                />
                <InputError class="mt-1" :message="form.errors.password" />
            </div>

            <label class="inline-flex items-center cursor-pointer select-none">
                <input
                    v-model="form.remember"
                    type="checkbox"
                    class="rounded border-slate-300 text-cvaup-primary focus:ring-cvaup-primary"
                />
                <span class="ml-2 text-sm text-slate-600">Mantener sesión iniciada</span>
            </label>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full py-2.5 bg-cvaup-primary text-white font-semibold rounded-md hover:bg-cvaup-secondary transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
                {{ form.processing ? 'Ingresando...' : 'Iniciar sesión' }}
            </button>
        </form>
    </GuestLayout>
</template>
