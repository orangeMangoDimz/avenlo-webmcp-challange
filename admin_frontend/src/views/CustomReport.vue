<template>
  <div class="custom-report-page">
    <div v-if="!hasReadonlyPermission" class="error-container">
      <i class="fas fa-lock"></i>
      <p>
        {{
          t(
            "common_noPermission",
            "You do not have permission to view this page.",
          )
        }}
      </p>
    </div>

    <template v-else>
      <!-- List view -->
      <template v-if="!routeReportId">
        <div class="page-header">
          <div class="page-title">
            <div>
              <h1>{{ t("menu_customReport", "Custom Report") }}</h1>
              <p>
                {{
                  t("customReport_sub", "Manage and open your custom reports.")
                }}
              </p>
            </div>
          </div>
          <div class="page-actions">
            <PageHeaderActions />
          </div>
        </div>

        <div class="transaction-table-container">
          <div class="table-header">
            <h2>
              <i class="fas fa-list"></i>
              {{ t("customReport_listTitle", "Reports") }}
            </h2>
            <div class="rows-selector">
              <label>{{ t("customReport_show", "Show") }}</label>
              <select v-model="perPage">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
              </select>
            </div>
            <div class="table-controls">
              <button class="btn btn-secondary" @click="openCoverage">
                <i class="fas fa-layer-group"></i>
                {{ t("customReport_stack_open", "Sales coverage") }}
              </button>
              <button class="btn btn-primary" @click="openCreateModal">
                <i class="fas fa-plus"></i> {{ t("customReport_new", "NEW") }}
              </button>
              <div class="search-box">
                <input
                  type="text"
                  v-model="searchQuery"
                  :placeholder="
                    t('customReport_searchPlaceholder', 'Search reports...')
                  "
                  @input="handleSearch"
                />
                <i class="fas fa-search search-icon"></i>
              </div>
            </div>
          </div>

          <div v-if="loading" class="loading-container">
            <i class="fas fa-spinner fa-spin"></i>
            <p>{{ t("customReport_loading", "Loading...") }}</p>
          </div>

          <table v-else class="transaction-table">
            <thead>
              <tr>
                <th>{{ t("customReport_th_select", "Select") }}</th>
                <th>{{ t("customReport_th_name", "Name") }}</th>
                <th>{{ t("customReport_th_widgetCount", "Widgets") }}</th>
                <th>{{ t("customReport_th_createdBy", "Created By") }}</th>
                <th>{{ t("customReport_th_createdAt", "Created At") }}</th>
                <th>{{ t("customReport_th_action", "Action") }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="reports.length === 0">
                <td colspan="6" class="empty-state">
                  <i class="fas fa-inbox"></i>
                  <p>
                    {{
                      t(
                        "customReport_empty",
                        "No reports yet. Create one to get started.",
                      )
                    }}
                  </p>
                </td>
              </tr>
              <tr v-for="report in paginatedReports" :key="report.id">
                <td>
                  <button class="btn-select" @click="selectReport(report.id)">
                    {{ t("customReport_select", "SELECT") }}
                  </button>
                </td>
                <td>{{ report.name }}</td>
                <td>{{ report.widgetCount ?? 0 }}</td>
                <td>{{ report.createdByName || report.createdBy || "-" }}</td>
                <td>{{ formatDateTime(report.createdAt) }}</td>
                <td>
                  <div class="action-buttons">
                    <button
                      class="btn-action btn-edit"
                      :title="t('customReport_titleEdit', 'Edit Report')"
                      @click="openEditModal(report)"
                    >
                      <i class="fas fa-edit"></i>
                      {{ t("customReport_btnEdit", "Edit") }}
                    </button>
                    <button
                      class="btn-action btn-delete"
                      :title="t('customReport_titleDelete', 'Delete Report')"
                      @click="handleDelete(report)"
                    >
                      <i class="fas fa-trash"></i>
                      {{ t("customReport_btnDelete", "Delete") }}
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>

      <!-- Detail / widgets view -->
      <template v-else>
        <div class="page-header">
          <div class="page-title">
            <button class="btn-back" @click="backToList">
              <i class="fas fa-arrow-left"></i>
            </button>
            <div>
              <h1>
                {{
                  detail?.report?.name ||
                  t("menu_customReport", "Custom Report")
                }}
              </h1>
              <p>
                {{ t("customReport_widgetsSub", "Widgets for this report.") }}
              </p>
            </div>
          </div>
          <div class="page-actions">
            <PageHeaderActions />
          </div>
        </div>

        <div v-if="detailLoading" class="loading-container">
          <i class="fas fa-spinner fa-spin"></i>
          <p>{{ t("customReport_loading", "Loading...") }}</p>
        </div>

        <div v-else class="transaction-table-container">
          <div class="table-header">
            <h2>
              <i class="fas fa-th-large"></i>
              {{ t("customReport_widgetsTitle", "Widgets") }}
            </h2>
            <div class="rows-selector">
              <label>{{ t("customReport_show", "Show") }}</label>
              <select v-model="widgetPerPage">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
              </select>
            </div>
            <div class="table-controls">
              <button class="btn btn-primary" @click="openWidgetModal">
                <i class="fas fa-plus"></i> {{ t("customReport_new", "NEW") }}
              </button>
              <div class="search-box">
                <input
                  type="text"
                  v-model="widgetSearchQuery"
                  :placeholder="
                    t(
                      'customReport_widgetSearchPlaceholder',
                      'Search widgets...',
                    )
                  "
                />
                <i class="fas fa-search search-icon"></i>
              </div>
            </div>
          </div>

          <table class="transaction-table">
            <thead>
              <tr>
                <th>{{ t("customReport_th_select", "Select") }}</th>
                <th>{{ t("customReport_th_name", "Name") }}</th>
                <th>{{ t("customReport_th_dataSource", "Data Source") }}</th>
                <th>{{ t("customReport_th_createdBy", "Created By") }}</th>
                <th>{{ t("customReport_th_createdAt", "Created At") }}</th>
                <th>{{ t("customReport_th_action", "Action") }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="filteredWidgets.length === 0">
                <td colspan="6" class="empty-state">
                  <i class="fas fa-inbox"></i>
                  <p>
                    {{
                      t(
                        "customReport_widgetsEmpty",
                        "No widgets on this report yet.",
                      )
                    }}
                  </p>
                </td>
              </tr>
              <tr v-for="widget in paginatedWidgets" :key="widget.id">
                <td>
                  <button class="btn-select" @click="openWidget(widget)">
                    {{ t("customReport_select", "SELECT") }}
                  </button>
                </td>
                <td>{{ widget.name || "-" }}</td>
                <td>
                  {{
                    widget.dataSourceName ||
                    widget.dataSourceObject ||
                    widget.dataSourceId
                  }}
                </td>
                <td>{{ widget.createdByName || widget.createdBy || "-" }}</td>
                <td>{{ formatDateTime(widget.createdAt) }}</td>
                <td>
                  <div class="action-buttons">
                    <button
                      class="btn-action btn-duplicate"
                      :title="
                        t(
                          'customReport_titleDuplicateWidget',
                          'Duplicate Widget',
                        )
                      "
                      :disabled="duplicating"
                      @click="handleDuplicateWidget(widget)"
                    >
                      <i class="fas fa-copy"></i>
                      {{ t("customReport_btnDuplicate", "Duplicate") }}
                    </button>
                    <button
                      class="btn-action btn-edit"
                      :title="t('customReport_titleEditWidget', 'Edit Widget')"
                      @click="openEditWidgetModal(widget)"
                    >
                      <i class="fas fa-edit"></i>
                      {{ t("customReport_btnEdit", "Edit") }}
                    </button>
                    <button
                      class="btn-action btn-delete"
                      :title="
                        t('customReport_titleDeleteWidget', 'Delete Widget')
                      "
                      @click="handleDeleteWidget(widget)"
                    >
                      <i class="fas fa-trash"></i>
                      {{ t("customReport_btnDelete", "Delete") }}
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </template>

    <!-- Create / Edit report modal -->
    <div
      v-if="showFormModal"
      class="modal-overlay"
      @click.self="closeFormModal"
    >
      <div class="modal-card">
        <h3>
          {{
            editingReportId
              ? t("customReport_editTitle", "Edit Report")
              : t("customReport_createTitle", "Create New Report")
          }}
        </h3>
        <p>
          {{
            t(
              "customReport_createHint",
              "Kindly provide a title for the new report",
            )
          }}
        </p>
        <input
          v-model="formReportName"
          type="text"
          class="modal-input"
          :placeholder="t('customReport_namePlaceholder', 'Report name')"
          @keyup.enter="saveReport"
        />
        <div class="modal-actions">
          <button
            class="btn btn-primary"
            :disabled="saving"
            @click="saveReport"
          >
            {{
              editingReportId
                ? t("customReport_save", "SAVE")
                : t("customReport_create", "CREATE")
            }}
          </button>
          <button
            class="btn btn-secondary"
            :disabled="saving"
            @click="closeFormModal"
          >
            {{ t("customReport_cancel", "CANCEL") }}
          </button>
        </div>
      </div>
    </div>

    <!-- Create / Edit widget modal -->
    <div
      v-if="showWidgetModal"
      class="modal-overlay"
      @click.self="closeWidgetModal"
    >
      <div class="modal-card">
        <h3>
          {{
            editingWidgetId
              ? t("customReport_widgetEditTitle", "Edit Widget")
              : t("customReport_widgetCreateTitle", "Create Widget")
          }}
        </h3>
        <p>
          {{
            editingWidgetId
              ? t(
                  "customReport_widgetCreateHint",
                  "Enter a widget name, then choose a data source.",
                )
              : t(
                  "customReport_widgetCreateHintWithType",
                  "Enter a widget name, choose a data source, then add the first type.",
                )
          }}
        </p>

        <label class="modal-label">{{
          t("customReport_th_name", "Name")
        }}</label>
        <input
          v-model="widgetForm.name"
          type="text"
          class="modal-input"
          :placeholder="t('customReport_widgetNamePlaceholder', 'Widget name')"
          @keyup.enter="saveWidget"
        />

        <label class="modal-label">{{
          t("customReport_th_dataSource", "Data Source")
        }}</label>
        <select
          v-model="widgetForm.dataSourceId"
          class="modal-input modal-select"
        >
          <option value="">
            {{ t("customReport_selectDataSource", "Select data source") }}
          </option>
          <option
            v-for="source in dataSources"
            :key="source.id"
            :value="source.id"
          >
            {{ source.displayName }}
          </option>
        </select>

        <template v-if="!editingWidgetId">
          <label class="modal-label">{{
            t("customReport_widgetType", "Widget type")
          }}</label>
          <select
            v-model="widgetForm.typeKind"
            class="modal-input modal-select"
          >
            <option value="table">
              {{ t("customReport_type_table", "Table") }}
            </option>
            <option value="chart">
              {{ t("customReport_type_chart", "Chart") }}
            </option>
          </select>

          <label class="modal-label">{{
            t("customReport_typeNameOptional", "Type name (optional)")
          }}</label>
          <input
            v-model="widgetForm.typeLabel"
            type="text"
            class="modal-input"
            maxlength="255"
            :placeholder="
              widgetForm.typeKind === 'chart'
                ? t('customReport_type_chart', 'Chart')
                : t('customReport_type_table', 'Table')
            "
            @keyup.enter="saveWidget"
          />
        </template>

        <div class="modal-actions">
          <button
            class="btn btn-primary"
            :disabled="savingWidget"
            @click="saveWidget"
          >
            {{
              editingWidgetId
                ? t("customReport_save", "SAVE")
                : t("customReport_create", "CREATE")
            }}
          </button>
          <button
            class="btn btn-secondary"
            :disabled="savingWidget"
            @click="closeWidgetModal"
          >
            {{ t("customReport_cancel", "CANCEL") }}
          </button>
        </div>
      </div>
    </div>

    <!-- Delete confirmation modal -->
    <div
      v-if="showDeleteModal"
      class="modal-overlay"
      @click.self="closeDeleteModal"
    >
      <div class="modal-card">
        <h3>{{ t("customReport_deleteTitle", "Confirm Delete") }}</h3>
        <p>{{ deleteModalMessage }}</p>
        <div class="modal-actions">
          <button
            class="btn btn-danger"
            :disabled="deleting"
            @click="confirmDelete"
          >
            {{ t("customReport_btnDelete", "Delete") }}
          </button>
          <button
            class="btn btn-secondary"
            :disabled="deleting"
            @click="closeDeleteModal"
          >
            {{ t("customReport_cancel", "CANCEL") }}
          </button>
        </div>
      </div>
    </div>

    <div
      v-if="showDuplicateModal"
      class="modal-overlay"
      @click.self="closeDuplicateModal"
    >
      <div class="modal-card">
        <h3>{{ t("customReport_duplicateTitle", "Confirm Duplicate") }}</h3>
        <p>{{ duplicateModalMessage }}</p>
        <div class="modal-actions">
          <button
            class="btn btn-primary"
            :disabled="duplicating"
            @click="confirmDuplicateWidget"
          >
            {{ t("customReport_btnDuplicate", "Duplicate") }}
          </button>
          <button
            class="btn btn-secondary"
            :disabled="duplicating"
            @click="closeDuplicateModal"
          >
            {{ t("customReport_cancel", "CANCEL") }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";
import { useAuthStore } from "@/stores/auth";
import customReportApi from "@/services/customReportApi";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t, tParams } = useAdminI18n();
const authStore = useAuthStore();
const route = useRoute();
const router = useRouter();

const hasReadonlyPermission = computed(() =>
  authStore.hasPermission("page_fundingreport_readonly"),
);

const loading = ref(true);
const reports = ref([]);
const searchQuery = ref("");
const perPage = ref(10);
let searchTimer = null;

const paginatedReports = computed(() => {
  const limit = Number(perPage.value) || 10;
  return reports.value.slice(0, limit);
});

const selectedReportId = ref(null);
const detail = ref(null);
const detailLoading = ref(false);
const widgetSearchQuery = ref("");
const widgetPerPage = ref(10);
const showWidgetModal = ref(false);
const savingWidget = ref(false);
const editingWidgetId = ref(null);
const dataSources = ref([]);
const TYPE_LABEL_MAX = 255;

const emptyWidgetForm = () => ({
  name: "",
  dataSourceId: "",
  typeKind: "table",
  typeLabel: "",
});

const widgetForm = ref(emptyWidgetForm());

const filteredWidgets = computed(() => {
  const widgets = detail.value?.widgets || [];
  const q = widgetSearchQuery.value.trim().toLowerCase();
  if (!q) return widgets;
  return widgets.filter((w) => {
    const haystack = [
      w.name,
      w.dataSourceName,
      w.dataSourceObject,
      w.dataSourceId,
    ]
      .filter(Boolean)
      .join(" ")
      .toLowerCase();
    return haystack.includes(q);
  });
});

const paginatedWidgets = computed(() => {
  const limit = Number(widgetPerPage.value) || 10;
  return filteredWidgets.value.slice(0, limit);
});

const loadDataSources = async () => {
  try {
    const response = await customReportApi.listDataSources();
    if (response.success) {
      dataSources.value = response.data.items || [];
    }
  } catch (err) {
    alert(
      err.response?.data?.message ||
        err.message ||
        t("customReport_err_loadDataSources", "Failed to load data sources."),
    );
  }
};

const openWidgetModal = async () => {
  editingWidgetId.value = null;
  widgetForm.value = emptyWidgetForm();
  showWidgetModal.value = true;
  await loadDataSources();
};

const openEditWidgetModal = async (widget) => {
  editingWidgetId.value = widget.id;
  widgetForm.value = {
    ...emptyWidgetForm(),
    name: widget.name || "",
    dataSourceId: widget.dataSourceId || "",
  };
  showWidgetModal.value = true;
  await loadDataSources();
};

const closeWidgetModal = () => {
  showWidgetModal.value = false;
  editingWidgetId.value = null;
  widgetForm.value = emptyWidgetForm();
};

const firstWidgetTypeViewConfig = () => {
  const kind = widgetForm.value.typeKind === "chart" ? "chart" : "table";
  const label =
    widgetForm.value.typeLabel.trim() ||
    (kind === "chart"
      ? t("customReport_type_chart", "Chart")
      : t("customReport_type_table", "Table"));
  const user = authStore.user || {};
  const createdBy = String(user.id || user.userId || "")
    .trim()
    .slice(0, 36);
  const createdByName = String(
    user.fullName || user.username || createdBy || "",
  )
    .trim()
    .slice(0, TYPE_LABEL_MAX);
  const type = {
    id: kind,
    label: label.slice(0, TYPE_LABEL_MAX),
    kind,
  };
  if (createdBy) type.createdBy = createdBy;
  if (createdByName) type.createdByName = createdByName;
  type.createdAt = new Date().toISOString().slice(0, 19).replace("T", " ");
  return {
    activeView: type.id,
    types: [type],
    views: {},
  };
};

const saveWidget = async () => {
  if (!selectedReportId.value || savingWidget.value) return;

  if (!widgetForm.value.name.trim()) {
    alert(
      t("customReport_err_widgetNameRequired", "Please enter a widget name."),
    );
    return;
  }
  if (!widgetForm.value.dataSourceId) {
    alert(
      t("customReport_err_dataSourceRequired", "Please select a data source."),
    );
    return;
  }

  savingWidget.value = true;
  try {
    const payload = {
      name: widgetForm.value.name.trim(),
      dataSourceId: widgetForm.value.dataSourceId,
    };
    if (!editingWidgetId.value) {
      payload.viewConfig = firstWidgetTypeViewConfig();
    }
    const response = editingWidgetId.value
      ? await customReportApi.updateWidget(
          selectedReportId.value,
          editingWidgetId.value,
          payload,
        )
      : await customReportApi.createWidget(selectedReportId.value, payload);

    if (response.success) {
      closeWidgetModal();
      await loadReportDetail(selectedReportId.value);
    }
  } catch (err) {
    alert(
      err.response?.data?.message ||
        err.message ||
        t("customReport_err_createWidget", "Failed to save widget."),
    );
  } finally {
    savingWidget.value = false;
  }
};

const handleDeleteWidget = (widget) => {
  if (!selectedReportId.value || !widget?.id) return;

  deleteTarget.value = {
    type: "widget",
    id: widget.id,
    name:
      widget.name ||
      widget.dataSourceName ||
      t("customReport_widget", "Widget"),
  };
  showDeleteModal.value = true;
};

const showFormModal = ref(false);
const formReportName = ref("");
const editingReportId = ref(null);
const saving = ref(false);

const showDeleteModal = ref(false);
const deleting = ref(false);
const deleteTarget = ref(null);

const showDuplicateModal = ref(false);
const duplicating = ref(false);
const duplicateTarget = ref(null);

const deleteModalMessage = computed(() => {
  const name = deleteTarget.value?.name || "";
  if (deleteTarget.value?.type === "widget") {
    return tParams(
      "customReport_confirmDeleteWidget",
      'Are you sure you want to delete widget "{name}"?',
      { name },
    );
  }
  return tParams(
    "customReport_confirmDelete",
    'Are you sure you want to delete report "{name}"?',
    { name },
  );
});

const duplicateModalMessage = computed(() => {
  const name =
    duplicateTarget.value?.name || t("customReport_widget", "Widget");
  return tParams(
    "customReport_confirmDuplicateWidget",
    'Duplicate widget "{name}" as a new widget with the same data source and widget types?',
    { name },
  );
});

const handleDuplicateWidget = (widget) => {
  if (!selectedReportId.value || !widget?.id || duplicating.value) return;
  duplicateTarget.value = {
    id: widget.id,
    name:
      widget.name ||
      widget.dataSourceName ||
      t("customReport_widget", "Widget"),
  };
  showDuplicateModal.value = true;
};

const closeDuplicateModal = () => {
  if (duplicating.value) return;
  showDuplicateModal.value = false;
  duplicateTarget.value = null;
};

const confirmDuplicateWidget = async () => {
  if (
    !selectedReportId.value ||
    !duplicateTarget.value?.id ||
    duplicating.value
  )
    return;
  duplicating.value = true;
  try {
    const response = await customReportApi.duplicateWidget(
      selectedReportId.value,
      duplicateTarget.value.id,
    );
    if (!response.success) {
      alert(
        response.message ||
          t("customReport_err_duplicateWidget", "Failed to duplicate widget."),
      );
      return;
    }
    const newName = response.data?.name || "";
    showDuplicateModal.value = false;
    duplicateTarget.value = null;
    await loadReportDetail(selectedReportId.value);
    if (newName) {
      alert(
        tParams(
          "customReport_alert_duplicateOk",
          'Widget duplicated successfully as "{name}".',
          { name: newName },
        ),
      );
    }
  } catch (err) {
    alert(
      err.response?.data?.message ||
        err.message ||
        t("customReport_err_duplicateWidget", "Failed to duplicate widget."),
    );
  } finally {
    duplicating.value = false;
  }
};

const closeDeleteModal = () => {
  if (deleting.value) return;
  showDeleteModal.value = false;
  deleteTarget.value = null;
};

const confirmDelete = async () => {
  if (!deleteTarget.value || deleting.value) return;

  deleting.value = true;
  try {
    if (deleteTarget.value.type === "widget") {
      await customReportApi.deleteWidget(
        selectedReportId.value,
        deleteTarget.value.id,
      );
      showDeleteModal.value = false;
      deleteTarget.value = null;
      await loadReportDetail(selectedReportId.value);
    } else {
      await customReportApi.deleteReport(deleteTarget.value.id);
      showDeleteModal.value = false;
      deleteTarget.value = null;
      await loadReports();
    }
  } catch (err) {
    alert(
      err.response?.data?.message ||
        err.message ||
        (deleteTarget.value?.type === "widget"
          ? t("customReport_err_deleteWidget", "Failed to delete widget.")
          : t("customReport_err_delete", "Failed to delete report.")),
    );
  } finally {
    deleting.value = false;
  }
};

const loadReports = async () => {
  loading.value = true;
  try {
    const params = {};
    if (searchQuery.value) params.search = searchQuery.value;
    const response = await customReportApi.listReports(params);
    if (response.success) {
      reports.value = response.data.items || [];
    }
  } catch (err) {
    alert(
      err.response?.data?.message ||
        err.message ||
        t("customReport_err_load", "Failed to load custom reports."),
    );
  } finally {
    loading.value = false;
  }
};

const handleSearch = () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => loadReports(), 300);
};

