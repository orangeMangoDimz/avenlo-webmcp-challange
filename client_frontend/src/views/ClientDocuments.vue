<template>
  <div class="container ui-page">
    <!-- Documents Header -->
    <div class="documents-header">
      <div class="documents-header-left">
        <h2>{{ t("signedLegalDocuments", "Signed Legal Documents") }}</h2>
        <p>
          {{
            t(
              "documentsDescription",
              "These are the documents you agreed to during registration",
            )
          }}
        </p>
      </div>
      <div class="documents-count">
        <i class="fas fa-file-check"></i> {{ documentsCountLabel }}
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <i class="fas fa-spinner fa-spin"></i>
      <p>{{ t("loadingDocuments", "Loading documents...") }}</p>
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
            <div class="document-title">{{ getDocumentTitle(doc) }}</div>
            <div class="document-date">
              <i class="fas fa-calendar"></i>
              {{ t("signedOn", "Signed on") }} {{ formatDate(doc.signedAt) }}
            </div>
          </div>
        </div>

        <div class="document-meta">
          <div class="document-meta-item">
            <i class="fas fa-file-pdf"></i>
            <span>PDF</span>
          </div>
          <div class="document-meta-item">
            <i class="fas fa-tag"></i>
            <span>{{ getDocumentSourceLabel(doc) }}</span>
          </div>
          <div class="document-status signed">
            <i class="fas fa-check-circle"></i>
            {{ t("signed", "Signed") }}
          </div>
        </div>

        <div class="document-actions">
          <button class="btn-doc btn-view" @click.stop="viewDocument(doc)">
            <i class="fas fa-eye"></i> {{ t("view", "View") }}
          </button>
          <button
            class="btn-doc btn-download"
            @click.stop="downloadDocument(doc)"
          >
            <i class="fas fa-download"></i> {{ t("download", "Download") }}
          </button>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="documents.length === 0" class="empty-state">
        <i class="fas fa-file-alt"></i>
        <p>{{ t("noDocumentsFound", "No documents found") }}</p>
      </div>
    </div>

    <!-- Info Box -->
    <div class="info-box">
      <h3>
        <i class="fas fa-info-circle"></i>
        {{ t("aboutYourDocuments", "About Your Documents") }}
      </h3>
      <p>
        {{
          t(
            "documentsInfoText",
            "All documents displayed here were signed electronically during your account registration. Each document includes your digital signature with your full name and email address. These documents are legally binding and are stored securely in PDF format. You can view or download them at any time. If you have any questions about these documents, please contact our support team.",
          )
        }}
      </p>
    </div>

    <!-- Document Viewer Modal -->
    <div v-if="showDocumentModal" class="modal" @click="closeDocumentModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h2>
            <i class="fas fa-file-alt"></i>
            {{
              currentDocument
                ? getDocumentTitle(currentDocument)
                : t("documentPreview", "Document Preview")
            }}
          </h2>
          <span class="close" @click="closeDocumentModal">&times;</span>
        </div>

        <div class="modal-body">
          <div class="document-preview">
            <h3>
              {{ currentDocument ? getDocumentTitle(currentDocument) : "" }}
            </h3>
            <div
              class="document-preview-content"
              v-html="getDocumentContent(currentDocument)"
            ></div>
          </div>

          <!-- Signature Section -->
          <div class="document-signature">
            <h4>
              <i class="fas fa-signature"></i>
              {{ t("digitalSignature", "Digital Signature") }}
            </h4>
            <div class="signature-info-row">
              <div class="signature-field">
                <label>{{ t("fullName", "Full Name") }}</label>
                <div class="signature-value">
                  {{ user.firstName }} {{ user.lastName }}
                </div>
              </div>
              <div class="signature-field">
                <label>{{ t("emailAddress", "Email Address") }}</label>
                <div class="signature-value">{{ user.email }}</div>
              </div>
              <div class="signature-field">
                <label>{{ t("clientId", "Client ID") }}</label>
                <div class="signature-value">{{ user.id }}</div>
              </div>
              <div class="signature-field">
                <label>{{ t("dateTimeSigned", "Date & Time Signed") }}</label>
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
            <i class="fas fa-times"></i> {{ t("close", "Close") }}
          </button>
          <button
            class="btn-modal btn-modal-primary"
            @click="downloadCurrentDocument"
          >
            <i class="fas fa-download"></i>
            {{ t("downloadPDF", "Download PDF") }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useClientAuthStore } from "@/stores/clientAuth";
import { useLanguageStore } from "@/stores/language";
import clientAuthService from "@/services/clientAuthService";
import { clientKycService } from "@/services/clientKycService";
import brandingApi from "@/services/brandingApi";
import { formatNumber } from "@/utils/helpers";

