<template>
  <div class="clients-list-page ui-page workspace-list-page">
    <!-- 页面头部 -->
    <div class="page-header ui-page-header">
      <div class="page-title ui-page-heading">
        <h1 class="ui-page-title">{{ t("page_clientsList_title") }}</h1>
        <p class="ui-page-description">{{ t("page_clientsList_sub") }}</p>
      </div>
      <div class="page-actions ui-page-actions">
        <PageHeaderActions />
      </div>
    </div>

    <!-- 统计信息 -->
    <div class="stats-header ui-surface ui-summary-band">
      <div class="stats-info">
        <h2>{{ t("clientsList_stats_heading") }}</h2>
        <p>{{ t("clientsList_stats_sub") }}</p>
      </div>
      <div class="page-stats">
        <div class="stat-badge stat-total">
          <i class="fas fa-users"></i>
          <span>{{
            tParams("clientsList_stat_total", "{n} Total Clients", {
              n: formatNumber(statistics.totalClients || 0),
            })
          }}</span>
        </div>
        <div class="stat-badge stat-active">
          <i class="fas fa-user-check"></i>
          <span>{{
            tParams("clientsList_stat_active", "{n} Active", {
              n: formatNumber(statistics.activeClients || 0),
            })
          }}</span>
        </div>
        <div class="stat-badge stat-pending">
          <i class="fas fa-clock"></i>
          <span>{{
            tParams("clientsList_stat_pendingKyc", "{n} Pending KYC", {
              n: formatNumber(statistics.pendingKyc || 0),
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

    <!-- 客户表格容器 -->
    <div class="clients-table-container ui-data-region">
      <!-- 表格头部 -->
      <div class="table-header ui-toolbar">
        <div class="table-header-left">
          <h2>{{ t("clientsList_verifiedHeading") }}</h2>

          <!-- 批量操作 -->
          <div
            class="bulk-actions"
            :class="{ show: selectedClients.length > 0 }"
          >
            <span class="bulk-actions-label">{{
              t("leads_bulkSelected")
            }}</span>
            <span class="bulk-actions-count">{{ selectedClients.length }}</span>
            <button
              type="button"
              v-if="hasAssignPermission"
              class="btn-bulk btn-bulk-assign"
              @click="openBulkAssignModal"
            >
              <i class="fas fa-user-plus"></i> {{ t("leads_bulkAssign") }}
            </button>
            <button
              type="button"
              v-if="hasTagsPermission"
              class="btn-bulk btn-bulk-tag"
              @click="openBulkTagModal"
            >
              <i class="fas fa-tags"></i> {{ t("leads_bulkAddTag") }}
            </button>
            <div
              v-if="hasExportPermission"
              class="btn-bulk-export"
              style="position: relative"
            >
              <button
                type="button"
                class="btn-bulk"
                @click="toggleExportDropdown"
              >
                <i class="fas fa-download"></i> {{ t("leads_bulkExport") }}
              </button>
              <div
                class="export-dropdown"
                :class="{ show: showExportDropdown }"
              >
                <button
                  type="button"
                  class="export-option csv"
                  @click="exportData('csv')"
                >
                  <i class="fas fa-file-csv"></i> {{ t("leads_exportCsv") }}
                </button>
                <button
                  type="button"
                  class="export-option excel"
                  @click="exportData('excel')"
                >
                  <i class="fas fa-file-excel"></i> {{ t("leads_exportExcel") }}
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
            <select v-model="perPage" @change="loadClients">
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
          <div style="position: relative">
            <button
              type="button"
              class="btn-column-settings"
              aria-label="Configure table columns"
              @click="toggleColumnSettings"
            >
              <i class="fas fa-cog"></i>
            </button>

            <!-- 列设置下拉菜单 -->
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
                    :id="'col-' + column.key"
                    :checked="visibleColumns.includes(column.key)"
                    @change="toggleColumn(column.key)"
                  />
                  <label :for="'col-' + column.key">{{ column.label }}</label>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 表格滚动容器 -->
      <div class="table-scroll-outer">
        <div
          ref="tableScrollWrapperRef"
          class="table-scroll-wrapper ui-table-scroll"
          :class="scrollClasses"
        >
          <table class="clients-table">
            <thead>
              <tr>
                <th class="checkbox-col">
                  <label class="custom-checkbox">
                    <input
                      type="checkbox"
                      v-model="selectAll"
                      @change="toggleSelectAll"
                    />
                    <span class="checkbox-checkmark"></span>
                  </label>
                </th>
                <th
                  class="hideable sortable"
                  :class="{ hidden: !visibleColumns.includes('id') }"
                  @click="sortBy('id')"
                >
                  {{ t("clientsList_col_id") }}
                  <div class="sort-icon">
                    <i class="fas fa-caret-up"></i>
                    <i class="fas fa-caret-down"></i>
                  </div>
                </th>
                <th
                  class="hideable sortable"
                  :class="{ hidden: !visibleColumns.includes('client') }"
                  @click="sortBy('firstName')"
                >
                  {{ t("leads_col_client") }}
                  <div class="sort-icon">
                    <i class="fas fa-caret-up"></i>
                    <i class="fas fa-caret-down"></i>
                  </div>
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
                  :class="{ hidden: !visibleColumns.includes('tags') }"
                >
                  {{ t("leads_col_tags") }}
                </th>
                <th
                  class="hideable sortable"
                  :class="{ hidden: !visibleColumns.includes('kycStatus') }"
                  @click="sortBy('kycStatus')"
                >
                  {{ t("leads_col_kycStatus") }}
                  <div class="sort-icon">
                    <i class="fas fa-caret-up"></i>
                    <i class="fas fa-caret-down"></i>
                  </div>
                </th>
                <th
                  class="hideable"
                  :class="{
                    hidden: !visibleColumns.includes('depositCurrency'),
                  }"
                >
                  {{ t("clientsList_col_depositCurrency") }}
                </th>
                <th
                  class="hideable sortable"
                  :class="{
                    hidden: !visibleColumns.includes('firstLoginDate'),
                  }"
                  @click="sortBy('firstLoginDate')"
                >
                  {{ t("leads_col_firstLoginDate") }}
                  <div class="sort-icon">
                    <i class="fas fa-caret-up"></i>
                    <i class="fas fa-caret-down"></i>
                  </div>
                </th>
                <th
                  class="hideable sortable"
                  :class="{ hidden: !visibleColumns.includes('lastLoginDate') }"
                  @click="sortBy('lastLoginDate')"
                >
                  {{ t("leads_col_lastLoginDate") }}
                  <div class="sort-icon">
                    <i class="fas fa-caret-up"></i>
                    <i class="fas fa-caret-down"></i>
                  </div>
                </th>
                <th
                  class="hideable"
                  :class="{ hidden: !visibleColumns.includes('hasLoggedIn') }"
                >
                  {{ t("clientsList_col_hasLoggedIn") }}
                </th>
                <th class="clients-table__th--sticky">
                  {{ t("leads_col_action") }}
                </th>
              </tr>
            </thead>
            <tbody>
              <template v-for="client in clients" :key="client.id">
                <!-- 主行 -->
                <tr :class="{ expanded: expandedClientId === client.id }">
                  <td class="checkbox-col">
                    <label class="custom-checkbox">
                      <input
                        type="checkbox"
                        :value="client.id"
                        v-model="selectedClients"
                      />
                      <span class="checkbox-checkmark"></span>
                    </label>
                  </td>
                  <td
                    class="hideable client-id"
                    :class="{ hidden: !visibleColumns.includes('id') }"
                  >
                    #{{ client.id }}
                  </td>
                  <td
                    class="hideable client-info-cell"
                    :class="{ hidden: !visibleColumns.includes('client') }"
                  >
                    <div class="client-info">
                      <div class="client-avatar">
                        {{ getClientInitials(client) }}
                      </div>
                      <div class="client-details">
                        <router-link
                          v-if="canViewClientDetail"
                          class="client-name client-name--link"
                          :to="{
                            name: 'client-detail',
                            query: { id: client.id },
                          }"
                          >{{ getFullName(client) }}</router-link
                        >
                        <div v-else class="client-name">
                          {{ getFullName(client) }}
                        </div>
                        <div class="client-email">{{ client.email }}</div>
                      </div>
                    </div>
                  </td>
                  <td
                    class="hideable"
                    :class="{ hidden: !visibleColumns.includes('phone') }"
                  >
                    {{ client.phone || "-" }}
                  </td>
                  <td
                    class="hideable"
                    :class="{ hidden: !visibleColumns.includes('country') }"
                  >
                    {{ client.country || "-" }}
                  </td>
                  <td
                    class="hideable"
                    :class="{ hidden: !visibleColumns.includes('manager') }"
                  >
                    {{ client.manager || "-" }}
                  </td>
                  <td
                    class="hideable"
                    :class="{ hidden: !visibleColumns.includes('tags') }"
                  >
                    <div
                      class="client-tags"
                      v-if="client.tags && client.tags.length > 0"
                    >
                      <span
                        v-for="tag in client.tags"
                        :key="tag.id"
                        class="client-tag"
                      >
                        <i class="fas fa-tag"></i> {{ tag.tagName || tag.name }}
                        <button
                          type="button"
                          v-if="hasTagsPermission"
                          class="tag-remove-btn"
                          @click.stop="
                            removeClientTag(
                              client.id,
                              tag.id,
                              tag.tagName || tag.name,
                            )
                          "
                          :title="
                            tParams(
                              'leads_removeTagTitle',
                              'Remove {name} tag',
                              { name: tag.tagName || tag.name },
                            )
                          "
                          :aria-label="
                            tParams(
                              'leads_removeTagTitle',
                              'Remove {name} tag',
                              { name: tag.tagName || tag.name },
                            )
                          "
                        >
                          ×
                        </button>
                      </span>
                    </div>
                    <span v-else class="no-tags">{{ t("leads_noTags") }}</span>
                  </td>
                  <td
                    class="hideable"
                    :class="{ hidden: !visibleColumns.includes('kycStatus') }"
                  >
                    <span
                      class="kyc-badge"
                      :class="getKycStatusClass(client.kycStatus)"
                    >
                      {{ formatKycStatus(client.kycStatus) }}
                    </span>
                  </td>
                  <td
                    class="hideable"
                    :class="{
                      hidden: !visibleColumns.includes('depositCurrency'),
                    }"
                  >
                    {{ client.depositCurrency || "USD" }}
                  </td>
                  <td
                    class="hideable"
                    :class="{
                      hidden: !visibleColumns.includes('firstLoginDate'),
                    }"
                  >
                    {{ formatDate(client.firstLoginAt || client.createdAt) }}
                  </td>
                  <td
                    class="hideable"
                    :class="{
                      hidden: !visibleColumns.includes('lastLoginDate'),
                    }"
                  >
                    {{ formatDate(client.lastLoginAt) }}
                  </td>
                  <td
                    class="hideable"
                    :class="{ hidden: !visibleColumns.includes('hasLoggedIn') }"
                  >
                    <div
                      class="login-status"
                      :class="client.lastLoginAt ? 'logged-in' : 'not-logged'"
                    >
                      <i
                        :class="
                          client.lastLoginAt
                            ? 'fas fa-check-circle'
                            : 'fas fa-times-circle'
                        "
                      ></i>
                      {{
                        client.lastLoginAt
                          ? t("leadDetail_yes")
                          : t("leadDetail_no")
                      }}
                    </div>
                  </td>
                  <td class="clients-table__cell--sticky">
                    <div class="action-buttons">
                      <button
                        type="button"
                        class="btn-action btn-detail btn-icon-only"
                        @click="toggleRowExpansion(client.id)"
                        :title="
                          expandedClientId === client.id
                            ? t('leads_btnHide')
                            : t('leads_btnDetail')
                        "
                        :aria-label="
                          expandedClientId === client.id
                            ? t('leads_btnHide')
                            : t('leads_btnDetail')
                        "
                      >
                        <i
                          :class="[
                            'fas',
                            expandedClientId === client.id
                              ? 'fa-chevron-up'
                              : 'fa-chevron-down',
                          ]"
                        ></i>
                      </button>
                      <router-link
                        v-if="canViewClientDetailProfile"
                        class="btn-action btn-client-page btn-icon-only"
                        :to="{
                          name: 'client-detail',
                          query: {
                            id: client.id,
                            source: clientsListLogSubModule,
                          },
                        }"
                        :title="t('leads_btnDetail')"
                        :aria-label="t('leads_btnDetail')"
                      >
                        <i class="fas fa-address-card"></i>
                      </router-link>
                      <button
                        type="button"
                        v-if="hasViewAsClientPermission"
                        class="btn-action btn-view-as-client btn-icon-only"
                        @click.stop="handleViewAsClient(client)"
                        :title="t('leads_titleViewAsClient')"
                        :aria-label="t('leads_titleViewAsClient')"
                      >
                        <i class="fas fa-user"></i>
                      </button>
                      <button
                        type="button"
                        v-if="hasNotificationsPermission"
                        class="btn-action btn-notification btn-icon-only"
                        @click.stop="sendNotification(client)"
                        :title="t('leads_titleSendNotification')"
                        :aria-label="t('leads_titleSendNotification')"
                      >
                        <i class="fas fa-bell"></i>
                      </button>
                    </div>
                  </td>
                </tr>

                <!-- 详情行 -->
                <tr
                  class="detail-row"
                  :class="{ show: expandedClientId === client.id }"
                >
                  <td :colspan="visibleColumns.length + 2">
                    <div
                      class="detail-content"
                      v-if="expandedClientId === client.id"
                    >
                      <ClientDetailRow
                        :client="client"
                        :can-edit="hasEditPermission"
                        :log-sub-module-key="clientsListLogSubModule"
                        @update="handleClientUpdate"
                        @delete="handleClientDelete"
                      />
                    </div>
                  </td>
                </tr>
              </template>

              <tr v-if="clients.length === 0">
                <td colspan="20" class="empty-state">
                  <i class="fas fa-users"></i>
                  <p>{{ t("clientsList_empty") }}</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- 滚动提示：置于表格下方，右对齐，不遮挡操作列 -->
        <div class="scroll-hint">
          {{ t("leads_scrollHint") }} <i class="fas fa-arrow-right"></i>
        </div>
      </div>

      <!-- 分页 -->
      <div class="table-pagination" v-if="totalPages > 1">
        <button
          type="button"
          class="btn-page"
          :disabled="currentPage === 1"
          :aria-label="t('leads_paginationPrev')"
          @click="changePage(currentPage - 1)"
        >
          <i class="fas fa-chevron-left"></i>
        </button>

        <span class="page-info">
          {{
            tParams("clientsList_pageOf", "Page {current} of {total}", {
              current: currentPage,
              total: totalPages,
            })
          }}
        </span>

        <button
          type="button"
          class="btn-page"
          :disabled="currentPage === totalPages"
          :aria-label="t('leads_paginationNext')"
          @click="changePage(currentPage + 1)"
        >
          <i class="fas fa-chevron-right"></i>
        </button>
      </div>
    </div>

    <!-- Search Tag Modal -->
    <SearchTagModal
      v-model="showSearchTagModal"
      @confirm="handleCreateSearchTag"
    />

    <!-- 批量分配模态框 -->
    <BulkAssignmentModal
      v-model="showBulkAssignModal"
      :selected-items="selectedClientsData"
      context="client"
      @confirm="handleBulkAssign"
    />

    <!-- 批量标签模态框 -->
    <BulkTagModal
      v-model="showBulkTagModal"
      :selected-leads="selectedClientsData"
      :available-tags="leadTags"
      @confirm="handleBulkAddTags"
    />

    <!-- 发送通知模态框 -->
    <SendNotificationModal
      v-if="
        selectedClientForNotification ||
        (bulkNotificationRecipients && bulkNotificationRecipients.length)
      "
      v-model="showSendNotificationModal"
      :recipient="
        selectedClientForNotification ||
        (bulkNotificationRecipients && bulkNotificationRecipients[0])
      "
      :recipients="bulkNotificationRecipients"
      section-key="client_list"
      :log-sub-module-key="clientsListLogSubModule"
      @send="handleSendNotification"
    />

    <AddClientModal
      v-model="showAddClientModal"
      :log-sub-module-key="clientsListLogSubModule"
      :country-options="countryOptions"
      :currency-options="currencyOptions"
      :manager-options="managerOptions"
      :kyc-templates="kycTemplates"
      :loading-options="loadingCreateOptions"
      @success="handleAddClientSuccess"
    />
  </div>
