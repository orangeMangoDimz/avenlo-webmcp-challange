<template>
  <Teleport to="body">
    <div :class="['need-docs-modal', { show: modelValue }]" @click="close">
      <div class="need-docs-modal-content" @click.stop>
        <div class="need-docs-modal-header">
          <h3>
            <i class="fas fa-file-upload"></i> {{ t("needDocsModal_title") }}
          </h3>
          <button class="need-docs-modal-close" @click="close">×</button>
        </div>

        <div class="need-docs-modal-body">
          <!-- Items Section -->
          <div class="need-docs-items-section">
            <div class="need-docs-items-header">
              <h5>
                <i class="fas fa-clipboard-list"></i>
                {{ t("needDocsModal_sectionItems") }}
              </h5>
              <div class="add-item-buttons">
                <button
                  class="btn-add-item btn-add-question"
                  @click="openAddItemModal('question')"
                  type="button"
                >
                  <i class="fas fa-plus"></i>
                  {{ t("needDocsModal_btnAddQuestion") }}
                </button>
                <button
                  class="btn-add-item btn-add-document"
                  @click="openAddItemModal('document')"
                  type="button"
                >
                  <i class="fas fa-plus"></i>
                  {{ t("needDocsModal_btnAddDocument") }}
                </button>
              </div>
            </div>

            <div class="items-list">
              <div v-if="requestedItems.length === 0" class="empty-message">
                <i class="fas fa-inbox"></i>
                {{ t("needDocsModal_empty") }}
              </div>

              <div
                v-for="(item, index) in requestedItems"
                :key="index"
                :class="[
                  item.type === 'question'
                    ? 'selectable-question-item'
                    : 'selectable-document-item',
                  { selected: item.selected },
                ]"
                @click.stop="toggleItemSelection(index)"
                class="item-card"
              >
                <label class="question-checkbox" @click.stop>
                  <input
                    type="checkbox"
                    class="need-docs-checkbox"
                    :checked="item.selected"
                    @change="item.selected = $event.target.checked"
                  />
                  <span class="question-checkbox-mark"></span>
                </label>
                <!-- Question -->
                <template v-if="item.type === 'question'">
                  <div class="selectable-question-content">
                    <div class="selectable-question-title">
                      {{ item.name || item.title }}
                    </div>
                    <span
                      v-if="item.questionType"
                      class="selectable-question-type"
                    >
                      {{ getQuestionTypeLabel(item.questionType) }}
                    </span>
                  </div>
                </template>
                <!-- Document -->
                <template v-else>
                  <div class="document-icon">
                    <i :class="getDocumentIcon(item.documentType)"></i>
                  </div>
                  <div class="selectable-document-content">
                    <div class="selectable-document-title">
                      {{ item.name || item.title }}
                    </div>
                  </div>
                </template>
                <div class="item-actions">
                  <button
                    class="btn-edit-item"
                    @click.stop="openEditItemModal(index)"
                    :title="t('needDocsModal_tooltipEdit')"
                  >
                    <i class="fas fa-edit"></i>
                  </button>
                  <button
                    class="btn-remove-item"
                    @click.stop="removeRequestedItem(index)"
                    :title="t('needDocsModal_tooltipRemove')"
                  >
                    <i class="fas fa-times"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Additional Instructions -->
          <div class="form-group">
            <label for="admin-instructions">
              <i class="fas fa-sticky-note"></i>
              {{ t("needDocsModal_additionalInstructions") }}
            </label>
            <textarea
              id="admin-instructions"
              v-model="adminInstructions"
              :placeholder="t('needDocsModal_instructionsPlaceholder')"
              rows="3"
            ></textarea>
          </div>

          <!-- Summary -->
          <div class="need-docs-summary">
            <h5>
              <i class="fas fa-clipboard-list"></i>
              {{ t("needDocsModal_summaryTitle") }}
            </h5>
            <div class="summary-items">
              <span v-if="selectedItems.length === 0" class="summary-empty">{{
                t("needDocsModal_summaryEmpty")
              }}</span>
              <span
                v-for="(item, index) in selectedItems"
                :key="index"
                class="summary-tag"
              >
                <i
                  :class="
                    item.type === 'question'
                      ? 'fas fa-question-circle'
                      : 'fas fa-file-alt'
                  "
                ></i>
                {{ item.name || item.title }}
              </span>
            </div>
          </div>
        </div>

        <div class="need-docs-modal-footer">
          <button
            class="btn btn-secondary"
            @click="close"
            :disabled="processing"
          >
            {{ t("needDocsModal_btnCancel") }}
          </button>
          <button
            class="btn btn-primary"
            @click="confirm"
            :disabled="processing || selectedItems.length === 0"
          >
            <i
              :class="
                processing ? 'fas fa-spinner fa-spin' : 'fas fa-paper-plane'
              "
            ></i>
            {{
              processing
                ? t("needDocsModal_btnSending")
                : t("needDocsModal_btnSendRequest")
            }}
          </button>
        </div>
      </div>
    </div>

    <!-- Add Item Modal -->
    <div
      class="add-item-modal"
      :class="{ show: showAddItemModal }"
      @click.self="closeAddItemModal"
    >
      <div class="add-item-modal-content">
        <div class="add-item-modal-header">
          <h3>
            <i
              :class="
                isEditMode
                  ? 'fas fa-edit'
                  : addItemType === 'question'
                    ? 'fas fa-plus-circle'
                    : 'fas fa-file-alt'
              "
            ></i>
            {{ addItemModalTitle }}
          </h3>
          <button class="add-item-modal-close" @click="closeAddItemModal">
            ×
          </button>
        </div>
        <div class="add-item-modal-body">
          <form @submit.prevent="saveItemToList">
            <!-- Question Text -->
            <div class="add-item-field" v-if="addItemType === 'question'">
              <label for="itemQuestionText"
                >{{ t("needDocsModal_labelQuestionText") }}
                <span style="color: var(--color-danger)">*</span></label
              >
              <textarea
                id="itemQuestionText"
                v-model="itemForm.questionText"
                :placeholder="t('needDocsModal_placeholderQuestion')"
                rows="3"
                required
              ></textarea>
            </div>

            <!-- Document Name -->
            <div class="add-item-field" v-if="addItemType === 'document'">
              <label for="itemDocumentName"
                >{{ t("needDocsModal_labelDocumentName") }}
                <span style="color: var(--color-danger)">*</span></label
              >
              <input
                type="text"
                id="itemDocumentName"
                v-model="itemForm.documentName"
                :placeholder="t('needDocsModal_placeholderDocumentName')"
                required
              />
            </div>

            <!-- Question Type -->
            <div class="add-item-field" v-if="addItemType === 'question'">
              <label for="itemQuestionType"
                >{{ t("needDocsModal_labelQuestionType") }}
                <span style="color: var(--color-danger)">*</span></label
              >
              <select
                id="itemQuestionType"
                v-model="itemForm.questionType"
                @change="toggleAnswerOptions"
                required
              >
                <option value="">
                  {{ t("needDocsModal_selectQuestionType") }}
                </option>
                <option
                  v-for="type in questionTypes"
                  :key="type.value"
                  :value="type.value"
                >
                  {{ type.label }}
                </option>
              </select>
            </div>

            <!-- Document Type -->
            <div class="add-item-field" v-if="addItemType === 'document'">
              <label for="itemDocumentType">{{
                t("needDocsModal_labelDocumentType")
              }}</label>
              <select id="itemDocumentType" v-model="itemForm.documentType">
                <option
                  v-for="docOpt in documentTypeOptions"
                  :key="docOpt.value"
                  :value="docOpt.value"
                >
                  {{ t(docOpt.labelKey) }}
                </option>
              </select>
            </div>

            <!-- Help Text -->
            <div class="add-item-field">
              <label for="itemHelpText">{{
                t("needDocsModal_labelHelpText")
              }}</label>
              <input
                type="text"
                id="itemHelpText"
                v-model="itemForm.helpText"
                :placeholder="t('needDocsModal_placeholderHelpText')"
              />
            </div>

            <!-- Validation Rule -->
            <div class="add-item-field" v-if="addItemType === 'question'">
              <label for="itemValidation">{{
                t("needDocsModal_labelValidation")
              }}</label>
              <input
                type="text"
                id="itemValidation"
                v-model="itemForm.validation"
                :placeholder="t('needDocsModal_placeholderValidation')"
              />
            </div>

            <!-- Answer Options (for choice questions) -->
            <div
              class="add-item-field"
              v-if="addItemType === 'question' && showAnswerOptions"
              style="grid-column: 1 / -1"
            >
              <label>{{ t("needDocsModal_labelAnswerOptions") }}</label>
              <div class="answer-options-container">
                <div
                  v-for="(option, optIndex) in itemForm.options"
                  :key="optIndex"
                  class="answer-option-row"
                >
                  <input
                    type="text"
                    v-model="itemForm.options[optIndex]"
                    :placeholder="optionPlaceholder(optIndex)"
                    class="answer-option-input"
                  />
                  <button
                    type="button"
                    class="btn-remove-option"
                    @click="removeOption(optIndex)"
                    :disabled="itemForm.options.length <= 1"
                  >
                    <i class="fas fa-times"></i>
                  </button>
                </div>
                <button type="button" class="btn-add-option" @click="addOption">
                  <i class="fas fa-plus"></i>
                  {{ t("needDocsModal_btnAddOption") }}
                </button>
              </div>
            </div>

            <!-- File Document Types (for file upload questions) -->
            <div
              class="add-item-field"
              v-if="
                addItemType === 'question' &&
                itemForm.questionType === 'file_upload'
              "
              style="grid-column: 1 / -1"
            >
              <label for="itemFileDocumentTypes">
                <i class="fas fa-file-alt"></i>
                {{ t("needDocsModal_labelAcceptedDocTypes") }}
                <span style="color: var(--color-danger)">*</span>
              </label>
              <select
                id="itemFileDocumentTypes"
                v-model="itemForm.fileDocumentTypes"
                multiple
                class="file-document-types-select"
                required
              >
                <option
                  v-for="docOpt in documentTypeOptions"
                  :key="docOpt.value"
                  :value="docOpt.value"
                >
                  {{ t(docOpt.labelKey) }}
                </option>
              </select>
              <small
                style="
                  color: var(--color-muted);
                  font-size: 14px;
                  margin-top: 8px;
                  display: block;
                "
              >
                <i class="fas fa-info-circle"></i>
                {{ t("needDocsModal_holdCtrlHint") }}
              </small>
            </div>

            <!-- Required Checkbox -->
            <div class="add-item-field" style="grid-column: 1 / -1">
              <label
                style="
                  display: flex;
                  align-items: center;
                  gap: 8px;
                  cursor: pointer;
                "
              >
                <input
                  type="checkbox"
                  v-model="itemForm.isRequired"
                  style="
                    width: 18px;
                    height: 18px;
                    accent-color: var(--color-brand);
                    cursor: pointer;
                  "
                />
                <span>{{ t("needDocsModal_requiredField") }}</span>
              </label>
            </div>
          </form>
        </div>
        <div class="add-item-modal-footer">
          <button class="btn-modal-cancel" @click="closeAddItemModal">
            {{ t("needDocsModal_btnCancelSub") }}
          </button>
          <button class="btn-modal-save" @click="saveItemToList">
            <i class="fas fa-save"></i>
            {{
              isEditMode
                ? t("needDocsModal_btnUpdateItem")
                : t("needDocsModal_btnAddItem")
            }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed } from "vue";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t, tParams } = useAdminI18n();

