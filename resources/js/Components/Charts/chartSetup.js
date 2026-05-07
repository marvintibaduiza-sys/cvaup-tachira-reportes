/**
 * chartSetup.js — registro de Chart.js v4.
 *
 * Chart.js v4 es tree-shakeable: NO importa todo el bundle por default.
 * Aquí registramos SOLO los controllers y elementos que usamos en el dashboard.
 *
 * Importar este archivo UNA SOLA VEZ en cualquier componente Chart asegura
 * que los componentes estén disponibles globalmente para vue-chartjs.
 *
 * Si en el futuro se agregan otros tipos (Radar, Polar, etc.), registrarlos aquí.
 */

import {
    Chart as ChartJS,
    // Tipos de gráficos que usamos
    BarController,
    LineController,
    DoughnutController,
    // Elementos visuales
    BarElement,
    LineElement,
    PointElement,
    ArcElement,
    // Escalas
    CategoryScale,
    LinearScale,
    // Plugins
    Title,
    Tooltip,
    Legend,
    Filler,
} from 'chart.js';

ChartJS.register(
    BarController,
    LineController,
    DoughnutController,
    BarElement,
    LineElement,
    PointElement,
    ArcElement,
    CategoryScale,
    LinearScale,
    Title,
    Tooltip,
    Legend,
    Filler,
);

// Defaults globales coherentes con la app (paleta CVAUP, tipografía)
ChartJS.defaults.font.family = "'Inter', system-ui, sans-serif";
ChartJS.defaults.font.size = 11;
ChartJS.defaults.color = '#475569'; // slate-600
ChartJS.defaults.plugins.legend.labels.usePointStyle = true;
ChartJS.defaults.plugins.legend.labels.padding = 12;
ChartJS.defaults.plugins.tooltip.backgroundColor = '#1B3A1B'; // sidebar CVAUP
ChartJS.defaults.plugins.tooltip.padding = 10;
ChartJS.defaults.plugins.tooltip.cornerRadius = 6;
ChartJS.defaults.plugins.tooltip.titleFont = { weight: '600' };

/**
 * Paleta CVAUP para datasets (usar en orden, ciclar si hay más de 6).
 */
export const CVAUP_PALETTE = [
    '#2E7D32', // primary
    '#1B5E20', // secondary (más oscuro)
    '#F9A825', // accent ámbar
    '#43A047', // verde claro
    '#66BB6A', // verde más claro
    '#EF6C00', // ámbar oscuro
    '#9E9D24', // oliva
    '#388E3C', // verde medio
    '#FBC02D', // amarillo
    '#558B2F', // verde lima oscuro
];

export { ChartJS };
