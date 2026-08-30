<template>
  <div v-if="modelValue" class="modal-overlay" @click.self="closeModal">
    <div class="modal-container">
      <!-- Modal Header -->
      <div class="modal-header">
        <div class="modal-title">
          <i class="fas fa-paper-plane"></i>
          <h2>{{ t("sendNotif_title", "Send Notification") }}</h2>
        </div>
        <button class="btn-close" @click="closeModal">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <!-- Modal Body -->
      <div class="modal-body">
        <!-- Recipient Info -->
        <div class="recipient-section">
          <div class="section-title">
            <i class="fas fa-user"></i>
            {{
              isBulk
                ? t("sendNotif_recipients", "Recipients")
                : t("sendNotif_recipient", "Recipient")
            }}
          </div>
          <div v-if="isBulk" class="recipient-bulk-wrap">
            <div class="recipient-bulk-header">
              <span class="recipient-bulk-icon"
                ><i class="fas fa-users"></i
              ></span>
              <span class="recipient-bulk-title">{{
                tParams(
                  "sendNotif_bulkSendTo",
                  "Send to {n} selected lead(s)",
                  { n: String(recipients.length) },
                )
              }}</span>
              <span class="recipient-bulk-hint">{{
                t(
                  "sendNotif_bulkHint",
                  "Same notification content will be sent to all",
                )
              }}</span>
            </div>
            <div class="recipient-bulk-list">
              <div
                v-for="(r, index) in recipients"
                :key="(r.id ?? r.leadId) + '-' + index"
                class="recipient-bulk-item"
              >
                <span class="recipient-bulk-item-index">{{ index + 1 }}</span>
                <span class="recipient-bulk-item-email">{{
                  r.email || "—"
                }}</span>
                <span
                  v-if="r.firstName || r.lastName"
                  class="recipient-bulk-item-name"
                  >{{
                    [r.firstName, r.lastName].filter(Boolean).join(" ")
                  }}</span
                >
              </div>
            </div>
          </div>
          <div v-else class="recipient-card">
            <div class="recipient-avatar">
              <i class="fas fa-user-circle"></i>
            </div>
            <div class="recipient-info">
              <div class="recipient-name">{{ recipientName }}</div>
              <div class="recipient-email">{{ recipient.email }}</div>
              <div class="recipient-id">
                {{
                  tParams("sendNotif_clientIdLabel", "Client ID: #{id}", {
                    id: String(recipient.id),
                  })
                }}
              </div>
            </div>
          </div>
        </div>

        <!-- Notification Type -->
        <div class="form-section">
          <div class="section-title">
            <i class="fas fa-bell"></i>
            {{ t("sendNotif_sectionType", "Notification Type") }}
          </div>
          <div class="notification-types">
            <label class="type-checkbox">
              <input
                type="checkbox"
                v-model="formData.sendSystemNotification"
              />
              <div class="type-card">
                <div class="type-icon system">
                  <i class="fas fa-desktop"></i>
                </div>
                <div class="type-content">
                  <div class="type-name">
                    {{ t("sendNotif_sysName", "System Notification") }}
                  </div>
                  <div class="type-desc">
                    {{
                      t(
                        "sendNotif_sysDesc",
                        "Send in-app notification to client",
                      )
                    }}
                  </div>
                </div>
                <div class="type-check">
                  <i class="fas fa-check-circle"></i>
                </div>
              </div>
            </label>

            <label class="type-checkbox">
              <input type="checkbox" v-model="formData.sendEmail" />
              <div class="type-card">
                <div class="type-icon email">
                  <i class="fas fa-envelope"></i>
                </div>
                <div class="type-content">
                  <div class="type-name">
                    {{ t("sendNotif_emailName", "Email Notification") }}
                  </div>
                  <div class="type-desc">
                    {{
                      tParams("sendNotif_emailDesc", "Send email to {email}", {
                        email: recipient.email || "—",
                      })
                    }}
                  </div>
                </div>
                <div class="type-check">
                  <i class="fas fa-check-circle"></i>
                </div>
              </div>
            </label>
          </div>
          <div
            v-if="!formData.sendSystemNotification && !formData.sendEmail"
            class="error-message"
          >
            <i class="fas fa-exclamation-triangle"></i>
            {{
              t(
                "sendNotif_errSelectType",
                "Please select at least one notification type",
              )
            }}
          </div>
        </div>

        <!-- Notification Content -->
        <div class="form-section">
          <div class="section-title">
            <i class="fas fa-edit"></i>
            {{ t("sendNotif_sectionContent", "Notification Content") }}
          </div>

          <div class="form-group">
            <label for="subject"
              >{{ t("sendNotif_subjectLabel", "Subject / Title") }}
              <span class="required">*</span></label
            >
            <input
              type="text"
              id="subject"
              v-model="formData.subject"
              :placeholder="
                t('sendNotif_subjectPh', 'Enter notification subject or title')
              "
              class="form-input"
              :class="{ error: errors.subject }"
            />
            <span v-if="errors.subject" class="error-text">{{
              errors.subject
            }}</span>
          </div>

          <div class="form-group">
            <label for="message"
              >{{ t("sendNotif_messageLabel", "Message") }}
              <span class="required">*</span></label
            >
            <textarea
              id="message"
              v-model="formData.message"
              :placeholder="
                t(
                  'sendNotif_messagePh',
                  'Enter your notification message here...',
                )
              "
              rows="6"
              class="form-textarea"
              :class="{ error: errors.message }"
            ></textarea>
            <div class="textarea-footer">
              <span v-if="errors.message" class="error-text">{{
                errors.message
              }}</span>
              <span class="character-count">{{
                tParams("sendNotif_charCount", "{n} / 1000", {
                  n: String(formData.message.length),
                })
              }}</span>
            </div>
          </div>

          <!-- Email-specific fields -->
          <div v-if="formData.sendEmail" class="email-additional">
            <div class="form-group">
              <label for="emailTemplate">{{
                t("sendNotif_emailTemplateLabel", "Email Template (Optional)")
              }}</label>
              <select
                id="emailTemplate"
                v-model="formData.emailTemplate"
                class="form-select"
                :disabled="loadingTemplates"
              >
                <option value="">
                  {{ t("sendNotif_customMessage", "Custom Message") }}
                </option>
                <option
                  v-for="template in emailTemplates"
                  :key="template.templateKey"
                  :value="template.templateKey"
                >
                  {{ template.name }}
                </option>
              </select>
              <div v-if="loadingTemplates" class="template-helper">
                <small>{{
                  t("sendNotif_loadingTemplates", "Loading templates...")
                }}</small>
              </div>
            </div>
          </div>
        </div>

        <!-- Delivery Schedule -->
        <div class="form-section">
          <div class="section-title">
            <i class="fas fa-clock"></i>
            {{ t("sendNotif_sectionSchedule", "Delivery Schedule") }}
          </div>

          <div class="schedule-options">
            <label class="schedule-radio">
              <input
                type="radio"
                name="schedule"
                value="immediate"
                v-model="formData.scheduleType"
              />
              <div class="schedule-card">
                <div class="schedule-icon immediate">
                  <i class="fas fa-bolt"></i>
                </div>
                <div class="schedule-content">
                  <div class="schedule-name">
                    {{ t("sendNotif_immediateName", "Send Immediately") }}
                  </div>
                  <div class="schedule-desc">
                    {{
                      t(
                        "sendNotif_immediateDesc",
                        "Deliver notification right now",
                      )
                    }}
                  </div>
                </div>
              </div>
            </label>

            <label class="schedule-radio">
              <input
                type="radio"
                name="schedule"
                value="scheduled"
                v-model="formData.scheduleType"
              />
              <div class="schedule-card">
                <div class="schedule-icon scheduled">
                  <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="schedule-content">
                  <div class="schedule-name">
                    {{ t("sendNotif_laterName", "Schedule for Later") }}
                  </div>
                  <div class="schedule-desc">
                    {{
                      t(
                        "sendNotif_laterDesc",
                        "Choose a specific date and time",
                      )
                    }}
                  </div>
                </div>
              </div>
            </label>
          </div>

          <!-- Scheduled Date/Time Picker -->
          <div
            v-if="formData.scheduleType === 'scheduled'"
            class="scheduled-datetime"
          >
            <div class="form-group">
              <label for="scheduleDate"
                >{{ t("sendNotif_scheduleDate", "Schedule Date") }}
                <span class="required">*</span></label
              >
              <input
                type="date"
                id="scheduleDate"
                v-model="formData.scheduleDate"
                :min="minDate"
                class="form-input"
                :class="{ error: errors.scheduleDate }"
              />
              <span v-if="errors.scheduleDate" class="error-text">{{
                errors.scheduleDate
              }}</span>
            </div>

            <div class="form-group">
              <label for="scheduleTime"
                >{{ t("sendNotif_scheduleTime", "Schedule Time") }}
                <span class="required">*</span></label
              >
              <input
                type="time"
                id="scheduleTime"
                v-model="formData.scheduleTime"
                class="form-input"
                :class="{ error: errors.scheduleTime }"
              />
              <span v-if="errors.scheduleTime" class="error-text">{{
                errors.scheduleTime
              }}</span>
            </div>

            <div
              v-if="formData.scheduleDate && formData.scheduleTime"
              class="schedule-preview"
            >
              <i class="fas fa-info-circle"></i>
              {{
                t(
                  "sendNotif_schedulePreviewLead",
                  "Notification will be sent on",
                )
              }}
              <strong>{{ formatScheduledDateTime }}</strong>
            </div>
          </div>
        </div>

        <!-- Priority (Optional) -->
        <div class="form-section">
          <div class="section-title">
            <i class="fas fa-flag"></i>
            {{ t("sendNotif_sectionPriority", "Priority Level") }}
          </div>
          <div class="priority-options">
            <label
              v-for="priority in priorities"
              :key="priority.value"
              class="priority-option"
            >
              <input
                type="radio"
                name="priority"
                :value="priority.value"
                v-model="formData.priority"
              />
              <div class="priority-badge" :class="priority.value">
                <i :class="priority.icon"></i>
                {{ priority.label }}
              </div>
            </label>
          </div>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="modal-footer">
        <span v-if="generalError" class="general-error-message">
          <i class="fas fa-exclamation-circle"></i>
          {{ generalError }}
        </span>
        <button class="btn btn-cancel" @click="closeModal" :disabled="sending">
          <i class="fas fa-times"></i>
          {{ t("common_cancel", "Cancel") }}
        </button>
        <button
          class="btn btn-preview"
          @click="previewNotification"
          :disabled="sending"
        >
          <i class="fas fa-eye"></i>
          {{ t("sendNotif_preview", "Preview") }}
        </button>
        <button
          class="btn btn-send"
          @click="sendNotification"
          :disabled="!isFormValid || sending"
        >
          <i
            :class="['fas', sending ? 'fa-spinner fa-spin' : 'fa-paper-plane']"
          ></i>
          <span>
            {{
              sending
                ? formData.scheduleType === "scheduled"
                  ? t("sendNotif_scheduling", "Scheduling...")
                  : t("sendNotif_sending", "Sending...")
                : formData.scheduleType === "scheduled"
                  ? t("sendNotif_btnSchedule", "Schedule Notification")
                  : t("sendNotif_btnSend", "Send Notification")
            }}
          </span>
        </button>
      </div>
    </div>

    <!-- Preview Modal -->
    <div
      v-if="showPreview"
      class="preview-overlay"
      @click.self="showPreview = false"
    >
      <div class="preview-container">
        <div class="preview-header">
          <h3>
            {{ t("sendNotif_previewModalTitle", "Notification Preview") }}
          </h3>
          <button class="btn-close" @click="showPreview = false">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="preview-body">
          <div v-if="formData.sendSystemNotification" class="preview-section">
            <h4>
              <i class="fas fa-desktop"></i>
              {{
                t("sendNotif_previewSystemTitle", "System Notification Preview")
              }}
            </h4>
            <div class="system-notification-preview">
              <div class="notification-preview-icon">
                <i class="fas fa-bell"></i>
              </div>
              <div class="notification-preview-content">
                <div class="notification-preview-title">
                  {{
                    formData.subject || t("sendNotif_noSubject", "No Subject")
                  }}
                </div>
                <div class="notification-preview-message">
                  {{
                    previewSystemMessage ||
                    t("sendNotif_noMessage", "No Message")
                  }}
                </div>
                <div class="notification-preview-time">
                  {{ t("sendNotif_justNow", "Just now") }}
                </div>
              </div>
            </div>
          </div>

          <div v-if="formData.sendEmail" class="preview-section">
            <h4>
              <i class="fas fa-envelope"></i>
              {{ t("sendNotif_previewEmailTitle", "Email Preview") }}
            </h4>
            <div class="email-preview">
              <div class="email-preview-header">
                <div class="email-preview-row">
                  <strong>{{ t("sendNotif_emailTo", "To:") }}</strong>
                  {{ previewRecipient.email }}
                </div>
                <div class="email-preview-row">
                  <strong>{{
                    t("sendNotif_emailSubjectLine", "Subject:")
                  }}</strong>
                  {{
                    previewEmailSubject ||
                    formData.subject ||
                    t("sendNotif_noSubject", "No Subject")
                  }}
                </div>
              </div>
              <div class="email-preview-body">
                <iframe
                  ref="emailPreviewIframeRef"
                  class="email-preview-iframe"
                  title="Email preview"
                  sandbox="allow-same-origin"
                ></iframe>
              </div>
            </div>
          </div>
        </div>
        <div class="preview-footer">
          <button class="btn btn-cancel" @click="showPreview = false">
            {{ t("sendNotif_previewClose", "Close") }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from "vue";
import { notificationService } from "@/services/notificationService";
import brandingApi from "@/services/brandingApi";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { translateApiErrorMessage } from "@/i18n/adminI18nBridge";
import { getSubModuleKey } from "@/config/operationLogPages";
import {
  sanitizeSystemMessage,
  buildEmailPreviewPayload,
  wrapEmailForIframe,
} from "@/utils/notificationPreview";

const { t, tParams, languageStore } = useAdminI18n();

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
  recipient: {
    type: Object,
    default: null,
  },
  recipients: {
    type: Array,
    default: null,
  },
  sectionKey: {
    type: String,
    default: null, // 'leads' | 'client_list' | 'ib_list', null means use default (all templates)
  },
  /** 操作日志子模块：leads | clients_list | ib_list */
  logSubModuleKey: {
    type: String,
    default: "",
  },
});

