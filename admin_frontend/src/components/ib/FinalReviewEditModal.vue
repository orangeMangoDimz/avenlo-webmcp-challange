<template>
  <div class="ir-modal-overlay" @click.self="$emit('close')">
    <div class="ir-modal ir-modal--wide">
      <div class="ir-modal__header">
        <h2 class="ir-modal__title">
          {{ t("ib_finalReview_edit_title", "Edit IB (Post-Approval)") }}
        </h2>
        <button
          type="button"
          class="ir-modal__close"
          :aria-label="t('ib_finalReview_edit_closeAria', 'Close')"
          @click="$emit('close')"
        >
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="ir-modal__body">
        <section class="ir-modal__section ir-modal__section--info">
          <div class="ir-modal__grid ir-modal__grid--3">
            <div class="ir-modal__field">
              <span class="ir-modal__label">{{
                t("ib_finalReview_col_ibName", "IB Name")
              }}</span>
              <span class="ir-modal__value">{{
                row.ibName || row.companyName || emDash
              }}</span>
            </div>
            <div class="ir-modal__field">
              <span class="ir-modal__label">{{
                t("ib_finalReview_col_email", "Email")
              }}</span>
              <span class="ir-modal__value">{{ row.email || emDash }}</span>
            </div>
            <div class="ir-modal__field">
              <span class="ir-modal__label">{{
                t("ib_finalReview_col_phone", "Phone")
              }}</span>
              <span class="ir-modal__value">{{ formatPhone(row.phone) }}</span>
            </div>
            <div class="ir-modal__field">
              <span class="ir-modal__label">{{
                t("ib_finalReview_col_applicationDate", "Application Date")
              }}</span>
              <span class="ir-modal__value">{{
                formatDate(row.applicationDate)
              }}</span>
            </div>
            <div class="ir-modal__field">
              <span class="ir-modal__label">{{
                t("ib_finalReview_col_totalClients", "Total Clients")
              }}</span>
              <span class="ir-modal__value">{{ row.totalClients ?? 0 }}</span>
            </div>
            <div class="ir-modal__field">
              <span class="ir-modal__label">{{
                t("ib_finalReview_col_status", "Status")
              }}</span>
              <span class="ir-modal__value">{{
                row.statusDisplay || formatIbStatus(row.status)
              }}</span>
            </div>
            <div class="ir-modal__field">
              <span class="ir-modal__label">{{
                t("ib_finalReview_col_initialReviewer", "Initial Reviewer")
              }}</span>
              <span class="ir-modal__value">{{
                row.initialReviewerName || emDash
              }}</span>
            </div>
            <div class="ir-modal__field">
              <span class="ir-modal__label">{{
                t("ib_finalReview_col_riskReviewer", "Risk Reviewer")
              }}</span>
              <span class="ir-modal__value">{{
                row.riskReviewerName || emDash
              }}</span>
            </div>
            <div class="ir-modal__field">
              <span class="ir-modal__label">{{
                t("ib_finalReview_col_finalReviewer", "Final Reviewer")
              }}</span>
              <span class="ir-modal__value">{{
                row.finalReviewerName || emDash
              }}</span>
            </div>
          </div>
        </section>

        <section class="ir-modal__section ir-modal__section--edit">
          <div class="ir-modal__edit-card">
            <div v-if="tierChangeBlocked" class="ir-modal__alert" role="alert">
              <strong>{{
                t(
                  "ib_finalReview_edit_tierBlockedTitle",
                  "Tier Level cannot be changed yet.",
                )
              }}</strong>
              {{
                t(
                  "ib_finalReview_edit_tierBlockedBody",
                  "You must unbind all IB relationships first (client bindings do not block this).",
                )
              }}
              <ul class="ir-modal__alert-list">
                <li v-if="blockers.parentIb">
                  <span class="ir-modal__alert-label">{{
                    t("ib_finalReview_edit_parentIb", "Parent IB:")
                  }}</span>
                  {{ blockers.parentIb.ibCode || emDash }} —
                  {{ blockers.parentIb.ibName || emDash }}
                  <span class="ir-modal__alert-hint">{{
                    t("ib_finalReview_edit_parentHint", "")
                  }}</span>
                </li>
                <li v-for="c in blockers.childIbs" :key="'ch-' + c.id">
                  <span class="ir-modal__alert-label">{{
                    t("ib_finalReview_edit_childIb", "Child IB:")
                  }}</span>
                  {{ c.ibCode || emDash }} — {{ c.ibName || emDash }}
                  <span class="ir-modal__alert-hint">{{
                    t("ib_finalReview_edit_childHint", "")
                  }}</span>
                </li>
              </ul>
            </div>

            <div class="ir-modal__row">
              <div class="ir-modal__field ir-modal__field--half">
                <label class="ir-modal__label">{{
                  t("ib_finalReview_edit_ibType", "IB Type")
                }}</label>
                <select v-model="form.ibType" class="ir-modal__select">
                  <option value="">
                    {{
                      t("ib_finalReview_edit_selectPlaceholder", "— Select —")
                    }}
                  </option>
                  <option value="individual">
                    {{
                      t("ib_finalReview_edit_ibTypeIndividual", "Individual")
                    }}
                  </option>
                  <option value="company">
                    {{ t("ib_finalReview_edit_ibTypeCompany", "Company") }}
                  </option>
                </select>
              </div>
              <div class="ir-modal__field ir-modal__field--half">
                <label class="ir-modal__label">{{
                  t("ib_finalReview_edit_tierLevel", "Tier Level")
                }}</label>
                <select
                  v-model="form.tierLevelId"
                  class="ir-modal__select"
                  @change="onTierLevelChange"
                >
                  <option value="">
                    {{
                      t("ib_finalReview_edit_selectPlaceholder", "— Select —")
                    }}
                  </option>
                  <option v-for="tl in tierLevels" :key="tl.id" :value="tl.id">
                    {{ tl.tierName }} {{ tierOptionSuffix(tl.tierLevel) }}
                  </option>
                </select>
              </div>
            </div>

            <div class="ir-modal__field ir-modal__field--full">
              <span class="ir-modal__label">{{
                t("ib_finalReview_edit_ruleName", "Rule Name")
              }}</span>
              <div v-if="rulesLoading" class="ir-modal__rules-loading">
                <i class="fas fa-spinner fa-spin"></i>
                {{ t("ib_finalReview_edit_loadingRules", "Loading rules...") }}
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
                  >{{
                    t("ib_finalReview_edit_noRules", "No rules available")
                  }}</span
                >
              </div>
            </div>

            <div
              class="ir-modal__field ir-modal__field--full ir-modal__field--groups"
            >
              <span class="ir-modal__label">{{
                t("ib_finalReview_edit_group", "Group")
              }}</span>
              <div v-if="groupsLoading" class="ir-modal__rules-loading">
                <i class="fas fa-spinner fa-spin"></i>
                {{
                  t("ib_finalReview_edit_loadingGroups", "Loading groups...")
                }}
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
                    {{ t("ib_finalReview_edit_groupFilter_all", "All") }}
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
                    >{{
                      t("ib_finalReview_edit_noGroups", "No groups available")
                    }}</span
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
          {{ t("ib_finalReview_btn_cancel", "Cancel") }}
        </button>
        <button
          type="button"
          class="ir-modal__btn ir-modal__btn--primary"
          :disabled="saving || saveDisabled"
          @click="onConfirm"
        >
          <i v-if="saving" class="fas fa-spinner fa-spin"></i>
          {{ t("ib_finalReview_btn_save", "Save") }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed, onMounted } from "vue";
