<template>
  <tr :class="['detail-row', { show: isExpanded }]">
    <td colspan="10">
      <div class="detail-content">
        <div v-if="loading" class="loading-state">
          <i class="fas fa-spinner fa-spin"></i>
          <p>{{ t("withdrawalDetail_loading") }}</p>
        </div>

        <div v-else-if="error" class="error-state">
          <i class="fas fa-exclamation-circle"></i>
          <p>{{ error }}</p>
        </div>

        <div v-else-if="withdrawalDetails">
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
                  withdrawalDetails.transactionId
                }}</span>
              </div>
              <div class="detail-field">
                <span class="detail-label">{{
                  t("depositDetail_label_amountUsd")
                }}</span>
                <span class="detail-value highlight">{{
                  formatCurrency(withdrawalDetails.amount)
                }}</span>
              </div>
              <div class="detail-field" v-if="withdrawalDetails.amountCrypto">
                <span class="detail-label">{{
                  t("depositDetail_label_amountCrypto")
                }}</span>
                <span class="detail-value"
                  >{{ withdrawalDetails.amountCrypto }}
                  {{ withdrawalDetails.shortCode }}</span
                >
              </div>
              <div class="detail-field">
                <span class="detail-label">{{
                  t("depositDetail_label_platformFee")
                }}</span>
                <span class="detail-value">{{
                  formatCurrency(withdrawalDetails.platformFee)
                }}</span>
              </div>
              <div class="detail-field" v-if="withdrawalScaleLabel">
                <span class="detail-label">{{
                  t("depositDetail_label_scale")
                }}</span>
                <span class="detail-value">{{ withdrawalScaleLabel }}</span>
              </div>
              <div
                class="detail-field"
                v-if="withdrawalScaleLabel && deductedAmountLabel"
              >
                <span class="detail-label">{{
                  t("withdrawalDetail_label_amountDeducted")
                }}</span>
                <span class="detail-value highlight">{{
                  deductedAmountLabel
                }}</span>
              </div>
              <div class="detail-field" v-if="showCanceledReason">
                <span class="detail-label">{{
                  t("depositDetail_label_cancelReason")
                }}</span>
                <span class="detail-value">{{ canceledReasonText }}</span>
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
                  >{{ withdrawalDetails.firstName }}
                  {{ withdrawalDetails.lastName }}</span
                >
              </div>
              <div class="detail-field">
                <span class="detail-label">{{
                  t("depositDetail_label_email")
                }}</span>
                <span class="detail-value">{{ withdrawalDetails.email }}</span>
              </div>
              <div class="detail-field" v-if="withdrawalDetails.phone">
                <span class="detail-label">{{
                  t("depositDetail_label_phone")
                }}</span>
                <span class="detail-value">{{ withdrawalDetails.phone }}</span>
              </div>
              <div class="detail-field" v-if="tradingAccountNumber">
                <span class="detail-label">{{
                  t("depositDetail_label_tradingAccount")
                }}</span>
                <span class="detail-value">{{ tradingAccountNumber }}</span>
              </div>
              <div class="detail-field" v-if="tradingAccountGroupLabel">
                <span class="detail-label">{{
                  t("depositDetail_label_tradingGroup")
                }}</span>
                <span class="detail-value">{{ tradingAccountGroupLabel }}</span>
              </div>
              <div class="detail-field" v-if="withdrawalDetails.accountBalance">
                <span class="detail-label">{{
                  t("withdrawalDetail_label_accountBalance")
                }}</span>
                <span class="detail-value">{{
                  formatCurrency(withdrawalDetails.accountBalance)
                }}</span>
              </div>
              <div
                class="detail-field"
                v-if="
                  withdrawalDetails.previousWithdrawalsCount30Days !== undefined
                "
              >
                <span class="detail-label">{{
                  t("withdrawalDetail_label_prevWithdrawals30d")
                }}</span>
                <span class="detail-value">
                  {{
                    tParams(
                      "withdrawalDetail_prevWithdrawalsLine",
                      "{n} withdrawals — {amount}",
                      {
                        n: formatNumber(
                          withdrawalDetails.previousWithdrawalsCount30Days,
                        ),
                        amount: formatCurrency(
                          withdrawalDetails.previousWithdrawalsAmount30Days,
                        ),
                      },
                    )
                  }}
                </span>
              </div>
            </div>

            <!-- Withdrawal Details -->
            <div class="detail-section full-width">
              <h3>
                <i class="fas fa-wallet"></i>
                {{ t("withdrawalDetail_section_withdrawal") }}
              </h3>
              <div class="detail-field" v-if="withdrawalDetails.gatewayName">
                <span class="detail-label">{{
                  t("depositDetail_label_paymentMethod")
                }}</span>
                <span class="detail-value payment-method-emphasis">
                  <i
                    :class="
                      withdrawalDetails.gatewayIconClass || 'fas fa-university'
                    "
                    style="margin-right: 6px"
                  ></i>
                  {{ withdrawalDetails.gatewayName }}
                </span>
              </div>
              <div class="detail-field" v-else>
                <span class="detail-label">{{
                  t("depositDetail_label_paymentMethod")
                }}</span>
                <span class="detail-value payment-method-emphasis">{{
                  t("withdrawalDetail_bankTransferManual")
                }}</span>
              </div>
              <div class="detail-field" v-if="withdrawalDetails.networkName">
                <span class="detail-label">{{
                  t("depositDetail_label_network")
                }}</span>
                <span class="detail-value">{{
                  withdrawalDetails.networkName
                }}</span>
              </div>
              <div
                class="detail-field"
                v-if="
                  withdrawalDetails.exchangeRate !== undefined &&
                  withdrawalDetails.exchangeRate !== null &&
                  withdrawalDetails.exchangeRate !== ''
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
                      rate: formatNumber(withdrawalDetails.exchangeRate, 8),
                      code: withdrawalDetails.currencyCode || "",
                    },
                  ).replace(/\s+$/u, "")
                }}</span>
              </div>
              <div
                class="detail-field"
                v-if="
                  withdrawalDetails.platformFee !== undefined &&
                  withdrawalDetails.platformFee !== null &&
                  withdrawalDetails.platformFee !== ''
                "
              >
                <span class="detail-label">{{
                  t("depositDetail_label_settlementFee")
                }}</span>
                <span class="detail-value fee-deduction"
                  >-
                  {{
                    formatAmountWithCode(
                      withdrawalDetails.platformFee,
                      withdrawalDetails.currencyCode,
                    )
                  }}</span
                >
              </div>
              <div
                class="detail-field"
                v-if="withdrawalDetails.hasPreTemplate !== undefined"
              >
                <span class="detail-label">{{
                  t("withdrawalDetail_label_addressVerification")
                }}</span>
                <span class="detail-value">{{
                  withdrawalDetails.hasPreTemplate
                    ? t("withdrawalDetail_enable")
                    : t("withdrawalDetail_disable")
                }}</span>
              </div>
              <div
                class="detail-field"
                v-if="
                  withdrawalDetails.quotedAmount !== undefined &&
                  withdrawalDetails.quotedAmount !== null &&
                  withdrawalDetails.quotedAmount !== ''
                "
              >
                <span class="detail-label">{{
                  t("depositDetail_label_quotedAmount")
                }}</span>
                <span class="detail-value highlight emphasis">{{
                  formatAmountWithCode(
                    withdrawalDetails.quotedAmount,
                    withdrawalDetails.currencyCode,
                  )
                }}</span>
              </div>

              <!-- Payment Account Information (for bank transfer) -->
              <div
                v-if="
                  withdrawalDetails.paymentAccountLegalName ||
                  withdrawalDetails.paymentAccountBSB
                "
                class="payment-account-info"
              >
                <h4
                  style="
                    margin: 20px 0 15px 0;
                    color: var(--color-ink);
                    font-size: 14px;
                    font-weight: 600;
                  "
                >
                  <i
                    class="fas fa-university"
                    style="color: var(--color-brand); margin-right: 8px"
                  ></i>
                  {{ t("withdrawalDetail_section_paymentAccount") }}
                </h4>
                <div class="detail-field">
                  <span class="detail-label">{{
                    t("withdrawalDetail_label_legalName")
                  }}</span>
                  <span class="detail-value">{{
                    withdrawalDetails.paymentAccountLegalName || t("leads_na")
                  }}</span>
                </div>
                <div
                  class="detail-field"
                  v-if="withdrawalDetails.paymentAccountBSB"
                >
                  <span class="detail-label">{{
                    t("withdrawalDetail_label_bsb")
                  }}</span>
                  <span class="detail-value">{{
                    formatBSB(withdrawalDetails.paymentAccountBSB)
                  }}</span>
                </div>
                <div
                  class="detail-field"
                  v-if="withdrawalDetails.paymentAccountNumber"
                >
                  <span class="detail-label">{{
                    t("withdrawalDetail_label_bankAccountNumber")
                  }}</span>
                  <span class="detail-value">{{
                    withdrawalDetails.paymentAccountNumber
                  }}</span>
                </div>
              </div>

              <div
                class="detail-field"
                v-if="withdrawalDetails.destinationAddress"
              >
                <span class="detail-label">{{
                  t("withdrawalDetail_label_destinationAddress")
                }}</span>
                <div class="crypto-address">
                  {{ withdrawalDetails.destinationAddress }}
                  <button
                    class="copy-address-btn"
                    @click="copyAddress(withdrawalDetails.destinationAddress)"
                  >
                    <i :class="copied ? 'fas fa-check' : 'fas fa-copy'"></i>
                  </button>
                </div>
              </div>
              <div
                class="detail-field"
                v-if="withdrawalDetails.withdrawalReason"
              >
                <span class="detail-label">{{
                  t("withdrawalDetail_label_withdrawalReason")
                }}</span>
                <span class="detail-value">{{
                  withdrawalDetails.withdrawalReason
                }}</span>
              </div>
              <div
                class="detail-field"
                v-if="withdrawalDetails.transactionHash"
              >
                <span class="detail-label">{{
                  t("withdrawalDetail_label_transactionHash")
                }}</span>
                <div class="crypto-address">
                  {{ withdrawalDetails.transactionHash }}
                  <button
                    class="copy-address-btn"
                    @click="copyAddress(withdrawalDetails.transactionHash)"
                  >
                    <i :class="copied ? 'fas fa-check' : 'fas fa-copy'"></i>
                  </button>
                </div>
              </div>
            </div>

            <div
              class="detail-section full-width"
              v-if="
                withdrawalDetails.supportQuestions &&
                withdrawalDetails.supportQuestions.length > 0
              "
            >
              <h3>
                <i class="fas fa-circle-question"></i>
                {{ t("depositDetail_section_support") }}
              </h3>
              <div
                v-for="question in withdrawalDetails.supportQuestions"
                :key="question.id"
                class="detail-field"
              >
                <span class="detail-label">{{
                  capitalizeLabel(question.name)
                }}</span>
                <span class="detail-value">{{ question.answer || "-" }}</span>
              </div>
            </div>

            <!-- Additional Information Submitted -->
            <div
              class="detail-section full-width"
              v-if="
                documentRequest && documentRequest.requestStatus === 'submitted'
              "
            >
              <h3>
                <i class="fas fa-file-check"></i>
                {{ t("withdrawalDetail_section_additionalSubmitted") }}
              </h3>
              <div class="submitted-info">
                <div class="submitted-header">
                  <div class="submitted-status">
                    <i class="fas fa-check-circle"></i>
                    <span>{{
                      tParams(
                        "withdrawalDetail_submittedOn",
                        "Submitted on {datetime}",
                        {
                          datetime: formatDateTime(documentRequest.submittedAt),
                        },
                      )
                    }}</span>
                  </div>
                </div>
                <div class="submitted-items">
                  <div
                    v-for="item in documentRequest.items"
                    :key="item.id"
                    class="submitted-item"
                  >
                    <div class="submitted-item-header">
                      <i
                        :class="
                          item.itemType === 'question'
                            ? 'fas fa-question-circle'
                            : 'fas fa-file-alt'
                        "
                      ></i>
                      <span class="submitted-item-title">
                        {{
                          item.itemType === "question"
                            ? item.questionText
                            : item.documentName
                        }}
                      </span>
                    </div>
                    <div
                      class="submitted-item-content"
                      v-if="item.clientResponse"
                    >
                      <!-- Question Answer -->
                      <div v-if="item.itemType === 'question'">
                        <div
                          v-if="
                            item.questionType === 'file_upload' ||
                            (item.clientResponse.files &&
                              item.clientResponse.files.length > 0)
                          "
                        >
                          <div
                            v-for="(file, idx) in item.clientResponse.files ||
                            []"
                            :key="idx"
                            class="submitted-file"
                          >
                            <i class="fas fa-file-pdf"></i>
                            <a
                              :href="file.downloadUrl || file.s3Url"
                              target="_blank"
                              class="file-link"
                            >
                              {{ file.fileName }}
                            </a>
                            <span class="file-date" v-if="file.uploadedAt">
                              ({{ formatDateTime(file.uploadedAt) }})
                            </span>
                          </div>
                        </div>
                        <div v-else-if="item.clientResponse.answerText">
                          <div class="submitted-answer">
                            {{ item.clientResponse.answerText }}
                          </div>
                        </div>
                        <div
                          v-else-if="
                            item.clientResponse.answerValues &&
                            item.clientResponse.answerValues.length > 0
                          "
                        >
                          <div class="submitted-answer">
                            {{ item.clientResponse.answerValues.join(", ") }}
                          </div>
                        </div>
                      </div>
                      <!-- Document Files -->
                      <div
                        v-else-if="
                          item.itemType === 'document' &&
                          item.clientResponse.files &&
                          item.clientResponse.files.length > 0
                        "
                      >
                        <div
                          v-for="(file, idx) in item.clientResponse.files"
                          :key="idx"
                          class="submitted-file"
                        >
                          <i class="fas fa-file-pdf"></i>
                          <a
                            :href="file.downloadUrl || file.s3Url"
                            target="_blank"
                            class="file-link"
                          >
                            {{ file.fileName }}
                          </a>
                          <span class="file-date" v-if="file.uploadedAt">
                            ({{ formatDateTime(file.uploadedAt) }})
                          </span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Transaction Timeline -->
            <div
              class="detail-section full-width"
              v-if="
                withdrawalDetails.statusHistory &&
                withdrawalDetails.statusHistory.length > 0
              "
            >
              <h3>
                <i class="fas fa-history"></i>
                {{ t("depositDetail_section_timeline") }}
              </h3>
              <div class="timeline">
                <div
                  class="timeline-item"
                  v-for="(history, index) in withdrawalDetails.statusHistory"
                  :key="history.id"
                >
                  <div
                    :class="[
                      'timeline-dot',
                      getTimelineDotClass(
                        history.newStatus,
                        index === withdrawalDetails.statusHistory.length - 1,
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
            v-if="withdrawalDetails.transactionId"
            :order-id="withdrawalDetails.transactionId"
            transaction-type="withdrawal"
            :record-id="withdrawalDetails.id"
          />

          <!-- Action Buttons -->
          <div
            class="actions-panel"
            v-if="
              showActions &&
              withdrawalDetails.status === 'pending' &&
              (hasApprovePermission ||
                hasRejectPermission ||
                hasNeedMoreDocumentsPermission ||
                hasContactClientPermission)
            "
          >
            <button
              v-if="hasApprovePermission"
              class="action-btn-large approve"
              @click="handleApprove"
              :disabled="approving"
            >
              <i
                :class="
                  approving ? 'fas fa-spinner fa-spin' : 'fas fa-check-circle'
                "
              ></i>
              <span>{{ t("withdrawalDetail_btnApproveProcess") }}</span>
            </button>
            <button
              v-if="hasNeedMoreDocumentsPermission"
              class="action-btn-large need-docs"
              @click="handleRequestDocuments"
            >
              <i class="fas fa-file-upload"></i>
              <span>{{ t("withdrawalDetail_btnNeedMoreDocs") }}</span>
            </button>
            <button
              v-if="hasRejectPermission"
              class="action-btn-large reject"
              @click="handleReject"
            >
              <i class="fas fa-times-circle"></i>
              <span>{{ t("depositDetail_btnReject") }}</span>
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
import withdrawalApi from "../../services/withdrawalApi";
import EmailEditorModal from "../common/EmailEditorModal.vue";
import PspCallbackSection from "../common/PspCallbackSection.vue";
import { formatCurrency, formatNumber } from "../../utils/helpers";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { translateApiErrorMessage } from "@/i18n/adminI18nBridge";

const { t, tParams, languageStore } = useAdminI18n();

const props = defineProps({
  withdrawalId: {
    type: [Number, String],
    required: true,
  },
  withdrawal: {
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
  hasNeedMoreDocumentsPermission: {
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

const emit = defineEmits(["approve", "reject", "request-documents", "refresh"]);

const loading = ref(false);
const error = ref(null);
const withdrawalDetails = ref(null);
const documentRequest = ref(null);
const copied = ref(false);
const showEmailModal = ref(false);
const sendingEmail = ref(false);

const recipientEmail = computed(() => {
  return withdrawalDetails.value?.email || "";
});

const recipientName = computed(() => {
  if (!withdrawalDetails.value) return "";
  return `${withdrawalDetails.value.firstName || ""} ${withdrawalDetails.value.lastName || ""}`.trim();
});

const displayWithdrawal = computed(() => {
  return {
    ...(props.withdrawal || {}),
    ...(withdrawalDetails.value || {}),
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
  const status = String(displayWithdrawal.value?.status || "").toLowerCase();
  return (
    ["cancaled", "cancelled", "canceled"].includes(status) &&
    Boolean(canceledReasonText.value)
  );
});

const canceledReasonText = computed(() => {
  return String(displayWithdrawal.value?.adminNotes || "").trim();
});

const platformUnit = computed(() => {
  const details = displayWithdrawal.value;
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

const withdrawalScaleLabel = computed(() => {
  const details = displayWithdrawal.value;
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

const deductedAmountLabel = computed(() => {
  if (!withdrawalScaleLabel.value) {
    return "";
  }

  const platformAmount = displayWithdrawal.value?.platformAmount;
  if (
    platformAmount === undefined ||
    platformAmount === null ||
    platformAmount === ""
  ) {
    return "";
  }

  const formattedAmount = formatTrimmedNumber(platformAmount, 8);
  if (!formattedAmount) {
    return "";
  }

  const unit = String(platformUnit.value || "").trim();
  return unit ? `${formattedAmount} ${unit}` : formattedAmount;
});

const tradingAccountNumber = computed(() => {
  return String(
    getFirstDefined(
      displayWithdrawal.value?.accountNumber,
      displayWithdrawal.value?.providerAccountId,
      displayWithdrawal.value?.accountName,
    ) || "",
  ).trim();
});

const tradingAccountGroupLabel = computed(() => {
  return String(
    getFirstDefined(
      displayWithdrawal.value?.groupLabel,
      displayWithdrawal.value?.tradingGroupLabel,
      displayWithdrawal.value?.label,
      displayWithdrawal.value?.groupName,
      displayWithdrawal.value?.tradingGroupName,
    ) || "",
  ).trim();
});

// 加载提款详情
const loadWithdrawalDetails = async () => {
  if (!props.isExpanded) return;

  loading.value = true;
  error.value = null;

  try {
    const response = await withdrawalApi.getWithdrawal(props.withdrawalId);
    if (response.success) {
      withdrawalDetails.value = response.data;

      // 如果没有statusHistory，尝试加载
      if (!withdrawalDetails.value.statusHistory) {
        const historyResponse = await withdrawalApi.getWithdrawalHistory(
          props.withdrawalId,
        );
        if (historyResponse.success) {
          withdrawalDetails.value.statusHistory = historyResponse.data || [];
        }
      }

      // 加载文档请求（如果有）
      try {
        const docRequestResponse = await withdrawalApi.getDocumentRequest(
          props.withdrawalId,
        );
        if (docRequestResponse.success && docRequestResponse.data) {
          documentRequest.value = docRequestResponse.data;
          // 处理JSON字段（后端可能已经解析，但前端也需要处理以防万一）
          if (documentRequest.value.items) {
            documentRequest.value.items.forEach((item) => {
              // 处理 questionOptions 和 acceptedFileTypes（如果后端没有解析）
              if (
                item.questionOptions &&
                typeof item.questionOptions === "string"
              ) {
                try {
                  item.questionOptions = JSON.parse(item.questionOptions);
                } catch (e) {
                  item.questionOptions = [];
                }
              }
              if (
                item.acceptedFileTypes &&
                typeof item.acceptedFileTypes === "string"
              ) {
                try {
                  item.acceptedFileTypes = JSON.parse(item.acceptedFileTypes);
                } catch (e) {
                  item.acceptedFileTypes = [];
                }
              }
              // 处理 clientResponse
              if (item.clientResponse) {
                if (typeof item.clientResponse === "string") {
                  try {
                    item.clientResponse = JSON.parse(item.clientResponse);
                  } catch (e) {
                    // 如果不是JSON，保持原样
                  }
                }
              }
            });
          }
        } else {
          // 如果没有数据，清空 documentRequest
          documentRequest.value = null;
        }
      } catch (err) {
        // 文档请求加载失败不影响主流程
        console.warn("Failed to load document request:", err);
        documentRequest.value = null;
      }
    } else {
      const raw = response.message || t("withdrawalDetail_err_loadDetails");
      error.value = translateApiErrorMessage(response.errorCode, raw);
    }
  } catch (err) {
    console.error("Failed to load withdrawal details:", err);
    const data = err?.response?.data ?? err;
    const rawMsg =
      data?.message || err?.message || t("withdrawalDetail_err_loadDetails");
    error.value = translateApiErrorMessage(data?.errorCode, rawMsg);
  } finally {
    loading.value = false;
  }
};

// 监听展开状态
watch(
  () => props.isExpanded,
  (newVal) => {
    if (newVal) {
      // 展开时总是重新加载数据，确保显示最新的补充资料
      loadWithdrawalDetails();
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
    pending: "withdrawalDetail_status_requestSubmitted",
    processing: "withdrawalDetail_status_paymentProcessing",
    completed: "withdrawalDetail_status_transactionComplete",
    rejected: "withdrawalDetail_status_requestRejected",
    cancaled: "withdrawalMgmt_status_cancelled",
    cancelled: "withdrawalMgmt_status_cancelled",
    canceled: "withdrawalMgmt_status_cancelled",
    failed: "withdrawalMgmt_status_failed",
  };
  const key = map[s];
  return key ? t(key) : status;
};

// 获取timeline圆点类名
const getTimelineDotClass = (status, isLast) => {
  const s = String(status || "").toLowerCase();
  if (s === "completed") return "completed";
  if (s === "rejected") return "rejected";
  if (isLast && s !== "completed" && s !== "rejected") return "active";
  return "";
};

// 格式化 BSB (XXX-XXX)
const formatBSB = (bsb) => {
  if (!bsb) return "";
  const cleanBSB = bsb.replace(/\D/g, "");
  if (cleanBSB.length === 6) {
    return cleanBSB.replace(/(\d{3})(\d{3})/, "$1-$2");
  }
  return bsb;
};

const capitalizeLabel = (value) => {
  if (!value) return "";
  const text = String(value);
  return text.charAt(0).toUpperCase() + text.slice(1);
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
  emit("approve", props.withdrawalId);
};

// 处理拒绝
const handleReject = () => {
  emit("reject", props.withdrawalId);
};

// 处理请求文档
const handleRequestDocuments = () => {
  emit("request-documents", props.withdrawalId);
};

// 联系客户
const handleContactClient = () => {
  const email = withdrawalDetails.value?.email;

  if (!email) {
    console.error("Contact Client: Email is missing", withdrawalDetails.value);
    alert(t("depositDetail_alert_noClientEmail"));
    return;
  }

  showEmailModal.value = true;
};

// 发送邮件
const handleSendEmail = async (emailData) => {
  sendingEmail.value = true;
  try {
    // 调用后端API发送邮件
    const response = await withdrawalApi.sendEmail(
      props.withdrawalId,
      emailData,
    );
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

// 初始加载
if (props.isExpanded) {
  loadWithdrawalDetails();
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
  color: var(--color-danger);
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

.payment-account-info {
  margin-top: 20px;
  padding-top: 20px;
  border-top: 2px solid var(--color-border);
  background: var(--color-success-soft);
  padding: 15px;
  border-radius: var(--radius-md);
  border-left: 4px solid var(--color-success);
}

.payment-account-info h4 {
  margin: 0 0 15px 0;
  color: var(--color-ink);
  font-size: 14px;
  font-weight: 600;
  display: flex;
  align-items: center;
}

.crypto-address {
  font-family: "Courier New", monospace;
  font-size: 12px;
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

.timeline-dot.active {
  background: var(--color-brand-solid);
  border-color: var(--color-brand);
  animation: pulse 2s infinite;
}

.timeline-dot.rejected {
  background: var(--color-danger-solid);
  border-color: var(--color-danger);
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

.submitted-info {
  margin-top: 16px;
}

.submitted-header {
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 2px solid var(--color-border);
}

.submitted-status {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--color-success);
  font-weight: 600;
  font-size: 14px;
}

.submitted-status i {
  font-size: 18px;
}

.submitted-items {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.submitted-item {
  padding: 16px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-md);
  border-left: 4px solid var(--color-brand);
}

.submitted-item-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;
}

.submitted-item-header i {
  color: var(--color-brand);
  font-size: 18px;
}

.submitted-item-title {
  font-weight: 600;
  color: var(--color-ink);
  font-size: 14px;
}

.submitted-item-content {
  margin-top: 8px;
}

.submitted-answer {
  padding: 12px;
  background: var(--color-surface);
  border-radius: var(--radius-sm);
  color: var(--color-ink);
  font-size: 14px;
  line-height: 1.6;
}

.submitted-file {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  background: var(--color-surface);
  border-radius: var(--radius-sm);
  margin-bottom: 8px;
}

.submitted-file:last-child {
  margin-bottom: 0;
}

.submitted-file i {
  color: var(--color-danger);
  font-size: 18px;
}

.file-link {
  color: var(--color-brand);
  text-decoration: none;
  font-weight: 500;
  font-size: 14px;
  flex: 1;
}

.file-link:hover {
  text-decoration: underline;
}

.file-date {
  color: var(--color-muted);
  font-size: 12px;
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

.action-btn-large:hover {
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

.action-btn-large.approve:hover {
  border-color: var(--color-success);
  background: var(--color-success-soft);
}

.action-btn-large.reject {
  border-color: var(--color-danger);
}

.action-btn-large.reject i {
  color: var(--color-danger);
}

.action-btn-large.reject:hover {
  border-color: var(--color-danger);
  background: var(--color-danger-soft);
}

.action-btn-large.need-docs {
  border-color: var(--color-warning);
}

.action-btn-large.need-docs i {
  color: var(--color-warning);
}

.action-btn-large.need-docs:hover {
  border-color: var(--color-warning);
  background: var(--color-warning-soft);
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
