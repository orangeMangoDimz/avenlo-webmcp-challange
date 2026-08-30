<template>
  <!-- /app/openaccount 入口未通过 KYC：用 gate 模式只显示"此功能需要 KYC"，不暴露具体 KYC 状态 -->
  <KycRequiredNotice v-if="isAppOpenAccount && !isKycApproved" gate />

  <div v-else class="open-account-page ui-page">
    <div class="container">
      <div class="page-header">
        <h2>
          {{ t("selectTradingPlatform", "Select Your Trading Platform") }}
        </h2>
        <p>
          {{
            t(
              "choosePlatformDesc",
              "Choose the platform that best suits your trading needs and configure your account settings",
            )
          }}
        </p>
      </div>

      <div v-if="loadingPlatforms" class="state-card">
        <i class="fas fa-spinner fa-spin"></i>
        <p>
          {{ t("loadingPlatforms", "Loading available trading platforms...") }}
        </p>
      </div>

      <div v-else>
        <div v-if="error" class="state-card error">
          <i class="fas fa-exclamation-circle"></i>
          <p>{{ error }}</p>
          <button class="btn btn-secondary" @click="loadPlatforms">
            {{ t("retry", "Retry") }}
          </button>
        </div>

        <div v-else>
          <div class="platform-cards">
            <div
              v-for="platform in platforms"
              :key="platform.id"
              :class="[
                'platform-card',
                {
                  selected: platform.id === selectedPlatformId,
                  recommended: platform.isRecommended,
                },
              ]"
              @click="handlePlatformSelect(platform.id)"
            >
              <div class="select-indicator">
                <i class="fas fa-check"></i>
              </div>
              <div class="platform-card-header">
                <div class="platform-icon">
                  <i :class="platformIcon(platform)"></i>
                </div>
                <div class="platform-name">{{ platform.displayName }}</div>
                <div class="platform-description">
                  {{ platform.description }}
                </div>
              </div>

              <div class="platform-features">
                <div
                  v-for="feature in platformFeatures(platform)"
                  :key="feature"
                  class="feature-item"
                >
                  <i class="fas fa-check-circle"></i>
                  <span>{{ feature }}</span>
                </div>
              </div>

              <div
                v-if="!isAppOpenAccount && hasPlatformLink(platform)"
                class="platform-actions"
                @click.stop
              >
                <button
                  type="button"
                  class="btn btn-secondary platform-download-btn"
                  @click.stop="handlePlatformLink(platform)"
                >
                  <i :class="getPlatformActionIcon(platform)"></i>
                  {{ getPlatformActionLabel(platform) }}
                </button>
              </div>

              <!--              <div class="platform-selection" @click.stop>-->
              <!--                <label class="selection-label">{{ t('selectLeverage', 'Select Leverage') }}</label>-->
              <!--                <select-->
              <!--                  class="leverage-select"-->
              <!--                  :value="getLeverageValue(platform)"-->
              <!--                  :disabled="platform.id !== selectedPlatformId"-->
              <!--                  @change="handleLeverageChange($event.target.value, platform.id)"-->
              <!--                >-->
              <!--                  <option-->
              <!--                    v-for="leverage in platform.leverages"-->
              <!--                    :key="leverage.id"-->
              <!--                    :value="leverage.leverageValue"-->
              <!--                  >-->
              <!--                    {{ leverage.displayLabel }}-->
              <!--                  </option>-->
              <!--                </select>-->
              <!--              </div>-->
            </div>
          </div>

          <div
            class="account-details-section"
            :class="{ show: !!selectedPlatformId }"
          >
            <form class="account-form" @submit.prevent="handleSubmit">
              <div class="form-row">
                <div class="form-group">
                  <label>{{ t("selectLeverage", "Leverage") }}</label>
                  <select
                    v-model="selectedLeverage"
                    class="account-type-select"
                    :disabled="
                      submitting ||
                      !selectedPlatformId ||
                      !leverageOptions.length
                    "
                  >
                    <option value="" disabled>
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
                  <small
                    ><i class="fas fa-info-circle"></i>
                    {{
                      t(
                        "selectLeverageHint",
                        "Select the leverage level for this trading account.",
                      )
                    }}</small
                  >
                </div>
              </div>

              <div class="form-row">
                <div class="form-group">
                  <label>{{ t("accountType", "Account Type") }}</label>
                  <select
                    v-model="form.accountType"
                    class="account-type-select"
                    :disabled="
                      submitting ||
                      !selectedPlatformId ||
                      !accountTypeOptions.length
                    "
                  >
                    <option value="" disabled>
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
                  <small
                    ><i class="fas fa-info-circle"></i>
                    {{
                      t(
                        "standardAccountHint",
                        "Select the trading account group independently from leverage.",
                      )
                    }}</small
                  >
                </div>
              </div>

              <div class="form-row">
                <div class="form-group">
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
                    required
                    :disabled="submitting"
                  />
                  <small
                    ><i class="fas fa-info-circle"></i>
                    {{
                      t(
                        "accountNicknameHint",
                        "Give your account a memorable name.",
                      )
                    }}</small
                  >
                </div>

                <div class="form-group">
                  <label>{{ t("accountCurrency", "Account Currency") }}</label>
                  <input
                    type="text"
                    :value="form.accountCurrencyDisplay"
                    readonly
                    class="readonly"
                  />
                  <input type="hidden" v-model="form.accountCurrency" />
                  <small
                    ><i class="fas fa-info-circle"></i>
                    {{
                      t(
                        "defaultCurrencyHint",
                        "Default base currency for all accounts.",
                      )
                    }}</small
                  >
                </div>
              </div>

              <div
                v-if="selectedPlatformId && !accountTypeOptions.length"
                class="form-row"
              >
                <div class="form-group">
                  <small
                    ><i class="fas fa-info-circle"></i>
                    {{
                      t(
                        "noAccountTypeAvailable",
                        "No account groups are currently available for the selected platform.",
                      )
                    }}</small
                  >
                </div>
              </div>

              <div v-if="formError" class="form-error">
                <i class="fas fa-exclamation-triangle"></i>
                {{ formError }}
              </div>

              <div class="action-buttons">
                <button
                  type="button"
                  class="btn btn-secondary"
                  @click="resetForm"
                  :disabled="submitting"
                >
                  <i class="fas fa-redo"></i>
                  {{ t("reset", "Reset") }}
                </button>
                <button
                  type="submit"
                  class="btn btn-primary"
                  :disabled="!canSubmit || submitting"
                >
                  <i class="fas fa-check"></i>
                  {{
                    submitting
                      ? t("creating", "Creating...")
                      : t("createAccount", "Create Account")
                  }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <transition name="modal">
      <div
        v-if="showCreateModal"
        class="modal-overlay"
        @click.self="closeCreateModal"
      >
        <div class="create-modal">
          <div class="create-modal-header">
            <h2>{{ t("createTradingAccount", "Create Trading Account") }}</h2>
            <button
              type="button"
              class="modal-close-btn"
              @click="closeCreateModal"
            >
              ×
            </button>
          </div>

          <form
            class="create-modal-body"
            @submit.prevent="confirmCreateAccount"
          >
            <div class="form-group">
              <label
                >{{ t("password", "Password") }}
                <span class="required">*</span></label
              >
              <div class="password-input-wrapper">
                <input
                  v-model="passwordForm.password"
                  :type="showPassword ? 'text' : 'password'"
                  class="password-input"
                  autocomplete="new-password"
                  :disabled="submitting"
                  :placeholder="
                    t('enterTradingPassword', 'Enter trading password')
                  "
                />
                <button
                  type="button"
                  class="password-toggle-btn"
                  :disabled="submitting"
                  @click="showPassword = !showPassword"
                >
                  <i
                    :class="showPassword ? 'fas fa-eye-slash' : 'fas fa-eye'"
                  ></i>
                </button>
              </div>
            </div>

            <div class="form-group">
              <label
                >{{ t("confirmPassword", "Confirm Password") }}
                <span class="required">*</span></label
              >
              <div class="password-input-wrapper">
                <input
                  v-model="passwordForm.confirmPassword"
                  :type="showConfirmPassword ? 'text' : 'password'"
                  class="password-input"
                  autocomplete="new-password"
                  :disabled="submitting"
                  :placeholder="
                    t('confirmTradingPassword', 'Confirm trading password')
                  "
                />
                <button
                  type="button"
                  class="password-toggle-btn"
                  :disabled="submitting"
                  @click="showConfirmPassword = !showConfirmPassword"
                >
                  <i
                    :class="
                      showConfirmPassword ? 'fas fa-eye-slash' : 'fas fa-eye'
                    "
                  ></i>
                </button>
              </div>
            </div>

            <div class="password-rule-box">
              <i class="fas fa-shield-alt"></i>
              <span>{{
                t(
                  "tradingPasswordRule",
                  "Password must be 8-16 characters and include uppercase, lowercase, number, and special character.",
                )
              }}</span>
            </div>

            <div class="password-checklist">
              <div
                :class="[
                  'password-check-item',
                  { met: passwordRuleStatus.length },
                ]"
              >
                <i
                  :class="
                    passwordRuleStatus.length
                      ? 'fas fa-check-circle'
                      : 'far fa-circle'
                  "
                ></i>
                <span>{{ t("passwordRuleLength", "8-16 characters") }}</span>
              </div>
              <div
                :class="[
                  'password-check-item',
                  { met: passwordRuleStatus.uppercase },
                ]"
              >
                <i
                  :class="
                    passwordRuleStatus.uppercase
                      ? 'fas fa-check-circle'
                      : 'far fa-circle'
                  "
                ></i>
                <span>{{
                  t("passwordRuleUppercase", "At least one uppercase letter")
                }}</span>
              </div>
              <div
                :class="[
                  'password-check-item',
                  { met: passwordRuleStatus.lowercase },
                ]"
              >
                <i
                  :class="
                    passwordRuleStatus.lowercase
                      ? 'fas fa-check-circle'
                      : 'far fa-circle'
                  "
                ></i>
                <span>{{
                  t("passwordRuleLowercase", "At least one lowercase letter")
                }}</span>
              </div>
              <div
                :class="[
                  'password-check-item',
                  { met: passwordRuleStatus.number },
                ]"
              >
                <i
                  :class="
                    passwordRuleStatus.number
                      ? 'fas fa-check-circle'
                      : 'far fa-circle'
                  "
                ></i>
                <span>{{
                  t("passwordRuleNumber", "At least one number")
                }}</span>
              </div>
              <div
                :class="[
                  'password-check-item',
                  { met: passwordRuleStatus.special },
                ]"
              >
                <i
                  :class="
                    passwordRuleStatus.special
                      ? 'fas fa-check-circle'
                      : 'far fa-circle'
                  "
                ></i>
                <span>{{
                  t("passwordRuleSpecial", "At least one special character")
                }}</span>
              </div>
              <div
                :class="[
                  'password-check-item',
                  { met: passwordRuleStatus.matches },
                ]"
              >
                <i
                  :class="
                    passwordRuleStatus.matches
                      ? 'fas fa-check-circle'
                      : 'far fa-circle'
                  "
                ></i>
                <span>{{ t("passwordRuleMatches", "Passwords match") }}</span>
              </div>
            </div>

            <div v-if="passwordError" class="form-error">
              <i class="fas fa-exclamation-triangle"></i>
              {{ passwordError }}
            </div>

            <div class="modal-actions modal-actions-right">
              <button
                type="button"
                class="btn btn-secondary"
                :disabled="submitting"
                @click="closeCreateModal"
              >
                {{ t("cancel", "Cancel") }}
              </button>
              <button
                type="submit"
                class="btn btn-primary"
                :disabled="submitting"
              >
                <i
                  :class="
                    submitting ? 'fas fa-spinner fa-spin' : 'fas fa-check'
                  "
                ></i>
                {{
                  submitting
                    ? t("creating", "Creating...")
                    : t("createAccount", "Create Account")
                }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </transition>

    <transition name="modal">
      <div v-if="showSuccess" class="modal-overlay" @click.self="closeSuccess">
        <div class="success-modal">
          <div class="success-icon"><i class="fas fa-check"></i></div>
          <h2>
            {{
              t("accountCreatedSuccessfully", "Account Created Successfully!")
            }}
          </h2>
          <p>
            {{
              t(
                "tradingAccountReady",
                "Your trading account has been created and is ready to use.",
              )
            }}
          </p>

          <div class="account-info-box" v-if="createdAccount">
            <div class="account-info-row">
              <span>{{ t("accountNumber", "Account Number") }}</span>
              <span>{{ createdAccount.accountNumber }}</span>
            </div>
            <div class="account-info-row">
              <span>{{ t("platform", "Platform") }}</span>
              <span>{{ createdAccount.platformName }}</span>
            </div>
            <div class="account-info-row">
              <span>{{ t("accountType", "Account Type") }}</span>
              <span>{{ createdAccount.accountType }}</span>
            </div>
            <div class="account-info-row">
              <span>{{ t("leverage", "Leverage") }}</span>
              <span>{{ createdAccount.leverageValue }}</span>
            </div>
            <div class="account-info-row">
              <span>{{ t("currency", "Currency") }}</span>
              <span>{{ createdAccount.accountCurrency }}</span>
            </div>
          </div>

          <p v-if="!isAppOpenAccount" class="countdown">
            {{ t("redirectingToDashboardIn", "Redirecting to dashboard in") }}
            <span class="countdown-number">{{ countdown }}</span>
            {{ t("seconds", "seconds") }}...
          </p>

          <div class="modal-actions">
            <router-link
              v-if="!isAppOpenAccount"
              class="btn btn-primary"
              to="/client/dashboard"
              >{{ t("goToDashboard", "Go to Dashboard") }}</router-link
            >
            <button class="btn btn-secondary" @click="closeSuccess">
              {{ t("close", "Close") }}
            </button>
          </div>
        </div>
      </div>
    </transition>

    <transition name="modal">
      <div v-if="actionModal.visible" class="action-modal-overlay">
        <div class="action-modal-card">
          <div class="action-modal-icon">
            <i :class="actionModal.icon"></i>
          </div>
          <h3>{{ actionModal.title }}</h3>
          <p>{{ actionModal.message }}</p>
          <div class="action-modal-footer">
            <div class="action-modal-countdown">
              {{ t("actionStartsIn", "Starting in") }}
              {{ actionModal.countdown }}s
            </div>
            <div class="action-modal-progress">
              <div
                class="action-modal-progress-bar"
                :style="{
                  width: `${((4 - actionModal.countdown) / 3) * 100}%`,
                }"
              ></div>
            </div>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useClientAuthStore } from "@/stores/clientAuth";
