<template>
  <div class="modal-overlay show" @click="onOverlayClick">
    <div class="modal" @click.stop>
      <div class="modal-header">
        <h2 class="modal-title">
          <i :class="headerIcon"></i>
          {{ title }}
        </h2>
        <button
          class="modal-close"
          @click="emit('close')"
          :disabled="submitting"
        >
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="modal-body">
        <div class="account-line">
          <span class="account-chip">{{ platformName || "-" }}</span>
          <span class="account-login"
            >{{ t("tamanage_login", "Login") }}: {{ login || "-" }}</span
          >
        </div>

        <!-- 重置密码 -->
        <template v-if="mode === 'reset-password'">
          <p class="manage-hint">
            <i class="fas fa-info-circle"></i>
            {{
              t(
                "tamanage_reset_hint",
                "A new password will be generated and emailed to the client. The password is never shown to the admin.",
              )
            }}
          </p>
        </template>

        <!-- 改 group -->
        <template v-else-if="mode === 'group'">
          <div class="form-group">
            <label class="form-label">{{ t("tamanage_group", "Group") }}</label>
            <p class="manage-current">
              {{ t("tamanage_current", "Current") }}:
              {{ currentGroupLabel || "-" }}
            </p>
            <select
              class="form-input"
              v-model="groupValue"
              :disabled="submitting || optionsLoading"
            >
              <option :value="null" disabled>
                {{
                  optionsLoading
                    ? t("tamanage_loading", "Loading...")
                    : t("tamanage_selectGroup", "Select group")
                }}
              </option>
              <option
                v-for="g in groups"
                :key="g.trading_id"
                :value="g.trading_id"
              >
                {{ g.label || g.name }}
              </option>
            </select>
          </div>
        </template>

        <!-- 改 leverage -->
        <template v-else-if="mode === 'leverage'">
          <div class="form-group">
            <label class="form-label">{{
              t("tamanage_leverage", "Leverage")
            }}</label>
            <p class="manage-current">
              {{ t("tamanage_current", "Current") }}:
              {{ currentLeverageLabel || "-" }}
            </p>
            <select
              class="form-input"
              v-model="leverageValue"
              :disabled="submitting || optionsLoading"
            >
              <option value="" disabled>
                {{
                  optionsLoading
                    ? t("tamanage_loading", "Loading...")
                    : t("tamanage_selectLeverage", "Select leverage")
                }}
              </option>
              <option
                v-for="l in leverages"
                :key="l.leverageValue"
                :value="l.leverageValue"
              >
                {{ l.displayLabel || l.leverageValue }}
              </option>
            </select>
          </div>
        </template>

        <div v-if="errorMessage" class="form-error">
          <i class="fas fa-exclamation-circle"></i> {{ errorMessage }}
        </div>
        <div v-if="successMessage" class="form-success">
          <i class="fas fa-check-circle"></i> {{ successMessage }}
        </div>
      </div>

      <div class="modal-footer">
        <button
          type="button"
          class="btn btn-secondary"
          @click="emit('close')"
          :disabled="submitting"
        >
          {{
            done ? t("tamanage_close", "Close") : t("tamanage_cancel", "Cancel")
          }}
        </button>
        <button
          v-if="!done"
          type="button"
          class="btn btn-primary"
          :disabled="!canSubmit"
          @click="handleSubmit"
        >
          <i v-if="submitting" class="fas fa-spinner fa-spin"></i>
          {{ submitLabel }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import {
  resetTradingPassword,
  changeTradingGroup,
  changeTradingLeverage,
  getTradingAccountOptions,
} from "@/services/adminTradingAccountAdjustmentApi";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t } = useAdminI18n();

const props = defineProps({
  tradingAccountId: { type: [Number, String], required: true },
  mode: { type: String, required: true }, // 'reset-password' | 'group' | 'leverage'
  platformName: { type: String, default: "" },
  login: { type: [Number, String], default: "" },
});

const emit = defineEmits(["close", "success"]);

const submitting = ref(false);
const optionsLoading = ref(false);
const errorMessage = ref("");
const successMessage = ref("");
const done = ref(false);

const groups = ref([]);
const leverages = ref([]);
const groupValue = ref(null);
const leverageValue = ref("");
const currentGroupLabel = ref("");
const currentLeverageLabel = ref("");

const title = computed(() => {
  if (props.mode === "reset-password")
    return t("tamanage_title_reset", "Reset Trading Password");
  if (props.mode === "group") return t("tamanage_title_group", "Change Group");
  return t("tamanage_title_leverage", "Change Leverage");
});
const headerIcon = computed(() => {
  if (props.mode === "reset-password") return "fas fa-key";
  if (props.mode === "group") return "fas fa-layer-group";
  return "fas fa-sliders-h";
});
const submitLabel = computed(() =>
  props.mode === "reset-password"
    ? t("tamanage_reset_submit", "Reset & Email")
    : t("tamanage_submit", "Submit"),
);

const canSubmit = computed(() => {
  if (submitting.value) return false;
  if (props.mode === "group") return !!groupValue.value;
  if (props.mode === "leverage") return !!leverageValue.value;
  return true;
});

