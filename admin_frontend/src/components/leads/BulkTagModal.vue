<template>
  <Teleport to="body">
    <transition name="modal">
      <div v-if="modelValue" class="bulk-tag-modal" @click.self="close">
        <div class="bulk-tag-modal-content">
          <div class="bulk-tag-modal-header">
            <h3><i class="fas fa-tags"></i> {{ t("leadsBulkTag_title") }}</h3>
            <button class="bulk-tag-modal-close" @click="close">×</button>
          </div>

          <div class="bulk-tag-modal-body">
            <!-- Tag Selection or Input -->
            <div class="bulk-tag-input-section">
              <label>{{ t("leadsBulkTag_labelSelect") }}</label>
              <select
                v-model="selectedTagId"
                @change="handleTagSelect"
                class="tag-select"
              >
                <option value="">
                  {{ t("leadsBulkTag_optionPlaceholder") }}
                </option>
                <option
                  v-for="tag in availableTags"
                  :key="tag.id"
                  :value="tag.id"
                >
                  {{ tag.tagName }}
                </option>
              </select>

              <input
                v-model="newTagName"
                type="text"
                :placeholder="t('leadsBulkTag_phNewTag')"
                :disabled="!!selectedTagId"
              />
            </div>

            <!-- Preview Section -->
            <div class="bulk-tag-preview">
              <h4>
                <i class="fas fa-eye"></i>
                {{
                  tParams(
                    "leadsBulkTag_previewTitle",
                    "Preview — This tag will be added to {n} lead(s)",
                    { n: String(selectedLeads.length) },
                  )
                }}
              </h4>
              <div class="bulk-tag-preview-item">
                <i class="fas fa-tag"></i>
                <span>{{ previewTagName }}</span>
              </div>
            </div>

            <!-- Selected Leads Info -->
            <div class="selected-leads-list">
              <h4>
                <i class="fas fa-users"></i>
                {{ t("leadsBulkTag_selectedLeads") }}
                <span class="count-badge">{{ selectedLeads.length }}</span>
              </h4>
              <div class="selected-leads-container">
                <div
                  v-for="lead in selectedLeads"
                  :key="lead.leadId"
                  class="selected-lead-item"
                >
                  <div class="selected-lead-avatar">
                    {{ getInitials(lead.firstName, lead.lastName) }}
                  </div>
                  <div class="selected-lead-info">
                    <div class="selected-lead-name">
                      {{ lead.firstName }} {{ lead.lastName }}
                    </div>
                    <div class="selected-lead-email">{{ lead.email }}</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="bulk-tag-modal-footer">
            <button class="btn-modal-tag btn-modal-tag-cancel" @click="close">
              {{ t("common_cancel") }}
            </button>
            <button
              class="btn-modal-tag btn-modal-tag-add"
              @click="confirm"
              :disabled="!selectedTagId && !newTagName"
            >
              <i class="fas fa-tag"></i> {{ t("leadsBulkTag_btnAdd") }}
            </button>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t, tParams } = useAdminI18n();

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  selectedLeads: {
    type: Array,
    default: () => [],
  },
  availableTags: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(["update:modelValue", "confirm"]);

const selectedTagId = ref("");
const newTagName = ref("");

const previewTagName = computed(() => {
  if (selectedTagId.value) {
    const tag = props.availableTags.find((tg) => tg.id === selectedTagId.value);
    return tag ? tag.tagName : t("leadsBulkTag_previewFallback");
  }
  return newTagName.value || t("leadsBulkTag_previewFallback");
});

const handleTagSelect = () => {
  if (selectedTagId.value) {
    newTagName.value = "";
  }
};

watch(
  () => newTagName.value,
  (newVal) => {
    if (newVal) {
      selectedTagId.value = "";
    }
  },
);

const getInitials = (firstName, lastName) => {
  const first = firstName ? firstName.charAt(0) : "";
  const last = lastName ? lastName.charAt(0) : "";
  return (first + last).toUpperCase();
};

const close = () => {
  emit("update:modelValue", false);
  // Reset form
  selectedTagId.value = "";
  newTagName.value = "";
};

