<template>
  <div class="email-settings-page">
    <!-- Page Header -->
    <div class="page-header">
      <div class="page-title">
        <h1>
          <i class="fas fa-envelope-open-text"></i>
          {{ t("page_emailSettings_title") }}
        </h1>
        <p>{{ t("page_emailSettings_sub") }}</p>
      </div>
      <div class="page-actions">
        <PageHeaderActions />
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-container">
      <i class="fas fa-spinner fa-spin"></i>
      <p>{{ t("emailSettings_loading") }}</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-container">
      <i class="fas fa-exclamation-circle"></i>
      <p>{{ error }}</p>
      <button class="btn btn-primary" @click="loadSettings">
        <i class="fas fa-redo"></i> {{ t("emailSettings_retry") }}
      </button>
    </div>

    <!-- Settings Content -->
    <div v-else class="settings-content">
      <div class="info-banner">
        <i class="fas fa-info-circle"></i>
        <div class="info-text">
          <strong>{{ t("emailSettings_infoTitle") }}</strong>
          {{ t("emailSettings_infoBody") }}
        </div>
      </div>

      <!-- Section Cards -->
      <div
        v-for="section in sections"
        :key="section.sectionKey"
        class="section-card"
      >
        <div class="section-header">
          <div class="section-title">
            <h2>
              <i class="fas fa-tag"></i>
              {{ displaySectionName(section) }}
            </h2>
            <p>{{ sectionIntro(section) }}</p>
          </div>
        </div>

        <div class="section-body">
          <div v-if="availableTemplates.length === 0" class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>
              {{ t("emailSettings_emptyPart1") }}
              <router-link to="/email-templates">{{
                t("menu_emailTemplates")
              }}</router-link
              >{{ t("emailSettings_emptyPart2") }}
            </p>
          </div>

          <div v-else class="templates-selection">
            <div class="selection-header">
              <label class="select-all-checkbox">
                <input
                  type="checkbox"
                  :checked="isAllSelected(section.sectionKey)"
                  :indeterminate="isIndeterminate(section.sectionKey)"
                  @change="
                    toggleSelectAll(section.sectionKey, $event.target.checked)
                  "
                  :disabled="!hasEditPermission"
                />
                <span>{{ t("emailSettings_selectAll") }}</span>
              </label>
              <span class="selected-count">
                {{ selectedCountText(section.sectionKey) }}
              </span>
            </div>

            <div class="templates-grid">
              <label
                v-for="template in availableTemplates"
                :key="template.id"
                class="template-checkbox-label"
              >
                <input
                  type="checkbox"
                  :value="template.id"
                  :checked="isTemplateSelected(section.sectionKey, template.id)"
                  @change="
                    toggleTemplate(
                      section.sectionKey,
                      template.id,
                      $event.target.checked,
                    )
                  "
                  :disabled="!hasEditPermission"
                />
                <span>{{ template.templateName }}</span>
              </label>
            </div>
          </div>
        </div>

        <div class="section-footer">
          <button
            v-if="hasEditPermission"
            class="btn btn-primary"
            :disabled="!hasChanges(section.sectionKey) || saving"
            @click="saveSectionSettings(section.sectionKey)"
          >
            <i :class="saving ? 'fas fa-spinner fa-spin' : 'fas fa-save'"></i>
            {{
              saving
                ? t("emailSettings_saving")
                : t("emailSettings_saveChanges")
            }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";

import { ref, reactive, onMounted, computed } from "vue";
import { useAuthStore } from "@/stores/auth";
import emailTemplateSectionSettingsApi from "@/services/emailTemplateSectionSettingsApi";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t, tParams } = useAdminI18n();

const authStore = useAuthStore();

// 权限检查
const hasReadonlyPermission = computed(() =>
  authStore.hasPermission("page_emailsettings_readonly"),
);
const hasEditPermission = computed(() =>
  authStore.hasPermission("page_emailsettings_edit"),
);

const loading = ref(false);
const error = ref(null);
const saving = ref(false);
const sections = ref([]);
const availableTemplates = ref([]);

// 存储每个板块选中的模板ID
const selectedTemplates = reactive({});
// 存储每个板块的原始选中状态（用于检测变化）
const originalSelectedTemplates = reactive({});

