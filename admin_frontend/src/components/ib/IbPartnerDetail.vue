<template>
  <div class="detail-content">
    <!-- IB & Contact Information -->
    <div class="detail-section full-width">
      <h3>
        <div class="section-header">
          <div class="section-title">
            <i class="fas fa-building"></i> IB & Contact Information
          </div>
          <button
            class="btn-save"
            :class="{ active: hasInfoChanges }"
            :disabled="!hasInfoChanges || saving"
            @click="saveInfo"
          >
            <i
              class="fas"
              :class="saving ? 'fa-spinner fa-spin' : 'fa-save'"
            ></i>
            Save
          </button>
        </div>
      </h3>
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px">
        <!-- Left Column -->
        <div>
          <div class="detail-field">
            <span class="detail-label">Company Name</span>
            <div class="detail-value-wrapper">
              <span
                class="detail-value"
                :contenteditable="editingFields.companyName"
                @blur="handleFieldBlur('companyName', $event)"
                @input="handleFieldInput('companyName', $event)"
                :data-original="originalIb.companyName"
                >{{ localIb.companyName || "-" }}</span
              >
              <button
                class="btn-edit"
                @click="enableEdit('companyName')"
                :title="editingFields.companyName ? 'Save' : 'Edit'"
              >
                <i class="fas fa-edit"></i>
              </button>
            </div>
          </div>
          <div class="detail-field">
            <span class="detail-label">Client Alias</span>
            <!-- clientAlias 由 IB 本人在客户端 Name IB 处维护，后台只展示 -->
            <span class="detail-value">{{ localIb.clientAlias || "-" }}</span>
          </div>
          <div class="detail-field">
            <span class="detail-label">Admin Alias</span>
            <div class="detail-value-wrapper">
              <span
                class="detail-value"
                :contenteditable="editingFields.adminAlias"
                @blur="handleFieldBlur('adminAlias', $event)"
                @input="handleFieldInput('adminAlias', $event)"
                :data-original="originalIb.adminAlias || ''"
                >{{ localIb.adminAlias || "-" }}</span
              >
              <button
                class="btn-edit"
                @click="enableEdit('adminAlias')"
                :title="editingFields.adminAlias ? 'Save' : 'Edit'"
              >
                <i class="fas fa-edit"></i>
              </button>
            </div>
          </div>
          <div class="detail-field">
            <span class="detail-label">IB Code</span>
            <span class="detail-value">{{ ibPartner.ibCode }}</span>
          </div>
          <div class="detail-field">
            <span class="detail-label">Registration Date</span>
            <span class="detail-value">{{
              formatDate(ibPartner.registrationDate)
            }}</span>
          </div>
          <div class="detail-field">
            <span class="detail-label">Country</span>
            <div
              style="
                display: flex;
                align-items: center;
                gap: 10px;
                flex: 1;
                justify-content: flex-end;
              "
            >
              <select
                :value="getCurrentCountryCode()"
                @change="
                  (e) => {
                    localIb.country = e.target.value;
                    markInfoChanged();
                  }
                "
                class="detail-value-select"
              >
                <option value="" disabled>Select Country</option>
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
          <div
            class="detail-field"
            style="border-bottom: none; padding-bottom: 8px"
          >
            <span class="detail-label">IB Referral URL</span>
            <div class="detail-value-wrapper">
              <span
                class="detail-value"
                :contenteditable="editingFields.referralUrl"
                @blur="handleFieldBlur('referralUrl', $event)"
                @input="handleFieldInput('referralUrl', $event)"
                :data-original="
                  localIb.referralUrl ||
                  props.ibPartner.referralUrl ||
                  getReferralUrl()
                "
                style="
                  color: var(--color-brand);
                  font-family: &quot;Courier New&quot;, monospace;
                  font-size: 14px;
                "
                >{{
                  localIb.referralUrl ||
                  props.ibPartner.referralUrl ||
                  getReferralUrl()
                }}</span
              >
              <button
                class="btn-edit"
                @click="enableEdit('referralUrl')"
                :title="editingFields.referralUrl ? 'Save' : 'Edit'"
              >
                <i class="fas fa-edit"></i>
              </button>
              <button
                class="btn-edit"
                @click="copyReferralUrl()"
                title="Copy URL"
                style="margin-left: 4px"
              >
                <i class="fas fa-copy"></i>
              </button>
            </div>
          </div>
          <div style="padding: 8px 0 12px 0; border-bottom: 1px solid #f0f0f0">
            <div
              style="
                background: var(--color-brand-soft);
                padding: 8px 12px;
                border-radius: var(--radius-sm);
                border-left: 3px solid var(--color-brand);
              "
            >
              <div
                style="
                  font-size: 14px;
                  color: var(--color-text);
                  line-height: 1.5;
                "
              >
                <i
                  class="fas fa-info-circle"
                  style="color: var(--color-brand); margin-right: 4px"
                ></i>
                <strong>Unique referral link</strong> for this IB to share with
                potential clients. Auto-generated based on IB code.
              </div>
            </div>
          </div>
          <div class="detail-field">
            <span class="detail-label">Status</span>
            <select
              v-model="localIb.status"
              @change="markInfoChanged"
              class="detail-value-select"
            >
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="pending">Pending</option>
            </select>
          </div>
        </div>

        <!-- Right Column -->
        <div>
          <div class="detail-field">
            <span class="detail-label">Contact Person</span>
            <div class="detail-value-wrapper">
              <span
                class="detail-value"
                :contenteditable="editingFields.contactPerson"
                @blur="handleFieldBlur('contactPerson', $event)"
                @input="handleFieldInput('contactPerson', $event)"
                :data-original="originalIb.contactPerson"
                >{{ localIb.contactPerson || "-" }}</span
              >
              <button
                class="btn-edit"
                @click="enableEdit('contactPerson')"
                :title="editingFields.contactPerson ? 'Save' : 'Edit'"
              >
                <i class="fas fa-edit"></i>
              </button>
            </div>
          </div>
          <div class="detail-field">
            <span class="detail-label">Email</span>
            <div class="detail-value-wrapper">
              <span
                class="detail-value"
                :contenteditable="editingFields.contactEmail"
                @blur="handleFieldBlur('contactEmail', $event)"
                @input="handleFieldInput('contactEmail', $event)"
                :data-original="originalIb.contactEmail"
                >{{ localIb.contactEmail || "-" }}</span
              >
              <button
                class="btn-edit"
                @click="enableEdit('contactEmail')"
                :title="editingFields.contactEmail ? 'Save' : 'Edit'"
              >
                <i class="fas fa-edit"></i>
              </button>
            </div>
          </div>
          <div class="detail-field">
            <span class="detail-label">Phone</span>
            <div class="detail-value-wrapper">
              <span
                class="detail-value"
                :contenteditable="editingFields.contactPhone"
                @blur="handleFieldBlur('contactPhone', $event)"
                @input="handleFieldInput('contactPhone', $event)"
                :data-original="
                  originalIb.contactPhone || ibPartner.clientPhone
                "
                >{{
                  localIb.contactPhone || ibPartner.clientPhone || "-"
                }}</span
              >
              <button
                class="btn-edit"
                @click="enableEdit('contactPhone')"
                :title="editingFields.contactPhone ? 'Save' : 'Edit'"
              >
                <i class="fas fa-edit"></i>
              </button>
            </div>
          </div>
          <div class="detail-field">
            <span class="detail-label">Address</span>
            <div class="detail-value-wrapper">
              <span
                class="detail-value"
                :contenteditable="editingFields.address"
                @blur="handleFieldBlur('address', $event)"
                @input="handleFieldInput('address', $event)"
                :data-original="originalIb.address"
                >{{ localIb.address || "-" }}</span
              >
              <button
                class="btn-edit"
                @click="enableEdit('address')"
                :title="editingFields.address ? 'Save' : 'Edit'"
              >
                <i class="fas fa-edit"></i>
              </button>
            </div>
          </div>
          <div class="detail-field">
            <span class="detail-label">Website</span>
            <div class="detail-value-wrapper">
              <span
                class="detail-value"
                :contenteditable="editingFields.website"
                @blur="handleFieldBlur('website', $event)"
                @input="handleFieldInput('website', $event)"
                :data-original="originalIb.website"
                >{{ localIb.website || "-" }}</span
              >
              <button
                class="btn-edit"
                @click="enableEdit('website')"
                :title="editingFields.website ? 'Save' : 'Edit'"
              >
                <i class="fas fa-edit"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- IB Commission Rules Assignment -->
    <div class="detail-section full-width">
      <h3>
        <div class="section-header">
          <div class="section-title">
            <i class="fas fa-file-invoice-dollar"></i> IB Commission Rules
            Assignment
          </div>
        </div>
      </h3>

      <div class="info-banner">
        <i class="fas fa-info-circle"></i>
        <strong>Modify Rules:</strong>
        <span
          >You can modify the rules. Changes will be saved immediately.</span
        >
      </div>

      <!-- Step 1: IB Tier Assignment (Read-only) -->
      <div class="setup-step">
        <div class="step-title">
          <i class="fas fa-sitemap"></i> Step 1: Assign Agent Tier Level
        </div>

        <div class="info-note">
          <strong><i class="fas fa-info-circle"></i> Tier Selection:</strong>
          The agent tier level for this IB. Tiers are pre-configured in IB Tier
          Template.
        </div>

        <div class="tier-selection-box">
          <label class="form-label">Current Agent Tier Level</label>
          <div class="tier-display">
            <div class="tier-display-content">
              <i class="fas fa-check-circle" style="color: #48bb78"></i>
              <span class="tier-display-text">
                {{ getCurrentTierDisplay() }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Step 2: Available IB Rules -->
      <div class="setup-step">
        <div class="step-title">
          <i class="fas fa-list-alt"></i> Step 2: Assign Commission Rules
        </div>

        <div class="info-note">
          <strong><i class="fas fa-info-circle"></i> Commission Rules:</strong>
          Select rules that will apply to the configured tiers. You can assign
          different rules for different product types.
        </div>

        <div class="rules-selection-box">
          <div
            class="rule-checkbox"
            v-for="rule in commissionRules"
            :key="rule.id"
          >
            <input
              type="checkbox"
              :id="`rule-${rule.id}`"
              :value="rule.id"
              v-model="selectedRuleIds"
              @change="markRuleChanged"
            />
            <label :for="`rule-${rule.id}`">
              <div class="rule-name">
                <i class="fas" :class="getRuleIcon(rule.ruleType)"></i>
                {{ rule.ruleName }}
              </div>
              <div class="rule-meta">
                {{ formatPaymentCycle(rule.paymentCycle) }} •
                {{ formatCurrency(rule.minimumPayout) }} min payout •
                {{ rule.productCount }} Products •
                <span class="active-badge">Active</span>
              </div>
            </label>
          </div>
        </div>

        <!-- 按Rules分别显示配置 -->
        <div
          v-for="ruleId in selectedRuleIds"
          :key="ruleId"
          class="rule-config-section"
        >
          <div class="rule-config-header">
            <div class="rule-config-title">
              <i
                class="fas"
                :class="getRuleIcon(getRule(ruleId)?.ruleType)"
              ></i>
              {{ getRule(ruleId)?.ruleName }}
            </div>
          </div>

          <!-- Payment Settings -->
          <div class="rule-payment-settings">
            <h4><i class="fas fa-calendar-alt"></i> Payment Settings</h4>
            <div class="payment-settings-grid">
              <div class="payment-field">
                <label>Payment Cycle</label>
                <select
                  v-model="rulePaymentSettings[ruleId].paymentCycle"
                  @change="handlePaymentCycleChange(ruleId)"
                  class="form-select"
                >
                  <option value="realtime">Real-time</option>
                  <option value="daily">Daily</option>
                  <option value="weekly">Weekly</option>
                  <option value="biweekly">Bi-weekly</option>
                  <option value="monthly">Monthly</option>
                  <option value="quarterly">Quarterly</option>
                </select>
              </div>
              <div class="payment-field">
                <label>Payment Day</label>
                <!-- Payment Day 根据 Payment Cycle 动态显示 -->
                <input
                  v-if="rulePaymentSettings[ruleId].paymentCycle === 'realtime'"
                  type="text"
                  value="Immediate"
                  class="form-input"
                  disabled
                  style="
                    background: var(--color-surface-soft);
                    color: var(--color-faint);
                    cursor: not-allowed;
                  "
                />
                <select
                  v-else-if="
                    rulePaymentSettings[ruleId].paymentCycle === 'daily'
                  "
                  v-model="rulePaymentSettings[ruleId].paymentDay"
                  @change="markPaymentChanged(ruleId)"
                  class="form-select"
                >
                  <option value="everyday">Every Day</option>
                  <option value="weekdays">Weekdays Only (Mon-Fri)</option>
                  <option value="weekends">Weekends Only (Sat-Sun)</option>
                </select>
                <select
                  v-else-if="
                    rulePaymentSettings[ruleId].paymentCycle === 'weekly'
                  "
                  v-model="rulePaymentSettings[ruleId].paymentDay"
                  @change="markPaymentChanged(ruleId)"
                  class="form-select"
                >
                  <option value="Monday">Monday</option>
                  <option value="Tuesday">Tuesday</option>
                  <option value="Wednesday">Wednesday</option>
                  <option value="Thursday">Thursday</option>
                  <option value="Friday">Friday</option>
                  <option value="Saturday">Saturday</option>
                  <option value="Sunday">Sunday</option>
                </select>
                <select
                  v-else-if="
                    rulePaymentSettings[ruleId].paymentCycle === 'biweekly'
                  "
                  v-model="rulePaymentSettings[ruleId].paymentDay"
                  @change="markPaymentChanged(ruleId)"
                  class="form-select"
                >
                  <option value="1-15">1st & 15th of month</option>
                  <option value="5-20">5th & 20th of month</option>
                  <option value="10-25">10th & 25th of month</option>
                  <option value="15-30">15th & Last day of month</option>
                </select>
                <select
                  v-else-if="
                    rulePaymentSettings[ruleId].paymentCycle === 'monthly'
                  "
                  v-model="rulePaymentSettings[ruleId].paymentDay"
                  @change="markPaymentChanged(ruleId)"
                  class="form-select"
                >
                  <option value="1">1st of month</option>
                  <option value="5">5th of month</option>
                  <option value="10">10th of month</option>
                  <option value="15">15th of month</option>
                  <option value="20">20th of month</option>
                  <option value="25">25th of month</option>
                  <option value="last">Last day of month</option>
                </select>
                <select
                  v-else-if="
                    rulePaymentSettings[ruleId].paymentCycle === 'quarterly'
                  "
                  v-model="rulePaymentSettings[ruleId].paymentDay"
                  @change="markPaymentChanged(ruleId)"
                  class="form-select"
                >
                  <option value="1">1st of quarter</option>
                  <option value="15">15th of quarter</option>
                  <option value="last">Last day of quarter</option>
                </select>
                <input
                  v-else
                  type="text"
                  v-model="rulePaymentSettings[ruleId].paymentDay"
                  @input="markPaymentChanged(ruleId)"
                  class="form-input"
                  placeholder="Enter payment day"
                />
              </div>
              <div class="payment-field">
                <label>Min. Payout Amount</label>
                <div style="display: flex; align-items: center; gap: 10px">
                  <input
                    type="number"
                    v-model.number="rulePaymentSettings[ruleId].minimumPayout"
                    @input="markPaymentChanged(ruleId)"
                    class="form-input"
                    step="0.01"
                    min="0"
                  />
                  <span style="color: var(--color-muted); font-size: 14px"
                    >USD</span
                  >
                </div>
              </div>
              <div class="payment-field">
                <label>Currency</label>
                <select
                  v-model="rulePaymentSettings[ruleId].payoutCurrency"
                  @change="markPaymentChanged(ruleId)"
                  class="form-select"
                >
                  <option value="USD">USD</option>
                  <option value="EUR">EUR</option>
                  <option value="GBP">GBP</option>
                  <option value="JPY">JPY</option>
                  <option value="AUD">AUD</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Product Commission Configuration -->
          <div class="rule-products-section">
            <h4>
              <div class="section-header">
                <div class="section-title">
                  <i class="fas fa-chart-line"></i> Product Commission
                  Configuration
                </div>
                <button class="btn btn-success" @click="addProduct(ruleId)">
                  <i class="fas fa-plus"></i> Add Product
                </button>
              </div>
            </h4>

            <div class="table-wrapper">
              <table class="product-commission-table">
                <thead>
                  <tr>
                    <th style="min-width: 200px">Product</th>
                    <th style="min-width: 150px">Commission Type</th>
                    <th style="min-width: 120px">Rate</th>
                    <th style="min-width: 120px">Additional</th>
                    <th style="min-width: 100px">Min. Volume</th>
                    <th style="width: 80px">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="(product, index) in ruleProducts[ruleId] || []"
                    :key="index"
                  >
                    <td>
                      <select
                        v-model="product.productName"
                        @change="markProductChanged(ruleId)"
                        class="form-select"
                      >
                        <option value="">Select Product...</option>
                        <optgroup
                          label="Securities"
                          v-if="allSecurities.length > 0"
                        >
                          <option
                            v-for="security in allSecurities"
                            :key="'sec-' + security.id"
                            :value="security.securityName"
                          >
                            {{ security.securityName }}
                          </option>
                        </optgroup>
                        <optgroup
                          label="Symbols"
                          v-if="customSymbols.length > 0"
                        >
                          <option
                            v-for="symbol in customSymbols"
                            :key="'sym-' + symbol.id"
                            :value="symbol.symbolName"
                          >
                            {{ symbol.symbolName }}
                          </option>
                        </optgroup>
                      </select>
                    </td>
                    <td>
                      <select
                        v-model="product.commissionType"
                        @change="markProductChanged(ruleId)"
                        class="form-select"
                      >
                        <option value="per_lot">Per Lot</option>
                        <option value="percentage">Percentage</option>
                        <option value="per_trade">Per Trade</option>
                        <option value="cashback">Cash Back</option>
                        <option value="hybrid">Hybrid</option>
                      </select>
                    </td>
                    <td>
                      <input
                        type="number"
                        v-model.number="product.commissionRate"
                        @input="markProductChanged(ruleId)"
                        class="form-input"
                        step="0.01"
                        min="0"
                        placeholder="Rate"
                      />
                    </td>
                    <td>
                      <input
                        type="number"
                        v-model.number="product.additionalRate"
                        @input="markProductChanged(ruleId)"
                        class="form-input"
                        step="0.01"
                        min="0"
                        placeholder="Bonus"
                        :disabled="product.commissionType === 'cashback'"
                      />
                    </td>
                    <td>
                      <input
                        type="text"
                        v-model="product.minimumVolume"
                        @input="markProductChanged(ruleId)"
                        class="form-input"
                        placeholder="Min Vol"
                      />
                    </td>
                    <td style="text-align: center">
                      <button
                        class="btn-icon btn-delete"
                        @click="removeProduct(ruleId, index)"
                        title="Delete"
                      >
                        <i class="fas fa-trash"></i>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Enable Additional Rules & Tiers -->
            <div class="toggle-additional-rules">
              <label class="toggle-label">
                <span>Enable Additional Rules & Tiers</span>
                <div
                  class="toggle-switch"
                  :class="{ active: additionalRulesEnabled[ruleId] }"
                  @click="toggleAdditionalRules(ruleId)"
                ></div>
              </label>
              <p>
                Enable volume-based tiers and additional commission rules for
                this package
              </p>
            </div>

            <!-- Configure Additional Rules & Tiers -->
            <div
              v-if="additionalRulesEnabled[ruleId]"
              class="additional-rule-section"
            >
              <div class="section-header">
                <div class="section-title">
                  <i class="fas fa-table"></i> Configure Additional Rules &
                  Tiers
                </div>
                <button
                  class="btn btn-success"
                  @click="addAdditionalRule(ruleId)"
                >
                  <i class="fas fa-plus"></i> Add Additional Rule
                </button>
              </div>

              <div class="table-wrapper">
                <table class="product-commission-table">
                  <thead>
                    <tr>
                      <th style="min-width: 200px">Product</th>
                      <th style="min-width: 150px">Rule Type</th>
                      <th style="min-width: 120px">Value</th>
                      <th style="min-width: 150px">Condition</th>
                      <th style="min-width: 150px">Commission Tiers</th>
                      <th style="width: 80px">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="(rule, index) in ruleAdditionalRules[ruleId] || []"
                      :key="index"
                    >
                      <td>
                        <select
                          v-model="rule.productName"
                          @change="markAdditionalRulesChanged(ruleId)"
                          class="form-select"
                        >
                          <option value="">Select Product...</option>
                          <optgroup
                            label="Securities"
                            v-if="allSecurities.length > 0"
                          >
                            <option
                              v-for="security in allSecurities"
                              :key="'sec-' + security.id"
                              :value="security.securityName"
                            >
                              {{ security.securityName }}
                            </option>
                          </optgroup>
                          <optgroup
                            label="Symbols"
                            v-if="customSymbols.length > 0"
                          >
                            <option
                              v-for="symbol in customSymbols"
                              :key="'sym-' + symbol.id"
                              :value="symbol.symbolName"
                            >
                              {{ symbol.symbolName }}
                            </option>
                          </optgroup>
                        </select>
                      </td>
                      <td>
                        <select
                          v-model="rule.ruleType"
                          @change="updateAdditionalRuleColumns(ruleId, index)"
                          class="form-select"
                        >
                          <option value="bonus_commission">
                            Bonus Commission
                          </option>
                          <option value="volume_tiers">
                            Volume-based Tiers
                          </option>
                          <option value="volume_multiplier">
                            Volume Multiplier
                          </option>
                          <option value="performance_bonus">
                            Performance Bonus
                          </option>
                          <option value="cash_rebate">Cash Rebate</option>
                        </select>
                      </td>
                      <td>
                        <input
                          v-if="rule.ruleType !== 'volume_tiers'"
                          type="number"
                          v-model.number="rule.ruleValue"
                          @input="markAdditionalRulesChanged(ruleId)"
                          class="form-input"
                          step="0.01"
                          min="0"
                          :placeholder="getValuePlaceholder(rule.ruleType)"
                        />
                        <input
                          v-else
                          type="text"
                          :value="
                            formatNumber(
                              rule.tierCount ||
                                (rule.tiers && rule.tiers.length) ||
                                0,
                            ) + ' Tiers'
                          "
                          disabled
                          class="form-input"
                          placeholder="Managed via Tiers"
                        />
                      </td>
                      <td>
                        <select
                          v-if="rule.ruleType !== 'volume_tiers'"
                          v-model="rule.ruleCondition"
                          @change="markAdditionalRulesChanged(ruleId)"
                          class="form-select"
                        >
                          <option value="">Select threshold...</option>
                          <option value=">500">Volume > 500 lots/month</option>
                          <option value=">1000">
                            Volume > 1000 lots/month
                          </option>
                          <option value=">2000">
                            Volume > 2000 lots/month
                          </option>
                          <option value=">5000">
                            Volume > 5000 lots/month
                          </option>
                          <option value="custom">✏️ Custom Condition...</option>
                        </select>
                        <select v-else disabled class="form-select">
                          <option value="auto">Auto (based on volume)</option>
                        </select>
                      </td>
                      <td>
                        <button
                          v-if="rule.ruleType === 'volume_tiers'"
                          class="btn btn-manage-tiers"
                          @click="openManageTiersModal(ruleId, index)"
                        >
                          <i class="fas fa-edit"></i> Manage ({{
                            formatNumber(
                              rule.tierCount ||
                                (rule.tiers && rule.tiers.length) ||
                                0,
                            )
                          }})
                        </button>
                        <button v-else disabled class="btn btn-disabled">
                          <i class="fas fa-ban"></i> N/A
                        </button>
                      </td>
                      <td style="text-align: center">
                        <button
                          class="btn-icon btn-delete"
                          @click="removeAdditionalRule(ruleId, index)"
                          title="Delete"
                        >
                          <i class="fas fa-trash"></i>
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Save Rules Button at the bottom -->
      <div class="step-2-footer">
        <button
          class="btn-save"
          :class="{ active: hasChanges }"
          :disabled="!hasChanges || saving"
          @click="handleSave"
        >
          <i class="fas" :class="saving ? 'fa-spinner fa-spin' : 'fa-save'"></i>
          Save Rules
        </button>
      </div>
    </div>

    <!-- Manage Tiers Modal -->
    <Teleport to="body">
      <div v-if="showTiersModal" class="modal-overlay" @click="closeTiersModal">
        <div class="modal-container" @click.stop>
          <div class="modal-header">
            <h3><i class="fas fa-table"></i> Manage Commission Tiers</h3>
            <button class="modal-close" @click="closeTiersModal">×</button>
          </div>

          <div class="modal-body">
            <div class="tiers-list">
              <div
                v-for="(tier, index) in editingTiers"
                :key="index"
                class="tier-edit-item"
              >
                <div class="tier-edit-header">
                  <span class="tier-edit-level">Tier {{ tier.tierLevel }}</span>
                  <button
                    v-if="editingTiers.length > 1"
                    class="btn-icon btn-delete"
                    @click="removeTierEdit(index)"
                  >
                    <i class="fas fa-trash"></i>
                  </button>
                </div>
                <div class="tier-edit-content">
                  <div class="tier-edit-field">
                    <label>Tier Name</label>
                    <input
                      type="text"
                      v-model="tier.tierName"
                      class="form-input"
                    />
                  </div>
                  <div class="tier-edit-field">
                    <label>Commission Rate</label>
                    <input
                      type="number"
                      v-model.number="tier.commissionRate"
                      step="0.01"
                      min="0"
                      class="form-input"
                    />
                  </div>
                  <div class="tier-edit-field">
                    <label>Min. Volume (lots)</label>
                    <input
                      type="number"
                      v-model.number="tier.minimumVolume"
                      step="0.01"
                      min="0"
                      class="form-input"
                    />
                  </div>
                  <div class="tier-edit-field">
                    <label>Max. Volume</label>
                    <input
                      type="text"
                      v-model="tier.maximumVolume"
                      class="form-input"
                      placeholder="Unlimited"
                    />
                  </div>
                </div>
              </div>
            </div>

            <button type="button" class="btn-add-tier" @click="addTierEdit">
              <i class="fas fa-plus"></i> Add Another Tier
            </button>
          </div>

          <div class="modal-footer">
            <button class="btn btn-secondary" @click="closeTiersModal">
              Cancel
            </button>
            <button class="btn btn-primary" @click="saveTiersModal">
              <i class="fas fa-save"></i> Save Tiers
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Performance Metrics -->
    <div class="detail-section">
      <h3><i class="fas fa-chart-line"></i> Performance Metrics</h3>
      <div class="detail-field">
        <span class="detail-label">Total Clients</span>
        <span class="detail-value"
          >{{ formatNumber(ibPartner.totalClients) }} Clients</span
        >
      </div>
      <div class="detail-field">
        <span class="detail-label">Active Clients</span>
        <span class="detail-value"
          >{{ formatNumber(ibPartner.activeClients) }} Clients</span
        >
      </div>
      <div class="detail-field">
        <span class="detail-label">Total Trading Volume</span>
        <span class="detail-value">{{
          formatCurrency(ibPartner.totalTradingVolume)
        }}</span>
      </div>
      <div class="detail-field">
        <span class="detail-label">Average Client Value</span>
        <span class="detail-value">{{
          formatCurrency(calculateAvgClientValue())
        }}</span>
      </div>
    </div>

    <!-- Documents & Contracts -->
    <div class="detail-section full-width">
      <h3><i class="fas fa-file-contract"></i> Documents & Contracts</h3>

      <!-- Loading State -->
      <div v-if="documentsLoading" class="loading-state">
        <i class="fas fa-spinner fa-spin"></i>
        <p>Loading documents...</p>
      </div>

      <!-- Documents Grid -->
      <div v-else class="documents-grid">
        <div
          v-for="doc in documents"
          :key="doc.id"
          class="document-card"
          @click="viewDocument(doc)"
        >
          <div class="document-card-header">
            <div class="document-icon">
              <i :class="getDocumentIcon(doc.documentType)"></i>
            </div>
            <div class="document-info">
              <div class="document-title">{{ doc.title }}</div>
              <div class="document-date">
                <i class="fas fa-calendar"></i>
                Signed on {{ formatDate(doc.signedAt) }}
              </div>
            </div>
          </div>

          <div class="document-meta">
            <div class="document-meta-item">
              <i class="fas fa-file-pdf"></i>
              <span>PDF</span>
            </div>
            <div class="document-meta-item">
              <i class="fas fa-tag"></i>
              <span>IB Application</span>
            </div>
            <div class="document-status signed">
              <i class="fas fa-check-circle"></i>
              Signed
            </div>
          </div>

          <div class="document-actions">
            <button class="btn-doc btn-view" @click.stop="viewDocument(doc)">
              <i class="fas fa-eye"></i> View
            </button>
            <button
              class="btn-doc btn-download"
              @click.stop="downloadDocument(doc)"
            >
              <i class="fas fa-download"></i> Download
            </button>
          </div>
        </div>

        <!-- Empty State -->
        <div v-if="documents.length === 0" class="empty-state">
          <i class="fas fa-file-alt"></i>
          <p>No documents found</p>
        </div>
      </div>
    </div>

    <!-- Document Viewer Modal -->
    <div v-if="showDocumentModal" class="modal" @click="closeDocumentModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h2>
            <i class="fas fa-file-alt"></i>
            {{ currentDocument ? currentDocument.title : "Document Preview" }}
          </h2>
          <span class="close" @click="closeDocumentModal">&times;</span>
        </div>

        <div class="modal-body">
          <div class="document-preview">
            <h3>{{ currentDocument ? currentDocument.title : "" }}</h3>
            <div
              class="document-preview-content font-floor-content"
              v-html="currentDocument ? currentDocument.content : ''"
            ></div>
          </div>

          <!-- Signature Section -->
          <div class="document-signature">
            <h4><i class="fas fa-signature"></i> Digital Signature</h4>
            <div class="signature-info-row">
              <div class="signature-field">
                <label>Full Name</label>
                <div class="signature-value">
                  {{
                    ibPartner.companyName || ibPartner.contactPerson || "N/A"
                  }}
                </div>
              </div>
              <div class="signature-field">
                <label>Email Address</label>
                <div class="signature-value">
                  {{ ibPartner.contactEmail || "N/A" }}
                </div>
              </div>
              <div class="signature-field">
                <label>IB Code</label>
                <div class="signature-value">
                  {{ ibPartner.ibCode || "N/A" }}
                </div>
              </div>
              <div class="signature-field">
                <label>Date & Time Signed</label>
                <div class="signature-value">
                  {{
                    currentDocument
                      ? formatDate(currentDocument.signedAt)
                      : "N/A"
                  }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button
            class="btn-modal btn-modal-secondary"
            @click="closeDocumentModal"
          >
            <i class="fas fa-times"></i> Close
          </button>
          <button
            class="btn-modal btn-modal-primary"
            @click="downloadCurrentDocument"
          >
            <i class="fas fa-download"></i> Download PDF
          </button>
        </div>
      </div>
    </div>

    <!-- IB Network Relationship Graph -->
    <div class="detail-section full-width">
      <h3>
        <i class="fas fa-project-diagram"></i> IB Network Relationship Graph ({{
          formatNumber(ibPartner.totalClients)
        }}
        Total)
      </h3>

      <div class="info-banner">
        <i class="fas fa-info-circle"></i>
        <strong>Mouse Controls:</strong> Drag to pan • Scroll to zoom • Click
        [+] to expand nodes • Double-click to reset view
      </div>

      <!-- Zoom Level Indicator -->
      <div v-if="showZoomIndicator" class="zoom-indicator">
        <i class="fas fa-search"></i> {{ Math.round(zoomLevel * 100) }}%
      </div>

      <div
        class="network-container"
        ref="networkContainer"
        :class="{ dragging: isDragging }"
        @mousedown="handleMouseDown"
        @wheel="handleWheel"
        @dblclick="resetView"
      >
        <div
          class="network-graph"
          ref="networkGraph"
          :style="{
            transform: `translate(${panX}px, ${panY}px) scale(${zoomLevel})`,
          }"
        >
          <!-- Root: Tier 1 IB -->
          <div class="network-branch">
            <div class="network-node">
              <div class="node-card tier1">
                <div class="node-content">
                  <div class="node-avatar">IB1</div>
                  <div class="node-info">
                    <div class="node-title">{{ ibPartner.companyName }}</div>
                    <div class="node-subtitle">
                      {{ ibPartner.ibCode }} • You
                    </div>
                    <div class="node-badge">
                      <i class="fas fa-crown"></i> Tier
                      {{ ibPartner.tierLevel || 1 }} IB
                    </div>
                  </div>
                </div>
                <div class="node-stats">
                  <div class="node-stat">
                    <i class="fas fa-users"></i>
                    <span
                      >{{ formatNumber(networkStats.directClients) }} Direct
                      Clients</span
                    >
                  </div>
                  <div class="node-stat">
                    <i class="fas fa-handshake"></i>
                    <span>{{ networkStats.subIbsCount }} Sub-IBs</span>
                  </div>
                </div>
              </div>

              <div
                class="expand-btn"
                :class="{ expanded: expandedNodes.root }"
                @click.stop="toggleNode('root')"
              >
                {{ expandedNodes.root ? "−" : "+" }}
              </div>
            </div>

            <!-- Children (shown when expanded) -->
            <div
              class="network-children"
              :class="{ expanded: expandedNodes.root }"
              v-if="networkTree.length > 0"
            >
              <!-- Sub-IBs and Clients -->
              <div
                class="network-branch"
                v-for="child in networkTree.slice(0, 3)"
                :key="child.id"
              >
                <div
                  class="node-card"
                  :class="child.type === 'ib' ? 'tier2' : 'client'"
                >
                  <div class="node-content">
                    <div class="node-avatar">{{ child.initials }}</div>
                    <div class="node-info">
                      <div class="node-title">{{ child.name }}</div>
                      <div class="node-subtitle">{{ child.subtitle }}</div>
                      <div v-if="child.type === 'ib'" class="node-badge">
                        <i class="fas fa-star"></i> Tier {{ child.tierLevel }}
                      </div>
                    </div>
                  </div>
                  <div class="node-stats">
                    <span v-if="child.type === 'ib'" class="node-stat">
                      <i class="fas fa-users"></i>
                      <span>{{ formatNumber(child.clientCount) }} Clients</span>
                    </span>
                    <span v-else class="status-badge active">Active</span>
                  </div>
                </div>
              </div>

              <!-- More indicator -->
              <div v-if="networkTree.length > 3" class="network-branch">
                <div
                  class="node-card collapsed-summary"
                  @click="alert('View more')"
                >
                  <i
                    class="fas fa-ellipsis-h"
                    style="font-size: 16px; margin-bottom: 4px"
                  ></i>
                  <div style="font-weight: 600; font-size: 14px">
                    +{{ networkTree.length - 3 }} More
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Network Summary Stats -->
      <div class="network-summary">
        <div class="summary-item">
          <div class="summary-value">
            {{ formatNumber(ibPartner.totalClients) }}
          </div>
          <div class="summary-label">Total Network</div>
        </div>
        <div class="summary-item">
          <div class="summary-value" style="color: #48bb78">
            {{ formatNumber(networkStats.tier2Count) }}
          </div>
          <div class="summary-label">Tier 2 IBs</div>
        </div>
        <div class="summary-item">
          <div class="summary-value" style="color: var(--color-warning)">
            {{ formatNumber(networkStats.tier3Count) }}
          </div>
          <div class="summary-label">Tier 3 IBs</div>
        </div>
        <div class="summary-item">
          <div class="summary-value" style="color: var(--color-text)">
            {{ formatNumber(networkStats.directClients) }}
          </div>
          <div class="summary-label">Direct Clients</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch, Teleport } from "vue";
