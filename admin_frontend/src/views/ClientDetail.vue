<template>
  <div class="client-detail-page ui-page workspace-detail-page">
    <!-- Page header and client lookup -->
    <div class="page-header ui-page-header">
      <div class="page-title">
        <h1>{{ t("page_clientDetail_title", "Client Detail") }}</h1>
        <p>
          {{ t("page_clientDetail_sub", "View and manage client information") }}
        </p>
      </div>
      <div class="page-actions">
        <PageHeaderActions />
      </div>
    </div>

    <router-link to="/clients-list" class="btn-back">
      <i class="fas fa-arrow-left"></i>
      {{ t("clientDetail_backToClientList", "Back to Client List") }}
    </router-link>

    <!--    <div class="client-search-bar">-->
    <!--      <div class="search-field">-->
    <!--        <label for="client-id-search">{{ t('clientDetail_idSearch', 'ID Search') }}</label>-->
    <!--        <input-->
    <!--          id="client-id-search"-->
    <!--          v-model="searchId"-->
    <!--          type="text"-->
    <!--          class="search-input"-->
    <!--          :placeholder="t('clientDetail_clientId', 'Client ID')"-->
    <!--          @keyup.enter="handleClientSearch"-->
    <!--        >-->
    <!--      </div>-->
    <!--      <button class="btn-search" type="button" @click="handleClientSearch">-->
    <!--        <i class="fas fa-search"></i>-->
    <!--        {{ t('common_search', 'Search') }}-->
    <!--      </button>-->
    <!--    </div>-->

    <div class="client-detail-container">
      <div
        v-if="clientLoading && !clientDetail"
        class="client-detail-state-card"
      >
        <i class="fas fa-spinner fa-spin"></i>
        <p>
          {{ t("clientDetail_loadingProfile", "Loading client profile...") }}
        </p>
      </div>

      <div v-else-if="clientDetailError" class="client-detail-state-card error">
        <i class="fas fa-exclamation-circle"></i>
        <p>{{ clientDetailError }}</p>
      </div>

      <div v-else-if="!clientDetail" class="client-detail-state-card">
        <i class="fas fa-spinner fa-spin"></i>
        <p>
          {{ t("clientDetail_loadingProfile", "Loading client profile...") }}
        </p>
      </div>

      <template v-else>
        <!-- Client identity summary shown above every tab -->
        <div class="client-profile-card">
          <div class="client-profile-info">
            <div class="client-badges">
              <span class="badge-client">{{
                t("clientDetail_badgeClient", "Client")
              }}</span>
            </div>
            <div class="client-profile-name">{{ displayClientName }}</div>
            <div class="client-profile-id">({{ clientId }})</div>
          </div>
        </div>

        <!-- Main tab navigation. The IB tab is added only when the client has an IB partner. -->
        <div class="tab-nav">
          <button
            v-for="tab in tabs"
            :key="tab.key"
            type="button"
            class="tab-nav-item"
            :class="{ active: activeTab === tab.key }"
            @click.prevent.stop="selectTab(tab.key)"
          >
            {{ tab.label }}
          </button>
        </div>

        <div class="tab-content-wrapper">
          <!-- Profile tab: editable client data, internal metadata, and KYC answers -->
          <div v-if="activeTab === 'profile'" class="tab-pane">
            <div v-if="clientLoading" class="profile-loading">
              <i class="fas fa-spinner fa-spin"></i>
              {{
                t("clientDetail_loadingProfile", "Loading client profile...")
              }}
            </div>
            <div class="profile-verified-banner" :class="profileStatusClass">
              <i class="fas fa-check-circle"></i>
              PROFILE {{ profileStatusText }}.
            </div>

            <div class="balance-table-wrapper">
              <table class="detail-table">
                <thead>
                  <tr>
                    <th>{{ t("clientDetail_thCurrency", "CURRENCY") }}</th>
                    <th>
                      {{ t("clientDetail_thWalletBalance", "WALLET BALANCE") }}
                    </th>
                    <th>
                      {{ t("clientDetail_thTotalBalance", "TOTAL BALANCE") }}
                    </th>
                    <th>
                      {{ t("clientDetail_thTotalEquity", "TOTAL EQUITY") }}
                    </th>
                    <th>
                      {{ t("clientDetail_thTotalCredit", "TOTAL CREDIT") }}
                    </th>
                    <th>
                      {{ t("clientDetail_thTotalDeposit", "TOTAL DEPOSIT") }}
                    </th>
                    <th>
                      {{
                        t(
                          "clientDetail_thTotalWithdrawals",
                          "TOTAL WITHDRAWALS",
                        )
                      }}
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="clientLoading">
                    <td colspan="7" class="empty-cell">
                      <i class="fas fa-spinner fa-spin"></i>
                      {{
                        t("clientDetail_loadingOverview", "Loading overview...")
                      }}
                    </td>
                  </tr>
                  <tr v-else>
                    <td>{{ profileOverview.currency || "-" }}</td>
                    <td>
                      {{ formatNullableAmount(profileOverview.walletBalance) }}
                    </td>
                    <td>
                      {{ formatNullableAmount(profileOverview.totalBalance) }}
                    </td>
                    <td>
                      {{ formatNullableAmount(profileOverview.totalEquity) }}
                    </td>
                    <td>
                      {{ formatNullableAmount(profileOverview.totalCredit) }}
                    </td>
                    <td>
                      {{ formatNullableAmount(profileOverview.totalDeposit) }}
                    </td>
                    <td>
                      {{ formatNullableAmount(profileOverview.totalWithdraw) }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="profile-body">
              <div class="profile-section">
                <div class="profile-section-title">
                  {{ t("clientDetail_sectionClientData", "CLIENT DATA") }}
                </div>

                <div class="data-card">
                  <div class="data-card-header">
                    <div class="data-card-title">
                      {{
                        t(
                          "clientDetail_personalInformation",
                          "Personal Information",
                        )
                      }}
                    </div>
                    <button
                      class="btn-save-profile"
                      :class="{
                        active: hasProfileChanges,
                        disabled: !hasProfileChanges,
                      }"
                      type="button"
                      :disabled="!hasProfileChanges || savingProfile"
                      @click="saveProfile"
                    >
                      <i
                        v-if="savingProfile"
                        class="fas fa-spinner fa-spin"
                      ></i>
                      <i v-else class="fas fa-save"></i>
                      {{
                        savingProfile
                          ? t("common_saving", "Saving")
                          : t("common_save", "Save")
                      }}
                    </button>
                  </div>
                  <div class="data-grid">
                    <div class="data-field">
                      <span class="data-label">{{
                        t("firstName", "First Name")
                      }}</span>
                      <div class="data-value">
                        <input
                          class="data-input"
                          type="text"
                          v-model="editableClient.firstName"
                        />
                      </div>
                    </div>
                    <div class="data-field">
                      <span class="data-label">{{
                        t("lastName", "Last Name")
                      }}</span>
                      <div class="data-value">
                        <input
                          class="data-input"
                          type="text"
                          v-model="editableClient.lastName"
                        />
                      </div>
                    </div>
                    <div class="data-field">
                      <span class="data-label">{{
                        t("clientDetail_email", "Email")
                      }}</span>
                      <div class="data-value">
                        <input
                          class="data-input"
                          type="email"
                          v-model="editableClient.email"
                        />
                      </div>
                    </div>
                    <div class="data-field">
                      <span class="data-label">{{
                        t("password", "Password")
                      }}</span>
                      <div class="data-value">
                        <button
                          class="detail-action-button"
                          type="button"
                          :disabled="
                            sendingPasswordReset || !editableClient.email
                          "
                          @click="sendResetEmail"
                        >
                          <i
                            v-if="sendingPasswordReset"
                            class="fas fa-spinner fa-spin"
                          ></i>
                          <i v-else class="fas fa-envelope"></i>
                          {{
                            sendingPasswordReset
                              ? t("common_sending", "Sending")
                              : t(
                                  "clientDetail_sendResetEmail",
                                  "Send Reset Email",
                                )
                          }}
                        </button>
                      </div>
                    </div>
                    <div class="data-field">
                      <span class="data-label">{{
                        t("phoneNumber", "Phone Number")
                      }}</span>
                      <div class="data-value">
                        <input
                          class="data-input"
                          type="tel"
                          v-model="editableClient.phone"
                        />
                      </div>
                    </div>
                    <div class="data-field">
                      <span class="data-label">{{
                        t("country", "Country")
                      }}</span>
                      <div class="data-value">
                        <select
                          class="data-input"
                          v-model="editableClient.country"
                        >
                          <option value="">
                            {{
                              t(
                                "clientDetail_selectCountry",
                                "-- Select Country --",
                              )
                            }}
                          </option>
                          <option
                            v-for="country in countries"
                            :key="country.code"
                            :value="country.code"
                          >
                            {{ country.name }}
                          </option>
                        </select>
                      </div>
                    </div>
                    <div class="data-field">
                      <span class="data-label">{{
                        t("clientDetail_status", "Status")
                      }}</span>
                      <div class="data-value">
                        <select
                          class="data-input"
                          v-model="editableClient.status"
                        >
                          <option value="active">
                            {{ t("status_active", "Active") }}
                          </option>
                          <option value="inactive">
                            {{ t("status_inactive", "Inactive") }}
                          </option>
                          <option value="suspended">
                            {{ t("status_suspended", "Suspended") }}
                          </option>
                          <option value="pending_verification">
                            {{
                              t(
                                "status_pending_verification",
                                "Pending Verification",
                              )
                            }}
                          </option>
                        </select>
                      </div>
                    </div>
                    <div class="data-field">
                      <span class="data-label">{{
                        t("clientDetail_emailVerified", "Email Verified")
                      }}</span>
                      <div class="data-value">
                        <select
                          class="data-input"
                          v-model="editableClient.emailVerified"
                        >
                          <option :value="true">
                            {{ t("common_yes", "Yes") }}
                          </option>
                          <option :value="false">
                            {{ t("common_no", "No") }}
                          </option>
                        </select>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="profile-section">
                <div class="profile-section-heading">
                  <div class="profile-section-title">
                    {{ t("clientDetail_sectionInternalData", "INTERNAL DATA") }}
                  </div>
                  <button
                    class="btn-view-portal"
                    type="button"
                    @click="handleViewClientPortal"
                  >
                    <i class="fas fa-external-link-alt"></i>
                    {{
                      t("clientDetail_viewClientPortal", "VIEW CLIENT PORTAL")
                    }}
                  </button>
                </div>

                <div class="internal-data-grid">
                  <div class="data-card registration-data-card">
                    <div class="data-card-title">
                      {{
                        t(
                          "clientDetail_registrationInformation",
                          "Registration Information",
                        )
                      }}
                    </div>
                    <div class="registration-info-grid">
                      <div class="data-field">
                        <span class="data-label">{{
                          t(
                            "clientDetail_registrationDate",
                            "Registration Date",
                          )
                        }}</span>
                        <span class="data-value">{{
                          clientDetail?.createdAt || "-"
                        }}</span>
                      </div>
                      <div class="data-field">
                        <span class="data-label">{{
                          t("clientDetail_firstLogin", "First Login")
                        }}</span>
                        <span class="data-value">{{
                          clientDetail?.firstLoginAt ||
                          clientDetail?.createdAt ||
                          "-"
                        }}</span>
                      </div>
                      <div class="data-field">
                        <span class="data-label">{{
                          t("clientDetail_lastLogin", "Last Login")
                        }}</span>
                        <span class="data-value">{{
                          clientDetail?.lastLoginAt || "-"
                        }}</span>
                      </div>
                      <div class="data-field">
                        <span class="data-label">{{
                          t("clientDetail_ipAddress", "IP Address")
                        }}</span>
                        <span class="data-value">{{
                          clientDetail?.lastLoginIp ||
                          clientDetail?.registrationIp ||
                          "-"
                        }}</span>
                      </div>
                    </div>
                  </div>

                  <div class="data-card tags-data-card">
                    <div class="data-card-header">
                      <div class="data-card-title">
                        {{ t("clientDetail_tags", "Tags") }}
                      </div>
                      <button
                        class="detail-action-button"
                        type="button"
                        @click="openTagModal"
                      >
                        <i class="fas fa-plus"></i>
                        {{ t("clientDetail_addTag", "Add Tag") }}
                      </button>
                    </div>
                    <div v-if="clientTags.length > 0" class="tag-list">
                      <span
                        v-for="tag in clientTags"
                        :key="tag.id"
                        class="detail-tag"
                        :style="{
                          backgroundColor:
                            tag.color || 'var(--color-surface-muted)',
                          color: getTagTextColor(tag.color),
                        }"
                      >
                        <i class="fas fa-tag"></i>
                        {{ tag.name }}
                        <button
                          class="detail-tag-remove"
                          type="button"
                          :title="`Remove ${tag.name}`"
                          @click="removeClientTag(tag)"
                        >
                          ×
                        </button>
                      </span>
                    </div>
                  </div>
                </div>
              </div>

              <div class="profile-section">
                <div class="profile-section-title">
                  {{ t("clientDetail_sectionKyc", "KYC") }}
                </div>

                <div class="data-card">
                  <div class="data-card-header">
                    <div class="data-card-title">
                      {{ t("clientDetail_kycInformation", "KYC Information") }}
                    </div>
                    <span v-if="kycLoading" class="loading-label">
                      <i class="fas fa-spinner fa-spin"></i>
                      {{ t("common_loading", "Loading") }}
                    </span>
                    <!-- 配了 detailUrl 才显示，跳到第三方后台 -->
                    <a
                      v-if="kycSummary.detailUrl"
                      class="kyc-card-header-link"
                      :href="kycSummary.detailUrl"
                      target="_blank"
                      rel="noopener noreferrer"
                    >
                      <i class="fas fa-external-link-alt"></i>
                      {{
                        t(
                          "kycList_btn_viewInProvider",
                          "View in third-party provider",
                        )
                      }}
                    </a>
                  </div>

                  <div v-if="kycLoading" class="empty-card">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>
                      {{
                        t(
                          "clientDetail_loadingKycAnswers",
                          "Loading KYC answers...",
                        )
                      }}
                    </p>
                  </div>
                  <template v-else-if="clientDetail?.kycSubmissionId">
                    <div
                      class="kyc-summary-grid"
                      :class="{ 'has-third-party': kycSummary.isThirdParty }"
                    >
                      <div class="data-field">
                        <span class="data-label">{{
                          t("clientDetail_kycStatus", "KYC Status")
                        }}</span>
                        <span class="data-value">
                          <span
                            class="kyc-status-pill"
                            :class="kycStatusClass"
                            >{{ kycSummary.status }}</span
                          >
                        </span>
                      </div>
                      <div class="data-field">
                        <span class="data-label">{{
                          t("clientDetail_submissionId", "Submission ID")
                        }}</span>
                        <span class="data-value">{{
                          clientDetail.kycSubmissionId
                        }}</span>
                      </div>
                      <div class="data-field">
                        <span class="data-label">{{
                          t("clientDetail_submittedDate", "Submitted Date")
                        }}</span>
                        <span class="data-value">{{
                          kycSummary.submittedAt
                        }}</span>
                      </div>
                      <div class="data-field">
                        <span class="data-label">{{
                          t("clientDetail_reviewedDate", "Reviewed Date")
                        }}</span>
                        <span class="data-value">{{
                          kycSummary.reviewedAt
                        }}</span>
                      </div>
                      <div class="data-field">
                        <span class="data-label">{{
                          t("clientDetail_reviewer", "Reviewer")
                        }}</span>
                        <span class="data-value">{{
                          kycSummary.reviewer
                        }}</span>
                      </div>
                      <div class="data-field">
                        <span class="data-label">{{
                          t("clientDetail_template", "Template")
                        }}</span>
                        <span class="data-value">{{
                          kycSummary.templateName
                        }}</span>
                      </div>
                      <!-- 仅第三方 KYC 显示，单独一行（撑满 3 列），不参与上面的 nth-last-child 规则 -->
                      <div
                        v-if="kycSummary.isThirdParty"
                        class="data-field kyc-third-party-field"
                      >
                        <span class="data-label">{{
                          t("clientDetail_kycProvider", "KYC Provider")
                        }}</span>
                        <span class="data-value">
                          <span class="kyc-third-party-pill">
                            <i class="fas fa-plug"></i>
                            {{
                              kycSummary.thirdPartyProvider
                                ? kycSummary.thirdPartyProvider.toUpperCase()
                                : t(
                                    "clientDetail_kycProviderExternal",
                                    "3rd Party",
                                  )
                            }}
                          </span>
                        </span>
                      </div>
                    </div>

                    <div
                      v-if="kycRejectionReason"
                      class="data-field kyc-rejection-row"
                    >
                      <span class="data-label">{{
                        t("clientDetail_rejectionReason", "Rejection Reason")
                      }}</span>
                      <span class="data-value">{{ kycRejectionReason }}</span>
                    </div>

                    <div class="kyc-subsection-title">
                      <i class="fas fa-question-circle"></i>
                      {{ t("clientDetail_questions", "Questions") }}
                    </div>
                    <div
                      v-if="kycAnswerCategories.length > 0"
                      class="kyc-answer-grid"
                    >
                      <div
                        v-for="category in kycAnswerCategories"
                        :key="category.id"
                        class="data-card kyc-answer-card"
                      >
                        <div class="data-card-title">
                          <i
                            :class="getKycCategoryIcon(category.categoryName)"
                          ></i>
                          {{ category.categoryName }}
                        </div>
                        <div
                          v-for="answer in category.answers"
                          :key="answer.questionId || answer.questionText"
                          class="data-field"
                        >
                          <span class="data-label">{{
                            answer.questionText
                          }}</span>
                          <span
                            v-if="answer.files.length > 0"
                            class="data-value kyc-answer-value"
                          >
                            <a
                              v-for="(file, fileIdx) in answer.files"
                              :key="fileIdx"
                              :href="getKycFileUrl(file)"
                              target="_blank"
                              class="file-download-link"
                            >
                              {{ getKycFileName(file)
                              }}<span v-if="fileIdx < answer.files.length - 1"
                                >,
                              </span>
                            </a>
                          </span>
                          <span v-else class="data-value kyc-answer-value">{{
                            answer.displayValue
                          }}</span>
                        </div>
                      </div>
                    </div>
                    <div
                      v-else
                      class="empty-card compact-empty kyc-empty-inline"
                    >
                      <i class="fas fa-clipboard-list"></i>
                      <p>
                        {{
                          t(
                            "clientDetail_noKycAnswers",
                            "No KYC answers found.",
                          )
                        }}
                      </p>
                    </div>

                    <div
                      v-if="kycMoreDocumentItems.length > 0"
                      class="kyc-subsection-title"
                    >
                      <i class="fas fa-file-upload"></i>
                      {{ t("clientDetail_moreDocuments", "More Documents") }}
                    </div>
                    <div
                      v-if="kycMoreDocumentItems.length > 0"
                      class="data-card kyc-detail-card kyc-section-card"
                    >
                      <div class="kyc-list-stack">
                        <div
                          v-for="(item, index) in kycMoreDocumentItems"
                          :key="index"
                          class="kyc-list-item"
                        >
                          <div class="kyc-list-item-main">
                            <span>
                              <i
                                :class="
                                  item.itemType === 'question'
                                    ? 'fas fa-question-circle'
                                    : 'fas fa-file-alt'
                                "
                              ></i>
                              {{
                                item.questionText ||
                                item.documentName ||
                                item.name ||
                                item.title ||
                                "-"
                              }}
                            </span>
                            <small v-if="item.questionType">{{
                              item.questionType
                            }}</small>
                          </div>
                          <div class="kyc-list-item-value">
                            <template
                              v-if="getKycResubmitFiles(item).length > 0"
                            >
                              <a
                                v-for="(file, fileIndex) in getKycResubmitFiles(
                                  item,
                                )"
                                :key="fileIndex"
                                :href="getKycFileUrl(file)"
                                target="_blank"
                                class="file-download-link"
                              >
                                {{ getKycFileName(file)
                                }}<span
                                  v-if="
                                    fileIndex <
                                    getKycResubmitFiles(item).length - 1
                                  "
                                  >,
                                </span>
                              </a>
                            </template>
                            <span v-else>{{
                              formatKycResubmitAnswer(item)
                            }}</span>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div class="kyc-secondary-grid">
                      <div class="kyc-full-section">
                        <div class="kyc-subsection-title">
                          <i class="fas fa-signature"></i>
                          {{ t("clientDetail_signatures", "Signatures") }}
                        </div>
                        <div class="data-card kyc-detail-card kyc-section-card">
                          <div
                            v-if="kycSignatures.length > 0"
                            class="kyc-list-stack"
                          >
                            <div
                              v-for="signature in kycSignatures"
                              :key="
                                signature.id || signature.templateDocumentId
                              "
                              class="kyc-list-item"
                            >
                              <div class="kyc-list-item-main">
                                <span>{{
                                  signature.documentTitle || "-"
                                }}</span>
                                <small>{{
                                  tParams(
                                    "clientDetail_signedAt",
                                    "Signed {date}",
                                    { date: signature.createdAt || "-" },
                                  )
                                }}</small>
                              </div>
                              <div class="kyc-list-item-meta">
                                <span
                                  >IP: {{ signature.ipAddress || "-" }}</span
                                >
                              </div>
                            </div>
                          </div>
                          <div
                            v-else
                            class="empty-card compact-empty kyc-empty-inline"
                          >
                            <i class="fas fa-signature"></i>
                            <p>
                              {{
                                t(
                                  "clientDetail_noSignatures",
                                  "No signatures found.",
                                )
                              }}
                            </p>
                          </div>
                        </div>
                      </div>
                    </div>
                  </template>
                  <div v-else class="empty-card compact-empty">
                    <i class="fas fa-id-card"></i>
                    <p>
                      {{
                        t(
                          "clientDetail_noKycSubmission",
                          "No KYC submission found.",
                        )
                      }}
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Trading Accounts tab -->
          <div
            v-else-if="activeTab === 'trading-accounts'"
            class="tab-pane trading-tab-pane"
          >
            <!-- Sub-tabs: trading account list vs trading history (positions and closed orders), so one screen is not overloaded -->
            <div class="detail-section-tabs">
              <button
                v-for="section in tradingSectionTabs"
                :key="section.key"
                type="button"
                class="detail-section-tab"
                :class="{ active: activeTradingSection === section.key }"
                @click="activeTradingSection = section.key"
              >
                {{ section.label }}
              </button>
            </div>

            <ClientTradingHistory
              v-if="activeTradingSection === 'history'"
              :client-id="clientId"
            />

            <div v-else class="table-panel">
              <div class="table-panel-header">
                <h3>
                  {{ t("clientDetail_tabTradingAccounts", "Trading Accounts") }}
                </h3>
                <div class="table-panel-actions">
                  <span v-if="tradingLoading" class="loading-label">
                    <i class="fas fa-spinner fa-spin"></i>
                    {{ t("common_loading", "Loading") }}
                  </span>
                  <button
                    v-if="canCreateTradingAccount"
                    class="btn-view-portal"
                    type="button"
                    :disabled="clientLoading || tradingLoading"
                    @click="openCreateTradingAccountModal"
                  >
                    <i class="fas fa-plus"></i>
                    {{
                      t(
                        "clientDetail_createTradingAccount",
                        "Create Trading Account",
                      )
                    }}
                  </button>
                </div>
              </div>
              <table class="detail-table">
                <thead>
                  <tr>
                    <th>{{ t("clientDetail_thAccountId", "Account ID") }}</th>
                    <th>{{ t("clientDetail_thPlatform", "Platform") }}</th>
                    <th>{{ t("clientDetail_thLogin", "Login") }}</th>
                    <th>{{ t("clientDetail_thName", "Name") }}</th>
                    <th>{{ t("clientDetail_thGroup", "Group") }}</th>
                    <th>{{ t("clientDetail_thCurrencyLabel", "Currency") }}</th>
                    <th>{{ t("clientDetail_thBalance", "Balance") }}</th>
                    <th>{{ t("clientDetail_thCredit", "Credit") }}</th>
                    <th>{{ t("clientDetail_thEquity", "Equity") }}</th>
                    <th>{{ t("clientDetail_thMargin", "Margin") }}</th>
                    <th>{{ t("clientDetail_thFreeMargin", "Free Margin") }}</th>
                    <th>{{ t("clientDetail_thLeverage", "Leverage") }}</th>
                    <th>{{ t("clientDetail_thRebateRule", "Rebate Rule") }}</th>
                    <th>{{ t("clientDetail_thCreated", "Created") }}</th>
                    <th v-if="canManageAnyTradingAction">
                      {{ t("clientDetail_thActions", "Actions") }}
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="tradingLoading">
                    <td
                      :colspan="canManageAnyTradingAction ? 15 : 14"
                      class="empty-cell"
                    >
                      <i class="fas fa-spinner fa-spin"></i>
                      {{
                        t(
                          "clientDetail_loadingTradingAccounts",
                          "Loading trading accounts...",
                        )
                      }}
                    </td>
                  </tr>
                  <tr v-else-if="tradingAccounts.length === 0">
                    <td
                      :colspan="canManageAnyTradingAction ? 15 : 14"
                      class="empty-cell"
                    >
                      {{
                        t(
                          "clientDetail_noTradingAccountsFound",
                          "No trading accounts found",
                        )
                      }}
                    </td>
                  </tr>
                  <template v-else>
                    <tr
                      v-for="account in tradingAccounts"
                      :key="account.accountId || account.accountNumber"
                    >
                      <td>
                        <span class="table-link">{{
                          account.accountId || "-"
                        }}</span>
                      </td>
                      <td>{{ account.platformName || "-" }}</td>
                      <td>{{ account.login || "-" }}</td>
                      <td>{{ account.name || "-" }}</td>
                      <td>{{ account.accountType || "-" }}</td>
                      <td>{{ account.currency || "-" }}</td>
                      <td>{{ formatNullableAmount(account.balance) }}</td>
                      <td>{{ formatNullableAmount(account.credit) }}</td>
                      <td>{{ formatNullableAmount(account.equity) }}</td>
                      <td>{{ formatNullableAmount(account.margin) }}</td>
                      <td>{{ formatNullableAmount(account.freeMargin) }}</td>
                      <td>{{ formatLeverage(account.leverage) }}</td>
                      <td>{{ formatAssignedRebateRule(account) }}</td>
                      <td>{{ account.createdAt || "-" }}</td>
                      <td
                        v-if="canManageAnyTradingAction"
                        class="ta-actions-cell"
                      >
                        <button
                          type="button"
                          class="ta-manage-btn"
                          :class="{ active: manageMenuAccount === account }"
                          @click.stop="toggleManageMenu($event, account)"
                        >
                          <i class="fas fa-cog"></i>
                          <span>{{ t("clientDetail_manage", "Manage") }}</span>
                          <i class="fas fa-chevron-down ta-manage-caret"></i>
                        </button>
                      </td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Documents tab: signed documents and preview/download actions -->
          <div v-else-if="activeTab === 'all-documents'" class="tab-pane">
            <div class="documents-panel">
              <div class="table-panel-header">
                <h3>
                  {{ t("clientDetail_tabAllDocuments", "All Documents") }}
                </h3>
                <span v-if="documentsLoading" class="loading-label">
                  <i class="fas fa-spinner fa-spin"></i>
                  {{ t("common_loading", "Loading") }}
                </span>
              </div>
              <div v-if="documentsLoading" class="empty-card">
                <i class="fas fa-spinner fa-spin"></i>
                <p>
                  {{
                    t("clientDetail_loadingDocuments", "Loading documents...")
                  }}
                </p>
              </div>
              <div v-else-if="documents.length === 0" class="empty-card">
                <i class="fas fa-file-alt"></i>
                <p>
                  {{ t("clientDetail_noDocumentsFound", "No documents found") }}
                </p>
              </div>
              <div v-else class="document-list">
                <div v-for="doc in documents" :key="doc.id" class="doc-card">
                  <div class="doc-card-main">
                    <div class="doc-info">
                      <div class="doc-icon">
                        <i :class="getDocumentIcon(doc.documentType)"></i>
                      </div>
                      <div class="doc-copy">
                        <div class="doc-name">{{ doc.title || "-" }}</div>
                        <div class="doc-meta-list">
                          <span class="doc-meta-item">
                            <i class="fas fa-file-pdf"></i>
                            {{ t("clientDetail_pdf", "PDF") }}
                          </span>
                          <span class="doc-meta-item">
                            <i class="fas fa-tag"></i>
                            {{ formatDocumentSource(doc.source) }}
                          </span>
                          <span class="doc-meta-item">
                            <i class="fas fa-code-branch"></i>
                            v{{ doc.version || "-" }}
                          </span>
                        </div>
                      </div>
                    </div>
                    <div class="doc-signature-summary">
                      <span class="doc-status">
                        <i class="fas fa-check-circle"></i>
                        {{ t("clientDetail_signed", "Signed") }}
                      </span>
                      <span class="doc-date">{{ doc.signedAt || "-" }}</span>
                    </div>
                  </div>
                  <div class="doc-actions">
                    <button
                      class="btn-doc btn-doc-view"
                      type="button"
                      @click="viewDocument(doc)"
                    >
                      <i class="fas fa-eye"></i>
                      {{ t("common_view", "View") }}
                    </button>
                    <button
                      class="btn-doc btn-doc-download"
                      type="button"
                      @click="downloadDocument(doc)"
                    >
                      <i class="fas fa-download"></i>
                      {{ t("common_download", "Download") }}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Funding tab: deposit, withdrawal, and internal transfer history -->
          <div v-else-if="activeTab === 'funding'" class="tab-pane">
            <div class="funding-wrapper">
              <div v-if="canAdjustWalletBalance" class="funding-toolbar">
                <button
                  type="button"
                  class="funding-adjust-btn"
                  @click="openAdjustModal"
                  :disabled="!clientId"
                >
                  <i class="fas fa-coins"></i>
                  {{ t("clientDetail_adjustBalance", "Adjust Balance") }}
                </button>
              </div>
              <div class="funding-summary">
                <div class="funding-stat">
                  <div class="funding-stat-label">
                    {{ t("clientDetail_totalDeposit", "Total Deposit") }}
                  </div>
                  <div class="funding-stat-value positive">
                    {{
                      formatMoney(
                        profileOverview.totalDeposit,
                        profileOverview.currency,
                      )
                    }}
                  </div>
                </div>
                <div class="funding-stat">
                  <div class="funding-stat-label">
                    {{ t("clientDetail_totalWithdrawal", "Total Withdrawal") }}
                  </div>
                  <div class="funding-stat-value negative">
                    {{
                      formatMoney(
                        profileOverview.totalWithdraw,
                        profileOverview.currency,
                      )
                    }}
                  </div>
                </div>
                <div class="funding-stat">
                  <div class="funding-stat-label">
                    {{ t("clientDetail_netFunding", "Net Funding") }}
                  </div>
                  <div
                    class="funding-stat-value"
                    :class="netFunding >= 0 ? 'positive' : 'negative'"
                  >
                    {{ formatMoney(netFunding, profileOverview.currency) }}
                  </div>
                </div>
              </div>
              <div class="funding-transactions-panel">
                <div class="table-panel-header">
                  <h3>{{ t("clientDetail_transactions", "Transactions") }}</h3>
                  <span v-if="activeFundingLoading" class="loading-label">
                    <i class="fas fa-spinner fa-spin"></i>
                    {{ t("common_loading", "Loading") }}
                  </span>
                </div>
                <div class="funding-type-tabs">
                  <button
                    v-for="type in fundingTypes"
                    :key="type.key"
                    type="button"
                    class="funding-type-tab"
                    :class="{ active: activeFundingType === type.key }"
                    @click="selectFundingType(type.key)"
                  >
                    {{ type.label }}
                  </button>
                </div>
                <div class="funding-table-wrapper">
                  <table class="detail-table">
                    <thead>
                      <tr>
                        <th>
                          {{
                            t("clientDetail_thTransactionId", "Transaction ID")
                          }}
                        </th>
                        <th>{{ t("clientDetail_thType", "Type") }}</th>
                        <th>{{ t("clientDetail_thAmount", "Amount") }}</th>
                        <th>{{ t("clientDetail_thGateway", "Gateway") }}</th>
                        <th>{{ t("clientDetail_status", "Status") }}</th>
                        <th>{{ t("clientDetail_thFrom", "From") }}</th>
                        <th>{{ t("clientDetail_thTo", "To") }}</th>
                        <th>{{ t("clientDetail_thDate", "Date") }}</th>
                        <th>{{ t("clientDetail_thAction", "Action") }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-if="activeFundingLoading">
                        <td colspan="9" class="empty-cell">
                          <i class="fas fa-spinner fa-spin"></i>
                          {{
                            t(
                              "clientDetail_loadingTransactions",
                              "Loading transactions...",
                            )
                          }}
                        </td>
                      </tr>
                      <tr v-else-if="activeFundingItems.length === 0">
                        <td colspan="9" class="empty-cell">
                          {{
                            t(
                              "clientDetail_noTransactionsFound",
                              "No transactions found",
                            )
                          }}
                        </td>
                      </tr>
                      <template v-else>
                        <template
                          v-for="transaction in activeFundingItems"
                          :key="getFundingDetailKey(transaction)"
                        >
                          <tr>
                            <td>
                              <span class="table-link">{{
                                transaction.transactionId || "-"
                              }}</span>
                            </td>
                            <td>
                              <span
                                class="funding-type-pill"
                                :class="`type-${transaction.type}`"
                              >
                                {{ formatFundingType(transaction.type) }}
                              </span>
                            </td>
                            <td>
                              {{
                                formatMoney(
                                  transaction.amount,
                                  transaction.currency,
                                )
                              }}
                            </td>
                            <td>{{ transaction.gatewayName || "-" }}</td>
                            <td>
                              <span
                                class="funding-status-pill"
                                :class="`status-${transaction.status}`"
                              >
                                {{ formatFundingStatus(transaction.status) }}
                              </span>
                            </td>
                            <td>
                              {{ formatFundingEndpoint(transaction.from) }}
                            </td>
                            <td>{{ formatFundingEndpoint(transaction.to) }}</td>
                            <td>{{ transaction.date || "-" }}</td>
                            <td>
                              <button
                                type="button"
                                class="funding-detail-toggle"
                                @click="toggleFundingDetail(transaction)"
                              >
                                <i
                                  :class="
                                    expandedFundingDetailKey ===
                                    getFundingDetailKey(transaction)
                                      ? 'fas fa-chevron-up'
                                      : 'fas fa-chevron-down'
                                  "
                                ></i>
                                {{
                                  expandedFundingDetailKey ===
                                  getFundingDetailKey(transaction)
                                    ? t("common_hide", "Hide")
                                    : t("common_detail", "Detail")
                                }}
                              </button>
                            </td>
                          </tr>
                          <tr
                            v-if="
                              expandedFundingDetailKey ===
                              getFundingDetailKey(transaction)
                            "
                            class="funding-detail-row"
                          >
                            <td colspan="9">
                              <div class="funding-detail-card">
                                <div
                                  v-if="
                                    getFundingDetailState(transaction).loading
                                  "
                                  class="funding-detail-state"
                                >
                                  <i class="fas fa-spinner fa-spin"></i>
                                  {{
                                    t(
                                      "clientDetail_loadingDetail",
                                      "Loading detail...",
                                    )
                                  }}
                                </div>
                                <div
                                  v-else-if="
                                    getFundingDetailState(transaction).error
                                  "
                                  class="funding-detail-state error"
                                >
                                  <i class="fas fa-exclamation-circle"></i>
                                  {{ getFundingDetailState(transaction).error }}
                                </div>
                                <div v-else class="funding-detail-grid">
                                  <div
                                    v-for="section in buildFundingDetailSections(
                                      transaction,
                                    )"
                                    :key="section.title"
                                    class="funding-detail-section"
                                    :class="{
                                      'full-width': section.fullWidth,
                                      'single-column': section.singleColumn,
                                    }"
                                  >
                                    <div class="funding-detail-section-title">
                                      <i :class="section.icon"></i>
                                      {{ section.title }}
                                    </div>
                                    <div
                                      v-for="field in section.fields"
                                      :key="field.label"
                                      class="funding-detail-field"
                                      :class="{ 'full-width': field.fullWidth }"
                                    >
                                      <span class="funding-detail-label">{{
                                        field.label
                                      }}</span>
                                      <span
                                        v-if="field.variant === 'type'"
                                        class="funding-detail-value"
                                      >
                                        <span
                                          class="funding-type-pill"
                                          :class="`type-${field.rawValue}`"
                                        >
                                          {{ field.value }}
                                        </span>
                                      </span>
                                      <span
                                        v-else-if="field.variant === 'status'"
                                        class="funding-detail-value"
                                      >
                                        <span
                                          class="funding-status-pill"
                                          :class="`status-${field.rawValue}`"
                                        >
                                          {{ field.value }}
                                        </span>
                                      </span>
                                      <span
                                        v-else
                                        class="funding-detail-value"
                                        :class="{ highlight: field.highlight }"
                                        >{{ field.value }}</span
                                      >
                                    </div>
                                  </div>
                                </div>
                                <!-- PSP Callback 历史：仅 deposit / withdrawal 才查（internal_transfer 不查）；没有 callback 时组件内自动隐藏 -->
                                <PspCallbackSection
                                  v-if="
                                    ['deposit', 'withdrawal'].includes(
                                      transaction.type,
                                    ) &&
                                    (getFundingDetailState(transaction).data
                                      ?.transactionId ||
                                      transaction.transactionId)
                                  "
                                  :order-id="
                                    getFundingDetailState(transaction).data
                                      ?.transactionId ||
                                    transaction.transactionId
                                  "
                                  :transaction-type="transaction.type"
                                  :record-id="
                                    getFundingDetailState(transaction).data
                                      ?.id || transaction.id
                                  "
                                />
                              </div>
                            </td>
                          </tr>
                        </template>
                      </template>
                    </tbody>
                  </table>
                </div>
                <div
                  v-if="activeFundingPagination.total > 0"
                  class="funding-pagination"
                >
                  <span class="funding-pagination-info">{{
                    fundingPaginationInfo
                  }}</span>
                  <div class="funding-pagination-controls">
                    <button
                      type="button"
                      class="funding-pagination-btn"
                      :disabled="
                        activeFundingPagination.page <= 1 ||
                        activeFundingLoading
                      "
                      @click="goToFundingPage(activeFundingPagination.page - 1)"
                    >
                      <i class="fas fa-chevron-left"></i>
                      {{ t("common_previous", "Previous") }}
                    </button>
                    <span class="funding-pagination-page">
                      {{
                        tParams("common_pageOf", "Page {current} of {total}", {
                          current: activeFundingPagination.page,
                          total: activeFundingPagination.total_pages,
                        })
                      }}
                    </span>
                    <button
                      type="button"
                      class="funding-pagination-btn"
                      :disabled="
                        !activeFundingPagination.has_more ||
                        activeFundingLoading
                      "
                      @click="goToFundingPage(activeFundingPagination.page + 1)"
                    >
                      {{ t("common_next", "Next") }}
                      <i class="fas fa-chevron-right"></i>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Withdraw Method tab: dynamic payment template fields and address verification detail -->
          <div v-else-if="activeTab === 'payment-methods'" class="tab-pane">
            <div class="placeholder-panel withdraw-methods-panel">
              <div class="table-panel-header">
                <h3>
                  {{ t("clientDetail_tabWithdrawMethod", "Withdraw Method") }}
                </h3>
                <span v-if="withdrawMethodsLoading" class="loading-label">
                  <i class="fas fa-spinner fa-spin"></i>
                  {{ t("common_loading", "Loading") }}
                </span>
              </div>
              <div
                v-if="
                  withdrawMethodsLoading && withdrawMethodItems.length === 0
                "
                class="empty-card compact-empty"
              >
                <i class="fas fa-spinner fa-spin"></i>
                <p>
                  {{
                    t(
                      "clientDetail_loadingWithdrawMethods",
                      "Loading withdraw methods...",
                    )
                  }}
                </p>
              </div>
              <div
                v-else-if="withdrawMethodItems.length === 0"
                class="empty-card compact-empty"
              >
                <i class="fas fa-credit-card"></i>
                <p>
                  {{
                    t(
                      "clientDetail_noWithdrawMethods",
                      "No withdraw methods loaded yet.",
                    )
                  }}
                </p>
              </div>
              <template v-else>
                <div class="withdraw-method-list">
                  <div
                    v-for="method in withdrawMethodItems"
                    :key="method.id"
                    class="withdraw-method-card"
                  >
                    <div
                      class="withdraw-method-icon"
                      :class="{ crypto: method.isCrypto }"
                    >
                      <i
                        :class="method.gatewayIconClass || 'fas fa-credit-card'"
                      ></i>
                    </div>
                    <div class="withdraw-method-details">
                      <div class="withdraw-method-title">
                        {{
                          method.gatewayName ||
                          t("clientDetail_tabWithdrawMethod", "Withdraw Method")
                        }}
                      </div>
                      <div
                        v-if="method.detailLines.length > 0"
                        class="withdraw-method-values"
                      >
                        <div
                          v-for="line in method.detailLines"
                          :key="line.label"
                          class="withdraw-method-value"
                        >
                          <span class="withdraw-method-value-label"
                            >{{ line.label }}:</span
                          >
                          <span class="withdraw-method-value-text">{{
                            line.value
                          }}</span>
                        </div>
                      </div>
                      <div v-else class="withdraw-method-empty-detail">
                        {{
                          t("clientDetail_noDetailFields", "No detail fields")
                        }}
                      </div>
                      <div class="withdraw-method-date-row">
                        <span>Created {{ method.createdAt || "-" }}</span>
                        <span>Updated {{ method.updatedAt || "-" }}</span>
                      </div>
                    </div>
                    <div class="withdraw-method-actions">
                      <span
                        class="funding-status-pill withdraw-method-status"
                        :class="`status-${method.submissionStatus || ''}`"
                      >
                        {{ formatFundingStatus(method.submissionStatus) }}
                      </span>
                      <button
                        type="button"
                        class="funding-detail-toggle"
                        :disabled="getWithdrawMethodDetailState(method).loading"
                        @click="toggleWithdrawMethodDetail(method)"
                      >
                        <i
                          class="fas"
                          :class="
                            expandedWithdrawMethodDetailKey ===
                            getWithdrawMethodDetailKey(method)
                              ? 'fa-chevron-up'
                              : 'fa-chevron-down'
                          "
                        ></i>
                        {{
                          expandedWithdrawMethodDetailKey ===
                          getWithdrawMethodDetailKey(method)
                            ? t("common_hide", "Hide")
                            : t("common_detail", "Detail")
                        }}
                      </button>
                    </div>
                    <div
                      v-if="
                        expandedWithdrawMethodDetailKey ===
                        getWithdrawMethodDetailKey(method)
                      "
                      class="withdraw-method-detail-card data-card"
                    >
                      <div
                        v-if="getWithdrawMethodDetailState(method).loading"
                        class="empty-card compact-empty"
                      >
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>
                          {{
                            t(
                              "clientDetail_loadingAddressVerificationDetail",
                              "Loading address verification detail...",
                            )
                          }}
                        </p>
                      </div>
                      <div
                        v-else-if="getWithdrawMethodDetailState(method).error"
                        class="empty-card compact-empty"
                      >
                        <i class="fas fa-exclamation-circle"></i>
                        <p>{{ getWithdrawMethodDetailState(method).error }}</p>
                      </div>
                      <template v-else>
                        <div class="data-card-header">
                          <div class="data-card-title">
                            {{
                              t(
                                "clientDetail_addressVerificationDetail",
                                "Address Verification Detail",
                              )
                            }}
                          </div>
                        </div>
                        <div class="kyc-summary-grid">
                          <div
                            v-for="field in buildWithdrawMethodDetailSummary(
                              method,
                            )"
                            :key="field.label"
                            class="data-field"
                          >
                            <span class="data-label">{{ field.label }}</span>
                            <span class="data-value">
                              <span
                                v-if="field.variant === 'status'"
                                class="funding-status-pill"
                                :class="`status-${field.rawValue || ''}`"
                              >
                                {{ field.value }}
                              </span>
                              <span v-else>{{ field.value }}</span>
                            </span>
                          </div>
                        </div>

                        <div
                          v-if="
                            getWithdrawMethodAnswerCategories(method).length > 0
                          "
                          class="kyc-subsection-title"
                        >
                          <i class="fas fa-question-circle"></i>
                          {{ t("clientDetail_questions", "Questions") }}
                        </div>
                        <div
                          v-if="
                            getWithdrawMethodAnswerCategories(method).length > 0
                          "
                          class="kyc-answer-grid"
                        >
                          <div
                            v-for="category in getWithdrawMethodAnswerCategories(
                              method,
                            )"
                            :key="category.id"
                            class="data-card kyc-answer-card"
                          >
                            <div class="data-card-title">
                              <i
                                :class="
                                  getKycCategoryIcon(category.categoryName)
                                "
                              ></i>
                              {{ category.categoryName }}
                            </div>
                            <div
                              v-for="answer in category.answers"
                              :key="answer.questionId || answer.questionText"
                              class="data-field"
                            >
                              <span class="data-label">{{
                                answer.questionText
                              }}</span>
                              <span
                                v-if="answer.files.length > 0"
                                class="data-value kyc-answer-value"
                              >
                                <a
                                  v-for="(file, fileIdx) in answer.files"
                                  :key="fileIdx"
                                  :href="getKycFileUrl(file)"
                                  target="_blank"
                                  class="file-download-link"
                                >
                                  {{ getKycFileName(file)
                                  }}<span
                                    v-if="fileIdx < answer.files.length - 1"
                                    >,
                                  </span>
                                </a>
                              </span>
                              <span
                                v-else
                                class="data-value kyc-answer-value"
                                >{{ answer.displayValue }}</span
                              >
                            </div>
                          </div>
                        </div>

                        <div
                          v-if="getWithdrawMethodSignatures(method).length > 0"
                          class="kyc-subsection-title"
                        >
                          <i class="fas fa-signature"></i>
                          {{ t("clientDetail_signatures", "Signatures") }}
                        </div>
                        <div
                          v-if="getWithdrawMethodSignatures(method).length > 0"
                          class="data-card kyc-detail-card kyc-section-card withdraw-method-full-card"
                        >
                          <div class="kyc-list-stack">
                            <div
                              v-for="signature in getWithdrawMethodSignatures(
                                method,
                              )"
                              :key="
                                signature.id ||
                                signature.templateDocumentId ||
                                signature.documentTitle
                              "
                              class="kyc-list-item"
                            >
                              <div class="kyc-list-item-main">
                                <span>{{
                                  signature.documentTitle ||
                                  signature.documentName ||
                                  "-"
                                }}</span>
                                <small>{{
                                  tParams(
                                    "clientDetail_signedAt",
                                    "Signed {date}",
                                    {
                                      date:
                                        signature.createdAt ||
                                        signature.signedAt ||
                                        "-",
                                    },
                                  )
                                }}</small>
                              </div>
                              <div class="kyc-list-item-meta">
                                <span v-if="signature.ipAddress">{{
                                  tParams("clientDetail_ipValue", "IP: {ip}", {
                                    ip: signature.ipAddress,
                                  })
                                }}</span>
                              </div>
                            </div>
                          </div>
                        </div>
                      </template>
                    </div>
                  </div>
                </div>
                <div
                  v-if="withdrawMethodPagination.total > 0"
                  class="funding-pagination"
                >
                  <span class="funding-pagination-info">{{
                    withdrawMethodPaginationInfo
                  }}</span>
                  <div class="funding-pagination-controls">
                    <button
                      type="button"
                      class="funding-pagination-btn"
                      :disabled="
                        withdrawMethodPagination.page <= 1 ||
                        withdrawMethodsLoading
                      "
                      @click="
                        goToWithdrawMethodPage(
                          withdrawMethodPagination.page - 1,
                        )
                      "
                    >
                      <i class="fas fa-chevron-left"></i>
                      {{ t("common_previous", "Previous") }}
                    </button>
                    <span class="funding-pagination-page">
                      {{
                        tParams("common_pageOf", "Page {current} of {total}", {
                          current: withdrawMethodPagination.page,
                          total: withdrawMethodPagination.total_pages,
                        })
                      }}
                    </span>
                    <button
                      type="button"
                      class="funding-pagination-btn"
                      :disabled="
                        !withdrawMethodPagination.has_more ||
                        withdrawMethodsLoading
                      "
                      @click="
                        goToWithdrawMethodPage(
                          withdrawMethodPagination.page + 1,
                        )
                      "
                    >
                      {{ t("common_next", "Next") }}
                      <i class="fas fa-chevron-right"></i>
                    </button>
                  </div>
                </div>
              </template>
            </div>
          </div>

          <!-- Communications tab: only the direct contact channels used on Client Detail -->
          <div v-else-if="activeTab === 'communications'" class="tab-pane">
            <div class="comm-wrapper">
              <div class="table-panel-header">
                <h3>
                  {{ t("clientDetail_tabCommunications", "Communications") }}
                </h3>
              </div>
              <div class="comm-item">
                <div class="comm-icon email">
                  <i class="fas fa-envelope"></i>
                </div>
                <div class="comm-body">
                  <div class="comm-type">
                    {{ t("clientDetail_email", "Email") }}
                  </div>
                  <a
                    v-if="clientDetail?.email"
                    class="comm-preview comm-link"
                    :href="`mailto:${clientDetail.email}`"
                  >
                    {{ clientDetail.email }}
                  </a>
                  <div v-else class="comm-preview">-</div>
                </div>
              </div>
              <div class="comm-item">
                <div class="comm-icon call">
                  <i class="fas fa-phone-alt"></i>
                </div>
                <div class="comm-body">
                  <div class="comm-type">
                    {{ t("clientDetail_phone", "Phone") }}
                  </div>
                  <a
                    v-if="communicationPhone"
                    class="comm-preview comm-link"
                    :href="`tel:${communicationPhone}`"
                  >
                    {{ communicationPhone }}
                  </a>
                  <div v-else class="comm-preview">-</div>
                </div>
              </div>
            </div>
          </div>

          <!-- IB tab: admin IB detail plus statistics from /admin-clients/ib -->
          <div v-else-if="activeTab === 'ib-referral'" class="tab-pane">
            <div class="ib-wrapper">
              <div v-if="ibPartnerLoading" class="empty-card compact-empty">
                <i class="fas fa-spinner fa-spin"></i>
                <p>
                  {{
                    t(
                      "clientDetail_loadingIbReferralDetail",
                      "Loading IB detail...",
                    )
                  }}
                </p>
              </div>
              <div v-else-if="ibPartnerError" class="empty-card compact-empty">
                <i class="fas fa-exclamation-circle"></i>
                <p>{{ ibPartnerError }}</p>
              </div>
              <template v-else-if="ibPartnerDetail">
                <div
                  v-if="ibPartnerOptions.length > 1"
                  class="ib-partner-switcher"
                >
                  <label
                    class="ib-partner-switcher__label"
                    for="client-detail-ib-select"
                  >
                    {{ t("clientDetail_selectIbPartner", "Select IB") }}
                  </label>
                  <select
                    id="client-detail-ib-select"
                    v-model="selectedIbPartnerId"
                    class="ib-partner-switcher__select"
                    @change="loadIbPartnerDetail(true)"
                  >
                    <option
                      v-for="ib in ibPartnerOptions"
                      :key="ib.id"
                      :value="String(ib.id)"
                    >
                      {{ formatClientIbOption(ib) }}
                    </option>
                  </select>
                </div>
                <!-- 二级 tab：统计/详情/下级客户/佣金分开切换，避免一屏堆太长 -->
                <div class="detail-section-tabs">
                  <button
                    v-for="section in ibSectionTabs"
                    :key="section.key"
                    type="button"
                    class="detail-section-tab"
                    :class="{ active: activeIbSection === section.key }"
                    @click="activeIbSection = section.key"
                  >
                    {{ section.label }}
                  </button>
                </div>
                <IbDetailRow
                  v-if="activeIbSection === 'detail'"
                  :row="ibPartnerDetail"
                />

                <!-- 该 IB 整个下级网络下的客户列表（带跳转到 client detail） -->
                <IbNetworkClients
                  v-if="activeIbSection === 'clients'"
                  :ib-partner-id="currentIbPartnerId"
                />

                <!-- 该 IB 的佣金/rebate 报表（复用客户端 IB Report 同一套数据） -->
                <IbCommissionReport
                  v-if="activeIbSection === 'commission'"
                  :ib-partner-id="currentIbPartnerId"
                />
              </template>
            </div>
          </div>

          <div v-else-if="activeTab === 'ib-referal'" class="tab-pane">
            <div class="ib-referal-wrapper">
              <div class="table-panel-header">
                <h3>{{ t("clientDetail_ibReferalTitle", "IB Referal") }}</h3>
              </div>
              <div v-if="ibUplineLoading" class="empty-card compact-empty">
                <i class="fas fa-spinner fa-spin"></i>
                <p>
                  {{
                    t("clientDetail_loadingIbUpline", "Loading IB upline...")
                  }}
                </p>
              </div>
              <div v-else-if="ibUplineError" class="empty-card compact-empty">
                <i class="fas fa-exclamation-circle"></i>
                <p>{{ ibUplineError }}</p>
              </div>
              <IbDetailRow
                v-else-if="ibReferalRootRow"
                :row="ibReferalRootRow"
                :network-members-override="ibReferalNetworkMembers"
                :network-stats-override="ibReferalNetworkStats"
                network-only
              />
              <div v-else class="empty-card compact-empty">
                <i class="fas fa-project-diagram"></i>
                <p>
                  {{
                    t(
                      "clientDetail_noIbUpline",
                      "No IB upline found for this client.",
                    )
                  }}
                </p>
              </div>
            </div>
          </div>

          <div v-else-if="activeTab === 'sales'" class="tab-pane">
            <div class="sales-wrapper">
              <div
                v-if="salesAssignmentLoading"
                class="empty-card compact-empty"
              >
                <i class="fas fa-spinner fa-spin"></i>
                <p>
                  {{
                    t(
                      "clientDetail_loadingSalesAssignment",
                      "Loading sales assignment...",
                    )
                  }}
                </p>
              </div>
              <div
                v-else-if="salesAssignmentError"
                class="empty-card compact-empty"
              >
                <i class="fas fa-exclamation-circle"></i>
                <p>{{ salesAssignmentError }}</p>
              </div>
              <ClientSalesAssignment
                v-else-if="salesAssignmentDetail"
                :client="salesAssignmentDetail"
                :log-sub-module-key="operationLogSubModule"
                @assigned="handleSalesAssignmentUpdated"
              />
            </div>
          </div>

          <div v-else class="tab-pane">
            <div class="placeholder-panel">
              <div class="table-panel-header">
                <h3>{{ activeTabLabel }}</h3>
                <button class="btn-view-portal" type="button">
                  <i class="fas fa-plus"></i>
                  {{ t("common_add", "Add") }}
                </button>
              </div>
              <div class="empty-card compact-empty">
                <i class="fas fa-layer-group"></i>
                <p>{{ activeTabLabel }} content will be added later.</p>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- Document preview modal -->
    <div v-if="showDocumentModal" class="modal" @click="closeDocumentModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h2>
            <i class="fas fa-file-alt"></i>
            {{
              currentDocument
                ? currentDocument.title
                : t("clientDetail_documentPreview", "Document Preview")
            }}
          </h2>
          <button class="modal-close" type="button" @click="closeDocumentModal">
            &times;
          </button>
        </div>

        <div class="modal-body">
          <div class="document-preview">
            <h3>{{ currentDocument ? currentDocument.title : "" }}</h3>
            <div
              class="document-preview-content font-floor-content"
              v-html="currentDocument ? currentDocument.content : ''"
            ></div>
          </div>

          <div class="document-signature">
            <h4>
              <i class="fas fa-signature"></i>
              {{ t("clientDetail_digitalSignature", "Digital Signature") }}
            </h4>
            <div class="signature-info-row">
              <div class="signature-field">
                <label>{{ t("clientDetail_clientName", "Client Name") }}</label>
                <div class="signature-value">{{ displayClientName }}</div>
              </div>
              <div class="signature-field">
                <label>{{ t("clientDetail_clientId", "Client ID") }}</label>
                <div class="signature-value">{{ clientId }}</div>
              </div>
              <div class="signature-field">
                <label>{{ t("clientDetail_dateSigned", "Date Signed") }}</label>
                <div class="signature-value">
                  {{ currentDocument ? currentDocument.signedAt || "-" : "-" }}
                </div>
              </div>
              <div class="signature-field">
                <label>{{ t("clientDetail_source", "Source") }}</label>
                <div class="signature-value">
                  {{ currentDocument ? currentDocument.source || "-" : "-" }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button
            class="btn-modal btn-modal-secondary"
            type="button"
            @click="closeDocumentModal"
          >
            <i class="fas fa-times"></i>
            {{ t("common_close", "Close") }}
          </button>
          <button
            class="btn-modal btn-modal-primary"
            type="button"
            @click="downloadCurrentDocument"
          >
            <i class="fas fa-download"></i>
            {{ t("common_download", "Download") }}
          </button>
        </div>
      </div>
    </div>

    <!-- Tag assignment modal -->
    <BulkTagModal
      v-model="showTagModal"
      :selected-leads="selectedClientForTag"
      :available-tags="availableClientTags"
      @confirm="handleAddTag"
    />

    <CreateTradingAccountModal
      v-model="showCreateTradingAccountModal"
      :client-id="clientId"
      :log-sub-module-key="operationLogSubModule"
      @created="handleTradingAccountCreated"
    />

    <AdminBalanceAdjustmentModal
      v-if="showAdjustModal"
      :user-id="clientId"
      :currency-code="profileOverview.currency || 'USD'"
      @close="showAdjustModal = false"
      @success="handleAdjustmentSuccess"
    />

    <TradingAccountManageModal
      v-if="showManageModal && manageAccount"
      :trading-account-id="manageAccount.tradingAccountId"
      :mode="manageMode"
      :platform-name="manageAccount.platformName"
      :login="manageAccount.login"
      @close="showManageModal = false"
      @success="handleManageSuccess"
    />

    <!-- 交易账户行内操作下拉：Teleport 到 body，避开表格 overflow 裁剪 -->
    <Teleport to="body">
      <div
        v-if="manageMenuAccount"
        ref="manageMenuRef"
        class="ta-manage-dropdown"
        :style="manageMenuStyle"
      >
        <button
          v-if="canResetTradingPassword"
          type="button"
          class="ta-manage-item"
          @click="onManageMenuSelect('reset-password')"
        >
          <i class="fas fa-key"></i>
          <span>{{ t("clientDetail_resetPassword", "Reset Password") }}</span>
        </button>
        <button
          v-if="canChangeTradingGroup"
          type="button"
          class="ta-manage-item"
          @click="onManageMenuSelect('group')"
        >
          <i class="fas fa-layer-group"></i>
          <span>{{ t("clientDetail_changeGroup", "Change Group") }}</span>
        </button>
        <button
          v-if="canChangeTradingLeverage"
          type="button"
          class="ta-manage-item"
          @click="onManageMenuSelect('leverage')"
        >
          <i class="fas fa-sliders-h"></i>
          <span>{{ t("clientDetail_changeLeverage", "Change Leverage") }}</span>
        </button>
        <button
          v-if="canCreateTradingAccount"
          type="button"
          class="ta-manage-item"
          @click="onAssignRebateRule"
        >
          <i class="fas fa-percentage"></i>
          <span>{{
            t("clientDetail_assignRebateRule", "Assign Rebate Rule")
          }}</span>
        </button>
      </div>
    </Teleport>

    <AssignCommissionRuleModal
      v-if="showAssignRuleModal && assignRuleAccount?.tradingAccountId"
      v-model="showAssignRuleModal"
      :client-id="clientId"
      :trading-account-id="assignRuleAccount.tradingAccountId"
      :login="assignRuleAccount.login || ''"
      :platform-name="assignRuleAccount.platformName || ''"
      :current-rule-id="assignRuleAccount.assignedCommissionRuleId"
      :current-ib-partner-id="assignRuleAccount.assignedCommissionIbPartnerId"
      @saved="handleAssignRuleSaved"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";
import BulkTagModal from "@/components/leads/BulkTagModal.vue";
import IbDetailRow from "@/components/ib/IbDetailRow.vue";
import IbNetworkClients from "@/components/ib/IbNetworkClients.vue";
import IbCommissionReport from "@/components/ib/IbCommissionReport.vue";
import ClientSalesAssignment from "@/components/clients/ClientSalesAssignment.vue";
import ClientTradingHistory from "@/components/client/ClientTradingHistory.vue";
import CreateTradingAccountModal from "@/components/client/CreateTradingAccountModal.vue";
import AssignCommissionRuleModal from "@/components/client/AssignCommissionRuleModal.vue";
import AdminBalanceAdjustmentModal from "@/components/clients/AdminBalanceAdjustmentModal.vue";
import TradingAccountManageModal from "@/components/clients/TradingAccountManageModal.vue";
import PspCallbackSection from "@/components/common/PspCallbackSection.vue";
import { clientService } from "@/services/clientListService";
import { useAuthStore } from "@/stores/auth";
import countryService from "@/services/countryService";
import { kycSubmissionService } from "@/services/kycListService";
import leadsService from "@/services/leadsService";
import depositApi from "@/services/depositApi";
import withdrawalApi from "@/services/withdrawalApi";
import * as internalTransferApi from "@/services/internalTransferApi";
import { addressVerificationSubmissionService } from "@/services/addressVerificationService";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { translateApiErrorMessage } from "@/i18n/adminI18nBridge";
import { subModuleKeyFromDetailSource } from "@/config/operationLogPages";

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const { t, tParams, languageStore } = useAdminI18n();

const clientId = computed(() => {
  const id = route.query.id;
  return id == null || id === "" ? "1782" : String(id);
});

/** 操作日志子模块：由入口页 query.source 决定（leads / clients_list / ib_list） */
const operationLogSubModule = computed(() =>
  subModuleKeyFromDetailSource(route.query.source),
);

const searchId = ref(clientId.value);
const clientDetail = ref(null);
const tradingAccounts = ref([]);
const documents = ref([]);
const kycSubmissionDetail = ref(null);
const kycResubmitDetail = ref(null);
const fundingTransactions = ref({});
const fundingDetails = ref({});
const withdrawMethods = ref([]);
const withdrawMethodDetails = ref({});
const ibPartnerDetail = ref(null);
const selectedIbPartnerId = ref("");
// 当前 IB tab 选中的 ibPartnerId（多 IB 时取下拉选中，否则取客户自身的 ibPartnerId），供网络客户表/佣金报表使用
const currentIbPartnerId = computed(
  () => selectedIbPartnerId.value || clientDetail.value?.ibPartnerId || null,
);
const ibUpline = ref([]);
const salesAssignmentDetail = ref(null);
const editableClient = ref({});
const originalProfileData = ref({});
const countryOptions = ref([]);
const clientLoading = ref(false);
const clientDetailError = ref("");
const tradingLoading = ref(false);
const documentsLoading = ref(false);
const kycLoading = ref(false);
const fundingLoading = ref({});
const fundingDetailLoading = ref({});
const withdrawMethodsLoading = ref(false);
const withdrawMethodDetailLoading = ref({});
const ibPartnerLoading = ref(false);
const ibPartnerError = ref("");
const ibUplineLoading = ref(false);
const ibUplineError = ref("");
const salesAssignmentLoading = ref(false);
const salesAssignmentError = ref("");
const savingProfile = ref(false);
const sendingPasswordReset = ref(false);
const showTagModal = ref(false);
const showDocumentModal = ref(false);
const showCreateTradingAccountModal = ref(false);
const showAdjustModal = ref(false);
const showManageModal = ref(false);
const manageMode = ref("");
const manageAccount = ref(null);
const showAssignRuleModal = ref(false);
const assignRuleAccount = ref(null);
// 行内操作下拉：当前展开的账户 + 悬浮定位
const manageMenuAccount = ref(null);
const manageMenuRef = ref(null);
const manageMenuStyle = ref({});
const currentDocument = ref(null);
const leadTags = ref([]);
const clientLoadedClientId = ref(null);
const tradingLoadedClientId = ref(null);
const documentsLoadedClientId = ref(null);
const kycLoadedSubmissionId = ref(null);
const fundingLoadedKeys = ref({});
const expandedFundingDetailKey = ref(null);
const withdrawMethodsLoadedKeys = ref({});
const expandedWithdrawMethodDetailKey = ref(null);
const ibPartnerLoadedId = ref(null);
const ibUplineLoadedClientId = ref(null);
const salesAssignmentLoadedClientId = ref(null);

const fundingTypes = computed(() => [
  { key: "all", label: t("common_all", "All") },
  { key: "deposit", label: t("clientDetail_typeDeposit", "Deposit") },
  { key: "withdrawal", label: t("clientDetail_typeWithdrawal", "Withdrawal") },
  {
    key: "internal_transfer",
    label: t("clientDetail_typeInternalTransfer", "Internal Transfer"),
  },
  { key: "credit", label: t("clientDetail_typeCredit", "Credit") },
]);
const fundingLimit = 10;
const activeFundingType = ref("all");
const ibSectionTabs = computed(() => [
  { key: "detail", label: t("clientDetail_ibSectionDetail", "IB Detail") },
  { key: "clients", label: t("clientDetail_ibSectionClients", "Clients") },
  {
    key: "commission",
    label: t("clientDetail_ibSectionCommission", "Commission"),
  },
]);
const activeIbSection = ref("detail");
const tradingSectionTabs = computed(() => [
  {
    key: "accounts",
    label: t("clientDetail_tradingSectionAccounts", "Trading Account"),
  },
  {
    key: "history",
    label: t("clientDetail_tradingSectionHistory", "Trading History"),
  },
]);
const activeTradingSection = ref("accounts");
const withdrawMethodLimit = 10;

const canViewClientDetailProfile = computed(() =>
  authStore.hasPermission("page_clientsdetail_profile"),
);
const canViewClientDetailTrading = computed(() =>
  authStore.hasPermission("page_clientsdetail_trading"),
);
const canViewClientDetailDocument = computed(() =>
  authStore.hasPermission("page_clientsdetail_document"),
);
const canViewClientDetailFunding = computed(() =>
  authStore.hasPermission("page_clientsdetail_funding"),
);
const canViewClientDetailPayment = computed(() =>
  authStore.hasPermission("page_clientsdetail_payment"),
);
const canViewClientDetailIb = computed(() =>
  authStore.hasPermission("page_clientsdetail_ib"),
);
const canViewClientDetailIbReferal = computed(() =>
  authStore.hasPermission("page_clientsdetail_ib_referal"),
);
const canViewClientDetailSales = computed(() =>
  authStore.hasPermission("page_clientsdetail_sales"),
);
const canCreateTradingAccount = computed(() =>
  authStore.hasPermission("client_tradding_create"),
);
// "Adjust Wallet Balance"（Admin Pay）独立权限。deposit/withdraw 的批准/拒绝沿用各自原权限，不受影响
const canAdjustWalletBalance = computed(() =>
  authStore.hasPermission("page_clientsdetail_funding_adjust"),
);
const canResetTradingPassword = computed(() =>
  authStore.hasPermission("client_trading_reset_password"),
);
const canChangeTradingGroup = computed(() =>
  authStore.hasPermission("client_trading_change_group"),
);
const canChangeTradingLeverage = computed(() =>
  authStore.hasPermission("client_trading_change_leverage"),
);
const canManageAnyTradingAction = computed(
  () =>
    canResetTradingPassword.value ||
    canChangeTradingGroup.value ||
    canChangeTradingLeverage.value ||
    canCreateTradingAccount.value,
);
const ibPartnerOptions = computed(() =>
  Array.isArray(clientDetail.value?.ibPartners)
    ? clientDetail.value.ibPartners
    : [],
);
// 客户本身是一个 IB（有自己的 ibPartner）
const hasIbPartner = computed(() => {
  if (ibPartnerOptions.value.length > 0) return true;
  const ibPartnerId = clientDetail.value?.ibPartnerId;
  return (
    ibPartnerId !== null && ibPartnerId !== undefined && ibPartnerId !== ""
  );
});
// 客户有上级 IB 才展示 IB Referal 板块。directIbPartnerId 由后端解析：
// 普通客户=直属推荐 IB，客户本身是 IB=它自己 IB 的上级 IB；无上级为 null。
// undefined 表示后端还没返回该字段，先按展示处理避免误隐藏
const hasIbReferal = computed(() => {
  const directIbPartnerId = clientDetail.value?.directIbPartnerId;
  if (directIbPartnerId === undefined) return true;
  return directIbPartnerId !== null;
});

const baseTabs = computed(() =>
  [
    {
      key: "profile",
      label: t("clientDetail_tabProfile", "Profile"),
      visible: canViewClientDetailProfile.value,
    },
    {
      key: "trading-accounts",
      label: t("clientDetail_tabTradingAccounts", "Trading Accounts"),
      visible: canViewClientDetailTrading.value,
    },
    {
      key: "all-documents",
      label: t("clientDetail_tabAllDocuments", "All Documents"),
      visible: canViewClientDetailDocument.value,
    },
    {
      key: "funding",
      label: t("clientDetail_tabFunding", "Funding"),
      visible: canViewClientDetailFunding.value,
    },
    {
      key: "payment-methods",
      label: t("clientDetail_tabWithdrawMethod", "Withdraw Method"),
      visible: canViewClientDetailPayment.value,
    },
    {
      key: "communications",
      label: t("clientDetail_tabCommunications", "Communications"),
      visible: canViewClientDetailProfile.value,
    },
    {
      key: "ib-referal",
      label: t("clientDetail_tabIbReferal", "IB Referal"),
      visible: canViewClientDetailIbReferal.value && hasIbReferal.value,
    },
    {
      key: "sales",
      label: t("clientDetail_tabSales", "Sales"),
      visible: canViewClientDetailSales.value,
    },
  ].filter((tab) => tab.visible),
);
const tabs = computed(() =>
  canViewClientDetailIb.value && hasIbPartner.value
    ? [
        ...baseTabs.value,
        { key: "ib-referral", label: t("clientDetail_tabIbReferral", "IB") },
      ]
    : baseTabs.value,
);

const activeTab = ref("profile");
/** 入口页可用 ?tab= 指定初始 tab（如关系网图跳转到 IB tab）；tab 数据加载完成前不可用，故延迟到 tabs 出现该项时再应用 */
const pendingTab = ref(String(route.query.tab || ""));

watch(
  tabs,
  (availableTabs) => {
    if (
      pendingTab.value &&
      availableTabs.some((tab) => tab.key === pendingTab.value)
    ) {
      activeTab.value = pendingTab.value;
      pendingTab.value = "";
      loadActiveTabData();
      return;
    }
    if (!availableTabs.some((tab) => tab.key === activeTab.value)) {
      activeTab.value = availableTabs[0]?.key || "";
    }
  },
  { immediate: true },
);

const activeTabLabel = computed(
  () => tabs.value.find((tab) => tab.key === activeTab.value)?.label || "",
);
const profileOverview = computed(() => clientDetail.value?.overview || {});
const defaultFundingPagination = () => ({
  total: 0,
  page: 1,
  limit: fundingLimit,
  total_pages: 1,
  has_more: false,
});
const defaultWithdrawMethodPagination = () => ({
  total: 0,
  page: 1,
  per_page: withdrawMethodLimit,
  total_pages: 1,
  has_more: false,
});
const netFunding = computed(() => {
  const deposit = Number(profileOverview.value.totalDeposit || 0);
  const withdraw = Number(profileOverview.value.totalWithdraw || 0);
  return deposit - withdraw;
});
const displayClientName = computed(() => {
  const firstName = clientDetail.value?.firstName || "";
  const lastName = clientDetail.value?.lastName || "";
  const fullName = `${firstName} ${lastName}`.trim();
  return (
    fullName ||
    clientDetail.value?.email ||
    tParams("clientDetail_clientNumber", "Client #{id}", { id: clientId.value })
  );
});
const getIbReferalInitials = (name) => {
  const text = String(name || "").trim();
  if (!text) return "-";
  const parts = text.split(/\s+/);
  if (parts.length >= 2) {
    return `${parts[0][0] || ""}${parts[parts.length - 1][0] || ""}`.toUpperCase();
  }
  return text.slice(0, 2).toUpperCase();
};
const ibReferalRootRow = computed(() => {
  if (!ibUpline.value.length) return null;
  const root = ibUpline.value[ibUpline.value.length - 1];
  return root
    ? {
        ...root,
        ibName: root.ibName || root.companyName || root.ibCode || "-",
        totalClients: ibReferalNetworkStats.value.directClients,
      }
    : null;
});
const buildIbReferalMember = (ib, child) => {
  const name = ib.ibName || ib.companyName || ib.ibCode || "-";
  return {
    id: `ib-upline-${ib.id}`,
    type: "ib",
    // clientUsers.id — node id is not in client-<id> form, so it must be passed
    // explicitly for the client detail link to resolve
    clientUserId: Number(ib.userId) || null,
    name,
    code: [ib.ibCode || "", formatIbUplineTier(ib)].filter(Boolean).join(" • "),
    adminAlias: ib.adminAlias || "",
    initials: getIbReferalInitials(name),
    hasChildren: Boolean(child),
    children: child ? [child] : [],
  };
};
const ibReferalNetworkMembers = computed(() => {
  if (!ibUpline.value.length) return [];

  const clientName = displayClientName.value;
  let child = {
    id: `client-${clientId.value}`,
    type: "client",
    name: clientName,
    code: clientDetail.value?.email || "",
    initials: getIbReferalInitials(clientName),
    hasChildren: false,
    children: [],
  };

  const ibsBelowRoot = ibUpline.value.slice(0, -1);
  ibsBelowRoot.forEach((ib) => {
    child = buildIbReferalMember(ib, child);
  });

  return [child];
});
const ibReferalNetworkStats = computed(() => {
  const ibCount = ibUpline.value.length;
  return {
    totalNetwork: ibCount + (ibCount ? 1 : 0),
    directClients: ibCount ? 1 : 0,
    subIbs: Math.max(ibCount - 1, 0),
    tier2Ibs: ibCount > 1 ? 1 : 0,
    tier3Ibs: Math.max(ibCount - 2, 0),
  };
});
const communicationPhone = computed(() => {
  const countryCode = clientDetail.value?.phoneCountryCode || "";
  const phone = clientDetail.value?.phone || "";
  return `${countryCode} ${phone}`.trim();
});
const profileStatusValue = computed(
  () => clientDetail.value?.kycStatus || clientDetail.value?.status || "",
);
const profileStatusText = computed(() => {
  const status = profileStatusValue.value;
  return status
    ? tParams("clientDetail_profileStatusIs", "IS {status}", {
        status: formatFundingStatus(status).toUpperCase(),
      })
    : t("clientDetail_profileStatusLoaded", "LOADED");
});
const profileStatusClass = computed(
  () =>
    `status-${String(profileStatusValue.value || "loaded")
      .toLowerCase()
      .replace(/_/g, "-")}`,
);
const profileEditableFields = [
  "firstName",
  "lastName",
  "email",
  "phone",
  "country",
  "status",
  "emailVerified",
];
const hasProfileChanges = computed(() =>
  profileEditableFields.some(
    (field) => editableClient.value[field] !== originalProfileData.value[field],
  ),
);
const clientTags = computed(() =>
  Array.isArray(clientDetail.value?.tags) ? clientDetail.value.tags : [],
);
const selectedClientForTag = computed(() => [
  {
    id: Number(clientId.value),
    leadId: Number(clientId.value),
    firstName: clientDetail.value?.firstName || "",
    lastName: clientDetail.value?.lastName || "",
    email: clientDetail.value?.email || "",
  },
]);
const availableClientTags = computed(() => {
  const assignedTagIds = new Set(clientTags.value.map((tag) => String(tag.id)));
  return leadTags.value.filter((tag) => !assignedTagIds.has(String(tag.id)));
});
const activeFundingState = computed(
  () =>
    fundingTransactions.value[activeFundingType.value] || {
      items: [],
      pagination: defaultFundingPagination(),
    },
);
const activeFundingItems = computed(() => activeFundingState.value.items || []);
const activeFundingPagination = computed(
  () => activeFundingState.value.pagination || defaultFundingPagination(),
);
const activeFundingLoading = computed(() =>
  Boolean(fundingLoading.value[activeFundingType.value]),
);
const fundingPaginationInfo = computed(() => {
  const pagination = activeFundingPagination.value;
  const total = Number(pagination.total) || 0;
  if (total === 0) return t("clientDetail_noTransactions", "No transactions");
  const limit = Number(pagination.limit) || fundingLimit;
  const page = Number(pagination.page) || 1;
  const from = (page - 1) * limit + 1;
  const to = Math.min(page * limit, total);
  return tParams(
    "clientDetail_showingTransactions",
    "Showing {from}-{to} of {total} transactions",
    { from, to, total },
  );
});
const withdrawMethodItems = computed(() => withdrawMethods.value.items || []);
const withdrawMethodPagination = computed(
  () => withdrawMethods.value.pagination || defaultWithdrawMethodPagination(),
);
const withdrawMethodPaginationInfo = computed(() => {
  const pagination = withdrawMethodPagination.value;
  const total = Number(pagination.total) || 0;
  if (total === 0)
    return t("clientDetail_noWithdrawMethodsShort", "No withdraw methods");
  const limit = Number(pagination.per_page) || withdrawMethodLimit;
  const page = Number(pagination.page) || 1;
  const from = (page - 1) * limit + 1;
  const to = Math.min(page * limit, total);
  return tParams(
    "clientDetail_showingWithdrawMethods",
    "Showing {from}-{to} of {total} withdraw methods",
    { from, to, total },
  );
});

const fallbackCountries = computed(() => [
  { code: "US", name: t("country_us", "United States") },
  { code: "UK", name: t("country_uk", "United Kingdom") },
  { code: "CA", name: t("country_ca", "Canada") },
  { code: "AU", name: t("country_au", "Australia") },
  { code: "DE", name: t("country_de", "Germany") },
  { code: "FR", name: t("country_fr", "France") },
  { code: "JP", name: t("country_jp", "Japan") },
  { code: "CN", name: t("country_cn", "China") },
  { code: "SG", name: t("country_sg", "Singapore") },
  { code: "HK", name: t("country_hk", "Hong Kong") },
]);
const countries = computed(() =>
  countryOptions.value.length ? countryOptions.value : fallbackCountries.value,
);
const kycAnswerCategories = computed(() =>
  normalizeKycAnswerCategories(kycSubmissionDetail.value?.answers || []),
);
const kycResubmitAnswers = computed(() => {
  const answers = kycResubmitDetail.value?.answers;
  return Array.isArray(answers) ? answers.filter(hasKycResubmitAnswer) : [];
});
const kycSignatures = computed(() => {
  const signatures = kycSubmissionDetail.value?.signatures;
  return Array.isArray(signatures) ? signatures : [];
});
const kycMoreDocumentItems = computed(() => {
  if (kycResubmitAnswers.value.length > 0) return kycResubmitAnswers.value;

  const requestedItems = kycResubmitDetail.value?.requestedItems;
  return Array.isArray(requestedItems) ? requestedItems : [];
});
const kycRejectionReason = computed(() => {
  const status = String(
    kycSubmissionDetail.value?.submissionStatus ||
      clientDetail.value?.kycStatus ||
      "",
  ).toLowerCase();
  if (status !== "rejected") return "";
  return kycSubmissionDetail.value?.rejectionReason || "";
});
const kycSummary = computed(() => ({
  status:
    kycSubmissionDetail.value?.submissionStatus ||
    clientDetail.value?.kycStatus ||
    "-",
  submittedAt:
    kycSubmissionDetail.value?.submittedAt ||
    clientDetail.value?.submittedAt ||
    "-",
  reviewedAt:
    kycSubmissionDetail.value?.reviewedAt ||
    clientDetail.value?.kycVerifiedAt ||
    "-",
  reviewer:
    kycSubmissionDetail.value?.reviewerName ||
    clientDetail.value?.kycVerifiedBy ||
    "-",
  templateName:
    kycSubmissionDetail.value?.templateName ||
    clientDetail.value?.kycTemplateName ||
    "-",
  isThirdParty: !!kycSubmissionDetail.value?.isThirdParty,
  thirdPartyProvider: kycSubmissionDetail.value?.thirdPartyProvider || "",
  detailUrl: kycSubmissionDetail.value?.detailUrl || "",
}));
const kycStatusClass = computed(() =>
  String(kycSummary.value.status || "")
    .toLowerCase()
    .replace(/_/g, "-"),
);

const syncEditableProfile = (client) => {
  const next = {};
  profileEditableFields.forEach((field) => {
    next[field] = client?.[field];
  });
  editableClient.value = { ...next };
  originalProfileData.value = { ...next };
};

const handleClientSearch = () => {
  const nextId = String(searchId.value || "").trim();
  if (!nextId) {
    alert(t("clientDetail_alertClientIdRequired", "Client ID is required."));
    return;
  }

  if (nextId === String(clientId.value)) {
    loadActiveTabData();
    return;
  }

  const query = { id: nextId };
  if (route.query.source) {
    query.source = route.query.source;
  }
  router.push({ name: "client-detail", query });
};

watch(clientId, (value) => {
  searchId.value = value;
  pendingTab.value = String(route.query.tab || "");
  if (activeTab.value === "ib-referral") {
    activeTab.value = "profile";
  }
  clientDetail.value = null;
  clientDetailError.value = "";
  syncEditableProfile(null);
  tradingAccounts.value = [];
  documents.value = [];
  kycSubmissionDetail.value = null;
  kycResubmitDetail.value = null;
  fundingTransactions.value = {};
  fundingDetails.value = {};
  withdrawMethods.value = [];
  withdrawMethodDetails.value = {};
  ibPartnerDetail.value = null;
  selectedIbPartnerId.value = "";
  ibUpline.value = [];
  salesAssignmentDetail.value = null;
  fundingLoading.value = {};
  fundingDetailLoading.value = {};
  withdrawMethodsLoading.value = false;
  withdrawMethodDetailLoading.value = {};
  ibPartnerLoading.value = false;
  ibPartnerError.value = "";
  ibUplineLoading.value = false;
  ibUplineError.value = "";
  salesAssignmentLoading.value = false;
  salesAssignmentError.value = "";
  clientLoadedClientId.value = null;
  tradingLoadedClientId.value = null;
  documentsLoadedClientId.value = null;
  kycLoadedSubmissionId.value = null;
  fundingLoadedKeys.value = {};
  expandedFundingDetailKey.value = null;
  withdrawMethodsLoadedKeys.value = {};
  expandedWithdrawMethodDetailKey.value = null;
  ibPartnerLoadedId.value = null;
  ibUplineLoadedClientId.value = null;
  salesAssignmentLoadedClientId.value = null;
  loadActiveTabData();
});

const selectTab = (tab) => {
  activeTab.value = tab;
  loadActiveTabData();
};

const formatNullableAmount = (value) => {
  if (value === null || value === undefined || value === "") return "-";
  const numberValue = Number(value);
  if (!Number.isFinite(numberValue)) return String(value);
  return numberValue.toLocaleString("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
};

const formatMoney = (value, currency = "USD") => {
  if (value === null || value === undefined || value === "") return "-";
  const numberValue = Number(value);
  if (!Number.isFinite(numberValue)) return String(value);
  return `${currency || "USD"} ${numberValue.toLocaleString("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
};

const formatLeverage = (value) => {
  if (value === null || value === undefined || value === "") return "-";
  return `1:${value}`;
};

const getDocumentIcon = (type) => {
  const iconMap = {
    terms_of_service: "fas fa-file-contract",
    privacy_policy: "fas fa-shield-alt",
    risk_disclosure: "fas fa-exclamation-triangle",
  };
  return iconMap[type] || "fas fa-file-alt";
};

const formatDocumentSource = (source) => {
  if (source === "registration")
    return t("clientDetail_docSourceRegistration", "Registration");
  if (source === "ib") return "IB";
  if (source === "kyc") return "KYC";
  return source || "-";
};

const formatFundingType = (type) => {
  if (type === "deposit") return t("clientDetail_typeDeposit", "Deposit");
  if (type === "withdrawal")
    return t("clientDetail_typeWithdrawal", "Withdrawal");
  if (type === "internal_transfer")
    return t("clientDetail_typeInternalTransfer", "Internal Transfer");
  if (type === "credit") return t("clientDetail_typeCredit", "Credit");
  return type || "-";
};

const formatFundingStatus = (status) => {
  if (!status) return "-";
  const normalized = String(status).toLowerCase();
  const statusMap = {
    pending: t("status_pending", "Pending"),
    processing: t("status_processing", "Processing"),
    completed: t("status_completed", "Completed"),
    approved: t("status_approved", "Approved"),
    rejected: t("status_rejected", "Rejected"),
    failed: t("status_failed", "Failed"),
    cancelled: t("status_cancelled", "Cancelled"),
    expired: t("status_expired", "Expired"),
    under_review: t("status_under_review", "Under Review"),
  };
  return statusMap[normalized] || String(status).replace(/_/g, " ");
};

const formatFundingEndpoint = (endpoint) => {
  if (!endpoint) return "-";
  if (typeof endpoint === "string") return endpoint || "-";

  const account = endpoint.account || "";
  const platformName = endpoint.platformName || "";
  if (account && platformName) return `${account} (${platformName})`;
  return account || platformName || "-";
};

const hasDetailValue = (value) =>
  value !== null && value !== undefined && value !== "";

const unwrapApiData = (response) => response?.data || response || {};

const formatWithdrawMethodDetailLabel = (key) =>
  String(key || "")
    .replace(/[_-]+/g, " ")
    .replace(/([a-z0-9])([A-Z])/g, "$1 $2")
    .replace(/\b\w/g, (char) => char.toUpperCase());

const formatWithdrawMethodDetailValue = (value) => {
  if (!hasDetailValue(value)) return "";
  if (Array.isArray(value)) {
    return value
      .map(formatWithdrawMethodDetailValue)
      .filter(Boolean)
      .join(", ");
  }
  if (typeof value === "object") {
    return Object.entries(value)
      .map(([key, nestedValue]) => {
        const formattedValue = formatWithdrawMethodDetailValue(nestedValue);
        return formattedValue
          ? `${formatWithdrawMethodDetailLabel(key)}: ${formattedValue}`
          : "";
      })
      .filter(Boolean)
      .join(", ");
  }
  return String(value).trim();
};

const normalizeWithdrawMethodDetailLines = (detail) => {
  if (!detail || typeof detail !== "object" || Array.isArray(detail)) return [];

  return Object.entries(detail)
    .map(([key, value]) => ({
      label: formatWithdrawMethodDetailLabel(key),
      value: formatWithdrawMethodDetailValue(value),
    }))
    .filter((line) => line.value);
};

const normalizeWithdrawMethodItem = (item = {}) => {
  const detail =
    item.detail &&
    typeof item.detail === "object" &&
    !Array.isArray(item.detail)
      ? item.detail
      : {};

  return {
    id: item.id,
    templateId: item.templateId,
    templateName: item.templateName,
    gatewaySettingId: item.gatewaySettingId,
    gatewayName: item.gatewayName,
    gatewayIconClass: item.gatewayIconClass,
    isCrypto: Boolean(item.isCrypto),
    paymentMethodId: item.paymentMethodId,
    submissionStatus: item.submissionStatus,
    submittedAt: item.submittedAt,
    createdAt: item.createdAt,
    updatedAt: item.updatedAt,
    detail,
    detailLines: normalizeWithdrawMethodDetailLines(detail),
  };
};

const getWithdrawMethodDetailKey = (method) => String(method?.id || "");

const getWithdrawMethodDetailState = (method) => {
  const key = getWithdrawMethodDetailKey(method);
  return {
    loading: Boolean(withdrawMethodDetailLoading.value[key]),
    error: withdrawMethodDetails.value[key]?.error || "",
    data: withdrawMethodDetails.value[key]?.data || null,
  };
};

const getWithdrawMethodDetailData = (method) =>
  getWithdrawMethodDetailState(method).data || {};

const getWithdrawMethodAnswerCategories = (method) => {
  const detailData = getWithdrawMethodDetailData(method);
  return normalizeKycAnswerCategories(detailData.answers || []);
};

const getWithdrawMethodSignatures = (method) => {
  const detailData = getWithdrawMethodDetailData(method);
  return Array.isArray(detailData.signatures) ? detailData.signatures : [];
};

const buildWithdrawMethodDetailSummary = (method) => {
  const detailData = getWithdrawMethodDetailData(method);
  return [
    {
      label: t("clientDetail_thGateway", "Gateway"),
      value: detailData.gatewayName || method.gatewayName || "-",
    },
    {
      label: t("clientDetail_template", "Template"),
      value: detailData.templateName || method.templateName || "-",
    },
    {
      label: t("clientDetail_status", "Status"),
      value: formatFundingStatus(
        detailData.submissionStatus || method.submissionStatus,
      ),
      rawValue: detailData.submissionStatus || method.submissionStatus,
      variant: "status",
    },
    {
      label: t("clientDetail_submittedAt", "Submitted At"),
      value: detailData.submittedAt || method.submittedAt || "-",
    },
    {
      label: t("clientDetail_createdAt", "Created At"),
      value: detailData.createdAt || method.createdAt || "-",
    },
    {
      label: t("clientDetail_updatedAt", "Updated At"),
      value: detailData.updatedAt || method.updatedAt || "-",
    },
  ];
};

const normalizeWithdrawMethodPagination = (pagination = {}) => ({
  total: Number(pagination.total) || 0,
  per_page: Number(pagination.per_page) || withdrawMethodLimit,
  page: Number(pagination.page) || 1,
  total_pages: Number(pagination.total_pages) || 1,
  has_more: Boolean(pagination.has_more),
});

const formatDetailNumber = (value) => {
  if (!hasDetailValue(value)) return "-";
  const numberValue = Number(value);
  if (!Number.isFinite(numberValue)) return String(value);
  return numberValue.toLocaleString("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
};

const formatDetailAmountWithCode = (value, code = "") => {
  if (!hasDetailValue(value)) return "-";
  const numberValue = Number(value);
  const formatted = Number.isFinite(numberValue)
    ? numberValue.toLocaleString("en-US", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 8,
      })
    : String(value);
  return code ? `${formatted} ${code}` : formatted;
};

const getFundingDetailKey = (transaction) =>
  `${transaction.type}:${transaction.id}`;

const getFundingDetailState = (transaction) => {
  const key = getFundingDetailKey(transaction);
  return {
    loading: Boolean(fundingDetailLoading.value[key]),
    error: fundingDetails.value[key]?.error || "",
    data: fundingDetails.value[key]?.data || null,
    notes: fundingDetails.value[key]?.notes || [],
    statusHistory: fundingDetails.value[key]?.statusHistory || [],
    documentRequest: fundingDetails.value[key]?.documentRequest || null,
  };
};

const setFundingDetailLoading = (key, loading) => {
  fundingDetailLoading.value = {
    ...fundingDetailLoading.value,
    [key]: loading,
  };
};

const createFundingDetailSection = (
  title,
  icon,
  fields,
  fullWidth = false,
) => ({
  title,
  icon,
  fullWidth,
  fields: fields.filter(
    (field) => hasDetailValue(field.value) && field.value !== "-",
  ),
});

const buildFundingQuestionSection = (questions) => {
  if (!Array.isArray(questions) || questions.length === 0) return null;
  return createFundingDetailSection(
    t("clientDetail_supportQuestions", "Support Questions"),
    "fas fa-circle-question",
    questions.map((question) => ({
      label:
        question.name ||
        question.questionText ||
        t("clientDetail_question", "Question"),
      value: question.answer || question.answerText || "-",
    })),
    true,
  );
};

const buildFundingNotesSection = (notes) => {
  if (!Array.isArray(notes) || notes.length === 0) return null;
  return createFundingDetailSection(
    t("clientDetail_notes", "Notes"),
    "fas fa-sticky-note",
    notes.map((note, index) => ({
      label:
        note.createdByName ||
        tParams("clientDetail_noteNumber", "Note {n}", { n: index + 1 }),
      value: note.noteContent || note.content || "-",
    })),
    true,
  );
};

const buildFundingTimelineSection = (history) => {
  if (!Array.isArray(history) || history.length === 0) return null;
  const section = createFundingDetailSection(
    t("clientDetail_timeline", "Timeline"),
    "fas fa-history",
    history.map((item) => ({
      label: formatFundingStatus(item.newStatus || item.status),
      value: [
        item.createdAt,
        item.description,
        item.changedByName
          ? tParams("clientDetail_byUser", "By: {name}", {
              name: item.changedByName,
            })
          : "",
      ]
        .filter(Boolean)
        .join(" | "),
    })),
    true,
  );
  section.singleColumn = true;
  return section;
};

const buildDepositDetailSections = (detailState, transaction) => {
  const detail = detailState.data || {};
  return [
    createFundingDetailSection(
      t("clientDetail_transactionDetails", "Transaction Details"),
      "fas fa-receipt",
      [
        {
          label: t("clientDetail_thTransactionId", "Transaction ID"),
          value: detail.transactionId || transaction.transactionId,
        },
        {
          label: t("clientDetail_thType", "Type"),
          value: formatFundingType(transaction.type),
          rawValue: transaction.type,
          variant: "type",
        },
        {
          label: t("clientDetail_amountUsd", "Amount USD"),
          value: formatDetailNumber(detail.amount ?? transaction.amount),
          highlight: true,
        },
        {
          label: t("clientDetail_platformFee", "Platform Fee"),
          value: formatDetailNumber(detail.platformFee),
        },
        {
          label: t("clientDetail_status", "Status"),
          value: formatFundingStatus(detail.status || transaction.status),
          rawValue: detail.status || transaction.status,
          variant: "status",
        },
        {
          label: t("clientDetail_amountCrypto", "Amount Crypto"),
          value: hasDetailValue(detail.amountCrypto)
            ? `${detail.amountCrypto} ${detail.shortCode || ""}`.trim()
            : "",
        },
        {
          label: t("clientDetail_scale", "Scale"),
          value:
            detail.accountNickname ||
            detail.tradingGroupLabel ||
            detail.tradingGroupName,
        },
        {
          label: t("clientDetail_amountAfterTransfer", "Amount After Transfer"),
          value: formatDetailAmountWithCode(
            detail.platformAmount,
            detail.platformUnit || detail.accountCurrency,
          ),
        },
        {
          label: t("clientDetail_thGateway", "Gateway"),
          value: detail.gatewayName || transaction.gatewayName,
        },
        {
          label: t("clientDetail_thFrom", "From"),
          value: formatFundingEndpoint(transaction.from),
        },
        {
          label: t("clientDetail_thTo", "To"),
          value: formatFundingEndpoint(transaction.to),
        },
        {
          label: t("clientDetail_thDate", "Date"),
          value: detail.createdAt || detail.requestedAt || transaction.date,
        },
        // admin 手工打钱(Admin Pay)填的 reason 写在 deposits.adminNotes，走两列网格跟其它字段对齐，避免独占行破坏分隔线
        { label: t("clientDetail_reason", "Reason"), value: detail.adminNotes },
        {
          label: t("clientDetail_cancelReason", "Cancel Reason"),
          value: detail.failureReason,
          fullWidth: true,
        },
      ],
      true,
    ),
    createFundingDetailSection(
      t("clientDetail_paymentDetails", "Payment Details"),
      "fas fa-wallet",
      [
        {
          label: t("clientDetail_paymentMethod", "Payment Method"),
          value: detail.gatewayName || transaction.gatewayName,
        },
        {
          label: t("clientDetail_network", "Network"),
          value: detail.networkName,
        },
        {
          label: t("clientDetail_exchangeRate", "Exchange Rate"),
          value: hasDetailValue(detail.exchangeRate)
            ? `1 USD = ${detail.exchangeRate} ${detail.currencyCode || ""}`.trim()
            : "",
        },
        {
          label: t("clientDetail_settlementFee", "Settlement Fee"),
          value: formatDetailAmountWithCode(
            detail.platformFee,
            detail.currencyCode,
          ),
        },
        {
          label: t("clientDetail_quotedAmount", "Quoted Amount"),
          value: formatDetailAmountWithCode(
            detail.quotedAmount,
            detail.currencyCode,
          ),
          highlight: true,
        },
      ],
      true,
    ),
    buildFundingQuestionSection(detail.supportQuestions),
    buildFundingNotesSection(detail.notes || detailState.notes),
    buildFundingTimelineSection(
      detail.statusHistory || detailState.statusHistory,
    ),
  ].filter((section) => section && section.fields.length > 0);
};

const buildWithdrawalDetailSections = (detailState, transaction) => {
  const detail = detailState.data || {};
  const documentRequest = detailState.documentRequest;
  return [
    createFundingDetailSection(
      t("clientDetail_transactionDetails", "Transaction Details"),
      "fas fa-receipt",
      [
        {
          label: t("clientDetail_thTransactionId", "Transaction ID"),
          value: detail.transactionId || transaction.transactionId,
        },
        {
          label: t("clientDetail_thType", "Type"),
          value: formatFundingType(transaction.type),
          rawValue: transaction.type,
          variant: "type",
        },
        {
          label: t("clientDetail_amountUsd", "Amount USD"),
          value: formatDetailNumber(detail.amount ?? transaction.amount),
          highlight: true,
        },
        {
          label: t("clientDetail_platformFee", "Platform Fee"),
          value: formatDetailNumber(detail.platformFee),
        },
        {
          label: t("clientDetail_status", "Status"),
          value: formatFundingStatus(detail.status || transaction.status),
          rawValue: detail.status || transaction.status,
          variant: "status",
        },
        {
          label: t("clientDetail_amountCrypto", "Amount Crypto"),
          value: hasDetailValue(detail.amountCrypto)
            ? `${detail.amountCrypto} ${detail.shortCode || ""}`.trim()
            : "",
        },
        {
          label: t("clientDetail_scale", "Scale"),
          value:
            detail.accountNickname ||
            detail.tradingGroupLabel ||
            detail.tradingGroupName,
        },
        {
          label: t("clientDetail_amountDeducted", "Amount Deducted"),
          value: formatDetailAmountWithCode(
            detail.platformAmount,
            detail.platformUnit || detail.accountCurrency,
          ),
        },
        {
          label: t("clientDetail_thGateway", "Gateway"),
          value: detail.gatewayName || transaction.gatewayName,
        },
        {
          label: t("clientDetail_thFrom", "From"),
          value: formatFundingEndpoint(transaction.from),
        },
        {
          label: t("clientDetail_thTo", "To"),
          value: formatFundingEndpoint(transaction.to),
        },
        {
          label: t("clientDetail_thDate", "Date"),
          value: detail.createdAt || detail.requestedAt || transaction.date,
        },
        // admin 手工扣款(Admin Pay)的 reason 写在 withdrawals.adminNotes，走两列网格
        { label: t("clientDetail_reason", "Reason"), value: detail.adminNotes },
        {
          label: t("clientDetail_cancelReason", "Cancel Reason"),
          value:
            detail.failureReason ||
            detail.rejectionReason ||
            detail.rejectionNotes,
          fullWidth: true,
        },
      ],
      true,
    ),
    createFundingDetailSection(
      t("clientDetail_withdrawalDetails", "Withdrawal Details"),
      "fas fa-wallet",
      [
        {
          label: t("clientDetail_paymentMethod", "Payment Method"),
          value: detail.gatewayName || transaction.gatewayName,
        },
        {
          label: t("clientDetail_network", "Network"),
          value: detail.networkName,
        },
        {
          label: t("clientDetail_exchangeRate", "Exchange Rate"),
          value: hasDetailValue(detail.exchangeRate)
            ? `1 USD = ${detail.exchangeRate} ${detail.currencyCode || ""}`.trim()
            : "",
        },
        {
          label: t("clientDetail_settlementFee", "Settlement Fee"),
          value: formatDetailAmountWithCode(
            detail.platformFee,
            detail.currencyCode,
          ),
        },
        {
          label: t("clientDetail_addressVerification", "Address Verification"),
          value: hasDetailValue(detail.hasPreTemplate)
            ? detail.hasPreTemplate
              ? t("common_enable", "Enable")
              : t("common_disable", "Disable")
            : "",
        },
        {
          label: t("clientDetail_quotedAmount", "Quoted Amount"),
          value: formatDetailAmountWithCode(
            detail.quotedAmount,
            detail.currencyCode,
          ),
          highlight: true,
        },
        {
          label: t("clientDetail_legalName", "Legal Name"),
          value: detail.paymentAccountLegalName,
        },
        { label: "BSB", value: detail.paymentAccountBSB },
        {
          label: t("clientDetail_bankAccountNumber", "Bank Account Number"),
          value: detail.paymentAccountNumber,
        },
        {
          label: t("clientDetail_destinationAddress", "Destination Address"),
          value: detail.destinationAddress,
        },
        {
          label: t("clientDetail_withdrawalReason", "Withdrawal Reason"),
          value: detail.withdrawalReason,
        },
        {
          label: t("clientDetail_transactionHash", "Transaction Hash"),
          value: detail.transactionHash,
        },
      ],
      true,
    ),
    buildFundingQuestionSection(detail.supportQuestions),
    documentRequest && documentRequest.requestStatus === "submitted"
      ? createFundingDetailSection(
          t(
            "clientDetail_additionalInformationSubmitted",
            "Additional Information Submitted",
          ),
          "fas fa-file-check",
          [
            {
              label: t("clientDetail_submittedAt", "Submitted At"),
              value: documentRequest.submittedAt,
            },
            {
              label: t("clientDetail_items", "Items"),
              value: Array.isArray(documentRequest.items)
                ? `${documentRequest.items.length}`
                : "",
            },
          ],
          true,
        )
      : null,
    buildFundingTimelineSection(
      detail.statusHistory || detailState.statusHistory,
    ),
  ].filter((section) => section && section.fields.length > 0);
};

const buildInternalTransferDetailSections = (detailState, transaction) => {
  const detail = detailState.data || {};
  return [
    createFundingDetailSection(
      t("clientDetail_transactionDetails", "Transaction Details"),
      "fas fa-receipt",
      [
        {
          label: t("clientDetail_thTransactionId", "Transaction ID"),
          value: detail.transactionId || transaction.transactionId,
        },
        {
          label: t("clientDetail_thType", "Type"),
          value: formatFundingType(transaction.type),
          rawValue: transaction.type,
          variant: "type",
        },
        {
          label: t("clientDetail_sourceType", "Source Type"),
          value: detail.fromType,
        },
        {
          label: t("clientDetail_amountUsd", "Amount USD"),
          value: formatDetailNumber(detail.amount ?? transaction.amount),
          highlight: true,
        },
        {
          label: t("clientDetail_status", "Status"),
          value: formatFundingStatus(detail.status || transaction.status),
          rawValue: detail.status || transaction.status,
          variant: "status",
        },
        {
          label: t("clientDetail_thFrom", "From"),
          value:
            detail.fromAccountNumber || formatFundingEndpoint(transaction.from),
        },
        {
          label: t("clientDetail_thTo", "To"),
          value:
            detail.toAccountNumber || formatFundingEndpoint(transaction.to),
        },
        {
          label: t("clientDetail_thDate", "Date"),
          value: detail.requestedAt || detail.createdAt || transaction.date,
        },
      ],
      true,
    ),
    createFundingDetailSection(
      t("clientDetail_source", "Source"),
      "fas fa-arrow-right-from-bracket",
      [
        {
          label: t("clientDetail_thPlatform", "Platform"),
          value: detail.fromPlatformName,
        },
        {
          label: t("clientDetail_source", "Source"),
          value:
            detail.fromAccountNumber || formatFundingEndpoint(transaction.from),
        },
        {
          label: t("clientDetail_thGroup", "Group"),
          value: detail.fromGroupLabel,
        },
        { label: t("clientDetail_scale", "Scale"), value: detail.fromCurrency },
        {
          label: t("clientDetail_amountDeducted", "Amount Deducted"),
          value: formatDetailAmountWithCode(
            detail.fromPlatformAmount,
            detail.fromCurrency,
          ),
        },
      ],
    ),
    createFundingDetailSection(
      t("clientDetail_target", "Target"),
      "fas fa-arrow-right-to-bracket",
      [
        {
          label: t("clientDetail_thPlatform", "Platform"),
          value: detail.toPlatformName,
        },
        {
          label: t("clientDetail_targetAccount", "Target Account"),
          value:
            detail.toAccountNumber || formatFundingEndpoint(transaction.to),
        },
        {
          label: t("clientDetail_thGroup", "Group"),
          value: detail.toGroupLabel,
        },
        { label: t("clientDetail_scale", "Scale"), value: detail.toCurrency },
        {
          label: t("clientDetail_amountToCredit", "Amount To Credit"),
          value: formatDetailAmountWithCode(
            detail.toPlatformAmount,
            detail.toCurrency,
          ),
        },
      ],
    ),
    buildFundingNotesSection(detail.notes || detailState.notes),
    buildFundingTimelineSection(
      detail.statusHistory || detailState.statusHistory,
    ),
  ].filter((section) => section && section.fields.length > 0);
};

const buildFundingDetailSections = (transaction) => {
  const detailState = getFundingDetailState(transaction);
  if (transaction.type === "deposit")
    return buildDepositDetailSections(detailState, transaction);
  if (transaction.type === "withdrawal")
    return buildWithdrawalDetailSections(detailState, transaction);
  if (transaction.type === "internal_transfer")
    return buildInternalTransferDetailSections(detailState, transaction);
  return [
    createFundingDetailSection(
      t("clientDetail_transactionDetails", "Transaction Details"),
      "fas fa-receipt",
      [
        {
          label: t("clientDetail_thTransactionId", "Transaction ID"),
          value: transaction.transactionId,
        },
        {
          label: t("clientDetail_thType", "Type"),
          value: formatFundingType(transaction.type),
          rawValue: transaction.type,
          variant: "type",
        },
        {
          label: t("clientDetail_thAmount", "Amount"),
          value: formatMoney(transaction.amount, transaction.currency),
          highlight: true,
        },
        {
          label: t("clientDetail_thGateway", "Gateway"),
          value: transaction.gatewayName,
        },
        {
          label: t("clientDetail_status", "Status"),
          value: formatFundingStatus(transaction.status),
          rawValue: transaction.status,
          variant: "status",
        },
        {
          label: t("clientDetail_thFrom", "From"),
          value: formatFundingEndpoint(transaction.from),
        },
        {
          label: t("clientDetail_thTo", "To"),
          value: formatFundingEndpoint(transaction.to),
        },
        { label: t("clientDetail_thDate", "Date"), value: transaction.date },
      ],
      true,
    ),
  ];
};

const normalizeFundingPagination = (pagination = {}) => ({
  total: Number(pagination.total) || 0,
  page: Number(pagination.page) || 1,
  limit: Number(pagination.limit) || fundingLimit,
  total_pages: Math.max(1, Number(pagination.total_pages) || 1),
  has_more: Boolean(pagination.has_more),
});

const normalizeFundingTransaction = (item = {}) => ({
  id: item.id,
  transactionId: item.transactionId,
  type: item.type,
  amount: item.amount,
  currency: item.currency,
  gatewayName: item.gatewayName,
  status: item.status,
  from: item.from,
  to: item.to,
  date: item.date,
});

const setFundingLoading = (type, loading) => {
  fundingLoading.value = {
    ...fundingLoading.value,
    [type]: loading,
  };
};

const selectFundingType = (type) => {
  activeFundingType.value = type;
  loadFundingTransactions(clientId.value, type, 1);
};

const basename = (path) => {
  if (!path) return "";
  const parts = String(path).split("/");
  return parts[parts.length - 1];
};

const parseJsonValue = (value) => {
  if (typeof value !== "string") return value;
  try {
    return JSON.parse(value);
  } catch (error) {
    return value;
  }
};

const normalizeKycFileList = (value) => {
  const parsed = parseJsonValue(value);
  if (Array.isArray(parsed)) return parsed.filter(Boolean);
  if (parsed && typeof parsed === "object") return [parsed];
  if (typeof parsed === "string" && parsed.trim()) return [parsed.trim()];
  return [];
};

const getKycFiles = (answer) => {
  const candidates = [answer.files, answer.uploadedFiles];
  if (answer.questionType === "file_upload") {
    candidates.push(answer.answerValue, answer.answerValues, answer.answer);
  }

  for (const candidate of candidates) {
    const files = normalizeKycFileList(candidate);
    if (files.length > 0) return files;
  }

  return [];
};

const getKycAnswerValue = (answer) => {
  if (
    answer.answer !== undefined &&
    answer.answer !== null &&
    answer.answer !== ""
  )
    return answer.answer;
  if (
    answer.answerValue !== undefined &&
    answer.answerValue !== null &&
    answer.answerValue !== ""
  )
    return answer.answerValue;
  if (
    answer.value !== undefined &&
    answer.value !== null &&
    answer.value !== ""
  )
    return answer.value;
  if (answer.answerText) return answer.answerText;
  if (answer.answerDate) return answer.answerDate;
  if (
    answer.answerNumber !== undefined &&
    answer.answerNumber !== null &&
    answer.answerNumber !== ""
  )
    return answer.answerNumber;
  if (answer.answerValues) return parseJsonValue(answer.answerValues);
  return "";
};

const formatKycAnswerValue = (answer) => {
  if (answer.questionType === "file_upload" && getKycFiles(answer).length > 0)
    return "";

  const value = getKycAnswerValue(answer);
  if (value === null || value === undefined || value === "") return "";
  if (Array.isArray(value)) return value.join(", ");
  if (answer.questionType === "date") {
    try {
      return new Date(value).toLocaleDateString("en-US");
    } catch (error) {
      return String(value);
    }
  }
  return String(value);
};

const normalizeKycAnswerCategories = (answers) => {
  if (!Array.isArray(answers)) return [];

  if (
    answers.some(
      (item) => Array.isArray(item.questions) || Array.isArray(item.answers),
    )
  ) {
    return answers
      .map((category, index) => {
        const rawAnswers = category.questions || category.answers || [];
        const normalizedAnswers = rawAnswers
          .map((question) => {
            const files = getKycFiles(question);
            return {
              questionId: question.questionId,
              questionText: question.questionText || "-",
              questionType: question.questionType || "text",
              displayValue: formatKycAnswerValue(question),
              files,
            };
          })
          .filter((answer) => answer.files.length > 0 || answer.displayValue);

        return {
          id: category.categoryId || index,
          categoryName: category.categoryName || t("common_other", "Other"),
          answers: normalizedAnswers,
        };
      })
      .filter((category) => category.answers.length > 0);
  }

  const grouped = {};
  answers.forEach((answer) => {
    const files = getKycFiles(answer);
    const displayValue = formatKycAnswerValue(answer);
    if (files.length === 0 && !displayValue) return;

    const categoryName = answer.categoryName || t("common_other", "Other");
    if (!grouped[categoryName]) {
      grouped[categoryName] = {
        id: answer.categoryId || categoryName,
        categoryName,
        answers: [],
      };
    }
    grouped[categoryName].answers.push({
      questionId: answer.questionId,
      questionText: answer.questionText || "-",
      questionType: answer.questionType || "text",
      displayValue,
      files,
    });
  });

  return Object.values(grouped);
};

const getKycCategoryIcon = (categoryName) => {
  const iconMap = {
    "Personal Information": "fas fa-user-circle",
    "Financial Information": "fas fa-dollar-sign",
    "Investment Experience": "fas fa-chart-line",
    "Risk Assessment": "fas fa-exclamation-triangle",
    Compliance: "fas fa-shield-alt",
    "Identity Verification": "fas fa-id-card",
  };
  return iconMap[categoryName] || "fas fa-clipboard-list";
};

const getTagTextColor = (color) => {
  if (!color || typeof color !== "string" || !color.startsWith("#"))
    return "var(--color-ink)";
  const hex = color.replace("#", "");
  if (![3, 6].includes(hex.length)) return "var(--color-ink)";
  const normalized =
    hex.length === 3
      ? hex
          .split("")
          .map((char) => char + char)
          .join("")
      : hex;
  const red = parseInt(normalized.slice(0, 2), 16);
  const green = parseInt(normalized.slice(2, 4), 16);
  const blue = parseInt(normalized.slice(4, 6), 16);
  const luminance = (red * 299 + green * 587 + blue * 114) / 1000;
  return luminance > 150 ? "var(--color-ink)" : "#ffffff";
};

const getKycFileUrl = (file) => {
  if (typeof file === "object" && file !== null) {
    return (
      file.downloadUrl ||
      file.s3Url ||
      file.url ||
      file.path ||
      file.filePath ||
      ""
    );
  }
  if (typeof file === "string" && file.startsWith("uploads/"))
    return `/api/${file}`;
  return file ? String(file) : "";
};

const getKycFileName = (file) => {
  if (typeof file === "object" && file !== null) {
    return (
      file.fileName ||
      file.name ||
      basename(
        file.filePath ||
          file.path ||
          file.downloadUrl ||
          file.s3Url ||
          file.url ||
          "",
      )
    );
  }
  return basename(file);
};

const getKycResubmitFiles = (answer) => {
  const candidates = [answer.uploadedFiles, answer.files];
  if (answer.questionType === "file_upload") {
    candidates.push(answer.answerValue, answer.answerValues, answer.answer);
  }

  for (const candidate of candidates) {
    const files = normalizeKycFileList(candidate);
    if (files.length > 0) return files;
  }

  return [];
};

const formatKycResubmitAnswer = (answer) => {
  if (answer.answerText) return answer.answerText;
  if (answer.answerValues) {
    const values = parseJsonValue(answer.answerValues);
    if (Array.isArray(values)) return values.join(", ");
    if (values !== null && values !== undefined && values !== "")
      return String(values);
  }
  return "-";
};

const hasKycResubmitAnswer = (answer) => {
  return (
    getKycResubmitFiles(answer).length > 0 ||
    formatKycResubmitAnswer(answer) !== "-"
  );
};

const escapeHtml = (value) =>
  String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");

const viewDocument = (doc) => {
  currentDocument.value = doc;
  showDocumentModal.value = true;
  document.body.style.overflow = "hidden";
};

const closeDocumentModal = () => {
  showDocumentModal.value = false;
  currentDocument.value = null;
  document.body.style.overflow = "auto";
};

const downloadDocument = (doc) => {
  if (!doc) return;

  const printWindow = window.open("", "_blank", "width=800,height=600");
  if (!printWindow) {
    alert(
      t(
        "clientDetail_alertPopupBlocked",
        "Popup blocked. Please allow popups to download the document.",
      ),
    );
    return;
  }

  const title = escapeHtml(
    doc.title || t("clientDetail_signedDocument", "Signed Document"),
  );
  const signedAt = escapeHtml(doc.signedAt || "-");
  const source = escapeHtml(doc.source || "-");
  const signedDocumentLabel = escapeHtml(
    t("clientDetail_signedDocument", "Signed Document"),
  );
  const digitalSignatureLabel = escapeHtml(
    t("clientDetail_digitalSignature", "Digital Signature"),
  );
  const clientNameLabel = escapeHtml(
    t("clientDetail_clientName", "Client Name"),
  );
  const clientIdLabel = escapeHtml(t("clientDetail_clientId", "Client ID"));
  const dateSignedLabel = escapeHtml(
    t("clientDetail_dateSigned", "Date Signed"),
  );
  const sourceLabel = escapeHtml(t("clientDetail_source", "Source"));
  const generatedCopyText = escapeHtml(
    t(
      "clientDetail_generatedDocumentCopy",
      "This document copy was generated from the admin client detail page.",
    ),
  );
  const printSavePdfLabel = escapeHtml(
    t("clientDetail_printSavePdf", "Print / Save PDF"),
  );
  const closeLabel = escapeHtml(t("common_close", "Close"));
  const generatedAt = new Date().toLocaleDateString("en-US", {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
  const generatedOnText = escapeHtml(
    tParams("clientDetail_generatedOn", "Generated on {date}", {
      date: generatedAt,
    }),
  );

  const htmlContent = `
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset="UTF-8">
      <title>${title} - Client ${escapeHtml(clientId.value)}</title>
      <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
          font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
          color: var(--color-ink);
          line-height: 1.6;
          max-width: 800px;
          margin: 0 auto;
          padding: 40px;
        }
        .header {
          text-align: center;
          margin-bottom: 30px;
          padding-bottom: 20px;
          border-bottom: 3px solid var(--color-brand);
        }
        .header h1 { color: var(--color-brand); font-size: 28px; margin-bottom: 8px; }
        .document-content {
          margin: 30px 0;
          padding: 20px;
          background: var(--color-surface-soft);
          border-radius: var(--radius-md);
        }
        .signature-section {
          margin-top: 40px;
          padding: 25px;
          background: var(--color-warning-soft);
          border: 2px solid #f6b93b;
          border-radius: var(--radius-md);
          page-break-inside: avoid;
        }
        .signature-section h3 { margin-bottom: 18px; font-size: 18px; }
        .signature-grid {
          display: grid;
          grid-template-columns: repeat(2, 1fr);
          gap: 18px;
        }
        .signature-item label {
          display: block;
          font-size: 14px;
          color: var(--color-muted);
          text-transform: uppercase;
          font-weight: 700;
          margin-bottom: 4px;
        }
        .signature-item .value { font-weight: 700; }
        .footer {
          margin-top: 40px;
          padding-top: 20px;
          border-top: 2px solid var(--color-border);
          text-align: center;
          color: var(--color-muted);
          font-size: 14px;
        }
        .no-print { margin-top: 30px; text-align: center; }
        .no-print button {
          padding: 12px 26px;
          border: 0;
          border-radius: var(--radius-md);
          font-weight: 700;
          cursor: pointer;
          margin: 0 5px;
        }
        .print-btn { background: var(--color-brand-solid); color: white; }
        .close-btn { background: var(--color-border); color: var(--color-text); }
        @media print {
          body { padding: 20px; }
          .no-print { display: none; }
        }
      </style>
    </head>
    <body>
      <div class="header">
        <h1>${title}</h1>
        <div>${signedDocumentLabel}</div>
      </div>
      <div class="document-content">
        ${doc.content || ""}
      </div>
      <div class="signature-section">
        <h3>${digitalSignatureLabel}</h3>
        <div class="signature-grid">
          <div class="signature-item">
            <label>${clientNameLabel}</label>
            <div class="value">${escapeHtml(displayClientName.value)}</div>
          </div>
          <div class="signature-item">
            <label>${clientIdLabel}</label>
            <div class="value">${escapeHtml(clientId.value)}</div>
          </div>
          <div class="signature-item">
            <label>${dateSignedLabel}</label>
            <div class="value">${signedAt}</div>
          </div>
          <div class="signature-item">
            <label>${sourceLabel}</label>
            <div class="value">${source}</div>
          </div>
        </div>
      </div>
      <div class="footer">
        <p>${generatedCopyText}</p>
        <p>${generatedOnText}</p>
      </div>
      <div class="no-print">
        <button class="print-btn" onclick="window.print()">${printSavePdfLabel}</button>
        <button class="close-btn" onclick="window.close()">${closeLabel}</button>
      </div>
    </body>
    </html>
  `;

  printWindow.document.write(htmlContent);
  printWindow.document.close();
  printWindow.onload = () => {
    setTimeout(() => {
      printWindow.focus();
    }, 250);
  };
};

const downloadCurrentDocument = () => {
  downloadDocument(currentDocument.value);
};

const handleViewClientPortal = async () => {
  try {
    const res = await leadsService.createPreviewToken(
      clientId.value,
      operationLogSubModule.value,
    );
    const url = res?.data?.previewUrl;
    if (!url) {
      alert(
        t(
          "clientDetail_alertPreviewLinkFailed",
          "Failed to create client preview link.",
        ),
      );
      return;
    }
    window.open(url, "_blank", "noopener,noreferrer");
  } catch (error) {
    const message =
      error?.response?.data?.message ||
      error?.message ||
      t(
        "clientDetail_alertPreviewLinkFailed",
        "Failed to create client preview link.",
      );
    alert(message);
  }
};

const loadCountries = async () => {
  if (countryOptions.value.length) return;

  try {
    const response = await countryService.getCountriesWithoutAll();
    const responseData = response.data || response;
    const list = responseData.data || responseData || [];
    countryOptions.value = Array.isArray(list) ? list : [];
  } catch (error) {
    console.error("Failed to load country options:", error);
    countryOptions.value = [];
  }
};

const loadLeadTags = async () => {
  try {
    const response = await leadsService.getLeadTags();
    const responseData = response.data || response;
    leadTags.value = Array.isArray(responseData)
      ? responseData
      : responseData.data || [];
  } catch (error) {
    console.error("Failed to load tags:", error);
    leadTags.value = [];
  }
};

const reloadClientDetail = async () => {
  clientLoadedClientId.value = null;
  await loadClientDetail(clientId.value);
};

const openTagModal = async () => {
  await loadLeadTags();
  showTagModal.value = true;
};

const openCreateTradingAccountModal = async () => {
  const loaded = await loadClientDetail(clientId.value);
  if (!loaded) return;
  showCreateTradingAccountModal.value = true;
};

const openAdjustModal = () => {
  if (!clientId.value) return;
  showAdjustModal.value = true;
};

// 交易账户管理动作（重置密码 / 改组 / 改杠杆）
const openManage = (mode, account) => {
  if (!account || !account.tradingAccountId) return;
  manageMode.value = mode;
  manageAccount.value = account;
  showManageModal.value = true;
};
// 点操作按钮：定位到按钮右下角展开菜单；再点同一行则收起
const toggleManageMenu = (event, account) => {
  if (manageMenuAccount.value === account) {
    closeManageMenu();
    return;
  }
  const rect = event.currentTarget.getBoundingClientRect();
  manageMenuStyle.value = {
    top: `${rect.bottom + 6}px`,
    left: `${rect.right}px`,
  };
  manageMenuAccount.value = account;
};
const closeManageMenu = () => {
  manageMenuAccount.value = null;
};
const onManageMenuSelect = (mode) => {
  const account = manageMenuAccount.value;
  closeManageMenu();
  openManage(mode, account);
};

const formatAssignedRebateRule = (account) => {
  if (!account?.assignedCommissionRuleId) {
    const approved = ibPartnerOptions.value
      .filter(
        (ib) =>
          String(ib?.status || "") === "approved" &&
          String(ib?.ibCode || "").trim(),
      )
      .slice()
      .sort((a, b) => Number(a.id) - Number(b.id));
    const code = String(approved[0]?.ibCode || "").trim();
    if (code) {
      return tParams("clientDetail_rebateRuleAutoPackage", "Auto ({ibCode})", {
        ibCode: code,
      });
    }
    return t("clientDetail_rebateRuleNone", "Auto");
  }
  const ibCode = String(account.assignedCommissionRuleIbCode || "").trim();
  return ibCode || `#${account.assignedCommissionRuleId}`;
};

const onAssignRebateRule = () => {
  const account = manageMenuAccount.value;
  closeManageMenu();
  if (!account?.tradingAccountId) return;
  assignRuleAccount.value = account;
  showAssignRuleModal.value = true;
};

const handleAssignRuleSaved = async () => {
  if (clientId.value) {
    tradingLoadedClientId.value = null;
    await loadTradingInfo(clientId.value);
  }
};
// 菜单是悬浮定位，页面滚动/点击别处时收起，避免留在原地飘着
const handleManageMenuOutside = (event) => {
  if (!manageMenuAccount.value) return;
  if (manageMenuRef.value && manageMenuRef.value.contains(event.target)) return;
  closeManageMenu();
};
const handleManageMenuScroll = () => {
  if (manageMenuAccount.value) closeManageMenu();
};
const handleManageSuccess = async () => {
  // 成功后重拉账户列表反映最新 group / leverage（重置密码不改这些但重拉无害）
  // loadTradingInfo 有缓存早退，先清掉标记强制重拉
  if (clientId.value) {
    tradingLoadedClientId.value = null;
    await loadTradingInfo(clientId.value);
  }
};

// admin 手工调整成功后，强制刷新 funding：
// 1) 清掉 fundingLoadedKeys 里这个 client 所有 type/page 的缓存，否则 loadFundingTransactions 会命中缓存早退
// 2) 重拉当前 type 第 1 页，让新建的 deposit/withdrawal 立刻出现在表格顶部
// 3) 重拉 clientDetail overview，刷新顶部 Total Deposit / Total Withdrawal / Net Funding 三个统计
const handleAdjustmentSuccess = async () => {
  // 弹窗成功后自己显示提示、由用户点关闭，这里只刷新数据不主动关

  const prefix = `${clientId.value}:`;
  const cleaned = {};
  for (const k of Object.keys(fundingLoadedKeys.value)) {
    if (!k.startsWith(prefix)) cleaned[k] = fundingLoadedKeys.value[k];
  }
  fundingLoadedKeys.value = cleaned;

  await Promise.all([
    loadFundingTransactions(clientId.value, activeFundingType.value, 1),
    reloadClientDetail(),
  ]);
};

const handleTradingAccountCreated = async () => {
  tradingLoadedClientId.value = null;
  await loadTradingInfo(clientId.value);
};

const handleAddTag = async (tagData) => {
  try {
    let tagId = tagData.tagId;

    if (!tagId && tagData.tagName) {
      const response = await leadsService.createLeadTag({
        tagName: tagData.tagName,
        tagColor: "var(--color-warning)",
      });
      const responseData = response.data || response;
      tagId = responseData.id || responseData.data?.id;
    }

    if (!tagId) return;

    await leadsService.bulkAssignTag(
      [Number(clientId.value)],
      tagId,
      operationLogSubModule.value,
    );
    await reloadClientDetail();
    await loadLeadTags();
  } catch (error) {
    const message =
      error?.response?.data?.message ||
      error?.message ||
      t("clientDetail_alertAddTagFailed", "Failed to add tag.");
    alert(message);
  }
};

const removeClientTag = async (tag) => {
  if (
    !confirm(
      tParams(
        "clientDetail_confirmRemoveTag",
        'Remove "{tag}" tag from this client?',
        { tag: tag.name },
      ),
    )
  )
    return;

  try {
    await leadsService.removeTagFromLead(
      clientId.value,
      tag.id,
      operationLogSubModule.value,
    );
    await reloadClientDetail();
  } catch (error) {
    const message =
      error?.response?.data?.message ||
      error?.message ||
      t("clientDetail_alertRemoveTagFailed", "Failed to remove tag.");
    alert(message);
  }
};

const saveProfile = async () => {
  if (!hasProfileChanges.value) return;

  savingProfile.value = true;
  try {
    const updateData = {};
    profileEditableFields.forEach((field) => {
      updateData[field] = editableClient.value[field];
    });
    updateData.logSubModuleKey = operationLogSubModule.value;
    await clientService.update(clientId.value, updateData);
    clientDetail.value = {
      ...clientDetail.value,
      ...updateData,
    };
    originalProfileData.value = { ...updateData };
  } catch (error) {
    const message =
      error?.response?.data?.message ||
      error?.message ||
      t(
        "clientDetail_alertSaveProfileFailed",
        "Failed to save client profile.",
      );
    alert(message);
  } finally {
    savingProfile.value = false;
  }
};

const sendResetEmail = async () => {
  if (!editableClient.value.email) {
    alert(
      t(
        "clientDetail_alertEmailRequiredForReset",
        "Client email is required before sending a reset email.",
      ),
    );
    return;
  }

  if (
    !confirm(
      tParams(
        "clientDetail_confirmSendResetEmail",
        "Send a password reset email to {email}?",
        { email: editableClient.value.email },
      ),
    )
  )
    return;

  sendingPasswordReset.value = true;
  try {
    await clientService.sendPasswordReset(
      clientId.value,
      editableClient.value.email,
      {
        logSubModuleKey: operationLogSubModule.value,
      },
    );
    alert(
      t("clientDetail_alertPasswordResetSent", "Password reset email sent."),
    );
  } catch (error) {
    const message =
      error?.response?.data?.message ||
      error?.message ||
      t(
        "clientDetail_alertSendPasswordResetFailed",
        "Failed to send password reset email.",
      );
    alert(message);
  } finally {
    sendingPasswordReset.value = false;
  }
};

const loadClientDetail = async (id) => {
  if (String(clientLoadedClientId.value) === String(id)) return true;
  clientLoading.value = true;
  clientDetailError.value = "";
  try {
    await loadCountries();
    const response = await clientService.getDetail(id);
    clientDetail.value = response.data || response || null;
    syncSelectedIbPartner();
    syncEditableProfile(clientDetail.value);
    if (activeTab.value === "ib-referral" && !hasIbPartner.value) {
      activeTab.value = tabs.value[0]?.key || "";
    }
    clientLoadedClientId.value = id;
    return true;
  } catch (error) {
    console.error("Failed to load client detail:", error);
    const data = error?.response?.data ?? error;
    const rawMsg = data?.message || error?.message;
    const message =
      translateApiErrorMessage(data?.errorCode, rawMsg) ||
      t("clientDetail_clientNotFound", "Client not found");
    clientDetailError.value = message;
    clientDetail.value = null;
    syncEditableProfile(null);
    return false;
  } finally {
    clientLoading.value = false;
  }
};

const loadIbPartnerDetail = async () => {
  const ibPartnerId =
    selectedIbPartnerId.value || clientDetail.value?.ibPartnerId;
  if (ibPartnerId === null || ibPartnerId === undefined || ibPartnerId === "") {
    ibPartnerDetail.value = null;
    ibPartnerError.value = t(
      "clientDetail_noIbPartner",
      "No IB partner found.",
    );
    return;
  }
  if (String(ibPartnerLoadedId.value) === String(ibPartnerId)) return;

  ibPartnerLoading.value = true;
  ibPartnerError.value = "";
  try {
    const response = await clientService.getIb(ibPartnerId);
    const data = response?.data?.data || response?.data || response || null;
    ibPartnerDetail.value = data;
    ibPartnerLoadedId.value = ibPartnerId;
  } catch (error) {
    console.error("Failed to load IB partner detail:", error);
    ibPartnerDetail.value = null;
    ibPartnerError.value =
      error?.response?.data?.message ||
      error?.message ||
      t("clientDetail_alertLoadIbReferralFailed", "Failed to load IB detail.");
  } finally {
    ibPartnerLoading.value = false;
  }
};

const syncSelectedIbPartner = () => {
  const options = ibPartnerOptions.value;
  if (!options.length) {
    selectedIbPartnerId.value = clientDetail.value?.ibPartnerId
      ? String(clientDetail.value.ibPartnerId)
      : "";
    return;
  }
  const current = selectedIbPartnerId.value;
  if (current && options.some((ib) => String(ib.id) === String(current)))
    return;
  selectedIbPartnerId.value = String(options[0].id);
  if (clientDetail.value) {
    clientDetail.value.ibPartnerId = options[0].id;
  }
};

const formatClientIbOption = (ib) => {
  // 后台展示优先用 adminAlias；为空时回落到 ibCode
  const alias = (ib.adminAlias || "").trim();
  if (alias) {
    return ib.ibCode ? `${alias} (${ib.ibCode})` : alias;
  }
  return ib.ibCode || `IB #${ib.id}`;
};

const loadIbUpline = async (id) => {
  if (String(ibUplineLoadedClientId.value) === String(id)) return;

  ibUplineLoading.value = true;
  ibUplineError.value = "";
  try {
    const response = await clientService.getIbUpline(id);
    const data = response?.data?.data || response?.data || response || {};
    ibUpline.value = Array.isArray(data.upline) ? data.upline : [];
    ibUplineLoadedClientId.value = id;
  } catch (error) {
    console.error("Failed to load IB upline:", error);
    ibUpline.value = [];
    ibUplineError.value =
      error?.response?.data?.message ||
      error?.message ||
      t("clientDetail_alertLoadIbUplineFailed", "Failed to load IB upline.");
  } finally {
    ibUplineLoading.value = false;
  }
};

const loadSalesAssignment = async (id) => {
  if (String(salesAssignmentLoadedClientId.value) === String(id)) return;

  if (!clientDetail.value || String(clientDetail.value.id) !== String(id)) {
    clientDetail.value = { id: Number(id) };
  }
  salesAssignmentLoading.value = true;
  salesAssignmentError.value = "";
  try {
    const response = await clientService.getSalesAssignment(id);
    salesAssignmentDetail.value = response.data || response || null;
    if (salesAssignmentDetail.value) {
      clientDetail.value = {
        ...clientDetail.value,
        ...salesAssignmentDetail.value,
      };
    }
    salesAssignmentLoadedClientId.value = id;
  } catch (error) {
    console.error("Failed to load sales assignment:", error);
    salesAssignmentDetail.value = null;
    salesAssignmentError.value =
      error?.response?.data?.message ||
      error?.message ||
      t(
        "clientDetail_failedLoadSalesAssignment",
        "Failed to load sales assignment.",
      );
  } finally {
    salesAssignmentLoading.value = false;
  }
};

const handleSalesAssignmentUpdated = (assignment) => {
  if (!assignment) return;
  if (salesAssignmentDetail.value) {
    salesAssignmentDetail.value = {
      ...salesAssignmentDetail.value,
      ...assignment,
    };
  }
  if (
    clientDetail.value &&
    String(clientDetail.value.id) === String(clientId.value)
  ) {
    clientDetail.value = { ...clientDetail.value, ...assignment };
  }
};

const formatIbUplineTier = (ib) => {
  if (ib?.tierLevelName) return ib.tierLevelName;
  if (
    ib?.tierLevel !== null &&
    ib?.tierLevel !== undefined &&
    ib?.tierLevel !== ""
  ) {
    return `Tier ${ib.tierLevel}`;
  }
  return "-";
};

const loadTradingInfo = async (id) => {
  if (String(tradingLoadedClientId.value) === String(id)) return;
  tradingLoading.value = true;
  try {
    const response = await clientService.getTradingInfo(id);
    tradingAccounts.value = Array.isArray(response.data) ? response.data : [];
    tradingLoadedClientId.value = id;
  } catch (error) {
    console.error("Failed to load client trading info:", error);
    tradingAccounts.value = [];
  } finally {
    tradingLoading.value = false;
  }
};

const loadDocuments = async (id) => {
  if (String(documentsLoadedClientId.value) === String(id)) return;
  documentsLoading.value = true;
  try {
    const response = await clientService.getDocuments(id);
    documents.value = Array.isArray(response.data) ? response.data : [];
    documentsLoadedClientId.value = id;
  } catch (error) {
    console.error("Failed to load client documents:", error);
    documents.value = [];
  } finally {
    documentsLoading.value = false;
  }
};

const loadWithdrawMethods = async (id, page = 1) => {
  const loadedKey = `${id}:${page}:${withdrawMethodLimit}`;
  if (
    withdrawMethodsLoadedKeys.value[loadedKey] &&
    Number(withdrawMethods.value.pagination?.page) === Number(page)
  )
    return;

  withdrawMethodsLoading.value = true;
  try {
    const response = await clientService.getWithdrawTemplates(id, {
      page,
      per_page: withdrawMethodLimit,
    });
    const data = response.data || response || {};

    withdrawMethods.value = {
      items: Array.isArray(data.payment)
        ? data.payment.map(normalizeWithdrawMethodItem)
        : [],
      pagination: normalizeWithdrawMethodPagination(data.pagination),
    };
    withdrawMethodsLoadedKeys.value = {
      ...withdrawMethodsLoadedKeys.value,
      [loadedKey]: true,
    };
  } catch (error) {
    console.error("Failed to load client withdraw methods:", error);
    withdrawMethods.value = {
      items: [],
      pagination: defaultWithdrawMethodPagination(),
    };
  } finally {
    withdrawMethodsLoading.value = false;
  }
};

const loadWithdrawMethodDetail = async (method) => {
  const key = getWithdrawMethodDetailKey(method);
  if (!key || withdrawMethodDetails.value[key]?.data) return;

  withdrawMethodDetailLoading.value = {
    ...withdrawMethodDetailLoading.value,
    [key]: true,
  };
  try {
    const response = await addressVerificationSubmissionService.getDetail(
      method.id,
    );
    withdrawMethodDetails.value = {
      ...withdrawMethodDetails.value,
      [key]: {
        data: unwrapApiData(response),
      },
    };
  } catch (error) {
    console.error("Failed to load address verification detail:", error);
    withdrawMethodDetails.value = {
      ...withdrawMethodDetails.value,
      [key]: {
        error:
          error?.response?.data?.message ||
          error?.message ||
          t(
            "clientDetail_alertLoadAddressVerificationFailed",
            "Failed to load address verification detail.",
          ),
      },
    };
  } finally {
    withdrawMethodDetailLoading.value = {
      ...withdrawMethodDetailLoading.value,
      [key]: false,
    };
  }
};

const loadFundingTransactions = async (
  id,
  type = activeFundingType.value,
  page = 1,
) => {
  const loadedKey = `${id}:${type}:${page}:${fundingLimit}`;
  if (
    fundingLoadedKeys.value[loadedKey] &&
    Number(fundingTransactions.value[type]?.pagination?.page) === Number(page)
  )
    return;

  setFundingLoading(type, true);
  try {
    const response = await clientService.getTransactions(id, {
      type,
      page,
      limit: fundingLimit,
    });
    const data = response.data || response || {};
    const items = Array.isArray(data.items)
      ? data.items.map(normalizeFundingTransaction)
      : [];

    fundingTransactions.value = {
      ...fundingTransactions.value,
      [type]: {
        items,
        pagination: normalizeFundingPagination(data.pagination),
      },
    };
    fundingLoadedKeys.value = {
      ...fundingLoadedKeys.value,
      [loadedKey]: true,
    };
  } catch (error) {
    console.error("Failed to load client funding transactions:", error);
    fundingTransactions.value = {
      ...fundingTransactions.value,
      [type]: {
        items: [],
        pagination: defaultFundingPagination(),
      },
    };
  } finally {
    setFundingLoading(type, false);
  }
};

const loadFundingDetail = async (transaction) => {
  const key = getFundingDetailKey(transaction);
  if (fundingDetails.value[key]?.data) return;

  setFundingDetailLoading(key, true);
  try {
    let detailResponse;
    let historyResponse;
    let notesResponse;
    let documentRequestResponse;

    if (transaction.type === "deposit") {
      detailResponse = await depositApi.getDeposit(transaction.id);
      const detail = unwrapApiData(detailResponse);
      if (!detail.statusHistory) {
        historyResponse = await depositApi.getDepositHistory(transaction.id);
      }
      if (!detail.notes) {
        notesResponse = await depositApi.getDepositNotes(transaction.id);
      }
      fundingDetails.value = {
        ...fundingDetails.value,
        [key]: {
          data: detail,
          statusHistory: unwrapApiData(historyResponse),
          notes: unwrapApiData(notesResponse),
        },
      };
      return;
    }

    if (transaction.type === "withdrawal") {
      detailResponse = await withdrawalApi.getWithdrawal(transaction.id);
      const detail = unwrapApiData(detailResponse);
      if (!detail.statusHistory) {
        historyResponse = await withdrawalApi.getWithdrawalHistory(
          transaction.id,
        );
      }
      try {
        documentRequestResponse = await withdrawalApi.getDocumentRequest(
          transaction.id,
        );
      } catch (error) {
        documentRequestResponse = null;
      }
      fundingDetails.value = {
        ...fundingDetails.value,
        [key]: {
          data: detail,
          statusHistory: unwrapApiData(historyResponse),
          documentRequest: unwrapApiData(documentRequestResponse),
        },
      };
      return;
    }

    if (transaction.type === "internal_transfer") {
      detailResponse = await internalTransferApi.getInternalTransfer(
        transaction.id,
      );
      const detail = unwrapApiData(detailResponse);
      if (!detail.statusHistory) {
        historyResponse = await internalTransferApi.getInternalTransferHistory(
          transaction.id,
        );
      }
      if (!detail.notes) {
        notesResponse = await internalTransferApi.getInternalTransferNotes(
          transaction.id,
        );
      }
      fundingDetails.value = {
        ...fundingDetails.value,
        [key]: {
          data: detail,
          statusHistory: unwrapApiData(historyResponse),
          notes: unwrapApiData(notesResponse),
        },
      };
      return;
    }

    fundingDetails.value = {
      ...fundingDetails.value,
      [key]: { data: transaction },
    };
  } catch (error) {
    console.error("Failed to load funding transaction detail:", error);
    fundingDetails.value = {
      ...fundingDetails.value,
      [key]: {
        error:
          error?.response?.data?.message ||
          error?.message ||
          t(
            "clientDetail_alertLoadTransactionDetailFailed",
            "Failed to load transaction detail.",
          ),
      },
    };
  } finally {
    setFundingDetailLoading(key, false);
  }
};

const toggleFundingDetail = (transaction) => {
  const key = getFundingDetailKey(transaction);
  if (expandedFundingDetailKey.value === key) {
    expandedFundingDetailKey.value = null;
    return;
  }

  expandedFundingDetailKey.value = key;
  loadFundingDetail(transaction);
};

const goToFundingPage = (page) => {
  const totalPages = Number(activeFundingPagination.value.total_pages) || 1;
  if (page < 1 || page > totalPages) return;
  loadFundingTransactions(clientId.value, activeFundingType.value, page);
};

const goToWithdrawMethodPage = (page) => {
  const totalPages = Number(withdrawMethodPagination.value.total_pages) || 1;
  if (page < 1 || page > totalPages) return;
  loadWithdrawMethods(clientId.value, page);
};

const toggleWithdrawMethodDetail = (method) => {
  const key = getWithdrawMethodDetailKey(method);
  if (expandedWithdrawMethodDetailKey.value === key) {
    expandedWithdrawMethodDetailKey.value = null;
    return;
  }

  expandedWithdrawMethodDetailKey.value = key;
  loadWithdrawMethodDetail(method);
};

const loadKycDetail = async (id) => {
  if (
    !clientDetail.value ||
    String(clientLoadedClientId.value) !== String(id)
  ) {
    await loadClientDetail(id);
  }

  const submissionId = clientDetail.value?.kycSubmissionId;
  if (!submissionId) {
    kycSubmissionDetail.value = null;
    kycResubmitDetail.value = null;
    kycLoadedSubmissionId.value = null;
    return;
  }

  if (String(kycLoadedSubmissionId.value) === String(submissionId)) return;

  kycLoading.value = true;
  try {
    const detailResponse = await kycSubmissionService.getDetail(submissionId);
    kycSubmissionDetail.value = detailResponse.data || detailResponse || null;

    try {
      const resubmitResponse =
        await kycSubmissionService.getResubmitAnswers(submissionId);
      kycResubmitDetail.value = resubmitResponse.data || null;
    } catch (error) {
      kycResubmitDetail.value = null;
    }

    kycLoadedSubmissionId.value = submissionId;
  } catch (error) {
    console.error("Failed to load KYC detail:", error);
    kycSubmissionDetail.value = null;
    kycResubmitDetail.value = null;
  } finally {
    kycLoading.value = false;
  }
};

// 函数声明（非 const）：tabs 的 immediate watcher 在 setup 阶段即可能调用它
function loadActiveTabData() {
  if (activeTab.value === "profile") {
    loadClientDetail(clientId.value).then((loaded) => {
      if (loaded) loadKycDetail(clientId.value);
    });
    return;
  }

  if (activeTab.value === "trading-accounts") {
    loadClientDetail(clientId.value).then((loaded) => {
      if (loaded) loadTradingInfo(clientId.value);
    });
    return;
  }

  if (activeTab.value === "all-documents") {
    loadClientDetail(clientId.value).then((loaded) => {
      if (loaded) loadDocuments(clientId.value);
    });
    return;
  }

  if (activeTab.value === "funding") {
    loadClientDetail(clientId.value).then((loaded) => {
      if (loaded)
        loadFundingTransactions(
          clientId.value,
          activeFundingType.value,
          activeFundingPagination.value.page || 1,
        );
    });
    return;
  }

  if (activeTab.value === "payment-methods") {
    loadClientDetail(clientId.value).then((loaded) => {
      if (loaded)
        loadWithdrawMethods(
          clientId.value,
          withdrawMethodPagination.value.page || 1,
        );
    });
    return;
  }

  if (activeTab.value === "ib-referral") {
    loadClientDetail(clientId.value).then((loaded) => {
      if (loaded) loadIbPartnerDetail();
    });
    return;
  }

  if (activeTab.value === "ib-referal") {
    loadClientDetail(clientId.value).then((loaded) => {
      if (loaded) loadIbUpline(clientId.value);
    });
    return;
  }

  if (activeTab.value === "sales") {
    loadSalesAssignment(clientId.value);
  }
}

onMounted(() => {
  document.title = t("page_clientDetail_title", "Client Detail");
  loadActiveTabData();
  document.addEventListener("click", handleManageMenuOutside);
  window.addEventListener("scroll", handleManageMenuScroll, true);
});

watch(
  () => languageStore.currentLanguage,
  () => {
    document.title = t("page_clientDetail_title", "Client Detail");
  },
);

onUnmounted(() => {
  document.body.style.overflow = "auto";
  document.removeEventListener("click", handleManageMenuOutside);
  window.removeEventListener("scroll", handleManageMenuScroll, true);
});
</script>

<style scoped>
.client-detail-page {
  padding: 40px 20px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  gap: 20px;
}

.page-title h1 {
  font-size: 32px;
  font-weight: 700;
  color: var(--color-ink);
  margin-bottom: 5px;
}

.page-title p {
  color: var(--color-muted);
  font-size: 14px;
}

.page-actions {
  display: flex;
  align-items: center;
}

.btn-back {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: var(--color-text);
  font-size: 14px;
  font-weight: 600;
  text-decoration: none;
  margin-bottom: 18px;
}

.btn-back:hover {
  color: var(--color-brand);
}

.search-input {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: 14px;
  color: var(--color-text);
  background: var(--color-surface);
}

.client-search-bar {
  display: flex;
  align-items: flex-end;
  gap: 12px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 16px;
  margin-bottom: 16px;
}

.search-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.search-field label {
  font-size: 14px;
  font-weight: 700;
  color: var(--color-ink);
}

.search-input {
  width: 240px;
  padding: 8px 12px;
  color: var(--color-ink);
}

.btn-search {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  background: var(--color-brand-solid);
  color: #ffffff;
  border: 0;
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s ease;
}

.btn-search:hover {
  background: var(--color-brand-strong);
}

.client-detail-container {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  overflow: hidden;
}

.client-profile-card {
  background: var(--color-surface);
  border-bottom: 1px solid var(--color-border);
  padding: 16px 20px;
  display: flex;
  align-items: flex-start;
  gap: 20px;
  flex-wrap: wrap;
}

.client-profile-info {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 180px;
}

.client-badges {
  display: flex;
  gap: 6px;
  align-items: center;
  flex-wrap: wrap;
}

.badge-client {
  font-size: 14px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 4px;
}

.badge-client {
  background: var(--color-success-solid);
  color: #ffffff;
}

.client-profile-name {
  font-size: 15px;
  font-weight: 700;
  color: var(--color-ink);
}

.client-profile-id {
  font-size: 14px;
  color: var(--color-muted);
}

.client-detail-state-card {
  min-height: 320px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
  color: var(--color-muted);
  background: var(--color-surface-soft);
  border-top: 1px solid var(--color-border);
  font-size: 14px;
  font-weight: 600;
}

.client-detail-state-card i {
  font-size: 28px;
  color: var(--color-brand);
}

.client-detail-state-card.error {
  color: var(--color-danger);
}

.client-detail-state-card.error i {
  color: var(--color-danger);
}

.tab-nav {
  display: flex;
  background: var(--color-surface);
  border-bottom: 2px solid var(--color-border);
  overflow-x: auto;
  scrollbar-width: none;
}

.tab-nav::-webkit-scrollbar {
  display: none;
}

.tab-nav-item {
  padding: 14px 18px;
  font-size: 14px;
  font-weight: 500;
  color: var(--color-muted);
  cursor: pointer;
  white-space: nowrap;
  border: 0;
  border-bottom: 3px solid transparent;
  background: transparent;
  margin-bottom: -2px;
  transition: all 0.2s ease;
}

.tab-nav-item:hover {
  color: var(--color-brand);
  background: var(--color-surface-soft);
}

.tab-nav-item.active {
  color: var(--color-brand);
  border-bottom-color: var(--color-brand);
  font-weight: 600;
}

.tab-content-wrapper {
  background: var(--color-surface);
  min-height: 320px;
}

.tab-pane {
  padding: 0;
}

.profile-verified-banner {
  background: var(--color-success-solid);
  color: #ffffff;
  text-align: center;
  padding: 10px;
  font-weight: 700;
  font-size: 14px;
}

.profile-verified-banner.status-approved,
.profile-verified-banner.status-verified,
.profile-verified-banner.status-active,
.profile-verified-banner.status-loaded {
  background: var(--color-success-solid);
}

.profile-verified-banner.status-rejected,
.profile-verified-banner.status-suspended,
.profile-verified-banner.status-inactive {
  background: var(--color-danger-solid);
}

.profile-verified-banner.status-draft {
  background: var(--color-muted);
}

.profile-verified-banner.status-pending,
.profile-verified-banner.status-pending-verification,
.profile-verified-banner.status-pending-documents,
.profile-verified-banner.status-submitted,
.profile-verified-banner.status-under-review,
.profile-verified-banner.status-resubmit-required,
.profile-verified-banner.status-incomplete {
  background: #dd6b20;
}

.balance-table-wrapper,
.table-panel {
  overflow-x: auto;
}

.detail-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}

