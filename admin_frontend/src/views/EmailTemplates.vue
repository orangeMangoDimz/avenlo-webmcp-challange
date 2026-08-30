<template>
  <div class="container">
    <!-- Page Header -->
    <div class="page-header">
      <div class="page-title">
        <h1>
          <i class="fas fa-envelope"></i> {{ t("page_emailTemplates_title") }}
        </h1>
        <p>{{ t("page_emailTemplates_sub") }}</p>
      </div>
      <div class="page-actions">
        <PageHeaderActions />
      </div>
    </div>

    <!-- Filters -->
    <div class="filters-section">
      <div class="filter-group">
        <label>{{ t("emailTpl_labelCategory") }}</label>
        <select v-model="filters.category" @change="loadTemplates">
          <option value="">{{ t("emailTpl_filterAllCategories") }}</option>
          <option v-for="cat in categories" :key="cat" :value="cat">
            {{ formatCategory(cat) }}
          </option>
        </select>
      </div>
      <div class="filter-group">
        <label>{{ t("emailTpl_labelRecipientType") }}</label>
        <select v-model="filters.recipientType" @change="loadTemplates">
          <option value="">{{ t("emailTpl_filterAllTypes") }}</option>
          <option value="client">{{ t("emailTpl_recipient_client") }}</option>
          <option value="admin">{{ t("emailTpl_recipient_admin") }}</option>
          <option value="both">{{ t("emailTpl_recipient_both") }}</option>
        </select>
      </div>
      <div class="filter-group">
        <label>{{ t("emailTpl_labelStatus") }}</label>
        <select v-model="filters.isActive" @change="loadTemplates">
          <option :value="null">{{ t("emailTpl_filterAll") }}</option>
          <option :value="1">{{ t("emailTpl_filterActive") }}</option>
          <option :value="0">{{ t("emailTpl_filterInactive") }}</option>
        </select>
      </div>
      <div class="filter-group">
        <input
          type="text"
          v-model="filters.search"
          :placeholder="t('emailTpl_searchPlaceholder')"
          @input="handleSearch"
        />
      </div>
      <button
        v-if="hasCreatePermission"
        class="btn btn-primary"
        @click="openCreateModal"
      >
        <i class="fas fa-plus"></i> {{ t("emailTpl_btnNewTemplate") }}
      </button>
    </div>

    <!-- Templates Table -->
    <div v-if="loading" class="loading-container">
      <i class="fas fa-spinner fa-spin"></i>
      <p>{{ t("emailTpl_loading") }}</p>
    </div>

    <div v-else-if="error" class="error-container">
      <i class="fas fa-exclamation-circle"></i>
      <p>{{ error }}</p>
      <button class="btn btn-primary" @click="loadTemplates">
        <i class="fas fa-redo"></i> {{ t("emailTpl_retry") }}
      </button>
    </div>

    <div v-else class="templates-table-container">
      <div v-if="templates.length === 0" class="empty-state">
        <i class="fas fa-inbox"></i>
        <p>{{ t("emailTpl_empty") }}</p>
      </div>

      <table v-else class="templates-table">
        <thead>
          <tr>
            <th>{{ t("emailTpl_th_templateName") }}</th>
            <th>{{ t("emailTpl_th_templateKey") }}</th>
            <th>{{ t("emailTpl_th_category") }}</th>
            <th>{{ t("emailTpl_th_recipientType") }}</th>
            <th>{{ t("emailTpl_th_status") }}</th>
            <th>{{ t("emailTpl_th_updatedAt") }}</th>
            <th>{{ t("emailTpl_th_actions") }}</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="template in templates" :key="template.id">
            <!-- Main Row -->
            <tr :class="{ expanded: expandedRows.includes(template.id) }">
              <td>
                <div class="template-name-cell">
                  <strong>{{ template.templateName }}</strong>
                </div>
              </td>
              <td>
                <span class="template-key-badge">{{
                  template.templateKey
                }}</span>
              </td>
              <td>
                <span class="badge badge-category">{{
                  formatCategory(template.category)
                }}</span>
              </td>
              <td>
                <span class="badge badge-recipient">{{
                  formatRecipientType(template.recipientType)
                }}</span>
              </td>
              <td>
                <span
                  class="badge badge-status"
                  :class="template.isActive ? 'active' : 'inactive'"
                >
                  {{
                    template.isActive
                      ? t("emailTpl_statusActive")
                      : t("emailTpl_statusInactive")
                  }}
                </span>
              </td>
              <td>{{ formatDateTime(template.updatedAt) }}</td>
              <td>
                <div class="action-buttons">
                  <button
                    class="btn-action btn-detail"
                    @click="toggleDetail(template.id)"
                    :title="
                      expandedRows.includes(template.id)
                        ? t('emailTpl_titleToggleDetailHide')
                        : t('emailTpl_titleToggleDetailShow')
                    "
                  >
                    <i
                      class="fas"
                      :class="
                        expandedRows.includes(template.id)
                          ? 'fa-chevron-up'
                          : 'fa-chevron-down'
                      "
                    ></i>
                    {{
                      expandedRows.includes(template.id)
                        ? t("emailTpl_btnHide")
                        : t("emailTpl_btnDetail")
                    }}
                  </button>
                  <button
                    v-if="hasEditPermission"
                    class="btn-action btn-edit"
                    @click="openEditModal(template)"
                    :title="t('emailTpl_titleEditTemplate')"
                  >
                    <i class="fas fa-edit"></i> {{ t("emailTpl_btnEdit") }}
                  </button>
                  <button
                    v-if="hasDisablePermission"
                    class="btn-action"
                    :class="template.isActive ? 'btn-warning' : 'btn-success'"
                    @click="toggleActive(template.id, template.isActive)"
                    :title="
                      template.isActive
                        ? t('emailTpl_titleDisableTemplate')
                        : t('emailTpl_titleEnableTemplate')
                    "
                  >
                    <i
                      class="fas"
                      :class="
                        template.isActive ? 'fa-toggle-on' : 'fa-toggle-off'
                      "
                    ></i>
                    {{
                      template.isActive
                        ? t("emailTpl_btnDisable")
                        : t("emailTpl_btnEnable")
                    }}
                  </button>
                  <button
                    v-if="hasDeletePermission"
                    class="btn-action btn-delete"
                    @click="handleDelete(template.id, template.templateName)"
                    :title="t('emailTpl_titleDeleteTemplate')"
                  >
                    <i class="fas fa-trash"></i> {{ t("emailTpl_btnDelete") }}
                  </button>
                </div>
              </td>
            </tr>

            <!-- Detail Row -->
            <tr
              class="detail-row"
              :class="{ show: expandedRows.includes(template.id) }"
            >
              <td colspan="7">
                <div
                  class="detail-content"
                  v-if="expandedRows.includes(template.id)"
                >
                  <div class="detail-section">
                    <h3>
                      <i class="fas fa-info-circle"></i>
                      {{ t("emailTpl_sectionTemplateInfo") }}
                    </h3>
                    <div class="detail-grid">
                      <div class="detail-item">
                        <label>{{
                          t("emailTpl_detailLabelTemplateName")
                        }}</label>
                        <span>{{ template.templateName }}</span>
                      </div>
                      <div class="detail-item">
                        <label>{{
                          t("emailTpl_detailLabelTemplateKey")
                        }}</label>
                        <span class="template-key-code">{{
                          template.templateKey
                        }}</span>
                      </div>
                      <div class="detail-item">
                        <label>{{ t("emailTpl_detailLabelCategory") }}</label>
                        <span class="badge badge-category">{{
                          formatCategory(template.category)
                        }}</span>
                      </div>
                      <div class="detail-item">
                        <label>{{
                          t("emailTpl_detailLabelRecipientType")
                        }}</label>
                        <span class="badge badge-recipient">{{
                          formatRecipientType(template.recipientType)
                        }}</span>
                      </div>
                      <div class="detail-item">
                        <label>{{ t("emailTpl_detailLabelStatus") }}</label>
                        <span
                          class="badge badge-status"
                          :class="template.isActive ? 'active' : 'inactive'"
                        >
                          {{
                            template.isActive
                              ? t("emailTpl_statusActive")
                              : t("emailTpl_statusInactive")
                          }}
                        </span>
                      </div>
                      <div class="detail-item">
                        <label>{{ t("emailTpl_detailLabelCreatedAt") }}</label>
                        <span>{{ formatDateTime(template.createdAt) }}</span>
                      </div>
                      <div class="detail-item">
                        <label>{{ t("emailTpl_detailLabelUpdatedAt") }}</label>
                        <span>{{ formatDateTime(template.updatedAt) }}</span>
                      </div>
                    </div>
                  </div>

                  <div class="detail-section" v-if="template.description">
                    <h3>
                      <i class="fas fa-align-left"></i>
                      {{ t("emailTpl_sectionDescription") }}
                    </h3>
                    <p class="detail-text">{{ template.description }}</p>
                  </div>

                  <div class="detail-section">
                    <h3>
                      <i class="fas fa-envelope"></i>
                      {{ t("emailTpl_sectionEmailSubject") }}
                    </h3>
                    <p class="detail-text">{{ template.emailSubject }}</p>
                  </div>

                  <div class="detail-section">
                    <h3>
                      <i class="fas fa-file-code"></i>
                      {{ t("emailTpl_sectionEmailBodyHtml") }}
                    </h3>
                    <div class="email-body-preview-wrapper">
                      <iframe
                        :srcdoc="sanitizeEmailBody(template.emailBody)"
                        class="email-body-iframe"
                        frameborder="0"
                      ></iframe>
                    </div>
                  </div>

                  <div
                    class="detail-section"
                    v-if="
                      template.variables &&
                      Object.keys(template.variables).length > 0
                    "
                  >
                    <h3>
                      <i class="fas fa-code"></i>
                      {{ t("emailTpl_sectionAvailableVariables") }}
                    </h3>
                    <div class="variables-grid">
                      <div
                        v-for="(desc, key) in template.variables"
                        :key="key"
                        class="variable-item"
                      >
                        <div class="variable-key">
                          {{ formatVariable(key) }}
                        </div>
                        <div class="variable-desc">{{ desc }}</div>
                      </div>
                    </div>
                  </div>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="pagination">
        <button
          class="btn btn-secondary"
          :disabled="currentPage === 1"
          @click="changePage(currentPage - 1)"
        >
          <i class="fas fa-chevron-left"></i> {{ t("emailTpl_previous") }}
        </button>
        <span class="pagination-info">
          {{ paginationLabel }}
        </span>
        <button
          class="btn btn-secondary"
          :disabled="currentPage === totalPages"
          @click="changePage(currentPage + 1)"
        >
          {{ t("emailTpl_next") }} <i class="fas fa-chevron-right"></i>
        </button>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <EmailTemplateModal
      v-if="showModal"
      :template="currentTemplate"
      @close="closeModal"
      @save="handleSave"
    />
  </div>
