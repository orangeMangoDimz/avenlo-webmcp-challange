<template>
  <div class="container ui-page">
    <!-- Loading State: wait for both KYC and accounts so verified users don't flash KYC card -->
    <div v-if="initialLoading" class="loading-notice">
      <i
        class="fas fa-spinner fa-spin"
        style="font-size: 24px; color: var(--color-brand)"
      ></i>
      <p style="margin-top: 10px; color: var(--color-muted)">
        {{ t("loadingDashboard", "Loading dashboard...") }}
      </p>
    </div>

    <!-- Default KYC Notice (for draft/expired/resubmit_required status) -->
    <div
      v-else-if="
        !initialLoading &&
        showDefaultNotice &&
        noticeConfig &&
        noticeConfig.display
      "
      class="kyc-notice-card"
      :style="{
        '--notice-accent':
          noticeConfig.display.borderColor || 'var(--color-brand)',
      }"
    >
      <div class="kyc-notice-header">
        <div v-if="noticeConfig.display.showIcon" class="kyc-notice-icon">
          <i :class="noticeConfig.display.iconClass || 'fas fa-id-card'"></i>
        </div>
        <div class="kyc-notice-title">
          <h2>
            {{
              noticeConfig.title ||
              t("completeKycVerification", "Complete Your KYC Verification")
            }}
          </h2>
          <p>
            {{
              noticeConfig.subtitle ||
              t(
                "verifyIdentitySubtitle",
                "Verify your identity to unlock full trading capabilities",
              )
            }}
          </p>
        </div>
      </div>

      <div class="kyc-notice-content">
        <p
          class="font-floor-content"
          v-html="
            noticeConfig.description ||
            t('pleaseCompleteKyc', 'Please complete your KYC verification.')
          "
        ></p>

        <div
          v-if="
            noticeConfig.requirements && noticeConfig.requirements.length > 0
          "
          class="kyc-requirements"
        >
          <h3>
            <i class="fas fa-clipboard-list"></i>
            {{
              noticeConfig.requirementsTitle ||
              t("requiredDocuments", "Required Documents")
            }}
          </h3>
          <div class="kyc-requirements-list">
            <div
              v-for="requirement in noticeConfig.requirements"
              :key="requirement.id"
              class="kyc-requirement-item"
            >
              <i :class="requirement.iconClass || 'fas fa-file-alt'"></i>
              <div class="kyc-requirement-text">
                <strong>{{ requirement.itemTitle }}</strong>
                <span>{{ requirement.itemDescription }}</span>
              </div>
            </div>
          </div>
        </div>

        <div v-if="noticeConfig.verificationTimeNotice" class="info-alert">
          <i class="fas fa-info-circle"></i>
          <p
            class="font-floor-content"
            v-html="noticeConfig.verificationTimeNotice"
          ></p>
        </div>
      </div>

      <div class="kyc-notice-actions">
        <button
          v-if="noticeConfig.primaryButton"
          class="btn btn-primary"
          @click="handlePrimaryAction"
          :disabled="startingKyc || clientAuthStore.isPreviewMode"
          :title="
            clientAuthStore.isPreviewMode
              ? 'Preview mode - operation not allowed'
              : undefined
          "
        >
          <i class="fas fa-check-circle"></i>
          {{
            noticeConfig.primaryButton.text ||
            t("startVerificationNow", "Start Verification Now")
          }}
        </button>
        <!-- 屏蔽noticeConfig.secondaryButton -->
        <button
          v-if="false"
          class="btn btn-secondary"
          @click="handleSecondaryAction"
        >
          <i class="fas fa-book"></i>
          {{
            noticeConfig.secondaryButton.text || t("learnMore", "Learn More")
          }}
        </button>
      </div>
    </div>

    <!-- KYC Status Card (Dynamic based on user status) -->
    <KycStatusCard
      v-else-if="!initialLoading && shouldShowKycCard"
      :kyc-status="kycStatus"
      @refresh="loadKycStatus"
    />

    <!-- Fallback if no data -->
    <div
      v-else-if="!initialLoading && !showAdditionalContent"
      class="info-message"
    >
      <i class="fas fa-info-circle"></i>
      <p>
        {{
          t(
            "welcomeDashboardKyc",
            "Welcome to your dashboard! KYC verification information will appear here when available.",
          )
        }}
      </p>
    </div>

    <!-- Additional Dashboard Content (Future) -->
    <VerifiedDashboardSummary v-if="!initialLoading && showAdditionalContent" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import { useClientAuthStore } from "@/stores/clientAuth";
