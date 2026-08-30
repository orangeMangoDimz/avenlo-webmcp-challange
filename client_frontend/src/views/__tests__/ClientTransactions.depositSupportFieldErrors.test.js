import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import { createTestingPinia } from "@pinia/testing";
import { createRouter, createWebHashHistory } from "vue-router";
import { nextTick, isRef } from "vue";

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

const EMAIL_QUESTION = {
  id: 2,
  name: "email",
  questionType: "email",
  depositEnabled: true,
  label: "Email",
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
        TransactionResult: true,
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

describe("ClientTransactions deposit support field errors", () => {
  beforeEach(() => {
    Object.defineProperty(window, "localStorage", {
      value: localStorageMock,
      writable: true,
      configurable: true,
    });
    localStorageMock.clear();
    vi.clearAllMocks();
    stubMountServices();
  });

  describe("extractDepositSupportFieldErrors", () => {
    it("maps matching question keys from array and string API errors", async () => {
      const { wrapper } = await mountClientTransactions();
      const setupState = getSetupState(wrapper);

      await seedDepositDetailsState(setupState, [
        PHONE_QUESTION,
        EMAIL_QUESTION,
      ]);

      expect(
        setupState.extractDepositSupportFieldErrors({
          errors: {
            phone: ["Phone number is invalid"],
            email: "Email is required",
          },
        }),
      ).toEqual({
        phone: "Phone number is invalid",
        email: "Email is required",
      });
    });

    it("ignores API error keys that are not deposit support question names", async () => {
      const { wrapper } = await mountClientTransactions();
      const setupState = getSetupState(wrapper);

      await seedDepositDetailsState(setupState, [PHONE_QUESTION]);

      expect(
        setupState.extractDepositSupportFieldErrors({
          errors: {
            phone: ["Invalid phone"],
            amount: ["Amount too low"],
            gatewaySettingId: "Missing gateway",
          },
        }),
      ).toEqual({
        phone: "Invalid phone",
      });
    });

    it("returns an empty object when errors payload is missing or invalid", async () => {
      const { wrapper } = await mountClientTransactions();
      const setupState = getSetupState(wrapper);

      await seedDepositDetailsState(setupState, [PHONE_QUESTION]);

      expect(setupState.extractDepositSupportFieldErrors(null)).toEqual({});
      expect(setupState.extractDepositSupportFieldErrors({})).toEqual({});
      expect(
        setupState.extractDepositSupportFieldErrors({ errors: "bad" }),
      ).toEqual({});
    });

    it("uses the first non-empty array message and skips blank values", async () => {
      const { wrapper } = await mountClientTransactions();
      const setupState = getSetupState(wrapper);

      await seedDepositDetailsState(setupState, [PHONE_QUESTION]);

      expect(
        setupState.extractDepositSupportFieldErrors({
          errors: {
            phone: ["", "  ", "Phone must be valid"],
          },
        }),
      ).toEqual({
        phone: "Phone must be valid",
      });

      expect(
        setupState.extractDepositSupportFieldErrors({
          errors: {
            phone: ["", "   "],
          },
        }),
      ).toEqual({});
    });
  });

  describe("clearDepositSupportFieldError", () => {
    it("removes a single field error without mutating other entries", async () => {
      const { wrapper } = await mountClientTransactions();
      const setupState = getSetupState(wrapper);

      setSetupRef(setupState, "depositSupportFieldErrors", {
        phone: "Invalid phone",
        email: "Invalid email",
      });

      setupState.clearDepositSupportFieldError("phone");

      expect(readSetupRef(setupState, "depositSupportFieldErrors")).toEqual({
        email: "Invalid email",
      });
    });

    it("does nothing when field name is missing or no error exists", async () => {
      const { wrapper } = await mountClientTransactions();
      const setupState = getSetupState(wrapper);

      setSetupRef(setupState, "depositSupportFieldErrors", {
        phone: "Invalid phone",
      });

      setupState.clearDepositSupportFieldError("");
      setupState.clearDepositSupportFieldError("email");

      expect(readSetupRef(setupState, "depositSupportFieldErrors")).toEqual({
        phone: "Invalid phone",
      });
    });
  });

  describe("confirmDeposit error handling", () => {
    it("sets depositSupportFieldErrors and skips alert when API returns support field errors", async () => {
      const { wrapper } = await mountClientTransactions();
      const setupState = getSetupState(wrapper);

      await seedDepositDetailsState(setupState, [PHONE_QUESTION]);

      clientTransactionService.createDeposit.mockRejectedValueOnce({
        response: {
          data: {
            message: "Validation failed",
            errors: {
              phone: ["Phone number is invalid"],
            },
          },
        },
        message: "Request failed",
      });

      await setupState.confirmDeposit();
      await flushPromises();

      expect(clientTransactionService.createDeposit).toHaveBeenCalledTimes(1);
      expect(readSetupRef(setupState, "depositSupportFieldErrors")).toEqual({
        phone: "Phone number is invalid",
      });
      expect(global.alert).not.toHaveBeenCalled();
    });

    it("alerts with the API message when no support field errors are present", async () => {
      const { wrapper } = await mountClientTransactions();
      const setupState = getSetupState(wrapper);

      await seedDepositDetailsState(setupState, [PHONE_QUESTION]);

      clientTransactionService.createDeposit.mockRejectedValueOnce({
        response: {
          data: {
            message: "Gateway unavailable",
            errors: {
              amount: ["Amount too low"],
            },
          },
        },
        message: "Request failed",
      });

      await setupState.confirmDeposit();
      await flushPromises();

      expect(readSetupRef(setupState, "depositSupportFieldErrors")).toEqual({});
      expect(global.alert).toHaveBeenCalledWith(
        "Failed to create deposit: Gateway unavailable",
      );
    });
  });

  describe("support question template", () => {
    it("shows input-error and error-text for matching support question fields", async () => {
      const { wrapper } = await mountClientTransactions();
      const setupState = getSetupState(wrapper);

      await seedDepositDetailsState(setupState, [PHONE_QUESTION]);
      setSetupRef(setupState, "depositSupportFieldErrors", {
        phone: "Phone number is invalid",
      });

      await flushPromises();

      expect(wrapper.find(".deposit-panel-stub").exists()).toBe(true);

      const phoneInput = wrapper.find('input[type="tel"]');
      expect(phoneInput.exists()).toBe(true);
      expect(phoneInput.classes()).toContain("input-error");

      const errorText = wrapper.find(".support-questions-inline .error-text");
      expect(errorText.exists()).toBe(true);
      expect(errorText.text()).toBe("Phone number is invalid");
    });

    it("clears field error styling after the user edits the input", async () => {
      const { wrapper } = await mountClientTransactions();
      const setupState = getSetupState(wrapper);

      await seedDepositDetailsState(setupState, [PHONE_QUESTION]);
      setSetupRef(setupState, "depositSupportFieldErrors", {
        phone: "Phone number is invalid",
      });

      await flushPromises();

      expect(wrapper.find(".deposit-panel-stub").exists()).toBe(true);

      const phoneInput = wrapper.find('input[type="tel"]');
      await phoneInput.setValue("+15551234567");
      await nextTick();

      expect(readSetupRef(setupState, "depositSupportFieldErrors")).toEqual({});
      expect(phoneInput.classes()).not.toContain("input-error");
      expect(
        wrapper.find(".support-questions-inline .error-text").exists(),
      ).toBe(false);
    });
  });
});
