<template>
  <section class="transaction-table-container stack-panel">
    <div class="table-header">
      <div class="table-header-main">
        <h2>
          <i class="fas fa-list"></i>
          {{ title }}
        </h2>
        <button
          v-if="hasExportPermission"
          type="button"
          class="btn-export-all"
          :disabled="exportDisabled || loading"
          @click="requestExportAll"
        >
          <i class="fas fa-download"></i>
          {{ t("customReport_exportAll", "Export All") }}
        </button>
      </div>
      <div class="table-controls">
        <div class="rows-selector" v-if="totalPages > 1 || total > 0">
          <label>{{ t("customReport_show", "Show") }}</label>
          <select v-model.number="perPage" @change="changePerPage">
            <option :value="5">5</option>
            <option :value="10">10</option>
            <option :value="20">20</option>
          </select>
        </div>
        <div class="filter-control" ref="columnsControlRef">
          <button
            type="button"
            class="filter-trigger edit-columns-trigger"
            :class="{ active: showColumns }"
            @click.stop="toggleColumnsPanel"
          >
            <i class="fas fa-columns"></i>
            <span>{{ t("customReport_editColumns", "Edit columns") }}</span>
          </button>
          <div
            v-if="showColumns"
            class="filter-panel filter-panel-wide"
            @click.stop
          >
            <div class="filter-panel-header">
              <span>{{ t("customReport_column", "Column") }}</span>
              <button
                type="button"
                class="filter-icon-btn"
                @click="closeColumnsPanel"
              >
                <i class="fas fa-times"></i>
              </button>
            </div>
            <div class="filter-property-search">
              <i class="fas fa-search"></i>
              <input
                v-model="columnSearchQuery"
                type="text"
                :placeholder="t('customReport_filterBy', 'Filter by...')"
              />
            </div>
            <div class="column-toggle-list">
              <label class="column-toggle-item column-toggle-all">
                <input
                  type="checkbox"
                  :checked="allColumnsVisible"
                  :indeterminate.prop="someColumnsVisible && !allColumnsVisible"
                  @change="toggleAllColumns($event.target.checked)"
                />
                <span>{{
                  t("customReport_toggleAllColumns", "Toggle all")
                }}</span>
              </label>
              <label
                v-for="col in filteredColumnToggleColumns"
                :key="col.field"
                class="column-toggle-item"
                :class="{
                  'is-dragging': dragColumnField === col.field,
                  'drag-over':
                    dragOverField === col.field &&
                    dragColumnField !== col.field,
                  'is-last-visible': isLastVisibleColumn(col.field),
                }"
                @dragover.prevent="onColumnDragOver(col.field)"
                @drop.prevent="onColumnDrop(col.field)"
              >
                <span
                  class="column-drag-handle"
                  draggable="true"
                  :title="t('customReport_dragColumn', 'Drag to reorder')"
                  @dragstart.stop="onColumnDragStart($event, col.field)"
                  @dragend.stop="onColumnDragEnd"
                  @click.prevent
                >
                  <i class="fas fa-grip-vertical"></i>
                </span>
                <input
                  type="checkbox"
                  :checked="visible[col.field] !== false"
                  :disabled="isLastVisibleColumn(col.field)"
                  @change="toggleColumn(col.field, $event.target.checked)"
                />
                <span class="filter-property-icon">{{ columnIcon(col) }}</span>
                <span>{{ col.label }}</span>
              </label>
              <p
                v-if="filteredColumnToggleColumns.length === 0"
                class="filter-empty-hint"
              >
                {{ t("customReport_noColumns", "No columns found.") }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div :class="['bulk-actions', { show: selectedRowIds.length > 0 }]">
      <div class="bulk-actions-left">
        <span class="bulk-actions-label">{{
          t("fundingReport_bulk_selected")
        }}</span>
        <span class="bulk-actions-count">{{ selectedRowIds.length }}</span>
      </div>
      <button
        v-if="hasExportPermission"
        type="button"
        class="btn-bulk btn-bulk-export"
        :disabled="exportDisabled"
        @click.stop="toggleExportSelectedDropdown"
      >
        <i class="fas fa-download"></i>
        {{ t("customReport_exportSelected", "Export Selected") }}
        <div :class="['export-dropdown', { show: showExportDropdown }]">
          <div
            class="export-option csv"
            @click.stop="handleExportSelected('csv')"
          >
            <i class="fas fa-file-csv"></i>
            <span>{{ t("fundingReport_export_csv") }}</span>
          </div>
          <div
            class="export-option excel"
            @click.stop="handleExportSelected('excel')"
          >
            <i class="fas fa-file-excel"></i>
            <span>{{ t("fundingReport_export_excel") }}</span>
          </div>
        </div>
      </button>
    </div>

    <div class="table-scroll">
      <div v-if="loading" class="stack-loading">
        <i class="fas fa-spinner fa-spin"></i>
        <p>{{ t("customReport_loading", "Loading...") }}</p>
      </div>
      <table v-else class="transaction-table">
        <thead>
          <tr>
            <th class="checkbox-col">
              <label class="custom-checkbox">
                <input
                  type="checkbox"
                  :checked="isAllSelected"
                  :indeterminate.prop="isIndeterminate"
                  @change="toggleSelectAllRows"
                />
                <span class="checkbox-checkmark"></span>
              </label>
            </th>
            <th
              v-for="col in visibleColumns"
              :key="col.field"
              class="sortable"
              :class="{ 'is-filtered': isFiltered(col.field) }"
            >
              <span class="th-text">{{ col.label }}</span>
              <button
                type="button"
                class="th-filter-btn"
                :class="{
                  active: isFiltered(col.field) || headerField === col.field,
                }"
                @click.stop="toggleHeaderFilter($event, col.field)"
              >
                <i class="fas fa-filter"></i>
              </button>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!rows.length">
            <td
              :colspan="Math.max(visibleColumns.length, 1) + 1"
              class="empty-state"
            >
              <i class="fas fa-inbox"></i>
              <p>{{ t("fundingReport_empty", "No data") }}</p>
            </td>
          </tr>
          <tr v-for="(row, index) in rows" :key="`${title}-${index}`">
            <td class="checkbox-col">
              <label class="custom-checkbox">
                <input
                  type="checkbox"
                  :value="rowKey(row, index)"
                  v-model="selectedRowIds"
                />
                <span class="checkbox-checkmark"></span>
              </label>
            </td>
            <td
              v-for="col in visibleColumns"
              :key="col.field"
              class="cell-clip"
              :title="displayCell(row[col.field])"
            >
              <span
                v-if="isStatusField(col.field) && hasCellValue(row[col.field])"
                class="status-badge"
                :class="statusBadgeClass(row[col.field])"
                >{{ statusLabel(row[col.field]) }}</span
              >
              <template v-else>{{ displayCell(row[col.field]) }}</template>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="pagination" v-if="totalPages > 1">
      <div class="pagination-info">
        {{
          tParams(
            "fundingReport_pagination_range",
            "Showing {from}–{to} of {total} transactions",
            {
              from: (page - 1) * perPage + 1,
              to: Math.min(page * perPage, total),
              total,
            },
          )
        }}
      </div>
      <div class="pagination-controls">
        <button
          type="button"
          class="pagination-btn"
          :disabled="page <= 1"
          @click="changePage(page - 1)"
        >
          <i class="fas fa-chevron-left"></i>
        </button>
        <button
          type="button"
          class="pagination-btn"
          :disabled="page >= totalPages"
          @click="changePage(page + 1)"
        >
          <i class="fas fa-chevron-right"></i>
        </button>
      </div>
    </div>

    <div
      v-if="headerField"
      ref="headerPanelRef"
      class="header-filter-panel"
      :style="headerPanelStyle"
      @click.stop
    >
      <div class="filter-panel-header">
        <span>{{ columnLabel(headerField) }}</span>
      </div>
      <div class="header-filter-sorts">
        <button type="button" class="filter-link-btn" @click="applySort('asc')">
          {{ t("customReport_sortAsc", "Ascending") }}
        </button>
        <button
          type="button"
          class="filter-link-btn"
          @click="applySort('desc')"
        >
          {{ t("customReport_sortDesc", "Descending") }}
        </button>
      </div>
      <div class="filter-property-search">
        <i class="fas fa-search"></i>
        <input
          v-model="headerSearch"
          type="text"
          :placeholder="t('customReport_filterBy', 'Filter by...')"
          @input="onHeaderSearchInput"
        />
      </div>
      <div class="header-filter-values">
        <label class="header-filter-value-row">
          <input
            type="checkbox"
            :checked="allSelected"
            :indeterminate.prop="someSelected && !allSelected"
            @change="toggleSelectAll($event.target.checked)"
          />
          <span>{{ t("customReport_selectAll", "(Select all)") }}</span>
        </label>
        <p v-if="headerLoading" class="filter-empty-hint">
          {{ t("common_loading", "Loading...") }}
        </p>
        <p v-else-if="!headerValues.length" class="filter-empty-hint">
          {{ t("customReport_noValues", "No values") }}
        </p>
        <label
          v-for="value in headerValues"
          :key="`${headerField}-${value}`"
          class="header-filter-value-row"
        >
          <input
            type="checkbox"
            :checked="headerSelected.includes(value)"
            @change="toggleValue(value, $event.target.checked)"
          />
          <span>{{ value }}</span>
        </label>
      </div>
      <div class="header-filter-footer">
        <button
          type="button"
          class="filter-link-btn danger"
          @click="clearHeaderFilter"
        >
          {{ t("customReport_clearFilter", "Clear") }}
        </button>
        <button
          type="button"
          class="btn btn-primary"
          @click="applyHeaderFilter"
        >
          {{ t("customReport_apply", "Apply") }}
        </button>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import customReportApi from "@/services/customReportApi";
