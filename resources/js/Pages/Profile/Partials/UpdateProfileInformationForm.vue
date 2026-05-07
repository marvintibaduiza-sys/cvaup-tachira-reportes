<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import Icon from '@/Components/Icon.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: { type: Boolean },
    status: { type: String },
});

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
});
</script>

<template>
    <section>
        <header class="border-b border-slate-100 pb-3 mb-5">
            <h2 class="text-base font-semibold text-slate-800 flex items-center gap-2">
                <Icon name="user" :size="16" class="text-cvaup-primary" />
                Información de la cuenta
            </h2>
            <p class="mt-1 text-xs text-slate-500">
                Estos datos se usan para identificar al titular de la cuenta administradora.
            </p>
        </header>

        <form
            @submit.prevent="form.patch(route('profile.update'))"
            class="space-y-5"
        >
            <!-- Nombre -->
            <div>
                <InputLabel for="name" value="Nombre completo" />
                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <!-- Email -->
            <div>
                <InputLabel for="email" value="Correo electrónico" />
                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />
                <InputError class="mt-2" :message="form.errors.email" />
                <p class="mt-1 text-[11px] text-slate-500">
                    Este correo se usa para iniciar sesión en el sistema.
                </p>
            </div>

            <!-- Verificación de email (solo si la app lo exige) -->
            <div v-if="mustVerifyEmail && user.email_verified_at === null" class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded p-3">
                <p>
                    Tu correo aún no está verificado.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="underline font-medium hover:text-amber-900 focus:outline-none"
                    >
                        Reenviar correo de verificación.
                    </Link>
                </p>
                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-cvaup-primary font-medium"
                >
                    Enlace de verificación enviado al correo.
                </div>
            </div>

            <!-- Acción -->
            <div class="flex items-center gap-4 pt-1">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-cvaup-primary text-white rounded-md text-sm font-semibold hover:bg-cvaup-secondary transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <Icon name="save" :size="14" />
                    {{ form.processing ? 'Guardando...' : 'Guardar cambios' }}
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