import { useLanguageStore } from "@/stores/language";
import loginSettingsService from "@/services/loginSettingsService";
import tradingAccountService from "@/services/tradingAccountService";
import { sendToFlutter } from "@/utils/flutterBridge";
import KycRequiredNotice from "@/components/client/KycRequiredNotice.vue";

const router = useRouter();
const route = useRoute();
const clientAuthStore = useClientAuthStore();
// /app/openaccount 是嵌在 Flutter App 内的开户页：
// 由宿主负责跳转/关闭，前端不显示下载/打开平台按钮，也不自动跳 dashboard
const isAppOpenAccount = computed(() => route.name === "app-openaccount");
const isKycApproved = computed(() => clientAuthStore.isKycApproved);
const languageStore = useLanguageStore();
const t = (key, fallback = "") => languageStore.t(key, fallback);

const loadingPlatforms = ref(true);
const platforms = ref([]);
const selectedPlatformId = ref(null);
const selectedLeverage = ref("");
const submitting = ref(false);
const error = ref("");
const formError = ref("");
const platformDefaultGroupsMap = ref({});
/** 若客户绑定了父级 IB，此处为父级 IB 的组别信息 { groupName, tradingId, platformKey } */
const parentIbGroup = ref(null);

const form = ref({
  accountNickname: "",
  accountCurrency: "USD",
  accountCurrencyDisplay: "USD - US Dollar",
  accountType: "",
});

