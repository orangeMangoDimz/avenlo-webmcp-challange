<template>
  <div class="detail-content">
    <div class="detail-sections">
      <div class="detail-section">
        <h3>
          <div class="section-header">
            <div class="section-title">
              <i class="fas fa-info-circle"></i>
              {{ t("withdrawKycDetail_section_templateInfo") }}
            </div>
            <button
              v-if="hasEditTemplatePermission"
              class="btn-save"
              :class="{
                active: hasTemplateChanges,
                disabled: !hasTemplateChanges,
              }"
              @click="saveTemplateInfo"
            >
              <i class="fas fa-save"></i> {{ t("withdrawKycDetail_btnSave") }}
            </button>
          </div>
        </h3>
        <div class="detail-field">
          <span class="detail-label">{{
            t("withdrawKycDetail_label_templateName")
          }}</span>
          <div class="detail-value-wrapper">
            <span
              ref="templateNameRef"
              class="detail-value"
              :class="{ editable: editingField === 'templateName' }"
              :contenteditable="editingField === 'templateName'"
              @input="checkTemplateChanges"
              >{{ localTemplate.templateName }}</span
            >
            <button
              v-if="hasEditTemplatePermission"
              class="btn-edit"
              @click="enableEdit('templateName')"
            >
              <i class="fas fa-edit"></i>
            </button>
          </div>
        </div>
        <div class="detail-field">
          <span class="detail-label">{{
            t("withdrawKycDetail_label_description")
          }}</span>
          <div class="detail-value-wrapper">
            <span
              ref="templateDescRef"
              class="detail-value"
              :class="{ editable: editingField === 'description' }"
              :contenteditable="editingField === 'description'"
              @input="checkTemplateChanges"
              >{{ localTemplate.description }}</span
            >
            <button
              v-if="hasEditTemplatePermission"
              class="btn-edit"
              @click="enableEdit('description')"
            >
              <i class="fas fa-edit"></i>
            </button>
          </div>
        </div>
        <div class="detail-field">
          <span class="detail-label">{{
            t("withdrawKycDetail_label_totalQuestions")
          }}</span>
          <span class="detail-value">{{
            tParams("withdrawKycTpl_questionsCount", "{n} Questions", {
              n: formatNumber(localTemplate.totalQuestions || 0),
            })
          }}</span>
        </div>
        <div class="detail-field">
          <span class="detail-label">{{
            t("withdrawKycDetail_label_activeRules")
          }}</span>
          <span class="detail-value">{{
            tParams("withdrawKycTpl_rulesCount", "{n} Rules", {
              n: formatNumber(localTemplate.totalRules || 0),
            })
          }}</span>
        </div>
        <div class="detail-field">
          <span class="detail-label">{{
            t("withdrawKycDetail_label_status")
          }}</span>
          <div class="detail-value-wrapper">
            <select
              v-model="localTemplate.status"
              class="status-select"
              :class="localTemplate.status"
              :disabled="!hasEditTemplatePermission"
              @change="handleStatusChange"
            >
              <option value="draft">
                {{ t("withdrawKycTpl_status_draft") }}
              </option>
              <option value="active">
                {{ t("withdrawKycTpl_status_active") }}
              </option>
              <option value="inactive">
                {{ t("withdrawKycTpl_status_inactive") }}
              </option>
            </select>
            <span v-if="statusChanged" class="status-change-indicator">
              <i class="fas fa-exclamation-circle"></i>
              {{ t("withdrawKycDetail_statusChanged") }}
            </span>
          </div>
        </div>
        <div class="detail-field">
          <span class="detail-label">{{
            t("withdrawKycDetail_label_lastUpdated")
          }}</span>
          <span class="detail-value">{{
            formatDate(localTemplate.updatedAt)
          }}</span>
        </div>
        <div class="detail-field">
          <span
            class="detail-label"
            style="color: var(--color-ink); font-size: 14px"
          >
            <i class="fas fa-user-check"></i>
            {{ t("withdrawKycDetail_autoApproveTitle") }}
          </span>
          <div
            class="toggle-switch"
            :class="{
              active: isAutoApproveEnabled,
              disabled: !hasEditTemplatePermission,
            }"
            @click="hasEditTemplatePermission ? toggleAutoApprove() : null"
          ></div>
        </div>
        <div v-if="isAutoApproveEnabled" class="auto-approve-note">
          <i class="fas fa-exclamation-triangle"></i>
          <strong>{{ t("common_warning") }}:</strong>
          {{ t("withdrawKycDetail_autoApproveWarning") }}
        </div>
      </div>

      <div class="detail-section">
        <h3>
          <div class="section-header">
            <div class="section-title">
              <i class="fas fa-credit-card"></i>
              {{ t("withdrawKycDetail_section_paymentMethods") }}
            </div>
          </div>
        </h3>
        <div class="detail-field">
          <span class="detail-label">{{
            t("withdrawKycDetail_label_methodList")
          }}</span>
        </div>
        <div class="payment-methods-display">
          <span
            v-for="(method, idx) in paymentMethods"
            :key="method.gatewaySettingId || method.gatewayName || idx"
            :class="['payment-method-badge', method.methodType || 'fiat']"
          >
            <i :class="getPaymentMethodIconClass(method)"></i>
            {{ method.label }}
          </span>
        </div>
      </div>
    </div>

    <div class="detail-section full-width">
      <h3>
        <div class="section-header">
          <div class="section-title">
            <i class="fas fa-list"></i>
            {{
              tParams(
                "withdrawKycDetail_section_questions",
                "Withdraw KYC Questions ({n})",
                { n: questions.length },
              )
            }}
          </div>
          <button
            v-if="hasAddCategoryPermission"
            class="btn btn-success"
            @click="openCategoryModal"
          >
            <i class="fas fa-plus"></i>
            {{ t("withdrawKycDetail_btnNewCategory") }}
          </button>
        </div>
      </h3>
      <QuestionList
        :template-id="template.templateId"
        :questions="questions"
        :categories="categories"
        :has-add-category-permission="hasAddCategoryPermission"
        :has-edit-category-permission="hasEditCategoryPermission"
        :has-delete-category-permission="hasDeleteCategoryPermission"
        :has-add-question-permission="hasAddQuestionPermission"
        :has-edit-question-permission="hasEditQuestionPermission"
        :has-duplicate-question-permission="hasDuplicateQuestionPermission"
        :has-delete-question-permission="hasDeleteQuestionPermission"
        :question-service="withdrawKycServiceSet.questionService"
        :category-service="withdrawKycServiceSet.categoryService"
        @refresh="loadTemplateData"
        @add-category="openCategoryModal"
      />
    </div>

    <div class="detail-section full-width">
      <h3>
        <div class="section-header">
          <div class="section-title">
            <i class="fas fa-sitemap"></i>
            {{
              tParams(
                "withdrawKycDetail_section_rules",
                "Conditional Logic Rules ({n})",
                { n: formatNumber(rules.length) },
              )
            }}
          </div>
          <button
            v-if="hasAddRulePermission"
            class="btn btn-success"
            @click="openRuleModal"
          >
            <i class="fas fa-plus"></i> {{ t("withdrawKycDetail_btnAddRule") }}
          </button>
        </div>
      </h3>
      <RuleList
        :template-id="template.templateId"
        :rules="rules"
        :questions="choiceQuestions"
        :has-add-rule-permission="hasAddRulePermission"
        :has-edit-rule-permission="hasEditRulePermission"
        :has-delete-rule-permission="hasDeleteRulePermission"
        :rule-service="withdrawKycServiceSet.ruleService"
        @refresh="loadTemplateData"
        @edit="openEditRuleModal"
      />
    </div>

    <div class="detail-section full-width">
      <h3>
        <div class="section-header">
          <div class="section-title">
            <i class="fas fa-file-signature"></i>
            {{ t("withdrawKycDetail_section_docSig") }}
          </div>
        </div>
      </h3>
      <DocumentList
        :template-id="template.templateId"
        :documents="documents"
        :require-document-signature="localTemplate.requireDocumentSignature"
        :has-require-legal-document-permission="
          hasRequireLegalDocumentPermission
        "
        :has-add-legal-document-permission="hasAddLegalDocumentPermission"
        :has-edit-legal-document-permission="hasEditLegalDocumentPermission"
        :has-delete-legal-document-permission="hasDeleteLegalDocumentPermission"
        :document-service="withdrawKycServiceSet.documentService"
        :template-service="withdrawKycServiceSet.templateService"
        @refresh="loadTemplateData"
      />
    </div>

    <CategoryModal
      v-if="showCategoryModal"
      :template-id="template.templateId"
      :category-service="withdrawKycServiceSet.categoryService"
      @close="showCategoryModal = false"
      @save="handleCategorySave"
    />

    <RuleModal
      v-if="showRuleModal"
      :template-id="template.templateId"
      :questions="choiceQuestions"
      :rule="editingRule"
      :rule-service="withdrawKycServiceSet.ruleService"
      :application-label="t('withdrawKycDetail_ruleApplicationLabel')"
      @close="closeRuleModal"
      @save="handleRuleSave"
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, nextTick, watch } from "vue";
import { withdrawKycServiceSet } from "@/services/withdrawKycTemplateService";
import QuestionList from "@/components/withdrawals/WithdrawQuestionList.vue";
import RuleList from "@/components/withdrawals/WithdrawRuleList.vue";
import DocumentList from "@/components/withdrawals/WithdrawDocumentList.vue";
import CategoryModal from "@/components/withdrawals/WithdrawCategoryModal.vue";
import RuleModal from "@/components/withdrawals/WithdrawRuleModal.vue";
import { formatNumber } from "@/utils/helpers";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { translateApiErrorMessage } from "@/i18n/adminI18nBridge";

