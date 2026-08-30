/** @vitest-environment jsdom */

import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import {
  WEBMCP_ENABLED_STORAGE_KEY,
  isWebMcpEnabled,
  setWebMcpEnabled,
  subscribeWebMcpEnabled,
} from "../adminWebMcpSettings";

describe("admin WebMCP settings", () => {
  beforeEach(() => {
    const values = new Map();
    Object.defineProperty(window, "localStorage", {
      configurable: true,
      value: {
        clear: () => values.clear(),
        getItem: (key) => values.get(key) ?? null,
        setItem: (key, value) => values.set(key, String(value)),
      },
    });
  });

  afterEach(() => {
    localStorage.clear();
    vi.restoreAllMocks();
  });

  it("defaults to enabled when no browser setting exists", () => {
    expect(isWebMcpEnabled()).toBe(true);
  });

  it("persists the global enabled state and notifies subscribers", () => {
    const listener = vi.fn();
    const unsubscribe = subscribeWebMcpEnabled(listener);

    expect(setWebMcpEnabled(false)).toBe(false);
    expect(localStorage.getItem(WEBMCP_ENABLED_STORAGE_KEY)).toBe("false");
    expect(isWebMcpEnabled()).toBe(false);
    expect(listener).toHaveBeenLastCalledWith(false);

    unsubscribe();
    setWebMcpEnabled(true);
    expect(listener).toHaveBeenCalledTimes(1);
  });

  it("reads external storage changes", () => {
    const listener = vi.fn();
    const unsubscribe = subscribeWebMcpEnabled(listener);

    localStorage.setItem(WEBMCP_ENABLED_STORAGE_KEY, "false");
    window.dispatchEvent(
      new StorageEvent("storage", {
        key: WEBMCP_ENABLED_STORAGE_KEY,
        newValue: "false",
      }),
    );

    expect(listener).toHaveBeenLastCalledWith(false);
    unsubscribe();
  });
});