</template>

<script setup>
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";

import { ref, reactive, onMounted, computed } from "vue";
import { useAuthStore } from "@/stores/auth";
import emailTemplateApi from "@/services/emailTemplateApi";
import EmailTemplateModal from "@/components/email/EmailTemplateModal.vue";
import { formatNumber } from "@/utils/helpers";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t, tParams, languageStore } = useAdminI18n();
const authStore = useAuthStore();

const paginationLabel = computed(() =>
  tParams(
    "emailTpl_pagination",
    "Page {current} of {totalPages} ({total} templates)",
    {
      current: formatNumber(currentPage.value),
      totalPages: formatNumber(totalPages.value),
      total: formatNumber(total.value),
    },
  ),
);

// 权限检查
const hasReadonlyPermission = computed(() =>
  authStore.hasPermission("page_emailtemplates_readonly"),
);
const hasCreatePermission = computed(() =>
  authStore.hasPermission("page_emailtemplates_create"),
);
const hasEditPermission = computed(() =>
  authStore.hasPermission("page_emailtemplates_edit"),
);
const hasDeletePermission = computed(() =>
  authStore.hasPermission("page_emailtemplates_delete"),
);
const hasDisablePermission = computed(() =>
  authStore.hasPermission("page_emailtemplates_disable"),
);

