<template>
  <div class="transaction-result">
    <div v-if="!hideStepper" class="result-stepper">
      <div class="step-item complete">
        <div class="step-index"><i class="fas fa-check"></i></div>
        <div class="step-label">{{ firstStepLabel }}</div>
      </div>
      <div class="step-line complete"></div>
      <div class="step-item complete">
        <div class="step-index"><i class="fas fa-check"></i></div>
        <div class="step-label">{{ secondStepLabel }}</div>
      </div>
      <div class="step-line complete"></div>
      <div class="step-item active">
        <div class="step-index">3</div>
        <div class="step-label">{{ t("transStepConfirm", "Confirm") }}</div>
      </div>
    </div>

    <div class="confirm-card">
      <div :class="['confirm-icon', status]">
        <i :class="statusIconClass"></i>
      </div>

      <h2>{{ titleText }}</h2>
      <p class="confirm-copy">{{ messageText }}</p>

      <div v-if="reference" class="reference-card">
        <div class="reference-label">
          {{ t("transTransactionReference", "Transaction Reference") }}
        </div>
        <div class="reference-value">{{ reference }}</div>
      </div>

      <div v-if="visibleRows.length" class="summary-card">
        <div
          v-for="(row, index) in visibleRows"
          :key="`${row.label}-${index}`"
          :class="[
            'summary-row',
            row.section ? 'summary-row-section' : '',
            row.image ? 'summary-row-image' : '',
          ]"
        >
          <template v-if="row.section">
            <span class="summary-section-title">{{ row.label }}</span>
          </template>
          <template v-else>
            <span>{{ row.label }}</span>
            <span v-if="row.image" class="summary-row-image-wrap">
              <img
                :src="String(row.value)"
                :alt="row.label"
                class="summary-qr-image"
              />
            </span>
            <span v-else class="summary-row-value">
              <span>{{ row.value }}</span>
              <button
                v-if="row.copyable && row.value"
                type="button"
                class="copy-btn"
                :title="
                  copiedIndex === index
                    ? t('transCopied', 'Copied!')
                    : t('transCopy', 'Copy')
                "
                :aria-label="
                  copiedIndex === index
                    ? t('transCopied', 'Copied!')
                    : t('transCopy', 'Copy')
                "
                @click="copyRowValue(row.value, index)"
              >
                <i
                  :class="
                    copiedIndex === index ? 'fas fa-check' : 'fas fa-copy'
                  "
                ></i>
              </button>
            </span>
          </template>
        </div>
      </div>

      <div class="confirm-actions">
        <button
          type="button"
          class="btn btn-primary primary-action"
          @click="handlePrimaryAction"
        >
          <i :class="primaryActionIcon"></i>
          {{ primaryActionText }}
        </button>
        <button
          type="button"
          class="btn secondary-action"
          @click="emit('view-history')"
        >
          <i class="fas fa-list"></i>
          {{ t("transTransactionHistory", "Transaction History") }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from "vue";
import { useLanguageStore } from "@/stores/language";

const languageStore = useLanguageStore();
const t = (key, fallback = "") => languageStore.t(key, fallback);

const props = defineProps({
  status: {
    type: String,
    default: "success",
  },
  titleText: {
    type: String,
    default: "",
  },
  messageText: {
    type: String,
    default: "",
  },
  reference: {
    type: String,
    default: "",
  },
  rows: {
    type: Array,
    default: () => [],
  },
  hideStepper: {
    type: Boolean,
    default: false,
  },
  firstStepLabel: {
    type: String,
    default: "",
  },
  secondStepLabel: {
    type: String,
    default: "",
  },
  primaryActionText: {
    type: String,
    default: "",
  },
  primaryActionIcon: {
    type: String,
    default: "fas fa-plus",
  },
});

const emit = defineEmits(["primary-action", "view-history"]);

const copiedIndex = ref(null);
let copiedResetTimer = null;

const visibleRows = computed(() =>
  Array.isArray(props.rows)
    ? props.rows.filter((row) => {
        if (!row || !row.label) {
          return false;
        }
        if (row.section) {
          return true;
        }
        return (
          row.value !== "" && row.value !== null && row.value !== undefined
        );
      })
    : [],
);

const statusIconClass = computed(() => {
  if (props.status === "pending") {
    return "fas fa-clock";
  }

  return props.status === "success" ? "fas fa-check" : "fas fa-times";
});

const copyRowValue = async (value, index) => {
  try {
    await navigator.clipboard.writeText(String(value));
    copiedIndex.value = index;
    if (copiedResetTimer) {
      clearTimeout(copiedResetTimer);
    }
    copiedResetTimer = setTimeout(() => {
      copiedIndex.value = null;
      copiedResetTimer = null;
    }, 2000);
  } catch (err) {
    alert(t("transAlertCopyFailed", "Failed to copy address"));
  }
};

const handlePrimaryAction = () => {
  emit("primary-action");
};
</script>

<style scoped>
.transaction-result {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 22px;
}

.result-stepper {
  display: flex;
  align-items: center;
  gap: 16px;
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 18px;
  padding: 18px 22px;
}

.step-item {
  display: flex;
  align-items: center;
  gap: 10px;
  color: #94a3b8;
  font-weight: 700;
  white-space: nowrap;
}

.step-item.complete,
.step-item.active {
  color: #3d4b63;
}

.step-index {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-border);
  color: #64748b;
  font-size: 14px;
  font-weight: 800;
}

