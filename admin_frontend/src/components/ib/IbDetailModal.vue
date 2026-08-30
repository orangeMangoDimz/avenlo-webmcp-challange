<template>
  <div class="ib-detail-overlay" @click.self="$emit('close')">
    <div class="ib-detail-modal">
      <div class="ib-detail-header">
        <h2 class="ib-detail-title">IB Detail</h2>
        <button
          type="button"
          class="ib-detail-close"
          aria-label="Close"
          @click="$emit('close')"
        >
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="ib-detail-body">
        <!-- Part 1: Read-only IB info (Risk Review style) -->
        <section class="ib-detail-section ib-detail-info">
          <h3 class="ib-detail-section-title">
            <i class="fas fa-user-tie"></i> Basic Info
          </h3>
          <div class="ib-detail-grid">
            <div class="ib-detail-field">
              <span class="ib-detail-label">IB Name</span>
              <span class="ib-detail-value">{{
                row.ibName || row.companyName || "—"
              }}</span>
            </div>
            <div class="ib-detail-field">
              <span class="ib-detail-label">Email</span>
              <span class="ib-detail-value">{{ row.email || "—" }}</span>
            </div>
            <div class="ib-detail-field">
              <span class="ib-detail-label">Phone</span>
              <span class="ib-detail-value">{{ formatPhone(row.phone) }}</span>
            </div>
            <div class="ib-detail-field">
              <span class="ib-detail-label">Application Date</span>
              <span class="ib-detail-value">{{
                formatDate(row.applicationDate)
              }}</span>
            </div>
            <div class="ib-detail-field">
              <span class="ib-detail-label">Total Clients</span>
              <span class="ib-detail-value">{{ row.totalClients ?? 0 }}</span>
            </div>
            <div class="ib-detail-field">
              <span class="ib-detail-label">Status</span>
              <span class="ib-detail-value"
                ><span
                  class="ib-detail-status"
                  :class="statusClass(row.status)"
                  >{{ row.statusDisplay || formatIbStatus(row.status) }}</span
                ></span
              >
            </div>
            <div class="ib-detail-field">
              <span class="ib-detail-label">IB Type</span>
              <span class="ib-detail-value">{{ row.ibType || "—" }}</span>
            </div>
            <div class="ib-detail-field">
              <span class="ib-detail-label">Group</span>
              <span class="ib-detail-value">{{
                row.groupName ||
                (row.groupId ? `Group #${row.groupId}` : "") ||
                "—"
              }}</span>
            </div>
            <div class="ib-detail-field">
              <span class="ib-detail-label">Tier Level</span>
              <span class="ib-detail-value">{{
                row.tierLevelName || "—"
              }}</span>
            </div>
            <div class="ib-detail-field">
              <span class="ib-detail-label">Rule Name</span>
              <span class="ib-detail-value ib-detail-value--multiline">{{
                row.ruleNames || "—"
              }}</span>
            </div>
            <div class="ib-detail-field">
              <span class="ib-detail-label">Initial Reviewer</span>
              <span class="ib-detail-value">{{
                row.initialReviewerName || "—"
              }}</span>
            </div>
            <div class="ib-detail-field">
              <span class="ib-detail-label">Risk Reviewer</span>
              <span class="ib-detail-value">{{
                row.riskReviewerName || "—"
              }}</span>
            </div>
            <div class="ib-detail-field">
              <span class="ib-detail-label">Final Reviewer</span>
              <span class="ib-detail-value">{{
                row.finalReviewerName || "—"
              }}</span>
            </div>
            <div class="ib-detail-field ib-detail-field--full">
              <span class="ib-detail-label">IB Referral URL</span>
              <span class="ib-detail-value ib-detail-value--url-wrap">
                <template v-if="editingReferralIbId === row.id">
                  <span class="ib-referral-prefix">{{
                    getReferralUrlPrefix(row)
                  }}</span>
                  <input
                    v-model="editingReferralSuffix"
                    type="text"
                    class="ib-referral-suffix-input"
                    placeholder="Suffix"
                    @keydown.enter="saveReferralSuffix(row)"
                    @keydown.esc="cancelEditReferral"
                  />
                  <span class="ib-referral-edit-actions">
                    <button
                      type="button"
                      class="ib-referral-btn ib-referral-btn--copy"
                      @click="saveReferralSuffix(row)"
                    >
                      Save
                    </button>
                    <button
                      type="button"
                      class="ib-referral-btn ib-referral-btn--edit"
                      @click="cancelEditReferral"
                    >
                      Cancel
                    </button>
                  </span>
                  <span v-if="referralSaveError" class="ib-referral-error">{{
                    referralSaveError
                  }}</span>
                </template>
                <template v-else>
                  <span class="ib-detail-value--url">{{
                    row.ibReferralUrl
                  }}</span>
                  <button
                    type="button"
                    class="ib-referral-btn ib-referral-btn--edit"
                    title="Edit suffix only"
                    @click="startEditReferral(row)"
                  >
                    <i class="fas fa-edit"></i> Edit
                  </button>
                  <button
                    type="button"
                    class="ib-referral-btn ib-referral-btn--copy"
                    title="Copy URL"
                    @click="copyReferralUrl(row)"
                  >
                    <i class="fas fa-copy"></i> Copy
                  </button>
                </template>
              </span>
            </div>
          </div>
        </section>

        <!-- Part 2: Relationship Network (Your IB Network style: stats, pan/zoom, expand) -->
        <section class="ib-detail-section ib-detail-network">
          <h3 class="ib-detail-section-title">
            <i class="fas fa-project-diagram"></i> Relationship Network
          </h3>
          <div v-if="networkLoading" class="ib-detail-network-loading">
            <i class="fas fa-spinner fa-spin"></i> Loading network...
          </div>
          <template v-else>
            <div class="network-section-controls">
              <div class="network-search-wrapper">
                <i class="fas fa-search network-search-icon"></i>
                <input
                  type="text"
                  class="network-search-input"
                  v-model="networkSearchQuery"
                  placeholder="Search by name or code..."
                  @keydown.enter="searchNetwork"
                />
              </div>
              <div class="level-selector">
                <label>Expand:</label>
                <select v-model="expandLevel" @change="expandToLevel">
                  <option value="0">None</option>
                  <option value="1">Level 1</option>
                  <option value="2">Level 2</option>
                  <option value="3">Level 3</option>
                  <option value="5">Level 5</option>
                  <option value="10">Level 10</option>
                  <option value="15">Level 15</option>
                  <option value="all">All Levels</option>
                </select>
              </div>
              <button
                type="button"
                class="btn-collapse-all"
                @click="collapseAll"
              >
                <i class="fas fa-compress-alt"></i> Collapse All
              </button>
            </div>
            <div class="network-info-banner">
              <i class="fas fa-info-circle"></i>
              <span
                >Drag to pan • Scroll to zoom • Click [+] to expand nodes •
                Double-click to reset view</span
              >
            </div>
            <div class="network-canvas-wrap">
              <div class="zoom-indicator" v-if="showZoomIndicator">
                <i class="fas fa-search"></i> <span>{{ zoomLevel }}%</span>
              </div>
              <div
                class="network-container"
                ref="networkContainerRef"
                @mousedown="handleMouseDown"
                @mousemove="handleMouseMove"
                @mouseup="handleMouseUp"
                @wheel.prevent="handleWheel"
                @dblclick="resetNetworkView"
              >
                <div
                  class="network-graph"
                  ref="networkGraphRef"
                  :style="{
                    transform: `translate(${panX}px, ${panY}px) scale(${zoom})`,
                  }"
                >
                  <div class="network-branch network-branch--root">
                    <div class="network-node">
                      <div class="node-card tier1">
                        <div class="node-content">
                          <div class="node-avatar">{{ rootInitials }}</div>
                          <div class="node-info">
                            <div class="node-title">
                              {{
                                row.ibName ||
                                row.companyName ||
                                row.ibCode ||
                                "—"
                              }}
                            </div>
                            <div class="node-subtitle">
                              {{ row.ibCode }} • Tier 1
                            </div>
                            <div class="node-badge">
                              <i class="fas fa-crown"></i> Root IB
                            </div>
                          </div>
                        </div>
                        <div class="node-stats">
                          <div class="node-stat">
                            <i class="fas fa-users"></i>
                            <span
                              >{{ networkStats.directClients }} Direct
                              Clients</span
                            >
                          </div>
                          <div class="node-stat">
                            <i class="fas fa-handshake"></i>
                            <span>{{ networkStats.subIbs }} Sub-IBs</span>
                          </div>
                        </div>
                      </div>
                      <div
                        class="expand-btn"
                        :class="{ expanded: expandedNodes.tier1 }"
                        @click.stop="toggleNode('tier1')"
                      >
                        {{ expandedNodes.tier1 ? "−" : "+" }}
                      </div>
                    </div>
                    <div
                      class="network-children"
                      :class="{ expanded: expandedNodes.tier1 }"
                    >
                      <IbDetailNetworkGraphNode
                        v-for="member in networkMembers"
                        :key="member.id"
                        :member="member"
                        :expanded-nodes="expandedNodes"
                        :highlighted-node-id="highlightedNodeId"
                        @toggle="toggleNode"
                      />
                    </div>
                    <div
                      v-if="networkMembers.length === 0 && expandedNodes.tier1"
                      class="network-empty-msg"
                    >
                      No direct members
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="network-summary">
              <div>
                <div class="summary-value">{{ networkStats.totalNetwork }}</div>
                <div class="summary-label">Total Network</div>
              </div>
              <div>
                <div class="summary-value tier2">
                  {{ networkStats.tier2Ibs }}
                </div>
                <div class="summary-label">Level 2 IBs</div>
              </div>
              <div>
                <div class="summary-value tier3">
                  {{ networkStats.tier3Ibs }}
                </div>
                <div class="summary-label">Level 3 IBs</div>
              </div>
              <div>
                <div class="summary-value clients">
                  {{ networkStats.directClients }}
                </div>
                <div class="summary-label">Direct Clients</div>
              </div>
            </div>
          </template>
        </section>
      </div>

      <div class="ib-detail-footer">
        <button
          type="button"
          class="ib-detail-btn ib-detail-btn--primary"
          @click="$emit('close')"
        >
          Close
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from "vue";
import IbDetailNetworkGraphNode from "./IbDetailNetworkGraphNode.vue";
import ibPartnersApi from "@/services/ibPartnersApi";

