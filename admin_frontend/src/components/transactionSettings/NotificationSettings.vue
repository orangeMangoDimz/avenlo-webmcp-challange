<template>
  <div class="notification-settings-container">
    <div class="toggle-switch-wrapper">
      <div class="toggle-label">
        <div class="toggle-label-title">
          {{ t("txnSettings_ns_clientEmailTitle") }}
        </div>
        <div class="toggle-label-description">
          {{ t("txnSettings_ns_clientEmailDesc") }}
        </div>
      </div>
      <div
        :class="[
          'toggle-switch',
          { active: settings.clientEmailNotifications, disabled: !canEdit },
        ]"
        @click="canEdit ? toggleSetting('clientEmailNotifications') : null"
      ></div>
    </div>

    <div class="toggle-switch-wrapper">
      <div class="toggle-label">
        <div class="toggle-label-title">
          {{ t("txnSettings_ns_adminEmailTitle") }}
        </div>
        <div class="toggle-label-description">
          {{ t("txnSettings_ns_adminEmailDesc") }}
        </div>
      </div>
      <div
        :class="[
          'toggle-switch',
          { active: settings.adminEmailNotifications, disabled: !canEdit },
        ]"
        @click="canEdit ? toggleSetting('adminEmailNotifications') : null"
      ></div>
    </div>

    <div class="form-group">
      <label>
        {{ t("txnSettings_ns_adminEmailsLabel") }}
        <span class="label-description">{{
          t("txnSettings_ns_adminEmailsHelp")
        }}</span>
      </label>
      <input
        type="text"
        v-model="settings.adminNotificationEmails"
        :placeholder="t('txnSettings_ns_ph_adminEmails')"
        :disabled="!canEdit"
        @input="handleChange"
      />
    </div>

    <div class="toggle-switch-wrapper">
      <div class="toggle-label">
        <div class="toggle-label-title">
          {{ t("txnSettings_ns_largeDepositTitle") }}
        </div>
        <div class="toggle-label-description">
          {{ t("txnSettings_ns_largeDepositDesc") }}
        </div>
      </div>
      <div
        :class="[
          'toggle-switch',
          { active: settings.largeDepositAlerts, disabled: !canEdit },
        ]"
        @click="canEdit ? toggleSetting('largeDepositAlerts') : null"
      ></div>
    </div>

    <div class="form-group">
      <label>
        {{ t("txnSettings_ns_largeDepositThresholdLabel") }}
        <span class="label-description">{{
          t("txnSettings_ns_largeDepositThresholdHelp")
        }}</span>
      </label>
      <input
        type="number"
        v-model.number="settings.largeDepositThreshold"
        step="100"
        :placeholder="t('txnSettings_ns_ph_largeAmount')"
        :disabled="!canEdit"
        @input="handleChange"
      />
    </div>

    <div class="toggle-switch-wrapper">
      <div class="toggle-label">
        <div class="toggle-label-title">
          {{ t("txnSettings_ns_largeWithdrawalTitle") }}
        </div>
        <div class="toggle-label-description">
          {{ t("txnSettings_ns_largeWithdrawalDesc") }}
        </div>
      </div>
      <div
        :class="[
          'toggle-switch',
          { active: settings.largeWithdrawalAlerts, disabled: !canEdit },
        ]"
        @click="canEdit ? toggleSetting('largeWithdrawalAlerts') : null"
      ></div>
    </div>

    <div class="form-group">
      <label>
        {{ t("txnSettings_ns_largeWithdrawalThresholdLabel") }}
        <span class="label-description">{{
          t("txnSettings_ns_largeWithdrawalThresholdHelp")
        }}</span>
      </label>
      <input
        type="number"
        v-model.number="settings.largeWithdrawalThreshold"
        step="100"
        :placeholder="t('txnSettings_ns_ph_largeAmount')"
        :disabled="!canEdit"
        @input="handleChange"
      />
    </div>

    <div class="info-box success">
      <p>
        <strong
          ><i class="fas fa-check-circle"></i>
          {{ t("txnSettings_ns_bestPracticeStrong") }}</strong
        >
        {{ t("txnSettings_ns_bestPracticeBody") }}
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from "vue";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t } = useAdminI18n();

const props = defineProps({
  notificationData: {
    type: Object,
    default: () => ({}),
  },
  canEdit: {
    type: Boolean,
    default: true,
  },
});

const emit = defineEmits(["update", "change"]);

