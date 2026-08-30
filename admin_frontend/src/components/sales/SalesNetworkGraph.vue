<template>
  <div class="sales-network-graph-wrap">
    <div class="sales-detail-graph-header">
      <h3>
        <i class="fas fa-project-diagram"></i>
        {{
          tParams(
            "salesList_graph_section",
            "Sales Network Relationship Graph ({total} Total Clients)",
            {
              total: clientCount,
            },
          )
        }}
      </h3>
      <div class="sales-detail-graph-search">
        <i class="fas fa-search"></i>
        <input
          v-model="searchQuery"
          type="text"
          :placeholder="
            t('salesList_graph_search_placeholder', 'Search network...')
          "
          @keydown.enter="searchGraph"
        />
      </div>
    </div>
    <div class="sales-network-canvas-wrap">
      <div v-if="showZoomIndicator" class="sales-zoom-indicator">
        <i class="fas fa-search"></i> <span>{{ zoomLevel }}%</span>
      </div>
      <div
        ref="networkContainerRef"
        class="sales-network-container"
        @mousedown="handleMouseDown"
        @wheel.prevent="handleWheel"
        @dblclick="resetView"
      >
        <div
          class="sales-network-graph"
          :style="{
            transform: `translate(${panX}px, ${panY}px) scale(${zoom})`,
          }"
        >
          <div v-if="loading" class="sales-network-loading">
            <i class="fas fa-spinner fa-spin"></i>
            {{ t("salesList_graph_loading", "Loading network...") }}
          </div>
          <template v-else>
            <div class="network-branch network-branch--root">
              <div class="network-node">
                <div class="node-card tier1 sales-root-card">
                  <div class="node-content">
                    <div class="node-avatar">{{ initials }}</div>
                    <div class="node-info">
                      <div class="node-title">{{ salesName || "—" }}</div>
                      <div class="node-subtitle">
                        {{ t("salesList_graph_rootSubtitle", "Sales") }}
                      </div>
                      <div class="node-badge">
                        <i class="fas fa-user-tie"></i>
                        {{ t("salesList_graph_badge_sales", "Sales") }}
                      </div>
                    </div>
                  </div>
                  <div class="node-stats">
                    <div class="node-stat">
                      <i class="fas fa-handshake"></i>
                      {{
                        tParams("salesList_graph_stat_ibs", "{count} IBs", {
                          count: ibCount,
                        })
                      }}
                    </div>
                    <div class="node-stat">
                      <i class="fas fa-users"></i>
                      {{
                        tParams(
                          "salesList_graph_stat_clients",
                          "{count} Clients",
                          { count: memberClientCount },
                        )
                      }}
                    </div>
                  </div>
                </div>
                <div
                  class="expand-btn"
                  :class="{ expanded: expandedNodes.tier1 }"
                  @click.stop="toggleRoot"
                >
                  {{ expandedNodes.tier1 ? "−" : "+" }}
                </div>
              </div>
              <div
                class="network-children"
                :class="{ expanded: expandedNodes.tier1 }"
              >
                <IbDetailNetworkGraphNode
                  v-for="member in members"
                  :key="member.id"
                  :member="member"
                  :expanded-nodes="expandedNodes"
                  :highlighted-node-id="highlightedNodeId"
                  @toggle="onNodeToggle"
                />
              </div>
              <div
                v-if="expandedNodes.tier1 && !members.length"
                class="network-empty-msg"
              >
                {{
                  t(
                    "salesList_graph_empty",
                    "No IBs or clients in this network.",
                  )
                }}
              </div>
            </div>
          </template>
        </div>
      </div>
      <div class="sales-zoom-controls">
        <button type="button" class="sales-zoom-btn" @click="zoomOut">
          <i class="fas fa-minus"></i>
        </button>
        <div class="sales-zoom-level">
          <i class="fas fa-search"></i> {{ zoomLevel }}%
        </div>
        <button type="button" class="sales-zoom-btn" @click="zoomIn">
          <i class="fas fa-plus"></i>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch, nextTick } from "vue";
import IbDetailNetworkGraphNode from "@/components/ib/IbDetailNetworkGraphNode.vue";
import salesApi from "@/services/salesApi";
import ibPartnersApi from "@/services/ibPartnersApi";
import { useAdminI18n } from "@/composables/useAdminI18n";

const props = defineProps({
  salesId: { type: [Number, String], required: true },
  salesName: { type: String, default: "" },
  clientTotal: { type: [Number, String], default: 0 },
});