const props = defineProps({
  row: { type: Object, required: true },
});

const emit = defineEmits(["close", "referral-saved"]);

const networkMembers = ref([]);
const networkLoading = ref(false);
const networkStats = ref({
  totalNetwork: 0,
  directClients: 0,
  subIbs: 0,
  tier2Ibs: 0,
  tier3Ibs: 0,
});
const networkSearchQuery = ref("");
const expandLevel = ref("0");
const expandedNodes = ref({});
const highlightedNodeId = ref(null);
const networkContainerRef = ref(null);
const networkGraphRef = ref(null);
const panX = ref(0);
const panY = ref(0);
const zoom = ref(1.0);
const isDragging = ref(false);
const startX = ref(0);
const startY = ref(0);
const showZoomIndicator = ref(false);
const zoomLevel = ref("100");

const editingReferralIbId = ref(null);
const editingReferralSuffix = ref("");
const referralSaveError = ref("");

function getReferralUrlPrefix(ib) {
  const url = ib?.ibReferralUrl ?? "";
  const lastSlash = url.lastIndexOf("/");
  if (lastSlash === -1) return url;
  return url.slice(0, lastSlash + 1);
}

function getReferralUrlSuffix(ib) {
  const url = ib?.ibReferralUrl ?? "";
  const lastSlash = url.lastIndexOf("/");
  if (lastSlash === -1) return "";
  try {
    return decodeURIComponent(url.slice(lastSlash + 1));
  } catch {
    return url.slice(lastSlash + 1);
  }
}

