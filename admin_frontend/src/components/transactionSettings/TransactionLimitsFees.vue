<template>
  <div class="limits-fees-container">
    <!-- Deposit Limits -->
    <h3 class="section-title">
      <i class="fas fa-chart-line"></i> Deposit Limits
    </h3>

    <div class="form-row">
      <div class="form-group">
        <label>
          Minimum Deposit (USD)
          <span class="label-description"
            >Minimum amount clients can deposit</span
          >
        </label>
        <FormattedNumberInput
          v-model="limitsData.minimumAmount"
          :decimals="0"
          placeholder="10"
          :disabled="!canEdit"
          @update:modelValue="handleChange"
        />
      </div>
      <div class="form-group">
        <label>
          Maximum Deposit (USD)
          <span class="label-description">Maximum amount per transaction</span>
        </label>
        <FormattedNumberInput
          v-model="limitsData.maximumAmount"
          :decimals="0"
          placeholder="50000"
          :disabled="!canEdit"
          @update:modelValue="handleChange"
        />
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>
          Daily Deposit Limit (USD)
          <span class="label-description"
            >Maximum deposits per day per client</span
          >
        </label>
        <FormattedNumberInput
          v-model="limitsData.dailyLimit"
          :decimals="0"
          placeholder="100000"
          :disabled="!canEdit"
          @update:modelValue="handleChange"
        />
      </div>
      <div class="form-group">
        <label>
          Monthly Deposit Limit (USD)
          <span class="label-description"
            >Maximum deposits per month per client</span
          >
        </label>
        <FormattedNumberInput
          v-model="limitsData.monthlyLimit"
          :decimals="0"
          placeholder="500000"
          :disabled="!canEdit"
          @update:modelValue="handleChange"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from "vue";
import FormattedNumberInput from "../common/FormattedNumberInput.vue";

const props = defineProps({
  limits: {
    type: Object,
    default: () => ({}),
  },
  canEdit: {
    type: Boolean,
    default: true,
  },
});

const emit = defineEmits(["update", "change"]);

// 限额数据
const limitsData = ref({
  id: props.limits.id || null,
  transactionType: "deposit",
  paymentType: "all",
  minimumAmount: props.limits.minimumAmount || 10,
  maximumAmount: props.limits.maximumAmount || 50000,
  dailyLimit: props.limits.dailyLimit || 100000,
  monthlyLimit: props.limits.monthlyLimit || 500000,
  isActive: props.limits.isActive !== undefined ? props.limits.isActive : true,
});

// 处理变更
const handleChange = () => {
  emit("change", { ...limitsData.value });
};

// 监听prop变化
watch(
  () => props.limits,
  (newVal) => {
    limitsData.value = {
      id: newVal.id || null,
      transactionType: "deposit",
      paymentType: "all",
      minimumAmount: newVal.minimumAmount || 10,
      maximumAmount: newVal.maximumAmount || 50000,
      dailyLimit: newVal.dailyLimit || 100000,
      monthlyLimit: newVal.monthlyLimit || 500000,
      isActive: newVal.isActive !== undefined ? newVal.isActive : true,
    };
  },
  { deep: true },
);
</script>

<style scoped>
.limits-fees-container {
  padding: 0;
}

.section-title {
  font-size: 16px;
  color: var(--color-ink);
  margin-bottom: 20px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 10px;
}

.section-title i {
  color: var(--color-brand);
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  margin-bottom: 10px;
  color: var(--color-ink);
  font-weight: 600;
  font-size: 14px;
}

.label-description {
  display: block;
  color: var(--color-muted);
  font-weight: 400;
  font-size: 13px;
  margin-top: 5px;
}

.form-group input,
.form-group select {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  transition: all 0.3s ease;
  background: var(--color-surface);
}

.form-group input:focus,
.form-group select:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.form-row {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
}

@media (max-width: 768px) {
  .form-row {
    grid-template-columns: 1fr;
  }
}
</style>
