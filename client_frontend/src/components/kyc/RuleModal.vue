<template>
  <div class="modal-overlay show" @click="$emit('close')">
    <div class="modal" style="max-width: 600px" @click.stop>
      <div class="modal-header">
        <h2 class="modal-title">
          <i class="fas fa-cogs"></i> Add New Conditional Logic Rule
        </h2>
        <button class="modal-close" @click="$emit('close')">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="modal-body">
        <div
          style="
            background: var(--color-brand-soft);
            padding: 12px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            border-left: 4px solid var(--color-brand);
          "
        >
          <div style="display: flex; align-items: center; gap: 8px">
            <i class="fas fa-lightbulb" style="color: var(--color-brand)"></i>
            <strong style="color: var(--color-ink); font-size: 13px"
              >Simple Rules:</strong
            >
            <span style="color: var(--color-text); font-size: 13px"
              >Jump to a question or reject the application based on answer
              choice</span
            >
          </div>
        </div>

        <form @submit.prevent="handleSubmit">
          <div class="form-group">
            <label class="form-label">
              <i class="fas fa-tag"></i> Rule Type *
            </label>
            <select class="form-select" v-model="formData.ruleType" required>
              <option value="">Select rule type...</option>
              <option value="jump_to">
                Jump to Question - Skip to a specific question
              </option>
              <option value="reject">
                Reject Application - Stop and reject when condition met
              </option>
            </select>
            <small
              style="
                color: var(--color-muted);
                font-size: 12px;
                margin-top: 5px;
                display: block;
              "
            >
              {{ getRuleTypeHelp() }}
            </small>
          </div>

          <div class="form-group">
            <label class="form-label">
              <i class="fas fa-pencil-alt"></i> Rule Name *
            </label>
            <input
              type="text"
              class="form-input"
              v-model="formData.ruleName"
              placeholder="e.g., High Income Skip Logic"
              required
            />
          </div>

          <div
            style="
              border: 2px solid var(--color-border);
              border-radius: var(--radius-md);
              padding: 20px;
              margin-bottom: 20px;
              background: var(--color-surface-soft);
            "
          >
            <h4
              style="
                font-size: 14px;
                font-weight: 600;
                color: var(--color-ink);
                margin-bottom: 15px;
                display: flex;
                align-items: center;
                gap: 8px;
              "
            >
              <span
                style="
                  background: var(--color-brand-soft);
                  color: var(--color-brand-strong);
                  padding: 4px 10px;
                  border-radius: 4px;
                  font-size: 12px;
                "
                >IF</span
              >
              When User Selects
            </h4>

            <div class="form-group" style="margin-bottom: 15px">
              <label class="form-label">
                <i class="fas fa-question-circle"></i> Question (must be choice
                type) *
              </label>
              <select
                class="form-select"
                v-model="formData.triggerQuestion"
                required
              >
                <option value="">Select a question...</option>
                <option v-for="q in questions" :key="q.id" :value="q.id">
                  Question #{{ q.order }} - {{ q.questionText }}
                </option>
              </select>
            </div>

            <div class="form-group" style="margin-bottom: 0">
              <label class="form-label">
                <i class="fas fa-check-square"></i> Selected Answer Option *
              </label>
              <select
                class="form-select"
                v-model="formData.selectedAnswer"
                required
                :disabled="!formData.triggerQuestion"
              >
                <option value="">
                  {{
                    formData.triggerQuestion
                      ? "Select an answer option..."
                      : "First select a question above"
                  }}
                </option>
                <option
                  v-for="(option, idx) in selectedQuestionOptions"
                  :key="idx"
                  :value="option"
                >
                  {{ option }}
                </option>
              </select>
              <small
                style="
                  color: var(--color-muted);
                  font-size: 12px;
                  margin-top: 5px;
                  display: block;
                "
              >
                Choose which answer option triggers this rule
              </small>
            </div>
          </div>

          <div
            style="
              border: 2px solid var(--color-border);
              border-radius: var(--radius-md);
              padding: 20px;
              margin-bottom: 20px;
              background: var(--color-surface-soft);
            "
          >
            <h4
              style="
                font-size: 14px;
                font-weight: 600;
                color: var(--color-ink);
                margin-bottom: 15px;
                display: flex;
                align-items: center;
                gap: 8px;
              "
            >
              <span
                style="
                  background: #86efac;
                  color: #166534;
                  padding: 4px 10px;
                  border-radius: 4px;
                  font-size: 12px;
                "
                >THEN</span
              >
              Action
            </h4>

            <div
              v-if="formData.ruleType === 'jump_to'"
              class="form-group"
              style="margin-bottom: 0"
            >
              <label class="form-label">
                <i class="fas fa-arrow-right"></i> Jump to Question *
              </label>
              <select class="form-select" v-model="formData.jumpToQuestion">
                <option value="">Select target question...</option>
                <option v-for="q in questions" :key="q.id" :value="q.id">
                  Question #{{ q.order }} - {{ q.questionText }}
                </option>
              </select>
              <small
                style="
                  color: var(--color-muted);
                  font-size: 12px;
                  margin-top: 5px;
                  display: block;
                "
              >
                Skip intermediate questions and jump directly to this question
              </small>
            </div>

            <div
              v-if="formData.ruleType === 'reject'"
              class="form-group"
              style="margin-bottom: 0"
            >
              <label class="form-label">
                <i class="fas fa-ban"></i> Rejection Message *
              </label>
              <textarea
                class="form-textarea"
                v-model="formData.rejectMessage"
                placeholder="e.g., Sorry, you must meet our eligibility requirements"
                rows="3"
              ></textarea>
              <small
                style="
                  color: var(--color-muted);
                  font-size: 12px;
                  margin-top: 5px;
                  display: block;
                "
              >
                This message will be shown to the user when application is
                rejected
              </small>
            </div>
          </div>

          <div class="form-group">
            <div class="form-checkbox">
              <input
                type="checkbox"
                id="ruleActive"
                v-model="formData.isActive"
              />
              <label for="ruleActive">
                <strong>Rule Active</strong> - Uncheck to disable this rule
                temporarily
              </label>
            </div>
          </div>
        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" @click="$emit('close')">
          <i class="fas fa-times"></i> Cancel
        </button>
        <button class="btn btn-primary" @click="handleSubmit">
          <i class="fas fa-save"></i> Create Rule
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from "vue";
import { kycRuleService } from "@/services/kycTemplateService";

