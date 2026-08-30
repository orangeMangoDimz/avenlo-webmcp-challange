<template>
  <div class="verification-container">
    <div class="verification-card">
      <!-- Logo -->
      <div class="logo">
        <h1>{{ branding.logoText }}</h1>
        <p>{{ t("tradingPlatform", "Trading Platform") }}</p>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="verification-status">
        <div class="spinner"></div>
        <h2>{{ t("verifying", "Verifying Your Email...") }}</h2>
        <p>
          {{
            t("pleaseWait", "Please wait while we verify your email address.")
          }}
        </p>
      </div>

      <!-- Success State -->
      <div v-else-if="success" class="verification-status success">
        <div class="success-icon">
          <i class="fas fa-check-circle"></i>
        </div>
        <h2>{{ t("emailVerified", "Email Verified Successfully!") }}</h2>
        <p>
          {{
            t(
              "verificationSuccess",
              "Your email has been verified. Logging you in...",
            )
          }}
        </p>
        <div class="redirect-info">
          <i class="fas fa-spinner fa-spin"></i>
          {{ t("redirecting", "Redirecting to your dashboard...") }}
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="verification-status error">
        <div class="error-icon">
          <i class="fas fa-times-circle"></i>
        </div>
        <h2>{{ t("verificationFailed", "Verification Failed") }}</h2>
        <p class="error-message">{{ errorMessage }}</p>

        <div class="error-actions">
          <button class="btn btn-primary" @click="resendEmail">
            <i class="fas fa-envelope"></i>
            {{ t("resendVerification", "Resend Verification Email") }}
          </button>
          <button class="btn btn-secondary" @click="goToLogin">
            <i class="fas fa-sign-in-alt"></i>
            {{ t("backToLogin", "Back to Login") }}
          </button>
        </div>

        <div class="help-text">
          <p>
            {{ t("troubleVerifying", "Having trouble verifying your email?") }}
          </p>
          <a :href="`mailto:${branding.supportEmail}`">{{
            t("contactSupport", "Contact Support")
          }}</a>
        </div>
      </div>

      <!-- No Token State -->
      <div v-else class="verification-status error">
        <div class="error-icon">
          <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h2>{{ t("invalidLink", "Invalid Verification Link") }}</h2>
        <p>
          {{ t("noTokenFound", "No verification token found in the URL.") }}
        </p>

        <button class="btn btn-primary" @click="goToLogin">
          <i class="fas fa-sign-in-alt"></i>
          {{ t("goToLogin", "Go to Login") }}
        </button>
      </div>
    </div>

    <!-- Footer -->
    <div class="footer">
      <p>
        © 2025 {{ branding.copyrightText }}.
        {{ t("allRightsReserved", "All rights reserved.") }}
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useClientAuthStore } from "@/stores/clientAuth";
import { useLanguageStore } from "@/stores/language";
import brandingApi from "@/services/brandingApi";

const route = useRoute();
const router = useRouter();
const clientAuthStore = useClientAuthStore();
const languageStore = useLanguageStore();

const loading = ref(false);
const success = ref(false);
const error = ref(false);
const errorMessage = ref("");

// Branding configuration
const branding = ref({
  logoText: "CRM",
  copyrightText: "Trading Platform",
  supportEmail: "support.demo@gmail.com",
});

// Translation function
const t = (key, fallback = "") => languageStore.t(key, fallback);

// 从URL获取token
const verificationToken = computed(() => route.query.token);

// 执行邮箱验证
const verifyEmail = async () => {
  if (!verificationToken.value) {
    error.value = true;
    errorMessage.value = t("noToken", "No verification token provided");
    return;
  }

  loading.value = true;
  error.value = false;

  try {
    const result = await clientAuthStore.verifyEmail(verificationToken.value);

    if (result.success) {
      success.value = true;

      // 如果自动登录成功，3秒后跳转到Dashboard
      if (result.autoLogin) {
        setTimeout(() => {
          router.push({ name: "client-dashboard" });
        }, 3000);
      } else {
        // 如果没有自动登录，跳转到登录页
        setTimeout(() => {
          router.push({
            name: "client-login",
            query: { message: "verification_success" },
          });
        }, 3000);
      }
    } else {
      error.value = true;
      errorMessage.value =
        result.error ||
        t("verificationError", "Verification failed. Please try again.");
    }
  } catch (err) {
    error.value = true;
    errorMessage.value = t(
      "unexpectedError",
      "An unexpected error occurred. Please try again.",
    );
    console.error("Verification error:", err);
  } finally {
    loading.value = false;
  }
};

