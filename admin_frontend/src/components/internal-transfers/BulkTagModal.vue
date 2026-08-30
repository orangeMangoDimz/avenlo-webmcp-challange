<template>
  <Teleport to="body">
    <div :class="['bulk-tag-modal', { show: modelValue }]" @click="close">
      <div class="bulk-tag-modal-content" @click.stop>
        <div class="bulk-tag-modal-header">
          <h3>
            <i class="fas fa-tags"></i> {{ t("internalXfer_bulkTag_title") }}
          </h3>
          <button class="bulk-tag-modal-close" @click="close">×</button>
        </div>

        <div class="bulk-tag-modal-body">
          <!-- Tag Input -->
          <div class="form-group">
            <label for="tag-name-input">{{
              t("internalXfer_bulkTag_labelName")
            }}</label>
            <input
              id="tag-name-input"
              ref="tagInput"
              type="text"
              v-model="tagName"
              :placeholder="t('internalXfer_bulkTag_namePlaceholder')"
              @keyup.enter="confirm"
            />
          </div>

          <!-- Preview -->
          <div class="bulk-tag-preview">
            <h4>
              <i class="fas fa-eye"></i>
              {{
                tParams(
                  "internalXfer_bulkTag_previewTitle",
                  "Preview - This tag will be added to {n} transfer(s)",
                  { n: selectedTransfers.length },
                )
              }}
            </h4>
            <div class="preview-tag">
              <span class="bulk-tag-preview-item">
                <i class="fas fa-tag"></i>
                {{ tagName || t("internalXfer_bulkTag_previewFallback") }}
              </span>
            </div>
          </div>

          <!-- Selected Transfers -->
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
        </div>

        <div class="bulk-tag-modal-footer">
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
            :disabled="processing || !tagName.trim()"
          >
            <i
              :class="processing ? 'fas fa-spinner fa-spin' : 'fas fa-tag'"
            ></i>
            {{
              processing
                ? t("internalXfer_bulkTag_adding")
                : t("internalXfer_bulkTag_btnAdd")
            }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, watch, nextTick } from "vue";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t, tParams } = useAdminI18n();

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

const tagName = ref("");
const tagInput = ref(null);

// 关闭Modal
const close = () => {
  if (!props.processing) {
    emit("update:modelValue", false);
    tagName.value = "";
  }
};

// 确认添加标签
const confirm = () => {
  const tag = tagName.value.trim();
  if (!tag) {
    alert(t("internalXfer_bulkTag_enterName"));
    return;
  }

  if (props.selectedTransfers.length === 0) {
    alert(t("internalXfer_bulkTag_noSelection"));
    return;
  }

  emit("confirm", {
    transferIds: props.selectedTransfers.map((row) => row.id),
    tagName: tag,
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

// 监听Modal打开，自动聚焦输入框
watch(
  () => props.modelValue,
  (newVal) => {
    if (newVal) {
      nextTick(() => {
        tagInput.value?.focus();
      });
    } else {
      tagName.value = "";
    }
  },
);
</script>

<style scoped>
.bulk-tag-modal {
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

.bulk-tag-modal.show {
  display: flex;
}

.bulk-tag-modal-content {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
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

.bulk-tag-modal-header {
  padding: 25px 30px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--color-warning-soft);
}

.bulk-tag-modal-header h3 {
  font-size: 20px;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 0;
}

.bulk-tag-modal-header h3 i {
  color: var(--color-warning);
  font-size: 22px;
}

.bulk-tag-modal-close {
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

.bulk-tag-modal-close:hover {
  color: var(--color-warning);
  background: var(--color-warning-soft);
}

.bulk-tag-modal-body {
  padding: 30px;
}

.form-group {
  margin-bottom: 25px;
}

.form-group label {
  display: block;
  margin-bottom: 10px;
  color: var(--color-ink);
  font-weight: 600;
  font-size: 14px;
}

.form-group input {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  transition: all 0.3s ease;
}

.form-group input:focus {
  outline: none;
  border-color: var(--color-warning);
  box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
}

.bulk-tag-preview {
  background: var(--color-warning-soft);
  border: 2px solid var(--color-warning-border);
  border-radius: var(--radius-md);
  padding: 20px;
  margin-bottom: 25px;
}

.bulk-tag-preview h4 {
  font-size: 14px;
  color: var(--color-warning);
  font-weight: 600;
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.bulk-tag-preview-item {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  background: var(--color-warning-solid);
  color: white;
  border-radius: var(--radius-xl);
  font-size: 13px;
  font-weight: 500;
}

.selected-deposit-list {
  background: var(--color-surface-soft);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 20px;
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
  max-height: 200px;
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

.bulk-tag-modal-footer {
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
  background: var(--color-warning-solid);
  color: white;
  box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
}

.btn-primary:hover:not(:disabled) {
  background: var(--color-warning-solid);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

@media (max-width: 768px) {
  .bulk-tag-modal-content {
    width: 95%;
  }

  .bulk-tag-modal-footer {
    flex-direction: column;
  }

  .btn {
    width: 100%;
    justify-content: center;
  }
}
</style>
