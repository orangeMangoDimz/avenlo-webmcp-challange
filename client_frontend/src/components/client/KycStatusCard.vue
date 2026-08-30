<template>
  <div class="status-card">
    <!-- Status Header -->
    <div class="status-header">
      <div class="status-header-left">
        <div class="status-icon-wrapper" :class="statusConfig.iconClass">
          <i :class="statusConfig.icon"></i>
        </div>
        <div class="status-info">
          <h2>{{ statusConfig.title }}</h2>
          <!--          <p>{{ statusConfig.description }}</p>-->
        </div>
      </div>
      <div class="status-badge" :class="statusConfig.badgeClass">
        {{ statusConfig.badgeText }}
      </div>
    </div>

    <!-- Old Status Message (for other statuses) -->
    <div
      v-if="kycStatus.submissionStatus === 'incomplete'"
      class="status-message warning"
    >
      <h3>
        <i class="fas fa-exclamation-triangle"></i> {{ statusConfig.title }}
      </h3>
      <p>{{ statusConfig.description }}</p>
    </div>
    <div
      v-else-if="
        (kycStatus.submissionStatus === 'under_review' ||
          kycStatus.submissionStatus === 'pending') &&
        statusMessage
      "
      class="status-message"
      :class="statusMessage.messageType"
    >
      <h3><i :class="statusMessage.icon"></i> {{ statusMessage.title }}</h3>
      <div v-html="statusConfig.description"></div>
    </div>
    <div
      v-else-if="statusMessage"
      class="status-message"
      :class="statusMessage.messageType"
    >
      <h3><i :class="statusMessage.icon"></i> {{ statusMessage.title }}</h3>
      <div v-html="statusConfig.description"></div>
    </div>

    <!-- Status Details -->
    <div
      v-if="kycStatus.submissionStatus === 'incomplete'"
      class="status-details-grid"
    >
      <div class="detail-item">
        <div class="detail-label">{{ t("status", "Status") }}</div>
        <div class="detail-value">{{ t("incomplete", "Incomplete") }}</div>
      </div>
      <div class="detail-item">
        <div class="detail-label">{{ t("startedOn", "Started On") }}</div>
        <div class="detail-value">
          {{ formatDateOnly(kycStatus.createdAt) }}
        </div>
      </div>
      <div class="detail-item">
        <div class="detail-label">{{ t("lastUpdated", "Last Updated") }}</div>
        <div class="detail-value">
          {{ formatDateOnly(kycStatus.updatedAt) }}
        </div>
      </div>
      <div class="detail-item">
        <div class="detail-label">{{ t("progress", "Progress") }}</div>
        <div class="detail-value">
          {{ Math.round(kycStatus.progressPercentage || 0) }}%
          {{ t("complete", "Complete") }}
        </div>
      </div>
    </div>
    <div
      v-else-if="kycStatus.submissionStatus === 'under_review'"
      class="status-details-grid"
    >
      <div class="detail-item">
        <div class="detail-label">{{ t("status", "Status") }}</div>
        <div class="detail-value">{{ t("underReview", "Under Review") }}</div>
      </div>
      <div class="detail-item">
        <div class="detail-label">{{ t("submittedOn", "Submitted On") }}</div>
        <div class="detail-value">
          {{ formatDateOnly(kycStatus.submittedAt) }}
        </div>
      </div>
      <div class="detail-item">
        <div class="detail-label">
          {{ t("expectedCompletion", "Expected Completion") }}
        </div>
        <div class="detail-value">{{ expectedCompletionDate }}</div>
      </div>
      <div class="detail-item">
        <div class="detail-label">{{ t("referenceId", "Reference ID") }}</div>
        <div class="detail-value">{{ kycStatus.submissionId }}</div>
      </div>
    </div>

    <!-- Timeline (for submitted/review statuses) -->
    <div v-if="showTimeline" class="timeline-section">
      <div class="timeline-title">
        <i class="fas fa-history"></i>
        {{ t("verificationTimeline", "Verification Timeline") }}
      </div>
      <div class="timeline">
        <div
          v-for="item in timelineItems"
          :key="item.id"
          class="timeline-item"
          :class="{ completed: item.completed, current: item.current }"
        >
          <div class="timeline-dot"></div>
          <div class="timeline-content">
            <div class="timeline-date">
              {{
                item.eventStatus === "completed"
                  ? item.date
                  : t("pending", "Pending")
              }}
            </div>
            <div class="timeline-title-text">{{ item.title }}</div>
            <div class="timeline-description">{{ item.description }}</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Action Buttons -->
    <div class="action-buttons">
      <button
        v-if="showStartButton"
        class="btn btn-primary"
        @click="handlePrimaryAction"
        :disabled="loading || clientAuthStore.isPreviewMode"
        :title="
          clientAuthStore.isPreviewMode ? PREVIEW_BLOCK_MESSAGE : undefined
        "
      >
        <i class="fas fa-play"></i>
        {{
          statusConfig.actionButtonText ||
          t("startVerificationNow", "Start Verification Now")
        }}
      </button>

      <button
        v-if="showContinueButton"
        class="btn btn-primary"
        @click="handlePrimaryAction"
        :disabled="loading || clientAuthStore.isPreviewMode"
        :title="
          clientAuthStore.isPreviewMode ? PREVIEW_BLOCK_MESSAGE : undefined
        "
      >
        <i class="fas fa-arrow-right"></i>
        {{
          statusConfig.actionButtonText ||
          t("continueApplication", "Continue Application")
        }}
      </button>

      <button
        v-if="showRestartButton"
        class="btn btn-primary"
        @click="restartKyc"
        :disabled="loading || clientAuthStore.isPreviewMode"
        :title="
          clientAuthStore.isPreviewMode ? PREVIEW_BLOCK_MESSAGE : undefined
        "
      >
        <i class="fas fa-redo"></i>
        {{ t("startNewApplication", "Start New Application") }}
      </button>

      <!-- v-if="showContactButton" -->
      <button v-if="false" class="btn btn-secondary" @click="contactSupport">
        <i class="fas fa-headset"></i>
        {{ t("contactSupport", "Contact Support") }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, onMounted } from "vue";