const emit = defineEmits(["update:modelValue", "send"]);

const showPreview = ref(false);
const emailPreviewIframeRef = ref(null);
const errors = ref({});
const generalError = ref("");
const sending = ref(false);
const loadingTemplates = ref(false);
const emailTemplates = ref([]);
const templatesLoaded = ref(false);

// Branding configuration
const branding = ref({
  logoText: "CRM",
});

const formData = ref({
  sendSystemNotification: true,
  sendEmail: true,
  subject: "",
  message: "",
  emailTemplate: "",
  scheduleType: "immediate",
  scheduleDate: "",
  scheduleTime: "",
  priority: "normal",
});

const priorities = computed(() => [
  {
    value: "low",
    label: t("sendNotif_priorityLow", "Low"),
    icon: "fas fa-flag",
  },
  {
    value: "normal",
    label: t("sendNotif_priorityNormal", "Normal"),
    icon: "fas fa-flag",
  },
  {
    value: "high",
    label: t("sendNotif_priorityHigh", "High"),
    icon: "fas fa-flag",
  },
  {
    value: "urgent",
    label: t("sendNotif_priorityUrgent", "Urgent"),
    icon: "fas fa-exclamation-triangle",
  },
]);

const htmlToPlain = (html) => {
  if (!html) return "";

  if (typeof window === "undefined") {
    return html
      .replace(/<[^>]+>/g, " ")
      .replace(/\s+/g, " ")
      .trim();
  }

  const container = document.createElement("div");
  container.innerHTML = html;
  const text = container.textContent || container.innerText || "";
  return text.replace(/\r\n|\r|\n/g, "\n").trim();
};

