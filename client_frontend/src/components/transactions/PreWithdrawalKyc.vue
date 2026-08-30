<template>
  <div class="pre-withdrawal-kyc">
    <div class="withdraw-stepper">
      <div class="step-item active">
        <div class="step-index">1</div>
        <div class="step-label">
          {{ t("transStepMethodAddress", "Method & Address") }}
        </div>
      </div>
      <div class="step-line"></div>
      <div class="step-item">
        <div class="step-index">2</div>
        <div class="step-label">
          {{ t("transStepVerificationAmount", "Verification / Amount") }}
        </div>
      </div>
      <div class="step-line"></div>
      <div class="step-item">
        <div class="step-index">3</div>
        <div class="step-label">{{ t("transStepConfirm", "Confirm") }}</div>
      </div>
    </div>

    <div class="withdraw-card">
      <div class="withdraw-card-header">
        <h3>
          <i class="fas fa-credit-card"></i>
          {{
            t(
              "transStep1SelectWithdrawalMethodAddress",
              "Step 1 - Select Withdrawal Method & Address",
            )
          }}
        </h3>
      </div>

      <div class="withdraw-card-body">
        <div v-if="loading" class="methods-loading">
          <i class="fas fa-spinner fa-spin methods-loading-icon"></i>
          <span>{{
            t("transLoadingWithdrawalMethods", "Loading withdrawal methods...")
          }}</span>
        </div>

        <template v-else>
          <div
            v-if="availableTypes.length"
            class="form-group asset-filter-group"
          >
            <label class="form-label">
              <i class="fas fa-layer-group"></i>
              {{ t("transPaymentType", "Payment Type") }}
            </label>
            <div class="asset-filter">
              <button
                v-for="assetType in availableTypes"
                :key="assetType"
                type="button"
                class="asset-filter-btn"
                :class="{ active: assetFilter === assetType }"
                @click="selectAssetFilter(assetType)"
              >
                <i
                  :class="
                    assetType === 'crypto'
                      ? 'fas fa-coins'
                      : 'fas fa-money-bill-wave'
                  "
                ></i>
                {{
                  assetType === "crypto"
                    ? t("transCrypto", "Crypto")
                    : t("transFiat", "Fiat")
                }}
              </button>
            </div>
          </div>

          <div v-if="assetFilter || !availableTypes.length" class="form-group">
            <label class="form-label">
              <i class="fas fa-arrow-right-arrow-left"></i>
              {{ t("transWithdrawalMethod", "Withdrawal Method") }}
            </label>

            <div ref="dropdownRef" class="select-shell">
              <button
                type="button"
                class="select-trigger"
                :class="{ open: isOpen, empty: !selectedGateway }"
                @click="isOpen = !isOpen"
              >
                <div class="trigger-main">
                  <div class="trigger-icon">
                    <i
                      :class="selectedGateway?.iconClass || 'fas fa-right-left'"
                    ></i>
                  </div>
                  <div class="trigger-text">
                    <div class="trigger-title">
                      {{
                        selectedGateway?.gatewayName ||
                        t(
                          "transSelectWithdrawalMethod",
                          "Please select a withdrawal method",
                        )
                      }}
                    </div>
                    <div v-if="selectedGateway" class="trigger-subtitle">
                      {{ getGatewayEta(selectedGateway) }}
                    </div>
                  </div>
                </div>
                <i class="fas fa-chevron-down trigger-chevron"></i>
              </button>

              <div v-if="isOpen" class="select-dropdown">
                <button
                  type="button"
                  class="dropdown-option"
                  :class="{ selected: !modelValue }"
                  @click="selectGateway('')"
                >
                  <div class="option-main">
                    <div class="option-icon">
                      <i class="fas fa-right-left"></i>
                    </div>
                    <div class="option-text">
                      <div class="option-title">
                        {{
                          t(
                            "transSelectWithdrawalMethod",
                            "Please select a withdrawal method",
                          )
                        }}
                      </div>
                    </div>
                  </div>
                  <i v-if="!modelValue" class="fas fa-check option-check"></i>
                </button>

                <template
                  v-for="group in groupedFilteredGateways"
                  :key="group.name"
                >
                  <div class="dropdown-group">
                    <div class="dropdown-group-label">{{ group.name }}</div>
                    <button
                      v-for="gateway in group.gateways"
                      :key="gateway.gatewayKey"
                      type="button"
                      class="dropdown-option"
                      :class="{ selected: gateway.gatewayKey === modelValue }"
                      @click="selectGateway(gateway.gatewayKey)"
                    >
                      <div class="option-main">
                        <div class="option-icon">
                          <i
                            :class="gateway.iconClass || 'fas fa-right-left'"
                          ></i>
                        </div>
                        <div class="option-text">
                          <div class="option-title">
                            {{ gateway.gatewayName }}
                          </div>
                          <div class="option-subtitle">
                            {{ getGatewayEta(gateway) }}
                          </div>
                          <div
                            v-if="getGatewaySupportedAssets(gateway)"
                            class="option-meta"
                          >
                            {{ getGatewaySupportedAssets(gateway) }}
                          </div>
                        </div>
                      </div>
                      <i
                        v-if="gateway.gatewayKey === modelValue"
                        class="fas fa-check option-check"
                      ></i>
                    </button>
                  </div>
                </template>
              </div>
            </div>

            <div
              v-if="
                !gateways.length || (assetFilter && !filteredGateways.length)
              "
              class="field-note muted"
            >
              {{
                t(
                  "transNoWithdrawalMethods",
                  "No withdrawal methods available.",
                )
              }}
            </div>
          </div>

          <div v-if="selectedGateway" class="selected-method-card">
            <div class="selected-method-main">
              <div class="selected-method-icon">
                <i
                  :class="
                    selectedGateway.iconClass || 'fas fa-building-columns'
                  "
                ></i>
              </div>
              <div>
                <div class="selected-method-name">
                  {{ selectedGateway.gatewayName }}
                </div>
                <div
                  v-if="getGatewaySupportedAssets(selectedGateway)"
                  class="selected-method-supported"
                >
                  {{ getGatewaySupportedAssets(selectedGateway) }}
                </div>
              </div>
            </div>
            <div class="selected-method-eta">
              {{ getGatewayEta(selectedGateway) }}
            </div>
          </div>

          <div
            v-if="selectedGateway && isPrewithdrawEnabled"
            class="address-section show"
          >
            <div class="address-section-title">
              <i class="fas fa-map-marker-alt"></i>
              {{ t("transWithdrawalAddress", "Withdrawal Address") }}
            </div>

            <div class="address-info-box">
              <i class="fas fa-info-circle"></i>
              <div>
                {{
                  t(
                    "transWithdrawalAddressInfo",
                    "Select a previously verified address for instant withdrawal, or add a new address which requires KYC verification before first use.",
                  )
                }}
              </div>
            </div>

            <div v-if="templateLoading" class="template-state">
              <i class="fas fa-spinner fa-spin"></i>
              {{
                t(
                  "transLoadingWithdrawalAddresses",
                  "Loading withdrawal addresses...",
                )
              }}
            </div>

            <div v-else-if="templateError" class="template-state error">
              <i class="fas fa-exclamation-circle"></i>
              {{ templateError }}
            </div>

            <div class="address-list">
              <button
                v-for="address in visibleAddressOptions"
                :key="address.id"
                type="button"
                :class="[
                  'address-card',
                  {
                    selected: selectedAddressId === address.id,
                    disabled: !address.selectable,
                  },
                ]"
                @click="address.selectable && (selectedAddressId = address.id)"
              >
                <div class="address-radio"></div>
                <div :class="['address-icon', address.iconType]">
                  <i :class="address.iconClass"></i>
                </div>
                <div class="address-details">
                  <div class="address-name">{{ address.name }}</div>
                  <template v-if="address.values?.length">
                    <div
                      v-for="line in address.values"
                      :key="line.label"
                      class="address-value address-value-multi"
                    >
                      <span class="address-value-label">{{ line.label }}:</span>
                      <span class="address-value-text">{{ line.value }}</span>
                    </div>
                  </template>
                  <div v-else class="address-value">{{ address.value }}</div>
                </div>
                <span :class="['address-badge', address.badgeClass]">
                  <i :class="address.badgeIcon"></i> {{ address.badgeText }}
                </span>
                <span
                  v-if="address.submissionId"
                  class="address-delete-btn"
                  role="button"
                  :title="t('transRemoveAddress', 'Remove address')"
                  @click.stop="handleHideAddress(address)"
                >
                  <i class="fas fa-trash-alt"></i>
                </span>
              </button>

              <button
                type="button"
                :class="[
                  'address-card',
                  'new-address',
                  { selected: selectedAddressId === NEW_ADDRESS_ID },
                ]"
                @click="selectedAddressId = NEW_ADDRESS_ID"
              >
                <div class="address-radio"></div>
                <div class="address-icon new">
                  <i class="fas fa-plus"></i>
                </div>
                <div class="address-details">
                  <div class="address-name">
                    {{ t("transAddNewAddress", "Add New Address") }}
                  </div>
                  <div class="address-value address-value-plain">
                    {{
                      t(
                        "transCompleteKycForNewAddress",
                        "Complete KYC verification for a new withdrawal address",
                      )
                    }}
                  </div>
                </div>
                <span class="address-badge new-badge">
                  <i class="fas fa-id-card"></i>
                  {{ t("transKycRequired", "KYC Required") }}
                </span>
              </button>
            </div>

            <div v-if="shouldShowInlineKyc" class="inline-addr-form show">
              <div class="inline-addr-hdr">
                <div class="inline-addr-hdr-title">
                  <i class="fas fa-plus-circle"></i>
                  {{
                    t(
                      "transNewWithdrawalAddressKyc",
                      "New Withdrawal Address - KYC Verification",
                    )
                  }}
                </div>
                <button
                  type="button"
                  class="inline-cancel-btn"
                  @click="closeInlineKyc"
                >
                  <i class="fas fa-arrow-left"></i> {{ t("transBack", "Back") }}
                </button>
              </div>

              <div class="inline-addr-body">
                <div class="kyc-info-banner">
                  <i class="fas fa-shield-alt"></i>
                  <p>
                    {{
                      t(
                        "transFirstTimeWithdrawalAddressInfo",
                        "This is your first time using this withdrawal address. To comply with AML / KYC regulations, please fill in the address details and complete all verification questions below. Your application will be reviewed within 1-2 business days.",
                      )
                    }}
                  </p>
                </div>

                <div v-if="creatingKycSubmission" class="template-state">
                  <i class="fas fa-spinner fa-spin"></i>
                  {{
                    t(
                      "transCreatingKycSubmission",
                      "Creating KYC submission...",
                    )
                  }}
                </div>

                <div v-else-if="inlineKycSubmitted" class="inline-kyc-waiting">
                  <div class="waiting-icon">
                    <i class="fas fa-clock"></i>
                  </div>
                  <h4>
                    {{
                      t(
                        "transVerificationSubmittedTitle",
                        "Verification submitted",
                      )
                    }}
                  </h4>
                  <p>
                    {{
                      t(
                        "transWithdrawalVerificationSubmitted",
                        "Your withdrawal address verification has been submitted. Please wait for review.",
                      )
                    }}
                  </p>
                </div>

                <div
                  v-else-if="inlineKycSubmissionId"
                  class="inline-kyc-content"
                >
                  <WithdrawalAddressKycVerification
                    :initial-submission-id="inlineKycSubmissionId"
                    @close="handleInlineKycClose"
                    @submitted="handleInlineKycSubmitted"
                  />
                </div>
              </div>
            </div>

            <div v-if="!shouldShowInlineKyc" class="step-actions">
              <button
                type="button"
                class="continue-btn"
                :disabled="!selectedAddressId"
                @click="handleContinue"
              >
                {{ t("transContinue", "Continue") }}
                <i class="fas fa-arrow-right"></i>
              </button>
            </div>
          </div>
        </template>
      </div>
    </div>

    <WithdrawalSuccessModal
      v-if="showWithdrawalSuccessModal"
      @close="showWithdrawalSuccessModal = false"
    />
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import withdrawalSubmissionService from "@/services/withdrawalSubmissionService";
import WithdrawalAddressKycVerification from "@/components/transactions/WithdrawalAddressKycVerification.vue";
import WithdrawalSuccessModal from "@/components/transactions/WithdrawalSuccessModal.vue";
import { useLanguageStore } from "@/stores/language";
import { groupGatewaysByCurrency } from "@/utils/gatewayPsp";

