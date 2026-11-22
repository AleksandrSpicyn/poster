import prettier from 'eslint-config-prettier';
import vue from 'eslint-plugin-vue';

import { configureVueProject, defineConfigWithVueTs, vueTsConfigs } from '@vue/eslint-config-typescript';

configureVueProject({ rootDir: import.meta.dirname + '/resources/js' });

export default defineConfigWithVueTs(
    vue.configs['flat/essential'],
    vueTsConfigs.recommended,
    {
        ignores: [
            'vendor',
            'node_modules',
            'public',
            'storage',
            'bootstrap/ssr',
            '.playwright-mcp/**',
            '.agents/skills/{frontend-design,ui-ux-pro-max,design-taste-frontend,vue-*,create-adaptable-composable}/**',
            'tailwind.config.js',
            'resources/js/components/ui/*',
        ],
    },
    {
        rules: {
            'vue/multi-word-component-names': 'off',
            '@typescript-eslint/no-explicit-any': 'off',
        },
    },
    prettier,
);
