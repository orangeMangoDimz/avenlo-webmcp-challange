<template>
  <div class="custom-report-page ui-page workspace-report-page">
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
      <div class="page-header ui-page-header">
        <div class="page-title">
          <button class="btn-back" @click="goBack">
            <i class="fas fa-arrow-left"></i>
          </button>
          <div>
            <h1>{{ pageTitle }}</h1>
            <p>
              {{
                t(
                  "customReport_widgetDataSub",
                  "Transaction data from the selected widget.",
                )
              }}
            </p>
          </div>
        </div>
        <div class="page-actions">
          <PageHeaderActions />
        </div>
      </div>

      <ExportProgressBanner
        v-if="exportBannerVisible && exportStatusText"
        :cancelling="exportCancelling"
        :status-text="exportStatusText"
        :percent="exportProgressPercent"
        :cancel-disabled="!exportJobId"
        :title="t('ibReport_exportInProgressTitle', 'Export in progress')"
        :cancelling-title="
          t('ibReport_exportCancelling', 'Cancelling export...')
        "
        :cancel-label="t('customReport_cancel', 'Cancel')"
        @cancel-export="cancelActiveExport"
      />

      <div
        v-if="exportColumnModal.visible"
        class="modal-overlay"
        @click.self="closeExportColumnModal"
      >
        <div class="modal-card modal-card-types">
          <div class="modal-card-head">
            <h3>
              {{ t("customReport_exportColumnsTitle", "Export columns") }}
            </h3>
            <button
              type="button"
              class="filter-icon-btn"
              @click="closeExportColumnModal"
            >
              <i class="fas fa-times"></i>
            </button>
          </div>
          <p>
            {{
              t(
                "customReport_exportColumnsHint",
                "Choose whether to export every column or only the ones you pick.",
              )
            }}
          </p>
          <div class="export-column-mode-grid">
            <button
              type="button"
              class="export-column-mode-option"
              :class="{ active: exportColumnModal.mode === 'all' }"
              @click="setExportColumnMode('all')"
            >
              <span class="export-column-mode-icon"
                ><i class="fas fa-th"></i
              ></span>
              <span class="export-column-mode-copy">
                <strong>{{
                  t("customReport_exportAllColumns", "All columns")
                }}</strong>
                <small>{{
                  t(
                    "customReport_exportAllColumnsHint",
                    "Include every field from this data source.",
                  )
                }}</small>
              </span>
            </button>
            <button
              type="button"
              class="export-column-mode-option"
              :class="{ active: exportColumnModal.mode === 'specific' }"
              @click="setExportColumnMode('specific')"
            >
              <span class="export-column-mode-icon"
                ><i class="fas fa-tasks"></i
              ></span>
              <span class="export-column-mode-copy">
                <strong>{{
                  t("customReport_exportSpecificColumns", "Specific columns")
                }}</strong>
                <small>{{
                  t(
                    "customReport_exportSpecificColumnsHint",
                    "Pick which fields to include in the file.",
                  )
                }}</small>
              </span>
            </button>
          </div>
          <div
            v-if="exportColumnModal.mode === 'specific'"
            class="export-column-picker"
          >
            <div class="filter-property-search">
              <i class="fas fa-search"></i>
              <input
                v-model="exportColumnModal.search"
                type="text"
                :placeholder="t('customReport_filterBy', 'Filter by...')"
              />
            </div>
            <div class="column-toggle-list export-column-list">
              <label class="column-toggle-item column-toggle-all">
                <input
                  type="checkbox"
                  :checked="allExportColumnsChecked"
                  :indeterminate.prop="
                    someExportColumnsChecked && !allExportColumnsChecked
                  "
                  @change="toggleAllExportColumns($event.target.checked)"
                />
                <span>{{
                  t("customReport_toggleAllColumns", "Toggle all")
                }}</span>
              </label>
              <label
                v-for="col in filteredExportPickerColumns"
                :key="col.field"
                class="column-toggle-item"
              >
                <input
                  type="checkbox"
                  :checked="!!exportColumnModal.selected[col.field]"
                  @change="toggleExportColumn(col.field, $event.target.checked)"
                />
                <span class="filter-property-icon">{{ col.icon }}</span>
                <span>{{ col.label }}</span>
              </label>
              <p
                v-if="filteredExportPickerColumns.length === 0"
                class="filter-empty-hint"
              >
                {{ t("customReport_noColumns", "No columns found.") }}
              </p>
            </div>
          </div>
          <div class="modal-actions">
            <button
              type="button"
              class="btn btn-primary"
              :disabled="!canConfirmExportColumns"
              @click="confirmExportColumns"
            >
              {{ t("customReport_exportAll", "Export All") }}
            </button>
            <button
              type="button"
              class="btn btn-secondary"
              @click="closeExportColumnModal"
            >
              {{ t("customReport_cancel", "Cancel") }}
            </button>
          </div>
        </div>
      </div>

      <div
        v-if="exportModal.visible"
        class="export-modal-overlay"
        @click="onExportModalContinue"
      >
        <div class="export-modal" @click.stop>
          <div class="export-modal-header">
            <h3>
              {{ t("ibReport_exportInProgressTitle", "Export in progress") }}
            </h3>
            <button
              type="button"
              class="export-modal-close"
              @click="onExportModalContinue"
            >
              ×
            </button>
          </div>
          <div class="export-modal-body">
            <p class="export-modal-text">
              {{
                exportModal.message ||
                t(
                  "ibReport_exportInProgressMsg",
                  "Your export is running. You can continue working.",
                )
              }}
            </p>
            <div class="export-modal-progress">
              <div
                class="export-modal-progress-bar"
                :style="{ width: `${exportModal.percent || 0}%` }"
              ></div>
            </div>
            <p class="export-modal-percent">{{ exportModal.percent || 0 }}%</p>
          </div>
          <div class="export-modal-footer">
            <button
              type="button"
              class="export-modal-btn primary"
              @click="onExportModalContinue"
              :disabled="exportModal.busy"
            >
              {{ t("ibReport_exportContinue", "Continue") }}
            </button>
            <button
              type="button"
              class="export-modal-btn secondary"
              @click="onExportModalCancel"
              :disabled="exportModal.busy"
            >
              {{ t("ibReport_exportCancel", "Cancel") }}
            </button>
          </div>
        </div>
      </div>

      <div v-if="loading" class="loading-container">
        <i class="fas fa-spinner fa-spin"></i>
        <p>{{ t("customReport_loading", "Loading...") }}</p>
      </div>

      <div v-else>
        <div v-if="widgetTypes.length" class="widget-type-section">
          <div class="widget-type-bar" aria-label="Widget type">
            <div class="widget-type-presets">
              <template v-for="type in widgetTypes" :key="type.id">
                <input
                  v-if="renamingTypeId === type.id"
                  ref="renameTypeInputRef"
                  class="widget-type-btn widget-type-rename"
                  :class="{ active: activeView === type.id }"
                  :style="renamingTypeStyle"
                  v-model="renamingTypeLabel"
                  maxlength="255"
                  size="1"
                  @blur="commitRenameWidgetType"
                  @keydown.enter.prevent="commitRenameWidgetType"
                  @keydown.escape.prevent="cancelRenameWidgetType"
                  @click.stop
                />
                <button
                  v-else
                  type="button"
                  class="widget-type-btn"
                  :class="{ active: activeView === type.id }"
                  @click="onWidgetTypeClick(type, $event)"
                  @dblclick.prevent
                >
                  {{ type.label }}
                </button>
              </template>
            </div>
            <button
              type="button"
              class="widget-type-edit"
              @click="openEditWidgetTypes"
            >
              <i class="fas fa-edit"></i>
              <span>{{
                t("customReport_editWidgetType", "Edit Widget Type")
              }}</span>
            </button>
          </div>
        </div>
        <div class="transaction-table-container">
          <div v-if="!widgetTypes.length" class="chart-stage">
            <div class="chart-empty">
              <p>
                {{
                  t(
                    "customReport_noWidgetType",
                    "No widget type yet. Add one to get started.",
                  )
                }}
              </p>
              <button
                type="button"
                class="btn btn-primary"
                @click="openEditWidgetTypes"
              >
                <i class="fas fa-plus"></i>
                {{ t("customReport_addWidgetType", "Add Widget Type") }}
              </button>
            </div>
          </div>
          <div v-if="widgetTypes.length" class="table-header">
            <div class="table-header-top">
              <div class="table-header-main">
                <h2><i class="fas fa-list"></i> {{ dataSourceTitle }}</h2>
                <button
                  v-if="activeKind === 'table' && hasExportPermission"
                  type="button"
                  class="btn-export-all"
                  :disabled="isExportAllRunning || loading"
                  @click="exportAllRows"
                >
                  <i class="fas fa-download"></i>
                  {{ t("customReport_exportAll", "Export All") }}
                </button>
              </div>
              <div class="table-controls">
                <div v-if="activeKind === 'table'" class="rows-selector">
                  <label>{{ t("customReport_show", "Show") }}</label>
                  <select v-model.number="perPage" @change="changeTablePerPage">
                    <option
                      v-for="size in TABLE_PER_PAGE_OPTIONS"
                      :key="size"
                      :value="size"
                    >
                      {{ size }}
                    </option>
                  </select>
                </div>
                <div class="search-box">
                  <input
                    type="text"
                    v-model="searchQuery"
                    :placeholder="
                      t('customReport_search_placeholder', 'Search...')
                    "
                    @input="handleSearch"
                  />
                  <i class="fas fa-search search-icon"></i>
                </div>
                <button
                  v-if="activeKind === 'table'"
                  type="button"
                  class="filter-trigger edit-columns-trigger"
                  :class="{
                    active: showFilterPanel && filterPanelView === 'columns',
                  }"
                  :title="t('customReport_editColumns', 'Edit columns')"
                  @click.stop="openEditColumnsToolbar"
                >
                  <i class="fas fa-columns"></i>
                  <span>{{
                    t("customReport_editColumns", "Edit columns")
                  }}</span>
                </button>
                <div class="filter-control" ref="filterControlRef">
                  <button
                    type="button"
                    class="filter-trigger"
                    :class="{
                      active:
                        showFilterPanel || sortActive || activeFilterCount > 0,
                    }"
                    :title="t('customReport_filters', 'Filters')"
                    @click="toggleFilterPanel"
                  >
                    <span class="notion-filter-icon" aria-hidden="true">
                      <span></span>
                      <span></span>
                      <span></span>
                    </span>
                  </button>

                  <div
                    v-if="showFilterPanel"
                    class="filter-panel filter-panel-wide"
                    :class="{ 'filter-panel-tall': isFilterPanelTall }"
                    @click.stop
                  >
                    <template v-if="filterPanelView === 'chart' && chartPicker">
                      <div class="filter-panel-header">
                        <button
                          type="button"
                          class="filter-icon-btn"
                          @click="closeChartPicker"
                        >
                          <i class="fas fa-arrow-left"></i>
                        </button>
                        <span>{{ chartPickerTitle }}</span>
                        <button
                          type="button"
                          class="filter-icon-btn"
                          @click="closeFilterPanel"
                        >
                          <i class="fas fa-times"></i>
                        </button>
                      </div>
                      <template v-if="chartPicker === 'rangeCustom'">
                        <div class="chart-range-custom">
                          <span class="chart-range-custom-heading">{{
                            t("customReport_customRange", "Set custom range")
                          }}</span>
                          <div class="chart-range-custom-inputs">
                            <input
                              v-model="chartRangeMin"
                              type="number"
                              step="any"
                              class="chart-range-input"
                              :placeholder="t('customReport_rangeMin', 'Min')"
                            />
                            <span class="chart-range-sep" aria-hidden="true"
                              >-</span
                            >
                            <input
                              v-model="chartRangeMax"
                              type="number"
                              step="any"
                              class="chart-range-input"
                              :placeholder="t('customReport_rangeMax', 'Max')"
                            />
                          </div>
                        </div>
                      </template>
                      <template v-else>
                        <div
                          v-if="
                            chartPicker !== 'range' && chartPicker !== 'color'
                          "
                          class="filter-property-search"
                        >
                          <i class="fas fa-search"></i>
                          <input
                            v-model="chartPickerQuery"
                            type="text"
                            :placeholder="
                              t('customReport_filterBy', 'Filter by...')
                            "
                          />
                        </div>
                        <div
                          v-if="chartPicker === 'color'"
                          class="chart-color-list"
                        >
                          <button
                            v-for="scheme in chartColorSchemes"
                            :key="scheme.value"
                            type="button"
                            class="chart-color-item"
                            :class="{
                              selected: scheme.value === chartColorScheme,
                            }"
                            @click="selectChartPickerValue(scheme.value)"
                          >
                            <span>{{ scheme.label }}</span>
                            <span
                              v-if="scheme.colors.length"
                              class="chart-color-swatches"
                            >
                              <i
                                v-for="swatch in scheme.colors"
                                :key="swatch"
                                :style="{ background: swatch }"
                              ></i>
                            </span>
                          </button>
                        </div>
                        <div v-else class="chart-picker-list">
                          <button
                            v-for="(
                              opt, optIndex
                            ) in filteredChartPickerOptions"
                            :key="opt.value || 'empty'"
                            type="button"
                            class="chart-picker-item"
                            :class="{
                              selected: opt.value === chartPickerValue,
                              stripe: optIndex % 2 === 1,
                            }"
                            @click="selectChartPickerValue(opt.value)"
                          >
                            <span
                              v-if="opt.icon"
                              class="filter-property-icon"
                              >{{ opt.icon }}</span
                            >
                            <span>{{ opt.label }}</span>
                            <i
                              v-if="opt.value === chartPickerValue"
                              class="fas fa-check filter-selected-check"
                            ></i>
                          </button>
                          <p
                            v-if="filteredChartPickerOptions.length === 0"
                            class="filter-empty-hint"
                          >
                            {{
                              t("customReport_noColumns", "No columns found.")
                            }}
                          </p>
                        </div>
                      </template>
                    </template>

                    <template v-else-if="filterPanelView === 'chart'">
                      <div class="filter-panel-header">
                        <span>{{
                          t("customReport_viewSettings", "View settings")
                        }}</span>
                        <button
                          type="button"
                          class="filter-icon-btn"
                          @click="closeFilterPanel"
                        >
                          <i class="fas fa-times"></i>
                        </button>
                      </div>
                      <div class="chart-settings">
                        <div class="chart-settings-section">
                          <span class="chart-settings-label">{{
                            t("customReport_chartType", "Chart type")
                          }}</span>
                          <div class="chart-type-grid">
                            <button
                              type="button"
                              class="chart-type-option"
                              :class="{ active: chartType === 'bar_vertical' }"
                              :title="
                                t('customReport_chartVertical', 'Vertical bar')
                              "
                              @click="chartType = 'bar_vertical'"
                            >
                              <span
                                class="chart-type-icon icon-bar-v"
                                aria-hidden="true"
                              >
                                <i></i><i></i><i></i>
                              </span>
                            </button>
                            <button
                              type="button"
                              class="chart-type-option"
                              :class="{
                                active: chartType === 'bar_horizontal',
                              }"
                              :title="
                                t(
                                  'customReport_chartHorizontal',
                                  'Horizontal bar',
                                )
                              "
                              @click="chartType = 'bar_horizontal'"
                            >
                              <span
                                class="chart-type-icon icon-bar-h"
                                aria-hidden="true"
                              >
                                <i></i><i></i><i></i>
                              </span>
                            </button>
                          </div>
                        </div>

                        <div class="chart-settings-section">
                          <span class="chart-settings-heading">{{
                            t("customReport_xAxis", "X axis")
                          }}</span>
                          <button
                            type="button"
                            class="chart-field-row"
                            @click="openChartPicker('xField')"
                          >
                            <span class="chart-field-left">
                              <i class="fas fa-level-up-alt fa-rotate-90"></i>
                              <span>{{
                                t("customReport_whatToShow", "What to show")
                              }}</span>
                            </span>
                            <span class="chart-field-right">
                              <span>{{ chartXFieldLabel }}</span>
                              <i class="fas fa-chevron-right"></i>
                            </span>
                          </button>
                          <button
                            type="button"
                            class="chart-field-row"
                            @click="openChartPicker('sortBy')"
                          >
                            <span class="chart-field-left">
                              <i class="fas fa-exchange-alt"></i>
                              <span>{{
                                t("customReport_sortBy", "Sort by")
                              }}</span>
                            </span>
                            <span class="chart-field-right">
                              <span>{{ chartSortByLabel }}</span>
                              <i class="fas fa-chevron-right"></i>
                            </span>
                          </button>
                          <label class="chart-field-row">
                            <span class="chart-field-left">
                              <i class="fas fa-eye-slash"></i>
                              <span>{{
                                t("customReport_omitZero", "Omit zero values")
                              }}</span>
                            </span>
                            <input
                              v-model="chartOmitZero"
                              type="checkbox"
                              class="chart-toggle"
                            />
                          </label>
                        </div>

                        <div class="chart-settings-section">
                          <span class="chart-settings-heading">{{
                            t("customReport_yAxis", "Y axis")
                          }}</span>
                          <button
                            type="button"
                            class="chart-field-row"
                            @click="openChartPicker('yField')"
                          >
                            <span class="chart-field-left">
                              <i class="fas fa-level-up-alt fa-rotate-90"></i>
                              <span>{{
                                t("customReport_whatToShow", "What to show")
                              }}</span>
                            </span>
                            <span class="chart-field-right">
                              <span>{{ chartYFieldLabel }}</span>
                              <i class="fas fa-chevron-right"></i>
                            </span>
                          </button>
                          <button
                            type="button"
                            class="chart-field-row"
                            @click="openChartPicker('groupBy')"
                          >
                            <span class="chart-field-left">
                              <i class="fas fa-layer-group"></i>
                              <span>{{
                                t("customReport_groupBy", "Group by")
                              }}</span>
                            </span>
                            <span class="chart-field-right">
                              <span>{{ chartGroupByLabel }}</span>
                              <i class="fas fa-chevron-right"></i>
                            </span>
                          </button>
                          <button
                            type="button"
                            class="chart-field-row"
                            @click="openChartPicker('ySortBy')"
                          >
                            <span class="chart-field-left">
                              <i class="fas fa-exchange-alt"></i>
                              <span>{{
                                t("customReport_sortBy", "Sort by")
                              }}</span>
                            </span>
                            <span class="chart-field-right">
                              <span>{{ chartYSortByLabel }}</span>
                              <i class="fas fa-chevron-right"></i>
                            </span>
                          </button>
                          <label class="chart-field-row">
                            <span class="chart-field-left">
                              <i class="fas fa-chart-line"></i>
                              <span>{{
                                t("customReport_cumulative", "Cumulative")
                              }}</span>
                            </span>
                            <input
                              v-model="chartCumulative"
                              type="checkbox"
                              class="chart-toggle"
                            />
                          </label>
                          <button
                            type="button"
                            class="chart-field-row"
                            @click="openChartPicker('range')"
                          >
                            <span class="chart-field-left">
                              <i class="fas fa-arrows-alt-h"></i>
                              <span>{{
                                t("customReport_range", "Range")
                              }}</span>
                            </span>
                            <span class="chart-field-right">
                              <span>{{ chartRangeLabel }}</span>
                              <i class="fas fa-chevron-right"></i>
                            </span>
                          </button>
                        </div>
                        <div class="chart-settings-section">
                          <button
                            type="button"
                            class="chart-field-row"
                            @click="openChartPicker('color')"
                          >
                            <span class="chart-field-left">
                              <i class="fas fa-palette"></i>
                              <span>{{
                                t("customReport_color", "Color")
                              }}</span>
                            </span>
                            <span class="chart-field-right">
                              <span>{{ chartColorLabel }}</span>
                              <i class="fas fa-chevron-right"></i>
                            </span>
                          </button>
                          <button
                            type="button"
                            class="filter-property-item"
                            @click="openChartFilter"
                          >
                            <span class="filter-property-icon">
                              <span
                                class="notion-filter-icon menu-mini-icon"
                                aria-hidden="true"
                              >
                                <span></span>
                                <span></span>
                                <span></span>
                              </span>
                            </span>
                            <span>{{
                              t("customReport_filter", "Filter")
                            }}</span>
                            <span
                              v-if="activeFilterCount > 0"
                              class="chart-filter-count"
                              >{{ activeFilterCount }}</span
                            >
                          </button>
                        </div>
                        <div class="filter-menu-actions">
                          <button
                            type="button"
                            class="filter-property-item filter-property-item-primary"
                            :disabled="
                              !activeWidgetType ||
                              widgetTypes.length >= MAX_WIDGET_TYPES
                            "
                            @click.stop="duplicateActiveWidgetType"
                          >
                            <span class="filter-property-icon"
                              ><i class="fas fa-copy"></i
                            ></span>
                            <span>{{
                              t("customReport_duplicate", "Duplicate")
                            }}</span>
                          </button>
                          <button
                            type="button"
                            class="filter-property-item filter-property-item-danger"
                            :disabled="!activeWidgetType"
                            @click.stop="openDeleteActiveTypeConfirm"
                          >
                            <span class="filter-property-icon"
                              ><i class="fas fa-trash"></i
                            ></span>
                            <span>{{
                              t("customReport_btnDelete", "Delete")
                            }}</span>
                          </button>
                        </div>
                      </div>
                    </template>

                    <template v-else-if="filterPanelView === 'menu'">
                      <div class="filter-panel-header">
                        <span>{{
                          t("customReport_viewOptions", "Options")
                        }}</span>
                        <button
                          type="button"
                          class="filter-icon-btn"
                          @click="closeFilterPanel"
                        >
                          <i class="fas fa-times"></i>
                        </button>
                      </div>
                      <div class="filter-menu-list">
                        <button
                          type="button"
                          class="filter-property-item"
                          @click="chooseAddSort"
                        >
                          <span class="filter-property-icon">
                            <i class="fas fa-arrows-alt-v"></i>
                          </span>
                          <span>{{ t("customReport_sort", "Sort") }}</span>
                        </button>
                        <button
                          type="button"
                          class="filter-property-item"
                          @click="chooseAddFilter"
                        >
                          <span class="filter-property-icon">
                            <span
                              class="notion-filter-icon menu-mini-icon"
                              aria-hidden="true"
                            >
                              <span></span>
                              <span></span>
                              <span></span>
                            </span>
                          </span>
                          <span>{{ t("customReport_filter", "Filter") }}</span>
                        </button>
                        <button
                          type="button"
                          class="filter-property-item"
                          @click="openColumnPanel"
                        >
                          <span class="filter-property-icon">
                            <span
                              class="menu-column-icon"
                              aria-hidden="true"
                            ></span>
                          </span>
                          <span>{{ t("customReport_column", "Column") }}</span>
                        </button>
                        <div class="filter-menu-actions">
                          <button
                            type="button"
                            class="filter-property-item filter-property-item-primary"
                            :disabled="
                              !activeWidgetType ||
                              widgetTypes.length >= MAX_WIDGET_TYPES
                            "
                            @click.stop="duplicateActiveWidgetType"
                          >
                            <span class="filter-property-icon"
                              ><i class="fas fa-copy"></i
                            ></span>
                            <span>{{
                              t("customReport_duplicate", "Duplicate")
                            }}</span>
                          </button>
                          <button
                            type="button"
                            class="filter-property-item filter-property-item-danger"
                            :disabled="!activeWidgetType"
                            @click.stop="openDeleteActiveTypeConfirm"
                          >
                            <span class="filter-property-icon"
                              ><i class="fas fa-trash"></i
                            ></span>
                            <span>{{
                              t("customReport_btnDelete", "Delete")
                            }}</span>
                          </button>
                        </div>
                      </div>
                    </template>

                    <template v-else-if="filterPanelView === 'columns'">
                      <div class="filter-panel-header">
                        <button
                          type="button"
                          class="filter-icon-btn"
                          @click="filterPanelView = 'menu'"
                        >
                          <i class="fas fa-arrow-left"></i>
                        </button>
                        <span>{{ t("customReport_column", "Column") }}</span>
                        <button
                          type="button"
                          class="filter-icon-btn"
                          @click="closeFilterPanel"
                        >
                          <i class="fas fa-times"></i>
                        </button>
                      </div>
                      <div class="filter-property-search">
                        <i class="fas fa-search"></i>
                        <input
                          v-model="columnSearchQuery"
                          type="text"
                          :placeholder="
                            t('customReport_filterBy', 'Filter by...')
                          "
                        />
                      </div>
                      <div class="column-toggle-list">
                        <label class="column-toggle-item column-toggle-all">
                          <input
                            type="checkbox"
                            :checked="allColumnsVisible"
                            :indeterminate.prop="
                              someColumnsVisible && !allColumnsVisible
                            "
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
                            :title="
                              t('customReport_dragColumn', 'Drag to reorder')
                            "
                            @dragstart.stop="
                              onColumnDragStart($event, col.field)
                            "
                            @dragend.stop="onColumnDragEnd"
                            @click.prevent
                          >
                            <i class="fas fa-grip-vertical"></i>
                          </span>
                          <input
                            type="checkbox"
                            :checked="visibleColumns[col.field]"
                            :disabled="isLastVisibleColumn(col.field)"
                            @change="
                              toggleColumnVisibility(
                                col.field,
                                $event.target.checked,
                              )
                            "
                          />
                          <span class="filter-property-icon">{{
                            col.icon
                          }}</span>
                          <span>{{ col.label }}</span>
                        </label>
                        <p
                          v-if="filteredColumnToggleColumns.length === 0"
                          class="filter-empty-hint"
                        >
                          {{ t("customReport_noColumns", "No columns found.") }}
                        </p>
                      </div>
                    </template>

                    <template v-else-if="filterPanelView === 'sort'">
                      <div class="filter-panel-header">
                        <button
                          type="button"
                          class="filter-icon-btn"
                          @click="filterPanelView = 'menu'"
                        >
                          <i class="fas fa-arrow-left"></i>
                        </button>
                        <span>{{ t("customReport_sort", "Sort") }}</span>
                        <button
                          type="button"
                          class="filter-icon-btn"
                          @click="closeFilterPanel"
                        >
                          <i class="fas fa-times"></i>
                        </button>
                      </div>

                      <div v-if="!sortActive" class="filter-empty">
                        <p>{{ t("customReport_noSorts", "No sorts yet.") }}</p>
                        <button
                          type="button"
                          class="filter-link-btn"
                          @click="startFirstSort"
                        >
                          <i class="fas fa-plus"></i>
                          {{ t("customReport_addNewSort", "Add New Sort") }}
                        </button>
                      </div>

                      <template v-else>
                        <div class="filter-sort-rules">
                          <div
                            v-for="(rule, index) in sorts"
                            :key="rule.id"
                            class="sort-popover-row"
                          >
                            <select
                              v-model="rule.field"
                              class="filter-select"
                              @change="onSortChipChange"
                            >
                              <option
                                v-for="col in filterColumns"
                                :key="col.field"
                                :value="col.field"
                              >
                                {{ col.label }}
                              </option>
                            </select>
                            <select
                              v-model="rule.direction"
                              class="filter-select filter-select-op"
                              @change="onSortChipChange"
                            >
                              <option value="asc">
                                {{ t("customReport_sortAsc", "Ascending") }}
                              </option>
                              <option value="desc">
                                {{ t("customReport_sortDesc", "Descending") }}
                              </option>
                            </select>
                            <button
                              type="button"
                              class="filter-icon-btn filter-icon-btn-danger"
                              @click="removeSortRule(index)"
                            >
                              <i class="fas fa-trash"></i>
                            </button>
                          </div>
                        </div>
                        <div class="sort-popover-footer modal-sort-footer">
                          <button
                            type="button"
                            class="filter-link-btn"
                            @click="addSortRule"
                          >
                            <i class="fas fa-plus"></i>
                            {{ t("customReport_addNewSort", "Add New Sort") }}
                          </button>
                          <button
                            type="button"
                            class="filter-link-btn danger"
                            @click="clearSortFromModal"
                          >
                            <i class="fas fa-trash"></i>
                            {{
                              t("customReport_deleteAllSort", "Delete All sort")
                            }}
                          </button>
                        </div>
                      </template>
                    </template>

                    <template v-else-if="filterPanelView === 'add'">
                      <div class="filter-panel-header">
                        <button
                          type="button"
                          class="filter-icon-btn"
                          @click="backFromAddFilter"
                        >
                          <i class="fas fa-arrow-left"></i>
                        </button>
                        <span>{{
                          t("customReport_addFilter", "Add filter")
                        }}</span>
                        <button
                          type="button"
                          class="filter-icon-btn"
                          @click="closeFilterPanel"
                        >
                          <i class="fas fa-times"></i>
                        </button>
                      </div>
                      <div class="filter-property-search">
                        <i class="fas fa-search"></i>
                        <input
                          v-model="filterPropertyQuery"
                          type="text"
                          :placeholder="
                            t('customReport_filterBy', 'Filter by...')
                          "
                        />
                      </div>
                      <div class="filter-property-list">
                        <button
                          v-for="col in filteredFilterColumns"
                          :key="col.field"
                          type="button"
                          class="filter-property-item"
                          @click="addFilter(col.field)"
                        >
                          <span class="filter-property-icon">{{
                            col.icon
                          }}</span>
                          <span>{{ col.label }}</span>
                        </button>
                        <p
                          v-if="filteredFilterColumns.length === 0"
                          class="filter-empty-hint"
                        >
                          {{ t("customReport_noColumns", "No columns found.") }}
                        </p>
                      </div>
                    </template>

                    <template v-else-if="filterPanelView === 'list'">
                      <div class="filter-panel-header">
                        <button
                          type="button"
                          class="filter-icon-btn"
                          @click="backToFilterReturn"
                        >
                          <i class="fas fa-arrow-left"></i>
                        </button>
                        <span>{{ t("customReport_filters", "Filters") }}</span>
                        <button
                          type="button"
                          class="filter-icon-btn"
                          @click="closeFilterPanel"
                        >
                          <i class="fas fa-times"></i>
                        </button>
                      </div>

                      <div v-if="filters.length === 0" class="filter-empty">
                        <p>
                          {{ t("customReport_noFilters", "No filters yet.") }}
                        </p>
                        <button
                          type="button"
                          class="filter-link-btn"
                          @click="openAddFilter"
                        >
                          <i class="fas fa-plus"></i>
                          {{ t("customReport_addFilter", "Add filter") }}
                        </button>
                      </div>

                      <div v-else class="filter-rules">
                        <div
                          v-for="(rule, index) in filters"
                          :key="rule.id"
                          class="filter-rule"
                        >
                          <div class="filter-rule-top">
                            <select
                              v-model="rule.field"
                              class="filter-select"
                              @change="onFilterFieldChange(rule)"
                            >
                              <option
                                v-for="col in filterColumns"
                                :key="col.field"
                                :value="col.field"
                              >
                                {{ col.label }}
                              </option>
                            </select>
                            <button
                              type="button"
                              class="filter-icon-btn filter-icon-btn-danger"
                              :title="t('customReport_removeFilter', 'Remove')"
                              @click="removeFilter(index)"
                            >
                              <i class="fas fa-trash"></i>
                            </button>
                          </div>
                          <div class="filter-rule-bottom">
                            <select
                              v-model="rule.op"
                              class="filter-select filter-select-op"
                              @change="onFilterOpChange(rule)"
                            >
                              <option
                                v-for="op in opsForField(rule.field)"
                                :key="op.value"
                                :value="op.value"
                              >
                                {{ op.label }}
                              </option>
                            </select>
                            <input
                              v-if="!isEmptyOp(rule.op)"
                              class="filter-value-input"
                              :type="
                                isMultiValueOp(rule.op)
                                  ? 'text'
                                  : inputTypeForField(rule.field)
                              "
                              :value="filterValueDisplay(rule.value)"
                              :placeholder="
                                isMultiValueOp(rule.op)
                                  ? t(
                                      'customReport_filterValuesHint',
                                      'Comma-separated values...',
                                    )
                                  : t(
                                      'customReport_filterValue',
                                      'Type a value...',
                                    )
                              "
                              @input="
                                onFilterRuleValueInput(
                                  rule,
                                  $event.target.value,
                                )
                              "
                            />
                          </div>
                        </div>
                      </div>

                      <div class="filter-panel-footer">
                        <button
                          type="button"
                          class="filter-link-btn"
                          @click="openAddFilter"
                        >
                          <i class="fas fa-plus"></i>
                          {{ t("customReport_addFilter", "Add filter") }}
                        </button>
                        <button
                          v-if="filters.length"
                          type="button"
                          class="filter-link-btn"
                          @click="resetFilters"
                        >
                          <i class="fas fa-undo"></i>
                          {{ t("customReport_resetFilters", "Reset filters") }}
                        </button>
                      </div>
                    </template>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div v-if="showViewActiveBar" class="active-controls-bar">
            <div class="active-controls-left">
              <div
                v-if="sortActive && activeKind === 'table'"
                class="active-chip-wrap"
                ref="sortChipRef"
              >
                <button
                  type="button"
                  class="active-chip active-chip-sort"
                  @click.stop="toggleSortPopover"
                >
                  <span>{{ primarySortDirection === "asc" ? "↑" : "↓" }}</span>
                  <span>{{ primarySortLabel }}</span>
                  <span v-if="sorts.length > 1" class="chip-count"
                    >+{{ sorts.length - 1 }}</span
                  >
                  <i class="fas fa-chevron-down chip-chevron"></i>
                </button>
                <div v-if="showSortPopover" class="sort-popover" @click.stop>
                  <div
                    v-for="(rule, index) in sorts"
                    :key="rule.id"
                    class="sort-popover-row"
                  >
                    <select
                      v-model="rule.field"
                      class="filter-select"
                      @change="onSortChipChange"
                    >
                      <option
                        v-for="col in filterColumns"
                        :key="col.field"
                        :value="col.field"
                      >
                        {{ col.label }}
                      </option>
                    </select>
                    <select
                      v-model="rule.direction"
                      class="filter-select filter-select-op"
                      @change="onSortChipChange"
                    >
                      <option value="asc">
                        {{ t("customReport_sortAsc", "Ascending") }}
                      </option>
                      <option value="desc">
                        {{ t("customReport_sortDesc", "Descending") }}
                      </option>
                    </select>
                    <button
                      type="button"
                      class="filter-icon-btn filter-icon-btn-danger"
                      @click="removeSortRule(index)"
                    >
                      <i class="fas fa-trash"></i>
                    </button>
                  </div>
                  <div class="sort-popover-footer">
                    <button
                      type="button"
                      class="filter-link-btn"
                      @click="addSortRule"
                    >
                      <i class="fas fa-plus"></i>
                      {{ t("customReport_addNewSort", "Add New Sort") }}
                    </button>
                    <button
                      type="button"
                      class="filter-link-btn danger"
                      @click="clearSort"
                    >
                      <i class="fas fa-trash"></i>
                      {{ t("customReport_deleteAllSort", "Delete All sort") }}
                    </button>
                  </div>
                </div>
              </div>

              <div
                v-if="
                  sortActive && activeKind === 'table' && activeFilterCount > 0
                "
                class="active-controls-divider"
              ></div>

              <div class="active-filter-wrap" ref="filterBarRef">
                <button
                  v-for="rule in activeFilterRules"
                  :key="rule.id"
                  type="button"
                  class="active-chip active-chip-filter"
                  :class="{
                    active:
                      showBarFilterPanel &&
                      editingBarFilterField === rule.field,
                  }"
                  @click.stop="openFilterPanelForRule(rule)"
                >
                  <span class="chip-icon">{{ columnIcon(rule.field) }}</span>
                  <span>{{ columnLabel(rule.field) }}</span>
                  <span class="filter-chip-dot"></span>
                  <i class="fas fa-chevron-down chip-chevron"></i>
                </button>

                <button
                  type="button"
                  class="active-add-filter"
                  @click.stop="openFilterFromBar"
                >
                  <i class="fas fa-plus"></i>
                  {{ t("customReport_filter", "Filter") }}
                </button>

                <div
                  v-if="showBarFilterPanel"
                  class="filter-panel filter-panel-bar"
                  @click.stop
                >
                  <template v-if="editingBarFilterField && barEditingRule">
                    <div class="filter-panel-header">
                      <span class="filter-focused-title">
                        <span class="filter-property-icon">{{
                          columnIcon(editingBarFilterField)
                        }}</span>
                        {{ columnLabel(editingBarFilterField) }}
                      </span>
                      <button
                        type="button"
                        class="filter-icon-btn"
                        @click="closeBarFilterPanel"
                      >
                        <i class="fas fa-times"></i>
                      </button>
                    </div>
                    <div class="filter-focused-body">
                      <div class="filter-focused-row">
                        <span class="filter-focused-label">{{
                          columnLabel(editingBarFilterField)
                        }}</span>
                        <select
                          v-model="barEditingRule.op"
                          class="filter-select filter-select-op"
                          @change="onFilterOpChange(barEditingRule)"
                        >
                          <option
                            v-for="op in opsForField(editingBarFilterField)"
                            :key="op.value"
                            :value="op.value"
                          >
                            {{ op.label }}
                          </option>
                        </select>
                        <button
                          type="button"
                          class="filter-icon-btn filter-icon-btn-danger"
                          :title="t('customReport_removeFilter', 'Remove')"
                          @click="removeBarFilterField(editingBarFilterField)"
                        >
                          <i class="fas fa-trash"></i>
                        </button>
                      </div>
                      <input
                        v-if="!isEmptyOp(barEditingRule.op)"
                        class="filter-value-input filter-focused-input"
                        :type="
                          isMultiValueOp(barEditingRule.op)
                            ? 'text'
                            : inputTypeForField(editingBarFilterField)
                        "
                        :value="filterValueDisplay(barEditingRule.value)"
                        :placeholder="
                          isMultiValueOp(barEditingRule.op)
                            ? t(
                                'customReport_filterValuesHint',
                                'Comma-separated values...',
                              )
                            : t('customReport_filterValue', 'Type a value...')
                        "
                        @input="
                          onFilterRuleValueInput(
                            barEditingRule,
                            $event.target.value,
                          )
                        "
                      />
                    </div>
                  </template>

                  <template v-else>
                    <div class="filter-panel-header">
                      <span>{{ t("customReport_filter", "Filter") }}</span>
                      <button
                        type="button"
                        class="filter-icon-btn"
                        @click="closeBarFilterPanel"
                      >
                        <i class="fas fa-times"></i>
                      </button>
                    </div>
                    <div class="filter-property-search">
                      <i class="fas fa-search"></i>
                      <input
                        v-model="filterPropertyQuery"
                        type="text"
                        :placeholder="
                          t('customReport_filterBy', 'Filter by...')
                        "
                      />
                    </div>
                    <div class="filter-property-list">
                      <button
                        v-for="col in filteredFilterColumns"
                        :key="col.field"
                        type="button"
                        class="filter-property-item"
                        :class="{ selected: isColumnFiltered(col.field) }"
                        @click="selectBarFilterColumn(col.field)"
                      >
                        <span class="filter-property-icon">{{ col.icon }}</span>
                        <span>{{ col.label }}</span>
                        <i
                          v-if="isColumnFiltered(col.field)"
                          class="fas fa-check filter-selected-check"
                        ></i>
                      </button>
                      <p
                        v-if="filteredFilterColumns.length === 0"
                        class="filter-empty-hint"
                      >
                        {{ t("customReport_noColumns", "No columns found.") }}
                      </p>
                    </div>
                  </template>
                </div>
              </div>
            </div>

            <button type="button" class="active-reset-btn" @click="resetAll">
              {{ t("customReport_reset", "Reset") }}
            </button>
          </div>

          <div v-if="activeKind === 'chart'" class="chart-stage">
            <div v-if="!isChartReady" class="chart-empty">
              <div class="chart-empty-plot" aria-hidden="true">
                <span></span><span></span><span></span><span></span>
              </div>
              <p>{{ chartEmptyHint }}</p>
            </div>
            <div
              v-else
              class="chart-ready"
              :class="{ 'has-hot-slice': hoveredChartSerie !== null }"
              :style="chartReadyStyle"
            >
              <div
                v-if="chartType === 'bar_horizontal'"
                class="chart-horizontal"
              >
                <div
                  v-for="(label, labelIndex) in chartLabels"
                  :key="label"
                  class="chart-h-row"
                  @click="openChartBarDetail(label)"
                >
                  <span class="chart-h-label">{{ label }}</span>
                  <div class="chart-h-track">
                    <div
                      v-for="item in chartStacks[labelIndex]"
                      :key="item.serieIndex"
                      class="chart-h-bar"
                      :class="item.colorClass"
                      :style="{
                        width: item.percent + '%',
                        '--serie': item.serieIndex,
                      }"
                      @mouseenter="
                        hoverChartSlice(
                          $event,
                          label,
                          labelIndex,
                          item.serieIndex,
                        )
                      "
                      @mouseleave="scheduleHideChartTooltip"
                      @wheel="onChartTooltipWheel"
                    ></div>
                  </div>
                  <span class="chart-h-value">{{
                    formatChartValue(chartLabelTotals[labelIndex])
                  }}</span>
                </div>
              </div>
              <div v-else class="chart-vertical">
                <div class="chart-y-labels">
                  <span v-for="tick in chartTicks" :key="tick">{{
                    formatChartValue(tick)
                  }}</span>
                </div>
                <div class="chart-plot">
                  <div
                    class="chart-bars"
                    :style="{ minWidth: chartVerticalMinWidth + 'px' }"
                  >
                    <div
                      v-for="(label, labelIndex) in chartLabels"
                      :key="label"
                      class="chart-col"
                      @click="openChartBarDetail(label)"
                    >
                      <div class="chart-col-plot">
                        <div class="chart-stack">
                          <div
                            v-for="item in chartStacks[labelIndex]"
                            :key="item.serieIndex"
                            class="chart-bar"
                            :class="item.colorClass"
                            :style="{
                              height: item.percent + '%',
                              '--serie': item.serieIndex,
                            }"
                            @mouseenter="
                              hoverChartSlice(
                                $event,
                                label,
                                labelIndex,
                                item.serieIndex,
                              )
                            "
                            @mouseleave="scheduleHideChartTooltip"
                            @wheel="onChartTooltipWheel"
                          ></div>
                        </div>
                        <span
                          v-if="chartLabelTotals[labelIndex] > 0"
                          class="chart-col-total"
                          :style="{
                            bottom:
                              barPercent(chartLabelTotals[labelIndex]) + '%',
                          }"
                          >{{
                            formatChartValue(chartLabelTotals[labelIndex])
                          }}</span
                        >
                      </div>
                      <span class="chart-x-label">{{ label }}</span>
                    </div>
                  </div>
                </div>
              </div>
              <div
                v-if="chartTooltip"
                class="chart-tooltip"
                :class="{ 'is-grouped': chartTooltip.items.length }"
                :style="{
                  left: chartTooltip.x + 'px',
                  top: chartTooltip.y + 'px',
                }"
                @mouseenter="onChartTooltipEnter"
                @mouseleave="scheduleHideChartTooltip"
                @click.stop="openChartBarDetail(chartTooltip.label)"
              >
                <template v-if="chartTooltip.items.length">
                  <div class="chart-tooltip-head">
                    <span class="chart-tooltip-title">{{
                      chartTooltip.label
                    }}</span>
                    <strong class="chart-tooltip-total">{{
                      formatChartValue(chartTooltip.value)
                    }}</strong>
                  </div>
                  <div class="chart-tooltip-rows" ref="chartTooltipRowsRef">
                    <div
                      v-for="item in chartTooltip.items"
                      :key="item.name"
                      class="chart-tooltip-row"
                      :class="{
                        'is-hot': hoveredChartSerie === item.serieIndex,
                      }"
                      @mouseenter="hoveredChartSerie = item.serieIndex"
                      @click.stop="
                        openChartBarDetail(chartTooltip.label, item.name)
                      "
                    >
                      <i :class="'serie-' + (item.serieIndex % 5)"></i>
                      <span>{{ item.name }}</span>
                      <strong>{{ formatChartValue(item.value) }}</strong>
                    </div>
                  </div>
                </template>
                <div v-else class="chart-tooltip-main">
                  <span>{{ chartTooltip.label }}</span>
                  <strong>{{ formatChartValue(chartTooltip.value) }}</strong>
                </div>
                <div class="chart-tooltip-hint">
                  <i class="fas fa-list"></i>
                  {{ t("customReport_clickToView", "Click to view data") }}
                </div>
              </div>
              <div
                v-if="chartTotalLabels > 0"
                class="pagination chart-pagination"
              >
                <div class="pagination-info">
                  <div class="rows-selector">
                    <label>{{ t("customReport_show", "Show") }}</label>
                    <select
                      v-model.number="chartPerPage"
                      @change="changeChartPerPage"
                    >
                      <option
                        v-for="size in CHART_PER_PAGE_OPTIONS"
                        :key="size"
                        :value="size"
                      >
                        {{ size }}
                      </option>
                    </select>
                  </div>
                  <span>{{
                    tParams(
                      "customReport_chartPaginationRange",
                      "Showing {from}–{to} of {total} values",
                      {
                        from: chartRangeFrom,
                        to: chartRangeTo,
                        total: chartTotalLabels,
                      },
                    )
                  }}</span>
                </div>
                <div v-if="chartTotalPages > 1" class="pagination-controls">
                  <button
                    type="button"
                    class="pagination-btn"
                    :disabled="chartPage === 1"
                    @click="changeChartPage(chartPage - 1)"
                  >
                    <i class="fas fa-chevron-left"></i>
                    {{ t("fundingReport_pagination_previous") }}
                  </button>
                  <template
                    v-for="(page, idx) in visibleChartPages"
                    :key="`cp-${idx}`"
                  >
                    <button
                      v-if="page !== '...'"
                      type="button"
                      :class="[
                        'pagination-btn',
                        { active: chartPage === page },
                      ]"
                      @click="changeChartPage(page)"
                    >
                      {{ page }}
                    </button>
                    <span v-else class="pagination-ellipsis">...</span>
                  </template>
                  <button
                    type="button"
                    class="pagination-btn"
                    :disabled="chartPage === chartTotalPages"
                    @click="changeChartPage(chartPage + 1)"
                  >
                    {{ t("fundingReport_pagination_next") }}
                    <i class="fas fa-chevron-right"></i>
                  </button>
                </div>
              </div>
              <div v-if="chartSeries.length > 1" class="chart-legend">
                <div
                  class="chart-legend-items"
                  @mouseleave="hoveredChartSerie = null"
                >
                  <span
                    v-for="(serie, serieIndex) in visibleChartLegend"
                    :key="serie.name"
                    class="chart-legend-item"
                    :class="{ 'is-off': isChartSerieHidden(serie.name) }"
                    :style="{ '--serie': serieIndex }"
                    @mouseenter="hoveredChartSerie = serieIndex"
                    @click.stop="toggleChartSerie(serie.name)"
                  >
                    <i :class="'serie-' + (serieIndex % 5)"></i>
                    {{ serie.name }}
                  </span>
                </div>
                <button
                  v-if="chartLegendNeedsToggle"
                  type="button"
                  class="btn btn-primary chart-legend-toggle"
                  @click="chartLegendExpanded = !chartLegendExpanded"
                >
                  {{
                    chartLegendExpanded
                      ? t("customReport_showLess", "Show less")
                      : tParams(
                          "customReport_showAllLegend",
                          "Show all ({count})",
                          { count: chartSeries.length },
                        )
                  }}
                </button>
              </div>
            </div>
          </div>

          <template v-else-if="activeKind === 'table'">
            <div
              :class="[
                'bulk-actions',
                { show: selectedTransactionIds.length > 0 },
              ]"
            >
              <div class="bulk-actions-left">
                <span class="bulk-actions-label">{{
                  t("fundingReport_bulk_selected")
                }}</span>
                <span class="bulk-actions-count">{{
                  selectedTransactionIds.length
                }}</span>
              </div>
              <button
                v-if="hasExportPermission"
                type="button"
                class="btn-bulk btn-bulk-export"
                :disabled="isExportAllRunning"
                @click.stop="toggleExportSelectedDropdown"
              >
                <i class="fas fa-download"></i>
                {{ t("customReport_exportSelected", "Export Selected") }}
                <div :class="['export-dropdown', { show: showExportDropdown }]">
                  <div
                    class="export-option csv"
                    @click.stop="handleExport('csv')"
                  >
                    <i class="fas fa-file-csv"></i>
                    <span>{{ t("fundingReport_export_csv") }}</span>
                  </div>
                  <div
                    class="export-option excel"
                    @click.stop="handleExport('excel')"
                  >
                    <i class="fas fa-file-excel"></i>
                    <span>{{ t("fundingReport_export_excel") }}</span>
                  </div>
                </div>
              </button>
            </div>

            <div
              class="table-scroll table-scroll--limited"
              ref="tableScrollRef"
            >
              <table class="transaction-table">
                <thead>
                  <tr>
                    <th class="checkbox-col">
                      <label class="custom-checkbox">
                        <input
                          type="checkbox"
                          :checked="isAllSelected"
                          :indeterminate.prop="isIndeterminate"
                          @change="toggleSelectAll"
                        />
                        <span class="checkbox-checkmark"></span>
                      </label>
                    </th>
                    <th
                      v-for="col in tableColumns"
                      :key="col.field"
                      class="sortable"
                      :class="{
                        'is-dragging': dragColumnField === col.field,
                        'drag-over':
                          dragOverField === col.field &&
                          dragColumnField !== col.field,
                        'is-filtered': isColumnFiltered(col.field),
                      }"
                      draggable="true"
                      :title="col.label"
                      @click="onHeaderClick(col.field)"
                      @dragstart="onHeaderDragStart($event, col.field)"
                      @dragover.prevent="onColumnDragOver(col.field)"
                      @drop.prevent="onColumnDrop(col.field)"
                      @dragend="onColumnDragEnd"
                    >
                      <div class="th-label-row">
                        <span class="th-label-text">{{ col.label }}</span>
                      </div>
                      <button
                        type="button"
                        class="th-filter-btn"
                        :class="{
                          active:
                            isColumnFiltered(col.field) ||
                            headerFilterField === col.field,
                        }"
                        :title="t('customReport_columnFilter', 'Filter')"
                        @click.stop="toggleHeaderFilter($event, col.field)"
                      >
                        <i class="fas fa-filter"></i>
                      </button>
                      <div class="sort-icon">
                        <i
                          class="fas fa-caret-up"
                          :class="{ active: isSortActive(col.field, 'asc') }"
                        ></i>
                        <i
                          class="fas fa-caret-down"
                          :class="{ active: isSortActive(col.field, 'desc') }"
                        ></i>
                      </div>
                      <span
                        class="col-resize-handle"
                        @mousedown.stop.prevent="
                          startColumnResize($event, col.field)
                        "
                        @dragstart.stop.prevent
                        @click.stop
                      ></span>
                    </th>
                    <th v-if="hasDetailPanels" class="action-col">
                      {{ t("customReport_th_action", "Action") }}
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="transactions.length === 0">
                    <td :colspan="tableColspan" class="empty-state">
                      <i class="fas fa-inbox"></i>
                      <p>{{ t("fundingReport_empty") }}</p>
                    </td>
                  </tr>
                  <template
                    v-for="(row, rowIndex) in transactions"
                    :key="rowKey(row, rowIndex)"
                  >
                    <tr
                      :class="{
                        expanded: expandedRowKey === rowKey(row, rowIndex),
                      }"
                    >
                      <td class="checkbox-col">
                        <label class="custom-checkbox">
                          <input
                            type="checkbox"
                            :value="rowKey(row, rowIndex)"
                            v-model="selectedTransactionIds"
                          />
                          <span class="checkbox-checkmark"></span>
                        </label>
                      </td>
                      <td
                        v-for="col in tableColumns"
                        :key="col.field"
                        :class="{
                          'cell-clip':
                            col.type !== 'date' && !isStatusField(col.field),
                        }"
                        :title="cellTitle(row[col.field], col.field)"
                      >
                        <template v-if="col.type === 'date'">
                          <div>{{ formatDate(row[col.field]) }}</div>
                          <small class="time-small">{{
                            formatTime(row[col.field])
                          }}</small>
                        </template>
                        <template v-else-if="col.type === 'number'">
                          {{ formatCellNumber(row[col.field]) }}
                        </template>
                        <template v-else-if="isStatusField(col.field)">
                          <span
                            v-if="hasCellValue(row[col.field])"
                            class="status-badge"
                            :class="statusBadgeClass(row[col.field])"
                            >{{ getStatusLabel(row[col.field]) }}</span
                          >
                          <template v-else>-</template>
                        </template>
                        <template v-else>
                          {{ displayCell(row[col.field]) }}
                        </template>
                      </td>
                      <td v-if="hasDetailPanels" class="action-col">
                        <button
                          type="button"
                          class="report-detail-btn"
                          @click="toggleDetail(row, rowIndex)"
                        >
                          <i
                            :class="[
                              'fas',
                              expandedRowKey === rowKey(row, rowIndex)
                                ? 'fa-chevron-up'
                                : 'fa-chevron-down',
                            ]"
                          ></i>
                          {{
                            expandedRowKey === rowKey(row, rowIndex)
                              ? t("customReport_btn_hide", "Hide")
                              : t("customReport_btn_detail", "Detail")
                          }}
                        </button>
                      </td>
                    </tr>
                    <tr
                      v-if="
                        hasDetailPanels &&
                        expandedRowKey === rowKey(row, rowIndex)
                      "
                      class="report-detail-row"
                    >
                      <td :colspan="tableColspan" class="report-detail-cell">
                        <CustomReportRowDetail
                          :row="row"
                          :row-key-val="rowKey(row, rowIndex)"
                          :panels="detailPanels"
                          :container-width="containerWidth"
                          :panel-state="panelState"
                          :visible-detail-fields="visibleDetailFields"
                          :format-detail-cell="formatDetailCell"
                          :is-status-field="isStatusField"
                          :has-cell-value="hasCellValue"
                          :status-badge-class="statusBadgeClass"
                          :get-status-label="getStatusLabel"
                          :detail-pagination-info="detailPaginationInfo"
                          @search-input="
                            (panel, value) =>
                              onDetailSearchInput(row, rowIndex, panel, value)
                          "
                          @search="
                            (panel) =>
                              loadDetailPanel(
                                row,
                                rowKey(row, rowIndex),
                                panel,
                                true,
                              )
                          "
                          @page-change="
                            (panel, page) =>
                              changeDetailPage(row, rowIndex, panel, page)
                          "
                          @per-page-change="
                            (panel, value) =>
                              onDetailPerPageChange(row, rowIndex, panel, value)
                          "
                        />
                      </td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>

            <div
              v-if="headerFilterField"
              ref="headerFilterPanelRef"
              class="header-filter-panel"
              :style="headerFilterPanelStyle"
              @click.stop
            >
              <div class="header-filter-title">
                {{ columnLabel(headerFilterField) }}
              </div>
              <div class="header-filter-sorts">
                <button
                  type="button"
                  class="filter-link-btn"
                  @click="applyHeaderSort('asc')"
                >
                  {{ t("customReport_sortAsc", "Ascending") }}
                </button>
                <button
                  type="button"
                  class="filter-link-btn"
                  @click="applyHeaderSort('desc')"
                >
                  {{ t("customReport_sortDesc", "Descending") }}
                </button>
              </div>
              <div class="filter-property-search">
                <i class="fas fa-search"></i>
                <input
                  v-model="headerFilterSearch"
                  type="text"
                  :placeholder="t('customReport_filterBy', 'Filter by...')"
                  @input="onHeaderFilterSearchInput"
                />
              </div>
              <div class="header-filter-values">
                <label class="header-filter-value-row">
                  <input
                    type="checkbox"
                    :checked="headerFilterAllSelected"
                    :indeterminate.prop="
                      headerFilterSomeSelected && !headerFilterAllSelected
                    "
                    @change="toggleHeaderFilterSelectAll($event.target.checked)"
                  />
                  <span>{{ t("customReport_selectAll", "(Select all)") }}</span>
                </label>
                <p v-if="headerFilterLoading" class="filter-empty-hint">
                  {{ t("common_loading", "Loading...") }}
                </p>
                <p
                  v-else-if="!headerFilterValues.length"
                  class="filter-empty-hint"
                >
                  {{ t("customReport_noValues", "No values") }}
                </p>
                <label
                  v-for="value in headerFilterValues"
                  :key="`hf-${headerFilterField}-${value}`"
                  class="header-filter-value-row"
                >
                  <input
                    type="checkbox"
                    :checked="headerFilterSelected.includes(value)"
                    @change="
                      toggleHeaderFilterValue(value, $event.target.checked)
                    "
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
                  class="btn btn-primary header-filter-apply"
                  @click="applyHeaderFilter"
                >
                  {{ t("customReport_apply", "Apply") }}
                </button>
              </div>
            </div>

            <div class="pagination" v-if="totalPages > 1">
              <div class="pagination-info">
                {{
                  tParams(
                    "fundingReport_pagination_range",
                    "Showing {from}–{to} of {total} transactions",
                    {
                      from: (currentPage - 1) * perPage + 1,
                      to: Math.min(currentPage * perPage, total),
                      total,
                    },
                  )
                }}
              </div>
              <div class="pagination-controls">
                <button
                  class="pagination-btn"
                  :disabled="currentPage === 1"
                  @click="changePage(currentPage - 1)"
                >
                  <i class="fas fa-chevron-left"></i>
                  {{ t("fundingReport_pagination_previous") }}
                </button>
                <template v-for="(page, idx) in visiblePages" :key="`p-${idx}`">
                  <button
                    v-if="page !== '...'"
                    :class="[
                      'pagination-btn',
                      { active: currentPage === page },
                    ]"
                    @click="changePage(page)"
                  >
                    {{ page }}
                  </button>
                  <span v-else class="pagination-ellipsis">...</span>
                </template>
                <button
                  class="pagination-btn"
                  :disabled="currentPage === totalPages"
                  @click="changePage(currentPage + 1)"
                >
                  {{ t("fundingReport_pagination_next") }}
                  <i class="fas fa-chevron-right"></i>
                </button>
              </div>
            </div>
          </template>
        </div>
      </div>
    </template>

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
            @click="confirmDeleteWidget"
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
      v-if="showEditTypesModal"
      class="modal-overlay"
      @click.self="closeEditWidgetTypes"
    >
      <div class="modal-card modal-card-types">
        <div class="modal-card-head">
          <h3>{{ t("customReport_editWidgetType", "Edit Widget Type") }}</h3>
          <button
            type="button"
            class="filter-icon-btn"
            @click="closeEditWidgetTypes"
          >
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div v-if="widgetTypes.length" class="widget-type-edit-list">
          <div
            v-for="type in widgetTypes"
            :key="type.id"
            class="widget-type-edit-row"
            :class="{
              'is-dragging': dragTypeId === type.id,
              'drag-over': dragOverTypeId === type.id && dragTypeId !== type.id,
            }"
            @dragover.prevent="onTypeDragOver(type.id)"
            @drop.prevent="onTypeDrop(type.id)"
          >
            <span
              class="column-drag-handle"
              draggable="true"
              :title="t('customReport_dragColumn', 'Drag to reorder')"
              @dragstart.stop="onTypeDragStart($event, type.id)"
              @dragend.stop="onTypeDragEnd"
            >
              <i class="fas fa-grip-vertical"></i>
            </span>
            <span class="widget-type-kind-badge">{{
              type.kind === "chart"
                ? t("customReport_type_chart", "Chart")
                : t("customReport_type_table", "Table")
            }}</span>
            <input
              class="widget-type-name-input"
              :value="type.label"
              maxlength="255"
              @input="renameWidgetType(type.id, $event.target.value)"
              @blur="normalizeWidgetTypeLabel(type.id)"
            />
            <span
              class="widget-type-created-meta"
              :title="type.createdAt || ''"
            >
              {{
                type.createdByName ||
                type.createdBy ||
                t("customReport_unknownCreator", "Unknown")
              }}
            </span>
            <button
              type="button"
              class="filter-icon-btn filter-icon-btn-danger"
              :title="t('customReport_removeType', 'Delete type')"
              @click="askRemoveWidgetType(type)"
            >
              <i class="fas fa-trash"></i>
            </button>
          </div>
        </div>
        <div class="widget-type-add-form">
          <select v-model="newTypeKind" class="filter-select">
            <option value="table">
              {{ t("customReport_type_table", "Table") }}
            </option>
            <option value="chart">
              {{ t("customReport_type_chart", "Chart") }}
            </option>
          </select>
          <input
            v-model="newTypeLabel"
            class="widget-type-name-input"
            type="text"
            maxlength="255"
            :placeholder="t('customReport_typeNamePlaceholder', 'Type name')"
            @keyup.enter="addWidgetType"
          />
          <button type="button" class="btn btn-primary" @click="addWidgetType">
            {{ t("customReport_addType", "Add") }}
          </button>
        </div>
      </div>
    </div>

    <div
      v-if="pendingDeleteTypeId"
      class="modal-overlay modal-overlay-stack"
      @click.self="closeRemoveWidgetTypeConfirm"
    >
      <div class="modal-card">
        <h3>{{ t("customReport_deleteTitle", "Confirm Delete") }}</h3>
        <p>
          {{
            tParams(
              "customReport_deleteTypeConfirm",
              'Delete "{name}"? This cannot be undone.',
              { name: pendingDeleteTypeLabel },
            )
          }}
        </p>
        <div class="modal-actions">
          <button
            type="button"
            class="btn btn-danger"
            @click="confirmRemoveWidgetType"
          >
            {{ t("customReport_btnDelete", "Delete") }}
          </button>
          <button
            type="button"
            class="btn btn-secondary"
            @click="closeRemoveWidgetTypeConfirm"
          >
            {{ t("customReport_cancel", "CANCEL") }}
          </button>
        </div>
      </div>
    </div>

    <div
      v-if="showChartDetailModal"
      class="modal-overlay"
      @click.self="onChartDetailOverlayClick"
    >
      <div class="modal-card modal-card-wide">
        <div class="modal-card-head">
          <h3>{{ chartDetailTitle }}</h3>
          <button
            type="button"
            class="filter-icon-btn"
            @click="closeChartDetailModal"
          >
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="chart-detail-toolbar">
          <p class="chart-detail-meta">
            {{ chartXFieldLabel }}: {{ chartDetailLabel }}
          </p>
          <div class="table-controls">
            <div class="search-box">
              <input
                type="text"
                v-model="chartDetailSearch"
                :placeholder="t('customReport_search_placeholder', 'Search...')"
                @input="handleChartDetailSearch"
              />
              <i class="fas fa-search search-icon"></i>
            </div>
            <div class="filter-control" ref="chartDetailFilterRef">
              <button
                type="button"
                class="filter-trigger"
                :class="{
                  active:
                    showChartDetailFilter ||
                    chartDetailFilterCount > 0 ||
                    chartDetailSortActive ||
                    !allChartDetailColumnsVisible,
                }"
                :title="t('customReport_filters', 'Filters')"
                @click="toggleChartDetailFilter"
              >
                <span class="notion-filter-icon" aria-hidden="true">
                  <span></span>
                  <span></span>
                  <span></span>
                </span>
              </button>
              <div
                v-if="showChartDetailFilter"
                class="filter-panel"
                @click.stop
              >
                <template v-if="chartDetailFilterView === 'menu'">
                  <div class="filter-panel-header">
                    <span>{{ t("customReport_viewOptions", "Options") }}</span>
                    <button
                      type="button"
                      class="filter-icon-btn"
                      @click="closeChartDetailFilter"
                    >
                      <i class="fas fa-times"></i>
                    </button>
                  </div>
                  <div class="filter-menu-list">
                    <button
                      type="button"
                      class="filter-property-item"
                      @click="chartDetailFilterView = 'sort'"
                    >
                      <span class="filter-property-icon">
                        <i class="fas fa-arrows-alt-v"></i>
                      </span>
                      <span>{{ t("customReport_sort", "Sort") }}</span>
                    </button>
                    <button
                      type="button"
                      class="filter-property-item"
                      @click="openChartDetailFilterList"
                    >
                      <span class="filter-property-icon">
                        <span
                          class="notion-filter-icon menu-mini-icon"
                          aria-hidden="true"
                        >
                          <span></span>
                          <span></span>
                          <span></span>
                        </span>
                      </span>
                      <span>{{ t("customReport_filter", "Filter") }}</span>
                    </button>
                    <button
                      type="button"
                      class="filter-property-item"
                      @click="openChartDetailColumnPanel"
                    >
                      <span class="filter-property-icon">
                        <span
                          class="menu-column-icon"
                          aria-hidden="true"
                        ></span>
                      </span>
                      <span>{{ t("customReport_column", "Column") }}</span>
                    </button>
                  </div>
                </template>

                <template v-else-if="chartDetailFilterView === 'sort'">
                  <div class="filter-panel-header">
                    <button
                      type="button"
                      class="filter-icon-btn"
                      @click="chartDetailFilterView = 'menu'"
                    >
                      <i class="fas fa-arrow-left"></i>
                    </button>
                    <span>{{ t("customReport_sort", "Sort") }}</span>
                    <button
                      type="button"
                      class="filter-icon-btn"
                      @click="closeChartDetailFilter"
                    >
                      <i class="fas fa-times"></i>
                    </button>
                  </div>
                  <div v-if="!chartDetailSortActive" class="filter-empty">
                    <p>{{ t("customReport_noSorts", "No sorts yet.") }}</p>
                    <button
                      type="button"
                      class="filter-link-btn"
                      @click="startChartDetailSort"
                    >
                      <i class="fas fa-plus"></i>
                      {{ t("customReport_addNewSort", "Add New Sort") }}
                    </button>
                  </div>
                  <template v-else>
                    <div class="filter-sort-rules">
                      <div
                        v-for="(rule, index) in chartDetailSorts"
                        :key="rule.id"
                        class="sort-popover-row"
                      >
                        <select
                          v-model="rule.field"
                          class="filter-select"
                          @change="onChartDetailSortChange"
                        >
                          <option
                            v-for="col in filterColumns"
                            :key="col.field"
                            :value="col.field"
                          >
                            {{ col.label }}
                          </option>
                        </select>
                        <select
                          v-model="rule.direction"
                          class="filter-select filter-select-op"
                          @change="onChartDetailSortChange"
                        >
                          <option value="asc">
                            {{ t("customReport_sortAsc", "Ascending") }}
                          </option>
                          <option value="desc">
                            {{ t("customReport_sortDesc", "Descending") }}
                          </option>
                        </select>
                        <button
                          type="button"
                          class="filter-icon-btn filter-icon-btn-danger"
                          @click="removeChartDetailSort(index)"
                        >
                          <i class="fas fa-trash"></i>
                        </button>
                      </div>
                    </div>
                    <div class="sort-popover-footer modal-sort-footer">
                      <button
                        type="button"
                        class="filter-link-btn"
                        @click="addChartDetailSort"
                      >
                        <i class="fas fa-plus"></i>
                        {{ t("customReport_addNewSort", "Add New Sort") }}
                      </button>
                      <button
                        type="button"
                        class="filter-link-btn danger"
                        @click="clearChartDetailSort"
                      >
                        <i class="fas fa-trash"></i>
                        {{ t("customReport_deleteAllSort", "Delete All sort") }}
                      </button>
                    </div>
                  </template>
                </template>

                <template v-else-if="chartDetailFilterView === 'columns'">
                  <div class="filter-panel-header">
                    <button
                      type="button"
                      class="filter-icon-btn"
                      @click="chartDetailFilterView = 'menu'"
                    >
                      <i class="fas fa-arrow-left"></i>
                    </button>
                    <span>{{ t("customReport_column", "Column") }}</span>
                    <button
                      type="button"
                      class="filter-icon-btn"
                      @click="closeChartDetailFilter"
                    >
                      <i class="fas fa-times"></i>
                    </button>
                  </div>
                  <div class="filter-property-search">
                    <i class="fas fa-search"></i>
                    <input
                      v-model="chartDetailPropertyQuery"
                      type="text"
                      :placeholder="t('customReport_filterBy', 'Filter by...')"
                    />
                  </div>
                  <div class="column-toggle-list">
                    <label class="column-toggle-item column-toggle-all">
                      <input
                        type="checkbox"
                        :checked="allChartDetailColumnsVisible"
                        :indeterminate.prop="
                          someChartDetailColumnsVisible &&
                          !allChartDetailColumnsVisible
                        "
                        @change="
                          toggleAllChartDetailColumns($event.target.checked)
                        "
                      />
                      <span>{{
                        t("customReport_toggleAllColumns", "Toggle all")
                      }}</span>
                    </label>
                    <label
                      v-for="col in chartDetailFilteredColumns"
                      :key="col.field"
                      class="column-toggle-item"
                      :class="{
                        'is-dragging': chartDetailDragField === col.field,
                        'drag-over':
                          chartDetailDragOver === col.field &&
                          chartDetailDragField !== col.field,
                        'is-last-visible': isLastChartDetailColumn(col.field),
                      }"
                      @dragover.prevent="onChartDetailColumnDragOver(col.field)"
                      @drop.prevent="onChartDetailColumnDrop(col.field)"
                    >
                      <span
                        class="column-drag-handle"
                        draggable="true"
                        :title="t('customReport_dragColumn', 'Drag to reorder')"
                        @dragstart.stop="
                          onChartDetailHeaderDragStart($event, col.field)
                        "
                        @dragend.stop="onChartDetailColumnDragEnd"
                        @click.prevent
                      >
                        <i class="fas fa-grip-vertical"></i>
                      </span>
                      <input
                        type="checkbox"
                        :checked="chartDetailVisibleColumns[col.field]"
                        :disabled="isLastChartDetailColumn(col.field)"
                        @change="
                          toggleChartDetailColumn(
                            col.field,
                            $event.target.checked,
                          )
                        "
                      />
                      <span class="filter-property-icon">{{ col.icon }}</span>
                      <span>{{ col.label }}</span>
                    </label>
                    <p
                      v-if="chartDetailFilteredColumns.length === 0"
                      class="filter-empty-hint"
                    >
                      {{ t("customReport_noColumns", "No columns found.") }}
                    </p>
                  </div>
                </template>

                <template v-else-if="chartDetailFilterView === 'add'">
                  <div class="filter-panel-header">
                    <button
                      type="button"
                      class="filter-icon-btn"
                      @click="backChartDetailAddFilter"
                    >
                      <i class="fas fa-arrow-left"></i>
                    </button>
                    <span>{{ t("customReport_addFilter", "Add filter") }}</span>
                    <button
                      type="button"
                      class="filter-icon-btn"
                      @click="closeChartDetailFilter"
                    >
                      <i class="fas fa-times"></i>
                    </button>
                  </div>
                  <div class="filter-property-search">
                    <i class="fas fa-search"></i>
                    <input
                      v-model="chartDetailPropertyQuery"
                      type="text"
                      :placeholder="t('customReport_filterBy', 'Filter by...')"
                    />
                  </div>
                  <div class="filter-property-list">
                    <button
                      v-for="col in chartDetailFilteredColumns"
                      :key="col.field"
                      type="button"
                      class="filter-property-item"
                      @click="addChartDetailFilter(col.field)"
                    >
                      <span class="filter-property-icon">{{ col.icon }}</span>
                      <span>{{ col.label }}</span>
                    </button>
                    <p
                      v-if="chartDetailFilteredColumns.length === 0"
                      class="filter-empty-hint"
                    >
                      {{ t("customReport_noColumns", "No columns found.") }}
                    </p>
                  </div>
                </template>

                <template v-else>
                  <div class="filter-panel-header">
                    <button
                      type="button"
                      class="filter-icon-btn"
                      @click="chartDetailFilterView = 'menu'"
                    >
                      <i class="fas fa-arrow-left"></i>
                    </button>
                    <span>{{ t("customReport_filters", "Filters") }}</span>
                    <button
                      type="button"
                      class="filter-icon-btn"
                      @click="closeChartDetailFilter"
                    >
                      <i class="fas fa-times"></i>
                    </button>
                  </div>
                  <div
                    v-if="chartDetailFilters.length === 0"
                    class="filter-empty"
                  >
                    <p>{{ t("customReport_noFilters", "No filters yet.") }}</p>
                    <button
                      type="button"
                      class="filter-link-btn"
                      @click="chartDetailFilterView = 'add'"
                    >
                      <i class="fas fa-plus"></i>
                      {{ t("customReport_addFilter", "Add filter") }}
                    </button>
                  </div>
                  <template v-else>
                    <div class="filter-rules">
                      <div
                        v-for="(rule, index) in chartDetailFilters"
                        :key="rule.id"
                        class="filter-rule"
                      >
                        <div class="filter-rule-top">
                          <select
                            v-model="rule.field"
                            class="filter-select"
                            @change="onChartDetailFieldChange(rule)"
                          >
                            <option
                              v-for="col in filterColumns"
                              :key="col.field"
                              :value="col.field"
                            >
                              {{ col.label }}
                            </option>
                          </select>
                          <button
                            type="button"
                            class="filter-icon-btn filter-icon-btn-danger"
                            @click="removeChartDetailFilter(index)"
                          >
                            <i class="fas fa-trash"></i>
                          </button>
                        </div>
                        <div class="filter-rule-bottom">
                          <select
                            v-model="rule.op"
                            class="filter-select filter-select-op"
                            @change="loadChartDetailRows"
                          >
                            <option
                              v-for="op in opsForField(rule.field)"
                              :key="op.value"
                              :value="op.value"
                            >
                              {{ op.label }}
                            </option>
                          </select>
                          <input
                            v-if="!isEmptyOp(rule.op)"
                            v-model="rule.value"
                            class="filter-value-input"
                            :type="inputTypeForField(rule.field)"
                            :placeholder="
                              t('customReport_filterValue', 'Type a value...')
                            "
                            @input="handleChartDetailFilterInput"
                          />
                        </div>
                      </div>
                    </div>
                    <div class="filter-panel-footer">
                      <button
                        type="button"
                        class="filter-link-btn"
                        @click="chartDetailFilterView = 'add'"
                      >
                        <i class="fas fa-plus"></i>
                        {{ t("customReport_addFilter", "Add filter") }}
                      </button>
                      <button
                        type="button"
                        class="filter-link-btn"
                        @click="resetChartDetailFilters"
                      >
                        <i class="fas fa-undo"></i>
                        {{ t("customReport_resetFilters", "Reset filters") }}
                      </button>
                    </div>
                  </template>
                </template>
              </div>
            </div>
          </div>
        </div>
        <div
          v-if="showChartDetailActiveBar"
          class="active-controls-bar chart-detail-active-bar"
        >
          <div class="active-controls-left">
            <div
              v-if="chartDetailSortActive"
              class="active-chip-wrap"
              ref="chartDetailSortChipRef"
            >
              <button
                type="button"
                class="active-chip active-chip-sort"
                @click.stop="toggleChartDetailSortPopover"
              >
                <span>{{
                  chartDetailPrimarySortDirection === "asc" ? "↑" : "↓"
                }}</span>
                <span>{{ chartDetailPrimarySortLabel }}</span>
                <span v-if="chartDetailSorts.length > 1" class="chip-count"
                  >+{{ chartDetailSorts.length - 1 }}</span
                >
                <i class="fas fa-chevron-down chip-chevron"></i>
              </button>
              <div
                v-if="showChartDetailSortPopover"
                class="sort-popover"
                @click.stop
              >
                <div
                  v-for="(rule, index) in chartDetailSorts"
                  :key="rule.id"
                  class="sort-popover-row"
                >
                  <select
                    v-model="rule.field"
                    class="filter-select"
                    @change="onChartDetailSortChange"
                  >
                    <option
                      v-for="col in filterColumns"
                      :key="col.field"
                      :value="col.field"
                    >
                      {{ col.label }}
                    </option>
                  </select>
                  <select
                    v-model="rule.direction"
                    class="filter-select filter-select-op"
                    @change="onChartDetailSortChange"
                  >
                    <option value="asc">
                      {{ t("customReport_sortAsc", "Ascending") }}
                    </option>
                    <option value="desc">
                      {{ t("customReport_sortDesc", "Descending") }}
                    </option>
                  </select>
                  <button
                    type="button"
                    class="filter-icon-btn filter-icon-btn-danger"
                    @click="removeChartDetailSort(index)"
                  >
                    <i class="fas fa-trash"></i>
                  </button>
                </div>
                <div class="sort-popover-footer">
                  <button
                    type="button"
                    class="filter-link-btn"
                    @click="addChartDetailSort"
                  >
                    <i class="fas fa-plus"></i>
                    {{ t("customReport_addNewSort", "Add New Sort") }}
                  </button>
                  <button
                    type="button"
                    class="filter-link-btn danger"
                    @click="clearChartDetailSort"
                  >
                    <i class="fas fa-trash"></i>
                    {{ t("customReport_deleteAllSort", "Delete All sort") }}
                  </button>
                </div>
              </div>
            </div>

            <div
              v-if="chartDetailSortActive && chartDetailFilterCount > 0"
              class="active-controls-divider"
            ></div>

            <div class="active-filter-wrap" ref="chartDetailFilterBarRef">
              <button
                v-for="rule in chartDetailActiveFilterRules"
                :key="rule.id"
                type="button"
                class="active-chip active-chip-filter"
                :class="{
                  active:
                    showChartDetailBarFilter &&
                    editingChartDetailBarField === rule.field,
                }"
                @click.stop="openChartDetailFilterPanelForRule(rule)"
              >
                <span class="chip-icon">{{ columnIcon(rule.field) }}</span>
                <span>{{ columnLabel(rule.field) }}</span>
                <span class="filter-chip-dot"></span>
                <i class="fas fa-chevron-down chip-chevron"></i>
              </button>

              <button
                type="button"
                class="active-add-filter"
                @click.stop="openChartDetailFilterFromBar"
              >
                <i class="fas fa-plus"></i>
                {{ t("customReport_filter", "Filter") }}
              </button>

              <div
                v-if="showChartDetailBarFilter"
                class="filter-panel filter-panel-bar"
                @click.stop
              >
                <template
                  v-if="editingChartDetailBarField && chartDetailBarEditingRule"
                >
                  <div class="filter-panel-header">
                    <span class="filter-focused-title">
                      <span class="filter-property-icon">{{
                        columnIcon(editingChartDetailBarField)
                      }}</span>
                      {{ columnLabel(editingChartDetailBarField) }}
                    </span>
                    <button
                      type="button"
                      class="filter-icon-btn"
                      @click="closeChartDetailBarFilter"
                    >
                      <i class="fas fa-times"></i>
                    </button>
                  </div>
                  <div class="filter-focused-body">
                    <div class="filter-focused-row">
                      <span class="filter-focused-label">{{
                        columnLabel(editingChartDetailBarField)
                      }}</span>
                      <select
                        v-model="chartDetailBarEditingRule.op"
                        class="filter-select filter-select-op"
                        @change="loadChartDetailRows"
                      >
                        <option
                          v-for="op in opsForField(editingChartDetailBarField)"
                          :key="op.value"
                          :value="op.value"
                        >
                          {{ op.label }}
                        </option>
                      </select>
                      <button
                        type="button"
                        class="filter-icon-btn filter-icon-btn-danger"
                        :title="t('customReport_removeFilter', 'Remove')"
                        @click="
                          removeChartDetailBarFilterField(
                            editingChartDetailBarField,
                          )
                        "
                      >
                        <i class="fas fa-trash"></i>
                      </button>
                    </div>
                    <input
                      v-if="!isEmptyOp(chartDetailBarEditingRule.op)"
                      v-model="chartDetailBarEditingRule.value"
                      class="filter-value-input filter-focused-input"
                      :type="inputTypeForField(editingChartDetailBarField)"
                      :placeholder="
                        t('customReport_filterValue', 'Type a value...')
                      "
                      @input="handleChartDetailFilterInput"
                    />
                  </div>
                </template>
                <template v-else>
                  <div class="filter-panel-header">
                    <span>{{ t("customReport_filter", "Filter") }}</span>
                    <button
                      type="button"
                      class="filter-icon-btn"
                      @click="closeChartDetailBarFilter"
                    >
                      <i class="fas fa-times"></i>
                    </button>
                  </div>
                  <div class="filter-property-search">
                    <i class="fas fa-search"></i>
                    <input
                      v-model="chartDetailPropertyQuery"
                      type="text"
                      :placeholder="t('customReport_filterBy', 'Filter by...')"
                    />
                  </div>
                  <div class="filter-property-list">
                    <button
                      v-for="col in chartDetailFilteredColumns"
                      :key="col.field"
                      type="button"
                      class="filter-property-item"
                      :class="{
                        selected: isChartDetailColumnFiltered(col.field),
                      }"
                      @click="selectChartDetailBarFilterColumn(col.field)"
                    >
                      <span class="filter-property-icon">{{ col.icon }}</span>
                      <span>{{ col.label }}</span>
                      <i
                        v-if="isChartDetailColumnFiltered(col.field)"
                        class="fas fa-check filter-selected-check"
                      ></i>
                    </button>
                    <p
                      v-if="chartDetailFilteredColumns.length === 0"
                      class="filter-empty-hint"
                    >
                      {{ t("customReport_noColumns", "No columns found.") }}
                    </p>
                  </div>
                </template>
              </div>
            </div>
          </div>

          <button
            type="button"
            class="active-reset-btn"
            @click="resetChartDetailBar"
          >
            {{ t("customReport_reset", "Reset") }}
          </button>
        </div>

        <div v-if="!chartDetailReady" class="chart-detail-empty">
          <i class="fas fa-spinner fa-spin"></i>
          <span>{{ t("customReport_loading", "Loading...") }}</span>
        </div>
        <div
          v-else-if="chartDetailRows.length === 0 && !chartDetailLoading"
          class="chart-detail-empty"
        >
          <i class="fas fa-inbox"></i>
          <span>{{ t("fundingReport_empty") }}</span>
        </div>
        <div
          v-else
          class="table-scroll chart-detail-table"
          ref="chartDetailScrollRef"
        >
          <table
            class="transaction-table"
            :style="{ width: displayChartDetailTableWidth + 'px' }"
          >
            <colgroup>
              <col
                v-for="col in chartDetailTableColumns"
                :key="col.field"
                :style="{
                  width:
                    (chartDetailColumnWidths[col.field] || DEFAULT_COL_WEIGHT) +
                    'px',
                  minWidth:
                    (chartDetailColumnWidths[col.field] || DEFAULT_COL_WEIGHT) +
                    'px',
                }"
              />
              <col
                v-if="hasDetailPanels"
                :style="{ width: DETAIL_COL_WIDTH + 'px' }"
              />
            </colgroup>
            <thead>
              <tr>
                <th
                  v-for="col in chartDetailTableColumns"
                  :key="col.field"
                  class="sortable"
                  :class="{
                    'is-dragging': chartDetailDragField === col.field,
                    'drag-over':
                      chartDetailDragOver === col.field &&
                      chartDetailDragField !== col.field,
                  }"
                  draggable="true"
                  :title="col.label"
                  @click="onChartDetailHeaderClick(col.field)"
                  @dragstart="onChartDetailHeaderDragStart($event, col.field)"
                  @dragover.prevent="onChartDetailColumnDragOver(col.field)"
                  @drop.prevent="onChartDetailColumnDrop(col.field)"
                  @dragend="onChartDetailColumnDragEnd"
                >
                  {{ col.label }}
                  <div class="sort-icon">
                    <i
                      class="fas fa-caret-up"
                      :class="{
                        active: isChartDetailSortActive(col.field, 'asc'),
                      }"
                    ></i>
                    <i
                      class="fas fa-caret-down"
                      :class="{
                        active: isChartDetailSortActive(col.field, 'desc'),
                      }"
                    ></i>
                  </div>
                  <span
                    class="col-resize-handle"
                    @mousedown.stop.prevent="
                      startChartDetailColumnResize($event, col.field)
                    "
                    @dragstart.stop.prevent
                    @click.stop
                  ></span>
                </th>
                <th v-if="hasDetailPanels" class="action-col">
                  {{ t("customReport_th_action", "Action") }}
                </th>
              </tr>
            </thead>
            <tbody>
              <template
                v-for="(row, rowIndex) in chartDetailRows"
                :key="rowKey(row, rowIndex)"
              >
                <tr
                  :class="{
                    expanded:
                      chartDetailExpandedRowKey === rowKey(row, rowIndex),
                  }"
                >
                  <td
                    v-for="col in chartDetailTableColumns"
                    :key="col.field"
                    :class="{
                      'cell-clip':
                        col.type !== 'date' && !isStatusField(col.field),
                    }"
                    :title="cellTitle(row[col.field], col.field)"
                  >
                    <template v-if="col.type === 'date'">
                      <div>{{ formatDate(row[col.field]) }}</div>
                      <small class="time-small">{{
                        formatTime(row[col.field])
                      }}</small>
                    </template>
                    <template v-else-if="col.type === 'number'">
                      {{ formatCellNumber(row[col.field]) }}
                    </template>
                    <template v-else-if="isStatusField(col.field)">
                      <span
                        v-if="hasCellValue(row[col.field])"
                        class="status-badge"
                        :class="statusBadgeClass(row[col.field])"
                        >{{ getStatusLabel(row[col.field]) }}</span
                      >
                      <template v-else>-</template>
                    </template>
                    <template v-else>
                      {{ displayCell(row[col.field]) }}
                    </template>
                  </td>
                  <td v-if="hasDetailPanels" class="action-col">
                    <button
                      type="button"
                      class="report-detail-btn"
                      @click="toggleChartRowDetail(row, rowIndex)"
                    >
                      <i
                        :class="[
                          'fas',
                          chartDetailExpandedRowKey === rowKey(row, rowIndex)
                            ? 'fa-chevron-up'
                            : 'fa-chevron-down',
                        ]"
                      ></i>
                      {{
                        chartDetailExpandedRowKey === rowKey(row, rowIndex)
                          ? t("customReport_btn_hide", "Hide")
                          : t("customReport_btn_detail", "Detail")
                      }}
                    </button>
                  </td>
                </tr>
                <tr
                  v-if="
                    hasDetailPanels &&
                    chartDetailExpandedRowKey === rowKey(row, rowIndex)
                  "
                  class="report-detail-row"
                >
                  <td :colspan="chartDetailColspan" class="report-detail-cell">
                    <CustomReportRowDetail
                      :row="row"
                      :row-key-val="rowKey(row, rowIndex)"
                      :panels="detailPanels"
                      :container-width="chartDetailContainerWidth"
                      :panel-state="panelState"
                      :visible-detail-fields="visibleDetailFields"
                      :format-detail-cell="formatDetailCell"
                      :is-status-field="isStatusField"
                      :has-cell-value="hasCellValue"
                      :status-badge-class="statusBadgeClass"
                      :get-status-label="getStatusLabel"
                      :detail-pagination-info="detailPaginationInfo"
                      @search-input="
                        (panel, value) =>
                          onDetailSearchInput(row, rowIndex, panel, value)
                      "
                      @search="
                        (panel) =>
                          loadDetailPanel(
                            row,
                            rowKey(row, rowIndex),
                            panel,
                            true,
                          )
                      "
                      @page-change="
                        (panel, page) =>
                          changeDetailPage(row, rowIndex, panel, page)
                      "
                      @per-page-change="
                        (panel, value) =>
                          onDetailPerPageChange(row, rowIndex, panel, value)
                      "
                    />
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
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
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from "vue";
import { useRoute, useRouter } from "vue-router";
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";
import ExportProgressBanner from "@/components/common/ExportProgressBanner.vue";
import CustomReportRowDetail from "@/components/custom-report/CustomReportRowDetail.vue";
import { useAuthStore } from "@/stores/auth";
import customReportApi from "@/services/customReportApi";
import { formatCurrency } from "@/utils/helpers";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { useAsyncReportExport } from "@/composables/useAsyncReportExport";