const languageStore = useLanguageStore();
const t = (key, fallback = "") => languageStore.t(key, fallback);

const props = defineProps({
  modelValue: {
    type: String,
    default: "",
  },
  gateways: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
  templateLoading: {
    type: Boolean,
    default: false,
  },
  templateError: {
    type: String,
    default: "",
  },
  templateMeta: {
    type: Object,
    default: null,
  },
  templates: {
    type: [Array, Object],
    default: () => [],
  },
});

const emit = defineEmits([
  "update:modelValue",
  "refresh-submissions",
  "proceed-step-two",
]);

const NEW_ADDRESS_ID = "new-address";
const isOpen = ref(false);
const dropdownRef = ref(null);
const selectedAddressId = ref("");
const creatingKycSubmission = ref(false);
const inlineKycSubmissionId = ref(null);
const inlineKycSubmitted = ref(false);
const showWithdrawalSuccessModal = ref(false);
const hasAutoProceededToStepTwo = ref(false);

const selectedGateway = computed(
  () =>
    props.gateways.find((gateway) => gateway.gatewayKey === props.modelValue) ||
    null,
);

// 出金通道先按 fiat / crypto 分组，选了类型再显示对应通道
const assetFilter = ref("");

// 网关 type 缺省按 fiat 处理（DB 默认值就是 fiat）
const gatewayType = (gateway) =>
  String(gateway?.type || "fiat").toLowerCase() === "crypto"
    ? "crypto"
    : "fiat";

