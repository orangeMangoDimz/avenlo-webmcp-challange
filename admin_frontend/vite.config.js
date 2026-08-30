import { defineConfig, loadEnv } from "vite";
import vue from "@vitejs/plugin-vue";
import { fileURLToPath, URL } from "node:url";

// https://vitejs.dev/config/
export default defineConfig(({ mode }) => {
  // 加载环境变量
  const env = loadEnv(mode, process.cwd(), "");

  return {
    plugins: [vue()],
    resolve: {
      alias: {
        "@": fileURLToPath(new URL("./src", import.meta.url)),
      },
    },
    // 配置基础路径
    // 开发环境使用根路径，生产环境部署在根目录
    // 如果生产环境部署在子目录，请修改为对应路径，如：'/admin/' 或 '/Vue/'
    base: mode === "production" ? "/" : "/",

    server: {
      host: true,
      port: 3000,
      strictPort: true,
      proxy: {
        "/index.php": {
          target: process.env.VITE_PROXY_TARGET || "http://localhost:8000",
          changeOrigin: false,
        },
      },
    },

    // 构建配置
    build: {
      // 输出目录
      outDir: "dist",
      // 生成 source map 便于调试
      sourcemap: mode !== "production",
      // 消除打包大小超过 500kb 警告
      chunkSizeWarningLimit: 1000,
      rollupOptions: {
        output: {
          // 静态资源分类打包
          chunkFileNames: "js/[name]-[hash].js",
          entryFileNames: "js/[name]-[hash].js",
          assetFileNames: "[ext]/[name]-[hash].[ext]",
        },
      },
    },
  };
});
