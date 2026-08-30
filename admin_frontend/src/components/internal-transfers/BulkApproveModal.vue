<template>
  <Teleport to="body">
    <div :class="['bulk-modal', { show: modelValue }]" @click="close">
      <div class="bulk-modal-content" @click.stop>
        <div class="bulk-modal-header">
          <h3>
            <i class="fas fa-check-double"></i>
            {{ t("internalXfer_bulkApprove_title") }}
          </h3>
          <button class="bulk-modal-close" @click="close">×</button>
        </div>

        <div class="bulk-modal-body">
          <!-- Selected Transfers List -->
          <div class="selected-deposit-list">
            <h4>
              <i class="fas fa-exchange-alt"></i>
              {{ t("internalXfer_bulkApprove_selectedTitle") }}
              <span class="count-badge">{{ selectedTransfers.length }}</span>
            </h4>
            <div class="selected-deposit-items">
              <div
                v-for="transfer in selectedTransfers"
                :key="transfer.id"
                class="selected-deposit-item"
              >
                <div class="selected-deposit-avatar">
                  {{ getInitials(transfer.clientName) }}
                </div>
                <div class="selected-deposit-info">
                  <div class="selected-deposit-name">
                    {{ transfer.clientName }}
                  </div>
                  <div class="selected-deposit-id">
                    {{ transfer.transactionId }}
                  </div>
                </div>
                <div class="selected-deposit-amount">
                  ${{ formatAmount(transfer.amount) }}
                </div>
              </div>
            </div>
          </div>

          <!-- Summary Info -->
          <div class="bulk-summary">
            <div class="bulk-summary-item">
              <span class="bulk-summary-label">{{
                t("internalXfer_bulkApprove_totalCount")
              }}</span>
              <span class="bulk-summary-value">{{
                selectedTransfers.length
              }}</span>
            </div>
            <div class="bulk-summary-item">
              <span class="bulk-summary-label">{{
                t("internalXfer_bulkApprove_totalAmount")
              }}</span>
              <span class="bulk-summary-value highlight"
                >${{ formatAmount(totalAmount) }}</span
              >
            </div>
            <div class="bulk-summary-item">
              <span class="bulk-summary-label">{{
                t("internalXfer_bulkApprove_approvalDate")
              }}</span>
              <span class="bulk-summary-value">{{ currentDate }}</span>
            </div>
          </div>

          <!-- Admin Notes -->
          <div class="form-group">
            <label for="admin-notes">{{
              t("internalXfer_bulkApprove_adminNotes")
            }}</label>
            <textarea
              id="admin-notes"
              v-model="adminNotes"
              :placeholder="t('internalXfer_bulkApprove_adminNotesPlaceholder')"
              rows="4"
            ></textarea>
          </div>

          <div class="info-box warning">
            <p>
              <strong
                ><i class="fas fa-exclamation-triangle"></i>
                {{ t("common_warning") }}:</strong
              >
              {{
                tParams(
                  "internalXfer_bulkApprove_warning",
                  "This action will approve and complete {n} transfer(s) with a total amount of {amount}. Funds will be immediately transferred between accounts. This action cannot be undone.",
                  {
                    n: selectedTransfers.length,
                    amount: "$" + formatAmount(totalAmount),
                  },
                )
              }}
            </p>
          </div>
        </div>

        <div class="bulk-modal-footer">
          <button
            class="btn btn-secondary"
            @click="close"
            :disabled="processing"
          >
            {{ t("common_cancel") }}
          </button>
          <button
            class="btn btn-primary"
            @click="confirm"
            :disabled="processing"
          >
            <i
              :class="
                processing ? 'fas fa-spinner fa-spin' : 'fas fa-check-double'
              "
            ></i>
            {{
              processing
                ? t("internalXfer_bulkApprove_btnApproving")
                : approveButtonLabel
            }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed } from "vue";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t, tParams, languageStore } = useAdminI18n();

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  selectedTransfers: {
    type: Array,
    default: () => [],
  },
  processing: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["update:modelValue", "confirm"]);

const adminNotes = ref("");

// 计算总金额
const totalAmount = computed(() => {
  return props.selectedTransfers.reduce((sum, transfer) => {
    return sum + parseFloat(transfer.amount || 0);
  }, 0);
});

const approveButtonLabel = computed(() => {
  const n = props.selectedTransfers.length;
  if (n === 1) return t("internalXfer_bulkApprove_btnApprove1");
  return tParams(
    "internalXfer_bulkApprove_btnApproveN",
    "Approve {n} Transfer(s)",
    { n },
  );
});

