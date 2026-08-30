<template>
  <div class="modal-overlay" @click="$emit('close')">
    <div class="modal" @click.stop>
      <div class="modal-header">
        <h2 class="modal-title">
          <i class="fas fa-plus-circle"></i> {{ t("ibRuleModal_create_title") }}
        </h2>
        <button
          type="button"
          class="modal-close"
          :aria-label="t('ibIrModal_ariaClose')"
          @click="$emit('close')"
        >
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="modal-body">
        <form @submit.prevent="saveRule">
          <div class="form-group">
            <label class="form-label required">{{
              t("ibRuleMgmt_th_ruleName")
            }}</label>
            <input
              type="text"
              v-model="ruleForm.ruleName"
              :placeholder="t('ibRuleModal_placeholder_ruleName')"
              required
              class="form-input"
            />
          </div>

          <div class="form-group">
            <label class="form-label required">{{
              t("ibRuleMgmt_th_ruleType")
            }}</label>
            <select v-model="ruleForm.ruleType" required class="form-select">
              <option
                v-for="opt in visibleRuleTypeOptions"
                :key="opt.value"
                :value="opt.value"
              >
                {{ opt.label }}
              </option>
            </select>
            <p
              v-if="ruleTypeFormula(ruleForm.ruleType)"
              class="form-hint formula-hint"
            >
              {{ ruleTypeFormula(ruleForm.ruleType) }}
            </p>
          </div>

          <div class="form-group">
            <label class="form-label">{{
              t("ibRuleModal_label_tradingPlatform")
            }}</label>
            <select
              v-model="ruleForm.trading_platforms_key"
              class="form-select"
              @change="onPlatformChange"
            >
              <option value="">
                {{ t("ibRuleModal_placeholder_selectPlatform") }}
              </option>
              <option v-for="p in platforms" :key="p.key" :value="p.key">
                {{ p.name }} ({{ p.shortCode }})
              </option>
            </select>
            <p class="form-hint">{{ t("ibRuleModal_hint_platformSteps") }}</p>
          </div>
          <div class="form-group">
            <label class="form-label">{{
              t("ibRuleModal_label_productType")
            }}</label>
            <select
              v-model="ruleForm.product_type"
              class="form-select"
              @change="ruleForm.products = []"
            >
              <option value="">
                {{ t("ibRuleModal_placeholder_selectType") }}
              </option>
              <option value="securities">
                {{ t("ibRuleModal_product_securities") }}
              </option>
              <option value="symbols">
                {{ t("ibRuleModal_product_symbols") }}
              </option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">{{ t("ibRuleMgmt_th_product") }}</label>
            <div
              class="multi-select-panel"
              :class="{ 'is-disabled': !ruleForm.product_type }"
            >
              <div class="multi-select-search">
                <i class="fas fa-search"></i>
                <input
                  v-model="productSearch"
                  type="text"
                  class="multi-select-search__input"
                  :placeholder="t('ibRuleModal_search_products')"
                  :disabled="!ruleForm.product_type"
                />
              </div>
              <div class="multi-select-actions">
                <span class="multi-select-summary">{{
                  selectedProductsLabel
                }}</span>
                <div class="multi-select-actions__buttons">
                  <button
                    type="button"
                    class="multi-select-link"
                    @click="selectAllProducts"
                    :disabled="
                      !ruleForm.product_type ||
                      filteredAvailableProducts.length === 0
                    "
                  >
                    {{ t("ibRuleModal_selectAll") }}
                  </button>
                  <button
                    type="button"
                    class="multi-select-link"
                    @click="clearProductsSelection"
                    :disabled="
                      !ruleForm.product_type || ruleForm.products.length === 0
                    "
                  >
                    {{ t("ibRuleModal_clearSelection") }}
                  </button>
                </div>
              </div>
              <div class="multi-select-options">
                <label
                  v-for="product in filteredAvailableProducts"
                  :key="product.key"
                  class="multi-select-option"
                >
                  <input
                    :checked="ruleForm.products.includes(product.name)"
                    type="checkbox"
                    :disabled="!ruleForm.product_type"
                    @change="toggleProductSelection(product.name)"
                  />
                  <span>{{ product.name }}</span>
                </label>
                <div
                  v-if="
                    ruleForm.product_type &&
                    filteredAvailableProducts.length === 0
                  "
                  class="multi-select-empty"
                >
                  {{ t("ibRuleModal_products_notFound") }}
                </div>
                <div
                  v-else-if="!ruleForm.product_type"
                  class="multi-select-empty"
                >
                  {{ t("ibRuleModal_products_pickTypeFirst") }}
                </div>
              </div>
            </div>
            <p v-if="!ruleForm.product_type" class="form-hint">
              {{ t("ibRuleModal_hint_pickProductType") }}
            </p>
            <p v-else class="form-hint">
              {{ t("ibRuleModal_hint_tickProducts") }}
            </p>
          </div>

          <div class="form-group">
            <label class="form-label required">{{
              t("ibRuleMgmt_th_targetRegion")
            }}</label>
            <select
              v-model="ruleForm.targetRegion"
              required
              class="form-select"
            >
              <option value="">
                {{ t("ibRuleModal_placeholder_region") }}
              </option>
              <option value="global">
                {{ t("ibRuleModal_region_global") }}
              </option>
              <option value="asia">{{ t("ibRuleModal_region_asia") }}</option>
              <option value="europe">
                {{ t("ibRuleModal_region_europe") }}
              </option>
              <option value="americas">
                {{ t("ibRuleModal_region_americas") }}
              </option>
              <option value="mena">{{ t("ibRuleModal_region_mena") }}</option>
            </select>
          </div>

          <!-- 币种暂时隐藏，默认仍提交 USD
          <div class="form-group">
            <label class="form-label">{{ t('ibRuleMgmt_th_currency') }}</label>
            <select v-model="ruleForm.payoutCurrency" class="form-select">
              <option value="USD">USD</option>
              <option value="EUR">EUR</option>
              <option value="GBP">GBP</option>
              <option value="JPY">JPY</option>
              <option value="AUD">AUD</option>
            </select>
          </div>
          -->

          <div class="form-group">
            <label class="form-label">{{
              t("ibRuleModal_label_minPayoutUsd")
            }}</label>
            <input
              type="number"
              v-model.number="ruleForm.minimumPayout"
              step="0.01"
              min="0"
              placeholder="100"
              class="form-input"
            />
          </div>

          <div class="form-group">
            <label class="form-label">{{
              t("ibRuleModal_label_minTrade")
            }}</label>
            <input
              type="number"
              v-model.number="ruleForm.minimum_trade"
              step="0.01"
              min="0"
              class="form-input"
              placeholder="0"
            />
          </div>
          <div class="form-group">
            <label class="form-label">{{
              t("ibRuleModal_label_maxTrade")
            }}</label>
            <input
              type="number"
              v-model.number="ruleForm.maximum_trade"
              step="0.01"
              min="0"
              class="form-input"
              placeholder="0"
            />
          </div>
          <div v-if="formulaFieldVisibility.showRate" class="form-group">
            <label class="form-label">{{ t("ibRuleMgmt_th_rate") }}</label>
            <input
              type="number"
              v-model.number="ruleForm.rate"
              step="0.0001"
              min="0"
              class="form-input"
              placeholder="0"
            />
          </div>
          <div class="form-group">
            <label class="form-label required">{{
              t("ibRuleMgmt_th_tier")
            }}</label>
            <select v-model="ruleForm.tierId" class="form-select" required>
              <option :value="null">
                {{ t("ibRuleModal_placeholder_tier") }}
              </option>
              <option
                v-for="tierOpt in tierLevels"
                :key="tierOpt.id"
                :value="tierOpt.id"
              >
                {{
                  tParams("ibRuleModal_tier_option", "Tier {level} - {name}", {
                    level: tierOpt.tierLevel,
                    name: tierOpt.tierName,
                  })
                }}
              </option>
            </select>
          </div>
          <div v-if="formulaFieldVisibility.showFixedAmount" class="form-group">
            <label class="form-label">{{
              t("ibRuleMgmt_th_fixedAmount")
            }}</label>
            <input
              type="number"
              v-model.number="ruleForm.fixed_amount"
              step="0.01"
              min="0"
              class="form-input"
              placeholder="0"
            />
          </div>
          <div
            v-if="formulaFieldVisibility.showRebateAmount"
            class="form-group"
          >
            <label class="form-label required">{{
              t("ibRuleMgmt_th_rebateAmount")
            }}</label>
            <input
              type="number"
              v-model.number="ruleForm.rebateAmount"
              step="0.00001"
              min="0.00001"
              class="form-input"
              placeholder="0"
            />
          </div>

          <div class="form-group">
            <label class="form-label required">{{
              t("ibRuleMgmt_th_paymentCycle")
            }}</label>
            <select
              v-model="ruleForm.paymentCycle"
              @change="handlePaymentCycleChange"
              required
              class="form-select"
            >
              <option value="">{{ t("ibRuleModal_placeholder_cycle") }}</option>
              <option value="realtime">
                {{ t("ibRuleMgmt_payCycle_realtime") }}
              </option>
              <option value="daily">
                {{ t("ibRuleMgmt_payCycle_daily") }}
              </option>
              <option value="weekly">
                {{ t("ibRuleMgmt_payCycle_weekly") }}
              </option>
              <option value="biweekly">
                {{ t("ibRuleMgmt_payCycle_biweekly") }}
              </option>
              <option value="monthly">
                {{ t("ibRuleMgmt_payCycle_monthly") }}
              </option>
              <option value="quarterly">
                {{ t("ibRuleMgmt_payCycle_quarterly") }}
              </option>
            </select>
          </div>
          <div class="form-group">
            <label
              class="form-label"
              :class="{ required: ruleForm.paymentCycle !== 'realtime' }"
              >{{ t("ibRuleModal_label_paymentDay") }}</label
            >
            <input
              v-if="ruleForm.paymentCycle === 'realtime'"
              type="text"
              v-model="ruleForm.paymentDay"
              class="form-input"
              disabled
              style="
                background: var(--color-surface-soft);
                color: var(--color-faint);
                cursor: not-allowed;
              "
            />
            <select
              v-else-if="ruleForm.paymentCycle === 'daily'"
              v-model="ruleForm.paymentDay"
              required
              class="form-select"
            >
              <option value="">
                {{ t("ibRuleModal_placeholder_selectShort") }}
              </option>
              <option value="everyday">
                {{ t("ibRuleModal_payDay_daily_everyday") }}
              </option>
              <option value="weekdays">
                {{ t("ibRuleModal_payDay_daily_weekdays") }}
              </option>
              <option value="weekends">
                {{ t("ibRuleModal_payDay_daily_weekends") }}
              </option>
            </select>
            <select
              v-else-if="ruleForm.paymentCycle === 'weekly'"
              v-model="ruleForm.paymentDay"
              required
              class="form-select"
            >
              <option value="">
                {{ t("ibRuleModal_placeholder_selectDay") }}
              </option>
              <option value="Monday">{{ t("ibRuleModal_day_monday") }}</option>
              <option value="Tuesday">
                {{ t("ibRuleModal_day_tuesday") }}
              </option>
              <option value="Wednesday">
                {{ t("ibRuleModal_day_wednesday") }}
              </option>
              <option value="Thursday">
                {{ t("ibRuleModal_day_thursday") }}
              </option>
              <option value="Friday">{{ t("ibRuleModal_day_friday") }}</option>
              <option value="Saturday">
                {{ t("ibRuleModal_day_saturday") }}
              </option>
              <option value="Sunday">{{ t("ibRuleModal_day_sunday") }}</option>
            </select>
            <select
              v-else-if="ruleForm.paymentCycle === 'biweekly'"
              v-model="ruleForm.paymentDay"
              required
              class="form-select"
            >
              <option value="">
                {{ t("ibRuleModal_placeholder_selectShort") }}
              </option>
              <option value="1-15">
                {{ t("ibRuleModal_payDay_bi_1_15") }}
              </option>
              <option value="5-20">
                {{ t("ibRuleModal_payDay_bi_5_20") }}
              </option>
              <option value="10-25">
                {{ t("ibRuleModal_payDay_bi_10_25") }}
              </option>
              <option value="15-30">
                {{ t("ibRuleModal_payDay_bi_15_30") }}
              </option>
            </select>
            <select
              v-else-if="ruleForm.paymentCycle === 'monthly'"
              v-model="ruleForm.paymentDay"
              required
              class="form-select"
            >
              <option value="">
                {{ t("ibRuleModal_placeholder_selectDay") }}
              </option>
              <option value="1">{{ t("ibRuleModal_payDay_monthly_1") }}</option>
              <option value="5">{{ t("ibRuleModal_payDay_monthly_5") }}</option>
              <option value="10">
                {{ t("ibRuleModal_payDay_monthly_10") }}
              </option>
              <option value="15">
                {{ t("ibRuleModal_payDay_monthly_15") }}
              </option>
              <option value="20">
                {{ t("ibRuleModal_payDay_monthly_20") }}
              </option>
              <option value="25">
                {{ t("ibRuleModal_payDay_monthly_25") }}
              </option>
              <option value="last">
                {{ t("ibRuleModal_payDay_monthly_last") }}
              </option>
            </select>
            <select
              v-else-if="ruleForm.paymentCycle === 'quarterly'"
              v-model="ruleForm.paymentDay"
              required
              class="form-select"
            >
              <option value="">
                {{ t("ibRuleModal_placeholder_selectShort") }}
              </option>
              <option value="1">{{ t("ibRuleModal_payDay_q_1") }}</option>
              <option value="15">{{ t("ibRuleModal_payDay_q_15") }}</option>
              <option value="last">{{ t("ibRuleModal_payDay_q_last") }}</option>
            </select>
            <input
              v-else
              type="text"
              v-model="ruleForm.paymentDay"
              :placeholder="t('ibRuleModal_paymentDay_placeholder')"
              required
              class="form-input"
            />
          </div>

          <div class="form-group">
            <label class="form-label">{{
              t("ibRuleMgmt_th_description")
            }}</label>
            <textarea
              v-model="ruleForm.ruleDescription"
              :placeholder="t('ibRuleModal_placeholder_desc')"
              class="form-textarea"
            ></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">{{ t("ibTierMgmt_label_status") }}</label>
            <select v-model="ruleForm.status" class="form-select">
              <option value="draft">{{ t("ibTierMgmt_status_draft") }}</option>
              <option value="active">
                {{ t("ibTierMgmt_status_active") }}
              </option>
              <option value="inactive">
                {{ t("ibTierMgmt_status_inactive") }}
              </option>
            </select>
          </div>
        </form>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" @click="$emit('close')">
          {{ t("ibTierMgmt_btn_cancel") }}
        </button>
        <button
          type="button"
          class="btn btn-primary"
          @click="saveRule"
          :disabled="saving"
        >
          <i class="fas" :class="saving ? 'fa-spinner fa-spin' : 'fa-save'"></i>
          {{
            saving
              ? t("ibRuleModal_btn_creating")
              : t("ibRuleModal_btn_createRule")
          }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from "vue";
import ibRulesApi from "@/services/ibRulesApi";
import ibSettingsApi from "@/services/ibSettingsApi";
import ibTierLevelsApi from "@/services/ibTierLevelsApi";
import loginSettingsService from "@/services/loginSettingsService";
import { useAdminI18n } from "@/composables/useAdminI18n";
import {
  getFormulaFieldVisibility,
  buildFormulaFieldsPayload,
  validateFormulaFields,
  clearHiddenFormulaFields,
  getFormulaCommissionRate,
} from "@/composables/useIbRuleFormulaFields";

const emit = defineEmits(["close", "created"]);

const { t, tParams } = useAdminI18n();

const RULE_TYPE_I18N = {
  cash_back_rebate: "ibRuleMgmt_ruleType_cash_back_rebate",
  per_lot: "ibRuleMgmt_ruleType_per_lot",
  per_trade: "ibRuleMgmt_ruleType_per_trade",
  per_trade_rebate: "ibRuleMgmt_ruleType_per_trade_rebate",
  per_lot_rebate: "ibRuleMgmt_ruleType_per_lot_rebate",
  percentage: "ibRuleMgmt_ruleType_percentage",
  hybrid: "ibRuleMgmt_ruleType_hybrid",
};

const ruleTypeOptions = computed(() =>
  Object.keys(RULE_TYPE_I18N).map((value) => ({
    value,
    label: t(RULE_TYPE_I18N[value]),
  })),
);
const visibleRuleTypeOptions = computed(() =>
  ruleTypeOptions.value.filter(
    (o) => o.value !== "percentage" && o.value !== "hybrid",
  ),
);

const saving = ref(false);
const platforms = ref([]);
const customSecurities = ref([]);
const customSymbols = ref([]);
const tierLevels = ref([]);
const productSearch = ref("");

const allSecurities = computed(() => customSecurities.value);
const availableProducts = computed(() => {
  if (ruleForm.product_type === "securities") {
    return allSecurities.value.map((security) => ({
      key: `security-${security.id}`,
      id: security.id,
      name: security.securityName,
      productType: "security",
    }));
  }

  if (ruleForm.product_type === "symbols") {
    return customSymbols.value.map((symbol) => ({
      key: `symbol-${symbol.id}`,
      id: symbol.id,
      name: symbol.symbolName,
      productType: "symbol",
    }));
  }

  return [];
});
const filteredAvailableProducts = computed(() => {
  const keyword = productSearch.value.trim().toLowerCase();
  if (!keyword) return availableProducts.value;
  return availableProducts.value.filter((product) =>
    product.name.toLowerCase().includes(keyword),
  );
});
const selectedProductsLabel = computed(() => {
  if (!ruleForm.product_type) return t("ibRuleModal_summary_needType");
  if (ruleForm.products.length === 0) return t("ibRuleModal_summary_none");
  return tParams("ibRuleModal_summary_selected", "{names} ({count})", {
    names: ruleForm.products.join(", "),
    count: ruleForm.products.length,
  });
});

const ruleTypeFormula = (ruleType) => {
  const keys = {
    cash_back_rebate: "ibRuleModal_formula_cash_back",
    per_lot: "ibRuleModal_formula_per_lot",
    per_trade: "ibRuleModal_formula_per_trade",
    per_trade_rebate: "ibRuleModal_formula_per_trade_rebate",
    per_lot_rebate: "ibRuleModal_formula_per_lot_rebate",
  };
  const k = keys[ruleType];
  return k ? t(k) : "";
};

const formulaFieldVisibility = computed(() =>
  getFormulaFieldVisibility(ruleForm.ruleType),
);

const ruleForm = reactive({
  ruleName: "",
  ruleType: "cash_back_rebate",
  ruleDescription: "",
  targetRegion: "global",
  paymentCycle: "monthly",
  paymentDay: "5",
  minimumPayout: 100.0,
  payoutCurrency: "USD",
  autoPaymentEnabled: true,
  status: "active",
  trading_platforms_key: "",
  product_type: "",
  products: [],
  tierId: null,
  minimum_trade: null,
  maximum_trade: null,
  rate: null,
  fixed_amount: null,
  rebateAmount: null,
});

watch(
  () => ruleForm.ruleType,
  (ruleType) => {
    clearHiddenFormulaFields(ruleType, ruleForm);
  },
);

const onPlatformChange = () => {
  ruleForm.product_type = "";
  ruleForm.products = [];
  productSearch.value = "";
  const key = ruleForm.trading_platforms_key;
  if (key) {
    loadCustomSecurities(key);
    loadCustomSymbols(key);
  } else {
    customSecurities.value = [];
    customSymbols.value = [];
  }
};

const toggleProductSelection = (productName) => {
  if (ruleForm.products.includes(productName)) {
    ruleForm.products = ruleForm.products.filter(
      (name) => name !== productName,
    );
    return;
  }
  ruleForm.products = [...ruleForm.products, productName];
};

const selectAllProducts = () => {
  const merged = new Set([
    ...ruleForm.products,
    ...filteredAvailableProducts.value.map((product) => product.name),
  ]);
  ruleForm.products = Array.from(merged);
};

const clearProductsSelection = () => {
  ruleForm.products = [];
};

const getPrimaryProductId = () => {
  if (!ruleForm.products.length) return null;
  const selectedName = ruleForm.products[0];
  const selectedProduct = availableProducts.value.find(
    (product) => product.name === selectedName,
  );
  return selectedProduct ? selectedProduct.id : null;
};

const getDefaultCommissionType = (ruleType) => {
  if (ruleType === "cash_back_rebate") return "cashback";
  return ruleType || "per_lot";
};

const getDefaultCommissionRate = () =>
  getFormulaCommissionRate(ruleForm.ruleType, ruleForm);

const buildProductsPayload = () => {
  const productType =
    ruleForm.product_type === "symbols" ? "symbol" : "security";
  const commissionType = getDefaultCommissionType(ruleForm.ruleType);
  const commissionRate = getDefaultCommissionRate();

  return ruleForm.products.map((productName) => {
    const ap = availableProducts.value.find((p) => p.name === productName);
    return {
      productType,
      productName,
      productId: ap?.id,
      commissionType,
      commissionRate,
      additionalRate: 0,
      minimumVolume: "0.01 lots",
    };
  });
};

/**
 * 处理支付周期变更
 */
const handlePaymentCycleChange = () => {
  // 根据不同的支付周期设置合适的默认值
  const defaultValues = {
    realtime: "immediate",
    daily: "everyday",
    weekly: "Monday",
    biweekly: "1-15",
    monthly: "5",
    quarterly: "1",
  };

  const newCycle = ruleForm.paymentCycle;

  // 如果是切换周期，则清空或设置默认值
  if (defaultValues[newCycle]) {
    // 对于 realtime，总是设置为 immediate
    if (newCycle === "realtime") {
      ruleForm.paymentDay = defaultValues[newCycle];
    }
    // 对于其他周期，如果当前值是 immediate 或空，则设置默认值
    else if (
      !ruleForm.paymentDay ||
      ruleForm.paymentDay === "immediate" ||
      ruleForm.paymentDay === "0"
    ) {
      ruleForm.paymentDay = defaultValues[newCycle];
    } else {
      // 清空当前值，让用户重新选择
      ruleForm.paymentDay = "";
    }
  }
};

/**
 * 保存规则
 */
const saveRule = async () => {
  if (ruleForm.ruleType === "percentage" || ruleForm.ruleType === "hybrid") {
    alert(t("ibRuleModal_alert_ruleTypeDev"));
    return;
  }
  if (!ruleForm.ruleName || !ruleForm.paymentCycle) {
    alert(t("ibRuleModal_alert_required"));
    return;
  }
  if (ruleForm.tierId == null || ruleForm.tierId === "") {
    alert(t("ibRuleModal_alert_selectTier"));
    return;
  }
  if (ruleForm.product_type && ruleForm.products.length === 0) {
    alert(t("ibRuleModal_alert_selectProduct"));
    return;
  }
  const formulaErrorKey = validateFormulaFields(ruleForm.ruleType, ruleForm);
  if (formulaErrorKey) {
    alert(t(formulaErrorKey));
    return;
  }

  try {
    saving.value = true;
    const primaryProductId = getPrimaryProductId();

    const productsPayload = buildProductsPayload();
    if (ruleForm.product_type && productsPayload.some((p) => !p.productId)) {
      alert(t("ibRuleModal_alert_selectProduct"));
      return;
    }

    const data = {
      ruleName: ruleForm.ruleName,
      ruleType: ruleForm.ruleType || "cash_back_rebate",
      ruleDescription: ruleForm.ruleDescription,
      targetRegion: ruleForm.targetRegion,
      paymentCycle: ruleForm.paymentCycle,
      paymentDay: ruleForm.paymentDay,
      minimumPayout: ruleForm.minimumPayout,
      payoutCurrency: ruleForm.payoutCurrency,
      autoPaymentEnabled: ruleForm.autoPaymentEnabled ? 1 : 0,
      status: ruleForm.status,
      trading_platforms_key: ruleForm.trading_platforms_key || null,
      product_type: ruleForm.product_type || null,
      product: primaryProductId,
      products: productsPayload,
      tierId: ruleForm.tierId,
      minimum_trade: ruleForm.minimum_trade,
      maximum_trade: ruleForm.maximum_trade,
      ...buildFormulaFieldsPayload(ruleForm.ruleType, ruleForm),
    };

    const response = await ibRulesApi.createRule(data);

    if (response.success) {
      alert(t("ibRuleModal_alert_createOk"));
      emit("created");
    } else {
      alert(
        tParams(
          "ibRuleModal_alert_createFail",
          "Failed to create rule: {msg}",
          { msg: response.message ?? "" },
        ),
      );
    }
  } catch (error) {
    console.error("Failed to create rule:", error);
    alert(t("ibRuleModal_alert_createFailGeneric"));
  } finally {
    saving.value = false;
  }
};

/**
 * 加载自定义证券列表（按平台）
 */
const loadCustomSecurities = async (platformKey) => {
  try {
    const response =
      await ibSettingsApi.getCustomSecuritiesByPlatform(platformKey);
    if (response?.success && response.data != null) {
      customSecurities.value = Array.isArray(response.data)
        ? response.data
        : response.data.items || response.data;
    } else {
      customSecurities.value = [];
    }
  } catch (error) {
    console.error("Failed to load custom securities:", error);
    customSecurities.value = [];
  }
};

/**
 * 加载自定义交易对列表（按平台）
 */
const loadCustomSymbols = async (platformKey) => {
  try {
    const response =
      await ibSettingsApi.getCustomSymbolsByPlatform(platformKey);
    if (response?.success && response.data != null) {
      customSymbols.value = Array.isArray(response.data)
        ? response.data
        : response.data.items || response.data;
    } else {
      customSymbols.value = [];
    }
  } catch (error) {
    console.error("Failed to load custom symbols:", error);
    customSymbols.value = [];
  }
};

/**
 * 加载 Tier 列表
 */
const loadTierLevels = async () => {
  try {
    const response = await ibTierLevelsApi.getTierLevels();
    if (response.success && response.data) {
      tierLevels.value = response.data.items || response.data;
    }
  } catch (error) {
    console.error("Failed to load tier levels:", error);
  }
};

const loadPlatforms = async () => {
  try {
    const res = await loginSettingsService.getTradingGroupPlatforms();
    const list = Array.isArray(res?.data) ? res.data : [];
    platforms.value = list
      .map((p) => ({
        key: p.key ?? p.platformKey ?? "",
        name: p.name ?? p.displayName ?? p.key ?? "",
        shortCode:
          p.shortCode ??
          (p.key === "financepro" ? "FinancePro" : (p.key || "").toUpperCase()),
      }))
      .filter((p) => p.key);
    // 默认选择第一个可用平台
    if (platforms.value.length > 0 && !ruleForm.trading_platforms_key) {
      ruleForm.trading_platforms_key = platforms.value[0].key;
      onPlatformChange();
    }
  } catch (e) {
    console.error("Failed to load platforms:", e);
  }
};

onMounted(() => {
  loadPlatforms();
  loadTierLevels();
});
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
  z-index: 1000;
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
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
  max-width: 1000px;
  width: 90%;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
  animation: slideUp 0.3s ease;
}

