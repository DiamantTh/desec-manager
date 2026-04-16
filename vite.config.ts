import { defineConfig } from 'vite'
import { svelte } from '@sveltejs/vite-plugin-svelte'

// Jede Svelte-App (Seiten-Bereich) ist ein eigener Entry-Point.
// Output: themes/default/js/<name>.bundle.js — von PHP-Templates eingebunden.
export default defineConfig({
    plugins: [svelte()],
    build: {
        outDir: 'themes/default/js',
        emptyOutDir: false,   // theme.js im Zielordner nicht löschen
        rollupOptions: {
            input: {
                records: 'themes/default/src/records/main.ts',
                pwtools: 'themes/default/src/pwtools/main.ts',
                // domains: 'themes/default/src/domains/main.ts',  // bei Bedarf
            },
            output: {
                entryFileNames: '[name].bundle.js',
                chunkFileNames: '[name].chunk.js',
                assetFileNames: '[name][extname]',
            },
        },
    },
})