const { t, tParams } = useAdminI18n();
const authStore = useAuthStore();
const route = useRoute();
const router = useRouter();

const hasReadonlyPermission = computed(() =>
  authStore.hasPermission("page_fundingreport_readonly"),
);
const hasExportPermission = computed(() =>
  authStore.hasPermission("page_fundingreport_export"),
);

const reportId = computed(() => route.params.reportId);
const widgetId = computed(() => route.params.widgetId);

const {
  exportJobId,
  exportStatusText,
  exportBannerVisible,
  exportCancelling,
  exportModal,
  exportPolling,
  lastExportProgress,
  startOrResumeExport,
  resumeActiveExportIfAny,
  cancelActiveExport,
  onExportModalContinue,
  onExportModalCancel,
} = useAsyncReportExport({
  getActiveExport: () =>
    customReportApi.getWidgetExportActive(reportId.value, widgetId.value),
  enqueueExport: (params) =>
    customReportApi.enqueueWidgetExport(reportId.value, widgetId.value, params),
  getExportStatus: (jobId) =>
    customReportApi.getWidgetExportStatus(
      reportId.value,
      widgetId.value,
      jobId,
    ),
  cancelExport: (jobId) =>
    customReportApi.cancelWidgetExport(reportId.value, widgetId.value, jobId),
  downloadExport: (jobId) =>
    customReportApi.downloadWidgetExport(reportId.value, widgetId.value, jobId),
  buildFilename: () => {
    const base = (widgetMeta.value?.name || "custom_report")
      .toString()
      .replace(/[^\w\-]+/g, "_");
    return `${base}_${new Date().toISOString().split("T")[0]}.csv`;
  },
  t,
});

