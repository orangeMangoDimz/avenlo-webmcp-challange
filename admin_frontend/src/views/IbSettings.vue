<template>
  <div class="container">
    <!-- No permission -->
    <div v-if="!hasReadonlyPermission" class="loading-state">
      <p class="ib-settings-loading-text">{{ t("ibSettings_noPermission") }}</p>
    </div>
    <template v-else>
      <!-- Page Header -->
      <div class="page-header">
        <div class="page-title">
          <h1>{{ t("page_ibSettings_title") }}</h1>
          <p>{{ t("page_ibSettings_sub") }}</p>
        </div>
        <div class="page-actions">
          <PageHeaderActions />
        </div>
      </div>

      <!-- Tabs -->
      <div class="tabs-header">
        <button
          type="button"
          class="tab"
          :class="{ active: activeTab === 'documents' }"
          @click="activeTab = 'documents'"
        >
          {{ t("ibSettings_tab_documents") }}
        </button>
        <button
          type="button"
          class="tab"
          :class="{ active: activeTab === 'tier' }"
          @click="activeTab = 'tier'"
        >
          {{ t("ibSettings_tab_tier") }}
        </button>
        <button
          type="button"
          class="tab"
          :class="{ active: activeTab === 'commission-rule' }"
          @click="activeTab = 'commission-rule'"
        >
          {{ t("ibSettings_tab_commissionRule") }}
        </button>
        <button
          type="button"
          class="tab"
          :class="{ active: activeTab === 'exchange-rate' }"
          @click="activeTab = 'exchange-rate'"
        >
          {{ t("ibSettings_tab_exchangeRate") }}
        </button>
      </div>

      <!-- Tab: Documents -->
      <div v-show="activeTab === 'documents'" class="tab-panel">
        <div v-if="loading" class="loading-state">
          <i class="fas fa-spinner fa-spin ib-settings-loading-spinner"></i>
          <p class="ib-settings-loading-text">{{ t("ibSettings_loading") }}</p>
        </div>
        <div v-else class="documents-panel">
          <div class="info-banner">
            <div class="info-banner-content">
              <i class="fas fa-info-circle info-banner-icon"></i>
              <div class="info-banner-text">
                <strong>{{ t("ibSettings_banner_title") }}</strong>
                {{ t("ibSettings_banner_body") }}
              </div>
            </div>
          </div>

          <div id="ib-documents-list">
            <IbDocumentCard
              v-for="doc in documents"
              :key="doc.id"
              :document="doc"
              :saving="savingDocId === doc.id"
              :can-edit-documents="hasEditDocumentsPermission"
              :show-duplicate="hasDuplicateDocumentPermission"
              :show-delete="hasDeleteDocumentPermission"
              @save="saveDocument"
              @duplicate="duplicateDocument(doc.id)"
              @delete="removeDocument(doc.id)"
            />
          </div>

          <button
            v-if="hasCreateDocumentsPermission"
            type="button"
            class="btn-add-requirement"
            @click="addNewDocument"
          >
            <i class="fas fa-plus"></i> {{ t("ibSettings_btn_addDocument") }}
          </button>

          <div class="info-box ib-settings-info-box-important">
            <p>
              <strong
                ><i class="fas fa-exclamation-triangle"></i>
                {{ t("ibSettings_important_label") }}</strong
              >
              {{ t("ibSettings_important_body") }}
            </p>
          </div>

          <div class="info-box success ib-settings-info-box-tip">
            <p>
              <strong
                ><i class="fas fa-lightbulb"></i>
                {{ t("ibSettings_tip_label") }}</strong
              >
              {{ t("ibSettings_tip_body") }}
            </p>
          </div>
        </div>
      </div>

      <!-- Tab: Tier -->
      <div v-show="activeTab === 'tier'" class="tab-panel">
        <IbTierLevels
          :embedded="true"
          :has-create-tier="hasCreateTierPermission"
          :has-edit-tier="hasEditTierPermission"
          :has-delete-tier="hasDeleteTierPermission"
          :has-set-tier-count="hasSetTierCountPermission"
        />
      </div>

      <!-- Tab: Commission Rule -->
      <div v-show="activeTab === 'commission-rule'" class="tab-panel">
        <IbRules
          :embedded="true"
          :has-sync-products="hasSyncProductsPermission"
          :has-create-rule="hasCreateRulePermission"
          :has-edit-rule="hasEditRulePermission"
          :has-delete-rule="hasDeleteRulePermission"
        />
      </div>

      <!-- Tab: Exchange Rate -->
      <div v-show="activeTab === 'exchange-rate'" class="tab-panel">
        <IbSymbolExchangeRates
          :embedded="true"
          :can-batch-update-mode="hasBatchUpdateExchangeRateModePermission"
          :can-create="hasCreateExchangeRatePermission"
          :can-edit="hasEditExchangeRatePermission"
          :can-delete="hasDeleteExchangeRatePermission"
        />
      </div>
    </template>
  </div>
