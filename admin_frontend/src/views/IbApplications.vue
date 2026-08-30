<template>
  <div class="container">
    <!-- Page Header -->
    <div class="page-header">
      <div class="page-title">
        <h1>{{ t("page_ibApplications_title") }}</h1>
        <p>Review and approve IB partnership applications</p>
      </div>
      <div class="page-actions">
        <PageHeaderActions />
      </div>
    </div>

    <!-- Statistics Header -->
    <div class="stats-header">
      <div>
        <h2>All IB Applications</h2>
        <p>Review and manage IB partnership requests</p>
      </div>
      <div class="page-stats">
        <div class="stat-badge">
          <i class="fas fa-file-alt"></i>
          <span>{{ statistics.totalApplications }} Total Applications</span>
        </div>
        <div
          class="stat-badge"
          style="
            background: var(--color-warning-soft);
            color: var(--color-warning);
          "
        >
          <i class="fas fa-clock"></i>
          <span>{{ statistics.pendingCount }} Pending Review</span>
        </div>
        <div
          class="stat-badge"
          style="
            background: var(--color-success-soft);
            color: var(--color-success);
          "
        >
          <i class="fas fa-check-circle"></i>
          <span>{{ statistics.approvedToday }} Approved Today</span>
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
        Loading applications...
      </p>
    </div>

    <!-- Applications Table -->
    <div v-else class="ib-table-container">
      <div class="table-header">
        <div class="table-header-left">
          <h2>IB Applications</h2>
          <div
            class="bulk-actions"
            :class="{ show: selectedApplications.length > 0 }"
          >
            <span class="bulk-actions-label">Selected:</span>
            <span class="bulk-actions-count">{{
              selectedApplications.length
            }}</span>
            <button
              class="btn-bulk btn-bulk-assign"
              @click="openBulkAssignModal"
            >
              <i class="fas fa-user-check"></i> Assign Reviewer
            </button>
            <button
              class="btn-bulk btn-bulk-approve"
              @click="openBulkApproveModal"
            >
              <i class="fas fa-check-double"></i> Approve All
            </button>
            <button
              class="btn-bulk btn-bulk-export"
              @click="toggleExportDropdown"
            >
              <i class="fas fa-download"></i> Export
              <div
                class="export-dropdown"
                :class="{ show: showExportDropdown }"
                @click.stop
              >
                <div class="export-option" @click="exportApplications('csv')">
                  <i class="fas fa-file-csv"></i>
                  <span>Export as CSV</span>
                </div>
                <div class="export-option" @click="exportApplications('excel')">
                  <i class="fas fa-file-excel"></i>
                  <span>Export as Excel</span>
                </div>
              </div>
            </button>
          </div>
        </div>
        <div class="table-header-right">
          <div class="rows-selector">
            <label>Show Rows:</label>
            <select v-model="perPage" @change="loadApplications">
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
            <th class="checkbox-col">
              <label class="custom-checkbox">
                <input
                  type="checkbox"
                  @change="toggleSelectAll"
                  v-model="selectAll"
                />
                <span class="checkbox-checkmark"></span>
              </label>
            </th>
            <th>Applicant</th>
            <th>IB Type</th>
            <!-- <th>Expected Clients</th> -->
            <th>Application Date</th>
            <th>Status</th>
            <th>Operator</th>
            <th>Reviewer</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="app in applications" :key="app.id">
            <tr
              :data-ib-id="app.id"
              :class="{ expanded: expandedRows.includes(app.id) }"
            >
              <td class="checkbox-col">
                <label class="custom-checkbox">
                  <input
                    type="checkbox"
                    :value="app.id"
                    v-model="selectedApplications"
                  />
                  <span class="checkbox-checkmark"></span>
                </label>
              </td>
              <td>
                <div class="applicant-info">
                  <div class="applicant-avatar">
                    {{ getInitials(app.applicantName) }}
                  </div>
                  <div class="applicant-details">
                    <div class="applicant-name">{{ app.applicantName }}</div>
                    <div class="applicant-email">{{ app.applicantEmail }}</div>
                  </div>
                </div>
              </td>
              <td>
                <span class="type-badge" :class="app.ibType">{{
                  app.ibType
                }}</span>
              </td>
              <!-- <td>{{ app.expectedClients || 'N/A' }}</td> -->
              <td>{{ formatDateTime(app.applicationDate) }}</td>
              <td>
                <span class="status-badge" :class="app.applicationStatus">{{
                  formatStatus(app.applicationStatus)
                }}</span>
              </td>
              <td>{{ app.reviewerName || "-" }}</td>
              <td>{{ app.auditorName || "-" }}</td>
              <td>
                <button
                  class="btn-action btn-detail"
                  @click="toggleDetail(app.id)"
                >
                  <i
                    class="fas"
                    :class="
                      expandedRows.includes(app.id)
                        ? 'fa-chevron-up'
                        : 'fa-chevron-down'
                    "
                  ></i>
                  {{ expandedRows.includes(app.id) ? "Hide" : "Detail" }}
                </button>
              </td>
            </tr>
            <!-- Detail Row -->
            <tr v-if="expandedRows.includes(app.id)" class="detail-row">
              <td colspan="8">
                <IbApplicationDetail
                  :application="app"
                  :tier-levels="tierLevels"
                  :commission-rules="commissionRules"
                  :commission-rules-details="commissionRulesDetails"
                  :securities="securities"
                  :symbols="symbols"
                  :saving="savingAppId === app.id"
                  @approve="approveApplication"
                  @reject="rejectApplication"
                  @request-info="requestMoreInfo"
                  @assign-tier="assignTier"
                  @assign-rule="assignRule"
                  @remove-rule="removeRule"
                  @refresh="loadApplications"
                  @refresh-securities-symbols="refreshSecuritiesAndSymbols"
                />
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <!-- Bulk Assign Reviewer Modal -->
    <BulkAssignReviewerModal
      v-if="showBulkAssignModal"
      :visible="showBulkAssignModal"
      :selected-applications="getSelectedApplicationDetails()"
      @close="closeBulkAssignModal"
      @assign="handleBulkAssign"
    />

    <!-- Bulk Approve Modal -->
    <BulkApproveModal
      v-if="showBulkApproveModal"
      :selected-count="selectedApplications.length"
      :tier-levels="tierLevels"
      :commission-rules="commissionRules"
      @close="closeBulkApproveModal"
      @approve="handleBulkApprove"
    />
  </div>