import { useCountryStore } from "@/stores/countryStore";
import { formatCurrency, formatNumber } from "@/utils/helpers";
import ibPartnersApi from "@/services/ibPartnersApi";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t } = useAdminI18n();

const props = defineProps({
  ibPartner: {
    type: Object,
    required: true,
  },
  tierLevels: {
    type: Array,
    default: () => [],
  },
  commissionRules: {
    type: Array,
    default: () => [],
  },
  commissionRulesDetails: {
    type: Object,
    default: () => ({}),
  },
  securities: {
    type: Array,
    default: () => [],
  },
  symbols: {
    type: Array,
    default: () => [],
  },
  saving: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["save", "refresh"]);

const localIb = reactive({ ...props.ibPartner });
const originalIb = reactive({ ...props.ibPartner });

const selectedRuleIds = ref([]);
const originalRuleIds = ref([]);
const hasInfoChanges = ref(false);
const hasRuleChanges = ref(false);

// 每个规则的Payment Settings
const rulePaymentSettings = ref({}); // { ruleId: { paymentCycle, paymentDay, minimumPayout, payoutCurrency } }
const hasPaymentChanges = ref({}); // { ruleId: boolean }

// 每个规则的产品和额外规则
const ruleProducts = ref({}); // { ruleId: [products] }
const ruleAdditionalRules = ref({}); // { ruleId: [rules] }
const hasProductChanges = ref({}); // { ruleId: boolean }
const hasAdditionalRulesChanges = ref({}); // { ruleId: boolean }
const additionalRulesEnabled = ref({}); // { ruleId: boolean }
const loadedCustomDataRules = ref(new Set()); // 跟踪哪些rule已经加载过保存的自定义数据
const isInitializing = ref(true); // 标记是否正在初始化，防止watch在初始化时触发

// Securities和Symbols
const allSecurities = ref([]);
const customSymbols = ref([]);

// Tiers Modal
const showTiersModal = ref(false);
const currentEditingRuleId = ref(null);
const currentEditingRuleIndex = ref(null);
const editingTiers = ref([]);

// 计算是否有变化
const hasChanges = computed(() => {
  return (
    hasRuleChanges.value ||
    Object.values(hasPaymentChanges.value).some((v) => v) ||
    Object.values(hasProductChanges.value).some((v) => v) ||
    Object.values(hasAdditionalRulesChanges.value).some((v) => v)
  );
});
const editingFields = reactive({
  companyName: false,
  adminAlias: false,
  country: false,
  referralUrl: false,
  contactPerson: false,
  contactEmail: false,
  contactPhone: false,
  address: false,
  website: false,
});

// 使用 countryStore
const countryStore = useCountryStore();
const countries = computed(() => countryStore.countries);

// Network Graph State
const networkContainer = ref(null);
const networkGraph = ref(null);
const zoomLevel = ref(1.0);
const panX = ref(0);
const panY = ref(0);
const isDragging = ref(false);
const dragStartX = ref(0);
const dragStartY = ref(0);
const showZoomIndicator = ref(false);
const expandedNodes = ref({ root: false });
const networkTree = ref([]);
const networkStats = ref({
  subIbsCount: 0,
  tier2Count: 0,
  tier3Count: 0,
  directClients: 48,
});

// Documents相关
const documents = ref([]);
const documentsLoading = ref(false);
const showDocumentModal = ref(false);
const currentDocument = ref(null);

/**
 * 标记信息已变更
 */
const markInfoChanged = () => {
  hasInfoChanges.value = true;
};

/**
 * 启用字段编辑
 */
const enableEdit = (fieldName) => {
  if (editingFields[fieldName]) {
    // 如果正在编辑，保存并退出编辑模式
    editingFields[fieldName] = false;
    markInfoChanged();
  } else {
    // 进入编辑模式
    editingFields[fieldName] = true;
    // 聚焦到该字段
    setTimeout(() => {
      const element = document.querySelector(
        `[data-original="${originalIb[fieldName] || ""}"]`,
      );
      if (element) {
        element.focus();
        // 选中所有文本
        const range = document.createRange();
        range.selectNodeContents(element);
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
      }
    }, 10);
  }
};

/**
 * 处理字段输入
 */
const handleFieldInput = (fieldName, event) => {
  const newValue = event.target.textContent.trim();
  if (fieldName === "referralUrl") {
    // Referral URL 特殊处理
    localIb.referralUrl = newValue;
  } else {
    localIb[fieldName] = newValue;
  }
  markInfoChanged();
};

/**
 * 处理字段失焦
 */
const handleFieldBlur = (fieldName, event) => {
  const newValue = event.target.textContent.trim();
  if (fieldName === "referralUrl") {
    localIb.referralUrl = newValue;
  } else {
    localIb[fieldName] = newValue || null;
  }
  editingFields[fieldName] = false;
  markInfoChanged();
};

/**
 * 获取 IB Referral URL
 */
const getReferralUrl = () => {
  // 优先使用数据库中的 referralUrl 字段
  if (localIb.referralUrl || props.ibPartner.referralUrl) {
    return localIb.referralUrl || props.ibPartner.referralUrl;
  }
  // 如果数据库中没有，根据 IB Code 生成 URL（例如：IB-101 -> https://trading.bdx.com/register?ref=IB101）
  const ibCode = props.ibPartner.ibCode || "";
  const refCode = ibCode.replace(/-/g, "");
  return `https://trading.bdx.com/register?ref=${refCode}`;
};

/**
 * 复制 Referral URL
 */
const copyReferralUrl = async () => {
  const url = getReferralUrl();
  try {
    await navigator.clipboard.writeText(url);
    alert("✓ Referral URL copied to clipboard!");
  } catch (err) {
    // 降级方案
    const textArea = document.createElement("textarea");
    textArea.value = url;
    textArea.style.position = "fixed";
    textArea.style.opacity = "0";
    document.body.appendChild(textArea);
    textArea.select();
    try {
      document.execCommand("copy");
      alert("✓ Referral URL copied to clipboard!");
    } catch (e) {
      alert("Failed to copy URL. Please copy manually: " + url);
    }
    document.body.removeChild(textArea);
  }
};

/**
 * 获取当前 Tier Level 显示文本
 */
const getCurrentTierDisplay = () => {
  const tierLevelId = props.ibPartner.tierLevelId || props.ibPartner.tierLevel;
  if (!tierLevelId) return "No Tier Level Assigned";

  const tier = props.tierLevels.find(
    (t) => t.id === tierLevelId || t.tierLevel === tierLevelId,
  );
  if (tier) {
    const perms = [];
    if (tier.canRecruitSubAgents) perms.push("Recruit");
    if (tier.canViewReports) perms.push("Reports");
    if (tier.canManageClients) perms.push("Manage");
    const permText =
      perms.length > 0 ? ` (${perms.join(" + ")})` : " (Basic Access)";
    return `Tier ${tier.tierLevel} - ${tier.tierName}${permText}`;
  }
  return `Tier ${tierLevelId}`;
};

/**
 * 标记规则已变更
 */
const markRuleChanged = () => {
  hasRuleChanges.value = true;
};

/**
 * 标记 Payment 已变更
 */
const markPaymentChanged = (ruleId) => {
  hasPaymentChanges.value = { ...hasPaymentChanges.value, [ruleId]: true };
};

/**
 * 处理Payment Cycle变化，自动设置Payment Day默认值
 */
const handlePaymentCycleChange = (ruleId) => {
  const settings = rulePaymentSettings.value[ruleId];
  if (!settings) return;

  const defaultValues = {
    realtime: "immediate",
    daily: "everyday",
    weekly: "Monday",
    biweekly: "1-15",
    monthly: "5",
    quarterly: "1",
  };

  const newCycle = settings.paymentCycle;

  if (defaultValues[newCycle]) {
    if (newCycle === "realtime") {
      settings.paymentDay = defaultValues[newCycle];
    } else if (
      !settings.paymentDay ||
      settings.paymentDay === "immediate" ||
      settings.paymentDay === "0"
    ) {
      settings.paymentDay = defaultValues[newCycle];
    }
  }

  markPaymentChanged(ruleId);
};

/**
 * 标记 Product 已变更
 */
const markProductChanged = (ruleId) => {
  hasProductChanges.value = { ...hasProductChanges.value, [ruleId]: true };
};

/**
 * 标记 Additional Rules 已变更
 */
const markAdditionalRulesChanged = (ruleId) => {
  hasAdditionalRulesChanges.value = {
    ...hasAdditionalRulesChanges.value,
    [ruleId]: true,
  };
};

/**
 * 产品管理
 */
const addProduct = (ruleId) => {
  if (!ruleProducts.value[ruleId]) {
    ruleProducts.value = { ...ruleProducts.value, [ruleId]: [] };
  }
  ruleProducts.value[ruleId].push({
    productType: "security",
    productName: "",
    commissionType: "per_lot",
    commissionRate: 0,
    additionalRate: 0,
    minimumVolume: "0.01 lots",
  });
  markProductChanged(ruleId);
};

const removeProduct = (ruleId, index) => {
  if (ruleProducts.value[ruleId] && ruleProducts.value[ruleId].length <= 1) {
    alert("⚠️ You must have at least one product.");
    return;
  }
  if (confirm("Are you sure you want to remove this product?")) {
    ruleProducts.value[ruleId].splice(index, 1);
    markProductChanged(ruleId);
  }
};

/**
 * 额外规则管理
 */
const toggleAdditionalRules = (ruleId) => {
  const products = ruleProducts.value[ruleId] || [];
  if (!additionalRulesEnabled.value[ruleId] && products.length === 0) {
    alert(
      "⚠️ Please add at least one product in the Product Commission Configuration table first.",
    );
    return;
  }
  additionalRulesEnabled.value = {
    ...additionalRulesEnabled.value,
    [ruleId]: !additionalRulesEnabled.value[ruleId],
  };
};

const addAdditionalRule = (ruleId) => {
  if (!ruleAdditionalRules.value[ruleId]) {
    ruleAdditionalRules.value = { ...ruleAdditionalRules.value, [ruleId]: [] };
  }
  ruleAdditionalRules.value[ruleId].push({
    productType: "security",
    productName: "",
    ruleType: "bonus_commission",
    ruleValue: 0,
    ruleCondition: "",
    tierCount: 0,
    isActive: 1,
  });
  markAdditionalRulesChanged(ruleId);
};

const removeAdditionalRule = (ruleId, index) => {
  if (confirm("Are you sure you want to remove this additional rule?")) {
    ruleAdditionalRules.value[ruleId].splice(index, 1);
    markAdditionalRulesChanged(ruleId);
  }
};

const updateAdditionalRuleColumns = (ruleId, index) => {
  const rule = ruleAdditionalRules.value[ruleId][index];
  if (rule.ruleType === "volume_tiers") {
    rule.ruleValue = null;
  }
  markAdditionalRulesChanged(ruleId);
};

/**
 * Tiers Modal
 */
const openManageTiersModal = (ruleId, ruleIndex) => {
  currentEditingRuleId.value = ruleId;
  currentEditingRuleIndex.value = ruleIndex;
  const rule = ruleAdditionalRules.value[ruleId][ruleIndex];

  if (rule.tiers && rule.tiers.length > 0) {
    editingTiers.value = rule.tiers.map((t) => ({ ...t }));
  } else {
    editingTiers.value = [
      {
        tierLevel: 1,
        tierName: "Starter Level",
        commissionRate: 8.0,
        minimumVolume: 0,
        maximumVolume: "100",
      },
    ];
  }

  showTiersModal.value = true;
};

const closeTiersModal = () => {
  showTiersModal.value = false;
  currentEditingRuleId.value = null;
  currentEditingRuleIndex.value = null;
};

const addTierEdit = () => {
  const tierLevel = editingTiers.value.length + 1;
  editingTiers.value.push({
    tierLevel,
    tierName: `Tier ${tierLevel}`,
    commissionRate: 0,
    minimumVolume: 0,
    maximumVolume: "Unlimited",
  });
};

const removeTierEdit = (index) => {
  if (editingTiers.value.length <= 1) {
    alert("⚠️ You must have at least one tier.");
    return;
  }
  if (confirm("Are you sure you want to remove this tier?")) {
    editingTiers.value.splice(index, 1);
  }
};

const saveTiersModal = () => {
  for (let i = 0; i < editingTiers.value.length; i++) {
    if (!editingTiers.value[i].commissionRate) {
      alert(`⚠️ Please enter commission rate for Tier ${i + 1}`);
      return;
    }
  }

  if (
    currentEditingRuleId.value !== null &&
    currentEditingRuleIndex.value !== null
  ) {
    const rule =
      ruleAdditionalRules.value[currentEditingRuleId.value][
        currentEditingRuleIndex.value
      ];
    rule.tiers = editingTiers.value.map((t) => ({ ...t }));
    rule.tierCount = editingTiers.value.length;
    rule.ruleValue = `${editingTiers.value.length} Tiers`;
    markAdditionalRulesChanged(currentEditingRuleId.value);
  }

  closeTiersModal();
  alert(
    `✓ Commission tiers saved successfully!\n\nTotal Tiers: ${editingTiers.value.length}`,
  );
};

/**
 * 获取值占位符
 */
const getValuePlaceholder = (ruleType) => {
  const placeholders = {
    bonus_commission: "$/lot",
    volume_multiplier: "Multiplier (e.g., 1.25)",
    performance_bonus: "% of base commission",
    cash_rebate: "Rebate $/lot",
  };
  return placeholders[ruleType] || "Value";
};

/**
 * 保存基本信息
 */
const saveInfo = () => {
  const data = {
    companyName: localIb.companyName,
    adminAlias: localIb.adminAlias,
    contactPerson: localIb.contactPerson,
    contactEmail: localIb.contactEmail,
    contactPhone: localIb.contactPhone || props.ibPartner.clientPhone,
    country: localIb.country, // 保存为 code
    address: localIb.address,
    website: localIb.website,
    status: localIb.status,
    referralUrl: localIb.referralUrl || getReferralUrl(),
  };

  emit("save", { ibId: props.ibPartner.id, type: "basic-info", data });
  hasInfoChanges.value = false;
  Object.assign(originalIb, localIb);
  // 退出所有编辑模式
  Object.keys(editingFields).forEach((key) => {
    editingFields[key] = false;
  });
};

/**
 * 保存规则（新的 handleSave 方法）
 */
const handleSave = async () => {
  emit("save", {
    ibId: props.ibPartner.id,
    type: "save-rules",
    data: {
      ruleIds: selectedRuleIds.value,
      paymentSettings: rulePaymentSettings.value,
      products: ruleProducts.value,
      additionalRules: ruleAdditionalRules.value,
    },
  });

  // 重置变化标记
  originalRuleIds.value = [...selectedRuleIds.value];
  hasRuleChanges.value = false;
  Object.keys(hasPaymentChanges.value).forEach((key) => {
    hasPaymentChanges.value[key] = false;
  });
  Object.keys(hasProductChanges.value).forEach((key) => {
    hasProductChanges.value[key] = false;
  });
  Object.keys(hasAdditionalRulesChanges.value).forEach((key) => {
    hasAdditionalRulesChanges.value[key] = false;
  });
};

/**
 * 格式化支付周期
 */
const formatPaymentCycle = (cycle) => {
  const cycles = {
    realtime: "Real-time",
    daily: "Daily",
    weekly: "Weekly",
    biweekly: "Bi-weekly",
    monthly: "Monthly",
    quarterly: "Quarterly",
  };
  return cycles[cycle] || cycle;
};

/**
 * 获取规则对象
 */
const getRule = (ruleId) => {
  return props.commissionRules.find((r) => r.id === ruleId);
};

/**
 * 获取规则图标
 */
const getRuleIcon = (ruleType) => {
  const icons = {
    standard: "fa-layer-group",
    premium: "fa-crown",
    ultra: "fa-gem",
    custom: "fa-cog",
  };
  return icons[ruleType] || "fa-file-invoice-dollar";
};

/**
 * 获取规则颜色
 */
const getRuleColor = (ruleType) => {
  const colors = {
    standard: "var(--color-brand)",
    premium: "#f59e0b",
    ultra: "#8b5cf6",
    custom: "#64748b",
  };
  return colors[ruleType] || "var(--color-brand)";
};

/**
 * 格式化支付日
 */
const formatPaymentDay = (paymentDay) => {
  if (!paymentDay) return "N/A";
  if (paymentDay.includes("-")) {
    return `${paymentDay.split("-")[0]}th & ${paymentDay.split("-")[1]}th of month`;
  }
  if (paymentDay === "everyday") return "Every day";
  if (paymentDay === "weekdays") return "Weekdays only";
  if (paymentDay === "last") return "Last day of month";
  return `${paymentDay}th of each month`;
};

/**
 * 获取适用产品
 */
const getApplicableProducts = (rule) => {
  // 可以扩展显示具体产品类型
  return `${rule.productCount} Products`;
};

/**
 * 计算平均客户价值
 */
const calculateAvgClientValue = () => {
  if (!props.ibPartner.totalClients || props.ibPartner.totalClients === 0) {
    return 0;
  }
  const avg = props.ibPartner.totalTradingVolume / props.ibPartner.totalClients;
  return avg;
};

/**
 * 获取支付信息
 */
const getPaymentInfo = (field) => {
  if (
    !props.ibPartner.paymentSettings ||
    props.ibPartner.paymentSettings.length === 0
  ) {
    return "N/A";
  }
  const defaultPayment =
    props.ibPartner.paymentSettings.find((p) => p.isDefault) ||
    props.ibPartner.paymentSettings[0];
  return defaultPayment[field] || "N/A";
};

/**
 * 格式化日期
 */
const formatDate = (dateString) => {
  if (!dateString) return "N/A";
  const date = new Date(dateString);
  return date.toLocaleDateString("en-US", {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
};

/**
 * 格式化文件大小
 */
const formatFileSize = (bytes) => {
  if (!bytes) return "0 B";
  const k = 1024;
  const sizes = ["B", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + " " + sizes[i];
};

/**
 * 加载文档
 */
const loadDocuments = async () => {
  try {
    documentsLoading.value = true;
    // TODO: 调用 API 获取 IB Application Document Acknowledgements
    // 需要根据 ibPartner.id 找到对应的 applicationId，然后获取文档
    const response = await ibPartnersApi.getIbPartnerDocuments(
      props.ibPartner.id,
    );
    if (response.success && response.data) {
      documents.value = response.data.map((doc) => ({
        id: doc.id,
        title: doc.documentTitle || doc.title,
        content: doc.documentContent || doc.content,
        documentType: doc.documentType || "ib_agreement",
        signedAt: doc.acknowledgedAt || doc.signedAt,
        source: "ib_application",
      }));
    }
  } catch (error) {
    console.error("Failed to load documents:", error);
    documents.value = [];
  } finally {
    documentsLoading.value = false;
  }
};

/**
 * 获取文档图标
 */
const getDocumentIcon = (type) => {
  const iconMap = {
    terms_of_service: "fas fa-file-contract",
    privacy_policy: "fas fa-shield-alt",
    risk_disclosure: "fas fa-exclamation-triangle",
    ib_agreement: "fas fa-file-signature",
    kyc_document: "fas fa-file-signature",
  };
  return iconMap[type] || "fas fa-file";
};

/**
 * 查看文档
 */
const viewDocument = (doc) => {
  currentDocument.value = doc;
  showDocumentModal.value = true;
  document.body.style.overflow = "hidden";
};

/**
 * 关闭文档模态框
 */
const closeDocumentModal = () => {
  showDocumentModal.value = false;
  currentDocument.value = null;
  document.body.style.overflow = "auto";
};

/**
 * 下载文档
 */
const downloadDocument = (doc) => {
  if (!doc) {
    doc = currentDocument.value;
  }
  if (!doc) return;

  const title = doc.title;
  const content = doc.content;

  // Create a printable HTML document
  const printWindow = window.open("", "_blank", "width=800,height=600");

  if (!printWindow) {
    alert("Please allow popups to download the PDF");
    return;
  }

  const htmlContent = `
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset="UTF-8">
      <title>${title} - ${props.ibPartner.companyName || props.ibPartner.ibCode}</title>
      <style>
        * {
          margin: 0;
          padding: 0;
          box-sizing: border-box;
        }

        body {
          font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
          line-height: 1.6;
          color: var(--color-ink);
          padding: 40px;
          max-width: 800px;
          margin: 0 auto;
        }

        .header {
          text-align: center;
          margin-bottom: 30px;
          padding-bottom: 20px;
          border-bottom: 3px solid var(--color-brand);
        }

        .header h1 {
          font-size: 28px;
          color: var(--color-brand);
          margin-bottom: 10px;
        }

        .document-content {
          margin: 30px 0;
          padding: 20px;
          background: var(--color-surface-soft);
          border-radius: var(--radius-md);
        }

        .document-content h4 {
          color: var(--color-ink);
          margin-top: 20px;
          margin-bottom: 10px;
          font-size: 16px;
        }

        .document-content p {
          margin-bottom: 12px;
          text-align: justify;
        }

        .signature-section {
          margin-top: 40px;
          padding-top: 20px;
          border-top: 2px solid var(--color-border);
        }

        .signature-info {
          display: grid;
          grid-template-columns: repeat(2, 1fr);
          gap: 15px;
          margin-top: 15px;
        }

        .signature-field {
          padding: 10px;
          background: var(--color-surface);
          border-radius: var(--radius-sm);
        }

        .signature-field label {
          font-size: 14px;
          color: var(--color-muted);
          font-weight: 600;
          display: block;
          margin-bottom: 5px;
        }

        .signature-field .value {
          font-size: 14px;
          color: var(--color-ink);
        }

        @media print {
          body {
            padding: 20px;
          }
        }
      </style>
    </head>
    <body>
      <div class="header">
        <h1>${title}</h1>
        <div class="company-name">${props.ibPartner.companyName || "IB Partner"}</div>
      </div>

      <div class="document-content">
        ${content}
      </div>

      <div class="signature-section">
        <h4>Digital Signature</h4>
        <div class="signature-info">
          <div class="signature-field">
            <label>Full Name</label>
            <div class="value">${props.ibPartner.companyName || props.ibPartner.contactPerson || "N/A"}</div>
          </div>
          <div class="signature-field">
            <label>Email Address</label>
            <div class="value">${props.ibPartner.contactEmail || "N/A"}</div>
          </div>
          <div class="signature-field">
            <label>IB Code</label>
            <div class="value">${props.ibPartner.ibCode || "N/A"}</div>
          </div>
          <div class="signature-field">
            <label>Date & Time Signed</label>
            <div class="value">${formatDate(doc.signedAt)}</div>
          </div>
        </div>
      </div>
    </body>
    </html>
  `;

  printWindow.document.write(htmlContent);
  printWindow.document.close();

  // Wait for content to load, then print
  printWindow.onload = () => {
    setTimeout(() => {
      printWindow.print();
    }, 250);
  };
};

/**
 * 下载当前文档
 */
const downloadCurrentDocument = () => {
  if (currentDocument.value) {
    downloadDocument(currentDocument.value);
  }
};

// ========== Network Graph Functions ==========

/**
 * 切换节点展开/收起
 */
const toggleNode = (nodeId) => {
  expandedNodes.value[nodeId] = !expandedNodes.value[nodeId];
};

/**
 * 鼠标按下开始拖动
 */
const handleMouseDown = (e) => {
  // 不在节点或按钮上才允许拖动
  if (e.target.closest(".node-card") || e.target.closest(".expand-btn")) {
    return;
  }

  isDragging.value = true;
  dragStartX.value = e.clientX - panX.value;
  dragStartY.value = e.clientY - panY.value;
  e.preventDefault();
};

/**
 * 鼠标移动拖动
 */
const handleMouseMove = (e) => {
  if (!isDragging.value) return;

  panX.value = e.clientX - dragStartX.value;
  panY.value = e.clientY - dragStartY.value;
};

/**
 * 鼠标释放结束拖动
 */
const handleMouseUp = () => {
  isDragging.value = false;
};

/**
 * 鼠标滚轮缩放
 */
const handleWheel = (e) => {
  e.preventDefault();

  const delta = e.deltaY > 0 ? -0.1 : 0.1;
  const oldZoom = zoomLevel.value;

  zoomLevel.value += delta;
  zoomLevel.value = Math.max(0.3, Math.min(3.0, zoomLevel.value));

  // 显示缩放指示器
  showZoomIndicator.value = true;
  clearTimeout(zoomIndicatorTimeout);
  zoomIndicatorTimeout = setTimeout(() => {
    showZoomIndicator.value = false;
  }, 1000);
};

/**
 * 双击重置视图
 */
const resetView = () => {
  zoomLevel.value = 1.0;
  panX.value = 0;
  panY.value = 0;

  showZoomIndicator.value = true;
  clearTimeout(zoomIndicatorTimeout);
  zoomIndicatorTimeout = setTimeout(() => {
    showZoomIndicator.value = false;
  }, 1000);
};

let zoomIndicatorTimeout = null;

/**
 * 加载网络数据
 */
const loadNetworkData = async () => {
  // 模拟网络数据（实际应从API获取）
  networkTree.value = [
    {
      id: 2,
      type: "ib",
      name: "European Markets",
      subtitle: "IB-2024-003",
      tierLevel: 2,
      clientCount: 18,
      initials: "IB2",
    },
    {
      id: 3,
      type: "ib",
      name: "Asia Pacific Solutions",
      subtitle: "IB-2024-002",
      tierLevel: 2,
      clientCount: 32,
      initials: "IB5",
    },
    {
      id: 4,
      type: "client",
      name: "Michael Johnson",
      subtitle: "Client",
      initials: "MJ",
    },
    {
      id: 5,
      type: "client",
      name: "Sarah Davis",
      subtitle: "Client",
      initials: "SD",
    },
  ];

  networkStats.value = {
    subIbsCount: networkTree.value.filter((n) => n.type === "ib").length,
    tier2Count: 5,
    tier3Count: 8,
    directClients: 48,
  };

  // TODO: 调用实际API
  // const response = await ibPartnersApi.getIbNetwork(props.ibPartner.id, 5);
  // if (response.success) {
  //   networkTree.value = response.data;
  // }
};

/**
 * 初始化Securities和Symbols（从props获取）
 */
const initializeSecuritiesAndSymbols = () => {
  if (props.securities && props.securities.length > 0) {
    allSecurities.value = props.securities;
  }
  if (props.symbols && props.symbols.length > 0) {
    customSymbols.value = props.symbols;
  }
};

/**
 * 加载已保存的自定义规则数据（从接口返回的customRules）
 */
const loadRuleCustomData = async () => {
  try {
    console.log(
      "[IbPartnerDetail] loadRuleCustomData - Start, ibPartner.id:",
      props.ibPartner.id,
    );

    // 从接口获取完整的IB Partner数据
    const response = await ibPartnersApi.getIbPartner(props.ibPartner.id);

    if (!response.success || !response.data) {
      console.log("[IbPartnerDetail] loadRuleCustomData - No data from API");
      return;
    }

    const ibPartnerData = response.data;
    const customRules = ibPartnerData.customRules || [];
    const preAssignedRules = ibPartnerData.preAssignedRules || [];

    console.log(
      "[IbPartnerDetail] loadRuleCustomData - customRules:",
      customRules,
    );
    console.log(
      "[IbPartnerDetail] loadRuleCustomData - preAssignedRules:",
      preAssignedRules,
    );

    // 如果有preAssignedRules，更新selectedRuleIds
    if (
      preAssignedRules &&
      Array.isArray(preAssignedRules) &&
      preAssignedRules.length > 0
    ) {
      selectedRuleIds.value = preAssignedRules.map((r) =>
        String(r.id || r.ruleId),
      );
      originalRuleIds.value = [...selectedRuleIds.value];
    }

    // 加载每个规则的自定义数据
    if (customRules && Array.isArray(customRules) && customRules.length > 0) {
      customRules.forEach((customRule) => {
        const ruleId = String(customRule.ruleId);
        console.log(
          `[IbPartnerDetail] loadRuleCustomData - Processing customRule for ruleId: ${ruleId}`,
          customRule,
        );

        // 标记该rule已加载过保存数据
        loadedCustomDataRules.value.add(ruleId);

        // 更新Payment Settings（如果customRule中有）
        if (
          customRule.paymentCycle ||
          customRule.paymentDay ||
          customRule.minimumPayout ||
          customRule.payoutCurrency
        ) {
          rulePaymentSettings.value[ruleId] = {
            paymentCycle: customRule.paymentCycle || "monthly",
            paymentDay: customRule.paymentDay || "",
            minimumPayout: customRule.minimumPayout || 100.0,
            payoutCurrency: customRule.payoutCurrency || "USD",
          };
        }

        // 加载产品（如果有保存的数据，否则初始化为空数组）
        if (
          customRule.products &&
          Array.isArray(customRule.products) &&
          customRule.products.length > 0
        ) {
          ruleProducts.value[ruleId] = customRule.products.map((p) => ({
            productType: p.productType || "security",
            productName: p.productName || "",
            commissionType: p.commissionType || "per_lot",
            commissionRate: p.commissionRate || 0,
            additionalRate: p.additionalRate || 0,
            minimumVolume: p.minimumVolume || "0.01 lots",
          }));
        } else {
          // 即使没有products，也要初始化为空数组，确保数据结构一致
          ruleProducts.value[ruleId] = [];
        }

        // 加载额外规则（如果有保存的数据，否则初始化为空数组）
        if (
          customRule.additionalRules &&
          Array.isArray(customRule.additionalRules) &&
          customRule.additionalRules.length > 0
        ) {
          ruleAdditionalRules.value[ruleId] = customRule.additionalRules.map(
            (r) => ({
              productType: r.productType || "security",
              productName: r.productName || "",
              ruleType: r.ruleType || "bonus_commission",
              ruleValue: r.ruleValue || 0,
              ruleCondition: r.ruleCondition || "",
              tierCount: (r.tiers && r.tiers.length) || 0,
              tiers: r.tiers || [],
              isActive: r.isActive !== undefined ? r.isActive : 1,
            }),
          );

          // 如果有额外规则，自动启用开关
          additionalRulesEnabled.value[ruleId] = true;
        } else {
          // 即使没有additionalRules，也要初始化为空数组，确保数据结构一致
          ruleAdditionalRules.value[ruleId] = [];
          additionalRulesEnabled.value[ruleId] = false;
        }

        console.log(
          `[IbPartnerDetail] loadRuleCustomData - Loaded data for ruleId ${ruleId}:`,
          {
            paymentSettings: rulePaymentSettings.value[ruleId],
            products: ruleProducts.value[ruleId],
            additionalRules: ruleAdditionalRules.value[ruleId],
          },
        );
      });
    }

    console.log(
      "[IbPartnerDetail] loadRuleCustomData - End, loadedCustomDataRules:",
      Array.from(loadedCustomDataRules.value),
    );
  } catch (error) {
    console.error("[IbPartnerDetail] Failed to load rule custom data:", error);
  }
};

/**
 * 加载规则的通用模板数据（从props中的commissionRulesDetails获取）
 */
const loadRuleTemplateData = (ruleId) => {
  const ruleIdStr = String(ruleId);
  const ruleData = props.commissionRulesDetails[ruleId];

  if (!ruleData) {
    // 如果没有详情数据，初始化为空数组
    ruleProducts.value[ruleIdStr] = [];
    ruleAdditionalRules.value[ruleIdStr] = [];
    additionalRulesEnabled.value[ruleIdStr] = false;
    return;
  }

  // 加载产品配置（通用模板）
  if (
    ruleData.products &&
    Array.isArray(ruleData.products) &&
    ruleData.products.length > 0
  ) {
    ruleProducts.value[ruleIdStr] = ruleData.products.map((p) => ({
      productType: p.productType || "security",
      productName: p.productName || "",
      commissionType: p.commissionType || "per_lot",
      commissionRate: p.commissionRate || 0,
      additionalRate: p.additionalRate || 0,
      minimumVolume: p.minimumVolume || "0.01 lots",
    }));
  } else {
    // 如果没有产品，初始化为空数组
    ruleProducts.value[ruleIdStr] = [];
  }

  // 加载额外规则（通用模板）
  if (
    ruleData.additionalRules &&
    Array.isArray(ruleData.additionalRules) &&
    ruleData.additionalRules.length > 0
  ) {
    ruleAdditionalRules.value[ruleIdStr] = ruleData.additionalRules.map(
      (ar) => ({
        productType: ar.productType || "security",
        productName: ar.productName || "",
        ruleType: ar.ruleType || "bonus_commission",
        ruleValue: ar.ruleValue || 0,
        ruleCondition: ar.ruleCondition || "",
        tierCount: (ar.tiers && ar.tiers.length) || 0,
        tiers: ar.tiers || [],
        isActive: ar.isActive !== undefined ? ar.isActive : 1,
      }),
    );

    // 如果有额外规则，自动启用开关
    additionalRulesEnabled.value[ruleIdStr] = true;
  } else {
    // 如果没有额外规则，初始化为空数组并关闭开关
    ruleAdditionalRules.value[ruleIdStr] = [];
    additionalRulesEnabled.value[ruleIdStr] = false;
  }
};

/**
 * 初始化
 */
onMounted(async () => {
  isInitializing.value = true;

  // 初始化Securities和Symbols（从props获取）
  initializeSecuritiesAndSymbols();

  // 加载文档
  await loadDocuments();

  // 先加载接口返回的自定义规则数据
  await loadRuleCustomData();

  // 初始化选中的规则（如果loadRuleCustomData没有设置）
  if (selectedRuleIds.value.length === 0 && props.ibPartner.assignedRuleNames) {
    // 从名称匹配ID（简化处理）
    const assignedNames = props.ibPartner.assignedRuleNames
      .split(",")
      .map((n) => n.trim());
    selectedRuleIds.value = props.commissionRules
      .filter((r) => assignedNames.includes(r.ruleName))
      .map((r) => String(r.id));
    originalRuleIds.value = [...selectedRuleIds.value];
  }

  // 初始化每个规则的Payment Settings和确保数据结构完整
  selectedRuleIds.value.forEach((ruleId) => {
    const ruleIdNum = Number(ruleId);
    const rule = getRule(ruleIdNum);
    const ruleIdStr = String(ruleId);

    // 如果还没有设置Payment Settings，使用规则默认值
    if (!rulePaymentSettings.value[ruleIdStr]) {
      if (rule) {
        rulePaymentSettings.value[ruleIdStr] = {
          paymentCycle: rule.paymentCycle || "monthly",
          paymentDay: rule.paymentDay || "",
          minimumPayout: rule.minimumPayout || 100.0,
          payoutCurrency: rule.payoutCurrency || "USD",
        };

        // 如果Payment Day为空，根据Payment Cycle设置默认值
        if (!rulePaymentSettings.value[ruleIdStr].paymentDay) {
          handlePaymentCycleChange(ruleIdStr);
        }
      }
    }

    // 确保products和additionalRules已初始化（即使没有自定义数据）
    if (!ruleProducts.value[ruleIdStr]) {
      ruleProducts.value[ruleIdStr] = [];
    }
    if (!ruleAdditionalRules.value[ruleIdStr]) {
      ruleAdditionalRules.value[ruleIdStr] = [];
    }
    if (additionalRulesEnabled.value[ruleIdStr] === undefined) {
      additionalRulesEnabled.value[ruleIdStr] = false;
    }

    // 如果该rule没有加载过保存的自定义数据，就加载通用模板数据
    if (!loadedCustomDataRules.value.has(ruleIdStr)) {
      loadRuleTemplateData(ruleIdNum);
    }
  });

  isInitializing.value = false;
});

// 监听 securities 和 symbols 变化，更新本地数据
watch(
  () => props.securities,
  (newSecurities) => {
    if (newSecurities && newSecurities.length > 0) {
      allSecurities.value = newSecurities;
    }
  },
  { deep: true },
);

watch(
  () => props.symbols,
  (newSymbols) => {
    if (newSymbols && newSymbols.length > 0) {
      customSymbols.value = newSymbols;
    }
  },
  { deep: true },
);

// 监听 ibPartner.id 变化，重新加载数据
watch(
  () => props.ibPartner?.id,
  async (newId, oldId) => {
    if (newId && newId !== oldId) {
      isInitializing.value = true;

      // 更新本地数据
      Object.assign(localIb, props.ibPartner);
      Object.assign(originalIb, props.ibPartner);

      // 重新加载文档
      await loadDocuments();

      // 清空已加载的数据标记
      loadedCustomDataRules.value.clear();

      // 清空规则相关数据
      rulePaymentSettings.value = {};
      ruleProducts.value = {};
      ruleAdditionalRules.value = {};
      hasPaymentChanges.value = {};
      hasProductChanges.value = {};
      hasAdditionalRulesChanges.value = {};
      additionalRulesEnabled.value = {};

      // 重新加载规则数据
      await loadRuleCustomData();

      // 初始化Payment Settings和加载模板数据
      selectedRuleIds.value.forEach((ruleId) => {
        const ruleIdNum = Number(ruleId);
        const rule = getRule(ruleIdNum);
        const ruleIdStr = String(ruleId);

        if (!rulePaymentSettings.value[ruleIdStr]) {
          if (rule) {
            rulePaymentSettings.value[ruleIdStr] = {
              paymentCycle: rule.paymentCycle || "monthly",
              paymentDay: rule.paymentDay || "",
              minimumPayout: rule.minimumPayout || 100.0,
              payoutCurrency: rule.payoutCurrency || "USD",
            };

            if (!rulePaymentSettings.value[ruleIdStr].paymentDay) {
              handlePaymentCycleChange(ruleIdStr);
            }
          }
        }

        // 确保products和additionalRules已初始化
        if (!ruleProducts.value[ruleIdStr]) {
          ruleProducts.value[ruleIdStr] = [];
        }
        if (!ruleAdditionalRules.value[ruleIdStr]) {
          ruleAdditionalRules.value[ruleIdStr] = [];
        }
        if (additionalRulesEnabled.value[ruleIdStr] === undefined) {
          additionalRulesEnabled.value[ruleIdStr] = false;
        }

        if (!loadedCustomDataRules.value.has(ruleIdStr)) {
          loadRuleTemplateData(ruleIdNum);
        }
      });

      isInitializing.value = false;
    }
  },
  { immediate: false },
);

// 监听selectedRuleIds变化
watch(selectedRuleIds, async (newIds, oldIds) => {
  // 如果正在初始化，不处理watch逻辑
  if (isInitializing.value) {
    return;
  }

  // 找出新添加的规则（包括重新选择的）
  const addedRuleIds = newIds.filter((ruleId) => !oldIds.includes(ruleId));

  // 新增的规则，初始化Payment Settings和加载模板数据
  // 注意：重新选择Rule时，总是显示通用模板数据（让用户重新编辑）
  for (const ruleId of addedRuleIds) {
    const ruleIdStr = String(ruleId);

    // 移除已保存数据的标记，因为重新选择了规则
    loadedCustomDataRules.value.delete(ruleIdStr);

    // 初始化Payment Settings
    const rule = getRule(ruleId);
    if (rule) {
      rulePaymentSettings.value[ruleIdStr] = {
        paymentCycle: rule.paymentCycle || "monthly",
        paymentDay: rule.paymentDay || "",
        minimumPayout: rule.minimumPayout || 100.0,
        payoutCurrency: rule.payoutCurrency || "USD",
      };

      // 如果Payment Day为空，根据Payment Cycle设置默认值
      if (!rulePaymentSettings.value[ruleIdStr].paymentDay) {
        handlePaymentCycleChange(ruleIdStr);
      }
    }

    // 重新选择Rule时，总是加载通用模板数据
    loadRuleTemplateData(ruleId);
  }

  // 移除的规则，清理数据
  oldIds.forEach((ruleId) => {
    const ruleIdStr = String(ruleId);
    if (!newIds.includes(ruleId)) {
      delete rulePaymentSettings.value[ruleIdStr];
      delete ruleProducts.value[ruleIdStr];
      delete ruleAdditionalRules.value[ruleIdStr];
      delete hasPaymentChanges.value[ruleIdStr];
      delete hasProductChanges.value[ruleIdStr];
      delete hasAdditionalRulesChanges.value[ruleIdStr];
      delete additionalRulesEnabled.value[ruleIdStr];
      loadedCustomDataRules.value.delete(ruleIdStr);
    }
  });
});

/**
 * 根据国家 code 获取国家名称
 */
const getCountryName = (countryCode) => {
  if (!countryCode) return "";
  // 如果已经是国家名称（不在列表中），直接返回
  const country = countries.value.find((c) => c.code === countryCode);
  if (country) {
    return country.name;
  }
  // 如果没有找到，可能是名称而不是 code，尝试反向查找
  const countryByName = countries.value.find((c) => c.name === countryCode);
  if (countryByName) {
    return countryByName.name;
  }
  // 如果都找不到，返回原值
  return countryCode;
};

/**
 * 获取当前显示的国家值（用于下拉选择）
 */
const getCurrentCountryCode = () => {
  if (!localIb.country) return "";
  // 如果 country 是 code，直接返回
  const country = countries.value.find((c) => c.code === localIb.country);
  if (country) {
    return localIb.country;
  }
  // 如果 country 是 name，查找对应的 code
  const countryByName = countries.value.find((c) => c.name === localIb.country);
  if (countryByName) {
    return countryByName.code;
  }
  return localIb.country;
};

/**
 * 加载国家列表（使用 countryStore）
 */
const loadCountries = async () => {
  if (!countryStore.loaded) {
    await countryStore.fetchCountries(true);
  }
};

// 加载网络数据
loadNetworkData();

// 加载国家列表
loadCountries();

// 添加全局事件监听
if (typeof document !== "undefined") {
  document.addEventListener("mousemove", handleMouseMove);
  document.addEventListener("mouseup", handleMouseUp);
}
</script>

<style scoped>
.detail-content {
  padding: 30px;
  background: var(--color-surface-soft);
}

.detail-sections {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  margin-bottom: 20px;
}

.detail-section {
  background: var(--color-surface);
  border-radius: var(--radius-md);
  padding: 25px;
  border: 2px solid var(--color-border);
  transition: all 0.3s ease;
}

.detail-section:hover {
  border-color: var(--color-border-strong);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.detail-section.full-width {
  grid-column: 1 / -1;
}

.detail-section h3 {
  font-size: 16px;
  color: var(--color-ink);
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  align-items: center;
  gap: 10px;
}

.detail-section h3 i {
  color: var(--color-brand);
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
}

.section-title {
  display: flex;
  align-items: center;
  gap: 10px;
}

.btn-save {
  padding: 6px 16px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: var(--color-border);
  color: var(--color-faint);
}

.btn-save:disabled {
  cursor: not-allowed;
}

.btn-save.active {
  background: var(--color-brand-solid);
  color: white;
  box-shadow: 0 2px 8px rgba(var(--color-brand-rgb), 0.3);
}

.btn-save.active:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.4);
}

/* Step 2 Footer */
.step-2-footer {
  margin-top: 30px;
  padding-top: 20px;
  border-top: 2px solid var(--color-border);
  display: flex;
  justify-content: flex-end;
}

.detail-field {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 0;
  border-bottom: 1px solid #f0f0f0;
}

.detail-field:last-child {
  border-bottom: none;
}

.detail-label {
  font-weight: 600;
  color: var(--color-muted);
  font-size: 14px;
}

.detail-value {
  color: var(--color-ink);
  font-size: 14px;
  font-weight: 500;
  text-align: right;
}

.detail-input,
.detail-select {
  border: 2px solid var(--color-border);
  padding: 6px 12px;
  border-radius: var(--radius-sm);
  min-width: 150px;
  font-size: 14px;
  font-weight: 500;
  color: var(--color-ink);
  transition: all 0.3s ease;
}

.detail-input:focus,
.detail-select:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.detail-value-wrapper {
  display: flex;
  align-items: center;
  gap: 10px;
  flex: 1;
  justify-content: flex-end;
}

.detail-value[contenteditable="true"] {
  border: 2px solid var(--color-brand);
  background: var(--color-surface-soft);
  padding: 4px 8px;
  border-radius: 4px;
  outline: none;
  min-width: 150px;
  text-align: right;
}

.detail-value[contenteditable="false"] {
  cursor: default;
}

.detail-value.editable {
  cursor: text;
  min-width: 150px;
}

.detail-value-select {
  border: 2px solid var(--color-border);
  background: var(--color-surface);
  cursor: pointer;
  padding: 6px 12px;
  border-radius: var(--radius-sm);
  min-width: 150px;
  font-size: 14px;
  font-weight: 500;
  color: var(--color-ink);
  transition: all 0.3s ease;
}

.detail-value-select:hover {
  border-color: var(--color-border-strong);
  background: var(--color-surface-soft);
}

.detail-value-select:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
  background: var(--color-surface);
}

.btn-edit {
  background: none;
  border: none;
  color: var(--color-faint);
  cursor: pointer;
  font-size: 14px;
  padding: 4px;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.btn-edit:hover {
  color: var(--color-brand);
  transform: scale(1.1);
}

.info-banner {
  background: var(--color-brand-soft);
  padding: 12px;
  border-radius: var(--radius-md);
  margin-bottom: 20px;
  border-left: 4px solid var(--color-brand);
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: var(--color-text);
}

.setup-step {
  margin-bottom: 30px;
}

.step-title {
  font-weight: 600;
  color: var(--color-ink);
  font-size: 15px;
  margin-bottom: 15px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.step-title i {
  color: var(--color-brand);
}

.info-note {
  background: var(--color-warning-soft);
  padding: 12px;
  border-radius: var(--radius-sm);
  margin-bottom: 15px;
  border-left: 3px solid var(--color-warning);
  font-size: 14px;
  color: var(--color-warning);
}

.tier-selection-box {
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 25px;
  margin-bottom: 15px;
}

.tier-display {
  padding: 15px;
  background: var(--color-success-soft);
  border-radius: var(--radius-md);
  border-left: 4px solid var(--color-success);
}

.tier-display-content {
  display: flex;
  align-items: center;
  gap: 10px;
}

.tier-display-text {
  font-weight: 600;
  color: var(--color-success);
  font-size: 14px;
}

.rules-selection-box {
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 20px;
  margin-bottom: 20px;
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px;
}

.rule-checkbox {
  padding: 12px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-sm);
  border: 2px solid transparent;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  gap: 12px;
  cursor: pointer;
}

.rule-checkbox:hover {
  border-color: var(--color-border-strong);
}

.rule-checkbox:has(input:checked) {
  background: var(--color-brand-soft);
  border-color: var(--color-brand);
}

.rule-checkbox input[type="checkbox"] {
  width: 18px;
  height: 18px;
  accent-color: var(--color-brand);
  cursor: pointer;
  flex-shrink: 0;
}

.rule-checkbox label {
  cursor: pointer;
  flex: 1;
}

.rule-name {
  font-weight: 600;
  color: var(--color-ink);
  font-size: 14px;
  margin-bottom: 3px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.rule-meta {
  font-size: 14px;
  color: var(--color-muted);
}

.active-badge {
  color: var(--color-success);
  font-weight: 600;
}

.rule-config-section {
  margin-top: 30px;
  padding: 25px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-md);
  border: 2px solid var(--color-border);
}

.rule-config-header {
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 2px solid var(--color-border);
}

.rule-config-title {
  font-size: 18px;
  font-weight: 700;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 10px;
}

.rule-config-title i {
  color: var(--color-brand);
}

.rule-payment-settings {
  margin-bottom: 25px;
  padding: 20px;
  background: var(--color-surface);
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border);
}

.rule-payment-settings h4 {
  font-size: 16px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 15px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.rule-payment-settings h4 i {
  color: var(--color-brand);
}

.payment-settings-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 15px;
}

.payment-field {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.payment-field label {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
}

.form-select {
  width: 100%;
  padding: 10px 12px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: 14px;
  transition: all 0.3s ease;
}

.form-select:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.form-input {
  width: 100%;
  padding: 10px 12px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: 14px;
  transition: all 0.3s ease;
}

.form-input:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.rule-products-section {
  margin-bottom: 25px;
}

.rule-products-section h4 {
  font-size: 16px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 15px;
}

.table-wrapper {
  overflow-x: auto;
  margin-bottom: 15px;
}

.product-commission-table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 15px;
}

.product-commission-table th {
  background: var(--color-surface-soft);
  padding: 10px 12px;
  text-align: left;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
  border: 1px solid var(--color-border);
}

.product-commission-table td {
  padding: 10px 12px;
  font-size: 14px;
  color: var(--color-ink);
  border: 1px solid var(--color-border);
}

.btn {
  padding: 8px 16px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.btn-success {
  background: var(--color-success-solid);
  color: white;
  box-shadow: 0 2px 8px rgba(72, 187, 120, 0.3);
}

.btn-success:hover:not(:disabled) {
  background: var(--color-success-solid);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(72, 187, 120, 0.4);
}

.btn-icon {
  background: none;
  border: none;
  cursor: pointer;
  padding: 6px;
  border-radius: 4px;
  transition: all 0.2s ease;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.btn-delete {
  color: var(--color-danger);
}

.btn-delete:hover {
  background: var(--color-danger-soft);
}

.toggle-additional-rules {
  margin-top: 20px;
  padding: 15px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-md);
  border: 2px dashed var(--color-border-strong);
}

.toggle-additional-rules .toggle-label {
  display: flex;
  justify-content: space-between;
  align-items: center;
  cursor: pointer;
}

.toggle-additional-rules .toggle-label span {
  font-weight: 600;
  color: var(--color-ink);
  font-size: 14px;
}

.toggle-additional-rules p {
  font-size: 14px;
  color: var(--color-muted);
  margin-top: 8px;
  margin-bottom: 0;
}

.toggle-switch {
  position: relative;
  width: 50px;
  height: 26px;
  background: var(--color-border-strong);
  border-radius: 13px;
  cursor: pointer;
  transition: all 0.4s ease;
}

.toggle-switch.active {
  background: var(--color-success-solid);
}

.toggle-switch::before {
  content: "";
  position: absolute;
  height: 18px;
  width: 18px;
  left: 4px;
  bottom: 4px;
  background-color: var(--color-surface);
  border-radius: 50%;
  transition: all 0.4s ease;
}

.toggle-switch.active::before {
  transform: translateX(24px);
}

.additional-rule-section {
  margin-top: 20px;
  padding: 20px;
  background: var(--color-surface);
  border-radius: var(--radius-md);
  border: 2px solid var(--color-border);
}

.btn-manage-tiers {
  background: var(--color-brand-solid);
  color: white;
  padding: 6px 12px;
  font-size: 14px;
}

.btn-manage-tiers:hover:not(:disabled) {
  background: var(--color-brand-strong);
}

.btn-disabled {
  background: var(--color-border);
  color: var(--color-faint);
  cursor: not-allowed;
}

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.6);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.modal-container {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  width: 90%;
  max-width: 800px;
  max-height: 90vh;
  overflow-y: auto;
  animation: slideUp 0.3s ease;
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.modal-header {
  padding: 25px 30px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--color-brand-solid);
  color: white;
  border-radius: 12px 12px 0 0;
}

.modal-header h3 {
  font-size: 20px;
  font-weight: 700;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 12px;
}

.modal-close {
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
  font-size: 24px;
  transition: all 0.3s ease;
}

.modal-close:hover {
  background: rgba(255, 255, 255, 0.3);
}

.modal-body {
  padding: 30px;
}

.modal-footer {
  padding: 20px 30px;
  border-top: 2px solid var(--color-border);
  background: var(--color-surface-soft);
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  border-radius: 0 0 12px 12px;
}

.btn-primary {
  background: var(--color-brand-solid);
  color: white;
  box-shadow: 0 2px 8px rgba(var(--color-brand-rgb), 0.3);
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.4);
}

.btn-secondary {
  background: var(--color-border);
  color: var(--color-text);
}

.btn-secondary:hover {
  background: var(--color-border-strong);
}

.tiers-list {
  margin-bottom: 20px;
}

.tier-edit-item {
  background: var(--color-surface-soft);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 20px;
  margin-bottom: 15px;
}

.tier-edit-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
  padding-bottom: 10px;
  border-bottom: 1px solid var(--color-border);
}