const availableTypes = computed(() => {
  const order = ["fiat", "crypto"];
  return order.filter((type) =>
    props.gateways.some((gateway) => gatewayType(gateway) === type),
  );
});

const filteredGateways = computed(() => {
  if (!assetFilter.value) return [];
  return props.gateways.filter(
    (gateway) => gatewayType(gateway) === assetFilter.value,
  );
});

const groupedFilteredGateways = computed(() =>
  groupGatewaysByCurrency(filteredGateways.value),
);

const selectAssetFilter = (type) => {
  if (assetFilter.value === type) return;
  assetFilter.value = type;
  // 切换类型后，已选通道若不属于当前类型则清空
  if (selectedGateway.value && gatewayType(selectedGateway.value) !== type) {
    emit("update:modelValue", "");
  }
  isOpen.value = false;
};

// 通道列表加载完成后定默认类型：跟随已选通道 > 只有一种类型时自动选 > 两种都有时让用户先选
watch(
  () => props.gateways,
  () => {
    const types = availableTypes.value;
    if (assetFilter.value && types.includes(assetFilter.value)) return;

    if (
      selectedGateway.value &&
      types.includes(gatewayType(selectedGateway.value))
    ) {
      assetFilter.value = gatewayType(selectedGateway.value);
    } else if (types.length === 1) {
      assetFilter.value = types[0];
    } else {
      assetFilter.value = "";
    }
  },
  { immediate: true },
);

const normalizedTemplates = computed(() => {
  if (Array.isArray(props.templates)) return props.templates;
  if (props.templates && typeof props.templates === "object")
    return [props.templates];
  return [];
});

const findNestedTemplateId = (value, visited = new Set()) => {
  if (!value || typeof value !== "object") return null;
  if (visited.has(value)) return null;
  visited.add(value);

  if (value.templateId != null && value.templateId !== "") {
    return value.templateId;
  }

  if (value.template_id != null && value.template_id !== "") {
    return value.template_id;
  }

  if (Array.isArray(value)) {
    for (const item of value) {
      const nestedId = findNestedTemplateId(item, visited);
      if (nestedId != null && nestedId !== "") {
        return nestedId;
      }
    }
    return null;
  }

  for (const nestedValue of Object.values(value)) {
    const nestedId = findNestedTemplateId(nestedValue, visited);
    if (nestedId != null && nestedId !== "") {
      return nestedId;
    }
  }

  return null;
};

const resolvedTemplateMeta = computed(
  () =>
    props.templateMeta?.data ||
    props.templateMeta ||
    normalizedTemplates.value[0] ||
    null,
);

const resolvedTemplateId = computed(
  () =>
    resolvedTemplateMeta.value?.templateId ||
    resolvedTemplateMeta.value?.template_id ||
    normalizedTemplates.value[0]?.templateId ||
    normalizedTemplates.value[0]?.template_id ||
    findNestedTemplateId(props.templateMeta) ||
    findNestedTemplateId(props.templates) ||
    null,
);

