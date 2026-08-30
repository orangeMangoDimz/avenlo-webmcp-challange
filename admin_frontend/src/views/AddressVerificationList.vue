<template>
  <div class="kyc-list-page">
    <!-- 页面头部 -->
    <div class="page-header">
      <div class="page-title">
        <h1>{{ t("page_addressVerification_title") }}</h1>
        <p>{{ t("page_addressVerification_sub") }}</p>
      </div>
      <div class="page-actions">
        <PageHeaderActions />
      </div>
    </div>

    <!-- Statistics Header -->
    <div class="stats-header">
      <div>
        <h2
          style="font-size: 20px; color: var(--color-ink); margin-bottom: 5px"
        >
          {{ t("addrVerif_stats_heading") }}
        </h2>
        <p style="font-size: 14px; color: var(--color-muted)">
          {{ t("addrVerif_stats_sub") }}
        </p>
      </div>
      <div class="page-stats">
        <div class="stat-badge">
          <i class="fas fa-file-alt"></i>
          <span>{{
            tParams(
              "addrVerif_stat_totalSubmissions",
              "{n} Total Submissions",
              { n: formatNumber(statistics.total || 0) },
            )
          }}</span>
        </div>
        <div
          class="stat-badge"
          style="
            background: var(--color-warning-soft);
            color: var(--color-warning);
          "
        >
          <i class="fas fa-clock"></i>
          <span>{{
            tParams("addrVerif_stat_pendingReview", "{n} Pending Review", {
              n: formatNumber(statistics.pending || 0),
            })
          }}</span>
        </div>
        <div
          class="stat-badge"
          style="
            background: var(--color-success-soft);
            color: var(--color-success);
          "
        >
          <i class="fas fa-check-circle"></i>
          <span>{{
            tParams("addrVerif_stat_approvedToday", "{n} Approved Today", {
              n: formatNumber(statistics.approved || 0),
            })
          }}</span>
        </div>
      </div>
    </div>

    <!-- KYC表格容器 -->
    <div class="kyc-table-container">
      <!-- 表格头部 -->
      <div class="table-header">
        <div class="table-header-left">
          <h2>{{ t("addrVerif_table_title") }}</h2>

          <!-- 批量操作 -->
          <div
            class="bulk-actions"
            :class="{ show: selectedSubmissions.length > 0 }"
          >
            <span class="bulk-actions-label">{{
              t("addrVerif_bulk_selected")
            }}</span>
            <span class="bulk-actions-count">{{
              selectedSubmissions.length
            }}</span>
            <button
              v-if="hasAssignReviewerPermission"
              class="btn-bulk btn-bulk-assign"
              @click="openBulkAssignModal"
            >
              <i class="fas fa-user-check"></i>
              {{ t("addrVerif_btn_assignReviewer") }}
            </button>
            <button
              v-if="hasApprovePermission"
              class="btn-bulk btn-bulk-approve"
              @click="bulkApprove"
              :disabled="processingBulkApprove"
            >
              <i
                :class="
                  processingBulkApprove
                    ? 'fas fa-spinner fa-spin'
                    : 'fas fa-check-double'
                "
              ></i>
              {{ t("addrVerif_btn_approveAll") }}
            </button>
            <div
              v-if="hasExportPermission"
              class="btn-bulk-export"
              style="position: relative"
            >
              <button class="btn-bulk" @click="toggleExportDropdown">
                <i class="fas fa-download"></i> {{ t("addrVerif_export") }}
              </button>
              <div
                class="export-dropdown"
                :class="{ show: showExportDropdown }"
              >
                <div class="export-option csv" @click="exportData('csv')">
                  <i class="fas fa-file-csv"></i>
                  {{ t("addrVerif_export_csv") }}
                </div>
                <div class="export-option excel" @click="exportData('excel')">
                  <i class="fas fa-file-excel"></i>
                  {{ t("addrVerif_export_excel") }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="table-header-right">
          <div class="rows-selector">
            <label>{{ t("addrVerif_show_label") }}</label>
            <select v-model="perPage" @change="loadSubmissions">
              <option value="10">10</option>
              <option value="25">25</option>
              <option value="50">50</option>
              <option value="100">100</option>
            </select>
          </div>
        </div>
      </div>

      <!-- KYC表格 -->
      <table class="kyc-table">
        <thead>
          <tr>
            <th class="checkbox-col">
              <label class="custom-checkbox">
                <input
                  type="checkbox"
                  v-model="selectAll"
                  @change="toggleSelectAll"
                />
                <span class="checkbox-checkmark"></span>
              </label>
            </th>
            <th>{{ t("addrVerif_col_client") }}</th>
            <th>{{ t("addrVerif_col_paymentMethods") }}</th>
            <th>{{ t("addrVerif_col_submissionDate") }}</th>
            <th>{{ t("addrVerif_col_status") }}</th>
            <th>{{ t("addrVerif_col_reviewer") }}</th>
            <th>{{ t("addrVerif_col_action") }}</th>
          </tr>
        </thead>
        <tbody>
          <template
            v-for="submission in submissions"
            :key="submission.submissionId"
          >
            <!-- 主行 -->
            <tr
              :class="{
                expanded: expandedRows.includes(submission.submissionId),
              }"
            >
              <td class="checkbox-col">
                <label class="custom-checkbox">
                  <input
                    type="checkbox"
                    :value="submission.submissionId"
                    v-model="selectedSubmissions"
                  />
                  <span class="checkbox-checkmark"></span>
                </label>
              </td>
              <td>
                <div class="client-info">
                  <div class="client-avatar">
                    {{ getInitials(submission.clientName) }}
                  </div>
                  <div class="client-details">
                    <div class="client-name">
                      {{ submission.clientName || "-" }}
                    </div>
                    <div class="client-email">{{ submission.clientEmail }}</div>
                  </div>
                </div>
              </td>
              <td>
                <div class="payment-method-cell">
                  <i
                    v-if="submission.iconClass"
                    :class="[submission.iconClass, 'payment-method-icon']"
                  ></i>
                  <span>{{ submission.gatewayName || "-" }}</span>
                </div>
              </td>
              <td>{{ formatDate(submission.submittedAt) }}</td>
              <td>
                <span
                  class="status-badge"
                  :class="getStatusClass(submission.submissionStatus)"
                >
                  {{ formatStatus(submission.submissionStatus) }}
                </span>
              </td>
              <td>
                <span v-if="submission.reviewerName" class="assigned-user">
                  {{ submission.reviewerName }}
                </span>
                <span v-else class="unassigned">-</span>
              </td>
              <td>
                <button
                  class="btn-action btn-detail"
                  @click="toggleRowExpansion(submission.submissionId)"
                >
                  <i
                    class="fas"
                    :class="
                      expandedRows.includes(submission.submissionId)
                        ? 'fa-chevron-up'
                        : 'fa-chevron-down'
                    "
                  ></i>
                  {{
                    expandedRows.includes(submission.submissionId)
                      ? t("addrVerif_btn_hide")
                      : t("addrVerif_btn_detail")
                  }}
                </button>
              </td>
            </tr>

            <!-- 详情行 -->
            <tr
              class="detail-row"
              :class="{ show: expandedRows.includes(submission.submissionId) }"
            >
              <td colspan="7">
                <div
                  class="detail-content"
                  v-if="expandedRows.includes(submission.submissionId)"
                >
                  <AddressVerificationSubmissionDetail
                    :submission="submission"
                    :has-approve-permission="hasApprovePermission"
                    :has-reject-permission="hasRejectPermission"
                    :has-need-more-documents-permission="
                      hasNeedMoreDocumentsPermission
                    "
                    :has-assign-reviewer-permission="
                      hasAssignReviewerPermission
                    "
                    :approving="processingApprove"
                    @approve="handleApprove"
                    @reject="handleReject"
                    @needDocs="handleNeedDocs"
                    @assign="handleAssign"
                  />
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>

      <!-- 分页 -->
      <div class="table-pagination" v-if="totalPages > 1">
        <button
          class="btn-page"
          :disabled="currentPage === 1"
          @click="changePage(currentPage - 1)"
        >
          <i class="fas fa-chevron-left"></i>
        </button>

        <span class="page-info">
          {{
            tParams("addrVerif_page_of", "Page {current} of {total}", {
              current: currentPage,
              total: totalPages,
            })
          }}
        </span>

        <button
          class="btn-page"
          :disabled="currentPage === totalPages"
          @click="changePage(currentPage + 1)"
        >
          <i class="fas fa-chevron-right"></i>
        </button>
      </div>
    </div>

    <!-- 批量分配审核员模态框 -->
    <AddressVerificationBulkAssignModal
      :visible="showBulkAssignModal"
      :selectedSubmissions="getSelectedSubmissionDetails()"
      @close="closeBulkAssignModal"
      @assign="handleBulkAssign"
    />
  </div>
