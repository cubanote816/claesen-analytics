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
                primary: {
                    50: '#e0f7ff',
                    100: '#b3ecff',
                    200: '#80dfff',
                    300: '#4dd2ff',
                    400: '#26c7ff',
                    500: '#00aeef',
                    600: '#009bd6',
                    700: '#0084b8',
                    800: '#006e99',
                    900: '#004866',
                    950: '#002a3d',
                },
                success: {
                    50: '#f4facc',
                    100: '#e9f599',
                    200: '#deef66',
                    300: '#d3ea33',
                    400: '#c8e500',
                    500: '#a5d610',
                    600: '#8db70e',
                    700: '#75980c',
                    800: '#5d790a',
                    900: '#455a08',
                    950: '#2d3b05',
                },
                danger: {
                    50: '#fce0ef',
                    100: '#f9b3df',
                    200: '#f680cd',
                    300: '#f34dbb',
                    400: '#f026a9',
                    500: '#e6007e',
                    600: '#b80065',
                    700: '#8a004c',
                    800: '#5c0032',
                    900: '#2e0019',
                    950: '#14000b',
                },
                warning: {
                    50: '#fff9e6',
                    100: '#fff0b3',
                    200: '#ffe780',
                    300: '#ffde4d',
                    400: '#fcd34d',
                    500: '#fcd34d',
                    600: '#cca300',
                    700: '#997a00',
                    800: '#665200',
                    900: '#332900',
                    950: '#1a1400',
                },
                gray: colors.slate,
                industrial: '#0b0b0f',
            },
            fontFamily: {
                sans: ['Outfit', ...defaultTheme.fontFamily.sans],
            },
        },
    },
    plugins: [],
}