const isPrewithdrawEnabled = computed(() => {
  const rawValue = resolvedTemplateMeta.value?.prewithdrawenabled;
  if (
    rawValue === false ||
    rawValue === 0 ||
    rawValue === "0" ||
    rawValue === "false"
  ) {
    return false;
  }
  return true;
});

const selectedAddressOption = computed(
  () =>
    normalizedAddressOptions.value.find(
      (item) => item.id === selectedAddressId.value,
    ) || null,
);

const shouldShowInlineKyc = computed(() =>
  Boolean(inlineKycSubmissionId.value),
);

const visibleAddressOptions = computed(() => {
  if (
    !shouldShowInlineKyc.value ||
    !selectedAddressId.value ||
    selectedAddressId.value === NEW_ADDRESS_ID
  ) {
    return normalizedAddressOptions.value;
  }

  return selectedAddressOption.value
    ? [selectedAddressOption.value]
    : normalizedAddressOptions.value;
});

const normalizeDetailValue = (value) => {
  if (value == null) return "";

  if (Array.isArray(value)) {
    return value
      .map((item) => String(item || "").trim())
      .filter(Boolean)
      .join(", ");
  }

  return String(value).trim();
};

const normalizedAddressOptions = computed(() =>
  normalizedTemplates.value
    .filter((item) => {
      const submissionStatus = String(
        item.submissionStatus || item.status || "",
      ).toLowerCase();
      return (
        submissionStatus === "draft" ||
        submissionStatus === "incomplete" ||
        submissionStatus === "approved" ||
        submissionStatus === "submitted" ||
        submissionStatus === "under_review" ||
        submissionStatus === "resubmit_required" ||
        submissionStatus === "rejected" ||
        submissionStatus === "expired"
      );
    })
    .map((item, index) => {
      const submissionStatus = String(
        item.submissionStatus || item.status || "",
      ).toLowerCase();
      const detail =
        item.detail &&
        typeof item.detail === "object" &&
        !Array.isArray(item.detail)
          ? item.detail
          : {};
      const gatewayKey = String(
        item.gatewayKey ||
          resolvedTemplateMeta.value?.gatewayKey ||
          selectedGateway.value?.gatewayKey ||
          "",
      ).toLowerCase();
      const badgeStatusMap = {
        draft: {
          className: "draft",
          icon: "fas fa-pen",
          text: t("transDraft", "Draft"),
        },
        incomplete: {
          className: "incomplete",
          icon: "fas fa-clock",
          text: t("transIncomplete", "Incomplete"),
        },
        approved: {
          className: "verified",
          icon: "fas fa-shield-alt",
          text: t("transVerified", "Verified"),
        },
        submitted: {
          className: "submitted",
          icon: "fas fa-hourglass-half",
          text: t("transPendingReview", "Pending review"),
        },
        under_review: {
          className: "submitted",
          icon: "fas fa-hourglass-half",
          text: t("transPendingReview", "Pending review"),
        },
        resubmit_required: {
          className: "resubmit-required",
          icon: "fas fa-file-circle-exclamation",
          text: t("transNeedDoc", "Need doc"),
        },
        rejected: {
          className: "rejected",
          icon: "fas fa-circle-xmark",
          text: t("transRejected", "Rejected"),
        },
        expired: {
          className: "expired",
          icon: "fas fa-ban",
          text: t("transExpired", "Expired"),
        },
      };
      const badgeConfig = badgeStatusMap[submissionStatus] || {
        className: "new-badge",
        icon: "fas fa-id-card",
        text: submissionStatus || t("transUnknown", "Unknown"),
      };
      const selectableStatuses = [
        "draft",
        "incomplete",
        "approved",
        "resubmit_required",
      ];
      const detailLines =
        gatewayKey === "ibeepay"
          ? [
              {
                label: t("transName", "Name"),
                value: normalizeDetailValue(detail.name || item.name),
              },
              {
                label: t("transEmail", "Email"),
                value: normalizeDetailValue(detail.email || item.email),
              },
              {
                label: t("transPhone", "Phone"),
                value: normalizeDetailValue(detail.phone || item.phone),
              },
              {
                label: "DOB",
                value: normalizeDetailValue(
                  detail.dob || detail.birthday || item.dob || item.birthday,
                ),
              },
            ].filter((line) => line.value)
          : [
              {
                label: t("transAddress", "Address"),
                value: normalizeDetailValue(
                  detail.wallet_address ||
                    detail.walletAddress ||
                    detail.address,
                ),
              },
              {
                label: t("transChain", "Chain"),
                value: normalizeDetailValue(detail.chain || detail.network),
              },
              {
                label: t("transEmail", "Email"),
                value: normalizeDetailValue(detail.email || item.email),
              },
              {
                label: t("transPhone", "Phone"),
                value: normalizeDetailValue(detail.phone),
              },
              {
                label: t("transName", "Name"),
                value: normalizeDetailValue(detail.name),
              },
            ].filter((line) => line.value);

      const fallbackValue = normalizeDetailValue(
        detail.wallet_address ||
          detail.walletAddress ||
          detail.address ||
          detail.email ||
          detail.phone ||
          item.email,
      );

      return {
        id: String(
          item.id || item.paymentId || item.templateId || `address-${index}`,
        ),
        submissionId: item.id || item.paymentId || null,
        submissionStatus,
        selectable: selectableStatuses.includes(submissionStatus),
        name:
          submissionStatus === "draft" || submissionStatus === "incomplete"
            ? t("transNewVerification", "New verification")
            : resolvedTemplateMeta.value?.gatewayName ||
              selectedGateway.value?.gatewayName ||
              t("transGateway", "Gateway"),
        values: detailLines,
        value: detailLines.length
          ? ""
          : fallbackValue ||
            (item.createdAt
              ? `${t("transCreatedAt", "Created at")} ${item.createdAt}`
              : ""),
        iconClass:
          item.iconClass ||
          selectedGateway.value?.iconClass ||
          "fas fa-credit-card",
        iconType: getIconType(item),
        badgeClass: badgeConfig.className,
        badgeIcon: badgeConfig.icon,
        badgeText: badgeConfig.text,
      };
    }),
);

