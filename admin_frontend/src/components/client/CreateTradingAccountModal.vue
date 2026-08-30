<template>
  <div v-if="modelValue" class="modal-overlay" @click.self="handleClose">
    <div class="modal-container" @click.stop>
      <div class="modal-header">
        <h2 class="modal-title">
          <i class="fas fa-chart-line"></i>
          {{ t("clientDetail_createTradingAccount", "Create Trading Account") }}
        </h2>
        <button
          type="button"
          class="modal-close"
          :disabled="submitting"
          @click="handleClose"
        >
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div v-if="createdAccount" class="modal-body">
        <div class="success-state">
          <div class="success-icon">
            <i class="fas fa-check"></i>
          </div>
          <h3>
            {{
              t("accountCreatedSuccessfully", "Account Created Successfully!")
            }}
          </h3>
          <p>
            {{
              t(
                "tradingAccountReady",
                "Trading account has been created successfully.",
              )
            }}
          </p>
        </div>

        <div class="created-account-box">
          <div class="created-account-row">
            <span>{{ t("clientDetail_thPlatform", "Platform") }}</span>
            <strong>{{ createdAccount.platformName || "-" }}</strong>
          </div>
          <div class="created-account-row">
            <span>{{ t("accountNumber", "Account Number") }}</span>
            <strong>{{ createdAccount.accountNumber || "-" }}</strong>
          </div>
          <div class="created-account-row">
            <span>{{ t("password", "Password") }}</span>
            <strong class="password-value">{{
              createdAccount.password || "-"
            }}</strong>
          </div>
          <div class="created-account-row">
            <span>{{ t("selectLeverage", "Leverage") }}</span>
            <strong>{{ createdAccount.leverageValue || "-" }}</strong>
          </div>
          <div class="created-account-row">
            <span>{{ t("accountCurrency", "Account Currency") }}</span>
            <strong>{{ createdAccount.accountCurrency || "-" }}</strong>
          </div>
          <div class="created-account-row">
            <span>{{ t("clientDetail_thCreated", "Created") }}</span>
            <strong>{{ createdAccount.createdAt || "-" }}</strong>
          </div>
        </div>
      </div>

      <form v-else class="modal-body" @submit.prevent="handleSubmit">
        <div v-if="loadingPlatforms" class="state-box">
          <i class="fas fa-spinner fa-spin"></i>
          {{
            t(
              "clientDetail_loadingTradingPlatforms",
              "Loading trading platforms...",
            )
          }}
        </div>

        <template v-else>
          <div v-if="loadError" class="error-box">
            <i class="fas fa-exclamation-circle"></i>
            <span>{{ loadError }}</span>
            <button type="button" class="inline-retry" @click="loadPlatforms">
              {{ t("common_retry", "Retry") }}
            </button>
          </div>

          <div class="form-grid">
            <div class="form-field">
              <label
                >{{ t("clientDetail_thPlatform", "Platform") }}
                <span class="required">*</span></label
              >
              <select
                v-model="selectedPlatformId"
                :disabled="submitting || loadingGroups"
                required
                @change="handlePlatformChange"
              >
                <option value="">
                  {{
                    t("clientDetail_selectPlatform", "-- Select Platform --")
                  }}
                </option>
                <option
                  v-for="platform in platforms"
                  :key="platform.id"
                  :value="String(platform.id)"
                >
                  {{ formatPlatformLabel(platform) }}
                </option>
              </select>
            </div>

            <template v-if="selectedPlatformId">
              <div class="form-field">
                <label
                  >{{ t("selectLeverage", "Leverage") }}
                  <span class="required">*</span></label
                >
                <select
                  v-model="selectedLeverage"
                  :disabled="
                    submitting || loadingGroups || !leverageOptions.length
                  "
                  required
                >
                  <option value="">
                    {{ t("selectLeveragePlaceholder", "Select leverage") }}
                  </option>
                  <option
                    v-for="option in leverageOptions"
                    :key="option.value"
                    :value="option.value"
                  >
                    {{ option.label }}
                  </option>
                </select>
              </div>

              <div class="form-field">
                <label
                  >{{ t("accountType", "Account Type") }}
                  <span class="required">*</span></label
                >
                <select
                  v-model="form.accountType"
                  :disabled="
                    submitting || loadingGroups || !accountTypeOptions.length
                  "
                  required
                >
                  <option value="">
                    {{
                      t("selectAccountTypePlaceholder", "Select account type")
                    }}
                  </option>
                  <option
                    v-for="option in accountTypeOptions"
                    :key="option.value"
                    :value="option.value"
                  >
                    {{ option.label }}
                  </option>
                </select>
              </div>

              <div v-if="assignablePackages.length" class="form-field">
                <label>{{ t("clientDetail_rebateRule", "Rebate Rule") }}</label>
                <select
                  v-model="form.assignedIbPartnerId"
                  :disabled="submitting || loadingRules"
                >
                  <option value="">{{ autoPackageLabel }}</option>
                  <option
                    v-for="pkg in assignablePackages"
                    :key="pkg.ibPartnerId"
                    :value="String(pkg.ibPartnerId)"
                  >
                    {{ formatAssignablePackageLabel(pkg) }}
                  </option>
                </select>
              </div>

              <div class="form-field">
                <label
                  >{{ t("accountNickname", "Account Nickname") }}
                  <span class="required">*</span></label
                >
                <input
                  v-model="form.accountNickname"
                  type="text"
                  :placeholder="
                    t(
                      'accountNicknamePlaceholder',
                      'e.g., My Main Trading Account',
                    )
                  "
                  :disabled="submitting"
                  required
                />
              </div>

              <div class="form-field">
                <label>{{ t("accountCurrency", "Account Currency") }}</label>
                <input
                  type="text"
                  :value="form.accountCurrencyDisplay"
                  disabled
                />
              </div>
            </template>
          </div>

          <div
            v-if="selectedPlatformId && loadingGroups"
            class="state-box compact"
          >
            <i class="fas fa-spinner fa-spin"></i>
            {{
              t(
                "clientDetail_loadingTradingGroups",
                "Loading trading groups...",
              )
            }}
          </div>

          <div
            v-else-if="selectedPlatformId && !accountTypeOptions.length"
            class="hint-box"
          >
            <i class="fas fa-info-circle"></i>
            {{
              t(
                "noAccountTypeAvailable",
                "No account groups are currently available for the selected platform.",
              )
            }}
          </div>

          <div v-if="selectedPlatformId" class="hint-box">
            <i class="fas fa-key"></i>
            {{
              t(
                "clientDetail_randomTradingPasswordHint",
                "A secure random trading password will be generated automatically.",
              )
            }}
          </div>

          <div v-if="formError" class="error-box">
            <i class="fas fa-exclamation-triangle"></i>
            <span>{{ formError }}</span>
          </div>
        </template>
      </form>

      <div class="modal-footer">
        <button
          type="button"
          class="btn btn-secondary"
          :disabled="submitting"
          @click="handleClose"
        >
          {{ t("common_cancel", "Cancel") }}
        </button>
        <button
          v-if="!createdAccount"
          type="button"
          class="btn btn-primary"
          :disabled="!canSubmit || submitting"
          @click="handleSubmit"
        >
          <i :class="submitting ? 'fas fa-spinner fa-spin' : 'fas fa-plus'"></i>
          {{
            submitting
              ? t("creating", "Creating...")
              : t("createAccount", "Create Account")
          }}
        </button>
        <button
          v-else
          type="button"
          class="btn btn-primary"
          @click="handleClose"
        >
          <i class="fas fa-check"></i>
          {{ t("common_close", "Close") }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from "vue";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { clientService } from "@/services/clientListService";
import { getSubModuleKey } from "@/config/operationLogPages";

const { t, tParams } = useAdminI18n();

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  clientId: {
    type: [Number, String],
    required: true,
  },
  logSubModuleKey: {
    type: String,
    default: () => getSubModuleKey("page_clients_list"),
  },
});

