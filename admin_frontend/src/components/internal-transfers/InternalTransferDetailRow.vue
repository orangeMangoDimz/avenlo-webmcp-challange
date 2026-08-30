<template>
  <tr :class="['detail-row', { show: isExpanded }]">
    <td colspan="10">
      <div class="detail-content">
        <div v-if="loading" class="loading-state">
          <i class="fas fa-spinner fa-spin"></i>
          <p>{{ t("internalXfer_detail_loading") }}</p>
        </div>

        <div v-else-if="error" class="error-state">
          <i class="fas fa-exclamation-circle"></i>
          <p>{{ error }}</p>
        </div>

        <div v-else-if="transferDetails">
          <div class="detail-sections">
            <!-- Transaction Details -->
            <div class="detail-section">
              <h3>
                <i class="fas fa-receipt"></i>
                {{ t("internalXfer_detail_section_transaction") }}
              </h3>
              <div class="detail-field">
                <span class="detail-label">{{
                  t("internalXfer_detail_label_transactionId")
                }}</span>
                <span class="detail-value">{{
                  transferDetails.transactionId
                }}</span>
              </div>
              <div class="detail-field">
                <span class="detail-label">{{
                  t("internalXfer_detail_label_sourceType")
                }}</span>
                <span class="detail-value">{{ sourceTypeLabel }}</span>
              </div>
              <div class="detail-field">
                <span class="detail-label">{{
                  t("internalXfer_detail_label_amountUsd")
                }}</span>
                <span class="detail-value highlight">{{
                  formatCurrency(transferDetails.amount)
                }}</span>
              </div>
              <div class="detail-field">
                <span class="detail-label">{{
                  t("internalXfer_detail_label_status")
                }}</span>
                <span :class="['status-badge', transferDetails.status]">
                  {{ getStatusLabel(transferDetails.status) }}
                </span>
              </div>
            </div>

            <!-- Client Information -->
            <div class="detail-section">
              <h3>
                <i class="fas fa-user-circle"></i>
                {{ t("internalXfer_detail_section_client") }}
              </h3>
              <div class="detail-field">
                <span class="detail-label">{{
                  t("internalXfer_detail_label_fullName")
                }}</span>
                <span class="detail-value"
                  >{{ transferDetails.firstName }}
                  {{ transferDetails.lastName }}</span
                >
              </div>
              <div class="detail-field">
                <span class="detail-label">{{
                  t("internalXfer_detail_label_email")
                }}</span>
                <span class="detail-value">{{ transferDetails.email }}</span>
              </div>
              <div class="detail-field" v-if="transferDetails.phone">
                <span class="detail-label">{{
                  t("internalXfer_detail_label_phone")
                }}</span>
                <span class="detail-value">{{ transferDetails.phone }}</span>
              </div>
              <div class="detail-field" v-if="transferDetails.country">
                <span class="detail-label">{{
                  t("internalXfer_detail_label_country")
                }}</span>
                <span class="detail-value">{{ transferDetails.country }}</span>
              </div>
            </div>

            <div v-if="!isWalletSource" class="detail-section">
              <h3>
                <i class="fas fa-arrow-right-from-bracket"></i>
                {{ t("internalXfer_detail_section_source") }}
              </h3>
              <div class="detail-field">
                <span class="detail-label">{{
                  t("internalXfer_detail_label_platform")
                }}</span>
                <span class="detail-value">
                  <span
                    :class="[
                      'platform-badge',
                      `platform-${fromPlatformMeta.key}`,
                    ]"
                  >
                    <i :class="fromPlatformMeta.icon"></i>
                    {{ fromPlatformMeta.label }}
                  </span>
                </span>
              </div>
              <div class="detail-field">
                <span class="detail-label">{{
                  t("internalXfer_detail_label_source")
                }}</span>
                <span class="detail-value">
                  <span
                    v-if="isWalletSource"
                    class="from-type-badge available-balance"
                  >
                    <i class="fas fa-wallet"></i> {{ t("depositMgmt_wallet") }}
                  </span>
                  <div v-else class="account-info">
                    <div class="account-number">
                      {{
                        transferDetails.fromAccountNumber ||
                        t("internalXfer_na")
                      }}
                    </div>
                    <div
                      class="account-nickname"
                      v-if="transferDetails.fromGroupLabel"
                    >
                      {{ transferDetails.fromGroupLabel }}
                    </div>
                  </div>
                </span>
              </div>
              <div class="detail-field" v-if="formattedFromScale">
                <span class="detail-label">{{
                  t("internalXfer_detail_label_scale")
                }}</span>
                <span class="detail-value">{{ formattedFromScale }}</span>
              </div>
              <div class="detail-field" v-if="formattedFromPlatformAmount">
                <span class="detail-label">{{
                  t("internalXfer_detail_label_amountDeducted")
                }}</span>
                <span class="detail-value highlight">{{
                  formattedFromPlatformAmount
                }}</span>
              </div>
            </div>

            <div class="detail-section">
              <h3>
                <i class="fas fa-arrow-right-to-bracket"></i>
                {{ t("internalXfer_detail_section_target") }}
              </h3>
              <div class="detail-field">
                <span class="detail-label">{{
                  t("internalXfer_detail_label_platform")
                }}</span>
                <span class="detail-value">
                  <span
                    :class="[
                      'platform-badge',
                      `platform-${toPlatformMeta.key}`,
                    ]"
                  >
                    <i :class="toPlatformMeta.icon"></i>
                    {{ toPlatformMeta.label }}
                  </span>
                </span>
              </div>
              <div class="detail-field">
                <span class="detail-label">{{
                  t("internalXfer_detail_label_targetAccount")
                }}</span>
                <span class="detail-value">
                  <div class="account-info">
                    <div class="account-number">
                      {{
                        transferDetails.toAccountNumber || t("internalXfer_na")
                      }}
                    </div>
                    <div
                      class="account-nickname"
                      v-if="transferDetails.toGroupLabel"
                    >
                      {{ transferDetails.toGroupLabel }}
                    </div>
                  </div>
                </span>
              </div>
              <div class="detail-field" v-if="formattedToScale">
                <span class="detail-label">{{
                  t("internalXfer_detail_label_scale")
                }}</span>
                <span class="detail-value">{{ formattedToScale }}</span>
              </div>
              <div class="detail-field" v-if="formattedToPlatformAmount">
                <span class="detail-label">{{
                  t("internalXfer_detail_label_amountToCredit")
                }}</span>
                <span class="detail-value highlight">{{
                  formattedToPlatformAmount
                }}</span>
              </div>
            </div>

            <!-- Notes Section -->
            <div
              class="detail-section full-width"
              v-if="transferDetails.notes && transferDetails.notes.length > 0"
            >
              <div class="notes-header">
                <h3>
                  <i class="fas fa-sticky-note"></i>
                  {{ t("internalXfer_detail_notesTitle") }}
                </h3>
                <button class="btn-add-note" @click="handleAddNote">
                  <i class="fas fa-plus"></i>
                  {{ t("internalXfer_btn_addNote") }}
                </button>
              </div>
              <div class="notes-list">
                <div
                  class="note-item"
                  v-for="note in transferDetails.notes"
                  :key="note.id"
                >
                  <div class="note-content">{{ note.noteContent }}</div>
                  <div class="note-meta">
                    <span class="note-author">
                      <i class="fas fa-user"></i>
                      {{
                        note.createdByName ||
                        t("internalXfer_detail_unknownUser")
                      }}
                    </span>
                    <span class="note-time">
                      <i class="fas fa-clock"></i>
                      {{ formatDateTime(note.createdAt) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Transaction Timeline -->
            <div
              class="detail-section full-width"
              v-if="
                transferDetails.statusHistory &&
                transferDetails.statusHistory.length > 0
              "
            >
              <h3>
                <i class="fas fa-history"></i>
                {{ t("internalXfer_detail_timelineTitle") }}
              </h3>
              <div class="timeline">
                <div
                  class="timeline-item"
                  v-for="(history, index) in transferDetails.statusHistory"
                  :key="history.id"
                >
                  <div
                    :class="[
                      'timeline-dot',
                      getTimelineDotClass(
                        history.newStatus,
                        index === transferDetails.statusHistory.length - 1,
                      ),
                    ]"
                  ></div>
                  <div class="timeline-content">
                    <div class="timeline-title">
                      {{ getStatusLabel(history.newStatus) }}
                    </div>
                    <div class="timeline-time">
                      {{ formatDateTime(history.createdAt) }}
                    </div>
                    <div
                      class="timeline-description"
                      v-if="history.description"
                    >
                      {{ history.description }}
                    </div>
                    <div
                      class="timeline-description"
                      v-if="history.changedByName"
                    >
                      {{
                        tParams(
                          "internalXfer_detail_timelineBy",
                          "By: {name}",
                          { name: history.changedByName },
                        )
                      }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Action Buttons -->
          <div
            class="actions-panel"
            v-if="
              showActions &&
              ![
                'completed',
                'rejected',
                'cancelled',
                'canceled',
                'failed',
              ].includes(transferDetails.status) &&
              (hasApprovePermission ||
                hasRejectPermission ||
                hasAddNotePermission ||
                hasContactClientPermission)
            "
          >
            <button
              v-if="hasApprovePermission"
              class="action-btn-large approve"
              @click="handleApprove"
              :disabled="
                [
                  'completed',
                  'rejected',
                  'cancelled',
                  'canceled',
                  'failed',
                ].includes(transferDetails.status)
              "
            >
              <i class="fas fa-check-circle"></i>
              <span>{{ t("internalXfer_btn_approveComplete") }}</span>
            </button>
            <button
              v-if="hasRejectPermission && transferDetails.status === 'pending'"
              class="action-btn-large reject"
              @click="handleReject"
            >
              <i class="fas fa-times-circle"></i>
              <span>{{ t("internalXfer_btn_reject") }}</span>
            </button>
            <button
              v-if="hasAddNotePermission"
              class="action-btn-large"
              @click="handleAddNote"
            >
              <i class="fas fa-sticky-note"></i>
              <span>{{ t("internalXfer_btn_addNote") }}</span>
            </button>
            <button
              v-if="hasContactClientPermission"
              class="action-btn-large"
              @click="handleContactClient"
            >
              <i class="fas fa-envelope"></i>
              <span>{{ t("internalXfer_btn_contactClient") }}</span>
            </button>
          </div>
        </div>
      </div>
    </td>
  </tr>

  <!-- Email Editor Modal -->
  <EmailEditorModal
    v-model="showEmailModal"
    :recipient-email="recipientEmail"
    :recipient-name="recipientName"
    :processing="sendingEmail"
    @confirm="handleSendEmail"
  />
</template>

<script setup>
import { ref, watch, computed } from "vue";
import * as internalTransferApi from "../../services/internalTransferApi";
import EmailEditorModal from "../common/EmailEditorModal.vue";
import { formatCurrency } from "../../utils/helpers";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { translateApiErrorMessage } from "@/i18n/adminI18nBridge";

const { t, tParams, languageStore } = useAdminI18n();

const props = defineProps({
  transferId: {
    type: [Number, String],
    required: true,
  },
  isExpanded: {
    type: Boolean,
    default: false,
  },
  showActions: {
    type: Boolean,
    default: true,
  },
  hasApprovePermission: {
    type: Boolean,
    default: false,
  },
  hasRejectPermission: {
    type: Boolean,
    default: false,
  },
  hasAddNotePermission: {
    type: Boolean,
    default: false,
  },
  hasContactClientPermission: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["approve", "reject", "refresh"]);

const loading = ref(false);
const error = ref(null);
const transferDetails = ref(null);
const showEmailModal = ref(false);
const sendingEmail = ref(false);

const recipientEmail = computed(() => {
  return transferDetails.value?.email || "";
});

const recipientName = computed(() => {
  if (!transferDetails.value) return "";
  return `${transferDetails.value.firstName || ""} ${transferDetails.value.lastName || ""}`.trim();
});

const isWalletSource = computed(() => {
  return (
    transferDetails.value?.fromType == "wallet" ||
    transferDetails.value?.fromType == "available_balance"
  );
});

const sourceTypeLabel = computed(() => {
  return isWalletSource.value
    ? t("internalXfer_sourceType_wallet")
    : t("internalXfer_sourceType_trading");
});

const normalizePlatformKey = (platformKey) => {
  const normalized = String(platformKey || "")
    .trim()
    .toLowerCase();
  if (!normalized) return "wallet";
  if (normalized === "finance_pro") return "financepro";
  return normalized;
};

// badge 只用 shortCode，没有就空着（wallet 不是交易平台，单独显示）
const getPlatformLabel = (platformKey, shortCode) => {
  const normalized = normalizePlatformKey(platformKey);
  if (normalized === "wallet") return t("depositMgmt_wallet");
  return shortCode || "";
};

const getPlatformIcon = (platformKey) => {
  const normalized = normalizePlatformKey(platformKey);
  if (normalized === "wallet") return "fas fa-wallet";
  if (normalized === "mt4" || normalized === "mt5") return "fas fa-chart-line";
  if (normalized === "financepro") return "fas fa-landmark";
  return "fas fa-server";
};

const getTransferPlatformMeta = (details, direction) => {
  const prefix = direction === "from" ? "from" : "to";
  const platformKey = normalizePlatformKey(
    details?.[`${prefix}GroupPlatformKey`] ||
      details?.[`${prefix}PlatformKey`] ||
      details?.[`${prefix}TradingPlatformKey`] ||
      details?.groupPlatformKey ||
      details?.platformKey,
  );
  const shortCode = details?.[`${prefix}PlatformShortCode`] || "";

  return {
    key: platformKey,
    label: getPlatformLabel(platformKey, shortCode),
    icon: getPlatformIcon(platformKey),
  };
};

const formatTrimmedNumber = (value, maxDecimals = 8) => {
  if (value === undefined || value === null || value === "") return "";
  const numericValue = typeof value === "string" ? parseFloat(value) : value;
  if (Number.isNaN(numericValue)) return "";

  return numericValue.toLocaleString("en-US", {
    minimumFractionDigits: 0,
    maximumFractionDigits: maxDecimals,
  });
};

const formatScaleLabel = (scale, unit) => {
  const formattedScale = formatTrimmedNumber(scale, 8);
  const normalizedUnit = String(unit || "").trim();

  if (!formattedScale) return "";
  if (Number(scale) === 1 && !normalizedUnit) return "";

  return normalizedUnit
    ? tParams("internalXfer_scaleLine", "1 USD = {rate} {unit}", {
        rate: formattedScale,
        unit: normalizedUnit,
      })
    : tParams("internalXfer_scaleLineNoUnit", "1 USD = {rate}", {
        rate: formattedScale,
      });
};

const formatPlatformAmountLabel = (amount, unit) => {
  const formattedAmount = formatTrimmedNumber(amount, 8);
  if (!formattedAmount) return "";
  const normalizedUnit = String(unit || "").trim();
  return normalizedUnit
    ? `${formattedAmount} ${normalizedUnit}`
    : formattedAmount;
};

const formattedFromScale = computed(() => {
  return formatScaleLabel(
    transferDetails.value?.fromAmountScale ||
      transferDetails.value?.fromGroupScale,
    transferDetails.value?.fromDisplayUnit ||
      transferDetails.value?.fromGroupUnit,
  );
});

const formattedToScale = computed(() => {
  return formatScaleLabel(
    transferDetails.value?.toAmountScale || transferDetails.value?.toGroupScale,
    transferDetails.value?.toDisplayUnit || transferDetails.value?.toGroupUnit,
  );
});

const formattedFromPlatformAmount = computed(() => {
  if (!formattedFromScale.value) return "";
  return formatPlatformAmountLabel(
    transferDetails.value?.fromPlatformAmount,
    transferDetails.value?.fromDisplayUnit ||
      transferDetails.value?.fromGroupUnit,
  );
});

const formattedToPlatformAmount = computed(() => {
  if (!formattedToScale.value) return "";
  return formatPlatformAmountLabel(
    transferDetails.value?.toPlatformAmount,
    transferDetails.value?.toDisplayUnit || transferDetails.value?.toGroupUnit,
  );
});

const fromPlatformMeta = computed(() =>
  getTransferPlatformMeta(transferDetails.value, "from"),
);

const toPlatformMeta = computed(() =>
  getTransferPlatformMeta(transferDetails.value, "to"),
);

// 加载转账详情
const loadTransferDetails = async () => {
  if (!props.isExpanded) return;

  loading.value = true;
  error.value = null;

  try {
    const response = await internalTransferApi.getInternalTransfer(
      props.transferId,
    );
    if (response.success) {
      transferDetails.value = response.data;

      // 如果没有statusHistory，尝试加载
      if (!transferDetails.value.statusHistory) {
        const historyResponse =
          await internalTransferApi.getInternalTransferHistory(
            props.transferId,
          );
        if (historyResponse.success) {
          transferDetails.value.statusHistory = historyResponse.data || [];
        }
      }

      // 如果没有notes，尝试加载
      if (!transferDetails.value.notes) {
        const notesResponse =
          await internalTransferApi.getInternalTransferNotes(props.transferId);
        if (notesResponse.success) {
          transferDetails.value.notes = notesResponse.data || [];
        }
      }
    } else {
      const raw = response.message || "";
      error.value = translateApiErrorMessage(
        response.errorCode,
        raw || t("internalXfer_detail_err_loadFailed"),
      );
    }
  } catch (err) {
    console.error("Failed to load transfer details:", err);
    const data = err.response?.data;
    const rawMsg = data?.message || err.message || "";
    error.value = translateApiErrorMessage(
      data?.errorCode,
      rawMsg || t("internalXfer_detail_err_loadFailed"),
    );
  } finally {
    loading.value = false;
  }
};

// 监听展开状态
watch(
  () => props.isExpanded,
  (newVal) => {
    if (newVal && !transferDetails.value) {
      loadTransferDetails();
    }
  },
);

// 格式化日期时间
const formatDateTime = (datetime) => {
  if (!datetime) return "-";
  const date = new Date(datetime);
  const loc = languageStore.currentLanguage === "zh" ? "zh-CN" : "en-US";
  return date.toLocaleString(loc, {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
};

// 获取状态标签
const getStatusLabel = (status) => {
  if (status === "pending") return t("internalXfer_detailStatus_pending");
  if (status === "processing") return t("internalXfer_detailStatus_processing");
  const keyByStatus = {
    completed: "depositMgmt_status_completed",
    rejected: "depositMgmt_status_rejected",
    failed: "depositMgmt_status_failed",
    cancelled: "depositMgmt_status_cancelled",
    canceled: "depositMgmt_status_cancelled",
  };
  const key = keyByStatus[status];
  return key ? t(key) : status;
};

// 获取timeline圆点类名
const getTimelineDotClass = (status, isLast) => {
  if (status === "completed") return "completed";
  if (status === "rejected") return "rejected";
  if (isLast) return "active";
  return "";
};

// 处理批准
const handleApprove = () => {
  emit("approve", props.transferId);
};

// 处理拒绝
const handleReject = () => {
  emit("reject", props.transferId);
};

// 处理添加备注
const handleAddNote = async () => {
  const noteContent = prompt(t("internalXfer_prompt_addNote"));
  if (!noteContent || !noteContent.trim()) {
    return;
  }

  try {
    const response = await internalTransferApi.addInternalTransferNote(
      props.transferId,
      {
        noteContent: noteContent.trim(),
      },
    );

    if (response.success) {
      // 将新添加的备注添加到列表顶部
      if (!transferDetails.value.notes) {
        transferDetails.value.notes = [];
      }
      transferDetails.value.notes.unshift(response.data);
      alert(t("internalXfer_alert_noteOk"));
    } else {
      const raw = response.message || "";
      alert(
        tParams("internalXfer_alert_noteFailed", "Failed to add note: {msg}", {
          msg: translateApiErrorMessage(
            response.errorCode,
            raw || t("common_unknownError"),
          ),
        }),
      );
    }
  } catch (err) {
    console.error("Failed to add note:", err);
    const data = err.response?.data;
    const rawMsg = data?.message || err.message || "";
    alert(
      tParams("internalXfer_alert_noteFailed", "Failed to add note: {msg}", {
        msg: translateApiErrorMessage(data?.errorCode, rawMsg),
      }),
    );
  }
};

// 联系客户
const handleContactClient = () => {
  const email = transferDetails.value?.email;

  if (!email) {
    console.error("Contact Client: Email is missing", transferDetails.value);
    alert(t("internalXfer_alert_noClientEmail"));
    return;
  }

  showEmailModal.value = true;
};

// 发送邮件
const handleSendEmail = async (emailData) => {
  sendingEmail.value = true;
  try {
    const response = await internalTransferApi.sendEmail(
      props.transferId,
      emailData,
    );
    if (response.success) {
      alert(t("internalXfer_alert_emailOk"));
      showEmailModal.value = false;
    } else {
      const raw = response.message || "";
      alert(
        tParams(
          "internalXfer_alert_emailFailed",
          "Failed to send email: {msg}",
          {
            msg: translateApiErrorMessage(
              response.errorCode,
              raw || t("common_unknownError"),
            ),
          },
        ),
      );
    }
  } catch (err) {
    console.error("Failed to send email:", err);
    const data = err.response?.data;
    const rawMsg = data?.message || err.message || "";
    alert(
      tParams("internalXfer_alert_emailFailed", "Failed to send email: {msg}", {
        msg: translateApiErrorMessage(data?.errorCode, rawMsg),
      }),
    );
  } finally {
    sendingEmail.value = false;
  }
};

// 初始加载（如果已展开）
if (props.isExpanded) {
  loadTransferDetails();
}
</script>

<style scoped>
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

.loading-state,
.error-state {
  text-align: center;
  padding: 40px;
}

.loading-state i {
  font-size: 48px;
  color: var(--color-brand);
  margin-bottom: 15px;
  display: block;
}

.error-state i {
  font-size: 48px;
  color: var(--color-danger);
  margin-bottom: 15px;
  display: block;
}

.loading-state p,
.error-state p {
  color: var(--color-text);
  font-size: 16px;
}

.detail-sections {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  margin-bottom: 20px;
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

.detail-section.full-width {
  grid-column: 1 / -1;
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

.detail-value.highlight {
  font-weight: 700;
  color: var(--color-success);
  font-size: 16px;
}

.from-type-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: var(--radius-sm);
  font-size: 12px;
  font-weight: 600;
}

.from-type-badge.available-balance {
  background: var(--color-success-soft);
  color: #38b2ac;
}

.platform-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  line-height: 1;
}

.platform-badge i {
  font-size: 11px;
}

.platform-wallet {
  background: var(--color-surface-soft);
  color: var(--color-text);
}

.platform-mt4 {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.platform-mt5 {
  background: var(--color-brand-soft);
  color: var(--color-info);
}

.platform-financepro {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.account-info {
  text-align: right;
}

.account-number {
  font-weight: 600;
  color: var(--color-ink);
  font-size: 13px;
}

.account-nickname {
  font-size: 12px;
  color: var(--color-muted);
  margin-top: 2px;
}

.status-badge {
  display: inline-block;
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
}

.status-badge.pending {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.status-badge.processing {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.status-badge.completed {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.status-badge.failed,
.status-badge.cancelled {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.notes-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 2px solid var(--color-border);
}

.notes-header h3 {
  margin: 0;
}

.btn-add-note {
  padding: 8px 16px;
  border: 2px solid var(--color-brand);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-brand);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.btn-add-note:hover {
  background: var(--color-brand-solid);
  color: white;
  transform: translateY(-1px);
}

.notes-list {
  display: flex;
  flex-direction: column;
  gap: 15px;
}

.note-item {
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 15px;
  transition: all 0.2s ease;
}

.note-item:hover {
  border-color: var(--color-border-strong);
  background: var(--color-surface-muted);
}

.note-content {
  color: var(--color-ink);
  font-size: 14px;
  line-height: 1.6;
  margin-bottom: 10px;
}

.note-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12px;
  color: var(--color-muted);
  padding-top: 8px;
  border-top: 1px solid var(--color-border);
}

.note-author,
.note-time {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.note-author i,
.note-time i {
  font-size: 11px;
}

.timeline {
  position: relative;
  padding-left: 30px;
}

.timeline::before {
  content: "";
  position: absolute;
  left: 8px;
  top: 8px;
  bottom: 8px;
  width: 2px;
  background: var(--color-border);
}

.timeline-item {
  position: relative;
  padding-bottom: 25px;
}

.timeline-item:last-child {
  padding-bottom: 0;
}

.timeline-dot {
  position: absolute;
  left: -26px;
  top: 4px;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  border: 3px solid var(--color-border);
  background: var(--color-surface);
}

.timeline-dot.completed {
  background: var(--color-success-solid);
  border-color: var(--color-success);
}

.timeline-dot.rejected {
  background: var(--color-danger-solid);
  border-color: var(--color-danger);
}

.timeline-dot.active {
  background: var(--color-brand-solid);
  border-color: var(--color-brand);
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0%,
  100% {
    box-shadow: 0 0 0 0 rgba(var(--color-brand-rgb), 0.7);
  }
  50% {
    box-shadow: 0 0 0 8px rgba(var(--color-brand-rgb), 0);
  }
}

.timeline-content {
  padding-left: 10px;
}

.timeline-title {
  font-weight: 600;
  color: var(--color-ink);
  font-size: 14px;
  margin-bottom: 4px;
}

.timeline-time {
  font-size: 12px;
  color: var(--color-faint);
}

.timeline-description {
  font-size: 13px;
  color: var(--color-muted);
  margin-top: 5px;
}

.actions-panel {
  display: flex;
  gap: 10px;
  padding: 20px;
  background: var(--color-surface);
  border-radius: var(--radius-md);
  border: 2px solid var(--color-border);
  flex-wrap: wrap;
}

.action-btn-large {
  flex: 1;
  min-width: 150px;
  padding: 14px 20px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.action-btn-large i {
  font-size: 24px;
  color: var(--color-brand);
}

.action-btn-large:hover:not(:disabled) {
  border-color: var(--color-brand);
  background: var(--color-brand-soft);
  transform: translateY(-2px);
}

.action-btn-large.approve {
  border-color: var(--color-success);
}

.action-btn-large.approve i {
  color: var(--color-success);
}

.action-btn-large.approve:hover:not(:disabled) {
  border-color: var(--color-success);
  background: var(--color-success-soft);
}

.action-btn-large.reject {
  border-color: var(--color-danger);
}

.action-btn-large.reject i {
  color: var(--color-danger);
}

.action-btn-large.reject:hover:not(:disabled) {
  border-color: var(--color-danger);
  background: var(--color-danger-soft);
}

.action-btn-large:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

@media (max-width: 768px) {
  .detail-sections {
    grid-template-columns: 1fr;
  }

  .actions-panel {
    flex-direction: column;
  }

  .action-btn-large {
    min-width: 100%;
  }
}
</style>