import { useAdminI18n } from "@/composables/useAdminI18n";

const props = defineProps({
  dataSourceId: { type: String, required: true },
  title: { type: String, required: true },
  defaultVisible: { type: Array, default: () => [] },
  sharedFilters: { type: Array, default: () => [] },
  sharedSearch: { type: String, default: "" },
  hasExportPermission: { type: Boolean, default: false },
  exportDisabled: { type: Boolean, default: false },
});

const emit = defineEmits(["total", "export-all", "export-selected"]);

const { t, tParams } = useAdminI18n();

const fields = ref([]);
const rows = ref([]);
const loading = ref(true);
const page = ref(1);
const perPage = ref(10);
const total = ref(0);
const totalPages = ref(0);
const visible = ref({});
const columnOrder = ref([]);
const showColumns = ref(false);
const columnSearchQuery = ref("");
const columnsControlRef = ref(null);
const dragColumnField = ref("");
const dragOverField = ref("");
const filters = ref([]);
const sortField = ref("");
const sortDirection = ref("desc");

const headerField = ref("");
const headerPanelRef = ref(null);
const headerSearch = ref("");
const headerValues = ref([]);
const headerSelected = ref([]);
const headerLoading = ref(false);
const headerPanelStyle = ref({ top: "0px", left: "0px" });
let headerSearchTimer = null;

