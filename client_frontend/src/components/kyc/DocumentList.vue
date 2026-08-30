<template>
  <div class="document-list">
    <div class="document-required-toggle">
      <span class="toggle-label">Require Document Signature</span>
      <div
        class="toggle-switch"
        :class="{ active: isEnabled }"
        @click="toggleRequirement"
      ></div>
    </div>

    <div v-if="isEnabled" class="documents-list-container">
      <div v-if="localDocuments.length === 0" class="empty-state">
        <i class="fas fa-file-signature"></i>
        <p>No documents added yet</p>
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
            v-model="doc.title"
            placeholder="Document Title"
          />
          <button
            type="button"
            class="btn-small btn-delete"
            @click="removeDocument(idx)"
          >
            <i class="fas fa-trash"></i> Remove
          </button>
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
          @input="updateContent(idx, $event)"
          v-html="doc.content"
        ></div>
      </div>

      <button
        type="button"
        class="btn btn-small btn-add-document"
        @click="addDocument"
      >
        <i class="fas fa-plus"></i> Add New Legal Document
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from "vue";

const props = defineProps({
  templateId: {
    type: Number,
    required: true,
  },
  documents: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(["refresh"]);

const isEnabled = ref(props.documents.length > 0);
const localDocuments = ref([...props.documents]);

watch(
  () => props.documents,
  (newDocs) => {
    localDocuments.value = [...newDocs];
    isEnabled.value = newDocs.length > 0;
  },
);

const toggleRequirement = () => {
  isEnabled.value = !isEnabled.value;
  if (isEnabled.value && localDocuments.value.length === 0) {
    addDocument();
  }
};

const addDocument = () => {
  localDocuments.value.push({
    title: `New Legal Document ${localDocuments.value.length + 1}`,
    content: "<p>Enter document content here...</p>",
  });
};

const removeDocument = (index) => {
  if (localDocuments.value.length <= 1) {
    alert("⚠️ You must have at least one legal document.");
    return;
  }

  const doc = localDocuments.value[index];
  if (confirm(`Are you sure you want to remove "${doc.title}"?`)) {
    localDocuments.value.splice(index, 1);
  }
};

const formatContent = (index, command) => {
  document.execCommand(command, false, null);
};

const updateContent = (index, event) => {
  localDocuments.value[index].content = event.target.innerHTML;
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
  background: var(--color-success);
}

.toggle-switch::after {
  content: "";
  position: absolute;
  top: 3px;
  left: 3px;
  width: 20px;
  height: 20px;
  background: white;
  border-radius: 50%;
  transition: all 0.3s ease;
}

.toggle-switch.active::after {
  left: 27px;
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
  background: white;
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
  background: white;
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border);
}

.editor-btn {
  padding: 6px 10px;
  background: white;
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
  background: white;
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
  background: var(--color-success);
  color: white;
}

.btn-add-document:hover {
  background: var(--color-success);
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
