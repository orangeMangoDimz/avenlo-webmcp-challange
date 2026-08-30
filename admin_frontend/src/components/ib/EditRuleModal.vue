<template>
  <div class="modal-overlay" @click="$emit('close')">
    <div class="modal" @click.stop>
      <div class="modal-header">
        <h2 class="modal-title">
          <i class="fas fa-edit"></i> {{ t("ibRuleModal_edit_title") }}
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
        <form v-if="rule" @submit.prevent="saveRule">
          <div class="form-group">
            <label class="form-label required">{{
              t("ibRuleMgmt_th_ruleName")
            }}</label>
            <input
              type="text"
              v-model="editForm.ruleName"
              :placeholder="t('ibRuleModal_placeholder_ruleName')"
              required
              class="form-input"
            />
          </div>

          <div class="form-group">
            <label class="form-label required">{{
              t("ibRuleMgmt_th_ruleType")
            }}</label>
            <select v-model="editForm.ruleType" required class="form-select">
              <option
                v-for="opt in visibleRuleTypeOptions"
                :key="opt.value"
                :value="opt.value"
              >
                {{ opt.label }}
              </option>
            </select>
            <p
              v-if="ruleTypeFormula(editForm.ruleType)"
              class="form-hint formula-hint"
            >
              {{ ruleTypeFormula(editForm.ruleType) }}
            </p>
            <p
              v-else-if="
                editForm.ruleType === 'percentage' ||
                editForm.ruleType === 'hybrid'
              "
              class="form-hint form-hint--warn"
            >
              {{ t("ibRuleModal_underDevelopment") }}
            </p>
          </div>

          <div class="form-group">
            <label class="form-label">{{
              t("ibRuleModal_label_tradingPlatform")
            }}</label>
            <select
              v-model="editForm.trading_platforms_key"
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
              v-model="editForm.product_type"
              class="form-select"
              @change="editForm.products = []"
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
              :class="{ 'is-disabled': !editForm.product_type }"
            >
              <div class="multi-select-search">
                <i class="fas fa-search"></i>
                <input
                  v-model="productSearch"
                  type="text"
                  class="multi-select-search__input"
                  :placeholder="t('ibRuleModal_search_products')"
                  :disabled="!editForm.product_type"
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
                      !editForm.product_type ||
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
                      !editForm.product_type || editForm.products.length === 0
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
                    :checked="editForm.products.includes(product.name)"
                    type="checkbox"
                    :disabled="!editForm.product_type"
                    @change="toggleProductSelection(product.name)"
                  />
                  <span>{{ product.name }}</span>
                </label>
                <div
                  v-if="
                    editForm.product_type &&
                    filteredAvailableProducts.length === 0
                  "
                  class="multi-select-empty"
                >
                  {{ t("ibRuleModal_products_notFound") }}
                </div>
                <div
                  v-else-if="!editForm.product_type"
                  class="multi-select-empty"
                >
                  {{ t("ibRuleModal_products_pickTypeFirst") }}
                </div>
              </div>
            </div>
            <p v-if="!editForm.product_type" class="form-hint">
              {{ t("ibRuleModal_hint_pickProductType") }}
            </p>
            <p v-else class="form-hint">
              {{ t("ibRuleModal_hint_tickProducts") }}
            </p>
          </div>

          <div class="form-group">
            <label class="form-label">{{
              t("ibRuleMgmt_th_targetRegion")
            }}</label>
            <select v-model="editForm.targetRegion" class="form-select">
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
            <select v-model="editForm.payoutCurrency" class="form-select">
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
              v-model.number="editForm.minimumPayout"
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
              v-model.number="editForm.minimum_trade"
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
              v-model.number="editForm.maximum_trade"
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
              v-model.number="editForm.rate"
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
            <select v-model="editForm.tierId" class="form-select" required>
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
              v-model.number="editForm.fixed_amount"
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
              v-model.number="editForm.rebateAmount"
              step="0.00001"
              min="0.00001"
              class="form-input"
              placeholder="0"
            />
          </div>

          <div class="form-group">
            <label class="form-label">{{
              t("ibRuleMgmt_th_paymentCycle")
            }}</label>
            <select
              v-model="editForm.paymentCycle"
              @change="handlePaymentCycleChange"
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
              :class="{ required: editForm.paymentCycle !== 'realtime' }"
              >{{ t("ibRuleModal_label_paymentDay") }}</label
            >
            <input
              v-if="editForm.paymentCycle === 'realtime'"
              type="text"
              v-model="editForm.paymentDay"
              class="form-input"
              disabled
              style="
                background: var(--color-surface-soft);
                color: var(--color-faint);
                cursor: not-allowed;
              "
            />
            <select
              v-else-if="editForm.paymentCycle === 'daily'"
              v-model="editForm.paymentDay"
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
              v-else-if="editForm.paymentCycle === 'weekly'"
              v-model="editForm.paymentDay"
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
              v-else-if="editForm.paymentCycle === 'biweekly'"
              v-model="editForm.paymentDay"
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
              v-else-if="editForm.paymentCycle === 'monthly'"
              v-model="editForm.paymentDay"
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
              v-else-if="editForm.paymentCycle === 'quarterly'"
              v-model="editForm.paymentDay"
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
              v-model="editForm.paymentDay"
              :placeholder="t('ibRuleModal_paymentDay_placeholder')"
              class="form-input"
            />
          </div>

          <div class="form-group">
            <label class="form-label">{{
              t("ibRuleMgmt_th_description")
            }}</label>
            <textarea
              v-model="editForm.ruleDescription"
              class="form-textarea"
              :placeholder="t('ibRuleModal_placeholder_desc')"
            ></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">{{ t("ibTierMgmt_label_status") }}</label>
            <select v-model="editForm.status" class="form-select">
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
          {{ saving ? t("ibRuleModal_btn_saving") : t("ibTierMgmt_btn_save") }}
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

