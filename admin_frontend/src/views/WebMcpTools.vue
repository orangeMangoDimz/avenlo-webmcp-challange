<template>
  <div class="webmcp-page">
    <div class="webmcp-page-header">
      <div>
        <h1>
          <i class="fas fa-list-check" aria-hidden="true"></i>
          {{ t("webmcp_tools_title", "Tool catalog") }}
        </h1>
        <p class="webmcp-subtitle">
          {{
            t(
              "webmcp_tools_subtitle",
              "The permission-aware admin tools exposed to the browser Model Context API.",
            )
          }}
        </p>
      </div>
      <PageHeaderActions />
    </div>

    <DeveloperToolsTabs />

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

      <div class="webmcp-filter-bar">
        <div class="webmcp-search-field">
          <label for="webmcp-tool-search">
            {{ t("webmcp_search_tools", "Search tools") }}
          </label>
          <div class="webmcp-search-input-wrapper">
            <input
              id="webmcp-tool-search"
              v-model="searchQuery"
              type="search"
              @input="handleToolSearch"
              :placeholder="t('webmcp_search_placeholder', 'Search by tool name or description...')"
            />
            <i class="fas fa-search webmcp-search-icon" aria-hidden="true"></i>
          </div>
        </div>
        <div class="webmcp-filter-field">
          <label for="webmcp-section-filter">
            {{ t("webmcp_filter_section", "Section") }}
          </label>
          <select
            id="webmcp-section-filter"
            v-model="selectedSection"
            @change="handleSectionFilterChange"
          >
            <option value="all">
              {{ t("webmcp_filter_all_sections", "All sections") }}
            </option>
            <option
              v-for="section in groupedTools"
              :key="section.key"
              :value="section.key"
            >
              {{ section.title }}
            </option>
          </select>
        </div>
        <div class="webmcp-filter-field">
          <label for="webmcp-access-filter">
            {{ t("webmcp_filter_access", "Access") }}
          </label>
          <select
            id="webmcp-access-filter"
            v-model="selectedAccess"
            @change="handleAccessFilterChange"
          >
            <option value="all">
              {{ t("webmcp_filter_all_access", "All access") }}
            </option>
            <option value="read">
              {{ t("webmcp_access_read", "Read") }}
            </option>
            <option value="write">
              {{ t("webmcp_access_write", "Write") }}
            </option>
            <option value="export">
              {{ t("webmcp_access_export", "Export") }}
            </option>
          </select>
        </div>
      </div>

      <div class="webmcp-table-container">
        <div class="webmcp-table-header">
          <h2>
            <i class="fas fa-list-check" aria-hidden="true"></i>
            {{ t("webmcp_table_title", "WebMCP Tool Details") }}
          </h2>
        </div>
        <div class="webmcp-table-scroll">
          <table class="webmcp-tool-table">
            <thead>
              <tr>
                <th scope="col">
                  {{ t("webmcp_tool_information_column", "Tool information") }}
                </th>
                <th scope="col">
                  {{ t("webmcp_section_column", "Section") }}
                </th>
                <th scope="col">{{ t("webmcp_access_column", "Access") }}</th>
                <th scope="col">{{ t("webmcp_status_column", "Status") }}</th>
                <th scope="col" class="webmcp-tool-action-column">
                  {{ t("webmcp_detail_column", "Detail") }}
                </th>
              </tr>
            </thead>
            <tbody>
              <template
                v-for="section in paginatedGroupedTools"
                :key="section.key"
              >
                <template v-for="tool in section.tools" :key="tool.name">
                    <tr
                      class="webmcp-tool-row"
                      :class="{ 'is-expanded': isToolExpanded(tool.name) }"
                    >
                      <td class="webmcp-tool-cell">
                        <div class="webmcp-tool-info">
                          <span class="webmcp-tool-icon">
                            <i
                              :class="['fas', tool.icon]"
                              aria-hidden="true"
                            ></i>
                          </span>
                          <span class="webmcp-tool-copy">
                            <strong>{{ tool.name }}</strong>
                            <span class="webmcp-tool-description">{{
                              tool.description
                            }}</span>
                          </span>
                        </div>
                      </td>
                      <td class="webmcp-tool-section-cell">
                        <span class="webmcp-tool-section">{{
                          section.title
                        }}</span>
                      </td>
                      <td>
                        <span
                          class="webmcp-operation-badge"
                          :class="`is-${tool.accessMode}`"
                        >
                          <i
                            :class="['fas', accessModeIcon(tool)]"
                            aria-hidden="true"
                          ></i>
                          {{ accessModeLabel(tool) }}
                        </span>
                      </td>
                      <td>
                        <span
                          class="webmcp-tool-status"
                          :class="tool.statusClass"
                        >
                          <span class="webmcp-status-dot"></span>
                          {{ tool.statusLabel }}
                        </span>
                      </td>
                      <td class="webmcp-tool-action-cell">
                        <button
                          class="webmcp-tool-action btn-action btn-detail"
                          type="button"
                          :aria-expanded="isToolExpanded(tool.name)"
                          :aria-controls="`webmcp-tool-details-${tool.name}`"
                          :aria-label="`${tool.title}: ${
                            isToolExpanded(tool.name)
                              ? t('webmcp_hide_details', 'Hide details')
                              : t('webmcp_show_details', 'Show details')
                          }`"
                          @click="toggleTool(tool.name)"
                        >
                          <i
                            :class="
                              isToolExpanded(tool.name)
                                ? 'fas fa-chevron-up'
                                : 'fas fa-chevron-down'
                            "
                            aria-hidden="true"
                          ></i>
                          {{ t("webmcp_detail_button", "Detail") }}
                        </button>
                      </td>
                    </tr>

                    <tr
                      v-if="isToolExpanded(tool.name)"
                      class="webmcp-tool-detail-row"
                    >
                      <td colspan="5">
                        <div
                          :id="`webmcp-tool-details-${tool.name}`"
                          class="webmcp-tool-details"
                        >
                          <div class="webmcp-tool-detail-grid">
                            <div
                              class="webmcp-tool-detail webmcp-tool-detail-wide"
                            >
                              <span>
                                {{ t("webmcp_detail_purpose", "Purpose") }}
                              </span>
                              <p>{{ tool.description }}</p>
                            </div>
                            <div
                              class="webmcp-tool-detail webmcp-tool-detail-wide"
                            >
                              <span>
                                {{
                                  t(
                                    "webmcp_detail_arguments",
                                    "Input arguments",
                                  )
                                }}
                              </span>
                              <div class="webmcp-arguments-table-wrap">
                                <table class="webmcp-arguments-table">
                                  <thead>
                                    <tr>
                                      <th scope="col">
                                        {{
                                          t("webmcp_argument_name", "Argument")
                                        }}
                                      </th>
                                      <th scope="col">
                                        {{ t("webmcp_argument_type", "Type") }}
                                      </th>
                                      <th scope="col">
                                        {{
                                          t(
                                            "webmcp_argument_requirement",
                                            "Required",
                                          )
                                        }}
                                      </th>
                                      <th scope="col">
                                        {{
                                          t(
                                            "webmcp_argument_description",
                                            "Description",
                                          )
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
                                          :class="
                                            requirementClass(field.requirement)
                                          "
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
                              <span>
                                {{
                                  t("webmcp_detail_input", "Request JSON")
                                }}
                              </span>
                              <p>{{ tool.inputSummary }}</p>
                              <pre><code>{{
                                formatJson(tool.inputExample)
                              }}</code></pre>
                            </div>
                            <div class="webmcp-tool-detail">
                              <span>
                                {{
                                  t("webmcp_detail_output", "Response JSON")
                                }}
                              </span>
                              <p>{{ tool.outputSummary }}</p>
                              <pre><code>{{
                                formatJson(tool.outputExample)
                              }}</code></pre>
                            </div>
                            <div
                              class="webmcp-tool-detail webmcp-tool-detail-wide"
                            >
                              <span>
                                {{
                                  t("webmcp_detail_access", "Permission status")
                                }}
                              </span>
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
                              <i
                                class="fas fa-shield-halved"
                                aria-hidden="true"
                              ></i>
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
                      </td>
                    </tr>
                  </template>
              </template>
            </tbody>
          </table>
        </div>
        <div v-if="totalPages > 1" class="pagination">
          <div class="pagination-info">
            {{
              tParams(
                "webmcp_catalog_pagination_range",
                "Showing {from}–{to} of {total} tools",
                {
                  from: (currentPage - 1) * perPage + 1,
                  to: Math.min(currentPage * perPage, visibleToolCount),
                  total: visibleToolCount,
                },
              )
            }}
          </div>
          <div class="pagination-controls">
            <button
              class="pagination-btn"
              :disabled="currentPage === 1"
              @click="changePage(currentPage - 1)"
            >
              <i class="fas fa-chevron-left" aria-hidden="true"></i>
              {{ t("webmcp_catalog_pagination_previous", "Previous") }}
            </button>
            <template v-for="page in visiblePages" :key="page">
              <button
                v-if="page !== '...'"
                :class="['pagination-btn', { active: currentPage === page }]"
                @click="changePage(page)"
              >
                {{ page }}
              </button>
              <span v-else class="pagination-ellipsis">...</span>
            </template>
            <button
              class="pagination-btn"
              :disabled="currentPage === totalPages"
              @click="changePage(currentPage + 1)"
            >
              {{ t("webmcp_catalog_pagination_next", "Next") }}
              <i class="fas fa-chevron-right" aria-hidden="true"></i>
            </button>
          </div>
        </div>
        <div v-else class="webmcp-table-footer">
          {{
            tParams("webmcp_catalog_showing_all", "Showing {count} tools", {
              count: visibleToolCount,
            })
          }}
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from "vue";
import DeveloperToolsTabs from "@/components/layout/DeveloperToolsTabs.vue";
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
const selectedSection = ref("all");
const selectedAccess = ref("all");
const searchQuery = ref("");
const expandedTools = ref([]);
const currentPage = ref(1);
const perPage = 10;
let unsubscribeWebMcpSetting = () => {};

const hasToolPermission = (tool) => {
  const matcher = tool.permissionMatch === "all" ? "every" : "some";
  return tool.permissionKeys[matcher]((permissionKey) =>
    authStore.hasPermission(permissionKey),
  );
};

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

const visibleGroupedTools = computed(() => {
  const normalizedQuery = searchQuery.value.trim().toLowerCase();
  return groupedTools.value
    .filter(
      (section) =>
        selectedSection.value === "all" ||
        section.key === selectedSection.value,
    )
    .map((section) => ({
      ...section,
      tools: section.tools.filter((tool) => {
        if (
          selectedAccess.value !== "all" &&
          tool.accessMode !== selectedAccess.value
        ) {
          return false;
        }
        if (!normalizedQuery) return true;
        return [
          tool.name,
          tool.title,
          tool.description,
          section.title,
        ].some((value) =>
          String(value || "")
            .toLowerCase()
            .includes(normalizedQuery),
        );
      }),
    }))
    .filter((section) => section.tools.length > 0);
});

const visibleToolCount = computed(() =>
  visibleGroupedTools.value.reduce(
    (count, section) => count + section.tools.length,
    0,
  ),
);

const visibleTools = computed(() =>
  visibleGroupedTools.value.flatMap((section) =>
    section.tools.map((tool) => ({
      sectionKey: section.key,
      sectionTitle: section.title,
      tool,
    })),
  ),
);

const totalPages = computed(() =>
  Math.ceil(visibleToolCount.value / perPage),
);

const visiblePages = computed(() => {
  const pages = [];
  const maxVisible = 5;

  if (totalPages.value <= maxVisible) {
    for (let i = 1; i <= totalPages.value; i++) {
      pages.push(i);
    }
  } else if (currentPage.value <= 3) {
    for (let i = 1; i <= 4; i++) pages.push(i);
    pages.push("...");
    pages.push(totalPages.value);
  } else if (currentPage.value >= totalPages.value - 2) {
    pages.push(1);
    pages.push("...");
    for (let i = totalPages.value - 3; i <= totalPages.value; i++) {
      pages.push(i);
    }
  } else {
    pages.push(1);
    pages.push("...");
    pages.push(currentPage.value - 1);
    pages.push(currentPage.value);
    pages.push(currentPage.value + 1);
    pages.push("...");
    pages.push(totalPages.value);
  }

  return pages;
});

const paginatedGroupedTools = computed(() => {
  const start = (currentPage.value - 1) * perPage;
  const pageTools = visibleTools.value.slice(start, start + perPage);

  return pageTools.reduce((sections, entry) => {
    const lastSection = sections[sections.length - 1];
    if (!lastSection || lastSection.key !== entry.sectionKey) {
      sections.push({
        key: entry.sectionKey,
        title: entry.sectionTitle,
        tools: [],
      });
    }
    sections[sections.length - 1].tools.push(entry.tool);
    return sections;
  }, []);
});

const handleSectionFilterChange = () => {
  currentPage.value = 1;
  expandedTools.value = [];
};

const handleAccessFilterChange = () => {
  currentPage.value = 1;
  expandedTools.value = [];
};

const handleToolSearch = () => {
  currentPage.value = 1;
  expandedTools.value = [];
};

const isToolExpanded = (toolName) => expandedTools.value.includes(toolName);

const toggleTool = (toolName) => {
  expandedTools.value = isToolExpanded(toolName) ? [] : [toolName];
};

const permissionLabel = (tool) => {
  if (tool.permissionKeys.length === 1) {
    return tParams("webmcp_permission_label", "Requires {permission}", {
      permission: tool.permissionKeys[0],
    });
  }
  if (tool.permissionMatch === "all") {
    return tParams(
      "webmcp_permission_all_label",
      "Requires all of: {permissions}",
      { permissions: tool.permissionKeys.join(", ") },
    );
  }
  return t(
    "webmcp_permission_any_label",
    "Requires one of the client permissions",
  );
};

const accessModeLabel = (tool) => {
  if (tool.accessMode === "write") return t("webmcp_access_write", "Write");
  if (tool.accessMode === "export")
    return t("webmcp_access_export", "Export");
  return t("webmcp_access_read", "Read");
};

const accessModeIcon = (tool) => {
  if (tool.accessMode === "write") return "fa-pen";
  if (tool.accessMode === "export") return "fa-file-export";
  return "fa-eye";
};

const formatJson = (value) => JSON.stringify(value, null, 2);

const requirementClass = (requirement) => {
  if (requirement === "Optional") return "is-optional";
  if (requirement === "At least one") return "is-conditional";
  return "is-required";
};

const changePage = (page) => {
  if (page === "..." || page < 1 || page > totalPages.value) return;
  currentPage.value = page;
  expandedTools.value = [];
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
  margin-top: 22px;
}

.webmcp-catalog-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  margin-bottom: 12px;
}

.webmcp-catalog-heading h2 {
  margin: 0;
  color: var(--color-ink);
  font-size: 19px;
  letter-spacing: -0.02em;
}

.webmcp-catalog-heading p:not(.webmcp-eyebrow) {
  max-width: 680px;
  margin: 4px 0 0;
  color: var(--color-muted);
  font-size: 14px;
  line-height: 1.45;
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

.webmcp-filter-bar {
  display: flex;
  align-items: center;
  gap: 20px;
  padding: 12px 14px;
  margin-bottom: 12px;
  flex-wrap: wrap;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
}

.webmcp-filter-field {
  display: flex;
  align-items: center;
  gap: 12px;
}

.webmcp-filter-field label {
  color: var(--color-text);
  font-size: 14px;
  font-weight: 600;
}

.webmcp-filter-field select {
  min-width: 250px;
  min-height: 40px;
  padding: 8px 36px 8px 12px;
  color: var(--color-ink);
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font: inherit;
  font-size: 14px;
  cursor: pointer;
}

.webmcp-filter-field select:hover,
.webmcp-filter-field select:focus {
  border-color: var(--color-brand);
}

.webmcp-search-field {
  display: flex;
  align-items: center;
  flex: 1 1 360px;
  min-width: 300px;
  gap: 12px;
}

.webmcp-search-field label {
  flex: 0 0 auto;
  color: var(--color-text);
  font-size: 14px;
  font-weight: 600;
}

.webmcp-search-input-wrapper {
  position: relative;
  flex: 1;
}

.webmcp-search-input-wrapper input {
  width: 100%;
  min-height: 40px;
  padding: 10px 40px 10px 15px;
  color: var(--color-ink);
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font: inherit;
  font-size: 14px;
  outline: none;
  transition: all 0.3s ease;
}

.webmcp-search-input-wrapper input:focus {
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.webmcp-search-icon {
  position: absolute;
  top: 50%;
  right: 15px;
  color: var(--color-faint);
  transform: translateY(-50%);
}

.webmcp-table-container {
  overflow: hidden;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.webmcp-table-header {
  display: flex;
  align-items: center;
  min-height: 72px;
  padding: 20px 30px;
  background: var(--color-surface-soft);
  border-bottom: 2px solid var(--color-border);
}

.webmcp-table-header h2 {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 0;
  color: var(--color-ink);
  font-size: 18px;
}

.webmcp-table-header h2 i {
  color: var(--color-brand);
}

.webmcp-table-scroll {
  width: 100%;
  overflow-x: auto;
  overflow-y: hidden;
  overscroll-behavior-inline: contain;
  -webkit-overflow-scrolling: touch;
}

.pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 30px;
  border-top: 2px solid var(--color-border);
}

.pagination-info {
  color: var(--color-muted);
  font-size: 14px;
}

.pagination-controls {
  display: flex;
  gap: 8px;
}

.pagination-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 8px 12px;
  color: var(--color-text);
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
}

.pagination-btn:hover:not(:disabled) {
  color: var(--color-brand);
  background: var(--color-brand-soft);
  border-color: var(--color-brand);
}

.pagination-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.pagination-btn.active {
  color: #fff;
  background: var(--color-brand-solid);
  border-color: var(--color-brand);
}

.pagination-ellipsis {
  padding: 8px 12px;
  color: var(--color-faint);
  font-weight: 600;
}

.webmcp-table-footer {
  padding: 20px 30px;
  color: var(--color-muted);
  border-top: 2px solid var(--color-border);
  font-size: 14px;
}

.webmcp-tool-table {
  width: 100%;
  min-width: 1060px;
  table-layout: fixed;
  border-collapse: collapse;
  font-family: var(--font-ui);
}

.webmcp-tool-table thead {
  background: var(--color-surface-soft);
}

.webmcp-tool-table th {
  padding: 16px 20px;
  color: var(--color-text);
  border-bottom: 2px solid var(--color-border);
  font-size: 14px;
  font-weight: 600;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  white-space: nowrap;
}

.webmcp-tool-table > thead > tr > th:nth-child(1) {
  width: 36%;
}

.webmcp-tool-table > thead > tr > th:nth-child(2) {
  width: 16%;
}

.webmcp-tool-table > thead > tr > th:nth-child(3) {
  width: 14%;
}

.webmcp-tool-table > thead > tr > th:nth-child(4) {
  width: 20%;
}

.webmcp-tool-table > thead > tr > th:nth-child(5) {
  width: 14%;
}

.webmcp-tool-table td {
  padding: 16px 20px;
  color: var(--color-text);
  border-bottom: 1px solid var(--color-border);
  font-size: 14px;
  text-align: left;
  vertical-align: middle;
  line-height: 1.4;
}

.webmcp-tool-table th:nth-child(1),
.webmcp-tool-table td:nth-child(1) {
  min-width: 360px;
}

.webmcp-tool-action-column,
.webmcp-tool-action-cell {
  width: 112px;
  min-width: 112px !important;
  padding-right: 20px !important;
  padding-left: 20px !important;
  text-align: left !important;
}

.webmcp-tool-table tbody tr {
  border-bottom: 1px solid var(--color-border);
  transition: all 0.2s ease;
}

.webmcp-tool-table tbody tr:not(.webmcp-tool-detail-row):hover {
  background: var(--color-surface-soft);
}

.webmcp-tool-action:focus-visible {
  outline: 2px solid var(--color-brand);
  outline-offset: 3px;
}

.webmcp-tool-section {
  display: inline-block;
  color: var(--color-muted);
  font-size: 14px;
  font-weight: 600;
  white-space: nowrap;
}

.webmcp-tool-row.is-expanded td {
  background: var(--color-brand-soft);
}

.webmcp-tool-info {
  display: flex;
  align-items: center;
  width: 100%;
  gap: 12px;
}

.webmcp-tool-icon {
  display: grid;
  width: 40px;
  height: 40px;
  flex: 0 0 40px;
  place-items: center;
  color: #fff;
  background: var(--color-brand-solid);
  border-radius: 50%;
  font-size: 14px;
}

.webmcp-tool-copy {
  min-width: 0;
}

.webmcp-tool-copy strong {
  display: block;
  overflow: hidden;
  color: var(--color-ink);
  font-size: 14px;
  font-weight: 750;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.webmcp-tool-description {
  display: block;
  max-width: 100%;
  margin-top: 3px;
  color: var(--color-muted);
  font-size: 14px;
  font-weight: 600;
  line-height: 1.4;
  white-space: normal;
  overflow-wrap: anywhere;
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

.webmcp-operation-badge.is-export {
  color: var(--color-info, #2563eb);
  background: color-mix(in srgb, currentColor 10%, transparent);
  border-color: color-mix(in srgb, currentColor 25%, transparent);
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

.webmcp-tool-action {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  width: auto;
  height: auto;
  padding: 8px 16px;
  color: var(--color-brand);
  background: var(--color-brand-soft);
  border: 0;
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: 600;
  line-height: 1.2;
  white-space: nowrap;
  cursor: pointer;
  transition: all 0.3s ease;
}

.webmcp-tool-action:hover {
  color: #fff;
  background: var(--color-brand-solid);
}

.webmcp-tool-details {
  width: 100%;
  min-width: 0;
  max-width: 100%;
  box-sizing: border-box;
  padding: 30px;
  background: var(--color-surface-soft);
}

.webmcp-tool-detail-row > td {
  max-width: 0;
  padding: 0 !important;
  overflow: hidden;
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

.webmcp-inline-access.is-export {
  color: var(--color-info, #2563eb);
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

  .webmcp-tool-table th,
  .webmcp-tool-table td {
    padding-right: 10px;
    padding-left: 10px;
  }

  .webmcp-filter-field,
  .webmcp-filter-field select,
  .webmcp-search-field {
    width: 100%;
  }

  .webmcp-filter-field {
    align-items: flex-start;
    flex-direction: column;
  }

  .webmcp-tool-details {
    padding: 20px;
  }

  .webmcp-tool-detail-grid {
    grid-template-columns: 1fr;
    gap: 13px;
  }

  .webmcp-tool-detail-wide {
    grid-column: auto;
  }

  .pagination {
    flex-direction: column;
    gap: 15px;
  }

  .pagination-controls {
    flex-wrap: wrap;
    justify-content: center;
  }
}
</style>