const showSuccess = ref(false);
const showCreateModal = ref(false);
const createdAccount = ref(null);
const countdown = ref(5);
const passwordError = ref("");
const passwordForm = ref({
  password: "",
  confirmPassword: "",
});
const actionModal = ref({
  visible: false,
  title: "",
  message: "",
  icon: "fas fa-spinner fa-spin",
  countdown: 3,
});
const showPassword = ref(false);
const showConfirmPassword = ref(false);
let countdownTimer = null;
let actionModalTimer = null;
let actionModalCountdownTimer = null;
const mt5PasswordRegex =
  /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9])[^\s]{8,16}$/;

const platformIconMap = {
  mt4: "fas fa-chart-line",
  mt5: "fas fa-chart-area",
  financepro: "fas fa-rocket",
};

const platformFeaturesMap = {
  mt4: [
    t("platformFeatureMt4_1", "User-friendly interface"),
    t("platformFeatureMt4_2", "Reliable forex & CFD trading"),
    t("platformFeatureMt4_3", "Expert Advisors library"),
    t("platformFeatureMt4_4", "Mobile and desktop support"),
  ],
  mt5: [
    t("platformFeatureMt5_1", "Multi-asset trading support"),
    t("platformFeatureMt5_2", "Advanced charting tools"),
    t("platformFeatureMt5_3", "Integrated economic calendar"),
    t("platformFeatureMt5_4", "Algorithmic trading (EAs)"),
  ],
  financepro: [
    t("platformFeatureFp_1", "Ultra-fast execution"),
    t("platformFeatureFp_2", "Advanced risk management"),
    t("platformFeatureFp_3", "Real-time market analytics"),
    t("platformFeatureFp_4", "Social trading features"),
  ],
};