const QUESTION_TYPE_VALUES = [
  "text",
  "number",
  "email",
  "tel",
  "date",
  "single_choice",
  "multiple_choice",
  "yes_no",
  "file_upload",
  "textarea",
];
const DOCUMENT_TYPE_VALUES = [
  "ID_CARD",
  "PASSPORT",
  "DRIVERS_LICENSE",
  "PROOF_ADDRESS",
  "BANK_STATEMENT",
  "UTILITY_BILL",
  "INCOME_PROOF",
  "TAX_DOCUMENT",
  "EMPLOYMENT_LETTER",
  "OTHER",
];

const documentTypeOptions = computed(() =>
  DOCUMENT_TYPE_VALUES.map((value) => ({
    value,
    labelKey: `needDocsModal_doc_${value}`,
  })),
);

const questionTypes = computed(() =>
  QUESTION_TYPE_VALUES.map((value) => ({
    value,
    label: t(`needDocsModal_qt_${value}`),
  })),
);

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  withdrawalId: {
    type: [Number, String],
    default: null,
  },
  processing: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["update:modelValue", "confirm"]);

// State
const requestedItems = ref([]);
const adminInstructions = ref("");
const showAddItemModal = ref(false);
const addItemType = ref("question");
const showAnswerOptions = ref(false);
const isEditMode = ref(false);
const editingItemIndex = ref(-1);