</template>

<script>
import { ref, onMounted, onUnmounted, computed, nextTick, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import ClientDetailRow from "@/components/clients/ClientDetailRow.vue";
import SearchTagModal from "@/components/leads/SearchTagModal.vue";
import BulkAssignmentModal from "@/components/leads/BulkAssignmentModal.vue";
import BulkTagModal from "@/components/leads/BulkTagModal.vue";
import SendNotificationModal from "@/components/client/SendNotificationModal.vue";
import AddClientModal from "@/components/client/AddClientModal.vue";
import { clientService } from "@/services/clientListService";
import leadsService from "@/services/leadsService";
import { formatNumber } from "@/utils/helpers";
import { CLIENT_DETAIL_PERMISSION_KEYS } from "@/utils/constants";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { translateApiErrorMessage } from "@/i18n/adminI18nBridge";
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";
import { recordOperationLog } from "@/services/operationLogReportApi";
import {
  buildExportLogPayload,
  getSubModuleKey,
} from "@/config/operationLogPages";

export default {
  name: "ClientsList",
  components: {
    PageHeaderActions,
    ClientDetailRow,
    SearchTagModal,
    BulkAssignmentModal,
    BulkTagModal,
    SendNotificationModal,
    AddClientModal,
  },
  setup() {
    const { t, tParams, languageStore } = useAdminI18n();
    const route = useRoute();
    const router = useRouter();
    const authStore = useAuthStore();
    const clientsListLogSubModule = getSubModuleKey("page_clients_list");

    // Permission checks for Clients List page
    const hasCreatePermission = computed(() =>
      authStore.hasPermission("page_clientslist_create"),
    );
    const hasEditPermission = computed(() =>
      authStore.hasPermission("page_clientslist_edit"),
    );
    const hasTagsPermission = computed(() =>
      authStore.hasPermission("page_clientslist_tags"),
    );
    const hasNotificationsPermission = computed(() =>
      authStore.hasPermission("page_clientslist_notifications"),
    );
    const hasAssignPermission = computed(() =>
      authStore.hasPermission("page_clientslist_assign"),
    );
    const hasExportPermission = computed(() =>
      authStore.hasPermission("page_clientslist_export"),
    );
    const hasViewAsClientPermission = computed(() =>
      authStore.hasPermission("page_clientslist_view_as_client"),
    );
    // 能否进客户详情页：与路由守卫口径一致，8 个详情权限任一即可
    const canViewClientDetail = computed(() =>
      CLIENT_DETAIL_PERMISSION_KEYS.some((key) => authStore.hasPermission(key)),
    );
    const canViewClientDetailProfile = computed(() =>
      authStore.hasPermission("page_clientsdetail_profile"),
    );

    // 响应式数据
    const clients = ref([]);
    const statistics = ref({
      totalClients: 0,
      activeClients: 0,
      pendingKyc: 0,
    });
    const loading = ref(false);
    const selectedClients = ref([]);
    const expandedClientId = ref(null);
    const showExportDropdown = ref(false);
    const showBulkAssignModal = ref(false);
    const showBulkTagModal = ref(false);
    const showColumnSettings = ref(false);
    const showSearchTagModal = ref(false);
    const showSendNotificationModal = ref(false);
    const selectedClientForNotification = ref(null);
    const bulkNotificationRecipients = ref(null);
    const showAddClientModal = ref(false);
    const tableScrollWrapperRef = ref(null);

    const countryOptions = ref([]);
    const currencyOptions = ref([]);
    const managerOptions = ref([]);
    const kycTemplates = ref([]);
    const createOptionsLoaded = ref(false);
    const loadingCreateOptions = ref(false);

    // 搜索相关
    const searchTerm = ref("");
    const activeSearchTag = ref(null);
    const searchTags = ref([]);
    const searchTimeout = ref(null);

    // 分页和排序
    const currentPage = ref(1);
    const perPage = ref(10);
    const totalItems = ref(0);
    const totalPagesFromApi = ref(null); // 存储接口返回的 total_pages
    const sortField = ref("createdAt");
    const sortDirection = ref("desc");

    // 列显示设置（随语言切换更新标签）
    const availableColumns = computed(() => [
      { key: "id", label: t("clientsList_col_id") },
      { key: "client", label: t("leads_col_client") },
      { key: "phone", label: t("leads_col_phone") },
      { key: "country", label: t("leads_col_country") },
      { key: "manager", label: t("leads_col_manager") },
      { key: "tags", label: t("leads_col_tags") },
      { key: "kycStatus", label: t("leads_col_kycStatus") },
      { key: "depositCurrency", label: t("clientsList_col_depositCurrency") },
      { key: "firstLoginDate", label: t("leads_col_firstLoginDate") },
      { key: "lastLoginDate", label: t("leads_col_lastLoginDate") },
      { key: "hasLoggedIn", label: t("clientsList_col_hasLoggedIn") },
    ]);

    const defaultClientVisibleColumns = [
      "id",
      "client",
      "phone",
      "country",
      "manager",
      "tags",
      "kycStatus",
      "depositCurrency",
      "firstLoginDate",
      "lastLoginDate",
      "hasLoggedIn",
    ];
    const visibleColumns = ref([...defaultClientVisibleColumns]);

    // 滚动状态
    const scrollClasses = ref({
      "has-overflow": false,
      "has-scroll-left": false,
      "has-scroll-right": false,
      "show-scroll-hint": false,
    });

    // 计算属性 - 优先使用接口返回的 pagination.total_pages
    const totalPages = computed(() => {
      // 优先使用接口返回的 pagination.total_pages
      if (totalPagesFromApi.value !== null) {
        return totalPagesFromApi.value;
      }
      // 如果没有，则根据 totalItems 和 perPage 计算
      return Math.ceil(totalItems.value / perPage.value);
    });
    const selectAll = computed({
      get: () =>
        selectedClients.value.length === clients.value.length &&
        clients.value.length > 0,
      set: (value) => {
        if (value) {
          selectedClients.value = clients.value.map((c) => c.id);
        } else {
          selectedClients.value = [];
        }
      },
    });

    const selectedClientsData = computed(() => {
      return clients.value
        .filter((c) => selectedClients.value.includes(c.id))
        .map((c) => ({
          id: c.id,
          leadId: c.id, // BulkTagModal期望leadId字段
          firstName: c.firstName,
          lastName: c.lastName,
          email: c.email,
        }));
    });

    // 加载新增客户所需选项（Account Manager 下拉使用可分配 Sales 列表）
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
        const data = error?.response?.data ?? error;
        const rawMsg =
          data?.message || error?.message || t("common_unknownError");
        const message = translateApiErrorMessage(data?.errorCode, rawMsg);
        alert(
          tParams(
            "clientsList_alert_loadCreateOptions",
            "Failed to load client creation options: {msg}",
            { msg: message },
          ),
        );
      } finally {
        loadingCreateOptions.value = false;
      }
    };

    // 加载客户列表
    const loadClients = async () => {
      try {
        loading.value = true;
        const params = {
          page: currentPage.value,
          per_page: perPage.value,
          sort_field: sortField.value,
          sort_direction: sortDirection.value,
          kyc_status: "approved", // 只显示KYC已通过的客户
        };

        // 添加搜索参数
        if (searchTerm.value) {
          params.search = searchTerm.value;
        }

        const response = await clientService.getList(params);

        clients.value = response.data.items;
        // 使用接口返回的 pagination 数据
        if (response.data.pagination) {
          totalItems.value = response.data.pagination.total;
          currentPage.value = response.data.pagination.page;
          perPage.value = response.data.pagination.per_page || perPage.value;
          totalPagesFromApi.value =
            response.data.pagination.total_pages || null;
        } else {
          // 兼容旧格式
          totalItems.value = response.data.total || 0;
          currentPage.value = response.data.page || 1;
          totalPagesFromApi.value = null;
        }

        scheduleTableScrollRecalc();
      } catch (error) {
        console.error("Failed to load clients:", error);
        const data = error?.response?.data ?? error;
        const rawMsg =
          data?.message || error?.message || t("common_unknownError");
        const message = translateApiErrorMessage(data?.errorCode, rawMsg);
        alert(
          tParams(
            "clientsList_alert_loadClientsFailed",
            "Failed to load clients: {msg}",
            { msg: message },
          ),
        );
      } finally {
        loading.value = false;
        scheduleTableScrollRecalc();
      }
    };

    // 加载统计信息
    const loadStatistics = async () => {
      try {
        const response = await clientService.getStatistics();
        const statsData = response?.data ?? response ?? {};
        statistics.value = {
          totalClients: statsData.totalClients ?? 0,
          activeClients: statsData.activeClients ?? 0,
          pendingKyc: statsData.pendingKyc ?? 0,
        };
      } catch (error) {
        console.error("Failed to load statistics:", error);
      }
    };

    // 加载搜索标签
    const loadSearchTags = async () => {
      try {
        const response = await leadsService.getSearchTags();
        if (response.success) {
          searchTags.value = response.data;
        }
      } catch (error) {
        console.error("Failed to load search tags:", error);
      }
    };

    // 搜索处理
    const handleSearch = () => {
      // Debounce search
      if (searchTimeout.value) {
        clearTimeout(searchTimeout.value);
      }

      searchTimeout.value = setTimeout(async () => {
        currentPage.value = 1;
        await loadClients();
      }, 300);
    };

    const clearSearch = () => {
      searchTerm.value = "";
      activeSearchTag.value = null;
      currentPage.value = 1;
      loadClients();
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

      currentPage.value = 1;
      await loadClients();
    };

    const removeSearchTag = async (tagId) => {
      if (confirm(t("leads_confirm_removeSearchTag"))) {
        try {
          await leadsService.deleteSearchTag(tagId, clientsListLogSubModule);
          if (activeSearchTag.value === tagId) {
            clearSearch();
          }
          await loadSearchTags();
          alert(t("leads_alert_searchTagRemoved"));
        } catch (error) {
          const data = error?.response?.data ?? error;
          const rawMsg =
            data?.message || error?.message || t("common_unknownError");
          const msg = translateApiErrorMessage(data?.errorCode, rawMsg);
          alert(
            tParams(
              "leads_alert_searchTagRemoveFailed",
              "Failed to remove search tag: {msg}",
              { msg },
            ),
          );
        }
      }
    };

    const handleCreateSearchTag = async (data) => {
      try {
        await leadsService.createSearchTag({
          ...data,
          logSubModuleKey: clientsListLogSubModule,
        });
        await loadSearchTags();
        alert(t("leads_alert_searchTagCreated"));
      } catch (error) {
        const data = error?.response?.data ?? error;
        const rawMsg =
          data?.message || error?.message || t("common_unknownError");
        const msg = translateApiErrorMessage(data?.errorCode, rawMsg);
        alert(
          tParams(
            "leads_alert_searchTagCreateFailed",
            "Failed to create search tag: {msg}",
            { msg },
          ),
        );
      }
    };

    // 工具函数
    const getFullName = (client) => {
      const firstName = client.firstName || "";
      const lastName = client.lastName || "";
      return `${firstName} ${lastName}`.trim() || client.email;
    };

    const getClientInitials = (client) => {
      const firstInitial = (client.firstName || "").charAt(0);
      const lastInitial = (client.lastName || "").charAt(0);
      const initials = `${firstInitial}${lastInitial}`.trim();
      if (initials) {
        return initials.toUpperCase();
      }
      if (client.email) {
        return client.email.charAt(0).toUpperCase();
      }
      return "?";
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

    // 排序功能
    const sortBy = (field) => {
      if (sortField.value === field) {
        sortDirection.value = sortDirection.value === "asc" ? "desc" : "asc";
      } else {
        sortField.value = field;
        sortDirection.value = "asc";
      }
      loadClients();
    };

    // 行展开/收起
    const toggleRowExpansion = (clientId) => {
      expandedClientId.value =
        expandedClientId.value === clientId ? null : clientId;
      scheduleTableScrollRecalc();
    };

    // 全选切换
    const toggleSelectAll = () => {
      // 由计算属性处理
    };

    // 分页
    const changePage = (page) => {
      currentPage.value = page;
      loadClients();
    };

    // 列设置
    const toggleColumnSettings = () => {
      showColumnSettings.value = !showColumnSettings.value;
    };

    const toggleColumn = (columnKey) => {
      const index = visibleColumns.value.indexOf(columnKey);
      if (index > -1) {
        // 至少保留一列
        if (visibleColumns.value.length > 1) {
          visibleColumns.value.splice(index, 1);
          // 保存到localStorage
          localStorage.setItem(
            "clientsVisibleColumns",
            JSON.stringify(visibleColumns.value),
          );
          // 重新检查滚动状态
          nextTick(() => {
            checkScrollState();
          });
        }
      } else {
        visibleColumns.value.push(columnKey);
        // 保存到localStorage
        localStorage.setItem(
          "clientsVisibleColumns",
          JSON.stringify(visibleColumns.value),
        );
        // 重新检查滚动状态
        nextTick(() => {
          checkScrollState();
        });
      }
    };

    // 从localStorage恢复列设置
    const restoreColumnSettings = () => {
      const saved = localStorage.getItem("clientsVisibleColumns");
      if (saved) {
        try {
          const parsed = JSON.parse(saved);
          if (Array.isArray(parsed)) {
            const allowedKeys = availableColumns.value.map((col) => col.key);
            const normalized = [];
            let hadLegacyNameColumn = false;

            parsed.forEach((key) => {
              if (["firstName", "lastName", "email"].includes(key)) {
                hadLegacyNameColumn = true;
                return;
              }
              if (allowedKeys.includes(key) && !normalized.includes(key)) {
                normalized.push(key);
              }
            });

            if (hadLegacyNameColumn && !normalized.includes("client")) {
              normalized.splice(1, 0, "client");
            }

            if (!normalized.includes("client")) {
              normalized.unshift("client");
            }

            visibleColumns.value =
              normalized.length > 0
                ? normalized
                : [...defaultClientVisibleColumns];
            return;
          }
        } catch (e) {
          console.error("Failed to restore column settings:", e);
        }
      }
      visibleColumns.value = [...defaultClientVisibleColumns];
    };

    // 滚动检测
    const checkScrollState = () => {
      const wrapper = tableScrollWrapperRef.value;
      if (!wrapper) return;
      wrapper.style.setProperty(
        "--detail-panel-width",
        `${wrapper.clientWidth}px`,
      );

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

    const scheduleTableScrollRecalc = () => {
      nextTick(() => {
        requestAnimationFrame(() => {
          checkScrollState();
          requestAnimationFrame(() => checkScrollState());
        });
      });
    };

    // 导出功能
    const toggleExportDropdown = () => {
      showExportDropdown.value = !showExportDropdown.value;
    };

    const exportData = (format) => {
      showExportDropdown.value = false;

      if (selectedClients.value.length === 0) {
        alert(t("clientsList_alert_exportNeedSelection"));
        return;
      }

      try {
        // 获取选中的 clients 数据（selectedClients 是 ID 数组，需要从 clients 中找到对应的数据）
        const selectedClientsData = clients.value.filter((client) =>
          selectedClients.value.includes(client.id),
        );

        const yesNo = (v) => (v ? t("leadDetail_yes") : t("leadDetail_no"));

        // 根据可见列定义导出字段
        const exportColumns = [
          {
            key: "id",
            label: t("clientsList_export_col_clientId"),
            getValue: (client) => client.id,
          },
          {
            key: "client",
            label: t("leads_export_col_firstName"),
            getValue: (client) => client.firstName || "",
          },
          {
            key: "client",
            label: t("leads_export_col_lastName"),
            getValue: (client) => client.lastName || "",
          },
          {
            key: "client",
            label: t("leads_export_col_email"),
            getValue: (client) => client.email || "",
          },
          {
            key: "phone",
            label: t("leads_export_col_phone"),
            getValue: (client) => client.phone || "",
          },
          {
            key: "country",
            label: t("leads_export_col_country"),
            getValue: (client) => client.country || "",
          },
          {
            key: "manager",
            label: t("leads_export_col_manager"),
            getValue: (client) => client.manager || "",
          },
          {
            key: "tags",
            label: t("leads_export_col_tags"),
            getValue: (client) =>
              (client.tags || []).map((tg) => tg.tagName || tg.name).join(", "),
          },
          {
            key: "kycStatus",
            label: t("leads_export_col_kycStatus"),
            getValue: (client) => formatKycStatus(client.kycStatus),
          },
          {
            key: "depositCurrency",
            label: t("clientsList_export_col_depositCurrency"),
            getValue: (client) => client.depositCurrency || "USD",
          },
          {
            key: "firstLoginDate",
            label: t("leads_export_col_firstLoginDate"),
            getValue: (client) =>
              formatDate(client.firstLoginAt || client.createdAt),
          },
          {
            key: "lastLoginDate",
            label: t("leads_export_col_lastLoginDate"),
            getValue: (client) => formatDate(client.lastLoginAt),
          },
          {
            key: "hasLoggedIn",
            label: t("clientsList_export_col_hasLoggedIn"),
            getValue: (client) => yesNo(!!client.lastLoginAt),
          },
        ].filter((col) => visibleColumns.value.includes(col.key));

        // 构建 CSV 内容
        const headers = exportColumns.map((col) => col.label);
        const rows = selectedClientsData.map((client) =>
          exportColumns.map((col) => {
            const value = col.getValue(client);
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
          `clients_${new Date().toISOString().split("T")[0]}.${format === "csv" ? "csv" : "xls"}`,
        );
        link.style.visibility = "hidden";
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);

        alert(
          tParams(
            "clientsList_alert_exportSuccess",
            "Successfully exported {n} client(s) as {format}!",
            {
              n: String(selectedClients.value.length),
              format: format.toUpperCase(),
            },
          ),
        );

        const fmtLabel = format.toUpperCase();
        recordOperationLog(
          buildExportLogPayload("page_clients_list", {
            detailZh: `导出 ${selectedClientsData.length} 条客户（${fmtLabel}）`,
            detailEn: `Exported ${selectedClientsData.length} client(s) (${fmtLabel})`,
          }),
        ).catch(() => {});
      } catch (error) {
        console.error("Export error:", error);
        const rawMsg = error?.message || t("common_unknownError");
        alert(
          tParams(
            "clientsList_alert_exportFailed",
            "Failed to export clients: {msg}",
            { msg: rawMsg },
          ),
        );
      }
    };

    // 批量操作
    const openBulkAssignModal = () => {
      if (selectedClients.value.length === 0) return;
      showBulkAssignModal.value = true;
    };

    const handleBulkAssign = async ({ managerId, notes }) => {
      if (!managerId) {
        alert(t("leads_alert_selectManager"));
        return;
      }

      if (selectedClients.value.length === 0) {
        alert(t("clientsList_alert_selectClientFirst"));
        return;
      }

      try {
        const clientIds = selectedClients.value.map((id) => Number(id));
        await clientService.bulkAssign(clientIds, {
          assigneeId: managerId,
          notes: notes || undefined,
          logSubModuleKey: clientsListLogSubModule,
        });
        alert(t("clientsList_alert_bulkAssignOk"));
        showBulkAssignModal.value = false;
        selectedClients.value = [];
        await loadClients();
        await loadStatistics();
      } catch (error) {
        console.error("Bulk assign failed:", error);
        const data = error?.response?.data ?? error;
        const rawMsg =
          data?.message || error?.message || t("common_unknownError");
        const message = translateApiErrorMessage(data?.errorCode, rawMsg);
        alert(
          tParams(
            "clientsList_alert_bulkAssignFailed",
            "Failed to assign clients: {msg}",
            { msg: message },
          ),
        );
      }
    };

    const openBulkTagModal = () => {
      if (selectedClients.value.length === 0) {
        alert(t("clientsList_alert_selectClientFirst"));
        return;
      }
      showBulkTagModal.value = true;
    };

    const closeBulkTagModal = () => {
      showBulkTagModal.value = false;
    };

    // 加载lead tags用于批量操作
    const leadTags = ref([]);

    const loadLeadTags = async () => {
      try {
        const response = await leadsService.getLeadTags();
        if (response.success) {
          leadTags.value = response.data;
        }
      } catch (error) {
        console.error("Failed to load lead tags:", error);
      }
    };

    const handleBulkAddTags = async (tagData) => {
      try {
        if (tagData.tagId) {
          // Use existing tag
          await leadsService.bulkAssignTag(
            selectedClients.value,
            tagData.tagId,
            clientsListLogSubModule,
          );
          alert(
            tParams(
              "clientsList_alert_bulkTagOk",
              "Successfully added tag to {n} client(s)!",
              { n: String(selectedClients.value.length) },
            ),
          );
        } else if (tagData.tagName) {
          // Create new tag first
          const response = await leadsService.createLeadTag({
            tagName: tagData.tagName,
            tagColor: "#f59e0b",
          });

          if (response.success) {
            await leadsService.bulkAssignTag(
              selectedClients.value,
              response.data.id,
              clientsListLogSubModule,
            );
            alert(
              tParams(
                "clientsList_alert_bulkTagCreatedOk",
                "Successfully created tag and added to {n} client(s)!",
                { n: String(selectedClients.value.length) },
              ),
            );
          }
        }

        selectedClients.value = [];
        await loadClients();
        await loadSearchTags();
      } catch (error) {
        const data = error?.response?.data ?? error;
        const rawMsg =
          data?.message || error?.message || t("common_unknownError");
        const msg = translateApiErrorMessage(data?.errorCode, rawMsg);
        alert(
          tParams(
            "clientsList_alert_bulkTagFailed",
            "Failed to add tag: {msg}",
            { msg },
          ),
        );
      }
    };

    // 客户操作
    const addNewClient = async () => {
      await loadCreateOptions();
      if (!createOptionsLoaded.value) {
        return;
      }
      showAddClientModal.value = true;
    };

    const handleClientUpdate = async (clientId, updateData) => {
      try {
        await clientService.update(clientId, updateData);
        await loadClients();
      } catch (error) {
        // 业务错误（如手机号重复）会以 success:false 被拦截器 reject，这里要提示出来，
        // 并刷新回服务端数据，撤销行内乐观更新（行会随 props.client 变化重置）
        console.error("Update client failed:", error);
        const data = error?.response?.data ?? error;
        const rawMsg =
          data?.message || error?.message || t("common_unknownError");
        const message = translateApiErrorMessage(data?.errorCode, rawMsg);
        await loadClients();
        alert(
          tParams(
            "clientsList_alert_updateClientFailed",
            "Failed to update client: {msg}",
            { msg: message },
          ),
        );
      }
    };

    const handleClientDelete = async (clientId) => {
      if (confirm(t("clientsList_confirm_deleteClient"))) {
        try {
          await clientService.delete(clientId);
          await loadClients();
          await loadStatistics();
        } catch (error) {
          console.error("Delete client failed:", error);
        }
      }
    };

    // Tag管理
    const removeClientTag = async (clientId, tagId, tagName) => {
      // 确认删除
      if (
        !confirm(
          tParams(
            "clientsList_confirm_removeClientTag",
            'Are you sure you want to remove the "{tag}" tag from this client?',
            { tag: tagName },
          ),
        )
      ) {
        return;
      }

      try {
        // 使用leadsService的removeTag方法（因为客户和leads使用相同的tag系统）
        await leadsService.removeTagFromLead(
          clientId,
          tagId,
          clientsListLogSubModule,
        );
        alert(
          tParams(
            "clientsList_alert_tagRemoved",
            'Successfully removed "{tag}" tag!',
            { tag: tagName },
          ),
        );

        // 重新加载客户数据以更新显示
        await loadClients();
      } catch (error) {
        const data = error?.response?.data ?? error;
        const rawMsg =
          data?.message || error?.message || t("common_unknownError");
        const msg = translateApiErrorMessage(data?.errorCode, rawMsg);
        alert(
          tParams(
            "clientsList_alert_tagRemoveFailed",
            "Failed to remove tag: {msg}",
            { msg },
          ),
        );
      }
    };

    // 发送通知（单条）
    const sendNotification = (client) => {
      selectedClientForNotification.value = client;
      bulkNotificationRecipients.value = null;
      showSendNotificationModal.value = true;
    };

    // 批量发送通知
    const openBulkSendNotificationModal = () => {
      if (!selectedClientsData.value.length) return;
      bulkNotificationRecipients.value = selectedClientsData.value.map((c) => ({
        id: c.id,
        email: c.email,
        firstName: c.firstName,
        lastName: c.lastName,
      }));
      selectedClientForNotification.value = null;
      showSendNotificationModal.value = true;
    };

    const handleViewAsClient = async (client) => {
      try {
        const res = await leadsService.createPreviewToken(
          client.id,
          clientsListLogSubModule,
        );
        const url = res?.data?.previewUrl;
        if (!url) {
          alert(t("leads_alert_previewUrlFailed"));
          return;
        }
        window.open(url, "_blank", "noopener,noreferrer");
      } catch (e) {
        const data = e?.response?.data ?? e;
        const rawMsg =
          data?.message || e?.message || t("leads_alert_previewLinkFailed");
        const msg = translateApiErrorMessage(data?.errorCode, rawMsg);
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
        loadClients();
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
        tParams("clientsList_notif_recipientFallback", "Client #{n}", {
          n: String(payload.clientId || ""),
        });

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
      selectedClientForNotification.value = null;
    };

    const handleAddClientSuccess = async () => {
      await loadClients();
      await loadStatistics();
    };

    // 点击外部关闭下拉菜单
    const handleClickOutside = (event) => {
      // 关闭列设置下拉菜单
      if (
        !event.target.closest(".btn-column-settings") &&
        !event.target.closest(".column-settings-dropdown")
      ) {
        showColumnSettings.value = false;
      }
    };

    // 生命周期
    onMounted(async () => {
      // 恢复列设置
      restoreColumnSettings();

      if (route.query.search != null && route.query.search !== "") {
        searchTerm.value = String(route.query.search);
      }
      await loadClients();
      const detailId = route.query.detailId;
      if (detailId != null && detailId !== "") {
        const id = parseInt(detailId, 10);
        if (!Number.isNaN(id)) expandedClientId.value = id;
      }

      loadStatistics();
      loadSearchTags();
      loadLeadTags();
      loadCreateOptions().catch(() => {});

      // 监听滚动事件和窗口大小变化
      nextTick(() => {
        const wrapper = tableScrollWrapperRef.value;
        if (wrapper) {
          wrapper.addEventListener("scroll", checkScrollState);
        }
        checkScrollState();
      });

      // 监听窗口大小变化，重新检测滚动状态
      window.addEventListener("resize", checkScrollState);

      // 监听点击外部关闭下拉菜单
      document.addEventListener("click", handleClickOutside);
    });

    // 清理事件监听器
    onUnmounted(() => {
      const wrapper = tableScrollWrapperRef.value;
      if (wrapper) {
        wrapper.removeEventListener("scroll", checkScrollState);
      }
      window.removeEventListener("resize", checkScrollState);
      document.removeEventListener("click", handleClickOutside);
    });

    watch(showSendNotificationModal, (visible) => {
      if (!visible) {
        selectedClientForNotification.value = null;
        bulkNotificationRecipients.value = null;
      }
    });

    watch(
      () => languageStore.loading,
      (isLoading) => {
        if (isLoading) return;
        scheduleTableScrollRecalc();
      },
    );

    return {
      t,
      tParams,
      languageStore,
      clientsListLogSubModule,
      tableScrollWrapperRef,
      // 数据
      clients,
      statistics,
      loading,
      selectedClients,
      expandedClientId,
      showExportDropdown,
      showBulkAssignModal,
      showBulkTagModal,
      showColumnSettings,
      showSearchTagModal,
      showSendNotificationModal,
      selectedClientForNotification,
      bulkNotificationRecipients,
      openBulkSendNotificationModal,
      currentPage,
      perPage,
      totalItems,
      sortField,
      sortDirection,
      availableColumns,
      visibleColumns,
      scrollClasses,
      searchTerm,
      activeSearchTag,
      searchTags,
      leadTags,

      // 计算属性
      totalPages,
      selectAll,
      selectedClientsData,

      // 权限
      hasCreatePermission,
      hasEditPermission,
      hasTagsPermission,
      hasNotificationsPermission,
      hasViewAsClientPermission,
      canViewClientDetail,
      canViewClientDetailProfile,
      hasAssignPermission,
      hasExportPermission,

      // 方法
      loadClients,
      handleSearch,
      clearSearch,
      applySearchTag,
      removeSearchTag,
      handleCreateSearchTag,
      getFullName,
      getClientInitials,
      formatKycStatus,
      getKycStatusClass,
      formatDate,
      formatNumber,
      sortBy,
      toggleRowExpansion,
      toggleSelectAll,
      changePage,
      toggleColumnSettings,
      toggleColumn,
      checkScrollState,
      toggleExportDropdown,
      exportData,
      openBulkAssignModal,
      handleBulkAssign,
      openBulkTagModal,
      closeBulkTagModal,
      handleBulkAddTags,
      addNewClient,
      showAddClientModal,
      countryOptions,
      currencyOptions,
      managerOptions,
      kycTemplates,
      loadingCreateOptions,
      handleClientUpdate,
      handleClientDelete,
      removeClientTag,
      sendNotification,
      handleViewAsClient,
      handleSendNotification,
      handleAddClientSuccess,
    };
  },
};
</script>

<style scoped>
/* 基础样式 */
.clients-list-page {
  padding: var(--content-padding);
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

.stats-info h2 {
  font-size: 20px;
  color: var(--color-ink);
  margin-bottom: 5px;
}

.stats-info p {
  font-size: 14px;
  color: var(--color-muted);
}

.page-stats {
  display: flex;
  gap: 15px;
}

.stat-badge {
  padding: 8px 16px;
  border-radius: 20px;
  font-size: 14px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 8px;
}

.stat-badge i {
  font-size: 16px;
}

.stat-badge span {
  white-space: nowrap;
}

.stat-badge.stat-total {
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.stat-badge.stat-active {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.stat-badge.stat-pending {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

/* Add Client Modal */
.add-client-modal {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  z-index: 2000;
  display: flex;
  align-items: center;
  justify-content: center;
}

.add-client-modal-content {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: 0;
  max-width: 800px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
  animation: modalSlideIn 0.3s ease;
}

.add-client-modal-content::-webkit-scrollbar {
  width: 6px;
}

.add-client-modal-content::-webkit-scrollbar-thumb {
  background: rgba(var(--color-brand-rgb), 0.4);
  border-radius: 4px;
}

.add-client-modal-header {
  padding: 25px 30px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: linear-gradient(
    135deg,
    var(--color-brand-soft) 0%,
    var(--color-brand-soft) 100%
  );
}

.add-client-modal-header h3 {
  font-size: 20px;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 0;
}

.add-client-modal-header h3 i {
  color: var(--color-brand);
  font-size: 22px;
}

.add-client-modal-close {
  border: none;
  background: transparent;
  font-size: 28px;
  color: var(--color-text);
  cursor: pointer;
  line-height: 1;
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

.add-client-modal-close:hover {
  color: var(--color-ink);
  transform: scale(1.05);
}

.add-client-modal-body {
  padding: 30px;
}

.add-client-modal-body .form-section {
  margin-bottom: 30px;
}

.add-client-modal-body .form-section:last-child {
  margin-bottom: 0;
}

.add-client-modal-body .form-section-title {
  font-size: 16px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 15px;
  padding-bottom: 10px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  align-items: center;
  gap: 8px;
}

.add-client-modal-body .form-section-title i {
  color: var(--color-brand);
}

.add-client-modal-body .form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 15px;
}

.add-client-modal-body .form-field {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.add-client-modal-body .form-field.full-width {
  grid-column: 1 / -1;
}

.add-client-modal-body .form-field label {
  font-weight: 600;
  color: var(--color-text);
  font-size: 14px;
}

.add-client-modal-body .form-field label .required {
  color: var(--color-danger);
  margin-left: 2px;
}

.add-client-modal-body .form-field input,
.add-client-modal-body .form-field select {
  padding: 10px 14px;
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
  background: var(--color-surface);
}

.add-client-modal-body .form-field input:focus,
.add-client-modal-body .form-field select:focus {
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.add-client-modal-body .form-field input::placeholder {
  color: var(--color-faint);
}

.add-client-modal-body .field-hint {
  font-size: 14px;
  color: var(--color-muted);
  font-style: italic;
  margin-top: -4px;
}

.add-client-modal-body .field-hint-error {
  color: var(--color-danger);
  font-weight: 500;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  font-weight: 500;
  color: var(--color-text);
  font-size: 14px;
  user-select: none;
}

.checkbox-label input[type="checkbox"] {
  width: 18px;
  height: 18px;
  cursor: pointer;
  accent-color: var(--color-brand);
}

.checkbox-label:hover {
  color: var(--color-brand);
}

.password-strength {
  width: 100%;
  height: 4px;
  background: var(--color-border);
  border-radius: 2px;
  overflow: hidden;
  margin-top: 8px;
}

.password-strength-bar {
  height: 100%;
  width: 0%;
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
  border-radius: 2px;
}

.password-strength-bar.weak {
  width: 33%;
  background: var(--color-danger-solid);
}

.password-strength-bar.medium {
  width: 66%;
  background: var(--color-warning-solid);
}

.password-strength-bar.strong {
  width: 100%;
  background: var(--color-success-solid);
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

.kyc-options {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.kyc-option {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 15px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
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
}

.kyc-option:hover {
  border-color: var(--color-brand);
  background: var(--color-surface-soft);
}

.kyc-option input[type="radio"] {
  margin-top: 3px;
  cursor: pointer;
  accent-color: var(--color-brand);
  width: 18px;
  height: 18px;
}

.kyc-option-content {
  flex: 1;
}

.kyc-option-title {
  font-weight: 600;
  color: var(--color-ink);
  font-size: 14px;
  margin-bottom: 4px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.kyc-option-title i {
  color: var(--color-brand);
}

.kyc-option-description {
  font-size: 14px;
  color: var(--color-muted);
}

.kyc-option.selected {
  border-color: var(--color-brand);
  background: var(--color-brand-soft);
}

.kyc-template-select {
  margin-top: 15px;
}

.add-client-modal-footer {
  padding: 20px 30px;
  border-top: 2px solid var(--color-border);
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  background: var(--color-surface-soft);
}

.btn-modal-client {
  padding: 12px 24px;
  border: none;
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
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.btn-modal-client-cancel {
  background: var(--color-border);
  color: var(--color-text);
}

.btn-modal-client-cancel:hover {
  background: var(--color-border-strong);
}

.btn-modal-client-submit {
  background: var(--color-brand-solid);
  color: white;
  box-shadow: 0 2px 8px rgba(var(--color-brand-rgb), 0.3);
}

.btn-modal-client-submit:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.4);
}

@keyframes modalSlideIn {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (max-width: 768px) {
  .add-client-modal-body .form-grid {
    grid-template-columns: 1fr;
  }

  .add-client-modal-content {
    width: 95%;
    max-height: 95vh;
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
  font-size: 14px;
  color: var(--color-text);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.btn-add-tag {
  padding: 4px 12px;
  border: none;
  border-radius: var(--radius-sm);
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
  font-size: 14px;
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
  font-size: 14px;
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
  font-size: 14px;
  font-style: italic;
}

/* 表格容器 */
.clients-table-container {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  overflow: visible;
  position: relative;
  z-index: 1;
}

.table-header {
  padding: 20px 30px;
  background: var(--color-surface-soft);
  border-bottom: 2px solid var(--color-border);
  border-radius: 12px 12px 0 0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 15px;
  position: relative;
  z-index: 100;
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
  position: relative;
  z-index: 100;
}

/* 批量操作 */
.bulk-actions {
  display: none;
  align-items: center;
  gap: 10px;
  padding: 10px 15px;
  background: var(--color-brand-soft);
  border-radius: var(--radius-md);
  border: 2px solid var(--color-brand);
}

.bulk-actions.show {
  display: flex;
}

.bulk-actions-label {
  font-size: 14px;
  color: var(--color-brand);
  font-weight: 600;
}

.bulk-actions-count {
  background: var(--color-brand-solid);
  color: white;
  padding: 2px 8px;
  border-radius: var(--radius-lg);
  font-size: 14px;
  font-weight: 600;
}

.btn-bulk {
  padding: 6px 12px;
  border: none;
  border-radius: var(--radius-sm);
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

.btn-bulk-tag {
  background: var(--color-warning-solid);
  color: white;
}

.btn-bulk-tag:hover {
  background: var(--color-warning-solid);
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
  font-size: 14px;
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
  display: none;
  z-index: 1000;
  overflow: hidden;
  animation: slideDown 0.2s ease;
}

.export-dropdown.show {
  display: block;
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
  font-size: 14px;
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

/* 行选择器和按钮 */
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

.rows-selector select:hover {
  border-color: var(--color-brand);
}

.rows-selector select:focus {
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.btn-add-client {
  background: var(--color-brand-solid);
  color: white;
  border: none;
  padding: 10px 20px;
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
  gap: 8px;
  box-shadow: 0 2px 8px rgba(var(--color-brand-rgb), 0.3);
}

.btn-add-client:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.4);
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
  position: relative;
  z-index: 100;
}

.btn-column-settings:hover {
  border-color: var(--color-brand);
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

/* 列设置下拉菜单 */
.column-settings-dropdown {
  position: absolute;
  top: calc(100% + 5px);
  right: 0;
  background: var(--color-surface);
  border-radius: var(--radius-md);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
  min-width: 280px;
  max-width: 320px;
  display: none;
  z-index: 10000;
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
  max-height: 400px;
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
  user-select: none;
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
  font-size: 14px;
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
  0%,
  100% {
    transform: translateX(0);
  }
  50% {
    transform: translateX(5px);
  }
}

/* 表格样式 */
.clients-table {
  width: max-content;
  min-width: 100%;
  border-collapse: collapse;
  table-layout: auto;
  position: relative;
  z-index: 1;
}

.clients-table thead {
  background: var(--color-surface-soft);
  position: relative;
  z-index: 1;
}

.clients-table th {
  padding: 16px 20px;
  text-align: left;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 2px solid var(--color-border);
  white-space: nowrap;
}

.clients-table th.sortable {
  cursor: pointer;
  user-select: none;
  position: relative;
  padding-right: 30px;
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

.clients-table th.sortable:hover {
  background: var(--color-surface-muted);
  color: var(--color-brand);
}

.sort-icon {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  display: flex;
  flex-direction: column;
  gap: 2px;
  opacity: 0.3;
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

.clients-table th.sortable:hover .sort-icon {
  opacity: 0.6;
}

.sort-icon i {
  /* @font-floor-exempt: visual-only sort glyph */
  font-size: 10px;
  line-height: 1;
}

.clients-table th.hideable {
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

.clients-table th.hidden,
.clients-table td.hidden {
  display: none;
}

.clients-table th.checkbox-col,
.clients-table td.checkbox-col {
  width: 50px;
  text-align: center;
  padding: 16px 10px;
}

/* 复选框样式 */
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

/* 表格行样式 */
.clients-table tbody tr {
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
  position: relative;
  z-index: 1;
}

.clients-table tbody tr:hover {
  background: var(--color-surface-soft);
}

.clients-table tbody tr.expanded {
  background: var(--color-brand-soft);
}

/* Actions 列固定（参考 IB List） */
.clients-table__th--sticky {
  position: sticky;
  right: 0;
  z-index: 2;
  background: var(--color-surface-soft);
  border-left: 1px solid var(--color-border);
  box-shadow: inset 12px 0 20px -10px rgba(0, 0, 0, 0.06);
}

.clients-table__cell--sticky {
  position: sticky;
  right: 0;
  z-index: 1;
  background: var(--color-surface);
  border-left: 1px solid var(--color-border);
  box-shadow: inset 12px 0 20px -10px rgba(0, 0, 0, 0.06);
  white-space: nowrap;
}

.clients-table tbody tr:hover .clients-table__cell--sticky {
  background: var(--color-surface-soft);
}

.clients-table tbody tr.expanded .clients-table__cell--sticky {
  background: var(--color-brand-soft);
}

.clients-table td {
  padding: 16px 20px;
  font-size: 14px;
  color: var(--color-text);
  white-space: nowrap;
}

.clients-table td.hideable {
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

.client-id {
  font-weight: 600;
  color: var(--color-brand);
}

.client-info-cell {
  white-space: normal;
}

.client-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.client-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: var(--color-brand-soft);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--color-brand);
  border: 1px solid var(--color-brand-soft-strong);
  font-weight: 600;
  font-size: 14px;
  flex-shrink: 0;
  text-transform: uppercase;
}

.client-details {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.client-name {
  font-weight: 600;
  color: var(--color-ink);
  line-height: 1.2;
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
  font-size: 14px;
  color: var(--color-muted);
}

/* 标签样式 */
.client-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  max-width: 200px;
}

.client-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 8px 3px 8px;
  background: var(--color-warning-soft);
  color: var(--color-warning);
  border-radius: var(--radius-lg);
  font-size: 14px;
  font-weight: 600;
  white-space: nowrap;
  position: relative;
  padding-right: 20px;
}

.client-tag i {
  /* @font-floor-exempt: visual-only tag glyph */
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
  font-size: 14px;
  font-style: italic;
}

/* KYC状态徽章 */
.kyc-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: var(--radius-lg);
  font-size: 14px;
  font-weight: 600;
  text-transform: uppercase;
  white-space: nowrap;
}

.kyc-badge.not-started {
  background: var(--color-border);
  color: var(--color-text);
}

.kyc-badge.in-progress {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.kyc-badge.pending-review {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.kyc-badge.approved {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.kyc-badge.rejected {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

/* 登录状态 */
.login-status {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  font-weight: 600;
}

.login-status i {
  font-size: 14px;
}

.login-status.logged-in {
  color: var(--color-success);
}

.login-status.logged-in i {
  color: var(--color-success);
}

.login-status.not-logged {
  color: var(--color-danger);
}

.login-status.not-logged i {
  color: var(--color-danger);
}

/* 操作按钮 */
.action-buttons {
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-action {
  padding: 8px 16px;
  border: none;
  border-radius: var(--radius-sm);
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

.btn-notification {
  color: var(--color-warning);
  background: var(--color-warning-soft);
  border: 1px solid var(--color-warning-border);
  box-shadow: none;
}

.btn-notification:hover {
  color: var(--color-surface);
  background: var(--color-warning-solid);
  transform: translateY(-2px);
  box-shadow: var(--shadow-sm);
}

.btn-view-as-client {
  color: var(--color-info);
  background: var(--color-info-soft);
  border: 1px solid var(--color-info-border);
  box-shadow: none;
}

.btn-view-as-client:hover {
  color: var(--color-surface);
  background: var(--color-info-solid);
  transform: translateY(-2px);
  box-shadow: var(--shadow-sm);
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

/* 详情行 */
.detail-row {
  display: none;
  position: relative;
  z-index: 1;
}

.detail-row.show {
  display: table-row;
}

.detail-row > td {
  padding: 0;
  white-space: normal;
  background: var(--color-surface-soft);
}

.detail-content {
  padding: 30px;
  background: var(--color-surface-soft);
  width: var(--detail-panel-width, 100%);
  max-width: 100%;
  box-sizing: border-box;
  position: sticky;
  left: 0;
  z-index: 1;
}

/* 分页 */
.table-pagination {
  padding: 20px 30px;
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 15px;
  border-top: 1px solid var(--color-border);
}

.btn-page {
  padding: 8px 12px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
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

.btn-page:hover:not(:disabled) {
  border-color: var(--color-brand);
  color: var(--color-brand);
}

.btn-page:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.page-info {
  font-size: 14px;
  color: var(--color-text);
  font-weight: 600;
}

/* 动画 */
@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* 响应式设计 */
@media (max-width: 768px) {
  .clients-list-page {
    padding: 20px 15px;
  }

  .page-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 20px;
  }

  .page-actions {
    width: 100%;
    justify-content: flex-end;
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
    gap: 15px;
  }

  .table-header-left {
    width: 100%;
    flex-direction: column;
    align-items: flex-start;
  }

  .bulk-actions {
    width: 100%;
    justify-content: flex-start;
  }

  .table-header-right {
    width: 100%;
    justify-content: space-between;
  }
}
</style>
