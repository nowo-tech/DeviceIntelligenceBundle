import { resolve } from 'node:path';
import { defineConfig } from 'vite';

/**
 * Library IIFE for `assets:install` (REQ-ASSETS-004).
 * Symfony apps that use Pentatrion Vite compile the same TypeScript from
 * `src/Resources/assets` (see `demo/symfony8/vite.config.ts`).
 */
export default defineConfig({
  define: {
    __DEVICE_INTELLIGENCE_BUILD_TIME__: JSON.stringify(new Date().toISOString()),
  },
  build: {
    outDir: 'src/Resources/public/js',
    emptyOutDir: false,
    lib: {
      entry: resolve(__dirname, 'src/Resources/assets/src/index.ts'),
      name: 'DeviceIntelligence',
      formats: ['iife'],
      fileName: () => 'device-intelligence.min.js',
    },
    rollupOptions: {
      output: {
        inlineDynamicImports: true,
        footer: 'globalThis.DeviceIntelligence = DeviceIntelligence.DeviceIntelligence;',
      },
    },
    minify: true,
    sourcemap: true,
    target: 'es2022',
  },
});
