<template>
  <div v-if="modelValue" class="modal-overlay" @click.self="handleClose">
    <div class="modal-container" @click.stop>
      <div class="modal-header">
        <h2 class="modal-title">
          <i class="fas fa-percentage"></i>
          {{ t("clientDetail_assignRebateRule", "Assign Rebate Rule") }}
        </h2>
        <button
          type="button"
          class="modal-close"
          :disabled="submitting"
          @click="handleClose"
        >
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="modal-body">
        <div class="meta-box">
          <div>
            <span>{{ t("clientDetail_thLogin", "Login") }}</span
            ><strong>{{ login || "-" }}</strong>
          </div>
          <div>
            <span>{{ t("clientDetail_thPlatform", "Platform") }}</span
            ><strong>{{ platformName || "-" }}</strong>
          </div>
        </div>

        <div v-if="loading" class="state-box">
          <i class="fas fa-spinner fa-spin"></i>
          {{ t("common_loading", "Loading") }}
        </div>

        <div v-else-if="loadError" class="error-box">
          <i class="fas fa-exclamation-circle"></i>
          <span>{{ loadError }}</span>
        </div>

        <div v-else class="form-field">
          <label>{{ t("clientDetail_rebateRule", "Rebate Rule") }}</label>
          <select v-model="selectedIbPartnerId" :disabled="submitting">
            <option value="">{{ autoPackageLabel }}</option>
            <option
              v-for="pkg in packages"
              :key="pkg.ibPartnerId"
              :value="String(pkg.ibPartnerId)"
            >
              {{ formatPackageLabel(pkg) }}
            </option>
          </select>
        </div>

        <div v-if="formError" class="error-box">
          <i class="fas fa-exclamation-circle"></i>
          <span>{{ formError }}</span>
        </div>
      </div>

      <div class="modal-footer">
        <button
          type="button"
          class="btn-secondary"
          :disabled="submitting"
          @click="handleClose"
        >
          {{ t("common_cancel", "Cancel") }}
        </button>
        <button
          type="button"
          class="btn-primary"
          :disabled="submitting || loading"
          @click="handleSave"
        >
          <i v-if="submitting" class="fas fa-spinner fa-spin"></i>
          {{ t("common_save", "Save") }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import { clientService } from "@/services/clientListService";
import { useAdminI18n } from "@/composables/useAdminI18n";

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  clientId: { type: [Number, String], required: true },
  tradingAccountId: { type: [Number, String], required: true },
  login: { type: String, default: "" },
  platformName: { type: String, default: "" },
  currentRuleId: { type: [Number, String, null], default: null },
  currentIbPartnerId: { type: [Number, String, null], default: null },
});

const emit = defineEmits(["update:modelValue", "saved"]);
const { t, tParams } = useAdminI18n();

const loading = ref(false);
const submitting = ref(false);
const loadError = ref("");
const formError = ref("");
const packages = ref([]);
const selectedIbPartnerId = ref("");

const formatPackageLabel = (pkg) => {
  return String(pkg?.ibCode || "").trim() || `#${pkg?.ibPartnerId}`;
};

const autoPackageLabel = computed(() => {
  const code = String(packages.value[0]?.ibCode || "").trim();
  if (!code) {
    return t("clientDetail_rebateRuleNone", "Auto (no assignment)");
  }
  return tParams("clientDetail_rebateRuleAutoPackage", "Auto ({ibCode})", {
    ibCode: code,
  });
});

const loadRules = async () => {
  loading.value = true;
  loadError.value = "";
  try {
    const response = await clientService.getAssignableCommissionRules(
      props.clientId,
    );
    const items = response?.data?.items ?? response?.items ?? [];
    packages.value = Array.isArray(items) ? items : [];
    const fromIb =
      props.currentIbPartnerId != null && props.currentIbPartnerId !== ""
        ? String(props.currentIbPartnerId)
        : "";
    const fromRule =
      props.currentRuleId != null && props.currentRuleId !== ""
        ? packages.value.find(
            (pkg) => String(pkg.ruleId) === String(props.currentRuleId),
          )
        : null;
    selectedIbPartnerId.value =
      fromIb || (fromRule ? String(fromRule.ibPartnerId) : "");
  } catch (error) {
    loadError.value =
      error?.response?.data?.message ||
      error?.message ||
      t("common_loadFailed", "Failed to load");
    packages.value = [];
  } finally {
    loading.value = false;
  }
};

const handleClose = () => {
  if (submitting.value) return;
  emit("update:modelValue", false);
};

const handleSave = async () => {
  if (submitting.value || loading.value) return;
  submitting.value = true;
  formError.value = "";
  try {
    const pkg = packages.value.find(
      (item) => String(item.ibPartnerId) === String(selectedIbPartnerId.value),
    );
    const value = pkg?.ruleId ? Number(pkg.ruleId) : null;
    await clientService.updateAssignedCommissionRule(
      props.clientId,
      props.tradingAccountId,
      value,
    );
    emit("saved");
    emit("update:modelValue", false);
  } catch (error) {
    formError.value =
      error?.response?.data?.message ||
      error?.message ||
      t("common_saveFailed", "Failed to save");
  } finally {
    submitting.value = false;
  }
};

watch(
  () => props.modelValue,
  (visible) => {
    if (!visible) return;
    formError.value = "";
    loadRules();
  },
  { immediate: true },
);
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1200;
}
.modal-container {
  width: min(520px, calc(100vw - 32px));
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 18px 48px rgba(15, 23, 42, 0.18);
  overflow: hidden;
}
.modal-header,
.modal-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 16px 20px;
  border-bottom: 1px solid #e5e7eb;
}
.modal-footer {
  border-bottom: none;
  border-top: 1px solid #e5e7eb;
  justify-content: flex-end;
}
.modal-title {
  margin: 0;
  font-size: 18px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.modal-close {
  border: none;
  background: transparent;
  cursor: pointer;
  color: var(--color-muted);
}
.modal-body {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.meta-box {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-md);
  padding: 12px;
}
.meta-box span {
  display: block;
  color: var(--color-muted);
  font-size: 12px;
}
.meta-box strong {
  font-size: 14px;
}
.form-field label {
  display: block;
  margin-bottom: 6px;
  font-weight: 600;
}
.form-field select {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #cbd5e1;
  border-radius: var(--radius-md);
}
.state-box,
.error-box {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  border-radius: var(--radius-md);
}
.state-box {
  background: var(--color-surface-soft);
  color: var(--color-text);
}
.error-box {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}
.btn-secondary,
.btn-primary {
  border: none;
  border-radius: var(--radius-md);
  padding: 10px 14px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.btn-secondary {
  background: var(--color-border);
  color: var(--color-text);
}
.btn-primary {
  background: #2563eb;
  color: #fff;
}
.btn-primary:disabled,
.btn-secondary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