const selectedRowIds = ref([]);
const showExportDropdown = ref(false);

const isAllSelected = computed(
  () =>
    rows.value.length > 0 && selectedRowIds.value.length === rows.value.length,
);

const isIndeterminate = computed(
  () =>
    selectedRowIds.value.length > 0 &&
    selectedRowIds.value.length < rows.value.length,
);

const rowIdentityPart = (value) => {
  if (value === undefined || value === null || value === "") return "";
  return String(value);
};

const rowFingerprint = (row) => {
  if (!row || typeof row !== "object") return "";
  return Object.keys(row)
    .sort()
    .map((key) => `${key}=${row[key]}`)
    .join("\u0001");
};

const rowKey = (row, index) => {
  const id = rowIdentityPart(row?.id);
  if (id) return id;
  const transactionId = rowIdentityPart(row?.transactionId);
  if (transactionId) return transactionId;
  const accountId = rowIdentityPart(row?.accountId);
  if (accountId) return `account-${accountId}`;
  const salesId = rowIdentityPart(row?.salesId);
  if (salesId) return `sales-${salesId}`;
  const clientId = rowIdentityPart(row?.clientId);
  if (clientId) return `client-${clientId}`;
  const fingerprint = rowFingerprint(row);
  if (fingerprint) return `row-${fingerprint}`;
  return `row-${index}`;
};

const retainVisibleSelectedIds = (nextRows) => {
  const selected = selectedRowIds.value;
  if (!selected.length) return [];
  const visibleKeys = new Set(nextRows.map((row, index) => rowKey(row, index)));
  return selected.filter((id) => visibleKeys.has(id));
};

const toggleSelectAllRows = () => {
  if (isAllSelected.value) {
    selectedRowIds.value = [];
    return;
  }
  selectedRowIds.value = rows.value.map((row, index) => rowKey(row, index));
};

const toggleExportSelectedDropdown = () => {
  if (props.exportDisabled) return;
  showExportDropdown.value = !showExportDropdown.value;
};

const exportColumnDefs = () =>
  visibleColumns.value.map((col) => ({ field: col.field, label: col.label }));

const getExportCellValue = (row, field) => row[field] ?? "";

const selectedExportRows = () => {
  const selected = new Set(selectedRowIds.value);
  const cols = visibleColumns.value;
  return rows.value
    .filter((row, index) => selected.has(rowKey(row, index)))
    .map((row) => {
      const out = {};
      cols.forEach((col) => {
        out[col.field] = getExportCellValue(row, col.field);
      });
      return out;
    });
};

