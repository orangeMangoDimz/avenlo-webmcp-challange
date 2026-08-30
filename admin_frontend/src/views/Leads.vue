<template>
  <div class="container ui-page workspace-list-page">
    <!-- Page Header -->
    <div class="page-header ui-page-header">
      <div class="page-title ui-page-heading">
        <h1 class="ui-page-title">{{ t("page_leads_title") }}</h1>
        <p class="ui-page-description">{{ t("page_leads_sub") }}</p>
      </div>
      <div class="page-actions ui-page-actions">
        <PageHeaderActions />
      </div>
    </div>

    <!-- Statistics Header -->
    <div class="stats-header ui-surface ui-summary-band">
      <div>
        <h2>{{ t("leads_stats_heading") }}</h2>
        <p>{{ t("leads_stats_sub") }}</p>
      </div>
      <div class="page-stats">
        <div class="stat-badge">
          <i class="fas fa-user-plus"></i>
          <span>{{
            tParams("leads_stat_totalLeads", "{n} Total Leads", {
              n: formatNumber(statistics.leads.totalLeads || 0),
            })
          }}</span>
        </div>
        <div class="stat-badge success">
          <i class="fas fa-check-circle"></i>
          <span>{{
            tParams("leads_stat_newToday", "{n} New Today", {
              n: formatNumber(statistics.leads.todayLeads || 0),
            })
          }}</span>
        </div>
      </div>
    </div>

    <!-- Search and Filter Section -->
    <div class="search-filter-section ui-surface">
      <div class="search-filter-container">
        <!-- Search Input -->
        <div class="search-input-wrapper">
          <i class="fas fa-search search-icon"></i>
          <input
            type="text"
            v-model="searchTerm"
            :placeholder="t('leads_searchPlaceholder')"
            @input="handleSearch"
          />
          <button
            type="button"
            v-if="searchTerm"
            class="fas fa-times clear-search"
            aria-label="Clear search"
            @click="clearSearch"
          ></button>
        </div>

        <!-- Quick Search Tags -->
        <div class="tags-section">
          <div class="tags-header">
            <h4>{{ t("leads_quickSearchTags") }}</h4>
            <button
              type="button"
              v-if="hasTagsPermission"
              class="btn-add-tag"
              @click="showSearchTagModal = true"
            >
              <i class="fas fa-plus"></i> {{ t("leads_btnSearchTag") }}
            </button>
          </div>
          <div class="tags-container">
            <div
              v-for="tag in searchTags"
              :key="tag.id"
              class="search-tag-control"
            >
              <button
                type="button"
                :class="['search-tag', { active: activeSearchTag === tag.id }]"
                @click="applySearchTag(tag)"
              >
                <i class="fas fa-tag"></i> {{ tag.tagName }}
              </button>
              <button
                v-if="hasTagsPermission"
                type="button"
                :class="['tag-remove', { active: activeSearchTag === tag.id }]"
                :aria-label="
                  tParams('leads_removeTagTitle', 'Remove {name} tag', {
                    name: tag.tagName,
                  })
                "
                @click="removeSearchTag(tag.id)"
              >
                ×
              </button>
            </div>
            <span v-if="searchTags.length === 0" class="empty-tags-message">
              {{ t("leads_noTagsYet") }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Leads Table -->
    <div class="leads-table-container ui-data-region">
      <div class="table-header ui-toolbar">
        <div class="table-header-left">
          <h2>{{ t("leads_registeredLeads") }}</h2>

          <!-- Bulk Actions -->
          <div v-if="hasSelection" class="bulk-actions">
            <span class="bulk-actions-label">{{
              t("leads_bulkSelected")
            }}</span>
            <span class="bulk-actions-count">{{ selectedLeadIds.length }}</span>
            <button
              type="button"
              v-if="hasAssignPermission"
              class="btn-bulk btn-bulk-assign"
              @click="showBulkAssignModal = true"
            >
              <i class="fas fa-user-check"></i> {{ t("leads_bulkAssign") }}
            </button>
            <button
              type="button"
              v-if="hasTagsPermission"
              class="btn-bulk btn-bulk-tag"
              @click="showBulkTagModal = true"
            >
              <i class="fas fa-tag"></i> {{ t("leads_bulkAddTag") }}
            </button>
            <div v-if="hasExportPermission" class="btn-bulk-export">
              <button
                type="button"
                class="btn-bulk"
                @click="toggleExportDropdown"
              >
                <i class="fas fa-download"></i> {{ t("leads_bulkExport") }}
              </button>
              <div v-if="showExportDropdown" class="export-dropdown">
                <button
                  type="button"
                  class="export-option csv"
                  @click="handleExport('csv')"
                >
                  <i class="fas fa-file-csv"></i>
                  <span>{{ t("leads_exportCsv") }}</span>
                </button>
                <button
                  type="button"
                  class="export-option excel"
                  @click="handleExport('excel')"
                >
                  <i class="fas fa-file-excel"></i>
                  <span>{{ t("leads_exportExcel") }}</span>
                </button>
              </div>
            </div>
            <button
              type="button"
              v-if="hasNotificationsPermission"
              class="btn-bulk btn-bulk-notification"
              @click="openBulkSendNotificationModal"
              :title="t('leads_bulkSendNotificationTitle')"
            >
              <span class="btn-bulk-notification-icon"
                ><i class="fas fa-bell"></i
              ></span>
              <span class="btn-bulk-notification-text">{{
                t("leads_bulkSendNotification")
              }}</span>
            </button>
          </div>
        </div>

        <div class="table-header-right">
          <div class="rows-selector">
            <label>{{ t("leads_showRows") }}</label>
            <select v-model="perPage" @change="handlePerPageChange">
              <option value="10">10</option>
              <option value="25">25</option>
              <option value="50">50</option>
              <option value="100">100</option>
              <option value="200">200</option>
              <option value="500">500</option>
              <option value="1000">1000</option>
            </select>
          </div>
          <button
            type="button"
            v-if="hasCreatePermission"
            class="btn-add-client"
            @click="addNewClient"
          >
            <i class="fas fa-plus"></i> {{ t("leads_addClient") }}
          </button>
          <div class="column-settings-wrapper">
            <button
              type="button"
              class="btn-column-settings"
              aria-label="Configure table columns"
              @click="toggleColumnSettings"
            >
              <i class="fas fa-cog"></i>
            </button>
            <div
              class="column-settings-dropdown"
              :class="{ show: showColumnSettings }"
            >
              <div class="column-settings-header">
                <i class="fas fa-columns"></i>
                {{ t("leads_columnSettings") }}
              </div>
              <div class="column-settings-body">
                <div
                  v-for="column in availableColumns"
                  :key="column.key"
                  class="column-setting-item"
                >
                  <input
                    type="checkbox"
                    :id="'lead-col-' + column.key"
                    :checked="visibleColumns.includes(column.key)"
                    @change="toggleColumn(column.key)"
                  />
                  <label :for="'lead-col-' + column.key">{{
                    column.label
                  }}</label>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="loading-state">
        <i class="fas fa-spinner fa-spin"></i>
        <p>{{ t("leads_loading") }}</p>
      </div>

      <!-- Table Scroll Wrapper -->
      <div v-else class="table-scroll-outer">
        <div
          ref="tableScrollWrapperRef"
          class="table-scroll-wrapper ui-table-scroll"
          :class="scrollClasses"
        >
          <table class="leads-table">
            <thead>
              <tr>
                <th class="checkbox-col">
                  <label class="custom-checkbox">
                    <input
                      type="checkbox"
                      :checked="allSelected"
                      :indeterminate="someSelected"
                      @change="toggleAllSelection"
                    />
                    <span class="checkbox-checkmark"></span>
                  </label>
                </th>
                <th
                  class="hideable"
                  :class="{ hidden: !visibleColumns.includes('leadId') }"
                >
                  {{ t("leads_col_leadId") }}
                </th>
                <th
                  class="hideable"
                  :class="{ hidden: !visibleColumns.includes('client') }"
                >
                  {{ t("leads_col_client") }}
                </th>
                <th
                  class="hideable"
                  :class="{ hidden: !visibleColumns.includes('phone') }"
                >
                  {{ t("leads_col_phone") }}
                </th>
                <th
                  class="hideable"
                  :class="{ hidden: !visibleColumns.includes('country') }"
                >
                  {{ t("leads_col_country") }}
                </th>
                <th
                  class="hideable"
                  :class="{ hidden: !visibleColumns.includes('manager') }"
                >
                  {{ t("leads_col_manager") }}
                </th>
                <th
                  class="hideable"
                  :class="{
                    hidden: !visibleColumns.includes('registrationDate'),
                  }"
                >
                  {{ t("leads_col_registrationDate") }}
                </th>
                <th
                  class="hideable"
                  :class="{ hidden: !visibleColumns.includes('tags') }"
                >
                  {{ t("leads_col_tags") }}
                </th>
                <th
                  class="hideable"
                  :class="{ hidden: !visibleColumns.includes('kycStatus') }"
                >
                  {{ t("leads_col_kycStatus") }}
                </th>
                <th
                  class="hideable"
                  :class="{
                    hidden: !visibleColumns.includes('firstLoginDate'),
                  }"
                >
                  {{ t("leads_col_firstLoginDate") }}
                </th>
                <th
                  class="hideable"
                  :class="{ hidden: !visibleColumns.includes('lastLoginDate') }"
                >
                  {{ t("leads_col_lastLoginDate") }}
                </th>
                <th
                  class="hideable"
                  :class="{ hidden: !visibleColumns.includes('status') }"
                >
                  {{ t("leads_col_status") }}
                </th>
                <th class="leads-table__th--sticky">
                  {{ t("leads_col_action") }}
                </th>
              </tr>
            </thead>
            <tbody>
              <template v-for="lead in leads" :key="lead.leadId">
                <!-- Main Row -->
                <tr :class="{ expanded: expandedLeadId === lead.leadId }">
                  <td class="checkbox-col">
                    <label class="custom-checkbox">
                      <input
                        type="checkbox"
                        :checked="lead.selected"
                        @change="toggleLeadSelection(lead.leadId)"
                      />
                      <span class="checkbox-checkmark"></span>
                    </label>
                  </td>
                  <td
                    class="hideable"
                    :class="{ hidden: !visibleColumns.includes('leadId') }"
                  >
                    <span class="lead-id">#{{ lead.leadId }}</span>
                  </td>
                  <td
                    class="hideable"
                    :class="{ hidden: !visibleColumns.includes('client') }"
                  >
                    <div class="client-info">
                      <div class="client-avatar">
                        {{ getInitials(lead.firstName, lead.lastName) }}
                      </div>
                      <div class="client-details">
                        <router-link
                          v-if="canViewClientDetail && lead.leadId != null"
                          class="client-name client-name--link"
                          :to="{
                            name: 'client-detail',
                            query: { id: lead.leadId },
                          }"
                          >{{ lead.firstName }} {{ lead.lastName }}</router-link
                        >
                        <div v-else class="client-name">
                          {{ lead.firstName }} {{ lead.lastName }}
                        </div>
                        <div class="client-email">{{ lead.email }}</div>
                      </div>
                    </div>
                  </td>
                  <td
                    class="hideable"
                    :class="{ hidden: !visibleColumns.includes('phone') }"
                  >
                    {{ lead.phone }}
                  </td>
                  <td
                    class="hideable"
                    :class="{ hidden: !visibleColumns.includes('country') }"
                  >
                    {{ lead.country }}
                  </td>
                  <td
                    class="hideable"
                    :class="{ hidden: !visibleColumns.includes('manager') }"
                  >
                    {{ lead.managerName || lead.manager || "-" }}
                  </td>
                  <td
                    class="hideable"
                    :class="{
                      hidden: !visibleColumns.includes('registrationDate'),
                    }"
                  >
                    {{ formatDate(lead.registrationDate) }}
                  </td>
                  <td
                    class="hideable"
                    :class="{ hidden: !visibleColumns.includes('tags') }"
                  >
                    <div class="lead-tags">
                      <span
                        v-for="tag in lead.tags"
                        :key="tag.id"
                        class="lead-tag"
                      >
                        <i class="fas fa-tag"></i> {{ tag.tagName }}
                        <button
                          type="button"
                          v-if="hasTagsPermission"
                          class="tag-remove-btn"
                          @click.stop="
                            removeLeadTag(lead.leadId, tag.id, tag.tagName)
                          "
                          :title="
                            tParams(
                              'leads_removeTagTitle',
                              'Remove {name} tag',
                              { name: tag.tagName },
                            )
                          "
                          :aria-label="
                            tParams(
                              'leads_removeTagTitle',
                              'Remove {name} tag',
                              { name: tag.tagName },
                            )
                          "
                        >
                          ×
                        </button>
                      </span>
                      <span
                        v-if="!lead.tags || lead.tags.length === 0"
                        class="no-tags"
                      >
                        {{ t("leads_noTags") }}
                      </span>
                    </div>
                  </td>
                  <td
                    class="hideable"
                    :class="{ hidden: !visibleColumns.includes('kycStatus') }"
                  >
                    <span
                      :class="[
                        'kyc-status-badge',
                        'ui-status',
                        getKycStatusClass(lead.kycStatus),
                      ]"
                    >
                      {{ formatKycStatus(lead.kycStatus) }}
                    </span>
                  </td>
                  <td
                    class="hideable"
                    :class="{
                      hidden: !visibleColumns.includes('firstLoginDate'),
                    }"
                  >
                    {{ formatDate(lead.firstLoginAt || lead.firstLoginDate) }}
                  </td>
                  <td
                    class="hideable"
                    :class="{
                      hidden: !visibleColumns.includes('lastLoginDate'),
                    }"
                  >
                    {{ formatDate(lead.lastLoginAt || lead.lastLoginDate) }}
                  </td>
                  <td
                    class="hideable"
                    :class="{ hidden: !visibleColumns.includes('status') }"
                  >
                    <span :class="['status-badge', 'ui-status', lead.status]">{{
                      formatLeadStatus(lead.status)
                    }}</span>
                  </td>
                  <td class="leads-table__cell--sticky">
                    <div class="lead-actions">
                      <button
                        type="button"
                        class="btn-action btn-detail btn-icon-only"
                        @click="toggleDetail(lead.leadId)"
                        :title="
                          expandedLeadId === lead.leadId
                            ? t('leads_btnHide')
                            : t('leads_btnDetail')
                        "
                        :aria-label="
                          expandedLeadId === lead.leadId
                            ? t('leads_btnHide')
                            : t('leads_btnDetail')
                        "
                      >
                        <i
                          :class="[
                            'fas',
                            expandedLeadId === lead.leadId
                              ? 'fa-chevron-up'
                              : 'fa-chevron-down',
                          ]"
                        ></i>
                      </button>
                      <router-link
                        v-if="canViewClientDetailProfile && lead.leadId != null"
                        class="btn-action btn-client-page btn-icon-only"
                        :to="{
                          name: 'client-detail',
                          query: { id: lead.leadId, source: leadsLogSubModule },
                        }"
                        :title="t('leads_btnDetail')"
                        :aria-label="t('leads_btnDetail')"
                      >
                        <i class="fas fa-address-card"></i>
                      </router-link>
                      <button
                        type="button"
                        v-if="
                          hasDirectApproveKycPermission &&
                          lead.kycStatus !== 'approved'
                        "
                        class="btn-action btn-approve-kyc"
                        @click.stop="handleApproveKyc(lead)"
                        title="Approve KYC"
                        aria-label="Approve KYC"
                      >
                        <i class="fas fa-user-check"></i> KYC
                      </button>
                      <button
                        type="button"
                        v-if="hasViewAsClientPermission"
                        class="btn-action btn-view-as-client btn-icon-only"
                        @click.stop="handleViewAsClient(lead)"
                        :title="t('leads_titleViewAsClient')"
                        :aria-label="t('leads_titleViewAsClient')"
                      >
                        <i class="fas fa-user"></i>
                      </button>
                      <button
                        type="button"
                        v-if="hasNotificationsPermission"
                        class="btn-action btn-notification btn-icon-only"
                        @click.stop="openSendNotificationModal(lead)"
                        :title="t('leads_titleSendNotification')"
                        :aria-label="t('leads_titleSendNotification')"
                      >
                        <i class="fas fa-bell"></i>
                      </button>
                    </div>
                  </td>
                </tr>

                <!-- Detail Row -->
                <LeadDetailRow
                  v-if="expandedLeadId === lead.leadId"
                  :lead="lead"
                  :colspan="visibleColumns.length + 2"
                  :can-edit="hasEditPermission"
                  @close="expandedLeadId = null"
                  @updated="loadLeads"
                />
              </template>

              <!-- Empty State -->
              <tr v-if="leads.length === 0">
                <td colspan="16" class="empty-state">
                  <i class="fas fa-users"></i>
                  <p>{{ t("leads_empty") }}</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Scroll Hint：below table, right-aligned -->
        <div class="scroll-hint">
          {{ t("leads_scrollHint") }} <i class="fas fa-arrow-right"></i>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="pagination.total_pages > 1" class="pagination">
        <button
          type="button"
          class="pagination-btn"
          :disabled="pagination.page === 1"
          @click="goToPage(pagination.page - 1)"
        >
          <i class="fas fa-chevron-left"></i> {{ t("leads_paginationPrev") }}
        </button>

        <div class="pagination-pages">
          <button
            type="button"
            v-for="page in visiblePages"
            :key="page"
            :class="['pagination-page', { active: page === pagination.page }]"
            @click="goToPage(page)"
          >
            {{ page }}
          </button>
        </div>

        <button
          type="button"
          class="pagination-btn"
          :disabled="pagination.page === pagination.total_pages"
          @click="goToPage(pagination.page + 1)"
        >
          {{ t("leads_paginationNext") }} <i class="fas fa-chevron-right"></i>
        </button>
      </div>
    </div>

    <!-- Modals -->
    <SearchTagModal
      v-model="showSearchTagModal"
      @confirm="handleCreateSearchTag"
    />

    <BulkAssignmentModal
      v-model="showBulkAssignModal"
      :selected-items="selectedLeadsData"
      context="lead"
      @confirm="handleBulkAssign"
    />

    <BulkTagModal
      v-model="showBulkTagModal"
      :selected-leads="selectedLeads"
      :available-tags="leadTags"
      @confirm="handleBulkTag"
    />

    <AddClientModal
      v-model="showAddClientModal"
      :country-options="countryOptions"
      :currency-options="currencyOptions"
      :manager-options="managerOptions"
      :kyc-templates="kycTemplates"
      :loading-options="loadingCreateOptions"
      log-sub-module-key="leads"
      @success="handleAddClientSuccess"
    />

    <SendNotificationModal
      v-if="
        notificationRecipient ||
        (bulkNotificationRecipients && bulkNotificationRecipients.length)
      "
      v-model="showSendNotificationModal"
      :recipient="
        notificationRecipient ||
        (bulkNotificationRecipients && bulkNotificationRecipients[0])
      "
      :recipients="bulkNotificationRecipients"
      section-key="leads"
      log-sub-module-key="leads"
      @send="handleSendNotification"
    />
  </div>
