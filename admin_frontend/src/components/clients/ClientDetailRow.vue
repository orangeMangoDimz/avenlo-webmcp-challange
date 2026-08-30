<template>
  <div class="client-detail-row">
    <!-- 信息卡片网格 -->
    <div class="info-cards-grid">
      <!-- Personal Information Card -->
      <div class="detail-card">
        <div class="detail-card-header">
          <div class="detail-card-icon">
            <i class="fas fa-user"></i>
          </div>
          <div class="section-header">
            <h3 class="detail-card-title">
              {{ t("leadDetail_sectionPersonal") }}
            </h3>
            <button
              v-if="canEdit"
              class="btn-save"
              :class="{ active: hasChanges, disabled: !hasChanges }"
              @click="saveAllInfo"
              :disabled="!hasChanges"
            >
              <i class="fas fa-save"></i> {{ t("leadDetail_save") }}
            </button>
          </div>
        </div>

        <div class="detail-field">
          <span class="detail-label">{{ t("leadDetail_firstName") }}:</span>
          <div class="detail-value-wrapper">
            <input
              type="text"
              class="detail-value editable"
              v-model="editableClient.firstName"
              :disabled="!canEdit"
              @input="checkChanges"
            />
          </div>
        </div>

        <div class="detail-field">
          <span class="detail-label">{{ t("leadDetail_lastName") }}:</span>
          <div class="detail-value-wrapper">
            <input
              type="text"
              class="detail-value editable"
              v-model="editableClient.lastName"
              :disabled="!canEdit"
              @input="checkChanges"
            />
          </div>
        </div>

        <div class="detail-field">
          <span class="detail-label">{{ t("leadDetail_email") }}:</span>
          <div class="detail-value-wrapper">
            <input
              type="email"
              class="detail-value editable"
              v-model="editableClient.email"
              :disabled="!canEdit"
              @input="checkChanges"
            />
          </div>
        </div>

        <div class="detail-field">
          <span class="detail-label">{{ t("leadDetail_password") }}</span>
          <div
            class="detail-value-wrapper"
            style="display: flex; gap: 8px; flex-wrap: wrap"
          >
            <button
              v-if="canEdit"
              class="detail-action-button"
              :disabled="sendingPasswordReset || !editableClient.email"
              @click="sendResetEmail"
            >
              <i v-if="sendingPasswordReset" class="fas fa-spinner fa-spin"></i>
              <i v-else class="fas fa-envelope"></i>
              {{
                sendingPasswordReset
                  ? t("leadDetail_sending")
                  : t("leadDetail_sendResetEmail")
              }}
            </button>
            <button
              v-if="canEdit"
              class="detail-action-button"
              @click="showResetPasswordModal = true"
            >
              <i class="fas fa-key"></i>
              {{ t("resetPassword", "Reset Password") }}
            </button>
          </div>
        </div>

        <ResetPasswordModal
          v-model="showResetPasswordModal"
          :client-id="client.id"
          :client-name="
            (editableClient.firstName || '') +
            ' ' +
            (editableClient.lastName || '')
          "
        />
        <div class="detail-field">
          <span class="detail-label">{{ t("leadDetail_phone") }}:</span>
          <div class="detail-value-wrapper">
            <input
              type="tel"
              class="detail-value editable"
              v-model="editableClient.phone"
              :disabled="!canEdit"
              @input="checkChanges"
            />
          </div>
        </div>

        <div class="detail-field">
          <span class="detail-label">{{ t("leadDetail_country") }}:</span>
          <div class="detail-value-wrapper">
            <select
              class="detail-value editable"
              v-model="editableClient.country"
              :disabled="!canEdit"
              @change="checkChanges"
            >
              <option value="">{{ t("clientsList_selectCountry") }}</option>
              <option
                v-for="country in countries"
                :key="country.code"
                :value="country.code"
              >
                {{ country.name }}
              </option>
            </select>
          </div>
        </div>

        <div class="detail-field">
          <span class="detail-label">{{ t("leadDetail_statusLabel") }}</span>
          <div class="detail-value-wrapper">
            <select
              class="detail-value editable"
              v-model="editableClient.status"
              :disabled="!canEdit"
              @change="checkChanges"
            >
              <option value="active">
                {{ t("leadDetail_status_active") }}
              </option>
              <option value="inactive">
                {{ t("leadDetail_status_inactive") }}
              </option>
              <option value="suspended">
                {{ t("leadDetail_status_suspended") }}
              </option>
              <option value="pending_verification">
                {{ t("leadDetail_status_pending_verification") }}
              </option>
            </select>
          </div>
        </div>

        <div class="detail-field">
          <span class="detail-label">{{ t("leadDetail_emailVerified") }}</span>
          <div class="detail-value-wrapper">
            <select
              class="detail-value editable"
              v-model="editableClient.emailVerified"
              :disabled="!canEdit"
              @change="checkChanges"
            >
              <option :value="true">{{ t("leadDetail_yes") }}</option>
              <option :value="false">{{ t("leadDetail_no") }}</option>
            </select>
          </div>
        </div>

        <div class="detail-field">
          <span class="detail-label">{{
            t("leadDetail_registrationDate")
          }}</span>
          <span class="detail-value">{{ formatDate(client.createdAt) }}</span>
        </div>

        <div class="detail-field">
          <span class="detail-label">{{ t("leadDetail_lastLogin") }}</span>
          <span class="detail-value">{{ formatDate(client.lastLoginAt) }}</span>
        </div>
      </div>

      <!-- Activity Statistics Card -->
      <div class="detail-card">
        <div class="detail-card-header">
          <div class="detail-card-icon">
            <i class="fas fa-chart-bar"></i>
          </div>
          <div class="section-header">
            <h3 class="detail-card-title">
              {{ t("leadDetail_sectionActivity") }}
            </h3>
            <button
              v-if="client.kycStatus !== 'approved'"
              class="btn-approve-kyc"
              :disabled="approvingKyc"
              @click="approveClientKyc"
            >
              <i
                :class="
                  approvingKyc ? 'fas fa-spinner fa-spin' : 'fas fa-user-check'
                "
              ></i>
              {{
                approvingKyc
                  ? t("clientDetail_btnApprovingKyc", "Approving...")
                  : t("clientDetail_btnApproveKyc", "Approve KYC")
              }}
            </button>
          </div>
        </div>

        <div class="detail-field">
          <span class="detail-label">{{ t("leadDetail_kycStatus") }}</span>
          <span class="detail-value">
            <span class="kyc-badge" :class="client.kycStatus">
              {{ formatKycStatus(client.kycStatus) }}
            </span>
          </span>
        </div>

        <div class="detail-field">
          <span class="detail-label">{{
            t("clientDetail_kycVerifiedAtLabel")
          }}</span>
          <span class="detail-value">{{
            formatDate(client.kycVerifiedAt)
          }}</span>
        </div>

        <div class="detail-field">
          <span class="detail-label">{{
            t("clientDetail_kycTemplateName")
          }}</span>
          <span class="detail-value">{{
            client.kycTemplateName || t("leads_na")
          }}</span>
        </div>

        <div class="detail-field">
          <span class="detail-label">{{ t("clientDetail_verifiedBy") }}</span>
          <span class="detail-value">{{
            client.kycVerifiedBy || t("leads_na")
          }}</span>
        </div>

        <div class="detail-field">
          <span class="detail-label">{{ t("leadDetail_totalLogins") }}</span>
          <span class="detail-value">{{ client.loginCount || 0 }}</span>
        </div>

        <div class="detail-field">
          <span class="detail-label">{{
            t("leadDetail_documentsUploaded")
          }}</span>
          <span class="detail-value">{{
            client.documentsCount || client.kycDocumentsCount || 0
          }}</span>
        </div>

        <div class="detail-field">
          <span class="detail-label">{{
            t("clientDetail_formsSubmitted")
          }}</span>
          <span class="detail-value">{{ client.formsCount || 0 }}</span>
        </div>

        <div class="detail-field">
          <span class="detail-label">{{
            t("clientDetail_supportTickets")
          }}</span>
          <span class="detail-value">{{ client.ticketsCount || 0 }}</span>
        </div>

        <div class="detail-field">
          <span class="detail-label">{{ t("leadDetail_lastActivity") }}</span>
          <span class="detail-value">{{
            formatDate(client.lastActivityAt || client.lastLoginAt)
          }}</span>
        </div>
      </div>
    </div>

    <!-- Trading Information Section -->
    <div class="detail-card trading-section">
      <div class="detail-card-header">
        <div class="detail-card-icon">
          <i class="fas fa-chart-line"></i>
        </div>
        <div class="trading-section-header">
          <h3 class="detail-card-title">
            {{ t("clientDetail_sectionTrading") }}
          </h3>
          <button
            v-if="canCreateTradingAccount"
            type="button"
            class="btn-create-trading-account"
            :disabled="tradingLoading"
            @click="openCreateTradingAccountModal"
          >
            <i class="fas fa-plus"></i>
            {{
              t("clientDetail_createTradingAccount", "Create Trading Account")
            }}
          </button>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="tradingLoading" class="loading-state">
        <i class="fas fa-spinner fa-spin"></i>
        <p>{{ t("clientDetail_loadingTrading") }}</p>
      </div>

      <!-- Trading Info Content -->
      <div v-else-if="hasTradingAccounts" class="trading-info-content">
        <div class="trading-platform-sections">
          <div
            v-for="section in tradingPlatformSections"
            :key="section.platformKey"
            class="trading-platform-section"
          >
            <div class="trading-platform-header">
              <h4 class="info-group-title">
                <i class="fas fa-wallet"></i>
                {{ section.platformName }}
              </h4>
              <div class="trading-platform-meta">
                <span
                  class="platform-badge"
                  :class="`platform-${section.platformKey}`"
                >
                  {{ section.platformBadge }}
                </span>
                <span class="platform-count">{{
                  tParams(
                    "clientDetail_tradingAccountCount",
                    "{n} account(s)",
                    { n: section.accounts.length },
                  )
                }}</span>
              </div>
            </div>

            <div class="trading-account-grid">
              <div
                v-for="account in section.accounts"
                :key="`${section.platformKey}-${account.accountId || account.accountNumber || 'account'}`"
                class="trading-account-card"
              >
                <div class="trading-account-card-header">
                  <div class="trading-account-heading">
                    <div class="trading-account-title">
                      {{
                        account.accountNumber ||
                        account.accountId ||
                        t("clientDetail_tradingAccountTitle")
                      }}
                    </div>
                    <div class="trading-account-subtitle">
                      {{
                        account.name ||
                        account.login ||
                        t("clientDetail_unnamedAccount")
                      }}
                    </div>
                  </div>
                  <span
                    v-if="account.status"
                    class="status-badge"
                    :class="getAccountStatusClass(account.status)"
                  >
                    {{ getAccountStatusText(account.status) }}
                  </span>
                </div>

                <div class="info-grid">
                  <div
                    v-for="item in account.items"
                    :key="`${section.platformKey}-${account.accountId || account.accountNumber}-${item.label}`"
                    class="info-item"
                  >
                    <span class="info-label">{{ item.label }}:</span>
                    <span class="info-value">{{ item.value }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- No Trading Account -->
      <div v-else class="empty-state">
        <i class="fas fa-chart-line"></i>
        <p>{{ t("clientDetail_noTradingAccount") }}</p>
      </div>
    </div>

    <CreateTradingAccountModal
      v-model="showCreateTradingAccountModal"
      :client-id="client.id"
      @created="handleTradingAccountCreated"
    />

    <!-- Documents Section -->
    <div class="detail-card documents-section">
      <div class="detail-card-header">
        <div class="detail-card-icon">
          <i class="fas fa-file-signature"></i>
        </div>
        <h3 class="detail-card-title">
          {{ t("leadDetail_sectionDocuments") }}
        </h3>
      </div>

      <!-- Loading State -->
      <div v-if="documentsLoading" class="loading-state">
        <i class="fas fa-spinner fa-spin"></i>
        <p>{{ t("clientDetail_loadingDocuments") }}</p>
      </div>

      <!-- Documents Grid -->
      <div v-else class="documents-grid">
        <div
          v-for="doc in documents"
          :key="doc.id"
          class="document-card"
          @click="viewDocument(doc)"
        >
          <div class="document-card-header">
            <div class="document-icon">
              <i :class="getDocumentIcon(doc.documentType)"></i>
            </div>
            <div class="document-info">
              <div class="document-title">{{ doc.title }}</div>
              <div class="document-date">
                <i class="fas fa-calendar"></i>
                {{
                  tParams("leadDetail_signedOn", "Signed on {date}", {
                    date: formatDate(doc.signedAt),
                  })
                }}
              </div>
            </div>
          </div>

          <div class="document-meta">
            <div class="document-meta-item">
              <i class="fas fa-file-pdf"></i>
              <span>{{ t("leadDetail_pdf") }}</span>
            </div>
            <div class="document-meta-item">
              <i class="fas fa-tag"></i>
              <span>{{ getDocumentSourceLabel(doc) }}</span>
            </div>
            <div class="document-status signed">
              <i class="fas fa-check-circle"></i>
              {{ t("leadDetail_signed") }}
            </div>
          </div>

          <div class="document-actions">
            <button class="btn-doc btn-view" @click.stop="viewDocument(doc)">
              <i class="fas fa-eye"></i> {{ t("leadDetail_view") }}
            </button>
            <button
              class="btn-doc btn-download"
              @click.stop="downloadDocument(doc)"
            >
              <i class="fas fa-download"></i> {{ t("leadDetail_download") }}
            </button>
          </div>
        </div>

        <!-- Empty State -->
        <div v-if="documents.length === 0" class="empty-state">
          <i class="fas fa-file-alt"></i>
          <p>{{ t("leadDetail_noDocuments") }}</p>
        </div>
      </div>
    </div>

    <!-- Sales Assignment Section -->
    <ClientSalesAssignment
      :client="client"
      :can-edit="canEdit"
      :log-sub-module-key="logSubModuleKey"
      @assigned="applySalesAssignment"
    />

    <!-- Document Viewer Modal -->
    <div v-if="showDocumentModal" class="modal" @click="closeDocumentModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h2>
            <i class="fas fa-file-alt"></i>
            {{
              currentDocument
                ? currentDocument.title
                : t("leadDetail_docPreview")
            }}
          </h2>
          <span class="close" @click="closeDocumentModal">&times;</span>
        </div>

        <div class="modal-body">
          <div class="document-preview">
            <h3>{{ currentDocument ? currentDocument.title : "" }}</h3>
            <div
              class="document-preview-content"
              v-html="currentDocument ? currentDocument.content : ''"
            ></div>
          </div>

          <!-- Signature Section -->
          <div class="document-signature">
            <h4>
              <i class="fas fa-signature"></i>
              {{ t("leadDetail_digitalSignature") }}
            </h4>
            <div class="signature-info-row">
              <div class="signature-field">
                <label>{{ t("leadDetail_fullName") }}</label>
                <div class="signature-value">
                  {{ client.firstName }} {{ client.lastName }}
                </div>
              </div>
              <div class="signature-field">
                <label>{{ t("leadDetail_labelEmail") }}</label>
                <div class="signature-value">{{ client.email }}</div>
              </div>
              <div class="signature-field">
                <label>{{ t("leadDetail_clientId") }}</label>
                <div class="signature-value">{{ client.id }}</div>
              </div>
              <div class="signature-field">
                <label>{{ t("leadDetail_dateSigned") }}</label>
                <div class="signature-value">
                  {{
                    currentDocument
                      ? formatDate(currentDocument.signedAt)
                      : t("leads_na")
                  }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button
            class="btn-modal btn-modal-secondary"
            @click="closeDocumentModal"
          >
            <i class="fas fa-times"></i> {{ t("leadDetail_close") }}
          </button>
          <button
            class="btn-modal btn-modal-primary"
            @click="downloadCurrentDocument"
          >
            <i class="fas fa-download"></i> {{ t("leadDetail_downloadPdf") }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, watch, onMounted } from "vue";
import { clientService } from "@/services/clientListService";
import ClientSalesAssignment from "@/components/clients/ClientSalesAssignment.vue";
import CreateTradingAccountModal from "@/components/client/CreateTradingAccountModal.vue";
import brandingApi from "@/services/brandingApi";
import ResetPasswordModal from "./ResetPasswordModal.vue";
import { useAuthStore } from "@/stores/auth";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { translateApiErrorMessage } from "@/i18n/adminI18nBridge";
import { getSubModuleKey } from "@/config/operationLogPages";

export default {
  name: "ClientDetailRow",
  components: {
    ResetPasswordModal,
    ClientSalesAssignment,
    CreateTradingAccountModal,
  },
  props: {
    client: {
      type: Object,
      required: true,
    },
    canEdit: {
      type: Boolean,
      default: true,
    },
    logSubModuleKey: {
      type: String,
      default: () => getSubModuleKey("page_clients_list"),
    },
  },
  emits: ["update", "delete"],
  setup(props, { emit }) {
    const { t, tParams, languageStore } = useAdminI18n();
    const authStore = useAuthStore();

    // 响应式数据
    const editableClient = ref({ ...props.client });
    const originalData = ref({});

    // Documents相关
    const documents = ref([]);
    const documentsLoading = ref(false);
    const showDocumentModal = ref(false);
    const currentDocument = ref(null);

    // Branding configuration（默认文案走语言包，加载后覆盖）
    const branding = ref({
      companyName: "",
      copyrightText: "",
    });

    // Trading信息相关
    const tradingInfo = ref(null);
    const tradingLoading = ref(false);
    const showCreateTradingAccountModal = ref(false);
    const platformNameMap = {
      financepro: "FinancePro",
      mt5: "MetaTrader 5",
      mt4: "MetaTrader 4",
    };

    const approvingKyc = ref(false);
    const sendingPasswordReset = ref(false);
    const showResetPasswordModal = ref(false);

    const applySalesAssignment = (assignment) => {
      if (!assignment) return;
      Object.assign(props.client, assignment);
    };
    // 国家列表（示例数据）
    const countries = ref([
      { code: "US", name: "United States" },
      { code: "UK", name: "United Kingdom" },
      { code: "CA", name: "Canada" },
      { code: "AU", name: "Australia" },
      { code: "DE", name: "Germany" },
      { code: "FR", name: "France" },
      { code: "JP", name: "Japan" },
      { code: "CN", name: "China" },
      { code: "SG", name: "Singapore" },
      { code: "HK", name: "Hong Kong" },
    ]);

    // 计算属性
    const hasChanges = computed(() => {
      const fields = [
        "firstName",
        "lastName",
        "email",
        "phone",
        "country",
        "status",
        "emailVerified",
      ];
      return fields.some(
        (field) => editableClient.value[field] !== originalData.value[field],
      );
    });

    // 初始化原始数据
    const initializeOriginalData = () => {
      originalData.value = {
        firstName: props.client.firstName,
        lastName: props.client.lastName,
        email: props.client.email,
        phone: props.client.phone,
        country: props.client.country,
        status: props.client.status,
        emailVerified: props.client.emailVerified,
      };
    };

    // 工具函数
    const formatKycStatus = (status) => {
      const map = {
        approved: "leads_kyc_approved",
        pending: "leads_kyc_pendingReview",
        rejected: "leads_kyc_rejected",
        not_started: "leads_kyc_notStarted",
      };
      const key = map[status];
      return key ? t(key) : status || t("leads_na");
    };

    const formatDate = (dateString) => {
      if (!dateString) return t("leads_na");
      const date = new Date(dateString);
      const loc = languageStore.currentLanguage === "zh" ? "zh-CN" : "en-US";
      return date.toLocaleDateString(loc, {
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
      });
    };

    const approveClientKyc = async () => {
      if (approvingKyc.value) return;

      const confirmed = window.confirm(
        t(
          "clientDetail_confirmApproveKyc",
          "Approve this client KYC now?\n\nIf the client has no KYC submission yet, the system will create one automatically and mark it as approved.",
        ),
      );

      if (!confirmed) return;

      approvingKyc.value = true;
      try {
        const response = await clientService.approveKyc(props.client.id);
        const payload = response?.data ?? response ?? {};
        const updatedClient = payload.client || payload.data?.client || null;
        const updatedSubmission =
          payload.submission || payload.data?.submission || null;

        props.client.kycStatus = updatedClient?.kycStatus || "approved";
        props.client.kycSubmissionId =
          updatedClient?.kycSubmissionId ??
          updatedSubmission?.id ??
          props.client.kycSubmissionId;
        props.client.kycVerifiedAt =
          updatedClient?.verifiedAt ||
          updatedSubmission?.reviewedAt ||
          new Date().toISOString();
        if (updatedSubmission?.templateId) {
          props.client.latestKycStatus = updatedSubmission.submissionStatus;
        }

        alert(t("clientDetail_approveKycOk", "KYC approved successfully!"));
      } catch (error) {
        const data = error?.response?.data ?? error;
        const rawMsg =
          data?.message || error?.message || t("common_unknownError");
        const message = translateApiErrorMessage(data?.errorCode, rawMsg);
        alert(
          tParams(
            "clientDetail_approveKycFailed",
            "Failed to approve KYC: {msg}",
            { msg: message },
          ),
        );
      } finally {
        approvingKyc.value = false;
      }
    };

    // 检查变更
    const checkChanges = () => {
      // 触发计算属性重新计算
    };

    // 保存所有更改
    const saveAllInfo = async () => {
      if (!hasChanges.value) return;

      try {
        const updateData = {
          firstName: editableClient.value.firstName,
          lastName: editableClient.value.lastName,
          email: editableClient.value.email,
          phone: editableClient.value.phone,
          country: editableClient.value.country,
          status: editableClient.value.status,
          emailVerified: editableClient.value.emailVerified,
          logSubModuleKey: props.logSubModuleKey,
        };

        emit("update", props.client.id, updateData);

        // 更新原始数据
        Object.assign(originalData.value, updateData);
      } catch (error) {
        console.error("Save info failed:", error);
      }
    };

    // 操作方法
    const sendEmail = () => {
      // TODO: 打开发送邮件模态框
      console.log("Send email to client:", props.client.id);
    };

    const sendResetEmail = () => {
      if (!editableClient.value.email) {
        alert(t("clientDetail_alertNoEmail"));
        return;
      }

      if (
        !confirm(
          tParams(
            "leadDetail_confirmResetEmail",
            "Send a password reset email to {email}?",
            { email: editableClient.value.email },
          ),
        )
      )
        return;

      sendingPasswordReset.value = true;
      clientService
        .sendPasswordReset(props.client.id, editableClient.value.email, {
          logSubModuleKey: props.logSubModuleKey,
        })
        .then(() => {
          alert(t("leadDetail_resetEmailOk"));
        })
        .catch((error) => {
          const data = error?.response?.data ?? error;
          const rawMsg =
            data?.message || error?.message || t("common_unknownError");
          const message = translateApiErrorMessage(data?.errorCode, rawMsg);
          alert(
            tParams(
              "leadDetail_resetEmailFailed",
              "Failed to send password reset email: {msg}",
              { msg: message },
            ),
          );
        })
        .finally(() => {
          sendingPasswordReset.value = false;
        });
    };

    const suspendAccount = () => {
      if (confirm(t("clientDetail_confirmSuspend"))) {
        editableClient.value.status = "suspended";
        saveAllInfo();
      }
    };

    const viewKycDetails = () => {
      // TODO: 打开KYC详情模态框或跳转到KYC详情页面
      console.log("View KYC details for client:", props.client.id);
    };

    const exportData = () => {
      // TODO: 实现导出客户数据
      console.log("Export data for client:", props.client.id);
    };

    const deleteClient = () => {
      if (confirm(t("clientDetail_confirmDelete"))) {
        emit("delete", props.client.id);
      }
    };

    // Documents相关方法
    const loadDocuments = async () => {
      try {
        documentsLoading.value = true;
        const response = await clientService.getDocuments(props.client.id);
        if (response.success) {
          documents.value = response.data;
        }
      } catch (error) {
        console.error("Failed to load documents:", error);
      } finally {
        documentsLoading.value = false;
      }
    };

    const getDocumentIcon = (type) => {
      const iconMap = {
        terms_of_service: "fas fa-file-contract",
        privacy_policy: "fas fa-shield-alt",
        risk_disclosure: "fas fa-exclamation-triangle",
        kyc_document: "fas fa-file-signature",
        ib_agreement: "fas fa-handshake",
      };
      return iconMap[type] || "fas fa-file";
    };

    const getDocumentSourceLabel = (doc) => {
      if (doc.source === "registration")
        return t("clientDetail_docSource_registration");
      if (doc.source === "ib") return t("clientDetail_docSource_ib");
      return t("clientDetail_docSource_kyc");
    };

    const viewDocument = (doc) => {
      currentDocument.value = doc;
      showDocumentModal.value = true;
      document.body.style.overflow = "hidden";
    };

    const closeDocumentModal = () => {
      showDocumentModal.value = false;
      currentDocument.value = null;
      document.body.style.overflow = "auto";
    };

    const downloadDocument = (doc) => {
      if (!doc) {
        doc = currentDocument.value;
      }
      if (!doc) return;

      const title = doc.title;
      const content = doc.content;

      // Create a printable HTML document
      const printWindow = window.open("", "_blank", "width=800,height=600");

      if (!printWindow) {
        alert(t("leadDetail_popupBlocked"));
        return;
      }

      const loc = languageStore.currentLanguage === "zh" ? "zh-CN" : "en-US";
      const genDate = new Date().toLocaleDateString(loc, {
        year: "numeric",
        month: "long",
        day: "numeric",
      });
      const company =
        branding.value.companyName || t("clientDetail_platformGeneric");
      const copyright =
        branding.value.copyrightText || t("clientDetail_platformGeneric");

      const htmlContent = `
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset="UTF-8">
          <title>${title} - ${props.client.firstName} ${props.client.lastName}</title>
          <style>
            * {
              margin: 0;
              padding: 0;
              box-sizing: border-box;
            }

            body {
              font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
              line-height: 1.6;
              color: var(--color-ink);
              padding: 40px;
              max-width: 800px;
              margin: 0 auto;
            }

            .header {
              text-align: center;
              margin-bottom: 30px;
              padding-bottom: 20px;
              border-bottom: 3px solid var(--color-brand);
            }

            .header h1 {
              font-size: 28px;
              color: var(--color-brand);
              margin-bottom: 10px;
            }

            .header .company-name {
              font-size: 18px;
              color: var(--color-text);
              font-weight: 600;
            }

            .document-content {
              margin: 30px 0;
              padding: 20px;
              background: var(--color-surface-soft);
              border-radius: var(--radius-md);
            }

            .document-content h4 {
              color: var(--color-ink);
              margin-top: 20px;
              margin-bottom: 10px;
              font-size: 16px;
            }

            .document-content p {
              margin-bottom: 12px;
              text-align: justify;
            }

            .document-content ul {
              margin: 15px 0 15px 30px;
            }

            .document-content li {
              margin-bottom: 8px;
            }

            .signature-section {
              margin-top: 40px;
              padding: 25px;
              background: var(--color-warning-soft);
              border: 2px solid #f6b93b;
              border-radius: var(--radius-md);
              page-break-inside: avoid;
            }

            .signature-section h3 {
              font-size: 18px;
              color: var(--color-ink);
              margin-bottom: 20px;
            }

            .signature-section h3::before {
              content: "✍️";
              margin-right: 10px;
              font-size: 20px;
            }

            .signature-grid {
              display: grid;
              grid-template-columns: repeat(2, 1fr);
              gap: 20px;
            }

            .signature-item {
              margin-bottom: 15px;
            }

            .signature-item label {
              display: block;
              font-size: 11px;
              color: var(--color-muted);
              text-transform: uppercase;
              font-weight: 600;
              letter-spacing: 0.5px;
              margin-bottom: 5px;
            }

            .signature-item .value {
              font-size: 14px;
              color: var(--color-ink);
              font-weight: 600;
            }

            .footer {
              margin-top: 40px;
              padding-top: 20px;
              border-top: 2px solid var(--color-border);
              text-align: center;
              font-size: 12px;
              color: var(--color-muted);
            }

            @media print {
              body {
                padding: 20px;
              }

              .no-print {
                display: none;
              }
            }
          </style>
        </head>
        <body>
          <div class="header">
            <h1>${title}</h1>
            <div class="company-name">${company}</div>
          </div>

          <div class="document-content">
            ${content}
          </div>

          <div class="signature-section">
            <h3>${t("leadDetail_digitalSignature")}</h3>
            <div class="signature-grid">
              <div class="signature-item">
                <label>${t("leadDetail_fullName")}</label>
                <div class="value">${props.client.firstName} ${props.client.lastName}</div>
              </div>
              <div class="signature-item">
                <label>${t("leadDetail_labelEmail")}</label>
                <div class="value">${props.client.email}</div>
              </div>
              <div class="signature-item">
                <label>${t("leadDetail_clientId")}</label>
                <div class="value">${props.client.id}</div>
              </div>
              <div class="signature-item">
                <label>${t("leadDetail_dateSigned")}</label>
                <div class="value">${formatDate(doc.signedAt)}</div>
              </div>
            </div>
          </div>

          <div class="footer">
            <p>${t("clientDetail_print_legalNotice")}</p>
            <p>${tParams("clientDetail_print_generatedOn", "Generated on {date}", { date: genDate })}</p>
            <p>${tParams("clientDetail_print_allRights", "© {company}. All rights reserved.", { company: copyright })}</p>
          </div>

          <div class="no-print" style="margin-top: 30px; text-align: center;">
            <button onclick="window.print()" style="padding: 12px 30px; background: var(--color-brand-solid); color: white; border: none; border-radius: var(--radius-md); font-size: 16px; font-weight: 600; cursor: pointer; margin-right: 10px;">
              ${t("clientDetail_print_btnPrintSave")}
            </button>
            <button onclick="window.close()" style="padding: 12px 30px; background: var(--color-border); color: var(--color-text); border: none; border-radius: var(--radius-md); font-size: 16px; font-weight: 600; cursor: pointer;">
              ${t("leadDetail_close")}
            </button>
          </div>
        </body>
        </html>
      `;

      printWindow.document.write(htmlContent);
      printWindow.document.close();

      printWindow.onload = () => {
        setTimeout(() => {
          printWindow.focus();
        }, 250);
      };
    };

    const downloadCurrentDocument = () => {
      downloadDocument(currentDocument.value);
    };

    // 监听props变化
    watch(
      () => props.client,
      (newClient) => {
        editableClient.value = { ...newClient };
        initializeOriginalData();
      },
      { deep: true },
    );

    // 初始化
    initializeOriginalData();

    // 加载交易信息
    const loadTradingInfo = async () => {
      try {
        tradingLoading.value = true;
        const response = await clientService.getTradingInfo(props.client.id);
        if (response.success) {
          tradingInfo.value = response.data;
        }
      } catch (error) {
        console.error("Failed to load trading info:", error);
        tradingInfo.value = { hasTradingAccount: false };
      } finally {
        tradingLoading.value = false;
      }
    };

    const getPlatformDisplayName = (platformKey, fallbackName = "") => {
      if (fallbackName) return fallbackName;
      return (
        platformNameMap[platformKey] ||
        String(platformKey || "").toUpperCase() ||
        t("clientDetail_platformGeneric")
      );
    };

    const getPlatformBadge = (platformKey) => {
      if (!platformKey) return t("clientDetail_platformBadge_fallback");
      if (platformKey === "financepro") return "FinancePro";
      return String(platformKey).toUpperCase();
    };

    const getFirstDefined = (...values) =>
      values.find(
        (value) => value !== undefined && value !== null && value !== "",
      );

    const formatValue = (value) => {
      if (value === undefined || value === null || value === "")
        return t("leads_na");
      return String(value);
    };

    const formatLeverage = (value) => {
      if (value === undefined || value === null || value === "")
        return t("leads_na");
      return `1:${value}`;
    };

    // 格式化货币
    const formatCurrency = (value, currency = "AUD") => {
      if (value === undefined || value === null || value === "")
        return t("leads_na");
      const numValue = parseFloat(value);
      if (isNaN(numValue)) return t("leads_na");
      const loc = languageStore.currentLanguage === "zh" ? "zh-CN" : "en-US";
      return new Intl.NumberFormat(loc, {
        style: "currency",
        currency: currency || "AUD",
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      }).format(numValue);
    };

    // 格式化保证金水平（取2位小数，并加上%字符）
    const formatMarginLevel = (value) => {
      if (value === undefined || value === null || value === "")
        return t("leads_na");
      const numValue = parseFloat(value);
      if (isNaN(numValue)) return t("leads_na");
      return numValue.toFixed(2) + "%";
    };

    const buildAccountItems = (entry) => {
      const currency = getFirstDefined(entry.currency);
      const na = t("leads_na");

      return [
        {
          label: t("clientDetail_trading_label_accountId"),
          value: formatValue(entry.accountId),
        },
        {
          label: t("clientDetail_trading_label_login"),
          value: formatValue(entry.login),
        },
        {
          label: t("clientDetail_trading_label_country"),
          value: formatValue(entry.country),
        },
        {
          label: t("clientDetail_trading_label_created"),
          value: formatDate(entry.createdAt),
        },
        {
          label: t("clientDetail_trading_label_currency"),
          value: formatValue(currency),
        },
        {
          label: t("clientDetail_trading_label_balance"),
          value: formatCurrency(entry.balance, currency),
        },
        {
          label: t("clientDetail_trading_label_credit"),
          value: formatCurrency(entry.credit, currency),
        },
        {
          label: t("clientDetail_trading_label_margin"),
          value: formatCurrency(entry.margin, currency),
        },
        {
          label: t("clientDetail_trading_label_freeMargin"),
          value: formatCurrency(entry.freeMargin, currency),
        },
        {
          label: t("clientDetail_trading_label_leverage"),
          value: formatLeverage(entry.leverage),
        },
        {
          label: t("clientDetail_trading_label_accountType"),
          value: formatValue(entry.accountType),
        },
      ].filter((item) => item.value !== na);
    };

    const normalizeTradingAccounts = (data) => {
      if (!data) return [];

      if (Array.isArray(data)) {
        return data
          .filter(
            (entry) =>
              entry &&
              typeof entry === "object" &&
              (entry.platformKey || entry.accountId || entry.accountNumber),
          )
          .map((entry) => ({
            platformKey: entry.platformKey || "unknown",
            platformName: getPlatformDisplayName(entry.platformKey),
            platformBadge: getPlatformBadge(entry.platformKey),
            accountId: entry.accountId,
            accountNumber: entry.accountNumber,
            login: entry.login,
            name: entry.name,
            status: entry.status,
            items: buildAccountItems(entry),
          }));
      }

      const legacyAccounts = [];
      const reservedKeys = new Set([
        "accountSummary",
        "accountInfo",
        "hasTradingAccount",
        "accountId",
        "platformKey",
      ]);

      Object.entries(data).forEach(([key, value]) => {
        if (reservedKeys.has(key)) return;
        if (!value || typeof value !== "object") return;

        const summary = value.accountSummary || {};
        const info = value.accountInfo || {};
        const external = value.externalAccount || {};
        const platformKey = getFirstDefined(
          value.platformKey,
          key,
          data.platformKey,
        );

        const normalized = {
          platformKey: platformKey || "unknown",
          accountId: getFirstDefined(
            value.accountId,
            info.Id,
            summary.accountId,
            external.providerAccountId,
            data.accountId,
          ),
          accountNumber: getFirstDefined(external.accountNumber),
          login: getFirstDefined(
            info.PredefinedLogin,
            external.predefinedLogin,
          ),
          name: getFirstDefined(info.Name, external.name),
          status: getFirstDefined(
            info.Status,
            summary.Status,
            summary.status,
            external.status,
          ),
          country: getFirstDefined(info.Country, external.country),
          createdAt: getFirstDefined(info.CreatorTime, external.creatorTime),
          currency: getFirstDefined(
            summary.Currency,
            summary.CurrencyName,
            summary.accountCurrency,
            external.accountCurrency,
          ),
          total: getFirstDefined(summary.Total),
          balance: getFirstDefined(
            summary.Balance,
            summary.platformBalance,
            external.platformBalance,
          ),
          credit: getFirstDefined(summary.Credit, external.platformCredit),
          equity: getFirstDefined(summary.Equity),
          margin: getFirstDefined(summary.Margin),
          freeMargin: getFirstDefined(summary.FreeMargin),
          leverage: getFirstDefined(
            info.Leverage,
            summary.Leverage,
            summary.leverage,
            external.leverage,
          ),
          accountType: getFirstDefined(
            summary.accountType,
            external.accountType,
          ),
        };

        legacyAccounts.push({
          ...normalized,
          platformName: getPlatformDisplayName(
            platformKey,
            getFirstDefined(external.platformName, summary.platformName),
          ),
          platformBadge: getPlatformBadge(platformKey),
          items: buildAccountItems(normalized),
        });
      });

      if (legacyAccounts.length) return legacyAccounts;

      if (data.accountSummary || data.accountInfo || data.accountId) {
        const summary = data.accountSummary || {};
        const info = data.accountInfo || {};
        return [
          {
            platformKey: data.platformKey || "unknown",
            platformName: getPlatformDisplayName(data.platformKey),
            platformBadge: getPlatformBadge(data.platformKey),
            accountId: getFirstDefined(data.accountId, info.Id),
            accountNumber: "-",
            login: getFirstDefined(info.PredefinedLogin),
            name: getFirstDefined(info.Name),
            status: getFirstDefined(info.Status, summary.Status),
            items: buildAccountItems({
              accountId: getFirstDefined(data.accountId, info.Id),
              login: getFirstDefined(info.PredefinedLogin),
              name: getFirstDefined(info.Name),
              status: getFirstDefined(info.Status, summary.Status),
              country: getFirstDefined(info.Country),
              createdAt: getFirstDefined(info.CreatorTime),
              currency: getFirstDefined(summary.Currency, summary.CurrencyName),
              total: getFirstDefined(summary.Total),
              balance: getFirstDefined(summary.Balance),
              credit: getFirstDefined(summary.Credit),
              equity: getFirstDefined(summary.Equity),
              margin: getFirstDefined(summary.Margin),
              freeMargin: getFirstDefined(summary.FreeMargin),
              leverage: getFirstDefined(info.Leverage, summary.Leverage),
              accountType: getFirstDefined(summary.accountType),
            }),
          },
        ];
      }

      return [];
    };

    const tradingAccounts = computed(() => {
      void languageStore.currentLanguage;
      return normalizeTradingAccounts(tradingInfo.value);
    });

    const tradingPlatformSections = computed(() => {
      const sectionMap = new Map();

      tradingAccounts.value.forEach((account) => {
        const key = account.platformKey || "unknown";
        if (!sectionMap.has(key)) {
          sectionMap.set(key, {
            platformKey: key,
            platformName: account.platformName || getPlatformDisplayName(key),
            platformBadge: account.platformBadge || getPlatformBadge(key),
            accounts: [],
          });
        }

        sectionMap.get(key).accounts.push(account);
      });

      return Array.from(sectionMap.values());
    });

    const hasTradingAccounts = computed(() => tradingAccounts.value.length > 0);
    const canCreateTradingAccount = computed(() =>
      authStore.hasPermission("client_tradding_create"),
    );

    const openCreateTradingAccountModal = () => {
      showCreateTradingAccountModal.value = true;
    };

    const handleTradingAccountCreated = () => {
      loadTradingInfo();
    };

    // 获取账户状态样式类
    const getAccountStatusClass = (status) => {
      if (typeof status === "string") {
        const normalized = status.toLowerCase();
        if (normalized === "active") return "active";
        if (normalized === "inactive") return "inactive";
        if (normalized === "suspended") return "suspended";
      }

      const statusMap = {
        1: "active",
        0: "inactive",
        2: "suspended",
      };
      return statusMap[status] || "unknown";
    };

    // 获取账户状态文本
    const getAccountStatusText = (status) => {
      if (typeof status === "string") {
        const normalized = status.toLowerCase();
        if (normalized === "active")
          return t("clientDetail_accountStatus_active");
        if (normalized === "inactive")
          return t("clientDetail_accountStatus_inactive");
        if (normalized === "suspended")
          return t("clientDetail_accountStatus_suspended");
        return status;
      }

      const statusMap = {
        1: "clientDetail_accountStatus_active",
        0: "clientDetail_accountStatus_inactive",
        2: "clientDetail_accountStatus_suspended",
      };
      const key = statusMap[status];
      return key ? t(key) : t("clientDetail_accountStatus_unknown");
    };

    // 组件挂载时加载文档和交易信息
    onMounted(async () => {
      loadDocuments();
      loadTradingInfo();
      // 加载品牌配置
      try {
        const config = await brandingApi.getBranding();
        branding.value = {
          companyName: config.companyName || t("clientDetail_platformGeneric"),
          copyrightText:
            config.copyrightText || t("clientDetail_platformGeneric"),
        };
      } catch (error) {
        console.error("Failed to load branding:", error);
      }
    });

    return {
      t,
      tParams,
      // 数据
      editableClient,
      countries,
      documents,
      documentsLoading,
      showDocumentModal,
      currentDocument,
      tradingInfo,
      tradingLoading,
      showCreateTradingAccountModal,
      tradingPlatformSections,
      hasTradingAccounts,
      canCreateTradingAccount,
      branding,
      approvingKyc,
      sendingPasswordReset,
      showResetPasswordModal,
      applySalesAssignment,

      // 计算属性
      hasChanges,

      // 方法
      formatKycStatus,
      formatDate,
      approveClientKyc,
      loadTradingInfo,
      openCreateTradingAccountModal,
      handleTradingAccountCreated,
      formatCurrency,
      formatMarginLevel,
      getAccountStatusClass,
      getAccountStatusText,
      checkChanges,
      saveAllInfo,
      sendEmail,
      sendResetEmail,
      suspendAccount,
      viewKycDetails,
      exportData,
      deleteClient,

      // Documents方法
      loadDocuments,
      getDocumentIcon,
      getDocumentSourceLabel,
      viewDocument,
      closeDocumentModal,
      downloadDocument,
      downloadCurrentDocument,
    };
  },
};
</script>

<style scoped>
.client-detail-row {
  padding: 0;
  width: 100%;
  max-width: 100%;
}

/* 信息卡片网格 */
.info-cards-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 20px;
  margin-bottom: 15px;
}

.detail-card {
  background: var(--color-surface);
  border-radius: var(--radius-md);
  padding: 20px;
  border: 2px solid var(--color-border);
  transition: all 0.3s ease;
  width: 100%;
  min-width: 0;
  box-sizing: border-box;
}

.detail-card:hover {
  border-color: var(--color-brand);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.1);
}