</template>

<script setup>
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";

import { ref, computed, onMounted } from "vue";
import { useAuthStore } from "@/stores/auth";
import IbDocumentCard from "@/components/ib/IbDocumentCard.vue";
import IbTierLevels from "@/components/ib/IbTierLevels.vue";
import IbRules from "@/components/ib/IbRules.vue";
import IbSymbolExchangeRates from "@/components/ib/IbSymbolExchangeRates.vue";
import ibSettingsApi from "@/services/ibSettingsApi";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t, tParams } = useAdminI18n();

const authStore = useAuthStore();
const hasReadonlyPermission = computed(() =>
  authStore.hasPermission("page_ib_settings_readonly"),
);
const hasCreateDocumentsPermission = computed(() =>
  authStore.hasPermission("page_ib_settings_create_documents"),
);
const hasEditDocumentsPermission = computed(() =>
  authStore.hasPermission("page_ib_settings_edit_documents"),
);
const hasDuplicateDocumentPermission = computed(() =>
  authStore.hasPermission("page_ib_settings_duplicate_document"),
);
const hasDeleteDocumentPermission = computed(() =>
  authStore.hasPermission("page_ib_settings_delete_document"),
);
const hasCreateTierPermission = computed(() =>
  authStore.hasPermission("page_ib_settings_create_tier"),
);
const hasEditTierPermission = computed(() =>
  authStore.hasPermission("page_ib_settings_edit_tier"),
);
const hasDeleteTierPermission = computed(() =>
  authStore.hasPermission("page_ib_settings_delete_tier"),
);
const hasSetTierCountPermission = computed(() =>
  authStore.hasPermission("page_ib_settings_set_tier_count"),
);
const hasSyncProductsPermission = computed(() =>
  authStore.hasPermission("page_ib_settings_sync_products"),
);
const hasCreateRulePermission = computed(() =>
  authStore.hasPermission("page_ib_settings_create_rule"),
);
const hasEditRulePermission = computed(() =>
  authStore.hasPermission("page_ib_settings_edit_rule"),
);
const hasDeleteRulePermission = computed(() =>
  authStore.hasPermission("page_ib_settings_delete_rule"),
);
const hasBatchUpdateExchangeRateModePermission = computed(() =>
  authStore.hasPermission("page_ib_settings_batch_update_exchange_rate_mode"),
);
const hasCreateExchangeRatePermission = computed(() =>
  authStore.hasPermission("page_ib_settings_create_exchange_rate"),
);
const hasEditExchangeRatePermission = computed(() =>
  authStore.hasPermission("page_ib_settings_edit_exchange_rate"),
);
const hasDeleteExchangeRatePermission = computed(() =>
  authStore.hasPermission("page_ib_settings_delete_exchange_rate"),
);

const activeTab = ref("documents");
const loading = ref(true);
const savingDocId = ref(null);
const documents = ref([]);

/**
 * 加载文档
 */
const loadData = async () => {
  try {
    loading.value = true;

    const docsResponse = await ibSettingsApi.getDocuments(false);
    if (docsResponse.success && docsResponse.data) {
      documents.value = docsResponse.data.items || docsResponse.data;
    }
  } catch (error) {
    console.error("Failed to load IB settings:", error);
    alert(t("ibSettings_alert_loadFail"));
  } finally {
    loading.value = false;
  }
};

/**
 * 保存文档
 */