const applyTemplateToForm = (templateKey) => {
  if (!templateKey) {
    return;
  }

  const template = emailTemplates.value.find(
    (item) => item.templateKey === templateKey,
  );
  if (!template) {
    return;
  }

  if (template.subject) {
    formData.value.subject = template.subject;
  }

  if (template.body) {
    formData.value.message = htmlToPlain(template.body).slice(0, 1000);
  }
};

const loadEmailTemplates = async () => {
  if (templatesLoaded.value || loadingTemplates.value) {
    return;
  }

  loadingTemplates.value = true;
  try {
    let templates = [];

    // 如果指定了 sectionKey，使用板块特定的模板列表
    if (props.sectionKey) {
      const emailTemplateSectionSettingsApi = (
        await import("@/services/emailTemplateSectionSettingsApi")
      ).default;
      const response =
        await emailTemplateSectionSettingsApi.getSectionTemplates(
          props.sectionKey,
        );
      if (response.success && Array.isArray(response.data)) {
        templates = response.data;
      }
    } else {
      // 否则使用默认的模板列表（所有激活的模板）
      const response = await notificationService.getEmailTemplates();
      const payload = response?.data ?? response ?? {};
      templates = Array.isArray(payload.data)
        ? payload.data
        : Array.isArray(payload)
          ? payload
          : [];
    }

    if (Array.isArray(templates)) {
      emailTemplates.value = templates.map((item) => ({
        id: item.id ?? item.templateKey,
        templateKey: item.templateKey,
        name: item.name || item.templateName || item.templateKey,
        subject: item.subject || item.emailSubject || "",
        body: item.body || item.emailBody || "",
      }));
      templatesLoaded.value = true;
    }
  } catch (error) {
    console.error("Failed to load email templates:", error);
  } finally {
    loadingTemplates.value = false;
  }
};