.detail-card-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 15px;
  padding-bottom: 12px;
  border-bottom: 2px solid var(--color-border);
}

.detail-card-icon {
  width: 40px;
  height: 40px;
  background: var(--color-brand-solid);
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  color: white;
  flex-shrink: 0;
}

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex: 1;
}

.trading-section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex: 1;
}

.detail-card-title {
  font-size: 16px;
  font-weight: 600;
  color: var(--color-ink);
  margin: 0;
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
}

.btn-save.disabled {
  background: var(--color-border);
  color: var(--color-faint);
  cursor: not-allowed;
}

.btn-save.active {
  background: var(--color-brand-solid);
  color: white;
  box-shadow: 0 2px 8px rgba(var(--color-brand-rgb), 0.3);
}

.btn-save.active:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.4);
}

.btn-approve-kyc {
  padding: 10px 18px;
  border: none;
  border-radius: var(--radius-md);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: linear-gradient(
    135deg,
    var(--color-success) 0%,
    var(--color-success) 100%
  );
  color: white;
  box-shadow: 0 2px 8px rgba(56, 161, 105, 0.28);
}

.btn-approve-kyc:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(56, 161, 105, 0.36);
}

.btn-approve-kyc:disabled {
  opacity: 0.7;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

.btn-create-trading-account {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 8px 12px;
  border: 0;
  border-radius: var(--radius-sm);
  background: var(--color-brand-solid);
  color: #ffffff;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  transition: background 0.2s ease;
}

.btn-create-trading-account:hover:not(:disabled) {
  background: var(--color-brand-strong);
}

.btn-create-trading-account:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

.detail-action-button {
  padding: 8px 14px;
  border: none;
  border-radius: var(--radius-md);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: var(--color-brand-soft);
  color: var(--color-brand-strong);
  white-space: nowrap;
}

.detail-action-button:hover:not(:disabled) {
  background: var(--color-brand-strong);
  color: white;
}

.detail-action-button:disabled {
  background: var(--color-border);
  color: var(--color-faint);
  cursor: not-allowed;
}

/* Sales Assignment */
.sales-assignment-section .detail-card-header {
  flex-wrap: wrap;
  gap: 12px;
}
.assignment-content {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}
.assignment-field {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.assignment-field label {
  font-weight: 600;
  color: var(--color-text);
  font-size: 13px;
}
.assignment-field select,
.assignment-field textarea {
  padding: 10px 14px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  outline: none;
  transition: all 0.3s ease;
  background: var(--color-surface);
}
.assignment-field select {
  cursor: pointer;
}
.assignment-field select:focus,
.assignment-field textarea:focus {
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}
.assignment-field select:disabled,
.assignment-field textarea:disabled {
  background: var(--color-surface-soft);
  color: var(--color-text);
  cursor: not-allowed;
  opacity: 1;
}
.assignment-field textarea {
  resize: vertical;
  min-height: 80px;
  font-family: inherit;
}
.assignment-info {
  background: var(--color-surface-soft);
  padding: 15px;
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border);
}
.assignment-info-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 0;
  border-bottom: 1px solid var(--color-border);
}
.assignment-info-item:last-child {
  border-bottom: none;
}
.assignment-info-label {
  font-weight: 600;
  color: var(--color-muted);
  font-size: 13px;
}
.assignment-info-value {
  color: var(--color-ink);
  font-size: 14px;
  font-weight: 500;
}
.assignment-helper {
  font-size: 12px;
  color: var(--color-muted);
}
.assignment-status {
  display: inline-block;
  padding: 4px 12px;
  border-radius: var(--radius-lg);
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
}
.assignment-status.unassigned {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}
.assignment-status.assigned {
  background: var(--color-success-soft);
  color: var(--color-success);
}
.btn-assign {
  padding: 10px 20px;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: var(--color-brand-solid);
  color: white;
  box-shadow: 0 2px 8px rgba(var(--color-brand-rgb), 0.3);
}
.btn-assign:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.4);
}
.btn-assign:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  box-shadow: none;
  transform: none;
}

