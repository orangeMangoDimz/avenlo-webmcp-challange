<template>
  <div class="sales-list-container">
    <div class="sales-list-page-header">
      <div class="sales-list-page-title">
        <h1 class="sales-list-page-title__heading">
          {{ t("page_salesList_title") }}
        </h1>
        <p class="sales-list-page-title__desc">{{ t("page_salesList_sub") }}</p>
      </div>
      <div class="sales-list-page-actions">
        <PageHeaderActions />
      </div>
    </div>

    <div v-if="loading" class="sales-list-loading">
      <i class="fas fa-spinner fa-spin sales-list-loading__icon"></i>
      <p class="sales-list-loading__text">{{ t("salesList_loading") }}</p>
    </div>

    <div v-else class="sales-list-table-wrap">
      <div class="sales-list-stats-header">
        <div>
          <h2 class="sales-list-stats-title">
            {{ t("salesList_stats_title") }}
          </h2>
          <p class="sales-list-stats-desc">{{ t("salesList_stats_desc") }}</p>
        </div>
        <div class="sales-list-page-stats">
          <div class="sales-list-stat-badge">
            <i class="fas fa-user-tie"></i>
            <span>{{
              tParams("salesList_stat_totalSales", "{count} Total Sales", {
                count: listStats.totalSales,
              })
            }}</span>
          </div>
          <div class="sales-list-stat-badge sales-list-stat-badge--active">
            <i class="fas fa-check-circle"></i>
            <span>{{
              tParams("salesList_stat_active", "{count} Active", {
                count: listStats.activeSales,
              })
            }}</span>
          </div>
          <div class="sales-list-stat-badge sales-list-stat-badge--inactive">
            <i class="fas fa-times-circle"></i>
            <span>{{
              tParams("salesList_stat_inactive", "{count} Inactive", {
                count: listStats.inactiveSales,
              })
            }}</span>
          </div>
        </div>
      </div>
      <div class="sales-list-toolbar">
        <div class="sales-list-toolbar__left">
          <h2 class="sales-list-toolbar__title">
            {{ t("salesList_toolbar_title") }}
          </h2>
        </div>
        <div class="sales-list-toolbar__right">
          <div class="sales-list-search-wrap">
            <input
              v-model="searchKeyword"
              type="text"
              class="sales-list-search"
              :placeholder="t('salesList_search_placeholder')"
              @keyup.enter="onSearch"
            />
            <button
              type="button"
              class="sales-list-btn sales-list-btn--search"
              @click="onSearch"
            >
              <i class="fas fa-search"></i> {{ t("common_search") }}
            </button>
          </div>
          <div class="sales-list-rows">
            <label class="sales-list-rows__label">{{
              t("salesList_showRows")
            }}</label>
            <select
              v-model="perPage"
              class="sales-list-rows__select"
              @change="onPageSizeChange"
            >
              <option :value="5">{{ t("salesList_rows_5") }}</option>
              <option :value="10">{{ t("salesList_rows_10") }}</option>
              <option :value="20">{{ t("salesList_rows_20") }}</option>
              <option value="all">{{ t("salesList_rows_all") }}</option>
            </select>
          </div>
        </div>
      </div>

      <div class="sales-list-table-scroll">
        <table class="sales-list-table">
          <thead class="sales-list-table__head">
            <tr>
              <th class="sales-list-table__th">
                {{ t("salesList_th_salesName") }}
              </th>
              <th class="sales-list-table__th">
                {{ t("salesList_th_totalIbs") }}
              </th>
              <th class="sales-list-table__th">
                {{ t("salesList_th_totalClients") }}
              </th>
              <th class="sales-list-table__th">
                {{ t("salesList_th_joinDate") }}
              </th>
              <th class="sales-list-table__th">
                {{ t("salesList_th_status") }}
              </th>
              <th class="sales-list-table__th">
                {{ t("salesList_th_action") }}
              </th>
            </tr>
          </thead>
          <tbody>
            <template v-for="sales in visibleList" :key="sales.id">
              <tr
                :class="[
                  'sales-list-table__row',
                  { expanded: expandedSalesId === sales.id },
                ]"
              >
                <td class="sales-list-table__cell sales-list-table__cell--name">
                  <div class="sales-list-sales-info">
                    <div class="sales-list-sales-name">
                      {{ sales.salesName }}
                    </div>
                    <div class="sales-list-sales-code">
                      {{ sales.salesCode }}
                    </div>
                  </div>
                </td>
                <td class="sales-list-table__cell">{{ sales.totalIbs }}</td>
                <td class="sales-list-table__cell">{{ sales.totalClients }}</td>
                <td class="sales-list-table__cell">
                  {{ formatDate(sales.joinDate) }}
                </td>
                <td class="sales-list-table__cell">
                  <span
                    class="sales-list-status"
                    :class="statusClass(sales.status)"
                    >{{ salesStatusText(sales) }}</span
                  >
                </td>
                <td class="sales-list-table__cell">
                  <button
                    type="button"
                    class="sales-list-btn-action sales-list-btn-action--detail"
                    @click="toggleRowExpansion(sales.id)"
                  >
                    <i
                      :class="[
                        'fas',
                        expandedSalesId === sales.id
                          ? 'fa-chevron-up'
                          : 'fa-chevron-down',
                      ]"
                    ></i>
                    {{
                      expandedSalesId === sales.id
                        ? t("salesList_btn_hide")
                        : t("salesList_btn_detail")
                    }}
                  </button>
                </td>
              </tr>
              <tr
                class="sales-detail-row"
                :class="{ show: expandedSalesId === sales.id }"
              >
                <td colspan="6">
                  <div
                    class="sales-detail-content"
                    v-if="expandedSalesId === sales.id"
                  >
                    <!-- 基本信息 (只读) -->
                    <div class="sales-detail-sections">
                      <div
                        class="sales-detail-section sales-detail-section--full"
                      >
                        <div class="sales-detail-card">
                          <div class="sales-detail-card-header">
                            <div class="sales-detail-card-icon">
                              <i class="fas fa-user-tie"></i>
                            </div>
                            <h3 class="sales-detail-card-title">
                              {{ t("salesList_section_basicInfo") }}
                            </h3>
                          </div>
                          <div class="sales-detail-fields">
                            <div class="sales-detail-field">
                              <span class="sales-detail-label">{{
                                t("salesList_label_fullName")
                              }}</span>
                              <span class="sales-detail-value">{{
                                sales.fullName
                              }}</span>
                            </div>
                            <div class="sales-detail-field">
                              <span class="sales-detail-label">{{
                                t("salesList_label_salesCode")
                              }}</span>
                              <span class="sales-detail-value">{{
                                sales.salesCode
                              }}</span>
                            </div>
                            <div class="sales-detail-field">
                              <span class="sales-detail-label">{{
                                t("salesList_label_email")
                              }}</span>
                              <span class="sales-detail-value">{{
                                sales.email
                              }}</span>
                            </div>
                            <div class="sales-detail-field">
                              <span class="sales-detail-label">{{
                                t("salesList_label_joinDate")
                              }}</span>
                              <span class="sales-detail-value">{{
                                formatDate(sales.joinDate)
                              }}</span>
                            </div>
                            <div class="sales-detail-field">
                              <span class="sales-detail-label">{{
                                t("salesList_label_department")
                              }}</span>
                              <span class="sales-detail-value">{{
                                sales.departmentName ?? "—"
                              }}</span>
                            </div>
                            <div class="sales-detail-field">
                              <span class="sales-detail-label">{{
                                t("salesList_label_position")
                              }}</span>
                              <span class="sales-detail-value">{{
                                sales.positionName ?? "—"
                              }}</span>
                            </div>
                            <div
                              class="sales-detail-field sales-detail-field--full"
                            >
                              <span class="sales-detail-label">{{
                                t("salesList_label_referralUrl")
                              }}</span>
                              <span
                                class="sales-detail-value sales-detail-value--url-wrap"
                              >
                                <template
                                  v-if="editingReferralSalesId === sales.id"
                                >
                                  <span class="sales-referral-prefix">{{
                                    getReferralUrlPrefix(sales)
                                  }}</span>
                                  <input
                                    v-model="editingReferralSuffix"
                                    type="text"
                                    class="sales-referral-suffix-input"
                                    :placeholder="
                                      t('salesList_placeholder_suffix')
                                    "
                                    @keydown.enter="saveReferralSuffix(sales)"
                                    @keydown.esc="cancelEditReferral"
                                  />
                                  <span class="sales-referral-edit-actions">
                                    <button
                                      type="button"
                                      class="sales-referral-btn sales-referral-btn--copy"
                                      @click="saveReferralSuffix(sales)"
                                    >
                                      {{ t("common_save") }}
                                    </button>
                                    <button
                                      type="button"
                                      class="sales-referral-btn sales-referral-btn--edit"
                                      @click="cancelEditReferral"
                                    >
                                      {{ t("common_cancel") }}
                                    </button>
                                  </span>
                                  <span
                                    v-if="referralSaveError"
                                    class="sales-referral-error"
                                    >{{ referralSaveError }}</span
                                  >
                                </template>
                                <template v-else>
                                  <span class="sales-detail-value--url">{{
                                    sales.personalReferralUrl
                                  }}</span>
                                  <button
                                    type="button"
                                    class="sales-referral-btn sales-referral-btn--edit"
                                    :title="t('salesList_title_editSuffix')"
                                    @click="startEditReferral(sales)"
                                  >
                                    <i class="fas fa-edit"></i>
                                    {{ t("common_edit") }}
                                  </button>
                                  <button
                                    type="button"
                                    class="sales-referral-btn sales-referral-btn--copy"
                                    :title="t('salesList_title_copyUrl')"
                                    @click="copyReferralUrl(sales)"
                                  >
                                    <i class="fas fa-copy"></i>
                                    {{ t("ibDetail_btnCopy") }}
                                  </button>
                                </template>
                              </span>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- IBs Under This Sales -->
                    <div
                      class="sales-detail-section sales-detail-section--full"
                    >
                      <div class="sales-detail-ib-client-wrap">
                        <div
                          class="sales-detail-table-header sales-detail-table-header--with-search"
                        >
                          <h3>
                            <i class="fas fa-handshake"></i>
                            {{
                              tParams(
                                "salesList_ib_section",
                                "IBs Under This Sales ({total} Total)",
                                {
                                  total:
                                    boundIbsMap[sales.id]?.pagination?.total ??
                                    0,
                                },
                              )
                            }}
                          </h3>
                          <div class="sales-detail-search-row">
                            <input
                              v-model="searchIbBySales[sales.id]"
                              type="text"
                              class="sales-detail-search-input"
                              :placeholder="
                                t('salesList_search_ib_placeholder')
                              "
                              @keyup.enter="loadBoundIbs(sales.id, true)"
                            />
                            <button
                              type="button"
                              class="sales-detail-btn-search"
                              @click="loadBoundIbs(sales.id, true)"
                            >
                              <i class="fas fa-search"></i>
                              {{ t("common_search") }}
                            </button>
                          </div>
                        </div>
                        <div
                          v-if="loadingBoundIbs[sales.id]"
                          class="sales-detail-loading"
                        >
                          {{ t("salesList_loadingIbs") }}
                        </div>
                        <div v-else class="sales-detail-table-scroll">
                          <table class="sales-detail-table">
                            <thead>
                              <tr>
                                <th>{{ t("salesList_th_ibCode") }}</th>
                                <th>{{ t("salesList_th_ibName") }}</th>
                                <th>{{ t("salesList_label_email") }}</th>
                                <th>{{ t("salesList_th_phone") }}</th>
                                <th>{{ t("salesList_th_country") }}</th>
                                <th>{{ t("salesList_th_clientsCount") }}</th>
                                <th>{{ t("salesList_th_totalCommission") }}</th>
                                <th>{{ t("salesList_th_status") }}</th>
                                <th>{{ t("salesList_th_action") }}</th>
                              </tr>
                            </thead>
                            <tbody>
                              <tr
                                v-for="ib in boundIbsMap[sales.id]?.items || []"
                                :key="ib.id"
                              >
                                <td>
                                  <span
                                    class="sales-detail-id sales-detail-id--link"
                                    @click="openIbDetail(ib)"
                                    >{{ ib.ibCode }}</span
                                  >
                                  <span
                                    v-if="ib.adminAlias"
                                    class="sales-detail-ib-alias"
                                    >({{ ib.adminAlias }})</span
                                  >
                                </td>
                                <td>
                                  <span class="sales-detail-name-cell">{{
                                    ib.ibName
                                  }}</span>
                                </td>
                                <td>
                                  <span class="sales-detail-email">{{
                                    ib.email
                                  }}</span>
                                </td>
                                <td>{{ ib.phone }}</td>
                                <td>{{ ib.country }}</td>
                                <td>{{ ib.clientsCount }}</td>
                                <td>
                                  <strong class="sales-detail-amount"
                                    >${{
                                      Number(
                                        ib.totalCommission ?? 0,
                                      ).toLocaleString("en-US", {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2,
                                      })
                                    }}</strong
                                  >
                                </td>
                                <td>
                                  <span
                                    class="sales-detail-kyc-badge"
                                    :class="ib.status"
                                    >{{ boundIbStatusText(ib) }}</span
                                  >
                                </td>
                                <td>
                                  <button
                                    type="button"
                                    class="sales-detail-btn-view"
                                    @click="openIbDetail(ib)"
                                  >
                                    <i class="fas fa-external-link-alt"></i>
                                    {{ t("salesList_btn_view") }}
                                  </button>
                                </td>
                              </tr>
                              <tr v-if="!boundIbsMap[sales.id]?.items?.length">
                                <td colspan="9" class="sales-detail-empty">
                                  {{
                                    loadingBoundIbs[sales.id]
                                      ? t("salesList_empty_loading")
                                      : t("salesList_empty_noIbs")
                                  }}
                                </td>
                              </tr>
                            </tbody>
                          </table>
                        </div>
                        <div
                          v-if="
                            (boundIbsMap[sales.id]?.pagination?.total ?? 0) > 0
                          "
                          class="sales-detail-pagination"
                        >
                          <div class="sales-detail-pagination__rows">
                            <label class="sales-detail-pagination__label">{{
                              t("salesList_showRows")
                            }}</label>
                            <select
                              :value="boundIbsPerPage[sales.id] ?? 10"
                              class="sales-detail-pagination__select"
                              @change="
                                onBoundIbPerPageChange(
                                  sales.id,
                                  $event.target.value,
                                )
                              "
                            >
                              <option :value="5">
                                {{ t("salesList_rows_5") }}
                              </option>
                              <option :value="10">
                                {{ t("salesList_rows_10") }}
                              </option>
                              <option :value="20">
                                {{ t("salesList_rows_20") }}
                              </option>
                              <option value="all">
                                {{ t("salesList_rows_all") }}
                              </option>
                            </select>
                          </div>
                          <span class="sales-detail-pagination__info">{{
                            boundIbPaginationInfo(sales.id)
                          }}</span>
                          <div class="sales-detail-pagination__btns">
                            <button
                              type="button"
                              class="sales-detail-pagination__btn"
                              :disabled="
                                (boundIbsMap[sales.id]?.pagination?.page ??
                                  1) <= 1
                              "
                              @click="
                                goToBoundIbPage(
                                  sales.id,
                                  (boundIbsMap[sales.id]?.pagination?.page ??
                                    1) - 1,
                                )
                              "
                            >
                              <i class="fas fa-chevron-left"></i>
                              {{ t("ibIr_pagination_prev") }}
                            </button>
                            <span class="sales-detail-pagination__page">{{
                              tParams(
                                "salesList_pagination_pageOf",
                                "Page {current} of {total}",
                                {
                                  current:
                                    boundIbsMap[sales.id]?.pagination?.page ??
                                    1,
                                  total:
                                    boundIbsMap[sales.id]?.pagination
                                      ?.total_pages ?? 1,
                                },
                              )
                            }}</span>
                            <button
                              type="button"
                              class="sales-detail-pagination__btn"
                              :disabled="
                                (boundIbsMap[sales.id]?.pagination?.page ??
                                  1) >=
                                (boundIbsMap[sales.id]?.pagination
                                  ?.total_pages ?? 1)
                              "
                              @click="
                                goToBoundIbPage(
                                  sales.id,
                                  (boundIbsMap[sales.id]?.pagination?.page ??
                                    1) + 1,
                                )
                              "
                            >
                              {{ t("ibIr_pagination_next") }}
                              <i class="fas fa-chevron-right"></i>
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- Clients Under This Sales -->
                    <div
                      class="sales-detail-section sales-detail-section--full"
                    >
                      <div class="sales-detail-ib-client-wrap">
                        <div
                          class="sales-detail-table-header sales-detail-table-header--with-search"
                        >
                          <h3>
                            <i class="fas fa-users"></i>
                            {{
                              tParams(
                                "salesList_clients_section",
                                "Clients Under This Sales ({total} Total)",
                                {
                                  total:
                                    boundClientsMap[sales.id]?.pagination
                                      ?.total ?? 0,
                                },
                              )
                            }}
                          </h3>
                          <div class="sales-detail-search-row">
                            <input
                              v-model="searchClientBySales[sales.id]"
                              type="text"
                              class="sales-detail-search-input"
                              :placeholder="
                                t('salesList_search_client_placeholder')
                              "
                              @keyup.enter="loadBoundClients(sales.id, true)"
                            />
                            <button
                              type="button"
                              class="sales-detail-btn-search"
                              @click="loadBoundClients(sales.id, true)"
                            >
                              <i class="fas fa-search"></i>
                              {{ t("common_search") }}
                            </button>
                          </div>
                        </div>
                        <div
                          v-if="loadingBoundClients[sales.id]"
                          class="sales-detail-loading"
                        >
                          {{ t("salesList_loadingClients") }}
                        </div>
                        <div v-else class="sales-detail-table-scroll">
                          <table class="sales-detail-table">
                            <thead>
                              <tr>
                                <th>{{ t("salesList_th_clientId") }}</th>
                                <th>{{ t("salesList_th_firstName") }}</th>
                                <th>{{ t("salesList_th_lastName") }}</th>
                                <th>{{ t("salesList_label_email") }}</th>
                                <th>{{ t("salesList_th_phone") }}</th>
                                <th>{{ t("salesList_th_country") }}</th>
                                <th>{{ t("salesList_th_balance") }}</th>
                                <th>{{ t("salesList_th_trades") }}</th>
                                <th>{{ t("salesList_th_kycStatus") }}</th>
                                <th>{{ t("salesList_th_action") }}</th>
                              </tr>
                            </thead>
                            <tbody>
                              <tr
                                v-for="client in boundClientsMap[sales.id]
                                  ?.items || []"
                                :key="client.id"
                              >
                                <td>
                                  <span
                                    class="sales-detail-id sales-detail-id--link"
                                    @click="openClientDetail(client)"
                                    >{{ client.clientId }}</span
                                  >
                                </td>
                                <td>
                                  <span class="sales-detail-name-cell">{{
                                    client.firstName
                                  }}</span>
                                </td>
                                <td>
                                  <span class="sales-detail-name-cell">{{
                                    client.lastName
                                  }}</span>
                                </td>
                                <td>
                                  <span class="sales-detail-email">{{
                                    client.email
                                  }}</span>
                                </td>
                                <td>{{ client.phone }}</td>
                                <td>{{ client.country }}</td>
                                <td>
                                  <strong class="sales-detail-amount"
                                    >${{
                                      Number(
                                        client.balance ?? 0,
                                      ).toLocaleString("en-US", {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2,
                                      })
                                    }}</strong
                                  >
                                </td>
                                <td>{{ client.trades ?? 0 }}</td>
                                <td>
                                  <span
                                    class="sales-detail-kyc-badge"
                                    :class="client.kycStatus"
                                    >{{ clientKycText(client) }}</span
                                  >
                                </td>
                                <td>
                                  <button
                                    type="button"
                                    class="sales-detail-btn-view"
                                    @click="openClientDetail(client)"
                                  >
                                    <i class="fas fa-external-link-alt"></i>
                                    {{ t("salesList_btn_view") }}
                                  </button>
                                </td>
                              </tr>
                              <tr
                                v-if="!boundClientsMap[sales.id]?.items?.length"
                              >
                                <td colspan="10" class="sales-detail-empty">
                                  {{
                                    loadingBoundClients[sales.id]
                                      ? t("salesList_empty_loading")
                                      : t("salesList_empty_noClients")
                                  }}
                                </td>
                              </tr>
                            </tbody>
                          </table>
                        </div>
                        <div
                          v-if="
                            (boundClientsMap[sales.id]?.pagination?.total ??
                              0) > 0
                          "
                          class="sales-detail-pagination"
                        >
                          <div class="sales-detail-pagination__rows">
                            <label class="sales-detail-pagination__label">{{
                              t("salesList_showRows")
                            }}</label>
                            <select
                              :value="boundClientsPerPage[sales.id] ?? 10"
                              class="sales-detail-pagination__select"
                              @change="
                                onBoundClientPerPageChange(
                                  sales.id,
                                  $event.target.value,
                                )
                              "
                            >
                              <option :value="5">
                                {{ t("salesList_rows_5") }}
                              </option>
                              <option :value="10">
                                {{ t("salesList_rows_10") }}
                              </option>
                              <option :value="20">
                                {{ t("salesList_rows_20") }}
                              </option>
                              <option value="all">
                                {{ t("salesList_rows_all") }}
                              </option>
                            </select>
                          </div>
                          <span class="sales-detail-pagination__info">{{
                            boundClientPaginationInfo(sales.id)
                          }}</span>
                          <div class="sales-detail-pagination__btns">
                            <button
                              type="button"
                              class="sales-detail-pagination__btn"
                              :disabled="
                                (boundClientsMap[sales.id]?.pagination?.page ??
                                  1) <= 1
                              "
                              @click="
                                goToBoundClientPage(
                                  sales.id,
                                  (boundClientsMap[sales.id]?.pagination
                                    ?.page ?? 1) - 1,
                                )
                              "
                            >
                              <i class="fas fa-chevron-left"></i>
                              {{ t("ibIr_pagination_prev") }}
                            </button>
                            <span class="sales-detail-pagination__page">{{
                              tParams(
                                "salesList_pagination_pageOf",
                                "Page {current} of {total}",
                                {
                                  current:
                                    boundClientsMap[sales.id]?.pagination
                                      ?.page ?? 1,
                                  total:
                                    boundClientsMap[sales.id]?.pagination
                                      ?.total_pages ?? 1,
                                },
                              )
                            }}</span>
                            <button
                              type="button"
                              class="sales-detail-pagination__btn"
                              :disabled="
                                (boundClientsMap[sales.id]?.pagination?.page ??
                                  1) >=
                                (boundClientsMap[sales.id]?.pagination
                                  ?.total_pages ?? 1)
                              "
                              @click="
                                goToBoundClientPage(
                                  sales.id,
                                  (boundClientsMap[sales.id]?.pagination
                                    ?.page ?? 1) + 1,
                                )
                              "
                            >
                              {{ t("ibIr_pagination_next") }}
                              <i class="fas fa-chevron-right"></i>
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- Sales Network Relationship Graph -->
                    <div
                      class="sales-detail-section sales-detail-section--full"
                    >
                      <div class="sales-detail-graph-header">
                        <h3>
                          <i class="fas fa-project-diagram"></i>
                          {{
                            tParams(
                              "salesList_graph_section",
                              "Sales Network Relationship Graph ({total} Total Clients)",
                              {
                                total:
                                  boundClientsMap[sales.id]?.pagination
                                    ?.total ?? 0,
                              },
                            )
                          }}
                        </h3>
                        <div class="sales-detail-graph-search">
                          <i class="fas fa-search"></i>
                          <input
                            type="text"
                            :placeholder="
                              t('salesList_graph_search_placeholder')
                            "
                            v-model="graphSearch[sales.id]"
                            @keydown.enter="searchSalesGraph(sales.id)"
                          />
                        </div>
                      </div>
                      <div class="sales-network-canvas-wrap">
                        <div
                          class="sales-zoom-indicator"
                          v-if="showZoomIndicator"
                        >
                          <i class="fas fa-search"></i>
                          <span>{{ zoomLevel }}%</span>
                        </div>
                        <div
                          class="sales-network-container"
                          :ref="
                            (el) => {
                              if (expandedSalesId === sales.id)
                                networkContainerRef = el;
                            }
                          "
                          @mousedown="handleGraphMouseDown"
                          @wheel.prevent="handleGraphWheel"
                          @dblclick="resetGraphView"
                        >
                          <div
                            class="sales-network-graph"
                            :ref="
                              (el) => {
                                if (expandedSalesId === sales.id)
                                  networkGraphRef = el;
                              }
                            "
                            :style="{
                              transform: `translate(${panX}px, ${panY}px) scale(${zoom})`,
                            }"
                          >
                            <div
                              v-if="graphLoadingFull[sales.id]"
                              class="sales-network-loading"
                            >
                              <i class="fas fa-spinner fa-spin"></i>
                              {{ t("salesList_graph_loading") }}
                            </div>
                            <template v-else>
                              <div class="network-branch network-branch--root">
                                <div class="network-node">
                                  <div class="node-card tier1 sales-root-card">
                                    <div class="node-content">
                                      <div class="node-avatar">
                                        {{ getInitials(sales.fullName) }}
                                      </div>
                                      <div class="node-info">
                                        <div class="node-title">
                                          {{ sales.fullName }}
                                        </div>
                                        <div class="node-subtitle">
                                          {{
                                            t("salesList_graph_rootSubtitle")
                                          }}
                                        </div>
                                        <div class="node-badge">
                                          <i class="fas fa-user-tie"></i>
                                          {{ t("salesList_graph_badge_sales") }}
                                        </div>
                                      </div>
                                    </div>
                                    <div class="node-stats">
                                      <div class="node-stat">
                                        <i class="fas fa-handshake"></i>
                                        {{
                                          tParams(
                                            "salesList_graph_stat_ibs",
                                            "{count} IBs",
                                            {
                                              count: (
                                                graphTreeBySales[
                                                  sales.id
                                                ]?.members?.filter(
                                                  (m) => m.type === "ib",
                                                ) || []
                                              ).length,
                                            },
                                          )
                                        }}
                                      </div>
                                      <div class="node-stat">
                                        <i class="fas fa-users"></i>
                                        {{
                                          tParams(
                                            "salesList_graph_stat_clients",
                                            "{count} Clients",
                                            {
                                              count: (
                                                graphTreeBySales[
                                                  sales.id
                                                ]?.members?.filter(
                                                  (m) => m.type === "client",
                                                ) || []
                                              ).length,
                                            },
                                          )
                                        }}
                                      </div>
                                    </div>
                                  </div>
                                  <div
                                    class="expand-btn"
                                    :class="{
                                      expanded: getExpandedNodes(sales.id)
                                        .tier1,
                                    }"
                                    @click.stop="toggleGraph(sales.id)"
                                  >
                                    {{
                                      getExpandedNodes(sales.id).tier1
                                        ? "−"
                                        : "+"
                                    }}
                                  </div>
                                </div>
                                <div
                                  class="network-children"
                                  :class="{
                                    expanded: getExpandedNodes(sales.id).tier1,
                                  }"
                                >
                                  <IbDetailNetworkGraphNode
                                    v-for="member in graphTreeBySales[sales.id]
                                      ?.members || []"
                                    :key="member.id"
                                    :member="member"
                                    :expanded-nodes="getExpandedNodes(sales.id)"
                                    :highlighted-node-id="
                                      expandedSalesId === sales.id
                                        ? highlightedGraphNodeId
                                        : null
                                    "
                                    @toggle="
                                      (nodeId) =>
                                        onGraphNodeToggle(sales.id, nodeId)
                                    "
                                  />
                                </div>
                                <div
                                  v-if="
                                    getExpandedNodes(sales.id).tier1 &&
                                    !graphTreeBySales[sales.id]?.members?.length
                                  "
                                  class="network-empty-msg"
                                >
                                  {{ t("salesList_graph_empty") }}
                                </div>
                              </div>
                            </template>
                          </div>
                        </div>
                        <div class="sales-zoom-controls">
                          <button
                            type="button"
                            class="sales-zoom-btn"
                            @click="zoomGraphOut"
                            :aria-label="t('salesList_aria_zoomOut')"
                          >
                            <i class="fas fa-minus"></i>
                          </button>
                          <div class="sales-zoom-level">
                            <i class="fas fa-search"></i> {{ zoomLevel }}%
                          </div>
                          <button
                            type="button"
                            class="sales-zoom-btn"
                            @click="zoomGraphIn"
                            :aria-label="t('salesList_aria_zoomIn')"
                          >
                            <i class="fas fa-plus"></i>
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
            </template>
            <tr v-if="visibleList.length === 0" class="sales-list-table__row">
              <td colspan="6" class="sales-list-table__empty">
                {{ t("salesList_table_empty") }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="sales-list-pagination" v-if="pagination.total > 0">
        <span class="sales-list-pagination__info">{{ paginationInfo }}</span>
        <div class="sales-list-pagination__btns">
          <button
            type="button"
            class="sales-list-btn sales-list-btn--pagination"
            :disabled="currentPage <= 1"
            @click="goToPage(currentPage - 1)"
          >
            <i class="fas fa-chevron-left"></i> {{ t("ibIr_pagination_prev") }}
          </button>
          <span class="sales-list-pagination__page">{{
            tParams(
              "salesList_pagination_pageOf",
              "Page {current} of {total}",
              { current: currentPage, total: totalPagesText },
            )
          }}</span>
          <button
            type="button"
            class="sales-list-btn sales-list-btn--pagination"
            :disabled="!hasNextPage"
            @click="goToPage(currentPage + 1)"
          >
            {{ t("ibIr_pagination_next") }} <i class="fas fa-chevron-right"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";

import { ref, computed, onMounted, onUnmounted, nextTick } from "vue";
import IbDetailNetworkGraphNode from "@/components/ib/IbDetailNetworkGraphNode.vue";
import salesApi from "@/services/salesApi";
import ibPartnersApi from "@/services/ibPartnersApi";
import { getSubModuleKey } from "@/config/operationLogPages";
import { useAdminI18n } from "@/composables/useAdminI18n";

const salesListLogSubModule = getSubModuleKey("page_sales_list");

const { t, tParams } = useAdminI18n();

function salesStatusText(sales) {
  const s = String(sales?.status ?? "").toLowerCase();
  if (s === "active") return t("salesList_status_active");
  if (s === "inactive") return t("salesList_status_inactive");
  return sales?.statusDisplay || "—";
}

function boundIbStatusText(ib) {
  const s = String(ib?.status ?? "").toLowerCase();
  const map = {
    approved: "salesList_kyc_approved",
    pending: "salesList_kyc_pending",
    rejected: "salesList_kyc_rejected",
    submitted: "salesList_kyc_submitted",
    none: "salesList_kyc_none",
  };
  if (map[s]) return t(map[s]);
  return ib?.statusDisplay || "—";
}

function clientKycText(client) {
  const s = String(client?.kycStatus ?? "").toLowerCase();
  const map = {
    approved: "salesList_kyc_approved",
    pending: "salesList_kyc_pending",
    rejected: "salesList_kyc_rejected",
    submitted: "salesList_kyc_submitted",
    none: "salesList_kyc_none",
  };
  if (map[s]) return t(map[s]);
  return client?.kycStatusDisplay || "—";
}

const loading = ref(false);
const expandedSalesId = ref(null);
const expandedGraph = ref({});
const graphSearch = ref({});
/** 关系图：全量 IB/Client（用于图，不按表分页） */
const graphFullIbsMap = ref({});
const graphFullClientsMap = ref({});
/** 关系图树：salesId -> { members: [ { id, name, code, type, hasChildren, children } ] } */
const graphTreeBySales = ref({});
/** 展开状态：salesId -> { tier1: true, 'ib-123': true } */
const expandedNodesBySales = ref({});
/** 当前高亮节点 id（用于搜索命中） */
const highlightedGraphNodeId = ref(null);
/** 拖拽/缩放 */
const networkContainerRef = ref(null);
const networkGraphRef = ref(null);
const panX = ref(0);
const panY = ref(0);
const zoom = ref(1.0);
const isDragging = ref(false);
const startX = ref(0);
const startY = ref(0);
const showZoomIndicator = ref(false);
const zoomLevel = ref("100");
const graphLoadingFull = ref({});
const perPage = ref(10);
const currentPage = ref(1);
const searchKeyword = ref("");

const fullList = ref([]);
const listStats = ref({ totalSales: 0, activeSales: 0, inactiveSales: 0 });

const boundIbsMap = ref({});
const boundClientsMap = ref({});
const searchIbBySales = ref({});
const searchClientBySales = ref({});
const loadingBoundIbs = ref({});
const loadingBoundClients = ref({});
const boundIbsPage = ref({});
const boundIbsPerPage = ref({});
const boundClientsPage = ref({});
const boundClientsPerPage = ref({});

const editingReferralSalesId = ref(null);
const editingReferralSuffix = ref("");
const referralSaveError = ref("");

const pagination = ref({
  total: 0,
  total_pages: 1,
  page: 1,
  per_page: 10,
});

const loadList = async () => {
  loading.value = true;
  try {
    const params = {
      page: currentPage.value,
      per_page: perPage.value === "all" ? "all" : perPage.value,
      search: searchKeyword.value?.trim() || undefined,
    };
    const { items, pagination: pag, stats } = await salesApi.getList(params);
    fullList.value = Array.isArray(items) ? items : [];
    listStats.value = stats || {
      totalSales: 0,
      activeSales: 0,
      inactiveSales: 0,
    };
    pagination.value = {
      total: pag?.total ?? 0,
      total_pages: Math.max(1, pag?.total_pages ?? 1),
      page: pag?.page ?? 1,
      per_page: pag?.per_page ?? 10,
    };
  } catch (err) {
    fullList.value = [];
    listStats.value = { totalSales: 0, activeSales: 0, inactiveSales: 0 };
    pagination.value = { total: 0, total_pages: 1, page: 1, per_page: 10 };
    console.error("Failed to load sales list:", err);
  } finally {
    loading.value = false;
  }
};

const totalPagesText = computed(() => {
  const total = pagination.value.total_pages;
  if (total <= 0) return "1";
  return String(total);
});

const hasNextPage = computed(
  () => pagination.value.page < pagination.value.total_pages,
);

const paginationInfo = computed(() => {
  const total = pagination.value.total;
  if (total === 0) return t("salesList_pagination_noRecords");
  if (perPage.value === "all")
    return tParams(
      "salesList_pagination_totalRecords",
      "Total {total} record(s)",
      { total },
    );
  const per = Number(pagination.value.per_page) || 10;
  const from = (currentPage.value - 1) * per + 1;
  const to = Math.min(currentPage.value * per, total);
  return tParams(
    "salesList_pagination_showing",
    "Showing {from}-{to} of {total}",
    { from, to, total },
  );
});

const visibleList = computed(() => fullList.value);

async function loadBoundIbs(salesId, resetPage = false) {
  if (!salesId) return;
  if (resetPage) boundIbsPage.value = { ...boundIbsPage.value, [salesId]: 1 };
  const page = boundIbsPage.value[salesId] ?? 1;
  const perPage = boundIbsPerPage.value[salesId] ?? 10;
  loadingBoundIbs.value = { ...loadingBoundIbs.value, [salesId]: true };
  try {
    const { items, pagination: pag } = await salesApi.getBoundIbs(salesId, {
      page,
      per_page: perPage === "all" ? 9999 : perPage,
      search: (searchIbBySales.value[salesId] || "").trim() || undefined,
    });
    boundIbsMap.value = {
      ...boundIbsMap.value,
      [salesId]: { items, pagination: pag },
    };
  } catch (e) {
    boundIbsMap.value = {
      ...boundIbsMap.value,
      [salesId]: {
        items: [],
        pagination: { total: 0, page: 1, per_page: 10, total_pages: 1 },
      },
    };
    console.error("Load bound IBs failed:", e);
  } finally {
    loadingBoundIbs.value = { ...loadingBoundIbs.value, [salesId]: false };
  }
}

async function loadBoundClients(salesId, resetPage = false) {
  if (!salesId) return;
  if (resetPage)
    boundClientsPage.value = { ...boundClientsPage.value, [salesId]: 1 };
  const page = boundClientsPage.value[salesId] ?? 1;
  const perPage = boundClientsPerPage.value[salesId] ?? 10;
  loadingBoundClients.value = { ...loadingBoundClients.value, [salesId]: true };
  try {
    const { items, pagination: pag } = await salesApi.getBoundClients(salesId, {
      page,
      per_page: perPage === "all" ? 9999 : perPage,
      search: (searchClientBySales.value[salesId] || "").trim() || undefined,
    });
    boundClientsMap.value = {
      ...boundClientsMap.value,
      [salesId]: { items, pagination: pag },
    };
  } catch (e) {
    boundClientsMap.value = {
      ...boundClientsMap.value,
      [salesId]: {
        items: [],
        pagination: { total: 0, page: 1, per_page: 10, total_pages: 1 },
      },
    };
    console.error("Load bound clients failed:", e);
  } finally {
    loadingBoundClients.value = {
      ...loadingBoundClients.value,
      [salesId]: false,
    };
  }
}

function boundIbPaginationInfo(salesId) {
  const pag = boundIbsMap.value[salesId]?.pagination;
  if (!pag || pag.total === 0) return t("salesList_pagination_noRecords");
  const per = Number(pag.per_page) || 10;
  if (per >= (pag.total || 0))
    return tParams(
      "salesList_pagination_totalRecords",
      "Total {total} record(s)",
      { total: pag.total },
    );
  const from = (pag.page - 1) * per + 1;
  const to = Math.min(pag.page * per, pag.total);
  return tParams(
    "salesList_pagination_showing",
    "Showing {from}-{to} of {total}",
    { from, to, total: pag.total },
  );
}

function boundClientPaginationInfo(salesId) {
  const pag = boundClientsMap.value[salesId]?.pagination;
  if (!pag || pag.total === 0) return t("salesList_pagination_noRecords");
  const per = Number(pag.per_page) || 10;
  if (per >= (pag.total || 0))
    return tParams(
      "salesList_pagination_totalRecords",
      "Total {total} record(s)",
      { total: pag.total },
    );
  const from = (pag.page - 1) * per + 1;
  const to = Math.min(pag.page * per, pag.total);
  return tParams(
    "salesList_pagination_showing",
    "Showing {from}-{to} of {total}",
    { from, to, total: pag.total },
  );
}

function goToBoundIbPage(salesId, page) {
  const pag = boundIbsMap.value[salesId]?.pagination;
  if (!pag || page < 1 || page > (pag.total_pages || 1)) return;
  boundIbsPage.value = { ...boundIbsPage.value, [salesId]: page };
  loadBoundIbs(salesId);
}

function goToBoundClientPage(salesId, page) {
  const pag = boundClientsMap.value[salesId]?.pagination;
  if (!pag || page < 1 || page > (pag.total_pages || 1)) return;
  boundClientsPage.value = { ...boundClientsPage.value, [salesId]: page };
  loadBoundClients(salesId);
}

function onBoundIbPerPageChange(salesId, val) {
  boundIbsPerPage.value = {
    ...boundIbsPerPage.value,
    [salesId]: val === "all" ? "all" : Number(val) || 10,
  };
  boundIbsPage.value = { ...boundIbsPage.value, [salesId]: 1 };
  loadBoundIbs(salesId);
}

function onBoundClientPerPageChange(salesId, val) {
  boundClientsPerPage.value = {
    ...boundClientsPerPage.value,
    [salesId]: val === "all" ? "all" : Number(val) || 10,
  };
  boundClientsPage.value = { ...boundClientsPage.value, [salesId]: 1 };
  loadBoundClients(salesId);
}

function getReferralUrlPrefix(sales) {
  const url = sales?.personalReferralUrl ?? "";
  const lastSlash = url.lastIndexOf("/");
  if (lastSlash === -1) return url;
  return url.slice(0, lastSlash + 1);
}

function getReferralUrlSuffix(sales) {
  const url = sales?.personalReferralUrl ?? "";
  const lastSlash = url.lastIndexOf("/");
  if (lastSlash === -1) return "";
  try {
    return decodeURIComponent(url.slice(lastSlash + 1));
  } catch {
    return url.slice(lastSlash + 1);
  }
}

async function copyReferralUrl(sales) {
  const url = sales?.personalReferralUrl ?? "";
  if (!url) return;
  try {
    await navigator.clipboard.writeText(url);
    alert(t("salesList_alert_urlCopied"));
  } catch {
    alert(t("salesList_alert_copyFailed"));
  }
}

function startEditReferral(sales) {
  editingReferralSalesId.value = sales.id;
  editingReferralSuffix.value = getReferralUrlSuffix(sales);
  referralSaveError.value = "";
}

function cancelEditReferral() {
  editingReferralSalesId.value = null;
  editingReferralSuffix.value = "";
  referralSaveError.value = "";
}

async function saveReferralSuffix(sales) {
  const suffix = (editingReferralSuffix.value ?? "").trim();
  if (!suffix) {
    referralSaveError.value = t("salesList_err_suffixRequired");
    return;
  }
  referralSaveError.value = "";
  try {
    const data = await salesApi.updateReferralSuffix(sales.id, suffix, {
      logSubModuleKey: salesListLogSubModule,
    });
    if (data?.personalReferralUrl) {
      const idx = fullList.value.findIndex((s) => s.id === sales.id);
      if (idx !== -1)
        fullList.value[idx] = {
          ...fullList.value[idx],
          personalReferralUrl: data.personalReferralUrl,
        };
    }
    cancelEditReferral();
  } catch (err) {
    const msg =
      err?.response?.data?.message ??
      err?.message ??
      t("salesList_err_updateFailed");
    referralSaveError.value = msg;
  }
}

function openClientDetail(client) {
  const base = window.location.origin + window.location.pathname;
  const id = client?.id;
  const email = (client?.email ?? "").trim();
  const kycStatus = (client?.kycStatus ?? "").toLowerCase();
  const path = kycStatus === "approved" ? "clients-list" : "leads";
  const q = new URLSearchParams();
  if (id != null) q.set("detailId", String(id));
  if (email) q.set("search", email);
  window.open(
    base + `#/${path}?${q.toString()}`,
    "_blank",
    "noopener,noreferrer",
  );
}

function openIbDetail(ib) {
  const base = window.location.origin + window.location.pathname;
  const id = ib?.id;
  const email = (ib?.email ?? "").trim();
  const q = new URLSearchParams();
  if (id != null) q.set("detailId", String(id));
  if (email) q.set("search", email);
  window.open(
    base + `#/ib-list?${q.toString()}`,
    "_blank",
    "noopener,noreferrer",
  );
}

function getInitials(name) {
  if (!name) return "—";
  return name
    .split(" ")
    .map((s) => s[0])
    .join("")
    .toUpperCase()
    .slice(0, 2);
}

function getIbInitials(name) {
  if (!name) return "—";
  const words = name.split(" ");
  if (words.length >= 3)
    return (words[0][0] + words[1][0] + words[2][0]).toUpperCase().slice(0, 3);
  return name.slice(0, 3).toUpperCase();
}

function toggleRowExpansion(id) {
  if (expandedSalesId.value === id) {
    expandedSalesId.value = null;
    return;
  }
  expandedSalesId.value = id;
  if (!searchIbBySales.value[id])
    searchIbBySales.value = { ...searchIbBySales.value, [id]: "" };
  if (!searchClientBySales.value[id])
    searchClientBySales.value = { ...searchClientBySales.value, [id]: "" };
  boundIbsPage.value = { ...boundIbsPage.value, [id]: 1 };
  boundIbsPerPage.value = { ...boundIbsPerPage.value, [id]: 10 };
  boundClientsPage.value = { ...boundClientsPage.value, [id]: 1 };
  boundClientsPerPage.value = { ...boundClientsPerPage.value, [id]: 10 };
  loadBoundIbs(id);
  loadBoundClients(id);
}

function buildTier1Members(ibs, clients) {
  const members = [];
  (ibs || []).forEach((ib) => {
    members.push({
      id: `ib-${ib.id}`,
      clientUserId: Number(ib.userId || 0),
      name: ib.ibName || "—",
      code: ib.ibCode || "",
      adminAlias: ib.adminAlias || "",
      type: "ib",
      hasChildren: true,
      children: [],
      initials: getIbInitials(ib.ibName),
    });
  });
  (clients || []).forEach((c) => {
    const fullName =
      [c.firstName, c.lastName].filter(Boolean).join(" ").trim() || "—";
    members.push({
      id: `client-${c.id}`,
      name: fullName,
      code: c.clientId || "",
      type: "client",
      hasChildren: false,
      children: [],
      initials: getInitials(fullName),
    });
  });
  return members;
}

async function loadGraphFullData(salesId) {
  if (graphFullIbsMap.value[salesId] && graphFullClientsMap.value[salesId]) {
    ensureGraphTreeAndExpanded(salesId);
    return;
  }
  graphLoadingFull.value = { ...graphLoadingFull.value, [salesId]: true };
  try {
    const [ibsRes, clientsRes] = await Promise.all([
      salesApi.getBoundIbs(salesId, { page: 1, per_page: 9999 }),
      salesApi.getBoundClients(salesId, { page: 1, per_page: 9999 }),
    ]);
    const ibs = ibsRes?.items ?? [];
    const clients = clientsRes?.items ?? [];
    graphFullIbsMap.value = {
      ...graphFullIbsMap.value,
      [salesId]: { items: ibs },
    };
    graphFullClientsMap.value = {
      ...graphFullClientsMap.value,
      [salesId]: { items: clients },
    };
    graphTreeBySales.value = {
      ...graphTreeBySales.value,
      [salesId]: { members: buildTier1Members(ibs, clients) },
    };
    expandedNodesBySales.value = {
      ...expandedNodesBySales.value,
      [salesId]: { ...expandedNodesBySales.value[salesId], tier1: true },
    };
  } finally {
    graphLoadingFull.value = { ...graphLoadingFull.value, [salesId]: false };
  }
}

function ensureGraphTreeAndExpanded(salesId) {
  const ibs = graphFullIbsMap.value[salesId]?.items ?? [];
  const clients = graphFullClientsMap.value[salesId]?.items ?? [];
  if (!graphTreeBySales.value[salesId]) {
    graphTreeBySales.value = {
      ...graphTreeBySales.value,
      [salesId]: { members: buildTier1Members(ibs, clients) },
    };
  }
  expandedNodesBySales.value = {
    ...expandedNodesBySales.value,
    [salesId]: { ...expandedNodesBySales.value[salesId], tier1: true },
  };
}

function findMemberInTree(members, nodeId) {
  if (!members || !nodeId) return null;
  for (const m of members) {
    if (m.id === nodeId) return m;
    const found = findMemberInTree(m.children, nodeId);
    if (found) return found;
  }
  return null;
}

async function loadIbChildrenForGraph(salesId, nodeId) {
  const tree = graphTreeBySales.value[salesId];
  if (!tree?.members) return;
  const member = findMemberInTree(tree.members, nodeId);
  if (
    !member ||
    member.type !== "ib" ||
    (member.children && member.children.length > 0)
  )
    return;
  const numericId = parseInt(String(nodeId).replace(/^ib-/, ""), 10);
  if (!numericId) return;
  try {
    const res = await ibPartnersApi.getNetwork(numericId);
    const raw = res?.data?.data ?? res?.data ?? res;
    const list = Array.isArray(raw) ? raw : (raw?.members ?? []);
    member.children = list;
  } catch (e) {
    console.error("Load IB network for graph failed:", e);
    member.children = [];
  }
}

function toggleGraph(salesId) {
  const alreadyOpen = expandedGraph.value[salesId];
  if (!alreadyOpen) {
    expandedGraph.value = { ...expandedGraph.value, [salesId]: true };
    loadGraphFullData(salesId);
    return;
  }
  const exp = expandedNodesBySales.value[salesId] || {};
  expandedNodesBySales.value = {
    ...expandedNodesBySales.value,
    [salesId]: { ...exp, tier1: !exp.tier1 },
  };
}

function getExpandedNodes(salesId) {
  return expandedNodesBySales.value[salesId] || {};
}

function onGraphNodeToggle(salesId, nodeId) {
  const exp = expandedNodesBySales.value[salesId] || {};
  const next = { ...exp, [nodeId]: !exp[nodeId] };
  expandedNodesBySales.value = {
    ...expandedNodesBySales.value,
    [salesId]: next,
  };
  if (next[nodeId]) loadIbChildrenForGraph(salesId, nodeId);
}

function searchInGraphMembers(members, query, path = []) {
  const results = [];
  const lowerQuery = String(query).toLowerCase().trim();
  if (!lowerQuery) return results;
  (members || []).forEach((member) => {
    const currentPath = [...path, member.id];
    const name = (member.name || "").toLowerCase();
    const code = (member.code || "").toLowerCase();
    const alias = (member.adminAlias || "").toLowerCase();
    const idStr = String(member.id).toLowerCase();
    if (
      name.includes(lowerQuery) ||
      code.includes(lowerQuery) ||
      alias.includes(lowerQuery) ||
      idStr.includes(lowerQuery)
    ) {
      results.push({ member, path: currentPath });
    }
    if (member.children && member.children.length > 0) {
      results.push(
        ...searchInGraphMembers(member.children, query, currentPath),
      );
    }
  });
  return results;
}

function expandToPathInGraph(salesId, path) {
  const next = { ...expandedNodesBySales.value[salesId], tier1: true };
  (path || []).forEach((nodeId) => {
    if (nodeId !== "tier1") next[nodeId] = true;
  });
  expandedNodesBySales.value = {
    ...expandedNodesBySales.value,
    [salesId]: next,
  };
}

function scrollGraphToNode(nodeId) {
  nextTick(() => {
    const el = document.querySelector(`[data-node-id="${nodeId}"]`);
    if (el && networkContainerRef.value) {
      const containerRect = networkContainerRef.value.getBoundingClientRect();
      const nodeRect = el.getBoundingClientRect();
      const relativeX = nodeRect.left - containerRect.left + nodeRect.width / 2;
      const relativeY = nodeRect.top - containerRect.top + nodeRect.height / 2;
      panX.value = containerRect.width / 2 - relativeX;
      panY.value = containerRect.height / 2 - relativeY;
    }
  });
}

function searchSalesGraph(salesId) {
  const query = (graphSearch.value[salesId] || "").trim();
  highlightedGraphNodeId.value = null;
  if (!query) return;
  const tree = graphTreeBySales.value[salesId];
  const members = tree?.members ?? [];
  const results = searchInGraphMembers(members, query);
  if (results.length > 0) {
    const first = results[0];
    expandToPathInGraph(salesId, first.path);
    highlightedGraphNodeId.value = first.member.id;
    scrollGraphToNode(first.member.id);
  } else {
    // eslint-disable-next-line no-alert
    alert(
      tParams(
        "salesList_graph_noMatch",
        'No members found matching "{query}"',
        { query },
      ),
    );
  }
}

function handleGraphMouseDown(e) {
  if (e.target.closest(".expand-btn") || e.target.closest(".node-card")) return;
  isDragging.value = true;
  startX.value = e.clientX - panX.value;
  startY.value = e.clientY - panY.value;
  if (networkContainerRef.value)
    networkContainerRef.value.classList.add("dragging");
  e.preventDefault();
}

function handleGraphMouseMove(e) {
  if (!isDragging.value) return;
  panX.value = e.clientX - startX.value;
  panY.value = e.clientY - startY.value;
}

function handleGraphMouseUp() {
  if (isDragging.value) {
    isDragging.value = false;
    if (networkContainerRef.value)
      networkContainerRef.value.classList.remove("dragging");
  }
}

function handleGraphWheel(e) {
  e.preventDefault();
  const delta = e.deltaY > 0 ? -0.1 : 0.1;
  zoom.value = Math.max(0.3, Math.min(3.0, zoom.value + delta));
  zoomLevel.value = String(Math.round(zoom.value * 100));
  showZoomIndicator.value = true;
  setTimeout(() => {
    showZoomIndicator.value = false;
  }, 1000);
}

function resetGraphView(e) {
  if (e.target.closest(".node-card") || e.target.closest(".expand-btn")) return;
  zoom.value = 1.0;
  panX.value = 0;
  panY.value = 0;
  zoomLevel.value = "100";
  showZoomIndicator.value = true;
  setTimeout(() => {
    showZoomIndicator.value = false;
  }, 1000);
}

function zoomGraphIn() {
  zoom.value = Math.min(3.0, zoom.value + 0.15);
  zoomLevel.value = String(Math.round(zoom.value * 100));
  showZoomIndicator.value = true;
  setTimeout(() => {
    showZoomIndicator.value = false;
  }, 1000);
}

function zoomGraphOut() {
  zoom.value = Math.max(0.3, zoom.value - 0.15);
  zoomLevel.value = String(Math.round(zoom.value * 100));
  showZoomIndicator.value = true;
  setTimeout(() => {
    showZoomIndicator.value = false;
  }, 1000);
}

onMounted(() => {
  document.addEventListener("mousemove", handleGraphMouseMove);
  document.addEventListener("mouseup", handleGraphMouseUp);
});

onUnmounted(() => {
  document.removeEventListener("mousemove", handleGraphMouseMove);
  document.removeEventListener("mouseup", handleGraphMouseUp);
});

const formatDate = (dateString) => {
  if (!dateString) return "—";
  const date = new Date(dateString);
  return date.toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
};

const statusClass = (status) => {
  if (!status) return "";
  if (status === "active") return "sales-list-status--active";
  if (status === "inactive") return "sales-list-status--inactive";
  return "";
};

const onSearch = () => {
  currentPage.value = 1;
  loadList();
};

const onPageSizeChange = () => {
  currentPage.value = 1;
  loadList();
};

const goToPage = (page) => {
  if (page < 1 || page > pagination.value.total_pages) return;
  currentPage.value = page;
  loadList();
};

onMounted(() => {
  loadList();
});
</script>

<style scoped>
.sales-list-container {
  max-width: 1600px;
  margin: 0 auto;
  padding: 30px 20px;
}
.sales-list-page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 2px solid var(--color-border);
}
.sales-list-page-title__heading {
  font-size: 28px;
  color: var(--color-ink);
  margin: 0 0 5px 0;
}
.sales-list-page-title__desc {
  font-size: 14px;
  color: var(--color-muted);
  margin: 0;
}
.sales-list-page-actions {
  display: flex;
  align-items: center;
  gap: 20px;
}
.sales-list-loading {
  text-align: center;
  padding: 100px 20px;
  color: var(--color-muted);
}
.sales-list-loading__icon {
  font-size: 32px;
  color: var(--color-brand);
}
.sales-list-loading__text {
  margin: 15px 0 0 0;
  color: var(--color-muted);
}
.sales-list-table-wrap {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
}
.sales-list-stats-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 30px;
  background: var(--color-surface);
  border-bottom: 2px solid var(--color-border);
  flex-wrap: wrap;
  gap: 15px;
}
.sales-list-stats-title {
  font-size: 20px;
  color: var(--color-ink);
  margin: 0 0 5px 0;
}
.sales-list-stats-desc {
  font-size: 14px;
  color: var(--color-muted);
  margin: 0;
}
.sales-list-page-stats {
  display: flex;
  gap: 15px;
  flex-wrap: wrap;
}
.sales-list-stat-badge {
  background: var(--color-brand-soft);
  color: var(--color-info);
  padding: 8px 16px;
  border-radius: 20px;
  font-size: 14px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 8px;
}
.sales-list-stat-badge i {
  font-size: 16px;
}
.sales-list-stat-badge--active {
  background: var(--color-success-soft);
  color: var(--color-success);
}
.sales-list-stat-badge--inactive {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}
.sales-list-toolbar {
  padding: 20px 30px;
  background: var(--color-surface-soft);
  border-bottom: 2px solid var(--color-border);
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 15px;
}
.sales-list-toolbar__title {
  font-size: 18px;
  color: var(--color-ink);
  margin: 0;
}
.sales-list-toolbar__right {
  display: flex;
  align-items: center;
  gap: 15px;
  flex-wrap: wrap;
}
.sales-list-search-wrap {
  display: flex;
  align-items: center;
  gap: 10px;
}
.sales-list-search {
  padding: 10px 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  min-width: 220px;
}
.sales-list-search:focus {
  outline: none;
  border-color: var(--color-brand);
}
.sales-list-btn {
  padding: 12px 20px;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.sales-list-btn--search {
  background: var(--color-brand-solid);
  color: white;
}
.sales-list-btn--search:hover {
  background: var(--color-brand-strong);
}
.sales-list-rows {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  color: var(--color-text);
}
.sales-list-rows__select {
  padding: 8px 12px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  color: var(--color-ink);
  background: var(--color-surface);
  font-size: 14px;
  cursor: pointer;
}
.sales-list-table-scroll {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}
.sales-list-table {
  width: 100%;
  min-width: 800px;
  border-collapse: separate;
  border-spacing: 0;
  background: var(--color-surface);
}
.sales-list-table__head {
  background: var(--color-surface-soft);
}
.sales-list-table__th {
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
.sales-list-table__row {
  border-bottom: 1px solid var(--color-border);
  transition: background 0.2s;
}
.sales-list-table__row:hover {
  background: var(--color-surface-soft);
}
.sales-list-table__cell {
  padding: 16px 20px;
  font-size: 14px;
  color: var(--color-text);
  white-space: nowrap;
}
.sales-list-table__empty {
  text-align: center;
  color: var(--color-muted);
  padding: 40px;
}
.sales-list-sales-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.sales-list-sales-name {
  font-weight: 600;
  color: var(--color-ink);
  font-size: 15px;
}
.sales-list-sales-code {
  font-size: 14px;
  color: var(--color-muted);
  font-family: "Courier New", monospace;
}
.sales-list-status {
  display: inline-block;
  padding: 4px 12px;
  border-radius: var(--radius-lg);
  font-size: 14px;
  font-weight: 600;
  text-transform: uppercase;
}
.sales-list-status--active {
  background: var(--color-success-soft);
  color: var(--color-success);
}
.sales-list-status--inactive {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}
.sales-list-btn-action {
  padding: 8px 14px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.sales-list-btn-action--detail {
  background: var(--color-brand-soft);
  color: var(--color-brand);
}
.sales-list-btn-action--detail:hover {
  background: var(--color-brand-solid);
  color: white;
}
.sales-list-table__row.expanded {
  background: var(--color-surface-soft);
}

/* Detail row (Client List style) */
.sales-detail-row {
  display: none;
}
.sales-detail-row.show {
  display: table-row;
}
.sales-detail-content {
  padding: 30px;
  background: var(--color-surface-soft);
  border-top: 3px solid var(--color-brand);
}
.sales-detail-sections {
  display: grid;
  gap: 25px;
  margin-bottom: 25px;
}
.sales-detail-section--full {
  grid-column: 1 / -1;
}
.sales-detail-card {
  background: var(--color-surface);
  padding: 25px;
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  border: 2px solid var(--color-border);
}
.sales-detail-card-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 2px solid var(--color-border);
}
.sales-detail-card-icon {
  width: 40px;
  height: 40px;
  border-radius: var(--radius-md);
  background: var(--color-brand-solid);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
}
.sales-detail-card-title {
  font-size: 16px;
  color: var(--color-ink);
  font-weight: 600;
  margin: 0;
}
.sales-detail-fields {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 16px 20px;
}
.sales-detail-field {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 0;
  border-bottom: 1px solid var(--color-border);
  gap: 12px;
}
.sales-detail-field--full {
  grid-column: 1 / -1;
}
.sales-detail-label {
  font-weight: 600;
  color: var(--color-muted);
  font-size: 14px;
  flex-shrink: 0;
}
.sales-detail-value {
  color: var(--color-ink);
  font-size: 14px;
}
.sales-detail-value--url {
  font-family: "Courier New", monospace;
  color: var(--color-brand);
  word-break: break-all;
}
.sales-detail-value--url-wrap {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}
.sales-referral-prefix {
  font-family: "Courier New", monospace;
  color: var(--color-text);
  word-break: break-all;
  font-size: 14px;
}
.sales-referral-suffix-input {
  padding: 6px 10px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: 14px;
  min-width: 120px;
}
.sales-referral-suffix-input:focus {
  outline: none;
  border-color: var(--color-brand);
}
.sales-referral-edit-actions {
  display: inline-flex;
  gap: 6px;
}
.sales-referral-error {
  color: var(--color-danger);
  font-size: 14px;
  display: block;
  margin-top: 4px;
}
/* Personal Referral URL 按钮：风格与 Sales Dashboard Copy 一致 */
.sales-referral-btn {
  padding: 8px 16px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition:
    background 0.2s,
    color 0.2s;
}
.sales-referral-btn--copy {
  background: var(--color-brand-solid);
  color: white;
}
.sales-referral-btn--copy:hover {
  background: var(--color-brand-strong);
  color: white;
}
.sales-referral-btn--edit {
  background: var(--color-surface);
  color: var(--color-brand);
  border: 2px solid var(--color-brand);
}
.sales-referral-btn--edit:hover {
  background: var(--color-brand-soft);
  color: var(--color-brand-strong);
  border-color: var(--color-brand-strong);
}
.sales-detail-btn {
  padding: 6px 12px;
  border-radius: var(--radius-sm);
  border: 1px solid var(--color-border);
  color: var(--color-ink);
  background: var(--color-surface);
  font-size: 14px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.sales-detail-btn:hover {
  border-color: var(--color-brand);
  color: var(--color-brand);
}
.sales-detail-btn--small {
  padding: 4px 10px;
  font-size: 14px;
}

/* IBs / Clients tables (SalesList.html style) */
.sales-detail-ib-client-wrap {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  overflow: hidden;
  box-shadow: var(--shadow-sm);
  margin-bottom: 20px;
}
.sales-detail-table-header {
  padding: 16px 20px;
  border-bottom: 2px solid var(--color-border);
}
.sales-detail-table-header h3 {
  font-size: 16px;
  color: var(--color-ink);
  font-weight: 700;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 10px;
}
.sales-detail-table-header h3 i {
  color: var(--color-brand);
}
.sales-detail-table-header--with-search {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}
.sales-detail-search-row {
  display: flex;
  align-items: center;
  gap: 10px;
}
.sales-detail-search-input {
  padding: 8px 14px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  min-width: 200px;
}
.sales-detail-search-input:focus {
  outline: none;
  border-color: var(--color-brand);
}
.sales-detail-btn-search {
  padding: 8px 16px;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  background: var(--color-brand-solid);
  color: white;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.sales-detail-btn-search:hover {
  background: var(--color-brand-strong);
}
.sales-detail-loading {
  padding: 20px;
  text-align: center;
  color: var(--color-muted);
  font-size: 14px;
}
.sales-detail-pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  padding: 12px 20px;
  background: var(--color-surface-soft);
  border-top: 1px solid var(--color-border);
}
.sales-detail-pagination__rows {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  color: var(--color-text);
}
.sales-detail-pagination__label {
  margin: 0;
}
.sales-detail-pagination__select {
  padding: 6px 10px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  color: var(--color-ink);
  background: var(--color-surface);
  font-size: 14px;
  cursor: pointer;
}
.sales-detail-pagination__info {
  font-size: 14px;
  color: var(--color-text);
}
.sales-detail-pagination__btns {
  display: flex;
  align-items: center;
  gap: 12px;
}
.sales-detail-pagination__page {
  font-size: 14px;
  color: var(--color-text);
}
.sales-detail-pagination__btn {
  padding: 8px 14px;
  font-size: 14px;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-border);
  color: var(--color-text);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.sales-detail-pagination__btn:hover:not(:disabled) {
  background: var(--color-brand-solid);
  color: white;
}
.sales-detail-pagination__btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.sales-detail-table-scroll {
  overflow-x: auto;
}
.sales-detail-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  background: var(--color-surface);
}
.sales-detail-table thead {
  background: var(--color-surface-soft);
}
.sales-detail-table th {
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
.sales-detail-table tbody tr {
  border-bottom: 1px solid var(--color-border);
  transition: background 0.2s;
}
.sales-detail-table tbody tr:hover {
  background: var(--color-surface-soft);
}
.sales-detail-table td {
  padding: 16px 20px;
  font-size: 14px;
  color: var(--color-text);
  white-space: nowrap;
}
.sales-detail-id {
  font-weight: 600;
  color: var(--color-brand);
}
.sales-detail-id--link {
  cursor: pointer;
}
.sales-detail-id--link:hover {
  text-decoration: underline;
}
.sales-detail-ib-alias {
  margin-left: 6px;
  font-size: 14px;
  color: var(--color-muted);
}
.sales-detail-name-cell {
  font-weight: 600;
  color: var(--color-ink);
}
.sales-detail-email {
  color: var(--color-muted);
}
.sales-detail-amount {
  color: var(--color-success);
}
.sales-detail-kyc-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: var(--radius-lg);
  font-size: 14px;
  font-weight: 600;
  text-transform: uppercase;
}
.sales-detail-kyc-badge.approved {
  background: var(--color-success-soft);
  color: var(--color-success);
}
.sales-detail-kyc-badge.pending {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}
.sales-detail-btn-view {
  padding: 8px 16px;
  border-radius: var(--radius-md);
  border: 2px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text);
  font-weight: 600;
  cursor: pointer;
  font-size: 14px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.sales-detail-btn-view:hover {
  border-color: var(--color-brand);
  color: var(--color-brand);
  transform: translateY(-2px);
}
.sales-detail-empty {
  text-align: center;
  padding: 20px;
  color: var(--color-muted);
  font-size: 14px;
}

/* Network graph (aligned with IB Detail Relationship Network) */
.sales-detail-graph-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
  flex-wrap: wrap;
  gap: 10px;
}
.sales-detail-graph-header h3 {
  margin: 0;
  font-size: 16px;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 8px;
}
.sales-detail-graph-header h3 i {
  color: var(--color-brand);
}
.sales-detail-graph-search {
  position: relative;
  display: flex;
  align-items: center;
}
.sales-detail-graph-search i {
  position: absolute;
  left: 12px;
  color: var(--color-faint);
  font-size: 14px;
}
.sales-detail-graph-search input {
  padding: 10px 12px 10px 35px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  width: 260px;
}
.sales-network-canvas-wrap {
  position: relative;
  min-height: 400px;
}
.sales-zoom-indicator {
  position: absolute;
  top: 12px;
  right: 12px;
  background: rgba(var(--color-brand-rgb), 0.95);
  color: white;
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 14px;
  font-weight: 600;
  z-index: 10;
  pointer-events: none;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}
.sales-network-loading {
  padding: 24px;
  text-align: center;
  color: var(--color-muted);
}
.sales-network-container {
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: 24px;
  overflow: hidden;
  min-height: 400px;
  max-height: 520px;
  position: relative;
  cursor: grab;
  user-select: none;
}
.sales-network-container.dragging {
  cursor: grabbing;
}
.sales-network-graph {
  display: flex;
  flex-direction: row;
  align-items: flex-start;
  min-width: max-content;
  padding: 16px;
  transform-origin: 0 0;
}
.sales-network-graph .network-branch--root {
  display: flex;
  flex-direction: row;
  align-items: flex-start;
  gap: 24px;
}
.sales-network-graph .network-branch--root .network-node {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 16px;
}
.sales-network-graph .node-card.tier1.sales-root-card {
  background: var(--color-brand-solid);
  border: 3px solid var(--color-brand-strong);
  color: white;
  min-width: 220px;
  padding: 18px 20px;
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}
.sales-network-graph .node-card.tier1.sales-root-card .node-avatar {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.25);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  font-weight: 700;
  border: 2px solid rgba(255, 255, 255, 0.4);
}
.sales-network-graph .node-card.tier1.sales-root-card .node-title {
  font-size: 15px;
}
.sales-network-graph .node-card.tier1.sales-root-card .node-subtitle {
  opacity: 0.9;
}
.sales-network-graph .node-card.tier1.sales-root-card .node-badge {
  background: rgba(255, 255, 255, 0.25);
  padding: 3px 8px;
  border-radius: var(--radius-md);
  font-size: 14px;
}
.sales-network-graph .node-card.tier1.sales-root-card .node-stats {
  display: flex;
  gap: 12px;
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px solid rgba(255, 255, 255, 0.25);
  font-size: 14px;
  flex-wrap: wrap;
}
.sales-network-graph .node-card.tier1.sales-root-card .node-stat {
  display: flex;
  align-items: center;
  gap: 4px;
}
.sales-network-graph .network-branch--root .expand-btn {
  width: 32px;
  height: 32px;
  min-width: 32px;
  min-height: 32px;
  border-radius: 50%;
  background: var(--color-surface);
  border: 3px solid var(--color-border-strong);
  color: var(--color-text);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 16px;
  font-weight: 700;
  flex-shrink: 0;
  z-index: 5;
}
.sales-network-graph .network-branch--root .expand-btn:hover,
.sales-network-graph .network-branch--root .expand-btn.expanded {
  background: var(--color-brand-solid);
  border-color: var(--color-brand);
  color: white;
}
.sales-network-graph .network-branch--root .network-children {
  display: none;
  flex-direction: column;
  gap: 16px;
  margin-left: 24px;
  padding-left: 20px;
  border-left: 2px solid var(--color-border-strong);
}
.sales-network-graph .network-branch--root .network-children.expanded {
  display: flex;
}
.sales-network-graph .network-empty-msg {
  margin-left: 24px;
  padding: 12px;
  color: var(--color-muted);
  font-size: 14px;
}
.sales-zoom-controls {
  position: absolute;
  bottom: 20px;
  right: 20px;
  display: flex;
  gap: 10px;
  align-items: center;
  background: var(--color-surface);
  padding: 10px;
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-sm);
  z-index: 5;
}
.sales-zoom-btn {
  width: 36px;
  height: 36px;
  border: 2px solid var(--color-border);
  color: var(--color-ink);
  background: var(--color-surface);
  border-radius: var(--radius-sm);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}
.sales-zoom-btn:hover {
  border-color: var(--color-brand);
  color: var(--color-brand);
}
.sales-zoom-level {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 10px;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
}

.sales-list-pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 15px;
  padding: 16px 24px;
  background: var(--color-surface-soft);
  border-top: 1px solid var(--color-border);
}
.sales-list-pagination__info {
  font-size: 14px;
  color: var(--color-text);
}
.sales-list-pagination__btns {
  display: flex;
  align-items: center;
  gap: 12px;
}
.sales-list-pagination__page {
  font-size: 14px;
  color: var(--color-text);
}
.sales-list-btn {
  padding: 12px 20px;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.sales-list-btn--pagination {
  padding: 8px 14px;
  font-size: 14px;
  background: var(--color-border);
  color: var(--color-text);
}
.sales-list-btn--pagination:hover:not(:disabled) {
  background: var(--color-brand-solid);
  color: white;
}
.sales-list-btn--pagination:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
