<template>
  <div class="ib-status-card">
    <div v-if="loading" class="loading-container">
      <i class="fas fa-spinner fa-spin"></i>
      <p>{{ t("ibStatusLoading", "Loading IB Application Status...") }}</p>
    </div>

    <div v-else-if="error" class="error-container">
      <i class="fas fa-exclamation-circle"></i>
      <p>{{ error }}</p>
      <button class="btn btn-primary" @click="loadStatus">
        {{ t("ibStatusRetry", "Retry") }}
      </button>
    </div>

    <div v-else-if="!ibStatus.hasApplication" class="no-application">
      <div class="no-application-content">
        <i class="fas fa-file-alt"></i>
        <h2>{{ t("ibStatusNoApplication", "No IB Application Found") }}</h2>
        <p>
          {{
            t(
              "ibStatusNoApplicationDesc",
              "You haven't submitted an IB application yet.",
            )
          }}
        </p>
      </div>
    </div>

    <div v-else class="status-card">
      <!-- Status Header -->
      <div class="status-header">
        <div class="status-header-left">
          <div class="status-icon-wrapper" :class="statusConfig.iconClass">
            <i :class="statusConfig.icon"></i>
          </div>
          <div class="status-info">
            <h2>{{ statusConfig.title }}</h2>
            <p>{{ statusConfig.description }}</p>
          </div>
        </div>
        <div class="status-badge" :class="statusConfig.badgeClass">
          {{ statusConfig.badgeText }}
        </div>
      </div>

      <!-- Status Message -->
      <div
        v-if="statusMessage"
        class="status-message"
        :class="statusMessage.messageType"
      >
        <h3><i :class="statusMessage.icon"></i> {{ statusMessage.title }}</h3>
        <div v-html="statusConfig.description"></div>
      </div>

      <!-- Status Details -->
      <div class="status-details-grid">
        <div class="detail-item">
          <div class="detail-label">
            {{ t("ibStatusApplicationStatus", "Application Status") }}
          </div>
          <div class="detail-value">{{ statusConfig.badgeText }}</div>
        </div>
        <div class="detail-item">
          <div class="detail-label">
            {{ t("ibStatusApplicationDate", "Application Date") }}
          </div>
          <div class="detail-value">
            {{ formatDate(ibStatus.application?.applicationDate) }}
          </div>
        </div>
        <div v-if="ibStatus.application?.reviewStartDate" class="detail-item">
          <div class="detail-label">
            {{ t("ibStatusReviewStarted", "Review Started") }}
          </div>
          <div class="detail-value">
            {{ formatDate(ibStatus.application.reviewStartDate) }}
          </div>
        </div>
        <div
          v-if="ibStatus.application?.reviewCompletedDate"
          class="detail-item"
        >
          <div class="detail-label">
            {{ t("ibStatusReviewCompleted", "Review Completed") }}
          </div>
          <div class="detail-value">
            {{ formatDate(ibStatus.application.reviewCompletedDate) }}
          </div>
        </div>
        <div v-if="ibStatus.application?.id" class="detail-item">
          <div class="detail-label">
            {{ t("ibStatusApplicationId", "Application ID") }}
          </div>
          <div class="detail-value">#{{ ibStatus.application.id }}</div>
        </div>
      </div>

      <!-- Rejection Reason -->
      <div
        v-if="
          ibStatus.applicationStatus === 'rejected' &&
          ibStatus.application?.rejectionReason
        "
        class="rejection-card"
      >
        <div class="rejection-content">
          <h3>
            <i class="fas fa-times-circle"></i>
            {{ t("ibStatusRejectionReason", "Rejection Reason") }}
          </h3>
          <p>{{ ibStatus.application.rejectionReason }}</p>
        </div>
      </div>

      <!-- More Info Request -->
      <div
        v-if="
          ibStatus.applicationStatus === 'more_info_requested' &&
          ibStatus.application?.additionalInfoRequest
        "
        class="status-message warning"
      >
        <h3>
          <i class="fas fa-info-circle"></i>
          {{
            t(
              "ibStatusAdditionalInfoRequired",
              "Additional Information Required",
            )
          }}
        </h3>
        <p>{{ ibStatus.application.additionalInfoRequest }}</p>
      </div>

      <!-- Timeline -->
      <div
        v-if="ibStatus.timeline && ibStatus.timeline.length > 0"
        class="timeline-section"
      >
        <div class="timeline-title">
          <i class="fas fa-history"></i>
          {{ t("ibStatusApplicationTimeline", "Application Timeline") }}
        </div>
        <div class="timeline">
          <div
            v-for="item in ibStatus.timeline"
            :key="item.id"
            class="timeline-item"
            :class="{ completed: item.completed, current: item.current }"
          >
            <div class="timeline-dot"></div>
            <div class="timeline-content">
              <div class="timeline-date">
                {{
                  item.date
                    ? formatDate(item.date)
                    : t("ibStatusPending", "Pending")
                }}
              </div>
              <div class="timeline-title-text">{{ item.title }}</div>
              <div class="timeline-description">{{ item.description }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useLanguageStore } from "@/stores/language";
