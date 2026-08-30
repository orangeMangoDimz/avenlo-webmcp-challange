<template>
  <div class="utrada-login-page">
    <!-- Header: Logo + Language（与登录页一致） -->
    <header class="login-header">
      <div class="header-logo">
        <img
          src="@/assets/utradaimg/logo1.png"
          alt="Avenlo"
          class="header-logo-img"
        />
      </div>
      <button
        type="button"
        class="language-switcher"
        :aria-expanded="showLanguageDropdown"
        @click.stop="toggleLanguageDropdown"
      >
        <ClientLangFlagIcon :language-code="languageStore.currentLanguage" />
        <span class="language-text">{{ currentLanguageName }}</span>
      </button>
      <div class="language-dropdown" :class="{ active: showLanguageDropdown }">
        <button
          v-for="lang in enabledLanguages"
          :key="lang.languageCode"
          type="button"
          class="language-option"
          :class="{
            active: languageStore.currentLanguage === lang.languageCode,
          }"
          @click="changeLanguage(lang.languageCode)"
        >
          <ClientLangFlagIcon :language-code="lang.languageCode" />
          <span>{{ lang.languageName }}</span>
        </button>
      </div>
    </header>

    <div
      class="login-body"
      :key="
        'lang-' +
        languageStore.currentLanguage +
        '-' +
        translationVersion +
        '-' +
        languageSwitchKey
      "
    >
      <!-- Left: 与登录页相同的左侧内容 -->
      <div class="login-left">
        <div class="left-content">
          <div class="features-row features-row-1">
            <div class="feature-item">
              <span class="feature-icon">
                <i class="fas fa-check" aria-hidden="true"></i>
              </span>
              <span class="feature-text">{{
                t("featureFastExecution", "Fast and reliable order execution")
              }}</span>
            </div>
            <div class="feature-item">
              <span class="feature-icon">
                <i class="fas fa-check" aria-hidden="true"></i>
              </span>
              <span class="feature-text">{{
                t(
                  "featureCompetitivePricing",
                  "Competitive pricing on global markets",
                )
              }}</span>
            </div>
            <div class="feature-item">
              <span class="feature-icon">
                <i class="fas fa-check" aria-hidden="true"></i>
              </span>
              <span class="feature-text">{{
                t("featureFunding", "Easy and secure funding solutions")
              }}</span>
            </div>
          </div>
          <h1 class="journey-title">
            <span class="line1">{{
              t("yourTradingJourney", "Your Trading Journey")
            }}</span>
            <span class="line2">{{
              t("startsWithUtrada", "Starts with Avenlo")
            }}</span>
          </h1>
          <div class="features-row features-row-2">
            <div class="feature-item">
              <span class="feature-icon">
                <i class="fas fa-check" aria-hidden="true"></i>
              </span>
              <span class="feature-text">{{
                t("featureRegulated", "Fully regulated and authorized broker")
              }}</span>
            </div>
            <div class="feature-item">
              <span class="feature-icon">
                <i class="fas fa-check" aria-hidden="true"></i>
              </span>
              <span class="feature-text">{{
                t("featureSocialTrading", "One-stop social trading platform")
              }}</span>
            </div>
            <div class="feature-item">
              <span class="feature-icon">
                <i class="fas fa-check" aria-hidden="true"></i>
              </span>
              <span class="feature-text">{{
                t("featureAcademy", "Powerful academy and market research")
              }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Right: 仅显示 Reset Password 表单（与登录页右侧同风格） -->
      <div class="login-right">
        <div class="form-panel">
          <!-- 无 token：无效链接 -->
          <div v-if="!resetToken" class="reset-status-block">
            <h2 class="form-title">
              {{ t("invalidLink", "Invalid Reset Link") }}
            </h2>
            <p class="form-subtitle">
              {{
                t(
                  "noTokenFound",
                  "No reset token found in the URL. Please use the link from your email.",
                )
              }}
            </p>
            <a href="#" class="back-to-login" @click.prevent="goToLogin">{{
              t("backToLogin", "Back to Sign In")
            }}</a>
          </div>

          <!-- 提交中 -->
          <div v-else-if="submitting" class="reset-status-block">
            <div class="spinner"></div>
            <h2 class="form-title">
              {{ t("resettingPassword", "Resetting Your Password...") }}
            </h2>
            <p class="form-subtitle">{{ t("pleaseWait", "Please wait.") }}</p>
          </div>

          <!-- 成功 -->
          <div v-else-if="success" class="reset-status-block success">
            <div class="success-icon"><i class="fas fa-check-circle"></i></div>
            <h2 class="form-title">
              {{ t("passwordResetSuccess", "Password Reset Successfully") }}
            </h2>
            <p class="form-subtitle">
              {{
                t(
                  "passwordResetSuccessMessage",
                  "Your password has been reset. Redirecting to login...",
                )
              }}
            </p>
          </div>

          <!-- API 错误（如 token 过期） -->
          <div v-else-if="error" class="reset-status-block error">
            <div class="error-icon"><i class="fas fa-times-circle"></i></div>
            <h2 class="form-title">{{ t("resetFailed", "Reset Failed") }}</h2>
            <p class="form-subtitle error-text">{{ errorMessage }}</p>
            <a href="#" class="back-to-login" @click.prevent="goToLogin">{{
              t("backToLogin", "Back to Sign In")
            }}</a>
          </div>

          <!-- 有 token：显示重置密码表单 -->
          <div v-else class="reset-form">
            <h2 class="form-title">
              {{ t("resetPassword", "Reset Password") }}
            </h2>

            <form @submit.prevent="handleResetPassword" class="login-form">
              <div class="form-group" :class="{ error: !!errors.password }">
                <label for="password">{{
                  t("newPassword", "New Password")
                }}</label>
                <div class="password-input-wrapper">
                  <input
                    :type="showPassword ? 'text' : 'password'"
                    id="password"
                    v-model="form.password"
                    :placeholder="
                      t('enterNewPasswordPlaceholder', 'Enter new password')
                    "
                    autocomplete="new-password"
                    @input="errors.password = ''"
                  />
                  <button
                    type="button"
                    class="password-toggle"
                    @click="showPassword = !showPassword"
                    :aria-label="
                      showPassword
                        ? t('hidePassword', 'Hide password')
                        : t('showPassword', 'Show password')
                    "
                  >
                    <i
                      :class="showPassword ? 'fas fa-eye-slash' : 'fas fa-eye'"
                    ></i>
                  </button>
                </div>
                <small
                  v-if="passwordStrengthSettings.description"
                  class="password-hint"
                  >{{ passwordRequirementText }}</small
                >
                <span v-if="errors.password" class="field-error">{{
                  errors.password
                }}</span>
              </div>

              <div
                class="form-group"
                :class="{ error: !!errors.confirmPassword }"
              >
                <label for="confirmPassword">{{
                  t("confirmPassword", "Confirm Password")
                }}</label>
                <div class="password-input-wrapper">
                  <input
                    :type="showConfirmPassword ? 'text' : 'password'"
                    id="confirmPassword"
                    v-model="form.confirmPassword"
                    :placeholder="
                      t('confirmNewPassword', 'Confirm new password')
                    "
                    autocomplete="new-password"
                    @input="errors.confirmPassword = ''"
                  />
                  <button
                    type="button"
                    class="password-toggle"
                    @click="showConfirmPassword = !showConfirmPassword"
                    :aria-label="
                      showConfirmPassword
                        ? t('hidePassword', 'Hide password')
                        : t('showPassword', 'Show password')
                    "
                  >
                    <i
                      :class="
                        showConfirmPassword ? 'fas fa-eye-slash' : 'fas fa-eye'
                      "
                    ></i>
                  </button>
                </div>
                <span v-if="errors.confirmPassword" class="field-error">{{
                  errors.confirmPassword
                }}</span>
              </div>

              <div v-if="formError" class="general-error">{{ formError }}</div>

              <button
                type="submit"
                class="btn-primary"
                :disabled="submitting || !isFormValid"
              >
                {{
                  submitting
                    ? t("resetting", "Resetting...")
                    : t("resetPassword", "Reset Password")
                }}
              </button>
            </form>

            <div class="back-row">
              <a href="#" class="back-to-login" @click.prevent="goToLogin">{{
                t("backToLogin", "Back to Sign In")
              }}</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useClientAuthStore } from "@/stores/clientAuth";
