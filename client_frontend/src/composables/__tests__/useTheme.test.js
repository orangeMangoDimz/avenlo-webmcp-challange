import { beforeEach, describe, expect, it, vi } from "vitest";

const storageKey = "utrada-theme";
const localStorageMock = (() => {
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
})();

const createMediaQuery = (matches = false) => ({
  matches,
  addEventListener: vi.fn(),
  removeEventListener: vi.fn(),
});

describe("useTheme", () => {
  beforeEach(() => {
    vi.resetModules();
    Object.defineProperty(window, "localStorage", {
      configurable: true,
      value: localStorageMock,
    });
    localStorageMock.clear();
    delete document.documentElement.dataset.theme;
    document.documentElement.style.colorScheme = "";
    window.matchMedia = vi.fn(() => createMediaQuery(false));
  });

  it("restores a saved dark choice and applies it to the document", async () => {
    window.localStorage.setItem(storageKey, "dark");
    const { initializeTheme, theme } = await import("../useTheme");

    initializeTheme();

    expect(theme.value).toBe("dark");
    expect(document.documentElement.dataset.theme).toBe("dark");
    expect(document.documentElement.style.colorScheme).toBe("dark");
  });

  it("uses light mode by default even when the operating system is dark", async () => {
    window.matchMedia = vi.fn(() => createMediaQuery(true));
    const { initializeTheme, theme } = await import("../useTheme");

    initializeTheme();

    expect(theme.value).toBe("light");
    expect(document.documentElement.dataset.theme).toBe("light");
    expect(window.localStorage.getItem(storageKey)).toBeNull();
  });

  it("toggles the active appearance and persists the user choice", async () => {
    const { initializeTheme, isDark, toggleTheme } =
      await import("../useTheme");
    initializeTheme();

    toggleTheme();

    expect(isDark.value).toBe(true);
    expect(document.documentElement.dataset.theme).toBe("dark");
    expect(window.localStorage.getItem(storageKey)).toBe("dark");
  });

  it("updates a system preference when the operating-system appearance changes", async () => {
    const mediaQuery = createMediaQuery(false);
    window.matchMedia = vi.fn(() => mediaQuery);
    const { initializeTheme } = await import("../useTheme");

    initializeTheme();
    const { setTheme } = await import("../useTheme");
    setTheme("system");
    mediaQuery.matches = true;
    mediaQuery.addEventListener.mock.calls[0][1]();

    expect(document.documentElement.dataset.theme).toBe("dark");
  });

  it("keeps an explicit choice when the operating-system appearance changes", async () => {
    const mediaQuery = createMediaQuery(false);
    window.matchMedia = vi.fn(() => mediaQuery);
    const { initializeTheme, setTheme } = await import("../useTheme");

    initializeTheme();
    setTheme("light");
    mediaQuery.matches = true;
    mediaQuery.addEventListener.mock.calls[0][1]();

    expect(document.documentElement.dataset.theme).toBe("light");
  });

  it("ignores unsupported theme values", async () => {
    const { initializeTheme, setTheme, theme } = await import("../useTheme");
    initializeTheme();

    setTheme("sepia");

    expect(theme.value).toBe("light");
    expect(window.localStorage.getItem(storageKey)).toBeNull();
  });

  it("can return to the system preference and clear the saved choice", async () => {
    window.matchMedia = vi.fn(() => createMediaQuery(true));
    const { initializeTheme, setTheme, theme } = await import("../useTheme");
    initializeTheme();
    setTheme("light");

    setTheme("system");

    expect(theme.value).toBe("system");
    expect(document.documentElement.dataset.theme).toBe("dark");
    expect(window.localStorage.getItem(storageKey)).toBeNull();
  });
});
