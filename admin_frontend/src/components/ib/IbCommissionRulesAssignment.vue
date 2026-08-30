<template>
  <div class="ib-commission-rules-assignment">
    <!-- Step 1: Assign Agent Tier Level -->
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
        <strong v-if="canEdit">Modify Rules:</strong>
        <strong v-else>Current Configuration:</strong>
        <span v-if="canEdit"
          >You can modify the rules. After saving, the application will be
          resubmitted for review.</span
        >
        <span v-else>The current rule configuration for this application.</span>
      </div>

      <!-- Step 1: IB Tier Assignment -->
      <div class="setup-step">
        <div class="step-title">
          <i class="fas fa-sitemap"></i> Step 1: Assign Agent Tier Level
        </div>

        <div class="info-note">
          <strong><i class="fas fa-info-circle"></i> Tier Selection:</strong>
          Select the agent tier level for this IB. Tiers are pre-configured in
          IB Tier Template.
        </div>

        <div class="tier-selection-box">
          <label class="form-label">Select Agent Tier Level *</label>
          <select
            v-model="selectedTierLevelId"
            @change="handleTierChange"
            class="form-select-large"
            :disabled="!canEdit"
          >
            <option value="">Select tier level...</option>
            <option
              v-for="tier in tierLevels"
              :key="tier.id"
              :value="String(tier.id)"
            >
              Tier {{ tier.tierLevel }} - {{ tier.tierName }} ({{
                getTierPermissions(tier)
              }})
            </option>
          </select>
        </div>

        <div v-if="selectedTierLevelId" class="tier-preview">
          <div class="preview-header">
            <i class="fas fa-check-circle"></i> Selected Tier:
            {{ getSelectedTier()?.tierName }}
          </div>
          <div class="preview-content">
            <strong>Permissions:</strong><br />
            <span v-if="getSelectedTier()?.canRecruitSubAgents"
              >✓ Recruit Sub-agents<br
            /></span>
            <span v-if="getSelectedTier()?.canViewReports"
              >✓ View Reports<br
            /></span>
            <span v-if="getSelectedTier()?.canManageClients"
              >✓ Manage Clients<br
            /></span>
            <span
              v-if="
                !getSelectedTier()?.canRecruitSubAgents &&
                !getSelectedTier()?.canViewReports &&
                !getSelectedTier()?.canManageClients
              "
            >
              - No special permissions
            </span>
          </div>
          <div class="preview-footer">
            Commission rates will be determined by the IB Rules assigned in Step
            2
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
              :disabled="!canEdit"
            />
            <label :for="`rule-${rule.id}`" :class="{ disabled: !canEdit }">
              <div class="rule-name">
                <i class="fas" :class="getRuleIcon(rule.ruleType)"></i>
                {{ rule.ruleName }}
              </div>
              <div class="rule-meta">
                {{ formatPaymentCycle(rule.paymentCycle) }} • ${{
                  rule.minimumPayout
                }}
                min payout • {{ rule.productCount }} Products •
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
                  :disabled="!canEdit"
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
                  :disabled="!canEdit"
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
                  :disabled="!canEdit"
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
                  :disabled="!canEdit"
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
                  :disabled="!canEdit"
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
                  :disabled="!canEdit"
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
                  :disabled="!canEdit"
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
                    :disabled="!canEdit"
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
                  :disabled="!canEdit"
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
                <button
                  v-if="canEdit"
                  class="btn btn-success"
                  @click="addProduct(ruleId)"
                >
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
                    <th v-if="canEdit" style="width: 80px">Action</th>
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
                        :disabled="!canEdit"
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
                        :disabled="!canEdit"
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
                        :disabled="!canEdit"
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
                        :disabled="
                          product.commissionType === 'cashback' || !canEdit
                        "
                      />
                    </td>
                    <td>
                      <input
                        type="text"
                        v-model="product.minimumVolume"
                        @input="markProductChanged(ruleId)"
                        class="form-input"
                        placeholder="Min Vol"
                        :disabled="!canEdit"
                      />
                    </td>
                    <td v-if="canEdit" style="text-align: center">
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
                  @click="canEdit && toggleAdditionalRules(ruleId)"
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
                  v-if="canEdit"
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
                      <th v-if="canEdit" style="width: 80px">Action</th>
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
                          :disabled="!canEdit"
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
                          :disabled="!canEdit"
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
                          :disabled="!canEdit"
                        />
                        <input
                          v-else
                          type="text"
                          :value="
                            (rule.tierCount ||
                              (rule.tiers && rule.tiers.length) ||
                              0) + ' Tiers'
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
                          :disabled="!canEdit"
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
                          :disabled="!canEdit"
                        >
                          <i class="fas fa-edit"></i> Manage ({{
                            rule.tierCount ||
                            (rule.tiers && rule.tiers.length) ||
                            0
                          }})
                        </button>
                        <button v-else disabled class="btn btn-disabled">
                          <i class="fas fa-ban"></i> N/A
                        </button>
                      </td>
                      <td v-if="canEdit" style="text-align: center">
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

        <!-- Rejected状态显示拒绝理由 -->
        <div
          v-if="showRejectionReason && application.rejectionReason"
          class="rejection-reason-box"
        >
          <div class="rejection-reason-header">
            <i class="fas fa-info-circle"></i>
            <strong>Rejection Reason</strong>
          </div>
          <div class="rejection-reason-content">
            {{ application.rejectionReason }}
          </div>
        </div>
      </div>

      <!-- Save Button -->
      <div v-if="canEdit" class="assign-rules-footer">
        <button
          class="btn-save"
          :class="{ active: hasChanges }"
          :disabled="!hasChanges || saving"
          @click="handleSave"
        >
          <i class="fas" :class="saving ? 'fa-spinner fa-spin' : 'fa-save'"></i>
          Assign Rules
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
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, Teleport } from "vue";
import ibApplicationsApi from "@/services/ibApplicationsApi";

