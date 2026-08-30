<template>
  <div class="modal-overlay show" @click="$emit('close')">
    <div class="modal" @click.stop>
      <div class="modal-header">
        <h2 class="modal-title">
          <i class="fas" :class="template ? 'fa-edit' : 'fa-plus-circle'"></i>
          {{
            template
              ? t("emailTplModal_titleEdit")
              : t("emailTplModal_titleCreate")
          }}
        </h2>
        <button class="modal-close" @click="$emit('close')">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="modal-body">
        <form @submit.prevent="handleSubmit">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="templateKey">
                {{ t("emailTplModal_labelTemplateKey") }}
                <span class="label-hint">{{
                  t("emailTplModal_hintTemplateKeyUnique")
                }}</span>
              </label>
              <input
                type="text"
                class="form-input"
                id="templateKey"
                v-model="formData.templateKey"
                :placeholder="t('emailTplModal_placeholderTemplateKey')"
                :disabled="!!template"
                required
              />
              <small v-if="template" class="form-hint">{{
                t("emailTplModal_hintKeyLocked")
              }}</small>
            </div>

            <div class="form-group">
              <label class="form-label" for="templateName">{{
                t("emailTplModal_labelTemplateName")
              }}</label>
              <input
                type="text"
                class="form-input"
                id="templateName"
                v-model="formData.templateName"
                :placeholder="t('emailTplModal_placeholderTemplateName')"
                required
              />
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="category">{{
                t("emailTplModal_labelCategory")
              }}</label>
              <select
                class="form-select"
                id="category"
                v-model="formData.category"
                required
              >
                <option value="">
                  {{ t("emailTplModal_selectCategory") }}
                </option>
                <option v-for="cat in categories" :key="cat" :value="cat">
                  {{ formatCategory(cat) }}
                </option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label" for="recipientType">{{
                t("emailTplModal_labelRecipientType")
              }}</label>
              <select
                class="form-select"
                id="recipientType"
                v-model="formData.recipientType"
                required
              >
                <option value="client">
                  {{ t("emailTpl_recipient_client") }}
                </option>
                <option value="admin">
                  {{ t("emailTpl_recipient_admin") }}
                </option>
                <option value="both">{{ t("emailTpl_recipient_both") }}</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="emailSubject">
              {{ t("emailTplModal_labelEmailSubject") }}
            </label>
            <input
              type="text"
              class="form-input"
              id="emailSubject"
              v-model="formData.emailSubject"
              :placeholder="t('emailTplModal_placeholderEmailSubject')"
              required
            />
          </div>

          <div class="form-group">
            <label class="form-label" for="emailBody">
              {{ t("emailTplModal_labelEmailBody") }}
              <span class="label-hint">{{
                t("emailTplModal_hintEmailBodyVars")
              }}</span>
            </label>
            <div class="variable-warning">
              <i class="fas fa-exclamation-triangle"></i>
              <div>
                <strong>{{ t("emailTplModal_warnTitle") }}</strong>
                {{ t("emailTplModal_warnBody") }}
              </div>
            </div>
            <textarea
              class="form-textarea"
              id="emailBody"
              v-model="formData.emailBody"
              rows="12"
              :placeholder="t('emailTplModal_placeholderEmailBody')"
              required
            ></textarea>
          </div>

          <div class="form-group">
            <label class="form-label" for="variables">
              {{ t("emailTplModal_labelTemplateVariables") }}
              <span class="label-hint">{{
                t("emailTplModal_hintTemplateVariables")
              }}</span>
            </label>
            <div class="variable-info">
              <i class="fas fa-info-circle"></i>
              <span>{{ t("emailTplModal_varInfo") }}</span>
            </div>
            <div class="variables-list">
              <div
                v-for="(variable, index) in variablesList"
                :key="index"
                class="variable-item"
              >
                <div class="variable-input-group">
                  <div class="variable-name-input">
                    <label class="variable-label">{{
                      t("emailTplModal_labelVariableName")
                    }}</label>
                    <input
                      type="text"
                      class="form-input variable-name"
                      v-model="variable.name"
                      :placeholder="t('emailTplModal_placeholderVariableName')"
                      @blur="updateVariablesFromList"
                    />
                  </div>
                  <div class="variable-desc-input">
                    <label class="variable-label">{{
                      t("emailTplModal_labelVariableDesc")
                    }}</label>
                    <input
                      type="text"
                      class="form-input variable-desc"
                      v-model="variable.description"
                      :placeholder="t('emailTplModal_placeholderVariableDesc')"
                      @blur="updateVariablesFromList"
                    />
                  </div>
                  <button
                    type="button"
                    class="btn-remove-variable"
                    @click="removeVariable(index)"
                    :title="t('emailTplModal_removeVariableTitle')"
                  >
                    <i class="fas fa-times"></i>
                  </button>
                </div>
              </div>
              <button
                type="button"
                class="btn-add-variable"
                @click="addVariable"
              >
                <i class="fas fa-plus"></i>
                {{ t("emailTplModal_btnAddVariable") }}
              </button>
            </div>
            <small class="form-hint">{{
              t("emailTplModal_hintCurlyBracesFooter")
            }}</small>
          </div>

          <div class="form-group">
            <label class="form-label" for="description">{{
              t("emailTplModal_labelDescription")
            }}</label>
            <textarea
              class="form-textarea"
              id="description"
              v-model="formData.description"
              rows="3"
              :placeholder="t('emailTplModal_placeholderDescription')"
            ></textarea>
          </div>

          <div class="form-group">
            <label class="form-checkbox-label">
              <input type="checkbox" v-model="formData.isActive" />
              <span>{{ t("emailTplModal_checkboxActive") }}</span>
            </label>
          </div>
        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" @click="$emit('close')">
          {{ t("emailTplModal_btnCancel") }}
        </button>
        <button class="btn btn-secondary" @click="showPreview = true">
          <i class="fas fa-eye"></i> {{ t("emailTplModal_btnPreview") }}
        </button>
        <button
          class="btn btn-primary"
          @click="handleSubmit"
          :disabled="saving"
        >
          <i class="fas fa-spinner fa-spin" v-if="saving"></i>
          <i class="fas fa-save" v-else></i>
          {{
            saving
              ? t("emailTplModal_saving")
              : template
                ? t("emailTplModal_btnUpdateTemplate")
                : t("emailTplModal_btnCreateTemplate")
          }}
        </button>
      </div>
    </div>

    <!-- Preview Modal -->
    <div
      v-if="showPreview"
      class="preview-overlay"
      @click="showPreview = false"
    >
      <div class="preview-modal" @click.stop>
        <div class="preview-header">
          <h3 class="preview-title">
            <i class="fas fa-envelope"></i>
            {{ t("emailTplModal_previewTitle") }}
          </h3>
          <button class="preview-close" @click="showPreview = false">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="preview-body">
          <div class="email-preview">
            <div class="email-header">
              <div class="email-from">
                <strong>{{ t("emailTplModal_previewFrom") }}</strong>
                noreply@platform.com
              </div>
              <div class="email-to">
                <strong>{{ t("emailTplModal_previewTo") }}</strong>
                recipient@example.com
              </div>
              <div class="email-subject">
                <strong>{{ t("emailTplModal_previewSubject") }}</strong>
                {{ formData.emailSubject || t("emailTplModal_noSubject") }}
              </div>
            </div>
            <div class="email-content" v-html="previewContent"></div>
          </div>
        </div>
        <div class="preview-footer">
          <button class="btn btn-secondary" @click="showPreview = false">
            {{ t("emailTplModal_btnClose") }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from "vue";
import emailTemplateApi from "@/services/emailTemplateApi";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t } = useAdminI18n();