const isBulk = computed(() => props.recipients && props.recipients.length > 1);

const recipientName = computed(() => {
  const fallback = t("sendNotif_fallbackName", "Client");
  const r = previewRecipient.value;
  const firstName = r.firstName || "";
  const lastName = r.lastName || "";
  return `${firstName} ${lastName}`.trim() || fallback;
});

const previewRecipient = computed(() => {
  if (props.recipient) return props.recipient;
  if (props.recipients?.length) return props.recipients[0];
  return { email: "", firstName: "", lastName: "", id: "" };
});

const selectedEmailTemplate = computed(() => {
  const key = formData.value.emailTemplate;
  if (!key) return null;
  return emailTemplates.value.find((item) => item.templateKey === key) ?? null;
});

const previewSystemMessage = computed(() => {
  return sanitizeSystemMessage(formData.value.message);
});

const previewEmailPayload = computed(() => {
  return buildEmailPreviewPayload(
    previewRecipient.value,
    formData.value.subject,
    formData.value.message,
    selectedEmailTemplate.value,
  );
});

const previewEmailSubject = computed(() => previewEmailPayload.value.subject);

const previewEmailIframeSrcdoc = computed(() => {
  return wrapEmailForIframe(previewEmailPayload.value.body);
});

const renderEmailPreviewIframe = () => {
  nextTick(() => {
    const iframe = emailPreviewIframeRef.value;
    if (!iframe) return;

    const doc = iframe.contentDocument || iframe.contentWindow?.document;
    if (!doc) return;

    const html = previewEmailIframeSrcdoc.value;
    doc.open();
    doc.write(html);
    doc.close();

    nextTick(() => {
      const height = Math.max(
        doc.documentElement?.scrollHeight || 0,
        doc.body?.scrollHeight || 0,
        280,
      );
      iframe.style.height = `${height}px`;
    });
  });
};

watch([showPreview, previewEmailIframeSrcdoc], ([visible]) => {
  if (visible && formData.value.sendEmail) {
    renderEmailPreviewIframe();
  }
});

const minDate = computed(() => {
  const today = new Date();
  return today.toISOString().split("T")[0];
});

