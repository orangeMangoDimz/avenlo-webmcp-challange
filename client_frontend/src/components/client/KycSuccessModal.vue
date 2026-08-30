<template>
  <!-- variant=submitted：modal 浮在表单上方，rgba 背景遮住表单
       variant=approved：独立页面（/app/kyc 已通过），下面没东西，不要 dim，按 KycRequiredNotice 那种纯卡片样式 -->
  <div
    class="modal-overlay show"
    :class="{ 'plain-bg': variant === 'approved' }"
  >
    <div class="success-modal">
      <div class="success-icon">
        <i class="fas fa-check"></i>
      </div>
      <!-- approved 版本：用户落地时就已通过，无需提交流程；submitted 版本：刚提交，等审核 -->
      <template v-if="variant === 'approved'">
        <h2>
          {{ t("kycAlreadyApprovedTitle", "Identity Verification Complete") }}
        </h2>
        <p>
          {{
            t(
              "kycAlreadyApprovedMsg",
              "Your KYC verification has already been approved. No further action is needed.",
            )
          }}
        </p>
      </template>
      <template v-else>
        <h2>{{ t("kycSuccessTitle", "Application Submitted!") }}</h2>
        <p>
          {{
            t(
              "kycSuccessThankYou",
              "Thank you for completing your KYC verification. Your application has been successfully submitted and is now under review.",
            )
          }}
        </p>
        <p>
          {{
            t(
              "kycSuccessNotify",
              "We will notify you via email once the review is complete. This typically takes 1-2 business days.",
            )
          }}
        </p>
      </template>
      <!-- 非 webview 模式：保留原有的"X 秒后跳 dashboard"倒计时和按钮 -->
      <template v-if="!isWebView">
        <div class="countdown-text">
          {{ t("kycSuccessRedirect", "Redirecting to dashboard in") }}
          <span class="countdown-number">{{ countdown }}</span>
          {{ t("kycSuccessSeconds", "seconds...") }}
        </div>
        <button class="btn btn-primary" @click="handleClose">
          <i class="fas fa-home"></i>
          {{ t("kycSuccessGoDashboard", "Go to Dashboard Now") }}
        </button>
      </template>
      <!-- webview 模式：宿主 App 自己控制后续动作，这里不自动跳转、不显示 dashboard 按钮 -->
    </div>
  </div>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from "vue";
import { useRoute } from "vue-router";
import { useLanguageStore } from "@/stores/language";

defineProps({
  // 'submitted'：刚提交完，等审核；'approved'：用户已通过，本次只是落地提示
  variant: {
    type: String,
    default: "submitted",
    validator: (v) => ["submitted", "approved"].includes(v),
  },
});

const emit = defineEmits(["close"]);
const route = useRoute();
const languageStore = useLanguageStore();
const t = (key, fallback) => languageStore.t(key, fallback);

const isWebView = computed(() => Boolean(route.meta?.isWebView));

const countdown = ref(5);
let timer = null;

// 通知父组件关闭。webview 模式下不再调用，所以不会触发跳转
const handleClose = () => {
  if (timer) clearInterval(timer);
  emit("close");
};

onMounted(() => {
  // webview 模式下不启动倒计时，modal 由宿主 App 自行处理（不会自动消失/跳转）
  if (isWebView.value) return;

  timer = setInterval(() => {
    countdown.value--;
    if (countdown.value <= 0) {
      handleClose();
    }
  }, 1000);
});

onUnmounted(() => {
  if (timer) clearInterval(timer);
});
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  display: none;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-overlay.show {
  display: flex;
}

/* approved 模式：独立页面，不 dim 背景，露出 .kyc-verification-page 的浅灰底色 */
.modal-overlay.plain-bg {
  background: var(--color-canvas);
}

/* 卡片尺寸 / 排版与 KycRequiredNotice 保持一致，pending / rejected / success 视觉统一 */
.success-modal {
  background: var(--color-surface);
  border-radius: var(--radius-xl);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
  padding: 36px 28px;
  max-width: 480px;
  width: 100%;
  text-align: center;
  animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
  from {
    opacity: 0;
    transform: translateY(-20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* 与 KycRequiredNotice .status-icon 同尺寸（72x72），保留绿色渐变体现"成功"语义 */
.success-icon {
  width: 72px;
  height: 72px;
  background: linear-gradient(
    135deg,
    var(--color-success-solid) 0%,
    var(--color-success-solid) 100%
  );
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 20px;
  animation: scaleIn 0.5s ease;
}

@keyframes scaleIn {
  0% {
    transform: scale(0);
  }
  50% {
    transform: scale(1.1);
  }
  100% {
    transform: scale(1);
  }
}

.success-icon i {
  font-size: 32px;
  color: white;
}

.success-modal h2 {
  font-size: 20px;
  font-weight: 600;
  color: var(--color-ink);
  margin: 0 0 12px;
}

.success-modal p {
  font-size: 14px;
  color: var(--color-text);
  line-height: 1.6;
  margin: 0 0 12px;
}

.countdown-text {
  font-size: 14px;
  color: var(--color-faint);
  margin: 20px 0;
}

.countdown-number {
  font-weight: 700;
  color: var(--color-brand);
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

/* 与 KycRequiredNotice 一致的小屏适配 */
@media (max-width: 768px) {
  .modal-overlay {
    padding: 24px 16px;
  }

  .success-modal {
    padding: 28px 20px;
  }

  .success-icon {
    width: 60px;
    height: 60px;
    margin-bottom: 16px;
  }

  .success-icon i {
    font-size: 26px;
  }

  .success-modal h2 {
    font-size: 17px;
  }

  .success-modal p {
    font-size: 13px;
  }
}
</style>