const addItemModalTitle = computed(() => {
  if (isEditMode.value) {
    return addItemType.value === "question"
      ? t("needDocsModal_titleEditQuestion")
      : t("needDocsModal_titleEditDocument");
  }
  return addItemType.value === "question"
    ? t("needDocsModal_titleAddQuestion")
    : t("needDocsModal_titleAddDocument");
});

const optionPlaceholder = (optIndex) =>
  tParams("needDocsModal_optionN", "Option {n}", { n: String(optIndex + 1) });

// Form
const itemForm = ref({
  questionText: "",
  documentName: "",
  questionType: "",
  documentType: "ID_CARD",
  helpText: "",
  validation: "",
  options: [""],
  fileDocumentTypes: [],
  isRequired: true,
});

// Computed
const selectedItems = computed(() => {
  return requestedItems.value.filter((item) => item.selected);
});

// Methods
const openAddItemModal = (type) => {
  isEditMode.value = false;
  editingItemIndex.value = -1;
  addItemType.value = type;
  itemForm.value = {
    questionText: "",
    documentName: "",
    questionType: "",
    documentType: "ID_CARD",
    helpText: "",
    validation: "",
    options: [""],
    fileDocumentTypes: [],
    isRequired: true,
  };
  showAnswerOptions.value = false;
  showAddItemModal.value = true;
};

