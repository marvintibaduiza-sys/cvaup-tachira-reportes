<script setup>
import { Doughnut } from 'vue-chartjs';
import { computed, ref } from 'vue';
import { CVAUP_PALETTE } from './chartSetup';

/**
 * Doughnut chart — distribución de reportes del mes por municipio.
 *
 * Por qué doughnut y no pie:
 *  - El centro vacío permite mostrar el total ahí
 *  - Es más legible cuando hay muchas categorías (la atención se va al borde, no al centro)
 *  - Estéticamente más moderno
 *
 * Expone toBase64Image() para que el Dashboard pueda capturar este chart
 * al exportar el resumen ejecutivo a PDF.
 *
 * NOTA: el "total" centrado es un <div> absoluto sobre el canvas, NO se incluye
 * en toBase64Image() (que solo captura el canvas). El PDF muestra el doughnut
 * sin texto centrado, pero el total ya aparece en las stats arriba del PDF.
 */
const props = defineProps({
    data: {
        type: Array,
        default: () => [],
        // [{municipio: 'Jáuregui', count: 5}, ...]
    },
});

const chartRef = ref(null);

defineExpose({
    toBase64Image: () => chartRef.value?.chart?.toBase64Image('image/png', 1) ?? null,
});

const total = computed(() => props.data.reduce((acc, d) => acc + d.count, 0));

const chartData = computed(() => ({
    labels: props.data.map((d) => d.municipio),
    datasets: [
        {
            label: 'Reportes',
            data: props.data.map((d) => d.count),
            backgroundColor: props.data.map((_, i) => CVAUP_PALETTE[i % CVAUP_PALETTE.length]),
            borderColor: '#FFFFFF',
            borderWidth: 2,
            hoverOffset: 8, // se separa al hacer hover
        },
    ],
}));

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    cutout: '60%', // tamaño del agujero central
    plugins: {
        legend: {
            position: 'right',
            labels: {
                font: { size: 11 },
                boxWidth: 10,
                padding: 8,
            },
        },
        tooltip: {
            callbacks: {
                label: (ctx) => {
                    const value = ctx.parsed;
                    const pct = total.value > 0 ? ((value / total.value) * 100).toFixed(1) : 0;
                    return ` ${value} reporte${value === 1 ? '' : 's'} (${pct}%)`;
                },
            },
        },
    },
}));
</script>

<template>
    <div class="relative" style="height: 280px;">
        <template v-if="data.length > 0">
            <Doughnut ref="chartRef" :data="chartData" :options="chartOptions" />
            <!-- Total centrado en el agujero del doughnut -->
            <div
                class="absolute top-1/2 -translate-y-1/2 text-center pointer-events-none"
                style="left: calc(50% - 75px); transform: translate(-50%, -50%);"
            >
                <div class="text-2xl font-bold text-cvaup-primary leading-none">{{ total }}</div>
                <div class="text-[10px] text-slate-500 uppercase tracking-wider mt-1">total</div>
            </div>
        </template>
        <div
            v-else
            class="absolute inset-0 flex items-center justify-center text-sm text-slate-400"
        >
            Sin reportes este mes para distribuir.
        </div>
    </div>
</template>
