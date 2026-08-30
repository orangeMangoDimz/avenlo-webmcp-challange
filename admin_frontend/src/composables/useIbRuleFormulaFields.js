/** 佣金规则弹窗：按 ruleType 控制公式相关字段显隐（与后端算佣公式一致） */

export function getFormulaFieldVisibility(ruleType) {
  return {
    showRate: ruleType === "cash_back_rebate",
    showFixedAmount: ruleType === "per_lot" || ruleType === "per_trade",
    showRebateAmount:
      ruleType === "per_trade_rebate" || ruleType === "per_lot_rebate",
  };
}

export function buildFormulaFieldsPayload(ruleType, form) {
  const visibility = getFormulaFieldVisibility(ruleType);
  return {
    rate: visibility.showRate ? form.rate : null,
    fixed_amount: visibility.showFixedAmount ? form.fixed_amount : null,
    rebateAmount: visibility.showRebateAmount ? form.rebateAmount : null,
  };
}

export function validateFormulaFields(ruleType, form) {
  const visibility = getFormulaFieldVisibility(ruleType);
  if (visibility.showRebateAmount) {
    if (
      form.rebateAmount == null ||
      form.rebateAmount === "" ||
      Number(form.rebateAmount) <= 0
    ) {
      return "ibRuleModal_alert_rebateAmountRequired";
    }
  }
  return null;
}

export function clearHiddenFormulaFields(ruleType, form) {
  const visibility = getFormulaFieldVisibility(ruleType);
  if (!visibility.showRate) form.rate = null;
  if (!visibility.showFixedAmount) form.fixed_amount = null;
  if (!visibility.showRebateAmount) form.rebateAmount = null;
}

export function getFormulaCommissionRate(ruleType, form) {
  const visibility = getFormulaFieldVisibility(ruleType);
  if (visibility.showRebateAmount) return Number(form.rebateAmount || 0);
  if (visibility.showRate) return Number(form.rate || 0);
  if (visibility.showFixedAmount) return Number(form.fixed_amount ?? 0);
  return 0;
}
