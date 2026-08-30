<template>
  <div class="question-list">
    <div v-if="groupedQuestions.length === 0" class="empty-state">
      <i class="fas fa-question-circle"></i>
      <p>{{ t("withdrawKycQ_empty") }}</p>
      <button
        v-if="hasAddCategoryPermission"
        class="btn btn-primary"
        @click="$emit('add-category')"
      >
        <i class="fas fa-plus"></i> {{ t("withdrawKycQ_btnAddFirstCategory") }}
      </button>
    </div>

    <!-- Category Items -->
    <div
      v-for="category in groupedQuestions"
      :key="category.id"
      class="category-item"
      :class="{ expanded: expandedCategories.includes(category.id) }"
    >
      <div class="category-header" @click="toggleCategory(category.id)">
        <div class="category-info">
          <div class="category-indicator"></div>
          <h4 class="category-title">{{ category.categoryName }}</h4>
          <span class="category-count">{{ category.questions.length }}</span>
          <button
            class="category-collapse-btn"
            :title="
              expandedCategories.includes(category.id)
                ? t('withdrawKycQ_titleCollapse')
                : t('withdrawKycQ_titleExpand')
            "
          >
            <i
              class="fas"
              :class="
                expandedCategories.includes(category.id)
                  ? 'fa-chevron-up'
                  : 'fa-chevron-down'
              "
            ></i>
          </button>
        </div>
        <div
          v-if="showCategoryActions(category)"
          class="category-actions"
          @click.stop
        >
          <button
            v-if="hasAddQuestionPermission"
            class="btn btn-success btn-category-action"
            @click="handleAddQuestion(category.id)"
          >
            <i class="fas fa-plus"></i> {{ t("withdrawKycQ_addQuestion") }}
          </button>
          <button
            v-if="hasEditCategoryPermission"
            class="btn btn-secondary btn-category-icon"
            style="color: var(--color-brand)"
            @click="handleEditCategory(category)"
            :title="t('withdrawKycQ_titleEditCategory')"
          >
            <i class="fas fa-edit"></i>
          </button>
          <button
            v-if="hasDeleteCategoryPermission && !isLockedItem(category)"
            class="btn btn-secondary btn-category-icon"
            style="color: var(--color-danger)"
            @click="handleDeleteCategory(category.id)"
            :title="t('withdrawKycQ_titleDeleteCategory')"
          >
            <i class="fas fa-trash"></i>
          </button>
        </div>
      </div>

      <div class="category-questions">
        <div
          v-for="(question, qIdx) in category.questions"
          :key="question.id"
          class="question-item"
        >
          <div class="question-number">{{ qIdx + 1 }}</div>
          <div class="question-content">
            <div class="question-text">{{ question.questionText }}</div>
            <div v-if="question.helpText" class="question-help">
              {{ question.helpText }}
            </div>
          </div>
          <div class="question-type">{{ question.questionType }}</div>
          <div v-if="question.isRequired" class="required-badge">
            {{ t("withdrawKycQ_required") }}
          </div>
          <div
            v-if="question.isActive !== undefined && question.isActive !== null"
            :class="[
              'status-badge',
              question.isActive ? 'active-badge' : 'inactive-badge',
            ]"
          >
            {{
              question.isActive
                ? t("withdrawKycTpl_status_active")
                : t("withdrawKycTpl_status_inactive")
            }}
          </div>
          <div v-if="showQuestionActions(question)" class="question-actions">
            <button
              v-if="hasEditQuestionPermission && !isLockedItem(question)"
              class="action-btn edit"
              :title="t('withdrawKycQ_titleEditQuestion')"
              @click="handleEditQuestion(question)"
            >
              <i class="fas fa-edit"></i>
            </button>
            <button
              v-if="hasDuplicateQuestionPermission"
              class="action-btn duplicate"
              :title="t('withdrawKycQ_titleDuplicateQuestion')"
              @click="handleDuplicateQuestion(question.id)"
            >
              <i class="fas fa-copy"></i>
            </button>
            <button
              v-if="hasDeleteQuestionPermission && !isLockedItem(question)"
              class="action-btn delete"
              :title="t('withdrawKycQ_titleDeleteQuestion')"
              @click="handleDeleteQuestion(question.id)"
            >
              <i class="fas fa-trash"></i>
            </button>
          </div>
          <div class="additional-info">
            <div class="info-row">
              <span class="info-label validation"
                ><i class="fas fa-search"></i>
                {{ t("withdrawKycQ_validation") }}</span
              >
              <span class="info-badge validation">{{
                question.validationRules
              }}</span>
            </div>
            <div class="timestamps">
              <span
                ><i class="fas fa-calendar"></i> {{ t("withdrawKycQ_created") }}
                {{ question.createdAt }}</span
              >
              <span
                ><i class="fas fa-sync"></i> {{ t("withdrawKycQ_updated") }}
                {{ question.updatedAt }}</span
              >
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Question Modal -->
    <QuestionModal
      v-if="showQuestionModal"
      :question="currentQuestion"
      :category-id="currentCategoryId"
      :template-id="templateId"
      :question-service="questionService"
      @close="closeQuestionModal"
      @save="handleQuestionSave"
    />

    <!-- Category Modal -->
    <CategoryModal
      v-if="showCategoryModal"
      :template-id="templateId"
      :category="currentCategory"
      :category-service="categoryService"
      @close="closeCategoryModal"
      @save="handleCategorySave"
    />
  </div>