.tier-edit-level {
  font-size: 16px;
  font-weight: 700;
  color: var(--color-brand);
}

.tier-edit-content {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 15px;
}

.tier-edit-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.tier-edit-field label {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-muted);
}

.btn-add-tier {
  width: 100%;
  padding: 12px;
  background: var(--color-brand-soft);
  border: 2px dashed var(--color-brand);
  border-radius: var(--radius-md);
  color: var(--color-brand);
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.btn-add-tier:hover {
  background: var(--color-brand-soft);
  border-color: var(--color-brand-strong);
}

@media (max-width: 768px) {
  .payment-settings-grid {
    grid-template-columns: 1fr;
  }

  .tier-edit-content {
    grid-template-columns: 1fr;
  }
}

.rules-title {
  font-weight: 600;
  color: var(--color-ink);
  font-size: 14px;
  margin-bottom: 15px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.rules-selection-box {
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 20px;
  margin-bottom: 20px;
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px;
}

.rule-checkbox {
  padding: 12px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-sm);
  border: 2px solid transparent;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  gap: 12px;
  cursor: pointer;
}

.rule-checkbox:hover {
  border-color: var(--color-border-strong);
}

.rule-checkbox:has(input:checked) {
  background: var(--color-brand-soft);
  border-color: var(--color-brand);
}

.rule-checkbox input[type="checkbox"] {
  width: 18px;
  height: 18px;
  accent-color: var(--color-brand);
  cursor: pointer;
  flex-shrink: 0;
}

.rule-checkbox label {
  cursor: pointer;
  flex: 1;
}

.rule-name {
  font-weight: 600;
  color: var(--color-ink);
  font-size: 14px;
  margin-bottom: 3px;
}

.rule-meta {
  font-size: 14px;
  color: var(--color-muted);
}

.meta-highlight {
  color: var(--color-success);
  font-weight: 600;
}

.preview-title {
  font-weight: 600;
  color: var(--color-ink);
  font-size: 14px;
  margin-bottom: 15px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.document-item {
  background: var(--color-surface-soft);
  padding: 15px 20px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  margin-bottom: 10px;
  display: grid;
  grid-template-columns: auto 1fr auto auto;
  gap: 15px;
  align-items: center;
  transition: all 0.2s ease;
}

.document-item:last-child {
  margin-bottom: 0;
}

.document-item:hover {
  background: var(--color-brand-soft);
  border-color: var(--color-brand);
}

.document-icon {
  width: 40px;
  height: 40px;
  border-radius: var(--radius-md);
  background: var(--color-brand-soft);
  color: var(--color-brand);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
}

.document-info {
  flex: 1;
}

.document-name {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 3px;
}

.document-size {
  font-size: 14px;
  color: var(--color-muted);
}

.document-date {
  font-size: 14px;
  color: var(--color-faint);
}

.btn-download {
  background: var(--color-brand-soft);
  color: var(--color-brand);
  border: none;
  padding: 8px 12px;
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
}

.btn-download:hover {
  background: var(--color-brand-solid);
  color: white;
}

.empty-state {
  text-align: center;
  padding: 40px;
  color: var(--color-faint);
}

.empty-state i {
  font-size: 48px;
  margin-bottom: 15px;
}

.empty-state p {
  font-size: 14px;
  color: var(--color-muted);
}

.rule-preview-card {
  margin-bottom: 20px;
  padding: 25px;
  background: var(--color-surface);
  border-radius: var(--radius-md);
  border: 2px solid var(--color-brand);
  box-shadow: 0 2px 8px rgba(var(--color-brand-rgb), 0.15);
  transition: all 0.3s ease;
}

.rule-preview-card:hover {
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.25);
  transform: translateY(-2px);
}

.rule-preview-header {
  display: flex;
  justify-content: space-between;
  align-items: start;
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 2px solid var(--color-border);
}

.rule-preview-name {
  font-weight: 600;
  color: var(--color-ink);
  font-size: 16px;
  margin-bottom: 5px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.rule-preview-desc {
  font-size: 14px;
  color: var(--color-muted);
}

.preview-status-badge {
  padding: 4px 12px;
  border-radius: var(--radius-lg);
  font-size: 14px;
  font-weight: 600;
  text-transform: uppercase;
  flex-shrink: 0;
}

.preview-status-badge.active {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.rule-section {
  margin-bottom: 20px;
}

.rule-section:last-child {
  margin-bottom: 0;
}

.rule-section h4 {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 12px;
  padding-bottom: 10px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  align-items: center;
  gap: 8px;
}

.rule-section h4 i {
  color: var(--color-brand);
}

.rule-info-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 15px;
  padding: 15px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-md);
}

.rule-info-item {
  display: flex;
  flex-direction: column;
}

.rule-info-label {
  font-size: 14px;
  color: var(--color-muted);
  font-weight: 600;
  text-transform: uppercase;
  margin-bottom: 4px;
}

.rule-info-value {
  font-size: 14px;
  color: var(--color-ink);
  font-weight: 600;
}

.product-structure-badge {
  padding: 12px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-sm);
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: var(--color-ink);
  font-weight: 600;
}

