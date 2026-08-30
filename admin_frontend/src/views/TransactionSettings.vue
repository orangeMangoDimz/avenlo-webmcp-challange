<template>
  <div class="transaction-settings-page ui-page workspace-settings-page">
    <!-- Page Header -->
    <div class="page-header ui-page-header">
      <div class="page-title">
        <h1>{{ t("page_transactionSettings_title") }}</h1>
        <p>{{ t("page_transactionSettings_sub") }}</p>
      </div>
      <div class="page-actions">
        <PageHeaderActions />
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-container">
      <i class="fas fa-spinner fa-spin"></i>
      <p>{{ t("txnSettings_loadingPage") }}</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-container">
      <i class="fas fa-exclamation-circle"></i>
      <p>{{ error }}</p>
      <button class="btn btn-primary" @click="loadSettings">
        <i class="fas fa-redo"></i> {{ t("depositMgmt_retry") }}
      </button>
    </div>

    <!-- Settings Content -->
    <div v-else class="settings-content">
      <!-- Payment Gateways -->
      <div class="settings-card">
        <div class="card-header" @click="toggleCard('gatewayList')">
          <div class="card-header-content">
            <h2>
              <i class="fas fa-plug"></i>
              {{ t("txnSettings_paymentGatewayTitle") }}
            </h2>
            <p>{{ t("txnSettings_paymentGatewayDesc") }}</p>
          </div>
          <i
            :class="[
              'fas fa-chevron-down card-collapse-icon',
              { rotated: !collapsedCards.gatewayList },
            ]"
          ></i>
        </div>

        <transition name="card-expand">
          <div v-show="!collapsedCards.gatewayList" class="card-body">
            <div class="gateway-list">
              <div v-if="gatewayList.length === 0" class="empty-state">
                <i class="fas fa-info-circle"></i>
                <p>{{ t("txnSettings_noGateways") }}</p>
              </div>

              <div v-else class="gateway-table">
                <table>
                  <thead>
                    <tr>
                      <th>{{ t("txnSettings_col_gateway") }}</th>
                      <th>{{ t("common_status") }}</th>
                      <th>{{ t("txnSettings_col_deposit") }}</th>
                      <th>{{ t("txnSettings_col_withdrawal") }}</th>
                      <th>{{ t("txnSettings_col_fiat") }}</th>
                      <th>{{ t("txnSettings_col_crypto") }}</th>
                      <th class="gateway-table__actions">
                        {{ t("common_actions") }}
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    <template
                      v-for="group in groupedGatewayList"
                      :key="group.name"
                    >
                      <tr class="gateway-group-row">
                        <td colspan="7">
                          <div class="gateway-cell">
                            <div class="gateway-cell-text">
                              <strong>{{ group.name }}</strong>
                            </div>
                          </div>
                        </td>
                      </tr>
                      <template
                        v-for="gateway in group.gateways"
                        :key="gateway.gatewayKey"
                      >
                        <tr
                          :class="[
                            { inactive: !gateway.isEnabled },
                            {
                              'gateway-summary-row': isGatewayExpanded(
                                gateway.gatewayKey,
                              ),
                            },
                          ]"
                        >
                          <td>
                            <div class="gateway-cell">
                              <div
                                :class="[
                                  'gateway-icon-preview',
                                  getGatewayIconPreviewClass(gateway.type),
                                ]"
                              >
                                <i
                                  :class="gateway.iconClass || 'fas fa-wallet'"
                                ></i>
                              </div>
                              <div class="gateway-cell-text">
                                <strong>{{
                                  gateway.gatewayName || gateway.gatewayKey
                                }}</strong>
                                <span>{{ gateway.gatewayKey }}</span>
                              </div>
                            </div>
                          </td>
                          <td>
                            <span
                              :class="[
                                'status-badge',
                                gateway.isEnabled ? 'active' : 'inactive',
                              ]"
                            >
                              {{
                                gateway.isEnabled
                                  ? t("txnSettings_statusEnabled")
                                  : t("txnSettings_statusDisabled")
                              }}
                            </span>
                          </td>
                          <td>
                            <span
                              :class="[
                                'status-badge',
                                gateway.isDepositEnabled
                                  ? 'active'
                                  : 'inactive',
                              ]"
                            >
                              {{
                                gateway.isDepositEnabled
                                  ? t("txnSettings_statusEnabled")
                                  : t("txnSettings_statusDisabled")
                              }}
                            </span>
                          </td>
                          <td>
                            <span
                              :class="[
                                'status-badge',
                                gateway.isWithdrawalEnabled
                                  ? 'active'
                                  : 'inactive',
                              ]"
                            >
                              {{
                                gateway.isWithdrawalEnabled
                                  ? t("txnSettings_statusEnabled")
                                  : t("txnSettings_statusDisabled")
                              }}
                            </span>
                          </td>
                          <td>
                            <div class="currency-tags">
                              <span
                                v-for="currency in normalizeCurrencyList(
                                  gateway.supportedFiatCurrencies,
                                )"
                                :key="`${gateway.gatewayKey}-fiat-${currency}`"
                                class="currency-tag"
                              >
                                {{ currency }}
                              </span>
                              <span
                                v-if="
                                  normalizeCurrencyList(
                                    gateway.supportedFiatCurrencies,
                                  ).length === 0
                                "
                                >-</span
                              >
                            </div>
                          </td>
                          <td>
                            <div class="currency-tags">
                              <span
                                v-for="currency in normalizeCurrencyList(
                                  gateway.supportedCryptoCurrencies,
                                )"
                                :key="`${gateway.gatewayKey}-crypto-${currency}`"
                                class="currency-tag crypto"
                              >
                                {{ currency }}
                              </span>
                              <span
                                v-if="
                                  normalizeCurrencyList(
                                    gateway.supportedCryptoCurrencies,
                                  ).length === 0
                                "
                                >-</span
                              >
                            </div>
                          </td>
                          <td class="gateway-table__actions">
                            <div class="gateway-row-actions">
                              <button
                                type="button"
                                class="btn-detail"
                                @click="toggleGatewayDetail(gateway)"
                              >
                                <i
                                  :class="
                                    isGatewayExpanded(gateway.gatewayKey)
                                      ? 'fas fa-chevron-up'
                                      : 'fas fa-chevron-down'
                                  "
                                ></i>
                                {{
                                  isGatewayExpanded(gateway.gatewayKey)
                                    ? t("txnSettings_btn_hide")
                                    : t("txnSettings_btn_detail")
                                }}
                              </button>
                              <button
                                v-if="hasEditPermission"
                                type="button"
                                class="gateway-action-btn gateway-action-btn--icon"
                                :title="
                                  gateway.isEnabled
                                    ? t('txnSettings_title_disable')
                                    : t('txnSettings_title_enable')
                                "
                                @click="toggleGatewayStatus(gateway)"
                              >
                                <i
                                  :class="
                                    gateway.isEnabled
                                      ? 'fas fa-toggle-on'
                                      : 'fas fa-toggle-off'
                                  "
                                ></i>
                              </button>
                              <button
                                v-if="hasEditPermission"
                                type="button"
                                class="gateway-action-btn gateway-action-btn--danger gateway-action-btn--icon"
                                :title="t('txnSettings_title_delete')"
                                @click="deleteGatewayConfig(gateway)"
                              >
                                <i class="fas fa-trash"></i>
                              </button>
                            </div>
                          </td>
                        </tr>
                        <tr
                          class="gateway-detail-row"
                          :class="{
                            show: isGatewayExpanded(gateway.gatewayKey),
                          }"
                        >
                          <td colspan="7">
                            <div
                              v-if="isGatewayExpanded(gateway.gatewayKey)"
                              class="gateway-detail-content"
                            >
                              <div class="gateway-detail-sections">
                                <div class="gateway-detail-section">
                                  <div class="gateway-detail-section-header">
                                    <h3>
                                      <i class="fas fa-circle-info"></i>
                                      {{ t("txnSettings_gatewayOverview") }}
                                    </h3>
                                    <button
                                      v-if="hasEditPermission"
                                      type="button"
                                      class="gateway-section-edit-btn"
                                      @click="openGatewayModal(gateway)"
                                    >
                                      <i class="fas fa-edit"></i>
                                      <span>{{ t("common_edit") }}</span>
                                    </button>
                                  </div>
                                  <div class="gateway-detail-field">
                                    <span class="gateway-detail-label">{{
                                      t("txnSettings_label_gatewayName")
                                    }}</span>
                                    <span class="gateway-detail-value">{{
                                      gateway.gatewayName || gateway.gatewayKey
                                    }}</span>
                                  </div>
                                  <div class="gateway-detail-field">
                                    <span class="gateway-detail-label">{{
                                      t("txnSettings_label_gatewayKey")
                                    }}</span>
                                    <span class="gateway-detail-value">{{
                                      gateway.gatewayKey || "-"
                                    }}</span>
                                  </div>
                                  <div class="gateway-detail-field">
                                    <span class="gateway-detail-label">{{
                                      t("txnSettings_label_environment")
                                    }}</span>
                                    <span class="gateway-detail-value">{{
                                      formatGatewayEnvironment(
                                        gateway.environment,
                                      )
                                    }}</span>
                                  </div>
                                  <div class="gateway-detail-field">
                                    <span class="gateway-detail-label">{{
                                      t("txnSettings_label_merchantNameShort")
                                    }}</span>
                                    <span class="gateway-detail-value">{{
                                      gateway.merchantName || "-"
                                    }}</span>
                                  </div>
                                  <div class="gateway-detail-field">
                                    <span class="gateway-detail-label">{{
                                      t(
                                        "txnSettings_label_depositProcessingTime",
                                        "Deposit Processing Time",
                                      )
                                    }}</span>
                                    <span class="gateway-detail-value">{{
                                      gateway.processingTime || "-"
                                    }}</span>
                                  </div>
                                  <div class="gateway-detail-field">
                                    <span class="gateway-detail-label">{{
                                      t(
                                        "txnSettings_label_withdrawalProcessingTime",
                                        "Withdrawal Processing Time",
                                      )
                                    }}</span>
                                    <span class="gateway-detail-value">{{
                                      gateway.withdrawalProcessingTime || "-"
                                    }}</span>
                                  </div>
                                  <div class="gateway-detail-field">
                                    <span class="gateway-detail-label">{{
                                      t(
                                        "txnSettings_label_displayOrder",
                                        "Display Order",
                                      )
                                    }}</span>
                                    <span class="gateway-detail-value">{{
                                      gateway.displayOrder ?? 0
                                    }}</span>
                                  </div>
                                  <div class="gateway-detail-field">
                                    <span class="gateway-detail-label">{{
                                      t("txnSettings_label_currentStatus")
                                    }}</span>
                                    <span class="gateway-detail-value">
                                      <span
                                        :class="[
                                          'status-badge',
                                          gateway.isEnabled
                                            ? 'active'
                                            : 'inactive',
                                        ]"
                                      >
                                        {{
                                          gateway.isEnabled
                                            ? t("txnSettings_statusEnabled")
                                            : t("txnSettings_statusDisabled")
                                        }}
                                      </span>
                                    </span>
                                  </div>
                                </div>

                                <div class="gateway-detail-section">
                                  <div class="gateway-detail-section-header">
                                    <h3>
                                      <i class="fas fa-sliders-h"></i>
                                      {{
                                        t("txnSettings_availabilityCurrency")
                                      }}
                                    </h3>
                                    <button
                                      v-if="hasEditPermission"
                                      type="button"
                                      class="gateway-section-edit-btn"
                                      @click="
                                        openGatewayCapabilityModal(gateway)
                                      "
                                    >
                                      <i class="fas fa-edit"></i>
                                      <span>{{ t("common_edit") }}</span>
                                    </button>
                                  </div>
                                  <div class="gateway-detail-field">
                                    <span class="gateway-detail-label">{{
                                      t("txnSettings_label_depositEnabled")
                                    }}</span>
                                    <span class="gateway-detail-value">
                                      <span
                                        :class="[
                                          'status-badge',
                                          gateway.isDepositEnabled
                                            ? 'active'
                                            : 'inactive',
                                        ]"
                                      >
                                        {{
                                          gateway.isDepositEnabled
                                            ? t("txnSettings_statusEnabled")
                                            : t("txnSettings_statusDisabled")
                                        }}
                                      </span>
                                    </span>
                                  </div>
                                  <div class="gateway-detail-field">
                                    <span class="gateway-detail-label">{{
                                      t("txnSettings_label_withdrawalEnabled")
                                    }}</span>
                                    <span class="gateway-detail-value">
                                      <span
                                        :class="[
                                          'status-badge',
                                          gateway.isWithdrawalEnabled
                                            ? 'active'
                                            : 'inactive',
                                        ]"
                                      >
                                        {{
                                          gateway.isWithdrawalEnabled
                                            ? t("txnSettings_statusEnabled")
                                            : t("txnSettings_statusDisabled")
                                        }}
                                      </span>
                                    </span>
                                  </div>
                                  <div class="gateway-detail-field">
                                    <span class="gateway-detail-label">{{
                                      t("txnSettings_label_supportedFiatShort")
                                    }}</span>
                                    <span
                                      class="gateway-detail-value gateway-detail-value--wrap"
                                    >
                                      {{
                                        formatGatewayCurrencySummary(
                                          gateway.supportedFiatCurrencies,
                                        )
                                      }}
                                    </span>
                                  </div>
                                  <div class="gateway-detail-field">
                                    <span class="gateway-detail-label">{{
                                      t(
                                        "txnSettings_label_supportedCryptoShort",
                                      )
                                    }}</span>
                                    <span
                                      class="gateway-detail-value gateway-detail-value--wrap"
                                    >
                                      {{
                                        formatGatewayCurrencySummary(
                                          gateway.supportedCryptoCurrencies,
                                        )
                                      }}
                                    </span>
                                  </div>
                                </div>

                                <div class="gateway-detail-section full-width">
                                  <div class="gateway-detail-section-header">
                                    <h3>
                                      <i class="fas fa-wallet"></i>
                                      {{
                                        t("txnSettings_section_paymentMethods")
                                      }}
                                    </h3>
                                  </div>

                                  <div
                                    v-if="
                                      getGatewayQuestionDetailState(
                                        gateway.gatewayKey,
                                      ).loading
                                    "
                                    class="loading-container compact"
                                  >
                                    <i class="fas fa-spinner fa-spin"></i>
                                    <p>
                                      {{
                                        t("txnSettings_loadingPaymentMethods")
                                      }}
                                    </p>
                                  </div>

                                  <div
                                    v-else-if="
                                      getGatewayQuestionDetailState(
                                        gateway.gatewayKey,
                                      ).data
                                    "
                                  >
                                    <GatewayPaymentMethodsList
                                      :methods="
                                        getGatewayPaymentMethodOptions(
                                          gateway.gatewayKey,
                                        )
                                      "
                                      :can-edit="hasEditPermission"
                                      :toggling-id="togglingPaymentMethodId"
                                      @toggle="
                                        (method) =>
                                          toggleGatewayPaymentMethodOption(
                                            gateway,
                                            method,
                                          )
                                      "
                                    />
                                  </div>

                                  <div v-else class="error-container compact">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <p>
                                      {{
                                        getGatewayQuestionDetailState(
                                          gateway.gatewayKey,
                                        ).error ||
                                        t("txnSettings_err_loadPaymentMethods")
                                      }}
                                    </p>
                                  </div>
                                </div>

                                <div class="gateway-detail-section full-width">
                                  <div class="gateway-detail-section-header">
                                    <h3>
                                      <i class="fas fa-percent"></i>
                                      {{ t("txnSettings_section_limit") }}
                                    </h3>
                                    <button
                                      v-if="hasEditPermission"
                                      type="button"
                                      class="gateway-section-edit-btn"
                                      @click="openGatewayFundingModal(gateway)"
                                    >
                                      <i class="fas fa-edit"></i>
                                      <span>{{ t("common_edit") }}</span>
                                    </button>
                                  </div>

                                  <div
                                    v-if="
                                      getGatewayFundingDetailState(
                                        gateway.gatewayKey,
                                      ).loading
                                    "
                                    class="loading-container compact"
                                  >
                                    <i class="fas fa-spinner fa-spin"></i>
                                    <p>
                                      {{
                                        t("txnSettings_loadingFundingSetting")
                                      }}
                                    </p>
                                  </div>

                                  <div
                                    v-else-if="
                                      getGatewayFundingDetailState(
                                        gateway.gatewayKey,
                                      ).data
                                    "
                                    class="gateway-funding-display"
                                  >
                                    <div
                                      class="gateway-funding-grid gateway-funding-grid--limits"
                                    >
                                      <div class="gateway-funding-card">
                                        <h5>
                                          {{ t("txnSettings_depositLimit") }}
                                        </h5>
                                        <div class="gateway-funding-stat">
                                          <span
                                            class="gateway-funding-stat-label"
                                            >{{
                                              t("txnSettings_minDeposit")
                                            }}</span
                                          >
                                          <strong>{{
                                            formatFundingValue(
                                              getGatewayFundingDetailState(
                                                gateway.gatewayKey,
                                              ).data.minDeposit,
                                            )
                                          }}</strong>
                                        </div>
                                        <div class="gateway-funding-stat">
                                          <span
                                            class="gateway-funding-stat-label"
                                            >{{
                                              t("txnSettings_maxDeposit")
                                            }}</span
                                          >
                                          <strong>{{
                                            formatFundingValue(
                                              getGatewayFundingDetailState(
                                                gateway.gatewayKey,
                                              ).data.maxDeposit,
                                            )
                                          }}</strong>
                                        </div>
                                      </div>

                                      <div class="gateway-funding-card">
                                        <h5>
                                          {{ t("txnSettings_withdrawLimit") }}
                                        </h5>
                                        <div class="gateway-funding-stat">
                                          <span
                                            class="gateway-funding-stat-label"
                                            >{{
                                              t("txnSettings_minWithdrawal")
                                            }}</span
                                          >
                                          <strong>{{
                                            formatFundingValue(
                                              getGatewayFundingDetailState(
                                                gateway.gatewayKey,
                                              ).data.minWithdrawal,
                                            )
                                          }}</strong>
                                        </div>
                                        <div class="gateway-funding-stat">
                                          <span
                                            class="gateway-funding-stat-label"
                                            >{{
                                              t("txnSettings_maxWithdrawal")
                                            }}</span
                                          >
                                          <strong>{{
                                            formatFundingValue(
                                              getGatewayFundingDetailState(
                                                gateway.gatewayKey,
                                              ).data.maxWithdrawal,
                                            )
                                          }}</strong>
                                        </div>
                                      </div>
                                    </div>
                                  </div>

                                  <div v-else class="error-container compact">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <p>
                                      {{
                                        getGatewayFundingDetailState(
                                          gateway.gatewayKey,
                                        ).error ||
                                        t("txnSettings_err_loadFunding")
                                      }}
                                    </p>
                                  </div>
                                </div>

                                <div class="gateway-detail-section full-width">
                                  <div class="gateway-detail-section-header">
                                    <h3>
                                      <i class="fas fa-bolt"></i>
                                      {{
                                        t(
                                          "txnSettings_section_quickAmounts",
                                          "Quick Amounts",
                                        )
                                      }}
                                    </h3>
                                  </div>

                                  <div
                                    v-if="
                                      getGatewayFundingDetailState(
                                        gateway.gatewayKey,
                                      ).loading
                                    "
                                    class="loading-container compact"
                                  >
                                    <i class="fas fa-spinner fa-spin"></i>
                                    <p>
                                      {{
                                        t("txnSettings_loadingFundingSetting")
                                      }}
                                    </p>
                                  </div>

                                  <div
                                    v-else-if="
                                      getGatewayFundingDetailState(
                                        gateway.gatewayKey,
                                      ).data
                                    "
                                    class="gateway-funding-display"
                                  >
                                    <div
                                      class="gateway-funding-grid gateway-funding-grid--limits"
                                    >
                                      <div class="gateway-funding-card">
                                        <h5>
                                          {{
                                            t(
                                              "txnSettings_depositQuickAmounts",
                                              "Deposit Quick Amounts (USD)",
                                            )
                                          }}
                                        </h5>
                                        <div class="gateway-quick-amounts">
                                          <div
                                            v-for="item in getGatewayQuickAmounts(
                                              gateway.gatewayKey,
                                              'deposit',
                                            )"
                                            :key="`deposit-qa-${item.id}`"
                                            class="gateway-quick-amount-chip"
                                          >
                                            <span>{{
                                              formatFundingValue(item.amount)
                                            }}</span>
                                            <button
                                              v-if="hasEditPermission"
                                              type="button"
                                              class="gateway-quick-amount-remove"
                                              :disabled="
                                                getGatewayQuickAmounts(
                                                  gateway.gatewayKey,
                                                  'deposit',
                                                ).length <= 1 ||
                                                quickAmountBusyKey ===
                                                  `${gateway.gatewayKey}-deposit-${item.id}`
                                              "
                                              :title="
                                                t(
                                                  'txnSettings_removeQuickAmount',
                                                  'Remove',
                                                )
                                              "
                                              @click="
                                                removeGatewayQuickAmount(
                                                  gateway,
                                                  'deposit',
                                                  item,
                                                )
                                              "
                                            >
                                              <i class="fas fa-times"></i>
                                            </button>
                                          </div>
                                        </div>
                                        <form
                                          v-if="hasEditPermission"
                                          class="gateway-quick-amount-add"
                                          @submit.prevent="
                                            addGatewayQuickAmount(
                                              gateway,
                                              'deposit',
                                              { silentIfEmpty: true },
                                            )
                                          "
                                        >
                                          <div
                                            class="form-group gateway-quick-amount-add-field"
                                          >
                                            <label class="form-label">{{
                                              t(
                                                "txnSettings_quickAmountPlaceholder",
                                                "Amount USD",
                                              )
                                            }}</label>
                                            <FormattedNumberInput
                                              v-model="
                                                quickAmountDrafts[
                                                  `${gateway.gatewayKey}-deposit`
                                                ]
                                              "
                                              :decimals="2"
                                              placeholder="0.00"
                                              input-class="form-input"
                                            />
                                          </div>
                                          <button
                                            type="submit"
                                            class="btn btn-primary gateway-quick-amount-add-btn"
                                            :disabled="
                                              quickAmountBusyKey ===
                                              `${gateway.gatewayKey}-deposit-add`
                                            "
                                          >
                                            <i class="fas fa-plus"></i>
                                            <span>{{
                                              t("common_add", "Add")
                                            }}</span>
                                          </button>
                                        </form>
                                      </div>

                                      <div class="gateway-funding-card">
                                        <h5>
                                          {{
                                            t(
                                              "txnSettings_withdrawQuickAmounts",
                                              "Withdraw Quick Amounts (USD)",
                                            )
                                          }}
                                        </h5>
                                        <div class="gateway-quick-amounts">
                                          <div
                                            v-for="item in getGatewayQuickAmounts(
                                              gateway.gatewayKey,
                                              'withdrawal',
                                            )"
                                            :key="`withdraw-qa-${item.id}`"
                                            class="gateway-quick-amount-chip"
                                          >
                                            <span>{{
                                              formatFundingValue(item.amount)
                                            }}</span>
                                            <button
                                              v-if="hasEditPermission"
                                              type="button"
                                              class="gateway-quick-amount-remove"
                                              :disabled="
                                                getGatewayQuickAmounts(
                                                  gateway.gatewayKey,
                                                  'withdrawal',
                                                ).length <= 1 ||
                                                quickAmountBusyKey ===
                                                  `${gateway.gatewayKey}-withdrawal-${item.id}`
                                              "
                                              :title="
                                                t(
                                                  'txnSettings_removeQuickAmount',
                                                  'Remove',
                                                )
                                              "
                                              @click="
                                                removeGatewayQuickAmount(
                                                  gateway,
                                                  'withdrawal',
                                                  item,
                                                )
                                              "
                                            >
                                              <i class="fas fa-times"></i>
                                            </button>
                                          </div>
                                        </div>
                                        <form
                                          v-if="hasEditPermission"
                                          class="gateway-quick-amount-add"
                                          @submit.prevent="
                                            addGatewayQuickAmount(
                                              gateway,
                                              'withdrawal',
                                              { silentIfEmpty: true },
                                            )
                                          "
                                        >
                                          <div
                                            class="form-group gateway-quick-amount-add-field"
                                          >
                                            <label class="form-label">{{
                                              t(
                                                "txnSettings_quickAmountPlaceholder",
                                                "Amount USD",
                                              )
                                            }}</label>
                                            <FormattedNumberInput
                                              v-model="
                                                quickAmountDrafts[
                                                  `${gateway.gatewayKey}-withdrawal`
                                                ]
                                              "
                                              :decimals="2"
                                              placeholder="0.00"
                                              input-class="form-input"
                                            />
                                          </div>
                                          <button
                                            type="submit"
                                            class="btn btn-primary gateway-quick-amount-add-btn"
                                            :disabled="
                                              quickAmountBusyKey ===
                                              `${gateway.gatewayKey}-withdrawal-add`
                                            "
                                          >
                                            <i class="fas fa-plus"></i>
                                            <span>{{
                                              t("common_add", "Add")
                                            }}</span>
                                          </button>
                                        </form>
                                      </div>
                                    </div>
                                  </div>

                                  <div v-else class="error-container compact">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <p>
                                      {{
                                        getGatewayFundingDetailState(
                                          gateway.gatewayKey,
                                        ).error ||
                                        t("txnSettings_err_loadFunding")
                                      }}
                                    </p>
                                  </div>
                                </div>

                                <div class="gateway-detail-section full-width">
                                  <div class="gateway-detail-section-header">
                                    <h3>
                                      <i class="fas fa-coins"></i>
                                      {{ t("txnSettings_section_fee") }}
                                    </h3>
                                  </div>

                                  <div
                                    v-if="
                                      getGatewayFundingDetailState(
                                        gateway.gatewayKey,
                                      ).loading
                                    "
                                    class="loading-container compact"
                                  >
                                    <i class="fas fa-spinner fa-spin"></i>
                                    <p>
                                      {{ t("txnSettings_loadingFeeSetting") }}
                                    </p>
                                  </div>

                                  <div
                                    v-else-if="
                                      getGatewayFundingDetailState(
                                        gateway.gatewayKey,
                                      ).data
                                    "
                                    class="gateway-funding-display"
                                  >
                                    <div
                                      class="gateway-funding-grid gateway-funding-grid--fees"
                                    >
                                      <div class="gateway-funding-card">
                                        <div
                                          class="gateway-funding-card-header"
                                        >
                                          <h5>
                                            {{
                                              t(
                                                "txnSettings_gatewayDetail_label_depositFee",
                                              )
                                            }}
                                          </h5>
                                          <button
                                            v-if="hasEditPermission"
                                            type="button"
                                            class="gateway-section-edit-btn"
                                            @click="
                                              openGatewayFeeModal(
                                                gateway,
                                                'deposit',
                                              )
                                            "
                                          >
                                            <i class="fas fa-edit"></i>
                                            <span>{{ t("common_edit") }}</span>
                                          </button>
                                        </div>
                                        <div class="gateway-funding-rules">
                                          <div
                                            v-for="(
                                              rule, index
                                            ) in getGatewayFundingDetailState(
                                              gateway.gatewayKey,
                                            ).data.depositRules"
                                            :key="`detail-deposit-rule-${gateway.gatewayKey}-${index}`"
                                            class="gateway-funding-rule"
                                          >
                                            <div
                                              v-if="
                                                getRuleRangeLabel(
                                                  getGatewayFundingDetailState(
                                                    gateway.gatewayKey,
                                                  ).data.depositRules,
                                                  index,
                                                  'deposit',
                                                )
                                              "
                                              class="gateway-funding-rule-range"
                                            >
                                              {{
                                                getRuleRangeLabel(
                                                  getGatewayFundingDetailState(
                                                    gateway.gatewayKey,
                                                  ).data.depositRules,
                                                  index,
                                                  "deposit",
                                                )
                                              }}
                                            </div>
                                            <div
                                              class="gateway-funding-rule-value"
                                            >
                                              {{ formatGatewayFeeRule(rule) }}
                                            </div>
                                          </div>
                                        </div>
                                      </div>

                                      <div class="gateway-funding-card">
                                        <div
                                          class="gateway-funding-card-header"
                                        >
                                          <h5>
                                            {{
                                              t(
                                                "txnSettings_gatewayDetail_label_withdrawFee",
                                              )
                                            }}
                                          </h5>
                                          <button
                                            v-if="hasEditPermission"
                                            type="button"
                                            class="gateway-section-edit-btn"
                                            @click="
                                              openGatewayFeeModal(
                                                gateway,
                                                'withdrawal',
                                              )
                                            "
                                          >
                                            <i class="fas fa-edit"></i>
                                            <span>{{ t("common_edit") }}</span>
                                          </button>
                                        </div>
                                        <div class="gateway-funding-rules">
                                          <div
                                            v-for="(
                                              rule, index
                                            ) in getGatewayFundingDetailState(
                                              gateway.gatewayKey,
                                            ).data.withdrawalRules"
                                            :key="`detail-withdraw-rule-${gateway.gatewayKey}-${index}`"
                                            class="gateway-funding-rule"
                                          >
                                            <div
                                              v-if="
                                                getRuleRangeLabel(
                                                  getGatewayFundingDetailState(
                                                    gateway.gatewayKey,
                                                  ).data.withdrawalRules,
                                                  index,
                                                  'withdrawal',
                                                )
                                              "
                                              class="gateway-funding-rule-range"
                                            >
                                              {{
                                                getRuleRangeLabel(
                                                  getGatewayFundingDetailState(
                                                    gateway.gatewayKey,
                                                  ).data.withdrawalRules,
                                                  index,
                                                  "withdrawal",
                                                )
                                              }}
                                            </div>
                                            <div
                                              class="gateway-funding-rule-value"
                                            >
                                              {{ formatGatewayFeeRule(rule) }}
                                            </div>
                                          </div>
                                        </div>
                                      </div>
                                    </div>
                                  </div>

                                  <div v-else class="error-container compact">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <p>
                                      {{
                                        getGatewayFundingDetailState(
                                          gateway.gatewayKey,
                                        ).error || t("txnSettings_err_loadFee")
                                      }}
                                    </p>
                                  </div>
                                </div>

                                <div class="gateway-detail-section full-width">
                                  <div class="gateway-detail-section-header">
                                    <h3>
                                      <i class="fas fa-file-alt"></i>
                                      {{ t("txnSettings_section_display") }}
                                    </h3>
                                  </div>

                                  <div
                                    v-if="
                                      getGatewayDisplayDetailState(
                                        gateway.gatewayKey,
                                      ).loading
                                    "
                                    class="loading-container compact"
                                  >
                                    <i class="fas fa-spinner fa-spin"></i>
                                    <p>
                                      {{
                                        t("txnSettings_loadingDisplaySetting")
                                      }}
                                    </p>
                                  </div>

                                  <div
                                    v-else-if="
                                      getGatewayDisplayDetailState(
                                        gateway.gatewayKey,
                                      ).data
                                    "
                                    class="gateway-display-preview-list"
                                  >
                                    <div class="gateway-display-preview-item">
                                      <div
                                        class="gateway-display-preview-header"
                                      >
                                        <span
                                          class="gateway-display-preview-label"
                                          >{{
                                            t(
                                              "txnSettings_label_depositDisplay",
                                            )
                                          }}</span
                                        >
                                        <button
                                          v-if="hasEditPermission"
                                          type="button"
                                          class="gateway-section-edit-btn"
                                          @click="
                                            openGatewayDisplayModal(
                                              gateway,
                                              'deposit',
                                            )
                                          "
                                        >
                                          <i class="fas fa-edit"></i>
                                          <span>{{ t("common_edit") }}</span>
                                        </button>
                                      </div>
                                      <div class="gateway-display-preview-card">
                                        <div
                                          v-if="
                                            String(
                                              getGatewayDisplayDetailState(
                                                gateway.gatewayKey,
                                              ).data.depositContent || '',
                                            ).trim()
                                          "
                                          class="gateway-display-preview-content"
                                          v-html="
                                            getGatewayDisplayDetailState(
                                              gateway.gatewayKey,
                                            ).data.depositContent
                                          "
                                        ></div>
                                        <p v-else class="gateway-display-empty">
                                          {{
                                            t(
                                              "txnSettings_empty_depositDisplay",
                                            )
                                          }}
                                        </p>
                                      </div>
                                    </div>
                                    <div class="gateway-display-preview-item">
                                      <div
                                        class="gateway-display-preview-header"
                                      >
                                        <span
                                          class="gateway-display-preview-label"
                                          >{{
                                            t(
                                              "txnSettings_label_withdrawalDisplay",
                                            )
                                          }}</span
                                        >
                                        <button
                                          v-if="hasEditPermission"
                                          type="button"
                                          class="gateway-section-edit-btn"
                                          @click="
                                            openGatewayDisplayModal(
                                              gateway,
                                              'withdrawal',
                                            )
                                          "
                                        >
                                          <i class="fas fa-edit"></i>
                                          <span>{{ t("common_edit") }}</span>
                                        </button>
                                      </div>
                                      <div class="gateway-display-preview-card">
                                        <div
                                          v-if="
                                            String(
                                              getGatewayDisplayDetailState(
                                                gateway.gatewayKey,
                                              ).data.withdrawalContent || '',
                                            ).trim()
                                          "
                                          class="gateway-display-preview-content"
                                          v-html="
                                            getGatewayDisplayDetailState(
                                              gateway.gatewayKey,
                                            ).data.withdrawalContent
                                          "
                                        ></div>
                                        <p v-else class="gateway-display-empty">
                                          {{
                                            t(
                                              "txnSettings_empty_withdrawalDisplay",
                                            )
                                          }}
                                        </p>
                                      </div>
                                    </div>
                                  </div>

                                  <div v-else class="error-container compact">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <p>
                                      {{
                                        getGatewayDisplayDetailState(
                                          gateway.gatewayKey,
                                        ).error ||
                                        t("txnSettings_err_loadDisplay")
                                      }}
                                    </p>
                                  </div>
                                </div>

                                <div class="gateway-detail-section full-width">
                                  <div class="gateway-detail-section-header">
                                    <h3>
                                      <i class="fas fa-circle-question"></i>
                                      {{ t("txnSettings_supportedQuestions") }}
                                    </h3>
                                  </div>

                                  <div
                                    v-if="
                                      getGatewayQuestionDetailState(
                                        gateway.gatewayKey,
                                      ).loading
                                    "
                                    class="loading-container compact"
                                  >
                                    <i class="fas fa-spinner fa-spin"></i>
                                    <p>
                                      {{
                                        t("txnSettings_loadingSupportQuestions")
                                      }}
                                    </p>
                                  </div>

                                  <div
                                    v-else-if="
                                      getGatewayQuestionDetailState(
                                        gateway.gatewayKey,
                                      ).data
                                    "
                                  >
                                    <GatewaySupportQuestionList
                                      :groups="
                                        getGatewayQuestionGroups(
                                          gateway.gatewayKey,
                                        )
                                      "
                                      :can-edit="hasEditPermission"
                                      @add-question="
                                        (group) =>
                                          handleGatewayQuestionAction(
                                            'add',
                                            gateway,
                                            group.key,
                                          )
                                      "
                                      @edit-question="
                                        (group, question) =>
                                          handleGatewayQuestionAction(
                                            'edit',
                                            gateway,
                                            group.key,
                                            question,
                                          )
                                      "
                                      @delete-question="
                                        (group, question) =>
                                          handleGatewayQuestionAction(
                                            'delete',
                                            gateway,
                                            group.key,
                                            question,
                                          )
                                      "
                                    />
                                  </div>

                                  <div v-else class="error-container compact">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <p>
                                      {{
                                        getGatewayQuestionDetailState(
                                          gateway.gatewayKey,
                                        ).error ||
                                        t("txnSettings_err_loadQuestions")
                                      }}
                                    </p>
                                  </div>
                                </div>
                              </div>
                            </div>
                          </td>
                        </tr>
                      </template>
                    </template>
                  </tbody>
                </table>
              </div>
            </div>

            <div
              v-if="showGatewayModal"
              class="modal-overlay"
              @click.self="closeGatewayModal"
            >
              <div class="modal-content gateway-modal-content">
                <div class="modal-header">
                  <h3>{{ t("txnSettings_modal_editGateway") }}</h3>
                  <button
                    type="button"
                    class="btn-close-modal"
                    @click="closeGatewayModal"
                  >
                    <i class="fas fa-times"></i>
                  </button>
                </div>
                <div class="modal-body">
                  <div class="gateway-modal-summary">
                    <div
                      :class="[
                        'gateway-icon-preview',
                        'large',
                        getGatewayIconPreviewClass(gatewayForm.type),
                      ]"
                    >
                      <i :class="gatewayForm.iconClass || 'fas fa-wallet'"></i>
                    </div>
                    <div>
                      <h4>
                        {{ gatewayForm.gatewayName || gatewayForm.gatewayKey }}
                      </h4>
                      <p>{{ gatewayForm.gatewayKey }}</p>
                    </div>
                  </div>

                  <div class="form-group">
                    <label class="form-label">{{
                      t("txnSettings_label_gatewayName")
                    }}</label>
                    <input
                      type="text"
                      class="form-input"
                      v-model="gatewayForm.gatewayName"
                      :placeholder="
                        t('txnSettings_ph_gatewayName', 'Enter gateway name')
                      "
                    />
                  </div>

                  <div class="form-group">
                    <label class="form-label">{{
                      t("txnSettings_label_type")
                    }}</label>
                    <select v-model="gatewayForm.type" class="form-input">
                      <option value="fiat">
                        {{ t("txnSettings_col_fiat") }}
                      </option>
                      <option value="crypto">
                        {{ t("txnSettings_col_crypto") }}
                      </option>
                    </select>
                  </div>

                  <div class="form-group">
                    <label class="form-label">{{
                      t("txnSettings_label_displayOrder", "Display Order")
                    }}</label>
                    <input
                      type="number"
                      min="0"
                      class="form-input"
                      v-model.number="gatewayForm.displayOrder"
                      :placeholder="
                        t('txnSettings_ph_displayOrder', 'Smaller shows first')
                      "
                    />
                  </div>

                  <div class="form-group">
                    <label class="form-label">{{
                      t("txnSettings_label_iconClass")
                    }}</label>
                    <div class="icon-input-wrapper">
                      <input
                        type="text"
                        class="form-input icon-input"
                        v-model="gatewayForm.iconClass"
                        :placeholder="t('txnSettings_ph_gatewayIcon')"
                      />
                      <div
                        :class="[
                          'icon-input-preview',
                          getGatewayIconPreviewClass(gatewayForm.type),
                        ]"
                      >
                        <i
                          :class="gatewayForm.iconClass || 'fas fa-wallet'"
                        ></i>
                      </div>
                    </div>
                  </div>

                  <div class="form-row">
                    <div class="form-group">
                      <label class="form-label">{{
                        t("txnSettings_label_gatewayStatus")
                      }}</label>
                      <label class="switch-toggle">
                        <input
                          type="checkbox"
                          v-model="gatewayForm.isEnabled"
                        />
                        <span class="switch-toggle-track">
                          <span class="switch-toggle-thumb"></span>
                        </span>
                        <span class="switch-toggle-label">{{
                          gatewayForm.isEnabled
                            ? t("txnSettings_statusEnabled")
                            : t("txnSettings_statusDisabled")
                        }}</span>
                      </label>
                    </div>
                  </div>

                  <div class="form-group">
                    <label class="form-label">{{
                      t("txnSettings_label_appId")
                    }}</label>
                    <input
                      type="text"
                      class="form-input"
                      v-model="gatewayForm.appId"
                      :placeholder="t('txnSettings_ph_appId')"
                    />
                  </div>

                  <div class="form-group">
                    <label class="form-label">{{
                      t("txnSettings_label_apiKey")
                    }}</label>
                    <input
                      type="password"
                      class="form-input"
                      v-model="gatewayForm.apiKey"
                      :placeholder="t('txnSettings_ph_apiKey')"
                    />
                  </div>

                  <div class="form-group">
                    <label class="form-label">{{
                      t("txnSettings_label_secretKey")
                    }}</label>
                    <input
                      type="password"
                      class="form-input"
                      v-model="gatewayForm.secretKey"
                      :placeholder="t('txnSettings_ph_secretKey')"
                    />
                  </div>

                  <div class="form-group">
                    <label class="form-label">{{
                      t("txnSettings_label_merchantName")
                    }}</label>
                    <input
                      type="text"
                      class="form-input"
                      v-model="gatewayForm.merchantName"
                      :placeholder="t('txnSettings_ph_merchantName')"
                    />
                  </div>

                  <div class="form-group">
                    <label class="form-label">{{
                      t(
                        "txnSettings_label_depositProcessingTime",
                        "Deposit Processing Time",
                      )
                    }}</label>
                    <input
                      type="text"
                      class="form-input"
                      v-model="gatewayForm.processingTime"
                      :placeholder="t('txnSettings_ph_processingTime')"
                    />
                  </div>

                  <div class="form-group">
                    <label class="form-label">{{
                      t(
                        "txnSettings_label_withdrawalProcessingTime",
                        "Withdrawal Processing Time",
                      )
                    }}</label>
                    <input
                      type="text"
                      class="form-input"
                      v-model="gatewayForm.withdrawalProcessingTime"
                      :placeholder="t('txnSettings_ph_processingTime')"
                    />
                  </div>

                  <div class="form-group">
                    <label class="form-label">{{
                      t("txnSettings_label_configData")
                    }}</label>
                    <textarea
                      class="form-textarea"
                      v-model="gatewayForm.configData"
                      rows="6"
                    ></textarea>
                    <span class="form-help">{{
                      t("txnSettings_help_configData")
                    }}</span>
                  </div>
                </div>
                <div class="modal-footer">
                  <button
                    type="button"
                    class="btn btn-secondary"
                    @click="closeGatewayModal"
                  >
                    {{ t("common_cancel") }}
                  </button>
                  <button
                    type="button"
                    class="btn btn-primary"
                    @click="saveGatewayEdit"
                    :disabled="saving"
                  >
                    <i
                      :class="saving ? 'fas fa-spinner fa-spin' : 'fas fa-save'"
                    ></i>
                    {{ saving ? t("txnSettings_saving") : t("common_save") }}
                  </button>
                </div>
              </div>
            </div>

            <div
              v-if="showGatewayCapabilityModal"
              class="modal-overlay"
              @click.self="closeGatewayCapabilityModal"
            >
              <div class="modal-content gateway-modal-content">
                <div class="modal-header">
                  <h3>{{ t("txnSettings_modal_availabilityCurrency") }}</h3>
                  <button
                    type="button"
                    class="btn-close-modal"
                    @click="closeGatewayCapabilityModal"
                  >
                    <i class="fas fa-times"></i>
                  </button>
                </div>
                <div class="modal-body">
                  <div class="gateway-modal-summary">
                    <div
                      :class="[
                        'gateway-icon-preview',
                        'large',
                        getGatewayIconPreviewClass(gatewayCapabilityForm.type),
                      ]"
                    >
                      <i
                        :class="
                          gatewayCapabilityForm.iconClass || 'fas fa-wallet'
                        "
                      ></i>
                    </div>
                    <div>
                      <h4>
                        {{
                          gatewayCapabilityForm.gatewayName ||
                          gatewayCapabilityForm.gatewayKey
                        }}
                      </h4>
                      <p>{{ gatewayCapabilityForm.gatewayKey }}</p>
                    </div>
                  </div>

                  <div class="form-row">
                    <div class="form-group">
                      <label class="form-label">{{
                        t("txnSettings_col_deposit")
                      }}</label>
                      <label class="switch-toggle">
                        <input
                          type="checkbox"
                          v-model="gatewayCapabilityForm.isDepositEnabled"
                        />
                        <span class="switch-toggle-track">
                          <span class="switch-toggle-thumb"></span>
                        </span>
                        <span class="switch-toggle-label">{{
                          gatewayCapabilityForm.isDepositEnabled
                            ? t("txnSettings_statusEnabled")
                            : t("txnSettings_statusDisabled")
                        }}</span>
                      </label>
                    </div>

                    <div class="form-group">
                      <label class="form-label">{{
                        t("txnSettings_col_withdrawal")
                      }}</label>
                      <label class="switch-toggle">
                        <input
                          type="checkbox"
                          v-model="gatewayCapabilityForm.isWithdrawalEnabled"
                        />
                        <span class="switch-toggle-track">
                          <span class="switch-toggle-thumb"></span>
                        </span>
                        <span class="switch-toggle-label">{{
                          gatewayCapabilityForm.isWithdrawalEnabled
                            ? t("txnSettings_statusEnabled")
                            : t("txnSettings_statusDisabled")
                        }}</span>
                      </label>
                    </div>
                  </div>

                  <div class="form-group">
                    <label class="form-label">{{
                      t("txnSettings_label_supportedFiat")
                    }}</label>
                    <CurrencyMultiSelectPanel
                      v-model="gatewayCapabilityForm.supportedFiatCurrencies"
                      :currencies="fiatCurrencyOptions"
                      :search-placeholder="t('txnSettings_searchFiatPh')"
                    />
                  </div>

                  <div class="form-group">
                    <label class="form-label">{{
                      t("txnSettings_label_supportedCrypto")
                    }}</label>
                    <CurrencyMultiSelectPanel
                      v-model="gatewayCapabilityForm.supportedCryptoCurrencies"
                      :currencies="cryptoCurrencyOptions"
                      :search-placeholder="t('txnSettings_searchCryptoPh')"
                    />
                  </div>
                </div>
                <div class="modal-footer">
                  <button
                    type="button"
                    class="btn btn-secondary"
                    @click="closeGatewayCapabilityModal"
                  >
                    {{ t("common_cancel") }}
                  </button>
                  <button
                    type="button"
                    class="btn btn-primary"
                    @click="saveGatewayCapabilityEdit"
                    :disabled="saving"
                  >
                    <i
                      :class="saving ? 'fas fa-spinner fa-spin' : 'fas fa-save'"
                    ></i>
                    {{ saving ? t("txnSettings_saving") : t("common_save") }}
                  </button>
                </div>
              </div>
            </div>

            <div
              v-if="showGatewayFundingModal"
              class="modal-overlay"
              @click.self="closeGatewayFundingModal"
            >
              <div class="modal-content gateway-modal-content">
                <div class="modal-header">
                  <h3>{{ t("txnSettings_modal_limitSetting") }}</h3>
                  <button
                    type="button"
                    class="btn-close-modal"
                    @click="closeGatewayFundingModal"
                  >
                    <i class="fas fa-times"></i>
                  </button>
                </div>
                <div class="modal-body">
                  <div class="gateway-modal-summary">
                    <div class="gateway-icon-preview large">
                      <i
                        :class="
                          fundingModalGateway.iconClass || 'fas fa-wallet'
                        "
                      ></i>
                    </div>
                    <div>
                      <h4>
                        {{
                          fundingModalGateway.gatewayName ||
                          fundingModalGateway.gatewayKey
                        }}
                      </h4>
                      <p>{{ fundingModalGateway.gatewayKey }}</p>
                    </div>
                  </div>

                  <div
                    v-if="gatewayFundingLoading"
                    class="loading-container compact"
                  >
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>{{ t("txnSettings_loadingLimitSetting") }}</p>
                  </div>

                  <div v-else class="gateway-fee-section standalone">
                    <div
                      class="gateway-fee-fields gateway-fee-fields-two-columns"
                    >
                      <div class="form-group">
                        <label class="form-label">{{
                          t("txnSettings_minDeposit")
                        }}</label>
                        <label class="checkbox-label fee-unlimited-toggle">
                          <input
                            type="checkbox"
                            :checked="
                              isUnlimitedValue(gatewayFundingForm.minDeposit)
                            "
                            @change="
                              toggleUnlimitedField(
                                gatewayFundingForm,
                                'minDeposit',
                                $event.target.checked,
                              )
                            "
                          />
                          <span>{{ t("txnSettings_unlimited") }}</span>
                        </label>
                        <FormattedNumberInput
                          v-if="
                            !isUnlimitedValue(gatewayFundingForm.minDeposit)
                          "
                          v-model="gatewayFundingForm.minDeposit"
                          :decimals="2"
                          placeholder="0.00"
                          input-class="form-input"
                        />
                      </div>

                      <div class="form-group">
                        <label class="form-label">{{
                          t("txnSettings_maxDeposit")
                        }}</label>
                        <label class="checkbox-label fee-unlimited-toggle">
                          <input
                            type="checkbox"
                            :checked="
                              isUnlimitedValue(gatewayFundingForm.maxDeposit)
                            "
                            @change="
                              toggleUnlimitedField(
                                gatewayFundingForm,
                                'maxDeposit',
                                $event.target.checked,
                              )
                            "
                          />
                          <span>{{ t("txnSettings_unlimited") }}</span>
                        </label>
                        <FormattedNumberInput
                          v-if="
                            !isUnlimitedValue(gatewayFundingForm.maxDeposit)
                          "
                          v-model="gatewayFundingForm.maxDeposit"
                          :decimals="2"
                          placeholder="0.00"
                          input-class="form-input"
                        />
                      </div>

                      <div class="form-group">
                        <label class="form-label">{{
                          t("txnSettings_minWithdrawal")
                        }}</label>
                        <label class="checkbox-label fee-unlimited-toggle">
                          <input
                            type="checkbox"
                            :checked="
                              isUnlimitedValue(gatewayFundingForm.minWithdrawal)
                            "
                            @change="
                              toggleUnlimitedField(
                                gatewayFundingForm,
                                'minWithdrawal',
                                $event.target.checked,
                              )
                            "
                          />
                          <span>{{ t("txnSettings_unlimited") }}</span>
                        </label>
                        <FormattedNumberInput
                          v-if="
                            !isUnlimitedValue(gatewayFundingForm.minWithdrawal)
                          "
                          v-model="gatewayFundingForm.minWithdrawal"
                          :decimals="2"
                          placeholder="0.00"
                          input-class="form-input"
                        />
                      </div>

                      <div class="form-group">
                        <label class="form-label">{{
                          t("txnSettings_maxWithdrawal")
                        }}</label>
                        <label class="checkbox-label fee-unlimited-toggle">
                          <input
                            type="checkbox"
                            :checked="
                              isUnlimitedValue(gatewayFundingForm.maxWithdrawal)
                            "
                            @change="
                              toggleUnlimitedField(
                                gatewayFundingForm,
                                'maxWithdrawal',
                                $event.target.checked,
                              )
                            "
                          />
                          <span>{{ t("txnSettings_unlimited") }}</span>
                        </label>
                        <FormattedNumberInput
                          v-if="
                            !isUnlimitedValue(gatewayFundingForm.maxWithdrawal)
                          "
                          v-model="gatewayFundingForm.maxWithdrawal"
                          :decimals="2"
                          placeholder="0.00"
                          input-class="form-input"
                        />
                      </div>
                    </div>
                  </div>
                </div>
                <div class="modal-footer">
                  <button
                    type="button"
                    class="btn btn-secondary"
                    @click="closeGatewayFundingModal"
                  >
                    {{ t("common_cancel") }}
                  </button>
                  <button
                    type="button"
                    class="btn btn-primary"
                    @click="saveGatewayFunding"
                    :disabled="
                      gatewayFundingLoading ||
                      !isGatewayFundingFormValid ||
                      saving
                    "
                  >
                    <i
                      :class="saving ? 'fas fa-spinner fa-spin' : 'fas fa-save'"
                    ></i>
                    {{ saving ? t("txnSettings_saving") : t("common_save") }}
                  </button>
                </div>
              </div>
            </div>

            <div
              v-if="showGatewayFeeModal"
              class="modal-overlay"
              @click.self="closeGatewayFeeModal"
            >
              <div class="modal-content gateway-modal-content">
                <div class="modal-header">
                  <h3>
                    {{
                      gatewayFeeModalType === "withdrawal"
                        ? t("txnSettings_modal_withdrawFee")
                        : t("txnSettings_modal_depositFee")
                    }}
                  </h3>
                  <button
                    type="button"
                    class="btn-close-modal"
                    @click="closeGatewayFeeModal"
                  >
                    <i class="fas fa-times"></i>
                  </button>
                </div>
                <div class="modal-body">
                  <div class="gateway-modal-summary">
                    <div class="gateway-icon-preview large">
                      <i
                        :class="
                          fundingModalGateway.iconClass || 'fas fa-wallet'
                        "
                      ></i>
                    </div>
                    <div>
                      <h4>
                        {{
                          fundingModalGateway.gatewayName ||
                          fundingModalGateway.gatewayKey
                        }}
                      </h4>
                      <p>{{ fundingModalGateway.gatewayKey }}</p>
                    </div>
                  </div>

                  <div
                    v-if="gatewayFundingLoading"
                    class="loading-container compact"
                  >
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>{{ t("txnSettings_loadingFeeModal") }}</p>
                  </div>

                  <div v-else class="gateway-fee-section standalone">
                    <div class="gateway-fee-card">
                      <h5>
                        {{
                          gatewayFeeModalType === "withdrawal"
                            ? t("txnSettings_withdrawFeeRules")
                            : t("txnSettings_depositFeeRules")
                        }}
                      </h5>
                      <p class="gateway-fee-description">
                        {{ t("txnSettings_feeByRangeHelp") }}
                      </p>

                      <div class="gateway-fee-fields">
                        <div
                          v-for="(rule, index) in activeGatewayFeeRules"
                          :key="`${gatewayFeeModalType}-rule-${index}`"
                          class="gateway-rule-item"
                        >
                          <div class="gateway-rule-header">
                            <div class="gateway-rule-header-main">
                              <strong>{{
                                tParams("txnSettings_ruleN", "Rule {n}", {
                                  n: index + 1,
                                })
                              }}</strong>
                              <span
                                v-if="
                                  getRuleRangeLabel(
                                    activeGatewayFeeRules,
                                    index,
                                    gatewayFeeModalType,
                                  )
                                "
                                class="gateway-rule-range"
                              >
                                {{
                                  getRuleRangeLabel(
                                    activeGatewayFeeRules,
                                    index,
                                    gatewayFeeModalType,
                                  )
                                }}
                              </span>
                            </div>
                            <button
                              v-if="activeGatewayFeeRules.length > 1"
                              type="button"
                              class="btn-icon btn-danger"
                              @click="
                                removeGatewayRule(gatewayFeeModalType, index)
                              "
                              :title="t('txnSettings_title_removeRule')"
                            >
                              <i class="fas fa-trash"></i>
                            </button>
                          </div>

                          <div
                            class="gateway-fee-fields gateway-fee-fields-two-columns"
                          >
                            <div v-if="index > 0" class="form-group">
                              <label class="form-label">{{
                                t("txnSettings_label_ruleStartsAt")
                              }}</label>
                              <FormattedNumberInput
                                v-model="rule.thresholdAmount"
                                :decimals="2"
                                placeholder="0.00"
                                input-class="form-input"
                              />
                            </div>

                            <div class="form-group">
                              <label class="form-label">{{
                                t("txnSettings_label_feePolicy")
                              }}</label>
                              <select v-model="rule.feeMode" class="form-input">
                                <option value="none">
                                  {{ t("txnSettings_feeMode_none") }}
                                </option>
                                <option value="dynamic">
                                  {{ t("txnSettings_feeMode_dynamic") }}
                                </option>
                                <option value="fixed">
                                  {{ t("txnSettings_feeMode_fixed") }}
                                </option>
                              </select>
                            </div>

                            <div
                              v-if="rule.feeMode === 'dynamic'"
                              class="form-group"
                            >
                              <label class="form-label">{{
                                t("txnSettings_label_percentage")
                              }}</label>
                              <FormattedNumberInput
                                v-model="rule.percentage"
                                :decimals="2"
                                placeholder="0.00"
                                input-class="form-input"
                              />
                            </div>

                            <div
                              v-if="rule.feeMode === 'fixed'"
                              class="form-group"
                            >
                              <label class="form-label">{{
                                t("txnSettings_label_fixedFee")
                              }}</label>
                              <FormattedNumberInput
                                v-model="rule.fixed"
                                :decimals="4"
                                placeholder="0.0000"
                                input-class="form-input"
                              />
                            </div>

                            <div
                              v-if="rule.feeMode === 'dynamic'"
                              class="form-group"
                            >
                              <label class="form-label">{{
                                t("txnSettings_label_minFee")
                              }}</label>
                              <label
                                class="checkbox-label fee-unlimited-toggle"
                              >
                                <input
                                  type="checkbox"
                                  :checked="isUnlimitedValue(rule.minFee)"
                                  @change="
                                    toggleUnlimitedField(
                                      rule,
                                      'minFee',
                                      $event.target.checked,
                                    )
                                  "
                                />
                                <span>{{ t("txnSettings_unlimited") }}</span>
                              </label>
                              <FormattedNumberInput
                                v-if="!isUnlimitedValue(rule.minFee)"
                                v-model="rule.minFee"
                                :decimals="4"
                                placeholder="0.0000"
                                input-class="form-input"
                              />
                            </div>

                            <div
                              v-if="rule.feeMode === 'dynamic'"
                              class="form-group"
                            >
                              <label class="form-label">{{
                                t("txnSettings_label_maxFee")
                              }}</label>
                              <label
                                class="checkbox-label fee-unlimited-toggle"
                              >
                                <input
                                  type="checkbox"
                                  :checked="isUnlimitedValue(rule.maxFee)"
                                  @change="
                                    toggleUnlimitedField(
                                      rule,
                                      'maxFee',
                                      $event.target.checked,
                                    )
                                  "
                                />
                                <span>{{ t("txnSettings_unlimited") }}</span>
                              </label>
                              <FormattedNumberInput
                                v-if="!isUnlimitedValue(rule.maxFee)"
                                v-model="rule.maxFee"
                                :decimals="4"
                                placeholder="0.0000"
                                input-class="form-input"
                              />
                            </div>
                          </div>
                        </div>
                      </div>

                      <button
                        type="button"
                        class="btn btn-secondary gateway-rule-add"
                        @click="addGatewayRule(gatewayFeeModalType)"
                      >
                        <i class="fas fa-plus"></i>
                        {{
                          gatewayFeeModalType === "withdrawal"
                            ? t("txnSettings_addWithdrawRule")
                            : t("txnSettings_addDepositRule")
                        }}
                      </button>
                    </div>
                  </div>
                </div>
                <div class="modal-footer">
                  <button
                    type="button"
                    class="btn btn-secondary"
                    @click="closeGatewayFeeModal"
                  >
                    {{ t("common_cancel") }}
                  </button>
                  <button
                    type="button"
                    class="btn btn-primary"
                    @click="saveGatewayFunding"
                    :disabled="
                      gatewayFundingLoading ||
                      !isGatewayFundingFormValid ||
                      saving
                    "
                  >
                    <i
                      :class="saving ? 'fas fa-spinner fa-spin' : 'fas fa-save'"
                    ></i>
                    {{ saving ? t("txnSettings_saving") : t("common_save") }}
                  </button>
                </div>
              </div>
            </div>

            <div
              v-if="showGatewayDisplayModal"
              class="modal-overlay"
              @click.self="closeGatewayDisplayModal"
            >
              <div
                class="modal-content gateway-modal-content gateway-display-modal-content"
              >
                <div class="modal-header">
                  <h3>
                    {{
                      displayModalType === "withdrawal"
                        ? t("txnSettings_modal_withdrawDisplay")
                        : t("txnSettings_modal_depositDisplay")
                    }}
                  </h3>
                  <button
                    type="button"
                    class="btn-close-modal"
                    @click="closeGatewayDisplayModal"
                  >
                    <i class="fas fa-times"></i>
                  </button>
                </div>
                <div class="modal-body">
                  <div class="gateway-modal-summary">
                    <div class="gateway-icon-preview large">
                      <i
                        :class="
                          displayModalGateway.iconClass || 'fas fa-wallet'
                        "
                      ></i>
                    </div>
                    <div>
                      <h4>
                        {{
                          displayModalGateway.gatewayName ||
                          displayModalGateway.gatewayKey
                        }}
                      </h4>
                      <p>{{ displayModalGateway.gatewayKey }}</p>
                    </div>
                  </div>

                  <div
                    v-if="gatewayDisplayLoading"
                    class="loading-container compact"
                  >
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>{{ t("txnSettings_loadingDisplayModal") }}</p>
                  </div>

                  <div v-else class="gateway-display-editor">
                    <div class="form-group">
                      <label class="form-label">
                        {{
                          displayModalType === "withdrawal"
                            ? t("txnSettings_label_withdrawDisplayContent")
                            : t("txnSettings_label_depositDisplayContent")
                        }}
                      </label>
                      <RichTextInput
                        v-model="currentGatewayDisplayContent"
                        :placeholder="
                          displayModalType === 'withdrawal'
                            ? t('txnSettings_ph_withdrawDisplay')
                            : t('txnSettings_ph_depositDisplay')
                        "
                      />
                    </div>
                  </div>
                </div>
                <div class="modal-footer">
                  <button
                    type="button"
                    class="btn btn-secondary"
                    @click="closeGatewayDisplayModal"
                  >
                    {{ t("common_cancel") }}
                  </button>
                  <button
                    type="button"
                    class="btn btn-primary"
                    @click="saveGatewayDisplayContent"
                    :disabled="gatewayDisplayLoading || saving"
                  >
                    <i
                      :class="saving ? 'fas fa-spinner fa-spin' : 'fas fa-save'"
                    ></i>
                    {{ saving ? t("txnSettings_saving") : t("common_save") }}
                  </button>
                </div>
              </div>
            </div>

            <GatewaySupportQuestionModal
              v-if="showGatewayQuestionModal"
              :question="gatewayQuestionModalQuestion"
              :scope="gatewayQuestionModalScope"
              @close="closeGatewayQuestionModal"
              @save="saveGatewayQuestion"
            />
          </div>
        </transition>
      </div>

      <!-- Exchange Rates Settings -->
      <div class="settings-card">
        <div class="card-header" @click="toggleCard('exchangeRates')">
          <div class="card-header-content">
            <h2>
              <i class="fas fa-exchange-alt"></i>
              {{ t("txnSettings_exchangeRatesTitle") }}
            </h2>
            <p>{{ t("txnSettings_exchangeRatesSub") }}</p>
          </div>
          <i
            :class="[
              'fas fa-chevron-down card-collapse-icon',
              { rotated: !collapsedCards.exchangeRates },
            ]"
          ></i>
        </div>

        <transition name="card-expand">
          <div v-show="!collapsedCards.exchangeRates" class="card-body">
            <div class="info-banner">
              <div class="info-banner-content">
                <i class="fas fa-info-circle info-banner-icon"></i>
                <div class="info-banner-text">
                  <strong>{{ t("txnSettings_exchangeRatesLabel") }}:</strong>
                  {{ t("txnSettings_exchangeRatesBanner") }}
                </div>
              </div>
            </div>

            <!-- Exchange Rates List -->
            <div class="exchange-rates-list">
              <div class="list-header">
                <h3>
                  <i class="fas fa-list"></i>
                  {{ t("txnSettings_exchangeRatesListTitle") }}
                </h3>
                <button
                  v-if="hasEditPermission"
                  type="button"
                  class="btn-add-item"
                  @click="showAddRateModal = true"
                >
                  <i class="fas fa-plus"></i>
                  {{ t("txnSettings_addExchangeRate") }}
                </button>
              </div>

              <div v-if="exchangeRates.length === 0" class="empty-state">
                <i class="fas fa-info-circle"></i>
                <p>{{ t("txnSettings_noExchangeRates") }}</p>
              </div>

              <div v-else class="rates-table">
                <table>
                  <thead>
                    <tr>
                      <th>{{ t("txnSettings_col_type") }}</th>
                      <th>{{ t("txnSettings_col_currencyCode") }}</th>
                      <th>{{ t("txnSettings_col_exchangeRate") }}</th>
                      <th>{{ t("txnSettings_col_syncMode") }}</th>
                      <th>{{ t("txnSettings_col_deposit") }}</th>
                      <th>{{ t("txnSettings_col_withdraw") }}</th>
                      <th>{{ t("common_status") }}</th>
                      <th v-if="hasEditPermission">
                        {{ t("common_actions") }}
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="rate in exchangeRates"
                      :key="rate.id"
                      :class="{ inactive: !rate.isActive }"
                    >
                      <td>
                        <span
                          :class="[
                            'type-badge',
                            (rate.type || 'fiat').toLowerCase(),
                          ]"
                        >
                          {{ (rate.type || "fiat").toUpperCase() }}
                        </span>
                      </td>
                      <td>
                        <strong>{{ rate.currencyCode }}</strong>
                      </td>
                      <td>
                        {{
                          formatExchangeRateLine(
                            rate.exchangeRate,
                            rate.currencyCode,
                          )
                        }}
                      </td>
                      <td>
                        <span
                          :class="['sync-mode-badge', rate.syncMode || 'auto']"
                        >
                          <i
                            :class="
                              (rate.syncMode || 'auto') === 'auto'
                                ? 'fas fa-sync-alt'
                                : 'fas fa-hand-paper'
                            "
                          ></i>
                          {{
                            (rate.syncMode || "auto") === "auto"
                              ? t("txnSettings_syncMode_auto")
                              : t("txnSettings_syncMode_manual")
                          }}
                        </span>
                        <div
                          v-if="(rate.syncMode || 'auto') === 'auto'"
                          class="sync-updated-at"
                          :class="{ 'sync-stale': isRateSyncStale(rate) }"
                        >
                          <i
                            v-if="isRateSyncStale(rate)"
                            class="fas fa-exclamation-circle"
                          ></i>
                          {{
                            rate.lastSyncedAt
                              ? formatSyncUpdatedAt(rate.lastSyncedAt)
                              : t("txnSettings_syncMode_neverSynced")
                          }}
                        </div>
                      </td>
                      <td>
                        <span>{{
                          formatAdjustedExchangeRateValue(
                            rate.exchangeRate,
                            rate.depositBias,
                            rate.depositType,
                          )
                        }}</span>
                        <span
                          :class="[
                            'exchange-rate-bias',
                            getExchangeRateBiasClass(rate.depositBias),
                          ]"
                        >
                          {{
                            formatExchangeRateBias(
                              rate.depositBias,
                              rate.depositType,
                            )
                          }}
                        </span>
                      </td>
                      <td>
                        <span>{{
                          formatAdjustedExchangeRateValue(
                            rate.exchangeRate,
                            rate.withdrawBias,
                            rate.withdrawType,
                          )
                        }}</span>
                        <span
                          :class="[
                            'exchange-rate-bias',
                            getExchangeRateBiasClass(rate.withdrawBias),
                          ]"
                        >
                          {{
                            formatExchangeRateBias(
                              rate.withdrawBias,
                              rate.withdrawType,
                            )
                          }}
                        </span>
                      </td>
                      <td>
                        <span
                          :class="[
                            'status-badge',
                            rate.isActive ? 'active' : 'inactive',
                          ]"
                        >
                          {{
                            rate.isActive
                              ? t("txnSettings_active")
                              : t("txnSettings_inactive")
                          }}
                        </span>
                      </td>
                      <td v-if="hasEditPermission">
                        <div class="action-buttons">
                          <button
                            type="button"
                            class="btn-icon"
                            @click="editExchangeRate(rate)"
                            :title="t('common_edit')"
                          >
                            <i class="fas fa-edit"></i>
                          </button>
                          <button
                            type="button"
                            class="btn-icon"
                            @click="openRateSettingsModal(rate)"
                            :title="t('txnSettings_title_editRateSettings')"
                          >
                            <i class="fas fa-sliders-h"></i>
                          </button>
                          <button
                            type="button"
                            class="btn-icon"
                            @click="toggleRateSyncMode(rate)"
                            :disabled="saving"
                            :title="t('txnSettings_syncMode_toggleHint')"
                          >
                            <i
                              :class="
                                (rate.syncMode || 'auto') === 'auto'
                                  ? 'fas fa-sync-alt'
                                  : 'fas fa-hand-paper'
                              "
                            ></i>
                          </button>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Add/Edit Rate Modal -->
            <div
              v-if="showAddRateModal || editingRate"
              class="modal-overlay"
              @click.self="closeRateModal"
            >
              <div class="modal-content">
                <div class="modal-header">
                  <h3>
                    {{
                      editingRate
                        ? t("txnSettings_modal_editRate")
                        : t("txnSettings_modal_addRate")
                    }}
                  </h3>
                  <button
                    type="button"
                    class="btn-close-modal"
                    @click="closeRateModal"
                  >
                    <i class="fas fa-times"></i>
                  </button>
                </div>
                <div class="modal-body">
                  <div class="form-group">
                    <label class="form-label">
                      {{ t("txnSettings_label_rateType") }}
                      <span class="required">*</span>
                    </label>
                    <select class="form-input" v-model="rateForm.type">
                      <option value="fiat">
                        {{ t("txnSettings_col_fiat") }}
                      </option>
                      <option value="crypto">
                        {{ t("txnSettings_col_crypto") }}
                      </option>
                    </select>
                    <span class="form-help">{{
                      t("txnSettings_help_rateType")
                    }}</span>
                  </div>

                  <div class="form-group">
                    <label class="form-label">
                      {{ t("txnSettings_label_currencyCode") }}
                      <span class="required">*</span>
                    </label>
                    <input
                      type="text"
                      class="form-input"
                      v-model="rateForm.currencyCode"
                      :placeholder="t('txnSettings_ph_currencyCode')"
                      :disabled="!!editingRate"
                      @input="
                        rateForm.currencyCode =
                          rateForm.currencyCode.toUpperCase()
                      "
                    />
                    <span class="form-help">{{
                      t("txnSettings_help_currencyCode")
                    }}</span>
                  </div>

                  <div class="form-group">
                    <label class="form-label">
                      {{ t("txnSettings_label_currencyName") }}
                    </label>
                    <input
                      type="text"
                      class="form-input"
                      v-model="rateForm.currencyName"
                      :placeholder="t('txnSettings_ph_currencyName')"
                    />
                    <span class="form-help">{{
                      t("txnSettings_help_currencyName")
                    }}</span>
                  </div>

                  <div class="form-group">
                    <label class="form-label">
                      {{ t("txnSettings_label_currencySymbol") }}
                      <span class="required">*</span>
                    </label>
                    <input
                      type="text"
                      class="form-input"
                      v-model="rateForm.currencySymbol"
                      placeholder="$"
                      maxlength="10"
                    />
                    <span class="form-help">{{
                      t("txnSettings_help_currencySymbol")
                    }}</span>
                  </div>

                  <div v-if="!editingRate" class="form-group">
                    <label class="form-label">
                      {{ t("txnSettings_label_exchangeRate") }}
                      <span class="required">*</span>
                    </label>
                    <FormattedNumberInput
                      v-model="rateForm.exchangeRate"
                      :decimals="8"
                      placeholder="150.00"
                      input-class="form-input"
                    />
                    <span class="form-help">{{
                      tParams(
                        "txnSettings_help_exchangeRateLine",
                        "1 USD = X {code}",
                        {
                          code:
                            rateForm.currencyCode ||
                            t("txnSettings_targetCurrencyPlaceholder"),
                        },
                      )
                    }}</span>
                  </div>
                </div>
                <div class="modal-footer">
                  <button
                    type="button"
                    class="btn btn-secondary"
                    @click="closeRateModal"
                  >
                    {{ t("common_cancel") }}
                  </button>
                  <button
                    type="button"
                    class="btn btn-primary"
                    @click="saveExchangeRate"
                    :disabled="!isRateFormValid || saving"
                  >
                    <i
                      :class="saving ? 'fas fa-spinner fa-spin' : 'fas fa-save'"
                    ></i>
                    {{ saving ? t("txnSettings_saving") : t("common_save") }}
                  </button>
                </div>
              </div>
            </div>

            <div
              v-if="showRateSettingsModal && editingRateSettings"
              class="modal-overlay"
              @click.self="closeRateSettingsModal"
            >
              <div class="modal-content">
                <div class="modal-header">
                  <h3>{{ t("txnSettings_modal_editRateSettings") }}</h3>
                  <button
                    type="button"
                    class="btn-close-modal"
                    @click="closeRateSettingsModal"
                  >
                    <i class="fas fa-times"></i>
                  </button>
                </div>
                <div class="modal-body">
                  <div class="form-group">
                    <label class="form-label">
                      {{ t("txnSettings_label_exchangeRate") }}
                      <span class="required">*</span>
                    </label>
                    <FormattedNumberInput
                      v-model="rateSettingsForm.exchangeRate"
                      :decimals="8"
                      placeholder="150.00"
                      :input-class="[
                        'form-input',
                        { 'input-error': rateSettingsErrors.exchangeRate },
                      ]"
                      @update:modelValue="handleRateSettingsExchangeRateChange"
                    />
                    <span class="form-help">
                      {{
                        tParams(
                          "txnSettings_help_exchangeRateLine",
                          "1 USD = X {code}",
                          {
                            code:
                              editingRateSettings.currencyCode ||
                              t("txnSettings_targetCurrencyPlaceholder"),
                          },
                        )
                      }}
                    </span>
                    <span
                      v-if="rateSettingsErrors.exchangeRate"
                      class="form-error"
                      >{{ rateSettingsErrors.exchangeRate }}</span
                    >
                  </div>

                  <div class="rate-config-section">
                    <div class="rate-config-section-header">
                      <h4>{{ t("txnSettings_depositRateSettingsTitle") }}</h4>
                    </div>
                    <div class="form-row">
                      <div class="form-group">
                        <label class="form-label">{{
                          t("txnSettings_label_rateAdjustmentType")
                        }}</label>
                        <select
                          v-model="rateSettingsForm.depositType"
                          class="form-input"
                          @change="handleRateSettingsTypeChange('deposit')"
                        >
                          <option value="fixed">
                            {{ t("txnSettings_feeMode_fixed") }}
                          </option>
                          <option value="dynamic">
                            {{ t("txnSettings_feeMode_dynamic") }}
                          </option>
                        </select>
                      </div>

                      <div class="form-group">
                        <label class="form-label">
                          {{
                            rateSettingsForm.depositType === "dynamic"
                              ? t("txnSettings_label_rateBiasPercent")
                              : t("txnSettings_label_rateBias")
                          }}
                        </label>
                        <input
                          v-model.number="rateSettingsForm.depositBias"
                          type="number"
                          :class="[
                            'form-input',
                            { 'input-error': rateSettingsErrors.depositBias },
                          ]"
                          :placeholder="
                            rateSettingsForm.depositType === 'dynamic'
                              ? '0.00'
                              : '0.0000'
                          "
                          step="any"
                          @input="handleRateSettingsBiasChange('deposit')"
                        />
                        <span class="form-help">{{
                          getExchangeRateBiasHelp(rateSettingsForm.depositType)
                        }}</span>
                        <span
                          v-if="rateSettingsErrors.depositBias"
                          class="form-error"
                          >{{ rateSettingsErrors.depositBias }}</span
                        >
                      </div>

                      <div class="form-group">
                        <label class="form-label">{{
                          t("txnSettings_label_rateTarget")
                        }}</label>
                        <input
                          v-model.number="rateSettingsForm.depositTarget"
                          type="number"
                          :class="[
                            'form-input',
                            { 'input-error': rateSettingsErrors.depositTarget },
                          ]"
                          placeholder="0.0000"
                          step="any"
                          @input="handleRateSettingsTargetChange('deposit')"
                        />
                        <span class="form-help">{{
                          t("txnSettings_help_exchangeRateTarget")
                        }}</span>
                        <span
                          v-if="rateSettingsErrors.depositTarget"
                          class="form-error"
                          >{{ rateSettingsErrors.depositTarget }}</span
                        >
                      </div>
                    </div>
                  </div>

                  <div class="rate-config-section">
                    <div class="rate-config-section-header">
                      <h4>{{ t("txnSettings_withdrawRateSettingsTitle") }}</h4>
                    </div>
                    <div class="form-row">
                      <div class="form-group">
                        <label class="form-label">{{
                          t("txnSettings_label_rateAdjustmentType")
                        }}</label>
                        <select
                          v-model="rateSettingsForm.withdrawType"
                          class="form-input"
                          @change="handleRateSettingsTypeChange('withdraw')"
                        >
                          <option value="fixed">
                            {{ t("txnSettings_feeMode_fixed") }}
                          </option>
                          <option value="dynamic">
                            {{ t("txnSettings_feeMode_dynamic") }}
                          </option>
                        </select>
                      </div>

                      <div class="form-group">
                        <label class="form-label">
                          {{
                            rateSettingsForm.withdrawType === "dynamic"
                              ? t("txnSettings_label_rateBiasPercent")
                              : t("txnSettings_label_rateBias")
                          }}
                        </label>
                        <input
                          v-model.number="rateSettingsForm.withdrawBias"
                          type="number"
                          :class="[
                            'form-input',
                            { 'input-error': rateSettingsErrors.withdrawBias },
                          ]"
                          :placeholder="
                            rateSettingsForm.withdrawType === 'dynamic'
                              ? '0.00'
                              : '0.0000'
                          "
                          step="any"
                          @input="handleRateSettingsBiasChange('withdraw')"
                        />
                        <span class="form-help">{{
                          getExchangeRateBiasHelp(rateSettingsForm.withdrawType)
                        }}</span>
                        <span
                          v-if="rateSettingsErrors.withdrawBias"
                          class="form-error"
                          >{{ rateSettingsErrors.withdrawBias }}</span
                        >
                      </div>

                      <div class="form-group">
                        <label class="form-label">{{
                          t("txnSettings_label_rateTarget")
                        }}</label>
                        <input
                          v-model.number="rateSettingsForm.withdrawTarget"
                          type="number"
                          :class="[
                            'form-input',
                            {
                              'input-error': rateSettingsErrors.withdrawTarget,
                            },
                          ]"
                          placeholder="0.0000"
                          step="any"
                          @input="handleRateSettingsTargetChange('withdraw')"
                        />
                        <span class="form-help">{{
                          t("txnSettings_help_exchangeRateTarget")
                        }}</span>
                        <span
                          v-if="rateSettingsErrors.withdrawTarget"
                          class="form-error"
                          >{{ rateSettingsErrors.withdrawTarget }}</span
                        >
                      </div>
                    </div>
                  </div>
                </div>
                <div class="modal-footer">
                  <button
                    type="button"
                    class="btn btn-secondary"
                    @click="closeRateSettingsModal"
                  >
                    {{ t("common_cancel") }}
                  </button>
                  <button
                    type="button"
                    class="btn btn-primary"
                    @click="saveRateSettings"
                    :disabled="!isRateSettingsFormValid || saving"
                  >
                    <i
                      :class="saving ? 'fas fa-spinner fa-spin' : 'fas fa-save'"
                    ></i>
                    {{ saving ? t("txnSettings_saving") : t("common_save") }}
                  </button>
                </div>
              </div>
            </div>

            <div class="card-footer card-footer-full">
              <div class="info-box">
                <p>
                  <strong
                    ><i class="fas fa-info-circle"></i>
                    {{ t("txnSettings_noteLabel") }}:</strong
                  >
                  {{ t("txnSettings_ratesFooterNote") }}
                </p>
              </div>
            </div>
          </div>
        </transition>
      </div>

      <!-- Display Contents -->
      <div class="settings-card">
        <div class="card-header" @click="toggleCard('displayContents')">
          <div class="card-header-content">
            <h2>
              <i class="fas fa-window-maximize"></i>
              {{ t("txnSettings_displayContentsTitle") }}
            </h2>
            <p>{{ t("txnSettings_displayContentsSub") }}</p>
          </div>
          <i
            :class="[
              'fas fa-chevron-down card-collapse-icon',
              { rotated: !collapsedCards.displayContents },
            ]"
          ></i>
        </div>

        <transition name="card-expand">
          <div v-show="!collapsedCards.displayContents" class="card-body">
            <div class="settings-section">
              <div class="section-header">
                <div>
                  <h3>
                    <i class="fas fa-arrow-down"></i>
                    {{ t("txnSettings_depositSectionTitle") }}
                  </h3>
                  <p class="section-description">
                    {{ t("txnSettings_depositSectionDesc") }}
                  </p>
                </div>
                <button
                  v-if="hasEditPermission"
                  type="button"
                  class="btn-add-item"
                  @click="addDisplayContentItem('deposit')"
                >
                  <i class="fas fa-plus"></i> {{ t("txnSettings_addItem") }}
                </button>
              </div>

              <div
                v-if="displayContentForms.deposit.items.length === 0"
                class="empty-tips"
              >
                <i class="fas fa-align-left"></i>
                <p>{{ t("txnSettings_noDepositDisplayItems") }}</p>
              </div>

              <div
                v-for="(item, index) in displayContentForms.deposit.items"
                :key="`deposit-${index}`"
                class="tip-item-editor display-content-item"
              >
                <div class="tip-item-header">
                  <span class="tip-number">{{
                    tParams("txnSettings_itemN", "Item {n}", { n: index + 1 })
                  }}</span>
                  <button
                    v-if="hasEditPermission"
                    type="button"
                    class="btn-remove-item"
                    @click="removeDisplayContentItem('deposit', index)"
                  >
                    <i class="fas fa-trash"></i> {{ t("txnSettings_remove") }}
                  </button>
                </div>

                <div class="form-group">
                  <label class="form-label">{{
                    t("txnSettings_label_content")
                  }}</label>
                  <textarea
                    v-model="item.content"
                    class="form-textarea"
                    rows="1"
                    :disabled="!hasEditPermission"
                  ></textarea>
                </div>
              </div>

              <div class="section-actions" v-if="hasEditPermission">
                <button
                  type="button"
                  class="btn btn-primary"
                  :disabled="saving"
                  @click="saveDisplayContent('deposit')"
                >
                  <i
                    :class="saving ? 'fas fa-spinner fa-spin' : 'fas fa-save'"
                  ></i>
                  {{
                    saving
                      ? t("txnSettings_saving")
                      : t("txnSettings_saveDepositContent")
                  }}
                </button>
              </div>
            </div>

            <div class="settings-section">
              <div class="section-header">
                <div>
                  <h3>
                    <i class="fas fa-arrow-up"></i>
                    {{ t("txnSettings_withdrawalSectionTitle") }}
                  </h3>
                  <p class="section-description">
                    {{ t("txnSettings_withdrawalSectionDesc") }}
                  </p>
                </div>
                <button
                  v-if="hasEditPermission"
                  type="button"
                  class="btn-add-item"
                  @click="addDisplayContentItem('withdrawal')"
                >
                  <i class="fas fa-plus"></i> {{ t("txnSettings_addItem") }}
                </button>
              </div>

              <div
                v-if="displayContentForms.withdrawal.items.length === 0"
                class="empty-tips"
              >
                <i class="fas fa-align-left"></i>
                <p>{{ t("txnSettings_noWithdrawalDisplayItems") }}</p>
              </div>

              <div
                v-for="(item, index) in displayContentForms.withdrawal.items"
                :key="`withdrawal-${index}`"
                class="tip-item-editor display-content-item"
              >
                <div class="tip-item-header">
                  <span class="tip-number">{{
                    tParams("txnSettings_itemN", "Item {n}", { n: index + 1 })
                  }}</span>
                  <button
                    v-if="hasEditPermission"
                    type="button"
                    class="btn-remove-item"
                    @click="removeDisplayContentItem('withdrawal', index)"
                  >
                    <i class="fas fa-trash"></i> {{ t("txnSettings_remove") }}
                  </button>
                </div>

                <div class="form-group">
                  <label class="form-label">{{
                    t("txnSettings_label_content")
                  }}</label>
                  <textarea
                    v-model="item.content"
                    class="form-textarea"
                    rows="1"
                    :disabled="!hasEditPermission"
                  ></textarea>
                </div>
              </div>

              <div class="section-actions" v-if="hasEditPermission">
                <button
                  type="button"
                  class="btn btn-primary"
                  :disabled="saving"
                  @click="saveDisplayContent('withdrawal')"
                >
                  <i
                    :class="saving ? 'fas fa-spinner fa-spin' : 'fas fa-save'"
                  ></i>
                  {{
                    saving
                      ? t("txnSettings_saving")
                      : t("txnSettings_saveWithdrawalContent")
                  }}
                </button>
              </div>
            </div>

            <div class="settings-section">
              <div class="section-header">
                <div>
                  <h3>
                    <i class="fas fa-right-left"></i>
                    {{ t("txnSettings_internalTransferSectionTitle") }}
                  </h3>
                  <p class="section-description">
                    {{ t("txnSettings_internalTransferSectionDesc") }}
                  </p>
                </div>
                <button
                  v-if="hasEditPermission"
                  type="button"
                  class="btn-add-item"
                  @click="addDisplayContentItem('internal_transfer')"
                >
                  <i class="fas fa-plus"></i> {{ t("txnSettings_addItem") }}
                </button>
              </div>

              <div
                v-if="displayContentForms.internalTransfer.items.length === 0"
                class="empty-tips"
              >
                <i class="fas fa-right-left"></i>
                <p>{{ t("txnSettings_noInternalDisplayItems") }}</p>
              </div>

              <div
                v-for="(item, index) in displayContentForms.internalTransfer
                  .items"
                :key="`internal-${index}`"
                class="tip-item-editor display-content-item internal-transfer-item"
              >
                <div class="tip-item-header">
                  <span class="tip-number">{{
                    tParams("txnSettings_itemN", "Item {n}", { n: index + 1 })
                  }}</span>
                  <button
                    v-if="hasEditPermission"
                    type="button"
                    class="btn-remove-item"
                    @click="
                      removeDisplayContentItem('internal_transfer', index)
                    "
                  >
                    <i class="fas fa-trash"></i> {{ t("txnSettings_remove") }}
                  </button>
                </div>

                <div class="display-content-compact-grid">
                  <div class="form-group">
                    <label class="form-label">{{
                      t("txnSettings_label_title")
                    }}</label>
                    <input
                      v-model="item.title"
                      type="text"
                      class="form-input"
                      :disabled="!hasEditPermission"
                      :placeholder="t('txnSettings_ph_title')"
                    />
                  </div>

                  <div class="form-group">
                    <label class="form-label">{{
                      t("txnSettings_label_iconClassShort")
                    }}</label>
                    <div class="icon-input-wrapper">
                      <input
                        v-model="item.iconClass"
                        type="text"
                        class="form-input icon-input"
                        :disabled="!hasEditPermission"
                        :placeholder="t('txnSettings_ph_iconClass')"
                      />
                      <div class="icon-input-preview">
                        <i :class="item.iconClass || 'fas fa-right-left'"></i>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="form-group">
                  <label class="form-label">{{
                    t("txnSettings_label_content")
                  }}</label>
                  <textarea
                    v-model="item.content"
                    class="form-textarea"
                    rows="1"
                    :disabled="!hasEditPermission"
                  ></textarea>
                </div>
              </div>

              <div class="section-actions" v-if="hasEditPermission">
                <button
                  type="button"
                  class="btn btn-primary"
                  :disabled="saving"
                  @click="saveDisplayContent('internal_transfer')"
                >
                  <i
                    :class="saving ? 'fas fa-spinner fa-spin' : 'fas fa-save'"
                  ></i>
                  {{
                    saving
                      ? t("txnSettings_saving")
                      : t("txnSettings_saveInternalContent")
                  }}
                </button>
              </div>
            </div>
          </div>
        </transition>
      </div>

      <!-- Transaction Notifications & Security -->
      <div class="settings-card">
        <div class="card-header" @click="toggleCard('notifications')">
          <div class="card-header-content">
            <h2>
              <i class="fas fa-bell"></i>
              {{ t("txnSettings_notificationsSecurityTitle") }}
            </h2>
            <p>{{ t("txnSettings_notificationsSecuritySub") }}</p>
          </div>
          <i
            :class="[
              'fas fa-chevron-down card-collapse-icon',
              { rotated: !collapsedCards.notifications },
            ]"
          ></i>
        </div>

        <transition name="card-expand">
          <div v-show="!collapsedCards.notifications" class="card-body">
            <!-- Original Notification Settings -->
            <NotificationSettings
              :notification-data="notifications"
              :can-edit="hasEditPermission"
              @change="handleNotificationChange"
            />

            <!-- Sales Manager Notifications -->
            <div class="settings-section">
              <div class="section-header">
                <div>
                  <h3>
                    <i class="fas fa-user-tie"></i>
                    {{ t("txnSettings_salesMgrTitle") }}
                  </h3>
                  <p class="section-description">
                    {{ t("txnSettings_salesMgrDesc") }}
                  </p>
                </div>
                <label
                  class="toggle-switch"
                  :class="{ disabled: !hasEditPermission }"
                >
                  <input
                    type="checkbox"
                    v-model="securitySettings.salesManagerNotifications"
                    :disabled="!hasEditPermission"
                    @change="handleSecuritySettingsChange"
                  />
                  <span class="toggle-slider"></span>
                </label>
              </div>

              <div
                v-if="securitySettings.salesManagerNotifications"
                class="setting-details"
              >
                <div class="info-banner">
                  <div class="info-banner-content">
                    <i class="fas fa-info-circle info-banner-icon"></i>
                    <div class="info-banner-text">
                      {{ t("txnSettings_salesMgrBanner") }}
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Withdrawal Account Verification -->
            <div class="settings-section">
              <div class="section-header">
                <div>
                  <h3>
                    <i class="fas fa-shield-check"></i>
                    {{ t("txnSettings_withdrawVerifyTitle") }}
                  </h3>
                  <p class="section-description">
                    {{ t("txnSettings_withdrawVerifyDesc") }}
                  </p>
                </div>
                <label
                  class="toggle-switch"
                  :class="{ disabled: !hasEditPermission }"
                >
                  <input
                    type="checkbox"
                    v-model="securitySettings.requireWithdrawalVerification"
                    :disabled="!hasEditPermission"
                    @change="handleSecuritySettingsChange"
                  />
                  <span class="toggle-slider"></span>
                </label>
              </div>

              <div
                v-if="securitySettings.requireWithdrawalVerification"
                class="setting-details"
              >
                <div class="info-banner warning">
                  <div class="info-banner-content">
                    <i class="fas fa-info-circle info-banner-icon"></i>
                    <div class="info-banner-text">
                      <strong>{{ t("txnSettings_verifyBannerStrong") }}</strong>
                      {{ t("txnSettings_verifyBanner") }}
                    </div>
                  </div>
                </div>

                <div class="verification-requirements">
                  <h4>
                    <i class="fas fa-list-check"></i>
                    {{ t("txnSettings_verifyRequirementsTitle") }}
                  </h4>

                  <div class="requirement-section">
                    <h5>
                      <i class="fas fa-university"></i>
                      {{ t("txnSettings_bankVerifyTitle") }}
                    </h5>
                    <ul>
                      <li>{{ t("txnSettings_bankVerify_li1") }}</li>
                      <li>{{ t("txnSettings_bankVerify_li2") }}</li>
                      <li>{{ t("txnSettings_bankVerify_li3") }}</li>
                      <li>{{ t("txnSettings_bankVerify_li4") }}</li>
                    </ul>
                  </div>

                  <div class="requirement-section">
                    <h5>
                      <i class="fas fa-wallet"></i>
                      {{ t("txnSettings_cryptoVerifyTitle") }}
                    </h5>
                    <ul>
                      <li>{{ t("txnSettings_cryptoVerify_li1") }}</li>
                      <li>{{ t("txnSettings_cryptoVerify_li2") }}</li>
                      <li>{{ t("txnSettings_cryptoVerify_li3") }}</li>
                    </ul>
                  </div>
                </div>

                <div class="form-group">
                  <label class="form-label">
                    <i class="fas fa-file-upload"></i>
                    {{ t("txnSettings_label_maxFileSizeMb") }}
                  </label>
                  <input
                    type="number"
                    class="form-input"
                    v-model.number="securitySettings.verificationMaxFileSize"
                    placeholder="5"
                    min="1"
                    max="20"
                    :disabled="!hasEditPermission"
                    @input="handleSecuritySettingsChange"
                  />
                  <span class="form-help">{{
                    t("txnSettings_help_maxFileSize")
                  }}</span>
                </div>

                <div class="form-group">
                  <label class="checkbox-label">
                    <input
                      type="checkbox"
                      v-model="securitySettings.autoRejectUnverified"
                      :disabled="!hasEditPermission"
                      @change="handleSecuritySettingsChange"
                    />
                    <span>{{ t("txnSettings_autoRejectUnverified") }}</span>
                  </label>
                </div>

                <div class="form-group">
                  <label class="checkbox-label">
                    <input
                      type="checkbox"
                      v-model="securitySettings.requireVerifiedWalletOnly"
                      :disabled="!hasEditPermission"
                      @change="handleSecuritySettingsChange"
                    />
                    <span>{{ t("txnSettings_verifiedAddressesOnly") }}</span>
                  </label>
                </div>
              </div>
            </div>

            <!-- Withdrawal OTP Verification -->
            <div class="settings-section">
              <div class="section-header">
                <div>
                  <h3>
                    <i class="fas fa-shield-alt"></i>
                    {{ t("txnSettings_otpTitle") }}
                  </h3>
                  <p class="section-description">
                    {{ t("txnSettings_otpDesc") }}
                  </p>
                </div>
                <label
                  class="toggle-switch"
                  :class="{ disabled: !hasEditPermission }"
                >
                  <input
                    type="checkbox"
                    v-model="securitySettings.withdrawalOtpRequired"
                    :disabled="!hasEditPermission"
                    @change="handleSecuritySettingsChange"
                  />
                  <span class="toggle-slider"></span>
                </label>
              </div>

              <div
                v-if="securitySettings.withdrawalOtpRequired"
                class="setting-details"
              >
                <div class="info-banner warning">
                  <div class="info-banner-content">
                    <i class="fas fa-exclamation-circle info-banner-icon"></i>
                    <div class="info-banner-text">
                      <strong>{{ t("txnSettings_otpBannerStrong") }}</strong>
                      {{ t("txnSettings_otpBanner") }}
                    </div>
                  </div>
                </div>

                <div class="form-group">
                  <label class="form-label">
                    <i class="fas fa-clock"></i>
                    {{ t("txnSettings_label_otpMinutes") }}
                  </label>
                  <input
                    type="number"
                    class="form-input"
                    v-model.number="securitySettings.otpValidityMinutes"
                    placeholder="10"
                    min="1"
                    max="60"
                    :disabled="!hasEditPermission"
                    @input="handleSecuritySettingsChange"
                  />
                  <span class="form-help">{{
                    t("txnSettings_help_otpMinutes")
                  }}</span>
                </div>
              </div>
            </div>

            <div class="card-footer" v-if="hasEditPermission">
              <button
                class="btn btn-primary"
                :disabled="
                  (!hasChanges.notifications && !hasChanges.security) || saving
                "
                @click="saveNotificationSettings"
              >
                <i
                  :class="saving ? 'fas fa-spinner fa-spin' : 'fas fa-save'"
                ></i>
                {{
                  saving
                    ? t("txnSettings_saving")
                    : t("txnSettings_saveAllSettings")
                }}
              </button>
            </div>
          </div>
        </transition>
      </div>

      <!-- Auto-Approval Rules -->
      <div class="settings-card">
        <div class="card-header" @click="toggleCard('autoApproval')">
          <div class="card-header-content">
            <h2>
              <i class="fas fa-robot"></i>
              {{ t("txnSettings_autoApprovalTitle") }}
            </h2>
            <p>{{ t("txnSettings_autoApprovalSub") }}</p>
          </div>
          <i
            :class="[
              'fas fa-chevron-down card-collapse-icon',
              { rotated: !collapsedCards.autoApproval },
            ]"
          ></i>
        </div>

        <transition name="card-expand">
          <div v-show="!collapsedCards.autoApproval" class="card-body">
            <div class="info-banner">
              <div class="info-banner-content">
                <i class="fas fa-info-circle info-banner-icon"></i>
                <div class="info-banner-text">
                  <strong>{{
                    t("txnSettings_autoApprovalBannerStrong")
                  }}</strong>
                  {{ t("txnSettings_autoApprovalBanner") }}
                </div>
              </div>
            </div>

            <!-- Deposit Auto-Approval -->
            <div class="auto-approval-section">
              <div class="section-header">
                <h3>
                  <i class="fas fa-arrow-down"></i>
                  {{ t("txnSettings_depositAutoApprovalTitle") }}
                </h3>
                <label
                  class="toggle-switch"
                  :class="{ disabled: !hasEditPermission }"
                >
                  <input
                    type="checkbox"
                    v-model="autoApprovalRules.deposit.enabled"
                    :disabled="!hasEditPermission"
                    @change="handleAutoApprovalChange"
                  />
                  <span class="toggle-slider"></span>
                </label>
              </div>

              <div
                v-if="autoApprovalRules.deposit.enabled"
                class="rule-settings"
              >
                <div class="form-row">
                  <div class="form-group">
                    <label class="form-label">
                      <i class="fas fa-dollar-sign"></i>
                      {{ t("txnSettings_label_minAmountUsd") }}
                    </label>
                    <FormattedNumberInput
                      v-model="autoApprovalRules.deposit.minAmount"
                      :decimals="2"
                      placeholder="0.00"
                      input-class="form-input"
                      :disabled="!hasEditPermission"
                      @update:modelValue="handleAutoApprovalChange"
                    />
                  </div>

                  <div class="form-group">
                    <label class="form-label">
                      <i class="fas fa-dollar-sign"></i>
                      {{ t("txnSettings_label_maxAmountUsd") }}
                    </label>
                    <FormattedNumberInput
                      v-model="autoApprovalRules.deposit.maxAmount"
                      :decimals="2"
                      placeholder="10000.00"
                      input-class="form-input"
                      :disabled="!hasEditPermission"
                      @update:modelValue="handleAutoApprovalChange"
                    />
                  </div>
                </div>

                <div class="form-group">
                  <label class="form-label">
                    <i class="fas fa-globe"></i>
                    {{ t("txnSettings_label_allowedCountries") }}
                  </label>
                  <select
                    multiple
                    class="form-select-multiple"
                    v-model="autoApprovalRules.deposit.allowedCountries"
                    :disabled="!hasEditPermission"
                    @change="onDepositAllowedCountriesChange"
                  >
                    <option
                      v-for="country in availableCountries"
                      :key="country.code"
                      :value="country.code"
                    >
                      {{ country.name }}
                    </option>
                  </select>
                  <span class="form-help">{{
                    t("txnSettings_help_allowedCountries")
                  }}</span>
                </div>

                <div class="form-group">
                  <label class="form-label">
                    <i class="fas fa-tags"></i>
                    {{ t("txnSettings_label_requiredTags") }}
                  </label>
                  <select
                    multiple
                    class="form-select-multiple"
                    v-model="autoApprovalRules.deposit.requiredTags"
                    :disabled="!hasEditPermission"
                    @change="onDepositRequiredTagsChange"
                  >
                    <option
                      v-for="tag in availableClientTags"
                      :key="tag.id"
                      :value="tag.tagName"
                    >
                      {{ tag.tagName }}
                    </option>
                  </select>
                  <span class="form-help">{{
                    t("txnSettings_help_requiredTags")
                  }}</span>
                </div>

                <div class="form-group">
                  <label class="form-label">
                    <i class="fas fa-ban"></i>
                    {{ t("txnSettings_label_excludedTags") }}
                  </label>
                  <select
                    multiple
                    class="form-select-multiple"
                    v-model="autoApprovalRules.deposit.excludedTags"
                    :disabled="!hasEditPermission"
                    @change="onDepositExcludedTagsChange"
                  >
                    <option
                      v-for="tag in availableClientTags"
                      :key="tag.id"
                      :value="tag.tagName"
                    >
                      {{ tag.tagName }}
                    </option>
                  </select>
                  <span class="form-help">{{
                    t("txnSettings_help_excludedTags")
                  }}</span>
                </div>
              </div>
            </div>

            <!-- Internal Transfer Auto-Approval -->
            <div class="auto-approval-section">
              <div class="section-header">
                <h3>
                  <i class="fas fa-exchange-alt"></i>
                  {{ t("txnSettings_internalTransferAutoApprovalTitle") }}
                </h3>
                <label
                  class="toggle-switch"
                  :class="{ disabled: !hasEditPermission }"
                >
                  <input
                    type="checkbox"
                    v-model="autoApprovalRules.internalTransfer.enabled"
                    :disabled="!hasEditPermission"
                    @change="handleAutoApprovalChange"
                  />
                  <span class="toggle-slider"></span>
                </label>
              </div>

              <div
                v-if="autoApprovalRules.internalTransfer.enabled"
                class="rule-settings"
              >
                <div class="form-row">
                  <div class="form-group">
                    <label class="form-label">
                      <i class="fas fa-dollar-sign"></i>
                      {{ t("txnSettings_label_minAmountUsd") }}
                    </label>
                    <FormattedNumberInput
                      v-model="autoApprovalRules.internalTransfer.minAmount"
                      :decimals="2"
                      placeholder="0.00"
                      input-class="form-input"
                      :disabled="!hasEditPermission"
                      @update:modelValue="handleAutoApprovalChange"
                    />
                  </div>

                  <div class="form-group">
                    <label class="form-label">
                      <i class="fas fa-dollar-sign"></i>
                      {{ t("txnSettings_label_maxAmountUsd") }}
                    </label>
                    <FormattedNumberInput
                      v-model="autoApprovalRules.internalTransfer.maxAmount"
                      :decimals="2"
                      placeholder="10000.00"
                      input-class="form-input"
                      :disabled="!hasEditPermission"
                      @update:modelValue="handleAutoApprovalChange"
                    />
                  </div>
                </div>

                <div class="form-group">
                  <label class="form-label">
                    <i class="fas fa-globe"></i>
                    {{ t("txnSettings_label_allowedCountries") }}
                  </label>
                  <select
                    multiple
                    class="form-select-multiple"
                    v-model="
                      autoApprovalRules.internalTransfer.allowedCountries
                    "
                    :disabled="!hasEditPermission"
                    @change="onInternalTransferAllowedCountriesChange"
                  >
                    <option
                      v-for="country in availableCountries"
                      :key="country.code"
                      :value="country.code"
                    >
                      {{ country.name }}
                    </option>
                  </select>
                  <span class="form-help">{{
                    t("txnSettings_help_allowedCountries")
                  }}</span>
                </div>

                <div class="form-group">
                  <label class="form-label">
                    <i class="fas fa-tags"></i>
                    {{ t("txnSettings_label_requiredTags") }}
                  </label>
                  <select
                    multiple
                    class="form-select-multiple"
                    v-model="autoApprovalRules.internalTransfer.requiredTags"
                    :disabled="!hasEditPermission"
                    @change="onInternalTransferRequiredTagsChange"
                  >
                    <option
                      v-for="tag in availableClientTags"
                      :key="tag.id"
                      :value="tag.tagName"
                    >
                      {{ tag.tagName }}
                    </option>
                  </select>
                  <span class="form-help">{{
                    t("txnSettings_help_requiredTags")
                  }}</span>
                </div>

                <div class="form-group">
                  <label class="form-label">
                    <i class="fas fa-ban"></i>
                    {{ t("txnSettings_label_excludedTags") }}
                  </label>
                  <select
                    multiple
                    class="form-select-multiple"
                    v-model="autoApprovalRules.internalTransfer.excludedTags"
                    :disabled="!hasEditPermission"
                    @change="onInternalTransferExcludedTagsChange"
                  >
                    <option
                      v-for="tag in availableClientTags"
                      :key="tag.id"
                      :value="tag.tagName"
                    >
                      {{ tag.tagName }}
                    </option>
                  </select>
                  <span class="form-help">{{
                    t("txnSettings_help_excludedTags")
                  }}</span>
                </div>
              </div>
            </div>

            <div class="info-box warning">
              <p>
                <strong
                  ><i class="fas fa-exclamation-triangle"></i>
                  {{ t("txnSettings_autoApprovalImportantStrong") }}</strong
                >
                {{ t("txnSettings_autoApprovalImportant") }}
              </p>
            </div>

            <div class="card-footer" v-if="hasEditPermission">
              <button
                class="btn btn-primary"
                :disabled="!hasChanges.autoApproval || saving"
                @click="saveAutoApprovalRules"
              >
                <i
                  :class="saving ? 'fas fa-spinner fa-spin' : 'fas fa-save'"
                ></i>
                {{
                  saving
                    ? t("txnSettings_saving")
                    : t("txnSettings_saveAutoApprovalRules")
                }}
              </button>
            </div>
          </div>
        </transition>
      </div>
    </div>
  </div>
