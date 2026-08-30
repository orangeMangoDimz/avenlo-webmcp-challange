<template>
  <div class="ib-statement-page">
    <div class="page-header">
      <div class="page-title">
        <h1>{{ t("page_ibStatement_title", "IB P&L Report") }}</h1>
        <p>
          {{
            t(
              "page_ibStatement_sub",
              "Introducing broker client P&L statement by partner and period.",
            )
          }}
        </p>
      </div>
      <div class="page-actions">
        <PageHeaderActions />
      </div>
    </div>

    <div v-if="!hasReadonlyPermission" class="error-message">
      <i class="fas fa-exclamation-circle"></i>
      {{
        t(
          "ibStatement_noPermission",
          "You do not have permission to view this page.",
        )
      }}
    </div>

    <template v-else>
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
        :cancel-label="t('ibReport_exportCancel', 'Cancel')"
        @cancel-export="cancelActiveExport"
      />

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

      <div class="date-filter-section">
        <el-config-provider :locale="elementPlusLocale">
          <div class="date-filter-container">
            <span class="date-filter-label">{{
              t("ibReport_timePeriod", "Time Period:")
            }}</span>
            <div class="date-filter-presets">
              <button
                type="button"
                :class="['preset-btn', { active: activePreset === 'today' }]"
                @click="selectPreset('today')"
              >
                {{ t("ibReport_preset_today", "Today") }}
              </button>
              <button
                type="button"
                :class="['preset-btn', { active: activePreset === 'month' }]"
                @click="selectPreset('month')"
              >
                {{ t("ibReport_preset_month", "This Month") }}
              </button>
              <button
                type="button"
                :class="['preset-btn', { active: activePreset === 'quarter' }]"
                @click="selectPreset('quarter')"
              >
                {{ t("ibReport_preset_quarter", "This Quarter") }}
              </button>
              <button
                type="button"
                :class="['preset-btn', { active: activePreset === 'year' }]"
                @click="selectPreset('year')"
              >
                {{ t("ibReport_preset_year", "This Year") }}
              </button>
            </div>
            <div class="date-input-wrapper">
              <label>{{ t("ibReport_fromDate", "From Date") }}</label>
              <el-date-picker
                v-model="startDate"
                type="date"
                value-format="YYYY-MM-DD"
                :placeholder="t('ibReport_fromDate', 'From Date')"
                class="filter-date"
                clearable
                @change="activePreset = ''"
              />
            </div>
            <div class="date-input-wrapper">
              <label>{{ t("ibReport_toDate", "To Date") }}</label>
              <el-date-picker
                v-model="endDate"
                type="date"
                value-format="YYYY-MM-DD"
                :placeholder="t('ibReport_toDate', 'To Date')"
                class="filter-date"
                clearable
                @change="activePreset = ''"
              />
            </div>
            <button
              type="button"
              class="btn-apply-filter"
              :disabled="loading"
              @click="loadStatement"
            >
              <i class="fas fa-filter"></i>
              {{ t("ibReport_applyFilter", "Apply Filter") }}
            </button>
            <div v-if="hasExportPermission" class="export-wrap">
              <button
                type="button"
                class="btn-export"
                :disabled="isExportRunning || !statement"
                @click.stop="toggleExportDropdown"
              >
                <i class="fas fa-download"></i>
                {{ t("ibReport_export", "Export") }}
              </button>
              <div :class="['export-dropdown', { show: showExportDropdown }]">
                <div
                  class="export-option csv"
                  @click.stop="handleExport('csv')"
                >
                  <i class="fas fa-file-csv"></i>
                  <span>{{ t("fundingReport_export_csv", "CSV") }}</span>
                </div>
                <div
                  class="export-option excel"
                  @click.stop="handleExport('excel')"
                >
                  <i class="fas fa-file-excel"></i>
                  <span>{{ t("fundingReport_export_excel", "Excel") }}</span>
                </div>
              </div>
            </div>
          </div>
        </el-config-provider>
      </div>

      <div class="search-filter-section">
        <div class="search-container">
          <div
            class="search-field ib-select-field"
            :class="{ 'ib-select-field--open': showIbDropdown }"
          >
            <label>{{ t("ibStatement_label_ib", "Introducing Broker") }}</label>
            <div class="ib-select-search" @click.stop>
              <div class="ib-select-search__input-wrap">
                <i class="fas fa-search ib-select-search__icon"></i>
                <input
                  ref="ibInputRef"
                  type="text"
                  class="ib-select-search__input"
                  :value="ibInputDisplay"
                  :placeholder="ibSelectPlaceholder"
                  autocomplete="off"
                  @input="onIbInput"
                  @focus="onIbFocus"
                />
              </div>
              <div
                v-show="showIbDropdown"
                class="ib-select-search__dropdown"
                @mousedown.prevent
              >
                <div
                  v-if="filteredPartners.length === 0"
                  class="ib-select-search__empty"
                >
                  {{
                    ibSearch.trim()
                      ? t("ibStatement_noIbMatch", "No IB matched your search.")
                      : t(
                          "ibStatement_noIb",
                          "No introducing brokers available.",
                        )
                  }}
                </div>
                <div v-else class="ib-select-search__list">
                  <div
                    v-for="ib in filteredPartners"
                    :key="ib.id"
                    class="ib-select-option"
                    :class="{
                      'ib-select-option--selected':
                        String(ib.id) === String(selectedIbId),
                    }"
                    @mousedown.prevent="selectPartner(ib)"
                  >
                    <span class="ib-select-option__avatar">{{
                      partnerInitials(ib)
                    }}</span>
                    <div class="ib-select-option__info">
                      <span class="ib-select-option__name">{{
                        ib.name || partnerName(ib)
                      }}</span>
                      <span v-if="ib.ibCode" class="ib-select-option__code">{{
                        ib.ibCode
                      }}</span>
                    </div>
                    <i
                      v-if="String(ib.id) === String(selectedIbId)"
                      class="fas fa-check ib-select-option__check"
                    ></i>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="error" class="error-message">
        <i class="fas fa-exclamation-circle"></i> {{ error }}
      </div>

      <div v-if="loading" class="loading-container">
        <i class="fas fa-spinner fa-spin"></i>
        {{ t("ibStatement_loading", "Loading IB statement...") }}
      </div>

      <div v-else-if="!statement" class="empty-state">
        <i class="fas fa-file-invoice-dollar"></i>
        <p>
          {{
            t(
              "ibStatement_emptyHint",
              "Select an introducing broker and apply the date filter.",
            )
          }}
        </p>
      </div>

      <template v-else>
        <div class="stats-grid">
          <div class="stat-card total">
            <div class="stat-header">
              <span class="stat-title">{{
                t("ibStatement_stat_clientsAccounts", "Clients / Accounts")
              }}</span>
              <div class="stat-icon"><i class="fas fa-users"></i></div>
            </div>
            <div class="stat-value">
              {{ headline.clientCount }} / {{ headline.accountCount }}
            </div>
            <div class="stat-footer">
              {{
                tParams(
                  "ibStatement_stat_fundedTraded",
                  "{funded} funded, {traded} traded",
                  {
                    funded: headline.fundedCount,
                    traded: headline.tradedCount,
                  },
                )
              }}
            </div>
          </div>
          <div class="stat-card paid">
            <div class="stat-header">
              <span class="stat-title">{{
                t("ibStatement_stat_deposits", "Total deposits")
              }}</span>
              <div class="stat-icon"><i class="fas fa-arrow-down"></i></div>
            </div>
            <div class="stat-value">
              {{ formatCurrency(headline.totalDeposits) }}
            </div>
          </div>
          <div class="stat-card total-lots">
            <div class="stat-header">
              <span class="stat-title">{{
                t("ibStatement_stat_withdrawals", "Total withdrawals")
              }}</span>
              <div class="stat-icon"><i class="fas fa-arrow-up"></i></div>
            </div>
            <div class="stat-value">
              {{ formatCurrency(headline.totalWithdrawals) }}
            </div>
          </div>
          <div class="stat-card total-trade">
            <div class="stat-header">
              <span class="stat-title">{{
                t("ibStatement_stat_closing", "Closing balance")
              }}</span>
              <div class="stat-icon"><i class="fas fa-wallet"></i></div>
            </div>
            <div class="stat-value">
              {{ formatCurrency(headline.closingBalance) }}
            </div>
          </div>
        </div>

        <div class="transaction-table-container">
          <div class="table-header">
            <h2>
              <i class="fas fa-exchange-alt"></i>
              {{
                t("ibStatement_section_movement", "Account Movement Summary")
              }}
            </h2>
          </div>
          <table class="transaction-table">
            <thead>
              <tr>
                <th>{{ t("ibStatement_th_item", "Item") }}</th>
                <th>{{ t("ibStatement_th_amount", "Amount (USD)") }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in movementPagedRows" :key="row.key">
                <td>{{ row.label }}</td>
                <td>
                  <div :class="['amount-display', amountTone(row.value)]">
                    {{ formatSignedCurrency(row.value) }}
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
          <div v-if="movementPager.totalPages > 1" class="pagination">
            <div class="pagination-info">
              {{
                tParams(
                  "ibStatement_pagination_range_items",
                  "Showing {from}–{to} of {total} items",
                  {
                    from: formatNumber(movementPager.range.from),
                    to: formatNumber(movementPager.range.to),
                    total: formatNumber(movementPager.range.total),
                  },
                )
              }}
            </div>
            <div class="pagination-controls">
              <button
                type="button"
                class="pagination-btn"
                :disabled="movementPager.currentPage === 1"
                @click="movementPager.goTo(movementPager.currentPage - 1)"
              >
                <i class="fas fa-chevron-left"></i>
                {{ t("ibReport_pagination_previous", "Previous") }}
              </button>
              <template
                v-for="(page, index) in movementPageList"
                :key="`movement-${page}-${index}`"
              >
                <button
                  v-if="page !== '...'"
                  type="button"
                  :class="[
                    'pagination-btn',
                    { active: movementPager.currentPage === page },
                  ]"
                  @click="movementPager.goTo(page)"
                >
                  {{ page }}
                </button>
                <span v-else class="pagination-ellipsis">...</span>
              </template>
              <button
                type="button"
                class="pagination-btn"
                :disabled="
                  movementPager.currentPage === movementPager.totalPages
                "
                @click="movementPager.goTo(movementPager.currentPage + 1)"
              >
                {{ t("ibReport_pagination_next", "Next") }}
                <i class="fas fa-chevron-right"></i>
              </button>
            </div>
          </div>
        </div>

        <div class="transaction-table-container">
          <div class="table-header">
            <h2>
              <i class="fas fa-list"></i>
              {{ t("ibStatement_section_accounts", "Client Account Detail") }}
            </h2>
            <div class="table-controls">
              <el-tooltip
                :content="
                  t(
                    'ibStatement_unfundedHint',
                    'No completed deposit and no closed trade in the selected date range',
                  )
                "
                placement="top"
              >
                <button
                  type="button"
                  :class="['preset-btn', { active: showUnfunded }]"
                  @click="showUnfunded = !showUnfunded"
                >
                  {{ t("ibStatement_showUnfunded", "Unfunded") }}
                </button>
              </el-tooltip>
              <div class="search-box">
                <input
                  v-model="accountSearch"
                  type="text"
                  :placeholder="
                    t('ibStatement_searchAccounts', 'Search login or name')
                  "
                />
                <i class="fas fa-search search-icon"></i>
              </div>
            </div>
          </div>
          <table class="transaction-table accounts-detail-table">
            <thead>
              <tr>
                <th>{{ t("ibStatement_th_clientName", "Client name") }}</th>
                <th>{{ t("ibStatement_th_type", "Type") }}</th>
                <th>{{ t("ibStatement_th_login", "Login") }}</th>
                <th>{{ t("ibStatement_th_opened", "Opened") }}</th>
                <th>{{ t("ibStatement_th_deposits", "Deposits") }}</th>
                <th>{{ t("ibStatement_th_withdrawals", "Withdrawals") }}</th>
                <th>{{ t("ibStatement_th_netDeposits", "Net deposits") }}</th>
                <th>{{ t("ibStatement_th_lots", "Lots") }}</th>
                <th>{{ t("ibStatement_th_trades", "Trades") }}</th>
                <th>{{ t("ibStatement_th_result", "Trading result") }}</th>
                <th>{{ t("ibStatement_th_balance", "Balance") }}</th>
              </tr>
            </thead>
            <tbody>
              <template v-if="filteredAccounts.length === 0">
                <tr>
                  <td colspan="11" class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>
                      {{
                        t(
                          "ibStatement_noAccounts",
                          "No accounts in this period.",
                        )
                      }}
                    </p>
                  </td>
                </tr>
              </template>
              <template v-else>
                <tr
                  v-for="row in accountPagedRows"
                  :key="row.login"
                  :class="{ unfunded: row.unfunded }"
                >
                  <td>
                    <div class="client-info">
                      <div class="client-avatar">
                        {{ clientInitials(row.clientName) }}
                      </div>
                      <div class="client-details">
                        <div class="client-name">
                          {{ row.clientName || "—" }}
                        </div>
                        <div v-if="row.clientId" class="client-id">
                          {{ t("clientId", "Client ID") }} #{{ row.clientId }}
                        </div>
                        <el-tooltip
                          v-if="row.unfunded"
                          :content="
                            t(
                              'ibStatement_unfundedHint',
                              'No completed deposit and no closed trade in the selected date range',
                            )
                          "
                          placement="top"
                        >
                          <span class="unfunded-label">{{
                            t("ibStatement_unfunded", "Unfunded")
                          }}</span>
                        </el-tooltip>
                      </div>
                    </div>
                  </td>
                  <td>
                    <span
                      :class="['type-badge', clientTypeClass(row.clientType)]"
                      >{{ clientTypeLabel(row.clientType) }}</span
                    >
                  </td>
                  <td>
                    <span class="login-badge">{{ row.login }}</span>
                  </td>
                  <td>{{ row.opened || "—" }}</td>
                  <td>
                    <div :class="['amount-display', amountTone(row.deposits)]">
                      {{ dashZero(row.deposits) }}
                    </div>
                  </td>
                  <td>
                    <div
                      :class="['amount-display', amountTone(row.withdrawals)]"
                    >
                      {{ dashZero(row.withdrawals) }}
                    </div>
                  </td>
                  <td>
                    <div
                      :class="['amount-display', amountTone(row.netDeposits)]"
                    >
                      {{ dashZero(row.netDeposits) }}
                    </div>
                  </td>
                  <td>{{ dashZero(row.lots, 2, false) }}</td>
                  <td>{{ row.trades ? formatNumber(row.trades) : "–" }}</td>
                  <td>
                    <div
                      :class="['amount-display', amountTone(row.tradingResult)]"
                    >
                      {{ dashZero(row.tradingResult) }}
                    </div>
                  </td>
                  <td>
                    <div :class="['amount-display', amountTone(row.balance)]">
                      {{ formatCurrency(row.balance) }}
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
            <tfoot v-if="filteredAccounts.length">
              <tr class="total-row">
                <td>
                  <strong>{{ t("ibStatement_total", "TOTAL") }}</strong>
                  <div class="client-id">
                    {{
                      tParams("ibStatement_accountsCount", "{count} accounts", {
                        count: visibleAccountsTotal.accountCount,
                      })
                    }}
                  </div>
                </td>
                <td></td>
                <td></td>
                <td></td>
                <td>
                  <div class="amount-display">
                    {{ formatCurrency(visibleAccountsTotal.deposits) }}
                  </div>
                </td>
                <td>
                  <div class="amount-display">
                    {{ formatCurrency(visibleAccountsTotal.withdrawals) }}
                  </div>
                </td>
                <td>
                  <div
                    :class="[
                      'amount-display',
                      amountTone(visibleAccountsTotal.netDeposits),
                    ]"
                  >
                    {{ formatSignedCurrency(visibleAccountsTotal.netDeposits) }}
                  </div>
                </td>
                <td>
                  <strong>{{
                    formatNumber(visibleAccountsTotal.lots, 2)
                  }}</strong>
                </td>
                <td>
                  <strong>{{
                    formatNumber(visibleAccountsTotal.trades)
                  }}</strong>
                </td>
                <td>
                  <div
                    :class="[
                      'amount-display',
                      amountTone(visibleAccountsTotal.tradingResult),
                    ]"
                  >
                    {{
                      formatSignedCurrency(visibleAccountsTotal.tradingResult)
                    }}
                  </div>
                </td>
                <td>
                  <div class="amount-display">
                    {{ formatCurrency(visibleAccountsTotal.balance) }}
                  </div>
                </td>
              </tr>
            </tfoot>
          </table>
          <div v-if="accountPager.totalPages > 1" class="pagination">
            <div class="pagination-info">
              {{
                tParams(
                  "ibStatement_pagination_range_accounts",
                  "Showing {from}–{to} of {total} accounts",
                  {
                    from: formatNumber(accountPager.range.from),
                    to: formatNumber(accountPager.range.to),
                    total: formatNumber(accountPager.range.total),
                  },
                )
              }}
            </div>
            <div class="pagination-controls">
              <button
                type="button"
                class="pagination-btn"
                :disabled="accountPager.currentPage === 1"
                @click="accountPager.goTo(accountPager.currentPage - 1)"
              >
                <i class="fas fa-chevron-left"></i>
                {{ t("ibReport_pagination_previous", "Previous") }}
              </button>
              <template
                v-for="(page, index) in accountPageList"
                :key="`account-${page}-${index}`"
              >
                <button
                  v-if="page !== '...'"
                  type="button"
                  :class="[
                    'pagination-btn',
                    { active: accountPager.currentPage === page },
                  ]"
                  @click="accountPager.goTo(page)"
                >
                  {{ page }}
                </button>
                <span v-else class="pagination-ellipsis">...</span>
              </template>
              <button
                type="button"
                class="pagination-btn"
                :disabled="accountPager.currentPage === accountPager.totalPages"
                @click="accountPager.goTo(accountPager.currentPage + 1)"
              >
                {{ t("ibReport_pagination_next", "Next") }}
                <i class="fas fa-chevron-right"></i>
              </button>
            </div>
          </div>
        </div>

        <div class="transaction-table-container">
          <div class="table-header">
            <h2>
              <i class="fas fa-chart-pie"></i>
              {{
                t(
                  "ibStatement_section_instruments",
                  "Volume and Result by Instrument",
                )
              }}
            </h2>
          </div>
          <table class="transaction-table instruments-detail-table">
            <thead>
              <tr>
                <th>{{ t("ibStatement_th_instrument", "Instrument") }}</th>
                <th>
                  {{ t("ibStatement_th_closedLots", "Closed volume (lots)") }}
                </th>
                <th>{{ t("ibStatement_th_share", "Share of volume") }}</th>
                <th>{{ t("ibStatement_th_result", "Trading result") }}</th>
                <th>
                  {{ t("ibStatement_th_accountsTraded", "Accounts traded") }}
                </th>
              </tr>
            </thead>
            <tbody>
              <template v-if="!instrumentItems.length">
                <tr>
                  <td colspan="5" class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>
                      {{
                        t(
                          "ibStatement_noInstruments",
                          "No closed trades in this period.",
                        )
                      }}
                    </p>
                  </td>
                </tr>
              </template>
              <template v-else>
                <tr v-for="row in instrumentPagedRows" :key="row.instrument">
                  <td>
                    <span class="login-badge">{{ row.instrument }}</span>
                  </td>
                  <td>{{ formatNumber(row.lots, 2) }}</td>
                  <td>{{ formatNumber(row.shareOfVolume, 1) }}%</td>
                  <td>
                    <div
                      :class="['amount-display', amountTone(row.tradingResult)]"
                    >
                      {{ formatSignedCurrency(row.tradingResult) }}
                    </div>
                  </td>
                  <td>{{ formatNumber(row.accountsTraded) }}</td>
                </tr>
              </template>
            </tbody>
            <tfoot v-if="instrumentItems.length">
              <tr class="total-row">
                <td>
                  <strong>{{ t("ibStatement_total", "TOTAL") }}</strong>
                  <div class="client-id">
                    {{
                      tParams(
                        "ibStatement_instrumentsCount",
                        "{count} instruments",
                        { count: instrumentItems.length },
                      )
                    }}
                  </div>
                </td>
                <td>
                  <strong>{{ formatNumber(instrumentTotal.lots, 2) }}</strong>
                </td>
                <td>
                  <strong
                    >{{
                      formatNumber(instrumentTotal.shareOfVolume, 1)
                    }}%</strong
                  >
                </td>
                <td>
                  <div
                    :class="[
                      'amount-display',
                      amountTone(instrumentTotal.tradingResult),
                    ]"
                  >
                    {{ formatSignedCurrency(instrumentTotal.tradingResult) }}
                  </div>
                </td>
                <td>
                  <strong>{{
                    formatNumber(instrumentTotal.accountsTraded)
                  }}</strong>
                </td>
              </tr>
            </tfoot>
          </table>
          <div v-if="instrumentPager.totalPages > 1" class="pagination">
            <div class="pagination-info">
              {{
                tParams(
                  "ibStatement_pagination_range_instruments",
                  "Showing {from}–{to} of {total} instruments",
                  {
                    from: formatNumber(instrumentPager.range.from),
                    to: formatNumber(instrumentPager.range.to),
                    total: formatNumber(instrumentPager.range.total),
                  },
                )
              }}
            </div>
            <div class="pagination-controls">
              <button
                type="button"
                class="pagination-btn"
                :disabled="instrumentPager.currentPage === 1"
                @click="instrumentPager.goTo(instrumentPager.currentPage - 1)"
              >
                <i class="fas fa-chevron-left"></i>
                {{ t("ibReport_pagination_previous", "Previous") }}
              </button>
              <template
                v-for="(page, index) in instrumentPageList"
                :key="`instrument-${page}-${index}`"
              >
                <button
                  v-if="page !== '...'"
                  type="button"
                  :class="[
                    'pagination-btn',
                    { active: instrumentPager.currentPage === page },
                  ]"
                  @click="instrumentPager.goTo(page)"
                >
                  {{ page }}
                </button>
                <span v-else class="pagination-ellipsis">...</span>
              </template>
              <button
                type="button"
                class="pagination-btn"
                :disabled="
                  instrumentPager.currentPage === instrumentPager.totalPages
                "
                @click="instrumentPager.goTo(instrumentPager.currentPage + 1)"
              >
                {{ t("ibReport_pagination_next", "Next") }}
                <i class="fas fa-chevron-right"></i>
              </button>
            </div>
          </div>
        </div>

        <div
          class="transaction-table-container transaction-table-container--overflow"
        >
          <div class="table-header">
            <h2>
              <i class="fas fa-calendar-week"></i>
              {{ t("ibStatement_section_weeks", "Trading Activity by Week") }}
            </h2>
            <div v-if="allWeekItems.length" class="table-controls">
              <div
                class="filter-field week-filter-field"
                :class="{ 'week-filter-field--open': showWeekDropdown }"
              >
                <label>{{ t("ibStatement_label_weekFilter", "Week") }}</label>
                <div class="ib-select-search" @click.stop>
                  <div class="ib-select-search__input-wrap">
                    <i class="fas fa-search ib-select-search__icon"></i>
                    <input
                      ref="weekInputRef"
                      type="text"
                      class="ib-select-search__input"
                      :value="weekInputDisplay"
                      :placeholder="t('ibStatement_searchWeek', 'Search week')"
                      autocomplete="off"
                      @input="onWeekInput"
                      @focus="onWeekFocus"
                    />
                  </div>
                  <div
                    v-show="showWeekDropdown"
                    class="ib-select-search__dropdown"
                    @mousedown.prevent
                  >
                    <div
                      v-if="filteredWeekOptions.length === 0"
                      class="ib-select-search__empty"
                    >
                      {{
                        t(
                          "ibStatement_noWeekMatch",
                          "No week matched your search.",
                        )
                      }}
                    </div>
                    <div v-else class="ib-select-search__list">
                      <div
                        v-for="opt in filteredWeekOptions"
                        :key="opt.value === '' ? 'all' : opt.value"
                        class="ib-select-option"
                        :class="{
                          'ib-select-option--selected':
                            opt.value === weekFilter,
                        }"
                        @mousedown.prevent="selectWeek(opt)"
                      >
                        <div class="ib-select-option__info">
                          <span class="ib-select-option__name">{{
                            opt.label
                          }}</span>
                        </div>
                        <i
                          v-if="opt.value === weekFilter"
                          class="fas fa-check ib-select-option__check"
                        ></i>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <table class="transaction-table weeks-detail-table">
            <thead>
              <tr>
                <th>{{ t("ibStatement_th_week", "Week") }}</th>
                <th>{{ t("ibStatement_th_lots", "Lots") }}</th>
                <th>{{ t("ibStatement_th_positions", "Positions closed") }}</th>
                <th>
                  {{ t("ibStatement_th_accountsTrading", "Accounts trading") }}
                </th>
                <th>{{ t("ibStatement_th_result", "Trading result") }}</th>
              </tr>
            </thead>
            <tbody>
              <template v-if="!allWeekItems.length">
                <tr>
                  <td colspan="5" class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>
                      {{
                        t(
                          "ibStatement_noWeeks",
                          "No weekly trading activity in this period.",
                        )
                      }}
                    </p>
                  </td>
                </tr>
              </template>
              <template v-else>
                <tr v-for="row in weekPagedRows" :key="row.weekKey">
                  <td>
                    <strong>{{
                      tParams("ibStatement_weekN", "Week {n}", {
                        n: row.weekIndex,
                      })
                    }}</strong>
                    <div class="client-id">{{ row.week }}</div>
                  </td>
                  <td>{{ formatNumber(row.lots, 2) }}</td>
                  <td>{{ formatNumber(row.trades) }}</td>
                  <td>{{ formatNumber(row.accountsTrading) }}</td>
                  <td>
                    <div
                      :class="['amount-display', amountTone(row.tradingResult)]"
                    >
                      {{ formatSignedCurrency(row.tradingResult) }}
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
            <tfoot v-if="weekItems.length">
              <tr class="total-row">
                <td>
                  <strong>{{ t("ibStatement_total", "TOTAL") }}</strong>
                  <div class="client-id">
                    {{
                      tParams("ibStatement_weeksCount", "{count} weeks", {
                        count: weekItems.length,
                      })
                    }}
                  </div>
                </td>
                <td>
                  <strong>{{ formatNumber(weekTotal.lots, 2) }}</strong>
                </td>
                <td>
                  <strong>{{ formatNumber(weekTotal.trades) }}</strong>
                </td>
                <td>
                  <strong>{{ formatNumber(weekTotal.accountsTrading) }}</strong>
                </td>
                <td>
                  <div
                    :class="[
                      'amount-display',
                      amountTone(weekTotal.tradingResult),
                    ]"
                  >
                    {{ formatSignedCurrency(weekTotal.tradingResult) }}
                  </div>
                </td>
              </tr>
            </tfoot>
          </table>
          <div v-if="weekPager.totalPages > 1" class="pagination">
            <div class="pagination-info">
              {{
                tParams(
                  "ibStatement_pagination_range_weeks",
                  "Showing {from}–{to} of {total} weeks",
                  {
                    from: formatNumber(weekPager.range.from),
                    to: formatNumber(weekPager.range.to),
                    total: formatNumber(weekPager.range.total),
                  },
                )
              }}
            </div>
            <div class="pagination-controls">
              <button
                type="button"
                class="pagination-btn"
                :disabled="weekPager.currentPage === 1"
                @click="weekPager.goTo(weekPager.currentPage - 1)"
              >
                <i class="fas fa-chevron-left"></i>
                {{ t("ibReport_pagination_previous", "Previous") }}
              </button>
              <template
                v-for="(page, index) in weekPageList"
                :key="`week-${page}-${index}`"
              >
                <button
                  v-if="page !== '...'"
                  type="button"
                  :class="[
                    'pagination-btn',
                    { active: weekPager.currentPage === page },
                  ]"
                  @click="weekPager.goTo(page)"
                >
                  {{ page }}
                </button>
                <span v-else class="pagination-ellipsis">...</span>
              </template>
              <button
                type="button"
                class="pagination-btn"
                :disabled="weekPager.currentPage === weekPager.totalPages"
                @click="weekPager.goTo(weekPager.currentPage + 1)"
              >
                {{ t("ibReport_pagination_next", "Next") }}
                <i class="fas fa-chevron-right"></i>
              </button>
            </div>
          </div>
        </div>
      </template>
    </template>
  </div>