const isExportAllRunning = computed(
  () =>
    exportPolling.value ||
    exportCancelling.value ||
    !!exportJobId.value ||
    !!exportModal.value.visible,
);

const emptyExportColumnModal = () => ({
  visible: false,
  mode: "all",
  search: "",
  selected: {},
});

const exportColumnModal = ref(emptyExportColumnModal());

const exportProgressPercent = computed(() =>
  Math.max(0, Math.min(100, Number(lastExportProgress.value?.percent || 0))),
);

const loading = ref(true);
const reportName = ref("");
const widgetMeta = ref(null);
const transactions = ref([]);
const expandedRowKey = ref(null);
const detailState = ref({});
const DETAIL_COL_WIDTH = 120;
const DETAIL_HIDDEN_FIELDS = ["userId", "salesId"];
const emptyDetailPanelState = () => ({
  loading: false,
  items: [],
  total: 0,
  page: 1,
  perPage: 10,
  totalPages: 0,
  search: "",
  error: "",
});
const currentPage = ref(1);
const perPage = ref(DEFAULT_TABLE_PER_PAGE);
const total = ref(0);
const totalPages = ref(0);
const chartPage = ref(1);
const chartPerPage = ref(DEFAULT_CHART_PER_PAGE);
const chartTotalPages = ref(1);
const isHydratingTypeView = ref(false);

const normalizeChartPerPage = (value) =>
  CHART_PER_PAGE_OPTIONS.includes(Number(value))
    ? Number(value)
    : DEFAULT_CHART_PER_PAGE;

const normalizeTablePerPage = (value) =>
  TABLE_PER_PAGE_OPTIONS.includes(Number(value))
    ? Number(value)
    : DEFAULT_TABLE_PER_PAGE;

const normalizePage = (value) => Math.max(1, Number(value) || 1);
const searchQuery = ref("");
let searchTimer = null;
let filterTimer = null;

const selectedTransactionIds = ref([]);
const showExportDropdown = ref(false);

const isAllSelected = computed(
  () =>
    transactions.value.length > 0 &&
    selectedTransactionIds.value.length === transactions.value.length,
);

const isIndeterminate = computed(
  () =>
    selectedTransactionIds.value.length > 0 &&
    selectedTransactionIds.value.length < transactions.value.length,
);

const showFilterPanel = ref(false);
const filterPanelView = ref("list");
const filterPanelReturnView = ref("menu");
const filterPropertyQuery = ref("");
const columnSearchQuery = ref("");
const filterControlRef = ref(null);
const filters = ref([]);
let filterIdSeq = 1;

const showBarFilterPanel = ref(false);
const barFilterView = ref("list");
const filterBarRef = ref(null);
const editingBarFilterField = ref("");

const barEditingRule = computed(
  () =>
    filters.value.find((rule) => rule.field === editingBarFilterField.value) ||
    null,
);

const headerFilterField = ref("");
const headerFilterPanelRef = ref(null);
const headerFilterSearch = ref("");
const headerFilterValues = ref([]);
const headerFilterSelected = ref([]);
const headerFilterLoading = ref(false);
const headerFilterPanelStyle = ref({ top: "0px", left: "0px" });
let headerFilterSearchTimer = null;

const sortActive = ref(false);
const showSortPopover = ref(false);
const sortChipRef = ref(null);
let sortIdSeq = 1;

const showDeleteModal = ref(false);
const deleting = ref(false);
const showDuplicateModal = ref(false);
const duplicating = ref(false);

const DEFAULT_SORT_DIRECTION = "desc";

const sorts = ref([]);

const columnOrder = ref([]);
const dragColumnField = ref("");
const dragOverField = ref("");

const sourceFields = computed(() => widgetMeta.value?.fields || []);

const defaultSortField = computed(() => {
  const datetime = sourceFields.value.find(
    (field) => field.fieldRole === "datetime",
  );
  return datetime?.columnName || sourceFields.value[0]?.columnName || "";
});

const fieldType = (field) => {
  const role = field.fieldRole;
  const dataType = String(field.dataType || "").toLowerCase();
  if (role === "datetime" || dataType === "datetime" || dataType === "date")
    return "date";
  if (role === "measure" || ["integer", "decimal", "number"].includes(dataType))
    return "number";
  return "text";
};

const fieldIcon = (field) => {
  const type = fieldType(field);
  if (type === "date") return "🕒";
  if (type === "number") return "#";
  return "Aa";
};

