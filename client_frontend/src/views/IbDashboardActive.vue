<template>
  <div class="ib-dashboard-active ui-page">
    <div v-if="ibPartnerOptions.length >= 1" class="ib-dashboard-switcher">
      <div class="ib-dashboard-switcher__label">
        <i class="fas fa-exchange-alt"></i>
        <span>{{ t("ibActiveSelectIbPartner", "Select IB") }}</span>
      </div>
      <CustomSelect
        v-if="ibPartnerOptions.length > 1"
        v-model="selectedIbPartnerId"
        class="ib-dashboard-switcher__select"
        :options="ibPartnerOptions"
        searchable
        :placeholder="t('ibActiveSelectIbPartner', 'Select IB')"
        :search-placeholder="t('ibActiveSearchIbPartner', 'Search IB...')"
        @change="onIbPartnerChange"
      />
      <span v-else class="ib-dashboard-switcher__single">{{
        currentIbLabel
      }}</span>

      <!-- Name IB：给当前选中的 IB 起一个 clientAlias，方便有多个 IB 身份时区分 -->
      <template v-if="aliasEditing">
        <input
          v-model="aliasDraft"
          type="text"
          maxlength="200"
          class="ib-dashboard-alias-input"
          :placeholder="
            t('ibActiveNameIbPlaceholder', 'Enter an alias for this IB')
          "
          @keydown.enter="saveAlias"
          @keydown.esc="cancelAlias"
        />
        <button
          type="button"
          class="ib-dashboard-alias-btn ib-dashboard-alias-btn--primary"
          :disabled="aliasSaving"
          @click="saveAlias"
        >
          <i
            class="fas"
            :class="aliasSaving ? 'fa-spinner fa-spin' : 'fa-check'"
          ></i>
          {{ t("ibActiveSave", "Save") }}
        </button>
        <button
          type="button"
          class="ib-dashboard-alias-btn"
          :disabled="aliasSaving"
          @click="cancelAlias"
        >
          {{ t("ibActiveCancel", "Cancel") }}
        </button>
        <span v-if="aliasError" class="ib-dashboard-alias-error">{{
          aliasError
        }}</span>
      </template>
      <button
        v-else
        type="button"
        class="ib-dashboard-alias-btn ib-dashboard-alias-btn--primary"
        :disabled="!selectedIbPartnerId"
        @click="startNameIb"
      >
        <i class="fas fa-tag"></i>
        {{ t("ibActiveNameIb", "Name IB") }}
      </button>
    </div>

    <!-- Performance Statistics -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-card-header">
          <span class="stat-card-title">{{
            t("ibActiveTotalNetwork", "Total Network")
          }}</span>
          <div class="stat-card-icon purple">
            <i class="fas fa-users"></i>
          </div>
        </div>
        <div class="stat-card-value">
          {{ formatNumber(stats.totalNetwork) }}
        </div>
        <div class="stat-card-label">
          {{ t("ibActiveClientsSubIbs", "Clients & Sub-IBs") }}
        </div>
        <div class="stat-card-trend">
          <i class="fas fa-arrow-up"></i> +{{ stats.networkChange }}
          {{ t("ibActiveThisMonth", "this month") }}
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-card-header">
          <span class="stat-card-title">{{
            t("ibActiveTotalEarned", "Total Earned")
          }}</span>
          <div class="stat-card-icon green">
            <i class="fas fa-dollar-sign"></i>
          </div>
        </div>
        <div class="stat-card-value">
          {{ formatCurrency(stats.totalEarned) }}
        </div>
        <div class="stat-card-label">
          {{ t("ibActiveLifetimeCommissions", "Lifetime Commissions") }}
        </div>
        <div class="stat-card-trend">
          <i class="fas fa-arrow-up"></i> +{{ stats.earnedChange }}%
          {{ t("ibActiveVsLastMonth", "vs last month") }}
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-card-header">
          <span class="stat-card-title">{{
            t("ibActiveThisMonthTitle", "This Month")
          }}</span>
          <div class="stat-card-icon orange">
            <i class="fas fa-chart-line"></i>
          </div>
        </div>
        <div class="stat-card-value">{{ formatCurrency(stats.thisMonth) }}</div>
        <div class="stat-card-label">
          {{ t("ibActiveCurrentPeriod", "Current Period") }}
        </div>
        <div class="stat-card-trend">
          <i class="fas fa-arrow-up"></i> +{{ stats.monthChange }}%
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-card-header">
          <span class="stat-card-title">{{
            t("ibActiveActiveClients", "Active Clients")
          }}</span>
          <div class="stat-card-icon blue">
            <i class="fas fa-user-check"></i>
          </div>
        </div>
        <div class="stat-card-value">
          {{ formatNumber(stats.activeClients) }}
        </div>
        <div class="stat-card-label">
          {{ t("ibActiveTradingActively", "Trading Actively") }}
        </div>
        <div class="stat-card-trend">
          <i class="fas fa-arrow-up"></i> {{ stats.activeRate }}%
          {{ t("ibActiveActiveRate", "active rate") }}
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-card-header">
          <span class="stat-card-title">{{
            t("ibActiveTotalDeposit", "Total Deposit")
          }}</span>
          <div class="stat-card-icon green">
            <i class="fas fa-arrow-down"></i>
          </div>
        </div>
        <div class="stat-card-value">
          {{ formatCurrency(stats.totalDeposit) }}
        </div>
        <div class="stat-card-label">
          {{ t("ibActiveAccountLifetime", "Account Lifetime") }}
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-card-header">
          <span class="stat-card-title">{{
            t("ibActiveTotalWithdraw", "Total Withdraw")
          }}</span>
          <div class="stat-card-icon orange">
            <i class="fas fa-arrow-up"></i>
          </div>
        </div>
        <div class="stat-card-value">
          {{ formatCurrency(stats.totalWithdraw) }}
        </div>
        <div class="stat-card-label">
          {{ t("ibActiveAccountLifetime", "Account Lifetime") }}
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-card-header">
          <span class="stat-card-title">{{
            t("ibActiveNetDeposit", "Net Deposit")
          }}</span>
          <div class="stat-card-icon purple">
            <i class="fas fa-balance-scale"></i>
          </div>
        </div>
        <div class="stat-card-value">
          {{ formatCurrency(stats.netDeposit) }}
        </div>
        <div class="stat-card-label">
          {{ t("ibActiveDepositMinusWithdraw", "Deposit − Withdraw") }}
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-card-header">
          <span class="stat-card-title">{{
            t("ibActiveTotalTradingVolume", "Total Trading Volume")
          }}</span>
          <div class="stat-card-icon blue">
            <i class="fas fa-chart-area"></i>
          </div>
        </div>
        <div class="stat-card-value">
          {{ formatNumber(stats.totalTradingVolume, 2) }}
        </div>
        <div class="stat-card-label">
          {{ t("ibActiveLotsLifetime", "Lots · Lifetime") }}
        </div>
      </div>
    </div>

    <!-- Your IB Referral URL（样式参考后台 Sales Dashboard - Your Sales Referral URL） -->
    <div class="ib-referral-url-card">
      <div class="ib-referral-url-card-header">
        <div class="ib-referral-url-card-icon"><i class="fas fa-link"></i></div>
        <div class="ib-referral-url-card-title">
          <h3>{{ t("ibActiveYourIbReferralUrl", "Your IB Referral URL") }}</h3>
          <p>
            {{
              t(
                "ibActiveShareLinkIbDesc",
                "Share this unique link - clients who register through it will be assigned to you as their IB",
              )
            }}
          </p>
        </div>
      </div>
      <div class="ib-referral-url-display">
        <div class="ib-referral-url-text" ref="referralUrlRef">
          {{ referralUrl }}
        </div>
        <button
          type="button"
          class="ib-referral-url-btn-copy"
          @click="copyReferralUrl"
        >
          <i class="fas fa-copy"></i> {{ t("ibActiveCopy", "Copy") }}
        </button>
      </div>
      <div class="ib-referral-url-stats">
        <div class="ib-referral-url-stat-item">
          <div class="ib-referral-url-stat-value">{{ urlStats.clicks }}</div>
          <div class="ib-referral-url-stat-label">
            {{ t("ibActiveUrlClicks", "URL Clicks") }}
          </div>
        </div>
        <div class="ib-referral-url-stat-item">
          <div class="ib-referral-url-stat-value">
            {{ urlStats.registrations }}
          </div>
          <div class="ib-referral-url-stat-label">
            {{ t("ibActiveRegistrations", "Registrations") }}
          </div>
        </div>
        <div class="ib-referral-url-stat-item">
          <div class="ib-referral-url-stat-value">
            {{ urlStats.conversionRate }}%
          </div>
          <div class="ib-referral-url-stat-label">
            {{ t("ibActiveConversionRate", "Conversion Rate") }}
          </div>
        </div>
      </div>
    </div>

    <!-- Your Commission Rates：规则列表 + 佣金统计 -->
    <div class="commission-rate-card">
      <div class="commission-rate-header">
        <div class="commission-rate-title">
          <i class="fas fa-percentage"></i>
          {{ t("ibActiveYourCommissionRates", "Your Commission Rates") }}
        </div>
        <div class="commission-header-actions" v-if="false">
          <button class="btn-journey" @click="openJourneyModal">
            <i class="fas fa-history"></i>
            {{ t("ibActiveIbJourney", "IB Journey") }}
          </button>
        </div>
      </div>
      <!-- 佣金统计（按入金/订单及按规则） -->
      <div class="commission-stats-section" v-if="commissionStats">
        <h4 class="commission-stats-title">
          {{ t("ibActiveCommissionSummary", "Commission Summary") }}
        </h4>
        <div class="commission-stats-grid">
          <div class="commission-stat-item">
            <span class="commission-stat-label">{{
              t("ibActiveTotalCommission", "Total Commission")
            }}</span>
            <span class="commission-stat-value">{{
              formatCurrency(commissionStats.total || 0)
            }}</span>
          </div>
          <div class="commission-stat-item">
            <span class="commission-stat-label">{{
              t("ibActiveFromDeposits", "From Deposits")
            }}</span>
            <span class="commission-stat-value">{{
              formatCurrency(commissionStats.depositTotal || 0)
            }}</span>
          </div>
          <div class="commission-stat-item">
            <span class="commission-stat-label">{{
              t("ibActiveFromOrders", "From Orders")
            }}</span>
            <span class="commission-stat-value">{{
              formatCurrency(commissionStats.orderTotal || 0)
            }}</span>
          </div>
        </div>
        <div
          class="commission-by-rule"
          v-if="commissionStats.byRule && commissionStats.byRule.length > 0"
        >
          <span class="commission-by-rule-label"
            >{{ t("ibActiveByRule", "By Rule") }}:</span
          >
          <div class="commission-by-rule-list">
            <span
              v-for="(r, i) in commissionStats.byRule"
              :key="i"
              class="commission-by-rule-item"
            >
              {{ r.ruleName }}: {{ formatCurrency(r.amount)
              }}<template v-if="i < commissionStats.byRule.length - 1"
                >;
              </template>
            </span>
          </div>
        </div>
      </div>
      <!-- 规则列表：Rule Name, Product, Target Region, Currency, Rate, Fixed Amount -->
      <h4 class="commission-rules-table-title">
        {{ t("ibActiveActiveRules", "Active Rules") }}
      </h4>
      <div class="commission-rules-table-wrap">
        <table class="commission-rules-table">
          <thead>
            <tr>
              <th>{{ t("ibActiveRuleName", "Rule Name") }}</th>
              <th>{{ t("ibActiveProduct", "Product") }}</th>
              <th>{{ t("ibActiveTargetRegion", "Target Region") }}</th>
              <th>{{ t("ibActiveCurrency", "Currency") }}</th>
              <th>{{ t("ibActiveRate", "Rate") }}</th>
              <th>{{ t("ibActiveFixedAmount", "Fixed Amount") }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, idx) in rulesList" :key="idx">
              <td>{{ row.ruleName }}</td>
              <td>{{ row.product }}</td>
              <td>{{ row.targetRegion }}</td>
              <td>{{ row.currency }}</td>
              <td>{{ row.rate }}</td>
              <td>{{ row.fixedAmount }}</td>
            </tr>
            <tr v-if="!rulesList || rulesList.length === 0">
              <td colspan="6" class="empty-rules">
                {{ t("ibActiveNoRules", "No Rules") }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- IB Network Graph -->
    <div class="content-section">
      <div class="section-header">
        <div class="section-title">
          <i class="fas fa-project-diagram"></i>
          {{ t("ibActiveYourNetwork", "Your IB Network") }} ({{
            stats.totalNetwork
          }}
          {{ t("ibActiveTotal", "Total") }})
        </div>
        <div class="section-controls">
          <div class="network-search-wrapper">
            <i class="fas fa-search network-search-icon"></i>
            <input
              type="text"
              class="network-search-input"
              v-model="networkSearchQuery"
              :placeholder="
                t('ibActiveSearchByNameOrCode', 'Search by name or code...')
              "
              @change="searchNetwork"
            />
          </div>
          <div class="level-selector">
            <label>{{ t("ibActiveExpand", "Expand:") }}</label>
            <CustomSelect
              v-model="expandLevel"
              :options="expandLevelOptions"
              @change="expandToLevel"
            />
          </div>
          <button class="btn-collapse-all" @click="collapseAll">
            <i class="fas fa-compress-alt"></i>
            {{ t("ibActiveCollapseAll", "Collapse All") }}
          </button>
        </div>
      </div>

      <div class="network-info-banner">
        <i class="fas fa-info-circle"></i>
        <span>{{
          t(
            "ibActiveMouseControls",
            "Mouse Controls: Drag to pan • Scroll to zoom • Click [+] to expand nodes • Double-click to reset view • Search to find and highlight specific members",
          )
        }}</span>
      </div>

      <!-- Zoom Level Indicator -->
      <div class="zoom-indicator" v-if="showZoomIndicator">
        <i class="fas fa-search"></i> <span>{{ zoomLevel }}%</span>
      </div>

      <div
        class="network-container"
        ref="networkContainerRef"
        @mousedown="handleMouseDown"
        @mousemove="handleMouseMove"
        @mouseup="handleMouseUp"
        @wheel="handleWheel"
        @dblclick="resetNetworkView"
      >
        <div
          class="network-graph"
          ref="networkGraphRef"
          :style="{
            transform: `translate(${panX}px, ${panY}px) scale(${zoom})`,
          }"
        >
          <!-- Network Graph Content -->
          <div class="network-branch">
            <div class="network-node">
              <div class="node-card tier1">
                <div class="node-content">
                  <div class="node-avatar">{{ userInitials }}</div>
                  <div class="node-info">
                    <div class="node-title">
                      {{ t("ibActiveYou", "You") }} ({{ userName }})
                    </div>
                    <div class="node-subtitle">
                      {{ ibCode }} •
                      {{ t("ibActiveTier1MasterIb", "Tier 1 Master IB") }}
                    </div>
                    <div class="node-badge">
                      <i class="fas fa-crown"></i>
                      {{ t("ibActiveTier1Ib", "Tier 1 IB") }}
                    </div>
                  </div>
                </div>
                <div class="node-stats">
                  <div class="node-stat">
                    <i class="fas fa-users"></i>
                    <span
                      >{{ stats.directClients }}
                      {{ t("ibActiveDirectClients", "Direct Clients") }}</span
                    >
                  </div>
                  <div class="node-stat">
                    <i class="fas fa-handshake"></i>
                    <span
                      >{{ stats.subIbs }}
                      {{ t("ibActiveSubIbs", "Sub-IBs") }}</span
                    >
                  </div>
                </div>
              </div>

              <button
                type="button"
                class="expand-btn"
                :class="{ expanded: expandedNodes.tier1 }"
                :aria-expanded="expandedNodes.tier1"
                @click.stop="toggleNode('tier1')"
              >
                {{ expandedNodes.tier1 ? "−" : "+" }}
              </button>
            </div>

            <!-- Tier 1 Children -->
            <div
              class="network-children"
              :class="{ expanded: expandedNodes.tier1 }"
            >
              <!-- Sample network members -->
              <template v-for="member in networkMembers" :key="member.id">
                <div class="network-branch">
                  <div class="network-node" v-if="!member.isSummary">
                    <div
                      :class="[
                        'node-card',
                        member.type,
                        { highlighted: highlightedNodeId === member.id },
                      ]"
                      :data-node-id="member.id"
                    >
                      <div class="node-content">
                        <div class="node-avatar">{{ member.initials }}</div>
                        <div class="node-info">
                          <div class="node-title">{{ member.name }}</div>
                          <div class="node-subtitle" v-if="member.code">
                            {{ member.code }}
                          </div>
                          <div v-if="member.tier" class="node-badge">
                            <i :class="member.tierIcon"></i> {{ member.tier }}
                          </div>
                        </div>
                      </div>
                      <div class="node-stats" v-if="member.stats">
                        <div
                          class="node-stat"
                          v-for="stat in member.stats"
                          :key="stat.value"
                        >
                          <i :class="stat.icon"></i>
                          <span>{{ stat.value }}</span>
                        </div>
                      </div>
                      <div class="node-stats" v-if="member.type === 'client'">
                        <span class="status-badge active">{{
                          t("ibActiveStatusActive", "Active")
                        }}</span>
                      </div>
                    </div>

                    <button
                      v-if="member.hasChildren"
                      type="button"
                      class="expand-btn"
                      :class="{ expanded: expandedNodes[member.id] }"
                      :aria-expanded="expandedNodes[member.id]"
                      @click.stop="toggleNode(member.id)"
                    >
                      {{ expandedNodes[member.id] ? "−" : "+" }}
                    </button>
                  </div>

                  <!-- Collapsed Summary Card -->
                  <div v-else class="node-card collapsed-summary">
                    <i
                      class="fas fa-ellipsis-h"
                      style="font-size: 16px; margin-bottom: 4px"
                    ></i>
                    <div style="font-weight: 600; font-size: 12px">
                      {{ member.name }}
                    </div>
                    <div
                      v-if="member.code"
                      style="font-size: 11px; margin-top: 2px"
                    >
                      {{ member.code }}
                    </div>
                  </div>

                  <!-- Children (if any) -->
                  <div
                    v-if="member.hasChildren && member.children"
                    class="network-children"
                    :class="{ expanded: expandedNodes[member.id] }"
                  >
                    <template v-for="child in member.children" :key="child.id">
                      <div class="network-branch">
                        <div class="network-node" v-if="!child.isSummary">
                          <div
                            :class="[
                              'node-card',
                              child.type,
                              { highlighted: highlightedNodeId === child.id },
                            ]"
                            :data-node-id="child.id"
                          >
                            <div class="node-content">
                              <div class="node-avatar">
                                {{ child.initials }}
                              </div>
                              <div class="node-info">
                                <div class="node-title">{{ child.name }}</div>
                                <div class="node-subtitle" v-if="child.code">
                                  {{ child.code }}
                                </div>
                                <div v-if="child.tier" class="node-badge">
                                  <i :class="child.tierIcon"></i>
                                  {{ child.tier }}
                                </div>
                              </div>
                            </div>
                            <div class="node-stats" v-if="child.stats">
                              <div
                                class="node-stat"
                                v-for="stat in child.stats"
                                :key="stat.value"
                              >
                                <i :class="stat.icon"></i>
                                <span>{{ stat.value }}</span>
                              </div>
                            </div>
                            <div
                              class="node-stats"
                              v-if="child.type === 'client'"
                            >
                              <span class="status-badge active">{{
                                t("ibActiveStatusActive", "Active")
                              }}</span>
                            </div>
                          </div>

                          <button
                            v-if="child.hasChildren"
                            type="button"
                            class="expand-btn"
                            :class="{ expanded: expandedNodes[child.id] }"
                            :aria-expanded="expandedNodes[child.id]"
                            @click.stop="toggleNode(child.id)"
                          >
                            {{ expandedNodes[child.id] ? "−" : "+" }}
                          </button>
                        </div>

                        <!-- Collapsed Summary Card for children -->
                        <div v-else class="node-card collapsed-summary">
                          <i
                            class="fas fa-ellipsis-h"
                            style="font-size: 16px; margin-bottom: 4px"
                          ></i>
                          <div style="font-weight: 600; font-size: 12px">
                            {{ child.name }}
                          </div>
                          <div
                            v-if="child.code"
                            style="font-size: 11px; margin-top: 2px"
                          >
                            {{ child.code }}
                          </div>
                        </div>

                        <!-- Grandchildren (if any) -->
                        <div
                          v-if="child.hasChildren && child.children"
                          class="network-children"
                          :class="{ expanded: expandedNodes[child.id] }"
                        >
                          <template
                            v-for="grandchild in child.children"
                            :key="grandchild.id"
                          >
                            <div class="network-branch">
                              <div
                                class="network-node"
                                v-if="!grandchild.isSummary"
                              >
                                <div
                                  :class="[
                                    'node-card',
                                    grandchild.type,
                                    {
                                      highlighted:
                                        highlightedNodeId === grandchild.id,
                                    },
                                  ]"
                                  :data-node-id="grandchild.id"
                                >
                                  <div class="node-content">
                                    <div class="node-avatar">
                                      {{ grandchild.initials }}
                                    </div>
                                    <div class="node-info">
                                      <div class="node-title">
                                        {{ grandchild.name }}
                                      </div>
                                      <div
                                        class="node-subtitle"
                                        v-if="grandchild.code"
                                      >
                                        {{ grandchild.code }}
                                      </div>
                                    </div>
                                  </div>
                                  <div
                                    class="node-stats"
                                    v-if="grandchild.type === 'client'"
                                  >
                                    <span class="status-badge active">{{
                                      t("ibActiveStatusActive", "Active")
                                    }}</span>
                                  </div>
                                </div>
                              </div>

                              <!-- Collapsed Summary Card for grandchildren -->
                              <div v-else class="node-card collapsed-summary">
                                <i
                                  class="fas fa-ellipsis-h"
                                  style="font-size: 16px; margin-bottom: 4px"
                                ></i>
                                <div style="font-weight: 600; font-size: 12px">
                                  {{ grandchild.name }}
                                </div>
                                <div
                                  v-if="grandchild.code"
                                  style="font-size: 11px; margin-top: 2px"
                                >
                                  {{ grandchild.code }}
                                </div>
                              </div>
                            </div>
                          </template>
                        </div>
                      </div>
                    </template>
                  </div>
                </div>
              </template>
            </div>
          </div>
        </div>
      </div>

      <div class="network-summary">
        <div>
          <div class="summary-value">{{ stats.totalNetwork }}</div>
          <div class="summary-label">
            {{ t("ibActiveTotalNetwork", "Total Network") }}
          </div>
        </div>
        <div>
          <div class="summary-value tier2">{{ stats.tier2Ibs }}</div>
          <div class="summary-label">
            {{ t("ibActiveLevel2Ibs", "Level 2 IBs") }}
          </div>
        </div>
        <div>
          <div class="summary-value tier3">{{ stats.tier3Ibs }}</div>
          <div class="summary-label">
            {{ t("ibActiveLevel3Ibs", "Level 3 IBs") }}
          </div>
        </div>
        <div>
          <div class="summary-value clients">{{ stats.directClients }}</div>
          <div class="summary-label">
            {{ t("ibActiveDirectClients", "Direct Clients") }}
          </div>
        </div>
      </div>
    </div>

    <!-- Network Members List（分页参考 Commission Report：Show Rows + Showing X-Y of Z + Prev/Page n of N/Next） -->
    <div class="content-section">
      <div class="section-header network-members-toolbar">
        <div class="section-title">
          <i class="fas fa-list-ul"></i>
          {{ t("ibActiveAllNetworkMembers", "All Network Members") }}
        </div>
        <div class="section-controls">
          <div class="network-search-wrapper">
            <i class="fas fa-search network-search-icon"></i>
            <input
              type="text"
              class="network-search-input"
              v-model="memberListSearchQuery"
              :placeholder="t('ibActiveSearchMembers', 'Search members...')"
              @change="filterMemberList"
            />
          </div>
          <div class="level-selector">
            <label>{{ t("ibActiveFilter", "Filter:") }}</label>
            <CustomSelect
              v-model="memberTypeFilter"
              :options="memberTypeOptions"
              @change="filterMemberList"
            />
          </div>
          <div class="network-show-rows">
            <label class="network-show-rows__label">{{
              t("commShowRows", "Show Rows")
            }}</label>
            <CustomSelect
              v-model="membersPerPage"
              class="network-show-rows__select"
              :options="pageSizeOptions"
              @change="onMemberPerPageChange"
            />
          </div>
        </div>
      </div>

      <div class="members-table-container">
        <table class="members-table">
          <thead>
            <tr>
              <th>{{ t("ibActiveMemberName", "Member Name") }}</th>
              <th>{{ t("ibActiveType", "Type") }}</th>
              <th>{{ t("ibActivePerformance", "Performance") }}</th>
              <th>{{ t("ibActiveCommission", "Commission") }}</th>
              <th>{{ t("ibActiveStatus", "Status") }}</th>
              <th>{{ t("ibActiveDetail", "Detail") }}</th>
            </tr>
          </thead>
          <tbody>
            <template v-if="memberListLoading">
              <tr>
                <td colspan="6" class="empty-state">
                  <i class="fas fa-spinner fa-spin"></i>
                  <p>{{ t("ibActiveLoading", "Loading...") }}</p>
                </td>
              </tr>
            </template>
            <template v-else-if="visibleMemberList.length === 0">
              <tr>
                <td colspan="6" class="empty-state">
                  <i class="fas fa-inbox"></i>
                  <p>
                    {{
                      t("ibActiveNoNetworkMembers", "No network members found.")
                    }}
                  </p>
                </td>
              </tr>
            </template>
            <template v-else>
              <template
                v-for="member in visibleMemberList"
                :key="getMemberRowKey(member)"
              >
                <tr
                  :data-member-id="member.id"
                  :class="{
                    expanded: expandedMemberDetails.includes(
                      getMemberRowKey(member),
                    ),
                    'row-child': (member.depth || 0) > 0,
                  }"
                >
                  <td>
                    <div
                      class="member-info member-info--tree"
                      :style="{ paddingLeft: (member.depth || 0) * 20 + 'px' }"
                    >
                      <button
                        v-if="member.hasChildren"
                        type="button"
                        class="tree-toggle"
                        @click="toggleMemberExpand(member.id)"
                        :aria-label="
                          isMemberExpanded(member.id)
                            ? t('ibActiveCollapse', 'Collapse')
                            : t('ibActiveExpand', 'Expand')
                        "
                      >
                        {{ isMemberExpanded(member.id) ? "−" : "+" }}
                      </button>
                      <span
                        v-else
                        class="tree-toggle tree-toggle--empty"
                      ></span>
                      <div :class="['member-avatar', member.avatarClass]">
                        {{ member.initials }}
                      </div>
                      <div class="member-details">
                        <div class="member-name">{{ member.name }}</div>
                        <div class="member-code">{{ member.code }}</div>
                      </div>
                    </div>
                  </td>
                  <td>
                    <span :class="['member-type-badge', member.badgeClass]">{{
                      member.type === "ib"
                        ? t("ibActiveTypeSubIb", "Sub-IB")
                        : t("ibActiveTypeDirectClient", "Direct Client")
                    }}</span>
                  </td>
                  <td>
                    <div class="performance-info">
                      <!-- IB代理：显示客户数和下级IB数 -->
                      <template
                        v-if="member.type === 'ib' && member.performance"
                      >
                        <div style="margin-bottom: 4px">
                          <i
                            class="fas fa-users"
                            style="color: var(--color-brand); margin-right: 6px"
                          ></i>
                          {{ member.performance.clients || 0 }}
                          {{ t("ibActiveClients", "Clients") }}
                        </div>
                        <div>
                          <i
                            class="fas fa-handshake"
                            style="color: var(--color-brand); margin-right: 6px"
                          ></i>
                          {{ member.performance.subIbs || 0 }}
                          {{ t("ibActiveSubIbs", "Sub-IBs") }}
                        </div>
                      </template>
                      <!-- 客户端用户：显示交易量和交易订单数 -->
                      <template
                        v-else-if="
                          member.type === 'client' && member.performance
                        "
                      >
                        <div style="margin-bottom: 4px">
                          <i
                            class="fas fa-chart-line"
                            style="color: var(--color-brand); margin-right: 6px"
                          ></i>
                          {{ formatVolume(member.performance.volume || 0) }}
                          {{ t("ibActiveVolume", "Volume") }}
                        </div>
                        <div>
                          <i
                            class="fas fa-exchange-alt"
                            style="color: var(--color-brand); margin-right: 6px"
                          ></i>
                          {{ member.performance.trades || 0 }}
                          {{ t("ibActiveTrades", "Trades") }}
                        </div>
                      </template>
                      <template v-else>
                        <div style="color: var(--color-faint); font-size: 12px">
                          {{ t("ibActiveNoData", "No data") }}
                        </div>
                      </template>
                    </div>
                  </td>
                  <td class="commission-cell">
                    {{ formatCurrency(member.commission) }}
                  </td>
                  <td>
                    <span class="status-badge active">{{
                      t("ibActiveStatusActive", "Active")
                    }}</span>
                  </td>
                  <td>
                    <button
                      class="btn-action btn-detail"
                      @click="toggleMemberDetail(getMemberRowKey(member))"
                    >
                      <i
                        :class="
                          expandedMemberDetails.includes(
                            getMemberRowKey(member),
                          )
                            ? 'fas fa-chevron-up'
                            : 'fas fa-chevron-down'
                        "
                      ></i>
                      {{
                        expandedMemberDetails.includes(getMemberRowKey(member))
                          ? t("ibActiveHide", "Hide")
                          : t("ibActiveDetail", "Detail")
                      }}
                    </button>
                  </td>
                </tr>
                <tr
                  v-if="expandedMemberDetails.includes(getMemberRowKey(member))"
                  class="detail-row show"
                >
                  <td colspan="6">
                    <div class="detail-content">
                      <div class="detail-grid">
                        <div
                          class="detail-item"
                          v-for="detail in member.details"
                          :key="detail.label"
                        >
                          <span class="detail-label">{{
                            translateDetailLabel(detail.label)
                          }}</span>
                          <div
                            class="detail-value"
                            :style="
                              isProfitLossDetail(detail)
                                ? undefined
                                : detail.style || undefined
                            "
                          >
                            <span
                              v-if="isProfitLossDetail(detail)"
                              :class="profitLossSpanClass(detail)"
                              >{{ formatProfitLossValue(detail) }}</span
                            >
                            <template v-else>{{
                              translateDetailValue(detail)
                            }}</template>
                          </div>
                        </div>
                      </div>
                      <div
                        v-if="member.performanceOverview"
                        style="margin-top: 20px"
                      >
                        <h4
                          style="
                            font-size: 14px;
                            font-weight: 600;
                            color: var(--color-ink);
                            margin-bottom: 12px;
                          "
                        >
                          <i class="fas fa-chart-line"></i>
                          {{
                            t(
                              "ibActivePerformanceOverview",
                              "Performance Overview",
                            )
                          }}
                        </h4>
                        <div class="performance-overview-grid">
                          <div
                            v-for="overview in member.performanceOverview"
                            :key="overview.label"
                            class="overview-item"
                          >
                            <div class="overview-label">
                              {{ translateOverviewLabel(overview.label) }}
                            </div>
                            <div
                              class="overview-value"
                              :style="{ color: overview.color }"
                            >
                              {{ overview.value }}
                            </div>
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
      <!-- 底部分页（与 Commission Report 一致：左侧 Showing x-y of z，右侧 Prev + Page n of N + Next） -->
      <div
        class="network-pagination"
        v-if="
          !memberListLoading && (totalMemberCount > 0 || memberList.length > 0)
        "
      >
        <span class="network-pagination__info">{{ memberPaginationInfo }}</span>
        <div class="network-pagination__btns">
          <button
            type="button"
            class="network-pagination__btn"
            :disabled="currentMemberPage <= 1"
            @click="goToMemberPage(currentMemberPage - 1)"
          >
            <i class="fas fa-chevron-left"></i> {{ t("commPrevious", "Prev") }}
          </button>
          <span class="network-pagination__page">{{ memberPageOfText }}</span>
          <button
            type="button"
            class="network-pagination__btn"
            :disabled="currentMemberPage >= totalMemberPages"
            @click="goToMemberPage(currentMemberPage + 1)"
          >
            {{ t("commNext", "Next") }} <i class="fas fa-chevron-right"></i>
          </button>
        </div>
      </div>
    </div>

    <!-- IB Journey Modal -->
    <Teleport to="body">
      <div
        v-if="showJourneyModal"
        class="journey-modal-overlay"
        @click.self="closeJourneyModal"
      >
        <div class="journey-modal-container">
          <div class="journey-modal-header">
            <h2>
              <i class="fas fa-history"></i>
              {{ t("ibActiveYourIbJourney", "Your IB Journey") }}
            </h2>
            <button
              type="button"
              class="journey-modal-close-btn"
              :aria-label="t('close', 'Close')"
              @click="closeJourneyModal"
            >
              <i class="fas fa-times"></i>
            </button>
          </div>

          <div class="journey-modal-body">
            <div class="timeline">
              <div
                class="timeline-item"
                v-for="(event, index) in journeyEvents"
                :key="index"
              >
                <div :class="['timeline-dot', { current: index === 0 }]">
                  <i :class="event.icon"></i>
                </div>
                <div class="timeline-content">
                  <div class="timeline-date">{{ event.date }}</div>
                  <div class="timeline-title">{{ event.title }}</div>
                  <div class="timeline-description">
                    {{ event.description }}
                  </div>
                  <span
                    v-if="event.badge"
                    :class="['timeline-tier-badge', event.badgeClass]"
                    >{{ event.badge }}</span
                  >
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from "vue";
import CustomSelect from "@/components/common/CustomSelect.vue";
import { useClientAuthStore } from "@/stores/clientAuth";
import { useLanguageStore } from "@/stores/language";
import { formatCurrency, formatNumber, getInitials } from "@/utils/helpers";
import ibDashboardService from "@/services/ibDashboardService";

