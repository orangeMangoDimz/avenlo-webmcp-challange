<template>
  <div class="file-upload-wrapper">
    <div
      class="file-upload-area"
      :class="{ dragover: isDragging }"
      @click="triggerFileInput"
      @dragover.prevent="isDragging = true"
      @dragleave.prevent="isDragging = false"
      @drop.prevent="handleDrop"
    >
      <input
        ref="fileInput"
        type="file"
        accept=".jpg,.jpeg,.png,.pdf"
        multiple
        style="display: none"
        @change="handleFileSelect"
      />
      <div class="file-upload-icon">
        <i class="fas fa-cloud-upload-alt"></i>
      </div>
      <div class="file-upload-text">
        {{ t("fileUploadClickDrag", "Click to upload or drag and drop") }}
      </div>
      <div class="file-upload-hint">
        {{ t("fileUploadHint", "JPG, PNG or PDF (max. 5MB per file)") }}
      </div>
      <div
        v-if="documentTypes && documentTypes.length > 0"
        class="file-upload-types"
      >
        <strong>{{
          t("fileUploadAcceptedDocs", "Accepted documents:")
        }}</strong>
        <span v-for="(docType, idx) in documentTypes" :key="idx">
          {{ docType.documentDisplayName
          }}<span v-if="idx < documentTypes.length - 1">, </span>
        </span>
      </div>
    </div>

    <!-- File List -->
    <div v-if="files.length > 0" class="file-list">
      <div v-for="(file, idx) in files" :key="file.id" class="file-item">
        <div class="file-info">
          <div class="file-icon">
            <i class="fas fa-file-alt"></i>
          </div>
          <div class="file-details">
            <div class="file-name">{{ file.name }}</div>
            <div class="file-size">{{ formatFileSize(file.size) }}</div>
            <div v-if="file.uploading" class="file-status uploading">
              <i class="fas fa-spinner fa-spin"></i> Uploading...
            </div>
            <div v-else-if="file.uploaded" class="file-status success">
              <i class="fas fa-check-circle"></i> Uploaded
            </div>
            <div v-else-if="file.error" class="file-status error">
              <i class="fas fa-exclamation-circle"></i> {{ file.error }}
            </div>
          </div>
        </div>
        <button
          type="button"
          class="file-remove"
          @click="removeFile(idx)"
          :disabled="file.uploading"
        >
          <i class="fas fa-times"></i>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from "vue";
import { useLanguageStore } from "@/stores/language";
import withdrawalSubmissionService from "@/services/withdrawalSubmissionService";

const languageStore = useLanguageStore();
const t = (key, fallback) => languageStore.t(key, fallback);