const selectReport = (id) => {
  const nextId = String(id || "");
  if (!nextId) return;
  router.push(`/custom-report/${nextId}`);
};

const openCoverage = () => {
  router.push({ name: "custom-report-coverage" });
};

const openWidget = (widget) => {
  const reportId = routeReportId.value || selectedReportId.value;
  if (!reportId || !widget?.id) return;
  router.push(`/custom-report/${reportId}/widget/${widget.id}`);
};

const backToList = () => {
  widgetSearchQuery.value = "";
  router.push("/custom-report");
};

let detailLoadSeq = 0;
const loadReportDetail = async (id) => {
  const seq = ++detailLoadSeq;
  selectedReportId.value = id;
  detailLoading.value = true;
  loading.value = false;
  try {
    const response = await customReportApi.getReport(id);
    if (seq !== detailLoadSeq) return;
    if (response.success) {
      detail.value = response.data;
      return;
    }
    detail.value = null;
    selectedReportId.value = null;
    router.replace("/custom-report");
  } catch (err) {
    if (seq !== detailLoadSeq) return;
    alert(
      err.response?.data?.message ||
        err.message ||
        t("customReport_err_loadDetail", "Failed to load report detail."),
    );
    detail.value = null;
    selectedReportId.value = null;
    router.replace("/custom-report");
  } finally {
    if (seq === detailLoadSeq) detailLoading.value = false;
  }
};

