<template>
  <!-- webview 模式下未通过 KYC：用 gate 模式只显示"此功能需要 KYC"，不暴露具体 KYC 状态 -->
  <KycRequiredNotice v-if="isWebView && !isKycApproved" gate />

  <div v-else class="client-transactions-page ui-page">
    <!-- Balance Card -->
    <div class="balance-card ui-surface">
      <div class="balance-content">
        <div class="balance-row">
          <div class="balance-item">
            <div class="balance-label">{{ t("transWallet", "Wallet") }}</div>
            <div class="balance-amount">
              {{ formatCurrency(accountBalance) }}
            </div>
          </div>
          <div class="balance-item">
            <div class="balance-label">
              {{ t("transTradingAccountBalance", "Trading Account Balance") }}
            </div>
            <div class="balance-amount">
              {{ formatCurrency(tradingAccountBalance) }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab Navigation：webview 模式（如 /app/deposit）下隐藏顶部切换条，只保留对应主体 -->
    <div v-if="!isWebView" class="tab-navigation ui-toolbar">
      <button
        :class="['tab-btn', { active: activeTab === 'deposit' }]"
        @click="activeTab = 'deposit'"
      >
        <i class="fas fa-arrow-down"></i>
        <span>{{ t("transDeposit", "Deposit") }}</span>
      </button>
      <button
        :class="['tab-btn', { active: activeTab === 'withdraw' }]"
        @click="activeTab = 'withdraw'"
      >
        <i class="fas fa-arrow-up"></i>
        <span>{{ t("transWithdraw", "Withdraw") }}</span>
      </button>
      <button
        :class="['tab-btn', { active: activeTab === 'transfer' }]"
        @click="activeTab = 'transfer'"
      >
        <i class="fas fa-exchange-alt"></i>
        <span>{{ t("transInternalTransfer", "Internal Transfer") }}</span>
      </button>
    </div>

    <!-- Deposit Tab -->
    <div v-show="activeTab === 'deposit'" class="tab-content">
      <div class="transaction-cards">
        <PreDepositFlow
          v-if="depositView === 'pre-step'"
          v-model="depositForm.gatewayKey"
          :gateways="paymentGateways"
          :loading="loadingPaymentGateways"
          @continue="handleProceedToDepositDetails"
        />
        <DepositPanel
          v-else-if="depositView === 'details'"
          :selected-item="selectedDepositDisplayItem"
          :gateway-content-html="depositGatewayContentHtml"
          :trading-accounts="tradingAccounts"
          :selected-target-account-type="depositForm.targetAccountType"
          :account-balance="accountBalance"
          :currencies="depositCurrencyOptions"
          :asset-type="selectedDepositAssetType"
          :selected-currency="depositForm.selectedCrypto"
          :currency-loading="!depositRateLookup.loaded"
          :currency-empty-text="
            t(
              'transNoSupportedCurrencies',
              'No supported currencies are currently available.',
            )
          "
          :is-multi-currency="depositIsMultiCurrency"
          :amount="depositForm.amount"
          :min-amount="depositGatewayMinAmount"
          :max-amount="depositGatewayMaxAmount"
          :quick-amounts="depositQuickAmounts"
          :fee-summary="depositFeeSummary"
          :exchange-rate="depositFeeSummary.exchangeRate"
          :display-currency="depositFeeSummary.displayCurrency"
          :submitting="submitting"
          :display-contents="depositDisplayContents"
          :format-currency="formatCurrency"
          :format-settlement-amount="formatSettlementAmount"
          :on-submit="confirmDeposit"
          @back="handleBackToDepositSelection"
          @select-currency="selectDepositCryptoSimple"
          @select-platform-account="onDepositPlatformAccountChange"
          @update:amount="depositForm.amount = $event"
        >
          <template #support-questions>
            <!-- gateway 配置的额外问题（原本是 Confirm 后弹窗）：现在直接展示在 Confirm 按钮上方 -->
            <div
              v-if="depositSupportQuestions.length > 0"
              class="support-questions-inline"
            >
              <div class="support-questions-title">
                <i class="fas fa-circle-info"></i>
                <span>{{ t("transDepositDetails", "Deposit Details") }}</span>
              </div>
              <div
                v-for="question in depositSupportQuestions"
                :key="question.id"
                class="form-group"
              >
                <label class="form-label">{{
                  getDepositQuestionLabel(question)
                }}</label>
                <input
                  v-if="isDepositTextQuestion(question)"
                  v-model="depositSupportDataForm[question.name]"
                  :type="
                    question.questionType === 'date'
                      ? 'date'
                      : question.questionType === 'email'
                        ? 'email'
                        : ['tel', 'phone'].includes(question.questionType)
                          ? 'tel'
                          : 'text'
                  "
                  class="form-input"
                  :class="{
                    'input-error': !!depositSupportFieldErrors[question.name],
                  }"
                  @input="clearDepositSupportFieldError(question.name)"
                />
                <CustomSelect
                  v-else-if="isDepositChoiceQuestion(question)"
                  v-model="depositSupportDataForm[question.name]"
                  :options="
                    normalizeDepositQuestionOptions(question).map((o) => ({
                      label: o.label,
                      value: o.id,
                    }))
                  "
                  :placeholder="t('transPleaseSelectOption', 'Please select')"
                  @update:modelValue="
                    clearDepositSupportFieldError(question.name)
                  "
                />
                <textarea
                  v-else
                  v-model="depositSupportDataForm[question.name]"
                  class="form-input deposit-support-textarea"
                  :class="{
                    'input-error': !!depositSupportFieldErrors[question.name],
                  }"
                  rows="3"
                  @input="clearDepositSupportFieldError(question.name)"
                ></textarea>
                <span
                  v-if="depositSupportFieldErrors[question.name]"
                  class="error-text"
                >
                  {{ depositSupportFieldErrors[question.name] }}
                </span>
                <span v-else-if="question.hintText" class="form-help">{{
                  question.hintText
                }}</span>
              </div>
            </div>
          </template>
        </DepositPanel>
        <TransactionResult
          v-else
          :status="depositResultStatus"
          :title-text="depositResultTitle"
          :message-text="depositResultMessage"
          :reference="depositConfirmation?.reference || ''"
          :rows="depositResultRows"
          :first-step-label="t('transMethod', 'Method')"
          :second-step-label="
            t('transStepVerificationAmount', 'Verification / Amount')
          "
          :primary-action-text="t('transNewDeposit', 'New Deposit')"
          :primary-action-icon="'fas fa-plus'"
          @primary-action="handleStartNewDeposit"
          @view-history="handleViewTransactionHistory"
        />
      </div>
    </div>

    <!-- Withdraw Tab -->
    <div v-show="activeTab === 'withdraw'" class="tab-content">
      <div class="transaction-cards">
        <PreWithdrawalKyc
          v-if="withdrawView === 'pre-kyc'"
          v-model="withdrawForm.gatewayKey"
          :gateways="withdrawGateways"
          :loading="loadingWithdrawGateways"
          :template-loading="loadingWithdrawalTemplatePayments"
          :template-error="withdrawalTemplatePaymentsError"
          :templates="withdrawalTemplatePayments"
          :template-meta="withdrawalTemplateMeta"
          @update:modelValue="onWithdrawGatewayModelUpdate"
          @refresh-submissions="refreshWithdrawalTemplatePayments"
          @proceed-step-two="handleProceedToWithdrawalDetails"
        />
        <WithdrawalPanel
          v-else-if="withdrawView === 'details'"
          :t="t"
          :gateway-content-html="withdrawalGatewayContentHtml"
          :security-settings="securitySettings"
          :otp-verification-status="otpVerificationStatus"
          :withdraw-form="withdrawForm"
          :account-balance="accountBalance"
          :trading-accounts="tradingAccounts"
          :withdrawal-infos="withdrawalInfos"
          :submitting="submitting"
          :fee-summary="withdrawalFeeSummary"
          :exchange-rate="withdrawalFeeSummary.exchangeRate"
          :display-currency="withdrawalFeeSummary.displayCurrency"
          :currencies="filteredWithdrawalCurrencyOptions"
          :asset-type="selectedWithdrawalAssetType"
          :selected-currency="withdrawForm.selectedCrypto"
          :currency-loading="!withdrawalRateLookup.loaded"
          :currency-empty-text="'No supported currencies are currently available.'"
          :min-amount="withdrawalGatewayMinAmount"
          :max-amount="withdrawalGatewayMaxAmount"
          :quick-amounts="withdrawalQuickAmounts"
          :request-o-t-p="requestOTP"
          :verify-o-t-p="verifyOTP"
          :on-source-account-change="onSourceAccountChange"
          :handle-b-s-b-input="handleBSBInput"
          :handle-account-number-input="handleAccountNumberInput"
          :handle-withdrawal="handleWithdrawal"
          :format-time="formatTime"
          :format-currency="formatCurrency"
          :format-settlement-amount="formatSettlementAmount"
          :format-b-s-b="formatBSB"
          :mask-account-number="maskAccountNumber"
          :selected-address="selectedWithdrawalAddress"
          :display-contents="withdrawalDisplayContents"
          :template-meta="withdrawalTemplateMeta"
          @back="handleBackToPreWithdrawalKyc"
          @select-currency="withdrawForm.selectedCrypto = $event"
        />
        <TransactionResult
          v-else
          :status="withdrawalResultStatus"
          :title-text="withdrawalResultTitle"
          :message-text="withdrawalResultMessage"
          :reference="withdrawalConfirmation?.reference || ''"
          :rows="withdrawalResultRows"
          :first-step-label="t('transStepMethodAddress', 'Method & Address')"
          :second-step-label="
            t('transStepVerificationAmount', 'Verification / Amount')
          "
          :primary-action-text="t('transNewWithdrawal', 'New Withdrawal')"
          :primary-action-icon="'fas fa-plus'"
          @primary-action="handleStartNewWithdrawal"
          @view-history="handleViewTransactionHistory"
        />
      </div>
    </div>

    <!-- Internal Transfer Tab -->
    <div v-show="activeTab === 'transfer'" class="tab-content">
      <div
        :class="[
          'transaction-cards',
          { 'transfer-layout': transferView === 'form' },
        ]"
      >
        <template v-if="transferView === 'form'">
          <div class="transaction-card transfer-form-card">
            <div class="card-header">
              <h3 class="card-title">
                <i class="fas fa-exchange-alt"></i>
                {{ t("transInternalTransfer", "Internal Transfer") }}
              </h3>
            </div>
            <form @submit.prevent="handleInternalTransfer">
              <div class="form-group">
                <label class="form-label"
                  ><i class="fas fa-wallet"></i>
                  {{ t("transFrom", "From") }}</label
                >
                <CustomSelect
                  v-model="transferForm.fromType"
                  :options="transferSourceOptions"
                  :placeholder="t('transSelectSource', 'Select source')"
                  @change="onFromTypeChange"
                />
              </div>

              <div
                v-if="transferForm.fromType === 'trading_account'"
                class="form-group"
              >
                <label class="form-label"
                  ><i class="fas fa-building"></i>
                  {{
                    t("transFromTradingAccount", "From Trading Account")
                  }}</label
                >
                <CustomSelect
                  v-model="transferForm.fromTradingAccountId"
                  :groups="transferFromAccountGroups"
                  :placeholder="
                    t('transSelectTradingAccount', 'Select trading account')
                  "
                  @change="onFromAccountChange"
                />
              </div>

              <div class="form-group">
                <label class="form-label"
                  ><i class="fas fa-arrow-right"></i>
                  {{ t("transToTradingAccount", "To Trading Account") }}</label
                >
                <CustomSelect
                  v-model="transferForm.toTradingAccountId"
                  :groups="transferToAccountGroups"
                  :placeholder="
                    t(
                      'transSelectTargetTradingAccount',
                      'Select target trading account',
                    )
                  "
                />
              </div>

              <div class="form-group">
                <label class="form-label"
                  ><i class="fas fa-dollar-sign"></i>
                  {{ t("transAmountUSD", "Amount (USD)") }}</label
                >
                <div class="input-with-icon">
                  <i class="input-icon fas fa-dollar-sign"></i>
                  <FormattedNumberInput
                    v-model="transferForm.amount"
                    :decimals="2"
                    :max="transferAmountMax"
                    :placeholder="t('transEnterAmount', 'Enter amount')"
                    required
                    input-class="form-input"
                  />
                </div>
                <span
                  class="form-help"
                  v-if="
                    transferForm.fromType == 'wallet' ||
                    transferForm.fromType == 'available_balance'
                  "
                >
                  {{ t("transAvailable", "Available:") }}
                  {{
                    formatTransferAvailableUsd(accountBalance, {
                      unit: "USD",
                      scale: 1,
                    })
                  }}
                </span>
                <span class="form-help" v-else-if="selectedFromAccount">
                  {{ t("transAvailable", "Available:") }}
                  {{
                    formatTransferAvailableUsd(
                      selectedFromAccount.availableBalance || 0,
                      selectedFromAccount,
                    )
                  }}
                </span>
              </div>

              <div v-if="transferImpactSummary" class="transfer-inline-summary">
                <div class="transfer-inline-summary-header">
                  <i class="fas fa-scale-balanced"></i>
                  <span>{{
                    t("transTransferSummary", "Transfer Summary")
                  }}</span>
                </div>
                <div class="transfer-inline-summary-row">
                  <div class="transfer-inline-summary-side">
                    <span class="transfer-inline-summary-kicker">{{
                      t("transFrom", "From")
                    }}</span>
                    <strong>{{ transferImpactSummary.fromLabel }}</strong>
                    <span class="transfer-inline-summary-change negative"
                      >-{{ transferImpactSummary.fromAmount }}</span
                    >
                    <span
                      >{{ t("transAfterTransfer", "After Transfer:") }}
                      {{ transferImpactSummary.fromAfter }}</span
                    >
                  </div>
                  <div class="transfer-inline-summary-divider">
                    <i class="fas fa-arrow-right"></i>
                  </div>
                  <div class="transfer-inline-summary-side">
                    <span class="transfer-inline-summary-kicker">{{
                      t("transTo", "To")
                    }}</span>
                    <strong>{{ transferImpactSummary.toLabel }}</strong>
                    <span class="transfer-inline-summary-change positive"
                      >+{{ transferImpactSummary.toAmount }}</span
                    >
                    <span
                      >{{ t("transAfterTransfer", "After Transfer:") }}
                      {{ transferImpactSummary.toAfter }}</span
                    >
                  </div>
                </div>
              </div>

              <button
                type="submit"
                class="btn btn-primary btn-block"
                :disabled="submitting"
              >
                <i
                  :class="
                    submitting
                      ? 'fas fa-spinner fa-spin'
                      : 'fas fa-exchange-alt'
                  "
                ></i>
                {{
                  submitting
                    ? t("transProcessing", "Processing...")
                    : t("transSubmitTransferRequest", "Submit Transfer Request")
                }}
              </button>
            </form>
          </div>

          <div
            v-if="internalTransferDisplayContents.length"
            class="transaction-card transfer-info-card"
          >
            <div class="card-header">
              <h3 class="card-title">
                <i class="fas fa-info-circle"></i>
                {{ t("transTransferInformation", "Transfer Information") }}
              </h3>
            </div>
            <div class="tips-list compact">
              <div
                v-for="(item, index) in internalTransferDisplayContents"
                :key="`${item.title || item.content || 'display'}-${index}`"
                class="tip-item"
              >
                <i :class="item.iconClass || 'fas fa-info-circle'"></i>
                <div>
                  <strong>{{ item.title }}</strong>
                  <p>{{ item.content }}</p>
                </div>
              </div>
            </div>
          </div>
        </template>

        <TransactionResult
          v-else
          :status="transferResultStatus"
          :title-text="transferResultTitle"
          :message-text="transferResultMessage"
          :reference="transferConfirmation?.reference || ''"
          :rows="transferResultRows"
          :hide-stepper="true"
          :primary-action-text="t('transNewTransfer', 'New Transfer')"
          :primary-action-icon="'fas fa-exchange-alt'"
          @primary-action="handleStartNewTransfer"
          @view-history="handleViewTransactionHistory"
        />
      </div>
    </div>

    <!-- Crypto Deposit Modal -->
    <Teleport to="body">
      <div
        :class="['modal-overlay', { active: showDepositModal }]"
        @click="showDepositModal = false"
      >
        <div class="modal-container" @click.stop>
          <div class="modal-header">
            <h2>
              <i class="fab fa-bitcoin"></i>
              {{ t("transCryptocurrencyDeposit", "Cryptocurrency Deposit") }}
            </h2>
            <button
              type="button"
              class="modal-close-btn"
              :aria-label="t('close', 'Close')"
              @click="showDepositModal = false"
            >
              ×
            </button>
          </div>
          <div class="modal-body">
            <div v-if="depositForm.selectedCrypto">
              <div class="qr-code-display">
                <div class="qr-placeholder">
                  <i class="fas fa-qrcode"></i>
                </div>
                <p>
                  {{ t("transScanQRorCopy", "Scan QR Code or Copy Address") }}
                </p>
              </div>

              <div class="form-group">
                <label class="form-label">{{
                  t("transDepositAddress", "Deposit Address")
                }}</label>
                <div class="crypto-address-display">
                  {{
                    depositAddress ||
                    t(
                      "transPleaseSelectCrypto",
                      "Please select a cryptocurrency",
                    )
                  }}
                  <button
                    v-if="depositAddress"
                    class="copy-btn"
                    @click="copyAddress(depositAddress)"
                  >
                    <i :class="copied ? 'fas fa-check' : 'fas fa-copy'"></i>
                    {{
                      copied
                        ? t("transCopied", "Copied!")
                        : t("transCopy", "Copy")
                    }}
                  </button>
                </div>
              </div>

              <div class="info-box">
                <p>
                  <i class="fas fa-info-circle"></i>
                  {{ t("transSendOnlyToAddress", "Send only") }}
                  <strong>{{ selectedCryptoInfo?.methodName }}</strong>
                  {{ t("transToThisAddress", "to this address.") }}
                </p>
              </div>

              <div class="info-box warning">
                <p>
                  <i class="fas fa-clock"></i>
                  {{ t("transWaitConfirmations", "Wait for at least") }}
                  <strong>{{ confirmationBlocks }}</strong>
                  {{
                    t(
                      "transNetworkConfirmations",
                      "network confirmations before the deposit is credited.",
                    )
                  }}
                </p>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" @click="showDepositModal = false">
              {{ t("transClose", "Close") }}
            </button>
            <button class="btn btn-primary" @click="confirmDeposit">
              <i class="fas fa-check"></i>
              {{ t("transIVeMadeTheTransfer", "I've Made the Transfer") }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div :class="['modal-overlay', { active: showTransactionRedirectModal }]">
        <div class="modal-container redirect-modal" @click.stop>
          <div class="modal-body redirect-modal-body">
            <i class="fas fa-spinner fa-spin redirect-spinner"></i>
            <h3>
              {{
                t("transRedirectingToPayment", "Redirecting to payment page...")
              }}
            </h3>
            <p>
              {{ t("transProcessing", "Processing...") }}
              {{ transactionRedirectCountdown }}s
            </p>
          </div>

          <form
            v-if="transactionRedirectMethod === 'post'"
            ref="transactionRedirectFormRef"
            :action="transactionRedirectUrl"
            method="post"
            style="display: none"
          >
            <input
              v-for="(field, index) in transactionRedirectFormFields"
              :key="`${field.key}-${index}`"
              type="hidden"
              :name="field.key"
              :value="field.value"
            />
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Add Wallet Modal -->
    <Teleport to="body">
      <div
        :class="['modal-overlay', { active: showAddWalletModal }]"
        @click="showAddWalletModal = false"
      >
        <div class="modal-container" @click.stop>
          <div class="modal-header">
            <h2>
              <i class="fas fa-plus-circle"></i>
              {{ t("transAddNewWallet", "Add New Wallet") }}
            </h2>
            <button
              type="button"
              class="modal-close-btn"
              :aria-label="t('close', 'Close')"
              @click="showAddWalletModal = false"
            >
              ×
            </button>
          </div>
          <div class="modal-body">
            <form @submit.prevent="saveNewWallet">
              <div class="form-group">
                <label class="form-label">{{
                  t("transWalletName", "Wallet Name")
                }}</label>
                <input
                  type="text"
                  class="form-input"
                  v-model="newWalletForm.walletName"
                  :placeholder="
                    t('transWalletNamePlaceholder', 'e.g., My BTC Wallet')
                  "
                  required
                />
              </div>

              <div class="form-group">
                <label class="form-label">{{
                  t("transWalletAddress", "Wallet Address")
                }}</label>
                <input
                  type="text"
                  class="form-input"
                  v-model="newWalletForm.walletAddress"
                  :placeholder="
                    t('transEnterWalletAddress', 'Enter wallet address')
                  "
                  required
                />
              </div>

              <div class="info-box warning">
                <p>
                  <i class="fas fa-exclamation-triangle"></i>
                  <strong>{{ t("transWarning", "Warning:") }}</strong>
                  {{
                    t(
                      "transVerifyWalletWarning",
                      "Verify the wallet address carefully.",
                    )
                  }}
                </p>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button
              class="btn btn-secondary"
              @click="showAddWalletModal = false"
            >
              {{ t("transCancel", "Cancel") }}
            </button>
            <button
              class="btn btn-primary"
              @click="saveNewWallet"
              :disabled="savingWallet"
            >
              <i
                :class="savingWallet ? 'fas fa-spinner fa-spin' : 'fas fa-save'"
              ></i>
              {{
                savingWallet
                  ? t("transSaving", "Saving...")
                  : t("transSaveWallet", "Save Wallet")
              }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Account Verification Modal -->
    <!--    <Teleport to="body">-->
    <!--      <div :class="['modal-overlay', { active: showVerificationModal }]" @click="showVerificationModal = false">-->
    <!--        <div class="modal-container modal-container-large" @click.stop>-->
    <!--          <div class="modal-header">-->
    <!--            <h2><i class="fas fa-shield-check"></i> Account Verification</h2>-->
    <!--            <button class="modal-close-btn" @click="showVerificationModal = false">×</button>-->
    <!--          </div>-->
    <!--          <div class="modal-body">-->
    <!--            <div class="info-banner">-->
    <!--              <div class="info-banner-content">-->
    <!--                <i class="fas fa-info-circle info-banner-icon"></i>-->
    <!--                <div class="info-banner-text">-->
    <!--                  <strong>Security First:</strong> To protect your funds and comply with regulations, we need to verify your withdrawal destination before processing your first withdrawal.-->
    <!--                </div>-->
    <!--              </div>-->
    <!--            </div>-->

    <!-- Bank Account Verification -->
    <!--            <form v-if="verificationForm.accountType === 'bank'" @submit.prevent="submitVerification">-->
    <!--              <h3 class="verification-section-title"><i class="fas fa-university"></i> Bank Account Information</h3>-->
    <!--              -->
    <!--              <div class="form-group">-->
    <!--                <label class="form-label">Bank Name *</label>-->
    <!--                <input -->
    <!--                  type="text" -->
    <!--                  class="form-input" -->
    <!--                  v-model="verificationForm.bankName"-->
    <!--                  placeholder="Enter your bank name" -->
    <!--                  required-->
    <!--                >-->
    <!--              </div>-->

    <!--              <div class="form-group">-->
    <!--                <label class="form-label">Account Holder Name *</label>-->
    <!--                <input -->
    <!--                  type="text" -->
    <!--                  class="form-input" -->
    <!--                  v-model="verificationForm.accountHolderName"-->
    <!--                  placeholder="Full name as shown on bank account" -->
    <!--                  required-->
    <!--                >-->
    <!--                <span class="form-help">Must match your registered name</span>-->
    <!--              </div>-->

    <!--              <div class="form-group">-->
    <!--                <label class="form-label">Account Number *</label>-->
    <!--                <input -->
    <!--                  type="text" -->
    <!--                  class="form-input" -->
    <!--                  v-model="verificationForm.accountNumber"-->
    <!--                  placeholder="Your bank account number" -->
    <!--                  required-->
    <!--                >-->
    <!--              </div>-->

    <!--              <div class="form-group">-->
    <!--                <label class="form-label">SWIFT/BIC Code (Optional)</label>-->
    <!--                <input -->
    <!--                  type="text" -->
    <!--                  class="form-input" -->
    <!--                  v-model="verificationForm.swiftCode"-->
    <!--                  placeholder="For international transfers"-->
    <!--                >-->
    <!--              </div>-->

    <!--              <div class="form-group">-->
    <!--                <label class="form-label">Bank Statement / Proof of Account *</label>-->
    <!--                <input -->
    <!--                  type="file" -->
    <!--                  class="form-file-input" -->
    <!--                  accept="image/*,application/pdf"-->
    <!--                  @change="handleFileUpload($event, 'bankStatement')"-->
    <!--                  required-->
    <!--                >-->
    <!--                <span class="form-help">Upload a recent bank statement or account verification document (PDF or Image, max 5MB)</span>-->
    <!--                <div v-if="verificationForm.bankStatementFile" class="file-preview">-->
    <!--                  <i class="fas fa-file-alt"></i>-->
    <!--                  <span>{{ verificationForm.bankStatementFile.name }}</span>-->
    <!--                </div>-->
    <!--              </div>-->

    <!--              <div class="form-group">-->
    <!--                <label class="form-label">Additional Notes (Optional)</label>-->
    <!--                <textarea -->
    <!--                  class="form-textarea" -->
    <!--                  v-model="verificationForm.notes"-->
    <!--                  placeholder="Any additional information"-->
    <!--                  rows="3"-->
    <!--                ></textarea>-->
    <!--              </div>-->
    <!--            </form>-->

    <!-- Crypto Wallet Verification -->
    <!--            <form v-if="verificationForm.accountType === 'crypto'" @submit.prevent="submitVerification">-->
    <!--              <h3 class="verification-section-title"><i class="fas fa-wallet"></i> Wallet Information</h3>-->
    <!--              -->
    <!--              <div class="form-group">-->
    <!--                <label class="form-label">Wallet Name *</label>-->
    <!--                <input -->
    <!--                  type="text" -->
    <!--                  class="form-input" -->
    <!--                  v-model="verificationForm.walletName"-->
    <!--                  placeholder="e.g., My BTC Wallet, Binance BTC" -->
    <!--                  required-->
    <!--                >-->
    <!--                <span class="form-help">A friendly name to identify this wallet</span>-->
    <!--              </div>-->

    <!--              <div class="form-group">-->
    <!--                <label class="form-label">Wallet Address *</label>-->
    <!--                <input -->
    <!--                  type="text" -->
    <!--                  class="form-input" -->
    <!--                  v-model="verificationForm.walletAddress"-->
    <!--                  placeholder="Enter your cryptocurrency wallet address" -->
    <!--                  required-->
    <!--                >-->
    <!--                <span class="form-help">Double-check the address carefully</span>-->
    <!--              </div>-->

    <!--              <div class="form-group">-->
    <!--                <label class="form-label">Wallet Ownership Proof *</label>-->
    <!--                <input -->
    <!--                  type="file" -->
    <!--                  class="form-file-input" -->
    <!--                  accept="image/*,application/pdf"-->
    <!--                  @change="handleFileUpload($event, 'walletScreenshot')"-->
    <!--                  required-->
    <!--                >-->
    <!--                <span class="form-help">Upload a screenshot showing the wallet address with your name/email visible (PDF or Image, max 5MB)</span>-->
    <!--                <div v-if="verificationForm.walletScreenshotFile" class="file-preview">-->
    <!--                  <i class="fas fa-file-image"></i>-->
    <!--                  <span>{{ verificationForm.walletScreenshotFile.name }}</span>-->
    <!--                </div>-->
    <!--              </div>-->

    <!--              <div class="info-box warning">-->
    <!--                <p><i class="fas fa-exclamation-triangle"></i> <strong>Important:</strong> The screenshot must show both the wallet address and identifying information (your name or registered email) from your wallet provider.</p>-->
    <!--              </div>-->

    <!--              <div class="form-group">-->
    <!--                <label class="form-label">Additional Notes (Optional)</label>-->
    <!--                <textarea -->
    <!--                  class="form-textarea" -->
    <!--                  v-model="verificationForm.notes"-->
    <!--                  placeholder="Any additional information"-->
    <!--                  rows="3"-->
    <!--                ></textarea>-->
    <!--              </div>-->
    <!--            </form>-->

    <!--            <div class="info-box">-->
    <!--              <p><i class="fas fa-clock"></i> Your verification will be reviewed within 24-48 hours. You'll receive an email notification once approved.</p>-->
    <!--            </div>-->
    <!--          </div>-->
    <!--          <div class="modal-footer">-->
    <!--            <button class="btn btn-secondary" @click="showVerificationModal = false">Cancel</button>-->
    <!--            <button class="btn btn-primary" @click="submitVerification" :disabled="uploadingVerification">-->
    <!--              <i :class="uploadingVerification ? 'fas fa-spinner fa-spin' : 'fas fa-paper-plane'"></i>-->
    <!--              {{ uploadingVerification ? 'Submitting...' : 'Submit for Verification' }}-->
    <!--            </button>-->
    <!--          </div>-->
    <!--        </div>-->
    <!--      </div>-->
    <!--    </Teleport>-->
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, onUnmounted, nextTick } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useClientAuthStore } from "@/stores/clientAuth";
import { useLanguageStore } from "@/stores/language";
import PreWithdrawalKyc from "../components/transactions/PreWithdrawalKyc.vue";
import WithdrawalPanel from "../components/transactions/WithdrawalPanel.vue";
import TransactionResult from "../components/transactions/TransactionResult.vue";
import PreDepositFlow from "../components/transactions/PreDepositFlow.vue";
import DepositPanel from "../components/transactions/DepositPanel.vue";
import CustomSelect from "../components/common/CustomSelect.vue";
import clientTransactionService from "../services/clientTransactionApi";
import tradingAccountService from "../services/tradingAccountService";
import { formatCurrency, formatNumber } from "../utils/helpers";
import FormattedNumberInput from "../components/common/FormattedNumberInput.vue";
import KycRequiredNotice from "../components/client/KycRequiredNotice.vue";