import { useLanguageStore } from "@/stores/language";
import { kycSettingsService } from "@/services/kycSettingsService";
import { clientKycService } from "@/services/clientKycService";
import tradingAccountService from "@/services/tradingAccountService";
import KycStatusCard from "@/components/client/KycStatusCard.vue";
import VerifiedDashboardSummary from "@/components/client/VerifiedDashboardSummary.vue";

const router = useRouter();
const clientAuthStore = useClientAuthStore();
const languageStore = useLanguageStore();

// KYC Status State
const loadingKycStatus = ref(false);
const kycStatus = ref(null);
const startingKyc = ref(false);
const hasTradingAccounts = ref(false);

// Initial load: wait for both KYC + accounts before deciding view (avoids flash of KYC card for verified users)
const initialLoading = ref(true);

// KYC Notice State (fallback for draft status)
const loadingNotice = ref(false);
const noticeConfig = ref(null);

// Translation function
const t = (key, fallback = "") => languageStore.t(key, fallback);

// Show default notice for specific statuses
const showDefaultNotice = computed(() => {
  if (!kycStatus.value) return true;
  return ["draft", "expired"].includes(kycStatus.value.submissionStatus);
});

const shouldShowKycCard = computed(() => {
  if (!kycStatus.value || showDefaultNotice.value) {
    return false;
  }
  const isApproved = kycStatus.value.submissionStatus === "approved";
  if (isApproved) {
    return !hasTradingAccounts.value;
  }
  return true;
});

const showAdditionalContent = computed(() => {
  if (!kycStatus.value) return false;
  return (
    kycStatus.value.submissionStatus === "approved" && hasTradingAccounts.value
  );
});

// Load KYC Status
const loadKycStatus = async () => {
  loadingKycStatus.value = true;
  try {
    const response = await clientKycService.getClientStatus();

    if (response.success && response.data) {
      kycStatus.value = response.data;

      // Load notice config for draft status
      if (showDefaultNotice.value) {
        await loadKycNoticeConfig();
      }
    } else {
      console.warn("No KYC status found");
      await loadKycNoticeConfig();
    }
  } catch (error) {
    console.error("Failed to load KYC status:", error);
    // Fallback to notice config
    await loadKycNoticeConfig();
  } finally {
    loadingKycStatus.value = false;
  }
};

// Load KYC Notice Configuration from API (fallback)
const loadKycNoticeConfig = async () => {
  loadingNotice.value = true;
  try {
    const response = await kycSettingsService.getClientNoticeConfig();

    if (response.success && response.data) {
      noticeConfig.value = response.data;
      // console.log('KYC Notice Config loaded:', noticeConfig.value)
    } else {
      console.log("No KYC notice configuration available");
      noticeConfig.value = null;
    }
  } catch (error) {
    console.error("Failed to load KYC notice config:", error);
    // Use fallback - don't show notice if API fails
    noticeConfig.value = null;
  } finally {
    loadingNotice.value = false;
  }
};

const handlePrimaryAction = async () => {
  if (clientAuthStore.isPreviewMode) {
    window.alert("Preview mode - operation not allowed"); // i18n: same as api intercept
    return;
  }
  startingKyc.value = true;
  try {
    // Start or continue KYC process
    const response = await clientKycService.startKycProcess();

    if (response.success) {
      // If there's an existing submission, navigate directly to the verification page
      // The KycVerification component will handle finding the first incomplete step
      if (response.data && response.data.submissionId) {
        router.push({
          path: "/client/kyc-verification",
          query: { submissionId: response.data.submissionId },
        });
      } else {
        // New submission
        router.push("/client/kyc-verification");
      }
    } else {
      throw new Error(response.message || "Failed to start KYC process");
    }
  } catch (error) {
    console.error("Failed to start KYC process:", error);
    // Fallback: direct navigation
    router.push("/client/kyc-verification");

    // Show error message to user
    // You might want to use a toast or alert here
    // For example: showToast('Failed to start KYC process. Please try again.', 'error')
  } finally {
    startingKyc.value = false;
  }
};

const handleSecondaryAction = () => {
  const action = noticeConfig.value?.secondaryButton?.action;
  if (action) {
    if (action.startsWith("http://") || action.startsWith("https://")) {
      window.open(action, "_blank");
    } else if (action.startsWith("/client/")) {
      router.push(action);
    } else {
      router.push("/client" + action);
    }
  } else {
    alert(t("learnMoreKyc", "Learn more about KYC requirements"));
  }
};