</template>

<script setup>
import {
  ref,
  computed,
  watch,
  reactive,
  nextTick,
  onMounted,
  onUnmounted,
} from "vue";
import { useAuthStore } from "@/stores/auth";
import { ElConfigProvider, ElDatePicker, ElTooltip } from "element-plus";
import zhCn from "element-plus/es/locale/lang/zh-cn";
import en from "element-plus/es/locale/lang/en";
import "element-plus/es/components/date-picker/style/css";
import "element-plus/es/components/config-provider/style/css";
import "element-plus/es/components/tooltip/style/css";
import { formatCurrency, formatNumber } from "@/utils/helpers";
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";
import ExportProgressBanner from "@/components/common/ExportProgressBanner.vue";
import ibStatementReportApi from "@/services/ibStatementReportApi";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { useAsyncReportExport } from "@/composables/useAsyncReportExport";

const { t, tParams, languageStore } = useAdminI18n();
const authStore = useAuthStore();

const exportFormat = ref("csv");

const {
  exportJobId,
  exportStatusText,
  exportBannerVisible,
  exportCancelling,
  exportModal,
  lastExportProgress,
  exportPolling,
  startOrResumeExport,
  resumeActiveExportIfAny,
  cancelActiveExport,
  onExportModalContinue,
  onExportModalCancel,
} = useAsyncReportExport({
  getActiveExport: () => ibStatementReportApi.getActiveExport(),
  enqueueExport: (params) => ibStatementReportApi.exportReport(params),
  getExportStatus: (jobId) => ibStatementReportApi.getExportStatus(jobId),
  cancelExport: (jobId) => ibStatementReportApi.cancelExport(jobId),
  downloadExport: (jobId) => ibStatementReportApi.downloadExport(jobId),
  buildFilename: () =>
    `ib_statement_${new Date().toISOString().split("T")[0]}.${exportFormat.value === "excel" ? "xls" : "csv"}`,
  t,
});

