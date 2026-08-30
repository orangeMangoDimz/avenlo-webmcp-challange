<template>
  <div class="webmcp-page">
    <div class="webmcp-page-header">
      <div>
        <p class="webmcp-kicker">{{ t("webmcp_kicker", "Developer tools") }}</p>
        <h1>
          <i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i>
          {{ t("webmcp_overview_title", "WebMCP") }}
        </h1>
        <p class="webmcp-subtitle">
          {{
            t(
              "webmcp_overview_subtitle",
              "Control the browser tools available to your workspace.",
            )
          }}
        </p>
      </div>
      <PageHeaderActions />
    </div>

    <div class="webmcp-overview-grid">
      <section class="webmcp-panel webmcp-control-panel">
        <div class="webmcp-panel-heading">
          <div>
            <p class="webmcp-eyebrow">
              {{ t("webmcp_runtime_eyebrow", "Runtime access") }}
            </p>
            <h2>{{ t("webmcp_runtime_title", "Browser tools") }}</h2>
            <p>
              {{
                t(
                  "webmcp_runtime_description",
                  "When enabled, this admin session exposes approved client tools through the browser's Model Context API.",
                )
              }}
            </p>
          </div>
          <span class="webmcp-status" :class="statusClass">
            <span class="webmcp-status-dot"></span>
            {{ statusLabel }}
          </span>
        </div>

        <div class="webmcp-toggle-row">
          <div>
            <h3>
              {{ t("webmcp_global_toggle_title", "Enable WebMCP tools") }}
            </h3>
            <p>
              {{
                t(
                  "webmcp_global_toggle_description",
                  "Applies immediately to this browser. Turning it off removes the tools from the current page.",
                )
              }}
            </p>
          </div>
          <label class="webmcp-toggle" :aria-label="toggleLabel">
            <input
              v-model="enabled"
              type="checkbox"
              :aria-label="toggleLabel"
              @change="handleToggle"
            />
            <span class="webmcp-toggle-track"></span>
          </label>
        </div>

        <div class="webmcp-metrics">
          <div class="webmcp-metric">
            <span>{{ t("webmcp_metric_tools", "Tools in catalog") }}</span>
            <strong>{{ WEBMCP_TOOL_CATALOG.length }}</strong>
          </div>
          <div class="webmcp-metric">
            <span>{{ t("webmcp_metric_runtime", "Runtime support") }}</span>
            <strong :class="{ 'is-muted': !modelContextSupported }">
              {{
                modelContextSupported
                  ? t("webmcp_detected", "Detected")
                  : t("webmcp_not_detected", "Not detected")
              }}
            </strong>
          </div>
          <div class="webmcp-metric">
            <span>{{ t("webmcp_metric_scope", "Setting scope") }}</span>
            <strong>{{ t("webmcp_scope_browser", "This browser") }}</strong>
          </div>
        </div>
      </section>

      <aside class="webmcp-panel webmcp-note-panel">
        <div class="webmcp-note-icon">
          <i class="fas fa-shield-halved" aria-hidden="true"></i>
        </div>
        <p class="webmcp-eyebrow">
          {{ t("webmcp_safety_eyebrow", "Access boundary") }}
        </p>
        <h2>{{ t("webmcp_safety_title", "Permission-aware by design") }}</h2>
        <p>
          {{
            t(
              "webmcp_safety_description",
              "Every tool checks the signed-in administrator and the same page permissions used by the admin portal before it reads client data.",
            )
          }}
        </p>
        <router-link class="webmcp-text-link" to="/webmcp/tools">
          {{ t("webmcp_view_catalog", "View the tool catalog") }}
          <i class="fas fa-arrow-right" aria-hidden="true"></i>
        </router-link>
      </aside>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from "vue";
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";
import { WEBMCP_TOOL_CATALOG } from "@/services/adminWebMcpCatalog";
import {
  isWebMcpEnabled,
  setWebMcpEnabled,
  subscribeWebMcpEnabled,
} from "@/services/adminWebMcpSettings";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t } = useAdminI18n();
const enabled = ref(isWebMcpEnabled());
const modelContextSupported = ref(false);
let unsubscribeWebMcpSetting = () => {};

const statusLabel = computed(() => {
  if (!enabled.value) return t("webmcp_status_disabled", "Disabled");
  if (!modelContextSupported.value) {
    return t("webmcp_status_not_detected", "Runtime not detected");
  }
  return t("webmcp_status_enabled", "Enabled");
});

const statusClass = computed(() => ({
  "is-enabled": enabled.value && modelContextSupported.value,
  "is-disabled": !enabled.value,
  "is-unavailable": enabled.value && !modelContextSupported.value,
}));

const toggleLabel = computed(() =>
  enabled.value
    ? t("webmcp_toggle_disable", "Disable WebMCP tools")
    : t("webmcp_toggle_enable", "Enable WebMCP tools"),
);

const handleToggle = (event) => {
  enabled.value = setWebMcpEnabled(event.target.checked);
};

onMounted(() => {
  modelContextSupported.value =
    typeof document !== "undefined" &&
    typeof document.modelContext?.registerTool === "function";
  unsubscribeWebMcpSetting = subscribeWebMcpEnabled((value) => {
    enabled.value = value;
  });
});

onUnmounted(() => {
  unsubscribeWebMcpSetting();
});
</script>

<style scoped>
.webmcp-page {
  max-width: 1180px;
  margin: 0 auto;
  padding: 10px 2px 34px;
}

.webmcp-page-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 24px;
  padding: 18px 0 22px;
  border-bottom: 1px solid var(--color-border);
}

