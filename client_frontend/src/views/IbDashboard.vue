<template>
  <div class="ib-dashboard-container">
    <div class="ib-prompt-container">
      <!-- 初次加载中：不显示“同意成为IB”或状态卡，避免闪一下 -->
      <div v-if="initialLoad" class="ib-dashboard-loading">
        <i class="fas fa-spinner fa-spin ib-dashboard-loading__icon"></i>
        <p class="ib-dashboard-loading__text">
          {{ t("ibStatusLoading", "Loading IB Application Status...") }}
        </p>
      </div>

      <!-- IB Status Card (if application exists) -->
      <template v-else-if="hasApplication">
        <IbStatusCard
          ref="statusCardRef"
          :auto-load="true"
          @status-loaded="onStatusLoaded"
        />
      </template>

      <!-- IB Locked Banner (if no application) -->
      <div v-else class="ib-locked-banner">
        <div class="ib-locked-content">
          <div class="lock-icon-large">
            <i class="fas fa-lock"></i>
          </div>
          <h1>{{ t("notIbYet", "You're Not an IB Yet") }}</h1>
          <p>
            {{
              t(
                "ibJoinPrompt",
                "Join our Introducing Broker program and start earning commissions by referring clients to our platform. Build your network and enjoy competitive commission rates!",
              )
            }}
          </p>

          <!-- Required Documents Agreement -->
          <div v-if="requiredDocuments.length > 0" class="terms-text">
            <p>
              {{ t("iHaveReadAndAgree", "I have read and agree to the") }}
              <template v-for="(doc, index) in requiredDocuments" :key="doc.id">
                <a href="#" @click.prevent="openDocument(doc)">
                  {{ doc.documentTitle }}
                </a>
                <template v-if="index < requiredDocuments.length - 1"
                  >,
                </template>
                <template v-else-if="index === requiredDocuments.length - 2">
                  {{ t("and", "and") }}
                </template>
              </template>
            </p>
          </div>

          <button
            class="btn btn-white btn-large"
            @click="handleAgree"
            :disabled="loading"
          >
            <i class="fas fa-handshake"></i>
            {{
              loading
                ? t("processing", "Processing...")
                : t("agreeToBecomeIb", "Agree to Become an IB")
            }}
          </button>
        </div>
      </div>
    </div>

    <!-- Document Modal -->
    <div
      v-if="activeDocument"
      class="modal legal-modal"
      @click.self="closeDocument"
    >
      <div class="modal-content legal-content">
        <div class="modal-header">
          <span class="close" @click="closeDocument">&times;</span>
          <h2>{{ activeDocument.documentTitle }}</h2>
        </div>
        <div class="modal-body legal-body">
          <div
            class="legal-document-content"
            v-html="activeDocument.documentContent"
          ></div>
          <div class="legal-footer">
            <button type="button" class="modal-button" @click="closeDocument">
              {{ t("close", "Close") }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useRouter } from "vue-router";
import { useClientAuthStore } from "@/stores/clientAuth";
import { useLanguageStore } from "@/stores/language";
import ibDocumentApi from "@/services/ibDocumentApi";
import ibApplicationApi from "@/services/ibApplicationApi";
import IbStatusCard from "@/components/client/IbStatusCard.vue";

const router = useRouter();
const clientAuthStore = useClientAuthStore();
const languageStore = useLanguageStore();
const t = (key, fallback = "") => languageStore.t(key, fallback);

const requiredDocuments = ref([]);
const activeDocument = ref(null);
const loading = ref(false);
const hasApplication = ref(false);
const initialLoad = ref(true);
const statusCardRef = ref(null);
const invitationSelectedDocuments = ref(null); // 邀请中选中的文档ID列表

