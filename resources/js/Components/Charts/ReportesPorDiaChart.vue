<script setup>
import { Line } from 'vue-chartjs';
import { computed, ref } from 'vue';
import { CVAUP_PALETTE } from './chartSetup';

/**
 * Line chart — tendencia de reportes en los últimos 30 días.
 * Rellenado con área translúcida para enfatizar el volumen acumulado.
 *
 * Los datos vienen del backend YA rellenados con 0 en días sin reportes.
 *
 * Expone toBase64Image() para que el Dashboard pueda capturar este chart
 * al exportar el resumen ejecutivo a PDF.
 */
const props = defineProps({
    data: {
        type: Array,
        default: () => [],
        // [{fecha: 'YYYY-MM-DD', label: 'DD/MM', count: N}, ...]
    },
});

const chartRef = ref(null);

defineExpose({
    toBase64Image: () => chartRef.value?.chart?.toBase64Image('image/png', 1) ?? null,
});

const chartData = computed(() => ({
    labels: props.data.map((d) => d.label),
    datasets: [
        {
            label: 'Reportes',
            data: props.data.map((d) => d.count),
            borderColor: CVAUP_PALETTE[0], // verde primary
            backgroundColor: 'rgba(46, 125, 50, 0.12)', // verde primary translúcido
            borderWidth: 2,
            pointRadius: 2,
            pointHoverRadius: 5,
            pointBackgroundColor: CVAUP_PALETTE[0],
            tension: 0.3, // curva suave (no zigzag agresivo)
            fill: true, // sombra debajo de la línea
        },
    ],
}));

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
        mode: 'index',
        intersect: false,
    },
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                title: (items) => {
                    // Mostrar fecha completa en tooltip (DD/MM/YYYY)
                    const idx = items[0].dataIndex;
                    const fecha = props.data[idx]?.fecha;
                    if (!fecha) return items[0].label;
                    const [y, m, d] = fecha.split('-');
                    return `${d}/${m}/${y}`;
                },
                label: (ctx) => ` ${ctx.parsed.y} reporte${ctx.parsed.y === 1 ? '' : 's'}`,
            },
        },
    },
    scales: {
        x: {
            grid: { display: false },
            ticks: {
                font: { size: 10 },
                maxRotation: 0,
                autoSkip: true,
                maxTicksLimit: 10, // no abarrotar el eje X con 30 etiquetas
            },
        },
        y: {
            beginAtZero: true,
            ticks: {
                stepSize: 1,
                precision: 0,
            },
            grid: {
                color: '#E2E8F0',
            },
        },
    },
}));

// Detectamos si TODOS los días son 0 — si sí, mostramos empty state
const todosCero = computed(() => props.data.every((d) => d.count === 0));
</script>

<template>
    <div class="relative" style="height: 280px;">
        <Line
            v-if="data.length > 0 && !todosCero"
            ref="chartRef"
            :data="chartData"
            :options="chartOptions"
        />
        <div
            v-else
            class="absolute inset-0 flex items-center justify-center text-sm text-slate-400 text-center px-4"
        >
            <div>
                Sin reportes en los últimos 30 días.
                <br>
                <span class="text-xs">La tendencia se construye conforme se cargan reportes.</span>
            </div>
        </div>
    </div>
</template>
