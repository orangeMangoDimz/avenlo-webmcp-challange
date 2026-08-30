<template>
  <Teleport to="body">
    <transition name="modal">
      <div v-if="modelValue" class="tag-modal" @click.self="close">
        <div class="tag-modal-content">
          <div class="tag-modal-header">
            <h3><i class="fas fa-tag"></i> {{ t("leadsSearchTag_title") }}</h3>
            <button class="tag-modal-close" @click="close">×</button>
          </div>

          <div class="tag-modal-body">
            <div class="form-group">
              <label for="tagName">{{ t("leadsSearchTag_labelName") }}</label>
              <input
                id="tagName"
                v-model="tagName"
                type="text"
                :placeholder="t('leadsSearchTag_phName')"
                @keypress.enter="confirm"
              />
            </div>

            <div class="form-group">
              <label for="tagSearch">{{
                t("leadsSearchTag_labelKeywords")
              }}</label>
              <input
                id="tagSearch"
                v-model="searchKeywords"
                type="text"
                :placeholder="t('leadsSearchTag_phKeywords')"
                @keypress.enter="confirm"
              />
              <span class="help-text">
                {{ t("leadsSearchTag_keywordsHelp") }}
              </span>
            </div>
          </div>

          <div class="tag-modal-footer">
            <button class="btn-modal btn-modal-cancel" @click="close">
              {{ t("common_cancel") }}
            </button>
            <button
              class="btn-modal btn-modal-create"
              @click="confirm"
              :disabled="!tagName || !searchKeywords"
            >
              {{ t("leadsSearchTag_btnCreate") }}
            </button>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>

<script setup>
import { ref } from "vue";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t } = useAdminI18n();

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["update:modelValue", "confirm"]);

const tagName = ref("");
const searchKeywords = ref("");

const close = () => {
  emit("update:modelValue", false);
  // Reset form
  tagName.value = "";
  searchKeywords.value = "";
};

const confirm = () => {
  if (!tagName.value || !searchKeywords.value) {
    alert(t("leadsSearchTag_alertBothRequired"));
    return;
  }

  emit("confirm", {
    tagName: tagName.value,
    searchKeywords: searchKeywords.value,
  });

  close();
};
</script>

<style scoped>
.tag-modal {
  display: flex;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  z-index: 1000;
  align-items: center;
  justify-content: center;
}

.tag-modal-content {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: 0;
  max-width: 400px;
  width: 90%;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
}

.tag-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 25px;
  border-bottom: 2px solid var(--color-border);
}

.tag-modal-header h3 {
  font-size: 18px;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0;
}

.tag-modal-header h3 i {
  color: var(--color-brand);
}

.tag-modal-close {
  background: none;
  border: none;
  font-size: 24px;
  color: var(--color-faint);
  cursor: pointer;
  transition: all 0.2s ease;
  line-height: 1;
}

.tag-modal-close:hover {
  color: var(--color-brand);
}

.tag-modal-body {
  padding: 25px;
}

.form-group {
  margin-bottom: 20px;
}

.form-group:last-child {
  margin-bottom: 0;
}

.form-group label {
  display: block;
  font-weight: 600;
  color: var(--color-text);
  font-size: 14px;
  margin-bottom: 8px;
}

.form-group input {
  width: 100%;
  padding: 10px 14px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  outline: none;
  transition: all 0.3s ease;
}

.form-group input:focus {
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.help-text {
  display: block;
  margin-top: 5px;
  font-size: 14px;
  color: var(--color-muted);
  font-style: italic;
}

.tag-modal-footer {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  padding: 20px 25px;
  border-top: 2px solid var(--color-border);
  background: var(--color-surface-soft);
}

.btn-modal {
  padding: 10px 20px;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
}

.btn-modal-cancel {
  background: var(--color-border);
  color: var(--color-text);
}

.btn-modal-cancel:hover {
  background: var(--color-border-strong);
}

.btn-modal-create {
  background: var(--color-brand-solid);
  color: white;
  box-shadow: 0 2px 8px rgba(var(--color-brand-rgb), 0.3);
}

.btn-modal-create:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.4);
}

.btn-modal-create:disabled {
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

.modal-enter-active .tag-modal-content,
.modal-leave-active .tag-modal-content {
  transition: transform 0.3s ease;
}

.modal-enter-from .tag-modal-content,
.modal-leave-to .tag-modal-content {
  transform: translateY(-20px);
}
</style>
