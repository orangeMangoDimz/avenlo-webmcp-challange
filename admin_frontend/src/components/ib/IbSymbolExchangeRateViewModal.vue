<template>
  <Teleport to="body">
    <div class="modal-overlay" @click="$emit('close')">
      <div class="modal ib-ex-rate-view-modal" @click.stop>
        <div class="modal-header">
          <h2 class="modal-title">{{ t("ibExRateView_title") }}</h2>
          <button
            type="button"
            class="modal-close"
            :aria-label="t('ibExRateModal_close')"
            @click="$emit('close')"
          >
            <i class="fas fa-times"></i>
          </button>
        </div>

        <div class="modal-body">
          <dl class="detail-list">
            <div class="detail-row">
              <dt>{{ t("ibExRate_col_symbol") }}</dt>
              <dd>{{ rate?.symbol || "—" }}</dd>
            </div>
            <div class="detail-row">
              <dt>{{ t("ibExRateModal_baseCurrency") }}</dt>
              <dd>{{ rate?.baseCurrency || "—" }}</dd>
            </div>
            <div class="detail-row">
              <dt>{{ t("ibExRateModal_targetCurrency") }}</dt>
              <dd>{{ rate?.targetCurrency || "—" }}</dd>
            </div>
            <div class="detail-row">
              <dt>{{ t("ibExRate_col_rate") }}</dt>
              <dd>{{ formatExchangeRateLine(rate) }}</dd>
            </div>
            <div class="detail-row">
              <dt>{{ t("ibExRate_col_mode") }}</dt>
              <dd>
                <span class="mode-badge" :class="rate?.syncMode">
                  {{
                    rate?.syncMode === "manual"
                      ? t("ibExRate_mode_manual")
                      : t("ibExRate_mode_auto")
                  }}
                </span>
              </dd>
            </div>
            <div class="detail-row">
              <dt>{{ t("ibExRate_col_updatedAt") }}</dt>
              <dd>{{ formatDateTime(rate?.updatedAt) }}</dd>
            </div>
            <div class="detail-row">
              <dt>{{ t("ibExRateModal_remarks") }}</dt>
              <dd>{{ rate?.remarks || "—" }}</dd>
            </div>
          </dl>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-close" @click="$emit('close')">
            {{ t("ibExRateModal_close") }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { useAdminI18n } from "@/composables/useAdminI18n";

defineProps({
  rate: { type: Object, default: null },
});

defineEmits(["close"]);

const { t, tParams } = useAdminI18n();

const formatExchangeRateDisplay = (value) =>
  new Intl.NumberFormat(undefined, {
    minimumFractionDigits: 0,
    maximumFractionDigits: 5,
  }).format(value);

const formatExchangeRateLine = (row) => {
  if (!row) return "—";
  const n = Number(row.exchangeRate);
  if (row.exchangeRate == null || row.exchangeRate === "" || Number.isNaN(n))
    return "—";
  const code =
    row.exchangeRateCurrency || row.targetCurrency || row.baseCurrency || "USD";
  return tParams("txnSettings_rateLine", "1 USD = {rate} {code}", {
    rate: formatExchangeRateDisplay(n),
    code: String(code).trim(),
  }).replace(/\s+$/u, "");
};

const formatDateTime = (value) => {
  if (!value) return "—";
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return String(value);
  const pad = (n) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
};
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
  animation: ibExRateViewFadeIn 0.3s ease;
}

@keyframes ibExRateViewFadeIn {
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
  max-width: 520px;
  width: 92vw;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
  animation: ibExRateViewSlideUp 0.3s ease;
}

@keyframes ibExRateViewSlideUp {
  from {
    opacity: 0;
    transform: translateY(-20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.modal-header {
  flex-shrink: 0;
  padding: 22px 28px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--color-surface-soft);
}

.modal-title {
  font-size: 20px;
  font-weight: 600;
  color: var(--color-ink);
  margin: 0;
}

.modal-close {
  background: var(--color-border);
  border: none;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
  font-size: 16px;
  color: var(--color-text);
}

.modal-close:hover {
  background: var(--color-brand-solid);
  color: #fff;
}

.modal-body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: 28px;
}

.ib-ex-rate-view-modal {
  max-width: 520px;
}

.detail-list {
  margin: 0;
}

.detail-row {
  display: grid;
  grid-template-columns: 120px 1fr;
  gap: 12px;
  padding: 10px 0;
  border-bottom: 1px solid var(--color-surface-muted);
}

.detail-row dt {
  margin: 0;
  font-size: 14px;
  color: var(--color-muted);
}

.detail-row dd {
  margin: 0;
  font-size: 14px;
  color: var(--color-ink);
}

.mode-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 14px;
  font-weight: 600;
}

.mode-badge.auto {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.mode-badge.manual {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.view-placeholder-note {
  margin: 16px 0 0;
  font-size: 14px;
  color: var(--color-faint);
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  padding: 16px 24px 24px;
}

.btn-close {
  background: var(--color-brand-solid);
  border: none;
  color: #fff;
  padding: 10px 18px;
  border-radius: var(--radius-md);
  cursor: pointer;
}
</style>
