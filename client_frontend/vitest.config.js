import { defineConfig } from "vitest/config";
import vue from "@vitejs/plugin-vue";
import path from "path";

// NOTE: thresholds are intentionally omitted from the coverage config below.
// This is the first test suite in client_frontend/ (see
// .claude/agent-memory/client-frontend-agent/project-test-infrastructure.md).
// A global 80% threshold across src/**/*.{js,vue} is unsatisfiable until many
// more files have tests, and would fail `npm run test:coverage` even when
// every written test passes. Re-introduce `coverage.thresholds` once overall
// coverage is broad enough that the gate reflects real regressions instead of
// "file X has no tests yet".
export default defineConfig({
  plugins: [vue()],
  test: {
    environment: "jsdom",
    globals: true,
    setupFiles: ["./src/test-setup.js"],
    include: ["src/**/*.test.js", "src/**/*.spec.js", "src/__tests__/**/*.js"],
    coverage: {
      provider: "v8",
      reporter: ["text", "lcov"],
      include: ["src/**/*.{js,vue}"],
      exclude: ["src/main.js", "src/router/index.js"],
    },
  },
  resolve: {
    alias: {
      "@": path.resolve(__dirname, "./src"),
    },
  },
});
