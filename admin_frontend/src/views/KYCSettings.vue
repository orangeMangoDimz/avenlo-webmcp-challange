<template>
  <div class="container">
    <!-- Page Header -->
    <div class="page-header">
      <div class="page-title">
        <h1>{{ t("page_kycSettings_title") }}</h1>
        <p>{{ t("page_kycSettings_sub") }}</p>
      </div>
      <div class="page-actions">
        <PageHeaderActions />
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <i
        class="fas fa-spinner fa-spin"
        style="font-size: 32px; color: var(--color-brand)"
      ></i>
      <p style="margin-top: 15px; color: var(--color-muted)">
        {{ t("kycSettings_loadingSettings") }}
      </p>
    </div>

    <!-- KYC Notice Text Settings Card -->
    <div
      v-else
      class="settings-card"
      :class="{ collapsed: collapsedCards.includes('notice') }"
    >
      <div class="card-header" @click="toggleCard('notice')">
        <div class="card-header-content">
          <h2>
            <i class="fas fa-bell"></i> {{ t("kycSettings_noticeCardTitle") }}
          </h2>
          <p>{{ t("kycSettings_noticeCardSub") }}</p>
        </div>
        <i class="fas fa-chevron-down card-collapse-icon"></i>
      </div>
      <div class="card-body">
        <form @submit.prevent="saveNoticeSettings">
          <div class="form-group">
            <label>
              {{ t("kycSettings_labelNoticeTitle") }}
              <span class="label-description">{{
                t("kycSettings_descNoticeTitle")
              }}</span>
            </label>
            <input
              type="text"
              v-model="noticeSettings.noticeTitle"
              @input="markAsChanged('notice')"
              :placeholder="t('kycSettings_phNoticeTitle')"
              :disabled="!hasEditPermission"
            />
          </div>

          <div class="form-group">
            <label>
              {{ t("kycSettings_labelNoticeSubtitle") }}
              <span class="label-description">{{
                t("kycSettings_descNoticeSubtitle")
              }}</span>
            </label>
            <input
              type="text"
              v-model="noticeSettings.noticeSubtitle"
              @input="markAsChanged('notice')"
              :placeholder="t('kycSettings_phNoticeSubtitle')"
              :disabled="!hasEditPermission"
            />
          </div>

          <div class="form-group">
            <label>
              {{ t("kycSettings_labelNoticeDescription") }}
              <span class="label-description">{{
                t("kycSettings_descNoticeDescription")
              }}</span>
            </label>
            <textarea
              v-model="noticeSettings.noticeDescription"
              @input="markAsChanged('notice')"
              class="text-editor"
              :placeholder="t('kycSettings_phNoticeDescription')"
              :disabled="!hasEditPermission"
            ></textarea>
          </div>

          <div class="form-group">
            <label>
              {{ t("kycSettings_labelRequirementsTitle") }}
              <span class="label-description">{{
                t("kycSettings_descRequirementsTitle")
              }}</span>
            </label>
            <input
              type="text"
              v-model="noticeSettings.requirementsTitle"
              @input="markAsChanged('notice')"
              :placeholder="t('kycSettings_phRequirementsTitle')"
              :disabled="!hasEditPermission"
            />
          </div>

          <div class="form-group">
            <label>
              {{ t("kycSettings_labelVerificationTime") }}
              <span class="label-description">{{
                t("kycSettings_descVerificationTime")
              }}</span>
            </label>
            <textarea
              v-model="noticeSettings.verificationTimeNotice"
              @input="markAsChanged('notice')"
              class="text-editor"
              :placeholder="t('kycSettings_phVerificationTime')"
              :disabled="!hasEditPermission"
            ></textarea>
          </div>

          <div class="form-group">
            <label>
              {{ t("kycSettings_labelPrimaryButton") }}
              <span class="label-description">{{
                t("kycSettings_descPrimaryButton")
              }}</span>
            </label>
            <input
              type="text"
              v-model="noticeSettings.primaryButtonText"
              @input="markAsChanged('notice')"
              :placeholder="t('kycSettings_phButtonText')"
              :disabled="!hasEditPermission"
            />
          </div>

          <div v-if="false" class="form-group">
            <label>
              {{ t("kycSettings_labelSecondaryButton") }}
              <span class="label-description">{{
                t("kycSettings_descSecondaryButton")
              }}</span>
            </label>
            <input
              type="text"
              v-model="noticeSettings.secondaryButtonText"
              @input="markAsChanged('notice')"
              :placeholder="t('kycSettings_phButtonText')"
            />
          </div>

          <div class="info-box">
            <p>
              <strong
                ><i class="fas fa-info-circle"></i>
                {{ t("kycSettings_noteTitle") }}</strong
              >
              {{ t("kycSettings_noteBody") }}
            </p>
          </div>

          <!-- Card Save Button -->
          <div class="card-footer">
            <button
              v-if="hasEditPermission"
              type="submit"
              class="btn btn-primary"
              :disabled="!hasChanges('notice') || saving"
            >
              <i
                class="fas"
                :class="saving ? 'fa-spinner fa-spin' : 'fa-save'"
              ></i>
              {{
                saving ? t("kycSettings_saving") : t("kycSettings_saveChanges")
              }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- External KYC Gateway 卡片 -->
    <ExternalKycGatewaysCard v-if="hasExternalKycReadPermission" />
  </div>
</template>

<script setup>
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";
import ExternalKycGatewaysCard from "@/components/kyc/ExternalKycGatewaysCard.vue";

import { ref, reactive, onMounted, computed } from "vue";
import { useAuthStore } from "@/stores/auth";
import { kycSettingsService } from "@/services/kycSettingsService";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t, tParams } = useAdminI18n();

