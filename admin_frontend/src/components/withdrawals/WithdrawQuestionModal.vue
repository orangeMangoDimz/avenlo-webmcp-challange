<template>
  <div class="modal-overlay show" @click="$emit('close')">
    <div class="modal" @click.stop>
      <div class="modal-header">
        <h2 class="modal-title">
          <i class="fas" :class="question ? 'fa-edit' : 'fa-plus-circle'"></i>
          {{
            question
              ? t("withdrawKycQModal_titleEdit")
              : t("withdrawKycQModal_titleCreate")
          }}
        </h2>
        <button class="modal-close" @click="$emit('close')">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="modal-body">
        <form @submit.prevent="handleSubmit">
          <div class="form-group">
            <label class="form-label">{{
              t("withdrawKycQModal_labelQuestionText")
            }}</label>
            <textarea
              class="form-textarea"
              v-model="formData.questionText"
              :placeholder="t('withdrawKycQModal_placeholderQuestionText')"
              required
            ></textarea>
          </div>

          <div class="form-group">
            <label class="form-label">{{
              t("withdrawKycQModal_labelQuestionType")
            }}</label>
            <select
              class="form-select"
              v-model="formData.questionType"
              required
            >
              <option value="">
                {{ t("withdrawKycQModal_selectTypePlaceholder") }}
              </option>
              <option
                v-for="type in questionTypes"
                :key="type.value"
                :value="type.value"
              >
                {{ type.label }}
              </option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">{{
              t("withdrawKycQModal_labelHelp")
            }}</label>
            <input
              type="text"
              class="form-input"
              v-model="formData.helpText"
              :placeholder="t('withdrawKycQModal_placeholderHelp')"
            />
          </div>

          <div class="form-group">
            <label class="form-label">{{
              t("withdrawKycQModal_labelValidation")
            }}</label>
            <input
              type="text"
              class="form-input"
              v-model="formData.validationRule"
              :placeholder="t('withdrawKycQModal_placeholderValidation')"
            />
          </div>

          <div v-if="isChoiceQuestion" class="form-group">
            <label class="form-label">{{
              t("withdrawKycQModal_labelAnswerOptions")
            }}</label>
            <div class="options-container">
              <div
                v-if="formData.separateOptionValue"
                class="option-column-labels"
              >
                <span>{{ t("withdrawKycQModal_colLabel") }}</span>
                <span>{{ t("withdrawKycQModal_colValue") }}</span>
              </div>
              <div
                v-for="(option, idx) in formData.options"
                :key="idx"
                class="option-item"
              >
                <div v-if="formData.separateOptionValue" class="option-pair">
                  <input
                    type="text"
                    class="option-input"
                    v-model="formData.options[idx].optionLabel"
                    :placeholder="
                      tParams(
                        'withdrawKycQModal_placeholderOptionLabel',
                        'Option {n} Label',
                        { n: idx + 1 },
                      )
                    "
                  />
                  <input
                    type="text"
                    class="option-input"
                    v-model="formData.options[idx].optionValue"
                    :placeholder="
                      tParams(
                        'withdrawKycQModal_placeholderOptionValue',
                        'Option {n} Value',
                        { n: idx + 1 },
                      )
                    "
                  />
                </div>
                <input
                  v-else
                  type="text"
                  class="option-input option-input-single"
                  v-model="formData.options[idx].optionLabel"
                  :placeholder="
                    tParams(
                      'withdrawKycQModal_placeholderOption',
                      'Option {n}',
                      { n: idx + 1 },
                    )
                  "
                />
                <button
                  type="button"
                  class="remove-option"
                  @click="removeOption(idx)"
                >
                  {{ t("withdrawKycQModal_btnRemove") }}
                </button>
              </div>
              <button type="button" class="add-option" @click="addOption">
                {{ t("withdrawKycQModal_btnAddOption") }}
              </button>
              <div class="option-toggle">
                <label class="form-checkbox">
                  <input
                    type="checkbox"
                    v-model="formData.separateOptionValue"
                  />
                  <span>{{ t("withdrawKycQModal_separateLabelValue") }}</span>
                </label>
                <small class="option-toggle-hint">
                  {{ t("withdrawKycQModal_separateHint") }}
                </small>
              </div>
            </div>
          </div>

          <div
            v-if="formData.questionType === 'file_upload'"
            class="form-group"
          >
            <label class="form-label">
              <i class="fas fa-file-alt"></i>
              {{ t("withdrawKycQModal_labelDocType") }}
            </label>
            <select
              class="form-select"
              v-model="formData.documentTypes"
              multiple
              style="min-height: 120px"
            >
              <option
                v-for="docType in availableDocumentTypes"
                :key="docType.value"
                :value="docType.value"
              >
                {{ docType.label }}
              </option>
            </select>
            <small
              style="
                color: var(--color-muted);
                font-size: 14px;
                margin-top: 8px;
                display: block;
              "
            >
              <i class="fas fa-info-circle"></i>
              {{ t("withdrawKycQModal_docMultiHint") }}
            </small>
          </div>

          <div class="form-group">
            <div class="form-checkbox">
              <input
                type="checkbox"
                id="isRequired"
                v-model="formData.isRequired"
              />
              <label for="isRequired">{{
                t("withdrawKycQModal_requiredQuestion")
              }}</label>
            </div>
          </div>

          <div class="form-group">
            <div class="form-checkbox">
              <input
                type="checkbox"
                id="isActive"
                v-model="formData.isActive"
              />
              <label for="isActive">{{ t("withdrawKycQModal_active") }}</label>
            </div>
          </div>
        </form>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" @click="$emit('close')">
          {{ t("withdrawKycQModal_btnCancel") }}
        </button>
        <button type="button" class="btn btn-primary" @click="handleSubmit">
          <i class="fas fa-save"></i> {{ t("withdrawKycQModal_btnSave") }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import { withdrawKycQuestionService } from "@/services/withdrawKycTemplateService";
import { useLanguageStore } from "@/stores/language";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { translateApiErrorMessage } from "@/i18n/adminI18nBridge";

const languageStore = useLanguageStore();
const { t, tParams } = useAdminI18n();

const props = defineProps({
  question: {
    type: Object,
    default: null,
  },
  categoryId: {
    type: Number,
    required: true,
  },
  templateId: {
    type: Number,
    required: true,
  },
  questionService: {
    type: Object,
    default: () => withdrawKycQuestionService,
  },
});

const emit = defineEmits(["close", "save"]);

const formData = ref({
  questionText: "",
  questionType: "",
  helpText: "",
  validationRule: "",
  options: [{ optionLabel: "", optionValue: "" }],
  separateOptionValue: false,
  documentTypes: [],
  isRequired: true,
  isActive: true,
});

const questionTypes = computed(() => {
  void languageStore.currentLanguage;
  return [
    { value: "text", label: t("withdrawKycQModal_type_text", "Text") },
    { value: "number", label: t("withdrawKycQModal_type_number", "Number") },
    { value: "email", label: t("withdrawKycQModal_type_email", "Email") },
    { value: "tel", label: t("withdrawKycQModal_type_tel", "Tel") },
    { value: "date", label: t("withdrawKycQModal_type_date", "Date") },
    {
      value: "single_choice",
      label: t("withdrawKycQModal_type_single_choice", "Single Choice"),
    },
    {
      value: "multiple_choice",
      label: t("withdrawKycQModal_type_multiple_choice", "Multiple Choice"),
    },
    { value: "yes_no", label: t("withdrawKycQModal_type_yes_no", "Yes/No") },
    {
      value: "file_upload",
      label: t("withdrawKycQModal_type_file_upload", "File Upload"),
    },
    {
      value: "textarea",
      label: t("withdrawKycQModal_type_textarea", "Textarea"),
    },
  ];
});

const availableDocumentTypes = computed(() => {
  void languageStore.currentLanguage;
  return [
    { value: "ID_CARD", label: t("withdrawKycQModal_docType_ID_CARD") },
    { value: "PASSPORT", label: t("withdrawKycQModal_docType_PASSPORT") },
    {
      value: "DRIVERS_LICENSE",
      label: t("withdrawKycQModal_docType_DRIVERS_LICENSE"),
    },
    {
      value: "PROOF_ADDRESS",
      label: t("withdrawKycQModal_docType_PROOF_ADDRESS"),
    },
    {
      value: "BANK_STATEMENT",
      label: t("withdrawKycQModal_docType_BANK_STATEMENT"),
    },
    {
      value: "UTILITY_BILL",
      label: t("withdrawKycQModal_docType_UTILITY_BILL"),
    },
    {
      value: "INCOME_PROOF",
      label: t("withdrawKycQModal_docType_INCOME_PROOF"),
    },
    {
      value: "TAX_DOCUMENT",
      label: t("withdrawKycQModal_docType_TAX_DOCUMENT"),
    },
    {
      value: "EMPLOYMENT_LETTER",
      label: t("withdrawKycQModal_docType_EMPLOYMENT_LETTER"),
    },
    {
      value: "BUSINESS_REGISTRATION",
      label: t("withdrawKycQModal_docType_BUSINESS_REGISTRATION"),
    },
    {
      value: "FINANCIAL_STATEMENT",
      label: t("withdrawKycQModal_docType_FINANCIAL_STATEMENT"),
    },
    { value: "OTHER", label: t("withdrawKycQModal_docType_OTHER") },
  ];
});

const isChoiceQuestion = computed(() => {
  return ["single_choice", "multiple_choice"].includes(
    formData.value.questionType,
  );
});

const createEmptyOption = () => ({
  optionLabel: "",
  optionValue: "",
});

const normalizeOption = (option) => {
  if (option && typeof option === "object") {
    const label =
      `${option.optionLabel || option.label || option.labal || option.optionValue || option.value || ""}`.trim();
    const value =
      `${option.optionValue || option.value || option.optionLabel || option.label || option.labal || ""}`.trim();

    if (!label && !value) {
      return null;
    }

    return {
      optionLabel: label || value,
      optionValue: value || label,
    };
  }

  const raw = `${option || ""}`.trim();
  if (!raw) {
    return null;
  }

  return {
    optionLabel: raw,
    optionValue: raw,
  };
};

const hasSeparateOptionValues = (options) => {
  return options.some((option) => {
    const normalized = normalizeOption(option);
    return normalized && normalized.optionLabel !== normalized.optionValue;
  });
};

watch(
  () => props.question,
  (newVal) => {
    if (newVal) {
      // 处理选择题选项
      let options = [createEmptyOption()];
      let separateOptionValue = false;
      if (
        newVal.options?.length &&
        ["single_choice", "multiple_choice"].includes(newVal.questionType)
      ) {
        options = newVal.options
          .map((opt) => normalizeOption(opt))
          .filter(Boolean);
        separateOptionValue = hasSeparateOptionValues(options);
        if (options.length === 0) {
          options = [createEmptyOption()];
        }
      }

      // 处理文档类型选项
      let documentTypes = [];
      if (newVal.options?.length && newVal.questionType === "file_upload") {
        documentTypes = newVal.options.map((opt) => opt.documentType);
      }

      formData.value = {
        questionText: newVal.questionText || "",
        questionType: newVal.questionType || "",
        helpText: newVal.helpText || "",
        validationRule: newVal.validationRules || "",
        options: options,
        separateOptionValue: separateOptionValue,
        documentTypes: documentTypes,
        isRequired: newVal.isRequired == 1,
        isActive: newVal.isActive == 1,
      };
    } else {
      // 新增问题时的初始化
      formData.value = {
        questionText: "",
        questionType: "",
        helpText: "",
        validationRule: "",
        options: [createEmptyOption()],
        separateOptionValue: false,
        documentTypes: [],
        isRequired: true,
        isActive: true,
      };
    }
  },
  { immediate: true },
);

const addOption = () => {
  formData.value.options.push(createEmptyOption());
};

const removeOption = (index) => {
  if (formData.value.options.length > 1) {
    formData.value.options.splice(index, 1);
  } else {
    alert(t("withdrawKycQModal_alert_minOneOption"));
  }
};

const handleSubmit = async () => {
  if (!formData.value.questionText || !formData.value.questionType) {
    alert(t("withdrawKycQModal_alert_fillRequired"));
    return;
  }

  if (isChoiceQuestion.value) {
    const validOptions = formData.value.options
      .map((option) => normalizeOption(option))
      .map((option) => {
        if (!option) {
          return null;
        }

        const optionLabel = option.optionLabel || option.optionValue;
        const optionValue = formData.value.separateOptionValue
          ? option.optionValue || optionLabel
          : optionLabel;

        if (!optionLabel || !optionValue) {
          return null;
        }

        return {
          optionLabel,
          optionValue,
        };
      })
      .filter(Boolean);
    if (validOptions.length === 0) {
      alert(t("withdrawKycQModal_alert_needChoiceOption"));
      return;
    }
    formData.value.options = validOptions;
  }

  if (
    formData.value.questionType === "file_upload" &&
    formData.value.documentTypes.length === 0
  ) {
    alert(t("withdrawKycQModal_alert_needDocType"));
    return;
  }

  const { separateOptionValue, ...payloadFormData } = formData.value;

  const payload = {
    ...payloadFormData,
    categoryId: props.categoryId,
    templateId: props.templateId,
  };

  try {
    let response;
    if (props.question) {
      response = await props.questionService.updateQuestion(
        props.question.id,
        payload,
      );
    } else {
      response = await props.questionService.createQuestion(payload);
    }

    if (response.success) {
      alert(
        props.question
          ? t("withdrawKycQModal_alert_saveOkUpdated")
          : t("withdrawKycQModal_alert_saveOkCreated"),
      );
      emit("save");
    } else {
      const raw = translateApiErrorMessage(
        response.errorCode,
        response.message,
      );
      alert(
        tParams(
          "withdrawKycQModal_alert_saveFailed",
          "Failed to save question: {msg}",
          { msg: raw },
        ),
      );
    }
  } catch (error) {
    console.error("Failed to save question:", error);
    alert(t("withdrawKycQModal_alert_saveCatch"));
  }
};
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

.modal {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: 0;
  max-width: 600px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
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

.modal-header {
  padding: 25px 30px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--color-surface-soft);
}

.modal-title {
  font-size: 20px;
  font-weight: 600;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 0;
}

.modal-title i {
  color: var(--color-brand);
}

.modal-close {
  background: var(--color-border);
  border: none;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
  font-size: 18px;
  color: var(--color-text);
}

.modal-close:hover {
  background: var(--color-brand-solid);
  color: white;
}

.modal-body {
  padding: 30px;
}

.form-group {
  margin-bottom: 25px;
}

.form-label {
  display: block;
  margin-bottom: 10px;
  color: var(--color-ink);
  font-weight: 600;
  font-size: 14px;
}

.form-input,
.form-select,
.form-textarea {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  transition: all 0.3s ease;
  background: var(--color-surface);
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.form-textarea {
  resize: vertical;
  min-height: 80px;
}

.options-container {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 15px;
  background: var(--color-surface-soft);
}

.option-item {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 10px;
}

.option-column-labels {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin-bottom: 8px;
  padding-right: 82px;
  color: var(--color-text);
  font-size: 14px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.option-pair {
  flex: 1;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.option-input-single {
  flex: 1;
}

.option-item:last-child {
  margin-bottom: 0;
}

.option-input {
  flex: 1;
  padding: 8px 12px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-ink);
  font-size: 14px;
}

.option-input:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.remove-option {
  background: var(--color-danger-solid);
  color: white;
  border: none;
  border-radius: 4px;
  padding: 6px 10px;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.3s ease;
}

.remove-option:hover {
  background: var(--color-danger-solid);
}

.add-option {
  background: var(--color-brand-solid);
  color: white;
  border: none;
  border-radius: var(--radius-sm);
  padding: 8px 12px;
  font-size: 14px;
  cursor: pointer;
  margin-top: 10px;
  transition: all 0.3s ease;
  width: 100%;
}

.add-option:hover {
  background: var(--color-brand-strong);
}

.option-toggle {
  margin-top: 14px;
  padding-top: 14px;
  border-top: 1px solid var(--color-border);
}

.option-toggle-hint {
  display: block;
  margin-top: 6px;
  color: var(--color-muted);
  font-size: 14px;
}

.form-checkbox {
  display: flex;
  align-items: center;
  gap: 8px;
}

.form-checkbox input[type="checkbox"] {
  width: 18px;
  height: 18px;
  accent-color: var(--color-brand);
  cursor: pointer;
}

.form-checkbox label {
  cursor: pointer;
  color: var(--color-text);
  font-size: 14px;
}

.modal-footer {
  padding: 20px 30px;
  border-top: 2px solid var(--color-border);
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  background: var(--color-surface-soft);
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

.btn-primary {
  background: var(--color-brand-solid);
  color: white;
  box-shadow: 0 2px 8px rgba(var(--color-brand-rgb), 0.3);
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.4);
}

.btn-secondary {
  background: var(--color-border);
  color: var(--color-text);
}

.btn-secondary:hover {
  background: var(--color-border-strong);
}
</style>
