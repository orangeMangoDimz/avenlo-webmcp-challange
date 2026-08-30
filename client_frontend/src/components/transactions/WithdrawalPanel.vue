<template>
  <div class="withdrawal-panel withdrawal-details">
    <div class="transaction-card details-card">
      <div class="card-header details-header">
        <h3 class="card-title details-title">
          <i class="fas fa-bolt"></i>
          {{ t("transStep2WithdrawalDetails", "Step 2 - Withdrawal Details") }}
        </h3>
        <button type="button" class="btn back-button" @click="emit('back')">
          <i class="fas fa-arrow-left"></i>
          {{ t("transBack", "Back") }}
        </button>
      </div>

      <div class="details-body">
        <div
          v-if="selectedAddress || displayGatewayCard"
          class="verified-address-card"
        >
          <div class="verified-address-main">
            <div class="verified-address-icon">
              <i
                :class="displayAddress.iconClass || 'fas fa-building-columns'"
              ></i>
            </div>
            <div class="verified-address-text">
              <div class="verified-address-name">
                {{
                  displayAddress.name ||
                  t("transVerifiedAddress", "Verified address")
                }}
              </div>
              <template v-if="displayAddress.values?.length">
                <div
                  v-for="line in displayAddress.values"
                  :key="`${line.label}-${line.value}`"
                  class="verified-address-line"
                >
                  <span class="verified-address-line-label"
                    >{{ line.label }}:</span
                  >
                  <span class="verified-address-line-value">{{
                    line.value
                  }}</span>
                </div>
              </template>
              <div v-if="displayAddress.value" class="verified-address-value">
                {{ displayAddress.value }}
              </div>
            </div>
          </div>
          <span v-if="showVerifiedBadge" class="verified-address-badge">
            <i class="fas fa-shield-alt"></i>
            {{ t("transVerified", "Verified") }}
          </span>
        </div>

        <div v-if="gatewayContentHtml" class="gateway-content-card">
          <div class="gateway-content-title">
            {{ t("transPaymentDetails", "Payment Details") }}
          </div>
          <div class="gateway-content" v-html="gatewayContentHtml"></div>
        </div>

        <form class="details-form" @submit.prevent="handleSubmit">
          <div v-if="currencies.length" class="form-group">
            <label class="section-label">
              <i class="fas fa-coins"></i>
              {{
                assetType === "fiat"
                  ? t("transSupportedCurrency", "Supported Currency")
                  : t("transSelectCryptocurrency", "Cryptocurrency")
              }}
            </label>
            <div class="currency-grid">
              <button
                v-for="currency in currencies"
                :key="currency.id"
                type="button"
                :class="[
                  'currency-card',
                  { selected: selectedCurrency === currency.id },
                ]"
                @click="emit('select-currency', currency.id)"
              >
                <div class="currency-card-icon">
                  <CurrencyLogo
                    :code="currency.shortCode || currency.code || currency.id"
                    :asset-type="assetType"
                    :symbol="currency.currencySymbol"
                    :icon-class="currency.iconClass || 'fas fa-coins'"
                    :alt="`${currency.methodName || currency.name || currency.code || currency.id} logo`"
                  />
                </div>
                <div class="currency-card-text">
                  <div class="currency-card-name">
                    {{ currency.methodName || currency.name }}
                  </div>
                  <div class="currency-card-code">
                    {{
                      currency.shortCode ||
                      currency.code ||
                      t("transAsset", "Asset")
                    }}
                  </div>
                </div>
              </button>
            </div>
          </div>

          <div v-else-if="!currencyLoading" class="form-group">
            <label class="section-label">
              <i class="fas fa-coins"></i>
              {{
                assetType === "fiat"
                  ? t("transSupportedCurrency", "Supported Currency")
                  : t("transSelectCryptocurrency", "Cryptocurrency")
              }}
            </label>
            <div class="empty-currency-state">{{ currencyEmptyText }}</div>
          </div>

          <div class="form-group">
            <label class="section-label">
              <i class="fas fa-coins"></i>
              {{ t("transWithdrawalSource", "Withdrawal Source") }}
            </label>
            <div ref="sourceAccountSelectRef" class="details-select-shell">
              <button
                type="button"
                :class="[
                  'details-select-trigger',
                  {
                    placeholder: !withdrawForm.sourceAccountType,
                    open: showSourceAccountDropdown,
                  },
                ]"
                @click="toggleSourceAccountDropdown"
              >
                <span>{{
                  selectedSourceAccountLabel ||
                  t("transSelectSourceAccount", "Select source account")
                }}</span>
                <i class="fas fa-chevron-down"></i>
              </button>
              <div v-if="showSourceAccountDropdown" class="details-select-menu">
                <button
                  type="button"
                  :class="[
                    'details-select-option',
                    { selected: withdrawForm.sourceAccountType === 'wallet' },
                  ]"
                  @click="selectSourceAccount('wallet')"
                >
                  {{ t("transWalletBalance", "Wallet") }} -
                  {{
                    formatBalanceValue(accountBalance, {
                      unit: "USD",
                      scale: 1,
                    })
                  }}
                </button>
                <template
                  v-for="group in groupedTradingAccounts"
                  :key="group.label"
                >
                  <div class="details-select-group">{{ group.label }}</div>
                  <button
                    v-for="account in group.accounts"
                    :key="account.id"
                    type="button"
                    :class="[
                      'details-select-option',
                      {
                        selected:
                          withdrawForm.sourceAccountType ===
                          `trading_${account.id}`,
                      },
                    ]"
                    @click="selectSourceAccount(`trading_${account.id}`)"
                  >
                    {{ account.accountNickname }} ({{ account.accountNumber }})
                    -
                    {{
                      formatBalanceValue(account.availableBalance || 0, account)
                    }}
                  </button>
                </template>
              </div>
            </div>
            <div v-if="selectedBalanceSource" class="account-balance-preview">
              <div class="account-balance-preview-title">
                {{ t("transBalanceImpact", "Balance Impact") }}
              </div>
              <div class="account-balance-preview-row">
                <span>{{ t("transSelectedAccount", "Selected Account") }}</span>
                <span>{{ selectedBalanceSource.label }}</span>
              </div>
              <div class="account-balance-preview-row">
                <span>{{ t("transCurrentBalance", "Current Balance") }}</span>
                <span>{{
                  formatBalanceValue(
                    balanceBeforeWithdrawal,
                    selectedBalanceSource,
                  )
                }}</span>
              </div>
              <div class="account-balance-preview-row">
                <span>{{
                  t("transWithdrawalAmountLabel", "Withdrawal Amount")
                }}</span>
                <span>{{
                  formatImpactValue(normalizedAmount, selectedBalanceSource)
                }}</span>
              </div>
              <div class="account-balance-preview-row strong">
                <span>{{
                  t("transBalanceAfterWithdrawal", "Balance After Withdrawal")
                }}</span>
                <span>{{
                  formatBalanceValue(
                    balanceAfterWithdrawal,
                    selectedBalanceSource,
                  )
                }}</span>
              </div>
            </div>
          </div>

          <div class="form-group">
            <label class="section-label">
              <i class="fas fa-dollar-sign"></i>
              {{ t("transWithdrawalAmountUsd", "Withdrawal Amount (USD)") }}
            </label>
            <div class="amount-input-shell">
              <div class="amount-prefix">$</div>
              <FormattedNumberInput
                :model-value="withdrawForm.amount"
                :decimals="2"
                :max="maxWithdrawAmount"
                :disabled="!withdrawForm.sourceAccountType"
                placeholder="0.00"
                input-class="details-amount-input"
                @update:model-value="updateAmount"
              />
            </div>
            <div class="quick-amounts">
              <button
                v-for="amount in quickAmountOptions"
                :key="`withdraw-quick-${amount}`"
                type="button"
                class="quick-amount-chip"
                @click="setAmount(amount)"
              >
                {{ formatQuickAmount(amount) }}
              </button>
              <button
                type="button"
                class="quick-amount-chip"
                @click="setAmount(maxWithdrawAmount)"
              >
                {{ t("transUseAvailableAmount", "Use Available") }}
              </button>
            </div>
            <div v-if="selectedBalanceSource" class="amount-range-hint">
              <span
                >{{ t("transAvailable", "Available:") }}
                {{ availableUsdDisplay }}</span
              >
            </div>
            <div v-if="hasAmountRange" class="amount-range-hint">
              <span v-if="minAmount != null && maxAmount != null">
                {{ t("transWithdrawalAmountRange", "Amount range") }}:
                {{ formatCurrency(minAmount) }} -
                {{ formatCurrency(maxAmount) }}
              </span>
              <span v-else-if="minAmount != null">
                {{ t("transWithdrawalMinimumAmount", "Minimum withdrawal") }}:
                {{ formatCurrency(minAmount) }}
              </span>
              <span v-else-if="maxAmount != null">
                {{ t("transWithdrawalMaximumAmount", "Maximum withdrawal") }}:
                {{ formatCurrency(maxAmount) }}
              </span>
            </div>
          </div>

          <div class="summary-card">
            <div class="summary-row">
              <span>{{
                t("transWithdrawalAmountLabel", "Withdrawal Amount")
              }}</span>
              <span>{{ formatCurrency(normalizedAmount) }}</span>
            </div>
            <div class="summary-row">
              <span>{{ t("transProcessingFee", "Processing Fee") }}</span>
              <span>{{ formatCurrency(feeSummary.feeAmount) }}</span>
            </div>
            <div
              v-if="displayCurrency !== 'USD' && exchangeRate > 0"
              class="summary-row"
            >
              <span>{{ t("transExchangeRate", "Exchange Rate") }}</span>
              <span
                >1 USD =
                {{
                  formatSettlementAmount(exchangeRate, displayCurrency)
                }}</span
              >
            </div>
            <div class="summary-row total">
              <span>{{ t("transTotalAmount", "Total Amount") }}</span>
              <span>{{
                formatSettlementAmount(
                  totalAmount,
                  displayCurrency,
                  feeSummary.amountDecimalPlaces,
                )
              }}</span>
            </div>
            <div class="summary-row">
              <span>{{ t("transMethod", "Method") }}</span>
              <span>{{
                templateMeta?.gatewayName ||
                selectedAddress?.name ||
                t("transWithdraw", "Withdrawal")
              }}</span>
            </div>
            <div v-if="selectedCurrencyInfo" class="summary-row">
              <span>{{
                assetType === "fiat"
                  ? t("transCurrency", "Currency")
                  : t("transNetwork", "Network")
              }}</span>
              <span>{{
                selectedCurrencyInfo.methodName ||
                selectedCurrencyInfo.shortCode ||
                selectedCurrencyInfo.code
              }}</span>
            </div>
          </div>

          <div
            v-if="
              securitySettings.withdrawalOtpRequired &&
              !otpVerificationStatus.isVerified
            "
            class="otp-card"
          >
            <div class="otp-card-header">
              <i class="fas fa-shield-alt"></i>
              <div>
                <strong>{{
                  t(
                    "transSecurityVerificationRequired",
                    "Security Verification Required",
                  )
                }}</strong>
                <p>
                  {{
                    t(
                      "transSecurityVerificationDesc",
                      "For your security, please verify your identity before proceeding with withdrawal.",
                    )
                  }}
                </p>
              </div>
            </div>

            <div class="otp-controls">
              <input
                type="text"
                class="form-input otp-input"
                :value="otpVerificationStatus.otpCode"
                :placeholder="
                  t('transEnterVerificationCode', 'Enter verification code')
                "
                maxlength="6"
                @input="updateOtpCode"
              />
              <button
                type="button"
                class="btn otp-button secondary"
                :disabled="
                  submitting ||
                  (otpVerificationStatus.otpSent &&
                    otpVerificationStatus.remainingTime > 0)
                "
                @click="requestOTP"
              >
                <i
                  :class="
                    submitting ? 'fas fa-spinner fa-spin' : 'fas fa-envelope'
                  "
                ></i>
                {{
                  submitting
                    ? t("transSending", "Sending...")
                    : otpVerificationStatus.otpSent
                      ? t("transResend", "Resend")
                      : t("transSendCode", "Send Code")
                }}
              </button>
              <button
                type="button"
                class="btn otp-button primary"
                :disabled="
                  submitting || otpVerificationStatus.otpCode.length !== 6
                "
                @click="verifyOTP"
              >
                <i
                  :class="
                    submitting ? 'fas fa-spinner fa-spin' : 'fas fa-check'
                  "
                ></i>
                {{
                  submitting
                    ? t("transVerifying", "Verifying...")
                    : t("transVerify", "Verify")
                }}
              </button>
            </div>
          </div>

          <!-- 支持问题（来自 gateway 配置）：原本在弹窗里，现在移到 Confirm 按钮上方让用户先填 -->
          <div
            v-if="usesSupportDataFlow && supportQuestions.length > 0"
            class="support-questions-inline"
          >
            <div class="support-questions-title">
              <i class="fas fa-circle-info"></i>
              <span>{{
                t("transWithdrawalDetails", "Withdrawal Details")
              }}</span>
            </div>
            <div
              v-for="question in supportQuestions"
              :key="question.id"
              class="support-question"
            >
              <label class="support-question-label">
                {{ questionLabel(question) }}
              </label>

              <input
                v-if="isTextQuestion(question)"
                v-model="supportDataForm[question.name]"
                type="text"
                class="form-input support-input"
                :placeholder="question.hintText || ''"
              />

              <CustomSelect
                v-else-if="isSingleChoiceQuestion(question)"
                v-model="supportDataForm[question.name]"
                :options="
                  normalizedQuestionOptions(question).map((o) => ({
                    label: o.label,
                    value: o.id,
                  }))
                "
                :placeholder="t('transPleaseSelectOption', 'Please select')"
                :searchable="normalizedQuestionOptions(question).length > 5"
                :search-placeholder="t('transSearchOption', 'Search...')"
              />

              <textarea
                v-else
                v-model="supportDataForm[question.name]"
                class="form-input support-input support-textarea"
                :placeholder="question.hintText || ''"
                rows="3"
              ></textarea>

              <div v-if="question.hintText" class="support-question-hint">
                {{ question.hintText }}
              </div>
            </div>
          </div>

          <div v-if="displayContents.length" class="warning-banner-list">
            <div
              v-for="(item, index) in displayContents"
              :key="`${item.content || 'content'}-${index}`"
              class="warning-banner"
            >
              <i class="fas fa-triangle-exclamation"></i>
              <div class="warning-banner-text">
                <p>{{ item.content }}</p>
              </div>
            </div>
          </div>

          <button
            type="submit"
            class="btn withdraw-button"
            :disabled="
              submitting ||
              !canSubmit ||
              (securitySettings.withdrawalOtpRequired &&
                !otpVerificationStatus.isVerified)
            "
          >
            <i
              :class="submitting ? 'fas fa-spinner fa-spin' : 'fas fa-arrow-up'"
            ></i>
            {{
              submitting
                ? t("transProcessing", "Processing...")
                : t("transWithdrawNow", "Withdraw Now")
            }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import {
  computed,
  onBeforeUnmount,
  onMounted,
  reactive,
  ref,
  watch,
} from "vue";
import FormattedNumberInput from "../common/FormattedNumberInput.vue";
import CurrencyLogo from "./CurrencyLogo.vue";
import CustomSelect from "../common/CustomSelect.vue";

const props = defineProps({
  t: { type: Function, required: true },
  securitySettings: { type: Object, required: true },
  otpVerificationStatus: { type: Object, required: true },
  withdrawForm: { type: Object, required: true },
  accountBalance: { type: Number, required: true },
  tradingAccounts: { type: Array, default: () => [] },
  withdrawalInfos: { type: Array, default: () => [] },
  submitting: { type: Boolean, default: false },
  currencies: { type: Array, default: () => [] },
  displayContents: { type: Array, default: () => [] },
  gatewayContentHtml: { type: String, default: "" },
  assetType: { type: String, default: "crypto" },
  selectedCurrency: { type: [String, Number, null], default: null },
  currencyLoading: { type: Boolean, default: false },
  currencyEmptyText: { type: String, default: "" },
  minAmount: { type: [Number, String, null], default: null },
  maxAmount: { type: [Number, String, null], default: null },
  quickAmounts: { type: Array, default: () => [] },
  feeSummary: {
    type: Object,
    default: () => ({
      feeAmount: 0,
      totalAmount: 0,
    }),
  },
  exchangeRate: { type: Number, default: 1 },
  displayCurrency: { type: String, default: "USD" },
  requestOTP: { type: Function, required: true },
  verifyOTP: { type: Function, required: true },
  onSourceAccountChange: { type: Function, required: true },
  handleBSBInput: { type: Function, required: true },
  handleAccountNumberInput: { type: Function, required: true },
  handleWithdrawal: { type: Function, required: true },
  formatTime: { type: Function, required: true },
  formatCurrency: { type: Function, required: true },
  formatSettlementAmount: { type: Function, required: true },
  formatBSB: { type: Function, required: true },
  maskAccountNumber: { type: Function, required: true },
  selectedAddress: {
    type: Object,
    default: null,
  },
  templateMeta: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(["back", "select-currency"]);
const supportDataForm = reactive({});
const showSourceAccountDropdown = ref(false);
const sourceAccountSelectRef = ref(null);

const displayGatewayCard = computed(() =>
  Boolean(props.templateMeta?.gatewayName || props.templateMeta?.iconClass),
);

const sanitizeDisplayValue = (value) => {
  const rawValue = String(value || "").trim();
  if (!rawValue) return "";

  const hasHtmlLikeContent =
    /<\/?[a-z][^>]*>/i.test(rawValue) || /&[a-z#0-9]+;/i.test(rawValue);
  const normalizedPlainText = rawValue
    .replace(/<br\s*\/?>/gi, "\n")
    .replace(/<\/p>|<\/div>|<\/li>|<\/h[1-6]>/gi, "\n")
    .replace(/<[^>]+>/g, " ")
    .replace(/&nbsp;/gi, " ")
    .replace(/&amp;/gi, "&")
    .replace(/\s+/g, " ")
    .trim();

  if (!hasHtmlLikeContent) {
    return normalizedPlainText;
  }

  const emailMatch = rawValue.match(/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i);
  if (emailMatch) {
    return emailMatch[0];
  }

  const urlMatch = rawValue.match(/https?:\/\/[^\s<]+/i);
  if (urlMatch) {
    return urlMatch[0];
  }

  const firstLine = rawValue.split(/<br\s*\/?>|\n/i)[0]?.trim() || "";
  if (firstLine && !/[<>]/.test(firstLine)) {
    return firstLine;
  }

  return "";
};

const displayAddress = computed(() => {
  const selectedAddress = props.selectedAddress || null;

  if (!selectedAddress) {
    return {
      name:
        props.templateMeta?.gatewayName ||
        props.t("transWithdraw", "Withdrawal"),
      value: "",
      iconClass: props.templateMeta?.iconClass || "fas fa-building-columns",
    };
  }

  return {
    ...selectedAddress,
    value: sanitizeDisplayValue(selectedAddress.value),
  };
});

const showVerifiedBadge = computed(
  () => props.selectedAddress?.submissionStatus === "approved",
);
const usesSupportDataFlow = computed(() => {
  const rawValue = props.templateMeta?.prewithdrawenabled;

  if (
    rawValue === false ||
    rawValue === 0 ||
    rawValue === "0" ||
    rawValue === "false"
  ) {
    return true;
  }

  if (
    rawValue === true ||
    rawValue === 1 ||
    rawValue === "1" ||
    rawValue === "true"
  ) {
    return false;
  }

  return !props.selectedAddress;
});
const supportQuestions = computed(() =>
  Array.isArray(props.templateMeta?.questions)
    ? props.templateMeta.questions.filter((question) => {
        const questionScope = String(question?.scope || "").toLowerCase();

        if (questionScope) {
          return questionScope === "withdraw";
        }

        return (
          Number(question?.withdrawEnabled) === 1 ||
          question?.withdrawEnabled === true
        );
      })
    : [],
);

const updateOtpCode = (event) => {
  // eslint-disable-next-line vue/no-mutating-props
  props.otpVerificationStatus.otpCode = event.target.value.replace(/\D/g, "");
};

const selectSourceAccount = async (value) => {
  // eslint-disable-next-line vue/no-mutating-props
  props.withdrawForm.sourceAccountType = value;
  showSourceAccountDropdown.value = false;
  await props.onSourceAccountChange();
};

const extractTradingAccountIdFromSelection = (selectionValue) => {
  const normalizedSelection = String(selectionValue || "").trim();
  if (!normalizedSelection.startsWith("trading_")) return "";
  return String(normalizedSelection.replace("trading_", "")).trim();
};

const groupedTradingAccounts = computed(() => {
  const groups = new Map();

  props.tradingAccounts.forEach((account) => {
    const label =
      account.platformName ||
      account.platformCode ||
      account.platformKey ||
      props.t("transTradingAccounts", "Trading Accounts");
    if (!groups.has(label)) {
      groups.set(label, []);
    }
    groups.get(label).push(account);
  });

  return Array.from(groups.entries()).map(([label, accounts]) => ({
    label,
    accounts,
  }));
});

const selectedSourceAccountLabel = computed(() => {
  if (props.withdrawForm.sourceAccountType === "wallet") {
    return `${props.t("transWalletBalance", "Wallet")} - ${formatBalanceValue(props.accountBalance, { unit: "USD", scale: 1 })}`;
  }

  const matchedAccount = props.tradingAccounts.find(
    (account) =>
      `trading_${account.id}` === props.withdrawForm.sourceAccountType,
  );
  if (!matchedAccount) return "";
  return `${matchedAccount.accountNickname} (${matchedAccount.accountNumber}) - ${formatBalanceValue(matchedAccount.availableBalance || 0, matchedAccount)}`;
});

const toggleSourceAccountDropdown = () => {
  showSourceAccountDropdown.value = !showSourceAccountDropdown.value;
};

const handleClickOutside = (event) => {
  if (!sourceAccountSelectRef.value?.contains(event.target)) {
    showSourceAccountDropdown.value = false;
  }
};

const selectedTradingAccount = computed(() => {
  if (!props.withdrawForm.sourceAccountType?.startsWith("trading_"))
    return null;
  const accountId = extractTradingAccountIdFromSelection(
    props.withdrawForm.sourceAccountType,
  );
  return (
    props.tradingAccounts.find((account) => String(account.id) === accountId) ||
    null
  );
});

const resolveDisplayUnit = (source = {}) =>
  String(
    source?.groupUnit ||
      source?.unit ||
      source?.accountCurrency ||
      source?.currency ||
      "",
  )
    .trim()
    .toUpperCase();

const resolveDisplayScale = (source = {}) => {
  const scale = Number(source?.groupScale ?? source?.scale ?? 1);
  return Number.isFinite(scale) && scale > 0 ? scale : 1;
};

const formatScaledNumber = (value) =>
  Number(value || 0).toLocaleString("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });

const formatDisplayValue = (amount, unit = "") => {
  const normalizedValue = Number(amount || 0);
  const normalizedUnit = String(unit || "")
    .trim()
    .toUpperCase();

  if (normalizedUnit === "USD") {
    return `${props.formatCurrency(normalizedValue)} USD`;
  }

  if (!normalizedUnit) {
    return formatScaledNumber(normalizedValue);
  }

  return `${formatScaledNumber(normalizedValue)} ${normalizedUnit}`;
};

const formatBalanceValue = (amount, source = {}) =>
  formatDisplayValue(amount, resolveDisplayUnit(source));

const formatImpactValue = (amount, source = {}) => {
  const scaledImpact = Number(amount || 0) * resolveDisplayScale(source);
  return formatDisplayValue(scaledImpact, resolveDisplayUnit(source));
};

const formatAvailableUsd = (amount, source = {}) => {
  const scale = resolveDisplayScale(source);
  const normalizedAmount = Number(amount || 0) / scale;
  return `${props.formatCurrency(normalizedAmount)} USD`;
};

const resolveAvailableUsdValue = (amount, source = {}) => {
  const scale = resolveDisplayScale(source);
  return Number(amount || 0) / scale;
};

const selectedBalanceSource = computed(() => {
  if (props.withdrawForm.sourceAccountType === "wallet") {
    return {
      label: props.t("transWalletBalance", "Wallet"),
      unit: "USD",
      scale: 1,
      before: Number(props.accountBalance || 0),
    };
  }

  if (selectedTradingAccount.value) {
    return {
      label: `${selectedTradingAccount.value.accountNickname} (${selectedTradingAccount.value.accountNumber})`,
      unit: resolveDisplayUnit(selectedTradingAccount.value),
      scale: resolveDisplayScale(selectedTradingAccount.value),
      groupUnit: selectedTradingAccount.value.groupUnit,
      groupScale: selectedTradingAccount.value.groupScale,
      accountCurrency: selectedTradingAccount.value.accountCurrency,
      before: Number(selectedTradingAccount.value.availableBalance || 0),
    };
  }

  return null;
});

const maxWithdrawAmount = computed(() => {
  if (props.withdrawForm.sourceAccountType === "wallet") {
    return Math.max(
      resolveAvailableUsdValue(props.accountBalance || 0, {
        unit: "USD",
        scale: 1,
      }),
      0,
    );
  }

  return Math.max(
    resolveAvailableUsdValue(
      selectedTradingAccount.value?.availableBalance || 0,
      selectedTradingAccount.value || {},
    ),
    0,
  );
});

const availableUsdDisplay = computed(() => {
  if (!selectedBalanceSource.value) {
    return formatAvailableUsd(0, { unit: "USD", scale: 1 });
  }

  return formatAvailableUsd(
    selectedBalanceSource.value.before || 0,
    selectedBalanceSource.value,
  );
});

const normalizedAmount = computed(() => Number(props.withdrawForm.amount || 0));
const totalAmount = computed(() =>
  Number(props.feeSummary?.totalAmount || normalizedAmount.value || 0),
);
const balanceBeforeWithdrawal = computed(() =>
  Number(selectedBalanceSource.value?.before || 0),
);
const balanceAfterWithdrawal = computed(() =>
  Math.max(
    balanceBeforeWithdrawal.value -
      normalizedAmount.value * resolveDisplayScale(selectedBalanceSource.value),
    0,
  ),
);
const hasAmountRange = computed(
  () => props.minAmount != null || props.maxAmount != null,
);
const quickAmountOptions = computed(() => {
  const list = Array.isArray(props.quickAmounts) ? props.quickAmounts : [];
  return list
    .map((item) =>
      typeof item === "number" || typeof item === "string"
        ? Number(item)
        : Number(item?.amount),
    )
    .filter((amount) => Number.isFinite(amount) && amount > 0)
    .sort((a, b) => a - b);
});

const formatQuickAmount = (amount) => {
  const value = Number(amount);
  if (!Number.isFinite(value)) return props.formatCurrency(0);
  const fractionDigits = Number.isInteger(value) ? 0 : 2;
  return `$${value.toLocaleString("en-US", {
    minimumFractionDigits: fractionDigits,
    maximumFractionDigits: fractionDigits,
  })}`;
};

const selectedCurrencyInfo = computed(
  () =>
    props.currencies.find(
      (currency) => currency.id === props.selectedCurrency,
    ) || null,
);
const canSubmit = computed(() => Boolean(props.selectedCurrency));

const setAmount = (value) => {
  // 未选出金来源时，点快捷金额先提示选择来源
  if (!props.withdrawForm.sourceAccountType) {
    alert(props.t("transAlertSelectSource", "Please select a source"));
    return;
  }

  const safeValue = Number(value || 0);
  if (!(safeValue > 0)) {
    // eslint-disable-next-line vue/no-mutating-props
    props.withdrawForm.amount = null;
    return;
  }

  // eslint-disable-next-line vue/no-mutating-props
  props.withdrawForm.amount = Math.min(
    safeValue,
    Number(maxWithdrawAmount.value || safeValue),
  );
};

const updateAmount = (value) => {
  // eslint-disable-next-line vue/no-mutating-props
  props.withdrawForm.amount = value;
};

const normalizedQuestionOptions = (question) => {
  if (Array.isArray(question?.options)) {
    return question.options
      .map((option, index) => {
        if (typeof option === "string") {
          return {
            id: `option-${index}`,
            label: option,
            value: option,
          };
        }

        const label = String(option?.label || "").trim();
        if (!label) {
          return null;
        }

        return {
          id:
            option?.id != null && option.id !== ""
              ? String(option.id)
              : `option-${index}`,
          label,
          value: String(option?.value ?? ""),
        };
      })
      .filter(Boolean);
  }

  if (typeof question?.options === "string" && question.options.trim()) {
    try {
      const parsed = JSON.parse(question.options);
      return Array.isArray(parsed)
        ? parsed
            .map((option, index) => {
              if (typeof option === "string") {
                return {
                  id: `option-${index}`,
                  label: option,
                  value: option,
                };
              }

              const label = String(option?.label || "").trim();
              if (!label) {
                return null;
              }

              return {
                id:
                  option?.id != null && option.id !== ""
                    ? String(option.id)
                    : `option-${index}`,
                label,
                value: String(option?.value ?? ""),
              };
            })
            .filter(Boolean)
        : [];
    } catch {
      return [];
    }
  }

  return [];
};

const toTitleCase = (value) =>
  String(value || "")
    .replace(/[_-]+/g, " ")
    .replace(/\s+/g, " ")
    .trim()
    .replace(/\b\w/g, (char) => char.toUpperCase());

const questionLabel = (question) =>
  toTitleCase(
    question?.label || question?.title || question?.name || "Question",
  );
const isTextQuestion = (question) =>
  ["text", "email", "tel", "phone"].includes(
    String(question?.questionType || "").toLowerCase(),
  );
const isSingleChoiceQuestion = (question) =>
  ["single_choice", "select", "radio"].includes(
    String(question?.questionType || "").toLowerCase(),
  );

const hasSupportQuestionAnswer = (question) => {
  const rawValue = supportDataForm[question.name];
  if (isSingleChoiceQuestion(question)) {
    return rawValue !== "" && rawValue !== null && rawValue !== undefined;
  }

  return Boolean(String(rawValue || "").trim());
};

const getSupportQuestionSubmitValue = (question) => {
  const rawValue = supportDataForm[question.name];

  if (isSingleChoiceQuestion(question)) {
    const selectedOption = normalizedQuestionOptions(question).find(
      (option) => String(option.id) === String(rawValue),
    );

    return selectedOption ? selectedOption.value : "";
  }

  return String(rawValue || "").trim();
};

const handleSubmit = async () => {
  // 支持问题已经在 form 内联渲染，提交前先做必填校验，再带 supportData 一起调 handleWithdrawal
  if (usesSupportDataFlow.value && supportQuestions.value.length > 0) {
    for (const question of supportQuestions.value) {
      const rules = String(question.validationRules || "");
      if (rules.includes("required") && !hasSupportQuestionAnswer(question)) {
        alert(
          `${props.t("transPleaseFillIn", "Please fill in")} ${questionLabel(question)}.`,
        );
        return;
      }
    }

    await props.handleWithdrawal({
      gatewaySettingId: props.templateMeta?.gatewaySettingId || null,
      withdrawalSubmissionId: null,
      supportData: supportQuestions.value.reduce((acc, question) => {
        acc[question.name] = getSupportQuestionSubmitValue(question);
        return acc;
      }, {}),
    });
    return;
  }

  await props.handleWithdrawal({
    gatewaySettingId: props.templateMeta?.gatewaySettingId || null,
    withdrawalSubmissionId: props.selectedAddress?.submissionId || null,
    supportData: usesSupportDataFlow.value ? {} : null,
  });
};

watch(
  supportQuestions,
  (questions) => {
    Object.keys(supportDataForm).forEach((key) => {
      delete supportDataForm[key];
    });

    questions.forEach((question) => {
      supportDataForm[question.name] = "";
    });
  },
  { immediate: true },
);

onMounted(() => {
  document.addEventListener("click", handleClickOutside, true);
});

onBeforeUnmount(() => {
  document.removeEventListener("click", handleClickOutside, true);
});
</script>

<style scoped>
.withdrawal-details {
  width: 100%;
}

.details-card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 22px;
  box-shadow: 0 20px 50px rgba(15, 23, 42, 0.08);
  overflow: hidden;
}

.details-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 22px 26px;
  border-bottom: 1px solid var(--color-border);
}

.details-title {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  color: var(--color-ink);
}

.details-title i {
  color: var(--color-brand);
}

.back-button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface-soft);
  color: var(--color-text);
  padding: 10px 16px;
  font-size: 15px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s ease;
}

.back-button:hover {
  background: var(--color-surface-muted);
  border-color: var(--color-border);
}

.details-body {
  padding: 26px;
}

.details-select-shell {
  position: relative;
}

.details-select-trigger {
  width: 100%;
  min-height: 48px;
  padding: 12px 14px;
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  color: var(--color-ink);
  font-size: 14px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  cursor: pointer;
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease;
  text-align: left;
}

.details-select-trigger.placeholder {
  color: var(--color-faint);
}

.details-select-trigger.open,
.details-select-trigger:focus {
  border-color: var(--color-brand);
  box-shadow: none;
}

.details-select-trigger i {
  color: var(--color-muted);
  font-size: 13px;
  flex-shrink: 0;
}

.details-select-menu {
  position: absolute;
  left: 0;
  right: 0;
  top: calc(100% + 8px);
  z-index: 20;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 14px;
  box-shadow: 0 18px 35px rgba(15, 23, 42, 0.12);
  overflow: hidden;
  max-height: 260px;
  overflow-y: auto;
}

.details-select-group {
  padding: 10px 14px 6px;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--color-faint);
  background: var(--color-surface-soft);
}