import { useRouter } from "vue-router";
import { useLanguageStore } from "@/stores/language";
import { useClientAuthStore } from "@/stores/clientAuth";
import { clientKycService } from "@/services/clientKycService";
import brandingApi from "@/services/brandingApi";

const PREVIEW_BLOCK_MESSAGE = "Preview mode - operation not allowed";

const languageStore = useLanguageStore();
const clientAuthStore = useClientAuthStore();
const t = (key, fallback = "") => languageStore.t(key, fallback);

const props = defineProps({
  kycStatus: {
    type: Object,
    required: true,
  },
});

const emit = defineEmits(["refresh"]);

const router = useRouter();
const loading = ref(false);
const statusMessage = ref(null);

// Branding configuration
const branding = ref({
  supportEmail: "support.demo@gmail.com",
});

// 状态配置
const statusConfig = computed(() => {
  // 优先使用后端返回的配置
  if (props.kycStatus.statusConfig) {
    const cfg = props.kycStatus.statusConfig;
    return {
      title: cfg.title,
      description: cfg.description,
      icon: cfg.icon || "fas fa-info-circle",
      iconClass: cfg.iconClass || "incomplete",
      badgeText: cfg.badgeText || "",
      badgeClass: cfg.badgeClass || "info",
      messageType: cfg.messageType || "info",
      messageIcon: cfg.messageIcon || "fas fa-info-circle",
      messageTitle: cfg.messageTitle || cfg.title,
      messageContent: cfg.messageContent || cfg.description,
      showActionButton: cfg.showActionButton || false,
      actionButtonText: cfg.actionButtonText || "",
      actionButtonUrl: cfg.actionButtonUrl || "",
    };
  }

  const status = props.kycStatus.submissionStatus || "draft";

  const configs = {
    draft: {
      title: t("completeKycVerification", "Complete Your KYC Verification"),
      description: t(
        "verifyIdentitySubtitle",
        "Verify your identity to unlock full trading capabilities",
      ),
      icon: "fas fa-id-card",
      iconClass: "incomplete",
      badgeText: t("notStarted", "Not Started"),
      badgeClass: "warning",
    },
    incomplete: {
      title: t("kycIncomplete", "KYC Incomplete"),
      description: t(
        "verificationInProgress",
        "Your verification is in progress",
      ),
      icon: "fas fa-hourglass-half",
      iconClass: "incomplete",
      badgeText: t("inProgress", "In Progress"),
      badgeClass: "incomplete",
    },
    pending: {
      title: t("pendingFurtherReview", "Pending - Under Further Review"),
      description: t(
        "kycPendingReviewDesc",
        "Your KYC submission is pending further review by our compliance team.",
      ),
      icon: "fas fa-hourglass-half",
      iconClass: "pending",
      badgeText: t("pendingReview", "Pending Review"),
      badgeClass: "info",
    },
    under_review: {
      title: t("completedInReview", "Completed - In Review"),
      description: t(
        "kycUnderReviewDesc",
        "Your KYC verification is being reviewed by our compliance team.",
      ),
      icon: "fas fa-search",
      iconClass: "in-review",
      badgeText: t("underReview", "Under Review"),
      badgeClass: "info",
    },
    approved: {
      title: t("approved", "Approved"),
      description: t(
        "kycApprovedDesc",
        "Your KYC verification has been approved. You now have full access.",
      ),
      icon: "fas fa-check-circle",
      iconClass: "approved",
      badgeText: t("approved", "Approved"),
      badgeClass: "success",
      actionButtonText: t("startTrading", "Start Trading"),
    },
    rejected: {
      title: t("rejected", "Rejected"),
      description: t(
        "kycRejectedDesc",
        "Your KYC verification was rejected. Please review the feedback and resubmit.",
      ),
      icon: "fas fa-times-circle",
      iconClass: "rejected",
      badgeText: t("rejected", "Rejected"),
      badgeClass: "error",
    },
    expired: {
      title: t("expired", "Expired"),
      description: t(
        "kycExpiredDesc",
        "Your KYC verification has expired. Please start the process again.",
      ),
      icon: "fas fa-exclamation-triangle",
      iconClass: "incomplete",
      badgeText: t("expired", "Expired"),
      badgeClass: "warning",
    },
    resubmit_required: {
      title: t("resubmissionRequired", "Resubmission Required"),
      description: t(
        "resubmitKycDesc",
        "Please resubmit your KYC verification with the requested changes.",
      ),
      icon: "fas fa-redo",
      iconClass: "incomplete",
      badgeText: t("resubmitRequired", "Resubmit Required"),
      badgeClass: "warning",
    },
    pending_documents: {
      title: t("pendingMoreDocuments", "Pending - Need More Documents"),
      description: t(
        "additionalDocsRequired",
        "Additional documents are required to complete your verification.",
      ),
      icon: "fas fa-upload",
      iconClass: "pending",
      badgeText: t("documentsRequired", "Documents Required"),
      badgeClass: "warning",
    },
  };

  return configs[status] || configs.draft;
});

