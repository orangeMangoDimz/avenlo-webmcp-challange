import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import { createTestingPinia } from "@pinia/testing";
import { createRouter, createWebHashHistory } from "vue-router";
import { isRef } from "vue";

vi.mock("@/services/clientTransactionApi", () => ({
  default: {
    getClientGateways: vi.fn(),
    getAvailableBalance: vi.fn(),
    getSecuritySettings: vi.fn(),
    getClientDisplayContents: vi.fn(),
    getClientExchangeRates: vi.fn(),
    getDepositTemplatePayments: vi.fn(),
    createDeposit: vi.fn(),
    createAlchemyPayDeposit: vi.fn(),
  },
}));

vi.mock("@/services/tradingAccountService", () => ({
  default: {
    getAccounts: vi.fn(),
  },
}));

vi.mock("@/stores/language", () => ({
  useLanguageStore: vi.fn(() => ({
    t: vi.fn((key, fallback) => fallback ?? key),
    currentLanguage: "en",
  })),
}));

import clientTransactionService from "@/services/clientTransactionApi";
import tradingAccountService from "@/services/tradingAccountService";
import ClientTransactions from "@/views/ClientTransactions.vue";

const localStorageMock = (() => {
  let store = {};
  return {
    getItem: vi.fn((key) => store[key] ?? null),
    setItem: vi.fn((key, value) => {
      store[key] = String(value);
    }),
    removeItem: vi.fn((key) => {
      delete store[key];
    }),
    clear: vi.fn(() => {
      store = {};
    }),
  };
})();

const PHONE_QUESTION = {
  id: 1,
  name: "phone",
  questionType: "tel",
  depositEnabled: true,
  label: "Phone Number",
};

const VEXORA_VA_PAY_INFO = {
  bankName: "BCA",
  virtualAccountNumber: "8808123456789012",
  virtualAccountHolder: "UTRADA PT",
  expirationTimestamp: "2026-07-20T12:00:00Z",
};

function createTestRouter() {
  return createRouter({
    history: createWebHashHistory(),
    routes: [
      { path: "/", name: "home", component: { template: "<div />" } },
      {
        path: "/client/transactions",
        name: "client-transactions",
        component: { template: "<div />" },
      },
    ],
  });
}

function getSetupState(wrapper) {
  return wrapper.vm.$.setupState;
}

function setSetupRef(setupState, key, value) {
  const current = setupState[key];
  if (isRef(current)) {
    current.value = value;
    return;
  }
  setupState[key] = value;
}

function readSetupRef(setupState, key) {
  const current = setupState[key];
  if (isRef(current)) {
    return current.value;
  }
  return current;
}

function stubMountServices() {
  clientTransactionService.getClientGateways.mockResolvedValue({
    success: true,
    data: [
      { id: 1, gatewayKey: "vexora", gatewayName: "Vexora", type: "fiat" },
    ],
  });
  clientTransactionService.getAvailableBalance.mockResolvedValue({
    success: true,
    data: { availableBalance: 1000 },
  });
  clientTransactionService.getSecuritySettings.mockResolvedValue({
    success: true,
    data: {},
  });
  clientTransactionService.getClientDisplayContents.mockResolvedValue({
    success: true,
    data: [],
  });
  clientTransactionService.getClientExchangeRates.mockResolvedValue({
    success: true,
    data: [{ currency: "USD", exchangeRate: 1 }],
  });
  tradingAccountService.getAccounts.mockResolvedValue({
    success: true,
    data: [],
  });
}