const props = defineProps({
  template: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(["close", "save"]);

const saving = ref(false);
const showPreview = ref(false);
const variablesList = ref([]);

// 邮件模板分类列表（前端写死）
const categories = ref([
  "general",
  "notification",
  "registration",
  "kyc",
  "transaction",
  "account",
]);

const previewContent = computed(() => {
  return (
    formData.value.emailBody ||
    `<p style="color: var(--color-faint);">${t("emailTplModal_noContent")}</p>`
  );
});

const formData = ref({
  templateKey: "",
  templateName: "",
  category: "",
  emailSubject: "",
  emailBody: "",
  recipientType: "client",
  description: "",
  variables: {},
  isActive: true,
});

// 从变量列表更新 formData.variables
const updateVariablesFromList = () => {
  const variables = {};
  variablesList.value.forEach((item) => {
    if (item.name && item.name.trim()) {
      variables[item.name.trim()] = item.description || "";
    }
  });
  formData.value.variables = variables;
};

// 从 formData.variables 更新变量列表
const updateListFromVariables = () => {
  if (
    formData.value.variables &&
    typeof formData.value.variables === "object"
  ) {
    variablesList.value = Object.keys(formData.value.variables).map((key) => ({
      name: key,
      description: formData.value.variables[key] || "",
    }));
  } else {
    variablesList.value = [];
  }
};

// 添加新变量
const addVariable = () => {
  variablesList.value.push({
    name: "",
    description: "",
  });
};

// 删除变量
const removeVariable = (index) => {
  variablesList.value.splice(index, 1);
  updateVariablesFromList();
};

const formatCategory = (category) => {
  if (!category) return "";
  return t(`emailTpl_cat_${category}`, category);
};

watch(
  () => props.template,
  (newTemplate) => {
    if (newTemplate) {
      // 编辑模式：更新所有字段
      formData.value.templateKey = newTemplate.templateKey || "";
      formData.value.templateName = newTemplate.templateName || "";
      formData.value.category = newTemplate.category || "";
      formData.value.emailSubject = newTemplate.emailSubject || "";
      formData.value.emailBody = newTemplate.emailBody || "";
      formData.value.recipientType = newTemplate.recipientType || "client";
      formData.value.description = newTemplate.description || "";

      // 处理variables字段：如果是字符串则解析，如果是对象则直接使用
      if (newTemplate.variables) {
        if (typeof newTemplate.variables === "string") {
          try {
            formData.value.variables = JSON.parse(newTemplate.variables);
          } catch (e) {
            console.error("Failed to parse variables JSON:", e);
            formData.value.variables = {};
          }
        } else {
          formData.value.variables = newTemplate.variables;
        }
      } else {
        formData.value.variables = {};
      }

      formData.value.isActive =
        newTemplate.isActive !== undefined ? !!newTemplate.isActive : true;

      // 更新变量列表显示
      updateListFromVariables();
    } else {
      // 新建模式：重置所有字段，但保持响应式
      formData.value.templateKey = "";
      formData.value.templateName = "";
      formData.value.category = "";
      formData.value.emailSubject = "";
      formData.value.emailBody = "";
      formData.value.recipientType = "client";
      formData.value.description = "";
      formData.value.variables = {};
      formData.value.isActive = true;
      variablesList.value = [];
    }
  },
  { immediate: true },
);

const handleSubmit = async () => {
  // 确保 variables 已更新
  updateVariablesFromList();

  saving.value = true;

  try {
    let response;
    if (props.template) {
      response = await emailTemplateApi.updateTemplate(
        props.template.id,
        formData.value,
      );
    } else {
      response = await emailTemplateApi.createTemplate(formData.value);
    }

    if (response.success) {
      emit("save");
    } else {
      alert(response.message || t("emailTplModal_errSave"));
    }
  } catch (err) {
    alert(
      err.response?.data?.message || err.message || t("emailTplModal_errSave"),
    );
  } finally {
    saving.value = false;
  }
};
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  opacity: 0;
  transition: opacity 0.3s ease;
}

.modal-overlay.show {
  opacity: 1;
}

.modal {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  width: 90%;
  max-width: 900px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  animation: slideUp 0.3s ease;
}

@keyframes slideUp {
  from {
    transform: translateY(30px);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 24px 30px;
  border-bottom: 1px solid var(--color-border);
}

.modal-title {
  font-size: 24px;
  color: var(--color-ink);
  margin: 0;
}

.modal-title i {
  margin-right: 10px;
  color: var(--color-brand);
}

.modal-close {
  background: none;
  border: none;
  font-size: 24px;
  color: var(--color-muted);
  cursor: pointer;
  padding: 0;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-sm);
  transition: all 0.2s ease;
}

.modal-close:hover {
  background: var(--color-surface-soft);
  color: var(--color-ink);
}

.modal-body {
  padding: 30px;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

.form-group {
  margin-bottom: 20px;
}

.form-label {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 8px;
}

.label-hint {
  font-weight: 400;
  color: var(--color-muted);
  font-size: 12px;
  margin-left: 5px;
}

.form-input,
.form-select,
.form-textarea {
  width: 100%;
  padding: 10px 14px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: 14px;
  transition: all 0.2s ease;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.form-textarea {
  font-family: monospace;
  resize: vertical;
}

.form-hint {
  display: block;
  font-size: 12px;
  color: var(--color-muted);
  margin-top: 5px;
}

.variable-warning {
  background: var(--color-warning-soft);
  border: 1px solid var(--color-warning-border);
  padding: 12px 15px;
  border-radius: var(--radius-sm);
  margin-bottom: 12px;
  display: flex;
  align-items: flex-start;
  gap: 10px;
  font-size: 13px;
  color: var(--color-warning);
}

.variable-warning i {
  color: var(--color-warning-border);
  font-size: 16px;
  margin-top: 2px;
  flex-shrink: 0;
}

.variable-warning strong {
  display: block;
  margin-bottom: 4px;
}

.variable-info {
  background: var(--color-info-soft);
  border: 1px solid #b3d9ff;
  padding: 10px 12px;
  border-radius: var(--radius-sm);
  margin-bottom: 10px;
  display: flex;
  align-items: flex-start;
  gap: 8px;
  font-size: 12px;
  color: var(--color-info);
}

.variable-info i {
  color: var(--color-info);
  font-size: 14px;
  margin-top: 2px;
  flex-shrink: 0;
}

.variables-list {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  padding: 12px;
  background: var(--color-surface-soft);
  margin-bottom: 8px;
}

.variable-item {
  margin-bottom: 12px;
}

.variable-item:last-of-type {
  margin-bottom: 0;
}

.variable-input-group {
  display: grid;
  grid-template-columns: 1fr 2fr auto;
  gap: 12px;
  align-items: end;
}

.variable-name-input,
.variable-desc-input {
  display: flex;
  flex-direction: column;
}

.variable-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text);
  margin-bottom: 6px;
}

.variable-name,
.variable-desc {
  margin: 0;
}

.btn-remove-variable {
  background: var(--color-danger-solid);
  color: white;
  border: none;
  border-radius: var(--radius-sm);
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
  flex-shrink: 0;
}

.btn-remove-variable:hover {
  background: var(--color-danger-solid);
}

.btn-add-variable {
  background: var(--color-brand-solid);
  color: white;
  border: none;
  border-radius: var(--radius-sm);
  padding: 10px 16px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 12px;
}

.btn-add-variable:hover {
  background: var(--color-brand-strong);
}

.btn-add-variable i {
  font-size: 12px;
}

.form-checkbox-label {
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
}

.form-checkbox-label input[type="checkbox"] {
  width: 18px;
  height: 18px;
  cursor: pointer;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 24px 30px;
  border-top: 1px solid var(--color-border);
}

.btn {
  padding: 10px 20px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-sm {
  padding: 6px 12px;
  font-size: 12px;
}

.btn-primary {
  background: var(--color-brand-solid);
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: var(--color-brand-strong);
}

.btn-secondary {
  background: var(--color-border);
  color: var(--color-text);
}

.btn-secondary:hover {
  background: var(--color-border-strong);
}

.btn-danger {
  background: var(--color-danger-solid);
  color: white;
}

.btn-danger:hover {
  background: var(--color-danger-solid);
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Preview Modal Styles */
.preview-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.7);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
  animation: fadeIn 0.2s ease;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.preview-modal {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  width: 90%;
  max-width: 800px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
  animation: slideUp 0.3s ease;
}

.preview-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid var(--color-border);
}

.preview-title {
  font-size: 20px;
  color: var(--color-ink);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 10px;
}

.preview-title i {
  color: var(--color-brand);
}

.preview-close {
  background: none;
  border: none;
  font-size: 20px;
  color: var(--color-muted);
  cursor: pointer;
  padding: 5px;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-sm);
  transition: all 0.2s ease;
}

