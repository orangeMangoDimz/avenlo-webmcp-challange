<template>
  <div class="detail-content">
    <!-- Pending状态：无审批人，需先配置审批人 -->
    <template
      v-if="
        application.applicationStatus === 'pending' &&
        (!application.reviewerId || !application.auditorId)
      "
    >
      <div class="status-message pending-message">
        <div class="status-message-content">
          <i class="fas fa-exclamation-triangle"></i>
          <p class="status-message-title">Please assign reviewers first</p>
          <p class="status-message-description">
            This application requires both an operator and an auditor before it
            can be reviewed.
          </p>
        </div>
      </div>
    </template>
    <!-- In Review状态：根据编辑状态和等待审批状态显示不同内容 -->
    <template v-else-if="application.applicationStatus === 'in_review'">
      <!-- 不是分配的Operator和Reviewer：显示提示信息 -->
      <div
        v-if="!isCurrentAuditor && !isCurrentOperator"
        class="status-message under-review-message"
      >
        <div class="status-message-content">
          <i class="fas fa-id-card"></i>
          <p class="status-message-title">IB Application Under Review</p>
          <p class="status-message-description">
            This application is currently being reviewed by
            {{ application.auditorName || "an auditor" }}
          </p>
        </div>
      </div>
      <!-- 是分配的Operator或Reviewer -->
      <template v-else>
        <!-- 编辑状态：只有Operator可以查看Detail，Reviewer看到提示信息 -->
        <div
          v-if="isEditingState && !isCurrentOperator"
          class="status-message under-review-message"
        >
          <div class="status-message-content">
            <i class="fas fa-id-card"></i>
            <p class="status-message-title">IB Application Under Review</p>
            <p class="status-message-description">
              This application is currently being reviewed by
              {{ application.auditorName || "an auditor" }}
            </p>
          </div>
        </div>
        <!-- 等待审批状态：只有Reviewer可以查看Detail，Operator看到提示信息 -->
        <div
          v-else-if="!isEditingState && !isCurrentAuditor"
          class="status-message under-review-message"
        >
          <div class="status-message-content">
            <i class="fas fa-id-card"></i>
            <p class="status-message-title">IB Application Under Review</p>
            <p class="status-message-description">
              This application is currently being reviewed by
              {{ application.auditorName || "an auditor" }}
            </p>
          </div>
        </div>
        <!-- 可以查看Detail的情况 -->
        <template v-else>
          <!-- 当前用户是审批人或操作员时显示完整详情 -->
          <div class="detail-sections">
            <div class="detail-section basic-info-section">
              <h3><i class="fas fa-user-circle"></i> Basic Information</h3>
              <!-- KYC数据（如果存在） -->
              <template v-if="kycData && kycData.length > 0">
                <div class="basic-info-grid">
                  <template v-for="category in kycData" :key="category.id">
                    <div
                      v-for="answer in category.answers"
                      :key="answer.questionId"
                      class="detail-field"
                    >
                      <span class="detail-label">{{
                        answer.questionText
                      }}</span>
                      <span
                        class="detail-value"
                        v-if="
                          answer.questionType === 'file_upload' &&
                          answer.files &&
                          answer.files.length > 0
                        "
                      >
                        <a
                          v-for="(file, fileIdx) in answer.files"
                          :key="fileIdx"
                          :href="file.downloadUrl"
                          target="_blank"
                          class="file-download-link"
                        >
                          {{
                            file.fileName ||
                            basename(file.filePath || file.downloadUrl || "")
                          }}
                          <span v-if="fileIdx < answer.files.length - 1"
                            >,
                          </span>
                        </a>
                      </span>
                      <span
                        v-else
                        class="detail-value"
                        v-html="formatAnswerValue(answer)"
                      ></span>
                    </div>
                  </template>
                </div>
              </template>
            </div>
          </div>

          <!-- IB Commission Rules Assignment 组件 -->
          <IbCommissionRulesAssignment
            :application="mergedApplication"
            :tier-levels="tierLevels"
            :commission-rules="commissionRules"
            :commission-rules-details="commissionRulesDetails"
            :securities="securities"
            :symbols="symbols"
            :can-edit="canEditRules"
            :show-rejection-reason="false"
            :saving="saving"
            @save="handleRulesAssignmentSave"
            @refresh="emit('refresh')"
            @refresh-securities-symbols="emit('refresh-securities-symbols')"
          />

          <!-- Application Decision (只有当前Reviewer可以显示，且规则已保存) -->
          <div v-if="canShowDecision" class="ib-actions-section">
            <div class="ib-actions-header">
              <i class="fas fa-tasks"></i>
              <h3>Application Decision</h3>
            </div>
            <div class="ib-actions-buttons">
              <button
                class="btn-ib-action btn-approve-all"
                @click="handleApprove"
                :disabled="saving"
              >
                <i
                  class="fas"
                  :class="saving ? 'fa-spinner fa-spin' : 'fa-check-double'"
                ></i>
                Approve Application
              </button>
              <button
                v-if="false"
                class="btn-ib-action btn-request-info"
                @click="handleRequestInfo"
                :disabled="saving"
              >
                <i class="fas fa-info-circle"></i> Request More Info
              </button>
              <button
                class="btn-ib-action btn-reject"
                @click="handleReject"
                :disabled="saving"
              >
                <i class="fas fa-times"></i> Reject
              </button>
            </div>
          </div>
        </template>
      </template>
    </template>

    <!-- Approved状态：显示完整详情（只读） -->
    <template v-else-if="application.applicationStatus === 'approved'">
      <div class="status-message approved-message">
        <div class="status-message-content">
          <i class="fas fa-check-circle"></i>
          <p class="status-message-title">IB Application Approved</p>
          <p class="status-message-description">
            This application was approved on
            {{ formatApprovalDate(application.reviewCompletedDate) }} by
            {{ application.approvedByName || "an administrator" }}
          </p>
        </div>
      </div>
    </template>

    <!-- Rejected状态：只有当前Operator可以查看并编辑Detail，其他人显示提示信息 -->
    <template v-else-if="application.applicationStatus === 'rejected'">
      <!-- 不是当前Operator：显示提示信息 -->
      <div v-if="!isCurrentOperator" class="status-message rejected-message">
        <div class="status-message-content">
          <i class="fas fa-times-circle"></i>
          <p class="status-message-title">Application Rejected</p>
          <p class="status-message-description">
            This application was rejected on
            {{ formatRejectionDate(application.reviewCompletedDate) }} by
            {{ application.rejectedByName || "an administrator" }}
          </p>
          <div
            v-if="application.rejectionReason"
            class="rejection-reason-box"
            style="margin-top: 15px"
          >
            <div class="rejection-reason-header">
              <i class="fas fa-info-circle"></i>
              <strong>Rejection Reason</strong>
            </div>
            <div class="rejection-reason-content">
              {{ application.rejectionReason }}
            </div>
          </div>
        </div>
      </div>
      <!-- 是当前Operator：显示完整详情，可以编辑 -->
      <template v-else>
        <!-- 当前用户是审批人或操作员时显示完整详情 -->
        <div class="detail-sections">
          <div class="detail-section basic-info-section">
            <h3><i class="fas fa-user-circle"></i> Basic Information</h3>
            <!-- KYC数据（如果存在） -->
            <template v-if="kycData && kycData.length > 0">
              <div class="basic-info-grid">
                <template v-for="category in kycData" :key="category.id">
                  <div
                    v-for="answer in category.answers"
                    :key="answer.questionId"
                    class="detail-field"
                  >
                    <span class="detail-label">{{ answer.questionText }}</span>
                    <span
                      class="detail-value"
                      v-if="
                        answer.questionType === 'file_upload' &&
                        answer.files &&
                        answer.files.length > 0
                      "
                    >
                      <a
                        v-for="(file, fileIdx) in answer.files"
                        :key="fileIdx"
                        :href="file.downloadUrl"
                        target="_blank"
                        class="file-download-link"
                      >
                        {{
                          file.fileName ||
                          basename(file.filePath || file.downloadUrl || "")
                        }}
                        <span v-if="fileIdx < answer.files.length - 1">, </span>
                      </a>
                    </span>
                    <span
                      v-else
                      class="detail-value"
                      v-html="formatAnswerValue(answer)"
                    ></span>
                  </div>
                </template>
              </div>
            </template>
          </div>
        </div>

        <!-- IB Commission Rules Assignment 组件 -->
        <IbCommissionRulesAssignment
          :application="mergedApplication"
          :tier-levels="tierLevels"
          :commission-rules="commissionRules"
          :commission-rules-details="commissionRulesDetails"
          :securities="securities"
          :symbols="symbols"
          :can-edit="canEditRules"
          :show-rejection-reason="true"
          :saving="saving"
          @save="handleRulesAssignmentSave"
          @refresh="emit('refresh')"
          @refresh-securities-symbols="emit('refresh-securities-symbols')"
        />

        <!-- Application Decision (只有当前Reviewer可以显示，且规则已保存) -->
        <div v-if="canShowDecision" class="ib-actions-section">
          <div class="ib-actions-header">
            <i class="fas fa-tasks"></i>
            <h3>Application Decision</h3>
          </div>
          <div class="ib-actions-buttons">
            <button
              class="btn-ib-action btn-approve-all"
              @click="handleApprove"
              :disabled="saving"
            >
              <i
                class="fas"
                :class="saving ? 'fa-spinner fa-spin' : 'fa-check-double'"
              ></i>
              Approve Application
            </button>
            <button
              v-if="false"
              class="btn-ib-action btn-request-info"
              @click="handleRequestInfo"
              :disabled="saving"
            >
              <i class="fas fa-info-circle"></i> Request More Info
            </button>
            <button
              class="btn-ib-action btn-reject"
              @click="handleReject"
              :disabled="saving"
            >
              <i class="fas fa-times"></i> Reject
            </button>
          </div>
        </div>
      </template>
    </template>

    <!-- Manage Tiers Modal -->
    <Teleport to="body">
      <div v-if="showTiersModal" class="modal-overlay" @click="closeTiersModal">
        <div class="modal-container" @click.stop>
          <div class="modal-header">
            <h3><i class="fas fa-table"></i> Manage Commission Tiers</h3>
            <button class="modal-close" @click="closeTiersModal">×</button>
          </div>

          <div class="modal-body">
            <div class="tiers-list">
              <div
                v-for="(tier, index) in editingTiers"
                :key="index"
                class="tier-edit-item"
              >
                <div class="tier-edit-header">
                  <span class="tier-edit-level">Tier {{ tier.tierLevel }}</span>
                  <button
                    v-if="editingTiers.length > 1"
                    class="btn-icon btn-delete"
                    @click="removeTierEdit(index)"
                  >
                    <i class="fas fa-trash"></i>
                  </button>
                </div>
                <div class="tier-edit-content">
                  <div class="tier-edit-field">
                    <label>Tier Name</label>
                    <input
                      type="text"
                      v-model="tier.tierName"
                      class="form-input"
                    />
                  </div>
                  <div class="tier-edit-field">
                    <label>Commission Rate</label>
                    <input
                      type="number"
                      v-model.number="tier.commissionRate"
                      step="0.01"
                      min="0"
                      class="form-input"
                    />
                  </div>
                  <div class="tier-edit-field">
                    <label>Min. Volume (lots)</label>
                    <input
                      type="number"
                      v-model.number="tier.minimumVolume"
                      step="0.01"
                      min="0"
                      class="form-input"
                    />
                  </div>
                  <div class="tier-edit-field">
                    <label>Max. Volume</label>
                    <input
                      type="text"
                      v-model="tier.maximumVolume"
                      class="form-input"
                      placeholder="Unlimited"
                    />
                  </div>
                </div>
              </div>
            </div>

            <button type="button" class="btn-add-tier" @click="addTierEdit">
              <i class="fas fa-plus"></i> Add Another Tier
            </button>
          </div>

          <div class="modal-footer">
            <button class="btn btn-secondary" @click="closeTiersModal">
              Cancel
            </button>
            <button class="btn btn-primary" @click="saveTiersModal">
              <i class="fas fa-save"></i> Save Tiers
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, Teleport } from "vue";
import { useAuthStore } from "@/stores/auth";
import IbCommissionRulesAssignment from "./IbCommissionRulesAssignment.vue";
import ibApplicationsApi from "@/services/ibApplicationsApi";

