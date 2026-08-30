<template>
  <div class="webmcp-page">
    <div class="webmcp-page-header">
      <div>
        <p class="webmcp-kicker">{{ t("webmcp_kicker", "Developer tools") }}</p>
        <h1>
          <i class="fas fa-list-check" aria-hidden="true"></i>
          {{ t("webmcp_tools_title", "Tool catalog") }}
        </h1>
        <p class="webmcp-subtitle">
          {{
            t(
              "webmcp_tools_subtitle",
              "The read-only client tools exposed to the browser Model Context API.",
            )
          }}
        </p>
      </div>
      <PageHeaderActions />
    </div>

    <section class="webmcp-catalog-panel">
      <div class="webmcp-catalog-heading">
        <div>
          <p class="webmcp-eyebrow">
            {{ t("webmcp_catalog_eyebrow", "Registered surface") }}
          </p>
          <h2>{{ t("webmcp_catalog_title", "Available tools") }}</h2>
          <p>
            {{
              t(
                "webmcp_catalog_description",
                "Tools remain permission-aware and read-only. Expand a tool to inspect its purpose, inputs, and access boundary.",
              )
            }}
          </p>
        </div>
        <div class="webmcp-catalog-count" aria-label="Tool count">
          <strong>{{ WEBMCP_TOOL_CATALOG.length }}</strong>
          <span>{{ t("webmcp_catalog_count_label", "tools") }}</span>
        </div>
      </div>

      <div class="webmcp-section-list">
        <section
          v-for="section in groupedTools"
          :key="section.key"
          class="webmcp-section"
        >
          <button
            class="webmcp-section-toggle"
            type="button"
            :aria-expanded="isSectionExpanded(section.key)"
            @click="toggleSection(section.key)"
          >
            <span class="webmcp-section-leading">
              <span class="webmcp-section-icon">
                <i :class="['fas', section.icon]" aria-hidden="true"></i>
              </span>
              <span class="webmcp-section-copy">
                <span class="webmcp-eyebrow">{{
                  t("webmcp_section_label", "Section")
                }}</span>
                <strong>{{ section.title }}</strong>
                <small>{{ section.description }}</small>
              </span>
            </span>
            <span class="webmcp-section-meta">
              <span
                >{{ section.tools.length }}
                {{ t("webmcp_tools_label", "tools") }}</span
              >
              <i
                class="fas fa-chevron-down"
                :class="{ 'is-collapsed': !isSectionExpanded(section.key) }"
                aria-hidden="true"
              ></i>
            </span>
          </button>

          <div
            v-if="isSectionExpanded(section.key)"
            class="webmcp-section-tools"
          >
            <article
              v-for="tool in section.tools"
              :key="tool.name"
              class="webmcp-tool"
              :class="{ 'is-expanded': isToolExpanded(tool.name) }"
            >
              <button
                class="webmcp-tool-summary"
                type="button"
                :aria-expanded="isToolExpanded(tool.name)"
                :aria-controls="`webmcp-tool-details-${tool.name}`"
                @click="toggleTool(tool.name)"
              >
                <span class="webmcp-tool-icon">
                  <i :class="['fas', tool.icon]" aria-hidden="true"></i>
                </span>
                <span class="webmcp-tool-copy">
                  <span class="webmcp-tool-name-line">
                    <strong>{{ tool.name }}</strong>
                    <span class="webmcp-tool-badge">{{ tool.title }}</span>
                    <span
                      class="webmcp-operation-badge"
                      :class="`is-${tool.accessMode}`"
                    >
                      <i
                        :class="[
                          'fas',
                          tool.accessMode === 'write' ? 'fa-pen' : 'fa-eye',
                        ]"
                        aria-hidden="true"
                      ></i>
                      {{ accessModeLabel(tool) }}
                    </span>
                  </span>
                </span>
                <span class="webmcp-tool-status" :class="tool.statusClass">
                  <span class="webmcp-status-dot"></span>
                  {{ tool.statusLabel }}
                </span>
                <i
                  class="fas fa-chevron-down webmcp-tool-chevron"
                  :class="{ 'is-expanded': isToolExpanded(tool.name) }"
                  aria-hidden="true"
                ></i>
              </button>

              <div
                v-if="isToolExpanded(tool.name)"
                :id="`webmcp-tool-details-${tool.name}`"
                class="webmcp-tool-details"
              >
                <div class="webmcp-tool-detail-grid">
                  <div class="webmcp-tool-detail webmcp-tool-detail-wide">
                    <span>{{ t("webmcp_detail_purpose", "Purpose") }}</span>
                    <p>{{ tool.description }}</p>
                  </div>
                  <div class="webmcp-tool-detail webmcp-tool-detail-wide">
                    <span>{{
                      t("webmcp_detail_arguments", "Input arguments")
                    }}</span>
                    <div class="webmcp-arguments-table-wrap">
                      <table class="webmcp-arguments-table">
                        <thead>
                          <tr>
                            <th scope="col">
                              {{ t("webmcp_argument_name", "Argument") }}
                            </th>
                            <th scope="col">
                              {{ t("webmcp_argument_type", "Type") }}
                            </th>
                            <th scope="col">
                              {{ t("webmcp_argument_requirement", "Required") }}
                            </th>
                            <th scope="col">
                              {{
                                t("webmcp_argument_description", "Description")
                              }}
                            </th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr
                            v-for="field in tool.inputFields"
                            :key="field.name"
                          >
                            <td>
                              <code>{{ field.name }}</code>
                            </td>
                            <td>{{ field.type }}</td>
                            <td>
                              <span
                                class="webmcp-requirement"
                                :class="requirementClass(field.requirement)"
                              >
                                {{ field.requirement }}
                              </span>
                            </td>
                            <td>{{ field.description }}</td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                  <div class="webmcp-tool-detail">
                    <span>{{ t("webmcp_detail_input", "Request JSON") }}</span>
                    <p>{{ tool.inputSummary }}</p>
                    <pre><code>{{ formatJson(tool.inputExample) }}</code></pre>
                  </div>
                  <div class="webmcp-tool-detail">
                    <span>{{
                      t("webmcp_detail_output", "Response JSON")
                    }}</span>
                    <p>{{ tool.outputSummary }}</p>
                    <pre><code>{{ formatJson(tool.outputExample) }}</code></pre>
                  </div>
                  <div class="webmcp-tool-detail webmcp-tool-detail-wide">
                    <span>{{
                      t("webmcp_detail_access", "Permission status")
                    }}</span>
                    <p>
                      <strong
                        class="webmcp-inline-access"
                        :class="`is-${tool.accessMode}`"
                      >
                        {{ accessModeLabel(tool) }}
                      </strong>
                      {{ permissionLabel(tool) }}
                    </p>
                  </div>
                </div>
                <div class="webmcp-tool-detail-footer">
                  <span>
                    <i class="fas fa-shield-halved" aria-hidden="true"></i>
                    {{
                      t(
                        "webmcp_permission_checked",
                        "Permission checked at runtime",
                      )
                    }}
                  </span>
                  <span :class="tool.statusClass">
                    <span class="webmcp-status-dot"></span>
                    {{ tool.statusLabel }}
                  </span>
                </div>
              </div>
            </article>
          </div>
        </section>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from "vue";
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { useAuthStore } from "@/stores/auth";
import {
  WEBMCP_TOOL_CATALOG,
  groupWebMcpTools,
} from "@/services/adminWebMcpCatalog";
import {
  isWebMcpEnabled,
  subscribeWebMcpEnabled,
} from "@/services/adminWebMcpSettings";

