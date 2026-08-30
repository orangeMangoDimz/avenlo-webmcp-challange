<template>
  <div class="login-page workspace-access-page">
    <div class="admin-login-lang">
      <AdminLanguageSwitcher />
      <AdminUserGuideLink />
    </div>
    <div class="admin-login-container">
      <aside class="admin-access-brand">
        <span class="admin-access-kicker">{{
          t("admin_login_securityTitle")
        }}</span>
        <h1>{{ branding.logoText }}</h1>
        <p>{{ t("admin_login_secureSubtitle") }}</p>
        <div class="admin-access-brand-note">
          <i class="fas fa-shield-alt" aria-hidden="true"></i>
          <span>{{ t("admin_login_securityBody") }}</span>
        </div>
      </aside>
      <section class="admin-access-form-panel">
        <div class="admin-header">
          <div class="admin-logo">
            <h1 class="admin-logo-text">{{ branding.logoText }}</h1>
          </div>
          <p>{{ t("admin_login_secureSubtitle") }}</p>
        </div>

        <form class="access-form-grid" @submit.prevent="handleLogin">
          <section
            class="access-block access-credentials"
            aria-labelledby="access-credentials-title"
          >
            <div class="access-block-heading">
              <span class="access-step" aria-hidden="true">01</span>
              <div>
                <h2 id="access-credentials-title">
                  {{ t("admin_login_usernameLabel") }} &amp;
                  {{ t("admin_login_passwordLabel") }}
                </h2>
                <p>{{ t("admin_login_secureSubtitle") }}</p>
              </div>
            </div>
            <div class="access-fields">
              <div class="form-group">
                <label for="admin-username">{{
                  t("admin_login_usernameLabel")
                }}</label>
                <div class="input-wrapper">
                  <span class="input-icon"><i class="fas fa-user"></i></span>
                  <input
                    type="text"
                    id="admin-username"
                    v-model="loginForm.username"
                    :placeholder="t('admin_login_usernamePh')"
                    required
                  />
                </div>
              </div>
              <div class="form-group">
                <label for="admin-password">{{
                  t("admin_login_passwordLabel")
                }}</label>
                <div class="input-wrapper password-input-wrapper">
                  <span class="input-icon"><i class="fas fa-lock"></i></span>
                  <input
                    :type="showPassword ? 'text' : 'password'"
                    id="admin-password"
                    v-model="loginForm.password"
                    :placeholder="t('admin_login_passwordPh')"
                    required
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
              </div>
            </div>
          </section>

          <div class="access-block access-options">
            <div class="form-options">
              <div class="remember-me">
                <input
                  type="checkbox"
                  id="remember-admin"
                  v-model="loginForm.remember"
                />
                <label for="remember-admin">{{
                  t("admin_login_remember")
                }}</label>
              </div>
              <a
                href="#"
                class="forgot-password"
                @click.prevent="openForgotPasswordModal"
              >
                {{ t("admin_login_forgot") }}
              </a>
            </div>
          </div>

          <div class="access-submit-rail">
            <div class="security-notice">
              <strong
                ><i class="fas fa-shield-alt"></i>
                {{ t("admin_login_securityTitle") }}</strong
              >
              {{ t("admin_login_securityBody") }}
            </div>
            <button type="submit" class="login-button" :disabled="loading">
              {{
                loading
                  ? t("admin_login_signingIn")
                  : t("admin_login_signInBtn")
              }}
            </button>
          </div>
        </form>

        <div class="divider">{{ t("admin_login_needHelp") }}</div>

        <div class="support-link">
          {{ t("admin_login_trouble") }}
          <a href="#" @click.prevent="openSupportModal">{{
            t("admin_login_contactIt")
          }}</a>
        </div>

        <div class="footer">
          © {{ loginYear }} {{ branding.companyShortName }}.
          {{ t("admin_login_footerRights") }}
        </div>
      </section>
    </div>

    <!-- Forgot Password Modal -->
    <Teleport to="body">
      <div
        class="modal"
        :class="{ active: showForgotPassword }"
        @click="closeForgotPasswordModal"
      >
        <div class="modal-content" @click.stop>
          <div class="modal-header">
            <button
              type="button"
              class="close"
              aria-label="Close reset-password dialog"
              @click="closeForgotPasswordModal"
            >
              &times;
            </button>
            <h2>{{ t("admin_login_resetTitle") }}</h2>
            <p>{{ t("admin_login_resetSubtitle") }}</p>
          </div>
          <div class="modal-body">
            <form v-if="!resetEmailSent" @submit.prevent="handleForgotPassword">
              <div class="modal-form-group">
                <label for="reset-email">{{
                  t("admin_login_adminEmail")
                }}</label>
                <div class="input-wrapper">
                  <span class="input-icon"
                    ><i class="fas fa-envelope"></i
                  ></span>
                  <input
                    type="email"
                    id="reset-email"
                    v-model="resetEmail"
                    placeholder="admin@gmail.com"
                    required
                  />
                </div>
                <small>{{ t("admin_login_resetHintEmail") }}</small>
              </div>
              <div v-if="resetError" class="error-message">
                {{ resetError }}
              </div>
              <button
                type="submit"
                class="modal-button"
                :disabled="resetLoading"
              >
                {{
                  resetLoading
                    ? t("common_sending")
                    : t("admin_login_sendResetLink")
                }}
              </button>
            </form>
            <div v-else class="success-message">
              <strong>✓ {{ t("admin_login_resetSentTitle") }}</strong
              ><br />
              {{ t("admin_login_resetSentBody") }} ({{ resetEmail }})
            </div>
            <div class="back-link">
              <a href="#" @click.prevent="closeForgotPasswordModal">{{
                t("admin_login_backToLoginLink")
              }}</a>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Copy Toast -->
    <Teleport to="body">
      <div v-if="copyToast.show" class="copy-toast">
        {{ copyToast.message }}
      </div>
    </Teleport>

    <!-- Support Modal -->
    <Teleport to="body">
      <div
        class="modal"
        :class="{ active: showSupport }"
        @click="closeSupportModal"
      >
        <div class="modal-content" @click.stop>
          <div class="modal-header">
            <button
              type="button"
              class="close"
              aria-label="Close support dialog"
              @click="closeSupportModal"
            >
              &times;
            </button>
            <h2>{{ t("admin_support_modalTitle") }}</h2>
            <p>{{ t("admin_support_modalSubtitle") }}</p>
          </div>
          <div class="modal-body">
            <div class="support-modal-content">
              <div class="support-icon-wrapper">
                <i class="fas fa-headset"></i>
              </div>
              <h3 class="support-title">{{ t("admin_support_needTitle") }}</h3>
              <p class="support-description">
                {{ t("admin_support_desc") }}
              </p>
              <div class="support-info-box">
                <p class="support-label">
                  <i class="fas fa-envelope"></i>
                  {{ t("admin_support_emailSupport") }}
                </p>
                <div class="support-info-row">
                  <span class="copy-button-spacer"></span>
                  <p class="support-value">{{ branding.supportEmail }}</p>
                  <button
                    type="button"
                    @click="copyToClipboard(branding.supportEmail, 'email')"
                    class="copy-button"
                    :title="'Copy ' + branding.supportEmail"
                    :aria-label="'Copy ' + branding.supportEmail"
                  >
                    <i class="fas fa-copy"></i>
                  </button>
                </div>

                <!--                <p class="support-label">-->
                <!--                  <i class="fas fa-phone"></i> Phone Support-->
                <!--                </p>-->
                <!--                <div class="support-info-row">-->
                <!--                  <span class="copy-button-spacer"></span>-->
                <!--                  <p class="support-value">{{ branding.supportPhone }}</p>-->
                <!--                  <button -->
                <!--                    @click="copyToClipboard(branding.supportPhone, 'phone')"-->
                <!--                    class="copy-button"-->
                <!--                    :title="'Copy ' + branding.supportPhone"-->
                <!--                  >-->
                <!--                    <i class="fas fa-copy"></i>-->
                <!--                  </button>-->
                <!--                </div>-->
              </div>
            </div>
            <div class="back-link">
              <a href="#" @click.prevent="closeSupportModal">{{
                t("admin_login_backToLoginLink")
              }}</a>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import brandingApi from "@/services/brandingApi";
