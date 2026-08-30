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
          <i class="fas fa-user-check"></i> Assign Reviewer to Selected IB
          Applications
        </h3>
        <button class="bulk-assignment-modal-close" @click="closeModal">
          ×
        </button>
      </div>
      <div class="bulk-assignment-modal-body">
        <!-- Selected Applications List -->
        <div class="selected-applications-list">
          <h4>
            <i class="fas fa-file-alt"></i> Selected IB Applications
            <span class="count-badge">{{ selectedApplications.length }}</span>
          </h4>
          <div class="selected-applications-items">
            <div
              v-for="app in selectedApplications"
              :key="app.id"
              class="selected-application-item"
            >
              <div class="application-item-info">
                <div class="application-item-client">
                  <div class="client-avatar">
                    {{ getInitials(app.applicantName) }}
                  </div>
                  <div class="client-details">
                    <div class="client-name">{{ app.applicantName }}</div>
                    <div class="client-email">{{ app.applicantEmail }}</div>
                  </div>
                </div>
                <div class="application-item-meta">
                  <span
                    class="application-status"
                    :class="getStatusClass(app.applicationStatus)"
                  >
                    {{ formatStatus(app.applicationStatus) }}
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
              <label for="bulk-operator">Assign Operator (Edit Rules) *</label>
              <select id="bulk-operator" v-model="selectedOperator">
                <option value="">-- Select Operator --</option>
                <option
                  v-for="operator in operators"
                  :key="operator.id"
                  :value="operator.id"
                >
                  {{ operator.fullName }} ({{ operator.username }})
                </option>
              </select>
              <small class="field-hint"
                >Sales permission - responsible for editing rules</small
              >
            </div>

            <div class="bulk-assignment-field">
              <label for="bulk-reviewer">Assign Reviewer (Decision) *</label>
              <select id="bulk-reviewer" v-model="selectedReviewer">
                <option value="">-- Select Reviewer --</option>
                <option
                  v-for="reviewer in reviewers"
                  :key="reviewer.id"
                  :value="reviewer.id"
                >
                  {{ reviewer.fullName }} ({{ reviewer.username }})
                </option>
              </select>
              <small class="field-hint"
                >Risk/Operation permission - responsible for
                approval/rejection</small
              >
            </div>

            <div class="bulk-assignment-info">
              <div class="bulk-assignment-info-item">
                <span class="bulk-assignment-info-label"
                  >Total Applications:</span
                >
                <span class="bulk-assignment-info-value">{{
                  selectedApplications.length
                }}</span>
              </div>
              <div class="bulk-assignment-info-item">
                <span class="bulk-assignment-info-label">Assignment Date:</span>
                <span class="bulk-assignment-info-value">{{
                  formatDate(new Date())
                }}</span>
              </div>
              <div class="bulk-assignment-info-item">
                <span class="bulk-assignment-info-label">Assigned By:</span>
                <span class="bulk-assignment-info-value">{{
                  currentUser.fullName || "Admin User"
                }}</span>
              </div>
            </div>

            <div class="bulk-assignment-field" style="grid-column: 1 / -1">
              <label for="bulk-assignment-notes"
                >Assignment Notes (Optional)</label
              >
              <textarea
                id="bulk-assignment-notes"
                v-model="assignmentNotes"
                placeholder="Add notes for the assigned reviewers..."
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
          Cancel
        </button>
        <button
          class="btn-modal-bulk btn-modal-bulk-assign"
          @click="confirmAssignment"
          :disabled="!selectedOperator || !selectedReviewer || loading"
        >
          <i class="fas fa-user-check"></i>
          {{ loading ? "Assigning..." : "Assign Reviewers" }}
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted } from "vue";
import accountService from "@/services/accountService";

