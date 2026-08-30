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
              <i class="fas fa-spinner fa-spin"></i>
              {{ t("fileUploadUploading", "Uploading...") }}
            </div>
            <div v-else-if="file.uploaded" class="file-status success">
              <i class="fas fa-check-circle"></i>
              {{ t("fileUploadUploaded", "Uploaded") }}
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
import { ref, watch } from "vue";
import { useLanguageStore } from "@/stores/language";
import withdrawalSubmissionService from "@/services/withdrawalSubmissionService";

const languageStore = useLanguageStore();
const t = (key, fallback) => languageStore.t(key, fallback);

const props = defineProps({
  questionId: {
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
  existingFiles: {
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

const toDisplayFile = (file = {}) => {
  const normalized = normalizeFilePayload(file);

  return {
    id:
      file.id ||
      normalized.filePath ||
      normalized.fileName ||
      Date.now() + Math.random(),
    file: file.file || null,
    name: normalized.fileName,
    fileName: normalized.fileName,
    size: normalized.fileSize,
    uploading: false,
    uploaded: true,
    error: null,
    url: normalized.downloadUrl || normalized.s3Url || normalized.filePath,
    downloadUrl: normalized.downloadUrl,
    s3Url: normalized.s3Url,
    filePath: normalized.filePath,
    s3Key: normalized.s3Key,
    mimeType: normalized.mimeType,
    uploadedAt: normalized.uploadedAt,
  };
};

const getFileListSignature = (fileList = []) =>
  JSON.stringify(
    (Array.isArray(fileList) ? fileList : []).map((file) =>
      normalizeFilePayload(file),
    ),
  );

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
      alert(
        t("fileUploadTooLarge", "File is too large. Maximum size is 5MB.") +
          ' "' +
          file.name +
          '"',
      );
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

    files.value[files.value.length - 1] = {
      ...fileData,
      uploading: true,
    };

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
    const response = await withdrawalSubmissionService.uploadFile(
      props.submissionId,
      props.questionId,
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
    props.questionId,
    files.value
      .filter(
        (file) =>
          file.uploaded || file.downloadUrl || file.s3Url || file.filePath,
      )
      .map((file) => normalizeFilePayload(file)),
  );
};

watch(
  () => props.existingFiles,
  (newFiles) => {
    const nextFiles = Array.isArray(newFiles) ? newFiles : [];
    const nextSignature = getFileListSignature(nextFiles);
    const currentSignature = getFileListSignature(files.value);

    if (nextSignature === currentSignature) {
      return;
    }

    files.value = nextFiles.map((file) => toDisplayFile(file));
  },
  { immediate: true, deep: true },
);
</script>

<style scoped>
.file-upload-area {
  border: 1px dashed var(--color-border-strong);
  border-radius: var(--radius-md);
  padding: 30px;
  text-align: center;
  cursor: pointer;
  transition: all 0.3s ease;
}

.file-upload-area:hover {
  border-color: var(--color-brand);
  background: var(--color-surface-soft);
}

.file-upload-area.dragover {
  border-color: var(--color-brand);
  background: var(--color-brand-soft);
}

.file-upload-icon {
  font-size: 48px;
  color: var(--color-border-strong);
  margin-bottom: 15px;
}

.file-upload-text {
  font-size: 14px;
  color: var(--color-text);
  margin-bottom: 8px;
}

.file-upload-hint {
  font-size: 12px;
  color: var(--color-faint);
}

.file-upload-types {
  margin-top: 15px;
  padding-top: 15px;
  border-top: 1px solid var(--color-border);
  font-size: 13px;
  color: var(--color-muted);
}

.file-upload-types strong {
  display: block;
  margin-bottom: 5px;
  color: var(--color-text);
}

.file-list {
  margin-top: 15px;
}

.file-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  margin-bottom: 10px;
}

.file-info {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
}

.file-icon {
  font-size: 24px;
  color: var(--color-brand);
}

.file-details {
  flex: 1;
}

.file-name {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 2px;
}

.file-size {
  font-size: 12px;
  color: var(--color-muted);
}

.file-status {
  font-size: 12px;
  margin-top: 4px;
  display: flex;
  align-items: center;
  gap: 5px;
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
  background: var(--color-danger-soft);
  color: var(--color-danger);
  border: none;
  width: 32px;
  height: 32px;
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
}

.file-remove:hover:not(:disabled) {
  background: var(--color-danger-border);
  color: white;
}

.file-remove:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