const route = useRoute();
const router = useRouter();
const clientAuthStore = useClientAuthStore();
const languageStore = useLanguageStore();
const t = (key, fallback) => languageStore.t(key, fallback);

// webview 模式（如 /app/deposit、/app/withdraw、/app/transfer）通过路由 meta 注入：
//   - isWebView: 隐藏顶部 tab 切换条
//   - transactionTab: 决定初始展示哪一个主体（deposit / withdraw / transfer）
const isWebView = computed(() => Boolean(route.meta?.isWebView));
// webview 入口的 KYC 校验：未通过时直接渲染 KycRequiredNotice，不渲染下面的交易主体
const isKycApproved = computed(() => clientAuthStore.isKycApproved);
const initialTabFromRoute = (() => {
  const metaTab = String(route.meta?.transactionTab || "").toLowerCase();
  if (
    metaTab === "withdraw" ||
    metaTab === "transfer" ||
    metaTab === "deposit"
  ) {
    return metaTab;
  }
  return "deposit";
})();

// State
// 初始 tab：优先 URL ?tab=（dashboard 深链），其次 route.meta.transactionTab（WebView 入口）
const ALLOWED_TABS = ["deposit", "withdraw", "transfer"];
const resolveInitialTab = () => {
  const candidate = String(route.query.tab || "").toLowerCase();
  if (ALLOWED_TABS.includes(candidate)) return candidate;
  return initialTabFromRoute;
};
const activeTab = ref(resolveInitialTab());

// 用户已经在该页时再次跳同 path 不同 tab，也要切过去
watch(
  () => route.query.tab,
  (next) => {
    const candidate = String(next || "").toLowerCase();
    if (ALLOWED_TABS.includes(candidate) && activeTab.value !== candidate) {
      activeTab.value = candidate;
    }
  },
);
const accountBalance = ref(0);
const tradingAccountBalance = ref(0);
const submitting = ref(false);
const savingWallet = ref(false);
const copied = ref(false);
// const uploadingVerification = ref(false); // 账户验证上传状态（已注释）

// Deposit Limits
const depositLimits = ref({
  minimumAmount: 10,
  maximumAmount: 50000,
  dailyLimit: 100000,
  monthlyLimit: 500000,
});

// Deposit Stats (今日和本月存款总额)
const depositStats = ref({
  todayDeposits: 0,
  monthlyDeposits: 0,
});

// Payment Gateways (from paymentGatewaySettings)
const paymentGateways = ref([]);
const loadingPaymentGateways = ref(true);
const withdrawGateways = ref([]);
const loadingWithdrawGateways = ref(true);
const DEFAULT_GATEWAY_AMOUNT_DECIMAL_PLACES = 2;
const MAX_GATEWAY_AMOUNT_DECIMAL_PLACES = 4;
const depositView = ref("pre-step");
const depositConfirmation = ref(null);
const depositTemplateMeta = ref(null);
const loadingDepositTemplatePayments = ref(false);
const depositTemplatePaymentsError = ref("");
const withdrawalTemplatePayments = ref([]);
const withdrawalTemplateMeta = ref(null);
const loadingWithdrawalTemplatePayments = ref(false);
const withdrawalTemplatePaymentsError = ref("");
const selectedWithdrawalAddress = ref(null);
const withdrawalConfirmation = ref(null);
const withdrawView = ref("pre-kyc");
const transferView = ref("form");
const transferConfirmation = ref(null);
const depositExchangeRateInfo = ref({
  rate: 1,
  currencyCode: "USD",
  type: "fiat",
});
const withdrawalExchangeRateInfo = ref({
  rate: 1,
  currencyCode: "USD",
  type: "fiat",
});
const depositRateLookup = ref({
  loaded: false,
  byCurrency: {},
});
const withdrawalRateLookup = ref({
  loaded: false,
  byCurrency: {},
});

// Cryptocurrencies (from gateway's supportedCryptoCurrencies)
const depositCryptos = ref([]);
const withdrawCryptos = ref([]);
const savedWallets = ref([]);
// 账户验证相关变量（已注释 - 只保留OTP验证码流程）
// const verifiedAccounts = ref([]); // 已验证的账户列表
// const needsVerification = ref(false); // 当前选择的出金方式是否需要验证

// Forms
const depositForm = ref({
  gatewayKey: null, // 选择的支付方式（gatewayKey）
  targetAccountType: "",
  tradingAccountId: "",
  platformAccountId: "",
  selectedCrypto: null, // 选择的币种（paymentMethodId）
  amount: null,
});

const depositContactForm = ref({
  fullName: "",
  email: "",
  birthday: "",
  phone: "",
  phoneCountryCode: "",
});

const depositSupportDataForm = ref({});
const depositSupportFieldErrors = ref({});

const withdrawForm = ref({
  sourceAccountType: "", // 'wallet' or 'trading_{id}'
  sourceTradingAccountId: "",
  gatewayKey: null, // 选择的支付方式（gatewayKey）
  selectedCrypto: "", // 选择的币种（paymentMethodId）
  savedWalletId: null,
  destinationAddress: "",
  amount: null,
  // 如果没有Payment Account，用户填写的账户信息
  newPaymentAccount: {
    legalName: "",
    bsb: "",
    accountNumber: "",
  },
});

const newWalletForm = ref({
  walletName: "",
  walletAddress: "",
});

// Modals
const showDepositModal = ref(false);
const showAddWalletModal = ref(false);
const showTransactionRedirectModal = ref(false);
const transactionRedirectMethod = ref("");
const transactionRedirectUrl = ref("");
const transactionRedirectPayload = ref({});
const transactionRedirectCountdown = ref(3);
const transactionRedirectFormRef = ref(null);
let transactionRedirectTimer = null;

// Internal Transfer
const tradingAccounts = ref([]);
const transferForm = ref({
  fromType: "", // 'wallet' or 'trading_account'
  fromTradingAccountId: null,
  toTradingAccountId: null,
  amount: null,
});

const withdrawalInfos = ref([
  {
    id: 1,
    icon: "fa-clock",
    title: "Processing Time",
    description: "Crypto: 1-2 hours | Bank: 2-3 days",
  },
  {
    id: 2,
    icon: "fa-percentage",
    title: "Fees",
    description: "Crypto: Network fee | Bank: Free",
  },
]);
const displayContentsByScope = ref({
  deposit: [],
  withdrawal: [],
  internal_transfer: [],
});

// 安全设置
const securitySettings = ref({
  withdrawalOtpRequired: false,
  requireVerifiedWalletOnly: false,
  requireWithdrawalVerification: false, // 是否需要首次出金验证
  otpValidityMinutes: 10,
});

// OTP 验证状态
const otpVerificationStatus = ref({
  isVerified: false,
  otpSent: false,
  otpCode: "",
  expiresAt: null,
  remainingTime: 0,
});

const otpTimer = ref(null);

const normalizeTransactionResultType = (type) => {
  const normalizedType = String(type || "")
    .trim()
    .toLowerCase();
  if (normalizedType === "withdraw" || normalizedType === "withdrawal") {
    return "withdrawal";
  }

  if (normalizedType === "transfer" || normalizedType === "internal_transfer") {
    return "internal_transfer";
  }

  return "deposit";
};

const transactionResultType = computed(() =>
  normalizeTransactionResultType(route.query.type),
);

const transactionResultStatus = computed(() => {
  const routeName = String(route.name || "");
  if (routeName === "client-transactions-success") return "success";
  if (routeName === "client-transactions-fail") return "fail";
  if (routeName === "client-transactions-pending") return "pending";
  return "";
});

const isTransactionResultRoute = computed(() =>
  Boolean(transactionResultStatus.value),
);

const transactionResultDetails = computed(() => ({
  id: String(route.query.id || "").trim(),
  amount: String(route.query.amount || "").trim(),
  fee: String(route.query.fee || "").trim(),
  total: String(route.query.total || "").trim(),
  currency: String(route.query.currency || "USD").trim(),
  exchangeRate: String(route.query.exchangeRate || "1").trim(),
  method: String(route.query.method || "").trim(),
  network: String(route.query.network || "").trim(),
  address: String(route.query.address || "").trim(),
  processingTime: String(route.query.processingTime || "").trim(),
  fromLabel: String(route.query.fromLabel || "").trim(),
  toLabel: String(route.query.toLabel || "").trim(),
}));

const hasVexoraPayInfo = computed(() => {
  const payInfo = depositConfirmation.value?.vexoraPayInfo;
  return !!(
    payInfo &&
    typeof payInfo === "object" &&
    (payInfo.virtualAccountNumber || payInfo.bankName)
  );
});

const hasFlashPayPayInfo = computed(() => {
  const payInfo = depositConfirmation.value?.flashpayPayInfo;
  if (!payInfo || typeof payInfo !== "object") {
    return false;
  }
  if (
    payInfo.payDataType === "bankcard" &&
    (payInfo.bankcard ||
      payInfo.bankName ||
      payInfo.accountNo ||
      payInfo.cashierLink)
  ) {
    return true;
  }
  if (payInfo.codeImgUrl || payInfo.codeUrl) {
    return true;
  }
  return false;
});

const hasCvPayPayInfo = computed(() => {
  const payInfo = depositConfirmation.value?.cvpayPayInfo;
  if (!payInfo || typeof payInfo !== "object") {
    return false;
  }
  if (payInfo.codeUrl || payInfo.code) {
    return true;
  }
  if (payInfo.payDataType === "JSON" && payInfo.json) {
    return true;
  }
  return false;
});

const hasPendingGatewayPayInfo = computed(
  () =>
    hasVexoraPayInfo.value || hasFlashPayPayInfo.value || hasCvPayPayInfo.value,
);

const depositResultStatus = computed(() => {
  if (
    isTransactionResultRoute.value &&
    transactionResultType.value === "deposit"
  ) {
    return transactionResultStatus.value;
  }

  const providerStatus = String(depositConfirmation.value?.status || "")
    .trim()
    .toLowerCase();
  if (["success", "completed"].includes(providerStatus)) {
    return "success";
  }
  if (["rejected", "failed", "cancelled"].includes(providerStatus)) {
    return "fail";
  }

  // VA / bank transfer: waiting for payment + webhook — not success yet
  if (hasPendingGatewayPayInfo.value) {
    return "pending";
  }
  return "pending";
});

const depositResultTitle = computed(() => {
  if (depositResultStatus.value === "pending") {
    if (hasVexoraPayInfo.value) {
      return t("transVexoraVaPendingTitle", "Transfer to Virtual Account");
    }
    if (hasFlashPayPayInfo.value) {
      return t("transFlashPayPendingTitle", "Complete FlashPay Payment");
    }
    if (hasCvPayPayInfo.value) {
      return t("transCvPayPendingTitle", "Complete CVPay Payment");
    }
    return t("paymentPending", "Payment Pending");
  }

  if (depositResultStatus.value === "fail") {
    return t("paymentFailed", "Payment Failed");
  }

  return t("paymentSuccessful", "Payment Successful!");
});

const depositResultMessage = computed(() => {
  if (hasVexoraPayInfo.value) {
    return t(
      "transVexoraVaPendingMessage",
      "Transfer the exact amount to the virtual account below before it expires. Your deposit will be credited after the payment is confirmed.",
    );
  }

  if (hasFlashPayPayInfo.value) {
    return t(
      "transFlashPayPendingMessage",
      "Complete the payment using the details below. Your deposit will be credited after FlashPay confirms the transfer.",
    );
  }

  if (hasCvPayPayInfo.value) {
    return t(
      "transCvPayPendingMessage",
      "Complete the payment using the QR or code details below. Your deposit will be credited after CVPay confirms the transfer.",
    );
  }

  if (depositResultStatus.value === "pending") {
    return t(
      "depositPendingMessage",
      "Your payment has been submitted and is waiting for the payment channel response.",
    );
  }

  if (depositResultStatus.value === "fail") {
    return t("depositFailedMessage", "Your deposit request was not completed.");
  }

  return t(
    "depositSubmittedMessage",
    "Your deposit request has been submitted successfully. We will process your payment shortly.",
  );
});

const withdrawalResultStatus = computed(() => {
  if (
    isTransactionResultRoute.value &&
    transactionResultType.value === "withdrawal"
  ) {
    return transactionResultStatus.value;
  }
  return "success";
});

const withdrawalResultTitle = computed(() => {
  if (withdrawalResultStatus.value === "pending") {
    return t("transWithdrawalPendingTitle", "Withdrawal Pending");
  }

  if (withdrawalResultStatus.value === "fail") {
    return t("transWithdrawalFailedTitle", "Withdrawal Failed");
  }

  return t("transWithdrawalSubmitted", "Withdrawal Submitted!");
});

const withdrawalResultMessage = computed(() => {
  if (withdrawalResultStatus.value === "pending") {
    return t(
      "transWithdrawalPendingMessage",
      "Your withdrawal request has been submitted and is waiting for the payment channel response.",
    );
  }

  if (withdrawalResultStatus.value === "fail") {
    return t(
      "transWithdrawalFailedMessage",
      "Your withdrawal request was not completed.",
    );
  }

  return t(
    "transWithdrawalSubmittedMessage",
    "Your withdrawal request has been submitted successfully and is being processed.",
  );
});

const transferResultStatus = computed(() => {
  if (
    isTransactionResultRoute.value &&
    transactionResultType.value === "internal_transfer"
  ) {
    return transactionResultStatus.value;
  }
  return "success";
});

const transferResultTitle = computed(() => {
  if (transferResultStatus.value === "pending") {
    return t("transTransferPendingTitle", "Transfer Pending");
  }

  if (transferResultStatus.value === "fail") {
    return t("transTransferFailedTitle", "Transfer Failed");
  }

  return t("transInternalTransferSubmitted", "Transfer Submitted!");
});

const transferResultMessage = computed(() => {
  if (transferResultStatus.value === "pending") {
    return t(
      "transTransferPendingMessage",
      "Your internal transfer request has been submitted and is waiting to be processed.",
    );
  }

  if (transferResultStatus.value === "fail") {
    return t(
      "transTransferFailedMessage",
      "Your internal transfer request was not completed.",
    );
  }

  return t(
    "transAlertTransferSuccess",
    "Your internal transfer request has been submitted successfully and is being processed.",
  );
});

const depositResultRows = computed(() =>
  buildDepositResultRows(depositConfirmation.value),
);
const withdrawalResultRows = computed(() =>
  buildWithdrawalResultRows(withdrawalConfirmation.value),
);
const transferResultRows = computed(() =>
  buildTransferResultRows(transferConfirmation.value),
);

const serializeRedirectValue = (value) => {
  if (value === undefined || value === null) {
    return "";
  }

  if (typeof value === "object") {
    return JSON.stringify(value);
  }

  return String(value);
};