</template>

<script setup>
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";

import { ref, computed, onMounted, onUnmounted, watch, nextTick } from "vue";
import { useRoute } from "vue-router";
import { useLeadsStore } from "@/stores/leads";
import { useAuthStore } from "@/stores/auth";
import LeadDetailRow from "@/components/leads/LeadDetailRow.vue";
import SearchTagModal from "@/components/leads/SearchTagModal.vue";
import BulkAssignmentModal from "@/components/leads/BulkAssignmentModal.vue";
import BulkTagModal from "@/components/leads/BulkTagModal.vue";
import SendNotificationModal from "@/components/client/SendNotificationModal.vue";
import { clientService } from "@/services/clientListService";
import { createPreviewToken } from "@/services/leadsService";
import { recordOperationLog } from "@/services/operationLogReportApi";
import {
  buildExportLogPayload,
  getSubModuleKey,
} from "@/config/operationLogPages";
import AddClientModal from "@/components/client/AddClientModal.vue";
import { formatNumber } from "@/utils/helpers";
import { CLIENT_DETAIL_PERMISSION_KEYS } from "@/utils/constants";
import { useAdminI18n } from "@/composables/useAdminI18n";

const leadsLogSubModule = getSubModuleKey("page_leads");

const { t, tParams, languageStore } = useAdminI18n();