.preview-close:hover {
  background: var(--color-surface-soft);
  color: var(--color-ink);
}

.preview-body {
  padding: 24px;
  overflow-y: auto;
  flex: 1;
}

.email-preview {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  overflow: hidden;
}

.email-header {
  background: var(--color-surface-soft);
  padding: 16px 20px;
  border-bottom: 1px solid var(--color-border);
}

.email-header > div {
  margin-bottom: 8px;
  font-size: 14px;
  color: var(--color-text);
}

.email-header > div:last-child {
  margin-bottom: 0;
}

.email-header strong {
  color: var(--color-ink);
  margin-right: 8px;
}

.email-content {
  padding: 24px;
  min-height: 200px;
  color: var(--color-ink);
  line-height: 1.6;
}

.email-content :deep(p) {
  margin: 0 0 12px 0;
}

.email-content :deep(p:last-child) {
  margin-bottom: 0;
}

.email-content :deep(a) {
  color: var(--color-brand);
  text-decoration: none;
}

.email-content :deep(a:hover) {
  text-decoration: underline;
}

.email-content :deep(ul),
.email-content :deep(ol) {
  margin: 12px 0;
  padding-left: 24px;
}

.email-content :deep(li) {
  margin: 6px 0;
}

.preview-footer {
  padding: 16px 24px;
  border-top: 1px solid var(--color-border);
  display: flex;
  justify-content: flex-end;
}
</style>