const transactionRedirectFormFields = computed(() => {
  const payload = transactionRedirectPayload.value;
  if (!payload || typeof payload !== "object") {
    return [];
  }

  return Object.entries(payload).flatMap(([key, value]) => {
    if (value === undefined || value === null || value === "") {
      return [];
    }

    if (Array.isArray(value)) {
      return value.map((item) => ({
        key,
        value: serializeRedirectValue(item),
      }));
    }

    return [
      {
        key,
        value: serializeRedirectValue(value),
      },
    ];
  });
});

// Verification State (已注释 - 只保留OTP验证码流程)
/*
const verificationForm = ref({
  paymentMethodId: null,
  accountType: '', // 'bank' or 'crypto'
  // For Bank
  bankName: '',
  accountNumber: '',
  accountHolderName: '',
  swiftCode: '',
  bankStatementFile: null,
  // For Crypto
  walletName: '',
  walletAddress: '',
  walletScreenshotFile: null,
  // Common
  notes: ''
});
*/

// Computed
const selectedDepositGateway = computed(() => {
  return paymentGateways.value.find(
    (g) => g.gatewayKey === depositForm.value.gatewayKey,
  );
});

const selectedDepositDisplayItem = computed(() => {
  return {
    name: selectedDepositGateway.value?.gatewayName || "Deposit",
    value: "",
    iconClass: selectedDepositGateway.value?.iconClass || "fas fa-credit-card",
  };
});

const selectedWithdrawGateway = computed(() => {
  return withdrawGateways.value.find(
    (g) => g.gatewayKey === withdrawForm.value.gatewayKey,
  );
});

const depositGatewayKey = computed(() =>
  (depositForm.value.gatewayKey || "").toLowerCase(),
);
// 多币种网关（如 AEON）：不展示/不要求选币种，后端按 USD 1:1 记账
const depositIsMultiCurrency = computed(
  () => Number(selectedDepositGateway.value?.isMultiCurrency) === 1,
);
const hasDepositCryptos = computed(() => depositCryptos.value.length > 0);
const requiresDepositPaymentMethodId = computed(() => true);

// 当前选择的币种信息（Deposit）
const selectedCryptoInfo = computed(() => {
  if (!depositForm.value.selectedCrypto) {
    return null;
  }
  return depositCryptos.value.find(
    (c) => c.id === depositForm.value.selectedCrypto,
  );
});

const selectedDepositAssetType = computed(() =>
  String(
    selectedCryptoInfo.value?.assetType ||
      depositCryptos.value[0]?.assetType ||
      depositTemplateMeta.value?.type ||
      selectedDepositGateway.value?.type ||
      "crypto",
  ).toLowerCase(),
);

const depositCurrencyOptions = computed(() => {
  if (!depositCryptos.value.length) {
    return [];
  }

  if (!depositRateLookup.value.loaded) {
    return [];
  }

  return depositCryptos.value.filter((currency) => {
    const rateEntry =
      depositRateLookup.value.byCurrency[
        String(currency.id || "").toUpperCase()
      ];
    return rateEntry && rateEntry.exchangeRate != null;
  });
});

const depositTemplatePaymentOptions = computed(() => {
  const rawPayments =
    depositTemplateMeta.value?.payment ||
    depositTemplateMeta.value?.payments ||
    [];

  return Array.isArray(rawPayments) ? rawPayments : [];
});

const depositSupportQuestions = computed(() => {
  const rawQuestions = Array.isArray(depositTemplateMeta.value?.questions)
    ? depositTemplateMeta.value.questions
    : [];

  return rawQuestions.filter((question) =>
    matchesQuestionScope(question, "deposit"),
  );
});

// 当前选择的币种信息（Withdraw）
const selectedWithdrawCryptoInfo = computed(() => {
  if (!withdrawForm.value.selectedCrypto) {
    return null;
  }
  return withdrawCryptos.value.find(
    (c) => c.id === withdrawForm.value.selectedCrypto,
  );
});

const escapeRichTextHtml = (value) =>
  String(value || "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");

const containsHtmlMarkup = (value) =>
  /<\/?[a-z][^>]*>/i.test(String(value || ""));

const isMeaningfulHtmlContent = (value) => {
  const html = String(value || "").trim();
  if (!html) return false;

  const plainText = html
    .replace(/<br\s*\/?>/gi, " ")
    .replace(/<\/(p|div|li|h[1-6]|ul|ol)>/gi, " ")
    .replace(/<[^>]+>/g, " ")
    .replace(/&nbsp;/gi, " ")
    .replace(/\s+/g, " ")
    .trim();

  return plainText.length > 0;
};

const renderRichTextNode = (node) => {
  if (node == null) return "";

  if (typeof node === "string" || typeof node === "number") {
    return escapeRichTextHtml(String(node));
  }

  if (Array.isArray(node)) {
    return node.map(renderRichTextNode).filter(Boolean).join("");
  }

  if (typeof node !== "object") {
    return "";
  }

  if (typeof node.html === "string" && node.html.trim()) {
    return node.html.trim();
  }

  if (typeof node.content === "string" && containsHtmlMarkup(node.content)) {
    return node.content.trim();
  }

  if (Array.isArray(node.blocks)) {
    return node.blocks
      .map((block) => {
        const type = String(block?.type || "").toLowerCase();
        const data = block?.data || {};

        if (type === "header") {
          const level = Math.min(Math.max(Number(data.level || 2), 1), 6);
          return `<h${level}>${data.text || ""}</h${level}>`;
        }

        if (type === "list") {
          const tag = data.style === "ordered" ? "ol" : "ul";
          const items = Array.isArray(data.items) ? data.items : [];
          return `<${tag}>${items.map((item) => `<li>${typeof item === "string" ? item : renderRichTextNode(item)}</li>`).join("")}</${tag}>`;
        }

        if (type === "paragraph" || type === "quote") {
          return `<p>${data.text || ""}</p>`;
        }

        if (typeof data.text === "string" && data.text.trim()) {
          return `<p>${data.text}</p>`;
        }

        return "";
      })
      .join("");
  }

  if (Array.isArray(node.children) && node.children.length) {
    return node.children.map(renderRichTextNode).join("");
  }

  if (typeof node.text === "string" && node.text.trim()) {
    return `<p>${escapeRichTextHtml(node.text).replace(/\n/g, "<br>")}</p>`;
  }

  if (typeof node.content === "string" && node.content.trim()) {
    return `<p>${escapeRichTextHtml(node.content).replace(/\n/g, "<br>")}</p>`;
  }

  return "";
};

const normalizeGatewayContentHtml = (rawContent) => {
  if (rawContent == null) {
    return "";
  }

  if (typeof rawContent === "string") {
    const trimmed = rawContent.trim();
    if (!trimmed) {
      return "";
    }

    if (containsHtmlMarkup(trimmed)) {
      return isMeaningfulHtmlContent(trimmed) ? trimmed : "";
    }

    try {
      const parsed = JSON.parse(trimmed);
      return renderRichTextNode(parsed);
    } catch {
      return isMeaningfulHtmlContent(trimmed)
        ? `<p>${trimmed.replace(/\n/g, "<br>")}</p>`
        : "";
    }
  }

  return renderRichTextNode(rawContent);
};

const depositGatewayContentHtml = computed(() =>
  normalizeGatewayContentHtml(depositTemplateMeta.value?.content),
);
const withdrawalGatewayContentHtml = computed(() =>
  normalizeGatewayContentHtml(withdrawalTemplateMeta.value?.content),
);

const buildSupportedCurrencyOptions = (
  source,
  fallbackType = "crypto",
  iconClass = "fas fa-coins",
) => {
  if (!source || typeof source !== "object") {
    return [];
  }

  const resolvedType = String(
    source.type ||
      fallbackType ||
      (Array.isArray(source.supportedFiatCurrencies) &&
      source.supportedFiatCurrencies.length
        ? "fiat"
        : "crypto"),
  ).toLowerCase();

  const rawOptions =
    resolvedType === "fiat"
      ? source.supportedFiatCurrencies || []
      : source.supportedCryptoCurrencies || [];

  return (Array.isArray(rawOptions) ? rawOptions : [])
    .map((item) => {
      if (typeof item === "object" && item !== null) {
        const code =
          item.code ||
          item.shortCode ||
          item.symbol ||
          item.currency ||
          item.name ||
          "";
        return {
          id: String(code),
          code: String(code),
          methodName: item.methodName || item.name || code,
          shortCode: item.shortCode || item.code || item.symbol || String(code),
          assetType: String(item.type || resolvedType).toLowerCase(),
          iconClass: item.iconClass || iconClass,
        };
      }

      const code = String(item || "").trim();
      return {
        id: code,
        code,
        methodName: code,
        shortCode: code,
        assetType: resolvedType,
        iconClass,
      };
    })
    .filter((item) => item.id);
};

const withdrawalCurrencyOptions = computed(() =>
  buildSupportedCurrencyOptions(
    withdrawalTemplateMeta.value || selectedWithdrawGateway.value || {},
    selectedWithdrawGateway.value?.type || "crypto",
    selectedWithdrawGateway.value?.iconClass || "fas fa-coins",
  ),
);

const filteredWithdrawalCurrencyOptions = computed(() => {
  if (!withdrawalCurrencyOptions.value.length) {
    return [];
  }

  if (!withdrawalRateLookup.value.loaded) {
    return [];
  }

  return withdrawalCurrencyOptions.value.filter((currency) => {
    const rateEntry =
      withdrawalRateLookup.value.byCurrency[
        String(currency.id || "").toUpperCase()
      ];
    return rateEntry && rateEntry.exchangeRate != null;
  });
});

const selectedWithdrawalCurrencyInfo = computed(
  () =>
    filteredWithdrawalCurrencyOptions.value.find(
      (item) => item.id === withdrawForm.value.selectedCrypto,
    ) || null,
);

const selectedWithdrawalAssetType = computed(() =>
  String(
    selectedWithdrawalCurrencyInfo.value?.assetType ||
      withdrawalCurrencyOptions.value[0]?.assetType ||
      withdrawalTemplateMeta.value?.type ||
      selectedWithdrawGateway.value?.type ||
      "crypto",
  ).toLowerCase(),
);

const depositDisplayContents = computed(
  () => displayContentsByScope.value.deposit || [],
);
const withdrawalDisplayContents = computed(
  () => displayContentsByScope.value.withdrawal || [],
);
const internalTransferDisplayContents = computed(
  () => displayContentsByScope.value.internal_transfer || [],
);

const depositAddress = computed(() => {
  return selectedCryptoInfo.value?.walletAddress || "";
});

// 确认区块数
const confirmationBlocks = computed(() => {
  return selectedCryptoInfo.value?.confirmationBlocks || 3;
});

const resolvedDepositPaymentMethodId = computed(() => {
  return (
    selectedCryptoInfo.value?.paymentMethodId ||
    selectedCryptoInfo.value?.payment_method_id ||
    depositForm.value.selectedCrypto ||
    selectedDepositGateway.value?.id ||
    null
  );
});

const normalizeFeeConfig = (fees) => {
  if (!fees || typeof fees !== "object") {
    return {
      mode: "",
      percentage: 0,
      fixed: 0,
      minFee: null,
      maxFee: null,
      rules: [],
    };
  }

  const normalizedRules = Array.isArray(fees.rules)
    ? fees.rules
        .map((rule) => ({
          thresholdAmount: Number(rule?.thresholdAmount ?? 0) || 0,
          mode: String(rule?.feeMode || rule?.mode || "").toLowerCase(),
          percentage: Number(rule?.percentage || 0),
          fixed: Number(rule?.fixed || 0),
          minFee: rule?.minFee == null ? null : Number(rule.minFee),
          maxFee: rule?.maxFee == null ? null : Number(rule.maxFee),
        }))
        .sort((a, b) => a.thresholdAmount - b.thresholdAmount)
    : [];

  return {
    mode: String(fees.mode || "").toLowerCase(),
    percentage: Number(fees.percentage || 0),
    fixed: Number(fees.fixed || 0),
    minFee: fees.minFee == null ? null : Number(fees.minFee),
    maxFee: fees.maxFee == null ? null : Number(fees.maxFee),
    rules: normalizedRules,
  };
};

const resolveApplicableFeeRule = (amount, config) => {
  if (!Array.isArray(config.rules) || !config.rules.length) {
    return config;
  }

  const normalizedAmount = Number(amount || 0);
  let matchedRule = config.rules[0];

  config.rules.forEach((rule) => {
    if (normalizedAmount >= Number(rule.thresholdAmount ?? 0)) {
      matchedRule = rule;
    }
  });

  return matchedRule || config;
};

const normalizeOptionalAmount = (value) => {
  if (value === null || value === undefined || value === "") {
    return null;
  }

  const numericValue = Number(value);
  return Number.isFinite(numericValue) ? numericValue : null;
};

const normalizeGatewayAmountDecimalPlaces = (...sources) => {
  for (const source of sources) {
    const rawValue = source?.amountDecimalPlaces;
    if (rawValue === null || rawValue === undefined || rawValue === "") {
      continue;
    }

    const decimalPlaces = Number.parseInt(rawValue, 10);
    if (
      Number.isInteger(decimalPlaces) &&
      decimalPlaces >= 0 &&
      decimalPlaces <= MAX_GATEWAY_AMOUNT_DECIMAL_PLACES
    ) {
      return decimalPlaces;
    }
  }

  return DEFAULT_GATEWAY_AMOUNT_DECIMAL_PLACES;
};

const roundAmount = (
  value,
  decimalPlaces = DEFAULT_GATEWAY_AMOUNT_DECIMAL_PLACES,
) => {
  const numericValue = Number(value || 0);
  if (!Number.isFinite(numericValue)) {
    return 0;
  }

  return Number.parseFloat(numericValue.toFixed(decimalPlaces));
};

const calculateFeeSummary = (amount, fees, exchangeInfo = {}, options = {}) => {
  const normalizedAmount = Number(amount || 0);
  const baseConfig = normalizeFeeConfig(fees);
  const config = resolveApplicableFeeRule(normalizedAmount, baseConfig);
  const exchangeRate = Number(exchangeInfo?.rate || 1);
  const displayCurrency = exchangeInfo?.currencyCode || "USD";
  const direction = options.direction === "deduct" ? "deduct" : "add";
  const amountDecimalPlaces = normalizeGatewayAmountDecimalPlaces({
    amountDecimalPlaces: options.amountDecimalPlaces,
  });

  if (!normalizedAmount || normalizedAmount < 0) {
    return {
      mode: config.mode,
      feeAmount: 0,
      totalAmount: 0,
      baseTotalAmount: 0,
      exchangeRate,
      displayCurrency,
      amountDecimalPlaces,
    };
  }

  let feeAmount = 0;

  if (config.mode === "fixed") {
    feeAmount = config.fixed;
  } else if (config.mode === "dynamic") {
    feeAmount = normalizedAmount * config.percentage;
  }

  if (config.minFee != null) {
    feeAmount = Math.max(feeAmount, config.minFee);
  }

  if (config.maxFee != null) {
    feeAmount = Math.min(feeAmount, config.maxFee);
  }
  feeAmount = roundAmount(feeAmount, 2);

  const baseTotalAmount =
    direction === "deduct"
      ? roundAmount(Math.max(normalizedAmount - feeAmount, 0), 2)
      : normalizedAmount + feeAmount;

  return {
    mode: config.mode,
    feeAmount,
    totalAmount: roundAmount(
      baseTotalAmount * exchangeRate,
      amountDecimalPlaces,
    ),
    baseTotalAmount,
    exchangeRate,
    displayCurrency,
    amountDecimalPlaces,
  };
};

const depositAmountDecimalPlaces = computed(() =>
  normalizeGatewayAmountDecimalPlaces(
    depositTemplateMeta.value,
    selectedDepositGateway.value,
  ),
);

const withdrawalAmountDecimalPlaces = computed(() =>
  normalizeGatewayAmountDecimalPlaces(
    withdrawalTemplateMeta.value,
    selectedWithdrawGateway.value,
  ),
);

const depositFeeSummary = computed(() =>
  calculateFeeSummary(
    depositForm.value.amount,
    selectedCryptoInfo.value?.fees || depositTemplateMeta.value?.fees,
    depositExchangeRateInfo.value,
    { amountDecimalPlaces: depositAmountDecimalPlaces.value },
  ),
);

const withdrawalFeeSummary = computed(() =>
  calculateFeeSummary(
    withdrawForm.value.amount,
    withdrawalTemplateMeta.value?.fees,
    withdrawalExchangeRateInfo.value,
    {
      direction: "deduct",
      amountDecimalPlaces: withdrawalAmountDecimalPlaces.value,
    },
  ),
);

const depositGatewayMinAmount = computed(() =>
  normalizeOptionalAmount(
    depositTemplateMeta.value?.fees?.minAmount ??
      selectedDepositGateway.value?.feeSettings?.minAmount,
  ),
);

const depositGatewayMaxAmount = computed(() =>
  normalizeOptionalAmount(
    depositTemplateMeta.value?.fees?.maxAmount ??
      selectedDepositGateway.value?.feeSettings?.maxAmount,
  ),
);

const withdrawalGatewayMinAmount = computed(() =>
  normalizeOptionalAmount(
    withdrawalTemplateMeta.value?.fees?.minAmount ??
      selectedWithdrawGateway.value?.feeSettings?.minAmount,
  ),
);

const withdrawalGatewayMaxAmount = computed(() =>
  normalizeOptionalAmount(
    withdrawalTemplateMeta.value?.fees?.maxAmount ??
      selectedWithdrawGateway.value?.feeSettings?.maxAmount,
  ),
);

const depositQuickAmounts = computed(
  () =>
    depositTemplateMeta.value?.fees?.quickAmounts ??
    selectedDepositGateway.value?.feeSettings?.quickAmounts ??
    [],
);

const withdrawalQuickAmounts = computed(
  () =>
    withdrawalTemplateMeta.value?.fees?.quickAmounts ??
    selectedWithdrawGateway.value?.feeSettings?.quickAmounts ??
    [],
);

const validateGatewayAmountRange = (
  amount,
  minAmount,
  maxAmount,
  labels = {},
) => {
  const numericAmount = Number(amount || 0);
  const min = normalizeOptionalAmount(minAmount);
  const max = normalizeOptionalAmount(maxAmount);
  const noun = labels.noun || "amount";
  const minLabel = labels.minLabel || "minimum";
  const maxLabel = labels.maxLabel || "maximum";

  if (min !== null && numericAmount < min) {
    alert(`${noun} is below the ${minLabel}: ${formatCurrency(min)}`);
    return false;
  }

  if (max !== null && numericAmount > max) {
    alert(`${noun} exceeds the ${maxLabel}: ${formatCurrency(max)}`);
    return false;
  }

  return true;
};

const normalizeExchangeRateEntry = (item, fallbackType = "fiat") => {
  if (!item || typeof item !== "object") {
    return null;
  }

  const currency = String(item.currency || item.code || item.symbol || "")
    .trim()
    .toUpperCase();
  if (!currency) {
    return null;
  }

  const rawRate = item.exchangeRate ?? item.rate ?? item.value ?? null;
  const exchangeRate =
    rawRate == null || rawRate === "" ? null : Number(rawRate);

  return {
    type: String(item.type || fallbackType || "fiat")
      .trim()
      .toLowerCase(),
    currency,
    exchangeRate: Number.isFinite(exchangeRate) ? exchangeRate : null,
  };
};

const extractExchangeRateEntries = (response, fallbackType = "fiat") => {
  const payload = response?.data?.data || response?.data || response || {};
  const candidates = [
    payload,
    payload?.rates,
    payload?.exchangeRates,
    payload?.data,
    payload?.data?.rates,
    payload?.data?.exchangeRates,
  ];

  for (const candidate of candidates) {
    if (!candidate) continue;

    if (Array.isArray(candidate)) {
      return candidate
        .map((item) => normalizeExchangeRateEntry(item, fallbackType))
        .filter(Boolean);
    }
  }

  return [];
};

const buildExchangeRateLookup = (entries = []) =>
  entries.reduce((acc, entry) => {
    acc[entry.currency] = entry;
    return acc;
  }, {});

const setSelectedExchangeRate = ({ targetRef, lookupRef, currency, type }) => {
  const normalizedType = String(type || "fiat")
    .trim()
    .toLowerCase();
  const normalizedCurrency = String(currency || "USD")
    .trim()
    .toUpperCase();
  const matchedRate =
    lookupRef.value.byCurrency[normalizedCurrency]?.exchangeRate;

  targetRef.value = {
    rate: matchedRate != null ? matchedRate : 1,
    currencyCode: normalizedCurrency || "USD",
    type: normalizedType,
  };
};

const loadExchangeRatesForCurrencies = async ({
  type,
  transactionType,
  currencies,
  lookupRef,
  targetRef,
  selectedCurrency,
}) => {
  const normalizedType = String(type || "fiat")
    .trim()
    .toLowerCase();
  const normalizedTransactionType = String(transactionType || "")
    .trim()
    .toLowerCase();
  const normalizedCurrencies = (Array.isArray(currencies) ? currencies : [])
    .map((currency) =>
      String(currency || "")
        .trim()
        .toUpperCase(),
    )
    .filter(Boolean);

  if (!normalizedCurrencies.length) {
    lookupRef.value = {
      loaded: true,
      byCurrency: {},
    };
    setSelectedExchangeRate({
      targetRef,
      lookupRef,
      currency: selectedCurrency || "USD",
      type: normalizedType,
    });
    return;
  }

  try {
    const response = await clientTransactionService.getClientExchangeRates(
      normalizedType,
      normalizedCurrencies,
      normalizedTransactionType || null,
    );
    const entries = extractExchangeRateEntries(response, normalizedType);
    lookupRef.value = {
      loaded: true,
      byCurrency: buildExchangeRateLookup(entries),
    };
  } catch (err) {
    console.error("Failed to load exchange rates:", err);
    lookupRef.value = {
      loaded: true,
      byCurrency: {},
    };
  }

  setSelectedExchangeRate({
    targetRef,
    lookupRef,
    currency: selectedCurrency || "USD",
    type: normalizedType,
  });
};