import ibTierLevelsApi from "@/services/ibTierLevelsApi";
import ibRulesApi from "@/services/ibRulesApi";
import ibPartnersApi from "@/services/ibPartnersApi";
import loginSettingsService from "@/services/loginSettingsService";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { useLanguageStore } from "@/stores/language";

const props = defineProps({
  row: { type: Object, required: true },
});

const emit = defineEmits(["close", "success"]);

const { t, tParams } = useAdminI18n();
const languageStore = useLanguageStore();

const emDash = computed(() => t("ib_common_emDash", "—"));

function tierOptionSuffix(level) {
  const n = level != null && level !== "" ? String(level) : "";
  return tParams("ib_finalReview_edit_tierSuffix", "(Tier {n})", { n });
}

const form = ref({
  ibType: "",
  tierLevelId: "",
  ruleIds: [],
  groupIds: [],
});

const initialTierLevelId = ref(null);

const tierLevels = ref([]);
const ruleList = ref([]);
const rulesLoading = ref(false);
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

const blockers = ref({ parentIb: null, childIbs: [] });
const blockersLoading = ref(false);

const tierChangeBlocked = computed(() => {
  const cur =
    form.value.tierLevelId === "" || form.value.tierLevelId === null
      ? null
      : Number(form.value.tierLevelId);
  const init = initialTierLevelId.value;
  if (init == null || cur == null || Number(init) === Number(cur)) return false;
  const p = blockers.value.parentIb;
  const kids = blockers.value.childIbs || [];
  return !!(p || (kids && kids.length > 0));
});