const route = useRoute();
const leadsStore = useLeadsStore();
const authStore = useAuthStore();

// Permission checks for Leads page
const hasCreatePermission = computed(() =>
  authStore.hasPermission("page_leads_create"),
);
const hasEditPermission = computed(() =>
  authStore.hasPermission("page_leads_edit"),
);
const hasTagsPermission = computed(() =>
  authStore.hasPermission("page_leads_tags"),
);
const hasNotificationsPermission = computed(() =>
  authStore.hasPermission("page_leads_notifications"),
);
const hasAssignPermission = computed(() =>
  authStore.hasPermission("page_leads_assign"),
);
const hasExportPermission = computed(() =>
  authStore.hasPermission("page_leads_export"),
);
const hasViewAsClientPermission = computed(() =>
  authStore.hasPermission("page_leads_view_as_client"),
);
const hasDirectApproveKycPermission = computed(() =>
  authStore.hasPermission("page_leads_direct_approve_kyc"),
);
// 能否进客户详情页：与路由守卫口径一致，8 个详情权限任一即可
const canViewClientDetail = computed(() =>
  CLIENT_DETAIL_PERMISSION_KEYS.some((key) => authStore.hasPermission(key)),
);
const canViewClientDetailProfile = computed(() =>
  authStore.hasPermission("page_clientsdetail_profile"),
);

// State
const searchTerm = ref("");
const activeSearchTag = ref(null);
const perPage = ref(10);
const expandedLeadId = ref(null);
const showSearchTagModal = ref(false);
const showBulkAssignModal = ref(false);
const showBulkTagModal = ref(false);
const showExportDropdown = ref(false);
const searchTimeout = ref(null);
const showAddClientModal = ref(false);
const showSendNotificationModal = ref(false);
const selectedLeadForNotification = ref(null);
const bulkNotificationRecipients = ref(null);
const showColumnSettings = ref(false);
/** 表格横向滚动容器（避免 querySelector 误选其它页面元素；语言切换后需重新测量） */
const tableScrollWrapperRef = ref(null);

// 横向滚动状态
const scrollClasses = ref({
  "has-overflow": false,
  "has-scroll-left": false,
  "has-scroll-right": false,
  "show-scroll-hint": false,
});

const availableColumns = computed(() => [
  { key: "leadId", label: t("leads_col_leadId") },
  { key: "client", label: t("leads_col_client") },
  { key: "phone", label: t("leads_col_phone") },
  { key: "country", label: t("leads_col_country") },
  { key: "manager", label: t("leads_col_manager") },
  { key: "tags", label: t("leads_col_tags") },
  { key: "kycStatus", label: t("leads_col_kycStatus") },
  { key: "registrationDate", label: t("leads_col_registrationDate") },
  { key: "firstLoginDate", label: t("leads_col_firstLoginDate") },
  { key: "lastLoginDate", label: t("leads_col_lastLoginDate") },
  { key: "status", label: t("leads_col_status") },
]);

const defaultLeadVisibleColumns = [
  "leadId",
  "client",
  "phone",
  "country",
  "manager",
  "tags",
  "kycStatus",
  "registrationDate",
  "firstLoginDate",
  "lastLoginDate",
  "status",
];

const visibleColumns = ref([...defaultLeadVisibleColumns]);

const countryOptions = ref([]);
const currencyOptions = ref([]);
const managerOptions = ref([]);
const kycTemplates = ref([]);
const createOptionsLoaded = ref(false);
const loadingCreateOptions = ref(false);

// Computed
const leads = computed(() => leadsStore.leads);
const leadTags = computed(() => leadsStore.leadTags);
const searchTags = computed(() => leadsStore.searchTags);
const statistics = computed(() => leadsStore.statistics);
const pagination = computed(() => leadsStore.pagination);
const loading = computed(() => leadsStore.loading);
const selectedLeads = computed(() => leadsStore.selectedLeads);
const selectedLeadIds = computed(() => leadsStore.selectedLeadIds);
const hasSelection = computed(() => leadsStore.hasSelection);
const selectedLeadsData = computed(() => {
  return (selectedLeads.value || []).map((lead) => ({
    id: lead.leadId ?? lead.id ?? null,
    leadId: lead.leadId ?? lead.id ?? null,
    firstName: lead.firstName ?? "",
    lastName: lead.lastName ?? "",
    email: lead.email ?? "",
  }));
});

const allSelected = computed(() => {
  return leads.value.length > 0 && leads.value.every((lead) => lead.selected);
});

const someSelected = computed(() => {
  return leads.value.some((lead) => lead.selected) && !allSelected.value;
});

const visiblePages = computed(() => {
  const current = pagination.value.page;
  const total = pagination.value.total_pages;
  const pages = [];

  let start = Math.max(1, current - 2);
  let end = Math.min(total, current + 2);

  if (current <= 3) {
    end = Math.min(5, total);
  }
  if (current >= total - 2) {
    start = Math.max(1, total - 4);
  }

  for (let i = start; i <= end; i++) {
    pages.push(i);
  }

  return pages;
});

// Methods
const loadLeads = async (params = {}) => {
  try {
    const queryParams = {
      page: params.page || pagination.value.page,
      per_page: perPage.value,
      ...params,
    };

    if (searchTerm.value) {
      queryParams.search = searchTerm.value;
    }

    await leadsStore.fetchLeads(queryParams);

    // 检查滚动状态
    nextTick(() => {
      checkScrollState();
    });
  } catch (error) {
    alert(
      tParams("leads_alert_loadFailed", "Failed to load leads: {msg}", {
        msg: error.message || t("common_errorUnknown"),
      }),
    );
  } finally {
    // 确保在加载完成后也检查滚动状态
    nextTick(() => {
      checkScrollState();
    });
  }
};

const loadStatistics = async () => {
  await leadsStore.fetchStatistics();
};

const loadTags = async () => {
  await leadsStore.fetchLeadTags(true);
};

const loadSearchTags = async () => {
  await leadsStore.fetchSearchTags(true);
};

const loadCreateOptions = async () => {
  if (loadingCreateOptions.value || createOptionsLoaded.value) return;

  loadingCreateOptions.value = true;
  try {
    const [optionsRes, salesRes] = await Promise.all([
      clientService.getCreateOptions(),
      clientService.getAssignableSales(),
    ]);
    const optionsData = optionsRes?.data ?? optionsRes ?? {};
    const salesData = salesRes?.data ?? salesRes ?? {};

    countryOptions.value = Array.isArray(optionsData.countries)
      ? optionsData.countries
      : [];
    currencyOptions.value = Array.isArray(optionsData.currencies)
      ? optionsData.currencies
      : [];
    managerOptions.value = Array.isArray(salesData.managers)
      ? salesData.managers
      : [];
    kycTemplates.value = Array.isArray(optionsData.templates)
      ? optionsData.templates
      : [];

    createOptionsLoaded.value = true;
  } catch (error) {
    console.error("Failed to load creation options:", error);
    const message =
      error.response?.data?.message ||
      error.message ||
      t("common_errorUnknown");
    alert(
      tParams(
        "leads_alert_loadCreateOptions",
        "Failed to load client creation options: {msg}",
        { msg: message },
      ),
    );
  } finally {
    loadingCreateOptions.value = false;
  }
};

const addNewClient = async () => {
  if (!createOptionsLoaded.value && !loadingCreateOptions.value) {
    await loadCreateOptions();
  }
  if (!createOptionsLoaded.value) return;
  showAddClientModal.value = true;
};

const openSendNotificationModal = (lead) => {
  selectedLeadForNotification.value = lead;
  bulkNotificationRecipients.value = null;
  showSendNotificationModal.value = true;
};

const openBulkSendNotificationModal = () => {
  if (!selectedLeadsData.value.length) return;
  bulkNotificationRecipients.value = selectedLeadsData.value.map((item) => ({
    id: item.id ?? item.leadId,
    leadId: item.leadId ?? item.id,
    email: item.email,
    firstName: item.firstName,
    lastName: item.lastName,
  }));
  selectedLeadForNotification.value = null;
  showSendNotificationModal.value = true;
};

const handleViewAsClient = async (lead) => {
  try {
    const res = await createPreviewToken(lead.leadId);
    const url = res?.data?.previewUrl;
    if (!url) {
      alert(t("leads_alert_previewUrlFailed"));
      return;
    }
    window.open(url, "_blank", "noopener,noreferrer");
  } catch (e) {
    const msg =
      e?.response?.data?.message ||
      e?.message ||
      t("leads_alert_previewLinkFailed");
    alert(msg);
  }
};