const { t, tParams } = useAdminI18n();

const props = defineProps({
  rule: { type: Object, default: null },
});

const emit = defineEmits(["close", "saved"]);

const saving = ref(false);
const platforms = ref([]);
const customSecurities = ref([]);
const customSymbols = ref([]);
const tierLevels = ref([]);
const existingProducts = ref([]);
const productSearch = ref("");
const availableProducts = computed(() => {
  if (editForm.product_type === "securities") {
    return customSecurities.value.map((security) => ({
      key: `security-${security.id}`,
      id: security.id,
      name: security.securityName,
      productType: "security",
    }));
  }

  if (editForm.product_type === "symbols") {
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
  if (!editForm.product_type) return t("ibRuleModal_summary_needType");
  if (editForm.products.length === 0) return t("ibRuleModal_summary_none");
  return tParams("ibRuleModal_summary_selected", "{names} ({count})", {
    names: editForm.products.join(", "),
    count: editForm.products.length,
  });
});

const RULE_TYPE_I18N = {
  cash_back_rebate: "ibRuleMgmt_ruleType_cash_back_rebate",
  per_lot: "ibRuleMgmt_ruleType_per_lot",
  per_trade: "ibRuleMgmt_ruleType_per_trade",
  per_trade_rebate: "ibRuleMgmt_ruleType_per_trade_rebate",
  per_lot_rebate: "ibRuleMgmt_ruleType_per_lot_rebate",
  percentage: "ibRuleMgmt_ruleType_percentage",
  hybrid: "ibRuleMgmt_ruleType_hybrid",
};

const editForm = reactive({
  ruleName: "",
  ruleType: "cash_back_rebate",
  ruleDescription: "",
  targetRegion: "global",
  status: "active",
  trading_platforms_key: "",
  product_type: "",
  products: [],
  minimumPayout: 100,
  payoutCurrency: "USD",
  minimum_trade: null,
  maximum_trade: null,
  rate: null,
  fixed_amount: null,
  rebateAmount: null,
  tierId: null,
  paymentCycle: "monthly",
  paymentDay: "",
});

watch(
  () => editForm.ruleType,
  (ruleType) => {
    clearHiddenFormulaFields(ruleType, editForm);
  },
);

const ruleTypeOptions = computed(() =>
  Object.keys(RULE_TYPE_I18N).map((value) => ({
    value,
    label: t(RULE_TYPE_I18N[value]),
  })),
);

/** percentage、hybrid 在下拉中默认隐藏；编辑时若当前规则为该类型则仍显示 */
const visibleRuleTypeOptions = computed(() => {
  const visible = ruleTypeOptions.value.filter(
    (o) => o.value !== "percentage" && o.value !== "hybrid",
  );
  const current = editForm.ruleType;
  if (current === "percentage" || current === "hybrid") {
    const extra = ruleTypeOptions.value.find((o) => o.value === current);
    if (extra && !visible.some((o) => o.value === current))
      return [...visible, extra];
  }
  return visible;
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
  getFormulaFieldVisibility(editForm.ruleType),
);

function fillFormFromRule(rule) {
  if (!rule) return;
  const detailProducts = Array.isArray(rule.products) ? rule.products : [];
  const inferredProductType =
    detailProducts[0]?.productType === "symbol"
      ? "symbols"
      : detailProducts[0]?.productType === "security"
        ? "securities"
        : "";

  editForm.ruleName = rule.ruleName || "";
  editForm.ruleType =
    rule.ruleType &&
    Object.prototype.hasOwnProperty.call(RULE_TYPE_I18N, rule.ruleType)
      ? rule.ruleType
      : "cash_back_rebate";
  editForm.ruleDescription = rule.ruleDescription || "";
  editForm.targetRegion = rule.targetRegion || "global";
  editForm.status = rule.status || "active";
  editForm.trading_platforms_key = rule.trading_platforms_key ?? "";
  editForm.product_type = inferredProductType || rule.product_type || "";
  editForm.products =
    detailProducts.length > 0
      ? detailProducts.map((product) => product.productName).filter(Boolean)
      : rule.productName
        ? [rule.productName]
        : [];
  editForm.minimumPayout =
    rule.minimumPayout != null ? Number(rule.minimumPayout) : 100;
  editForm.payoutCurrency = rule.payoutCurrency || "USD";
  editForm.minimum_trade =
    rule.minimum_trade != null ? Number(rule.minimum_trade) : null;
  editForm.maximum_trade =
    rule.maximum_trade != null ? Number(rule.maximum_trade) : null;
  editForm.rate = rule.rate != null ? Number(rule.rate) : null;
  editForm.fixed_amount =
    rule.fixed_amount != null ? Number(rule.fixed_amount) : null;
  editForm.rebateAmount =
    rule.rebateAmount != null ? Number(rule.rebateAmount) : null;
  editForm.tierId =
    rule.tierId != null && rule.tierId !== "" ? rule.tierId : null;
  editForm.paymentCycle = rule.paymentCycle ?? "monthly";
  editForm.paymentDay = rule.paymentDay ?? "";
  existingProducts.value = detailProducts.map((product) => ({ ...product }));
  productSearch.value = "";
}

/** 当前平台可选列表已加载完成（用于同步后剔除已下架绑定） */
const loadedProductsPlatformKey = ref("");

/**
 * 已选产品仅保留仍在可选列表（EnabledMark=1）且 productId 仍有效的项
 */
function reconcileProductSelectionWithAvailable() {
  if (!editForm.product_type) return;

  const availableById = new Map(
    availableProducts.value
      .map((p) => [Number(p.id), p])
      .filter(([id]) => id > 0),
  );

  const keptExisting = existingProducts.value.filter((product) => {
    const pid =
      product?.productId != null && product?.productId !== ""
        ? Number(product.productId)
        : 0;
    return pid > 0 && availableById.has(pid);
  });

  existingProducts.value = keptExisting;
  editForm.products = keptExisting.map((p) => p.productName).filter(Boolean);
}

const onPlatformChange = () => {
  editForm.product_type = "";
  editForm.products = [];
  existingProducts.value = [];
  productSearch.value = "";
  loadedProductsPlatformKey.value = "";
  const key = editForm.trading_platforms_key;
  if (key) {
    loadPlatformProducts(key);
  } else {
    customSecurities.value = [];
    customSymbols.value = [];
  }
};

const toggleProductSelection = (productName) => {
  if (editForm.products.includes(productName)) {
    editForm.products = editForm.products.filter(
      (name) => name !== productName,
    );
    return;
  }
  editForm.products = [...editForm.products, productName];
};

const selectAllProducts = () => {
  const merged = new Set([
    ...editForm.products,
    ...filteredAvailableProducts.value.map((product) => product.name),
  ]);
  editForm.products = Array.from(merged);
};

const clearProductsSelection = () => {
  editForm.products = [];
};

const getPrimaryProductId = () => {
  if (!editForm.products.length) return null;
  const selectedName = editForm.products[0];
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
  getFormulaCommissionRate(editForm.ruleType, editForm);

const buildProductsPayload = () => {
  const productType =
    editForm.product_type === "symbols" ? "symbol" : "security";
  const existingProductsMap = new Map(
    existingProducts.value
      .filter((product) => product?.productName)
      .map((product) => [product.productName, product]),
  );
  const defaultCommissionType = getDefaultCommissionType(editForm.ruleType);
  const defaultCommissionRate = getDefaultCommissionRate();

  return editForm.products.map((productName) => {
    const existing = existingProductsMap.get(productName);
    const ap = availableProducts.value.find((p) => p.name === productName);
    const productId = ap?.id != null && ap?.id !== "" ? Number(ap.id) : null;
    if (existing) {
      return {
        productType: existing.productType || productType,
        productName: existing.productName,
        productId,
        commissionType: existing.commissionType || defaultCommissionType,
        commissionRate: Number(
          existing.commissionRate ?? defaultCommissionRate,
        ),
        additionalRate: Number(existing.additionalRate || 0),
        minimumVolume: existing.minimumVolume || "0.01 lots",
      };
    }

    return {
      productType,
      productName,
      productId,
      commissionType: defaultCommissionType,
      commissionRate: defaultCommissionRate,
      additionalRate: 0,
      minimumVolume: "0.01 lots",
    };
  });
};

watch(
  () => props.rule,
  (r) => {
    fillFormFromRule(r);
    if (
      loadedProductsPlatformKey.value &&
      loadedProductsPlatformKey.value === editForm.trading_platforms_key
    ) {
      reconcileProductSelectionWithAvailable();
    }
  },
  { immediate: true },
);

/**
 * 支付周期变更时设置默认 Payment Day（与 New Rule 一致）
 */
const handlePaymentCycleChange = () => {
  const defaultValues = {
    realtime: "immediate",
    daily: "everyday",
    weekly: "Monday",
    biweekly: "1-15",
    monthly: "5",
    quarterly: "1",
  };
  const newCycle = editForm.paymentCycle;
  if (defaultValues[newCycle]) {
    if (newCycle === "realtime") {
      editForm.paymentDay = defaultValues[newCycle];
    } else if (
      !editForm.paymentDay ||
      editForm.paymentDay === "immediate" ||
      editForm.paymentDay === "0"
    ) {
      editForm.paymentDay = defaultValues[newCycle];
    }
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
  } catch (e) {
    console.error("Failed to load platforms:", e);
  }
};

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
  } catch (e) {
    console.error("Failed to load custom securities:", e);
    customSecurities.value = [];
  }
};

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
  } catch (e) {
    console.error("Failed to load custom symbols:", e);
    customSymbols.value = [];
  }
};

