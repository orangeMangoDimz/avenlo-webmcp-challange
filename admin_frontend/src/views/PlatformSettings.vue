<template>
  <div
    v-if="hasReadonlyPermission"
    class="container ui-page workspace-settings-page"
  >
    <div class="page-header ui-page-header">
      <div class="page-title">
        <h1>
          <i class="fas fa-server"></i> {{ t("page_platformSettings_title") }}
        </h1>
        <p>{{ t("page_platformSettings_sub") }}</p>
      </div>
      <div class="page-actions">
        <PageHeaderActions />
      </div>
    </div>

    <div
      class="settings-card"
      :class="{ collapsed: collapsedCards.tradingGroups }"
    >
      <div class="card-header" @click="toggleCard('tradingGroups')">
        <div class="card-header-content">
          <h2>
            <i class="fas fa-users"></i> {{ t("plat_card_tradingTitle") }}
          </h2>
          <p>{{ t("plat_card_tradingDesc") }}</p>
        </div>
        <i class="fas fa-chevron-down card-collapse-icon"></i>
      </div>
      <div class="card-body">
        <div class="form-group">
          <label>
            {{ t("plat_label_tradingPlatform") }}
            <span class="label-description">{{
              t("plat_label_tradingPlatform_hint")
            }}</span>
          </label>
          <div class="platform-selection-cards">
            <button
              v-for="platform in enabledPlatforms"
              :key="platform.key"
              type="button"
              class="platform-selection-card"
              :class="{ active: selectedPlatform === platform.key }"
              @click="selectPlatform(platform.key)"
              :disabled="!hasEditPermission"
            >
              <div class="platform-selection-icon" :class="platform.key">
                <i :class="platform.icon"></i>
              </div>
              <div class="platform-selection-info">
                <span class="platform-selection-name">{{ platform.name }}</span>
                <span class="platform-selection-code">{{
                  platform.shortCode || platform.key.toUpperCase()
                }}</span>
              </div>
              <div class="platform-selection-check">
                <i
                  class="fas"
                  :class="
                    selectedPlatform === platform.key
                      ? 'fa-check-circle'
                      : 'fa-circle'
                  "
                ></i>
              </div>
            </button>
          </div>
          <p v-if="enabledPlatforms.length === 0" class="no-platform-hint">
            {{ t("plat_noPlatformHint") }}
          </p>
        </div>

        <div v-if="selectedPlatform" class="form-group">
          <label>
            {{ t("plat_label_syncGroups") }}
            <span class="label-description">{{
              tParams("plat_label_syncGroups_hint", "", {
                name: getPlatformName(selectedPlatform),
              })
            }}</span>
          </label>
          <button
            type="button"
            class="btn btn-sync"
            @click="syncPlatformGroups(selectedPlatform)"
            :disabled="syncingGroups || !hasEditPermission"
          >
            <i class="fas fa-sync-alt" :class="{ spinning: syncingGroups }"></i>
            {{
              syncingGroups
                ? t("plat_syncing")
                : tParams("plat_sync_groups", "", {
                    name: getPlatformName(selectedPlatform),
                  })
            }}
          </button>
        </div>

        <div
          v-if="selectedPlatform && tradingGroups.length > 0"
          class="form-group"
        >
          <label>
            {{
              tParams("plat_available_groups_title", "", {
                count: tradingGroups.length,
              })
            }}
            <span class="label-description">{{
              t("plat_label_availableGroups_hint")
            }}</span>
          </label>
          <div class="groups-list">
            <div
              v-for="group in tradingGroups"
              :key="group.id"
              class="group-item"
              :class="{ 'is-default': group.isDefault }"
            >
              <div class="group-info">
                <div class="group-name-row">
                  <span class="group-name">{{ group.name }}</span>
                  <span v-if="group.isDefault" class="default-badge">{{
                    t("plat_badge_default")
                  }}</span>
                </div>
                <div class="group-details">
                  <span
                    v-if="group.label && group.label !== group.name"
                    class="group-detail"
                  >
                    <i class="fas fa-font"></i> {{ t("plat_lbl_label") }}
                    {{ group.label }}
                  </span>
                  <span v-if="group.unit" class="group-detail">
                    <i class="fas fa-text-height"></i> {{ t("plat_lbl_unit") }}
                    {{ group.unit }}
                  </span>
                  <span
                    v-if="Number(group.scale ?? 1) !== 1"
                    class="group-detail"
                  >
                    <i class="fas fa-ruler-combined"></i>
                    {{ t("plat_lbl_scale") }} {{ group.scale }}
                  </span>
                  <span v-if="group.leverageDisplay" class="group-detail">
                    <i class="fas fa-chart-line"></i>
                    {{ t("plat_lbl_leverage") }} {{ group.leverageDisplay }}
                  </span>
                  <span v-if="group.depositByDefault" class="group-detail">
                    <i class="fas fa-dollar-sign"></i>
                    {{ t("plat_lbl_defaultDeposit") }}
                    {{ group.depositByDefault }}
                    {{ group.depositCurrency || "USD" }}
                  </span>
                  <span v-if="group.accountTag" class="group-detail">
                    <i class="fas fa-tag"></i> {{ t("plat_lbl_tag") }}
                    {{ group.accountTag }}
                  </span>
                </div>
              </div>
              <div v-if="hasEditPermission" class="group-actions">
                <button
                  type="button"
                  class="btn-small btn-ghost"
                  @click="openTradingGroupLabelModal(group)"
                  :disabled="savingGroupLabel || savingDefault"
                >
                  <i class="fas fa-pen"></i> {{ t("plat_btn_edit") }}
                </button>
                <button
                  v-if="!group.isDefault"
                  type="button"
                  class="btn-small btn-set-default"
                  @click="setDefaultGroup(group.id, selectedPlatform)"
                  :disabled="savingDefault"
                >
                  <i class="fas fa-star"></i> {{ t("plat_btn_setDefault") }}
                </button>
                <button
                  v-if="group.isDefault"
                  type="button"
                  class="btn-small btn-set-default btn-remove-default"
                  @click="removeDefaultGroup(group.id, selectedPlatform)"
                  :disabled="savingDefault"
                >
                  <i class="fas fa-times-circle"></i>
                  {{ t("plat_btn_removeDefault") }}
                </button>
              </div>
            </div>
          </div>
        </div>

        <div
          v-if="selectedPlatform && tradingGroups.length === 0"
          class="empty-state"
        >
          <i class="fas fa-inbox"></i>
          <p>
            {{
              tParams("plat_empty_groups", "", {
                name: getPlatformName(selectedPlatform),
              })
            }}
          </p>
        </div>
      </div>
    </div>

    <div class="settings-card" :class="{ collapsed: collapsedCards.leverages }">
      <div class="card-header" @click="toggleCard('leverages')">
        <div class="card-header-content">
          <h2>
            <i class="fas fa-sliders-h"></i> {{ t("plat_card_leverageTitle") }}
          </h2>
          <p>{{ t("plat_card_leverageDesc") }}</p>
        </div>
        <i class="fas fa-chevron-down card-collapse-icon"></i>
      </div>
      <div class="card-body">
        <div class="form-group">
          <label>
            {{ t("plat_label_tradingPlatform") }}
            <span class="label-description">{{
              t("plat_label_leveragePlatform_hint")
            }}</span>
          </label>
          <div class="platform-selection-cards">
            <button
              v-for="platform in enabledPlatforms"
              :key="`leverage-${platform.key}`"
              type="button"
              class="platform-selection-card"
              :class="{ active: leverageSelectedPlatform === platform.key }"
              @click="selectLeveragePlatform(platform.key)"
              :disabled="!hasEditPermission"
            >
              <div class="platform-selection-icon" :class="platform.key">
                <i :class="platform.icon"></i>
              </div>
              <div class="platform-selection-info">
                <span class="platform-selection-name">{{ platform.name }}</span>
                <span class="platform-selection-code">{{
                  platform.shortCode || platform.key.toUpperCase()
                }}</span>
              </div>
              <div class="platform-selection-check">
                <i
                  class="fas"
                  :class="
                    leverageSelectedPlatform === platform.key
                      ? 'fa-check-circle'
                      : 'fa-circle'
                  "
                ></i>
              </div>
            </button>
          </div>
        </div>

        <div class="section-toolbar">
          <div>
            <label>
              {{ t("plat_section_leverages") }}
              <span class="label-description">{{
                leverageSelectedPlatform
                  ? tParams("plat_section_leverages_hint", "", {
                      name: getPlatformName(leverageSelectedPlatform),
                    })
                  : t("plat_leverage_hint_fallback")
              }}</span>
            </label>
          </div>
          <button
            v-if="
              hasEditPermission &&
              leverageSelectedPlatform &&
              canManageSelectedLeverages
            "
            type="button"
            class="btn btn-primary"
            @click="openCreateLeverageModal"
          >
            <i class="fas fa-plus"></i> {{ t("plat_btn_addLeverage") }}
          </button>
        </div>

        <div v-if="loadingLeverages" class="empty-state compact">
          <i class="fas fa-spinner spinning"></i>
          <p>{{ t("plat_loading_leverages") }}</p>
        </div>

        <div
          v-else-if="leverageSelectedPlatform && filteredLeverages.length > 0"
          class="groups-list"
        >
          <div
            v-for="leverage in filteredLeverages"
            :key="leverage.id"
            class="group-item leverage-item"
            :class="{ disabled: !leverage.isEnabled }"
          >
            <div class="group-info">
              <div class="group-name-row">
                <span class="group-name">{{ leverage.displayLabel }}</span>
                <span
                  class="status-badge"
                  :class="{ disabled: !leverage.isEnabled }"
                >
                  {{
                    leverage.isEnabled
                      ? t("plat_status_enabled")
                      : t("plat_status_disabled")
                  }}
                </span>
              </div>
              <div class="group-details">
                <span class="group-detail">
                  <i class="fas fa-chart-line"></i>
                  {{ t("plat_leverage_value") }} {{ leverage.leverageValue }}
                </span>
                <span class="group-detail">
                  <i class="fas fa-sort-numeric-down"></i>
                  {{ t("plat_leverage_order") }}
                  {{ leverage.displayOrder ?? 0 }}
                </span>
                <span v-if="leverage.riskNote" class="group-detail">
                  <i class="fas fa-shield-alt"></i> {{ leverage.riskNote }}
                </span>
              </div>
            </div>
            <div
              v-if="hasEditPermission"
              class="group-actions leverage-actions"
            >
              <button
                type="button"
                class="btn-small btn-ghost"
                @click="openEditLeverageModal(leverage.id)"
                :disabled="
                  loadingLeverageDetailId === leverage.id ||
                  savingLeverageAction
                "
              >
                <i class="fas fa-pen"></i> {{ t("plat_btn_edit") }}
              </button>
              <button
                type="button"
                class="btn-small"
                :class="leverage.isEnabled ? 'btn-warn' : 'btn-success'"
                @click="toggleLeverageStatus(leverage)"
                :disabled="savingLeverageAction"
              >
                <i
                  :class="
                    leverage.isEnabled ? 'fas fa-eye-slash' : 'fas fa-eye'
                  "
                ></i>
                {{
                  leverage.isEnabled
                    ? t("plat_btn_disable")
                    : t("plat_btn_enable")
                }}
              </button>
              <button
                v-if="canManageSelectedLeverages"
                type="button"
                class="btn-small btn-danger"
                @click="deleteLeverage(leverage)"
                :disabled="savingLeverageAction"
              >
                <i class="fas fa-trash"></i> {{ t("plat_btn_delete") }}
              </button>
            </div>
          </div>
        </div>

        <div v-else-if="leverageSelectedPlatform" class="empty-state compact">
          <i class="fas fa-sliders-h"></i>
          <p>
            {{
              tParams("plat_empty_leverages", "", {
                name: getPlatformName(leverageSelectedPlatform),
              })
            }}
          </p>
        </div>
      </div>
    </div>

    <div
      class="settings-card"
      :class="{ collapsed: collapsedCards.accountSettings }"
    >
      <div class="card-header" @click="toggleCard('accountSettings')">
        <div class="card-header-content">
          <h2>
            <i class="fas fa-user-cog"></i>
            {{ t("plat_card_accountSettingsTitle", "Account Open Settings") }}
          </h2>
          <p>
            {{
              t(
                "plat_card_accountSettingsDesc",
                "Per-platform account limit and default open-account password (used when client does not provide one).",
              )
            }}
          </p>
        </div>
        <i class="fas fa-chevron-down card-collapse-icon"></i>
      </div>
      <div class="card-body">
        <div v-if="enabledPlatforms.length === 0" class="empty-state compact">
          <i class="fas fa-inbox"></i>
          <p>{{ t("plat_noPlatformHint") }}</p>
        </div>
        <div v-else class="account-settings-list">
          <div
            v-for="platform in enabledPlatforms"
            :key="`account-settings-${platform.key}`"
            class="account-settings-item"
          >
            <div class="account-settings-header">
              <div class="platform-selection-icon" :class="platform.key">
                <i :class="platform.icon"></i>
              </div>
              <div>
                <div class="account-settings-name">{{ platform.name }}</div>
                <div class="account-settings-code">
                  {{ platform.shortCode || platform.key.toUpperCase() }}
                </div>
              </div>
            </div>
            <div class="account-settings-fields">
              <div class="form-group">
                <label>{{
                  t("plat_label_accountLimit", "Max active accounts")
                }}</label>
                <input
                  type="number"
                  min="1"
                  class="form-input"
                  v-model.number="
                    accountSettingsForms[platform.key].accountLimit
                  "
                  :disabled="
                    !hasEditPermission || savingAccountSettings[platform.key]
                  "
                />
              </div>
              <div class="form-group">
                <label>{{
                  t("plat_label_passwordMode", "Open-account password")
                }}</label>
                <select
                  class="form-input"
                  v-model="accountSettingsForms[platform.key].passwordMode"
                  :disabled="
                    !hasEditPermission || savingAccountSettings[platform.key]
                  "
                >
                  <option value="manual">
                    {{ t("plat_password_manual", "Client enters manually") }}
                  </option>
                  <option value="random">
                    {{ t("plat_password_random", "System generates randomly") }}
                  </option>
                </select>
              </div>
              <button
                v-if="hasEditPermission"
                type="button"
                class="btn btn-primary"
                @click="saveAccountSettings(platform.key)"
                :disabled="savingAccountSettings[platform.key]"
              >
                <i class="fas fa-save"></i>
                {{
                  savingAccountSettings[platform.key]
                    ? t("plat_btn_saving", "Saving...")
                    : t("plat_btn_save", "Save")
                }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <LeverageModal
      v-model="showLeverageModal"
      :leverage="editingLeverage"
      :default-platform-key="leverageSelectedPlatform"
      :platform-name="
        getPlatformName(
          editingLeverage?.platformKey || leverageSelectedPlatform,
        )
      "
      :platforms="enabledPlatforms"
      :saving="savingLeverageModal"
      :error-message="leverageModalError"
      :lock-leverage-value="!canManageSelectedLeverages"
      @close="closeLeverageModal"
      @save="saveLeverage"
    />

    <TradingGroupLabelModal
      v-model="showTradingGroupLabelModal"
      :initial-label="editingTradingGroup?.label || ''"
      :initial-unit="editingTradingGroup?.unit || ''"
      :initial-scale="editingTradingGroup?.scale ?? 1"
      :group-name="editingTradingGroup?.name || ''"
      :platform-name="getPlatformName(selectedPlatform)"
      :saving="savingGroupLabel"
      :error-message="tradingGroupLabelError"
      @close="closeTradingGroupLabelModal"
      @save="saveTradingGroupLabel"
    />

    <div
      v-if="notification.show"
      class="notification"
      :class="notification.type"
    >
      {{ notification.message }}
      <button @click="notification.show = false" class="close-btn">
        &times;
      </button>
    </div>
  </div>
</template>

<script setup>
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";

import { computed, onMounted, reactive, ref } from "vue";
import { useAuthStore } from "@/stores/auth";
import loginSettingsService from "@/services/loginSettingsService";
import LeverageModal from "@/components/platform/LeverageModal.vue";
import TradingGroupLabelModal from "@/components/platform/TradingGroupLabelModal.vue";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t, tParams } = useAdminI18n();

