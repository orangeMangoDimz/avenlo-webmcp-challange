<template>
  <div class="reset-password-page workspace-access-page">
    <div class="admin-reset-lang">
      <AdminLanguageSwitcher />
    </div>
    <div class="admin-reset-container">
      <div class="admin-header">
        <div class="admin-logo">
          <div class="admin-logo-text">{{ branding.logoText }}</div>
        </div>
        <p>{{ t("admin_reset_header") }}</p>
      </div>

      <!-- Loading State (Submitting) -->
      <div v-if="submitting" class="reset-status">
        <div class="spinner"></div>
        <h2>{{ t("admin_reset_submittingTitle") }}</h2>
        <p>{{ t("admin_reset_submittingDesc") }}</p>
      </div>

      <!-- Success State -->
      <div v-else-if="success" class="reset-status success">
        <div class="success-icon">
          <i class="fas fa-check-circle"></i>
        </div>
        <h2>{{ t("admin_reset_successTitle") }}</h2>
        <p>{{ t("admin_reset_successDesc") }}</p>
        <div class="redirect-info">
          <i class="fas fa-spinner fa-spin"></i>
          {{ t("admin_reset_redirecting") }}
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="reset-status error">
        <div class="error-icon">
          <i class="fas fa-times-circle"></i>
        </div>
        <h2>{{ t("admin_reset_failedTitle") }}</h2>
        <p class="error-message">{{ errorMessage }}</p>

        <div class="error-actions">
          <button type="button" class="btn btn-primary" @click="goToLogin">
            <i class="fas fa-sign-in-alt"></i>
            {{ t("admin_reset_backLogin") }}
          </button>
        </div>

        <div class="help-text">
          <p>{{ t("admin_reset_trouble") }}</p>
          <a :href="`mailto:${branding.supportEmail}`">{{
            t("admin_login_contactIt")
          }}</a>
        </div>
      </div>

      <!-- No Token State -->
      <div v-else-if="!resetToken" class="reset-status error">
        <div class="error-icon">
          <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h2>{{ t("admin_reset_invalidTitle") }}</h2>
        <p>{{ t("admin_reset_invalidDesc") }}</p>

        <button type="button" class="btn btn-primary" @click="goToLogin">
          <i class="fas fa-sign-in-alt"></i>
          {{ t("admin_reset_goLogin") }}
        </button>
      </div>

      <!-- Password Reset Form -->
      <div v-else class="reset-form">
        <h2>{{ t("admin_reset_formTitle") }}</h2>
        <p class="form-subtitle">{{ t("admin_reset_formSubtitle") }}</p>

        <form @submit.prevent="handleResetPassword">
          <div class="form-group">
            <label for="password">{{ t("admin_reset_newPwdLabel") }}</label>
            <div class="input-wrapper">
              <span class="input-icon"><i class="fas fa-lock"></i></span>
              <input
                :type="showPassword ? 'text' : 'password'"
                id="password"
                v-model="form.password"
                :placeholder="t('admin_reset_newPwdPh')"
                required
                autocomplete="new-password"
              />
              <button
                type="button"
                class="password-toggle"
                @click="showPassword = !showPassword"
                :aria-label="
                  showPassword
                    ? t('admin_login_hidePwd')
                    : t('admin_login_showPwd')
                "
              >
                <i
                  :class="showPassword ? 'fas fa-eye-slash' : 'fas fa-eye'"
                ></i>
              </button>
            </div>
            <small class="password-hint">
              {{ t("admin_reset_pwdMinHint") }}
            </small>
          </div>

          <div class="form-group">
            <label for="confirmPassword">{{
              t("admin_reset_confirmPwdLabel")
            }}</label>
            <div class="input-wrapper">
              <span class="input-icon"><i class="fas fa-lock"></i></span>
              <input
                :type="showConfirmPassword ? 'text' : 'password'"
                id="confirmPassword"
                v-model="form.confirmPassword"
                :placeholder="t('admin_reset_confirmPwdPh')"
                required
                autocomplete="new-password"
              />
              <button
                type="button"
                class="password-toggle"
                @click="showConfirmPassword = !showConfirmPassword"
                :aria-label="
                  showConfirmPassword
                    ? t('admin_login_hidePwd')
                    : t('admin_login_showPwd')
                "
              >
                <i
                  :class="
                    showConfirmPassword ? 'fas fa-eye-slash' : 'fas fa-eye'
                  "
                ></i>
              </button>
            </div>
            <small
              v-if="
                form.password &&
                form.confirmPassword &&
                form.password !== form.confirmPassword
              "
              class="error-hint"
            >
              <i class="fas fa-exclamation-circle"></i>
              {{ t("admin_reset_pwdMismatch") }}
            </small>
          </div>

          <div v-if="formError" class="error-message">
            {{ formError }}
          </div>

          <button
            type="submit"
            class="login-button"
            :disabled="submitting || !isFormValid"
          >
            {{
              submitting
                ? t("admin_reset_btnSubmitting")
                : t("admin_reset_btnSubmit")
            }}
          </button>
        </form>

        <div class="back-link">
          <a href="#" @click.prevent="goToLogin">
            <i class="fas fa-arrow-left"></i>
            {{ t("admin_reset_backLogin") }}
          </a>
        </div>
      </div>

      <div class="footer">
        © {{ resetYear }} {{ branding.companyShortName }}.
        {{ t("admin_login_footerRights") }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import brandingApi from "@/services/brandingApi";
import AdminLanguageSwitcher from "@/components/layout/AdminLanguageSwitcher.vue";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t } = useAdminI18n();
const resetYear = computed(() => new Date().getFullYear());
const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const submitting = ref(false);
const success = ref(false);
const error = ref(false);
const errorMessage = ref("");
const formError = ref("");