const clientAuthStore = useClientAuthStore();
const languageStore = useLanguageStore();
const t = (key, fallback) => languageStore.t(key, fallback);

// 格式化交易量（例如：$850K, $1.2M）
const formatVolume = (volume) => {
  if (!volume || volume === 0) return "$0";
  if (volume >= 1000000) {
    return "$" + (volume / 1000000).toFixed(1) + "M";
  } else if (volume >= 1000) {
    return "$" + (volume / 1000).toFixed(0) + "K";
  } else {
    return "$" + volume.toFixed(0);
  }
};

// Detail 面板：后端返回的 label/value 翻译（与 getMemberDetails 返回字段对应）
const DETAIL_LABEL_KEYS = {
  "Join Date": "ibActiveDetailJoinDate",
  "Total Clients": "ibActiveDetailTotalClients",
  "This Month": "ibActiveDetailThisMonth",
  "Total Earned": "ibActiveDetailTotalEarned",
  "Trading Volume": "ibActiveDetailTradingVolume",
  Commission: "ibActiveDetailCommission",
  "Profit/Loss": "ibActiveDetailProfitLoss",
  "Net Deposit": "ibActiveDetailNetDeposit",
  "Account Type": "ibActiveDetailAccountType",
  "Active Rate": "ibActiveDetailActiveRate",
  "Sub-IBs": "ibActiveDetailSubIbs",
};
const translateDetailLabel = (label) => {
  const key = DETAIL_LABEL_KEYS[label];
  return key ? t(key, label) : label;
};
const getProfitLossAmount = (detail) => {
  if (detail?.rawAmount == null || detail.rawAmount === "") {
    return null;
  }
  const amount = Number(detail.rawAmount);
  return Number.isNaN(amount) ? null : amount;
};

