<template>
  <div class="modal-overlay show" @click="onOverlayClick">
    <div class="modal" @click.stop>
      <div class="modal-header">
        <h2 class="modal-title">
          <i class="fas fa-coins"></i>
          {{ t("adminAdjust_title", "Adjust Balance") }}
        </h2>
        <button
          class="modal-close"
          @click="emit('close')"
          :disabled="submitting"
        >
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="modal-body">
        <!-- 类型：balance / credits -->
        <div class="form-group">
          <label class="form-label">{{
            t("adminAdjust_label_type", "Type")
          }}</label>
          <div class="seg-toggle">
            <button
              type="button"
              class="seg-btn"
              :class="{ active: form.type === 'balance' }"
              :disabled="submitting"
              @click="form.type = 'balance'"
            >
              {{ t("adminAdjust_type_balance", "Balance") }}
            </button>
            <button
              type="button"
              class="seg-btn"
              :class="{ active: form.type === 'credits' }"
              :disabled="submitting"
              @click="form.type = 'credits'"
            >
              {{ t("adminAdjust_type_credits", "Credits") }}
            </button>
          </div>
        </div>

        <!-- 目标：钱包或某个交易账户，一个下拉解决 -->
        <div class="form-group">
          <label class="form-label">{{
            t("adminAdjust_label_target", "Target")
          }}</label>
          <select
            class="form-input"
            v-model="targetSelection"
            :disabled="submitting || tradingLoading"
          >
            <option value="" disabled>
              {{
                tradingLoading
                  ? t("adminAdjust_loadingAccounts", "Loading accounts...")
                  : t("adminAdjust_selectTarget", "Select target")
              }}
            </option>
            <option v-if="form.type !== 'credits'" value="wallet">
              {{ t("adminAdjust_target_wallet", "Wallet") }} ·
              {{ form.currencyCode }} — {{ formatMoney(currentBalance) }}
            </option>
            <optgroup
              v-for="group in groupedTradingAccounts"
              :key="group.label"
              :label="group.label"
            >
              <option
                v-for="acc in group.accounts"
                :key="acc.tradingAccountId"
                :value="String(acc.tradingAccountId)"
              >
                {{
                  (acc.login || "-") +
                  (acc.currency ? " (" + acc.currency + ")" : "")
                }}
                — {{ formatMoney(acc.balance) }}
              </option>
            </optgroup>
          </select>
          <p v-if="form.type === 'credits'" class="field-note">
            {{
              t(
                "adminAdjust_creditsAccountOnly",
                "Credits apply to a trading account only.",
              )
            }}
          </p>
          <p
            v-if="
              !tradingLoading &&
              tradingAccounts.length === 0 &&
              form.type === 'credits'
            "
            class="field-note"
          >
            {{
              t(
                "adminAdjust_noAccounts",
                "No trading accounts found for this client.",
              )
            }}
          </p>
        </div>

        <!-- direction 选择 -->
        <div class="form-group">
          <label class="form-label">{{
            t("adminAdjust_label_direction", "Operation")
          }}</label>
          <div class="direction-toggle">
            <button
              type="button"
              class="direction-btn"
              :class="{ active: form.direction === 'add' }"
              :disabled="submitting"
              @click="form.direction = 'add'"
            >
              <i class="fas fa-plus-circle"></i>
              {{ t("adminAdjust_add", "Add") }}
            </button>
            <button
              type="button"
              class="direction-btn"
              :class="{ active: form.direction === 'deduct' }"
              :disabled="submitting"
              @click="form.direction = 'deduct'"
            >
              <i class="fas fa-minus-circle"></i>
              {{ t("adminAdjust_deduct", "Deduct") }}
            </button>
          </div>
        </div>

        <!-- 金额 -->
        <div class="form-group">
          <label class="form-label">
            {{ t("adminAdjust_label_amount", "Amount") }}
            <span v-if="form.target === 'wallet'"
              >({{ form.currencyCode }})</span
            >
            <span v-else-if="selectedAccount && selectedAccount.currency"
              >({{ selectedAccount.currency }})</span
            >
          </label>
          <input
            type="number"
            class="form-input"
            v-model.number="form.amount"
            min="0"
            step="0.01"
            :disabled="submitting"
            :placeholder="t('adminAdjust_placeholder_amount', 'Enter amount')"
          />
        </div>

        <!-- 钱包余额预览（仅目标=钱包） -->
        <div v-if="form.target === 'wallet'" class="balance-preview">
          <div class="balance-row">
            <span class="balance-label">{{
              t("adminAdjust_currentBalance", "Current Balance")
            }}</span>
            <span class="balance-value">
              <i v-if="balanceLoading" class="fas fa-spinner fa-spin"></i>
              <template v-else
                >{{ formatMoney(currentBalance) }}
                {{ form.currencyCode }}</template
              >
            </span>
          </div>
          <div class="balance-row">
            <span class="balance-label">{{
              t("adminAdjust_resultBalance", "Resulting Balance")
            }}</span>
            <span
              class="balance-value"
              :class="{
                insufficient:
                  form.direction === 'deduct' && hasAmount && resultBalance < 0,
              }"
            >
              {{ formatMoney(resultBalance) }} {{ form.currencyCode }}
            </span>
          </div>
        </div>

        <!-- 交易账户当前值 + 改后值（仅目标=交易账户且已选） -->
        <div v-else-if="selectedAccount" class="balance-preview">
          <div v-if="form.type !== 'credits'" class="balance-row">
            <span class="balance-label">{{
              t("adminAdjust_accountBalance", "Account Balance")
            }}</span>
            <span class="balance-value"
              >{{ formatMoney(selectedAccount.balance)
              }}{{
                selectedAccount.currency ? " " + selectedAccount.currency : ""
              }}</span
            >
          </div>
          <div v-if="form.type === 'credits'" class="balance-row">
            <span class="balance-label">{{
              t("adminAdjust_accountCredit", "Account Credit")
            }}</span>
            <span class="balance-value"
              >{{ formatMoney(selectedAccount.credit)
              }}{{
                selectedAccount.currency ? " " + selectedAccount.currency : ""
              }}</span
            >
          </div>
          <div class="balance-row">
            <span class="balance-label">
              {{
                form.type === "credits"
                  ? t("adminAdjust_resultCredit", "Resulting Credit")
                  : t("adminAdjust_resultBalance", "Resulting Balance")
              }}
            </span>
            <span
              class="balance-value"
              :class="{
                insufficient:
                  form.direction === 'deduct' && hasAmount && accountResult < 0,
              }"
            >
              {{ formatMoney(accountResult)
              }}{{
                selectedAccount.currency ? " " + selectedAccount.currency : ""
              }}
            </span>
          </div>
        </div>

        <!-- 原因 -->
        <div class="form-group">
          <label class="form-label">{{
            t("adminAdjust_label_reason", "Reason")
          }}</label>
          <textarea
            class="form-textarea"
            v-model="form.reason"
            rows="3"
            :disabled="submitting"
            :placeholder="
              t(
                'adminAdjust_placeholder_reason',
                'Describe the reason for this manual adjustment',
              )
            "
          ></textarea>
        </div>

        <!-- 说明：credit 直接打平台 / balance 走审批 -->
        <div v-if="form.target === 'tradingAccount'" class="form-hint">
          <i class="fas fa-info-circle"></i>
          <span v-if="form.type === 'credits'">
            {{
              t(
                "adminAdjust_hint_credits",
                "Syncing between the CRM and the trading platform takes time; both credits and deductions may take a while before the updated amount appears.",
              )
            }}
          </span>
          <span v-else>
            {{
              t(
                "adminAdjust_hint_taBalance",
                "Balance change is recorded as a deposit/withdrawal and reflected on the platform via the funding approval flow.",
              )
            }}
          </span>
        </div>

        <!-- 错误提示 -->
        <div v-if="errorMessage" class="form-error">
          <i class="fas fa-exclamation-circle"></i>
          {{ errorMessage }}
        </div>

        <!-- 成功提示 -->
        <div v-if="successMessage" class="form-success">
          <i class="fas fa-check-circle"></i>
          {{ successMessage }}
        </div>
      </div>

      <div class="modal-footer">
        <button
          type="button"
          class="btn btn-secondary"
          @click="emit('close')"
          :disabled="submitting"
        >
          {{
            done
              ? t("adminAdjust_btn_close", "Close")
              : t("adminAdjust_btn_cancel", "Cancel")
          }}
        </button>
        <button
          v-if="!done"
          type="button"
          class="btn btn-primary"
          :disabled="!canSubmit"
          @click="handleSubmit"
        >
          <i v-if="submitting" class="fas fa-spinner fa-spin"></i>
          <i v-else class="fas fa-paper-plane"></i>
          {{ t("adminAdjust_btn_submit", "Submit") }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from "vue";
import {
  createBalanceAdjustment,
  getWalletBalance,
} from "@/services/adminBalanceAdjustmentApi";
import {
  createTradingAccountBalance,
  createTradingAccountCredit,
} from "@/services/adminTradingAccountAdjustmentApi";
import { clientService } from "@/services/clientListService";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t } = useAdminI18n();

