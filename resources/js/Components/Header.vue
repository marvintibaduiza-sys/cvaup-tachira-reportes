<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';

defineProps({
    title: { type: String, default: 'Dashboard' },
});

defineEmits(['toggle-sidebar']);

const page = usePage();
const user = computed(() => page.props.auth?.user);
const userInitial = computed(() => (user.value?.name ?? 'A').charAt(0).toUpperCase());

const logout = () => {
    router.post('/logout');
};
</script>

<template>
    <header class="h-14 bg-white border-b border-slate-200 flex items-center px-6 gap-4 sticky top-0 z-10">
        <button
            type="button"
            @click="$emit('toggle-sidebar')"
            class="text-slate-500 hover:text-slate-700 transition-colors p-1"
            aria-label="Alternar menú"
        >
            <Icon name="menu" :size="24" />
        </button>

        <h1 class="flex-1 text-base font-semibold text-slate-800 truncate">{{ title }}</h1>

        <div class="flex items-center gap-3">
            <span class="hidden sm:inline text-xs text-slate-500">{{ user?.name ?? 'Administrador' }}</span>
            <Link
                href="/perfil"
                class="w-8 h-8 rounded-full bg-cvaup-primary text-white flex items-center justify-center text-sm font-semibold hover:bg-cvaup-secondary transition-colors"
                :title="user?.email ?? ''"
            >
                {{ userInitial }}
            </Link>
            <button
                type="button"
                @click="logout"
                class="flex items-center gap-1 text-slate-500 text-xs px-2.5 py-1.5 rounded hover:bg-red-50 hover:text-red-600 transition-colors"
            >
                <Icon name="logout" :size="18" />
                <span>Salir</span>
            </button>
        </div>
    </header>
</template>