.detail-table th {
  padding: 10px 16px;
  text-align: left;
  font-weight: 700;
  color: var(--color-text);
  background: var(--color-surface-soft);
  border-bottom: 1px solid var(--color-border);
  white-space: nowrap;
}

.detail-table td {
  padding: 12px 16px;
  color: var(--color-ink);
  border-bottom: 1px solid var(--color-surface-muted);
  white-space: nowrap;
}

.empty-cell {
  text-align: center;
  color: var(--color-faint);
}

.table-link {
  color: var(--color-brand);
  font-weight: 700;
}

.profile-body {
  display: flex;
  flex-direction: column;
  border-top: 1px solid var(--color-border);
}

.profile-section {
  padding: 20px;
}

.profile-section + .profile-section {
  border-top: 1px solid var(--color-border);
}

.profile-section-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--color-muted);
  letter-spacing: 0.04em;
  margin-bottom: 12px;
}

.profile-section-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 12px;
}

.profile-section-heading .profile-section-title {
  margin-bottom: 0;
}

.data-card {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  overflow: hidden;
  background: var(--color-surface);
  margin-bottom: 16px;
}

.data-card:last-child {
  margin-bottom: 0;
}

.internal-data-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
  align-items: start;
}

.data-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.data-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 14px;
  background: var(--color-surface-soft);
  border-bottom: 1px solid var(--color-border);
}

