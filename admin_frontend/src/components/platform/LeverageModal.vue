<template>
  <div v-if="modelValue" class="modal-overlay show" @click.self="emit('close')">
    <div class="modal" @click.stop>
      <div class="modal-header">
        <h2 class="modal-title">
          <i class="fas fa-sliders-h"></i>
          {{ leverage ? t("platModal_title_edit") : t("platModal_title_add") }}
        </h2>
        <button class="modal-close" @click="emit('close')">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="modal-body">
        <form @submit.prevent="handleSubmit">
          <div class="form-group">
            <label class="form-label">{{
              t("platModal_label_platform")
            }}</label>
            <div class="platform-display">
              <i class="fas fa-server"></i>
              <span>{{ platformName || form.platformKey }}</span>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">{{ t("platModal_label_value") }}</label>
            <input
              v-model="form.leverageValue"
              type="text"
              class="form-input"
              :placeholder="t('platModal_ph_value')"
              :readonly="lockLeverageValue"
              required
            />
            <small class="form-hint">{{ t("platModal_hint_value") }}</small>
          </div>

          <div class="form-group">
            <label class="form-label">{{ t("platModal_label_display") }}</label>
            <input
              v-model="form.displayLabel"
              type="text"
              class="form-input"
              :placeholder="t('platModal_ph_display')"
              required
            />
          </div>

          <div class="form-group">
            <label class="form-label">{{ t("platModal_label_risk") }}</label>
            <textarea
              v-model="form.riskNote"
              class="form-textarea"
              rows="3"
              :placeholder="t('platModal_ph_risk')"
            />
          </div>

          <div class="form-group">
            <label class="form-label">{{ t("platModal_label_order") }}</label>
            <input
              v-model.number="form.displayOrder"
              type="number"
              min="0"
              class="form-input"
              :placeholder="t('platModal_ph_order')"
            />
          </div>

          <div v-if="errorMessage" class="error-box">
            {{ errorMessage }}
          </div>
        </form>
      </div>

      <div class="modal-footer">
        <button
          type="button"
          class="btn btn-secondary"
          @click="emit('close')"
          :disabled="saving"
        >
          {{ t("platModal_btn_cancel") }}
        </button>
        <button
          type="button"
          class="btn btn-primary"
          @click="handleSubmit"
          :disabled="saving"
        >
          <i class="fas fa-save"></i>
          {{
            saving
              ? t("platModal_saving")
              : leverage
                ? t("platModal_btn_saveEdit")
                : t("platModal_btn_create")
          }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, watch } from "vue";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t } = useAdminI18n();

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  leverage: {
    type: Object,
    default: null,
  },
  defaultPlatformKey: {
    type: String,
    default: "",
  },
  platformName: {
    type: String,
    default: "",
  },
  platforms: {
    type: Array,
    default: () => [],
  },
  saving: {
    type: Boolean,
    default: false,
  },
  errorMessage: {
    type: String,
    default: "",
  },
  lockLeverageValue: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["close", "save"]);

const form = reactive({
  platformKey: "",
  leverageValue: "",
  displayLabel: "",
  riskNote: "",
  displayOrder: 0,
});

const hydrateForm = () => {
  if (props.leverage) {
    form.platformKey = props.leverage.platformKey || "";
    form.leverageValue = props.leverage.leverageValue || "";
    form.displayLabel = props.leverage.displayLabel || "";
    form.riskNote = props.leverage.riskNote || "";
    form.displayOrder = Number(props.leverage.displayOrder ?? 0);
    return;
  }

  form.platformKey = props.defaultPlatformKey || "";
  form.leverageValue = "";
  form.displayLabel = "";
  form.riskNote = "";
  form.displayOrder = 0;
};

watch(
  () => [props.modelValue, props.leverage, props.defaultPlatformKey],
  () => {
    if (props.modelValue) {
      hydrateForm();
    }
  },
  { immediate: true },
);

const handleSubmit = () => {
  if (
    !form.platformKey ||
    !form.leverageValue.trim() ||
    !form.displayLabel.trim()
  ) {
    return;
  }

  emit("save", {
    platformKey: form.platformKey,
    leverageValue: form.leverageValue.trim(),
    displayLabel: form.displayLabel.trim(),
    riskNote: form.riskNote.trim(),
    displayOrder: Number.isFinite(form.displayOrder)
      ? Number(form.displayOrder)
      : 0,
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
  width: min(100%, 620px);
  max-height: 90vh;
  overflow-y: auto;
  background: var(--color-surface);
  border-radius: var(--radius-xl);
  box-shadow: 0 20px 60px rgba(15, 23, 42, 0.22);
}

.modal-header {
  padding: 22px 24px;
  border-bottom: 1px solid var(--color-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
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

.modal-body {
  padding: 24px;
}

.modal-footer {
  padding: 20px 24px 24px;
  display: flex;
  justify-content: flex-end;
  gap: 12px;
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

.form-input,
.form-textarea {
  width: 100%;
  padding: 12px 14px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  transition: all 0.2s ease;
}

.form-input:focus,
.form-textarea:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.12);
}

.form-textarea {
  resize: vertical;
}

.form-hint {
  display: block;
  margin-top: 8px;
  font-size: 12px;
  color: var(--color-muted);
}

.platform-display {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface-soft);
  color: var(--color-ink);
  font-size: 14px;
  font-weight: 600;
}

.platform-display i {
  color: var(--color-brand);
}

.error-box {
  padding: 12px 14px;
  border-radius: var(--radius-md);
  background: var(--color-danger-soft);
  border: 1px solid var(--color-danger-border);
  color: var(--color-danger);
  font-size: 13px;
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
