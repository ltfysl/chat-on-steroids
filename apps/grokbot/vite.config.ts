import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import wayfinder from '@laravel/vite-plugin-wayfinder';

export default defineConfig({
  plugins: [
    laravel({ input: ['resources/css/app.css', 'resources/js/app.ts'], refresh: true }),
    vue(),
    tailwindcss(),
    wayfinder(),
  ],
  resolve: { alias: { '@': '/resources/js' } },
});