.details-select-option {
  width: 100%;
  border: 0;
  background: var(--color-surface);
  padding: 11px 14px;
  text-align: left;
  font-size: 13px;
  color: var(--color-ink);
  cursor: pointer;
  transition:
    background 0.2s ease,
    color 0.2s ease;
}

.details-select-option:hover,
.details-select-option.selected {
  background: var(--color-brand-soft);
  color: var(--color-brand-strong);
}

.account-balance-preview {
  margin-top: 12px;
  padding: 14px 16px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface-soft);
}

.account-balance-preview-title {
  margin-bottom: 10px;
  font-size: 13px;
  font-weight: 700;
  color: var(--color-text);
}

.account-balance-preview-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  font-size: 13px;
  color: var(--color-text);
}

.account-balance-preview-row + .account-balance-preview-row {
  margin-top: 6px;
}

.account-balance-preview-row.strong {
  color: var(--color-brand);
  font-weight: 700;
}

.verified-address-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  padding: 16px 18px;
  border: 1px solid var(--color-success-border, var(--color-success));
  border-radius: 14px;
  background: var(--color-success-soft);
  margin-bottom: 20px;
}

.verified-address-main {
  display: flex;
  align-items: center;
  gap: 14px;
  min-width: 0;
}

.verified-address-icon {
  width: 42px;
  height: 42px;
  border-radius: var(--radius-lg);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--color-success);
  background: rgba(45, 160, 88, 0.12);
  font-size: 18px;
}

