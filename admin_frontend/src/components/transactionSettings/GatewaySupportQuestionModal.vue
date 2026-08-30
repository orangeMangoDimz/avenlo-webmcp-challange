<template>
  <div class="modal-overlay show" @click="$emit('close')">
    <div class="modal" @click.stop>
      <div class="modal-header">
        <h2 class="modal-title">
          <i class="fas" :class="question ? 'fa-edit' : 'fa-plus-circle'"></i>
          {{
            question
              ? t("txnSettings_gsq_titleEdit")
              : t("txnSettings_gsq_titleCreate")
          }}
        </h2>
        <button class="modal-close" @click="$emit('close')">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="modal-body">
        <form @submit.prevent="handleSubmit">
          <div v-if="lockedIdentityFields" class="locked-notice">
            <i class="fas fa-lock"></i>
            <span>{{ t("txnSettings_gsq_lockedNotice") }}</span>
          </div>

          <div class="form-group">
            <label class="form-label">{{
              t("txnSettings_gsq_label_name")
            }}</label>
            <textarea
              class="form-textarea"
              v-model="formData.name"
              :placeholder="t('txnSettings_gsq_ph_name')"
              :disabled="lockedIdentityFields"
              required
            ></textarea>
          </div>

          <div class="form-group">
            <label class="form-label">{{
              t("txnSettings_gsq_label_type")
            }}</label>
            <select
              class="form-select"
              v-model="formData.questionType"
              :disabled="lockedIdentityFields"
              required
            >
              <option value="">{{ t("txnSettings_gsq_selectType") }}</option>
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
              t("txnSettings_gsq_label_hint")
            }}</label>
            <input
              type="text"
              class="form-input"
              v-model="formData.hintText"
              :placeholder="t('txnSettings_gsq_ph_hint')"
            />
          </div>

          <div class="form-group">
            <label class="form-label">{{
              t("txnSettings_gsq_label_validation")
            }}</label>
            <input
              type="text"
              class="form-input"
              v-model="formData.validationRules"
              :placeholder="t('txnSettings_gsq_ph_validation')"
              :disabled="lockedIdentityFields"
            />
          </div>

          <div v-if="isChoiceQuestion" class="form-group">
            <label class="form-label">{{
              t("txnSettings_gsq_label_options")
            }}</label>
            <div class="options-container">
              <div
                v-if="formData.separateOptionValue"
                class="option-column-labels"
              >
                <span>{{ t("txnSettings_gsq_col_label") }}</span>
                <span>{{ t("txnSettings_gsq_col_value") }}</span>
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
                    v-model="formData.options[idx].label"
                    :placeholder="
                      tParams(
                        'txnSettings_gsq_ph_optionLabel',
                        'Option {n} Label',
                        { n: idx + 1 },
                      )
                    "
                    :disabled="lockedIdentityFields"
                  />
                  <input
                    type="text"
                    class="option-input"
                    v-model="formData.options[idx].value"
                    :placeholder="
                      tParams(
                        'txnSettings_gsq_ph_optionValue',
                        'Option {n} Value',
                        { n: idx + 1 },
                      )
                    "
                    :disabled="lockedIdentityFields"
                  />
                </div>
                <input
                  v-else
                  type="text"
                  class="option-input option-input-single"
                  v-model="formData.options[idx].label"
                  :placeholder="
                    tParams('txnSettings_gsq_ph_optionN', 'Option {n}', {
                      n: idx + 1,
                    })
                  "
                  :disabled="lockedIdentityFields"
                />
                <button
                  v-if="!lockedIdentityFields"
                  type="button"
                  class="remove-option"
                  @click="removeOption(idx)"
                >
                  {{ t("txnSettings_gsq_removeOption") }}
                </button>
              </div>
              <button
                v-if="!lockedIdentityFields"
                type="button"
                class="add-option"
                @click="addOption"
              >
                + {{ t("txnSettings_gsq_addOption") }}
              </button>
              <div class="option-toggle">
                <label class="form-checkbox">
                  <input
                    type="checkbox"
                    v-model="formData.separateOptionValue"
                    :disabled="lockedIdentityFields"
                  />
                  <span>{{ t("txnSettings_gsq_separateValue") }}</span>
                </label>
                <small class="option-toggle-hint">
                  {{ t("txnSettings_gsq_separateValueHint") }}
                </small>
              </div>
            </div>
          </div>

          <div class="form-group">
            <div class="form-checkbox">
              <input
                type="checkbox"
                id="gatewayQuestionActive"
                v-model="formData.isActive"
              />
              <label for="gatewayQuestionActive">{{
                t("txnSettings_gsq_label_active")
              }}</label>
            </div>
          </div>
        </form>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" @click="$emit('close')">
          {{ t("txnSettings_gsq_cancel") }}
        </button>
        <button type="button" class="btn btn-primary" @click="handleSubmit">
          <i class="fas fa-save"></i> {{ t("txnSettings_gsq_save") }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t, tParams } = useAdminI18n();