const hasReadonlyPermission = computed(
  () =>
    authStore.hasPermission("page_ibstatement_readonly") ||
    authStore.hasPermission("page_ibstatement"),
);
const hasExportPermission = computed(() =>
  authStore.hasPermission("page_ibstatement_export"),
);

const isExportRunning = computed(
  () =>
    exportPolling.value ||
    exportCancelling.value ||
    !!exportJobId.value ||
    !!exportModal.value.visible,
);

const exportProgressPercent = computed(() =>
  Math.max(0, Math.min(100, Number(lastExportProgress.value?.percent || 0))),
);

const elementPlusLocale = computed(() =>
  languageStore.currentLanguage === "zh" ? zhCn : en,
);

const partners = ref([]);
const selectedIbId = ref("");
const ibSearch = ref("");
const isIbSearching = ref(false);
const showIbDropdown = ref(false);
const activePreset = ref("month");
const startDate = ref("");
const endDate = ref("");
const loading = ref(false);
const error = ref("");
const statement = ref(null);
const accountSearch = ref("");
const showUnfunded = ref(false);
const weekFilter = ref("");
const weekSearch = ref("");
const isWeekSearching = ref(false);
const showWeekDropdown = ref(false);
const showExportDropdown = ref(false);
const ibInputRef = ref(null);
const weekInputRef = ref(null);