const { t, tParams } = useAdminI18n();

const loading = ref(false);
const members = ref([]);
const expandedNodes = ref({ tier1: false });
const highlightedNodeId = ref(null);
const searchQuery = ref("");
const loaded = ref(false);

const panX = ref(0);
const panY = ref(0);
const zoom = ref(1);
const zoomLevel = ref("100");
const showZoomIndicator = ref(false);
const isDragging = ref(false);
const startX = ref(0);
const startY = ref(0);
const networkContainerRef = ref(null);
let zoomIndicatorTimer = null;

const ibCount = computed(
  () => members.value.filter((m) => m.type === "ib").length,
);
const memberClientCount = computed(
  () => members.value.filter((m) => m.type === "client").length,
);
const clientCount = computed(
  () => Number(props.clientTotal) || memberClientCount.value,
);

const initials = computed(() => {
  const name = String(props.salesName || "").trim();
  if (!name) return "—";
  return name
    .split(" ")
    .map((s) => s[0])
    .join("")
    .toUpperCase()
    .slice(0, 2);
});

const getInitials = (name) => {
  if (!name) return "—";
  return name
    .split(" ")
    .map((s) => s[0])
    .join("")
    .toUpperCase()
    .slice(0, 2);
};

const getIbInitials = (name) => {
  if (!name) return "—";
  const words = name.split(" ");
  if (words.length >= 3)
    return (words[0][0] + words[1][0] + words[2][0]).toUpperCase().slice(0, 3);
  return name.slice(0, 3).toUpperCase();
};

const buildTier1Members = (ibs, clients) => {
  const next = [];
  (ibs || []).forEach((ib) => {
    next.push({
      id: `ib-${ib.id}`,
      clientUserId: Number(ib.userId || 0),
      name: ib.ibName || "—",
      code: ib.ibCode || "",
      adminAlias: ib.adminAlias || "",
      type: "ib",
      hasChildren: true,
      children: [],
      initials: getIbInitials(ib.ibName),
    });
  });
  (clients || []).forEach((client) => {
    const fullName =
      [client.firstName, client.lastName].filter(Boolean).join(" ").trim() ||
      "—";
    next.push({
      id: `client-${client.id}`,
      name: fullName,
      code: client.clientId || "",
      type: "client",
      hasChildren: false,
      children: [],
      initials: getInitials(fullName),
    });
  });
  return next;
};

const loadGraph = async () => {
  const salesId = Number(props.salesId);
  if (!salesId) return;
  loading.value = true;
  try {
    const [ibsRes, clientsRes] = await Promise.all([
      salesApi.getBoundIbs(salesId, { page: 1, per_page: 9999 }),
      salesApi.getBoundClients(salesId, { page: 1, per_page: 9999 }),
    ]);
    members.value = buildTier1Members(
      ibsRes?.items ?? [],
      clientsRes?.items ?? [],
    );
    expandedNodes.value = { ...expandedNodes.value, tier1: false };
    loaded.value = true;
  } catch (err) {
    members.value = [];
    loaded.value = true;
  } finally {
    loading.value = false;
  }
};

const findMember = (list, nodeId) => {
  if (!list || !nodeId) return null;
  for (const member of list) {
    if (member.id === nodeId) return member;
    const found = findMember(member.children, nodeId);
    if (found) return found;
  }
  return null;
};

const loadIbChildren = async (nodeId) => {
  const member = findMember(members.value, nodeId);
  if (
    !member ||
    member.type !== "ib" ||
    (member.children && member.children.length > 0)
  )
    return;
  const numericId = parseInt(String(nodeId).replace(/^ib-/, ""), 10);
  if (!numericId) return;
  try {
    const res = await ibPartnersApi.getNetwork(numericId);
    const raw = res?.data?.data ?? res?.data ?? res;
    member.children = Array.isArray(raw) ? raw : (raw?.members ?? []);
  } catch (err) {
    member.children = [];
  }
};

const toggleRoot = () => {
  if (!loaded.value && !loading.value) {
    loadGraph();
    return;
  }
  expandedNodes.value = {
    ...expandedNodes.value,
    tier1: !expandedNodes.value.tier1,
  };
};

const onNodeToggle = (nodeId) => {
  const next = {
    ...expandedNodes.value,
    [nodeId]: !expandedNodes.value[nodeId],
  };
  expandedNodes.value = next;
  if (next[nodeId]) loadIbChildren(nodeId);
};