const { t, tParams } = useAdminI18n();
const authStore = useAuthStore();
const enabled = ref(isWebMcpEnabled());
const modelContextSupported = ref(false);
const expandedSections = ref(["client"]);
const expandedTools = ref([]);
let unsubscribeWebMcpSetting = () => {};

const hasToolPermission = (tool) =>
  tool.permissionKeys.some((permissionKey) =>
    authStore.hasPermission(permissionKey),
  );

const getToolState = (tool) => {
  const permitted = hasToolPermission(tool);
  const active = enabled.value && modelContextSupported.value && permitted;
  const statusLabel = !permitted
    ? t("webmcp_tool_permission_required", "Permission required")
    : !enabled.value
      ? t("webmcp_tool_disabled", "Disabled")
      : !modelContextSupported.value
        ? t("webmcp_tool_runtime_unavailable", "Runtime unavailable")
        : t("webmcp_tool_available", "Available");

  return {
    ...tool,
    statusLabel,
    statusClass: {
      "is-available": active,
      "is-disabled": enabled.value && permitted && !modelContextSupported.value,
      "is-permission-required": !permitted,
    },
  };
};

const groupedTools = computed(() =>
  groupWebMcpTools(WEBMCP_TOOL_CATALOG).map((section) => ({
    ...section,
    tools: section.tools.map(getToolState),
  })),
);

