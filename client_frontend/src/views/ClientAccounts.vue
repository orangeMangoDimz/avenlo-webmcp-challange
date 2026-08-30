<template>
  <div class="accounts-page ui-page">
    <div class="page-header ui-page-header">
      <div class="page-title">
        <h2>
          <i class="fas fa-wallet"></i>
          {{ t("myTradingAccounts", "My Trading Accounts") }}
        </h2>
        <p>
          {{
            t(
              "manageTradingAccounts",
              "Manage and review the trading accounts you have opened.",
            )
          }}
        </p>
      </div>
      <router-link class="btn btn-primary" to="/client/account/new">
        <i class="fas fa-plus-circle"></i>
        {{ t("openNewAccount", "Open New Account") }}
      </router-link>
    </div>

    <div v-if="loading" class="state-card">
      <i class="fas fa-spinner fa-spin"></i>
      <p>
        {{ t("loadingTradingAccounts", "Loading your trading accounts...") }}
      </p>
    </div>

    <div v-else-if="error" class="state-card error">
      <i class="fas fa-exclamation-triangle"></i>
      <p>{{ error }}</p>
    </div>

    <div v-else-if="accounts.length === 0" class="state-card empty">
      <i class="fas fa-info-circle"></i>
      <p>
        {{
          t(
            "noTradingAccountsYet",
            "You have not opened any trading accounts yet.",
          )
        }}
      </p>
      <router-link to="/client/account/new" class="btn btn-secondary">
        {{ t("openFirstAccount", "Open your first account") }}
      </router-link>
    </div>

    <div v-else class="accounts-table-wrapper ui-data-region ui-table-scroll">
      <table class="accounts-table">
        <thead>
          <tr>
            <th>{{ t("account", "Account") }}</th>
            <th>{{ t("platform", "Platform") }}</th>
            <th>{{ t("groupLabel", "Group Label") }}</th>
            <th>{{ t("leverage", "Leverage") }}</th>
            <th>{{ t("currency", "Currency") }}</th>
            <th>{{ t("balance", "Balance") }}</th>
            <th>{{ t("credit", "Credit") }}</th>
            <th>{{ t("status", "Status") }}</th>
            <th>{{ t("created", "Created") }}</th>
            <th class="platform-action-column"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="account in accounts" :key="account.id">
            <td>
              <div class="account-info">
                <span class="account-number">{{ account.accountNumber }}</span>
                <span class="account-nickname">{{
                  account.accountNickname
                }}</span>
              </div>
            </td>
            <td>
              <span class="platform-pill">{{
                account.platformName || account.platformCode
              }}</span>
            </td>
            <td>{{ formatGroupLabel(account) }}</td>
            <td>{{ account.leverageValue }}</td>
            <td>{{ formatAccountCurrency(account) }}</td>
            <td>{{ formatAccountBalance(account) }}</td>
            <td>{{ formatAccountCredit(account) }}</td>
            <td>
              <span :class="['status-pill', account.status]">{{
                formatStatus(account.status)
              }}</span>
            </td>
            <td>{{ formatDate(account.createdAt) }}</td>
            <td class="platform-action-cell">
              <button
                type="button"
                class="account-action-button"
                :class="{
                  active: menuAccount && menuAccount.id === account.id,
                }"
                :title="t('manage', 'Manage')"
                @click.stop="toggleActionMenu($event, account)"
              >
                <i class="fas fa-ellipsis-v"></i>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

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

    <transition name="modal">
      <div v-if="leverageModal.visible" class="action-modal-overlay">
        <div class="form-modal-card">
          <h3>{{ t("changeLeverage", "Change Leverage") }}</h3>
          <p class="form-modal-sub">
            {{ leverageModal.account?.accountNumber }}
            <span v-if="leverageModal.account">
              ·
              {{
                leverageModal.account.platformName ||
                leverageModal.account.platformCode
              }}</span
            >
          </p>
          <template v-if="!leverageModal.done">
            <p class="form-modal-current">
              {{ t("currentLeverage", "Current leverage") }}:
              {{ leverageModal.account?.leverageValue || "-" }}
            </p>
            <div class="form-modal-field">
              <label>{{ t("leverage", "Leverage") }}</label>
              <select
                v-model="leverageModal.leverageValue"
                :disabled="leverageModal.submitting || leverageModal.loading"
              >
                <option value="" disabled>
                  {{
                    leverageModal.loading
                      ? t("loading", "Loading...")
                      : t("selectLeveragePlaceholder", "Select leverage")
                  }}
                </option>
                <option
                  v-for="opt in leverageModal.options"
                  :key="opt.leverageValue"
                  :value="opt.leverageValue"
                >
                  {{ opt.displayLabel || opt.leverageValue }}
                </option>
              </select>
            </div>
            <p v-if="leverageModal.error" class="form-modal-error">
              {{ leverageModal.error }}
            </p>
            <div class="form-modal-actions">
              <button
                type="button"
                class="form-btn-secondary"
                @click="closeLeverageModal"
                :disabled="leverageModal.submitting"
              >
                {{ t("cancel", "Cancel") }}
              </button>
              <button
                type="button"
                class="form-btn-primary"
                @click="submitLeverage"
                :disabled="
                  leverageModal.submitting || !leverageModal.leverageValue
                "
              >
                <i
                  v-if="leverageModal.submitting"
                  class="fas fa-spinner fa-spin"
                ></i>
                {{ t("submit", "Submit") }}
              </button>
            </div>
          </template>
          <template v-else>
            <p class="form-modal-success">
              <i class="fas fa-check-circle"></i>
              {{ t("leverageChangeSuccess", "Leverage updated successfully.") }}
            </p>
            <div class="form-modal-actions">
              <button
                type="button"
                class="form-btn-primary"
                @click="closeLeverageModal"
              >
                {{ t("close", "Close") }}
              </button>
            </div>
          </template>
        </div>
      </div>
    </transition>

    <transition name="modal">
      <div v-if="passwordModal.visible" class="action-modal-overlay">
        <div class="form-modal-card">
          <h3>{{ t("changeTradingPassword", "Change Trading Password") }}</h3>
          <p class="form-modal-sub">
            {{ passwordModal.account?.accountNumber }}
            <span v-if="passwordModal.account">
              ·
              {{
                passwordModal.account.platformName ||
                passwordModal.account.platformCode
              }}</span
            >
          </p>
          <template v-if="!passwordModal.done">
            <div class="form-modal-field">
              <label>{{ t("verificationCode", "Verification Code") }}</label>
              <div class="form-modal-code-row">
                <input
                  v-model="passwordModal.code"
                  type="text"
                  inputmode="numeric"
                  autocomplete="one-time-code"
                  :placeholder="
                    t(
                      'enterVerificationCode',
                      'Enter the code sent to your email',
                    )
                  "
                  :disabled="passwordModal.submitting"
                />
                <button
                  type="button"
                  class="form-btn-secondary form-modal-code-btn"
                  :disabled="
                    passwordModal.submitting ||
                    passwordModal.sendingCode ||
                    passwordModal.cooldown > 0
                  "
                  @click="sendPasswordCode"
                >
                  <i
                    v-if="passwordModal.sendingCode"
                    class="fas fa-spinner fa-spin"
                  ></i>
                  <span v-if="passwordModal.cooldown > 0"
                    >{{ passwordModal.cooldown }}s</span
                  >
                  <span v-else>{{ t("sendCode", "Send Code") }}</span>
                </button>
              </div>
            </div>
            <div class="form-modal-field">
              <label>{{ t("newPassword", "New Password") }}</label>
              <input
                v-model="passwordModal.newPassword"
                type="password"
                autocomplete="new-password"
                :disabled="passwordModal.submitting"
              />
            </div>
            <div class="form-modal-field">
              <label>{{ t("confirmPassword", "Confirm Password") }}</label>
              <input
                v-model="passwordModal.confirmPassword"
                type="password"
                autocomplete="new-password"
                :disabled="passwordModal.submitting"
              />
            </div>
            <p class="form-modal-current">
              {{
                t(
                  "passwordRuleHint",
                  "8-16 characters, with uppercase, lowercase, a number and a special character (#@!$%&*).",
                )
              }}
            </p>
            <p v-if="passwordModal.error" class="form-modal-error">
              {{ passwordModal.error }}
            </p>
            <div class="form-modal-actions">
              <button
                type="button"
                class="form-btn-secondary"
                @click="closePasswordModal"
                :disabled="passwordModal.submitting"
              >
                {{ t("cancel", "Cancel") }}
              </button>
              <button
                type="button"
                class="form-btn-primary"
                @click="submitPassword"
                :disabled="passwordModal.submitting || !canSubmitPassword"
              >
                <i
                  v-if="passwordModal.submitting"
                  class="fas fa-spinner fa-spin"
                ></i>
                {{ t("submit", "Submit") }}
              </button>
            </div>
          </template>
          <template v-else>
            <p class="form-modal-success">
              <i class="fas fa-check-circle"></i>
              {{
                t(
                  "passwordChangeSuccess",
                  "Trading password updated successfully.",
                )
              }}
            </p>
            <div class="form-modal-actions">
              <button
                type="button"
                class="form-btn-primary"
                @click="closePasswordModal"
              >
                {{ t("close", "Close") }}
              </button>
            </div>
          </template>
        </div>
      </div>
    </transition>

    <transition name="modal">
      <div v-if="nameModal.visible" class="action-modal-overlay">
        <div class="form-modal-card">
          <h3>{{ t("changeAccountName", "Change Account Name") }}</h3>
          <p class="form-modal-sub">
            {{ nameModal.account?.accountNumber }}
            <span v-if="nameModal.account">
              ·
              {{
                nameModal.account.platformName || nameModal.account.platformCode
              }}</span
            >
          </p>
          <template v-if="!nameModal.done">
            <p class="form-modal-current">
              {{
                t(
                  "changeAccountNameHint",
                  "This only changes the name shown here, not on the trading platform.",
                )
              }}
            </p>
            <div class="form-modal-field">
              <label>{{ t("accountName", "Account Name") }}</label>
              <input
                v-model="nameModal.name"
                type="text"
                maxlength="100"
                :disabled="nameModal.submitting"
              />
            </div>
            <p v-if="nameModal.error" class="form-modal-error">
              {{ nameModal.error }}
            </p>
            <div class="form-modal-actions">
              <button
                type="button"
                class="form-btn-secondary"
                @click="closeNameModal"
                :disabled="nameModal.submitting"
              >
                {{ t("cancel", "Cancel") }}
              </button>
              <button
                type="button"
                class="form-btn-primary"
                @click="submitName"
                :disabled="nameModal.submitting || !nameModal.name.trim()"
              >
                <i
                  v-if="nameModal.submitting"
                  class="fas fa-spinner fa-spin"
                ></i>
                {{ t("submit", "Submit") }}
              </button>
            </div>
          </template>
          <template v-else>
            <p class="form-modal-success">
              <i class="fas fa-check-circle"></i>
              {{ t("nameChangeSuccess", "Account name updated successfully.") }}
            </p>
            <div class="form-modal-actions">
              <button
                type="button"
                class="form-btn-primary"
                @click="closeNameModal"
              >
                {{ t("close", "Close") }}
              </button>
            </div>
          </template>
        </div>
      </div>
    </transition>

    <!-- 行内操作下拉：Teleport 到 body，避开表格 overflow 裁剪 -->
    <Teleport to="body">
      <div
        v-if="menuAccount"
        ref="actionMenuRef"
        class="account-action-dropdown"
        :style="menuStyle"
      >
        <button
          v-if="getPlatformLinkConfig(menuAccount)"
          type="button"
          class="account-action-item"
          @click="onActionMenuSelect('platform')"
        >
          <i class="fas fa-download"></i>
          <span>{{ getPlatformActionTitle(menuAccount) }}</span>
        </button>
        <button
          type="button"
          class="account-action-item"
          @click="onActionMenuSelect('leverage')"
        >
          <i class="fas fa-sliders-h"></i>
          <span>{{ t("changeLeverage", "Change Leverage") }}</span>
        </button>
        <button
          type="button"
          class="account-action-item"
          @click="onActionMenuSelect('password')"
        >
          <i class="fas fa-key"></i>
          <span>{{
            t("changeTradingPassword", "Change Trading Password")
          }}</span>
        </button>
        <button
          type="button"
          class="account-action-item"
          @click="onActionMenuSelect('name')"
        >
          <i class="fas fa-pen"></i>
          <span>{{ t("changeAccountName", "Change Account Name") }}</span>
        </button>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from "vue";
