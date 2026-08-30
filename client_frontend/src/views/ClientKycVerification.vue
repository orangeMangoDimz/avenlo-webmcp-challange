<template>
  <div class="kyc-verification-page ui-page">
    <!-- approved：成功页 -->
    <KycSuccessModal
      v-if="kycPhase === 'approved'"
      variant="approved"
      @close="goToDashboard"
    />

    <!-- pending / under_review / submitted / in_review：等待审核
         刚提交完（justSubmittedInWebView）时强制传 under_review，避免 store 还停留在
         resubmit_required / draft 等旧值，被 KycRequiredNotice 误判成红色 "Action Required" -->
    <KycRequiredNotice
      v-else-if="kycPhase === 'pending'"
      :status="justSubmittedInWebView ? 'under_review' : null"
    />

    <!-- rejected：仅 webview（/app/kyc）显示"提示 + Start a New One 按钮"。
         正常 /client/kyc-verification 下沿用原行为 —— 走 KycVerification 内部由 KycStatusCard 接管 rejected 展示。 -->
    <KycRequiredNotice v-else-if="isWebView && kycPhase === 'rejected'">
      <button class="restart-btn" :disabled="restarting" @click="handleRestart">
        <i class="fas fa-redo"></i>
        {{
          restarting
            ? t("restartingKyc", "Starting...")
            : t("startNewKyc", "Start a New One")
        }}
      </button>
    </KycRequiredNotice>

    <!-- 其它（无记录 / draft / resubmit_required / incomplete）：进表单 -->
    <KycVerification v-else @submitted="handleSubmitted" @close="handleClose" />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useClientAuthStore } from "@/stores/clientAuth";
import { useLanguageStore } from "@/stores/language";
import { clientKycService } from "@/services/clientKycService";
import KycVerification from "@/components/client/KycVerification.vue";
import KycRequiredNotice from "@/components/client/KycRequiredNotice.vue";
import KycSuccessModal from "@/components/client/KycSuccessModal.vue";

const route = useRoute();
const router = useRouter();
const clientAuthStore = useClientAuthStore();
const languageStore = useLanguageStore();
const t = (key, fallback = "") => languageStore.t(key, fallback);

// webview 模式（/app/kyc）：宿主 App 自行决定提交/关闭后的跳转，这里不再跳 dashboard
const isWebView = computed(() => Boolean(route.meta?.isWebView));
const isKycApproved = computed(() => clientAuthStore.isKycApproved);

// webview 模式下本次会话刚提交完 KYC 的标记 —— 提交成功后直接切到 pending 文案
const justSubmittedInWebView = ref(false);

// 当前 KYC 状态：优先用最新一条 submission 的状态，回退到 user.kycStatus
const kycSubmissionStatus = computed(() => {
  const submission = clientAuthStore.kycStatus?.submissionStatus;
  if (submission) return String(submission).toLowerCase();
  const userStatus = clientAuthStore.user?.kycStatus;
  if (userStatus) return String(userStatus).toLowerCase();
  return "";
});

// 把状态拍平成路由用的几个 phase，模板按 phase 渲染对应区块
const kycPhase = computed(() => {
  if (isKycApproved.value) return "approved";
  if (justSubmittedInWebView.value) return "pending";
  const status = kycSubmissionStatus.value;
  if (["pending", "under_review", "submitted", "in_review"].includes(status))
    return "pending";
  if (status === "rejected") return "rejected";
  // 无记录 / draft / resubmit_required / incomplete 都走表单（KycVerification 内部按 status 分流）
  return "form";
});

// 进入页面时拉一次最新用户信息，避免 store 里的 KYC 状态滞后（例如刚审批通过的情况）
onMounted(async () => {
  if (clientAuthStore.token) {
    await clientAuthStore.fetchUser();
  }
});

// 提交成功后：
// - webview 模式（/app/kyc）：标记 justSubmittedInWebView，模板立刻切到 pending 文案
// - 正常模式：什么都不做，由 close 事件触发 handleClose 跳 dashboard
const handleSubmitted = () => {
  if (!isWebView.value) return;
  justSubmittedInWebView.value = true;
};

const handleClose = () => {
  // webview 模式下，成功提交也会 emit close —— 不能跳 dashboard，让 handleSubmitted 接管
  if (isWebView.value) return;
  router.push("/client/dashboard");
};

const goToDashboard = () => {
  router.push("/client/dashboard");
};

// rejected 状态下的 "Start a New One"：调后端 restart 清掉旧 submission，再刷一次 user
// state 跟着翻成 'form'，模板自动渲染 KycVerification（无 submission → createNewSubmission 走起）
const restarting = ref(false);
const handleRestart = async () => {
  if (restarting.value) return;
  restarting.value = true;
  try {
    const res = await clientKycService.restartKycProcess();
    if (res?.success) {
      await clientAuthStore.fetchUser();
    }
  } catch (err) {
    console.error("Failed to restart KYC:", err);
  } finally {
    restarting.value = false;
  }
};
</script>

<style scoped>
/* 不再写 min-height:100vh —— ClientLayout 的 .client-layout 已经撑满视口、
 * 背景色一致，这里再加 100vh 会让页面比可用区域高出一截，第三方 KYC 模式下会
 * 在卡片底下露出一片空灰。 */
.kyc-verification-page {
  background: var(--color-canvas);
}

.restart-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px 24px;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-brand-solid);
  color: #fff;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease;
}

.restart-btn:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 6px 16px rgba(var(--color-brand-rgb), 0.3);
}

.restart-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
