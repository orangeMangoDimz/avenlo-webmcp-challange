<template>
  <div class="ib-document-card" :class="{ collapsed: isCollapsed }">
    <div class="ib-document-header" @click="toggleFromHeader">
      <div class="ib-document-title-section">
        <div
          class="ib-document-icon"
          :style="{ background: document.iconGradient || defaultGradient }"
        >
          <i :class="document.iconClass || 'fas fa-file-contract'"></i>
        </div>
        <input
          type="text"
          class="ib-document-title-input"
          v-model="localDocument.documentTitle"
          :placeholder="t('ibDocCard_placeholder_title')"
          :readonly="!canEditDocuments"
          @input="handleChange"
          @click.stop
        />
      </div>
      <div class="ib-document-actions" @click.stop>
        <div class="form-checkbox" @click.stop>
          <input
            type="checkbox"
            :id="`doc-required-${localDocument.id}`"
            v-model="localDocument.isRequired"
            :disabled="!canEditDocuments"
            @change="handleChange"
            @click.stop
            style="
              width: 18px;
              height: 18px;
              accent-color: var(--color-danger);
              cursor: pointer;
            "
          />
          <label
            :for="`doc-required-${localDocument.id}`"
            @click.stop
            style="
              font-size: 14px;
              color: var(--color-text);
              font-weight: 600;
              cursor: pointer;
            "
          >
            {{ t("ibDocCard_label_required") }}
          </label>
        </div>
        <button
          v-if="canEditDocuments"
          type="button"
          class="ib-doc-save-btn"
          :class="{ 'has-changes': hasChanges }"
          @click="saveDocument"
          :disabled="!hasChanges || saving"
          :title="
            hasChanges
              ? t('ibDocCard_saveHint_changed')
              : t('ibDocCard_saveHint_unchanged')
          "
        >
          <i class="fas" :class="saving ? 'fa-spinner fa-spin' : 'fa-save'"></i>
          {{ t("ibDocCard_btn_save") }}
        </button>
        <button
          v-if="showDuplicate"
          type="button"
          class="ib-doc-action-btn duplicate"
          @click="$emit('duplicate')"
          :title="t('ibDocCard_aria_duplicate')"
        >
          <i class="fas fa-copy"></i>
        </button>
        <button
          v-if="showDelete"
          type="button"
          class="ib-doc-action-btn delete"
          @click="$emit('delete')"
          :title="t('ibDocCard_aria_delete')"
        >
          <i class="fas fa-trash"></i>
        </button>
        <button
          type="button"
          class="ib-doc-toggle-btn"
          @click="toggleCollapse"
          :title="t('ibDocCard_aria_toggle')"
        >
          <i
            class="fas"
            :class="isCollapsed ? 'fa-chevron-down' : 'fa-chevron-up'"
          ></i>
        </button>
      </div>
    </div>

    <div class="ib-document-meta">
      <div class="ib-document-meta-item">
        <i class="fas fa-calendar-alt"></i>
        <span
          >{{ t("ibDocCard_meta_created") }}
          {{ formatDate(document.createdAt) }}</span
        >
      </div>
      <div class="ib-document-meta-item">
        <i class="fas fa-clock"></i>
        <span
          >{{ t("ibDocCard_meta_modified") }}
          {{ formatDate(document.updatedAt) }}</span
        >
      </div>
      <div class="ib-document-meta-item">
        <i class="fas fa-text-height"></i>
        <span class="doc-word-count">{{
          tParams("ibDocCard_wordCount", "≈{n} words", { n: stats.wordCount })
        }}</span>
      </div>
    </div>

    <div class="ib-document-body">
      <RichTextInput
        v-model="localDocument.documentContent"
        :disabled="!canEditDocuments"
        :placeholder="
          t('ibDocCard_placeholder_content', 'Write document content here...')
        "
      />
    </div>

    <div class="ib-document-footer">
      <div class="ib-document-stats">
        <div class="ib-document-stat">
          <i class="fas fa-file-alt"></i>
          <span class="doc-char-count">{{
            tParams("ibDocCard_charCount", "≈{n} characters", {
              n: stats.charCount,
            })
          }}</span>
        </div>
        <div class="ib-document-stat">
          <i class="fas fa-clock"></i>
          <span>{{
            tParams("ibDocCard_minRead", "{n} min read", { n: stats.readTime })
          }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch } from "vue";