const props = defineProps({
  itemIndex: {
    type: Number,
    required: true,
  },
  submissionId: {
    type: Number,
    required: true,
  },
  required: {
    type: Boolean,
    default: false,
  },
  documentTypes: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(["files-changed"]);

const fileInput = ref(null);
const files = ref([]);
const isDragging = ref(false);

const basename = (path = "") => {
  const normalizedPath = String(path || "").split("?")[0];
  const parts = normalizedPath.split("/");
  return parts[parts.length - 1] || "";
};

const normalizeFilePayload = (file = {}) => {
  const filePath =
    file.filePath ||
    file.s3Key ||
    file.path ||
    file.url ||
    file.s3Url ||
    file.downloadUrl ||
    null;
  const fileName =
    file.fileName || file.filename || file.name || basename(filePath);
  const fileSize = Number(file.fileSize ?? file.size ?? 0) || 0;

  return {
    fileName,
    fileSize,
    filePath,
    downloadUrl: file.downloadUrl || file.url || file.s3Url || filePath || null,
    s3Url: file.s3Url || file.url || null,
    s3Key: file.s3Key || null,
    mimeType: file.mimeType || file.type || null,
    uploadedAt: file.uploadedAt || null,
  };
};

const triggerFileInput = () => {
  fileInput.value?.click();
};

const handleFileSelect = (event) => {
  const selectedFiles = Array.from(event.target.files);
  addFiles(selectedFiles);
  // Reset input
  event.target.value = "";
};

const handleDrop = (event) => {
  isDragging.value = false;
  const droppedFiles = Array.from(event.dataTransfer.files);
  addFiles(droppedFiles);
};

const addFiles = async (newFiles) => {
  for (const file of newFiles) {
    // Validate file size (5MB max)
    if (file.size > 5 * 1024 * 1024) {
      alert(`File "${file.name}" is too large. Maximum size is 5MB.`);
      continue;
    }

    // Validate file type
    const validTypes = ["image/jpeg", "image/png", "application/pdf"];
    if (!validTypes.includes(file.type)) {
      alert(
        `File "${file.name}" has an invalid format. Please upload JPG, PNG, or PDF files.`,
      );
      continue;
    }

    const fileData = {
      id: Date.now() + Math.random(),
      file,
      name: file.name,
      size: file.size,
      uploading: false,
      uploaded: false,
      error: null,
      url: null,
    };

    files.value.push(fileData);

    uploadFile(fileData);
  }

  emitFilesChanged();
};

const uploadFile = async (fileData) => {
  const fileIndex = files.value.findIndex((item) => item.id === fileData.id);
  if (fileIndex === -1) return;

  files.value[fileIndex] = {
    ...files.value[fileIndex],
    uploading: true,
    uploaded: false,
    error: null,
  };

  try {
    const response = await withdrawalSubmissionService.uploadResubmitFile(
      props.submissionId,
      props.itemIndex,
      fileData.file,
    );

    if (response.success && response.data) {
      const uploaded = normalizeFilePayload(response.data);
      files.value[fileIndex] = {
        ...files.value[fileIndex],
        name: uploaded.fileName || fileData.name,
        fileName: uploaded.fileName || fileData.name,
        size: uploaded.fileSize || fileData.size,
        uploading: false,
        uploaded: true,
        error: null,
        url: uploaded.downloadUrl || uploaded.s3Url || uploaded.filePath,
        downloadUrl: uploaded.downloadUrl,
        s3Url: uploaded.s3Url,
        filePath: uploaded.filePath,
        s3Key: uploaded.s3Key,
        mimeType: uploaded.mimeType,
        uploadedAt: uploaded.uploadedAt,
      };
    } else {
      files.value[fileIndex] = {
        ...files.value[fileIndex],
        uploading: false,
        uploaded: false,
        error: response.message || "Upload failed",
      };
    }
  } catch (error) {
    files.value[fileIndex] = {
      ...files.value[fileIndex],
      uploading: false,
      uploaded: false,
      error: error.message || "Upload failed",
    };
  }

  emitFilesChanged();
};

const removeFile = (index) => {
  files.value.splice(index, 1);
  emitFilesChanged();
};

const formatFileSize = (bytes) => {
  if (bytes === 0) return "0 Bytes";
  const k = 1024;
  const sizes = ["Bytes", "KB", "MB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + " " + sizes[i];
};

const emitFilesChanged = () => {
  emit(
    "files-changed",
    props.itemIndex,
    files.value
      .filter(
        (file) =>
          file.uploaded || file.downloadUrl || file.s3Url || file.filePath,
      )
      .map((file) => normalizeFilePayload(file)),
  );
};
</script>

<style scoped>
.file-upload-wrapper {
  width: 100%;
}

.file-upload-area {
  border: 2px dashed var(--color-border-strong);
  border-radius: var(--radius-md);
  padding: 40px 20px;
  text-align: center;
  background: var(--color-surface-soft);
  cursor: pointer;
  transition: all 0.3s ease;
}

.file-upload-area:hover {
  border-color: var(--color-brand);
  background: var(--color-surface-muted);
}

.file-upload-area.dragover {
  border-color: var(--color-brand);
  background: var(--color-brand-soft);
}

.file-upload-icon {
  font-size: 48px;
  color: var(--color-muted);
  margin-bottom: 12px;
}

.file-upload-text {
  font-size: 16px;
  font-weight: 500;
  color: var(--color-ink);
  margin-bottom: 8px;
}

.file-upload-hint {
  font-size: 14px;
  color: var(--color-muted);
  margin-bottom: 12px;
}

.file-upload-types {
  margin-top: 12px;
  font-size: 13px;
  color: var(--color-text);
}

.file-upload-types strong {
  color: var(--color-ink);
}

/* File List */
.file-list {
  margin-top: 20px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.file-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  background: white;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  transition: all 0.2s;
}

.file-item:hover {
  border-color: var(--color-border-strong);
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.file-info {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
}

.file-icon {
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-surface-muted);
  border-radius: var(--radius-md);
  color: var(--color-brand);
  font-size: 20px;
}

.file-details {
  flex: 1;
  min-width: 0;
}

.file-name {
  font-size: 14px;
  font-weight: 500;
  color: var(--color-ink);
  margin-bottom: 4px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.file-size {
  font-size: 12px;
  color: var(--color-muted);
  margin-bottom: 4px;
}

.file-status {
  font-size: 12px;
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 4px;
}

.file-status.uploading {
  color: var(--color-brand);
}

.file-status.success {
  color: var(--color-success);
}

.file-status.error {
  color: var(--color-danger);
}

.file-remove {
  padding: 8px;
  background: transparent;
  border: none;
  color: var(--color-danger);
  cursor: pointer;
  border-radius: 4px;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}

.file-remove:hover:not(:disabled) {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.file-remove:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
