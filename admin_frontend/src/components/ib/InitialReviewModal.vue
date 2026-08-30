<template>
  <div class="ir-modal-overlay" @click.self="$emit('close')">
    <div class="ir-modal">
      <div class="ir-modal__header">
        <h2 class="ir-modal__title">{{ t("ibIrModal_title") }}</h2>
        <button
          type="button"
          class="ir-modal__close"
          :aria-label="t('ibIrModal_ariaClose')"
          @click="$emit('close')"
        >
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="ir-modal__body">
        <!-- Part 1: 信息展示 -->
        <section class="ir-modal__section ir-modal__section--info">
          <div class="ir-modal__grid ir-modal__grid--3">
            <div class="ir-modal__field">
              <span class="ir-modal__label">{{
                t("ibIrModal_label_ibName")
              }}</span>
              <span class="ir-modal__value">{{
                row.ibName || row.companyName || "—"
              }}</span>
            </div>
            <div class="ir-modal__field">
              <span class="ir-modal__label">{{
                t("ibIrModal_label_email")
              }}</span>
              <span class="ir-modal__value">{{ row.email || "—" }}</span>
            </div>
            <div class="ir-modal__field">
              <span class="ir-modal__label">{{
                t("ibIrModal_label_phone")
              }}</span>
              <span class="ir-modal__value">{{ formatPhone(row.phone) }}</span>
            </div>
            <div class="ir-modal__field">
              <span class="ir-modal__label">{{
                t("ibIrModal_label_applicationDate")
              }}</span>
              <span class="ir-modal__value">{{
                formatDate(row.applicationDate)
              }}</span>
            </div>
            <div class="ir-modal__field">
              <span class="ir-modal__label">{{
                t("ibIrModal_label_totalClients")
              }}</span>
              <span class="ir-modal__value">{{ totalClients }}</span>
            </div>
            <div class="ir-modal__field">
              <span class="ir-modal__label">{{
                t("ibIrModal_label_status")
              }}</span>
              <span class="ir-modal__value">{{
                row.statusDisplay || formatIbStatus(row.status)
              }}</span>
            </div>
            <div class="ir-modal__field">
              <span class="ir-modal__label">{{
                t("ibIrModal_label_initialReviewer")
              }}</span>
              <span class="ir-modal__value">{{
                row.initialReviewerName || "—"
              }}</span>
            </div>
            <div class="ir-modal__field">
              <span class="ir-modal__label">{{
                t("ibIrModal_label_riskReviewer")
              }}</span>
              <span class="ir-modal__value">{{
                row.riskReviewerName || "—"
              }}</span>
            </div>
            <div class="ir-modal__field">
              <span class="ir-modal__label">{{
                t("ibIrModal_label_finalReviewer")
              }}</span>
              <span class="ir-modal__value">{{
                row.finalReviewerName || "—"
              }}</span>
            </div>
          </div>
        </section>

        <!-- Part 2: 编辑区，与第一部分同风格卡片 -->
        <section class="ir-modal__section ir-modal__section--edit">
          <div class="ir-modal__edit-card">
            <div class="ir-modal__row">
              <div class="ir-modal__field ir-modal__field--half">
                <label class="ir-modal__label">{{
                  t("ibIrModal_label_ibType")
                }}</label>
                <select v-model="form.ibType" class="ir-modal__select">
                  <option value="">
                    {{ t("ibIrModal_selectPlaceholder") }}
                  </option>
                  <option value="individual">
                    {{ t("ibIrModal_ibType_individual") }}
                  </option>
                  <option value="company">
                    {{ t("ibIrModal_ibType_company") }}
                  </option>
                </select>
              </div>
              <div class="ir-modal__field ir-modal__field--half">
                <label class="ir-modal__label">{{
                  t("ibIrModal_label_tierLevel")
                }}</label>
                <select
                  v-model="form.tierLevelId"
                  class="ir-modal__select"
                  @change="onTierLevelChange"
                >
                  <option value="">
                    {{ t("ibIrModal_selectPlaceholder") }}
                  </option>
                  <option v-for="tl in tierLevels" :key="tl.id" :value="tl.id">
                    {{
                      tParams("ibIrModal_tierOption", "{name} (Tier {level})", {
                        name: tl.tierName,
                        level: tl.tierLevel,
                      })
                    }}
                  </option>
                </select>
              </div>
            </div>

            <div class="ir-modal__field ir-modal__field--full">
              <span class="ir-modal__label">{{
                t("ibIrModal_label_ruleName")
              }}</span>
              <div v-if="rulesLoading" class="ir-modal__rules-loading">
                <i class="fas fa-spinner fa-spin"></i>
                {{ t("ibIrModal_loadingRules") }}
              </div>
              <div v-else class="ir-modal__rules-list">
                <label
                  v-for="r in ruleList"
                  :key="r.id"
                  class="ir-modal__rule-item"
                  :class="{
                    'ir-modal__rule-item--checked': form.ruleIds.includes(r.id),
                  }"
                >
                  <input type="checkbox" :value="r.id" v-model="form.ruleIds" />
                  <span>{{ r.ruleName }}</span>
                </label>
                <span
                  v-if="ruleList.length === 0"
                  class="ir-modal__rules-empty"
                  >{{ t("ibIrModal_rulesEmpty") }}</span
                >
              </div>
            </div>
          </div>
        </section>
      </div>

      <div class="ir-modal__footer">
        <button
          type="button"
          class="ir-modal__btn ir-modal__btn--secondary"
          @click="$emit('close')"
        >
          {{ t("ibIrModal_btn_cancel") }}
        </button>
        <button
          type="button"
          class="ir-modal__btn ir-modal__btn--primary"
          :disabled="saving"
          @click="onConfirm"
        >
          <i v-if="saving" class="fas fa-spinner fa-spin"></i>
          {{ t("ibIrModal_btn_confirm") }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from "vue";
import ibTierLevelsApi from "@/services/ibTierLevelsApi";
import ibRulesApi from "@/services/ibRulesApi";
import ibPartnersApi from "@/services/ibPartnersApi";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t, tParams, languageStore } = useAdminI18n();