const confirm = () => {
  if (!selectedTagId.value && !newTagName.value) {
    alert(t("leadsBulkTag_alertNeedTag"));
    return;
  }

  emit("confirm", {
    tagId: selectedTagId.value,
    tagName: newTagName.value,
  });

  close();
};
</script>

<style scoped>
.bulk-tag-modal {
  display: flex;
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

.bulk-tag-modal-content {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  max-width: 600px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
}

.bulk-tag-modal-header {
  padding: 25px 30px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--color-warning-soft);
}

.bulk-tag-modal-header h3 {
  font-size: 20px;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 0;
}

.bulk-tag-modal-header h3 i {
  color: var(--color-warning);
  font-size: 22px;
}

.bulk-tag-modal-close {
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

.bulk-tag-modal-close:hover {
  color: var(--color-warning);
  background: var(--color-warning-soft);
}

.bulk-tag-modal-body {
  padding: 30px;
}

.bulk-tag-input-section {
  margin-bottom: 25px;
}

.bulk-tag-input-section label {
  display: block;
  font-weight: 600;
  color: var(--color-text);
  font-size: 13px;
  margin-bottom: 8px;
}

.tag-select,
.bulk-tag-input-section input {
  width: 100%;
  padding: 10px 14px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  outline: none;
  transition: all 0.3s ease;
  margin-bottom: 10px;
}

.tag-select:focus,
.bulk-tag-input-section input:focus {
  border-color: var(--color-warning);
  box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
}

.bulk-tag-input-section input:disabled {
  background: var(--color-surface-soft);
  cursor: not-allowed;
}

.bulk-tag-preview {
  background: var(--color-warning-soft);
  border: 2px solid var(--color-warning-border);
  border-radius: var(--radius-md);
  padding: 20px;
  margin-bottom: 25px;
}

.bulk-tag-preview h4 {
  font-size: 14px;
  color: var(--color-warning);
  font-weight: 600;
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.bulk-tag-preview-item {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  background: var(--color-warning-solid);
  color: white;
  border-radius: var(--radius-xl);
  font-size: 13px;
  font-weight: 500;
}

.selected-leads-list {
  background: var(--color-surface-soft);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 20px;
}

.selected-leads-list h4 {
  font-size: 14px;
  color: var(--color-text);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 15px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.count-badge {
  background: var(--color-brand-solid);
  color: white;
  padding: 2px 10px;
  border-radius: var(--radius-lg);
  font-size: 12px;
}

.selected-leads-container {
  max-height: 200px;
  overflow-y: auto;
}

.selected-lead-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px;
  background: var(--color-surface);
  border-radius: var(--radius-md);
  margin-bottom: 8px;
  border: 1px solid var(--color-border);
}

.selected-lead-item:last-child {
  margin-bottom: 0;
}

.selected-lead-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: var(--color-brand-solid);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: 600;
  font-size: 13px;
  flex-shrink: 0;
}

.selected-lead-info {
  flex: 1;
}

.selected-lead-name {
  font-weight: 600;
  color: var(--color-ink);
  font-size: 14px;
  margin-bottom: 2px;
}

.selected-lead-email {
  font-size: 12px;
  color: var(--color-muted);
}

.bulk-tag-modal-footer {
  padding: 20px 30px;
  border-top: 2px solid var(--color-border);
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  background: var(--color-surface-soft);
}

.btn-modal-tag {
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

.btn-modal-tag-cancel {
  background: var(--color-border);
  color: var(--color-text);
}

.btn-modal-tag-cancel:hover {
  background: var(--color-border-strong);
}

.btn-modal-tag-add {
  background: var(--color-warning-solid);
  color: white;
  box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
}

.btn-modal-tag-add:hover:not(:disabled) {
  background: var(--color-warning-solid);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
}

.btn-modal-tag-add:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Modal transition */
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-active .bulk-tag-modal-content,
.modal-leave-active .bulk-tag-modal-content {
  transition: transform 0.3s ease;
}

.modal-enter-from .bulk-tag-modal-content,
.modal-leave-to .bulk-tag-modal-content {
  transform: translateY(-20px);
}
</style>
