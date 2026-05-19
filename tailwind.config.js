import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            keyframes: {
                'metallic-shimmer': {
                    '0%': { transform: 'translateX(-130%) skewX(-14deg)' },
                    '100%': { transform: 'translateX(230%) skewX(-14deg)' },
                },
            },
            animation: {
                'metallic-shimmer': 'metallic-shimmer 14s ease-in-out infinite',
            },
        },
    },

    plugins: [forms],
};
