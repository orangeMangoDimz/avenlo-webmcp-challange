<template>
  <div class="pre-deposit-flow">
    <div class="withdraw-stepper">
      <div class="step-item active">
        <div class="step-index">1</div>
        <div class="step-label">
          {{ t("transStepMethodAddress", "Method & Address") }}
        </div>
      </div>
      <div class="step-line"></div>
      <div class="step-item">
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

    <div class="withdraw-card">
      <div class="withdraw-card-header">
        <h3>
          <i class="fas fa-credit-card"></i>
          {{
            t(
              "transStep1SelectDepositMethodAddress",
              "Step 1 - Select Deposit Method & Address",
            )
          }}
        </h3>
      </div>

      <div class="withdraw-card-body">
        <div v-if="loading" class="methods-loading">
          <i class="fas fa-spinner fa-spin methods-loading-icon"></i>
          <span>{{
            t("transLoadingDepositMethods", "Loading deposit methods...")
          }}</span>
        </div>

        <template v-else>
          <div
            v-if="availableTypes.length"
            class="form-group asset-filter-group"
          >
            <label class="form-label">
              <i class="fas fa-layer-group"></i>
              {{ t("transPaymentType", "Payment Type") }}
            </label>
            <div class="asset-filter">
              <button
                v-for="assetType in availableTypes"
                :key="assetType"
                type="button"
                class="asset-filter-btn"
                :class="{ active: assetFilter === assetType }"
                @click="selectAssetFilter(assetType)"
              >
                <i
                  :class="
                    assetType === 'crypto'
                      ? 'fas fa-coins'
                      : 'fas fa-money-bill-wave'
                  "
                ></i>
                {{
                  assetType === "crypto"
                    ? t("transCrypto", "Crypto")
                    : t("transFiat", "Fiat")
                }}
              </button>
            </div>
          </div>

          <div v-if="assetFilter || !availableTypes.length" class="form-group">
            <label class="form-label">
              <i class="fas fa-arrow-right-arrow-left"></i>
              {{ t("transDepositMethod", "Deposit Method") }}
            </label>

            <div ref="dropdownRef" class="select-shell">
              <button
                type="button"
                class="select-trigger"
                :class="{ open: isOpen, empty: !selectedGateway }"
                @click="isOpen = !isOpen"
              >
                <div class="trigger-main">
                  <div class="trigger-icon">
                    <i
                      :class="selectedGateway?.iconClass || 'fas fa-right-left'"
                    ></i>
                  </div>
                  <div class="trigger-text">
                    <div class="trigger-title">
                      {{
                        selectedGateway?.gatewayName ||
                        t(
                          "transSelectDepositMethod",
                          "Please select a deposit method",
                        )
                      }}
                    </div>
                    <div v-if="selectedGateway" class="trigger-subtitle">
                      {{ getGatewayEta(selectedGateway) }}
                    </div>
                  </div>
                </div>
                <i class="fas fa-chevron-down trigger-chevron"></i>
              </button>

              <div v-if="isOpen" class="select-dropdown">
                <button
                  type="button"
                  class="dropdown-option"
                  :class="{ selected: !modelValue }"
                  @click="selectGateway('')"
                >
                  <div class="option-main">
                    <div class="option-icon">
                      <i class="fas fa-right-left"></i>
                    </div>
                    <div class="option-text">
                      <div class="option-title">
                        {{
                          t(
                            "transSelectDepositMethod",
                            "Please select a deposit method",
                          )
                        }}
                      </div>
                    </div>
                  </div>
                  <i v-if="!modelValue" class="fas fa-check option-check"></i>
                </button>

                <template
                  v-for="group in groupedFilteredGateways"
                  :key="group.name"
                >
                  <div class="dropdown-group">
                    <div class="dropdown-group-label">{{ group.name }}</div>
                    <button
                      v-for="gateway in group.gateways"
                      :key="gateway.gatewayKey"
                      type="button"
                      class="dropdown-option"
                      :class="{ selected: gateway.gatewayKey === modelValue }"
                      @click="selectGateway(gateway.gatewayKey)"
                    >
                      <div class="option-main">
                        <div class="option-icon">
                          <i
                            :class="gateway.iconClass || 'fas fa-right-left'"
                          ></i>
                        </div>
                        <div class="option-text">
                          <div class="option-title">
                            {{ gateway.gatewayName }}
                          </div>
                          <div class="option-subtitle">
                            {{ getGatewayEta(gateway) }}
                          </div>
                          <div
                            v-if="getGatewaySupportedAssets(gateway)"
                            class="option-meta"
                          >
                            {{ getGatewaySupportedAssets(gateway) }}
                          </div>
                        </div>
                      </div>
                      <i
                        v-if="gateway.gatewayKey === modelValue"
                        class="fas fa-check option-check"
                      ></i>
                    </button>
                  </div>
                </template>
              </div>
            </div>

            <div
              v-if="
                !gateways.length || (assetFilter && !filteredGateways.length)
              "
              class="field-note muted"
            >
              {{ t("transNoDepositMethods", "No deposit methods available.") }}
            </div>
          </div>

          <div v-if="selectedGateway" class="selected-method-card">
            <div class="selected-method-main">
              <div class="selected-method-icon">
                <i
                  :class="
                    selectedGateway.iconClass || 'fas fa-building-columns'
                  "
                ></i>
              </div>
              <div>
                <div class="selected-method-name">
                  {{ selectedGateway.gatewayName }}
                </div>
                <div
                  v-if="getGatewaySupportedAssets(selectedGateway)"
                  class="selected-method-supported"
                >
                  {{ getGatewaySupportedAssets(selectedGateway) }}
                </div>
              </div>
            </div>
            <div class="selected-method-eta">
              {{ getGatewayEta(selectedGateway) }}
            </div>
          </div>

          <div v-if="selectedGateway" class="step-actions">
            <button
              type="button"
              class="continue-btn"
              :disabled="!canContinue"
              @click="emit('continue')"
            >
              {{ t("transContinue", "Continue") }}
              <i class="fas fa-arrow-right"></i>
            </button>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useLanguageStore } from "@/stores/language";
