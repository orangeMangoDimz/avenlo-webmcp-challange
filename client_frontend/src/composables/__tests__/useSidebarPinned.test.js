import { beforeEach, describe, expect, it } from "vitest";
import {
  CLIENT_SIDEBAR_PINNED_STORAGE_KEY,
  readSidebarPinned,
  toggleSidebarFromMenu,
  writeSidebarPinned,
} from "../useSidebarPinned";

describe("useSidebarPinned persistence", () => {
  beforeEach(() => localStorage.clear());

  it("defaults to unpinned and persists an explicit preference", () => {
    expect(readSidebarPinned()).toBe(false);
    writeSidebarPinned(true);
    expect(localStorage.getItem(CLIENT_SIDEBAR_PINNED_STORAGE_KEY)).toBe(
      "true",
    );
    expect(readSidebarPinned()).toBe(true);
  });

  it("treats unknown values as pinned for forward compatibility", () => {
    localStorage.setItem(CLIENT_SIDEBAR_PINNED_STORAGE_KEY, "unexpected");
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
        open: false,
        isDesktop: false,
        setPinned,
      }),
    ).toBe(true);
    expect(pinned).toBe(false);
  });
});
