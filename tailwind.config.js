const defaultTheme = require('tailwindcss/defaultTheme');

/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Cairo', ...defaultTheme.fontFamily.sans],
            },

            /*
             * Semantic, theme-aware design tokens.
             *
             * Values are driven by CSS custom properties defined in
             * resources/css/app.css, which flip automatically under
             * `@media (prefers-color-scheme: dark)` (this app's dark mode is
             * media/OS driven, not class based). Because the variables carry
             * the theme, these utilities resolve correctly in BOTH themes with
             * no `dark:` variant required — e.g. `text-secondary`, `bg-surface`.
             *
             * NOTE: `primary`/`secondary`/`tertiary`/`disabled` here are
             * FOREGROUND TEXT tokens (neutral greys), NOT the emerald brand
             * accent. Keep using `emerald-*` for brand/action colours.
             */
            colors: {
                primary: 'rgb(var(--fg-primary) / <alpha-value>)',
                secondary: 'rgb(var(--fg-secondary) / <alpha-value>)',
                tertiary: 'rgb(var(--fg-tertiary) / <alpha-value>)',
                disabled: 'rgb(var(--fg-disabled) / <alpha-value>)',
                surface: {
                    DEFAULT: 'rgb(var(--surface) / <alpha-value>)',
                    elevated: 'rgb(var(--surface-elevated) / <alpha-value>)',
                    sunken: 'rgb(var(--surface-sunken) / <alpha-value>)',
                },
                line: {
                    DEFAULT: 'rgb(var(--line) / <alpha-value>)',
                    strong: 'rgb(var(--line-strong) / <alpha-value>)',
                },
            },
        },
    },

    plugins: [require('@tailwindcss/forms'), require('@tailwindcss/typography')],
};
