import { vi } from "vitest";

const createStorage = () => {
  let values = {};

  return {
    clear: () => {
      values = {};
    },
    getItem: (key) => values[key] ?? null,
    removeItem: (key) => {
      delete values[key];
    },
    setItem: (key, value) => {
      values[key] = String(value);
    },
  };
};

// Node does not expose Web Storage globals in this test environment. The app
// and existing tests use these browser APIs directly, so provide one stable
// in-memory implementation for every suite.
vi.stubGlobal("localStorage", createStorage());
vi.stubGlobal("sessionStorage", createStorage());

// Suppress Vue Router warnings in tests
vi.stubGlobal("console", {
  ...console,
  warn: vi.fn(),
});

// Mock window.alert used in api.js preview-mode block
vi.stubGlobal("alert", vi.fn());