</template>

<script setup>
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";

import { ref, reactive, onMounted, computed } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import NotificationSettings from "../components/transactionSettings/NotificationSettings.vue";
import CurrencyMultiSelectPanel from "@/components/transactionSettings/CurrencyMultiSelectPanel.vue";
import GatewaySupportQuestionList from "@/components/transactionSettings/GatewaySupportQuestionList.vue";
import GatewaySupportQuestionModal from "@/components/transactionSettings/GatewaySupportQuestionModal.vue";
import GatewayPaymentMethodsList from "@/components/transactionSettings/GatewayPaymentMethodsList.vue";
import transactionSettingsApi from "../services/transactionSettingsApi";
import { groupGatewaysByPsp } from "@/utils/gatewayPsp";
import FormattedNumberInput from "../components/common/FormattedNumberInput.vue";
import RichTextInput from "../components/common/RichTextInput.vue";
import leadsService from "../services/leadsService";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { translateApiErrorMessage } from "@/i18n/adminI18nBridge";

const { t, tParams, languageStore } = useAdminI18n();

const numLocale = () =>
  languageStore.currentLanguage === "zh" ? "zh-CN" : "en-US";

const authStore = useAuthStore();

// Permission checks for Transaction Settings page
const hasEditPermission = computed(() =>
  authStore.hasPermission("page_transactionsettings_edit"),
);