const clientAuthStore = useClientAuthStore();
const languageStore = useLanguageStore();

// Translation function
const t = (key, fallback = "") => languageStore.t(key, fallback);

const loading = ref(true);
const documents = ref([]);
const showDocumentModal = ref(false);
const currentDocument = ref(null);

// 文件数量展示：英文要区分单复数；中文不需要，统一用 {n} 个文件
const documentsCountLabel = computed(() => {
  const n = documents.value.length;
  const template =
    n === 1
      ? t("documentsCount_one", "{n} Document")
      : t("documentsCount_many", "{n} Documents");
  return template.replace("{n}", formatNumber(n));
});

// Branding configuration
const branding = ref({
  companyName: "Trading Platform",
  copyrightText: "Trading Platform",
});

// Get user data from store
const user = computed(
  () =>
    clientAuthStore.user || {
      id: "N/A",
      firstName: "Unknown",
      lastName: "User",
      email: "N/A",
    },
);

onMounted(async () => {
  // Load branding configuration
  try {
    const config = await brandingApi.getBranding();
    branding.value = {
      companyName: config.companyName || "Trading Platform",
      copyrightText: config.copyrightText || "Trading Platform",
    };
  } catch (error) {
    console.error("Failed to load branding:", error);
  }

  try {
    loading.value = true;

    // Fetch both registration and KYC documents
    const [registrationDocsResponse, kycDocsResponse] = await Promise.all([
      clientAuthService.getMyDocuments(),
      clientKycService.getMySignedDocuments(),
    ]);

    // my-documents 同时返回注册法律文档与 IB 确认文档，须拆开后按：注册 → KYC → IB
    const fromAuth =
      registrationDocsResponse.success &&
      Array.isArray(registrationDocsResponse.data)
        ? registrationDocsResponse.data
        : [];
    const registrationOnly = fromAuth.filter(
      (d) => d.documentType !== "ib_agreement",
    );
    const ibOnly = fromAuth.filter((d) => d.documentType === "ib_agreement");
    const kycOnly =
      kycDocsResponse.success && Array.isArray(kycDocsResponse.data)
        ? kycDocsResponse.data
        : [];

    const bySignedAt = (a, b) => {
      const ta = a.signedAt ? new Date(a.signedAt).getTime() : 0;
      const tb = b.signedAt ? new Date(b.signedAt).getTime() : 0;
      return ta - tb;
    };
    registrationOnly.sort(bySignedAt);
    kycOnly.sort(bySignedAt);
    ibOnly.sort(bySignedAt);

    documents.value = [...registrationOnly, ...kycOnly, ...ibOnly];
  } catch (error) {
    console.error("Error loading documents:", error);
    // Keep empty documents array
  } finally {
    loading.value = false;
  }
});

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

/** 与后台 Client List Signed Documents 一致：Registration / KYC / IB Program */
const getDocumentSourceLabel = (doc) => {
  const type = doc?.documentType;
  if (type === "kyc_document") return t("docSourceKyc", "KYC");
  if (type === "ib_agreement") return t("docSourceIb", "IB Program");
  return t("docSourceRegistration", "Registration");
};

const getDocumentTitle = (doc) => {
  // 如果文档有 title 字段，直接使用
  if (doc && doc.title) {
    return doc.title;
  }

  // 否则根据 documentType 映射
  const type = doc?.documentType;
  const titleMap = {
    terms_of_service: "Terms of Service",
    privacy_policy: "Privacy Policy",
    risk_disclosure: "Risk Disclosure",
    kyc_document: doc?.title || "KYC Document",
    ib_agreement: doc?.title || "IB Agreement",
  };
  return titleMap[type] || type || "Document";
};

