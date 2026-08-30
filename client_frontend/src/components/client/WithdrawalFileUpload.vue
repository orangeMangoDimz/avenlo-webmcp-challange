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
        :accept="acceptTypes"
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
            <div class="file-name">{{ file.name || file.fileName }}</div>
            <div v-if="file.size" class="file-size">
              {{ formatFileSize(file.size) }}
            </div>
            <div v-if="file.uploading" class="file-status uploading">
              <i class="fas fa-spinner fa-spin"></i>
              {{ t("fileUploadUploading", "Uploading...") }}
            </div>
            <div
              v-else-if="file.uploaded || file.downloadUrl"
              class="file-status success"
            >
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
import { ref, computed, onMounted } from "vue";
import { useLanguageStore } from "@/stores/language";
import withdrawalService from "@/services/withdrawalService";

const languageStore = useLanguageStore();
const t = (key, fallback) => languageStore.t(key, fallback);

const props = defineProps({
  itemId: {
    type: Number,
    required: true,
  },
  withdrawalId: {
    type: [String, Number],
    required: true,
  },
  required: {
    type: Boolean,
    default: false,
  },
  acceptedFileTypes: {
    type: Array,
    default: () => [],
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

const acceptTypes = computed(() => {
  return ".jpg,.jpeg,.png,.pdf";
});

// 加载现有文件
onMounted(() => {
  if (props.existingFiles && props.existingFiles.length > 0) {
    files.value = props.existingFiles.map((file) => ({
      id: file.id || Date.now() + Math.random(),
      name: file.fileName || file.name,
      fileName: file.fileName || file.name,
      size: file.size || 0,
      uploading: false,
      uploaded: true,
      error: null,
      url: file.downloadUrl || file.s3Url || file.url,
      downloadUrl: file.downloadUrl,
      s3Url: file.s3Url,
      filePath: file.filePath,
    }));
    emitFilesChanged();
  }
});

const triggerFileInput = () => {
  fileInput.value?.click();
};

const handleFileSelect = (event) => {
  const selectedFiles = Array.from(event.target.files);
  addFiles(selectedFiles);
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
      fileName: file.name,
      size: file.size,
      uploading: false,
      uploaded: false,
      error: null,
      url: null,
    };

    files.value.push(fileData);

    // Upload file immediately after adding to list
    uploadFile(fileData);
  }

  emitFilesChanged();
};

const uploadFile = async (fileData) => {
  const fileIndex = files.value.findIndex((f) => f.id === fileData.id);
  if (fileIndex === -1) return;

  files.value[fileIndex] = {
    ...files.value[fileIndex],
    uploading: true,
    uploaded: false,
    error: null,
  };

  try {
    const response = await withdrawalService.uploadDocument(
      props.withdrawalId,
      props.itemId,
      fileData.file,
    );

    if (response.success && response.data) {
      files.value[fileIndex] = {
        ...files.value[fileIndex],
        uploading: false,
        uploaded: true,
        url: response.data.url,
        downloadUrl: response.data.url,
        s3Url: response.data.s3Url,
        filePath: response.data.s3Key,
        fileInfo: response.data.fileInfo,
      };
    } else {
      files.value[fileIndex] = {
        ...files.value[fileIndex],
        uploading: false,
        uploaded: false,
        error: response.message || "Upload failed",
      };
    }
  } catch (err) {
    files.value[fileIndex] = {
      ...files.value[fileIndex],
      uploading: false,
      uploaded: false,
      error: err.message || "Upload failed",
    };
  }

  emitFilesChanged();
};

const removeFile = (index) => {
  files.value.splice(index, 1);
  emitFilesChanged();
};

const formatFileSize = (bytes) => {
  if (!bytes || bytes === 0) return "0 Bytes";
  const k = 1024;
  const sizes = ["Bytes", "KB", "MB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + " " + sizes[i];
};

const emitFilesChanged = () => {
  // 发送文件信息数组
  const fileInfos = files.value
    .filter((f) => f.uploaded || f.downloadUrl)
    .map((f) => ({
      fileName: f.fileName || f.name,
      filePath: f.filePath,
      downloadUrl: f.downloadUrl || f.url,
      s3Url: f.s3Url,
      uploadedAt: f.fileInfo?.uploadedAt || new Date().toISOString(),
    }));

  emit("files-changed", fileInfos);
};
</script>

<style scoped>
.file-upload-wrapper {
  margin-top: 12px;
}

.file-upload-area {
  border: 1px dashed var(--color-border-strong);
  border-radius: var(--radius-md);
  padding: 40px 20px;
  text-align: center;
  cursor: pointer;
  transition: all 0.2s;
  background: var(--color-surface-soft);
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
  color: var(--color-brand);
  margin-bottom: 16px;
}

.file-upload-text {
  font-size: 16px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 8px;
}

.file-upload-hint {
  font-size: 14px;
  color: var(--color-muted);
}

.file-upload-types {
  margin-top: 12px;
  font-size: 13px;
  color: var(--color-text);
}

.file-upload-types strong {
  color: var(--color-ink);
}

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
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  transition: all 0.2s;
}

.file-item:hover {
  border-color: var(--color-border-strong);
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
  margin-bottom: 4px;
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
  border: none;
  border-radius: var(--radius-sm);
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: var(--color-danger);
  transition: all 0.2s;
}

.file-remove:hover:not(:disabled) {
  background: var(--color-danger-border);
}

.file-remove:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
