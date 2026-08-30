<template>
  <div class="container">
    <!-- Page Header -->
    <div class="page-header">
      <div class="page-title">
        <h1>{{ t("page_ibPartners_title") }}</h1>
        <p>Manage Introducing Brokers, commissions, and partner network</p>
      </div>
      <div class="page-actions">
        <PageHeaderActions />
      </div>
    </div>

    <!-- Statistics Header -->
    <div class="stats-header">
      <div>
        <h2>All Introducing Brokers</h2>
        <p>Overview of your partner network and commission structure</p>
      </div>
      <div class="page-stats">
        <div class="stat-badge">
          <i class="fas fa-handshake"></i>
          <span>{{ formatNumber(statistics.totalIbPartners) }} Total IBs</span>
        </div>
        <div
          class="stat-badge"
          style="
            background: var(--color-success-soft);
            color: var(--color-success);
          "
        >
          <i class="fas fa-check-circle"></i>
          <span>{{ formatNumber(statistics.activeIbPartners) }} Active</span>
        </div>
        <div
          class="stat-badge"
          style="
            background: var(--color-warning-soft);
            color: var(--color-warning);
          "
        >
          <i class="fas fa-clock"></i>
          <span>{{ formatNumber(statistics.pendingIbPartners) }} Pending</span>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <i
        class="fas fa-spinner fa-spin"
        style="font-size: 32px; color: var(--color-brand)"
      ></i>
      <p style="margin-top: 15px; color: var(--color-muted)">
        Loading IB partners...
      </p>
    </div>

    <!-- IB Table -->
    <div v-else class="ib-table-container">
      <div class="table-header">
        <div class="table-header-left">
          <h2>Introducing Brokers</h2>
        </div>
        <div class="table-header-right">
          <button class="btn btn-success" @click="openNewIBModal">
            <i class="fas fa-plus"></i> New IB
          </button>
          <div class="rows-selector">
            <label>Show Rows:</label>
            <select v-model="perPage" @change="loadIbPartners">
              <option :value="5">5 rows</option>
              <option :value="10">10 rows</option>
              <option :value="20">20 rows</option>
              <option value="all">All</option>
            </select>
          </div>
        </div>
      </div>

      <table class="ib-table">
        <thead>
          <tr>
            <th>IB Name</th>
            <th>Total Clients</th>
            <th>Commission Rules</th>
            <th>Total Commission</th>
            <th>Registration Date</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="ib in ibPartners" :key="ib.id">
            <tr
              :data-ib-id="ib.id"
              :class="{ expanded: expandedRows.includes(ib.id) }"
            >
              <td>
                <div class="ib-info">
                  <div class="ib-name">{{ ib.companyName }}</div>
                  <div class="ib-code">{{ ib.ibCode }}</div>
                </div>
              </td>
              <td>{{ formatNumber(ib.totalClients) }} Clients</td>
              <td>
                <div
                  v-if="ib.assignedRulesCount > 0"
                  style="display: flex; flex-direction: column; gap: 4px"
                >
                  <span class="commission-badge">{{
                    getRuleTypeBadge(ib)
                  }}</span>
                  <span
                    v-if="ib.assignedRulesCount > 1"
                    class="commission-badge"
                    style="
                      background: var(--color-brand-solid);
                      font-size: 14px;
                    "
                  >
                    + {{ formatNumber(ib.assignedRulesCount - 1) }} more
                  </span>
                </div>
                <div
                  v-else
                  style="display: flex; align-items: center; gap: 4px"
                >
                  <span
                    class="commission-badge"
                    style="background: var(--color-faint); font-size: 14px"
                    >No Rule</span
                  >
                </div>
              </td>
              <td style="color: var(--color-success); font-weight: 700">
                {{ formatCurrency(ib.totalCommissionEarned) }}
              </td>
              <td>{{ formatDate(ib.registrationDate) }}</td>
              <td>
                <span class="status-badge" :class="ibStatusClass(ib.status)">{{
                  formatIbStatus(ib.status)
                }}</span>
              </td>
              <td>
                <button
                  class="btn-action btn-detail"
                  @click="toggleDetail(ib.id)"
                >
                  <i
                    class="fas"
                    :class="
                      expandedRows.includes(ib.id)
                        ? 'fa-chevron-up'
                        : 'fa-chevron-down'
                    "
                  ></i>
                  {{ expandedRows.includes(ib.id) ? "Hide" : "Detail" }}
                </button>
              </td>
            </tr>
            <!-- Detail Row -->
            <tr v-if="expandedRows.includes(ib.id)" class="detail-row">
              <td colspan="7">
                <IbPartnerDetail
                  :ib-partner="ib"
                  :tier-levels="tierLevels"
                  :commission-rules="commissionRules"
                  :commission-rules-details="commissionRulesDetails"
                  :securities="securities"
                  :symbols="symbols"
                  :saving="savingIbId === ib.id"
                  @save="saveIbChanges"
                  @refresh="loadIbPartners"
                />
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <!-- New IB Invitation Modal -->
    <NewIbInvitationModal
      v-if="showNewIBModal"
      :documents="documentTemplates"
      @close="closeNewIBModal"
      @send="handleInvitationSent"
    />
  </div>