const loadOptions = async () => {
  optionsLoading.value = true;
  try {
    const res = await getTradingAccountOptions(props.tradingAccountId);
    const data = res?.data?.data ?? res?.data ?? {};
    groups.value = Array.isArray(data.groups) ? data.groups : [];
    leverages.value = Array.isArray(data.leverages) ? data.leverages : [];
    // 回显并预选账户当前的 group / leverage
    currentGroupLabel.value = data.currentGroupLabel || "";
    currentLeverageLabel.value = data.currentLeverageLabel || "";
    if (props.mode === "group" && data.currentGroupTradingId != null) {
      groupValue.value = data.currentGroupTradingId;
    }
    if (props.mode === "leverage" && data.currentLeverageValue) {
      leverageValue.value = data.currentLeverageValue;
    }
  } catch (e) {
    errorMessage.value =
      e?.message ||
      e?.response?.data?.message ||
      t("tamanage_optionsFailed", "Failed to load options");
  } finally {
    optionsLoading.value = false;
  }
};

const handleSubmit = async () => {
  if (!canSubmit.value) return;
  submitting.value = true;
  errorMessage.value = "";
  try {
    if (props.mode === "reset-password") {
      const res = await resetTradingPassword({
        tradingAccountId: Number(props.tradingAccountId),
      });
      const data = res?.data?.data ?? res?.data ?? {};
      successMessage.value = data.emailed
        ? t(
            "tamanage_reset_ok_emailed",
            "Password reset and emailed to the client.",
          )
        : t(
            "tamanage_reset_ok_noemail",
            "Password reset. (No email on file — please notify the client.)",
          );
      done.value = true;
      emit("success");
    } else if (props.mode === "group") {
      await changeTradingGroup({
        tradingAccountId: Number(props.tradingAccountId),
        groupTradingId: Number(groupValue.value),
      });
      // 成功后把「当前」刷成刚选中的新值
      const g = groups.value.find((x) => x.trading_id === groupValue.value);
      if (g) currentGroupLabel.value = g.label || g.name;
      successMessage.value = t(
        "tamanage_group_ok",
        "Group updated successfully.",
      );
      done.value = true;
      emit("success");
    } else {
      await changeTradingLeverage({
        tradingAccountId: Number(props.tradingAccountId),
        leverageValue: leverageValue.value,
      });
      const l = leverages.value.find(
        (x) => x.leverageValue === leverageValue.value,
      );
      if (l) currentLeverageLabel.value = l.displayLabel || l.leverageValue;
      successMessage.value = t(
        "tamanage_leverage_ok",
        "Leverage updated successfully.",
      );
      done.value = true;
      emit("success");
    }
  } catch (e) {
    errorMessage.value =
      e?.message ||
      e?.response?.data?.message ||
      t("tamanage_failed", "Operation failed");
  } finally {
    submitting.value = false;
  }
};

const onOverlayClick = () => {
  if (!submitting.value) emit("close");
};

onMounted(() => {
  if (props.mode === "group" || props.mode === "leverage") {
    loadOptions();
  }
});
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}
.modal {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  width: 440px;
  max-width: 90vw;
  display: flex;
  flex-direction: column;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
}
.modal-header {
  padding: 18px 24px;
  border-bottom: 1px solid var(--color-border);
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.modal-title {
  font-size: 16px;
  font-weight: 600;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
}
.modal-close {
  background: none;
  border: none;
  color: var(--color-muted);
  cursor: pointer;
  font-size: 16px;
}
.modal-close:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}
.modal-body {
  padding: 20px 24px;
}
.modal-footer {
  padding: 16px 24px;
  border-top: 1px solid var(--color-border);
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

.account-line {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 16px;
}
.account-chip {
  background: var(--color-info-soft);
  color: var(--color-brand);
  border-radius: var(--radius-sm);
  padding: 4px 10px;
  font-size: 12px;
  font-weight: 600;
}
.account-login {
  font-size: 13px;
  color: var(--color-muted);
}

.manage-hint {
  background: var(--color-info-soft);
  color: var(--color-info);
  border-radius: var(--radius-sm);
  padding: 12px 14px;
  font-size: 13px;
  margin: 0;
  display: flex;
  gap: 8px;
  align-items: flex-start;
}

.form-group {
  margin-bottom: 8px;
}
.form-label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text);
  margin-bottom: 8px;
}
.manage-current {
  margin: 0 0 8px;
  font-size: 13px;
  color: var(--color-muted);
}
.form-input {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: 14px;
  background: var(--color-surface);
  box-sizing: border-box;
}

.form-error {
  margin-top: 12px;
  background: var(--color-danger-soft);
  color: var(--color-danger);
  border-radius: var(--radius-sm);
  padding: 10px 12px;
  font-size: 13px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.form-success {
  margin-top: 12px;
  background: var(--color-success-soft);
  color: var(--color-success);
  border-radius: var(--radius-sm);
  padding: 10px 12px;
  font-size: 13px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn {
  padding: 9px 18px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.btn:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}
.btn-secondary {
  background: var(--color-surface-muted);
  color: var(--color-ink);
}
.btn-secondary:hover:not(:disabled) {
  background: var(--color-border);
}
.btn-primary {
  background: var(--color-brand-solid);
  color: #fff;
}
.btn-primary:hover:not(:disabled) {
  background: var(--color-brand-strong);
}
</style>