.detail-field {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 0;
  border-bottom: 1px solid #f0f0f0;
}

.detail-field:last-child {
  border-bottom: none;
}

.detail-label {
  font-weight: 600;
  color: var(--color-muted);
  font-size: 13px;
  min-width: 120px;
}

.detail-value-wrapper {
  display: flex;
  align-items: center;
  gap: 10px;
  flex: 1;
  justify-content: flex-end;
  min-width: 0;
}

.detail-value {
  color: var(--color-ink);
  font-size: 14px;
  font-weight: 500;
  text-align: right;
  border: 2px solid transparent;
  padding: 4px 8px;
  border-radius: 4px;
  min-width: 0;
  max-width: 100%;
  overflow-wrap: anywhere;
  transition: all 0.3s ease;
}

.detail-value.editable {
  border: 2px solid var(--color-border);
  background: var(--color-surface);
  cursor: text;
  outline: none;
}

.detail-value.editable:focus {
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.detail-value.editable:disabled {
  background: var(--color-surface-soft);
  color: var(--color-text);
  cursor: not-allowed;
  opacity: 1;
}

.detail-value select {
  width: 100%;
  background: var(--color-surface);
  border: none;
  outline: none;
  cursor: pointer;
}

.detail-value select:disabled {
  background: var(--color-surface-soft);
  color: var(--color-text);
  cursor: not-allowed;
  opacity: 1;
}

.kyc-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: var(--radius-lg);
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
}

