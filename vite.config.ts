import { createHash } from 'node:crypto';
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { resolve } from 'node:path';
import { defineConfig } from 'vite';

/**
 * Deterministic build id: a hash of the TypeScript/CSS sources and pnpm-lock.yaml. It only changes when the code or
 * the dependencies change, so rebuilding unchanged sources (e.g. in `make release-check`) leaves the committed bundle
 * byte-identical (the former wall-clock stamp rewrote it on every build).
 */
function sourceBuildId(dir: string): string {
  const root = new URL(`${dir}/`, import.meta.url);
  const hash = createHash('sha256');
  const files = (readdirSync(root, { recursive: true }) as string[])
    .filter((file) => !file.includes('.test.') && statSync(new URL(file, root)).isFile())
    .sort();
  for (const file of files) {
    hash.update(file).update(readFileSync(new URL(file, root)));
  }
  hash.update(readFileSync(new URL('pnpm-lock.yaml', import.meta.url)));

  return hash.digest('hex').slice(0, 12);
}

/**
 * Library IIFE for `assets:install` (REQ-ASSETS-004).
 * Symfony apps that use Pentatrion Vite compile the same TypeScript from
 * `src/Resources/assets` (see `demo/symfony8/vite.config.ts`).
 */
export default defineConfig({
  define: {
    __DEVICE_INTELLIGENCE_BUILD_TIME__: JSON.stringify(sourceBuildId('src/Resources/assets/src')),
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