async function mountClientTransactions() {
  const router = createTestRouter();
  await router.push("/client/transactions");

  const wrapper = mount(ClientTransactions, {
    global: {
      plugins: [
        router,
        createTestingPinia({
          initialState: {
            clientAuth: {
              user: { id: 1, email: "client@test.com" },
              token: "test-token",
              kycStatus: { submissionStatus: "approved" },
              isPreviewMode: false,
            },
          },
          createSpy: vi.fn,
        }),
      ],
      stubs: {
        KycRequiredNotice: true,
        PreDepositFlow: true,
        PreWithdrawalKyc: true,
        WithdrawalPanel: true,
        FormattedNumberInput: true,
        CustomSelect: true,
        DepositPanel: {
          template: `
            <div class="deposit-panel-stub">
              <slot name="support-questions" />
              <button data-testid="confirm-deposit" type="button" @click="onSubmit">Confirm</button>
            </div>
          `,
          props: {
            onSubmit: { type: Function, required: true },
            submitting: { type: Boolean, default: false },
            selectedItem: { type: Object, default: () => ({}) },
            gatewayContentHtml: { type: String, default: "" },
            tradingAccounts: { type: Array, default: () => [] },
            selectedTargetAccountType: { type: String, default: "" },
            accountBalance: { type: Number, default: 0 },
            currencies: { type: Array, default: () => [] },
            assetType: { type: String, default: "fiat" },
            selectedCurrency: { type: [String, Number, null], default: null },
            currencyLoading: { type: Boolean, default: false },
            currencyEmptyText: { type: String, default: "" },
            amount: { type: [Number, String, null], default: null },
            minAmount: { type: [Number, null], default: null },
            maxAmount: { type: [Number, null], default: null },
            feeSummary: { type: Object, default: () => ({}) },
            exchangeRate: { type: Number, default: 1 },
            displayCurrency: { type: String, default: "USD" },
            displayContents: { type: Array, default: () => [] },
            formatCurrency: { type: Function, default: (value) => `$${value}` },
            formatSettlementAmount: {
              type: Function,
              default: (value) => String(value),
            },
          },
        },
      },
    },
  });

  await flushPromises();
  return { wrapper, router };
}

async function seedDepositDetailsState(
  setupState,
  questions = [PHONE_QUESTION],
) {
  setSetupRef(setupState, "paymentGateways", [
    {
      id: 1,
      gatewayKey: "vexora",
      gatewayName: "Vexora",
      type: "fiat",
      supportedFiatCurrencies: ["USD"],
    },
  ]);
  setSetupRef(setupState, "depositLimits", {
    minimumAmount: 10,
    maximumAmount: 50000,
    dailyLimit: 0,
    monthlyLimit: 0,
  });
  setSetupRef(setupState, "depositStats", {
    todayDeposits: 0,
    monthlyDeposits: 0,
  });
  setSetupRef(setupState, "activeTab", "deposit");

  setSetupRef(setupState, "depositForm", {
    gatewayKey: "vexora",
    targetAccountType: "",
    tradingAccountId: "",
    platformAccountId: "",
    selectedCrypto: null,
    amount: null,
  });
  await flushPromises();

  setSetupRef(setupState, "depositTemplateMeta", { questions, type: "fiat" });
  setSetupRef(setupState, "depositCryptos", [
    {
      id: "USD",
      shortCode: "USD",
      code: "USD",
      paymentMethodId: 10,
      assetType: "fiat",
    },
  ]);
  setSetupRef(setupState, "depositForm", {
    gatewayKey: "vexora",
    targetAccountType: "wallet",
    tradingAccountId: "",
    platformAccountId: "",
    selectedCrypto: "USD",
    amount: 100,
  });
  setSetupRef(
    setupState,
    "depositSupportDataForm",
    questions.reduce((acc, question) => {
      acc[question.name] =
        question.name === "phone" ? "+1234567890" : "client@test.com";
      return acc;
    }, {}),
  );
  setSetupRef(setupState, "depositSupportFieldErrors", {});
  setSetupRef(setupState, "depositView", "details");
  await flushPromises();
}