// 通知设置
const settings = ref({
  clientEmailNotifications: true,
  adminEmailNotifications: true,
  adminNotificationEmails: "admin@bdx.com, finance@bdx.com",
  largeDepositAlerts: true,
  largeDepositThreshold: 10000,
  largeWithdrawalAlerts: true,
  largeWithdrawalThreshold: 10000,
});

// 从props初始化设置
const initializeSettings = () => {
  const data = props.notificationData;

  if (data.client) {
    data.client.forEach((setting) => {
      if (setting.settingKey === "clientEmailNotifications") {
        settings.value.clientEmailNotifications =
          setting.settingValue === "1" || setting.settingValue === true;
      }
    });
  }

  if (data.admin) {
    data.admin.forEach((setting) => {
      if (setting.settingKey === "adminEmailNotifications") {
        settings.value.adminEmailNotifications =
          setting.settingValue === "1" || setting.settingValue === true;
      } else if (setting.settingKey === "adminNotificationEmails") {
        settings.value.adminNotificationEmails = setting.settingValue || "";
      }
    });
  }

  if (data.alerts) {
    data.alerts.forEach((setting) => {
      if (setting.settingKey === "largeDepositAlerts") {
        settings.value.largeDepositAlerts =
          setting.settingValue === "1" || setting.settingValue === true;
      } else if (setting.settingKey === "largeDepositThreshold") {
        settings.value.largeDepositThreshold =
          parseFloat(setting.settingValue) || 10000;
      } else if (setting.settingKey === "largeWithdrawalAlerts") {
        settings.value.largeWithdrawalAlerts =
          setting.settingValue === "1" || setting.settingValue === true;
      } else if (setting.settingKey === "largeWithdrawalThreshold") {
        settings.value.largeWithdrawalThreshold =
          parseFloat(setting.settingValue) || 10000;
      }
    });
  }
};

initializeSettings();

// 切换设置
const toggleSetting = (key) => {
  if (!props.canEdit) return;
  settings.value[key] = !settings.value[key];
  handleChange();
};

// 处理变更
const handleChange = () => {
  emit("change", settings.value);
};

// 监听prop变化
watch(
  () => props.notificationData,
  () => {
    initializeSettings();
  },
  { deep: true },
);
</script>

<style scoped>
.notification-settings-container {
  padding: 0;
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
  font-size: 14px;
  margin-top: 5px;
}

.form-group input {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  transition: all 0.3s ease;
  background: var(--color-surface);
}

.form-group input:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.toggle-switch-wrapper {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 15px 20px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-md);
  margin-bottom: 20px;
}

.toggle-label {
  display: flex;
  flex-direction: column;
}

.toggle-label-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 3px;
}

.toggle-label-description {
  font-size: 14px;
  color: var(--color-muted);
}

.toggle-switch {
  position: relative;
  width: 50px;
  height: 26px;
  background: var(--color-border-strong);
  border-radius: 13px;
  cursor: pointer;
  transition: all 0.3s ease;
  flex-shrink: 0;
}

.toggle-switch.active {
  background: var(--color-success-solid);
}

.toggle-switch::after {
  content: "";
  position: absolute;
  top: 3px;
  left: 3px;
  width: 20px;
  height: 20px;
  background: var(--color-surface);
  border-radius: 50%;
  transition: all 0.3s ease;
}

.toggle-switch.active::after {
  left: 27px;
}

.toggle-switch.disabled {
  cursor: not-allowed;
  opacity: 0.7;
  background: var(--color-border) !important;
}

.toggle-switch.disabled.active {
  background: var(--color-success-soft) !important;
  opacity: 0.8;
}

.toggle-switch.disabled::after {
  background: var(--color-border-strong);
}

.toggle-switch.disabled.active::after {
  background: var(--color-surface);
}

.toggle-switch.disabled:hover {
  cursor: not-allowed;
}

.info-box {
  background: var(--color-success-soft);
  border-left: 4px solid var(--color-success);
  padding: 15px 20px;
  border-radius: var(--radius-md);
  margin-top: 20px;
  margin-bottom: 20px;
}

.info-box p {
  color: var(--color-text);
  font-size: 14px;
  line-height: 1.6;
  margin: 0;
}

@media (max-width: 768px) {
  .toggle-switch-wrapper {
    flex-direction: column;
    align-items: flex-start;
    gap: 15px;
  }
}
</style>