const isProfitLossDetail = (detail) => getProfitLossAmount(detail) != null;

const formatProfitLossValue = (detail) => {
  const amount = getProfitLossAmount(detail);
  if (amount == null) {
    return detail.value ?? "—";
  }
  if (amount === 0) {
    return "$0.00";
  }
  const sign = amount > 0 ? "+" : "-";
  return `${sign}$${Math.abs(amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
};

const profitLossSpanClass = (detail) => {
  const amount = getProfitLossAmount(detail);
  if (amount == null || amount === 0) {
    return "ib-dashboard-pnl--neutral";
  }
  return amount > 0
    ? "ib-dashboard-pnl--positive"
    : "ib-dashboard-pnl--negative";
};

const translateDetailValue = (detail) => {
  if (detail.label === "Account Type" && detail.value === "Standard") {
    return t("ibActiveDetailAccountTypeStandard", "Standard");
  }
  if (isProfitLossDetail(detail)) {
    return formatProfitLossValue(detail);
  }
  return detail.value;
};
const translateOverviewLabel = (label) => {
  const key = DETAIL_LABEL_KEYS[label];
  return key ? t(key, label) : label;
};

const user = computed(() => clientAuthStore.user);
const userInitials = computed(() => clientAuthStore.userInitials);
const userName = computed(() => {
  if (!user.value) return "User";
  return (
    user.value.fullName ||
    (user.value.firstName && user.value.lastName
      ? `${user.value.firstName} ${user.value.lastName}`
      : "") ||
    user.value.email ||
    "User"
  );
});

// Dashboard 数据（从API获取）
const stats = ref({
  totalNetwork: 0,
  networkChange: 0,
  totalEarned: 0,
  earnedChange: 0,
  thisMonth: 0,
  monthChange: 0,
  activeClients: 0,
  activeRate: 0,
  directClients: 0,
  subIbs: 0,
  tier2Ibs: 0,
  tier3Ibs: 0,
  totalDeposit: 0,
  totalWithdraw: 0,
  netDeposit: 0,
  totalTradingVolume: 0,
});

const referralUrl = ref("");
const referralUrlRef = ref(null);
const referralCode = ref("");

const urlStats = ref({
  clicks: 0,
  registrations: 0,
  conversionRate: 0,
});

const commissionRates = ref([]);
const ruleNames = ref([]);
const rulesList = ref([]);
const commissionStats = ref(null);
const ibCode = ref("");
const loading = ref(false);
const error = ref(null);
const ibPartners = ref([]);
const selectedIbPartnerId = ref("");

const formatIbPartnerLabel = (ibPartner) => {
  // 客户端展示统一优先使用管理员维护的 clientAlias，空则回落到 ibCode
  const alias = (ibPartner.clientAlias || "").trim();
  if (alias) return alias;
  return ibPartner.ibCode || `IB #${ibPartner.id}`;
};