const openEditItemModal = (index) => {
  const item = requestedItems.value[index];
  if (!item) return;

  isEditMode.value = true;
  editingItemIndex.value = index;
  addItemType.value = item.type;

  if (item.type === "question") {
    itemForm.value = {
      questionText: item.name || item.title || "",
      documentName: "",
      questionType: item.questionType || "",
      documentType: "ID_CARD",
      helpText: item.helpText || "",
      validation: item.validation || "",
      options:
        item.options && item.options.length > 0 ? [...item.options] : [""],
      fileDocumentTypes: item.fileDocumentTypes || [],
      isRequired: item.isRequired !== undefined ? item.isRequired : true,
    };
    showAnswerOptions.value =
      item.questionType === "single_choice" ||
      item.questionType === "multiple_choice";
  } else {
    itemForm.value = {
      questionText: "",
      documentName: item.name || item.title || "",
      questionType: "",
      documentType: item.documentType || "ID_CARD",
      helpText: "",
      validation: "",
      options: [""],
      fileDocumentTypes: [],
      isRequired: item.isRequired !== undefined ? item.isRequired : true,
    };
    showAnswerOptions.value = false;
  }

  showAddItemModal.value = true;
};

const closeAddItemModal = () => {
  showAddItemModal.value = false;
  isEditMode.value = false;
  editingItemIndex.value = -1;
  setTimeout(() => {
    itemForm.value = {
      questionText: "",
      documentName: "",
      questionType: "",
      documentType: "ID_CARD",
      helpText: "",
      validation: "",
      options: [""],
      fileDocumentTypes: [],
      isRequired: true,
    };
    showAnswerOptions.value = false;
  }, 300);
};

