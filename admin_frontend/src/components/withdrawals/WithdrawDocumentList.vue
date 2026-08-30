<template>
  <div class="document-list">
    <div class="document-required-toggle">
      <span class="toggle-label">{{
        t("withdrawKycDoc_requireSignature")
      }}</span>
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
        <p>{{ t("withdrawKycDoc_empty") }}</p>
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
            :placeholder="t('withdrawKycDoc_placeholderTitle')"
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
              {{
                doc.id
                  ? t("withdrawKycDoc_btnUpdate")
                  : t("withdrawKycDoc_btnSave")
              }}
            </button>
            <button
              v-if="hasDeleteLegalDocumentPermission"
              type="button"
              class="btn-small btn-delete"
              @click="removeDocument(idx)"
            >
              <i class="fas fa-trash"></i> {{ t("withdrawKycDoc_btnRemove") }}
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
          class="document-editor font-floor-content"
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
        <i class="fas fa-plus"></i> {{ t("withdrawKycDoc_btnAddLegal") }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, nextTick } from "vue";
import {
  withdrawKycDocumentService,
  withdrawKycTemplateService,
} from "@/services/withdrawKycTemplateService";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { translateApiErrorMessage } from "@/i18n/adminI18nBridge";

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
  documentService: {
    type: Object,
    default: () => withdrawKycDocumentService,
  },
  templateService: {
    type: Object,
    default: () => withdrawKycTemplateService,
  },
});

const emit = defineEmits(["refresh"]);

const isEnabled = ref(!!props.requireDocumentSignature);
const localDocuments = ref([]);

// 编辑器引用
const editorRefs = ref(new Map());

// 初始化文档数据，确保格式正确
const initializeDocuments = (docs) => {
  return docs.map((doc) => ({
    id: doc.id || null,
    documentTitle: doc.documentTitle || doc.title || "",
    documentContent:
      doc.documentContent ||
      doc.content ||
      "<p>Enter document content here...</p>",
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
        ? t("withdrawKycDoc_confirm_toggleOn")
        : t("withdrawKycDoc_confirm_toggleOff"),
    )
  ) {
    return;
  }

  try {
    // 调用 API 更新数据库
    const response = await props.templateService.updateTemplate(
      props.templateId,
      {
        requireDocumentSignature: newValue,
      },
    );

    if (response.success) {
      isEnabled.value = newValue;
      alert(
        tParams("withdrawKycDoc_alert_toggleOk", "...", {
          state: t(
            newValue
              ? "withdrawKycDoc_stateEnabled"
              : "withdrawKycDoc_stateDisabled",
          ),
        }),
      );

      // 如果启用且没有文档，添加一个新文档
      if (newValue && localDocuments.value.length === 0) {
        addDocument();
      }

      // 刷新父组件数据
      emit("refresh");
    } else {
      const raw = response.message || t("common_unknownError");
      alert(
        tParams("withdrawKycDoc_alert_toggleFailed", "Failed: {msg}", {
          msg: translateApiErrorMessage(response.errorCode, raw),
        }),
      );
    }
  } catch (error) {
    console.error("Failed to update document signature requirement:", error);
    const data = error?.response?.data ?? error;
    const rawMsg = data?.message || error?.message || t("common_unknownError");
    const msg = translateApiErrorMessage(data?.errorCode, rawMsg);
    alert(
      tParams("withdrawKycDoc_alert_toggleFailed", "Failed: {msg}", { msg }),
    );
  }
};

const addDocument = () => {
  const newIndex = localDocuments.value.length;
  localDocuments.value.push({
    id: null,
    documentTitle: `New Legal Document ${newIndex + 1}`,
    documentContent: "<p>Enter document content here...</p>",
    displayOrder: newIndex + 1,
    isActive: 1,
  });

  // 为新添加的文档初始化编辑器
  nextTick(() => {
    const editor = editorRefs.value.get(newIndex);
    if (editor) {
      editor.innerHTML = "<p>Enter document content here...</p>";
      setCaretToEnd(editor);
    }
  });
};

const removeDocument = async (index) => {
  if (localDocuments.value.length <= 1) {
    alert(t("withdrawKycDoc_alert_needOneDoc"));
    return;
  }

  const doc = localDocuments.value[index];
  if (
    !confirm(
      tParams("withdrawKycDoc_confirm_remove", "...", {
        title: doc.documentTitle,
      }),
    )
  ) {
    return;
  }

  try {
    // 如果文档已保存到数据库，调用删除接口
    if (doc.id) {
      const response = await props.documentService.deleteDocument(doc.id);
      if (response.success) {
        alert(t("withdrawKycDoc_alert_removed"));
        emit("refresh");
      } else {
        const raw = response.message || t("common_unknownError");
        alert(
          tParams("withdrawKycDoc_alert_removeFailed", "Failed: {msg}", {
            msg: translateApiErrorMessage(response.errorCode, raw),
          }),
        );
        return;
      }
    }

    // 从本地数组中移除
    localDocuments.value.splice(index, 1);
  } catch (error) {
    console.error("Failed to delete document:", error);
    const data = error?.response?.data ?? error;
    const rawMsg = data?.message || error?.message || t("common_unknownError");
    const msg = translateApiErrorMessage(data?.errorCode, rawMsg);
    alert(
      tParams("withdrawKycDoc_alert_removeFailed", "Failed: {msg}", { msg }),
    );
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
  nextTick(() => {
    localDocuments.value.forEach((doc, index) => {
      const editor = editorRefs.value.get(index);
      if (editor) {
        editor.innerHTML =
          doc.documentContent || "<p>Enter document content here...</p>";
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
    alert(t("withdrawKycDoc_alert_needTitle"));
    return;
  }

  if (
    !doc.documentContent.trim() ||
    doc.documentContent === "<p>Enter document content here...</p>"
  ) {
    alert(t("withdrawKycDoc_alert_needContent"));
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
      response = await props.documentService.updateDocument(doc.id, payload);
      if (response.success) {
        alert(t("withdrawKycDoc_alert_updateOk"));
      } else {
        const raw = response.message || t("common_unknownError");
        alert(
          tParams("withdrawKycDoc_alert_saveDocFailed", "Failed: {msg}", {
            msg: translateApiErrorMessage(response.errorCode, raw),
          }),
        );
        return;
      }
    } else {
      // 创建新文档
      response = await props.documentService.createDocument(payload);
      if (response.success) {
        // 更新本地文档ID
        doc.id = response.data.documentId;
        alert(t("withdrawKycDoc_alert_saveOk"));
      } else {
        const raw = response.message || t("common_unknownError");
        alert(
          tParams("withdrawKycDoc_alert_saveDocFailed", "Failed: {msg}", {
            msg: translateApiErrorMessage(response.errorCode, raw),
          }),
        );
        return;
      }
    }

    emit("refresh");
  } catch (error) {
    console.error("Failed to save document:", error);
    const data = error?.response?.data ?? error;
    const rawMsg = data?.message || error?.message || t("common_unknownError");
    const msg = translateApiErrorMessage(data?.errorCode, rawMsg);
    alert(
      tParams("withdrawKycDoc_alert_saveDocFailed", "Failed: {msg}", { msg }),
    );
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
  font-size: 14px;
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
  font-size: 14px;
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
