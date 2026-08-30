<template>
  <div class="ib-detail-row">
    <!-- Part 1: Basic Info -->
    <section v-if="!networkOnly" class="ib-detail-section ib-detail-info">
      <h3 class="ib-detail-section-title">
        <i class="fas fa-user-tie"></i> {{ t("ibDetail_section_basic") }}
      </h3>
      <div class="ib-detail-grid">
        <div class="ib-detail-field">
          <span class="ib-detail-label">{{ t("ibList_col_ibName") }}</span>
          <span class="ib-detail-value">{{
            row.ibName || row.companyName || "—"
          }}</span>
        </div>
        <div class="ib-detail-field">
          <span class="ib-detail-label">{{
            t("ibDetail_label_clientAlias", "Client Alias")
          }}</span>
          <!-- clientAlias 由 IB 本人在客户端 "Name IB" 入口维护，后台只展示不允许编辑 -->
          <span class="ib-detail-value">{{ row.clientAlias || "—" }}</span>
        </div>
        <div class="ib-detail-field">
          <span class="ib-detail-label">{{
            t("ibDetail_label_adminAlias", "Admin Alias")
          }}</span>
          <span class="ib-detail-value ib-detail-value--alias">
            <template
              v-if="
                editingAliasIbId === row.id &&
                editingAliasField === 'adminAlias'
              "
            >
              <input
                v-model="editingAliasValue"
                type="text"
                class="ib-alias-input"
                :placeholder="
                  t('ibDetail_aliasPlaceholder', 'Alias for admin display')
                "
                maxlength="200"
                @keydown.enter="saveAlias(row)"
                @keydown.esc="cancelEditAlias"
              />
              <span class="ib-referral-edit-actions">
                <button
                  type="button"
                  class="ib-referral-btn ib-referral-btn--copy"
                  @click="saveAlias(row)"
                >
                  {{ t("ibDetail_btnSave") }}
                </button>
                <button
                  type="button"
                  class="ib-referral-btn ib-referral-btn--edit"
                  @click="cancelEditAlias"
                >
                  {{ t("ibDetail_btnCancel") }}
                </button>
              </span>
              <span v-if="aliasSaveError" class="ib-referral-error">{{
                aliasSaveError
              }}</span>
            </template>
            <template v-else>
              <span class="ib-alias-text">{{ row.adminAlias || "—" }}</span>
              <button
                type="button"
                class="ib-referral-btn ib-referral-btn--edit"
                :title="t('ibDetail_titleEditAlias', 'Edit alias')"
                @click="startEditAlias(row, 'adminAlias')"
              >
                <i class="fas fa-edit"></i> {{ t("ibDetail_btnEdit") }}
              </button>
            </template>
          </span>
        </div>
        <div class="ib-detail-field">
          <span class="ib-detail-label">{{ t("ibList_col_email") }}</span>
          <span class="ib-detail-value">{{ row.email || "—" }}</span>
        </div>
        <div class="ib-detail-field">
          <span class="ib-detail-label">{{ t("ibList_col_phone") }}</span>
          <span class="ib-detail-value">{{ formatPhone(row.phone) }}</span>
        </div>
        <div class="ib-detail-field">
          <span class="ib-detail-label">{{
            t("ibList_col_applicationDate")
          }}</span>
          <span class="ib-detail-value">{{
            formatDate(row.applicationDate)
          }}</span>
        </div>
        <div class="ib-detail-field">
          <span class="ib-detail-label">{{
            t("ibList_col_totalClients")
          }}</span>
          <span class="ib-detail-value">{{ row.totalClients ?? 0 }}</span>
        </div>
        <div class="ib-detail-field">
          <span class="ib-detail-label">{{ t("ibList_col_status") }}</span>
          <span class="ib-detail-value">
            <span class="ib-detail-status" :class="statusClass(row.status)">{{
              row.statusDisplay || formatIbStatus(row.status)
            }}</span>
          </span>
        </div>
        <div class="ib-detail-field">
          <span class="ib-detail-label">{{ t("ibList_col_ibType") }}</span>
          <span class="ib-detail-value">{{ row.ibType || "—" }}</span>
        </div>
        <div class="ib-detail-field">
          <span class="ib-detail-label">{{ t("ibDetail_label_group") }}</span>
          <span class="ib-detail-value">{{
            row.groupName ||
            (row.groupId
              ? tParams("ibDetail_groupNum", "Group #{n}", { n: row.groupId })
              : "") ||
            "—"
          }}</span>
        </div>
        <div class="ib-detail-field">
          <span class="ib-detail-label">{{ t("ibList_col_tierLevel") }}</span>
          <span class="ib-detail-value">{{ row.tierLevelName || "—" }}</span>
        </div>
        <div class="ib-detail-field">
          <span class="ib-detail-label">{{ t("ibList_col_ruleName") }}</span>
          <span class="ib-detail-value ib-detail-value--multiline">{{
            row.ruleNames || "—"
          }}</span>
        </div>
        <div class="ib-detail-field">
          <span class="ib-detail-label">{{
            t("ibList_col_initialReviewer")
          }}</span>
          <span class="ib-detail-value">{{
            row.initialReviewerName || "—"
          }}</span>
        </div>
        <div class="ib-detail-field">
          <span class="ib-detail-label">{{
            t("ibList_col_riskReviewer")
          }}</span>
          <span class="ib-detail-value">{{ row.riskReviewerName || "—" }}</span>
        </div>
        <div class="ib-detail-field">
          <span class="ib-detail-label">{{
            t("ibList_col_finalReviewer")
          }}</span>
          <span class="ib-detail-value">{{
            row.finalReviewerName || "—"
          }}</span>
        </div>
        <div class="ib-detail-field ib-detail-field--full">
          <span class="ib-detail-label">{{
            t("ibDetail_label_ibReferralUrl")
          }}</span>
          <span class="ib-detail-value ib-detail-value--url-wrap">
            <template v-if="editingReferralIbId === row.id">
              <span class="ib-referral-prefix">{{
                getReferralUrlPrefix(row)
              }}</span>
              <input
                v-model="editingReferralSuffix"
                type="text"
                class="ib-referral-suffix-input"
                :placeholder="t('ibDetail_referralSuffixPlaceholder')"
                @keydown.enter="saveReferralSuffix(row)"
                @keydown.esc="cancelEditReferral"
              />
              <span class="ib-referral-edit-actions">
                <button
                  type="button"
                  class="ib-referral-btn ib-referral-btn--copy"
                  @click="saveReferralSuffix(row)"
                >
                  {{ t("ibDetail_btnSave") }}
                </button>
                <button
                  type="button"
                  class="ib-referral-btn ib-referral-btn--edit"
                  @click="cancelEditReferral"
                >
                  {{ t("ibDetail_btnCancel") }}
                </button>
              </span>
              <span v-if="referralSaveError" class="ib-referral-error">{{
                referralSaveError
              }}</span>
            </template>
            <template v-else>
              <span class="ib-detail-value--url">{{ row.ibReferralUrl }}</span>
              <button
                type="button"
                class="ib-referral-btn ib-referral-btn--edit"
                :title="t('ibDetail_titleEditSuffix')"
                @click="startEditReferral(row)"
              >
                <i class="fas fa-edit"></i> {{ t("ibDetail_btnEdit") }}
              </button>
              <button
                type="button"
                class="ib-referral-btn ib-referral-btn--copy"
                :title="t('ibDetail_titleCopyUrl')"
                @click="copyReferralUrl(row)"
              >
                <i class="fas fa-copy"></i> {{ t("ibDetail_btnCopy") }}
              </button>
            </template>
          </span>
        </div>
      </div>
    </section>

    <!-- Part 2: Relationship Network -->
    <section class="ib-detail-section ib-detail-network">
      <h3 class="ib-detail-section-title">
        <i class="fas fa-project-diagram"></i>
        {{ t("ibDetail_section_network") }}
      </h3>
      <div v-if="networkLoading" class="ib-detail-network-loading">
        <i class="fas fa-spinner fa-spin"></i>
        {{ t("ibDetail_networkLoading") }}
      </div>
      <template v-else>
        <div class="network-section-controls">
          <div class="network-search-wrapper">
            <i class="fas fa-search network-search-icon"></i>
            <input
              type="text"
              class="network-search-input"
              v-model="networkSearchQuery"
              :placeholder="t('ibDetail_networkSearchPlaceholder')"
              @keydown.enter="searchNetwork"
            />
          </div>
          <div class="level-selector">
            <label>{{ t("ibDetail_expandLabel") }}</label>
            <select v-model="expandLevel" @change="expandToLevel">
              <option value="0">{{ t("ibDetail_expand_none") }}</option>
              <option value="1">
                {{ tParams("ibDetail_expand_level", "Level {n}", { n: 1 }) }}
              </option>
              <option value="2">
                {{ tParams("ibDetail_expand_level", "Level {n}", { n: 2 }) }}
              </option>
              <option value="3">
                {{ tParams("ibDetail_expand_level", "Level {n}", { n: 3 }) }}
              </option>
              <option value="5">
                {{ tParams("ibDetail_expand_level", "Level {n}", { n: 5 }) }}
              </option>
              <option value="10">
                {{ tParams("ibDetail_expand_level", "Level {n}", { n: 10 }) }}
              </option>
              <option value="15">
                {{ tParams("ibDetail_expand_level", "Level {n}", { n: 15 }) }}
              </option>
              <option value="all">{{ t("ibDetail_expand_all") }}</option>
            </select>
          </div>
          <button type="button" class="btn-collapse-all" @click="collapseAll">
            <i class="fas fa-compress-alt"></i>
            {{ t("ibDetail_btnCollapseAll") }}
          </button>
        </div>
        <div class="network-info-banner">
          <i class="fas fa-info-circle"></i>
          <span>{{ t("ibDetail_networkHint") }}</span>
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
                  <component
                    :is="rootDetailLink ? 'router-link' : 'div'"
                    :to="rootDetailLink"
                    :class="[
                      'node-card',
                      'tier1',
                      { linkable: !!rootDetailLink },
                    ]"
                  >
                    <div class="node-content">
                      <div class="node-avatar">{{ rootInitials }}</div>
                      <div class="node-info">
                        <div class="node-title">
                          {{
                            row.ibName || row.companyName || row.ibCode || "—"
                          }}
                        </div>
                        <div class="node-subtitle">
                          {{ row.ibCode }} • {{ t("ibDetail_nodeTier1") }}
                        </div>
                        <div
                          class="node-alias"
                          v-if="row.adminAlias"
                          :title="t('ibDetail_label_adminAlias', 'Admin Alias')"
                        >
                          <i class="fas fa-tag"></i> {{ row.adminAlias }}
                        </div>
                        <div class="node-badge">
                          <i class="fas fa-crown"></i>
                          {{ t("ibDetail_badgeRootIb") }}
                        </div>
                      </div>
                    </div>
                    <div class="node-stats">
                      <div class="node-stat">
                        <i class="fas fa-users"></i>
                        <span>{{
                          tParams(
                            "ibDetail_statDirectClients",
                            "{n} Direct Clients",
                            { n: networkStats.directClients },
                          )
                        }}</span>
                      </div>
                      <div class="node-stat">
                        <i class="fas fa-handshake"></i>
                        <span>{{
                          tParams("ibDetail_statSubIbs", "{n} Sub-IBs", {
                            n: networkStats.subIbs,
                          })
                        }}</span>
                      </div>
                    </div>
                  </component>
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
                  {{ t("ibDetail_networkEmpty") }}
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="network-summary">
          <div>
            <div class="summary-value">{{ networkStats.totalNetwork }}</div>
            <div class="summary-label">
              {{ t("ibDetail_summaryTotalNetwork") }}
            </div>
          </div>
          <div>
            <div class="summary-value tier2">{{ networkStats.tier2Ibs }}</div>
            <div class="summary-label">{{ t("ibDetail_summaryLevel2") }}</div>
          </div>
          <div>
            <div class="summary-value tier3">{{ networkStats.tier3Ibs }}</div>
            <div class="summary-label">{{ t("ibDetail_summaryLevel3") }}</div>
          </div>
          <div>
            <div class="summary-value clients">
              {{ networkStats.directClients }}
            </div>
            <div class="summary-label">
              {{ t("ibDetail_summaryDirectClients") }}
            </div>
          </div>
        </div>
      </template>
    </section>
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from "vue";
import IbDetailNetworkGraphNode from "./IbDetailNetworkGraphNode.vue";
import ibPartnersApi from "@/services/ibPartnersApi";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { translateApiErrorMessage } from "@/i18n/adminI18nBridge";
import { getSubModuleKey } from "@/config/operationLogPages";

