<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Sidebar from '@/Components/Sidebar.vue';
import Header from '@/Components/Header.vue';

defineProps({
    title: { type: String, default: '' },
});

const page = usePage();

const sidebarCollapsed = ref(false);
const mobileMenuOpen = ref(false);
const isMobile = ref(false);

const checkMobile = () => {
    isMobile.value = window.innerWidth < 768;
};

onMounted(() => {
    checkMobile();
    window.addEventListener('resize', checkMobile);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', checkMobile);
});

const toggleSidebar = () => {
    if (isMobile.value) {
        mobileMenuOpen.value = !mobileMenuOpen.value;
    } else {
        sidebarCollapsed.value = !sidebarCollapsed.value;
    }
};

// Banner de mensajes flash (Inertia shared props)
const flash = computed(() => page.props.flash ?? {});
</script>

<template>
    <div class="flex min-h-screen bg-cvaup-bg">
        <!-- Overlay móvil -->
        <div
            v-if="mobileMenuOpen && isMobile"
            class="fixed inset-0 bg-black/40 z-30 md:hidden"
            @click="mobileMenuOpen = false"
        />

        <!-- Sidebar (fijo en desktop, deslizable en móvil) -->
        <div
            class="z-40 transition-transform duration-200"
            :class="[
                isMobile ? 'fixed top-0 left-0' : 'relative',
                isMobile && !mobileMenuOpen ? '-translate-x-full' : 'translate-x-0',
            ]"
        >
            <Sidebar :collapsed="sidebarCollapsed && !isMobile" />
        </div>

        <!-- Contenido principal -->
        <div class="flex-1 flex flex-col min-w-0">
            <Header :title="title" @toggle-sidebar="toggleSidebar" />

            <!-- Banner de éxito / error -->
            <div v-if="flash.success || flash.error" class="px-6 pt-4">
                <div
                    v-if="flash.success"
                    class="bg-green-50 border border-green-200 text-green-800 px-4 py-2.5 rounded-md text-sm"
                >
                    {{ flash.success }}
                </div>
                <div
                    v-if="flash.error"
                    class="bg-red-50 border border-red-200 text-red-800 px-4 py-2.5 rounded-md text-sm"
                >
                    {{ flash.error }}
                </div>
            </div>

            <main class="flex-1 p-6 overflow-auto">
                <slot />
            </main>
        </div>
    </div>
</template>
