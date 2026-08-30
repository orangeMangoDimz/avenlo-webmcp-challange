<template>
  <div class="deposit-panel">
    <div class="withdraw-stepper">
      <div class="step-item complete">
        <div class="step-index"><i class="fas fa-check"></i></div>
        <div class="step-label">{{ t("transMethod", "Method") }}</div>
      </div>
      <div class="step-line complete"></div>
      <div class="step-item active">
        <div class="step-index">2</div>
        <div class="step-label">
          {{ t("transStepVerificationAmount", "Verification / Amount") }}
        </div>
      </div>
      <div class="step-line"></div>
      <div class="step-item">
        <div class="step-index">3</div>
        <div class="step-label">{{ t("transStepConfirm", "Confirm") }}</div>
      </div>
    </div>

    <div class="transaction-card details-card">
      <div class="card-header details-header">
        <h3 class="card-title details-title">
          <i class="fas fa-bolt"></i>
          {{ t("transStep2DepositDetails", "Step 2 - Deposit Details") }}
        </h3>
        <button type="button" class="btn back-button" @click="emit('back')">
          <i class="fas fa-arrow-left"></i>
          {{ t("transBack", "Back") }}
        </button>
      </div>

      <div class="details-body">
        <div class="verified-address-card">
          <div class="verified-address-main">
            <div class="verified-address-icon">
              <i :class="selectedItem.iconClass || 'fas fa-credit-card'"></i>
            </div>
            <div class="verified-address-text">
              <div class="verified-address-name">{{ selectedItem.name }}</div>
              <div v-if="selectedItem.value" class="verified-address-value">
                {{ selectedItem.value }}
              </div>
            </div>
          </div>
        </div>

        <div v-if="gatewayContentHtml" class="gateway-content-card">
          <div class="gateway-content-title">
            {{ t("transPaymentDetails", "Payment Details") }}
          </div>
          <div class="gateway-content" v-html="gatewayContentHtml"></div>
        </div>

        <form class="details-form" @submit.prevent="onSubmit">
          <div class="form-group">
            <label class="section-label">
              <i class="fas fa-building"></i>
              {{ t("transDepositTo", "Deposit To") }}
            </label>
            <div ref="accountSelectRef" class="details-select-shell">
              <button
                type="button"
                :class="[
                  'details-select-trigger',
                  {
                    placeholder: !selectedTargetAccountType,
                    open: showAccountDropdown,
                  },
                ]"
                @click="toggleAccountDropdown"
              >
                <span>{{
                  selectedAccountLabel ||
                  t("transSelectTradingAccount", "Select trading account")
                }}</span>
                <i class="fas fa-chevron-down"></i>
              </button>
              <div v-if="showAccountDropdown" class="details-select-menu">
                <button
                  type="button"
                  :class="[
                    'details-select-option',
                    { selected: selectedTargetAccountType === 'wallet' },
                  ]"
                  @click="selectTargetAccount('wallet')"
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
                          selectedTargetAccountType === `trading_${account.id}`,
                      },
                    ]"
                    @click="selectTargetAccount(`trading_${account.id}`)"
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
            <div v-if="selectedBalanceTarget" class="account-balance-preview">
              <div class="account-balance-preview-title">
                {{ t("transBalanceImpact", "Balance Impact") }}
              </div>
              <div class="account-balance-preview-row">
                <span>{{ t("transSelectedAccount", "Selected Account") }}</span>
                <span>{{ selectedBalanceTarget.label }}</span>
              </div>
              <div class="account-balance-preview-row">
                <span>{{ t("transCurrentBalance", "Current Balance") }}</span>
                <span>{{
                  formatBalanceValue(
                    balanceBeforeDeposit,
                    selectedBalanceTarget,
                  )
                }}</span>
              </div>
              <div class="account-balance-preview-row">
                <span>{{
                  t("transDepositAmountLabel", "Deposit Amount")
                }}</span>
                <span>{{
                  formatImpactValue(
                    normalizedDepositAmount,
                    selectedBalanceTarget,
                  )
                }}</span>
              </div>
              <div class="account-balance-preview-row strong">
                <span>{{
                  t("transBalanceAfterDeposit", "Balance After Deposit")
                }}</span>
                <span>{{
                  formatBalanceValue(balanceAfterDeposit, selectedBalanceTarget)
                }}</span>
              </div>
            </div>
          </div>

          <div v-if="!isMultiCurrency && currencies.length" class="form-group">
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

          <div
            v-else-if="!isMultiCurrency && !currencyLoading"
            class="form-group"
          >
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
              <i class="fas fa-dollar-sign"></i>
              {{ t("transDepositAmountUsd", "Deposit Amount (USD)") }}
            </label>
            <div class="amount-input-shell">
              <div class="amount-prefix">$</div>
              <FormattedNumberInput
                :model-value="amount"
                :decimals="2"
                placeholder="0.00"
                input-class="details-amount-input"
                @update:model-value="emit('update:amount', $event)"
              />
            </div>
            <div v-if="quickAmountOptions.length > 0" class="quick-amounts">
              <button
                v-for="amount in quickAmountOptions"
                :key="`deposit-quick-${amount}`"
                type="button"
                class="quick-amount-chip"
                @click="emit('update:amount', amount)"
              >
                {{ formatQuickAmount(amount) }}
              </button>
            </div>
            <div v-if="hasAmountRange" class="amount-range-hint">
              <span v-if="minAmount != null && maxAmount != null">
                {{ t("transDepositAmountRange", "Amount range") }}:
                {{ formatCurrency(minAmount) }} -
                {{ formatCurrency(maxAmount) }}
              </span>
              <span v-else-if="minAmount != null">
                {{ t("transDepositMinimumAmount", "Minimum deposit") }}:
                {{ formatCurrency(minAmount) }}
              </span>
              <span v-else-if="maxAmount != null">
                {{ t("transDepositMaximumAmount", "Maximum deposit") }}:
                {{ formatCurrency(maxAmount) }}
              </span>
            </div>
          </div>

          <div class="summary-card">
            <div class="summary-row">
              <span>{{ t("transDepositAmountLabel", "Deposit Amount") }}</span>
              <span>{{ formatCurrency(Number(amount || 0)) }}</span>
            </div>
            <div v-if="feeSummary.feeAmount > 0" class="summary-row">
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
                  feeSummary.totalAmount || Number(amount || 0),
                  displayCurrency,
                  feeSummary.amountDecimalPlaces,
                )
              }}</span>
            </div>
            <div class="summary-row">
              <span>{{ t("transMethod", "Method") }}</span>
              <span>{{ selectedItem.name }}</span>
            </div>
            <div v-if="selectedItem.value" class="summary-row">
              <span>{{ t("transNetwork", "Network") }}</span>
              <span>{{ selectedItem.value }}</span>
            </div>
          </div>

          <!-- 支持问题（gateway 配置的额外字段）：由父级通过具名 slot 注入到 Confirm 按钮上方 -->
          <slot name="support-questions"></slot>

          <div v-if="displayContents.length" class="warning-banner-list">
            <div
              v-for="(item, index) in displayContents"
              :key="`${item.content || 'content'}-${index}`"
              class="warning-banner"
            >
              <i class="fas fa-circle-info"></i>
              <div class="warning-banner-text">
                <p>{{ item.content }}</p>
              </div>
            </div>
          </div>

          <button
            type="submit"
            class="btn submit-button"
            :disabled="submitting || !canSubmit"
          >
            <i
              :class="
                submitting ? 'fas fa-spinner fa-spin' : 'fas fa-arrow-down'
              "
            ></i>
            {{
              submitting
                ? t("transProcessing", "Processing...")
                : t("transConfirmDeposit", "Confirm Deposit")
            }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import FormattedNumberInput from "../common/FormattedNumberInput.vue";
import CurrencyLogo from "./CurrencyLogo.vue";
import { useLanguageStore } from "@/stores/language";

const languageStore = useLanguageStore();
const t = (key, fallback = "") => languageStore.t(key, fallback);

const props = defineProps({
  selectedItem: { type: Object, required: true },
  tradingAccounts: { type: Array, default: () => [] },
  selectedTargetAccountType: { type: String, default: "" },
  accountBalance: { type: Number, default: 0 },
  currencies: { type: Array, default: () => [] },
  assetType: { type: String, default: "crypto" },
  selectedCurrency: { type: [String, Number, null], default: null },
  currencyLoading: { type: Boolean, default: false },
  currencyEmptyText: { type: String, default: "" },
  // 多币种网关（如 AEON）：不展示币种选择器，也不要求选币种
  isMultiCurrency: { type: Boolean, default: false },
  amount: { type: [Number, String, null], default: null },
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
  submitting: { type: Boolean, default: false },
  displayContents: { type: Array, default: () => [] },
  gatewayContentHtml: { type: String, default: "" },
  formatCurrency: { type: Function, required: true },
  formatSettlementAmount: { type: Function, required: true },
  onSubmit: { type: Function, required: true },
});

const emit = defineEmits([
  "back",
  "select-currency",
  "update:amount",
  "select-platform-account",
]);

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

const hasAmountRange = computed(
  () => props.minAmount != null || props.maxAmount != null,
);
const canSubmit = computed(() =>
  props.isMultiCurrency ? true : Boolean(props.selectedCurrency),
);
const showAccountDropdown = ref(false);
const accountSelectRef = ref(null);
const groupedTradingAccounts = computed(() => {
  const groups = new Map();

  props.tradingAccounts.forEach((account) => {
    const label =
      account.platformName ||
      account.platformCode ||
      account.platformKey ||
      t("transTradingAccounts", "Trading Accounts");
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

const selectedAccountLabel = computed(() => {
  if (props.selectedTargetAccountType === "wallet") {
    return `${t("transWalletBalance", "Wallet")} - ${formatBalanceValue(props.accountBalance, { unit: "USD", scale: 1 })}`;
  }

  const matchedAccount = props.tradingAccounts.find(
    (account) => `trading_${account.id}` === props.selectedTargetAccountType,
  );
  if (!matchedAccount) return "";
  return `${matchedAccount.accountNickname} (${matchedAccount.accountNumber}) - ${formatBalanceValue(matchedAccount.availableBalance || 0, matchedAccount)}`;
});

const selectedTradingAccount = computed(
  () =>
    props.tradingAccounts.find(
      (account) => `trading_${account.id}` === props.selectedTargetAccountType,
    ) || null,
);

const normalizedDepositAmount = computed(() => Number(props.amount || 0));

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
  const normalizedAmount = Number(amount || 0);
  const normalizedUnit = String(unit || "")
    .trim()
    .toUpperCase();

  if (normalizedUnit === "USD") {
    return `${props.formatCurrency(normalizedAmount)} USD`;
  }

  if (!normalizedUnit) {
    return formatScaledNumber(normalizedAmount);
  }

  return `${formatScaledNumber(normalizedAmount)} ${normalizedUnit}`;
};

const formatBalanceValue = (amount, source = {}) =>
  formatDisplayValue(amount, resolveDisplayUnit(source));

const formatImpactValue = (amount, source = {}) => {
  const scaledImpact = Number(amount || 0) * resolveDisplayScale(source);
  return formatDisplayValue(scaledImpact, resolveDisplayUnit(source));
};

const selectedBalanceTarget = computed(() => {
  if (props.selectedTargetAccountType === "wallet") {
    return {
      label: t("transWalletBalance", "Wallet"),
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

const balanceBeforeDeposit = computed(() =>
  Number(selectedBalanceTarget.value?.before || 0),
);
const balanceAfterDeposit = computed(
  () =>
    balanceBeforeDeposit.value +
    normalizedDepositAmount.value *
      resolveDisplayScale(selectedBalanceTarget.value),
);

const selectTargetAccount = (value) => {
  emit("select-platform-account", value);
  showAccountDropdown.value = false;
};

const toggleAccountDropdown = () => {
  showAccountDropdown.value = !showAccountDropdown.value;
};

const handleClickOutside = (event) => {
  if (!accountSelectRef.value?.contains(event.target)) {
    showAccountDropdown.value = false;
  }
};

onMounted(() => {
  document.addEventListener("click", handleClickOutside, true);
});

onBeforeUnmount(() => {
  document.removeEventListener("click", handleClickOutside, true);
});
</script>

<style scoped>
.deposit-panel {
  display: flex;
  flex-direction: column;
  gap: 22px;
}

.withdraw-stepper {
  display: flex;
  align-items: center;
  gap: 16px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 18px;
  padding: 18px 22px;
}

.step-item {
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--color-faint);
  font-weight: 700;
  white-space: nowrap;
}

.step-item.complete,
.step-item.active {
  color: var(--color-text);
}

.step-index {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-border);
  color: var(--color-muted);
  font-size: 14px;
  font-weight: 800;
}

.step-item.complete .step-index {
  background: var(--color-success-solid);
  color: #fff;
}

.step-item.active .step-index {
  background: var(--color-brand-solid);
  color: #fff;
}

.step-line {
  flex: 1;
  height: 2px;
  background: var(--color-surface-muted);
}

.step-line.complete {
  background: var(--color-success-solid);
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
  font-size: 16px;
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
  border: 1px solid var(--color-border);
  border-radius: 14px;
  background: var(--color-brand-soft);
  margin-bottom: 20px;
}

.verified-address-main {
  display: flex;
  align-items: center;
  gap: 14px;
}

.verified-address-icon {
  width: 42px;
  height: 42px;
  border-radius: var(--radius-lg);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--color-brand);
  background: rgba(var(--color-brand-rgb), 0.12);
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
  margin-top: 4px;
  padding-top: 12px;
  border-top: 1px solid var(--color-border);
  color: var(--color-ink);
  font-weight: 800;
}

.warning-banner {
  display: grid;
  grid-template-columns: 18px minmax(0, 1fr);
  align-items: start;
  column-gap: 10px;
  background: var(--color-brand-soft);
  color: var(--color-brand);
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
  margin-top: 1px;
  color: var(--color-brand);
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

.submit-button {
  min-height: 52px;
  border: 0;
  border-radius: var(--radius-md);
  background: linear-gradient(
    135deg,
    var(--color-brand-solid) 0%,
    var(--color-brand-strong) 100%
  );
  color: #fff;
  font-size: 16px;
  font-weight: 800;
  box-shadow: 0 16px 28px rgba(var(--color-brand-rgb), 0.18);
}

@media (max-width: 768px) {
  .currency-grid {
    grid-template-columns: 1fr;
  }

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

  .withdraw-stepper {
    gap: 8px;
    padding: 14px;
  }

  .step-label {
    display: none;
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
