<template>
  <div class="multi-ib-modal-overlay" @click.self="emit('close')">
    <div class="multi-ib-modal">
      <div class="multi-ib-modal__header">
        <div>
          <h2 class="multi-ib-modal__title">{{ t("createMultiIb_title") }}</h2>
          <p class="multi-ib-modal__subtitle">
            {{ t("createMultiIb_subtitle") }}
          </p>
        </div>
        <button
          type="button"
          class="multi-ib-modal__close"
          :aria-label="t('createMultiIb_ariaClose')"
          @click="emit('close')"
        >
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="multi-ib-modal__body">
        <div class="multi-ib-form__group">
          <label class="multi-ib-form__label multi-ib-form__label--required">{{
            t("createMultiIb_label_selectIb")
          }}</label>
          <div class="multi-ib-select-search">
            <div class="multi-ib-select-search__input-wrap">
              <i class="fas fa-search multi-ib-select-search__icon"></i>
              <input
                ref="ibInputRef"
                type="text"
                class="multi-ib-select-search__input"
                :value="ibInputDisplay"
                :placeholder="ibSelectPlaceholder"
                :readonly="!!selectedIb"
                autocomplete="off"
                @input="onIbInput"
                @focus="showIbDropdown = true"
                @blur="onIbBlur"
              />
              <button
                v-if="selectedIb"
                type="button"
                class="multi-ib-select-search__clear"
                :aria-label="t('createMultiIb_ariaClearSelection')"
                @click.prevent="clearIb"
              >
                <i class="fas fa-times"></i>
              </button>
            </div>
            <div
              v-show="showIbDropdown"
              class="multi-ib-select-search__dropdown"
              @mousedown.prevent
            >
              <div
                v-if="loading && ibOptions.length === 0"
                class="multi-ib-select-search__loading"
              >
                <i class="fas fa-spinner fa-spin"></i>
                <span>{{ t("createMultiIb_loading") }}</span>
              </div>
              <div
                v-else-if="!loading && ibOptions.length === 0"
                class="multi-ib-select-search__empty"
              >
                {{
                  searchKeyword
                    ? t("createMultiIb_emptySearch")
                    : t("createMultiIb_empty")
                }}
              </div>
              <div
                v-else
                class="multi-ib-select-search__list"
                @scroll="handleScroll"
              >
                <div
                  v-for="ib in ibOptions"
                  :key="ib.id"
                  class="multi-ib-option"
                  :class="{
                    'multi-ib-option--selected': selectedIbId === ib.id,
                  }"
                  @mousedown.prevent="selectIb(ib)"
                >
                  <span class="multi-ib-option__avatar">{{
                    getIbInitials(ib)
                  }}</span>
                  <div class="multi-ib-option__info">
                    <span class="multi-ib-option__name">{{
                      getIbName(ib)
                    }}</span>
                    <span
                      v-if="getIbSubtitle(ib)"
                      class="multi-ib-option__email"
                      >{{ getIbSubtitle(ib) }}</span
                    >
                  </div>
                  <i
                    v-if="selectedIbId === ib.id"
                    class="fas fa-check multi-ib-option__check"
                  ></i>
                </div>
                <div
                  v-if="loadingMore"
                  class="multi-ib-select-search__loading-more"
                >
                  <i class="fas fa-spinner fa-spin"></i>
                  <span>{{ t("createMultiIb_loadingMore") }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="multi-ib-modal__footer">
        <button
          type="button"
          class="multi-ib-btn multi-ib-btn--cancel"
          :disabled="sending"
          @click="emit('close')"
        >
          {{ t("createMultiIb_btnCancel") }}
        </button>
        <button
          type="button"
          class="multi-ib-btn multi-ib-btn--primary"
          :disabled="!selectedIbId || sending"
          @click="submit"
        >
          <i
            class="fas"
            :class="sending ? 'fa-spinner fa-spin' : 'fa-plus'"
          ></i>
          {{
            sending
              ? t("createMultiIb_btnCreating")
              : t("createMultiIb_btnCreate")
          }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from "vue";
import ibPartnersApi from "@/services/ibPartnersApi";
import ibApplicationsApi from "@/services/ibApplicationsApi";
import { useAdminI18n } from "@/composables/useAdminI18n";

const emit = defineEmits(["close", "created"]);
const { t } = useAdminI18n();

const loading = ref(false);
const loadingMore = ref(false);
const sending = ref(false);
const searchKeyword = ref("");
const selectedIbId = ref(null);
const selectedIb = ref(null);
const ibList = ref([]);
const showIbDropdown = ref(false);
const ibInputRef = ref(null);
const currentPage = ref(1);
const hasMore = ref(true);
const perPage = ref(50);
const searchTimeout = ref(null);

const ibOptions = computed(() =>
  ibList.value.filter((ib) => ib.status === "approved"),
);
const ibInputDisplay = computed(() =>
  selectedIb.value ? getIbName(selectedIb.value) : searchKeyword.value,
);
const ibSelectPlaceholder = computed(() =>
  selectedIb.value ? "" : t("createMultiIb_searchPlaceholder"),
);

const loadIbList = async (page = 1, append = false) => {
  try {
    if (page === 1) {
      loading.value = true;
    } else {
      loadingMore.value = true;
    }

    const response = await ibPartnersApi.getAllStatusList({
      page,
      per_page: perPage.value,
      search: searchKeyword.value.trim(),
    });

    const data = response?.data;
    const items = Array.isArray(data?.items)
      ? data.items
      : Array.isArray(data)
        ? data
        : [];
    ibList.value = append ? [...ibList.value, ...items] : items;

    const pagination = data?.pagination;
    currentPage.value = Number(pagination?.page ?? page);
    hasMore.value = Boolean(
      pagination?.has_more ?? items.length >= perPage.value,
    );
  } catch (error) {
    console.error("Failed to load IB list:", error);
    alert(
      error?.message ||
        error?.response?.data?.message ||
        t("createMultiIb_alert_loadIbFailed"),
    );
  } finally {
    loading.value = false;
    loadingMore.value = false;
  }
};

watch(searchKeyword, () => {
  if (searchTimeout.value) {
    clearTimeout(searchTimeout.value);
  }

  searchTimeout.value = setTimeout(() => {
    currentPage.value = 1;
    hasMore.value = true;
    loadIbList(1, false);
  }, 300);
});

const handleScroll = (event) => {
  const container = event.target;
  const scrollBottom =
    container.scrollHeight - container.scrollTop - container.clientHeight;
  if (scrollBottom < 50 && hasMore.value && !loadingMore.value) {
    loadIbList(currentPage.value + 1, true);
  }
};

const onIbInput = (event) => {
  if (selectedIb.value) {
    clearIb();
  }
  searchKeyword.value = event.target.value;
};

const onIbBlur = () => {
  setTimeout(() => {
    showIbDropdown.value = false;
  }, 200);
};

const selectIb = (ib) => {
  selectedIbId.value = ib.id;
  selectedIb.value = ib;
  searchKeyword.value = "";
  showIbDropdown.value = false;
};

const clearIb = () => {
  selectedIbId.value = null;
  selectedIb.value = null;
  searchKeyword.value = "";
  showIbDropdown.value = true;
  nextTick(() => ibInputRef.value?.focus());
};

const getIbName = (ib) => {
  // 后台展示优先用 adminAlias，便于运营按业务上熟悉的称呼识别 IB
  const alias = (ib.adminAlias || "").trim();
  if (alias) return alias;
  return ib.ibCode || `IB #${ib.id}`;
};

const getIbSubtitle = (ib) => {
  // 设置了 adminAlias 时，在副标题里同时展示 ibCode 方便对照
  const alias = (ib.adminAlias || "").trim();
  return alias && ib.ibCode ? ib.ibCode : "";
};

const getIbInitials = (ib) => {
  const name = getIbName(ib);
  return (
    name
      .split(/\s+/)
      .filter(Boolean)
      .slice(0, 2)
      .map((part) => part.charAt(0).toUpperCase())
      .join("") || "IB"
  );
};

const submit = async () => {
  if (!selectedIbId.value) {
    alert(t("createMultiIb_alert_selectIb"));
    return;
  }

  try {
    sending.value = true;
    const response = await ibApplicationsApi.createMultipleIbFromExistingIb(
      selectedIbId.value,
    );
    emit("created", response?.data ?? response);
  } catch (error) {
    console.error("Failed to create multi IB:", error);
    alert(
      error?.message ||
        error?.response?.data?.message ||
        t("createMultiIb_alert_createFailed"),
    );
  } finally {
    sending.value = false;
  }
};

onMounted(loadIbList);
</script>

<style scoped>
.multi-ib-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  z-index: 9998;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
}

.multi-ib-modal {
  width: min(560px, 100%);
  background: var(--color-surface);
  border-radius: var(--radius-xl);
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  overflow: hidden;
}

.multi-ib-modal__header {
  padding: 20px 24px;
  border-bottom: 2px solid var(--color-border);
  background: var(--color-brand-solid);
  color: #ffffff;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.multi-ib-modal__title {
  margin: 0;
  font-size: 22px;
  font-weight: 700;
}

.multi-ib-modal__subtitle {
  margin: 6px 0 0;
  font-size: 14px;
  opacity: 0.9;
  line-height: 1.4;
}

.multi-ib-modal__close {
  width: 34px;
  height: 34px;
  border: 0;
  border-radius: var(--radius-md);
  background: rgba(255, 255, 255, 0.2);
  color: #ffffff;
  cursor: pointer;
}

.multi-ib-modal__body {
  padding: 24px;
  min-height: 340px;
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.multi-ib-form__group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.multi-ib-form__label {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
}

.multi-ib-form__label--required::after {
  content: " *";
  color: var(--color-danger);
}

.multi-ib-modal__footer {
  padding: 18px 24px;
  border-top: 1px solid var(--color-border);
  background: var(--color-surface-soft);
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

.multi-ib-btn {
  border: 0;
  border-radius: var(--radius-md);
  padding: 10px 16px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.multi-ib-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.multi-ib-btn--primary {
  background: var(--color-brand-solid);
  color: #ffffff;
}

.multi-ib-btn--secondary {
  background: var(--color-surface-muted);
  color: var(--color-ink);
}

.multi-ib-btn--cancel {
  background: var(--color-border);
  color: var(--color-text);
}

.multi-ib-select-search {
  position: relative;
}

.multi-ib-select-search__input-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.multi-ib-select-search__icon {
  position: absolute;
  left: 14px;
  color: var(--color-faint);
  font-size: 14px;
  pointer-events: none;
}

.multi-ib-select-search__input {
  width: 100%;
  padding: 10px 40px 10px 40px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  color: var(--color-ink);
  transition:
    border-color 0.2s,
    box-shadow 0.2s;
}

.multi-ib-select-search__input:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.multi-ib-select-search__input::placeholder {
  color: var(--color-faint);
}

.multi-ib-select-search__clear {
  position: absolute;
  right: 10px;
  width: 28px;
  height: 28px;
  border: none;
  background: var(--color-border);
  color: var(--color-text);
  border-radius: var(--radius-sm);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  transition:
    background 0.2s,
    color 0.2s;
}

.multi-ib-select-search__clear:hover {
  background: var(--color-border-strong);
  color: var(--color-ink);
}

.multi-ib-select-search__dropdown {
  position: absolute;
  left: 0;
  right: 0;
  top: 100%;
  margin-top: 4px;
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
  z-index: 10;
  max-height: 280px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.multi-ib-select-search__loading,
.multi-ib-select-search__empty {
  padding: 20px;
  text-align: center;
  font-size: 14px;
  color: var(--color-muted);
}

.multi-ib-select-search__loading i,
.multi-ib-select-search__loading-more i {
  margin-right: 8px;
}

.multi-ib-select-search__list {
  overflow-y: auto;
  max-height: 260px;
}

.multi-ib-select-search__list::-webkit-scrollbar {
  width: 6px;
}

.multi-ib-select-search__list::-webkit-scrollbar-thumb {
  background: var(--color-border-strong);
  border-radius: 3px;
}

.multi-ib-select-search__loading-more {
  padding: 12px;
  text-align: center;
  font-size: 14px;
  color: var(--color-brand);
  border-top: 1px solid var(--color-border);
}

.multi-ib-option {
  padding: 12px 14px;
  display: flex;
  align-items: center;
  gap: 12px;
  cursor: pointer;
  transition: background 0.2s;
  border-bottom: 1px solid #f1f1f1;
}

.multi-ib-option:last-child {
  border-bottom: none;
}

.multi-ib-option:hover {
  background: var(--color-surface-soft);
}

.multi-ib-option--selected {
  background: var(--color-brand-soft);
}

.multi-ib-option__avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: var(--color-brand-solid);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: 700;
  flex-shrink: 0;
}

.multi-ib-option__info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.multi-ib-option__name {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.multi-ib-option__email {
  font-size: 14px;
  color: var(--color-muted);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.multi-ib-option__check {
  color: var(--color-brand);
  font-size: 14px;
}

@media (max-width: 640px) {
  .multi-ib-modal__footer {
    flex-direction: column;
  }

  .multi-ib-btn {
    width: 100%;
  }
}
</style>