const filterColumns = computed(() => {
  const defs = {};
  sourceFields.value.forEach((field) => {
    defs[field.columnName] = {
      field: field.columnName,
      label: field.displayName || field.columnName,
      icon: fieldIcon(field),
      type: fieldType(field),
      role: field.fieldRole,
    };
  });
  const order = columnOrder.value.length
    ? columnOrder.value
    : sourceFields.value.map((field) => field.columnName);
  return order.map((name) => defs[name]).filter(Boolean);
});

const inferredTableColumns = computed(() => {
  const sample = transactions.value.find(
    (row) => row && typeof row === "object" && !Array.isArray(row),
  );
  if (!sample) return [];

  return Object.keys(sample).map((field) => ({
    field,
    label: field
      .replace(/([a-z])([A-Z])/g, "$1 $2")
      .replace(/[_-]+/g, " ")
      .replace(/^./, (char) => char.toUpperCase()),
    icon: "Aa",
    type: "text",
    role: "dimension",
  }));
});

const tableColumns = computed(() => {
  const configured = filterColumns.value.filter(
    (col) => visibleColumns.value[col.field],
  );
  if (configured.length) return configured.slice(0, MAX_TABLE_VISIBLE_COLUMNS);

  // A saved view with no visible columns must never render a checkbox-only grid.
  return filterColumns.value.length
    ? filterColumns.value.slice(0, MAX_TABLE_VISIBLE_COLUMNS)
    : inferredTableColumns.value.slice(0, MAX_TABLE_VISIBLE_COLUMNS);
});

const detailPanels = computed(() => widgetMeta.value?.detailPanels || []);
const hasDetailPanels = computed(() => detailPanels.value.length > 0);
const tableColspan = computed(() =>
  Math.max(tableColumns.value.length + 1 + (hasDetailPanels.value ? 1 : 0), 1),
);

const visibleDetailFields = (panel) =>
  (panel?.fields || []).filter((field) => {
    const name = field.columnName;
    if (name === panel.parentField || name === panel.childField) return false;
    return !DETAIL_HIDDEN_FIELDS.includes(name);
  });

const panelState = (rowKeyVal, panelId) =>
  detailState.value[rowKeyVal]?.[panelId] || emptyDetailPanelState();

const setPanelState = (rowKeyVal, panelId, patch) => {
  const current = panelState(rowKeyVal, panelId);
  detailState.value = {
    ...detailState.value,
    [rowKeyVal]: {
      ...(detailState.value[rowKeyVal] || {}),
      [panelId]: { ...current, ...patch },
    },
  };
};

const formatDetailCell = (value, field) => {
  const type = fieldType(field);
  if (type === "number") return formatCellNumber(value);
  return displayCell(value);
};

const loadDetailPanel = async (row, rowKeyVal, panel, resetPage = false) => {
  const parentValue = row?.[panel.parentField];
  const current = panelState(rowKeyVal, panel.id);
  const page = resetPage ? 1 : current.page;
  if (parentValue === null || parentValue === undefined || parentValue === "") {
    setPanelState(rowKeyVal, panel.id, {
      loading: false,
      items: [],
      total: 0,
      page: 1,
      totalPages: 0,
    });
    return;
  }
  setPanelState(rowKeyVal, panel.id, { loading: true, error: "", page });
  try {
    const perPage = current.perPage === "all" ? 9999 : current.perPage;
    const response = await customReportApi.getWidgetDetailRows(
      reportId.value,
      widgetId.value,
      {
        panelId: panel.id,
        parentValue,
        page,
        per_page: perPage,
        search: current.search,
      },
    );
    if (response.success) {
      const pagination = response.data.pagination || {};
      setPanelState(rowKeyVal, panel.id, {
        loading: false,
        items: response.data.items || [],
        total: pagination.total || 0,
        page: pagination.page || page,
        perPage: current.perPage,
        totalPages: pagination.total_pages || 0,
      });
      return;
    }
    setPanelState(rowKeyVal, panel.id, {
      loading: false,
      items: [],
      error: "failed",
    });
  } catch (err) {
    setPanelState(rowKeyVal, panel.id, {
      loading: false,
      error: err.message || "failed",
    });
  }
};

const toggleDetail = async (row, rowIndex) => {
  const key = rowKey(row, rowIndex);
  if (expandedRowKey.value === key) {
    expandedRowKey.value = null;
    return;
  }
  expandedRowKey.value = key;
  await nextTick();
  fillColumnWidths();
  await Promise.all(
    detailPanels.value.map((panel) => loadDetailPanel(row, key, panel, true)),
  );
  await nextTick();
  fillColumnWidths();
};

const toggleChartRowDetail = async (row, rowIndex) => {
  const key = rowKey(row, rowIndex);
  if (chartDetailExpandedRowKey.value === key) {
    chartDetailExpandedRowKey.value = null;
    return;
  }
  chartDetailExpandedRowKey.value = key;
  await nextTick();
  syncChartDetailWidth();
  await Promise.all(
    detailPanels.value.map((panel) => loadDetailPanel(row, key, panel, true)),
  );
  await nextTick();
  syncChartDetailWidth();
};

const onDetailSearchInput = (row, rowIndex, panel, value) => {
  setPanelState(rowKey(row, rowIndex), panel.id, { search: value });
};

const changeDetailPage = (row, rowIndex, panel, page) => {
  const key = rowKey(row, rowIndex);
  setPanelState(key, panel.id, { page });
  loadDetailPanel(row, key, panel, false);
};

const onDetailPerPageChange = (row, rowIndex, panel, value) => {
  const key = rowKey(row, rowIndex);
  setPanelState(key, panel.id, {
    perPage: value === "all" ? "all" : Number(value) || 10,
    page: 1,
  });
  loadDetailPanel(row, key, panel, true);
};

const detailPaginationInfo = (state) => {
  if (!state || !state.total)
    return t("salesList_pagination_noRecords", "No records");
  const per =
    state.perPage === "all" ? state.total : Number(state.perPage) || 10;
  if (per >= state.total) {
    return tParams(
      "salesList_pagination_totalRecords",
      "Total {total} record(s)",
      { total: state.total },
    );
  }
  const from = (state.page - 1) * per + 1;
  const to = Math.min(state.page * per, state.total);
  return tParams(
    "salesList_pagination_showing",
    "Showing {from}-{to} of {total}",
    { from, to, total: state.total },
  );
};

const COLUMN_MIN_WIDTH = 200;
const CHECKBOX_COL_WIDTH = 50;
const DEFAULT_COL_WEIGHT = 200;
const HEADER_PAD_X = 50;
const columnWidths = ref({});
const userResizedColumns = ref(false);
const tableScrollRef = ref(null);
const containerWidth = ref(0);
let tableResizeObserver = null;
let headerMeasureEl = null;

const measureHeaderWidth = (label) => {
  if (!headerMeasureEl) {
    headerMeasureEl = document.createElement("span");
    headerMeasureEl.setAttribute("aria-hidden", "true");
    headerMeasureEl.style.cssText = [
      "position:absolute",
      "left:-9999px",
      "top:0",
      "visibility:hidden",
      "white-space:nowrap",
      "font-size:14px",
      "font-weight:600",
      "text-transform:uppercase",
      "letter-spacing:0.5px",
      "padding:0",
    ].join(";");
    document.body.appendChild(headerMeasureEl);
  }
  headerMeasureEl.textContent = String(label || "");
  return Math.ceil(
    headerMeasureEl.getBoundingClientRect().width + HEADER_PAD_X,
  );
};

const headerBasedWidth = (col) =>
  Math.max(COLUMN_MIN_WIDTH, measureHeaderWidth(col.label));

const visibleColumns = ref({});

const visibleColumnCount = computed(
  () => Object.values(visibleColumns.value).filter(Boolean).length,
);

const isLastVisibleColumn = (field) =>
  !!visibleColumns.value[field] && visibleColumnCount.value <= 1;

const tableTotalWidth = computed(
  () =>
    CHECKBOX_COL_WIDTH +
    (hasDetailPanels.value ? DETAIL_COL_WIDTH : 0) +
    tableColumns.value.reduce(
      (sum, col) => sum + (columnWidths.value[col.field] || COLUMN_MIN_WIDTH),
      0,
    ),
);

const displayTableWidth = computed(() =>
  Math.max(tableTotalWidth.value, containerWidth.value || 0),
);

const fillColumnWidths = () => {
  const el = tableScrollRef.value;
  if (el) containerWidth.value = el.clientWidth;
  if (userResizedColumns.value) return;
  const cols = tableColumns.value;
  if (!cols.length) return;

  const next = {};
  cols.forEach((col) => {
    next[col.field] = headerBasedWidth(col);
  });

  const available =
    (el?.clientWidth || 0) -
    CHECKBOX_COL_WIDTH -
    (hasDetailPanels.value ? DETAIL_COL_WIDTH : 0);
  const used = cols.reduce((sum, col) => sum + next[col.field], 0);
  if (available > used) {
    const last = cols[cols.length - 1].field;
    next[last] += available - used;
  }

  columnWidths.value = next;
};

const onColumnDragStart = (event, field) => {
  dragColumnField.value = field;
  dragOverField.value = "";
  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = "move";
    event.dataTransfer.setData("text/plain", field);
  }
};

const onColumnDragOver = (field) => {
  if (!dragColumnField.value || dragColumnField.value === field) return;
  dragOverField.value = field;
};

const onColumnDrop = (targetField) => {
  const sourceField = dragColumnField.value;
  if (!sourceField || sourceField === targetField) {
    dragColumnField.value = "";
    dragOverField.value = "";
    return;
  }
  const order = [...columnOrder.value];
  const fromIndex = order.indexOf(sourceField);
  const toIndex = order.indexOf(targetField);
  if (fromIndex < 0 || toIndex < 0) {
    dragColumnField.value = "";
    dragOverField.value = "";
    return;
  }
  skipHeaderSort = true;
  order.splice(fromIndex, 1);
  order.splice(toIndex, 0, sourceField);
  columnOrder.value = order;
  dragColumnField.value = "";
  dragOverField.value = "";
  schedulePersistViewConfig();
  nextTick(() => fillColumnWidths());
};

let skipHeaderSort = false;

const onHeaderClick = (field) => {
  if (skipHeaderSort) {
    skipHeaderSort = false;
    return;
  }
  sortBy(field);
};

const onHeaderDragStart = (event, field) => {
  if (resizingColumn) {
    event.preventDefault();
    return;
  }
  onColumnDragStart(event, field);
};

const onColumnDragEnd = () => {
  dragColumnField.value = "";
  dragOverField.value = "";
  window.setTimeout(() => {
    skipHeaderSort = false;
  }, 50);
};

let resizingColumn = null;
let resizeStartX = 0;
let resizeStartWidth = 0;

const onColumnResizeMove = (event) => {
  if (!resizingColumn) return;
  userResizedColumns.value = true;
  const delta = event.clientX - resizeStartX;
  const nextWidth = Math.max(COLUMN_MIN_WIDTH, resizeStartWidth + delta);
  columnWidths.value = {
    ...columnWidths.value,
    [resizingColumn]: nextWidth,
  };
};

const stopColumnResize = () => {
  if (!resizingColumn) return;
  resizingColumn = null;
  document.body.style.cursor = "";
  document.body.style.userSelect = "";
  window.removeEventListener("mousemove", onColumnResizeMove);
  window.removeEventListener("mouseup", stopColumnResize);
};

const startColumnResize = (event, field) => {
  resizingColumn = field;
  resizeStartX = event.clientX;
  resizeStartWidth = columnWidths.value[field] || COLUMN_MIN_WIDTH;
  document.body.style.cursor = "col-resize";
  document.body.style.userSelect = "none";
  window.addEventListener("mousemove", onColumnResizeMove);
  window.addEventListener("mouseup", stopColumnResize);
};

const filteredFilterColumns = computed(() => {
  const q = filterPropertyQuery.value.trim().toLowerCase();
  if (!q) return filterColumns.value;
  return filterColumns.value.filter(
    (col) =>
      col.label.toLowerCase().includes(q) ||
      col.field.toLowerCase().includes(q),
  );
});

const filteredColumnToggleColumns = computed(() => {
  const q = columnSearchQuery.value.trim().toLowerCase();
  if (!q) return filterColumns.value;
  return filterColumns.value.filter(
    (col) =>
      col.label.toLowerCase().includes(q) ||
      col.field.toLowerCase().includes(q),
  );
});

const activeFilterCount = computed(
  () => filters.value.filter((rule) => hasFilterValue(rule)).length,
);

const activeFilterRules = computed(() =>
  filters.value.filter((rule) => hasFilterValue(rule)),
);

const showActiveBar = computed(
  () => sortActive.value || activeFilterCount.value > 0,
);

const showViewActiveBar = computed(() => {
  if (!activeKind.value) return false;
  return activeKind.value === "chart"
    ? activeFilterCount.value > 0
    : showActiveBar.value;
});

const primarySort = computed(() => sorts.value[0] || null);

const primarySortDirection = computed(
  () => primarySort.value?.direction || DEFAULT_SORT_DIRECTION,
);

const primarySortLabel = computed(() => {
  const field = primarySort.value?.field || defaultSortField.value;
  const col = filterColumns.value.find((c) => c.field === field);
  return col?.label || field;
});

const isSortActive = (field, direction) => {
  if (!sortActive.value) return false;
  return sorts.value.some(
    (rule) => rule.field === field && rule.direction === direction,
  );
};

const nextAvailableSortField = () => {
  const used = new Set(sorts.value.map((rule) => rule.field));
  const available = filterColumns.value.find((col) => !used.has(col.field));
  return available?.field || defaultSortField.value;
};

const columnLabel = (field) => {
  const col = filterColumns.value.find((c) => c.field === field);
  return col?.label || field;
};

const columnIcon = (field) => {
  const col = filterColumns.value.find((c) => c.field === field);
  return col?.icon || "Aa";
};

const TEXT_OPS = [
  { value: "contains", label: "contains" },
  { value: "not_contains", label: "does not contain" },
  { value: "equals", label: "is" },
  { value: "not_equals", label: "is not" },
  { value: "in", label: "is any of" },
  { value: "not_in", label: "is none of" },
  { value: "starts_with", label: "starts with" },
  { value: "ends_with", label: "ends with" },
  { value: "is_empty", label: "is empty" },
  { value: "is_not_empty", label: "is not empty" },
];

const NUMBER_OPS = [
  { value: "equals", label: "=" },
  { value: "not_equals", label: "≠" },
  { value: "gt", label: ">" },
  { value: "lt", label: "<" },
  { value: "gte", label: "≥" },
  { value: "lte", label: "≤" },
  { value: "in", label: "is any of" },
  { value: "not_in", label: "is none of" },
  { value: "is_empty", label: "is empty" },
  { value: "is_not_empty", label: "is not empty" },
];

const DATE_OPS = [
  { value: "equals", label: "is" },
  { value: "not_equals", label: "is not" },
  { value: "gt", label: "is after" },
  { value: "lt", label: "is before" },
  { value: "gte", label: "is on or after" },
  { value: "lte", label: "is on or before" },
  { value: "is_empty", label: "is empty" },
  { value: "is_not_empty", label: "is not empty" },
];

const SELECT_OPS = [
  { value: "equals", label: "is" },
  { value: "not_equals", label: "is not" },
  { value: "in", label: "is any of" },
  { value: "not_in", label: "is none of" },
  { value: "contains", label: "contains" },
  { value: "not_contains", label: "does not contain" },
  { value: "is_empty", label: "is empty" },
  { value: "is_not_empty", label: "is not empty" },
];

const opsForField = (field) => {
  const col = filterColumns.value.find((c) => c.field === field);
  if (!col) return TEXT_OPS;
  if (col.type === "number") return NUMBER_OPS;
  if (col.type === "date") return DATE_OPS;
  if (col.type === "select") return SELECT_OPS;
  return TEXT_OPS;
};

const defaultOpForField = (field) => {
  const col = filterColumns.value.find((c) => c.field === field);
  if (col?.type === "number" || col?.type === "date" || col?.type === "select")
    return "equals";
  return "contains";
};

const inputTypeForField = (field) => {
  const col = filterColumns.value.find((c) => c.field === field);
  if (col?.type === "number") return "number";
  if (col?.type === "date") return "date";
  return "text";
};

const isEmptyOp = (op) => op === "is_empty" || op === "is_not_empty";

const isMultiValueOp = (op) => op === "in" || op === "not_in";

const filterValueDisplay = (value) => {
  if (Array.isArray(value)) return value.join(", ");
  return value == null ? "" : String(value);
};

const parseMultiFilterValue = (raw) =>
  String(raw || "")
    .split(",")
    .map((part) => part.trim())
    .filter(Boolean)
    .slice(0, 100);

const hasFilterValue = (rule) => {
  if (!rule) return false;
  if (isEmptyOp(rule.op)) return true;
  if (isMultiValueOp(rule.op)) {
    return Array.isArray(rule.value)
      ? rule.value.length > 0
      : String(rule.value ?? "").trim() !== "";
  }
  return String(rule.value ?? "").trim() !== "";
};

const isColumnFiltered = (field) =>
  filters.value.some((rule) => rule.field === field && hasFilterValue(rule));

const dataSourceTitle = computed(
  () =>
    widgetMeta.value?.dataSourceName ||
    widgetMeta.value?.dataSourceObject ||
    t("customReport_transactionsTitle", "All Transactions"),
);

const pageTitle = computed(() => {
  const source =
    widgetMeta.value?.name ||
    widgetMeta.value?.dataSourceName ||
    widgetMeta.value?.dataSourceObject;
  if (source) return source;
  return reportName.value || t("menu_customReport", "Custom Report");
});

const TYPE_ID_PATTERN = /^[A-Za-z0-9_-]+$/;
const MAX_WIDGET_TYPES = 20;
const CHART_PER_PAGE_OPTIONS = [50, 100, 150];
const TABLE_PER_PAGE_OPTIONS = [50, 100, 200];
const MAX_TABLE_VISIBLE_COLUMNS = 10;
const DEFAULT_CHART_PER_PAGE = 50;
const DEFAULT_TABLE_PER_PAGE = 100;

const activeView = ref("");
const widgetTypes = ref([]);
const typeViewConfigs = ref({});
const showEditTypesModal = ref(false);
const pendingDeleteTypeId = ref("");
const renamingTypeId = ref("");
const renamingTypeLabel = ref("");
const renamingTypeWidth = ref(0);
const renameTypeInputRef = ref(null);
const renamingTypeStyle = computed(() =>
  renamingTypeWidth.value ? { width: `${renamingTypeWidth.value}px` } : {},
);
const newTypeKind = ref("table");
const newTypeLabel = ref("");
const dragTypeId = ref("");
const dragOverTypeId = ref("");

const activeWidgetType = computed(
  () =>
    widgetTypes.value.find((type) => type.id === activeView.value) ||
    widgetTypes.value[0] ||
    null,
);
const activeKind = computed(() => {
  const kind = activeWidgetType.value?.kind;
  return kind === "chart" || kind === "table" ? kind : "";
});

const chartType = ref("");
const chartXField = ref("");
const chartYField = ref("");
const chartSortBy = ref("label_asc");
const chartYSortBy = ref("label_asc");
const chartOmitZero = ref(false);
const chartGroupBy = ref("none");
const chartCumulative = ref(false);
const chartRange = ref("auto");
const chartRangeMin = ref("");
const chartRangeMax = ref("");
const chartColorScheme = ref("auto");

const CHART_COLORFUL = ["#3b82f6", "#eab308", "#22c55e", "#8b5cf6", "#f97316"];
const chartColorSchemes = [
  { value: "auto", label: "Auto", colors: [] },
  { value: "colorful", label: "Colorful", colors: CHART_COLORFUL },
  {
    value: "colorless",
    label: "Colorless",
    colors: ["#e5e7eb", "#d1d5db", "#9ca3af", "#6b7280", "#374151"],
  },
  {
    value: "blue",
    label: "Blue",
    colors: ["#dbeafe", "#93c5fd", "#3b82f6", "#1d4ed8", "#1e3a8a"],
  },
  {
    value: "yellow",
    label: "Yellow",
    colors: ["#fef9c3", "#fde047", "#eab308", "#ca8a04", "#854d0e"],
  },
  {
    value: "green",
    label: "Green",
    colors: ["#dcfce7", "#86efac", "#22c55e", "#15803d", "#14532d"],
  },
  {
    value: "purple",
    label: "Purple",
    colors: [
      "var(--color-brand-soft)",
      "#c4b5fd",
      "#8b5cf6",
      "#6d28d9",
      "#4c1d95",
    ],
  },
  {
    value: "teal",
    label: "Teal",
    colors: ["#ccfbf1", "#5eead4", "#14b8a6", "#0f766e", "#134e4a"],
  },
  {
    value: "orange",
    label: "Orange",
    colors: ["#ffedd5", "#fdba74", "#f97316", "#c2410c", "#7c2d12"],
  },
  {
    value: "pink",
    label: "Pink",
    colors: ["#fce7f3", "#f9a8d4", "#ec4899", "#be185d", "#831843"],
  },
  {
    value: "red",
    label: "Red",
    colors: ["#fee2e2", "#fca5a5", "#ef4444", "#b91c1c", "#7f1d1d"],
  },
];

const chartColorLabel = computed(() => {
  const scheme = chartColorSchemes.find(
    (item) => item.value === chartColorScheme.value,
  );
  return scheme?.label || t("customReport_auto", "Auto");
});

const activeChartColors = computed(() => {
  const scheme = chartColorSchemes.find(
    (item) => item.value === chartColorScheme.value,
  );
  return scheme?.colors?.length ? scheme.colors : CHART_COLORFUL;
});

const chartReadyStyle = computed(() => {
  const next = {};
  activeChartColors.value.forEach((color, index) => {
    next[`--serie-${index}`] = color;
  });
  if (hoveredChartSerie.value !== null) {
    next["--hot"] = hoveredChartSerie.value;
  }
  return next;
});

const chartPicker = ref("");
const chartPickerQuery = ref("");
const COMPACT_CHART_PICKERS = [
  "sortBy",
  "ySortBy",
  "range",
  "rangeCustom",
  "color",
];
const isFilterPanelTall = computed(() => {
  if (["menu", "list", "sort"].includes(filterPanelView.value)) return false;
  if (
    filterPanelView.value === "chart" &&
    COMPACT_CHART_PICKERS.includes(chartPicker.value)
  ) {
    return false;
  }
  return true;
});

const isBlankRangeValue = (value) => {
  if (value === "" || value === null || value === undefined) return true;
  const text = String(value).trim().toLowerCase();
  return text === "" || text === "none" || text === "null";
};

const parseOptionalNumber = (value) => {
  if (isBlankRangeValue(value)) return null;
  const num = Number(value);
  return Number.isFinite(num) ? num : null;
};

const hasCustomRangeValues = () =>
  parseOptionalNumber(chartRangeMin.value) !== null ||
  parseOptionalNumber(chartRangeMax.value) !== null;

const revertEmptyCustomRange = () => {
  if (chartRange.value !== "custom") return;
  if (hasCustomRangeValues()) return;
  chartRange.value = "auto";
  chartRangeMin.value = "";
  chartRangeMax.value = "";
};

const chartSortOptions = computed(() => [
  { value: "manual", label: t("customReport_sortManual", "Manual") },
  { value: "label_asc", label: t("customReport_sortNameAsc", "Name A → Z") },
  { value: "label_desc", label: t("customReport_sortNameDesc", "Name Z → A") },
  {
    value: "value_asc",
    label: t("customReport_sortDistinctAsc", "Distinct Low → High"),
  },
  {
    value: "value_desc",
    label: t("customReport_sortDistinctDesc", "Distinct High → Low"),
  },
]);

const chartXFieldLabel = computed(() => {
  const col = chartDimensionFields.value.find(
    (item) => item.field === chartXField.value,
  );
  return col?.label || t("customReport_selectField", "Select field");
});

const chartYFieldLabel = computed(() => {
  if (chartYField.value === "count") return t("customReport_count", "Count");
  const col = filterColumns.value.find(
    (item) => item.field === chartYField.value,
  );
  return col?.label || t("customReport_selectField", "Select field");
});

const chartSortByLabel = computed(() => {
  const opt = chartSortOptions.value.find(
    (item) => item.value === chartSortBy.value,
  );
  return opt?.label || t("customReport_sortBy", "Sort by");
});

const chartYSortByLabel = computed(() => {
  const opt = chartSortOptions.value.find(
    (item) => item.value === chartYSortBy.value,
  );
  return opt?.label || t("customReport_sortBy", "Sort by");
});

const chartGroupByLabel = computed(() => {
  if (!chartGroupBy.value || chartGroupBy.value === "none")
    return t("customReport_none", "None");
  const col = chartGroupFields.value.find(
    (item) => item.field === chartGroupBy.value,
  );
  return col?.label || t("customReport_none", "None");
});

const chartRangeLabel = computed(() =>
  chartRange.value === "custom" && hasCustomRangeValues()
    ? t("customReport_customRange", "Set custom range")
    : t("customReport_auto", "Auto"),
);

const chartPickerTitle = computed(() => {
  if (chartPicker.value === "xField" || chartPicker.value === "yField") {
    return t("customReport_whatToShow", "What to show");
  }
  if (chartPicker.value === "sortBy" || chartPicker.value === "ySortBy") {
    return t("customReport_sortBy", "Sort by");
  }
  if (chartPicker.value === "groupBy")
    return t("customReport_groupBy", "Group by");
  if (chartPicker.value === "range" || chartPicker.value === "rangeCustom") {
    return t("customReport_range", "Range");
  }
  if (chartPicker.value === "color") return t("customReport_color", "Color");
  return t("customReport_viewOptions", "Options");
});

const chartPickerOptions = computed(() => {
  if (chartPicker.value === "xField") {
    return [
      {
        value: "",
        label: t("customReport_selectField", "Select field"),
        icon: "",
      },
      ...chartDimensionFields.value.map((col) => ({
        value: col.field,
        label: col.label,
        icon: col.icon,
      })),
    ];
  }
  if (chartPicker.value === "yField") {
    return [
      { value: "count", label: t("customReport_count", "Count"), icon: "#" },
      ...filterColumns.value.map((col) => ({
        value: col.field,
        label: col.label,
        icon: col.icon,
      })),
    ];
  }
  if (chartPicker.value === "sortBy" || chartPicker.value === "ySortBy")
    return chartSortOptions.value;
  if (chartPicker.value === "groupBy") {
    return [
      { value: "none", label: t("customReport_none", "None"), icon: "" },
      ...chartGroupFields.value.map((col) => ({
        value: col.field,
        label: col.label,
        icon: col.icon,
      })),
    ];
  }
  if (chartPicker.value === "range") {
    return [
      { value: "auto", label: t("customReport_auto", "Auto"), icon: "" },
      {
        value: "custom",
        label: t("customReport_customRange", "Set custom range"),
        icon: "",
      },
    ];
  }
  return [];
});

const chartPickerValue = computed(() => {
  if (chartPicker.value === "xField") return chartXField.value;
  if (chartPicker.value === "yField") return chartYField.value;
  if (chartPicker.value === "sortBy") return chartSortBy.value;
  if (chartPicker.value === "ySortBy") return chartYSortBy.value;
  if (chartPicker.value === "groupBy") return chartGroupBy.value;
  if (chartPicker.value === "range" || chartPicker.value === "rangeCustom")
    return chartRange.value;
  if (chartPicker.value === "color") return chartColorScheme.value;
  return "";
});

const filteredChartPickerOptions = computed(() => {
  const q = chartPickerQuery.value.trim().toLowerCase();
  if (!q) return chartPickerOptions.value;
  return chartPickerOptions.value.filter((opt) =>
    String(opt.label || "")
      .toLowerCase()
      .includes(q),
  );
});

const openChartPicker = (key) => {
  if (chartPicker.value === key) {
    closeChartPicker();
    return;
  }
  chartPickerQuery.value = "";
  chartPicker.value = key;
};

const resetChartPicker = () => {
  revertEmptyCustomRange();
  chartPicker.value = "";
  chartPickerQuery.value = "";
};

const closeChartPicker = () => {
  if (chartPicker.value === "rangeCustom") {
    revertEmptyCustomRange();
    chartPicker.value = "range";
    chartPickerQuery.value = "";
    return;
  }
  resetChartPicker();
};

const selectChartPickerValue = (value) => {
  if (chartPicker.value === "xField") chartXField.value = value;
  if (chartPicker.value === "yField") chartYField.value = value;
  if (chartPicker.value === "sortBy") chartSortBy.value = value;
  if (chartPicker.value === "ySortBy") chartYSortBy.value = value;
  if (chartPicker.value === "groupBy") chartGroupBy.value = value;
  if (chartPicker.value === "color") chartColorScheme.value = value;
  if (chartPicker.value === "range") {
    if (value === "custom") {
      chartRange.value = "custom";
      chartPicker.value = "rangeCustom";
      chartPickerQuery.value = "";
      return;
    }
    chartRange.value = "auto";
    chartRangeMin.value = "";
    chartRangeMax.value = "";
  }
};

const chartDimensionFields = computed(() =>
  filterColumns.value.filter(
    (col) => col.role !== "measure" && col.type !== "number",
  ),
);

const chartGroupFields = computed(() =>
  chartDimensionFields.value.filter((col) => col.field !== chartXField.value),
);

const chartMeasureFields = computed(() =>
  filterColumns.value.filter(
    (col) => col.role === "measure" || col.type === "number",
  ),
);

const isChartReady = computed(() =>
  Boolean(chartType.value && chartXField.value && chartYField.value),
);

const chartEmptyHint = computed(() => {
  if (!chartType.value) {
    return t(
      "customReport_chartPickType",
      "Choose a chart type from the filter menu to get started.",
    );
  }
  if (!chartXField.value || !chartYField.value) {
    return t(
      "customReport_chartPickAxes",
      "Select X axis and Y axis fields to display chart data.",
    );
  }
  return t("customReport_chartEmpty", "No chart data yet.");
});

const chartResult = ref({
  labels: [],
  series: [],
  max: 1,
  ready: false,
  truncated: false,
  totalLabels: 0,
});
const chartLabels = computed(() => chartResult.value.labels || []);
const CHART_COL_MIN_WIDTH = 56;
const chartVerticalMinWidth = computed(() =>
  Math.max(chartLabels.value.length * CHART_COL_MIN_WIDTH, 0),
);
const chartSeries = computed(() => chartResult.value.series || []);
const hiddenChartSeries = ref({});
const isChartSerieHidden = (name) => !!hiddenChartSeries.value[name];
const toggleChartSerie = (name) => {
  const next = { ...hiddenChartSeries.value };
  if (next[name]) delete next[name];
  else next[name] = true;
  hiddenChartSeries.value = next;
};
const visibleChartSeries = computed(() =>
  chartSeries.value.filter((serie) => !hiddenChartSeries.value[serie.name]),
);
const CHART_LEGEND_MAX = 30;
const chartLegendExpanded = ref(false);
const chartLegendNeedsToggle = computed(
  () => chartSeries.value.length > CHART_LEGEND_MAX,
);
const visibleChartLegend = computed(() =>
  chartLegendExpanded.value || !chartLegendNeedsToggle.value
    ? chartSeries.value
    : chartSeries.value.slice(0, CHART_LEGEND_MAX),
);
const chartTotalLabels = computed(
  () => Number(chartResult.value.totalLabels) || chartLabels.value.length,
);
const chartRangeFrom = computed(() => {
  if (!chartTotalLabels.value) return 0;
  return (chartPage.value - 1) * chartPerPage.value + 1;
});
const chartRangeTo = computed(() =>
  Math.min(chartPage.value * chartPerPage.value, chartTotalLabels.value),
);
const visibleChartPages = computed(() => {
  const pages = [];
  const maxVisible = 5;
  const total = chartTotalPages.value;
  const current = chartPage.value;
  if (total <= maxVisible) {
    for (let i = 1; i <= total; i++) pages.push(i);
  } else if (current <= 3) {
    for (let i = 1; i <= 4; i++) pages.push(i);
    pages.push("...");
    pages.push(total);
  } else if (current >= total - 2) {
    pages.push(1);
    pages.push("...");
    for (let i = total - 3; i <= total; i++) pages.push(i);
  } else {
    pages.push(1);
    pages.push("...");
    pages.push(current - 1);
    pages.push(current);
    pages.push(current + 1);
    pages.push("...");
    pages.push(total);
  }
  return pages;
});

