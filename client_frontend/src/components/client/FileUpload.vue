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
import { clientKycService } from "@/services/clientKycService";

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
});

const emit = defineEmits(["files-changed"]);

const fileInput = ref(null);
const files = ref([]);
const isDragging = ref(false);

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

    // Upload file immediately after adding to list
    uploadFile(fileData);
  }

  emitFilesChanged();
};

const uploadFile = async (fileData) => {
  // 找到文件在数组中的索引
  const fileIndex = files.value.findIndex((f) => f.id === fileData.id);
  if (fileIndex === -1) return;

  // 使用响应式更新
  files.value[fileIndex] = {
    ...files.value[fileIndex],
    uploading: true,
    uploaded: false,
    error: null,
  };

  try {
    console.log("Starting file upload for:", fileData.name);
    const response = await clientKycService.uploadFile(
      props.submissionId,
      props.questionId,
      fileData.file,
    );

    console.log("Upload response:", response);

    if (response.success && response.data) {
      // 成功上传，更新状态
      files.value[fileIndex] = {
        ...files.value[fileIndex],
        uploading: false,
        uploaded: true,
        url: response.data.url,
        error: null,
      };
      console.log("File uploaded successfully:", fileData.name);
    } else {
      // 上传失败
      files.value[fileIndex] = {
        ...files.value[fileIndex],
        uploading: false,
        uploaded: false,
        error: response.message || "Upload failed",
      };
      console.error("Upload failed:", response);
    }
  } catch (err) {
    console.error("Failed to upload file:", err);
    // 上传异常
    files.value[fileIndex] = {
      ...files.value[fileIndex],
      uploading: false,
      uploaded: false,
      error: err.message || "Upload failed",
    };
  }

  console.log("Final file state:", files.value[fileIndex]);
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
  emit("files-changed", props.questionId, files.value);
};

watch(
  files,
  () => {
    emitFilesChanged();
  },
  { deep: true },
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
  font-size: 14px;
  color: var(--color-faint);
}

.file-upload-types {
  margin-top: 15px;
  padding-top: 15px;
  border-top: 1px solid var(--color-border);
  font-size: 14px;
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
  font-size: 14px;
  color: var(--color-muted);
}

.file-status {
  font-size: 14px;
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