</template>

<script setup>
import { ref, computed } from "vue";
import {
  withdrawKycQuestionService,
  withdrawKycCategoryService,
} from "@/services/withdrawKycTemplateService";
import QuestionModal from "./WithdrawQuestionModal.vue";
import CategoryModal from "./WithdrawCategoryModal.vue";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { translateApiErrorMessage } from "@/i18n/adminI18nBridge";

const { t, tParams } = useAdminI18n();

const props = defineProps({
  templateId: {
    type: Number,
    required: true,
  },
  questions: {
    type: Array,
    default: () => [],
  },
  categories: {
    type: Array,
    default: () => [],
  },
  hasAddCategoryPermission: {
    type: Boolean,
    default: false,
  },
  hasEditCategoryPermission: {
    type: Boolean,
    default: false,
  },
  hasDeleteCategoryPermission: {
    type: Boolean,
    default: false,
  },
  hasAddQuestionPermission: {
    type: Boolean,
    default: false,
  },
  hasEditQuestionPermission: {
    type: Boolean,
    default: false,
  },
  hasDuplicateQuestionPermission: {
    type: Boolean,
    default: false,
  },
  hasDeleteQuestionPermission: {
    type: Boolean,
    default: false,
  },
  questionService: {
    type: Object,
    default: () => withdrawKycQuestionService,
  },
  categoryService: {
    type: Object,
    default: () => withdrawKycCategoryService,
  },
});

const emit = defineEmits(["refresh", "add-category"]);

const expandedCategories = ref([]);
const showQuestionModal = ref(false);
const showCategoryModal = ref(false);
const currentQuestion = ref(null);
const currentCategoryId = ref(null);
const currentCategory = ref(null);

const groupedQuestions = computed(() => {
  const categories = Array.isArray(props.categories) ? props.categories : [];
  const questions = Array.isArray(props.questions) ? props.questions : [];
  return categories.map((category) => ({
    ...category,
    questions: questions.filter((q) => q.categoryId === category.id),
  }));
});

const isLockedItem = (item) => {
  if (!item || typeof item !== "object") return false;
  return Boolean(item.isLocked ?? item.islocked);
};

const showCategoryActions = (category) => {
  return (
    props.hasAddQuestionPermission ||
    props.hasEditCategoryPermission ||
    props.hasDeleteCategoryPermission
  );
};

const showQuestionActions = (question) => {
  if (isLockedItem(question)) {
    return props.hasDuplicateQuestionPermission;
  }

  return (
    props.hasEditQuestionPermission ||
    props.hasDuplicateQuestionPermission ||
    props.hasDeleteQuestionPermission
  );
};

const toggleCategory = (categoryId) => {
  const index = expandedCategories.value.indexOf(categoryId);
  if (index > -1) {
    expandedCategories.value.splice(index, 1);
  } else {
    expandedCategories.value.push(categoryId);
  }
};

const handleAddQuestion = (categoryId) => {
  currentCategoryId.value = categoryId;
  currentQuestion.value = null;
  showQuestionModal.value = true;
};

const handleEditQuestion = (question) => {
  if (isLockedItem(question)) {
    return;
  }

  currentQuestion.value = question;
  currentCategoryId.value = question.categoryId;
  showQuestionModal.value = true;
};