const formatScheduledDateTime = computed(() => {
  if (!formData.value.scheduleDate || !formData.value.scheduleTime) return "";

  const date = new Date(
    `${formData.value.scheduleDate}T${formData.value.scheduleTime}`,
  );
  const locale = languageStore.currentLanguage === "zh" ? "zh-CN" : "en-US";
  return date.toLocaleString(locale, {
    weekday: "long",
    year: "numeric",
    month: "long",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
});

const isFormValid = computed(() => {
  // At least one notification type must be selected
  if (!formData.value.sendSystemNotification && !formData.value.sendEmail) {
    return false;
  }

  // Subject and message are required
  if (!formData.value.subject.trim() || !formData.value.message.trim()) {
    return false;
  }

  // If scheduled, date and time are required
  if (formData.value.scheduleType === "scheduled") {
    if (!formData.value.scheduleDate || !formData.value.scheduleTime) {
      return false;
    }
  }

  return true;
});

watch(
  () => props.modelValue,
  (visible) => {
    if (visible) {
      generalError.value = "";
      templatesLoaded.value = false; // 重置加载状态，以便重新加载
      loadEmailTemplates();
    }
  },
  { immediate: true },
);

// 当 sectionKey 改变时，重新加载模板
watch(
  () => props.sectionKey,
  () => {
    if (props.modelValue) {
      templatesLoaded.value = false;
      loadEmailTemplates();
    }
  },
);

watch(
  () => formData.value.emailTemplate,
  (newValue, oldValue) => {
    if (newValue && newValue !== oldValue) {
      applyTemplateToForm(newValue);
    }
  },
);

const validateForm = () => {
  errors.value = {};

  if (!formData.value.subject.trim()) {
    errors.value.subject = t(
      "sendNotif_val_subjectRequired",
      "Subject is required",
    );
  }

  if (!formData.value.message.trim()) {
    errors.value.message = t(
      "sendNotif_val_messageRequired",
      "Message is required",
    );
  }

  if (formData.value.message.length > 1000) {
    errors.value.message = t(
      "sendNotif_val_messageMax",
      "Message must not exceed 1000 characters",
    );
  }

  if (formData.value.scheduleType === "scheduled") {
    if (!formData.value.scheduleDate) {
      errors.value.scheduleDate = t(
        "sendNotif_val_dateRequired",
        "Schedule date is required",
      );
    }

    if (!formData.value.scheduleTime) {
      errors.value.scheduleTime = t(
        "sendNotif_val_timeRequired",
        "Schedule time is required",
      );
    }

    // Check if scheduled time is in the future
    if (formData.value.scheduleDate && formData.value.scheduleTime) {
      const scheduledDateTime = new Date(
        `${formData.value.scheduleDate}T${formData.value.scheduleTime}`,
      );
      const now = new Date();

      if (scheduledDateTime <= now) {
        errors.value.scheduleDate = t(
          "sendNotif_val_future",
          "Scheduled time must be in the future",
        );
      }
    }
  }

  return Object.keys(errors.value).length === 0;
};

const previewNotification = () => {
  if (!validateForm()) {
    return;
  }
  showPreview.value = true;
  if (formData.value.sendEmail) {
    renderEmailPreviewIframe();
  }
};

const sendNotification = async () => {
  generalError.value = "";

  if (!validateForm()) {
    return;
  }

  const list =
    props.recipients && props.recipients.length > 0
      ? props.recipients
      : props.recipient
        ? [props.recipient]
        : null;
  if (!list || list.length === 0) {
    const msg = t(
      "sendNotif_errRecipientMissing",
      "Recipient information is missing.",
    );
    generalError.value = msg;
    alert(msg);
    return;
  }

  // 将用户选择的本地时间转换为 ISO 8601 格式（包含时区信息）
  const scheduledAt =
    formData.value.scheduleType === "scheduled"
      ? (() => {
          // 创建本地时间的 Date 对象（浏览器会自动使用用户本地时区）
          const localDate = new Date(
            `${formData.value.scheduleDate}T${formData.value.scheduleTime}:00`,
          );
          // 转换为 ISO 8601 格式（包含时区信息，如：2025-12-04T17:21:00+08:00）
          return localDate.toISOString();
        })()
      : null;

  const logSub =
    props.logSubModuleKey ||
    (props.sectionKey === "ib_list"
      ? getSubModuleKey("page_ib_list")
      : props.sectionKey === "client_list"
        ? getSubModuleKey("page_clients_list")
        : getSubModuleKey("page_leads"));

  const basePayload = {
    subject: formData.value.subject.trim(),
    message: formData.value.message.trim(),
    priority: formData.value.priority,
    scheduleType: formData.value.scheduleType,
    scheduledAt,
    sendSystemNotification: formData.value.sendSystemNotification,
    sendEmail: formData.value.sendEmail,
    emailTemplate: formData.value.emailTemplate || null,
    logSubModuleKey: logSub,
  };

  sending.value = true;
  try {
    if (list.length > 1) {
      const clientIds = list
        .map((r) => r.id ?? r.leadId)
        .filter((id) => id != null)
        .map(Number);
      if (clientIds.length === 0) {
        generalError.value = t(
          "sendNotif_errNoValidRecipients",
          "No valid recipients.",
        );
        return;
      }
      const response = await notificationService.sendBulkToClients({
        ...basePayload,
        clientIds,
      });
      const data = response?.data ?? response ?? {};
      const successCount = data.successCount ?? 0;
      const failCount = data.failCount ?? 0;
      emit("send", {
        bulk: true,
        successCount,
        failCount,
        payload: basePayload,
      });
      closeModal();
    } else {
      const single = list[0];
      if (!single || !single.id) {
        const msg = t(
          "sendNotif_errRecipientMissing",
          "Recipient information is missing.",
        );
        generalError.value = msg;
        alert(msg);
        return;
      }
      const payload = { ...basePayload, clientId: Number(single.id) };
      const response = await notificationService.sendToClient(payload);
      const responseData = response?.data ?? response ?? {};

      emit("send", {
        payload,
        recipient: {
          id: single.id,
          email: single.email,
          name: recipientName.value,
        },
        response: responseData,
      });

      closeModal();
    }
  } catch (error) {
    const data = error?.response?.data ?? error;
    const rawMsg =
      data?.message ||
      error?.message ||
      t("common_unknownError", "Unknown error");
    const message = translateApiErrorMessage(data?.errorCode, rawMsg);
    generalError.value = message;
    console.error("Failed to send notification:", error);
    alert(
      tParams("sendNotif_errSendFailed", "Failed to send notification: {msg}", {
        msg: message,
      }),
    );
  } finally {
    sending.value = false;
  }
};

const closeModal = (shouldReset = true) => {
  // Reset form
  if (shouldReset) {
    formData.value = {
      sendSystemNotification: true,
      sendEmail: true,
      subject: "",
      message: "",
      emailTemplate: "",
      scheduleType: "immediate",
      scheduleDate: "",
      scheduleTime: "",
      priority: "normal",
    };
    errors.value = {};
    showPreview.value = false;
    generalError.value = "";
  }

  emit("update:modelValue", false);
};

// Watch for character limit
watch(
  () => formData.value.message,
  (newValue) => {
    if (newValue.length > 1000) {
      formData.value.message = newValue.substring(0, 1000);
    }
  },
);

// Load branding configuration
onMounted(async () => {
  try {
    const config = await brandingApi.getBranding();
    branding.value = {
      logoText: config.logoText || "CRM",
    };
  } catch (error) {
    console.error("Failed to load branding:", error);
  }
});
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 10000;
  padding: 20px;
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.modal-container {
  background: var(--color-surface);
  border-radius: var(--radius-xl);
  width: 100%;
  max-width: 800px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  animation: slideUp 0.3s ease;
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.modal-header {
  padding: 25px 30px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--color-brand-solid);
  color: white;
  border-radius: 16px 16px 0 0;
}

.modal-title {
  display: flex;
  align-items: center;
  gap: 12px;
}

.modal-title i {
  font-size: 24px;
}

.modal-title h2 {
  font-size: 22px;
  margin: 0;
  font-weight: 600;
}

.btn-close {
  background: rgba(255, 255, 255, 0.2);
  border: none;
  width: 36px;
  height: 36px;
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.3s ease;
  color: white;
  font-size: 18px;
}

.btn-close:hover {
  background: rgba(255, 255, 255, 0.3);
  transform: rotate(90deg);
}

.modal-body {
  flex: 1;
  overflow-y: auto;
  padding: 30px;
}

.modal-body::-webkit-scrollbar {
  width: 8px;
}

.modal-body::-webkit-scrollbar-track {
  background: var(--color-surface-soft);
}

.modal-body::-webkit-scrollbar-thumb {
  background: var(--color-border-strong);
  border-radius: 4px;
}

.modal-body::-webkit-scrollbar-thumb:hover {
  background: var(--color-faint);
}

.form-section {
  margin-bottom: 30px;
}

.section-title {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 16px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 15px;
  padding-bottom: 10px;
  border-bottom: 2px solid var(--color-border);
}

.section-title i {
  color: var(--color-brand);
  font-size: 18px;
}

/* Recipient Section */
.recipient-section {
  margin-bottom: 30px;
}

.recipient-card {
  display: flex;
  align-items: center;
  gap: 15px;
  padding: 20px;
  background: linear-gradient(
    135deg,
    var(--color-brand-soft) 0%,
    var(--color-brand-soft) 100%
  );
  border-radius: var(--radius-lg);
  border: 2px solid var(--color-brand-soft);
}

.recipient-avatar {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  background: var(--color-brand-solid);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 30px;
  flex-shrink: 0;
}

.recipient-info {
  flex: 1;
}

.recipient-name {
  font-size: 18px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 4px;
}

.recipient-email {
  font-size: 14px;
  color: var(--color-text);
  margin-bottom: 4px;
}

.recipient-id {
  font-size: 12px;
  color: var(--color-brand);
  font-weight: 600;
}

/* Bulk Recipients */
.recipient-bulk-wrap {
  background: var(--color-success-soft);
  border: 2px solid var(--color-success-border);
  border-radius: var(--radius-lg);
  overflow: hidden;
}

.recipient-bulk-header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px 16px;
  padding: 14px 18px;
  background: rgba(255, 255, 255, 0.6);
  border-bottom: 1px solid #99f6e4;
}

.recipient-bulk-icon {
  width: 40px;
  height: 40px;
  border-radius: var(--radius-md);
  background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
}

.recipient-bulk-title {
  font-size: 16px;
  font-weight: 600;
  color: var(--color-success);
}

.recipient-bulk-hint {
  font-size: 13px;
  color: var(--color-success);
  margin-left: auto;
}

.recipient-bulk-list {
  max-height: 200px;
  overflow-y: auto;
  padding: 10px 12px;
}

.recipient-bulk-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  background: var(--color-surface);
  border-radius: var(--radius-md);
  margin-bottom: 6px;
  border: 1px solid var(--color-border);
  font-size: 14px;
}