const toggleAnswerOptions = () => {
  const questionType = itemForm.value.questionType;
  showAnswerOptions.value =
    questionType === "single_choice" || questionType === "multiple_choice";
  if (showAnswerOptions.value && itemForm.value.options.length === 0) {
    itemForm.value.options = [""];
  }
};

const addOption = () => {
  itemForm.value.options.push("");
};

const removeOption = (index) => {
  if (itemForm.value.options.length > 1) {
    itemForm.value.options.splice(index, 1);
  }
};

const getDocumentIcon = (documentType) => {
  const iconMap = {
    ID_CARD: "fas fa-id-card",
    PASSPORT: "fas fa-passport",
    DRIVERS_LICENSE: "fas fa-id-card",
    PROOF_ADDRESS: "fas fa-home",
    BANK_STATEMENT: "fas fa-university",
    UTILITY_BILL: "fas fa-file-invoice",
    INCOME_PROOF: "fas fa-money-check-alt",
    TAX_DOCUMENT: "fas fa-file-invoice-dollar",
    EMPLOYMENT_LETTER: "fas fa-briefcase",
    OTHER: "fas fa-file-alt",
  };
  return iconMap[documentType] || "fas fa-file-alt";
};

const getQuestionTypeLabel = (value) => {
  const key = `needDocsModal_qt_${value}`;
  const tr = t(key);
  return tr === key ? value : tr;
};

const toggleItemSelection = (index) => {
  requestedItems.value[index].selected = !requestedItems.value[index].selected;
};

const saveItemToList = () => {
  if (addItemType.value === "question") {
    if (!itemForm.value.questionText.trim()) {
      alert(t("needDocsModal_alert_enterQuestion"));
      return;
    }
    if (!itemForm.value.questionType) {
      alert(t("needDocsModal_alert_selectQType"));
      return;
    }

    if (
      itemForm.value.questionType === "single_choice" ||
      itemForm.value.questionType === "multiple_choice"
    ) {
      const validOptions = itemForm.value.options.filter((opt) => opt.trim());
      if (validOptions.length === 0) {
        alert(t("needDocsModal_alert_choiceOptions"));
        return;
      }
    }

    if (itemForm.value.questionType === "file_upload") {
      if (itemForm.value.fileDocumentTypes.length === 0) {
        alert(t("needDocsModal_alert_fileDocTypes"));
        return;
      }
    }

    const questionData = {
      type: "question",
      itemType: "question",
      name: itemForm.value.questionText.trim(),
      title: itemForm.value.questionText.trim(),
      questionText: itemForm.value.questionText.trim(),
      questionType: itemForm.value.questionType,
      helpText: itemForm.value.helpText,
      validation: itemForm.value.validation,
      questionOptions:
        itemForm.value.questionType === "single_choice" ||
        itemForm.value.questionType === "multiple_choice"
          ? itemForm.value.options.filter((opt) => opt.trim())
          : [],
      fileDocumentTypes:
        itemForm.value.questionType === "file_upload"
          ? itemForm.value.fileDocumentTypes
          : [],
      acceptedFileTypes:
        itemForm.value.questionType === "file_upload"
          ? itemForm.value.fileDocumentTypes
          : [],
      isRequired: itemForm.value.isRequired,
      selected: isEditMode.value
        ? requestedItems.value[editingItemIndex.value]?.selected
        : true,
    };

    if (isEditMode.value && editingItemIndex.value >= 0) {
      requestedItems.value[editingItemIndex.value] = questionData;
    } else {
      requestedItems.value.push(questionData);
    }
  } else {
    if (!itemForm.value.documentName.trim()) {
      alert(t("needDocsModal_alert_enterDocName"));
      return;
    }

    const documentData = {
      type: "document",
      itemType: "document",
      name: itemForm.value.documentName.trim(),
      title: itemForm.value.documentName.trim(),
      documentName: itemForm.value.documentName.trim(),
      documentType: itemForm.value.documentType,
      isRequired: itemForm.value.isRequired,
      selected: isEditMode.value
        ? requestedItems.value[editingItemIndex.value]?.selected
        : true,
    };

    if (isEditMode.value && editingItemIndex.value >= 0) {
      requestedItems.value[editingItemIndex.value] = documentData;
    } else {
      requestedItems.value.push(documentData);
    }
  }

  closeAddItemModal();
};

