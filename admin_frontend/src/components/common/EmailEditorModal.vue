<template>
  <Teleport to="body">
    <div :class="['email-editor-modal', { show: modelValue }]" @click="close">
      <div class="email-editor-modal-content" @click.stop>
        <div class="email-editor-modal-header">
          <h3>
            <i class="fas fa-envelope"></i> {{ t("emailEditorModal_title") }}
          </h3>
          <button class="email-editor-modal-close" @click="close">×</button>
        </div>

        <div class="email-editor-modal-body">
          <!-- Recipient Info -->
          <div class="form-group">
            <label>
              <i class="fas fa-user"></i>
              {{ t("emailEditorModal_labelRecipient") }}
            </label>
            <div class="recipient-info">
              <span class="recipient-name">{{ recipientName }}</span>
              <span class="recipient-email">{{ recipientEmail }}</span>
            </div>
          </div>

          <!-- Email Subject -->
          <div class="form-group">
            <label>
              <i class="fas fa-tag"></i>
              {{ t("emailEditorModal_labelSubject") }}
              <span class="required">{{
                t("emailEditorModal_requiredMark")
              }}</span>
            </label>
            <input
              type="text"
              v-model="emailForm.subject"
              :placeholder="t('emailEditorModal_placeholderSubject')"
              required
            />
          </div>

          <!-- Email Content -->
          <div class="form-group">
            <label for="email-content">
              <i class="fas fa-align-left"></i>
              {{ t("emailEditorModal_labelContent") }}
              <span class="required">{{
                t("emailEditorModal_requiredMark")
              }}</span>
            </label>
            <textarea
              id="email-content"
              v-model="emailForm.content"
              :placeholder="t('emailEditorModal_placeholderContent')"
              rows="10"
              required
            ></textarea>
          </div>
        </div>

        <div class="email-editor-modal-footer">
          <button
            class="btn btn-secondary"
            @click="close"
            :disabled="processing"
          >
            {{ t("emailEditorModal_btnCancel") }}
          </button>
          <button
            class="btn btn-primary"
            @click="sendEmail"
            :disabled="
              processing ||
              !emailForm.subject.trim() ||
              !emailForm.content.trim()
            "
          >
            <i
              :class="
                processing ? 'fas fa-spinner fa-spin' : 'fas fa-paper-plane'
              "
            ></i>
            {{
              processing
                ? t("emailEditorModal_btnSending")
                : t("emailEditorModal_btnSend")
            }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, watch } from "vue";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t } = useAdminI18n();

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  recipientEmail: {
    type: String,
    required: true,
  },
  recipientName: {
    type: String,
    default: "",
  },
  processing: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["update:modelValue", "confirm"]);

const emailForm = ref({
  subject: "",
  content: "",
});

// 监听modal打开，重置表单
watch(
  () => props.modelValue,
  (newVal) => {
    if (newVal) {
      emailForm.value = {
        subject: "",
        content: "",
      };
    }
  },
);

// 关闭Modal
const close = () => {
  if (!props.processing) {
    emit("update:modelValue", false);
  }
};

// 发送邮件
const sendEmail = () => {
  if (!emailForm.value.subject.trim() || !emailForm.value.content.trim()) {
    alert(t("emailEditorModal_alert_fillBoth"));
    return;
  }

  emit("confirm", {
    email: props.recipientEmail,
    subject: emailForm.value.subject.trim(),
    content: emailForm.value.content.trim(),
  });
};
</script>

<style scoped>
.email-editor-modal {
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

.email-editor-modal.show {
  display: flex;
}

.email-editor-modal-content {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  max-width: 700px;
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

.email-editor-modal-header {
  padding: 25px 30px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--color-brand-soft);
}

.email-editor-modal-header h3 {
  font-size: 20px;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 0;
}

.email-editor-modal-header h3 i {
  color: var(--color-brand);
  font-size: 22px;
}

.email-editor-modal-close {
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

.email-editor-modal-close:hover {
  color: var(--color-brand);
  background: var(--color-brand-soft);
}

.email-editor-modal-body {
  padding: 30px;
}

.form-group {
  margin-bottom: 25px;
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
  color: var(--color-brand);
}

.required {
  color: var(--color-danger);
}

.recipient-info {
  background: var(--color-surface-soft);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 12px 16px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.recipient-name {
  font-weight: 600;
  color: var(--color-ink);
  font-size: 14px;
}

.recipient-email {
  color: var(--color-brand);
  font-size: 13px;
}

.form-group input,
.form-group textarea {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  transition: all 0.3s ease;
  font-family: inherit;
}

.form-group textarea {
  resize: vertical;
  min-height: 200px;
}

.form-group input:focus,
.form-group textarea:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.email-editor-modal-footer {
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
  .email-editor-modal-content {
    width: 95%;
    margin: 10px;
  }
}
</style>