describe("ClientTransactions Vexora VA deposit confirm", () => {
  let mountedWrapper;

  beforeEach(() => {
    Object.defineProperty(window, "localStorage", {
      value: localStorageMock,
      writable: true,
      configurable: true,
    });
    localStorageMock.clear();
    vi.clearAllMocks();
    stubMountServices();
    mountedWrapper = null;
  });

  afterEach(() => {
    if (!mountedWrapper) {
      return;
    }
    const setupState = getSetupState(mountedWrapper);
    if (typeof setupState.stopTransactionRedirectTimer === "function") {
      setupState.stopTransactionRedirectTimer();
    }
    mountedWrapper.unmount();
    mountedWrapper = null;
  });

  it("shows VA confirm rows and skips redirect when createDeposit returns vexoraPayInfo", async () => {
    const { wrapper } = await mountClientTransactions();
    mountedWrapper = wrapper;
    const setupState = getSetupState(wrapper);

    await seedDepositDetailsState(setupState);

    clientTransactionService.createDeposit.mockResolvedValueOnce({
      success: true,
      data: {
        transactionReference: "DEP-VA-001",
        vexoraPayInfo: VEXORA_VA_PAY_INFO,
        redirect: "get",
        redirectUrl: "https://cashier.example.com/pay",
      },
    });

    await setupState.confirmDeposit();
    await flushPromises();

    expect(clientTransactionService.createDeposit).toHaveBeenCalledTimes(1);
    expect(readSetupRef(setupState, "depositView")).toBe("confirm");
    expect(readSetupRef(setupState, "showTransactionRedirectModal")).toBe(
      false,
    );
    expect(readSetupRef(setupState, "transactionRedirectCountdown")).toBe(3);

    const confirmation = readSetupRef(setupState, "depositConfirmation");
    expect(confirmation.reference).toBe("DEP-VA-001");
    expect(confirmation.vexoraPayInfo).toEqual(VEXORA_VA_PAY_INFO);

    expect(readSetupRef(setupState, "depositResultStatus")).toBe("pending");
    expect(readSetupRef(setupState, "depositResultTitle")).toBe(
      "Transfer to Virtual Account",
    );
    expect(readSetupRef(setupState, "depositResultMessage")).toContain(
      "virtual account",
    );

    const rows = readSetupRef(setupState, "depositResultRows");
    expect(rows).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ label: "Bank", value: "BCA" }),
        expect.objectContaining({
          label: "Virtual Account",
          value: "8808123456789012",
          copyable: true,
        }),
        expect.objectContaining({
          label: "Account Holder",
          value: "UTRADA PT",
        }),
        expect.objectContaining({ label: "Expires" }),
      ]),
    );

    expect(wrapper.text()).toContain("8808123456789012");
    expect(wrapper.text()).toContain("Virtual Account");
  });

  it("skips redirect when vexoraPayInfo has only bankName", async () => {
    const { wrapper } = await mountClientTransactions();
    mountedWrapper = wrapper;
    const setupState = getSetupState(wrapper);

    await seedDepositDetailsState(setupState);

    clientTransactionService.createDeposit.mockResolvedValueOnce({
      success: true,
      data: {
        transactionReference: "DEP-VA-002",
        vexoraPayInfo: { bankName: "Mandiri" },
        redirect: "get",
        redirectUrl: "https://cashier.example.com/pay",
      },
    });

    await setupState.confirmDeposit();
    await flushPromises();

    expect(readSetupRef(setupState, "depositView")).toBe("confirm");
    expect(readSetupRef(setupState, "showTransactionRedirectModal")).toBe(
      false,
    );
    expect(readSetupRef(setupState, "depositResultStatus")).toBe("pending");
    expect(wrapper.text()).toContain("Mandiri");
  });

  it("starts redirect countdown when createDeposit returns redirect without vexoraPayInfo", async () => {
    const { wrapper } = await mountClientTransactions();
    mountedWrapper = wrapper;
    const setupState = getSetupState(wrapper);

    await seedDepositDetailsState(setupState);

    clientTransactionService.createDeposit.mockResolvedValueOnce({
      success: true,
      data: {
        transactionReference: "DEP-REDIRECT-001",
        redirect: "get",
        redirectUrl: "https://cashier.example.com/pay",
      },
    });

    await setupState.confirmDeposit();
    await flushPromises();

    expect(clientTransactionService.createDeposit).toHaveBeenCalledTimes(1);
    expect(readSetupRef(setupState, "showTransactionRedirectModal")).toBe(true);
    expect(readSetupRef(setupState, "transactionRedirectCountdown")).toBe(3);
    expect(readSetupRef(setupState, "transactionRedirectUrl")).toBe(
      "https://cashier.example.com/pay",
    );
    expect(readSetupRef(setupState, "transactionRedirectMethod")).toBe("get");
    expect(readSetupRef(setupState, "depositView")).toBe("details");
    // Teleport target: assert in document rather than wrapper
    expect(document.body.querySelector(".modal-overlay.active")).not.toBeNull();
    expect(document.body.textContent).toContain(
      "Redirecting to payment page...",
    );
  });
});