import { useAdminI18n } from "@/composables/useAdminI18n";
import RichTextInput from "@/components/common/RichTextInput.vue";

const { t, tParams, languageStore } = useAdminI18n();

const props = defineProps({
  document: {
    type: Object,
    required: true,
  },
  saving: {
    type: Boolean,
    default: false,
  },
  canEditDocuments: {
    type: Boolean,
    default: true,
  },
  showDuplicate: {
    type: Boolean,
    default: true,
  },
  showDelete: {
    type: Boolean,
    default: true,
  },
});

const emit = defineEmits(["save", "duplicate", "delete"]);

const isCollapsed = ref(false);

// 初始化文档数据，确保数据类型正确
const initializeDocument = (doc) => {
  return {
    ...doc,
    isRequired: Boolean(doc.isRequired), // 转换为布尔值
  };
};

const localDocument = reactive(initializeDocument(props.document));
const originalDocument = reactive(initializeDocument(props.document));
const defaultGradient =
  "linear-gradient(135deg, var(--color-brand) 0%, var(--color-brand-strong) 100%)";

const hasChanges = computed(() => {
  return (
    localDocument.documentTitle !== originalDocument.documentTitle ||
    localDocument.documentContent !== originalDocument.documentContent ||
    localDocument.isRequired !== originalDocument.isRequired
  );
});

const stats = computed(() => {
  const content = localDocument.documentContent || "";
  const plainText = content.replace(/<[^>]*>/g, "");
  const charCount = plainText.length;
  const wordCount = plainText
    .trim()
    .split(/\s+/)
    .filter((w) => w.length > 0).length;
  const readTime = Math.max(1, Math.ceil(wordCount / 200));

  return {
    charCount,
    wordCount,
    readTime,
  };
});

const toggleCollapse = () => {
  isCollapsed.value = !isCollapsed.value;
};

const toggleFromHeader = () => {
  isCollapsed.value = !isCollapsed.value;
};

const handleChange = () => {
  // 触发变更检测
  console.log("Checkbox changed:", {
    id: localDocument.id,
    isRequired: localDocument.isRequired,
    hasChanges: hasChanges.value,
  });
};

const saveDocument = () => {
  if (!hasChanges.value || props.saving) return;

  emit("save", {
    id: localDocument.id,
    data: {
      documentTitle: localDocument.documentTitle,
      documentContent: localDocument.documentContent,
      isRequired: localDocument.isRequired ? 1 : 0,
    },
  });

  // 更新原始数据
  Object.assign(originalDocument, { ...localDocument });
};