const authStore = useAuthStore();

// 权限检查
const hasReadonlyPermission = computed(() =>
  authStore.hasPermission("page_kycsettings_readonly"),
);
const hasEditPermission = computed(() =>
  authStore.hasPermission("page_kycsettings_edit"),
);
// External KYC 卡片整体可见：readonly 或 edit 任一通过即可（行内按钮再各自校验细化权限）
const hasExternalKycReadPermission = computed(
  () =>
    authStore.hasPermission("page_externalkycsettings_readonly") ||
    authStore.hasPermission("page_externalkycsettings_edit"),
);

// State
const loading = ref(false);
const saving = ref(false);
const collapsedCards = ref([]);
const changedCards = ref([]);

const noticeSettings = reactive({
  settingKey: "default_kyc_notice",
  noticeTitle: "",
  noticeSubtitle: "",
  noticeDescription: "",
  requirementsTitle: "",
  verificationTimeNotice: "",
  primaryButtonText: "",
  secondaryButtonText: "",
});

const originalNoticeSettings = {};

// Methods
const loadNoticeSettings = async () => {
  loading.value = true;
  try {
    const response =
      await kycSettingsService.getNoticeSettings("default_kyc_notice");
    if (response.success && response.data) {
      const data = response.data;

      noticeSettings.settingKey = data.settingKey;
      noticeSettings.noticeTitle = data.noticeTitle || "";
      noticeSettings.noticeSubtitle = data.noticeSubtitle || "";
      noticeSettings.noticeDescription = data.noticeDescription || "";
      noticeSettings.requirementsTitle = data.requirementsTitle || "";
      noticeSettings.verificationTimeNotice = data.verificationTimeNotice || "";
      noticeSettings.primaryButtonText = data.primaryButtonText || "";
      noticeSettings.secondaryButtonText = data.secondaryButtonText || "";

      // 保存原始值用于比较
      Object.assign(originalNoticeSettings, noticeSettings);
    }
  } catch (error) {
    console.error("Failed to load notice settings:", error);
    alert(t("kycSettings_alertLoadFailed"));
  } finally {
    loading.value = false;
  }
};

const saveNoticeSettings = async () => {
  if (!hasChanges("notice")) return;

  saving.value = true;
  try {
    const response =
      await kycSettingsService.updateNoticeSettings(noticeSettings);

    if (response.success) {
      alert(t("kycSettings_alertSaveOk"));

      // 更新原始值
      Object.assign(originalNoticeSettings, noticeSettings);

      // 移除更改标记
      const index = changedCards.value.indexOf("notice");
      if (index > -1) {
        changedCards.value.splice(index, 1);
      }
    } else {
      alert(
        tParams(
          "kycSettings_alertSaveFailedWith",
          "Failed to save settings: {msg}",
          { msg: response.message || "" },
        ),
      );
    }
  } catch (error) {
    console.error("Failed to save notice settings:", error);
    alert(t("kycSettings_alertSaveFailed"));
  } finally {
    saving.value = false;
  }
};

const toggleCard = (cardId) => {
  const index = collapsedCards.value.indexOf(cardId);
  if (index > -1) {
    collapsedCards.value.splice(index, 1);
  } else {
    collapsedCards.value.push(cardId);
  }

  // 保存到 localStorage
  localStorage.setItem(
    "kycSettingsCollapsedCards",
    JSON.stringify(collapsedCards.value),
  );
};