const selectedPlatform = computed(() => {
  const id = selectedPlatformId.value;
  return platforms.value.find((platform) => platform.id === id) || null;
});

const requiresTradingPassword = computed(() =>
  Boolean(selectedPlatform.value?.requirePassword),
);

const canSubmit = computed(() => {
  return (
    !!selectedPlatformId.value &&
    form.value.accountNickname.trim().length >= 3 &&
    !!selectedLeverageOption.value &&
    accountTypeOptions.value.length > 0 &&
    !!selectedAccountTypeOption.value
  );
});

const platformIcon = (platform) => {
  const key = (platform.platformKey || platform.shortCode || "").toLowerCase();
  return platformIconMap[key] || "fas fa-chart-line";
};

const platformFeatures = (platform) => {
  const key = (platform.platformKey || platform.shortCode || "").toLowerCase();
  if (Array.isArray(platform.features) && platform.features.length) {
    return platform.features;
  }
  return (
    platformFeaturesMap[key] || [
      t("platformFeatureFastExecution", "Fast execution speed"),
      t("platformFeatureSecurity", "Institutional-grade security"),
      t("platformFeatureSupport", "24/7 support"),
    ]
  );
};

const hasPlatformLink = (platform) => {
  return Boolean(
    platform &&
    typeof platform.url === "string" &&
    platform.url.trim().length > 0,
  );
};

const handlePlatformLink = (platform) => {
  const url = typeof platform?.url === "string" ? platform.url.trim() : "";

  if (!url) {
    return;
  }

  showActionModal(platform?.download === false ? "open" : "download");

  if (actionModalTimer) {
    clearTimeout(actionModalTimer);
  }
  if (actionModalCountdownTimer) {
    clearInterval(actionModalCountdownTimer);
  }

  actionModalCountdownTimer = setInterval(() => {
    if (actionModal.value.countdown > 1) {
      actionModal.value.countdown -= 1;
    }
  }, 1000);

  actionModalTimer = setTimeout(() => {
    const link = document.createElement("a");
    link.href = url;
    link.target = "_blank";
    link.rel = "noopener noreferrer";
    link.click();
    hideActionModal();
    actionModalTimer = null;
    if (actionModalCountdownTimer) {
      clearInterval(actionModalCountdownTimer);
      actionModalCountdownTimer = null;
    }
  }, 3000);
};

const getPlatformActionLabel = (platform) => {
  return platform?.download === false
    ? t("openPlatform", "Open Platform")
    : t("downloadPlatform", "Download Platform");
};

const getPlatformActionIcon = (platform) => {
  return platform?.download === false ? "fas fa-globe" : "fas fa-download";
};

const showActionModal = (actionType) => {
  actionModal.value = {
    visible: true,
    title:
      actionType === "open"
        ? t("openingPlatform", "Opening Platform")
        : t("downloadingPlatform", "Downloading Platform"),
    message:
      actionType === "open"
        ? t("openingPlatformMessage", "You will be redirected shortly...")
        : t(
            "downloadingPlatformMessage",
            "Your download will start shortly...",
          ),
    icon: actionType === "open" ? "fas fa-globe" : "fas fa-download",
    countdown: 3,
  };
};

const hideActionModal = () => {
  actionModal.value.visible = false;
};

const formatCurrency = (value) => {
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "USD",
  }).format(Number(value || 0));
};