const openCreateModal = () => {
  editingReportId.value = null;
  formReportName.value = "";
  showFormModal.value = true;
};

const openEditModal = (report) => {
  editingReportId.value = report.id;
  formReportName.value = report.name || "";
  showFormModal.value = true;
};

const closeFormModal = () => {
  showFormModal.value = false;
  formReportName.value = "";
  editingReportId.value = null;
};

const saveReport = async () => {
  const name = formReportName.value.trim();
  if (!name || saving.value) return;

  saving.value = true;
  try {
    if (editingReportId.value) {
      const response = await customReportApi.updateReport(
        editingReportId.value,
        { name },
      );
      if (response.success) {
        closeFormModal();
        await loadReports();
      }
    } else {
      const response = await customReportApi.createReport({ name });
      if (response.success) {
        const created = response.data || {};
        closeFormModal();
        selectedReportId.value = null;
        detail.value = null;
        if (created.id) {
          reports.value = [
            {
              id: created.id,
              name: created.name || name,
              createdBy: created.createdBy,
              createdByName:
                authStore.user?.fullName ||
                authStore.user?.username ||
                created.createdBy,
              createdAt: created.createdAt,
              widgetCount: 0,
            },
            ...reports.value.filter((r) => r.id !== created.id),
          ];
        }
        await loadReports();
      }
    }
  } catch (err) {
    alert(
      err.response?.data?.message ||
        err.message ||
        t("common_unknownError", "Unknown error"),
    );
  } finally {
    saving.value = false;
  }
};