const props = defineProps({
  templateId: {
    type: Number,
    required: true,
  },
  questions: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(["close", "save"]);

const formData = ref({
  ruleType: "",
  ruleName: "",
  triggerQuestion: "",
  selectedAnswer: "",
  jumpToQuestion: "",
  rejectMessage: "",
  isActive: true,
});

const selectedQuestionOptions = computed(() => {
  if (!formData.value.triggerQuestion) return [];
  const question = props.questions.find(
    (q) => q.id === formData.value.triggerQuestion,
  );
  return question?.options || [];
});

const getRuleTypeHelp = () => {
  const helpTexts = {
    jump_to:
      "💡 Skip intermediate questions and jump directly to a specific question",
    reject:
      "💡 Stop the KYC process and reject the application with a custom message",
  };
  return (
    helpTexts[formData.value.ruleType] || "Select a rule type to see details"
  );
};

const handleSubmit = async () => {
  if (
    !formData.value.ruleType ||
    !formData.value.ruleName ||
    !formData.value.triggerQuestion ||
    !formData.value.selectedAnswer
  ) {
    alert("Please fill in all required fields.");
    return;
  }

  if (formData.value.ruleType === "jump_to" && !formData.value.jumpToQuestion) {
    alert("⚠️ Please select a target question to jump to");
    return;
  }

  if (
    formData.value.ruleType === "reject" &&
    !formData.value.rejectMessage.trim()
  ) {
    alert("⚠️ Please enter a rejection message");
    return;
  }

  const payload = {
    templateId: props.templateId,
    name: formData.value.ruleName,
    actionType: formData.value.ruleType,
    triggerQuestionId: formData.value.triggerQuestion,
    selectedAnswer: formData.value.selectedAnswer,
    jumpToQuestionId:
      formData.value.ruleType === "jump_to"
        ? formData.value.jumpToQuestion
        : null,
    rejectMessage:
      formData.value.ruleType === "reject"
        ? formData.value.rejectMessage
        : null,
    isActive: formData.value.isActive,
  };

  try {
    const response = await kycRuleService.createRule(payload);
    if (response.success) {
      alert("✓ Rule created successfully!");
      emit("save");
    } else {
      alert(`Failed to create rule: ${response.message}`);
    }
  } catch (error) {
    console.error("Failed to create rule:", error);
    alert("Failed to create rule. Please try again.");
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
  background: white;
  border-radius: var(--radius-lg);
  padding: 0;
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
  background: var(--color-brand);
  color: white;
}

.modal-body {
  padding: 30px;
}

.form-group {
  margin-bottom: 20px;
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
  background: white;
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
  background: var(--color-brand);
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
