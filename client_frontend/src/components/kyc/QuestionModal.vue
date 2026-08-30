<template>
  <div class="modal-overlay show" @click="$emit('close')">
    <div class="modal" @click.stop>
      <div class="modal-header">
        <h2 class="modal-title">
          <i class="fas" :class="question ? 'fa-edit' : 'fa-plus-circle'"></i>
          {{ question ? "Edit" : "Create New" }} Question
        </h2>
        <button class="modal-close" @click="$emit('close')">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="modal-body">
        <form @submit.prevent="handleSubmit">
          <div class="form-group">
            <label class="form-label">Question Text *</label>
            <textarea
              class="form-textarea"
              v-model="formData.questionText"
              placeholder="Enter your question here..."
              required
            ></textarea>
          </div>

          <div class="form-group">
            <label class="form-label">Question Type *</label>
            <select
              class="form-select"
              v-model="formData.questionType"
              required
            >
              <option value="">Select question type</option>
              <option value="Text Input">Text Input</option>
              <option value="Number">Number</option>
              <option value="Date">Date</option>
              <option value="Single Choice">Single Choice</option>
              <option value="Multiple Choice">Multiple Choice</option>
              <option value="Yes/No">Yes/No</option>
              <option value="File Upload">File Upload</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Help Text</label>
            <input
              type="text"
              class="form-input"
              v-model="formData.helpText"
              placeholder="Optional help text for users"
            />
          </div>

          <div class="form-group">
            <label class="form-label">Validation Rule</label>
            <input
              type="text"
              class="form-input"
              v-model="formData.validationRule"
              placeholder="e.g., required|min:2|max:100"
            />
          </div>

          <div v-if="isChoiceQuestion" class="form-group">
            <label class="form-label">Answer Options</label>
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
                  :placeholder="`Option ${idx + 1}`"
                />
                <button
                  type="button"
                  class="remove-option"
                  @click="removeOption(idx)"
                >
                  Remove
                </button>
              </div>
              <button type="button" class="add-option" @click="addOption">
                + Add Option
              </button>
            </div>
          </div>

          <div
            v-if="formData.questionType === 'File Upload'"
            class="form-group"
          >
            <label class="form-label">
              <i class="fas fa-file-alt"></i> Document Type *
            </label>
            <select
              class="form-select"
              v-model="formData.documentTypes"
              multiple
              style="min-height: 120px"
            >
              <option value="ID_CARD">Identity Card</option>
              <option value="PASSPORT">Passport</option>
              <option value="DRIVERS_LICENSE">Driver's License</option>
              <option value="PROOF_ADDRESS">Proof of Address</option>
              <option value="BANK_STATEMENT">Bank Statement</option>
              <option value="UTILITY_BILL">Utility Bill</option>
              <option value="INCOME_PROOF">Income Verification</option>
              <option value="TAX_DOCUMENT">Tax Document</option>
              <option value="EMPLOYMENT_LETTER">Employment Letter</option>
              <option value="BUSINESS_REGISTRATION">
                Business Registration
              </option>
              <option value="FINANCIAL_STATEMENT">Financial Statement</option>
              <option value="OTHER">Other Document</option>
            </select>
            <small
              style="
                color: var(--color-muted);
                font-size: 12px;
                margin-top: 8px;
                display: block;
              "
            >
              <i class="fas fa-info-circle"></i>
              Hold Ctrl (Cmd on Mac) to select multiple document types.
            </small>
          </div>

          <div class="form-group">
            <div class="form-checkbox">
              <input
                type="checkbox"
                id="isRequired"
                v-model="formData.isRequired"
              />
              <label for="isRequired">Required Question</label>
            </div>
          </div>

          <div class="form-group">
            <div class="form-checkbox">
              <input
                type="checkbox"
                id="isActive"
                v-model="formData.isActive"
              />
              <label for="isActive">Active</label>
            </div>
          </div>
        </form>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" @click="$emit('close')">
          Cancel
        </button>
        <button type="button" class="btn btn-primary" @click="handleSubmit">
          <i class="fas fa-save"></i> Save Question
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import { kycQuestionService } from "@/services/kycTemplateService";

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

const isChoiceQuestion = computed(() => {
  return ["Single Choice", "Multiple Choice"].includes(
    formData.value.questionType,
  );
});

watch(
  () => props.question,
  (newVal) => {
    if (newVal) {
      formData.value = {
        questionText: newVal.questionText || "",
        questionType: newVal.questionType || "",
        helpText: newVal.helpText || "",
        validationRule: newVal.validationRule || "",
        options: newVal.options?.length ? [...newVal.options] : [""],
        documentTypes: newVal.documentTypes || [],
        isRequired: newVal.isRequired !== false,
        isActive: newVal.isActive !== false,
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
    alert("At least one option is required for choice questions.");
  }
};

const handleSubmit = async () => {
  if (!formData.value.questionText || !formData.value.questionType) {
    alert("Please fill in all required fields.");
    return;
  }

  if (isChoiceQuestion.value) {
    const validOptions = formData.value.options.filter((o) => o.trim());
    if (validOptions.length === 0) {
      alert("Please add at least one option for choice questions.");
      return;
    }
    formData.value.options = validOptions;
  }

  if (
    formData.value.questionType === "File Upload" &&
    formData.value.documentTypes.length === 0
  ) {
    alert(
      "⚠️ Please select at least one document type for file upload questions",
    );
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
        props.question.id,
        payload,
      );
    } else {
      response = await kycQuestionService.createQuestion(payload);
    }

    if (response.success) {
      alert(
        `✓ Question ${props.question ? "updated" : "created"} successfully!`,
      );
      emit("save");
    } else {
      alert(`Failed to save question: ${response.message}`);
    }
  } catch (error) {
    console.error("Failed to save question:", error);
    alert("Failed to save question. Please try again.");
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
  border-bottom: 1px solid var(--color-border);
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
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  transition: all 0.3s ease;
  background: var(--color-surface);
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
  border-color: var(--color-brand);
  box-shadow: none;
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
  font-size: 13px;
}

.option-input:focus {
  border-color: var(--color-brand);
  box-shadow: none;
}

.remove-option {
  background: var(--color-danger-solid);
  color: white;
  border: none;
  border-radius: 4px;
  padding: 6px 10px;
  font-size: 12px;
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
  font-size: 13px;
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
  border-top: 1px solid var(--color-border);
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