// 显示条件
const showProgress = computed(() => {
  return (
    props.kycStatus.submissionStatus === "incomplete" &&
    props.kycStatus.hasKycRecord
  );
});

const showSubmissionDetails = computed(() => {
  return (
    props.kycStatus.hasKycRecord &&
    ["under_review", "approved", "rejected", "pending"].includes(
      props.kycStatus.submissionStatus,
    )
  );
});

const showTimeline = computed(() => {
  return [
    "incomplete",
    "under_review",
    "approved",
    "rejected",
    "pending",
    "resubmit_required",
  ].includes(props.kycStatus.submissionStatus);
});

const showStartButton = computed(() => {
  return ["draft", "expired"].includes(props.kycStatus.submissionStatus);
});

const showContinueButton = computed(() => {
  return ["incomplete", "approved", "resubmit_required"].includes(
    props.kycStatus.submissionStatus,
  );
});

const showRestartButton = computed(() => {
  return props.kycStatus.submissionStatus === "rejected";
});

const showContactButton = computed(() => {
  return ["rejected", "pending", "under_review"].includes(
    props.kycStatus.submissionStatus,
  );
});

// 时间线数据
const timelineItems = computed(() => {
  // 优先使用后端返回的时间线
  if (Array.isArray(props.kycStatus.timeline)) {
    return props.kycStatus.timeline;
  }

  const status = props.kycStatus.submissionStatus;
  const items = [];

  if (status === "incomplete") {
    // Incomplete状态的时间线
    items.push({
      id: 1,
      title: t("applicationStarted", "Application Started"),
      description: t("kycBegunDesc", "You began the KYC verification process"),
      date: formatDate(props.kycStatus.createdAt),
      completed: true,
      current: false,
    });

    items.push({
      id: 2,
      title: t("personalInfoSubmitted", "Personal Information Submitted"),
      description: t(
        "personalDetailsProvided",
        "Basic personal details have been provided",
      ),
      date: formatDate(props.kycStatus.createdAt),
      completed: true,
      current: false,
    });

    items.push({
      id: 3,
      title: t("financialInfoRequired", "Financial Information Required"),
      description: t(
        "completeFinancialSection",
        "Please complete the financial information section",
      ),
      date: formatDate(props.kycStatus.updatedAt || props.kycStatus.createdAt),
      completed: false,
      current: true,
    });

    items.push({
      id: 4,
      title: t("documentUpload", "Document Upload"),
      description: t(
        "uploadIdentityDocs",
        "Upload required identity verification documents",
      ),
      date: t("pending", "Pending"),
      completed: false,
      current: false,
    });
  } else {
    // 其他状态的时间线
    items.push({
      id: 1,
      title: t("applicationStarted", "Application Started"),
      description: t(
        "kycProcessInitiated",
        "KYC verification process initiated",
      ),
      date: formatDate(props.kycStatus.createdAt),
      completed: true,
      current: false,
    });

    if (props.kycStatus.submittedAt) {
      items.push({
        id: 2,
        title: t("documentsSubmitted", "Documents Submitted"),
        description: t(
          "allDocsUploaded",
          "All required documents have been uploaded",
        ),
        date: formatDate(props.kycStatus.submittedAt),
        completed: true,
        current: false,
      });
    }

    if (status === "under_review" || status === "pending") {
      items.push({
        id: 3,
        title: t("underReview", "Under Review"),
        description: t(
          "complianceReviewingDocs",
          "Our compliance team is reviewing your documents",
        ),
        date: t("inProgress", "In Progress"),
        completed: false,
        current: true,
      });
    }

    if (status === "approved") {
      items.push({
        id: 3,
        title: t("verificationComplete", "Verification Complete"),
        description: t(
          "kycApprovedDesc",
          "Your KYC verification has been approved",
        ),
        date: formatDate(props.kycStatus.reviewedAt),
        completed: true,
        current: false,
      });
    }

    if (status === "rejected") {
      items.push({
        id: 3,
        title: t("verificationRejected", "Verification Rejected"),
        description: t(
          "reviewFeedbackResubmit",
          "Please review the feedback and resubmit",
        ),
        date: formatDate(props.kycStatus.reviewedAt),
        completed: true,
        current: false,
      });
    }
  }

  return items;
});