const props = defineProps({
  application: {
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
  canEdit: {
    type: Boolean,
    default: false,
  },
  showRejectionReason: {
    type: Boolean,
    default: false,
  },
  saving: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["save", "refresh", "refresh-securities-symbols"]);

// 状态管理
const selectedTierLevelId = ref("");
const selectedRuleIds = ref([]);
const originalTierId = ref("");
const originalRuleIds = ref([]);
const hasTierChanges = ref(false);
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
    hasTierChanges.value ||
    hasRuleChanges.value ||
    Object.values(hasPaymentChanges.value).some((v) => v) ||
    Object.values(hasProductChanges.value).some((v) => v) ||
    Object.values(hasAdditionalRulesChanges.value).some((v) => v)
  );
});

// 工具函数
const getTierPermissions = (tier) => {
  const perms = [];
  if (tier.canRecruitSubAgents) perms.push("Recruit");
  if (tier.canViewReports) perms.push("Reports");
  if (tier.canManageClients) perms.push("Manage");
  return perms.length > 0 ? perms.join(" + ") : "Basic Access";
};

const getSelectedTier = () => {
  const tierId = selectedTierLevelId.value
    ? Number(selectedTierLevelId.value)
    : null;
  return props.tierLevels.find((t) => t.id === tierId);
};

const getRuleIcon = (ruleType) => {
  const icons = {
    standard: "fa-layer-group",
    premium: "fa-crown",
    ultra: "fa-gem",
    custom: "fa-cog",
  };
  return icons[ruleType] || "fa-file-invoice-dollar";
};

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

const getRule = (ruleId) => {
  return props.commissionRules.find((r) => r.id === ruleId);
};

const getValuePlaceholder = (ruleType) => {
  const placeholders = {
    bonus_commission: "$/lot",
    volume_multiplier: "Multiplier (e.g., 1.25)",
    performance_bonus: "% of base commission",
    cash_rebate: "Rebate $/lot",
  };
  return placeholders[ruleType] || "Value";
};

// 事件处理
const handleTierChange = () => {
  hasTierChanges.value = selectedTierLevelId.value !== originalTierId.value;
};

const markRuleChanged = () => {
  hasRuleChanges.value = true;
};

const markPaymentChanged = (ruleId) => {
  hasPaymentChanges.value = { ...hasPaymentChanges.value, [ruleId]: true };
};

// 处理Payment Cycle变化，自动设置Payment Day默认值
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

const markProductChanged = (ruleId) => {
  hasProductChanges.value = { ...hasProductChanges.value, [ruleId]: true };
};

const markAdditionalRulesChanged = (ruleId) => {
  hasAdditionalRulesChanges.value = {
    ...hasAdditionalRulesChanges.value,
    [ruleId]: true,
  };
};

// 产品管理
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

// 额外规则管理
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

// Tiers Modal
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

// 保存
const handleSave = async () => {
  emit("save", {
    tierLevelId: selectedTierLevelId.value,
    ruleIds: selectedRuleIds.value,
    paymentSettings: rulePaymentSettings.value,
    products: ruleProducts.value,
    additionalRules: ruleAdditionalRules.value,
  });
};

// 初始化Securities和Symbols（从props获取）
const initializeSecuritiesAndSymbols = () => {
  if (props.securities && props.securities.length > 0) {
    allSecurities.value = props.securities;
  }
  if (props.symbols && props.symbols.length > 0) {
    customSymbols.value = props.symbols;
  }
};