// Branding configuration
const branding = ref({
  logoText: "CRM",
  companyShortName: "Platform",
  copyrightText: "Trading Platform",
  supportEmail: "support.demo@gmail.com",
});

// Form state
const form = ref({
  password: "",
  confirmPassword: "",
});

const showPassword = ref(false);
const showConfirmPassword = ref(false);

// 从URL获取token
const resetToken = computed(() => route.query.token);

// 表单验证
const isFormValid = computed(() => {
  return (
    form.value.password &&
    form.value.confirmPassword &&
    form.value.password === form.value.confirmPassword &&
    form.value.password.length >= 8
  );
});

// 处理密码重置
const handleResetPassword = async () => {
  // 清除之前的错误
  formError.value = "";
  error.value = false;
  errorMessage.value = "";

  // 前端验证
  if (!form.value.password || !form.value.confirmPassword) {
    formError.value = "Please fill in all fields";
    return;
  }

  if (form.value.password !== form.value.confirmPassword) {
    formError.value = "Passwords do not match";
    return;
  }

  if (form.value.password.length < 8) {
    formError.value = "Password must be at least 8 characters long";
    return;
  }

  submitting.value = true;

  try {
    const result = await authStore.resetPassword(
      resetToken.value,
      form.value.password,
      form.value.confirmPassword,
    );

    if (result.success) {
      success.value = true;
      // 3秒后跳转到登录页
      setTimeout(() => {
        router.push({
          name: "login",
          query: { message: "password_reset_success" },
        });
      }, 3000);
    } else {
      error.value = true;
      errorMessage.value =
        result.error || "Password reset failed. Please try again.";
    }
  } catch (err) {
    error.value = true;
    errorMessage.value =
      err.message ||
      err.error ||
      "An unexpected error occurred. Please try again.";
    console.error("Reset password error:", err);
  } finally {
    submitting.value = false;
  }
};

// 返回登录页
const goToLogin = () => {
  router.push({ name: "login" });
};

// 页面加载时初始化
onMounted(async () => {
  // Load branding configuration
  try {
    const config = await brandingApi.getBranding();
    branding.value = {
      logoText: config.logoText || "CRM",
      companyShortName: config.companyShortName || "Platform",
      copyrightText: config.copyrightText || "Trading Platform",
      supportEmail: config.supportEmail || "support.demo@gmail.com",
    };
  } catch (error) {
    console.error("Failed to load branding:", error);
  }
});
</script>

<style scoped>
.reset-password-page {
  position: relative;
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: var(--color-surface-soft);
}

.admin-reset-lang {
  position: absolute;
  top: 20px;
  right: 20px;
  z-index: 10;
}

.admin-reset-container {
  background: var(--color-surface);
  border-radius: var(--radius-xl);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  width: 100%;
  max-width: 480px;
  padding: 50px 45px;
}