const removeRequestedItem = (index) => {
  requestedItems.value.splice(index, 1);
};

const close = () => {
  if (!props.processing) {
    emit("update:modelValue", false);
  }
};

const confirm = () => {
  if (selectedItems.value.length === 0) {
    alert(t("needDocsModal_alert_selectOne"));
    return;
  }

  emit("confirm", {
    items: selectedItems.value,
    adminInstructions: adminInstructions.value.trim() || null,
  });

  // 清空数据
  requestedItems.value = [];
  adminInstructions.value = "";
};
</script>

<style scoped>
/* 复用KYC的样式，但需要适配withdrawal的样式 */
.need-docs-modal {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  z-index: 2000;
  align-items: center;
  justify-content: center;
}

.need-docs-modal.show {
  display: flex;
}

.need-docs-modal-content {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  max-width: 900px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
  animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
  from {
    opacity: 0;
    transform: translateY(-20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.need-docs-modal-header {
  padding: 25px 30px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--color-warning-soft);
}

.need-docs-modal-header h3 {
  font-size: 20px;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 0;
}

.need-docs-modal-header h3 i {
  color: var(--color-warning);
  font-size: 22px;
}

.need-docs-modal-close {
  background: none;
  border: none;
  font-size: 28px;
  color: var(--color-faint);
  cursor: pointer;
  transition: all 0.2s ease;
  line-height: 1;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-sm);
}

.need-docs-modal-close:hover {
  color: var(--color-warning);
  background: var(--color-warning-soft);
}

.need-docs-modal-body {
  padding: 30px;
}

.need-docs-items-section {
  background: var(--color-warning-soft);
  border: 2px solid var(--color-warning-border);
  border-radius: var(--radius-md);
  padding: 20px;
  margin-bottom: 25px;
}

.need-docs-items-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 15px;
  flex-wrap: wrap;
  gap: 10px;
}

.need-docs-items-header h5 {
  font-size: 14px;
  color: var(--color-warning);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 8px;
}

.add-item-buttons {
  display: flex;
  gap: 10px;
}