const partnerName = (ib) => {
  const name = String(ib?.name || "").trim();
  const code = String(ib?.ibCode || "").trim();
  if (name && code) return `${name} (${code})`;
  return name || code || `IB #${ib?.id || ""}`;
};

const partnerInitials = (ib) => {
  const name = String(ib?.name || ib?.ibCode || "").trim();
  const parts = name.split(/\s+/).filter(Boolean).slice(0, 2);
  if (parts.length === 0) return "IB";
  return parts.map((part) => part.charAt(0).toUpperCase()).join("");
};

const clientTypeClass = (type) =>
  String(type || "Direct Client")
    .toLowerCase()
    .replace(/\s+/g, "-");

const clientTypeLabel = (type) => {
  if (type === "IB") return t("ibStatement_type_ib", "IB");
  if (type === "Sub-IB") return t("ibStatement_type_subIb", "Sub-IB");
  return t("ibStatement_type_directClient", "Direct Client");
};

const selectedPartner = computed(
  () =>
    partners.value.find((ib) => String(ib.id) === String(selectedIbId.value)) ||
    null,
);

const filteredPartners = computed(() => {
  const q = ibSearch.value.trim().toLowerCase();
  if (!q) return partners.value;
  return partners.value.filter((ib) => {
    const name = String(ib.name || "").toLowerCase();
    const code = String(ib.ibCode || "").toLowerCase();
    return name.includes(q) || code.includes(q);
  });
});

