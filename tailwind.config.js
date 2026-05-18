import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['PingFang TC', 'Noto Sans TC', 'Microsoft JhengHei', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                app: 'var(--color-bg-base)',
                surface: 'var(--color-bg-surface)',
                elevated: 'var(--color-bg-elevated)',
                subtle: 'var(--color-bg-subtle)',
                hover: 'var(--color-bg-hover)',
                active: 'var(--color-bg-active)',
                primary: 'var(--color-text-primary)',
                secondary: 'var(--color-text-secondary)',
                muted: 'var(--color-text-muted)',
                inverse: 'var(--color-text-inverse)',
                'border-default': 'var(--color-border-default)',
                'border-muted': 'var(--color-border-muted)',
                'border-strong': 'var(--color-border-strong)',
                'border-active': 'var(--color-border-active)',
                accent: 'var(--color-accent-primary)',
                'accent-hover': 'var(--color-accent-primary-hover)',
                'accent-subtle': 'var(--color-accent-primary-subtle)',
                success: 'var(--color-accent-success)',
                warning: 'var(--color-accent-warning)',
                danger: 'var(--color-accent-danger)',
                info: 'var(--color-accent-info)',
                selected: 'var(--color-state-selected)',
                disabled: 'var(--color-state-disabled)',
                focus: 'var(--color-state-focus)',
            },
            boxShadow: {
                card: 'var(--shadow-card)',
                elevated: 'var(--shadow-elevated)',
            },
            ringColor: {
                focus: 'var(--ring-focus)',
            },
        },
    },

    plugins: [forms],
};
