<template>
  <div
    class="report-detail-content"
    :style="
      containerWidth ? { '--report-detail-width': containerWidth + 'px' } : null
    "
  >
    <SalesBasicInfoCard
      v-if="row.salesId"
      :sales-id="row.salesId"
      :sales-name="row.salesName || ''"
      :sales-email="row.salesEmail || ''"
      :join-date="row.joinDate || ''"
    />
    <div v-for="panel in panels" :key="panel.id" class="report-detail-panel">
      <div class="report-detail-panel-header">
        <h3>
          <i class="fas fa-table"></i>
          {{ panel.title }}
          <span class="report-detail-panel-count">
            ({{ panelState(rowKeyVal, panel.id).total }})
          </span>
        </h3>
        <div class="report-detail-search-row">
          <input
            :value="panelState(rowKeyVal, panel.id).search"
            type="text"
            class="report-detail-search-input"
            :placeholder="t('customReport_search_placeholder', 'Search...')"
            @input="$emit('search-input', panel, $event.target.value)"
            @keyup.enter="$emit('search', panel)"
          />
          <button
            type="button"
            class="report-detail-search-btn"
            @click="$emit('search', panel)"
          >
            <i class="fas fa-search"></i> {{ t("common_search", "Search") }}
          </button>
        </div>
      </div>
      <div
        v-if="panelState(rowKeyVal, panel.id).loading"
        class="report-detail-loading"
      >
        {{ t("customReport_loading", "Loading...") }}
      </div>
      <div v-else class="report-detail-table-scroll">
        <table class="report-detail-table">
          <thead>
            <tr>
              <th
                v-for="field in visibleDetailFields(panel)"
                :key="field.columnName"
              >
                {{ field.displayName }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(child, childIndex) in panelState(rowKeyVal, panel.id)
                .items"
              :key="`${panel.id}-${childIndex}`"
            >
              <td
                v-for="field in visibleDetailFields(panel)"
                :key="field.columnName"
              >
                <span
                  v-if="
                    isStatusField(field.columnName) &&
                    hasCellValue(child[field.columnName])
                  "
                  class="status-badge"
                  :class="statusBadgeClass(child[field.columnName])"
                  >{{ getStatusLabel(child[field.columnName]) }}</span
                >
                <template v-else>{{
                  formatDetailCell(child[field.columnName], field)
                }}</template>
              </td>
            </tr>
            <tr v-if="!panelState(rowKeyVal, panel.id).items.length">
              <td
                :colspan="Math.max(visibleDetailFields(panel).length, 1)"
                class="report-detail-empty"
              >
                {{ t("customReport_detailEmpty", "No rows.") }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div
        v-if="panelState(rowKeyVal, panel.id).total > 0"
        class="report-detail-pagination"
      >
        <div class="report-detail-pagination-rows">
          <label class="report-detail-pagination-label">{{
            t("salesList_showRows", "Show rows")
          }}</label>
          <select
            class="report-detail-pagination-select"
            :value="panelState(rowKeyVal, panel.id).perPage"
            @change="$emit('per-page-change', panel, $event.target.value)"
          >
            <option :value="5">{{ t("salesList_rows_5", "5 rows") }}</option>
            <option :value="10">{{ t("salesList_rows_10", "10 rows") }}</option>
            <option :value="20">{{ t("salesList_rows_20", "20 rows") }}</option>
            <option value="all">{{ t("salesList_rows_all", "All") }}</option>
          </select>
        </div>
        <span class="report-detail-pagination-info">
          {{ detailPaginationInfo(panelState(rowKeyVal, panel.id)) }}
        </span>
        <div class="report-detail-pagination-btns">
          <button
            type="button"
            class="report-detail-page-btn"
            :disabled="panelState(rowKeyVal, panel.id).page <= 1"
            @click="
              $emit(
                'page-change',
                panel,
                panelState(rowKeyVal, panel.id).page - 1,
              )
            "
          >
            <i class="fas fa-chevron-left"></i>
            {{ t("ibIr_pagination_prev", "Prev") }}
          </button>
          <span class="report-detail-pagination-page">
            {{
              tParams(
                "salesList_pagination_pageOf",
                "Page {current} of {total}",
                {
                  current: panelState(rowKeyVal, panel.id).page,
                  total: panelState(rowKeyVal, panel.id).totalPages || 1,
                },
              )
            }}
          </span>
          <button
            type="button"
            class="report-detail-page-btn"
            :disabled="
              panelState(rowKeyVal, panel.id).page >=
              (panelState(rowKeyVal, panel.id).totalPages || 1)
            "
            @click="
              $emit(
                'page-change',
                panel,
                panelState(rowKeyVal, panel.id).page + 1,
              )
            "
          >
            {{ t("ibIr_pagination_next", "Next") }}
            <i class="fas fa-chevron-right"></i>
          </button>
        </div>
      </div>
    </div>
    <SalesNetworkGraph
      v-if="row.salesId"
      :key="row.salesId"
      :sales-id="row.salesId"
      :sales-name="row.salesName || ''"
      :client-total="row.totalClients"
    />
  </div>
</template>

<script setup>
import SalesNetworkGraph from "@/components/sales/SalesNetworkGraph.vue";
import SalesBasicInfoCard from "@/components/sales/SalesBasicInfoCard.vue";
import { useAdminI18n } from "@/composables/useAdminI18n";

defineProps({
  row: { type: Object, required: true },
  rowKeyVal: { type: String, required: true },
  panels: { type: Array, required: true },
  containerWidth: { type: Number, default: 0 },
  panelState: { type: Function, required: true },
  visibleDetailFields: { type: Function, required: true },
  formatDetailCell: { type: Function, required: true },
  isStatusField: { type: Function, required: true },
  hasCellValue: { type: Function, required: true },
  statusBadgeClass: { type: Function, required: true },
  getStatusLabel: { type: Function, required: true },
  detailPaginationInfo: { type: Function, required: true },
});

defineEmits(["search-input", "search", "page-change", "per-page-change"]);

const { t, tParams } = useAdminI18n();
</script>

<style scoped>
.report-detail-content {
  position: sticky;
  left: 0;
  box-sizing: border-box;
  width: var(--report-detail-width, 100%);
  max-width: var(--report-detail-width, 100%);
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 20px;
  overflow: hidden;
}

.report-detail-panel {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  overflow: hidden;
  min-width: 0;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.report-detail-panel-header {
  padding: 16px 20px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}

.report-detail-panel-header h3 {
  font-size: 16px;
  color: var(--color-ink);
  font-weight: 700;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 10px;
}

.report-detail-panel-header h3 i {
  color: var(--color-brand);
}

.report-detail-panel-count {
  font-weight: 600;
  color: var(--color-muted);
}

.report-detail-search-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.report-detail-search-input {
  padding: 8px 14px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  min-width: 200px;
}

.report-detail-search-input:focus {
  outline: none;
  border-color: var(--color-brand);
}

.report-detail-search-btn {
  padding: 8px 16px;
  border: none;
  border-radius: var(--radius-md);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  background: var(--color-brand-solid);
  color: white;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.report-detail-search-btn:hover {
  background: var(--color-brand-strong);
}

.report-detail-loading,
.report-detail-empty {
  padding: 20px;
  text-align: center;
  color: var(--color-muted);
  font-size: 14px;
}

.report-detail-table-scroll {
  overflow-x: auto;
  width: 100%;
}

.report-detail-table {
  width: 100%;
  min-width: max-content;
  border-collapse: collapse;
}

.report-detail-table thead {
  background: var(--color-surface-soft);
}

.report-detail-table th {
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

.report-detail-table td {
  padding: 16px 20px;
  font-size: 14px;
  color: var(--color-text);
  white-space: nowrap;
}

.report-detail-table tbody tr {
  border-bottom: 1px solid var(--color-border);
}

.report-detail-pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  padding: 12px 20px;
  background: var(--color-surface-soft);
  border-top: 1px solid var(--color-border);
  font-size: 14px;
  color: var(--color-text);
}

.report-detail-pagination-rows {
  display: flex;
  align-items: center;
  gap: 10px;
}

.report-detail-pagination-label {
  margin: 0;
}

.report-detail-pagination-select {
  padding: 6px 10px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  font-size: 14px;
  cursor: pointer;
}

.report-detail-pagination-info {
  font-size: 14px;
  color: var(--color-text);
}

.report-detail-pagination-btns {
  display: flex;
  align-items: center;
  gap: 12px;
}

.report-detail-pagination-page {
  font-size: 14px;
  color: var(--color-text);
}

.report-detail-page-btn {
  padding: 8px 14px;
  font-size: 13px;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-border);
  color: var(--color-text);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.report-detail-page-btn:hover:not(:disabled) {
  background: var(--color-brand-solid);
  color: white;
}

.report-detail-page-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: var(--radius-lg);
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
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