const formatDate = (dateString) => {
  if (!dateString) return "N/A";
  const date = new Date(dateString);
  return date.toLocaleDateString("en-US", {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
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

const getDocumentContent = (doc) => {
  if (doc && doc.content) {
    return doc.content;
  }
  return "<p>Document content not available.</p>";
};

const downloadDocument = (doc) => {
  if (!doc) {
    doc = currentDocument.value;
  }
  if (!doc) return;

  const title = getDocumentTitle(doc);
  const content = doc.content;

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
      <title>${title} - ${user.value.firstName} ${user.value.lastName}</title>
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
          background: #fff9e6;
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
            <div class="value">${user.value.firstName} ${user.value.lastName}</div>
          </div>
          <div class="signature-item">
            <label>Email Address</label>
            <div class="value">${user.value.email}</div>
          </div>
          <div class="signature-item">
            <label>Client ID</label>
            <div class="value">${user.value.id}</div>
          </div>
          <div class="signature-item">
            <label>Date & Time Signed</label>
            <div class="value">${formatDate(doc.signedAt)}</div>
          </div>
        </div>
      </div>

      <div class="footer">
        <p>This is a digitally signed legal document.</p>
        <p>Generated on ${new Date().toLocaleDateString("en-US", { year: "numeric", month: "long", day: "numeric" })}</p>
        <p>© ${branding.value.copyrightText}. All rights reserved.</p>
      </div>

      <div class="no-print" style="margin-top: 30px; text-align: center;">
        <button onclick="window.print()" style="padding: 12px 30px; background: var(--color-brand); color: white; border: none; border-radius: var(--radius-md); font-size: 16px; font-weight: 600; cursor: pointer; margin-right: 10px;">
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

  printWindow.onload = () => {
    setTimeout(() => {
      printWindow.focus();
    }, 250);
  };
};

const downloadCurrentDocument = () => {
  downloadDocument(currentDocument.value);
};
</script>

<style scoped>
.container {
  max-width: 1200px;
  margin: 0 auto;
}

/* Documents Header */
.documents-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 2px solid var(--color-border);
}

.documents-header-left h2 {
  font-size: 20px;
  color: var(--color-ink);
  margin-bottom: 5px;
}

.documents-header-left p {
  font-size: 14px;
  color: var(--color-muted);
}

.documents-count {
  background: var(--color-brand-soft);
  color: var(--color-brand);
  padding: 8px 16px;
  border-radius: 20px;
  font-size: 14px;
  font-weight: 600;
}

/* Loading State */
.loading-state {
  text-align: center;
  padding: 60px 20px;
  color: var(--color-muted);
}

.loading-state i {
  font-size: 48px;
  margin-bottom: 20px;
  color: var(--color-brand);
}

.loading-state p {
  font-size: 16px;
}

/* Documents Grid */
.documents-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 25px;
  margin-bottom: 30px;
}

.document-card {
  background: white;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: 25px;
  transition:
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease;
  cursor: pointer;
}

.document-card:hover {
  border-color: var(--color-brand);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.15);
  transform: translateY(-2px);
}

.document-card-header {
  display: flex;
  align-items: start;
  gap: 15px;
  margin-bottom: 20px;
}

.document-icon {
  width: 50px;
  height: 50px;
  background: var(--color-brand);
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  color: white;
  flex-shrink: 0;
}

.document-info {
  flex: 1;
  min-width: 0;
}

.document-title {
  font-size: 18px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 5px;
  white-space: normal;
  word-wrap: break-word;
  overflow-wrap: break-word;
  line-height: 1.35;
}

.document-date {
  font-size: 13px;
  color: var(--color-muted);
  display: flex;
  align-items: center;
  gap: 5px;
}

.document-date i {
  font-size: 12px;
}

.document-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 20px;
}

.document-meta-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: var(--color-text);
  padding: 6px 12px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-sm);
}

.document-meta-item i {
  color: var(--color-brand);
  font-size: 14px;
}

.document-status {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: var(--radius-sm);
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
}

.document-status.signed {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.document-status i {
  font-size: 12px;
}

.document-actions {
  display: flex;
  gap: 10px;
}

.btn-doc {
  flex: 1;
  padding: 10px 16px;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition:
    background-color 0.3s ease,
    color 0.3s ease;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.btn-view {
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.btn-view:hover {
  background: var(--color-brand);
  color: white;
}

.btn-download {
  background: var(--color-border);
  color: var(--color-text);
}

.btn-download:hover {
  background: var(--color-border-strong);
}

/* Empty State */
.empty-state {
  grid-column: 1 / -1;
  text-align: center;
  padding: 60px 20px;
  color: var(--color-faint);
}

.empty-state i {
  font-size: 64px;
  margin-bottom: 20px;
  display: block;
}

.empty-state p {
  font-size: 16px;
  font-style: italic;
}

/* Info Box */
.info-box {
  background: var(--color-brand-soft);
  border-left: 4px solid var(--color-brand);
  padding: 20px;
  border-radius: var(--radius-md);
  margin-top: 30px;
}

.info-box h3 {
  font-size: 16px;
  color: var(--color-ink);
  margin-bottom: 10px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.info-box h3 i {
  color: var(--color-brand);
}

.info-box p {
  color: var(--color-text);
  font-size: 14px;
  line-height: 1.6;
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
  background-color: #ffffff;
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
  background: var(--color-brand);
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
  background: white;
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
  background: #fff9e6;
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
  font-size: 12px;
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
  transition:
    background-color 0.3s ease,
    color 0.3s ease;
  border: none;
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-modal-primary {
  background: var(--color-brand);
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
  .documents-grid {
    grid-template-columns: 1fr;
  }

  .documents-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 15px;
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