import { useLanguageStore } from "@/stores/language";
import brandingApi from "@/services/brandingApi";
import loginSettingsService from "@/services/loginSettingsService";
import ClientLangFlagIcon from "@/components/layout/ClientLangFlagIcon.vue";

const route = useRoute();
const router = useRouter();
const clientAuthStore = useClientAuthStore();
const languageStore = useLanguageStore();

const submitting = ref(false);
const success = ref(false);
const error = ref(false);
const errorMessage = ref("");
const formError = ref("");
const showLanguageDropdown = ref(false);

const form = ref({ password: "", confirmPassword: "" });
const errors = ref({ password: "", confirmPassword: "" });
const languageSwitchKey = ref(0);
const showPassword = ref(false);
const showConfirmPassword = ref(false);

const branding = ref({ logoText: "CRM", copyrightText: "Trading Platform" });
const passwordStrengthSettings = ref({
  strengthLevel: "medium",
  minLength: 8,
  requireLetters: true,
  requireNumbers: true,
  requireUppercase: false,
  requireLowercase: false,
  requireSpecialChars: false,
  description: "Minimum 8 characters with letters and numbers",
});

const normalizePasswordStrengthSettings = (settings = {}) => ({
  strengthLevel: settings.strengthLevel || "medium",
  minLength: Number(settings.minLength || 8),
  requireLetters: Boolean(settings.requireLetters),
  requireNumbers: Boolean(settings.requireNumbers),
  requireUppercase: Boolean(settings.requireUppercase),
  requireLowercase: Boolean(settings.requireLowercase),
  requireSpecialChars: Boolean(settings.requireSpecialChars),
  description: settings.description || "",
});

