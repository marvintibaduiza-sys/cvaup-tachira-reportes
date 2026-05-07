<script setup>
import { Bar } from 'vue-chartjs';
import { computed, ref } from 'vue';
import { CVAUP_PALETTE } from './chartSetup';

/**
 * Bar chart horizontal — top N técnicos por # reportes del mes.
 * Diseño horizontal porque los nombres pueden ser largos y se leen mejor.
 *
 * Expone toBase64Image() para que el Dashboard pueda capturar este chart
 * al exportar el resumen ejecutivo a PDF.
 */
const props = defineProps({
    data: {
        type: Array,
        default: () => [],
        // [{nombre: 'Francy Ordoñez', count: 5}, ...]
    },
});

// Template ref hacia el componente Bar para acceder a la instancia Chart.js
const chartRef = ref(null);

// Exponemos el método de captura al padre.
// `chart.toBase64Image()` es nativo de Chart.js v4 — devuelve data:image/png;base64,...
defineExpose({
    toBase64Image: () => chartRef.value?.chart?.toBase64Image('image/png', 1) ?? null,
});

const chartData = computed(() => ({
    labels: props.data.map((d) => d.nombre),
    datasets: [
        {
            label: 'Reportes del mes',
            data: props.data.map((d) => d.count),
            backgroundColor: CVAUP_PALETTE[0], // verde primary
            borderRadius: 4,
            borderSkipped: false,
        },
    ],
}));

const chartOptions = computed(() => ({
    indexAxis: 'y', // barras horizontales
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false }, // un solo dataset, leyenda redundante
        tooltip: {
            callbacks: {
                label: (ctx) => ` ${ctx.parsed.x} reporte${ctx.parsed.x === 1 ? '' : 's'}`,
            },
        },
    },
    scales: {
        x: {
            beginAtZero: true,
            ticks: {
                stepSize: 1,
                precision: 0, // solo enteros, no decimales
            },
            grid: {
                color: '#E2E8F0', // slate-200
            },
        },
        y: {
            grid: { display: false },
            ticks: {
                font: { size: 11 },
            },
        },
    },
}));
</script>

<template>
    <div class="relative" style="height: 280px;">
        <Bar
            v-if="data.length > 0"
            ref="chartRef"
            :data="chartData"
            :options="chartOptions"
        />
        <div
            v-else
            class="absolute inset-0 flex items-center justify-center text-sm text-slate-400"
        >
            Sin reportes este mes para mostrar.
        </div>
    </div>
</template>