const chartLabelTotals = computed(() =>
  chartLabels.value.map((_, labelIndex) =>
    visibleChartSeries.value.reduce(
      (sum, serie) => sum + Number(serie.values?.[labelIndex] || 0),
      0,
    ),
  ),
);

const niceChartMax = (value) => {
  const max = Number(value) || 0;
  if (max <= 0) return 1;
  const magnitude = 10 ** Math.floor(Math.log10(max));
  return Math.ceil(max / magnitude) * magnitude;
};

const chartScale = computed(() => {
  const stackedMax = visibleChartSeries.value.length
    ? niceChartMax(Math.max(0, ...chartLabelTotals.value, 0))
    : 0;
  const dataMax = stackedMax || Number(chartResult.value.max) || 1;
  if (chartRange.value !== "custom") {
    return { min: 0, max: dataMax };
  }
  const customMin = parseOptionalNumber(chartRangeMin.value);
  const customMax = parseOptionalNumber(chartRangeMax.value);
  if (customMin === null && customMax === null) {
    return { min: 0, max: dataMax };
  }
  const min = customMin ?? 0;
  const max = customMax ?? dataMax;
  if (!(max > min)) {
    return { min: 0, max: dataMax };
  }
  return { min, max };
});

const chartMaxValue = computed(() => chartScale.value.max);

const chartTicks = computed(() => {
  const { min, max } = chartScale.value;
  return [max, (max + min) / 2, min];
});

const barPercent = (value) => {
  const { min, max } = chartScale.value;
  const span = max - min;
  if (!span) return 0;
  return Math.max(0, Math.min(100, ((Number(value) - min) / span) * 100));
};

const chartStacks = computed(() =>
  chartLabels.value.map((_, labelIndex) =>
    chartSeries.value.reduce((items, serie, serieIndex) => {
      const value = Number(serie.values?.[labelIndex] || 0);
      if (!(value > 0) || hiddenChartSeries.value[serie.name]) return items;
      items.push({
        name: serie.name,
        value,
        serieIndex,
        colorClass: "serie-" + (serieIndex % 5),
        percent: barPercent(value),
      });
      return items;
    }, []),
  ),
);

const formatChartValue = (value) => {
  const num = Number(value);
  if (!Number.isFinite(num)) return "0";
  if (Number.isInteger(num)) return String(num);
  return num.toFixed(1);
};

const chartTooltip = ref(null);
const showChartDetailModal = ref(false);
const chartDetailLoading = ref(false);
const chartDetailReady = ref(false);
let chartDetailLoadSeq = 0;
let ignoreChartDetailOverlayClose = false;
const chartDetailRows = ref([]);
const chartDetailExpandedRowKey = ref(null);
const chartDetailScrollRef = ref(null);
const chartDetailContainerWidth = ref(0);
let chartDetailResizeObserver = null;
const chartDetailLabel = ref("");
const chartDetailSerie = ref("");
const chartDetailSearch = ref("");
const chartDetailFilters = ref([]);
const chartDetailBaseFilters = ref([]);
const showChartDetailFilter = ref(false);
const chartDetailFilterView = ref("menu");
const chartDetailPropertyQuery = ref("");
const chartDetailFilterRef = ref(null);
const chartDetailVisibleColumns = ref({});
const chartDetailSorts = ref([]);
const chartDetailSortActive = ref(false);
let chartDetailSearchTimer = null;
let chartDetailFilterTimer = null;
let chartDetailFilterId = 1;
let chartDetailSortId = 1;
const chartDetailTitle = computed(
  () => chartDetailLabel.value || dataSourceTitle.value,
);
const chartDetailFilterCount = computed(
  () =>
    chartDetailFilters.value.filter(
      (rule) => isEmptyOp(rule.op) || String(rule.value ?? "").trim() !== "",
    ).length,
);
const chartDetailColumnOrder = ref([]);
const chartDetailColumnWidths = ref({});
const chartDetailDragField = ref("");
const chartDetailDragOver = ref("");
let skipChartDetailHeaderSort = false;
let chartDetailResizing = null;
let chartDetailResizeStartX = 0;
let chartDetailResizeStartWidth = 0;

const chartDetailOrderedColumns = computed(() => {
  const defs = {};
  filterColumns.value.forEach((col) => {
    defs[col.field] = col;
  });
  const order = chartDetailColumnOrder.value.length
    ? chartDetailColumnOrder.value
    : filterColumns.value.map((col) => col.field);
  return order.map((field) => defs[field]).filter(Boolean);
});
const chartDetailFilteredColumns = computed(() => {
  const q = chartDetailPropertyQuery.value.trim().toLowerCase();
  if (!q) return chartDetailOrderedColumns.value;
  return chartDetailOrderedColumns.value.filter(
    (col) =>
      col.label.toLowerCase().includes(q) ||
      col.field.toLowerCase().includes(q),
  );
});

const chartDetailTableColumns = computed(() =>
  chartDetailOrderedColumns.value.filter(
    (col) => chartDetailVisibleColumns.value[col.field],
  ),
);

const chartDetailColspan = computed(() =>
  Math.max(
    chartDetailTableColumns.value.length + (hasDetailPanels.value ? 1 : 0),
    1,
  ),
);

const displayChartDetailTableWidth = computed(() =>
  Math.max(
    (hasDetailPanels.value ? DETAIL_COL_WIDTH : 0) +
      chartDetailTableColumns.value.reduce(
        (sum, col) =>
          sum + (chartDetailColumnWidths.value[col.field] || COLUMN_MIN_WIDTH),
        0,
      ),
    chartDetailContainerWidth.value || COLUMN_MIN_WIDTH,
  ),
);

const syncChartDetailWidth = () => {
  chartDetailContainerWidth.value =
    chartDetailScrollRef.value?.clientWidth || 0;
};

watch(
  chartDetailScrollRef,
  (el) => {
    if (chartDetailResizeObserver) {
      chartDetailResizeObserver.disconnect();
      chartDetailResizeObserver = null;
    }
    if (!el) {
      chartDetailContainerWidth.value = 0;
      return;
    }
    if (typeof ResizeObserver === "undefined") {
      syncChartDetailWidth();
      return;
    }
    chartDetailResizeObserver = new ResizeObserver(syncChartDetailWidth);
    chartDetailResizeObserver.observe(el);
    syncChartDetailWidth();
  },
  { flush: "post" },
);
const chartDetailSortChipRef = ref(null);
const chartDetailFilterBarRef = ref(null);
const showChartDetailSortPopover = ref(false);
const showChartDetailBarFilter = ref(false);
const editingChartDetailBarField = ref("");
const chartDetailActiveFilterRules = computed(() =>
  chartDetailFilters.value.filter(
    (rule) => isEmptyOp(rule.op) || String(rule.value ?? "").trim() !== "",
  ),
);
const showChartDetailActiveBar = computed(
  () =>
    chartDetailSortActive.value ||
    chartDetailActiveFilterRules.value.length > 0,
);
const chartDetailPrimarySort = computed(
  () => chartDetailSorts.value[0] || null,
);
const chartDetailPrimarySortDirection = computed(
  () => chartDetailPrimarySort.value?.direction || "asc",
);
const chartDetailPrimarySortLabel = computed(() => {
  const field = chartDetailPrimarySort.value?.field || "";
  const col = filterColumns.value.find((c) => c.field === field);
  return col?.label || field;
});
const chartDetailBarEditingRule = computed(
  () =>
    chartDetailFilters.value.find(
      (rule) => rule.field === editingChartDetailBarField.value,
    ) || null,
);
const isChartDetailColumnFiltered = (field) =>
  chartDetailFilters.value.some(
    (rule) =>
      rule.field === field &&
      (isEmptyOp(rule.op) || String(rule.value ?? "").trim() !== ""),
  );
const isChartDetailSortActive = (field, direction) => {
  if (!chartDetailSortActive.value || !chartDetailPrimarySort.value)
    return false;
  return (
    chartDetailPrimarySort.value.field === field &&
    chartDetailPrimarySort.value.direction === direction
  );
};
const allChartDetailColumnsVisible = computed(
  () =>
    filterColumns.value.length > 0 &&
    filterColumns.value.every(
      (col) => chartDetailVisibleColumns.value[col.field],
    ),
);
const someChartDetailColumnsVisible = computed(() =>
  filterColumns.value.some((col) => chartDetailVisibleColumns.value[col.field]),
);
const isLastChartDetailColumn = (field) =>
  chartDetailVisibleColumns.value[field] &&
  filterColumns.value.filter(
    (col) => chartDetailVisibleColumns.value[col.field],
  ).length === 1;

const snapshotChartDetailColumns = () => {
  const next = {};
  const widths = {};
  const order = columnOrder.value.length
    ? [...columnOrder.value]
    : filterColumns.value.map((col) => col.field);
  filterColumns.value.forEach((col) => {
    next[col.field] = visibleColumns.value[col.field] !== false;
    widths[col.field] = columnWidths.value[col.field] || headerBasedWidth(col);
  });
  if (!Object.values(next).some(Boolean) && filterColumns.value[0]) {
    next[filterColumns.value[0].field] = true;
  }
  chartDetailVisibleColumns.value = next;
  chartDetailColumnOrder.value = order;
  chartDetailColumnWidths.value = widths;
};

const toggleAllChartDetailColumns = (checked) => {
  const next = { ...chartDetailVisibleColumns.value };
  filterColumns.value.forEach((col, index) => {
    next[col.field] = checked || index === 0;
  });
  chartDetailVisibleColumns.value = next;
};

const toggleChartDetailColumn = (field, checked) => {
  if (!checked && isLastChartDetailColumn(field)) return;
  chartDetailVisibleColumns.value = {
    ...chartDetailVisibleColumns.value,
    [field]: checked,
  };
};

const nextChartDetailSortField = () => {
  const used = new Set(chartDetailSorts.value.map((rule) => rule.field));
  const available = filterColumns.value.find((col) => !used.has(col.field));
  return available?.field || defaultSortField.value;
};

const hoveredChartSerie = ref(null);
const chartTooltipHover = ref(false);
const chartTooltipRowsRef = ref(null);
let hideTooltipTimer = 0;
const CHART_TOOLTIP_WIDTH = 260;
const CHART_TOOLTIP_HEIGHT = 280;

const hideChartTooltip = () => {
  clearTimeout(hideTooltipTimer);
  chartTooltip.value = null;
  chartTooltipHover.value = false;
  hoveredChartSerie.value = null;
};

const scheduleHideChartTooltip = () => {
  chartTooltipHover.value = false;
  clearTimeout(hideTooltipTimer);
  hideTooltipTimer = window.setTimeout(() => {
    hideChartTooltip();
  }, 200);
};

const onChartTooltipEnter = () => {
  clearTimeout(hideTooltipTimer);
  chartTooltipHover.value = true;
};

const onChartTooltipWheel = (event) => {
  const list = chartTooltipRowsRef.value;
  if (!list || list.scrollHeight <= list.clientHeight) return;
  event.preventDefault();
  list.scrollTop += event.deltaY;
};

const tooltipPosition = (event) => {
  const stage = event.currentTarget.closest(".chart-stage");
  if (!stage) return null;
  const stageRect = stage.getBoundingClientRect();
  const col =
    event.currentTarget.closest(".chart-col, .chart-h-row") ||
    event.currentTarget;
  const anchorRect = col.getBoundingClientRect();
  const placeRight =
    anchorRect.right - stageRect.left + 12 + CHART_TOOLTIP_WIDTH <=
    stage.clientWidth;
  const x = placeRight
    ? anchorRect.right - stageRect.left + 4
    : Math.max(8, anchorRect.left - stageRect.left - CHART_TOOLTIP_WIDTH - 4);
  const y = Math.min(
    Math.max(8, event.clientY - stageRect.top - 16),
    Math.max(8, stage.clientHeight - CHART_TOOLTIP_HEIGHT),
  );
  return { x, y };
};

const showChartTooltip = (event, label, labelIndex) => {
  clearTimeout(hideTooltipTimer);
  const pos = tooltipPosition(event);
  if (!pos) return;
  if (chartTooltip.value?.labelIndex === labelIndex) {
    chartTooltip.value = {
      ...chartTooltip.value,
      x: pos.x,
      y: pos.y,
    };
    return;
  }
  const stack = chartStacks.value[labelIndex] || [];
  chartTooltip.value = {
    label,
    labelIndex,
    value: chartLabelTotals.value[labelIndex] || 0,
    items: stack.length > 1 ? stack : [],
    x: pos.x,
    y: pos.y,
  };
};

const hoverChartSlice = (event, label, labelIndex, serieIndex) => {
  hoveredChartSerie.value = serieIndex;
  showChartTooltip(event, label, labelIndex);
};

const chartPointFilter = (label, serieName) => {
  const next = [];
  const isEmpty = !label || label === "(empty)";
  next.push({
    field: chartXField.value,
    op: isEmpty ? "is_empty" : "equals",
    value: isEmpty ? "" : label,
  });
  if (
    chartGroupBy.value &&
    chartGroupBy.value !== "none" &&
    serieName &&
    serieName !== "Value"
  ) {
    const groupEmpty = serieName === "(empty)";
    next.push({
      field: chartGroupBy.value,
      op: groupEmpty ? "is_empty" : "equals",
      value: groupEmpty ? "" : serieName,
    });
  }
  return next;
};

const buildChartDetailFilterPayload = () =>
  chartDetailFilters.value
    .filter(
      (rule) => isEmptyOp(rule.op) || String(rule.value ?? "").trim() !== "",
    )
    .map((rule) => ({
      field: rule.field,
      op: rule.op,
      value: rule.value,
    }));

const loadChartDetailRows = async (baseFilters) => {
  if (!showChartDetailModal.value) return;
  const seq = ++chartDetailLoadSeq;
  const lockedBase = Array.isArray(baseFilters)
    ? baseFilters
    : [...chartDetailBaseFilters.value];
  chartDetailLoading.value = true;
  try {
    const params = {
      page: 1,
      per_page: 100,
      filters: JSON.stringify([
        ...lockedBase,
        ...buildChartDetailFilterPayload(),
      ]),
    };
    if (chartDetailSearch.value) params.search = chartDetailSearch.value;
    if (chartDetailSortActive.value && chartDetailSorts.value.length) {
      params.sorts = JSON.stringify(
        chartDetailSorts.value.map((rule) => ({
          field: rule.field,
          direction: rule.direction,
        })),
      );
      params.sort_field = chartDetailSorts.value[0].field;
      params.sort_direction = chartDetailSorts.value[0].direction;
    }
    const response = await customReportApi.getWidgetRows(
      reportId.value,
      widgetId.value,
      params,
    );
    if (seq !== chartDetailLoadSeq || !showChartDetailModal.value) return;
    chartDetailRows.value = response.success ? response.data.items || [] : [];
    chartDetailReady.value = true;
  } catch (err) {
    if (seq !== chartDetailLoadSeq || !showChartDetailModal.value) return;
    chartDetailRows.value = [];
    chartDetailReady.value = true;
    console.error(err);
  } finally {
    if (seq === chartDetailLoadSeq) {
      chartDetailLoading.value = false;
    }
  }
};

const openChartBarDetail = async (label, serieName) => {
  hideChartTooltip();
  clearTimeout(chartDetailSearchTimer);
  clearTimeout(chartDetailFilterTimer);
  const baseFilters = chartPointFilter(label, serieName);
  chartDetailLoadSeq += 1;
  ignoreChartDetailOverlayClose = true;
  chartDetailLabel.value = label;
  chartDetailSerie.value = serieName || "";
  chartDetailSearch.value = "";
  chartDetailFilters.value = [];
  chartDetailBaseFilters.value = baseFilters;
  chartDetailSorts.value = [];
  chartDetailSortActive.value = false;
  showChartDetailFilter.value = false;
  showChartDetailSortPopover.value = false;
  showChartDetailBarFilter.value = false;
  editingChartDetailBarField.value = "";
  chartDetailFilterView.value = "menu";
  chartDetailPropertyQuery.value = "";
  chartDetailReady.value = false;
  snapshotChartDetailColumns();
  showChartDetailModal.value = true;
  chartDetailRows.value = [];
  await nextTick();
  await loadChartDetailRows(baseFilters);
  requestAnimationFrame(() => {
    ignoreChartDetailOverlayClose = false;
  });
};

const onChartDetailOverlayClick = () => {
  if (ignoreChartDetailOverlayClose) return;
  closeChartDetailModal();
};

const closeChartDetailModal = () => {
  chartDetailLoadSeq += 1;
  ignoreChartDetailOverlayClose = false;
  showChartDetailModal.value = false;
  chartDetailExpandedRowKey.value = null;
  chartDetailContainerWidth.value = 0;
  showChartDetailFilter.value = false;
  showChartDetailSortPopover.value = false;
  showChartDetailBarFilter.value = false;
  editingChartDetailBarField.value = "";
  chartDetailReady.value = false;
  chartDetailLoading.value = false;
  chartDetailRows.value = [];
  chartDetailLabel.value = "";
  chartDetailSerie.value = "";
  chartDetailSearch.value = "";
  chartDetailFilters.value = [];
  chartDetailBaseFilters.value = [];
  chartDetailSorts.value = [];
  chartDetailSortActive.value = false;
  chartDetailVisibleColumns.value = {};
  chartDetailColumnOrder.value = [];
  chartDetailColumnWidths.value = {};
  chartDetailDragField.value = "";
  chartDetailDragOver.value = "";
  stopChartDetailColumnResize();
  chartDetailFilterView.value = "menu";
  clearTimeout(chartDetailSearchTimer);
  clearTimeout(chartDetailFilterTimer);
};

const handleChartDetailSearch = () => {
  clearTimeout(chartDetailSearchTimer);
  chartDetailSearchTimer = setTimeout(() => {
    loadChartDetailRows();
  }, 300);
};

const handleChartDetailFilterInput = () => {
  clearTimeout(chartDetailFilterTimer);
  chartDetailFilterTimer = setTimeout(() => {
    loadChartDetailRows();
  }, 300);
};

const toggleChartDetailFilter = () => {
  showChartDetailFilter.value = !showChartDetailFilter.value;
  chartDetailFilterView.value = "menu";
  chartDetailPropertyQuery.value = "";
};

const closeChartDetailFilter = () => {
  showChartDetailFilter.value = false;
  chartDetailFilterView.value = "menu";
  chartDetailPropertyQuery.value = "";
};

const openChartDetailFilterList = () => {
  chartDetailFilterView.value = chartDetailFilters.value.length
    ? "list"
    : "add";
  chartDetailPropertyQuery.value = "";
};

const openChartDetailColumnPanel = () => {
  chartDetailPropertyQuery.value = "";
  chartDetailFilterView.value = "columns";
};

const backChartDetailAddFilter = () => {
  chartDetailFilterView.value = chartDetailFilters.value.length
    ? "list"
    : "menu";
  chartDetailPropertyQuery.value = "";
};

const startChartDetailSort = () => {
  chartDetailSorts.value = defaultSortField.value
    ? [
        {
          id: chartDetailSortId++,
          field: defaultSortField.value,
          direction: "asc",
        },
      ]
    : [];
  chartDetailSortActive.value = chartDetailSorts.value.length > 0;
  loadChartDetailRows();
};

const addChartDetailSort = () => {
  if (chartDetailSorts.value.length >= filterColumns.value.length) return;
  chartDetailSorts.value = [
    ...chartDetailSorts.value,
    {
      id: chartDetailSortId++,
      field: nextChartDetailSortField(),
      direction: "asc",
    },
  ];
  chartDetailSortActive.value = true;
  loadChartDetailRows();
};

const removeChartDetailSort = (index) => {
  if (chartDetailSorts.value.length <= 1) {
    clearChartDetailSort();
    return;
  }
  chartDetailSorts.value = chartDetailSorts.value.filter((_, i) => i !== index);
  chartDetailSortActive.value = true;
  loadChartDetailRows();
};

const clearChartDetailSort = () => {
  chartDetailSortActive.value = false;
  chartDetailSorts.value = [];
  loadChartDetailRows();
};

const onChartDetailSortChange = () => {
  chartDetailSortActive.value = true;
  loadChartDetailRows();
};

const onChartDetailHeaderClick = (field) => {
  if (skipChartDetailHeaderSort) {
    skipChartDetailHeaderSort = false;
    return;
  }
  showChartDetailSortPopover.value = false;
  showChartDetailBarFilter.value = false;
  const primary = chartDetailSorts.value[0];
  if (primary && primary.field === field) {
    chartDetailSorts.value = [
      {
        ...primary,
        direction: primary.direction === "asc" ? "desc" : "asc",
      },
      ...chartDetailSorts.value.slice(1),
    ];
  } else {
    const rest = chartDetailSorts.value.filter(
      (rule, index) => index > 0 && rule.field !== field,
    );
    chartDetailSorts.value = [
      { id: chartDetailSortId++, field, direction: "asc" },
      ...rest,
    ];
  }
  chartDetailSortActive.value = true;
  loadChartDetailRows();
};

const onChartDetailHeaderDragStart = (event, field) => {
  if (chartDetailResizing) {
    event.preventDefault();
    return;
  }
  chartDetailDragField.value = field;
  chartDetailDragOver.value = "";
  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = "move";
    event.dataTransfer.setData("text/plain", field);
  }
};

const onChartDetailColumnDragOver = (field) => {
  if (!chartDetailDragField.value || chartDetailDragField.value === field)
    return;
  chartDetailDragOver.value = field;
};

const onChartDetailColumnDrop = (targetField) => {
  const sourceField = chartDetailDragField.value;
  if (!sourceField || sourceField === targetField) {
    chartDetailDragField.value = "";
    chartDetailDragOver.value = "";
    return;
  }
  const order = [...chartDetailColumnOrder.value];
  const fromIndex = order.indexOf(sourceField);
  const toIndex = order.indexOf(targetField);
  if (fromIndex < 0 || toIndex < 0) {
    chartDetailDragField.value = "";
    chartDetailDragOver.value = "";
    return;
  }
  skipChartDetailHeaderSort = true;
  order.splice(fromIndex, 1);
  order.splice(toIndex, 0, sourceField);
  chartDetailColumnOrder.value = order;
  chartDetailDragField.value = "";
  chartDetailDragOver.value = "";
};

const onChartDetailColumnDragEnd = () => {
  chartDetailDragField.value = "";
  chartDetailDragOver.value = "";
  window.setTimeout(() => {
    skipChartDetailHeaderSort = false;
  }, 50);
};

const onChartDetailColumnResizeMove = (event) => {
  if (!chartDetailResizing) return;
  const delta = event.clientX - chartDetailResizeStartX;
  const nextWidth = Math.max(
    COLUMN_MIN_WIDTH,
    chartDetailResizeStartWidth + delta,
  );
  chartDetailColumnWidths.value = {
    ...chartDetailColumnWidths.value,
    [chartDetailResizing]: nextWidth,
  };
};

const stopChartDetailColumnResize = () => {
  if (!chartDetailResizing) return;
  chartDetailResizing = null;
  document.body.style.cursor = "";
  document.body.style.userSelect = "";
  window.removeEventListener("mousemove", onChartDetailColumnResizeMove);
  window.removeEventListener("mouseup", stopChartDetailColumnResize);
};

const startChartDetailColumnResize = (event, field) => {
  chartDetailResizing = field;
  chartDetailResizeStartX = event.clientX;
  chartDetailResizeStartWidth =
    chartDetailColumnWidths.value[field] || COLUMN_MIN_WIDTH;
  document.body.style.cursor = "col-resize";
  document.body.style.userSelect = "none";
  window.addEventListener("mousemove", onChartDetailColumnResizeMove);
  window.addEventListener("mouseup", stopChartDetailColumnResize);
};

const toggleChartDetailSortPopover = () => {
  showChartDetailBarFilter.value = false;
  editingChartDetailBarField.value = "";
  showChartDetailSortPopover.value = !showChartDetailSortPopover.value;
};

const openChartDetailFilterFromBar = () => {
  showChartDetailSortPopover.value = false;
  showChartDetailFilter.value = false;
  showChartDetailBarFilter.value = true;
  editingChartDetailBarField.value = "";
  chartDetailPropertyQuery.value = "";
};

const openChartDetailFilterPanelForRule = (rule) => {
  showChartDetailSortPopover.value = false;
  showChartDetailFilter.value = false;
  showChartDetailBarFilter.value = true;
  editingChartDetailBarField.value = rule?.field || "";
  chartDetailPropertyQuery.value = "";
};

const selectChartDetailBarFilterColumn = (field) => {
  const existing = chartDetailFilters.value.find(
    (rule) => rule.field === field,
  );
  if (!existing) {
    chartDetailFilters.value = [
      ...chartDetailFilters.value,
      {
        id: chartDetailFilterId++,
        field,
        op: defaultOpForField(field),
        value: "",
      },
    ];
  }
  editingChartDetailBarField.value = field;
};

const closeChartDetailBarFilter = () => {
  showChartDetailBarFilter.value = false;
  editingChartDetailBarField.value = "";
  chartDetailPropertyQuery.value = "";
};

const removeChartDetailBarFilterField = (field) => {
  const index = chartDetailFilters.value.findIndex(
    (rule) => rule.field === field,
  );
  if (index < 0) return;
  removeChartDetailFilter(index);
  editingChartDetailBarField.value = "";
  if (!chartDetailFilters.value.length) {
    closeChartDetailBarFilter();
  }
};

const resetChartDetailBar = () => {
  showChartDetailSortPopover.value = false;
  showChartDetailBarFilter.value = false;
  editingChartDetailBarField.value = "";
  chartDetailPropertyQuery.value = "";
  chartDetailSearch.value = "";
  chartDetailFilters.value = [];
  chartDetailFilterView.value = "menu";
  chartDetailSortActive.value = false;
  chartDetailSorts.value = [];
  loadChartDetailRows();
};

const addChartDetailFilter = (field) => {
  chartDetailFilters.value = [
    ...chartDetailFilters.value,
    {
      id: chartDetailFilterId++,
      field,
      op: defaultOpForField(field),
      value: "",
    },
  ];
  chartDetailFilterView.value = "list";
  chartDetailPropertyQuery.value = "";
};

const removeChartDetailFilter = (index) => {
  chartDetailFilters.value = chartDetailFilters.value.filter(
    (_, i) => i !== index,
  );
  loadChartDetailRows();
};

const resetChartDetailFilters = () => {
  chartDetailFilters.value = [];
  chartDetailFilterView.value = "menu";
  loadChartDetailRows();
};

const onChartDetailFieldChange = (rule) => {
  const allowed = opsForField(rule.field).map((op) => op.value);
  const nextOp = allowed.includes(rule.op)
    ? rule.op
    : defaultOpForField(rule.field);
  chartDetailFilters.value = chartDetailFilters.value.map((item) =>
    item.id === rule.id
      ? { ...item, field: rule.field, op: nextOp, value: "" }
      : item,
  );
  loadChartDetailRows();
};

const openChartSettings = () => {
  showFilterPanel.value = true;
  filterPanelView.value = "chart";
  filterPropertyQuery.value = "";
  showSortPopover.value = false;
  showBarFilterPanel.value = false;
  resetChartPicker();
};

const emptyChartView = () => ({
  chartType: "",
  xField: "",
  yField: "",
  sortBy: "label_asc",
  ySortBy: "label_asc",
  omitZero: false,
  groupBy: "none",
  cumulative: false,
  range: "auto",
  rangeMin: null,
  rangeMax: null,
  colorScheme: "auto",
  page: 1,
  perPage: DEFAULT_CHART_PER_PAGE,
});

const currentChartView = () => ({
  chartType: chartType.value,
  xField: chartXField.value,
  yField: chartYField.value,
  sortBy: chartSortBy.value,
  ySortBy: chartYSortBy.value,
  omitZero: chartOmitZero.value,
  groupBy: chartGroupBy.value || "none",
  cumulative: chartCumulative.value,
  range:
    chartRange.value === "custom" && hasCustomRangeValues() ? "custom" : "auto",
  rangeMin: parseOptionalNumber(chartRangeMin.value),
  rangeMax: parseOptionalNumber(chartRangeMax.value),
  colorScheme: chartColorScheme.value,
  page: normalizePage(chartPage.value),
  perPage: normalizeChartPerPage(chartPerPage.value),
});

const sanitizeClientTypes = (raw) => {
  if (!Array.isArray(raw)) return [];
  const seen = {};
  const next = [];
  raw.forEach((item) => {
    const id = String(item?.id || "").trim();
    const kind =
      item?.kind === "chart" ? "chart" : item?.kind === "table" ? "table" : "";
    const label = String(item?.label || item?.name || "").trim();
    if (!TYPE_ID_PATTERN.test(id) || !kind || !label || seen[id]) return;
    seen[id] = true;
    const type = { id, kind, label: label.slice(0, 255) };
    const createdBy = String(item?.createdBy || "").trim();
    if (createdBy) type.createdBy = createdBy.slice(0, 36);
    const createdByName = String(item?.createdByName || "").trim();
    if (createdByName) type.createdByName = createdByName.slice(0, 255);
    const createdAt = String(item?.createdAt || "").trim();
    if (createdAt) type.createdAt = createdAt.slice(0, 32);
    next.push(type);
  });
  return next;
};

const currentAdminCreator = () => {
  const user = authStore.user || {};
  const createdBy = String(user.id || user.userId || "").trim();
  const createdByName = String(
    user.fullName || user.username || createdBy || "",
  ).trim();
  return {
    createdBy: createdBy.slice(0, 36),
    createdByName: createdByName.slice(0, 255),
    createdAt: new Date().toISOString().slice(0, 19).replace("T", " "),
  };
};

const serializeWidgetType = (type) => {
  const next = {
    id: type.id,
    label: type.label,
    kind: type.kind,
  };
  if (type.createdBy) next.createdBy = type.createdBy;
  if (type.createdByName) next.createdByName = type.createdByName;
  if (type.createdAt) next.createdAt = type.createdAt;
  return next;
};

const currentQueryState = () => ({
  filters: filters.value.map((rule) => ({
    field: rule.field,
    op: rule.op,
    value: rule.value ?? "",
  })),
  sorts: sorts.value.map((rule) => ({
    field: rule.field,
    direction: rule.direction === "asc" ? "asc" : "desc",
  })),
  sortActive: !!sortActive.value,
});

