<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Icon from '@/Components/Icon.vue';

defineProps({
    mustVerifyEmail: { type: Boolean },
    status: { type: String },
});

// Mostrar info contextual del único administrador del sistema (mono-usuario)
const user = computed(() => usePage().props.auth?.user);
</script>

<template>
    <Head title="Mi cuenta" />

    <AuthenticatedLayout title="Mi cuenta">
        <!--
            Página de cuenta para el ÚNICO administrador del sistema.
            Estructura: identificación + actualización de datos + cambio de contraseña.
            NO incluye eliminación de cuenta (mono-usuario: borrar al admin sería borrar
            el acceso al sistema completo).
        -->
        <div class="max-w-3xl space-y-6">
            <!-- Cabecera contextual -->
            <div class="bg-white border border-slate-200 rounded-lg p-5 shadow-sm">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-full bg-cvaup-primary text-white flex items-center justify-center text-xl font-bold">
                        {{ (user?.name ?? 'A').charAt(0).toUpperCase() }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <h2 class="text-base font-semibold text-slate-800 truncate">
                            {{ user?.name ?? 'Administrador' }}
                        </h2>
                        <p class="text-sm text-slate-500 truncate">{{ user?.email }}</p>
                        <p class="text-[11px] text-cvaup-primary mt-0.5 inline-flex items-center gap-1">
                            <Icon name="shield" :size="12" />
                            <span>Cuenta administradora · acceso total al sistema</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Datos del perfil -->
            <div class="bg-white border border-slate-200 rounded-lg p-5 sm:p-6 shadow-sm">
                <UpdateProfileInformationForm
                    :must-verify-email="mustVerifyEmail"
                    :status="status"
                />
            </div>

            <!-- Cambio de contraseña -->
            <div class="bg-white border border-slate-200 rounded-lg p-5 sm:p-6 shadow-sm">
                <UpdatePasswordForm />
            </div>

            <!-- Aviso institucional sobre seguridad -->
            <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-xs text-amber-900">
                <p class="flex items-start gap-2">
                    <Icon name="alert-triangle" :size="14" class="mt-0.5 shrink-0" />
                    <span>
                        Esta es la única cuenta con acceso a la data institucional.
                        Mantén tu contraseña confidencial y actualízala periódicamente.
                        Si sospechas que fue comprometida, cámbiala de inmediato.
                    </span>
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
