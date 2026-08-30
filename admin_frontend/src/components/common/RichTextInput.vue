<script setup>
import { ref, watch, nextTick, computed } from "vue";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t, tParams } = useAdminI18n();

const props = defineProps({
  modelValue: {
    type: String,
    default: "",
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  placeholder: {
    type: String,
    default: "",
  },
});

// placeholder 调用方没传时用 i18n key 兜底；传了则直接用调用方的
const resolvedPlaceholder = computed(
  () => props.placeholder || t("richText_placeholder", "Enter content here..."),
);

const emit = defineEmits(["update:modelValue"]);

const editorRef = ref(null);
const textColors = [
  "#1f2937",
  "#dc2626",
  "#ea580c",
  "#ca8a04",
  "#16a34a",
  "#2563eb",
  "#7c3aed",
  "#db2777",
];
const highlightColors = [
  "#fef3c7",
  "#fde68a",
  "#fecaca",
  "#bfdbfe",
  "#ddd6fe",
  "#bbf7d0",
  "#fbcfe8",
  "#e5e7eb",
];

const updateContent = () => {
  if (!editorRef.value) return;
  emit("update:modelValue", editorRef.value.innerHTML);
};

const formatContent = (command, value = null) => {
  if (props.disabled || !editorRef.value) return;
  editorRef.value.focus();
  document.execCommand(command, false, value);
  updateContent();
};

const findSelectedBlock = () => {
  if (!editorRef.value) return null;

  const selection = window.getSelection();
  if (!selection || selection.rangeCount === 0) return null;

  let node = selection.anchorNode;

  while (node && node !== editorRef.value) {
    if (
      node.nodeType === Node.ELEMENT_NODE &&
      /^(P|DIV|H1|H2|H3|H4|H5|H6|LI)$/i.test(node.nodeName)
    ) {
      return node;
    }
    node = node.parentNode;
  }

  return null;
};

const placeCursorAtEnd = (element) => {
  const selection = window.getSelection();
  if (!selection) return;

  const range = document.createRange();
  range.selectNodeContents(element);
  range.collapse(false);
  selection.removeAllRanges();
  selection.addRange(range);
};

const clearHeadingInlineStyles = (rootElement) => {
  if (!rootElement) return;

  const elements = [rootElement, ...rootElement.querySelectorAll("*")];

  elements.forEach((element) => {
    if (!element.style) return;

    element.style.removeProperty("font-size");
    element.style.removeProperty("line-height");
    element.style.removeProperty("font-weight");

    if (!element.getAttribute("style")?.trim()) {
      element.removeAttribute("style");
    }
  });
};

const replaceBlockTag = (sourceElement, targetTag) => {
  if (!sourceElement || !sourceElement.parentNode) return null;

  const replacement = document.createElement(targetTag);
  replacement.innerHTML = sourceElement.innerHTML;

  for (const attr of sourceElement.attributes) {
    if (attr.name !== "style" && attr.name !== "class") {
      replacement.setAttribute(attr.name, attr.value);
    }
  }

  sourceElement.parentNode.replaceChild(replacement, sourceElement);
  clearHeadingInlineStyles(replacement);
  return replacement;
};

const toggleHeading = (tagName) => {
  if (props.disabled || !editorRef.value) return;

  const currentBlock = findSelectedBlock();
  const currentTag = currentBlock?.nodeName?.toLowerCase();
  const targetTag = tagName.toLowerCase();

  editorRef.value.focus();

  if (currentTag === targetTag && currentBlock) {
    const paragraph = replaceBlockTag(currentBlock, "p");
    if (paragraph) {
      placeCursorAtEnd(paragraph);
    }
  } else {
    document.execCommand("formatBlock", false, targetTag);
  }

  updateContent();
};

const applyParagraph = () => {
  if (props.disabled || !editorRef.value) return;

  const currentBlock = findSelectedBlock();
  editorRef.value.focus();

  if (currentBlock) {
    const paragraph = replaceBlockTag(currentBlock, "p");
    if (paragraph) {
      placeCursorAtEnd(paragraph);
    }
  } else {
    document.execCommand("formatBlock", false, "p");
  }

  updateContent();
};

const formatColor = (command, color) => {
  if (props.disabled || !editorRef.value) return;
  editorRef.value.focus();
  document.execCommand("styleWithCSS", false, true);
  document.execCommand(command, false, color);
  updateContent();
};

watch(
  () => props.modelValue,
  async (newValue) => {
    await nextTick();
    if (!editorRef.value) return;
    const normalized = newValue || "";
    if (editorRef.value.innerHTML !== normalized) {
      editorRef.value.innerHTML = normalized;
    }
  },
  { immediate: true },
);
</script>

<template>
  <div class="editor-toolbar">
    <button
      type="button"
      class="editor-btn"
      :disabled="disabled"
      :title="t('richText_bold', 'Bold')"
      @mousedown.prevent
      @click="formatContent('bold')"
    >
      <strong>B</strong>
    </button>
    <button
      type="button"
      class="editor-btn"
      :disabled="disabled"
      :title="t('richText_italic', 'Italic')"
      @mousedown.prevent
      @click="formatContent('italic')"
    >
      <em>I</em>
    </button>
    <button
      type="button"
      class="editor-btn"
      :disabled="disabled"
      :title="t('richText_underline', 'Underline')"
      @mousedown.prevent
      @click="formatContent('underline')"
    >
      <u>U</u>
    </button>
    <button
      type="button"
      class="editor-btn"
      :disabled="disabled"
      :title="t('richText_bullet', 'Bulleted list')"
      @mousedown.prevent
      @click="formatContent('insertUnorderedList')"
    >
      <i class="fas fa-list-ul"></i>
    </button>
    <button
      type="button"
      class="editor-btn"
      :disabled="disabled"
      :title="t('richText_numbered', 'Numbered list')"
      @mousedown.prevent
      @click="formatContent('insertOrderedList')"
    >
      <i class="fas fa-list-ol"></i>
    </button>
    <button
      type="button"
      class="editor-btn"
      :disabled="disabled"
      @mousedown.prevent
      @click="toggleHeading('h3')"
      :title="t('richText_title', 'Title')"
    >
      <i class="fas fa-heading"></i> {{ t("richText_title", "Title") }}
    </button>
    <button
      type="button"
      class="editor-btn"
      :disabled="disabled"
      @mousedown.prevent
      @click="toggleHeading('h4')"
      :title="t('richText_subtitle', 'Subtitle')"
    >
      <i class="fas fa-heading"></i> {{ t("richText_subtitle", "Subtitle") }}
    </button>
    <button
      type="button"
      class="editor-btn"
      :disabled="disabled"
      @mousedown.prevent
      @click="applyParagraph"
      :title="t('richText_bodyText', 'Body text')"
    >
      {{ t("richText_body", "Body") }}
    </button>
    <div class="toolbar-divider"></div>
    <div class="color-group">
      <span class="color-group-label">{{
        t("richText_textLabel", "Text")
      }}</span>
      <button
        v-for="color in textColors"
        :key="`text-${color}`"
        type="button"
        class="color-swatch"
        :style="{ backgroundColor: color }"
        :disabled="disabled"
        :title="tParams('richText_textColor', 'Text color {color}', { color })"
        @mousedown.prevent
        @click="formatColor('foreColor', color)"
      ></button>
    </div>
    <div class="color-group">
      <span class="color-group-label">{{
        t("richText_highlightLabel", "Highlight")
      }}</span>
      <button
        v-for="color in highlightColors"
        :key="`highlight-${color}`"
        type="button"
        class="color-swatch color-swatch-highlight"
        :style="{ backgroundColor: color }"
        :disabled="disabled"
        :title="
          tParams('richText_highlightColor', 'Highlight color {color}', {
            color,
          })
        "
        @mousedown.prevent
        @click="formatColor('hiliteColor', color)"
      ></button>
    </div>
    <button
      type="button"
      class="editor-btn"
      :disabled="disabled"
      :title="t('richText_clearFormatting', 'Clear formatting')"
      @mousedown.prevent
      @click="formatContent('removeFormat')"
    >
      <i class="fas fa-eraser"></i>
    </button>
  </div>
  <div
    ref="editorRef"
    class="document-editor"
    :class="{ 'document-editor-disabled': disabled }"
    :contenteditable="!disabled"
    :data-placeholder="resolvedPlaceholder"
    @input="updateContent"
  ></div>
</template>

<style scoped>
.editor-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
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

.editor-btn:disabled {
  background: var(--color-surface-soft);
  border-color: var(--color-border);
  color: var(--color-faint);
  cursor: not-allowed;
}

.toolbar-divider {
  width: 1px;
  height: 28px;
  background: var(--color-border);
}

.color-group {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 8px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface-soft);
}

