import js from '@eslint/js';
import pluginVue from 'eslint-plugin-vue';
import eslintConfigPrettier from 'eslint-config-prettier';
import globals from 'globals';

export default [
    js.configs.recommended,
    ...pluginVue.configs['flat/recommended'],
    {
        files: ['resources/js/**/*.{js,vue}'],
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: {
                ...globals.browser,
                route: 'readonly',
                axios: 'readonly',
                process: 'readonly',
            },
        },
        rules: {
            'vue/multi-word-component-names': 'off',
            'vue/no-v-html': 'error',
            'no-unused-vars': ['warn', { argsIgnorePattern: '^_' }],
        },
    },
    {
        // ssr.js runs under Node during server-side rendering, not the browser.
        files: ['resources/js/ssr.js'],
        languageOptions: {
            globals: globals.node,
        },
    },
    {
        // Vitest runs specs in a jsdom-like environment (the browser globals
        // above already cover it) plus Node itself; resources/js/tests/setup.js
        // patches Node's `global` directly to install test-wide mocks.
        files: ['resources/js/tests/**/*.js'],
        languageOptions: {
            globals: globals.node,
        },
    },
    // Disables every core/plugin formatting rule that conflicts with
    // Prettier (indentation, attribute wrapping, etc.) — Prettier owns
    // formatting, ESLint owns everything else. Must stay last so it can
    // override the rules enabled above.
    eslintConfigPrettier,
    {
        ignores: ['vendor/**', 'public/**', 'bootstrap/**', 'node_modules/**'],
    },
];