</template>

<script setup>
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";

import { ref, computed, onMounted } from "vue";
import IbPartnerDetail from "@/components/ib/IbPartnerDetail.vue";
import NewIbInvitationModal from "@/components/ib/NewIbInvitationModal.vue";
import ibPartnersApi from "@/services/ibPartnersApi";
import ibTierLevelsApi from "@/services/ibTierLevelsApi";
import ibRulesApi from "@/services/ibRulesApi";
import ibSettingsApi from "@/services/ibSettingsApi";
import { formatCurrency, formatNumber } from "@/utils/helpers";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t } = useAdminI18n();

const loading = ref(true);
const ibPartners = ref([]);
const expandedRows = ref([]);
const perPage = ref(10);
const showNewIBModal = ref(false);
const savingIbId = ref(null);
const tierLevels = ref([]);
const commissionRules = ref([]);
const commissionRulesDetails = ref({});
const securities = ref([]);
const symbols = ref([]);
const documentTemplates = ref([]);

const statistics = computed(() => {
  if (!ibPartners.value.length) {
    return {
      totalIbPartners: 0,
      activeIbPartners: 0,
      pendingIbPartners: 0,
    };
  }

  const pendingStatuses = [
    "pending_initial_review",
    "pending_risk_review",
    "pending_final_review",
  ];
  return {
    totalIbPartners: ibPartners.value.length,
    activeIbPartners: ibPartners.value.filter((ib) => ib.status === "approved")
      .length,
    pendingIbPartners: ibPartners.value.filter((ib) =>
      pendingStatuses.includes(ib.status),
    ).length,
  };
});

/** 数据库 status 小写+下划线 → 页面显示首字母大写+空格 */
const formatIbStatus = (status) => {
  if (!status || typeof status !== "string") return "—";
  const map = {
    pending_initial_review: "Pending Initial Review",
    pending_risk_review: "Pending Risk Review",
    pending_final_review: "Pending Final Review",
    approved: "Approved",
    rejected: "Rejected",
  };
  return (
    map[status] ||
    status.replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase())
  );
};

/** status 用于 CSS class：approved / rejected / pending */
const ibStatusClass = (status) => {
  if (!status) return "";
  if (status === "approved") return "approved";
  if (status === "rejected") return "rejected";
  if (
    [
      "pending_initial_review",
      "pending_risk_review",
      "pending_final_review",
    ].includes(status)
  )
    return "pending";
  return status;
};

/**
 * 获取规则类型徽章文本
 */
const getRuleTypeBadge = (ib) => {
  if (!ib.assignedRuleNames) return "No Rule";
  const firstRule = ib.assignedRuleNames.split(",")[0]?.trim();
  return firstRule || "No Rule";
};

/**
 * 格式化日期
 */
const formatDate = (dateString) => {
  if (!dateString) return "N/A";
  const date = new Date(dateString);
  return date.toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
};

/**
 * 切换详情行
 */
const toggleDetail = (ibId) => {
  const index = expandedRows.value.indexOf(ibId);
  if (index > -1) {
    expandedRows.value.splice(index, 1);
  } else {
    // 关闭其他详情行
    expandedRows.value = [ibId];
  }
};

/**
 * 打开新建IB模态框
 */
const openNewIBModal = () => {
  showNewIBModal.value = true;
};

/**
 * 关闭新建IB模态框
 */
const closeNewIBModal = () => {
  showNewIBModal.value = false;
};

/**
 * 处理邀请发送成功
 */
const handleInvitationSent = () => {
  closeNewIBModal();
  alert(
    "✓ IB Invitation Sent Successfully!\n\nThe client will receive an email invitation with the selected documents to review and digitally sign before becoming an IB.",
  );
};

/**
 * 保存IB变更
 */
const saveIbChanges = async ({ ibId, type, data }) => {
  try {
    savingIbId.value = ibId;

    let response;
    if (type === "basic-info") {
      response = await ibPartnersApi.updateIbPartner(ibId, data);
    } else if (type === "assign-rule") {
      response = await ibPartnersApi.assignRule(ibId, data.ruleId);
    } else if (type === "remove-rule") {
      response = await ibPartnersApi.removeRule(ibId, data.ruleId);
    } else if (type === "save-rules") {
      // 保存完整的规则配置（包括 Payment Settings, Products, Additional Rules）
      response = await ibPartnersApi.saveRules(ibId, data);
    }

    if (response && response.success) {
      // 刷新列表
      await loadIbPartners();
    } else {
      alert(
        "Failed to save changes: " + (response?.message || "Unknown error"),
      );
    }
  } catch (error) {
    console.error("Failed to save IB changes:", error);
    alert("Failed to save changes. Please try again.");
  } finally {
    savingIbId.value = null;
  }
};

/**
 * 加载IB合作伙伴列表
 */
const loadIbPartners = async () => {
  try {
    loading.value = true;

    const params = {
      page: 1,
      per_page: perPage.value === "all" ? 1000 : perPage.value,
    };

    const response = await ibPartnersApi.getIbPartners(params);

    if (response.success && response.data) {
      ibPartners.value = response.data.items || response.data;
    }
  } catch (error) {
    console.error("Failed to load IB partners:", error);
    alert("Failed to load IB partners. Please try again.");
  } finally {
    loading.value = false;
  }
};

