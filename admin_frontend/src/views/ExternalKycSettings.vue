<template>
  <div class="external-kyc-page">
    <!-- Page Header -->
    <div class="page-header">
      <div class="page-title">
        <h1>{{ t("page_externalKyc_title") }}</h1>
        <p>{{ t("page_externalKyc_sub") }}</p>
      </div>
      <div class="page-actions">
        <PageHeaderActions />
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-container">
      <i class="fas fa-spinner fa-spin"></i>
      <p>{{ t("externalKyc_loading") }}</p>
    </div>

    <!-- Content -->
    <div v-else class="settings-content">
      <div class="settings-card">
        <div class="card-header" @click="cardCollapsed = !cardCollapsed">
          <div class="card-header-content">
            <h2>
              <i class="fas fa-plug"></i> {{ t("externalKyc_card_title") }}
            </h2>
            <p>{{ t("externalKyc_card_sub") }}</p>
          </div>
          <i
            :class="[
              'fas fa-chevron-down card-collapse-icon',
              { rotated: !cardCollapsed },
            ]"
          ></i>
        </div>

        <transition name="card-expand">
          <div v-show="!cardCollapsed" class="card-body">
            <!-- Empty -->
            <div v-if="gateways.length === 0" class="empty-state">
              <i class="fas fa-info-circle"></i>
              <p>{{ t("externalKyc_empty") }}</p>
            </div>

            <!-- Gateway Table -->
            <div v-else class="gateway-table">
              <table>
                <thead>
                  <tr>
                    <th>{{ t("externalKyc_col_provider") }}</th>
                    <th>{{ t("externalKyc_col_displayName") }}</th>
                    <th>{{ t("externalKyc_col_isEnabled") }}</th>
                    <th>{{ t("externalKyc_col_environment") }}</th>
                    <th>{{ t("externalKyc_col_updatedAt") }}</th>
                    <th class="gateway-table__actions">
                      {{ t("externalKyc_col_actions") }}
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <template v-for="gateway in gateways" :key="gateway.id">
                    <!-- Summary row -->
                    <tr
                      :class="[
                        { inactive: !gateway.isEnabled },
                        { 'gateway-summary-row': isExpanded(gateway.id) },
                      ]"
                    >
                      <td>
                        <div class="gateway-cell">
                          <div class="gateway-icon-preview">
                            <i class="fas fa-id-card"></i>
                          </div>
                          <div class="gateway-cell-text">
                            <strong>{{ gateway.provider }}</strong>
                            <span>#{{ gateway.id }}</span>
                          </div>
                        </div>
                      </td>
                      <td>{{ gateway.displayName }}</td>
                      <td>
                        <span
                          :class="[
                            'status-badge',
                            gateway.isEnabled ? 'active' : 'inactive',
                          ]"
                        >
                          {{
                            gateway.isEnabled
                              ? t("externalKyc_status_enabled")
                              : t("externalKyc_status_disabled")
                          }}
                        </span>
                      </td>
                      <td>
                        <span class="env-badge" :class="gateway.environment">
                          {{
                            gateway.environment === "production"
                              ? t("externalKyc_env_production")
                              : t("externalKyc_env_sandbox")
                          }}
                        </span>
                      </td>
                      <td class="text-muted">
                        {{ formatDateTime(gateway.updatedAt) }}
                      </td>
                      <td class="gateway-table__actions">
                        <div class="gateway-row-actions">
                          <button
                            type="button"
                            class="btn-detail"
                            @click="toggleDetail(gateway)"
                          >
                            <i
                              :class="
                                isExpanded(gateway.id)
                                  ? 'fas fa-chevron-up'
                                  : 'fas fa-chevron-down'
                              "
                            ></i>
                            {{
                              isExpanded(gateway.id)
                                ? t("externalKyc_btn_hide")
                                : t("externalKyc_btn_detail")
                            }}
                          </button>
                          <button
                            v-if="hasEditPermission"
                            type="button"
                            class="gateway-action-btn gateway-action-btn--icon"
                            :title="t('externalKyc_btn_edit')"
                            @click="openEditModal(gateway)"
                          >
                            <i class="fas fa-edit"></i>
                          </button>
                          <button
                            v-if="hasEnableDisablePermission"
                            type="button"
                            class="gateway-action-btn gateway-action-btn--icon"
                            :title="
                              gateway.isEnabled
                                ? t('externalKyc_title_disable')
                                : t('externalKyc_title_enable')
                            "
                            @click="handleToggleEnabled(gateway)"
                          >
                            <i
                              :class="
                                gateway.isEnabled
                                  ? 'fas fa-toggle-on'
                                  : 'fas fa-toggle-off'
                              "
                            ></i>
                          </button>
                          <button
                            v-if="hasDeletePermission"
                            type="button"
                            class="gateway-action-btn gateway-action-btn--danger gateway-action-btn--icon"
                            :title="t('externalKyc_title_delete')"
                            @click="handleDelete(gateway)"
                          >
                            <i class="fas fa-trash"></i>
                          </button>
                        </div>
                      </td>
                    </tr>

                    <!-- Detail row -->
                    <tr
                      class="gateway-detail-row"
                      :class="{ show: isExpanded(gateway.id) }"
                    >
                      <td colspan="6">
                        <div
                          v-if="isExpanded(gateway.id)"
                          class="gateway-detail-content"
                        >
                          <div class="gateway-detail-section">
                            <div class="gateway-detail-section-header">
                              <h3>
                                <i class="fas fa-layer-group"></i>
                                {{ t("externalKyc_section_templates") }}
                              </h3>
                              <button
                                v-if="hasSyncPermission"
                                type="button"
                                class="gateway-section-edit-btn"
                                :disabled="
                                  syncingId === gateway.id || !gateway.isEnabled
                                "
                                @click="handleSync(gateway)"
                              >
                                <i
                                  :class="
                                    syncingId === gateway.id
                                      ? 'fas fa-spinner fa-spin'
                                      : 'fas fa-sync'
                                  "
                                ></i>
                                <span>{{ t("externalKyc_btn_sync") }}</span>
                              </button>
                            </div>

                            <div
                              v-if="getTemplatesState(gateway.id).loading"
                              class="loading-container compact"
                            >
                              <i class="fas fa-spinner fa-spin"></i>
                            </div>
                            <p
                              v-else-if="
                                getTemplatesState(gateway.id).items.length === 0
                              "
                              class="gateway-detail-empty"
                            >
                              {{ t("externalKyc_templates_empty") }}
                            </p>
                            <div v-else class="template-list">
                              <div
                                v-for="tpl in getTemplatesState(gateway.id)
                                  .items"
                                :key="tpl.id"
                                class="template-item"
                                :class="{ 'is-inactive': !tpl.isActive }"
                              >
                                <div class="template-item-main">
                                  <code class="template-name">{{
                                    tpl.externalLevelName
                                  }}</code>
                                  <span
                                    v-if="tpl.applicantType"
                                    class="template-applicant-badge"
                                    :class="tpl.applicantType"
                                  >
                                    {{
                                      tpl.applicantType === "company"
                                        ? t("externalKyc_applicant_company")
                                        : t("externalKyc_applicant_individual")
                                    }}
                                  </span>
                                  <span
                                    v-if="!tpl.isActive"
                                    class="template-inactive-badge"
                                  >
                                    {{ t("externalKyc_templates_inactive") }}
                                  </span>
                                </div>
                                <div
                                  v-if="tpl.description"
                                  class="template-description"
                                >
                                  {{ tpl.description }}
                                </div>
                                <div
                                  v-if="tpl.docTypesSummary"
                                  class="template-doctypes"
                                >
                                  <span
                                    v-for="docType in tpl.docTypesSummary.split(
                                      ',',
                                    )"
                                    :key="docType"
                                    class="template-doctype-chip"
                                  >
                                    {{ docType }}
                                  </span>
                                </div>
                                <div class="template-item-meta">
                                  <span
                                    v-if="tpl.externalLevelId"
                                    class="template-meta-chip"
                                  >
                                    ID: {{ tpl.externalLevelId }}
                                  </span>
                                  <span
                                    v-if="tpl.externalUpdatedAt"
                                    class="template-meta-chip"
                                  >
                                    <i class="fas fa-pen"></i>
                                    {{ formatDateTime(tpl.externalUpdatedAt) }}
                                  </span>
                                  <span class="template-meta-chip">
                                    <i class="far fa-clock"></i>
                                    {{ formatDateTime(tpl.lastSyncedAt) }}
                                  </span>
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>
                      </td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>
          </div>
        </transition>
      </div>
    </div>

    <!-- Edit Modal -->
    <div
      v-if="showEditModal"
      class="modal-overlay"
      @click.self="closeEditModal"
    >
      <div class="modal-content">
        <div class="modal-header">
          <h3>{{ t("externalKyc_modal_editTitle") }}</h3>
          <button type="button" class="btn-close-modal" @click="closeEditModal">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">{{
              t("externalKyc_label_displayName")
            }}</label>
            <input type="text" class="form-input" v-model="form.displayName" />
          </div>
          <div class="form-group">
            <label class="form-label">{{
              t("externalKyc_label_environment")
            }}</label>
            <select v-model="form.environment" class="form-input">
              <option value="sandbox">
                {{ t("externalKyc_env_sandbox") }}
              </option>
              <option value="production">
                {{ t("externalKyc_env_production") }}
              </option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">{{
              t("externalKyc_label_baseUrl")
            }}</label>
            <input
              type="text"
              class="form-input"
              v-model="form.baseUrl"
              placeholder="https://api.sumsub.com"
            />
          </div>
          <div class="form-group">
            <label class="form-label">{{
              t("externalKyc_label_iframeBaseUrl")
            }}</label>
            <input
              type="text"
              class="form-input"
              v-model="form.iframeBaseUrl"
            />
          </div>
          <div class="form-group">
            <label class="form-label">{{
              t("externalKyc_label_returnUrl")
            }}</label>
            <input type="text" class="form-input" v-model="form.returnUrl" />
          </div>
          <div class="form-group">
            <label class="form-label">{{
              t("externalKyc_label_detailUrl")
            }}</label>
            <input type="text" class="form-input" v-model="form.detailUrl" />
          </div>
          <div class="form-group">
            <label class="form-label">{{
              t("externalKyc_label_appToken")
            }}</label>
            <input
              type="password"
              class="form-input"
              v-model="form.appToken"
              autocomplete="new-password"
              :placeholder="t('externalKyc_secret_placeholder')"
            />
          </div>
          <div class="form-group">
            <label class="form-label">{{
              t("externalKyc_label_secretKey")
            }}</label>
            <input
              type="password"
              class="form-input"
              v-model="form.secretKey"
              autocomplete="new-password"
              :placeholder="t('externalKyc_secret_placeholder')"
            />
          </div>
          <div class="form-group">
            <label class="form-label">{{
              t("externalKyc_label_webhookSecret")
            }}</label>
            <input
              type="password"
              class="form-input"
              v-model="form.webhookSecret"
              autocomplete="new-password"
              :placeholder="t('externalKyc_secret_placeholder')"
            />
          </div>
        </div>
        <div class="modal-footer">
          <button
            type="button"
            class="btn btn-secondary"
            @click="closeEditModal"
          >
            {{ t("externalKyc_btn_cancel") }}
          </button>
          <button
            type="button"
            class="btn btn-primary"
            :disabled="saving"
            @click="handleSave"
          >
            <i :class="saving ? 'fas fa-spinner fa-spin' : 'fas fa-save'"></i>
            {{ t("externalKyc_btn_save") }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from "vue";
import { useAuthStore } from "@/stores/auth";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { externalKycGatewayService } from "@/services/externalKycGatewayService";
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";

const { t, tParams } = useAdminI18n();
const authStore = useAuthStore();

// 各按钮独立权限校验（和后端 api/external-kyc-gateways.php 的 guard 一一对应）
const hasReadPermission = computed(
  () =>
    authStore.hasPermission("page_externalkycsettings_readonly") ||
    authStore.hasPermission("page_externalkycsettings_edit"),
);
const hasEditPermission = computed(() =>
  authStore.hasPermission("page_externalkycsettings_edit"),
);
const hasEnableDisablePermission = computed(() =>
  authStore.hasPermission("page_externalkycsettings_enabledisable"),
);
const hasDeletePermission = computed(() =>
  authStore.hasPermission("page_externalkycsettings_delete"),
);
const hasSyncPermission = computed(() =>
  authStore.hasPermission("page_externalkycsettings_sync"),
);

const loading = ref(false);
const cardCollapsed = ref(false);
const gateways = ref([]);

const expandedIds = ref([]);
const isExpanded = (id) => expandedIds.value.includes(id);

const templatesByGatewayId = ref({});
const getTemplatesState = (id) =>
  templatesByGatewayId.value[id] || { loading: false, items: [] };

const syncingId = ref(null);

const showEditModal = ref(false);
const editingGateway = ref(null);
const saving = ref(false);
const form = reactive({
  displayName: "",
  environment: "sandbox",
  baseUrl: "",
  iframeBaseUrl: "",
  returnUrl: "",
  detailUrl: "",
  appToken: "",
  secretKey: "",
  webhookSecret: "",
});

const formatDateTime = (val) => {
  if (!val) return "--";
  const d = new Date(String(val).replace(" ", "T"));
  if (isNaN(d.getTime())) return val;
  const pad = (n) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

const extractMsg = (err) =>
  err?.response?.data?.message || err?.message || "Unknown error";

const loadGateways = async () => {
  loading.value = true;
  try {
    const res = await externalKycGatewayService.list();
    if (res.success) {
      gateways.value = res.data || [];
    } else {
      alert(
        tParams(
          "externalKyc_alert_loadFailed",
          "Failed to load gateways: {msg}",
          { msg: res.message || "" },
        ),
      );
    }
  } catch (err) {
    alert(
      tParams(
        "externalKyc_alert_loadFailed",
        "Failed to load gateways: {msg}",
        { msg: extractMsg(err) },
      ),
    );
  } finally {
    loading.value = false;
  }
};

const toggleDetail = (gateway) => {
  const isOpen = expandedIds.value.includes(gateway.id);
  // 一次只展开一行，行为和 transaction settings 一致
  expandedIds.value = isOpen ? [] : [gateway.id];
  if (!isOpen) {
    loadTemplates(gateway.id);
  }
};

const loadTemplates = async (gatewayId) => {
  templatesByGatewayId.value[gatewayId] = { loading: true, items: [] };
  try {
    const res = await externalKycGatewayService.listTemplates(gatewayId);
    templatesByGatewayId.value[gatewayId] = {
      loading: false,
      items: res.success ? res.data || [] : [],
    };
  } catch (err) {
    templatesByGatewayId.value[gatewayId] = { loading: false, items: [] };
  }
};

const handleToggleEnabled = async (gateway) => {
  const next = !gateway.isEnabled;
  if (!next && !confirm(t("externalKyc_confirm_disable"))) return;
  try {
    const res = await externalKycGatewayService.setEnabled(gateway.id, next);
    if (res.success) {
      gateway.isEnabled = next;
      // 同步合并最新字段（包含 updatedAt 等）
      if (res.data) Object.assign(gateway, res.data);
    } else {
      alert(
        tParams(
          "externalKyc_alert_toggleFailed",
          "Failed to update status: {msg}",
          { msg: res.message || "" },
        ),
      );
    }
  } catch (err) {
    alert(
      tParams(
        "externalKyc_alert_toggleFailed",
        "Failed to update status: {msg}",
        { msg: extractMsg(err) },
      ),
    );
  }
};

const handleDelete = async (gateway) => {
  if (!confirm(t("externalKyc_confirm_delete"))) return;
  try {
    const res = await externalKycGatewayService.softDelete(gateway.id);
    if (res.success) {
      gateways.value = gateways.value.filter((g) => g.id !== gateway.id);
      expandedIds.value = expandedIds.value.filter((id) => id !== gateway.id);
      alert(t("externalKyc_alert_deleteOk"));
    } else {
      alert(
        tParams("externalKyc_alert_deleteFailed", "Delete failed: {msg}", {
          msg: res.message || "",
        }),
      );
    }
  } catch (err) {
    alert(
      tParams("externalKyc_alert_deleteFailed", "Delete failed: {msg}", {
        msg: extractMsg(err),
      }),
    );
  }
};

const openEditModal = (gateway) => {
  editingGateway.value = gateway;
  // 后端不返回 secret，本地保持空 = 留空则不修改
  form.displayName = gateway.displayName || "";
  form.environment = gateway.environment || "sandbox";
  form.baseUrl = gateway.baseUrl || "";
  form.iframeBaseUrl = gateway.iframeBaseUrl || "";
  form.returnUrl = gateway.returnUrl || "";
  form.detailUrl = gateway.detailUrl || "";
  form.appToken = "";
  form.secretKey = "";
  form.webhookSecret = "";
  showEditModal.value = true;
};

const closeEditModal = () => {
  showEditModal.value = false;
  editingGateway.value = null;
};

const handleSave = async () => {
  if (!editingGateway.value) return;
  const payload = {
    displayName: form.displayName,
    environment: form.environment,
    baseUrl: form.baseUrl,
    iframeBaseUrl: form.iframeBaseUrl,
    returnUrl: form.returnUrl,
    detailUrl: form.detailUrl,
  };
  if (form.appToken !== "") payload.appToken = form.appToken;
  if (form.secretKey !== "") payload.secretKey = form.secretKey;
  if (form.webhookSecret !== "") payload.webhookSecret = form.webhookSecret;

  saving.value = true;
  try {
    const res = await externalKycGatewayService.update(
      editingGateway.value.id,
      payload,
    );
    if (res.success) {
      const idx = gateways.value.findIndex(
        (g) => g.id === editingGateway.value.id,
      );
      if (idx >= 0 && res.data) {
        gateways.value[idx] = { ...gateways.value[idx], ...res.data };
      }
      closeEditModal();
      alert(t("externalKyc_alert_saveOk"));
    } else {
      alert(
        tParams("externalKyc_alert_saveFailed", "Failed to save: {msg}", {
          msg: res.message || "",
        }),
      );
    }
  } catch (err) {
    alert(
      tParams("externalKyc_alert_saveFailed", "Failed to save: {msg}", {
        msg: extractMsg(err),
      }),
    );
  } finally {
    saving.value = false;
  }
};

const handleSync = async (gateway) => {
  syncingId.value = gateway.id;
  try {
    const res = await externalKycGatewayService.sync(gateway.id);
    if (res.success) {
      const created = res.data?.created || 0;
      const updated = res.data?.updated || 0;
      const restored = res.data?.restored || 0;
      const deleted = res.data?.deleted || 0;
      alert(
        tParams(
          "externalKyc_alert_syncOk",
          "Sync completed. Created: {created}, Updated: {updated}, Restored: {restored}, Removed: {deleted}.",
          { created, updated, restored, deleted },
        ),
      );
      await loadTemplates(gateway.id);
    } else {
      alert(
        tParams("externalKyc_alert_syncFailed", "Sync failed: {msg}", {
          msg: res.message || "",
        }),
      );
    }
  } catch (err) {
    alert(
      tParams("externalKyc_alert_syncFailed", "Sync failed: {msg}", {
        msg: extractMsg(err),
      }),
    );
  } finally {
    syncingId.value = null;
  }
};

onMounted(() => {
  loadGateways();
});
</script>

<style scoped>
/* ============================================================
 * Layout copied from TransactionSettings.vue style language
 * 保持和 Transaction Gateway Setting 一致的视觉风格
 * ============================================================ */

.external-kyc-page {
  padding: 40px 20px;
  max-width: 1400px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 2px solid var(--color-border);
}

.page-actions {
  display: flex;
  align-items: center;
  gap: 20px;
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

.loading-container {
  text-align: center;
  padding: 60px 20px;
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.loading-container i {
  font-size: 48px;
  color: var(--color-brand);
  margin-bottom: 20px;
}

.loading-container.compact {
  padding: 24px;
  background: transparent;
  box-shadow: none;
}

.loading-container.compact i {
  font-size: 22px;
  margin-bottom: 0;
}

.loading-container p {
  font-size: 16px;
  color: var(--color-text);
}

.settings-content {
  display: flex;
  flex-direction: column;
  gap: 30px;
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

.card-header-content {
  flex: 1;
}

.card-header h2 {
  font-size: 20px;
  color: var(--color-ink);
  margin-bottom: 5px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.card-header h2 i {
  color: var(--color-brand);
}

.card-header p {
  font-size: 14px;
  color: var(--color-muted);
}

.card-collapse-icon {
  font-size: 18px;
  color: var(--color-faint);
  transition:
    transform 0.3s ease,
    color 0.2s ease;
  margin-left: 20px;
}

.card-collapse-icon.rotated {
  transform: rotate(180deg);
}

.card-header:hover .card-collapse-icon {
  color: var(--color-brand);
}

.card-body {
  padding: 30px;
}

.card-expand-enter-active,
.card-expand-leave-active {
  transition: all 0.3s ease;
  overflow: hidden;
}

.card-expand-enter-from,
.card-expand-leave-to {
  max-height: 0;
  opacity: 0;
  padding-top: 0;
  padding-bottom: 0;
}

.card-expand-enter-to,
.card-expand-leave-from {
  max-height: 5000px;
  opacity: 1;
}

.empty-state {
  text-align: center;
  padding: 60px 20px;
  color: var(--color-muted);
}

.empty-state i {
  font-size: 48px;
  color: var(--color-border-strong);
  margin-bottom: 15px;
}

.empty-state p {
  font-size: 14px;
  margin: 0;
}

/* ===== Gateway Table ===== */
.gateway-table {
  overflow-x: auto;
  background: var(--color-surface);
  border-radius: var(--radius-md);
  border: 2px solid var(--color-border);
}

.gateway-table table {
  width: 100%;
  border-collapse: collapse;
}

.gateway-table thead {
  background: var(--color-surface-soft);
}

.gateway-table th {
  padding: 15px;
  text-align: left;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 2px solid var(--color-border);
}

.gateway-table th.gateway-table__actions,
.gateway-table td.gateway-table__actions {
  width: 280px;
  min-width: 280px;
  white-space: nowrap;
}

.gateway-table td {
  padding: 15px;
  border-bottom: 1px solid var(--color-border);
  color: var(--color-ink);
  font-size: 14px;
  vertical-align: middle;
}

.gateway-table tbody tr:hover {
  background: var(--color-surface-soft);
}

.gateway-table tbody tr.inactive {
  opacity: 0.7;
}

.gateway-summary-row {
  background: var(--color-brand-soft);
}

.gateway-detail-row {
  display: none;
}

.gateway-detail-row.show {
  display: table-row;
}

.gateway-detail-row td {
  background: var(--color-brand-soft);
  padding: 0;
}

.gateway-detail-row:hover {
  background: transparent !important;
}

.gateway-detail-content {
  padding: 24px;
  background: var(--color-surface-soft);
}

.gateway-detail-section {
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 25px;
}

.gateway-detail-section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
}

.gateway-detail-section h3 {
  margin: 0;
  font-size: 15px;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 8px;
}

.gateway-detail-section h3 i {
  color: var(--color-brand);
}

.gateway-section-edit-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 10px;
  border: 1px solid #dbe3f0;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.gateway-section-edit-btn:hover:not(:disabled) {
  border-color: #cbd5e1;
  background: var(--color-surface-soft);
  color: var(--color-ink);
}

.gateway-section-edit-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.gateway-detail-field {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  padding: 10px 0;
  border-bottom: 1px solid var(--color-surface-muted);
}

.gateway-detail-field:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

.gateway-detail-label {
  flex-shrink: 0;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-muted);
}

.gateway-detail-value {
  color: var(--color-ink);
  font-size: 14px;
  text-align: right;
  word-break: break-word;
}

.gateway-detail-value--break {
  word-break: break-all;
  font-size: 14px;
}

.gateway-detail-empty {
  color: var(--color-faint);
  font-size: 14px;
  margin: 0;
  padding: 12px 0;
}

/* ===== Action buttons in row ===== */
.gateway-row-actions {
  display: flex;
  flex-wrap: nowrap;
  align-items: center;
  gap: 8px;
}

.gateway-action-btn,
.btn-detail {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  white-space: nowrap;
  flex-shrink: 0;
  border-radius: var(--radius-md);
  border: 1px solid #dbe3f0;
  background: var(--color-surface);
  color: var(--color-text);
  cursor: pointer;
  transition: all 0.2s ease;
  font-size: 14px;
  font-weight: 600;
}

.gateway-action-btn {
  padding: 8px 10px;
}

.gateway-action-btn--icon {
  justify-content: center;
  min-width: 38px;
}

.btn-detail {
  padding: 8px 14px;
}

.gateway-action-btn:hover:not(:disabled),
.btn-detail:hover:not(:disabled) {
  border-color: var(--color-brand);
  color: var(--color-brand);
  background: var(--color-surface-soft);
}

.gateway-action-btn--danger {
  background: var(--color-danger-soft);
  border-color: var(--color-danger-border);
  color: var(--color-danger);
}

.gateway-action-btn--danger:hover:not(:disabled) {
  background: var(--color-danger-soft);
  border-color: var(--color-danger-border);
  color: var(--color-danger);
}

/* ===== Cells ===== */
.gateway-cell {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 180px;
}

.gateway-cell-text {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.gateway-cell-text strong {
  text-transform: uppercase;
  font-size: 14px;
  color: var(--color-ink);
}

.gateway-cell-text span {
  font-size: 14px;
  color: var(--color-muted);
}

.gateway-icon-preview {
  width: 36px;
  height: 36px;
  border-radius: var(--radius-md);
  background: var(--color-brand-soft);
  color: var(--color-brand);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  flex-shrink: 0;
}

.text-muted {
  color: var(--color-muted);
}

/* ===== Badges ===== */
.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: var(--radius-lg);
  font-size: 14px;
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

.env-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: var(--radius-lg);
  font-size: 14px;
  font-weight: 600;
}

.env-badge.sandbox {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.env-badge.production {
  background: var(--color-info-soft);
  color: var(--color-info);
}

/* ===== Template list ===== */
.template-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.template-item {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 12px 14px;
  background: var(--color-surface-soft);
}

.template-item.is-inactive {
  opacity: 0.65;
}

.template-item-main {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 6px;
}

.template-name {
  background: var(--color-brand-soft);
  color: var(--color-brand-strong);
  padding: 3px 8px;
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: 600;
}

.template-applicant-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: 600;
  text-transform: uppercase;
}

.template-applicant-badge.individual {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.template-applicant-badge.company {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.template-inactive-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: 600;
  text-transform: uppercase;
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.template-description {
  font-size: 14px;
  color: var(--color-text);
  margin-bottom: 8px;
  line-height: 1.4;
}

.template-doctypes {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-bottom: 6px;
}

.template-doctype-chip {
  font-size: 14px;
  color: var(--color-brand-strong);
  background: var(--color-brand-soft);
  padding: 2px 6px;
  border-radius: 4px;
  font-family: "Courier New", monospace;
}

.template-item-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.template-meta-chip {
  font-size: 14px;
  color: var(--color-muted);
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  padding: 2px 8px;
  border-radius: var(--radius-sm);
}

/* ===== Modal ===== */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
}

.modal-content {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow:
    0 20px 25px -5px rgba(0, 0, 0, 0.1),
    0 10px 10px -5px rgba(0, 0, 0, 0.04);
  max-width: 500px;
  width: 100%;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 30px;
  border-bottom: 2px solid var(--color-border);
}

.modal-header h3 {
  font-size: 20px;
  color: var(--color-ink);
  margin: 0;
}

.btn-close-modal {
  background: none;
  border: none;
  font-size: 20px;
  color: var(--color-faint);
  cursor: pointer;
  padding: 5px;
  transition: color 0.2s ease;
}

.btn-close-modal:hover {
  color: var(--color-muted);
}

.modal-body {
  padding: 30px;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 20px 30px;
  border-top: 2px solid var(--color-border);
}

/* ===== Form ===== */
.form-group {
  margin-bottom: 20px;
}

.form-label {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 8px;
}

.form-input {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  transition: all 0.3s ease;
}

.form-input:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

/* ===== Buttons ===== */
.btn {
  padding: 12px 24px;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;
  display: inline-flex;
  align-items: center;
  gap: 8px;
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

.btn-primary:disabled {
  background: var(--color-border-strong);
  color: var(--color-faint);
  box-shadow: none;
  cursor: not-allowed;
  opacity: 0.6;
}

.btn-secondary {
  background: var(--color-surface-soft);
  color: var(--color-text);
  border: 2px solid var(--color-border);
}

.btn-secondary:hover {
  background: var(--color-surface-muted);
  border-color: var(--color-border-strong);
}

@media (max-width: 768px) {
  .external-kyc-page {
    padding: 20px 15px;
  }

  .page-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 20px;
  }
}
</style>