const emit = defineEmits(["update:modelValue", "created"]);

const loadingPlatforms = ref(false);
const loadingGroups = ref(false);
const loadingRules = ref(false);
const submitting = ref(false);
const loadError = ref("");
const formError = ref("");
const platforms = ref([]);
const assignablePackages = ref([]);
const selectedPlatformId = ref("");
const selectedLeverage = ref("");
const platformDefaultGroupsMap = ref({});
const createdAccount = ref(null);

const form = reactive({
  accountNickname: "",
  accountCurrency: "USD",
  accountCurrencyDisplay: "USD",
  accountType: "",
  assignedIbPartnerId: "",
});

const selectedPlatform = computed(() => {
  return (
    platforms.value.find(
      (platform) => String(platform.id) === String(selectedPlatformId.value),
    ) || null
  );
});

const selectedPlatformTradingConfig = computed(() => {
  const platformKey = String(selectedPlatform.value?.platformKey || "")
    .trim()
    .toLowerCase();
  return platformKey
    ? platformDefaultGroupsMap.value[platformKey] || {
        groups: [],
        leverage: [],
      }
    : { groups: [], leverage: [] };
});

const normalizeLeverageKey = (leverage) => {
  const normalized = String(leverage ?? "").trim();
  return normalized || "__default__";
};