.product-structure-badge i {
  color: var(--color-brand);
}

.additional-status {
  padding: 15px;
  border-radius: var(--radius-md);
  border-left: 3px solid var(--color-warning);
  background: var(--color-warning-soft);
}

.additional-status.enabled {
  background: var(--color-success-soft);
  border-left-color: var(--color-success);
}

.additional-header {
  font-weight: 600;
  color: var(--color-ink);
  font-size: 14px;
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.additional-header i {
  font-size: 14px;
}

.additional-status.enabled .additional-header i {
  color: var(--color-success);
}

.additional-status:not(.enabled) .additional-header i {
  color: var(--color-warning);
}

.additional-content {
  font-size: 14px;
  line-height: 1.6;
}

.additional-status.enabled .additional-content {
  color: var(--color-success);
}

.additional-status:not(.enabled) .additional-content {
  color: var(--color-muted);
}

.empty-state-rules {
  text-align: center;
  padding: 40px 20px;
  color: var(--color-faint);
  background: var(--color-surface);
  border: 2px dashed var(--color-border);
  border-radius: var(--radius-md);
  margin-top: 10px;
}

.empty-state-rules i {
  font-size: 48px;
  margin-bottom: 15px;
  display: block;
}

.empty-state-rules p {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-muted);
  margin: 0;
}