</template>

<script>
import { ref, reactive, onMounted, computed } from "vue";
import { useAuthStore } from "@/stores/auth";
import AddressVerificationSubmissionDetail from "@/components/kyc/AddressVerificationSubmissionDetail.vue";
import AddressVerificationBulkAssignModal from "@/components/kyc/AddressVerificationBulkAssignModal.vue";
import { addressVerificationSubmissionService } from "@/services/addressVerificationService";
import { formatNumber } from "@/utils/helpers";
import { useAdminI18n } from "@/composables/useAdminI18n";
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";
import { recordOperationLog } from "@/services/operationLogReportApi";
import { buildExportLogPayload } from "@/config/operationLogPages";

const authStore = useAuthStore();

export default {
  name: "AddressVerificationList",
  components: {
    PageHeaderActions,
    AddressVerificationSubmissionDetail,
    AddressVerificationBulkAssignModal,
  },
  setup() {
    const { t, tParams, languageStore } = useAdminI18n();
    const dateLocale = () =>
      languageStore.currentLanguage === "zh" ? "zh-CN" : "en-US";
    // 响应式数据
    const submissions = ref([]);
    const statistics = ref({});
    const loading = ref(false);
    const selectedSubmissions = ref([]);
    const expandedRows = ref([]);
    const showExportDropdown = ref(false);
    const showBulkAssignModal = ref(false);
    // 审批进行中标记，防止重复提交
    const processingApprove = ref(false);
    const processingBulkApprove = ref(false);

    // 分页数据
    const currentPage = ref(1);
    const perPage = ref(10);
    const totalItems = ref(0);
    const totalPagesFromApi = ref(null);

    // 权限检查
    const hasReadonlyPermission = computed(() =>
      authStore.hasPermission("page_addressverification_readonly"),
    );
    const hasApprovePermission = computed(() =>
      authStore.hasPermission("page_addressverification_approve"),
    );
    const hasRejectPermission = computed(() =>
      authStore.hasPermission("page_addressverification_reject"),
    );
    const hasNeedMoreDocumentsPermission = computed(() =>
      authStore.hasPermission("page_addressverification_needmoredocuments"),
    );
    const hasExportPermission = computed(() =>
      authStore.hasPermission("page_addressverification_export"),
    );
    const hasAssignReviewerPermission = computed(() =>
      authStore.hasPermission("page_addressverification_assignreviewer"),
    );

    // 计算属性 - 优先使用接口返回的 pagination.total_pages
    const totalPages = computed(() => {
      // 优先使用接口返回的 pagination.total_pages
      if (totalPagesFromApi.value !== null) {
        return totalPagesFromApi.value;
      }
      // 如果没有，则根据 totalItems 和 perPage 计算
      return Math.ceil(totalItems.value / perPage.value);
    });
    const selectAll = computed({
      get: () =>
        selectedSubmissions.value.length === submissions.value.length &&
        submissions.value.length > 0,
      set: (value) => {
        if (value) {
          selectedSubmissions.value = submissions.value.map(
            (s) => s.submissionId,
          );
        } else {
          selectedSubmissions.value = [];
        }
      },
    });

    // 加载提交列表
    const loadSubmissions = async () => {
      try {
        loading.value = true;
        const response = await addressVerificationSubmissionService.getList({
          page: currentPage.value,
          per_page: perPage.value,
        });

        // API拦截器返回response.data，所以response包含success、message、data等字段
        // data字段中包含items和pagination
        if (response && response.data) {
          submissions.value = response.data.items || [];
          totalItems.value = response.data.pagination?.total || 0;
          currentPage.value = response.data.pagination?.page || 1;
          totalPagesFromApi.value =
            response.data.pagination?.total_pages || null;
        } else {
          console.error("Unexpected response structure:", response);
        }
      } catch (error) {
        console.error("Failed to load submissions:", error);
      } finally {
        loading.value = false;
      }
    };

    // 加载统计信息
    const loadStatistics = async () => {
      try {
        const response =
          await addressVerificationSubmissionService.getStatistics();
        statistics.value = response.data;
      } catch (error) {
        console.error("Failed to load statistics:", error);
      }
    };

    // 工具函数
    const getInitials = (clientName) => {
      if (!clientName) return "U";

      const parts = String(clientName).trim().split(/\s+/).filter(Boolean);

      if (parts.length === 0) return "U";
      if (parts.length === 1) return parts[0].slice(0, 1).toUpperCase();

      return `${parts[0][0]}${parts[1][0]}`.toUpperCase();
    };

    const formatStatus = (status) => {
      const keyMap = {
        pending: "addrVerif_status_pending",
        submitted: "addrVerif_status_submitted",
        incomplete: "addrVerif_status_incomplete",
        under_review: "addrVerif_status_under_review",
        approved: "addrVerif_status_approved",
        rejected: "addrVerif_status_rejected",
        resubmit_required: "addrVerif_status_resubmit_required",
        need_docs: "addrVerif_status_need_docs",
        draft: "addrVerif_status_draft",
      };
      const key = keyMap[status];
      return key ? t(key) : status;
    };

    const getStatusClass = (status) => {
      const statusClassMap = {
        pending: "pending",
        submitted: "pending",
        incomplete: "pending",
        under_review: "under_review",
        approved: "approved",
        rejected: "rejected",
        need_docs: "need_docs",
        resubmit_required: "resubmit_required",
      };

      return statusClassMap[status] || "pending";
    };

    const formatDate = (dateString) => {
      if (!dateString) return "-";
      return new Date(dateString).toLocaleDateString(dateLocale(), {
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
      });
    };

    // 行展开/收起
    const toggleRowExpansion = (submissionId) => {
      const index = expandedRows.value.indexOf(submissionId);
      if (index > -1) {
        expandedRows.value.splice(index, 1);
      } else {
        expandedRows.value.push(submissionId);
      }
    };

    // 全选切换
    const toggleSelectAll = () => {
      // 由计算属性处理
    };

    // 分页
    const changePage = (page) => {
      currentPage.value = page;
      loadSubmissions();
    };

    // 导出功能
    const toggleExportDropdown = () => {
      showExportDropdown.value = !showExportDropdown.value;
    };

    const exportData = async (format) => {
      showExportDropdown.value = false;

      if (selectedSubmissions.value.length === 0) {
        alert(t("addrVerif_alert_selectToExport"));
        return;
      }

      try {
        // 显示加载提示
        if (selectedSubmissions.value.length > 5) {
          alert(
            tParams(
              "addrVerif_alert_loadingDetails",
              "Loading details for {n} submissions, please wait...",
              { n: selectedSubmissions.value.length },
            ),
          );
        }

        // 获取选中的 submissions 数据
        const selectedSubmissionsData = submissions.value.filter((submission) =>
          selectedSubmissions.value.includes(submission.submissionId),
        );

        // 为每个 submission 获取详细数据（包含答案）
        const submissionsWithDetails = await Promise.all(
          selectedSubmissionsData.map(async (submission) => {
            try {
              const detailResponse =
                await addressVerificationSubmissionService.getDetail(
                  submission.submissionId,
                );
              return {
                ...submission,
                answers: detailResponse.data?.answers || [],
              };
            } catch (error) {
              console.error(
                `Failed to load details for submission ${submission.submissionId}:`,
                error,
              );
              return {
                ...submission,
                answers: [],
              };
            }
          }),
        );

        // 收集所有唯一的问题（用于创建列）
        const allQuestions = new Map();
        submissionsWithDetails.forEach((submission) => {
          if (submission.answers && Array.isArray(submission.answers)) {
            submission.answers.forEach((category) => {
              const questions = category.answers || [];
              questions.forEach((q) => {
                const questionId = q.questionId;
                const questionText = q.questionText || "";
                if (
                  questionId &&
                  questionText &&
                  !allQuestions.has(questionId)
                ) {
                  allQuestions.set(questionId, questionText);
                }
              });
            });
          }
        });

        // 格式化答案值的辅助函数
        const formatAnswerValue = (answer) => {
          if (!answer) return "-";

          // 根据问题类型获取答案
          const questionType = answer.questionType || "text";
          switch (questionType) {
            case "file_upload":
              if (answer.files && Array.isArray(answer.files)) {
                return answer.files
                  .map((f) =>
                    typeof f === "string"
                      ? f
                      : f.fileName || f.filePath || f.s3Url || f.url || "",
                  )
                  .join("; ");
              }
              if (answer.uploadedFiles && Array.isArray(answer.uploadedFiles)) {
                return answer.uploadedFiles
                  .map((f) =>
                    typeof f === "string"
                      ? f
                      : f.fileName || f.filePath || f.s3Url || f.url || "",
                  )
                  .join("; ");
              }
              if (answer.value && Array.isArray(answer.value)) {
                return answer.value
                  .map((f) =>
                    typeof f === "string"
                      ? f
                      : f.fileName || f.filePath || f.s3Url || f.url || "",
                  )
                  .join("; ");
              }
              return "-";
            case "multiple_choice":
              if (answer.answerValues) {
                try {
                  const values =
                    typeof answer.answerValues === "string"
                      ? JSON.parse(answer.answerValues)
                      : answer.answerValues;
                  return Array.isArray(values)
                    ? values.join(", ")
                    : String(values);
                } catch (e) {
                  return answer.answerValues || "-";
                }
              }
              return answer.value || "-";
            case "date":
              return answer.answerDate || answer.value || "-";
            case "number":
              return answer.answerNumber !== undefined
                ? String(answer.answerNumber)
                : answer.value || "-";
            default:
              return answer.value || answer.answerText || "-";
          }
        };

        // 获取某个 submission 的答案映射
        const getAnswerMap = (submission) => {
          const answerMap = new Map();
          if (submission.answers && Array.isArray(submission.answers)) {
            submission.answers.forEach((category) => {
              const questions = category.answers || [];
              questions.forEach((q) => {
                const questionId = q.questionId;
                if (questionId) {
                  answerMap.set(questionId, formatAnswerValue(q));
                }
              });
            });
          }
          return answerMap;
        };

        // 构建基础列
        const baseColumns = [
          {
            key: "submissionId",
            label: t("addrVerif_export_col_submissionId"),
          },
          { key: "clientName", label: t("addrVerif_export_col_clientName") },
          { key: "clientEmail", label: t("addrVerif_export_col_email") },
          {
            key: "gatewayName",
            label: t("addrVerif_export_col_paymentMethod"),
          },
          {
            key: "submissionDate",
            label: t("addrVerif_export_col_submissionDate"),
          },
          { key: "status", label: t("addrVerif_export_col_status") },
          { key: "reviewer", label: t("addrVerif_export_col_reviewer") },
          { key: "reviewedAt", label: t("addrVerif_export_col_reviewedAt") },
          { key: "progress", label: t("addrVerif_export_col_progress") },
          {
            key: "answeredQuestions",
            label: t("addrVerif_export_col_answeredQuestions"),
          },
          {
            key: "signedDocuments",
            label: t("addrVerif_export_col_signedDocuments"),
          },
        ];

        // 添加问题列（每个问题作为一列）
        const questionColumns = Array.from(allQuestions.entries()).map(
          ([questionId, questionText]) => ({
            key: `question_${questionId}`,
            label:
              questionText.length > 100
                ? questionText.substring(0, 100) + "..."
                : questionText,
            questionId: questionId,
          }),
        );

        // 构建 CSV 头部
        const headers = [
          ...baseColumns.map((col) => col.label),
          ...questionColumns.map((col) => col.label),
        ];

        // 构建数据行（每个submission一行）
        const rows = submissionsWithDetails.map((submission) => {
          const answerMap = getAnswerMap(submission);

          const baseValues = [
            submission.submissionId || "",
            submission.clientName || "",
            submission.clientEmail || "",
            submission.gatewayName || "",
            formatDate(submission.submittedAt),
            formatStatus(submission.submissionStatus),
            submission.reviewerName || "-",
            submission.reviewedAt ? formatDate(submission.reviewedAt) : "-",
            submission.progressPercentage
              ? `${submission.progressPercentage}%`
              : "0%",
            `${submission.answeredQuestions || 0}/${submission.totalQuestions || 0}`,
            `${submission.signedDocuments || 0}/${submission.totalDocuments || 0}`,
          ];

          // 添加每个问题的答案
          const answerValues = questionColumns.map((col) => {
            return answerMap.get(col.questionId) || "-";
          });

          return [...baseValues, ...answerValues];
        });

        // 生成 CSV 内容
        const csvContent = [
          headers.join(","),
          ...rows.map((row) =>
            row
              .map((cell) => {
                const value = String(cell || "");
                // 处理包含逗号、引号或换行符的值
                if (
                  value.includes(",") ||
                  value.includes('"') ||
                  value.includes("\n")
                ) {
                  return `"${value.replace(/"/g, '""')}"`;
                }
                return value;
              })
              .join(","),
          ),
        ].join("\n");

        // 添加 BOM 以支持 Excel 正确显示中文
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
          `address_verification_submissions_${new Date().toISOString().split("T")[0]}.${format === "csv" ? "csv" : "xls"}`,
        );
        link.style.visibility = "hidden";
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);

        alert(
          tParams(
            "addrVerif_alert_exportSuccess",
            "Successfully exported {n} submission(s) with questionnaire data as {format}!",
            {
              n: selectedSubmissions.value.length,
              format: format.toUpperCase(),
            },
          ),
        );

        const fmtLabel = format.toUpperCase();
        recordOperationLog(
          buildExportLogPayload("page_addressverification", {
            detailZh: `导出 ${selectedSubmissions.value.length} 条地址验证提交（${fmtLabel}）`,
            detailEn: `Exported ${selectedSubmissions.value.length} address verification submission(s) (${fmtLabel})`,
          }),
        ).catch(() => {});

        selectedSubmissions.value = [];
      } catch (error) {
        console.error("Export error:", error);
        alert(
          tParams(
            "addrVerif_alert_exportFail",
            "Failed to export submissions: {msg}",
            { msg: error.message || t("common_unknownError") },
          ),
        );
      }
    };

    // 批量操作
    const bulkApprove = async () => {
      if (processingBulkApprove.value) return; // 进行中，避免重复提交
      if (selectedSubmissions.value.length === 0) return;

      processingBulkApprove.value = true;
      try {
        const submissionIds = selectedSubmissions.value;
        await addressVerificationSubmissionService.bulkApprove(submissionIds);

        // 清空选中项
        selectedSubmissions.value = [];

        // 刷新列表和统计信息
        await Promise.all([loadSubmissions(), loadStatistics()]);

        alert(t("addrVerif_alert_bulkApproveOk"));
      } catch (error) {
        console.error("Bulk approve failed:", error);
        const errorMessage =
          error.response?.data?.message || t("addrVerif_alert_bulkApproveFail");
        alert(errorMessage);
      } finally {
        processingBulkApprove.value = false;
      }
    };

    const openBulkAssignModal = () => {
      if (selectedSubmissions.value.length === 0) return;
      showBulkAssignModal.value = true;
    };

    const closeBulkAssignModal = () => {
      showBulkAssignModal.value = false;
    };

    const handleBulkAssign = async (assignmentData) => {
      // 注意：API调用已经在BulkAssignModal组件中完成
      // 这里只需要更新UI状态
      try {
        // 先关闭弹窗
        showBulkAssignModal.value = false;

        // 清空选中项
        selectedSubmissions.value = [];

        // 刷新列表和统计信息
        await Promise.all([loadSubmissions(), loadStatistics()]);

        // 显示成功提示
        alert(t("addrVerif_alert_assignOk"));
      } catch (error) {
        console.error("Failed to update UI after bulk assign:", error);
        alert(t("addrVerif_alert_assignRefreshFail"));
      }
    };

    // 单个操作处理
    const handleApprove = async (submissionId) => {
      if (processingApprove.value) return; // 进行中，避免重复提交
      processingApprove.value = true;
      try {
        await addressVerificationSubmissionService.approve(submissionId);
        await loadSubmissions();
        await loadStatistics();
        alert(t("addrVerif_alert_approveOk"));
      } catch (error) {
        // 之前这里只 console.error，approve 失败时既不提示也不刷新，表现为"点了没反应"
        console.error("Approve failed:", error);
        const errorMessage =
          error.response?.data?.message ||
          t("addrVerif_err_approve", "Failed to approve submission");
        alert(errorMessage);
      } finally {
        processingApprove.value = false;
      }
    };

    const handleReject = async (submissionId, reason) => {
      try {
        await addressVerificationSubmissionService.reject(submissionId, {
          reason,
        });
        await loadSubmissions();
        await loadStatistics();
        alert(t("addrVerif_alert_rejectOk"));
      } catch (error) {
        console.error("Reject failed:", error);
        const errorMessage =
          error.response?.data?.message || t("addrVerif_alert_rejectFail");
        alert(errorMessage);
      }
    };

    const handleNeedDocs = async (submissionId, requirements) => {
      try {
        await addressVerificationSubmissionService.needDocs(submissionId, {
          items: requirements.items,
          notes: requirements.notes,
        });
        alert(t("addrVerif_alert_needDocsOk"));

        // 刷新当前页的数据（列表和统计信息）
        await Promise.all([loadSubmissions(), loadStatistics()]);

        // 注意：由于 Vue 的响应式系统，当 submissions 数组更新后，
        // 传入 AddressVerificationSubmissionDetail 组件的 submission prop 会自动更新，
        // 从而触发组件内的 watch 监听，自动刷新详情内容
      } catch (error) {
        console.error("Need docs failed:", error);
        const errorMessage =
          error.response?.data?.message || t("addrVerif_alert_needDocsFail");
        alert(errorMessage);
      }
    };

    const handleAssign = async (submissionId, reviewerId) => {
      try {
        await addressVerificationSubmissionService.assign(submissionId, {
          reviewerId: reviewerId,
        });
        await loadSubmissions();
        await loadStatistics();
      } catch (error) {
        console.error("Assign failed:", error);
        alert(t("addrVerif_alert_assignReviewerFail"));
      }
    };

    // 获取选中提交的详细信息
    const getSelectedSubmissionDetails = () => {
      return submissions.value
        .filter((submission) =>
          selectedSubmissions.value.includes(submission.submissionId),
        )
        .map((submission) => ({
          id: submission.submissionId,
          clientName: submission.clientName || "-",
          clientEmail: submission.clientEmail,
          templateName: submission.gatewayName || "-",
          submissionStatus: submission.submissionStatus,
        }));
    };

    // 生命周期
    onMounted(() => {
      loadSubmissions();
      loadStatistics();
    });

    return {
      t,
      tParams,
      // 数据
      submissions,
      statistics,
      loading,
      selectedSubmissions,
      expandedRows,
      showExportDropdown,
      processingApprove,
      processingBulkApprove,
      showBulkAssignModal,
      currentPage,
      perPage,
      totalItems,
      totalPagesFromApi,

      // 权限
      hasReadonlyPermission,
      hasApprovePermission,
      hasRejectPermission,
      hasNeedMoreDocumentsPermission,
      hasExportPermission,
      hasAssignReviewerPermission,

      // 计算属性
      totalPages,
      selectAll,

      // 方法
      loadSubmissions,
      getInitials,
      formatStatus,
      getStatusClass,
      formatDate,
      formatNumber,
      toggleRowExpansion,
      toggleSelectAll,
      changePage,
      toggleExportDropdown,
      exportData,
      bulkApprove,
      openBulkAssignModal,
      closeBulkAssignModal,
      handleBulkAssign,
      handleApprove,
      handleReject,
      handleNeedDocs,
      handleAssign,
      getSelectedSubmissionDetails,
    };
  },
};
</script>