.data-card-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--color-ink);
}

.data-card > .data-card-title {
  padding: 12px 14px;
  background: var(--color-surface-soft);
  border-bottom: 1px solid var(--color-border);
}

.data-field {
  display: grid;
  grid-template-columns: 160px minmax(0, 1fr);
  gap: 12px;
  padding: 11px 14px;
  border-bottom: 1px solid var(--color-surface-muted);
  align-items: center;
}

.data-field:last-child {
  border-bottom: 0;
}

.data-label {
  color: var(--color-muted);
  font-size: 14px;
}

.data-value {
  color: var(--color-ink);
  font-size: 14px;
  min-width: 0;
}

.data-value.link {
  color: var(--color-brand);
  font-weight: 700;
}

.data-value select {
  width: 100%;
  padding: 7px 10px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  color: var(--color-ink);
  background: var(--color-surface);
}

.data-input {
  width: 100%;
  padding: 7px 10px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-ink);
  font-size: 14px;
}

.btn-save-profile,
.detail-action-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  border: 0;
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-save-profile {
  padding: 7px 12px;
  background: var(--color-border);
  color: var(--color-muted);
}

.btn-save-profile.active {
  background: var(--color-success-solid);
  color: #ffffff;
}

.btn-save-profile.active:hover {
  background: var(--color-success-solid);
}