const validatePasswordAgainstSettings = (password, settings) => {
  const normalizedPassword = String(password || "");
  const errors = [];

  if (normalizedPassword.length < settings.minLength) {
    errors.push(`Password must be at least ${settings.minLength} characters`);
  }

  if (settings.requireLetters && !/[A-Za-z]/.test(normalizedPassword)) {
    errors.push("Password must include letters");
  }

  if (settings.requireNumbers && !/\d/.test(normalizedPassword)) {
    errors.push("Password must include numbers");
  }

  if (settings.requireUppercase && !/[A-Z]/.test(normalizedPassword)) {
    errors.push("Password must include uppercase letters");
  }

  if (settings.requireLowercase && !/[a-z]/.test(normalizedPassword)) {
    errors.push("Password must include lowercase letters");
  }

  if (
    settings.requireSpecialChars &&
    !/[^A-Za-z0-9]/.test(normalizedPassword)
  ) {
    errors.push("Password must include special characters");
  }

  return {
    valid: errors.length === 0,
    errors,
  };
};

const t = (key, fallback = "") => languageStore.t(key, fallback);
const resetToken = computed(() => route.query.token);

const currentLanguageName = computed(() => languageStore.currentLanguageName);
const enabledLanguages = computed(() => languageStore.enabledLanguages);
const translationVersion = computed(
  () => Object.keys(languageStore.translations || {}).length,
);

const passwordRequirementText = computed(() => {
  const s = passwordStrengthSettings.value;
  const parts = [];
  parts.push(
    `${t("minimum", "Minimum")} ${s.minLength} ${t("characters", "characters")}`,
  );
  if (s.requireLetters) parts.push(t("letters", "letters"));
  if (s.requireNumbers) parts.push(t("numbers", "numbers"));
  if (s.requireUppercase && s.requireLowercase)
    parts.push(t("uppercaseAndLowercase", "uppercase and lowercase"));
  else if (s.requireUppercase)
    parts.push(t("uppercaseLetters", "uppercase letters"));
  else if (s.requireLowercase)
    parts.push(t("lowercaseLetters", "lowercase letters"));
  if (s.requireSpecialChars)
    parts.push(t("specialCharacters", "special characters"));
  if (parts.length <= 1) return parts[0] || "";
  const last = parts.pop();
  return parts.join(", ") + " " + t("and", "and") + " " + last;
});