import AdminLanguageSwitcher from "@/components/layout/AdminLanguageSwitcher.vue";
import AdminUserGuideLink from "@/components/layout/AdminUserGuideLink.vue";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t } = useAdminI18n();
const router = useRouter();
const authStore = useAuthStore();
const loginYear = computed(() => new Date().getFullYear());

const branding = ref({
  logoText: "CRM",
  companyShortName: "Platform",
  copyrightText: "Trading Platform",
  supportEmail: "support.demo@gmail.com",
  supportPhone: "+1 (555) 123-4567",
});
onMounted(async () => {
  try {
    const config = await brandingApi.getBranding();
    branding.value = {
      logoText: config.logoText || "CRM",
      companyShortName: config.companyShortName || "Platform",
      copyrightText: config.copyrightText || "Trading Platform",
      supportEmail: config.supportEmail || "support.demo@gmail.com",
      supportPhone: config.supportPhone || "+1 (555) 123-4567",
    };
  } catch (error) {
    console.error("Failed to load branding:", error);
  }
});

const loginForm = ref({
  username: "",
  password: "",
  remember: false,
});

const loading = ref(false);
const showPassword = ref(false);
const showForgotPassword = ref(false);
const showSupport = ref(false);
const resetEmail = ref("");
const resetEmailSent = ref(false);
const resetLoading = ref(false);
const resetError = ref("");
const copyToast = ref({ show: false, message: "" });