.verified-address-name {
  font-size: 15px;
  font-weight: 800;
  color: var(--color-ink);
}

.verified-address-value {
  margin-top: 4px;
  color: var(--color-muted);
  font-size: 14px;
}

.verified-address-line {
  margin-top: 4px;
  color: var(--color-muted);
  font-size: 14px;
  line-height: 1.6;
}

.verified-address-line-label {
  font-weight: 700;
  color: var(--color-text);
  margin-right: 6px;
}

.verified-address-line-value {
  word-break: break-word;
}

.gateway-content {
  color: var(--color-text);
  font-size: 14px;
  line-height: 1.7;
}

.gateway-content-card {
  margin-bottom: 20px;
  padding: 16px 18px;
  border: 1px solid var(--color-border);
  border-radius: 14px;
  background: var(--color-surface-soft);
}

.gateway-content-title {
  margin-bottom: 10px;
  color: var(--color-ink);
  font-size: 13px;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.gateway-content :deep(p) {
  margin: 0 0 8px;
}

.gateway-content :deep(p:last-child) {
  margin-bottom: 0;
}

.gateway-content :deep(ul),
.gateway-content :deep(ol) {
  margin: 8px 0 0;
  padding-left: 20px;
}

.gateway-content :deep(li) {
  margin-bottom: 4px;
}

.gateway-content :deep(a) {
  color: var(--color-brand);
  text-decoration: underline;
}

.verified-address-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  white-space: nowrap;
  border-radius: 999px;
  padding: 7px 12px;
  font-size: 13px;
  font-weight: 800;
  color: var(--color-success);
  background: var(--color-success-soft);
}

