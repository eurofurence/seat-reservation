import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    mode: 'jit',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            colors: {
                primary: {
                    DEFAULT: '#005953',
                    foreground: '#ffffff',
                    100: '#E6EFEE',
                    200: '#CBDEDD',
                    300: '#AEC6C4',
                    400: '#69A3A2',
                    500: '#005953',
                    600: '#00504B',
                    700: '#003532',
                    800: '#002825',
                    900: '#001B19',
                },
                secondary: {
                    DEFAULT: 'hsl(210, 40%, 96.1%)',
                    foreground: 'hsl(222.2, 47.4%, 11.2%)',
                },
                destructive: {
                    DEFAULT: 'hsl(0, 84.2%, 60.2%)',
                    foreground: 'hsl(210, 40%, 98%)',
                },
                muted: {
                    DEFAULT: 'hsl(210, 40%, 96.1%)',
                    foreground: 'hsl(215.4, 16.3%, 46.9%)',
                },
                accent: {
                    DEFAULT: 'hsl(210, 40%, 96.1%)',
                    foreground: 'hsl(222.2, 47.4%, 11.2%)',
                },
                card: {
                    DEFAULT: 'hsl(0, 0%, 100%)',
                    foreground: 'hsl(222.2, 84%, 4.9%)',
                },
                border: 'hsl(214.3, 31.8%, 91.4%)',
                input: 'hsl(214.3, 31.8%, 91.4%)',
                ring: '#005953',
                background: 'hsl(0, 0%, 100%)',
                foreground: 'hsl(222.2, 84%, 4.9%)',
            },
            borderRadius: {
                lg: '0.5rem',
                md: 'calc(0.5rem - 2px)',
                sm: 'calc(0.5rem - 4px)',
            },
            keyframes: {
                "accordion-down": {
                    from: { height: 0 },
                    to: { height: "var(--radix-accordion-content-height)" },
                },
                "accordion-up": {
                    from: { height: "var(--radix-accordion-content-height)" },
                    to: { height: 0 },
                },
            },
            animation: {
                "accordion-down": "accordion-down 0.2s ease-out",
                "accordion-up": "accordion-up 0.2s ease-out",
            },
        },
    },

    plugins: [forms],
};