const ibInputDisplay = computed(() => {
  if (isIbSearching.value) return ibSearch.value;
  return selectedPartner.value
    ? partnerName(selectedPartner.value)
    : ibSearch.value;
});

const ibSelectPlaceholder = computed(() =>
  t("ibStatement_searchIb", "Search IB name or code"),
);

const closeIbSearch = () => {
  showIbDropdown.value = false;
  isIbSearching.value = false;
  ibSearch.value = "";
  nextTick(() => ibInputRef.value?.blur());
};

const onIbFocus = () => {
  isIbSearching.value = true;
  ibSearch.value = "";
  showIbDropdown.value = true;
};

const selectPartner = (ib) => {
  const nextId = String(ib.id);
  const alreadySelected = nextId === String(selectedIbId.value);
  selectedIbId.value = nextId;
  closeIbSearch();
  if (!alreadySelected || !statement.value) {
    loadStatement();
  }
};

const onIbInput = (event) => {
  isIbSearching.value = true;
  ibSearch.value = event.target.value;
  showIbDropdown.value = true;
};

const headline = computed(
  () =>
    statement.value?.headline || {
      clientCount: 0,
      accountCount: 0,
      fundedCount: 0,
      tradedCount: 0,
      totalDeposits: 0,
      totalWithdrawals: 0,
      closingBalance: 0,
    },
);

const movementRows = computed(() => {
  const m = statement.value?.movement;
  if (!m) return [];
  return [
    {
      key: "opening",
      label: t("ibStatement_mv_opening", "Opening balance"),
      value: m.openingBalance,
    },
    {
      key: "deposits",
      label: t("ibStatement_mv_deposits", "Client deposits"),
      value: m.deposits,
    },
    {
      key: "withdrawals",
      label: t("ibStatement_mv_withdrawals", "Client withdrawals"),
      value: -Math.abs(m.withdrawals || 0),
    },
    {
      key: "net",
      label: t("ibStatement_mv_net", "Net client deposits"),
      value: m.netDeposits,
    },
    {
      key: "result",
      label: t("ibStatement_mv_result", "Trading result on closed positions"),
      value: m.tradingResult,
    },
    {
      key: "fees",
      label: t("ibStatement_mv_fees", "of which commission and fees"),
      value: m.commissionFees,
    },
    {
      key: "swap",
      label: t("ibStatement_mv_swap", "of which swap"),
      value: m.swap,
    },
    {
      key: "closing",
      label: t("ibStatement_mv_closing", "Closing balance"),
      value: m.closingBalance,
    },
    {
      key: "unrealised",
      label: t(
        "ibStatement_mv_unrealised",
        "Unrealised result on open positions",
      ),
      value: m.unrealised,
    },
    {
      key: "equity",
      label: t("ibStatement_mv_equity", "Closing equity"),
      value: m.closingEquity,
    },
  ];
});