.kyc-badge.approved {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.kyc-badge.pending {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.kyc-badge.rejected {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.kyc-badge.not_started {
  background: var(--color-border);
  color: var(--color-text);
}

/* 响应式设计 */
@media (max-width: 768px) {
  .info-cards-grid {
    grid-template-columns: 1fr;
  }

  .detail-field {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }

  .detail-value-wrapper {
    width: 100%;
    justify-content: flex-start;
  }

  .detail-value {
    min-width: auto;
    width: 100%;
    text-align: left;
  }

  .section-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
  }
}

/* Trading Information Section Styles */
.trading-section {
  grid-column: 1 / -1;
  margin-bottom: 20px;
}

.trading-info-content {
  padding: 15px 0;
}

.trading-platform-sections {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.trading-platform-section {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.trading-platform-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.trading-platform-meta {
  display: flex;
  align-items: center;
  gap: 10px;
}

.info-group-title {
  font-size: 15px;
  font-weight: 600;
  color: var(--color-ink);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 8px;
}

.info-group-title i {
  color: var(--color-brand);
  font-size: 16px;
}

.platform-count {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-muted);
  white-space: nowrap;
}

.trading-account-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

.platform-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 6px 12px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  white-space: nowrap;
}

.platform-badge.platform-financepro {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.platform-badge.platform-mt5 {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.platform-badge.platform-mt4 {
  background: var(--color-warning-soft);
  color: #c2410c;
}

.trading-account-card {
  background: var(--color-surface-soft);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 18px;
  display: flex;
  flex-direction: column;
  gap: 14px;
  width: 100%;
  min-width: 0;
  box-sizing: border-box;
}

.trading-account-card-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding-bottom: 12px;
  border-bottom: 2px solid var(--color-border);
}

.trading-account-heading {
  min-width: 0;
}

.trading-account-title {
  font-size: 15px;
  font-weight: 700;
  color: var(--color-ink);
  line-height: 1.35;
  word-break: break-word;
}

.trading-account-subtitle {
  margin-top: 4px;
  font-size: 13px;
  color: var(--color-muted);
  line-height: 1.4;
  word-break: break-word;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px 16px;
}

.info-item {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  justify-content: flex-start;
  gap: 6px;
  padding: 12px 14px;
  background: var(--color-surface);
  border-radius: var(--radius-sm);
  border: 1px solid var(--color-border);
  min-width: 0;
}

.info-label {
  font-weight: 600;
  color: var(--color-muted);
  font-size: 13px;
}

.info-value {
  color: var(--color-ink);
  font-size: 14px;
  font-weight: 500;
  text-align: left;
  word-break: break-word;
}

.status-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: var(--radius-lg);
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
}

.status-badge.active {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.status-badge.inactive {
  background: var(--color-border);
  color: var(--color-text);
}

.status-badge.suspended {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.status-badge.unknown {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

@media (max-width: 768px) {
  .trading-platform-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .trading-platform-meta {
    flex-wrap: wrap;
  }

  .trading-account-grid {
    grid-template-columns: 1fr;
  }

  .trading-account-card-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .info-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .info-value {
    text-align: left;
  }
}

/* Documents Section Styles */
.documents-section {
  grid-column: 1 / -1;
}

.documents-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 20px;
  padding: 15px 0;
}

.document-card {
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 20px;
  transition: all 0.3s ease;
  cursor: pointer;
  width: 100%;
  min-width: 0;
  box-sizing: border-box;
}

.document-card:hover {
  border-color: var(--color-brand);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.15);
  transform: translateY(-2px);
}

.document-card-header {
  display: flex;
  align-items: start;
  gap: 12px;
  margin-bottom: 15px;
}

.document-icon {
  width: 40px;
  height: 40px;
  background: var(--color-brand-solid);
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  color: white;
  flex-shrink: 0;
}

.document-info {
  flex: 1;
  min-width: 0;
}

.document-title {
  font-size: 15px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 5px;
  white-space: normal;
  word-wrap: break-word;
  overflow-wrap: break-word;
  line-height: 1.35;
}

.document-date {
  font-size: 12px;
  color: var(--color-muted);
  display: flex;
  align-items: center;
  gap: 5px;
}

.document-date i {
  font-size: 11px;
}

.document-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 15px;
}

.document-meta-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--color-text);
  padding: 4px 10px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-sm);
}