const isFormValid = computed(() => {
  const validation = validatePasswordAgainstSettings(
    form.value.password,
    passwordStrengthSettings.value,
  );

  return (
    form.value.password &&
    form.value.confirmPassword &&
    form.value.password === form.value.confirmPassword &&
    validation.valid
  );
});

function toggleLanguageDropdown() {
  showLanguageDropdown.value = !showLanguageDropdown.value;
}

async function changeLanguage(langCode) {
  showLanguageDropdown.value = false;
  await languageStore.changeLanguage(langCode);
  formError.value = "";
  errors.value = { password: "", confirmPassword: "" };
  error.value = false;
  errorMessage.value = "";
  languageSwitchKey.value += 1;
}

async function handleResetPassword() {
  formError.value = "";
  errors.value = { password: "", confirmPassword: "" };
  error.value = false;
  errorMessage.value = "";

  if (!form.value.password) {
    errors.value.password = t(
      "pleaseFillAllFields",
      "Please fill in all fields",
    );
    return;
  }
  if (!form.value.confirmPassword) {
    errors.value.confirmPassword = t(
      "pleaseFillAllFields",
      "Please fill in all fields",
    );
    return;
  }
  if (form.value.password !== form.value.confirmPassword) {
    errors.value.confirmPassword = t(
      "passwordsDoNotMatch",
      "Passwords do not match",
    );
    return;
  }

  const passwordValidation = validatePasswordAgainstSettings(
    form.value.password,
    passwordStrengthSettings.value,
  );

  if (!passwordValidation.valid) {
    errors.value.password = passwordValidation.errors[0];
    return;
  }

  submitting.value = true;
  try {
    const result = await clientAuthStore.resetPassword({
      token: resetToken.value,
      password: form.value.password,
      confirmPassword: form.value.confirmPassword,
    });
    if (result.success) {
      success.value = true;
      setTimeout(() => {
        router.push({
          name: "client-login",
          query: { message: "password_reset_success" },
        });
      }, 2500);
    } else {
      error.value = true;
      errorMessage.value =
        result.error ||
        t("resetError", "Password reset failed. Please try again.");
    }
  } catch (err) {
    error.value = true;
    errorMessage.value =
      err.message ||
      err.error ||
      t("unexpectedError", "An unexpected error occurred. Please try again.");
  } finally {
    submitting.value = false;
  }
}

function goToLogin() {
  router.push({ name: "client-login" });
}

async function loadPasswordStrengthSettings() {
  try {
    const response = await loginSettingsService.getPasswordStrength();
    if (response.success && response.data) {
      passwordStrengthSettings.value = normalizePasswordStrengthSettings(
        response.data,
      );
    }
  } catch (err) {
    console.error("Failed to load password strength settings:", err);
    // 保持默认值
  }
}

onMounted(async () => {
  // 不依赖语言的请求先并发发出，避免串在语言初始化后面
  const pageData = Promise.all([
    brandingApi.getBranding().then(
      (config) => {
        branding.value = {
          logoText: config.logoText || "CRM",
          copyrightText: config.copyrightText || "Trading Platform",
        };
      },
      () => {},
    ),
    loadPasswordStrengthSettings(),
  ]);

  await languageStore.initLanguage();
  await pageData;
});
</script>

<style scoped>
/* 与登录页一致的页面与左侧样式 */
.utrada-login-page {
  min-height: 100vh;
  background: var(--color-sidebar);
  display: flex;
  flex-direction: column;
  width: 100%;
  font-family: "Work Sans", sans-serif;
}

.login-header {
  height: 64px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 132px;
  flex-shrink: 0;
  background: var(--color-sidebar);
  position: relative;
}

.header-logo-img {
  height: 32px;
  width: auto;
  max-width: 180px;
  object-fit: contain;
  display: block;
}

.language-switcher {
  border: 0;
  background: transparent;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 4px 0;
  cursor: pointer;
  color: rgba(255, 255, 255, 0.9);
  font-size: 14px;
}

.lang-icon-img {
  width: 20px;
  height: 20px;
  display: inline-block;
  vertical-align: middle;
}