// 加载必需的IB文档
const loadRequiredDocuments = async () => {
  try {
    // 先获取邀请信息，看是否有选中的文档
    let selectedDocumentIds = null;
    try {
      const ibInvitationApi = (await import("@/services/ibInvitationApi"))
        .default;
      const statusResponse = await ibInvitationApi.getMyStatus();
      if (
        statusResponse.success &&
        statusResponse.data?.invitation?.selectedDocuments
      ) {
        selectedDocumentIds = statusResponse.data.invitation.selectedDocuments;
        invitationSelectedDocuments.value = selectedDocumentIds;
      }
    } catch (error) {
      console.log("No invitation found or failed to load invitation:", error);
    }

    // 获取所有文档
    const response = await ibDocumentApi.getRequiredDocuments();
    const documents = response.data || response;
    let allDocuments = [];

    if (Array.isArray(documents)) {
      allDocuments = documents;
    } else if (documents.items) {
      allDocuments = documents.items;
    }

    // 如果有邀请中选中的文档，只显示选中的文档
    // 否则显示所有必需的文档（isRequired = 1）
    if (
      selectedDocumentIds &&
      Array.isArray(selectedDocumentIds) &&
      selectedDocumentIds.length > 0
    ) {
      // 根据邀请中选中的文档ID过滤
      requiredDocuments.value = allDocuments
        .filter((doc) => selectedDocumentIds.includes(doc.id))
        .sort((a, b) => (a.displayOrder || 0) - (b.displayOrder || 0));
    } else {
      // 没有邀请或没有选中文档，显示所有必需的文档
      requiredDocuments.value = allDocuments
        .filter((doc) => doc.isRequired === 1 || doc.isRequired === true)
        .sort((a, b) => (a.displayOrder || 0) - (b.displayOrder || 0));
    }
  } catch (error) {
    console.error("Failed to load required documents:", error);
  }
};

// 打开文档弹窗
const openDocument = (document) => {
  activeDocument.value = document;
};

// 关闭文档弹窗
const closeDocument = () => {
  activeDocument.value = null;
};

// 处理同意按钮
const handleAgree = async () => {
  if (!clientAuthStore.user) {
    alert("Please login first");
    return;
  }

  loading.value = true;
  try {
    // 创建IB申请
    const user = clientAuthStore.user;
    const fullName =
      user.fullName ||
      (user.firstName && user.lastName
        ? `${user.firstName} ${user.lastName}`
        : null) ||
      user.email;

    // 页面上展示的全部文档均写入 ibPartnerDocumentAcknowledgements（与邀请勾选列表一致）
    const documentIds = requiredDocuments.value
      .map((doc) => doc.id)
      .filter((id) => id !== undefined && id !== null);

    const applicationData = {
      clientId: user.id,
      applicantName: fullName,
      applicantEmail: user.email,
      applicantPhone: user.phone || "",
      ibType: "individual", // 默认个人，后续可以修改
      applicationStatus: "pending",
      documentIds: documentIds, // 传递文档ID列表
    };

    const response = await ibApplicationApi.createApplication(applicationData);

    if (response.success || response.data) {
      // 刷新用户信息以更新IB状态
      await clientAuthStore.fetchUser();

      // 标记为已有申请，显示状态卡片
      hasApplication.value = true;

      // 刷新状态卡片
      if (statusCardRef.value) {
        await statusCardRef.value.loadStatus();
      }
    } else {
      throw new Error(response.message || "Failed to create application");
    }
  } catch (error) {
    const errorMessage =
      error.response?.data?.message ||
      error.message ||
      "Failed to process your agreement. Please try again.";
    alert(errorMessage);
  } finally {
    loading.value = false;
  }
};

// 状态加载完成回调
const onStatusLoaded = (status) => {
  hasApplication.value = status.hasApplication;
};

// 检查是否已有申请
const checkApplicationStatus = async () => {
  try {
    const response = await ibApplicationApi.getMyStatus();
    const status = response.data || response;
    hasApplication.value = status.hasApplication || false;
  } catch (error) {
    // 如果获取失败，默认显示申请表单
    hasApplication.value = false;
  }
};

onMounted(async () => {
  try {
    await loadRequiredDocuments();
    await checkApplicationStatus();
  } finally {
    initialLoad.value = false;
  }
});
</script>

<style scoped>
.ib-dashboard-container {
  min-height: calc(100vh - 200px);
}

.ib-prompt-container {
  max-width: 1400px;
  margin: 0 auto;
}

.ib-dashboard-loading {
  text-align: center;
  padding: 80px 20px;
  color: var(--color-muted);
}

.ib-dashboard-loading__icon {
  font-size: 40px;
  color: var(--color-brand);
}

.ib-dashboard-loading__text {
  margin: 20px 0 0 0;
  font-size: 16px;
}

/* IB Locked Banner */
.ib-locked-banner {
  background: var(--color-brand);
  border-radius: var(--radius-xl);
  padding: 50px;
  color: white;
  box-shadow: 0 10px 30px rgba(var(--color-brand-rgb), 0.3);
  position: relative;
  overflow: hidden;
  text-align: center;
}

