import { resolve } from 'node:path';
import { defineConfig } from 'vite';

export default defineConfig({
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