.admin-header {
  text-align: center;
  margin-bottom: 40px;
}

.admin-logo {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 20px;
}

.admin-logo-text {
  font-size: 32px;
  font-weight: 700;
  background: var(--color-brand-solid);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.admin-header p {
  color: var(--color-muted);
  font-size: 14px;
  margin: 0;
}

/* Reset Status */
.reset-status {
  text-align: center;
  padding: 20px 0;
}

.reset-status h2 {
  font-size: 24px;
  color: var(--color-ink);
  margin: 20px 0 10px;
  font-weight: 700;
}

.reset-status p {
  font-size: 16px;
  color: var(--color-muted);
  line-height: 1.6;
  margin-bottom: 20px;
}

/* Spinner */
.spinner {
  width: 60px;
  height: 60px;
  margin: 0 auto 20px;
  border: 5px solid var(--color-border);
  border-top-color: var(--color-brand);
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* Success State */
.reset-status.success .success-icon {
  font-size: 80px;
  color: var(--color-success);
  margin-bottom: 20px;
  animation: scaleIn 0.5s ease;
}

@keyframes scaleIn {
  from {
    transform: scale(0);
  }
  to {
    transform: scale(1);
  }
}

.redirect-info {
  background: var(--color-brand-soft);
  border-radius: var(--radius-md);
  padding: 15px 20px;
  color: var(--color-brand);
  font-weight: 600;
  margin-top: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
}

/* Error State */
.reset-status.error .error-icon {
  font-size: 80px;
  color: var(--color-danger);
  margin-bottom: 20px;
  animation: shake 0.5s ease;
}

@keyframes shake {
  0%,
  100% {
    transform: translateX(0);
  }
  25% {
    transform: translateX(-10px);
  }
  75% {
    transform: translateX(10px);
  }
}

.error-message {
  background: var(--color-danger-soft);
  border-left: 4px solid var(--color-danger);
  padding: 15px 20px;
  border-radius: var(--radius-md);
  color: var(--color-danger);
  font-weight: 500;
  margin: 20px 0;
}

.error-actions {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 30px;
}

.btn {
  padding: 14px 32px;
  border-radius: var(--radius-md);
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    transform 0.3s ease,
    opacity 0.3s ease,
    width 0.3s ease,
    max-height 0.3s ease,
    filter 0.3s ease;
  border: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  width: 100%;
}

.btn-primary {
  background: var(--color-brand-solid);
  color: white;
  box-shadow: 0 4px 15px rgba(var(--color-brand-rgb), 0.4);
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(var(--color-brand-rgb), 0.5);
}

.help-text {
  margin-top: 30px;
  padding-top: 20px;
  border-top: 1px solid var(--color-border);
  text-align: center;
}

.help-text p {
  color: var(--color-muted);
  font-size: 14px;
  margin-bottom: 8px;
}

.help-text a {
  color: var(--color-brand);
  font-weight: 600;
  text-decoration: none;
  transition: color 0.3s ease;
}

.help-text a:hover {
  color: var(--color-brand-strong);
  text-decoration: underline;
}

/* Reset Form */
.reset-form {
  padding: 20px 0;
}

.reset-form h2 {
  font-size: 28px;
  color: var(--color-ink);
  margin-bottom: 10px;
  font-weight: 700;
  text-align: center;
}

.form-subtitle {
  text-align: center;
  color: var(--color-muted);
  font-size: 15px;
  margin-bottom: 30px;
}

.form-group {
  margin-bottom: 25px;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  color: var(--color-text);
  font-weight: 500;
  font-size: 14px;
}

.input-wrapper {
  position: relative;
  width: 100%;
}

.input-wrapper input {
  width: 100%;
  padding: 14px 50px 14px 45px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 15px;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    transform 0.3s ease,
    opacity 0.3s ease,
    width 0.3s ease,
    max-height 0.3s ease,
    filter 0.3s ease;
  background: var(--color-surface);
}

.input-wrapper input:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.input-icon {
  position: absolute;
  left: 16px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-faint);
  font-size: 16px;
  z-index: 1;
}

.password-toggle {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  cursor: pointer;
  font-size: 18px;
  padding: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--color-muted);
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    transform 0.3s ease,
    opacity 0.3s ease,
    width 0.3s ease,
    max-height 0.3s ease,
    filter 0.3s ease;
  z-index: 10;
  width: 36px;
  height: 36px;
  border-radius: var(--radius-sm);
}