const props = defineProps({
  application: {
    type: Object,
    required: true,
  },
  tierLevels: {
    type: Array,
    default: () => [],
  },
  commissionRules: {
    type: Array,
    default: () => [],
  },
  commissionRulesDetails: {
    type: Object,
    default: () => ({}),
  },
  securities: {
    type: Array,
    default: () => [],
  },
  symbols: {
    type: Array,
    default: () => [],
  },
  saving: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits([
  "approve",
  "reject",
  "request-info",
  "assign-tier",
  "assign-rule",
  "remove-rule",
  "refresh",
  "refresh-securities-symbols",
]);

// 权限检查
const authStore = useAuthStore();
const hasEditRulesPermission = computed(() => {
  return authStore.hasPermission("ib_application_edit_rules");
});
const hasDecisionPermission = computed(() => {
  return authStore.hasPermission("ib_application_decision");
});

// 检查是否是负责的操作员（用于判断是否可以编辑规则）
const isCurrentOperator = computed(() => {
  if (!hasEditRulesPermission.value) return false;
  if (!authStore.user || !props.application.reviewerId) return false;
  // 处理类型转换，确保比较正确（可能是字符串或数字）
  const userId = String(authStore.user.id);
  const reviewerId = String(props.application.reviewerId);
  return userId === reviewerId;
});

// 判断是否是编辑状态（Operator正在编辑规则）还是等待审批状态（Reviewer可以审批）
// 使用 isEdit 字段：0 = 编辑模式（Operator可以编辑），1 = 审批模式（Reviewer可以审批）
const isEditingState = computed(() => {
  if (props.application.applicationStatus !== "in_review") return false;

  // isEdit = 0 表示编辑模式，isEdit = 1 表示审批模式
  // 如果 isEdit 为 undefined 或 null，默认为编辑模式（0）
  const isEdit = props.application.isEdit;
  return isEdit === 0 || isEdit === undefined || isEdit === null;
});

// 检查是否可以编辑规则
const canEditRules = computed(() => {
  if (props.application.applicationStatus === "in_review") {
    // 如果是编辑状态（包括初始未保存状态），只有Operator可以编辑
    if (isEditingState.value) {
      return isCurrentOperator.value;
    }
    // 如果是等待审批状态（已保存且等待审批），Operator和Reviewer都不能编辑
    return false;
  }
  // 在 Rejected 状态下，只有操作员可以编辑
  if (props.application.applicationStatus === "rejected") {
    return isCurrentOperator.value;
  }
  // 其他状态不允许编辑
  return false;
});

// 检查是否可以显示审批按钮（只有当前Reviewer可以审批，且规则已保存）
const canShowDecision = computed(() => {
  if (!isCurrentAuditor.value) return false;
  if (
    props.application.applicationStatus !== "pending" &&
    props.application.applicationStatus !== "in_review"
  )
    return false;

  // 在 in_review 状态下，只有当 isEdit = 1（审批模式）时才显示审批按钮
  // 因为如果 isEdit = 0，说明还在编辑模式，规则还没有被保存，不应该显示审批按钮
  if (props.application.applicationStatus === "in_review") {
    return props.application.isEdit === 1;
  }

  // pending 状态下可以显示审批按钮
  return true;
});

// 检查是否是当前审批人
const isCurrentAuditor = computed(() => {
  if (!authStore.user || !props.application.auditorId) return false;
  // 处理类型转换，确保比较正确（可能是字符串或数字）
  const userId = String(authStore.user.id);
  const auditorId = String(props.application.auditorId);
  return userId === auditorId;
});

// 初始化时确保类型一致（字符串）
const initialTierId = props.application.assignedTierLevelId
  ? String(props.application.assignedTierLevelId)
  : "";
const selectedTierLevelId = ref(initialTierId);
const selectedRuleIds = ref([]);
const originalTierId = ref(initialTierId);
const originalRuleIds = ref([]);
const hasRuleChanges = ref(false);
const hasTierChanges = ref(false);
const kycData = ref([]);
const loadingKyc = ref(false);
const savingRules = ref(false); // 本地保存状态，用于跟踪规则保存

// 合并 prop 的 saving 和本地的 savingRules
const saving = computed(() => props.saving || savingRules.value);

// 个性化规则编辑相关状态
const customProducts = ref({}); // { ruleId: [products] }
const customAdditionalRules = ref({}); // { ruleId: [rules] }
const hasProductChanges = ref({}); // { ruleId: boolean }
const hasAdditionalRulesChanges = ref({}); // { ruleId: boolean }
const additionalRulesEnabled = ref({}); // { ruleId: boolean }
const allSecurities = ref([]);
const customSymbols = ref([]);
const showTiersModal = ref(false);

// 合并所有规则的产品和额外规则的计算属性
const allMergedProducts = computed(() => {
  const merged = [];
  selectedRuleIds.value.forEach((ruleId) => {
    const products = customProducts.value[ruleId] || [];
    products.forEach((product, originalIndex) => {
      merged.push({ ...product, ruleId: String(ruleId), originalIndex });
    });
  });
  return merged;
});

const allMergedAdditionalRules = computed(() => {
  const merged = [];
  selectedRuleIds.value.forEach((ruleId) => {
    const rules = customAdditionalRules.value[ruleId] || [];
    rules.forEach((rule, originalIndex) => {
      merged.push({ ...rule, ruleId: String(ruleId), originalIndex });
    });
  });
  return merged;
});

// 检查是否有任何产品变化
const hasAnyProductChanges = computed(() => {
  return selectedRuleIds.value.some(
    (ruleId) => hasProductChanges.value[ruleId] === true,
  );
});

// 检查是否有任何额外规则变化
const hasAnyAdditionalRulesChanges = computed(() => {
  return selectedRuleIds.value.some(
    (ruleId) => hasAdditionalRulesChanges.value[ruleId] === true,
  );
});

// 检查是否有任何规则启用了额外规则（检查是否有额外规则数据或开关已开启）
const anyAdditionalRulesEnabled = computed(() => {
  // 检查是否有任何规则有额外规则数据
  const hasAdditionalRulesData = selectedRuleIds.value.some((ruleId) => {
    const rules = customAdditionalRules.value[ruleId] || [];
    return rules.length > 0;
  });
  // 或者检查是否有任何规则的开关已开启
  const hasEnabledSwitch = selectedRuleIds.value.some(
    (ruleId) => additionalRulesEnabled.value[ruleId] === true,
  );
  return hasAdditionalRulesData || hasEnabledSwitch;
});
const currentEditingRuleId = ref(null);
const currentEditingRuleIndex = ref(null);
const editingTiers = ref([]);

/**
 * 格式化数组为字符串
 */
const formatArray = (arr) => {
  if (!arr) return "N/A";
  if (typeof arr === "string") {
    try {
      arr = JSON.parse(arr);
    } catch {
      return arr;
    }
  }
  if (Array.isArray(arr)) {
    return arr.join(", ");
  }
  return "N/A";
};

/**
 * 格式化日期时间
 */
const formatDateTime = (dateString) => {
  if (!dateString) return "N/A";
  const date = new Date(dateString);
  return new Intl.DateTimeFormat("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  }).format(date);
};

/**
 * 格式化批准日期
 */
const formatApprovalDate = (dateString) => {
  if (!dateString) return "N/A";
  const date = new Date(dateString);
  return new Intl.DateTimeFormat("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  }).format(date);
};

/**
 * 格式化拒绝日期
 */
const formatRejectionDate = (dateString) => {
  if (!dateString) return "N/A";
  const date = new Date(dateString);
  return new Intl.DateTimeFormat("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  }).format(date);
};

/**
 * 获取层级权限描述
 */
const getTierPermissions = (tier) => {
  const perms = [];
  if (tier.canRecruitSubAgents) perms.push("Recruit");
  if (tier.canViewReports) perms.push("Reports");
  if (tier.canManageClients) perms.push("Manage");
  return perms.length > 0 ? perms.join(" + ") : "Basic Access";
};

/**
 * 获取选中的层级
 */
const getSelectedTier = () => {
  // 确保类型匹配（都转换为数字进行比较）
  const tierId = selectedTierLevelId.value
    ? Number(selectedTierLevelId.value)
    : null;
  return props.tierLevels.find((t) => t.id === tierId);
};

/**
 * 获取规则图标
 */
const getRuleIcon = (ruleType) => {
  const icons = {
    standard: "fa-layer-group",
    premium: "fa-crown",
    ultra: "fa-gem",
    custom: "fa-cog",
  };
  return icons[ruleType] || "fa-file-invoice-dollar";
};

/**
 * 格式化支付周期
 */
const formatPaymentCycle = (cycle) => {
  const cycles = {
    realtime: "Real-time",
    daily: "Daily",
    weekly: "Weekly",
    biweekly: "Bi-weekly",
    monthly: "Monthly",
    quarterly: "Quarterly",
  };
  return cycles[cycle] || cycle;
};

/**
 * 获取规则对象
 */
const getRule = (ruleId) => {
  return props.commissionRules.find((r) => r.id === ruleId);
};

/**
 * 处理层级变更
 */
const handleTierChange = () => {
  if (selectedTierLevelId.value !== originalTierId.value) {
    hasTierChanges.value = true;
  } else {
    hasTierChanges.value = false;
  }
};

/**
 * 标记规则已变更
 */
const markRuleChanged = () => {
  hasRuleChanges.value = true;
};

/**
 * 处理规则分配保存（从组件接收）
 */
const handleRulesAssignmentSave = async (data) => {
  try {
    savingRules.value = true;

    // 保存Tier Level（如果有变化）
    if (data.tierLevelId && data.tierLevelId !== originalTierId.value) {
      const response = await ibApplicationsApi.assignTier(
        props.application.id,
        data.tierLevelId,
      );
      if (!response.success) {
        throw new Error("Failed to save tier level");
      }
      selectedTierLevelId.value = data.tierLevelId;
      originalTierId.value = data.tierLevelId;
      hasTierChanges.value = false;
    }

    // 处理规则分配
    const addedRules = data.ruleIds.filter(
      (id) => !originalRuleIds.value.includes(id),
    );
    const removedRules = originalRuleIds.value.filter(
      (id) => !data.ruleIds.includes(id),
    );

    // 分配新规则
    for (const ruleId of addedRules) {
      const response = await ibApplicationsApi.assignRule(
        props.application.id,
        ruleId,
      );
      if (!response.success) {
        throw new Error(`Failed to assign rule ${ruleId}`);
      }
    }

    // 移除规则
    for (const ruleId of removedRules) {
      const response = await ibApplicationsApi.removeRule(
        props.application.id,
        ruleId,
      );
      if (!response.success) {
        throw new Error(`Failed to remove rule ${ruleId}`);
      }
    }

    selectedRuleIds.value = [...data.ruleIds];
    originalRuleIds.value = [...data.ruleIds];
    hasRuleChanges.value = false;

    // 保存Payment Settings、Products和Additional Rules
    // 即使没有这些数据，也要保存规则分配（至少保存空的规则配置）
    if (data.ruleIds && data.ruleIds.length > 0) {
      const rules = [];
      for (const ruleId of data.ruleIds) {
        // 确保 ruleId 类型匹配（可能是字符串或数字）
        const ruleIdNum = Number(ruleId);
        const rule = props.commissionRules.find(
          (r) => Number(r.id) === ruleIdNum,
        );

        // 如果找不到规则，尝试从 commissionRulesDetails 获取
        let ruleName = rule?.ruleName || "";
        let ruleType = rule?.ruleType || "custom";

        if (!ruleName && props.commissionRulesDetails[ruleIdNum]) {
          ruleName = props.commissionRulesDetails[ruleIdNum].ruleName || "";
          ruleType =
            props.commissionRulesDetails[ruleIdNum].ruleType || "custom";
        }

        // 如果还是没有，使用默认值
        if (!ruleName) {
          ruleName = `Rule ${ruleIdNum}`;
        }

        const ruleData = {
          ruleId: ruleIdNum,
          ruleName: ruleName,
          ruleType: ruleType,
          paymentCycle:
            data.paymentSettings && data.paymentSettings[ruleId]
              ? data.paymentSettings[ruleId].paymentCycle
              : rule?.paymentCycle || "monthly",
          paymentDay:
            data.paymentSettings && data.paymentSettings[ruleId]
              ? data.paymentSettings[ruleId].paymentDay
              : rule?.paymentDay || "",
          minimumPayout:
            data.paymentSettings && data.paymentSettings[ruleId]
              ? data.paymentSettings[ruleId].minimumPayout
              : rule?.minimumPayout || 100.0,
          payoutCurrency:
            data.paymentSettings && data.paymentSettings[ruleId]
              ? data.paymentSettings[ruleId].payoutCurrency
              : rule?.payoutCurrency || "USD",
          products:
            data.products && data.products[ruleId]
              ? data.products[ruleId].map((product) => ({
                  productType: product.productType || "security",
                  productName: product.productName,
                  commissionType: product.commissionType || "per_lot",
                  commissionRate: Number(product.commissionRate),
                  additionalRate: Number(product.additionalRate || 0),
                  minimumVolume: product.minimumVolume || "0.01 lots",
                }))
              : [],
          additionalRules:
            data.additionalRules && data.additionalRules[ruleId]
              ? data.additionalRules[ruleId].map((rule) => ({
                  productType: rule.productType || "security",
                  productName: rule.productName,
                  ruleType: rule.ruleType,
                  ruleValue:
                    rule.ruleType === "volume_tiers"
                      ? null
                      : Number(rule.ruleValue || 0),
                  ruleCondition: rule.ruleCondition || "",
                  tiers: rule.tiers || [],
                }))
              : [],
        };
        rules.push(ruleData);
      }

      const response = await ibApplicationsApi.saveCustomRules(
        props.application.id,
        rules,
      );
      if (!response.success) {
        throw new Error("Failed to save custom rules");
      }
    }

    // 如果是Rejected状态，保存后状态改为in_review，isEdit设置为1
    if (props.application.applicationStatus === "rejected") {
      // 更新状态为in_review，isEdit设置为1（审批模式）
      const updateResponse = await ibApplicationsApi.updateApplication(
        props.application.id,
        {
          applicationStatus: "in_review",
          isEdit: 1,
        },
      );
      if (!updateResponse.success) {
        throw new Error("Failed to update application status");
      }
    }

    const tierInfo = data.tierLevelId
      ? `\nTier Level: ${getSelectedTier()?.tierName || "Selected"}`
      : "";

    alert(
      `✓ Rules assigned successfully!${tierInfo}\n\nTotal Rules: ${data.ruleIds.length}\n\nThese settings will be activated upon application approval.`,
    );

    // 触发刷新
    emit("refresh");
  } catch (error) {
    console.error("Failed to save rules assignment:", error);
    alert(
      "❌ Failed to save rules assignment: " +
        (error.message || "Please try again."),
    );
  } finally {
    savingRules.value = false;
  }
};

/**
 * 保存规则分配和Tier Level（保留用于兼容）
 */
const saveRuleAssignments = async () => {
  // 这个方法保留用于向后兼容，实际应该通过组件调用handleRulesAssignmentSave
  await handleRulesAssignmentSave({
    tierLevelId: selectedTierLevelId.value,
    ruleIds: selectedRuleIds.value,
    paymentSettings: {},
    products: {},
    additionalRules: {},
  });
};

/**
 * 处理批准
 */
const handleApprove = () => {
  if (!selectedTierLevelId.value) {
    alert("⚠️ Please select a tier level before approving.");
    return;
  }

  emit("approve", {
    applicationId: props.application.id,
    tierLevelId: selectedTierLevelId.value,
  });
};

/**
 * 处理拒绝
 */
const handleReject = () => {
  const reason = prompt(
    "⚠️ Reject IB Application\n\nPlease provide a reason for rejection:\n\n(This will be sent to the applicant)",
  );

  if (reason && reason.trim()) {
    const confirmMessage = `Are you sure you want to REJECT this IB application?\n\nReason: ${reason}\n\nThe applicant will be notified.`;

    if (confirm(confirmMessage)) {
      emit("reject", {
        applicationId: props.application.id,
        rejectionReason: reason.trim(),
      });
    }
  }
};

/**
 * 处理请求更多信息
 */
const handleRequestInfo = () => {
  const info = prompt(
    "Request Additional Information\n\nPlease specify what additional information or documents you need from the applicant:",
  );

  if (info && info.trim()) {
    const confirmMessage = `Send information request to applicant?\n\nRequest: ${info}\n\nThe applicant will be notified via email.`;

    if (confirm(confirmMessage)) {
      emit("request-info", {
        applicationId: props.application.id,
        infoRequest: info.trim(),
      });
    }
  }
};

/**
 * 加载KYC数据
 */
const loadKycData = async () => {
  if (!props.application.applicantEmail) return;

  try {
    loadingKyc.value = true;
    const response = await ibApplicationsApi.getKycData(props.application.id);

    if (response.success && response.data) {
      kycData.value = response.data;
    }
  } catch (error) {
    console.error("Failed to load KYC data:", error);
    kycData.value = [];
  } finally {
    loadingKyc.value = false;
  }
};

/**
 * 格式化答案值
 */
const formatAnswerValue = (answer) => {
  const answerValue = answer.answerValue;

  if (answerValue === null || answerValue === undefined || answerValue === "") {
    return "-";
  }

  // 根据问题类型格式化答案
  switch (answer.questionType) {
    case "date":
      try {
        return new Date(answerValue).toLocaleDateString();
      } catch (e) {
        return answerValue;
      }
    case "file_upload":
      if (
        answer.files &&
        Array.isArray(answer.files) &&
        answer.files.length > 0
      ) {
        return answer.files
          .map((f) => {
            const fileName =
              f.fileName || basename(f.filePath || f.downloadUrl || "");
            const downloadUrl = f.downloadUrl || getFileUrl(f.filePath || f);
            return `<a href="${downloadUrl}" target="_blank" class="file-download-link">${fileName}</a>`;
          })
          .join(", ");
      }
      if (Array.isArray(answerValue)) {
        return answerValue
          .map((f) => (typeof f === "string" ? basename(f) : f.fileName || f))
          .join(", ");
      }
      if (typeof answerValue === "string") {
        return basename(answerValue);
      }
      return answerValue;
    case "multiple_choice":
      if (Array.isArray(answerValue)) {
        return answerValue.join(", ");
      }
      return answerValue;
    case "single_choice":
      return answerValue;
    default:
      return answerValue;
  }
};

/**
 * 获取文件名
 */
const basename = (path) => {
  if (!path) return "";
  const parts = path.split("/");
  return parts[parts.length - 1];
};

/**
 * 加载个性化规则
 */
const loadCustomRules = async () => {
  if (!props.application.id) return;

  try {
    const response = await ibApplicationsApi.getCustomRules(
      props.application.id,
    );

    if (response.success && response.data) {
      // 按 ruleId 组织数据
      const rules = response.data;
      rules.forEach((rule) => {
        const ruleId = String(rule.ruleId || rule.id);
        if (rule.products) {
          customProducts.value[ruleId] = rule.products;
        }
        if (rule.additionalRules && rule.additionalRules.length > 0) {
          additionalRulesEnabled.value[ruleId] = true;
          customAdditionalRules.value[ruleId] = rule.additionalRules.map(
            (ar) => ({
              ...ar,
              tierCount: ar.tiers ? ar.tiers.length : 0,
            }),
          );
        }
      });
    }
  } catch (error) {
    console.error("Failed to load custom rules:", error);
  }
};

/**
 * 初始化 Securities 和 Symbols（从 props 获取）
 */
const initializeSecuritiesAndSymbols = () => {
  if (props.securities && props.securities.length > 0) {
    allSecurities.value = props.securities;
  }
  if (props.symbols && props.symbols.length > 0) {
    customSymbols.value = props.symbols;
  }
};

/**
 * 标记产品已变更
 */
const markProductChanged = (ruleId) => {
  if (!hasProductChanges.value[ruleId]) {
    hasProductChanges.value = { ...hasProductChanges.value, [ruleId]: true };
  }
};

/**
 * 标记额外规则已变更
 */
const markAdditionalRulesChanged = (ruleId) => {
  if (!hasAdditionalRulesChanges.value[ruleId]) {
    hasAdditionalRulesChanges.value = {
      ...hasAdditionalRulesChanges.value,
      [ruleId]: true,
    };
  }
};

/**
 * 添加产品
 */
const addProduct = (ruleId) => {
  if (!customProducts.value[ruleId]) {
    customProducts.value = { ...customProducts.value, [ruleId]: [] };
  }
  customProducts.value[ruleId].push({
    productType: "security",
    productName: "",
    commissionType: "per_lot",
    commissionRate: 0,
    additionalRate: 0,
    minimumVolume: "0.01 lots",
  });
  markProductChanged(ruleId);
};

// 为所有规则添加产品（合并模式）
const addProductToAllRules = () => {
  if (selectedRuleIds.value.length === 0) {
    alert("⚠️ Please select at least one rule first.");
    return;
  }
  // 默认添加到第一个规则
  const firstRuleId = String(selectedRuleIds.value[0]);
  addProduct(firstRuleId);
};

/**
 * 移除产品
 */
const removeProduct = (ruleId, index) => {
  if (
    customProducts.value[ruleId] &&
    customProducts.value[ruleId].length <= 1
  ) {
    alert("⚠️ You must have at least one product.");
    return;
  }

  if (confirm("Are you sure you want to remove this product?")) {
    customProducts.value[ruleId].splice(index, 1);
    markProductChanged(ruleId);
  }
};

/**
 * 保存产品配置
 */
const saveProducts = async (ruleId) => {
  const products = customProducts.value[ruleId] || [];

  if (products.length === 0) {
    alert("⚠️ Please add at least one product before saving.");
    return;
  }

  const invalidProducts = products.filter(
    (p) =>
      !p.productName ||
      p.commissionRate === null ||
      p.commissionRate === undefined,
  );
  if (invalidProducts.length > 0) {
    alert(
      "⚠️ Please fill in all required fields:\n- Product Name (required)\n- Commission Rate (required)",
    );
    return;
  }

  try {
    // 构建规则数据
    const rules = [
      {
        ruleId: Number(ruleId),
        products: products.map((product) => ({
          productType: product.productType || "security",
          productName: product.productName,
          commissionType: product.commissionType || "per_lot",
          commissionRate: Number(product.commissionRate),
          additionalRate: Number(product.additionalRate || 0),
          minimumVolume: product.minimumVolume || "0.01 lots",
        })),
      },
    ];

    const response = await ibApplicationsApi.saveCustomRules(
      props.application.id,
      rules,
    );

    if (response.success) {
      hasProductChanges.value = { ...hasProductChanges.value, [ruleId]: false };
      alert("✓ Products saved successfully!");
      emit("refresh");
    } else {
      throw new Error(response.message || "Failed to save products");
    }
  } catch (error) {
    console.error("Failed to save products:", error);
    alert(
      "❌ Failed to save products: " + (error.message || "Please try again."),
    );
  }
};

// 保存所有规则的产品（合并模式）
const saveAllProducts = async () => {
  if (!hasAnyProductChanges.value) {
    return;
  }

  try {
    savingRules.value = true;

    // 为每个有变化的规则保存产品
    for (const ruleId of selectedRuleIds.value) {
      if (hasProductChanges.value[ruleId]) {
        await saveProducts(ruleId);
      }
    }

    alert("✓ All products saved successfully!");
    emit("refresh");
  } catch (error) {
    console.error("Failed to save products:", error);
    alert(
      "❌ Failed to save products: " + (error.message || "Please try again."),
    );
  } finally {
    savingRules.value = false;
  }
};

/**
 * 切换额外规则
 */
const toggleAdditionalRules = (ruleId) => {
  const products = customProducts.value[ruleId] || [];
  if (!additionalRulesEnabled.value[ruleId] && products.length === 0) {
    alert(
      "⚠️ Please add at least one product in the Product Commission Configuration table first.",
    );
    return;
  }
  additionalRulesEnabled.value = {
    ...additionalRulesEnabled.value,
    [ruleId]: !additionalRulesEnabled.value[ruleId],
  };
};

// 切换所有规则的额外规则（合并模式）
const toggleAllAdditionalRules = () => {
  if (selectedRuleIds.value.length === 0) {
    return;
  }

  const newState = !anyAdditionalRulesEnabled.value;

  // 检查是否有产品
  const hasAnyProducts = selectedRuleIds.value.some((ruleId) => {
    const products = customProducts.value[ruleId] || [];
    return products.length > 0;
  });

  if (newState && !hasAnyProducts) {
    alert(
      "⚠️ Please add at least one product in the Product Commission Configuration table first.",
    );
    return;
  }

  // 为所有规则设置相同的状态
  selectedRuleIds.value.forEach((ruleId) => {
    additionalRulesEnabled.value = {
      ...additionalRulesEnabled.value,
      [ruleId]: newState,
    };
  });
};

/**
 * 添加额外规则
 */
const addAdditionalRule = (ruleId) => {
  if (!customAdditionalRules.value[ruleId]) {
    customAdditionalRules.value = {
      ...customAdditionalRules.value,
      [ruleId]: [],
    };
  }
  customAdditionalRules.value[ruleId].push({
    productType: "security",
    productName: "",
    ruleType: "bonus_commission",
    ruleValue: 0,
    ruleCondition: "",
    tierCount: 0,
    isActive: 1,
  });
  markAdditionalRulesChanged(ruleId);
};

// 为所有规则添加额外规则（合并模式）
const addAdditionalRuleToAllRules = () => {
  if (selectedRuleIds.value.length === 0) {
    alert("⚠️ Please select at least one rule first.");
    return;
  }
  // 默认添加到第一个规则
  const firstRuleId = String(selectedRuleIds.value[0]);
  addAdditionalRule(firstRuleId);
};

/**
 * 移除额外规则
 */
const removeAdditionalRule = (ruleId, index) => {
  if (confirm("Are you sure you want to remove this additional rule?")) {
    customAdditionalRules.value[ruleId].splice(index, 1);
    markAdditionalRulesChanged(ruleId);
  }
};

/**
 * 更新额外规则列显示
 */
const updateAdditionalRuleColumns = (ruleId, index) => {
  const rule = customAdditionalRules.value[ruleId][index];
  if (rule.ruleType === "volume_tiers") {
    rule.ruleValue = null;
  }
  markAdditionalRulesChanged(ruleId);
};

/**
 * 获取值输入框占位符
 */
const getValuePlaceholder = (ruleType) => {
  const placeholders = {
    bonus_commission: "$/lot",
    volume_multiplier: "Multiplier (e.g., 1.25)",
    performance_bonus: "% of base commission",
    cash_rebate: "Rebate $/lot",
  };
  return placeholders[ruleType] || "Value";
};

/**
 * 打开管理层级模态框
 */
const openManageTiersModal = (ruleId, ruleIndex) => {
  currentEditingRuleId.value = ruleId;
  currentEditingRuleIndex.value = ruleIndex;
  const rule = customAdditionalRules.value[ruleId][ruleIndex];

  if (rule.tiers && rule.tiers.length > 0) {
    editingTiers.value = rule.tiers.map((t) => ({ ...t }));
  } else {
    editingTiers.value = [
      {
        tierLevel: 1,
        tierName: "Starter Level",
        commissionRate: 8.0,
        minimumVolume: 0,
        maximumVolume: "100",
      },
    ];
  }

  showTiersModal.value = true;
};

/**
 * 关闭层级模态框
 */
const closeTiersModal = () => {
  showTiersModal.value = false;
  currentEditingRuleId.value = null;
  currentEditingRuleIndex.value = null;
};

/**
 * 添加层级
 */
const addTierEdit = () => {
  const tierLevel = editingTiers.value.length + 1;
  editingTiers.value.push({
    tierLevel,
    tierName: `Tier ${tierLevel}`,
    commissionRate: 0,
    minimumVolume: 0,
    maximumVolume: "Unlimited",
  });
};

/**
 * 移除层级
 */
const removeTierEdit = (index) => {
  if (editingTiers.value.length <= 1) {
    alert("⚠️ You must have at least one tier.");
    return;
  }

  if (confirm("Are you sure you want to remove this tier?")) {
    editingTiers.value.splice(index, 1);
  }
};

/**
 * 保存层级
 */
const saveTiersModal = () => {
  for (let i = 0; i < editingTiers.value.length; i++) {
    if (!editingTiers.value[i].commissionRate) {
      alert(`⚠️ Please enter commission rate for Tier ${i + 1}`);
      return;
    }
  }

  if (
    currentEditingRuleId.value !== null &&
    currentEditingRuleIndex.value !== null
  ) {
    const rule =
      customAdditionalRules.value[currentEditingRuleId.value][
        currentEditingRuleIndex.value
      ];
    rule.tiers = editingTiers.value.map((t) => ({ ...t }));
    rule.tierCount = editingTiers.value.length;
    rule.ruleValue = `${editingTiers.value.length} Tiers`;
    markAdditionalRulesChanged(currentEditingRuleId.value);
  }

  closeTiersModal();
  alert(
    `✓ Commission tiers saved successfully!\n\nTotal Tiers: ${editingTiers.value.length}`,
  );
};

/**
 * 保存额外规则
 */
const saveAdditionalRules = async (ruleId) => {
  const rules = customAdditionalRules.value[ruleId] || [];

  if (rules.length === 0) {
    alert("⚠️ Please add at least one additional rule before saving.");
    return;
  }

  const invalidRules = rules.filter((r) => !r.productName || !r.ruleType);
  if (invalidRules.length > 0) {
    alert(
      "⚠️ Please fill in all required fields:\n- Product Name (required)\n- Rule Type (required)",
    );
    return;
  }

  try {
    // 构建规则数据
    const customRules = [
      {
        ruleId: Number(ruleId),
        additionalRules: rules.map((rule) => ({
          productType: rule.productType || "security",
          productName: rule.productName,
          ruleType: rule.ruleType,
          ruleValue:
            rule.ruleType === "volume_tiers"
              ? null
              : Number(rule.ruleValue || 0),
          ruleCondition: rule.ruleCondition || "",
          tiers: rule.tiers || [],
        })),
      },
    ];

    const response = await ibApplicationsApi.saveCustomRules(
      props.application.id,
      customRules,
    );

    if (response.success) {
      hasAdditionalRulesChanges.value = {
        ...hasAdditionalRulesChanges.value,
        [ruleId]: false,
      };
      alert("✓ Additional rules saved successfully!");
      emit("refresh");
    } else {
      throw new Error(response.message || "Failed to save additional rules");
    }
  } catch (error) {
    console.error("Failed to save additional rules:", error);
    alert(
      "❌ Failed to save additional rules: " +
        (error.message || "Please try again."),
    );
  }
};

// 保存所有规则的额外规则（合并模式）
const saveAllAdditionalRules = async () => {
  if (!hasAnyAdditionalRulesChanges.value) {
    return;
  }

  try {
    savingRules.value = true;

    // 为每个有变化的规则保存额外规则
    for (const ruleId of selectedRuleIds.value) {
      if (hasAdditionalRulesChanges.value[ruleId]) {
        await saveAdditionalRules(ruleId);
      }
    }

    alert("✓ All additional rules saved successfully!");
    emit("refresh");
  } catch (error) {
    console.error("Failed to save additional rules:", error);
    alert(
      "❌ Failed to save additional rules: " +
        (error.message || "Please try again."),
    );
  } finally {
    savingRules.value = false;
  }
};

// 监听 application 的变化，更新 tier level
watch(
  () => props.application.tierLevel,
  (newTierId) => {
    // 处理数字和字符串类型，以及空值
    const tierId =
      newTierId !== undefined && newTierId !== null && newTierId !== ""
        ? String(newTierId)
        : "";

    if (tierId !== selectedTierLevelId.value) {
      selectedTierLevelId.value = tierId;
      originalTierId.value = tierId;
      hasTierChanges.value = false; // 重置变化标记
    }
  },
  { immediate: true },
);

// 监听 application 的变化，更新规则
watch(
  () => props.application.preAssignedRulesCount,
  async (newCount) => {
    if (newCount > 0) {
      // 优先使用已加载的数据，避免重复调用 API
      if (
        applicationDetails.value?.preAssignedRules &&
        Array.isArray(applicationDetails.value.preAssignedRules) &&
        applicationDetails.value.preAssignedRules.length > 0
      ) {
        selectedRuleIds.value = applicationDetails.value.preAssignedRules.map(
          (r) => String(r.id),
        );
        originalRuleIds.value = [...selectedRuleIds.value];
        hasRuleChanges.value = false;
      } else {
        // 如果已加载的数据中没有，才调用 API
        try {
          const response = await ibApplicationsApi.getApplication(
            props.application.id,
          );

          if (response.success && response.data?.preAssignedRules) {
            selectedRuleIds.value = response.data.preAssignedRules.map((r) =>
              String(r.id),
            );
            originalRuleIds.value = [...selectedRuleIds.value];
            hasRuleChanges.value = false;
          }
        } catch (error) {
          console.error("Failed to load pre-assigned rules:", error);
        }
      }
    }
  },
);

// 存储从详情接口获取的完整数据（包含 rejectionReason 等字段）
const applicationDetails = ref(null);

// 监听 application.id 的变化，重新加载KYC数据和详情数据
watch(
  () => props.application.id,
  async (newId) => {
    if (newId) {
      await Promise.all([loadKycData(), loadApplicationDetails()]);
    }
  },
);

// 加载申请详情数据（包含 rejectionReason）
const loadApplicationDetails = async () => {
  if (!props.application.id) return;

  try {
    const response = await ibApplicationsApi.getApplication(
      props.application.id,
    );
    if (response.success && response.data) {
      applicationDetails.value = response.data;
    }
  } catch (error) {
    console.error("Failed to load application details:", error);
  }
};

// 合并后的 application 对象，确保包含 rejectionReason
const mergedApplication = computed(() => {
  if (applicationDetails.value) {
    // 如果有详情数据，合并详情数据和 props.application
    return {
      ...props.application,
      ...applicationDetails.value,
    };
  }
  // 如果没有详情数据，直接返回 props.application
  return props.application;
});

// 监听 securities 和 symbols 变化，更新本地数据
watch(
  () => props.securities,
  (newSecurities) => {
    if (newSecurities && newSecurities.length > 0) {
      allSecurities.value = newSecurities;
    }
  },
  { deep: true },
);

watch(
  () => props.symbols,
  (newSymbols) => {
    if (newSymbols && newSymbols.length > 0) {
      customSymbols.value = newSymbols;
    }
  },
  { deep: true },
);

// 加载已分配的规则和KYC数据
onMounted(async () => {
  // 加载详情数据（包含 rejectionReason、preAssignedRules、customRules 等）
  await loadApplicationDetails();

  // 使用已加载的数据设置 preAssignedRules，避免重复调用 API
  if (
    applicationDetails.value?.preAssignedRules &&
    Array.isArray(applicationDetails.value.preAssignedRules) &&
    applicationDetails.value.preAssignedRules.length > 0
  ) {
    selectedRuleIds.value = applicationDetails.value.preAssignedRules.map((r) =>
      String(r.id),
    );
    originalRuleIds.value = [...selectedRuleIds.value];
  }

  // 初始化Securities和Symbols（从props获取）
  initializeSecuritiesAndSymbols();

  // 加载KYC数据和个性化规则
  await Promise.all([loadKycData(), loadCustomRules()]);
});
</script>

<style scoped>
.detail-content {
  padding: 30px;
  background: var(--color-surface-soft);
}

.detail-sections {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
  gap: 20px;
  margin-bottom: 20px;
}

.detail-section {
  background: var(--color-surface);
  border-radius: var(--radius-md);
  padding: 25px;
  border: 2px solid var(--color-border);
  transition: all 0.3s ease;
}

.detail-section:hover {
  border-color: var(--color-border-strong);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.detail-section.full-width {
  grid-column: 1 / -1;
}

/* Basic Information section特殊样式 */
.basic-info-section {
  padding: 30px;
  background: linear-gradient(
    to bottom,
    var(--color-surface) 0%,
    var(--color-surface-soft) 100%
  );
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
  flex-direction: column;
  padding: 16px 18px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border);
  transition: all 0.2s ease;
  min-height: 70px;
}

.detail-field:hover {
  background: var(--color-surface-soft);
  border-color: var(--color-border-strong);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
}

.detail-label {
  font-weight: 600;
  color: var(--color-text);
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  gap: 6px;
}

.detail-label::before {
  content: "";
  width: 3px;
  height: 12px;
  background: var(--color-brand-solid);
  border-radius: 2px;
  display: inline-block;
}

.detail-value {
  color: var(--color-ink);
  font-size: 14px;
  font-weight: 500;
  line-height: 1.5;
  word-break: break-word;
}

.basic-info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 18px;
  position: relative;
  padding: 8px 0;
}

.basic-info-grid::before {
  content: "";
  position: absolute;
  left: 50%;
  top: 20px;
  bottom: 20px;
  width: 2px;
  background: linear-gradient(
    to bottom,
    transparent 0%,
    var(--color-border-strong) 5%,
    var(--color-border-strong) 95%,
    transparent 100%
  );
  transform: translateX(-50%);
  pointer-events: none;
  border-radius: 1px;
  box-shadow: 0 0 4px rgba(var(--color-brand-rgb), 0.1);
}

/* 左列样式 */
.basic-info-grid > .detail-field:nth-child(odd) {
  margin-right: 8px;
}

/* 右列样式 */
.basic-info-grid > .detail-field:nth-child(even) {
  margin-left: 8px;
}

.file-download-link {
  color: var(--color-brand);
  text-decoration: none;
  font-weight: 500;
  transition: color 0.2s ease;
}

.file-download-link:hover {
  color: var(--color-brand-strong);
  text-decoration: underline;
}

@media (max-width: 768px) {
  .basic-info-grid {
    grid-template-columns: 1fr;
  }

  .basic-info-grid::before {
    display: none;
  }

  .detail-field {
    min-height: auto;
    padding: 14px 16px;
  }
}

/* Rejection Reason Box */
.rejection-reason-box {
  margin-top: 30px;
  padding: 20px;
  background: var(--color-danger-soft);
  border: 2px solid var(--color-danger-border);
  border-left: 4px solid var(--color-danger);
  border-radius: var(--radius-md);
}

.rejection-reason-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;
  color: var(--color-danger);
  font-size: 14px;
  font-weight: 600;
}

.rejection-reason-header i {
  font-size: 18px;
}

.rejection-reason-content {
  color: var(--color-danger);
  font-size: 14px;
  line-height: 1.6;
  padding: 12px;
  background: var(--color-surface);
  border-radius: var(--radius-sm);
  border: 1px solid var(--color-danger-border);
}

/* Assign Rules Footer */
.assign-rules-footer {
  margin-top: 30px;
  padding-top: 20px;
  border-top: 2px solid var(--color-border);
  display: flex;
  justify-content: flex-end;
}

.assign-rules-footer .btn-save {
  padding: 12px 30px;
  font-size: 14px;
  min-width: 150px;
  justify-content: center;
}

.ib-actions-section {
  background: var(--color-surface);
  border-radius: var(--radius-md);
  padding: 25px;
  border: 2px solid var(--color-border);
  margin-top: 20px;
}

.ib-actions-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 2px solid var(--color-border);
}

.ib-actions-header h3 {
  font-size: 16px;
  color: var(--color-ink);
  margin: 0;
}

.ib-actions-header i {
  color: var(--color-brand);
}

.ib-actions-buttons {
  display: flex;
  gap: 15px;
  align-items: center;
}

.btn-ib-action {
  padding: 12px 30px;
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

.btn-ib-action:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-approve-all {
  background: linear-gradient(
    135deg,
    var(--color-success) 0%,
    var(--color-success) 100%
  );
  color: white;
  box-shadow: 0 2px 8px rgba(72, 187, 120, 0.3);
}

.btn-approve-all:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(72, 187, 120, 0.4);
}

.btn-reject {
  background: var(--color-border);
  color: var(--color-text);
}

.btn-reject:hover:not(:disabled) {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.btn-request-info {
  background: linear-gradient(
    135deg,
    var(--color-warning) 0%,
    var(--color-warning) 100%
  );
  color: white;
  box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
}

.btn-request-info:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
}

.info-banner {
  background: var(--color-brand-soft);
  padding: 12px;
  border-radius: var(--radius-md);
  margin-bottom: 20px;
  border-left: 4px solid var(--color-brand);
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: var(--color-text);
}

.setup-step {
  margin-bottom: 30px;
}

.step-title {
  font-weight: 600;
  color: var(--color-ink);
  font-size: 15px;
  margin-bottom: 15px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.step-title i {
  color: var(--color-brand);
}

.info-note {
  background: var(--color-warning-soft);
  padding: 12px;
  border-radius: var(--radius-sm);
  margin-bottom: 15px;
  border-left: 3px solid var(--color-warning);
  font-size: 12px;
  color: var(--color-warning);
}

.tier-selection-box {
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 25px;
  margin-bottom: 15px;
}

.form-label {
  font-size: 14px;
  color: var(--color-text);
  font-weight: 600;
  display: block;
  margin-bottom: 12px;
}

.form-select-large {
  width: 100%;
  padding: 14px 18px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  transition: all 0.3s ease;
}

.form-select-large:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.tier-preview {
  padding: 15px;
  background: var(--color-success-soft);
  border-radius: var(--radius-md);
  border-left: 4px solid var(--color-success);
}

.preview-header {
  font-weight: 600;
  color: var(--color-success);
  font-size: 13px;
  margin-bottom: 10px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.preview-content {
  font-size: 12px;
  color: var(--color-success);
  line-height: 1.6;
}

.preview-footer {
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px solid var(--color-success-border);
  font-size: 11px;
  color: var(--color-success);
}

.rules-selection-box {
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 20px;
  margin-bottom: 20px;
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px;
}

.rule-checkbox {
  padding: 12px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-sm);
  border: 2px solid transparent;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  gap: 12px;
  cursor: pointer;
}

.rule-checkbox:hover {
  border-color: var(--color-border-strong);
}

.rule-checkbox:has(input:checked) {
  background: var(--color-brand-soft);
  border-color: var(--color-brand);
}

.rule-checkbox input[type="checkbox"] {
  width: 18px;
  height: 18px;
  accent-color: var(--color-brand);
  cursor: pointer;
  flex-shrink: 0;
}

.rule-checkbox label {
  cursor: pointer;
  flex: 1;
}

.rule-name {
  font-weight: 600;
  color: var(--color-ink);
  font-size: 14px;
  margin-bottom: 3px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.rule-meta {
  font-size: 12px;
  color: var(--color-muted);
}

.active-badge {
  color: var(--color-success);
  font-weight: 600;
}

.selected-rules-preview {
  margin-top: 20px;
}

.preview-title {
  font-weight: 600;
  color: var(--color-ink);
  font-size: 14px;
  margin-bottom: 15px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.empty-state-rules {
  text-align: center;
  padding: 40px 20px;
  color: var(--color-faint);
  background: var(--color-surface);
  border: 2px dashed var(--color-border);
  border-radius: var(--radius-md);
  margin-top: 10px;
}

.empty-state-rules i {
  font-size: 48px;
  margin-bottom: 15px;
  display: block;
}

.empty-state-rules p {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-muted);
  margin: 0;
}

.empty-subtitle {
  font-size: 13px;
  color: var(--color-faint);
  margin-top: 5px !important;
}

.rule-preview-card {
  margin-bottom: 20px;
  padding: 25px;
  background: var(--color-surface);
  border-radius: var(--radius-md);
  border: 2px solid var(--color-brand);
  box-shadow: 0 2px 8px rgba(var(--color-brand-rgb), 0.15);
  transition: all 0.3s ease;
}

.rule-preview-card:hover {
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.25);
}

.rule-preview-header {
  display: flex;
  justify-content: space-between;
  align-items: start;
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 2px solid var(--color-border);
}

.rule-preview-name {
  font-weight: 600;
  color: var(--color-ink);
  font-size: 16px;
  margin-bottom: 5px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.rule-preview-name i {
  color: var(--color-brand);
}

.rule-preview-desc {
  font-size: 13px;
  color: var(--color-muted);
}

.status-badge.active {
  background: var(--color-success-soft);
  color: var(--color-success);
  padding: 4px 12px;
  border-radius: var(--radius-lg);
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
}

.rule-preview-section {
  margin-bottom: 20px;
}

.rule-preview-section:last-child {
  margin-bottom: 0;
}

.rule-preview-section h4 {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 12px;
  padding-bottom: 10px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  align-items: center;
  gap: 8px;
}

.rule-preview-section h4 i {
  color: var(--color-brand);
}

.rule-info-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 15px;
  padding: 15px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-md);
}

.rule-info-item {
  display: flex;
  flex-direction: column;
}

.rule-info-label {
  font-size: 12px;
  color: var(--color-muted);
  font-weight: 600;
  text-transform: uppercase;
  margin-bottom: 4px;
}

.rule-info-value {
  font-size: 14px;
  color: var(--color-ink);
  font-weight: 600;
}

.product-count-badge {
  padding: 12px;
  background: var(--color-brand-soft);
  border-radius: var(--radius-sm);
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: var(--color-brand);
  font-weight: 600;
}

.product-count-badge i {
  font-size: 16px;
}

.additional-rules-status {
  padding: 15px;
  border-radius: var(--radius-md);
  border-left: 3px solid var(--color-warning);
}

.additional-rules-status.has-rules {
  background: var(--color-success-soft);
  border-left-color: var(--color-success);
}

.additional-rules-status:not(.has-rules) {
  background: var(--color-warning-soft);
  border-left-color: var(--color-warning);
}

.status-header {
  font-weight: 600;
  font-size: 13px;
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.additional-rules-status.has-rules .status-header {
  color: var(--color-ink);
}

.additional-rules-status:not(.has-rules) .status-header {
  color: var(--color-ink);
}

.status-header i {
  font-size: 14px;
}

.additional-rules-status.has-rules .status-header i {
  color: var(--color-success);
}

.additional-rules-status:not(.has-rules) .status-header i {
  color: var(--color-warning);
}

.status-content {
  font-size: 12px;
  line-height: 1.6;
}

.additional-rules-status.has-rules .status-content {
  color: var(--color-success);
}

.additional-rules-status:not(.has-rules) .status-content {
  color: var(--color-muted);
}

@media (max-width: 768px) {
  .detail-sections {
    grid-template-columns: 1fr;
  }

  .ib-actions-buttons {
    flex-direction: column;
    width: 100%;
  }

  .btn-ib-action {
    width: 100%;
    justify-content: center;
  }

  .rule-info-grid {
    grid-template-columns: 1fr;
  }
}

/* Status Messages */
.status-message {
  padding: 40px 30px;
  text-align: center;
  border-radius: var(--radius-lg);
  margin-bottom: 20px;
}

.status-message-content {
  max-width: 600px;
  margin: 0 auto;
}

.status-message-content i {
  font-size: 48px;
  margin-bottom: 20px;
  display: block;
}

.status-message-title {
  font-size: 20px;
  font-weight: 700;
  color: var(--color-ink);
  margin-bottom: 12px;
}

.status-message-description {
  font-size: 14px;
  color: var(--color-muted);
  line-height: 1.6;
}

.pending-message {
  background: var(--color-warning-soft);
  border: 2px solid #fbbf24;
}

.pending-message i {
  color: var(--color-warning);
}

.under-review-message {
  background: var(--color-brand-soft);
  border: 2px solid var(--color-brand);
}

.under-review-message i {
  color: var(--color-brand);
}

.approved-message {
  background: var(--color-success-soft);
  border: 2px solid var(--color-success);
}

.approved-message i {
  color: var(--color-success);
}

.rejected-message {
  background: var(--color-danger-soft);
  border: 2px solid var(--color-danger);
}

.rejected-message i {
  color: var(--color-danger);
}

.rejected-message .rejection-reason-box {
  margin-top: 20px;
  padding: 15px;
  background: var(--color-surface);
  border-radius: var(--radius-md);
  text-align: left;
}

.rejected-message .rejection-reason-box strong {
  display: block;
  margin-bottom: 8px;
  color: var(--color-ink);
}

/* Custom Rules Section */
.custom-rule-section {
  margin-bottom: 30px;
  padding: 20px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-md);
  border: 2px solid var(--color-border);
}

.custom-rule-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 2px solid var(--color-border);
}

.custom-rule-title {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 18px;
  font-weight: 700;
  color: var(--color-ink);
}

.custom-rule-content {
  margin-top: 20px;
}

.custom-rule-content h4 {
  font-size: 16px;
  color: var(--color-ink);
  margin-bottom: 15px;
}

/* Toggle Additional Rules */
.toggle-additional-rules {
  margin-top: 20px;
  padding: 15px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-md);
  border: 2px dashed var(--color-border-strong);
}

