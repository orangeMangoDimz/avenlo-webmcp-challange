<template>
  <Teleport to="body">
    <div class="modal-overlay" @click="$emit('close')">
      <div class="modal ib-ex-rate-modal" @click.stop>
        <div class="modal-header">
          <h2 class="modal-title">
            {{
              mode === "create"
                ? t("ibExRateModal_create_title")
                : t("ibExRateModal_edit_title")
            }}
          </h2>
          <button
            type="button"
            class="modal-close"
            :aria-label="t('ibExRateModal_close')"
            @click="$emit('close')"
          >
            <i class="fas fa-times"></i>
          </button>
        </div>

        <div class="modal-body">
          <form @submit.prevent="submit">
            <div class="form-group">
              <label class="form-label required">{{
                t("ibExRate_col_symbol")
              }}</label>
              <template v-if="mode === 'edit'">
                <input
                  type="text"
                  class="form-input"
                  :value="selectedSymbolName"
                  disabled
                />
              </template>
              <div v-else class="ib-ex-symbol-search">
                <div class="ib-ex-symbol-search__input-wrap">
                  <i
                    class="fas fa-search ib-ex-symbol-search__icon"
                    aria-hidden="true"
                  ></i>
                  <input
                    ref="symbolInputRef"
                    type="text"
                    class="ib-ex-symbol-search__input"
                    :value="symbolInputDisplay"
                    :placeholder="t('ibExRateModal_symbol_placeholder')"
                    autocomplete="off"
                    @input="onSymbolInput"
                    @focus="onSymbolFocus"
                    @blur="onSymbolBlur"
                  />
                  <button
                    v-if="form.symbolId > 0"
                    type="button"
                    class="ib-ex-symbol-search__clear"
                    :aria-label="t('ibExRateModal_symbol_clear')"
                    @mousedown.prevent
                    @click="clearSymbol"
                  >
                    <i class="fas fa-times" aria-hidden="true"></i>
                  </button>
                </div>
                <div
                  v-show="showSymbolDropdown"
                  class="ib-ex-symbol-search__dropdown"
                  @mousedown.prevent
                >
                  <div
                    v-if="filteredSymbols.length === 0"
                    class="ib-ex-symbol-search__empty"
                  >
                    {{
                      symbolSearchKeyword
                        ? t("ibExRateModal_symbol_empty")
                        : t("ibExRateModal_symbol_placeholder")
                    }}
                  </div>
                  <div v-else class="ib-ex-symbol-search__list">
                    <button
                      v-for="sym in filteredSymbols"
                      :key="sym.id"
                      type="button"
                      class="ib-ex-symbol-search__option"
                      :class="{ active: Number(sym.id) === form.symbolId }"
                      @click="selectSymbol(sym)"
                    >
                      <span class="ib-ex-symbol-search__option-name">{{
                        sym.symbolName
                      }}</span>
                      <span
                        v-if="sym.currency"
                        class="ib-ex-symbol-search__option-meta"
                        >{{ sym.currency }}</span
                      >
                    </button>
                  </div>
                </div>
              </div>
              <p class="form-hint">{{ t("ibExRateModal_symbol_hint") }}</p>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label required">{{
                  t("ibExRateModal_baseCurrency")
                }}</label>
                <input
                  v-if="form.syncMode === 'auto'"
                  v-model="form.baseCurrency"
                  type="text"
                  class="form-input"
                  disabled
                />
                <select
                  v-else
                  v-model="form.baseCurrency"
                  class="form-select"
                  required
                  @change="onManualBaseCurrencyChange"
                >
                  <option value="">
                    {{ t("ibExRateModal_currency_placeholder") }}
                  </option>
                  <option
                    v-for="code in fiatCurrencyOptions"
                    :key="`base-${code}`"
                    :value="code"
                  >
                    {{ code }}
                  </option>
                </select>
                <p class="form-hint">{{ t("ibExRateModal_base_hint") }}</p>
              </div>
              <div class="form-group">
                <label class="form-label required">{{
                  t("ibExRateModal_targetCurrency")
                }}</label>
                <input type="text" class="form-input" value="USD" disabled />
                <p class="form-hint">{{ t("ibExRateModal_target_hint") }}</p>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label required">{{
                t("ibExRate_col_rate")
              }}</label>
              <input
                v-if="form.syncMode === 'auto'"
                type="text"
                class="form-input"
                disabled
                :value="autoExchangeRatePreview"
              />
              <input
                v-else
                v-model.number="form.exchangeRate"
                type="number"
                step="0.00001"
                min="0.00001"
                class="form-input"
                required
                :placeholder="t('ibExRateModal_rate_placeholder')"
                @blur="normalizeExchangeRateInput"
              />
              <p class="form-hint">{{ manualRateHint }}</p>
            </div>

            <div class="form-group">
              <label class="form-label required">{{
                t("ibExRate_globalMode")
              }}</label>
              <div class="sync-mode-options">
                <label
                  class="sync-mode-option"
                  :class="{ active: form.syncMode === 'auto' }"
                >
                  <input v-model="form.syncMode" type="radio" value="auto" />
                  <div>
                    <span class="sync-mode-option__title">
                      {{ t("ibExRate_mode_auto") }}
                      <span class="sync-mode-option__badge">{{
                        t("ibExRateModal_recommended")
                      }}</span>
                    </span>
                    <span class="sync-mode-option__desc">{{
                      t("ibExRateModal_auto_desc")
                    }}</span>
                  </div>
                </label>
                <label
                  class="sync-mode-option"
                  :class="{ active: form.syncMode === 'manual' }"
                >
                  <input v-model="form.syncMode" type="radio" value="manual" />
                  <div>
                    <span class="sync-mode-option__title">{{
                      t("ibExRate_mode_manual")
                    }}</span>
                    <span class="sync-mode-option__desc">{{
                      t("ibExRateModal_manual_desc")
                    }}</span>
                  </div>
                </label>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">{{
                t("ibExRate_col_updatedAt")
              }}</label>
              <input
                type="text"
                class="form-input"
                :value="updatedAtPreview"
                disabled
              />
              <p class="form-hint">{{ t("ibExRateModal_updated_hint") }}</p>
            </div>

            <div class="form-group">
              <label class="form-label">{{ t("ibExRateModal_remarks") }}</label>
              <textarea
                v-model="form.remarks"
                class="form-textarea"
                rows="3"
                maxlength="200"
                :placeholder="t('ibExRateModal_remarks_placeholder')"
              ></textarea>
              <div class="form-hint form-hint--between">
                <span>{{ t("ibExRateModal_remarks_hint") }}</span>
                <span>{{ (form.remarks || "").length }} / 200</span>
              </div>
            </div>
          </form>
        </div>

        <div v-if="saveError" class="ib-ex-rate-modal__error" role="alert">
          <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
          <span>{{ saveError }}</span>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-cancel" @click="$emit('close')">
            {{ t("ibExRateModal_cancel") }}
          </button>
          <button
            type="button"
            class="btn btn-save"
            :disabled="saving || !isValid"
            @click="submit"
          >
            <i v-if="saving" class="fas fa-spinner fa-spin"></i>
            {{ t("ibExRateModal_save") }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from "vue";
import { useAdminI18n } from "@/composables/useAdminI18n";
import transactionSettingsApi from "@/services/transactionSettingsApi";

const props = defineProps({
  mode: { type: String, default: "create" },
  rate: { type: Object, default: null },
  symbols: { type: Array, default: () => [] },
  globalSyncMode: { type: String, default: "auto" },
  saving: { type: Boolean, default: false },
  saveError: { type: String, default: "" },
});

const emit = defineEmits(["close", "save"]);

const { t, tParams } = useAdminI18n();

const fiatRatesList = ref([]);

const symbolInputRef = ref(null);
const symbolSearchKeyword = ref("");
const showSymbolDropdown = ref(false);

const form = reactive({
  symbolId: 0,
  baseCurrency: "",
  targetCurrency: "USD",
  exchangeRate: null,
  syncMode: "auto",
  remarks: "",
});

const fiatCurrencyOptions = computed(() => {
  const codes = new Set(["USD"]);
  fiatRatesList.value.forEach((item) => {
    if (item?.currencyCode) codes.add(String(item.currencyCode).toUpperCase());
  });
  return Array.from(codes).sort();
});

const fiatRatesMap = computed(() => {
  const map = { USD: 1 };
  fiatRatesList.value.forEach((item) => {
    const code = String(item?.currencyCode || "").toUpperCase();
    const rate = Number(item?.exchangeRate);
    if (code && rate > 0) map[code] = rate;
  });
  return map;
});

const isManual = computed(() => form.syncMode === "manual");

const isValid = computed(() => {
  if (form.symbolId <= 0 || !form.baseCurrency) return false;
  if (isManual.value) {
    const rate = Number(form.exchangeRate);
    return rate > 0 && !Number.isNaN(rate);
  }
  return true;
});

const selectedSymbol = computed(
  () =>
    props.symbols.find((s) => Number(s.id) === Number(form.symbolId)) || null,
);

const selectedSymbolName = computed(
  () => props.rate?.symbol || selectedSymbol.value?.symbolName || "—",
);

const symbolInputDisplay = computed(() =>
  selectedSymbol.value
    ? selectedSymbol.value.symbolName
    : symbolSearchKeyword.value,
);

const filteredSymbols = computed(() => {
  const keyword = symbolSearchKeyword.value.trim().toLowerCase();
  if (!keyword) return props.symbols;
  return props.symbols.filter((sym) => {
    const name = String(sym.symbolName || "").toLowerCase();
    const currency = String(sym.currency || "").toLowerCase();
    const platform = String(sym.tradingPlatformKey || "").toLowerCase();
    return (
      name.includes(keyword) ||
      currency.includes(keyword) ||
      platform.includes(keyword)
    );
  });
});

const EXCHANGE_RATE_DECIMALS = 5;

const roundExchangeRate = (value) => {
  const n = Number(value);
  if (Number.isNaN(n)) return null;
  return Number(n.toFixed(EXCHANGE_RATE_DECIMALS));
};

const formatRateLine = (rate, code) => {
  const n = roundExchangeRate(rate);
  if (n == null) return "—";
  return tParams("txnSettings_rateLine", "1 USD = {rate} {code}", {
    rate: new Intl.NumberFormat(undefined, {
      minimumFractionDigits: 0,
      maximumFractionDigits: EXCHANGE_RATE_DECIMALS,
    }).format(n),
    code: String(code || "USD").trim(),
  }).replace(/\s+$/u, "");
};

const resolveAutoRateCurrency = () => {
  const base = String(form.baseCurrency || "").toUpperCase();
  if (base && base !== "USD") return base;
  return "USD";
};

const autoExchangeRatePreview = computed(() => {
  const code = resolveAutoRateCurrency();
  const rate = fiatRatesMap.value[code] ?? 1;
  return formatRateLine(rate, code);
});

const manualRateHint = computed(() => {
  const code = form.baseCurrency || t("ibExRateModal_currency_placeholder");
  return tParams("txnSettings_help_exchangeRateLine", "1 USD = X {code}", {
    code,
  });
});

const loadFiatRates = async () => {
  try {
    const res = await transactionSettingsApi.getExchangeRates(false);
    if (res?.success && Array.isArray(res.data)) {
      fiatRatesList.value = res.data.filter(
        (item) => (item?.type || "fiat") === "fiat" && item?.isActive !== 0,
      );
    } else {
      fiatRatesList.value = [];
    }
  } catch {
    fiatRatesList.value = [];
  }
};

const updatedAtPreview = computed(() => {
  if (props.mode === "edit" && props.rate?.updatedAt) {
    return formatDateTime(props.rate.updatedAt);
  }
  return formatDateTime(new Date().toISOString());
});

const formatDateTime = (value) => {
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return "—";
  const pad = (n) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
};

const resetForm = () => {
  const r = props.rate;
  form.symbolId = r?.symbolId != null ? Number(r.symbolId) : 0;
  form.targetCurrency = "USD";
  form.syncMode = r?.syncMode || props.globalSyncMode || "auto";
  form.remarks = r?.remarks || "";
  form.exchangeRate =
    r?.exchangeRate != null ? roundExchangeRate(r.exchangeRate) : null;
  symbolSearchKeyword.value = "";
  showSymbolDropdown.value = false;

  if (form.syncMode === "manual" && r?.baseCurrency) {
    form.baseCurrency = String(r.baseCurrency).toUpperCase();
  } else if (form.symbolId > 0) {
    applyAutoBaseFromSymbol();
  } else {
    form.baseCurrency = "";
  }
};

const applyAutoBaseFromSymbol = () => {
  const sym = props.symbols.find((s) => Number(s.id) === Number(form.symbolId));
  form.baseCurrency = sym?.currency ? String(sym.currency).toUpperCase() : "";
};

const prefillManualRateFromTable = () => {
  const code = String(form.baseCurrency || "").toUpperCase();
  if (!code) {
    form.exchangeRate = null;
    return;
  }
  const rate = fiatRatesMap.value[code];
  if (rate != null && rate > 0) {
    form.exchangeRate = roundExchangeRate(rate);
  }
};

const normalizeExchangeRateInput = () => {
  if (form.exchangeRate == null || form.exchangeRate === "") return;
  form.exchangeRate = roundExchangeRate(form.exchangeRate);
};

const onManualBaseCurrencyChange = () => {
  prefillManualRateFromTable();
};

const onSymbolChange = () => {
  form.targetCurrency = "USD";
  if (form.syncMode === "auto") {
    applyAutoBaseFromSymbol();
    form.exchangeRate = null;
  } else {
    if (!form.baseCurrency) applyAutoBaseFromSymbol();
    prefillManualRateFromTable();
  }
};

const onSymbolInput = (event) => {
  if (form.symbolId > 0) {
    form.symbolId = 0;
    form.baseCurrency = "";
  }
  symbolSearchKeyword.value = event.target.value;
  showSymbolDropdown.value = true;
};

const onSymbolFocus = () => {
  showSymbolDropdown.value = true;
};

const onSymbolBlur = () => {
  setTimeout(() => {
    showSymbolDropdown.value = false;
  }, 200);
};

const selectSymbol = (sym) => {
  form.symbolId = Number(sym.id);
  symbolSearchKeyword.value = "";
  showSymbolDropdown.value = false;
  onSymbolChange();
};

const clearSymbol = () => {
  form.symbolId = 0;
  form.baseCurrency = "";
  form.exchangeRate = null;
  symbolSearchKeyword.value = "";
  showSymbolDropdown.value = true;
  nextTick(() => symbolInputRef.value?.focus());
};

watch(() => [props.mode, props.rate], resetForm, { immediate: true });
watch(
  () => props.symbols,
  () => {
    if (form.symbolId > 0 && !form.baseCurrency) {
      onSymbolChange();
    }
  },
);
watch(
  () => form.syncMode,
  (mode, prev) => {
    if (mode === prev) return;
    if (mode === "auto") {
      applyAutoBaseFromSymbol();
      form.exchangeRate = null;
    } else {
      if (!form.baseCurrency) applyAutoBaseFromSymbol();
      if (form.exchangeRate == null || form.exchangeRate <= 0) {
        prefillManualRateFromTable();
      }
    }
  },
);

onMounted(() => {
  loadFiatRates();
});

const submit = () => {
  if (!isValid.value) return;
  const payload = {
    symbolId: form.symbolId,
    targetCurrency: form.targetCurrency,
    syncMode: form.syncMode,
    remarks: form.remarks?.trim() || "",
  };
  if (form.syncMode === "manual") {
    payload.baseCurrency = form.baseCurrency;
    payload.exchangeRate = roundExchangeRate(form.exchangeRate);
  }
  emit("save", payload);
};
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
  animation: ibExRateFadeIn 0.3s ease;
}

