<template>
  <div class="document-list">
    <div class="document-required-toggle">
      <span class="toggle-label">{{ t("kycTplDoc_toggle_label") }}</span>
      <div
        class="toggle-switch"
        :class="{
          active: isEnabled,
          disabled: !hasRequireLegalDocumentPermission,
        }"
        @click="hasRequireLegalDocumentPermission ? toggleRequirement() : null"
      ></div>
    </div>

    <div v-if="isEnabled" class="documents-list-container">
      <div v-if="localDocuments.length === 0" class="empty-state">
        <i class="fas fa-file-signature"></i>
        <p>{{ t("kycTplDoc_empty") }}</p>
      </div>

      <div
        v-for="(doc, idx) in localDocuments"
        :key="idx"
        class="legal-document-item"
      >
        <div class="document-header">
          <input
            type="text"
            class="document-title-input"
            v-model="doc.documentTitle"
            :placeholder="t('kycTplDoc_placeholder_title')"
          />
          <div class="document-actions">
            <button
              v-if="
                doc.id
                  ? hasEditLegalDocumentPermission
                  : hasAddLegalDocumentPermission
              "
              type="button"
              class="btn-small btn-save"
              @click="saveDocument(idx)"
            >
              <i class="fas fa-save"></i>
              {{ doc.id ? t("kycTplDoc_btn_update") : t("kycTplDoc_btn_save") }}
            </button>
            <button
              v-if="hasDeleteLegalDocumentPermission"
              type="button"
              class="btn-small btn-delete"
              @click="removeDocument(idx)"
            >
              <i class="fas fa-trash"></i> {{ t("kycTplDoc_btn_remove") }}
            </button>
          </div>
        </div>
        <div class="editor-toolbar">
          <button
            type="button"
            class="editor-btn"
            @click="formatContent(idx, 'bold')"
          >
            <strong>B</strong>
          </button>
          <button
            type="button"
            class="editor-btn"
            @click="formatContent(idx, 'italic')"
          >
            <em>I</em>
          </button>
          <button
            type="button"
            class="editor-btn"
            @click="formatContent(idx, 'underline')"
          >
            <u>U</u>
          </button>
          <button
            type="button"
            class="editor-btn"
            @click="formatContent(idx, 'insertUnorderedList')"
          >
            <i class="fas fa-list-ul"></i>
          </button>
          <button
            type="button"
            class="editor-btn"
            @click="formatContent(idx, 'insertOrderedList')"
          >
            <i class="fas fa-list-ol"></i>
          </button>
        </div>
        <div
          class="document-editor"
          :contenteditable="true"
          :ref="(el) => setEditorRef(el, idx)"
          @input="updateContent(idx, $event)"
        ></div>
      </div>

      <button
        v-if="hasAddLegalDocumentPermission"
        type="button"
        class="btn btn-small btn-add-document"
        @click="addDocument"
      >
        <i class="fas fa-plus"></i> {{ t("kycTplDoc_btn_add") }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, nextTick } from "vue";
import {
  kycDocumentService,
  kycTemplateService,
} from "@/services/kycTemplateService";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t, tParams } = useAdminI18n();