const formatSettlementAmount = (
  value,
  currencyCode = "USD",
  decimalPlacesOverride = null,
) => {
  const normalizedCurrency = String(currencyCode || "USD")
    .trim()
    .toUpperCase();
  const numericValue = Number(value || 0);
  const hasOverride =
    Number.isInteger(decimalPlacesOverride) &&
    decimalPlacesOverride >= 0 &&
    decimalPlacesOverride <= MAX_GATEWAY_AMOUNT_DECIMAL_PLACES;

  if (normalizedCurrency === "USD") {
    return hasOverride
      ? `$${formatNumber(numericValue, decimalPlacesOverride)}`
      : formatCurrency(numericValue);
  }

  const cryptoCurrencies = ["BTC", "ETH", "USDT", "USDC", "BNB", "SOL", "XRP"];
  const decimals = hasOverride
    ? decimalPlacesOverride
    : cryptoCurrencies.includes(normalizedCurrency)
      ? 8
      : 2;
  return `${formatNumber(numericValue, decimals)} ${normalizedCurrency}`;
};

const isHttpImageUrl = (value) => {
  const raw = String(value || "").trim();
  if (!/^https?:\/\//i.test(raw)) {
    return false;
  }

  try {
    const pathname = new URL(raw).pathname.toLowerCase();
    return /\.(png|jpe?g|gif|webp|bmp|svg)$/.test(pathname);
  } catch {
    return false;
  }
};

const buildDepositResultRows = (confirmation) => {
  if (!confirmation) {
    return [];
  }

  const rows = [
    {
      label: t("transAmount", "Amount"),
      value: formatCurrency(Number(confirmation.amount || 0)),
    },
  ];

  if (Number(confirmation.fee || 0) > 0) {
    rows.push({
      label: t("transProcessingFee", "Processing Fee"),
      value: formatCurrency(Number(confirmation.fee || 0)),
    });
  }

  if (
    confirmation.currency &&
    confirmation.currency !== "USD" &&
    Number(confirmation.exchangeRate || 0) > 0
  ) {
    rows.push({
      label: t("transExchangeRate", "Exchange Rate"),
      value: `1 USD = ${formatSettlementAmount(Number(confirmation.exchangeRate || 0), confirmation.currency)}`,
    });
  }

  rows.push(
    {
      label: t("transTotalAmount", "Total Amount"),
      value: formatSettlementAmount(
        Number(confirmation.total || confirmation.amount || 0),
        confirmation.currency || "USD",
        confirmation.amountDecimalPlaces,
      ),
    },
    {
      label: t("transMethod", "Method"),
      value: confirmation.method || "-",
    },
  );

  if (confirmation.network) {
    rows.push({
      label: t("transNetwork", "Network"),
      value: confirmation.network,
    });
  }

  const payInfo = confirmation.vexoraPayInfo;
  if (payInfo && typeof payInfo === "object") {
    if (payInfo.bankName) {
      rows.push({
        label: t("transVexoraBankName", "Bank"),
        value: String(payInfo.bankName),
      });
    }
    if (payInfo.virtualAccountNumber) {
      rows.push({
        label: t("transVexoraVaNumber", "Virtual Account"),
        value: String(payInfo.virtualAccountNumber),
        copyable: true,
      });
    }
    if (payInfo.virtualAccountHolder) {
      rows.push({
        label: t("transVexoraVaHolder", "Account Holder"),
        value: String(payInfo.virtualAccountHolder),
      });
    }
    if (payInfo.expirationTimestamp) {
      rows.push({
        label: t("transVexoraVaExpires", "Expires"),
        value: formatDateTime(payInfo.expirationTimestamp),
      });
    }
    const vaRemark =
      payInfo.virtualAccountRemark ||
      payInfo.virtualAccountremark ||
      payInfo.remark;
    if (vaRemark) {
      rows.push({
        label: t("transVexoraVaRemark", "Payment Remark"),
        value: String(vaRemark),
        copyable: true,
      });
    }
  }

  const flashpayPayInfo = confirmation.flashpayPayInfo;
  if (flashpayPayInfo && typeof flashpayPayInfo === "object") {
    if (flashpayPayInfo.payOrderId) {
      rows.push({
        label: t("transFlashPayOrderId", "FlashPay Order"),
        value: String(flashpayPayInfo.payOrderId),
        copyable: true,
      });
    }
    if (flashpayPayInfo.codeImgUrl) {
      rows.push({
        label: t("transFlashPayQrImage", "QR Image URL"),
        value: String(flashpayPayInfo.codeImgUrl),
        copyable: true,
      });
    }
    if (flashpayPayInfo.codeUrl) {
      rows.push({
        label: t("transFlashPayQrCode", "QR Code"),
        value: String(flashpayPayInfo.codeUrl),
        copyable: true,
      });
    }
    const bankcard =
      flashpayPayInfo.bankcard && typeof flashpayPayInfo.bankcard === "object"
        ? flashpayPayInfo.bankcard
        : null;
    const nestedBankInfo =
      bankcard?.bankInfo && typeof bankcard.bankInfo === "object"
        ? bankcard.bankInfo
        : null;
    const bankName =
      flashpayPayInfo.bankName ||
      bankcard?.bankName ||
      bankcard?.bank_name ||
      bankcard?.bank ||
      nestedBankInfo?.bankName ||
      nestedBankInfo?.bank_name;
    const accountNo =
      flashpayPayInfo.accountNo ||
      bankcard?.accountNo ||
      bankcard?.account_no ||
      bankcard?.cardNo ||
      bankcard?.card_no ||
      bankcard?.bankNo ||
      nestedBankInfo?.bankNo ||
      nestedBankInfo?.accountNo;
    const accountName =
      flashpayPayInfo.accountName ||
      bankcard?.accountName ||
      bankcard?.account_name ||
      bankcard?.accountHolder ||
      bankcard?.name ||
      nestedBankInfo?.name ||
      nestedBankInfo?.accountName;
    const amount =
      flashpayPayInfo.amount !== undefined && flashpayPayInfo.amount !== null
        ? flashpayPayInfo.amount
        : (bankcard?.amount ?? nestedBankInfo?.amount);
    const expireTime =
      flashpayPayInfo.expireTime ||
      bankcard?.expireTime ||
      bankcard?.expire_time ||
      nestedBankInfo?.expireTime ||
      nestedBankInfo?.expire_time;
    const hasBankInfo = !!(
      bankName ||
      accountNo ||
      accountName ||
      (amount !== undefined && amount !== null && String(amount) !== "") ||
      expireTime
    );
    if (hasBankInfo) {
      rows.push({
        label: t("transFlashPayBankInfoSection", "Bank Info"),
        section: true,
      });
    }
    if (bankName) {
      rows.push({
        label: t("transFlashPayBankName", "Bank"),
        value: String(bankName),
      });
    }
    if (accountNo) {
      rows.push({
        label: t("transFlashPayAccountNo", "Account Number"),
        value: String(accountNo),
        copyable: true,
      });
    }
    if (accountName) {
      rows.push({
        label: t("transFlashPayAccountName", "Account Name"),
        value: String(accountName),
      });
    }
    if (amount !== undefined && amount !== null && String(amount) !== "") {
      rows.push({
        label: t("transFlashPayTransferAmount", "Transfer Amount"),
        value: String(amount),
      });
    }
    if (expireTime) {
      rows.push({
        label: t("transFlashPayExpires", "Expires"),
        value: formatDateTime(expireTime),
      });
    }
  }

  const cvpayPayInfo = confirmation.cvpayPayInfo;
  if (cvpayPayInfo && typeof cvpayPayInfo === "object") {
    if (cvpayPayInfo.payOrderId) {
      rows.push({
        label: t("transCvPayOrderId", "CVPay Order"),
        value: String(cvpayPayInfo.payOrderId),
        copyable: true,
      });
    }
    if (cvpayPayInfo.codeUrl) {
      const codeUrl = String(cvpayPayInfo.codeUrl);
      if (isHttpImageUrl(codeUrl)) {
        rows.push({
          label: t("transCvPayQrCode", "QR Code"),
          value: codeUrl,
          image: true,
        });
      } else {
        rows.push({
          label: t("transCvPayQrCode", "QR Code"),
          value: codeUrl,
          copyable: true,
        });
      }
    }
    if (cvpayPayInfo.code) {
      rows.push({
        label: t("transCvPayCode", "Payment Code"),
        value: String(cvpayPayInfo.code),
        copyable: true,
      });
    }
    const jsonPayload =
      cvpayPayInfo.json && typeof cvpayPayInfo.json === "object"
        ? cvpayPayInfo.json
        : null;
    if (jsonPayload) {
      Object.keys(jsonPayload).forEach((key) => {
        const value = jsonPayload[key];
        if (
          value === undefined ||
          value === null ||
          String(value).trim() === ""
        ) {
          return;
        }
        rows.push({
          label: String(key),
          value:
            typeof value === "object" ? JSON.stringify(value) : String(value),
          copyable: true,
        });
      });
    }
  }

  return rows;
};

const buildWithdrawalResultRows = (confirmation) => {
  if (!confirmation) {
    return [];
  }

  const rows = [
    {
      label: t("transAmount", "Amount"),
      value: formatCurrency(Number(confirmation.amount || 0)),
    },
  ];

  if (Number(confirmation.fee || 0) > 0) {
    rows.push({
      label: t("transProcessingFee", "Processing Fee"),
      value: formatCurrency(Number(confirmation.fee || 0)),
    });
  }

  if (
    confirmation.currency &&
    confirmation.currency !== "USD" &&
    Number(confirmation.exchangeRate || 0) > 0
  ) {
    rows.push({
      label: t("transExchangeRate", "Exchange Rate"),
      value: `1 USD = ${formatSettlementAmount(Number(confirmation.exchangeRate || 0), confirmation.currency)}`,
    });
  }

  rows.push(
    {
      label: t("transTotalAmount", "Total Amount"),
      value: formatSettlementAmount(
        Number(confirmation.total || confirmation.amount || 0),
        confirmation.currency || "USD",
        confirmation.amountDecimalPlaces,
      ),
    },
    {
      label: t("transMethod", "Method"),
      value: confirmation.method || "-",
    },
  );

  if (confirmation.address) {
    rows.push({
      label: t("transAddress", "Address"),
      value: confirmation.address,
    });
  }

  if (confirmation.processingTime) {
    rows.push({
      label: t("transEstProcessing", "Est. Processing"),
      value: confirmation.processingTime,
    });
  }

  return rows;
};

const buildTransferResultRows = (confirmation) => {
  if (!confirmation) {
    return [];
  }

  const rows = [
    {
      label: t("transAmount", "Amount"),
      value: formatCurrency(Number(confirmation.amount || 0)),
    },
    {
      label: t("transTotalAmount", "Total Amount"),
      value: formatSettlementAmount(
        Number(confirmation.total || confirmation.amount || 0),
        confirmation.currency || "USD",
      ),
    },
    {
      label: t("transMethod", "Method"),
      value:
        confirmation.method || t("transInternalTransfer", "Internal Transfer"),
    },
  ];

  if (confirmation.fromLabel) {
    rows.push({
      label: t("transFrom", "From"),
      value: confirmation.fromLabel,
    });
  }

  if (confirmation.toLabel) {
    rows.push({
      label: t("transTo", "To"),
      value: confirmation.toLabel,
    });
  }

  return rows;
};

const hydrateDepositContactForm = () => {
  const user = clientAuthStore.user || {};
  const resolvedFullName =
    user.fullName ||
    [user.firstName, user.lastName].filter(Boolean).join(" ").trim() ||
    user.legalName ||
    "";

  depositContactForm.value.fullName = resolvedFullName || "";
  depositContactForm.value.email = user.email || "";
  depositContactForm.value.birthday = "";
  depositContactForm.value.phone = user.phone || "";
  depositContactForm.value.phoneCountryCode = user.phoneCountryCode || "";
};

const toTitleCase = (value) =>
  String(value || "")
    .replace(/[_-]+/g, " ")
    .replace(/\s+/g, " ")
    .trim()
    .replace(/\b\w/g, (char) => char.toUpperCase());

const isEnabledFlag = (value) => value === true || value === 1 || value === "1";

const getDepositQuestionLabel = (question) => {
  return toTitleCase(
    question?.label || question?.title || question?.name || "Question",
  );
};

const normalizeDepositQuestionOptions = (question) => {
  const normalizeOption = (option, index) => {
    if (option && typeof option === "object") {
      const label = String(option.label || "").trim();
      const value = String(option.value ?? "");
      if (!label) {
        return null;
      }

      return {
        id:
          option.id != null && option.id !== ""
            ? String(option.id)
            : `option-${index}`,
        label,
        value,
      };
    }

    if (option == null || option === "") {
      return null;
    }

    return {
      id: `option-${index}`,
      label: String(option),
      value: String(option),
    };
  };

  if (Array.isArray(question?.options)) {
    return question.options
      .map((option, index) => normalizeOption(option, index))
      .filter(Boolean);
  }

  if (typeof question?.options === "string" && question.options.trim()) {
    try {
      const parsed = JSON.parse(question.options);
      return Array.isArray(parsed)
        ? parsed
            .map((option, index) => normalizeOption(option, index))
            .filter(Boolean)
        : [];
    } catch {
      return [];
    }
  }

  return [];
};

const isDepositTextQuestion = (question) =>
  ["text", "email", "tel", "phone", "date"].includes(
    String(question?.questionType || "").toLowerCase(),
  );
const isDepositChoiceQuestion = (question) =>
  ["single_choice", "select", "radio"].includes(
    String(question?.questionType || "").toLowerCase(),
  );

const hasDepositSupportQuestionAnswer = (question) => {
  const rawValue = depositSupportDataForm.value[question.name];
  if (isDepositChoiceQuestion(question)) {
    return rawValue !== "" && rawValue !== null && rawValue !== undefined;
  }

  return Boolean(String(rawValue || "").trim());
};

const getDepositSupportQuestionSubmitValue = (question) => {
  const rawValue = depositSupportDataForm.value[question.name];

  if (isDepositChoiceQuestion(question)) {
    const selectedOption = normalizeDepositQuestionOptions(question).find(
      (option) => String(option.id) === String(rawValue),
    );

    return selectedOption ? selectedOption.value : "";
  }

  return String(rawValue || "").trim();
};

const resetDepositSupportDataForm = () => {
  depositSupportDataForm.value = depositSupportQuestions.value.reduce(
    (acc, question) => {
      acc[question.name] = "";
      return acc;
    },
    {},
  );
  depositSupportFieldErrors.value = {};
};

const clearDepositSupportFieldError = (fieldName) => {
  if (!fieldName || !depositSupportFieldErrors.value[fieldName]) {
    return;
  }
  const next = { ...depositSupportFieldErrors.value };
  delete next[fieldName];
  depositSupportFieldErrors.value = next;
};

const extractDepositSupportFieldErrors = (errorPayload) => {
  const rawErrors = errorPayload?.errors;
  if (!rawErrors || typeof rawErrors !== "object") {
    return {};
  }

  const questionNames = new Set(
    depositSupportQuestions.value.map((question) =>
      String(question?.name || ""),
    ),
  );
  const fieldErrors = {};

  Object.entries(rawErrors).forEach(([key, value]) => {
    const fieldName = String(key || "");
    if (!questionNames.has(fieldName)) {
      return;
    }
    if (Array.isArray(value)) {
      const message = value.find((item) => String(item || "").trim() !== "");
      if (message) {
        fieldErrors[fieldName] = String(message);
      }
      return;
    }
    if (value !== null && value !== undefined && String(value).trim() !== "") {
      fieldErrors[fieldName] = String(value);
    }
  });

  return fieldErrors;
};

const buildDepositConfirmation = (response) => {
  const payInfo = response?.data?.vexoraPayInfo;
  const flashpayPayInfo = response?.data?.flashpayPayInfo;
  const cvpayPayInfo = response?.data?.cvpayPayInfo;
  return {
    reference:
      response?.data?.transactionReference ||
      response?.data?.reference ||
      response?.data?.transactionId ||
      response?.data?.depositNo ||
      response?.data?.id ||
      "",
    amount: depositForm.value.amount,
    fee: depositFeeSummary.value.feeAmount,
    total: depositFeeSummary.value.totalAmount,
    amountDecimalPlaces: depositFeeSummary.value.amountDecimalPlaces,
    currency: depositFeeSummary.value.displayCurrency,
    exchangeRate: depositFeeSummary.value.exchangeRate,
    status: response?.data?.status || "pending",
    method: selectedDepositGateway.value?.gatewayName || "Deposit",
    network:
      selectedCryptoInfo.value?.methodName ||
      selectedCryptoInfo.value?.shortCode ||
      "",
    vexoraPayInfo: payInfo && typeof payInfo === "object" ? payInfo : null,
    flashpayPayInfo:
      flashpayPayInfo && typeof flashpayPayInfo === "object"
        ? flashpayPayInfo
        : null,
    cvpayPayInfo:
      cvpayPayInfo && typeof cvpayPayInfo === "object" ? cvpayPayInfo : null,
  };
};

const buildDepositResultFromRoute = () => ({
  reference: transactionResultDetails.value.id || "",
  amount: transactionResultDetails.value.amount || "",
  fee: transactionResultDetails.value.fee || 0,
  total:
    transactionResultDetails.value.total ||
    transactionResultDetails.value.amount ||
    "",
  currency: transactionResultDetails.value.currency || "USD",
  exchangeRate: Number(transactionResultDetails.value.exchangeRate || 1),
  method: transactionResultDetails.value.method || "",
  network: transactionResultDetails.value.network || "",
});

const normalizeRedirectMethod = (method) => {
  const normalizedMethod = String(method || "")
    .trim()
    .toLowerCase();
  return normalizedMethod === "post" || normalizedMethod === "get"
    ? normalizedMethod
    : "";
};

const normalizeRedirectPayloadData = (payload) => {
  if (!payload) {
    return {};
  }

  if (typeof payload !== "object" || Array.isArray(payload)) {
    return {};
  }

  return Object.fromEntries(
    Object.entries(payload).filter(
      ([, value]) => value !== undefined && value !== null && value !== "",
    ),
  );
};

const resolveTransactionRedirectConfig = (response) => {
  const source = response?.data;
  const method = normalizeRedirectMethod(source?.redirect);

  if (!method) {
    return null;
  }

  return {
    method,
    url: String(source?.redirectUrl || "").trim(),
    payload: normalizeRedirectPayloadData(source?.redirectPayloadData),
  };
};

const stopTransactionRedirectTimer = () => {
  if (transactionRedirectTimer) {
    clearInterval(transactionRedirectTimer);
    transactionRedirectTimer = null;
  }
};

const buildTransactionRedirectUrl = (url, payload) => {
  const redirectUrl = new URL(url, window.location.origin);

  Object.entries(payload || {}).forEach(([key, value]) => {
    if (value === undefined || value === null || value === "") {
      return;
    }

    if (Array.isArray(value)) {
      value.forEach((item) => {
        redirectUrl.searchParams.append(key, serializeRedirectValue(item));
      });
      return;
    }

    redirectUrl.searchParams.set(key, serializeRedirectValue(value));
  });

  return redirectUrl.toString();
};

const executeTransactionRedirect = async () => {
  if (!transactionRedirectUrl.value) {
    alert(
      t("transAlertDepositFailed", "Failed to create deposit:") +
        " Missing redirect URL",
    );
    return;
  }

  if (transactionRedirectMethod.value === "post") {
    await nextTick();
    transactionRedirectFormRef.value?.submit();
    return;
  }

  window.location.assign(
    buildTransactionRedirectUrl(
      transactionRedirectUrl.value,
      transactionRedirectPayload.value,
    ),
  );
};

const startTransactionRedirect = async (config) => {
  transactionRedirectMethod.value = config.method;
  transactionRedirectUrl.value = config.url;
  transactionRedirectPayload.value = config.payload || {};
  transactionRedirectCountdown.value = 3;
  showTransactionRedirectModal.value = true;

  stopTransactionRedirectTimer();

  transactionRedirectTimer = setInterval(async () => {
    if (transactionRedirectCountdown.value > 1) {
      transactionRedirectCountdown.value -= 1;
      return;
    }

    stopTransactionRedirectTimer();
    showTransactionRedirectModal.value = false;
    await executeTransactionRedirect();
  }, 1000);
};

const handleTransactionRedirectResponse = async (response) => {
  const redirectConfig = resolveTransactionRedirectConfig(response);
  if (!redirectConfig) {
    return false;
  }

  if (!redirectConfig.url) {
    alert(
      t("transAlertDepositFailed", "Failed to create deposit:") +
        " Missing redirect URL",
    );
    return true;
  }

  showDepositModal.value = false;
  await startTransactionRedirect(redirectConfig);
  return true;
};

const resetDepositFlowState = () => {
  depositForm.value = {
    gatewayKey: null,
    targetAccountType: "",
    tradingAccountId: "",
    platformAccountId: "",
    selectedCrypto: null,
    amount: null,
  };
  depositCryptos.value = [];
  depositTemplateMeta.value = null;
  depositTemplatePaymentsError.value = "";
  depositExchangeRateInfo.value = {
    rate: 1,
    currencyCode: "USD",
    type: "fiat",
  };
  depositRateLookup.value = {
    loaded: false,
    byCurrency: {},
  };
  resetDepositSupportDataForm();
};

const resetWithdrawalFlowState = () => {
  selectedWithdrawalAddress.value = null;
  withdrawForm.value = {
    sourceAccountType: "",
    sourceTradingAccountId: "",
    gatewayKey: null,
    selectedCrypto: "",
    savedWalletId: null,
    destinationAddress: "",
    amount: null,
    newPaymentAccount: {
      legalName: "",
      bsb: "",
      accountNumber: "",
    },
  };
  withdrawalTemplateMeta.value = null;
  withdrawalTemplatePayments.value = [];
  withdrawalTemplatePaymentsError.value = "";
  withdrawalExchangeRateInfo.value = {
    rate: 1,
    currencyCode: "USD",
    type: "fiat",
  };
  withdrawalRateLookup.value = {
    loaded: false,
    byCurrency: {},
  };
};

const normalizeTemplatePayload = (response) => {
  const responseData = response?.data;

  if (!responseData || typeof responseData !== "object") {
    return responseData || response || null;
  }

  if (responseData.template && typeof responseData.template === "object") {
    return {
      ...responseData,
      ...responseData.template,
      questions: Array.isArray(responseData.questions)
        ? responseData.questions
        : Array.isArray(responseData.template.questions)
          ? responseData.template.questions
          : [],
    };
  }

  return responseData;
};

const matchesQuestionScope = (question, scope) => {
  if (!question || typeof question !== "object") {
    return false;
  }

  const normalizedScope = String(scope || "").toLowerCase();
  const questionScope = String(question.scope || "").toLowerCase();

  if (questionScope) {
    return questionScope === normalizedScope;
  }

  if (normalizedScope === "deposit") {
    return (
      question.depositEnabled === true || Number(question.depositEnabled) === 1
    );
  }

  if (normalizedScope === "withdraw") {
    return (
      question.withdrawEnabled === true ||
      Number(question.withdrawEnabled) === 1
    );
  }

  return false;
};