@keyframes ibExRateFadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.modal {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  max-width: 560px;
  width: 92vw;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
  animation: ibExRateSlideUp 0.3s ease;
}

@keyframes ibExRateSlideUp {
  from {
    opacity: 0;
    transform: translateY(-20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.modal-header {
  flex-shrink: 0;
  padding: 22px 28px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--color-surface-soft);
}

.modal-title {
  font-size: 20px;
  font-weight: 600;
  color: var(--color-ink);
  margin: 0;
}

.modal-close {
  background: var(--color-border);
  border: none;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
  font-size: 16px;
  color: var(--color-text);
}

.modal-close:hover {
  background: var(--color-brand-solid);
  color: #fff;
}

.modal-body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: 28px;
}

.ib-ex-rate-modal__error {
  flex-shrink: 0;
  padding: 12px 28px;
  background: var(--color-danger-soft);
  color: var(--color-danger);
  font-size: 14px;
  line-height: 1.5;
  display: flex;
  align-items: flex-start;
  gap: 8px;
  border-top: 1px solid var(--color-danger-border);
}

.ib-ex-rate-modal__error i {
  margin-top: 2px;
  flex-shrink: 0;
}

.form-group {
  margin-bottom: 20px;
}

.form-label {
  display: block;
  font-size: 14px;
  font-weight: 500;
  color: var(--color-text);
  margin-bottom: 8px;
}

.form-input,
.form-select,
.form-textarea {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  transition: all 0.2s ease;
  box-sizing: border-box;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.form-input:disabled {
  background: var(--color-surface-soft);
  color: var(--color-faint);
  cursor: not-allowed;
}

.form-textarea {
  resize: vertical;
  min-height: 80px;
}

.ib-ex-rate-modal {
  max-width: 560px;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.form-label.required::after {
  content: " *";
  color: var(--color-danger);
}

.form-hint {
  margin: 6px 0 0;
  font-size: 14px;
  color: var(--color-faint);
}

.form-hint--between {
  display: flex;
  justify-content: space-between;
}

.ib-ex-symbol-search {
  position: relative;
}

.ib-ex-symbol-search__input-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.ib-ex-symbol-search__icon {
  position: absolute;
  left: 14px;
  color: var(--color-faint);
  font-size: 14px;
  pointer-events: none;
  z-index: 1;
}

.ib-ex-symbol-search__input {
  width: 100%;
  padding: 12px 40px 12px 40px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  color: var(--color-ink);
  background: var(--color-surface);
  box-sizing: border-box;
  transition:
    border-color 0.2s,
    box-shadow 0.2s;
}

.ib-ex-symbol-search__input:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.ib-ex-symbol-search__input::placeholder {
  color: var(--color-faint);
}

.ib-ex-symbol-search__clear {
  position: absolute;
  right: 10px;
  width: 24px;
  height: 24px;
  border: none;
  background: transparent;
  color: var(--color-faint);
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  transition:
    color 0.2s,
    background 0.2s;
}

.ib-ex-symbol-search__clear:hover {
  background: var(--color-surface-soft);
  color: var(--color-text);
}

.ib-ex-symbol-search__dropdown {
  position: absolute;
  left: 0;
  right: 0;
  top: calc(100% + 6px);
  z-index: 10;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
  overflow: hidden;
}

.ib-ex-symbol-search__empty {
  padding: 14px 16px;
  font-size: 14px;
  color: var(--color-faint);
  text-align: center;
}

.ib-ex-symbol-search__list {
  max-height: 220px;
  overflow-y: auto;
}

.ib-ex-symbol-search__option {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 16px;
  border: none;
  background: var(--color-surface);
  text-align: left;
  cursor: pointer;
  transition: background 0.15s;
}

.ib-ex-symbol-search__option:hover,
.ib-ex-symbol-search__option.active {
  background: var(--color-surface-soft);
}

.ib-ex-symbol-search__option-name {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
}

.ib-ex-symbol-search__option-meta {
  font-size: 14px;
  color: var(--color-muted);
  flex-shrink: 0;
}

.sync-mode-options {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.sync-mode-option {
  display: flex;
  gap: 10px;
  padding: 12px 14px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  cursor: pointer;
}

.sync-mode-option.active {
  border-color: var(--color-brand);
  background: var(--color-surface-soft);
}

.sync-mode-option input {
  margin-top: 4px;
}

.sync-mode-option__title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 600;
  color: var(--color-ink);
}

.sync-mode-option__badge {
  font-size: 14px;
  padding: 2px 8px;
  border-radius: 999px;
  background: var(--color-brand-solid);
  color: #fff;
}

.sync-mode-option__desc {
  display: block;
  margin-top: 4px;
  font-size: 14px;
  color: var(--color-muted);
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 16px 24px 24px;
  flex-shrink: 0;
  border-top: 1px solid var(--color-border);
  background: var(--color-surface);
}

.btn-cancel {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  color: var(--color-text);
  padding: 10px 18px;
  border-radius: var(--radius-md);
  cursor: pointer;
}

.btn-save {
  background: var(--color-brand-solid);
  border: none;
  color: #fff;
  padding: 10px 18px;
  border-radius: var(--radius-md);
  cursor: pointer;
}

.btn-save:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
