import defaultTheme from 'tailwindcss/defaultTheme';
import colors from 'tailwindcss/colors';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './app/Filament/**/*.php',
        './resources/views/filament/**/*.blade.php',
        './resources/views/livewire/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
    ],
    theme: {
        extend: {
            colors: {
                primary: colors.indigo,
                gray: colors.slate,
                danger: colors.rose,
                success: colors.emerald,
                warning: colors.orange,
                info: colors.blue,
            },
            fontFamily: {
                sans: ['Outfit', ...defaultTheme.fontFamily.sans],
            },
        },
    },
    plugins: [],
}