const loadSettings = async () => {
  loading.value = true;
  error.value = null;

  try {
    const response = await emailTemplateSectionSettingsApi.getSettings();
    if (response.success) {
      sections.value = response.data.sections || [];
      availableTemplates.value = response.data.availableTemplates || [];

      // 初始化选中状态
      sections.value.forEach((section) => {
        const templateIds = section.selectedTemplateIds || [];
        selectedTemplates[section.sectionKey] = [...templateIds];
        originalSelectedTemplates[section.sectionKey] = [...templateIds];
      });
    } else {
      error.value = response.message || t("emailSettings_loadFailed");
    }
  } catch (err) {
    error.value =
      err.response?.data?.message ||
      err.message ||
      t("emailSettings_loadFailed");
    console.error("Failed to load email settings:", err);
  } finally {
    loading.value = false;
  }
};

const isTemplateSelected = (sectionKey, templateId) => {
  const selected = selectedTemplates[sectionKey] || [];
  return selected.includes(templateId);
};

const toggleTemplate = (sectionKey, templateId, checked) => {
  if (!selectedTemplates[sectionKey]) {
    selectedTemplates[sectionKey] = [];
  }

  if (checked) {
    if (!selectedTemplates[sectionKey].includes(templateId)) {
      selectedTemplates[sectionKey].push(templateId);
    }
  } else {
    const index = selectedTemplates[sectionKey].indexOf(templateId);
    if (index > -1) {
      selectedTemplates[sectionKey].splice(index, 1);
    }
  }
};

const isAllSelected = (sectionKey) => {
  const selected = selectedTemplates[sectionKey] || [];
  return (
    availableTemplates.value.length > 0 &&
    selected.length === availableTemplates.value.length
  );
};

const isIndeterminate = (sectionKey) => {
  const selected = selectedTemplates[sectionKey] || [];
  return (
    selected.length > 0 && selected.length < availableTemplates.value.length
  );
};

const toggleSelectAll = (sectionKey, checked) => {
  if (checked) {
    selectedTemplates[sectionKey] = availableTemplates.value.map(
      (template) => template.id,
    );
  } else {
    selectedTemplates[sectionKey] = [];
  }
};

const getSelectedCount = (sectionKey) => {
  return (selectedTemplates[sectionKey] || []).length;
};

const displaySectionName = (section) => {
  const key = `emailSettings_section_${section.sectionKey}`;
  return t(key, section.sectionName);
};

const sectionIntro = (section) =>
  tParams(
    "emailSettings_configureSection",
    "Configure email templates for {section} page",
    {
      section: displaySectionName(section),
    },
  );

const selectedCountText = (sectionKey) =>
  tParams("emailSettings_selectedCount", "{selected} of {total} selected", {
    selected: getSelectedCount(sectionKey),
    total: availableTemplates.value.length,
  });

const hasChanges = (sectionKey) => {
  const current = selectedTemplates[sectionKey] || [];
  const original = originalSelectedTemplates[sectionKey] || [];

  if (current.length !== original.length) {
    return true;
  }

  const currentSorted = [...current].sort();
  const originalSorted = [...original].sort();

  return JSON.stringify(currentSorted) !== JSON.stringify(originalSorted);
};

const saveSectionSettings = async (sectionKey) => {
  saving.value = true;

  try {
    const templateIds = selectedTemplates[sectionKey] || [];
    const response =
      await emailTemplateSectionSettingsApi.updateSectionSettings(
        sectionKey,
        templateIds,
      );

    if (response.success) {
      // 更新原始状态
      originalSelectedTemplates[sectionKey] = [...templateIds];
      alert(t("emailSettings_savedOk"));
    } else {
      alert(
        tParams(
          "emailSettings_saveFailedDetail",
          "Failed to save settings: {msg}",
          {
            msg: response.message || t("common_errorUnknown"),
          },
        ),
      );
    }
  } catch (err) {
    console.error("Failed to save settings:", err);
    alert(
      tParams(
        "emailSettings_saveFailedDetail",
        "Failed to save settings: {msg}",
        {
          msg:
            err.response?.data?.message ||
            err.message ||
            t("common_errorUnknown"),
        },
      ),
    );
  } finally {
    saving.value = false;
  }
};

