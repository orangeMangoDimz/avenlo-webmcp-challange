<template>
  <div class="ir-modal-overlay" @click.self="$emit('close')">
    <div class="ir-modal">
      <div class="ir-modal__header">
        <h2 class="ir-modal__title">{{ t("ibUnbindModal_title") }}</h2>
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
        <section class="ir-modal__section ir-modal__section--info">
          <div class="ir-modal__grid ir-modal__grid--2">
            <div class="ir-modal__field">
              <span class="ir-modal__label">{{
                t("ibUnbindModal_label_ibName")
              }}</span>
              <span class="ir-modal__value">{{
                row.ibName || row.companyName || "—"
              }}</span>
            </div>
            <div class="ir-modal__field">
              <span class="ir-modal__label">{{
                t("ibUnbindModal_label_email")
              }}</span>
              <span class="ir-modal__value">{{ row.email || "—" }}</span>
            </div>
          </div>
        </section>

        <section class="ir-modal__section ir-modal__section--edit">
          <div class="ir-modal__edit-card ir-modal__search-row">
            <input
              v-model="searchKeyword"
              type="text"
              class="ir-modal__input"
              :placeholder="t('ibUnbindModal_searchPlaceholder')"
              @keyup.enter="applySearch"
            />
            <button
              type="button"
              class="ir-modal__btn ir-modal__btn--search"
              @click="applySearch"
            >
              <i class="fas fa-search"></i> {{ t("ibUnbindModal_btnSearch") }}
            </button>
          </div>
        </section>

        <section class="ir-modal__section ir-modal__section--edit">
          <p class="ir-modal__select-label">
            {{ t("ibBindModal_selectClient") }}
            <span class="ir-modal__select-count">{{
              tParams("ibBindModal_selectedCount", "{n} selected", {
                n: selectedIds.length,
              })
            }}</span>
          </p>
          <div class="ir-modal__list-wrap">
            <p v-if="loading && boundList.length === 0" class="ir-modal__hint">
              <i class="fas fa-spinner fa-spin"></i>
              {{ t("ibUnbindModal_loading") }}
            </p>
            <p
              v-else-if="!loading && filteredList.length === 0"
              class="ir-modal__hint"
            >
              {{ t("ibUnbindModal_empty") }}
            </p>
            <ul v-else class="ir-modal__list">
              <li
                v-for="item in filteredList"
                :key="(item.itemType || 'ib') + '-' + item.id"
                class="ir-modal__list-item"
              >
                <label class="ir-modal__checkbox-label">
                  <input
                    v-model="selectedIds"
                    type="checkbox"
                    :value="(item.itemType || 'ib') + '-' + item.id"
                    class="ir-modal__checkbox"
                  />
                  <span class="ir-modal__list-item-name">{{
                    item.ibName || item.companyName || "—"
                  }}</span>
                  <span class="ir-modal__list-item-email">{{
                    item.email || "—"
                  }}</span>
                  <span class="ir-modal__list-item-tier">{{
                    item.itemType === "client"
                      ? t("ibBindModal_typeClient")
                      : tParams("ibBindModal_typeIbTier", "IB Tier {n}", {
                          n: item.tierLevel ?? "—",
                        })
                  }}</span>
                  <span class="ir-modal__list-item-hint">{{
                    item.itemType === "ib" && (item.boundChildCount || 0) > 0
                      ? t("ibUnbindModal_subLevelHint")
                      : "\u00A0"
                  }}</span>
                </label>
              </li>
            </ul>
          </div>
        </section>
      </div>

      <div v-if="errorMessage" class="ir-modal__error">
        <i class="fas fa-exclamation-circle"></i>
        {{ errorMessage }}
      </div>

      <div class="ir-modal__footer">
        <button
          type="button"
          class="ir-modal__btn ir-modal__btn--secondary"
          @click="$emit('close')"
        >
          {{ t("ibBindModal_btn_cancel") }}
        </button>
        <button
          type="button"
          class="ir-modal__btn ir-modal__btn--primary"
          :disabled="saving || selectedIds.length === 0"
          @click="onConfirm"
        >
          <i v-if="saving" class="fas fa-spinner fa-spin"></i>
          {{ t("ibBindModal_btn_confirm") }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import ibPartnersApi from "@/services/ibPartnersApi";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t, tParams } = useAdminI18n();

const props = defineProps({
  row: { type: Object, required: true },
});

const emit = defineEmits(["close", "success"]);

const searchKeyword = ref("");
const appliedKeyword = ref("");
const boundList = ref([]);
const loading = ref(false);
const selectedIds = ref([]);
const saving = ref(false);
const errorMessage = ref("");

const filteredList = computed(() => {
  const kw = appliedKeyword.value.trim().toLowerCase();
  if (!kw) return boundList.value;
  return boundList.value.filter((item) => {
    const name = (item.ibName || item.companyName || "").toLowerCase();
    const email = (item.email || "").toLowerCase();
    return name.includes(kw) || email.includes(kw);
  });
});

const loadBoundChildren = async () => {
  errorMessage.value = "";
  loading.value = true;
  try {
    const res = await ibPartnersApi.getBoundChildren(props.row.id);
    const payload = res?.data?.data ?? res?.data;
    boundList.value = payload?.items ?? [];
    selectedIds.value = [];
    appliedKeyword.value = "";
  } catch (err) {
    boundList.value = [];
    const raw = err.response?.data?.message || err.message || "";
    errorMessage.value = raw || t("ibUnbindModal_loadFail");
  } finally {
    loading.value = false;
  }
};

