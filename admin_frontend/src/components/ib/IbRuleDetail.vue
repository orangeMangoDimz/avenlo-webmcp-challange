<template>
  <div class="detail-content">
    <div class="detail-sections">
      <!-- Rule Information -->
      <div class="detail-section">
        <h3>
          <div class="section-header">
            <div class="section-title">
              <i class="fas fa-info-circle"></i> Rule Information
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
        <div class="detail-field">
          <span class="detail-label">Rule Name</span>
          <input
            type="text"
            v-model="localRule.ruleName"
            @input="markInfoChanged"
            class="detail-input"
          />
        </div>
        <div class="detail-field">
          <span class="detail-label">Rule Type</span>
          <select
            v-model="localRule.ruleType"
            @change="markInfoChanged"
            class="detail-select"
          >
            <option value="standard">Standard</option>
            <option value="premium">Premium</option>
            <option value="ultra">Ultra</option>
            <option value="custom">Custom</option>
          </select>
        </div>
        <div class="detail-field">
          <span class="detail-label">Description</span>
          <input
            type="text"
            v-model="localRule.ruleDescription"
            @input="markInfoChanged"
            class="detail-input"
          />
        </div>
        <div class="detail-field">
          <span class="detail-label">Target Region</span>
          <select
            v-model="localRule.targetRegion"
            @change="markInfoChanged"
            class="detail-select"
          >
            <option value="global">Global</option>
            <option value="asia">Asia Pacific</option>
            <option value="europe">Europe</option>
            <option value="americas">Americas</option>
            <option value="mena">MENA</option>
          </select>
        </div>
        <div class="detail-field">
          <span class="detail-label">Status</span>
          <select
            v-model="localRule.status"
            @change="markInfoChanged"
            class="detail-select"
          >
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="draft">Draft</option>
          </select>
        </div>
        <div class="detail-field">
          <span class="detail-label">Created Date</span>
          <span class="detail-value">{{ formatDate(rule.createdAt) }}</span>
        </div>
      </div>

      <!-- Payment Settings -->
      <div class="detail-section">
        <h3>
          <div class="section-header">
            <div class="section-title">
              <i class="fas fa-calendar-alt"></i> Payment Settings
            </div>
            <button
              class="btn-save"
              :class="{ active: hasPaymentChanges }"
              :disabled="!hasPaymentChanges || saving"
              @click="savePayment"
            >
              <i
                class="fas"
                :class="saving ? 'fa-spinner fa-spin' : 'fa-save'"
              ></i>
              Save
            </button>
          </div>
        </h3>
        <div class="detail-field">
          <span class="detail-label">Payment Cycle</span>
          <select
            v-model="localRule.paymentCycle"
            @change="handlePaymentCycleChange"
            class="detail-select"
          >
            <option value="realtime">
              {{ t("ibRuleMgmt_payCycle_realtime") }}
            </option>
            <option value="daily">Daily</option>
            <option value="weekly">Weekly</option>
            <option value="biweekly">Bi-weekly</option>
            <option value="monthly">Monthly</option>
            <option value="quarterly">Quarterly</option>
          </select>
        </div>
        <div class="detail-field">
          <span class="detail-label">Payment Day</span>

          <!-- Real-time: 自动设置，禁用输入 -->
          <input
            v-if="localRule.paymentCycle === 'realtime'"
            type="text"
            v-model="localRule.paymentDay"
            class="detail-input"
            disabled
            style="
              background: var(--color-surface-soft);
              color: var(--color-faint);
              cursor: not-allowed;
            "
          />

          <!-- Daily: 下拉选择 -->
          <select
            v-else-if="localRule.paymentCycle === 'daily'"
            v-model="localRule.paymentDay"
            @change="markPaymentChanged"
            class="detail-select"
          >
            <option value="everyday">Every Day</option>
            <option value="weekdays">Weekdays Only (Mon-Fri)</option>
            <option value="weekends">Weekends Only (Sat-Sun)</option>
          </select>

          <!-- Weekly: 多选星期 -->
          <select
            v-else-if="localRule.paymentCycle === 'weekly'"
            v-model="localRule.paymentDay"
            @change="markPaymentChanged"
            class="detail-select"
          >
            <option value="Monday">Monday</option>
            <option value="Tuesday">Tuesday</option>
            <option value="Wednesday">Wednesday</option>
            <option value="Thursday">Thursday</option>
            <option value="Friday">Friday</option>
            <option value="Saturday">Saturday</option>
            <option value="Sunday">Sunday</option>
          </select>

          <!-- Bi-weekly: 预设选项 -->
          <select
            v-else-if="localRule.paymentCycle === 'biweekly'"
            v-model="localRule.paymentDay"
            @change="markPaymentChanged"
            class="detail-select"
          >
            <option value="1-15">1st & 15th of month</option>
            <option value="5-20">5th & 20th of month</option>
            <option value="10-25">10th & 25th of month</option>
            <option value="15-30">15th & Last day of month</option>
          </select>

          <!-- Monthly: 数字选择（1-31） -->
          <select
            v-else-if="localRule.paymentCycle === 'monthly'"
            v-model="localRule.paymentDay"
            @change="markPaymentChanged"
            class="detail-select"
          >
            <option value="1">1st of month</option>
            <option value="5">5th of month</option>
            <option value="10">10th of month</option>
            <option value="15">15th of month</option>
            <option value="20">20th of month</option>
            <option value="25">25th of month</option>
            <option value="last">Last day of month</option>
          </select>

          <!-- Quarterly: 季度日期 -->
          <select
            v-else-if="localRule.paymentCycle === 'quarterly'"
            v-model="localRule.paymentDay"
            @change="markPaymentChanged"
            class="detail-select"
          >
            <option value="1">1st of quarter</option>
            <option value="15">15th of quarter</option>
            <option value="last">Last day of quarter</option>
          </select>

          <!-- 默认：文本输入 -->
          <input
            v-else
            type="text"
            v-model="localRule.paymentDay"
            @input="markPaymentChanged"
            class="detail-input"
            placeholder="Enter payment day"
          />
        </div>
        <div class="detail-field">
          <span class="detail-label">Min. Payout Amount</span>
          <div style="display: flex; align-items: center; gap: 10px">
            <input
              type="number"
              v-model.number="localRule.minimumPayout"
              @input="markPaymentChanged"
              class="detail-input"
              step="0.01"
              min="0"
            />
            <span style="color: var(--color-muted); font-size: 14px">USD</span>
          </div>
        </div>
        <div class="detail-field">
          <span class="detail-label">Currency</span>
          <select
            v-model="localRule.payoutCurrency"
            @change="markPaymentChanged"
            class="detail-select"
          >
            <option value="USD">USD</option>
            <option value="EUR">EUR</option>
            <option value="GBP">GBP</option>
            <option value="JPY">JPY</option>
            <option value="AUD">AUD</option>
          </select>
        </div>
        <div class="detail-field">
          <span class="detail-label">Auto Payment</span>
          <select
            v-model="localRule.autoPaymentEnabled"
            @change="markPaymentChanged"
            class="detail-select"
          >
            <option :value="1">Enabled</option>
            <option :value="0">Disabled</option>
          </select>
        </div>
      </div>

      <!-- Product Commission Configuration -->
      <div class="detail-section full-width">
        <h3>
          <div class="section-header">
            <div class="section-title">
              <i class="fas fa-chart-line"></i> Product Commission Configuration
            </div>
            <button
              class="btn-save"
              :class="{ active: hasProductChanges }"
              :disabled="!hasProductChanges || saving"
              @click="saveProducts"
            >
              <i
                class="fas"
                :class="saving ? 'fa-spinner fa-spin' : 'fa-save'"
              ></i>
              Save
            </button>
          </div>
        </h3>

        <div class="product-actions">
          <button class="btn btn-success" @click="addProduct" style="flex: 1">
            <i class="fas fa-plus"></i> Add Product
          </button>
          <button class="btn btn-secondary" @click="openAddSecurityModal">
            <i class="fas fa-layer-group"></i> Add Security
          </button>
          <button class="btn btn-secondary" @click="openAddSymbolModal">
            <i class="fas fa-tag"></i> Add Symbol
          </button>
          <button class="btn btn-secondary" @click="syncProduct">
            <i class="fas fa-sync"></i> Sync Product
          </button>
        </div>

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
              <tr v-for="(product, index) in products" :key="index">
                <td>
                  <select
                    v-model="product.productName"
                    @change="markProductChanged"
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
                    <optgroup label="Symbols" v-if="customSymbols.length > 0">
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
                    @change="markProductChanged"
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
                    @input="markProductChanged"
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
                    @input="markProductChanged"
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
                    @input="markProductChanged"
                    class="form-input"
                    placeholder="Min Vol"
                  />
                </td>
                <td style="text-align: center">
                  <button
                    class="btn-icon btn-delete"
                    @click="removeProduct(index)"
                    title="Delete"
                  >
                    <i class="fas fa-trash"></i>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Commission Type Guide -->
        <div class="info-guide">
          <strong
            ><i class="fas fa-info-circle"></i> Commission Type Guide</strong
          >

          <div class="guide-item">
            <strong style="color: var(--color-brand)"
              >📊 Per Lot (Volume-based)</strong
            >
            <div class="guide-detail">
              <div class="guide-row">
                <span class="bullet">•</span> <strong>Rate:</strong> Base
                commission per lot (e.g., $10.00/lot)
              </div>
              <div class="guide-row">
                <span class="bullet">•</span> <strong>Additional:</strong> Bonus
                commission per lot (triggered when monthly volume exceeds
                threshold)
              </div>
              <div class="guide-row">
                <span class="bullet">•</span> <strong>Formula:</strong>
                <code
                  >Total = (Rate × Lots) + (Additional × Lots if volume >
                  threshold)</code
                >
              </div>
              <div class="guide-row">
                <span class="bullet">•</span> <strong>Example:</strong> Rate
                $10/lot + Bonus $2/lot (if >1000 lots/month) = $12/lot for
                high-volume traders
              </div>
            </div>
          </div>

          <div class="guide-item">
            <strong style="color: var(--color-warning)"
              >📈 Percentage (Spread-based)</strong
            >
            <div class="guide-detail">
              <div class="guide-row">
                <span class="bullet">•</span> <strong>Rate:</strong> Percentage
                of spread (e.g., 30% of spread)
              </div>
              <div class="guide-row">
                <span class="bullet">•</span>
                <strong>Additional:</strong> Minimum or Maximum cap per lot
                (ensures commission stays within bounds)
              </div>
              <div class="guide-row">
                <span class="bullet">•</span> <strong>Formula:</strong>
                <code
                  >Commission = (Spread × Rate%) capped between [Min, Max]</code
                >
              </div>
              <div class="guide-row">
                <span class="bullet">•</span> <strong>Example:</strong> 30%
                spread with Min $2/lot, Max $50/lot = commission between $2-$50
              </div>
            </div>
          </div>

          <div class="guide-item">
            <strong style="color: var(--color-brand)"
              >🎯 Per Trade (Trade-based)</strong
            >
            <div class="guide-detail">
              <div class="guide-row">
                <span class="bullet">•</span> <strong>Rate:</strong> Fixed
                commission per trade (e.g., $5.00/trade)
              </div>
              <div class="guide-row">
                <span class="bullet">•</span> <strong>Additional:</strong> Bonus
                per trade (triggered when trade count or volume exceeds
                threshold)
              </div>
              <div class="guide-row">
                <span class="bullet">•</span> <strong>Formula:</strong>
                <code
                  >Total = (Rate × Trades) + (Additional × Trades if conditions
                  met)</code
                >
              </div>
              <div class="guide-row">
                <span class="bullet">•</span> <strong>Example:</strong> $5/trade
                + $0.50 bonus (if >100 trades/month) = $5.50/trade
              </div>
            </div>
          </div>

          <div class="guide-item">
            <strong style="color: #48bb78">💰 Cash Back (Rebate)</strong>
            <div class="guide-detail">
              <div class="guide-row">
                <span class="bullet">•</span> <strong>Rate:</strong> Cash back
                amount per lot (e.g., $3.00/lot returned to IB)
              </div>
              <div class="guide-row">
                <span class="bullet">•</span> <strong>Additional:</strong> N/A
                (not applicable for cash back)
              </div>
              <div class="guide-row">
                <span class="bullet">•</span> <strong>Formula:</strong>
                <code>Cash Back = Rate × Lots</code>
              </div>
              <div class="guide-row">
                <span class="bullet">•</span> <strong>Example:</strong> $3/lot
                cash back = direct rebate of $3 for every lot traded
              </div>
            </div>
          </div>

          <div class="guide-item">
            <strong style="color: #ec4899">🔀 Hybrid (Combined)</strong>
            <div class="guide-detail">
              <div class="guide-row">
                <span class="bullet">•</span> <strong>Rate:</strong> Base
                commission per lot (fixed component)
              </div>
              <div class="guide-row">
                <span class="bullet">•</span>
                <strong>Additional:</strong> Additional percentage of spread
                (variable component)
              </div>
              <div class="guide-row">
                <span class="bullet">•</span> <strong>Formula:</strong>
                <code>Total = (Rate × Lots) + (Spread × Additional%)</code>
              </div>
              <div class="guide-row">
                <span class="bullet">•</span> <strong>Example:</strong> Base
                $5/lot + 15% of spread = $5 + (spread × 0.15) per lot
              </div>
            </div>
          </div>
        </div>

        <!-- Toggle Additional Rules -->
        <div class="toggle-additional-rules">
          <label class="toggle-label">
            <span>Enable Additional Rules & Tiers</span>
            <div
              class="toggle-switch"
              :class="{ active: additionalRulesEnabled }"
              @click="toggleAdditionalRules"
            ></div>
          </label>
          <p>
            Enable volume-based tiers and additional commission rules for this
            package
          </p>
        </div>

        <!-- Additional Rules Section -->
        <div v-if="additionalRulesEnabled" class="additional-rule-section">
          <div class="section-header">
            <div class="section-title">
              <i class="fas fa-table"></i> Configure Additional Rules & Tiers
            </div>
            <button
              class="btn-save"
              :class="{ active: hasAdditionalRulesChanges }"
              :disabled="!hasAdditionalRulesChanges || saving"
              @click="saveAdditionalRules"
            >
              <i
                class="fas"
                :class="saving ? 'fa-spinner fa-spin' : 'fa-save'"
              ></i>
              Save
            </button>
          </div>

          <div class="info-banner-warning">
            <i class="fas fa-lightbulb"></i>
            <strong>Custom Condition:</strong> Select "✏️ Custom Condition..."
            in the Condition dropdown to enter your own custom criteria (e.g.,
            "Active clients > 75", "Quarterly profit > $100,000")
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
                <tr v-for="(rule, index) in additionalRules" :key="index">
                  <td>
                    <select
                      v-model="rule.productName"
                      @change="markAdditionalRulesChanged"
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
                      <optgroup label="Symbols" v-if="customSymbols.length > 0">
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
                      @change="updateAdditionalRuleColumns(index)"
                      class="form-select"
                    >
                      <option value="bonus_commission">Bonus Commission</option>
                      <option value="volume_tiers">Volume-based Tiers</option>
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
                      @input="markAdditionalRulesChanged"
                      class="form-input"
                      step="0.01"
                      min="0"
                      :placeholder="getValuePlaceholder(rule.ruleType)"
                    />
                    <input
                      v-else
                      type="text"
                      :value="rule.tierCount || '0' + ' Tiers'"
                      disabled
                      class="form-input"
                      placeholder="Managed via Tiers"
                    />
                  </td>
                  <td>
                    <select
                      v-if="rule.ruleType !== 'volume_tiers'"
                      v-model="rule.ruleCondition"
                      @change="markAdditionalRulesChanged"
                      class="form-select"
                    >
                      <option value="">Select threshold...</option>
                      <option value=">500">Volume > 500 lots/month</option>
                      <option value=">1000">Volume > 1000 lots/month</option>
                      <option value=">2000">Volume > 2000 lots/month</option>
                      <option value=">5000">Volume > 5000 lots/month</option>
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
                      @click="openManageTiersModal(index)"
                    >
                      <i class="fas fa-edit"></i> Manage ({{
                        rule.tierCount || 0
                      }})
                    </button>
                    <button v-else disabled class="btn btn-disabled">
                      <i class="fas fa-ban"></i> N/A
                    </button>
                  </td>
                  <td style="text-align: center">
                    <button
                      class="btn-icon btn-delete"
                      @click="removeAdditionalRule(index)"
                      title="Delete"
                    >
                      <i class="fas fa-trash"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <button
            class="btn btn-success"
            @click="addAdditionalRule"
            style="width: 100%; margin-top: 10px"
          >
            <i class="fas fa-plus"></i> Add Additional Rule
          </button>

          <!-- Additional Rules Guide -->
          <div
            class="info-guide"
            style="
              margin-top: 20px;
              background: var(--color-warning-soft);
              border-left-color: #f59e0b;
            "
          >
            <strong
              ><i class="fas fa-gift"></i> Additional Commission Rule Types
              Guide</strong
            >

            <div class="guide-item">
              <strong style="color: var(--color-brand)"
                >🎁 Bonus Commission</strong
              >
              <div class="guide-detail">
                <div class="guide-row">
                  <span class="bullet">•</span> <strong>Purpose:</strong> Reward
                  high-volume IB partners with extra commission
                </div>
                <div class="guide-row">
                  <span class="bullet">•</span>
                  <strong>When triggered:</strong> When IB's monthly trading
                  volume exceeds the threshold
                </div>
                <div class="guide-row">
                  <span class="bullet">•</span> <strong>Formula:</strong>
                  <code>Bonus = Value × Lots (if condition met)</code>
                </div>
                <div class="guide-row">
                  <span class="bullet">•</span> <strong>Example:</strong> $2/lot
                  bonus when volume > 1000 lots/month
                </div>
              </div>
            </div>

            <div class="guide-item">
              <strong style="color: var(--color-warning)"
                >📊 Volume-based Tiers</strong
              >
              <div class="guide-detail">
                <div class="guide-row">
                  <span class="bullet">•</span>
                  <strong>Purpose:</strong> Progressive commission rates based
                  on trading volume levels
                </div>
                <div class="guide-row">
                  <span class="bullet">•</span>
                  <strong>When triggered:</strong> Automatically applied based
                  on IB's monthly volume
                </div>
                <div class="guide-row">
                  <span class="bullet">•</span> <strong>Formula:</strong>
                  <code
                    >Commission = Tier_Rate × Lots (rate changes by tier)</code
                  >
                </div>
                <div class="guide-row">
                  <span class="bullet">•</span> <strong>Example:</strong> 0-100
                  lots = $8/lot, 101-500 lots = $10/lot, 501+ = $12/lot
                </div>
                <div class="guide-row">
                  <span class="bullet">•</span> <strong>Note:</strong> Click
                  "Manage Tiers" button to configure multiple tier levels
                </div>
              </div>
            </div>

            <div class="guide-item">
              <strong style="color: var(--color-brand)"
                >⚡ Volume Multiplier</strong
              >
              <div class="guide-detail">
                <div class="guide-row">
                  <span class="bullet">•</span>
                  <strong>Purpose:</strong> Multiply base commission for top
                  performing IBs
                </div>
                <div class="guide-row">
                  <span class="bullet">•</span>
                  <strong>When triggered:</strong> When IB meets volume or
                  ranking threshold
                </div>
                <div class="guide-row">
                  <span class="bullet">•</span> <strong>Formula:</strong>
                  <code
                    >Total Commission = Base_Commission × Multiplier (if
                    condition met)</code
                  >
                </div>
                <div class="guide-row">
                  <span class="bullet">•</span> <strong>Example:</strong> 1.25x
                  multiplier for volume > 2000 lots = 25% more commission
                </div>
              </div>
            </div>

            <div class="guide-item">
              <strong style="color: #10b981">🏆 Performance Bonus</strong>
              <div class="guide-detail">
                <div class="guide-row">
                  <span class="bullet">•</span> <strong>Purpose:</strong> Reward
                  IB partners for excellent performance metrics
                </div>
                <div class="guide-row">
                  <span class="bullet">•</span>
                  <strong>When triggered:</strong> When IB meets specific
                  performance criteria (retention, growth, etc.)
                </div>
                <div class="guide-row">
                  <span class="bullet">•</span> <strong>Formula:</strong>
                  <code>Bonus = Base_Commission × (Value% / 100)</code>
                </div>
                <div class="guide-row">
                  <span class="bullet">•</span> <strong>Example:</strong> 25%
                  bonus for top 10% performers = extra 25% on top of base
                  commission
                </div>
              </div>
            </div>

            <div class="guide-item">
              <strong style="color: #48bb78">💵 Cash Rebate</strong>
              <div class="guide-detail">
                <div class="guide-row">
                  <span class="bullet">•</span> <strong>Purpose:</strong> Direct
                  cash back to IB based on client trading
                </div>
                <div class="guide-row">
                  <span class="bullet">•</span>
                  <strong>When triggered:</strong> Based on selected condition
                  (all trades, volume threshold, client type)
                </div>
                <div class="guide-row">
                  <span class="bullet">•</span> <strong>Formula:</strong>
                  <code>Rebate = Value × Lots (if condition met)</code>
                </div>
                <div class="guide-row">
                  <span class="bullet">•</span>
                  <strong>Example:</strong> $1.50/lot rebate for volume > 500
                  lots/month
                </div>
                <div class="guide-row">
                  <span class="bullet">•</span> <strong>Note:</strong> Can be
                  set to "All trades qualify" for unconditional rebate
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Add Security Modal -->
    <Teleport to="body">
      <div
        v-if="showAddSecurityModal"
        class="modal-overlay"
        @click="closeAddSecurityModal"
      >
        <div class="modal" @click.stop style="max-width: 500px">
          <div class="modal-header">
            <h2 class="modal-title">
              <i class="fas fa-layer-group"></i> Add Security
            </h2>
            <button class="modal-close" @click="closeAddSecurityModal">
              <i class="fas fa-times"></i>
            </button>
          </div>

          <div class="modal-body">
            <form @submit.prevent="handleAddSecurity">
              <div class="form-group">
                <label class="form-label" for="securityName"
                  >Security Name *</label
                >
                <input
                  type="text"
                  id="securityName"
                  v-model="securityForm.securityName"
                  class="form-input"
                  placeholder="e.g., Commodities"
                  required
                />
              </div>
              <div class="form-group" style="margin-bottom: 0">
                <label class="form-label" for="securityDescription"
                  >Description (Optional)</label
                >
                <textarea
                  id="securityDescription"
                  v-model="securityForm.securityDescription"
                  class="form-textarea"
                  placeholder="Enter security description..."
                ></textarea>
              </div>
            </form>
          </div>

          <div class="modal-footer">
            <button class="btn btn-secondary" @click="closeAddSecurityModal">
              Cancel
            </button>
            <button class="btn btn-primary" @click="handleAddSecurity">
              <i class="fas fa-plus"></i> Add Security
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Add Symbol Modal -->
    <Teleport to="body">
      <div
        v-if="showAddSymbolModal"
        class="modal-overlay"
        @click="closeAddSymbolModal"
      >
        <div class="modal" @click.stop style="max-width: 500px">
          <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-tag"></i> Add Symbol</h2>
            <button class="modal-close" @click="closeAddSymbolModal">
              <i class="fas fa-times"></i>
            </button>
          </div>

          <div class="modal-body">
            <form @submit.prevent="handleAddSymbol">
              <div class="form-group">
                <label class="form-label" for="securityId">Security *</label>
                <select
                  id="securityId"
                  v-model="symbolForm.securityId"
                  class="form-select"
                  required
                >
                  <option value="">Select Security...</option>
                  <option
                    v-for="security in customSecurities"
                    :key="security.id"
                    :value="security.id"
                  >
                    {{ security.securityName }}
                  </option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label" for="symbolName">Symbol Name *</label>
                <input
                  type="text"
                  id="symbolName"
                  v-model="symbolForm.symbolName"
                  class="form-input"
                  placeholder="e.g., EURUSD"
                  required
                  style="text-transform: uppercase"
                  @input="
                    symbolForm.symbolName = symbolForm.symbolName.toUpperCase()
                  "
                />
              </div>
              <div class="form-group" style="margin-bottom: 0">
                <label class="form-label" for="symbolDescription"
                  >Description (Optional)</label
                >
                <textarea
                  id="symbolDescription"
                  v-model="symbolForm.symbolDescription"
                  class="form-textarea"
                  placeholder="Enter symbol description..."
                ></textarea>
              </div>
            </form>
          </div>

          <div class="modal-footer">
            <button class="btn btn-secondary" @click="closeAddSymbolModal">
              Cancel
            </button>
            <button class="btn btn-primary" @click="handleAddSymbol">
              <i class="fas fa-plus"></i> Add Symbol
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Manage Tiers Modal -->
    <Teleport to="body">
      <div v-if="showTiersModal" class="modal-overlay" @click="closeTiersModal">
        <div class="modal" @click.stop style="max-width: 800px">
          <div class="modal-header">
            <h2 class="modal-title">
              <i class="fas fa-layer-group"></i> Manage Commission Tiers
            </h2>
            <button class="modal-close" @click="closeTiersModal">
              <i class="fas fa-times"></i>
            </button>
          </div>

          <div class="modal-body">
            <div class="info-banner">
              <i class="fas fa-lightbulb"></i>
              <strong>Volume-based Tiers:</strong>
              <span
                >Define different commission rates based on monthly trading
                volume</span
              >
            </div>

            <div class="tiers-edit-container">
              <div
                v-for="(tier, index) in editingTiers"
                :key="index"
                class="tier-builder"
              >
                <div class="tier-builder-header">
                  <span class="tier-builder-title">
                    <i
                      class="fas fa-star"
                      style="color: var(--color-brand)"
                    ></i>
                    Tier {{ index + 1 }}
                  </span>
                  <button
                    v-if="index > 0"
                    type="button"
                    class="btn-remove-tier"
                    @click="removeTierEdit(index)"
                  >
                    <i class="fas fa-trash"></i> Remove
                  </button>
                </div>
                <div class="tier-builder-fields">
                  <div class="form-group">
                    <label class="form-label">Tier Name</label>
                    <input
                      type="text"
                      v-model="tier.tierName"
                      placeholder="e.g., Starter Level"
                      class="form-input"
                    />
                  </div>
                  <div class="form-group">
                    <label class="form-label required">Commission Rate *</label>
                    <input
                      type="number"
                      v-model.number="tier.commissionRate"
                      placeholder="8.00"
                      step="0.01"
                      min="0"
                      required
                      class="form-input"
                    />
                  </div>
                  <div class="form-group">
                    <label class="form-label">Min. Volume (Lots)</label>
                    <input
                      type="number"
                      v-model.number="tier.minimumVolume"
                      placeholder="0"
                      min="0"
                      class="form-input"
                    />
                  </div>
                  <div class="form-group">
                    <label class="form-label">Max. Volume (Lots)</label>
                    <input
                      type="text"
                      v-model="tier.maximumVolume"
                      placeholder="100 or Unlimited"
                      class="form-input"
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
  </div>