.toggle-additional-rules .toggle-label {
  display: flex;
  justify-content: space-between;
  align-items: center;
  cursor: pointer;
}

.toggle-additional-rules .toggle-label span {
  font-weight: 600;
  color: var(--color-ink);
  font-size: 14px;
}

.toggle-additional-rules p {
  font-size: 12px;
  color: var(--color-muted);
  margin-top: 8px;
  margin-bottom: 0;
}

.toggle-switch {
  position: relative;
  width: 50px;
  height: 26px;
  background: var(--color-border-strong);
  border-radius: 13px;
  cursor: pointer;
  transition: all 0.4s ease;
}

.toggle-switch.active {
  background: var(--color-success-solid);
}

.toggle-switch::before {
  content: "";
  position: absolute;
  height: 18px;
  width: 18px;
  left: 4px;
  bottom: 4px;
  background-color: var(--color-surface);
  border-radius: 50%;
  transition: all 0.4s ease;
}

.toggle-switch.active::before {
  transform: translateX(24px);
}

/* Additional Rule Section */
.additional-rule-section {
  margin-top: 20px;
  padding: 20px;
  background: var(--color-surface);
  border-radius: var(--radius-md);
  border: 2px solid var(--color-border);
}

/* Modal Styles */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.6);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
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

.modal-container {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  width: 90%;
  max-width: 800px;
  max-height: 90vh;
  overflow-y: auto;
  animation: slideUp 0.3s ease;
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.modal-header {
  padding: 25px 30px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--color-brand-solid);
  color: white;
  border-radius: 12px 12px 0 0;
}