.btn-save-profile.disabled,
.btn-save-profile:disabled,
.detail-action-button:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

.detail-action-button {
  padding: 8px 12px;
  background: var(--color-brand-solid);
  color: #ffffff;
}

.detail-action-button:hover:not(:disabled) {
  background: var(--color-brand-strong);
}

.highlight-warning {
  color: var(--color-warning);
  font-weight: 700;
}

.registration-info-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.registration-info-grid .data-field:nth-last-child(-n + 2) {
  border-bottom: 0;
}

.btn-view-portal {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 9px 14px;
  border: 0;
  border-radius: var(--radius-sm);
  background: var(--color-brand-solid);
  color: #ffffff;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
}

.btn-view-portal:hover:not(:disabled) {
  background: var(--color-brand-strong);
}

.btn-view-portal:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

.tag-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  padding: 14px;
}

.detail-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 10px;
  border-radius: 999px;
  font-size: 14px;
  font-weight: 700;
}

.detail-tag-remove {
  width: 16px;
  height: 16px;
  border: 0;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.28);
  color: inherit;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  line-height: 1;
}

.detail-tag-remove:hover {
  background: rgba(255, 255, 255, 0.45);
}

.table-panel,
.documents-panel,
.placeholder-panel,
.comm-wrapper,
.funding-wrapper,
.ib-wrapper,
.ib-referal-wrapper,
.sales-wrapper {
  padding: 20px;
}

