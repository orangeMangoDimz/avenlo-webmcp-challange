<template>
  <tr class="detail-row">
    <td colspan="10">
      <div class="detail-content">
        <!-- Detail Sections Grid -->
        <div class="detail-sections">
          <!-- Registration Information -->
          <div class="detail-section">
            <h3>
              <div class="section-header">
                <div class="section-title">
                  <i class="fas fa-user-circle"></i> Registration Information
                </div>
                <button
                  :class="['btn-save', hasChanges ? 'active' : 'disabled']"
                  @click="saveChanges"
                  :disabled="!hasChanges"
                >
                  <i class="fas fa-save"></i> Save
                </button>
              </div>
            </h3>

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
                  @input="checkChanges"
                />
                <span v-else class="detail-value">
                  {{ lead[field.key] }}
                </span>
              </div>
            </div>
          </div>

          <!-- Account Information -->
          <div class="detail-section">
            <h3><i class="fas fa-info-circle"></i> Account Information</h3>
            <div class="detail-field">
              <span class="detail-label">Registration Date</span>
              <span class="detail-value">{{
                formatDate(lead.registrationDate)
              }}</span>
            </div>
            <div class="detail-field">
              <span class="detail-label">IP Address</span>
              <span class="detail-value">{{ lead.registrationIp }}</span>
            </div>
            <div class="detail-field">
              <span class="detail-label">Account Status</span>
              <span class="detail-value">
                <span :class="['status-badge', lead.status]">{{
                  lead.status
                }}</span>
              </span>
            </div>
            <div class="detail-field">
              <span class="detail-label">KYC Status</span>
              <span class="detail-value">{{
                lead.kycStatus || "Not Started"
              }}</span>
            </div>
            <div class="detail-field">
              <span class="detail-label">Email Verified</span>
              <span class="detail-value">{{
                lead.emailVerified ? "Yes" : "No"
              }}</span>
            </div>
            <div class="detail-field">
              <span class="detail-label">Last Login</span>
              <span class="detail-value">{{
                lead.lastLoginAt ? formatDate(lead.lastLoginAt) : "Never"
              }}</span>
            </div>
          </div>
        </div>

        <!-- Signed Documents Section -->
        <div class="documents-section">
          <h3><i class="fas fa-file-signature"></i> Signed Legal Documents</h3>
          <div class="documents-grid">
            <div
              v-for="doc in lead.documents"
              :key="doc.id"
              class="document-card-mini"
              @click="viewDocument(doc)"
            >
              <div class="document-card-header-mini">
                <div class="document-icon-mini">
                  <i :class="getDocumentIcon(doc.documentType)"></i>
                </div>
                <div class="document-title-mini">
                  {{ getDocumentTitle(doc.documentType) }}
                </div>
              </div>
              <div class="document-meta-mini">
                <span class="document-date-mini">{{
                  formatDate(doc.signedAt)
                }}</span>
                <span class="document-status-mini">Signed</span>
              </div>
              <button class="btn-view-doc" @click.stop="viewDocument(doc)">
                <i class="fas fa-eye"></i> View Details
              </button>
            </div>
            <div
              v-if="!lead.documents || lead.documents.length === 0"
              class="no-documents"
            >
              <p>No documents signed yet</p>
            </div>
          </div>
        </div>

        <!-- Sales Assignment Section -->
        <div class="sales-assignment-section">
          <h3>
            <div class="section-header">
              <div class="section-title">
                <i class="fas fa-user-tie"></i> Sales Assignment
              </div>
              <button class="btn-assign" @click="assignToSales">
                <i class="fas fa-user-check"></i> Assign Lead
              </button>
            </div>
          </h3>
          <div class="assignment-content">
            <div class="assignment-field">
              <label>Assign to Sales Representative</label>
              <select v-model="salesRep">
                <option value="">-- Select Sales Rep --</option>
                <option value="john-williams">John Williams</option>
                <option value="sarah-davis">Sarah Davis</option>
                <option value="michael-brown">Michael Brown</option>
                <option value="emily-taylor">Emily Taylor</option>
                <option value="david-wilson">David Wilson</option>
              </select>
            </div>

            <div class="assignment-info">
              <div class="assignment-info-item">
                <span class="assignment-info-label">Status:</span>
                <span
                  :class="['assignment-status', assignmentStatus.toLowerCase()]"
                >
                  {{ assignmentStatus }}
                </span>
              </div>
              <div class="assignment-info-item">
                <span class="assignment-info-label">Assigned To:</span>
                <span class="assignment-info-value">{{ assignedTo }}</span>
              </div>
              <div class="assignment-info-item">
                <span class="assignment-info-label">Assigned Date:</span>
                <span class="assignment-info-value">{{ assignedDate }}</span>
              </div>
            </div>

            <div class="assignment-field" style="grid-column: 1 / -1">
              <label>Follow-up Notes</label>
              <textarea
                v-model="assignmentNotes"
                placeholder="Add notes for the assigned sales representative..."
                rows="4"
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
                    : "Document Preview"
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
                <h4><i class="fas fa-signature"></i> Digital Signature</h4>
                <div class="signature-info-row">
                  <div class="signature-field">
                    <label>Full Name</label>
                    <div class="signature-value">
                      {{ lead.firstName }} {{ lead.lastName }}
                    </div>
                  </div>
                  <div class="signature-field">
                    <label>Email Address</label>
                    <div class="signature-value">{{ lead.email }}</div>
                  </div>
                  <div class="signature-field">
                    <label>Client ID</label>
                    <div class="signature-value">{{ lead.leadId }}</div>
                  </div>
                  <div class="signature-field">
                    <label>Date & Time Signed</label>
                    <div class="signature-value">
                      {{
                        currentDocument
                          ? formatDate(currentDocument.signedAt)
                          : "N/A"
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
                <i class="fas fa-times"></i> Close
              </button>
              <button
                class="btn-modal btn-modal-primary"
                @click="downloadDocument"
              >
                <i class="fas fa-download"></i> Download PDF
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
import brandingApi from "@/services/brandingApi";

const props = defineProps({
  lead: {
    type: Object,
    required: true,
  },
});

const emit = defineEmits(["close", "updated"]);

const leadsStore = useLeadsStore();

// Editable fields
const editableFields = [
  { key: "firstName", label: "First Name", editable: true },
  { key: "lastName", label: "Last Name", editable: true },
  { key: "email", label: "Email Address", editable: true, type: "email" },
  { key: "phone", label: "Phone Number", editable: true },
  { key: "country", label: "Country of Residence", editable: true },
];

// Edit data
const editData = reactive({
  firstName: props.lead.firstName,
  lastName: props.lead.lastName,
  email: props.lead.email,
  phone: props.lead.phone,
  country: props.lead.country,
});

// Original data for comparison
const originalData = {
  firstName: props.lead.firstName,
  lastName: props.lead.lastName,
  email: props.lead.email,
  phone: props.lead.phone,
  country: props.lead.country,
};

// Sales assignment
const salesRep = ref("");
const assignmentNotes = ref("");
const assignmentStatus = ref("Unassigned");
const assignedTo = ref("None");
const assignedDate = ref("N/A");

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
      alert("✓ Changes saved successfully!");
      emit("updated");
    } else {
      alert("❌ Failed to save changes: " + response.message);
    }
  } catch (error) {
    alert("❌ Error saving changes: " + error.message);
  }
};