const props = defineProps({
  question: {
    type: Object,
    default: null,
  },
  scope: {
    type: String,
    required: true,
  },
});

const emit = defineEmits(["close", "save"]);

const formData = ref({
  name: "",
  hintText: "",
  questionType: "",
  validationRules: "required",
  options: [{ label: "", value: "" }],
  separateOptionValue: false,
  isActive: true,
});

const questionTypes = computed(() => [
  { value: "text", label: t("txnSettings_qType_text") },
  { value: "tel", label: t("txnSettings_qType_tel") },
  { value: "email", label: t("txnSettings_qType_email") },
  { value: "date", label: t("txnSettings_qType_date") },
  { value: "single_choice", label: t("txnSettings_qType_single_choice") },
]);

const lockedIdentityFields = computed(() => Boolean(props.question?.isLocked));

const isChoiceQuestion = computed(
  () => formData.value.questionType === "single_choice",
);

const createEmptyOption = () => ({
  label: "",
  value: "",
  isEnabled: true,
});

const normalizeOption = (option) => {
  if (option && typeof option === "object") {
    const value =
      `${option.value || option.optionValue || option.label || option.labal || ""}`.trim();
    const label =
      `${option.label || option.labal || option.value || option.optionValue || ""}`.trim();

    if (!label && !value) {
      return null;
    }

    return {
      label: label || value,
      value: value || label,
      isEnabled: option.isEnabled !== false && option.enabled !== false,
    };
  }

  const raw = `${option || ""}`.trim();
  if (!raw) {
    return null;
  }

  return {
    label: raw,
    value: raw,
    isEnabled: true,
  };
};

const hasSeparateOptionValues = (options) =>
  options.some((option) => {
    const normalized = normalizeOption(option);
    return normalized && normalized.label !== normalized.value;
  });

watch(
  () => props.question,
  (newVal) => {
    if (newVal) {
      formData.value = {
        name: newVal.name || "",
        hintText: newVal.hintText || "",
        questionType: newVal.questionType || "",
        validationRules: newVal.validationRules || "",
        options:
          Array.isArray(newVal.options) && newVal.options.length
            ? newVal.options.map(normalizeOption).filter(Boolean)
            : [createEmptyOption()],
        separateOptionValue: Array.isArray(newVal.options)
          ? hasSeparateOptionValues(newVal.options)
          : false,
        isActive: newVal.isActive !== false,
      };
    } else {
      formData.value = {
        name: "",
        hintText: "",
        questionType: "",
        validationRules: "required",
        options: [createEmptyOption()],
        separateOptionValue: false,
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
  }
};

const handleSubmit = () => {
  if (!formData.value.name.trim() || !formData.value.questionType) {
    alert(t("txnSettings_gsq_alert_fillRequired"));
    return;
  }

  const payload = {
    ...formData.value,
    scope: props.scope,
    options: isChoiceQuestion.value
      ? formData.value.options
          .map(normalizeOption)
          .map((item) => {
            if (!item) {
              return null;
            }

            const label = item.label || item.value;
            const value = formData.value.separateOptionValue
              ? item.value || label
              : label;

            if (!label || !value) {
              return null;
            }

            return {
              label,
              value,
              isEnabled: item.isEnabled !== false,
            };
          })
          .filter(Boolean)
      : [],
    validationRules: lockedIdentityFields.value
      ? String(props.question?.validationRules || "")
      : formData.value.validationRules,
    isLocked: Boolean(props.question?.isLocked),
    isActive: Boolean(formData.value.isActive),
  };

  if (isChoiceQuestion.value && payload.options.length === 0) {
    alert(t("txnSettings_gsq_alert_addChoiceOption"));
    return;
  }

  emit("save", payload);
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

.locked-notice {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  margin-bottom: 20px;
  border: 1px solid #f6d365;
  border-radius: var(--radius-md);
  background: var(--color-warning-soft);
  color: var(--color-warning);
  font-size: 14px;
  font-weight: 600;
}

.locked-notice i {
  color: var(--color-warning);
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

.option-item:last-child {
  margin-bottom: 0;
}

.option-pair {
  flex: 1;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
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

.option-input-single {
  flex: 1;
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
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.4);
}

.btn-secondary {
  background: var(--color-surface);
  color: var(--color-text);
  border: 2px solid var(--color-border);
}

.btn-secondary:hover {
  background: var(--color-surface-muted);
  border-color: var(--color-border-strong);
}
</style>
