<template>
  <div class="modal-overlay show" @click="$emit('close')">
    <div class="modal" style="max-width: 600px" @click.stop>
      <div class="modal-header">
        <h2 class="modal-title">
          <i class="fas fa-cogs"></i>
          {{
            rule
              ? t("withdrawKycRuleModal_titleEdit")
              : t("withdrawKycRuleModal_titleAdd")
          }}
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
            <strong style="color: var(--color-ink); font-size: 14px">{{
              t("withdrawKycRuleModal_simpleRules")
            }}</strong>
            <span style="color: var(--color-text); font-size: 14px">{{
              tParams(
                "withdrawKycRuleModal_tipLine",
                "Jump to a question or reject the {label} based on answer choice",
                { label: applicationLabel },
              )
            }}</span>
          </div>
        </div>

        <form @submit.prevent="handleSubmit">
          <div class="form-group">
            <label class="form-label">
              <i class="fas fa-tag"></i>
              {{ t("withdrawKycRuleModal_labelRuleType") }}
            </label>
            <select class="form-select" v-model="formData.ruleType" required>
              <option value="">
                {{ t("withdrawKycRuleModal_selectRuleType") }}
              </option>
              <option value="jump_to">
                {{ t("withdrawKycRuleModal_optJump") }}
              </option>
              <option value="reject">
                {{ t("withdrawKycRuleModal_optReject") }}
              </option>
            </select>
            <small
              style="
                color: var(--color-muted);
                font-size: 14px;
                margin-top: 5px;
                display: block;
              "
            >
              {{ ruleTypeHelp }}
            </small>
          </div>

          <div class="form-group">
            <label class="form-label">
              <i class="fas fa-pencil-alt"></i>
              {{ t("withdrawKycRuleModal_labelRuleName") }}
            </label>
            <input
              type="text"
              class="form-input"
              v-model="formData.ruleName"
              :placeholder="t('withdrawKycRuleModal_placeholderRuleName')"
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
                  font-size: 14px;
                "
                >{{ t("withdrawKycRuleModal_ifBadge") }}</span
              >
              {{ t("withdrawKycRuleModal_whenUserSelects") }}
            </h4>

            <div class="form-group" style="margin-bottom: 15px">
              <label class="form-label">
                <i class="fas fa-question-circle"></i>
                {{ t("withdrawKycRuleModal_labelTriggerQ") }}
              </label>
              <select
                class="form-select"
                v-model="formData.triggerQuestion"
                required
              >
                <option value="">
                  {{ t("withdrawKycRuleModal_selectQuestion") }}
                </option>
                <option v-for="q in questions" :key="q.id" :value="q.id">
                  {{
                    tParams(
                      "withdrawKycRuleModal_questionLine",
                      "Question #{order} — {text}",
                      { order: q.displayOrder, text: q.questionText },
                    )
                  }}
                </option>
              </select>
            </div>

            <div class="form-group" style="margin-bottom: 0">
              <label class="form-label">
                <i class="fas fa-check-square"></i>
                {{ t("withdrawKycRuleModal_labelSelectedAnswer") }}
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
                      ? t("withdrawKycRuleModal_selectAnswerOption")
                      : t("withdrawKycRuleModal_selectQuestionFirst")
                  }}
                </option>
                <option
                  v-for="(option, idx) in selectedQuestionOptions"
                  :key="option.id || idx"
                  :value="getOptionValue(option)"
                >
                  {{ getOptionLabel(option) }}
                </option>
              </select>
              <small
                style="
                  color: var(--color-muted);
                  font-size: 14px;
                  margin-top: 5px;
                  display: block;
                "
              >
                {{ t("withdrawKycRuleModal_hintTrigger") }}
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
                  background: var(--color-success-soft);
                  color: var(--color-success);
                  padding: 4px 10px;
                  border-radius: 4px;
                  font-size: 14px;
                "
                >{{ t("withdrawKycRuleModal_thenBadge") }}</span
              >
              {{ t("withdrawKycRuleModal_actionHeading") }}
            </h4>

            <div
              v-if="formData.ruleType === 'jump_to'"
              class="form-group"
              style="margin-bottom: 0"
            >
              <label class="form-label">
                <i class="fas fa-arrow-right"></i>
                {{ t("withdrawKycRuleModal_labelJumpTo") }}
              </label>
              <select class="form-select" v-model="formData.jumpToQuestion">
                <option value="">
                  {{ t("withdrawKycRuleModal_selectTargetQuestion") }}
                </option>
                <option v-for="q in questions" :key="q.id" :value="q.id">
                  {{
                    tParams(
                      "withdrawKycRuleModal_questionLine",
                      "Question #{order} — {text}",
                      { order: q.displayOrder, text: q.questionText },
                    )
                  }}
                </option>
              </select>
              <small
                style="
                  color: var(--color-muted);
                  font-size: 14px;
                  margin-top: 5px;
                  display: block;
                "
              >
                {{ t("withdrawKycRuleModal_hintJump") }}
              </small>
            </div>

            <div
              v-if="formData.ruleType === 'reject'"
              class="form-group"
              style="margin-bottom: 0"
            >
              <label class="form-label">
                <i class="fas fa-ban"></i>
                {{ t("withdrawKycRuleModal_labelRejectMsg") }}
              </label>
              <textarea
                class="form-textarea"
                v-model="formData.rejectMessage"
                :placeholder="t('withdrawKycRuleModal_placeholderReject')"
                rows="3"
              ></textarea>
              <small
                style="
                  color: var(--color-muted);
                  font-size: 14px;
                  margin-top: 5px;
                  display: block;
                "
              >
                {{ t("withdrawKycRuleModal_hintReject") }}
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
                <strong>{{ t("withdrawKycRuleModal_ruleActive") }}</strong> —
                {{ t("withdrawKycRuleModal_ruleActiveHint") }}
              </label>
            </div>
          </div>
        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" @click="$emit('close')">
          <i class="fas fa-times"></i> {{ t("withdrawKycRuleModal_btnCancel") }}
        </button>
        <button class="btn btn-primary" @click="handleSubmit">
          <i class="fas fa-save"></i>
          {{
            rule
              ? t("withdrawKycRuleModal_btnUpdate")
              : t("withdrawKycRuleModal_btnCreate")
          }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import { withdrawKycRuleService } from "@/services/withdrawKycTemplateService";
import { useLanguageStore } from "@/stores/language";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { translateApiErrorMessage } from "@/i18n/adminI18nBridge";

const languageStore = useLanguageStore();
const { t, tParams } = useAdminI18n();

const props = defineProps({
  templateId: {
    type: Number,
    required: true,
  },
  questions: {
    type: Array,
    default: () => [],
  },
  rule: {
    type: Object,
    default: null,
  },
  ruleService: {
    type: Object,
    default: () => withdrawKycRuleService,
  },
  applicationLabel: {
    type: String,
    default: "Application",
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

const getOptionLabel = (option) => {
  if (option && typeof option === "object") {
    return (
      option.optionLabel ||
      option.label ||
      option.labal ||
      option.optionValue ||
      option.value ||
      ""
    );
  }

  return option == null ? "" : String(option);
};

const getOptionValue = (option) => {
  if (option && typeof option === "object") {
    return (
      option.optionValue ??
      option.value ??
      option.optionLabel ??
      option.label ??
      option.labal ??
      ""
    );
  }

  return option == null ? "" : String(option);
};

const normalizeOption = (option, index) => {
  const label = getOptionLabel(option);
  const value = getOptionValue(option);

  if (!label && !value) {
    return null;
  }

  if (option && typeof option === "object") {
    return {
      ...option,
      id: option.id ?? `${value || label}-${index}`,
      optionLabel: label || value,
      optionValue: value || label,
    };
  }

  return {
    id: `${value || label}-${index}`,
    optionLabel: label || value,
    optionValue: value || label,
  };
};

const selectedQuestionOptions = computed(() => {
  void languageStore.currentLanguage;
  if (!formData.value.triggerQuestion) return [];
  const question = props.questions.find(
    (q) => String(q.id) === String(formData.value.triggerQuestion),
  );

  // 如果是 yes_no 类型，返回固定的 Yes/No 选项
  if (question?.questionType === "yes_no") {
    return [
      { id: "yes", optionLabel: t("common_yes", "Yes"), optionValue: "Yes" },
      { id: "no", optionLabel: t("common_no", "No"), optionValue: "No" },
    ];
  }

  return Array.isArray(question?.options)
    ? question.options
        .map((option, index) => normalizeOption(option, index))
        .filter(Boolean)
    : [];
});

const ruleTypeHelp = computed(() => {
  void languageStore.currentLanguage;
  const rt = formData.value.ruleType;
  if (rt === "jump_to") return t("withdrawKycRuleModal_helpJump");
  if (rt === "reject") {
    return tParams(
      "withdrawKycRuleModal_helpReject",
      "💡 Stop the {label} flow and reject it with a custom message",
      { label: props.applicationLabel },
    );
  }
  return t("withdrawKycRuleModal_helpSelect");
});

// 监听 rule prop 变化，初始化编辑数据
watch(
  () => props.rule,
  (newRule) => {
    if (newRule) {
      formData.value = {
        ruleType: newRule.ruleType || "",
        ruleName: newRule.ruleName || "",
        triggerQuestion: newRule.triggerQuestionId || "",
        selectedAnswer: newRule.triggerAnswer || "",
        jumpToQuestion: newRule.targetQuestionId || "",
        rejectMessage: newRule.rejectMessage || "",
        isActive: newRule.isActive == 1 ? true : false,
      };
    } else {
      // 重置为新增模式
      formData.value = {
        ruleType: "",
        ruleName: "",
        triggerQuestion: "",
        selectedAnswer: "",
        jumpToQuestion: "",
        rejectMessage: "",
        isActive: true,
      };
    }
  },
  { immediate: true },
);

const handleSubmit = async () => {
  if (
    !formData.value.ruleType ||
    !formData.value.ruleName ||
    !formData.value.triggerQuestion ||
    !formData.value.selectedAnswer
  ) {
    alert(t("withdrawKycRuleModal_alert_fillRequired"));
    return;
  }

  if (formData.value.ruleType === "jump_to" && !formData.value.jumpToQuestion) {
    alert(t("withdrawKycRuleModal_alert_needJumpTarget"));
    return;
  }

  if (
    formData.value.ruleType === "reject" &&
    !formData.value.rejectMessage.trim()
  ) {
    alert(t("withdrawKycRuleModal_alert_needRejectMsg"));
    return;
  }

  const payload = {
    templateId: props.templateId,
    ruleName: formData.value.ruleName,
    ruleType: formData.value.ruleType,
    triggerQuestionId: formData.value.triggerQuestion,
    triggerAnswer: formData.value.selectedAnswer,
    targetQuestionId:
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
    let response;
    if (props.rule) {
      // 编辑模式
      response = await props.ruleService.updateRule(props.rule.id, payload);
      if (response.success) {
        alert(t("withdrawKycRuleModal_alert_updateOk"));
        emit("save");
      } else {
        const raw = translateApiErrorMessage(
          response.errorCode,
          response.message,
        );
        alert(
          tParams(
            "withdrawKycRuleModal_alert_updateFailed",
            "Failed to update rule: {msg}",
            { msg: raw },
          ),
        );
      }
    } else {
      // 新增模式
      response = await props.ruleService.createRule(payload);
      if (response.success) {
        alert(t("withdrawKycRuleModal_alert_createOk"));
        emit("save");
      } else {
        const raw = translateApiErrorMessage(
          response.errorCode,
          response.message,
        );
        alert(
          tParams(
            "withdrawKycRuleModal_alert_createFailed",
            "Failed to create rule: {msg}",
            { msg: raw },
          ),
        );
      }
    }
  } catch (error) {
    console.error("Failed to save rule:", error);
    alert(t("withdrawKycRuleModal_alert_saveCatch"));
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