</template>

<script setup>
import { ref, reactive, watch, onMounted, computed } from "vue";
import ibRulesApi from "@/services/ibRulesApi";
import ibSettingsApi from "@/services/ibSettingsApi";

const props = defineProps({
  rule: {
    type: Object,
    required: true,
  },
  saving: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["save", "refresh"]);

// 初始化规则数据，确保数据类型正确
const initializeRuleData = (rule) => {
  return {
    ...rule,
    ruleDescription: rule.ruleDescription || "", // 确保ruleDescription不为null或undefined
    autoPaymentEnabled: rule.autoPaymentEnabled
      ? Number(rule.autoPaymentEnabled)
      : 0,
    minimumPayout: rule.minimumPayout ? Number(rule.minimumPayout) : 0,
  };
};

const localRule = reactive(initializeRuleData(props.rule));
const originalRule = reactive(initializeRuleData(props.rule));
const products = ref([]);
const originalProducts = ref([]);
const hasInfoChanges = ref(false);
const hasPaymentChanges = ref(false);
const hasProductChanges = ref(false);
const hasAdditionalRulesChanges = ref(false);
const additionalRulesEnabled = ref(false);
const additionalRules = ref([]);
const originalAdditionalRules = ref([]);
const showTiersModal = ref(false);
const currentEditingRuleIndex = ref(null);
const editingTiers = ref([]);
const showAddSecurityModal = ref(false);
const showAddSymbolModal = ref(false);
const securityForm = ref({
  securityName: "",
  securityDescription: "",
});
const symbolForm = ref({
  securityId: "",
  symbolName: "",
  symbolDescription: "",
});
const customSecurities = ref([]);
const customSymbols = ref([]);

// 计算属性：所有Securities（包括默认的和自定义的）
const allSecurities = computed(() => {
  return customSecurities.value;
});

/**
 * 标记信息已变更
 */
const markInfoChanged = () => {
  hasInfoChanges.value = true;
};

/**
 * 标记支付设置已变更
 */
const markPaymentChanged = () => {
  hasPaymentChanges.value = true;
};

/**
 * 处理支付周期变更
 */
const handlePaymentCycleChange = () => {
  // 根据不同的支付周期设置合适的默认值
  const defaultValues = {
    realtime: "immediate",
    daily: "everyday",
    weekly: "Monday",
    biweekly: "1-15",
    monthly: "5",
    quarterly: "1",
  };

  const newCycle = localRule.paymentCycle;

  // 如果是切换周期（不是首次加载）或者当前值不合适，则设置默认值
  if (defaultValues[newCycle]) {
    // 对于 realtime，总是设置为 immediate
    if (newCycle === "realtime") {
      localRule.paymentDay = defaultValues[newCycle];
    }
    // 对于其他周期，如果当前值是 immediate 或空，则设置默认值
    else if (
      !localRule.paymentDay ||
      localRule.paymentDay === "immediate" ||
      localRule.paymentDay === "0"
    ) {
      localRule.paymentDay = defaultValues[newCycle];
    }
  }

  markPaymentChanged();
};

/**
 * 标记产品已变更
 */
const markProductChanged = () => {
  hasProductChanges.value = true;
};

/**
 * 标记额外规则已变更
 */
const markAdditionalRulesChanged = () => {
  hasAdditionalRulesChanges.value = true;
};

/**
 * 切换额外规则
 */
const toggleAdditionalRules = () => {
  if (!additionalRulesEnabled.value && products.value.length === 0) {
    alert(
      "⚠️ Please add at least one product in the Product Commission Configuration table first.",
    );
    return;
  }
  additionalRulesEnabled.value = !additionalRulesEnabled.value;
};

/**
 * 添加额外规则
 */
const addAdditionalRule = () => {
  additionalRules.value.push({
    productType: "security",
    productName: "",
    ruleType: "bonus_commission",
    ruleValue: 0,
    ruleCondition: "",
    tierCount: 0,
    isActive: 1,
  });
  markAdditionalRulesChanged();
};

/**
 * 移除额外规则
 */
const removeAdditionalRule = (index) => {
  if (confirm("Are you sure you want to remove this additional rule?")) {
    additionalRules.value.splice(index, 1);
    markAdditionalRulesChanged();
  }
};

/**
 * 更新额外规则列显示
 */
const updateAdditionalRuleColumns = (index) => {
  const rule = additionalRules.value[index];
  if (rule.ruleType === "volume_tiers") {
    rule.ruleValue = null;
  }
  markAdditionalRulesChanged();
};

/**
 * 获取值输入框占位符
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
 * 打开管理层级模态框
 */
const openManageTiersModal = (ruleIndex) => {
  currentEditingRuleIndex.value = ruleIndex;
  const rule = additionalRules.value[ruleIndex];

  // 加载现有层级或创建默认层级
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

/**
 * 关闭层级模态框
 */
const closeTiersModal = () => {
  showTiersModal.value = false;
  currentEditingRuleIndex.value = null;
};

/**
 * 添加层级
 */
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

/**
 * 移除层级
 */
const removeTierEdit = (index) => {
  if (editingTiers.value.length <= 1) {
    alert("⚠️ You must have at least one tier.");
    return;
  }

  if (confirm("Are you sure you want to remove this tier?")) {
    editingTiers.value.splice(index, 1);
  }
};

/**
 * 保存层级
 */
const saveTiersModal = () => {
  // 验证
  for (let i = 0; i < editingTiers.value.length; i++) {
    if (!editingTiers.value[i].commissionRate) {
      alert(`⚠️ Please enter commission rate for Tier ${i + 1}`);
      return;
    }
  }

  if (currentEditingRuleIndex.value !== null) {
    const rule = additionalRules.value[currentEditingRuleIndex.value];
    rule.tiers = editingTiers.value.map((t) => ({ ...t }));
    rule.tierCount = editingTiers.value.length;
    rule.ruleValue = `${editingTiers.value.length} Tiers`;
    markAdditionalRulesChanged();
  }

  closeTiersModal();
  alert(
    `✓ Commission tiers saved successfully!\n\nTotal Tiers: ${editingTiers.value.length}`,
  );
};

/**
 * 添加产品
 */
const addProduct = () => {
  products.value.push({
    productType: "security",
    productName: "",
    commissionType: "per_lot",
    commissionRate: 0,
    additionalRate: 0,
    minimumVolume: "0.01 lots",
  });
  markProductChanged();
};

/**
 * 移除产品
 */
const removeProduct = (index) => {
  if (products.value.length <= 1) {
    alert("⚠️ You must have at least one product.");
    return;
  }

  if (confirm("Are you sure you want to remove this product?")) {
    products.value.splice(index, 1);
    markProductChanged();
  }
};

/**
 * 保存基本信息
 */
const saveInfo = () => {
  const data = {
    ruleName: localRule.ruleName,
    ruleType: localRule.ruleType,
    ruleDescription: localRule.ruleDescription,
    targetRegion: localRule.targetRegion,
    status: localRule.status,
  };

  console.log("Saving rule info:", data);
  emit("save", { ruleId: props.rule.id, type: "basic-info", data });
  hasInfoChanges.value = false;

  // 更新 originalRule 保存成功的值
  Object.assign(originalRule, {
    ruleName: localRule.ruleName,
    ruleType: localRule.ruleType,
    ruleDescription: localRule.ruleDescription,
    targetRegion: localRule.targetRegion,
    status: localRule.status,
  });
};

/**
 * 保存支付设置
 */
const savePayment = () => {
  const data = {
    paymentCycle: localRule.paymentCycle,
    paymentDay: localRule.paymentDay,
    minimumPayout: localRule.minimumPayout,
    payoutCurrency: localRule.payoutCurrency,
    autoPaymentEnabled: localRule.autoPaymentEnabled,
  };

  console.log("Saving payment settings:", data);
  emit("save", { ruleId: props.rule.id, type: "basic-info", data });
  hasPaymentChanges.value = false;

  // 更新 originalRule 保存成功的值
  Object.assign(originalRule, {
    paymentCycle: localRule.paymentCycle,
    paymentDay: localRule.paymentDay,
    minimumPayout: localRule.minimumPayout,
    payoutCurrency: localRule.payoutCurrency,
    autoPaymentEnabled: localRule.autoPaymentEnabled,
  });
};

/**
 * 保存产品配置
 */
const saveProducts = () => {
  // 验证产品数据
  if (products.value.length === 0) {
    alert("⚠️ Please add at least one product before saving.");
    return;
  }

  const invalidProducts = products.value.filter(
    (p) =>
      !p.productName ||
      p.commissionRate === null ||
      p.commissionRate === undefined,
  );
  if (invalidProducts.length > 0) {
    alert(
      "⚠️ Please fill in all required fields:\n- Product Name (required)\n- Commission Rate (required)",
    );
    return;
  }

  // 清理产品数据：只发送需要的字段，移除前端的 id 字段（后端会自动生成）
  const resolveProductId = (product) => {
    const name = product.productName;
    if (!name)
      return {
        productType: product.productType || "security",
        productId: null,
      };
    const sym = customSymbols.value.find((s) => s.symbolName === name);
    if (sym) return { productType: "symbol", productId: sym.id };
    const sec = allSecurities.value.find((s) => s.securityName === name);
    if (sec) return { productType: "security", productId: sec.id };
    return { productType: product.productType || "security", productId: null };
  };

  const cleanProducts = products.value.map((product) => {
    const { productType, productId } = resolveProductId(product);
    return {
      productType,
      productName: product.productName,
      productId,
      commissionType: product.commissionType || "per_lot",
      commissionRate: Number(product.commissionRate),
      additionalRate: Number(product.additionalRate || 0),
      minimumVolume: product.minimumVolume || "0.01 lots",
    };
  });

  if (cleanProducts.some((p) => !p.productId)) {
    alert(
      "⚠️ Could not resolve product ID for one or more products. Please re-select products from the list.",
    );
    return;
  }

  console.log("Saving products:", cleanProducts);
  emit("save", {
    ruleId: props.rule.id,
    type: "products",
    data: { products: cleanProducts },
  });
  hasProductChanges.value = false;
  originalProducts.value = JSON.parse(JSON.stringify(products.value));
};

/**
 * 保存额外规则
 */
const saveAdditionalRules = () => {
  // 验证额外规则数据
  if (additionalRules.value.length === 0) {
    alert("⚠️ Please add at least one additional rule before saving.");
    return;
  }

  const invalidRules = additionalRules.value.filter(
    (r) => !r.productName || !r.ruleType,
  );
  if (invalidRules.length > 0) {
    alert(
      "⚠️ Please fill in all required fields:\n- Product Name (required)\n- Rule Type (required)",
    );
    return;
  }

  // 清理额外规则数据：只发送需要的字段
  const cleanAdditionalRules = additionalRules.value.map((rule) => ({
    id: rule.id || undefined, // 如果有 id 说明是更新，否则是新建
    productType: rule.productType || "security",
    productName: rule.productName,
    ruleType: rule.ruleType,
    ruleValue:
      rule.ruleType === "volume_tiers" ? null : Number(rule.ruleValue || 0),
    ruleCondition:
      rule.ruleType === "volume_tiers" ? "auto" : rule.ruleCondition,
    isActive: rule.isActive !== undefined ? rule.isActive : 1,
    tiers: rule.tiers || [], // 包含层级数据（如果有的话）
  }));

  console.log("Saving additional rules:", cleanAdditionalRules);
  emit("save", {
    ruleId: props.rule.id,
    type: "additional-rules",
    data: { additionalRules: cleanAdditionalRules },
  });
  hasAdditionalRulesChanges.value = false;
  originalAdditionalRules.value = JSON.parse(
    JSON.stringify(additionalRules.value),
  );
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
 * 加载规则详情
 */
const loadRuleDetails = async () => {
  try {
    const ibRulesApi = (await import("@/services/ibRulesApi")).default;
    const response = await ibRulesApi.getRule(props.rule.id);

    if (response.success && response.data) {
      const ruleData = response.data;

      // 更新基本信息（包括ruleDescription）
      if (ruleData.ruleDescription !== undefined) {
        localRule.ruleDescription = ruleData.ruleDescription || "";
      }
      if (ruleData.ruleName !== undefined) {
        localRule.ruleName = ruleData.ruleName;
      }
      if (ruleData.ruleType !== undefined) {
        localRule.ruleType = ruleData.ruleType;
      }
      if (ruleData.targetRegion !== undefined) {
        localRule.targetRegion = ruleData.targetRegion;
      }
      if (ruleData.status !== undefined) {
        localRule.status = ruleData.status;
      }

      // 加载产品配置
      if (ruleData.products && ruleData.products.length > 0) {
        products.value = ruleData.products.map((p) => ({ ...p }));
        originalProducts.value = JSON.parse(JSON.stringify(products.value));
      }

      // 加载额外规则
      if (ruleData.additionalRules && ruleData.additionalRules.length > 0) {
        additionalRulesEnabled.value = true;
        additionalRules.value = ruleData.additionalRules.map((ar) => ({
          ...ar,
          tierCount: ar.tiers ? ar.tiers.length : 0,
        }));
        originalAdditionalRules.value = JSON.parse(
          JSON.stringify(additionalRules.value),
        );
      }
    }
  } catch (error) {
    console.error("Failed to load rule details:", error);
  }
};

// 监听 rule 变化 (只在初始化时更新，保存后的更新由本地状态管理)
watch(
  () => props.rule,
  (newVal, oldVal) => {
    // 只在 rule ID 变化时才完全重新加载 (切换到不同规则)
    if (!oldVal || newVal.id !== oldVal.id) {
      const normalizedRule = initializeRuleData(newVal);
      Object.assign(localRule, normalizedRule);
      Object.assign(originalRule, normalizedRule);
      loadRuleDetails();
    }
  },
  { deep: true, immediate: false },
);

// 监听 products 变化（当父组件重新加载产品数据时更新显示）
watch(
  () => props.rule.products,
  (newProducts) => {
    if (newProducts && newProducts.length > 0) {
      console.log("Products updated from parent:", newProducts);
      products.value = newProducts.map((p) => ({ ...p }));
      originalProducts.value = JSON.parse(JSON.stringify(products.value));
      hasProductChanges.value = false;
    }
  },
  { deep: true },
);

// 监听 additionalRules 变化（当父组件重新加载额外规则数据时更新显示）
watch(
  () => props.rule.additionalRules,
  (newAdditionalRules) => {
    if (newAdditionalRules && newAdditionalRules.length > 0) {
      console.log("Additional rules updated from parent:", newAdditionalRules);
      additionalRules.value = newAdditionalRules.map((ar) => ({
        ...ar,
        tierCount: ar.tiers ? ar.tiers.length : 0,
      }));
      originalAdditionalRules.value = JSON.parse(
        JSON.stringify(additionalRules.value),
      );
      hasAdditionalRulesChanges.value = false;
      additionalRulesEnabled.value = true;
    }
  },
  { deep: true },
);

/**
 * 打开添加证券模态框
 */
const openAddSecurityModal = () => {
  showAddSecurityModal.value = true;
  securityForm.value = {
    securityName: "",
    securityDescription: "",
  };
};

/**
 * 关闭添加证券模态框
 */
const closeAddSecurityModal = () => {
  showAddSecurityModal.value = false;
};

/**
 * 处理添加证券
 */
const handleAddSecurity = async () => {
  if (!securityForm.value.securityName.trim()) {
    alert("⚠️ Please enter a security name");
    return;
  }

  try {
    const response = await ibSettingsApi.createCustomSecurity({
      securityName: securityForm.value.securityName.trim(),
      securityDescription:
        securityForm.value.securityDescription.trim() || null,
    });

    if (response.success) {
      alert(
        `✓ Security "${securityForm.value.securityName}" added successfully!\n\nIt's now available in all Product dropdowns.`,
      );
      closeAddSecurityModal();
      // 重新加载证券列表
      await loadCustomSecurities();
      // 更新产品下拉列表
      updateProductSelects();
    } else {
      alert(`Failed to add security: ${response.message || "Unknown error"}`);
    }
  } catch (error) {
    console.error("Failed to add security:", error);
    const errorMsg =
      error.response?.data?.message || error.message || "Please try again.";
    if (error.response?.status === 409) {
      alert(`⚠️ Security "${securityForm.value.securityName}" already exists.`);
    } else {
      alert("Failed to add security: " + errorMsg);
    }
  }
};

/**
 * 打开添加交易对模态框
 */
const openAddSymbolModal = () => {
  showAddSymbolModal.value = true;
  symbolForm.value = {
    securityId: "",
    symbolName: "",
    symbolDescription: "",
  };
};

/**
 * 关闭添加交易对模态框
 */
const closeAddSymbolModal = () => {
  showAddSymbolModal.value = false;
};

/**
 * 处理添加交易对
 */
const handleAddSymbol = async () => {
  if (!symbolForm.value.securityId) {
    alert("⚠️ Please select a security");
    return;
  }
  if (!symbolForm.value.symbolName.trim()) {
    alert("⚠️ Please enter a symbol name");
    return;
  }

  try {
    const response = await ibSettingsApi.createCustomSymbol({
      securityId: symbolForm.value.securityId,
      symbolName: symbolForm.value.symbolName.trim().toUpperCase(),
      symbolDescription: symbolForm.value.symbolDescription.trim() || null,
    });

    if (response.success) {
      alert(
        `✓ Symbol "${symbolForm.value.symbolName}" added successfully!\n\nIt's now available in all Product dropdowns.`,
      );
      closeAddSymbolModal();
      // 重新加载交易对列表
      await loadCustomSymbols();
      // 更新产品下拉列表
      updateProductSelects();
    } else {
      alert(`Failed to add symbol: ${response.message || "Unknown error"}`);
    }
  } catch (error) {
    console.error("Failed to add symbol:", error);
    const errorMsg =
      error.response?.data?.message || error.message || "Please try again.";
    if (error.response?.status === 409) {
      alert(`⚠️ Symbol "${symbolForm.value.symbolName}" already exists.`);
    } else {
      alert("Failed to add symbol: " + errorMsg);
    }
  }
};

/**
 * 同步产品（从交易平台）
 */
const syncProduct = () => {
  alert("Feature under development");
};

/**
 * 加载自定义证券列表
 */
const loadCustomSecurities = async () => {
  try {
    const response = await ibSettingsApi.getCustomSecurities();
    if (response.success && response.data) {
      customSecurities.value = response.data;
    }
  } catch (error) {
    console.error("Failed to load custom securities:", error);
  }
};

/**
 * 加载自定义交易对列表
 */
const loadCustomSymbols = async () => {
  try {
    const response = await ibSettingsApi.getCustomSymbols();
    if (response.success && response.data) {
      customSymbols.value = response.data;
    }
  } catch (error) {
    console.error("Failed to load custom symbols:", error);
  }
};

/**
 * 更新产品下拉列表选项
 */
const updateProductSelects = () => {
  // 这个方法会在组件重新渲染时自动更新下拉列表
  // 因为我们在模板中使用了computed属性来生成选项
};

onMounted(() => {
  // 初始化时检查 payment cycle
  if (localRule.paymentCycle === "realtime" && !localRule.paymentDay) {
    localRule.paymentDay = "immediate";
  }

  loadRuleDetails();
  loadCustomSecurities();
  loadCustomSymbols();
});
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
  font-size: 16px;
  font-weight: 600;
  color: var(--color-ink);
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
  font-size: 14px;
  width: 200px;
  transition: all 0.3s ease;
}

.detail-input:focus,
.detail-select:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.product-actions {
  margin-bottom: 15px;
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.btn {
  padding: 8px 16px;
  font-size: 14px;
  border: none;
  border-radius: var(--radius-md);
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.btn-success {
  background: var(--color-success-solid);
  color: white;
  box-shadow: 0 2px 8px rgba(72, 187, 120, 0.3);
}

.btn-success:hover {
  background: var(--color-success-solid);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(72, 187, 120, 0.4);
}

.btn-secondary {
  background: var(--color-border);
  color: var(--color-text);
}

.btn-secondary:hover {
  background: var(--color-border-strong);
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

.product-commission-table tr:hover {
  background: var(--color-surface-soft);
}

.form-select,
.form-input,
.form-textarea {
  width: 100%;
  padding: 8px 12px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: 14px;
  transition: all 0.3s ease;
  font-family: inherit;
}

.form-textarea {
  min-height: 80px;
  resize: vertical;
}

.form-select:focus,
.form-input:focus,
.form-textarea:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.form-label {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
  margin-bottom: 6px;
}

.form-label.required::after {
  content: " *";
  color: var(--color-danger);
}

.form-group {
  margin-bottom: 15px;
}

.btn-icon {
  padding: 6px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
}

.btn-delete {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.btn-delete:hover {
  background: var(--color-danger-solid);
  color: white;
}

.info-guide {
  margin-top: 15px;
  padding: 15px;
  background: var(--color-brand-soft);
  border-radius: var(--radius-md);
  font-size: 14px;
  color: var(--color-text);
  border-left: 4px solid var(--color-brand);
}

.info-guide strong {
  display: block;
  font-size: 14px;
  color: var(--color-ink);
  margin-bottom: 12px;
}

.guide-item {
  background: var(--color-surface);
  padding: 12px;
  border-radius: var(--radius-sm);
  margin-bottom: 10px;
}

.guide-item:last-child {
  margin-bottom: 0;
}

.guide-item strong {
  display: block;
  margin-bottom: 8px;
}

.guide-detail {
  margin-top: 6px;
}

.guide-row {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  margin-bottom: 6px;
  font-size: 14px;
  line-height: 1.6;
}

.guide-row:last-child {
  margin-bottom: 0;
}

.guide-row .bullet {
  color: var(--color-brand);
  font-weight: bold;
  flex-shrink: 0;
  width: 12px;
}

.guide-item code {
  background: var(--color-surface-soft);
  padding: 2px 6px;
  border-radius: 3px;
  font-family: "Courier New", monospace;
  font-size: 14px;
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
  transition: 0.4s;
  border-radius: 50%;
}

.toggle-switch.active::before {
  transform: translateX(24px);
}

.additional-rule-section {
  margin-top: 20px;
  padding-top: 20px;
  border-top: 2px solid var(--color-border);
}

.section-title-bar {
  margin-bottom: 15px;
}

.section-title-bar h4 {
  font-size: 15px;
  font-weight: 600;
  color: var(--color-ink);
  margin: 0;
}

.section-title-bar h4 i {
  color: var(--color-brand);
  margin-right: 8px;
}

.info-banner-warning {
  margin-bottom: 15px;
  padding: 10px;
  background: var(--color-warning-soft);
  border-radius: var(--radius-sm);
  border-left: 3px solid var(--color-warning);
  font-size: 14px;
  color: var(--color-warning);
  display: flex;
  align-items: center;
  gap: 8px;
}

.info-banner-warning i {
  flex-shrink: 0;
}

.btn-manage-tiers {
  padding: 6px 12px;
  font-size: 14px;
  width: 100%;
  background: var(--color-brand-solid);
  color: white;
  border: none;
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
}

.btn-manage-tiers:hover {
  background: var(--color-brand-strong);
}

.btn-disabled {
  padding: 6px 12px;
  font-size: 14px;
  width: 100%;
  background: var(--color-border);
  color: var(--color-faint);
  border: none;
  border-radius: var(--radius-sm);
  cursor: not-allowed;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
}

/* Modal Styles */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9998;
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

.modal {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  max-width: 900px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
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
  background: var(--color-brand-solid);
  color: white;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.modal-title {
  font-size: 22px;
  font-weight: 700;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 12px;
  color: white;
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
  font-size: 20px;
  transition: all 0.3s ease;
}

.modal-close:hover {
  background: rgba(255, 255, 255, 0.3);
  transform: rotate(90deg);
}

.modal-body {
  padding: 30px;
  max-height: calc(85vh - 180px);
  overflow-y: auto;
}

.modal-body::-webkit-scrollbar {
  width: 8px;
}

.modal-body::-webkit-scrollbar-track {
  background: var(--color-surface-soft);
  border-radius: 4px;
}

.modal-body::-webkit-scrollbar-thumb {
  background: var(--color-border-strong);
  border-radius: 4px;
}

.info-banner {
  background: var(--color-brand-soft);
  padding: 12px;
  border-radius: var(--radius-sm);
  margin-bottom: 15px;
  border-left: 3px solid var(--color-brand);
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: var(--color-text);
}

.info-banner i {
  color: var(--color-brand);
  flex-shrink: 0;
}

.tiers-edit-container {
  max-height: 400px;
  overflow-y: auto;
  padding-right: 5px;
  margin-bottom: 15px;
}

.tiers-edit-container::-webkit-scrollbar {
  width: 6px;
}

.tiers-edit-container::-webkit-scrollbar-track {
  background: var(--color-surface-soft);
  border-radius: 3px;
}

.tiers-edit-container::-webkit-scrollbar-thumb {
  background: var(--color-border-strong);
  border-radius: 3px;
}

.tier-builder {
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 20px;
  margin-bottom: 15px;
  transition: all 0.3s ease;
}

.tier-builder:hover {
  border-color: var(--color-border-strong);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.tier-builder-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
  padding-bottom: 12px;
  border-bottom: 2px solid var(--color-border);
}

.tier-builder-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-remove-tier {
  background: var(--color-danger-soft);
  color: var(--color-danger);
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
}

.btn-remove-tier:hover {
  background: var(--color-danger-border);
  color: white;
  transform: translateY(-1px);
}

.tier-builder-fields {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 15px;
}

.form-group {
  margin-bottom: 0;
}

.form-group label {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
  margin-bottom: 6px;
}

.btn-add-tier {
  width: 100%;
  justify-content: center;
  padding: 12px 20px;
  background: var(--color-success-solid);
  color: white;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-add-tier:hover {
  background: var(--color-success-solid);
  transform: translateY(-1px);
}

.modal-footer {
  padding: 20px 30px;
  border-top: 2px solid var(--color-border);
  background: var(--color-surface-soft);
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

.btn-secondary {
  padding: 12px 24px;
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  color: var(--color-text);
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
}

.btn-secondary:hover {
  background: var(--color-surface-soft);
  border-color: var(--color-border-strong);
}

.btn-primary {
  padding: 12px 24px;
  background: var(--color-brand-solid);
  border: none;
  color: white;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 2px 8px rgba(var(--color-brand-rgb), 0.3);
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.4);
}

@media (max-width: 768px) {
  .detail-sections {
    grid-template-columns: 1fr;
  }

  .tier-builder-fields {
    grid-template-columns: 1fr;
  }
}
</style>