const props = defineProps({
  userId: {
    type: [Number, String],
    required: true,
  },
  currencyCode: {
    type: String,
    default: "USD",
  },
});

const emit = defineEmits(["close", "success"]);

const form = ref({
  type: "balance", // balance | credits
  target: "wallet", // wallet | tradingAccount
  direction: "add", // add | deduct
  amount: null,
  currencyCode: props.currencyCode || "USD",
  reason: "",
  tradingAccountId: null,
});

const submitting = ref(false);
const balanceLoading = ref(false);
const currentBalance = ref(0);
const errorMessage = ref("");
const successMessage = ref("");
const done = ref(false);

const tradingAccounts = ref([]);
const tradingLoading = ref(false);
const tradingLoaded = ref(false);

const hasAmount = computed(
  () => typeof form.value.amount === "number" && form.value.amount > 0,
);

const selectedAccount = computed(
  () =>
    tradingAccounts.value.find(
      (a) => String(a.tradingAccountId) === String(form.value.tradingAccountId),
    ) || null,
);

// 账户按平台分组，下拉里用 optgroup 拆开显示
const groupedTradingAccounts = computed(() => {
  const groups = new Map();
  tradingAccounts.value.forEach((acc) => {
    const label =
      acc.platformName ||
      acc.platformKey ||
      t("adminAdjust_target_account", "Trading Account");
    if (!groups.has(label)) groups.set(label, []);
    groups.get(label).push(acc);
  });
  return Array.from(groups.entries()).map(([label, accounts]) => ({
    label,
    accounts,
  }));
});

