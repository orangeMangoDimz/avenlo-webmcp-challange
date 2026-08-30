<template>
  <tr class="detail-row">
    <td :colspan="colspan">
      <div class="detail-content">
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
                  @click="saveChanges"
                  :disabled="!hasChanges"
                >
                  <i class="fas fa-save"></i> {{ t("leadDetail_save") }}
                </button>
              </div>
            </div>

            <div
              class="detail-field"
              v-for="field in editableFields"
              :key="field.key"
            >
              <span class="detail-label">{{ field.label }}</span>
              <div class="detail-value-wrapper">
                <input
                  v-if="field.editable"
                  :type="field.type || 'text'"
                  v-model="editData[field.key]"
                  class="detail-value editable"
                  :disabled="!canEdit"
                  @input="checkChanges"
                />
                <span v-else class="detail-value">
                  {{ lead[field.key] }}
                </span>
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
                  :disabled="sendingPasswordReset || !editData.email"
                  @click="sendResetEmail"
                >
                  <i
                    v-if="sendingPasswordReset"
                    class="fas fa-spinner fa-spin"
                  ></i>
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
              :client-id="lead.leadId"
              :client-name="
                (editData.firstName || '') + ' ' + (editData.lastName || '')
              "
              entity-label="Lead"
              :reset-fn="leadResetPassword"
            />

            <div class="detail-field">
              <span class="detail-label">{{
                t("leadDetail_statusLabel")
              }}</span>
              <div class="detail-value-wrapper">
                <select
                  class="detail-value editable"
                  v-model="editData.status"
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
              <span class="detail-label">{{
                t("leadDetail_emailVerified")
              }}</span>
              <span class="detail-value">{{
                lead.emailVerified ? t("leadDetail_yes") : t("leadDetail_no")
              }}</span>
            </div>

            <div class="detail-field">
              <span class="detail-label">{{
                t("leadDetail_registrationDate")
              }}</span>
              <span class="detail-value">{{
                formatDate(lead.registrationDate)
              }}</span>
            </div>

            <div class="detail-field">
              <span class="detail-label">{{ t("leadDetail_lastLogin") }}</span>
              <span class="detail-value">{{
                lead.lastLoginAt
                  ? formatDate(lead.lastLoginAt)
                  : t("leadDetail_never")
              }}</span>
            </div>
          </div>

          <!-- Activity Statistics Card -->
          <div class="detail-card">
            <div class="detail-card-header">
              <div class="detail-card-icon">
                <i class="fas fa-chart-bar"></i>
              </div>
              <h3 class="detail-card-title">
                {{ t("leadDetail_sectionActivity") }}
              </h3>
            </div>

            <div class="detail-field">
              <span class="detail-label">{{ t("leadDetail_kycStatus") }}</span>
              <span class="detail-value">{{
                formatKycStatus(lead.kycStatus)
              }}</span>
            </div>

            <div class="detail-field">
              <span class="detail-label">{{
                t("leadDetail_accountStatus")
              }}</span>
              <span class="detail-value">
                <span :class="['status-badge', lead.status]">{{
                  formatLeadStatus(lead.status)
                }}</span>
              </span>
            </div>

            <div class="detail-field">
              <span class="detail-label">{{ t("leadDetail_ipAddress") }}</span>
              <span class="detail-value">{{ lead.registrationIp }}</span>
            </div>

            <div class="detail-field">
              <span class="detail-label">{{
                t("leadDetail_totalLogins")
              }}</span>
              <span class="detail-value">{{ lead.loginCount || 0 }}</span>
            </div>

            <div class="detail-field">
              <span class="detail-label">{{
                t("leadDetail_documentsUploaded")
              }}</span>
              <span class="detail-value">{{ lead.documentsCount || 0 }}</span>
            </div>

            <div class="detail-field">
              <span class="detail-label">{{
                t("leadDetail_lastActivity")
              }}</span>
              <span class="detail-value">{{
                formatDate(lead.lastActivityAt || lead.lastLoginAt)
              }}</span>
            </div>
          </div>
        </div>

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

          <div class="documents-grid">
            <div
              v-for="doc in lead.documents"
              :key="doc.id"
              class="document-card"
              @click="viewDocument(doc)"
            >
              <div class="document-card-header-inner">
                <div class="document-icon">
                  <i :class="getDocumentIcon(doc.documentType)"></i>
                </div>
                <div class="document-info">
                  <div class="document-title">
                    {{ getDocumentTitle(doc.documentType) }}
                  </div>
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
                <div class="document-status signed">
                  <i class="fas fa-check-circle"></i>
                  {{ t("leadDetail_signed") }}
                </div>
              </div>

              <div class="document-actions">
                <button
                  class="btn-doc btn-view"
                  @click.stop="viewDocument(doc)"
                >
                  <i class="fas fa-eye"></i> {{ t("leadDetail_view") }}
                </button>
                <button
                  class="btn-doc btn-download"
                  @click.stop="downloadDocument"
                >
                  <i class="fas fa-download"></i> {{ t("leadDetail_download") }}
                </button>
              </div>
            </div>

            <div
              v-if="!lead.documents || lead.documents.length === 0"
              class="empty-state"
            >
              <i class="fas fa-file-alt"></i>
              <p>{{ t("leadDetail_noDocuments") }}</p>
            </div>
          </div>
        </div>

        <!-- Sales Assignment Section -->
        <div class="detail-card sales-assignment-section">
          <div class="detail-card-header">
            <div class="detail-card-icon">
              <i class="fas fa-user-tie"></i>
            </div>
            <div class="section-header">
              <h3 class="detail-card-title">
                {{ t("leadDetail_sectionSales") }}
              </h3>
              <button
                v-if="canEdit"
                class="btn-assign"
                @click="assignToSales"
                :disabled="assigning"
              >
                <i class="fas fa-user-check"></i>
                {{ t("leadDetail_assignLead") }}
              </button>
            </div>
          </div>
          <div class="assignment-content">
            <div class="assignment-field">
              <label>{{ t("leadDetail_assignToSales") }}</label>
              <select v-model="salesRep" :disabled="assigning || !canEdit">
                <option value="">{{ t("leadDetail_selectSalesRep") }}</option>
                <option
                  v-for="manager in managerOptions"
                  :key="manager.id"
                  :value="String(manager.id)"
                >
                  {{ formatManagerDisplay(manager) }}
                </option>
              </select>
              <div
                v-if="!loadingManagers && managerOptions.length === 0"
                class="assignment-helper"
              >
                <small>{{ t("leadDetail_noSalesReps") }}</small>
              </div>
              <div v-if="loadingManagers" class="assignment-helper">
                <small>{{ t("leadDetail_loadingSalesReps") }}</small>
              </div>
            </div>

            <div class="assignment-info">
              <div class="assignment-info-item">
                <span class="assignment-info-label">{{
                  t("leadDetail_assignmentStatus")
                }}</span>
                <span :class="['assignment-status', assignmentStatusClass]">
                  {{ assignmentStatus }}
                </span>
              </div>
              <div class="assignment-info-item">
                <span class="assignment-info-label">{{
                  t("leadDetail_assignedTo")
                }}</span>
                <span class="assignment-info-value">{{
                  assignedToDisplay
                }}</span>
              </div>
              <div class="assignment-info-item">
                <span class="assignment-info-label">{{
                  t("leadDetail_assignedDate")
                }}</span>
                <span class="assignment-info-value">{{
                  assignedDateDisplay
                }}</span>
              </div>
            </div>

            <div class="assignment-field" style="grid-column: 1 / -1">
              <label>{{ t("leadDetail_followUpNotes") }}</label>
              <textarea
                v-model="assignmentNotes"
                :placeholder="t('leadDetail_notesPlaceholder')"
                rows="4"
                :disabled="assigning || !canEdit"
              ></textarea>
            </div>
          </div>
        </div>

        <!-- Document Viewer Modal -->
        <div v-if="showDocumentModal" class="modal" @click="closeDocumentModal">
          <div class="modal-content" @click.stop>
            <div class="modal-header">
              <h2>
                <i class="fas fa-file-alt"></i>
                {{
                  currentDocument
                    ? getDocumentTitle(currentDocument.documentType)
                    : t("leadDetail_docPreview")
                }}
              </h2>
              <span class="close" @click="closeDocumentModal">&times;</span>
            </div>
            <div class="modal-body">
              <div class="document-preview">
                <h3>
                  {{
                    currentDocument
                      ? getDocumentTitle(currentDocument.documentType)
                      : ""
                  }}
                </h3>
                <div
                  class="document-preview-content font-floor-content"
                  v-html="getDocumentContent(currentDocument?.documentType)"
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
                      {{ lead.firstName }} {{ lead.lastName }}
                    </div>
                  </div>
                  <div class="signature-field">
                    <label>{{ t("leadDetail_labelEmail") }}</label>
                    <div class="signature-value">{{ lead.email }}</div>
                  </div>
                  <div class="signature-field">
                    <label>{{ t("leadDetail_clientId") }}</label>
                    <div class="signature-value">{{ lead.leadId }}</div>
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
                @click="downloadDocument"
              >
                <i class="fas fa-download"></i>
                {{ t("leadDetail_downloadPdf") }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </td>
  </tr>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from "vue";
import { useLeadsStore } from "@/stores/leads";
import { clientService } from "@/services/clientListService";
import { resetPassword as leadsResetPassword } from "@/services/leadsService";
import brandingApi from "@/services/brandingApi";
import ResetPasswordModal from "@/components/clients/ResetPasswordModal.vue";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { translateApiErrorMessage } from "@/i18n/adminI18nBridge";
import { getSubModuleKey } from "@/config/operationLogPages";

const leadsLogSubModule = getSubModuleKey("page_leads");

const { t, tParams, languageStore } = useAdminI18n();

const props = defineProps({
  colspan: {
    type: Number,
    default: 9,
  },
  lead: {
    type: Object,
    required: true,
  },
  canEdit: {
    type: Boolean,
    default: true,
  },
});

const emit = defineEmits(["close", "updated"]);

const leadsStore = useLeadsStore();

const editableFields = computed(() => [
  { key: "firstName", label: t("leadDetail_firstName"), editable: true },
  { key: "lastName", label: t("leadDetail_lastName"), editable: true },
  { key: "email", label: t("leadDetail_email"), editable: true, type: "email" },
  { key: "phone", label: t("leadDetail_phone"), editable: true },
  { key: "country", label: t("leadDetail_country"), editable: true },
]);

// Edit data
const editData = reactive({
  firstName: props.lead.firstName,
  lastName: props.lead.lastName,
  email: props.lead.email,
  phone: props.lead.phone,
  country: props.lead.country,
  status: props.lead.status || "active",
});

// Original data for comparison
const originalData = {
  firstName: props.lead.firstName,
  lastName: props.lead.lastName,
  email: props.lead.email,
  phone: props.lead.phone,
  country: props.lead.country,
  status: props.lead.status || "active",
};

// Password reset
const sendingPasswordReset = ref(false);
const showResetPasswordModal = ref(false);

const leadResetPassword = (id, data) => {
  return leadsResetPassword(id, data);
};

const sendResetEmail = () => {
  if (!editData.email) {
    alert(t("leadDetail_alertNoEmail"));
    return;
  }
  if (
    !confirm(
      tParams(
        "leadDetail_confirmResetEmail",
        "Send a password reset email to {email}?",
        { email: editData.email },
      ),
    )
  )
    return;

  sendingPasswordReset.value = true;
  clientService
    .sendPasswordReset(props.lead.leadId ?? props.lead.id, editData.email, {
      logSubModuleKey: leadsLogSubModule,
    })
    .then(() => {
      alert(t("leadDetail_resetEmailOk"));
    })
    .catch((error) => {
      const data = error?.response?.data ?? error;
      const rawMsg =
        data?.message || error?.message || t("common_errorUnknown");
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

// Sales assignment
const salesRep = ref(
  props.lead.accountManagerId ? String(props.lead.accountManagerId) : "",
);
const assignmentNotes = ref(props.lead.accountManagerNote || "");
const managerOptions = ref([]);
const loadingManagers = ref(false);
const assigning = ref(false);

const formatManagerDisplay = (manager) => {
  if (!manager) return "";
  const displayName = manager.name || manager.fullName || "";
  const email = manager.email || "";
  if (displayName && email) {
    return `${displayName} (${email})`;
  }
  if (displayName) {
    return displayName;
  }
  if (email) {
    return email;
  }
  if (manager.id != null) {
    return tParams("leadDetail_managerNum", "Manager #{n}", {
      n: String(manager.id),
    });
  }
  return t("leadDetail_managerFallback");
};

// Document modal
const showDocumentModal = ref(false);
const currentDocument = ref(null);

// Branding configuration
const branding = ref({
  companyName: "Trading Platform",
  copyrightText: "Trading Platform",
});

onMounted(async () => {
  try {
    const config = await brandingApi.getBranding();
    branding.value = {
      companyName: config.companyName || "Trading Platform",
      copyrightText: config.copyrightText || "Trading Platform",
    };
  } catch (error) {
    console.error("Failed to load branding:", error);
  }
});

// Check if there are changes
const hasChanges = computed(() => {
  return Object.keys(editData).some(
    (key) => editData[key] !== originalData[key],
  );
});

// Watch for lead changes
watch(
  () => props.lead,
  (newLead) => {
    editData.firstName = newLead.firstName;
    editData.lastName = newLead.lastName;
    editData.email = newLead.email;
    editData.phone = newLead.phone;
    editData.country = newLead.country;
    editData.status = newLead.status || "active";
    salesRep.value = newLead.accountManagerId
      ? String(newLead.accountManagerId)
      : "";
    assignmentNotes.value = newLead.accountManagerNote || "";
  },
  { deep: true },
);

// Check for changes
const checkChanges = () => {
  // This will trigger the hasChanges computed property
};

// Save changes
const saveChanges = async () => {
  if (!hasChanges.value) return;

  try {
    const response = await leadsStore.updateLeadInfo(
      props.lead.leadId,
      editData,
    );

    if (response.success) {
      // Update original data
      Object.assign(originalData, editData);

      // Show success message
      alert(t("leadDetail_saveOk"));
      emit("updated");
    } else {
      alert(
        tParams("leadDetail_saveFailed", "Failed to save changes: {msg}", {
          msg: response.message || t("common_errorUnknown"),
        }),
      );
    }
  } catch (error) {
    alert(
      tParams("leadDetail_saveError", "Error saving changes: {msg}", {
        msg: error.message || t("common_errorUnknown"),
      }),
    );
  }
};

// Assign to sales
const assignToSales = async () => {
  if (!salesRep.value) {
    alert(t("leadDetail_alertSelectSales"));
    return;
  }
  assigning.value = true;
  try {
    const payload = {
      assigneeId: Number(salesRep.value),
      notes:
        assignmentNotes.value && assignmentNotes.value.trim() !== ""
          ? assignmentNotes.value.trim()
          : undefined,
      logSubModuleKey: leadsLogSubModule,
    };
    const response = await clientService.bulkAssign(
      [props.lead.leadId],
      payload,
    );
    if (response.success) {
      const manager = response.data?.accountManager;
      if (manager) {
        props.lead.accountManagerId = manager.id;
        props.lead.managerName =
          manager.name || manager.email || props.lead.managerName;
        props.lead.managerEmail = manager.email ?? props.lead.managerEmail;
      } else {
        props.lead.accountManagerId = Number(salesRep.value);
      }
      const returnedNote = response.data?.managerNote ?? payload.notes ?? null;
      props.lead.accountManagerNote = returnedNote;
      assignmentNotes.value = returnedNote || "";
      const assignedAt = response.data?.assignedAt || new Date().toISOString();
      props.lead.accountManagerAssignedAt = assignedAt;
      alert(t("leadDetail_assignOk"));
      emit("updated");
    } else {
      alert(
        tParams("leadDetail_assignFailed", "Failed to assign lead: {msg}", {
          msg: response.message || t("common_errorUnknown"),
        }),
      );
    }
  } catch (error) {
    const message =
      error.response?.data?.message ||
      error.message ||
      t("common_errorUnknown");
    alert(
      tParams("leadDetail_assignFailed", "Failed to assign lead: {msg}", {
        msg: message,
      }),
    );
  } finally {
    assigning.value = false;
  }
};

const loadManagerOptions = async () => {
  loadingManagers.value = true;
  try {
    const response = await clientService.getAssignableSales();
    const optionsData = response?.data ?? response ?? {};
    managerOptions.value = Array.isArray(optionsData.managers)
      ? optionsData.managers
      : [];
  } catch (error) {
    console.error("Failed to load sales representative options:", error);
  } finally {
    loadingManagers.value = false;
  }
};

onMounted(() => {
  loadManagerOptions();
});

const currentManagerId = computed(() => {
  return props.lead.accountManagerId ?? props.lead.assignedTo ?? null;
});

const assignmentStatus = computed(() =>
  currentManagerId.value
    ? t("leadDetail_statusAssigned")
    : t("leadDetail_statusUnassigned"),
);
const assignmentStatusClass = computed(() =>
  currentManagerId.value ? "assigned" : "unassigned",
);

const assignedToDisplay = computed(() => {
  if (props.lead.managerName || props.lead.managerEmail) {
    return formatManagerDisplay({
      id: currentManagerId.value,
      name: props.lead.managerName,
      email: props.lead.managerEmail,
    });
  }
  if (currentManagerId.value) {
    const found = managerOptions.value.find(
      (manager) => Number(manager.id) === Number(currentManagerId.value),
    );
    if (found) {
      return formatManagerDisplay(found);
    }
    return tParams("leadDetail_managerNum", "Manager #{n}", {
      n: String(currentManagerId.value),
    });
  }
  return t("leadDetail_notAssigned");
});

const assignedDateDisplay = computed(() => {
  if (props.lead.accountManagerAssignedAt) {
    return formatDate(props.lead.accountManagerAssignedAt);
  }
  return t("leads_na");
});

const managerNoteDisplay = computed(() => {
  return assignmentNotes.value || props.lead.accountManagerNote || "";
});

// Format date
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

const formatKycStatus = (status) => {
  if (!status) return t("leads_kyc_notStarted");
  const statusMap = {
    not_started: "leads_kyc_notStarted",
    in_progress: "leads_kyc_inProgress",
    pending_review: "leads_kyc_pendingReview",
    approved: "leads_kyc_approved",
    rejected: "leads_kyc_rejected",
  };
  const key = statusMap[status];
  return key ? t(key) : status;
};

const formatLeadStatus = (status) => {
  if (!status) return "";
  const statusMap = {
    active: "leads_account_active",
    inactive: "leads_account_inactive",
    suspended: "leads_account_suspended",
    pending_verification: "leads_account_pending_verification",
  };
  const key = statusMap[status];
  return key ? t(key) : status;
};

// Get document icon
const getDocumentIcon = (type) => {
  const iconMap = {
    terms_of_service: "fas fa-file-contract",
    privacy_policy: "fas fa-shield-alt",
    risk_disclosure: "fas fa-exclamation-triangle",
  };
  return iconMap[type] || "fas fa-file";
};

const getDocumentTitle = (type) => {
  const titleMap = {
    terms_of_service: "leadDetail_doc_terms",
    privacy_policy: "leadDetail_doc_privacy",
    risk_disclosure: "leadDetail_doc_risk",
  };
  const key = titleMap[type];
  return key ? t(key) : type;
};

// Document modal methods
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

const downloadDocument = () => {
  if (!currentDocument.value) return;

  const title = getDocumentTitle(currentDocument.value.documentType);
  const content = getDocumentContent(currentDocument.value.documentType);

  // Create a printable HTML document
  const printWindow = window.open("", "_blank", "width=800,height=600");

  if (!printWindow) {
    alert(t("leadDetail_popupBlocked"));
    return;
  }

  const htmlContent = `
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset="UTF-8">
      <title>${title} - ${props.lead.firstName} ${props.lead.lastName}</title>
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

        .document-content h2,
        .document-content h3,
        .document-content h4 {
          color: var(--color-ink);
          margin-top: 20px;
          margin-bottom: 10px;
        }

        .document-content h2 {
          font-size: 24px;
        }

        .document-content h3 {
          font-size: 20px;
        }

        .document-content h4 {
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
          display: flex;
          align-items: center;
          gap: 10px;
        }

        .signature-section h3::before {
          content: "✍️";
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
          font-size: 14px;
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
          font-size: 14px;
          color: var(--color-muted);
        }

        @media print {
          body {
            padding: 20px;
          }

          .no-print {
            display: none;
          }

          .signature-section {
            page-break-inside: avoid;
          }
        }
      </style>
    </head>
    <body>
      <div class="header">
        <h1>${title}</h1>
        <div class="company-name">${branding.value.companyName}</div>
      </div>

      <div class="document-content">
        ${content}
      </div>

      <div class="signature-section">
        <h3>Digital Signature</h3>
        <div class="signature-grid">
          <div class="signature-item">
            <label>Full Name</label>
            <div class="value">${props.lead.firstName} ${props.lead.lastName}</div>
          </div>
          <div class="signature-item">
            <label>Email Address</label>
            <div class="value">${props.lead.email}</div>
          </div>
          <div class="signature-item">
            <label>Client ID</label>
            <div class="value">${props.lead.leadId}</div>
          </div>
          <div class="signature-item">
            <label>Date & Time Signed</label>
            <div class="value">${formatDate(currentDocument.value.signedAt)}</div>
          </div>
        </div>
      </div>

      <div class="footer">
        <p>This is a digitally signed legal document.</p>
        <p>Generated on ${new Date().toLocaleDateString("en-US", { year: "numeric", month: "long", day: "numeric" })}</p>
        <p>© ${branding.value.copyrightText}. All rights reserved.</p>
      </div>

      <div class="no-print" style="margin-top: 30px; text-align: center;">
        <button onclick="window.print()" style="padding: 12px 30px; background: var(--color-brand-solid); color: white; border: none; border-radius: var(--radius-md); font-size: 16px; font-weight: 600; cursor: pointer; margin-right: 10px;">
          Print / Save as PDF
        </button>
        <button onclick="window.close()" style="padding: 12px 30px; background: var(--color-border); color: var(--color-text); border: none; border-radius: var(--radius-md); font-size: 16px; font-weight: 600; cursor: pointer;">
          Close
        </button>
      </div>
    </body>
    </html>
  `;

  printWindow.document.write(htmlContent);
  printWindow.document.close();

  // Auto print after content loads
  printWindow.onload = () => {
    setTimeout(() => {
      printWindow.focus();
    }, 250);
  };
};

const getDocumentContent = (type) => {
  // Get document content from current document object
  // The content is loaded from database (legalDocuments table)
  if (currentDocument.value && currentDocument.value.content) {
    return currentDocument.value.content;
  }

  // Fallback: find document in lead.documents array
  if (props.lead.documents && props.lead.documents.length > 0) {
    const doc = props.lead.documents.find((d) => d.documentType === type);
    if (doc && doc.content) {
      return doc.content;
    }
  }

  // If no content available, show placeholder
  return "<p>Document content not available. Please contact support.</p>";
};
</script>

<style scoped>
.detail-row {
  background: var(--color-surface-soft);
}

.detail-row > td {
  padding: 0;
  white-space: normal;
  background: var(--color-surface-soft);
}

.detail-content {
  padding: 30px;
  width: var(--detail-panel-width, 100%);
  max-width: 100%;
  box-sizing: border-box;
  position: sticky;
  left: 0;
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
  font-size: 14px;
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
  padding: 4px 8px;
  border-radius: 4px;
  min-width: 0;
  max-width: 100%;
  overflow-wrap: anywhere;
}

.detail-value.editable {
  border: 2px solid var(--color-border);
  background: var(--color-surface);
  transition: all 0.3s ease;
  cursor: text;
}

.detail-value.editable:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.detail-value.editable:disabled {
  background: var(--color-surface-soft);
  color: var(--color-text);
  cursor: not-allowed;
  opacity: 1;
}

.detail-action-button {
  padding: 8px 14px;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
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

/* Status Badge */
.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: var(--radius-lg);
  font-size: 14px;
  font-weight: 600;
  text-transform: uppercase;
}

.status-badge.active {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.status-badge.new {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.status-badge.contacted {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.status-badge.converted {
  background: var(--color-success-soft);
  color: var(--color-success);
}

/* Documents Section */
.documents-section {
  margin-bottom: 15px;
}

.documents-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 15px;
}

.document-card {
  background: var(--color-surface-soft);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 18px;
  transition: all 0.3s ease;
  cursor: pointer;
  width: 100%;
  min-width: 0;
  box-sizing: border-box;
}

.document-card:hover {
  border-color: var(--color-brand);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.1);
}

.document-card-header-inner {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
}

.document-icon {
  width: 40px;
  height: 40px;
  background: var(--color-brand-solid);
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  color: white;
  flex-shrink: 0;
}

.document-info {
  flex: 1;
}

.document-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
}

.document-date {
  font-size: 14px;
  color: var(--color-muted);
  margin-top: 2px;
}

.document-date i {
  margin-right: 4px;
}

.document-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.document-meta-item {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 14px;
  color: var(--color-muted);
}

.document-status.signed {
  background: var(--color-success-soft);
  color: var(--color-success);
  padding: 2px 10px;
  border-radius: var(--radius-md);
  font-weight: 600;
  font-size: 14px;
}

.document-actions {
  display: flex;
  gap: 8px;
}

.btn-doc {
  flex: 1;
  padding: 8px 12px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
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
  background: var(--color-success-soft);
  color: var(--color-success);
}

.btn-download:hover {
  background: var(--color-success-solid);
  color: white;
}

.empty-state {
  text-align: center;
  padding: 40px 20px;
  color: var(--color-faint);
  grid-column: 1 / -1;
}

.empty-state i {
  font-size: 32px;
  margin-bottom: 10px;
  display: block;
}

.empty-state p {
  font-size: 14px;
  margin: 0;
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
  font-size: 14px;
}

.assignment-field select,
.assignment-field input,
.assignment-field textarea {
  padding: 10px 14px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  outline: none;
  transition: all 0.3s ease;
  background: var(--color-surface);
  cursor: text;
}

.assignment-field select {
  cursor: pointer;
}

.assignment-field select:focus,
.assignment-field input:focus,
.assignment-field textarea:focus {
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.assignment-field select:disabled,
.assignment-field input:disabled,
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
  font-size: 14px;
}

.assignment-info-value {
  color: var(--color-ink);
  font-size: 14px;
  font-weight: 500;
}

.assignment-helper {
  font-size: 14px;
  color: var(--color-muted);
}

.assignment-status {
  display: inline-block;
  padding: 4px 12px;
  border-radius: var(--radius-lg);
  font-size: 14px;
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

/* Modal Styles */
.modal {
  position: fixed;
  z-index: 1000;
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
  font-size: 22px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.close {
  color: white;
  font-size: 32px;
  font-weight: 300;
  cursor: pointer;
  transition: transform 0.2s ease;
  line-height: 1;
}

.close:hover {
  transform: rotate(90deg);
}

.modal-body {
  padding: 30px;
  overflow-y: auto;
  flex: 1;
}

.document-preview {
  background: var(--color-surface-soft);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 30px;
  margin-bottom: 25px;
}

.document-preview h3 {
  font-size: 20px;
  color: var(--color-ink);
  margin-bottom: 20px;
  text-align: center;
}

.document-preview-content {
  background: var(--color-surface);
  padding: 30px;
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
  font-size: 16px;
  color: var(--color-ink);
  margin-bottom: 15px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.document-signature h4 i {
  color: #f6b93b;
}

.signature-info-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20px;
}

.signature-field {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.signature-field label {
  font-size: 14px;
  color: var(--color-muted);
  text-transform: uppercase;
  font-weight: 600;
  letter-spacing: 0.5px;
}

.signature-field .signature-value {
  font-size: 15px;
  color: var(--color-ink);
  font-weight: 600;
}

.modal-footer {
  padding: 20px 30px;
  background: var(--color-surface-soft);
  border-top: 1px solid var(--color-border);
  border-radius: 0 0 12px 12px;
  display: flex;
  gap: 15px;
  justify-content: flex-end;
}

.btn-modal {
  padding: 12px 24px;
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
    gap: 15px;
  }
}

@media (max-width: 768px) {
  .info-cards-grid {
    grid-template-columns: 1fr;
  }

  .assignment-content {
    grid-template-columns: 1fr;
  }

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
