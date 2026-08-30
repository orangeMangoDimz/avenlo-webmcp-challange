<template>
  <div class="question-list">
    <div v-if="normalizedGroups.length === 0" class="empty-state">
      <i class="fas fa-question-circle"></i>
      <p>{{ t("txnSettings_gsq_listEmpty") }}</p>
    </div>

    <div
      v-for="group in normalizedGroups"
      :key="group.id"
      class="category-item"
      :class="{ expanded: expandedCategories.includes(group.id) }"
    >
      <div class="category-header" @click="toggleCategory(group.id)">
        <div class="category-info">
          <div class="category-indicator"></div>
          <h4 class="category-title">{{ group.categoryName }}</h4>
          <span class="category-count">{{ group.questions.length }}</span>
          <button
            class="category-collapse-btn"
            :title="
              expandedCategories.includes(group.id)
                ? 'Collapse Category'
                : 'Expand Category'
            "
          >
            <i
              class="fas"
              :class="
                expandedCategories.includes(group.id)
                  ? 'fa-chevron-up'
                  : 'fa-chevron-down'
              "
            ></i>
          </button>
        </div>
        <div
          v-if="canEdit && !group.disableEditing"
          class="category-actions"
          @click.stop
        >
          <button
            class="btn btn-success btn-category-action"
            @click="$emit('add-question', group)"
          >
            <i class="fas fa-plus"></i> {{ t("txnSettings_gsq_add") }}
          </button>
        </div>
      </div>

      <div class="category-questions">
        <div v-if="group.notice" class="group-notice">
          <i class="fas fa-info-circle"></i>
          <span>{{ group.notice }}</span>
        </div>

        <div v-if="group.questions.length === 0" class="group-empty">
          {{ group.emptyMessage || t("txnSettings_gsq_listEmpty") }}
        </div>

        <div
          v-for="(question, qIdx) in group.questions"
          :key="question.id || `${group.id}-${qIdx}`"
          class="question-item"
        >
          <div class="question-number">{{ qIdx + 1 }}</div>
          <div class="question-content">
            <div class="question-text">
              {{ formatQuestionName(question.name) }}
            </div>
            <div v-if="question.hintText" class="question-help">
              {{ question.hintText }}
            </div>
          </div>
          <div class="question-type">
            {{ formatQuestionType(question.questionType) }}
          </div>
          <div
            v-if="isRequired(question.validationRules)"
            class="required-badge"
          >
            {{ t("txnSettings_gsq_requiredBadge") }}
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
                ? t("txnSettings_active")
                : t("txnSettings_inactive")
            }}
          </div>
          <div v-if="canEdit && !group.disableEditing" class="question-actions">
            <button
              class="action-btn edit"
              :title="t('txnSettings_gsq_titleEditQuestion')"
              @click="$emit('edit-question', group, question)"
            >
              <i class="fas fa-edit"></i>
            </button>
            <button
              v-if="!question.isLocked"
              class="action-btn delete"
              :title="t('txnSettings_gsq_titleDeleteQuestion')"
              @click="$emit('delete-question', group, question)"
            >
              <i class="fas fa-trash"></i>
            </button>
          </div>
          <div class="additional-info">
            <div
              v-if="normalizeOptions(question.options).length > 0"
              class="info-row"
            >
              <span class="info-label">{{
                t("txnSettings_gsq_optionsLabel")
              }}</span>
              <div class="option-list">
                <span
                  v-for="(option, idx) in normalizeOptions(question.options)"
                  :key="`${question.id || group.id}-${idx}`"
                  class="option-tag"
                >
                  {{ formatOptionLabel(option) }}
                </span>
              </div>
            </div>
            <div
              v-if="String(question.validationRules || '').trim()"
              class="info-row"
            >
              <span class="info-label validation"
                ><i class="fas fa-search"></i>
                {{ t("txnSettings_gsq_validationLabel") }}</span
              >
              <span class="info-badge validation">{{
                question.validationRules
              }}</span>
            </div>
            <div class="timestamps">
              <span v-if="question.createdAt"
                ><i class="fas fa-calendar"></i>
                {{ t("txnSettings_gsq_createdLabel") }}
                {{ question.createdAt }}</span
              >
              <span v-if="question.updatedAt"
                ><i class="fas fa-sync"></i>
                {{ t("txnSettings_gsq_updatedLabel") }}
                {{ question.updatedAt }}</span
              >
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t } = useAdminI18n();

const props = defineProps({
  groups: {
    type: Array,
    default: () => [],
  },
  canEdit: {
    type: Boolean,
    default: false,
  },
});

defineEmits(["add-question", "edit-question", "delete-question"]);

const expandedCategories = ref([]);

const normalizedGroups = computed(() =>
  Array.isArray(props.groups)
    ? props.groups.map((group) => ({
        ...group,
        id: group.id || group.key,
        categoryName:
          group.categoryName ||
          group.label ||
          group.key ||
          t("txnSettings_gsq_groupFallback"),
        emptyLabel:
          group.emptyLabel ||
          String(group.label || group.key || "group").toLowerCase(),
        emptyMessage: group.emptyMessage || "",
        questions: Array.isArray(group.questions) ? group.questions : [],
        disableEditing: Boolean(group.disableEditing),
        notice: String(group.notice || ""),
      }))
    : [],
);