.language-dropdown {
  position: absolute;
  top: 68px;
  right: 132px;
  background: var(--color-surface);
  border-radius: var(--radius-md);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
  overflow: hidden;
  display: none;
  min-width: 160px;
  z-index: 100;
}

.language-dropdown.active {
  display: block;
}

.language-option {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  border: 0;
  background: transparent;
  text-align: left;
  padding: 12px 16px;
  cursor: pointer;
  color: var(--color-text);
  font-size: 14px;
}

.language-option:hover {
  background: var(--color-surface-soft);
}

.language-option.active {
  background: var(--color-brand-soft);
  color: var(--color-brand);
  font-weight: 600;
}

.login-body {
  flex: 1;
  display: flex;
  min-height: calc(100vh - 64px);
  padding: 0 132px 24px;
  gap: 62px;
  align-items: stretch;
  max-width: 1440px;
  margin: 0 auto;
  width: 100%;
  box-sizing: border-box;
}

.login-left {
  width: 580px;
  flex-shrink: 0;
  padding: 64px 0 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  overflow: hidden;
  background-image: none;
  background-repeat: no-repeat;
  background-position: center center;
  background-size: cover;
  background-color: var(--color-sidebar);
}

.login-left::before {
  content: "";
  position: absolute;
  inset: 0;
  background: radial-gradient(
    ellipse 80% 200% at 70% 50%,
    rgba(var(--color-brand-rgb), 0.24) 0%,
    var(--color-sidebar) 70%
  );
  pointer-events: none;
  z-index: 0;
}

.login-left::after {
  content: "";
  position: absolute;
  inset: 0;
  pointer-events: none;
  z-index: 1;
  background:
    linear-gradient(
      90deg,
      var(--color-sidebar) 0%,
      transparent 20%,
      transparent 80%,
      var(--color-sidebar) 100%
    ),
    linear-gradient(
      0deg,
      var(--color-sidebar) 0%,
      transparent 18%,
      transparent 82%,
      var(--color-sidebar) 100%
    );
}

.left-content {
  position: relative;
  z-index: 2;
  width: 100%;
  max-width: 464px;
}

.features-row {
  display: flex;
  gap: 0;
  justify-content: space-between;
  margin-bottom: 0;
}

.features-row-1 {
  margin-bottom: 32px;
}
.features-row-2 {
  margin-top: 32px;
}

.feature-item {
  width: 154px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}

.feature-icon {
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 8px;
}

.feature-icon-img {
  width: 28px;
  height: 28px;
  display: inline-block;
  vertical-align: middle;
}

.feature-text {
  font-size: 15px;
  line-height: 1.35;
  color: var(--color-accent-soft);
}

.journey-title {
  font-size: 32px;
  font-weight: 700;
  line-height: 2;
  text-align: center;
  margin: 0;
  padding: 28px 36px;
  position: relative;
  border-radius: 84px;
  overflow: hidden;
}

.journey-title::before {
  content: "";
  position: absolute;
  inset: 0;
  background: var(--color-sidebar);
  border-radius: 84px;
  filter: blur(4px);
  z-index: -1;
}

.journey-title .line1,
.journey-title .line2 {
  display: block;
  position: relative;
  z-index: 1;
}

.journey-title .line1 {
  color: #fff;
}

.journey-title .line2 {
  background: linear-gradient(
    270deg,
    var(--color-accent-soft) 0%,
    var(--color-accent) 100%
  );
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
  -webkit-text-fill-color: transparent;
}

/* 右侧面板（与登录页一致） */
.login-right {
  width: 530px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 64px 0 48px;
}

.form-panel {
  width: 100%;
  padding: 24px;
  box-sizing: border-box;
  background: var(--color-surface);
  border-radius: var(--radius-xl);
}

.form-title {
  color: var(--color-ink);
  margin: 0 0 8px 0;
  line-height: 1.2;
  text-align: center;
  font-family: "Work Sans", sans-serif;
  font-weight: 600;
  font-size: 24px;
}

.form-subtitle {
  text-align: center;
  color: var(--color-muted);
  font-size: 15px;
  margin-bottom: 24px;
  line-height: 1.4;
}

/* 状态块：无 token / 提交中 / 成功 / 错误 */
.reset-status-block {
  text-align: center;
  padding: 24px 0;
}