.recipient-bulk-item:last-child {
  margin-bottom: 0;
}

.recipient-bulk-item-index {
  flex-shrink: 0;
  width: 24px;
  height: 24px;
  border-radius: var(--radius-sm);
  background: var(--color-info-soft);
  color: var(--color-info);
  font-weight: 600;
  font-size: 12px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.recipient-bulk-item-email {
  flex: 1;
  color: var(--color-ink);
  font-weight: 500;
  word-break: break-all;
}

.recipient-bulk-item-name {
  flex-shrink: 0;
  font-size: 12px;
  color: var(--color-muted);
}

/* Notification Types */
.notification-types {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 15px;
}

.type-checkbox {
  cursor: pointer;
}

.type-checkbox input[type="checkbox"] {
  display: none;
}

.type-card {
  display: flex;
  align-items: center;
  gap: 15px;
  padding: 20px;
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-lg);
  transition: all 0.3s ease;
  position: relative;
}

.type-checkbox input[type="checkbox"]:checked + .type-card {
  border-color: var(--color-brand);
  background: var(--color-brand-soft);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.2);
}

.type-card:hover {
  border-color: var(--color-brand);
  transform: translateY(-2px);
}

.type-icon {
  width: 50px;
  height: 50px;
  border-radius: var(--radius-lg);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  flex-shrink: 0;
}

