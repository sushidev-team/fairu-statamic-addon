import vue from "@vitejs/plugin-vue2";
import laravel from "laravel-vite-plugin";
import { defineConfig } from "vite";

export default defineConfig({
  plugins: [
    laravel({
      input: ["resources/js/cp.js", "resources/css/cp.css"],
      refresh: true,
      publicDirectory: "resources/dist",
      hotFile: "resources/dist/hot",
    }),
    vue(),
  ],
});