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

const FLASHPAY_BANKCARD_PAY_INFO = {
  payDataType: "bankcard",
  payOrderId: "FP-ORDER-1001",
  bankcard: {
    bankName: "BRI",
    accountNo: "1234567890123456",
    accountName: "UTRADA CLIENT",
    amount: "150000",
  },
};

const FLASHPAY_CODE_URL_PAY_INFO = {
  payOrderId: "FP-ORDER-2002",
  codeUrl: "https://pay.flashpay.example/qr/abc123",
};

const FLASHPAY_CODE_IMG_PAY_INFO = {
  payOrderId: "FP-ORDER-3003",
  codeImgUrl: "https://pay.flashpay.example/qr/img/abc123.png",
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
      { id: 2, gatewayKey: "flashpay", gatewayName: "FlashPay", type: "fiat" },
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

async function seedFlashPayDepositDetailsState(
  setupState,
  questions = [PHONE_QUESTION],
) {
  setSetupRef(setupState, "paymentGateways", [
    {
      id: 2,
      gatewayKey: "flashpay",
      gatewayName: "FlashPay",
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
    gatewayKey: "flashpay",
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
      paymentMethodId: 20,
      assetType: "fiat",
    },
  ]);
  setSetupRef(setupState, "depositForm", {
    gatewayKey: "flashpay",
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

describe("ClientTransactions FlashPay deposit confirm", () => {
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

  it("shows pending confirm UI and skips redirect when createDeposit returns bankcard flashpayPayInfo", async () => {
    const { wrapper } = await mountClientTransactions();
    mountedWrapper = wrapper;
    const setupState = getSetupState(wrapper);

    await seedFlashPayDepositDetailsState(setupState);

    clientTransactionService.createDeposit.mockResolvedValueOnce({
      success: true,
      data: {
        transactionReference: "DEP-FP-BANK-001",
        flashpayPayInfo: FLASHPAY_BANKCARD_PAY_INFO,
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
    expect(confirmation.reference).toBe("DEP-FP-BANK-001");
    expect(confirmation.flashpayPayInfo).toEqual(FLASHPAY_BANKCARD_PAY_INFO);

    expect(readSetupRef(setupState, "depositResultStatus")).toBe("pending");
    expect(readSetupRef(setupState, "depositResultTitle")).toBe(
      "Complete FlashPay Payment",
    );
    expect(readSetupRef(setupState, "depositResultMessage")).toContain(
      "FlashPay",
    );

    const rows = readSetupRef(setupState, "depositResultRows");
    expect(rows).toEqual(
      expect.arrayContaining([
        expect.objectContaining({
          label: "FlashPay Order",
          value: "FP-ORDER-1001",
          copyable: true,
        }),
        expect.objectContaining({ label: "Bank Info", section: true }),
        expect.objectContaining({ label: "Bank", value: "BRI" }),
        expect.objectContaining({
          label: "Account Number",
          value: "1234567890123456",
          copyable: true,
        }),
        expect.objectContaining({
          label: "Account Name",
          value: "UTRADA CLIENT",
        }),
        expect.objectContaining({ label: "Transfer Amount", value: "150000" }),
      ]),
    );

    expect(wrapper.text()).toContain("1234567890123456");
    expect(wrapper.text()).toContain("Bank Info");
    expect(wrapper.text()).toContain("Complete FlashPay Payment");
  });

  it("stores flashpayPayInfo on depositConfirmation via buildDepositConfirmation", async () => {
    const { wrapper } = await mountClientTransactions();
    mountedWrapper = wrapper;
    const setupState = getSetupState(wrapper);

    await seedFlashPayDepositDetailsState(setupState);

    const payInfo = {
      payDataType: "bankcard",
      payOrderId: "FP-BUILD-001",
      bankcard: { bankName: "BCA", accountNo: "9988776655" },
    };

    clientTransactionService.createDeposit.mockResolvedValueOnce({
      success: true,
      data: {
        transactionReference: "DEP-FP-BUILD-001",
        flashpayPayInfo: payInfo,
      },
    });

    await setupState.confirmDeposit();
    await flushPromises();

    const confirmation = readSetupRef(setupState, "depositConfirmation");
    expect(confirmation.flashpayPayInfo).toEqual(payInfo);
    expect(confirmation.vexoraPayInfo).toBeNull();
  });

  it("shows pending confirm UI when createDeposit returns codeUrl flashpayPayInfo", async () => {
    const { wrapper } = await mountClientTransactions();
    mountedWrapper = wrapper;
    const setupState = getSetupState(wrapper);

    await seedFlashPayDepositDetailsState(setupState);

    clientTransactionService.createDeposit.mockResolvedValueOnce({
      success: true,
      data: {
        transactionReference: "DEP-FP-QR-001",
        flashpayPayInfo: FLASHPAY_CODE_URL_PAY_INFO,
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
    expect(readSetupRef(setupState, "depositResultTitle")).toBe(
      "Complete FlashPay Payment",
    );

    const confirmation = readSetupRef(setupState, "depositConfirmation");
    expect(confirmation.flashpayPayInfo).toEqual(FLASHPAY_CODE_URL_PAY_INFO);

    const rows = readSetupRef(setupState, "depositResultRows");
    expect(rows).toEqual(
      expect.arrayContaining([
        expect.objectContaining({
          label: "QR Code",
          value: "https://pay.flashpay.example/qr/abc123",
          copyable: true,
        }),
      ]),
    );
    expect(wrapper.text()).toContain("https://pay.flashpay.example/qr/abc123");
  });

  it("shows pending confirm UI when createDeposit returns codeImgUrl flashpayPayInfo", async () => {
    const { wrapper } = await mountClientTransactions();
    mountedWrapper = wrapper;
    const setupState = getSetupState(wrapper);

    await seedFlashPayDepositDetailsState(setupState);

    clientTransactionService.createDeposit.mockResolvedValueOnce({
      success: true,
      data: {
        transactionReference: "DEP-FP-QR-IMG-001",
        flashpayPayInfo: FLASHPAY_CODE_IMG_PAY_INFO,
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

    const rows = readSetupRef(setupState, "depositResultRows");
    expect(rows).toEqual(
      expect.arrayContaining([
        expect.objectContaining({
          label: "QR Image URL",
          value: "https://pay.flashpay.example/qr/img/abc123.png",
          copyable: true,
        }),
      ]),
    );
  });

  it("maps flattened bankcard fields and shows Bank Info section", async () => {
    const { wrapper } = await mountClientTransactions();
    mountedWrapper = wrapper;
    const setupState = getSetupState(wrapper);

    await seedFlashPayDepositDetailsState(setupState);

    clientTransactionService.createDeposit.mockResolvedValueOnce({
      success: true,
      data: {
        transactionReference: "DEP-FP-FLAT-001",
        flashpayPayInfo: {
          payDataType: "bankcard",
          payOrderId: "FP-FLAT-001",
          bankName: "BRI",
          accountNo: "1111222233334444",
          accountName: "FLAT HOLDER",
          amount: "250000",
          expireTime: "2026-08-03T12:00:00.000Z",
          cashierLink: "https://pay.flashpay.fit/deposits/cashier/FLAT-001",
        },
      },
    });

    await setupState.confirmDeposit();
    await flushPromises();

    expect(readSetupRef(setupState, "depositView")).toBe("confirm");
    expect(readSetupRef(setupState, "depositResultStatus")).toBe("pending");

    const rows = readSetupRef(setupState, "depositResultRows");
    expect(rows).toEqual(
      expect.arrayContaining([
        expect.objectContaining({
          label: "FlashPay Order",
          value: "FP-FLAT-001",
          copyable: true,
        }),
        expect.objectContaining({ label: "Bank Info", section: true }),
        expect.objectContaining({ label: "Bank", value: "BRI" }),
        expect.objectContaining({
          label: "Account Number",
          value: "1111222233334444",
          copyable: true,
        }),
        expect.objectContaining({
          label: "Account Name",
          value: "FLAT HOLDER",
        }),
        expect.objectContaining({ label: "Transfer Amount", value: "250000" }),
        expect.objectContaining({ label: "Expires" }),
      ]),
    );

    expect(wrapper.text()).toContain("Bank Info");
    expect(wrapper.text()).toContain("1111222233334444");
    expect(wrapper.text()).not.toContain("Open Payment Page");

    const result = wrapper.findComponent({ name: "TransactionResult" });
    expect(result.exists()).toBe(true);
    const actionButtons = result.findAll(".confirm-actions > button");
    expect(actionButtons).toHaveLength(2);
    expect(actionButtons[0].text()).toContain("New Deposit");
    expect(actionButtons[0].classes()).toContain("primary-action");
    expect(actionButtons[1].text()).toContain("Transaction History");
    expect(actionButtons[1].classes()).toContain("secondary-action");
  });

  it("falls back to nested bankInfo when flattened bankcard fields are absent", async () => {
    const { wrapper } = await mountClientTransactions();
    mountedWrapper = wrapper;
    const setupState = getSetupState(wrapper);

    await seedFlashPayDepositDetailsState(setupState);

    clientTransactionService.createDeposit.mockResolvedValueOnce({
      success: true,
      data: {
        transactionReference: "DEP-FP-BANKINFO-001",
        flashpayPayInfo: {
          payDataType: "bankcard",
          payOrderId: "FP-BANKINFO-001",
          bankcard: {
            bankInfo: {
              name: "NESTED HOLDER",
              amount: "88000",
              bankNo: "9999888877776666",
              bankName: "BCA",
              expireTime: "2026-08-04T01:00:00.000Z",
            },
          },
        },
      },
    });

    await setupState.confirmDeposit();
    await flushPromises();

    expect(readSetupRef(setupState, "depositView")).toBe("confirm");

    const rows = readSetupRef(setupState, "depositResultRows");
    expect(rows).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ label: "Bank Info", section: true }),
        expect.objectContaining({ label: "Bank", value: "BCA" }),
        expect.objectContaining({
          label: "Account Number",
          value: "9999888877776666",
          copyable: true,
        }),
        expect.objectContaining({
          label: "Account Name",
          value: "NESTED HOLDER",
        }),
        expect.objectContaining({ label: "Transfer Amount", value: "88000" }),
        expect.objectContaining({ label: "Expires" }),
      ]),
    );

    expect(wrapper.text()).toContain("9999888877776666");
    expect(wrapper.text()).not.toContain("Open Payment Page");
  });

  it("prefers flattened fields over nested bankInfo when both are present", async () => {
    const { wrapper } = await mountClientTransactions();
    mountedWrapper = wrapper;
    const setupState = getSetupState(wrapper);

    await seedFlashPayDepositDetailsState(setupState);

    clientTransactionService.createDeposit.mockResolvedValueOnce({
      success: true,
      data: {
        transactionReference: "DEP-FP-NESTED-001",
        flashpayPayInfo: {
          payDataType: "bankcard",
          payOrderId: "PFIN2084175848257683458",
          bankName: "IBK Industrial Bank",
          accountNo: "15911440501017",
          accountName: "NI EKATERINA",
          amount: "148025",
          expireTime: "2026-08-03T07:28:39.528Z",
          cashierLink:
            "https://pay.flashpay.fit/deposits/cashier/PFIN2084175848257683458",
          bankcard: {
            cashier: {
              link: "https://pay.flashpay.fit/deposits/cashier/PFIN2084175848257683458",
            },
            bankInfo: {
              name: "WRONG NAME",
              amount: "1",
              bankNo: "0000000000000000",
              bankName: "WRONG BANK",
              expireTime: "2026-01-01T00:00:00.000Z",
            },
          },
        },
      },
    });

    await setupState.confirmDeposit();
    await flushPromises();

    expect(readSetupRef(setupState, "depositView")).toBe("confirm");

    const rows = readSetupRef(setupState, "depositResultRows");
    expect(rows).toEqual(
      expect.arrayContaining([
        expect.objectContaining({
          label: "Bank",
          value: "IBK Industrial Bank",
        }),
        expect.objectContaining({
          label: "Account Number",
          value: "15911440501017",
          copyable: true,
        }),
        expect.objectContaining({
          label: "Account Name",
          value: "NI EKATERINA",
        }),
        expect.objectContaining({ label: "Transfer Amount", value: "148025" }),
        expect.objectContaining({ label: "Expires" }),
      ]),
    );
    expect(rows).not.toEqual(
      expect.arrayContaining([
        expect.objectContaining({ label: "Bank", value: "WRONG BANK" }),
        expect.objectContaining({
          label: "Account Number",
          value: "0000000000000000",
        }),
      ]),
    );

    expect(wrapper.text()).toContain("15911440501017");
    expect(wrapper.text()).not.toContain("Open Payment Page");
  });

  it("maps snake_case bankcard fields into FlashPay result rows", async () => {
    const { wrapper } = await mountClientTransactions();
    mountedWrapper = wrapper;
    const setupState = getSetupState(wrapper);

    await seedFlashPayDepositDetailsState(setupState);

    clientTransactionService.createDeposit.mockResolvedValueOnce({
      success: true,
      data: {
        transactionReference: "DEP-FP-SNAKE-001",
        flashpayPayInfo: {
          payDataType: "bankcard",
          bankcard: {
            bank_name: "Mandiri",
            account_no: "5555666677778888",
            account_name: "SNAKE CASE HOLDER",
            amount: "99000",
          },
        },
      },
    });

    await setupState.confirmDeposit();
    await flushPromises();

    const rows = readSetupRef(setupState, "depositResultRows");
    expect(rows).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ label: "Bank", value: "Mandiri" }),
        expect.objectContaining({
          label: "Account Number",
          value: "5555666677778888",
          copyable: true,
        }),
        expect.objectContaining({
          label: "Account Name",
          value: "SNAKE CASE HOLDER",
        }),
        expect.objectContaining({ label: "Transfer Amount", value: "99000" }),
      ]),
    );
    expect(wrapper.text()).toContain("5555666677778888");
  });

  it("starts redirect countdown when createDeposit returns redirect without flashpayPayInfo", async () => {
    const { wrapper } = await mountClientTransactions();
    mountedWrapper = wrapper;
    const setupState = getSetupState(wrapper);

    await seedFlashPayDepositDetailsState(setupState);

    clientTransactionService.createDeposit.mockResolvedValueOnce({
      success: true,
      data: {
        transactionReference: "DEP-FP-REDIRECT-001",
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
    expect(document.body.querySelector(".modal-overlay.active")).not.toBeNull();
    expect(document.body.textContent).toContain(
      "Redirecting to payment page...",
    );
  });
});
