import js from '@eslint/js';
import pluginVue from 'eslint-plugin-vue';

export default [
    js.configs.recommended,
    ...pluginVue.configs['flat/recommended'],
    {
        files: ['resources/js/**/*.{js,vue}'],
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: {
                route: 'readonly',
                axios: 'readonly',
                sessionStorage: 'readonly',
                localStorage: 'readonly',
                window: 'readonly',
                document: 'readonly',
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
        ignores: ['vendor/**', 'public/**', 'bootstrap/**', 'node_modules/**'],
    },
];
