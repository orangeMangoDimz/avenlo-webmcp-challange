import { computed, onMounted, onUnmounted, ref } from "vue";

export const ADMIN_SIDEBAR_PINNED_STORAGE_KEY = "utrada-admin-sidebar-pinned";

const DESKTOP_BREAKPOINT = 1024;

const getStorage = () => {
  try {
    return window.localStorage;
  } catch {
    return null;
  }
};

export const readSidebarPinned = (
  storageKey = ADMIN_SIDEBAR_PINNED_STORAGE_KEY,
  defaultValue = false,
) => {
  try {
    const storedValue = getStorage()?.getItem(storageKey);
    return storedValue == null ? defaultValue : storedValue !== "false";
  } catch {
    return defaultValue;
  }
};

export const writeSidebarPinned = (
  value,
  storageKey = ADMIN_SIDEBAR_PINNED_STORAGE_KEY,
) => {
  const storage = getStorage();
  try {
    storage?.setItem(storageKey, String(Boolean(value)));
  } catch {
    // Preferences are best-effort when storage is disabled or full.
  }
};

/**
 * Keep the top-level menu button as the only sidebar control.
 * Desktop clicks toggle the persisted pinned state; mobile clicks toggle the
 * temporary navigation drawer without changing the persisted preference.
 */
export const toggleSidebarFromMenu = ({
  pinned,
  open = false,
  isDesktop,
  setPinned,
}) => {
  if (isDesktop) {
    setPinned(!pinned);
    return false;
  }

  return !open;
};

const isDesktopViewport = () =>
  typeof window === "undefined" || window.innerWidth >= DESKTOP_BREAKPOINT;

export const useSidebarPinned = () => {
  const pinned = ref(readSidebarPinned());
  const isDesktop = ref(isDesktopViewport());
  const effectivePinned = computed(() => pinned.value && isDesktop.value);

  const updateViewport = () => {
    isDesktop.value = isDesktopViewport();
  };

  const setPinned = (value) => {
    pinned.value = Boolean(value);
    writeSidebarPinned(pinned.value);
  };

  const togglePinned = () => setPinned(!pinned.value);

  onMounted(() => window.addEventListener("resize", updateViewport));
  onUnmounted(() => window.removeEventListener("resize", updateViewport));

  return { pinned, isDesktop, effectivePinned, setPinned, togglePinned };
};