const isSectionExpanded = (sectionKey) =>
  expandedSections.value.includes(sectionKey);

const toggleSection = (sectionKey) => {
  expandedSections.value = isSectionExpanded(sectionKey)
    ? expandedSections.value.filter((key) => key !== sectionKey)
    : [...expandedSections.value, sectionKey];
};

const isToolExpanded = (toolName) => expandedTools.value.includes(toolName);

const toggleTool = (toolName) => {
  expandedTools.value = isToolExpanded(toolName)
    ? expandedTools.value.filter((name) => name !== toolName)
    : [...expandedTools.value, toolName];
};

const permissionLabel = (tool) => {
  if (tool.permissionKeys.length === 1) {
    return tParams("webmcp_permission_label", "Requires {permission}", {
      permission: tool.permissionKeys[0],
    });
  }
  return t(
    "webmcp_permission_any_label",
    "Requires one of the client permissions",
  );
};

const accessModeLabel = (tool) =>
  tool.accessMode === "write"
    ? t("webmcp_access_write", "Write")
    : t("webmcp_access_read", "Read");

const formatJson = (value) => JSON.stringify(value, null, 2);

const requirementClass = (requirement) => {
  if (requirement === "Optional") return "is-optional";
  if (requirement === "At least one") return "is-conditional";
  return "is-required";
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
  font-family: var(--font-ui);
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
  font-size: 14px;
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
  max-width: 580px;
  margin: 7px 0 0;
  color: var(--color-muted);
  font-size: 14px;
}

.webmcp-catalog-panel {
  overflow: hidden;
  margin-top: 22px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 9px;
  box-shadow: 0 10px 28px rgba(15, 23, 42, 0.05);
}

.webmcp-catalog-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;
  padding: 24px 26px 22px;
  background: linear-gradient(
    135deg,
    var(--color-surface-soft),
    var(--color-surface)
  );
  border-bottom: 1px solid var(--color-border);
}

.webmcp-catalog-heading h2 {
  margin: 0;
  color: var(--color-ink);
  font-size: 19px;
  letter-spacing: -0.02em;
}

.webmcp-catalog-heading p:not(.webmcp-eyebrow) {
  max-width: 680px;
  margin: 7px 0 0;
  color: var(--color-muted);
  font-size: 14px;
  line-height: 1.6;
}

.webmcp-catalog-count {
  display: grid;
  min-width: 82px;
  padding: 9px 12px;
  text-align: center;
  background: var(--color-brand-soft);
  border: 1px solid var(--color-brand-soft-strong);
  border-radius: 6px;
}

.webmcp-catalog-count strong {
  color: var(--color-brand-strong);
  font-size: 24px;
  line-height: 1;
}

