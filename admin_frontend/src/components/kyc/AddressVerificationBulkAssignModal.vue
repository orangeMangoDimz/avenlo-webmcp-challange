<template>
  <!-- Bulk Assignment Modal -->
  <div
    class="bulk-assignment-modal"
    :class="{ show: visible }"
    @click.self="closeModal"
  >
    <div class="bulk-assignment-modal-content">
      <div class="bulk-assignment-modal-header">
        <h3>
          <i class="fas fa-user-check"></i> {{ t("addrVerif_bulk_modalTitle") }}
        </h3>
        <button class="bulk-assignment-modal-close" @click="closeModal">
          ×
        </button>
      </div>
      <div class="bulk-assignment-modal-body">
        <!-- Selected Address Verification List -->
        <div class="selected-kyc-list">
          <h4>
            <i class="fas fa-id-card"></i>
            {{ t("addrVerif_bulk_selectedTitle") }}
            <span class="count-badge">{{ selectedSubmissions.length }}</span>
          </h4>
          <div class="selected-kyc-items">
            <div
              v-for="submission in selectedSubmissions"
              :key="submission.id"
              class="selected-kyc-item"
            >
              <div class="kyc-item-info">
                <div class="kyc-item-client">
                  <div class="client-avatar">
                    {{ getInitials(submission.clientName) }}
                  </div>
                  <div class="client-details">
                    <div class="client-name">{{ submission.clientName }}</div>
                    <div class="client-email">{{ submission.clientEmail }}</div>
                  </div>
                </div>
                <div class="kyc-item-meta">
                  <span class="kyc-template">{{
                    submission.templateName
                  }}</span>
                  <span
                    class="kyc-status"
                    :class="getStatusClass(submission.submissionStatus)"
                  >
                    {{ formatStatus(submission.submissionStatus) }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Assignment Section -->
        <div class="bulk-assignment-section">
          <div class="bulk-assignment-content">
            <div class="bulk-assignment-field">
              <label for="bulk-reviewer">{{
                t("addrVerif_bulk_assignTo")
              }}</label>
              <select id="bulk-reviewer" v-model="selectedReviewer">
                <option value="">
                  {{ t("addrVerif_bulk_selectReviewer") }}
                </option>
                <option
                  v-for="reviewer in reviewers"
                  :key="reviewer.id"
                  :value="reviewer.id"
                >
                  {{ reviewer.fullName }} ({{ reviewer.username }})
                </option>
              </select>
            </div>

            <div class="bulk-assignment-info">
              <div class="bulk-assignment-info-item">
                <span class="bulk-assignment-info-label">{{
                  t("addrVerif_bulk_totalSubmissions")
                }}</span>
                <span class="bulk-assignment-info-value">{{
                  selectedSubmissions.length
                }}</span>
              </div>
              <div class="bulk-assignment-info-item">
                <span class="bulk-assignment-info-label">{{
                  t("addrVerif_bulk_assignmentDate")
                }}</span>
                <span class="bulk-assignment-info-value">{{
                  formatDate(new Date())
                }}</span>
              </div>
              <div class="bulk-assignment-info-item">
                <span class="bulk-assignment-info-label">{{
                  t("addrVerif_bulk_assignedBy")
                }}</span>
                <span class="bulk-assignment-info-value">{{
                  currentUser.fullName || t("addrVerif_bulk_userFallback")
                }}</span>
              </div>
            </div>

            <div class="bulk-assignment-field" style="grid-column: 1 / -1">
              <label for="bulk-assignment-notes">{{
                t("addrVerif_bulk_notesLabel")
              }}</label>
              <textarea
                id="bulk-assignment-notes"
                v-model="assignmentNotes"
                :placeholder="t('addrVerif_bulk_notesPh')"
              ></textarea>
            </div>
          </div>
        </div>
      </div>
      <div class="bulk-assignment-modal-footer">
        <button
          class="btn-modal-bulk btn-modal-bulk-cancel"
          @click="closeModal"
        >
          {{ t("common_cancel") }}
        </button>
        <button
          class="btn-modal-bulk btn-modal-bulk-assign"
          @click="confirmAssignment"
          :disabled="!selectedReviewer || loading"
        >
          <i class="fas fa-user-check"></i>
          {{
            loading
              ? t("addrVerif_bulk_assigning")
              : t("addrVerif_bulk_btn_assign")
          }}
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted, nextTick } from "vue";
import {
  addressVerificationSubmissionService,
  addressVerificationReviewerService,
} from "@/services/addressVerificationService";
import { useAdminI18n } from "@/composables/useAdminI18n";