.document-meta-item i {
  color: var(--color-brand);
  font-size: 12px;
}

.document-status {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: var(--radius-sm);
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
}

.document-status.signed {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.document-status i {
  font-size: 11px;
}

.document-actions {
  display: flex;
  gap: 8px;
}

.btn-doc {
  flex: 1;
  padding: 8px 12px;
  border-radius: var(--radius-sm);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
}

.btn-view {
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.btn-view:hover {
  background: var(--color-brand-solid);
  color: white;
}

.btn-download {
  background: var(--color-border);
  color: var(--color-text);
}

.btn-download:hover {
  background: var(--color-border-strong);
}

.loading-state {
  text-align: center;
  padding: 40px 20px;
  color: var(--color-muted);
}

.loading-state i {
  font-size: 32px;
  margin-bottom: 15px;
  color: var(--color-brand);
}

.loading-state p {
  font-size: 14px;
}

.empty-state {
  grid-column: 1 / -1;
  text-align: center;
  padding: 40px 20px;
  color: var(--color-faint);
}

.empty-state i {
  font-size: 48px;
  margin-bottom: 15px;
  display: block;
}

.empty-state p {
  font-size: 14px;
  font-style: italic;
}

/* Modal Styles */
.modal {
  position: fixed;
  z-index: 10000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.7);
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

.modal-content {
  background-color: var(--color-surface);
  margin: 3% auto;
  padding: 0;
  border-radius: var(--radius-lg);
  max-width: 900px;
  width: 90%;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  animation: slideIn 0.3s ease;
}

@keyframes slideIn {
  from {
    transform: translateY(-50px);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

.modal-header {
  background: var(--color-brand-solid);
  color: white;
  padding: 20px 30px;
  border-radius: 12px 12px 0 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.modal-header h2 {
  margin: 0;
  font-size: 20px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.close {
  color: white;
  font-size: 28px;
  font-weight: 300;
  cursor: pointer;
  transition: transform 0.2s ease;
  line-height: 1;
}

.close:hover {
  transform: rotate(90deg);
}

.modal-body {
  padding: 25px;
  overflow-y: auto;
  flex: 1;
}

.document-preview {
  background: var(--color-surface-soft);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 25px;
  margin-bottom: 20px;
}

.document-preview h3 {
  font-size: 18px;
  color: var(--color-ink);
  margin-bottom: 15px;
  text-align: center;
}

.document-preview-content {
  background: var(--color-surface);
  padding: 25px;
  border-radius: var(--radius-md);
  line-height: 1.8;
  color: var(--color-text);
  max-height: 400px;
  overflow-y: auto;
}

.document-preview-content h4 {
  color: var(--color-ink);
  margin-top: 20px;
  margin-bottom: 10px;
  font-size: 16px;
}

.document-preview-content p {
  margin-bottom: 15px;
}

.document-preview-content ul {
  margin-left: 20px;
  margin-bottom: 15px;
}

.document-preview-content li {
  margin-bottom: 8px;
}

.document-signature {
  background: var(--color-warning-soft);
  border: 2px solid #f6b93b;
  border-radius: var(--radius-md);
  padding: 20px;
  margin-top: 20px;
}

.document-signature h4 {
  font-size: 15px;
  color: var(--color-ink);
  margin-bottom: 15px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.document-signature h4 i {
  color: #f6b93b;
}

.signature-info-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 15px;
}

.signature-field {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.signature-field label {
  font-size: 11px;
  color: var(--color-muted);
  text-transform: uppercase;
  font-weight: 600;
  letter-spacing: 0.5px;
}

.signature-field .signature-value {
  font-size: 14px;
  color: var(--color-ink);
  font-weight: 600;
}

.modal-footer {
  padding: 15px 25px;
  background: var(--color-surface-soft);
  border-top: 1px solid var(--color-border);
  border-radius: 0 0 12px 12px;
  display: flex;
  gap: 12px;
  justify-content: flex-end;
}

.btn-modal {
  padding: 10px 20px;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-modal-primary {
  background: var(--color-brand-solid);
  color: white;
}

.btn-modal-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.4);
}

.btn-modal-secondary {
  background: var(--color-border);
  color: var(--color-text);
}

.btn-modal-secondary:hover {
  background: var(--color-border-strong);
}

@media (max-width: 1024px) {
  .signature-info-row {
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
  }
}

@media (max-width: 768px) {
  .documents-grid {
    grid-template-columns: 1fr;
  }

  .modal-content {
    width: 95%;
    margin: 5% auto;
  }

  .modal-body {
    padding: 20px;
  }

  .signature-info-row {
    grid-template-columns: 1fr;
  }
}
</style>