@keyframes slideUp {
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
  padding: 25px 30px;
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
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 0;
}

.modal-title i {
  color: var(--color-brand);
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
  font-size: 18px;
  color: var(--color-text);
}

.modal-close:hover {
  background: var(--color-brand-solid);
  color: white;
}

.modal-body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: 30px;
}

.form-section {
  background: var(--color-surface-soft);
  padding: 15px;
  border-radius: var(--radius-md);
  margin-bottom: 20px;
}

.form-section h4 {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 15px;
}

.form-section h4 i {
  color: var(--color-brand);
  margin-right: 8px;
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

.form-label.required::after {
  content: " *";
  color: var(--color-danger);
}

.form-hint {
  margin: 6px 0 0;
  font-size: 12px;
  color: var(--color-muted);
}
.form-hint--warn {
  color: var(--color-warning);
  font-weight: 500;
}

.form-input,
.form-select,
.form-textarea {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  transition: all 0.3s ease;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.form-textarea {
  resize: vertical;
  min-height: 80px;
}

.multi-select-panel.is-disabled {
  opacity: 0.7;
}

.multi-select-panel {
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 12px;
}

.multi-select-search {
  position: relative;
  margin-bottom: 10px;
}

.multi-select-search i {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-faint);
}

.multi-select-search__input {
  width: 100%;
  padding: 10px 12px 10px 36px;
  border: 1px solid #dbe3ee;
  border-radius: var(--radius-md);
  font-size: 14px;
}

.multi-select-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 10px;
}

