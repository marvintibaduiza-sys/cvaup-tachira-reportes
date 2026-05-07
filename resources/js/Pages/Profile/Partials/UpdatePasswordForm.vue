<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import Icon from '@/Components/Icon.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

// Mostrar/ocultar campos sensibles (UX cuando el admin teclea mal)
const showCurrent = ref(false);
const showNew = ref(false);

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            // Si falla la nueva, vacíala y vuelve al input principal
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value?.focus();
            }
            // Si la actual estaba mal, vacíala y vuelve a ese input
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value?.focus();
            }
        },
    });
};

/**
 * Indicadores visuales en vivo de los requisitos institucionales.
 * Cada uno se enciende a medida que el admin escribe, sin esperar al submit.
 */
const requisitos = computed(() => {
    const p = form.password;
    return [
        { ok: p.length >= 8, label: 'Mínimo 8 caracteres' },
        { ok: /[a-z]/.test(p) && /[A-Z]/.test(p), label: 'Mayúsculas y minúsculas' },
        { ok: /[0-9]/.test(p), label: 'Al menos un número' },
        { ok: /[^A-Za-z0-9]/.test(p), label: 'Al menos un símbolo (!?.@#$ ...)' },
    ];
});
</script>

<template>
    <section>
        <header class="border-b border-slate-100 pb-3 mb-5">
            <h2 class="text-base font-semibold text-slate-800 flex items-center gap-2">
                <Icon name="key" :size="16" class="text-cvaup-primary" />
                Cambiar contraseña
            </h2>
            <p class="mt-1 text-xs text-slate-500">
                Usa una contraseña larga y única. No la reutilices de otros sistemas.
            </p>
        </header>

        <form @submit.prevent="updatePassword" class="space-y-5">
            <!-- Contraseña actual -->
            <div>
                <InputLabel for="current_password" value="Contraseña actual" />
                <div class="relative mt-1">
                    <TextInput
                        id="current_password"
                        ref="currentPasswordInput"
                        v-model="form.current_password"
                        :type="showCurrent ? 'text' : 'password'"
                        class="block w-full pr-10"
                        autocomplete="current-password"
                    />
                    <button
                        type="button"
                        @click="showCurrent = !showCurrent"
                        class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                        :title="showCurrent ? 'Ocultar' : 'Mostrar'"
                        tabindex="-1"
                    >
                        <Icon :name="showCurrent ? 'eye-off' : 'eye'" :size="16" />
                    </button>
                </div>
                <InputError :message="form.errors.current_password" class="mt-2" />
            </div>

            <!-- Nueva contraseña -->
            <div>
                <InputLabel for="password" value="Nueva contraseña" />
                <div class="relative mt-1">
                    <TextInput
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        :type="showNew ? 'text' : 'password'"
                        class="block w-full pr-10"
                        autocomplete="new-password"
                    />
                    <button
                        type="button"
                        @click="showNew = !showNew"
                        class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                        :title="showNew ? 'Ocultar' : 'Mostrar'"
                        tabindex="-1"
                    >
                        <Icon :name="showNew ? 'eye-off' : 'eye'" :size="16" />
                    </button>
                </div>
                <InputError :message="form.errors.password" class="mt-2" />

                <!-- Checklist de requisitos en vivo -->
                <ul class="mt-3 space-y-1 text-[11px]">
                    <li
                        v-for="(r, i) in requisitos"
                        :key="i"
                        class="flex items-center gap-2"
                        :class="r.ok ? 'text-cvaup-primary' : 'text-slate-400'"
                    >
                        <Icon :name="r.ok ? 'check' : 'circle'" :size="12" />
                        {{ r.label }}
                    </li>
                </ul>
            </div>

            <!-- Confirmación -->
            <div>
                <InputLabel for="password_confirmation" value="Confirmar nueva contraseña" />
                <TextInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    :type="showNew ? 'text' : 'password'"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                />
                <InputError :message="form.errors.password_confirmation" class="mt-2" />
            </div>

            <!-- Acción -->
            <div class="flex items-center gap-4 pt-1">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-cvaup-primary text-white rounded-md text-sm font-semibold hover:bg-cvaup-secondary transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <Icon name="save" :size="14" />
                    {{ form.processing ? 'Guardando...' : 'Actualizar contraseña' }}
                </button>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-sm text-cvaup-primary inline-flex items-center gap-1"
                    >
                        <Icon name="check" :size="14" />
                        Guardado.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