.webmcp-kicker,
.webmcp-eyebrow {
  margin: 0 0 8px;
  color: var(--color-brand);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.webmcp-page-header h1 {
  display: flex;
  align-items: center;
  gap: 11px;
  margin: 0;
  color: var(--color-ink);
  font-size: clamp(24px, 3vw, 32px);
  letter-spacing: -0.03em;
}

.webmcp-page-header h1 i {
  color: var(--color-brand);
  font-size: 25px;
}

.webmcp-subtitle {
  max-width: 560px;
  margin: 7px 0 0;
  color: var(--color-muted);
  font-size: 13px;
}

.webmcp-overview-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.65fr) minmax(260px, 0.85fr);
  gap: 18px;
}

.webmcp-panel {
  min-width: 0;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 9px;
  box-shadow: 0 10px 28px rgba(15, 23, 42, 0.05);
}

.webmcp-control-panel {
  overflow: hidden;
}

.webmcp-panel-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20px;
  padding: 24px 26px 22px;
  background: linear-gradient(
    135deg,
    var(--color-surface-soft),
    var(--color-surface)
  );
  border-bottom: 1px solid var(--color-border);
}

.webmcp-panel h2 {
  margin: 0;
  color: var(--color-ink);
  font-size: 19px;
  letter-spacing: -0.02em;
}

.webmcp-panel-heading p:not(.webmcp-eyebrow),
.webmcp-note-panel > p:not(.webmcp-eyebrow) {
  max-width: 620px;
  margin: 7px 0 0;
  color: var(--color-muted);
  font-size: 12px;
  line-height: 1.6;
}

.webmcp-status {
  display: inline-flex;
  align-items: center;
  flex: 0 0 auto;
  gap: 7px;
  padding: 6px 9px;
  color: var(--color-muted);
  background: var(--color-surface-muted);
  border: 1px solid var(--color-border);
  border-radius: 999px;
  font-size: 10px;
  font-weight: 750;
  white-space: nowrap;
}

.webmcp-status-dot {
  width: 7px;
  height: 7px;
  background: currentColor;
  border-radius: 50%;
}

.webmcp-status.is-enabled {
  color: var(--color-success);
  background: var(--color-success-soft);
  border-color: var(--color-success-border);
}

.webmcp-status.is-unavailable {
  color: var(--color-warning);
  background: var(--color-warning-soft);
  border-color: var(--color-warning-border);
}

.webmcp-toggle-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  margin: 20px 26px;
  padding: 18px;
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  border-left: 3px solid var(--color-brand);
  border-radius: 6px;
}

.webmcp-toggle-row h3 {
  margin: 0;
  color: var(--color-ink);
  font-size: 14px;
}

.webmcp-toggle-row p {
  max-width: 600px;
  margin: 5px 0 0;
  color: var(--color-muted);
  font-size: 12px;
  line-height: 1.5;
}

.webmcp-toggle {
  position: relative;
  display: inline-block;
  width: 54px;
  height: 30px;
  flex: 0 0 auto;
}

.webmcp-toggle input {
  position: absolute;
  width: 1px;
  height: 1px;
  opacity: 0;
}

.webmcp-toggle-track {
  position: absolute;
  inset: 0;
  cursor: pointer;
  background: var(--color-border-strong);
  border-radius: 999px;
  transition: background 160ms ease;
}

.webmcp-toggle-track::after {
  position: absolute;
  top: 4px;
  left: 4px;
  width: 22px;
  height: 22px;
  content: "";
  background: var(--color-surface);
  border-radius: 50%;
  box-shadow: 0 2px 5px rgba(15, 23, 42, 0.2);
  transition: transform 160ms ease;
}

.webmcp-toggle input:checked + .webmcp-toggle-track {
  background: var(--color-brand-solid);
}

.webmcp-toggle input:checked + .webmcp-toggle-track::after {
  transform: translateX(24px);
}

.webmcp-toggle input:focus-visible + .webmcp-toggle-track {
  outline: 2px solid var(--color-brand);
  outline-offset: 3px;
}

.webmcp-metrics {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1px;
  background: var(--color-border);
  border-top: 1px solid var(--color-border);
}

.webmcp-metric {
  display: grid;
  gap: 7px;
  min-width: 0;
  padding: 17px 20px;
  background: var(--color-surface);
}

.webmcp-metric span {
  color: var(--color-muted);
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.webmcp-metric strong {
  overflow: hidden;
  color: var(--color-ink);
  font-size: 18px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.webmcp-metric strong.is-muted {
  color: var(--color-muted);
  font-size: 14px;
}

.webmcp-note-panel {
  align-self: start;
  padding: 24px;
  border-top: 3px solid var(--color-brand);
}

.webmcp-note-icon {
  display: grid;
  width: 38px;
  height: 38px;
  margin-bottom: 22px;
  place-items: center;
  color: var(--color-brand);
  background: var(--color-brand-soft);
  border-radius: 7px;
  font-size: 17px;
}

.webmcp-note-panel h2 {
  max-width: 240px;
}

.webmcp-text-link {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin-top: 24px;
  color: var(--color-brand);
  font-size: 12px;
  font-weight: 750;
  text-decoration: none;
}

.webmcp-text-link:hover {
  color: var(--color-brand-strong);
}

@media (max-width: 760px) {
  .webmcp-page-header,
  .webmcp-panel-heading,
  .webmcp-toggle-row {
    align-items: flex-start;
    flex-direction: column;
  }

  .webmcp-overview-grid {
    grid-template-columns: 1fr;
  }

  .webmcp-metrics {
    grid-template-columns: 1fr;
  }

  .webmcp-metric {
    grid-template-columns: 1fr auto;
    align-items: center;
  }
}
</style>