/**
 * 加载层级列表
 */
const loadTierLevels = async () => {
  try {
    const response = await ibTierLevelsApi.getActiveTiers();
    if (response.success && response.data) {
      tierLevels.value = response.data;
    }
  } catch (error) {
    console.error("Failed to load tier levels:", error);
  }
};

/**
 * 加载佣金规则列表
 */
const loadCommissionRules = async () => {
  try {
    const response = await ibRulesApi.getActiveRules();
    if (response.success && response.data) {
      commissionRules.value = response.data;

      // 加载所有规则的完整详情（包括products和additionalRules）
      await loadCommissionRulesDetails();
    }
  } catch (error) {
    console.error("Failed to load commission rules:", error);
  }
};

/**
 * 加载所有规则的完整详情（包括products和additionalRules）
 */
const loadCommissionRulesDetails = async () => {
  try {
    // 并行加载所有规则的详情
    const detailPromises = commissionRules.value.map(async (rule) => {
      try {
        const response = await ibRulesApi.getRule(rule.id);
        if (response.success && response.data) {
          return { ruleId: rule.id, details: response.data };
        }
      } catch (error) {
        console.error(`Failed to load details for rule ${rule.id}:`, error);
      }
      return null;
    });

    const details = await Promise.all(detailPromises);

    // 将详情存储到对象中，以ruleId为key
    details.forEach((item) => {
      if (item) {
        commissionRulesDetails.value[item.ruleId] = item.details;
      }
    });
  } catch (error) {
    console.error("Failed to load commission rules details:", error);
  }
};

/**
 * 加载 Securities 和 Symbols
 */
const loadSecuritiesAndSymbols = async () => {
  try {
    const [securitiesRes, symbolsRes] = await Promise.all([
      ibSettingsApi.getCustomSecurities(),
      ibSettingsApi.getCustomSymbols(),
    ]);

    if (securitiesRes.success && securitiesRes.data) {
      securities.value = securitiesRes.data;
    }
    if (symbolsRes.success && symbolsRes.data) {
      symbols.value = symbolsRes.data;
    }
  } catch (error) {
    console.error("Failed to load securities and symbols:", error);
  }
};

/**
 * 加载文档模板
 */
const loadDocumentTemplates = async () => {
  try {
    const response = await ibSettingsApi.getDocuments(true);
    if (response.success && response.data) {
      documentTemplates.value = response.data;
    }
  } catch (error) {
    console.error("Failed to load document templates:", error);
  }
};

onMounted(async () => {
  await Promise.all([
    loadIbPartners(),
    loadTierLevels(),
    loadCommissionRules(),
    loadSecuritiesAndSymbols(),
    loadDocumentTemplates(),
  ]);
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

.stats-header h2 {
  font-size: 20px;
  color: var(--color-ink);
  margin-bottom: 5px;
}

.stats-header p {
  font-size: 14px;
  color: var(--color-muted);
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

.ib-table-container {
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

.rows-selector {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  color: var(--color-text);
}

.rows-selector label {
  font-weight: 600;
}

.rows-selector select {
  padding: 8px 12px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  font-size: 14px;
  cursor: pointer;
  transition: all 0.3s ease;
}

.rows-selector select:hover {
  border-color: var(--color-brand);
}

.ib-table {
  width: 100%;
  border-collapse: collapse;
}

.ib-table thead {
  background: var(--color-surface-soft);
}

.ib-table th {
  padding: 16px 20px;
  text-align: left;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 2px solid var(--color-border);
}

.ib-table tbody tr {
  border-bottom: 1px solid var(--color-border);
  transition: all 0.2s ease;
}

.ib-table tbody tr:hover {
  background: var(--color-surface-soft);
}

.ib-table tbody tr.expanded {
  background: var(--color-brand-soft);
}

.ib-table td {
  padding: 16px 20px;
  font-size: 14px;
  color: var(--color-text);
}

.ib-info {
  display: flex;
  flex-direction: column;
}

.ib-name {
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 4px;
  font-size: 15px;
}

.ib-code {
  font-size: 14px;
  color: var(--color-muted);
  font-family: "Courier New", monospace;
}

.commission-badge {
  background: var(--color-brand-solid);
  color: white;
  padding: 6px 12px;
  border-radius: var(--radius-lg);
  font-size: 14px;
  font-weight: 700;
  display: inline-block;
}

.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: var(--radius-lg);
  font-size: 14px;
  font-weight: 600;
  text-transform: uppercase;
}

.status-badge.active,
.status-badge.approved {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.status-badge.inactive,
.status-badge.rejected {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.status-badge.pending {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.btn-action {
  padding: 8px 16px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 14px;
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

.detail-row {
  background: var(--color-surface-soft);
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

  .table-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 15px;
  }

  .table-header-left,
  .table-header-right {
    width: 100%;
  }

  .table-header-right {
    justify-content: space-between;
  }
}
</style>