async function copyReferralUrl(ib) {
  const url = ib?.ibReferralUrl ?? "";
  if (!url) return;
  try {
    await navigator.clipboard.writeText(url);
    alert("URL copied to clipboard.");
  } catch {
    alert("Failed to copy.");
  }
}

function startEditReferral(ib) {
  editingReferralIbId.value = ib.id;
  editingReferralSuffix.value = getReferralUrlSuffix(ib);
  referralSaveError.value = "";
}

function cancelEditReferral() {
  editingReferralIbId.value = null;
  editingReferralSuffix.value = "";
  referralSaveError.value = "";
}

async function saveReferralSuffix(ib) {
  const suffix = (editingReferralSuffix.value ?? "").trim();
  if (!suffix) {
    referralSaveError.value = "Suffix is required.";
    return;
  }
  referralSaveError.value = "";
  try {
    const data = await ibPartnersApi.updateReferralSuffix(ib.id, suffix);
    if (data?.ibReferralUrl) {
      emit("referral-saved", { ibReferralUrl: data.ibReferralUrl, id: ib.id });
    }
    cancelEditReferral();
  } catch (err) {
    const msg =
      err?.response?.data?.message ?? err?.message ?? "Update failed.";
    referralSaveError.value = msg;
  }
}

const rootInitials = computed(() => {
  const name = props.row.ibName || props.row.companyName || "";
  if (!name) return "—";
  const words = name.trim().split(/\s+/);
  if (words.length >= 2) {
    return (
      (words[0][0] || "").toUpperCase() +
      (words[words.length - 1][0] || "").toUpperCase()
    );
  }
  return name.slice(0, 2).toUpperCase() || "—";
});