.type-icon.system {
  background: var(--color-brand-solid);
  color: white;
}

.type-icon.email {
  background: linear-gradient(
    135deg,
    var(--color-warning) 0%,
    var(--color-warning) 100%
  );
  color: white;
}

.type-content {
  flex: 1;
}

.type-name {
  font-size: 15px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 4px;
}

.type-desc {
  font-size: 13px;
  color: var(--color-muted);
}

.type-check {
  font-size: 24px;
  color: var(--color-border-strong);
  transition: all 0.3s ease;
}

.type-checkbox input[type="checkbox"]:checked + .type-card .type-check {
  color: var(--color-success);
}

/* Form Groups */
.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 8px;
}

.required {
  color: var(--color-danger);
}

.form-input,
.form-select,
.form-textarea {
  width: 100%;
  padding: 12px 15px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  font-family: inherit;
  transition: all 0.3s ease;
  outline: none;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.form-input.error,
.form-textarea.error {
  border-color: var(--color-danger);
}

.form-textarea {
  resize: vertical;
  min-height: 120px;
}

.textarea-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 8px;
}

.character-count {
  font-size: 12px;
  color: var(--color-faint);
}

.error-message,
.error-text {
  color: var(--color-danger);
  font-size: 13px;
  margin-top: 8px;
  display: flex;
  align-items: center;
  gap: 6px;
}

.error-message i {
  font-size: 14px;
}

.email-additional {
  margin-top: 20px;
  padding-top: 20px;
  border-top: 1px dashed var(--color-border);
}

/* Schedule Options */
.schedule-options {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 15px;
  margin-bottom: 20px;
}

.schedule-radio {
  cursor: pointer;
}

.schedule-radio input[type="radio"] {
  display: none;
}

.schedule-card {
  display: flex;
  align-items: center;
  gap: 15px;
  padding: 20px;
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-lg);
  transition: all 0.3s ease;
}

.schedule-radio input[type="radio"]:checked + .schedule-card {
  border-color: var(--color-brand);
  background: var(--color-brand-soft);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.2);
}

.schedule-card:hover {
  border-color: var(--color-brand);
  transform: translateY(-2px);
}

.schedule-icon {
  width: 50px;
  height: 50px;
  border-radius: var(--radius-lg);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  flex-shrink: 0;
}

.schedule-icon.immediate {
  background: linear-gradient(
    135deg,
    var(--color-success) 0%,
    var(--color-success) 100%
  );
  color: white;
}

.schedule-icon.scheduled {
  background: var(--color-brand-solid);
  color: white;
}

.schedule-content {
  flex: 1;
}

.schedule-name {
  font-size: 15px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 4px;
}

.schedule-desc {
  font-size: 13px;
  color: var(--color-muted);
}

.scheduled-datetime {
  padding: 20px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-lg);
  border: 2px dashed var(--color-border-strong);
}

.schedule-preview {
  padding: 15px;
  background: var(--color-brand-soft);
  border-radius: var(--radius-md);
  border-left: 4px solid var(--color-brand);
  font-size: 14px;
  color: var(--color-text);
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 15px;
}