.modal-header h3 {
  font-size: 20px;
  font-weight: 700;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 12px;
}

.modal-close {
  background: rgba(255, 255, 255, 0.2);
  border: none;
  color: white;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  transition: all 0.3s ease;
}

.modal-close:hover {
  background: rgba(255, 255, 255, 0.3);
}

.modal-body {
  padding: 30px;
}

.modal-footer {
  padding: 20px 30px;
  border-top: 2px solid var(--color-border);
  background: var(--color-surface-soft);
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  border-radius: 0 0 12px 12px;
}

.btn-primary {
  background: var(--color-brand-solid);
  color: white;
  box-shadow: 0 2px 8px rgba(var(--color-brand-rgb), 0.3);
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.4);
}

/* Tiers Edit */
.tiers-list {
  margin-bottom: 20px;
}

.tier-edit-item {
  background: var(--color-surface-soft);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 20px;
  margin-bottom: 15px;
}

.tier-edit-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
  padding-bottom: 10px;
  border-bottom: 1px solid var(--color-border);
}

.tier-edit-level {
  font-size: 16px;
  font-weight: 700;
  color: var(--color-brand);
}

.tier-edit-content {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 15px;
}

.tier-edit-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.tier-edit-field label {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-muted);
}

.btn-add-tier {
  width: 100%;
  padding: 12px;
  background: var(--color-brand-soft);
  border: 2px dashed var(--color-brand);
  border-radius: var(--radius-md);
  color: var(--color-brand);
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.btn-add-tier:hover {
  background: var(--color-brand-soft);
  border-color: var(--color-brand-strong);
}

.btn-manage-tiers {
  background: var(--color-brand-solid);
  color: white;
  padding: 6px 12px;
  font-size: 12px;
}

.btn-manage-tiers:hover {
  background: var(--color-brand-strong);
}

.btn-disabled {
  background: var(--color-border);
  color: var(--color-faint);
  cursor: not-allowed;
}

.btn-icon {
  background: none;
  border: none;
  cursor: pointer;
  padding: 6px;
  border-radius: 4px;
  transition: all 0.2s ease;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.btn-delete {
  color: var(--color-danger);
}

.btn-delete:hover {
  background: var(--color-danger-soft);
}
</style>
