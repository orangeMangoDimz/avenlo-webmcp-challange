import { beforeEach, describe, expect, it } from "vitest";
import {
  ADMIN_SIDEBAR_PINNED_STORAGE_KEY,
  toggleSidebarFromMenu,
  readSidebarPinned,
  writeSidebarPinned,
} from "../useSidebarPinned";

const storage = new Map();
const localStorageMock = {
  clear: () => storage.clear(),
  getItem: (key) => (storage.has(key) ? storage.get(key) : null),
  setItem: (key, value) => storage.set(key, String(value)),
};

describe("useSidebarPinned persistence", () => {
  beforeEach(() => {
    globalThis.window = { localStorage: localStorageMock };
    globalThis.localStorage = localStorageMock;
    localStorageMock.clear();
  });

  it("defaults to unpinned and persists an explicit preference", () => {
    expect(readSidebarPinned()).toBe(false);
    writeSidebarPinned(true);
    expect(localStorage.getItem(ADMIN_SIDEBAR_PINNED_STORAGE_KEY)).toBe("true");
    expect(readSidebarPinned()).toBe(true);
  });

  it("treats unknown values as pinned for forward compatibility", () => {
    localStorage.setItem(ADMIN_SIDEBAR_PINNED_STORAGE_KEY, "unexpected");
    expect(readSidebarPinned()).toBe(true);
  });

  it("toggles the persisted desktop state and the mobile drawer state", () => {
    let pinned = false;
    const setPinned = (value) => {
      pinned = value;
    };

    expect(
      toggleSidebarFromMenu({
        pinned,
        open: false,
        isDesktop: true,
        setPinned,
      }),
    ).toBe(false);
    expect(pinned).toBe(true);

    expect(
      toggleSidebarFromMenu({
        pinned,
        open: false,
        isDesktop: true,
        setPinned,
      }),
    ).toBe(false);
    expect(pinned).toBe(false);

    expect(
      toggleSidebarFromMenu({
        pinned,
        isDesktop: false,
        setPinned,
      }),
    ).toBe(true);
    expect(pinned).toBe(false);

    expect(
      toggleSidebarFromMenu({
        pinned,
        open: true,
        isDesktop: false,
        setPinned,
      }),
    ).toBe(false);
  });
});