const saveDisabled = computed(() => tierChangeBlocked.value);

const formatDate = (dateString) => {
  if (!dateString) return t("ib_common_emDash", "—");
  const d = new Date(dateString);
  if (Number.isNaN(d.getTime())) return t("ib_common_emDash", "—");
  const loc = languageStore.currentLanguage === "zh" ? "zh-CN" : "en-US";
  return d.toLocaleDateString(loc, {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
};

const formatPhone = (phone) => {
  if (!phone || typeof phone !== "string") return t("ib_common_emDash", "—");
  return phone.trim() || t("ib_common_emDash", "—");
};

const formatIbStatus = (status) => {
  if (!status || typeof status !== "string") return t("ib_common_emDash", "—");
  const key = `ib_status_${status}`;
  const translated = t(key, "");
  if (translated && translated !== key) return translated;
  return status.replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
};

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

const loadTierLevels = async () => {
  try {
    const res = await ibTierLevelsApi.getActiveTiers();
    const data = res?.data ?? res;
    tierLevels.value = Array.isArray(data) ? data : (data?.items ?? []);
  } catch (e) {
    console.error(e);
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
    const raw = Array.isArray(data) ? data : (data?.items ?? []);
    ruleList.value = raw
      .map((r) => ({ ...r, id: Number(r.id) }))
      .filter((r) => !Number.isNaN(r.id) && r.id > 0);
  } catch (e) {
    console.error(e);
    ruleList.value = [];
  } finally {
    rulesLoading.value = false;
  }
};

const loadGroups = async () => {
  groupsLoading.value = true;
  try {
    const res = await loginSettingsService.getTradingGroups();
    const data = res?.data ?? res;
    const raw = Array.isArray(data) ? data : (data?.items ?? []);
    groups.value = raw
      .map((g) => ({ ...g, id: Number(g.id) }))
      .filter((g) => !Number.isNaN(g.id) && g.id > 0);
  } catch (e) {
    console.error(e);
    groups.value = [];
  } finally {
    groupsLoading.value = false;
  }
};

const fetchBlockers = async () => {
  blockersLoading.value = true;
  try {
    const res = await ibPartnersApi.getTierChangeBlockers(props.row.id);
    const payload = res?.data;
    blockers.value = {
      parentIb: payload?.parentIb ?? null,
      childIbs: Array.isArray(payload?.childIbs) ? payload.childIbs : [],
    };
  } catch (e) {
    console.error(e);
    blockers.value = { parentIb: null, childIbs: [] };
  } finally {
    blockersLoading.value = false;
  }
};

const onTierLevelChange = () => {
  form.value.ruleIds = [];
  loadRules();
};

const onConfirm = async () => {
  if (saveDisabled.value) return;
  saving.value = true;
  try {
    if (!form.value.tierLevelId) {
      alert(
        t(
          "ib_finalReview_edit_selectTierRequired",
          "Please select a Tier Level.",
        ),
      );
      saving.value = false;
      return;
    }
    const payload = {
      ibType: form.value.ibType || null,
      tierLevelId: Number(form.value.tierLevelId),
      ruleIds: (form.value.ruleIds || [])
        .map((id) => Number(id))
        .filter(Boolean),
      groupIds: (form.value.groupIds || [])
        .map((id) => Number(id))
        .filter(Boolean),
    };
    await ibPartnersApi.postApprovalUpdate(props.row.id, payload);
    alert(t("ib_finalReview_edit_saved", "IB details saved successfully."));
    emit("success");
    emit("close");
  } catch (err) {
    const msg =
      err?.message ||
      err?.response?.data?.message ||
      t("ib_finalReview_edit_saveFailed", "Save failed");
    const b = err?.errors?.blockers || err?.response?.data?.errors?.blockers;
    if (b && (b.parentIb || (b.childIbs && b.childIbs.length))) {
      blockers.value = {
        parentIb: b.parentIb || null,
        childIbs: Array.isArray(b.childIbs) ? b.childIbs : [],
      };
      alert(
        msg +
          "\n\n" +
          t(
            "ib_finalReview_edit_tierBlockedApiHint",
            "See the highlighted relationships in the dialog.",
          ),
      );
    } else {
      alert(msg);
    }
  } finally {
    saving.value = false;
  }
};

function applyRow(r) {
  if (!r) return;
  form.value.ibType = r.ibType || "";
  form.value.tierLevelId = r.tierLevelId ?? "";
  initialTierLevelId.value =
    r.tierLevelId != null && r.tierLevelId !== ""
      ? Number(r.tierLevelId)
      : null;
  form.value.ruleIds = parseRuleIdsFromRow(r);
  form.value.groupIds = parseGroupIdsFromRow(r);
  if (r.tierLevelId) loadRules();
}

watch(
  () => props.row,
  (r) => {
    applyRow(r);
  },
  { immediate: true },
);

onMounted(() => {
  loadTierLevels();
  loadGroups();
  fetchBlockers();
});
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

.ir-modal--wide {
  max-width: 760px;
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

.ir-modal__grid--3 {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0;
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
  font-size: 11px;
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

.ir-modal__field--groups {
  margin-top: 20px;
}

.ir-modal__row {
  display: flex;
  gap: 20px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}

.ir-modal__label {
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text);
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
  font-size: 13px;
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

/* 平台筛选按钮条 */
.ir-modal__group-filter {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 10px;
}

.ir-modal__filter-chip {
  padding: 4px 12px;
  font-size: 12px;
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
  font-size: 11px;
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

.ir-modal__alert {
  margin-bottom: 20px;
  padding: 14px 16px;
  background: var(--color-warning-soft);
  border: 1px solid #fcd34d;
  border-radius: var(--radius-md);
  color: #78350f;
  font-size: 14px;
  line-height: 1.5;
}

.ir-modal__alert-list {
  margin: 10px 0 0 1.2em;
  padding: 0;
}

.ir-modal__alert-label {
  font-weight: 600;
}

.ir-modal__alert-hint {
  display: block;
  font-size: 12px;
  color: var(--color-warning);
  margin-top: 4px;
}

.ir-modal__footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 16px 24px;
  border-top: 1px solid var(--color-border);
  background: var(--color-surface);
  border-radius: 0 0 12px 12px;
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

.ir-modal__btn--primary {
  background: var(--color-brand-solid);
  color: white;
  box-shadow: 0 2px 8px rgba(var(--color-brand-rgb), 0.3);
}

.ir-modal__btn--primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

@media (max-width: 640px) {
  .ir-modal__grid--3 {
    grid-template-columns: 1fr;
  }
}
</style>