const router = useRouter();

// 状态
const loading = ref(true);
const saving = ref(false);
const error = ref(null);
const expandedGatewayRows = ref([]);

// 数据
const gatewayList = ref([]);
const groupedGatewayList = computed(() =>
  groupGatewaysByPsp(gatewayList.value),
);
const notifications = ref({});
const availableCountries = ref([]);
const availableClientTags = ref([]);
const gatewayFundingDetails = reactive({});
const gatewayDisplayDetails = reactive({});
const gatewayQuestionDetails = reactive({});
const togglingPaymentMethodId = ref(null);
const showGatewayModal = ref(false);
const showGatewayCapabilityModal = ref(false);
const showGatewayFundingModal = ref(false);
const showGatewayFeeModal = ref(false);
const showGatewayDisplayModal = ref(false);
const showGatewayQuestionModal = ref(false);
const gatewayFundingLoading = ref(false);
const gatewayDisplayLoading = ref(false);
const gatewayQuestionSaving = ref(false);
const editingGatewayKey = ref("");
const editingGatewayCapabilityKey = ref("");
const gatewayFeeModalType = ref("deposit");
const displayModalType = ref("deposit");
const fundingModalGatewaySettingId = ref("");
const displayModalGatewaySettingId = ref("");
const editingGatewayQuestionId = ref("");
const gatewayQuestionModalScope = ref("deposit");
const gatewayQuestionModalGateway = reactive({
  gatewayKey: "",
  gatewayName: "",
  gatewaySettingId: "",
  paymentGatewayId: "",
});
const gatewayQuestionModalQuestion = ref(null);
const gatewayCapabilityForm = reactive({
  gatewayKey: "",
  gatewayName: "",
  type: "fiat",
  iconClass: "",
  isDepositEnabled: false,
  isWithdrawalEnabled: false,
  supportedFiatCurrencies: [],
  supportedCryptoCurrencies: [],
});
const fundingModalGateway = reactive({
  gatewayKey: "",
  gatewayName: "",
  iconClass: "",
});
const displayModalGateway = reactive({
  gatewayKey: "",
  gatewayName: "",
  iconClass: "",
});
const gatewayDisplayForm = reactive({
  depositContent: "",
  withdrawalContent: "",
});