const handleSendNotification = (result) => {
  if (result?.bulk) {
    const { successCount, failCount } = result;
    const total = (result.successCount ?? 0) + (result.failCount ?? 0);
    alert(
      tParams(
        "leads_notif_bulkDone",
        "Bulk notification finished. Sent: {success}, Failed: {fail} ({total} total).",
        {
          success: String(successCount ?? 0),
          fail: String(failCount ?? 0),
          total: String(total),
        },
      ),
    );
    showSendNotificationModal.value = false;
    bulkNotificationRecipients.value = null;
    loadLeads();
    return;
  }

  const payload = result?.payload ?? {};
  const recipient = result?.recipient ?? {};
  const response = result?.response ?? {};

  const types = [];
  if (payload.sendSystemNotification)
    types.push(t("leads_notif_channelSystem"));
  if (payload.sendEmail) types.push(t("leads_notif_channelEmail"));
  const typeDisplay = types.length
    ? types.join(" & ")
    : t("leads_notif_typeNa");

  const loc = languageStore.currentLanguage === "zh" ? "zh-CN" : "en-US";
  let message = response.message || t("leads_notif_processedOk");
  if (!response.message) {
    if (payload.scheduleType === "scheduled" && payload.scheduledAt) {
      const scheduledDate = new Date(payload.scheduledAt);
      const localTime = scheduledDate.toLocaleString(loc, {
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
        hour12: false,
      });
      message = tParams(
        "leads_notif_scheduledFor",
        "Notification scheduled for {time}!",
        { time: localTime },
      );
    } else {
      message = t("leads_notif_sentOk");
    }
  }

  const recipientDisplay =
    recipient.name ||
    recipient.email ||
    `Lead #${payload.clientId || payload.leadId || ""}`;
  alert(
    tParams(
      "leads_notif_resultBlock",
      "{message}\nType: {type}\nRecipient: {recipient}",
      {
        message,
        type: typeDisplay,
        recipient: recipientDisplay,
      },
    ),
  );

  showSendNotificationModal.value = false;
  selectedLeadForNotification.value = null;
};

const handleSearch = () => {
  // Debounce search
  if (searchTimeout.value) {
    clearTimeout(searchTimeout.value);
  }

  searchTimeout.value = setTimeout(async () => {
    await loadLeads({ page: 1 });
  }, 300);
};

const clearSearch = () => {
  searchTerm.value = "";
  activeSearchTag.value = null;
  loadLeads({ page: 1 });
};

const applySearchTag = async (tag) => {
  if (activeSearchTag.value === tag.id) {
    // Toggle off
    activeSearchTag.value = null;
    searchTerm.value = "";
  } else {
    // Apply tag search
    activeSearchTag.value = tag.id;
    searchTerm.value = tag.searchKeywords;
  }

  await loadLeads({ page: 1 });
};

const removeSearchTag = async (tagId) => {
  if (confirm(t("leads_confirm_removeSearchTag"))) {
    try {
      await leadsStore.deleteSearchTag(tagId);
      if (activeSearchTag.value === tagId) {
        clearSearch();
      }
      alert(t("leads_alert_searchTagRemoved"));
    } catch (error) {
      alert(
        tParams(
          "leads_alert_searchTagRemoveFailed",
          "Failed to remove search tag: {msg}",
          {
            msg: error.message || t("common_errorUnknown"),
          },
        ),
      );
    }
  }
};

const handleCreateSearchTag = async (data) => {
  try {
    await leadsStore.createSearchTag({
      ...data,
      logSubModuleKey: leadsLogSubModule,
    });
    alert(t("leads_alert_searchTagCreated"));
  } catch (error) {
    alert(
      tParams(
        "leads_alert_searchTagCreateFailed",
        "Failed to create search tag: {msg}",
        {
          msg: error.message || t("common_errorUnknown"),
        },
      ),
    );
  }
};

const toggleDetail = (leadId) => {
  expandedLeadId.value = expandedLeadId.value === leadId ? null : leadId;
};

const handleApproveKyc = async (lead) => {
  if (!hasDirectApproveKycPermission.value) {
    alert(t("err_40301"));
    return;
  }

  const leadId = lead?.leadId ?? lead?.id;
  if (!leadId) {
    alert("❌ Unable to determine lead ID");
    return;
  }

  const confirmed = window.confirm(
    "Approve this lead KYC now?\n\nIf the lead has no KYC submission yet, the system will create one automatically and mark it as approved.",
  );
  if (!confirmed) return;

  try {
    await clientService.approveKyc(leadId);
    alert("✓ KYC approved successfully!");
    await loadLeads();
  } catch (error) {
    const message =
      error.response?.data?.message || error.message || "Unknown error";
    alert("❌ Failed to approve KYC: " + message);
  }
};

const toggleLeadSelection = (leadId) => {
  leadsStore.toggleLeadSelection(leadId);
};

const toggleAllSelection = (event) => {
  leadsStore.toggleAllSelection(event.target.checked);
};

const handlePerPageChange = () => {
  loadLeads({ page: 1 });
};

const goToPage = (page) => {
  loadLeads({ page });
};

// 滚动检测
const checkScrollState = () => {
  const wrapper = tableScrollWrapperRef.value;
  if (!wrapper) return;

  wrapper.style.setProperty("--detail-panel-width", `${wrapper.clientWidth}px`);

  const hasHorizontalScroll = wrapper.scrollWidth > wrapper.clientWidth;
  const isAtLeft = wrapper.scrollLeft === 0;
  const isAtRight =
    wrapper.scrollLeft >= wrapper.scrollWidth - wrapper.clientWidth - 1;

  scrollClasses.value = {
    "has-overflow": hasHorizontalScroll,
    "has-scroll-left": hasHorizontalScroll && !isAtLeft,
    "has-scroll-right": hasHorizontalScroll && !isAtRight,
    "show-scroll-hint": hasHorizontalScroll && isAtLeft,
  };
};

/** 语言包更新后表头宽度变化，需在 DOM 布局稳定后再测 scrollWidth */
const scheduleTableScrollRecalc = () => {
  nextTick(() => {
    requestAnimationFrame(() => {
      checkScrollState();
      requestAnimationFrame(() => checkScrollState());
    });
  });
};

const toggleExportDropdown = () => {
  showExportDropdown.value = !showExportDropdown.value;
};

const handleExport = (format) => {
  showExportDropdown.value = false;

  if (selectedLeadIds.value.length === 0) {
    alert(t("leads_alert_exportNeedSelection"));
    return;
  }

  try {
    // 获取选中的 leads 数据
    const selectedLeads = leads.value.filter((lead) =>
      selectedLeadIds.value.includes(lead.leadId),
    );

    // 根据可见列定义导出字段
    const exportColumns = [
      {
        key: "leadId",
        label: t("leads_export_col_leadId"),
        getValue: (lead) => lead.leadId,
      },
      {
        key: "client",
        label: t("leads_export_col_firstName"),
        getValue: (lead) => lead.firstName,
      },
      {
        key: "client",
        label: t("leads_export_col_lastName"),
        getValue: (lead) => lead.lastName,
      },
      {
        key: "client",
        label: t("leads_export_col_email"),
        getValue: (lead) => lead.email,
      },
      {
        key: "phone",
        label: t("leads_export_col_phone"),
        getValue: (lead) => lead.phone || "",
      },
      {
        key: "country",
        label: t("leads_export_col_country"),
        getValue: (lead) => lead.country || "",
      },
      {
        key: "manager",
        label: t("leads_export_col_manager"),
        getValue: (lead) => lead.managerName || lead.manager || "",
      },
      {
        key: "registrationDate",
        label: t("leads_export_col_registrationDate"),
        getValue: (lead) => formatDate(lead.registrationDate),
      },
      {
        key: "tags",
        label: t("leads_export_col_tags"),
        getValue: (lead) =>
          (lead.tags || []).map((tg) => tg.tagName).join(", "),
      },
      {
        key: "kycStatus",
        label: t("leads_export_col_kycStatus"),
        getValue: (lead) => formatKycStatus(lead.kycStatus),
      },
      {
        key: "firstLoginDate",
        label: t("leads_export_col_firstLoginDate"),
        getValue: (lead) =>
          formatDate(lead.firstLoginAt || lead.firstLoginDate),
      },
      {
        key: "lastLoginDate",
        label: t("leads_export_col_lastLoginDate"),
        getValue: (lead) => formatDate(lead.lastLoginAt || lead.lastLoginDate),
      },
      {
        key: "status",
        label: t("leads_export_col_status"),
        getValue: (lead) => formatLeadStatus(lead.status),
      },
    ].filter((col) => visibleColumns.value.includes(col.key));

    // 构建 CSV 内容
    const headers = exportColumns.map((col) => col.label);
    const rows = selectedLeads.map((lead) =>
      exportColumns.map((col) => {
        const value = col.getValue(lead);
        // 处理包含逗号的值，用引号包裹
        return typeof value === "string" && value.includes(",")
          ? `"${value}"`
          : value || "";
      }),
    );

    // 生成 CSV 内容
    const csvContent = [
      headers.join(","),
      ...rows.map((row) => row.join(",")),
    ].join("\n");

    // 添加 BOM 以支持 Excel 正确显示中文
    const BOM = "\uFEFF";
    const blob = new Blob([BOM + csvContent], {
      type:
        format === "csv"
          ? "text/csv;charset=utf-8;"
          : "application/vnd.ms-excel;charset=utf-8;",
    });

    const link = document.createElement("a");
    const url = URL.createObjectURL(blob);
    link.setAttribute("href", url);
    link.setAttribute(
      "download",
      `leads_${new Date().toISOString().split("T")[0]}.${format === "csv" ? "csv" : "xls"}`,
    );
    link.style.visibility = "hidden";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);

    alert(
      tParams(
        "leads_alert_exportSuccess",
        "Successfully exported {n} lead(s) as {format}!",
        {
          n: String(selectedLeadIds.value.length),
          format: format.toUpperCase(),
        },
      ),
    );
    leadsStore.clearSelection();

    const fmtLabel = format.toUpperCase();
    recordOperationLog(
      buildExportLogPayload("page_leads", {
        detailZh: `导出 ${selectedLeads.length} 条 Lead（${fmtLabel}）`,
        detailEn: `Exported ${selectedLeads.length} lead(s) (${fmtLabel})`,
      }),
    ).catch(() => {});
  } catch (error) {
    console.error("Export error:", error);
    alert(
      tParams("leads_alert_exportFailed", "Failed to export leads: {msg}", {
        msg: error.message || t("common_errorUnknown"),
      }),
    );
  }
};

const toggleColumnSettings = () => {
  showColumnSettings.value = !showColumnSettings.value;
};