</template>

<script setup>
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";

import { ref, computed, onMounted } from "vue";
import { useAuthStore } from "@/stores/auth";
import IbApplicationDetail from "@/components/ib/IbApplicationDetail.vue";
import BulkAssignReviewerModal from "@/components/ib/BulkAssignReviewerModal.vue";
import BulkApproveModal from "@/components/ib/BulkApproveModal.vue";
import ibApplicationsApi from "@/services/ibApplicationsApi";
import ibTierLevelsApi from "@/services/ibTierLevelsApi";
import ibRulesApi from "@/services/ibRulesApi";
import ibSettingsApi from "@/services/ibSettingsApi";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t } = useAdminI18n();
const loading = ref(true);
const applications = ref([]);
const expandedRows = ref([]);
const selectedApplications = ref([]);
const selectAll = ref(false);
const perPage = ref(10);
const showExportDropdown = ref(false);
const showBulkAssignModal = ref(false);
const showBulkApproveModal = ref(false);
const savingAppId = ref(null);
const tierLevels = ref([]);
const commissionRules = ref([]);
const commissionRulesDetails = ref({}); // { ruleId: { products, additionalRules, ... } }
const securities = ref([]);
const symbols = ref([]);

const statistics = computed(() => {
  if (!applications.value.length) {
    return {
      totalApplications: 0,
      pendingCount: 0,
      approvedToday: 0,
    };
  }

  return {
    totalApplications: applications.value.length,
    pendingCount: applications.value.filter(
      (a) => a.applicationStatus === "pending",
    ).length,
    approvedToday: applications.value.filter((a) => {
      if (a.applicationStatus !== "approved" || !a.reviewCompletedDate)
        return false;
      const today = new Date().toDateString();
      return new Date(a.reviewCompletedDate).toDateString() === today;
    }).length,
  };
});

