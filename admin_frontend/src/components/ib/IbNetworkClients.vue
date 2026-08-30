<template>
  <div class="ib-network-clients">
    <ExportProgressBanner
      v-if="exportBannerVisible && exportStatusText"
      :cancelling="exportCancelling"
      :status-text="exportStatusText"
      :percent="exportProgressPercent"
      :cancel-disabled="!exportJobId"
      :title="t('ibReport_exportInProgressTitle', 'Export in progress')"
      :cancelling-title="t('ibReport_exportCancelling', 'Cancelling export...')"
      :cancel-label="t('ibReport_exportCancel', 'Cancel')"
      @cancel-export="cancelActiveExport"
    />

    <div
      v-if="exportModal.visible"
      class="export-modal-overlay"
      @click="onExportModalContinue"
    >
      <div class="export-modal" @click.stop>
        <div class="export-modal-header">
          <h3>
            {{ t("ibReport_exportInProgressTitle", "Export in progress") }}
          </h3>
          <button
            type="button"
            class="export-modal-close"
            @click="onExportModalContinue"
          >
            ×
          </button>
        </div>
        <div class="export-modal-body">
          <p class="export-modal-text">
            {{
              exportModal.message ||
              t(
                "ibReport_exportInProgressMsg",
                "You already have a report export running. Continue waiting or cancel it.",
              )
            }}
          </p>
          <div class="export-modal-progress">
            <div
              class="export-modal-progress-bar"
              :style="{ width: `${exportModal.percent || 0}%` }"
            ></div>
          </div>
          <p class="export-modal-percent">{{ exportModal.percent || 0 }}%</p>
        </div>
        <div class="export-modal-footer">
          <button
            type="button"
            class="export-modal-btn primary"
            @click="onExportModalContinue"
            :disabled="exportModal.busy"
          >
            {{ t("ibReport_exportContinue", "Continue") }}
          </button>
          <button
            type="button"
            class="export-modal-btn secondary"
            @click="onExportModalCancel"
            :disabled="exportModal.busy"
          >
            {{ t("ibReport_exportCancel", "Cancel") }}
          </button>
        </div>
      </div>
    </div>

    <div class="ib-nc-header">
      <h3 class="ib-nc-title">
        <i class="fas fa-users"></i>
        {{ t("ibNetClients_title", "Network Clients") }}
        <span class="ib-nc-count" v-if="!loading">{{ total }}</span>
      </h3>
      <button
        type="button"
        class="ib-nc-export-btn"
        :disabled="loading || exportPolling || total === 0"
        @click="exportAll"
      >
        <i
          :class="exportPolling ? 'fas fa-spinner fa-spin' : 'fas fa-download'"
        ></i>
        {{ t("ibNetClients_export", "Export") }}
      </button>
    </div>

    <div class="ib-nc-table-wrapper">
      <table class="ib-nc-table">
        <thead>
          <tr>
            <th>{{ t("ibNetClients_thId", "Client ID") }}</th>
            <th>{{ t("ibNetClients_thName", "Name") }}</th>
            <th>{{ t("ibNetClients_thEmail", "Email") }}</th>
            <th>{{ t("ibNetClients_thPhone", "Phone") }}</th>
            <th>{{ t("ibNetClients_thWallet", "Wallet Balance") }}</th>
            <th>{{ t("ibNetClients_thIsIb", "Is IB") }}</th>
            <th>{{ t("ibNetClients_thKyc", "KYC") }}</th>
            <th>{{ t("ibNetClients_thStatus", "Status") }}</th>
            <th>{{ t("ibNetClients_thUpline", "Upline IB") }}</th>
            <th>{{ t("ibNetClients_thCountry", "Country") }}</th>
            <th>{{ t("ibNetClients_thRegistered", "Registered") }}</th>
            <th>{{ t("ibNetClients_thAction", "Action") }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="12" class="ib-nc-empty">
              <i class="fas fa-spinner fa-spin"></i>
              {{ t("common_loading", "Loading...") }}
            </td>
          </tr>
          <tr v-else-if="!items.length">
            <td colspan="12" class="ib-nc-empty">
              {{ t("ibNetClients_empty", "No clients found under this IB.") }}
            </td>
          </tr>
          <tr v-else v-for="row in items" :key="row.clientId">
            <td>{{ row.clientId }}</td>
            <td>{{ fullName(row) }}</td>
            <td>{{ row.email || "--" }}</td>
            <td>{{ phoneText(row) }}</td>
            <td>{{ formatCurrency(row.walletBalance || 0) }}</td>
            <td>
              <span
                :class="[
                  'ib-nc-badge',
                  Number(row.isIb) === 1
                    ? 'ib-nc-badge--yes'
                    : 'ib-nc-badge--no',
                ]"
              >
                {{
                  Number(row.isIb) === 1
                    ? t("ibNetClients_yes", "Yes")
                    : t("ibNetClients_no", "No")
                }}
              </span>
            </td>
            <td>
              <span :class="['ib-nc-badge', kycClass(row.kycStatus)]">{{
                row.kycStatus || "--"
              }}</span>
            </td>
            <td>
              <span :class="['ib-nc-badge', statusClass(row.clientStatus)]">{{
                row.clientStatus || "--"
              }}</span>
            </td>
            <td>{{ row.parentIbCode || "--" }}</td>
            <td>{{ row.country || "--" }}</td>
            <td>{{ formatDate(row.clientRegistrationDate) }}</td>
            <td>
              <button class="ib-nc-view-btn" @click="goToClient(row.clientId)">
                <i class="fas fa-external-link-alt"></i>
                {{ t("ibNetClients_view", "View") }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="ib-nc-pagination" v-if="totalPages > 1">
      <button
        class="ib-nc-page-btn"
        :disabled="page <= 1 || loading"
        @click="changePage(page - 1)"
      >
        <i class="fas fa-chevron-left"></i>
      </button>
      <span class="ib-nc-page-info">{{ page }} / {{ totalPages }}</span>
      <button
        class="ib-nc-page-btn"
        :disabled="page >= totalPages || loading"
        @click="changePage(page + 1)"
      >
        <i class="fas fa-chevron-right"></i>
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from "vue";
import { useRouter } from "vue-router";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { useAsyncReportExport } from "@/composables/useAsyncReportExport";
import { clientService } from "@/services/clientListService";
import { formatCurrency } from "@/utils/helpers";
import ExportProgressBanner from "@/components/common/ExportProgressBanner.vue";

const { t } = useAdminI18n();
const router = useRouter();

const props = defineProps({
  ibPartnerId: {
    type: [Number, String],
    default: null,
  },
});

const items = ref([]);
const total = ref(0);
const page = ref(1);
const perPage = ref(10);
const loading = ref(false);

const {
  exportPolling,
  exportJobId,
  exportStatusText,
  exportBannerVisible,
  exportCancelling,
  exportModal,
  lastExportProgress,
  startOrResumeExport,
  resumeActiveExportIfAny,
  cancelActiveExport,
  onExportModalContinue,
  onExportModalCancel,
} = useAsyncReportExport({
  getActiveExport: () => clientService.getActiveIbNetworkClientsExport(),
  enqueueExport: (params) =>
    clientService.enqueueIbNetworkClientsExport(props.ibPartnerId, params),
  getExportStatus: (jobId) =>
    clientService.getIbNetworkClientsExportStatus(jobId),
  cancelExport: (jobId) => clientService.cancelIbNetworkClientsExport(jobId),
  downloadExport: (jobId) =>
    clientService.downloadIbNetworkClientsExport(jobId),
  buildFilename: () =>
    `network_clients_${props.ibPartnerId}_${new Date().toISOString().split("T")[0]}.csv`,
  t,
});

const exportProgressPercent = computed(() =>
  Math.max(0, Math.min(100, Number(lastExportProgress.value?.percent || 0))),
);

const totalPages = computed(() =>
  perPage.value > 0 ? Math.ceil(total.value / perPage.value) : 1,
);

const fullName = (row) => {
  const name = `${row.firstName || ""} ${row.lastName || ""}`.trim();
  return name || row.email || "--";
};

const phoneText = (row) => {
  const phone = (row.phone || "").trim();
  if (!phone) return "--";
  const code = (row.phoneCountryCode || "").trim();
  return code ? `${code} ${phone}` : phone;
};

// KYC / 状态徽章配色：只做展示，未知值回退到中性样式
const kycClass = (status) => {
  const s = (status || "").toLowerCase();
  if (s === "approved") return "ib-nc-badge--yes";
  if (s === "rejected" || s === "expired") return "ib-nc-badge--danger";
  if (s === "pending" || s === "under_review") return "ib-nc-badge--warn";
  return "ib-nc-badge--no";
};

const statusClass = (status) => {
  const s = (status || "").toLowerCase();
  if (s === "active") return "ib-nc-badge--yes";
  if (s === "inactive" || s === "suspended" || s === "banned")
    return "ib-nc-badge--danger";
  return "ib-nc-badge--no";
};

const formatDate = (val) => {
  if (!val) return "--";
  const d = new Date(String(val).replace(" ", "T"));
  if (isNaN(d.getTime())) return val;
  const mm = String(d.getMonth() + 1).padStart(2, "0");
  const dd = String(d.getDate()).padStart(2, "0");
  return `${d.getFullYear()}-${mm}-${dd}`;
};

const goToClient = (clientId) => {
  if (!clientId) return;
  router.push({ name: "client-detail", query: { id: clientId } });
};

const load = async () => {
  const ibId = props.ibPartnerId;
  if (ibId === null || ibId === undefined || ibId === "") {
    items.value = [];
    total.value = 0;
    return;
  }
  loading.value = true;
  try {
    const response = await clientService.getIbNetworkClients(ibId, {
      page: page.value,
      per_page: perPage.value,
    });
    const data = response?.data || {};
    items.value = Array.isArray(data.items) ? data.items : [];
    total.value = data.pagination?.total ?? items.value.length;
  } catch (error) {
    console.error("Failed to load IB network clients:", error);
    items.value = [];
    total.value = 0;
  } finally {
    loading.value = false;
  }
};

const changePage = (next) => {
  if (next < 1 || next > totalPages.value) return;
  page.value = next;
  load();
};

const exportAll = async () => {
  if (
    !props.ibPartnerId ||
    exportPolling.value ||
    loading.value ||
    total.value === 0
  )
    return;
  await startOrResumeExport(() => ({}));
};

watch(
  () => props.ibPartnerId,
  async (ibId) => {
    page.value = 1;
    load();
    if (ibId !== null && ibId !== undefined && ibId !== "") {
      await resumeActiveExportIfAny();
    }
  },
  { immediate: true },
);
</script>

<style scoped>
.ib-network-clients {
  margin-top: 24px;
}
.ib-nc-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 12px;
}
.ib-nc-export-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  font-size: 13px;
  color: var(--color-info);
  background: var(--color-info-soft);
  border: 1px solid #bfdbfe;
  border-radius: var(--radius-sm);
  cursor: pointer;
  white-space: nowrap;
}
.ib-nc-export-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.ib-nc-title {
  font-size: 16px;
  font-weight: 600;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
}
.ib-nc-title i {
  color: var(--color-info);
}
.ib-nc-count {
  background: var(--color-info-soft);
  color: var(--color-info);
  font-size: 12px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: var(--radius-md);
}
.ib-nc-table-wrapper {
  overflow-x: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
}
.ib-nc-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.ib-nc-table th,
.ib-nc-table td {
  padding: 10px 12px;
  text-align: left;
  border-bottom: 1px solid #f1f5f9;
  white-space: nowrap;
}
.ib-nc-table th {
  background: var(--color-surface-soft);
  color: var(--color-muted);
  font-weight: 600;
  text-transform: uppercase;
  font-size: 11px;
}
.ib-nc-table tbody tr:hover {
  background: var(--color-surface-soft);
}
.ib-nc-empty {
  text-align: center;
  color: var(--color-faint);
  padding: 24px;
}
.ib-nc-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: var(--radius-md);
  font-size: 11px;
  font-weight: 600;
  text-transform: capitalize;
}
.ib-nc-badge--yes {
  background: var(--color-success-soft);
  color: var(--color-success);
}
.ib-nc-badge--no {
  background: var(--color-surface-soft);
  color: var(--color-muted);
}
.ib-nc-badge--warn {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}
.ib-nc-badge--danger {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}
.ib-nc-view-btn {
  border: 1px solid #2563eb;
  color: var(--color-info);
  background: var(--color-surface);
  padding: 4px 10px;
  border-radius: var(--radius-sm);
  font-size: 12px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.ib-nc-view-btn:hover {
  background: #2563eb;
  color: #fff;
}
.ib-nc-pagination {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 12px;
}
.ib-nc-page-btn {
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  width: 32px;
  height: 32px;
  border-radius: var(--radius-sm);
  cursor: pointer;
  color: var(--color-text);
}
.ib-nc-page-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.ib-nc-page-info {
  font-size: 13px;
  color: var(--color-muted);
}

.export-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  z-index: 2000;
}