const normalizeDisplayContentItems = (payload) => {
  const source = Array.isArray(payload?.contentJson) ? payload.contentJson : [];

  return source
    .filter((item) => item && typeof item === "object")
    .map((item) => ({
      title: item.title || "",
      content: item.content || "",
      iconClass: item.iconClass || "",
    }))
    .filter((item) => item.title || item.content);
};

const loadDisplayContents = async (scope) => {
  if (!scope) return;

  try {
    const response =
      await clientTransactionService.getClientDisplayContents(scope);
    displayContentsByScope.value = {
      ...displayContentsByScope.value,
      [scope]: normalizeDisplayContentItems(response.data),
    };
  } catch (err) {
    console.error(`Failed to load display contents for ${scope}:`, err);
    displayContentsByScope.value = {
      ...displayContentsByScope.value,
      [scope]: [],
    };
  }
};

const refreshTransactionBalances = async () => {
  await Promise.all([loadAvailableBalance(), loadTradingAccounts()]);
};

const handleStartNewDeposit = async () => {
  await leaveTransactionResultRoute();
  depositConfirmation.value = null;
  depositView.value = "pre-step";
  resetDepositFlowState();
  await refreshTransactionBalances();
};

// Load data
const loadClientGateways = async () => {
  loadingPaymentGateways.value = true;
  loadingWithdrawGateways.value = true;

  try {
    const response = await clientTransactionService.getClientGateways();
    if (response.success) {
      const gateways = response.data || [];

      paymentGateways.value = gateways.filter((gateway) => {
        return (
          isEnabledFlag(gateway?.isEnabled) &&
          isEnabledFlag(gateway?.isDepositEnabled)
        );
      });

      withdrawGateways.value = gateways.filter((gateway) => {
        return (
          isEnabledFlag(gateway?.isEnabled) &&
          isEnabledFlag(gateway?.isWithdrawalEnabled)
        );
      });
    }
  } catch (err) {
    console.error("Failed to load client gateways:", err);
    paymentGateways.value = [];
    withdrawGateways.value = [];
  } finally {
    loadingPaymentGateways.value = false;
    loadingWithdrawGateways.value = false;
  }
};

const loadWithdrawalTemplatePayments = async (gatewaySettingId) => {
  if (!gatewaySettingId) {
    withdrawalTemplatePayments.value = [];
    withdrawalTemplateMeta.value = null;
    withdrawalTemplatePaymentsError.value = "";
    return;
  }

  loadingWithdrawalTemplatePayments.value = true;
  withdrawalTemplatePaymentsError.value = "";

  try {
    const response =
      await clientTransactionService.getWithdrawalTemplatePayments(
        gatewaySettingId,
      );
    if (response.success) {
      const payload = normalizeTemplatePayload(response);
      withdrawalTemplateMeta.value = payload || null;
      withdrawalTemplatePayments.value = payload?.payment || [];
    } else {
      withdrawalTemplatePayments.value = [];
      withdrawalTemplateMeta.value = null;
      withdrawalTemplatePaymentsError.value = "";
    }
  } catch (err) {
    withdrawalTemplatePayments.value = [];
    withdrawalTemplateMeta.value = null;
    withdrawalTemplatePaymentsError.value =
      err.response?.data?.message ||
      err.message ||
      "Failed to load withdrawal addresses";
    console.error("Failed to load withdrawal addresses:", err);
  } finally {
    loadingWithdrawalTemplatePayments.value = false;
  }
};

// 加载Deposit币种列表
const loadDepositCryptos = async () => {
  if (!depositForm.value.gatewayKey) {
    depositCryptos.value = [];
    return;
  }

  const gateway = paymentGateways.value.find(
    (item) => item.gatewayKey === depositForm.value.gatewayKey,
  );
  const gatewayType = String(
    gateway?.type || depositTemplateMeta.value?.type || "crypto",
  ).toLowerCase();
  const supportedOptions =
    gatewayType === "fiat"
      ? Array.isArray(gateway?.supportedFiatCurrencies)
        ? gateway.supportedFiatCurrencies
        : []
      : Array.isArray(gateway?.supportedCryptoCurrencies)
        ? gateway.supportedCryptoCurrencies
        : [];

  const toDepositOption = (crypto) => {
    if (typeof crypto === "object" && crypto !== null) {
      const code =
        crypto.shortCode ||
        crypto.code ||
        crypto.name ||
        crypto.methodName ||
        crypto.symbol ||
        crypto.currency ||
        crypto.paymentMethodId ||
        "";
      return {
        id: String(code),
        paymentMethodId:
          crypto.paymentMethodId || crypto.payment_method_id || null,
        methodName: crypto.methodName || crypto.name || code,
        shortCode:
          crypto.shortCode ||
          crypto.code ||
          crypto.symbol ||
          crypto.currency ||
          String(code),
        code:
          crypto.code ||
          crypto.shortCode ||
          crypto.symbol ||
          crypto.currency ||
          String(code),
        iconClass: crypto.iconClass || gateway?.iconClass || "fas fa-coins",
        questions: crypto.questions || [],
        walletAddress: crypto.walletAddress || "",
        confirmationBlocks: crypto.confirmationBlocks,
        fees: crypto.fees || null,
        assetType: String(crypto.type || gatewayType).toLowerCase(),
      };
    }

    const code = String(crypto || "").trim();
    return {
      id: code,
      paymentMethodId: null,
      methodName: code,
      shortCode: code,
      code,
      iconClass: gateway?.iconClass || "fas fa-coins",
      questions: [],
      walletAddress: "",
      confirmationBlocks: null,
      fees: null,
      assetType: gatewayType,
    };
  };

  const buildMatchKeys = (crypto) =>
    [
      crypto?.paymentMethodId,
      crypto?.payment_method_id,
      crypto?.id,
      crypto?.shortCode,
      crypto?.code,
      crypto?.symbol,
      crypto?.currency,
      crypto?.name,
      crypto?.methodName,
    ]
      .filter(
        (value) =>
          value !== undefined && value !== null && String(value).trim() !== "",
      )
      .map((value) => String(value).trim().toLowerCase());

  const baseOptions = supportedOptions
    .map(toDepositOption)
    .filter((crypto) => crypto.id);
  const templateOptions = depositTemplatePaymentOptions.value
    .map(toDepositOption)
    .filter((crypto) => crypto.id);
  const mergedOptions = [...baseOptions];

  templateOptions.forEach((templateOption) => {
    const templateKeys = buildMatchKeys(templateOption);
    const existingIndex = mergedOptions.findIndex((baseOption) => {
      const baseKeys = buildMatchKeys(baseOption);
      return templateKeys.some((key) => baseKeys.includes(key));
    });

    if (existingIndex >= 0) {
      mergedOptions[existingIndex] = {
        ...mergedOptions[existingIndex],
        ...templateOption,
        id: mergedOptions[existingIndex].id || templateOption.id,
        questions: templateOption.questions?.length
          ? templateOption.questions
          : mergedOptions[existingIndex].questions,
        fees: templateOption.fees || mergedOptions[existingIndex].fees,
      };
      return;
    }

    mergedOptions.push(templateOption);
  });

  depositCryptos.value = mergedOptions.filter((crypto) => crypto.id);
};

// 加载Withdraw币种列表
// eslint-disable-next-line no-unused-vars
const loadWithdrawCryptos = async () => {
  if (!withdrawForm.value.gatewayKey) {
    console.log("loadWithdrawCryptos: No gatewayKey selected");
    return;
  }
  try {
    console.log(
      "loadWithdrawCryptos: Loading cryptos for gateway:",
      withdrawForm.value.gatewayKey,
    );
    const response = await clientTransactionService.getGatewayCryptos(
      withdrawForm.value.gatewayKey,
    );
    if (response.success) {
      withdrawCryptos.value = (response.data || []).filter(
        (c) => c.isWithdrawalEnabled && c.isActive,
      );
      console.log(
        "loadWithdrawCryptos: Loaded",
        withdrawCryptos.value.length,
        "cryptos",
      );
    } else {
      console.warn("loadWithdrawCryptos: API returned success=false", response);
    }
  } catch (err) {
    console.error("Failed to load withdraw cryptos:", err);
  }
};

const loadSavedWallets = async () => {
  try {
    // 根据选择的币种的paymentMethodId加载钱包
    if (withdrawForm.value.selectedCrypto) {
      const response = await clientTransactionService.getSavedWallets(
        withdrawForm.value.selectedCrypto,
      );
      if (response.success) {
        savedWallets.value = response.data || [];
      }
    }
  } catch (err) {
    console.error("Failed to load saved wallets:", err);
  }
};

// 过滤保存的钱包（根据选择的币种）
// eslint-disable-next-line no-unused-vars
const filteredSavedWallets = computed(() => {
  if (!selectedWithdrawCryptoInfo.value) {
    return savedWallets.value;
  }
  return savedWallets.value.filter(
    (w) => w.paymentMethodId === selectedWithdrawCryptoInfo.value.id,
  );
});

// Deposit支付方式变更
const onDepositGatewayChange = async () => {
  depositForm.value.selectedCrypto = null;
  depositView.value = "pre-step";
  depositConfirmation.value = null;
  depositTemplateMeta.value = null;
  depositTemplatePaymentsError.value = "";
  depositCryptos.value = [];
  depositExchangeRateInfo.value = {
    rate: 1,
    currencyCode: "USD",
    type: "fiat",
  };
  depositRateLookup.value = {
    loaded: false,
    byCurrency: {},
  };

  if (depositForm.value.gatewayKey) {
    await loadDepositCryptos();
  }
};

const selectDepositCryptoSimple = (cryptoId) => {
  depositForm.value.selectedCrypto = cryptoId;
};

const loadDepositTemplatePayments = async (gatewaySettingId) => {
  if (!gatewaySettingId) {
    depositTemplateMeta.value = null;
    depositTemplatePaymentsError.value = "";
    return;
  }

  loadingDepositTemplatePayments.value = true;
  depositTemplatePaymentsError.value = "";

  try {
    const response =
      await clientTransactionService.getDepositTemplatePayments(
        gatewaySettingId,
      );
    const payload = normalizeTemplatePayload(response);
    depositTemplateMeta.value = payload || null;
    await loadDepositCryptos();
  } catch (err) {
    depositTemplateMeta.value = null;
    depositTemplatePaymentsError.value =
      err.response?.data?.message ||
      err.message ||
      "Failed to load deposit template";
  } finally {
    loadingDepositTemplatePayments.value = false;
  }
};

const handleProceedToDepositDetails = async () => {
  if (!depositForm.value.gatewayKey) {
    alert(t("transAlertSelectPaymentMethod", "Please select a payment method"));
    return;
  }

  await loadDepositTemplatePayments(selectedDepositGateway.value?.id || null);
  if (depositTemplatePaymentsError.value) {
    alert(depositTemplatePaymentsError.value);
    return;
  }

  depositView.value = "details";
};

const handleBackToDepositSelection = () => {
  resetDepositFlowState();
  depositView.value = "pre-step";
};

const leaveTransactionResultRoute = async () => {
  if (!isTransactionResultRoute.value) {
    return;
  }

  await router.replace("/client/transactions");
};

const resolveDepositPlatformAccountId = (account) => {
  if (!account) return "";
  return String(
    account.platformAccountId ||
      account.providerAccountId ||
      account.externalAccount?.providerAccountId ||
      "",
  ).trim();
};

const extractTradingAccountIdFromSelection = (selectionValue) => {
  const normalizedSelection = String(selectionValue || "").trim();
  if (!normalizedSelection.startsWith("trading_")) return "";
  return String(normalizedSelection.replace("trading_", "")).trim();
};

const findTradingAccountById = (accountId) => {
  const normalizedAccountId = String(accountId || "").trim();
  if (!normalizedAccountId) return null;
  return (
    tradingAccounts.value.find(
      (account) => String(account.id) === normalizedAccountId,
    ) || null
  );
};

const onDepositPlatformAccountChange = (targetAccountType) => {
  depositForm.value.targetAccountType = String(targetAccountType || "").trim();

  if (depositForm.value.targetAccountType === "wallet") {
    depositForm.value.tradingAccountId = "";
    depositForm.value.platformAccountId = "";
    return;
  }

  if (depositForm.value.targetAccountType.startsWith("trading_")) {
    const tradingAccountId = extractTradingAccountIdFromSelection(
      depositForm.value.targetAccountType,
    );
    const matchedAccount = findTradingAccountById(tradingAccountId);
    depositForm.value.tradingAccountId = matchedAccount
      ? String(matchedAccount.id)
      : "";
    depositForm.value.platformAccountId =
      resolveDepositPlatformAccountId(matchedAccount);
    return;
  }

  depositForm.value.tradingAccountId = "";
  depositForm.value.platformAccountId = "";
};

// Deposit币种选择
// eslint-disable-next-line no-unused-vars
const selectDepositCrypto = (cryptoId) => {
  // 在打开弹窗前先验证金额
  if (!depositForm.value.amount) {
    alert(t("transAlertEnterAmountFirst", "Please enter an amount first"));
    return;
  }

  // 验证每日限额
  if (depositLimits.value.dailyLimit > 0) {
    const totalAfterDeposit =
      depositStats.value.todayDeposits + depositForm.value.amount;
    if (totalAfterDeposit > depositLimits.value.dailyLimit) {
      const remaining =
        depositLimits.value.dailyLimit - depositStats.value.todayDeposits;
      alert(
        t(
          "transAlertDailyLimitExceeded",
          "Daily deposit limit exceeded. Remaining limit:",
        ) +
          " " +
          formatCurrency(remaining),
      );
      return;
    }
  }

  // 验证每月限额
  if (depositLimits.value.monthlyLimit > 0) {
    const totalAfterDeposit =
      depositStats.value.monthlyDeposits + depositForm.value.amount;
    if (totalAfterDeposit > depositLimits.value.monthlyLimit) {
      const remaining =
        depositLimits.value.monthlyLimit - depositStats.value.monthlyDeposits;
      alert(
        t(
          "transAlertMonthlyLimitExceeded",
          "Monthly deposit limit exceeded. Remaining limit:",
        ) +
          " " +
          formatCurrency(remaining),
      );
      return;
    }
  }

  // 验证通过后，仅选择币种
  depositForm.value.selectedCrypto = cryptoId;
};

// Withdraw支付方式变更
const onWithdrawGatewayChange = async () => {
  console.log(
    "onWithdrawGatewayChange: Gateway changed to",
    withdrawForm.value.gatewayKey,
  );
  withdrawForm.value.selectedCrypto = "";
  withdrawForm.value.savedWalletId = null;
  withdrawForm.value.destinationAddress = "";
  // 账户验证相关变量已注释（只保留OTP验证码流程）
  // needsVerification.value = false;
  // verifiedAccounts.value = [];
};

const onWithdrawGatewayModelUpdate = async (gatewayKey) => {
  withdrawForm.value.gatewayKey = gatewayKey || null;
  selectedWithdrawalAddress.value = null;
  withdrawView.value = "pre-kyc";
  await onWithdrawGatewayChange();
  if (withdrawForm.value.gatewayKey) {
    const selectedGateway = withdrawGateways.value.find(
      (gateway) => gateway.gatewayKey === withdrawForm.value.gatewayKey,
    );
    await loadWithdrawalTemplatePayments(selectedGateway?.id || null);
  } else {
    withdrawalTemplatePayments.value = [];
    withdrawalTemplateMeta.value = null;
    withdrawalTemplatePaymentsError.value = "";
  }
};

const refreshWithdrawalTemplatePayments = async () => {
  if (!withdrawForm.value.gatewayKey) return;
  const selectedGateway = withdrawGateways.value.find(
    (gateway) => gateway.gatewayKey === withdrawForm.value.gatewayKey,
  );
  await loadWithdrawalTemplatePayments(selectedGateway?.id || null);
};

const handleProceedToWithdrawalDetails = (address) => {
  selectedWithdrawalAddress.value = address || null;
  withdrawalConfirmation.value = null;
  withdrawView.value = "details";
};

const handleBackToPreWithdrawalKyc = () => {
  withdrawalConfirmation.value = null;
  resetWithdrawalFlowState();
  withdrawView.value = "pre-kyc";
};

const buildWithdrawalConfirmation = (response, options = {}) => {
  const responseData = response?.data || {};
  const selectedGatewayInfo = withdrawGateways.value.find(
    (gateway) => gateway.gatewayKey === withdrawForm.value.gatewayKey,
  );
  const directSupportAddress =
    options.supportData?.address || options.supportData?.Address || "";

  return {
    reference:
      responseData.transactionReference ||
      responseData.reference ||
      responseData.transactionId ||
      responseData.withdrawalNo ||
      responseData.id ||
      "",
    amount: withdrawForm.value.amount,
    fee: withdrawalFeeSummary.value.feeAmount,
    total: withdrawalFeeSummary.value.totalAmount,
    amountDecimalPlaces: withdrawalFeeSummary.value.amountDecimalPlaces,
    currency: withdrawalFeeSummary.value.displayCurrency,
    exchangeRate: withdrawalFeeSummary.value.exchangeRate,
    method:
      withdrawalTemplateMeta.value?.gatewayName ||
      selectedGatewayInfo?.gatewayName ||
      "Withdrawal",
    address:
      selectedWithdrawalAddress.value?.value || directSupportAddress || "",
    processingTime:
      withdrawalTemplateMeta.value?.processingTime ||
      selectedGatewayInfo?.processingTime ||
      "",
  };
};

const buildWithdrawalResultFromRoute = () => ({
  reference: transactionResultDetails.value.id || "",
  amount: transactionResultDetails.value.amount || "",
  fee: transactionResultDetails.value.fee || 0,
  total:
    transactionResultDetails.value.total ||
    transactionResultDetails.value.amount ||
    "",
  currency: transactionResultDetails.value.currency || "USD",
  exchangeRate: Number(transactionResultDetails.value.exchangeRate || 1),
  method: transactionResultDetails.value.method || "Withdrawal",
  address: transactionResultDetails.value.address || "",
  processingTime: transactionResultDetails.value.processingTime || "",
});

const handleStartNewWithdrawal = async () => {
  await leaveTransactionResultRoute();
  withdrawalConfirmation.value = null;
  resetWithdrawalFlowState();
  withdrawView.value = "pre-kyc";
  await refreshTransactionBalances();
};

const buildTransferConfirmation = (response) => ({
  reference:
    response?.data?.transactionReference ||
    response?.data?.reference ||
    response?.data?.transactionId ||
    response?.data?.transferNo ||
    response?.data?.id ||
    "",
  amount: transferForm.value.amount,
  fee: 0,
  total: transferForm.value.amount,
  currency: "USD",
  exchangeRate: 1,
  method: t("transInternalTransfer", "Internal Transfer"),
  network: "",
});

const buildTransferResultFromRoute = () => ({
  reference: transactionResultDetails.value.id || "",
  amount: transactionResultDetails.value.amount || "",
  fee: transactionResultDetails.value.fee || 0,
  total:
    transactionResultDetails.value.total ||
    transactionResultDetails.value.amount ||
    "",
  currency: transactionResultDetails.value.currency || "USD",
  exchangeRate: Number(transactionResultDetails.value.exchangeRate || 1),
  method:
    transactionResultDetails.value.method ||
    t("transInternalTransfer", "Internal Transfer"),
  fromLabel: transactionResultDetails.value.fromLabel || "",
  toLabel: transactionResultDetails.value.toLabel || "",
});

const handleStartNewTransfer = async () => {
  await leaveTransactionResultRoute();
  transferConfirmation.value = null;
  transferView.value = "form";
  transferForm.value = {
    fromType: "",
    fromTradingAccountId: null,
    toTradingAccountId: null,
    amount: null,
  };
  await refreshTransactionBalances();
};

const handleViewTransactionHistory = () => {
  router.push("/client/transaction-history");
};

// Withdraw币种变更
// eslint-disable-next-line no-unused-vars
const onWithdrawCryptoChange = async () => {
  withdrawForm.value.savedWalletId = null;
  withdrawForm.value.destinationAddress = "";

  // 账户验证相关代码（已注释 - 只保留OTP验证码流程）
  /*
  if (withdrawForm.value.selectedCrypto) {
    const verified = await loadVerifiedAccounts(withdrawForm.value.selectedCrypto);

    if (securitySettings.value.requireWithdrawalVerification && verified.length === 0) {
      needsVerification.value = true;
    } else {
      needsVerification.value = false;
    }
  }
  */

  await loadSavedWallets();
};

// Load trading accounts
const loadTradingAccounts = async () => {
  try {
    const response = await tradingAccountService.getAccounts();
    if (response.success && response.data) {
      tradingAccounts.value = response.data.accounts || [];

      // 计算所有交易账户的余额总和（后端已返回每个账户的余额）
      calculateTradingAccountBalance();
    }
  } catch (err) {
    console.error("Failed to load trading accounts:", err);
  }
};

// 计算所有交易账户的余额总和
const calculateTradingAccountBalance = () => {
  try {
    let totalBalance = 0;

    tradingAccounts.value.forEach((acc) => {
      if (
        acc.availableBalance !== null &&
        acc.availableBalance !== undefined &&
        acc.availableBalance !== ""
      ) {
        totalBalance += resolveTransferAvailableUsdValue(
          acc.availableBalance,
          acc,
        );
      }
    });

    tradingAccountBalance.value = totalBalance;
  } catch (err) {
    console.error("Failed to calculate trading account balance:", err);
    tradingAccountBalance.value = 0;
  }
};

// Computed for available trading accounts (excluding source account)
const availableTradingAccounts = computed(() => {
  if (
    transferForm.value.fromType === "trading_account" &&
    transferForm.value.fromTradingAccountId
  ) {
    return tradingAccounts.value.filter(
      (acc) => acc.id !== transferForm.value.fromTradingAccountId,
    );
  }
  return tradingAccounts.value;
});

const groupTradingAccountsByPlatform = (accounts = []) => {
  const groups = new Map();

  accounts.forEach((account) => {
    const label =
      account.platformName ||
      account.platformCode ||
      account.platformKey ||
      t("transTradingAccounts", "Trading Accounts");
    if (!groups.has(label)) {
      groups.set(label, []);
    }
    groups.get(label).push(account);
  });

  return Array.from(groups.entries()).map(([label, groupedAccounts]) => ({
    label,
    accounts: groupedAccounts,
  }));
};