const gatewayEtaMap = {
  bank_wire_transfer: "1-3 business days",
  bank_transfer: "1-3 business days",
  local_bank_transfer: "Same day",
  card: "3-5 business days",
  credit_card: "3-5 business days",
  debit_card: "3-5 business days",
  paypal: "Instant - 24 hrs",
  skrill: "Instant - 24 hrs",
  netteller: "Instant - 24 hrs",
  cryptocurrency: "15-60 minutes",
  crypto: "15-60 minutes",
  alchemy_pay: "15-60 minutes",
  ibeepay: "1-3 business days",
};

const getGatewayEta = (gateway) => {
  // 出金优先用出金处理时长，没配再退回通用 processingTime
  const processingTime =
    gateway?.withdrawalProcessingTime || gateway?.processingTime;
  if (typeof processingTime === "string" && processingTime.trim()) {
    return processingTime.trim();
  }

  const key = (gateway?.gatewayKey || "").toLowerCase();
  if (gatewayEtaMap[key]) return gatewayEtaMap[key];

  const name = (gateway?.gatewayName || "").toLowerCase();
  if (name.includes("wire")) return "1-3 business days";
  if (name.includes("bank")) return "Same day";
  if (name.includes("card")) return "3-5 business days";
  if (
    name.includes("paypal") ||
    name.includes("skrill") ||
    name.includes("neteller")
  )
    return "Instant - 24 hrs";
  if (name.includes("crypto")) return "15-60 minutes";
  return "Processing time varies";
};

const normalizeGatewayAssetCodes = (items) => {
  if (!Array.isArray(items)) return [];

  return items
    .map((item) => {
      if (typeof item === "object" && item !== null) {
        return (
          item.shortCode ||
          item.code ||
          item.symbol ||
          item.currency ||
          item.name ||
          item.methodName ||
          ""
        );
      }

      return String(item || "").trim();
    })
    .map((item) => String(item || "").trim())
    .filter(Boolean);
};

const getGatewaySupportedAssets = (gateway) => {
  if (!gateway || typeof gateway !== "object") return "";

  const fiatCurrencies = normalizeGatewayAssetCodes(
    gateway.supportedFiatCurrencies,
  );
  const cryptoCurrencies = normalizeGatewayAssetCodes(
    gateway.supportedCryptoCurrencies,
  );

  if (fiatCurrencies.length) {
    return `${t("transFiat", "Fiat")}: ${fiatCurrencies.join(", ")}`;
  }

  if (cryptoCurrencies.length) {
    return `${t("transCrypto", "Crypto")}: ${cryptoCurrencies.join(", ")}`;
  }

  return "";
};

const getIconType = (item) => {
  const key = String(
    item.type || item.methodType || selectedGateway.value?.gatewayKey || "",
  ).toLowerCase();
  const name = String(
    item.name || item.label || selectedGateway.value?.gatewayName || "",
  ).toLowerCase();

  if (
    key.includes("card") ||
    name.includes("visa") ||
    name.includes("mastercard") ||
    name.includes("card")
  )
    return "card";
  if (
    key.includes("paypal") ||
    key.includes("skrill") ||
    key.includes("neteller") ||
    name.includes("paypal") ||
    name.includes("skrill") ||
    name.includes("neteller")
  )
    return "ewallet";
  if (
    key.includes("crypto") ||
    key.includes("wallet") ||
    name.includes("wallet") ||
    name.includes("btc") ||
    name.includes("eth") ||
    name.includes("usdt")
  )
    return "crypto";
  return "bank";
};

const selectGateway = (gatewayKey) => {
  emit("update:modelValue", gatewayKey);
  isOpen.value = false;
};

const createSubmissionForNewAddress = async () => {
  const templateId = resolvedTemplateId.value;

  if (!templateId) {
    alert("Missing templateId for KYC submission.");
    return;
  }

  creatingKycSubmission.value = true;

  try {
    const response =
      await withdrawalSubmissionService.createSubmission(templateId);
    const submissionId =
      response?.data?.submissionId ||
      response?.data?.id ||
      response?.submissionId ||
      response?.id;
    if (!submissionId) {
      throw new Error("Missing submissionId from create submission response.");
    }
    inlineKycSubmitted.value = false;
    inlineKycSubmissionId.value = submissionId;
    selectedAddressId.value = NEW_ADDRESS_ID;
  } catch (err) {
    alert(
      `Failed to create KYC submission: ${err.response?.data?.message || err.message}`,
    );
  } finally {
    creatingKycSubmission.value = false;
  }
};

const handleContinue = async () => {
  if (!selectedAddressId.value) return;
  if (selectedAddressId.value === NEW_ADDRESS_ID) {
    await createSubmissionForNewAddress();
    return;
  }

  if (selectedAddressOption.value?.submissionStatus === "approved") {
    emit("proceed-step-two", selectedAddressOption.value);
    return;
  }

  if (selectedAddressOption.value?.submissionId) {
    inlineKycSubmitted.value = false;
    inlineKycSubmissionId.value = selectedAddressOption.value.submissionId;
  }
};