const searchInMembers = (list, query, path = []) => {
  const results = [];
  const lowerQuery = String(query).toLowerCase().trim();
  if (!lowerQuery) return results;
  (list || []).forEach((member) => {
    const currentPath = [...path, member.id];
    const name = (member.name || "").toLowerCase();
    const code = (member.code || "").toLowerCase();
    const alias = (member.adminAlias || "").toLowerCase();
    const idStr = String(member.id).toLowerCase();
    if (
      name.includes(lowerQuery) ||
      code.includes(lowerQuery) ||
      alias.includes(lowerQuery) ||
      idStr.includes(lowerQuery)
    ) {
      results.push({ member, path: currentPath });
    }
    if (member.children && member.children.length > 0) {
      results.push(...searchInMembers(member.children, query, currentPath));
    }
  });
  return results;
};

const flashZoom = () => {
  showZoomIndicator.value = true;
  if (zoomIndicatorTimer) clearTimeout(zoomIndicatorTimer);
  zoomIndicatorTimer = setTimeout(() => {
    showZoomIndicator.value = false;
  }, 1000);
};

const searchGraph = () => {
  const query = searchQuery.value.trim();
  highlightedNodeId.value = null;
  if (!query) return;
  const results = searchInMembers(members.value, query);
  if (!results.length) {
    alert(
      tParams(
        "salesList_graph_noMatch",
        'No members found matching "{query}"',
        { query },
      ),
    );
    return;
  }
  const first = results[0];
  const next = { ...expandedNodes.value, tier1: true };
  (first.path || []).forEach((nodeId) => {
    if (nodeId !== "tier1") next[nodeId] = true;
  });
  expandedNodes.value = next;
  highlightedNodeId.value = first.member.id;
  nextTick(() => {
    const el = document.querySelector(`[data-node-id="${first.member.id}"]`);
    if (!el || !networkContainerRef.value) return;
    const containerRect = networkContainerRef.value.getBoundingClientRect();
    const nodeRect = el.getBoundingClientRect();
    panX.value =
      containerRect.width / 2 -
      (nodeRect.left - containerRect.left + nodeRect.width / 2);
    panY.value =
      containerRect.height / 2 -
      (nodeRect.top - containerRect.top + nodeRect.height / 2);
  });
};

const handleMouseDown = (event) => {
  if (event.target.closest(".expand-btn") || event.target.closest(".node-card"))
    return;
  isDragging.value = true;
  startX.value = event.clientX - panX.value;
  startY.value = event.clientY - panY.value;
  if (networkContainerRef.value)
    networkContainerRef.value.classList.add("dragging");
  event.preventDefault();
};

const handleMouseMove = (event) => {
  if (!isDragging.value) return;
  panX.value = event.clientX - startX.value;
  panY.value = event.clientY - startY.value;
};

const handleMouseUp = () => {
  if (!isDragging.value) return;
  isDragging.value = false;
  if (networkContainerRef.value)
    networkContainerRef.value.classList.remove("dragging");
};

const handleWheel = (event) => {
  event.preventDefault();
  const delta = event.deltaY > 0 ? -0.1 : 0.1;
  zoom.value = Math.max(0.3, Math.min(3.0, zoom.value + delta));
  zoomLevel.value = String(Math.round(zoom.value * 100));
  flashZoom();
};

const resetView = (event) => {
  if (event.target.closest(".node-card") || event.target.closest(".expand-btn"))
    return;
  zoom.value = 1;
  panX.value = 0;
  panY.value = 0;
  zoomLevel.value = "100";
  flashZoom();
};

const zoomIn = () => {
  zoom.value = Math.min(3.0, zoom.value + 0.15);
  zoomLevel.value = String(Math.round(zoom.value * 100));
  flashZoom();
};

const zoomOut = () => {
  zoom.value = Math.max(0.3, zoom.value - 0.15);
  zoomLevel.value = String(Math.round(zoom.value * 100));
  flashZoom();
};

watch(
  () => props.salesId,
  () => {
    loaded.value = false;
    members.value = [];
    searchQuery.value = "";
    highlightedNodeId.value = null;
    loadGraph();
  },
);

onMounted(() => {
  document.addEventListener("mousemove", handleMouseMove);
  document.addEventListener("mouseup", handleMouseUp);
  loadGraph();
});