.btn-add-item {
  padding: 8px 16px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.btn-add-question {
  background: var(--color-brand-solid);
  color: white;
}

.btn-add-question:hover {
  background: var(--color-brand-strong);
  transform: translateY(-1px);
}

.btn-add-document {
  background: var(--color-success-solid);
  color: white;
}

.btn-add-document:hover {
  background: var(--color-success-solid);
  transform: translateY(-1px);
}

.items-list {
  max-height: 400px;
  overflow-y: auto;
}

.empty-message {
  text-align: center;
  padding: 40px 20px;
  color: var(--color-warning);
  font-size: 14px;
  font-style: italic;
}

.empty-message i {
  font-size: 32px;
  display: block;
  margin-bottom: 10px;
  opacity: 0.5;
}

.item-card {
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 12px 15px;
  margin-bottom: 10px;
  display: flex;
  align-items: start;
  gap: 12px;
  transition: all 0.3s ease;
  cursor: pointer;
}

.item-card:hover {
  border-color: var(--color-warning);
  box-shadow: 0 2px 8px rgba(245, 158, 11, 0.2);
}

.item-card.selected {
  border-color: var(--color-warning);
  background: var(--color-warning-soft);
}

.question-checkbox {
  position: relative;
  display: inline-block;
  width: 20px;
  height: 20px;
  flex-shrink: 0;
  margin-top: 2px;
}

.need-docs-checkbox {
  position: absolute;
  opacity: 0;
  cursor: pointer;
  width: 20px;
  height: 20px;
  margin: 0;
}

.question-checkbox-mark {
  position: absolute;
  top: 0;
  left: 0;
  height: 20px;
  width: 20px;
  background-color: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: 4px;
  transition: all 0.3s ease;
}

.question-checkbox:hover .question-checkbox-mark {
  border-color: var(--color-warning);
}

.need-docs-checkbox:checked ~ .question-checkbox-mark {
  background: linear-gradient(
    135deg,
    var(--color-warning) 0%,
    var(--color-warning) 100%
  );
  border-color: var(--color-warning);
}

.question-checkbox-mark:after {
  content: "";
  position: absolute;
  display: none;
  left: 6px;
  top: 2px;
  width: 5px;
  height: 10px;
  border: solid white;
  border-width: 0 2px 2px 0;
  transform: rotate(45deg);
}

.need-docs-checkbox:checked ~ .question-checkbox-mark:after {
  display: block;
}

.selectable-question-content,
.selectable-document-content {
  flex: 1;
}

.selectable-question-title,
.selectable-document-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 4px;
}

.selectable-question-type {
  display: inline-block;
  background: var(--color-brand-soft);
  color: var(--color-brand);
  padding: 2px 8px;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  margin-top: 4px;
}

.document-icon {
  width: 32px;
  height: 32px;
  border-radius: var(--radius-sm);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 14px;
  flex-shrink: 0;
  background: linear-gradient(
    135deg,
    var(--color-warning) 0%,
    var(--color-warning) 100%
  );
}

.item-actions {
  display: flex;
  gap: 6px;
  flex-shrink: 0;
}

.btn-edit-item,
.btn-remove-item {
  background: var(--color-danger-soft);
  color: var(--color-danger);
  border: none;
  border-radius: var(--radius-sm);
  padding: 6px 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
}