.schedule-preview i {
  color: var(--color-brand);
  font-size: 18px;
}

/* Priority Options */
.priority-options {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.priority-option {
  cursor: pointer;
}

.priority-option input[type="radio"] {
  display: none;
}

.priority-badge {
  padding: 10px 20px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 13px;
  font-weight: 600;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 8px;
}

.priority-option:hover .priority-badge {
  transform: translateY(-2px);
}

.priority-option input[type="radio"]:checked + .priority-badge {
  border-color: currentColor;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.priority-badge.low {
  background: var(--color-border);
  color: var(--color-text);
}

.priority-option input[type="radio"]:checked + .priority-badge.low {
  background: var(--color-border);
}

.priority-badge.normal {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.priority-option input[type="radio"]:checked + .priority-badge.normal {
  background: var(--color-info-soft);
}

.priority-badge.high {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.priority-option input[type="radio"]:checked + .priority-badge.high {
  background: var(--color-warning-soft);
}

.priority-badge.urgent {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.priority-option input[type="radio"]:checked + .priority-badge.urgent {
  background: var(--color-danger-soft);
}

/* Modal Footer */
.modal-footer {
  padding: 20px 30px;
  border-top: 2px solid var(--color-border);
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  background: var(--color-surface-soft);
  border-radius: 0 0 16px 16px;
}

.general-error-message {
  margin-right: auto;
  color: var(--color-danger);
  font-size: 13px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-weight: 600;
}

.template-helper {
  margin-top: 6px;
  color: var(--color-muted);
  font-size: 12px;
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

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-cancel {
  background: var(--color-surface);
  color: var(--color-text);
  border: 2px solid var(--color-border);
}

.btn-cancel:hover {
  background: var(--color-surface-soft);
  border-color: var(--color-border-strong);
}

.btn-preview {
  background: var(--color-surface);
  color: var(--color-brand);
  border: 2px solid var(--color-brand);
}

.btn-preview:hover {
  background: var(--color-brand-soft);
}

.btn-send {
  background: var(--color-brand-solid);
  color: white;
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.3);
}

.btn-send:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(var(--color-brand-rgb), 0.4);
}

/* Preview Modal */
.preview-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.8);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 10001;
  padding: 20px;
  animation: fadeIn 0.3s ease;
}

.preview-container {
  background: var(--color-surface);
  border-radius: var(--radius-xl);
  width: 100%;
  max-width: 700px;
  max-height: 80vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
  animation: slideUp 0.3s ease;
}

.preview-header {
  padding: 20px 25px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.preview-header h3 {
  font-size: 20px;
  color: var(--color-ink);
  margin: 0;
}

.preview-body {
  flex: 1;
  overflow-y: auto;
  padding: 25px;
}

.preview-section {
  margin-bottom: 30px;
}

.preview-section:last-child {
  margin-bottom: 0;
}

.preview-section h4 {
  font-size: 16px;
  color: var(--color-ink);
  margin-bottom: 15px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.preview-section h4 i {
  color: var(--color-brand);
}

.system-notification-preview {
  display: flex;
  align-items: flex-start;
  gap: 15px;
  padding: 20px;
  background: var(--color-brand-soft);
  border-radius: var(--radius-lg);
  border: 2px solid var(--color-brand-soft);
}

.notification-preview-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: var(--color-brand-solid);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 18px;
  flex-shrink: 0;
}

.notification-preview-content {
  flex: 1;
}

.notification-preview-title {
  font-size: 15px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 6px;
}

.notification-preview-message {
  font-size: 14px;
  color: var(--color-text);
  line-height: 1.5;
  margin-bottom: 8px;
}

.notification-preview-time {
  font-size: 12px;
  color: var(--color-faint);
}

.email-preview {
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-lg);
  overflow: hidden;
}

.email-preview-header {
  padding: 20px;
  background: var(--color-surface-soft);
  border-bottom: 2px solid var(--color-border);
}

.email-preview-row {
  font-size: 14px;
  color: var(--color-text);
  margin-bottom: 8px;
}

.email-preview-row:last-child {
  margin-bottom: 0;
}

.email-preview-row strong {
  color: var(--color-ink);
  display: inline-block;
  width: 80px;
}

.email-preview-body {
  padding: 0;
  background: var(--color-surface);
}

.email-preview-iframe {
  display: block;
  width: 100%;
  min-height: 280px;
  border: none;
  background: var(--color-surface);
}

.preview-footer {
  padding: 15px 25px;
  border-top: 2px solid var(--color-border);
  display: flex;
  justify-content: flex-end;
}

/* Responsive */
@media (max-width: 768px) {
  .modal-container {
    max-width: 100%;
    max-height: 95vh;
  }

  .modal-body {
    padding: 20px;
  }

  .notification-types,
  .schedule-options {
    grid-template-columns: 1fr;
  }

  .priority-options {
    flex-direction: column;
  }

  .priority-badge {
    width: 100%;
    justify-content: center;
  }

  .modal-footer {
    flex-wrap: wrap;
  }

  .btn {
    flex: 1;
    justify-content: center;
  }
}
</style>