const groupedTradingAccounts = computed(() =>
  groupTradingAccountsByPlatform(tradingAccounts.value),
);
const groupedAvailableTradingAccounts = computed(() =>
  groupTradingAccountsByPlatform(availableTradingAccounts.value),
);
const transferSourceOptions = computed(() => [
  { value: "wallet", label: t("transWallet", "Wallet") },
  {
    value: "trading_account",
    label: t("transTradingAccount", "Trading Account"),
  },
]);
const resolveTransferDisplayUnit = (source = {}) =>
  String(
    source?.groupUnit ||
      source?.unit ||
      source?.accountCurrency ||
      source?.currency ||
      "USD",
  )
    .trim()
    .toUpperCase() || "USD";

const resolveTransferDisplayScale = (source = {}) => {
  const scale = Number(source?.groupScale ?? source?.scale ?? 1);
  return Number.isFinite(scale) && scale > 0 ? scale : 1;
};

const formatTransferDisplayValue = (amount, source = {}) => {
  const normalizedAmount = Number(amount || 0);
  const normalizedUnit = resolveTransferDisplayUnit(source);

  if (normalizedUnit === "USD") {
    return `${formatCurrency(normalizedAmount)} USD`;
  }

  return `${formatNumber(normalizedAmount, 2)} ${normalizedUnit}`;
};

const formatTransferImpactValue = (amount, source = {}) => {
  const scaledImpact =
    Number(amount || 0) * resolveTransferDisplayScale(source);
  return formatTransferDisplayValue(scaledImpact, source);
};

const formatTransferAvailableUsd = (amount, source = {}) => {
  const scale = resolveTransferDisplayScale(source);
  const normalizedAmount = Number(amount || 0) / scale;
  return `${formatCurrency(normalizedAmount)} USD`;
};

const resolveTransferAvailableUsdValue = (amount, source = {}) => {
  const scale = resolveTransferDisplayScale(source);
  return Number(amount || 0) / scale;
};

const mapTransferAccountGroups = (groups) =>
  groups.map((group) => ({
    label: group.label,
    options: group.accounts.map((account) => ({
      value: account.id,
      label: `${account.accountNickname} (${account.accountNumber}) - ${formatTransferDisplayValue(account.availableBalance || 0, account)}`,
    })),
  }));
const transferFromAccountGroups = computed(() =>
  mapTransferAccountGroups(groupedTradingAccounts.value),
);
const transferToAccountGroups = computed(() =>
  mapTransferAccountGroups(groupedAvailableTradingAccounts.value),
);

// Selected from account
const selectedFromAccount = computed(() => {
  if (
    transferForm.value.fromType === "trading_account" &&
    transferForm.value.fromTradingAccountId
  ) {
    return tradingAccounts.value.find(
      (acc) => acc.id === transferForm.value.fromTradingAccountId,
    );
  }
  return null;
});

const selectedToAccount = computed(() => {
  if (!transferForm.value.toTradingAccountId) {
    return null;
  }

  return (
    tradingAccounts.value.find(
      (acc) => acc.id === transferForm.value.toTradingAccountId,
    ) || null
  );
});

const transferAmountMax = computed(() => {
  const isFromWallet =
    transferForm.value.fromType === "wallet" ||
    transferForm.value.fromType === "available_balance";

  if (isFromWallet) {
    return Math.max(
      resolveTransferAvailableUsdValue(accountBalance.value, {
        unit: "USD",
        scale: 1,
      }),
      0,
    );
  }

  if (!selectedFromAccount.value) {
    return null;
  }

  return Math.max(
    resolveTransferAvailableUsdValue(
      selectedFromAccount.value.availableBalance || 0,
      selectedFromAccount.value,
    ),
    0,
  );
});

const transferImpactSummary = computed(() => {
  const amount = Number(transferForm.value.amount || 0);

  if (!amount || amount <= 0 || !selectedToAccount.value) {
    return null;
  }

  const isFromWallet =
    transferForm.value.fromType === "wallet" ||
    transferForm.value.fromType === "available_balance";
  const fromSource = isFromWallet
    ? {
        unit: "USD",
        scale: 1,
        availableBalance: Number(accountBalance.value || 0),
      }
    : selectedFromAccount.value;

  if (!fromSource) {
    return null;
  }

  const fromScale = resolveTransferDisplayScale(fromSource);
  const toScale = resolveTransferDisplayScale(selectedToAccount.value);
  const fromBeforeRaw = Number(fromSource.availableBalance || 0);
  const toBeforeRaw = Number(selectedToAccount.value.availableBalance || 0);
  const fromDeltaRaw = amount * fromScale;
  const toDeltaRaw = amount * toScale;

  return {
    fromLabel: isFromWallet
      ? t("transWallet", "Wallet")
      : `${selectedFromAccount.value.accountNickname} (${selectedFromAccount.value.accountNumber})`,
    toLabel: `${selectedToAccount.value.accountNickname} (${selectedToAccount.value.accountNumber})`,
    fromAmount: formatTransferImpactValue(amount, fromSource),
    toAmount: formatTransferImpactValue(amount, selectedToAccount.value),
    fromBefore: formatTransferDisplayValue(fromBeforeRaw, fromSource),
    toBefore: formatTransferDisplayValue(toBeforeRaw, selectedToAccount.value),
    fromAfter: formatTransferDisplayValue(
      Math.max(fromBeforeRaw - fromDeltaRaw, 0),
      fromSource,
    ),
    toAfter: formatTransferDisplayValue(
      toBeforeRaw + toDeltaRaw,
      selectedToAccount.value,
    ),
  };
});

// Internal Transfer handlers
const onFromTypeChange = () => {
  transferForm.value.fromTradingAccountId = null;
  transferForm.value.toTradingAccountId = null;
  transferForm.value.amount = null;
};

const onFromAccountChange = () => {
  transferForm.value.toTradingAccountId = null;
};

// Handle internal transfer
const handleInternalTransfer = async () => {
  if (!transferForm.value.fromType) {
    alert(t("transAlertSelectSource", "Please select a source"));
    return;
  }

  if (
    transferForm.value.fromType === "trading_account" &&
    !transferForm.value.fromTradingAccountId
  ) {
    alert(
      t(
        "transAlertSelectSourceTrading",
        "Please select a source trading account",
      ),
    );
    return;
  }

  if (!transferForm.value.toTradingAccountId) {
    alert(
      t(
        "transAlertSelectTargetTrading",
        "Please select a target trading account",
      ),
    );
    return;
  }

  if (!transferForm.value.amount || transferForm.value.amount <= 0) {
    alert(t("transAlertEnterValidAmount", "Please enter a valid amount"));
    return;
  }

  // Validate amount
  if (
    transferForm.value.fromType == "wallet" ||
    transferForm.value.fromType == "available_balance"
  ) {
    if (transferForm.value.amount > accountBalance.value) {
      alert(
        t(
          "transAlertAmountExceedBalance",
          "Amount cannot exceed wallet balance:",
        ) +
          " " +
          formatCurrency(accountBalance.value),
      );
      return;
    }
  } else if (selectedFromAccount.value) {
    if (
      transferForm.value.amount >
      (selectedFromAccount.value.availableBalance || 0)
    ) {
      alert(
        t(
          "transAlertAmountExceedBalance",
          "Amount cannot exceed wallet balance:",
        ) +
          " " +
          formatCurrency(selectedFromAccount.value.availableBalance || 0),
      );
      return;
    }
  }

  submitting.value = true;
  try {
    const response = await clientTransactionService.createInternalTransfer({
      fromType: transferForm.value.fromType,
      fromTradingAccountId: transferForm.value.fromTradingAccountId,
      toTradingAccountId: transferForm.value.toTradingAccountId,
      amount: transferForm.value.amount,
    });

    if (response.success) {
      const wasFromWallet =
        transferForm.value.fromType == "wallet" ||
        transferForm.value.fromType == "available_balance";
      transferConfirmation.value = buildTransferConfirmation(response);
      transferView.value = "confirm";
      // Reload balance if from wallet
      if (wasFromWallet) {
        await loadAvailableBalance();
      }
    }
  } catch (err) {
    alert(
      t("transAlertTransferFailed", "Failed to submit transfer:") +
        " " +
        (err.response?.data?.message || err.message),
    );
  } finally {
    submitting.value = false;
  }
};

// Handle deposit
// eslint-disable-next-line no-unused-vars
const handleDeposit = async () => {
  if (!depositForm.value.gatewayKey) {
    alert(t("transAlertSelectPaymentMethod", "Please select a payment method"));
    return;
  }

  if (!depositCurrencyOptions.value.length) {
    alert("No supported currencies are currently available.");
    return;
  }

  if (hasDepositCryptos.value && !depositForm.value.selectedCrypto) {
    alert(
      selectedDepositAssetType.value === "fiat"
        ? t(
            "transSelectSupportedCurrency",
            "Please select a supported currency",
          )
        : t("transAlertSelectCrypto", "Please select a cryptocurrency"),
    );
    return;
  }

  if (!depositForm.value.amount) {
    alert(t("transAlertEnterAmount", "Please enter an amount"));
    return;
  }

  if (!depositForm.value.targetAccountType) {
    alert(
      t("transSelectTradingAccountRequired", "Please select a trading account"),
    );
    return;
  }

  if (
    !validateGatewayAmountRange(
      depositForm.value.amount,
      depositGatewayMinAmount.value,
      depositGatewayMaxAmount.value,
      {
        noun: "Deposit amount",
        minLabel: "gateway minimum deposit",
        maxLabel: "gateway maximum deposit",
      },
    )
  ) {
    return;
  }

  // 验证每日限额
  if (depositLimits.value.dailyLimit > 0) {
    const totalAfterDeposit =
      depositStats.value.todayDeposits + depositForm.value.amount;
    if (totalAfterDeposit > depositLimits.value.dailyLimit) {
      const remaining =
        depositLimits.value.dailyLimit - depositStats.value.todayDeposits;
      alert(
        t(
          "transAlertDailyLimitExceeded",
          "Daily deposit limit exceeded. Remaining limit:",
        ) +
          " " +
          formatCurrency(remaining),
      );
      return;
    }
  }

  // 验证每月限额
  if (depositLimits.value.monthlyLimit > 0) {
    const totalAfterDeposit =
      depositStats.value.monthlyDeposits + depositForm.value.amount;
    if (totalAfterDeposit > depositLimits.value.monthlyLimit) {
      const remaining =
        depositLimits.value.monthlyLimit - depositStats.value.monthlyDeposits;
      alert(
        t(
          "transAlertMonthlyLimitExceeded",
          "Monthly deposit limit exceeded. Remaining limit:",
        ) +
          " " +
          formatCurrency(remaining),
      );
      return;
    }
  }

  await confirmDeposit();
};

// Confirm deposit
const confirmDeposit = async () => {
  if (!depositForm.value.gatewayKey) {
    alert(t("transAlertSelectPaymentMethod", "Please select a payment method"));
    return;
  }

  if (!depositIsMultiCurrency.value && !depositCurrencyOptions.value.length) {
    alert("No supported currencies are currently available.");
    return;
  }

  if (
    !depositIsMultiCurrency.value &&
    hasDepositCryptos.value &&
    !depositForm.value.selectedCrypto
  ) {
    alert(t("transAlertSelectCrypto", "Please select a cryptocurrency"));
    return;
  }

  if (!depositForm.value.amount) {
    alert(t("transAlertEnterAmount", "Please enter an amount"));
    return;
  }

  if (!depositForm.value.targetAccountType) {
    alert(
      t("transSelectTradingAccountRequired", "Please select a trading account"),
    );
    return;
  }

  if (
    !validateGatewayAmountRange(
      depositForm.value.amount,
      depositGatewayMinAmount.value,
      depositGatewayMaxAmount.value,
      {
        noun: "Deposit amount",
        minLabel: "gateway minimum deposit",
        maxLabel: "gateway maximum deposit",
      },
    )
  ) {
    return;
  }

  if (
    requiresDepositPaymentMethodId.value &&
    !resolvedDepositPaymentMethodId.value
  ) {
    alert(t("transAlertChannelNotFound", "Selected deposit channel not found"));
    return;
  }

  // 验证每日限额
  if (depositLimits.value.dailyLimit > 0) {
    const totalAfterDeposit =
      depositStats.value.todayDeposits + depositForm.value.amount;
    if (totalAfterDeposit > depositLimits.value.dailyLimit) {
      const remaining =
        depositLimits.value.dailyLimit - depositStats.value.todayDeposits;
      alert(
        t(
          "transAlertDailyLimitExceeded",
          "Daily deposit limit exceeded. Remaining limit:",
        ) +
          " " +
          formatCurrency(remaining),
      );
      return;
    }
  }

  // 验证每月限额
  if (depositLimits.value.monthlyLimit > 0) {
    const totalAfterDeposit =
      depositStats.value.monthlyDeposits + depositForm.value.amount;
    if (totalAfterDeposit > depositLimits.value.monthlyLimit) {
      const remaining =
        depositLimits.value.monthlyLimit - depositStats.value.monthlyDeposits;
      alert(
        t(
          "transAlertMonthlyLimitExceeded",
          "Monthly deposit limit exceeded. Remaining limit:",
        ) +
          " " +
          formatCurrency(remaining),
      );
      return;
    }
  }

  // 支持问题已经在 DepositPanel 内部通过具名 slot 渲染在 Confirm 按钮上方，这里做必填校验
  if (depositSupportQuestions.value.length > 0) {
    for (const question of depositSupportQuestions.value) {
      const rules = String(question.validationRules || "");
      if (
        rules.includes("required") &&
        !hasDepositSupportQuestionAnswer(question)
      ) {
        alert(`Please fill in ${getDepositQuestionLabel(question)}.`);
        return;
      }
    }
  }

  submitting.value = true;
  depositSupportFieldErrors.value = {};
  try {
    let response;

    // alchemy_pay 在没有 gateway 配置额外问题时走专用接口；其他情况统一走 createDeposit + supportData
    if (
      depositGatewayKey.value === "alchemy_pay" &&
      depositSupportQuestions.value.length === 0
    ) {
      response = await clientTransactionService.createAlchemyPayDeposit({
        paymentMethodId: resolvedDepositPaymentMethodId.value,
        tradingAccountId: depositForm.value.tradingAccountId,
        platformAccountId: depositForm.value.platformAccountId,
        amount: depositForm.value.amount,
        total: depositFeeSummary.value.totalAmount,
        currency:
          selectedCryptoInfo.value?.shortCode ||
          selectedCryptoInfo.value?.code ||
          "USD",
      });
    } else {
      const supportData = depositSupportQuestions.value.reduce(
        (acc, question) => {
          acc[question.name] = getDepositSupportQuestionSubmitValue(question);
          return acc;
        },
        {},
      );

      response = await clientTransactionService.createDeposit({
        gatewaySettingId: selectedDepositGateway.value?.id,
        tradingAccountId: depositForm.value.tradingAccountId,
        platformAccountId: depositForm.value.platformAccountId,
        amount: depositForm.value.amount,
        total: depositFeeSummary.value.totalAmount,
        currency:
          selectedCryptoInfo.value?.shortCode ||
          selectedCryptoInfo.value?.code ||
          "USD",
        supportData,
      });
    }

    if (response.success) {
      const payInfo = response?.data?.vexoraPayInfo;
      const hasVaPayInfo = !!(
        payInfo &&
        typeof payInfo === "object" &&
        (payInfo.virtualAccountNumber || payInfo.bankName)
      );
      const flashpayPayInfo = response?.data?.flashpayPayInfo;
      const hasFlashPayPendingInfo = !!(
        flashpayPayInfo &&
        typeof flashpayPayInfo === "object" &&
        (flashpayPayInfo.payDataType === "bankcard" ||
          flashpayPayInfo.codeImgUrl ||
          flashpayPayInfo.codeUrl)
      );
      const cvpayPayInfo = response?.data?.cvpayPayInfo;
      const hasCvPayPendingInfo = !!(
        cvpayPayInfo &&
        typeof cvpayPayInfo === "object" &&
        (cvpayPayInfo.codeUrl ||
          cvpayPayInfo.code ||
          (cvpayPayInfo.payDataType === "JSON" && cvpayPayInfo.json))
      );

      // VA / bankcard: show confirm with payment details instead of cashier redirect
      if (hasVaPayInfo || hasFlashPayPendingInfo || hasCvPayPendingInfo) {
        depositConfirmation.value = buildDepositConfirmation(response);
        depositView.value = "confirm";
        showDepositModal.value = false;
      } else {
        const redirected = await handleTransactionRedirectResponse(response);
        if (!redirected) {
          depositConfirmation.value = buildDepositConfirmation(response);
          depositView.value = "confirm";
          showDepositModal.value = false;
        }
      }

      // 从响应中更新余额和统计
      if (response.data?.availableBalance !== undefined) {
        accountBalance.value = response.data.availableBalance;
      }
      if (response.data?.depositStats) {
        depositStats.value = {
          todayDeposits: response.data.depositStats.todayDeposits || 0,
          monthlyDeposits: response.data.depositStats.monthlyDeposits || 0,
        };
      }
    }
  } catch (err) {
    const errorPayload = err.response?.data || {};
    const fieldErrors = extractDepositSupportFieldErrors(errorPayload);
    if (Object.keys(fieldErrors).length > 0) {
      depositSupportFieldErrors.value = fieldErrors;
      return;
    }
    alert(
      t("transAlertDepositFailed", "Failed to create deposit:") +
        " " +
        (errorPayload.message || err.message),
    );
  } finally {
    submitting.value = false;
  }
};

// Deposit币种变更
// eslint-disable-next-line no-unused-vars
const onDepositCryptoChange = () => {
  // 币种变更时，更新显示
};

// Source account change handler
const onSourceAccountChange = async () => {
  if (withdrawForm.value.sourceAccountType == "wallet") {
    withdrawForm.value.sourceTradingAccountId = "";
  } else if (withdrawForm.value.sourceAccountType.startsWith("trading_")) {
    const accountId = extractTradingAccountIdFromSelection(
      withdrawForm.value.sourceAccountType,
    );
    const matchedAccount = findTradingAccountById(accountId);
    withdrawForm.value.sourceTradingAccountId = matchedAccount
      ? String(matchedAccount.id)
      : "";
  } else {
    withdrawForm.value.sourceTradingAccountId = "";
  }
};

// Format BSB (XXX-XXX)
const formatBSB = (bsb) => {
  if (!bsb) return "";
  return bsb.replace(/(\d{3})(\d{3})/, "$1-$2");
};

// Mask account number (show only last 4 digits)
const maskAccountNumber = (accountNumber) => {
  if (!accountNumber) return "";
  if (accountNumber.length <= 4) {
    return "*".repeat(accountNumber.length);
  }
  return "*".repeat(accountNumber.length - 4) + accountNumber.slice(-4);
};

// Handle BSB input (only numbers, max 6 digits)
const handleBSBInput = (event) => {
  const value = event.target.value.replace(/\D/g, "").slice(0, 6);
  withdrawForm.value.newPaymentAccount.bsb = value;
};

// Handle account number input (only numbers)
const handleAccountNumberInput = (event) => {
  const value = event.target.value.replace(/\D/g, "");
  withdrawForm.value.newPaymentAccount.accountNumber = value;
};

// Handle withdrawal
const handleWithdrawal = async (options = {}) => {
  if (!withdrawForm.value.sourceAccountType) {
    alert(t("transAlertSelectSourceAccount", "Please select a source account"));
    return false;
  }

  if (!filteredWithdrawalCurrencyOptions.value.length) {
    alert(
      t(
        "transNoSupportedCurrencies",
        "No supported currencies are currently available.",
      ),
    );
    return false;
  }

  if (
    filteredWithdrawalCurrencyOptions.value.length &&
    !withdrawForm.value.selectedCrypto
  ) {
    alert(
      selectedWithdrawalAssetType.value === "fiat"
        ? t(
            "transSelectSupportedCurrency",
            "Please select a supported currency",
          )
        : t("transAlertSelectCrypto", "Please select a cryptocurrency"),
    );
    return false;
  }

  // 验证 OTP（仅在启用时检查）
  if (
    securitySettings.value.withdrawalOtpRequired &&
    !otpVerificationStatus.value.isVerified
  ) {
    alert(
      t(
        "transAlertVerifyOTPFirst",
        "Please verify your identity with OTP code first",
      ),
    );
    return false;
  }

  if (!withdrawForm.value.amount) {
    alert(t("transAlertEnterAmount", "Please enter an amount"));
    return false;
  }

  if (
    !validateGatewayAmountRange(
      withdrawForm.value.amount,
      withdrawalGatewayMinAmount.value,
      withdrawalGatewayMaxAmount.value,
      {
        noun: "Withdrawal amount",
        minLabel: "gateway minimum withdrawal",
        maxLabel: "gateway maximum withdrawal",
      },
    )
  ) {
    return false;
  }

  // 验证余额
  const resolveSourceAvailableUsd = (amount, source = {}) => {
    const scale = Number(source?.groupScale ?? source?.scale ?? 1);
    const normalizedScale = Number.isFinite(scale) && scale > 0 ? scale : 1;
    return Number(amount || 0) / normalizedScale;
  };

  let availableBalance = resolveSourceAvailableUsd(accountBalance.value, {
    unit: "USD",
    scale: 1,
  });
  if (withdrawForm.value.sourceAccountType == "wallet") {
    if (withdrawForm.value.amount > availableBalance) {
      alert(t("transAlertInsufficientBalance", "Insufficient balance"));
      return false;
    }
  } else if (withdrawForm.value.sourceTradingAccountId) {
    const selectedAccount = findTradingAccountById(
      withdrawForm.value.sourceTradingAccountId,
    );
    if (!selectedAccount) {
      alert(
        t(
          "transAlertTradingAccountNotFound",
          "Selected trading account not found",
        ),
      );
      return false;
    }
    availableBalance = resolveSourceAvailableUsd(
      selectedAccount.availableBalance || 0,
      selectedAccount,
    );
    if (withdrawForm.value.amount > availableBalance) {
      alert(
        t(
          "transAlertInsufficientAvailable",
          "Insufficient balance. Available:",
        ) +
          " " +
          formatCurrency(availableBalance),
      );
      return false;
    }
  }

  if (!confirm(`Confirm withdrawal of $${withdrawForm.value.amount}?`)) {
    return false;
  }

  const gatewaySettingId =
    options.gatewaySettingId ||
    withdrawalTemplateMeta.value?.gatewaySettingId ||
    withdrawGateways.value.find(
      (gateway) => gateway.gatewayKey === withdrawForm.value.gatewayKey,
    )?.id ||
    null;

  if (!gatewaySettingId) {
    alert("Missing gatewaySettingId for withdrawal.");
    return false;
  }

  submitting.value = true;
  try {
    const baseWithdrawalData = {
      amount: withdrawForm.value.amount,
      total: withdrawalFeeSummary.value.totalAmount,
      currency:
        selectedWithdrawalCurrencyInfo.value?.shortCode ||
        selectedWithdrawalCurrencyInfo.value?.code ||
        "USD",
      gatewaySettingId,
    };

    if (withdrawForm.value.sourceTradingAccountId) {
      baseWithdrawalData.tradingAccountId =
        withdrawForm.value.sourceTradingAccountId;
    }

    let response;
    if (options.withdrawalSubmissionId) {
      response = await clientTransactionService.createWithdrawal({
        ...baseWithdrawalData,
        withdrawalSubmissionId: options.withdrawalSubmissionId,
      });
    } else {
      response = await clientTransactionService.createWithdrawalSupportData({
        ...baseWithdrawalData,
        supportData: options.supportData || {},
      });
    }

    if (response.success) {
      withdrawalConfirmation.value = buildWithdrawalConfirmation(
        response,
        options,
      );
      withdrawForm.value = {
        sourceAccountType: "",
        sourceTradingAccountId: "",
        amount: null,
        newPaymentAccount: {
          legalName: "",
          bsb: "",
          accountNumber: "",
        },
      };

      // Reset OTP verification status
      if (securitySettings.value.withdrawalOtpRequired) {
        otpVerificationStatus.value.isVerified = false;
        otpVerificationStatus.value.otpCode = "";
        otpVerificationStatus.value.otpSent = false;
      }
      // 从响应中更新余额
      if (response.data?.availableBalance !== undefined) {
        accountBalance.value = response.data.availableBalance;
      }
      // 重新加载交易账户余额
      await loadTradingAccounts();
      withdrawView.value = "confirm";
      return true;
    }
  } catch (err) {
    alert(
      t("transAlertWithdrawalFailed", "Failed to submit withdrawal:") +
        " " +
        (err.response?.data?.message || err.message),
    );
  } finally {
    submitting.value = false;
  }
  return false;
};

