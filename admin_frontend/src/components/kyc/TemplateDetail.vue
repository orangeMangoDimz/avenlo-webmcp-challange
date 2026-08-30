<template>
  <div class="detail-content">
    <div class="detail-sections">
      <!-- Template Info Section -->
      <div class="detail-section">
        <h3>
          <div class="section-header">
            <div class="section-title">
              <i class="fas fa-info-circle"></i>
              {{ t("kycTplDetail_section_info") }}
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
              <i class="fas fa-save"></i> {{ t("kycTplDetail_btn_save") }}
            </button>
          </div>
        </h3>
        <div class="detail-field">
          <span class="detail-label">{{
            t("kycTplDetail_label_templateName")
          }}</span>
          <div class="detail-value-wrapper">
            <span
              class="detail-value"
              :class="{ editable: editingField === 'templateName' }"
              :contenteditable="editingField === 'templateName'"
              @input="checkTemplateChanges"
              ref="templateNameRef"
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
            t("kycTplDetail_label_description")
          }}</span>
          <div class="detail-value-wrapper">
            <span
              class="detail-value"
              :class="{ editable: editingField === 'description' }"
              :contenteditable="editingField === 'description'"
              @input="checkTemplateChanges"
              ref="templateDescRef"
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
        <div v-if="!isThirdPartyEnabled" class="detail-field">
          <span class="detail-label">{{
            t("kycTplDetail_label_totalQuestions")
          }}</span>
          <span class="detail-value">{{
            tParams("kycTplDetail_questionsSuffix", "{n} Questions", {
              n: localTemplate.totalQuestions || 0,
            })
          }}</span>
        </div>
        <div v-if="!isThirdPartyEnabled" class="detail-field">
          <span class="detail-label">{{
            t("kycTplDetail_label_activeRules")
          }}</span>
          <span class="detail-value">{{
            tParams("kycTplDetail_rulesSuffix", "{n} Rules", {
              n: formatNumber(localTemplate.totalRules || 0),
            })
          }}</span>
        </div>
        <div class="detail-field">
          <span class="detail-label">{{ t("kycTplDetail_label_status") }}</span>
          <div class="detail-value-wrapper">
            <select
              v-model="localTemplate.status"
              @change="handleStatusChange"
              class="status-select"
              :class="localTemplate.status"
              :disabled="!hasEditTemplatePermission"
            >
              <option value="draft">{{ t("kycTpl_status_draft") }}</option>
              <option value="active">{{ t("kycTpl_status_active") }}</option>
              <option value="inactive">
                {{ t("kycTpl_status_inactive") }}
              </option>
            </select>
            <span v-if="statusChanged" class="status-change-indicator">
              <i class="fas fa-exclamation-circle"></i>
              {{ t("kycTplDetail_statusChanged") }}
            </span>
          </div>
        </div>
        <div class="detail-field">
          <span class="detail-label">{{
            t("kycTplDetail_label_lastUpdated")
          }}</span>
          <span class="detail-value">{{
            formatDate(localTemplate.updatedAt)
          }}</span>
        </div>
        <div
          class="detail-field"
          style="
            border-top: 2px solid var(--color-border);
            padding-top: 15px;
            margin-top: 10px;
          "
        >
          <span
            class="detail-label"
            style="color: var(--color-ink); font-size: 14px"
          >
            <i class="fas fa-plug"></i>
            {{ t("kycTplDetail_toggle_thirdParty") }}
          </span>
          <div
            class="toggle-switch"
            :class="{
              active: isThirdPartyEnabled,
              disabled: !hasEditTemplatePermission,
            }"
            @click="hasEditTemplatePermission ? toggleThirdPartyKyc() : null"
          ></div>
        </div>
        <!-- 启用第三方时，隐藏 Auto Approve；显示已绑定的 platform/template 信息 + Edit 按钮 -->
        <template v-if="!isThirdPartyEnabled">
          <div class="detail-field">
            <span
              class="detail-label"
              style="color: var(--color-ink); font-size: 14px"
            >
              <i class="fas fa-user-check"></i>
              {{ t("kycTplDetail_toggle_autoApprove") }}
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
            {{ t("kycTplDetail_autoApproveWarning") }}
          </div>
        </template>

        <template v-else>
          <div class="detail-field">
            <span
              class="detail-label"
              style="color: var(--color-ink); font-size: 14px"
            >
              <i class="fas fa-plug"></i> {{ t("kycTplDetail_label_platform") }}
            </span>
            <span class="detail-value third-party-bind-value">
              {{ boundGatewayName || "-" }}
            </span>
          </div>
          <div class="detail-field">
            <span
              class="detail-label"
              style="color: var(--color-ink); font-size: 14px"
            >
              <i class="fas fa-layer-group"></i>
              {{ t("kycTplDetail_label_externalTemplate") }}
            </span>
            <span class="detail-value third-party-bind-value">
              {{ boundExternalTemplateName || "-" }}
            </span>
          </div>
          <div v-if="hasEditTemplatePermission" class="third-party-actions">
            <button
              class="btn-third-party btn-third-party--edit"
              @click="openThirdPartyModal('edit')"
            >
              <i class="fas fa-edit"></i>
              {{ t("kycTplDetail_btn_changeBinding") }}
            </button>
          </div>
        </template>
      </div>

      <!-- Applied Countries Section -->
      <div class="detail-section">
        <h3>
          <div class="section-header">
            <div class="section-title">
              <i class="fas fa-globe"></i>
              {{ t("kycTplDetail_section_countries") }}
            </div>
            <button
              v-if="hasEditTemplatePermission"
              class="btn-edit-section"
              @click="openCountriesModal"
            >
              <i class="fas fa-edit"></i> {{ t("kycTplDetail_btn_edit") }}
            </button>
          </div>
        </h3>
        <div class="detail-field">
          <span class="detail-label">{{
            t("kycTplDetail_label_countryList")
          }}</span>
        </div>
        <div class="countries-display">
          <span
            v-for="(country, idx) in localTemplate.countries"
            :key="idx"
            class="country-tag"
          >
            <i class="fas fa-flag"></i> {{ country.countryName || country }}
          </span>
        </div>
      </div>
    </div>

    <!-- KYC Questions Section -->
    <div v-if="!isThirdPartyEnabled" class="detail-section full-width">
      <h3>
        <div class="section-header">
          <div class="section-title">
            <i class="fas fa-list"></i>
            {{
              tParams("kycTplDetail_section_questions", "KYC Questions ({n})", {
                n: questions.length,
              })
            }}
          </div>
          <button
            v-if="hasAddCategoryPermission"
            class="btn btn-success"
            @click="openCategoryModal"
          >
            <i class="fas fa-plus"></i> {{ t("kycTplDetail_btn_newCategory") }}
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
        @refresh="onQuestionsRefresh"
        @add-category="openCategoryModal"
      />
    </div>

    <!-- Rules Configuration Section -->
    <div v-if="!isThirdPartyEnabled" class="detail-section full-width">
      <h3>
        <div class="section-header">
          <div class="section-title">
            <i class="fas fa-sitemap"></i>
            {{
              tParams(
                "kycTplDetail_section_rules",
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
            <i class="fas fa-plus"></i> {{ t("kycTplDetail_btn_addRule") }}
          </button>
        </div>
      </h3>
      <RuleList
        :template-id="template.templateId"
        :rules="rules"
        :has-add-rule-permission="hasAddRulePermission"
        :has-edit-rule-permission="hasEditRulePermission"
        :has-delete-rule-permission="hasDeleteRulePermission"
        @refresh="loadTemplateData"
        @edit="openEditRuleModal"
      />
    </div>

    <!-- Document Requirements Section -->
    <div v-if="!isThirdPartyEnabled" class="detail-section full-width">
      <h3>
        <div class="section-header">
          <div class="section-title">
            <i class="fas fa-file-signature"></i>
            {{ t("kycTplDetail_section_documents") }}
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
        @refresh="loadTemplateData"
      />
    </div>

    <!-- Countries Modal -->
    <CountriesModal
      v-if="showCountriesModal"
      :template-id="template.templateId"
      :selected-countries="localTemplate.countries"
      @close="showCountriesModal = false"
      @save="handleCountriesSave"
    />

    <!-- Category Modal -->
    <CategoryModal
      v-if="showCategoryModal"
      :template-id="template.templateId"
      @close="showCategoryModal = false"
      @save="handleCategorySave"
    />

    <!-- Rule Modal -->
    <RuleModal
      v-if="showRuleModal"
      :template-id="template.templateId"
      :questions="choiceQuestions"
      :rule="editingRule"
      @close="closeRuleModal"
      @save="handleRuleSave"
    />

    <!-- Third-party KYC Binding Modal -->
    <div
      v-if="showThirdPartyModal"
      class="tpk-modal-mask"
      @click.self="closeThirdPartyModal"
    >
      <div class="tpk-modal-dialog">
        <div class="tpk-modal-header">
          <h3>
            <i class="fas fa-plug"></i>
            {{ t("kycTplDetail_modal_thirdPartyTitle") }}
          </h3>
          <button class="tpk-modal-close" @click="closeThirdPartyModal">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="tpk-modal-body">
          <div class="tpk-form-group">
            <label>
              <i class="fas fa-plug"></i> {{ t("kycTplDetail_label_platform") }}
            </label>
            <select
              class="tpk-select"
              v-model="modalGatewayId"
              :disabled="enabledGateways.length === 0"
              @change="onModalGatewayChange"
            >
              <option value="">
                {{ t("kycTplDetail_placeholder_platform") }}
              </option>
              <option v-for="gw in enabledGateways" :key="gw.id" :value="gw.id">
                {{ gw.displayName || gw.provider }}
              </option>
            </select>
            <p v-if="enabledGateways.length === 0" class="tpk-hint">
              <i class="fas fa-exclamation-triangle"></i>
              {{ t("kycTplDetail_hint_noEnabledGateways") }}
            </p>
          </div>

          <div class="tpk-form-group">
            <label>
              <i class="fas fa-layer-group"></i>
              {{ t("kycTplDetail_label_externalTemplate") }}
            </label>
            <select
              class="tpk-select"
              v-model="modalExternalTemplateId"
              :disabled="
                !modalGatewayId || modalActiveExternalTemplates.length === 0
              "
            >
              <option value="">
                {{ t("kycTplDetail_placeholder_externalTemplate") }}
              </option>
              <option
                v-for="tpl in modalActiveExternalTemplates"
                :key="tpl.id"
                :value="tpl.id"
              >
                {{ tpl.externalLevelName
                }}{{
                  tpl.displayName && tpl.displayName !== tpl.externalLevelName
                    ? " — " + tpl.displayName
                    : ""
                }}
              </option>
            </select>
            <p
              v-if="
                modalGatewayId &&
                modalActiveExternalTemplates.length === 0 &&
                !loadingExternalTemplates
              "
              class="tpk-hint"
            >
              <i class="fas fa-info-circle"></i>
              {{ t("kycTplDetail_hint_noActiveTemplates") }}
            </p>
          </div>
        </div>
        <div class="tpk-modal-footer">
          <button
            class="tpk-btn tpk-btn--secondary"
            @click="closeThirdPartyModal"
          >
            {{ t("kycTplDetail_btn_cancel") }}
          </button>
          <button
            class="tpk-btn tpk-btn--primary"
            :disabled="
              !modalGatewayId || !modalExternalTemplateId || savingThirdParty
            "
            @click="confirmThirdPartyBinding"
          >
            <i
              :class="
                savingThirdParty ? 'fas fa-spinner fa-spin' : 'fas fa-check'
              "
            ></i>
            {{ t("kycTplDetail_btn_apply") }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, nextTick, watch } from "vue";
import { kycTemplateService } from "@/services/kycTemplateService";
import { externalKycGatewayService } from "@/services/externalKycGatewayService";
import QuestionList from "./QuestionList.vue";
import RuleList from "./RuleList.vue";
import DocumentList from "./DocumentList.vue";
import CountriesModal from "./CountriesModal.vue";
import CategoryModal from "./CategoryModal.vue";
import RuleModal from "./RuleModal.vue";
import { formatNumber } from "@/utils/helpers";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t, tParams, languageStore } = useAdminI18n();
const dateLocale = () =>
  languageStore.currentLanguage === "zh" ? "zh-CN" : "en-US";

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

// Refs
const templateNameRef = ref(null);
const templateDescRef = ref(null);

// State
const localTemplate = reactive({ ...props.template });
const originalTemplate = { ...props.template };
const editingField = ref(null);
const hasTemplateChanges = ref(false);
const statusChanged = ref(false);
const isThirdPartyEnabled = ref(props.template.isThirdPartyEnabled || false);
const isAutoApproveEnabled = ref(props.template.isAutoApproveEnabled || false);

const questions = ref([]);
const categories = ref([]);
const rules = ref([]);
const documents = ref([]);
const choiceQuestions = ref([]);

const showCountriesModal = ref(false);
const showCategoryModal = ref(false);
const showRuleModal = ref(false);
const editingRule = ref(null);

// === 第三方 KYC 绑定相关状态 ===
// 直接保存"当前绑定的 external template + 它的 gateway"对象，用于只读展示
// 数据由 GET /external-kyc-gateways/template/{id} 一次性返回
const boundExternalTemplate = ref(null);

const boundGatewayName = computed(() => {
  const gw = boundExternalTemplate.value?.gateway;
  return gw?.displayName || gw?.provider || "";
});
const boundExternalTemplateName = computed(() => {
  const tpl = boundExternalTemplate.value;
  if (!tpl)
    return localTemplate.externalTemplateId
      ? `#${localTemplate.externalTemplateId}`
      : "";
  return tpl.displayName || tpl.externalLevelName;
});

// 全量 gateway / per-gateway templates 只在 Modal 打开时按需加载
const allGateways = ref([]);
const externalTemplatesByGateway = ref({}); // { [gatewayId]: Array<template> }
const loadingExternalTemplates = ref(false);

const enabledGateways = computed(() =>
  allGateways.value.filter((g) => g.isEnabled),
);

// === Modal 内部状态 ===
const showThirdPartyModal = ref(false);
const modalGatewayId = ref("");
const modalExternalTemplateId = ref("");
const savingThirdParty = ref(false);

const modalActiveExternalTemplates = computed(() => {
  const list = externalTemplatesByGateway.value[modalGatewayId.value] || [];
  return list.filter((tpl) => tpl.isActive);
});

// Methods
const loadTemplateData = async () => {
  try {
    const [questionsRes, categoriesRes, rulesRes, documentsRes] =
      await Promise.all([
        kycTemplateService.getTemplateQuestions(
          props.template.templateId,
          false,
        ), // false = 获取所有问题（包括inactive）
        kycTemplateService.getTemplateCategories(props.template.templateId),
        kycTemplateService.getTemplateRules(props.template.templateId),
        kycTemplateService.getTemplateDocuments(props.template.templateId),
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

    // Load choice questions for rule configuration
    const choiceRes = await kycTemplateService.getChoiceQuestions(
      props.template.templateId,
    );
    if (choiceRes.success) choiceQuestions.value = choiceRes.data.questions;
  } catch (error) {
    console.error("Failed to load template data:", error);
  }
};

// 问题列表变更后：刷新 Detail 数据并通知父级刷新模板列表（使列表中的 Question 总数同步更新）
const onQuestionsRefresh = async () => {
  await loadTemplateData();
  emit("refresh");
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
      tParams("kycTplDetail_confirm_statusChange", "Change?", {
        from: formatStatus(originalTemplate.status),
        to: formatStatus(localTemplate.status),
      }),
    )
  ) {
    // 恢复原值
    localTemplate.status = originalTemplate.status;
    statusChanged.value = false;
    return;
  }

  try {
    const response = await kycTemplateService.updateTemplate(
      props.template.templateId,
      {
        status: localTemplate.status,
      },
    );

    if (response.success) {
      originalTemplate.status = localTemplate.status;
      statusChanged.value = false;
      alert(
        tParams("kycTplDetail_alert_statusOk", "OK", {
          status: formatStatus(localTemplate.status),
        }),
      );
      emit("refresh");
    } else {
      // 恢复原值
      localTemplate.status = originalTemplate.status;
      statusChanged.value = false;
      alert(
        tParams("kycTplDetail_alert_statusFailed", "Failed: {msg}", {
          msg: response.message || t("common_unknownError"),
        }),
      );
    }
  } catch (error) {
    console.error("Failed to update status:", error);
    // 恢复原值
    localTemplate.status = originalTemplate.status;
    statusChanged.value = false;
    alert(t("kycTplDetail_alert_statusErr"));
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
    const response = await kycTemplateService.updateTemplate(
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
      alert(t("kycTplDetail_alert_saveInfoOk"));
      emit("refresh");
    } else {
      alert(
        tParams("kycTplDetail_alert_saveInfoFailed", "Failed: {msg}", {
          msg: response.message || t("common_unknownError"),
        }),
      );
    }
  } catch (error) {
    console.error("Failed to save template:", error);
    alert(t("kycTplDetail_alert_saveInfoErr"));
  }
};

const toggleThirdPartyKyc = async () => {
  if (!props.hasEditTemplatePermission) {
    return;
  }

  // 当前是 OFF 想开启 → 必须先配置 platform + template（在 Modal 里），不直接动 isThirdPartyEnabled
  if (!isThirdPartyEnabled.value) {
    await openThirdPartyModal("enable");
    return;
  }

  // 当前是 ON 想停用 → 简单确认即可（不动 provider / externalTemplateId，留作下次启用）
  if (!confirm(t("kycTplDetail_confirm_thirdPartyOff"))) return;
  await applyThirdPartyBinding({ isThirdPartyEnabled: false });
  alert(t("kycTplDetail_alert_thirdPartyOffOk"));
};

const toggleAutoApprove = async () => {
  if (!props.hasEditTemplatePermission) {
    return;
  }

  const newValue = !isAutoApproveEnabled.value;

  if (newValue) {
    if (confirm(t("kycTplDetail_confirm_autoApproveOn"))) {
      isAutoApproveEnabled.value = newValue;
      await updateTemplateSettings();
      alert(t("kycTplDetail_alert_autoApproveOnOk"));
    }
  } else {
    if (confirm(t("kycTplDetail_confirm_autoApproveOff"))) {
      isAutoApproveEnabled.value = newValue;
      await updateTemplateSettings();
      alert(t("kycTplDetail_alert_autoApproveOffOk"));
    }
  }
};

const updateTemplateSettings = async () => {
  try {
    const response = await kycTemplateService.updateTemplate(
      props.template.templateId,
      {
        isThirdPartyEnabled: isThirdPartyEnabled.value,
        isAutoApproveEnabled: isAutoApproveEnabled.value,
      },
    );

    if (!response.success) {
      alert(
        tParams("kycTplDetail_alert_settingsFailed", "Failed: {msg}", {
          msg: response.message || t("common_unknownError"),
        }),
      );
    }
  } catch (error) {
    console.error("Failed to update settings:", error);
    alert(t("kycTplDetail_alert_settingsErr"));
  }
};

// === 第三方绑定：拉网关 / 切平台 / 切模板 ===

const loadGateways = async () => {
  try {
    const res = await externalKycGatewayService.list();
    if (res.success) {
      allGateways.value = res.data || [];
    }
  } catch (error) {
    console.error("Failed to load external KYC gateways:", error);
  }
};

const loadExternalTemplates = async (gatewayId) => {
  if (!gatewayId) return;
  // 已经拉过就别再拉
  if (externalTemplatesByGateway.value[gatewayId]) return;
  loadingExternalTemplates.value = true;
  try {
    const res = await externalKycGatewayService.listTemplates(gatewayId);
    if (res.success) {
      externalTemplatesByGateway.value = {
        ...externalTemplatesByGateway.value,
        [gatewayId]: res.data || [],
      };
    }
  } catch (error) {
    console.error("Failed to load external templates:", error);
  } finally {
    loadingExternalTemplates.value = false;
  }
};

// === Modal 流程：开启 / 编辑 第三方绑定 ===

const openThirdPartyModal = async (mode = "edit") => {
  if (!props.hasEditTemplatePermission) return;
  // 先把全量网关数据备好（modal 里 platform 下拉要用）
  if (allGateways.value.length === 0) {
    await loadGateways();
  }
  // 编辑模式：用当前绑定预填（boundExternalTemplate 里已经带了 gateway 子对象）
  const currentGateway = boundExternalTemplate.value?.gateway;
  if (mode === "edit" && currentGateway) {
    modalGatewayId.value = String(currentGateway.id);
    await loadExternalTemplates(currentGateway.id);
    modalExternalTemplateId.value = localTemplate.externalTemplateId
      ? String(localTemplate.externalTemplateId)
      : "";
  } else {
    modalGatewayId.value = "";
    modalExternalTemplateId.value = "";
  }
  showThirdPartyModal.value = true;
};

const closeThirdPartyModal = () => {
  showThirdPartyModal.value = false;
  savingThirdParty.value = false;
};

const onModalGatewayChange = async () => {
  // 切平台必须清空 template 选择
  modalExternalTemplateId.value = "";
  if (modalGatewayId.value) {
    await loadExternalTemplates(modalGatewayId.value);
  }
};

const confirmThirdPartyBinding = async () => {
  if (!modalGatewayId.value || !modalExternalTemplateId.value) return;
  const gateway = allGateways.value.find(
    (g) => String(g.id) === String(modalGatewayId.value),
  );
  if (!gateway) return;

  savingThirdParty.value = true;
  const ok = await applyThirdPartyBinding({
    isThirdPartyEnabled: true,
    thirdPartyProvider: gateway.provider,
    externalTemplateId: Number(modalExternalTemplateId.value),
  });
  savingThirdParty.value = false;

  if (ok) {
    closeThirdPartyModal();
    alert(t("kycTplDetail_alert_thirdPartyOnOk"));
  }
};

/**
 * 调用后端原子接口写入第三方绑定。
 * 返回是否成功，供调用方决定后续 UI 行为（关闭 modal / 弹提示）。
 */
const applyThirdPartyBinding = async (payload) => {
  try {
    const response = await kycTemplateService.updateThirdPartyBinding(
      props.template.templateId,
      payload,
    );
    if (!response.success) {
      alert(
        tParams("kycTplDetail_alert_externalBindingFailed", "Failed: {msg}", {
          msg: response.message || t("common_unknownError"),
        }),
      );
      return false;
    }
    // 后端返回最新模板对象，本地立刻反映
    const updated = response.data || {};
    if (Object.prototype.hasOwnProperty.call(updated, "isThirdPartyEnabled")) {
      isThirdPartyEnabled.value = !!updated.isThirdPartyEnabled;
      localTemplate.isThirdPartyEnabled = updated.isThirdPartyEnabled;
    }
    if (Object.prototype.hasOwnProperty.call(updated, "thirdPartyProvider")) {
      localTemplate.thirdPartyProvider = updated.thirdPartyProvider;
    }
    if (Object.prototype.hasOwnProperty.call(updated, "externalTemplateId")) {
      localTemplate.externalTemplateId = updated.externalTemplateId;
    }
    // 启用 → 拉绑定信息；停用 → 清掉绑定信息并把 questions/rules/docs 重新拉一遍
    if (isThirdPartyEnabled.value) {
      await hydrateExternalBindingFromTemplate();
    } else {
      boundExternalTemplate.value = null;
      if (categories.value.length === 0 && questions.value.length === 0) {
        await loadTemplateData();
      }
    }
    emit("refresh");
    return true;
  } catch (error) {
    console.error("Failed to save third-party binding:", error);
    alert(
      tParams("kycTplDetail_alert_externalBindingFailed", "Failed: {msg}", {
        msg: error?.message || "unknown",
      }),
    );
    return false;
  }
};

/**
 * 第三方模式下，仅按 externalTemplateId 拉绑定信息（一条请求拿到 level + gateway）。
 * 不会拉全量 gateway，那个等用户点 Change Binding 才加载。
 */
const hydrateExternalBindingFromTemplate = async () => {
  if (!isThirdPartyEnabled.value || !localTemplate.externalTemplateId) {
    boundExternalTemplate.value = null;
    return;
  }
  try {
    const res = await externalKycGatewayService.getTemplateWithGateway(
      localTemplate.externalTemplateId,
    );
    if (res.success) {
      boundExternalTemplate.value = res.data || null;
    }
  } catch (err) {
    console.error("Failed to load bound external template:", err);
  }
};

const openCountriesModal = () => {
  showCountriesModal.value = true;
};

const handleCountriesSave = async (countries) => {
  localTemplate.countries = countries;
  showCountriesModal.value = false;
  emit("refresh");
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
  if (status === "active") return t("kycTpl_status_active");
  if (status === "inactive") return t("kycTpl_status_inactive");
  if (status === "draft") return t("kycTpl_status_draft");
  return status;
};

const formatDate = (dateString) => {
  if (!dateString) return t("addrVerif_na");
  const date = new Date(dateString);
  return date.toLocaleDateString(dateLocale(), {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
};

// Watch for template prop changes
watch(
  () => props.template,
  (newTemplate) => {
    // 更新本地状态
    Object.assign(localTemplate, newTemplate);
    Object.assign(originalTemplate, newTemplate);
    isThirdPartyEnabled.value = newTemplate.isThirdPartyEnabled || false;
    isAutoApproveEnabled.value = newTemplate.isAutoApproveEnabled || false;
    statusChanged.value = false;
    hasTemplateChanges.value = false;
    // 第三方模式只重拉绑定信息，不拉全量 gateway，也不拉 question/rules/docs
    if (isThirdPartyEnabled.value) {
      hydrateExternalBindingFromTemplate();
    }
  },
  { deep: true },
);

// Lifecycle
onMounted(async () => {
  // 第三方模式下：questions/rules/documents 都不渲染，所以也别拉数据
  if (isThirdPartyEnabled.value) {
    await hydrateExternalBindingFromTemplate();
  } else {
    await loadTemplateData();
  }
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

.btn-edit-section {
  padding: 6px 12px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.btn-edit-section:hover {
  background: var(--color-brand-solid);
  color: white;
  transform: translateY(-1px);
}

.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: var(--radius-lg);
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
}

.status-badge.active {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.status-badge.inactive {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.status-badge.draft {
  background: var(--color-warning-soft);
  color: var(--color-warning);
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

.status-select:disabled:hover {
  border-color: var(--color-border);
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

.status-change-indicator i {
  font-size: 10px;
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

.toggle-switch.disabled.active::after {
  background: var(--color-surface);
}

.toggle-switch.disabled:hover {
  cursor: not-allowed;
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

.third-party-bind-value {
  font-weight: 600;
  color: var(--color-ink);
}

.third-party-actions {
  display: flex;
  justify-content: flex-end;
  margin-top: 10px;
}

.btn-third-party {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-third-party:hover {
  border-color: var(--color-brand);
  color: var(--color-brand);
  background: var(--color-surface-soft);
}

/* === 第三方绑定 Modal === */
.tpk-modal-mask {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1100;
  padding: 20px;
}

.tpk-modal-dialog {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  width: 100%;
  max-width: 480px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
}

.tpk-modal-header {
  padding: 18px 24px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.tpk-modal-header h3 {
  margin: 0;
  font-size: 18px;
  color: var(--color-ink);
}

.tpk-modal-header h3 i {
  color: var(--color-brand);
  margin-right: 8px;
}

.tpk-modal-close {
  background: none;
  border: none;
  font-size: 18px;
  color: var(--color-faint);
  cursor: pointer;
}

.tpk-modal-body {
  padding: 24px;
  overflow-y: auto;
}

.tpk-modal-footer {
  padding: 16px 24px;
  border-top: 2px solid var(--color-border);
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

.tpk-form-group {
  margin-bottom: 16px;
}

.tpk-form-group label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text);
  margin-bottom: 6px;
}

.tpk-form-group label i {
  color: var(--color-brand);
  margin-right: 4px;
}

.tpk-select {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  font-size: 14px;
  background: var(--color-surface);
  color: var(--color-ink);
  cursor: pointer;
}

.tpk-select:disabled {
  background: var(--color-surface-soft);
  color: var(--color-faint);
  cursor: not-allowed;
}

.tpk-hint {
  margin: 6px 0 0 0;
  font-size: 12px;
  color: var(--color-muted);
}

.tpk-hint i {
  margin-right: 4px;
  color: var(--color-warning);
}

.tpk-btn {
  padding: 8px 18px;
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.tpk-btn--secondary {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.tpk-btn--secondary:hover {
  background: var(--color-border);
}

.tpk-btn--primary {
  background: var(--color-brand-solid);
  color: #fff;
}

.tpk-btn--primary:hover:not(:disabled) {
  background: var(--color-brand-strong);
}

.tpk-btn--primary:disabled {
  background: var(--color-border-strong);
  cursor: not-allowed;
}

.countries-display {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 10px;
}

.country-tag {
  background: var(--color-brand-soft);
  color: var(--color-brand);
  padding: 4px 10px;
  border-radius: var(--radius-lg);
  font-size: 12px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 5px;
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
  background: var(--color-success-solid);
  color: white;
  box-shadow: 0 2px 8px rgba(72, 187, 120, 0.3);
}

.btn-success:hover {
  background: var(--color-success-solid);
  transform: translateY(-2px);
}

@media (max-width: 768px) {
  .detail-sections {
    grid-template-columns: 1fr;
  }
}
</style>