const loading = ref(false);
const error = ref(null);
const templates = ref([]);
const categories = ref([]);
const currentPage = ref(1);
const perPage = ref(10);
const total = ref(0);
const totalPages = ref(0);
const showModal = ref(false);
const currentTemplate = ref(null);
const expandedRows = ref([]);

const filters = reactive({
  category: "",
  recipientType: "",
  isActive: null,
  search: "",
});

let searchTimeout = null;

const loadTemplates = async () => {
  loading.value = true;
  error.value = null;

  try {
    const params = {
      page: currentPage.value,
      per_page: perPage.value,
      ...filters,
    };

    // 移除空值
    Object.keys(params).forEach((key) => {
      if (params[key] === "" || params[key] === null) {
        delete params[key];
      }
    });

    const response = await emailTemplateApi.getTemplates(params);
    if (response.success) {
      // 处理templates数据，确保variables字段被正确解析
      templates.value = (response.data.items || []).map((template) => {
        if (template.variables && typeof template.variables === "string") {
          try {
            template.variables = JSON.parse(template.variables);
          } catch (e) {
            console.error(
              "Failed to parse variables JSON for template:",
              template.id,
              e,
            );
            template.variables = {};
          }
        }
        return template;
      });
      total.value = response.data.pagination?.total || 0;
      totalPages.value = response.data.pagination?.total_pages || 0;
      currentPage.value = response.data.pagination?.page || 1;
    } else {
      error.value = response.message || t("emailTpl_loadFailed");
    }
  } catch (err) {
    error.value =
      err.response?.data?.message || err.message || t("emailTpl_loadFailed");
  } finally {
    loading.value = false;
  }
};