// Load trading accounts (used in parallel with KYC for initial view decision)
const loadAccounts = async () => {
  try {
    const accountResponse = await tradingAccountService.getAccounts();
    const accounts = accountResponse.data || accountResponse;
    const count = Array.isArray(accounts)
      ? accounts.length
      : (accounts?.accounts?.length ?? 0);
    hasTradingAccounts.value = count > 0;
  } catch (error) {
    console.error("Failed to load trading accounts:", error);
    hasTradingAccounts.value = false;
  }
};

// Lifecycle: load KYC and accounts in parallel so we know the correct view before first paint
onMounted(async () => {
  initialLoading.value = true;
  await Promise.all([loadKycStatus(), loadAccounts()]);

  if (kycStatus.value) {
    clientAuthStore.updateKycStatus(kycStatus.value);
  }

  // KYC 已通过但还没开任何 trading account → 直接送去开户页
  // 没有账户的用户在 dashboard 上也没东西看（VerifiedDashboardSummary 必须有账户才显示），干脆推到开户页
  // preview 模式跳过，保留后台预览客户状态的能力
  if (
    !clientAuthStore.isPreviewMode &&
    kycStatus.value?.submissionStatus === "approved" &&
    !hasTradingAccounts.value
  ) {
    router.replace("/client/account/new");
    return;
  }

  initialLoading.value = false;
});
</script>

<style scoped>
.container {
  max-width: 1200px;
  margin: 0 auto;
  /*padding: 40px 20px;*/
}

/* Loading State */
.loading-notice {
  text-align: center;
  padding: 60px 20px;
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  margin-bottom: 30px;
}

/* Info Message */
.info-message {
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: 40px 30px;
  margin-bottom: 30px;
  text-align: center;
  color: var(--color-muted);
}

.info-message i {
  font-size: 48px;
  margin-bottom: 15px;
  display: block;
  color: var(--color-faint);
}

.info-message p {
  font-size: 16px;
  margin: 0;
}

/* KYC Notice Card */
.kyc-notice-card {
  --notice-accent: var(--color-brand);
  color: var(--color-text);
  background: var(--color-brand-soft);
  border: 1px solid var(--notice-accent);
  border-radius: var(--radius-lg);
  padding: 30px;
  margin-bottom: 30px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    opacity 0.3s ease,
    transform 0.3s ease;
}

.kyc-notice-header {
  display: flex;
  align-items: center;
  gap: 20px;
  margin-bottom: 20px;
}

.kyc-notice-icon {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28px;
  color: #fff;
  background: var(--color-brand-solid);
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.kyc-notice-title {
  flex: 1;
}

.kyc-notice-title h2 {
  font-size: 22px;
  color: var(--color-ink);
  margin-bottom: 5px;
}

.kyc-notice-title p {
  font-size: 14px;
  color: var(--color-muted);
}

.kyc-notice-content {
  margin-bottom: 25px;
}

.kyc-notice-content p {
  color: var(--color-text);
  line-height: 1.6;
  margin-bottom: 15px;
}

.kyc-requirements {
  background: var(--color-surface);
  border-radius: var(--radius-md);
  padding: 20px;
  margin-top: 20px;
}

.kyc-requirements h3 {
  font-size: 16px;
  color: var(--color-ink);
  margin-bottom: 15px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.kyc-requirements h3 i {
  color: var(--color-brand);
}

.kyc-requirements-list {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 15px;
}

.kyc-requirement-item {
  display: flex;
  align-items: start;
  gap: 12px;
  padding: 12px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-md);
}

.kyc-requirement-item i {
  color: var(--color-brand);
  font-size: 18px;
  margin-top: 2px;
}

.kyc-requirement-text {
  flex: 1;
}

.kyc-requirement-text strong {
  display: block;
  color: var(--color-ink);
  margin-bottom: 3px;
  font-size: 14px;
}

.kyc-requirement-text span {
  color: var(--color-muted);
  font-size: 14px;
  display: block;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.kyc-notice-actions {
  display: flex;
  gap: 15px;
  flex-wrap: wrap;
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
    opacity 0.3s ease,
    transform 0.3s ease;
  border: none;
  display: inline-flex;
  align-items: center;
  gap: 10px;
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

.info-alert {
  background: var(--color-brand-soft);
  border-left: 1px solid var(--color-brand);
  padding: 15px 20px;
  border-radius: var(--radius-md);
  margin-top: 20px;
  display: flex;
  align-items: start;
  gap: 12px;
}

.info-alert i {
  color: var(--color-brand);
  font-size: 18px;
  margin-top: 2px;
}

.info-alert p {
  color: var(--color-text);
  font-size: 14px;
  line-height: 1.6;
  margin: 0;
}

@media (max-width: 768px) {
  .kyc-notice-content {
    flex-direction: column;
  }

  .btn {
    width: 100%;
    justify-content: center;
  }
}
</style>