const props = defineProps({
  row: { type: Object, required: true },
});

const emit = defineEmits(["close", "success"]);

const totalClients = 0; // 目前固定为 0

const form = ref({
  ibType: props.row.ibType || "",
  tierLevelId: props.row.tierLevelId ?? "",
  ruleIds: [],
});

const tierLevels = ref([]);
const ruleList = ref([]);
const rulesLoading = ref(false);
const saving = ref(false);

const formatDate = (dateString) => {
  if (!dateString) return "—";
  const date = new Date(dateString);
  const loc = languageStore.currentLanguage === "zh" ? "zh-CN" : "en-US";
  return date.toLocaleDateString(loc, {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
};

const formatPhone = (phone) => {
  if (!phone || typeof phone !== "string") return "—";
  return phone.trim() || "—";
};

const formatIbStatus = (status) => {
  if (!status || typeof status !== "string") return "—";
  const keyMap = {
    pending_initial_review: "ibIr_status_pending_initial_review",
    pending_risk_review: "ibIr_status_pending_risk_review",
    pending_final_review: "ibIr_status_pending_final_review",
    approved: "ibIr_status_approved",
    rejected: "ibIr_status_rejected",
  };
  const k = keyMap[status];
  return k
    ? t(k)
    : status.replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
};

const loadTierLevels = async () => {
  try {
    const res = await ibTierLevelsApi.getActiveTiers();
    const data = res?.data ?? res;
    tierLevels.value = Array.isArray(data) ? data : (data?.items ?? []);
  } catch (e) {
    console.error("Failed to load tier levels:", e);
    tierLevels.value = [];
  }
};

const loadRules = async () => {
  rulesLoading.value = true;
  ruleList.value = [];
  try {
    const tierId = form.value.tierLevelId;
    if (!tierId) {
      rulesLoading.value = false;
      return;
    }
    const res = await ibRulesApi.getActiveRulesByTierLevel(tierId);
    const data = res?.data ?? res;
    ruleList.value = Array.isArray(data) ? data : (data?.items ?? []);
  } catch (e) {
    console.error("Failed to load rules:", e);
    ruleList.value = [];
  } finally {
    rulesLoading.value = false;
  }
};

const onTierLevelChange = () => {
  form.value.ruleIds = [];
  loadRules();
};

const onConfirm = async () => {
  saving.value = true;
  try {
    const payload = {
      ibType: form.value.ibType || null,
      tierLevelId: form.value.tierLevelId || null,
      ruleIds: (form.value.ruleIds || [])
        .map((id) => Number(id))
        .filter(Boolean),
    };
    await ibPartnersApi.submitInitialReview(props.row.id, payload);
    emit("success");
    emit("close");
  } catch (err) {
    const raw = err.response?.data?.message || err.message || "";
    const msg = raw || t("ibIrModal_errGeneric");
    alert(tParams("ibIrModal_alert_err", "{msg}", { msg }));
  } finally {
    saving.value = false;
  }
};

/** 从 row.ruleIds 解析出已选规则 ID 数组（后端返回逗号分隔字符串或已是数组） */
function parseRuleIdsFromRow(row) {
  if (!row) return [];
  const raw = row.ruleIds;
  if (Array.isArray(raw)) return raw.map((id) => Number(id)).filter(Boolean);
  if (raw == null || raw === "") return [];
  return String(raw)
    .split(",")
    .map((s) => parseInt(s.trim(), 10))
    .filter((n) => !Number.isNaN(n) && n > 0);
}

watch(
  () => props.row,
  (r) => {
    form.value.ibType = r?.ibType || "";
    form.value.tierLevelId = r?.tierLevelId ?? "";
    form.value.ruleIds = parseRuleIdsFromRow(r);
    if (r?.tierLevelId) loadRules();
  },
  { immediate: true },
);

loadTierLevels();
if (props.row?.tierLevelId) loadRules();
</script>

<style scoped>
.ir-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.ir-modal {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
  max-width: 720px;
  width: 95%;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

/* 标题区：与 IB Invitation 一致，顶部圆角与弹窗一致 */
.ir-modal__header {
  padding: 20px 24px;
  border-bottom: 2px solid rgba(255, 255, 255, 0.2);
  background: var(--color-brand-solid);
  color: white;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-radius: 12px 12px 0 0;
}

.ir-modal__title {
  margin: 0;
  font-size: 20px;
  font-weight: 700;
  color: white;
}

.ir-modal__close {
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
  padding: 0;
}

.ir-modal__close:hover {
  background: rgba(255, 255, 255, 0.3);
  color: white;
}

.ir-modal__body {
  padding: 0;
  overflow-y: auto;
}

/* 第一部分：信息展示区，卡片式 */
.ir-modal__section--info {
  padding: 20px 24px;
  margin: 20px 24px 0;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.ir-modal__section {
  margin-bottom: 0;
}

/* 第二部分：编辑区，与第一部分同风格卡片 */
.ir-modal__section--edit {
  padding: 20px 24px 24px;
  background: var(--color-surface);
}

.ir-modal__edit-card {
  padding: 20px 24px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.ir-modal__edit-card .ir-modal__row {
  margin-bottom: 20px;
}

.ir-modal__edit-card .ir-modal__field--full {
  margin-bottom: 0;
}

.ir-modal__grid {
  display: grid;
  gap: 0;
}

.ir-modal__grid--3 {
  grid-template-columns: repeat(3, 1fr);
}

.ir-modal__section--info .ir-modal__field {
  padding: 10px 14px;
  border-bottom: 1px solid #f0f4f8;
  border-right: 1px solid #f0f4f8;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.ir-modal__section--info .ir-modal__field:nth-child(3n) {
  border-right: none;
}

.ir-modal__section--info .ir-modal__field:nth-last-child(-n + 3) {
  border-bottom: none;
}

.ir-modal__section--info .ir-modal__label {
  font-size: 14px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: var(--color-muted);
  font-weight: 600;
}

.ir-modal__section--info .ir-modal__value {
  font-size: 14px;
  color: var(--color-ink);
  font-weight: 500;
}

.ir-modal__field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.ir-modal__field--half {
  flex: 1;
  min-width: 0;
}

.ir-modal__field--full {
  width: 100%;
}

.ir-modal__row {
  display: flex;
  gap: 20px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}

.ir-modal__label {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
}

.ir-modal__value {
  font-size: 14px;
  color: var(--color-ink);
}

.ir-modal__select {
  padding: 10px 12px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  background: var(--color-surface);
  min-width: 160px;
}

.ir-modal__select:focus {
  outline: none;
  border-color: var(--color-brand);
}

/* Rule Name 列表：白底 + 细线分隔，选中态高亮 */
.ir-modal__rules-list {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0;
  max-height: 200px;
  overflow-y: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
}

.ir-modal__rule-item {
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  font-size: 14px;
  color: var(--color-ink);
  padding: 10px 12px;
  border-bottom: 1px solid #f0f4f8;
  border-right: 1px solid #f0f4f8;
  transition: background 0.15s ease;
}

.ir-modal__rule-item:nth-child(3n) {
  border-right: none;
}

.ir-modal__rule-item:nth-last-child(-n + 3) {
  border-bottom: none;
}

.ir-modal__rule-item:hover {
  background: var(--color-surface-soft);
}

.ir-modal__rule-item--checked {
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.ir-modal__rule-item--checked:hover {
  background: var(--color-brand-soft);
}

.ir-modal__rule-item input {
  cursor: pointer;
  accent-color: var(--color-brand);
}

.ir-modal__rules-empty {
  grid-column: 1 / -1;
  color: var(--color-muted);
  font-size: 14px;
  padding: 20px;
  text-align: center;
}

.ir-modal__rules-loading {
  padding: 16px;
  color: var(--color-brand);
  font-size: 14px;
}

.ir-modal__footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 16px 24px;
  border-top: 1px solid var(--color-border);
  border-radius: 0 0 12px 12px;
  background: var(--color-surface);
}

.ir-modal__btn {
  padding: 10px 20px;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  border: none;
}

.ir-modal__btn--secondary {
  background: var(--color-border);
  color: var(--color-text);
}

.ir-modal__btn--secondary:hover {
  background: var(--color-border-strong);
}

/* Confirm 与 IB Invitation 一致 */
.ir-modal__btn--primary {
  background: var(--color-brand-solid);
  color: white;
  box-shadow: 0 2px 8px rgba(var(--color-brand-rgb), 0.3);
}

.ir-modal__btn--primary:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.4);
}

.ir-modal__btn--primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

@media (max-width: 640px) {
  .ir-modal__grid--3 {
    grid-template-columns: 1fr;
  }
  .ir-modal__section--info .ir-modal__field {
    border-right: none;
  }
  .ir-modal__section--info .ir-modal__field:nth-last-child(-n + 3) {
    border-bottom: 1px solid #f0f4f8;
  }
  .ir-modal__section--info .ir-modal__field:last-child {
    border-bottom: none;
  }
  .ir-modal__rules-list {
    grid-template-columns: 1fr;
  }
  .ir-modal__rule-item:nth-child(3n) {
    border-right: 1px solid #f0f4f8;
  }
  .ir-modal__rule-item:nth-last-child(-n + 3) {
    border-bottom: 1px solid #f0f4f8;
  }
  .ir-modal__rule-item:last-child {
    border-bottom: none;
  }
  .ir-modal__row {
    flex-direction: column;
  }
}
</style>