const loadCategories = async () => {
  try {
    const response = await emailTemplateApi.getCategories();
    if (response.success) {
      categories.value = response.data || [];
    }
  } catch (err) {
    console.error("Failed to load categories:", err);
  }
};

const handleSearch = () => {
  if (searchTimeout) {
    clearTimeout(searchTimeout);
  }
  searchTimeout = setTimeout(() => {
    currentPage.value = 1;
    loadTemplates();
  }, 500);
};

const changePage = (page) => {
  currentPage.value = page;
  loadTemplates();
};

const toggleDetail = (id) => {
  const index = expandedRows.value.indexOf(id);
  if (index > -1) {
    expandedRows.value.splice(index, 1);
  } else {
    expandedRows.value.push(id);
  }
};

const openCreateModal = () => {
  currentTemplate.value = null;
  showModal.value = true;
};

const openEditModal = (template) => {
  // 确保variables字段被正确解析（如果是JSON字符串则解析，如果是对象则直接使用）
  const templateCopy = { ...template };
  if (templateCopy.variables && typeof templateCopy.variables === "string") {
    try {
      templateCopy.variables = JSON.parse(templateCopy.variables);
    } catch (e) {
      console.error("Failed to parse variables JSON:", e);
      templateCopy.variables = {};
    }
  }
  currentTemplate.value = templateCopy;
  showModal.value = true;
};

// 清理邮件正文，移除可能影响页面样式的style标签，并包装在iframe中
const sanitizeEmailBody = (html) => {
  if (!html) return "";
  // 创建一个临时div来解析HTML
  const tempDiv = document.createElement("div");
  tempDiv.innerHTML = html;
  // 移除所有style标签
  const styleTags = tempDiv.querySelectorAll("style");
  styleTags.forEach((tag) => tag.remove());
  // 返回完整的HTML文档结构
  return `<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>${tempDiv.innerHTML}</body></html>`;
};