const formatDate = (dateString) => {
  if (!dateString) return "—";
  const date = new Date(dateString);
  return date.toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
};

const formatPhone = (phone) => {
  if (!phone || typeof phone !== "string") return "—";
  return phone.trim() || "—";
};

const formatIbStatus = (status) => {
  if (!status || typeof status !== "string") return "—";
  const map = {
    pending_initial_review: "Pending Initial Review",
    pending_risk_review: "Pending Risk Review",
    pending_final_review: "Pending Final Review",
    approved: "Approved",
    rejected: "Rejected",
  };
  return map[status] || status;
};

const statusClass = (status) => {
  if (!status) return "";
  if (status.includes("pending")) return "ib-detail-status--pending";
  if (status === "approved") return "ib-detail-status--approved";
  if (status === "rejected") return "ib-detail-status--rejected";
  return "";
};

const toggleNode = (id) => {
  const next = { ...expandedNodes.value };
  next[id] = !next[id];
  expandedNodes.value = next;
};

const expandToLevel = () => {
  if (expandLevel.value === "0" || expandLevel.value === "none") {
    expandedNodes.value = {};
    return;
  }
  expandedNodes.value = {};
  expandedNodes.value.tier1 = true;
  if (expandLevel.value === "all") {
    const expandAll = (members) => {
      (members || []).forEach((member) => {
        if (member.hasChildren) {
          expandedNodes.value[member.id] = true;
          if (member.children && member.children.length > 0) {
            expandAll(member.children);
          }
        }
      });
    };
    expandAll(networkMembers.value);
    return;
  }
  const targetLevel = parseInt(expandLevel.value, 10);
  if (isNaN(targetLevel) || targetLevel <= 0) return;
  const expandToTargetLevel = (members, currentLevel = 1) => {
    if (currentLevel > targetLevel) return;
    (members || []).forEach((member) => {
      if (member.hasChildren && currentLevel < targetLevel) {
        expandedNodes.value[member.id] = true;
        if (member.children && member.children.length > 0) {
          expandToTargetLevel(member.children, currentLevel + 1);
        }
      }
    });
  };
  expandToTargetLevel(networkMembers.value, 1);
};

const collapseAll = () => {
  expandedNodes.value = {};
  expandLevel.value = "0";
};

const searchInMembers = (members, query, path = []) => {
  const results = [];
  const lowerQuery = query.toLowerCase().trim();
  if (!lowerQuery) return results;
  (members || []).forEach((member) => {
    const currentPath = [...path, member.id];
    const name = (member.name || "").toLowerCase();
    const code = (member.code || "").toLowerCase();
    if (name.includes(lowerQuery) || code.includes(lowerQuery)) {
      results.push({ member, path: currentPath });
    }
    if (member.children && member.children.length > 0) {
      results.push(...searchInMembers(member.children, query, currentPath));
    }
  });
  return results;
};

const expandToPath = (path) => {
  const next = { ...expandedNodes.value };
  next.tier1 = true;
  path.forEach((nodeId) => {
    if (nodeId !== "tier1") next[nodeId] = true;
  });
  expandedNodes.value = next;
};

