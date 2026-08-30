<template>
  <div class="developer-settings-page">
    <div class="page-header">
      <div class="page-title">
        <h1>
          <i class="fas fa-code"></i>
          {{ t("page_developerSettings_title", "Kill Switches") }}
        </h1>
        <p>
          {{
            t(
              "page_developerSettings_sub",
              "Turn off inbound MT sync and real email on this environment.",
            )
          }}
        </p>
      </div>
      <div class="page-actions">
        <PageHeaderActions />
      </div>
    </div>

    <div v-if="loading" class="loading-container">
      <i class="fas fa-spinner fa-spin"></i>
      <p>{{ t("developerSettings_loading", "Loading settings...") }}</p>
    </div>

    <div v-else-if="error" class="error-container">
      <i class="fas fa-exclamation-circle"></i>
      <p>{{ error }}</p>
      <button class="btn btn-primary" type="button" @click="loadSettings">
        <i class="fas fa-redo"></i> {{ t("developerSettings_retry", "Retry") }}
      </button>
    </div>

    <div v-else class="settings-content">
      <div class="info-banner">
        <i class="fas fa-info-circle"></i>
        <div class="info-text">
          <strong>{{
            tParams(
              "developerSettings_infoEnvTitle",
              "This is the {env} environment only.",
              { env: environmentLabel },
            )
          }}</strong>
          {{
            t(
              "developerSettings_infoBody",
              "These switches do not run in production. Off skips the job; trading still works.",
            )
          }}
        </div>
      </div>

      <div class="settings-card">
        <div class="card-header">
          <div class="card-header-content">
            <h2>
              <i class="fas fa-power-off"></i>
              {{ t("developerSettings_cardTitle", "Background processes") }}
            </h2>
            <p>
              {{
                t(
                  "developerSettings_cardDesc",
                  "Each switch saves immediately.",
                )
              }}
            </p>
          </div>
        </div>
        <div class="card-body">
          <div v-for="item in toggles" :key="item.key" class="toggle-option">
            <div class="toggle-option-info">
              <h3>{{ t(item.labelKey, item.labelFallback) }}</h3>
              <p>{{ t(item.hintKey, item.hintFallback) }}</p>
            </div>
            <label
              class="toggle-switch"
              :class="{ disabled: savingKey === item.key || !!confirmModal }"
            >
              <input
                type="checkbox"
                :checked="settings[item.key]"
                :disabled="savingKey !== '' || !!confirmModal"
                @change="requestToggle(item, $event)"
              />
              <span class="toggle-slider"></span>
            </label>
          </div>
        </div>
      </div>
    </div>

    <div v-if="confirmModal" class="modal-overlay" @click.self="cancelToggle">
      <div class="modal" @click.stop>
        <div class="modal-header">
          <h2 class="modal-title">
            <i class="fas fa-exclamation-circle"></i>
            {{ confirmTitle }}
          </h2>
          <button
            type="button"
            class="modal-close"
            :disabled="savingKey !== ''"
            @click="cancelToggle"
          >
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <div class="modal-env-note">
            <i class="fas fa-info-circle"></i>
            <p>{{ confirmIntro }}</p>
          </div>
          <h3 class="effect-heading">
            {{ t("developerSettings_confirmEffects", "What will change") }}
          </h3>
          <div class="effect-rows">
            <div
              v-for="(effect, index) in confirmEffects"
              :key="index"
              class="effect-row"
              :class="{
                'is-on': confirmModal.next,
                'is-off': !confirmModal.next,
              }"
            >
              <span class="effect-icon">
                <i
                  :class="confirmModal.next ? 'fas fa-check' : 'fas fa-minus'"
                ></i>
              </span>
              <span class="effect-text">{{ t(effect[0], effect[1]) }}</span>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button
            type="button"
            class="btn btn-secondary"
            :disabled="savingKey !== ''"
            @click="cancelToggle"
          >
            {{ t("common_cancel", "Cancel") }}
          </button>
          <button
            type="button"
            class="btn btn-primary"
            :disabled="savingKey !== ''"
            @click="applyPendingToggle"
          >
            {{ confirmActionLabel }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";
import { useAdminI18n } from "@/composables/useAdminI18n";
import api from "@/services/api";

const { t, tParams } = useAdminI18n();
const router = useRouter();

const loading = ref(true);
const error = ref("");
const savingKey = ref("");
const confirmModal = ref(null);
const settings = ref({
  environment: "",
  mt4Sync: false,
  mt5Sync: false,
  emailSending: false,
});

const environmentLabel = computed(() => {
  const env = String(settings.value.environment || "").trim();
  return env || "—";
});

const toggles = [
  {
    key: "mt4Sync",
    labelKey: "developer_settings_mt4",
    labelFallback: "MT4",
    hintKey: "developer_settings_mt4_hint",
    hintFallback: "Stop inbound MT4 order and balance sync.",
    onEffects: [
      [
        "developerSettings_mt4_on_1",
        "Inbound MT4 order, balance, and history sync will run.",
      ],
      [
        "developerSettings_mt4_on_2",
        "New MT4 data will be written to this environment's database.",
      ],
      ["developerSettings_mt4_on_3", "Takes effect on the next sync tick."],
    ],
    offEffects: [
      [
        "developerSettings_mt4_off_1",
        "Inbound MT4 order, balance, and history sync will stop.",
      ],
      [
        "developerSettings_mt4_off_2",
        "No MT4 gateway pull and no those database writes.",
      ],
      [
        "developerSettings_mt4_off_3",
        "Open account, deposit, and withdraw still work.",
      ],
    ],
  },
  {
    key: "mt5Sync",
    labelKey: "developer_settings_mt5",
    labelFallback: "MT5",
    hintKey: "developer_settings_mt5_hint",
    hintFallback: "Stop inbound MT5 order, balance, and exchange-rate sync.",
    onEffects: [
      [
        "developerSettings_mt5_on_1",
        "Inbound MT5 order, balance, and history sync will run.",
      ],
      ["developerSettings_mt5_on_2", "Exchange-rate pull will run."],
      [
        "developerSettings_mt5_on_3",
        "New MT5 data will be written to this environment's database.",
      ],
      ["developerSettings_mt5_on_4", "Takes effect on the next sync tick."],
    ],
    offEffects: [
      [
        "developerSettings_mt5_off_1",
        "Inbound MT5 order, balance, history, and exchange-rate sync will stop.",
      ],
      [
        "developerSettings_mt5_off_2",
        "No MT5 gateway pull and no those database writes.",
      ],
      [
        "developerSettings_mt5_off_3",
        "Open account, deposit, and withdraw still work.",
      ],
    ],
  },
  {
    key: "emailSending",
    labelKey: "developer_settings_email",
    labelFallback: "Email sending",
    hintKey: "developer_settings_email_hint",
    hintFallback: "Stop sending real emails. Flows still succeed.",
    onEffects: [
      [
        "developerSettings_email_on_1",
        "Real emails will be sent from this environment.",
      ],
      ["developerSettings_email_on_2", "Recipients will receive live mail."],
    ],
    offEffects: [
      ["developerSettings_email_off_1", "Real emails will not be sent."],
      ["developerSettings_email_off_2", "User flows still succeed."],
      [
        "developerSettings_email_off_3",
        "A skip row is written to email sent logs.",
      ],
    ],
  },
];

const confirmTitle = computed(() => {
  if (!confirmModal.value) {
    return "";
  }
  const name = t(confirmModal.value.labelKey, confirmModal.value.labelFallback);
  if (confirmModal.value.next) {
    return tParams("developerSettings_confirmOnTitle", "Turn on {name}?", {
      name,
    });
  }
  return tParams("developerSettings_confirmOffTitle", "Turn off {name}?", {
    name,
  });
});

const confirmIntro = computed(() =>
  tParams(
    "developerSettings_confirmIntro",
    "This applies only to the {env} environment. Production is not affected.",
    { env: environmentLabel.value },
  ),
);

const confirmEffects = computed(() => {
  if (!confirmModal.value) {
    return [];
  }
  return confirmModal.value.next
    ? confirmModal.value.onEffects
    : confirmModal.value.offEffects;
});

const confirmActionLabel = computed(() => {
  if (!confirmModal.value) {
    return "";
  }
  if (confirmModal.value.next) {
    return t("developerSettings_confirmOn", "Turn on");
  }
  return t("developerSettings_confirmOff", "Turn off");
});

const isNonProductionEnv = (env) => env === "dev" || env === "staging";

const applyPayload = (payload) => {
  settings.value = {
    environment: payload.environment || "",
    mt4Sync: !!payload.mt4Sync,
    mt5Sync: !!payload.mt5Sync,
    emailSending: !!payload.emailSending,
  };
};

const loadSettings = async () => {
  loading.value = true;
  error.value = "";
  try {
    const res = await api.get("/developer-settings");
    const payload = res.data || {};
    if (!isNonProductionEnv(payload.environment)) {
      router.replace("/clients-list");
      return;
    }
    applyPayload(payload);
  } catch (err) {
    if (err?.statusCode === 404) {
      router.replace("/clients-list");
      return;
    }
    error.value =
      err?.message || t("common_errorUnknown", "Failed to load settings");
  } finally {
    loading.value = false;
  }
};

const requestToggle = (item, event) => {
  const next = event.target.checked;
  event.target.checked = !!settings.value[item.key];
  if (savingKey.value || confirmModal.value) {
    return;
  }
  confirmModal.value = {
    key: item.key,
    next,
    labelKey: item.labelKey,
    labelFallback: item.labelFallback,
    onEffects: item.onEffects,
    offEffects: item.offEffects,
  };
};

const cancelToggle = () => {
  if (savingKey.value) {
    return;
  }
  confirmModal.value = null;
};

const setFlag = async (key, value) => {
  const previous = { ...settings.value };
  settings.value = { ...settings.value, [key]: value };
  savingKey.value = key;
  try {
    const res = await api.put("/developer-settings", { [key]: value });
    applyPayload(res.data || previous);
  } catch {
    settings.value = previous;
  } finally {
    savingKey.value = "";
  }
};

const applyPendingToggle = async () => {
  const pending = confirmModal.value;
  if (!pending || savingKey.value) {
    return;
  }
  await setFlag(pending.key, pending.next);
  confirmModal.value = null;
};

onMounted(() => {
  loadSettings();
});
</script>

<style scoped>
.developer-settings-page {
  max-width: 1400px;
  margin: 0 auto;
  padding: 30px 20px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 2px solid var(--color-border);
}

.page-title h1 {
  font-size: 28px;
  color: var(--color-ink);
  margin-bottom: 5px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.page-title h1 i {
  color: var(--color-brand);
}

.page-title p {
  font-size: 14px;
  color: var(--color-muted);
  margin: 0;
}

.page-actions {
  display: flex;
  gap: 15px;
  align-items: center;
}

.loading-container,
.error-container {
  text-align: center;
  padding: 60px 20px;
  color: var(--color-muted);
}

.loading-container i,
.error-container i {
  font-size: 48px;
  color: var(--color-brand);
  margin-bottom: 15px;
  display: block;
}

.error-container i {
  color: var(--color-danger);
}

.info-banner {
  display: flex;
  gap: 16px;
  padding: 18px 22px;
  background: var(--color-brand-soft);
  border: 1px solid #c3dafe;
  border-radius: var(--radius-lg);
  margin-bottom: 24px;
}

.info-banner i {
  font-size: 24px;
  color: var(--color-brand);
  flex-shrink: 0;
  margin-top: 2px;
}

.info-text {
  flex: 1;
  color: var(--color-text);
  line-height: 1.6;
}

.info-text strong {
  color: var(--color-ink);
}

.settings-card {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

.card-header {
  background: var(--color-surface-soft);
  padding: 20px 30px;
  border-bottom: 2px solid var(--color-border);
}

.card-header-content h2 {
  font-size: 20px;
  color: var(--color-ink);
  margin: 0 0 5px 0;
}

.card-header-content h2 i {
  margin-right: 10px;
  color: var(--color-brand);
}

.card-header-content p {
  font-size: 14px;
  color: var(--color-muted);
  margin: 0;
}

.card-body {
  padding: 30px;
}

.toggle-option {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-md);
  margin-bottom: 15px;
}

.toggle-option:last-child {
  margin-bottom: 0;
}

.toggle-option-info h3 {
  font-size: 16px;
  color: var(--color-ink);
  margin: 0 0 5px 0;
}

.toggle-option-info p {
  font-size: 14px;
  color: var(--color-muted);
  line-height: 1.5;
  margin: 0;
}

.toggle-switch {
  position: relative;
  display: inline-block;
  width: 60px;
  height: 32px;
  flex-shrink: 0;
}

.toggle-switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.toggle-slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: var(--color-border-strong);
  transition: 0.4s;
  border-radius: 32px;
}

.toggle-slider:before {
  position: absolute;
  content: "";
  height: 24px;
  width: 24px;
  left: 4px;
  bottom: 4px;
  background-color: var(--color-surface);
  transition: 0.4s;
  border-radius: 50%;
}

.toggle-switch input:checked + .toggle-slider {
  background: var(--color-brand-solid);
}

.toggle-switch input:checked + .toggle-slider:before {
  transform: translateX(28px);
}

.toggle-switch.disabled {
  opacity: 0.7;
  pointer-events: none;
}

.btn {
  padding: 14px 32px;
  border-radius: var(--radius-md);
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;
}

.btn-primary {
  background: var(--color-brand-solid);
  color: white;
  box-shadow: 0 4px 15px rgba(var(--color-brand-rgb), 0.4);
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(var(--color-brand-rgb), 0.5);
}

.btn-secondary {
  background: var(--color-surface-muted);
  color: var(--color-ink);
}

.btn-secondary:hover:not(:disabled) {
  background: var(--color-border);
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

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

.modal-close:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.modal-body {
  padding: 24px;
}

.modal-env-note {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 14px 16px;
  background: var(--color-brand-soft);
  border-left: 4px solid var(--color-brand);
  border-radius: var(--radius-md);
  margin-bottom: 20px;
}

.modal-env-note i {
  color: var(--color-brand);
  font-size: 18px;
  flex-shrink: 0;
  margin-top: 2px;
}

.modal-env-note p {
  margin: 0;
  font-size: 14px;
  color: var(--color-text);
  line-height: 1.6;
}

.effect-heading {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
  margin: 0 0 12px;
}

.effect-rows {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.effect-row {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 14px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-md);
}

.effect-icon {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-size: 14px;
}

.effect-row.is-on .effect-icon {
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.effect-row.is-off .effect-icon {
  background: var(--color-surface-muted);
  color: var(--color-muted);
}

.effect-text {
  font-size: 14px;
  color: var(--color-ink);
  line-height: 1.5;
  padding-top: 4px;
}

.modal-footer {
  padding: 20px 24px 24px;
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}
</style>