const filteredAccounts = computed(() => {
  const rows = Array.isArray(statement.value?.accounts)
    ? statement.value.accounts
    : [];
  const visible = showUnfunded.value
    ? rows
    : rows.filter((row) => !row.unfunded);
  const q = accountSearch.value.trim().toLowerCase();
  if (!q) return visible;
  return visible.filter((row) => {
    const login = String(row.login || "").toLowerCase();
    const name = String(row.clientName || "").toLowerCase();
    const type = String(row.clientType || "").toLowerCase();
    const clientId = String(row.clientId || "").toLowerCase();
    const affiliation = String(row.affiliationCode || "").toLowerCase();
    const ibName = String(row.ibName || "").toLowerCase();
    const ibCode = String(row.ibCode || "").toLowerCase();
    const subIbName = String(row.subIbName || "").toLowerCase();
    const subIbCode = String(row.subIbCode || "").toLowerCase();
    return (
      login.includes(q) ||
      name.includes(q) ||
      type.includes(q) ||
      clientId.includes(q) ||
      `#${clientId}`.includes(q) ||
      affiliation.includes(q) ||
      ibName.includes(q) ||
      ibCode.includes(q) ||
      subIbName.includes(q) ||
      subIbCode.includes(q)
    );
  });
});

const visibleAccountsTotal = computed(() =>
  filteredAccounts.value.reduce(
    (acc, row) => ({
      accountCount: acc.accountCount + 1,
      deposits: acc.deposits + (Number(row.deposits) || 0),
      withdrawals: acc.withdrawals + (Number(row.withdrawals) || 0),
      netDeposits: acc.netDeposits + (Number(row.netDeposits) || 0),
      lots: acc.lots + (Number(row.lots) || 0),
      trades: acc.trades + (Number(row.trades) || 0),
      tradingResult: acc.tradingResult + (Number(row.tradingResult) || 0),
      balance: acc.balance + (Number(row.balance) || 0),
    }),
    {
      accountCount: 0,
      deposits: 0,
      withdrawals: 0,
      netDeposits: 0,
      lots: 0,
      trades: 0,
      tradingResult: 0,
      balance: 0,
    },
  ),
);

const instrumentItems = computed(() =>
  Array.isArray(statement.value?.instruments?.items)
    ? statement.value.instruments.items
    : [],
);
const instrumentTotal = computed(
  () => statement.value?.instruments?.total || {},
);
const allWeekItems = computed(() =>
  Array.isArray(statement.value?.weeks?.items)
    ? statement.value.weeks.items
    : [],
);

const weekFilterOptions = computed(() =>
  allWeekItems.value.map((row, index) => ({
    value: String(index),
    label: `${tParams("ibStatement_weekN", "Week {n}", { n: index + 1 })} · ${row.week}`,
  })),
);

const weekSelectOptions = computed(() => [
  { value: "", label: t("ibStatement_allWeeks", "All weeks") },
  ...weekFilterOptions.value,
]);

const filteredWeekOptions = computed(() => {
  const q = weekSearch.value.trim().toLowerCase();
  if (!q) return weekSelectOptions.value;
  return weekSelectOptions.value.filter((opt) =>
    opt.label.toLowerCase().includes(q),
  );
});

const weekInputDisplay = computed(() => {
  if (isWeekSearching.value) return weekSearch.value;
  const selected = weekSelectOptions.value.find(
    (opt) => opt.value === weekFilter.value,
  );
  return selected ? selected.label : weekSearch.value;
});

const closeWeekSearch = () => {
  showWeekDropdown.value = false;
  isWeekSearching.value = false;
  weekSearch.value = "";
  nextTick(() => weekInputRef.value?.blur());
};

const onWeekFocus = () => {
  isWeekSearching.value = true;
  weekSearch.value = "";
  showWeekDropdown.value = true;
};

const onWeekInput = (event) => {
  isWeekSearching.value = true;
  weekSearch.value = event.target.value;
  showWeekDropdown.value = true;
};

const selectWeek = (opt) => {
  weekFilter.value = opt.value;
  closeWeekSearch();
};

const weekItems = computed(() => {
  const numbered = allWeekItems.value.map((row, index) => ({
    ...row,
    weekIndex: index + 1,
    weekKey: `${index}-${row.week}`,
  }));
  if (weekFilter.value === "") return numbered;
  const index = Number(weekFilter.value);
  if (!Number.isInteger(index) || index < 0 || index >= numbered.length)
    return numbered;
  return [numbered[index]];
});

const weekTotal = computed(() => {
  if (weekFilter.value === "") {
    return statement.value?.weeks?.total || {};
  }
  const [row] = weekItems.value;
  if (!row) return {};
  return {
    lots: row.lots,
    trades: row.trades,
    accountsTrading: row.accountsTrading,
    tradingResult: row.tradingResult,
  };
});

const TABLE_PAGE_SIZE = 10;

const buildVisiblePages = (total, current) => {
  const pages = [];
  if (total <= 7) {
    for (let i = 1; i <= total; i++) pages.push(i);
    return pages;
  }
  if (current <= 3) {
    for (let i = 1; i <= 4; i++) pages.push(i);
    pages.push("...");
    pages.push(total);
    return pages;
  }
  if (current >= total - 2) {
    pages.push(1);
    pages.push("...");
    for (let i = total - 3; i <= total; i++) pages.push(i);
    return pages;
  }
  pages.push(1);
  pages.push("...");
  pages.push(current - 1);
  pages.push(current);
  pages.push(current + 1);
  pages.push("...");
  pages.push(total);
  return pages;
};

const createTablePager = (rowsSource) => {
  const page = ref(1);
  const sourceRows = computed(() =>
    Array.isArray(rowsSource.value) ? rowsSource.value : [],
  );
  const total = computed(() => sourceRows.value.length);
  const totalPages = computed(() =>
    Math.max(1, Math.ceil(total.value / TABLE_PAGE_SIZE)),
  );
  const currentPage = computed(() =>
    Math.min(Math.max(1, page.value), totalPages.value),
  );
  const rows = computed(() => {
    const start = (currentPage.value - 1) * TABLE_PAGE_SIZE;
    return sourceRows.value
      .slice(start, start + TABLE_PAGE_SIZE)
      .filter((row) => row != null);
  });
  const visiblePages = computed(() =>
    buildVisiblePages(totalPages.value, currentPage.value),
  );
  const range = computed(() => {
    if (total.value === 0) return { from: 0, to: 0, total: 0 };
    return {
      from: (currentPage.value - 1) * TABLE_PAGE_SIZE + 1,
      to: Math.min(currentPage.value * TABLE_PAGE_SIZE, total.value),
      total: total.value,
    };
  });
  const goTo = (next) => {
    const n = Number(next);
    if (!Number.isFinite(n) || n < 1 || n > totalPages.value) return;
    page.value = n;
  };
  const reset = () => {
    page.value = 1;
  };
  return reactive({
    currentPage,
    total,
    totalPages,
    rows,
    visiblePages,
    range,
    goTo,
    reset,
  });
};

const movementPager = createTablePager(movementRows);
const accountPager = createTablePager(filteredAccounts);
const instrumentPager = createTablePager(instrumentItems);
const weekPager = createTablePager(weekItems);

const pagerRows = (pager) =>
  computed(() => {
    const rows = pager.rows;
    return Array.isArray(rows) ? rows.filter((row) => row != null) : [];
  });

const pagerPageList = (pager) =>
  computed(() => {
    const pages = pager.visiblePages;
    return Array.isArray(pages) ? pages : [];
  });

const movementPagedRows = pagerRows(movementPager);
const accountPagedRows = pagerRows(accountPager);
const instrumentPagedRows = pagerRows(instrumentPager);
const weekPagedRows = pagerRows(weekPager);
const movementPageList = pagerPageList(movementPager);
const accountPageList = pagerPageList(accountPager);
const instrumentPageList = pagerPageList(instrumentPager);
const weekPageList = pagerPageList(weekPager);