// 客户软删除自己某条已保存地址：确认后调用后端隐藏，再刷新列表
const handleHideAddress = async (address) => {
  if (!address?.submissionId) return;
  const confirmed = confirm(
    t(
      "transConfirmRemoveAddress",
      "Remove this withdrawal address from your list?",
    ),
  );
  if (!confirmed) return;

  try {
    await withdrawalSubmissionService.hideAddress(address.submissionId);
    if (selectedAddressId.value === address.id) {
      selectedAddressId.value = "";
    }
    emit("refresh-submissions");
    alert(t("transRemoveAddressSuccess", "Address removed"));
  } catch (err) {
    alert(
      err.response?.data?.message ||
        err.message ||
        t("transRemoveAddressFailed", "Failed to remove address"),
    );
  }
};

const handleInlineKycClose = () => {
  inlineKycSubmissionId.value = null;
  inlineKycSubmitted.value = false;
  selectedAddressId.value = "";
};

const handleInlineKycSubmitted = () => {
  inlineKycSubmissionId.value = null;
  emit("refresh-submissions");
  inlineKycSubmitted.value = true;
  showWithdrawalSuccessModal.value = true;
};

const closeInlineKyc = () => {
  inlineKycSubmissionId.value = null;
  inlineKycSubmitted.value = false;
  selectedAddressId.value = "";
};

const handleClickOutside = (event) => {
  if (!dropdownRef.value?.contains(event.target)) {
    isOpen.value = false;
  }
};

watch(
  () => props.modelValue,
  () => {
    selectedAddressId.value = "";
    inlineKycSubmissionId.value = null;
    inlineKycSubmitted.value = false;
    showWithdrawalSuccessModal.value = false;
    hasAutoProceededToStepTwo.value = false;
  },
);

watch(
  [
    () => props.modelValue,
    () => props.templateLoading,
    () => props.templateError,
    isPrewithdrawEnabled,
    resolvedTemplateMeta,
  ],
  ([gatewayKey, templateLoading, templateError, prewithdrawEnabled]) => {
    if (
      !gatewayKey ||
      templateLoading ||
      templateError ||
      prewithdrawEnabled ||
      hasAutoProceededToStepTwo.value
    ) {
      return;
    }

    hasAutoProceededToStepTwo.value = true;
    emit("proceed-step-two", {
      id: `gateway-${gatewayKey}`,
      name:
        resolvedTemplateMeta.value?.gatewayName ||
        selectedGateway.value?.gatewayName ||
        t("transWithdraw", "Withdrawal"),
      value: "",
      iconClass:
        resolvedTemplateMeta.value?.iconClass ||
        selectedGateway.value?.iconClass ||
        "fas fa-coins",
      submissionStatus: "approved",
      directWithdrawal: true,
    });
  },
);

onMounted(() => {
  document.addEventListener("click", handleClickOutside, true);
});

onBeforeUnmount(() => {
  document.removeEventListener("click", handleClickOutside, true);
});
</script>

<style scoped>
.pre-withdrawal-kyc {
  display: flex;
  flex-direction: column;
  gap: 28px;
}

.withdraw-stepper {
  display: grid;
  grid-template-columns: auto 1fr auto 1fr auto;
  align-items: center;
  gap: 16px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 18px;
  padding: 20px 24px;
  box-shadow: 0 16px 40px rgba(15, 23, 42, 0.06);
}

.step-item {
  display: flex;
  align-items: center;
  gap: 12px;
  color: var(--color-faint);
  font-weight: 600;
  white-space: nowrap;
}

.step-item.active {
  color: var(--color-brand);
}

.step-index {
  width: 34px;
  height: 34px;
  border-radius: 999px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--color-border);
  background: var(--color-surface);
}

.step-item.active .step-index {
  border-color: var(--color-brand);
  background: var(--color-brand-solid);
  color: #fff;
}

.step-line {
  height: 1px;
  background: var(--color-surface-muted);
}

.withdraw-card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 18px;
  overflow: visible;
  box-shadow: 0 18px 45px rgba(15, 23, 42, 0.06);
}

.withdraw-card-header {
  padding: 18px 22px;
  border-bottom: 1px solid var(--color-border);
}

.withdraw-card-header h3 {
  margin: 0;
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 18px;
  color: var(--color-ink);
}

.withdraw-card-header i {
  color: var(--color-brand);
}

.withdraw-card-body {
  padding: 22px;
  position: relative;
  z-index: 1;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.currency-section {
  margin-top: 18px;
}

.crypto-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 12px;
  margin-top: 14px;
}

.crypto-card {
  border: 1px solid var(--color-border);
  border-radius: 14px;
  background: var(--color-surface);
  padding: 14px;
  text-align: left;
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    transform 0.2s ease;
}

.crypto-card.selected {
  border-color: var(--color-brand);
  box-shadow: 0 12px 24px rgba(var(--color-brand-rgb), 0.12);
  transform: translateY(-1px);
}

.crypto-card-icon {
  width: 38px;
  height: 38px;
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-brand-soft);
  color: var(--color-brand);
  margin-bottom: 10px;
}

.crypto-card-name {
  color: var(--color-ink);
  font-weight: 700;
}

.crypto-card-code {
  margin-top: 4px;
  color: var(--color-muted);
  font-size: 13px;
}

.form-label {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--color-text);
  font-weight: 600;
}

.form-label i {
  color: var(--color-brand);
}

.methods-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 48px 0;
  color: var(--color-brand);
  font-weight: 600;
}

.methods-loading-icon {
  font-size: 20px;
}

.asset-filter-group {
  margin-bottom: 18px;
}

.asset-filter {
  display: flex;
  gap: 10px;
}

.asset-filter-btn {
  flex: 1;
  min-height: 48px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  color: var(--color-text);
  font-size: 14px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  cursor: pointer;
}

.asset-filter-btn i {
  color: var(--color-faint);
}