const authStore = useAuthStore();

const hasReadonlyPermission = computed(() =>
  authStore.hasPermission("page_platformsettings_readonly"),
);
const hasEditPermission = computed(() =>
  authStore.hasPermission("page_platformsettings_edit"),
);

const collapsedCards = reactive({
  tradingGroups: false,
  leverages: false,
  accountSettings: false,
});

const accountSettingsForms = reactive({});
const savingAccountSettings = reactive({});

const syncingGroups = ref(false);
const savingDefault = ref(false);
const loadingLeverages = ref(false);
const savingLeverageAction = ref(false);
const savingLeverageModal = ref(false);
const savingGroupLabel = ref(false);
const loadingLeverageDetailId = ref(null);
const showLeverageModal = ref(false);
const showTradingGroupLabelModal = ref(false);
const leverageModalError = ref("");
const tradingGroupLabelError = ref("");
const selectedPlatform = ref(null);
const leverageSelectedPlatform = ref(null);
const editingLeverage = ref(null);
const editingTradingGroup = ref(null);
const tradingGroups = ref([]);
const enabledPlatforms = ref([]);
const leverageItems = ref([]);

const platformIcons = {
  financepro: "fas fa-chart-bar",
  mt4: "fas fa-desktop",
  mt5: "fas fa-laptop",
};

