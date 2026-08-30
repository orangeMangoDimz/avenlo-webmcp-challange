<template>
  <div class="container">
    <div class="page-header">
      <div class="page-title">
        <h1>
          <i class="fas fa-cog"></i> {{ t("page_generalSettings_title") }}
        </h1>
        <p>{{ t("page_generalSettings_sub") }}</p>
      </div>
      <div class="page-actions">
        <PageHeaderActions />
      </div>
    </div>

    <div class="placeholder-content">
      <i
        class="fas fa-cog"
        style="
          font-size: 64px;
          color: var(--color-border-strong);
          margin-bottom: 20px;
        "
      ></i>
      <h2 style="color: var(--color-ink); margin-bottom: 10px">
        {{ t("page_generalSettings_placeholderTitle") }}
      </h2>
      <p style="color: var(--color-muted); margin-bottom: 30px">
        {{ t("page_generalSettings_placeholderSub") }}
      </p>
      <router-link to="/accounts" class="btn btn-primary">
        <i class="fas fa-arrow-left"></i>
        {{ t("page_generalSettings_backToAccounts") }}
      </router-link>
    </div>
  </div>
</template>

<script setup>
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";

import { useAdminI18n } from "@/composables/useAdminI18n";

const { t } = useAdminI18n();
</script>

<style scoped>
.container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 40px 20px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 2px solid var(--color-border);
}

.page-title h1 {
  font-size: 28px;
  color: var(--color-ink);
  margin-bottom: 5px;
}

.page-title h1 i {
  margin-right: 10px;
  color: var(--color-brand);
}

.page-title p {
  font-size: 14px;
  color: var(--color-muted);
}

.page-actions {
  display: flex;
  align-items: center;
  gap: 20px;
}

.placeholder-content {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: 80px 40px;
  text-align: center;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.btn {
  padding: 12px 24px;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  text-decoration: none;
}

.btn-primary {
  background: var(--color-brand-solid);
  color: white;
  box-shadow: 0 4px 15px rgba(var(--color-brand-rgb), 0.4);
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(var(--color-brand-rgb), 0.5);
}
</style>
