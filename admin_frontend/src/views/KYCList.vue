<template>
  <div class="kyc-list-page ui-page workspace-list-page">
    <!-- 页面头部 -->
    <div class="page-header ui-page-header">
      <div class="page-title">
        <h1>{{ t("page_kycList_title") }}</h1>
        <p>{{ t("page_kycList_sub") }}</p>
      </div>
      <div class="page-actions">
        <PageHeaderActions />
      </div>
    </div>

    <!-- Statistics Header -->
    <div class="stats-header ui-summary-band">
      <div>
        <h2
          style="font-size: 20px; color: var(--color-ink); margin-bottom: 5px"
        >
          {{ t("kycList_stats_heading") }}
        </h2>
        <p style="font-size: 14px; color: var(--color-muted)">
          {{ t("kycList_stats_sub") }}
        </p>
      </div>
      <div class="page-stats">
        <div class="stat-badge">
          <i class="fas fa-file-alt"></i>
          <span>{{
            tParams("kycList_stat_totalSubmissions", "{n} Total Submissions", {
              n: formatNumber(statistics.total || 0),
            })
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
            tParams("kycList_stat_pendingReview", "{n} Pending Review", {
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
            tParams("kycList_stat_approvedToday", "{n} Approved Today", {
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
          <h2>{{ t("kycList_table_title") }}</h2>

          <!-- 批量操作 -->
          <div
            class="bulk-actions"
            :class="{ show: selectedSubmissions.length > 0 }"
          >
            <span class="bulk-actions-label">{{
              t("kycList_bulk_selected")
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
              {{ t("kycList_btn_assignReviewer") }}
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
              {{ t("kycList_btn_approveAll") }}
            </button>
            <div
              v-if="hasExportPermission"
              class="btn-bulk-export"
              style="position: relative"
            >
              <button class="btn-bulk" @click="toggleExportDropdown">
                <i class="fas fa-download"></i> {{ t("kycList_export") }}
              </button>
              <div
                class="export-dropdown"
                :class="{ show: showExportDropdown }"
              >
                <div class="export-option csv" @click="exportData('csv')">
                  <i class="fas fa-file-csv"></i> {{ t("kycList_export_csv") }}
                </div>
                <div class="export-option excel" @click="exportData('excel')">
                  <i class="fas fa-file-excel"></i>
                  {{ t("kycList_export_excel") }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="table-header-right">
          <div class="rows-selector">
            <label>{{ t("kycList_show_label") }}</label>
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
            <th>{{ t("kycList_col_client") }}</th>
            <th>{{ t("kycList_col_template") }}</th>
            <th>{{ t("kycList_col_submissionDate") }}</th>
            <th>{{ t("kycList_col_status") }}</th>
            <th>{{ t("kycList_col_reviewer") }}</th>
            <th>{{ t("kycList_col_action") }}</th>
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
                <label
                  class="custom-checkbox"
                  :class="{ 'is-disabled': !isAssignable(submission) }"
                  :title="
                    !isAssignable(submission)
                      ? t('kycList_alert_assignNoEligible')
                      : ''
                  "
                >
                  <input
                    type="checkbox"
                    :value="submission.submissionId"
                    v-model="selectedSubmissions"
                    :disabled="!isAssignable(submission)"
                  />
                  <span class="checkbox-checkmark"></span>
                </label>
              </td>
              <td>
                <div class="client-info">
                  <div class="client-avatar">
                    {{ getInitials(submission.firstName, submission.lastName) }}
                  </div>
                  <div class="client-details">
                    <div class="client-name">
                      {{ submission.firstName }} {{ submission.lastName }}
                    </div>
                    <div class="client-email">{{ submission.clientEmail }}</div>
                  </div>
                </div>
              </td>
              <td>
                <span>{{ submission.templateName }}</span>
                <!-- isThirdParty 是建 submission 时的快照，不受之后改模板影响 -->
                <span
                  v-if="submission.isThirdParty"
                  class="third-party-badge"
                  :title="submission.thirdPartyProvider || ''"
                >
                  <i class="fas fa-plug"></i>
                  {{
                    submission.thirdPartyProvider
                      ? submission.thirdPartyProvider.toUpperCase()
                      : t("kycList_thirdPartyBadge", "3rd Party")
                  }}
                </span>
              </td>
              <td>{{ formatDate(submission.submittedAt) }}</td>
              <td>
                <span class="status-badge" :class="submission.submissionStatus">
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
                      ? t("kycList_btn_hide")
                      : t("kycList_btn_detail")
                  }}
                </button>
                <!-- 配了第三方 detailUrl 时显示外跳按钮（仅 icon，悬停 tooltip 说明） -->
                <a
                  v-if="submission.detailUrl"
                  class="btn-action btn-third-party btn-third-party--icon"
                  :href="submission.detailUrl"
                  target="_blank"
                  rel="noopener noreferrer"
                  :title="
                    t(
                      'kycList_btn_viewInProvider',
                      'View in third-party provider',
                    )
                  "
                >
                  <i class="fas fa-external-link-alt"></i>
                </a>
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
                  <KYCSubmissionDetail
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
            tParams("kycList_pageOf", "Page {current} of {total}", {
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
    <BulkAssignModal
      :visible="showBulkAssignModal"
      :selectedSubmissions="getSelectedSubmissionDetails()"
      @close="closeBulkAssignModal"
      @assign="handleBulkAssign"
    />
  </div>
</template>

<script>
import { ref, onMounted, computed } from "vue";
import { useAuthStore } from "@/stores/auth";
import KYCSubmissionDetail from "@/components/kyc/KYCSubmissionDetail.vue";
import BulkAssignModal from "@/components/kyc/BulkAssignModal.vue";
import { kycSubmissionService } from "@/services/kycListService";
import { formatNumber } from "@/utils/helpers";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { useRoute } from "vue-router";
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";
import { recordOperationLog } from "@/services/operationLogReportApi";
import { buildExportLogPayload } from "@/config/operationLogPages";

const authStore = useAuthStore();

export default {
  name: "KYCList",
  components: {
    PageHeaderActions,
    KYCSubmissionDetail,
    BulkAssignModal,
  },
  setup() {
    const route = useRoute();
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
    // 审批进行中标记，防止重复提交
    const processingApprove = ref(false);
    const processingBulkApprove = ref(false);
    const showBulkAssignModal = ref(false);

    // 分页数据
    const currentPage = ref(1);
    const perPage = ref(10);
    const totalItems = ref(0);
    const totalPagesFromApi = ref(null);

    // 权限检查
    const hasReadonlyPermission = computed(() =>
      authStore.hasPermission("page_kyclist_readonly"),
    );
    const hasApprovePermission = computed(() =>
      authStore.hasPermission("page_kyclist_approve"),
    );
    const hasRejectPermission = computed(() =>
      authStore.hasPermission("page_kyclist_reject"),
    );
    const hasNeedMoreDocumentsPermission = computed(() =>
      authStore.hasPermission("page_kyclist_needmoredocuments"),
    );
    const hasExportPermission = computed(() =>
      authStore.hasPermission("page_kyclist_export"),
    );
    const hasAssignReviewerPermission = computed(() =>
      authStore.hasPermission("page_kyclist_assignreviewer"),
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

    // 判断一条 submission 是不是可以被 assign（用于 checkbox 和 selectAll）
    // 不可选的：
    //   - approved（已经审完了）
    //   - isThirdParty=1（第三方流程整套由 Sumsub 接管审核，我们这边不需要 assign 后台审核员）
    const isAssignable = (submission) => {
      if (!submission) return false;
      if (submission.submissionStatus === "approved") return false;
      if (submission.isThirdParty) return false;
      return true;
    };

    const selectAll = computed({
      get: () => {
        const assignable = submissions.value.filter(isAssignable);
        return (
          assignable.length > 0 &&
          assignable.every((s) =>
            selectedSubmissions.value.includes(s.submissionId),
          )
        );
      },
      set: (value) => {
        if (value) {
          // 全选只勾"可 assign"的那部分
          selectedSubmissions.value = submissions.value
            .filter(isAssignable)
            .map((s) => s.submissionId);
        } else {
          selectedSubmissions.value = [];
        }
      },
    });

    // 加载提交列表
    const loadSubmissions = async () => {
      try {
        loading.value = true;
        const response = await kycSubmissionService.getList({
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
        const response = await kycSubmissionService.getStatistics();
        statistics.value = response.data;
      } catch (error) {
        console.error("Failed to load statistics:", error);
      }
    };

    // 工具函数
    const getInitials = (firstName, lastName) => {
      if (!firstName && !lastName) return "U";
      if (firstName && lastName) {
        return `${firstName[0]}${lastName[0]}`.toUpperCase();
      }
      return (firstName || lastName)[0].toUpperCase();
    };

    const formatStatus = (status) => {
      if (!status) return "-";
      return t(`addrVerif_status_${status}`, status);
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
        alert(t("kycList_alert_exportNeedSelection"));
        return;
      }

      try {
        // 显示加载提示
        if (selectedSubmissions.value.length > 5) {
          alert(
            tParams(
              "kycList_alert_loadingDetails",
              "Loading details for {n} submissions. Please wait...",
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
              const detailResponse = await kycSubmissionService.getDetail(
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
              const questions = category.questions || category.answers || [];
              questions.forEach((q) => {
                const questionId = q.questionId || q.id;
                const questionText = q.questionText || q.question || "";
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

          // 如果已经有格式化后的答案
          if (answer.answer !== undefined && answer.answer !== null) {
            return String(answer.answer);
          }

          // 根据问题类型获取答案
          const questionType = answer.questionType || "text";
          switch (questionType) {
            case "file_upload":
              if (answer.files && Array.isArray(answer.files)) {
                return answer.files
                  .map((f) => f.fileName || f.filePath || f)
                  .join("; ");
              }
              if (answer.uploadedFiles && Array.isArray(answer.uploadedFiles)) {
                return answer.uploadedFiles
                  .map((f) =>
                    typeof f === "string" ? f : f.fileName || f.filePath || f,
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
              return answer.answer || "-";
            case "date":
              return answer.answerDate || answer.answer || "-";
            case "number":
              return answer.answerNumber !== undefined
                ? String(answer.answerNumber)
                : answer.answer || "-";
            default:
              return answer.answerValue || answer.answer || "-";
          }
        };

        // 获取某个 submission 的答案映射
        const getAnswerMap = (submission) => {
          const answerMap = new Map();
          if (submission.answers && Array.isArray(submission.answers)) {
            submission.answers.forEach((category) => {
              const questions = category.questions || category.answers || [];
              questions.forEach((q) => {
                const questionId = q.questionId || q.id;
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
          { key: "submissionId", label: t("kycList_export_col_submissionId") },
          { key: "firstName", label: t("kycList_export_col_firstName") },
          { key: "lastName", label: t("kycList_export_col_lastName") },
          { key: "clientEmail", label: t("kycList_export_col_email") },
          { key: "templateName", label: t("kycList_export_col_templateName") },
          {
            key: "submissionDate",
            label: t("kycList_export_col_submissionDate"),
          },
          { key: "status", label: t("kycList_export_col_status") },
          { key: "reviewer", label: t("kycList_export_col_reviewer") },
          { key: "reviewedAt", label: t("kycList_export_col_reviewedAt") },
          { key: "progress", label: t("kycList_export_col_progress") },
          {
            key: "answeredQuestions",
            label: t("kycList_export_col_answeredQuestions"),
          },
          {
            key: "signedDocuments",
            label: t("kycList_export_col_signedDocuments"),
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
            submission.firstName || "",
            submission.lastName || "",
            submission.clientEmail || "",
            submission.templateName || "",
            formatDate(submission.submittedAt),
            formatStatus(submission.submissionStatus),
            submission.reviewerName || "-",
            submission.reviewedAt ? formatDate(submission.reviewedAt) : "-",
            submission.progressPercentage
              ? `${submission.progressPercentage}%`
              : "0%",
            `${submission.answeredQuestions || 0}/${submission.totalQuestions || 0}`,
            `${submission.signedDocuments || 0}/${submission.requiredDocuments || 0}`,
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
          `kyc_submissions_${new Date().toISOString().split("T")[0]}.${format === "csv" ? "csv" : "xls"}`,
        );
        link.style.visibility = "hidden";
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);

        const exportCount = selectedSubmissions.value.length;
        alert(
          tParams(
            "kycList_alert_exportOk",
            "Successfully exported {n} submission(s) with questionnaire data as {format}!",
            {
              n: exportCount,
              format: format.toUpperCase(),
            },
          ),
        );

        const fmtLabel = format.toUpperCase();
        recordOperationLog(
          buildExportLogPayload("page_kyc_list", {
            detailZh: `导出 ${exportCount} 条 KYC 提交（${fmtLabel}）`,
            detailEn: `Exported ${exportCount} KYC submission(s) (${fmtLabel})`,
          }),
        ).catch(() => {});

        selectedSubmissions.value = [];
      } catch (error) {
        console.error("Export error:", error);
        alert(
          tParams(
            "kycList_alert_exportFailed",
            "Failed to export submissions: {msg}",
            {
              msg: error.message || t("common_unknownError"),
            },
          ),
        );
      }
    };

    // 批量操作
    const bulkApprove = async () => {
      if (processingBulkApprove.value) return; // 进行中，避免重复提交
      const submissionIds = selectedSubmissions.value.filter((id) => {
        const s = submissions.value.find((x) => x.submissionId === id);
        return s && s.submissionStatus !== "approved";
      });
      if (submissionIds.length === 0) return;

      processingBulkApprove.value = true;
      try {
        await kycSubmissionService.bulkApprove(submissionIds);

        // 清空选中项
        selectedSubmissions.value = [];

        // 刷新列表和统计信息
        await Promise.all([loadSubmissions(), loadStatistics()]);

        alert(t("kycList_alert_bulkApproveOk"));
      } catch (error) {
        console.error("Bulk approve failed:", error);
        const errorMessage =
          error.response?.data?.message || t("kycList_err_bulkApprove");
        alert(errorMessage);
      } finally {
        processingBulkApprove.value = false;
      }
    };

    const openBulkAssignModal = () => {
      if (selectedSubmissions.value.length === 0) return;
      // checkbox 那一层已经禁止选中不可 assign 的，这里直接打开 modal
      showBulkAssignModal.value = true;
    };

    const closeBulkAssignModal = () => {
      showBulkAssignModal.value = false;
    };

    const handleBulkAssign = async () => {
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
        alert(t("kycList_alert_assignOk"));
      } catch (error) {
        console.error("Failed to update UI after bulk assign:", error);
        alert(t("kycList_alert_assignRefreshFailed"));
      }
    };

    // 单个操作处理
    const handleApprove = async (submissionId) => {
      if (processingApprove.value) return; // 进行中，避免重复提交
      processingApprove.value = true;
      try {
        await kycSubmissionService.approve(submissionId);
        await loadSubmissions();
        await loadStatistics();
        alert(t("kycList_alert_approveOk"));
      } catch (error) {
        // 之前这里只 console.error，approve 失败时既不提示也不刷新，表现为"点了没反应"
        console.error("Approve failed:", error);
        const errorMessage =
          error.response?.data?.message ||
          t("kycList_err_approve", "Failed to approve submission");
        alert(errorMessage);
      } finally {
        processingApprove.value = false;
      }
    };

    const handleReject = async (submissionId, reason) => {
      try {
        await kycSubmissionService.reject(submissionId, { reason });
        await loadSubmissions();
        await loadStatistics();
        alert(t("kycList_alert_rejectOk"));
      } catch (error) {
        console.error("Reject failed:", error);
        const errorMessage =
          error.response?.data?.message || t("kycList_err_reject");
        alert(errorMessage);
      }
    };

    const handleNeedDocs = async (submissionId, requirements) => {
      try {
        await kycSubmissionService.needDocs(submissionId, {
          items: requirements.items,
          notes: requirements.notes,
        });
        alert(t("kycList_alert_needDocsOk"));

        // 刷新当前页的数据（列表和统计信息）
        await Promise.all([loadSubmissions(), loadStatistics()]);

        // 注意：由于 Vue 的响应式系统，当 submissions 数组更新后，
        // 传入 KYCSubmissionDetail 组件的 submission prop 会自动更新，
        // 从而触发组件内的 watch 监听，自动刷新详情内容
      } catch (error) {
        console.error("Need docs failed:", error);
        const errorMessage =
          error.response?.data?.message || t("kycList_err_needDocs");
        alert(errorMessage);
      }
    };

    const handleAssign = async (submissionId, reviewerId) => {
      try {
        await kycSubmissionService.assign(submissionId, {
          reviewerId: reviewerId,
        });
        await loadSubmissions();
        await loadStatistics();
      } catch (error) {
        console.error("Assign failed:", error);
        alert(t("kycList_err_assign"));
      }
    };

    // 获取选中提交的详细信息（bulk assign modal 用），跟 isAssignable 同一套规则
    const getSelectedSubmissionDetails = () => {
      return submissions.value
        .filter((submission) => {
          if (!selectedSubmissions.value.includes(submission.submissionId))
            return false;
          return isAssignable(submission);
        })
        .map((submission) => ({
          id: submission.submissionId,
          clientName: `${submission.firstName} ${submission.lastName}`,
          clientEmail: submission.clientEmail,
          templateName: submission.templateName,
          submissionStatus: submission.submissionStatus,
        }));
    };

    // 生命周期
    onMounted(async () => {
      const targetId = Number(route.query.submissionId);
      const hasDashboardTarget =
        route.query.source === "webmcp-overview" &&
        Number.isSafeInteger(targetId) &&
        targetId > 0;
      if (hasDashboardTarget) {
        try {
          loading.value = true;
          const response = await kycSubmissionService.getDetail(targetId);
          const target = response?.data?.submission || response?.data;
          if (target && Number(target.submissionId) === targetId) {
            submissions.value = [target];
            totalItems.value = 1;
            totalPagesFromApi.value = 1;
            expandedRows.value = [targetId];
          } else {
            await loadSubmissions();
          }
        } catch (error) {
          console.error("Failed to open dashboard KYC target:", error);
          await loadSubmissions();
        } finally {
          loading.value = false;
        }
      } else {
        await loadSubmissions();
      }
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
      isAssignable,
      loadSubmissions,
      getInitials,
      formatStatus,
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

/* 不可选状态：勾不上、灰掉、有禁止指针 */
.custom-checkbox.is-disabled {
  cursor: not-allowed;
}
.custom-checkbox.is-disabled input[type="checkbox"] {
  cursor: not-allowed;
}
.custom-checkbox.is-disabled .checkbox-checkmark {
  background: var(--color-surface-soft);
  border-color: var(--color-border);
  opacity: 0.6;
}
.custom-checkbox.is-disabled:hover .checkbox-checkmark {
  border-color: var(--color-border);
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

.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: var(--radius-lg);
  font-size: 14px;
  font-weight: 600;
  text-transform: uppercase;
}

/* 第三方 KYC 标识：跟在 templateName 后面的小药丸 */
.third-party-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  margin-left: 8px;
  padding: 2px 8px;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  letter-spacing: 0.3px;
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.third-party-badge i {
  /* @font-floor-exempt: visual-only status glyph */
  font-size: 10px;
}

.status-badge.pending {
  background: var(--color-warning-soft);
  color: var(--color-warning);
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

/* 跳转到第三方后台 */
.btn-third-party {
  background: var(--color-brand-soft);
  color: var(--color-brand);
  margin-left: 6px;
  text-decoration: none;
}

.btn-third-party:hover {
  background: #5b21b6;
  color: white;
}

/* icon-only 变体（KYC List 行内用） */
.btn-third-party--icon {
  padding: 8px 10px;
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