/**
 * 获取姓名首字母
 */
const getInitials = (name) => {
  if (!name) return "??";
  const parts = name.split(" ");
  if (parts.length >= 2) {
    return (parts[0][0] + parts[1][0]).toUpperCase();
  }
  return name.substring(0, 2).toUpperCase();
};

/**
 * 格式化日期时间
 */
const formatDateTime = (dateString) => {
  if (!dateString) return "N/A";
  const date = new Date(dateString);
  return date.toLocaleString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
};

/**
 * 格式化状态
 */
const formatStatus = (status) => {
  const statusMap = {
    pending: "Pending",
    in_review: "In Review",
    approved: "Approved",
    rejected: "Rejected",
    more_info_requested: "More Info Requested",
  };
  return statusMap[status] || status;
};

/**
 * 切换详情行
 */
const toggleDetail = (appId) => {
  const index = expandedRows.value.indexOf(appId);
  if (index > -1) {
    expandedRows.value.splice(index, 1);
  } else {
    expandedRows.value.push(appId);
  }
};

/**
 * 切换全选
 */
const toggleSelectAll = () => {
  if (selectAll.value) {
    selectedApplications.value = applications.value.map((a) => a.id);
  } else {
    selectedApplications.value = [];
  }
};

/**
 * 切换导出下拉菜单
 */
const toggleExportDropdown = () => {
  showExportDropdown.value = !showExportDropdown.value;
};

/**
 * 导出申请（前端导出）
 */
const exportApplications = async (format) => {
  if (selectedApplications.value.length === 0) {
    alert("⚠️ Please select at least one application to export.");
    return;
  }

  try {
    // 获取选中的申请数据
    const selectedAppsData = applications.value.filter((app) =>
      selectedApplications.value.includes(app.id),
    );

    // 定义导出字段
    const exportColumns = [
      { key: "id", label: "Application ID", getValue: (app) => app.id || "" },
      {
        key: "applicantName",
        label: "Applicant Name",
        getValue: (app) => app.applicantName || "",
      },
      {
        key: "applicantEmail",
        label: "Applicant Email",
        getValue: (app) => app.applicantEmail || "",
      },
      { key: "ibType", label: "IB Type", getValue: (app) => app.ibType || "" },
      {
        key: "expectedClients",
        label: "Expected Clients",
        getValue: (app) => app.expectedClients || "",
      },
      {
        key: "applicationDate",
        label: "Application Date",
        getValue: (app) => formatDateTime(app.applicationDate),
      },
      {
        key: "applicationStatus",
        label: "Status",
        getValue: (app) => formatStatus(app.applicationStatus),
      },
      {
        key: "tierLevel",
        label: "Tier Level",
        getValue: (app) => (app.tierLevel ? `Tier ${app.tierLevel}` : "-"),
      },
      {
        key: "assignedTierName",
        label: "Tier Name",
        getValue: (app) => app.assignedTierName || "-",
      },
      {
        key: "preAssignedRulesCount",
        label: "Rules Count",
        getValue: (app) => app.preAssignedRulesCount || 0,
      },
      {
        key: "reviewerName",
        label: "Reviewer",
        getValue: (app) => app.reviewerName || "-",
      },
      {
        key: "reviewStartDate",
        label: "Review Start Date",
        getValue: (app) =>
          app.reviewStartDate ? formatDateTime(app.reviewStartDate) : "-",
      },
    ];

    // 构建CSV内容
    const headers = exportColumns.map((col) => col.label);
    const rows = selectedAppsData.map((app) =>
      exportColumns.map((col) => {
        const value = col.getValue(app);
        // 处理包含逗号、引号或换行符的值
        if (
          typeof value === "string" &&
          (value.includes(",") || value.includes('"') || value.includes("\n"))
        ) {
          return `"${value.replace(/"/g, '""')}"`;
        }
        return value || "";
      }),
    );

    // 生成CSV内容
    const csvContent = [
      headers.join(","),
      ...rows.map((row) => row.join(",")),
    ].join("\n");

    // 添加BOM以支持Excel正确显示中文
    const BOM = "\uFEFF";
    const blob = new Blob([BOM + csvContent], {
      type:
        format === "csv"
          ? "text/csv;charset=utf-8;"
          : "application/vnd.ms-excel;charset=utf-8;",
    });

    const link = document.createElement("a");
    const url = URL.createObjectURL(blob);
    link.setAttribute("href", url);
    link.setAttribute(
      "download",
      `ib_applications_${new Date().toISOString().split("T")[0]}.${format === "csv" ? "csv" : "xls"}`,
    );
    link.style.visibility = "hidden";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);

    alert(
      `✓ Successfully exported ${selectedApplications.value.length} application(s) as ${format.toUpperCase()}!`,
    );
    selectedApplications.value = [];
  } catch (error) {
    console.error("Failed to export:", error);
    alert("Failed to export applications. Please try again.");
  } finally {
    showExportDropdown.value = false;
  }
};