.step-item.complete .step-index {
  background: #43b66f;
  color: #ffffff;
}

.step-item.active .step-index {
  background: var(--color-brand);
  color: #ffffff;
}

.step-line {
  flex: 1;
  height: 2px;
  background: #d6dee8;
}

.step-line.complete {
  background: #43b66f;
}

.confirm-card {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 22px;
  box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
  padding: 48px 24px;
  text-align: center;
}

.confirm-icon {
  width: 80px;
  height: 80px;
  margin: 0 auto 24px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #41b86c;
  color: #ffffff;
  font-size: 38px;
  box-shadow: 0 18px 30px rgba(65, 184, 108, 0.2);
}

.confirm-icon.fail {
  background: var(--color-danger);
  box-shadow: 0 18px 30px rgba(239, 68, 68, 0.2);
}

.confirm-icon.pending {
  background: var(--color-warning);
  box-shadow: 0 18px 30px rgba(245, 158, 11, 0.22);
}

.confirm-card h2 {
  margin: 0 0 14px;
  font-size: 24px;
  font-weight: 800;
  color: #2f3a4c;
}

.confirm-copy {
  max-width: 560px;
  margin: 0 auto 26px;
  color: #64748b;
  font-size: 15px;
  line-height: 1.7;
}

.reference-card {
  display: inline-flex;
  flex-direction: column;
  gap: 6px;
  background: var(--color-surface-soft);
  border-radius: 14px;
  padding: 16px 24px;
  margin-bottom: 24px;
}

.reference-label {
  color: #64748b;
  font-size: 14px;
}

.reference-value {
  color: #2f3a4c;
  font-size: 16px;
  font-weight: 800;
}

.summary-card {
  width: min(100%, 420px);
  margin: 0 auto 24px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-xl);
  padding: 18px 20px;
}

.summary-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 6px 0;
  color: #3d4b63;
  font-size: 15px;
}

.summary-row > span:first-child {
  color: var(--color-muted);
}

.summary-row-section {
  display: block;
  padding: 14px 0 8px;
  margin-top: 8px;
  border-top: 1px solid var(--color-border);
}

.summary-row-section:first-child {
  margin-top: 0;
  padding-top: 0;
  border-top: 0;
}

.summary-section-title {
  display: block;
  width: 100%;
  color: #2f3a4c;
  font-size: 14px;
  font-weight: 800;
  text-align: left;
}

.summary-row-value {
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
  text-align: right;
  flex-wrap: wrap;
  max-width: 70%;
}

.summary-row-image {
  flex-direction: column;
  align-items: stretch;
  gap: 10px;
}

.summary-row-image-wrap {
  display: flex;
  justify-content: center;
  width: 100%;
}

.summary-qr-image {
  width: min(100%, 220px);
  height: auto;
  border-radius: var(--radius-lg);
  background: #ffffff;
  padding: 10px;
  box-sizing: border-box;
}

.copy-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  border: 1px solid #d7dfeb;
  border-radius: var(--radius-md);
  background: #ffffff;
  color: #536277;
  font-size: 12px;
  font-weight: 700;
  padding: 4px 8px;
  cursor: pointer;
  white-space: nowrap;
}

.copy-btn:hover {
  border-color: var(--color-brand);
  color: var(--color-brand);
}

.confirm-actions {
  display: flex;
  justify-content: center;
  gap: 12px;
  flex-wrap: wrap;
}

.primary-action {
  min-width: 200px;
  min-height: 46px;
  border: 0;
  border-radius: var(--radius-lg);
  background: linear-gradient(
    135deg,
    var(--color-brand) 0%,
    var(--color-brand-strong) 100%
  );
  color: #ffffff;
  font-size: 15px;
  font-weight: 800;
  box-shadow: 0 14px 28px rgba(var(--color-brand-rgb), 0.2);
}

.secondary-action {
  min-width: 200px;
  min-height: 46px;
  border: 1px solid #d7dfeb;
  border-radius: var(--radius-lg);
  background: #eef2f7;
  color: #536277;
  font-size: 15px;
  font-weight: 800;
}

.primary-action i,
.secondary-action i {
  margin-right: 8px;
}

@media (max-width: 768px) {
  .result-stepper {
    gap: 8px;
    padding: 14px;
  }

  .step-label {
    display: none;
  }

  .confirm-card {
    padding: 36px 20px;
  }

  .confirm-actions {
    flex-direction: column;
  }

  .primary-action,
  .secondary-action {
    width: 100%;
  }
}
</style>