.multi-select-summary {
  font-size: 13px;
  color: var(--color-text);
  flex: 1;
  min-width: 0;
  white-space: normal;
  word-break: break-word;
}

.multi-select-actions__buttons {
  display: flex;
  align-items: center;
  gap: 12px;
}

.multi-select-link {
  border: none;
  background: transparent;
  color: var(--color-brand);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  padding: 0;
}

.multi-select-link:disabled {
  color: var(--color-faint);
  cursor: not-allowed;
}

.multi-select-options {
  max-height: 220px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.multi-select-option {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
  border-radius: var(--radius-md);
  cursor: pointer;
}

.multi-select-option:hover {
  background: var(--color-surface-soft);
}

.multi-select-option input[type="checkbox"] {
  width: 16px;
  height: 16px;
  accent-color: var(--color-brand);
}

.multi-select-empty {
  padding: 12px 10px;
  color: var(--color-muted);
  font-size: 13px;
  text-align: center;
}

.form-checkbox {
  display: flex;
  align-items: center;
  gap: 8px;
}

.form-checkbox input[type="checkbox"] {
  width: 18px;
  height: 18px;
  accent-color: var(--color-brand);
  cursor: pointer;
}

.form-checkbox label {
  cursor: pointer;
  color: var(--color-text);
  font-size: 14px;
}

.table-wrapper {
  overflow-x: auto;
  margin-bottom: 15px;
}

.product-commission-table {
  width: 100%;
  border-collapse: collapse;
}

.product-commission-table th {
  background: var(--color-surface-soft);
  padding: 10px 12px;
  text-align: left;
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text);
  border: 1px solid var(--color-border);
}

.product-commission-table td {
  padding: 10px 12px;
  border: 1px solid var(--color-border);
}

.btn {
  padding: 12px 20px;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.btn-success {
  background: var(--color-success-solid);
  color: white;
}

.btn-success:hover {
  background: var(--color-success-solid);
}

.btn-icon {
  padding: 6px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.3s ease;
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-delete {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.btn-delete:hover {
  background: var(--color-danger-solid);
  color: white;
}

.modal-footer {
  flex-shrink: 0;
  padding: 20px 30px;
  border-top: 2px solid var(--color-border);
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  background: var(--color-surface-soft);
}

.btn-secondary {
  background: var(--color-border);
  color: var(--color-text);
}

.btn-secondary:hover {
  background: var(--color-border-strong);
}

.btn-primary {
  background: var(--color-brand-solid);
  color: white;
  box-shadow: 0 2px 8px rgba(var(--color-brand-rgb), 0.3);
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.4);
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