const ibPartnerOptions = computed(() =>
  ibPartners.value.map((ibPartner) => ({
    value: ibPartner.id,
    label: formatIbPartnerLabel(ibPartner),
  })),
);

const selectedIbPartnerParams = () => {
  if (!selectedIbPartnerId.value) return {};
  return { ibPartnerId: selectedIbPartnerId.value };
};

const fetchIbPartners = async () => {
  const response = await ibDashboardService.getPartners();
  const data = response.data?.data || response.data;
  ibPartners.value = Array.isArray(data) ? data : [];

  if (ibPartners.value.length === 0) {
    selectedIbPartnerId.value = "";
    return;
  }

  const selectedExists = ibPartners.value.some(
    (ibPartner) => ibPartner.id === selectedIbPartnerId.value,
  );
  if (!selectedIbPartnerId.value || !selectedExists) {
    selectedIbPartnerId.value = ibPartners.value[0].id;
  }
};

const onIbPartnerChange = async () => {
  currentMemberPage.value = 1;
  expandedNodes.value = {};
  expandedMemberDetails.value = [];
  expandedMemberIds.value = [];
  highlightedNodeId.value = null;
  // 切换 IB 时取消未保存的别名编辑，避免改 A 的草稿被错按到 B 上
  cancelAlias();
  await fetchDashboardData();
};

// Name IB：客户端自己维护 clientAlias，仅作用于当前选中的 IB 身份
const aliasEditing = ref(false);
const aliasDraft = ref("");
const aliasSaving = ref(false);
const aliasError = ref("");

const currentIbPartner = computed(() => {
  if (!selectedIbPartnerId.value) return null;
  return (
    ibPartners.value.find((ib) => ib.id === selectedIbPartnerId.value) || null
  );
});

const currentIbLabel = computed(() => {
  return currentIbPartner.value
    ? formatIbPartnerLabel(currentIbPartner.value)
    : "";
});

const startNameIb = () => {
  if (!currentIbPartner.value) return;
  aliasDraft.value = (currentIbPartner.value.clientAlias || "").trim();
  aliasError.value = "";
  aliasEditing.value = true;
};

const cancelAlias = () => {
  aliasEditing.value = false;
  aliasDraft.value = "";
  aliasError.value = "";
};

const saveAlias = async () => {
  if (!currentIbPartner.value || aliasSaving.value) return;
  const raw = (aliasDraft.value ?? "").trim();
  const newAlias = raw === "" ? null : raw;
  // 与当前值一致就直接关闭，避免一次没必要的请求
  if ((currentIbPartner.value.clientAlias ?? null) === newAlias) {
    cancelAlias();
    return;
  }
  aliasSaving.value = true;
  aliasError.value = "";
  try {
    await ibDashboardService.updateClientAlias(
      currentIbPartner.value.id,
      newAlias,
    );
    currentIbPartner.value.clientAlias = newAlias;
    aliasEditing.value = false;
    aliasDraft.value = "";
  } catch (err) {
    // 预览模式下 axios 拦截器已经弹了 alert，这里就不在 inline 区域再重复提示了
    if (err?.isPreviewBlock) return;
    aliasError.value =
      err?.response?.data?.message ||
      err?.message ||
      t("ibActiveNameIbFailed", "Failed to update alias");
  } finally {
    aliasSaving.value = false;
  }
};

// Network Graph
const networkSearchQuery = ref("");
const expandLevel = ref("0");
const expandLevelOptions = computed(() => [
  { value: "0", label: t("ibActiveNone", "None") },
  { value: "1", label: `${t("ibActiveLevel", "Level")} 1` },
  { value: "2", label: `${t("ibActiveLevel", "Level")} 2` },
  { value: "3", label: `${t("ibActiveLevel", "Level")} 3` },
  { value: "5", label: `${t("ibActiveLevel", "Level")} 5` },
  { value: "10", label: `${t("ibActiveLevel", "Level")} 10` },
  { value: "15", label: `${t("ibActiveLevel", "Level")} 15` },
  { value: "all", label: t("ibActiveAllLevels", "All Levels") },
]);
const expandedNodes = ref({});
const highlightedNodeId = ref(null);
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

// 数据获取方法
const fetchDashboardData = async () => {
  loading.value = true;
  error.value = null;

  try {
    // 获取统计信息
    const params = selectedIbPartnerParams();
    const statsResponse = await ibDashboardService.getStatistics(params);
    const statsData = statsResponse.data?.data || statsResponse.data;
    if (statsData) {
      stats.value = {
        totalNetwork: statsData.totalNetwork || 0,
        networkChange: statsData.networkChange || 0,
        totalEarned: statsData.totalEarned || 0,
        earnedChange: statsData.earnedChange || 0,
        thisMonth: statsData.thisMonth || 0,
        monthChange: statsData.monthChange || 0,
        activeClients: statsData.activeClients || 0,
        activeRate: statsData.activeRate || 0,
        directClients: statsData.directClients || 0,
        subIbs: statsData.subIbs || 0,
        tier2Ibs: statsData.tier2Ibs || 0,
        tier3Ibs: statsData.tier3Ibs || 0,
        totalDeposit: statsData.totalDeposit || 0,
        totalWithdraw: statsData.totalWithdraw || 0,
        netDeposit: statsData.netDeposit || 0,
        totalTradingVolume: statsData.totalTradingVolume || 0,
      };
    }

    // 获取推荐URL
    const urlResponse = await ibDashboardService.getReferralUrl(params);
    const urlData = urlResponse.data?.data || urlResponse.data;
    if (urlData) {
      referralUrl.value = urlData.referralUrl || "";
      referralCode.value = urlData.referralCode || "";
      ibCode.value = urlData.referralCode || "";
      if (urlData.urlStats) {
        urlStats.value = {
          clicks: urlData.urlStats.clicks || 0,
          registrations: urlData.urlStats.registrations || 0,
          conversionRate: urlData.urlStats.conversionRate || 0,
        };
      }
    }

    // 获取佣金率、规则列表、佣金统计
    const ratesResponse = await ibDashboardService.getCommissionRates(params);
    const ratesData = ratesResponse.data?.data || ratesResponse.data;
    if (ratesData) {
      if (Array.isArray(ratesData)) {
        commissionRates.value = ratesData;
      } else {
        commissionRates.value = ratesData.rates || [];
        ruleNames.value = ratesData.ruleNames || [];
        rulesList.value = ratesData.rulesList || [];
        commissionStats.value = ratesData.commissionStats || null;
      }
    }

    // 获取网络图数据
    await fetchNetworkData();

    // 获取网络成员列表
    await fetchNetworkMembers();
  } catch (err) {
    console.error("Failed to fetch dashboard data:", err);
    error.value =
      err.response?.data?.message || "Failed to fetch dashboard data";
  } finally {
    loading.value = false;
  }
};