.export-modal {
  width: min(100%, 480px);
  background: var(--color-surface);
  border-radius: 18px;
  box-shadow: 0 25px 60px rgba(15, 23, 42, 0.2);
  overflow: hidden;
}

.export-modal-header,
.export-modal-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 22px;
}

.export-modal-header {
  border-bottom: 1px solid var(--color-border);
}

.export-modal-header h3 {
  margin: 0;
  font-size: 16px;
}

.export-modal-close {
  border: none;
  background: transparent;
  font-size: 22px;
  cursor: pointer;
  line-height: 1;
}

.export-modal-body {
  padding: 18px 22px;
}

.export-modal-text {
  margin: 0 0 16px;
  color: var(--color-text);
}

.export-modal-progress {
  height: 8px;
  background: var(--color-border);
  border-radius: 999px;
  overflow: hidden;
}

.export-modal-progress-bar {
  height: 100%;
  background: #2563eb;
}

.export-modal-percent {
  margin: 8px 0 0;
  font-size: 13px;
  color: var(--color-muted);
}

.export-modal-footer {
  border-top: 1px solid var(--color-border);
  gap: 8px;
  justify-content: flex-end;
}

.export-modal-btn {
  border-radius: var(--radius-md);
  padding: 8px 14px;
  cursor: pointer;
  font-weight: 600;
}

.export-modal-btn.secondary {
  background: var(--color-surface);
  border: 1px solid #cbd5e1;
  color: var(--color-text);
}

.export-modal-btn.primary {
  background: #2563eb;
  border: 1px solid #2563eb;
  color: #fff;
}

.export-modal-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