<style scoped>
.kyc-list-page {
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

.kyc-table-container {
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
  font-size: 14px;
  color: var(--color-brand);
  font-weight: 600;
}

.bulk-actions-count {
  background: var(--color-brand-solid);
  color: white;
  padding: 2px 8px;
  border-radius: var(--radius-lg);
  font-size: 14px;
  font-weight: 600;
}

.btn-bulk {
  padding: 6px 12px;
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

.btn-bulk-approve {
  background: var(--color-success-solid);
  color: white;
}

.btn-bulk-approve:hover {
  background: var(--color-success-solid);
  transform: translateY(-1px);
}

.btn-bulk-assign {
  background: var(--color-brand-solid);
  color: white;
}

.btn-bulk-assign:hover {
  background: var(--color-brand-strong);
  transform: translateY(-1px);
}

.btn-bulk-export {
  position: relative;
}

.btn-bulk-export .btn-bulk {
  background: var(--color-brand-solid);
  color: white;
}

.btn-bulk-export .btn-bulk:hover {
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

.export-option {
  padding: 10px 15px;
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--color-text);
  cursor: pointer;
  transition: all 0.2s ease;
  font-size: 14px;
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

.export-option i {
  color: var(--color-faint);
  font-size: 14px;
}

.export-option:hover i {
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
  outline: none;
  transition: all 0.3s ease;
}

.rows-selector select:hover {
  border-color: var(--color-brand);
}

.rows-selector select:focus {
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.kyc-table {
  width: 100%;
  border-collapse: collapse;
}

.kyc-table thead {
  background: var(--color-surface-soft);
}

.kyc-table th {
  padding: 16px 20px;
  text-align: left;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 2px solid var(--color-border);
}

.kyc-table th.checkbox-col,
.kyc-table td.checkbox-col {
  width: 50px;
  text-align: center;
  padding: 16px 10px;
}

.custom-checkbox {
  position: relative;
  display: inline-block;
  width: 20px;
  height: 20px;
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

.kyc-table tbody tr {
  border-bottom: 1px solid var(--color-border);
  transition: all 0.2s ease;
}

.kyc-table tbody tr:hover {
  background: var(--color-surface-soft);
}

.kyc-table tbody tr.expanded {
  background: var(--color-brand-soft);
}

.kyc-table td {
  padding: 16px 20px;
  font-size: 14px;
  color: var(--color-text);
}

.client-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.client-avatar {
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

.client-details {
  display: flex;
  flex-direction: column;
}

.client-name {
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 2px;
}

.client-email {
  font-size: 14px;
  color: var(--color-muted);
}

.payment-method-cell {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-weight: 500;
  color: var(--color-ink);
}

.payment-method-icon {
  width: 18px;
  text-align: center;
  color: var(--color-text);
}

.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: var(--radius-lg);
  font-size: 14px;
  font-weight: 600;
  text-transform: uppercase;
}

.status-badge.pending {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.status-badge.submitted {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.status-badge.incomplete {
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.status-badge.under_review {
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

.status-badge.need_docs,
.status-badge.resubmit_required {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.progress-info {
  display: flex;
  align-items: center;
  gap: 10px;
}

.progress-bar {
  width: 80px;
  height: 8px;
  background: var(--color-border);
  border-radius: 4px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: var(--color-brand-solid);
  transition: width 0.3s ease;
}

.progress-text {
  font-size: 14px;
  color: var(--color-muted);
  font-weight: 600;
}

.assigned-user {
  color: var(--color-ink);
  font-weight: 500;
}

.unassigned {
  color: var(--color-faint);
  font-style: italic;
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
  display: none;
}

.detail-row.show {
  display: table-row;
}

.detail-content {
  padding: 30px;
  background: var(--color-surface-soft);
}

.table-pagination {
  padding: 20px 30px;
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 15px;
  border-top: 1px solid var(--color-border);
}

.btn-page {
  padding: 8px 12px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-page:hover:not(:disabled) {
  border-color: var(--color-brand);
  color: var(--color-brand);
}

.btn-page:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.page-info {
  font-size: 14px;
  color: var(--color-text);
  font-weight: 600;
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

@media (max-width: 768px) {
  .kyc-list-page {
    padding: 20px 15px;
  }

  .page-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 20px;
  }

  .page-actions {
    width: 100%;
    justify-content: flex-end;
  }

  .stats-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 15px;
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

  .table-header-left {
    width: 100%;
    flex-direction: column;
    align-items: flex-start;
  }

  .bulk-actions {
    width: 100%;
    justify-content: flex-start;
  }
}
</style>