const leverageOptions = computed(() =>
  (Array.isArray(selectedPlatformTradingConfig.value?.leverage)
    ? selectedPlatformTradingConfig.value.leverage
    : []
  )
    .map((entry) => {
      const leverage = entry?.leverageValue;
      const displayLabel = String(entry?.displayLabel ?? "").trim();

      return {
        value: normalizeLeverageKey(leverage),
        label:
          displayLabel ||
          String(leverage ?? "").trim() ||
          t("defaultLabel", "Default"),
        leverage,
      };
    })
    .filter(
      (option, index, options) =>
        options.findIndex((candidate) => candidate.value === option.value) ===
        index,
    ),
);

const selectedLeverageOption = computed(
  () =>
    leverageOptions.value.find(
      (option) => option.value === selectedLeverage.value,
    ) || null,
);

const accountTypeOptions = computed(() =>
  (Array.isArray(selectedPlatformTradingConfig.value?.groups)
    ? selectedPlatformTradingConfig.value.groups
    : []
  )
    .map((group) => {
      const tradingId = Number(group?.trading_id ?? 0);
      const baseLabel = String(group?.label || group?.name || "").trim();
      if (!baseLabel) return null;

      return {
        label: baseLabel,
        value: baseLabel,
        tradingId:
          Number.isFinite(tradingId) && tradingId > 0 ? tradingId : null,
        unit: group?.unit ?? null,
      };
    })
    .filter(Boolean),
);

const selectedAccountTypeOption = computed(
  () =>
    accountTypeOptions.value.find(
      (option) => option.value === form.accountType,
    ) || null,
);

const canSubmit = computed(
  () =>
    Boolean(selectedPlatform.value) &&
    form.accountNickname.trim().length >= 3 &&
    Boolean(selectedLeverageOption.value) &&
    Boolean(selectedAccountTypeOption.value) &&
    !loadingPlatforms.value &&
    !loadingGroups.value,
);

const formatPlatformLabel = (platform) => {
  return (
    platform?.displayName || platform?.platformKey || platform?.shortCode || "-"
  );
};

const formatAssignablePackageLabel = (pkg) => {
  return String(pkg?.ibCode || "").trim() || `#${pkg?.ibPartnerId}`;
};

const autoPackageLabel = computed(() => {
  const code = String(assignablePackages.value[0]?.ibCode || "").trim();
  if (!code) {
    return t("clientDetail_rebateRuleNone", "Auto (no assignment)");
  }
  return tParams("clientDetail_rebateRuleAutoPackage", "Auto ({ibCode})", {
    ibCode: code,
  });
});

const setAccountCurrency = (currencyCode = "USD") => {
  const normalizedCurrency =
    String(currencyCode || "USD")
      .trim()
      .toUpperCase() || "USD";
  form.accountCurrency = normalizedCurrency;
  form.accountCurrencyDisplay = normalizedCurrency;
};

