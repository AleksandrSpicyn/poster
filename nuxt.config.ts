import eslint from '@nuxt/eslint';
import { defineNuxtConfig } from 'nuxt/config';

// Nuxt generates the ESLint configuration. Laravel and Vite own the application entry points.
export default defineNuxtConfig({
    srcDir: 'resources/js',
    pages: false,
    components: false,
    imports: { autoImport: false, scan: false },
    devtools: { enabled: false },
    modules: [eslint],
    eslint: { config: { autoInit: false } },
});