const handleLogin = async () => {
  loading.value = true;
  try {
    const result = await authStore.login(loginForm.value);
    if (result.success) {
      router.push("/clients-list");
    } else {
      alert(`Login failed: ${result.error}`);
    }
  } catch (error) {
    alert("Login failed. Please try again.");
  } finally {
    loading.value = false;
  }
};

const openForgotPasswordModal = () => {
  resetEmail.value = "";
  resetEmailSent.value = false;
  resetError.value = "";
  showForgotPassword.value = true;
};

const closeForgotPasswordModal = () => {
  showForgotPassword.value = false;
};

const handleForgotPassword = async () => {
  resetLoading.value = true;
  resetError.value = "";
  resetEmailSent.value = false;

  try {
    const result = await authStore.forgotPassword(resetEmail.value);

    if (result.success) {
      resetEmailSent.value = true;
      resetError.value = "";
    } else {
      resetError.value =
        result.error || "Failed to send reset email. Please try again.";
      resetEmailSent.value = false;
    }
  } catch (error) {
    resetError.value =
      error.message ||
      error.error ||
      "Failed to send reset email. Please try again.";
    resetEmailSent.value = false;
  } finally {
    resetLoading.value = false;
  }
};

const openSupportModal = () => {
  showSupport.value = true;
};

const closeSupportModal = () => {
  showSupport.value = false;
};

const copyToClipboard = async (text) => {
  try {
    await navigator.clipboard.writeText(text);
    // 显示提示消息
    showCopyToast(t("admin_login_copySuccess"));
  } catch (err) {
    // 降级方案：使用传统的复制方法
    const textArea = document.createElement("textarea");
    textArea.value = text;
    textArea.style.position = "fixed";
    textArea.style.left = "-999999px";
    document.body.appendChild(textArea);
    textArea.select();
    try {
      document.execCommand("copy");
      showCopyToast(t("admin_login_copySuccess"));
    } catch (e) {
      console.error("Failed to copy:", e);
      showCopyToast(t("admin_login_copyFailed"));
    } finally {
      document.body.removeChild(textArea);
    }
  }
};

const showCopyToast = (message) => {
  copyToast.value = { show: true, message };
  setTimeout(() => {
    copyToast.value = { show: false, message: "" };
  }, 2000);
};
</script>

