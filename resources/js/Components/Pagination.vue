<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Pagination — paginador para Inertia + Laravel paginate().
 *
 * Espera un objeto `meta` con la estructura de Laravel:
 *  { current_page, last_page, from, to, total, links: [...] }
 *
 * SEGURIDAD (post-auditoría LOW #3):
 *  ANTES usaba v-html con link.label (que viene del paginator de Laravel como "&laquo; Previous").
 *  Aunque la fuente es trusted (framework), v-html rompe el principio "nunca v-html".
 *  AHORA: parseamos las labels y devolvemos texto plano. El framework NO controla qué
 *  símbolo usar — usamos chevrons ‹ › limpios y números directos.
 */
const props = defineProps({
    meta: { type: Object, required: true },
});

const showPaginator = computed(() => props.meta.last_page > 1);

/**
 * Convierte una label cruda del paginator de Laravel a texto seguro.
 *
 * Laravel envía:
 *  - "&laquo; Previous"  → flecha izquierda
 *  - "Next &raquo;"      → flecha derecha
 *  - "1", "2", "3", ...  → número de página (texto plano, seguro)
 *  - "..."               → ellipsis (texto plano, seguro)
 *
 * No usamos v-html — devolvemos string plano que Vue escapa automáticamente.
 */
const cleanLabel = (label) => {
    if (typeof label !== 'string') return '';
    if (label.includes('Previous')) return '‹';
    if (label.includes('Next')) return '›';
    return label; // número o ellipsis
};
</script>

<template>
    <div v-if="showPaginator || meta.total > 0" class="flex items-center justify-between mt-4 px-2 py-3">
        <p class="text-xs text-slate-500">
            Mostrando <span class="font-semibold">{{ meta.from ?? 0 }}</span> –
            <span class="font-semibold">{{ meta.to ?? 0 }}</span>
            de <span class="font-semibold">{{ meta.total }}</span> resultados
        </p>

        <div v-if="showPaginator" class="flex items-center gap-1">
            <template v-for="(link, i) in meta.links" :key="i">
                <component
                    :is="link.url ? Link : 'span'"
                    :href="link.url || undefined"
                    preserve-scroll
                    preserve-state
                    :class="[
                        'min-w-[32px] px-2 h-8 inline-flex items-center justify-center rounded text-xs',
                        link.active
                            ? 'bg-cvaup-primary text-white font-semibold'
                            : link.url
                                ? 'text-slate-600 hover:bg-slate-100'
                                : 'text-slate-300 cursor-not-allowed',
                    ]"
                >
                    {{ cleanLabel(link.label) }}
                </component>
            </template>
        </div>
    </div>
</template>