const setAccountCurrency = (currencyCode = "USD") => {
  const normalizedCurrency =
    String(currencyCode || "USD")
      .trim()
      .toUpperCase() || "USD";
  form.value.accountCurrency = normalizedCurrency;
  form.value.accountCurrencyDisplay = normalizedCurrency;
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

const normalizedAccountTypeOptions = computed(() =>
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

const accountTypeOptions = computed(() => normalizedAccountTypeOptions.value);

const selectedAccountTypeOption = computed(
  () =>
    accountTypeOptions.value.find(
      (option) => option.value === form.value.accountType,
    ) || null,
);

const passwordRuleStatus = computed(() => {
  const password = String(passwordForm.value.password || "");
  const confirmPassword = String(passwordForm.value.confirmPassword || "");

  return {
    length:
      password.length >= 8 && password.length <= 16 && !/\s/.test(password),
    uppercase: /[A-Z]/.test(password),
    lowercase: /[a-z]/.test(password),
    number: /\d/.test(password),
    special: /[^A-Za-z0-9]/.test(password),
    matches:
      password.length > 0 &&
      confirmPassword.length > 0 &&
      password === confirmPassword,
  };
});

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

  try {
    const response = await loginSettingsService.getDefaultTradingGroups(
      normalizedPlatformKey,
    );
    platformDefaultGroupsMap.value = {
      ...platformDefaultGroupsMap.value,
      [normalizedPlatformKey]: normalizeDefaultTradingGroups(response),
    };
  } catch (err) {
    console.error(
      `Failed to load default trading groups for ${normalizedPlatformKey}:`,
      err,
    );
    platformDefaultGroupsMap.value = {
      ...platformDefaultGroupsMap.value,
      [normalizedPlatformKey]: { groups: [], leverage: [] },
    };
  }
};

const handlePlatformSelect = async (platformId) => {
  if (submitting.value) return;
  const id = Number(platformId);
  selectedPlatformId.value = id;
  selectedLeverage.value = "";
  form.value.accountType = "";

  const platform = platforms.value.find((item) => item.id === id);
  if (platform?.platformKey) {
    await fetchDefaultTradingGroups(platform.platformKey);
  }
};

watch(selectedPlatformId, (newValue) => {
  formError.value = "";
  if (!newValue) {
    selectedLeverage.value = "";
    form.value.accountType = "";
    return;
  }
});

watch(selectedLeverage, () => {
  formError.value = "";
});

watch(
  selectedAccountTypeOption,
  (option) => {
    setAccountCurrency(resolveGroupCurrency(option));
  },
  { immediate: true },
);

watch(
  accountTypeOptions,
  (options) => {
    if (!Array.isArray(options) || !options.length) {
      form.value.accountType = "";
      setAccountCurrency("USD");
      return;
    }

    const currentExists = options.some(
      (option) => option.value === form.value.accountType,
    );
    if (!currentExists) {
      form.value.accountType = "";
    }
  },
  { immediate: true },
);

const resetForm = () => {
  form.value.accountNickname = "";
  form.value.accountType = "";
  setAccountCurrency("USD");
  formError.value = "";
  selectedPlatformId.value = null;
  selectedLeverage.value = "";
};

const executeCreateAccount = async ({
  password = "",
  confirmPassword = "",
} = {}) => {
  submitting.value = true;
  formError.value = "";

  try {
    const platform = selectedPlatform.value;
    if (!platform) {
      formError.value = t(
        "pleaseSelectTradingPlatform",
        "Please select a trading platform.",
      );
      submitting.value = false;
      return;
    }

    const payload = {
      platformKey: platform.platformKey,
      accountNickname: form.value.accountNickname.trim(),
      leverageValue: selectedLeverageOption.value?.leverage,
      initialDeposit: 100,
      accountCurrency: form.value.accountCurrency,
      accountType:
        selectedAccountTypeOption.value?.label || form.value.accountType,
    };

    if (requiresTradingPassword.value) {
      payload.password = password;
      payload.confirmPassword = confirmPassword;
    }

    if (selectedAccountTypeOption.value?.tradingId != null) {
      payload.groupTradingId = selectedAccountTypeOption.value.tradingId;
    }

    const response = await tradingAccountService.createAccount(payload);

    if (response.success && response.data) {
      closeCreateModal();
      createdAccount.value = response.data;
      showSuccess.value = true;
      if (!isAppOpenAccount.value) {
        startCountdown();
      }
      resetForm();
      sendToFlutter("openAccountSuccess", true);
    } else {
      formError.value =
        response.message ||
        t("failedToCreateTradingAccount", "Failed to create trading account.");
    }
  } catch (err) {
    console.error("Failed to create account:", err);
    formError.value =
      err.response?.data?.message ||
      err.message ||
      t("failedToCreateTradingAccount", "Failed to create trading account.");
  } finally {
    submitting.value = false;
  }
};

const handleSubmit = async () => {
  if (!canSubmit.value || submitting.value) return;

  if (!requiresTradingPassword.value) {
    await executeCreateAccount();
    return;
  }

  passwordError.value = "";
  passwordForm.value.password = "";
  passwordForm.value.confirmPassword = "";
  showPassword.value = false;
  showConfirmPassword.value = false;
  showCreateModal.value = true;
};

const closeCreateModal = () => {
  if (submitting.value) return;

  showCreateModal.value = false;
  passwordError.value = "";
  passwordForm.value.password = "";
  passwordForm.value.confirmPassword = "";
  showPassword.value = false;
  showConfirmPassword.value = false;
};

const confirmCreateAccount = async () => {
  if (!canSubmit.value || submitting.value) return;

  const password = String(passwordForm.value.password || "").trim();
  const confirmPassword = String(
    passwordForm.value.confirmPassword || "",
  ).trim();
  passwordError.value = "";

  if (!password || !confirmPassword) {
    passwordError.value = t(
      "pleaseEnterPasswordAndConfirm",
      "Please enter password and confirm password.",
    );
    return;
  }

  if (!mt5PasswordRegex.test(password)) {
    passwordError.value = t(
      "tradingPasswordRule",
      "Password must be 8-16 characters and include uppercase, lowercase, number, and special character.",
    );
    return;
  }

  if (password !== confirmPassword) {
    passwordError.value = t(
      "passwordConfirmMismatch",
      "Password and confirm password do not match.",
    );
    return;
  }

  await executeCreateAccount({ password, confirmPassword });
};

const startCountdown = () => {
  countdown.value = 5;
  if (countdownTimer) {
    clearInterval(countdownTimer);
  }
  countdownTimer = setInterval(() => {
    countdown.value -= 1;
    if (countdown.value <= 0) {
      clearInterval(countdownTimer);
      countdownTimer = null;
      router.push("/client/dashboard");
    }
  }, 1000);
};

const closeSuccess = () => {
  showSuccess.value = false;
  if (countdownTimer) {
    clearInterval(countdownTimer);
    countdownTimer = null;
  }
};

const loadPlatforms = async () => {
  loadingPlatforms.value = true;
  error.value = "";
  try {
    const response = await tradingAccountService.getPlatforms();
    if (response.success && response.data) {
      parentIbGroup.value = response.data.parentIbGroup || null;
      platforms.value = (response.data.platforms || []).map((platform) => {
        return {
          ...platform,
          id: Number(platform.id),
        };
      });
    } else {
      error.value =
        response.message ||
        t("failedToLoadPlatforms", "Failed to load platforms.");
    }
  } catch (err) {
    console.error("Failed to load platforms:", err);
    error.value =
      err.response?.data?.message ||
      err.message ||
      t("failedToLoadTradingPlatforms", "Failed to load trading platforms.");
  } finally {
    loadingPlatforms.value = false;
  }
};

onMounted(async () => {
  // /app/openaccount 入口：强制再拉一次最新 user/kycStatus，避免 KYC 状态滞后让 gate 误判通过；
  // 未通过 KYC 时模板已渲染 KycRequiredNotice，不再加载开户主体数据
  if (isAppOpenAccount.value && clientAuthStore.token) {
    await clientAuthStore.fetchUser();
    if (!clientAuthStore.isKycApproved) {
      return;
    }
  }
  loadPlatforms();
});

onUnmounted(() => {
  if (countdownTimer) {
    clearInterval(countdownTimer);
  }
  if (actionModalTimer) {
    clearTimeout(actionModalTimer);
  }
  if (actionModalCountdownTimer) {
    clearInterval(actionModalCountdownTimer);
  }
});
</script>

<style scoped>
.open-account-page {
  background: var(--color-canvas);
  padding: 0 0 60px;
}

.open-account-page .container {
  max-width: 1200px;
  margin: 0 auto;
}

.page-header {
  text-align: center;
  margin-bottom: 50px;
}

.page-header h2 {
  font-size: 32px;
  color: var(--color-ink);
  margin-bottom: 15px;
  font-weight: 700;
}

.page-header p {
  font-size: 16px;
  color: var(--color-muted);
  line-height: 1.6;
}

.state-card {
  background: #ffffff;
  border-radius: var(--radius-xl);
  padding: 40px 30px;
  text-align: center;
  color: var(--color-text);
  box-shadow: 0 12px 30px rgba(17, 24, 39, 0.12);
}

.state-card i {
  font-size: 32px;
  color: var(--color-brand);
  margin-bottom: 14px;
}

.state-card.error i {
  color: var(--color-danger);
}

.state-card .btn {
  margin-top: 16px;
}

.platform-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 30px;
  margin-bottom: 40px;
  justify-items: center;
}

