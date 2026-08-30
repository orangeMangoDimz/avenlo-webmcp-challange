<template>
  <div class="ib-invitation-container">
    <div class="loading-overlay" v-if="loading">
      <div class="loading-spinner">
        <i class="fas fa-spinner fa-spin"></i>
        <p>{{ t("verifyingInvitation", "Verifying invitation...") }}</p>
      </div>
    </div>

    <div class="error-container" v-else-if="error">
      <div class="error-content">
        <i class="fas fa-exclamation-circle"></i>
        <h2>
          {{
            t("invitationVerificationFailed", "Invitation Verification Failed")
          }}
        </h2>
        <p>{{ error }}</p>
        <button class="btn btn-primary" @click="goToDashboard">
          {{ t("goToDashboard", "Go to Dashboard") }}
        </button>
      </div>
    </div>

    <div class="success-container" v-else-if="verified">
      <div class="success-content">
        <div class="success-icon-large">
          <i class="fas fa-check-circle"></i>
        </div>
        <h2>{{ t("verificationSuccessful", "Verification Successful") }}</h2>
        <p>
          {{
            t(
              "invitationVerifiedRedirect",
              "Your invitation has been verified. Redirecting to IB Dashboard...",
            )
          }}
        </p>
        <div class="redirect-spinner">
          <i class="fas fa-spinner fa-spin"></i>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useClientAuthStore } from "@/stores/clientAuth";
import { useLanguageStore } from "@/stores/language";
import ibInvitationApi from "@/services/ibInvitationApi";

const route = useRoute();
const router = useRouter();
const clientAuthStore = useClientAuthStore();
const languageStore = useLanguageStore();
const t = (key, fallback = "") => languageStore.t(key, fallback);

const loading = ref(true);
const error = ref(null);
const verified = ref(false);
const invitationCode = ref("");

// 验证邀请码
const verifyInvitationCode = async (encryptedCode) => {
  try {
    // 调用后端API解密并验证邀请码
    const response = await ibInvitationApi.verifyInvitation(encryptedCode);

    if (response.success) {
      return response.data;
    } else {
      throw new Error(response.message || "Failed to verify invitation");
    }
  } catch (err) {
    throw new Error(
      err.response?.data?.message ||
        err.message ||
        "Failed to verify invitation",
    );
  }
};

const verifyInvitation = async () => {
  try {
    loading.value = true;
    error.value = null;

    // 获取加密的邀请码
    const encryptedCode =
      route.params.encryptedCode ||
      sessionStorage.getItem("pendingIbInvitation");

    if (!encryptedCode) {
      throw new Error("Invalid invitation link");
    }

    // 验证用户是否已登录
    if (!clientAuthStore.isAuthenticated) {
      // 保存邀请码，跳转到登录页
      sessionStorage.setItem("pendingIbInvitation", encryptedCode);
      router.push({
        name: "client-login",
        query: { redirect: route.fullPath },
      });
      return;
    }

    // 清除sessionStorage中的邀请码
    sessionStorage.removeItem("pendingIbInvitation");

    // 验证邀请码（后端会检查是否匹配当前用户）
    const result = await verifyInvitationCode(encryptedCode);

    // 如果邀请已被接受，直接跳转到IB Dashboard
    if (result.alreadyAccepted) {
      router.push({ name: "client-ib-dashboard" });
      return;
    }

    // 验证邀请是否属于当前用户（已在后端验证）
    // 验证成功后跳转到IB Dashboard（不自动接受邀请）
    invitationCode.value = result.invitationCode;
    verified.value = true;

    // 刷新用户信息以更新IB状态（邀请已标记为viewed，菜单项应该显示）
    await clientAuthStore.fetchUser();

    // 验证成功后立即跳转到IB Dashboard（1秒后跳转，给用户看到成功提示）
    setTimeout(() => {
      router.push({ name: "client-ib-dashboard" });
    }, 1000);
  } catch (err) {
    error.value = err.message || "Failed to verify invitation";
    console.error("Invitation verification error:", err);

    // 如果是邀请已接受或其他错误，跳转到首页
    if (
      err.message &&
      (err.message.includes("already accepted") ||
        err.message.includes("not for your account") ||
        err.message.includes("expired") ||
        err.message.includes("declined"))
    ) {
      setTimeout(() => {
        router.push({ name: "client-dashboard" });
      }, 2000);
    }
  } finally {
    loading.value = false;
  }
};

const goToDashboard = () => {
  router.push({ name: "client-dashboard" });
};

onMounted(async () => {
  if (!languageStore.enabledLanguages.length) {
    await languageStore.initLanguage();
  }
  verifyInvitation();
});
</script>

<style scoped>
.ib-invitation-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-brand);
  padding: 20px;
}

.loading-overlay {
  text-align: center;
  color: white;
}

.loading-spinner {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 20px;
}

.loading-spinner i {
  font-size: 48px;
  color: white;
}

.loading-spinner p {
  font-size: 18px;
  color: white;
}

.error-container,
.success-container {
  background: white;
  border-radius: var(--radius-lg);
  padding: 40px;
  max-width: 600px;
  width: 100%;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
}

.error-content,
.success-content {
  text-align: center;
}

.error-content i {
  font-size: 64px;
  color: var(--color-danger);
  margin-bottom: 20px;
}

.success-content i {
  font-size: 64px;
  color: var(--color-success);
  margin-bottom: 20px;
}

.error-content h2,
.success-content h2 {
  font-size: 24px;
  color: var(--color-ink);
  margin-bottom: 15px;
}

.error-content p,
.success-content p {
  font-size: 16px;
  color: var(--color-muted);
  margin-bottom: 30px;
}

.success-icon-large {
  font-size: 80px;
  color: var(--color-success);
  margin-bottom: 20px;
  animation: scaleIn 0.5s ease;
}

@keyframes scaleIn {
  from {
    transform: scale(0);
    opacity: 0;
  }
  to {
    transform: scale(1);
    opacity: 1;
  }
}

.redirect-spinner {
  margin-top: 20px;
  font-size: 24px;
  color: var(--color-brand);
}

.btn {
  padding: 12px 24px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-primary {
  background: var(--color-brand);
  color: white;
}

.btn-primary:hover {
  background: var(--color-brand-strong);
}
</style>