const filteredLeverages = computed(() => {
  if (!leverageSelectedPlatform.value) return [];
  return leverageItems.value.filter(
    (item) => item.platformKey === leverageSelectedPlatform.value,
  );
});

// FP 这类平台的杠杆列表必须和外部系统对齐，只允许 enable/disable
const canManageSelectedLeverages = computed(() => {
  const platform = enabledPlatforms.value.find(
    (p) => p.key === leverageSelectedPlatform.value,
  );
  return platform ? platform.allowLeverageManagement !== false : true;
});

const notification = reactive({
  show: false,
  type: "success",
  message: "",
});

const toggleCard = (cardName) => {
  collapsedCards[cardName] = !collapsedCards[cardName];
};

const showNotification = (type, message) => {
  notification.type = type;
  notification.message = message;
  notification.show = true;
  setTimeout(() => {
    notification.show = false;
  }, 3000);
};

const normalizeLeverageItem = (item) => ({
  ...item,
  isEnabled: !!item.isEnabled,
  displayOrder: Number(item.displayOrder ?? 0),
});

const selectPlatform = async (platformKey) => {
  selectedPlatform.value = platformKey;
  await loadTradingGroups(platformKey);
};

const selectLeveragePlatform = async (platformKey) => {
  leverageSelectedPlatform.value = platformKey;
  await loadLeverages();
};