watch([accountSearch, showUnfunded], () => {
  accountPager.reset();
});

watch(weekFilter, () => {
  weekPager.reset();
});

const formatDateForInput = (date) => {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
};

const selectPreset = (preset) => {
  activePreset.value = preset;
  const today = new Date();
  let start;
  let end = new Date();
  if (preset === "today") {
    start = new Date();
    end = new Date();
  } else if (preset === "month") {
    start = new Date(today.getFullYear(), today.getMonth(), 1);
  } else if (preset === "quarter") {
    const quarter = Math.floor(today.getMonth() / 3);
    start = new Date(today.getFullYear(), quarter * 3, 1);
  } else {
    start = new Date(today.getFullYear(), 0, 1);
  }
  startDate.value = formatDateForInput(start);
  endDate.value = formatDateForInput(end);
};

const amountTone = (value) => {
  const n = Number(value) || 0;
  if (n > 0) return "positive";
  if (n < 0) return "negative";
  return "neutral";
};

const clientInitials = (name) => {
  const parts = String(name || "")
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2);
  if (parts.length === 0) return "—";
  return parts.map((part) => part.charAt(0).toUpperCase()).join("");
};

const formatSignedCurrency = (value) => {
  const n = Number(value) || 0;
  if (n < 0) return `-${formatCurrency(Math.abs(n))}`;
  return formatCurrency(n);
};

const dashZero = (value, decimals = 2, asCurrency = true) => {
  const n = Number(value) || 0;
  if (n === 0) return "–";
  if (asCurrency) return formatSignedCurrency(n);
  return formatNumber(n, decimals);
};

const unwrap = (response) => {
  const body =
    response?.data && typeof response.data === "object"
      ? response.data
      : response;
  if (
    body &&
    typeof body === "object" &&
    (body.headline || Array.isArray(body.items) || body.partner)
  ) {
    return body;
  }
  return response?.data?.data ?? response?.data ?? response;
};

let statementRequestId = 0;
let statementInFlight = 0;

const resetStatementPagers = () => {
  weekFilter.value = "";
  movementPager.reset();
  accountPager.reset();
  instrumentPager.reset();
  weekPager.reset();
};

const loadPartners = async () => {
  try {
    const response = await ibStatementReportApi.getPartners();
    partners.value = unwrap(response)?.items || [];
  } catch (err) {
    partners.value = [];
    error.value =
      err?.message ||
      t("ibStatement_err_partners", "Failed to load IB partners.");
  }
};

const loadStatement = async () => {
  if (!selectedIbId.value) {
    error.value = t(
      "ibStatement_err_selectIb",
      "Select an introducing broker.",
    );
    return;
  }
  if (!startDate.value || !endDate.value) {
    error.value = t("ibStatement_err_dates", "Select a date range.");
    return;
  }
  if (new Date(startDate.value) > new Date(endDate.value)) {
    error.value = t(
      "ibStatement_err_dateOrder",
      "From date must be on or before To date.",
    );
    return;
  }
  const requestId = ++statementRequestId;
  statementInFlight += 1;
  loading.value = true;
  error.value = "";
  try {
    const response = await ibStatementReportApi.getStatement({
      ibPartnerId: selectedIbId.value,
      startDate: startDate.value,
      endDate: endDate.value,
    });
    if (requestId !== statementRequestId) return;
    const payload = unwrap(response);
    resetStatementPagers();
    statement.value = payload && typeof payload === "object" ? payload : null;
  } catch (err) {
    if (requestId !== statementRequestId) return;
    statement.value = null;
    error.value =
      err?.message || t("ibStatement_err_load", "Failed to load IB statement.");
  } finally {
    statementInFlight = Math.max(0, statementInFlight - 1);
    if (statementInFlight === 0) {
      loading.value = false;
    }
  }
};

const toggleExportDropdown = () => {
  if (isExportRunning.value || !statement.value) return;
  showExportDropdown.value = !showExportDropdown.value;
};

const handleExport = async (format) => {
  showExportDropdown.value = false;
  if (!hasExportPermission.value || isExportRunning.value) return;
  if (format !== "csv" && format !== "excel") return;
  if (
    !selectedIbId.value ||
    !startDate.value ||
    !endDate.value ||
    !statement.value
  ) {
    error.value = t(
      "ibStatement_err_exportNeedFilter",
      "Apply the filter before exporting.",
    );
    return;
  }
  exportFormat.value = format;
  await startOrResumeExport(() => ({
    ibPartnerId: Number(selectedIbId.value),
    startDate: startDate.value,
    endDate: endDate.value,
    format,
  }));
};

const onDocumentClick = () => {
  showExportDropdown.value = false;
  closeIbSearch();
  closeWeekSearch();
};

onMounted(async () => {
  selectPreset("month");
  document.addEventListener("click", onDocumentClick);
  if (!hasReadonlyPermission.value) return;
  await loadPartners();
  await resumeActiveExportIfAny();
});

onUnmounted(() => {
  document.removeEventListener("click", onDocumentClick);
});
</script>

<style scoped>
.ib-statement-page {
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

.page-title p {
  font-size: 14px;
  color: var(--color-muted);
}

.page-actions {
  display: flex;
  align-items: center;
  gap: 20px;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20px;
  margin-bottom: 30px;
}

.stat-card {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: 25px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  border-left: 4px solid transparent;
}

.stat-card.total {
  border-left-color: var(--color-brand);
}
.stat-card.paid {
  border-left-color: var(--color-success);
}
.stat-card.total-lots {
  border-left-color: var(--color-warning);
}
.stat-card.total-trade {
  border-left-color: var(--color-accent);
}

.stat-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 15px;
}

.stat-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-muted);
  text-transform: uppercase;
}

.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: var(--radius-lg);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
}

.stat-card.total .stat-icon {
  background: var(--color-brand-soft);
  color: var(--color-brand);
}
.stat-card.paid .stat-icon {
  background: var(--color-success-soft);
  color: var(--color-success);
}
.stat-card.total-lots .stat-icon {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}
.stat-card.total-trade .stat-icon {
  background: var(--color-brand-soft);
  color: var(--color-accent);
}

.stat-value {
  font-size: 26px;
  font-weight: 700;
  color: var(--color-ink);
}

.stat-footer {
  margin-top: 10px;
  font-size: 12px;
  color: var(--color-faint);
}

.date-filter-section {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: 25px 30px;
  margin-bottom: 30px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  overflow: visible;
}

.date-filter-container {
  display: flex;
  align-items: center;
  gap: 15px;
  flex-wrap: wrap;
}

.date-filter-label {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
  white-space: nowrap;
}

.date-filter-presets {
  display: flex;
  gap: 10px;
  flex-wrap: nowrap;
}

.preset-btn {
  padding: 8px 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  color: var(--color-text);
  white-space: nowrap;
}

.preset-btn:hover {
  border-color: var(--color-border-strong);
  background: var(--color-surface-soft);
}

.preset-btn.active {
  background: var(--color-brand-solid);
  color: white;
  border-color: var(--color-brand);
}

.date-input-wrapper {
  display: flex;
  flex-direction: column;
  gap: 5px;
  min-width: 140px;
}