.asset-filter-btn.active {
  border-color: var(--color-brand);
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.asset-filter-btn.active i {
  color: var(--color-brand);
}

.select-shell {
  position: relative;
  z-index: 20;
}

.select-trigger {
  width: 100%;
  min-height: 56px;
  border: 1px solid var(--color-border);
  border-radius: 14px;
  background: var(--color-surface);
  padding: 0 16px;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  cursor: pointer;
}

.select-trigger.empty {
  border-color: var(--color-border);
}

.trigger-main,
.option-main,
.selected-method-main {
  display: flex;
  align-items: center;
  gap: 12px;
}

.trigger-icon,
.option-icon,
.selected-method-icon {
  width: 36px;
  height: 36px;
  border-radius: var(--radius-md);
  background: var(--color-brand-soft);
  color: var(--color-brand);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.trigger-text,
.option-text {
  display: flex;
  align-items: baseline;
  gap: 12px;
  flex-wrap: wrap;
  text-align: left;
}

.trigger-title,
.option-title,
.selected-method-name {
  font-size: 15px;
  font-weight: 700;
  color: var(--color-ink);
}

.trigger-subtitle,
.option-subtitle,
.selected-method-eta {
  font-size: 14px;
  color: var(--color-muted);
  font-weight: 600;
}

.option-meta,
.selected-method-supported {
  flex-basis: 100%;
  font-size: 12px;
  color: var(--color-muted);
  font-weight: 500;
}

.trigger-chevron {
  color: var(--color-faint);
}

.select-dropdown {
  position: absolute;
  top: calc(100% + 8px);
  left: 0;
  right: 0;
  z-index: 50;
  max-height: min(360px, 50vh);
  overflow-x: hidden;
  overflow-y: auto;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 14px;
  box-shadow: 0 18px 35px rgba(15, 23, 42, 0.12);
}

.dropdown-option {
  width: 100%;
  border: 0;
  border-top: 1px solid var(--color-surface-muted);
  background: var(--color-surface);
  padding: 14px 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  cursor: pointer;
  text-align: left;
}

.select-dropdown > .dropdown-option:first-child {
  border-top: 0;
}

.dropdown-group {
  border-top: 1px solid var(--color-border);
}

.dropdown-group .dropdown-option {
  border-top: 1px solid var(--color-surface-muted);
}

.dropdown-group-label {
  position: sticky;
  top: 0;
  z-index: 1;
  padding: 10px 16px;
  background: var(--color-brand-soft);
  border-bottom: 1px solid var(--color-border);
  color: var(--color-ink);
  font-size: 13px;
  font-weight: 700;
}

.dropdown-option:hover,
.dropdown-option.selected {
  background: var(--color-surface-soft);
}

.option-check {
  color: var(--color-brand);
}

.field-note {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: var(--color-brand);
}

.field-note.muted {
  color: var(--color-faint);
}

.selected-method-card {
  margin-top: 10px;
  background: var(--color-brand-soft);
  border-radius: var(--radius-md);
  padding: 10px 14px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.address-section {
  margin-top: 24px;
  border-top: 1px solid var(--color-border);
  padding-top: 20px;
}

.address-section-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.address-section-title i {
  color: var(--color-brand);
}

.address-info-box {
  margin-bottom: 14px;
  padding: 14px 16px;
  background: var(--color-brand-soft);
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border);
  display: flex;
  align-items: flex-start;
  gap: 10px;
  color: var(--color-text);
  font-size: 14px;
  line-height: 1.6;
}

.address-info-box i {
  color: var(--color-brand);
  margin-top: 2px;
}

.template-state {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 16px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface-soft);
  color: var(--color-text);
  margin-bottom: 12px;
}

.template-state.error {
  color: var(--color-danger);
  background: var(--color-danger-soft);
  border-color: var(--color-danger-soft);
}

.address-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.address-empty-note {
  margin-top: 12px;
  font-size: 12px;
  color: var(--color-muted);
}

.address-card {
  width: 100%;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 16px 18px;
  display: flex;
  align-items: center;
  gap: 14px;
  cursor: pointer;
  transition: all 0.2s ease;
  background: var(--color-surface-soft);
  text-align: left;
}

.address-card:hover {
  border-color: var(--color-brand);
  background: var(--color-brand-soft);
}

.address-card.selected {
  border-color: var(--color-brand);
  background: var(--color-brand-soft);
}

.address-card.new-address {
  border-style: dashed;
  border-color: var(--color-border-strong);
}

.address-delete-btn {
  flex-shrink: 0;
  width: 30px;
  height: 30px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-md);
  color: var(--color-faint);
  cursor: pointer;
  transition: all 0.2s ease;
}