<style scoped>
.login-page {
  position: relative;
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.admin-login-lang {
  position: absolute;
  top: 20px;
  right: 20px;
  z-index: 10;
  display: flex;
  align-items: center;
  gap: 12px;
}

.admin-login-container {
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
  color: var(--color-ink);
  letter-spacing: 2px;
}

.admin-header p {
  color: var(--color-muted);
  font-size: 15px;
  margin-top: 10px;
}

.form-group {
  margin-bottom: 25px;
}

.form-group label {
  display: block;
  margin-bottom: 10px;
  color: var(--color-ink);
  font-weight: 600;
  font-size: 14px;
}

.input-wrapper {
  position: relative;
}

.input-icon {
  position: absolute;
  left: 16px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-faint);
  font-size: 18px;
}

.form-group input {
  width: 100%;
  padding: 14px 16px 14px 45px;
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
  color: var(--color-ink);
}

/* Password input wrapper - for password field with toggle button */
.password-input-wrapper input {
  padding-right: 50px; /* Make room for the eye icon */
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

.password-toggle:active {
  transform: translateY(-50%) scale(0.95);
  background: rgba(var(--color-brand-rgb), 0.2);
}

.password-toggle:focus {
  outline: 2px solid var(--color-brand);
  outline-offset: 2px;
}

.password-toggle i {
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
}

.form-group input:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.form-group input::placeholder {
  color: var(--color-border-strong);
}

.form-options {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
}

.remember-me {
  display: flex;
  align-items: center;
  gap: 8px;
}

.remember-me input[type="checkbox"] {
  width: 18px;
  height: 18px;
  cursor: pointer;
  accent-color: var(--color-brand);
}

.remember-me label {
  color: var(--color-text);
  font-size: 14px;
  cursor: pointer;
  font-weight: 500;
}

.forgot-password {
  color: var(--color-brand);
  text-decoration: none;
  font-size: 14px;
  font-weight: 600;
  transition: color 0.3s ease;
}

.forgot-password:hover {
  color: var(--color-brand-strong);
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
}

.login-button:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(var(--color-brand-rgb), 0.5);
}

.login-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.security-notice {
  margin-top: 30px;
  padding: 18px;
  background: var(--color-surface-soft);
  border-left: 4px solid var(--color-brand);
  border-radius: var(--radius-md);
  font-size: 13px;
  color: var(--color-text);
  line-height: 1.6;
}

.security-notice strong {
  color: var(--color-ink);
  display: block;
  margin-bottom: 5px;
}

.security-notice strong i {
  margin-right: 8px;
  color: var(--color-brand);
}

.divider {
  display: flex;
  align-items: center;
  margin: 30px 0;
  color: var(--color-faint);
  font-size: 13px;
  font-weight: 500;
}

.divider::before,
.divider::after {
  content: "";
  flex: 1;
  height: 1px;
  background: var(--color-border);
}

.divider::before {
  margin-right: 15px;
}

.divider::after {
  margin-left: 15px;
}

.support-link {
  text-align: center;
  color: var(--color-muted);
  font-size: 14px;
}

.support-link a {
  color: var(--color-brand);
  text-decoration: none;
  font-weight: 600;
  transition: color 0.3s ease;
}

.support-link a:hover {
  color: var(--color-brand-strong);
}

.footer {
  text-align: center;
  margin-top: 30px;
  padding-top: 20px;
  border-top: 1px solid var(--color-border);
  color: var(--color-faint);
  font-size: 13px;
}

/* Modal Styles */
.modal {
  display: none;
  position: fixed;
  z-index: 1000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.5);
  animation: fadeIn 0.3s ease;
}