/**
 * 打开批量分配审核人模态框
 */
const openBulkAssignModal = () => {
  if (selectedApplications.value.length === 0) {
    alert("⚠️ Please select at least one application.");
    return;
  }
  showBulkAssignModal.value = true;
};

/**
 * 关闭批量分配审核人模态框
 */
const closeBulkAssignModal = () => {
  showBulkAssignModal.value = false;
};

/**
 * 获取选中申请的详细信息
 */
const getSelectedApplicationDetails = () => {
  return applications.value
    .filter((app) => selectedApplications.value.includes(app.id))
    .map((app) => ({
      id: app.id,
      applicantName: app.applicantName,
      applicantEmail: app.applicantEmail,
      applicationStatus: app.applicationStatus,
    }));
};

/**
 * 处理批量分配审核人
 */
const handleBulkAssign = async ({ operatorId, reviewerId, applicationIds }) => {
  try {
    const response = await ibApplicationsApi.bulkAssignReviewer(
      applicationIds,
      operatorId,
      reviewerId,
    );

    if (response.success) {
      alert(
        `✓ Successfully assigned ${response.data.successCount} application(s)!`,
      );
      selectedApplications.value = [];
      selectAll.value = false;
      // 关闭所有展开的Detail，确保数据更新
      expandedRows.value = [];
      await loadApplications();
    }
  } catch (error) {
    console.error("Failed to bulk assign:", error);
    const errorMessage =
      error.response?.data?.message ||
      "Failed to assign reviewers. Please try again.";
    alert(errorMessage);
  } finally {
    closeBulkAssignModal();
  }
};

/**
 * 打开批量批准模态框
 */
