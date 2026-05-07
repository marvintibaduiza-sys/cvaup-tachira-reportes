<script setup>
import { computed, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import CvaupLogo from '@/Components/CvaupLogo.vue';
import Icon from '@/Components/Icon.vue';

defineProps({
    collapsed: { type: Boolean, default: false },
});

const page = usePage();
const currentRoute = computed(() => page.url);

/**
 * Estructura de menú según specs.
 *
 * `defaultHref` → ruta a la que navega cuando se hace click en el item principal del grupo.
 *   Típicamente la lista del módulo. UX: click en "Técnicos" → va a /tecnicos directo.
 *   Click adicional al chevron → expande sub-opciones.
 */
const menu = [
    { id: 'dashboard', label: 'Dashboard', icon: 'dashboard', href: '/dashboard' },
    {
        id: 'reportes', label: 'Reportes', icon: 'reportes', match: '/reportes',
        defaultHref: '/reportes',
        sub: [
            { label: 'Listado de Reportes', href: '/reportes' },
            { label: 'Nuevo Reporte', href: '/reportes/crear' },
        ],
    },
    {
        id: 'tecnicos', label: 'Técnicos', icon: 'tecnicos', match: '/tecnicos',
        defaultHref: '/tecnicos',
        sub: [
            { label: 'Listado de Técnicos', href: '/tecnicos' },
            { label: 'Registrar Técnico', href: '/tecnicos/crear' },
        ],
    },
    {
        id: 'ubicaciones', label: 'Ubicaciones', icon: 'ubicaciones', match: '/ubicaciones',
        defaultHref: '/ubicaciones',
        sub: [
            { label: 'Gestión de Ubicaciones', href: '/ubicaciones' },
            { label: 'Importar Excel', href: '/ubicaciones/importar' },
        ],
    },
    {
        id: 'exportar', label: 'Exportar', icon: 'exportar', match: '/exportar',
        defaultHref: '/exportar/pdf',
        sub: [
            { label: 'Generar PDF', href: '/exportar/pdf' },
            { label: 'Descargar Excel', href: '/exportar/excel' },
        ],
    },
    {
        id: 'backup', label: 'Respaldo', icon: 'backup', match: '/backups',
        defaultHref: '/backups',
        sub: [
            { label: 'Historial de Backups', href: '/backups' },
            { label: 'Generar Backup', href: '/backups/generar' },
        ],
    },
    {
        id: 'config', label: 'Configuración', icon: 'config', match: '/perfil',
        defaultHref: '/perfil',
        sub: [
            { label: 'Mi Perfil', href: '/perfil' },
        ],
    },
];

const openMenus = ref({});

const isActive = (item) => {
    const url = currentRoute.value;
    if (item.href && url === item.href) return true;
    if (item.match && url.startsWith(item.match)) return true;
    if (item.sub?.some((s) => url === s.href || (s.href !== '/' && url.startsWith(s.href + '/')))) return true;
    return false;
};

const isSubActive = (sub) => currentRoute.value === sub.href;

watch(
    currentRoute,
    () => {
        for (const item of menu) {
            if (item.sub && item.sub.some((s) => currentRoute.value === s.href || currentRoute.value.startsWith(s.href + '/'))) {
                openMenus.value[item.id] = true;
            }
        }
    },
    { immediate: true },
);

const toggle = (id) => {
    openMenus.value[id] = !openMenus.value[id];
};
</script>

<template>
    <aside
        class="bg-cvaup-sidebar text-slate-300 flex flex-col h-screen overflow-hidden flex-shrink-0 transition-all duration-200 sticky top-0"
        :class="collapsed ? 'w-16' : 'w-64'"
    >
        <!-- Logo + título -->
        <div
            class="flex items-center gap-3 border-b border-white/10"
            :class="collapsed ? 'justify-center py-4 px-2' : 'px-5 py-5'"
        >
            <CvaupLogo :size="36" :show-text="!collapsed" />
        </div>

        <!-- Menú -->
        <nav class="flex-1 overflow-y-auto py-2">
            <template v-for="item in menu" :key="item.id">
                <!--
                    Item con submenú: estructura HÍBRIDA en un mismo row visual.
                    - El área del icono + label es un <Link> que navega al defaultHref (típicamente la lista).
                    - El área del chevron es un <button> separado que solo expande/colapsa.
                    - Resultado UX: click en "Técnicos" → va directo a /tecnicos. Click en chevron → expande sub-opciones.
                -->
                <div
                    v-if="item.sub"
                    :class="[
                        'flex items-center text-sm transition-colors border-l-[3px]',
                        item.disabled
                            ? 'opacity-40 cursor-not-allowed border-transparent'
                            : isActive(item)
                                ? 'bg-cvaup-primary/20 text-green-300 border-green-400'
                                : 'border-transparent hover:bg-white/5',
                    ]"
                >
                    <!-- Área principal: link a la lista del módulo -->
                    <Link
                        :href="item.disabled ? '#' : item.defaultHref"
                        :class="[
                            'flex items-center gap-3 px-5 py-2.5 flex-1 text-left',
                            item.disabled ? 'pointer-events-none' : '',
                        ]"
                        :title="item.disabled ? `${item.fase} — En construcción` : `Ir a ${item.label}`"
                    >
                        <Icon :name="item.icon" :size="20" />
                        <span v-if="!collapsed" class="flex-1">{{ item.label }}</span>
                        <span
                            v-if="!collapsed && item.disabled"
                            class="text-[9px] uppercase tracking-wider bg-amber-500/20 text-amber-300 px-1.5 py-0.5 rounded font-semibold"
                        >
                            {{ item.fase }}
                        </span>
                    </Link>

                    <!-- Botón del chevron: solo expande/colapsa, no navega -->
                    <button
                        v-if="!collapsed && !item.disabled"
                        type="button"
                        @click.stop="toggle(item.id)"
                        :title="openMenus[item.id] ? 'Colapsar' : 'Expandir'"
                        class="px-3 py-2.5 hover:bg-white/10 rounded-r"
                        aria-label="Expandir / Colapsar"
                    >
                        <Icon
                            name="chevron-right"
                            :size="16"
                            :class="['transition-transform duration-150', openMenus[item.id] ? 'rotate-90' : '']"
                        />
                    </button>
                </div>

                <!-- Item directo (sin submenu) -->
                <Link
                    v-else
                    :href="item.href"
                    :class="[
                        'w-full flex items-center gap-3 px-5 py-2.5 text-sm text-left transition-colors border-l-[3px]',
                        isActive(item)
                            ? 'bg-cvaup-primary/20 text-green-300 border-green-400'
                            : 'border-transparent hover:bg-white/5',
                    ]"
                >
                    <Icon :name="item.icon" :size="20" />
                    <span v-if="!collapsed" class="flex-1">{{ item.label }}</span>
                </Link>

                <!-- Submenú expandible -->
                <div v-if="item.sub && openMenus[item.id] && !collapsed && !item.disabled" class="pl-12">
                    <Link
                        v-for="sub in item.sub"
                        :key="sub.href"
                        :href="sub.href"
                        :class="[
                            'block w-full px-3 py-1.5 text-sm rounded transition-colors',
                            isSubActive(sub) ? 'text-green-400' : 'text-slate-400 hover:text-slate-200',
                        ]"
                    >
                        {{ sub.label }}
                    </Link>
                </div>
            </template>
        </nav>

        <!-- Footer -->
        <div v-if="!collapsed" class="px-5 py-3 border-t border-white/10 text-[11px] text-slate-500">
            v1.0.0 — {{ new Date().toLocaleDateString('es-VE', { month: 'long', year: 'numeric' }) }}
        </div>
    </aside>
</template>