// 目标下拉的单一取值：'wallet' 或交易账户 id（字符串），set 时拆回 target + tradingAccountId
const targetSelection = computed({
  get() {
    if (form.value.target === "wallet") return "wallet";
    return form.value.tradingAccountId != null
      ? String(form.value.tradingAccountId)
      : "";
  },
  set(val) {
    if (val === "wallet") {
      form.value.target = "wallet";
      form.value.tradingAccountId = null;
    } else {
      form.value.target = "tradingAccount";
      form.value.tradingAccountId = Number(val);
    }
  },
});

// 结束余额 = 当前钱包余额 ± amount（仅钱包目标用）
const resultBalance = computed(() => {
  const amt = Number(form.value.amount) || 0;
  return form.value.direction === "add"
    ? currentBalance.value + amt
    : currentBalance.value - amt;
});

// 交易账户改后金额：credits 作用于 Credit，balance 作用于 Balance
const accountResult = computed(() => {
  if (!selectedAccount.value) return 0;
  const base =
    form.value.type === "credits"
      ? Number(selectedAccount.value.credit) || 0
      : Number(selectedAccount.value.balance) || 0;
  const amt = Number(form.value.amount) || 0;
  return form.value.direction === "add" ? base + amt : base - amt;
});

const canSubmit = computed(() => {
  if (submitting.value) return false;
  if (!hasAmount.value) return false;
  if (!form.value.reason || !form.value.reason.trim()) return false;
  if (form.value.target === "tradingAccount" && !form.value.tradingAccountId)
    return false;
  // 钱包扣款不允许扣到负数
  if (
    form.value.target === "wallet" &&
    form.value.direction === "deduct" &&
    resultBalance.value < 0
  )
    return false;
  return true;
});