const applyQueryState = (saved) => {
  const known = new Set(sourceFields.value.map((field) => field.columnName));
  const nextFilters = [];
  if (Array.isArray(saved?.filters)) {
    saved.filters.forEach((rule) => {
      const field = String(rule?.field || "");
      if (known.size && !known.has(field)) return;
      nextFilters.push({
        id: filterIdSeq++,
        field,
        op: String(rule?.op || "contains"),
        value: normalizeFilterRuleValue(
          String(rule?.op || "contains"),
          rule?.value ?? "",
        ),
      });
    });
  }
  filters.value = nextFilters;
  const nextSorts = [];
  if (Array.isArray(saved?.sorts)) {
    saved.sorts.forEach((rule) => {
      const field = String(rule?.field || "");
      if (known.size && !known.has(field)) return;
      nextSorts.push({
        id: sortIdSeq++,
        field,
        direction: rule?.direction === "asc" ? "asc" : "desc",
      });
    });
  }
  sorts.value = nextSorts.length
    ? nextSorts
    : defaultSortField.value
      ? [
          {
            id: sortIdSeq++,
            field: defaultSortField.value,
            direction: DEFAULT_SORT_DIRECTION,
          },
        ]
      : [];
  sortActive.value = !!saved?.sortActive && nextSorts.length > 0;
};

const currentVisibleColumnNames = () => {
  const names = sourceFields.value.map((field) => field.columnName);
  const shown = names.filter((name) => visibleColumns.value[name] !== false);
  return (shown.length ? shown : names.slice(0, 1)).slice(
    0,
    MAX_TABLE_VISIBLE_COLUMNS,
  );
};

const ACCOUNTS_DEFAULT_VISIBLE = [
  "accountId",
  "accountNumber",
  "firstName",
  "lastName",
  "email",
  "platformName",
  "salesName",
  "groupName",
  "status",
  "balance",
];

const defaultVisibleColumnNames = () => {
  const names = sourceFields.value.map((field) => field.columnName);
  if (!names.length) return [];
  const objectName = widgetMeta.value?.dataSourceObject || "";
  if (objectName === "vReportAccounts") {
    const preferred = ACCOUNTS_DEFAULT_VISIBLE.filter((name) =>
      names.includes(name),
    );
    if (preferred.length) return preferred.slice(0, MAX_TABLE_VISIBLE_COLUMNS);
  }
  return names.slice(0, MAX_TABLE_VISIBLE_COLUMNS);
};

const applySavedVisibleColumns = (saved) => {
  const names = sourceFields.value.map((field) => field.columnName);
  if (!names.length) return;
  const vis = {};
  if (!Array.isArray(saved)) {
    const defaults = defaultVisibleColumnNames();
    names.forEach((name) => {
      vis[name] = defaults.includes(name);
    });
    visibleColumns.value = vis;
    return;
  }
  const shown = new Set(
    saved
      .filter((name) => names.includes(name))
      .slice(0, MAX_TABLE_VISIBLE_COLUMNS),
  );
  if (!shown.size) shown.add(names[0]);
  names.forEach((name) => {
    vis[name] = shown.has(name);
  });
  visibleColumns.value = vis;
};

const snapshotCurrentTypeView = () => {
  const id = activeView.value;
  if (!id) return;
  const next = { ...typeViewConfigs.value };
  const query = currentQueryState();
  next[id] =
    activeKind.value === "chart"
      ? { ...currentChartView(), ...query }
      : {
          columnOrder: [...columnOrder.value],
          visibleColumns: currentVisibleColumnNames(),
          page: normalizePage(currentPage.value),
          perPage: normalizeTablePerPage(perPage.value),
          ...query,
        };
  typeViewConfigs.value = next;
};

const applyChartFields = (chart) => {
  const next = chart || {};
  chartType.value = next.chartType || "";
  chartXField.value = next.xField || "";
  chartYField.value = next.yField || "";
  chartSortBy.value = next.sortBy || "label_asc";
  chartYSortBy.value = next.ySortBy || "label_asc";
  chartOmitZero.value = !!next.omitZero;
  chartGroupBy.value = next.groupBy || "none";
  chartCumulative.value = !!next.cumulative;
  const savedMin = parseOptionalNumber(next.rangeMin);
  const savedMax = parseOptionalNumber(next.rangeMax);
  const hasSavedRange = savedMin !== null || savedMax !== null;
  chartRange.value =
    next.range === "custom" && hasSavedRange ? "custom" : "auto";
  chartRangeMin.value =
    chartRange.value === "custom" && savedMin !== null ? String(savedMin) : "";
  chartRangeMax.value =
    chartRange.value === "custom" && savedMax !== null ? String(savedMax) : "";
  chartColorScheme.value = next.colorScheme || "auto";
  chartPage.value = normalizePage(next.page);
  chartPerPage.value = normalizeChartPerPage(next.perPage);
};

const hydrateTypeView = (typeId) => {
  const type = widgetTypes.value.find((item) => item.id === typeId);
  if (!type) return;
  const saved = typeViewConfigs.value[typeId] || {};
  isHydratingTypeView.value = true;
  try {
    if (type.kind === "chart") {
      applyChartFields(saved);
    } else {
      applySavedColumnOrder(saved.columnOrder);
      applySavedVisibleColumns(saved.visibleColumns);
      currentPage.value = normalizePage(saved.page);
      perPage.value = normalizeTablePerPage(saved.perPage);
    }
    applyQueryState(saved);
  } finally {
    isHydratingTypeView.value = false;
  }
};

const persistTypeQuery = () => {
  snapshotCurrentTypeView();
  schedulePersistViewConfig();
};

const currentViewConfig = () => {
  snapshotCurrentTypeView();
  return {
    activeView: activeView.value,
    types: widgetTypes.value.map((type) => serializeWidgetType(type)),
    views: { ...typeViewConfigs.value },
  };
};

const viewTypeStorageKey = () =>
  `customReport:widgetView:${widgetId.value || ""}`;

const readStoredViewType = () => {
  try {
    const raw = sessionStorage.getItem(viewTypeStorageKey());
    return TYPE_ID_PATTERN.test(raw || "") ? raw : null;
  } catch (err) {
    return null;
  }
};

const writeStoredViewType = (type) => {
  if (!TYPE_ID_PATTERN.test(type || "")) return;
  try {
    sessionStorage.setItem(viewTypeStorageKey(), type);
  } catch (err) {
    return;
  }
};

const applyViewConfig = (config) => {
  let next = config || {};
  if (typeof next === "string") {
    try {
      next = JSON.parse(next) || {};
    } catch (err) {
      next = {};
    }
  }
  const types = sanitizeClientTypes(next.types);
  widgetTypes.value = types;
  const views = next.views && typeof next.views === "object" ? next.views : {};
  const nextViews = {};
  types.forEach((type) => {
    nextViews[type.id] =
      views[type.id] ||
      (type.kind === "chart" ? emptyChartView() : { columnOrder: [] });
  });
  typeViewConfigs.value = nextViews;
  const ids = new Set(types.map((type) => type.id));
  const stored = readStoredViewType();
  const fromStore = stored && ids.has(stored) ? stored : null;
  const fromConfig = ids.has(next.activeView)
    ? next.activeView
    : types[0]?.id || "";
  activeView.value = fromStore || fromConfig;
  if (activeView.value) hydrateTypeView(activeView.value);
};

const applySavedColumnOrder = (saved) => {
  const names = sourceFields.value.map((field) => field.columnName);
  if (!names.length || !Array.isArray(saved) || !saved.length) return;
  const known = new Set(names);
  const next = saved.filter((name) => known.has(name));
  names.forEach((name) => {
    if (!next.includes(name)) next.push(name);
  });
  columnOrder.value = next;
};

const applySourceFields = () => {
  const fields = sourceFields.value;
  columnOrder.value = fields.map((field) => field.columnName);
  const defaults = new Set(defaultVisibleColumnNames());
  const vis = {};
  const widths = {};
  fields.forEach((field) => {
    vis[field.columnName] = defaults.has(field.columnName);
    widths[field.columnName] = headerBasedWidth({
      field: field.columnName,
      label: field.displayName || field.columnName,
    });
  });
  if (!Object.values(vis).some(Boolean) && fields[0]) {
    vis[fields[0].columnName] = true;
  }
  visibleColumns.value = vis;
  columnWidths.value = widths;
  const sortField = defaultSortField.value;
  sorts.value = sortField
    ? [{ id: sortIdSeq++, field: sortField, direction: DEFAULT_SORT_DIRECTION }]
    : [];
};

const restoreVisibleColumns = () => {
  if (!filterColumns.value.length) return;
  const next = { ...visibleColumns.value };
  const shown = filterColumns.value.filter(
    (col) => visibleColumns.value[col.field],
  );
  const visibleFields = new Set(
    (shown.length ? shown : filterColumns.value)
      .slice(0, MAX_TABLE_VISIBLE_COLUMNS)
      .map((col) => col.field),
  );
  filterColumns.value.forEach((col) => {
    next[col.field] = visibleFields.has(col.field);
  });
  visibleColumns.value = next;
};

const persistViewConfig = async () => {
  if (!reportId.value || !widgetId.value || !widgetMeta.value) return;
  try {
    await customReportApi.updateWidget(reportId.value, widgetId.value, {
      name: widgetMeta.value.name,
      dataSourceId: widgetMeta.value.dataSourceId,
      viewConfig: currentViewConfig(),
    });
  } catch (err) {
    console.error(err);
  }
};

let viewConfigTimer = null;
const skipPersist = ref(true);
const schedulePersistViewConfig = () => {
  if (skipPersist.value) return;
  clearTimeout(viewConfigTimer);
  viewConfigTimer = setTimeout(() => {
    persistViewConfig();
  }, 400);
};

const selectWidgetView = (typeId) => {
  if (!typeId || typeId === activeView.value) return;
  snapshotCurrentTypeView();
  activeView.value = typeId;
  writeStoredViewType(typeId);
  skipPersist.value = true;
  hydrateTypeView(typeId);
  nextTick(() => {
    skipPersist.value = false;
    persistViewConfig();
    if (activeKind.value === "chart") {
      openChartSettings();
      loadChart();
      return;
    }
    loadTransactions();
    fillColumnWidths();
  });
};

const openEditWidgetTypes = () => {
  showFilterPanel.value = false;
  newTypeLabel.value = "";
  newTypeKind.value = "table";
  showEditTypesModal.value = true;
};

const closeEditWidgetTypes = () => {
  if (pendingDeleteTypeId.value) return;
  showEditTypesModal.value = false;
  dragTypeId.value = "";
  dragOverTypeId.value = "";
  persistViewConfig();
};

const nextTypeId = (kind) => {
  const used = new Set(widgetTypes.value.map((type) => type.id));
  if (!used.has(kind)) return kind;
  let index = 2;
  while (used.has(`${kind}_${index}`)) index += 1;
  return `${kind}_${index}`;
};

const nextTypeLabel = (kind) => {
  const base =
    kind === "chart"
      ? t("customReport_type_chart", "Chart")
      : t("customReport_type_table", "Table");
  const used = new Set(
    widgetTypes.value.map((type) => type.label.toLowerCase()),
  );
  if (!used.has(base.toLowerCase())) return base;
  let index = 2;
  while (used.has(`${base} ${index}`.toLowerCase())) index += 1;
  return `${base} ${index}`;
};

const nextCopyTypeLabel = (label) => {
  const base =
    String(label || "").trim() || t("customReport_type_table", "Table");
  const used = new Set(
    widgetTypes.value.map((type) => type.label.toLowerCase()),
  );
  let candidate = `${base} copy`;
  if (!used.has(candidate.toLowerCase())) return candidate.slice(0, 255);
  let index = 2;
  while (used.has(`${base} copy ${index}`.toLowerCase())) index += 1;
  return `${base} copy ${index}`.slice(0, 255);
};

const cloneTypeViewConfig = (saved, kind) => {
  const source = saved && typeof saved === "object" ? saved : {};
  try {
    return JSON.parse(JSON.stringify(source));
  } catch (err) {
    return kind === "chart"
      ? { ...emptyChartView(), filters: [], sorts: [], sortActive: false }
      : {
          columnOrder: [],
          visibleColumns: [],
          filters: [],
          sorts: [],
          sortActive: false,
        };
  }
};

const duplicateActiveWidgetType = () => {
  const source = activeWidgetType.value;
  if (!source?.id || widgetTypes.value.length >= MAX_WIDGET_TYPES) return;
  snapshotCurrentTypeView();
  const kind = source.kind === "chart" ? "chart" : "table";
  const id = nextTypeId(kind);
  const label = nextCopyTypeLabel(source.label);
  const copied = cloneTypeViewConfig(typeViewConfigs.value[source.id], kind);
  widgetTypes.value = [
    ...widgetTypes.value,
    { id, label, kind, ...currentAdminCreator() },
  ];
  typeViewConfigs.value = {
    ...typeViewConfigs.value,
    [id]: copied,
  };
  closeFilterPanel();
  selectWidgetView(id);
};

let renameArmedAt = 0;

const onWidgetTypeClick = (type, event) => {
  if (activeView.value !== type.id) {
    selectWidgetView(type.id);
    renameArmedAt = Date.now() + 450;
    return;
  }
  if (event.detail < 2 || Date.now() < renameArmedAt) return;
  startRenameWidgetType(type, event);
};

const startRenameWidgetType = (type, event) => {
  const width = event?.currentTarget?.getBoundingClientRect?.().width || 0;
  renamingTypeWidth.value = Math.round(width);
  renamingTypeId.value = type.id;
  renamingTypeLabel.value = type.label;
  nextTick(() => {
    const el = renameTypeInputRef.value;
    if (!el) return;
    el.focus();
    el.select();
  });
};

const stopRenameWidgetType = () => {
  renamingTypeId.value = "";
  renamingTypeLabel.value = "";
  renamingTypeWidth.value = 0;
  renameArmedAt = Date.now() + 300;
};

const commitRenameWidgetType = () => {
  const id = renamingTypeId.value;
  if (!id) return;
  const type = widgetTypes.value.find((item) => item.id === id);
  const fallback =
    type?.kind === "chart"
      ? t("customReport_type_chart", "Chart")
      : t("customReport_type_table", "Table");
  const label = renamingTypeLabel.value.trim() || fallback;
  renameWidgetType(id, label);
  stopRenameWidgetType();
};

const cancelRenameWidgetType = () => {
  stopRenameWidgetType();
};

const renameWidgetType = (id, label) => {
  widgetTypes.value = widgetTypes.value.map((type) =>
    type.id === id ? { ...type, label: String(label).slice(0, 255) } : type,
  );
  schedulePersistViewConfig();
};

const normalizeWidgetTypeLabel = (id) => {
  const type = widgetTypes.value.find((item) => item.id === id);
  if (!type) return;
  const label = type.label.trim();
  if (label) {
    renameWidgetType(id, label);
    return;
  }
  renameWidgetType(
    id,
    type.kind === "chart"
      ? t("customReport_type_chart", "Chart")
      : t("customReport_type_table", "Table"),
  );
};

const addWidgetType = () => {
  if (widgetTypes.value.length >= MAX_WIDGET_TYPES) return;
  const kind = newTypeKind.value === "chart" ? "chart" : "table";
  const label = newTypeLabel.value.trim() || nextTypeLabel(kind);
  const id = nextTypeId(kind);
  widgetTypes.value = [
    ...widgetTypes.value,
    { id, label: label.slice(0, 255), kind, ...currentAdminCreator() },
  ];
  typeViewConfigs.value = {
    ...typeViewConfigs.value,
    [id]:
      kind === "chart"
        ? { ...emptyChartView(), filters: [], sorts: [], sortActive: false }
        : {
            columnOrder: [...columnOrder.value],
            visibleColumns: defaultVisibleColumnNames(),
            page: 1,
            perPage: DEFAULT_TABLE_PER_PAGE,
            filters: [],
            sorts: [],
            sortActive: false,
          },
  };
  newTypeLabel.value = "";
  persistViewConfig();
  if (!activeView.value) {
    selectWidgetView(id);
  }
};

const pendingDeleteTypeLabel = computed(() => {
  const type = widgetTypes.value.find(
    (item) => item.id === pendingDeleteTypeId.value,
  );
  return type?.label || "";
});

const askRemoveWidgetType = (type) => {
  if (!type?.id) return;
  pendingDeleteTypeId.value = type.id;
};

const closeRemoveWidgetTypeConfirm = () => {
  pendingDeleteTypeId.value = "";
};

const confirmRemoveWidgetType = () => {
  const id = pendingDeleteTypeId.value;
  pendingDeleteTypeId.value = "";
  removeWidgetType(id);
};

const removeWidgetType = (id) => {
  if (!id) return;
  const next = widgetTypes.value.filter((type) => type.id !== id);
  if (next.length === widgetTypes.value.length) return;
  if (activeView.value === id) {
    const fallback = next[0];
    activeView.value = fallback?.id || "";
    if (fallback) {
      writeStoredViewType(fallback.id);
      hydrateTypeView(fallback.id);
    }
  }
  widgetTypes.value = next;
  const nextViews = { ...typeViewConfigs.value };
  delete nextViews[id];
  typeViewConfigs.value = nextViews;
  persistViewConfig();
};

const onTypeDragStart = (event, id) => {
  dragTypeId.value = id;
  dragOverTypeId.value = "";
  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = "move";
    event.dataTransfer.setData("text/plain", id);
  }
};

const onTypeDragOver = (id) => {
  if (!dragTypeId.value || dragTypeId.value === id) return;
  dragOverTypeId.value = id;
};

const onTypeDrop = (targetId) => {
  const sourceId = dragTypeId.value;
  if (!sourceId || sourceId === targetId) {
    dragTypeId.value = "";
    dragOverTypeId.value = "";
    return;
  }
  const order = widgetTypes.value.map((type) => type.id);
  const fromIndex = order.indexOf(sourceId);
  const toIndex = order.indexOf(targetId);
  if (fromIndex < 0 || toIndex < 0) {
    dragTypeId.value = "";
    dragOverTypeId.value = "";
    return;
  }
  const next = [...widgetTypes.value];
  const [moved] = next.splice(fromIndex, 1);
  next.splice(toIndex, 0, moved);
  widgetTypes.value = next;
  dragTypeId.value = "";
  dragOverTypeId.value = "";
  persistViewConfig();
};

const onTypeDragEnd = () => {
  dragTypeId.value = "";
  dragOverTypeId.value = "";
};

const visiblePages = computed(() => {
  const pages = [];
  const maxVisible = 5;
  if (totalPages.value <= maxVisible) {
    for (let i = 1; i <= totalPages.value; i++) pages.push(i);
  } else if (currentPage.value <= 3) {
    for (let i = 1; i <= 4; i++) pages.push(i);
    pages.push("...");
    pages.push(totalPages.value);
  } else if (currentPage.value >= totalPages.value - 2) {
    pages.push(1);
    pages.push("...");
    for (let i = totalPages.value - 3; i <= totalPages.value; i++)
      pages.push(i);
  } else {
    pages.push(1);
    pages.push("...");
    pages.push(currentPage.value - 1);
    pages.push(currentPage.value);
    pages.push(currentPage.value + 1);
    pages.push("...");
    pages.push(totalPages.value);
  }
  return pages;
});

const goBack = () => {
  const id = String(reportId.value || "");
  if (id) {
    router.push(`/custom-report/${id}`);
    return;
  }
  router.push("/custom-report");
};

const loadMeta = async () => {
  try {
    const response = await customReportApi.getReport(reportId.value);
    if (response.success) {
      reportName.value = response.data.report?.name || "";
      widgetMeta.value =
        (response.data.widgets || []).find((w) => w.id === widgetId.value) ||
        null;
      if (!widgetMeta.value) {
        alert(t("customReport_err_widgetNotFound", "Widget not found."));
        goBack();
        return;
      }
      skipPersist.value = true;
      applySourceFields();
      applyViewConfig(widgetMeta.value.viewConfig);
      restoreVisibleColumns();
      await nextTick();
      skipPersist.value = false;
    }
  } catch (err) {
    alert(
      err.response?.data?.message ||
        err.message ||
        t("customReport_err_loadDetail", "Failed to load report detail."),
    );
    goBack();
  }
};

const buildFilterPayload = () =>
  filters.value
    .filter((rule) => hasFilterValue(rule))
    .map((rule) => {
      const payload = {
        field: rule.field,
        op: rule.op,
        value: rule.value,
      };
      if (isMultiValueOp(rule.op) && !Array.isArray(payload.value)) {
        payload.value = parseMultiFilterValue(payload.value);
      }
      return payload;
    });

const normalizeFilterRuleValue = (op, value) => {
  if (isMultiValueOp(op)) {
    return Array.isArray(value) ? value : parseMultiFilterValue(value);
  }
  return Array.isArray(value) ? value.join(", ") : (value ?? "");
};

const onFilterRuleValueInput = (rule, raw) => {
  if (!rule) return;
  rule.value = isMultiValueOp(rule.op) ? parseMultiFilterValue(raw) : raw;
  handleFilterValueInput();
};

const loadTransactions = async () => {
  restoreVisibleColumns();
  const normalizedPerPage = normalizeTablePerPage(perPage.value);
  if (perPage.value !== normalizedPerPage) {
    perPage.value = normalizedPerPage;
  }
  const showFullLoading = loading.value || transactions.value.length === 0;
  if (showFullLoading) loading.value = true;
  try {
    const params = {
      page: currentPage.value,
      per_page: normalizedPerPage,
    };
    if (sortActive.value && sorts.value.length) {
      params.sorts = JSON.stringify(
        sorts.value.map((rule) => ({
          field: rule.field,
          direction: rule.direction,
        })),
      );
      params.sort_field = sorts.value[0].field;
      params.sort_direction = sorts.value[0].direction;
    } else if (defaultSortField.value) {
      params.sort_field = defaultSortField.value;
      params.sort_direction = DEFAULT_SORT_DIRECTION;
    }
    if (searchQuery.value) params.search = searchQuery.value;
    const activeFilters = buildFilterPayload();
    if (activeFilters.length) params.filters = JSON.stringify(activeFilters);

    const response = await customReportApi.getWidgetRows(
      reportId.value,
      widgetId.value,
      params,
    );
    if (response.success) {
      transactions.value = response.data.items || [];
      selectedTransactionIds.value = retainVisibleSelectedIds(
        transactions.value,
      );
      if (!selectedTransactionIds.value.length) {
        showExportDropdown.value = false;
      }
      expandedRowKey.value = null;
      detailState.value = {};
      if (response.data.pagination) {
        const pagination = response.data.pagination;
        total.value = pagination.total || 0;
        perPage.value = normalizeTablePerPage(pagination.per_page);
        currentPage.value = pagination.page || 1;
        totalPages.value = pagination.total_pages || 0;
      }
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

watch(
  perPage,
  (value) => {
    const normalized = normalizeTablePerPage(value);
    if (value === normalized) return;

    perPage.value = normalized;
    if (!widgetMeta.value || activeKind.value !== "table") return;

    currentPage.value = 1;
    persistTypeQuery();
    loadTransactions();
  },
  { immediate: true },
);

const handleSearch = () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    currentPage.value = 1;
    chartPage.value = 1;
    persistTypeQuery();
    loadTransactions();
    if (activeKind.value === "chart") {
      loadChart();
    }
  }, 300);
};

const applyFilters = () => {
  currentPage.value = 1;
  chartPage.value = 1;
  persistTypeQuery();
  loadTransactions();
  if (activeKind.value === "chart") {
    loadChart();
  }
};

let chartLoadTimer = null;
let chartLoadSeq = 0;

const emptyChartResult = () => ({
  labels: [],
  series: [],
  max: 1,
  ready: false,
  truncated: false,
  totalLabels: 0,
});

const scheduleLoadChart = () => {
  clearTimeout(chartLoadTimer);
  chartLoadTimer = setTimeout(() => {
    loadChart();
  }, 250);
};

const loadChart = async () => {
  if (!isChartReady.value || !reportId.value || !widgetId.value) {
    chartResult.value = emptyChartResult();
    return;
  }
  const seq = ++chartLoadSeq;
  try {
    const params = {
      chartType: chartType.value,
      xField: chartXField.value,
      yField: chartYField.value,
      sortBy: chartSortBy.value,
      ySortBy: chartYSortBy.value,
      omitZero: chartOmitZero.value ? "1" : "0",
      groupBy: chartGroupBy.value || "none",
      cumulative: chartCumulative.value ? "1" : "0",
      range:
        chartRange.value === "custom" && hasCustomRangeValues()
          ? "custom"
          : "auto",
      page: normalizePage(chartPage.value),
      per_page: normalizeChartPerPage(chartPerPage.value),
    };
    const rangeMin = parseOptionalNumber(chartRangeMin.value);
    const rangeMax = parseOptionalNumber(chartRangeMax.value);
    if (rangeMin !== null) params.rangeMin = rangeMin;
    if (rangeMax !== null) params.rangeMax = rangeMax;
    const activeFilters = buildFilterPayload();
    if (activeFilters.length) params.filters = JSON.stringify(activeFilters);
    if (searchQuery.value) params.search = searchQuery.value;
    const response = await customReportApi.getWidgetChart(
      reportId.value,
      widgetId.value,
      params,
    );
    if (seq !== chartLoadSeq) return;
    if (response.success) {
      const labels = response.data.labels || [];
      chartLegendExpanded.value = false;
      chartPage.value = normalizePage(response.data.page ?? chartPage.value);
      chartPerPage.value = normalizeChartPerPage(
        response.data.per_page ?? chartPerPage.value,
      );
      chartTotalPages.value = Math.max(
        1,
        Number(response.data.total_pages) || 1,
      );
      chartResult.value = {
        labels,
        series: response.data.series || [],
        max: response.data.max || 1,
        ready: !!response.data.ready,
        truncated: !!response.data.truncated,
        totalLabels: Number(response.data.totalLabels) || labels.length,
      };
    }
  } catch (err) {
    if (seq !== chartLoadSeq) return;
    chartResult.value = emptyChartResult();
    console.error(err);
  }
};

const handleFilterValueInput = () => {
  clearTimeout(filterTimer);
  filterTimer = setTimeout(() => {
    applyFilters();
  }, 300);
};

const toggleFilterPanel = () => {
  if (showFilterPanel.value) {
    closeFilterPanel();
    return;
  }
  if (activeKind.value === "chart") {
    openChartSettings();
    return;
  }
  showFilterPanel.value = true;
  filterPanelView.value = "menu";
  filterPropertyQuery.value = "";
  showSortPopover.value = false;
  showBarFilterPanel.value = false;
};

const closeFilterPanel = () => {
  showFilterPanel.value = false;
  filterPanelView.value = "menu";
  filterPanelReturnView.value = "menu";
  filterPropertyQuery.value = "";
  columnSearchQuery.value = "";
  resetChartPicker();
  closeHeaderFilter();
};

const openAddFilter = () => {
  filterPanelView.value = "add";
  filterPropertyQuery.value = "";
};

const chooseAddFilter = () => {
  filterPanelReturnView.value = "menu";
  openAddFilter();
};

const openChartFilter = () => {
  filterPanelReturnView.value = "chart";
  resetChartPicker();
  if (filters.value.length) {
    filterPanelView.value = "list";
    return;
  }
  openAddFilter();
};

const backToFilterReturn = () => {
  filterPanelView.value = filterPanelReturnView.value || "menu";
  filterPropertyQuery.value = "";
};

const openColumnPanel = () => {
  columnSearchQuery.value = "";
  filterPanelView.value = "columns";
};

const openEditColumnsToolbar = () => {
  showBarFilterPanel.value = false;
  showSortPopover.value = false;
  closeHeaderFilter();
  if (showFilterPanel.value && filterPanelView.value === "columns") {
    closeFilterPanel();
    return;
  }
  showFilterPanel.value = true;
  filterPanelView.value = "columns";
  filterPanelReturnView.value = "menu";
  columnSearchQuery.value = "";
};

const allColumnsVisible = computed(
  () =>
    filterColumns.value.length > 0 &&
    filterColumns.value.every((col) => visibleColumns.value[col.field]),
);

const someColumnsVisible = computed(() =>
  filterColumns.value.some((col) => visibleColumns.value[col.field]),
);

const toggleAllColumns = (checked) => {
  const next = { ...visibleColumns.value };
  filterColumns.value.forEach((col, index) => {
    next[col.field] = checked || index === 0;
  });
  visibleColumns.value = next;
  persistTypeQuery();
  nextTick(() => fillColumnWidths());
};

const toggleColumnVisibility = (field, checked) => {
  if (!checked && isLastVisibleColumn(field)) return;
  visibleColumns.value = {
    ...visibleColumns.value,
    [field]: checked,
  };
  persistTypeQuery();
  nextTick(() => fillColumnWidths());
};

const chooseAddSort = () => {
  showSortPopover.value = false;
  filterPanelView.value = "sort";
};

const openDeleteActiveTypeConfirm = async () => {
  if (!activeWidgetType.value) return;
  closeFilterPanel();
  showBarFilterPanel.value = false;
  showSortPopover.value = false;
  await nextTick();
  askRemoveWidgetType(activeWidgetType.value);
};

const closeDeleteModal = () => {
  if (deleting.value) return;
  showDeleteModal.value = false;
};

const closeDuplicateModal = () => {
  if (duplicating.value) return;
  showDuplicateModal.value = false;
};

const deleteModalMessage = computed(() => {
  const name =
    widgetMeta.value?.name ||
    widgetMeta.value?.dataSourceName ||
    widgetMeta.value?.dataSourceObject ||
    pageTitle.value ||
    t("menu_customReport", "Custom Report");
  return tParams(
    "customReport_confirmDeleteWidget",
    'Are you sure you want to delete widget "{name}"? You will return to the report page.',
    { name },
  );
});

const duplicateModalMessage = computed(() => {
  const name =
    widgetMeta.value?.name ||
    widgetMeta.value?.dataSourceName ||
    widgetMeta.value?.dataSourceObject ||
    pageTitle.value ||
    t("menu_customReport", "Custom Report");
  return tParams(
    "customReport_confirmDuplicateWidget",
    'Duplicate widget "{name}" as a new widget with the same data source?',
    { name },
  );
});

const confirmDeleteWidget = async () => {
  if (deleting.value || !reportId.value || !widgetId.value) return;
  deleting.value = true;
  try {
    await customReportApi.deleteWidget(reportId.value, widgetId.value);
    showDeleteModal.value = false;
    goBack();
  } catch (err) {
    alert(
      err.response?.data?.message ||
        err.message ||
        t("customReport_err_deleteWidget", "Failed to delete widget."),
    );
  } finally {
    deleting.value = false;
  }
};

