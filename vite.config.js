import laravel from 'laravel-vite-plugin';
import statamic from '@statamic/cms/vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
  outDir: 'resources/dist',
  plugins: [
    statamic(),
    laravel({
      input: ['resources/js/addon.js'],
      refresh: true,
      publicDirectory: 'resources/dist',
    }),
  ],
});
