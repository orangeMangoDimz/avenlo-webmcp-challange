<template>
  <div>
    <!-- Page Header (hidden when embedded in Settings tabs) -->
    <div v-if="!embedded" class="page-header">
      <div class="page-title">
        <h1>{{ t("ibTierMgmt_page_title") }}</h1>
        <p>{{ t("ibTierMgmt_page_sub") }}</p>
      </div>
      <div class="page-actions">
        <PageHeaderActions />
      </div>
    </div>

    <!-- Statistics Header
    <div class="stats-header">
      <div>
        <h2 style="font-size: 20px; color: var(--color-ink); margin-bottom: 5px;">All IB Tier Levels</h2>
        <p style="font-size: 14px; color: var(--color-muted);">Manage individual IB agent tier levels with permissions and settings</p>
      </div>
      <div class="page-stats">
        <div class="stat-badge">
          <i class="fas fa-layer-group"></i>
          <span>{{ statistics.totalTiers }} Total Tiers</span>
        </div>
        <div class="stat-badge" style="background: var(--color-success-soft); color: var(--color-success);">
          <i class="fas fa-check-circle"></i>
          <span>{{ statistics.activeTiers }} Active</span>
        </div>
      </div>
    </div> -->

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <i
        class="fas fa-spinner fa-spin"
        style="font-size: 32px; color: var(--color-brand)"
      ></i>
      <p style="margin-top: 15px; color: var(--color-muted)">
        {{ t("ibTierMgmt_loading") }}
      </p>
    </div>

    <!-- Tier Table -->
    <div v-else class="tier-table-container">
      <div class="table-header">
        <div class="table-header-left">
          <h2>{{ t("ibTierMgmt_table_title") }}</h2>
        </div>
        <div class="table-header-right">
          <button
            v-if="hasSetTierCount !== false"
            type="button"
            class="btn btn-secondary"
            @click="openTierCountModal"
          >
            <i class="fas fa-sliders-h"></i>
            {{ t("ibTierMgmt_btn_setTierCount", "Set Tier Count") }}
          </button>
          <button
            v-if="hasCreateTier !== false"
            class="btn btn-success"
            @click="openCreateModal"
          >
            <i class="fas fa-plus"></i> {{ t("ibTierMgmt_btn_new") }}
          </button>
        </div>
      </div>

      <table class="tier-table">
        <thead>
          <tr>
            <th>{{ t("ibTierMgmt_th_tierName") }}</th>
            <th>{{ t("ibTierMgmt_th_tierLevel") }}</th>
            <th>{{ t("ibTierMgmt_th_created") }}</th>
            <th>{{ t("ibTierMgmt_th_description") }}</th>
            <th>{{ t("ibTierMgmt_th_status") }}</th>
            <th>{{ t("ibTierMgmt_th_action") }}</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="tier in tiers" :key="tier.id">
            <tr :data-tier-id="tier.id">
              <td>{{ tier.tierName }}</td>
              <td>{{ tier.tierLevel }}</td>
              <td>{{ formatDate(tier.createdAt) }}</td>
              <td class="tier-levels-desc-cell">
                {{ tier.tierDescription || t("ibTierMgmt_noDescription") }}
              </td>
              <td>
                <span class="status-badge" :class="tier.status">{{
                  formatTierStatus(tier.status)
                }}</span>
              </td>
              <td>
                <div class="action-buttons">
                  <button
                    v-if="hasEditTier !== false"
                    class="btn-icon btn-edit"
                    @click="openEditModal(tier)"
                    :title="t('ibTierMgmt_aria_edit')"
                  >
                    <i class="fas fa-edit"></i>
                  </button>
                  <!-- 复制按钮已隐藏，如需恢复请移除此 class -->
                  <button
                    v-if="false"
                    class="btn-icon btn-copy"
                    @click="copyTier(tier.id)"
                    :title="t('ibTierMgmt_aria_copy')"
                  >
                    <i class="fas fa-copy"></i>
                  </button>
                  <button
                    v-if="hasDeleteTier !== false"
                    class="btn-icon btn-delete"
                    @click="deleteTier(tier.id)"
                    :title="t('ibTierMgmt_aria_delete')"
                  >
                    <i class="fas fa-trash"></i>
                  </button>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <!-- Create/Edit Tier Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="modal-overlay" @click="closeModal">
        <div class="modal" @click.stop>
          <div class="modal-header">
            <h2 class="modal-title">
              <i class="fas fa-plus-circle"></i>
              {{ t("ibTierMgmt_modal_createTitle") }}
            </h2>
            <button class="modal-close" @click="closeModal">
              <i class="fas fa-times"></i>
            </button>
          </div>

          <div class="modal-body">
            <form @submit.prevent="saveTierModal">
              <!-- Basic Information -->
              <div class="form-section">
                <h4>
                  <i class="fas fa-info-circle"></i>
                  {{ t("ibTierMgmt_section_tierInfo") }}
                </h4>

                <div class="form-group">
                  <label class="form-label required">{{
                    t("ibTierMgmt_label_tierLevel")
                  }}</label>
                  <div
                    ref="tierLevelDropdownRef"
                    class="tier-level-dropdown"
                    :class="{ open: tierLevelDropdownOpen }"
                  >
                    <button
                      type="button"
                      class="form-select tier-level-dropdown__trigger"
                      :class="{ 'is-placeholder': isNewTierLevelPlaceholder }"
                      @click.stop="toggleTierLevelDropdown"
                    >
                      <span class="tier-level-dropdown__value">{{
                        newTierLevelLabel
                      }}</span>
                      <i
                        class="fas fa-chevron-down tier-level-dropdown__arrow"
                      ></i>
                    </button>
                    <div
                      v-show="tierLevelDropdownOpen"
                      class="tier-level-dropdown__menu"
                    >
                      <button
                        type="button"
                        class="tier-level-dropdown__option"
                        :class="{ 'is-selected': isNewTierLevelPlaceholder }"
                        @click="selectTierLevel('')"
                      >
                        {{ t("ibTierMgmt_placeholder_tierLevel") }}
                      </button>
                      <button
                        v-for="n in tierLevelOptionNumbers"
                        :key="n"
                        type="button"
                        class="tier-level-dropdown__option"
                        :class="{
                          'is-selected': Number(newTier.tierLevel) === n,
                        }"
                        :disabled="usedTierLevels.has(n)"
                        @click="selectTierLevel(n)"
                      >
                        {{ formatTierLevelOption(n) }}
                      </button>
                    </div>
                  </div>
                </div>

                <div class="form-group">
                  <label class="form-label required">{{
                    t("ibTierMgmt_label_tierName")
                  }}</label>
                  <input
                    type="text"
                    v-model="newTier.tierName"
                    :placeholder="t('ibTierMgmt_placeholder_tierName')"
                    required
                    class="form-input"
                  />
                </div>

                <div class="form-group">
                  <label class="form-label">{{
                    t("ibTierMgmt_label_description")
                  }}</label>
                  <textarea
                    v-model="newTier.tierDescription"
                    :placeholder="t('ibTierMgmt_placeholder_desc')"
                    class="form-textarea"
                  ></textarea>
                </div>

                <div class="form-group">
                  <div class="badge-color-row">
                    <div class="badge-color-side">
                      <label class="form-label">{{
                        t("ibTierMgmt_label_badgeColor", "Badge Color")
                      }}</label>
                      <div class="badge-color-field">
                        <input
                          type="color"
                          v-model="newTier.badgeColor"
                          class="badge-color-field__picker"
                        />
                        <input
                          type="text"
                          v-model="newTier.badgeColor"
                          class="form-input badge-color-field__hex"
                          placeholder="#475569"
                          maxlength="7"
                        />
                      </div>
                    </div>
                    <div class="badge-color-side">
                      <label class="form-label">{{
                        t("ibTierMgmt_label_preview", "Preview")
                      }}</label>
                      <div
                        class="badge-color-preview-box"
                        :style="badgePreviewStyle(newTier.badgeColor)"
                      >
                        {{ newTier.tierName || "Tier" }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="form-section">
                <div class="form-group">
                  <label class="form-label">{{
                    t("ibTierMgmt_label_status")
                  }}</label>
                  <select v-model="newTier.status" class="form-select">
                    <option value="active">
                      {{ t("ibTierMgmt_status_active") }}
                    </option>
                    <option value="inactive">
                      {{ t("ibTierMgmt_status_inactive") }}
                    </option>
                    <option value="draft">
                      {{ t("ibTierMgmt_status_draft") }}
                    </option>
                  </select>
                </div>
              </div>
            </form>
          </div>

          <div class="modal-footer">
            <button class="btn btn-secondary" @click="closeModal">
              {{ t("ibTierMgmt_btn_cancel") }}
            </button>
            <button
              class="btn btn-primary"
              @click="saveTierModal"
              :disabled="saving"
            >
              <i
                class="fas"
                :class="saving ? 'fa-spinner fa-spin' : 'fa-save'"
              ></i>
              {{
                saving
                  ? t("ibTierMgmt_btn_creating")
                  : t("ibTierMgmt_btn_create")
              }}
            </button>
          </div>
        </div>
      </div>

      <!-- Set tier count modal -->
      <div
        v-if="showTierCountModal"
        class="modal-overlay"
        @click="closeTierCountModal"
      >
        <div class="modal modal-tier-count" @click.stop>
          <div class="modal-header">
            <h2 class="modal-title">
              <i class="fas fa-sliders-h"></i>
              {{ t("ibTierMgmt_modal_setTierCountTitle", "Set Tier Count") }}
            </h2>
            <button class="modal-close" @click="closeTierCountModal">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <form class="tier-count-form" @submit.prevent="saveTierCount">
              <label
                class="form-label tier-count-form__label"
                for="tier-count-input"
              >
                {{ t("ibTierMgmt_modal_setTierCountTitle", "Set Tier Count") }}
              </label>
              <input
                id="tier-count-input"
                v-model="tierCountInput"
                type="number"
                min="1"
                step="1"
                class="form-input tier-count-form__input"
                :disabled="savingTierCount"
                @keydown.e.prevent
                @keydown.plus.prevent
                @keydown.minus.prevent
              />
              <p class="tier-count-form__hint">
                {{ t("ibTierMgmt_tierCount_hint") }}
              </p>
            </form>
          </div>
          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-secondary"
              @click="closeTierCountModal"
            >
              {{ t("ibTierMgmt_btn_cancel") }}
            </button>
            <button
              type="button"
              class="btn btn-primary"
              @click="saveTierCount"
              :disabled="savingTierCount"
            >
              <i
                class="fas"
                :class="savingTierCount ? 'fa-spinner fa-spin' : 'fa-save'"
              ></i>
              {{
                savingTierCount
                  ? t("ibTierMgmt_btn_saving")
                  : t("ibTierMgmt_btn_save")
              }}
            </button>
          </div>
        </div>
      </div>

      <!-- Edit Tier Modal -->
      <div v-if="showEditModal" class="modal-overlay" @click="closeEditModal">
        <div class="modal" @click.stop>
          <div class="modal-header">
            <h2 class="modal-title">
              <i class="fas fa-edit"></i> {{ t("ibTierMgmt_modal_editTitle") }}
            </h2>
            <button class="modal-close" @click="closeEditModal">
              <i class="fas fa-times"></i>
            </button>
          </div>

          <div class="modal-body">
            <form @submit.prevent="saveEditModal">
              <div class="form-section">
                <h4>
                  <i class="fas fa-info-circle"></i>
                  {{ t("ibTierMgmt_section_tierInfo") }}
                </h4>

                <div class="form-group">
                  <label class="form-label">{{
                    t("ibTierMgmt_label_tierLevel")
                  }}</label>
                  <div class="form-readonly">
                    {{
                      tParams("ibTierMgmt_tierLevel_readonly", "Tier {n}", {
                        n: editForm.tierLevel,
                      })
                    }}
                  </div>
                </div>

                <div class="form-group">
                  <label class="form-label required">{{
                    t("ibTierMgmt_label_tierName")
                  }}</label>
                  <input
                    type="text"
                    v-model="editForm.tierName"
                    :placeholder="t('ibTierMgmt_placeholder_tierName')"
                    required
                    class="form-input"
                  />
                </div>

                <div class="form-group">
                  <label class="form-label">{{
                    t("ibTierMgmt_label_description")
                  }}</label>
                  <textarea
                    v-model="editForm.tierDescription"
                    :placeholder="t('ibTierMgmt_placeholder_desc')"
                    class="form-textarea"
                  ></textarea>
                </div>

                <div class="form-group">
                  <div class="badge-color-row">
                    <div class="badge-color-side">
                      <label class="form-label">{{
                        t("ibTierMgmt_label_badgeColor", "Badge Color")
                      }}</label>
                      <div class="badge-color-field">
                        <input
                          type="color"
                          v-model="editForm.badgeColor"
                          class="badge-color-field__picker"
                        />
                        <input
                          type="text"
                          v-model="editForm.badgeColor"
                          class="form-input badge-color-field__hex"
                          placeholder="#475569"
                          maxlength="7"
                        />
                      </div>
                    </div>
                    <div class="badge-color-side">
                      <label class="form-label">{{
                        t("ibTierMgmt_label_preview", "Preview")
                      }}</label>
                      <div
                        class="badge-color-preview-box"
                        :style="badgePreviewStyle(editForm.badgeColor)"
                      >
                        {{ editForm.tierName || "Tier" }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="form-section">
                <div class="form-group">
                  <label class="form-label">{{
                    t("ibTierMgmt_label_status")
                  }}</label>
                  <select v-model="editForm.status" class="form-select">
                    <option value="active">
                      {{ t("ibTierMgmt_status_active") }}
                    </option>
                    <option value="inactive">
                      {{ t("ibTierMgmt_status_inactive") }}
                    </option>
                    <option value="draft">
                      {{ t("ibTierMgmt_status_draft") }}
                    </option>
                  </select>
                </div>
              </div>
            </form>
          </div>

          <div class="modal-footer">
            <button class="btn btn-secondary" @click="closeEditModal">
              {{ t("ibTierMgmt_btn_cancel") }}
            </button>
            <button
              class="btn btn-primary"
              @click="saveEditModal"
              :disabled="saving"
            >
              <i
                class="fas"
                :class="saving ? 'fa-spinner fa-spin' : 'fa-save'"
              ></i>
              {{
                saving ? t("ibTierMgmt_btn_saving") : t("ibTierMgmt_btn_save")
              }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";
import { ref, reactive, computed, onMounted, onUnmounted } from "vue";
import ibTierLevelsApi from "@/services/ibTierLevelsApi";
import ibSettingsApi from "@/services/ibSettingsApi";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { useIbTierConfig } from "@/composables/useIbTierConfig";
import { translateApiErrorMessage } from "@/i18n/adminI18nBridge";

defineProps({
  embedded: { type: Boolean, default: false },
  hasCreateTier: { type: Boolean, default: true },
  hasEditTier: { type: Boolean, default: true },
  hasDeleteTier: { type: Boolean, default: true },
  hasSetTierCount: { type: Boolean, default: false },
});

const { t, tParams, languageStore } = useAdminI18n();
const { maxTierLevelCount, loadMaxTierLevelCount, setMaxTierLevelCountLocal } =
  useIbTierConfig();

const formatTierStatus = (status) => {
  if (!status || typeof status !== "string") return "—";
  const keyMap = {
    active: "ibTierMgmt_status_active",
    inactive: "ibTierMgmt_status_inactive",
    draft: "ibTierMgmt_status_draft",
  };
  const k = keyMap[status.toLowerCase()];
  return k ? t(k) : status;
};

const loading = ref(true);
const saving = ref(false);
const savingTierCount = ref(false);
const tiers = ref([]);
const showModal = ref(false);
const showEditModal = ref(false);
const showTierCountModal = ref(false);
const tierCountInput = ref("5");
const editingTierId = ref(null);

const DEFAULT_BADGE_COLOR = "#475569";

const newTier = reactive({
  tierLevel: "",
  tierName: "",
  tierDescription: "",
  badgeColor: DEFAULT_BADGE_COLOR,
  status: "active",
});

const editForm = reactive({
  tierLevel: null,
  tierName: "",
  tierDescription: "",
  badgeColor: DEFAULT_BADGE_COLOR,
  status: "active",
});

const isValidHexColor = (value) =>
  /^#[0-9a-fA-F]{6}$/.test(String(value || "").trim());

const badgePreviewStyle = (color) => {
  const c = String(color || "").trim();
  if (!isValidHexColor(c)) return { background: "#f1f5f9", color: "#94a3b8" };
  return { background: c + "1f", color: c };
};

const usedTierLevels = computed(
  () => new Set(tiers.value.map((item) => item.tierLevel)),
);

const tierLevelOptionNumbers = computed(() => {
  const max = Math.max(1, maxTierLevelCount.value || 5);
  return Array.from({ length: max }, (_, i) => i + 1);
});

const tierLevelDropdownRef = ref(null);
const tierLevelDropdownOpen = ref(false);

const isNewTierLevelPlaceholder = computed(() => {
  const level = newTier.tierLevel;
  return level === "" || level === null || level === undefined;
});

const newTierLevelLabel = computed(() => {
  if (isNewTierLevelPlaceholder.value) {
    return t("ibTierMgmt_placeholder_tierLevel");
  }
  return formatTierLevelOption(Number(newTier.tierLevel));
});

const formatTierLevelOption = (n) =>
  tParams("ibTierMgmt_opt_tierN", "Tier {n}", { n });

const toggleTierLevelDropdown = () => {
  tierLevelDropdownOpen.value = !tierLevelDropdownOpen.value;
};

const selectTierLevel = (level) => {
  newTier.tierLevel = level === "" ? "" : Number(level);
  tierLevelDropdownOpen.value = false;
};

const onTierLevelDocumentClick = (event) => {
  if (!tierLevelDropdownOpen.value) return;
  const root = tierLevelDropdownRef.value;
  if (root && !root.contains(event.target)) {
    tierLevelDropdownOpen.value = false;
  }
};

const statistics = computed(() => {
  return {
    totalTiers: tiers.value.length,
    activeTiers: tiers.value.filter((t) => t.status === "active").length,
  };
});

/**
 * 获取层级图标
 */
const getTierIcon = (tierLevel) => {
  const icons = {
    1: "fa-crown",
    2: "fa-star",
    3: "fa-award",
    4: "fa-medal",
  };
  return icons[tierLevel] || "fa-certificate";
};

/**
 * 获取层级颜色
 */
const getTierColor = (tierLevel) => {
  const colors = {
    1: "#f59e0b",
    2: "#8b5cf6",
    3: "#3b82f6",
    4: "#10b981",
  };
  return colors[tierLevel] || "#64748b";
};

/**
 * 打开编辑弹窗
 */
const openEditModal = (tier) => {
  editingTierId.value = tier.id;
  editForm.tierLevel = tier.tierLevel;
  editForm.tierName = tier.tierName;
  editForm.tierDescription = tier.tierDescription || "";
  editForm.badgeColor = isValidHexColor(tier.badgeColor)
    ? tier.badgeColor
    : DEFAULT_BADGE_COLOR;
  editForm.status = tier.status || "active";
  showEditModal.value = true;
};

/**
 * 关闭编辑弹窗
 */
const closeEditModal = () => {
  showEditModal.value = false;
  editingTierId.value = null;
};

/**
 * 保存编辑
 */
const saveEditModal = async () => {
  if (!editingTierId.value || !editForm.tierName) {
    alert(t("ibTierMgmt_alert_fillTierName"));
    return;
  }

  try {
    saving.value = true;

    if (!isValidHexColor(editForm.badgeColor)) {
      alert(
        t(
          "ibTierMgmt_alert_invalidColor",
          "Badge color must be a hex color like #475569",
        ),
      );
      return;
    }

    const data = {
      tierName: editForm.tierName,
      tierDescription: editForm.tierDescription,
      badgeColor: editForm.badgeColor,
      status: editForm.status,
    };

    const response = await ibTierLevelsApi.updateTierLevel(
      editingTierId.value,
      data,
    );

    if (response.success) {
      const tierIndex = tiers.value.findIndex(
        (t) => t.id === editingTierId.value,
      );
      if (tierIndex > -1) {
        Object.assign(tiers.value[tierIndex], data);
      }
      alert(t("ibTierMgmt_alert_updateOk"));
      closeEditModal();
      await loadTiers();
    } else {
      alert(
        tParams(
          "ibTierMgmt_alert_updateFail",
          "Failed to save tier level: {msg}",
          { msg: response.message ?? "" },
        ),
      );
    }
  } catch (error) {
    console.error("Failed to save tier level:", error);
    alert(t("ibTierMgmt_alert_updateFailGeneric"));
  } finally {
    saving.value = false;
  }
};

/**
 * 打开创建模态框
 */
const openCreateModal = () => {
  tierLevelDropdownOpen.value = false;
  // 重置表单
  newTier.tierLevel = "";
  newTier.tierName = "";
  newTier.tierDescription = "";
  newTier.badgeColor = DEFAULT_BADGE_COLOR;
  newTier.status = "active";

  showModal.value = true;
};

/**
 * 关闭模态框
 */
const closeModal = () => {
  showModal.value = false;
  tierLevelDropdownOpen.value = false;
};

/**
 * 保存新层级
 */
const saveTierModal = async () => {
  if (!newTier.tierLevel || !newTier.tierName) {
    alert(t("ibTierMgmt_alert_fillRequired"));
    return;
  }

  try {
    saving.value = true;

    if (!isValidHexColor(newTier.badgeColor)) {
      alert(
        t(
          "ibTierMgmt_alert_invalidColor",
          "Badge color must be a hex color like #475569",
        ),
      );
      return;
    }

    const data = {
      tierLevel: newTier.tierLevel,
      tierName: newTier.tierName,
      tierDescription: newTier.tierDescription,
      badgeColor: newTier.badgeColor,
      status: newTier.status,
    };

    const response = await ibTierLevelsApi.createTierLevel(data);

    if (response.success) {
      alert(t("ibTierMgmt_alert_createOk"));
      closeModal();
      await loadTiers();
    } else {
      alert(
        tParams(
          "ibTierMgmt_alert_createFail",
          "Failed to create tier level: {msg}",
          { msg: response.message ?? "" },
        ),
      );
    }
  } catch (error) {
    console.error("Failed to create tier level:", error);

    // 现在所有错误都返回200 HTTP状态码，需要检查响应体中的statusCode
    if (error.statusCode === 409) {
      alert(t("ibTierMgmt_alert_createDuplicate"));
    } else {
      alert(error.message || t("ibTierMgmt_alert_createFailGeneric"));
    }
  } finally {
    saving.value = false;
  }
};

/**
 * 复制层级
 */
const copyTier = async (tierId) => {
  const tier = tiers.value.find((t) => t.id === tierId);
  if (!tier) return;

  const confirmMessage = tParams(
    "ibTierMgmt_confirm_copy",
    "Copy Tier Template\n\nAre you sure you want to copy this tier?\n\nTier: Tier {level} - {name}\n\nA new draft tier will be created.",
    { level: tier.tierLevel, name: tier.tierName || "" },
  );

  if (!confirm(confirmMessage)) return;

  try {
    // 找一个未使用的层级编号
    const usedLevels = tiers.value.map((t) => t.tierLevel);
    let newLevel = 1;
    const maxLevel = maxTierLevelCount.value || 5;
    while (usedLevels.includes(newLevel) && newLevel < maxLevel) {
      newLevel++;
    }

    const data = {
      tierLevel: newLevel,
      tierName: tier.tierName + t("ibTierMgmt_copyNameSuffix"),
      tierDescription: tier.tierDescription,
      status: "draft",
    };

    const response = await ibTierLevelsApi.createTierLevel(data);

    if (response.success) {
      alert(
        tParams(
          "ibTierMgmt_alert_copyOk",
          'Tier copied successfully.\n\nNew tier: "Tier {level} - {name}"\nStatus: Draft\n\nYou can now edit the copied tier.',
          { level: newLevel, name: data.tierName },
        ),
      );
      await loadTiers();
    } else {
      alert(
        tParams("ibTierMgmt_alert_copyFail", "Failed to copy tier: {msg}", {
          msg: response.message ?? "",
        }),
      );
    }
  } catch (error) {
    console.error("Failed to copy tier:", error);
    alert(t("ibTierMgmt_alert_copyFailGeneric"));
  }
};

/**
 * 删除层级
 */
const deleteTier = async (tierId) => {
  const tier = tiers.value.find((t) => t.id === tierId);
  if (!tier) return;

  const confirmMessage = tParams(
    "ibTierMgmt_confirm_delete",
    "Delete Tier Level\n\nAre you sure you want to delete this tier?\n\nTier: Tier {level} - {name}\nStatus: {status}\n\n⚠️ This action cannot be undone!",
    {
      level: tier.tierLevel,
      name: tier.tierName || "",
      status: formatTierStatus(tier.status),
    },
  );

  if (!confirm(confirmMessage)) return;

  if (tier.status === "active") {
    const finalConfirm = confirm(t("ibTierMgmt_confirm_deleteActive"));
    if (!finalConfirm) return;
  }

  try {
    const response = await ibTierLevelsApi.deleteTierLevel(tierId);

    if (response.success) {
      alert(
        tParams(
          "ibTierMgmt_alert_deleteOk",
          'Tier deleted successfully.\n\nTier "{name}" has been removed from the system.',
          { name: tier.tierName || "" },
        ),
      );
      await loadTiers();
    } else {
      alert(
        tParams("ibTierMgmt_alert_deleteFail", "Failed to delete tier: {msg}", {
          msg: response.message ?? "",
        }),
      );
    }
  } catch (error) {
    console.error("Failed to delete tier:", error);

    // 现在所有错误都返回200 HTTP状态码，需要检查响应体中的statusCode
    if (error.statusCode === 409) {
      alert(t("ibTierMgmt_alert_deleteInUse"));
    } else {
      alert(error.message || t("ibTierMgmt_alert_deleteFailGeneric"));
    }
  }
};

/**
 * 格式化日期
 */
const formatDate = (dateString) => {
  if (!dateString) return t("ibDocCard_date_na");
  const date = new Date(dateString);
  const loc = languageStore.currentLanguage === "zh" ? "zh-CN" : "en-US";
  return date.toLocaleDateString(loc, {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
};

/**
 * 加载层级列表
 */
const loadTiers = async () => {
  try {
    loading.value = true;

    const response = await ibTierLevelsApi.getTierLevels(true);

    if (response.success && response.data) {
      tiers.value = response.data.sort((a, b) => a.tierLevel - b.tierLevel);
    }
  } catch (error) {
    console.error("Failed to load tier levels:", error);
    alert(t("ibTierMgmt_alert_loadFail"));
  } finally {
    loading.value = false;
  }
};

onMounted(async () => {
  document.addEventListener("click", onTierLevelDocumentClick);
  await Promise.all([loadMaxTierLevelCount(), loadTiers()]);
});

onUnmounted(() => {
  document.removeEventListener("click", onTierLevelDocumentClick);
});

const openTierCountModal = () => {
  tierCountInput.value = String(maxTierLevelCount.value || 5);
  showTierCountModal.value = true;
};

const closeTierCountModal = () => {
  showTierCountModal.value = false;
};

const saveTierCount = async () => {
  const raw = String(tierCountInput.value ?? "").trim();
  if (!/^\d+$/.test(raw)) {
    alert(
      t(
        "ibTierMgmt_alert_tierCountInvalid",
        "Please enter a valid whole number.",
      ),
    );
    return;
  }
  const n = parseInt(raw, 10);
  if (n < 1) {
    alert(
      t(
        "ibTierMgmt_alert_tierCountInvalid",
        "Please enter a valid whole number.",
      ),
    );
    return;
  }

  try {
    savingTierCount.value = true;
    await ibSettingsApi.updateSetting("max_tier_level_count", String(n));
    setMaxTierLevelCountLocal(n);
    alert(
      t("ibTierMgmt_alert_tierCountSaved", "Tier count updated successfully."),
    );
    closeTierCountModal();
  } catch (error) {
    const data = error?.response?.data ?? error;
    const rawMsg = data?.message || error?.message;
    alert(
      translateApiErrorMessage(data?.errorCode, rawMsg) ||
        t(
          "ibTierMgmt_alert_tierCountSaveFailed",
          "Failed to update tier count.",
        ),
    );
  } finally {
    savingTierCount.value = false;
  }
};
</script>

<style scoped>
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

.stats-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 2px solid var(--color-border);
}

.page-stats {
  display: flex;
  gap: 15px;
}

.stat-badge {
  background: var(--color-brand-soft);
  color: var(--color-brand);
  padding: 8px 16px;
  border-radius: 20px;
  font-size: 14px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 8px;
}

.stat-badge i {
  font-size: 16px;
}

.loading-state {
  text-align: center;
  padding: 100px 20px;
  color: var(--color-muted);
}

.tier-table-container {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

.table-header {
  padding: 20px 30px;
  background: var(--color-surface-soft);
  border-bottom: 2px solid var(--color-border);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.table-header h2 {
  font-size: 18px;
  color: var(--color-ink);
}

.table-header-left {
  display: flex;
  align-items: center;
  gap: 20px;
  flex: 1;
}

.table-header-right {
  display: flex;
  align-items: center;
  gap: 10px;
}

.btn {
  padding: 12px 20px;
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

.btn-success {
  background: var(--color-success-solid);
  color: white;
  box-shadow: 0 2px 8px rgba(72, 187, 120, 0.3);
}

.btn-success:hover {
  background: var(--color-success-solid);
  transform: translateY(-2px);
}

.tier-table {
  width: 100%;
  border-collapse: collapse;
}

.tier-table thead {
  background: var(--color-surface-soft);
}

.tier-table th {
  padding: 16px 20px;
  text-align: center;
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 2px solid var(--color-border);
}

.tier-table tbody tr {
  border-bottom: 1px solid var(--color-border);
  transition: all 0.2s ease;
}

.tier-table tbody tr:hover {
  background: var(--color-surface-soft);
}

.tier-table tbody tr.expanded {
  background: var(--color-brand-soft);
}

.tier-table td {
  padding: 16px 20px;
  font-size: 14px;
  color: var(--color-ink);
  text-align: center;
}

.tier-info {
  display: flex;
  flex-direction: column;
}

.tier-name {
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 4px;
  font-size: 15px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.tier-name i {
  font-size: 18px;
}

.tier-description {
  font-size: 12px;
  color: var(--color-muted);
}

.tier-levels-permissions-wrap {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  justify-content: center;
}

/* Description 列：提高优先级覆盖 .tier-table td */
.tier-table td.tier-levels-desc-cell {
  max-width: 220px;
  min-width: 160px;
  text-align: left !important;
  word-break: break-word;
  line-height: 1.5;
  color: var(--color-ink);
  padding-left: 16px;
  padding-right: 16px;
  vertical-align: top;
}

.permission-badge {
  padding: 4px 10px;
  border-radius: var(--radius-lg);
  font-size: 11px;
  font-weight: 600;
}

.permission-badge.recruit {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.permission-badge.reports {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.permission-badge.manage {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.permission-badge.basic {
  background: var(--color-surface-soft);
  color: var(--color-muted);
}

.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: var(--radius-lg);
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
}

.status-badge.active {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.status-badge.inactive {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.status-badge.draft {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.action-buttons {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.btn-action {
  padding: 8px 16px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.btn-detail {
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.btn-detail:hover {
  background: var(--color-brand-solid);
  color: white;
}

.btn-icon {
  padding: 8px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 14px;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
}

.btn-copy {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.btn-copy:hover {
  background: #0284c7;
  color: white;
  transform: translateY(-2px);
}

/* 复制按钮隐藏，如需恢复请移除此 class */
.ib-tier-levels-copy-btn-hidden {
  visibility: hidden;
  pointer-events: none;
}

.btn-edit {
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.btn-edit:hover {
  background: var(--color-brand-solid);
  color: white;
  transform: translateY(-2px);
}

.btn-delete {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.btn-delete:hover {
  background: var(--color-danger-solid);
  color: white;
  transform: translateY(-2px);
}

.detail-row {
  background: var(--color-surface-soft);
}

.detail-content {
  padding: 30px;
}

.detail-sections {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

.detail-section {
  background: var(--color-surface);
  border-radius: var(--radius-md);
  padding: 25px;
  border: 2px solid var(--color-border);
}

.detail-section h3 {
  font-size: 16px;
  color: var(--color-ink);
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  align-items: center;
  gap: 10px;
}

.detail-section h3 i {
  color: var(--color-brand);
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
}

.section-title {
  display: flex;
  align-items: center;
  gap: 10px;
}

.btn-save {
  padding: 6px 16px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: var(--color-border);
  color: var(--color-faint);
}

.btn-save:disabled {
  cursor: not-allowed;
}

.btn-save.active {
  background: var(--color-brand-solid);
  color: white;
  box-shadow: 0 2px 8px rgba(var(--color-brand-rgb), 0.3);
}

.btn-save.active:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.4);
}

.detail-field {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 0;
  border-bottom: 1px solid #f0f0f0;
}

.detail-field:last-child {
  border-bottom: none;
}

.detail-label {
  font-weight: 600;
  color: var(--color-muted);
  font-size: 13px;
}

.detail-value {
  color: var(--color-ink);
  font-size: 14px;
  font-weight: 500;
  text-align: right;
}

.detail-input,
.detail-select {
  border: 2px solid var(--color-border);
  padding: 6px 12px;
  border-radius: var(--radius-sm);
  font-size: 13px;
  width: 200px;
  transition: all 0.3s ease;
}

.detail-input:focus,
.detail-select:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.permission-checkbox {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-sm);
  cursor: pointer;
  border: 2px solid transparent;
  transition: all 0.2s ease;
}

.permission-checkbox:hover {
  border-color: var(--color-border-strong);
}

.permission-checkbox input[type="checkbox"] {
  width: 18px;
  height: 18px;
  accent-color: var(--color-brand);
  cursor: pointer;
}

.permission-checkbox input[type="checkbox"]:checked ~ div {
  color: var(--color-ink);
}

.permission-title {
  font-weight: 600;
  font-size: 14px;
  color: var(--color-ink);
  margin-bottom: 2px;
}

.permission-desc {
  font-size: 12px;
  color: var(--color-muted);
}

/* Modal Styles */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.modal {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  max-width: 900px;
  width: 90%;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
  animation: slideUp 0.3s ease;
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(-20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.modal-header {
  flex-shrink: 0;
  padding: 25px 30px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--color-surface-soft);
}

.modal-title {
  font-size: 20px;
  font-weight: 600;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 0;
}

.modal-title i {
  color: var(--color-brand);
}

.modal-close {
  background: var(--color-border);
  border: none;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
  font-size: 18px;
  color: var(--color-text);
}

.modal-close:hover {
  background: var(--color-brand-solid);
  color: white;
}

.modal-body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: 30px;
}

.form-section {
  background: var(--color-surface-soft);
  padding: 15px;
  border-radius: var(--radius-md);
  margin-bottom: 20px;
}

.form-section h4 {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 15px;
}

.form-readonly {
  padding: 10px 14px;
  background: var(--color-surface-muted);
  border-radius: var(--radius-md);
  font-size: 14px;
  color: var(--color-text);
}

.form-section h4 i {
  color: var(--color-brand);
  margin-right: 8px;
}

.form-group {
  margin-bottom: 20px;
}

.form-label {
  display: block;
  font-size: 14px;
  font-weight: 500;
  color: var(--color-text);
  margin-bottom: 8px;
}

.form-label.required::after {
  content: " *";
  color: var(--color-danger);
}

.form-input,
.form-select,
.form-textarea {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  transition: all 0.3s ease;
}

.tier-level-dropdown {
  position: relative;
}

.tier-level-dropdown__trigger {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  width: 100%;
  text-align: left;
  background: var(--color-surface);
  cursor: pointer;
  appearance: none;
  font-family: inherit;
  line-height: 1.4;
}

.tier-level-dropdown__trigger.is-placeholder .tier-level-dropdown__value {
  color: var(--color-faint);
}

.tier-level-dropdown__value {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--color-ink);
}

.tier-level-dropdown__arrow {
  flex-shrink: 0;
  font-size: 12px;
  color: var(--color-muted);
  transition: transform 0.2s ease;
}

.tier-level-dropdown.open .tier-level-dropdown__arrow {
  transform: rotate(180deg);
}

.tier-level-dropdown.open .tier-level-dropdown__trigger {
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.tier-level-dropdown__menu {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  z-index: 20;
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
  max-height: min(220px, 36vh);
  overflow-y: auto;
}

.tier-level-dropdown__option {
  display: block;
  width: 100%;
  padding: 10px 16px;
  border: none;
  background: transparent;
  text-align: left;
  font-size: 14px;
  color: var(--color-ink);
  cursor: pointer;
  transition: background 0.15s ease;
}

.tier-level-dropdown__option:hover:not(:disabled) {
  background: var(--color-surface-soft);
}

.tier-level-dropdown__option.is-selected {
  background: var(--color-brand-soft);
  color: var(--color-brand);
  font-weight: 600;
}

.tier-level-dropdown__option:disabled {
  color: var(--color-border-strong);
  cursor: not-allowed;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.form-textarea {
  resize: vertical;
  min-height: 80px;
}

.badge-color-row {
  display: flex;
  align-items: flex-start;
  gap: 24px;
}

.badge-color-side {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
}

.badge-color-side:first-child {
  flex: 1;
  min-width: 0;
}

.badge-color-field {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 6px 12px 6px 6px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  transition: all 0.3s ease;
}

.badge-color-field:focus-within {
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.badge-color-field__picker {
  width: 36px;
  height: 36px;
  padding: 0;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  cursor: pointer;
}

.badge-color-field__picker::-webkit-color-swatch-wrapper {
  padding: 0;
}

.badge-color-field__picker::-webkit-color-swatch {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
}

.badge-color-field__picker::-moz-color-swatch {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
}

.badge-color-field__hex {
  flex: 1;
  min-width: 0;
  border: none;
  padding: 8px 0;
  font-size: 14px;
  color: var(--color-ink);
  text-transform: lowercase;
}

.badge-color-field__hex:focus {
  outline: none;
  box-shadow: none;
}

.badge-color-preview-box {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 50px;
  padding: 0 14px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  box-sizing: border-box;
  background: var(--color-surface-soft);
  color: var(--color-faint);
}

.info-banner {
  background: var(--color-brand-soft);
  padding: 12px;
  border-radius: var(--radius-sm);
  margin-bottom: 15px;
  border-left: 3px solid var(--color-brand);
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  color: var(--color-text);
}

.permissions-config {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.modal-footer {
  flex-shrink: 0;
  padding: 20px 30px;
  border-top: 2px solid var(--color-border);
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  background: var(--color-surface-soft);
}

.btn-secondary {
  background: var(--color-border);
  color: var(--color-text);
}

.btn-secondary:hover {
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

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.modal-tier-count {
  max-width: 480px;
}

.tier-count-form {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px 16px;
}

.tier-count-form__label {
  margin: 0;
  flex: 0 0 auto;
}

.tier-count-form__input {
  flex: 1;
  min-width: 120px;
  max-width: 180px;
}

.tier-count-form__hint {
  flex: 1 1 100%;
  margin: 0;
  font-size: 13px;
  color: var(--color-muted);
  line-height: 1.5;
}

@media (max-width: 768px) {
  .container {
    padding: 20px 15px;
  }

  .page-header,
  .stats-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 20px;
  }

  .page-stats {
    flex-direction: column;
    width: 100%;
  }

  .detail-sections {
    grid-template-columns: 1fr;
  }
}
</style>