const resolveGroupCurrency = (groupOption) => {
  const groupUnit = String(groupOption?.unit || "").trim();
  return groupUnit ? groupUnit.toUpperCase() : "USD";
};

const normalizeDefaultTradingGroups = (response) => {
  const payload = response?.data ?? response ?? [];

  if (payload && typeof payload === "object" && !Array.isArray(payload)) {
    const groups = Array.isArray(payload?.groups) ? payload.groups : [];
    const leverage = Array.isArray(payload?.leverage) ? payload.leverage : [];

    if (groups.length || leverage.length) {
      return { groups, leverage };
    }
  }

  if (Array.isArray(payload)) {
    const flattenedGroups = payload.flatMap((entry) =>
      Array.isArray(entry?.groups) ? entry.groups : [],
    );
    const leverage = payload.map((entry, index) => ({
      id: entry?.id ?? `legacy-${index}`,
      leverageValue: entry?.leverage ?? null,
      displayLabel:
        String(entry?.leverage ?? "").trim() || t("defaultLabel", "Default"),
    }));

    return {
      groups: flattenedGroups,
      leverage,
    };
  }

  if (Array.isArray(payload?.data)) {
    const flattenedGroups = payload.data.flatMap((entry) =>
      Array.isArray(entry?.groups) ? entry.groups : [],
    );
    const leverage = payload.data.map((entry, index) => ({
      id: entry?.id ?? `legacy-data-${index}`,
      leverageValue: entry?.leverage ?? null,
      displayLabel:
        String(entry?.leverage ?? "").trim() || t("defaultLabel", "Default"),
    }));

    return {
      groups: flattenedGroups,
      leverage,
    };
  }

  if (Array.isArray(payload?.groupIds)) {
    return {
      groups: payload.groupIds.map((groupId) => ({
        id: Number(groupId),
        trading_id: Number(groupId),
      })),
      leverage: [],
    };
  }

  return { groups: [], leverage: [] };
};

const normalizePlatforms = (response) => {
  const responseData = response?.data || response || {};
  const platformList = Array.isArray(responseData?.platforms)
    ? responseData.platforms
    : [];

  return platformList
    .filter((platform) => platform?.isEnabled !== false)
    .map((platform) => ({
      ...platform,
      id: Number(platform.id),
    }));
};

const resetForm = () => {
  selectedPlatformId.value = "";
  selectedLeverage.value = "";
  form.accountNickname = "";
  form.accountType = "";
  form.assignedIbPartnerId = "";
  setAccountCurrency("USD");
  formError.value = "";
  loadError.value = "";
  createdAccount.value = null;
};

const loadAssignableRules = async () => {
  if (!props.clientId) {
    assignablePackages.value = [];
    return;
  }
  loadingRules.value = true;
  try {
    const response = await clientService.getAssignableCommissionRules(
      props.clientId,
    );
    const items = response?.data?.items ?? response?.items ?? [];
    assignablePackages.value = Array.isArray(items) ? items : [];
  } catch (error) {
    console.error("Failed to load assignable commission rules:", error);
    assignablePackages.value = [];
  } finally {
    loadingRules.value = false;
  }
};

const loadPlatforms = async () => {
  loadingPlatforms.value = true;
  loadError.value = "";
  try {
    platforms.value = normalizePlatforms(
      await clientService.getTradingPlatforms(),
    );
  } catch (error) {
    console.error("Failed to load admin trading platforms:", error);
    platforms.value = [];
    loadError.value =
      error?.response?.data?.message ||
      error?.message ||
      t(
        "clientDetail_failedLoadTradingPlatforms",
        "Failed to load trading platforms.",
      );
  } finally {
    loadingPlatforms.value = false;
  }
};