const handleDelete = (report) => {
  deleteTarget.value = {
    type: "report",
    id: report.id,
    name: report?.name || "",
  };
  showDeleteModal.value = true;
};

const formatDateTime = (value) => {
  if (!value) return "-";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date
    .toLocaleString("en-US", {
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
      hour: "2-digit",
      minute: "2-digit",
      second: "2-digit",
      hour12: false,
    })
    .replace(",", "");
};

const reportIdFromRoute = () => {
  if (route.name === "custom-report-widget") return "";
  const paramId = route.params.reportId;
  if (paramId) return String(Array.isArray(paramId) ? paramId[0] : paramId);
  const queryId = route.query.reportId;
  if (queryId) return String(Array.isArray(queryId) ? queryId[0] : queryId);
  return "";
};

const routeReportId = computed(() => reportIdFromRoute());

watch(
  () => [route.name, routeReportId.value, String(route.query.reportId || "")],
  () => {
    if (!hasReadonlyPermission.value) {
      loading.value = false;
      detailLoading.value = false;
      return;
    }
    const queryId = route.query.reportId
      ? String(
          Array.isArray(route.query.reportId)
            ? route.query.reportId[0]
            : route.query.reportId,
        )
      : "";
    if (route.name === "custom-report" && queryId) {
      router.replace(`/custom-report/${queryId}`);
      return;
    }
    const id = routeReportId.value;
    if (id) {
      loadReportDetail(id);
      return;
    }
    detailLoadSeq += 1;
    selectedReportId.value = null;
    detail.value = null;
    detailLoading.value = false;
    loadReports();
  },
  { immediate: true },
);
</script>