.details-form {
  display: flex;
  flex-direction: column;
  gap: 22px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.section-label {
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--color-text);
  font-size: 14px;
  font-weight: 800;
}

.section-label i {
  color: var(--color-brand);
}

.details-select {
  width: 100%;
  min-height: 56px;
  border-radius: var(--radius-lg);
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  padding: 0 16px;
  font-size: 15px;
  color: var(--color-ink);
}

.details-select:focus {
  border-color: var(--color-border);
  box-shadow: none;
}

.currency-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}

.currency-card {
  display: flex;
  align-items: center;
  gap: 12px;
  border: 1px solid var(--color-border);
  border-radius: 14px;
  background: var(--color-surface);
  padding: 14px;
  text-align: left;
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    transform 0.2s ease;
}

.currency-card.selected {
  border-color: var(--color-brand);
  box-shadow: 0 12px 24px rgba(var(--color-brand-rgb), 0.12);
  transform: translateY(-1px);
}

.currency-card-icon {
  width: 44px;
  height: 44px;
  flex: 0 0 44px;
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.currency-symbol {
  font-size: 18px;
  font-weight: 800;
  line-height: 1;
}

.currency-card-text {
  min-width: 0;
}

.currency-card-name {
  color: var(--color-ink);
  font-weight: 800;
}

.currency-card-code {
  margin-top: 4px;
  color: var(--color-muted);
  font-size: 13px;
}

.amount-input-shell {
  position: relative;
}

.amount-prefix {
  position: absolute;
  left: 18px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-text);
  font-size: 18px;
  font-weight: 800;
  z-index: 1;
}