const toggleColumn = (columnKey) => {
  const index = visibleColumns.value.indexOf(columnKey);
  if (index > -1) {
    if (visibleColumns.value.length > 1) {
      visibleColumns.value.splice(index, 1);
      localStorage.setItem(
        "leadsVisibleColumns",
        JSON.stringify(visibleColumns.value),
      );
    }
  } else {
    visibleColumns.value.push(columnKey);
    localStorage.setItem(
      "leadsVisibleColumns",
      JSON.stringify(visibleColumns.value),
    );
  }
  nextTick(() => checkScrollState());
};

const restoreColumnSettings = () => {
  const saved = localStorage.getItem("leadsVisibleColumns");
  if (saved) {
    try {
      const parsed = JSON.parse(saved);
      if (Array.isArray(parsed)) {
        const allowedKeys = availableColumns.value.map((col) => col.key);
        const filtered = parsed.filter((key) => allowedKeys.includes(key));
        const hasLegacyColumns = parsed.some(
          (key) => !allowedKeys.includes(key),
        );

        if (hasLegacyColumns) {
          const merged = filtered.length > 0 ? [...filtered] : [];
          defaultLeadVisibleColumns.forEach((key) => {
            if (!merged.includes(key)) {
              merged.push(key);
            }
          });
          visibleColumns.value =
            merged.length > 0 ? merged : [...defaultLeadVisibleColumns];
        } else {
          visibleColumns.value =
            filtered.length > 0 ? filtered : [...defaultLeadVisibleColumns];
        }
        return;
      }
    } catch (error) {
      console.error("Failed to restore lead column settings:", error);
    }
  }
  visibleColumns.value = [...defaultLeadVisibleColumns];
};

const handleAddClientSuccess = async () => {
  await loadLeads();
  await loadStatistics();
};

const handleBulkAssign = async ({ managerId, notes }) => {
  if (!managerId) {
    alert(t("leads_alert_selectManager"));
    return;
  }

  if (selectedLeadIds.value.length === 0) {
    alert(t("leads_alert_selectLeadFirst"));
    return;
  }

  try {
    const ids = selectedLeadIds.value.map((id) => Number(id));
    await clientService.bulkAssign(ids, {
      assigneeId: managerId,
      notes: notes || undefined,
      logSubModuleKey: leadsLogSubModule,
    });
    alert(
      tParams(
        "leads_alert_bulkAssignOk",
        "Successfully assigned {n} lead(s) to manager!",
        {
          n: String(ids.length),
        },
      ),
    );
    showBulkAssignModal.value = false;
    leadsStore.clearSelection();
    await Promise.all([loadLeads(), loadStatistics()]);
  } catch (error) {
    console.error("Bulk assign failed:", error);
    const message =
      error.response?.data?.message ||
      error.message ||
      t("common_errorUnknown");
    alert(
      tParams("leads_alert_bulkAssignFailed", "Failed to assign leads: {msg}", {
        msg: message,
      }),
    );
  }
};

const handleBulkTag = async (data) => {
  try {
    if (data.tagId) {
      // Use existing tag
      await leadsStore.bulkAssign(selectedLeadIds.value, data.tagId);
      alert(
        tParams(
          "leads_alert_bulkTagOk",
          "Successfully added tag to {n} lead(s)!",
          {
            n: String(selectedLeadIds.value.length),
          },
        ),
      );
    } else if (data.tagName) {
      // Create new tag first
      const response = await leadsStore.createTag({
        tagName: data.tagName,
        tagColor: "#f59e0b",
      });

      if (response.success) {
        await leadsStore.bulkAssign(selectedLeadIds.value, response.data.id);
        alert(
          tParams(
            "leads_alert_bulkTagCreatedOk",
            "Successfully created tag and added to {n} lead(s)!",
            {
              n: String(selectedLeadIds.value.length),
            },
          ),
        );
      }
    }

    leadsStore.clearSelection();
    await loadTags();
  } catch (error) {
    alert(
      tParams("leads_alert_bulkTagFailed", "Failed to add tag: {msg}", {
        msg: error.message || t("common_errorUnknown"),
      }),
    );
  }
};

const removeLeadTag = async (leadId, tagId, tagName) => {
  // 确认删除
  if (
    !confirm(
      tParams(
        "leads_confirm_removeLeadTag",
        'Are you sure you want to remove the "{tag}" tag from this lead?',
        {
          tag: tagName,
        },
      ),
    )
  ) {
    return;
  }

  try {
    await leadsStore.removeTag(leadId, tagId);
    alert(
      tParams(
        "leads_alert_leadTagRemoved",
        'Successfully removed "{tag}" tag.',
        {
          tag: tagName,
        },
      ),
    );

    // 重新加载leads数据以更新显示
    await loadLeads();
  } catch (error) {
    alert(
      tParams(
        "leads_alert_leadTagRemoveFailed",
        "Failed to remove tag: {msg}",
        {
          msg: error.message || t("common_errorUnknown"),
        },
      ),
    );
  }
};

const getInitials = (firstName, lastName) => {
  const first = firstName ? firstName.charAt(0) : "";
  const last = lastName ? lastName.charAt(0) : "";
  return (first + last).toUpperCase();
};

const formatDate = (dateString) => {
  if (!dateString) return t("leads_na");
  const date = new Date(dateString);
  const loc = languageStore.currentLanguage === "zh" ? "zh-CN" : "en-US";
  return date.toLocaleDateString(loc, {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
};

const formatKycStatus = (status) => {
  if (!status) return t("leads_kyc_notStarted");
  const statusMap = {
    not_started: "leads_kyc_notStarted",
    in_progress: "leads_kyc_inProgress",
    pending_review: "leads_kyc_pendingReview",
    approved: "leads_kyc_approved",
    rejected: "leads_kyc_rejected",
  };
  const key = statusMap[status];
  return key ? t(key) : status;
};

const formatLeadStatus = (status) => {
  if (!status) return "";
  const statusMap = {
    active: "leads_account_active",
    inactive: "leads_account_inactive",
    suspended: "leads_account_suspended",
    pending_verification: "leads_account_pending_verification",
  };
  const key = statusMap[status];
  return key ? t(key) : status;
};

const getKycStatusClass = (status) => {
  if (!status) return "not-started";

  const classMap = {
    not_started: "not-started",
    in_progress: "in-progress",
    pending_review: "pending-review",
    approved: "approved",
    rejected: "rejected",
  };

  return classMap[status] || "not-started";
};

// Close export dropdown when clicking outside
const handleClickOutside = (event) => {
  if (!event.target.closest(".btn-bulk-export")) {
    showExportDropdown.value = false;
  }
  if (
    !event.target.closest(".btn-column-settings") &&
    !event.target.closest(".column-settings-dropdown")
  ) {
    showColumnSettings.value = false;
  }
};

// Lifecycle
onMounted(async () => {
  // 添加全局点击监听器
  document.addEventListener("click", handleClickOutside);

  restoreColumnSettings();

  if (route.query.search != null && route.query.search !== "") {
    searchTerm.value = String(route.query.search);
  }

  // 加载初始数据
  try {
    await Promise.all([
      loadLeads(),
      loadStatistics(),
      loadTags(),
      loadSearchTags(),
    ]);
    const detailId = route.query.detailId;
    if (detailId != null && detailId !== "") {
      const id = parseInt(detailId, 10);
      if (!Number.isNaN(id) && leads.value.length > 0) {
        const byUserId = leads.value.find(
          (l) =>
            (l.userId != null && Number(l.userId) === id) ||
            (l.id != null && Number(l.id) === id),
        );
        const byLeadId = leads.value.find(
          (l) => l.leadId != null && Number(l.leadId) === id,
        );
        const match = byUserId || byLeadId;
        expandedLeadId.value = match
          ? match.leadId
          : leads.value.length === 1
            ? leads.value[0].leadId
            : null;
      } else if (!Number.isNaN(id)) {
        expandedLeadId.value = id;
      }
    }
    loadCreateOptions().catch(() => {});
  } catch (error) {
    alert(t("leads_alert_loadDataFailed"));
  }

  // 设置滚动监听
  nextTick(() => {
    const wrapper = tableScrollWrapperRef.value;
    if (wrapper) {
      wrapper.addEventListener("scroll", checkScrollState);
    }
    window.addEventListener("resize", checkScrollState);
    checkScrollState();
  });
});

// 清理事件监听器
onUnmounted(() => {
  document.removeEventListener("click", handleClickOutside);
  const wrapper = tableScrollWrapperRef.value;
  if (wrapper) {
    wrapper.removeEventListener("scroll", checkScrollState);
  }
  window.removeEventListener("resize", checkScrollState);
});

// Watch perPage changes
watch(perPage, () => {
  loadLeads({ page: 1 });
});

// 管理端切换中/英文后表头文案宽度变化，需在语言包加载完成、DOM 更新后重新计算横向滚动
watch(
  () => languageStore.loading,
  (isLoading) => {
    if (isLoading) return;
    scheduleTableScrollRecalc();
  },
);

const notificationRecipient = computed(() => {
  const lead = selectedLeadForNotification.value;
  if (!lead) return null;
  return {
    id: lead.leadId ?? lead.id ?? null,
    email: lead.email,
    firstName: lead.firstName,
    lastName: lead.lastName,
  };
});

watch(showSendNotificationModal, (visible) => {
  if (!visible) {
    selectedLeadForNotification.value = null;
    bulkNotificationRecipients.value = null;
  }
});
</script>

<style scoped>
.container {
  padding: var(--content-padding);
}

/* Page Header */
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

.page-title p {
  font-size: 14px;
  color: var(--color-muted);
}

.page-actions {
  display: flex;
  align-items: center;
  gap: 20px;
}

.ui-page-header {
  align-items: flex-end;
  margin-bottom: var(--space-6);
  padding: 0;
  border: 0;
}

.ui-page-title {
  font-family: var(--font-display);
  font-size: clamp(26px, 2vw, 36px);
  font-weight: 600;
  line-height: 1.12;
  letter-spacing: -0.025em;
}

.ui-page-description {
  font-size: 14px;
}

/* Statistics Header */
.stats-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 2px solid var(--color-border);
}

.stats-header.ui-surface {
  margin-bottom: var(--space-5);
  padding: var(--space-5);
  border: 1px solid var(--color-border);
}

.stats-header h2 {
  font-size: 20px;
  color: var(--color-ink);
  margin-bottom: 5px;
}

.stats-header p {
  font-size: 14px;
  color: var(--color-muted);
}

.page-stats {
  display: flex;
  gap: 15px;
}

.stat-badge {
  background: var(--color-brand-soft);
  color: var(--color-brand);
  padding: 8px 16px;
  border-radius: 20px;
  font-size: 14px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 8px;
}

.stat-badge.success {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.stat-badge i {
  font-size: 16px;
}

/* Add Client Button */
.btn-add-client {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-brand-solid);
  color: #fff;
  font-weight: 600;
  cursor: pointer;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    transform 0.3s ease,
    opacity 0.3s ease,
    width 0.3s ease,
    max-height 0.3s ease,
    filter 0.3s ease;
  box-shadow: 0 10px 20px rgba(var(--color-brand-rgb), 0.25);
}

.btn-add-client:hover {
  transform: translateY(-1px);
  box-shadow: 0 12px 24px rgba(var(--color-brand-rgb), 0.35);
}

.btn-add-client i {
  font-size: 14px;
}

/* Add Client Modal */
.add-client-modal {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  display: none;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.6);
  z-index: 2200;
  padding: 30px;
}