const openBulkApproveModal = () => {
  if (selectedApplications.value.length === 0) {
    alert("⚠️ Please select at least one application.");
    return;
  }

  // 获取当前登录用户
  const authStore = useAuthStore();
  const currentUserId = authStore.user?.id;
  if (!currentUserId) {
    alert("⚠️ Please login first.");
    return;
  }

  // 检查每个选中的申请
  const selectedApps = applications.value.filter((app) =>
    selectedApplications.value.includes(app.id),
  );

  // 检查状态：必须是 in_review
  const invalidStatusApps = selectedApps.filter(
    (app) => app.applicationStatus !== "in_review",
  );

  if (invalidStatusApps.length > 0) {
    const appNames = invalidStatusApps
      .map((app) => app.applicantName || app.applicantEmail)
      .join(", ");
    const message = `The following application(s) are not in "in_review" status and cannot be approved:\n\n${appNames}\n\nOnly applications with "in_review" status can be approved.`;
    alert(message);
    return;
  }

  // 检查权限：Operator 或 Reviewer 必须是当前登录者
  const invalidPermissionApps = selectedApps.filter((app) => {
    const operatorId = app.reviewerId ? String(app.reviewerId) : null;
    const reviewerId = app.auditorId ? String(app.auditorId) : null;
    const currentUserIdStr = String(currentUserId);

    // 必须是 Operator 或 Reviewer
    return operatorId !== currentUserIdStr && reviewerId !== currentUserIdStr;
  });

  if (invalidPermissionApps.length > 0) {
    const appNames = invalidPermissionApps
      .map((app) => app.applicantName || app.applicantEmail)
      .join(", ");
    const message = `You are not the Operator or Reviewer for the following application(s):\n\n${appNames}\n\nOnly the assigned Operator or Reviewer can approve these applications.`;
    alert(message);
    return;
  }

  showBulkApproveModal.value = true;
};

/**
 * 关闭批量批准模态框
 */
const closeBulkApproveModal = () => {
  showBulkApproveModal.value = false;
};

/**
 * 处理批量批准
 */
const handleBulkApprove = async ({ tierLevelId, ruleIds }) => {
  try {
    const response = await ibApplicationsApi.bulkApprove(
      selectedApplications.value,
      tierLevelId,
      ruleIds,
    );

    if (response.success) {
      alert(
        `✓ Successfully approved ${response.data.successCount} application(s)!`,
      );
      selectedApplications.value = [];
      selectAll.value = false;
      await loadApplications();
    }
  } catch (error) {
    console.error("Failed to bulk approve:", error);
    const errorMessage =
      error.response?.data?.message ||
      "Failed to approve applications. Please try again.";
    alert(errorMessage);
  } finally {
    closeBulkApproveModal();
  }
};

/**
 * 批准申请
 */
const approveApplication = async ({ applicationId, tierLevelId }) => {
  const app = applications.value.find((a) => a.id === applicationId);
  if (!app) return;

  const confirmMessage = `⚠️ Approve this IB application?\n\nApplicant: ${app.applicantName}\nEmail: ${app.applicantEmail}\n\nThis will activate the IB partnership and grant commission access.\n\nAre you sure you want to continue?`;

  if (!confirm(confirmMessage)) return;

  try {
    savingAppId.value = applicationId;

    const response = await ibApplicationsApi.approveApplication(
      applicationId,
      tierLevelId,
    );

    if (response.success) {
      alert(
        `✓ IB Application Approved!\n\nIB Code: ${response.data.ibPartnerId}\n\nThe applicant will be notified and can start earning commissions.`,
      );
      await loadApplications();

      // 关闭详情行
      const index = expandedRows.value.indexOf(applicationId);
      if (index > -1) {
        setTimeout(() => {
          expandedRows.value.splice(index, 1);
        }, 1500);
      }
    }
  } catch (error) {
    console.error("Failed to approve application:", error);
    alert("Failed to approve application. Please try again.");
  } finally {
    savingAppId.value = null;
  }
};

/**
 * 拒绝申请
 */
const rejectApplication = async ({ applicationId, rejectionReason }) => {
  try {
    savingAppId.value = applicationId;

    const response = await ibApplicationsApi.rejectApplication(
      applicationId,
      rejectionReason,
    );

    if (response.success) {
      alert(
        "✓ IB Application Rejected\n\nThe applicant has been notified of the rejection.",
      );
      await loadApplications();

      // 关闭详情行
      const index = expandedRows.value.indexOf(applicationId);
      if (index > -1) {
        expandedRows.value.splice(index, 1);
      }
    }
  } catch (error) {
    console.error("Failed to reject application:", error);
    alert("Failed to reject application. Please try again.");
  } finally {
    savingAppId.value = null;
  }
};