const applySearch = () => {
  appliedKeyword.value = searchKeyword.value.trim();
};

onMounted(() => {
  loadBoundChildren();
});

const onConfirm = async () => {
  if (selectedIds.value.length === 0) return;
  errorMessage.value = "";
  // selectedIds 里存的是 `${itemType}-${id}` 复合 key，避免 IB 和 client 数字 id 撞号时被错分类
  const childIds = selectedIds.value
    .filter((k) => k.startsWith("ib-"))
    .map((k) => Number(k.slice(3)));
  const clientIds = selectedIds.value
    .filter((k) => k.startsWith("client-"))
    .map((k) => Number(k.slice(7)));
  const idToCount = Object.fromEntries(
    boundList.value
      .filter((i) => i.itemType === "ib")
      .map((item) => [item.id, item.boundChildCount || 0]),
  );
  const hasSubLevel = childIds.some((id) => (idToCount[id] || 0) > 0);
  if (hasSubLevel) {
    errorMessage.value = t("ibUnbindModal_errUnbindOrder");
    return;
  }
  saving.value = true;
  try {
    await ibPartnersApi.unbindIbPartners(props.row.id, { childIds, clientIds });
    emit("success");
    emit("close");
    alert(t("ibUnbindModal_alert_unbindOk"));
  } catch (err) {
    const raw = err.response?.data?.message || err.message || "";
    errorMessage.value = raw || t("ibUnbindModal_alert_unbindFail");
  } finally {
    saving.value = false;
  }
};
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
  max-width: 640px;
  width: 95%;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.ir-modal__header {
  padding: 20px 24px;
  border-bottom: 2px solid rgba(255, 255, 255, 0.2);
  background: linear-gradient(135deg, var(--color-danger) 0%, #9b2c2c 100%);
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
  padding: 20px 24px;
  background: var(--color-surface);
}

.ir-modal__grid {
  display: grid;
  gap: 0;
}

.ir-modal__grid--2 {
  grid-template-columns: repeat(2, 1fr);
}

.ir-modal__section--info .ir-modal__field {
  padding: 10px 14px;
  border-bottom: 1px solid #f0f4f8;
  border-right: 1px solid #f0f4f8;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.ir-modal__section--info .ir-modal__field:nth-child(2n) {
  border-right: none;
}

.ir-modal__section--info .ir-modal__field:nth-last-child(-n + 2) {
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

.ir-modal__edit-card {
  padding: 20px 24px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.ir-modal__search-row {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.ir-modal__input {
  flex: 1;
  min-width: 200px;
  padding: 10px 14px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
}

.ir-modal__input:focus {
  outline: none;
  border-color: var(--color-danger);
}

.ir-modal__btn--search {
  padding: 10px 18px;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  background: var(--color-danger-solid);
  color: white;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.ir-modal__btn--search:hover {
  background: #9b2c2c;
}

.ir-modal__select-label {
  margin: 0 0 12px 0;
  font-size: 14px;
  color: var(--color-text);
  display: flex;
  align-items: center;
}

.ir-modal__select-count {
  margin-left: 8px;
  padding: 2px 8px;
  background: linear-gradient(135deg, var(--color-danger) 0%, #9b2c2c 100%);
  color: white;
  border-radius: var(--radius-md);
  font-size: 11px;
  font-weight: 600;
}

.ir-modal__list-wrap {
  max-height: 280px;
  overflow-y: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 8px 0;
}

.ir-modal__hint {
  margin: 16px;
  font-size: 14px;
  color: var(--color-muted);
}

.ir-modal__list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.ir-modal__list-item {
  border-bottom: 1px solid #f0f4f8;
}

.ir-modal__list-item:last-child {
  border-bottom: none;
}

.ir-modal__checkbox-label {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  cursor: pointer;
  font-size: 14px;
}

.ir-modal__checkbox-label:hover {
  background: var(--color-surface-soft);
}

.ir-modal__checkbox {
  width: 18px;
  height: 18px;
  cursor: pointer;
}

.ir-modal__checkbox:disabled {
  cursor: not-allowed;
}

.ir-modal__list-item-name {
  flex: 0 0 140px;
  font-weight: 600;
  color: var(--color-ink);
}

.ir-modal__list-item-email {
  flex: 1;
  color: var(--color-text);
  min-width: 0;
}

.ir-modal__list-item-tier {
  flex: 0 0 104px;
  font-size: 13px;
  color: var(--color-muted);
  text-align: right;
}

.ir-modal__list-item-hint {
  flex: 0 0 110px;
  min-width: 110px;
  font-size: 12px;
  color: var(--color-danger);
}

.ir-modal__error {
  padding: 12px 24px;
  background: var(--color-danger-soft);
  color: var(--color-danger);
  font-size: 14px;
  display: flex;
  align-items: center;
  gap: 8px;
  border-top: 1px solid var(--color-danger-border);
}

.ir-modal__error i {
  flex-shrink: 0;
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
  background: linear-gradient(135deg, var(--color-danger) 0%, #9b2c2c 100%);
  color: white;
  box-shadow: 0 2px 8px rgba(197, 48, 48, 0.3);
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.ir-modal__btn--primary:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(197, 48, 48, 0.4);
}

.ir-modal__btn--primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}
</style>
