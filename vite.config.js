import laravel from 'laravel-vite-plugin';
import statamic from '@statamic/cms/vite-plugin';
import { defineConfig, loadEnv } from 'vite';

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '');
  const { host, protocol } = new URL(env.APP_URL ?? 'http://localhost');

  return {
    base: mode === 'production' ? '/vendor/fairu-statamic/build/' : '/',
    plugins: [
      laravel({
        input: ['resources/js/cp.js', 'resources/css/cp.css'],
        refresh: true,
        detectTls: protocol.startsWith('https') ? host : null,
        publicDirectory: 'resources/dist',
        hotFile: 'resources/dist/hot',
      }),
      statamic(),
    ],
    server: {
      host,
    },
    build: {
      rollupOptions: {
        external: ['@statamic/cms'],
        output: {
          globals: {
            '@statamic/cms': 'Statamic',
          },
        },
      },
    },
  };
});
