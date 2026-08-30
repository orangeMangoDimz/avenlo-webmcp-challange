<template>
  <div class="modal-overlay" @click="$emit('close')">
    <div class="modal-container" @click.stop>
      <div class="modal-header">
        <h2><i class="fas fa-check-double"></i> Bulk Approve Applications</h2>
        <button class="modal-close" @click="$emit('close')">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="modal-body">
        <p class="modal-description">
          Approve <strong>{{ selectedCount }}</strong> selected application(s)
        </p>

        <div class="form-group">
          <label class="form-label">Select Tier Level for All *</label>
          <select v-model="selectedTierLevel" class="form-select" required>
            <option value="">Select tier level...</option>
            <option v-for="tier in tierLevels" :key="tier.id" :value="tier.id">
              Tier {{ tier.tierLevel }} - {{ tier.tierName }}
            </option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Select Commission Rules for All *</label>
          <div class="rules-selection-box">
            <div
              v-for="rule in commissionRules"
              :key="rule.id"
              class="rule-checkbox-item"
            >
              <label class="rule-checkbox-label">
                <input
                  type="checkbox"
                  :value="rule.id"
                  v-model="selectedRuleIds"
                  class="rule-checkbox-input"
                />
                <div class="rule-checkbox-content">
                  <div class="rule-name">
                    <i class="fas" :class="getRuleIcon(rule.ruleType)"></i>
                    {{ rule.ruleName }}
                  </div>
                  <div class="rule-meta">
                    {{ formatPaymentCycle(rule.paymentCycle) }} • ${{
                      rule.minimumPayout
                    }}
                    min payout
                  </div>
                </div>
              </label>
            </div>
          </div>
          <p v-if="commissionRules.length === 0" class="no-rules-message">
            No commission rules available. Please configure rules first.
          </p>
        </div>

        <div class="warning-box">
          <i class="fas fa-exclamation-triangle"></i>
          <span>
            <strong>Warning:</strong> This will activate all selected IB
            partnerships and grant commission access. The selected Tier Level
            and Rules will be applied to all applications. This action cannot be
            undone.
          </span>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" @click="$emit('close')">
          Cancel
        </button>
        <button
          class="btn btn-primary"
          @click="approve"
          :disabled="!selectedTierLevel || selectedRuleIds.length === 0"
        >
          <i class="fas fa-check"></i> Approve All
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from "vue";

const props = defineProps({
  selectedCount: {
    type: Number,
    required: true,
  },
  tierLevels: {
    type: Array,
    default: () => [],
  },
  commissionRules: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(["close", "approve"]);

const selectedTierLevel = ref("");
const selectedRuleIds = ref([]);

/**
 * 获取规则图标
 */
const getRuleIcon = (ruleType) => {
  const iconMap = {
    fixed: "fa-dollar-sign",
    percentage: "fa-percentage",
    tiered: "fa-layer-group",
    custom: "fa-cog",
  };
  return iconMap[ruleType] || "fa-file-invoice-dollar";
};

/**
 * 格式化支付周期
 */
const formatPaymentCycle = (cycle) => {
  const cycleMap = {
    daily: "Daily",
    weekly: "Weekly",
    monthly: "Monthly",
    quarterly: "Quarterly",
    yearly: "Yearly",
  };
  return cycleMap[cycle] || cycle || "Monthly";
};

const approve = () => {
  if (!selectedTierLevel.value) {
    alert("Please select a tier level.");
    return;
  }

  if (selectedRuleIds.value.length === 0) {
    alert("Please select at least one commission rule.");
    return;
  }

  const confirmMessage = `⚠️ Approve ${props.selectedCount} IB application(s)?\n\nThis will activate all selected IB partnerships with:\n- Tier Level: ${getTierName(selectedTierLevel.value)}\n- Rules: ${selectedRuleIds.value.length} rule(s)\n\nAre you sure you want to continue?`;

  if (confirm(confirmMessage)) {
    emit("approve", {
      tierLevelId: selectedTierLevel.value,
      ruleIds: selectedRuleIds.value,
    });
  }
};

/**
 * 获取Tier名称
 */
const getTierName = (tierId) => {
  const tier = props.tierLevels.find((t) => t.id === tierId);
  return tier ? `Tier ${tier.tierLevel} - ${tier.tierName}` : "Selected Tier";
};
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.6);
  z-index: 9998;
  display: flex;
  align-items: center;
  justify-content: center;
  animation: fadeIn 0.3s ease;
}

.modal-container {
  background: var(--color-surface);
  border-radius: var(--radius-xl);
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  width: 90%;
  max-width: 500px;
  animation: slideUp 0.3s ease;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.modal-header {
  padding: 25px 30px;
  border-bottom: 2px solid var(--color-border);
  background: var(--color-brand-solid);
  color: white;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-radius: 16px 16px 0 0;
}

.modal-header h2 {
  font-size: 22px;
  font-weight: 700;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 12px;
}

.modal-close {
  background: rgba(255, 255, 255, 0.2);
  border: none;
  color: white;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  transition: all 0.3s ease;
}

.modal-close:hover {
  background: rgba(255, 255, 255, 0.3);
}

.modal-body {
  padding: 30px;
}

.modal-description {
  font-size: 14px;
  color: var(--color-text);
  margin-bottom: 20px;
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

.form-select {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  transition: all 0.3s ease;
}

.form-select:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.warning-box {
  background: var(--color-danger-soft);
  border-left: 4px solid var(--color-danger-border);
  padding: 12px 16px;
  border-radius: var(--radius-md);
  display: flex;
  align-items: start;
  gap: 10px;
  font-size: 14px;
  color: var(--color-danger);
}

.warning-box i {
  color: var(--color-danger);
  flex-shrink: 0;
  margin-top: 2px;
}

.modal-footer {
  padding: 20px 30px;
  border-top: 2px solid var(--color-border);
  background: var(--color-surface-soft);
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

.btn {
  padding: 12px 24px;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.btn-secondary {
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  color: var(--color-text);
}

.btn-secondary:hover {
  background: var(--color-surface-soft);
  border-color: var(--color-border-strong);
}

.btn-primary {
  background: var(--color-brand-solid);
  color: white;
  box-shadow: 0 2px 8px rgba(var(--color-brand-rgb), 0.3);
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.4);
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.rules-selection-box {
  max-height: 300px;
  overflow-y: auto;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 12px;
  background: var(--color-surface-soft);
}

.rule-checkbox-item {
  margin-bottom: 12px;
}

.rule-checkbox-item:last-child {
  margin-bottom: 0;
}

.rule-checkbox-label {
  display: flex;
  align-items: start;
  gap: 12px;
  cursor: pointer;
  padding: 12px;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  transition: all 0.2s ease;
}

.rule-checkbox-label:hover {
  border-color: var(--color-brand);
  background: var(--color-brand-soft);
}

.rule-checkbox-input {
  margin-top: 4px;
  width: 18px;
  height: 18px;
  cursor: pointer;
  flex-shrink: 0;
}

.rule-checkbox-content {
  flex: 1;
}

.rule-name {
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 4px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.rule-name i {
  color: var(--color-brand);
  font-size: 14px;
}

.rule-meta {
  font-size: 14px;
  color: var(--color-muted);
}

.no-rules-message {
  color: var(--color-danger);
  font-size: 14px;
  text-align: center;
  padding: 20px;
  background: var(--color-danger-soft);
  border-radius: var(--radius-md);
  border: 1px solid var(--color-danger-soft);
}
</style>
