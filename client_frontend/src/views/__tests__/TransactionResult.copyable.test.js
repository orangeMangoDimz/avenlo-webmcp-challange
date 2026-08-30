import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import { nextTick } from "vue";
import TransactionResult from "@/components/transactions/TransactionResult.vue";

vi.mock("@/stores/language", () => ({
  useLanguageStore: vi.fn(() => ({
    t: vi.fn((key, fallback) => fallback ?? key),
    currentLanguage: "en",
  })),
}));

describe("TransactionResult copyable rows", () => {
  let writeText;

  beforeEach(() => {
    vi.clearAllMocks();
    vi.useFakeTimers();
    writeText = vi.fn().mockResolvedValue(undefined);
    Object.defineProperty(navigator, "clipboard", {
      value: { writeText },
      configurable: true,
      writable: true,
    });
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it("renders a Copy button for copyable rows and writes the value to the clipboard", async () => {
    const wrapper = mount(TransactionResult, {
      props: {
        status: "pending",
        titleText: "Transfer to Virtual Account",
        messageText: "Pay via VA",
        reference: "DEP-1",
        rows: [
          { label: "Bank", value: "BCA" },
          {
            label: "Virtual Account",
            value: "8808123456789012",
            copyable: true,
          },
        ],
        hideStepper: true,
      },
    });

    const copyButtons = wrapper.findAll("button.copy-btn");
    expect(copyButtons).toHaveLength(1);
    expect(copyButtons[0].attributes("aria-label")).toBe("Copy");
    expect(copyButtons[0].find("i").classes()).toContain("fa-copy");
    expect(copyButtons[0].text().trim()).toBe("");

    await copyButtons[0].trigger("click");
    await flushPromises();

    expect(writeText).toHaveBeenCalledWith("8808123456789012");
    expect(copyButtons[0].attributes("aria-label")).toBe("Copied!");
    expect(copyButtons[0].find("i").classes()).toContain("fa-check");

    vi.advanceTimersByTime(2000);
    await nextTick();

    expect(copyButtons[0].attributes("aria-label")).toBe("Copy");
    expect(copyButtons[0].find("i").classes()).toContain("fa-copy");
  });

  it("does not render a Copy button for non-copyable rows", () => {
    const wrapper = mount(TransactionResult, {
      props: {
        status: "success",
        titleText: "Payment Successful!",
        messageText: "Done",
        rows: [
          { label: "Amount", value: "$100.00" },
          { label: "Bank", value: "BCA", copyable: false },
        ],
        hideStepper: true,
      },
    });

    expect(wrapper.find("button.copy-btn").exists()).toBe(false);
  });

  it("alerts when clipboard write fails", async () => {
    writeText.mockRejectedValueOnce(new Error("denied"));

    const wrapper = mount(TransactionResult, {
      props: {
        status: "pending",
        titleText: "Transfer to Virtual Account",
        messageText: "Pay via VA",
        rows: [
          {
            label: "Virtual Account",
            value: "8808123456789012",
            copyable: true,
          },
        ],
        hideStepper: true,
      },
    });

    await wrapper.find("button.copy-btn").trigger("click");
    await flushPromises();

    expect(global.alert).toHaveBeenCalledWith("Failed to copy address");
  });
});

describe("TransactionResult actions", () => {
  it("renders Bank Info section and standard New Deposit / History buttons", async () => {
    const wrapper = mount(TransactionResult, {
      props: {
        status: "pending",
        titleText: "Complete FlashPay Payment",
        messageText: "Use the bank details below",
        reference: "DEP-1",
        rows: [
          { label: "FlashPay Order", value: "FP-1", copyable: true },
          { label: "Bank Info", section: true },
          { label: "Bank", value: "BRI" },
        ],
        hideStepper: true,
        primaryActionText: "New Deposit",
      },
    });

    expect(wrapper.text()).toContain("Bank Info");
    expect(wrapper.find(".summary-row-section").exists()).toBe(true);
    expect(wrapper.text()).not.toContain("Open Payment Page");
    expect(wrapper.find(".hero-action-wrap").exists()).toBe(false);

    const actionButtons = wrapper.findAll(".confirm-actions > button");
    expect(actionButtons).toHaveLength(2);
    expect(actionButtons[0].text()).toContain("New Deposit");
    expect(actionButtons[0].classes()).toContain("primary-action");
    expect(actionButtons[1].text()).toContain("Transaction History");
    expect(actionButtons[1].classes()).toContain("secondary-action");

    await actionButtons[0].trigger("click");
    expect(wrapper.emitted("primary-action")).toHaveLength(1);
  });
});