const handleDuplicateQuestion = async (questionId) => {
  if (confirm(t("withdrawKycQ_confirm_duplicate"))) {
    try {
      const response =
        await props.questionService.duplicateQuestion(questionId);
      if (response.success) {
        alert(t("withdrawKycQ_alert_dupOk"));
        emit("refresh");
      } else {
        const raw = response.message || t("common_unknownError");
        alert(
          tParams("withdrawKycQ_alert_dupFailed", "Failed: {msg}", {
            msg: translateApiErrorMessage(response.errorCode, raw),
          }),
        );
      }
    } catch (error) {
      console.error("Failed to duplicate question:", error);
      const data = error?.response?.data ?? error;
      const rawMsg =
        data?.message || error?.message || t("common_unknownError");
      const msg = translateApiErrorMessage(data?.errorCode, rawMsg);
      alert(tParams("withdrawKycQ_alert_dupFailed", "Failed: {msg}", { msg }));
    }
  }
};

const handleDeleteQuestion = async (questionId) => {
  if (confirm(t("withdrawKycQ_confirm_deleteQuestion"))) {
    try {
      const response = await props.questionService.deleteQuestion(questionId);
      if (response.success) {
        alert(t("withdrawKycQ_alert_delQOk"));
        emit("refresh");
      } else {
        const raw = response.message || t("common_unknownError");
        alert(
          tParams("withdrawKycQ_alert_delQFailed", "Failed: {msg}", {
            msg: translateApiErrorMessage(response.errorCode, raw),
          }),
        );
      }
    } catch (error) {
      console.error("Failed to delete question:", error);
      const data = error?.response?.data ?? error;
      const rawMsg =
        data?.message || error?.message || t("common_unknownError");
      const msg = translateApiErrorMessage(data?.errorCode, rawMsg);
      alert(tParams("withdrawKycQ_alert_delQFailed", "Failed: {msg}", { msg }));
    }
  }
};

const handleEditCategory = (category) => {
  currentCategory.value = category;
  showCategoryModal.value = true;
};

const handleDeleteCategory = async (categoryId) => {
  const category = props.categories.find((c) => c.id === categoryId);
  const categoryName = category?.categoryName || category?.name || "—";
  if (
    confirm(
      tParams("withdrawKycQ_confirm_deleteCategory", "...", {
        name: categoryName,
      }),
    )
  ) {
    try {
      const response = await props.categoryService.deleteCategory(categoryId);
      if (response.success) {
        alert(t("withdrawKycQ_alert_delCatOk"));
        emit("refresh");
      } else {
        const raw = response.message || t("common_unknownError");
        alert(
          tParams("withdrawKycQ_alert_delCatFailed", "Failed: {msg}", {
            msg: translateApiErrorMessage(response.errorCode, raw),
          }),
        );
      }
    } catch (error) {
      console.error("Failed to delete category:", error);
      const data = error?.response?.data ?? error;
      const rawMsg =
        data?.message || error?.message || t("common_unknownError");
      const msg = translateApiErrorMessage(data?.errorCode, rawMsg);
      alert(
        tParams("withdrawKycQ_alert_delCatFailed", "Failed: {msg}", { msg }),
      );
    }
  }
};

const closeCategoryModal = () => {
  showCategoryModal.value = false;
  currentCategory.value = null;
};

const handleCategorySave = () => {
  closeCategoryModal();
  emit("refresh");
};

const closeQuestionModal = () => {
  showQuestionModal.value = false;
  currentQuestion.value = null;
  currentCategoryId.value = null;
};

const handleQuestionSave = () => {
  closeQuestionModal();
  emit("refresh");
};
</script>

<style scoped>
.question-list {
  margin-top: 20px;
}

.empty-state {
  padding: 60px 20px;
  text-align: center;
  color: var(--color-faint);
}

.empty-state i {
  font-size: 48px;
  /*margin-bottom: 15px;*/
  display: block;
}

.empty-state p {
  font-size: 16px;
  margin-bottom: 20px;
}

.category-item {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  margin-bottom: 12px;
  overflow: hidden;
  transition: all 0.2s ease;
}

.category-item:last-child {
  margin-bottom: 0;
}

.category-item:hover {
  border-color: var(--color-brand);
}

.category-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 15px 20px;
  background: var(--color-surface-soft);
  cursor: pointer;
  transition: all 0.2s ease;
  user-select: none;
}

.category-header:hover {
  background: var(--color-brand-soft);
}

.category-info {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
}

.category-indicator {
  width: 4px;
  height: 24px;
  background: var(--color-brand-solid);
  border-radius: 2px;
}

.category-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
}

.category-count {
  background: var(--color-brand-solid);
  color: white;
  padding: 4px 10px;
  border-radius: var(--radius-lg);
  font-size: 11px;
  font-weight: bold;
  box-shadow: 0 2px 6px rgba(var(--color-brand-rgb), 0.3);
}