// Assign to sales
const assignToSales = () => {
  if (!salesRep.value) {
    alert("⚠️ Please select a sales representative before assigning.");
    return;
  }

  const today = new Date();
  const dateString = today.toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });

  assignmentStatus.value = "Assigned";
  assignedTo.value = salesRep.value
    .replace("-", " ")
    .replace(/\b\w/g, (l) => l.toUpperCase());
  assignedDate.value = dateString;

  alert(
    `✓ Lead successfully assigned!\n\nAssigned to: ${assignedTo.value}\nDate: ${dateString}`,
  );
};

// Format date
const formatDate = (dateString) => {
  if (!dateString) return "N/A";
  const date = new Date(dateString);
  return date.toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
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
    terms_of_service: "Terms of Service",
    privacy_policy: "Privacy Policy",
    risk_disclosure: "Risk Disclosure",
  };
  return titleMap[type] || type;
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
    alert("Please allow popups to download the PDF");
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
          border-bottom: 1px solid var(--color-brand);
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
          border: 1px solid var(--color-warning-border, var(--color-warning));
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
          border-top: 1px solid var(--color-border);
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

.detail-content {
  padding: 30px;
}

.detail-sections {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 30px;
  margin-bottom: 30px;
}

.detail-section {
  background: var(--color-surface);
  border-radius: var(--radius-md);
  padding: 25px;
  border: 1px solid var(--color-border);
}