const ibListLogSubModule = getSubModuleKey("page_ib_list");

const { t, tParams, languageStore } = useAdminI18n();

const props = defineProps({
  row: { type: Object, required: true },
  networkOnly: { type: Boolean, default: false },
  networkMembersOverride: { type: Array, default: null },
  networkStatsOverride: { type: Object, default: null },
});

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

// 别名 inline 编辑：同一时刻只允许编辑一个字段，编辑时清空已有编辑态避免互相覆盖
const editingAliasIbId = ref(null);
const editingAliasField = ref(null); // 'clientAlias' | 'adminAlias'
const editingAliasValue = ref("");
const aliasSaveError = ref("");

function startEditAlias(ib, field) {
  editingAliasIbId.value = ib.id;
  editingAliasField.value = field;
  editingAliasValue.value = ib[field] ?? "";
  aliasSaveError.value = "";
}

function cancelEditAlias() {
  editingAliasIbId.value = null;
  editingAliasField.value = null;
  editingAliasValue.value = "";
  aliasSaveError.value = "";
}

async function saveAlias(ib) {
  const field = editingAliasField.value;
  if (!field) return;
  // 允许清空：后端 PUT 接收 null 时表示清除别名
  const raw = (editingAliasValue.value ?? "").trim();
  const newValue = raw === "" ? null : raw;
  // 跟当前值一致就直接关闭，避免触发 "No changes detected"
  if ((ib[field] ?? null) === newValue) {
    cancelEditAlias();
    return;
  }
  aliasSaveError.value = "";
  try {
    const resp = await ibPartnersApi.updateIbPartner(
      ib.id,
      { [field]: newValue },
      { logSubModuleKey: ibListLogSubModule },
    );
    if (resp && resp.success === false) {
      throw new Error(resp.message || t("ibDetail_updateFailed"));
    }
    ib[field] = newValue;
    cancelEditAlias();
  } catch (err) {
    const data = err?.response?.data ?? err;
    const rawMsg = data?.message ?? err?.message ?? t("ibDetail_updateFailed");
    aliasSaveError.value = translateApiErrorMessage(data?.errorCode, rawMsg);
  }
}

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
    alert(t("ibDetail_copyOk"));
  } catch {
    alert(t("ibDetail_copyFailed"));
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
    referralSaveError.value = t("ibDetail_errSuffixRequired");
    return;
  }
  referralSaveError.value = "";
  try {
    const data = await ibPartnersApi.updateReferralSuffix(ib.id, suffix, {
      logSubModuleKey: ibListLogSubModule,
    });
    if (data?.ibReferralUrl) {
      ib.ibReferralUrl = data.ibReferralUrl;
    }
    cancelEditReferral();
  } catch (err) {
    const data = err?.response?.data ?? err;
    const rawMsg = data?.message ?? err?.message ?? t("ibDetail_updateFailed");
    referralSaveError.value = translateApiErrorMessage(data?.errorCode, rawMsg);
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

/**
 * Root IB node link: opens the bound client detail page on the IB Referral tab.
 * Null when the IB has no bound clientUsers.id, which keeps the card non-clickable.
 */
const rootDetailLink = computed(() => {
  const clientUserId = Number(props.row?.userId);
  if (!Number.isInteger(clientUserId) || clientUserId <= 0) return null;
  return {
    name: "client-detail",
    query: { id: String(clientUserId), tab: "ib-referral" },
  };
});

const formatDate = (dateString) => {
  if (!dateString) return "—";
  const date = new Date(dateString);
  const loc = languageStore.currentLanguage === "zh" ? "zh-CN" : "en-US";
  return date.toLocaleDateString(loc, {
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
    pending_initial_review: "ibList_status_pending_initial_review",
    pending_risk_review: "ibList_status_pending_risk_review",
    pending_final_review: "ibList_status_pending_final_review",
    approved: "ibList_status_approved",
    rejected: "ibList_status_rejected",
  };
  const key = map[status];
  return key ? t(key) : status;
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
    const alias = (member.adminAlias || "").toLowerCase();
    if (
      name.includes(lowerQuery) ||
      code.includes(lowerQuery) ||
      alias.includes(lowerQuery)
    ) {
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
    alert(
      tParams("ibDetail_searchNoMatch", 'No members found matching "{query}"', {
        query,
      }),
    );
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

const applyNetworkOverride = () => {
  networkMembers.value = Array.isArray(props.networkMembersOverride)
    ? props.networkMembersOverride
    : [];
  networkStats.value = {
    totalNetwork:
      props.networkStatsOverride?.totalNetwork ?? networkMembers.value.length,
    directClients: props.networkStatsOverride?.directClients ?? 0,
    subIbs: props.networkStatsOverride?.subIbs ?? 0,
    tier2Ibs: props.networkStatsOverride?.tier2Ibs ?? 0,
    tier3Ibs: props.networkStatsOverride?.tier3Ibs ?? 0,
  };
  const nextExpanded = { tier1: true };
  const expandAllOverrideNodes = (members) => {
    (members || []).forEach((member) => {
      if (member.hasChildren) {
        nextExpanded[member.id] = true;
        expandAllOverrideNodes(member.children);
      }
    });
  };
  expandAllOverrideNodes(networkMembers.value);
  expandedNodes.value = nextExpanded;
  expandLevel.value = "all";
  networkLoading.value = false;
};

const loadNetwork = async () => {
  if (props.networkMembersOverride) {
    applyNetworkOverride();
    return;
  }
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

watch(
  () => [props.networkMembersOverride, props.networkStatsOverride],
  () => {
    if (props.networkMembersOverride) applyNetworkOverride();
  },
  { deep: true },
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
.ib-detail-row {
  display: flex;
  flex-direction: column;
  gap: 20px;
  width: 100%;
  max-width: 100%;
}

.ib-detail-section {
  flex: none;
  width: 100%;
  min-width: 0;
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
  width: 100%;
  box-sizing: border-box;
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
  border-right: none;
}

.ib-detail-label {
  font-size: 14px;
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

.ib-detail-status {
  display: inline-block;
  padding: 4px 10px;
  border-radius: var(--radius-md);
  font-size: 14px;
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
  width: 100%;
  box-sizing: border-box;
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
  font-size: 14px;
  color: var(--color-text);
  white-space: nowrap;
}

.level-selector select {
  padding: 6px 10px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: 14px;
}

.btn-collapse-all {
  padding: 8px 14px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  font-size: 14px;
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
  font-size: 14px;
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
  font-size: 14px;
  font-weight: 600;
  z-index: 10;
  pointer-events: none;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
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
  display: block;
  text-decoration: none;
}

.network-branch--root .node-card.tier1.linkable {
  cursor: pointer;
}

.network-branch--root .node-card.tier1.linkable:hover {
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
}

.network-branch--root .node-card.tier1.linkable:hover .node-title {
  text-decoration: underline;
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
  font-size: 14px;
}

.network-branch--root .node-stats {
  display: flex;
  gap: 12px;
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px solid rgba(255, 255, 255, 0.25);
  font-size: 14px;
  flex-wrap: wrap;
}

.network-branch--root .node-stat {
  display: flex;
  align-items: center;
  gap: 4px;
}

.network-branch--root .expand-btn {
  width: 32px;
  height: 32px;
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
  font-size: 14px;
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
  font-size: 14px;
  color: var(--color-muted);
  text-align: center;
  margin-top: 2px;
}

.ib-detail-network .ib-detail-section-title {
  margin-bottom: 12px;
}

.node-content {
  display: flex;
  align-items: center;
  gap: 12px;
}

.node-info {
  flex: 1;
  min-width: 0;
}

.node-title {
  font-size: 14px;
  font-weight: 700;
  margin-bottom: 2px;
  line-height: 1.3;
}

.node-subtitle {
  font-size: 14px;
  opacity: 0.9;
  margin-bottom: 4px;
}

.node-alias {
  font-size: 14px;
  font-weight: 600;
  margin-bottom: 4px;
  word-break: break-word;
}

.node-badge {
  display: inline-block;
  font-size: 14px;
  font-weight: 600;
}

.ib-detail-value--url-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.ib-detail-value--url {
  font-family: "Courier New", monospace;
  font-size: 14px;
  word-break: break-all;
  color: var(--color-brand);
}
.ib-referral-prefix {
  font-family: "Courier New", monospace;
  color: var(--color-text);
  word-break: break-all;
  font-size: 14px;
}
.ib-referral-suffix-input {
  padding: 6px 10px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: 14px;
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
  font-size: 14px;
  display: block;
  margin-top: 4px;
}
.ib-referral-btn {
  padding: 8px 16px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 14px;
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

.ib-detail-value--alias {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.ib-alias-text {
  word-break: break-word;
}
.ib-alias-input {
  padding: 6px 10px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: 14px;
  min-width: 160px;
}
.ib-alias-input:focus {
  outline: none;
  border-color: var(--color-brand);
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