import { useLanguageStore } from "@/stores/language";
import { useClientAuthStore } from "@/stores/clientAuth";
import tradingAccountService from "@/services/tradingAccountService";
import { formatNumber } from "@/utils/helpers";
import fpIcon from "@/assets/platform-icons/fp.png";
import mt4Icon from "@/assets/platform-icons/mt4.png";
import mt5Icon from "@/assets/platform-icons/mt5.png";

const languageStore = useLanguageStore();
const t = (key, fallback = "") => languageStore.t(key, fallback);

const clientAuthStore = useClientAuthStore();
const userEmail = computed(() => clientAuthStore.user?.email || "");

const accounts = ref([]);
const platformDownloads = ref({});
const loading = ref(true);
const error = ref("");
const actionModal = ref({
  visible: false,
  title: "",
  message: "",
  icon: "fas fa-spinner fa-spin",
  countdown: 3,
});
let actionModalTimer = null;
let actionModalCountdownTimer = null;

const platformIconMap = {
  mt4: mt4Icon,
  mt5: mt5Icon,
  fp: fpIcon,
  financepro: fpIcon,
};

const normalizePlatformKey = (account) => {
  const rawKey = String(
    account?.platformCode ||
      account?.platformKey ||
      account?.platformName ||
      "",
  )
    .trim()
    .toLowerCase();
  const compactKey = rawKey.replace(/[\s_-]+/g, "");

  if (!rawKey) {
    return "";
  }

  if (compactKey.includes("mt4")) return "mt4";
  if (compactKey.includes("mt5")) return "mt5";
  if (compactKey.includes("financepro") || compactKey === "fp")
    return "financepro";

  return compactKey;
};