.detail-section h3 {
  font-size: 16px;
  color: var(--color-ink);
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 1px solid var(--color-border);
  display: flex;
  align-items: center;
  gap: 10px;
}

.detail-section h3 i {
  color: var(--color-brand);
}

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
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
  border-bottom: 1px solid var(--color-border);
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
}

.detail-value {
  color: var(--color-ink);
  font-size: 14px;
  font-weight: 500;
  text-align: right;
  padding: 4px 8px;
  border-radius: 4px;
  min-width: 150px;
}

.detail-value.editable {
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  transition: all 0.3s ease;
}

.detail-value.editable:focus {
  border-color: var(--color-brand);
  box-shadow: none;
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
  background: var(--color-surface);
  border-radius: var(--radius-md);
  padding: 25px;
  border: 1px solid var(--color-border);
  margin-bottom: 30px;
}

.documents-section h3 {
  font-size: 16px;
  color: var(--color-ink);
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 1px solid var(--color-border);
  display: flex;
  align-items: center;
  gap: 10px;
}

.documents-section h3 i {
  color: var(--color-brand);
}

.documents-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 15px;
}

.document-card-mini {
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 15px;
  transition: all 0.3s ease;
  cursor: pointer;
}

.document-card-mini:hover {
  border-color: var(--color-brand);
  background: var(--color-brand-soft);
}

.document-card-header-mini {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
}

.document-icon-mini {
  width: 36px;
  height: 36px;
  background: var(--color-brand-solid);
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  color: white;
  flex-shrink: 0;
}

.document-title-mini {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
}

.document-meta-mini {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 14px;
}

.document-date-mini {
  color: var(--color-muted);
}

.document-status-mini {
  background: var(--color-success-soft);
  color: var(--color-success);
  padding: 2px 8px;
  border-radius: var(--radius-md);
  font-weight: 600;
  text-transform: uppercase;
  font-size: 14px;
}

.document-signature-info {
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid var(--color-border);
}

.signature-line {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: var(--color-muted);
  margin: 6px 0;
}

.signature-line i {
  color: var(--color-faint);
  width: 14px;
  /* @font-floor-exempt: visual-only signature glyph */
  font-size: 10px;
}

.signature-line:first-child {
  font-weight: 600;
  color: var(--color-text);
}

.btn-view-doc {
  width: 100%;
  padding: 8px 12px;
  margin-top: 12px;
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
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.btn-view-doc:hover {
  background: var(--color-brand-solid);
  color: white;
  transform: translateY(-1px);
}

.no-documents {
  grid-column: 1 / -1;
  text-align: center;
  padding: 40px;
  color: var(--color-faint);
  font-style: italic;
}

/* Sales Assignment Section */
.sales-assignment-section {
  background: var(--color-surface);
  border-radius: var(--radius-md);
  padding: 25px;
  border: 1px solid var(--color-border);
}

.sales-assignment-section h3 {
  font-size: 16px;
  color: var(--color-ink);
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 1px solid var(--color-border);
}

.sales-assignment-section h3 i {
  color: var(--color-brand);
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
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;

  transition: all 0.3s ease;
  background: var(--color-surface);
}

.assignment-field select:focus,
.assignment-field input:focus,
.assignment-field textarea:focus {
  border-color: var(--color-brand);
  box-shadow: none;
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
  border: 1px solid var(--color-border);
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
  border: 1px solid var(--color-warning-border, var(--color-warning));
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
  color: var(--color-warning);
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
  .detail-sections {
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