.color-group-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-muted);
}

.color-swatch {
  width: 20px;
  height: 20px;
  padding: 0;
  border: 2px solid var(--color-surface);
  border-radius: 999px;
  box-shadow: 0 0 0 1px var(--color-border-strong);
  cursor: pointer;
  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease;
}

.color-swatch:hover {
  transform: scale(1.08);
  box-shadow: 0 0 0 2px var(--color-muted);
}

.color-swatch-highlight {
  border-radius: var(--radius-sm);
}

.color-swatch:disabled {
  cursor: not-allowed;
  opacity: 0.5;
  transform: none;
}

.document-editor {
  width: 100%;
  min-height: 150px;
  padding: 15px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  color: var(--color-text);
  background: var(--color-surface);
  line-height: 1.6;
  transition: all 0.3s ease;
}

.document-editor-disabled {
  background: var(--color-surface-soft);
  cursor: not-allowed;
}

.document-editor:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.document-editor:empty:before {
  content: attr(data-placeholder);
  color: var(--color-border-strong);
}

.document-editor :deep(ul),
.document-editor :deep(ol) {
  padding-left: 28px;
  list-style-position: outside;
  margin: 12px 0;
}

.document-editor :deep(li) {
  margin: 8px 0;
  padding-left: 4px;
}

.document-editor :deep(ul) {
  list-style-type: disc;
}

.document-editor :deep(ol) {
  list-style-type: decimal;
}

.document-editor :deep(ul ul) {
  list-style-type: circle;
}

.document-editor :deep(ol ol) {
  list-style-type: lower-alpha;
}

.document-editor :deep(li::marker) {
  color: var(--color-text);
  font-weight: 600;
}

.document-editor :deep(p) {
  margin: 10px 0;
}

.document-editor :deep(h3) {
  margin: 16px 0 10px;
  font-size: 20px;
  line-height: 1.35;
  font-weight: 700;
  color: var(--color-ink);
}

.document-editor :deep(h4) {
  margin: 14px 0 8px;
  font-size: 17px;
  line-height: 1.4;
  font-weight: 700;
  color: var(--color-ink);
}
</style>