const getPlatformIcon = (account) => {
  const platformKey = normalizePlatformKey(account);
  return platformIconMap[platformKey] || fpIcon;
};

const getPlatformLinkConfig = (account) => {
  const platformKey = normalizePlatformKey(account);
  const config = platformDownloads.value?.[platformKey];

  if (!config || typeof config !== "object") {
    return null;
  }

  if (typeof config.url !== "string" || config.url.trim().length === 0) {
    return null;
  }

  return config;
};

const getPlatformActionTitle = (account) => {
  const config = getPlatformLinkConfig(account);
  return config?.download === false
    ? t("openPlatform", "Open Platform")
    : t("downloadPlatform", "Download Platform");
};

const handlePlatformAction = (account) => {
  const config = getPlatformLinkConfig(account);

  if (!config) {
    return;
  }

  const url = config.url.trim();

  showActionModal(config.download === false ? "open" : "download");

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

const formatDate = (value) => {
  if (!value) return "-";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString();
};

const formatStatus = (status) => {
  switch (status) {
    case "active":
      return t("active", "Active");
    case "pending":
      return t("pending", "Pending");
    case "closed":
      return t("closed", "Closed");
    default:
      return status;
  }
};

const formatGroupLabel = (account) => {
  return String(account?.groupLabel || account?.groupName || "-").trim() || "-";
};

const formatAccountCurrency = (account) => {
  return (
    String(account?.groupUnit || account?.accountCurrency || "USD")
      .trim()
      .toUpperCase() || "USD"
  );
};

const getAccountBalanceValue = (account) => {
  const candidate =
    account?.availableBalance ??
    account?.balance ??
    account?.Balance ??
    account?.accountBalance ??
    account?.currentBalance;

  if (candidate === null || candidate === undefined || candidate === "") {
    return null;
  }

  const numericValue =
    typeof candidate === "string" ? parseFloat(candidate) : candidate;
  return Number.isNaN(numericValue) ? null : numericValue;
};

const formatAccountBalance = (account) => {
  const balance = getAccountBalanceValue(account);
  return balance === null ? "-" : formatNumber(balance, 2);
};

// credit 直接取后端返回的 credit 字段（来自 tradingAccountExternalAccounts.platformCredit）
const formatAccountCredit = (account) => {
  const value = account?.credit;
  if (value === null || value === undefined || value === "") return "-";
  const numericValue = typeof value === "string" ? parseFloat(value) : value;
  return Number.isNaN(numericValue) ? "-" : formatNumber(numericValue, 2);
};

const loadAccounts = async () => {
  loading.value = true;
  error.value = "";
  try {
    const [accountsResponse, downloadsResponse] = await Promise.all([
      tradingAccountService.getAccounts(),
      tradingAccountService.getPlatformDownloads(),
    ]);

    if (accountsResponse.success && accountsResponse.data) {
      accounts.value = accountsResponse.data.accounts || [];
    } else {
      error.value = accountsResponse.message || "Failed to load accounts.";
    }

    if (
      downloadsResponse?.success &&
      downloadsResponse.data &&
      typeof downloadsResponse.data === "object"
    ) {
      platformDownloads.value = downloadsResponse.data;
    }
  } catch (err) {
    console.error("Failed to load trading accounts:", err);
    error.value =
      err.response?.data?.message || err.message || "Failed to load accounts.";
  } finally {
    loading.value = false;
  }
};

// ---- 行内操作下拉 ----
const menuAccount = ref(null);
const menuStyle = ref({});
const actionMenuRef = ref(null);

const toggleActionMenu = (event, account) => {
  if (menuAccount.value && menuAccount.value.id === account.id) {
    closeActionMenu();
    return;
  }
  const rect = event.currentTarget.getBoundingClientRect();
  menuStyle.value = { top: `${rect.bottom + 6}px`, left: `${rect.right}px` };
  menuAccount.value = account;
};

const closeActionMenu = () => {
  menuAccount.value = null;
};

const onActionMenuSelect = (action) => {
  const account = menuAccount.value;
  closeActionMenu();
  if (!account) return;
  if (action === "platform") handlePlatformAction(account);
  else if (action === "leverage") openLeverageModal(account);
  else if (action === "password") openPasswordModal(account);
  else if (action === "name") openNameModal(account);
};

// 菜单是悬浮定位，点别处/滚动时收起
const handleActionMenuOutside = (event) => {
  if (!menuAccount.value) return;
  if (actionMenuRef.value && actionMenuRef.value.contains(event.target)) return;
  closeActionMenu();
};
const handleActionMenuScroll = () => {
  if (menuAccount.value) closeActionMenu();
};

// ---- 修改杠杆 ----
const platformsCache = ref(null);
const leverageModal = ref({
  visible: false,
  account: null,
  leverageValue: "",
  options: [],
  loading: false,
  submitting: false,
  error: "",
  done: false,
});

const ensurePlatforms = async () => {
  if (platformsCache.value) return platformsCache.value;
  const res = await tradingAccountService.getPlatforms();
  platformsCache.value = res?.data?.platforms || [];
  return platformsCache.value;
};

const openLeverageModal = async (account) => {
  leverageModal.value = {
    visible: true,
    account,
    leverageValue: "",
    options: [],
    loading: true,
    submitting: false,
    error: "",
    done: false,
  };
  try {
    const platforms = await ensurePlatforms();
    const match =
      platforms.find((p) => Number(p.id) === Number(account.platformId)) ||
      platforms.find(
        (p) =>
          String(p.platformKey || "") === String(account.platformKey || ""),
      );
    leverageModal.value.options = Array.isArray(match?.leverages)
      ? match.leverages
      : [];
    // 预选当前杠杆
    const current = String(account.leverageValue || "");
    if (
      leverageModal.value.options.some(
        (o) => String(o.leverageValue) === current,
      )
    ) {
      leverageModal.value.leverageValue = current;
    }
  } catch (err) {
    leverageModal.value.error = t(
      "leverageLoadFailed",
      "Failed to load leverage options",
    );
  } finally {
    leverageModal.value.loading = false;
  }
};

const closeLeverageModal = () => {
  if (leverageModal.value.submitting) return;
  leverageModal.value.visible = false;
};

const submitLeverage = async () => {
  if (!leverageModal.value.leverageValue || leverageModal.value.submitting)
    return;
  leverageModal.value.submitting = true;
  leverageModal.value.error = "";
  try {
    const res = await tradingAccountService.changeLeverage({
      tradingAccountId: leverageModal.value.account?.id,
      leverageValue: leverageModal.value.leverageValue,
    });
    if (res?.success) {
      leverageModal.value.done = true;
      await loadAccounts();
    } else {
      leverageModal.value.error =
        res?.message || t("leverageChangeFailed", "Failed to change leverage");
    }
  } catch (err) {
    leverageModal.value.error =
      err.response?.data?.message ||
      err.message ||
      t("leverageChangeFailed", "Failed to change leverage");
  } finally {
    leverageModal.value.submitting = false;
  }
};

// ---- 修改交易密码（邮箱验证码 + 新密码 + 确认密码）----
const passwordModal = ref({
  visible: false,
  account: null,
  code: "",
  newPassword: "",
  confirmPassword: "",
  sendingCode: false,
  cooldown: 0,
  submitting: false,
  error: "",
  done: false,
});
let passwordCooldownTimer = null;

const canSubmitPassword = computed(() => {
  const m = passwordModal.value;
  return !!m.code.trim() && !!m.newPassword && !!m.confirmPassword;
});

const openPasswordModal = (account) => {
  passwordModal.value = {
    visible: true,
    account,
    code: "",
    newPassword: "",
    confirmPassword: "",
    sendingCode: false,
    cooldown: 0,
    submitting: false,
    error: "",
    done: false,
  };
};

const closePasswordModal = () => {
  if (passwordModal.value.submitting) return;
  passwordModal.value.visible = false;
  if (passwordCooldownTimer) {
    clearInterval(passwordCooldownTimer);
    passwordCooldownTimer = null;
  }
};

const sendPasswordCode = async () => {
  const email = String(userEmail.value || "").trim();
  if (!email) {
    passwordModal.value.error = t(
      "noEmailOnFile",
      "No email on file. Please contact support.",
    );
    return;
  }
  passwordModal.value.sendingCode = true;
  passwordModal.value.error = "";
  try {
    const res = await tradingAccountService.sendEmailCode(email);
    if (res?.success !== false) {
      // 60s 冷却，防止连点
      passwordModal.value.cooldown = 60;
      if (passwordCooldownTimer) clearInterval(passwordCooldownTimer);
      passwordCooldownTimer = setInterval(() => {
        if (passwordModal.value.cooldown > 0) {
          passwordModal.value.cooldown -= 1;
        } else {
          clearInterval(passwordCooldownTimer);
          passwordCooldownTimer = null;
        }
      }, 1000);
    } else {
      passwordModal.value.error =
        res?.message || t("sendCodeFailed", "Failed to send verification code");
    }
  } catch (err) {
    passwordModal.value.error =
      err.response?.data?.message ||
      err.message ||
      t("sendCodeFailed", "Failed to send verification code");
  } finally {
    passwordModal.value.sendingCode = false;
  }
};

const submitPassword = async () => {
  const m = passwordModal.value;
  if (m.submitting || !canSubmitPassword.value) return;
  if (m.newPassword !== m.confirmPassword) {
    m.error = t("passwordMismatch", "The two passwords do not match.");
    return;
  }
  m.submitting = true;
  m.error = "";
  try {
    const res = await tradingAccountService.changeTradingPassword({
      tradingAccountId: m.account?.id,
      code: m.code.trim(),
      newPassword: m.newPassword,
    });
    if (res?.success) {
      m.done = true;
    } else {
      m.error =
        res?.message || t("passwordChangeFailed", "Failed to change password");
    }
  } catch (err) {
    m.error =
      err.response?.data?.message ||
      err.message ||
      t("passwordChangeFailed", "Failed to change password");
  } finally {
    m.submitting = false;
  }
};

// ---- 修改账户名字（仅 CRM 显示）----
const nameModal = ref({
  visible: false,
  account: null,
  name: "",
  submitting: false,
  error: "",
  done: false,
});

const openNameModal = (account) => {
  nameModal.value = {
    visible: true,
    account,
    name: String(account.accountNickname || ""),
    submitting: false,
    error: "",
    done: false,
  };
};

const closeNameModal = () => {
  if (nameModal.value.submitting) return;
  nameModal.value.visible = false;
};

const submitName = async () => {
  const m = nameModal.value;
  if (m.submitting || !m.name.trim()) return;
  m.submitting = true;
  m.error = "";
  try {
    const res = await tradingAccountService.changeAccountName({
      tradingAccountId: m.account?.id,
      name: m.name.trim(),
    });
    if (res?.success) {
      m.done = true;
      await loadAccounts();
    } else {
      m.error =
        res?.message || t("nameChangeFailed", "Failed to change account name");
    }
  } catch (err) {
    m.error =
      err.response?.data?.message ||
      err.message ||
      t("nameChangeFailed", "Failed to change account name");
  } finally {
    m.submitting = false;
  }
};

onMounted(() => {
  loadAccounts();
  document.addEventListener("click", handleActionMenuOutside);
  window.addEventListener("scroll", handleActionMenuScroll, true);
});

onUnmounted(() => {
  if (actionModalTimer) {
    clearTimeout(actionModalTimer);
  }
  if (actionModalCountdownTimer) {
    clearInterval(actionModalCountdownTimer);
  }
  if (passwordCooldownTimer) {
    clearInterval(passwordCooldownTimer);
  }
  document.removeEventListener("click", handleActionMenuOutside);
  window.removeEventListener("scroll", handleActionMenuScroll, true);
});
</script>

<style scoped>
.accounts-page {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #ffffff;
  border-radius: var(--radius-xl);
  padding: 24px;
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
}

.page-title h2 {
  font-size: 24px;
  color: var(--color-ink);
  margin-bottom: 6px;
}

.page-title h2 i {
  margin-right: 10px;
  color: var(--color-brand);
}

.page-title p {
  color: var(--color-muted);
  font-size: 14px;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 20px;
  border-radius: var(--radius-md);
  border: none;
  font-weight: 600;
  cursor: pointer;
  transition:
    color 0.2s ease,
    background-color 0.2s ease,
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    opacity 0.2s ease,
    transform 0.2s ease;
  text-decoration: none;
}

.btn-primary {
  background: var(--color-brand);
  color: #ffffff;
  box-shadow: 0 6px 18px rgba(var(--color-brand-rgb), 0.35);
}

.btn-primary:hover {
  transform: translateY(-1px);
  box-shadow: 0 10px 24px rgba(var(--color-brand-rgb), 0.45);
}

.btn-secondary {
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.state-card {
  background: #ffffff;
  border-radius: var(--radius-xl);
  padding: 40px 24px;
  text-align: center;
  color: var(--color-text);
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
}

.state-card i {
  font-size: 32px;
  color: var(--color-brand);
}

.state-card.error i {
  color: var(--color-danger);
}

.state-card.empty i {
  color: var(--color-brand);
}

.accounts-table-wrapper {
  background: #ffffff;
  border-radius: var(--radius-xl);
  box-shadow: 0 6px 16px rgba(15, 23, 42, 0.08);
  overflow: hidden;
}

.accounts-table {
  width: 100%;
  border-collapse: collapse;
}

.accounts-table th,
.accounts-table td {
  padding: 18px 20px;
  text-align: left;
  font-size: 14px;
  color: var(--color-ink);
}

.accounts-table thead {
  background: var(--color-surface-soft);
}

.platform-action-column,
.platform-action-cell {
  width: 72px;
  text-align: center;
}

.accounts-table tbody tr:hover {
  background: var(--color-surface-soft);
}

.account-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.account-number {
  font-weight: 700;
  font-size: 15px;
  color: var(--color-ink-strong);
}

.account-nickname {
  font-size: 13px;
  color: var(--color-muted);
}

.platform-pill {
  display: inline-flex;
  align-items: center;
  padding: 6px 12px;
  border-radius: 999px;
  background: var(--color-brand-soft);
  color: var(--color-brand);
  font-weight: 600;
  font-size: 13px;
}

.platform-icon-button {
  width: 40px;
  height: 40px;
  padding: 0;
  border: none;
  background: transparent;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  border-radius: var(--radius-md);
  transition:
    background 0.2s ease,
    transform 0.2s ease;
}

.platform-icon-button:hover {
  background: #f1f5f9;
  transform: translateY(-1px);
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

.platform-action-icon {
  width: 28px;
  height: 28px;
  object-fit: contain;
}

.status-pill {
  display: inline-flex;
  align-items: center;
  padding: 6px 12px;
  border-radius: 999px;
  font-weight: 600;
  font-size: 12px;
  text-transform: capitalize;
}

.status-pill.active {
  background: rgba(72, 187, 120, 0.15);
  color: var(--color-success);
}

.status-pill.pending {
  background: rgba(251, 191, 36, 0.18);
  color: #b7791f;
}

.status-pill.closed {
  background: rgba(245, 101, 101, 0.15);
  color: var(--color-danger);
}

@media (max-width: 960px) {
  .accounts-table-wrapper {
    overflow-x: auto;
  }

  .accounts-table {
    min-width: 760px;
  }
}
.account-action-button {
  width: 40px;
  height: 40px;
  padding: 0;
  border: none;
  background: transparent;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  border-radius: var(--radius-md);
  color: var(--color-brand);
  font-size: 16px;
  transition: background 0.2s ease;
}
.account-action-button:hover,
.account-action-button.active {
  background: rgba(var(--color-brand-rgb), 0.12);
}

.account-action-dropdown {
  position: fixed;
  transform: translateX(-100%);
  min-width: 200px;
  background: #fff;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: 0 12px 32px rgba(15, 23, 42, 0.16);
  padding: 6px;
  z-index: 2200;
}
.account-action-item {
  width: 100%;
  background: none;
  border: none;
  border-radius: var(--radius-md);
  padding: 10px 12px;
  font-size: 14px;
  color: var(--color-ink);
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 10px;
  text-align: left;
}
.account-action-item:hover {
  background: #f1f5f9;
  color: var(--color-brand);
}
.account-action-item i {
  width: 16px;
  text-align: center;
  color: #94a3b8;
}
.account-action-item:hover i {
  color: var(--color-brand);
}

.form-modal-card {
  background: #fff;
  border-radius: 18px;
  padding: 32px;
  width: 520px;
  max-width: 92vw;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
}
.form-modal-card h3 {
  margin: 0 0 8px;
  font-size: 22px;
  color: var(--color-ink-strong);
}
.form-modal-sub {
  margin: 0 0 22px;
  font-size: 14px;
  color: var(--color-muted);
}
.form-modal-field {
  margin-bottom: 20px;
}
.form-modal-field label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text);
  margin-bottom: 8px;
}
.form-modal-field select,
.form-modal-field input {
  width: 100%;
  padding: 12px 14px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 15px;
  background: #fff;
  box-sizing: border-box;
}
.form-modal-field label {
  font-size: 14px;
}
.form-modal-current {
  margin: 0 0 14px;
  font-size: 13px;
  color: var(--color-muted);
}
.form-modal-success {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 4px 0 18px;
  font-size: 14px;
  color: var(--color-success);
}
.form-modal-success i {
  font-size: 18px;
}
.form-modal-code-row {
  display: flex;
  gap: 8px;
}
.form-modal-code-row input {
  flex: 1;
}
.form-modal-code-btn {
  white-space: nowrap;
  min-width: 96px;
  justify-content: center;
}
.form-modal-error {
  color: var(--color-danger);
  font-size: 13px;
  margin: 0 0 12px;
}
.form-modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}
.form-btn-primary,
.form-btn-secondary {
  padding: 9px 18px;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.form-btn-primary {
  background: var(--color-brand);
  color: #fff;
}
.form-btn-primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.form-btn-secondary {
  background: var(--color-surface-muted);
  color: var(--color-ink);
}
.form-btn-secondary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