const fetchNetworkData = async () => {
  try {
    const response = await ibDashboardService.getNetwork(
      selectedIbPartnerParams(),
    );
    const data = response.data?.data || response.data;
    if (Array.isArray(data)) {
      networkMembers.value = data;
    }
  } catch (err) {
    console.error("Failed to fetch network data:", err);
  }
};

const memberListLoading = ref(false);
const fetchNetworkMembers = async () => {
  memberListLoading.value = true;
  try {
    const params = {
      page: currentMemberPage.value,
      per_page: membersPerPage.value,
    };
    if (memberListSearchQuery.value) {
      params.search = memberListSearchQuery.value;
    }
    if (memberTypeFilter.value !== "all") {
      params.type = memberTypeFilter.value;
    }
    Object.assign(params, selectedIbPartnerParams());

    const response = await ibDashboardService.getNetworkMembers(params);
    const data = response.data?.data || response.data;

    if (data) {
      if (data.items) {
        memberList.value = data.items;
        if (data.pagination) {
          currentMemberPage.value = data.pagination.page || 1;
          membersPerPage.value = data.pagination.per_page || 10;
          totalMemberCount.value = data.pagination.total || 0;
        }
      } else if (Array.isArray(data)) {
        memberList.value = data;
        totalMemberCount.value = data.length;
      }
    }
  } catch (err) {
    console.error("Failed to fetch network members:", err);
  } finally {
    memberListLoading.value = false;
  }
};

const networkMembers = ref([]);

// Member List
const memberListSearchQuery = ref("");
const memberTypeFilter = ref("all");
const memberTypeOptions = computed(() => [
  { value: "all", label: t("ibActiveAllMembers", "All Members") },
  { value: "ib", label: t("ibActiveSubIbsOnly", "Sub-IBs Only") },
  { value: "client", label: t("ibActiveClientsOnly", "Clients Only") },
]);
const expandedMemberDetails = ref([]);

const memberList = ref([]);

// 层级展示：按 depth/parentId/hasChildren 展开收起（参考 Commission Report）
const expandedMemberIds = ref([]);
const expandedMemberSet = computed(() => new Set(expandedMemberIds.value));
const getMemberRowKey = (m) => `${m.type}-${m.id}`;
const visibleMemberList = computed(() => {
  const list = memberList.value;
  const expanded = expandedMemberSet.value;
  const keyToRow = {};
  list.forEach((r) => {
    keyToRow[getMemberRowKey(r)] = r;
  });
  function isVisible(row) {
    const depth = Number(row.depth) || 0;
    if (depth === 0) return true;
    const pid = row.parentId != null ? Number(row.parentId) : null;
    if (pid == null || !expanded.has(pid)) return false;
    const parent = keyToRow["ib-" + pid];
    return parent ? isVisible(parent) : true;
  }
  return list.filter(isVisible);
});
function isMemberExpanded(id) {
  return id != null && expandedMemberSet.value.has(Number(id));
}
function toggleMemberExpand(id) {
  const numId = id != null ? Number(id) : null;
  if (numId == null) return;
  const arr = expandedMemberIds.value.slice();
  const idx = arr.indexOf(numId);
  if (idx >= 0) arr.splice(idx, 1);
  else arr.push(numId);
  expandedMemberIds.value = arr;
}

// 分页由后端处理，请求较大 per_page 以获取完整树
const filteredMemberList = computed(() => memberList.value);

// Member list：按直接绑定的子IB/Client分页，层级展示（与 Commission Report 一致）
const currentMemberPage = ref(1);
const membersPerPage = ref(10);
const pageSizeOptions = [
  { value: 10, label: "10" },
  { value: 20, label: "20" },
  { value: 50, label: "50" },
  { value: 100, label: "100" },
];
const totalMemberCount = ref(0);

const totalMemberPages = computed(() =>
  Math.max(1, Math.ceil(totalMemberCount.value / membersPerPage.value)),
);
const memberPaginationInfo = computed(() => {
  const total = totalMemberCount.value;
  if (total <= 0)
    return `${t("commShowingRecords", "Showing")} 0 ${t("commOf", "of")} 0`;
  const from = (currentMemberPage.value - 1) * membersPerPage.value + 1;
  const to = Math.min(currentMemberPage.value * membersPerPage.value, total);
  return `${t("commShowingRecords", "Showing")} ${from}-${to} ${t("commOf", "of")} ${total}`;
});
const memberPageOfText = computed(() => {
  const fmt = t("commPageOfFormat", "Page {0} of {1}");
  return fmt
    .replace("{0}", String(currentMemberPage.value))
    .replace("{1}", String(totalMemberPages.value));
});
function goToMemberPage(p) {
  const n = Number(p);
  if (n < 1 || n > totalMemberPages.value) return;
  currentMemberPage.value = n;
  expandedMemberDetails.value = [];
  fetchNetworkMembers();
}
function onMemberPerPageChange() {
  currentMemberPage.value = 1;
  fetchNetworkMembers();
}

const paginatedMemberList = computed(() => memberList.value);

const visibleMemberPages = computed(() => {
  const pages = [];
  const total = totalMemberPages.value;
  const current = currentMemberPage.value;

  if (total <= 7) {
    for (let i = 1; i <= total; i++) {
      pages.push(i);
    }
  } else {
    if (current <= 3) {
      for (let i = 1; i <= 5; i++) pages.push(i);
      pages.push("...");
      pages.push(total);
    } else if (current >= total - 2) {
      pages.push(1);
      pages.push("...");
      for (let i = total - 4; i <= total; i++) pages.push(i);
    } else {
      pages.push(1);
      pages.push("...");
      for (let i = current - 1; i <= current + 1; i++) pages.push(i);
      pages.push("...");
      pages.push(total);
    }
  }

  return pages.filter((p, i, arr) => {
    if (p === "...") return true;
    return arr.indexOf(p) === i;
  });
});

// Journey Modal
const showJourneyModal = ref(false);
const journeyEvents = ref([]);

// Methods
const copyReferralUrl = async () => {
  try {
    await navigator.clipboard.writeText(referralUrl.value);
    if (referralUrlRef.value) {
      referralUrlRef.value.style.background = "#d1fae5";
      referralUrlRef.value.style.borderColor = "#48bb78";
      setTimeout(() => {
        if (referralUrlRef.value) {
          referralUrlRef.value.style.background = "white";
          referralUrlRef.value.style.borderColor = "var(--color-border-strong)";
        }
      }, 2000);
    }
    alert("✓ " + t("ibActiveUrlCopied", "URL Copied to Clipboard!"));
  } catch (error) {
    alert(
      "✓ " +
        t("ibActiveUrlCopied", "URL Copied to Clipboard!") +
        "\n\n" +
        referralUrl.value,
    );
  }
};

const openJourneyModal = async () => {
  try {
    // 从接口获取journey时间线数据
    const response = await ibDashboardService.getJourney(
      selectedIbPartnerParams(),
    );
    const data = response.data?.data || response.data;
    if (Array.isArray(data)) {
      journeyEvents.value = data;
    }
  } catch (err) {
    console.error("Failed to fetch journey data:", err);
    // 如果获取失败，使用空数组
    journeyEvents.value = [];
  }

  showJourneyModal.value = true;
  document.body.style.overflow = "hidden";
};

const closeJourneyModal = () => {
  showJourneyModal.value = false;
  document.body.style.overflow = "";
};

const toggleNode = (nodeId) => {
  expandedNodes.value[nodeId] = !expandedNodes.value[nodeId];
};

const expandToLevel = () => {
  if (expandLevel.value === "0" || expandLevel.value === "none") {
    expandedNodes.value = {};
    return;
  }

  // 重置展开状态
  expandedNodes.value = {};

  // 总是展开 Tier 1
  expandedNodes.value.tier1 = true;

  // 如果选择 "all"，展开所有层级
  if (expandLevel.value === "all") {
    const expandAll = (members, level = 1) => {
      members.forEach((member) => {
        if (member.hasChildren) {
          expandedNodes.value[member.id] = true;
          if (member.children && member.children.length > 0) {
            expandAll(member.children, level + 1);
          }
        }
      });
    };
    expandAll(networkMembers.value, 1);
    return;
  }

  // 展开到指定层级
  const targetLevel = parseInt(expandLevel.value);
  if (isNaN(targetLevel) || targetLevel <= 0) {
    return;
  }

  const expandToTargetLevel = (members, currentLevel = 1) => {
    if (currentLevel > targetLevel) {
      return;
    }

    members.forEach((member) => {
      if (member.hasChildren && currentLevel < targetLevel) {
        expandedNodes.value[member.id] = true;
        if (member.children && member.children.length > 0) {
          expandToTargetLevel(member.children, currentLevel + 1);
        }
      }
    });
  };

  expandToTargetLevel(networkMembers.value, 1);
};

const collapseAll = () => {
  expandedNodes.value = {};
  expandLevel.value = "0";
};

// 递归搜索网络成员
const searchInMembers = (members, query, path = []) => {
  const results = [];
  const lowerQuery = query.toLowerCase().trim();

  if (!lowerQuery) {
    return results;
  }

  members.forEach((member, index) => {
    const currentPath = [...path, member.id];
    const memberName = (member.name || "").toLowerCase();
    const memberCode = (member.code || "").toLowerCase();

    // 检查是否匹配
    if (memberName.includes(lowerQuery) || memberCode.includes(lowerQuery)) {
      results.push({
        member,
        path: currentPath,
      });
    }

    // 递归搜索子节点
    if (member.children && member.children.length > 0) {
      const childResults = searchInMembers(member.children, query, currentPath);
      results.push(...childResults);
    }
  });

  return results;
};

// 展开到指定路径
const expandToPath = (path) => {
  // 展开 Tier 1
  expandedNodes.value.tier1 = true;

  // 展开路径中的所有节点
  path.forEach((nodeId) => {
    if (nodeId !== "tier1") {
      expandedNodes.value[nodeId] = true;
    }
  });
};

// 滚动到指定节点
const scrollToNode = (nodeId) => {
  nextTick(() => {
    const nodeElement = document.querySelector(`[data-node-id="${nodeId}"]`);
    if (nodeElement && networkContainerRef.value) {
      const containerRect = networkContainerRef.value.getBoundingClientRect();
      const nodeRect = nodeElement.getBoundingClientRect();

      // 计算节点相对于容器的位置
      const relativeX = nodeRect.left - containerRect.left + nodeRect.width / 2;
      const relativeY = nodeRect.top - containerRect.top + nodeRect.height / 2;

      // 计算需要平移的距离（将节点移动到容器中心）
      const targetX = containerRect.width / 2 - relativeX;
      const targetY = containerRect.height / 2 - relativeY;

      // 应用平移（考虑当前缩放）
      panX.value = targetX / zoom.value;
      panY.value = targetY / zoom.value;
    }
  });
};