const props = defineProps({
  templateId: {
    type: Number,
    required: true,
  },
  documents: {
    type: Array,
    default: () => [],
  },
  requireDocumentSignature: {
    type: [Number, Boolean],
    default: 1,
  },
  hasRequireLegalDocumentPermission: {
    type: Boolean,
    default: false,
  },
  hasAddLegalDocumentPermission: {
    type: Boolean,
    default: false,
  },
  hasEditLegalDocumentPermission: {
    type: Boolean,
    default: false,
  },
  hasDeleteLegalDocumentPermission: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["refresh"]);

const isEnabled = ref(!!props.requireDocumentSignature);
const localDocuments = ref([]);

// 编辑器引用
const editorRefs = ref(new Map());

// 初始化文档数据，确保格式正确
const initializeDocuments = (docs) => {
  const defaultContent = t("kycTplDoc_editorPlaceholder");
  return docs.map((doc) => ({
    id: doc.id || null,
    documentTitle: doc.documentTitle || doc.title || "",
    documentContent: doc.documentContent || doc.content || defaultContent,
    displayOrder: doc.displayOrder || 0,
    isActive: doc.isActive !== undefined ? doc.isActive : 1,
  }));
};

// 初始化
localDocuments.value = initializeDocuments(props.documents);

// 组件挂载后初始化编辑器内容
onMounted(() => {
  initializeEditorContent();
});

watch(
  () => props.documents,
  (newDocs) => {
    localDocuments.value = initializeDocuments(newDocs);
    // 重新初始化编辑器内容
    initializeEditorContent();
  },
);

watch(
  () => props.requireDocumentSignature,
  (newValue) => {
    isEnabled.value = !!newValue;
  },
);

const toggleRequirement = async () => {
  if (!props.hasRequireLegalDocumentPermission) {
    return;
  }

  const newValue = !isEnabled.value;

  if (
    !confirm(
      newValue
        ? t("kycTplDoc_confirm_enableSig")
        : t("kycTplDoc_confirm_disableSig"),
    )
  ) {
    return;
  }

  try {
    // 调用 API 更新数据库
    const response = await kycTemplateService.updateTemplate(props.templateId, {
      requireDocumentSignature: newValue,
    });

    if (response.success) {
      isEnabled.value = newValue;
      alert(
        newValue
          ? t("kycTplDoc_alert_sigEnabled")
          : t("kycTplDoc_alert_sigDisabled"),
      );

      // 如果启用且没有文档，添加一个新文档
      if (newValue && localDocuments.value.length === 0) {
        addDocument();
      }

      // 刷新父组件数据
      emit("refresh");
    } else {
      alert(
        tParams("kycTplDoc_alert_settingFailed", "", {
          msg: response.message || t("common_unknownError"),
        }),
      );
    }
  } catch (error) {
    console.error("Failed to update document signature requirement:", error);
    alert(t("kycTplDoc_alert_settingErr"));
  }
};

const addDocument = () => {
  const newIndex = localDocuments.value.length;
  const defaultContent = t("kycTplDoc_editorPlaceholder");
  localDocuments.value.push({
    id: null,
    documentTitle: tParams("kycTplDoc_newTitle", "", { n: newIndex + 1 }),
    documentContent: defaultContent,
    displayOrder: newIndex + 1,
    isActive: 1,
  });

  // 为新添加的文档初始化编辑器
  nextTick(() => {
    const editor = editorRefs.value.get(newIndex);
    if (editor) {
      editor.innerHTML = defaultContent;
      setCaretToEnd(editor);
    }
  });
};

const removeDocument = async (index) => {
  if (localDocuments.value.length <= 1) {
    alert(t("kycTplDoc_alert_needOne"));
    return;
  }

  const doc = localDocuments.value[index];
  if (
    !confirm(
      tParams("kycTplDoc_confirm_remove", "", { title: doc.documentTitle }),
    )
  ) {
    return;
  }

  try {
    // 如果文档已保存到数据库，调用删除接口
    if (doc.id) {
      const response = await kycDocumentService.deleteDocument(doc.id);
      if (response.success) {
        alert(t("kycTplDoc_alert_delOk"));
        // 不触发整页刷新，避免冲掉其他文档未保存的编辑；下面本地 splice 即可移除该项
      } else {
        alert(
          tParams("kycTplDoc_alert_delFailed", "", {
            msg: response.message || t("common_unknownError"),
          }),
        );
        return;
      }
    }

    // 从本地数组中移除
    localDocuments.value.splice(index, 1);
  } catch (error) {
    console.error("Failed to delete document:", error);
    alert(t("kycTplDoc_alert_delErr"));
  }
};

// 设置编辑器引用
const setEditorRef = (el, index) => {
  if (el) {
    editorRefs.value.set(index, el);
  }
};

// 将光标设置到元素末尾
const setCaretToEnd = (element) => {
  const range = document.createRange();
  const selection = window.getSelection();

  if (element.childNodes.length > 0) {
    range.selectNodeContents(element);
    range.collapse(false); // false 表示光标在末尾
  } else {
    range.setStart(element, 0);
    range.setEnd(element, 0);
  }

  selection.removeAllRanges();
  selection.addRange(range);
  element.focus();
};

// 初始化编辑器内容
const initializeEditorContent = () => {
  const defaultContent = t("kycTplDoc_editorPlaceholder");
  nextTick(() => {
    localDocuments.value.forEach((doc, index) => {
      const editor = editorRefs.value.get(index);
      if (editor) {
        editor.innerHTML = doc.documentContent || defaultContent;
      }
    });
  });
};

const formatContent = (index, command) => {
  document.execCommand(command, false, null);
};

const updateContent = (index, event) => {
  const element = event.target;
  // 更新数据但不触发重新渲染
  localDocuments.value[index].documentContent = element.innerHTML;

  // 确保光标保持在末尾
  setTimeout(() => {
    setCaretToEnd(element);
  }, 0);
};

// 新增保存文档方法
const saveDocument = async (index) => {
  const doc = localDocuments.value[index];

  if (!doc.documentTitle.trim()) {
    alert(t("kycTplDoc_alert_needTitle"));
    return;
  }

  const defaultContent = t("kycTplDoc_editorPlaceholder");
  const legacyEmpty = "<p>Enter document content here...</p>";
  if (
    !doc.documentContent.trim() ||
    doc.documentContent === legacyEmpty ||
    doc.documentContent === defaultContent
  ) {
    alert(t("kycTplDoc_alert_needContent"));
    return;
  }

  const payload = {
    templateId: props.templateId,
    documentTitle: doc.documentTitle,
    documentContent: doc.documentContent,
    displayOrder: doc.displayOrder,
    isActive: doc.isActive,
  };

  try {
    let response;
    if (doc.id) {
      // 更新现有文档
      response = await kycDocumentService.updateDocument(doc.id, payload);
      if (response.success) {
        alert(t("kycTplDoc_alert_updateOk"));
      } else {
        alert(
          tParams("kycTplDoc_alert_updateFailed", "", {
            msg: response.message || t("common_unknownError"),
          }),
        );
        return;
      }
    } else {
      // 创建新文档
      response = await kycDocumentService.createDocument(payload);
      if (response.success) {
        // 更新本地文档ID
        doc.id = response.data.documentId;
        alert(t("kycTplDoc_alert_saveOk"));
      } else {
        alert(
          tParams("kycTplDoc_alert_saveFailed", "", {
            msg: response.message || t("common_unknownError"),
          }),
        );
        return;
      }
    }

    // 不触发整页刷新：保存单个文档会让父组件重新拉取 documents，进而重建 localDocuments，
    // 冲掉其他文档里尚未保存的编辑。该文档已落库、新建时也已回填 id，无需刷新。
  } catch (error) {
    console.error("Failed to save document:", error);
    alert(t("kycTplDoc_alert_saveErr"));
  }
};
</script>

<style scoped>
.document-list {
  margin-top: 20px;
}

.document-required-toggle {
  background: var(--color-surface-soft);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 15px 20px;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.toggle-label {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
}

.toggle-switch {
  position: relative;
  width: 50px;
  height: 26px;
  background: var(--color-border-strong);
  border-radius: 13px;
  cursor: pointer;
  transition: all 0.3s ease;
}

.toggle-switch.active {
  background: var(--color-success-solid);
}

.toggle-switch::after {
  content: "";
  position: absolute;
  top: 3px;
  left: 3px;
  width: 20px;
  height: 20px;
  background: var(--color-surface);
  border-radius: 50%;
  transition: all 0.3s ease;
}

.toggle-switch.active::after {
  left: 27px;
}

.toggle-switch.disabled {
  cursor: not-allowed;
  opacity: 0.7;
  background: var(--color-border) !important;
}

.toggle-switch.disabled.active {
  background: var(--color-success-soft) !important;
  opacity: 0.8;
}

.toggle-switch.disabled::after {
  background: var(--color-border-strong);
}

.toggle-switch.disabled.active::after {
  background: var(--color-surface);
}

.toggle-switch.disabled:hover {
  cursor: not-allowed;
}

.documents-list-container {
  display: block;
}

.empty-state {
  padding: 60px 20px;
  text-align: center;
  color: var(--color-faint);
}

.empty-state i {
  font-size: 48px;
  margin-bottom: 15px;
  display: block;
}

.empty-state p {
  font-size: 16px;
}

.legal-document-item {
  background: var(--color-surface-soft);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 20px;
  margin-bottom: 15px;
  transition: all 0.3s ease;
}

.legal-document-item:hover {
  border-color: var(--color-border-strong);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.document-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 15px;
}

.document-title-input {
  flex: 1;
  padding: 10px 14px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
  background: var(--color-surface);
  margin-right: 10px;
}

.document-title-input:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.btn-small {
  padding: 8px 16px;
  border-radius: var(--radius-sm);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.document-actions {
  display: flex;
  gap: 8px;
}

.btn-save {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.btn-save:hover {
  background: var(--color-success-soft);
  transform: translateY(-1px);
}

.btn-delete {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.btn-delete:hover {
  background: var(--color-danger-border);
  color: white;
}

.editor-toolbar {
  display: flex;
  gap: 8px;
  margin-bottom: 10px;
  padding: 10px;
  background: var(--color-surface);
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border);
}

.editor-btn {
  padding: 6px 10px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  cursor: pointer;
  font-size: 13px;
  transition: all 0.2s ease;
  color: var(--color-text);
}

.editor-btn:hover {
  background: var(--color-brand-soft);
  border-color: var(--color-brand);
  color: var(--color-brand);
}

.document-editor {
  width: 100%;
  min-height: 150px;
  padding: 15px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  background: var(--color-surface);
  line-height: 1.6;
  transition: all 0.3s ease;
}

.document-editor:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.document-editor:empty:before {
  content: "Enter document content here...";
  color: var(--color-border-strong);
}

.document-editor ul,
.document-editor ol {
  padding-left: 20px;
  margin: 10px 0;
}

.document-editor li {
  margin: 6px 0;
}

.document-editor p {
  margin: 10px 0;
}

.btn-add-document {
  width: 100%;
  justify-content: center;
  padding: 12px 20px;
  background: var(--color-success-solid);
  color: white;
}

.btn-add-document:hover {
  background: var(--color-success-solid);
}

.btn {
  padding: 12px 20px;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
</style>