const confirmDuplicateWidget = async () => {
  if (duplicating.value || !reportId.value || !widgetId.value) return;
  duplicating.value = true;
  try {
    const response = await customReportApi.duplicateWidget(
      reportId.value,
      widgetId.value,
    );
    const payload = response?.data ?? response ?? {};
    const newWidgetId = payload.id;
    const newName = payload.name || "";
    const targetReportId = payload.reportId || reportId.value;
    showDuplicateModal.value = false;
    if (!newWidgetId || !targetReportId) {
      alert(
        t("customReport_err_duplicateWidget", "Failed to duplicate widget."),
      );
      return;
    }
    alert(
      tParams(
        "customReport_alert_duplicateOk",
        'Widget duplicated successfully as "{name}".',
        { name: newName || "Copy" },
      ),
    );
    await router.push({
      name: "custom-report-widget",
      params: {
        reportId: String(targetReportId),
        widgetId: String(newWidgetId),
      },
    });
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

const startFirstSort = () => {
  sorts.value = defaultSortField.value
    ? [{ id: sortIdSeq++, field: defaultSortField.value, direction: "asc" }]
    : [];
  sortActive.value = true;
  currentPage.value = 1;
  persistTypeQuery();
  loadTransactions();
};

const clearSortFromModal = () => {
  clearSort();
  filterPanelView.value = "sort";
};

const backFromAddFilter = () => {
  filterPanelView.value = filters.value.length
    ? "list"
    : filterPanelReturnView.value || "menu";
  filterPropertyQuery.value = "";
};

const addFilter = (field) => {
  filters.value = [
    ...filters.value,
    {
      id: filterIdSeq++,
      field,
      op: defaultOpForField(field),
      value: "",
    },
  ];
  filterPanelView.value = "list";
  filterPropertyQuery.value = "";
};

const removeFilter = (index) => {
  filters.value = filters.value.filter((_, i) => i !== index);
  applyFilters();
  if (!filters.value.length) {
    filterPanelView.value = "add";
    barFilterView.value = "add";
  }
};

const resetFilters = () => {
  filters.value = [];
  filterPanelView.value = "add";
  barFilterView.value = "add";
  applyFilters();
};

const closeBarFilterPanel = () => {
  showBarFilterPanel.value = false;
  barFilterView.value = "list";
  editingBarFilterField.value = "";
  filterPropertyQuery.value = "";
};

const openFilterFromBar = () => {
  showSortPopover.value = false;
  showFilterPanel.value = false;
  showBarFilterPanel.value = true;
  editingBarFilterField.value = "";
  filterPropertyQuery.value = "";
};

const openFilterPanelForRule = (rule) => {
  showSortPopover.value = false;
  showFilterPanel.value = false;
  showBarFilterPanel.value = true;
  editingBarFilterField.value = rule?.field || "";
  filterPropertyQuery.value = "";
};

const selectBarFilterColumn = (field) => {
  const existing = filters.value.find((rule) => rule.field === field);
  if (!existing) {
    filters.value = [
      ...filters.value,
      {
        id: filterIdSeq++,
        field,
        op: defaultOpForField(field),
        value: "",
      },
    ];
  }
  editingBarFilterField.value = field;
};

const removeBarFilterField = (field) => {
  const index = filters.value.findIndex((rule) => rule.field === field);
  if (index < 0) return;
  removeFilter(index);
  editingBarFilterField.value = "";
  if (!filters.value.length) {
    closeBarFilterPanel();
  }
};

const toggleSortPopover = () => {
  showBarFilterPanel.value = false;
  editingBarFilterField.value = "";
  showSortPopover.value = !showSortPopover.value;
};

const onSortChipChange = () => {
  sortActive.value = true;
  currentPage.value = 1;
  persistTypeQuery();
  loadTransactions();
};

const addSortRule = () => {
  if (sorts.value.length >= filterColumns.value.length) return;
  sorts.value = [
    ...sorts.value,
    {
      id: sortIdSeq++,
      field: nextAvailableSortField(),
      direction: "asc",
    },
  ];
  sortActive.value = true;
  currentPage.value = 1;
  persistTypeQuery();
  loadTransactions();
};

const removeSortRule = (index) => {
  if (sorts.value.length <= 1) {
    clearSort();
    return;
  }
  sorts.value = sorts.value.filter((_, i) => i !== index);
  sortActive.value = true;
  currentPage.value = 1;
  persistTypeQuery();
  loadTransactions();
};

const clearSort = () => {
  sortActive.value = false;
  showSortPopover.value = false;
  sorts.value = defaultSortField.value
    ? [
        {
          id: sortIdSeq++,
          field: defaultSortField.value,
          direction: DEFAULT_SORT_DIRECTION,
        },
      ]
    : [];
  currentPage.value = 1;
  persistTypeQuery();
  loadTransactions();
};

const resetAll = () => {
  filters.value = [];
  filterPanelView.value = "add";
  showFilterPanel.value = false;
  showBarFilterPanel.value = false;
  barFilterView.value = "list";
  editingBarFilterField.value = "";
  showSortPopover.value = false;
  sortActive.value = false;
  sorts.value = defaultSortField.value
    ? [
        {
          id: sortIdSeq++,
          field: defaultSortField.value,
          direction: DEFAULT_SORT_DIRECTION,
        },
      ]
    : [];
  applySourceFields();
  userResizedColumns.value = false;
  currentPage.value = 1;
  chartPage.value = 1;
  persistTypeQuery();
  loadTransactions();
  if (activeKind.value === "chart") {
    loadChart();
  }
  nextTick(() => fillColumnWidths());
};

const onFilterFieldChange = (rule) => {
  const allowed = opsForField(rule.field).map((op) => op.value);
  const nextOp = allowed.includes(rule.op)
    ? rule.op
    : defaultOpForField(rule.field);
  filters.value = filters.value.map((item) =>
    item.id === rule.id
      ? {
          ...item,
          field: rule.field,
          op: nextOp,
          value: isMultiValueOp(nextOp) ? [] : "",
        }
      : item,
  );
  applyFilters();
};

const onFilterOpChange = (rule) => {
  if (!rule) return;
  rule.value = normalizeFilterRuleValue(rule.op, rule.value);
  applyFilters();
};

const headerFilterAllSelected = computed(
  () =>
    headerFilterValues.value.length > 0 &&
    headerFilterValues.value.every((value) =>
      headerFilterSelected.value.includes(value),
    ),
);

const headerFilterSomeSelected = computed(() =>
  headerFilterValues.value.some((value) =>
    headerFilterSelected.value.includes(value),
  ),
);

const closeHeaderFilter = () => {
  headerFilterField.value = "";
  headerFilterSearch.value = "";
  headerFilterValues.value = [];
  headerFilterSelected.value = [];
  headerFilterLoading.value = false;
  clearTimeout(headerFilterSearchTimer);
};

const positionHeaderFilterPanel = (anchorEl) => {
  if (!anchorEl) return;
  const rect = anchorEl.getBoundingClientRect();
  const width = 280;
  const left = Math.min(Math.max(8, rect.left), window.innerWidth - width - 8);
  headerFilterPanelStyle.value = {
    top: `${Math.round(rect.bottom + 6)}px`,
    left: `${Math.round(left)}px`,
    width: `${width}px`,
  };
};

const loadHeaderFilterValues = async () => {
  const field = headerFilterField.value;
  if (!field || !reportId.value || !widgetId.value) return;
  headerFilterLoading.value = true;
  try {
    const params = {
      field,
      limit: 200,
    };
    if (headerFilterSearch.value.trim()) {
      params.search = headerFilterSearch.value.trim();
    }
    const otherFilters = buildFilterPayload().filter(
      (rule) => rule.field !== field,
    );
    if (otherFilters.length) {
      params.filters = JSON.stringify(otherFilters);
    }
    const response = await customReportApi.getWidgetColumnValues(
      reportId.value,
      widgetId.value,
      params,
    );
    if (headerFilterField.value !== field) return;
    const values = response?.data?.values || [];
    headerFilterValues.value = Array.isArray(values)
      ? values.map((v) => String(v))
      : [];
  } catch (err) {
    if (headerFilterField.value === field) {
      headerFilterValues.value = [];
    }
    console.error(err);
  } finally {
    if (headerFilterField.value === field) {
      headerFilterLoading.value = false;
    }
  }
};

const onHeaderFilterSearchInput = () => {
  clearTimeout(headerFilterSearchTimer);
  headerFilterSearchTimer = setTimeout(() => {
    loadHeaderFilterValues();
  }, 250);
};

const toggleHeaderFilter = async (event, field) => {
  if (headerFilterField.value === field) {
    closeHeaderFilter();
    return;
  }
  showFilterPanel.value = false;
  showBarFilterPanel.value = false;
  showSortPopover.value = false;
  headerFilterField.value = field;
  headerFilterSearch.value = "";
  const existing = filters.value.find(
    (rule) => rule.field === field && hasFilterValue(rule),
  );
  if (
    existing &&
    isMultiValueOp(existing.op) &&
    Array.isArray(existing.value)
  ) {
    headerFilterSelected.value = [...existing.value.map((v) => String(v))];
  } else if (
    existing &&
    existing.op === "equals" &&
    String(existing.value ?? "").trim() !== ""
  ) {
    headerFilterSelected.value = [String(existing.value)];
  } else {
    headerFilterSelected.value = [];
  }
  positionHeaderFilterPanel(event.currentTarget);
  await loadHeaderFilterValues();
  if (!headerFilterSelected.value.length && headerFilterValues.value.length) {
    headerFilterSelected.value = [...headerFilterValues.value];
  }
};

const toggleHeaderFilterSelectAll = (checked) => {
  headerFilterSelected.value = checked ? [...headerFilterValues.value] : [];
};

const toggleHeaderFilterValue = (value, checked) => {
  if (checked) {
    if (!headerFilterSelected.value.includes(value)) {
      headerFilterSelected.value = [...headerFilterSelected.value, value];
    }
    return;
  }
  headerFilterSelected.value = headerFilterSelected.value.filter(
    (item) => item !== value,
  );
};

const upsertFieldFilter = (field, op, value) => {
  const index = filters.value.findIndex((rule) => rule.field === field);
  const nextRule = {
    id: index >= 0 ? filters.value[index].id : filterIdSeq++,
    field,
    op,
    value,
  };
  if (index >= 0) {
    filters.value = filters.value.map((rule, i) =>
      i === index ? nextRule : rule,
    );
  } else {
    filters.value = [...filters.value, nextRule];
  }
};

const applyHeaderFilter = () => {
  const field = headerFilterField.value;
  if (!field) return;
  const selected = [...headerFilterSelected.value];
  const allVisible = headerFilterValues.value;
  if (!selected.length) {
    filters.value = filters.value.filter((rule) => rule.field !== field);
  } else if (
    allVisible.length &&
    selected.length === allVisible.length &&
    allVisible.every((value) => selected.includes(value)) &&
    !headerFilterSearch.value.trim()
  ) {
    filters.value = filters.value.filter((rule) => rule.field !== field);
  } else {
    upsertFieldFilter(field, "in", selected);
  }
  closeHeaderFilter();
  applyFilters();
};

const clearHeaderFilter = () => {
  const field = headerFilterField.value;
  if (!field) return;
  filters.value = filters.value.filter((rule) => rule.field !== field);
  closeHeaderFilter();
  applyFilters();
};

const applyHeaderSort = (direction) => {
  const field = headerFilterField.value;
  if (!field) return;
  upsertSortRule(field, direction);
  if (activeKind.value === "chart") {
    loadChart();
  }
};

const onDocumentClick = (event) => {
  if (headerFilterField.value) {
    const panel = headerFilterPanelRef.value;
    const target = event.target;
    const onFilterBtn = target?.closest?.(".th-filter-btn");
    if (panel && !panel.contains(target) && !onFilterBtn) {
      closeHeaderFilter();
    }
  }
  if (showFilterPanel.value) {
    const el = filterControlRef.value;
    const editBtn = targetClosestEditColumns(event.target);
    if (el && !el.contains(event.target) && !editBtn) {
      closeFilterPanel();
    }
  }
  if (showBarFilterPanel.value) {
    const el = filterBarRef.value;
    if (el && !el.contains(event.target)) {
      closeBarFilterPanel();
    }
  }
  if (showSortPopover.value) {
    const el = sortChipRef.value;
    if (el && !el.contains(event.target)) {
      showSortPopover.value = false;
    }
  }
  if (showExportDropdown.value) {
    showExportDropdown.value = false;
  }
  if (showChartDetailFilter.value) {
    const el = chartDetailFilterRef.value;
    if (el && !el.contains(event.target)) {
      closeChartDetailFilter();
    }
  }
  if (showChartDetailSortPopover.value) {
    const el = chartDetailSortChipRef.value;
    if (el && !el.contains(event.target)) {
      showChartDetailSortPopover.value = false;
    }
  }
  if (showChartDetailBarFilter.value) {
    const el = chartDetailFilterBarRef.value;
    if (el && !el.contains(event.target)) {
      closeChartDetailBarFilter();
    }
  }
};

const targetClosestEditColumns = (target) =>
  target?.closest?.(".edit-columns-trigger");

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

const retainVisibleSelectedIds = (rows) => {
  const selected = selectedTransactionIds.value;
  if (!selected.length) return [];
  const visibleKeys = new Set(rows.map((row, index) => rowKey(row, index)));
  return selected.filter((id) => visibleKeys.has(id));
};

const displayCell = (value) => {
  if (value === null || value === undefined || value === "") return "-";
  return String(value);
};

const hasCellValue = (value) =>
  value !== null && value !== undefined && value !== "";

const isStatusField = (field) => String(field || "").toLowerCase() === "status";

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

const cellTitle = (value, field) =>
  isStatusField(field) ? getStatusLabel(value) : displayCell(value);

const formatCellNumber = (value) => {
  if (value === null || value === undefined || value === "") return "-";
  const num = Number(value);
  if (!Number.isFinite(num)) return displayCell(value);
  return num.toLocaleString();
};

const toggleSelectAll = () => {
  if (isAllSelected.value) {
    selectedTransactionIds.value = [];
    return;
  }
  selectedTransactionIds.value = transactions.value.map((row, index) =>
    rowKey(row, index),
  );
};

const getExportCellValue = (row, field) => {
  const col = filterColumns.value.find((item) => item.field === field);
  const value = row[field];
  if (col?.type === "date") {
    return `${formatDate(value)} ${formatTime(value)}`.trim();
  }
  return value ?? "";
};

const buildWidgetExportParams = (extra = {}) => {
  const params = {
    columns: tableColumns.value.map((col) => ({
      field: col.field,
      label: col.label,
    })),
    widgetName:
      widgetMeta.value?.name || dataSourceTitle.value || "custom_report",
    ...extra,
  };
  if (searchQuery.value) params.search = searchQuery.value;
  const activeFilters = buildFilterPayload();
  if (activeFilters.length) params.filters = activeFilters;
  if (sortActive.value && sorts.value.length) {
    params.sorts = sorts.value.map((rule) => ({
      field: rule.field,
      direction: rule.direction === "asc" ? "asc" : "desc",
    }));
  }
  return params;
};

const selectedExportRows = () => {
  const selected = new Set(selectedTransactionIds.value);
  const cols = tableColumns.value;
  return transactions.value
    .filter((row, index) => selected.has(rowKey(row, index)))
    .map((row) => {
      const out = {};
      cols.forEach((col) => {
        out[col.field] = getExportCellValue(row, col.field);
      });
      return out;
    });
};

const handleExport = async (format) => {
  showExportDropdown.value = false;
  if (!hasExportPermission.value || isExportAllRunning.value) return;
  if (!selectedTransactionIds.value.length) {
    alert(
      t("fundingReport_alert_exportFail", "Failed to export: no rows selected"),
    );
    return;
  }
  if (format !== "csv" && format !== "excel") return;
  const rows = selectedExportRows();
  if (!rows.length) {
    alert(
      t("fundingReport_alert_exportFail", "Failed to export: no rows selected"),
    );
    return;
  }
  await startOrResumeExport(() =>
    buildWidgetExportParams({ mode: "selected", rows }),
  );
};

const toggleExportSelectedDropdown = () => {
  if (isExportAllRunning.value) return;
  showExportDropdown.value = !showExportDropdown.value;
};

const filteredExportPickerColumns = computed(() => {
  const q = String(exportColumnModal.value.search || "")
    .trim()
    .toLowerCase();
  if (!q) return filterColumns.value;
  return filterColumns.value.filter(
    (col) =>
      col.label.toLowerCase().includes(q) ||
      col.field.toLowerCase().includes(q),
  );
});

const allExportColumnsChecked = computed(
  () =>
    filterColumns.value.length > 0 &&
    filterColumns.value.every(
      (col) => exportColumnModal.value.selected[col.field],
    ),
);

const someExportColumnsChecked = computed(() =>
  filterColumns.value.some(
    (col) => exportColumnModal.value.selected[col.field],
  ),
);

const canConfirmExportColumns = computed(() => {
  if (exportColumnModal.value.mode !== "specific")
    return filterColumns.value.length > 0;
  return someExportColumnsChecked.value;
});

const closeExportColumnModal = () => {
  exportColumnModal.value = emptyExportColumnModal();
};

const setExportColumnMode = (mode) => {
  exportColumnModal.value = {
    ...exportColumnModal.value,
    mode,
  };
};

const toggleExportColumn = (field, checked) => {
  exportColumnModal.value = {
    ...exportColumnModal.value,
    selected: {
      ...exportColumnModal.value.selected,
      [field]: checked,
    },
  };
};

const toggleAllExportColumns = (checked) => {
  const selected = { ...exportColumnModal.value.selected };
  filteredExportPickerColumns.value.forEach((col) => {
    selected[col.field] = checked;
  });
  exportColumnModal.value = {
    ...exportColumnModal.value,
    selected,
  };
};

const resolveExportAllColumns = () => {
  const source =
    exportColumnModal.value.mode === "specific"
      ? filterColumns.value.filter(
          (col) => exportColumnModal.value.selected[col.field],
        )
      : filterColumns.value;
  return source.map((col) => ({ field: col.field, label: col.label }));
};

const confirmExportColumns = async () => {
  if (!canConfirmExportColumns.value || isExportAllRunning.value) return;
  const columns = resolveExportAllColumns();
  if (!columns.length) return;
  closeExportColumnModal();
  await startOrResumeExport(() =>
    buildWidgetExportParams({ mode: "all", columns }),
  );
};

const exportAllRows = () => {
  if (!hasExportPermission.value || activeKind.value !== "table") return;
  if (isExportAllRunning.value) return;
  showExportDropdown.value = false;
  const selected = {};
  filterColumns.value.forEach((col) => {
    selected[col.field] = !!visibleColumns.value[col.field];
  });
  exportColumnModal.value = {
    visible: true,
    mode: "all",
    search: "",
    selected,
  };
};

const changePage = (page) => {
  if (page === "...") return;
  currentPage.value = page;
  persistTypeQuery();
  loadTransactions();
};

const changeTablePerPage = () => {
  perPage.value = normalizeTablePerPage(perPage.value);
  currentPage.value = 1;
  persistTypeQuery();
  loadTransactions();
};

const changeChartPage = (page) => {
  if (page === "...") return;
  chartPage.value = normalizePage(page);
  persistTypeQuery();
  loadChart();
};

const changeChartPerPage = () => {
  chartPerPage.value = normalizeChartPerPage(chartPerPage.value);
  chartPage.value = 1;
  persistTypeQuery();
  loadChart();
};

const upsertSortRule = (field, direction) => {
  const existingIndex = sorts.value.findIndex((rule) => rule.field === field);
  if (sortActive.value && existingIndex >= 0) {
    sorts.value = sorts.value.map((rule, index) =>
      index === existingIndex ? { ...rule, direction } : rule,
    );
  } else if (!sortActive.value) {
    sorts.value = [{ id: sortIdSeq++, field, direction }];
  } else {
    sorts.value = [...sorts.value, { id: sortIdSeq++, field, direction }];
  }
  sortActive.value = true;
  showSortPopover.value = false;
  currentPage.value = 1;
  persistTypeQuery();
  loadTransactions();
};

const sortBy = (field) => {
  const existing = sortActive.value
    ? sorts.value.find((rule) => rule.field === field)
    : null;
  const nextDirection =
    existing && existing.direction === "asc" ? "desc" : "asc";
  upsertSortRule(field, nextDirection);
};

const formatDate = (dateString) => {
  if (!dateString) return "-";
  return new Date(dateString).toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  });
};

const formatTime = (dateString) => {
  if (!dateString) return "";
  return new Date(dateString).toLocaleTimeString("en-US", {
    hour: "2-digit",
    minute: "2-digit",
  });
};

const getInitials = (firstName, lastName) => {
  if (!firstName && !lastName) return "??";
  return `${firstName?.[0] || ""}${lastName?.[0] || ""}`.toUpperCase();
};

const getStatusLabel = (status) => {
  if (!hasCellValue(status)) return "-";
  const key = normalizeStatusKey(status);
  const keys = {
    active: "status_active",
    inactive: "status_inactive",
    suspended: "status_suspended",
    pending_verification: "status_pending_verification",
    closed: "status_closed",
    pending: "fundingReport_status_pending",
    processing: "fundingReport_status_processing",
    completed: "fundingReport_status_completed",
    rejected: "fundingReport_status_rejected",
    failed: "fundingReport_status_failed",
    cancelled: "fundingReport_status_cancelled",
  };
  return keys[key] ? t(keys[key], displayCell(status)) : displayCell(status);
};

const getMethodIcon = (method) => {
  const lowerMethod = method?.toLowerCase() || "";
  if (lowerMethod.includes("bitcoin")) return "fab fa-bitcoin";
  if (lowerMethod.includes("ethereum")) return "fab fa-ethereum";
  if (lowerMethod.includes("usdt") || lowerMethod.includes("usdc"))
    return "fas fa-coins";
  if (lowerMethod.includes("bank")) return "fas fa-university";
  if (lowerMethod.includes("alchemy")) return "fas fa-credit-card";
  if (lowerMethod.includes("internal") || lowerMethod.includes("transfer"))
    return "fas fa-exchange-alt";
  return "fas fa-wallet";
};

const getTransactionTypeIcon = (type) => {
  if (type === "deposit") return "fas fa-arrow-down";
  if (type === "withdrawal") return "fas fa-arrow-up";
  if (type === "internal_transfer") return "fas fa-exchange-alt";
  return "fas fa-exchange-alt";
};

const getTransactionTypeLabel = (type) => {
  const x = String(type ?? "").toLowerCase();
  if (x === "deposit") return t("fundingReport_type_deposit");
  if (x === "withdrawal") return t("fundingReport_type_withdrawal");
  if (x === "internal_transfer")
    return t("fundingReport_type_internalTransfer");
  return type;
};

const getAmountClass = (type) => {
  if (type === "deposit") return "positive";
  if (type === "withdrawal") return "negative";
  if (type === "internal_transfer") return "neutral";
  return "";
};

const getAmountPrefix = (type) => {
  if (type === "deposit") return "+";
  if (type === "withdrawal") return "-";
  return "";
};

const reloadWidgetPage = async () => {
  if (!hasReadonlyPermission.value) {
    loading.value = false;
    return;
  }
  selectedTransactionIds.value = [];
  showExportDropdown.value = false;
  filters.value = [];
  sortActive.value = false;
  sorts.value = [];
  currentPage.value = 1;
  searchQuery.value = "";
  widgetMeta.value = null;
  transactions.value = [];
  await loadMeta();
  if (widgetMeta.value) {
    if (widgetTypes.value.length) {
      await loadTransactions();
      if (activeKind.value === "chart") {
        await loadChart();
      }
    } else {
      loading.value = false;
    }
  } else {
    loading.value = false;
  }
  await nextTick();
  userResizedColumns.value = false;
  fillColumnWidths();
};

watch(
  () => `${route.params.reportId || ""}:${route.params.widgetId || ""}`,
  async (nextKey, prevKey) => {
    if (!nextKey || nextKey === prevKey || !route.params.widgetId) return;
    await reloadWidgetPage();
  },
);

watch(
  () => [
    chartType.value,
    chartXField.value,
    chartYField.value,
    chartSortBy.value,
    chartYSortBy.value,
    chartOmitZero.value,
    chartGroupBy.value,
    chartCumulative.value,
  ],
  () => {
    if (!widgetMeta.value) return;
    if (!isHydratingTypeView.value) {
      chartPage.value = 1;
    }
    schedulePersistViewConfig();
    if (activeKind.value === "chart") {
      scheduleLoadChart();
    }
  },
);

watch(
  () => [
    chartRange.value,
    chartRangeMin.value,
    chartRangeMax.value,
    chartColorScheme.value,
  ],
  () => {
    if (!widgetMeta.value) return;
    schedulePersistViewConfig();
  },
);

watch(isExportAllRunning, (running) => {
  if (running) showExportDropdown.value = false;
});

onMounted(async () => {
  document.addEventListener("click", onDocumentClick);
  if (!hasReadonlyPermission.value) {
    loading.value = false;
    return;
  }
  resumeActiveExportIfAny();
  await loadMeta();
  if (widgetMeta.value) {
    if (widgetTypes.value.length) {
      await loadTransactions();
      if (activeKind.value === "chart") {
        await loadChart();
      }
    } else {
      loading.value = false;
    }
  } else {
    loading.value = false;
  }
  await nextTick();
  fillColumnWidths();
  if (typeof ResizeObserver !== "undefined" && tableScrollRef.value) {
    tableResizeObserver = new ResizeObserver(() => {
      containerWidth.value = tableScrollRef.value?.clientWidth || 0;
      fillColumnWidths();
    });
    tableResizeObserver.observe(tableScrollRef.value);
  }
});

onUnmounted(() => {
  document.removeEventListener("click", onDocumentClick);
  clearTimeout(searchTimer);
  clearTimeout(filterTimer);
  clearTimeout(chartLoadTimer);
  clearTimeout(viewConfigTimer);
  if (!skipPersist.value && widgetMeta.value) {
    persistViewConfig();
  }
  clearTimeout(chartDetailSearchTimer);
  clearTimeout(chartDetailFilterTimer);
  clearTimeout(hideTooltipTimer);
  hideChartTooltip();
  stopColumnResize();
  stopChartDetailColumnResize();
  if (tableResizeObserver) {
    tableResizeObserver.disconnect();
    tableResizeObserver = null;
  }
  if (chartDetailResizeObserver) {
    chartDetailResizeObserver.disconnect();
    chartDetailResizeObserver = null;
  }
  if (headerMeasureEl) {
    headerMeasureEl.remove();
    headerMeasureEl = null;
  }
});
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

.loading-container i {
  font-size: 48px;
  color: var(--color-brand);
  margin-bottom: 20px;
}

.error-container i {
  font-size: 48px;
  color: var(--color-danger);
  margin-bottom: 20px;
}

.widget-type-section {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: 25px 30px;
  margin-bottom: 20px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.transaction-table-container {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  overflow: visible;
}

.table-header {
  padding: 25px 30px;
  background: var(--color-surface-soft);
  border-bottom: 2px solid var(--color-border);
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 15px;
}

.table-header-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: nowrap;
  gap: 15px;
  width: 100%;
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

.widget-type-bar {
  display: flex;
  align-items: center;
  gap: 15px;
  width: 100%;
  box-sizing: border-box;
  flex-wrap: nowrap;
}

.widget-type-label {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
  white-space: nowrap;
}

.widget-type-presets {
  display: flex;
  gap: 10px;
  flex-wrap: nowrap;
  min-width: 0;
  overflow-x: auto;
}

.widget-type-btn {
  padding: 8px 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  color: var(--color-text);
  white-space: nowrap;
}

.widget-type-btn:hover {
  border-color: var(--color-border-strong);
  background: var(--color-surface-soft);
}

.widget-type-btn.active {
  background: var(--color-brand-solid);
  color: white;
  border-color: var(--color-brand);
}

.widget-type-rename {
  min-width: 0;
  box-sizing: border-box;
  text-align: center;
  outline: none;
  overflow: hidden;
}

.widget-type-add {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0;
  padding: 8px 12px;
  min-width: 38px;
  height: 38px;
  box-sizing: border-box;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-size: 14px;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  cursor: pointer;
  transition:
    gap 0.2s ease,
    background 0.2s ease,
    border-color 0.2s ease;
}

.widget-type-add-label {
  max-width: 0;
  opacity: 0;
  overflow: hidden;
  transition:
    max-width 0.2s ease,
    opacity 0.2s ease;
}

.widget-type-add:hover {
  gap: 8px;
  border-color: var(--color-border-strong);
  background: var(--color-surface-soft);
}

.widget-type-add:hover .widget-type-add-label {
  max-width: 160px;
  opacity: 1;
}

.widget-type-edit {
  margin-left: auto;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  height: 38px;
  box-sizing: border-box;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-size: 14px;
  font-weight: 600;
  white-space: nowrap;
  cursor: pointer;
}

.widget-type-edit:hover {
  border-color: var(--color-border-strong);
  background: var(--color-surface-soft);
}

.modal-card-types {
  max-width: 520px;
}

.modal-card-types .modal-card-head {
  margin-bottom: 12px;
}

.widget-type-edit-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin: 0 0 16px;
}

.widget-type-edit-row {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
}

.widget-type-edit-row.drag-over {
  border-color: var(--color-brand);
  background: var(--color-brand-soft);
}

.widget-type-edit-row.is-dragging {
  opacity: 0.55;
}

.widget-type-kind-badge {
  flex-shrink: 0;
  min-width: 52px;
  padding: 4px 8px;
  border-radius: 999px;
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-size: 14px;
  font-weight: 700;
  text-align: center;
  text-transform: uppercase;
}

.widget-type-name-input {
  flex: 1;
  min-width: 0;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  padding: 7px 10px;
  font-size: 14px;
  color: var(--color-ink);
}

.widget-type-name-input:focus {
  outline: none;
  border-color: var(--color-brand);
}

.widget-type-created-meta {
  flex-shrink: 0;
  max-width: 140px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 14px;
  color: var(--color-muted);
}

.widget-type-add-form {
  display: flex;
  align-items: center;
  gap: 8px;
}

.widget-type-add-form .filter-select {
  width: 110px;
  flex-shrink: 0;
}

.table-header-top .table-controls {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-shrink: 0;
  margin-left: auto;
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

.table-controls {
  display: flex;
  align-items: center;
  gap: 12px;
}

.active-controls-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 20px;
  border-bottom: 1px solid var(--color-border);
  background: var(--color-surface);
  font-size: 14px;
}

.active-controls-left {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  min-width: 0;
}

.active-controls-divider {
  width: 1px;
  height: 20px;
  background: var(--color-border-strong);
  margin: 0 2px;
}

.active-filter-wrap {
  position: relative;
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

.filter-panel-bar {
  left: 0;
  right: auto;
}

.filter-chip-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #ed8936;
  flex-shrink: 0;
}

.active-chip-wrap {
  position: relative;
}

.active-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border: none;
  border-radius: 999px;
  padding: 6px 10px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  line-height: 1.2;
}

.active-chip-sort {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.active-chip-filter {
  background: var(--color-surface-muted);
  color: var(--color-ink);
}

.active-chip:hover {
  filter: brightness(0.97);
}

.chip-icon {
  font-size: 14px;
  opacity: 0.85;
}

.chip-chevron {
  font-size: 14px;
  opacity: 0.7;
}

.active-add-filter {
  border: none;
  background: transparent;
  color: var(--color-muted);
  font-size: 14px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 8px;
  border-radius: var(--radius-sm);
}

.active-add-filter:hover {
  background: var(--color-surface-muted);
  color: var(--color-ink);
}

.active-reset-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 2px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  padding: 10px 18px;
  border-radius: var(--radius-md);
  flex-shrink: 0;
  transition: all 0.2s ease;
}

.active-reset-btn:hover {
  background: var(--color-surface-soft);
  color: var(--color-text);
}

.sort-popover {
  position: absolute;
  top: calc(100% + 8px);
  left: 0;
  width: 320px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: 0 12px 32px rgba(15, 23, 42, 0.16);
  z-index: 45;
  padding: 10px;
}

.sort-popover-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.sort-popover-footer {
  display: flex;
  align-items: stretch;
  gap: 0;
  margin-top: 8px;
}

.sort-popover-footer .filter-link-btn {
  flex: 1;
  justify-content: center;
  text-align: center;
  padding: 8px 4px;
}

.chip-count {
  font-size: 14px;
  opacity: 0.85;
}

.sort-popover-row + .sort-popover-row {
  margin-top: 8px;
}