<style scoped>
.custom-report-page {
  padding: 40px 20px;
  max-width: 1400px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 2px solid var(--color-border);
  gap: 16px;
}

.page-title {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.page-title h1 {
  margin: 0 0 6px;
  font-size: 28px;
  color: var(--color-ink);
}

.page-title p {
  margin: 0;
  color: var(--color-muted);
  font-size: 14px;
  display: block;
}

.page-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.btn-back {
  border: 2px solid var(--color-border);
  background: var(--color-surface);
  width: 40px;
  height: 40px;
  border-radius: var(--radius-md);
  cursor: pointer;
  color: var(--color-text);
}

.btn-back:hover {
  border-color: var(--color-brand);
  color: var(--color-brand);
}

.loading-container,
.error-container {
  text-align: center;
  padding: 60px 20px;
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.loading-container i,
.error-container i {
  font-size: 48px;
  margin-bottom: 20px;
}

.loading-container i {
  color: var(--color-brand);
}

.error-container i {
  color: var(--color-danger);
}

.transaction-table-container {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

.table-header {
  padding: 25px 30px;
  background: var(--color-surface-soft);
  border-bottom: 2px solid var(--color-border);
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 15px;
}

.rows-selector {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  color: var(--color-text);
  margin-left: auto;
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
  transition: all 0.3s ease;
}

.rows-selector select:hover {
  border-color: var(--color-brand);
}

.rows-selector select:focus {
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.table-header h2 {
  font-size: 18px;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 0;
}

.table-header h2 i {
  color: var(--color-brand);
}

.search-box {
  position: relative;
}

.table-controls {
  display: flex;
  align-items: center;
  gap: 12px;
}

.search-box input,
.modal-input {
  padding: 10px 40px 10px 15px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  outline: none;
  min-width: 250px;
}

.search-box input:focus,
.modal-input:focus {
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.search-icon {
  position: absolute;
  right: 15px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-faint);
}

.transaction-table {
  width: 100%;
  border-collapse: collapse;
}

.transaction-table thead {
  background: var(--color-surface-soft);
}

.transaction-table th {
  padding: 16px 20px;
  text-align: left;
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 2px solid var(--color-border);
}

.transaction-table td {
  padding: 16px 20px;
  font-size: 14px;
  color: var(--color-text);
  border-bottom: 1px solid var(--color-border);
}

.transaction-table tbody tr:hover {
  background: var(--color-surface-soft);
}

.btn-select {
  background: #4299e1;
  color: white;
  border: none;
  border-radius: var(--radius-sm);
  padding: 8px 14px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  letter-spacing: 0.4px;
}

.btn-select:hover {
  background: var(--color-info-solid);
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

.btn-duplicate {
  background: var(--color-success-solid);
  color: white;
}

.btn-duplicate:hover:not(:disabled) {
  background: var(--color-success-solid);
}

.btn-duplicate:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-edit {
  background: var(--color-brand-solid);
  color: white;
}

.btn-edit:hover {
  background: var(--color-brand-strong);
}

.btn-delete {
  background: var(--color-danger-solid);
  color: white;
}

.btn-delete:hover {
  background: var(--color-danger-solid);
}

.empty-state {
  text-align: center;
  padding: 60px 20px !important;
}

.empty-state i {
  font-size: 48px;
  margin-bottom: 15px;
  display: block;
  color: var(--color-border-strong);
}

.btn {
  padding: 12px 24px;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-primary {
  background: var(--color-brand-solid);
  color: white;
}

.btn-secondary {
  background: var(--color-border);
  color: var(--color-text);
}

.btn-danger {
  background: var(--color-danger-solid);
  color: white;
}

.btn-danger:hover:not(:disabled) {
  background: var(--color-danger-solid);
}

.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
}

.modal-card {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: 28px;
  width: 100%;
  max-width: 460px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.modal-card h3 {
  margin: 0 0 8px;
  color: var(--color-ink);
}

.modal-card p {
  margin: 0 0 16px;
  color: var(--color-muted);
  font-size: 14px;
}

.modal-input {
  width: 100%;
  min-width: 0;
  box-sizing: border-box;
  margin-bottom: 20px;
  padding-right: 15px;
}

.modal-label {
  display: block;
  margin: 0 0 8px;
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text);
}

.modal-select {
  appearance: auto;
  padding-right: 15px;
  margin-bottom: 16px;
  background: var(--color-surface);
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

@media (max-width: 768px) {
  .custom-report-page {
    padding: 20px 15px;
  }

  .page-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .search-box input {
    min-width: 0;
    width: 100%;
  }
}
</style>