const saveDocument = async ({ id, data }) => {
  try {
    savingDocId.value = id;

    const response = await ibSettingsApi.updateDocument(id, data);

    if (response.success) {
      // 更新本地文档数据
      const docIndex = documents.value.findIndex((d) => d.id === id);
      if (docIndex > -1) {
        documents.value[docIndex] = { ...documents.value[docIndex], ...data };
      }

      // 显示成功提示（简短，不干扰工作流）
      console.log("Document saved successfully");
    } else {
      alert(
        tParams(
          "ibSettings_alert_saveDocFail",
          "Failed to save document: {msg}",
          { msg: response.message ?? "" },
        ),
      );
    }
  } catch (error) {
    console.error("Failed to save document:", error);
    alert(t("ibSettings_alert_saveDocFailGeneric"));
  } finally {
    savingDocId.value = null;
  }
};

/**
 * 添加新文档
 */
const addNewDocument = async () => {
  try {
    const newDoc = {
      documentTitle: t("ibSettings_newDoc_title"),
      documentContent: t("ibSettings_newDoc_content"),
      iconClass: "fas fa-file-alt",
      iconGradient: "linear-gradient(135deg, #a855f7 0%, #7c3aed 100%)",
      isRequired: 1,
      displayOrder: documents.value.length + 1,
      isActive: 1,
    };

    console.log("Creating document with data:", newDoc);
    const response = await ibSettingsApi.createDocument(newDoc);
    console.log("Create document response:", response);
    console.log("Response type:", typeof response);
    console.log("Response success:", response?.success);
    console.log("Response data:", response?.data);
    console.log("Response message:", response?.message);

    // 检查响应格式
    if (!response) {
      alert(t("ibSettings_alert_createNoResponse"));
      return;
    }

    if (response.success === true) {
      // 重新加载文档列表
      await loadDocuments();
      alert(t("ibSettings_alert_createOk"));
    } else {
      const errorMsg =
        response?.message || response?.errors || JSON.stringify(response);
      console.error("Create failed - full response:", response);
      alert(
        tParams(
          "ibSettings_alert_createFail",
          "Failed to create document: {msg}",
          { msg: String(errorMsg) },
        ),
      );
    }
  } catch (error) {
    console.error("Failed to create document - exception:", error);
    console.error("Error response:", error.response);
    console.error("Error data:", error.response?.data);
    const errorMsg =
      error.response?.data?.message || error.message || "Unknown error";
    alert(
      tParams(
        "ibSettings_alert_createException",
        "Failed to create document (exception): {msg}",
        { msg: errorMsg },
      ),
    );
  }
};

/**
 * 复制文档
 */
const duplicateDocument = async (id) => {
  if (!confirm(t("ibSettings_confirm_duplicate"))) {
    return;
  }

  try {
    const response = await ibSettingsApi.duplicateDocument(id);

    if (response.success) {
      // 重新加载文档列表
      await loadDocuments();
      alert(t("ibSettings_alert_duplicateOk"));
    } else {
      alert(
        tParams(
          "ibSettings_alert_duplicateFail",
          "Failed to duplicate document: {msg}",
          { msg: response.message ?? "" },
        ),
      );
    }
  } catch (error) {
    console.error("Failed to duplicate document:", error);
    alert(t("ibSettings_alert_duplicateFailGeneric"));
  }
};

/**
 * 删除文档
 */
const removeDocument = async (id) => {
  if (documents.value.length <= 1) {
    alert(t("ibSettings_alert_minOneDoc"));
    return;
  }

  const doc = documents.value.find((d) => d.id === id);
  if (
    !confirm(
      tParams(
        "ibSettings_confirm_remove",
        'Are you sure you want to remove "{title}"?',
        { title: doc?.documentTitle || "" },
      ),
    )
  ) {
    return;
  }

  try {
    const response = await ibSettingsApi.deleteDocument(id);

    if (response.success) {
      // 从列表中移除
      const index = documents.value.findIndex((d) => d.id === id);
      if (index > -1) {
        documents.value.splice(index, 1);
      }
      alert(t("ibSettings_alert_deleteOk"));
    } else {
      alert(
        tParams(
          "ibSettings_alert_deleteFail",
          "Failed to delete document: {msg}",
          { msg: response.message ?? "" },
        ),
      );
    }
  } catch (error) {
    console.error("Failed to delete document:", error);
    alert(t("ibSettings_alert_deleteFailGeneric"));
  }
};