const loadPlatformProducts = async (platformKey) => {
  if (!platformKey) {
    customSecurities.value = [];
    customSymbols.value = [];
    loadedProductsPlatformKey.value = "";
    return;
  }
  await loadCustomSecurities(platformKey);
  await loadCustomSymbols(platformKey);
  loadedProductsPlatformKey.value = platformKey;
  reconcileProductSelectionWithAvailable();
};

onMounted(async () => {
  fillFormFromRule(props.rule);
  const [tierRes] = await Promise.all([
    ibTierLevelsApi.getTierLevels(),
    loadPlatforms(),
  ]);
  if (tierRes?.success && tierRes.data)
    tierLevels.value = tierRes.data.items || tierRes.data;
  const platformKey = editForm.trading_platforms_key;
  if (platformKey) {
    await loadPlatformProducts(platformKey);
  }
});

const saveRule = async () => {
  if (!props.rule?.id) return;
  if (editForm.ruleType === "percentage" || editForm.ruleType === "hybrid") {
    alert(t("ibRuleModal_alert_ruleTypeDev"));
    return;
  }
  if (editForm.tierId == null || editForm.tierId === "") {
    alert(t("ibRuleModal_alert_selectTier"));
    return;
  }
  if (editForm.product_type && editForm.products.length === 0) {
    alert(t("ibRuleModal_alert_selectProduct"));
    return;
  }
  const formulaErrorKey = validateFormulaFields(editForm.ruleType, editForm);
  if (formulaErrorKey) {
    alert(t(formulaErrorKey));
    return;
  }
  try {
    saving.value = true;
    const primaryProductId = getPrimaryProductId();
    const payload = {
      ruleName: editForm.ruleName,
      ruleType: editForm.ruleType || "cash_back_rebate",
      ruleDescription: editForm.ruleDescription,
      targetRegion: editForm.targetRegion,
      status: editForm.status,
      trading_platforms_key: editForm.trading_platforms_key || null,
      product_type: editForm.product_type || null,
      product: primaryProductId,
      minimumPayout: editForm.minimumPayout,
      payoutCurrency: editForm.payoutCurrency,
      minimum_trade: editForm.minimum_trade,
      maximum_trade: editForm.maximum_trade,
      ...buildFormulaFieldsPayload(editForm.ruleType, editForm),
      tierId: editForm.tierId,
      paymentCycle: editForm.paymentCycle,
      paymentDay: editForm.paymentDay,
    };
    const productsPayload = buildProductsPayload();
    if (editForm.product_type && productsPayload.some((p) => !p.productId)) {
      alert(t("ibRuleModal_alert_selectProduct"));
      return;
    }

    const response = await ibRulesApi.updateRule(props.rule.id, payload);
    if (response?.success) {
      await ibRulesApi.updateProducts(props.rule.id, productsPayload);
      alert(t("ibRuleModal_alert_updateOk"));
      emit("saved");
      emit("close");
    } else {
      alert(
        tParams(
          "ibRuleModal_alert_updateFail",
          "Failed to update rule: {msg}",
          { msg: response?.message || "Unknown error" },
        ),
      );
    }
  } catch (e) {
    console.error(e);
    alert(t("ibRuleModal_alert_updateFailGeneric"));
  } finally {
    saving.value = false;
  }
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
  font-size: 14px;
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
  font-size: 14px;
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
  font-size: 14px;
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
  font-size: 14px;
  text-align: center;
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
}
.btn-primary:hover:not(:disabled) {
  background: var(--color-brand-strong);
}
.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