onMounted(() => {
  loadSettings();
});
</script>

<style scoped>
.email-settings-page {
  max-width: 1400px;
  margin: 0 auto;
  padding: 30px 20px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 2px solid var(--color-border);
}

.page-title h1 {
  font-size: 28px;
  color: var(--color-ink);
  margin-bottom: 5px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.page-title h1 i {
  color: var(--color-brand);
}

.page-title p {
  font-size: 14px;
  color: var(--color-muted);
  margin: 0;
}

.page-actions {
  display: flex;
  gap: 15px;
  align-items: center;
}

.loading-container,
.error-container {
  text-align: center;
  padding: 60px 20px;
  color: var(--color-muted);
}

.loading-container i {
  font-size: 48px;
  color: var(--color-brand);
  margin-bottom: 15px;
  display: block;
}

.error-container i {
  font-size: 48px;
  color: var(--color-danger);
  margin-bottom: 15px;
  display: block;
}

.info-banner {
  background: linear-gradient(
    135deg,
    var(--color-brand-soft) 0%,
    var(--color-brand-soft) 100%
  );
  border: 2px solid var(--color-brand-soft);
  border-radius: var(--radius-lg);
  padding: 20px;
  margin-bottom: 30px;
  display: flex;
  align-items: flex-start;
  gap: 15px;
}

.info-banner i {
  font-size: 24px;
  color: var(--color-brand);
  flex-shrink: 0;
  margin-top: 2px;
}

.info-text {
  flex: 1;
  color: var(--color-text);
  line-height: 1.6;
}

.info-text strong {
  color: var(--color-ink);
}

.section-card {
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-lg);
  margin-bottom: 30px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.section-header {
  background: var(--color-brand-solid);
  color: white;
  padding: 25px 30px;
}

.section-title h2 {
  font-size: 20px;
  margin: 0 0 8px 0;
  display: flex;
  align-items: center;
  gap: 12px;
}

.section-title p {
  margin: 0;
  font-size: 14px;
  opacity: 0.9;
}

.section-body {
  padding: 30px;
}

.empty-state {
  text-align: center;
  padding: 40px 20px;
  color: var(--color-muted);
}

.empty-state i {
  font-size: 48px;
  margin-bottom: 15px;
  display: block;
  color: var(--color-border-strong);
}

.empty-state a {
  color: var(--color-brand);
  text-decoration: none;
}

.empty-state a:hover {
  text-decoration: underline;
}

.templates-selection {
  margin-top: 20px;
}

.selection-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 2px solid var(--color-border);
}

.select-all-checkbox {
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  font-weight: 600;
  color: var(--color-ink);
}

.select-all-checkbox input[type="checkbox"] {
  width: 18px;
  height: 18px;
  cursor: pointer;
}

.selected-count {
  font-size: 14px;
  color: var(--color-muted);
}

.templates-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 12px;
}

.template-checkbox-label {
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  padding: 12px 16px;
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  transition: all 0.3s ease;
  font-size: 14px;
  color: var(--color-ink);
}

.template-checkbox-label:hover {
  border-color: var(--color-brand);
  background: var(--color-surface-soft);
}

.template-checkbox-label input[type="checkbox"] {
  width: 18px;
  height: 18px;
  cursor: pointer;
  accent-color: var(--color-brand);
  flex-shrink: 0;
}

.template-checkbox-label input[type="checkbox"]:checked + span {
  color: var(--color-brand);
  font-weight: 600;
}

.template-checkbox-label:has(input[type="checkbox"]:checked) {
  border-color: var(--color-brand);
  background: var(--color-brand-soft);
}

.template-checkbox-label span {
  flex: 1;
  user-select: none;
}

.section-footer {
  padding: 20px 30px;
  background: var(--color-surface-soft);
  border-top: 2px solid var(--color-border);
  display: flex;
  justify-content: flex-end;
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

.btn-primary {
  background: var(--color-brand-solid);
  color: white;
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.3);
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(var(--color-brand-rgb), 0.4);
}
</style>
