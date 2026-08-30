<template>
  <Teleport to="body">
    <div :class="['reject-modal', { show: modelValue }]" @click="close">
      <div class="reject-modal-content" @click.stop>
        <div class="reject-modal-header">
          <h3><i class="fas fa-times-circle"></i> {{ titleText }}</h3>
          <button class="reject-modal-close" @click="close">×</button>
        </div>

        <div class="reject-modal-body">
          <!-- Rejection Reasons -->
          <div class="form-group">
            <label>
              <i class="fas fa-exclamation-triangle"></i>
              {{ t("rejectModal_selectReasonLabel") }}
            </label>
            <div class="reject-reasons-container">
              <div
                v-for="reason in rejectionReasons"
                :key="reason.id"
                :class="[
                  'reject-reason-item',
                  { selected: selectedReasonId === reason.id },
                ]"
                @click="selectReason(reason.id)"
              >
                <label class="reject-reason-radio">
                  <input
                    type="radio"
                    name="rejectReason"
                    :value="reason.id"
                    v-model="selectedReasonId"
                  />
                  <span class="reject-reason-radio-mark"></span>
                </label>
                <div class="reject-reason-content">
                  <div class="reject-reason-title">
                    {{ reason.reasonTitle }}
                  </div>
                  <div class="reject-reason-description">
                    {{ reason.reasonDescription }}
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Custom Reason (if "Other" selected) -->
          <div class="form-group" v-if="isCustomReason">
            <label for="custom-reason">
              <i class="fas fa-edit"></i>
              {{ t("rejectModal_customReasonLabel") }}
            </label>
            <textarea
              id="custom-reason"
              v-model="customReason"
              :placeholder="customReasonPlaceholder"
              rows="4"
            ></textarea>
          </div>

          <!-- Additional Notes -->
          <div class="form-group">
            <label for="rejection-notes">
              <i class="fas fa-sticky-note"></i>
              {{ t("rejectModal_additionalNotes") }}
            </label>
            <textarea
              id="rejection-notes"
              v-model="rejectionNotes"
              :placeholder="t('rejectModal_notesPlaceholder')"
              rows="3"
            ></textarea>
          </div>

          <!-- Warning Box -->
          <div class="reject-warning-box">
            <i class="fas fa-exclamation-circle"></i>
            <div class="reject-warning-content">
              <h5>{{ t("rejectModal_importantNotice") }}</h5>
              <p>{{ warningText }}</p>
            </div>
          </div>
        </div>

        <div class="reject-modal-footer">
          <button
            class="btn btn-secondary"
            @click="close"
            :disabled="processing"
          >
            <i class="fas fa-times"></i> {{ t("rejectModal_btnCancel") }}
          </button>
          <button
            class="btn btn-danger"
            @click="confirm"
            :disabled="processing || !canSubmit"
          >
            <i
              :class="processing ? 'fas fa-spinner fa-spin' : 'fas fa-ban'"
            ></i>
            {{ processing ? processingText : confirmButtonText }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch, onMounted } from "vue";
import withdrawalApi from "../../services/withdrawalApi";
import depositApi from "../../services/depositApi";
import * as internalTransferApi from "../../services/internalTransferApi";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t, tParams } = useAdminI18n();

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  withdrawalId: {
    type: [Number, String],
    default: null,
  },
  processing: {
    type: Boolean,
    default: false,
  },
  entityLabel: {
    type: String,
    default: "Withdrawal",
  },
  rejectionScope: {
    type: String,
    default: "withdrawal",
  },
});

const emit = defineEmits(["update:modelValue", "confirm"]);

const rejectionReasons = ref([]);
const selectedReasonId = ref(null);
const customReason = ref("");
const rejectionNotes = ref("");
const normalizedEntityLabel = computed(
  () =>
    String(props.entityLabel || "").trim() || t("withdrawalMgmt_entityLabel"),
);
const titleText = computed(() =>
  tParams("rejectModal_title", "Reject {entity} request", {
    entity: normalizedEntityLabel.value,
  }),
);
const confirmButtonText = computed(() =>
  tParams("rejectModal_confirmBtn", "Reject {entity}", {
    entity: normalizedEntityLabel.value,
  }),
);
const processingText = computed(() => t("rejectModal_processing"));
const customReasonPlaceholder = computed(() =>
  tParams(
    "rejectModal_customReasonPlaceholder",
    "Please provide a detailed reason for rejecting this {entity} request...",
    { entity: normalizedEntityLabel.value },
  ),
);
const warningText = computed(() =>
  tParams(
    "rejectModal_warning",
    "Rejecting this {entity} request will notify the client immediately via email. The selected reason will be included in the notification. This action cannot be undone.",
    { entity: normalizedEntityLabel.value },
  ),
);
const normalizedRejectionScope = computed(() => {
  const scope = String(props.rejectionScope || "withdrawal")
    .trim()
    .toLowerCase();
  if (scope === "deposit") return "deposit";
  if (scope === "internal_transfer" || scope === "internal-transfer")
    return "internal_transfer";
  return "withdrawal";
});

// 是否是自定义原因
const isCustomReason = computed(() => {
  const selected = rejectionReasons.value.find(
    (r) => r.id === selectedReasonId.value,
  );
  return selected && selected.reasonKey === "custom";
});

// 是否可以提交
const canSubmit = computed(() => {
  if (!selectedReasonId.value) return false;
  if (isCustomReason.value && !customReason.value.trim()) return false;
  return true;
});

