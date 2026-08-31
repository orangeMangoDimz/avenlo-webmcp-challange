<template>
  <nav class="developer-tools-tabs" aria-label="Developer tools navigation">
    <router-link
      v-for="tab in tabs"
      :key="tab.to"
      :to="tab.to"
      class="developer-tools-tab"
      exact-active-class="is-active"
    >
      <span>{{ t(tab.key, tab.label) }}</span>
    </router-link>
  </nav>
</template>

<script setup>
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t } = useAdminI18n();

const tabs = [
  {
    to: "/developer-settings",
    key: "nav_feature_controls",
    label: "Feature controls",
  },
  {
    to: "/webmcp/overview",
    key: "nav_webmcp_overview",
    label: "WebMCP overview",
  },
  {
    to: "/webmcp/tools",
    key: "nav_webmcp_tools",
    label: "Tool catalog",
  },
];
</script>

<style scoped>
.developer-tools-tabs {
  display: flex;
  max-width: 100%;
  align-items: stretch;
  gap: 8px;
  margin: 0 0 22px;
  padding: 0 0 4px;
  overflow-x: auto;
  border-bottom: 2px solid var(--color-border);
  scrollbar-width: thin;
}

.developer-tools-tab {
  display: inline-flex;
  min-height: 42px;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
  padding: 10px 18px;
  color: var(--color-muted);
  border-bottom: 3px solid transparent;
  margin-bottom: -2px;
  font-size: 14px;
  font-weight: 650;
  text-decoration: none;
  transition:
    color var(--transition-fast),
    background var(--transition-fast),
    border-color var(--transition-fast);
}

.developer-tools-tab:hover {
  color: var(--color-brand);
  background: var(--color-surface-soft);
}

.developer-tools-tab.is-active {
  color: var(--color-brand);
  border-bottom-color: var(--color-brand);
}

.developer-tools-tab:focus-visible {
  outline: 2px solid var(--color-brand);
  outline-offset: -2px;
}
</style>