// 重新发送验证邮件
const resendEmail = () => {
  router.push({
    name: "client-login",
    query: { action: "resend" },
  });
};

// 返回登录页
const goToLogin = () => {
  router.push({ name: "client-login" });
};

// 页面加载时自动验证
onMounted(async () => {
  // Load branding configuration
  try {
    const config = await brandingApi.getBranding();
    branding.value = {
      logoText: config.logoText || "CRM",
      copyrightText: config.copyrightText || "Trading Platform",
      supportEmail: config.supportEmail || "support.demo@gmail.com",
    };
  } catch (error) {
    console.error("Failed to load branding:", error);
  }
  verifyEmail();
});
</script>

<style scoped>
.verification-container {
  min-height: 100vh;
  background: var(--color-brand-solid);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 20px;
  position: relative;
}

.verification-card {
  background: var(--color-surface);
  border-radius: 20px;
  padding: 50px;
  max-width: 600px;
  width: 100%;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  animation: slideUp 0.6s ease;
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Logo */
.logo {
  text-align: center;
  margin-bottom: 40px;
}

.logo h1 {
  font-size: 48px;
  font-weight: 900;
  background: var(--color-brand-solid);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  margin-bottom: 5px;
}

.logo p {
  font-size: 14px;
  color: var(--color-muted);
  font-weight: 500;
}

/* Verification Status */
.verification-status {
  text-align: center;
  padding: 20px 0;
}

.verification-status h2 {
  font-size: 24px;
  color: var(--color-ink);
  margin: 20px 0 10px;
  font-weight: 700;
}

.verification-status p {
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
  border: 1px solid var(--color-border);
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
.verification-status.success .success-icon {
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
.verification-status.error .error-icon {
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
  border-left: 1px solid var(--color-danger);
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
  transition: all 0.3s ease;
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

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(var(--color-brand-rgb), 0.5);
}

.btn-secondary {
  background: var(--color-surface);
  color: var(--color-text);
  border: 1px solid var(--color-border);
}

.btn-secondary:hover {
  background: var(--color-surface-soft);
  border-color: var(--color-border-strong);
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

/* Footer */
.footer {
  position: absolute;
  bottom: 20px;
  text-align: center;
  color: white;
  font-size: 14px;
  opacity: 0.9;
}

/* Responsive */
@media (max-width: 768px) {
  .verification-card {
    padding: 30px 20px;
    border-radius: var(--radius-lg);
  }

  .logo h1 {
    font-size: 36px;
  }

  .verification-status h2 {
    font-size: 20px;
  }

  .verification-status p {
    font-size: 14px;
  }

  .verification-status.success .success-icon,
  .verification-status.error .error-icon {
    font-size: 60px;
  }
}

.verification-container {
  background:
    radial-gradient(
      circle at 12% 16%,
      rgba(185, 141, 63, 0.16),
      transparent 22rem
    ),
    linear-gradient(
      90deg,
      var(--color-sidebar) 0 34%,
      var(--color-canvas) 34% 100%
    );
}

.verification-card {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-xl);
  box-shadow: var(--shadow-lg);
}

.logo h1,
.verification-status h2 {
  font-family: var(--font-display);
  color: var(--color-ink);
}

.btn-primary {
  background: var(--color-brand-solid);
  border-radius: var(--radius-md);
  box-shadow: none;
}

.btn-primary:hover {
  background: var(--color-brand-strong);
  transform: translateY(-1px);
  box-shadow: var(--shadow-sm);
}

@media (max-width: 768px) {
  .verification-container {
    padding: 20px;
    background: var(--color-canvas);
  }
}
</style>