const loadTradingGroups = async (platformKey) => {
  try {
    const response = await loginSettingsService.getTradingGroups(platformKey);
    tradingGroups.value = (response.data || []).map((group) => ({
      ...group,
      isDefault: !!group.isDefault,
    }));
  } catch (error) {
    console.error("Failed to load trading groups:", error);
    tradingGroups.value = [];
  }
};

const loadLeverages = async () => {
  loadingLeverages.value = true;
  try {
    const response = await loginSettingsService.getAdminTradingLeverages();
    leverageItems.value = (response.data?.items || []).map(
      normalizeLeverageItem,
    );
  } catch (error) {
    console.error("Failed to load leverages:", error);
    leverageItems.value = [];
    showNotification(
      "error",
      error.response?.data?.message ||
        error.message ||
        t("plat_err_loadLeverages"),
    );
  } finally {
    loadingLeverages.value = false;
  }
};

const syncPlatformGroups = async (platformKey) => {
  syncingGroups.value = true;
  try {
    const response = await loginSettingsService.syncTradingGroups(platformKey);
    if (response.data && response.data[platformKey]) {
      const result = response.data[platformKey];
      if (result.success) {
        showNotification(
          "success",
          tParams("plat_notify_syncCounts", "", {
            synced: result.synced,
            updated: result.updated,
          }),
        );
        await loadTradingGroups(platformKey);
      } else {
        showNotification("error", result.error || t("plat_err_syncResult"));
      }
    } else {
      showNotification("success", t("plat_notify_syncOk"));
      await loadTradingGroups(platformKey);
    }
  } catch (error) {
    showNotification(
      "error",
      error.response?.data?.message ||
        error.message ||
        t("plat_err_syncGroups"),
    );
  } finally {
    syncingGroups.value = false;
  }
};