.add-client-modal.show {
  display: flex;
}

.add-client-modal-content {
  background: var(--color-surface);
  border-radius: var(--radius-xl);
  width: min(960px, 100%);
  max-height: 90vh;
  overflow: hidden;
  box-shadow: 0 25px 50px -12px rgba(var(--color-brand-rgb), 0.35);
  display: flex;
  flex-direction: column;
}

.add-client-modal-header {
  padding: 24px 32px;
  border-bottom: 1px solid var(--color-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: linear-gradient(
    135deg,
    var(--color-brand-soft) 0%,
    var(--color-surface) 100%
  );
}

.add-client-modal-header h3 {
  margin: 0;
  font-size: 20px;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 12px;
}

.add-client-modal-header h3 i {
  color: var(--color-brand);
}

.add-client-modal-close {
  background: none;
  border: none;
  font-size: 28px;
  color: var(--color-faint);
  cursor: pointer;
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-md);
  transition:
    color 0.2s ease,
    background-color 0.2s ease,
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    transform 0.2s ease,
    opacity 0.2s ease,
    width 0.2s ease,
    max-height 0.2s ease,
    filter 0.2s ease;
}

.add-client-modal-close:hover {
  color: var(--color-brand);
  background: var(--color-brand-soft);
}

.add-client-modal-body {
  padding: 32px;
  overflow-y: auto;
}

.form-section {
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  border-radius: 14px;
  padding: 24px;
  margin-bottom: 24px;
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.6);
}