import ibApplicationApi from "@/services/ibApplicationApi";

const languageStore = useLanguageStore();
const t = (key, fallback) => languageStore.t(key, fallback);

const props = defineProps({
  autoLoad: {
    type: Boolean,
    default: true,
  },
});

const emit = defineEmits(["status-loaded"]);

const loading = ref(false);
const error = ref(null);
const ibStatus = ref({
  hasApplication: false,
  applicationStatus: null,
  application: null,
  timeline: [],
});

// 状态配置（使用 t 在 computed 中会循环依赖，这里在模板里用 t 显示；badgeText 等需要从 store 取翻译，用 getter）
function getStatusConfigs(t) {
  return {
    pending: {
      title: t("ibStatusAppPending", "Application Pending"),
      description: t(
        "ibStatusAppPendingDesc",
        "Your IB application is waiting to be reviewed by our team.",
      ),
      icon: "fas fa-hourglass-half",
      iconClass: "pending",
      badgeText: t("ibStatusPending", "Pending"),
      badgeClass: "info",
    },
    in_review: {
      title: t("ibStatusInReview", "Under Review"),
      description: t(
        "ibStatusInReviewDesc",
        "Our team is currently reviewing your IB application.",
      ),
      icon: "fas fa-search",
      iconClass: "in-review",
      badgeText: t("ibStatusInReview", "In Review"),
      badgeClass: "info",
    },
    approved: {
      title: t("ibStatusApproved", "Application Approved"),
      description: t(
        "ibStatusApprovedDesc",
        "Congratulations! Your IB application has been approved.",
      ),
      icon: "fas fa-check-circle",
      iconClass: "approved",
      badgeText: t("ibStatusApproved", "Approved"),
      badgeClass: "success",
    },
    rejected: {
      title: t("ibStatusAppRejected", "Application Rejected"),
      description: t(
        "ibStatusRejectedDesc",
        "Your IB application has been rejected. Please review the feedback.",
      ),
      icon: "fas fa-times-circle",
      iconClass: "rejected",
      badgeText: t("ibStatusRejected", "Rejected"),
      badgeClass: "error",
    },
    more_info_requested: {
      title: t("ibStatusAdditionalInfoRequired", "More Information Required"),
      description: t(
        "ibStatusMoreInfoDesc",
        "We need additional information to process your application.",
      ),
      icon: "fas fa-info-circle",
      iconClass: "warning",
      badgeText: t("ibStatusInfoRequired", "Info Required"),
      badgeClass: "warning",
    },
  };
}

const statusConfig = computed(() => {
  const status = ibStatus.value.applicationStatus;
  const configs = getStatusConfigs(t);
  return configs[status] || configs.pending;
});

const statusMessage = computed(() => {
  const status = ibStatus.value.applicationStatus;
  if (status === "approved") {
    return {
      title: t("ibStatusCongratulations", "Congratulations!"),
      messageType: "success",
      icon: "fas fa-check-circle",
    };
  } else if (status === "rejected") {
    return {
      title: t("ibStatusAppRejected", "Application Rejected"),
      messageType: "error",
      icon: "fas fa-times-circle",
    };
  } else if (status === "more_info_requested") {
    return {
      title: t(
        "ibStatusAdditionalInfoRequired",
        "Additional Information Required",
      ),
      messageType: "warning",
      icon: "fas fa-info-circle",
    };
  }
  return null;
});

const formatDate = (dateString) => {
  if (!dateString) return "N/A";
  try {
    const date = new Date(dateString);
    return date.toLocaleDateString("en-US", {
      year: "numeric",
      month: "long",
      day: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });
  } catch (e) {
    return dateString;
  }
};

const loadStatus = async () => {
  loading.value = true;
  error.value = null;
  try {
    const response = await ibApplicationApi.getMyStatus();
    ibStatus.value = response.data || response;
    emit("status-loaded", ibStatus.value);
  } catch (err) {
    error.value =
      err.response?.data?.message ||
      err.message ||
      "Failed to load IB application status";
  } finally {
    loading.value = false;
  }
};