const handleExportSelected = (format) => {
  showExportDropdown.value = false;
  if (!props.hasExportPermission || props.exportDisabled) return;
  if (format !== "csv" && format !== "excel") return;
  const exportedRows = selectedExportRows();
  if (!exportedRows.length) {
    alert(
      t("fundingReport_alert_exportFail", "Failed to export: no rows selected"),
    );
    return;
  }
  emit("export-selected", {
    dataSourceId: props.dataSourceId,
    title: props.title,
    columns: exportColumnDefs(),
    rows: exportedRows,
  });
};

const requestExportAll = () => {
  if (!props.hasExportPermission || props.exportDisabled) return;
  showExportDropdown.value = false;
  emit("export-all", {
    dataSourceId: props.dataSourceId,
    title: props.title,
    allColumns: columns.value.map((col) => ({
      field: col.field,
      label: col.label,
      icon: columnIcon(col),
    })),
    visibleFields: visibleColumns.value.map((col) => col.field),
    search: props.sharedSearch,
    filters: buildFilterPayload(),
    sorts: sortField.value
      ? [
          {
            field: sortField.value,
            direction: sortDirection.value === "asc" ? "asc" : "desc",
          },
        ]
      : [],
  });
};

const columns = computed(() => {
  const byName = Object.fromEntries(
    fields.value.map((field) => [
      field.columnName,
      {
        field: field.columnName,
        label: field.displayName || field.columnName,
        type: fieldType(field),
      },
    ]),
  );
  const order = columnOrder.value.length
    ? columnOrder.value
    : fields.value.map((field) => field.columnName);
  return order.map((name) => byName[name]).filter(Boolean);
});

const visibleColumns = computed(() =>
  columns.value.filter((col) => visible.value[col.field] !== false),
);

const visibleCount = computed(
  () => Object.values(visible.value).filter(Boolean).length,
);

const allColumnsVisible = computed(
  () =>
    columns.value.length > 0 &&
    columns.value.every((col) => visible.value[col.field] !== false),
);

const someColumnsVisible = computed(() =>
  columns.value.some((col) => visible.value[col.field] !== false),
);

const filteredColumnToggleColumns = computed(() => {
  const q = columnSearchQuery.value.trim().toLowerCase();
  if (!q) return columns.value;
  return columns.value.filter(
    (col) =>
      col.label.toLowerCase().includes(q) ||
      col.field.toLowerCase().includes(q),
  );
});

const isLastVisibleColumn = (field) =>
  visible.value[field] !== false && visibleCount.value <= 1;

const columnIcon = (col) => {
  if (col?.type === "number") return "#";
  if (col?.type === "date") return "◷";
  return "Aa";
};

const allSelected = computed(
  () =>
    headerValues.value.length > 0 &&
    headerValues.value.every((value) => headerSelected.value.includes(value)),
);

const someSelected = computed(() =>
  headerValues.value.some((value) => headerSelected.value.includes(value)),
);

const fieldType = (field) => {
  const role = field.fieldRole;
  const dataType = String(field.dataType || "").toLowerCase();
  if (role === "datetime" || dataType === "datetime" || dataType === "date")
    return "date";
  if (role === "measure" || ["integer", "decimal", "number"].includes(dataType))
    return "number";
  return "text";
};

const isStatusField = (field) => String(field || "").toLowerCase() === "status";
const hasCellValue = (value) =>
  value !== null && value !== undefined && value !== "";
const displayCell = (value) => (hasCellValue(value) ? String(value) : "-");

const normalizeStatusKey = (value) =>
  String(value ?? "")
    .trim()
    .toLowerCase()
    .replace(/[\s-]+/g, "_");

const STATUS_BADGE_CLASS = {
  active: "active",
  completed: "completed",
  approved: "completed",
  inactive: "inactive",
  pending: "pending",
  pending_verification: "pending",
  processing: "processing",
  suspended: "suspended",
  failed: "failed",
  rejected: "rejected",
  cancelled: "rejected",
  canceled: "rejected",
  closed: "closed",
};

const statusBadgeClass = (value) =>
  STATUS_BADGE_CLASS[normalizeStatusKey(value)] || "unknown";
const statusLabel = (value) => String(value ?? "");

const columnLabel = (field) =>
  columns.value.find((col) => col.field === field)?.label || field;

const isFiltered = (field) =>
  filters.value.some(
    (rule) =>
      rule.field === field && Array.isArray(rule.value) && rule.value.length,
  );