.reset-status-block .success-icon,
.reset-status-block .error-icon {
  font-size: 48px;
  margin-bottom: 16px;
}

.reset-status-block.success .success-icon {
  color: var(--color-success);
}
.reset-status-block.error .error-icon {
  color: var(--color-danger);
}
.reset-status-block .error-text {
  color: var(--color-danger);
}

.spinner {
  width: 40px;
  height: 40px;
  margin: 0 auto 16px;
  border: 1px solid var(--color-border);
  border-top-color: var(--color-brand);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.back-to-login {
  display: inline-block;
  margin-top: 16px;
  font-size: 14px;
  font-weight: 500;
  color: var(--color-brand);
  text-decoration: none;
}

.back-to-login:hover {
  text-decoration: underline;
}

/* 表单（与登录页表单项一致） */
.reset-form {
}
.login-form {
  display: flex;
  flex-direction: column;
}

.form-group {
  margin-bottom: 24px;
}

.form-group label {
  display: block;
  font-size: 16px;
  font-weight: 500;
  color: var(--color-muted);
  margin-bottom: 8px;
}

.form-group input {
  width: 100%;
  height: 37px;
  padding: 8px 16px;
  padding-right: 44px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: 15px;
  font-weight: 500;
  color: var(--color-ink);
  background: var(--color-surface);
  box-sizing: border-box;
  transition:
    border-color 0.2s,
    box-shadow 0.2s;
}

.form-group input::placeholder {
  color: var(--color-faint);
}

.form-group input:focus {
  border-color: var(--color-brand);
  box-shadow: none;
}

.form-group.error input {
  border-color: var(--color-danger);
}

.form-group.error input:focus {
  box-shadow: none;
}

.password-input-wrapper {
  position: relative;
  width: 100%;
}

.password-toggle {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  cursor: pointer;
  padding: 6px;
  color: var(--color-muted);
  font-size: 16px;
}

.password-toggle:hover {
  color: var(--color-brand);
}

.password-hint {
  display: block;
  margin-top: 6px;
  font-size: 13px;
  color: var(--color-muted);
}

.field-error {
  display: block;
  margin-top: 6px;
  font-size: 13px;
  color: var(--color-danger);
}

.general-error {
  margin-top: 12px;
  padding: 10px 14px;
  background: var(--color-danger-soft);
  border: 1px solid var(--color-danger-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  color: var(--color-danger);
  margin-bottom: 8px;
}

.btn-primary {
  width: 100%;
  height: 48px;
  background: linear-gradient(
    90deg,
    var(--color-brand-solid) 0%,
    var(--color-accent) 100%
  );
  border: 1.1px solid var(--color-brand);
  border-radius: 32px;
  color: #fff;
  border: none;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
  margin-top: 8px;
}

.btn-primary:hover:not(:disabled) {
  background: linear-gradient(
    90deg,
    var(--color-brand-solid) 0%,
    var(--color-accent) 100%
  );
  border: 1.1px solid var(--color-brand);
  border-radius: 32px;
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.back-row {
  text-align: center;
  margin-top: 24px;
}

@media (max-width: 1200px) {
  .login-header {
    padding: 0 24px;
  }
  .language-dropdown {
    right: 24px;
  }
  .login-body {
    padding: 0 24px;
    gap: 32px;
  }
  .login-left {
    max-width: 400px;
  }
  .feature-item {
    width: 120px;
  }
  .feature-text {
    font-size: 13px;
  }
  .journey-title {
    font-size: 28px;
  }
  .login-right {
    max-width: 530px;
  }
}

@media (max-width: 768px) {
  .login-body {
    flex-direction: column;
    padding: 0 16px;
    gap: 0;
  }
  .login-left {
    width: 100%;
    max-width: none;
    min-height: 280px;
    padding: 32px 0 24px;
  }
  .features-row {
    flex-wrap: wrap;
    justify-content: center;
    gap: 16px;
  }
  .feature-item {
    width: auto;
  }
  .login-right {
    width: 100%;
    max-width: none;
    padding: 32px 0 48px;
  }
  .form-panel {
    padding: 20px 16px;
  }
}

.utrada-login-page {
  background: var(--color-canvas);
  color: var(--color-text);
}

.login-header {
  height: 76px;
  padding: 0 clamp(24px, 5vw, 72px);
  background: var(--color-sidebar);
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.header-logo-img {
  height: 30px;
}

.language-switcher {
  min-height: 38px;
  padding: 8px 11px;
  border: 1px solid rgba(255, 255, 255, 0.14);
  border-radius: var(--radius-md);
}

.language-dropdown {
  top: 66px;
  right: clamp(24px, 5vw, 72px);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-md);
}

.login-body {
  min-height: calc(100vh - 76px);
  max-width: 1480px;
  padding: 28px clamp(24px, 4vw, 64px) 48px;
  gap: 28px;
  align-items: stretch;
}

.login-left {
  width: auto;
  flex: 1 1 0;
  min-width: 0;
  padding: 48px;
  background-color: var(--color-sidebar);
  background-image: none;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 24px;
  box-shadow: var(--shadow-md);
}

.login-left::before {
  background:
    radial-gradient(
      circle at 22% 18%,
      rgba(185, 141, 63, 0.24),
      transparent 26%
    ),
    radial-gradient(
      circle at 78% 72%,
      rgba(var(--color-brand-rgb), 0.7),
      transparent 36%
    );
}

.login-left::after {
  background:
    linear-gradient(
      120deg,
      transparent 0 48%,
      rgba(255, 255, 255, 0.035) 48% 49%,
      transparent 49% 100%
    ),
    linear-gradient(
      25deg,
      transparent 0 62%,
      rgba(216, 188, 131, 0.1) 62% 63%,
      transparent 63% 100%
    );
}

.left-content {
  max-width: 560px;
}

.features-row {
  gap: 18px;
}

.feature-item {
  width: auto;
  flex: 1 1 0;
  align-items: flex-start;
  text-align: left;
}

.feature-text {
  color: rgba(255, 255, 255, 0.7);
  font-size: 13px;
}

.feature-icon-img {
  opacity: 0.82;
  filter: grayscale(1) sepia(1) saturate(1.2) hue-rotate(350deg)
    brightness(1.18);
}

.journey-title {
  padding: 42px 0;
  border-radius: 0;
  font-family: var(--font-display);
  font-size: clamp(38px, 4.2vw, 64px);
  font-weight: 500;
  line-height: 1.05;
  letter-spacing: -0.04em;
  text-align: left;
}

.journey-title::before {
  display: none;
}

.journey-title .line2 {
  margin-top: 8px;
  background: none;
  color: var(--color-warning);
  -webkit-text-fill-color: currentColor;
}

.login-right {
  width: 520px;
  padding: 0;
  justify-content: center;
}

.form-panel {
  padding: 42px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-xl);
  box-shadow: var(--shadow-lg);
}

.form-title {
  font-family: var(--font-display);
  font-size: 32px;
  line-height: 1.15;
  font-weight: 600;
  letter-spacing: -0.025em;
  color: var(--color-ink);
}

.form-group input {
  min-height: 48px;
  border-width: 1px;
  border-color: var(--color-border);
  border-radius: var(--radius-md);
}

.btn-primary {
  background: var(--color-brand-solid);
  border: 1px solid var(--color-brand);
  border-radius: var(--radius-md);
  box-shadow: none;
}

.btn-primary:hover:not(:disabled) {
  background: var(--color-brand-strong);
  border-color: var(--color-brand-strong);
  border-radius: var(--radius-md);
}

@media (max-width: 1100px) {
  .login-body {
    padding: 24px;
    gap: 20px;
  }

  .login-left {
    max-width: none;
    padding: 36px;
  }

  .login-right {
    width: min(50%, 520px);
  }
}

@media (max-width: 768px) {
  .login-header {
    height: 68px;
    padding: 0 16px;
  }

  .language-dropdown {
    top: 60px;
    right: 16px;
  }

  .login-body {
    min-height: calc(100vh - 68px);
    padding: 16px;
  }

  .login-left {
    display: none;
  }

  .login-right {
    width: 100%;
    padding: 0;
  }

  .form-panel {
    padding: 28px 22px;
    border-radius: var(--radius-lg);
  }
}
</style>
