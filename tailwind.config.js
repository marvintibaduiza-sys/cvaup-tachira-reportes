import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"DM Sans"', 'Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Paleta institucional CVAUP Táchira (definida en el chat de specs)
                cvaup: {
                    primary: '#2E7D32',     // verde institucional
                    secondary: '#1B5E20',   // verde oscuro
                    accent: '#F9A825',      // amarillo/dorado
                    sidebar: '#1B3A1B',     // sidebar (verde muy oscuro armonizado con logo)
                    bg: '#F5F5F5',          // fondo principal
                },
                // Semáforo de cumplimiento
                semaforo: {
                    rojo: '#E53935',
                    amarillo: '#FDD835',
                    verde: '#43A047',
                    gris: '#94A3B8',
                },
                // Estados de reporte
                estado: {
                    completo: { bg: '#E8F5E9', text: '#2E7D32', border: '#A5D6A7' },
                    incompleto: { bg: '#FFF8E1', text: '#F57F17', border: '#FFE082' },
                    borrador: { bg: '#ECEFF1', text: '#546E7A', border: '#CFD8DC' },
                },
            },
        },
    },

    plugins: [forms],
};
