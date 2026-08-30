<template>
  <Teleport to="body">
    <div :class="['tag-modal', { show: modelValue }]" @click="close">
      <div class="tag-modal-content" @click.stop>
        <div class="tag-modal-header">
          <h3><i class="fas fa-tag"></i> Create Search Tag</h3>
          <button class="tag-modal-close" @click="close">×</button>
        </div>

        <div class="tag-modal-body">
          <div class="form-group">
            <label for="tag-name">Tag Name</label>
            <input
              id="tag-name"
              ref="tagNameInput"
              type="text"
              v-model="formData.tagName"
              placeholder="e.g., High Value, Crypto Only"
              @keyup.enter="confirm"
            />
          </div>

          <div class="form-group">
            <label for="search-keywords">Search Keywords</label>
            <input
              id="search-keywords"
              type="text"
              v-model="formData.searchKeywords"
              placeholder="e.g., bitcoin, ethereum, crypto"
              @keyup.enter="confirm"
            />
            <small class="help-text">
              <i class="fas fa-info-circle"></i> Enter keywords separated by
              commas
            </small>
          </div>
        </div>

        <div class="tag-modal-footer">
          <button
            class="btn btn-secondary"
            @click="close"
            :disabled="processing"
          >
            Cancel
          </button>
          <button
            class="btn btn-primary"
            @click="confirm"
            :disabled="
              processing ||
              !formData.tagName.trim() ||
              !formData.searchKeywords.trim()
            "
          >
            <i
              :class="processing ? 'fas fa-spinner fa-spin' : 'fas fa-plus'"
            ></i>
            {{ processing ? "Creating..." : "Create Tag" }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, watch, nextTick } from "vue";

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  processing: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["update:modelValue", "confirm"]);

const formData = ref({
  tagName: "",
  searchKeywords: "",
});

const tagNameInput = ref(null);

// 关闭Modal
const close = () => {
  if (!props.processing) {
    emit("update:modelValue", false);
    formData.value = {
      tagName: "",
      searchKeywords: "",
    };
  }
};

// 确认创建
const confirm = () => {
  const tagName = formData.value.tagName.trim();
  const keywords = formData.value.searchKeywords.trim();

  if (!tagName) {
    alert("Please enter a tag name");
    return;
  }

  if (!keywords) {
    alert("Please enter search keywords");
    return;
  }

  emit("confirm", {
    tagName,
    searchKeywords: keywords,
    transactionType: "deposit",
  });
};

// 监听Modal打开，自动聚焦
watch(
  () => props.modelValue,
  (newVal) => {
    if (newVal) {
      nextTick(() => {
        tagNameInput.value?.focus();
      });
    }
  },
);
</script>

<style scoped>
.tag-modal {
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

.tag-modal.show {
  display: flex;
}

.tag-modal-content {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  max-width: 500px;
  width: 90%;
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

.tag-modal-header {
  padding: 25px 30px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--color-surface-soft);
}

.tag-modal-header h3 {
  font-size: 20px;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 0;
}

.tag-modal-header h3 i {
  color: var(--color-brand);
}

.tag-modal-close {
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

.tag-modal-close:hover {
  color: var(--color-brand);
  background: var(--color-brand-soft);
}

.tag-modal-body {
  padding: 30px;
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
}

.form-group input {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  transition: all 0.3s ease;
}

.form-group input:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.help-text {
  display: block;
  margin-top: 8px;
  color: var(--color-muted);
  font-size: 12px;
}

.tag-modal-footer {
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
  background: var(--color-brand-solid);
  color: white;
  box-shadow: 0 2px 8px rgba(var(--color-brand-rgb), 0.3);
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.4);
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

@media (max-width: 768px) {
  .tag-modal-content {
    width: 95%;
    margin: 10px;
  }

  .tag-modal-footer {
    flex-direction: column;
  }

  .btn {
    width: 100%;
    justify-content: center;
  }
}
</style>