.amount-input-shell :deep(.details-amount-input) {
  width: 100%;
  min-height: 58px;
  border-radius: var(--radius-lg);
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  padding: 0 18px 0 42px;
  font-size: 18px;
  font-weight: 800;
  color: var(--color-ink);
}

.amount-input-shell :deep(.details-amount-input:disabled) {
  background: var(--color-surface-soft);
  color: var(--color-faint);
  cursor: not-allowed;
}

.amount-input-shell :deep(.details-amount-input:focus) {
  border-color: var(--color-border);
  box-shadow: none;
}

.amount-hint {
  color: var(--color-muted);
  font-size: 14px;
  font-weight: 600;
}

.quick-amounts {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.amount-range-hint {
  display: flex;
  flex-wrap: wrap;
  gap: 14px;
  margin-top: 10px;
  color: var(--color-muted);
  font-size: 14px;
  font-weight: 600;
}

.empty-currency-state {
  padding: 14px 16px;
  border: 1px dashed var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface-soft);
  color: var(--color-muted);
  font-size: 14px;
}

.quick-amount-chip {
  border: 1px solid var(--color-border);
  border-radius: 999px;
  background: var(--color-surface);
  color: var(--color-text);
  font-size: 14px;
  font-weight: 800;
  padding: 8px 16px;
  cursor: pointer;
}