.ib-statistics-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 14px;
  margin-bottom: 18px;
}

.detail-section-tabs {
  display: flex;
  gap: 8px;
  margin-bottom: 18px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--color-border);
  overflow-x: auto;
}

/*
 * The IB sub-tabs sit inside .ib-wrapper, which is already padded. The trading sub-tabs hang
 * straight off .tab-pane (padding: 0), so they need their own. Content below carries its own
 * 20px padding, hence no margin-bottom here.
 */
.trading-tab-pane > .detail-section-tabs {
  padding: 20px 20px 12px;
  margin-bottom: 0;
}

.detail-section-tab {
  flex: 0 0 auto;
  padding: 7px 14px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
}

.detail-section-tab:hover,
.detail-section-tab.active {
  border-color: var(--color-brand);
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.ib-partner-switcher {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 18px;
}

.ib-partner-switcher__label {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
  white-space: nowrap;
}

.ib-partner-switcher__select {
  min-width: 280px;
  max-width: 520px;
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-md);
  padding: 9px 12px;
  font-size: 14px;
  color: var(--color-ink);
  background: var(--color-surface);
}

.ib-partner-switcher__select:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.12);
}

.ib-stat-card {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  padding: 18px 20px;
  box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
}

.ib-stat-card-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 22px;
}