const setDefaultGroup = async (groupId, platformKey) => {
  savingDefault.value = true;
  try {
    await loginSettingsService.setDefaultTradingGroup(groupId, platformKey);
    showNotification("success", t("plat_notify_defaultSet"));
    await loadTradingGroups(platformKey);
  } catch (error) {
    showNotification(
      "error",
      error.response?.data?.message ||
        error.message ||
        t("plat_err_setDefault"),
    );
  } finally {
    savingDefault.value = false;
  }
};

const removeDefaultGroup = async (groupId, platformKey) => {
  savingDefault.value = true;
  try {
    await loginSettingsService.removeDefaultTradingGroup(groupId, platformKey);
    showNotification("success", t("plat_notify_defaultRemoved"));
    await loadTradingGroups(platformKey);
  } catch (error) {
    showNotification(
      "error",
      error.response?.data?.message ||
        error.message ||
        t("plat_err_removeDefault"),
    );
  } finally {
    savingDefault.value = false;
  }
};

const openCreateLeverageModal = () => {
  editingLeverage.value = null;
  leverageModalError.value = "";
  showLeverageModal.value = true;
};

const openEditLeverageModal = async (id) => {
  loadingLeverageDetailId.value = id;
  leverageModalError.value = "";
  try {
    const response = await loginSettingsService.getAdminTradingLeverage(id);
    editingLeverage.value = normalizeLeverageItem(response.data);
    showLeverageModal.value = true;
  } catch (error) {
    showNotification(
      "error",
      error.response?.data?.message ||
        error.message ||
        t("plat_err_loadLeverageDetail"),
    );
  } finally {
    loadingLeverageDetailId.value = null;
  }
};

const closeLeverageModal = () => {
  showLeverageModal.value = false;
  editingLeverage.value = null;
  leverageModalError.value = "";
};

const openTradingGroupLabelModal = (group) => {
  editingTradingGroup.value = { ...group };
  tradingGroupLabelError.value = "";
  showTradingGroupLabelModal.value = true;
};

const closeTradingGroupLabelModal = () => {
  showTradingGroupLabelModal.value = false;
  editingTradingGroup.value = null;
  tradingGroupLabelError.value = "";
};

const saveTradingGroupLabel = async (payload) => {
  if (!editingTradingGroup.value?.id) return;

  savingGroupLabel.value = true;
  tradingGroupLabelError.value = "";
  try {
    const groupBefore = editingTradingGroup.value;
    const operationLogGroupBefore = {
      label: groupBefore.label ?? "",
      unit: groupBefore.unit ?? "",
      scale: groupBefore.scale ?? "",
    };
    await loginSettingsService.updateTradingGroupLabel(
      editingTradingGroup.value.id,
      payload.label,
    );
    await loginSettingsService.updateTradingGroupConfig(
      editingTradingGroup.value.id,
      {
        unit: payload.unit,
        scale: payload.scale,
        operationLogGroupBefore,
      },
    );
    showNotification("success", t("plat_notify_groupUpdated"));
    await loadTradingGroups(selectedPlatform.value);
    closeTradingGroupLabelModal();
  } catch (error) {
    tradingGroupLabelError.value =
      error.response?.data?.message ||
      error.message ||
      t("plat_err_updateGroup");
  } finally {
    savingGroupLabel.value = false;
  }
};