const closeModal = () => {
  showModal.value = false;
  currentTemplate.value = null;
};

const handleSave = () => {
  closeModal();
  loadTemplates();
};

const toggleActive = async (id, currentStatus) => {
  try {
    const response = await emailTemplateApi.toggleActive(id);
    if (response.success) {
      await loadTemplates();
    } else {
      alert(response.message || t("emailTpl_err_toggleStatus"));
    }
  } catch (err) {
    alert(
      err.response?.data?.message ||
        err.message ||
        t("emailTpl_err_toggleStatus"),
    );
  }
};

const handleDelete = async (id, name) => {
  if (
    !confirm(
      tParams(
        "emailTpl_confirmDelete",
        'Are you sure you want to delete template "{name}"?',
        { name },
      ),
    )
  ) {
    return;
  }

  try {
    const response = await emailTemplateApi.deleteTemplate(id);
    if (response.success) {
      await loadTemplates();
    } else {
      alert(response.message || t("emailTpl_err_delete"));
    }
  } catch (err) {
    alert(
      err.response?.data?.message || err.message || t("emailTpl_err_delete"),
    );
  }
};

const formatCategory = (category) => {
  if (!category) return "";
  return t(`emailTpl_cat_${category}`, category);
};

const formatRecipientType = (r) => {
  if (!r) return "";
  return t(`emailTpl_recipient_${r}`, r);
};