import { groupGatewaysByCurrency } from "@/utils/gatewayPsp";

const languageStore = useLanguageStore();
const t = (key, fallback = "") => languageStore.t(key, fallback);

const props = defineProps({
  modelValue: { type: String, default: "" },
  gateways: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "continue"]);

const isOpen = ref(false);
const dropdownRef = ref(null);

// 入金通道先按 fiat / crypto 分组，选了类型再显示对应通道
const assetFilter = ref("");

// 网关 type 缺省按 fiat 处理（DB 默认值就是 fiat）
const gatewayType = (gateway) =>
  String(gateway?.type || "fiat").toLowerCase() === "crypto"
    ? "crypto"
    : "fiat";

const availableTypes = computed(() => {
  const order = ["fiat", "crypto"];
  return order.filter((type) =>
    props.gateways.some((gateway) => gatewayType(gateway) === type),
  );
});

const filteredGateways = computed(() => {
  if (!assetFilter.value) return [];
  return props.gateways.filter(
    (gateway) => gatewayType(gateway) === assetFilter.value,
  );
});

const groupedFilteredGateways = computed(() =>
  groupGatewaysByCurrency(filteredGateways.value),
);

const selectedGateway = computed(
  () =>
    props.gateways.find((gateway) => gateway.gatewayKey === props.modelValue) ||
    null,
);
const canContinue = computed(() => Boolean(props.modelValue));

const selectAssetFilter = (type) => {
  if (assetFilter.value === type) return;
  assetFilter.value = type;
  // 切换类型后，已选通道若不属于当前类型则清空
  if (selectedGateway.value && gatewayType(selectedGateway.value) !== type) {
    emit("update:modelValue", "");
  }
  isOpen.value = false;
};