const formatMoney = (val) => {
  const n = Number(val) || 0;
  return n.toLocaleString("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
};

// credits 只作用于交易账户：切到 credits 时若还选着钱包，清空让用户重选账户
watch(
  () => form.value.type,
  (type) => {
    if (type === "credits" && form.value.target === "wallet") {
      form.value.target = "tradingAccount";
      form.value.tradingAccountId = null;
    }
  },
);

const loadBalance = async () => {
  balanceLoading.value = true;
  try {
    const res = await getWalletBalance(props.userId);
    const payload = res?.data?.data ?? res?.data;
    currentBalance.value = Number(payload?.availableBalance ?? 0);
  } catch (e) {
    // 钱包余额加载失败不阻断操作，交易账户目标也用不到
    currentBalance.value = 0;
  } finally {
    balanceLoading.value = false;
  }
};

const loadTradingAccounts = async () => {
  tradingLoading.value = true;
  try {
    const res = await clientService.getTradingInfo(props.userId);
    tradingAccounts.value = Array.isArray(res?.data) ? res.data : [];
    tradingLoaded.value = true;
  } catch (e) {
    tradingAccounts.value = [];
  } finally {
    tradingLoading.value = false;
  }
};

const handleSubmit = async () => {
  if (!canSubmit.value) return;
  submitting.value = true;
  errorMessage.value = "";
  try {
    let res;
    if (form.value.target === "wallet") {
      // 钱包余额调整（沿用原接口）：add=credit / deduct=debit
      res = await createBalanceAdjustment({
        userId: Number(props.userId),
        direction: form.value.direction === "add" ? "credit" : "debit",
        amount: Number(form.value.amount),
        currencyCode: form.value.currencyCode,
        reason: form.value.reason.trim(),
      });
      successMessage.value = t(
        "adminAdjust_ok_wallet",
        "Wallet balance adjusted.",
      );
    } else {
      // 交易账户：add=in / deduct=out
      const payload = {
        tradingAccountId: Number(form.value.tradingAccountId),
        direction: form.value.direction === "add" ? "in" : "out",
        amount: Number(form.value.amount),
        reason: form.value.reason.trim(),
      };
      if (form.value.type === "credits") {
        res = await createTradingAccountCredit(payload);
        successMessage.value = t(
          "adminAdjust_ok_credit",
          "Credit submitted to the platform; the synced amount may be delayed.",
        );
      } else {
        res = await createTradingAccountBalance(payload);
        successMessage.value =
          form.value.direction === "add"
            ? t(
                "adminAdjust_ok_taDeposit",
                "Deposit submitted, pending approval before it reaches the account.",
              )
            : t(
                "adminAdjust_ok_taWithdraw",
                "Withdrawal submitted; the synced amount may be delayed.",
              );
      }
    }
    done.value = true;
    emit("success", res?.data?.data ?? res?.data ?? null);
  } catch (e) {
    const apiErr = e?.response?.data;
    if (apiErr?.errors?.available !== undefined) {
      errorMessage.value =
        `${apiErr.message || "Insufficient wallet balance"} ` +
        `(${t("adminAdjust_available", "available")}: ${formatMoney(apiErr.errors.available)})`;
    } else {
      errorMessage.value =
        apiErr?.message || e?.message || "Failed to submit adjustment";
    }
  } finally {
    submitting.value = false;
  }
};

const onOverlayClick = () => {
  if (!submitting.value) emit("close");
};

onMounted(() => {
  loadBalance();
  loadTradingAccounts();
});
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}
.modal {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  width: 480px;
  max-width: 90vw;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
}
.modal-header {
  padding: 18px 24px;
  border-bottom: 1px solid var(--color-border);
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.modal-title {
  font-size: 16px;
  font-weight: 600;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
}
.modal-close {
  background: none;
  border: none;
  color: var(--color-muted);
  cursor: pointer;
  font-size: 16px;
}
.modal-close:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}
.modal-body {
  padding: 20px 24px;
  overflow-y: auto;
}
.modal-footer {
  padding: 16px 24px;
  border-top: 1px solid var(--color-border);
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

.form-group {
  margin-bottom: 16px;
}
.form-label {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
  margin-bottom: 8px;
}
.form-input,
.form-textarea {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-family: inherit;
  box-sizing: border-box;
}
.form-input:focus,
.form-textarea:focus {
  outline: none;
  border-color: var(--color-brand);
}
.field-note {
  margin: 6px 0 0;
  font-size: 14px;
  color: var(--color-muted);
}

.seg-toggle,
.direction-toggle {
  display: flex;
  gap: 10px;
}
.seg-btn,
.direction-btn {
  flex: 1;
  padding: 12px;
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  border-radius: var(--radius-sm);
  cursor: pointer;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}
.seg-btn:hover:not(:disabled),
.direction-btn:hover:not(:disabled) {
  background: var(--color-surface-soft);
}
.seg-btn.active,
.direction-btn.active {
  background: var(--color-info-soft);
  border-color: var(--color-brand);
  color: var(--color-brand);
}
.seg-btn:disabled,
.direction-btn:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

.balance-preview {
  background: var(--color-surface-soft);
  border-radius: var(--radius-md);
  padding: 14px 16px;
  margin-bottom: 16px;
}
.balance-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 4px 0;
}
.balance-row + .balance-row {
  border-top: 1px dashed var(--color-border);
  margin-top: 6px;
  padding-top: 10px;
}
.balance-label {
  font-size: 14px;
  color: var(--color-muted);
}
.balance-value {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
}
.balance-value.insufficient {
  color: var(--color-danger);
}

.form-hint {
  background: var(--color-info-soft);
  color: var(--color-info);
  border-radius: var(--radius-sm);
  padding: 10px 12px;
  font-size: 14px;
  display: flex;
  align-items: flex-start;
  gap: 8px;
  margin-bottom: 12px;
}

.form-error {
  background: var(--color-danger-soft);
  color: var(--color-danger);
  border-radius: var(--radius-sm);
  padding: 10px 12px;
  font-size: 14px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.form-success {
  background: var(--color-success-soft);
  color: var(--color-success);
  border-radius: var(--radius-sm);
  padding: 10px 12px;
  font-size: 14px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn {
  padding: 9px 18px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.btn:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}
.btn-secondary {
  background: var(--color-surface-muted);
  color: var(--color-ink);
}
.btn-secondary:hover:not(:disabled) {
  background: var(--color-border);
}
.btn-primary {
  background: var(--color-brand-solid);
  color: #fff;
}
.btn-primary:hover:not(:disabled) {
  background: var(--color-brand-strong);
}
</style>