.platform-card {
  background: #ffffff;
  border-radius: var(--radius-xl);
  padding: 35px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  border: 3px solid transparent;
  cursor: pointer;
  transition:
    border-color 0.3s ease,
    background-color 0.3s ease,
    box-shadow 0.3s ease;
  position: relative;
  width: 100%;
  max-width: 450px;
}

.platform-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.platform-card.selected {
  border-color: var(--color-brand);
  background: linear-gradient(
    135deg,
    rgba(var(--color-brand-rgb), 0.05) 0%,
    rgba(var(--color-brand-rgb), 0.05) 100%
  );
}

/*.platform-card.recommended::after {*/
/*  content: 'Recommended';*/
/*  position: absolute;*/
/*  top: 20px;*/
/*  right: 20px;*/
/*  background: linear-gradient(135deg, #f6ad55 0%, #ed8936 100%);*/
/*  color: #ffffff;*/
/*  padding: 4px 12px;*/
/*  border-radius: 999px;*/
/*  font-size: 12px;*/
/*  font-weight: 600;*/
/*  box-shadow: 0 8px 18px rgba(237, 137, 54, 0.35);*/
/*}*/

.select-indicator {
  position: absolute;
  top: 20px;
  right: 20px;
  width: 30px;
  height: 30px;
  border-radius: 50%;
  background: var(--color-border);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--color-text);
  transition:
    background 0.2s ease,
    color 0.2s ease;
}

.platform-card.selected .select-indicator {
  background: var(--color-brand);
  color: #ffffff;
}

.platform-card.selected .select-indicator i {
  color: #ffffff;
}

.platform-card-header {
  text-align: center;
  margin-bottom: 25px;
}

.platform-icon {
  width: 80px;
  height: 80px;
  background: var(--color-brand);
  border-radius: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 20px;
  font-size: 36px;
  color: #ffffff;
  transition: color 0.3s ease;
}

.platform-card:hover .platform-icon {
  transform: scale(1.1);
}

