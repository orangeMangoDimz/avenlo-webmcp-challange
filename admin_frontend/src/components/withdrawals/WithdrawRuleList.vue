<template>
  <div class="rule-list">
    <div
      style="
        background: var(--color-brand-soft);
        padding: 15px;
        border-radius: var(--radius-md);
        margin-bottom: 20px;
        border-left: 4px solid var(--color-brand);
      "
    >
      <div
        style="
          display: flex;
          align-items: center;
          gap: 10px;
          margin-bottom: 5px;
        "
      >
        <i
          class="fas fa-info-circle"
          style="color: var(--color-brand); font-size: 16px"
        ></i>
        <strong style="color: var(--color-ink); font-size: 14px">{{
          t("withdrawKycRule_aboutTitle")
        }}</strong>
      </div>
      <p
        style="
          color: var(--color-text);
          font-size: 14px;
          margin: 0;
          line-height: 1.6;
        "
      >
        {{ t("withdrawKycRule_aboutBody") }}
      </p>
    </div>

    <div v-if="rules.length === 0" class="empty-state">
      <i class="fas fa-sitemap"></i>
      <p>{{ t("withdrawKycRule_empty") }}</p>
    </div>

    <div v-for="rule in rules" :key="rule.id" class="rule-item">
      <div class="rule-header">
        <div class="rule-title">
          <i
            class="fas"
            :class="rule.ruleType === 'jump_to' ? 'fa-arrow-right' : 'fa-ban'"
          ></i>
          {{ rule.ruleName }}
        </div>
        <div style="display: flex; align-items: center; gap: 10px">
          <span
            class="rule-type"
            :style="
              rule.ruleType === 'jump_to'
                ? 'background: #48bb78;'
                : 'background: #ef4444;'
            "
          >
            {{
              rule.ruleType === "jump_to"
                ? t("withdrawKycRule_typeJump")
                : t("withdrawKycRule_typeReject")
            }}
          </span>
          <div
            v-if="hasEditRulePermission || hasDeleteRulePermission"
            class="rule-actions"
          >
            <button
              v-if="hasEditRulePermission"
              class="rule-action-btn"
              @click="handleEditRule(rule)"
            >
              <i class="fas fa-edit"></i>
            </button>
            <button
              v-if="hasDeleteRulePermission"
              class="rule-action-btn delete"
              @click="handleDeleteRule(rule.id)"
            >
              <i class="fas fa-trash"></i>
            </button>
          </div>
        </div>
      </div>
      <div class="rule-description">
        <div
          style="
            display: flex;
            align-items: start;
            gap: 8px;
            margin-bottom: 8px;
          "
        >
          <span
            style="
              background: var(--color-brand-soft);
              color: var(--color-brand-strong);
              padding: 2px 8px;
              border-radius: 4px;
              font-size: 14px;
              font-weight: 600;
            "
            >{{ t("withdrawKycRule_if") }}</span
          >
          <span
            >{{ rule.triggerQuestionText }} =
            <strong>"{{ getRuleAnswerLabel(rule) }}"</strong></span
          >
        </div>
        <div
          style="
            display: flex;
            align-items: start;
            gap: 8px;
            padding-left: 40px;
          "
        >
          <span
            :style="
              rule.ruleType === 'jump_to'
                ? 'background: var(--color-success-soft); color: var(--color-success);'
                : 'background: var(--color-danger-soft); color: var(--color-danger);'
            "
            style="
              padding: 2px 8px;
              border-radius: 4px;
              font-size: 14px;
              font-weight: 600;
            "
          >
            {{ t("withdrawKycRule_then") }}
          </span>
          <span v-if="rule.ruleType === 'jump_to'">
            {{
              tParams("withdrawKycRule_jumpTo", "Jump to {text}", {
                text:
                  rule.targetQuestionText ||
                  tParams("withdrawKycRule_questionRef", "Question #{id}", {
                    id: rule.targetQuestionId,
                  }),
              })
            }}
          </span>
          <span v-else>
            {{
              tParams(
                "withdrawKycRule_rejectWithMsg",
                'Reject application with message: "{msg}"',
                { msg: rule.rejectMessage },
              )
            }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { withdrawKycRuleService } from "@/services/withdrawKycTemplateService";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { translateApiErrorMessage } from "@/i18n/adminI18nBridge";

const { t, tParams } = useAdminI18n();

const props = defineProps({
  templateId: {
    type: Number,
    required: true,
  },
  rules: {
    type: Array,
    default: () => [],
  },
  questions: {
    type: Array,
    default: () => [],
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
  ruleService: {
    type: Object,
    default: () => withdrawKycRuleService,
  },
});

const emit = defineEmits(["refresh", "edit"]);

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

const getQuestionOptions = (rule) => {
  const question = props.questions.find(
    (item) => String(item.id) === String(rule.triggerQuestionId),
  );

  if (!question) {
    return [];
  }

  if (question.questionType === "yes_no") {
    return [
      { optionLabel: t("common_yes"), optionValue: "Yes" },
      { optionLabel: t("common_no"), optionValue: "No" },
    ];
  }

  return Array.isArray(question.options) ? question.options : [];
};

const getRuleAnswerLabel = (rule) => {
  if (rule?.triggerAnswerLabel) {
    return rule.triggerAnswerLabel;
  }

  const matchedOption = getQuestionOptions(rule).find(
    (option) => String(getOptionValue(option)) === String(rule.triggerAnswer),
  );
  return matchedOption
    ? getOptionLabel(matchedOption)
    : rule?.triggerAnswer || "";
};

const handleEditRule = (rule) => {
  emit("edit", rule);
};

const handleDeleteRule = async (ruleId) => {
  if (confirm(t("withdrawKycRule_confirm_delete"))) {
    try {
      const response = await props.ruleService.deleteRule(ruleId);
      if (response.success) {
        alert(t("withdrawKycRule_alert_delOk"));
        emit("refresh");
      } else {
        const raw = response.message || t("common_unknownError");
        alert(
          tParams("withdrawKycRule_alert_delFailed", "Failed: {msg}", {
            msg: translateApiErrorMessage(response.errorCode, raw),
          }),
        );
      }
    } catch (error) {
      console.error("Failed to delete rule:", error);
      const data = error?.response?.data ?? error;
      const rawMsg =
        data?.message || error?.message || t("common_unknownError");
      const msg = translateApiErrorMessage(data?.errorCode, rawMsg);
      alert(
        tParams("withdrawKycRule_alert_delFailed", "Failed: {msg}", { msg }),
      );
    }
  }
};
</script>

<style scoped>
.rule-list {
  margin-top: 20px;
}

.empty-state {
  padding: 60px 20px;
  text-align: center;
  color: var(--color-faint);
}

.empty-state i {
  font-size: 48px;
  margin-bottom: 15px;
  display: block;
}

.empty-state p {
  font-size: 16px;
}

.rule-item {
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 15px 20px;
  margin-bottom: 10px;
  transition: all 0.2s ease;
}

.rule-item:last-child {
  margin-bottom: 0;
}

.rule-item:hover {
  border-color: var(--color-brand);
  background: var(--color-brand-soft);
}

.rule-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}

.rule-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 8px;
}

.rule-type {
  background: var(--color-warning-solid);
  color: white;
  padding: 3px 10px;
  border-radius: var(--radius-lg);
  font-size: 14px;
  font-weight: 600;
}

.rule-description {
  font-size: 14px;
  color: var(--color-muted);
  line-height: 1.5;
}

.rule-actions {
  display: flex;
  gap: 5px;
}

.rule-action-btn {
  width: 26px;
  height: 26px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-muted);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
  font-size: 14px;
}

.rule-action-btn:hover {
  border-color: var(--color-brand);
  color: var(--color-brand);
  background: var(--color-brand-soft);
}

.rule-action-btn.delete:hover {
  border-color: var(--color-danger);
  color: var(--color-danger);
  background: var(--color-danger-soft);
}
</style>