.btn-edit-item {
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.btn-edit-item:hover {
  background: var(--color-brand-solid);
  color: white;
}

.btn-remove-item:hover {
  background: var(--color-danger-border);
  color: white;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  margin-bottom: 10px;
  color: var(--color-ink);
  font-weight: 600;
  font-size: 14px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.form-group label i {
  color: var(--color-warning);
}

.form-group textarea {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  transition: all 0.3s ease;
  font-family: inherit;
  resize: vertical;
}

.form-group textarea:focus {
  outline: none;
  border-color: var(--color-warning);
  box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
}

.need-docs-summary {
  background: var(--color-warning-soft);
  border: 2px solid var(--color-warning-border);
  border-radius: var(--radius-md);
  padding: 20px;
}

.need-docs-summary h5 {
  font-size: 14px;
  color: var(--color-warning);
  font-weight: 600;
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.summary-items {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.summary-empty {
  color: var(--color-warning);
  font-size: 14px;
  font-style: italic;
}

.summary-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  background: var(--color-warning-solid);
  color: white;
  border-radius: var(--radius-xl);
  font-size: 14px;
  font-weight: 500;
}

.need-docs-modal-footer {
  padding: 20px 30px;
  border-top: 2px solid var(--color-border);
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  background: var(--color-surface-soft);
}

.btn {
  padding: 12px 24px;
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

.btn-secondary {
  background: var(--color-border);
  color: var(--color-text);
}

.btn-secondary:hover:not(:disabled) {
  background: var(--color-border-strong);
}

.btn-primary {
  background: linear-gradient(
    135deg,
    var(--color-warning) 0%,
    var(--color-warning) 100%
  );
  color: white;
  box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Add Item Modal Styles */
.add-item-modal {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  z-index: 2100;
  align-items: center;
  justify-content: center;
}

.add-item-modal.show {
  display: flex;
}

.add-item-modal-content {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: 0;
  max-width: 600px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
  animation: modalSlideIn 0.3s ease;
}

.add-item-modal-header {
  padding: 25px 30px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--color-surface-soft);
}

.add-item-modal-header h3 {
  font-size: 20px;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 0;
}

.add-item-modal-header h3 i {
  color: var(--color-brand);
  font-size: 22px;
}

.add-item-modal-close {
  background: none;
  border: none;
  font-size: 28px;
  color: var(--color-faint);
  cursor: pointer;
  transition: all 0.2s ease;
  line-height: 1;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-sm);
}

.add-item-modal-close:hover {
  color: var(--color-brand);
  background: var(--color-brand-soft);
}

.add-item-modal-body {
  padding: 30px;
  /*display: grid;*/
  /*grid-template-columns: 1fr 1fr;*/
  /*gap: 20px;*/
}

.add-item-field {
  display: flex;
  flex-direction: column;
  margin-bottom: 25px;
}

.add-item-field:last-child {
  margin-bottom: 0;
}

.add-item-field label {
  display: block;
  font-weight: 600;
  color: var(--color-ink);
  font-size: 14px;
  margin-bottom: 8px;
}

.add-item-field input,
.add-item-field textarea,
.add-item-field select {
  width: 100%;
  padding: 10px 14px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  outline: none;
  transition: all 0.3s ease;
  font-family: inherit;
  background: var(--color-surface);
}

.add-item-field textarea {
  resize: vertical;
  min-height: 60px;
}

.add-item-field input:focus,
.add-item-field textarea:focus,
.add-item-field select:focus {
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.file-document-types-select {
  min-height: 120px;
  padding: 10px 14px;
}

.answer-options-container {
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 15px;
}

.answer-option-row {
  display: flex;
  gap: 10px;
  margin-bottom: 10px;
}

.answer-option-row:last-of-type {
  margin-bottom: 0;
}

.answer-option-input {
  flex: 1;
  padding: 8px 12px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  font-size: 14px;
  outline: none;
  transition: all 0.3s ease;
}

.answer-option-input:focus {
  border-color: var(--color-brand);
  box-shadow: 0 0 0 2px rgba(var(--color-brand-rgb), 0.1);
}

.btn-remove-option {
  background: var(--color-danger-solid);
  color: white;
  border: none;
  border-radius: 4px;
  padding: 0px 16px;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-remove-option:hover:not(:disabled) {
  background: var(--color-danger-solid);
}

.btn-remove-option:disabled {
  background: var(--color-border-strong);
  cursor: not-allowed;
}

.btn-add-option {
  background: var(--color-brand-solid);
  color: white;
  border: none;
  border-radius: var(--radius-sm);
  padding: 8px 12px;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-top: 5px;
}

.btn-add-option:hover {
  background: var(--color-brand-strong);
}

.add-item-modal-footer {
  padding: 20px 30px;
  border-top: 2px solid var(--color-border);
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  background: var(--color-surface-soft);
}

.btn-modal-cancel {
  padding: 12px 24px;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: var(--color-border);
  color: var(--color-text);
}

.btn-modal-cancel:hover {
  background: var(--color-border-strong);
}

.btn-modal-save {
  padding: 12px 24px;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: var(--color-brand-solid);
  color: white;
}

.btn-modal-save:hover {
  background: var(--color-brand-strong);
}

@media (max-width: 768px) {
  .need-docs-modal-content,
  .add-item-modal-content {
    width: 95%;
    margin: 10px;
  }

  .add-item-modal-body form {
    grid-template-columns: 1fr;
  }

  .add-item-buttons {
    flex-direction: column;
    width: 100%;
  }

  .btn-add-item {
    width: 100%;
    justify-content: center;
  }
}
</style>