onUnmounted(() => {
  document.removeEventListener("mousemove", handleMouseMove);
  document.removeEventListener("mouseup", handleMouseUp);
  if (zoomIndicatorTimer) clearTimeout(zoomIndicatorTimer);
});
</script>

<style scoped>
.sales-network-graph-wrap {
  min-width: 0;
}

.sales-detail-graph-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
  flex-wrap: wrap;
  gap: 10px;
}

.sales-detail-graph-header h3 {
  margin: 0;
  font-size: 16px;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 8px;
}

.sales-detail-graph-header h3 i {
  color: var(--color-brand);
}

.sales-detail-graph-search {
  position: relative;
  display: flex;
  align-items: center;
}

.sales-detail-graph-search i {
  position: absolute;
  left: 12px;
  color: var(--color-faint);
  font-size: 14px;
}

.sales-detail-graph-search input {
  padding: 10px 12px 10px 35px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  width: 260px;
}

.sales-network-canvas-wrap {
  position: relative;
  min-height: 400px;
}

.sales-zoom-indicator {
  position: absolute;
  top: 12px;
  right: 12px;
  background: rgba(var(--color-brand-rgb), 0.95);
  color: white;
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  z-index: 10;
  pointer-events: none;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.sales-network-loading {
  padding: 24px;
  text-align: center;
  color: var(--color-muted);
}

.sales-network-container {
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: 24px;
  overflow: hidden;
  min-height: 400px;
  max-height: 520px;
  position: relative;
  cursor: grab;
  user-select: none;
}

.sales-network-container.dragging {
  cursor: grabbing;
}

.sales-network-graph {
  display: flex;
  flex-direction: row;
  align-items: flex-start;
  min-width: max-content;
  padding: 16px;
  transform-origin: 0 0;
}

.network-branch--root {
  display: flex;
  flex-direction: row;
  align-items: flex-start;
  gap: 24px;
}

.network-branch--root .network-node {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 16px;
}

.node-card.tier1.sales-root-card {
  background: var(--color-brand-solid);
  border: 3px solid var(--color-brand-strong);
  color: white;
  min-width: 220px;
  padding: 18px 20px;
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.node-content {
  display: flex;
  align-items: center;
  gap: 12px;
}

.node-avatar {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.25);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  font-weight: 700;
  border: 2px solid rgba(255, 255, 255, 0.4);
}

.node-title {
  font-size: 15px;
  font-weight: 700;
}

.node-subtitle {
  opacity: 0.9;
  font-size: 12px;
}

.node-badge {
  background: rgba(255, 255, 255, 0.25);
  padding: 3px 8px;
  border-radius: var(--radius-md);
  font-size: 11px;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  margin-top: 4px;
}

.node-stats {
  display: flex;
  gap: 12px;
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px solid rgba(255, 255, 255, 0.25);
  font-size: 11px;
  flex-wrap: wrap;
}

.node-stat {
  display: flex;
  align-items: center;
  gap: 4px;
}

.expand-btn {
  width: 32px;
  height: 32px;
  min-width: 32px;
  min-height: 32px;
  border-radius: 50%;
  background: var(--color-surface);
  border: 3px solid var(--color-border-strong);
  color: var(--color-text);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 16px;
  font-weight: 700;
  flex-shrink: 0;
  z-index: 5;
}

.expand-btn:hover,
.expand-btn.expanded {
  background: var(--color-brand-solid);
  border-color: var(--color-brand);
  color: white;
}

.network-children {
  display: none;
  flex-direction: column;
  gap: 16px;
  margin-left: 24px;
  padding-left: 20px;
  border-left: 2px solid var(--color-border-strong);
}

.network-children.expanded {
  display: flex;
}

.network-empty-msg {
  margin-left: 24px;
  padding: 12px;
  color: var(--color-muted);
  font-size: 13px;
}

.sales-zoom-controls {
  position: absolute;
  bottom: 20px;
  right: 20px;
  display: flex;
  gap: 10px;
  align-items: center;
  background: var(--color-surface);
  padding: 10px;
  border-radius: var(--radius-md);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  z-index: 5;
}

.sales-zoom-btn {
  width: 36px;
  height: 36px;
  border: 2px solid var(--color-border);
  background: var(--color-surface);
  border-radius: var(--radius-sm);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.sales-zoom-btn:hover {
  border-color: var(--color-brand);
  color: var(--color-brand);
}

.sales-zoom-level {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 10px;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
}
</style>