const buildFilterPayload = () => {
  const local = filters.value.filter(
    (rule) => Array.isArray(rule.value) && rule.value.length,
  );
  const shared = (props.sharedFilters || []).filter((rule) => {
    if (!rule?.field || !fields.value.some((f) => f.columnName === rule.field))
      return false;
    if (local.some((item) => item.field === rule.field)) return false;
    return true;
  });
  return [...shared, ...local];
};

const initVisible = () => {
  const names = fields.value.map((field) => field.columnName);
  columnOrder.value = [...names];
  const preferred = (props.defaultVisible || []).filter((name) =>
    names.includes(name),
  );
  const shown = preferred.length
    ? preferred
    : names.slice(0, Math.min(6, names.length));
  const next = {};
  names.forEach((name) => {
    next[name] = shown.includes(name);
  });
  if (!Object.values(next).some(Boolean) && names[0]) next[names[0]] = true;
  visible.value = next;
};

const toggleColumnsPanel = () => {
  showColumns.value = !showColumns.value;
  if (showColumns.value) {
    columnSearchQuery.value = "";
    closeHeaderFilter();
  }
};

const closeColumnsPanel = () => {
  showColumns.value = false;
  columnSearchQuery.value = "";
  dragColumnField.value = "";
  dragOverField.value = "";
};

const toggleColumn = (field, checked) => {
  if (!checked && isLastVisibleColumn(field)) return;
  visible.value = { ...visible.value, [field]: checked };
};

const toggleAllColumns = (checked) => {
  const next = { ...visible.value };
  columns.value.forEach((col, index) => {
    next[col.field] = checked || index === 0;
  });
  visible.value = next;
};

const onColumnDragStart = (event, field) => {
  dragColumnField.value = field;
  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = "move";
    event.dataTransfer.setData("text/plain", field);
  }
};

const onColumnDragOver = (field) => {
  if (!dragColumnField.value || dragColumnField.value === field) return;
  dragOverField.value = field;
};

const onColumnDrop = (field) => {
  const from = dragColumnField.value;
  if (!from || from === field) {
    onColumnDragEnd();
    return;
  }
  const next = [...columnOrder.value];
  const fromIndex = next.indexOf(from);
  const toIndex = next.indexOf(field);
  if (fromIndex < 0 || toIndex < 0) {
    onColumnDragEnd();
    return;
  }
  next.splice(fromIndex, 1);
  next.splice(toIndex, 0, from);
  columnOrder.value = next;
  onColumnDragEnd();
};

const onColumnDragEnd = () => {
  dragColumnField.value = "";
  dragOverField.value = "";
};

const loadMeta = async () => {
  const response = await customReportApi.getDataSource(props.dataSourceId);
  fields.value = response?.data?.fields || [];
  initVisible();
};

const loadRows = async () => {
  if (!props.dataSourceId) return;
  loading.value = true;
  try {
    const params = {
      page: page.value,
      per_page: perPage.value,
    };
    if (props.sharedSearch) params.search = props.sharedSearch;
    if (sortField.value) {
      params.sorts = JSON.stringify([
        { field: sortField.value, direction: sortDirection.value },
      ]);
      params.sort_field = sortField.value;
      params.sort_direction = sortDirection.value;
    }
    const activeFilters = buildFilterPayload();
    if (activeFilters.length) params.filters = JSON.stringify(activeFilters);

    const response = await customReportApi.getDataSourceRows(
      props.dataSourceId,
      params,
    );
    rows.value = response?.data?.items || [];
    selectedRowIds.value = retainVisibleSelectedIds(rows.value);
    const pagination = response?.data?.pagination || {};
    total.value = pagination.total || 0;
    totalPages.value = pagination.total_pages || 0;
    page.value = pagination.page || page.value;
    emit("total", total.value);
  } catch (err) {
    rows.value = [];
    selectedRowIds.value = [];
    total.value = 0;
    totalPages.value = 0;
    emit("total", 0);
    console.error(err);
  } finally {
    loading.value = false;
  }
};

const changePage = (next) => {
  page.value = next;
  loadRows();
};

const changePerPage = () => {
  page.value = 1;
  loadRows();
};

const closeHeaderFilter = () => {
  headerField.value = "";
  headerSearch.value = "";
  headerValues.value = [];
  headerSelected.value = [];
  headerLoading.value = false;
  clearTimeout(headerSearchTimer);
};

