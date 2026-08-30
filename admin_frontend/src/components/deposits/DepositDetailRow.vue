<template>
  <tr :class="['detail-row', { show: isExpanded }]">
    <td colspan="10">
      <div class="detail-content">
        <div v-if="loading" class="loading-state">
          <i class="fas fa-spinner fa-spin"></i>
          <p>{{ t("depositDetail_loading") }}</p>
        </div>

        <div v-else-if="error" class="error-state">
          <i class="fas fa-exclamation-circle"></i>
          <p>{{ error }}</p>
        </div>

        <div v-else-if="depositDetails">
          <div class="detail-sections">
            <!-- Transaction Details -->
            <div class="detail-section">
              <h3>
                <i class="fas fa-receipt"></i>
                {{ t("depositDetail_section_transaction") }}
              </h3>
              <div class="detail-field">
                <span class="detail-label">{{
                  t("depositDetail_label_transactionId")
                }}</span>
                <span class="detail-value">{{
                  depositDetails.transactionId
                }}</span>
              </div>
              <div class="detail-field">
                <span class="detail-label">{{
                  t("depositDetail_label_amountUsd")
                }}</span>
                <span class="detail-value highlight">{{
                  formatCurrency(depositDetails.amount)
                }}</span>
              </div>
              <div class="detail-field" v-if="depositDetails.amountCrypto">
                <span class="detail-label">{{
                  t("depositDetail_label_amountCrypto")
                }}</span>
                <span class="detail-value"
                  >{{ depositDetails.amountCrypto }}
                  {{ depositDetails.shortCode }}</span
                >
              </div>
              <div class="detail-field">
                <span class="detail-label">{{
                  t("depositDetail_label_platformFee")
                }}</span>
                <span class="detail-value">{{
                  formatCurrency(depositDetails.platformFee)
                }}</span>
              </div>
              <div class="detail-field" v-if="targetAccountLabel">
                <span class="detail-label">{{
                  t("depositDetail_label_scale")
                }}</span>
                <span class="detail-value">{{ targetAccountLabel }}</span>
              </div>
              <div class="detail-field" v-if="formattedPlatformAmount">
                <span class="detail-label">{{
                  t("depositDetail_label_amountAfterTransfer")
                }}</span>
                <span class="detail-value highlight">{{
                  formattedPlatformAmount
                }}</span>
              </div>
              <div class="detail-field" v-if="showCanceledReason">
                <span class="detail-label">{{
                  t("depositDetail_label_cancelReason")
                }}</span>
                <span class="detail-value">{{
                  depositDetails.failureReason
                }}</span>
              </div>
            </div>

            <!-- Client Information -->
            <div class="detail-section">
              <h3>
                <i class="fas fa-user-circle"></i>
                {{ t("depositDetail_section_client") }}
              </h3>
              <div class="detail-field">
                <span class="detail-label">{{
                  t("depositDetail_label_fullName")
                }}</span>
                <span class="detail-value"
                  >{{ depositDetails.firstName }}
                  {{ depositDetails.lastName }}</span
                >
              </div>
              <div class="detail-field">
                <span class="detail-label">{{
                  t("depositDetail_label_email")
                }}</span>
                <span class="detail-value">{{ depositDetails.email }}</span>
              </div>
              <div class="detail-field" v-if="depositDetails.phone">
                <span class="detail-label">{{
                  t("depositDetail_label_phone")
                }}</span>
                <span class="detail-value">{{ depositDetails.phone }}</span>
              </div>
              <div class="detail-field" v-if="depositDetails.country">
                <span class="detail-label">{{
                  t("depositDetail_label_country")
                }}</span>
                <span class="detail-value">{{ depositDetails.country }}</span>
              </div>
              <div class="detail-field" v-if="depositDetails.accountNumber">
                <span class="detail-label">{{
                  t("depositDetail_label_tradingAccount")
                }}</span>
                <span class="detail-value">{{
                  depositDetails.accountNumber
                }}</span>
              </div>
              <div class="detail-field" v-if="tradingAccountGroupLabel">
                <span class="detail-label">{{
                  t("depositDetail_label_tradingGroup")
                }}</span>
                <span class="detail-value">{{ tradingAccountGroupLabel }}</span>
              </div>
            </div>

            <!-- Payment Details -->
            <div class="detail-section full-width">
              <h3>
                <i class="fas fa-wallet"></i>
                {{ t("depositDetail_section_payment") }}
              </h3>
              <div class="detail-field" v-if="depositDetails.gatewayName">
                <span class="detail-label">{{
                  t("depositDetail_label_paymentMethod")
                }}</span>
                <span class="detail-value payment-method-emphasis">
                  <i
                    :class="depositDetails.gatewayIconClass || 'fas fa-wallet'"
                    style="margin-right: 6px"
                  ></i>
                  {{ depositDetails.gatewayName }}
                </span>
              </div>
              <div class="detail-field" v-else>
                <span class="detail-label">{{
                  t("depositDetail_label_paymentMethod")
                }}</span>
                <span class="detail-value payment-method-emphasis">-</span>
              </div>
              <div class="detail-field" v-if="depositDetails.networkName">
                <span class="detail-label">{{
                  t("depositDetail_label_network")
                }}</span>
                <span class="detail-value">{{
                  depositDetails.networkName
                }}</span>
              </div>
              <div
                class="detail-field"
                v-if="
                  depositDetails.exchangeRate !== undefined &&
                  depositDetails.exchangeRate !== null &&
                  depositDetails.exchangeRate !== ''
                "
              >
                <span class="detail-label">{{
                  t("depositDetail_label_exchangeRate")
                }}</span>
                <span class="detail-value">{{
                  tParams(
                    "depositDetail_exchangeRateLine",
                    "1 USD = {rate} {code}",
                    {
                      rate: formatNumber(depositDetails.exchangeRate, 8),
                      code: depositDetails.currencyCode || "",
                    },
                  ).replace(/\s+$/u, "")
                }}</span>
              </div>
              <div
                class="detail-field"
                v-if="
                  depositDetails.platformFee !== undefined &&
                  depositDetails.platformFee !== null &&
                  depositDetails.platformFee !== ''
                "
              >
                <span class="detail-label">{{
                  t("depositDetail_label_settlementFee")
                }}</span>
                <span class="detail-value fee-deduction">{{
                  formatAmountWithCode(
                    depositDetails.platformFee,
                    depositDetails.currencyCode,
                  )
                }}</span>
              </div>
              <div
                class="detail-field"
                v-if="
                  depositDetails.quotedAmount !== undefined &&
                  depositDetails.quotedAmount !== null &&
                  depositDetails.quotedAmount !== ''
                "
              >
                <span class="detail-label">{{
                  t("depositDetail_label_quotedAmount")
                }}</span>
                <span class="detail-value highlight emphasis">{{
                  formatAmountWithCode(
                    depositDetails.quotedAmount,
                    depositDetails.currencyCode,
                  )
                }}</span>
              </div>
            </div>

            <div
              class="detail-section full-width"
              v-if="
                depositDetails.supportQuestions &&
                depositDetails.supportQuestions.length > 0
              "
            >
              <h3>
                <i class="fas fa-circle-question"></i>
                {{ t("depositDetail_section_support") }}
              </h3>
              <div
                v-for="question in depositDetails.supportQuestions"
                :key="question.id"
                class="detail-field"
              >
                <span class="detail-label">{{
                  capitalizeLabel(question.name)
                }}</span>
                <span class="detail-value">{{ question.answer || "-" }}</span>
              </div>
            </div>

            <!-- Notes Section -->
            <div
              class="detail-section full-width"
              v-if="depositDetails.notes && depositDetails.notes.length > 0"
            >
              <div class="notes-header">
                <h3>
                  <i class="fas fa-sticky-note"></i>
                  {{ t("depositDetail_section_notes") }}
                </h3>
                <button
                  v-if="hasAddNotePermission"
                  class="btn-add-note"
                  @click="handleAddNote"
                >
                  <i class="fas fa-plus"></i>
                  {{ t("depositDetail_btnAddNote") }}
                </button>
              </div>
              <div class="notes-list">
                <div
                  class="note-item"
                  v-for="note in depositDetails.notes"
                  :key="note.id"
                >
                  <div class="note-content">{{ note.noteContent }}</div>
                  <div class="note-meta">
                    <span class="note-author">
                      <i class="fas fa-user"></i>
                      {{
                        note.createdByName || t("depositDetail_unknownAuthor")
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
                depositDetails.statusHistory &&
                depositDetails.statusHistory.length > 0
              "
            >
              <h3>
                <i class="fas fa-history"></i>
                {{ t("depositDetail_section_timeline") }}
              </h3>
              <div class="timeline">
                <div
                  class="timeline-item"
                  v-for="(history, index) in depositDetails.statusHistory"
                  :key="history.id"
                >
                  <div
                    :class="[
                      'timeline-dot',
                      getTimelineDotClass(
                        history.newStatus,
                        index === depositDetails.statusHistory.length - 1,
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
                        tParams("depositDetail_timelineBy", "By: {name}", {
                          name: history.changedByName,
                        })
                      }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- PSP Callback 历史（用 transactionId 当 orderId 查 paymentProcessorCallbackLogs；没有就自动隐藏） -->
          <PspCallbackSection
            v-if="depositDetails.transactionId"
            :order-id="depositDetails.transactionId"
            transaction-type="deposit"
            :record-id="depositDetails.id"
          />

          <!-- Action Buttons（completed / unpaid / payment_failed 不显示审批行，与 Completed 一致） -->
          <div
            class="actions-panel"
            v-if="
              showActions &&
              ![
                'completed',
                'unpaid',
                'payment_failed',
                'rejected',
                'expired',
                'cancaled',
                'cancelled',
                'canceled',
              ].includes(depositDetails.status) &&
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
                approving ||
                [
                  'completed',
                  'unpaid',
                  'payment_failed',
                  'rejected',
                  'expired',
                  'cancaled',
                  'cancelled',
                  'canceled',
                ].includes(depositDetails.status)
              "
            >
              <i
                :class="
                  approving ? 'fas fa-spinner fa-spin' : 'fas fa-check-circle'
                "
              ></i>
              <span>{{ t("depositDetail_btnApproveComplete") }}</span>
            </button>
            <button
              v-if="hasRejectPermission && depositDetails.status === 'pending'"
              class="action-btn-large reject"
              @click="handleReject"
            >
              <i class="fas fa-times-circle"></i>
              <span>{{ t("depositDetail_btnReject") }}</span>
            </button>
            <button
              v-if="hasAddNotePermission"
              class="action-btn-large"
              @click="handleAddNote"
            >
              <i class="fas fa-sticky-note"></i>
              <span>{{ t("depositDetail_btnAddNote") }}</span>
            </button>
            <button
              class="action-btn-large"
              @click="handleViewTransaction"
              v-if="depositDetails.transactionHash"
            >
              <i class="fas fa-external-link-alt"></i>
              <span>{{ t("depositDetail_btnViewBlockchain") }}</span>
            </button>
            <button
              v-if="hasContactClientPermission"
              class="action-btn-large"
              @click="handleContactClient"
            >
              <i class="fas fa-envelope"></i>
              <span>{{ t("depositDetail_btnContactClient") }}</span>
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
import depositApi from "../../services/depositApi";
import EmailEditorModal from "../common/EmailEditorModal.vue";
import PspCallbackSection from "../common/PspCallbackSection.vue";
import { formatCurrency, formatNumber } from "../../utils/helpers";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { translateApiErrorMessage } from "@/i18n/adminI18nBridge";

const { t, tParams, languageStore } = useAdminI18n();

const props = defineProps({
  depositId: {
    type: [Number, String],
    required: true,
  },
  deposit: {
    type: Object,
    default: null,
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
  // 审批进行中：父组件发起 approve 请求期间置 true，用于禁用按钮防止重复提交
  approving: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["approve", "reject", "refresh"]);

const loading = ref(false);
const error = ref(null);
const depositDetails = ref(null);
const copied = ref(false);
const showEmailModal = ref(false);
const sendingEmail = ref(false);

const recipientEmail = computed(() => {
  return depositDetails.value?.email || "";
});

const recipientName = computed(() => {
  if (!depositDetails.value) return "";
  return `${depositDetails.value.firstName || ""} ${depositDetails.value.lastName || ""}`.trim();
});

const displayDeposit = computed(() => {
  return {
    ...(props.deposit || {}),
    ...(depositDetails.value || {}),
  };
});

const getFirstDefined = (...values) => {
  for (const value of values) {
    if (value !== undefined && value !== null && String(value).trim() !== "") {
      return value;
    }
  }
  return null;
};

const formatTrimmedNumber = (value, maxDecimals = 8) => {
  if (value === undefined || value === null || value === "") {
    return "";
  }

  const numericValue = typeof value === "string" ? parseFloat(value) : value;
  if (Number.isNaN(numericValue)) {
    return "";
  }

  return numericValue.toLocaleString("en-US", {
    minimumFractionDigits: 0,
    maximumFractionDigits: maxDecimals,
  });
};

const showCanceledReason = computed(() => {
  const status = String(displayDeposit.value?.status || "").toLowerCase();
  return (
    ["cancaled", "cancelled", "canceled"].includes(status) &&
    Boolean(displayDeposit.value?.failureReason)
  );
});

const targetAccountLabel = computed(() => {
  const details = displayDeposit.value;
  if (
    !details?.tradingAccountId ||
    details?.amountScale === undefined ||
    details?.amountScale === null ||
    details?.amountScale === ""
  ) {
    return "";
  }

  const formattedScale = formatTrimmedNumber(details.amountScale, 8);
  const unit = String(platformUnit.value || "").trim();

  if (!formattedScale) {
    return "";
  }

  if (Number(details.amountScale) === 1 && !unit) {
    return "";
  }

  return tParams("depositDetail_exchangeRateLine", "1 USD = {rate} {code}", {
    rate: formattedScale,
    code: unit,
  }).replace(/\s+$/u, "");
});

const platformUnit = computed(() => {
  const details = displayDeposit.value;
  return getFirstDefined(
    details?.unit,
    details?.accountUnit,
    details?.groupUnit,
    details?.tradingGroupUnit,
    details?.platformUnit,
    details?.platformAccountUnit,
    details?.uni,
  );
});

const tradingAccountGroupLabel = computed(() => {
  const details = displayDeposit.value;
  return String(
    getFirstDefined(
      details?.groupLabel,
      details?.tradingGroupLabel,
      details?.label,
      details?.groupName,
      details?.tradingGroupName,
    ) || "",
  ).trim();
});

const formattedPlatformAmount = computed(() => {
  const platformAmount = displayDeposit.value?.platformAmount;
  if (
    platformAmount === undefined ||
    platformAmount === null ||
    platformAmount === ""
  ) {
    return "";
  }

  const formattedAmount = formatTrimmedNumber(platformAmount, 8);
  return platformUnit.value
    ? `${formattedAmount} ${platformUnit.value}`
    : formattedAmount;
});

// 加载存款详情
const loadDepositDetails = async () => {
  if (!props.isExpanded) return;

  loading.value = true;
  error.value = null;

  try {
    const response = await depositApi.getDeposit(props.depositId);
    if (response.success) {
      depositDetails.value = response.data;

      // 如果没有statusHistory，尝试加载
      if (!depositDetails.value.statusHistory) {
        const historyResponse = await depositApi.getDepositHistory(
          props.depositId,
        );
        if (historyResponse.success) {
          depositDetails.value.statusHistory = historyResponse.data || [];
        }
      }

      // 如果没有notes，尝试加载
      if (!depositDetails.value.notes) {
        const notesResponse = await depositApi.getDepositNotes(props.depositId);
        if (notesResponse.success) {
          depositDetails.value.notes = notesResponse.data || [];
        }
      }

      if (!Array.isArray(depositDetails.value.notes)) {
        depositDetails.value.notes = [];
      }

      // 将管理员备注添加到notes列表顶部
      const adminNotes = (depositDetails.value.adminNotes || "").trim();
      if (adminNotes) {
        const accountLabel =
          depositDetails.value.accountNickname ||
          depositDetails.value.accountNumber;
        const adminNoteContent = accountLabel
          ? `${tParams("depositDetail_adminNotePrefix", "[Account: {label}]", { label: accountLabel })} ${adminNotes}`
          : adminNotes;

        const alreadyAdded = depositDetails.value.notes.some(
          (note) => note.isAdminNote && note.noteContent === adminNoteContent,
        );

        if (!alreadyAdded) {
          depositDetails.value.notes.push({
            id: `admin-note-${new Date().getTime()}`,
            noteContent: adminNoteContent,
            createdByName:
              depositDetails.value.approvedByName ||
              t("depositDetail_noteAuthorAdmin"),
            createdAt: depositDetails.value.approvedAt,
            isAdminNote: true,
          });
        }
      }
    } else {
      const raw = response.message || t("depositDetail_err_loadDetails");
      error.value = translateApiErrorMessage(response.errorCode, raw);
    }
  } catch (err) {
    console.error("Failed to load deposit details:", err);
    const data = err?.response?.data ?? err;
    const rawMsg =
      data?.message || err?.message || t("depositDetail_err_loadDetails");
    error.value = translateApiErrorMessage(data?.errorCode, rawMsg);
  } finally {
    loading.value = false;
  }
};

// 监听展开状态
watch(
  () => props.isExpanded,
  (newVal) => {
    if (newVal && !depositDetails.value) {
      loadDepositDetails();
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
  const s = String(status || "").toLowerCase();
  const map = {
    pending: "depositDetail_status_depositInitiated",
    processing: "depositDetail_status_processingTx",
    completed: "depositMgmt_status_completed",
    failed: "depositMgmt_status_failed",
    rejected: "depositMgmt_status_rejected",
    expired: "depositMgmt_status_expired",
    cancaled: "depositMgmt_status_cancelled",
    cancelled: "depositMgmt_status_cancelled",
    canceled: "depositMgmt_status_cancelled",
    unpaid: "depositMgmt_status_unpaid",
    payment_failed: "depositMgmt_status_payment_failed",
  };
  const key = map[s];
  return key ? t(key) : status;
};

// 获取timeline圆点类名
const getTimelineDotClass = (status, isLast) => {
  const s = String(status || "").toLowerCase();
  if (s === "completed") return "completed";
  if (s === "rejected") return "rejected";
  if (s === "expired") return "expired";
  if (["cancaled", "cancelled", "canceled"].includes(s)) return "cancelled";
  if (isLast) return "active";
  return "";
};

const capitalizeLabel = (value) => {
  const normalized = String(value || "").trim();
  if (!normalized) return "-";
  return normalized.charAt(0).toUpperCase() + normalized.slice(1);
};

const formatAmountWithCode = (amount, currencyCode) => {
  const formattedAmount = formatNumber(amount, 2);
  return currencyCode ? `${formattedAmount} ${currencyCode}` : formattedAmount;
};

// 复制地址
const copyAddress = async (text) => {
  try {
    await navigator.clipboard.writeText(text);
    copied.value = true;
    setTimeout(() => {
      copied.value = false;
    }, 2000);
  } catch (err) {
    console.error("Failed to copy:", err);
    alert(t("depositDetail_alert_copyFailed"));
  }
};

// 处理批准
const handleApprove = () => {
  if (props.approving) return; // 审批进行中，避免重复提交
  emit("approve", props.depositId);
};

const handleReject = () => {
  emit("reject", props.depositId);
};

// 处理添加备注
const handleAddNote = async () => {
  const noteContent = prompt(t("depositDetail_prompt_addNote"));
  if (!noteContent || !noteContent.trim()) {
    return;
  }

  try {
    const response = await depositApi.addDepositNote(props.depositId, {
      noteContent: noteContent.trim(),
    });

    if (response.success) {
      // 将新添加的备注添加到列表顶部
      if (!depositDetails.value.notes) {
        depositDetails.value.notes = [];
      }
      depositDetails.value.notes.unshift(response.data);
      alert(t("depositDetail_alert_noteOk"));
    } else {
      const raw = response.message || t("common_unknownError");
      alert(
        tParams("depositDetail_alert_noteFailed", "Failed to add note: {msg}", {
          msg: translateApiErrorMessage(response.errorCode, raw),
        }),
      );
    }
  } catch (err) {
    console.error("Failed to add note:", err);
    const data = err?.response?.data ?? err;
    const rawMsg = data?.message || err?.message || t("common_unknownError");
    const msg = translateApiErrorMessage(data?.errorCode, rawMsg);
    alert(
      tParams("depositDetail_alert_noteFailed", "Failed to add note: {msg}", {
        msg,
      }),
    );
  }
};

// 查看区块链交易
const handleViewTransaction = () => {
  const hash = depositDetails.value.transactionHash;
  if (hash) {
    // 根据不同的加密货币打开对应的区块链浏览器
    const method =
      depositDetails.value.methodKey ||
      depositDetails.value.shortCode?.toLowerCase();
    let url = "";

    if (method === "bitcoin" || method === "btc") {
      url = `https://blockchain.com/btc/tx/${hash}`;
    } else if (method === "ethereum" || method === "eth") {
      url = `https://etherscan.io/tx/${hash}`;
    } else if (method === "usdt" || method === "usdc") {
      url = `https://etherscan.io/tx/${hash}`;
    } else {
      url = `https://blockchain.com/explorer/transactions/${hash}`;
    }

    window.open(url, "_blank");
  }
};

// 联系客户
const handleContactClient = () => {
  const email = depositDetails.value?.email;

  if (!email) {
    console.error("Contact Client: Email is missing", depositDetails.value);
    alert(t("depositDetail_alert_noClientEmail"));
    return;
  }

  showEmailModal.value = true;
};

// 发送邮件
const handleSendEmail = async (emailData) => {
  sendingEmail.value = true;
  try {
    const response = await depositApi.sendEmail(props.depositId, emailData);
    if (response.success) {
      alert(t("depositDetail_alert_emailOk"));
      showEmailModal.value = false;
    } else {
      const raw = response.message || t("common_unknownError");
      alert(
        tParams(
          "depositDetail_alert_emailFailed",
          "Failed to send email: {msg}",
          {
            msg: translateApiErrorMessage(response.errorCode, raw),
          },
        ),
      );
    }
  } catch (err) {
    console.error("Failed to send email:", err);
    const data = err?.response?.data ?? err;
    const rawMsg = data?.message || err?.message || t("common_unknownError");
    const msg = translateApiErrorMessage(data?.errorCode, rawMsg);
    alert(
      tParams(
        "depositDetail_alert_emailFailed",
        "Failed to send email: {msg}",
        { msg },
      ),
    );
  } finally {
    sendingEmail.value = false;
  }
};

// 初始加载（如果已展开）
if (props.isExpanded) {
  loadDepositDetails();
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
  font-size: 14px;
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

.detail-value.emphasis {
  font-weight: 800;
}

.detail-value.payment-method-emphasis {
  font-weight: 700;
  font-size: 15px;
}

.detail-value.fee-deduction {
  color: var(--color-warning);
  font-weight: 600;
}

.crypto-address {
  font-family: "Courier New", monospace;
  font-size: 14px;
  color: var(--color-brand);
  background: var(--color-brand-soft);
  padding: 8px 12px;
  border-radius: var(--radius-sm);
  display: inline-flex;
  align-items: center;
  gap: 8px;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.copy-address-btn {
  background: none;
  border: none;
  color: var(--color-brand);
  cursor: pointer;
  font-size: 14px;
  padding: 4px;
  transition: all 0.2s ease;
}

.copy-address-btn:hover {
  color: var(--color-brand-strong);
  transform: scale(1.1);
}

/* Timeline */
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

.timeline-dot.expired {
  background: var(--color-danger-solid);
  border-color: var(--color-danger);
}

.timeline-dot.cancelled {
  background: var(--color-muted);
  border-color: var(--color-muted);
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
  margin-bottom: 3px;
}

.timeline-time {
  font-size: 14px;
  color: var(--color-faint);
}

.timeline-description {
  font-size: 14px;
  color: var(--color-muted);
  margin-top: 5px;
}

/* Actions Panel */
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

/* Notes Section */
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
  border-bottom: none;
  padding: 0;
}

.btn-add-note {
  padding: 8px 16px;
  border: 2px solid var(--color-brand);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-brand);
  font-size: 14px;
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
  gap: 12px;
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
  white-space: pre-wrap;
  word-wrap: break-word;
}

.note-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 14px;
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
  font-size: 14px;
}

.notes-empty {
  text-align: center;
  padding: 40px 20px;
  color: var(--color-faint);
}

.notes-empty i {
  font-size: 48px;
  margin-bottom: 15px;
  display: block;
  opacity: 0.5;
}

.notes-empty p {
  font-size: 14px;
  margin: 0;
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