/**
 * 请求更多信息
 */
const requestMoreInfo = async ({ applicationId, infoRequest }) => {
  try {
    savingAppId.value = applicationId;

    const response = await ibApplicationsApi.requestMoreInfo(
      applicationId,
      infoRequest,
    );

    if (response.success) {
      alert(
        "✓ Information Request Sent!\n\nThe applicant will be notified to provide the requested information.",
      );
      await loadApplications();
    }
  } catch (error) {
    console.error("Failed to request info:", error);
    alert("Failed to send information request. Please try again.");
  } finally {
    savingAppId.value = null;
  }
};

/**
 * 分配层级
 */
const assignTier = async ({ applicationId, tierLevelId }) => {
  try {
    const response = await ibApplicationsApi.assignTier(
      applicationId,
      tierLevelId,
    );

    if (response.success) {
      console.log("Tier assigned successfully");
      // 更新本地数据
      const app = applications.value.find((a) => a.id === applicationId);
      if (app) {
        app.assignedTierLevelId = tierLevelId;
        const tier = tierLevels.value.find((t) => t.id === tierLevelId);
        if (tier) {
          app.assignedTierName = tier.tierName;
        }
      }
      // 刷新应用列表以确保数据同步
      await loadApplications();
    }
  } catch (error) {
    console.error("Failed to assign tier:", error);
    alert("Failed to assign tier. Please try again.");
  }
};

/**
 * 分配规则
 */
const assignRule = async ({ applicationId, ruleId }) => {
  try {
    const response = await ibApplicationsApi.assignRule(applicationId, ruleId);

    if (response.success) {
      console.log("Rule assigned successfully");
      // 刷新应用详情
      await loadApplications();
    }
  } catch (error) {
    console.error("Failed to assign rule:", error);
    alert("Failed to assign rule. Please try again.");
  }
};

/**
 * 移除规则
 */
const removeRule = async ({ applicationId, ruleId }) => {
  try {
    const response = await ibApplicationsApi.removeRule(applicationId, ruleId);

    if (response.success) {
      console.log("Rule removed successfully");
      // 刷新应用详情
      await loadApplications();
    }
  } catch (error) {
    console.error("Failed to remove rule:", error);
    alert("Failed to remove rule. Please try again.");
  }
};

/**
 * 加载申请列表
 */