const positionHeaderPanel = (anchorEl) => {
  if (!anchorEl) return;
  const rect = anchorEl.getBoundingClientRect();
  const width = 280;
  const left = Math.min(Math.max(8, rect.left), window.innerWidth - width - 8);
  headerPanelStyle.value = {
    top: `${Math.round(rect.bottom + 6)}px`,
    left: `${Math.round(left)}px`,
    width: `${width}px`,
  };
};

const loadHeaderValues = async () => {
  const field = headerField.value;
  if (!field) return;
  headerLoading.value = true;
  try {
    const params = { field, limit: 200 };
    if (headerSearch.value.trim()) params.search = headerSearch.value.trim();
    const other = buildFilterPayload().filter((rule) => rule.field !== field);
    if (other.length) params.filters = JSON.stringify(other);
    const response = await customReportApi.getDataSourceColumnValues(
      props.dataSourceId,
      params,
    );
    if (headerField.value !== field) return;
    headerValues.value = (response?.data?.values || []).map((value) =>
      String(value),
    );
  } catch (err) {
    if (headerField.value === field) headerValues.value = [];
    console.error(err);
  } finally {
    if (headerField.value === field) headerLoading.value = false;
  }
};

const onHeaderSearchInput = () => {
  clearTimeout(headerSearchTimer);
  headerSearchTimer = setTimeout(() => loadHeaderValues(), 250);
};

const toggleHeaderFilter = async (event, field) => {
  if (headerField.value === field) {
    closeHeaderFilter();
    return;
  }
  closeColumnsPanel();
  headerField.value = field;
  headerSearch.value = "";
  const existing = filters.value.find((rule) => rule.field === field);
  headerSelected.value = existing?.value ? [...existing.value] : [];
  positionHeaderPanel(event.currentTarget);
  await loadHeaderValues();
  if (!headerSelected.value.length && headerValues.value.length) {
    headerSelected.value = [...headerValues.value];
  }
};

const toggleSelectAll = (checked) => {
  headerSelected.value = checked ? [...headerValues.value] : [];
};

const toggleValue = (value, checked) => {
  if (checked) {
    if (!headerSelected.value.includes(value)) {
      headerSelected.value = [...headerSelected.value, value];
    }
    return;
  }
  headerSelected.value = headerSelected.value.filter((item) => item !== value);
};

const applyHeaderFilter = () => {
  const field = headerField.value;
  if (!field) return;
  const selected = [...headerSelected.value];
  const allVisible = headerValues.value;
  const isAll =
    allVisible.length &&
    selected.length === allVisible.length &&
    allVisible.every((value) => selected.includes(value)) &&
    !headerSearch.value.trim();

  if (!selected.length || isAll) {
    filters.value = filters.value.filter((rule) => rule.field !== field);
  } else {
    const next = { field, op: "in", value: selected };
    const index = filters.value.findIndex((rule) => rule.field === field);
    filters.value =
      index >= 0
        ? filters.value.map((rule, i) => (i === index ? next : rule))
        : [...filters.value, next];
  }
  closeHeaderFilter();
  page.value = 1;
  loadRows();
};

const clearHeaderFilter = () => {
  const field = headerField.value;
  if (!field) return;
  filters.value = filters.value.filter((rule) => rule.field !== field);
  closeHeaderFilter();
  page.value = 1;
  loadRows();
};

const applySort = (direction) => {
  const field = headerField.value;
  if (!field) return;
  sortField.value = field;
  sortDirection.value = direction;
  page.value = 1;
  loadRows();
};

const onDocumentClick = (event) => {
  if (showExportDropdown.value) {
    showExportDropdown.value = false;
  }
  if (showColumns.value) {
    const el = columnsControlRef.value;
    if (el && !el.contains(event.target)) {
      closeColumnsPanel();
    }
  }
  if (!headerField.value) return;
  const panel = headerPanelRef.value;
  const onBtn = event.target?.closest?.(".th-filter-btn");
  if (panel && !panel.contains(event.target) && !onBtn) {
    closeHeaderFilter();
  }
};

watch(
  () => [props.sharedSearch, JSON.stringify(props.sharedFilters || [])],
  () => {
    page.value = 1;
    loadRows();
  },
);

onMounted(async () => {
  document.addEventListener("click", onDocumentClick);
  try {
    await loadMeta();
    await loadRows();
  } catch (err) {
    loading.value = false;
    console.error(err);
  }
});

onUnmounted(() => {
  document.removeEventListener("click", onDocumentClick);
  clearTimeout(headerSearchTimer);
});
</script>

