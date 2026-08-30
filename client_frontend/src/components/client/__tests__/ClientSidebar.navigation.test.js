import { createMemoryHistory, createRouter } from "vue-router";
import { mount } from "@vue/test-utils";
import { describe, expect, it, vi } from "vitest";

vi.mock("@/stores/clientAuth", () => ({
  useClientAuthStore: () => ({
    canOpenNewAccount: true,
    isKycApproved: true,
    isIbApproved: true,
    showIbProgram: true,
  }),
}));

vi.mock("@/stores/language", () => ({
  useLanguageStore: () => ({
    t: (_key, fallback) => fallback,
  }),
}));

import ClientSidebar from "../ClientSidebar.vue";

const createTestRouter = () =>
  createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: "/:pathMatch(.*)*",
        component: { template: "<div />" },
      },
    ],
  });

describe("ClientSidebar navigation", () => {
  it("uses the client-facing grouped navigation names", async () => {
    const router = createTestRouter();
    await router.push("/client/dashboard");
    await router.isReady();

    const wrapper = mount(ClientSidebar, {
      props: { mobileOpen: true },
      global: { plugins: [router] },
    });

    const labels = wrapper
      .findAll(".menu-section-header")
      .map((header) => header.text().replace(/\s+/g, " ").trim());

    expect(labels).toEqual(
      expect.arrayContaining([
        "Home",
        "Funds",
        "Trading",
        "Partner program",
        "Documents",
      ]),
    );
    expect(labels).not.toContain("Accounts");
  });

  it("keeps pinning controlled by the top-level navigate button", async () => {
    const router = createTestRouter();
    await router.push("/client/dashboard");
    await router.isReady();

    const wrapper = mount(ClientSidebar, {
      props: { mobileOpen: false, pinned: false },
      global: { plugins: [router] },
    });

    expect(wrapper.find('[data-testid="sidebar-pin"]').exists()).toBe(false);
    expect(wrapper.emitted("toggle-pin")).toBeUndefined();
  });
});