// 方法
const formatDate = (dateString) => {
  if (!dateString) return "N/A";
  return new Date(dateString).toLocaleDateString("en-US", {
    year: "numeric",
    month: "long",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
};

const formatDateOnly = (dateString) => {
  if (!dateString) return "N/A";
  return new Date(dateString).toLocaleDateString("en-US", {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
};

// 计算预期完成日期（提交日期 + 2天）
const expectedCompletionDate = computed(() => {
  if (!props.kycStatus.submittedAt) return "N/A";

  const submittedDate = new Date(props.kycStatus.submittedAt);
  const expectedDate = new Date(submittedDate);
  expectedDate.setDate(submittedDate.getDate() + 2);

  return formatDateOnly(expectedDate.toISOString());
});

const navigateTo = (url) => {
  if (!url) {
    router.push("/client/kyc-verification");
    return;
  }
  if (url.startsWith("http")) {
    window.open(url, "_blank");
  } else {
    router.push(url);
  }
};

const handlePrimaryAction = async () => {
  if (clientAuthStore.isPreviewMode) {
    window.alert(PREVIEW_BLOCK_MESSAGE);
    return;
  }
  loading.value = true;
  try {
    const status = props.kycStatus.submissionStatus;

    // 特殊处理：如果是approved状态，直接跳转到开户页面
    if (status === "approved") {
      navigateTo("/client/account/new");
      return;
    }

    // 如果后端配置了actionButtonUrl，则按配置跳转
    if (statusConfig.value && statusConfig.value.actionButtonUrl) {
      // 直接跳转到配置的页面
      navigateTo(statusConfig.value.actionButtonUrl);
      return;
    }

    // 默认逻辑
    if (status === "draft") {
      const response = await clientKycService.startKycProcess();
      if (response.success) navigateTo("/client/kyc-verification");
    } else {
      navigateTo("/client/kyc-verification");
    }
  } catch (error) {
    console.error("Failed to handle primary action:", error);
    navigateTo("/client/kyc-verification");
  } finally {
    loading.value = false;
  }
};

const restartKyc = async () => {
  if (clientAuthStore.isPreviewMode) {
    window.alert(PREVIEW_BLOCK_MESSAGE);
    return;
  }
  loading.value = true;
  try {
    const response = await clientKycService.restartKycProcess();
    if (response.success) {
      emit("refresh");
      router.push("/client/kyc-verification");
    }
  } catch (error) {
    console.error("Failed to restart KYC:", error);
  } finally {
    loading.value = false;
  }
};

const saveAndExit = () => {
  // 返回到Dashboard页面
  router.push("/client/dashboard");
};

const contactSupport = () => {
  // 实现联系支持逻辑
  window.open(`mailto:${branding.value.supportEmail}`, "_blank");
};

// 加载状态消息
const loadStatusMessage = async () => {
  try {
    const status = props.kycStatus.submissionStatus;
    if (status && status !== "draft") {
      const response = await clientKycService.getStatusMessage(status);
      if (response.success) {
        statusMessage.value = response.data;
      }
    }
  } catch (error) {
    console.error("Failed to load status message:", error);
  }
};

onMounted(async () => {
  // Load branding configuration
  try {
    const config = await brandingApi.getBranding();
    branding.value = {
      supportEmail: config.supportEmail || "support.demo@gmail.com",
    };
  } catch (error) {
    console.error("Failed to load branding:", error);
  }
  loadStatusMessage();
});
</script>

<style scoped>
/* Status Card */
.status-card {
  background: white;
  border-radius: var(--radius-xl);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  padding: 40px;
  margin-bottom: 30px;
}

.status-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 30px;
  padding-bottom: 25px;
  border-bottom: 2px solid var(--color-border);
}

.status-header-left {
  display: flex;
  align-items: center;
  gap: 20px;
}

.status-icon-wrapper {
  width: 70px;
  height: 70px;
  border-radius: var(--radius-xl);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 32px;
}

.status-icon-wrapper.incomplete {
  background: linear-gradient(135deg, #fbbf24 0%, var(--color-warning) 100%);
  color: white;
}

.status-icon-wrapper.in-review {
  background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%);
  color: white;
}

.status-icon-wrapper.approved {
  background: linear-gradient(135deg, #34d399 0%, var(--color-success) 100%);
  color: white;
}

.status-icon-wrapper.rejected {
  background: linear-gradient(135deg, #f87171 0%, var(--color-danger) 100%);
  color: white;
}

.status-icon-wrapper.pending {
  background: linear-gradient(135deg, #a78bfa 0%, var(--color-accent) 100%);
  color: white;
}

.status-info h2 {
  font-size: 28px;
  color: var(--color-ink);
  margin-bottom: 8px;
}

.status-info p {
  font-size: 15px;
  color: var(--color-muted);
}

.status-badge {
  padding: 8px 16px;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  white-space: nowrap;
}

.status-badge.success {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.status-badge.warning {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.status-badge.incomplete {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.status-badge.error {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.status-badge.info {
  background: var(--color-info-soft);
  color: var(--color-info);
}

/* Status Details Grid (for incomplete status) */
.status-details-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 25px;
  margin-bottom: 35px;
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.detail-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--color-faint);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.detail-value {
  font-size: 16px;
  color: var(--color-ink);
  font-weight: 600;
}

/* Status Details */
.status-details {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 25px;
  margin-bottom: 30px;
}

.detail-card {
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: 25px;
}

.detail-header {
  margin-bottom: 20px;
}

.detail-header h3 {
  font-size: 16px;
  font-weight: 700;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 10px;
}

.detail-header h3 i {
  color: var(--color-brand);
}

/* Progress Info */
.progress-stats {
  display: flex;
  justify-content: space-between;
  margin-bottom: 20px;
}

.progress-stat {
  text-align: center;
}

.stat-number {
  display: block;
  font-size: 24px;
  font-weight: 700;
  color: var(--color-ink);
}

.stat-label {
  font-size: 12px;
  color: var(--color-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.progress-bar {
  height: 8px;
  background: var(--color-border);
  border-radius: 4px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: var(--color-brand);
  transition: width 0.3s ease;
}

/* Submission Details */
.submission-details {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.detail-label {
  font-weight: 600;
  color: var(--color-text);
}

.detail-value {
  color: var(--color-ink);
}

/* Rejection Card */
.rejection-card {
  border-left: 4px solid var(--color-danger);
  background: var(--color-danger-soft);
}

.rejection-content p {
  color: var(--color-danger);
  line-height: 1.6;
}

/* Status Message */
.status-message {
  background: var(--color-info-soft);
  border: 1px solid #0ea5e9;
  border-left: 4px solid #0ea5e9;
  border-radius: var(--radius-md);
  padding: 20px;
  margin-bottom: 30px;
}

.status-message h3 {
  font-size: 16px;
  font-weight: 700;
  color: #0c4a6e;
  margin-bottom: 10px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.status-message.success {
  background: #f0fdf4;
  border-color: var(--color-success);
}

.status-message.success h3 {
  color: var(--color-success);
}

.status-message.success h3 i {
  color: var(--color-success);
}

.status-message.error {
  border-left-color: var(--color-danger);
  background: var(--color-danger-soft);
  border-color: var(--color-danger);
}

.status-message.error h3 {
  color: var(--color-danger);
}

.status-message.error h3 i {
  color: var(--color-danger);
}

.status-message.warning {
  background: var(--color-warning-soft);
  border-color: var(--color-warning);
}

.status-message.warning h3 {
  color: var(--color-warning);
}

.status-message.warning h3 i {
  color: var(--color-warning);
}

/* Timeline */
.timeline-section {
  margin-top: 40px;
}

.timeline-title {
  font-size: 20px;
  font-weight: 700;
  color: var(--color-ink);
  margin-bottom: 25px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.timeline-title i {
  color: var(--color-brand);
}

.timeline {
  position: relative;
  padding-left: 40px;
}

.timeline::before {
  content: "";
  position: absolute;
  left: 16px;
  top: 8px;
  bottom: 8px;
  width: 2px;
  background: var(--color-border);
}

.timeline-item {
  position: relative;
  margin-bottom: 30px;
  padding-left: 25px;
}

.timeline-item:last-child {
  margin-bottom: 0;
}

.timeline-dot {
  position: absolute;
  left: -28px;
  top: 4px;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: var(--color-border-strong);
  border: 3px solid white;
  box-shadow: 0 0 0 2px var(--color-border-strong);
}

.timeline-item.completed .timeline-dot {
  background: var(--color-success);
  box-shadow: 0 0 0 2px var(--color-success);
}

.timeline-item.current .timeline-dot {
  background: var(--color-brand);
  box-shadow: 0 0 0 2px var(--color-brand);
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0%,
  100% {
    box-shadow:
      0 0 0 2px var(--color-brand),
      0 0 0 4px rgba(var(--color-brand-rgb), 0.3);
  }
  50% {
    box-shadow:
      0 0 0 2px var(--color-brand),
      0 0 0 8px rgba(var(--color-brand-rgb), 0.1);
  }
}

.timeline-content {
  background: var(--color-surface-soft);
  padding: 18px 20px;
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border);
}

.timeline-date {
  font-size: 12px;
  color: var(--color-faint);
  margin-bottom: 8px;
  font-weight: 600;
}

.timeline-title-text {
  font-size: 15px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 5px;
}

.timeline-description {
  font-size: 14px;
  color: var(--color-muted);
  line-height: 1.6;
}

/* Action Buttons */
.action-buttons {
  display: flex;
  gap: 15px;
  flex-wrap: wrap;
  margin-top: 30px;
}

.btn {
  padding: 14px 28px;
  border-radius: var(--radius-md);
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;
  display: inline-flex;
  align-items: center;
  gap: 10px;
  text-decoration: none;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-primary {
  background: var(--color-brand);
  color: white;
  box-shadow: 0 4px 15px rgba(var(--color-brand-rgb), 0.4);
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(var(--color-brand-rgb), 0.5);
}

.btn-secondary {
  background: white;
  color: var(--color-text);
  border: 2px solid var(--color-border);
}

.btn-secondary:hover {
  border-color: var(--color-brand);
  color: var(--color-brand);
  background: var(--color-surface-soft);
}

/* Responsive */
@media (max-width: 768px) {
  .status-card {
    padding: 25px 20px;
  }

  .status-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 20px;
  }

  .status-details {
    grid-template-columns: 1fr;
  }

  .action-buttons {
    flex-direction: column;
  }

  .btn {
    width: 100%;
    justify-content: center;
  }

  .progress-stats {
    flex-direction: column;
    gap: 15px;
  }
}
</style>