const searchNetwork = () => {
  const query = networkSearchQuery.value.trim();

  // 清除之前的高亮
  highlightedNodeId.value = null;

  if (!query) {
    return;
  }

  // 搜索网络成员
  const results = searchInMembers(networkMembers.value, query);

  if (results.length > 0) {
    // 找到第一个匹配的成员
    const firstMatch = results[0];

    // 展开到该成员的路径
    expandToPath(firstMatch.path);

    // 高亮显示
    highlightedNodeId.value = firstMatch.member.id;

    // 滚动到该节点
    scrollToNode(firstMatch.member.id);
  } else {
    alert(
      `${t("ibActiveNoMembersFound", "No members found matching")} "${query}"`,
    );
  }
};

// Network Graph Drag & Zoom
const handleMouseDown = (e) => {
  if (e.target.closest(".expand-btn") || e.target.closest(".node-card")) {
    return;
  }
  isDragging.value = true;
  startX.value = e.clientX - panX.value;
  startY.value = e.clientY - panY.value;
  if (networkContainerRef.value) {
    networkContainerRef.value.classList.add("dragging");
  }
  e.preventDefault();
};

const handleMouseMove = (e) => {
  if (!isDragging.value) return;
  panX.value = e.clientX - startX.value;
  panY.value = e.clientY - startY.value;
};

const handleMouseUp = () => {
  if (isDragging.value) {
    isDragging.value = false;
    if (networkContainerRef.value) {
      networkContainerRef.value.classList.remove("dragging");
    }
  }
};

const handleWheel = (e) => {
  e.preventDefault();
  const delta = e.deltaY > 0 ? -0.1 : 0.1;
  zoom.value = Math.max(0.3, Math.min(3.0, zoom.value + delta));

  zoomLevel.value = Math.round(zoom.value * 100).toString();
  showZoomIndicator.value = true;
  setTimeout(() => {
    showZoomIndicator.value = false;
  }, 1000);
};

const resetNetworkView = (e) => {
  if (e.target.closest(".node-card") || e.target.closest(".expand-btn")) {
    return;
  }
  zoom.value = 1.0;
  panX.value = 0;
  panY.value = 0;
  zoomLevel.value = "100";
  showZoomIndicator.value = true;
  setTimeout(() => {
    showZoomIndicator.value = false;
  }, 1000);
};

const toggleMemberDetail = (memberKey) => {
  const index = expandedMemberDetails.value.indexOf(memberKey);
  if (index > -1) {
    expandedMemberDetails.value.splice(index, 1);
  } else {
    expandedMemberDetails.value = [memberKey];
  }
};

const filterMemberList = async () => {
  currentMemberPage.value = 1;
  await fetchNetworkMembers();
};

onMounted(async () => {
  document.addEventListener("mousemove", handleMouseMove);
  document.addEventListener("mouseup", handleMouseUp);

  // 加载数据
  await fetchIbPartners();
  await fetchDashboardData();
});

onUnmounted(() => {
  document.removeEventListener("mousemove", handleMouseMove);
  document.removeEventListener("mouseup", handleMouseUp);
  document.body.style.overflow = "";
});
</script>

<style scoped>
.ib-dashboard-active {
  max-width: 1400px;
  margin: 0 auto;
  padding: 20px;
}

.ib-dashboard-switcher {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  margin-bottom: 20px;
}

.ib-dashboard-switcher__label {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: var(--color-text);
  font-size: 14px;
  font-weight: 600;
  white-space: nowrap;
}

.ib-dashboard-switcher__label i {
  color: var(--color-brand);
}

.ib-dashboard-switcher__select {
  width: 320px;
}

.ib-dashboard-switcher__select :deep(.custom-select__trigger) {
  min-height: 40px;
  border-color: #d8e0ea;
  border-radius: var(--radius-md);
}

.ib-dashboard-switcher__single {
  display: inline-flex;
  align-items: center;
  min-height: 40px;
  padding: 6px 12px;
  border: 1px solid #d8e0ea;
  border-radius: var(--radius-md);
  background: var(--color-surface-soft);
  color: var(--color-ink);
  font-size: 14px;
  font-weight: 600;
}

.ib-dashboard-alias-input {
  min-height: 40px;
  padding: 6px 12px;
  border: 1px solid #d8e0ea;
  border-radius: var(--radius-md);
  font-size: 14px;
  color: var(--color-ink);
  outline: none;
  min-width: 200px;
}
.ib-dashboard-alias-input:focus {
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.15);
}

.ib-dashboard-alias-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 40px;
  padding: 0 14px;
  border: 1px solid #d8e0ea;
  border-radius: var(--radius-md);
  background: #fff;
  color: var(--color-text);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition:
    background 0.15s,
    color 0.15s,
    border-color 0.15s;
}
.ib-dashboard-alias-btn:hover:not(:disabled) {
  background: #edf2ff;
  color: var(--color-brand-strong);
  border-color: var(--color-brand-strong);
}
.ib-dashboard-alias-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.ib-dashboard-alias-btn--primary {
  background: var(--color-brand);
  color: #fff;
  border-color: var(--color-brand);
}
.ib-dashboard-alias-btn--primary:hover:not(:disabled) {
  background: var(--color-brand-strong);
  color: #fff;
  border-color: var(--color-brand-strong);
}

.ib-dashboard-alias-error {
  color: var(--color-danger);
  font-size: 12px;
  margin-left: 4px;
}

/* Page Header */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  flex-wrap: wrap;
  gap: 20px;
}

.page-title h1 {
  font-size: 28px;
  color: var(--color-ink);
  margin: 0 0 4px 0;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 10px;
}

.page-title h1 i {
  color: var(--color-brand);
}

.page-title p {
  font-size: 14px;
  color: var(--color-muted);
  margin: 0;
}

.page-actions {
  display: flex;
  align-items: center;
  gap: 16px;
}

/* Statistics Grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
  margin-bottom: 30px;
}

.stat-card {
  background: white;
  border-radius: var(--radius-lg);
  padding: 24px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
}

.stat-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.stat-card-title {
  font-size: 14px;
  color: var(--color-muted);
  font-weight: 500;
}

.stat-card-icon {
  width: 48px;
  height: 48px;
  border-radius: var(--radius-lg);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  color: white;
}

.stat-card-icon.purple {
  background: var(--color-brand);
}

.stat-card-icon.green {
  background: linear-gradient(
    135deg,
    var(--color-success) 0%,
    var(--color-success) 100%
  );
}

.stat-card-icon.orange {
  background: linear-gradient(
    135deg,
    var(--color-warning) 0%,
    var(--color-warning) 100%
  );
}

.stat-card-icon.blue {
  background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
}

.stat-card-value {
  font-size: 32px;
  font-weight: 700;
  color: var(--color-ink);
  margin-bottom: 8px;
}

.stat-card-label {
  font-size: 13px;
  color: var(--color-muted);
  margin-bottom: 12px;
}

.stat-card-trend {
  font-size: 12px;
  color: var(--color-success);
  display: flex;
  align-items: center;
  gap: 4px;
}

.stat-card-trend i {
  font-size: 10px;
}

/* URL Card */
.url-card {
  background: linear-gradient(
    135deg,
    var(--color-brand-soft) 0%,
    var(--color-brand-soft) 100%
  );
  border: 2px solid var(--color-brand);
  border-radius: var(--radius-lg);
  padding: 25px;
  margin-bottom: 30px;
}

.url-card-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 15px;
}

.url-card-icon {
  width: 50px;
  height: 50px;
  background: var(--color-brand);
  border-radius: var(--radius-lg);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  color: white;
}

.url-card-title {
  flex: 1;
}

.url-card-title h3 {
  font-size: 18px;
  color: var(--color-ink);
  margin-bottom: 3px;
  font-weight: 600;
}

.url-card-title p {
  font-size: 13px;
  color: var(--color-muted);
  margin: 0;
}

.url-display {
  background: white;
  border: 2px solid var(--color-border-strong);
  border-radius: var(--radius-md);
  padding: 15px 20px;
  display: flex;
  align-items: center;
  gap: 15px;
  margin-bottom: 15px;
}

.url-text {
  flex: 1;
  font-family: "Courier New", monospace;
  font-size: 14px;
  color: var(--color-brand);
  font-weight: 600;
  word-break: break-all;
}

.btn-icon {
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
    opacity 0.3s ease,
    transform 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.url-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 15px;
}

.url-stat-item {
  text-align: center;
  padding: 12px;
  background: white;
  border-radius: var(--radius-md);
}

.url-stat-value {
  font-size: 20px;
  font-weight: 700;
  color: var(--color-brand);
  margin-bottom: 4px;
}

.url-stat-label {
  font-size: 12px;
  color: var(--color-muted);
}

/* Your IB Referral URL Card（样式与后台 Sales Dashboard - Your Sales Referral URL 一致） */
.ib-referral-url-card {
  background: linear-gradient(
    135deg,
    var(--color-brand-soft) 0%,
    var(--color-brand-soft) 100%
  );
  border: 2px solid var(--color-brand);
  border-radius: var(--radius-lg);
  padding: 25px;
  margin-bottom: 30px;
}

.ib-referral-url-card-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 15px;
}

.ib-referral-url-card-icon {
  width: 50px;
  height: 50px;
  background: var(--color-brand);
  border-radius: var(--radius-lg);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  color: white;
}

.ib-referral-url-card-title {
  flex: 1;
}

.ib-referral-url-card-title h3 {
  font-size: 18px;
  color: var(--color-ink);
  margin: 0 0 3px 0;
  font-weight: 600;
}

.ib-referral-url-card-title p {
  font-size: 13px;
  color: var(--color-muted);
  margin: 0;
}

.ib-referral-url-display {
  background: white;
  border: 2px solid var(--color-border-strong);
  border-radius: var(--radius-md);
  padding: 15px 20px;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 15px;
  margin-bottom: 15px;
}

.ib-referral-url-text {
  flex: 1;
  min-width: 0;
  font-family: "Courier New", monospace;
  font-size: 14px;
  color: var(--color-brand);
  font-weight: 600;
  word-break: break-all;
}

.ib-referral-url-btn-copy {
  padding: 8px 16px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: var(--color-brand);
  color: white;
}

.ib-referral-url-btn-copy:hover {
  background: var(--color-brand-strong);
  color: white;
}

.ib-referral-url-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 15px;
}

.ib-referral-url-stat-item {
  text-align: center;
  padding: 12px;
  background: white;
  border-radius: var(--radius-md);
}

.ib-referral-url-stat-value {
  font-size: 20px;
  font-weight: 700;
  color: var(--color-brand);
  margin-bottom: 4px;
}

.ib-referral-url-stat-label {
  font-size: 12px;
  color: var(--color-muted);
}

/* Commission Rate Card */
.commission-rate-card {
  background: white;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: 25px;
  margin-bottom: 30px;
}

.commission-rate-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 2px solid var(--color-border);
}

.commission-rate-title {
  font-size: 18px;
  font-weight: 700;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 10px;
}

.commission-rate-title i {
  color: var(--color-brand);
}

.commission-header-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.btn-journey {
  background: var(--color-brand);
  color: white;
  padding: 10px 18px;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  border: none;
  cursor: pointer;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    opacity 0.3s ease,
    transform 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  box-shadow: 0 2px 8px rgba(var(--color-brand-rgb), 0.3);
}

.btn-journey:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.4);
}

.commission-badge {
  background: linear-gradient(
    135deg,
    var(--color-success) 0%,
    var(--color-success) 100%
  );
  color: white;
  padding: 8px 16px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%;
  border-radius: 20px;
  font-size: 14px;
  font-weight: 700;
}