// 当前日期
const currentDate = computed(() => {
  const today = new Date();
  const loc = languageStore.currentLanguage === "zh" ? "zh-CN" : "en-US";
  return today.toLocaleDateString(loc, {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
});

// 关闭Modal
const close = () => {
  if (!props.processing) {
    emit("update:modelValue", false);
    adminNotes.value = "";
  }
};

// 确认批准
const confirm = () => {
  if (props.selectedTransfers.length === 0) {
    alert(t("internalXfer_bulkApprove_noSelection"));
    return;
  }

  emit("confirm", {
    transferIds: props.selectedTransfers.map((row) => row.id),
    adminNotes: adminNotes.value.trim() || null,
  });
};

// 格式化金额
const formatAmount = (amount) => {
  if (!amount) return "0.00";
  return parseFloat(amount).toLocaleString("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
};

// 获取首字母
const getInitials = (name) => {
  if (!name) return "??";
  const parts = name.split(" ");
  if (parts.length >= 2) {
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
  }
  return name.substring(0, 2).toUpperCase();
};
</script>

<style scoped>
.bulk-modal {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  z-index: 2000;
  align-items: center;
  justify-content: center;
}

.bulk-modal.show {
  display: flex;
}

.bulk-modal-content {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  max-width: 700px;
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

.bulk-modal-header {
  padding: 25px 30px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--color-surface-soft);
}

.bulk-modal-header h3 {
  font-size: 20px;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 0;
}

.bulk-modal-header h3 i {
  color: var(--color-brand);
  font-size: 22px;
}

.bulk-modal-close {
  background: none;
  border: none;
  font-size: 28px;
  color: var(--color-faint);
  cursor: pointer;
  transition: all 0.2s ease;
  line-height: 1;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-sm);
}

.bulk-modal-close:hover {
  color: var(--color-brand);
  background: var(--color-brand-soft);
}

.bulk-modal-body {
  padding: 30px;
}

.selected-deposit-list {
  background: var(--color-surface-soft);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 20px;
  margin-bottom: 25px;
}

.selected-deposit-list h4 {
  font-size: 14px;
  color: var(--color-text);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 15px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.count-badge {
  background: var(--color-brand-solid);
  color: white;
  padding: 2px 10px;
  border-radius: var(--radius-lg);
  font-size: 12px;
}

.selected-deposit-items {
  max-height: 300px;
  overflow-y: auto;
}

.selected-deposit-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px;
  background: var(--color-surface);
  border-radius: var(--radius-md);
  margin-bottom: 8px;
  border: 1px solid var(--color-border);
}

.selected-deposit-item:last-child {
  margin-bottom: 0;
}

.selected-deposit-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: var(--color-brand-solid);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: 600;
  font-size: 13px;
  flex-shrink: 0;
}

.selected-deposit-info {
  flex: 1;
}

.selected-deposit-name {
  font-weight: 600;
  color: var(--color-ink);
  font-size: 14px;
  margin-bottom: 2px;
}

.selected-deposit-id {
  font-size: 12px;
  color: var(--color-muted);
  font-family: "Courier New", monospace;
}

.selected-deposit-amount {
  font-weight: 700;
  color: var(--color-success);
  font-size: 14px;
}

.bulk-summary {
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 20px;
  margin-bottom: 25px;
}

.bulk-summary-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 0;
  border-bottom: 1px solid var(--color-border);
}

.bulk-summary-item:last-child {
  border-bottom: none;
}

.bulk-summary-label {
  font-weight: 600;
  color: var(--color-muted);
  font-size: 14px;
}

.bulk-summary-value {
  color: var(--color-ink);
  font-size: 15px;
  font-weight: 600;
}

.bulk-summary-value.highlight {
  color: var(--color-success);
  font-size: 18px;
  font-weight: 700;
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

.form-group textarea {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  font-family: inherit;
  resize: vertical;
  transition: all 0.3s ease;
}

.form-group textarea:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.info-box {
  background: var(--color-danger-soft);
  border-left: 4px solid var(--color-danger-border);
  padding: 15px 20px;
  border-radius: var(--radius-md);
}

.info-box p {
  color: var(--color-text);
  font-size: 13px;
  line-height: 1.6;
  margin: 0;
}

.bulk-modal-footer {
  padding: 20px 30px;
  border-top: 2px solid var(--color-border);
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  background: var(--color-surface-soft);
}

.btn {
  padding: 12px 24px;
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

.btn-secondary {
  background: var(--color-border);
  color: var(--color-text);
}

.btn-secondary:hover:not(:disabled) {
  background: var(--color-border-strong);
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

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

@media (max-width: 768px) {
  .bulk-modal-content {
    width: 95%;
    margin: 10px;
  }

  .bulk-modal-footer {
    flex-direction: column;
  }

  .btn {
    width: 100%;
    justify-content: center;
  }
}
</style>