const formatDateTime = (dateString) => {
  if (!dateString) return "";
  const date = new Date(dateString);
  const loc = languageStore.currentLanguage === "zh" ? "zh-CN" : "en-US";
  return date.toLocaleString(loc, {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
};

const formatVariable = (key) => {
  return `{{${key}}}`;
};

onMounted(() => {
  loadCategories();
  loadTemplates();
});
</script>

<style scoped>
.container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 40px 20px;
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
}

.page-title h1 i {
  margin-right: 10px;
  color: var(--color-brand);
}

.page-title p {
  font-size: 14px;
  color: var(--color-muted);
}

.page-actions {
  display: flex;
  align-items: center;
  gap: 20px;
}

.filters-section {
  display: flex;
  gap: 15px;
  margin-bottom: 30px;
  padding: 20px;
  background: var(--color-surface);
  border-radius: var(--radius-md);
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
  flex-wrap: wrap;
  align-items: flex-end;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.filter-group label {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text);
}

.filter-group select,
.filter-group input {
  padding: 8px 12px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: 14px;
  min-width: 150px;
}

.filter-group input {
  min-width: 200px;
}

.loading-container,
.error-container,
.empty-state {
  text-align: center;
  padding: 60px 20px;
  background: var(--color-surface);
  border-radius: var(--radius-md);
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.loading-container i,
.error-container i {
  font-size: 48px;
  color: var(--color-brand);
  margin-bottom: 15px;
}

.error-container i {
  color: var(--color-danger);
}

.empty-state i {
  font-size: 64px;
  color: var(--color-border-strong);
  margin-bottom: 15px;
}

.templates-table-container {
  background: var(--color-surface);
  border-radius: var(--radius-md);
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

.templates-table {
  width: 100%;
  border-collapse: collapse;
}

.templates-table thead {
  background: var(--color-surface-soft);
  border-bottom: 2px solid var(--color-border);
}

.templates-table th {
  padding: 15px;
  text-align: left;
  font-weight: 600;
  font-size: 13px;
  color: var(--color-text);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.templates-table tbody tr {
  border-bottom: 1px solid var(--color-border);
  transition: background-color 0.2s ease;
}

.templates-table tbody tr:hover {
  background: var(--color-surface-soft);
}

.templates-table tbody tr.expanded {
  background: var(--color-surface-soft);
}

.templates-table td {
  padding: 15px;
  font-size: 14px;
  color: var(--color-ink);
  vertical-align: middle;
}

.template-name-cell strong {
  color: var(--color-ink);
  font-size: 15px;
}

.template-key-badge {
  font-family: monospace;
  font-size: 12px;
  background: var(--color-surface-soft);
  padding: 4px 8px;
  border-radius: 4px;
  color: var(--color-text);
  border: 1px solid var(--color-border);
}

.badge {
  padding: 4px 12px;
  border-radius: var(--radius-lg);
  font-size: 12px;
  font-weight: 600;
  display: inline-block;
}

.badge-category {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.badge-recipient {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.badge-status.active {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.badge-status.inactive {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.action-buttons {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.btn-action {
  padding: 6px 12px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  white-space: nowrap;
}

.btn-action i {
  margin-right: 4px;
}

.btn-detail {
  background: var(--color-border);
  color: var(--color-text);
}

.btn-detail:hover {
  background: var(--color-border-strong);
}

.btn-edit {
  background: var(--color-brand-solid);
  color: white;
}

.btn-edit:hover {
  background: var(--color-brand-strong);
}

.btn-warning {
  background: #ed8936;
  color: white;
}

.btn-warning:hover {
  background: #dd6b20;
}

.btn-success {
  background: var(--color-success-solid);
  color: white;
}

.btn-success:hover {
  background: var(--color-success-solid);
}

.btn-delete {
  background: var(--color-danger-solid);
  color: white;
}

.btn-delete:hover {
  background: var(--color-danger-solid);
}

.detail-row {
  display: none;
}

.detail-row.show {
  display: table-row;
}

.detail-content {
  padding: 30px;
  background: var(--color-surface-soft);
  border-top: 2px solid var(--color-border);
}

.detail-section {
  margin-bottom: 30px;
}

.detail-section:last-child {
  margin-bottom: 0;
}

.detail-section h3 {
  font-size: 16px;
  color: var(--color-ink);
  margin-bottom: 15px;
  padding-bottom: 10px;
  border-bottom: 1px solid var(--color-border);
}

.detail-section h3 i {
  margin-right: 8px;
  color: var(--color-brand);
}

.detail-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 15px;
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.detail-item label {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.detail-item span {
  font-size: 14px;
  color: var(--color-ink);
}

.template-key-code {
  font-family: monospace;
  background: var(--color-surface-muted);
  padding: 4px 8px;
  border-radius: 4px;
  color: var(--color-text);
}

.detail-text {
  font-size: 14px;
  color: var(--color-text);
  line-height: 1.6;
  background: var(--color-surface);
  padding: 15px;
  border-radius: var(--radius-sm);
  border: 1px solid var(--color-border);
}

.email-body-preview-wrapper {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  overflow: hidden;
  background: var(--color-surface);
}

.email-body-iframe {
  width: 100%;
  min-height: 400px;
  border: none;
  display: block;
}

.email-body-preview {
  background: var(--color-surface);
  padding: 20px;
  border-radius: var(--radius-sm);
  border: 1px solid var(--color-border);
  max-height: 400px;
  overflow-y: auto;
  font-size: 14px;
  line-height: 1.6;
  color: var(--color-ink);
}

.variables-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 15px;
}

.variable-item {
  background: var(--color-surface);
  padding: 12px;
  border-radius: var(--radius-sm);
  border: 1px solid var(--color-border);
}

.variable-key {
  font-family: monospace;
  font-size: 13px;
  font-weight: 600;
  color: var(--color-brand);
  margin-bottom: 5px;
}

.variable-desc {
  font-size: 12px;
  color: var(--color-muted);
}

.btn {
  padding: 8px 16px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-primary {
  background: var(--color-brand-solid);
  color: white;
}

.btn-primary:hover {
  background: var(--color-brand-strong);
}

.btn-secondary {
  background: var(--color-border);
  color: var(--color-text);
}

.btn-secondary:hover {
  background: var(--color-border-strong);
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 20px;
  margin: 30px;
  padding-top: 20px;
  border-top: 1px solid var(--color-border);
}

.pagination-info {
  font-size: 14px;
  color: var(--color-muted);
}
</style>