const currentGatewayDisplayContent = computed({
  get: () =>
    displayModalType.value === "withdrawal"
      ? gatewayDisplayForm.withdrawalContent
      : gatewayDisplayForm.depositContent,
  set: (value) => {
    if (displayModalType.value === "withdrawal") {
      gatewayDisplayForm.withdrawalContent = value;
    } else {
      gatewayDisplayForm.depositContent = value;
    }
  },
});

const fiatCurrencyOptions = computed(() => {
  const seen = new Set();
  return exchangeRates.value
    .filter(
      (rate) =>
        String(rate?.type || "").toLowerCase() === "fiat" &&
        Boolean(rate?.isActive),
    )
    .map((rate) => ({
      code: String(rate.currencyCode || "")
        .trim()
        .toUpperCase(),
      name: rate.currencyName || rate.currencyCode || "",
      type: "fiat",
    }))
    .filter((rate) => {
      if (!rate.code || seen.has(rate.code)) return false;
      seen.add(rate.code);
      return true;
    });
});

const cryptoCurrencyOptions = computed(() => {
  const seen = new Set();
  return exchangeRates.value
    .filter(
      (rate) =>
        String(rate?.type || "").toLowerCase() === "crypto" &&
        Boolean(rate?.isActive),
    )
    .map((rate) => ({
      code: String(rate.currencyCode || "")
        .trim()
        .toUpperCase(),
      name: rate.currencyName || rate.currencyCode || "",
      type: "crypto",
    }))
    .filter((rate) => {
      if (!rate.code || seen.has(rate.code)) return false;
      seen.add(rate.code);
      return true;
    });
});

// 安全设置
const securitySettings = reactive({
  salesManagerNotifications: false,
  withdrawalOtpRequired: false,
  otpValidityMinutes: 10,
  requireVerifiedWalletOnly: true,
  requireWithdrawalVerification: false,
  verificationMaxFileSize: 5,
  autoRejectUnverified: false,
});

// 自动审批规则
const autoApprovalRules = reactive({
  deposit: {
    enabled: false,
    minAmount: 0,
    maxAmount: 10000,
    allowedCountries: ["ALL"],
    requiredTags: [],
    excludedTags: [],
  },
  withdrawal: {
    enabled: false,
    minAmount: 0,
    maxAmount: 5000,
    allowedCountries: ["ALL"],
    requiredTags: [],
    excludedTags: [],
    requireKycVerified: true,
    checkSavedWallet: false,
  },
  internalTransfer: {
    enabled: false,
    minAmount: 0,
    maxAmount: 10000,
    allowedCountries: ["ALL"],
    requiredTags: [],
    excludedTags: [],
  },
});

const displayContentForms = reactive({
  deposit: {
    scope: "deposit",
    items: [],
  },
  withdrawal: {
    scope: "withdrawal",
    items: [],
  },
  internalTransfer: {
    scope: "internal_transfer",
    items: [],
  },
});

// 变更追踪
const hasChanges = reactive({
  notifications: false,
  security: false,
  autoApproval: false,
});

// 汇率表单验证
const isRateFormValid = computed(() => {
  const hasBaseFields =
    String(rateForm.type || "").trim() &&
    String(rateForm.currencyCode || "").trim();

  if (!hasBaseFields) return false;
  if (editingRate.value) return true;

  return rateForm.exchangeRate && rateForm.exchangeRate > 0;
});

const isRateSettingsFormValid = computed(() => {
  return (
    rateSettingsForm.exchangeRate &&
    rateSettingsForm.exchangeRate > 0 &&
    !rateSettingsErrors.exchangeRate &&
    !rateSettingsErrors.depositTarget &&
    !rateSettingsErrors.depositBias &&
    !rateSettingsErrors.withdrawTarget &&
    !rateSettingsErrors.withdrawBias
  );
});

// 卡片折叠状态
const collapsedCards = reactive({
  gatewayList: false,
  displayContents: false,
  notifications: false,
  autoApproval: false,
  exchangeRates: false,
});

// 变更数据缓存
const pendingChanges = reactive({
  notifications: {},
  autoApproval: {},
});

// 汇率设置
const exchangeRates = ref([]);
const showAddRateModal = ref(false);
const editingRate = ref(null);
const showRateSettingsModal = ref(false);
const editingRateSettings = ref(null);
const rateForm = reactive({
  type: "fiat",
  currencyCode: "",
  currencyName: "",
  currencySymbol: "$",
  exchangeRate: null,
});
const rateSettingsForm = reactive({
  exchangeRate: null,
  depositType: "fixed",
  depositBias: 0,
  depositTarget: null,
  withdrawType: "fixed",
  withdrawBias: 0,
  withdrawTarget: null,
});
const rateSettingsErrors = reactive({
  exchangeRate: "",
  depositTarget: "",
  depositBias: "",
  withdrawTarget: "",
  withdrawBias: "",
});

const gatewayForm = reactive({
  gatewayKey: "",
  gatewayName: "",
  type: "fiat",
  iconClass: "",
  isEnabled: false,
  isDepositEnabled: false,
  isWithdrawalEnabled: false,
  displayOrder: 0,
  processingTime: "",
  withdrawalProcessingTime: "",
  environment: "production",
  appId: "",
  apiKey: "",
  secretKey: "",
  merchantName: "",
  webhookUrl: "",
  returnUrl: "",
  supportedFiatCurrencies: [],
  supportedCryptoCurrencies: [],
  configData: "",
});

const gatewayFundingForm = reactive({
  minDeposit: null,
  maxDeposit: null,
  minWithdrawal: null,
  maxWithdrawal: null,
  depositRules: [],
  withdrawalRules: [],
});

const quickAmountDrafts = reactive({});
const quickAmountBusyKey = ref("");

const isValidPercentageFee = (mode, value) => {
  if (mode !== "dynamic") return true;
  const num = Number(value ?? 0);
  return !Number.isNaN(num) && num >= 0 && num <= 100;
};

const createEmptyGatewayRule = (
  transactionType = "deposit",
  thresholdAmount = 0,
) => ({
  transactionType,
  thresholdAmount,
  feeMode: "fixed",
  percentage: 0,
  fixed: 0,
  minFee: null,
  maxFee: null,
  sortOrder: 0,
  isActive: true,
});

const normalizeNullableNumber = (value) => {
  if (value === null || value === undefined || value === "") return null;
  const num = Number(value);
  return Number.isNaN(num) ? null : num;
};

const isUnlimitedValue = (value) => normalizeNullableNumber(value) === null;

const toggleUnlimitedField = (target, field, checked) => {
  if (!target || !field) return;
  target[field] = checked ? null : 0;
};

const normalizeGatewayRule = (
  rule = {},
  transactionType = "deposit",
  index = 0,
) => ({
  transactionType,
  thresholdAmount: Number(rule.thresholdAmount ?? 0) || 0,
  feeMode: String(rule.feeMode || rule.mode || "fixed"),
  percentage: Number(rule.percentage ?? 0) || 0,
  fixed: Number(rule.fixed ?? 0) || 0,
  minFee: normalizeNullableNumber(rule.minFee),
  maxFee: normalizeNullableNumber(rule.maxFee),
  sortOrder: Number(rule.sortOrder ?? index) || index,
  isActive: rule.isActive !== false,
});

const normalizeGatewayRules = (rules = [], transactionType = "deposit") => {
  const source = Array.isArray(rules) ? rules : [];
  const normalized = source
    .filter(
      (rule) => (rule?.transactionType || transactionType) === transactionType,
    )
    .map((rule, index) => normalizeGatewayRule(rule, transactionType, index))
    .sort((a, b) => a.thresholdAmount - b.thresholdAmount);

  return normalized.length
    ? normalized
    : [createEmptyGatewayRule(transactionType, 0)];
};