const saveLeverage = async (payload) => {
  savingLeverageModal.value = true;
  leverageModalError.value = "";
  try {
    if (editingLeverage.value?.id) {
      await loginSettingsService.updateAdminTradingLeverage(
        editingLeverage.value.id,
        {
          leverageValue: payload.leverageValue,
          displayLabel: payload.displayLabel,
          riskNote: payload.riskNote,
          displayOrder: payload.displayOrder,
        },
      );
      showNotification("success", t("plat_notify_leverageUpdated"));
    } else {
      await loginSettingsService.createAdminTradingLeverage(payload);
      showNotification("success", t("plat_notify_leverageCreated"));
    }
    await loadLeverages();
    closeLeverageModal();
  } catch (error) {
    leverageModalError.value =
      error.response?.data?.message ||
      error.message ||
      t("plat_err_saveLeverage");
  } finally {
    savingLeverageModal.value = false;
  }
};

const toggleLeverageStatus = async (leverage) => {
  savingLeverageAction.value = true;
  try {
    if (leverage.isEnabled) {
      await loginSettingsService.disableAdminTradingLeverage(leverage.id);
      showNotification("success", t("plat_notify_leverageDisabled"));
    } else {
      await loginSettingsService.enableAdminTradingLeverage(leverage.id);
      showNotification("success", t("plat_notify_leverageEnabled"));
    }
    await loadLeverages();
  } catch (error) {
    showNotification(
      "error",
      error.response?.data?.message ||
        error.message ||
        t("plat_err_toggleLeverage"),
    );
  } finally {
    savingLeverageAction.value = false;
  }
};

const deleteLeverage = async (leverage) => {
  if (
    !confirm(
      tParams("plat_confirm_deleteLeverage", "", {
        name: leverage.displayLabel,
      }),
    )
  )
    return;

  savingLeverageAction.value = true;
  try {
    await loginSettingsService.deleteAdminTradingLeverage(leverage.id);
    showNotification("success", t("plat_notify_leverageDeleted"));
    await loadLeverages();
  } catch (error) {
    showNotification(
      "error",
      error.response?.data?.message ||
        error.message ||
        t("plat_err_deleteLeverage"),
    );
  } finally {
    savingLeverageAction.value = false;
  }
};

const getPlatformName = (platformKey) => {
  const platform = enabledPlatforms.value.find((p) => p.key === platformKey);
  return platform ? platform.name : platformKey;
};

const saveAccountSettings = async (platformKey) => {
  const form = accountSettingsForms[platformKey];
  if (!form) return;

  const limit = Number(form.accountLimit);
  if (!Number.isFinite(limit) || limit < 1) {
    showNotification(
      "error",
      t(
        "plat_account_limit_invalid",
        "Account limit must be a positive integer.",
      ),
    );
    return;
  }

  savingAccountSettings[platformKey] = true;
  try {
    const res = await loginSettingsService.updatePlatformAccountSettings(
      platformKey,
      {
        accountLimit: limit,
        passwordMode: form.passwordMode === "manual" ? "manual" : "random",
      },
    );

    const platform = enabledPlatforms.value.find((p) => p.key === platformKey);
    if (platform) {
      platform.accountLimit = res.data?.accountLimit ?? limit;
      platform.passwordMode = res.data?.passwordMode ?? "random";
      form.accountLimit = platform.accountLimit;
      form.passwordMode = platform.passwordMode;
    }
    showNotification(
      "success",
      t("plat_account_settings_saved", "Platform account settings saved."),
    );
  } catch (error) {
    showNotification(
      "error",
      error.response?.data?.message ||
        error.message ||
        t(
          "plat_account_settings_save_fail",
          "Failed to save platform account settings.",
        ),
    );
  } finally {
    savingAccountSettings[platformKey] = false;
  }
};