const scrollToNode = (nodeId) => {
  nextTick(() => {
    const el = document.querySelector(`[data-node-id="${nodeId}"]`);
    if (el && networkContainerRef.value) {
      const containerRect = networkContainerRef.value.getBoundingClientRect();
      const nodeRect = el.getBoundingClientRect();
      const relativeX = nodeRect.left - containerRect.left + nodeRect.width / 2;
      const relativeY = nodeRect.top - containerRect.top + nodeRect.height / 2;
      panX.value = containerRect.width / 2 - relativeX;
      panY.value = containerRect.height / 2 - relativeY;
    }
  });
};

const searchNetwork = () => {
  const query = networkSearchQuery.value.trim();
  highlightedNodeId.value = null;
  if (!query) return;
  const results = searchInMembers(networkMembers.value, query);
  if (results.length > 0) {
    const first = results[0];
    expandToPath(first.path);
    highlightedNodeId.value = first.member.id;
    scrollToNode(first.member.id);
  } else {
    // eslint-disable-next-line no-alert
    alert(`No members found matching "${query}"`);
  }
};

const handleMouseDown = (e) => {
  if (e.target.closest(".expand-btn") || e.target.closest(".node-card")) return;
  isDragging.value = true;
  startX.value = e.clientX - panX.value;
  startY.value = e.clientY - panY.value;
  if (networkContainerRef.value)
    networkContainerRef.value.classList.add("dragging");
  e.preventDefault();
};

const handleMouseMove = (e) => {
  if (!isDragging.value) return;
  panX.value = e.clientX - startX.value;
  panY.value = e.clientY - startY.value;
};

const handleMouseUp = () => {
  if (isDragging.value) {
    isDragging.value = false;
    if (networkContainerRef.value)
      networkContainerRef.value.classList.remove("dragging");
  }
};

const handleWheel = (e) => {
  e.preventDefault();
  const delta = e.deltaY > 0 ? -0.1 : 0.1;
  zoom.value = Math.max(0.3, Math.min(3.0, zoom.value + delta));
  zoomLevel.value = String(Math.round(zoom.value * 100));
  showZoomIndicator.value = true;
  setTimeout(() => {
    showZoomIndicator.value = false;
  }, 1000);
};

const resetNetworkView = (e) => {
  if (e.target.closest(".node-card") || e.target.closest(".expand-btn")) return;
  zoom.value = 1.0;
  panX.value = 0;
  panY.value = 0;
  zoomLevel.value = "100";
  showZoomIndicator.value = true;
  setTimeout(() => {
    showZoomIndicator.value = false;
  }, 1000);
};

const loadNetwork = async () => {
  networkLoading.value = true;
  networkMembers.value = [];
  expandedNodes.value = {};
  networkStats.value = {
    totalNetwork: 0,
    directClients: 0,
    subIbs: 0,
    tier2Ibs: 0,
    tier3Ibs: 0,
  };
  try {
    const [statsRes, treeRes] = await Promise.all([
      ibPartnersApi.getNetworkStats(props.row.id),
      ibPartnersApi.getNetwork(props.row.id),
    ]);
    const statsData = statsRes?.data?.data ?? statsRes?.data ?? {};
    networkStats.value = {
      totalNetwork: statsData.totalNetwork ?? 0,
      directClients: statsData.directClients ?? 0,
      subIbs: statsData.subIbs ?? 0,
      tier2Ibs: statsData.tier2Ibs ?? 0,
      tier3Ibs: statsData.tier3Ibs ?? 0,
    };
    const raw = treeRes?.data?.data ?? treeRes?.data;
    networkMembers.value = Array.isArray(raw) ? raw : (raw?.members ?? []);
  } catch (e) {
    console.error("Failed to load network", e);
    networkMembers.value = [];
  } finally {
    networkLoading.value = false;
  }
};

watch(
  () => props.row?.id,
  (id) => {
    if (id) loadNetwork();
  },
  { immediate: true },
);

onMounted(() => {
  document.addEventListener("mousemove", handleMouseMove);
  document.addEventListener("mouseup", handleMouseUp);
});