.modal.active {
  display: flex;
  align-items: center;
  justify-content: center;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.modal-content {
  background-color: var(--color-surface);
  margin: 8% auto;
  padding: 0;
  border-radius: var(--radius-xl);
  max-width: 450px;
  width: 90%;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  animation: slideDown 0.3s ease;
}

@keyframes slideDown {
  from {
    transform: translateY(-50px);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

.modal-header {
  background: var(--color-brand-solid);
  color: white;
  padding: 30px;
  border-radius: 16px 16px 0 0;
  position: relative;
}

.modal-header h2 {
  margin: 0;
  font-size: 24px;
}

.modal-header p {
  margin: 10px 0 0 0;
  opacity: 0.9;
  font-size: 14px;
}

.close {
  border: 0;
  background: transparent;
  position: absolute;
  right: 25px;
  top: 25px;
  color: white;
  font-size: 32px;
  font-weight: 300;
  cursor: pointer;
  transition: transform 0.2s ease;
  line-height: 1;
}

.close:hover {
  transform: rotate(90deg);
}

.modal-body {
  padding: 35px;
}

.modal-form-group {
  margin-bottom: 20px;
}

.modal-form-group label {
  display: block;
  margin-bottom: 8px;
  color: var(--color-ink);
  font-weight: 600;
  font-size: 14px;
}

.modal-form-group input {
  width: 100%;
  padding: 14px 16px 14px 45px;
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

.modal-form-group input:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.modal-form-group small {
  display: block;
  margin-top: 8px;
  color: var(--color-muted);
  font-size: 13px;
}

.modal-button {
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
}

.modal-button:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(var(--color-brand-rgb), 0.5);
}

.modal-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.success-message {
  background: var(--color-success-soft);
  color: var(--color-success);
  padding: 15px;
  border-radius: var(--radius-md);
  margin-bottom: 20px;
  border: 1px solid #c3e6cb;
  text-align: center;
}

.error-message {
  background: var(--color-danger-soft);
  color: var(--color-danger);
  padding: 12px 15px;
  border-radius: var(--radius-md);
  margin-bottom: 15px;
  border: 1px solid var(--color-danger-border);
  font-size: 14px;
  line-height: 1.5;
}

.back-link {
  text-align: center;
  margin-top: 20px;
  font-size: 14px;
}

.back-link a {
  color: var(--color-brand);
  text-decoration: none;
  font-weight: 600;
}

.back-link a:hover {
  text-decoration: underline;
}

.copy-button {
  background: transparent;
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  padding: 6px 10px;
  color: var(--color-brand);
  cursor: pointer;
  transition:
    color 0.2s ease,
    background-color 0.2s ease,
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    transform 0.2s ease,
    opacity 0.2s ease,
    width 0.2s ease,
    max-height 0.2s ease,
    filter 0.2s ease;
  font-size: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 36px;
  height: 36px;
}

.copy-button:hover {
  background: var(--color-brand-solid);
  color: white;
  border-color: var(--color-brand);
  transform: scale(1.05);
}

.copy-button:active {
  transform: scale(0.95);
}

.copy-button i {
  font-size: 14px;
}

.support-info-row {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  margin-bottom: 20px;
}

.support-info-row:last-child {
  margin-bottom: 0;
}

.support-info-row p {
  text-align: center;
  flex: 1;
}

.copy-button-spacer {
  width: 36px;
  height: 36px;
  flex-shrink: 0;
}

.support-modal-content {
  text-align: center;
  padding: 10px 0;
}

.support-icon-wrapper {
  font-size: 48px;
  margin-bottom: 20px;
  color: var(--color-brand);
}

.support-title {
  color: var(--color-ink);
  margin-bottom: 15px;
  font-size: 20px;
}

.support-description {
  color: var(--color-muted);
  margin-bottom: 25px;
  line-height: 1.6;
}

.support-info-box {
  background: var(--color-surface-soft);
  padding: 20px;
  border-radius: var(--radius-md);
  margin-bottom: 20px;
}

.support-label {
  color: var(--color-text);
  margin-bottom: 10px;
  font-weight: 600;
}

.support-value {
  color: var(--color-brand);
  font-size: 15px;
  margin: 0;
}

.copy-toast {
  position: fixed;
  top: 20px;
  right: 20px;
  background: var(--color-ink);
  color: white;
  padding: 12px 20px;
  border-radius: var(--radius-md);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  z-index: 10000;
  animation: slideInRight 0.3s ease;
  font-size: 14px;
  font-weight: 500;
}

@keyframes slideInRight {
  from {
    transform: translateX(100%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

@media (max-width: 768px) {
  .admin-login-container {
    padding: 40px 30px;
  }

  .modal-content {
    margin: 15% auto;
  }
}

/* Refined financial workspace */
.login-page {
  justify-content: flex-end;
  padding: 48px clamp(48px, 8vw, 132px);
  overflow: hidden;
  background: linear-gradient(
    90deg,
    var(--color-sidebar) 0 42%,
    var(--color-canvas) 42% 100%
  );
}

.login-page::before,
.login-page::after {
  content: "";
  position: absolute;
  pointer-events: none;
}

.login-page::before {
  left: clamp(48px, 8vw, 132px);
  top: 18%;
  width: min(24vw, 360px);
  aspect-ratio: 1;
  border: 1px solid rgba(216, 188, 131, 0.42);
  border-radius: 50%;
  box-shadow:
    60px 80px 0 -1px var(--color-sidebar),
    60px 80px 0 0 rgba(216, 188, 131, 0.18);
}

.login-page::after {
  left: clamp(48px, 8vw, 132px);
  bottom: 15%;
  width: min(24vw, 360px);
  height: 1px;
  background: linear-gradient(90deg, var(--color-accent), transparent);
}

.login-visual {
  position: absolute;
  inset: 0 auto 0 0;
  z-index: 1;
  display: grid;
  width: 42%;
  padding: clamp(48px, 8vw, 132px);
  color: #ffffff;
  pointer-events: none;
}

.login-visual-content {
  align-self: center;
  max-width: 390px;
}

.login-visual-kicker {
  display: inline-flex;
  align-items: center;
  min-height: 28px;
  padding: 0 10px;
  border: 1px solid rgba(216, 188, 131, 0.48);
  border-radius: 999px;
  color: #e6c98d;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.login-visual-brand {
  margin: 22px 0 12px;
  color: #ffffff;
  font-family: var(--font-display);
  font-size: clamp(48px, 5vw, 78px);
  font-weight: 600;
  letter-spacing: -0.05em;
  line-height: 0.95;
}

.login-visual-copy {
  max-width: 28ch;
  color: rgba(255, 255, 255, 0.72);
  font-size: clamp(15px, 1.2vw, 18px);
  line-height: 1.55;
}

.login-visual-rule {
  display: block;
  width: min(100%, 210px);
  height: 1px;
  margin-top: 30px;
  background: linear-gradient(
    90deg,
    var(--color-accent),
    rgba(216, 188, 131, 0)
  );
}

.admin-login-lang {
  top: 24px;
  right: clamp(32px, 5vw, 72px);
}

.admin-login-container {
  position: relative;
  z-index: 1;
  max-width: 520px;
  padding: 48px 50px;
  border: 1px solid rgba(199, 193, 181, 0.82);
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
  color: var(--color-ink);
}

.admin-header p {
  margin-top: 0;
  color: var(--color-muted);
}

.form-group {
  margin-bottom: 20px;
}

.form-group input,
.modal-form-group input {
  min-height: 48px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
}

.login-button,
.modal-button {
  min-height: 50px;
  padding: 13px 18px;
  background: var(--color-brand-solid);
  border-radius: var(--radius-md);
  box-shadow: none;
}

.login-button:hover:not(:disabled),
.modal-button:hover:not(:disabled) {
  background: var(--color-brand-strong);
  transform: translateY(-1px);
  box-shadow: var(--shadow-sm);
}

.security-notice {
  margin-top: 24px;
  background: var(--color-brand-soft);
  border-left: 3px solid var(--color-brand);
  border-radius: var(--radius-md);
}

.modal-content {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-xl);
  box-shadow: var(--shadow-lg);
}

.modal-header {
  background: var(--color-brand-solid);
  border-radius: var(--radius-xl) var(--radius-xl) 0 0;
}

@media (max-width: 900px) {
  .login-page {
    justify-content: center;
    padding: 88px 20px 32px;
    background: var(--color-canvas);
  }

  .login-page::before,
  .login-page::after,
  .login-visual {
    display: none;
  }

  .admin-login-container {
    padding: 38px 28px;
  }
}

@media (max-width: 480px) {
  .form-options {
    flex-wrap: wrap;
    gap: 12px 16px;
  }
}
</style>