// 通道列表加载完成后定默认类型：跟随已选通道 > 只有一种类型时自动选 > 两种都有时让用户先选
watch(
  () => props.gateways,
  () => {
    const types = availableTypes.value;
    if (assetFilter.value && types.includes(assetFilter.value)) return;

    if (
      selectedGateway.value &&
      types.includes(gatewayType(selectedGateway.value))
    ) {
      assetFilter.value = gatewayType(selectedGateway.value);
    } else if (types.length === 1) {
      assetFilter.value = types[0];
    } else {
      assetFilter.value = "";
    }
  },
  { immediate: true },
);

const gatewayEtaMap = {
  bank_wire_transfer: () =>
    t("transEtaOneThreeBusinessDays", "1-3 business days"),
  bank_transfer: () => t("transEtaOneThreeBusinessDays", "1-3 business days"),
  local_bank_transfer: () => t("transEtaSameDay", "Same day"),
  card: () => t("transEtaThreeFiveBusinessDays", "3-5 business days"),
  credit_card: () => t("transEtaThreeFiveBusinessDays", "3-5 business days"),
  debit_card: () => t("transEtaThreeFiveBusinessDays", "3-5 business days"),
  paypal: () => t("transEtaInstant24Hours", "Instant - 24 hrs"),
  skrill: () => t("transEtaInstant24Hours", "Instant - 24 hrs"),
  netteller: () => t("transEtaInstant24Hours", "Instant - 24 hrs"),
  cryptocurrency: () => t("transEtaFifteenSixtyMinutes", "15-60 minutes"),
  crypto: () => t("transEtaFifteenSixtyMinutes", "15-60 minutes"),
  alchemy_pay: () => t("transEtaFifteenSixtyMinutes", "15-60 minutes"),
  ibeepay: () => t("transEtaOneThreeBusinessDays", "1-3 business days"),
};

const getGatewayEta = (gateway) => {
  const processingTime = gateway?.processingTime;
  if (typeof processingTime === "string" && processingTime.trim()) {
    return processingTime.trim();
  }

  const key = (gateway?.gatewayKey || "").toLowerCase();
  if (gatewayEtaMap[key]) return gatewayEtaMap[key]();

  const name = (gateway?.gatewayName || "").toLowerCase();
  if (name.includes("wire"))
    return t("transEtaOneThreeBusinessDays", "1-3 business days");
  if (name.includes("bank")) return t("transEtaSameDay", "Same day");
  if (name.includes("card"))
    return t("transEtaThreeFiveBusinessDays", "3-5 business days");
  if (
    name.includes("paypal") ||
    name.includes("skrill") ||
    name.includes("neteller")
  )
    return t("transEtaInstant24Hours", "Instant - 24 hrs");
  if (name.includes("crypto"))
    return t("transEtaFifteenSixtyMinutes", "15-60 minutes");
  return t("transEtaVaries", "Processing time varies");
};

const normalizeGatewayAssetCodes = (items) => {
  if (!Array.isArray(items)) return [];

  return items
    .map((item) => {
      if (typeof item === "object" && item !== null) {
        return (
          item.shortCode ||
          item.code ||
          item.symbol ||
          item.currency ||
          item.name ||
          item.methodName ||
          ""
        );
      }

      return String(item || "").trim();
    })
    .map((item) => String(item || "").trim())
    .filter(Boolean);
};

const getGatewaySupportedAssets = (gateway) => {
  if (!gateway || typeof gateway !== "object") return "";

  // 多币种网关（如 AEON）：不展示支持币种
  if (Number(gateway.isMultiCurrency) === 1) return "";

  const fiatCurrencies = normalizeGatewayAssetCodes(
    gateway.supportedFiatCurrencies,
  );
  const cryptoCurrencies = normalizeGatewayAssetCodes(
    gateway.supportedCryptoCurrencies,
  );

  if (fiatCurrencies.length) {
    return `${t("transFiat", "Fiat")}: ${fiatCurrencies.join(", ")}`;
  }

  if (cryptoCurrencies.length) {
    return `${t("transCrypto", "Crypto")}: ${cryptoCurrencies.join(", ")}`;
  }

  return "";
};

