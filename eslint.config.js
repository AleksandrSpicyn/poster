import withNuxt from './.nuxt/eslint.config.mjs';
import prettier from 'eslint-config-prettier/flat';

export default withNuxt(
    {
        files: ['resources/js/**/*.vue'],
        rules: {
            'vue/multi-word-component-names': 'off',
            // Optional TypeScript props use undefined when omitted.
            'vue/require-default-prop': 'off',
            'vue/prop-name-casing': ['warn', 'camelCase', { ignoreProps: ['release_started'] }],
        },
    },
    prettier,
);