.commission-stats-section {
  margin-bottom: 24px;
  padding: 16px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border);
}

.commission-stats-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
  margin: 0 0 12px 0;
}

.commission-stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 16px;
  margin-bottom: 12px;
}

.commission-stat-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.commission-stat-label {
  font-size: 12px;
  color: var(--color-muted);
}

.commission-stat-value {
  font-size: 18px;
  font-weight: 700;
  color: var(--color-ink);
}

.commission-by-rule {
  font-size: 13px;
  color: var(--color-text);
}

.commission-by-rule-label {
  font-weight: 600;
  margin-right: 6px;
}

.commission-by-rule-list {
  display: inline;
}

.commission-by-rule-item {
  white-space: normal;
}

.commission-rules-table-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
  margin: 0 0 10px 0;
}

.commission-rules-table-wrap {
  overflow-x: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
}

.commission-rules-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.commission-rules-table th,
.commission-rules-table td {
  padding: 10px 12px;
  text-align: left;
  border-bottom: 1px solid var(--color-border);
}

.commission-rules-table th {
  background: var(--color-surface-soft);
  font-weight: 600;
  color: var(--color-text);
}

.commission-rules-table tr:last-child td {
  border-bottom: none;
}

.commission-rules-table .empty-rules {
  color: var(--color-muted);
  text-align: center;
  padding: 20px;
}

.commission-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 15px;
}

.commission-item {
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 20px;
  text-align: center;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    opacity 0.3s ease,
    transform 0.3s ease;
}

.commission-item:hover {
  background: var(--color-brand-soft);
  border-color: var(--color-brand);
  transform: translateY(-3px);
}

.commission-product {
  font-size: 13px;
  color: var(--color-muted);
  font-weight: 600;
  text-transform: uppercase;
  margin-bottom: 8px;
}

.commission-rate {
  font-size: 24px;
  font-weight: 700;
  color: var(--color-brand);
  margin-bottom: 5px;
}

.commission-type {
  font-size: 12px;
  color: var(--color-faint);
}

/* Content Section */
.content-section {
  background: white;
  border-radius: var(--radius-lg);
  padding: 24px;
  margin-bottom: 30px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  flex-wrap: wrap;
  gap: 12px;
}

.section-title {
  font-size: 18px;
  font-weight: 600;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 10px;
}

.section-title i {
  color: var(--color-brand);
}

.section-controls {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.network-search-wrapper {
  position: relative;
}

.network-search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-faint);
  font-size: 14px;
}

.network-search-input {
  padding: 8px 12px 8px 36px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: 13px;
  width: 200px;
  transition: border-color 0.2s ease;
}

.network-search-input:focus {
  outline: none;
  border-color: var(--color-brand);
}

.level-selector {
  display: flex;
  align-items: center;
  gap: 8px;
}

.level-selector label {
  font-size: 13px;
  color: var(--color-text);
}

.level-selector :deep(.custom-select__trigger) {
  min-height: 34px;
  padding: 6px 10px;
  border-radius: var(--radius-md);
  border-color: #d8e0ea;
  font-size: 13px;
}

.level-selector :deep(.custom-select__menu) {
  min-width: 100%;
}

/* Show Rows（与 Commission Report 一致） */
.network-show-rows {
  display: flex;
  align-items: center;
  gap: 8px;
}
.network-show-rows__label {
  font-size: 14px;
  color: var(--color-text);
  white-space: nowrap;
}
.network-show-rows__select {
  min-width: 72px;
}

.network-show-rows__select :deep(.custom-select__trigger) {
  min-height: 34px;
  padding: 6px 10px;
  border-radius: var(--radius-md);
  border-color: #d8e0ea;
  font-size: 13px;
}

.network-show-rows__select :deep(.custom-select__trigger-text) {
  text-align: left;
}

.network-show-rows__select :deep(.custom-select__menu) {
  min-width: 100%;
}

/* 底部分页（与 Commission Report 一致：左 Showing x-y of z，右 Prev + Page n of N + Next） */
.network-pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 15px;
  margin-top: 0;
  padding: 16px 24px;
  background: var(--color-surface-soft);
  border-top: 1px solid var(--color-border);
}
.network-pagination__info {
  font-size: 14px;
  color: var(--color-text);
}
.network-pagination__btns {
  display: flex;
  align-items: center;
  gap: 12px;
}
.network-pagination__page {
  font-size: 14px;
  color: var(--color-text);
}
.network-pagination__btn {
  padding: 8px 14px;
  font-size: 13px;
  background: var(--color-border);
  color: var(--color-text);
  border: none;
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition:
    background 0.2s,
    color 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.network-pagination__btn:hover:not(:disabled) {
  background: var(--color-border-strong);
  color: var(--color-ink);
}
.network-pagination__btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-collapse-all {
  padding: 8px 16px;
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: 13px;
  color: var(--color-text);
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 6px;
  transition:
    color 0.2s ease,
    background-color 0.2s ease,
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    opacity 0.2s ease,
    transform 0.2s ease;
}

.btn-collapse-all:hover {
  background: var(--color-surface-muted);
  border-color: var(--color-border-strong);
}

.network-info-banner {
  background: var(--color-brand-soft);
  padding: 12px;
  border-radius: var(--radius-md);
  margin-bottom: 20px;
  border-left: 4px solid var(--color-brand);
  display: flex;
  align-items: center;
  gap: 8px;
}

.network-info-banner i {
  color: var(--color-brand);
  font-size: 16px;
  flex-shrink: 0;
}

.network-info-banner span {
  color: var(--color-text);
  font-size: 13px;
}

.zoom-indicator {
  position: absolute;
  top: 20px;
  right: 20px;
  background: rgba(var(--color-brand-rgb), 0.95);
  color: white;
  padding: 8px 16px;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 600;
  z-index: 100;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
  pointer-events: none;
  display: none;
}

.network-container {
  background: white;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: 40px;
  overflow: hidden;
  min-height: 500px;
  max-height: 700px;
  position: relative;
  cursor: grab;
  user-select: none;
}

.network-container.dragging {
  cursor: grabbing;
}

.network-container:active {
  cursor: grabbing;
}

.network-graph {
  display: flex;
  flex-direction: row;
  align-items: flex-start;
  min-width: max-content;
  padding: 20px;
}

.network-level {
  display: flex;
  flex-direction: column;
  justify-content: flex-start;
  gap: 30px;
  position: relative;
  margin-left: 60px;
}

.network-level:first-child {
  margin-left: 0;
}

.network-branch {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 60px;
  position: relative;
}

.network-branch::before {
  content: "";
  position: absolute;
  left: -30px;
  top: 50%;
  width: 30px;
  height: 2px;
  background: var(--color-border-strong);
  transform: translateY(-50%);
}

.network-branch:first-child::before {
  display: none;
}

.network-node {
  position: relative;
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 20px;
}

.node-card {
  background: white;
  border: 3px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: 16px 20px;
  min-width: 200px;
  max-width: 280px;
  text-align: left;
  cursor: pointer;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    opacity 0.3s ease,
    transform 0.3s ease;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  position: relative;
}

.node-card:hover {
  transform: translateX(4px);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
  border-color: var(--color-brand);
}

.node-card.tier1 {
  background: var(--color-brand);
  border-color: var(--color-brand-strong);
  color: white;
  min-width: 240px;
  padding: 20px 24px;
}

.node-card.tier2 {
  background: linear-gradient(
    135deg,
    var(--color-success) 0%,
    var(--color-success) 100%
  );
  border-color: var(--color-success);
  color: white;
  min-width: 220px;
}

.node-card.tier3 {
  background: linear-gradient(
    135deg,
    var(--color-warning) 0%,
    var(--color-warning) 100%
  );
  border-color: var(--color-warning);
  color: white;
  min-width: 200px;
}

.node-card.client {
  background: var(--color-surface-soft);
  border-color: var(--color-border);
  min-width: 180px;
  padding: 12px 16px;
}

.node-card.client:hover {
  background: var(--color-brand-soft);
  border-color: #a5b4fc;
}

.node-card.collapsed-summary {
  background: var(--color-warning-soft);
  border-color: var(--color-warning);
  border-style: dashed;
  color: var(--color-warning);
  min-width: 160px;
  text-align: center;
  padding: 12px 16px;
}

.node-content {
  display: flex;
  align-items: center;
  gap: 12px;
}

.node-avatar {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  font-weight: 700;
  border: 3px solid rgba(255, 255, 255, 0.3);
  flex-shrink: 0;
}

.node-card.tier1 .node-avatar {
  background: rgba(255, 255, 255, 0.2);
  color: white;
  width: 56px;
  height: 56px;
  font-size: 18px;
}

.node-card.tier2 .node-avatar {
  background: rgba(255, 255, 255, 0.2);
  color: white;
}

.node-card.tier3 .node-avatar {
  background: rgba(255, 255, 255, 0.2);
  color: white;
}

.node-card.client .node-avatar {
  background: var(--color-border);
  color: var(--color-text);
  width: 40px;
  height: 40px;
  font-size: 14px;
  border: 2px solid var(--color-border-strong);
}

.node-info {
  flex: 1;
  min-width: 0;
}

.node-title {
  font-size: 15px;
  font-weight: 700;
  margin-bottom: 4px;
  line-height: 1.3;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.node-card.tier1 .node-title {
  font-size: 17px;
}

.node-card.client .node-title {
  font-size: 14px;
  color: var(--color-ink);
}

.node-subtitle {
  font-size: 12px;
  opacity: 0.85;
  margin-bottom: 6px;
}

.node-card.client .node-subtitle {
  color: var(--color-muted);
}

.node-badge {
  display: inline-block;
  padding: 3px 8px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: var(--radius-md);
  font-size: 10px;
  font-weight: 600;
}

.node-card.tier1 .node-badge {
  background: rgba(255, 255, 255, 0.25);
}

.node-stats {
  display: flex;
  gap: 12px;
  margin-top: 8px;
  padding-top: 8px;
  border-top: 1px solid rgba(255, 255, 255, 0.2);
  font-size: 11px;
  flex-wrap: wrap;
}

.node-card.client .node-stats {
  border-top: 1px solid var(--color-border);
  margin-top: 6px;
  padding-top: 6px;
}

.node-card.highlighted {
  border-color: var(--color-warning) !important;
  border-width: 4px !important;
  box-shadow: 0 0 20px rgba(245, 158, 11, 0.5) !important;
  animation: highlightPulse 1.5s ease-in-out infinite;
}

@keyframes highlightPulse {
  0%,
  100% {
    box-shadow: 0 0 20px rgba(245, 158, 11, 0.5);
  }
  50% {
    box-shadow: 0 0 30px rgba(245, 158, 11, 0.8);
  }
}

.node-stat {
  display: flex;
  align-items: center;
  gap: 4px;
}

.expand-btn {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: white;
  border: 3px solid var(--color-border-strong);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    transform 0.3s ease;
  font-size: 16px;
  font-weight: 700;
  color: var(--color-text);
  flex-shrink: 0;
  z-index: 10;
}

.expand-btn:hover {
  background: var(--color-brand);
  border-color: var(--color-brand);
  color: white;
  transform: scale(1.1);
}

.expand-btn.expanded {
  background: var(--color-brand);
  border-color: var(--color-brand);
  color: white;
}

.node-card.tier1 .expand-btn {
  border-color: rgba(255, 255, 255, 0.4);
  background: rgba(255, 255, 255, 0.15);
  color: white;
}

.node-card.tier1 .expand-btn:hover,
.node-card.tier1 .expand-btn.expanded {
  background: rgba(255, 255, 255, 0.3);
  border-color: white;
}

.node-card.tier2 .expand-btn {
  border-color: rgba(255, 255, 255, 0.4);
  background: rgba(255, 255, 255, 0.15);
  color: white;
}

.node-card.tier2 .expand-btn:hover,
.node-card.tier2 .expand-btn.expanded {
  background: rgba(255, 255, 255, 0.3);
  border-color: white;
}

.node-card.tier3 .expand-btn {
  border-color: rgba(255, 255, 255, 0.4);
  background: rgba(255, 255, 255, 0.15);
  color: white;
}

.node-card.tier3 .expand-btn:hover,
.node-card.tier3 .expand-btn.expanded {
  background: rgba(255, 255, 255, 0.3);
  border-color: white;
}

.network-children {
  display: none;
  flex-direction: column;
  gap: 30px;
  margin-left: 60px;
  position: relative;
}

.network-children::before {
  content: "";
  position: absolute;
  left: -30px;
  top: 0;
  bottom: 0;
  width: 2px;
  background: var(--color-border-strong);
}

.network-children::after {
  content: "";
  position: absolute;
  left: -30px;
  top: 50%;
  width: 30px;
  height: 2px;
  background: var(--color-border-strong);
  transform: translateY(-50%);
}

.network-children.expanded {
  display: flex;
}

.network-summary {
  display: flex;
  justify-content: space-around;
  padding: 20px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border);
}

.summary-value {
  font-size: 28px;
  font-weight: 700;
  color: var(--color-ink);
  text-align: center;
  margin-bottom: 4px;
}

.summary-value.tier2 {
  color: var(--color-brand);
}

.summary-value.tier3 {
  color: var(--color-success);
}

.summary-value.clients {
  color: var(--color-warning);
}

.summary-label {
  font-size: 12px;
  color: var(--color-muted);
  text-align: center;
}

/* Members Table */
.members-table-container {
  background: white;
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  overflow-x: auto;
  overflow-y: hidden;
}

.members-table {
  width: 100%;
  border-collapse: collapse;
}

.members-table thead {
  background: var(--color-surface-soft);
}

.members-table th {
  padding: 16px 20px;
  text-align: left;
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 2px solid var(--color-border);
}

.members-table tbody tr {
  border-bottom: 1px solid var(--color-border);
  transition:
    color 0.2s ease,
    background-color 0.2s ease,
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    opacity 0.2s ease,
    transform 0.2s ease;
}

.members-table tbody tr:hover {
  background: var(--color-surface-soft);
}

.members-table tbody tr.expanded {
  background: var(--color-brand-soft);
}

.members-table tbody tr.highlighted {
  background: var(--color-warning-soft);
  animation: highlightPulse 1.5s ease-in-out;
}

@keyframes highlightPulse {
  0%,
  100% {
    background: var(--color-warning-soft);
  }
  50% {
    background: var(--color-warning-border);
  }
}

.members-table td {
  padding: 16px 20px;
  font-size: 14px;
  color: var(--color-text);
}

/* 加载/空态行：colspan 占满整行后图标和文案居中堆叠，避免挤在第一列左侧 */
.members-table tbody tr:has(> td.empty-state):hover {
  background: transparent;
}

.members-table td.empty-state {
  padding: 48px 20px;
  text-align: center;
  color: var(--color-faint);
}

.members-table td.empty-state i {
  display: block;
  font-size: 32px;
  margin-bottom: 12px;
  color: var(--color-border-strong);
}

.members-table td.empty-state p {
  margin: 0;
  font-size: 14px;
}

.member-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.member-info--tree {
  display: flex;
  align-items: center;
  gap: 8px;
}

.tree-toggle {
  appearance: none;
  width: 24px;
  height: 24px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--color-border-strong);
  border-radius: 4px;
  background: var(--color-surface-soft);
  cursor: pointer;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
  flex-shrink: 0;
}