onUnmounted(() => {
  document.removeEventListener("mousemove", handleMouseMove);
  document.removeEventListener("mouseup", handleMouseUp);
});
</script>

<style scoped>
.ib-detail-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
}

.ib-detail-modal {
  background: var(--color-surface);
  border-radius: var(--radius-xl);
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
  max-width: 900px;
  width: 100%;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.ib-detail-header {
  padding: 20px 24px;
  background: var(--color-brand-solid);
  color: white;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.ib-detail-title {
  margin: 0;
  font-size: 20px;
  font-weight: 700;
}

.ib-detail-close {
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

.ib-detail-close:hover {
  background: rgba(255, 255, 255, 0.3);
}

.ib-detail-body {
  padding: 24px;
  overflow-y: auto;
  flex: 1;
}

.ib-detail-section {
  margin-bottom: 28px;
}

.ib-detail-section:last-of-type {
  margin-bottom: 0;
}

.ib-detail-section-title {
  font-size: 15px;
  font-weight: 600;
  color: var(--color-ink);
  margin: 0 0 14px 0;
  display: flex;
  align-items: center;
  gap: 8px;
}

.ib-detail-section-title i {
  color: var(--color-brand);
}

.ib-detail-info {
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: 20px;
}

.ib-detail-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0;
}

.ib-detail-field {
  padding: 12px 14px;
  border-bottom: 1px solid var(--color-surface-muted);
  border-right: 1px solid var(--color-surface-muted);
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.ib-detail-field:nth-child(3n) {
  border-right: none;
}
.ib-detail-field:nth-last-child(-n + 3) {
  border-bottom: none;
}
.ib-detail-field--full {
  grid-column: 1 / -1;
}

.ib-detail-label {
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--color-muted);
  font-weight: 600;
}

.ib-detail-value {
  font-size: 14px;
  color: var(--color-ink);
  font-weight: 500;
}

.ib-detail-value--multiline {
  white-space: normal;
  word-break: break-word;
}

.ib-detail-value--url-wrap {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}
.ib-detail-value--url {
  word-break: break-all;
  font-family: "Courier New", monospace;
  font-size: 13px;
  color: var(--color-text);
}
.ib-referral-prefix {
  font-family: "Courier New", monospace;
  color: var(--color-text);
  word-break: break-all;
  font-size: 13px;
}
.ib-referral-suffix-input {
  padding: 6px 10px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: 13px;
  min-width: 120px;
}
.ib-referral-suffix-input:focus {
  outline: none;
  border-color: var(--color-brand);
}
.ib-referral-edit-actions {
  display: inline-flex;
  gap: 6px;
}
.ib-referral-error {
  color: var(--color-danger);
  font-size: 12px;
  display: block;
  margin-top: 4px;
}
.ib-referral-btn {
  padding: 8px 16px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition:
    background 0.2s,
    color 0.2s;
}
.ib-referral-btn--copy {
  background: var(--color-brand-solid);
  color: white;
}
.ib-referral-btn--copy:hover {
  background: var(--color-brand-strong);
  color: white;
}
.ib-referral-btn--edit {
  background: var(--color-surface);
  color: var(--color-brand);
  border: 2px solid var(--color-brand);
}
.ib-referral-btn--edit:hover {
  background: var(--color-brand-soft);
  color: var(--color-brand-strong);
  border-color: var(--color-brand-strong);
}

.ib-detail-status {
  display: inline-block;
  padding: 4px 10px;
  border-radius: var(--radius-md);
  font-size: 12px;
  font-weight: 600;
}

.ib-detail-status--pending {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}
.ib-detail-status--approved {
  background: var(--color-success-soft);
  color: var(--color-success);
}
.ib-detail-status--rejected {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.ib-detail-network {
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: 20px;
}

.ib-detail-network-loading {
  padding: 24px;
  text-align: center;
  color: var(--color-brand);
}

.network-section-controls {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
}

.network-search-wrapper {
  position: relative;
  flex: 1;
  min-width: 180px;
}

.network-search-wrapper .network-search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-muted);
  font-size: 14px;
}

.network-search-input {
  width: 100%;
  padding: 8px 12px 8px 36px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
}

.level-selector {
  display: flex;
  align-items: center;
  gap: 8px;
}

.level-selector label {
  font-size: 13px;
  color: var(--color-text);
  white-space: nowrap;
}

.level-selector select {
  padding: 6px 10px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: 13px;
}

.btn-collapse-all {
  padding: 8px 14px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  font-size: 13px;
  cursor: pointer;
  color: var(--color-text);
}

.btn-collapse-all:hover {
  background: var(--color-surface-soft);
  border-color: var(--color-border-strong);
}

.network-info-banner {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  background: var(--color-surface-muted);
  border-radius: var(--radius-md);
  margin-bottom: 12px;
  font-size: 12px;
  color: var(--color-text);
}

.network-info-banner i {
  color: var(--color-brand);
  flex-shrink: 0;
}

.network-canvas-wrap {
  position: relative;
}

.zoom-indicator {
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

.ib-detail-network .ib-detail-section-title {
  margin-bottom: 12px;
}

.network-container {
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

.network-container.dragging {
  cursor: grabbing;
}

.network-graph {
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

.network-branch--root .node-card.tier1 {
  background: var(--color-brand-solid);
  border: 3px solid var(--color-brand-strong);
  color: white;
  min-width: 220px;
  padding: 18px 20px;
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.network-branch--root .node-card.tier1 .node-avatar {
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

.network-branch--root .node-card.tier1 .node-title {
  font-size: 15px;
}

.network-branch--root .node-card.tier1 .node-subtitle {
  opacity: 0.9;
}

.network-branch--root .node-card.tier1 .node-badge {
  background: rgba(255, 255, 255, 0.25);
  padding: 3px 8px;
  border-radius: var(--radius-md);
  font-size: 11px;
}

.network-branch--root .node-stats {
  display: flex;
  gap: 12px;
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px solid rgba(255, 255, 255, 0.25);
  font-size: 11px;
  flex-wrap: wrap;
}

.network-branch--root .node-stat {
  display: flex;
  align-items: center;
  gap: 4px;
}

/* 根节点与下一级之间的 +/- 按钮：与第二层及以下统一（白底、灰边框、展开/悬停紫色） */
.network-branch--root .expand-btn {
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

.network-branch--root .expand-btn:hover,
.network-branch--root .expand-btn.expanded {
  background: var(--color-brand-solid);
  border-color: var(--color-brand);
  color: white;
}

.network-branch--root .network-children {
  display: none;
  flex-direction: column;
  gap: 16px;
  margin-left: 24px;
  padding-left: 20px;
  border-left: 2px solid var(--color-border-strong);
}

.network-branch--root .network-children.expanded {
  display: flex;
}

.network-empty-msg {
  margin-left: 24px;
  padding: 12px;
  color: var(--color-muted);
  font-size: 13px;
}

.network-summary {
  display: flex;
  justify-content: space-around;
  padding: 16px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-md);
  margin-top: 16px;
  flex-wrap: wrap;
  gap: 16px;
}

.network-summary .summary-value {
  font-size: 22px;
  font-weight: 700;
  color: var(--color-ink);
  text-align: center;
}

.network-summary .summary-value.tier2 {
  color: var(--color-success);
}
.network-summary .summary-value.tier3 {
  color: var(--color-warning);
}
.network-summary .summary-value.clients {
  color: var(--color-brand);
}

.network-summary .summary-label {
  font-size: 12px;
  color: var(--color-muted);
  text-align: center;
  margin-top: 2px;
}

.ib-detail-footer {
  padding: 16px 24px;
  border-top: 1px solid var(--color-border);
  background: var(--color-surface);
  display: flex;
  justify-content: flex-end;
}

.ib-detail-btn {
  padding: 10px 24px;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  border: none;
}

.ib-detail-btn--primary {
  background: var(--color-brand-solid);
  color: white;
}

.ib-detail-btn--primary:hover {
  opacity: 0.95;
}

@media (max-width: 640px) {
  .ib-detail-grid {
    grid-template-columns: 1fr;
  }
  .ib-detail-field {
    border-right: none;
  }
  .ib-detail-field:nth-last-child(-n + 3) {
    border-bottom: 1px solid var(--color-surface-muted);
  }
  .ib-detail-field:last-child {
    border-bottom: none;
  }
}
</style>