.filter-link-btn.danger {
  color: var(--color-danger);
}

.filter-link-btn.danger:hover {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.search-box {
  position: relative;
}

.search-box input {
  padding: 10px 40px 10px 15px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  outline: none;
  min-width: 250px;
}

.search-box input:focus {
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

.filter-control {
  position: relative;
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
  color-scheme: only light;
  forced-color-adjust: none;
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

.edit-columns-trigger span {
  white-space: nowrap;
}

.notion-filter-icon {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 3px;
  width: 14px;
}

.notion-filter-icon span {
  display: block;
  height: 1.5px;
  background: var(--color-surface);
  border-radius: 2px;
}

.filter-trigger .notion-filter-icon span {
  background: var(--color-surface) !important;
  background-color: var(--color-surface) !important;
  forced-color-adjust: none;
}

.notion-filter-icon span:nth-child(1) {
  width: 14px;
}

.notion-filter-icon span:nth-child(2) {
  width: 10px;
}

.notion-filter-icon span:nth-child(3) {
  width: 6px;
}

.filter-trigger.active .notion-filter-icon span {
  background: var(--color-surface) !important;
  background-color: var(--color-surface) !important;
}

.filter-dot {
  position: absolute;
  top: 7px;
  right: 7px;
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #ed8936;
  box-shadow: 0 0 0 2px #fff;
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

.filter-panel.filter-panel-tall {
  height: 470px;
  display: flex;
  flex-direction: column;
}

.filter-panel-tall .filter-property-list,
.filter-panel-tall .chart-settings,
.filter-panel-tall .column-toggle-list,
.filter-panel-tall .chart-picker-list,
.filter-panel-tall .chart-color-list,
.filter-panel-tall .filter-rules,
.filter-panel-tall .filter-empty,
.filter-panel-tall .filter-sort-rules,
.filter-panel-tall .chart-range-custom {
  flex: 1;
  min-height: 0;
  max-height: none;
  overflow-y: auto;
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

.filter-menu-list {
  padding: 2px 6px 10px;
  display: flex;
  flex-direction: column;
}

.filter-menu-list .filter-property-item {
  min-height: 40px;
  padding: 10px 12px;
}

.filter-menu-actions {
  display: flex;
  align-items: stretch;
  gap: 0;
  margin-top: 4px;
  border-top: 1px solid var(--color-surface-muted);
}

.filter-menu-actions .filter-property-item {
  flex: 1;
  width: auto;
  justify-content: center;
  margin: 0;
  border-radius: 0;
}

.filter-property-item-primary {
  color: var(--color-brand);
}

.filter-property-item-primary:hover {
  background: var(--color-brand-soft);
  color: var(--color-brand-strong);
}

.filter-property-item-primary .filter-property-icon {
  color: var(--color-brand);
}

.filter-property-item-danger {
  color: var(--color-danger);
}

.filter-property-item-danger:hover {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.filter-property-item-danger .filter-property-icon {
  color: var(--color-danger);
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

.filter-sort-rules {
  padding: 4px 12px 8px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.modal-sort-footer {
  padding: 0 6px 10px;
  margin-top: 0;
  border-top: 1px solid var(--color-surface-muted);
}

.menu-mini-icon {
  width: 12px;
  gap: 2px;
}

.menu-mini-icon span {
  background: var(--color-muted);
}

.menu-mini-icon span:nth-child(1) {
  width: 12px;
}

.menu-mini-icon span:nth-child(2) {
  width: 8px;
}

.menu-mini-icon span:nth-child(3) {
  width: 5px;
}

.menu-column-icon {
  width: 12px;
  height: 12px;
  border: 1.5px solid var(--color-muted);
  border-radius: 2px;
  box-sizing: border-box;
  position: relative;
}

.menu-column-icon::after {
  content: "";
  position: absolute;
  top: 0;
  bottom: 0;
  left: 50%;
  width: 1.5px;
  background: var(--color-muted);
  transform: translateX(-50%);
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

.filter-icon-btn-danger {
  color: var(--color-danger);
}

.filter-icon-btn-danger:hover {
  background: var(--color-danger-soft);
  color: var(--color-danger);
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

.filter-property-list {
  max-height: 280px;
  overflow-y: auto;
  padding: 4px 6px 10px;
}

.filter-property-item {
  width: 100%;
  border: none;
  background: transparent;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
  border-radius: var(--radius-sm);
  cursor: pointer;
  color: var(--color-ink);
  font-size: 14px;
  text-align: left;
}

.filter-property-item:hover {
  background: var(--color-surface-muted);
}

.filter-property-item.selected {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.filter-selected-check {
  margin-left: auto;
  font-size: 14px;
  color: var(--color-info);
}

.filter-focused-title {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.filter-focused-body {
  padding: 4px 12px 12px;
}

.filter-focused-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
}

.filter-focused-label {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
  white-space: nowrap;
}

.filter-focused-input {
  width: 100%;
  box-sizing: border-box;
}

.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
  padding: 20px;
}

.modal-overlay-stack {
  z-index: 2100;
}

.modal-card {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: 28px;
  width: 100%;
  max-width: 420px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.modal-card-wide {
  max-width: 960px;
  padding: 20px 24px 24px;
  overflow: visible;
}

.modal-card-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.modal-card-head h3 {
  margin: 0;
}

.chart-detail-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin: 4px 0 16px;
}

.chart-detail-toolbar .chart-detail-meta {
  margin: 0;
  color: var(--color-brand);
  font-size: 16px;
  font-weight: 600;
  line-height: 1.25;
  min-width: 0;
  display: flex;
  align-items: center;
  align-self: center;
}

.chart-detail-empty {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 160px;
  color: var(--color-muted);
}

.chart-detail-table {
  max-height: 70vh;
}

.chart-detail-active-bar {
  padding: 4px 0 12px;
  margin: 0 0 8px;
  border-bottom: 1px solid var(--color-surface-muted);
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

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

.btn {
  border: none;
  border-radius: var(--radius-md);
  padding: 10px 16px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-secondary {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.btn-primary {
  background: var(--color-brand-solid);
  color: #fff;
}

.btn-primary:hover:not(:disabled) {
  opacity: 0.92;
}

.btn-danger {
  background: var(--color-danger-solid);
  color: #fff;
}

.btn-danger:hover:not(:disabled) {
  background: var(--color-danger-solid);
}

.filter-property-icon {
  width: 22px;
  text-align: center;
  color: var(--color-muted);
  font-size: 14px;
  font-weight: 600;
}

.filter-empty,
.filter-empty-hint {
  padding: 16px 14px;
  color: var(--color-muted);
  font-size: 14px;
  text-align: center;
}

.filter-rules {
  padding: 4px 12px 8px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  max-height: 360px;
  overflow-y: auto;
}

.filter-rule {
  padding: 10px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface-soft);
}

.filter-rule-top,
.filter-rule-bottom {
  display: flex;
  align-items: center;
  gap: 8px;
}

.filter-rule-bottom {
  margin-top: 8px;
}

.filter-select,
.filter-value-input {
  flex: 1;
  min-width: 0;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  padding: 7px 8px;
  font-size: 14px;
  color: var(--color-ink);
  outline: none;
}

.filter-select-op {
  max-width: 120px;
  flex: 0 0 120px;
}

.filter-select:focus,
.filter-value-input:focus {
  border-color: var(--color-brand);
}

.filter-panel-footer {
  display: flex;
  align-items: stretch;
  gap: 0;
  padding: 0;
  border-top: 1px solid var(--color-surface-muted);
}

.filter-panel-footer .filter-link-btn {
  flex: 1;
  justify-content: center;
  text-align: center;
  padding: 10px 4px;
  border-radius: 0;
}

.filter-link-btn {
  border: none;
  background: transparent;
  color: var(--color-text);
  font-size: 14px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 4px;
  border-radius: var(--radius-sm);
}

.filter-link-btn:hover {
  background: var(--color-surface-muted);
  color: var(--color-ink);
}

.transaction-table {
  width: max-content;
  min-width: 100%;
  border-collapse: collapse;
  table-layout: auto;
}

.table-scroll {
  width: 100%;
  max-width: 100%;
  min-width: 0;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  position: relative;
  z-index: 1;
}

.table-scroll--limited {
  max-height: min(640px, calc(100vh - 360px));
  overflow-y: auto;
}

.transaction-table thead {
  background: var(--color-surface-soft);
  position: relative;
  z-index: 1;
}

.table-scroll--limited .transaction-table thead th {
  position: sticky;
  top: 0;
  z-index: 3;
  background: var(--color-surface-soft);
}

.transaction-table th {
  padding: 16px 20px;
  text-align: left;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 2px solid var(--color-border);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  min-width: 200px;
}

.transaction-table th.checkbox-col,
.transaction-table td.checkbox-col {
  width: 50px;
  min-width: 50px;
  max-width: 50px;
  padding: 16px 10px !important;
}

.transaction-table th.sortable {
  cursor: grab;
  user-select: none;
  position: relative;
  z-index: 1;
  padding-right: 52px;
  transition: all 0.2s ease;
}

.transaction-table th.sortable.is-filtered {
  color: var(--color-brand-strong);
}

.th-label-row {
  display: flex;
  align-items: center;
  min-width: 0;
}

.th-label-text {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  min-width: 0;
  flex: 1;
}

.th-filter-btn {
  position: absolute;
  right: 28px;
  top: 50%;
  transform: translateY(-50%);
  width: 22px;
  height: 22px;
  border: none;
  border-radius: 4px;
  background: transparent;
  color: var(--color-faint);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  padding: 0;
  z-index: 2;
}

.th-filter-btn:hover,
.th-filter-btn.active {
  color: var(--color-brand-strong);
  background: rgba(var(--color-brand-rgb), 0.12);
}

.header-filter-panel {
  position: fixed;
  z-index: 1200;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 12px;
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12);
  display: flex;
  flex-direction: column;
  gap: 10px;
  max-height: min(420px, calc(100vh - 24px));
}

.header-filter-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--color-ink);
}

.header-filter-sorts {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.header-filter-values {
  overflow: auto;
  max-height: 220px;
  border: 1px solid var(--color-surface-muted);
  border-radius: var(--radius-md);
  padding: 6px 8px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.header-filter-value-row {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: var(--color-text);
  padding: 4px 2px;
  cursor: pointer;
}

.header-filter-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.header-filter-apply {
  margin-left: auto;
}

.transaction-table th.sortable:hover {
  background: var(--color-surface-muted);
  color: var(--color-brand);
}

.transaction-table th.sortable.is-dragging {
  opacity: 0.45;
  cursor: grabbing;
}

.transaction-table th.sortable.drag-over {
  box-shadow: inset 3px 0 0 var(--color-brand);
}

.col-resize-handle {
  position: absolute;
  top: 0;
  right: 0;
  width: 6px;
  height: 100%;
  cursor: col-resize;
  z-index: 2;
}

.col-resize-handle:hover,
.col-resize-handle:active {
  background: rgba(var(--color-brand-rgb), 0.35);
}

.transaction-table td {
  padding: 16px 20px;
  font-size: 14px;
  color: var(--color-text);
  border-bottom: 1px solid var(--color-border);
  overflow: hidden;
  text-overflow: ellipsis;
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
  transition: all 0.2s ease;
}

.transaction-table th.sortable:hover .sort-icon {
  opacity: 0.6;
}

.sort-icon i {
  font-size: 14px;
  line-height: 1;
}

.sort-icon i.active {
  opacity: 1;
  color: var(--color-brand);
}

.transaction-table td {
  padding: 16px 20px;
  font-size: 14px;
  color: var(--color-text);
  border-bottom: 1px solid var(--color-border);
}

.transaction-table td.cell-clip {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.transaction-table tbody tr:hover {
  background: var(--color-surface-soft);
}

.transaction-table tbody tr.expanded {
  background: var(--color-surface-soft);
}

.transaction-table th.action-col,
.transaction-table td.action-col {
  min-width: 120px;
  width: 120px;
  overflow: visible;
  white-space: nowrap;
  text-transform: none;
  letter-spacing: 0;
}

.report-detail-btn {
  padding: 8px 14px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.report-detail-btn:hover {
  background: var(--color-brand-solid);
  color: white;
}

.report-detail-row > td.report-detail-cell {
  max-width: 0;
  width: 100%;
  overflow: visible;
  padding: 0;
  background: var(--color-surface-soft);
  border-top: 3px solid var(--color-brand);
  vertical-align: top;
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
  background: var(--color-brand-solid);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: 700;
  font-size: 14px;
  flex-shrink: 0;
}

.client-name {
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 3px;
}

.client-id {
  font-size: 14px;
  color: var(--color-muted);
}

.time-small {
  color: var(--color-faint);
  font-size: 14px;
  display: block;
  margin-top: 2px;
}

.transaction-type {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 14px;
  font-weight: 600;
}

.transaction-type.deposit {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.transaction-type.withdrawal {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.transaction-type.internal_transfer {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.amount-display {
  font-weight: 700;
  font-size: 16px;
}

.amount-display.positive {
  color: var(--color-success);
}

.amount-display.negative {
  color: var(--color-danger);
}

.amount-display.neutral {
  color: var(--color-text);
}

.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: var(--radius-lg);
  font-size: 14px;
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

.payment-method-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 14px;
  font-weight: 600;
}

.payment-method-badge.crypto {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.payment-method-badge.fiat {
  background: var(--color-info-soft);
  color: var(--color-info);
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

.pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 30px;
  border-top: 2px solid var(--color-border);
}

.pagination-info {
  font-size: 14px;
  color: var(--color-muted);
  display: flex;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
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

.rows-selector select:hover {
  border-color: var(--color-brand);
}

.rows-selector select:focus {
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.chart-pagination {
  border-top: none;
  padding: 12px 0 4px;
  flex-wrap: wrap;
  gap: 12px;
}

.pagination-controls {
  display: flex;
  gap: 8px;
}

.pagination-btn {
  padding: 8px 12px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  color: var(--color-text);
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.pagination-btn:hover:not(:disabled) {
  border-color: var(--color-brand);
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.pagination-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.pagination-btn.active {
  background: var(--color-brand-solid);
  color: white;
  border-color: var(--color-brand);
}

.pagination-ellipsis {
  padding: 8px 12px;
  color: var(--color-faint);
  font-weight: 600;
}

.chart-settings {
  position: relative;
  padding: 0 12px 14px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  max-height: 420px;
  overflow-y: auto;
}

.chart-picker-trigger {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  width: 100%;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  padding: 7px 10px;
  font-size: 14px;
  color: var(--color-ink);
  cursor: pointer;
  text-align: left;
}

.chart-picker-trigger i {
  font-size: 14px;
  color: var(--color-faint);
}

.chart-picker-trigger:hover {
  border-color: var(--color-border-strong);
}

.chart-picker-overlay {
  position: absolute;
  inset: 0;
  background: rgba(247, 250, 252, 0.55);
  z-index: 5;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 8px 0 12px;
}

.chart-picker-menu {
  width: 100%;
  background: var(--color-surface);
  border-radius: var(--radius-md);
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
  border: 1px solid var(--color-surface-muted);
  overflow: hidden;
}

.chart-picker-list {
  max-height: 320px;
  overflow-y: auto;
  padding: 4px 0 10px;
}

.chart-picker-item {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  border: none;
  background: var(--color-surface);
  padding: 9px 14px;
  font-size: 14px;
  color: var(--color-ink);
  cursor: pointer;
  text-align: left;
}

.chart-picker-item .filter-selected-check {
  margin-left: auto;
}

.chart-picker-item.stripe {
  background: var(--color-surface-soft);
}

.chart-picker-item:hover,
.chart-picker-item.selected {
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.chart-range-custom {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 12px 14px 16px;
}

.chart-range-custom-heading {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
}

.chart-range-custom-inputs {
  display: flex;
  align-items: center;
  gap: 8px;
}

.chart-range-input {
  flex: 1;
  min-width: 0;
  height: 36px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  padding: 0 10px;
  font-size: 14px;
  color: var(--color-ink);
}

.chart-range-input:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 2px rgba(var(--color-brand-rgb), 0.15);
}

.chart-range-sep {
  flex: 0 0 auto;
  color: var(--color-faint);
  font-size: 14px;
  font-weight: 600;
}

.chart-picker-item i {
  font-size: 14px;
  color: var(--color-brand);
}

.chart-settings-section {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.chart-settings-label,
.chart-settings-heading {
  font-size: 14px;
  font-weight: 700;
  color: var(--color-text);
  text-transform: uppercase;
  letter-spacing: 0.03em;
  padding: 0 4px 8px;
}

.chart-settings-section .chart-type-grid,
.chart-settings-section .chart-field-row {
  margin-left: 16px;
  width: calc(100% - 16px);
}

.chart-settings-section .filter-property-item {
  min-height: 40px;
  padding: 10px 12px;
}

.chart-filter-count {
  margin-left: auto;
  min-width: 18px;
  height: 18px;
  padding: 0 6px;
  border-radius: 999px;
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-size: 14px;
  font-weight: 700;
  line-height: 18px;
  text-align: center;
}

.chart-type-grid {
  display: flex;
  gap: 8px;
}

.chart-type-option {
  width: 44px;
  height: 44px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.chart-type-option.active {
  border-color: var(--color-brand);
  box-shadow: 0 0 0 2px rgba(var(--color-brand-rgb), 0.15);
}

.chart-type-icon {
  display: flex;
  gap: 3px;
  align-items: flex-end;
  height: 16px;
}

.icon-bar-v i {
  display: block;
  width: 4px;
  background: var(--color-text);
  border-radius: 1px;
}

.icon-bar-v i:nth-child(1) {
  height: 8px;
}
.icon-bar-v i:nth-child(2) {
  height: 14px;
}
.icon-bar-v i:nth-child(3) {
  height: 11px;
}

.icon-bar-h {
  flex-direction: column;
  align-items: flex-start;
  justify-content: center;
  gap: 2px;
  width: 16px;
}

.icon-bar-h i {
  display: block;
  height: 3px;
  background: var(--color-text);
  border-radius: 1px;
}

.icon-bar-h i:nth-child(1) {
  width: 16px;
}
.icon-bar-h i:nth-child(2) {
  width: 11px;
}
.icon-bar-h i:nth-child(3) {
  width: 14px;
}

.chart-field,
.chart-toggle-row {
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  font-size: 14px;
  color: var(--color-text);
}

.chart-field-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  width: 100%;
  min-height: 44px;
  padding: 12px 8px;
  border: none;
  border-bottom: 1px solid var(--color-surface-muted);
  background: transparent;
  font-size: 14px;
  color: var(--color-ink);
  cursor: pointer;
  text-align: left;
}

.chart-field-row:last-child {
  border-bottom: none;
}

.chart-field-row:hover {
  background: var(--color-surface-soft);
}

.chart-field-left,
.chart-field-right {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.chart-field-left i {
  width: 14px;
  color: var(--color-muted);
  font-size: 14px;
  text-align: center;
}

.chart-field-right {
  flex-shrink: 0;
  color: var(--color-muted);
}

.chart-field-right span {
  max-width: 140px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.chart-field-right i {
  font-size: 14px;
  color: var(--color-faint);
}

.chart-toggle-row {
  min-height: 40px;
}

.chart-stage {
  position: relative;
  display: flex;
  flex-direction: column;
  padding: 24px 30px 30px;
  min-height: 780px;
  overflow: visible;
}

.chart-tooltip {
  position: absolute;
  z-index: 8;
  width: 260px;
  max-width: calc(100% - 24px);
  padding: 12px 14px;
  background: var(--color-ink-strong);
  color: var(--color-surface-muted);
  border-radius: var(--radius-md);
  box-shadow: 0 10px 28px rgba(15, 23, 42, 0.35);
  pointer-events: auto;
  cursor: pointer;
  overflow-x: hidden;
  box-sizing: border-box;
}

.chart-tooltip-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 10px;
}

.chart-tooltip-title {
  min-width: 0;
  font-size: 14px;
  font-weight: 700;
  color: #fff;
  overflow-wrap: anywhere;
}

.chart-tooltip-total {
  flex-shrink: 0;
  font-size: 14px;
  font-weight: 700;
  color: #fff;
}

.chart-tooltip-rows {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 220px;
  overflow-x: hidden;
  overflow-y: auto;
  padding-bottom: 10px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.12);
}

.chart-tooltip-row,
.chart-tooltip-main {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  font-size: 14px;
}

.chart-tooltip-row {
  border-radius: 4px;
  padding: 2px 4px;
  margin: 0 -4px;
  cursor: default;
}

.chart-tooltip-row:hover,
.chart-tooltip-row.is-hot {
  background: rgba(255, 255, 255, 0.08);
}

.chart-tooltip-main {
  padding-bottom: 8px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.12);
}

.chart-tooltip-row i,
.chart-tooltip-main i {
  width: 10px;
  height: 10px;
  border-radius: 2px;
  background: var(--serie-0, #3b82f6);
  flex-shrink: 0;
  margin-top: 4px;
}

.chart-tooltip-row i.serie-1,
.chart-tooltip-main i.serie-1 {
  background: var(--serie-1, #eab308);
}
.chart-tooltip-row i.serie-2,
.chart-tooltip-main i.serie-2 {
  background: var(--serie-2, #22c55e);
}
.chart-tooltip-row i.serie-3,
.chart-tooltip-main i.serie-3 {
  background: var(--serie-3, var(--color-accent));
}
.chart-tooltip-row i.serie-4,
.chart-tooltip-main i.serie-4 {
  background: var(--serie-4, #f97316);
}

.chart-tooltip-row span,
.chart-tooltip-main span {
  flex: 1;
  min-width: 0;
  overflow-wrap: anywhere;
  word-break: break-word;
  white-space: normal;
}

.chart-tooltip-row strong,
.chart-tooltip-main strong {
  flex-shrink: 0;
  font-weight: 600;
}

.chart-tooltip-hint {
  display: flex;
  align-items: center;
  gap: 8px;
  padding-top: 8px;
  font-size: 14px;
  color: var(--color-faint);
}

.chart-empty,
.chart-ready {
  flex: 1;
  min-height: 280px;
  overflow: visible;
}

.chart-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 16px;
  width: 100%;
  text-align: center;
  color: var(--color-muted);
}

.chart-empty-plot {
  width: 640px;
  max-width: 100%;
  height: 180px;
  border-left: 2px dashed var(--color-border-strong);
  border-bottom: 2px dashed var(--color-border-strong);
  display: flex;
  align-items: flex-end;
  justify-content: space-around;
  padding: 0 24px 8px;
}

.chart-empty-plot span {
  width: 28px;
  background: var(--color-surface-muted);
  border-radius: 4px 4px 0 0;
}

.chart-empty-plot span:nth-child(1) {
  height: 40%;
}
.chart-empty-plot span:nth-child(2) {
  height: 70%;
}
.chart-empty-plot span:nth-child(3) {
  height: 25%;
}
.chart-empty-plot span:nth-child(4) {
  height: 55%;
}

.chart-vertical {
  display: flex;
  gap: 12px;
  height: 720px;
}

.chart-y-labels {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  font-size: 14px;
  color: var(--color-muted);
  min-width: 36px;
  text-align: right;
  padding: 18px 10px 20px 0;
  box-sizing: border-box;
}

.chart-plot {
  flex: 1;
  min-width: 0;
  overflow-x: auto;
  overflow-y: hidden;
  display: flex;
  flex-direction: column;
  padding: 4px 12px 12px 0;
  scrollbar-gutter: stable;
  box-sizing: border-box;
}

.chart-bars {
  flex: 1;
  display: flex;
  align-items: flex-end;
  gap: 8px;
  min-height: 0;
  height: 100%;
  border-left: 1px solid var(--color-border);
  border-bottom: 1px solid var(--color-border);
  padding: 18px 8px 0;
  box-sizing: border-box;
}

.chart-col {
  flex: 0 0 56px;
  width: 56px;
  display: flex;
  flex-direction: column;
  align-items: center;
  height: 100%;
  justify-content: flex-end;
}

.chart-col-plot {
  position: relative;
  flex: 1;
  width: 100%;
  min-height: 0;
  display: flex;
  justify-content: center;
}

.chart-col-total {
  position: absolute;
  left: 0;
  right: 0;
  transform: translateY(-100%);
  padding-bottom: 2px;
  text-align: center;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
  line-height: 1.2;
  pointer-events: none;
}

.chart-stack {
  width: 70%;
  max-width: 48px;
  height: 100%;
  display: flex;
  flex-direction: column-reverse;
  justify-content: flex-start;
}

.chart-col,
.chart-h-row {
  cursor: pointer;
}

.chart-bar {
  width: 100%;
  background: var(--serie-0, #3b82f6);
  border-radius: 4px 4px 0 0;
  min-height: 3px;
}

.chart-ready.has-hot-slice .chart-bar,
.chart-ready.has-hot-slice .chart-h-bar {
  opacity: calc(1 - 0.75 * min(1, abs(var(--serie) - var(--hot))));
}

.chart-bar.serie-1 {
  background: var(--serie-1, #eab308);
}
.chart-bar.serie-2 {
  background: var(--serie-2, #22c55e);
}
.chart-bar.serie-3 {
  background: var(--serie-3, var(--color-accent));
}
.chart-bar.serie-4 {
  background: var(--serie-4, #f97316);
}

.chart-x-label {
  margin-top: 8px;
  font-size: 14px;
  color: var(--color-text);
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.chart-horizontal {
  display: flex;
  flex-direction: column;
  gap: 10px;
  height: 720px;
  max-height: 720px;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 8px 16px 8px 12px;
  scrollbar-gutter: stable;
  box-sizing: border-box;
}

.chart-truncated-hint {
  margin: 8px 0 0;
  font-size: 14px;
  color: var(--color-muted);
}

.chart-h-row {
  display: grid;
  grid-template-columns: 140px 1fr 56px;
  gap: 10px;
  align-items: center;
}

.chart-h-label {
  font-size: 14px;
  color: var(--color-text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.chart-h-track {
  height: 18px;
  background: var(--color-surface-muted);
  border-radius: var(--radius-sm);
  overflow: hidden;
  display: flex;
}

.chart-h-bar {
  height: 100%;
  min-width: 3px;
  background: var(--serie-0, #3b82f6);
}

.chart-h-bar.serie-1 {
  background: var(--serie-1, #eab308);
}
.chart-h-bar.serie-2 {
  background: var(--serie-2, #22c55e);
}
.chart-h-bar.serie-3 {
  background: var(--serie-3, var(--color-accent));
}
.chart-h-bar.serie-4 {
  background: var(--serie-4, #f97316);
}

.chart-h-value {
  font-size: 14px;
  color: var(--color-muted);
  text-align: right;
}

.chart-legend {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 16px;
  margin-top: 16px;
  font-size: 14px;
  color: var(--color-text);
}

.chart-legend-items {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  align-items: center;
  gap: 12px;
  width: 100%;
}

.chart-legend-item {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
  user-select: none;
}

.chart-legend-item.is-off {
  opacity: 0.35;
  text-decoration: line-through;
}

.chart-legend-item.is-off i {
  filter: grayscale(1);
}

.chart-ready.has-hot-slice .chart-legend-item {
  opacity: calc(1 - 0.65 * min(1, abs(var(--serie) - var(--hot))));
}

.chart-ready.has-hot-slice .chart-legend-item.is-off {
  opacity: 0.25;
}

.chart-legend-item i {
  width: 10px;
  height: 10px;
  border-radius: 2px;
  background: var(--serie-0, #3b82f6);
}

.chart-legend-item i.serie-1 {
  background: var(--serie-1, #eab308);
}
.chart-legend-item i.serie-2 {
  background: var(--serie-2, #22c55e);
}
.chart-legend-item i.serie-3 {
  background: var(--serie-3, var(--color-accent));
}
.chart-legend-item i.serie-4 {
  background: var(--serie-4, #f97316);
}

.chart-color-list {
  display: flex;
  flex-direction: column;
  padding: 4px 0 8px;
  max-height: 320px;
  overflow-y: auto;
}

.chart-color-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  width: 100%;
  border: none;
  background: var(--color-surface);
  padding: 9px 14px;
  font-size: 14px;
  color: var(--color-ink);
  cursor: pointer;
  text-align: left;
}

.chart-color-item:hover,
.chart-color-item.selected {
  background: var(--color-surface-muted);
}

.chart-color-swatches {
  display: inline-flex;
  gap: 3px;
  flex-shrink: 0;
}

.chart-color-swatches i {
  width: 14px;
  height: 14px;
  border-radius: 3px;
}

.chart-legend-toggle {
  align-self: center;
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

.export-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  z-index: 2000;
}

.export-modal {
  width: min(100%, 480px);
  background: var(--color-surface);
  border-radius: 18px;
  box-shadow: 0 25px 60px rgba(15, 23, 42, 0.2);
  overflow: hidden;
}

.export-modal-header,
.export-modal-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 22px;
}

.export-modal-header {
  border-bottom: 1px solid var(--color-border);
}

.export-modal-header h3 {
  margin: 0;
  font-size: 18px;
  color: var(--color-ink);
}

.export-modal-close {
  border: none;
  background: transparent;
  font-size: 24px;
  line-height: 1;
  cursor: pointer;
  color: var(--color-muted);
}

.export-modal-body {
  padding: 20px 22px;
}

.export-modal-text {
  margin: 0 0 16px;
  color: var(--color-text);
  font-size: 14px;
  line-height: 1.5;
}

.export-modal-progress {
  height: 8px;
  background: var(--color-border);
  border-radius: 999px;
  overflow: hidden;
}

.export-modal-progress-bar {
  height: 100%;
  background: var(--color-brand-solid);
  transition: width 0.3s ease;
}

.export-modal-percent {
  margin: 10px 0 0;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
}

.export-modal-footer {
  border-top: 1px solid var(--color-border);
  gap: 12px;
  justify-content: flex-end;
}

.export-modal-btn {
  padding: 10px 16px;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  border: none;
}

.export-modal-btn.secondary {
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  color: var(--color-text);
}

.export-modal-btn.primary {
  background: var(--color-brand-solid);
  color: #fff;
}

.export-modal-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.export-column-mode-grid {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin: 0 0 16px;
}

.export-column-mode-option {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  width: 100%;
  padding: 12px 14px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  text-align: left;
  cursor: pointer;
  color: var(--color-text);
  transition: all 0.2s ease;
}

.export-column-mode-option:hover {
  border-color: var(--color-border-strong);
  background: var(--color-surface-soft);
}

.export-column-mode-option.active {
  background: var(--color-brand-solid);
  color: #fff;
  border-color: var(--color-brand);
}

.export-column-mode-icon {
  width: 36px;
  height: 36px;
  flex-shrink: 0;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  color: var(--color-brand);
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.export-column-mode-option.active .export-column-mode-icon {
  background: rgba(255, 255, 255, 0.18);
  color: #fff;
}

.export-column-mode-copy {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.export-column-mode-copy strong {
  font-size: 14px;
  font-weight: 600;
}

.export-column-mode-copy small {
  font-size: 14px;
  font-weight: 500;
  color: var(--color-muted);
  line-height: 1.4;
}

.export-column-mode-option.active .export-column-mode-copy small {
  color: rgba(255, 255, 255, 0.82);
}

.export-column-picker {
  margin: 0 0 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  overflow: hidden;
}

.export-column-picker .filter-property-search {
  margin: 10px 10px 0;
}

.export-column-list {
  max-height: min(280px, calc(100vh - 420px));
  overflow-y: auto;
}
</style>