.quick-amount-chip:hover {
  border-color: var(--color-border);
  color: var(--color-brand);
}

.summary-card {
  background: var(--color-surface-soft);
  border-radius: 14px;
  padding: 18px;
}

.summary-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  color: var(--color-muted);
  font-size: 15px;
  padding: 6px 0;
}

.summary-row.total {
  margin-top: 8px;
  padding-top: 12px;
  border-top: 1px solid var(--color-border);
  color: var(--color-ink);
  font-size: 15px;
  font-weight: 800;
}

.summary-row.total span:last-child {
  color: var(--color-success);
}

.otp-card {
  border: 1px solid var(--color-border);
  background: var(--color-surface-soft);
  border-radius: 14px;
  padding: 18px;
}

.otp-card-header {
  display: flex;
  gap: 12px;
  align-items: flex-start;
  margin-bottom: 14px;
}

.otp-card-header i {
  color: var(--color-brand);
  margin-top: 3px;
}

.otp-card-header strong {
  display: block;
  color: var(--color-ink);
  margin-bottom: 4px;
}

.otp-card-header p {
  margin: 0;
  color: var(--color-muted);
  font-size: 14px;
  line-height: 1.5;
}

.otp-controls {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.otp-input {
  flex: 1 1 220px;
  min-height: 48px;
  border-radius: var(--radius-lg);
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  padding: 0 14px;
  font-size: 15px;
}

.otp-button {
  min-height: 48px;
  border: 0;
  border-radius: var(--radius-lg);
  padding: 0 16px;
  font-size: 14px;
  font-weight: 800;
  cursor: pointer;
}

.otp-button.secondary {
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.otp-button.primary {
  background: linear-gradient(
    135deg,
    var(--color-brand-solid) 0%,
    var(--color-brand-strong) 100%
  );
  color: #fff;
}

.warning-banner {
  display: grid;
  grid-template-columns: 18px minmax(0, 1fr);
  align-items: start;
  column-gap: 10px;
  background: var(--color-warning-soft);
  color: var(--color-warning);
  border-radius: var(--radius-lg);
  padding: 10px 12px;
  font-size: 15px;
  line-height: 1.5;
}

.warning-banner-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.warning-banner i {
  width: 18px;
  line-height: 1;
  color: var(--color-warning);
  margin-top: 1px;
  text-align: center;
}

.warning-banner-text {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.warning-banner-text p {
  margin: 0;
}

.withdraw-button {
  min-height: 52px;
  border: 0;
  border-radius: var(--radius-md);
  background: linear-gradient(
    135deg,
    var(--color-success-solid) 0%,
    var(--color-success-solid) 100%
  );
  color: #fff;
  font-size: 16px;
  font-weight: 800;
  box-shadow: 0 16px 28px rgba(54, 164, 94, 0.18);
  cursor: pointer;
}

.withdraw-button:disabled,
.back-button:disabled,
.quick-amount-chip:disabled,
.otp-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.support-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  z-index: 1000;
}

.support-modal {
  width: min(100%, 560px);
  background: var(--color-surface);
  border-radius: 18px;
  box-shadow: 0 30px 60px rgba(15, 23, 42, 0.22);
  overflow: hidden;
  border: 1px solid var(--color-border);
}

.support-modal-header,
.support-modal-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 20px;
}

.support-modal-header {
  border-bottom: 1px solid rgba(255, 255, 255, 0.14);
  background: linear-gradient(
    135deg,
    var(--color-brand-solid) 0%,
    var(--color-brand-strong) 100%
  );
}

.support-modal-header h4 {
  margin: 0;
  font-size: 17px;
  font-weight: 800;
  color: #fff;
}

.support-modal-close {
  width: 36px;
  height: 36px;
  border: 1px solid rgba(255, 255, 255, 0.3);
  border-radius: var(--radius-md);
  background: rgba(255, 255, 255, 0.12);
  color: #fff;
  font-size: 22px;
  line-height: 1;
  cursor: pointer;
}

.support-modal-body {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  background: var(--color-surface);
}

/* 支持问题内联块（Confirm 按钮上方） */
.support-questions-inline {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 16px;
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  margin-bottom: 16px;
}

.support-questions-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 700;
  color: var(--color-ink);
}

