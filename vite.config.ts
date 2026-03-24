import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
    plugins: [vue()],
    publicDir: false,
    build: {
        outDir: 'public',
        emptyOutDir: false,
        rollupOptions: {
            input: 'resources/js/main.ts',
            output: {
                entryFileNames: 'app.js',
                chunkFileNames: 'chunks/[name]-[hash].js',
                assetFileNames: (assetInfo) => {
                    if (assetInfo.name?.endsWith('.css')) {
                        return 'app.css'
                    }
                    return 'assets/[name]-[hash][extname]'
                },
            },
        },
    },
    test: {
        environment: 'jsdom',
        globals: true,
    },
})