const loadPlatformSettings = async () => {
  try {
    const platformsRes = await loginSettingsService.getTradingGroupPlatforms();
    if (platformsRes.data && Array.isArray(platformsRes.data)) {
      enabledPlatforms.value = platformsRes.data.map((p) => ({
        key: p.key ?? p.platformKey ?? "",
        name:
          p.name ??
          p.platformName ??
          p.displayName ??
          p.key ??
          p.platformKey ??
          "",
        shortCode: p.shortCode ?? p.platformCode ?? "",
        icon: platformIcons[p.key ?? p.platformKey] || "fas fa-server",
        allowLeverageManagement: Number(p.allowLeverageManagement ?? 1) === 1,
        accountLimit: Number(p.accountLimit ?? 1),
        passwordMode: p.passwordMode === "manual" ? "manual" : "random",
      }));
      enabledPlatforms.value.forEach((p) => {
        accountSettingsForms[p.key] = {
          accountLimit: p.accountLimit,
          passwordMode: p.passwordMode,
        };
        if (savingAccountSettings[p.key] === undefined) {
          savingAccountSettings[p.key] = false;
        }
      });
      if (
        enabledPlatforms.value.length > 0 &&
        selectedPlatform.value === null
      ) {
        selectedPlatform.value = enabledPlatforms.value[0].key;
      }
      if (
        enabledPlatforms.value.length > 0 &&
        leverageSelectedPlatform.value === null
      ) {
        leverageSelectedPlatform.value = enabledPlatforms.value[0].key;
      }
    }

    if (selectedPlatform.value) {
      await Promise.all([
        loadTradingGroups(selectedPlatform.value),
        loadLeverages(),
      ]);
    }
  } catch (error) {
    console.error("Failed to load platform settings:", error);
    showNotification(
      "error",
      error.response?.data?.message ||
        error.message ||
        t("plat_err_loadPlatform"),
    );
  }
};

onMounted(() => {
  loadPlatformSettings();
});
</script>

<style scoped>
.container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 40px 20px;
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
  gap: 10px;
}

.page-title h1 i {
  color: var(--color-brand);
}

.page-title p {
  font-size: 14px;
  color: var(--color-muted);
}

.page-actions {
  display: flex;
  align-items: center;
  gap: 20px;
}

.settings-card {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  margin-bottom: 30px;
  overflow: hidden;
  transition: all 0.3s ease;
}

.card-header {
  background: var(--color-surface-soft);
  padding: 20px 30px;
  border-bottom: 2px solid var(--color-border);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: space-between;
  user-select: none;
  transition: background 0.2s ease;
}

.card-header:hover {
  background: var(--color-surface-muted);
}

.card-header-content h2 {
  font-size: 20px;
  color: var(--color-ink);
  margin-bottom: 5px;
}

.card-header-content h2 i {
  margin-right: 10px;
  color: var(--color-brand);
}

.card-header-content p {
  font-size: 14px;
  color: var(--color-muted);
}

.card-collapse-icon {
  font-size: 18px;
  color: var(--color-faint);
  transition: transform 0.3s ease;
}

.settings-card.collapsed .card-collapse-icon {
  transform: rotate(-90deg);
}

.card-body {
  padding: 30px;
  max-height: 5000px;
  overflow: hidden;
  transition:
    max-height 0.4s ease,
    opacity 0.3s ease,
    padding 0.3s ease;
  opacity: 1;
}

.settings-card.collapsed .card-body {
  max-height: 0;
  opacity: 0;
  padding-top: 0;
  padding-bottom: 0;
}