.support-questions-title i {
  color: var(--color-brand);
}

.support-question {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.support-question-label {
  color: var(--color-text);
  font-size: 14px;
  font-weight: 700;
}

.support-input {
  width: 100%;
  min-height: 48px;
  border-radius: var(--radius-lg);
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  padding: 0 14px;
  font-size: 14px;
  color: var(--color-ink);
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease;
}

.support-input:focus {
  border-color: var(--color-border);
  box-shadow: none;
}

.support-textarea {
  min-height: 96px;
  resize: vertical;
  padding: 12px 14px;
}

.support-question-hint {
  color: var(--color-muted);
  font-size: 13px;
  line-height: 1.5;
}

.support-modal-footer {
  gap: 10px;
  justify-content: flex-end;
  border-top: 1px solid var(--color-border);
  background: var(--color-surface-soft);
}

@media (max-width: 768px) {
  .details-header,
  .details-body {
    padding: 18px;
  }

  .details-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
  }

  .details-title {
    font-size: 15px;
  }

  .verified-address-card {
    flex-direction: column;
    align-items: stretch;
  }

  .currency-grid {
    grid-template-columns: 1fr;
  }

  .otp-controls {
    flex-direction: column;
  }

  .back-button {
    order: -1;
    padding: 0;
    border: 0;
    border-radius: 0;
    background: transparent;
    color: var(--color-muted);
    font-size: 14px;
    gap: 6px;
  }
}
</style>