export default {
  name: "AddressVerificationBulkAssignModal",
  props: {
    visible: {
      type: Boolean,
      default: false,
    },
    selectedSubmissions: {
      type: Array,
      default: () => [],
    },
  },
  emits: ["close", "assign"],
  setup(props, { emit }) {
    const { t, languageStore } = useAdminI18n();
    const dateLocale = () =>
      languageStore.currentLanguage === "zh" ? "zh-CN" : "en-US";
    const selectedReviewer = ref("");
    const assignmentNotes = ref("");
    const reviewers = ref([]);
    const loading = ref(false);
    const currentUser = ref({});

    // 获取审核员列表
    const loadReviewers = async () => {
      try {
        console.log("Loading reviewers...");
        const response =
          await addressVerificationReviewerService.getReviewers();
        console.log("Reviewers response:", response);

        if (response && response.data) {
          reviewers.value = response.data;
          console.log("Reviewers loaded:", reviewers.value);
        } else {
          console.warn("No reviewers data in response, using mock data");
          reviewers.value = [
            { id: 1, fullName: "Admin User", username: "admin" },
            { id: 2, fullName: "Sarah Davis", username: "sarah.davis" },
            { id: 3, fullName: "Michael Brown", username: "michael.brown" },
            { id: 4, fullName: "Emily Taylor", username: "emily.taylor" },
            { id: 5, fullName: "David Wilson", username: "david.wilson" },
          ];
        }
      } catch (error) {
        console.error("Failed to load reviewers:", error);
        console.error("Error details:", error.response || error.message);
      }
    };

    // 获取当前用户信息
    const loadCurrentUser = () => {
      // 从localStorage或其他地方获取当前用户信息
      const user = JSON.parse(localStorage.getItem("user") || "{}");
      currentUser.value = user;
    };

    // 关闭弹窗
    const closeModal = () => {
      selectedReviewer.value = "";
      assignmentNotes.value = "";
      emit("close");
    };

    // 确认分配
    const confirmAssignment = async () => {
      if (!selectedReviewer.value) {
        alert(t("addrVerif_bulk_alert_selectReviewer"));
        return;
      }

      loading.value = true;
      try {
        const submissionIds = props.selectedSubmissions.map((s) => s.id);

        // 调用API分配审核员
        await addressVerificationSubmissionService.bulkAssign(submissionIds, {
          reviewerId: selectedReviewer.value,
          notes: assignmentNotes.value,
        });

        // 保存数据用于emit（在清空表单之前）
        const assignData = {
          reviewerId: selectedReviewer.value,
          submissionIds,
          notes: assignmentNotes.value,
        };

        // 清空表单
        selectedReviewer.value = "";
        assignmentNotes.value = "";

        // API调用成功后，emit事件通知父组件更新UI
        // 使用nextTick确保在下一个tick中触发事件
        await nextTick();
        emit("assign", assignData);
      } catch (error) {
        console.error("Failed to assign reviewer:", error);
        const errorMessage =
          error.response?.data?.message ||
          error.message ||
          t("addrVerif_bulk_err_assign");
        alert(errorMessage);
      } finally {
        loading.value = false;
      }
    };

    // 工具函数
    const getInitials = (name) => {
      if (!name) return "??";
      return name
        .split(" ")
        .map((n) => n[0])
        .join("")
        .toUpperCase()
        .slice(0, 2);
    };

    const formatStatus = (status) => {
      const keyMap = {
        draft: "addrVerif_status_draft",
        submitted: "addrVerif_status_submitted",
        under_review: "addrVerif_status_under_review",
        approved: "addrVerif_status_approved",
        rejected: "addrVerif_status_rejected",
        incomplete: "addrVerif_status_incomplete",
        pending: "addrVerif_status_pending",
        resubmit_required: "addrVerif_status_resubmit_required",
        need_docs: "addrVerif_status_need_docs",
      };
      const key = keyMap[status];
      return key ? t(key) : status;
    };

    const getStatusClass = (status) => {
      const classMap = {
        draft: "status-draft",
        submitted: "status-submitted",
        under_review: "status-review",
        approved: "status-approved",
        rejected: "status-rejected",
        incomplete: "status-incomplete",
      };
      return classMap[status] || "status-default";
    };

    const formatDate = (date) => {
      return new Intl.DateTimeFormat(dateLocale(), {
        year: "numeric",
        month: "short",
        day: "numeric",
      }).format(date);
    };

    onMounted(() => {
      loadReviewers();
      loadCurrentUser();
    });

    return {
      t,
      selectedReviewer,
      assignmentNotes,
      reviewers,
      loading,
      currentUser,
      closeModal,
      confirmAssignment,
      getInitials,
      formatStatus,
      getStatusClass,
      formatDate,
    };
  },
};
</script>