.address-delete-btn:hover {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.address-icon.bank {
  background: var(--color-info-soft);
  color: var(--color-brand);
}

.address-icon.card {
  background: var(--color-purple-soft);
  color: var(--color-purple);
}

.address-icon.ewallet {
  background: var(--color-warning-soft);
  color: var(--color-danger);
}

.address-icon.crypto {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.address-icon.new {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.address-details {
  flex: 1;
  min-width: 0;
}

.address-name {
  font-size: 14px;
  font-weight: 700;
  color: var(--color-ink);
  margin-bottom: 2px;
}

.address-value {
  font-size: 12px;
  color: var(--color-muted);
  font-family: monospace;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 280px;
}

.address-value-multi {
  display: flex;
  align-items: center;
  gap: 6px;
  max-width: 100%;
}

.address-value-label {
  flex: 0 0 auto;
  font-family: inherit;
  font-weight: 600;
  color: var(--color-muted);
}

.address-value-text {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.address-value-plain {
  font-family: inherit;
}

.address-badge {
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  flex-shrink: 0;
}

.address-badge.verified {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.address-badge.draft {
  background: var(--color-purple-soft);
  color: var(--color-purple);
}

.address-badge.incomplete {
  background: var(--color-warning-soft);
  color: var(--color-danger);
}

.address-badge.submitted {
  background: var(--color-info-soft);
  color: var(--color-brand);
}

.address-badge.resubmit-required {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.address-badge.rejected {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.address-badge.expired {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.address-badge.new-badge {
  background: var(--color-brand-soft);
  color: var(--color-brand-strong);
}

.address-card.disabled {
  opacity: 0.62;
  cursor: not-allowed;
}

.address-radio {
  width: 20px;
  height: 20px;
  border: 1px solid var(--color-border-strong);
  border-radius: 50%;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.address-card.selected .address-radio {
  border-color: var(--color-brand);
  background: var(--color-brand-solid);
}

.address-card.selected .address-radio::after {
  content: "";
  width: 8px;
  height: 8px;
  background: var(--color-surface);
  border-radius: 50%;
}

.step-actions {
  margin-top: 24px;
  display: flex;
  justify-content: center;
}

.continue-btn {
  border: none;
  border-radius: var(--radius-md);
  background: linear-gradient(
    135deg,
    var(--color-brand-solid),
    var(--color-purple-solid)
  );
  color: #fff;
  padding: 12px 24px;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 10px 24px rgba(139, 156, 255, 0.28);
}

.continue-btn:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 14px 28px rgba(139, 156, 255, 0.34);
}

.continue-btn:disabled {
  opacity: 0.75;
  cursor: not-allowed;
  background: linear-gradient(
    135deg,
    var(--color-brand-soft),
    var(--color-purple-soft)
  );
  box-shadow: none;
}

.inline-addr-form {
  margin-top: 16px;
  border: 1px solid var(--color-border);
  border-radius: 14px;
  overflow: hidden;
  background: var(--color-surface);
}

.inline-addr-hdr {
  background: linear-gradient(
    135deg,
    var(--color-brand-solid) 0%,
    var(--color-purple-solid) 100%
  );
  color: #fff;
  padding: 14px 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.inline-addr-hdr-title {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 15px;
  font-weight: 700;
}

.inline-cancel-btn {
  border: 1px solid rgba(255, 255, 255, 0.35);
  background: rgba(255, 255, 255, 0.18);
  color: #fff;
  border-radius: var(--radius-md);
  padding: 7px 12px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.inline-addr-body {
  padding: 18px 20px 20px;
}

.kyc-info-banner {
  background: var(--color-brand-soft);
  border-left: 1px solid var(--color-brand);
  border-radius: var(--radius-md);
  padding: 14px 18px;
  margin-bottom: 20px;
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.kyc-info-banner i {
  color: var(--color-brand);
  font-size: 16px;
  margin-top: 2px;
  flex-shrink: 0;
}

.kyc-info-banner p {
  font-size: 13px;
  color: var(--color-text);
  line-height: 1.6;
  margin: 0;
}

.kyc-info-banner strong {
  color: var(--color-ink);
}

.inline-section-divider {
  margin: 20px 0 16px;
  border: 0;
  border-top: 1px solid var(--color-border);
}

.inline-kyc-content {
  margin-top: 18px;
}

.inline-kyc-content :deep(.kyc-verification-wrapper) {
  background: transparent;
}

.inline-kyc-content :deep(.progress-section) {
  margin-top: 0;
}

.inline-kyc-waiting {
  margin-top: 18px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-xl);
  background: linear-gradient(
    180deg,
    var(--color-surface-soft) 0%,
    var(--color-brand-soft) 100%
  );
  padding: 28px 24px;
  text-align: center;
}

.waiting-icon {
  width: 60px;
  height: 60px;
  margin: 0 auto 14px;
  border-radius: 999px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-brand-soft);
  color: var(--color-brand);
  font-size: 24px;
}

.inline-kyc-waiting h4 {
  margin: 0 0 8px;
  font-size: 22px;
  color: var(--color-brand);
}

.inline-kyc-waiting p {
  margin: 0;
  color: var(--color-muted);
  line-height: 1.6;
}

@media (max-width: 768px) {
  .withdraw-stepper {
    grid-template-columns: auto 1fr auto 1fr auto;
    gap: 8px;
    padding: 14px;
  }

  .step-item {
    justify-content: center;
  }

  .step-label {
    display: none;
  }

  .trigger-text,
  .option-text,
  .selected-method-card {
    display: block;
  }

  .trigger-subtitle,
  .option-subtitle,
  .selected-method-eta {
    margin-top: 4px;
  }

  .address-card {
    display: grid;
    grid-template-columns: auto auto minmax(0, 1fr);
    align-items: flex-start;
    gap: 12px;
    padding: 14px;
  }

  .address-radio,
  .address-icon {
    margin-top: 2px;
  }

  .address-details {
    width: 100%;
  }

  .address-name {
    padding-right: 0;
    margin-bottom: 8px;
  }

  .address-value {
    max-width: none;
    white-space: normal;
    overflow: visible;
    text-overflow: unset;
    word-break: break-word;
  }

  .address-value-multi {
    align-items: flex-start;
    flex-wrap: wrap;
    row-gap: 2px;
  }

  .address-value-text {
    white-space: normal;
    overflow: visible;
    text-overflow: unset;
    word-break: break-word;
  }

  .address-badge {
    grid-column: 3;
    justify-self: flex-start;
    margin-top: 2px;
  }

  .inline-addr-hdr {
    align-items: flex-start;
    flex-direction: column;
    gap: 8px;
  }

  .inline-cancel-btn {
    order: -1;
    align-self: flex-start;
    padding: 0;
    border: 0;
    border-radius: 0;
    background: transparent;
    color: rgba(255, 255, 255, 0.9);
    font-size: 14px;
    font-weight: 700;
    gap: 6px;
  }
}
</style>