.search-filter-section {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: 25px 30px;
  margin-bottom: 30px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.search-container {
  display: flex;
  gap: 15px;
  align-items: flex-end;
  flex-wrap: wrap;
}

.search-field {
  flex: 1;
  min-width: 250px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.search-field label {
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text);
}

.ib-select-field {
  position: relative;
}

.ib-select-field--open {
  z-index: 20;
}

.ib-select-search {
  position: relative;
  width: 100%;
}

.ib-select-search__input-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.ib-select-search__icon {
  position: absolute;
  left: 14px;
  color: var(--color-faint);
  font-size: 14px;
  pointer-events: none;
}

.ib-select-search__input {
  width: 100%;
  padding: 10px 12px 10px 40px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  color: var(--color-ink);
  outline: none;
  background: var(--color-surface);
}

.ib-select-search__input:focus {
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.ib-select-search__input::placeholder {
  color: var(--color-faint);
}

.ib-select-search__dropdown {
  position: absolute;
  left: 0;
  right: 0;
  top: 100%;
  margin-top: 4px;
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
  z-index: 1100;
  max-height: 280px;
  overflow: hidden;
}

.ib-select-search__empty {
  padding: 20px;
  text-align: center;
  font-size: 13px;
  color: var(--color-muted);
}

.ib-select-search__list {
  overflow-y: auto;
  max-height: 260px;
}

.ib-select-option {
  padding: 12px 14px;
  display: flex;
  align-items: center;
  gap: 12px;
  cursor: pointer;
  border-bottom: 1px solid #f1f1f1;
}

.ib-select-option:last-child {
  border-bottom: none;
}

.ib-select-option:hover {
  background: var(--color-surface-soft);
}

.ib-select-option--selected {
  background: var(--color-brand-soft);
}

.ib-select-option__avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: var(--color-brand-solid);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 700;
  flex-shrink: 0;
}

.ib-select-option__info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.ib-select-option__name {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ib-select-option__code {
  font-size: 12px;
  color: var(--color-muted);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ib-select-option__check {
  color: var(--color-brand);
  font-size: 14px;
  flex-shrink: 0;
}

.date-input-wrapper label {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-muted);
  white-space: nowrap;
}

.date-input-wrapper .filter-date {
  width: 100%;
}

.date-input-wrapper :deep(.el-input__wrapper) {
  border-radius: var(--radius-md);
  box-shadow: 0 0 0 1px var(--color-border) inset;
}

.date-input-wrapper :deep(.el-input__wrapper.is-focus) {
  box-shadow:
    0 0 0 1px var(--color-brand) inset,
    0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.btn-apply-filter,
.btn-export {
  padding: 10px 20px;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  white-space: nowrap;
}

.btn-apply-filter {
  background: var(--color-brand-solid);
  color: white;
  border: none;
}

.btn-apply-filter:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.4);
}

.btn-apply-filter:disabled,
.btn-export:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-export {
  background: var(--color-surface);
  color: var(--color-brand);
  border: 2px solid var(--color-brand);
  position: relative;
}

.export-wrap {
  position: relative;
  z-index: 30;
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
  font-size: 13px;
  font-weight: 600;
  border-bottom: 1px solid var(--color-border);
}

.export-option:last-child {
  border-bottom: none;
}

.export-option.csv:hover {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.export-option.excel:hover {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.transaction-table-container {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  margin-bottom: 30px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

.transaction-table-container--overflow {
  overflow: visible;
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
  gap: 15px;
  align-items: center;
}

.filter-field {
  min-width: 220px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.filter-field label {
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text);
}

.filter-field select {
  padding: 10px 15px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  outline: none;
  transition: all 0.3s ease;
  background: var(--color-surface);
  cursor: pointer;
  color: var(--color-ink);
}

.filter-field select:focus {
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.week-filter-field {
  min-width: 280px;
  position: relative;
  z-index: 5;
}

.week-filter-field--open {
  z-index: 40;
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

.transaction-table tbody tr {
  border-bottom: 1px solid var(--color-border);
  transition: all 0.2s ease;
}

.transaction-table tbody tr:hover {
  background: var(--color-surface-soft);
}

.transaction-table td {
  padding: 16px 20px;
  font-size: 14px;
  color: var(--color-text);
}

.transaction-table tfoot td {
  padding: 16px 20px;
  font-size: 14px;
  color: var(--color-ink);
  background: var(--color-surface-soft);
  border-top: 2px solid var(--color-border);
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
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
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

.client-info {
  display: flex;
  align-items: flex-start;
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

.client-details {
  display: flex;
  flex-direction: column;
}

.client-name {
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 3px;
}

.type-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
  background: var(--color-surface-soft);
  color: var(--color-text);
}

.type-badge.ib {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.type-badge.sub-ib {
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.type-badge.direct-client {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.client-id {
  font-size: 12px;
  color: var(--color-muted);
}

.unfunded-label {
  font-size: 12px;
  color: var(--color-muted);
  cursor: help;
  border-bottom: 1px dotted currentColor;
}

.login-badge {
  display: inline-flex;
  align-items: center;
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  font-family: "Courier New", monospace;
  background: var(--color-info-soft);
  color: var(--color-info);
}

.amount-display {
  font-weight: 700;
  font-size: 16px;
}

.accounts-detail-table tbody .amount-display,
.instruments-detail-table tbody .amount-display,
.weeks-detail-table tbody .amount-display {
  font-weight: 400;
}

.instruments-detail-table tbody .login-badge {
  font-weight: 700;
}

.accounts-detail-table tbody .type-badge,
.accounts-detail-table tbody .login-badge {
  font-weight: 700;
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

.transaction-table tr.unfunded .client-name,
.transaction-table tr.unfunded td {
  color: var(--color-faint);
}

.transaction-table tr.unfunded .client-avatar {
  background: var(--color-border-strong);
}

.transaction-table tr.unfunded .login-badge {
  background: var(--color-surface-muted);
  color: var(--color-muted);
}

.transaction-table tr.unfunded .type-badge {
  background: var(--color-surface-muted);
  color: var(--color-muted);
}

.transaction-table tr.unfunded .amount-display {
  color: var(--color-faint);
}

.transaction-table td.empty-state {
  text-align: center;
  padding: 60px 20px !important;
}

.transaction-table td.empty-state i {
  font-size: 48px;
  margin-bottom: 15px;
  display: block;
  color: var(--color-border-strong);
}

.transaction-table td.empty-state p {
  font-size: 16px;
  color: var(--color-muted);
  margin: 0;
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
  transition: all 0.3s ease;
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

.loading-container,
div.empty-state {
  text-align: center;
  padding: 60px 20px;
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  color: var(--color-muted);
}

div.empty-state i,
.loading-container i {
  font-size: 40px;
  color: var(--color-brand);
  margin-bottom: 16px;
  display: block;
}

.error-message {
  background: var(--color-danger-soft);
  color: var(--color-danger);
  padding: 15px 20px;
  border-radius: var(--radius-md);
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 10px;
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
}

.export-modal-percent {
  margin: 10px 0 0;
  font-size: 13px;
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
  font-size: 13px;
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

@media (max-width: 1024px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .ib-statement-page {
    padding: 20px 15px;
  }

  .stats-grid {
    grid-template-columns: 1fr;
  }

  .page-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 20px;
  }

  .table-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .table-controls {
    width: 100%;
  }

  .search-box input {
    width: 100%;
  }

  .transaction-table {
    font-size: 12px;
  }

  .transaction-table th,
  .transaction-table td {
    padding: 12px 10px;
  }

  .pagination {
    flex-direction: column;
    gap: 15px;
  }

  .pagination-controls {
    flex-wrap: wrap;
    justify-content: center;
  }
}
</style>