watch(
  normalizedGroups,
  (groups) => {
    const nextIds = groups.map((group) => group.id);
    expandedCategories.value = expandedCategories.value.length
      ? expandedCategories.value.filter((id) => nextIds.includes(id))
      : nextIds;
  },
  { immediate: true },
);

const toggleCategory = (categoryId) => {
  const index = expandedCategories.value.indexOf(categoryId);
  if (index > -1) {
    expandedCategories.value.splice(index, 1);
  } else {
    expandedCategories.value.push(categoryId);
  }
};

const formatQuestionName = (value) => {
  const text = String(value || "").trim();
  if (!text) return "-";
  return text
    .split(/[_\s]+/)
    .filter(Boolean)
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(" ");
};

const formatQuestionType = (value) => {
  const map = {
    text: () => t("txnSettings_qType_text"),
    tel: () => t("txnSettings_qType_tel"),
    date: () => t("txnSettings_qType_date"),
    email: () => t("txnSettings_qType_email"),
    single_choice: () => t("txnSettings_qType_single_choice"),
  };

  const key = String(value || "").trim();
  return map[key] ? map[key]() : formatQuestionName(key);
};

const isRequired = (value) =>
  /(^|[|,])required($|[|,])/i.test(String(value || "").trim());

const normalizeOptions = (value) => {
  if (!value) return [];
  if (Array.isArray(value)) return value.map(normalizeOption).filter(Boolean);

  if (typeof value === "string") {
    try {
      const parsed = JSON.parse(value);
      return Array.isArray(parsed)
        ? parsed.map(normalizeOption).filter(Boolean)
        : [];
    } catch {
      return value
        .split(",")
        .map((item) => item.trim())
        .filter(Boolean);
    }
  }

  return [];
};

const normalizeOption = (option) => {
  if (option && typeof option === "object") {
    const value = String(
      option.value ?? option.optionValue ?? option.label ?? option.labal ?? "",
    ).trim();
    const label = String(
      option.label ?? option.labal ?? option.value ?? option.optionValue ?? "",
    ).trim();

    if (!label && !value) {
      return null;
    }

    return {
      label: label || value,
      value: value || label,
      isEnabled: option.isEnabled !== false && option.enabled !== false,
    };
  }

  const raw = String(option || "").trim();
  if (!raw) {
    return null;
  }

  return {
    label: raw,
    value: raw,
    isEnabled: true,
  };
};

const formatOptionLabel = (option) => {
  if (!option || typeof option !== "object") {
    return String(option || "").trim();
  }

  if (option.label && option.value && option.label !== option.value) {
    return `${option.label} (${option.value})`;
  }

  return option.label || option.value || "";
};
</script>

<style scoped>
.question-list {
  margin-top: 20px;
}

.btn {
  border: none;
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: 600;
  padding: 8px 16px;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.btn-success {
  background: var(--color-success-solid);
  color: white;
}

.btn-success:hover {
  background: var(--color-success-solid);
}

.btn-category-action {
  font-size: 12px;
  padding: 6px 12px;
}

.empty-state {
  padding: 60px 20px;
  text-align: center;
  color: var(--color-faint);
}

.empty-state i {
  font-size: 48px;
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
  transition: all 0.2s ease;
  user-select: none;
  cursor: pointer;
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
  padding-bottom: 12px;
}

.category-item.expanded .category-questions {
  max-height: 3000px;
}

.group-notice {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin: 14px 15px 0;
  padding: 12px 14px;
  border: 1px solid #fbd38d;
  border-left: 4px solid #ed8936;
  border-radius: var(--radius-md);
  background: var(--color-warning-soft);
  color: var(--color-warning);
  font-size: 13px;
  line-height: 1.5;
}

.group-notice i {
  margin-top: 2px;
  color: var(--color-warning);
}

.group-empty {
  padding: 18px 20px;
  color: var(--color-faint);
  font-size: 14px;
}

.question-item {
  background: var(--color-surface-soft);
  padding: 15px 20px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  margin: 12px 15px 0;
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
  min-width: 0;
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

.action-btn.edit:hover {
  background: var(--color-brand-soft);
  border-color: var(--color-brand);
  transform: scale(1.1);
}

.action-btn.delete {
  color: var(--color-danger);
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
  font-size: 12px;
  color: var(--color-muted);
  font-weight: 600;
}

.info-badge.validation {
  background: var(--color-surface-muted);
  color: var(--color-text);
  padding: 4px 8px;
  border-radius: var(--radius-lg);
  font-size: 10px;
  font-weight: bold;
}

.option-list {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.option-tag {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  border: 1px solid #dbe3f0;
  color: var(--color-text);
  font-size: 11px;
  font-weight: 600;
}

.timestamps {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  color: var(--color-muted);
  font-size: 12px;
}

@media (max-width: 768px) {
  .category-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }

  .question-item {
    grid-template-columns: 1fr;
  }

  .additional-info {
    grid-column: auto;
  }
}
</style>