const selectGateway = (gatewayKey) => {
  emit("update:modelValue", gatewayKey);
  isOpen.value = false;
};

const handleClickOutside = (event) => {
  if (!dropdownRef.value?.contains(event.target)) {
    isOpen.value = false;
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
.pre-deposit-flow {
  display: flex;
  flex-direction: column;
  gap: 28px;
}

.withdraw-stepper {
  display: grid;
  grid-template-columns: auto 1fr auto 1fr auto;
  align-items: center;
  gap: 16px;
  background: #ffffff;
  border: 1px solid #e7ebf3;
  border-radius: 18px;
  padding: 20px 24px;
  box-shadow: 0 16px 40px rgba(15, 23, 42, 0.06);
}

.step-item {
  display: flex;
  align-items: center;
  gap: 12px;
  color: #94a3b8;
  font-weight: 600;
  white-space: nowrap;
}

.step-item.active {
  color: var(--color-brand);
}

.step-index {
  width: 34px;
  height: 34px;
  border-radius: 999px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid #d6dbea;
  background: #ffffff;
}

.step-item.active .step-index {
  border-color: var(--color-brand);
  background: var(--color-brand);
  color: #ffffff;
}

.step-line {
  height: 1px;
  background: #dbe3ef;
}

.withdraw-card {
  background: #ffffff;
  border: 1px solid #e7ebf3;
  border-radius: 18px;
  box-shadow: 0 18px 45px rgba(15, 23, 42, 0.06);
  overflow: visible;
}

.withdraw-card-header {
  padding: 18px 22px;
  border-bottom: 1px solid #e7ebf3;
}

.withdraw-card-header h3 {
  margin: 0;
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 18px;
  color: #2f3a4f;
}

.withdraw-card-header i {
  color: var(--color-brand);
}

.withdraw-card-body {
  padding: 22px;
  position: relative;
  z-index: 1;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.form-label {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--color-text);
  font-weight: 600;
}

.form-label i {
  color: var(--color-brand);
}

.methods-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 48px 0;
  color: var(--color-brand);
  font-weight: 600;
}

.methods-loading-icon {
  font-size: 20px;
}

.asset-filter-group {
  margin-bottom: 18px;
}

.asset-filter {
  display: flex;
  gap: 10px;
}

.asset-filter-btn {
  flex: 1;
  min-height: 48px;
  border: 2px solid #cfd7e6;
  border-radius: var(--radius-lg);
  background: #ffffff;
  color: var(--color-text);
  font-size: 14px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  cursor: pointer;
}

.asset-filter-btn i {
  color: #94a3b8;
}

.asset-filter-btn.active {
  border-color: var(--color-brand);
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.asset-filter-btn.active i {
  color: var(--color-brand);
}

.select-shell {
  position: relative;
  z-index: 20;
}

.select-trigger {
  width: 100%;
  min-height: 56px;
  border: 2px solid #cfd7e6;
  border-radius: 14px;
  background: #ffffff;
  padding: 0 16px;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  cursor: pointer;
}

.select-trigger.empty {
  border-color: #cfd7e6;
}

.trigger-main,
.option-main,
.selected-method-main {
  display: flex;
  align-items: center;
  gap: 12px;
}

.trigger-icon,
.option-icon,
.selected-method-icon {
  width: 36px;
  height: 36px;
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-brand-soft);
  color: var(--color-brand);
  flex-shrink: 0;
}

.crypto-card-icon {
  width: 36px;
  height: 36px;
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-brand-soft);
  color: var(--color-brand);
  flex-shrink: 0;
}

.trigger-text,
.option-text {
  display: flex;
  align-items: baseline;
  gap: 12px;
  flex-wrap: wrap;
  text-align: left;
}

.trigger-title,
.option-title,
.selected-method-name {
  font-size: 15px;
  font-weight: 700;
  color: #2f3a4f;
}