<style scoped>
.transaction-table-container {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  overflow: visible;
  position: relative;
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

.table-header-main {
  display: flex;
  align-items: center;
  min-width: 0;
  flex: 1;
  gap: 16px;
}

.btn-export-all {
  margin-left: auto;
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-brand-solid);
  color: #fff;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-export-all:hover:not(:disabled) {
  background: var(--color-brand-strong);
}

.btn-export-all:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.bulk-actions {
  display: none;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  width: 100%;
  box-sizing: border-box;
  padding: 10px 30px;
  background: var(--color-brand-soft);
  border-bottom: 2px solid var(--color-brand);
  margin: 0;
  border-radius: 0;
  position: relative;
  z-index: 30;
}

.bulk-actions.show {
  display: flex;
}

.bulk-actions-left {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
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
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  position: relative;
  flex-shrink: 0;
  margin-left: auto;
}

.btn-bulk-export {
  background: var(--color-brand-solid);
  color: white;
}

.btn-bulk-export:hover:not(:disabled) {
  background: var(--color-brand-strong);
  transform: translateY(-1px);
}

.btn-bulk-export:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.export-dropdown {
  position: absolute;
  top: calc(100% + 5px);
  right: 0;
  left: auto;
  background: var(--color-surface);
  border-radius: var(--radius-md);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  min-width: 150px;
  display: none;
  z-index: 50;
  overflow: hidden;
  isolation: isolate;
}

.export-dropdown.show {
  display: block;
}

.export-option {
  padding: 10px 15px;
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--color-text);
  cursor: pointer;
  transition: all 0.2s ease;
  font-size: 14px;
  font-weight: 600;
  border-bottom: 1px solid var(--color-border);
  background: var(--color-surface);
  position: relative;
  z-index: 1;
}

.export-option:last-child {
  border-bottom: none;
}

.export-option:hover {
  background: var(--color-surface-soft);
  color: var(--color-brand);
}

.export-option.csv:hover {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.export-option.excel:hover {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.checkbox-col {
  width: 50px;
  text-align: center;
  padding: 16px 10px !important;
}

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
  transition: all 0.3s ease;
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

.table-controls {
  display: flex;
  align-items: center;
  gap: 12px;
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
  transition: all 0.3s ease;
}

.rows-selector select:hover,
.rows-selector select:focus {
  border-color: var(--color-brand);
}

.rows-selector select:focus {
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.filter-trigger {
  position: relative;
  width: 40px;
  height: 40px;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-brand-solid);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
  color: #ffffff;
}

.filter-trigger:hover,
.filter-trigger.active {
  opacity: 0.92;
}

.edit-columns-trigger {
  width: auto;
  min-width: 40px;
  padding: 0 12px;
  gap: 8px;
  font-size: 14px;
  font-weight: 600;
}

.filter-control {
  position: relative;
}

.filter-panel {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  width: 320px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: 0 12px 32px rgba(15, 23, 42, 0.16);
  z-index: 40;
  overflow: hidden;
  font-size: 14px;
}

.filter-panel.filter-panel-wide {
  width: 360px;
}

.filter-panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 12px 12px 8px;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
}

.filter-icon-btn {
  width: 28px;
  height: 28px;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-muted);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.filter-icon-btn:hover {
  background: var(--color-surface-muted);
  color: var(--color-ink);
}

.filter-property-search {
  position: relative;
  margin: 0 12px 8px;
}

.filter-property-search i {
  position: absolute;
  left: 10px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-faint);
  font-size: 14px;
}

.filter-property-search input {
  width: 100%;
  box-sizing: border-box;
  padding: 8px 10px 8px 30px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: 14px;
  outline: none;
}

.filter-property-search input:focus {
  border-color: var(--color-brand);
}

.column-toggle-list {
  padding: 4px 8px 12px;
  display: flex;
  flex-direction: column;
  gap: 2px;
  max-height: 320px;
  overflow-y: auto;
}

.column-toggle-all {
  margin-bottom: 4px;
  padding-left: 26px;
  font-weight: 600;
  border-bottom: 1px solid var(--color-surface-muted);
  border-radius: 6px 6px 0 0;
}

.column-toggle-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  border-radius: var(--radius-sm);
  cursor: pointer;
  font-size: 14px;
  color: var(--color-ink);
  border: 1px solid transparent;
}

.column-toggle-item:hover {
  background: var(--color-surface-muted);
}

.column-toggle-item.drag-over {
  border-color: var(--color-brand);
  background: var(--color-brand-soft);
}

.column-toggle-item.is-dragging {
  opacity: 0.45;
}

.column-drag-handle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 18px;
  height: 18px;
  color: var(--color-faint);
  cursor: grab;
  flex-shrink: 0;
}