.webmcp-catalog-count span {
  margin-top: 5px;
  color: var(--color-brand-strong);
  font-size: 14px;
  font-weight: 750;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

.webmcp-section-list {
  display: grid;
  gap: 12px;
  padding: 16px;
  background: var(--color-canvas);
}

.webmcp-section {
  overflow: hidden;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 8px;
}

.webmcp-section-toggle,
.webmcp-tool-summary {
  width: 100%;
  border: 0;
  font: inherit;
  text-align: left;
  cursor: pointer;
}

.webmcp-section-toggle {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  padding: 16px 18px;
  color: var(--color-ink);
  background: var(--color-surface-soft);
}

.webmcp-section-toggle:hover,
.webmcp-section-toggle:focus-visible {
  background: var(--color-brand-soft);
}

.webmcp-section-toggle:focus-visible,
.webmcp-tool-summary:focus-visible {
  outline: 2px solid var(--color-brand);
  outline-offset: -2px;
}

.webmcp-section-leading {
  display: flex;
  align-items: center;
  min-width: 0;
  gap: 12px;
}

.webmcp-section-icon {
  display: grid;
  width: 34px;
  height: 34px;
  flex: 0 0 34px;
  place-items: center;
  color: var(--color-brand);
  background: var(--color-brand-soft);
  border: 1px solid var(--color-brand-soft-strong);
  border-radius: 6px;
}

.webmcp-section-copy {
  display: grid;
  min-width: 0;
}

.webmcp-section-copy .webmcp-eyebrow {
  margin-bottom: 3px;
  font-size: 14px;
}

.webmcp-section-copy strong {
  color: var(--color-ink);
  font-size: 15px;
}

.webmcp-section-copy small {
  overflow: hidden;
  margin-top: 3px;
  color: var(--color-muted);
  font-size: 14px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.webmcp-section-meta {
  display: inline-flex;
  align-items: center;
  flex: 0 0 auto;
  gap: 12px;
  color: var(--color-muted);
  font-size: 14px;
  font-weight: 700;
}

.webmcp-section-meta i,
.webmcp-tool-chevron {
  transition: transform var(--transition-fast);
}

.webmcp-section-meta i.is-collapsed,
.webmcp-tool-chevron:not(.is-expanded) {
  transform: rotate(-90deg);
}

.webmcp-section-tools {
  display: grid;
  gap: 1px;
  background: var(--color-border);
}

.webmcp-tool {
  background: var(--color-surface);
}

.webmcp-tool-summary {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto auto;
  align-items: center;
  gap: 13px;
  padding: 14px 18px;
  color: var(--color-ink);
  background: var(--color-surface);
}

.webmcp-tool-summary:hover,
.webmcp-tool.is-expanded .webmcp-tool-summary {
  background: var(--color-surface-soft);
}

.webmcp-tool-icon {
  display: grid;
  width: 32px;
  height: 32px;
  flex: 0 0 32px;
  place-items: center;
  color: var(--color-brand);
  background: var(--color-brand-soft);
  border-radius: 6px;
  font-size: 14px;
}

.webmcp-tool-copy {
  min-width: 0;
}

.webmcp-tool-name-line {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 9px;
}

.webmcp-tool-name-line strong {
  overflow: hidden;
  color: var(--color-ink);
  font-size: 14px;
  font-weight: 750;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.webmcp-tool-badge {
  padding: 3px 7px;
  color: var(--color-muted);
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  border-radius: 4px;
  font-size: 14px;
  font-weight: 700;
}

.webmcp-operation-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 7px;
  color: var(--color-success);
  background: var(--color-success-soft);
  border: 1px solid var(--color-success-border);
  border-radius: 4px;
  font-size: 14px;
  font-weight: 750;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.webmcp-operation-badge.is-write {
  color: var(--color-warning);
  background: var(--color-warning-soft);
  border-color: var(--color-warning-border);
}

.webmcp-tool-status {
  display: inline-flex;
  align-items: center;
  flex: 0 0 auto;
  gap: 7px;
  padding: 6px 9px;
  color: var(--color-muted);
  background: var(--color-surface-muted);
  border: 1px solid var(--color-border);
  border-radius: 999px;
  font-size: 14px;
  font-weight: 750;
  white-space: nowrap;
}

.webmcp-status-dot {
  width: 7px;
  height: 7px;
  flex: 0 0 7px;
  background: currentColor;
  border-radius: 50%;
}

.webmcp-tool-status.is-available,
.webmcp-tool-detail-footer .is-available {
  color: var(--color-success);
}

.webmcp-tool-status.is-available {
  background: var(--color-success-soft);
  border-color: var(--color-success-border);
}

.webmcp-tool-status.is-disabled,
.webmcp-tool-detail-footer .is-disabled {
  color: var(--color-warning);
}

.webmcp-tool-status.is-disabled {
  background: var(--color-warning-soft);
  border-color: var(--color-warning-border);
}

.webmcp-tool-status.is-permission-required,
.webmcp-tool-detail-footer .is-permission-required {
  color: var(--color-danger);
}

.webmcp-tool-status.is-permission-required {
  background: var(--color-danger-soft);
  border-color: var(--color-danger-border);
}

.webmcp-tool-chevron {
  width: 16px;
  color: var(--color-muted);
  /* @font-floor-exempt: visual-only disclosure glyph */
  font-size: 10px;
  text-align: center;
}

.webmcp-tool-chevron.is-expanded {
  transform: rotate(0deg);
}

.webmcp-tool-details {
  padding: 0 18px 16px 63px;
  background: var(--color-surface-soft);
}

.webmcp-tool-detail-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 18px;
  padding: 15px 0;
  border-top: 1px solid var(--color-border);
}

.webmcp-tool-detail {
  min-width: 0;
}

.webmcp-tool-detail > span {
  display: block;
  margin-bottom: 6px;
  color: var(--color-muted);
  font-size: 14px;
  font-weight: 800;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.webmcp-tool-detail p {
  margin: 0;
  color: var(--color-text);
  font-size: 14px;
  line-height: 1.55;
}

.webmcp-tool-detail-wide {
  grid-column: 1 / -1;
}

.webmcp-arguments-table-wrap {
  overflow-x: auto;
  margin-top: 9px;
  border: 1px solid var(--color-border);
  border-radius: 4px;
}

.webmcp-arguments-table {
  width: 100%;
  min-width: 680px;
  border-collapse: collapse;
  color: var(--color-text);
  font-size: 14px;
}

.webmcp-arguments-table th,
.webmcp-arguments-table td {
  padding: 10px 11px;
  border-bottom: 1px solid var(--color-border);
  text-align: left;
  vertical-align: top;
}

.webmcp-arguments-table th {
  position: static;
  top: auto;
  z-index: auto;
  color: var(--color-muted);
  background: var(--color-surface-muted);
  font-size: 14px;
  font-weight: 800;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.webmcp-arguments-table tbody tr:last-child td {
  border-bottom: 0;
}

.webmcp-arguments-table td:first-child,
.webmcp-arguments-table td:nth-child(2),
.webmcp-arguments-table td:nth-child(3) {
  white-space: nowrap;
}

.webmcp-arguments-table code {
  color: var(--color-ink);
  font-family: var(--font-ui);
  font-size: 14px;
  font-weight: 750;
}

.webmcp-requirement {
  display: inline-flex;
  padding: 3px 6px;
  border: 1px solid var(--color-border);
  border-radius: 999px;
  font-size: 14px;
  font-weight: 750;
  line-height: 1;
}

.webmcp-requirement.is-required {
  color: var(--color-info);
  background: var(--color-info-soft);
  border-color: var(--color-info-border);
}

.webmcp-requirement.is-conditional {
  color: var(--color-warning);
  background: var(--color-warning-soft);
  border-color: var(--color-warning-border);
}

.webmcp-requirement.is-optional {
  color: var(--color-muted);
  background: var(--color-surface-muted);
}

.webmcp-tool-detail pre {
  overflow-x: auto;
  margin: 9px 0 0;
  padding: 10px 11px;
  color: var(--color-ink);
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-left: 2px solid var(--color-brand);
  border-radius: 4px;
  font-family: var(--font-ui);
  font-size: 14px;
  line-height: 1.55;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}

.webmcp-inline-access {
  display: inline-block;
  margin-right: 5px;
  color: var(--color-success);
  font-weight: 800;
  text-transform: uppercase;
}

.webmcp-inline-access.is-write {
  color: var(--color-warning);
}

.webmcp-tool-detail-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  color: var(--color-muted);
  font-size: 14px;
}

.webmcp-tool-detail-footer > span {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.webmcp-tool-detail-footer .webmcp-status-dot {
  width: 6px;
  height: 6px;
  flex-basis: 6px;
}

@media (max-width: 760px) {
  .webmcp-page-header,
  .webmcp-catalog-heading {
    align-items: flex-start;
    flex-direction: column;
  }

  .webmcp-section-toggle {
    align-items: flex-start;
  }

  .webmcp-section-meta {
    margin-top: 2px;
  }

  .webmcp-tool-summary {
    grid-template-columns: auto minmax(0, 1fr) auto;
  }

  .webmcp-tool-status {
    grid-column: 2 / 3;
    justify-self: start;
  }

  .webmcp-tool-chevron {
    grid-column: 3 / 4;
    grid-row: 1 / 3;
  }

  .webmcp-tool-details {
    padding-left: 18px;
  }

  .webmcp-tool-detail-grid {
    grid-template-columns: 1fr;
    gap: 13px;
  }

  .webmcp-tool-detail-wide {
    grid-column: auto;
  }
}
</style>
