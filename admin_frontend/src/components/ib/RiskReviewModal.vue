<template>
  <div class="ir-modal-overlay" @click.self="$emit('close')">
    <div class="ir-modal">
      <div class="ir-modal__header">
        <h2 class="ir-modal__title">{{ t("ibRrModal_title") }}</h2>
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
        <!-- Part 1: 信息展示（比 Initial Review 多 Tier Level、Rule Name） -->
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
                t("ibIrModal_label_tierLevel")
              }}</span>
              <span class="ir-modal__value">{{
                row.tierLevelName || "—"
              }}</span>
            </div>
            <div class="ir-modal__field">
              <span class="ir-modal__label">{{
                t("ibIrModal_label_ruleName")
              }}</span>
              <span class="ir-modal__value">{{ row.ruleNames || "—" }}</span>
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

        <!-- Part 2: Group 多选（样式与 Initial Review 的 Rule Name 一致） -->
        <section class="ir-modal__section ir-modal__section--edit">
          <div class="ir-modal__edit-card">
            <div class="ir-modal__field ir-modal__field--full">
              <span class="ir-modal__label">{{
                t("ibRrModal_label_group")
              }}</span>
              <div v-if="groupsLoading" class="ir-modal__rules-loading">
                <i class="fas fa-spinner fa-spin"></i>
                {{ t("ibRrModal_loadingGroups") }}
              </div>
              <template v-else>
                <div
                  v-if="platformOptions.length > 1"
                  class="ir-modal__group-filter"
                >
                  <button
                    type="button"
                    class="ir-modal__filter-chip"
                    :class="{
                      'ir-modal__filter-chip--active': platformFilter === '',
                    }"
                    @click="platformFilter = ''"
                  >
                    {{ t("ibRrModal_groupFilter_all", "All") }}
                  </button>
                  <button
                    type="button"
                    v-for="pk in platformOptions"
                    :key="pk"
                    class="ir-modal__filter-chip"
                    :class="{
                      'ir-modal__filter-chip--active': platformFilter === pk,
                    }"
                    @click="platformFilter = pk"
                  >
                    {{ platformBadge(pk) }}
                  </button>
                </div>
                <div class="ir-modal__rules-list">
                  <label
                    v-for="g in filteredGroups"
                    :key="g.id"
                    class="ir-modal__rule-item"
                    :class="{
                      'ir-modal__rule-item--checked': form.groupIds.includes(
                        g.id,
                      ),
                    }"
                  >
                    <input
                      type="checkbox"
                      :value="g.id"
                      v-model="form.groupIds"
                    />
                    <span class="ir-modal__rule-name">{{ g.name }}</span>
                    <span class="ir-modal__group-platform">{{
                      platformBadge(g.trading_platforms_key)
                    }}</span>
                  </label>
                  <span
                    v-if="filteredGroups.length === 0"
                    class="ir-modal__rules-empty"
                    >{{ t("ibRrModal_groupsEmpty") }}</span
                  >
                </div>
              </template>
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
import { ref, watch, computed } from "vue";
import ibPartnersApi from "@/services/ibPartnersApi";
import loginSettingsService from "@/services/loginSettingsService";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t, tParams, languageStore } = useAdminI18n();

const props = defineProps({
  row: { type: Object, required: true },
});

const emit = defineEmits(["close", "success"]);

const totalClients = 0;

const form = ref({
  groupIds: [],
});

const groups = ref([]);
const groupsLoading = ref(false);
const saving = ref(false);

// 分配 group 时按平台筛选，并在 group 名后标注平台，避免多平台 group 混在一起看不出来
const platformFilter = ref("");

// 平台徽标显示名，沿用 ClientDetailRow 的惯例（financepro 特例，其余大写）
const platformBadge = (key) => {
  if (!key) return "";
  if (key === "financepro") return "FinancePro";
  return String(key).toUpperCase();
};

// 只列出真正有 group 的平台，不硬编码平台清单
const platformOptions = computed(() =>
  [
    ...new Set(
      groups.value.map((g) => g.trading_platforms_key).filter(Boolean),
    ),
  ].sort(),
);

const filteredGroups = computed(() =>
  platformFilter.value
    ? groups.value.filter(
        (g) => g.trading_platforms_key === platformFilter.value,
      )
    : groups.value,
);

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

/** 从 row.groupIds / row.groupId 解析已选组别 ID（后端逗号分隔字符串或数组） */
function parseGroupIdsFromRow(row) {
  if (!row) return [];
  const raw = row.groupIds;
  if (Array.isArray(raw)) return raw.map((id) => Number(id)).filter(Boolean);
  if (raw != null && raw !== "") {
    return String(raw)
      .split(",")
      .map((s) => parseInt(s.trim(), 10))
      .filter((n) => !Number.isNaN(n) && n > 0);
  }
  const gid = row.groupId;
  if (gid != null && gid !== "") {
    const n = parseInt(String(gid), 10);
    return n > 0 ? [n] : [];
  }
  return [];
}

const loadGroups = async () => {
  groupsLoading.value = true;
  try {
    const res = await loginSettingsService.getTradingGroups();
    const data = res?.data ?? res;
    const raw = Array.isArray(data) ? data : (data?.items ?? []);
    groups.value = raw
      .map((g) => ({
        ...g,
        id: Number(g.id),
      }))
      .filter((g) => !Number.isNaN(g.id) && g.id > 0);
  } catch (e) {
    console.error("Failed to load trading groups:", e);
    groups.value = [];
  } finally {
    groupsLoading.value = false;
  }
};

const onConfirm = async () => {
  saving.value = true;
  try {
    const payload = {
      groupIds: (form.value.groupIds || [])
        .map((id) => Number(id))
        .filter(Boolean),
    };
    await ibPartnersApi.submitRiskReview(props.row.id, payload);
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

watch(
  () => props.row,
  (r) => {
    form.value.groupIds = parseGroupIdsFromRow(r);
  },
  { immediate: true },
);

loadGroups();
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

.ir-modal__field--full {
  width: 100%;
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

/* Group 多选：与 Initial Review Rule Name 一致 */
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

/* 平台筛选按钮条 */
.ir-modal__group-filter {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 10px;
}

.ir-modal__filter-chip {
  padding: 4px 12px;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  border-radius: 999px;
  cursor: pointer;
  transition: all 0.15s ease;
}

.ir-modal__filter-chip:hover {
  background: var(--color-border);
}

.ir-modal__filter-chip--active {
  color: #fff;
  background: var(--color-brand-solid);
  border-color: var(--color-brand);
}

/* group 名占满，平台标签靠右 */
.ir-modal__rule-name {
  flex: 1;
}

/* group 所属平台标签 */
.ir-modal__group-platform {
  flex-shrink: 0;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-muted);
  background: var(--color-surface-soft);
  padding: 1px 7px;
  border-radius: 4px;
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
}
</style>
