import { defineConfig } from 'vitest/config';

export default defineConfig({
  define: {
    __DEVICE_INTELLIGENCE_BUILD_TIME__: JSON.stringify(new Date().toISOString()),
  },
  test: {
    environment: 'jsdom',
    include: ['src/Resources/assets/tests/**/*.test.ts'],
    restoreMocks: true,
    clearMocks: true,
    coverage: {
      provider: 'v8',
      reporter: ['text', 'text-summary', 'html'],
      reportsDirectory: './coverage-ts',
      include: ['src/Resources/assets/src/**/*.ts'],
      exclude: ['**/*.test.ts', '**/node_modules/**', '**/types/**'],
      thresholds: {
        lines: 90,
        functions: 90,
        statements: 90,
        branches: 70,
      },
    },
  },
});