const fetchDefaultTradingGroups = async (platformKey) => {
  const normalizedPlatformKey = String(platformKey || "")
    .trim()
    .toLowerCase();
  if (
    !normalizedPlatformKey ||
    platformDefaultGroupsMap.value[normalizedPlatformKey]
  ) {
    return;
  }

  loadingGroups.value = true;
  try {
    const response = await clientService.getDefaultTradingGroups(
      props.clientId,
      normalizedPlatformKey,
    );
    platformDefaultGroupsMap.value = {
      ...platformDefaultGroupsMap.value,
      [normalizedPlatformKey]: normalizeDefaultTradingGroups(response),
    };
  } catch (error) {
    console.error(
      `Failed to load admin trading groups for ${normalizedPlatformKey}:`,
      error,
    );
    platformDefaultGroupsMap.value = {
      ...platformDefaultGroupsMap.value,
      [normalizedPlatformKey]: { groups: [], leverage: [] },
    };
    formError.value =
      error?.response?.data?.message ||
      error?.message ||
      t(
        "clientDetail_failedLoadTradingGroups",
        "Failed to load trading groups.",
      );
  } finally {
    loadingGroups.value = false;
  }
};

const handlePlatformChange = async () => {
  selectedLeverage.value = "";
  form.accountType = "";
  formError.value = "";
  setAccountCurrency("USD");

  if (selectedPlatform.value?.platformKey) {
    await fetchDefaultTradingGroups(selectedPlatform.value.platformKey);
  }
};

const generateRandomTradingPassword = () => {
  const upper = "ABCDEFGHJKLMNPQRSTUVWXYZ";
  const lower = "abcdefghijkmnopqrstuvwxyz";
  const numbers = "23456789";
  const special = "!@#$%^&*";
  const all = upper + lower + numbers + special;
  const required = [upper, lower, numbers, special].map(
    (chars) => chars[randomIndex(chars.length)],
  );
  const rest = Array.from({ length: 8 }, () => all[randomIndex(all.length)]);
  return shufflePassword([...required, ...rest]).join("");
};

const randomIndex = (max) => {
  const values = new Uint32Array(1);
  window.crypto.getRandomValues(values);
  return values[0] % max;
};

const shufflePassword = (chars) => {
  for (let index = chars.length - 1; index > 0; index -= 1) {
    const swapIndex = randomIndex(index + 1);
    const current = chars[index];
    chars[index] = chars[swapIndex];
    chars[swapIndex] = current;
  }
  return chars;
};

const handleSubmit = async () => {
  if (!canSubmit.value || submitting.value) return;

  submitting.value = true;
  formError.value = "";
  try {
    const password = generateRandomTradingPassword();
    const payload = {
      platformKey: selectedPlatform.value.platformKey,
      accountNickname: form.accountNickname.trim(),
      leverageValue: selectedLeverageOption.value?.leverage,
      initialDeposit: 100,
      accountCurrency: form.accountCurrency,
      accountType: selectedAccountTypeOption.value?.label || form.accountType,
      password,
      confirmPassword: password,
    };

    if (selectedAccountTypeOption.value?.tradingId != null) {
      payload.groupTradingId = selectedAccountTypeOption.value.tradingId;
    }

    const pkg = assignablePackages.value.find(
      (item) => String(item.ibPartnerId) === String(form.assignedIbPartnerId),
    );
    if (pkg?.ruleId) {
      payload.assignedCommissionRuleId = Number(pkg.ruleId);
    }

    payload.logSubModuleKey = props.logSubModuleKey;

    const response = await clientService.createTradingAccount(
      props.clientId,
      payload,
    );
    createdAccount.value = response?.data || response || null;
    emit("created", createdAccount.value);
  } catch (error) {
    console.error("Failed to create admin trading account:", error);
    formError.value =
      error?.response?.data?.message ||
      error?.message ||
      t("failedToCreateTradingAccount", "Failed to create trading account.");
  } finally {
    submitting.value = false;
  }
};

const handleClose = () => {
  if (submitting.value) return;
  emit("update:modelValue", false);
};

watch(
  () => props.modelValue,
  (visible) => {
    if (!visible) return;
    resetForm();
    loadAssignableRules();
    if (!platforms.value.length) {
      loadPlatforms();
    }
  },
);

watch(selectedAccountTypeOption, (option) => {
  setAccountCurrency(resolveGroupCurrency(option));
});

watch(accountTypeOptions, (options) => {
  if (!Array.isArray(options) || !options.length) {
    form.accountType = "";
    setAccountCurrency("USD");
    return;
  }

  const currentExists = options.some(
    (option) => option.value === form.accountType,
  );
  if (!currentExists) {
    form.accountType = "";
  }
});
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 2200;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: rgba(15, 23, 42, 0.48);
}