const formatRuleAmount = (value) => {
  const num = Number(value ?? 0);
  if (Number.isNaN(num)) return "0";
  const loc = numLocale();
  if (Number.isInteger(num)) {
    return new Intl.NumberFormat(loc, {
      maximumFractionDigits: 0,
    }).format(num);
  }

  return new Intl.NumberFormat(loc, {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(num);
};

const formatCurrencyAmount = (value) => `$${formatRuleAmount(value)}`;

const formatFundingValue = (value) => {
  if (isUnlimitedValue(value)) return t("txnSettings_unlimited");
  return formatCurrencyAmount(value);
};

const normalizeExchangeRateNumber = (value) => {
  const num = Number(value);
  return Number.isFinite(num) ? num : null;
};

const normalizeExchangeRateMode = (mode = "fixed") => {
  const normalizedMode = String(mode || "fixed").toLowerCase();
  return normalizedMode === "dyn" ? "dynamic" : normalizedMode;
};

const normalizeExchangeRateBiasForForm = (bias, mode = "fixed") => {
  const normalizedBias = normalizeExchangeRateNumber(bias) ?? 0;
  return normalizeExchangeRateMode(mode) === "dynamic"
    ? normalizedBias * 100
    : normalizedBias;
};

const normalizeExchangeRateBiasForSave = (bias, mode = "fixed") => {
  const normalizedBias = normalizeExchangeRateNumber(bias) ?? 0;
  return normalizeExchangeRateMode(mode) === "dynamic"
    ? normalizedBias / 100
    : normalizedBias;
};

const roundExchangeRateValue = (value, digits = 8) => {
  const normalizedValue = normalizeExchangeRateNumber(value);
  if (normalizedValue === null) return null;
  return Number(normalizedValue.toFixed(digits));
};

const getRateSettingsScopeFields = (scope = "deposit") =>
  scope === "withdraw"
    ? { type: "withdrawType", bias: "withdrawBias", target: "withdrawTarget" }
    : { type: "depositType", bias: "depositBias", target: "depositTarget" };

const getRateSettingsScopeErrorFields = (scope = "deposit") =>
  scope === "withdraw"
    ? { target: "withdrawTarget", bias: "withdrawBias" }
    : { target: "depositTarget", bias: "depositBias" };

const getAdjustedExchangeRate = (rate, bias, mode = "fixed") => {
  const baseRate = normalizeExchangeRateNumber(rate);
  if (baseRate === null) return null;

  const normalizedBias = normalizeExchangeRateNumber(bias) ?? 0;
  const normalizedMode = normalizeExchangeRateMode(mode);

  if (normalizedMode === "dynamic" || normalizedMode === "dyn") {
    return baseRate * (1 + normalizedBias);
  }

  return baseRate + normalizedBias;
};

const formatExchangeRateDisplay = (value) => {
  const rate = normalizeExchangeRateNumber(value);
  if (rate === null) return "-";

  return new Intl.NumberFormat(numLocale(), {
    minimumFractionDigits: 0,
    maximumFractionDigits: 8,
  }).format(rate);
};

// 出入金列显示用：默认 2 位小数；极小值（2 位会显示成 0.00）才从第一位有效数字起多留几位，避免看不出来
const formatRateShort = (value) => {
  const rate = normalizeExchangeRateNumber(value);
  if (rate === null) return "-";

  const abs = Math.abs(rate);
  const isTiny = abs !== 0 && abs < 0.01;
  const maxDigits = isTiny ? Math.min(Math.floor(-Math.log10(abs)) + 3, 12) : 2;

  return new Intl.NumberFormat(numLocale(), {
    minimumFractionDigits: isTiny ? 0 : 2,
    maximumFractionDigits: maxDigits,
  }).format(rate);
};

const formatExchangeRateLine = (value, code = "") => {
  const rate = normalizeExchangeRateNumber(value);
  if (rate === null) return "-";

  return tParams("txnSettings_rateLine", "1 USD = {rate} {code}", {
    rate: formatExchangeRateDisplay(rate),
    code: String(code || "").trim(),
  }).replace(/\s+$/u, "");
};

const formatExchangeRateBias = (bias, mode = "fixed") => {
  const normalizedBias = normalizeExchangeRateNumber(bias) ?? 0;
  const normalizedMode = normalizeExchangeRateMode(mode);
  const sign = normalizedBias < 0 ? "-" : "+";
  const absoluteBias = Math.abs(normalizedBias);

  if (normalizedMode === "dynamic" || normalizedMode === "dyn") {
    const percentValue = new Intl.NumberFormat(numLocale(), {
      minimumFractionDigits: 0,
      maximumFractionDigits: 2,
    }).format(absoluteBias * 100);

    return `(${sign}${percentValue}%)`;
  }

  return `(${sign}${formatExchangeRateDisplay(absoluteBias)})`;
};

const formatAdjustedExchangeRateValue = (rate, bias, mode = "fixed") => {
  const adjustedRate = getAdjustedExchangeRate(rate, bias, mode);
  if (adjustedRate === null) return "-";

  return formatRateShort(adjustedRate);
};

const getExchangeRateBiasClass = (bias) => {
  const normalizedBias = normalizeExchangeRateNumber(bias) ?? 0;
  if (normalizedBias > 0) return "positive";
  if (normalizedBias < 0) return "negative";
  return "neutral";
};

const getExchangeRateBiasHelp = (mode = "fixed") => {
  if (normalizeExchangeRateMode(mode) === "dynamic") {
    return t("txnSettings_help_exchangeRateBiasDynamic");
  }

  return t("txnSettings_help_exchangeRateBiasFixed");
};

const clearRateSettingsScopeErrors = (scope = "deposit") => {
  const errorFields = getRateSettingsScopeErrorFields(scope);
  rateSettingsErrors[errorFields.target] = "";
  rateSettingsErrors[errorFields.bias] = "";
};

const validateRateSettingsScope = (scope = "deposit") => {
  const fields = getRateSettingsScopeFields(scope);
  const errorFields = getRateSettingsScopeErrorFields(scope);
  const baseRate = normalizeExchangeRateNumber(rateSettingsForm.exchangeRate);
  const targetRate = normalizeExchangeRateNumber(
    rateSettingsForm[fields.target],
  );
  const biasValue = normalizeExchangeRateNumber(rateSettingsForm[fields.bias]);
  const mode = normalizeExchangeRateMode(rateSettingsForm[fields.type]);

  rateSettingsErrors.exchangeRate = "";
  rateSettingsErrors[errorFields.target] = "";
  rateSettingsErrors[errorFields.bias] = "";

  if (baseRate === null || baseRate <= 0) {
    rateSettingsErrors.exchangeRate = t("txnSettings_err_exchangeRatePositive");
  }

  if (targetRate === null || targetRate <= 0) {
    rateSettingsErrors[errorFields.target] = t(
      "txnSettings_err_rateTargetPositive",
    );
  }

  if (mode === "dynamic" && biasValue !== null && biasValue <= -100) {
    rateSettingsErrors[errorFields.bias] = t(
      "txnSettings_err_rateBiasDynamicRange",
    );
  }

  if (mode === "fixed" && targetRate !== null && targetRate <= 0) {
    rateSettingsErrors[errorFields.bias] = t(
      "txnSettings_err_rateBiasFixedResultPositive",
    );
  }
};

const syncRateSettingsTargetFromBias = (scope = "deposit") => {
  const baseRate = normalizeExchangeRateNumber(rateSettingsForm.exchangeRate);
  const fields = getRateSettingsScopeFields(scope);

  if (baseRate === null || baseRate <= 0) {
    rateSettingsErrors.exchangeRate = t("txnSettings_err_exchangeRatePositive");
    rateSettingsForm[fields.target] = null;
    clearRateSettingsScopeErrors(scope);
    return;
  }

  const mode = normalizeExchangeRateMode(rateSettingsForm[fields.type]);
  const bias = normalizeExchangeRateBiasForSave(
    rateSettingsForm[fields.bias],
    mode,
  );
  rateSettingsForm[fields.target] = roundExchangeRateValue(
    getAdjustedExchangeRate(baseRate, bias, mode),
  );
  validateRateSettingsScope(scope);
};

const syncRateSettingsBiasFromTarget = (scope = "deposit") => {
  const baseRate = normalizeExchangeRateNumber(rateSettingsForm.exchangeRate);
  const fields = getRateSettingsScopeFields(scope);
  const targetRate = normalizeExchangeRateNumber(
    rateSettingsForm[fields.target],
  );
  const mode = normalizeExchangeRateMode(rateSettingsForm[fields.type]);

  if (baseRate === null || baseRate <= 0) {
    rateSettingsErrors.exchangeRate = t("txnSettings_err_exchangeRatePositive");
    rateSettingsForm[fields.bias] = 0;
    clearRateSettingsScopeErrors(scope);
    return;
  }

  if (targetRate === null) {
    rateSettingsForm[fields.bias] = 0;
    validateRateSettingsScope(scope);
    return;
  }

  if (mode === "dynamic") {
    const percentBias = baseRate === 0 ? 0 : (targetRate / baseRate - 1) * 100;
    rateSettingsForm[fields.bias] = roundExchangeRateValue(percentBias, 4);
    validateRateSettingsScope(scope);
    return;
  }

  rateSettingsForm[fields.bias] = roundExchangeRateValue(targetRate - baseRate);
  validateRateSettingsScope(scope);
};

const handleRateSettingsExchangeRateChange = () => {
  syncRateSettingsTargetFromBias("deposit");
  syncRateSettingsTargetFromBias("withdraw");
};

const handleRateSettingsBiasChange = (scope = "deposit") => {
  syncRateSettingsTargetFromBias(scope);
};

const handleRateSettingsTargetChange = (scope = "deposit") => {
  syncRateSettingsBiasFromTarget(scope);
};

const handleRateSettingsTypeChange = (scope = "deposit") => {
  syncRateSettingsBiasFromTarget(scope);
};

const formatGatewayFeeRule = (rule = {}) => {
  const normalizedRule = normalizeGatewayRule(rule);

  if (normalizedRule.feeMode === "none") {
    return t("txnSettings_feeNoFee");
  }

  if (normalizedRule.feeMode === "fixed") {
    return tParams("txnSettings_feeFixed", "Fixed fee {amount}", {
      amount: formatCurrencyAmount(normalizedRule.fixed ?? 0),
    });
  }

  const percentage = Number(normalizedRule.percentage ?? 0);
  const pctDisplay = Number.isInteger(percentage)
    ? String(percentage)
    : percentage.toFixed(2).replace(/\.?0+$/, "");
  const parts = [
    tParams("txnSettings_feePercentPart", "{pct}% of the amount", {
      pct: pctDisplay,
    }),
  ];

  if (!isUnlimitedValue(normalizedRule.minFee)) {
    parts.push(
      tParams("txnSettings_feeMinimum", "minimum {amount}", {
        amount: formatCurrencyAmount(normalizedRule.minFee ?? 0),
      }),
    );
  }

  if (!isUnlimitedValue(normalizedRule.maxFee)) {
    parts.push(
      tParams("txnSettings_feeMaximum", "maximum {amount}", {
        amount: formatCurrencyAmount(normalizedRule.maxFee ?? 0),
      }),
    );
  }

  return parts.join(", ");
};

const getFeeModeHelp = (feeMode) => {
  if (feeMode === "dynamic") return t("txnSettings_feeHelp_dynamic");
  if (feeMode === "fixed") return t("txnSettings_feeHelp_fixed");
  return t("txnSettings_feeHelp_none");
};

const getRuleRangeLabel = (
  rules = [],
  index = 0,
  transactionType = "deposit",
) => {
  const normalizedRules = sortGatewayRules(rules, transactionType);
  const currentRule = normalizedRules[index];

  if (!currentRule) return "";

  if (
    normalizedRules.length === 1 &&
    (Number(currentRule.thresholdAmount ?? 0) || 0) === 0
  ) {
    return "";
  }

  const currentStart = Number(currentRule.thresholdAmount ?? 0) || 0;
  const nextRule = normalizedRules[index + 1];

  if (index === 0 && currentStart === 0 && nextRule) {
    return tParams("txnSettings_rangeBelow", "Below {amount}", {
      amount: formatCurrencyAmount(nextRule.thresholdAmount),
    });
  }

  if (!nextRule) {
    return tParams("txnSettings_rangeFromPlus", "{amount}+", {
      amount: formatCurrencyAmount(currentStart),
    });
  }

  const nextStart = Number(nextRule.thresholdAmount ?? 0) || 0;
  return tParams("txnSettings_rangeBetween", "{from} - < {to}", {
    from: formatCurrencyAmount(currentStart),
    to: formatCurrencyAmount(nextStart),
  });
};

const sortGatewayRules = (rules = [], transactionType = "deposit") =>
  [...rules]
    .map((rule, index) => normalizeGatewayRule(rule, transactionType, index))
    .sort((a, b) => a.thresholdAmount - b.thresholdAmount)
    .map((rule, index) => ({
      ...rule,
      transactionType,
      sortOrder: index,
    }));

const addGatewayRule = (transactionType = "deposit") => {
  const key =
    transactionType === "withdrawal" ? "withdrawalRules" : "depositRules";
  const existingRules = sortGatewayRules(
    gatewayFundingForm[key],
    transactionType,
  );
  const lastThreshold = existingRules.length
    ? Number(existingRules[existingRules.length - 1].thresholdAmount ?? 0)
    : 0;

  gatewayFundingForm[key] = [
    ...existingRules,
    createEmptyGatewayRule(transactionType, lastThreshold),
  ].map((rule, index) => ({
    ...normalizeGatewayRule(rule, transactionType, index),
    sortOrder: index,
  }));
};

const removeGatewayRule = (transactionType = "deposit", index = 0) => {
  const key =
    transactionType === "withdrawal" ? "withdrawalRules" : "depositRules";
  const nextRules = [...gatewayFundingForm[key]];
  nextRules.splice(index, 1);
  gatewayFundingForm[key] = normalizeGatewayRules(
    nextRules,
    transactionType,
  ).map((rule, ruleIndex) => ({
    ...rule,
    sortOrder: ruleIndex,
  }));
};

const inferGatewayFundingMode = () => {
  const groups = [
    gatewayFundingForm.depositRules,
    gatewayFundingForm.withdrawalRules,
  ];
  return groups.some((rules) => Array.isArray(rules) && rules.length > 1)
    ? "threshold"
    : "single";
};

const isGatewayFundingFormValid = computed(() => {
  const rangesValid = [
    [gatewayFundingForm.minDeposit, gatewayFundingForm.maxDeposit],
    [gatewayFundingForm.minWithdrawal, gatewayFundingForm.maxWithdrawal],
  ].every(([min, max]) => {
    const normalizedMin = normalizeNullableNumber(min);
    const normalizedMax = normalizeNullableNumber(max);
    if (normalizedMin !== null && normalizedMin < 0) return false;
    if (normalizedMax !== null && normalizedMax < 0) return false;
    return (
      normalizedMin === null ||
      normalizedMax === null ||
      normalizedMax >= normalizedMin
    );
  });

  if (!rangesValid) return false;

  const validateRules = (rules, transactionType) => {
    if (!Array.isArray(rules) || rules.length === 0) return false;

    const normalizedRules = [...rules]
      .map((rule, index) => normalizeGatewayRule(rule, transactionType, index))
      .sort((a, b) => a.thresholdAmount - b.thresholdAmount);

    if (
      !normalizedRules.every((rule) =>
        isValidPercentageFee(rule.feeMode, rule.percentage),
      )
    )
      return false;

    if (!normalizedRules.every((rule) => rule.thresholdAmount >= 0))
      return false;

    if (inferGatewayFundingMode() === "single") {
      return (
        normalizedRules.length === 1 && normalizedRules[0].thresholdAmount === 0
      );
    }

    return normalizedRules[0].thresholdAmount === 0;
  };

  return (
    validateRules(gatewayFundingForm.depositRules, "deposit") &&
    validateRules(gatewayFundingForm.withdrawalRules, "withdrawal")
  );
});

const activeGatewayFeeRules = computed(() =>
  gatewayFeeModalType.value === "withdrawal"
    ? gatewayFundingForm.withdrawalRules
    : gatewayFundingForm.depositRules,
);

const normalizeCurrencyList = (value) => {
  if (!value) return [];
  if (Array.isArray(value)) return value.filter(Boolean);

  if (typeof value === "string") {
    try {
      const parsed = JSON.parse(value);
      return Array.isArray(parsed) ? parsed.filter(Boolean) : [];
    } catch {
      return value
        .split(",")
        .map((item) => item.trim())
        .filter(Boolean);
    }
  }

  return [];
};

const createGatewayFundingDetailState = () => ({
  loading: false,
  error: "",
  data: null,
});

const createGatewayDisplayDetailState = () => ({
  loading: false,
  error: "",
  data: null,
});

const createGatewayQuestionDetailState = () => ({
  loading: false,
  error: "",
  data: null,
});

const getGatewayFundingDetailState = (gatewayKey) => {
  if (!gatewayKey) return createGatewayFundingDetailState();
  if (!gatewayFundingDetails[gatewayKey]) {
    gatewayFundingDetails[gatewayKey] = createGatewayFundingDetailState();
  }
  return gatewayFundingDetails[gatewayKey];
};

const getGatewayDisplayDetailState = (gatewayKey) => {
  if (!gatewayKey) return createGatewayDisplayDetailState();
  if (!gatewayDisplayDetails[gatewayKey]) {
    gatewayDisplayDetails[gatewayKey] = createGatewayDisplayDetailState();
  }
  return gatewayDisplayDetails[gatewayKey];
};

const getGatewayQuestionDetailState = (gatewayKey) => {
  if (!gatewayKey) return createGatewayQuestionDetailState();
  if (!gatewayQuestionDetails[gatewayKey]) {
    gatewayQuestionDetails[gatewayKey] = createGatewayQuestionDetailState();
  }
  return gatewayQuestionDetails[gatewayKey];
};

const normalizeQuestionOptions = (value) => {
  if (!value) return [];
  if (Array.isArray(value))
    return value.map(normalizeQuestionOptionItem).filter(Boolean);
  if (typeof value === "string") {
    try {
      const parsed = JSON.parse(value);
      return Array.isArray(parsed)
        ? parsed.map(normalizeQuestionOptionItem).filter(Boolean)
        : [];
    } catch {
      return value.split(",").map(normalizeQuestionOptionItem).filter(Boolean);
    }
  }
  return [];
};

const normalizeQuestionOptionItem = (option) => {
  if (option && typeof option === "object") {
    const value = String(
      option.value ?? option.optionValue ?? option.label ?? option.labal ?? "",
    ).trim();
    const label = String(
      option.label ?? option.labal ?? option.value ?? option.optionValue ?? "",
    ).trim();

    if (!label && !value) {
      return null;
    }

    return {
      label: label || value,
      value: value || label,
      isEnabled: option.isEnabled !== false && option.enabled !== false,
    };
  }

  const raw = String(option || "").trim();
  if (!raw) {
    return null;
  }

  return {
    label: raw,
    value: raw,
    isEnabled: true,
  };
};

const normalizeQuestionValidation = (value) => {
  const text = String(value || "").trim();
  return {
    required: /(^|[|,])required($|[|,])/i.test(text),
    text,
  };
};

const normalizeGatewayQuestionItem = (item = {}) => ({
  ...item,
  id: item.id || "",
  name: String(item.name || "").trim(),
  hintText: String(item.hintText || "").trim(),
  questionType: String(item.questionType || "text").trim(),
  validationRules: String(item.validationRules || "").trim(),
  options: normalizeQuestionOptions(item.options),
  scope: String(item.scope || "").trim(),
  isLocked: Boolean(item.isLocked),
  isActive: item.isActive !== false,
});

const normalizeGatewayQuestionData = (data = {}) => ({
  deposit: Array.isArray(data.deposit)
    ? data.deposit.map(normalizeGatewayQuestionItem)
    : [],
  withdraw: Array.isArray(data.withdraw)
    ? data.withdraw.map(normalizeGatewayQuestionItem)
    : [],
  withdrawTemplateActive: Boolean(data.withdrawTemplateActive),
});

const getGatewayQuestionGroups = (gatewayKey) => {
  const data = getGatewayQuestionDetailState(gatewayKey).data || {
    deposit: [],
    withdraw: [],
    withdrawTemplateActive: false,
  };
  return [
    {
      key: "deposit",
      label: t("txnSettings_gsq_scope_deposit"),
      emptyLabel: "deposit",
      emptyMessage: t("txnSettings_gsq_groupEmptyDeposit"),
      questions: Array.isArray(data.deposit) ? data.deposit : [],
      disableEditing: false,
      notice: "",
    },
    {
      key: "withdraw",
      label: t("txnSettings_gsq_scope_withdrawal"),
      emptyLabel: "withdraw",
      emptyMessage: t("txnSettings_gsq_groupEmptyWithdraw"),
      questions: Array.isArray(data.withdraw) ? data.withdraw : [],
      disableEditing: Boolean(data.withdrawTemplateActive),
      notice: data.withdrawTemplateActive
        ? t("txnSettings_gsq_withdrawTemplateNotice")
        : "",
    },
  ];
};

const normalizeQuickAmounts = (list = []) => {
  if (!Array.isArray(list)) return [];
  return list
    .map((item) => ({
      id: Number(item?.id) || 0,
      amount: Number(item?.amount),
      sortOrder: Number(item?.sortOrder) || 0,
      transactionType: item?.transactionType || null,
    }))
    .filter((item) => item.id > 0 && Number.isFinite(item.amount))
    .sort(
      (a, b) => a.amount - b.amount || a.sortOrder - b.sortOrder || a.id - b.id,
    );
};

const getGatewayQuickAmounts = (gatewayKey, transactionType = "deposit") => {
  const listKey =
    transactionType === "withdrawal"
      ? "withdrawalQuickAmounts"
      : "depositQuickAmounts";
  return normalizeQuickAmounts(
    getGatewayFundingDetailState(gatewayKey).data?.[listKey] || [],
  );
};

const normalizeGatewayFundingData = (data = {}) => {
  const feeRules = (
    Array.isArray(data.feeRules)
      ? data.feeRules
      : Array.isArray(data.rules)
        ? data.rules
        : []
  ).map((rule) => ({
    ...rule,
    percentage: Number(rule?.percentage ?? 0) * 100,
  }));

  return {
    minDeposit: normalizeNullableNumber(data.minDeposit),
    maxDeposit: normalizeNullableNumber(data.maxDeposit),
    minWithdrawal: normalizeNullableNumber(data.minWithdrawal),
    maxWithdrawal: normalizeNullableNumber(data.maxWithdrawal),
    depositRules: normalizeGatewayRules(feeRules, "deposit"),
    withdrawalRules: normalizeGatewayRules(feeRules, "withdrawal"),
    depositQuickAmounts: normalizeQuickAmounts(data.depositQuickAmounts),
    withdrawalQuickAmounts: normalizeQuickAmounts(data.withdrawalQuickAmounts),
  };
};

const buildGatewayFundingDetailSnapshot = () => ({
  minDeposit: normalizeNullableNumber(gatewayFundingForm.minDeposit),
  maxDeposit: normalizeNullableNumber(gatewayFundingForm.maxDeposit),
  minWithdrawal: normalizeNullableNumber(gatewayFundingForm.minWithdrawal),
  maxWithdrawal: normalizeNullableNumber(gatewayFundingForm.maxWithdrawal),
  depositRules: normalizeGatewayRules(
    gatewayFundingForm.depositRules,
    "deposit",
  ),
  withdrawalRules: normalizeGatewayRules(
    gatewayFundingForm.withdrawalRules,
    "withdrawal",
  ),
});

const syncGatewayFundingDetailState = (gatewayKey) => {
  if (!gatewayKey) return;
  const state = getGatewayFundingDetailState(gatewayKey);
  state.data = {
    ...buildGatewayFundingDetailSnapshot(),
    depositQuickAmounts: normalizeQuickAmounts(state.data?.depositQuickAmounts),
    withdrawalQuickAmounts: normalizeQuickAmounts(
      state.data?.withdrawalQuickAmounts,
    ),
  };
  state.error = "";
  state.loading = false;
};

const fetchGatewayFundingDetail = async (gateway) => {
  const gatewayKey = gateway?.gatewayKey || "";
  const gatewaySettingId = gateway?.gatewaySettingId || gateway?.id || "";

  if (!gatewayKey || !gatewaySettingId) return;

  const state = getGatewayFundingDetailState(gatewayKey);
  if (state.loading || state.data) return;

  state.loading = true;
  state.error = "";

  try {
    const response =
      await transactionSettingsApi.getGatewayFundingSettings(gatewaySettingId);
    const fundingData = response?.data || response || {};
    state.data = normalizeGatewayFundingData(fundingData);
  } catch (err) {
    console.error("Failed to load funding detail:", err);
    state.error = err.response?.data?.message || err.message || "Unknown error";
    state.data = null;
  } finally {
    state.loading = false;
  }
};

const applyGatewayFundingResponse = (gatewayKey, fundingData) => {
  const state = getGatewayFundingDetailState(gatewayKey);
  state.data = normalizeGatewayFundingData(fundingData || {});
  state.error = "";
  state.loading = false;
};

const addGatewayQuickAmount = async (
  gateway,
  transactionType,
  options = {},
) => {
  const gatewayKey = gateway?.gatewayKey || "";
  const gatewaySettingId = gateway?.gatewaySettingId || gateway?.id || "";
  const draftKey = `${gatewayKey}-${transactionType}`;
  const amount = Number(quickAmountDrafts[draftKey]);

  if (!gatewaySettingId) {
    alert(t("txnSettings_alert_missingGatewayIdFunding"));
    return;
  }
  if (!Number.isFinite(amount) || amount <= 0) {
    if (!options.silentIfEmpty) {
      alert(
        t(
          "txnSettings_alert_quickAmountInvalid",
          "Please enter an amount greater than 0",
        ),
      );
    }
    return;
  }

  const busyKey = `${draftKey}-add`;
  quickAmountBusyKey.value = busyKey;
  try {
    const response = await transactionSettingsApi.addGatewayQuickAmount(
      gatewaySettingId,
      {
        transactionType,
        amount,
      },
    );
    const payload = response?.data || response || {};
    const funding = payload.funding || payload.data?.funding || payload;
    applyGatewayFundingResponse(gatewayKey, funding);
    quickAmountDrafts[draftKey] = null;
  } catch (err) {
    const data = err.response?.data;
    const rawMsg = data?.message || err.message || t("common_unknownError");
    alert(
      tParams(
        "txnSettings_alert_quickAmountAddFailed",
        "Failed to add quick amount: {msg}",
        {
          msg: translateApiErrorMessage(data?.errorCode, rawMsg),
        },
      ),
    );
  } finally {
    quickAmountBusyKey.value = "";
  }
};

const removeGatewayQuickAmount = async (gateway, transactionType, item) => {
  const gatewayKey = gateway?.gatewayKey || "";
  const gatewaySettingId = gateway?.gatewaySettingId || gateway?.id || "";
  const list = getGatewayQuickAmounts(gatewayKey, transactionType);

  if (!gatewaySettingId || !item?.id) {
    alert(t("txnSettings_alert_missingGatewayIdFunding"));
    return;
  }
  if (list.length <= 1) {
    alert(
      t(
        "txnSettings_alert_quickAmountKeepOne",
        "At least one quick amount is required",
      ),
    );
    return;
  }

  const busyKey = `${gatewayKey}-${transactionType}-${item.id}`;
  quickAmountBusyKey.value = busyKey;
  try {
    const response = await transactionSettingsApi.deleteGatewayQuickAmount(
      gatewaySettingId,
      item.id,
    );
    const payload = response?.data || response || {};
    const funding = payload.funding || payload.data?.funding || payload;
    applyGatewayFundingResponse(gatewayKey, funding);
  } catch (err) {
    const data = err.response?.data;
    const rawMsg = data?.message || err.message || t("common_unknownError");
    alert(
      tParams(
        "txnSettings_alert_quickAmountDeleteFailed",
        "Failed to remove quick amount: {msg}",
        {
          msg: translateApiErrorMessage(data?.errorCode, rawMsg),
        },
      ),
    );
  } finally {
    quickAmountBusyKey.value = "";
  }
};

const fetchGatewayDisplayDetail = async (gateway) => {
  const gatewayKey = gateway?.gatewayKey || "";
  const gatewaySettingId = gateway?.gatewaySettingId || gateway?.id || "";

  if (!gatewayKey || !gatewaySettingId) return;

  const state = getGatewayDisplayDetailState(gatewayKey);
  if (state.loading || state.data) return;

  state.loading = true;
  state.error = "";

  try {
    const response =
      await transactionSettingsApi.getGatewayDisplayContent(gatewaySettingId);
    const displayData = response?.data || response || {};
    state.data = extractGatewayDisplayContent(displayData);
  } catch (err) {
    console.error("Failed to load display detail:", err);
    state.error = err.response?.data?.message || err.message || "Unknown error";
    state.data = null;
  } finally {
    state.loading = false;
  }
};

const fetchGatewayQuestionDetail = async (gateway) => {
  const gatewayKey = gateway?.gatewayKey || "";
  const gatewaySettingId = gateway?.gatewaySettingId || gateway?.id || "";

  if (!gatewayKey || !gatewaySettingId) return;

  const state = getGatewayQuestionDetailState(gatewayKey);
  if (state.loading || state.data) return;

  state.loading = true;
  state.error = "";

  try {
    const response =
      await transactionSettingsApi.getGatewaySupportQuestions(gatewaySettingId);
    const questionData =
      response?.data?.data || response?.data || response || {};
    state.data = normalizeGatewayQuestionData(questionData);
  } catch (err) {
    console.error("Failed to load support questions:", err);
    const data = err.response?.data;
    const rawMsg = data?.message || err.message || "";
    state.error = translateApiErrorMessage(
      data?.errorCode,
      rawMsg || t("common_unknownError"),
    );
    state.data = null;
  } finally {
    state.loading = false;
  }
};

const findDepositPaymentMethodQuestion = (gatewayKey) => {
  const depositQuestions =
    getGatewayQuestionDetailState(gatewayKey).data?.deposit;
  if (!Array.isArray(depositQuestions)) return null;

  return (
    depositQuestions.find(
      (question) =>
        String(question?.name || "").toLowerCase() === "payment_method" &&
        String(question?.questionType || "").toLowerCase() === "single_choice",
    ) || null
  );
};

const getGatewayPaymentMethodOptions = (gatewayKey) => {
  const question = findDepositPaymentMethodQuestion(gatewayKey);
  if (!question) return [];

  return normalizeQuestionOptions(question.options).map((option) => ({
    ...option,
    isEnabled: option.isEnabled !== false,
  }));
};

const patchDepositPaymentMethodQuestionOptions = (gatewayKey, options) => {
  const state = getGatewayQuestionDetailState(gatewayKey);
  if (!state.data || !Array.isArray(state.data.deposit)) return;

  state.data = {
    ...state.data,
    deposit: state.data.deposit.map((question) =>
      String(question?.name || "").toLowerCase() === "payment_method"
        ? { ...question, options: normalizeQuestionOptions(options) }
        : question,
    ),
  };
};

const toggleGatewayPaymentMethodOption = async (gateway, option) => {
  if (
    !hasEditPermission.value ||
    !option?.value ||
    togglingPaymentMethodId.value
  )
    return;

  const gatewayKey = gateway?.gatewayKey || "";
  const question = findDepositPaymentMethodQuestion(gatewayKey);
  if (!question?.id) return;

  const currentlyEnabled = option.isEnabled !== false;
  const actionKey = currentlyEnabled
    ? "txnSettings_action_disableVerb"
    : "txnSettings_action_enableVerb";
  const methodName = option.label || option.value;

  if (
    !confirm(
      tParams(
        "txnSettings_confirm_togglePaymentMethod",
        "Are you sure you want to {action} {name}?",
        {
          action: t(actionKey),
          name: methodName,
        },
      ),
    )
  ) {
    return;
  }

  const nextEnabled = !currentlyEnabled;
  togglingPaymentMethodId.value = option.value;

  try {
    const response =
      await transactionSettingsApi.toggleGatewaySupportQuestionOption(
        question.id,
        {
          value: option.value,
          isEnabled: nextEnabled,
        },
      );
    const updatedQuestion = response?.data || response || {};
    const updatedOptions = Array.isArray(updatedQuestion.options)
      ? updatedQuestion.options
      : getGatewayPaymentMethodOptions(gatewayKey).map((item) =>
          item.value === option.value
            ? { ...item, isEnabled: nextEnabled }
            : item,
        );

    patchDepositPaymentMethodQuestionOptions(gatewayKey, updatedOptions);
  } catch (err) {
    console.error("Failed to toggle payment method option:", err);
    const data = err.response?.data;
    const rawMsg = data?.message || err.message || "";
    alert(
      tParams(
        "txnSettings_alert_paymentMethodToggleFailed",
        "Failed to update payment method: {msg}",
        {
          msg: translateApiErrorMessage(
            data?.errorCode,
            rawMsg || t("common_unknownError"),
          ),
        },
      ),
    );
  } finally {
    togglingPaymentMethodId.value = null;
  }
};

const toggleGatewayDetail = (gateway) => {
  const gatewayKey =
    typeof gateway === "string" ? gateway : gateway?.gatewayKey;
  if (!gatewayKey) return;

  const isExpanded = expandedGatewayRows.value.includes(gatewayKey);
  expandedGatewayRows.value = isExpanded ? [] : [gatewayKey];

  if (!isExpanded && gateway && typeof gateway === "object") {
    fetchGatewayFundingDetail(gateway);
    fetchGatewayDisplayDetail(gateway);
    fetchGatewayQuestionDetail(gateway);
  }
};

const isGatewayExpanded = (gatewayKey) =>
  expandedGatewayRows.value.includes(gatewayKey);

const formatGatewayEnvironment = (value) => {
  if (!value) return "-";
  return value === "sandbox"
    ? t("txnSettings_env_sandbox")
    : t("txnSettings_env_production");
};

const formatGatewayCurrencySummary = (value) => {
  const currencies = normalizeCurrencyList(value);
  return currencies.length ? currencies.join(", ") : "-";
};

const formatGatewayQuestionName = (value) => {
  const text = String(value || "").trim();
  if (!text) return "-";
  return text
    .split(/[_\s]+/)
    .filter(Boolean)
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(" ");
};

const formatGatewayQuestionType = (value) => {
  const map = {
    text: "txnSettings_qType_text",
    tel: "txnSettings_qType_tel",
    date: "txnSettings_qType_date",
    email: "txnSettings_qType_email",
    single_choice: "txnSettings_qType_single_choice",
  };

  const key = String(value || "").trim();
  return map[key] ? t(map[key]) : formatGatewayQuestionName(key);
};

const hasGatewayQuestionOptions = (question = {}) =>
  normalizeQuestionOptions(question.options).length > 0;

const refreshGatewayQuestionDetail = async (gateway) => {
  const gatewayKey = gateway?.gatewayKey || "";
  if (!gatewayKey) return;
  const state = getGatewayQuestionDetailState(gatewayKey);
  state.data = null;
  state.error = "";
  state.loading = false;
  await fetchGatewayQuestionDetail(gateway);
};

const closeGatewayQuestionModal = () => {
  showGatewayQuestionModal.value = false;
  gatewayQuestionSaving.value = false;
  editingGatewayQuestionId.value = "";
  gatewayQuestionModalScope.value = "deposit";
  gatewayQuestionModalGateway.gatewayKey = "";
  gatewayQuestionModalGateway.gatewayName = "";
  gatewayQuestionModalGateway.gatewaySettingId = "";
  gatewayQuestionModalGateway.paymentGatewayId = "";
  gatewayQuestionModalQuestion.value = null;
};

const handleGatewayQuestionAction = async (
  action,
  gateway,
  scope,
  question = null,
) => {
  if (!gateway?.gatewaySettingId && !gateway?.id) {
    alert(t("txnSettings_alert_missingGatewayId"));
    return;
  }

  if (action === "delete") {
    if (!question?.id) return;
    if (question?.isLocked) {
      alert(t("txnSettings_alert_lockedQuestion"));
      return;
    }

    if (!confirm(t("txnSettings_confirm_deleteQuestion"))) {
      return;
    }

    try {
      await transactionSettingsApi.deleteGatewaySupportQuestion(question.id);
      await refreshGatewayQuestionDetail(gateway);
      alert(t("txnSettings_alert_questionDeleted"));
    } catch (err) {
      console.error("Failed to delete support question:", err);
      const data = err.response?.data;
      const rawMsg = data?.message || err.message || "";
      alert(
        tParams(
          "txnSettings_alert_questionDeleteFailed",
          "Failed to delete question: {msg}",
          {
            msg: translateApiErrorMessage(
              data?.errorCode,
              rawMsg || t("common_unknownError"),
            ),
          },
        ),
      );
    }
    return;
  }

  editingGatewayQuestionId.value = question?.id || "";
  gatewayQuestionModalScope.value = scope;
  gatewayQuestionModalGateway.gatewayKey = gateway.gatewayKey || "";
  gatewayQuestionModalGateway.gatewayName = gateway.gatewayName || "";
  gatewayQuestionModalGateway.gatewaySettingId =
    gateway.gatewaySettingId || gateway.id || "";
  gatewayQuestionModalGateway.paymentGatewayId =
    gateway.gatewaySettingId || gateway.id || "";
  gatewayQuestionModalQuestion.value = question ? { ...question } : null;
  showGatewayQuestionModal.value = true;
};

const saveGatewayQuestion = async (payload) => {
  if (!gatewayQuestionModalGateway.gatewaySettingId) return;

  gatewayQuestionSaving.value = true;

  const requestPayload = {
    gatewaySettingId: gatewayQuestionModalGateway.gatewaySettingId,
    name: payload.name,
    hintText: payload.hintText || "",
    questionType: payload.questionType,
    validationRules: payload.validationRules || "",
    options: Array.isArray(payload.options) ? payload.options : [],
    scope: payload.scope,
    isLocked: Boolean(payload.isLocked),
    isActive: payload.isActive,
  };

  try {
    const wasEditing = Boolean(editingGatewayQuestionId.value);
    if (editingGatewayQuestionId.value) {
      await transactionSettingsApi.updateGatewaySupportQuestion(
        editingGatewayQuestionId.value,
        requestPayload,
      );
    } else {
      await transactionSettingsApi.createGatewaySupportQuestion(requestPayload);
    }

    const gateway = {
      gatewayKey: gatewayQuestionModalGateway.gatewayKey,
      gatewayName: gatewayQuestionModalGateway.gatewayName,
      gatewaySettingId: gatewayQuestionModalGateway.gatewaySettingId,
    };

    await refreshGatewayQuestionDetail(gateway);
    closeGatewayQuestionModal();
    alert(
      tParams(
        "txnSettings_alert_questionSaved",
        "Question {verb} successfully!",
        {
          verb: wasEditing
            ? t("txnSettings_verb_updated")
            : t("txnSettings_verb_created"),
        },
      ),
    );
  } catch (err) {
    console.error("Failed to save support question:", err);
    const data = err.response?.data;
    const rawMsg = data?.message || err.message || "";
    alert(
      tParams(
        "txnSettings_alert_questionSaveFailed",
        "Failed to save question: {msg}",
        {
          msg: translateApiErrorMessage(
            data?.errorCode,
            rawMsg || t("common_unknownError"),
          ),
        },
      ),
    );
  } finally {
    gatewayQuestionSaving.value = false;
  }
};

const maskSensitiveValue = (value) => {
  if (!value) return "-";

  const text = String(value);
  if (text.length <= 8) {
    return "*".repeat(text.length);
  }

  return `${text.slice(0, 4)}••••${text.slice(-4)}`;
};

const getGatewayIconPreviewClass = (type) =>
  String(type || "fiat").toLowerCase() === "crypto"
    ? "gateway-icon-preview--crypto"
    : "gateway-icon-preview--fiat";

const formatConfigData = (value) => {
  if (!value) return "";
  if (typeof value === "string") {
    try {
      return JSON.stringify(JSON.parse(value), null, 2);
    } catch {
      return value;
    }
  }

  if (typeof value === "object") {
    try {
      return JSON.stringify(value, null, 2);
    } catch {
      return String(value);
    }
  }

  return String(value);
};

const createEmptyDisplayContentEntry = (scope) => {
  if (scope === "internal_transfer") {
    return {
      title: "",
      content: "",
      iconClass: "",
    };
  }

  return {
    content: "",
  };
};

const normalizeDisplayContentEntry = (item = {}, scope = "") => {
  if (scope === "internal_transfer") {
    return {
      title: item.title || "",
      content: item.content || "",
      iconClass: item.iconClass || item.icon_class || "",
    };
  }

  return {
    content: item.content || "",
  };
};

const parseDisplayContentJson = (value, scope) => {
  let parsed = value;

  if (typeof value === "string") {
    try {
      parsed = JSON.parse(value);
    } catch {
      parsed = [];
    }
  }

  if (!Array.isArray(parsed)) return [];
  return parsed.map((item) => normalizeDisplayContentEntry(item, scope));
};

const applyDisplayContentForms = (items = []) => {
  const normalizedItems = Array.isArray(items)
    ? items
    : Object.entries(items || {}).map(([scope, value]) => ({
        scope,
        ...(value || {}),
      }));

  const byScope = normalizedItems.reduce((acc, item) => {
    const scope = item.scope || "";
    if (scope) {
      acc[scope] = item;
    }
    return acc;
  }, {});

  Object.assign(displayContentForms.deposit, {
    scope: "deposit",
    items: parseDisplayContentJson(
      byScope.deposit?.contentJson || [],
      "deposit",
    ),
  });

  Object.assign(displayContentForms.withdrawal, {
    scope: "withdrawal",
    items: parseDisplayContentJson(
      byScope.withdrawal?.contentJson || [],
      "withdrawal",
    ),
  });

  Object.assign(displayContentForms.internalTransfer, {
    scope: "internal_transfer",
    items: parseDisplayContentJson(
      byScope.internal_transfer?.contentJson ||
        byScope["internal-transfer"]?.contentJson ||
        [],
      "internal_transfer",
    ),
  });
};

const addDisplayContentItem = (scope) => {
  if (scope === "deposit") {
    displayContentForms.deposit.items.push(
      createEmptyDisplayContentEntry(scope),
    );
    return;
  }

  if (scope === "withdrawal") {
    displayContentForms.withdrawal.items.push(
      createEmptyDisplayContentEntry(scope),
    );
    return;
  }

  if (scope === "internal_transfer") {
    displayContentForms.internalTransfer.items.push(
      createEmptyDisplayContentEntry(scope),
    );
  }
};

const removeDisplayContentItem = (scope, index) => {
  if (scope === "deposit") {
    displayContentForms.deposit.items.splice(index, 1);
    return;
  }

  if (scope === "withdrawal") {
    displayContentForms.withdrawal.items.splice(index, 1);
    return;
  }

  if (scope === "internal_transfer") {
    displayContentForms.internalTransfer.items.splice(index, 1);
  }
};

// 加载所有设置
const loadSettings = async () => {
  loading.value = true;
  error.value = null;

  try {
    // 先获取网关配置
    const gatewaysResponse = await transactionSettingsApi.getGateways();
    if (gatewaysResponse.success) {
      const gateways = gatewaysResponse.data || [];
      gatewayList.value = gateways;
    }

    const displayContentsResponse =
      await transactionSettingsApi.getDisplayContents();
    if (displayContentsResponse.success) {
      applyDisplayContentForms(displayContentsResponse.data || []);
    }

    // 获取通知设置
    const notificationsResponse =
      await transactionSettingsApi.getNotifications();
    if (notificationsResponse.success) {
      notifications.value = notificationsResponse.data || {};
    }

    // 获取安全设置
    const securityResponse = await transactionSettingsApi.getSecuritySettings();
    if (securityResponse.success && securityResponse.data) {
      // 确保布尔值正确转换
      const loadedSettings = securityResponse.data;
      Object.assign(securitySettings, {
        salesManagerNotifications: Boolean(
          loadedSettings.salesManagerNotifications,
        ),
        withdrawalOtpRequired: Boolean(loadedSettings.withdrawalOtpRequired),
        otpValidityMinutes: Number(loadedSettings.otpValidityMinutes) || 10,
        requireVerifiedWalletOnly: Boolean(
          loadedSettings.requireVerifiedWalletOnly,
        ),
        requireWithdrawalVerification: Boolean(
          loadedSettings.requireWithdrawalVerification,
        ),
        verificationMaxFileSize:
          Number(loadedSettings.verificationMaxFileSize) || 5,
        autoRejectUnverified: Boolean(loadedSettings.autoRejectUnverified),
      });
    }

    // 入金自动审核国家多选：使用专有接口（从 countryList 表），与旧 getCountries 分离避免不兼容
    const countriesResponse =
      await transactionSettingsApi.getCountriesForAutoApproval();
    if (countriesResponse.success) {
      const raw = countriesResponse.data;
      availableCountries.value =
        raw && raw.data ? raw.data : Array.isArray(raw) ? raw : [];
    }

    // 客户标签列表（与 Clients List 一致：leadTags，用于入金规则多选）
    try {
      const tagsRes = await leadsService.getLeadTags(false);
      const raw = tagsRes?.data;
      const arr = raw && raw.data ? raw.data : Array.isArray(raw) ? raw : [];
      if (Array.isArray(arr)) {
        availableClientTags.value = arr;
      }
    } catch (e) {
      console.warn("Failed to load client tags for auto-approval:", e);
      availableClientTags.value = [];
    }

    // 获取自动审批规则（API 返回规则数组，按 ruleType 映射）
    const autoApprovalResponse =
      await transactionSettingsApi.getAutoApprovalRules();
    if (autoApprovalResponse.success && autoApprovalResponse.data) {
      const raw = autoApprovalResponse.data;
      const rules = raw && raw.data ? raw.data : Array.isArray(raw) ? raw : [];
      const depositRule = rules.find((r) => r.ruleType === "deposit");
      const withdrawalRule = rules.find((r) => r.ruleType === "withdrawal");
      const internalTransferRule = rules.find(
        (r) => r.ruleType === "internal_transfer",
      );
      if (depositRule) {
        autoApprovalRules.deposit.enabled = !!depositRule.isEnabled;
        autoApprovalRules.deposit.minAmount =
          Number(depositRule.minAmount) || 0;
        autoApprovalRules.deposit.maxAmount =
          Number(depositRule.maxAmount) || 10000;
        autoApprovalRules.deposit.allowedCountries = Array.isArray(
          depositRule.allowedCountries,
        )
          ? depositRule.allowedCountries
          : depositRule.allowedCountries
            ? JSON.parse(depositRule.allowedCountries || "[]")
            : ["ALL"];
        autoApprovalRules.deposit.requiredTags = toTagArray(
          depositRule.requiredClientTags,
        );
        autoApprovalRules.deposit.excludedTags = toTagArray(
          depositRule.excludedClientTags,
        );
      }
      if (withdrawalRule) {
        autoApprovalRules.withdrawal.enabled = !!withdrawalRule.isEnabled;
        autoApprovalRules.withdrawal.minAmount =
          Number(withdrawalRule.minAmount) || 0;
        autoApprovalRules.withdrawal.maxAmount =
          Number(withdrawalRule.maxAmount) || 5000;
        autoApprovalRules.withdrawal.allowedCountries = Array.isArray(
          withdrawalRule.allowedCountries,
        )
          ? withdrawalRule.allowedCountries
          : withdrawalRule.allowedCountries
            ? JSON.parse(withdrawalRule.allowedCountries || "[]")
            : ["ALL"];
        autoApprovalRules.withdrawal.requiredTags = toTagArray(
          withdrawalRule.requiredClientTags,
        );
        autoApprovalRules.withdrawal.excludedTags = toTagArray(
          withdrawalRule.excludedClientTags,
        );
      }
      if (internalTransferRule) {
        autoApprovalRules.internalTransfer.enabled =
          !!internalTransferRule.isEnabled;
        autoApprovalRules.internalTransfer.minAmount =
          Number(internalTransferRule.minAmount) || 0;
        autoApprovalRules.internalTransfer.maxAmount =
          Number(internalTransferRule.maxAmount) || 10000;
        autoApprovalRules.internalTransfer.allowedCountries = Array.isArray(
          internalTransferRule.allowedCountries,
        )
          ? internalTransferRule.allowedCountries
          : internalTransferRule.allowedCountries
            ? JSON.parse(internalTransferRule.allowedCountries || "[]")
            : ["ALL"];
        autoApprovalRules.internalTransfer.requiredTags = toTagArray(
          internalTransferRule.requiredClientTags,
        );
        autoApprovalRules.internalTransfer.excludedTags = toTagArray(
          internalTransferRule.excludedClientTags,
        );
      }
    }

    // 获取汇率设置
    const exchangeRatesResponse =
      await transactionSettingsApi.getExchangeRates(true);
    if (exchangeRatesResponse.success) {
      exchangeRates.value = exchangeRatesResponse.data || [];
    }

    loading.value = false;
  } catch (err) {
    console.error("Failed to load settings:", err);
    const data = err.response?.data;
    const rawMsg = data?.message || err.message || "";
    error.value = translateApiErrorMessage(
      data?.errorCode,
      rawMsg || t("txnSettings_err_load"),
    );
    loading.value = false;
  }
};

const openGatewayModal = (gateway) => {
  editingGatewayKey.value = gateway.gatewayKey;
  gatewayForm.gatewayKey = gateway.gatewayKey || "";
  gatewayForm.gatewayName = gateway.gatewayName || "";
  gatewayForm.type = gateway.type || "fiat";
  gatewayForm.iconClass = gateway.iconClass || "";
  gatewayForm.isEnabled = Boolean(gateway.isEnabled);
  gatewayForm.isDepositEnabled = Boolean(gateway.isDepositEnabled);
  gatewayForm.isWithdrawalEnabled = Boolean(gateway.isWithdrawalEnabled);
  gatewayForm.displayOrder = Number(gateway.displayOrder) || 0;
  gatewayForm.processingTime = gateway.processingTime || "";
  gatewayForm.withdrawalProcessingTime = gateway.withdrawalProcessingTime || "";
  gatewayForm.environment = gateway.environment || "production";
  gatewayForm.appId = gateway.appId || "";
  gatewayForm.apiKey = gateway.apiKey || "";
  gatewayForm.secretKey = gateway.secretKey || "";
  gatewayForm.merchantName = gateway.merchantName || "";
  gatewayForm.webhookUrl = gateway.webhookUrl || "";
  gatewayForm.returnUrl = gateway.returnUrl || "";
  gatewayForm.supportedFiatCurrencies = normalizeCurrencyList(
    gateway.supportedFiatCurrencies,
  );
  gatewayForm.supportedCryptoCurrencies = normalizeCurrencyList(
    gateway.supportedCryptoCurrencies,
  );
  gatewayForm.configData = formatConfigData(gateway.configData);
  showGatewayModal.value = true;
};

const closeGatewayModal = () => {
  editingGatewayKey.value = "";
  gatewayForm.gatewayKey = "";
  gatewayForm.gatewayName = "";
  gatewayForm.type = "fiat";
  gatewayForm.iconClass = "";
  gatewayForm.isEnabled = false;
  gatewayForm.isDepositEnabled = false;
  gatewayForm.isWithdrawalEnabled = false;
  gatewayForm.displayOrder = 0;
  gatewayForm.processingTime = "";
  gatewayForm.withdrawalProcessingTime = "";
  gatewayForm.environment = "production";
  gatewayForm.appId = "";
  gatewayForm.apiKey = "";
  gatewayForm.secretKey = "";
  gatewayForm.merchantName = "";
  gatewayForm.webhookUrl = "";
  gatewayForm.returnUrl = "";
  gatewayForm.supportedFiatCurrencies = [];
  gatewayForm.supportedCryptoCurrencies = [];
  gatewayForm.configData = "";
  showGatewayModal.value = false;
};

const openGatewayCapabilityModal = (gateway) => {
  editingGatewayCapabilityKey.value = gateway.gatewayKey || "";
  gatewayCapabilityForm.gatewayKey = gateway.gatewayKey || "";
  gatewayCapabilityForm.gatewayName = gateway.gatewayName || "";
  gatewayCapabilityForm.type = gateway.type || "fiat";
  gatewayCapabilityForm.iconClass = gateway.iconClass || "";
  gatewayCapabilityForm.isDepositEnabled = Boolean(gateway.isDepositEnabled);
  gatewayCapabilityForm.isWithdrawalEnabled = Boolean(
    gateway.isWithdrawalEnabled,
  );
  gatewayCapabilityForm.supportedFiatCurrencies = normalizeCurrencyList(
    gateway.supportedFiatCurrencies,
  );
  gatewayCapabilityForm.supportedCryptoCurrencies = normalizeCurrencyList(
    gateway.supportedCryptoCurrencies,
  );
  showGatewayCapabilityModal.value = true;
};

const closeGatewayCapabilityModal = () => {
  editingGatewayCapabilityKey.value = "";
  gatewayCapabilityForm.gatewayKey = "";
  gatewayCapabilityForm.gatewayName = "";
  gatewayCapabilityForm.type = "fiat";
  gatewayCapabilityForm.iconClass = "";
  gatewayCapabilityForm.isDepositEnabled = false;
  gatewayCapabilityForm.isWithdrawalEnabled = false;
  gatewayCapabilityForm.supportedFiatCurrencies = [];
  gatewayCapabilityForm.supportedCryptoCurrencies = [];
  showGatewayCapabilityModal.value = false;
};

const applyGatewayFundingData = (data = {}) => {
  const normalizedData = normalizeGatewayFundingData(data);
  gatewayFundingForm.minDeposit = normalizedData.minDeposit;
  gatewayFundingForm.maxDeposit = normalizedData.maxDeposit;
  gatewayFundingForm.minWithdrawal = normalizedData.minWithdrawal;
  gatewayFundingForm.maxWithdrawal = normalizedData.maxWithdrawal;
  gatewayFundingForm.depositRules = normalizedData.depositRules;
  gatewayFundingForm.withdrawalRules = normalizedData.withdrawalRules;
};

const resetGatewayFundingModal = () => {
  fundingModalGatewaySettingId.value = "";
  fundingModalGateway.gatewayKey = "";
  fundingModalGateway.gatewayName = "";
  fundingModalGateway.iconClass = "";
  gatewayFeeModalType.value = "deposit";
  applyGatewayFundingData();
  gatewayFundingLoading.value = false;
  showGatewayFundingModal.value = false;
  showGatewayFeeModal.value = false;
};

const openGatewayFundingModal = async (gateway) => {
  const gatewaySettingId = gateway.gatewaySettingId || gateway.id || "";

  if (!gatewaySettingId) {
    alert(t("txnSettings_alert_missingGatewayIdFunding"));
    return;
  }

  fundingModalGatewaySettingId.value = gatewaySettingId;
  fundingModalGateway.gatewayKey = gateway.gatewayKey || "";
  fundingModalGateway.gatewayName = gateway.gatewayName || "";
  fundingModalGateway.iconClass = gateway.iconClass || "";
  showGatewayFeeModal.value = false;
  showGatewayFundingModal.value = true;
  gatewayFundingLoading.value = true;

  try {
    const response =
      await transactionSettingsApi.getGatewayFundingSettings(gatewaySettingId);
    const fundingData = response?.data || response || {};
    applyGatewayFundingData(fundingData);
  } catch (err) {
    console.error("Failed to load funding setting:", err);
    applyGatewayFundingData();
    const data = err.response?.data;
    const rawMsg = data?.message || err.message || "";
    alert(
      tParams(
        "txnSettings_alert_loadFundingFailed",
        "Failed to load funding setting: {msg}",
        {
          msg: translateApiErrorMessage(
            data?.errorCode,
            rawMsg || t("common_unknownError"),
          ),
        },
      ),
    );
  } finally {
    gatewayFundingLoading.value = false;
  }
};

const closeGatewayFundingModal = () => {
  resetGatewayFundingModal();
};

const openGatewayFeeModal = async (gateway, type = "deposit") => {
  gatewayFeeModalType.value = type === "withdrawal" ? "withdrawal" : "deposit";
  await openGatewayFundingModal(gateway);
  showGatewayFundingModal.value = false;
  showGatewayFeeModal.value = true;
};

const closeGatewayFeeModal = () => {
  resetGatewayFundingModal();
};

const extractGatewayDisplayContent = (data = {}) => {
  const payload =
    data?.data && typeof data.data === "object" ? data.data : data;

  return {
    depositContent: String(payload?.depositContent || ""),
    withdrawalContent: String(payload?.withdrawalContent || ""),
  };
};

const resetGatewayDisplayModal = () => {
  displayModalGatewaySettingId.value = "";
  displayModalType.value = "deposit";
  displayModalGateway.gatewayKey = "";
  displayModalGateway.gatewayName = "";
  displayModalGateway.iconClass = "";
  gatewayDisplayForm.depositContent = "";
  gatewayDisplayForm.withdrawalContent = "";
  gatewayDisplayLoading.value = false;
  showGatewayDisplayModal.value = false;
};

const openGatewayDisplayModal = async (gateway, type = "deposit") => {
  const gatewaySettingId = gateway.gatewaySettingId || gateway.id || "";

  if (!gatewaySettingId) {
    alert(t("txnSettings_alert_missingGatewayIdDisplay"));
    return;
  }

  displayModalGatewaySettingId.value = gatewaySettingId;
  displayModalType.value = type === "withdrawal" ? "withdrawal" : "deposit";
  displayModalGateway.gatewayKey = gateway.gatewayKey || "";
  displayModalGateway.gatewayName = gateway.gatewayName || "";
  displayModalGateway.iconClass = gateway.iconClass || "";
  gatewayDisplayForm.depositContent = "";
  gatewayDisplayForm.withdrawalContent = "";
  showGatewayDisplayModal.value = true;
  gatewayDisplayLoading.value = true;

  try {
    const response =
      await transactionSettingsApi.getGatewayDisplayContent(gatewaySettingId);
    const displayData = response?.data || response || {};
    const content = extractGatewayDisplayContent(displayData);
    gatewayDisplayForm.depositContent = content.depositContent;
    gatewayDisplayForm.withdrawalContent = content.withdrawalContent;
  } catch (err) {
    console.error("Failed to load display setting:", err);
    gatewayDisplayForm.depositContent = "";
    gatewayDisplayForm.withdrawalContent = "";
    const data = err.response?.data;
    const rawMsg = data?.message || err.message || "";
    alert(
      tParams(
        "txnSettings_alert_loadDisplayFailed",
        "Failed to load display setting: {msg}",
        {
          msg: translateApiErrorMessage(
            data?.errorCode,
            rawMsg || t("common_unknownError"),
          ),
        },
      ),
    );
  } finally {
    gatewayDisplayLoading.value = false;
  }
};

const closeGatewayDisplayModal = () => {
  resetGatewayDisplayModal();
};

const saveGatewayEdit = async () => {
  if (!editingGatewayKey.value) return;

  saving.value = true;

  try {
    let parsedConfigData = null;

    if (gatewayForm.configData && String(gatewayForm.configData).trim()) {
      try {
        parsedConfigData = JSON.parse(gatewayForm.configData);
      } catch {
        parsedConfigData = gatewayForm.configData;
      }
    }

    await transactionSettingsApi.updateGateway(editingGatewayKey.value, {
      gatewayName: gatewayForm.gatewayName || null,
      isEnabled: gatewayForm.isEnabled ? 1 : 0,
      type: gatewayForm.type || "fiat",
      iconClass: gatewayForm.iconClass || null,
      isDepositEnabled: gatewayForm.isDepositEnabled ? 1 : 0,
      isWithdrawalEnabled: gatewayForm.isWithdrawalEnabled ? 1 : 0,
      displayOrder: Number(gatewayForm.displayOrder) || 0,
      processingTime: gatewayForm.processingTime || null,
      withdrawalProcessingTime: gatewayForm.withdrawalProcessingTime || null,
      environment: gatewayForm.environment || "production",
      appId: gatewayForm.appId || null,
      apiKey: gatewayForm.apiKey || null,
      secretKey: gatewayForm.secretKey || null,
      merchantName: gatewayForm.merchantName || null,
      webhookUrl: gatewayForm.webhookUrl || null,
      returnUrl: gatewayForm.returnUrl || null,
      supportedFiatCurrencies: gatewayForm.supportedFiatCurrencies,
      supportedCryptoCurrencies: gatewayForm.supportedCryptoCurrencies,
      configData: parsedConfigData,
    });

    alert(t("txnSettings_alert_gatewaySaved"));
    closeGatewayModal();
    await loadSettings();
  } catch (err) {
    console.error("Failed to save gateway settings:", err);
    const data = err.response?.data;
    const rawMsg = data?.message || err.message || "";
    alert(
      tParams(
        "txnSettings_alert_gatewaySaveFailed",
        "Failed to save gateway settings: {msg}",
        { msg: translateApiErrorMessage(data?.errorCode, rawMsg) },
      ),
    );
  } finally {
    saving.value = false;
  }
};

const saveGatewayCapabilityEdit = async () => {
  if (!editingGatewayCapabilityKey.value) return;

  saving.value = true;

  try {
    await transactionSettingsApi.updateGateway(
      editingGatewayCapabilityKey.value,
      {
        isDepositEnabled: gatewayCapabilityForm.isDepositEnabled ? 1 : 0,
        isWithdrawalEnabled: gatewayCapabilityForm.isWithdrawalEnabled ? 1 : 0,
        supportedFiatCurrencies: gatewayCapabilityForm.supportedFiatCurrencies,
        supportedCryptoCurrencies:
          gatewayCapabilityForm.supportedCryptoCurrencies,
      },
    );

    alert(t("txnSettings_alert_capabilitySaved"));
    closeGatewayCapabilityModal();
    await loadSettings();
  } catch (err) {
    console.error("Failed to save gateway availability settings:", err);
    const data = err.response?.data;
    const rawMsg = data?.message || err.message || "";
    alert(
      tParams(
        "txnSettings_alert_capabilitySaveFailed",
        "Failed to save availability & currency: {msg}",
        {
          msg: translateApiErrorMessage(
            data?.errorCode,
            rawMsg || t("common_unknownError"),
          ),
        },
      ),
    );
  } finally {
    saving.value = false;
  }
};

const clampPercentage = (value) => {
  const num = Number(value ?? 0);
  if (Number.isNaN(num)) return 0;
  if (num < 0) return 0;
  if (num > 100) return 100;
  return num / 100;
};

const normalizeRulesForSave = (rules = [], transactionType = "deposit") => {
  const normalizedRules = sortGatewayRules(rules, transactionType);
  const rulesForMode =
    inferGatewayFundingMode() === "single"
      ? normalizedRules
          .slice(0, 1)
          .map((rule) => ({ ...rule, thresholdAmount: 0 }))
      : normalizedRules;

  return rulesForMode.map((rule, index) => ({
    thresholdAmount: Number(rule.thresholdAmount ?? 0) || 0,
    feeMode: rule.feeMode || "fixed",
    percentage:
      rule.feeMode === "dynamic" ? clampPercentage(rule.percentage) : 0,
    fixed: rule.feeMode === "fixed" ? Number(rule.fixed ?? 0) : 0,
    minFee: normalizeNullableNumber(rule.minFee),
    maxFee: normalizeNullableNumber(rule.maxFee),
    chargeToClient: rule.chargeToClient !== false,
    sortOrder: index,
    isActive: rule.isActive !== false,
  }));
};

const saveGatewayFunding = async () => {
  if (!fundingModalGatewaySettingId.value) return;

  if (!isGatewayFundingFormValid.value) {
    alert(t("txnSettings_alert_fundingInvalid"));
    return;
  }

  saving.value = true;

  try {
    const gatewayKey = fundingModalGateway.gatewayKey || "";

    if (showGatewayFundingModal.value) {
      await transactionSettingsApi.updateGatewayLimitSettings(
        fundingModalGatewaySettingId.value,
        {
          minDeposit: normalizeNullableNumber(gatewayFundingForm.minDeposit),
          maxDeposit: normalizeNullableNumber(gatewayFundingForm.maxDeposit),
          minWithdrawal: normalizeNullableNumber(
            gatewayFundingForm.minWithdrawal,
          ),
          maxWithdrawal: normalizeNullableNumber(
            gatewayFundingForm.maxWithdrawal,
          ),
          isActive: true,
          notes: null,
        },
      );

      syncGatewayFundingDetailState(gatewayKey);
      alert(t("txnSettings_alert_limitSaved"));
      closeGatewayFundingModal();
    } else {
      const feePayload = normalizeRulesForSave(
        gatewayFeeModalType.value === "withdrawal"
          ? gatewayFundingForm.withdrawalRules
          : gatewayFundingForm.depositRules,
        gatewayFeeModalType.value,
      );

      if (gatewayFeeModalType.value === "withdrawal") {
        await transactionSettingsApi.updateGatewayWithdrawFeeSettings(
          fundingModalGatewaySettingId.value,
          feePayload,
        );
        alert(t("txnSettings_alert_withdrawFeeSaved"));
      } else {
        await transactionSettingsApi.updateGatewayDepositFeeSettings(
          fundingModalGatewaySettingId.value,
          feePayload,
        );
        alert(t("txnSettings_alert_depositFeeSaved"));
      }

      syncGatewayFundingDetailState(gatewayKey);
      closeGatewayFeeModal();
    }

    await loadSettings();
  } catch (err) {
    console.error("Failed to save gateway funding settings:", err);
    const data = err.response?.data;
    const rawMsg = data?.message || err.message || "";
    alert(
      tParams(
        "txnSettings_alert_fundingSaveFailed",
        "Failed to save setting: {msg}",
        {
          msg: translateApiErrorMessage(
            data?.errorCode,
            rawMsg || t("common_unknownError"),
          ),
        },
      ),
    );
  } finally {
    saving.value = false;
  }
};

const saveGatewayDisplayContent = async () => {
  if (!displayModalGatewaySettingId.value) return;

  saving.value = true;

  try {
    const depositContent = String(
      gatewayDisplayForm.depositContent || "",
    ).trim();
    const withdrawalContent = String(
      gatewayDisplayForm.withdrawalContent || "",
    ).trim();

    await transactionSettingsApi.updateGatewayDisplayContent(
      displayModalGatewaySettingId.value,
      {
        depositContent,
        withdrawalContent,
      },
    );

    const gatewayKey = displayModalGateway.gatewayKey || "";
    if (gatewayKey) {
      const state = getGatewayDisplayDetailState(gatewayKey);
      state.data = {
        depositContent,
        withdrawalContent,
      };
      state.error = "";
      state.loading = false;
    }

    alert(t("txnSettings_alert_displaySettingSaved"));
    closeGatewayDisplayModal();
    await loadSettings();
  } catch (err) {
    console.error("Failed to save display setting:", err);
    const data = err.response?.data;
    const rawMsg = data?.message || err.message || "";
    alert(
      tParams(
        "txnSettings_alert_displaySettingSaveFailed",
        "Failed to save display setting: {msg}",
        {
          msg: translateApiErrorMessage(
            data?.errorCode,
            rawMsg || t("common_unknownError"),
          ),
        },
      ),
    );
  } finally {
    saving.value = false;
  }
};

const toggleGatewayStatus = async (gateway) => {
  const actionKey = gateway.isEnabled
    ? "txnSettings_action_disableVerb"
    : "txnSettings_action_enableVerb";
  if (
    !confirm(
      tParams(
        "txnSettings_confirm_toggleGateway",
        "Are you sure you want to {action} {name}?",
        {
          action: t(actionKey),
          name: gateway.gatewayName || gateway.gatewayKey,
        },
      ),
    )
  ) {
    return;
  }

  saving.value = true;

  try {
    await transactionSettingsApi.updateGateway(gateway.gatewayKey, {
      isEnabled: gateway.isEnabled ? 0 : 1,
    });

    await loadSettings();
  } catch (err) {
    console.error("Failed to toggle gateway status:", err);
    const data = err.response?.data;
    const rawMsg = data?.message || err.message || "";
    alert(
      tParams(
        "txnSettings_alert_gatewayStatusFailed",
        "Failed to update gateway status: {msg}",
        { msg: translateApiErrorMessage(data?.errorCode, rawMsg) },
      ),
    );
  } finally {
    saving.value = false;
  }
};

const deleteGatewayConfig = async (gateway) => {
  const gatewayName = gateway.gatewayName || gateway.gatewayKey;
  if (
    !confirm(
      tParams(
        "txnSettings_confirm_deleteGateway",
        "Are you sure you want to delete {name}?",
        { name: gatewayName },
      ),
    )
  ) {
    return;
  }

  saving.value = true;

  try {
    await transactionSettingsApi.deleteGateway(gateway.gatewayKey);
    alert(t("txnSettings_alert_gatewayDeleted"));
    await loadSettings();
  } catch (err) {
    console.error("Failed to delete gateway:", err);
    const data = err.response?.data;
    const rawMsg = data?.message || err.message || "";
    alert(
      tParams(
        "txnSettings_alert_gatewayDeleteFailed",
        "Failed to delete gateway: {msg}",
        {
          msg: translateApiErrorMessage(
            data?.errorCode,
            rawMsg || t("common_unknownError"),
          ),
        },
      ),
    );
  } finally {
    saving.value = false;
  }
};

// 处理通知设置变更
const handleNotificationChange = (change) => {
  pendingChanges.notifications = change;
  hasChanges.notifications = true;
};

// 处理安全设置变更
const handleSecuritySettingsChange = () => {
  hasChanges.security = true;
};

// 保存通知和安全设置
const saveNotificationSettings = async () => {
  saving.value = true;

  try {
    const promises = [];

    // 保存通知设置
    for (const [key, value] of Object.entries(pendingChanges.notifications)) {
      promises.push(transactionSettingsApi.updateNotification(key, value));
    }

    // 保存安全设置
    if (hasChanges.security) {
      promises.push(
        transactionSettingsApi.updateSecuritySettings(securitySettings),
      );
    }

    await Promise.all(promises);

    alert(t("txnSettings_alert_notificationSaved"));
    hasChanges.notifications = false;
    hasChanges.security = false;
    pendingChanges.notifications = {};
    await loadSettings();
  } catch (err) {
    console.error("Failed to save settings:", err);
    const data = err.response?.data;
    const rawMsg = data?.message || err.message || "";
    alert(
      tParams(
        "txnSettings_alert_notificationSaveFailed",
        "Failed to save settings: {msg}",
        { msg: translateApiErrorMessage(data?.errorCode, rawMsg) },
      ),
    );
  } finally {
    saving.value = false;
  }
};

// 切换卡片折叠状态
const toggleCard = (cardKey) => {
  collapsedCards[cardKey] = !collapsedCards[cardKey];

  // 保存到localStorage
  localStorage.setItem(
    "transactionSettingsCollapsed",
    JSON.stringify(collapsedCards),
  );
};

// 恢复折叠状态
const restoreCollapsedState = () => {
  const saved = localStorage.getItem("transactionSettingsCollapsed");
  if (saved) {
    try {
      const parsed = JSON.parse(saved);
      Object.assign(collapsedCards, parsed);
    } catch (err) {
      console.error("Failed to restore collapsed state:", err);
    }
  }
};

// 将后端标签字段（逗号分隔或数组）转为数组
const toTagArray = (v) => {
  if (!v) return [];
  if (Array.isArray(v)) return v.filter(Boolean);
  return String(v)
    .split(",")
    .map((s) => s.trim())
    .filter(Boolean);
};

// 国家多选：选 ALL 则只保留 ALL；选其它国家则去掉 ALL
const onDepositAllowedCountriesChange = () => {
  const arr = autoApprovalRules.deposit.allowedCountries;
  if (!Array.isArray(arr)) return;
  if (arr.includes("ALL")) {
    autoApprovalRules.deposit.allowedCountries = ["ALL"];
  } else {
    autoApprovalRules.deposit.allowedCountries = arr.filter((c) => c !== "ALL");
  }
  handleAutoApprovalChange();
};

// Required tags 与 Excluded tags 互斥：选入 Required 则从 Excluded 移除，反之亦然
const onDepositRequiredTagsChange = () => {
  const req = autoApprovalRules.deposit.requiredTags;
  if (Array.isArray(req)) {
    autoApprovalRules.deposit.excludedTags = (
      autoApprovalRules.deposit.excludedTags || []
    ).filter((t) => !req.includes(t));
  }
  handleAutoApprovalChange();
};

const onDepositExcludedTagsChange = () => {
  const exc = autoApprovalRules.deposit.excludedTags;
  if (Array.isArray(exc)) {
    autoApprovalRules.deposit.requiredTags = (
      autoApprovalRules.deposit.requiredTags || []
    ).filter((t) => !exc.includes(t));
  }
  handleAutoApprovalChange();
};

const onInternalTransferAllowedCountriesChange = () => {
  const arr = autoApprovalRules.internalTransfer.allowedCountries;
  if (!Array.isArray(arr)) return;
  if (arr.includes("ALL")) {
    autoApprovalRules.internalTransfer.allowedCountries = ["ALL"];
  } else {
    autoApprovalRules.internalTransfer.allowedCountries = arr.filter(
      (c) => c !== "ALL",
    );
  }
  handleAutoApprovalChange();
};

const onInternalTransferRequiredTagsChange = () => {
  const req = autoApprovalRules.internalTransfer.requiredTags;
  if (Array.isArray(req)) {
    autoApprovalRules.internalTransfer.excludedTags = (
      autoApprovalRules.internalTransfer.excludedTags || []
    ).filter((t) => !req.includes(t));
  }
  handleAutoApprovalChange();
};

const onInternalTransferExcludedTagsChange = () => {
  const exc = autoApprovalRules.internalTransfer.excludedTags;
  if (Array.isArray(exc)) {
    autoApprovalRules.internalTransfer.requiredTags = (
      autoApprovalRules.internalTransfer.requiredTags || []
    ).filter((t) => !exc.includes(t));
  }
  handleAutoApprovalChange();
};

// 处理自动审批规则变更
const handleAutoApprovalChange = () => {
  pendingChanges.autoApproval = {
    deposit: { ...autoApprovalRules.deposit },
    withdrawal: { ...autoApprovalRules.withdrawal },
    internalTransfer: { ...autoApprovalRules.internalTransfer },
  };
  hasChanges.autoApproval = true;
};

// 保存自动审批规则（按后端字段名：isEnabled, requiredClientTags, excludedClientTags）
const saveAutoApprovalRules = async () => {
  saving.value = true;

  try {
    // 使用 0/1 而非 true/false，避免线上环境对布尔类型绑定 tinyint 不一致导致保存失败
    const payload = {
      deposit: {
        isEnabled: autoApprovalRules.deposit.enabled ? 1 : 0,
        minAmount: Number(autoApprovalRules.deposit.minAmount) || 0,
        maxAmount: Number(autoApprovalRules.deposit.maxAmount) || 10000,
        allowedCountries: Array.isArray(
          autoApprovalRules.deposit.allowedCountries,
        )
          ? autoApprovalRules.deposit.allowedCountries
          : ["ALL"],
        requiredClientTags: Array.isArray(
          autoApprovalRules.deposit.requiredTags,
        )
          ? autoApprovalRules.deposit.requiredTags
          : [],
        excludedClientTags: Array.isArray(
          autoApprovalRules.deposit.excludedTags,
        )
          ? autoApprovalRules.deposit.excludedTags
          : [],
      },
      withdrawal: {
        isEnabled: autoApprovalRules.withdrawal.enabled ? 1 : 0,
        minAmount: Number(autoApprovalRules.withdrawal.minAmount) || 0,
        maxAmount: Number(autoApprovalRules.withdrawal.maxAmount) || 5000,
        allowedCountries: Array.isArray(
          autoApprovalRules.withdrawal.allowedCountries,
        )
          ? autoApprovalRules.withdrawal.allowedCountries
          : ["ALL"],
        requiredClientTags: Array.isArray(
          autoApprovalRules.withdrawal.requiredTags,
        )
          ? autoApprovalRules.withdrawal.requiredTags
          : [],
        excludedClientTags: Array.isArray(
          autoApprovalRules.withdrawal.excludedTags,
        )
          ? autoApprovalRules.withdrawal.excludedTags
          : [],
        requireKycVerified: autoApprovalRules.withdrawal.requireKycVerified
          ? 1
          : 0,
        checkSavedWallet: autoApprovalRules.withdrawal.checkSavedWallet ? 1 : 0,
      },
      internal_transfer: {
        isEnabled: autoApprovalRules.internalTransfer.enabled ? 1 : 0,
        minAmount: Number(autoApprovalRules.internalTransfer.minAmount) || 0,
        maxAmount:
          Number(autoApprovalRules.internalTransfer.maxAmount) || 10000,
        allowedCountries: Array.isArray(
          autoApprovalRules.internalTransfer.allowedCountries,
        )
          ? autoApprovalRules.internalTransfer.allowedCountries
          : ["ALL"],
        requiredClientTags: Array.isArray(
          autoApprovalRules.internalTransfer.requiredTags,
        )
          ? autoApprovalRules.internalTransfer.requiredTags
          : [],
        excludedClientTags: Array.isArray(
          autoApprovalRules.internalTransfer.excludedTags,
        )
          ? autoApprovalRules.internalTransfer.excludedTags
          : [],
      },
    };
    await transactionSettingsApi.updateAutoApprovalRules(payload);

    alert(t("txnSettings_alert_autoApprovalSaved"));
    hasChanges.autoApproval = false;
    pendingChanges.autoApproval = {};
    await loadSettings();
  } catch (err) {
    console.error("Failed to save auto-approval rules:", err);
    const data = err.response?.data;
    const rawMsg = data?.message || err.message || "";
    alert(
      tParams(
        "txnSettings_alert_autoApprovalSaveFailed",
        "Failed to save auto-approval rules: {msg}",
        { msg: translateApiErrorMessage(data?.errorCode, rawMsg) },
      ),
    );
  } finally {
    saving.value = false;
  }
};

const saveDisplayContent = async (scope) => {
  saving.value = true;

  try {
    let payload = {};

    if (scope === "deposit") {
      payload = {
        contentJson: displayContentForms.deposit.items
          .map((item) => ({
            content: String(item.content || "").trim(),
          }))
          .filter((item) => item.content),
      };
    } else if (scope === "withdrawal") {
      payload = {
        contentJson: displayContentForms.withdrawal.items
          .map((item) => ({
            content: String(item.content || "").trim(),
          }))
          .filter((item) => item.content),
      };
    } else if (scope === "internal_transfer") {
      payload = {
        contentJson: displayContentForms.internalTransfer.items
          .map((item) => ({
            title: String(item.title || "").trim(),
            content: String(item.content || "").trim(),
            iconClass: String(item.iconClass || "").trim(),
          }))
          .filter((item) => item.title || item.content || item.iconClass),
      };
    } else {
      throw new Error(`Unsupported display content scope: ${scope}`);
    }

    await transactionSettingsApi.updateDisplayContent(scope, payload);
    alert(t("txnSettings_alert_displayContentSaved"));
    await loadSettings();
  } catch (err) {
    console.error("Failed to save display content:", err);
    const data = err.response?.data;
    const rawMsg = data?.message || err.message || "";
    alert(
      tParams(
        "txnSettings_alert_displayContentSaveFailed",
        "Failed to save display content: {msg}",
        { msg: translateApiErrorMessage(data?.errorCode, rawMsg) },
      ),
    );
  } finally {
    saving.value = false;
  }
};

// 汇率相关方法
const editExchangeRate = (rate) => {
  editingRate.value = rate;
  rateForm.type = rate.type || "fiat";
  rateForm.currencyCode = rate.currencyCode;
  rateForm.currencyName = rate.currencyName || "";
  rateForm.currencySymbol = rate.currencySymbol || "$";
  rateForm.exchangeRate = parseFloat(rate.exchangeRate);
  showAddRateModal.value = true;
};

const closeRateModal = () => {
  showAddRateModal.value = false;
  editingRate.value = null;
  rateForm.type = "fiat";
  rateForm.currencyCode = "";
  rateForm.currencyName = "";
  rateForm.currencySymbol = "$";
  rateForm.exchangeRate = null;
};

const openRateSettingsModal = (rate) => {
  editingRateSettings.value = rate;
  rateSettingsForm.exchangeRate = parseFloat(rate.exchangeRate);
  rateSettingsForm.depositType = normalizeExchangeRateMode(
    rate.depositType || "fixed",
  );
  rateSettingsForm.depositBias = normalizeExchangeRateBiasForForm(
    rate.depositBias,
    rate.depositType,
  );
  rateSettingsForm.withdrawType = normalizeExchangeRateMode(
    rate.withdrawType || "fixed",
  );
  rateSettingsForm.withdrawBias = normalizeExchangeRateBiasForForm(
    rate.withdrawBias,
    rate.withdrawType,
  );
  syncRateSettingsTargetFromBias("deposit");
  syncRateSettingsTargetFromBias("withdraw");
  showRateSettingsModal.value = true;
};

const closeRateSettingsModal = () => {
  showRateSettingsModal.value = false;
  editingRateSettings.value = null;
  rateSettingsForm.exchangeRate = null;
  rateSettingsForm.depositType = "fixed";
  rateSettingsForm.depositBias = 0;
  rateSettingsForm.depositTarget = null;
  rateSettingsForm.withdrawType = "fixed";
  rateSettingsForm.withdrawBias = 0;
  rateSettingsForm.withdrawTarget = null;
  rateSettingsErrors.exchangeRate = "";
  rateSettingsErrors.depositTarget = "";
  rateSettingsErrors.depositBias = "";
  rateSettingsErrors.withdrawTarget = "";
  rateSettingsErrors.withdrawBias = "";
};

const normalizeExchangeRateText = (value) => String(value ?? "").trim();

const areExchangeRateNumbersEqual = (left, right, digits = 8) => {
  const normalizedLeft = roundExchangeRateValue(left, digits);
  const normalizedRight = roundExchangeRateValue(right, digits);
  return normalizedLeft === normalizedRight;
};

const setChangedField = (
  target,
  key,
  nextValue,
  currentValue,
  isEqual = (left, right) => left === right,
) => {
  if (!isEqual(nextValue, currentValue)) {
    target[key] = nextValue;
  }
};

const saveExchangeRate = async () => {
  if (!isRateFormValid.value) {
    alert(t("txnSettings_alert_rateFormInvalid"));
    return;
  }

  saving.value = true;

  try {
    if (editingRate.value) {
      const data = {
        type: String(rateForm.type || "").trim(),
        currencyCode: String(rateForm.currencyCode || "")
          .trim()
          .toUpperCase(),
        currencyName: normalizeExchangeRateText(rateForm.currencyName) || null,
        currencySymbol:
          normalizeExchangeRateText(rateForm.currencySymbol) || "$",
      };

      // 更新
      const response = await transactionSettingsApi.updateExchangeRate(
        editingRate.value.id,
        data,
      );
      if (response.success) {
        alert(t("txnSettings_alert_rateUpdated"));
        await loadSettings();
        closeRateModal();
      } else {
        const rawMsg = response.message || "";
        alert(
          tParams(
            "txnSettings_alert_rateUpdateFailed",
            "Failed to update exchange rate: {msg}",
            {
              msg: translateApiErrorMessage(
                response.errorCode,
                rawMsg || t("common_unknownError"),
              ),
            },
          ),
        );
      }
    } else {
      const data = {
        type: rateForm.type,
        currencyCode: String(rateForm.currencyCode || "")
          .trim()
          .toUpperCase(),
        currencyName: normalizeExchangeRateText(rateForm.currencyName) || null,
        currencySymbol:
          normalizeExchangeRateText(rateForm.currencySymbol) || "$",
        exchangeRate: rateForm.exchangeRate,
        isActive: 1,
      };

      // 创建
      const response = await transactionSettingsApi.createExchangeRate(data);
      if (response.success) {
        alert(t("txnSettings_alert_rateCreated"));
        await loadSettings();
        closeRateModal();
      } else {
        const rawMsg = response.message || "";
        alert(
          tParams(
            "txnSettings_alert_rateCreateFailed",
            "Failed to create exchange rate: {msg}",
            {
              msg: translateApiErrorMessage(
                response.errorCode,
                rawMsg || t("common_unknownError"),
              ),
            },
          ),
        );
      }
    }
  } catch (err) {
    console.error("Failed to save exchange rate:", err);
    const data = err.response?.data;
    const rawMsg = data?.message || err.message || "";
    alert(
      tParams(
        "txnSettings_alert_rateSaveFailed",
        "Failed to save exchange rate: {msg}",
        { msg: translateApiErrorMessage(data?.errorCode, rawMsg) },
      ),
    );
  } finally {
    saving.value = false;
  }
};

const saveRateSettings = async () => {
  if (!isRateSettingsFormValid.value || !editingRateSettings.value) {
    alert(t("txnSettings_alert_rateFormInvalid"));
    return;
  }

  saving.value = true;

  try {
    const data = {};
    setChangedField(
      data,
      "exchangeRate",
      normalizeExchangeRateNumber(rateSettingsForm.exchangeRate),
      normalizeExchangeRateNumber(editingRateSettings.value.exchangeRate),
      areExchangeRateNumbersEqual,
    );
    setChangedField(
      data,
      "depositType",
      normalizeExchangeRateMode(rateSettingsForm.depositType),
      normalizeExchangeRateMode(
        editingRateSettings.value.depositType || "fixed",
      ),
    );
    setChangedField(
      data,
      "depositBias",
      normalizeExchangeRateBiasForSave(
        rateSettingsForm.depositBias,
        rateSettingsForm.depositType,
      ),
      normalizeExchangeRateNumber(editingRateSettings.value.depositBias) ?? 0,
      areExchangeRateNumbersEqual,
    );
    setChangedField(
      data,
      "withdrawType",
      normalizeExchangeRateMode(rateSettingsForm.withdrawType),
      normalizeExchangeRateMode(
        editingRateSettings.value.withdrawType || "fixed",
      ),
    );
    setChangedField(
      data,
      "withdrawBias",
      normalizeExchangeRateBiasForSave(
        rateSettingsForm.withdrawBias,
        rateSettingsForm.withdrawType,
      ),
      normalizeExchangeRateNumber(editingRateSettings.value.withdrawBias) ?? 0,
      areExchangeRateNumbersEqual,
    );

    if (Object.keys(data).length === 0) {
      closeRateSettingsModal();
      return;
    }

    const response = await transactionSettingsApi.updateExchangeRate(
      editingRateSettings.value.id,
      data,
    );
    if (response.success) {
      alert(t("txnSettings_alert_rateUpdated"));
      await loadSettings();
      closeRateSettingsModal();
    } else {
      const rawMsg = response.message || "";
      alert(
        tParams(
          "txnSettings_alert_rateUpdateFailed",
          "Failed to update exchange rate: {msg}",
          {
            msg: translateApiErrorMessage(
              response.errorCode,
              rawMsg || t("common_unknownError"),
            ),
          },
        ),
      );
    }
  } catch (err) {
    console.error("Failed to save rate settings:", err);
    const data = err.response?.data;
    const rawMsg = data?.message || err.message || "";
    alert(
      tParams(
        "txnSettings_alert_rateSaveFailed",
        "Failed to save exchange rate: {msg}",
        { msg: translateApiErrorMessage(data?.errorCode, rawMsg) },
      ),
    );
  } finally {
    saving.value = false;
  }
};

const toggleExchangeRateStatus = async (id) => {
  if (!confirm(t("txnSettings_confirm_toggleRate"))) {
    return;
  }

  saving.value = true;

  try {
    const response = await transactionSettingsApi.toggleExchangeRate(id);
    if (response.success) {
      await loadSettings();
    } else {
      const rawMsg = response.message || "";
      alert(
        tParams(
          "txnSettings_alert_rateStatusFailed",
          "Failed to update exchange rate status: {msg}",
          {
            msg: translateApiErrorMessage(
              response.errorCode,
              rawMsg || t("common_unknownError"),
            ),
          },
        ),
      );
    }
  } catch (err) {
    console.error("Failed to toggle exchange rate:", err);
    const data = err.response?.data;
    const rawMsg = data?.message || err.message || "";
    alert(
      tParams(
        "txnSettings_alert_rateStatusFailed",
        "Failed to update exchange rate status: {msg}",
        { msg: translateApiErrorMessage(data?.errorCode, rawMsg) },
      ),
    );
  } finally {
    saving.value = false;
  }
};

// 最近一次自动同步的时间 = auto 行里最大的 lastSyncedAt（都是服务器时间，字符串可直接比大小）
const latestAutoSyncTime = computed(() => {
  let max = "";
  for (const r of exchangeRates.value || []) {
    if ((r.syncMode || "auto") === "auto" && r.lastSyncedAt) {
      const s = String(r.lastSyncedAt);
      if (s > max) max = s;
    }
  }
  return max;
});

// auto 行且（从没同步过 或 落后于最近一次同步）→ 说明没在被刷，标红。手动行不算。
const isRateSyncStale = (rate) => {
  if ((rate.syncMode || "auto") !== "auto") return false;
  const latest = latestAutoSyncTime.value;
  if (!latest) return false; // 还没发生过任何同步，先不标红
  return !rate.lastSyncedAt || String(rate.lastSyncedAt) < latest;
};

// 后端存的是 'Y-m-d H:i:s'（服务器时区），展示到分钟即可，不用 Date 解析避免时区偏移
const formatSyncUpdatedAt = (value) => {
  if (!value) return "";
  const s = String(value).replace("T", " ");
  return s.length >= 16 ? s.slice(0, 16) : s;
};

const toggleRateSyncMode = async (rate) => {
  const toManual = (rate.syncMode || "auto") === "auto";
  if (
    !confirm(
      toManual
        ? t("txnSettings_confirm_syncToManual")
        : t("txnSettings_confirm_syncToAuto"),
    )
  ) {
    return;
  }

  saving.value = true;

  try {
    const response = await transactionSettingsApi.toggleExchangeRateSyncMode(
      rate.id,
    );
    if (response.success) {
      await loadSettings();
    } else {
      const rawMsg = response.message || "";
      alert(
        tParams(
          "txnSettings_alert_rateSyncModeFailed",
          "Failed to update sync mode: {msg}",
          {
            msg: translateApiErrorMessage(
              response.errorCode,
              rawMsg || t("common_unknownError"),
            ),
          },
        ),
      );
    }
  } catch (err) {
    console.error("Failed to toggle exchange rate sync mode:", err);
    const data = err.response?.data;
    const rawMsg = data?.message || err.message || "";
    alert(
      tParams(
        "txnSettings_alert_rateSyncModeFailed",
        "Failed to update sync mode: {msg}",
        { msg: translateApiErrorMessage(data?.errorCode, rawMsg) },
      ),
    );
  } finally {
    saving.value = false;
  }
};

const confirmDeleteRate = (rate) => {
  if (
    confirm(
      tParams(
        "txnSettings_confirm_deleteRate",
        "Are you sure you want to delete the exchange rate for {code}? This action cannot be undone.",
        { code: rate.currencyCode },
      ),
    )
  ) {
    deleteExchangeRate(rate.id);
  }
};

const deleteExchangeRate = async (id) => {
  saving.value = true;

  try {
    const response = await transactionSettingsApi.deleteExchangeRate(id);
    if (response.success) {
      alert(t("txnSettings_alert_rateDeleted"));
      await loadSettings();
    } else {
      const rawMsg = response.message || "";
      alert(
        tParams(
          "txnSettings_alert_rateDeleteFailed",
          "Failed to delete exchange rate: {msg}",
          {
            msg: translateApiErrorMessage(
              response.errorCode,
              rawMsg || t("common_unknownError"),
            ),
          },
        ),
      );
    }
  } catch (err) {
    console.error("Failed to delete exchange rate:", err);
    const data = err.response?.data;
    const rawMsg = data?.message || err.message || "";
    alert(
      tParams(
        "txnSettings_alert_rateDeleteFailed",
        "Failed to delete exchange rate: {msg}",
        { msg: translateApiErrorMessage(data?.errorCode, rawMsg) },
      ),
    );
  } finally {
    saving.value = false;
  }
};

// 页面挂载时加载数据
onMounted(async () => {
  restoreCollapsedState();
  await loadSettings();
});
</script>

<style scoped>
.transaction-settings-page {
  padding: 40px 20px;
  max-width: 1200px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 2px solid var(--color-border);
}

.page-actions {
  display: flex;
  align-items: center;
  gap: 20px;
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

.loading-container p,
.error-container p {
  font-size: 16px;
  color: var(--color-text);
  margin-bottom: 20px;
}

.settings-content {
  display: flex;
  flex-direction: column;
  gap: 30px;
}

.settings-card {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  overflow: hidden;
  transition: all 0.3s ease;
}

.card-header {
  background: var(--color-surface-soft);
  padding: 20px 30px;
  border-bottom: 2px solid var(--color-border);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: space-between;
  user-select: none;
  transition: background 0.2s ease;
}

.card-header:hover {
  background: var(--color-surface-muted);
}

.card-header-content {
  flex: 1;
}

.card-header h2 {
  font-size: 20px;
  color: var(--color-ink);
  margin-bottom: 5px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.card-header h2 i {
  color: var(--color-brand);
}

.card-header p {
  font-size: 14px;
  color: var(--color-muted);
}

.card-collapse-icon {
  font-size: 18px;
  color: var(--color-faint);
  transition:
    transform 0.3s ease,
    color 0.2s ease;
  margin-left: 20px;
}

.card-collapse-icon.rotated {
  transform: rotate(180deg);
}

.card-header:hover .card-collapse-icon {
  color: var(--color-brand);
}

.card-body {
  padding: 30px;
}

.card-expand-enter-active,
.card-expand-leave-active {
  transition: all 0.3s ease;
  overflow: hidden;
}

.card-expand-enter-from,
.card-expand-leave-to {
  max-height: 0;
  opacity: 0;
  padding-top: 0;
  padding-bottom: 0;
}

.card-expand-enter-to,
.card-expand-leave-from {
  max-height: 5000px;
  opacity: 1;
}

.card-footer {
  display: flex;
  justify-content: flex-end;
  margin-top: 30px;
  padding-top: 20px;
  border-top: 2px solid var(--color-border);
}

.card-footer-full {
  justify-content: flex-start;
}

.card-footer-full .info-box {
  width: 100%;
}

.info-banner {
  background: var(--color-brand-soft);
  border-left: 4px solid var(--color-brand);
  padding: 14px 16px;
  border-radius: var(--radius-md);
  margin-bottom: 20px;
}

.info-banner-content {
  display: flex;
  align-items: start;
  gap: 10px;
}

.info-banner-icon {
  color: var(--color-brand);
  font-size: 18px;
  flex-shrink: 0;
  margin-top: 2px;
}

.info-banner-text {
  font-size: 13px;
  color: var(--color-text);
  line-height: 1.6;
}

.info-box {
  background: var(--color-brand-soft);
  border-left: 4px solid var(--color-brand);
  padding: 15px 20px;
  border-radius: var(--radius-md);
  margin-top: 20px;
}

.info-box.warning {
  background: var(--color-danger-soft);
  border-left-color: var(--color-danger-border);
}

.info-box p {
  color: var(--color-text);
  font-size: 13px;
  line-height: 1.6;
  margin: 0;
}

.btn {
  padding: 14px 32px;
  border-radius: var(--radius-md);
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;
  display: inline-flex;
  align-items: center;
  gap: 10px;
}

.btn-primary {
  background: var(--color-brand-solid);
  color: white;
  box-shadow: 0 4px 15px rgba(var(--color-brand-rgb), 0.4);
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(var(--color-brand-rgb), 0.5);
}

.btn-primary:disabled {
  background: var(--color-border-strong);
  color: var(--color-faint);
  box-shadow: none;
  cursor: not-allowed;
  opacity: 0.6;
}

/* Client Text Settings */
.text-settings-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
  gap: 30px;
}

.text-section {
  background: var(--color-surface-soft);
  padding: 20px;
  border-radius: var(--radius-md);
  border-left: 4px solid var(--color-brand);
}

.text-section h3 {
  font-size: 16px;
  color: var(--color-ink);
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.text-section h3 i {
  color: var(--color-brand);
}

.form-textarea {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  font-family: inherit;
  transition: all 0.3s ease;
  resize: vertical;
}

.form-textarea:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

/* Auto-Approval Rules */
.auto-approval-section {
  background: var(--color-surface-soft);
  padding: 20px;
  border-radius: var(--radius-md);
  margin-bottom: 20px;
  border-left: 4px solid var(--color-brand);
}

.auto-approval-disabled-note {
  border-left-color: var(--color-border);
  color: var(--color-muted);
}

.auto-approval-disabled-note p {
  margin: 0;
  display: flex;
  align-items: center;
  gap: 8px;
}

.auto-approval-disabled-note i {
  color: var(--color-faint);
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.section-header h3 {
  font-size: 16px;
  color: var(--color-ink);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 10px;
}

.section-header h3 i {
  color: var(--color-brand);
}

/* Toggle Switch */
.toggle-switch {
  position: relative;
  display: inline-block;
  width: 52px;
  height: 28px;
}

.toggle-switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.toggle-slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: var(--color-border-strong);
  transition: 0.4s;
  border-radius: 34px;
}

.toggle-slider:before {
  position: absolute;
  content: "";
  height: 20px;
  width: 20px;
  left: 4px;
  bottom: 4px;
  background-color: var(--color-surface);
  transition: 0.4s;
  border-radius: 50%;
}

.toggle-switch input:checked + .toggle-slider {
  background: var(--color-brand-solid);
}

.toggle-switch input:checked + .toggle-slider:before {
  transform: translateX(24px);
}

.toggle-switch.disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.toggle-switch.disabled .toggle-slider {
  cursor: not-allowed;
  background-color: var(--color-border) !important;
}

.toggle-switch.disabled input:checked + .toggle-slider {
  background: var(--color-success-soft) !important;
  opacity: 0.8;
}

.toggle-switch.disabled input:disabled + .toggle-slider {
  cursor: not-allowed;
}

.checkbox-label input[type="checkbox"]:disabled {
  cursor: not-allowed;
  opacity: 0.7;
}

.form-select-multiple:disabled {
  cursor: not-allowed;
  opacity: 0.7;
  background-color: var(--color-surface-soft);
}

.rule-settings {
  padding-top: 20px;
}

.form-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 20px;
}

.form-group {
  margin-bottom: 20px;
}

.rate-config-section {
  margin-top: 8px;
  padding: 20px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface-soft);
}

.rate-config-section-header {
  margin-bottom: 16px;
}

.rate-config-section-header h4 {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
  color: var(--color-ink);
}

.form-label {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 8px;
}

.form-label i {
  color: var(--color-brand);
  margin-right: 4px;
}

.form-input,
.form-select-multiple {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  transition: all 0.3s ease;
}

.form-input:focus,
.form-select-multiple:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.form-input.input-error,
.form-select-multiple.input-error {
  border-color: var(--color-danger);
  box-shadow: 0 0 0 3px rgba(229, 62, 62, 0.1);
}

.form-select-multiple {
  min-height: 120px;
}

.form-help {
  font-size: 12px;
  color: var(--color-muted);
  margin-top: 6px;
  display: block;
}

.form-error {
  font-size: 12px;
  color: var(--color-danger);
  margin-top: 6px;
  display: block;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  user-select: none;
}

.settings-section .checkbox-label {
  padding: 12px 14px;
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
}

.checkbox-label input[type="checkbox"] {
  width: 18px;
  height: 18px;
  cursor: pointer;
}

.checkbox-label span {
  font-size: 14px;
  color: var(--color-ink);
}

.switch-toggle {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  cursor: pointer;
  user-select: none;
}

.switch-toggle input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}

.switch-toggle-track {
  position: relative;
  width: 52px;
  height: 30px;
  border-radius: 999px;
  background: var(--color-border);
  border: 1px solid #cbd5e1;
  transition: all 0.2s ease;
  flex-shrink: 0;
}

.switch-toggle-thumb {
  position: absolute;
  top: 3px;
  left: 3px;
  width: 22px;
  height: 22px;
  border-radius: 999px;
  background: var(--color-surface);
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.16);
  transition: all 0.2s ease;
}