// Save new wallet
const saveNewWallet = async () => {
  if (!newWalletForm.value.walletName || !newWalletForm.value.walletAddress) {
    alert(t("transAlertFillAllFields", "Please fill in all fields"));
    return;
  }

  if (!withdrawForm.value.selectedCrypto) {
    alert(
      t("transAlertSelectCryptoFirst", "Please select a cryptocurrency first"),
    );
    return;
  }

  savingWallet.value = true;
  try {
    const response = await clientTransactionService.createSavedWallet({
      walletName: newWalletForm.value.walletName,
      paymentMethodId: withdrawForm.value.selectedCrypto,
      walletAddress: newWalletForm.value.walletAddress,
    });

    if (response.success) {
      alert("✓ " + t("transAlertWalletSaved", "Wallet saved successfully!"));
      showAddWalletModal.value = false;
      newWalletForm.value = { walletName: "", walletAddress: "" };
      await loadSavedWallets();
    }
  } catch (err) {
    alert(
      t("transAlertSaveWalletFailed", "Failed to save wallet:") +
        " " +
        (err.response?.data?.message || err.message),
    );
  } finally {
    savingWallet.value = false;
  }
};

// Copy address
const copyAddress = async (text) => {
  try {
    await navigator.clipboard.writeText(text);
    copied.value = true;
    setTimeout(() => {
      copied.value = false;
    }, 2000);
  } catch (err) {
    alert(t("transAlertCopyFailed", "Failed to copy address"));
  }
};

const formatDateTime = (datetime) => {
  if (!datetime) return "-";
  const date = new Date(datetime);
  return date.toLocaleString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
};

// eslint-disable-next-line no-unused-vars
const getStatusLabel = (status) => {
  const labels = {
    pending: t("transStatusPending", "Pending"),
    processing: t("transProcessing", "Processing"),
    completed: t("transStatusCompleted", "Completed"),
    rejected: t("transRejected", "Rejected"),
    failed: t("transStatusFailed", "Failed"),
  };
  return labels[status] || status;
};

// Load available balance and deposit limits
const loadAvailableBalance = async () => {
  try {
    const response = await clientTransactionService.getAvailableBalance();
    if (response.success) {
      accountBalance.value = response.data.availableBalance || 0;

      // 从响应中获取存款限制和统计
      if (response.data.depositLimits) {
        depositLimits.value = {
          minimumAmount: response.data.depositLimits.minimumAmount || 10,
          maximumAmount: response.data.depositLimits.maximumAmount || 50000,
          dailyLimit: response.data.depositLimits.dailyLimit || 100000,
          monthlyLimit: response.data.depositLimits.monthlyLimit || 500000,
        };
      }

      // 从响应中获取存款统计
      if (response.data.depositStats) {
        depositStats.value = {
          todayDeposits: response.data.depositStats.todayDeposits || 0,
          monthlyDeposits: response.data.depositStats.monthlyDeposits || 0,
        };
      }
    }
  } catch (err) {
    console.error("Failed to load available balance:", err);
    accountBalance.value = 0;
    // 使用默认值
    depositLimits.value = {
      minimumAmount: 10,
      maximumAmount: 50000,
      dailyLimit: 100000,
      monthlyLimit: 500000,
    };
  }
};

// Load security settings
const loadSecuritySettings = async () => {
  try {
    const response = await clientTransactionService.getSecuritySettings();
    // console.log('Security settings response:', response);

    // 后端返回格式：{ success: true, data: { withdrawalOtpRequired: true, ... } }
    // 前端 API 拦截器已经提取了 response.data，所以这里 response 就是 { success: true, data: {...} }
    if (response.success) {
      // 确保正确映射设置字段，并显式转换布尔值
      securitySettings.value = {
        withdrawalOtpRequired: response.data.withdrawalOtpRequired || false,
        requireVerifiedWalletOnly:
          response.data.requireVerifiedWalletOnly || false,
        requireWithdrawalVerification:
          response.data.requireWithdrawalVerification || false,
        otpValidityMinutes: response.data.otpValidityMinutes || 10,
        verificationMaxFileSize: response.data.verificationMaxFileSize || 5,
      };
      // console.log('Loaded security settings:', securitySettings.value);

      // 如果不需要OTP验证，自动设置为已验证状态
      if (!securitySettings.value.withdrawalOtpRequired) {
        otpVerificationStatus.value.isVerified = true;
      }
    } else {
      // console.error('Security settings response missing success or data:', response);
    }
  } catch (err) {
    console.error("Failed to load security settings:", err);
    // 使用默认值
    securitySettings.value = {
      withdrawalOtpRequired: false,
      requireVerifiedWalletOnly: false,
      requireWithdrawalVerification: false,
      otpValidityMinutes: 10,
      verificationMaxFileSize: 5,
    };
    // 默认不需要OTP，设置为已验证
    otpVerificationStatus.value.isVerified = true;
  }
};

// Check OTP status on page load
const checkOTPStatus = async () => {
  try {
    // 如果不需要OTP验证，直接设置为已验证状态
    if (!securitySettings.value.withdrawalOtpRequired) {
      otpVerificationStatus.value.isVerified = true;
      return;
    }

    const response = await clientTransactionService.checkOTPStatus();
    console.log("OTP status response:", response);
    if (response.success && response.data) {
      // 更新验证状态
      otpVerificationStatus.value.isVerified = response.data.verified || false;
      if (response.data.requireVerifiedWalletOnly !== undefined) {
        securitySettings.value.requireVerifiedWalletOnly = Boolean(
          response.data.requireVerifiedWalletOnly,
        );
      }
    } else {
      // 默认未验证状态
      otpVerificationStatus.value.isVerified = false;
    }
  } catch (err) {
    console.error("Failed to check OTP status:", err);
    // 如果不需要OTP，出错时也设置为已验证
    if (!securitySettings.value.withdrawalOtpRequired) {
      otpVerificationStatus.value.isVerified = true;
    } else {
      otpVerificationStatus.value.isVerified = false;
    }
  }
};

// Request OTP
const requestOTP = async () => {
  submitting.value = true;
  try {
    const response = await clientTransactionService.requestWithdrawalOTP();

    if (response.success) {
      otpVerificationStatus.value.otpSent = true;
      otpVerificationStatus.value.expiresAt = new Date(response.data.expiresAt);

      // 启动倒计时
      startOTPTimer(response.data.validityMinutes * 3);

      alert(
        "✓ " +
          t("transAlertCodeSentTo", "Verification code sent to") +
          " " +
          response.data.data.email +
          ". " +
          t("transAlertCheckEmail", "Please check your email."),
      );
    }
  } catch (err) {
    alert(
      t("transAlertSendCodeFailed", "Failed to send verification code:") +
        " " +
        (err.response?.data?.message || err.message),
    );
  } finally {
    submitting.value = false;
  }
};

// Verify OTP
const verifyOTP = async () => {
  if (otpVerificationStatus.value.otpCode.length !== 6) {
    alert(
      t("transAlertValidSixDigitCode", "Please enter a valid 6-digit code"),
    );
    return;
  }

  submitting.value = true;
  try {
    const response = await clientTransactionService.verifyWithdrawalOTP(
      otpVerificationStatus.value.otpCode,
    );

    if (response.success) {
      otpVerificationStatus.value.isVerified = true;
      clearInterval(otpTimer.value);
      alert(
        "✓ " +
          t(
            "transAlertVerificationSuccess",
            "Verification successful! You can now proceed with withdrawal.",
          ),
      );

      // 加载已保存的钱包（账户验证相关代码已注释）
      if (selectedWithdrawCryptoInfo.value) {
        await loadSavedWallets();
        // await loadVerifiedAccounts(withdrawForm.value.selectedCrypto); // 已注释
      }
    }
  } catch (err) {
    const errorData = err.response?.data;
    let errorMsg = "Invalid verification code. ";

    if (errorData?.data?.remainingAttempts !== undefined) {
      errorMsg += `${errorData.data.remainingAttempts} attempts remaining.`;
    }

    alert(errorMsg);

    // 如果没有剩余次数，重置状态
    if (errorData?.data?.remainingAttempts === 0) {
      otpVerificationStatus.value.otpSent = false;
      otpVerificationStatus.value.otpCode = "";
      clearInterval(otpTimer.value);
    }
  } finally {
    submitting.value = false;
  }
};

// Start OTP countdown timer
const startOTPTimer = (seconds) => {
  otpVerificationStatus.value.remainingTime = seconds;

  clearInterval(otpTimer.value);

  otpTimer.value = setInterval(() => {
    otpVerificationStatus.value.remainingTime--;

    if (otpVerificationStatus.value.remainingTime <= 0) {
      clearInterval(otpTimer.value);
      otpVerificationStatus.value.otpSent = false;
      otpVerificationStatus.value.otpCode = "";
    }
  }, 1000);
};

// Format time (seconds to MM:SS)
const formatTime = (seconds) => {
  const mins = Math.floor(seconds / 60);
  const secs = seconds % 60;
  return `${mins}:${secs.toString().padStart(2, "0")}`;
};

// 账户验证相关函数（已注释 - 只保留OTP验证码流程）
/*
// Load verified accounts
const loadVerifiedAccounts = async (paymentMethodId) => {
  try {
    const response = await clientTransactionService.getVerifiedAccounts(paymentMethodId);
    if (response.success) {
      verifiedAccounts.value = response.data || [];
      return verifiedAccounts.value;
    }
  } catch (err) {
    console.error('Failed to load verified accounts:', err);
  }
  return [];
};

// Start verification process
const startVerification = () => {
  const crypto = selectedWithdrawCryptoInfo.value;
  if (!crypto) return;

  const accountType = (crypto.methodType === 'bank') ? 'bank' : 'crypto';

  verificationForm.value = {
    paymentMethodId: withdrawForm.value.selectedCrypto,
    accountType: accountType,
    bankName: '',
    accountNumber: '',
    accountHolderName: '',
    swiftCode: '',
    bankStatementFile: null,
    walletName: '',
    walletAddress: '',
    walletNetwork: '',
    walletScreenshotFile: null,
    notes: ''
  };

  showVerificationModal.value = true;
};

// Handle file upload
const handleFileUpload = (event, fileType) => {
  const file = event.target.files[0];
  if (!file) return;

  const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
  if (!allowedTypes.includes(file.type)) {
    alert(t('transAlertUploadImageOrPDF', 'Please upload an image (JPG, PNG, GIF) or PDF file'));
    event.target.value = '';
    return;
  }

  const maxSize = securitySettings.value.verificationMaxFileSize || 5;
  if (file.size > maxSize * 1024 * 1024) {
    alert(t('transAlertFileSizeLessThan', 'File size must be less than') + ' ' + maxSize + 'MB');
    event.target.value = '';
    return;
  }

  if (fileType === 'bankStatement') {
    verificationForm.value.bankStatementFile = file;
  } else if (fileType === 'walletScreenshot') {
    verificationForm.value.walletScreenshotFile = file;
  }
};

// Submit verification
const submitVerification = async () => {
  if (verificationForm.value.accountType === 'bank') {
    if (!verificationForm.value.bankName || !verificationForm.value.accountNumber ||
        !verificationForm.value.accountHolderName || !verificationForm.value.bankStatementFile) {
      alert(t('transAlertFillBankAndUpload', 'Please fill in all bank details and upload bank statement'));
      return;
    }
  } else if (verificationForm.value.accountType === 'crypto') {
    if (!verificationForm.value.walletName || !verificationForm.value.walletAddress ||
        !verificationForm.value.walletScreenshotFile) {
      alert(t('transAlertFillWalletAndUpload', 'Please fill in wallet details and upload verification screenshot'));
      return;
    }
  }

  uploadingVerification.value = true;

  try {
    const formData = new FormData();
    formData.append('paymentMethodId', verificationForm.value.paymentMethodId);
    formData.append('accountType', verificationForm.value.accountType);

    if (verificationForm.value.accountType === 'bank') {
      const accountName = verificationForm.value.accountHolderName || verificationForm.value.bankName || 'Bank Account';
      formData.append('accountName', accountName);
      formData.append('bankName', verificationForm.value.bankName);
      formData.append('accountNumber', verificationForm.value.accountNumber);
      formData.append('accountHolderName', verificationForm.value.accountHolderName);
      formData.append('swiftCode', verificationForm.value.swiftCode || '');
      formData.append('bankStatementFile', verificationForm.value.bankStatementFile);
    } else {
      const accountName = verificationForm.value.walletName || 'Crypto Wallet';
      formData.append('accountName', accountName);
      formData.append('walletName', verificationForm.value.walletName);
      formData.append('walletAddress', verificationForm.value.walletAddress);
      formData.append('walletNetwork', verificationForm.value.walletNetwork || '');
      formData.append('walletScreenshotFile', verificationForm.value.walletScreenshotFile);
    }

    if (verificationForm.value.notes) {
      formData.append('notes', verificationForm.value.notes);
    }

    const response = await clientTransactionService.submitAccountVerification(formData);

    if (response.success) {
      alert('✓ ' + t('transAlertVerificationSubmitted', 'Verification submitted successfully! Your account will be reviewed within 24-48 hours.'));
      showVerificationModal.value = false;
      needsVerification.value = false;
      await loadVerifiedAccounts(verificationForm.value.paymentMethodId);
    }
  } catch (err) {
    alert(t('transAlertVerificationSubmitFailed', 'Failed to submit verification:') + ' ' + (err.response?.data?.message || err.message));
  } finally {
    uploadingVerification.value = false;
  }
};
*/

// Watch activeTab changes to check OTP when switching to withdraw
watch(activeTab, async (newTab) => {
  if (isTransactionResultRoute.value) {
    return;
  }

  if (newTab === "deposit") {
    depositView.value = "pre-step";
    await loadDisplayContents("deposit");
    if (!paymentGateways.value.length && !loadingPaymentGateways.value) {
      await loadClientGateways();
    }
  } else if (newTab === "withdraw") {
    withdrawView.value = "pre-kyc";
    await loadDisplayContents("withdrawal");
    if (!withdrawGateways.value.length && !loadingWithdrawGateways.value) {
      await loadClientGateways();
    }
    await checkOTPStatus();
  } else if (newTab === "transfer") {
    await loadDisplayContents("internal_transfer");
  }
});

watch(
  depositSupportQuestions,
  () => {
    resetDepositSupportDataForm();
  },
  { immediate: true },
);

watch(
  () => depositForm.value.gatewayKey,
  async (newGatewayKey, oldGatewayKey) => {
    if (newGatewayKey === oldGatewayKey) {
      return;
    }

    await onDepositGatewayChange();
  },
);

watch(
  tradingAccounts,
  (accounts) => {
    if (!Array.isArray(accounts) || !accounts.length) {
      if (depositForm.value.targetAccountType !== "wallet") {
        depositForm.value.targetAccountType = "";
        depositForm.value.tradingAccountId = "";
        depositForm.value.platformAccountId = "";
      }
      return;
    }

    if (depositForm.value.targetAccountType === "wallet") {
      depositForm.value.tradingAccountId = "";
      depositForm.value.platformAccountId = "";
      return;
    }

    const currentExists = accounts.some(
      (account) =>
        `trading_${account.id}` === depositForm.value.targetAccountType,
    );

    if (!currentExists) {
      depositForm.value.targetAccountType = "";
      depositForm.value.tradingAccountId = "";
      depositForm.value.platformAccountId = "";
      return;
    }

    const selectedAccount = findTradingAccountById(
      depositForm.value.tradingAccountId,
    );
    depositForm.value.platformAccountId =
      resolveDepositPlatformAccountId(selectedAccount);
  },
  { immediate: true },
);

watch(
  [
    () => depositView.value,
    () => depositCryptos.value.map((currency) => currency.id).join("|"),
    selectedDepositAssetType,
  ],
  async ([currentView, , assetType]) => {
    if (currentView !== "details") {
      depositRateLookup.value = {
        loaded: false,
        byCurrency: {},
      };
      return;
    }

    await loadExchangeRatesForCurrencies({
      type: assetType,
      transactionType: "deposit",
      currencies: depositCryptos.value.map(
        (currency) => currency.shortCode || currency.code || currency.id,
      ),
      lookupRef: depositRateLookup,
      targetRef: depositExchangeRateInfo,
      selectedCurrency: depositForm.value.selectedCrypto,
    });
  },
  { immediate: true },
);

watch(
  [
    () => withdrawView.value,
    () =>
      withdrawalCurrencyOptions.value.map((currency) => currency.id).join("|"),
    selectedWithdrawalAssetType,
  ],
  async ([currentView, , assetType]) => {
    if (currentView !== "details") {
      withdrawalRateLookup.value = {
        loaded: false,
        byCurrency: {},
      };
      return;
    }

    await loadExchangeRatesForCurrencies({
      type: assetType,
      transactionType: "withdraw",
      currencies: withdrawalCurrencyOptions.value.map(
        (currency) => currency.shortCode || currency.code || currency.id,
      ),
      lookupRef: withdrawalRateLookup,
      targetRef: withdrawalExchangeRateInfo,
      selectedCurrency: withdrawForm.value.selectedCrypto,
    });
  },
  { immediate: true },
);

watch(
  [() => depositForm.value.selectedCrypto, selectedDepositAssetType],
  ([selectedCurrency, assetType]) => {
    setSelectedExchangeRate({
      targetRef: depositExchangeRateInfo,
      lookupRef: depositRateLookup,
      currency: selectedCurrency || "USD",
      type: assetType,
    });
  },
  { immediate: true },
);

watch(
  [() => withdrawForm.value.selectedCrypto, selectedWithdrawalAssetType],
  ([selectedCurrency, assetType]) => {
    setSelectedExchangeRate({
      targetRef: withdrawalExchangeRateInfo,
      lookupRef: withdrawalRateLookup,
      currency: selectedCurrency || "USD",
      type: assetType,
    });
  },
  { immediate: true },
);

watch(
  depositCurrencyOptions,
  (currencies) => {
    if (!Array.isArray(currencies) || !currencies.length) {
      depositForm.value.selectedCrypto = null;
      return;
    }

    const currentSelectionExists = currencies.some(
      (currency) => currency.id === depositForm.value.selectedCrypto,
    );
    if (!currentSelectionExists) {
      depositForm.value.selectedCrypto = currencies[0].id;
    }
  },
  { immediate: true },
);

watch(
  filteredWithdrawalCurrencyOptions,
  (currencies) => {
    if (!Array.isArray(currencies) || !currencies.length) {
      withdrawForm.value.selectedCrypto = "";
      return;
    }

    const currentSelectionExists = currencies.some(
      (currency) => currency.id === withdrawForm.value.selectedCrypto,
    );
    if (!currentSelectionExists) {
      withdrawForm.value.selectedCrypto = currencies[0].id;
    }
  },
  { immediate: true },
);

watch(
  [() => route.name, () => route.query],
  () => {
    if (!isTransactionResultRoute.value) {
      return;
    }

    if (transactionResultType.value === "withdrawal") {
      activeTab.value = "withdraw";
      withdrawalConfirmation.value = buildWithdrawalResultFromRoute();
      withdrawView.value = "confirm";
      depositConfirmation.value = null;
      transferConfirmation.value = null;
      return;
    }

    if (transactionResultType.value === "internal_transfer") {
      activeTab.value = "transfer";
      transferConfirmation.value = buildTransferResultFromRoute();
      transferView.value = "confirm";
      depositConfirmation.value = null;
      withdrawalConfirmation.value = null;
      return;
    }

    activeTab.value = "deposit";
    depositConfirmation.value = buildDepositResultFromRoute();
    depositView.value = "confirm";
    withdrawalConfirmation.value = null;
    transferConfirmation.value = null;
  },
  { immediate: true },
);