// 加载已分配的规则的自定义数据（产品和额外规则）
// 注意：优先使用 props.application 中已传递的数据（从父组件 IbApplicationDetail 加载），避免重复调用 API
const loadRuleCustomData = async () => {
  try {
    let appData = null;

    // 优先使用 props.application 中已传递的 customRules（父组件已经加载过）
    if (
      props.application.customRules &&
      Array.isArray(props.application.customRules) &&
      props.application.customRules.length > 0
    ) {
      appData = { customRules: props.application.customRules };
    } else {
      // 如果 props.application 中没有 customRules，才调用 API
      const response = await ibApplicationsApi.getApplication(
        props.application.id,
      );

      if (response.success && response.data) {
        appData = response.data;
      }
    }

    // 加载每个规则的自定义产品和额外规则
    if (appData && appData.customRules && Array.isArray(appData.customRules)) {
      appData.customRules.forEach((customRule) => {
        const ruleId = String(customRule.ruleId);

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
            paymentCycle:
              customRule.paymentCycle ||
              rulePaymentSettings.value[ruleId]?.paymentCycle ||
              "monthly",
            paymentDay:
              customRule.paymentDay ||
              rulePaymentSettings.value[ruleId]?.paymentDay ||
              "",
            minimumPayout:
              customRule.minimumPayout ||
              rulePaymentSettings.value[ruleId]?.minimumPayout ||
              100.0,
            payoutCurrency:
              customRule.payoutCurrency ||
              rulePaymentSettings.value[ruleId]?.payoutCurrency ||
              "USD",
          };
        }

        // 加载产品（如果有保存的数据）
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
        }

        // 加载额外规则（如果有保存的数据）
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
        }
      });
    }
  } catch (error) {
    console.error("Failed to load rule custom data:", error);
  }
};

// 加载规则的通用模板数据（从props中的commissionRulesDetails获取，已提前加载）
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