.modal-container {
  width: min(100%, 680px);
  max-height: 90vh;
  overflow: hidden;
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 24px 70px rgba(15, 23, 42, 0.28);
}

.modal-header,
.modal-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px;
  border-bottom: 1px solid var(--color-border);
}

.modal-footer {
  justify-content: flex-end;
  gap: 12px;
  border-top: 1px solid var(--color-border);
  border-bottom: 0;
}

.modal-title {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0;
  color: var(--color-ink);
  font-size: 20px;
}

.modal-title i {
  color: var(--color-brand);
}

.modal-close {
  width: 36px;
  height: 36px;
  border: 0;
  border-radius: 50%;
  color: var(--color-text);
  background: var(--color-surface-muted);
  cursor: pointer;
}

.modal-body {
  max-height: calc(90vh - 154px);
  overflow-y: auto;
  padding: 24px;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 18px;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.form-field label {
  color: var(--color-text);
  font-size: 14px;
  font-weight: 700;
}

.form-field input,
.form-field select {
  width: 100%;
  height: 42px;
  padding: 0 12px;
  border: 1px solid #d1d5db;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-ink);
  font-size: 14px;
}

.form-field input:disabled,
.form-field select:disabled {
  background: var(--color-surface-soft);
  color: var(--color-muted);
}

.required {
  color: var(--color-danger);
}

.state-box,
.hint-box,
.error-box {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  border-radius: var(--radius-md);
  font-size: 14px;
}

.state-box {
  color: var(--color-brand);
  background: var(--color-brand-soft);
}

.state-box.compact,
.hint-box,
.error-box {
  margin-top: 16px;
}

.hint-box {
  color: var(--color-text);
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
}

.error-box {
  color: var(--color-danger);
  background: var(--color-danger-soft);
  border: 1px solid var(--color-danger-border);
}

.inline-retry {
  margin-left: auto;
  border: 0;
  color: var(--color-brand);
  background: transparent;
  font-weight: 700;
  cursor: pointer;
}

.success-state {
  text-align: center;
  padding: 8px 0 18px;
}

.success-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 54px;
  height: 54px;
  margin-bottom: 14px;
  border-radius: 50%;
  color: #fff;
  background: var(--color-success-solid);
}

.success-state h3 {
  margin: 0 0 8px;
  color: var(--color-ink);
  font-size: 20px;
}

.success-state p {
  margin: 0;
  color: var(--color-muted);
  font-size: 14px;
}

.created-account-box {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  overflow: hidden;
}

.created-account-row {
  display: grid;
  grid-template-columns: minmax(130px, 0.42fr) minmax(0, 1fr);
  gap: 16px;
  padding: 13px 16px;
  border-bottom: 1px solid var(--color-border);
  background: var(--color-surface);
}

.created-account-row:last-child {
  border-bottom: 0;
}

.created-account-row span {
  color: var(--color-muted);
  font-size: 14px;
  font-weight: 700;
}

.created-account-row strong {
  min-width: 0;
  color: var(--color-ink);
  font-size: 14px;
  overflow-wrap: anywhere;
}

.password-value {
  font-family:
    ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono",
    monospace;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  border: none;
  border-radius: var(--radius-md);
  font-weight: 700;
  cursor: pointer;
}

.btn:disabled {
  cursor: not-allowed;
  opacity: 0.65;
}

.btn-primary {
  color: #fff;
  background: linear-gradient(
    135deg,
    var(--color-brand),
    var(--color-brand-strong)
  );
}

.btn-secondary {
  color: var(--color-text);
  background: var(--color-surface-muted);
}

@media (max-width: 640px) {
  .form-grid {
    grid-template-columns: 1fr;
  }

  .modal-footer {
    flex-direction: column-reverse;
    align-items: stretch;
  }

  .created-account-row {
    grid-template-columns: 1fr;
    gap: 6px;
  }

  .btn {
    justify-content: center;
  }
}
</style>