.platform-card.selected .platform-icon {
  box-shadow: 0 8px 20px rgba(var(--color-brand-rgb), 0.4);
}

.platform-name {
  font-size: 24px;
  font-weight: 700;
  color: var(--color-ink);
  margin-bottom: 8px;
}

.platform-description {
  font-size: 14px;
  color: var(--color-muted);
  line-height: 1.6;
}

.platform-features {
  margin: 25px 0;
}

.platform-actions {
  display: flex;
  justify-content: center;
  margin-top: 20px;
}

.platform-download-btn {
  min-width: 160px;
  justify-content: center;
}

.action-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2100;
  padding: 24px;
}

.action-modal-card {
  width: min(100%, 360px);
  background: #ffffff;
  border-radius: 20px;
  padding: 28px 24px;
  box-shadow: 0 24px 60px rgba(15, 23, 42, 0.2);
  text-align: center;
}

.action-modal-icon {
  width: 64px;
  height: 64px;
  margin: 0 auto 16px;
  border-radius: 18px;
  background: var(--color-brand-soft);
  color: var(--color-brand);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
}

.action-modal-card h3 {
  margin: 0 0 10px;
  font-size: 22px;
  color: #1f2937;
}

.action-modal-card p {
  margin: 0;
  color: #64748b;
  line-height: 1.6;
}

.action-modal-footer {
  margin-top: 18px;
}

.action-modal-countdown {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 8px 14px;
  border-radius: 999px;
  background: var(--color-brand-soft);
  color: var(--color-brand);
  font-size: 14px;
  font-weight: 600;
}

.action-modal-progress {
  width: 100%;
  height: 8px;
  margin-top: 14px;
  border-radius: 999px;
  background: var(--color-border);
  overflow: hidden;
}

.action-modal-progress-bar {
  height: 100%;
  border-radius: inherit;
  background: linear-gradient(
    90deg,
    var(--color-brand) 0%,
    var(--color-brand-strong) 100%
  );
  transition: width 1s linear;
}

.feature-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 0;
  font-size: 14px;
  color: var(--color-text);
}

.feature-item i {
  color: var(--color-success);
  font-size: 16px;
}

.platform-selection {
  margin-top: 25px;
}

.selection-label {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 12px;
}

.leverage-select {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  transition:
    border-color 0.3s ease,
    background-color 0.3s ease,
    box-shadow 0.3s ease;
  font-family: inherit;
  background: #ffffff;
  cursor: pointer;
}

.leverage-select:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.platform-card.selected .leverage-select {
  border-color: var(--color-brand);
}

.account-details-section {
  background: #ffffff;
  border-radius: var(--radius-xl);
  padding: 40px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  margin-bottom: 30px;
  display: none;
}

.account-details-section.show {
  display: block;
  animation: fadeIn 0.5s ease;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.account-form {
  display: flex;
  flex-direction: column;
  gap: 26px;
}

.form-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 26px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.form-group label {
  font-weight: 600;
  color: var(--color-ink);
  font-size: 14px;
}

.form-group .required {
  color: var(--color-danger);
  margin-left: 4px;
}

.form-group input,
.form-group select {
  padding: 12px 16px;
  border-radius: var(--radius-md);
  border: 2px solid var(--color-border);
  font-size: 14px;
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease;
}

.form-group input:focus,
.form-group select:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.2);
}

.form-group .readonly {
  background: var(--color-surface-soft);
  color: var(--color-text);
  cursor: not-allowed;
}

.form-group small {
  color: var(--color-muted);
  font-size: 12px;
  line-height: 1.4;
}

.form-group small i {
  margin-right: 6px;
  color: var(--color-brand);
}

.form-error {
  display: flex;
  align-items: center;
  gap: 10px;
  background: rgba(245, 101, 101, 0.15);
  color: var(--color-danger);
  border-radius: var(--radius-lg);
  padding: 12px 16px;
}

.action-buttons {
  display: flex;
  justify-content: flex-end;
  gap: 14px;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 13px 26px;
  border-radius: var(--radius-md);
  border: none;
  font-weight: 600;
  cursor: pointer;
  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease;
}

.btn-primary {
  background: var(--color-brand);
  color: #ffffff;
  box-shadow: 0 14px 32px rgba(var(--color-brand-rgb), 0.35);
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 18px 38px rgba(var(--color-brand-rgb), 0.45);
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

.btn-secondary {
  background: var(--color-border);
  color: var(--color-ink);
}

.btn-secondary:hover {
  background: var(--color-border-strong);
}

.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.25s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(17, 24, 39, 0.55);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  z-index: 9999;
}

.create-modal,
.success-modal {
  background: #ffffff;
  border-radius: 18px;
  max-width: 500px;
  width: 100%;
  box-shadow: 0 24px 50px rgba(45, 55, 72, 0.28);
}

.success-modal {
  padding: 40px 36px;
  text-align: center;
}

.create-modal {
  max-width: 560px;
  overflow: hidden;
}

.create-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 22px 24px;
  border-bottom: 1px solid #e5e7eb;
}

.create-modal-header h2 {
  margin: 0;
  font-size: 22px;
  color: #1f2937;
}