.form-section-title {
  font-size: 16px;
  font-weight: 700;
  color: var(--color-ink);
  margin-bottom: 18px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.form-section-title i {
  color: var(--color-brand);
}

.form-grid {
  display: grid;
  gap: 20px;
}

.form-grid.two-columns {
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
}

.form-grid.single-column {
  grid-template-columns: 1fr;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.form-field.full-width select,
.form-field.full-width input {
  width: 100%;
}

.form-field label {
  font-weight: 600;
  color: var(--color-text);
  font-size: 14px;
  display: flex;
  align-items: center;
  gap: 6px;
}

.form-field input,
.form-field select {
  width: 100%;
  padding: 12px 14px;
  border-radius: var(--radius-md);
  border: 1px solid #d1d9e6;
  background: var(--color-surface);
  font-size: 14px;
  transition:
    color 0.2s ease,
    background-color 0.2s ease,
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    transform 0.2s ease,
    opacity 0.2s ease,
    width 0.2s ease,
    max-height 0.2s ease,
    filter 0.2s ease;
  outline: none;
}

.form-field input:focus,
.form-field select:focus {
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.15);
}

.required {
  color: var(--color-danger);
  font-weight: 700;
}

.password-field .password-input-wrapper {
  position: relative;
}

.password-strength {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 12px;
  font-weight: 600;
  text-transform: capitalize;
}

.password-strength.medium {
  color: var(--color-warning);
}

.password-strength.strong {
  color: var(--color-success);
}

.generate-password-toggle {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 6px;
  font-size: 13px;
  color: var(--color-text);
}

.toggle {
  position: relative;
  width: 40px;
  height: 22px;
  display: inline-block;
}

.toggle input {
  opacity: 0;
  width: 0;
  height: 0;
}

.toggle-slider {
  position: absolute;
  cursor: pointer;
  inset: 0;
  background: var(--color-brand-soft);
  border-radius: 999px;
  transition: 0.4s;
}

.toggle-slider:before {
  position: absolute;
  content: "";
  height: 18px;
  width: 18px;
  left: 2px;
  bottom: 2px;
  background-color: var(--color-surface);
  border-radius: 50%;
  transition: 0.4s;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}

.toggle input:checked + .toggle-slider {
  background: var(--color-brand-solid);
}

.toggle input:checked + .toggle-slider:before {
  transform: translateX(18px);
}

.kyc-options {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 16px;
  margin-bottom: 18px;
}

.kyc-option-card {
  border: 2px dashed #cbd5f5;
  border-radius: 14px;
  padding: 18px;
  background: var(--color-surface);
  cursor: pointer;
  transition:
    color 0.2s ease,
    background-color 0.2s ease,
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    transform 0.2s ease,
    opacity 0.2s ease,
    width 0.2s ease,
    max-height 0.2s ease,
    filter 0.2s ease;
}

.kyc-option-card:hover {
  border-color: var(--color-brand);
  box-shadow: 0 12px 24px rgba(var(--color-brand-rgb), 0.12);
}

.kyc-option-card.active {
  border: 2px solid transparent;
  background:
    linear-gradient(var(--color-surface), var(--color-surface)) padding-box,
    linear-gradient(135deg, var(--color-brand), var(--color-brand-strong))
      border-box;
  box-shadow: 0 18px 30px rgba(var(--color-brand-rgb), 0.2);
}

.kyc-option-header {
  display: flex;
  align-items: center;
  gap: 10px;
  font-weight: 700;
  color: var(--color-ink);
  margin-bottom: 10px;
}

.kyc-option-header i {
  color: var(--color-brand);
}

.kyc-option-card p {
  margin: 0;
  font-size: 13px;
  color: var(--color-text);
  line-height: 1.6;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding-top: 16px;
  border-top: 1px solid var(--color-border);
}

.btn-modal-secondary,
.btn-modal-primary {
  padding: 12px 24px;
  border-radius: var(--radius-md);
  border: none;
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  transition:
    color 0.2s ease,
    background-color 0.2s ease,
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    transform 0.2s ease,
    opacity 0.2s ease,
    width 0.2s ease,
    max-height 0.2s ease,
    filter 0.2s ease;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.btn-modal-secondary {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.btn-modal-secondary:hover {
  background: var(--color-border);
}

.btn-modal-primary {
  background: var(--color-brand-solid);
  color: #fff;
  box-shadow: 0 12px 24px rgba(var(--color-brand-rgb), 0.25);
}

.btn-modal-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  box-shadow: none;
}

.btn-modal-primary:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 14px 28px rgba(var(--color-brand-rgb), 0.35);
}

.input-error {
  border-color: var(--color-danger) !important;
  box-shadow: 0 0 0 3px rgba(245, 101, 101, 0.15);
}

.error-message {
  margin: 0;
  font-size: 12px;
  color: var(--color-danger);
}

@media (max-width: 768px) {
  .add-client-modal {
    padding: 16px;
  }

  .add-client-modal-content {
    max-height: 92vh;
  }

  .add-client-modal-body {
    padding: 24px;
  }
}

/* Search and Filter Section */
.search-filter-section {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: 20px 25px;
  margin-bottom: 30px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.search-filter-container {
  display: flex;
  gap: 20px;
  align-items: flex-start;
  flex-wrap: wrap;
}

.search-input-wrapper {
  flex: 1;
  min-width: 300px;
  position: relative;
}

.search-input-wrapper input {
  width: 100%;
  padding: 12px 45px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  outline: none;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    transform 0.3s ease,
    opacity 0.3s ease,
    width 0.3s ease,
    max-height 0.3s ease,
    filter 0.3s ease;
}

.search-input-wrapper input:focus {
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.search-icon {
  position: absolute;
  left: 15px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-faint);
  font-size: 16px;
}

.clear-search {
  border: 0;
  background: transparent;
  position: absolute;
  right: 15px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-faint);
  font-size: 16px;
  cursor: pointer;
  transition:
    color 0.2s ease,
    background-color 0.2s ease,
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    transform 0.2s ease,
    opacity 0.2s ease,
    width 0.2s ease,
    max-height 0.2s ease,
    filter 0.2s ease;
}

.clear-search:hover {
  color: var(--color-brand);
}

.tags-section {
  flex: 1;
  min-width: 300px;
}

.tags-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.tags-header h4 {
  font-size: 13px;
  color: var(--color-text);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.btn-add-tag {
  padding: 4px 12px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    transform 0.3s ease,
    opacity 0.3s ease,
    width 0.3s ease,
    max-height 0.3s ease,
    filter 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.btn-add-tag:hover {
  background: var(--color-brand-solid);
  color: white;
}

.tags-container {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  min-height: 36px;
}

.search-tag {
  border: 0;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  background: var(--color-brand-solid);
  color: white;
  border-radius: var(--radius-xl);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    transform 0.3s ease,
    opacity 0.3s ease,
    width 0.3s ease,
    max-height 0.3s ease,
    filter 0.3s ease;
  user-select: none;
}

.search-tag:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.4);
}

.search-tag.active {
  background: linear-gradient(
    135deg,
    var(--color-success) 0%,
    var(--color-success) 100%
  );
}

.search-tag i {
  font-size: 11px;
}

.tag-remove {
  border: 0;
  border-radius: 50%;
  color: white;
  background: var(--color-brand-solid);
  cursor: pointer;
  opacity: 0.8;
  transition: opacity 0.2s ease;
}

.tag-remove.active {
  background: var(--color-success-solid);
}

.search-tag-control {
  display: inline-flex;
  align-items: center;
  gap: 2px;
}

.tag-remove:hover {
  opacity: 1;
}

.empty-tags-message {
  color: var(--color-faint);
  font-size: 13px;
  font-style: italic;
}

/* Table Container */
.leads-table-container {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

.table-header {
  padding: 20px 30px;
  background: var(--color-surface-soft);
  border-bottom: 2px solid var(--color-border);
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 15px;
}

.table-header h2 {
  font-size: 18px;
  color: var(--color-ink);
}

.table-header-left {
  display: flex;
  align-items: center;
  gap: 20px;
  flex: 1;
}

.table-header-right {
  display: flex;
  align-items: center;
  gap: 10px;
}

.column-settings-wrapper {
  position: relative;
}

.btn-column-settings {
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  width: 36px;
  height: 36px;
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    transform 0.3s ease,
    opacity 0.3s ease,
    width 0.3s ease,
    max-height 0.3s ease,
    filter 0.3s ease;
  font-size: 16px;
  color: var(--color-text);
}

.btn-column-settings:hover {
  border-color: var(--color-brand);
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.column-settings-dropdown {
  position: absolute;
  top: calc(100% + 5px);
  right: 0;
  background: var(--color-surface);
  border-radius: var(--radius-md);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
  min-width: 260px;
  display: none;
  z-index: 1000;
  overflow: hidden;
  animation: slideDown 0.2s ease;
}

.column-settings-dropdown.show {
  display: block;
}

.column-settings-header {
  padding: 15px 20px;
  background: var(--color-surface-soft);
  border-bottom: 2px solid var(--color-border);
  font-weight: 600;
  color: var(--color-ink);
  font-size: 14px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.column-settings-header i {
  color: var(--color-brand);
}

.column-settings-body {
  padding: 10px;
  max-height: 320px;
  overflow-y: auto;
}

.column-setting-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px;
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition:
    color 0.2s ease,
    background-color 0.2s ease,
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    transform 0.2s ease,
    opacity 0.2s ease,
    width 0.2s ease,
    max-height 0.2s ease,
    filter 0.2s ease;
}

.column-setting-item:hover {
  background: var(--color-surface-soft);
}

.column-setting-item input[type="checkbox"] {
  width: 18px;
  height: 18px;
  cursor: pointer;
  accent-color: var(--color-brand);
}

.column-setting-item label {
  flex: 1;
  font-size: 14px;
  color: var(--color-text);
  cursor: pointer;
}

/* Bulk Actions */
.bulk-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 15px;
  background: var(--color-brand-soft);
  border-radius: var(--radius-md);
  border: 2px solid var(--color-brand);
}

.bulk-actions-label {
  font-size: 13px;
  color: var(--color-brand);
  font-weight: 600;
}

.bulk-actions-count {
  background: var(--color-brand-solid);
  color: white;
  padding: 2px 8px;
  border-radius: var(--radius-lg);
  font-size: 12px;
  font-weight: 600;
}

.btn-bulk {
  padding: 6px 12px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    transform 0.3s ease,
    opacity 0.3s ease,
    width 0.3s ease,
    max-height 0.3s ease,
    filter 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.btn-bulk-assign {
  background: var(--color-success-solid);
  color: white;
}

.btn-bulk-assign:hover {
  background: var(--color-success-solid);
  transform: translateY(-1px);
}

.btn-bulk-export {
  position: relative;
}

.btn-bulk-export .btn-bulk {
  background: var(--color-brand-solid);
  color: white;
}

.btn-bulk-export .btn-bulk:hover {
  background: var(--color-brand-strong);
  transform: translateY(-1px);
}

.btn-bulk-tag {
  background: var(--color-warning-solid);
  color: white;
}

.btn-bulk-tag:hover {
  background: var(--color-warning-solid);
  transform: translateY(-1px);
}

.btn-bulk-notification {
  background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
  color: #fff;
  box-shadow: 0 2px 8px rgba(13, 148, 136, 0.35);
}

.btn-bulk-notification:hover {
  background: linear-gradient(135deg, #0f766e 0%, #115e59 100%);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(13, 148, 136, 0.4);
}

.btn-bulk-notification .btn-bulk-notification-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: var(--radius-sm);
  font-size: 11px;
}

.btn-bulk-notification .btn-bulk-notification-text {
  letter-spacing: 0.02em;
}

.export-dropdown {
  position: absolute;
  top: calc(100% + 5px);
  left: 0;
  background: var(--color-surface);
  border-radius: var(--radius-md);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  min-width: 150px;
  z-index: 1000;
  overflow: hidden;
}

.export-option {
  width: 100%;
  border: 0;
  background: transparent;
  text-align: left;
  padding: 10px 15px;
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--color-text);
  cursor: pointer;
  transition:
    color 0.2s ease,
    background-color 0.2s ease,
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    transform 0.2s ease,
    opacity 0.2s ease,
    width 0.2s ease,
    max-height 0.2s ease,
    filter 0.2s ease;
  font-size: 13px;
  font-weight: 600;
  border-bottom: 1px solid var(--color-border);
}

.export-option:last-child {
  border-bottom: none;
}

.export-option:hover {
  background: var(--color-surface-soft);
  color: var(--color-brand);
}

.export-option i {
  color: var(--color-faint);
  font-size: 14px;
}

.export-option:hover i {
  color: var(--color-brand);
}

.rows-selector {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  color: var(--color-text);
}

.rows-selector label {
  font-weight: 600;
}

.rows-selector select {
  padding: 8px 12px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  font-size: 14px;
  cursor: pointer;
  outline: none;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    transform 0.3s ease,
    opacity 0.3s ease,
    width 0.3s ease,
    max-height 0.3s ease,
    filter 0.3s ease;
}

.rows-selector select:hover,
.rows-selector select:focus {
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

/* Loading State */
.loading-state {
  text-align: center;
  padding: 60px 20px;
  color: var(--color-muted);
}

.loading-state i {
  font-size: 48px;
  margin-bottom: 20px;
  color: var(--color-brand);
}

.loading-state p {
  font-size: 16px;
}

/* Table：用 max-content 保证列宽由内容决定，避免宽屏下被压窄换行；中文表头更长时需依赖横向滚动 */
.leads-table {
  width: max-content;
  min-width: 100%;
  border-collapse: collapse;
  table-layout: auto;
}

.leads-table thead {
  background: var(--color-surface-soft);
}

.leads-table th {
  padding: 16px 20px;
  text-align: left;
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 2px solid var(--color-border);
  white-space: nowrap;
}

.leads-table th.hideable {
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    transform 0.3s ease,
    opacity 0.3s ease,
    width 0.3s ease,
    max-height 0.3s ease,
    filter 0.3s ease;
}

.leads-table th.hidden,
.leads-table td.hidden {
  display: none;
}

.leads-table th.checkbox-col,
.leads-table td.checkbox-col {
  width: 50px;
  text-align: center;
  padding: 16px 10px;
}

/* Custom Checkbox */
.custom-checkbox {
  position: relative;
  display: inline-block;
  width: 20px;
  height: 20px;
}

.custom-checkbox input[type="checkbox"] {
  position: absolute;
  opacity: 0;
  cursor: pointer;
  width: 20px;
  height: 20px;
  margin: 0;
}

.checkbox-checkmark {
  position: absolute;
  top: 0;
  left: 0;
  height: 20px;
  width: 20px;
  background-color: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: 4px;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    transform 0.3s ease,
    opacity 0.3s ease,
    width 0.3s ease,
    max-height 0.3s ease,
    filter 0.3s ease;
}

.custom-checkbox:hover .checkbox-checkmark {
  border-color: var(--color-brand);
}

.custom-checkbox input[type="checkbox"]:checked ~ .checkbox-checkmark {
  background: var(--color-brand-solid);
  border-color: var(--color-brand);
}

.checkbox-checkmark:after {
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

.custom-checkbox input[type="checkbox"]:checked ~ .checkbox-checkmark:after {
  display: block;
}

.custom-checkbox input[type="checkbox"]:indeterminate ~ .checkbox-checkmark {
  background: var(--color-brand-solid);
  border-color: var(--color-brand);
}

.custom-checkbox
  input[type="checkbox"]:indeterminate
  ~ .checkbox-checkmark:after {
  content: "";
  position: absolute;
  display: block;
  left: 3px;
  top: 8px;
  width: 10px;
  height: 2px;
  border: none;
  background: var(--color-surface);
  transform: none;
}

.leads-table tbody tr {
  border-bottom: 1px solid var(--color-border);
  transition:
    color 0.2s ease,
    background-color 0.2s ease,
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    transform 0.2s ease,
    opacity 0.2s ease,
    width 0.2s ease,
    max-height 0.2s ease,
    filter 0.2s ease;
}

.leads-table tbody tr:hover {
  background: var(--color-surface-soft);
}

.leads-table tbody tr.expanded {
  background: var(--color-brand-soft);
}

.leads-table td {
  padding: 16px 20px;
  font-size: 14px;
  color: var(--color-text);
  white-space: nowrap;
  vertical-align: middle;
}

/* 标签列允许换行；避免 td 的 nowrap 阻止 flex 内标签折行 */
.leads-table td:has(.lead-tags) {
  white-space: normal;
  vertical-align: top;
}

/* Action 列固定（参考 IB List） */
.leads-table__th--sticky {
  position: sticky;
  right: 0;
  z-index: 2;
  background: var(--color-surface-soft);
  border-left: 1px solid var(--color-border);
  box-shadow: inset 12px 0 20px -10px rgba(0, 0, 0, 0.06);
}

.leads-table__cell--sticky {
  position: sticky;
  right: 0;
  z-index: 1;
  background: var(--color-surface);
  border-left: 1px solid var(--color-border);
  box-shadow: inset 12px 0 20px -10px rgba(0, 0, 0, 0.06);
  white-space: nowrap;
}

.leads-table tbody tr:hover .leads-table__cell--sticky {
  background: var(--color-surface-soft);
}

.leads-table tbody tr.expanded .leads-table__cell--sticky {
  background: var(--color-brand-soft);
}

/* Client Info */
.client-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.client-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: var(--color-brand-solid);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: 600;
  font-size: 14px;
  flex-shrink: 0;
}

.client-details {
  display: flex;
  flex-direction: column;
}

.client-name {
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 2px;
  white-space: nowrap;
}

/* 名字可点跳客户详情：仅有权限时渲染成链接 */
.client-name--link {
  display: inline-block;
  text-decoration: none;
  cursor: pointer;
}

.client-name--link:hover {
  color: var(--color-brand);
  text-decoration: underline;
}

.client-email {
  font-size: 13px;
  color: var(--color-muted);
  white-space: nowrap;
}

/* Lead ID */
.lead-id {
  font-family: "Courier New", monospace;
  font-size: 13px;
  font-weight: 600;
  color: var(--color-brand);
  background: var(--color-brand-soft);
  padding: 4px 8px;
  border-radius: var(--radius-sm);
  white-space: nowrap;
}

/* Status Badge */
.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: var(--radius-lg);
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
}

.status-badge.new {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.status-badge.active {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.status-badge.contacted {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.status-badge.converted {
  background: var(--color-success-soft);
  color: var(--color-success);
}

/* KYC Status Badge */
.kyc-status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: var(--radius-lg);
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  white-space: nowrap;
}

.kyc-status-badge.not-started {
  background: var(--color-border);
  color: var(--color-text);
}

.kyc-status-badge.in-progress {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.kyc-status-badge.pending-review {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.kyc-status-badge.approved {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.kyc-status-badge.rejected {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

/* Lead Tags */
.lead-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  min-width: 120px;
}

.lead-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 8px 3px 8px;
  background: var(--color-warning-soft);
  color: var(--color-warning);
  border-radius: var(--radius-lg);
  font-size: 11px;
  font-weight: 600;
  white-space: nowrap;
  position: relative;
  padding-right: 20px;
}

.lead-tag i {
  font-size: 9px;
}

.tag-remove-btn {
  position: absolute;
  right: 2px;
  top: 50%;
  transform: translateY(-50%);
  background: rgba(146, 64, 14, 0.15);
  border: none;
  border-radius: 50%;
  width: 16px;
  height: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 14px;
  line-height: 1;
  color: var(--color-warning);
  padding: 0;
  transition:
    color 0.2s ease,
    background-color 0.2s ease,
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    transform 0.2s ease,
    opacity 0.2s ease,
    width 0.2s ease,
    max-height 0.2s ease,
    filter 0.2s ease;
  opacity: 0.6;
}

.tag-remove-btn:hover {
  opacity: 1;
  background: var(--color-danger-solid);
  color: white;
  transform: translateY(-50%) scale(1.1);
}

.tag-remove-btn:active {
  transform: translateY(-50%) scale(0.95);
}

.no-tags {
  color: var(--color-faint);
  font-size: 12px;
  font-style: italic;
}

/* Action Button */
.btn-action {
  padding: 8px 16px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    transform 0.3s ease,
    opacity 0.3s ease,
    width 0.3s ease,
    max-height 0.3s ease,
    filter 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.btn-icon-only {
  justify-content: center;
  width: 34px;
  height: 34px;
  padding: 0;
}

.lead-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

/* 颜色/阴影区分语义；尺寸由 .btn-icon-only 统一管 */
.btn-notification {
  background: linear-gradient(
    135deg,
    var(--color-warning) 0%,
    var(--color-warning) 100%
  );
  color: #fff;
  box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
}

.btn-notification:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
}

.btn-detail {
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.btn-detail:hover {
  background: var(--color-brand-solid);
  color: white;
}

.btn-client-page {
  background: var(--color-surface-muted);
  color: var(--color-ink);
  text-decoration: none;
}

.btn-client-page:hover {
  background: var(--color-border);
  transform: translateY(-2px);
}

.btn-approve-kyc {
  background: linear-gradient(
    135deg,
    var(--color-success) 0%,
    var(--color-success) 100%
  );
  color: #fff;
  padding: 8px 14px;
  box-shadow: 0 2px 8px rgba(56, 161, 105, 0.28);
}

.btn-approve-kyc:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(56, 161, 105, 0.36);
}

.btn-view-as-client {
  background: linear-gradient(
    135deg,
    var(--color-info-soft) 0%,
    var(--color-info-border) 100%
  );
  color: var(--color-info);
  box-shadow: 0 1px 3px rgba(6, 182, 212, 0.15);
}

.btn-view-as-client:hover {
  background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
  color: #fff;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(14, 165, 233, 0.35);
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 60px 20px !important;
  color: var(--color-faint);
}

.empty-state i {
  font-size: 48px;
  margin-bottom: 15px;
  display: block;
}

.empty-state p {
  font-size: 16px;
  font-style: italic;
}

/* Pagination */
.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 10px;
  padding: 20px;
  border-top: 2px solid var(--color-border);
}

.pagination-btn {
  padding: 8px 16px;
  border: 2px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text);
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    transform 0.3s ease,
    opacity 0.3s ease,
    width 0.3s ease,
    max-height 0.3s ease,
    filter 0.3s ease;
  display: flex;
  align-items: center;
  gap: 6px;
}

.pagination-btn:hover:not(:disabled) {
  border-color: var(--color-brand);
  color: var(--color-brand);
  background: var(--color-brand-soft);
}

.pagination-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.pagination-pages {
  display: flex;
  gap: 5px;
}

.pagination-page {
  width: 36px;
  height: 36px;
  border: 2px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text);
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    transform 0.3s ease,
    opacity 0.3s ease,
    width 0.3s ease,
    max-height 0.3s ease,
    filter 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
}

.pagination-page:hover {
  border-color: var(--color-brand);
  color: var(--color-brand);
  background: var(--color-brand-soft);
}

.pagination-page.active {
  background: var(--color-brand-solid);
  color: white;
  border-color: var(--color-brand);
}

/* Table Scroll Wrapper：始终 auto，勿在「无溢出」时改为 visible，否则宽屏下列被压窄、中文易换行 */
.table-scroll-wrapper {
  --detail-panel-width: 100%;
  overflow-x: auto;
  overflow-y: visible;
  position: relative;
  scroll-behavior: smooth;
  border-radius: 0 0 12px 12px;
  -webkit-overflow-scrolling: touch;
}

.table-scroll-wrapper::-webkit-scrollbar {
  height: 12px;
}

.table-scroll-wrapper::-webkit-scrollbar-track {
  background: var(--color-surface-soft);
  border-radius: var(--radius-sm);
  margin: 0 10px;
}

.table-scroll-wrapper::-webkit-scrollbar-thumb {
  background: var(--color-brand-solid);
  border-radius: var(--radius-sm);
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    transform 0.3s ease,
    opacity 0.3s ease,
    width 0.3s ease,
    max-height 0.3s ease,
    filter 0.3s ease;
  border: 2px solid #f1f5f9;
}

.table-scroll-wrapper::-webkit-scrollbar-thumb:hover {
  background: linear-gradient(
    135deg,
    var(--color-brand-strong) 0%,
    var(--color-brand-strong) 100%
  );
  border-color: var(--color-border);
}

/* 滚动阴影指示器 */
.table-scroll-wrapper::before,
.table-scroll-wrapper::after {
  content: "";
  position: absolute;
  top: 0;
  bottom: 12px;
  width: 30px;
  pointer-events: none;
  z-index: 10;
  opacity: 0;
  transition: opacity 0.3s ease;
}

.table-scroll-wrapper::before {
  left: 0;
  background: linear-gradient(to right, rgba(255, 255, 255, 0.95), transparent);
}

.table-scroll-wrapper::after {
  right: 0;
  background: linear-gradient(to left, rgba(255, 255, 255, 0.95), transparent);
}

.table-scroll-wrapper.has-scroll-left::before {
  opacity: 1;
}

.table-scroll-wrapper.has-scroll-right::after {
  opacity: 1;
}

.table-scroll-wrapper:has(.detail-row.show)::before,
.table-scroll-wrapper:has(.detail-row.show)::after {
  opacity: 0;
}

/* 滚动提示：置于表格下方，右对齐，不遮挡操作列 */
.scroll-hint {
  display: none;
  margin: 12px 20px 0 auto;
  width: fit-content;
  background: var(--color-brand-solid);
  color: white;
  padding: 8px 16px;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 600;
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.3);
  animation: bounceRight 2s infinite;
  pointer-events: none;
  white-space: nowrap;
}

.scroll-hint i {
  margin-left: 8px;
  animation: slideRight 1s infinite;
}

.table-scroll-wrapper.show-scroll-hint + .scroll-hint {
  display: block;
}

@keyframes bounceRight {
  0%,
  20%,
  50%,
  80%,
  100% {
    transform: translateX(0);
  }
  40% {
    transform: translateX(-5px);
  }
  60% {
    transform: translateX(5px);
  }
}

@keyframes slideRight {
  0% {
    transform: translateX(0);
  }
  50% {
    transform: translateX(5px);
  }
  100% {
    transform: translateX(0);
  }
}

@media (max-width: 768px) {
  .search-filter-container {
    flex-direction: column;
  }

  .search-input-wrapper,
  .tags-section {
    min-width: 100%;
  }

  .table-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .stats-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 15px;
  }

  .page-stats {
    flex-direction: column;
    width: 100%;
  }
}
</style>