const markAsChanged = (cardId) => {
  if (!changedCards.value.includes(cardId)) {
    changedCards.value.push(cardId);
  }
};

const hasChanges = (cardId) => {
  if (cardId === "notice") {
    return Object.keys(noticeSettings).some((key) => {
      return noticeSettings[key] !== originalNoticeSettings[key];
    });
  }
  return false;
};

// Lifecycle
onMounted(async () => {
  // 恢复卡片折叠状态
  const savedCollapsed = localStorage.getItem("kycSettingsCollapsedCards");
  if (savedCollapsed) {
    try {
      collapsedCards.value = JSON.parse(savedCollapsed);
    } catch (e) {
      console.error("Failed to parse collapsed cards:", e);
    }
  }

  await loadNoticeSettings();
});
</script>

<style scoped>
.container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 40px 20px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 2px solid var(--color-border);
}

.page-title h1 {
  font-size: 28px;
  color: var(--color-ink);
  margin-bottom: 5px;
}

.page-title p {
  font-size: 14px;
  color: var(--color-muted);
}

.page-actions {
  display: flex;
  align-items: center;
  gap: 20px;
}

.loading-state {
  text-align: center;
  padding: 60px 20px;
  color: var(--color-muted);
}

.settings-card {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  margin-bottom: 30px;
  overflow: hidden;
}

.card-header {
  background: var(--color-surface-soft);
  padding: 20px 30px;
  border-bottom: 2px solid var(--color-border);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: space-between;
  user-select: none;
  transition: background 0.2s ease;
}

.card-header:hover {
  background: var(--color-surface-muted);
}

.card-header-content {
  flex: 1;
}

.card-header h2 {
  font-size: 20px;
  color: var(--color-ink);
  margin-bottom: 5px;
}

.card-header h2 i {
  margin-right: 10px;
  color: var(--color-brand);
}

.card-header p {
  font-size: 14px;
  color: var(--color-muted);
}

.card-collapse-icon {
  font-size: 18px;
  color: var(--color-faint);
  transition:
    transform 0.3s ease,
    color 0.2s ease;
  margin-left: 20px;
}

.card-header:hover .card-collapse-icon {
  color: var(--color-brand);
}

.settings-card.collapsed .card-collapse-icon {
  transform: rotate(-90deg);
}

.card-body {
  padding: 30px;
  max-height: 5000px;
  overflow: hidden;
  transition:
    max-height 0.4s ease,
    opacity 0.3s ease,
    padding 0.3s ease;
  opacity: 1;
}

.settings-card.collapsed .card-body {
  max-height: 0;
  opacity: 0;
  padding-top: 0;
  padding-bottom: 0;
}

.settings-card.collapsed .card-header {
  border-bottom: none;
}

.form-group {
  margin-bottom: 25px;
}

.form-group label {
  display: block;
  margin-bottom: 10px;
  color: var(--color-ink);
  font-weight: 600;
  font-size: 14px;
}

.form-group .label-description {
  display: block;
  color: var(--color-muted);
  font-weight: 400;
  font-size: 14px;
  margin-top: 5px;
}

.form-group input[type="text"],
.form-group input[type="number"],
.form-group select,
.form-group textarea {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  transition: all 0.3s ease;
  background: var(--color-surface);
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.form-group textarea {
  min-height: 100px;
  resize: vertical;
  font-family: inherit;
}

.text-editor {
  width: 100%;
  min-height: 200px;
  padding: 15px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
  resize: vertical;
  transition: all 0.3s ease;
}

.text-editor:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.info-box {
  background: var(--color-brand-soft);
  border-left: 4px solid var(--color-brand);
  padding: 15px 20px;
  border-radius: var(--radius-md);
  margin-top: 15px;
}

.info-box p {
  color: var(--color-text);
  font-size: 14px;
  line-height: 1.6;
  margin: 0;
}

.info-box i {
  margin-right: 6px;
}

.card-footer {
  display: flex;
  justify-content: flex-end;
  margin-top: 30px;
  padding-top: 20px;
  border-top: 2px solid var(--color-border);
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

.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(var(--color-brand-rgb), 0.5);
}

.btn-primary:disabled {
  background: var(--color-border-strong);
  color: var(--color-faint);
  box-shadow: none;
  cursor: not-allowed;
  opacity: 0.6;
}

@media (max-width: 768px) {
  .container {
    padding: 20px 15px;
  }

  .page-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 20px;
  }

  .page-actions {
    width: 100%;
    justify-content: flex-end;
  }
}
</style>