.empty-subtitle {
  font-size: 14px;
  color: var(--color-faint);
  margin-top: 5px !important;
}

/* Network Graph Styles */
.network-container {
  background: var(--color-surface);
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

.zoom-indicator {
  position: absolute;
  top: 20px;
  right: 20px;
  background: rgba(var(--color-brand-rgb), 0.95);
  color: white;
  padding: 8px 16px;
  border-radius: 20px;
  font-size: 14px;
  font-weight: 600;
  z-index: 100;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
  pointer-events: none;
  display: flex;
  align-items: center;
  gap: 8px;
}

.network-graph {
  display: flex;
  flex-direction: row;
  align-items: flex-start;
  min-width: max-content;
  padding: 20px;
  transform-origin: 0 0;
  transition: transform 0.1s ease-out;
}

.network-branch {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 60px;
  position: relative;
}

.network-node {
  position: relative;
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 20px;
}

.network-children {
  display: none;
  flex-direction: column;
  gap: 30px;
  margin-left: 60px;
}

.network-children.expanded {
  display: flex;
}

.node-card {
  background: var(--color-surface);
  border: 3px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: 16px 20px;
  min-width: 200px;
  max-width: 280px;
  text-align: left;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  position: relative;
}

.node-card:hover {
  transform: translateX(4px);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
  border-color: var(--color-brand);
}

.node-card.tier1 {
  background: var(--color-brand-solid);
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
  font-size: 14px;
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
  font-size: 14px;
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
  font-size: 14px;
  flex-wrap: wrap;
}

.node-card.client .node-stats {
  border-top: 1px solid var(--color-border);
  margin-top: 6px;
  padding-top: 6px;
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
  background: var(--color-surface);
  border: 3px solid var(--color-border-strong);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.3s ease;
  font-size: 16px;
  font-weight: 700;
  color: var(--color-text);
  flex-shrink: 0;
  z-index: 10;
}

.expand-btn:hover {
  background: var(--color-brand-solid);
  border-color: var(--color-brand);
  color: white;
  transform: scale(1.1);
}

.expand-btn.expanded {
  background: var(--color-brand-solid);
  border-color: var(--color-brand);
  color: white;
}

.network-summary {
  text-align: center;
  margin-top: 20px;
  padding: 15px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-md);
  display: flex;
  justify-content: space-around;
  align-items: center;
}