.tree-toggle:hover {
  background: var(--color-border);
  border-color: var(--color-faint);
}

.tree-toggle--empty {
  visibility: hidden;
  pointer-events: none;
}

.members-table tbody tr.row-child .member-name {
  font-weight: 500;
}

.member-avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 15px;
  font-weight: 700;
  color: white;
  flex-shrink: 0;
}

.member-avatar.tier2-bg {
  background: linear-gradient(
    135deg,
    var(--color-success) 0%,
    var(--color-success) 100%
  );
}

.member-avatar.tier3-bg {
  background: linear-gradient(
    135deg,
    var(--color-warning) 0%,
    var(--color-warning) 100%
  );
}

.member-avatar.client-bg {
  background: var(--color-brand);
}

.member-details {
  flex: 1;
}

.member-name {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 2px;
}

.member-code {
  font-size: 12px;
  color: var(--color-muted);
}

.member-type-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: var(--radius-lg);
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
}

.member-type-badge.sub-ib {
  background: linear-gradient(135deg, #a855f7 0%, #7e22ce 100%);
  color: white;
}

.member-type-badge.direct-client {
  background: linear-gradient(135deg, #3b82f6 0%, var(--color-info) 100%);
  color: white;
}

.performance-info {
  font-size: 13px;
  color: var(--color-text);
}

.performance-info > div {
  margin-bottom: 4px;
}

.performance-info > div:last-child {
  margin-bottom: 0;
}

.commission-cell {
  font-weight: 600;
  color: var(--color-ink);
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

.btn-action {
  padding: 6px 12px;
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: 12px;
  color: var(--color-text);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition:
    color 0.2s ease,
    background-color 0.2s ease,
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    opacity 0.2s ease,
    transform 0.2s ease;
}

.btn-action:hover {
  background: var(--color-surface-muted);
  border-color: var(--color-border-strong);
}

.btn-detail {
  background: var(--color-brand);
  color: white;
  border: none;
}

.btn-detail:hover {
  background: linear-gradient(
    135deg,
    var(--color-brand-strong) 0%,
    var(--color-brand) 100%
  );
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
}

.detail-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 20px;
  margin-bottom: 20px;
}

.detail-item {
  background: white;
  padding: 15px;
  border-radius: var(--radius-md);
  border: 2px solid var(--color-border);
}

.detail-label {
  font-size: 12px;
  color: var(--color-muted);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 8px;
  display: block;
}

.detail-value {
  font-size: 18px;
  font-weight: 700;
  color: var(--color-ink);
}

.ib-dashboard-pnl--positive {
  color: var(--color-success) !important;
}

.ib-dashboard-pnl--negative {
  color: var(--color-danger) !important;
}

.ib-dashboard-pnl--neutral {
  color: var(--color-ink) !important;
}

.performance-overview-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 12px;
}

.overview-item {
  padding: 12px;
  background: white;
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border);
  text-align: center;
}

.overview-label {
  font-size: 12px;
  color: var(--color-muted);
  margin-bottom: 4px;
}

.overview-value {
  font-size: 18px;
  font-weight: 700;
}

/* Pagination */
.pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 24px;
  padding-top: 20px;
  border-top: 1px solid var(--color-border);
  flex-wrap: wrap;
  gap: 16px;
}

.pagination-info {
  font-size: 13px;
  color: var(--color-muted);
}

.pagination-controls {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.pagination-btn {
  padding: 8px 12px;
  background: white;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: 13px;
  color: var(--color-text);
  cursor: pointer;
  transition:
    color 0.2s ease,
    background-color 0.2s ease,
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    opacity 0.2s ease,
    transform 0.2s ease;
  min-width: 40px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.pagination-btn:hover:not(:disabled) {
  background: var(--color-surface-soft);
  border-color: var(--color-border-strong);
}

.pagination-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.pagination-btn.active {
  background: var(--color-brand);
  color: white;
  border-color: transparent;
}

/* Journey Modal */
.journey-modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  z-index: 2000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  animation: fadeIn 0.3s ease;
}

.journey-modal-container {
  background: white;
  border-radius: var(--radius-xl);
  max-width: 800px;
  width: 100%;
  max-height: 90vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  animation: slideDown 0.3s ease;
}

.journey-modal-header {
  background: var(--color-brand);
  color: white;
  padding: 24px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.journey-modal-header h2 {
  margin: 0;
  font-size: 20px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.journey-modal-close-btn {
  background: rgba(255, 255, 255, 0.2);
  border: none;
  color: white;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.2s ease;
}

.journey-modal-close-btn:hover {
  background: rgba(255, 255, 255, 0.3);
}

.journey-modal-body {
  flex: 1;
  overflow-y: auto;
  padding: 24px;
}

.timeline {
  position: relative;
  padding-left: 40px;
}

.timeline::before {
  content: "";
  position: absolute;
  left: 15px;
  top: 0;
  bottom: 0;
  width: 2px;
  background: var(--color-border);
}

.timeline-item {
  position: relative;
  margin-bottom: 32px;
}

.timeline-dot {
  position: absolute;
  left: -32px;
  top: 0;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--color-border);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--color-muted);
  font-size: 14px;
  border: 3px solid white;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.timeline-dot.current {
  background: var(--color-brand);
  color: white;
}

.timeline-content {
  background: var(--color-surface-soft);
  border-radius: var(--radius-lg);
  padding: 20px;
  border: 1px solid var(--color-border);
}

.timeline-date {
  font-size: 12px;
  color: var(--color-muted);
  margin-bottom: 8px;
  font-weight: 600;
}

.timeline-title {
  font-size: 16px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 8px;
}

.timeline-description {
  font-size: 14px;
  color: var(--color-text);
  line-height: 1.6;
  margin-bottom: 12px;
}

.timeline-tier-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.timeline-tier-badge.milestone {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.timeline-tier-badge.bonus {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.timeline-tier-badge.earning {
  background: var(--color-brand-soft);
  color: var(--color-brand-strong);
}

.timeline-tier-badge.partnership {
  background: #fce7f3;
  color: #be185d;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

@keyframes slideDown {
  from {
    transform: translateY(-50px);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

@media (max-width: 768px) {
  .ib-dashboard-switcher {
    align-items: stretch;
    flex-direction: column;
  }

  .ib-dashboard-switcher__select {
    width: 100%;
  }

  .stats-grid {
    grid-template-columns: 1fr;
  }

  .url-stats,
  .ib-referral-url-stats {
    grid-template-columns: 1fr;
  }

  .commission-grid {
    grid-template-columns: 1fr;
  }

  .section-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .section-controls {
    width: 100%;
    flex-direction: column;
    align-items: stretch;
  }

  .network-search-wrapper {
    width: 100%;
  }

  .network-search-input {
    width: 100%;
  }

  .level-selector {
    width: 100%;
  }

  .level-selector :deep(.custom-select) {
    width: 100%;
  }

  .network-show-rows {
    width: 100%;
  }

  .network-show-rows__select {
    width: 100%;
  }

  .network-container {
    height: 400px;
  }

  .network-summary {
    flex-direction: column;
    gap: 16px;
  }

  .members-table {
    font-size: 12px;
    min-width: 700px;
  }

  .members-table th,
  .members-table td {
    padding: 8px;
    white-space: nowrap;
  }
}
</style>
