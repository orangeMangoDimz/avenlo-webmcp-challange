<template>
  <div v-if="modelValue" class="modal-overlay show">
    <div class="modal" @click.stop>
      <div class="modal-header">
        <h2 class="modal-title">
          <i class="fas fa-pen"></i>
          {{ t("platTgModal_title") }}
        </h2>
        <button class="modal-close" @click="emit('close')">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="modal-body">
        <div class="summary-box">
          <div>
            <strong>{{ t("platTgModal_platform") }}</strong> {{ platformName }}
          </div>
          <div>
            <strong>{{ t("platTgModal_group") }}</strong> {{ groupName }}
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">{{ t("platTgModal_label_label") }}</label>
          <input
            v-model="labelValue"
            type="text"
            class="form-input"
            :placeholder="t('platTgModal_ph_label')"
          />
        </div>

        <div class="form-group">
          <label class="form-label">{{ t("platTgModal_label_unit") }}</label>
          <input
            v-model="unitValue"
            type="text"
            class="form-input"
            :placeholder="t('platTgModal_ph_unit')"
          />
        </div>

        <div class="form-group">
          <label class="form-label">{{ t("platTgModal_label_scale") }}</label>
          <input
            v-model="scaleValue"
            type="number"
            min="0.00000001"
            step="0.00000001"
            class="form-input"
            :placeholder="t('platTgModal_ph_scale')"
          />
        </div>

        <div v-if="displayErrorMessage" class="error-box">
          {{ displayErrorMessage }}
        </div>
      </div>

      <div class="modal-footer">
        <button
          type="button"
          class="btn btn-secondary"
          @click="emit('close')"
          :disabled="saving"
        >
          {{ t("platTgModal_btn_cancel") }}
        </button>
        <button
          type="button"
          class="btn btn-primary"
          @click="submit"
          :disabled="saving"
        >
          <i class="fas fa-save"></i>
          {{ saving ? t("platTgModal_saving") : t("platTgModal_btn_save") }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t } = useAdminI18n();

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  initialLabel: { type: String, default: "" },
  initialUnit: { type: String, default: "" },
  initialScale: { type: [Number, String], default: 1 },
  groupName: { type: String, default: "" },
  platformName: { type: String, default: "" },
  saving: { type: Boolean, default: false },
  errorMessage: { type: String, default: "" },
});

const emit = defineEmits(["close", "save"]);
const labelValue = ref("");
const unitValue = ref("");
const scaleValue = ref("1");
const localErrorMessage = ref("");

const displayErrorMessage = computed(
  () => localErrorMessage.value || props.errorMessage,
);

watch(
  () => [
    props.modelValue,
    props.initialLabel,
    props.initialUnit,
    props.initialScale,
  ],
  () => {
    if (props.modelValue) {
      labelValue.value = props.initialLabel || "";
      unitValue.value = props.initialUnit || "";
      scaleValue.value = String(props.initialScale ?? 1);
      localErrorMessage.value = "";
    }
  },
  { immediate: true },
);

const submit = () => {
  const parsedScale = Number(scaleValue.value);

  if (!Number.isFinite(parsedScale) || parsedScale <= 0) {
    localErrorMessage.value = t("platTgModal_err_scale");
    return;
  }

  localErrorMessage.value = "";
  emit("save", {
    label: labelValue.value.trim(),
    unit: unitValue.value.trim() || null,
    scale: parsedScale,
  });
};
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2100;
  padding: 20px;
}

.modal {
  width: min(100%, 560px);
  background: var(--color-surface);
  border-radius: var(--radius-xl);
  box-shadow: 0 20px 60px rgba(15, 23, 42, 0.22);
}

.modal-header,
.modal-footer {
  padding: 22px 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.modal-header {
  border-bottom: 1px solid var(--color-border);
}

.modal-footer {
  justify-content: flex-end;
  gap: 12px;
}

.modal-body {
  padding: 24px;
}

.modal-title {
  margin: 0;
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 20px;
  color: var(--color-ink);
}

.modal-title i {
  color: var(--color-brand);
}

.modal-close {
  width: 36px;
  height: 36px;
  border: none;
  border-radius: 999px;
  background: var(--color-surface-muted);
  color: var(--color-text);
  cursor: pointer;
}

.summary-box {
  padding: 12px 14px;
  border-radius: var(--radius-md);
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  color: var(--color-text);
  font-size: 14px;
  margin-bottom: 18px;
}

.summary-box div + div {
  margin-top: 6px;
}

.form-group {
  margin-bottom: 18px;
}

.form-label {
  display: block;
  margin-bottom: 8px;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
}

.form-input {
  width: 100%;
  padding: 12px 14px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
}

.form-input:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.12);
}

.error-box {
  padding: 12px 14px;
  border-radius: var(--radius-md);
  background: var(--color-danger-soft);
  border: 1px solid var(--color-danger-border);
  color: var(--color-danger);
  font-size: 14px;
}

.btn {
  border: none;
  border-radius: var(--radius-md);
  padding: 11px 16px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}

.btn-primary {
  background: var(--color-brand-solid);
  color: white;
}

.btn-secondary {
  background: var(--color-surface-muted);
  color: var(--color-ink);
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
