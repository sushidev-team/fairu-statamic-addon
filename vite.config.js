import vue from '@vitejs/plugin-vue2';
import laravel from 'laravel-vite-plugin';
import { defineConfig, loadEnv } from 'vite';
import { resolve } from 'path';

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '');
  const { host, protocol } = new URL(env.APP_URL ?? 'http://localhost');

  return {
    plugins: [
      laravel({
        input: ['resources/js/cp.js', 'resources/css/cp.css'],
        refresh: true,
        detectTls: protocol.startsWith('https') ? host : null,
        publicDirectory: 'resources/dist',
        hotFile: 'resources/dist/hot',
      }),
      vue(),
    ],
    resolve: {
      alias: {
        '@': resolve(__dirname, 'resources'),
      },
    },
    build: {
      assetsInlineLimit: 0,
    },
    server: {
      host,
    },
  };
});