.category-collapse-btn {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  color: var(--color-muted);
  width: 28px;
  height: 28px;
  border-radius: var(--radius-sm);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
  font-size: 12px;
  margin-left: 12px;
}

.category-collapse-btn:hover {
  background: var(--color-brand-soft);
  color: var(--color-brand);
  border-color: var(--color-brand);
}

.category-actions {
  display: flex;
  gap: 6px;
}

.category-questions {
  max-height: 0;
  overflow: hidden;
  transition: max-height 0.3s ease;
  background: var(--color-surface);
}

.category-item.expanded .category-questions {
  max-height: 2000px;
}

.question-item {
  background: var(--color-surface-soft);
  padding: 15px 20px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  margin: 12px 15px;
  display: grid;
  grid-template-columns: auto 1fr auto auto auto auto;
  gap: 15px;
  align-items: start;
  transition: all 0.2s ease;
}

.question-item:hover {
  background: var(--color-brand-soft);
  border-color: var(--color-brand);
}

.question-number {
  width: 30px;
  height: 30px;
  background: var(--color-brand-solid);
  color: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: bold;
  flex-shrink: 0;
  box-shadow: 0 2px 6px rgba(var(--color-brand-rgb), 0.3);
}

.question-content {
  flex: 1;
}

.question-text {
  font-size: 13px;
  font-weight: 500;
  color: var(--color-ink);
  margin-bottom: 3px;
}

.question-help {
  font-size: 12px;
  color: var(--color-muted);
  margin-top: 2px;
}

.question-type {
  background: var(--color-brand-soft);
  color: var(--color-brand);
  padding: 4px 10px;
  border-radius: var(--radius-lg);
  font-size: 11px;
  font-weight: 600;
  border: 1px solid var(--color-brand-soft);
}

.required-badge {
  background: var(--color-danger-solid);
  color: white;
  padding: 4px 8px;
  border-radius: var(--radius-lg);
  font-size: 10px;
  font-weight: bold;
  box-shadow: 0 1px 4px rgba(245, 101, 101, 0.3);
}

.status-badge {
  padding: 4px 8px;
  border-radius: var(--radius-lg);
  font-size: 10px;
  font-weight: bold;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.2);
}

.active-badge {
  background: var(--color-success-solid);
  color: white;
  box-shadow: 0 1px 4px rgba(72, 187, 120, 0.3);
}

.inactive-badge {
  background: var(--color-faint);
  color: white;
  box-shadow: 0 1px 4px rgba(160, 174, 192, 0.3);
}

.question-actions {
  display: flex;
  gap: 3px;
}

.action-btn {
  width: 28px;
  height: 28px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
  font-size: 12px;
  background: var(--color-surface);
}

.action-btn.edit {
  color: var(--color-brand);
}

.action-btn.duplicate {
  color: var(--color-success);
}

.action-btn.delete {
  color: var(--color-danger);
}

.action-btn.edit:hover {
  background: var(--color-brand-soft);
  border-color: var(--color-brand);
  transform: scale(1.1);
}

.action-btn.duplicate:hover {
  background: var(--color-success-soft);
  border-color: var(--color-success);
  transform: scale(1.1);
}

.action-btn.delete:hover {
  background: var(--color-danger-soft);
  border-color: var(--color-danger);
  transform: scale(1.1);
}

.additional-info {
  grid-column: 2 / -1;
  display: flex;
  flex-direction: column;
  gap: 5px;
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px solid var(--color-border);
}

.info-row {
  display: flex;
  align-items: center;
  gap: 5px;
  flex-wrap: wrap;
}

.info-label {
  font-size: 11px;
  font-weight: 600;
  margin-right: 5px;
}

.info-label.validation {
  color: #17a2b8;
}

.info-badge {
  padding: 3px 8px;
  border-radius: var(--radius-sm);
  font-size: 10px;
  font-weight: 500;
}

.info-badge.validation {
  background: var(--color-info-soft);
  color: var(--color-info);
  border: 1px solid #7dd3fc;
}

.timestamps {
  display: flex;
  align-items: center;
  gap: 15px;
  font-size: 10px;
  color: var(--color-faint);
  margin-top: 5px;
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

.btn-primary {
  background: var(--color-brand-solid);
  color: white;
}

.btn-success {
  background: var(--color-success-solid);
  color: white;
}

.btn-success:hover {
  background: var(--color-success-solid);
}

.btn-secondary {
  background: var(--color-border);
  color: var(--color-text);
}

.btn-category-action {
  font-size: 12px;
  padding: 6px 12px;
}

.btn-category-icon {
  font-size: 12px;
  padding: 6px 10px;
}
</style>