.switch-toggle input:checked + .switch-toggle-track {
  background: #22c55e;
  border-color: var(--color-success);
}

.switch-toggle input:checked + .switch-toggle-track .switch-toggle-thumb {
  transform: translateX(22px);
}

.switch-toggle-label {
  font-size: 13px;
  font-weight: 700;
  color: var(--color-text);
}

.btn-add-item {
  padding: 6px 14px;
  background: var(--color-brand-solid);
  color: white;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.btn-add-item:hover {
  background: var(--color-brand-strong);
  transform: translateY(-1px);
}

.empty-tips {
  text-align: center;
  padding: 30px 20px;
  background: var(--color-surface);
  border: 2px dashed var(--color-border);
  border-radius: var(--radius-md);
  color: var(--color-faint);
}

.empty-tips i {
  font-size: 32px;
  margin-bottom: 10px;
  display: block;
}

.empty-tips p {
  font-size: 14px;
  margin: 0;
}

.tip-item-editor {
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 20px;
  margin-bottom: 16px;
  transition: all 0.3s ease;
}

.tip-item-editor:hover {
  border-color: var(--color-border-strong);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.display-content-item {
  max-width: none;
}

.internal-transfer-item {
  display: block;
  width: 100%;
  max-width: 100%;
}

.display-content-inline-item {
  display: flex;
  align-items: flex-end;
  gap: 16px;
  padding: 16px 18px;
}

.display-content-inline-header {
  flex: 0 0 auto;
  align-items: center;
  gap: 10px;
  margin-bottom: 0;
  padding-bottom: 0;
  border-bottom: none;
}

.display-content-inline-field {
  flex: 1 1 auto;
  margin-bottom: 0;
}

.display-content-compact-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 16px;
}

.tip-item-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
  padding-bottom: 12px;
  border-bottom: 2px solid var(--color-surface-soft);
}