// Load data on mount
onMounted(async () => {
  if (clientAuthStore.token && !clientAuthStore.user) {
    await clientAuthStore.fetchUser();
  }

  // webview 入口（/app/deposit 等）：每次进入都强制再拉一次最新 user/kycStatus，
  // 避免 KYC 状态滞后让 gate 误判通过；未通过时下面的交易数据加载对用户也看不到，无影响
  if (isWebView.value && clientAuthStore.token) {
    await clientAuthStore.fetchUser();
    if (!clientAuthStore.isKycApproved) {
      // 未通过 KYC：模板已渲染 KycRequiredNotice，无需继续加载交易数据
      return;
    }
  }

  hydrateDepositContactForm();

  // webview 入口可能直接进入 withdraw / transfer，按当前 activeTab 加载对应说明文案
  const initialDisplayScope =
    activeTab.value === "withdraw"
      ? "withdrawal"
      : activeTab.value === "transfer"
        ? "internal_transfer"
        : "deposit";

  await Promise.all([
    loadAvailableBalance(),
    loadClientGateways(),
    loadTradingAccounts(),
    loadSecuritySettings(),
    loadDisplayContents(initialDisplayScope),
  ]);

  // 如果切换到withdraw tab，检查OTP状态
  if (activeTab.value === "withdraw") {
    await checkOTPStatus();
  }
});

// Cleanup timer on component unmount
onUnmounted(() => {
  if (otpTimer.value) {
    clearInterval(otpTimer.value);
  }

  stopTransactionRedirectTimer();
});
</script>

<style scoped>
.client-transactions-page {
  padding: 40px 20px;
  max-width: 1200px;
  margin: 0 auto;
}

/* Balance Card */
.balance-card {
  background: var(--color-brand-solid);
  border-radius: var(--radius-xl);
  padding: 30px;
  margin-bottom: 30px;
  color: white;
  box-shadow: 0 10px 30px rgba(var(--color-brand-rgb), 0.3);
  position: relative;
  overflow: hidden;
}

.balance-card::before {
  content: "";
  position: absolute;
  top: -50%;
  right: -10%;
  width: 300px;
  height: 300px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 50%;
}

.balance-content {
  position: relative;
  z-index: 1;
}

.balance-row {
  display: flex;
  gap: 40px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}

.balance-item {
  flex: 1;
  min-width: 200px;
}

.balance-label {
  font-size: 14px;
  opacity: 0.9;
  margin-bottom: 8px;
}

.balance-amount {
  font-size: 36px;
  font-weight: 700;
}

.balance-actions {
  display: flex;
  gap: 15px;
}

.balance-btn {
  padding: 12px 24px;
  border: 1px solid white;
  border-radius: var(--radius-md);
  background: rgba(255, 255, 255, 0.2);
  color: white;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    opacity 0.3s ease,
    transform 0.3s ease;
  display: flex;
  align-items: center;
  gap: 8px;
}

.balance-btn:hover {
  background: rgba(255, 255, 255, 0.3);
  transform: translateY(-2px);
}

/* Tab Navigation */
.tab-navigation {
  display: flex;
  gap: 10px;
  margin-bottom: 30px;
  background: var(--color-surface);
  padding: 10px;
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.tab-btn {
  flex: 1;
  padding: 14px 20px;
  border: 1px solid transparent;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    opacity 0.3s ease,
    transform 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  color: var(--color-text);
}

.tab-btn span {
  min-width: 0;
}

.tab-btn:hover {
  background: var(--color-surface-soft);
  border-color: var(--color-border);
}

.tab-btn.active {
  background: var(--color-brand-solid);
  color: white;
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.3);
}

/* Transaction Cards */
.transaction-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
  gap: 20px;
}

.transaction-cards.transfer-layout {
  grid-template-columns: minmax(0, 1.35fr) minmax(320px, 0.95fr);
  align-items: stretch;
}

.transaction-card {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: 25px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    opacity 0.3s ease,
    transform 0.3s ease;
}

.transaction-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
}

.transfer-form-card {
  min-width: 0;
}

.transfer-info-card {
  align-self: stretch;
  height: 100%;
  display: flex;
  flex-direction: column;
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 1px solid var(--color-surface-soft);
}

.card-title {
  font-size: 18px;
  font-weight: 700;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0;
}

.card-title i {
  color: var(--color-brand);
}

.transfer-inline-summary {
  margin-bottom: 18px;
  border: 1px solid var(--color-border);
  border-radius: 14px;
  background: var(--color-surface-soft);
  padding: 16px 18px;
}

.transfer-inline-summary-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
  font-size: 14px;
  font-weight: 700;
  color: var(--color-ink);
}

.transfer-inline-summary-header i {
  color: var(--color-brand);
}

.transfer-inline-summary-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
  gap: 14px;
  align-items: center;
}

.transfer-inline-summary-side {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
  font-size: 14px;
  color: var(--color-muted);
}

.transfer-inline-summary-side strong {
  color: var(--color-ink);
  font-size: 14px;
}

.transfer-inline-summary-kicker {
  font-size: 14px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--color-muted);
}

.transfer-inline-summary-change {
  font-size: 18px;
  font-weight: 800;
}

.transfer-inline-summary-change.negative {
  color: var(--color-danger);
}

.transfer-inline-summary-change.positive {
  color: var(--color-success);
}

.transfer-inline-summary-divider {
  width: 36px;
  height: 36px;
  border-radius: 999px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-brand-soft);
  color: var(--color-brand);
  font-size: 14px;
}

.tips-list.compact {
  gap: 12px;
  flex: 1;
}

.tips-list.compact .tip-item {
  padding: 14px 16px;
}

.card-badge {
  background: var(--color-brand-soft);
  color: var(--color-brand);
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 14px;
  font-weight: 600;
}

.card-badge.warning {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

/* Form */
.form-group {
  margin-bottom: 20px;
}

.form-label {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 8px;
}

.form-input,
.form-select {
  width: 100%;
  padding: 12px 16px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    opacity 0.3s ease,
    transform 0.3s ease;
}

.form-input:focus,
.form-select:focus {
  border-color: var(--color-brand);
  box-shadow: none;
}

.form-help {
  font-size: 14px;
  color: var(--color-muted);
  margin-top: 6px;
  display: block;
}

.error-text {
  font-size: 14px;
  color: var(--color-danger);
  margin-top: 6px;
}

.input-error {
  border-color: var(--color-danger) !important;
  box-shadow: none;
}

.redirect-modal {
  max-width: 420px;
}

.redirect-modal-body {
  text-align: center;
  padding: 32px 24px;
}

.redirect-spinner {
  font-size: 36px;
  color: var(--color-brand);
  margin-bottom: 16px;
}

.redirect-modal-body h3 {
  margin: 0 0 10px;
  color: var(--color-ink);
}

.redirect-modal-body p {
  margin: 0;
  color: var(--color-muted);
  font-size: 15px;
}

.phone-input-row {
  display: flex;
  gap: 10px;
  align-items: flex-start;
}

.phone-country-code {
  width: 200px;
  padding: 12px 14px;
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border-strong);
  background: var(--color-surface);
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease;
  font-size: 14px;
  color: var(--color-ink);
  font-family: inherit;
  cursor: pointer;
}

.phone-country-code:focus {
  border-color: var(--color-brand);
  box-shadow: none;
}

.phone-input {
  flex: 1;
  padding: 12px 14px;
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border-strong);
  background: var(--color-surface);
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease;
  font-size: 14px;
  color: var(--color-ink);
  font-family: inherit;
}

.phone-input:focus {
  border-color: var(--color-brand);
  box-shadow: none;
}

.input-with-icon {
  position: relative;
}

.input-icon {
  position: absolute;
  left: 16px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-faint);
}

.input-with-icon input,
.input-with-icon :deep(input) {
  padding-left: 45px;
}

/* Payment Methods */
.payment-methods {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
}

.payment-method {
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 16px 12px;
  text-align: center;
  cursor: pointer;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    opacity 0.3s ease,
    transform 0.3s ease;
}

.payment-method:hover {
  border-color: var(--color-border-strong);
}

.payment-method.selected {
  border-color: var(--color-brand);
  background: var(--color-brand-soft);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.2);
}

.payment-method i {
  font-size: 28px;
  color: var(--color-brand);
  margin-bottom: 8px;
}

.payment-method-label {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 2px;
}

.payment-method-sublabel {
  font-size: 14px;
  color: var(--color-muted);
}

/* Crypto Options */
.crypto-options {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 12px;
}

.crypto-option {
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 16px 12px;
  text-align: center;
  cursor: pointer;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    opacity 0.3s ease,
    transform 0.3s ease;
}

.crypto-option:hover {
  border-color: var(--color-border-strong);
}

.crypto-option.selected {
  border-color: var(--color-brand);
  background: var(--color-brand-soft);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.2);
}

.crypto-option i {
  font-size: 28px;
  color: var(--color-brand);
  margin-bottom: 8px;
}

.crypto-option-label {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 2px;
}

.crypto-option-sublabel {
  font-size: 14px;
  color: var(--color-muted);
}

/* Saved Wallets */
.saved-wallets {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.wallet-item {
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 15px;
  display: flex;
  align-items: center;
  gap: 12px;
  cursor: pointer;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    opacity 0.3s ease,
    transform 0.3s ease;
}

.wallet-item:hover {
  border-color: var(--color-border-strong);
}

.wallet-item.selected {
  border-color: var(--color-brand);
  background: var(--color-brand-soft);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.2);
}

.wallet-icon {
  width: 40px;
  height: 40px;
  border-radius: var(--radius-md);
  background: var(--color-brand-solid);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 18px;
}

.wallet-info {
  flex: 1;
  min-width: 0;
}

.wallet-name {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 4px;
}

.wallet-address {
  font-size: 14px;
  color: var(--color-muted);
  font-family: "Courier New", monospace;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wallet-badge {
  background: var(--color-success-soft);
  color: var(--color-success);
  padding: 4px 10px;
  border-radius: var(--radius-lg);
  font-size: 14px;
  font-weight: 600;
}

.btn-add-wallet-inline {
  float: right;
  padding: 4px 12px;
  background: var(--color-brand-soft);
  color: var(--color-brand);
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
    opacity 0.3s ease,
    transform 0.3s ease;
}

.btn-add-wallet-inline:hover {
  background: var(--color-brand-solid);
  color: white;
}

.btn-use-new-address {
  width: 100%;
  padding: 12px;
  border: 1px dashed var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-brand);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    opacity 0.3s ease,
    transform 0.3s ease;
}

.btn-use-new-address:hover {
  border-color: var(--color-brand);
  background: var(--color-brand-soft);
}

/* Tips */
.tips-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.tip-item {
  padding: 15px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-md);
  border-left: 1px solid var(--color-brand);
  display: flex;
  gap: 12px;
  align-items: start;
}

.tip-item i {
  font-size: 20px;
  color: var(--color-brand);
  margin-top: 2px;
}

.tip-item strong {
  display: block;
  color: var(--color-ink);
  margin-bottom: 4px;
  font-size: 14px;
}

.tip-item p {
  font-size: 14px;
  color: var(--color-muted);
  margin: 0;
}

/* Info Box */
.info-box {
  background: var(--color-brand-soft);
  border-left: 1px solid var(--color-brand);
  padding: 12px 16px;
  border-radius: var(--radius-md);
  margin-bottom: 20px;
}

.info-box p {
  color: var(--color-text);
  font-size: 14px;
  line-height: 1.6;
  margin: 0;
}

.info-box.warning {
  background: var(--color-danger-soft);
  border-left-color: var(--color-danger-border);
}

/* Button */
.btn {
  padding: 14px 28px;
  border-radius: var(--radius-md);
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    opacity 0.3s ease,
    transform 0.3s ease;
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
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-secondary {
  background: var(--color-surface);
  color: var(--color-brand);
  border: 1px solid var(--color-brand);
}

.btn-secondary:hover {
  background: var(--color-brand-soft);
}

.btn-block {
  width: 100%;
  justify-content: center;
}

.loading-state,
.empty-state {
  text-align: center;
  padding: 60px 20px;
  color: var(--color-faint);
}

.loading-state i,
.empty-state i {
  font-size: 48px;
  margin-bottom: 15px;
  display: block;
}

/* Modal */
.modal-overlay {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.6);
  z-index: 9998;
  align-items: center;
  justify-content: center;
}

.modal-overlay.active {
  display: flex;
}

.modal-container {
  background: var(--color-surface);
  border-radius: var(--radius-xl);
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  width: 90%;
  max-width: 600px;
  max-height: 85vh;
  overflow: hidden;
}

.modal-header {
  padding: 25px 30px;
  border-bottom: 1px solid var(--color-border);
  background: var(--color-brand-solid);
  color: white;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.modal-header h2 {
  font-size: 20px;
  font-weight: 700;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 12px;
}

.modal-close-btn {
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
  font-size: 20px;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    opacity 0.3s ease,
    transform 0.3s ease;
}

.modal-close-btn:hover {
  background: rgba(255, 255, 255, 0.3);
  transform: rotate(90deg);
}

.modal-body {
  padding: 30px;
  max-height: calc(85vh - 180px);
  overflow-y: auto;
}

.modal-footer {
  padding: 20px 30px;
  border-top: 1px solid var(--color-border);
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  background: var(--color-surface-soft);
}

.deposit-support-textarea {
  resize: vertical;
  min-height: 100px;
  padding: 12px 14px;
}

/* DepositPanel slot 里的支持问题内联块（替代原本的 deposit-support-modal） */
.support-questions-inline {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 16px;
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  margin-bottom: 16px;
}

.support-questions-inline .form-group {
  margin-bottom: 0;
}

.support-questions-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 700;
  color: var(--color-ink);
}

.support-questions-title i {
  color: var(--color-brand);
}

.qr-code-display {
  text-align: center;
  padding: 20px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-md);
  margin-bottom: 20px;
}

.qr-placeholder {
  width: 200px;
  height: 200px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 15px;
  color: var(--color-faint);
  font-size: 64px;
}

.crypto-address-display {
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 12px 16px;
  font-family: "Courier New", monospace;
  font-size: 14px;
  color: var(--color-ink);
  word-break: break-all;
  position: relative;
}

.copy-btn {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  background: var(--color-brand-solid);
  color: white;
  border: none;
  padding: 6px 12px;
  border-radius: var(--radius-sm);
  cursor: pointer;
  font-size: 14px;
  font-weight: 600;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    opacity 0.3s ease,
    transform 0.3s ease;
}

.copy-btn:hover {
  background: var(--color-brand-strong);
}

/* OTP Verification */
.otp-verification-section {
  margin-bottom: 30px;
}

.security-notice {
  background: var(--color-brand-soft);
  border-left: 1px solid var(--color-brand);
  padding: 16px 20px;
  border-radius: var(--radius-md);
  display: flex;
  gap: 15px;
  align-items: start;
  margin-bottom: 20px;
}

.security-notice i {
  font-size: 24px;
  color: var(--color-brand);
  margin-top: 2px;
}

.security-notice strong {
  display: block;
  color: var(--color-ink);
  margin-bottom: 6px;
  font-size: 15px;
}

.security-notice p {
  font-size: 14px;
  color: var(--color-text);
  margin: 0;
  line-height: 1.6;
}

.otp-request-box,
.otp-verify-box {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 25px;
  text-align: center;
}

.otp-request-box p {
  margin-bottom: 20px;
  color: var(--color-text);
  font-size: 14px;
}

.otp-sent-notice {
  background: var(--color-success-soft);
  border-left: 1px solid var(--color-success);
  padding: 12px 16px;
  border-radius: var(--radius-md);
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.otp-sent-notice i {
  font-size: 20px;
  color: var(--color-success);
}

.otp-sent-notice p {
  font-size: 14px;
  color: var(--color-success);
  margin: 0;
  text-align: left;
}

.otp-input {
  font-size: 16px !important;
  text-align: left;
  letter-spacing: 0;
  font-weight: 500;
  font-family: inherit;
  flex: 1;
}

.otp-input-group {
  display: flex;
  gap: 10px;
  align-items: stretch;
}

.btn-send-otp,
.btn-verify-otp {
  white-space: nowrap;
  min-width: 100px;
  padding: 12px 16px;
}

.btn-send-otp {
  background: var(--color-surface-muted);
  color: var(--color-text);
  border: 1px solid var(--color-border-strong);
}

.btn-send-otp:hover:not(:disabled) {
  background: var(--color-border);
  color: var(--color-ink);
}

.btn-verify-otp {
  background: var(--color-brand-solid);
  color: white;
  border: none;
}

.btn-verify-otp:hover:not(:disabled) {
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.3);
}

.form-help.error {
  color: var(--color-danger);
  font-weight: 600;
}

.otp-actions {
  display: flex;
  gap: 12px;
  margin-top: 20px;
  justify-content: center;
}

.otp-actions .btn {
  flex: 1;
  max-width: 200px;
}

/* Verification Code Input Group */
.verification-code-group {
  display: flex;
  gap: 10px;
  align-items: stretch;
}

.verification-code-input {
  flex: 1;
}

.send-code-btn {
  white-space: nowrap;
  min-width: 120px;
}

.payment-account-form {
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 20px;
  margin-top: 10px;
}

.payment-account-form .form-group {
  margin-bottom: 20px;
}

.payment-account-form .form-group:last-child {
  margin-bottom: 0;
}

.required {
  color: var(--color-danger);
  margin-left: 2px;
}

.form-help.success {
  color: var(--color-success);
  font-weight: 500;
}

.form-help.success i {
  margin-right: 5px;
}

/* Account Verification Styles */
.verification-required-box {
  background: var(--color-danger-soft);
  border: 1px solid var(--color-danger-border);
  border-radius: var(--radius-md);
  padding: 20px;
  margin-bottom: 20px;
}

.verification-notice {
  display: flex;
  gap: 15px;
  align-items: start;
  margin-bottom: 20px;
}

.verification-notice i {
  font-size: 32px;
  color: var(--color-danger-border);
  margin-top: 2px;
  flex-shrink: 0;
}

.verification-notice strong {
  display: block;
  color: var(--color-ink);
  margin-bottom: 6px;
  font-size: 16px;
}

.verification-notice p {
  font-size: 14px;
  color: var(--color-text);
  margin: 0;
  line-height: 1.6;
}

.verified-accounts-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.verified-account-item {
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 16px;
  display: flex;
  align-items: center;
  gap: 15px;
  cursor: pointer;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    opacity 0.3s ease,
    transform 0.3s ease;
}

.verified-account-item:hover {
  border-color: var(--color-border-strong);
  transform: translateX(5px);
}

.verified-account-item.selected {
  border-color: var(--color-brand);
  background: var(--color-brand-soft);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.2);
}

.account-icon {
  width: 50px;
  height: 50px;
  border-radius: var(--radius-md);
  background: var(--color-brand-solid);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 22px;
  flex-shrink: 0;
}

.account-info {
  flex: 1;
  min-width: 0;
}

.account-name {
  font-size: 15px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 6px;
}

.account-identifier {
  font-size: 14px;
  color: var(--color-muted);
  font-family: "Courier New", monospace;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  margin-bottom: 6px;
}

.verified-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: var(--color-success-soft);
  color: var(--color-success);
  padding: 3px 10px;
  border-radius: var(--radius-lg);
  font-size: 14px;
  font-weight: 600;
}

.verified-badge i {
  /* @font-floor-exempt: visual-only status glyph */
  font-size: 10px;
}

.modal-container-large {
  max-width: 700px;
}

.verification-section-title {
  font-size: 16px;
  color: var(--color-ink);
  margin-bottom: 20px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--color-border);
  display: flex;
  align-items: center;
  gap: 10px;
}

.verification-section-title i {
  color: var(--color-brand);
}

.form-file-input {
  width: 100%;
  padding: 12px 16px;
  border: 1px dashed var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  cursor: pointer;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    opacity 0.3s ease,
    transform 0.3s ease;
  background: var(--color-surface-soft);
}

.form-file-input:hover {
  border-color: var(--color-border-strong);
  background: var(--color-surface);
}

.form-file-input:focus {
  border-color: var(--color-brand);
  border-style: solid;
  box-shadow: none;
}

.file-preview {
  margin-top: 12px;
  padding: 12px 16px;
  background: var(--color-brand-soft);
  border: 1px solid var(--color-brand);
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--color-brand);
  font-size: 14px;
}

.file-preview i {
  font-size: 20px;
}

.form-textarea {
  width: 100%;
  padding: 12px 16px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    opacity 0.3s ease,
    transform 0.3s ease;
  font-family: inherit;
  resize: vertical;
}

.form-textarea:focus {
  border-color: var(--color-brand);
  box-shadow: none;
}

.info-banner {
  background: var(--color-brand-soft);
  border-left: 1px solid var(--color-brand);
  padding: 16px 20px;
  border-radius: var(--radius-md);
  margin-bottom: 25px;
}

.info-banner-content {
  display: flex;
  gap: 15px;
  align-items: start;
}

.info-banner-icon {
  font-size: 24px;
  color: var(--color-brand);
  margin-top: 2px;
  flex-shrink: 0;
}

.info-banner-text {
  font-size: 14px;
  color: var(--color-text);
  line-height: 1.6;
}

.info-banner-text strong {
  color: var(--color-ink);
}

@media (max-width: 768px) {
  .client-transactions-page {
    padding: 20px 15px;
  }

  .balance-actions {
    display: none;
  }

  /* 移动端余额卡片：缩小内边距、间距和金额字号，避免占据过大屏幕空间 */
  .balance-card {
    padding: 16px 18px;
    margin-bottom: 16px;
    border-radius: var(--radius-lg);
  }

  .balance-card::before {
    width: 180px;
    height: 180px;
  }

  .balance-row {
    gap: 16px;
    margin-bottom: 0;
  }

  .balance-item {
    min-width: 0;
  }

  .balance-label {
    font-size: 14px;
    margin-bottom: 4px;
  }

  .balance-amount {
    font-size: 22px;
  }

  .tab-navigation {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
    padding: 8px;
    margin-bottom: 20px;
  }

  .tab-btn {
    min-width: 0;
    min-height: 72px;
    padding: 10px 8px;
    flex-direction: column;
    gap: 6px;
    border-radius: var(--radius-lg);
    font-size: 14px;
    line-height: 1.2;
    text-align: center;
  }

  .tab-btn i {
    font-size: 16px;
  }

  .tab-btn span {
    display: block;
    word-break: break-word;
  }

  .transaction-cards {
    grid-template-columns: 1fr;
  }

  .transaction-cards.transfer-layout {
    grid-template-columns: 1fr;
  }

  .transfer-inline-summary-row {
    grid-template-columns: 1fr;
  }

  .transfer-inline-summary-divider {
    margin: 0 auto;
    transform: rotate(90deg);
  }

  .payment-methods {
    grid-template-columns: repeat(2, 1fr);
  }

  .crypto-options {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