.ib-locked-banner::before {
  content: "";
  position: absolute;
  top: -50%;
  right: -10%;
  width: 400px;
  height: 400px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 50%;
}

.ib-locked-banner::after {
  content: "";
  position: absolute;
  bottom: -30%;
  left: -5%;
  width: 300px;
  height: 300px;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 50%;
}

.ib-locked-content {
  position: relative;
  z-index: 1;
}

.lock-icon-large {
  font-size: 80px;
  margin-bottom: 25px;
  opacity: 0.9;
}

.ib-locked-content h1 {
  font-size: 32px;
  margin-bottom: 15px;
  font-weight: 700;
}

.ib-locked-content p {
  font-size: 18px;
  opacity: 0.95;
  line-height: 1.6;
  margin-bottom: 30px;
}

/* Terms Text - 协议文本样式 */
.terms-text {
  margin-bottom: 25px;
  padding: 18px;
  background: rgba(255, 255, 255, 0.15);
  border-radius: var(--radius-md);
  border: 2px solid rgba(255, 255, 255, 0.3);
  backdrop-filter: blur(10px);
}

.terms-text p {
  color: white;
  font-size: 14px;
  line-height: 1.6;
  margin: 0;
}

.terms-text a {
  color: white;
  text-decoration: underline;
  font-weight: 600;
  transition: opacity 0.2s ease;
}

.terms-text a:hover {
  opacity: 0.8;
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
  text-decoration: none;
}

.btn-white {
  background: white;
  color: var(--color-brand);
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.btn-white:hover:not(:disabled) {
  transform: translateY(-3px);
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
}

.btn-white:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-large {
  padding: 16px 40px;
  font-size: 16px;
}

/* Modal Styles - 参考注册弹窗 */
.modal {
  display: block;
  position: fixed;
  z-index: 1000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.5);
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.legal-modal .modal-content {
  max-width: 800px;
  max-height: 85vh;
  background-color: #ffffff;
  margin: 5% auto;
  padding: 0;
  border-radius: 20px;
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

.legal-content {
  display: flex;
  flex-direction: column;
  max-height: 85vh;
}

.modal-header {
  background: var(--color-brand);
  color: white;
  padding: 30px;
  border-radius: 20px 20px 0 0;
  position: relative;
}

.modal-header h2 {
  margin: 0;
  font-size: 28px;
}

.close {
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

.legal-body {
  flex: 1;
  overflow-y: auto;
  padding: 30px 40px;
  max-height: calc(85vh - 100px);
}

.legal-document-content {
  line-height: 1.8;
  color: var(--color-ink);
  font-size: 15px;
}

.legal-document-content :deep(h1) {
  font-size: 24px;
  color: var(--color-ink);
  margin-bottom: 20px;
  margin-top: 30px;
}

.legal-document-content :deep(h2) {
  font-size: 20px;
  color: var(--color-ink);
  margin-bottom: 15px;
  margin-top: 25px;
}

.legal-document-content :deep(h3) {
  font-size: 18px;
  color: var(--color-text);
  margin-bottom: 12px;
  margin-top: 20px;
}

.legal-document-content :deep(p) {
  margin-bottom: 15px;
  text-align: justify;
}

.legal-document-content :deep(ul),
.legal-document-content :deep(ol) {
  margin-left: 25px;
  margin-bottom: 15px;
}

.legal-document-content :deep(li) {
  margin-bottom: 8px;
}

.legal-document-content :deep(strong) {
  color: var(--color-ink);
  font-weight: 600;
}

.legal-document-content :deep(a) {
  color: var(--color-brand);
  text-decoration: underline;
}

.legal-document-content :deep(a):hover {
  color: var(--color-brand-strong);
}

.legal-footer {
  margin-top: 30px;
  padding-top: 20px;
  border-top: 1px solid var(--color-border);
}

.modal-button {
  width: 100%;
  padding: 16px;
  background: var(--color-brand);
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

@media (max-width: 768px) {
  .ib-locked-banner {
    padding: 40px 25px;
  }

  .ib-locked-content h1 {
    font-size: 26px;
  }

  .ib-locked-content p {
    font-size: 16px;
  }

  .terms-text {
    padding: 15px;
  }

  .terms-text p {
    font-size: 13px;
  }

  .legal-modal .modal-content {
    margin: 10% auto;
    width: 95%;
    max-height: 90vh;
  }

  .legal-body {
    padding: 20px;
  }
}
</style>