.tip-number {
  font-size: 14px;
  font-weight: 700;
  color: var(--color-brand);
  background: var(--color-brand-soft);
  padding: 4px 12px;
  border-radius: var(--radius-sm);
}

.btn-remove-item {
  padding: 6px 10px;
  background: var(--color-danger-soft);
  color: var(--color-danger);
  border: none;
  border-radius: var(--radius-sm);
  font-size: 13px;
  cursor: pointer;
  transition: all 0.3s ease;
}

.btn-remove-item:hover {
  background: var(--color-danger-solid);
  color: white;
}

.settings-section {
  background: var(--color-surface-soft);
  padding: 20px;
  border-radius: var(--radius-md);
  margin-bottom: 20px;
  border-left: 4px solid var(--color-brand);
}

.section-description {
  font-size: 13px;
  color: var(--color-muted);
  margin-top: 4px;
  margin-bottom: 0;
}

.setting-details {
  margin-top: 16px;
  padding-top: 16px;
  border-top: 2px solid var(--color-border);
}

.section-actions {
  display: flex;
  justify-content: flex-end;
  margin-top: 12px;
  padding-top: 12px;
  border-top: 2px solid var(--color-border);
}

@media (max-width: 768px) {
  .display-content-inline-item {
    display: block;
  }

  .display-content-inline-header {
    margin-bottom: 12px;
  }

  .internal-transfer-item {
    display: block;
    width: 100%;
    max-width: 100%;
  }

  .display-content-compact-grid {
    grid-template-columns: 1fr;
  }
}