.trigger-subtitle,
.option-subtitle,
.selected-method-eta {
  font-size: 14px;
  color: #64748b;
  font-weight: 600;
}

.option-meta,
.selected-method-supported {
  flex-basis: 100%;
  font-size: 12px;
  color: #7c879c;
  font-weight: 500;
}

.trigger-chevron {
  color: #94a3b8;
}

.select-dropdown {
  position: absolute;
  top: calc(100% + 8px);
  left: 0;
  right: 0;
  z-index: 50;
  max-height: min(360px, 50vh);
  overflow-x: hidden;
  overflow-y: auto;
  background: #ffffff;
  border: 1px solid #d8e0f0;
  border-radius: 14px;
  box-shadow: 0 18px 35px rgba(15, 23, 42, 0.12);
}

.dropdown-option {
  width: 100%;
  border: 0;
  border-top: 1px solid var(--color-surface-muted);
  background: #ffffff;
  padding: 14px 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  cursor: pointer;
  text-align: left;
}

.select-dropdown > .dropdown-option:first-child {
  border-top: 0;
}

.dropdown-group {
  border-top: 1px solid #e7ebf3;
}

.dropdown-group .dropdown-option {
  border-top: 1px solid var(--color-surface-muted);
}

.dropdown-group-label {
  position: sticky;
  top: 0;
  z-index: 1;
  padding: 10px 16px;
  background: var(--color-brand-soft);
  border-bottom: 1px solid #e7ebf3;
  color: #2f3a4f;
  font-size: 13px;
  font-weight: 700;
}

.dropdown-option.selected,
.dropdown-option:hover {
  background: #f8faff;
}

.option-check {
  color: var(--color-brand);
}

.field-note {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: var(--color-brand);
  margin-top: 10px;
}

.field-note.muted {
  color: #94a3b8;
}

.selected-method-card {
  margin-top: 10px;
  background: var(--color-brand-soft);
  border-radius: var(--radius-md);
  padding: 10px 14px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.selected-method-eta {
  color: var(--color-brand);
  font-size: 14px;
  font-weight: 800;
}

.crypto-section {
  margin-top: 24px;
  border-top: 2px solid #f0f4f8;
  padding-top: 20px;
}

.address-section-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.address-section-title i {
  color: var(--color-brand);
}

.crypto-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 12px;
}

.crypto-card {
  border: 1px solid #d7dfeb;
  border-radius: 14px;
  background: #ffffff;
  padding: 18px 16px;
  text-align: left;
  cursor: pointer;
}

.crypto-card.selected {
  border-color: var(--color-brand);
  background: var(--color-brand-soft);
  box-shadow: 0 10px 24px rgba(var(--color-brand-rgb), 0.12);
}

.crypto-card-name {
  margin-top: 12px;
  color: #2f3a4c;
  font-size: 15px;
  font-weight: 800;
}

.crypto-card-code {
  margin-top: 4px;
  color: var(--color-muted);
  font-size: 13px;
}

.step-actions {
  margin-top: 26px;
  display: flex;
  justify-content: center;
}

.continue-btn {
  min-width: 180px;
  min-height: 48px;
  border: 0;
  border-radius: var(--radius-lg);
  background: linear-gradient(
    135deg,
    var(--color-brand) 0%,
    var(--color-brand-strong) 100%
  );
  color: #ffffff;
  font-size: 15px;
  font-weight: 800;
  cursor: pointer;
  box-shadow: 0 14px 28px rgba(var(--color-brand-rgb), 0.18);
}

.continue-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  box-shadow: none;
}

@media (max-width: 768px) {
  .withdraw-stepper {
    grid-template-columns: auto 1fr auto 1fr auto;
    gap: 8px;
    padding: 14px;
  }

  .step-item {
    justify-content: center;
  }

  .step-label {
    display: none;
  }

  .withdraw-card-header h3 {
    font-size: 16px;
  }

  .withdraw-card-body {
    padding: 18px;
  }
}
</style>