const formatDate = (dateString) => {
  if (!dateString) return t("ibDocCard_date_na");
  const date = new Date(dateString);
  const today = new Date();
  const isToday = date.toDateString() === today.toDateString();

  if (isToday) {
    return t("ibDocCard_date_today");
  }

  const loc = languageStore.currentLanguage === "zh" ? "zh-CN" : "en-US";
  return date.toLocaleDateString(loc, {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
};

// 监听 props.document 变化时同步本地状态。RichTextInput 内部用 v-model + watch 自己
// 处理 contenteditable 的内容同步与光标位置，这里只负责更新 localDocument 数据。
watch(
  () => props.document,
  (newVal) => {
    const normalizedDoc = initializeDocument(newVal);
    Object.assign(localDocument, normalizedDoc);
    Object.assign(originalDocument, normalizedDoc);
  },
  { deep: true },
);

// 监听saving状态，成功后更新原始数据
watch(
  () => props.saving,
  (newVal, oldVal) => {
    if (oldVal && !newVal) {
      // 保存完成，更新原始数据
      Object.assign(originalDocument, { ...localDocument });
    }
  },
);
</script>

<style scoped>
.ib-document-card {
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-lg);
  margin-bottom: 20px;
  overflow: hidden;
  transition: all 0.3s ease;
  box-shadow: var(--shadow-sm);
}

.ib-document-card:hover {
  border-color: var(--color-border-strong);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
  transform: translateY(-2px);
}

.ib-document-header {
  background: var(--color-surface-soft);
  padding: 20px 25px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 15px;
  cursor: pointer;
}

.ib-document-header:hover {
  background: var(--color-surface-muted);
}

.ib-document-title-section {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 15px;
  min-width: 0;
}

.ib-document-icon {
  width: 48px;
  height: 48px;
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 20px;
  flex-shrink: 0;
  box-shadow: 0 2px 8px rgba(var(--color-brand-rgb), 0.3);
}

.ib-document-title-input {
  flex: 1;
  padding: 12px 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 16px;
  font-weight: 700;
  color: var(--color-ink);
  background: var(--color-surface);
  transition: all 0.3s ease;
}

.ib-document-title-input:focus {
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
  outline: none;
}

.ib-document-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-shrink: 0;
}

.form-checkbox {
  display: flex;
  align-items: center;
  gap: 8px;
}

.ib-doc-save-btn {
  padding: 8px 16px;
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  cursor: pointer;
  font-size: 14px;
  font-weight: 600;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--color-faint);
}

.ib-doc-save-btn:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

.ib-doc-save-btn.has-changes {
  background: var(--color-brand-solid);
  border-color: var(--color-brand);
  color: white;
  box-shadow: 0 2px 8px rgba(var(--color-brand-rgb), 0.3);
}

.ib-doc-save-btn.has-changes:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.4);
}

.ib-doc-action-btn {
  padding: 8px 14px;
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  cursor: pointer;
  font-size: 14px;
  font-weight: 600;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 6px;
}

.ib-doc-action-btn.duplicate {
  color: var(--color-success);
  border-color: var(--color-success);
}

.ib-doc-action-btn.duplicate:hover {
  background: var(--color-success-soft);
  transform: translateY(-1px);
}

.ib-doc-action-btn.delete {
  color: var(--color-danger);
  border-color: var(--color-danger);
}

.ib-doc-action-btn.delete:hover {
  background: var(--color-danger-soft);
  transform: translateY(-1px);
}

.ib-doc-toggle-btn {
  padding: 8px 12px;
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  cursor: pointer;
  font-size: 14px;
  font-weight: 600;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--color-text);
}

.ib-doc-toggle-btn:hover {
  background: var(--color-surface-soft);
  border-color: var(--color-border-strong);
  color: var(--color-brand);
}

.ib-document-meta {
  display: flex;
  align-items: center;
  gap: 20px;
  padding: 12px 25px;
  background: var(--color-surface-soft);
  border-bottom: 1px solid var(--color-border);
  font-size: 14px;
  color: var(--color-muted);
}

.ib-document-meta-item {
  display: flex;
  align-items: center;
  gap: 6px;
}

.ib-document-meta-item i {
  color: var(--color-faint);
}

.ib-document-body {
  padding: 25px;
}

.ib-document-footer {
  padding: 15px 25px;
  background: var(--color-surface-soft);
  border-top: 1px solid var(--color-border);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.ib-document-stats {
  display: flex;
  gap: 20px;
  font-size: 14px;
  color: var(--color-muted);
}

.ib-document-stat {
  display: flex;
  align-items: center;
  gap: 6px;
}

.ib-document-card.collapsed .ib-document-body,
.ib-document-card.collapsed .ib-document-meta,
.ib-document-card.collapsed .ib-document-footer {
  display: none;
}
</style>