.settings-card.collapsed .card-header {
  border-bottom: none;
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

.label-description {
  display: block;
  color: var(--color-muted);
  font-weight: 400;
  font-size: 14px;
  margin-top: 5px;
}

.platform-selection-cards {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
}

.platform-selection-card {
  display: flex;
  align-items: center;
  gap: 14px;
  min-width: 260px;
  padding: 14px 18px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  text-align: left;
}

.platform-selection-card:hover:not(:disabled) {
  border-color: var(--color-brand-soft);
  box-shadow: 0 2px 8px rgba(var(--color-brand-rgb), 0.1);
}

.platform-selection-card.active {
  border-color: var(--color-brand);
  background: linear-gradient(
    135deg,
    var(--color-brand-soft) 0%,
    var(--color-surface) 100%
  );
  box-shadow: 0 6px 18px rgba(var(--color-brand-rgb), 0.14);
}

.platform-selection-card:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.platform-selection-icon {
  width: 44px;
  height: 44px;
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  color: white;
  flex-shrink: 0;
  background: linear-gradient(135deg, #64748b 0%, #475569 100%);
}

.platform-selection-icon.mt4 {
  background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
}

.platform-selection-icon.mt5 {
  background: linear-gradient(135deg, var(--color-accent) 0%, #6d28d9 100%);
}

.platform-selection-icon.financepro {
  background: linear-gradient(135deg, var(--color-success) 0%, #059669 100%);
}

.platform-selection-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex: 1;
  min-width: 0;
}

.platform-selection-name {
  font-size: 14px;
  font-weight: 700;
  color: var(--color-ink);
}

.platform-selection-code {
  font-size: 14px;
  color: var(--color-muted);
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.platform-selection-check {
  color: var(--color-faint);
  font-size: 18px;
}

.platform-selection-card.active .platform-selection-check {
  color: var(--color-brand);
}

.no-platform-hint {
  margin-top: 10px;
  color: var(--color-muted);
  font-size: 14px;
}

.section-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}

.btn {
  border: none;
  border-radius: var(--radius-md);
  padding: 12px 24px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.btn-primary {
  background: var(--color-brand-solid);
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: var(--color-brand-strong);
}

.btn-sync {
  background: var(--color-brand-solid);
  color: white;
}

.btn-sync:hover:not(:disabled) {
  background: var(--color-brand-strong);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.3);
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.spinning {
  animation: spin 1s linear infinite;
}

.groups-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  /* 组别很多时列表在自身区域内滚动，避免被 card-body 的 overflow 裁掉看不到 */
  max-height: 600px;
  overflow-y: auto;
  padding-right: 4px;
}

.group-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  padding: 18px 20px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  transition: all 0.3s ease;
}

.group-item.is-default {
  border-color: var(--color-brand);
  background: var(--color-surface-soft);
}

.group-item.disabled {
  opacity: 0.72;
  background: var(--color-surface-soft);
}

.group-info {
  flex: 1;
}

.group-name-row {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 8px;
  flex-wrap: wrap;
}

.group-name {
  font-size: 15px;
  font-weight: 600;
  color: var(--color-ink);
}

.default-badge,
.status-badge {
  background: var(--color-brand-solid);
  color: white;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 14px;
  font-weight: 700;
}

.status-badge.disabled {
  background: var(--color-faint);
}

.group-details {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.group-detail {
  font-size: 14px;
  color: var(--color-muted);
}

.group-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.btn-small {
  border: none;
  border-radius: var(--radius-md);
  padding: 10px 14px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-set-default,
.btn-success {
  background: var(--color-brand-solid);
  color: white;
}

.btn-remove-default {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.btn-ghost {
  background: var(--color-surface-muted);
  color: var(--color-ink);
}

.btn-warn {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.btn-danger {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.btn-small:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.leverage-item {
  align-items: flex-start;
}

.leverage-actions {
  justify-content: flex-end;
}

.empty-state {
  text-align: center;
  padding: 36px 20px;
  border: 2px dashed var(--color-border);
  border-radius: var(--radius-lg);
  color: var(--color-muted);
}

.empty-state.compact {
  padding: 28px 20px;
}

.empty-state i {
  font-size: 28px;
  margin-bottom: 12px;
  color: var(--color-faint);
}

.info-box {
  background: var(--color-brand-soft);
  border-left: 4px solid var(--color-brand);
  padding: 15px 20px;
  border-radius: var(--radius-md);
  margin-top: 15px;
}

.info-box.info-box-warning {
  background: var(--color-danger-soft);
  border-left-color: var(--color-danger-border);
}

.info-box p {
  color: var(--color-text);
  font-size: 14px;
  line-height: 1.6;
  margin: 0;
}

.info-box i {
  margin-right: 6px;
}

.notification {
  position: fixed;
  top: 20px;
  right: 20px;
  padding: 15px 40px 15px 20px;
  border-radius: var(--radius-md);
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
  z-index: 2000;
  animation: slideIn 0.3s ease;
}

.notification.success {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.notification.error {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.notification .close-btn {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  font-size: 20px;
  cursor: pointer;
}

@keyframes slideIn {
  from {
    transform: translateX(400px);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 768px) {
  .container {
    padding: 24px 16px;
  }

  .page-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
  }

  .card-header,
  .card-body {
    padding-left: 20px;
    padding-right: 20px;
  }

  .group-item {
    flex-direction: column;
    align-items: flex-start;
  }

  .group-actions,
  .leverage-actions {
    width: 100%;
  }
}

.account-settings-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.account-settings-item {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: 16px 18px;
  background: var(--color-surface-soft);
}

.account-settings-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 14px;
}

.account-settings-name {
  font-size: 15px;
  font-weight: 600;
  color: var(--color-ink);
}

.account-settings-code {
  font-size: 14px;
  color: var(--color-muted);
}

.account-settings-fields {
  display: flex;
  flex-wrap: wrap;
  gap: 14px;
  align-items: flex-end;
}

.account-settings-fields .form-group {
  flex: 1 1 220px;
  margin-bottom: 0;
}

.account-settings-fields .form-group label {
  display: block;
  margin-bottom: 6px;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
}

.account-settings-fields .form-input {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #cbd5e1;
  border-radius: var(--radius-md);
  font-size: 14px;
  background: var(--color-surface);
  transition:
    border-color 0.15s ease,
    box-shadow 0.15s ease;
}

.account-settings-fields .form-input:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.15);
}

.account-settings-fields .form-input:disabled {
  background: var(--color-surface-soft);
  cursor: not-allowed;
}

.account-settings-fields .btn-primary {
  white-space: nowrap;
}
</style>