.ib-stat-title {
  color: var(--color-muted);
  font-size: 14px;
  line-height: 1.4;
}

.ib-stat-icon {
  width: 38px;
  height: 38px;
  border-radius: var(--radius-md);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: #ffffff;
  font-size: 15px;
  flex: 0 0 auto;
}

.ib-stat-card.total-commission .ib-stat-icon {
  background: var(--color-success-solid);
}

.ib-stat-card.paid-commission .ib-stat-icon {
  background: var(--color-info-solid);
}

.ib-stat-card.pending-payout .ib-stat-icon {
  background: var(--color-warning-solid);
}

.ib-stat-card.total-referrals .ib-stat-icon {
  background: var(--color-purple-solid);
}

.ib-stat-value {
  color: var(--color-ink);
  font-size: 28px;
  font-weight: 800;
  line-height: 1.2;
}

.comm-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 14px 16px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  margin-bottom: 10px;
  background: var(--color-surface);
}

.comm-icon {
  width: 38px;
  height: 38px;
  border-radius: var(--radius-md);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  flex: 0 0 auto;
}

.comm-icon.email {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.comm-icon.call {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.comm-body {
  flex: 1;
  min-width: 0;
}

.comm-type {
  font-weight: 600;
  font-size: 14px;
  color: var(--color-ink);
}

.comm-preview {
  font-size: 14px;
  color: var(--color-muted);
  margin-top: 3px;
  overflow-wrap: anywhere;
}

.comm-link {
  display: inline-block;
  text-decoration: none;
}

.comm-link:hover {
  color: var(--color-info);
}

.kyc-summary-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.kyc-answer-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.kyc-answer-grid {
  border-top: 0;
}

.kyc-summary-grid .data-field:nth-last-child(-n + 3) {
  border-bottom: 0;
}

/* 出现第三方那一行时：
   - 把上面"去掉最后 3 个 border"的规则恢复（不然中间那行右边两格少线）
   - 单独让第三方那一行撑满整宽，且自己不再 border-bottom */
.kyc-summary-grid.has-third-party .data-field:nth-last-child(-n + 3) {
  border-bottom: 1px solid var(--color-surface-muted);
}
.kyc-summary-grid.has-third-party .data-field.kyc-third-party-field {
  grid-column: 1 / -1;
  border-bottom: 0;
}

.kyc-answer-card {
  border: 0;
  border-right: 1px solid var(--color-border);
  border-bottom: 1px solid var(--color-border);
  border-radius: 0;
  margin-bottom: 0;
}

.kyc-answer-card:nth-child(2n) {
  border-right: 0;
}

.kyc-answer-card:last-child,
.kyc-answer-card:nth-last-child(2):nth-child(odd) {
  border-bottom: 0;
}

.kyc-answer-card .data-card-title {
  display: flex;
  align-items: center;
  gap: 8px;
}

.kyc-answer-value {
  text-align: right;
}

.kyc-status-pill {
  display: inline-flex;
  align-items: center;
  padding: 3px 9px;
  border-radius: 999px;
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-size: 14px;
  font-weight: 700;
  text-transform: capitalize;
}

/* 第三方 KYC provider 标识 */
.kyc-third-party-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 9px;
  border-radius: 999px;
  background: var(--color-brand-soft);
  color: var(--color-brand);
  font-size: 14px;
  font-weight: 700;
  letter-spacing: 0.3px;
}

.kyc-third-party-pill i {
  font-size: 14px;
}

/* KYC 卡片 header 上的"跳第三方后台"按钮 */
.kyc-card-header-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 11px;
  border-radius: var(--radius-sm);
  background: var(--color-brand-soft);
  color: var(--color-brand);
  font-size: 14px;
  font-weight: 600;
  text-decoration: none;
  transition: background 0.15s ease;
}