.column-drag-handle:active {
  cursor: grabbing;
}

.column-drag-handle i {
  font-size: 14px;
}

.column-toggle-item:hover .column-drag-handle {
  color: var(--color-muted);
}

.column-toggle-item input {
  width: 15px;
  height: 15px;
  accent-color: var(--color-brand);
  cursor: pointer;
}

.column-toggle-item input:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.column-toggle-item.is-last-visible {
  cursor: default;
}

.filter-property-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  color: var(--color-muted);
  font-size: 14px;
  font-weight: 600;
}

.table-scroll {
  overflow: auto;
  max-height: 420px;
}

.stack-loading,
.empty-state {
  text-align: center;
  padding: 60px 20px;
  color: var(--color-faint);
}

.stack-loading i,
.empty-state i {
  font-size: 48px;
  margin-bottom: 15px;
  display: block;
  color: var(--color-border-strong);
}

.stack-loading i {
  color: var(--color-brand);
}

.transaction-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 720px;
}

.transaction-table thead {
  background: var(--color-surface-soft);
}

.transaction-table th {
  padding: 16px 20px;
  padding-right: 42px;
  text-align: left;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 2px solid var(--color-border);
  position: sticky;
  top: 0;
  z-index: 1;
  background: var(--color-surface-soft);
  white-space: nowrap;
}

.transaction-table th.sortable.is-filtered {
  color: var(--color-brand);
}

.transaction-table th .th-text {
  display: inline-block;
  max-width: 180px;
  overflow: hidden;
  text-overflow: ellipsis;
  vertical-align: middle;
}

.th-filter-btn {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  border: none;
  background: transparent;
  color: var(--color-faint);
  cursor: pointer;
  width: 22px;
  height: 22px;
  border-radius: 4px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.th-filter-btn:hover,
.th-filter-btn.active {
  color: var(--color-brand);
  background: rgba(var(--color-brand-rgb), 0.12);
}

.transaction-table td {
  padding: 16px 20px;
  font-size: 14px;
  color: var(--color-text);
  border-bottom: 1px solid var(--color-border);
}

.transaction-table td.cell-clip {
  max-width: 220px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  padding: 16px 30px;
  border-top: 1px solid var(--color-border);
  background: var(--color-surface);
}

.pagination-info {
  font-size: 14px;
  color: var(--color-muted);
}

.pagination-controls {
  display: flex;
  gap: 8px;
}

.pagination-btn {
  min-width: 36px;
  height: 36px;
  padding: 0 10px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  cursor: pointer;
  transition: all 0.2s ease;
}

.pagination-btn:hover:not(:disabled) {
  border-color: var(--color-brand);
  color: var(--color-brand);
}

.pagination-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.header-filter-panel {
  position: fixed;
  z-index: 1300;
  width: 320px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: 0 12px 32px rgba(15, 23, 42, 0.16);
  padding: 0 0 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: min(420px, calc(100vh - 24px));
  overflow: hidden;
}

.header-filter-sorts,
.header-filter-footer {
  display: flex;
  gap: 8px;
  align-items: center;
  padding: 0 12px;
}

.header-filter-footer {
  justify-content: space-between;
  padding-top: 4px;
}

.header-filter-values {
  overflow: auto;
  max-height: 220px;
  margin: 0 12px;
  border: 1px solid var(--color-surface-muted);
  border-radius: var(--radius-md);
  padding: 6px 8px;
}

.header-filter-value-row {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: var(--color-text);
  padding: 6px 4px;
  cursor: pointer;
}

.filter-link-btn {
  border: none;
  background: transparent;
  color: var(--color-brand);
  cursor: pointer;
  font-size: 14px;
  font-weight: 600;
  padding: 4px 0;
}

.filter-link-btn:hover {
  color: var(--color-brand-strong);
}

.filter-link-btn.danger {
  color: var(--color-danger);
}

.filter-link-btn.danger:hover {
  color: var(--color-danger);
}

.filter-empty-hint {
  margin: 8px 4px;
  color: var(--color-faint);
  font-size: 14px;
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

.btn-primary {
  background: var(--color-brand-solid);
  color: white;
}

.status-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 14px;
  font-weight: 600;
}

.status-badge.active,
.status-badge.completed {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.status-badge.inactive,
.status-badge.closed {
  background: var(--color-border);
  color: var(--color-text);
}

.status-badge.pending,
.status-badge.unknown {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.status-badge.processing {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.status-badge.failed,
.status-badge.rejected,
.status-badge.suspended {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}
</style>