// 初始化
onMounted(async () => {
  // 初始化Tier Level（使用 tierLevel）
  if (props.application.tierLevel) {
    selectedTierLevelId.value = String(props.application.tierLevel);
    originalTierId.value = String(props.application.tierLevel);
  }

  // 初始化Rules
  // 如果 preAssignedRules 不存在或为空，尝试从 API 获取
  let preAssignedRules = props.application.preAssignedRules;

  // 如果 preAssignedRules 是字符串（JSON格式），需要解析
  if (typeof preAssignedRules === "string") {
    try {
      preAssignedRules = JSON.parse(preAssignedRules);
    } catch (e) {
      console.error("Failed to parse preAssignedRules JSON:", e);
      preAssignedRules = null;
    }
  }

  // 如果 preAssignedRules 不存在，但有 preAssignedRulesCount，优先使用 props 中已传递的数据
  // 注意：父组件 IbApplicationDetail 已经通过 mergedApplication 传递了完整数据，包括 preAssignedRules
  if (
    (!preAssignedRules || preAssignedRules.length === 0) &&
    props.application.preAssignedRulesCount > 0
  ) {
    // 优先使用 props.application 中已传递的 preAssignedRules（来自父组件已加载的数据）
    if (
      props.application.preAssignedRules &&
      Array.isArray(props.application.preAssignedRules) &&
      props.application.preAssignedRules.length > 0
    ) {
      preAssignedRules = props.application.preAssignedRules;
    } else {
      // 如果 props 中没有，才调用 API（这种情况应该很少见）
      try {
        const response = await ibApplicationsApi.getApplication(
          props.application.id,
        );
        if (response.success && response.data?.preAssignedRules) {
          preAssignedRules = response.data.preAssignedRules;
        }
      } catch (error) {
        console.error("Failed to load pre-assigned rules:", error);
      }
    }
  }

  // 初始化规则列表
  if (
    preAssignedRules &&
    Array.isArray(preAssignedRules) &&
    preAssignedRules.length > 0
  ) {
    selectedRuleIds.value = preAssignedRules.map((r) => String(r.id));
    originalRuleIds.value = [...selectedRuleIds.value];

    // 初始化每个规则的Payment Settings（先使用规则默认值）
    selectedRuleIds.value.forEach((ruleId) => {
      const ruleIdNum = Number(ruleId);
      const rule = getRule(ruleIdNum);
      if (rule) {
        const ruleIdStr = String(ruleId);
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
    });
  }

  // 初始化Securities和Symbols（从props获取）
  initializeSecuritiesAndSymbols();

  // 加载已分配的规则的自定义数据（如果有保存过的数据，会显示保存的数据）
  await loadRuleCustomData();

  // 对于已选择但没有保存数据的规则，加载通用模板数据
  for (const ruleId of selectedRuleIds.value) {
    const ruleIdStr = String(ruleId);
    // 如果该rule没有加载过保存的自定义数据，就加载通用模板数据
    if (!loadedCustomDataRules.value.has(ruleIdStr)) {
      loadRuleTemplateData(Number(ruleId));
    }
  }
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

// 监听selectedRuleIds变化
watch(selectedRuleIds, async (newIds, oldIds) => {
  // 找出新添加的规则（包括重新选择的）
  const addedRuleIds = newIds.filter((ruleId) => !oldIds.includes(ruleId));

  // 新增的规则，初始化Payment Settings和加载模板数据
  // 注意：只要是重新选择了Rule，就显示通用模板数据（即使之前保存过数据）
  for (const ruleId of addedRuleIds) {
    const ruleIdStr = String(ruleId);

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
    // 这样用户可以基于通用模板进行个性化修改
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
      // 注意：不删除loadedCustomDataRules标记，这样在onMounted时仍然知道之前有保存过数据
    }
  });
});
</script>

<style scoped>
.ib-commission-rules-assignment {
  width: 100%;
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

.form-label {
  font-size: 14px;
  color: var(--color-text);
  font-weight: 600;
  display: block;
  margin-bottom: 12px;
}

.form-select-large {
  width: 100%;
  padding: 14px 18px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  transition: all 0.3s ease;
}

.form-select-large:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.form-select-large:disabled {
  background: var(--color-surface-soft);
  color: var(--color-ink) !important; /* 保持正常文字颜色，不使用灰色 */
  cursor: not-allowed;
  opacity: 1; /* 保持完全不透明，确保文字清晰 */
}

/* 确保 form-select-large 的 option 文字颜色也正常 */
.form-select-large:disabled option {
  color: var(--color-ink);
  background-color: var(--color-surface);
}

.tier-preview {
  padding: 15px;
  background: var(--color-success-soft);
  border-radius: var(--radius-md);
  border-left: 4px solid var(--color-success);
}

.preview-header {
  font-weight: 600;
  color: var(--color-success);
  font-size: 14px;
  margin-bottom: 10px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.preview-content {
  font-size: 14px;
  color: var(--color-success);
  line-height: 1.6;
}

.preview-footer {
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px solid var(--color-success-border);
  font-size: 14px;
  color: var(--color-success);
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

.rule-checkbox label.disabled {
  cursor: not-allowed;
  opacity: 1; /* 保持完全不透明，确保文字清晰 */
  color: var(--color-ink); /* 保持正常文字颜色 */
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

/* Rule Config Section */
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

/* Payment Settings */
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

.form-select:disabled {
  background: var(--color-surface-soft);
  color: var(--color-ink) !important; /* 保持正常文字颜色，不使用灰色 */
  cursor: not-allowed;
  opacity: 1; /* 保持完全不透明，确保文字清晰 */
}

/* 确保 select 的 option 文字颜色也正常 */
.form-select:disabled option {
  color: var(--color-ink);
  background-color: var(--color-surface);
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

.form-input:disabled {
  background: var(--color-surface-soft);
  color: var(--color-ink) !important; /* 保持正常文字颜色，不使用灰色 */
  cursor: not-allowed;
  opacity: 1; /* 保持完全不透明，确保文字清晰 */
}

/* Products Section */
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

.btn-success:disabled {
  opacity: 0.6;
  cursor: not-allowed;
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

/* Toggle Additional Rules */
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

/* Additional Rule Section */
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

.btn-manage-tiers:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-disabled {
  background: var(--color-border);
  color: var(--color-faint);
  cursor: not-allowed;
}

/* Rejection Reason */
.rejection-reason-box {
  margin-top: 30px;
  padding: 20px;
  background: var(--color-danger-soft);
  border: 2px solid var(--color-danger-border);
  border-left: 4px solid var(--color-danger);
  border-radius: var(--radius-md);
}

.rejection-reason-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;
  color: var(--color-danger);
  font-size: 14px;
  font-weight: 600;
}

.rejection-reason-header i {
  font-size: 18px;
}

.rejection-reason-content {
  color: var(--color-danger);
  font-size: 14px;
  line-height: 1.6;
  padding: 12px;
  background: var(--color-surface);
  border-radius: var(--radius-sm);
  border: 1px solid var(--color-danger-border);
}

/* Assign Rules Footer */
.assign-rules-footer {
  margin-top: 30px;
  padding-top: 20px;
  border-top: 2px solid var(--color-border);
  display: flex;
  justify-content: flex-end;
}

.btn-save {
  padding: 12px 30px;
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
  min-width: 150px;
  justify-content: center;
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

/* Modal Styles */
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

/* Tiers Edit */
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
</style>
