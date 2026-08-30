<template>
  <div class="ib-link-page">
    <div class="ib-link-header">
      <div class="ib-link-title">
        <h1>{{ t("page_ibLink_title", "IB Link") }}</h1>
        <p>
          {{ t("page_ibLink_sub", "IBs under you and their referral links") }}
        </p>
      </div>
      <div class="ib-link-actions">
        <PageHeaderActions />
      </div>
    </div>

    <div class="ib-link-toolbar">
      <input
        v-model="search"
        type="text"
        class="ib-link-search"
        :placeholder="
          t('salesList_search_ib_placeholder', 'Search IB by code / name')
        "
        @keyup.enter="loadIbs(true)"
      />
      <button type="button" class="ib-link-btn" @click="loadIbs(true)">
        <i class="fas fa-search"></i> {{ t("common_search", "Search") }}
      </button>
    </div>

    <div v-if="loading" class="ib-link-state">
      {{ t("common_loading", "Loading...") }}
    </div>
    <template v-else>
      <div class="ib-link-table-wrap">
        <table class="ib-link-table">
          <thead>
            <tr>
              <th>{{ t("salesList_th_ibCode", "IB Code") }}</th>
              <th>{{ t("salesList_th_ibName", "IB Name") }}</th>
              <th>{{ t("salesList_label_email", "Email") }}</th>
              <th>{{ t("salesList_th_country", "Country") }}</th>
              <th>{{ t("salesList_th_status", "Status") }}</th>
              <th>{{ t("ibLink_th_referralLink", "Referral Link") }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="ib in ibs" :key="ib.id">
              <td>{{ ib.ibCode || "—" }}</td>
              <td>{{ ib.ibName || "—" }}</td>
              <td>{{ ib.email || "—" }}</td>
              <td>{{ ib.country || "—" }}</td>
              <td>
                <span class="ib-link-badge" :class="ib.status">{{
                  ib.statusDisplay || ib.status || "—"
                }}</span>
              </td>
              <td>
                <div v-if="ib.referralUrl" class="ib-link-url">
                  <a
                    :href="ib.referralUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    :title="ib.referralUrl"
                    >{{ ib.referralUrl }}</a
                  >
                  <button
                    type="button"
                    class="ib-link-icon-btn"
                    :title="t('salesList_title_copyUrl', 'Copy')"
                    @click="copyUrl(ib)"
                  >
                    <i class="fas fa-copy"></i>
                  </button>
                </div>
                <span v-else>—</span>
              </td>
            </tr>
            <tr v-if="!ibs.length">
              <td colspan="6" class="ib-link-empty">
                {{ t("salesList_empty_noIbs", "No IBs") }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="(pagination.total ?? 0) > 0" class="ib-link-pagination">
        <span class="ib-link-pagination__info">{{
          tParams("salesList_pagination_pageOf", "Page {current} of {total}", {
            current: pagination.page ?? 1,
            total: pagination.total_pages ?? 1,
          })
        }}</span>
        <button
          type="button"
          class="ib-link-btn ib-link-btn--sm"
          :disabled="(pagination.page ?? 1) <= 1"
          @click="goToPage((pagination.page ?? 1) - 1)"
        >
          <i class="fas fa-chevron-left"></i>
          {{ t("ibIr_pagination_prev", "Prev") }}
        </button>
        <button
          type="button"
          class="ib-link-btn ib-link-btn--sm"
          :disabled="(pagination.page ?? 1) >= (pagination.total_pages ?? 1)"
          @click="goToPage((pagination.page ?? 1) + 1)"
        >
          {{ t("ibIr_pagination_next", "Next") }}
          <i class="fas fa-chevron-right"></i>
        </button>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import salesApi from "@/services/salesApi";
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { useAuthStore } from "@/stores/auth";

const { t, tParams } = useAdminI18n();
const authStore = useAuthStore();

const ibs = ref([]);
const pagination = ref({ total: 0, page: 1, per_page: 20, total_pages: 1 });
const loading = ref(false);
const search = ref("");
const page = ref(1);
const perPage = 20;

// 用当前登录账号 id 当 salesId，直接查 sales_bind 有没有对应的 IB（有则列出、没有则空）
const salesId = computed(() => authStore.user?.id ?? null);

async function loadIbs(resetPage = false) {
  const id = salesId.value;
  if (!id) {
    ibs.value = [];
    return;
  }
  if (resetPage) page.value = 1;
  loading.value = true;
  try {
    const { items, pagination: pag } = await salesApi.getBoundIbs(id, {
      page: page.value,
      per_page: perPage,
      search: (search.value || "").trim() || undefined,
    });
    ibs.value = items || [];
    pagination.value = pag || {
      total: 0,
      page: 1,
      per_page: perPage,
      total_pages: 1,
    };
  } catch (e) {
    ibs.value = [];
    pagination.value = { total: 0, page: 1, per_page: perPage, total_pages: 1 };
    console.error("Load bound IBs failed:", e);
  } finally {
    loading.value = false;
  }
}

function goToPage(p) {
  if (p < 1 || p > (pagination.value.total_pages ?? 1)) return;
  page.value = p;
  loadIbs();
}

async function copyUrl(ib) {
  const url = ib?.referralUrl;
  if (!url) return;
  try {
    await navigator.clipboard.writeText(url);
    alert(t("salesList_alert_urlCopied", "Copied"));
  } catch {
    alert(t("salesList_alert_copyFailed", "Copy failed"));
  }
}

onMounted(() => {
  loadIbs(true);
});
</script>

<style scoped>
.ib-link-page {
  max-width: 1600px;
  margin: 0 auto;
  padding: 30px 20px;
}
.ib-link-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 2px solid var(--color-border);
}
.ib-link-title h1 {
  font-size: 28px;
  color: var(--color-ink);
  margin: 0 0 5px 0;
}
.ib-link-title p {
  font-size: 14px;
  color: var(--color-muted);
  margin: 0;
}
.ib-link-actions {
  display: flex;
  align-items: center;
  gap: 20px;
}
.ib-link-toolbar {
  display: flex;
  gap: 8px;
  margin-bottom: 16px;
}
.ib-link-search {
  flex: 0 0 280px;
  max-width: 280px;
  padding: 8px 12px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
}
.ib-link-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border: 1px solid var(--color-brand);
  background: var(--color-brand-solid);
  color: #fff;
  border-radius: var(--radius-md);
  cursor: pointer;
  font-size: 13px;
}
.ib-link-btn:hover {
  background: var(--color-brand-strong);
}
.ib-link-btn--sm {
  padding: 5px 10px;
  font-size: 12px;
}
.ib-link-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.ib-link-state {
  padding: 40px;
  text-align: center;
  color: var(--color-muted);
}
.ib-link-table-wrap {
  overflow-x: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
}
.ib-link-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.ib-link-table th {
  text-align: left;
  padding: 12px 14px;
  background: var(--color-surface-soft);
  color: var(--color-text);
  font-weight: 600;
  border-bottom: 1px solid var(--color-border);
  white-space: nowrap;
}
.ib-link-table td {
  padding: 10px 14px;
  border-bottom: 1px solid #f1f5f9;
  color: var(--color-text);
  vertical-align: middle;
}
.ib-link-table tr:last-child td {
  border-bottom: none;
}
.ib-link-badge {
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 600;
  background: var(--color-brand-soft);
  color: var(--color-brand);
}
.ib-link-url {
  display: flex;
  align-items: center;
  gap: 8px;
}
.ib-link-url a {
  color: var(--color-info);
  text-decoration: none;
  max-width: 300px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.ib-link-url a:hover {
  text-decoration: underline;
}
.ib-link-icon-btn {
  border: 1px solid var(--color-border);
  background: var(--color-surface-soft);
  color: var(--color-text);
  border-radius: var(--radius-sm);
  padding: 4px 8px;
  cursor: pointer;
  flex-shrink: 0;
}
.ib-link-icon-btn:hover {
  background: var(--color-brand-soft);
  color: var(--color-brand);
}
.ib-link-empty {
  text-align: center;
  padding: 30px;
  color: var(--color-faint);
}
.ib-link-pagination {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-top: 16px;
  justify-content: flex-end;
  color: var(--color-muted);
  font-size: 13px;
}
</style>