.summary-item {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.summary-value {
  font-size: 24px;
  font-weight: 700;
  color: var(--color-brand);
}

.summary-label {
  font-size: 14px;
  color: var(--color-muted);
  margin-top: 4px;
}

@media (max-width: 768px) {
  .detail-sections {
    grid-template-columns: 1fr;
  }

  .rule-info-grid {
    grid-template-columns: 1fr;
  }

  .network-container {
    min-height: 400px;
  }

  .network-summary {
    flex-wrap: wrap;
    gap: 15px;
  }

  .payment-settings-grid {
    grid-template-columns: 1fr;
  }

  .tier-edit-content {
    grid-template-columns: 1fr;
  }

  .signature-info-row {
    grid-template-columns: repeat(2, 1fr);
  }
}

/* Documents & Contracts Styles */
.detail-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 2px solid var(--color-border);
}

.detail-card-title {
  font-size: 16px;
  color: var(--color-ink);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 10px;
}

.detail-card-title i {
  color: var(--color-brand);
}

.documents-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 20px;
  padding: 15px 0;
}

.document-card {
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 20px;
  transition: all 0.3s ease;
  cursor: pointer;
}

.document-card:hover {
  border-color: var(--color-brand);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.15);
  transform: translateY(-2px);
}

.document-card-header {
  display: flex;
  align-items: start;
  gap: 12px;
  margin-bottom: 15px;
}