.create-modal-body {
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.modal-close-btn {
  border: none;
  background: transparent;
  color: #64748b;
  font-size: 28px;
  line-height: 1;
  cursor: pointer;
}

.success-icon {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: linear-gradient(
    135deg,
    var(--color-success) 0%,
    var(--color-success) 100%
  );
  display: flex;
  align-items: center;
  justify-content: center;
  color: #ffffff;
  font-size: 36px;
  margin: 0 auto 24px;
  box-shadow: 0 12px 30px rgba(56, 161, 105, 0.4);
}

.account-info-box {
  margin: 24px 0;
  background: var(--color-surface-soft);
  border-radius: 14px;
  padding: 22px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  text-align: left;
}

.account-info-row {
  display: flex;
  justify-content: space-between;
  color: var(--color-ink);
  font-size: 14px;
  border-bottom: 1px solid var(--color-border);
  padding-bottom: 8px;
}

.account-info-row:last-child {
  border-bottom: none;
}

.account-info-row span:first-child {
  color: var(--color-muted);
}

.countdown {
  margin-top: 16px;
  color: var(--color-muted);
  font-size: 14px;
}

.countdown-number {
  font-weight: 700;
  color: var(--color-brand);
  margin: 0 4px;
}

.modal-actions {
  margin-top: 24px;
  display: flex;
  justify-content: center;
  gap: 12px;
}

.modal-actions-right {
  justify-content: flex-end;
}

.password-input-wrapper {
  position: relative;
}

.password-input {
  width: 100%;
  padding-right: 48px !important;
}

.password-toggle-btn {
  position: absolute;
  top: 50%;
  right: 12px;
  transform: translateY(-50%);
  border: none;
  background: transparent;
  color: #64748b;
  cursor: pointer;
}

.password-rule-box {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px 14px;
  border-radius: var(--radius-lg);
  background: var(--color-surface-soft);
  color: #475569;
  font-size: 14px;
  line-height: 1.5;
}

.password-rule-box i {
  margin-top: 3px;
  color: var(--color-brand);
}

.password-checklist {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.password-check-item {
  display: flex;
  align-items: center;
  gap: 10px;
  color: #64748b;
  font-size: 14px;
}

.password-check-item.met {
  color: var(--color-success);
}

.password-check-item i {
  width: 16px;
}

@media (max-width: 1024px) {
  .platform-cards {
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  }
}

@media (max-width: 768px) {
  .open-account-page {
    padding: 20px 16px 40px;
  }

  .page-header {
    margin-bottom: 28px;
  }

  .page-header h2 {
    font-size: 22px;
    margin-bottom: 8px;
  }

  .page-header p {
    font-size: 13px;
  }

  /* 手机端横向布局：每行一个 platform，从左到右依次为 icon → 名称 → 优势，
     操作按钮（下载/打开）单独占一行，选中态图标缩小贴右上角避免和优势重叠 */
  .platform-cards {
    grid-template-columns: 1fr;
    gap: 12px;
    margin-bottom: 24px;
  }

  .platform-card {
    padding: 14px 16px;
    max-width: none;
    border-width: 2px;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    column-gap: 12px;
    row-gap: 10px;
    align-items: center;
  }

  .platform-card:hover {
    transform: none;
  }

  /* header 内部用 grid：icon 跨两行在左，name + description 在右堆叠 */
  .platform-card-header {
    grid-column: 1;
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    grid-template-areas:
      "icon name"
      "icon desc";
    column-gap: 12px;
    row-gap: 2px;
    align-items: center;
    text-align: left;
    margin: 0;
  }

  .platform-icon {
    grid-area: icon;
    width: 44px;
    height: 44px;
    font-size: 20px;
    border-radius: var(--radius-md);
    margin: 0;
  }

  .platform-card:hover .platform-icon {
    transform: none;
  }

  .platform-name {
    grid-area: name;
    font-size: 15px;
    margin: 0;
    align-self: end;
  }

  .platform-description {
    grid-area: desc;
    font-size: 11px;
    line-height: 1.3;
    margin: 0;
    align-self: start;
    color: #94a3b8;
    /* 单行截断：横向布局空间有限，长 description 直接省略 */
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }

  /* 优势放在卡片右侧列，只保留前 2 条，紧凑字号 */
  .platform-features {
    grid-column: 2;
    grid-row: 1;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 4px;
    max-width: 140px;
    padding-right: 26px; /* 给右上角的选中标记让位 */
  }

  .platform-features .feature-item {
    padding: 0;
    font-size: 11px;
    gap: 4px;
    line-height: 1.3;
  }

  .platform-features .feature-item i {
    font-size: 12px;
  }

  .platform-features .feature-item:nth-child(n + 3) {
    display: none;
  }

  /* 下载/打开按钮占满第二行，避免和上面三列挤在一起 */
  .platform-actions {
    grid-column: 1 / -1;
    margin: 0;
  }

  .platform-download-btn {
    min-width: 0;
    width: 100%;
    padding: 8px 12px;
    font-size: 12px;
  }

  .select-indicator {
    top: 10px;
    right: 10px;
    width: 20px;
    height: 20px;
    font-size: 11px;
  }

  .account-details-section {
    padding: 24px 18px;
    border-radius: 14px;
  }

  .form-row {
    grid-template-columns: 1fr;
    gap: 18px;
  }

  .action-buttons {
    flex-direction: column-reverse;
  }

  .action-buttons .btn {
    width: 100%;
    justify-content: center;
  }

  .account-info-row {
    flex-direction: column;
    gap: 4px;
  }

  .modal-actions {
    flex-direction: column;
  }
}

/* 极窄屏（< 360px）兜底：避免两列卡片挤到字都断行 */
@media (max-width: 359px) {
  .platform-cards {
    grid-template-columns: 1fr;
  }
}
</style>