<style scoped>
/* Bulk Assignment Modal */
.bulk-assignment-modal {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  z-index: 2000;
  align-items: center;
  justify-content: center;
}

.bulk-assignment-modal.show {
  display: flex;
}

.bulk-assignment-modal-content {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: 0;
  max-width: 700px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
  animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
  from {
    opacity: 0;
    transform: translateY(-20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.bulk-assignment-modal-header {
  padding: 25px 30px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--color-surface-soft);
}

.bulk-assignment-modal-header h3 {
  font-size: 20px;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 0;
}

.bulk-assignment-modal-header h3 i {
  color: var(--color-brand);
  font-size: 22px;
}

.bulk-assignment-modal-close {
  background: none;
  border: none;
  font-size: 28px;
  color: var(--color-faint);
  cursor: pointer;
  transition: all 0.2s ease;
  line-height: 1;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-sm);
}

.bulk-assignment-modal-close:hover {
  color: var(--color-brand);
  background: var(--color-brand-soft);
}

.bulk-assignment-modal-body {
  padding: 30px;
}

.selected-kyc-list {
  background: var(--color-surface-soft);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 20px;
  margin-bottom: 25px;
}

.selected-kyc-list h4 {
  font-size: 14px;
  color: var(--color-text);
  font-weight: 600;
  margin: 0 0 15px 0;
  display: flex;
  align-items: center;
  gap: 8px;
}

.count-badge {
  background: var(--color-brand-solid);
  color: white;
  padding: 2px 8px;
  border-radius: var(--radius-lg);
  font-size: 12px;
  font-weight: 700;
}

.selected-kyc-items {
  max-height: 200px;
  overflow-y: auto;
}

.selected-kyc-item {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 12px;
  margin-bottom: 8px;
}

.selected-kyc-item:last-child {
  margin-bottom: 0;
}

.kyc-item-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.kyc-item-client {
  display: flex;
  align-items: center;
  gap: 10px;
}

.client-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--color-brand-solid);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 600;
}

.client-details {
  display: flex;
  flex-direction: column;
}

.client-name {
  font-size: 13px;
  font-weight: 600;
  color: var(--color-ink);
}

.client-email {
  font-size: 11px;
  color: var(--color-muted);
}

.kyc-item-meta {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
}

.kyc-template {
  font-size: 11px;
  color: var(--color-text);
}

.kyc-status {
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
}

.status-draft {
  background: var(--color-surface-soft);
  color: var(--color-text);
}
.status-submitted {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}
.status-review {
  background: var(--color-info-soft);
  color: var(--color-info);
}
.status-approved {
  background: var(--color-success-soft);
  color: var(--color-success);
}
.status-rejected {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}
.status-incomplete {
  background: var(--color-border);
  color: var(--color-text);
}

.bulk-assignment-section {
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 25px;
}

.bulk-assignment-content {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

.bulk-assignment-field {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.bulk-assignment-field label {
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text);
}

.bulk-assignment-field select,
.bulk-assignment-field textarea {
  padding: 10px 12px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  transition: all 0.2s ease;
}

.bulk-assignment-field select:focus,
.bulk-assignment-field textarea:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.bulk-assignment-field textarea {
  min-height: 80px;
  resize: vertical;
}

.bulk-assignment-info {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.bulk-assignment-info-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 0;
  border-bottom: 1px solid #f1f5f9;
}

.bulk-assignment-info-item:last-child {
  border-bottom: none;
}

.bulk-assignment-info-label {
  font-size: 12px;
  color: var(--color-muted);
  font-weight: 500;
}

.bulk-assignment-info-value {
  font-size: 13px;
  color: var(--color-ink);
  font-weight: 600;
}

.bulk-assignment-modal-footer {
  padding: 20px 30px;
  border-top: 2px solid var(--color-border);
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  background: var(--color-surface-soft);
}

.btn-modal-bulk {
  padding: 10px 20px;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-modal-bulk-cancel {
  background: var(--color-border);
  color: var(--color-text);
}

.btn-modal-bulk-cancel:hover {
  background: var(--color-border-strong);
}

.btn-modal-bulk-assign {
  background: var(--color-brand-solid);
  color: white;
}

.btn-modal-bulk-assign:hover:not(:disabled) {
  background: var(--color-brand-strong);
}

.btn-modal-bulk-assign:disabled {
  background: var(--color-faint);
  cursor: not-allowed;
}

@media (max-width: 768px) {
  .bulk-assignment-content {
    grid-template-columns: 1fr;
  }

  .kyc-item-info {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }

  .kyc-item-meta {
    align-items: flex-start;
  }
}
</style>
