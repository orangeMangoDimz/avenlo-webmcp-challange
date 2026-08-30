<template>
  <div class="modal-overlay show" @click="$emit('close')">
    <div class="modal" @click.stop>
      <div class="modal-header">
        <h2 class="modal-title">
          <i class="fas" :class="question ? 'fa-edit' : 'fa-plus-circle'"></i>
          {{
            question
              ? t("kycTplQModal_title_edit")
              : t("kycTplQModal_title_create")
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
              t("kycTplQModal_label_questionText")
            }}</label>
            <textarea
              class="form-textarea"
              v-model="formData.questionText"
              :placeholder="t('kycTplQModal_placeholder_question')"
              required
            ></textarea>
          </div>

          <div class="form-group">
            <label class="form-label">{{ t("kycTplQModal_label_type") }}</label>
            <select
              class="form-select"
              v-model="formData.questionType"
              required
            >
              <option value="">{{ t("kycTplQModal_select_type") }}</option>
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
              t("kycTplQModal_label_helpText")
            }}</label>
            <input
              type="text"
              class="form-input"
              v-model="formData.helpText"
              :placeholder="t('kycTplQModal_placeholder_helpText')"
            />
          </div>

          <div class="form-group">
            <label class="form-label">{{
              t("kycTplQModal_label_validation")
            }}</label>
            <input
              type="text"
              class="form-input"
              v-model="formData.validationRule"
              :placeholder="t('kycTplQModal_placeholder_validation')"
            />
          </div>

          <div v-if="isChoiceQuestion" class="form-group">
            <label class="form-label">{{
              t("kycTplQModal_label_answerOptions")
            }}</label>
            <div class="options-container">
              <div
                v-for="(option, idx) in formData.options"
                :key="idx"
                class="option-item"
              >
                <input
                  type="text"
                  class="option-input"
                  v-model="formData.options[idx]"
                  :placeholder="
                    tParams('kycTplQModal_optionN', 'Option {n}', {
                      n: idx + 1,
                    })
                  "
                />
                <button
                  type="button"
                  class="remove-option"
                  @click="removeOption(idx)"
                >
                  {{ t("kycTplQModal_btn_removeOption") }}
                </button>
              </div>
              <button type="button" class="add-option" @click="addOption">
                {{ t("kycTplQModal_btn_addOption") }}
              </button>
            </div>
          </div>

          <div
            v-if="formData.questionType === 'file_upload'"
            class="form-group"
          >
            <label class="form-label">
              <i class="fas fa-file-alt"></i>
              {{ t("kycTplQModal_label_docTypes") }}
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
              {{ t("kycTplQModal_hint_multi") }}
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
                t("kycTplQModal_label_requiredQ")
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
              <label for="isActive">{{ t("kycTplQModal_label_active") }}</label>
            </div>
          </div>
        </form>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" @click="$emit('close')">
          {{ t("common_cancel") }}
        </button>
        <button type="button" class="btn btn-primary" @click="handleSubmit">
          <i class="fas fa-save"></i> {{ t("kycTplQModal_btn_save") }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import { kycQuestionService } from "@/services/kycTemplateService";
import { useAdminI18n } from "@/composables/useAdminI18n";

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
});

const emit = defineEmits(["close", "save"]);

const formData = ref({
  questionText: "",
  questionType: "",
  helpText: "",
  validationRule: "",
  options: [""],
  documentTypes: [],
  isRequired: true,
  isActive: true,
});

const questionTypeKeys = [
  { value: "text", key: "txnSettings_qType_text" },
  { value: "number", key: "addrVerif_qType_number" },
  { value: "email", key: "txnSettings_qType_email" },
  { value: "tel", key: "addrVerif_qType_tel_short" },
  { value: "date", key: "txnSettings_qType_date" },
  { value: "single_choice", key: "txnSettings_qType_single_choice" },
  { value: "multiple_choice", key: "addrVerif_qType_multiple_choice" },
  { value: "yes_no", key: "addrVerif_qType_yes_no" },
  { value: "file_upload", key: "addrVerif_qType_file_upload" },
  { value: "textarea", key: "addrVerif_qType_textarea" },
];

const questionTypes = computed(() =>
  questionTypeKeys.map(({ value, key }) => ({ value, label: t(key) })),
);

const documentTypeValues = [
  "ID_CARD",
  "PASSPORT",
  "DRIVERS_LICENSE",
  "PROOF_ADDRESS",
  "BANK_STATEMENT",
  "UTILITY_BILL",
  "INCOME_PROOF",
  "TAX_DOCUMENT",
  "EMPLOYMENT_LETTER",
  "BUSINESS_REGISTRATION",
  "FINANCIAL_STATEMENT",
  "OTHER",
];

const availableDocumentTypes = computed(() =>
  documentTypeValues.map((value) => ({
    value,
    label: t(`kycDetail_docType_${value}`),
  })),
);

const isChoiceQuestion = computed(() => {
  return ["single_choice", "multiple_choice"].includes(
    formData.value.questionType,
  );
});

watch(
  () => props.question,
  (newVal) => {
    if (newVal) {
      // 处理选择题选项
      let options = [""];
      if (
        newVal.options?.length &&
        ["single_choice", "multiple_choice"].includes(newVal.questionType)
      ) {
        options = newVal.options.map((opt) =>
          typeof opt === "object" ? opt.optionValue : opt,
        );
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
        options: [""],
        documentTypes: [],
        isRequired: true,
        isActive: true,
      };
    }
  },
  { immediate: true },
);

const addOption = () => {
  formData.value.options.push("");
};

const removeOption = (index) => {
  if (formData.value.options.length > 1) {
    formData.value.options.splice(index, 1);
  } else {
    alert(t("kycTplQModal_alert_optionChoice"));
  }
};

const handleSubmit = async () => {
  if (!formData.value.questionText || !formData.value.questionType) {
    alert(t("kycTplQModal_alert_required"));
    return;
  }

  if (isChoiceQuestion.value) {
    const validOptions = formData.value.options.filter((o) => o.trim());
    if (validOptions.length === 0) {
      alert(t("kycTplQModal_alert_addOptions"));
      return;
    }
    formData.value.options = validOptions;
  }

  if (
    formData.value.questionType === "file_upload" &&
    formData.value.documentTypes.length === 0
  ) {
    alert(t("kycTplQModal_alert_fileTypes"));
    return;
  }

  const payload = {
    ...formData.value,
    categoryId: props.categoryId,
    templateId: props.templateId,
  };

  try {
    let response;
    if (props.question) {
      response = await kycQuestionService.updateQuestion(
        props.question.questionId,
        payload,
      );
    } else {
      response = await kycQuestionService.createQuestion(payload);
    }

    if (response.success) {
      alert(
        props.question
          ? t("kycTplQModal_alert_questionUpdated")
          : t("kycTplQModal_alert_questionCreated"),
      );
      emit("save");
    } else {
      alert(
        tParams("kycTplQModal_alert_failed", "Failed to save question: {msg}", {
          msg: response.message || "",
        }),
      );
    }
  } catch (error) {
    console.error("Failed to save question:", error);
    alert(t("kycTplQModal_alert_err"));
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
