<template>
  <div class="app-kyc-required">
    <div class="content-card">
      <div class="status-icon" :class="iconWrapperClass">
        <i :class="iconName"></i>
      </div>
      <h2 class="title">{{ title }}</h2>
      <p class="message">{{ message }}</p>
      <!-- 调用方可在 message 下方塞按钮（比如 rejected 状态的 "Start a New One"） -->
      <div v-if="$slots.default" class="actions">
        <slot />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { useClientAuthStore } from "@/stores/clientAuth";
import { useLanguageStore } from "@/stores/language";

const props = defineProps({
  // 可选：强制覆盖状态判断。用于"用户刚操作完，store 状态可能滞后"的场景，
  // 比如 resubmit 提交成功之后 store 还显示 resubmit_required，需要强制按 'under_review' 渲染
  status: {
    type: String,
    default: null,
  },
  // gate 模式：业务页门面用（deposit/withdraw/transfer/openaccount 未通过 KYC 时挡在外面）
  // 只告诉用户"这个功能需要 KYC"，不暴露其具体 KYC 状态。永远走默认的红色 "Verification Required"
  gate: {
    type: Boolean,
    default: false,
  },
});

const clientAuthStore = useClientAuthStore();
const languageStore = useLanguageStore();
const t = (key, fallback = "") => languageStore.t(key, fallback);

// 解析当前 KYC 状态：props 优先，再看 store 上 submissionStatus，最后回退到 user.kycStatus
const status = computed(() => {
  if (props.status) return props.status.toLowerCase();
  const submission = clientAuthStore.kycStatus?.submissionStatus;
  if (submission) return String(submission).toLowerCase();
  const userStatus = clientAuthStore.user?.kycStatus;
  if (userStatus) return String(userStatus).toLowerCase();
  return "";
});

// 等待审核中：用户已提交，无需操作，等结果即可
// gate 模式下永远不识别为 pending/rejected，直接落到默认的"需要 KYC"提示
const isPendingReview = computed(
  () =>
    !props.gate &&
    ["under_review", "pending", "submitted", "in_review"].includes(
      status.value,
    ),
);

// 被拒：需要用户重新走一次 KYC
const isRejected = computed(() => !props.gate && status.value === "rejected");

const iconName = computed(() => {
  if (isPendingReview.value) return "fas fa-hourglass-half";
  return "fas fa-exclamation-circle";
});

const iconWrapperClass = computed(() => {
  if (isPendingReview.value) return "icon-pending";
  return "icon-warning";
});

const title = computed(() => {
  if (isPendingReview.value) return t("kycReviewingTitle", "KYC Under Review");
  if (isRejected.value)
    return t("kycActionRequiredTitle", "KYC Action Required");
  // 未开始 / 无记录 / 要求补充：统一显示"需要完成 KYC"
  return t("kycRequiredTitle", "KYC Verification Required");
});

const message = computed(() => {
  if (isPendingReview.value) {
    return t(
      "kycReviewingMsg",
      "Your KYC application is currently under review. Please wait until your verification is approved before performing this operation.",
    );
  }
  if (isRejected.value) {
    return t(
      "kycRejectedMsg",
      "Your KYC application was rejected. Please start a new submission to continue.",
    );
  }
  return t(
    "kycRequiredMsg",
    "Please complete KYC verification before performing this operation.",
  );
});
</script>

<style scoped>
.app-kyc-required {
  min-height: 100vh;
  background: var(--color-canvas);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px 16px;
}

.content-card {
  width: 100%;
  max-width: 480px;
  background: var(--color-surface);
  border-radius: var(--radius-xl);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
  padding: 36px 28px;
  text-align: center;
}

.status-icon {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  margin: 0 auto 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 32px;
}

.status-icon.icon-pending {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.status-icon.icon-warning {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.title {
  font-size: 20px;
  font-weight: 600;
  color: var(--color-ink);
  margin: 0 0 12px;
}

.message {
  font-size: 14px;
  color: var(--color-text);
  line-height: 1.6;
  margin: 0;
}

.actions {
  margin-top: 24px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  align-items: stretch;
}

@media (max-width: 768px) {
  .content-card {
    padding: 28px 20px;
  }

  .status-icon {
    width: 60px;
    height: 60px;
    font-size: 26px;
    margin-bottom: 16px;
  }

  .title {
    font-size: 17px;
  }

  .message {
    font-size: 14px;
  }
}
</style>
