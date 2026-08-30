import { computed, ref } from "vue";

const STORAGE_KEY = "utrada-theme";
const VALID_THEMES = new Set(["light", "dark", "system"]);

export const theme = ref("light");
const appliedTheme = ref("light");
let mediaQuery;
let mediaQueryListener;

const canUseDom = () =>
  typeof window !== "undefined" && typeof document !== "undefined";

const getSystemTheme = () => {
  if (!canUseDom() || typeof window.matchMedia !== "function") return "light";
  return window.matchMedia("(prefers-color-scheme: dark)").matches
    ? "dark"
    : "light";
};

const getSavedTheme = () => {
  if (!canUseDom()) return "light";

  try {
    const savedTheme = window.localStorage.getItem(STORAGE_KEY);
    return VALID_THEMES.has(savedTheme) ? savedTheme : "light";
  } catch {
    return "light";
  }
};

const applyTheme = () => {
  const nextTheme = theme.value === "system" ? getSystemTheme() : theme.value;
  appliedTheme.value = nextTheme;

  if (!canUseDom()) return;

  document.documentElement.dataset.theme = nextTheme;
  document.documentElement.style.colorScheme = nextTheme;
};

const listenForSystemThemeChanges = () => {
  if (!canUseDom() || typeof window.matchMedia !== "function") return;

  mediaQuery?.removeEventListener?.("change", mediaQueryListener);
  mediaQuery = window.matchMedia("(prefers-color-scheme: dark)");
  mediaQueryListener = () => {
    if (theme.value === "system") applyTheme();
  };
  mediaQuery.addEventListener?.("change", mediaQueryListener);
};

export const initializeTheme = () => {
  theme.value = getSavedTheme();
  applyTheme();
  listenForSystemThemeChanges();
};

export const setTheme = (nextTheme) => {
  if (!VALID_THEMES.has(nextTheme)) return;

  theme.value = nextTheme;
  applyTheme();

  if (!canUseDom()) return;

  try {
    if (nextTheme === "system") window.localStorage.removeItem(STORAGE_KEY);
    else window.localStorage.setItem(STORAGE_KEY, nextTheme);
  } catch {
    // A disabled storage area must not prevent the display preference from working.
  }
};

export const isDark = computed(() => appliedTheme.value === "dark");

export const toggleTheme = () => setTheme(isDark.value ? "light" : "dark");

export const useTheme = () => ({ theme, isDark, setTheme, toggleTheme });