// 暴露方法供父组件调用
defineExpose({
  loadStatus,
});

onMounted(() => {
  if (props.autoLoad) {
    loadStatus();
  }
});
</script>

<style scoped>
.ib-status-card {
  width: 100%;
}

.loading-container,
.error-container {
  text-align: center;
  padding: 60px 20px;
}

.loading-container i {
  font-size: 48px;
  color: var(--color-brand);
  margin-bottom: 20px;
}

.error-container i {
  font-size: 48px;
  color: var(--color-danger);
  margin-bottom: 20px;
}

.no-application {
  background: var(--color-surface);
  border-radius: var(--radius-xl);
  padding: 60px 40px;
  text-align: center;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.no-application-content i {
  font-size: 64px;
  color: var(--color-border-strong);
  margin-bottom: 20px;
}

.no-application-content h2 {
  font-size: 24px;
  color: var(--color-ink);
  margin-bottom: 10px;
}

.no-application-content p {
  color: var(--color-muted);
  margin-bottom: 30px;
}

.status-card {
  background: var(--color-surface);
  border-radius: var(--radius-xl);
  padding: 40px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

/* Status Header */
.status-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 30px;
  padding-bottom: 30px;
  border-bottom: 1px solid var(--color-border);
}

.status-header-left {
  display: flex;
  align-items: flex-start;
  gap: 20px;
}

.status-icon-wrapper {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 36px;
  flex-shrink: 0;
}

.status-icon-wrapper.pending {
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.status-icon-wrapper.in-review {
  background: var(--color-info-soft);
  color: var(--color-brand);
}

.status-icon-wrapper.approved {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.status-icon-wrapper.rejected {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.status-icon-wrapper.warning {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.status-info h2 {
  font-size: 24px;
  color: var(--color-ink);
  margin-bottom: 8px;
  font-weight: 700;
}

.status-info p {
  color: var(--color-muted);
  font-size: 15px;
  line-height: 1.6;
}

.status-badge {
  padding: 8px 16px;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.status-badge.info {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.status-badge.success {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.status-badge.error {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.status-badge.warning {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

/* Status Message */
.status-message {
  background: var(--color-info-soft);
  border: 1px solid var(--color-border);
  border-left: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 20px;
  margin-bottom: 30px;
}

.status-message h3 {
  font-size: 16px;
  font-weight: 700;
  color: var(--color-info);
  margin-bottom: 10px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.status-message.success {
  background: var(--color-success-soft);
  border-color: var(--color-success);
}

.status-message.success h3 {
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

.status-message.warning {
  background: var(--color-warning-soft);
  border-color: var(--color-warning);
}

.status-message.warning h3 {
  color: var(--color-warning);
}

/* Status Details Grid */
.status-details-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 20px;
  margin-bottom: 30px;
}

.detail-item {
  background: var(--color-surface-soft);
  padding: 18px;
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border);
}

.detail-label {
  font-size: 12px;
  color: var(--color-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 8px;
  font-weight: 600;
}

.detail-value {
  font-size: 16px;
  color: var(--color-ink);
  font-weight: 600;
}

/* Rejection Card */
.rejection-card {
  border-left: 1px solid var(--color-danger);
  background: var(--color-danger-soft);
  border-radius: var(--radius-md);
  padding: 20px;
  margin-bottom: 30px;
}

.rejection-content h3 {
  font-size: 16px;
  font-weight: 700;
  color: var(--color-danger);
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.rejection-content p {
  color: var(--color-danger);
  line-height: 1.6;
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
  border: 1px solid white;
  box-shadow: none;
}

.timeline-item.completed .timeline-dot {
  background: var(--color-success-solid);
  box-shadow: none;
}

.timeline-item.current .timeline-dot {
  background: var(--color-brand-solid);
  box-shadow: none;
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0%,
  100% {
    box-shadow: none;
  }
  50% {
    box-shadow: none;
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

/* Buttons */
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

.btn-primary {
  background: var(--color-brand-solid);
  color: white;
  box-shadow: 0 4px 15px rgba(var(--color-brand-rgb), 0.4);
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(var(--color-brand-rgb), 0.5);
}

@media (max-width: 768px) {
  .status-card {
    padding: 30px 20px;
  }

  .status-header {
    flex-direction: column;
    gap: 20px;
  }

  .status-details-grid {
    grid-template-columns: 1fr;
  }
}
</style>
