<template>
  <div class="sales-referral-page">
    <div class="loading-overlay" v-if="loading">
      <div class="loading-spinner">
        <i class="fas fa-spinner fa-spin"></i>
        <p>{{ loadingText }}</p>
      </div>
    </div>
    <div class="error-container" v-else-if="error">
      <div class="error-content">
        <i class="fas fa-exclamation-circle"></i>
        <p>{{ error }}</p>
        <a href="#/client/login?mode=signup" class="btn btn-primary"
          >Go to Login</a
        >
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import clientAuthService from "@/services/clientAuthService";

const SALES_REFERRAL_REF_KEY = "salesReferralRef";

const route = useRoute();
const router = useRouter();
const loading = ref(true);
const error = ref(null);
const loadingText = ref("Redirecting...");

async function recordVisitAndRedirect() {
  const suffix =
    route.params.suffix ||
    (route.query.ref && String(route.query.ref).trim()) ||
    "";
  if (!suffix) {
    error.value = "Invalid referral link.";
    loading.value = false;
    return;
  }
  try {
    await clientAuthService.salesReferralVisit(suffix);
  } catch (e) {
    // 不阻断：记录失败也照常跳转
  }
  try {
    sessionStorage.setItem(SALES_REFERRAL_REF_KEY, suffix);
  } catch (e) {}
  router
    .replace({ path: "/client/login", query: { mode: "signup" } })
    .catch(() => {
      window.location.hash = "#/client/login?mode=signup";
    });
}

onMounted(() => {
  recordVisitAndRedirect();
});
</script>

<style scoped>
.sales-referral-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-surface-soft);
}
.loading-overlay {
  text-align: center;
}
.loading-spinner {
  font-size: 18px;
  color: var(--color-text);
}
.loading-spinner i {
  display: block;
  margin-bottom: 12px;
  font-size: 32px;
  color: var(--color-brand);
}
.error-container {
  text-align: center;
  padding: 24px;
}
.error-content {
  max-width: 400px;
}
.error-content i {
  font-size: 48px;
  color: var(--color-danger);
  margin-bottom: 12px;
}
.error-content p {
  margin: 12px 0 20px;
  color: var(--color-text);
}
.btn {
  display: inline-block;
  padding: 10px 20px;
  border-radius: var(--radius-md);
  background: var(--color-brand);
  color: white;
  text-decoration: none;
  font-weight: 600;
}
.btn:hover {
  background: var(--color-brand-strong);
  color: white;
}
</style>