.info-banner.warning {
  background: var(--color-danger-soft);
  border-left-color: var(--color-danger-border);
}

.info-banner.warning .info-banner-icon {
  color: var(--color-danger-border);
}

/* Verification Requirements Styles */
.verification-requirements {
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 20px;
  margin: 20px 0;
}

.verification-requirements h4 {
  font-size: 15px;
  color: var(--color-ink);
  margin-bottom: 20px;
  padding-bottom: 12px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  align-items: center;
  gap: 10px;
}

.verification-requirements h4 i {
  color: var(--color-brand);
}

.requirement-section {
  margin-bottom: 20px;
}

.requirement-section:last-child {
  margin-bottom: 0;
}

.requirement-section h5 {
  font-size: 14px;
  color: var(--color-brand);
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 600;
}

.requirement-section ul {
  margin: 0;
  padding-left: 24px;
  color: var(--color-text);
}

.requirement-section li {
  font-size: 13px;
  line-height: 1.8;
  margin-bottom: 6px;
}

.requirement-section li:last-child {
  margin-bottom: 0;
}

/* Gateway Styles */
.gateway-list {
  margin-top: 0;
}

.gateway-table {
  overflow-x: auto;
  overflow-y: hidden;
  -webkit-overflow-scrolling: touch;
  background: var(--color-surface);
  border-radius: var(--radius-md);
  border: 2px solid var(--color-border);
}

.gateway-table table {
  width: 100%;
  min-width: 100%;
  border-collapse: collapse;
}

.gateway-table thead {
  background: var(--color-surface-soft);
}

.gateway-table th {
  padding: 15px;
  text-align: left;
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 2px solid var(--color-border);
}

.gateway-table th.gateway-table__actions,
.gateway-table td.gateway-table__actions {
  width: 200px;
  min-width: 200px;
  white-space: nowrap;
}

.gateway-table td {
  padding: 15px;
  border-bottom: 1px solid var(--color-border);
  color: var(--color-ink);
  font-size: 14px;
  vertical-align: top;
}

/* Status / deposit / withdrawal: keep badge on one line (CJK e.g. 启用) */
.gateway-table > table > thead > tr > th:nth-child(2),
.gateway-table > table > thead > tr > th:nth-child(3),
.gateway-table > table > thead > tr > th:nth-child(4),
.gateway-table
  > table
  > tbody
  > tr:not(.gateway-detail-row):not(.gateway-group-row)
  > td:nth-child(2),
.gateway-table
  > table
  > tbody
  > tr:not(.gateway-detail-row):not(.gateway-group-row)
  > td:nth-child(3),
.gateway-table
  > table
  > tbody
  > tr:not(.gateway-detail-row):not(.gateway-group-row)
  > td:nth-child(4) {
  white-space: nowrap;
  vertical-align: middle;
}

.gateway-table
  > table
  > tbody
  > tr:not(.gateway-detail-row):not(.gateway-group-row)
  > td:nth-child(2)
  .status-badge,
.gateway-table
  > table
  > tbody
  > tr:not(.gateway-detail-row):not(.gateway-group-row)
  > td:nth-child(3)
  .status-badge,
.gateway-table
  > table
  > tbody
  > tr:not(.gateway-detail-row):not(.gateway-group-row)
  > td:nth-child(4)
  .status-badge {
  white-space: nowrap;
  word-break: keep-all;
}

.gateway-table tbody tr:hover {
  background: var(--color-surface-soft);
}

.gateway-table tbody tr.gateway-group-row,
.gateway-table tbody tr.gateway-group-row:hover {
  background: var(--color-surface-soft);
}

.gateway-table tbody tr.gateway-group-row td {
  padding: 12px 15px;
  background: var(--color-surface-soft);
  border-bottom: 2px solid var(--color-border);
  vertical-align: middle;
}

.gateway-table tbody tr.inactive {
  opacity: 0.7;
}

.gateway-table tbody tr:last-child td {
  border-bottom: none;
}

.gateway-summary-row {
  background: var(--color-brand-soft);
}

.gateway-detail-row {
  display: none;
}

.gateway-detail-row.show {
  display: table-row;
}

.gateway-detail-row td {
  background: var(--color-brand-soft);
  padding: 0;
}

.gateway-detail-row:hover {
  background: transparent !important;
}

.gateway-detail-content {
  padding: 24px;
  background: var(--color-surface-soft);
}

.gateway-detail-sections {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 18px;
}

.gateway-detail-section {
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 25px;
}

.gateway-detail-section.full-width {
  grid-column: 1 / -1;
}

.gateway-detail-section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
}

.gateway-detail-section h3 {
  margin: 0;
  font-size: 15px;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 8px;
}

.gateway-detail-section h3 i {
  color: var(--color-brand);
}

.gateway-section-edit-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 10px;
  border: 1px solid #dbe3f0;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.gateway-section-edit-btn:hover {
  border-color: #cbd5e1;
  background: var(--color-surface-soft);
  color: var(--color-ink);
}

.gateway-detail-field {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  padding: 10px 0;
  border-bottom: 1px solid var(--color-surface-muted);
}

.gateway-detail-field:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

.gateway-detail-label {
  flex-shrink: 0;
  font-size: 13px;
  font-weight: 600;
  color: var(--color-muted);
}

.gateway-detail-value {
  color: var(--color-ink);
  font-size: 14px;
  text-align: right;
}

.gateway-detail-value--mono {
  font-family: "Courier New", monospace;
}

.gateway-detail-value--break {
  word-break: break-all;
}

.gateway-detail-value--wrap {
  white-space: normal;
  word-break: break-word;
}

.gateway-detail-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
}

.gateway-detail-card {
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: 16px;
}

.gateway-detail-card-title {
  margin-bottom: 12px;
  color: var(--color-muted);
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

.gateway-detail-card-value {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  color: var(--color-ink);
  font-size: 14px;
}

.gateway-detail-card-value + .gateway-detail-card-value {
  margin-top: 10px;
}

.gateway-detail-empty {
  color: var(--color-faint);
  font-size: 14px;
}

.gateway-row-actions {
  display: flex;
  flex-wrap: nowrap;
  align-items: center;
  gap: 8px;
}

.gateway-action-btn,
.btn-detail {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  white-space: nowrap;
  flex-shrink: 0;
  border-radius: var(--radius-md);
  border: 1px solid #dbe3f0;
  background: var(--color-surface);
  color: var(--color-text);
  cursor: pointer;
  transition: all 0.2s ease;
  font-size: 13px;
  font-weight: 600;
}

.gateway-action-btn {
  padding: 10px 14px;
}

.gateway-action-btn--icon {
  justify-content: center;
  min-width: 38px;
  padding: 8px 10px;
}

.btn-detail {
  padding: 8px 14px;
}

.gateway-action-btn:hover,
.btn-detail:hover {
  border-color: var(--color-brand);
  color: var(--color-brand);
  background: var(--color-surface-soft);
}

.gateway-action-btn--danger {
  background: var(--color-danger-soft);
  border-color: var(--color-danger-border);
  color: var(--color-danger);
}

.gateway-action-btn--danger:hover {
  background: var(--color-danger-soft);
  border-color: var(--color-danger-border);
  color: var(--color-danger);
}

.gateway-cell {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 220px;
}

.gateway-cell-text {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.gateway-cell-text span {
  font-size: 12px;
  color: var(--color-muted);
}

.gateway-icon-preview {
  width: 40px;
  height: 40px;
  border-radius: var(--radius-md);
  background: var(--color-brand-soft);
  color: var(--color-brand);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-size: 18px;
}

.gateway-icon-preview--fiat {
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.gateway-icon-preview--crypto {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.gateway-icon-preview.large {
  width: 56px;
  height: 56px;
  font-size: 24px;
}

.gateway-modal-content {
  max-width: 1000px;
  width: 90%;
}

.gateway-display-modal-content {
  max-width: 1100px;
}

.gateway-display-editor {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.gateway-display-editor :deep(.document-editor) {
  min-height: 320px;
}

.gateway-funding-display {
  display: flex;
  flex-direction: column;
}

.gateway-funding-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 18px;
}

.gateway-funding-grid--limits,
.gateway-funding-grid--fees {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.gateway-funding-card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 14px;
  padding: 18px;
}

.gateway-funding-card h5 {
  margin: 0 0 16px;
  font-size: 15px;
  color: var(--color-ink);
}

.gateway-funding-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
}

.gateway-funding-card-header h5 {
  margin-bottom: 0;
}

.gateway-quick-amounts {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 12px;
}

.gateway-quick-amount-chip {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 10px;
  border-radius: 999px;
  background: var(--color-info-soft);
  border: 1px solid #bfdbfe;
  color: #1e3a8a;
  font-size: 13px;
  font-weight: 600;
}

.gateway-quick-amount-remove {
  border: none;
  background: transparent;
  color: var(--color-muted);
  cursor: pointer;
  padding: 0;
  line-height: 1;
}

.gateway-quick-amount-remove:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.gateway-quick-amount-add {
  display: flex;
  gap: 12px;
  align-items: flex-end;
}

.gateway-quick-amount-add-field {
  flex: 1;
  min-width: 0;
  margin-bottom: 0;
}

.gateway-quick-amount-add-btn {
  flex-shrink: 0;
  padding: 12px 20px;
  white-space: nowrap;
}

.gateway-quick-amount-add-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

.gateway-funding-stat {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 10px 0;
  border-bottom: 1px solid var(--color-surface-muted);
}

.gateway-funding-stat:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

.gateway-funding-stat-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--color-muted);
}

.gateway-funding-stat strong {
  font-size: 14px;
  color: var(--color-ink);
}

.gateway-funding-rules {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.gateway-funding-rule {
  padding: 14px;
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
}

.gateway-funding-rule-range {
  margin-bottom: 6px;
  font-size: 13px;
  font-weight: 600;
  color: var(--color-muted);
}

.gateway-funding-rule-value {
  font-size: 14px;
  color: var(--color-ink);
  line-height: 1.5;
}

.gateway-display-preview-list {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.gateway-display-preview-item {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.gateway-display-preview-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.gateway-display-preview-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--color-muted);
}

.gateway-display-preview-card {
  padding: 16px 18px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
}

.gateway-display-preview-content {
  color: var(--color-ink);
  line-height: 1.6;
  word-break: break-word;
}

.gateway-display-empty {
  margin: 0;
  color: var(--color-faint);
  font-size: 14px;
}

.gateway-display-preview-content :deep(p) {
  margin: 0;
}

.gateway-display-preview-content :deep(p + p) {
  margin-top: 8px;
}

.gateway-display-preview-content :deep(ul),
.gateway-display-preview-content :deep(ol) {
  margin: 10px 0;
  padding-left: 22px;
  list-style-position: outside;
}

.gateway-display-preview-content :deep(li) {
  margin: 6px 0;
  padding-left: 2px;
}

.gateway-display-preview-content :deep(ul) {
  list-style-type: disc;
}

.gateway-display-preview-content :deep(ol) {
  list-style-type: decimal;
}

.gateway-display-preview-content :deep(ul ul) {
  list-style-type: circle;
}

.gateway-display-preview-content :deep(ol ol) {
  list-style-type: lower-alpha;
}

.gateway-display-preview-content :deep(li::marker) {
  color: var(--color-text);
  font-weight: 600;
}

.gateway-question-groups {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.gateway-question-group {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  overflow: hidden;
  transition: all 0.2s ease;
}

.gateway-question-group:hover {
  border-color: var(--color-brand);
}

.gateway-question-group-header {
  padding: 15px 20px;
  background: var(--color-surface-soft);
  border-bottom: 1px solid var(--color-border);
}

.gateway-question-group-info {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
}

.gateway-question-group-actions {
  display: flex;
  gap: 6px;
}

.gateway-question-group-indicator {
  width: 4px;
  height: 24px;
  border-radius: 2px;
  background: var(--color-brand-solid);
}

.gateway-question-group-title {
  margin: 0;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
}

.gateway-question-group-count {
  background: var(--color-brand-solid);
  color: white;
  padding: 4px 10px;
  border-radius: var(--radius-lg);
  font-size: 11px;
  font-weight: bold;
  box-shadow: 0 2px 6px rgba(var(--color-brand-rgb), 0.3);
}

.gateway-question-empty {
  padding: 18px 20px;
  color: var(--color-faint);
  font-size: 14px;
  background: var(--color-surface);
}

.gateway-question-items {
  padding: 0 0 12px;
  display: flex;
  flex-direction: column;
  gap: 0;
  background: var(--color-surface);
}

.gateway-question-item {
  display: grid;
  grid-template-columns: auto 1fr auto auto auto auto;
  gap: 14px;
  padding: 15px 20px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface-soft);
  margin: 12px 15px 0;
  transition: all 0.2s ease;
}

.gateway-question-item:hover {
  background: var(--color-brand-soft);
  border-color: var(--color-brand);
}

.gateway-question-number {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  background: var(--color-brand-solid);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: bold;
  flex-shrink: 0;
  box-shadow: 0 2px 6px rgba(var(--color-brand-rgb), 0.3);
}

.gateway-question-content {
  flex: 1;
  min-width: 0;
}

.gateway-question-name {
  font-size: 13px;
  font-weight: 500;
  color: var(--color-ink);
  margin-bottom: 3px;
}

.gateway-question-hint {
  font-size: 12px;
  color: var(--color-muted);
  margin-top: 2px;
}

.gateway-question-actions {
  display: flex;
  gap: 3px;
}

.gateway-question-type,
.gateway-question-required,
.gateway-question-status {
  display: inline-flex;
  align-items: center;
  padding: 4px 8px;
  border-radius: var(--radius-lg);
  font-size: 10px;
  font-weight: bold;
}

.gateway-question-type {
  background: var(--color-brand-soft);
  color: var(--color-brand);
  border: 1px solid var(--color-brand-soft);
  font-size: 11px;
  font-weight: 600;
}

.gateway-question-required {
  background: var(--color-danger-solid);
  color: white;
  box-shadow: 0 1px 4px rgba(245, 101, 101, 0.3);
}

.gateway-question-status.active {
  background: var(--color-success-solid);
  color: white;
  box-shadow: 0 1px 4px rgba(72, 187, 120, 0.3);
}

.gateway-question-status.inactive {
  background: var(--color-faint);
  color: white;
  box-shadow: 0 1px 4px rgba(160, 174, 192, 0.3);
}

.gateway-question-additional-info {
  grid-column: 2 / -1;
  display: flex;
  flex-direction: column;
  gap: 5px;
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px solid var(--color-border);
}

.gateway-question-info-row {
  display: flex;
  align-items: center;
  gap: 5px;
  flex-wrap: wrap;
}

.gateway-question-info-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-muted);
}

.gateway-question-option-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.gateway-question-option-tag {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  border: 1px solid #dbe3f0;
  color: var(--color-text);
  font-size: 11px;
  font-weight: 600;
}

.gateway-question-validation-value {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 8px;
  border-radius: var(--radius-lg);
  font-size: 10px;
  font-weight: bold;
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.gateway-question-timestamps {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  color: var(--color-muted);
  font-size: 12px;
}

.action-btn {
  width: 28px;
  height: 28px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
  font-size: 12px;
  background: var(--color-surface);
}

.action-btn.edit {
  color: var(--color-brand);
}

.action-btn.edit:hover {
  background: var(--color-brand-soft);
  border-color: var(--color-brand);
  transform: scale(1.1);
}

.gateway-modal-summary {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px;
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  margin-bottom: 24px;
}

.gateway-modal-summary h4 {
  margin: 0 0 4px;
  color: var(--color-ink);
  font-size: 18px;
}

.gateway-modal-summary p {
  margin: 0;
  color: var(--color-muted);
  font-size: 13px;
}

.gateway-fee-section {
  margin: 24px 0;
  padding: 20px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface-soft);
}

.gateway-fee-header {
  margin-bottom: 18px;
}

.gateway-fee-header h4 {
  margin: 0 0 6px;
  color: var(--color-ink);
  font-size: 16px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.gateway-fee-header h4 i {
  color: var(--color-brand);
}

.gateway-fee-header p {
  margin: 0;
  color: var(--color-muted);
  font-size: 13px;
}

.gateway-fee-section.standalone {
  margin: 0;
}

.gateway-fee-stack {
  display: flex;
  flex-direction: column;
  gap: 20px;
  margin-top: 20px;
}

.gateway-fee-card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 18px;
}

.gateway-fee-card h5 {
  margin: 0 0 16px;
  color: var(--color-ink);
  font-size: 14px;
}

.gateway-fee-description {
  margin: -8px 0 16px;
  color: var(--color-muted);
  font-size: 13px;
  line-height: 1.5;
}

.gateway-fee-fields {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.gateway-rule-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 18px;
}

.gateway-rule-header-main {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.gateway-rule-range {
  display: inline-flex;
  align-items: center;
  width: fit-content;
  padding: 4px 10px;
  border-radius: 999px;
  background: var(--color-brand-soft);
  color: var(--color-brand);
  font-size: 12px;
  font-weight: 600;
}

.gateway-rule-item {
  padding: 18px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface-soft);
}

.gateway-rule-item + .gateway-rule-item {
  margin-top: 16px;
}

.gateway-rule-add {
  margin-top: 16px;
}

.fee-unlimited-toggle {
  margin-bottom: 8px;
  justify-content: flex-start;
}

.fee-unlimited-toggle span {
  font-size: 13px;
  color: var(--color-muted);
}

.loading-container.compact,
.error-container.compact {
  padding: 24px 16px;
  border-radius: var(--radius-md);
  box-shadow: none;
  border: 1px solid var(--color-border);
}

.icon-input-wrapper {
  position: relative;
}

.icon-input {
  padding-right: 72px;
}

.icon-input-preview {
  position: absolute;
  top: 2px;
  right: 2px;
  bottom: 2px;
  width: 52px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--color-brand);
  pointer-events: none;
  border-left: 1px solid var(--color-border);
  background: var(--color-surface-soft);
  border-top-right-radius: 6px;
  border-bottom-right-radius: 6px;
}

.icon-input-preview.gateway-icon-preview--fiat {
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.icon-input-preview.gateway-icon-preview--crypto {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.currency-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.currency-tag {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  border-radius: 999px;
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-size: 12px;
  font-weight: 600;
}

.currency-tag.crypto {
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.empty-state {
  text-align: center;
  padding: 30px 20px;
  background: var(--color-surface);
  border: 2px dashed var(--color-border);
  border-radius: var(--radius-md);
  color: var(--color-faint);
}

.empty-state i {
  font-size: 32px;
  margin-bottom: 10px;
  display: block;
}

.empty-state p {
  font-size: 14px;
  margin: 0;
}

/* Exchange Rates Styles */
.exchange-rates-list {
  margin-top: 20px;
}

.list-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 2px solid var(--color-border);
}

.list-header h3 {
  font-size: 18px;
  color: var(--color-ink);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 10px;
}

.list-header h3 i {
  color: var(--color-brand);
}

.rates-table {
  overflow-x: auto;
  background: var(--color-surface);
  border-radius: var(--radius-md);
  border: 2px solid var(--color-border);
}

.rates-table table {
  width: 100%;
  border-collapse: collapse;
}

.rates-table thead {
  background: var(--color-surface-soft);
}

.rates-table th {
  padding: 15px;
  text-align: left;
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 2px solid var(--color-border);
}

.rates-table td {
  padding: 15px;
  border-bottom: 1px solid var(--color-border);
  color: var(--color-ink);
  font-size: 14px;
}

.rates-table tbody tr:hover {
  background: var(--color-surface-soft);
}

.rates-table tbody tr.inactive {
  opacity: 0.6;
}

.rates-table tbody tr:last-child td {
  border-bottom: none;
}

.exchange-rate-bias {
  margin-left: 4px;
  font-weight: 600;
}

.exchange-rate-bias.positive {
  color: var(--color-danger);
}

.exchange-rate-bias.negative {
  color: var(--color-success);
}

.exchange-rate-bias.neutral {
  color: var(--color-muted);
}

.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: var(--radius-lg);
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
}

.status-badge.active {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.status-badge.inactive {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.sync-mode-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: var(--radius-lg);
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
}

.sync-mode-badge.auto {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.sync-mode-badge.manual {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.sync-updated-at {
  margin-top: 4px;
  font-size: 11px;
  color: var(--color-muted);
}

.sync-updated-at.sync-stale {
  color: var(--color-danger);
  font-weight: 600;
}

.type-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: var(--radius-lg);
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
}

.type-badge.fiat {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.type-badge.crypto {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.sort-order-badge {
  display: inline-block;
  padding: 4px 10px;
  background: var(--color-brand-soft);
  color: var(--color-brand);
  border-radius: var(--radius-sm);
  font-size: 12px;
  font-weight: 600;
  min-width: 40px;
  text-align: center;
}

.action-buttons {
  display: flex;
  gap: 8px;
  align-items: center;
}

.btn-icon {
  padding: 8px 12px;
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  color: var(--color-text);
  cursor: pointer;
  transition: all 0.2s ease;
  font-size: 14px;
}

.btn-icon:hover {
  background: var(--color-surface-muted);
  border-color: var(--color-border-strong);
  color: var(--color-ink);
}

.btn-icon.btn-danger {
  background: var(--color-danger-soft);
  border-color: var(--color-danger-border);
  color: var(--color-danger);
}

.btn-icon.btn-danger:hover {
  background: var(--color-danger-soft);
  border-color: var(--color-danger);
  color: var(--color-danger);
}

/* Modal Styles */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
}

.modal-content {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow:
    0 20px 25px -5px rgba(0, 0, 0, 0.1),
    0 10px 10px -5px rgba(0, 0, 0, 0.04);
  max-width: 500px;
  width: 100%;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 30px;
  border-bottom: 2px solid var(--color-border);
}

.modal-header h3 {
  font-size: 20px;
  color: var(--color-ink);
  margin: 0;
}

.btn-close-modal {
  background: none;
  border: none;
  font-size: 20px;
  color: var(--color-faint);
  cursor: pointer;
  padding: 5px;
  transition: color 0.2s ease;
}

.btn-close-modal:hover {
  color: var(--color-muted);
}

.modal-body {
  padding: 30px;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 20px 30px;
  border-top: 2px solid var(--color-border);
}

.btn-secondary {
  padding: 12px 24px;
  background: var(--color-surface-soft);
  color: var(--color-text);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
}

.btn-secondary:hover {
  background: var(--color-surface-muted);
  border-color: var(--color-border-strong);
}

.required {
  color: var(--color-danger);
}

@media (max-width: 768px) {
  .transaction-settings-page {
    padding: 20px 15px;
  }

  .page-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 20px;
  }

  .text-settings-grid {
    grid-template-columns: 1fr;
  }

  .form-row {
    grid-template-columns: 1fr;
  }

  .rates-table {
    font-size: 12px;
  }

  .rates-table th,
  .rates-table td {
    padding: 10px 8px;
  }

  .list-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 15px;
  }

  .gateway-funding-grid {
    grid-template-columns: 1fr;
  }

  .gateway-display-preview-item {
    gap: 10px;
  }

  .gateway-display-preview-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .gateway-question-item {
    grid-template-columns: 1fr;
  }

  .gateway-question-additional-info {
    grid-column: auto;
  }

  .modal-content {
    max-width: 100%;
    margin: 10px;
  }
}
</style>