export default {
  name: "BulkAssignReviewerModal",
  props: {
    visible: {
      type: Boolean,
      default: false,
    },
    selectedApplications: {
      type: Array,
      default: () => [],
    },
  },
  emits: ["close", "assign"],
  setup(props, { emit }) {
    const selectedOperator = ref("");
    const selectedReviewer = ref("");
    const assignmentNotes = ref("");
    const operators = ref([]);
    const reviewers = ref([]);
    const loading = ref(false);
    const currentUser = ref({});

    // 获取操作员列表（有 ib_application_edit_rules 权限的用户）
    const loadOperators = async () => {
      try {
        const response = await accountService.getAccounts({
          per_page: 1000,
          status: "active",
        });

        if (response && response.data) {
          // 过滤出有编辑规则权限的用户（operator角色）
          operators.value = (response.data.items || response.data || []).filter(
            (user) => {
              // 这里可以根据实际权限过滤，暂时先返回所有活跃用户
              return user.status === "active";
            },
          );
        }
      } catch (error) {
        console.error("Failed to load operators:", error);
      }
    };

    // 获取审批人列表（有 ib_application_decision 权限的用户）
    const loadReviewers = async () => {
      try {
        const response = await accountService.getAccounts({
          per_page: 1000,
          status: "active",
        });

        if (response && response.data) {
          // 过滤出有决策权限的用户（manager/admin角色）
          reviewers.value = (response.data.items || response.data || []).filter(
            (user) => {
              // 这里可以根据实际权限过滤，暂时先返回所有活跃用户
              return user.status === "active";
            },
          );
        }
      } catch (error) {
        console.error("Failed to load reviewers:", error);
      }
    };

    // 获取当前用户信息
    const loadCurrentUser = () => {
      const user = JSON.parse(localStorage.getItem("user") || "{}");
      currentUser.value = user;
    };

    // 关闭弹窗
    const closeModal = () => {
      selectedOperator.value = "";
      selectedReviewer.value = "";
      assignmentNotes.value = "";
      emit("close");
    };

    // 确认分配
    const confirmAssignment = async () => {
      if (!selectedOperator.value || !selectedReviewer.value) {
        alert("Please select both operator and reviewer");
        return;
      }

      loading.value = true;
      try {
        const applicationIds = props.selectedApplications.map((app) => app.id);

        // 保存数据用于emit
        const assignData = {
          operatorId: selectedOperator.value,
          reviewerId: selectedReviewer.value,
          applicationIds,
          notes: assignmentNotes.value,
        };

        // 清空表单
        selectedOperator.value = "";
        selectedReviewer.value = "";
        assignmentNotes.value = "";

        // emit事件通知父组件
        emit("assign", assignData);
      } catch (error) {
        console.error("Failed to assign reviewers:", error);
        const errorMessage =
          error.response?.data?.message ||
          error.message ||
          "Failed to assign reviewers. Please try again.";
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
      const statusMap = {
        pending: "Pending",
        in_review: "In Review",
        approved: "Approved",
        rejected: "Rejected",
        more_info_requested: "More Info Requested",
      };
      return statusMap[status] || status;
    };

    const getStatusClass = (status) => {
      const classMap = {
        pending: "status-pending",
        in_review: "status-review",
        approved: "status-approved",
        rejected: "status-rejected",
        more_info_requested: "status-info",
      };
      return classMap[status] || "status-default";
    };

    const formatDate = (date) => {
      return new Intl.DateTimeFormat("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
      }).format(date);
    };

    onMounted(() => {
      loadOperators();
      loadReviewers();
      loadCurrentUser();
    });

    return {
      selectedOperator,
      selectedReviewer,
      assignmentNotes,
      operators,
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

.selected-applications-list {
  background: var(--color-surface-soft);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 20px;
  margin-bottom: 25px;
}

.selected-applications-list h4 {
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
  font-size: 14px;
  font-weight: 700;
}

.selected-applications-items {
  max-height: 200px;
  overflow-y: auto;
}

.selected-application-item {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 12px;
  margin-bottom: 8px;
}

.selected-application-item:last-child {
  margin-bottom: 0;
}

.application-item-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.application-item-client {
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
  font-size: 14px;
  font-weight: 600;
}

.client-details {
  display: flex;
  flex-direction: column;
}

.client-name {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
}

.client-email {
  font-size: 14px;
  color: var(--color-muted);
}

.application-item-meta {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
}

.application-status {
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 14px;
  font-weight: 600;
  text-transform: uppercase;
}

.status-pending {
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
.status-info {
  background: var(--color-brand-soft);
  color: var(--color-brand);
}
.status-default {
  background: var(--color-surface-soft);
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
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
}

.bulk-assignment-field .field-hint {
  font-size: 14px;
  color: var(--color-muted);
  font-style: italic;
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
  font-size: 14px;
  color: var(--color-muted);
  font-weight: 500;
}

.bulk-assignment-info-value {
  font-size: 14px;
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

  .application-item-info {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }

  .application-item-meta {
    align-items: flex-start;
  }
}
</style>