// 加载拒绝原因列表
const loadRejectionReasons = async () => {
  try {
    const response =
      normalizedRejectionScope.value === "deposit"
        ? await depositApi.getRejectionReasons({ scope: "deposit" })
        : normalizedRejectionScope.value === "internal_transfer"
          ? await internalTransferApi.getRejectionReasons({
              scope: "internal_transfer",
            })
          : await withdrawalApi.getRejectionReasons({ scope: "withdrawal" });
    if (response.success) {
      rejectionReasons.value = response.data || [];
    }
  } catch (err) {
    console.error("Failed to load rejection reasons:", err);
  }
};

// 选择原因
const selectReason = (reasonId) => {
  selectedReasonId.value = reasonId;
};

// 关闭Modal
const close = () => {
  if (!props.processing) {
    emit("update:modelValue", false);
    resetForm();
  }
};

// 确认拒绝
const confirm = () => {
  if (!selectedReasonId.value) {
    alert(t("rejectModal_alert_selectReason"));
    return;
  }

  if (isCustomReason.value && !customReason.value.trim()) {
    alert(t("rejectModal_alert_customRequired"));
    return;
  }

  emit("confirm", {
    rejectionReasonId: selectedReasonId.value,
    rejectionNotes: rejectionNotes.value.trim() || null,
    customReason: isCustomReason.value ? customReason.value.trim() : null,
  });
};

// 重置表单
const resetForm = () => {
  selectedReasonId.value = null;
  customReason.value = "";
  rejectionNotes.value = "";
};

// 监听Modal打开
watch(
  () => props.modelValue,
  (newVal) => {
    if (newVal) {
      loadRejectionReasons();
    } else {
      resetForm();
    }
  },
);

watch(
  () => normalizedRejectionScope.value,
  () => {
    rejectionReasons.value = [];
    selectedReasonId.value = null;
    if (props.modelValue) {
      loadRejectionReasons();
    }
  },
);

// 页面加载时预加载原因列表
onMounted(() => {
  loadRejectionReasons();
});
</script>

<style scoped>
.reject-modal {
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

.reject-modal.show {
  display: flex;
}

.reject-modal-content {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  max-width: 650px;
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

.reject-modal-header {
  padding: 25px 30px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--color-surface-soft);
}

.reject-modal-header h3 {
  font-size: 20px;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 0;
}

.reject-modal-header h3 i {
  color: var(--color-danger);
  font-size: 22px;
}

.reject-modal-close {
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

.reject-modal-close:hover {
  color: var(--color-danger);
  background: var(--color-danger-soft);
}

.reject-modal-body {
  padding: 30px;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  margin-bottom: 15px;
  color: var(--color-ink);
  font-weight: 600;
  font-size: 15px;
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
  border-color: var(--color-danger);
  box-shadow: 0 0 0 3px rgba(229, 62, 62, 0.1);
}

.reject-reasons-container {
  max-height: 400px;
  overflow-y: auto;
  padding-right: 5px;
}

.reject-reason-item {
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 12px 15px;
  margin-bottom: 10px;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: start;
  gap: 12px;
}

.reject-reason-item:hover {
  border-color: var(--color-danger);
  background: var(--color-danger-soft);
}

.reject-reason-item.selected {
  background: var(--color-danger-soft);
  border-color: var(--color-danger);
  box-shadow: 0 2px 8px rgba(229, 62, 62, 0.2);
}

.reject-reason-radio {
  position: relative;
  display: inline-block;
  width: 20px;
  height: 20px;
  margin-top: 2px;
  flex-shrink: 0;
}

.reject-reason-radio input[type="radio"] {
  position: absolute;
  opacity: 0;
  cursor: pointer;
  width: 20px;
  height: 20px;
  margin: 0;
}

.reject-reason-radio-mark {
  position: absolute;
  top: 0;
  left: 0;
  height: 20px;
  width: 20px;
  background-color: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: 50%;
  transition: all 0.3s ease;
}

.reject-reason-radio:hover .reject-reason-radio-mark {
  border-color: var(--color-danger);
}

.reject-reason-radio input[type="radio"]:checked ~ .reject-reason-radio-mark {
  background: var(--color-surface);
  border-color: var(--color-danger);
}

.reject-reason-radio-mark:after {
  content: "";
  position: absolute;
  display: none;
  left: 4px;
  top: 4px;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--color-danger-solid);
}

.reject-reason-radio
  input[type="radio"]:checked
  ~ .reject-reason-radio-mark:after {
  display: block;
}

.reject-reason-content {
  flex: 1;
}

.reject-reason-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 3px;
}

.reject-reason-description {
  font-size: 14px;
  color: var(--color-muted);
}

.reject-warning-box {
  background: var(--color-danger-soft);
  border: 2px solid var(--color-danger-border);
  border-radius: var(--radius-md);
  padding: 15px;
  display: flex;
  align-items: start;
  gap: 12px;
}

.reject-warning-box i {
  color: var(--color-danger);
  font-size: 20px;
  margin-top: 2px;
}

.reject-warning-content {
  flex: 1;
}

.reject-warning-content h5 {
  font-size: 14px;
  color: var(--color-danger);
  font-weight: 600;
  margin-bottom: 5px;
}

.reject-warning-content p {
  font-size: 14px;
  color: var(--color-danger);
  line-height: 1.5;
  margin: 0;
}

.reject-modal-footer {
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

.btn-danger {
  background: linear-gradient(
    135deg,
    var(--color-danger) 0%,
    var(--color-danger) 100%
  );
  color: white;
  box-shadow: 0 2px 8px rgba(229, 62, 62, 0.3);
}

.btn-danger:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(229, 62, 62, 0.4);
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