.password-toggle:hover {
  color: var(--color-brand);
  background: rgba(var(--color-brand-rgb), 0.1);
}

.password-hint {
  display: block;
  margin-top: 8px;
  color: var(--color-muted);
  font-size: 14px;
}

.error-hint {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 8px;
  padding: 10px 12px;
  background: var(--color-danger-soft);
  border-left: 3px solid var(--color-danger);
  border-radius: var(--radius-sm);
  color: var(--color-danger);
  font-size: 14px;
  line-height: 1.5;
}

.error-hint i {
  color: var(--color-danger);
  font-size: 14px;
  flex-shrink: 0;
}

.login-button {
  width: 100%;
  padding: 16px;
  background: var(--color-brand-solid);
  color: white;
  border: none;
  border-radius: var(--radius-md);
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition:
    transform 0.2s ease,
    box-shadow 0.3s ease;
  box-shadow: 0 4px 15px rgba(var(--color-brand-rgb), 0.4);
  margin-top: 10px;
}

.login-button:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(var(--color-brand-rgb), 0.5);
}

.login-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.back-link {
  margin-top: 25px;
  text-align: center;
}

.back-link a {
  color: var(--color-brand);
  text-decoration: none;
  font-size: 14px;
  font-weight: 500;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: color 0.3s ease;
}

.back-link a:hover {
  color: var(--color-brand-strong);
}

.footer {
  text-align: center;
  margin-top: 30px;
  padding-top: 20px;
  border-top: 1px solid var(--color-border);
  color: var(--color-faint);
  font-size: 14px;
}

/* Responsive */
@media (max-width: 768px) {
  .admin-reset-container {
    padding: 30px 20px;
    border-radius: var(--radius-lg);
  }

  .admin-logo-text {
    font-size: 28px;
  }

  .reset-status h2,
  .reset-form h2 {
    font-size: 20px;
  }

  .reset-status p,
  .form-subtitle {
    font-size: 14px;
  }

  .reset-status.success .success-icon,
  .reset-status.error .error-icon {
    font-size: 60px;
  }
}

.reset-password-page {
  justify-content: flex-end;
  padding: 48px clamp(48px, 8vw, 132px);
  overflow: hidden;
  background: linear-gradient(
    90deg,
    var(--color-sidebar) 0 42%,
    var(--color-canvas) 42% 100%
  );
}

.reset-password-page::before {
  content: "";
  position: absolute;
  left: clamp(48px, 8vw, 132px);
  top: 22%;
  width: min(24vw, 360px);
  aspect-ratio: 1;
  border: 1px solid rgba(216, 188, 131, 0.42);
  border-radius: 50%;
  box-shadow:
    60px 80px 0 -1px var(--color-sidebar),
    60px 80px 0 0 rgba(216, 188, 131, 0.18);
}

.admin-reset-container {
  position: relative;
  z-index: 1;
  max-width: 520px;
  padding: 48px 50px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-xl);
  box-shadow: var(--shadow-lg);
}

.admin-header {
  text-align: left;
  margin-bottom: 34px;
}

.admin-logo {
  justify-content: flex-start;
  margin-bottom: 12px;
}

.admin-logo-text {
  font-family: var(--font-display);
  font-size: 40px;
  font-weight: 600;
  letter-spacing: -0.03em;
  background: none;
  color: var(--color-ink);
  -webkit-text-fill-color: currentColor;
}

.form-group input {
  min-height: 48px;
  border-width: 1px;
}

.login-button {
  min-height: 50px;
  background: var(--color-brand-solid);
  border-radius: var(--radius-md);
  box-shadow: none;
}

.login-button:hover:not(:disabled) {
  background: var(--color-brand-strong);
  transform: translateY(-1px);
  box-shadow: var(--shadow-sm);
}

@media (max-width: 900px) {
  .reset-password-page {
    justify-content: center;
    padding: 88px 20px 32px;
    background: var(--color-canvas);
  }

  .reset-password-page::before {
    display: none;
  }

  .admin-reset-container {
    padding: 38px 28px;
  }
}
</style>