/**
 * 重新加载文档列表
 */
const loadDocuments = async () => {
  try {
    const response = await ibSettingsApi.getDocuments(false);
    if (response.success && response.data) {
      documents.value = response.data.items || response.data;
    }
  } catch (error) {
    console.error("Failed to load documents:", error);
  }
};

onMounted(async () => {
  await loadData();
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

.page-title {
  display: flex;
  flex-direction: column;
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

.tabs-header {
  display: flex;
  gap: 4px;
  margin-bottom: 24px;
  border-bottom: 2px solid var(--color-border);
}

.tab {
  padding: 12px 24px;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-muted);
  background: transparent;
  border: none;
  border-bottom: 3px solid transparent;
  margin-bottom: -2px;
  cursor: pointer;
  transition:
    color 0.2s,
    border-color 0.2s;
}

.tab:hover {
  color: var(--color-text);
}

.tab.active {
  color: var(--color-brand);
  border-bottom-color: var(--color-brand);
}

.tab-panel {
  padding-top: 0;
}

.loading-state {
  text-align: center;
  padding: 100px 20px;
  color: var(--color-muted);
}

.ib-settings-loading-spinner {
  font-size: 32px;
  color: var(--color-brand);
}

.ib-settings-loading-text {
  margin-top: 15px;
  color: var(--color-muted);
}

.ib-settings-info-box-important {
  margin-top: 20px;
}

.ib-settings-info-box-tip {
  margin-top: 15px;
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

.info-banner {
  background: var(--color-brand-soft);
  border-left: 4px solid var(--color-brand);
  padding: 14px 16px;
  border-radius: var(--radius-md);
  margin-bottom: 20px;
}

.info-banner-content {
  display: flex;
  align-items: start;
  gap: 10px;
}

.info-banner-icon {
  color: var(--color-brand);
  font-size: 18px;
  flex-shrink: 0;
  margin-top: 2px;
}

.info-banner-text {
  font-size: 13px;
  color: var(--color-text);
  line-height: 1.6;
}

.btn-add-requirement {
  width: 100%;
  padding: 12px 20px;
  background: var(--color-success-solid);
  color: white;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.btn-add-requirement:hover {
  background: var(--color-success-solid);
  transform: translateY(-1px);
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
  font-size: 13px;
  line-height: 1.6;
  margin: 0;
}

.info-box i {
  margin-right: 6px;
}

.info-box.warning {
  background: var(--color-danger-soft);
  border-left-color: var(--color-danger-border);
}

.info-box.success {
  background: var(--color-success-soft);
  border-left-color: var(--color-success);
}

.toggle-switch-wrapper {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 15px 20px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-md);
  margin-bottom: 20px;
}

.toggle-label {
  display: flex;
  flex-direction: column;
}

.toggle-label-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 3px;
}

.toggle-label-description {
  font-size: 13px;
  color: var(--color-muted);
}

.toggle-switch {
  position: relative;
  width: 50px;
  height: 26px;
  background: var(--color-border-strong);
  border-radius: 13px;
  cursor: pointer;
  transition: all 0.3s ease;
}

.toggle-switch.active {
  background: var(--color-success-solid);
}

.toggle-switch::after {
  content: "";
  position: absolute;
  top: 3px;
  left: 3px;
  width: 20px;
  height: 20px;
  background: var(--color-surface);
  border-radius: 50%;
  transition: all 0.3s ease;
}

.toggle-switch.active::after {
  left: 27px;
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

.label-description {
  display: block;
  color: var(--color-muted);
  font-weight: 400;
  font-size: 13px;
  margin-top: 5px;
}

.form-group input,
.form-group select,
.form-group textarea {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  transition: all 0.3s ease;
  color: var(--color-ink);
  background: var(--color-surface);
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.card-footer {
  display: flex;
  justify-content: flex-end;
  margin-top: 30px;
  padding-top: 20px;
  border-top: 2px solid var(--color-border);
}

.btn {
  padding: 12px 24px;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;
  display: inline-flex;
  align-items: center;
  gap: 8px;
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