.kyc-card-header-link:hover {
  background: #5b21b6;
  color: white;
}

.kyc-status-pill.approved {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.kyc-status-pill.rejected {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.kyc-status-pill.submitted,
.kyc-status-pill.under-review,
.kyc-status-pill.pending {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.kyc-empty-inline {
  margin: 16px;
}

.kyc-subsection-title {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 16px 20px;
  border-top: 1px solid var(--color-border);
  border-bottom: 1px solid var(--color-border);
  color: var(--color-ink);
  font-size: 15px;
  font-weight: 800;
}

.kyc-secondary-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  border-top: 1px solid var(--color-border);
}

.kyc-half-section:first-child {
  border-right: 1px solid var(--color-border);
}

.kyc-full-section {
  grid-column: 1 / -1;
}

.kyc-secondary-grid .kyc-subsection-title {
  border-top: 0;
}

.kyc-rejection-row {
  grid-template-columns: 160px minmax(0, 1fr);
  border-top: 1px solid var(--color-surface-muted);
  border-bottom: 0;
}

.kyc-rejection-row .data-value {
  color: var(--color-danger);
  font-weight: 700;
}

.kyc-detail-card {
  border: 0;
  border-right: 1px solid var(--color-border);
  border-bottom: 1px solid var(--color-border);
  border-radius: 0;
  margin-bottom: 0;
}

.kyc-detail-card:nth-child(2n) {
  border-right: 0;
}

.kyc-section-card {
  border-top: 0;
  border-right: 0;
  border-bottom: 0;
  border-radius: 0;
  margin-bottom: 0;
}

.kyc-detail-card .data-card-title {
  display: flex;
  align-items: center;
  gap: 8px;
}

.kyc-list-stack {
  padding: 0 20px 18px;
}

.kyc-list-item {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  padding: 12px 0;
  border-top: 1px solid var(--color-surface-muted);
}

.kyc-list-item:first-child {
  border-top: 0;
}

.kyc-list-item-main {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
  color: var(--color-ink);
  font-size: 14px;
  font-weight: 700;
}

.kyc-list-item-main span {
  overflow-wrap: anywhere;
}

.kyc-list-item-main small,
.kyc-list-item-meta {
  color: var(--color-muted);
  font-size: 14px;
  font-weight: 600;
}

.kyc-list-item-value {
  max-width: 45%;
  color: var(--color-ink);
  font-size: 14px;
  font-weight: 600;
  text-align: right;
  overflow-wrap: anywhere;
}

.file-download-link {
  color: var(--color-brand);
  font-weight: 700;
  text-decoration: none;
}

.file-download-link:hover {
  text-decoration: underline;
}

.kyc-resubmit-section {
  margin-top: 20px;
  padding: 22px;
  background: var(--color-surface);
  border: 2px solid var(--color-warning-border);
  border-radius: var(--radius-md);
}

.kyc-resubmit-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 18px;
  padding-bottom: 14px;
  border-bottom: 2px solid var(--color-warning-border);
}

.kyc-resubmit-header i,
.kyc-resubmit-item-header i {
  color: var(--color-warning);
}

.kyc-resubmit-header h3 {
  font-size: 16px;
  color: var(--color-warning);
  margin: 0;
}

.kyc-resubmit-content {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.kyc-resubmit-item {
  padding: 14px;
  background: var(--color-warning-soft);
  border: 1px solid var(--color-warning-border);
  border-radius: var(--radius-md);
}

.kyc-resubmit-item-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 10px;
  color: var(--color-warning);
  font-size: 14px;
  font-weight: 700;
}

.kyc-resubmit-type {
  flex: 0 0 auto;
  padding: 2px 8px;
  color: var(--color-brand);
  background: var(--color-brand-soft);
  border-radius: 999px;
  font-size: 14px;
  text-transform: uppercase;
}

.kyc-resubmit-value {
  padding-left: 24px;
  color: var(--color-ink);
  font-size: 14px;
}

.kyc-resubmit-file {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
}

.funding-toolbar {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 14px;
}
.funding-adjust-btn {
  background: var(--color-brand-solid);
  color: #fff;
  border: none;
  border-radius: var(--radius-sm);
  padding: 9px 16px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.funding-adjust-btn:hover:not(:disabled) {
  background: var(--color-brand-strong);
}
.funding-adjust-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.ta-actions-cell {
  white-space: nowrap;
}
.ta-manage-btn {
  background: var(--color-info-soft);
  color: var(--color-brand);
  border: 1px solid #c3dafe;
  border-radius: var(--radius-sm);
  height: 30px;
  padding: 0 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.ta-manage-btn:hover,
.ta-manage-btn.active {
  background: var(--color-info-soft);
}
.ta-manage-caret {
  /* @font-floor-exempt: visual-only dropdown glyph */
  font-size: 10px;
}

.ta-manage-dropdown {
  position: fixed;
  transform: translateX(-100%);
  min-width: 168px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
  padding: 6px;
  z-index: 2000;
}
.ta-manage-item {
  width: 100%;
  background: none;
  border: none;
  border-radius: var(--radius-sm);
  padding: 9px 12px;
  font-size: 14px;
  color: var(--color-text);
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 10px;
  text-align: left;
}
.ta-manage-item:hover {
  background: var(--color-surface-soft);
  color: var(--color-brand);
}
.ta-manage-item i {
  width: 16px;
  text-align: center;
  color: var(--color-faint);
}
.ta-manage-item:hover i {
  color: var(--color-brand);
}

.funding-summary {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 14px;
  margin-bottom: 18px;
}

.funding-stat {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface-soft);
  padding: 16px;
}

.funding-stat-label {
  font-size: 14px;
  color: var(--color-muted);
  margin-bottom: 8px;
}

.funding-stat-value {
  font-size: 20px;
  font-weight: 800;
  color: var(--color-ink);
}

.funding-stat-value.positive {
  color: var(--color-success);
}

.funding-stat-value.negative {
  color: var(--color-danger);
}

.funding-transactions-panel {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  overflow: hidden;
}

.funding-transactions-panel .table-panel-header {
  padding: 14px 16px;
  margin-bottom: 0;
  background: var(--color-surface-soft);
  border-bottom: 1px solid var(--color-border);
}

.funding-type-tabs {
  display: flex;
  gap: 8px;
  padding: 12px 16px;
  border-bottom: 1px solid var(--color-border);
  overflow-x: auto;
}

.funding-type-tab {
  flex: 0 0 auto;
  padding: 7px 12px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
}

.funding-type-tab:hover,
.funding-type-tab.active {
  border-color: var(--color-brand);
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.funding-table-wrapper {
  overflow-x: auto;
}

.withdraw-method-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px;
}

.withdraw-method-card {
  display: flex;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 14px;
  width: 100%;
  padding: 16px 18px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface-soft);
}

.withdraw-method-icon {
  width: 40px;
  height: 40px;
  border-radius: var(--radius-md);
  background: var(--color-info-soft);
  color: var(--color-info);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
}

.withdraw-method-icon.crypto {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.withdraw-method-details {
  flex: 1;
  min-width: 0;
}

.withdraw-method-title {
  color: var(--color-ink);
  font-size: 14px;
  font-weight: 800;
  margin-bottom: 4px;
}

.withdraw-method-meta,
.withdraw-method-date-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 14px;
  color: var(--color-muted);
  font-size: 14px;
  font-weight: 600;
}

.withdraw-method-values {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 6px 18px;
  margin-top: 10px;
}

.withdraw-method-value {
  display: flex;
  align-items: baseline;
  gap: 6px;
  min-width: 0;
  color: var(--color-ink);
  font-size: 14px;
}

.withdraw-method-value-label {
  flex: 0 0 auto;
  color: var(--color-muted);
  font-weight: 700;
}

.withdraw-method-value-text {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-family: monospace;
  font-weight: 600;
}

.withdraw-method-empty-detail {
  margin-top: 10px;
  color: var(--color-faint);
  font-size: 14px;
  font-weight: 600;
}

.withdraw-method-date-row {
  margin-top: 10px;
}

.withdraw-method-status {
  flex: 0 0 auto;
}

.withdraw-method-actions {
  display: inline-flex;
  flex-direction: column;
  align-items: flex-end;
  justify-content: space-between;
  gap: 12px;
  align-self: stretch;
  flex: 0 0 auto;
}

.withdraw-method-detail-card {
  flex: 1 1 100%;
  min-width: 0;
  overflow: hidden;
  margin: 2px 0 0;
  max-width: 100%;
  border-radius: var(--radius-md);
}

.withdraw-method-detail-card .kyc-answer-card {
  grid-column: 1 / -1;
  border-right: 0;
}

.withdraw-method-full-card {
  border-right: 0;
}

.funding-type-pill {
  display: inline-flex;
  align-items: center;
  padding: 3px 8px;
  border-radius: 999px;
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-size: 14px;
  font-weight: 700;
}

.funding-type-pill.type-deposit {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.funding-type-pill.type-withdrawal {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.funding-type-pill.type-internal_transfer {
  background: var(--color-info-soft);
  color: #2a4365;
}

.funding-type-pill.type-credit {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.funding-status-pill {
  display: inline-flex;
  align-items: center;
  padding: 3px 8px;
  border-radius: 999px;
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-size: 14px;
  font-weight: 700;
  text-transform: capitalize;
}

.funding-status-pill.status-completed,
.funding-status-pill.status-approved,
.funding-status-pill.status-success {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.funding-status-pill.status-pending,
.funding-status-pill.status-processing,
.funding-status-pill.status-under_review,
.funding-status-pill.status-submitted {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.funding-status-pill.status-rejected,
.funding-status-pill.status-failed,
.funding-status-pill.status-cancelled,
.funding-status-pill.status-expired {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.funding-detail-toggle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 6px 10px;
  border: 0;
  border-radius: var(--radius-sm);
  background: var(--color-brand-soft);
  color: var(--color-brand);
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
}

.funding-detail-toggle:hover {
  background: var(--color-brand-solid);
  color: #ffffff;
}

.funding-detail-row td {
  padding: 0;
  background: var(--color-surface);
}

.funding-detail-card {
  padding: 16px;
  background: var(--color-surface-soft);
  border-bottom: 1px solid var(--color-border);
}

.funding-detail-state {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 90px;
  color: var(--color-muted);
  font-size: 14px;
  font-weight: 700;
}

.funding-detail-state.error {
  color: var(--color-danger);
}

.funding-detail-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  overflow: hidden;
  background: var(--color-surface);
}

.funding-detail-section {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  min-width: 0;
  border-right: 1px solid var(--color-border);
  border-bottom: 1px solid var(--color-border);
}

.funding-detail-section:nth-child(2n) {
  border-right: 0;
}

.funding-detail-section.full-width {
  grid-column: 1 / -1;
  border-right: 0;
}

.funding-detail-section.single-column {
  grid-template-columns: 1fr;
}

.funding-detail-section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  grid-column: 1 / -1;
  padding: 12px 14px;
  background: var(--color-surface-soft);
  color: var(--color-ink);
  font-size: 14px;
  font-weight: 800;
}

.funding-detail-section-title i {
  color: var(--color-brand);
}

.funding-detail-field {
  display: grid;
  grid-template-columns: 140px minmax(0, 1fr);
  gap: 12px;
  padding: 11px 14px;
  border-top: 1px solid var(--color-surface-muted);
  border-right: 1px solid var(--color-surface-muted);
  align-items: start;
}

.funding-detail-field:nth-child(2n + 1) {
  border-right: 0;
}

.funding-detail-field.full-width,
.funding-detail-section:not(.single-column)
  .funding-detail-field:last-child:nth-child(even) {
  grid-column: 1 / -1;
  border-right: 0;
}

.funding-detail-section.single-column .funding-detail-field {
  border-right: 0;
}

.funding-detail-label {
  color: var(--color-muted);
  font-size: 14px;
}

.funding-detail-value {
  color: var(--color-ink);
  font-size: 14px;
  font-weight: 600;
  overflow-wrap: anywhere;
}

.funding-detail-value.highlight {
  color: var(--color-success);
  font-weight: 800;
}

.funding-pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 16px;
  background: var(--color-surface-soft);
  border-top: 1px solid var(--color-border);
  flex-wrap: wrap;
}

.funding-pagination-info,
.funding-pagination-page {
  color: var(--color-text);
  font-size: 14px;
}

.funding-pagination-controls {
  display: flex;
  align-items: center;
  gap: 10px;
}

.funding-pagination-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 12px;
  border: 0;
  border-radius: var(--radius-sm);
  background: var(--color-border);
  color: var(--color-text);
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
}

.funding-pagination-btn:hover:not(:disabled) {
  background: var(--color-brand-solid);
  color: #ffffff;
}

.funding-pagination-btn:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.compact-empty {
  padding: 28px 20px;
}

.table-panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
}

.table-panel-header h3 {
  font-size: 15px;
  color: var(--color-ink);
}

.table-panel-actions {
  display: inline-flex;
  align-items: center;
  gap: 12px;
}

.loading-label {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: var(--color-muted);
  font-size: 14px;
}

.document-list {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
}

.doc-card {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.doc-card-main {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  padding: 16px;
  flex: 1;
  flex-direction: column;
}

.doc-info {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  min-width: 0;
  width: 100%;
}

.doc-icon {
  width: 40px;
  height: 40px;
  border-radius: var(--radius-md);
  background: var(--color-brand-soft);
  color: var(--color-brand);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.doc-copy {
  min-width: 0;
}

.doc-name {
  color: var(--color-ink);
  font-size: 14px;
  font-weight: 700;
  line-height: 1.35;
  margin-bottom: 6px;
}

.doc-date {
  color: var(--color-muted);
  font-size: 14px;
}

.doc-meta-list {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  color: var(--color-muted);
  font-size: 14px;
}

.doc-meta-item {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.doc-meta-item i {
  color: var(--color-brand);
}

.doc-signature-summary {
  display: flex;
  align-items: flex-start;
  flex-direction: row;
  justify-content: space-between;
  gap: 4px;
  width: 100%;
  flex-wrap: wrap;
}

.doc-status {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  color: var(--color-success);
  background: var(--color-success-soft);
  border-radius: 999px;
  padding: 3px 8px;
  font-size: 14px;
  font-weight: 700;
}

.doc-actions {
  display: flex;
  align-items: center;
  justify-content: stretch;
  gap: 8px;
  padding: 12px 16px;
  border-top: 1px solid var(--color-surface-muted);
  background: var(--color-surface-soft);
}

.btn-doc {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  border: 0;
  border-radius: var(--radius-sm);
  padding: 7px 10px;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s ease;
  flex: 1;
}

.btn-doc-view {
  color: var(--color-brand);
  background: var(--color-brand-soft);
}

.btn-doc-view:hover {
  background: var(--color-brand-solid);
  color: #ffffff;
}

.btn-doc-download {
  color: var(--color-success);
  background: var(--color-success-soft);
}

.btn-doc-download:hover {
  background: var(--color-success-solid);
  color: #ffffff;
}

.empty-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 180px;
  color: var(--color-faint);
  border: 1px dashed var(--color-border-strong);
  border-radius: var(--radius-md);
  background: var(--color-surface-soft);
}

.empty-card i {
  font-size: 28px;
}

.modal {
  position: fixed;
  inset: 0;
  z-index: 10000;
  background: rgba(0, 0, 0, 0.7);
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 3vh 20px;
}

.modal-content {
  width: min(900px, 100%);
  max-height: 90vh;
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  overflow: hidden;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 20px 30px;
  color: #ffffff;
  background: var(--color-brand-solid);
}

.modal-header h2 {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 20px;
  margin: 0;
}

.modal-close {
  border: 0;
  background: transparent;
  color: #ffffff;
  font-size: 28px;
  line-height: 1;
  cursor: pointer;
}

.modal-body {
  padding: 25px;
  overflow-y: auto;
}

.document-preview {
  background: var(--color-surface-soft);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 25px;
  margin-bottom: 20px;
}

.document-preview h3 {
  font-size: 18px;
  color: var(--color-ink);
  margin-bottom: 15px;
  text-align: center;
}

.document-preview-content {
  background: var(--color-surface);
  padding: 25px;
  border-radius: var(--radius-md);
  line-height: 1.8;
  color: var(--color-text);
  max-height: 400px;
  overflow-y: auto;
}

.document-preview-content p {
  margin-bottom: 15px;
}

.document-signature {
  background: var(--color-warning-soft);
  border: 2px solid #f6b93b;
  border-radius: var(--radius-md);
  padding: 20px;
}

.document-signature h4 {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--color-ink);
  font-size: 15px;
  margin-bottom: 15px;
}

.signature-info-row {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 15px;
}

.signature-field {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.signature-field label {
  font-size: 14px;
  color: var(--color-muted);
  text-transform: uppercase;
  font-weight: 700;
}

.signature-value {
  color: var(--color-ink);
  font-size: 14px;
  font-weight: 700;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 15px 25px;
  background: var(--color-surface-soft);
  border-top: 1px solid var(--color-border);
}

.btn-modal {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  border: 0;
  border-radius: var(--radius-md);
  padding: 10px 20px;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
}

.btn-modal-primary {
  color: #ffffff;
  background: var(--color-brand-solid);
}

.btn-modal-secondary {
  color: var(--color-text);
  background: var(--color-border);
}

@media (max-width: 1180px) {
  .document-list {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .ib-statistics-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 768px) {
  .client-detail-page {
    padding: 20px 15px;
  }

  .page-header,
  .client-search-bar {
    flex-direction: column;
    align-items: stretch;
  }

  .search-input {
    width: 100%;
  }

  .btn-search {
    justify-content: center;
  }

  .data-field {
    grid-template-columns: 1fr;
    gap: 4px;
  }

  .data-grid,
  .internal-data-grid,
  .registration-info-grid,
  .kyc-summary-grid,
  .kyc-answer-grid,
  .kyc-detail-grid,
  .kyc-secondary-grid {
    grid-template-columns: 1fr;
  }

  .registration-info-grid .data-field:nth-last-child(2) {
    border-bottom: 1px solid var(--color-surface-muted);
  }

  .kyc-resubmit-item-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .kyc-answer-card,
  .kyc-answer-card:nth-child(2n),
  .kyc-detail-card,
  .kyc-detail-card:nth-child(2n) {
    border-right: 0;
  }

  .kyc-half-section:first-child {
    border-right: 0;
  }

  .kyc-answer-card:nth-last-child(2):nth-child(odd) {
    border-bottom: 1px solid var(--color-border);
  }

  .kyc-list-item {
    flex-direction: column;
  }

  .kyc-list-item-value {
    max-width: none;
    text-align: left;
  }

  .document-list {
    grid-template-columns: 1fr;
  }

  .ib-statistics-grid {
    grid-template-columns: 1fr;
  }

  .doc-card-main {
    align-items: flex-start;
    flex-direction: column;
  }

  .doc-signature-summary {
    align-items: flex-start;
  }

  .doc-actions {
    flex-wrap: wrap;
    justify-content: flex-start;
  }

  .modal {
    padding: 16px;
  }

  .modal-header,
  .modal-body,
  .modal-footer {
    padding: 16px;
  }

  .signature-info-row {
    grid-template-columns: 1fr;
  }

  .modal-footer {
    flex-direction: column-reverse;
  }

  .btn-modal {
    justify-content: center;
  }
}
</style>