const { t, tParams, languageStore } = useAdminI18n();

const PAYMENT_METHOD_ICON_CLASS = "fas fa-wallet";
const INVALID_PAYMENT_METHOD_ICON_CLASSES = new Set([
  "fas fas-bee",
  "fas fa-bee",
]);

const props = defineProps({
  template: {
    type: Object,
    required: true,
  },
  hasEditTemplatePermission: {
    type: Boolean,
    default: false,
  },
  hasAddCategoryPermission: {
    type: Boolean,
    default: false,
  },
  hasEditCategoryPermission: {
    type: Boolean,
    default: false,
  },
  hasDeleteCategoryPermission: {
    type: Boolean,
    default: false,
  },
  hasAddQuestionPermission: {
    type: Boolean,
    default: false,
  },
  hasEditQuestionPermission: {
    type: Boolean,
    default: false,
  },
  hasDuplicateQuestionPermission: {
    type: Boolean,
    default: false,
  },
  hasDeleteQuestionPermission: {
    type: Boolean,
    default: false,
  },
  hasAddRulePermission: {
    type: Boolean,
    default: false,
  },
  hasEditRulePermission: {
    type: Boolean,
    default: false,
  },
  hasDeleteRulePermission: {
    type: Boolean,
    default: false,
  },
  hasAddLegalDocumentPermission: {
    type: Boolean,
    default: false,
  },
  hasEditLegalDocumentPermission: {
    type: Boolean,
    default: false,
  },
  hasDeleteLegalDocumentPermission: {
    type: Boolean,
    default: false,
  },
  hasRequireLegalDocumentPermission: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["refresh", "close"]);

const templateNameRef = ref(null);
const templateDescRef = ref(null);

const localTemplate = reactive({ ...props.template });
const originalTemplate = { ...props.template };
const editingField = ref(null);
const hasTemplateChanges = ref(false);
const statusChanged = ref(false);
const isAutoApproveEnabled = ref(props.template.isAutoApproveEnabled || false);

const questions = ref([]);
const categories = ref([]);
const rules = ref([]);
const documents = ref([]);
const choiceQuestions = ref([]);

const showCategoryModal = ref(false);
const showRuleModal = ref(false);
const editingRule = ref(null);

const paymentMethods = computed(() => {
  if (
    Array.isArray(localTemplate.paymentMethods) &&
    localTemplate.paymentMethods.length > 0
  ) {
    return localTemplate.paymentMethods.map((method) => ({
      ...method,
      label:
        method.gatewayName ||
        method.methodName ||
        (method.gatewaySettingId != null &&
        String(method.gatewaySettingId).trim() !== ""
          ? tParams("withdrawKycTpl_methodId", "Method #{id}", {
              id: String(method.gatewaySettingId),
            })
          : ""),
    }));
  }

  if (localTemplate.gatewayName || localTemplate.gatewaySettingId) {
    return [
      {
        gatewayName: localTemplate.gatewayName,
        gatewaySettingId: localTemplate.gatewaySettingId,
        iconClass: localTemplate.iconClass || localTemplate.iconclass,
        methodType: localTemplate.methodType || "fiat",
        label:
          localTemplate.gatewayName ||
          tParams("withdrawKycTpl_methodId", "Method #{id}", {
            id: String(localTemplate.gatewaySettingId),
          }),
      },
    ];
  }

  return [];
});

const getPaymentMethodIconClass = (method) => {
  const iconClass = `${method?.iconClass || method?.iconclass || ""}`.trim();
  if (!iconClass || INVALID_PAYMENT_METHOD_ICON_CLASSES.has(iconClass)) {
    return PAYMENT_METHOD_ICON_CLASS;
  }

  return iconClass;
};

const loadTemplateData = async () => {
  try {
    const [questionsRes, categoriesRes, rulesRes, documentsRes] =
      await Promise.all([
        withdrawKycServiceSet.templateService.getTemplateQuestions(
          props.template.templateId,
          false,
        ),
        withdrawKycServiceSet.templateService.getTemplateCategories(
          props.template.templateId,
        ),
        withdrawKycServiceSet.templateService.getTemplateRules(
          props.template.templateId,
        ),
        withdrawKycServiceSet.templateService.getTemplateDocuments(
          props.template.templateId,
        ),
      ]);

    if (questionsRes.success)
      questions.value = Array.isArray(questionsRes.data)
        ? questionsRes.data
        : questionsRes.data?.questions || [];
    if (categoriesRes.success)
      categories.value = Array.isArray(categoriesRes.data)
        ? categoriesRes.data
        : categoriesRes.data?.categories || [];
    if (rulesRes.success)
      rules.value = Array.isArray(rulesRes.data)
        ? rulesRes.data
        : rulesRes.data?.rules || [];
    if (documentsRes.success)
      documents.value = Array.isArray(documentsRes.data)
        ? documentsRes.data
        : documentsRes.data?.documents || [];

    const choiceRes =
      await withdrawKycServiceSet.templateService.getChoiceQuestions(
        props.template.templateId,
      );
    if (choiceRes.success) {
      choiceQuestions.value = choiceRes.data.questions;
    }
  } catch (error) {
    console.error("Failed to load withdraw template data:", error);
  }
};

const enableEdit = (field) => {
  editingField.value = field;
  nextTick(() => {
    if (field === "templateName" && templateNameRef.value) {
      templateNameRef.value.focus();
      selectAllText(templateNameRef.value);
    } else if (field === "description" && templateDescRef.value) {
      templateDescRef.value.focus();
      selectAllText(templateDescRef.value);
    }
  });
};

const selectAllText = (element) => {
  const range = document.createRange();
  range.selectNodeContents(element);
  const selection = window.getSelection();
  selection.removeAllRanges();
  selection.addRange(range);
};

const checkTemplateChanges = () => {
  const currentName = templateNameRef.value?.textContent || "";
  const currentDesc = templateDescRef.value?.textContent || "";
  hasTemplateChanges.value =
    currentName !== originalTemplate.templateName ||
    currentDesc !== originalTemplate.description;
};

const handleStatusChange = async () => {
  if (localTemplate.status === originalTemplate.status) {
    statusChanged.value = false;
    return;
  }

  statusChanged.value = true;

  if (
    !confirm(
      tParams("withdrawKycDetail_confirm_statusChange", "Change...", {
        from: formatStatus(originalTemplate.status),
        to: formatStatus(localTemplate.status),
      }),
    )
  ) {
    localTemplate.status = originalTemplate.status;
    statusChanged.value = false;
    return;
  }

  try {
    const response = await withdrawKycServiceSet.templateService.updateTemplate(
      props.template.templateId,
      {
        status: localTemplate.status,
      },
    );

    if (response.success) {
      originalTemplate.status = localTemplate.status;
      statusChanged.value = false;
      alert(
        tParams("withdrawKycDetail_alert_statusOk", "...", {
          status: formatStatus(localTemplate.status),
        }),
      );
      emit("refresh");
    } else {
      localTemplate.status = originalTemplate.status;
      statusChanged.value = false;
      const raw = response.message || t("common_unknownError");
      alert(
        tParams("withdrawKycDetail_alert_statusFailed", "Failed: {msg}", {
          msg: translateApiErrorMessage(response.errorCode, raw),
        }),
      );
    }
  } catch (error) {
    console.error("Failed to update status:", error);
    localTemplate.status = originalTemplate.status;
    statusChanged.value = false;
    const data = error?.response?.data ?? error;
    const rawMsg = data?.message || error?.message || t("common_unknownError");
    const msg = translateApiErrorMessage(data?.errorCode, rawMsg);
    alert(
      tParams("withdrawKycDetail_alert_statusFailed", "Failed: {msg}", { msg }),
    );
  }
};

const saveTemplateInfo = async () => {
  if (!hasTemplateChanges.value) return;

  const updatedData = {
    templateName:
      templateNameRef.value?.textContent || localTemplate.templateName,
    description:
      templateDescRef.value?.textContent || localTemplate.description,
  };

  try {
    const response = await withdrawKycServiceSet.templateService.updateTemplate(
      props.template.templateId,
      updatedData,
    );
    if (response.success) {
      localTemplate.templateName = updatedData.templateName;
      localTemplate.description = updatedData.description;
      originalTemplate.templateName = updatedData.templateName;
      originalTemplate.description = updatedData.description;
      hasTemplateChanges.value = false;
      editingField.value = null;
      alert(t("withdrawKycDetail_alert_saveOk"));
      emit("refresh");
    } else {
      const raw = response.message || t("common_unknownError");
      alert(
        tParams("withdrawKycDetail_alert_saveFailed", "Failed: {msg}", {
          msg: translateApiErrorMessage(response.errorCode, raw),
        }),
      );
    }
  } catch (error) {
    console.error("Failed to save template:", error);
    const data = error?.response?.data ?? error;
    const rawMsg = data?.message || error?.message || t("common_unknownError");
    const msg = translateApiErrorMessage(data?.errorCode, rawMsg);
    alert(
      tParams("withdrawKycDetail_alert_saveFailed", "Failed: {msg}", { msg }),
    );
  }
};

const toggleAutoApprove = async () => {
  if (!props.hasEditTemplatePermission) return;

  const newValue = !isAutoApproveEnabled.value;

  if (newValue) {
    if (confirm(t("withdrawKycDetail_confirm_autoApproveEnable"))) {
      isAutoApproveEnabled.value = newValue;
      await updateTemplateSettings();
      alert(t("withdrawKycDetail_alert_autoApproveOn"));
    }
  } else if (confirm(t("withdrawKycDetail_confirm_autoApproveDisable"))) {
    isAutoApproveEnabled.value = newValue;
    await updateTemplateSettings();
    alert(t("withdrawKycDetail_alert_autoApproveOff"));
  }
};

const updateTemplateSettings = async () => {
  try {
    const response = await withdrawKycServiceSet.templateService.updateTemplate(
      props.template.templateId,
      {
        isAutoApproveEnabled: isAutoApproveEnabled.value,
      },
    );

    if (!response.success) {
      const raw = response.message || t("common_unknownError");
      alert(
        tParams("withdrawKycDetail_alert_settingsFailed", "Failed: {msg}", {
          msg: translateApiErrorMessage(response.errorCode, raw),
        }),
      );
    }
  } catch (error) {
    console.error("Failed to update settings:", error);
    const data = error?.response?.data ?? error;
    const rawMsg = data?.message || error?.message || t("common_unknownError");
    const msg = translateApiErrorMessage(data?.errorCode, rawMsg);
    alert(
      tParams("withdrawKycDetail_alert_settingsFailed", "Failed: {msg}", {
        msg,
      }),
    );
  }
};

const openCategoryModal = () => {
  showCategoryModal.value = true;
};

const handleCategorySave = async () => {
  showCategoryModal.value = false;
  await loadTemplateData();
  emit("refresh");
};

const openRuleModal = () => {
  editingRule.value = null;
  showRuleModal.value = true;
};

const openEditRuleModal = (rule) => {
  editingRule.value = rule;
  showRuleModal.value = true;
};

const closeRuleModal = () => {
  showRuleModal.value = false;
  editingRule.value = null;
};

const handleRuleSave = async () => {
  closeRuleModal();
  await loadTemplateData();
  emit("refresh");
};

const formatStatus = (status) => {
  const key = String(status || "").toLowerCase();
  const map = {
    active: "withdrawKycTpl_status_active",
    inactive: "withdrawKycTpl_status_inactive",
    draft: "withdrawKycTpl_status_draft",
  };
  const tr = map[key];
  return tr ? t(tr) : status;
};

const formatDate = (dateString) => {
  if (!dateString) return t("leads_na");
  const date = new Date(dateString);
  const loc = languageStore.currentLanguage === "zh" ? "zh-CN" : "en-US";
  return date.toLocaleDateString(loc, {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
};

watch(
  () => props.template,
  (newTemplate) => {
    Object.assign(localTemplate, newTemplate);
    Object.assign(originalTemplate, newTemplate);
    isAutoApproveEnabled.value = newTemplate.isAutoApproveEnabled || false;
    statusChanged.value = false;
    hasTemplateChanges.value = false;
  },
  { deep: true },
);

onMounted(() => {
  loadTemplateData();
});
</script>

<style scoped>
.detail-content {
  padding: 30px;
  background: var(--color-surface-soft);
}

.detail-sections {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  margin-bottom: 20px;
}

.detail-section {
  background: var(--color-surface);
  border-radius: var(--radius-md);
  padding: 25px;
  border: 2px solid var(--color-border);
}

.detail-section h3 {
  font-size: 16px;
  color: var(--color-ink);
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 2px solid var(--color-border);
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
}

.section-title {
  font-size: 16px;
  font-weight: 600;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 10px;
}

.section-title i {
  color: var(--color-brand);
}

.detail-section.full-width {
  grid-column: 1 / -1;
}

.detail-sections + .detail-section.full-width {
  margin-top: 12px;
}

.detail-content > .detail-section.full-width + .detail-section.full-width {
  margin-top: 24px;
}

.detail-field {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 0;
  border-bottom: 1px solid #f0f0f0;
}

.detail-field:last-child {
  border-bottom: none;
}

.detail-label {
  font-weight: 600;
  color: var(--color-muted);
  font-size: 13px;
}

.detail-value {
  color: var(--color-ink);
  font-size: 14px;
  font-weight: 500;
  text-align: right;
}

.detail-value-wrapper {
  display: flex;
  align-items: center;
  gap: 10px;
}

.detail-value.editable {
  border: 2px solid var(--color-border);
  background: var(--color-surface);
  cursor: text;
  padding: 4px 8px;
  border-radius: 4px;
  min-width: 150px;
}

.detail-value.editable:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.detail-value[contenteditable="true"] {
  border-color: var(--color-brand);
  background: var(--color-surface-soft);
}

.btn-edit {
  background: none;
  border: none;
  color: var(--color-faint);
  cursor: pointer;
  font-size: 14px;
  padding: 4px;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-edit:hover {
  color: var(--color-brand);
  transform: scale(1.1);
}

.btn-save {
  padding: 6px 16px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.btn-save.disabled {
  background: var(--color-border);
  color: var(--color-faint);
  cursor: not-allowed;
}

.btn-save.active {
  background: var(--color-brand-solid);
  color: white;
  box-shadow: 0 2px 8px rgba(var(--color-brand-rgb), 0.3);
}

.btn-save.active:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.4);
}

.status-select {
  padding: 6px 32px 6px 12px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 13px;
  font-weight: 600;
  text-transform: uppercase;
  cursor: pointer;
  outline: none;
  transition: all 0.3s ease;
  background: var(--color-surface);
  color: var(--color-ink);
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%234a5568' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 10px center;
  padding-right: 35px;
}

.status-select:hover {
  border-color: var(--color-brand);
}

.status-select:focus {
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.status-select:disabled {
  cursor: not-allowed;
  opacity: 0.6;
  background-color: var(--color-surface-soft);
  color: var(--color-muted);
}

.status-select.active {
  background-color: var(--color-success-soft);
  color: var(--color-success);
  border-color: #9ae6b4;
}

.status-select.inactive {
  background-color: var(--color-danger-soft);
  color: var(--color-danger);
  border-color: var(--color-danger-border);
}

.status-select.draft {
  background-color: var(--color-warning-soft);
  color: var(--color-warning);
  border-color: #fbd38d;
}

.status-change-indicator {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  color: var(--color-warning);
  font-weight: 600;
  margin-left: 8px;
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

.toggle-switch.disabled {
  cursor: not-allowed;
  opacity: 0.7;
  background: var(--color-border) !important;
}

.toggle-switch.disabled.active {
  background: var(--color-success-soft) !important;
  opacity: 0.8;
}

.toggle-switch.disabled::after {
  background: var(--color-border-strong);
}

.auto-approve-note {
  margin-top: 10px;
  padding: 12px;
  background: var(--color-warning-soft);
  border: 1px solid var(--color-warning-border);
  border-radius: var(--radius-sm);
  font-size: 13px;
  color: var(--color-warning);
}

.auto-approve-note i {
  margin-right: 6px;
}

.payment-methods-display {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 10px;
}

.payment-method-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  background: var(--color-info-soft);
  color: var(--color-info);
}

.payment-method-badge.crypto {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.payment-method-badge.fiat {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.btn {
  padding: 12px 20px;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.btn-success {
  background: linear-gradient(
    135deg,
    var(--color-success) 0%,
    var(--color-success) 100%
  );
  color: white;
}

.btn-success:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 15px rgba(72, 187, 120, 0.3);
}

@media (max-width: 768px) {
  .detail-content {
    padding: 20px 15px;
  }

  .detail-sections {
    grid-template-columns: 1fr;
  }

  .detail-field {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }

  .detail-value-wrapper {
    width: 100%;
    justify-content: space-between;
  }

  .detail-value {
    text-align: left;
  }
}
</style>