.document-icon {
  width: 40px;
  height: 40px;
  background: var(--color-brand-solid);
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 18px;
  flex-shrink: 0;
}

.document-info {
  flex: 1;
}

.document-title {
  font-size: 15px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 5px;
}

.document-date {
  font-size: 14px;
  color: var(--color-muted);
  display: flex;
  align-items: center;
  gap: 5px;
}

.document-date i {
  /* @font-floor-exempt: visual-only metadata glyph */
  font-size: 11px;
}

.document-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 15px;
}

.document-meta-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  color: var(--color-text);
  padding: 4px 10px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-sm);
}

.document-meta-item i {
  color: var(--color-brand);
  /* @font-floor-exempt: visual-only metadata glyph */
  font-size: 12px;
}

.document-status {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: 600;
  text-transform: uppercase;
}

.document-status.signed {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.document-status i {
  /* @font-floor-exempt: visual-only status glyph */
  font-size: 11px;
}

.document-actions {
  display: flex;
  gap: 8px;
}

.btn-doc {
  flex: 1;
  padding: 8px 12px;
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
}

.btn-view {
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.btn-view:hover {
  background: var(--color-brand-solid);
  color: white;
}

.btn-download {
  background: var(--color-border);
  color: var(--color-text);
}

.btn-download:hover {
  background: var(--color-border-strong);
}

.loading-state {
  text-align: center;
  padding: 40px 20px;
  color: var(--color-muted);
}

.loading-state i {
  font-size: 32px;
  margin-bottom: 15px;
  color: var(--color-brand);
}

.loading-state p {
  font-size: 14px;
}

.empty-state {
  grid-column: 1 / -1;
  text-align: center;
  padding: 40px 20px;
  color: var(--color-faint);
}

.empty-state i {
  font-size: 48px;
  margin-bottom: 15px;
  display: block;
}

.empty-state p {
  font-size: 14px;
  font-style: italic;
}

/* Document Modal Styles */
.modal {
  position: fixed;
  z-index: 10000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.7);
  animation: fadeIn 0.3s ease;
}

.modal-content {
  background-color: var(--color-surface);
  margin: 3% auto;
  padding: 0;
  border-radius: var(--radius-lg);
  max-width: 900px;
  width: 90%;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  animation: slideIn 0.3s ease;
}

.modal-header {
  background: var(--color-brand-solid);
  color: white;
  padding: 20px 30px;
  border-radius: 12px 12px 0 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.modal-header h2 {
  margin: 0;
  font-size: 20px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.close {
  color: white;
  font-size: 28px;
  font-weight: 300;
  cursor: pointer;
  transition: transform 0.2s ease;
  line-height: 1;
}

.close:hover {
  transform: rotate(90deg);
}

.modal-body {
  padding: 25px;
  overflow-y: auto;
  flex: 1;
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

.document-preview-content h4 {
  color: var(--color-ink);
  margin-top: 20px;
  margin-bottom: 10px;
  font-size: 16px;
}

.document-preview-content p {
  margin-bottom: 15px;
}

.document-preview-content ul {
  margin-left: 20px;
  margin-bottom: 15px;
}

.document-preview-content li {
  margin-bottom: 8px;
}

.document-signature {
  background: var(--color-warning-soft);
  border: 2px solid #f6b93b;
  border-radius: var(--radius-md);
  padding: 20px;
  margin-top: 20px;
}

.document-signature h4 {
  font-size: 15px;
  color: var(--color-ink);
  margin-bottom: 15px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.document-signature h4 i {
  color: #f6b93b;
}

.signature-info-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
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
  font-weight: 600;
  letter-spacing: 0.5px;
}

.signature-field .signature-value {
  font-size: 14px;
  color: var(--color-ink);
  font-weight: 600;
}

.modal-footer {
  padding: 15px 25px;
  background: var(--color-surface-soft);
  border-top: 1px solid var(--color-border);
  border-radius: 0 0 12px 12px;
  display: flex;
  gap: 12px;
  justify-content: flex-end;
}

.btn-modal {
  padding: 10px 20px;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-modal-secondary {
  background: var(--color-border);
  color: var(--color-text);
}

.btn-modal-secondary:hover {
  background: var(--color-border-strong);
}

.btn-modal-primary {
  background: var(--color-brand-solid);
  color: white;
  box-shadow: 0 2px 8px rgba(var(--color-brand-rgb), 0.3);
}

.btn-modal-primary:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.4);
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

@keyframes slideIn {
  from {
    transform: translateY(-50px);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}
</style>
