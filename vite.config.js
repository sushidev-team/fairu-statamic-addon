import vue from '@vitejs/plugin-vue2';
import laravel from 'laravel-vite-plugin';
import { defineConfig, loadEnv } from 'vite';

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '');
  const { host, protocol } = new URL(env.APP_URL ?? 'http://localhost');

  return {
    plugins: [
      laravel({
        input: ['resources/js/cp.js', 'resources/css/cp.css', 'resources/fonts/MaterialSymbolsOutlined-Regular.ttf'],
        refresh: true,
        detectTls: protocol.startsWith('https') ? host : null,
        publicDirectory: 'resources/dist',
        hotFile: 'resources/dist/hot',
      }),
      vue(),
    ],
    server: {
      host,
    },
  };
});
