<template>
  <div class="rule-list">
    <div
      style="
        background: var(--color-brand-soft);
        padding: 15px;
        border-radius: var(--radius-md);
        margin-bottom: 20px;
        border-left: 1px solid var(--color-brand);
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
        <strong style="color: var(--color-ink); font-size: 14px"
          >About Conditional Logic Rules</strong
        >
      </div>
      <p
        style="
          color: var(--color-text);
          font-size: 13px;
          margin: 0;
          line-height: 1.6;
        "
      >
        Set simple rules based on answer choices: jump to a specific question or
        reject the application.
      </p>
    </div>

    <div v-if="rules.length === 0" class="empty-state">
      <i class="fas fa-sitemap"></i>
      <p>No rules configured yet</p>
    </div>

    <div v-for="rule in rules" :key="rule.id" class="rule-item">
      <div class="rule-header">
        <div class="rule-title">
          <i
            class="fas"
            :class="rule.actionType === 'jump_to' ? 'fa-arrow-right' : 'fa-ban'"
          ></i>
          {{ rule.name }}
        </div>
        <div style="display: flex; align-items: center; gap: 10px">
          <span
            class="rule-type"
            :style="
              rule.actionType === 'jump_to'
                ? 'background: #48bb78;'
                : 'background: #ef4444;'
            "
          >
            {{
              rule.actionType === "jump_to"
                ? "Jump to Question"
                : "Reject Application"
            }}
          </span>
          <div class="rule-actions">
            <button class="rule-action-btn" @click="handleEditRule(rule)">
              <i class="fas fa-edit"></i>
            </button>
            <button
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
              font-size: 11px;
              font-weight: 600;
            "
            >IF</span
          >
          <span
            >{{ rule.triggerQuestionText }} =
            <strong>"{{ rule.selectedAnswer }}"</strong></span
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
              rule.actionType === 'jump_to'
                ? 'background: var(--color-success-soft); color: var(--color-success);'
                : 'background: #fca5a5; color: #991b1b;'
            "
            style="
              padding: 2px 8px;
              border-radius: 4px;
              font-size: 11px;
              font-weight: 600;
            "
          >
            THEN
          </span>
          <span v-if="rule.actionType === 'jump_to'">
            Jump to {{ rule.jumpToQuestionText }}
          </span>
          <span v-else>
            <strong>Reject application</strong> with message: "{{
              rule.rejectMessage
            }}"
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { kycRuleService } from "@/services/kycTemplateService";

defineProps({
  templateId: {
    type: Number,
    required: true,
  },
  rules: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(["refresh"]);

const handleEditRule = (rule) => {
  // TODO: Implement edit functionality
  alert("Edit rule: " + rule.name);
};

const handleDeleteRule = async (ruleId) => {
  if (confirm("Are you sure you want to delete this rule?")) {
    try {
      const response = await kycRuleService.deleteRule(ruleId);
      if (response.success) {
        alert("✓ Rule deleted successfully!");
        emit("refresh");
      } else {
        alert(`Failed to delete rule: ${response.message}`);
      }
    } catch (error) {
      console.error("Failed to delete rule:", error);
      alert("Failed to delete rule. Please try again.");
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
  font-size: 11px;
  font-weight: 600;
}

.rule-description {
  font-size: 13px;
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
  font-size: 11px;
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