const loadApplications = async () => {
  try {
    loading.value = true;

    const params = {
      page: 1,
      per_page: perPage.value === "all" ? 1000 : perPage.value,
    };

    const response = await ibApplicationsApi.getApplications(params);

    if (response.success && response.data) {
      applications.value = response.data.items || response.data;
    }
  } catch (error) {
    console.error("Failed to load applications:", error);
    alert("Failed to load applications. Please try again.");
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
 * 更新 Securities 和 Symbols（在添加新的 Security 或 Symbol 后调用）
 */
const refreshSecuritiesAndSymbols = async () => {
  await loadSecuritiesAndSymbols();
};

// 点击外部关闭导出下拉菜单
const handleClickOutside = (event) => {
  if (!event.target.closest(".btn-bulk-export")) {
    showExportDropdown.value = false;
  }
};

onMounted(async () => {
  document.addEventListener("click", handleClickOutside);

  await Promise.all([
    loadApplications(),
    loadTierLevels(),
    loadCommissionRules(),
    loadSecuritiesAndSymbols(),
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

.bulk-actions {
  display: none;
  align-items: center;
  gap: 10px;
  padding: 10px 15px;
  background: var(--color-brand-soft);
  border-radius: var(--radius-md);
  border: 2px solid var(--color-brand);
}

.bulk-actions.show {
  display: flex;
}

.bulk-actions-label {
  font-size: 13px;
  color: var(--color-brand);
  font-weight: 600;
}

.bulk-actions-count {
  background: var(--color-brand-solid);
  color: white;
  padding: 2px 8px;
  border-radius: var(--radius-lg);
  font-size: 12px;
  font-weight: 600;
}

.btn-bulk {
  padding: 6px 12px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  position: relative;
}

.btn-bulk-assign {
  background: var(--color-brand-solid);
  color: white;
}

.btn-bulk-assign:hover {
  background: var(--color-brand-strong);
  transform: translateY(-1px);
}

.btn-bulk-approve {
  background: var(--color-success-solid);
  color: white;
}

.btn-bulk-approve:hover {
  background: var(--color-success-solid);
  transform: translateY(-1px);
}

.btn-bulk-export {
  background: var(--color-brand-solid);
  color: white;
}

.btn-bulk-export:hover {
  background: var(--color-brand-strong);
  transform: translateY(-1px);
}

.export-dropdown {
  position: absolute;
  top: calc(100% + 5px);
  left: 0;
  background: var(--color-surface);
  border-radius: var(--radius-md);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  min-width: 150px;
  display: none;
  z-index: 1000;
  overflow: hidden;
  animation: slideDown 0.2s ease;
}

.export-dropdown.show {
  display: block;
}

@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.export-option {
  padding: 10px 15px;
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--color-text);
  cursor: pointer;
  transition: all 0.2s ease;
  font-size: 13px;
  font-weight: 600;
  border-bottom: 1px solid var(--color-border);
}

.export-option:last-child {
  border-bottom: none;
}

.export-option:hover {
  background: var(--color-surface-soft);
  color: var(--color-brand);
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
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 2px solid var(--color-border);
}

.ib-table th.checkbox-col {
  width: 50px;
  text-align: center;
  padding: 16px 10px;
}

.ib-table td.checkbox-col {
  width: 50px;
  text-align: center;
  padding: 16px 10px;
}

.custom-checkbox {
  position: relative;
  display: inline-block;
  width: 20px;
  height: 20px;
  cursor: pointer;
}

.custom-checkbox input[type="checkbox"] {
  position: absolute;
  opacity: 0;
  cursor: pointer;
  width: 20px;
  height: 20px;
  margin: 0;
}

.checkbox-checkmark {
  position: absolute;
  top: 0;
  left: 0;
  height: 20px;
  width: 20px;
  background-color: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: 4px;
  transition: all 0.3s ease;
}

.custom-checkbox:hover .checkbox-checkmark {
  border-color: var(--color-brand);
}

.custom-checkbox input[type="checkbox"]:checked ~ .checkbox-checkmark {
  background: var(--color-brand-solid);
  border-color: var(--color-brand);
}

.checkbox-checkmark:after {
  content: "";
  position: absolute;
  display: none;
  left: 6px;
  top: 2px;
  width: 5px;
  height: 10px;
  border: solid white;
  border-width: 0 2px 2px 0;
  transform: rotate(45deg);
}

.custom-checkbox input[type="checkbox"]:checked ~ .checkbox-checkmark:after {
  display: block;
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

.applicant-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.applicant-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: var(--color-brand-solid);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: 600;
  font-size: 14px;
  flex-shrink: 0;
}

.applicant-details {
  display: flex;
  flex-direction: column;
}

.applicant-name {
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 2px;
}

.applicant-email {
  font-size: 13px;
  color: var(--color-muted);
}

.type-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: var(--radius-lg);
  font-size: 12px;
  font-weight: 600;
}

.type-badge.individual {
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.type-badge.company {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: var(--radius-lg);
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
}

.status-badge.pending {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.status-badge.in_review,
.status-badge.in-review {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.status-badge.approved {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.status-badge.rejected {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.status-badge.more_info_requested {
  background: var(--color-danger-soft);
  color: #9f1239;
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
}
</style>
