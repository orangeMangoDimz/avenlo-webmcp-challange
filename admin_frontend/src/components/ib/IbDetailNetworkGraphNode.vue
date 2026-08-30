<template>
  <div class="network-branch">
    <div class="network-node">
      <component
        :is="detailLink ? 'router-link' : 'div'"
        :to="detailLink"
        :class="[
          'node-card',
          nodeCardClass,
          {
            highlighted: highlightedNodeId === member.id,
            linkable: !!detailLink,
          },
        ]"
        :data-node-id="member.id"
      >
        <div class="node-content">
          <div class="node-avatar">{{ member.initials }}</div>
          <div class="node-info">
            <div class="node-title">{{ member.name }}</div>
            <div class="node-subtitle" v-if="member.code">
              {{ member.code }}
            </div>
            <div
              class="node-alias"
              v-if="member.adminAlias"
              :title="t('ibDetail_label_adminAlias', 'Admin Alias')"
            >
              <i class="fas fa-tag"></i> {{ member.adminAlias }}
            </div>
            <div class="node-badge" v-if="member.type === 'ib'">
              <i class="fas fa-handshake"></i>
              {{ t("ibDetail_network_badgeSubIb") }}
            </div>
            <div class="node-badge client-badge" v-else>
              <i class="fas fa-user"></i>
              {{ t("ibDetail_network_badgeClient") }}
            </div>
          </div>
        </div>
      </component>
      <div
        v-if="member.hasChildren"
        class="expand-btn"
        :class="{ expanded: expandedNodes[member.id] }"
        @click.stop="$emit('toggle', member.id)"
      >
        {{ expandedNodes[member.id] ? "−" : "+" }}
      </div>
    </div>
    <div
      v-if="member.hasChildren && member.children && member.children.length"
      class="network-children"
      :class="{ expanded: expandedNodes[member.id] }"
    >
      <IbDetailNetworkGraphNode
        v-for="child in member.children"
        :key="child.id"
        :member="child"
        :expanded-nodes="expandedNodes"
        :highlighted-node-id="highlightedNodeId"
        @toggle="$emit('toggle', $event)"
      />
    </div>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t } = useAdminI18n();

const props = defineProps({
  member: { type: Object, required: true },
  expandedNodes: { type: Object, default: () => ({}) },
  highlightedNodeId: { type: [String, Number], default: null },
});

defineEmits(["toggle"]);

const nodeCardClass = computed(() => {
  if (props.member.type === "client") return "client";
  return "tier2";
});

/**
 * 节点对应的客户详情页链接：Sub-IB 用其绑定的 clientUsers.id 并直接打开 IB tab，
 * Client 从节点 id（client-<clientUsers.id>）解析。缺少 id 时不可点击。
 */
const detailLink = computed(() => {
  const member = props.member;
  const clientUserId = Number(
    member.clientUserId ?? String(member.id ?? "").replace(/^client-/, ""),
  );
  if (!Number.isInteger(clientUserId) || clientUserId <= 0) return null;
  const query = { id: String(clientUserId) };
  if (member.type === "ib") query.tab = "ib-referral";
  return { name: "client-detail", query };
});
</script>

<style scoped>
.network-branch {
  display: flex;
  flex-direction: row;
  align-items: flex-start;
  gap: 20px;
  position: relative;
  margin-bottom: 8px;
}

.network-branch::before {
  content: "";
  position: absolute;
  left: -30px;
  top: 24px;
  width: 30px;
  height: 2px;
  background: var(--color-border-strong);
}

.network-branch:first-child::before {
  display: none;
}

.network-node {
  position: relative;
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 16px;
}

.node-card {
  background: var(--color-surface);
  border: 3px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: 14px 18px;
  min-width: 180px;
  max-width: 260px;
  text-align: left;
  transition: all 0.3s ease;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  display: block;
  color: inherit;
  text-decoration: none;
}

.node-card.linkable {
  cursor: pointer;
}

.node-card.linkable:hover .node-title {
  text-decoration: underline;
}

.node-card:hover {
  transform: translateX(4px);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
  border-color: var(--color-brand);
}

.node-card.tier2 {
  background: linear-gradient(
    135deg,
    var(--color-success) 0%,
    var(--color-success) 100%
  );
  border-color: var(--color-success);
  color: white;
}

.node-card.client {
  background: var(--color-surface-soft);
  border-color: var(--color-border);
}

.node-card.client:hover {
  background: var(--color-brand-soft);
  border-color: #a5b4fc;
}

.node-card.highlighted {
  border-color: var(--color-warning) !important;
  border-width: 4px !important;
  box-shadow: 0 0 20px rgba(245, 158, 11, 0.5) !important;
}

.node-content {
  display: flex;
  align-items: center;
  gap: 12px;
}

.node-avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: 700;
  flex-shrink: 0;
}

.node-card.tier2 .node-avatar {
  background: rgba(255, 255, 255, 0.25);
  color: white;
  border: 2px solid rgba(255, 255, 255, 0.4);
}

.node-card.client .node-avatar {
  background: var(--color-border);
  color: var(--color-text);
  border: 2px solid var(--color-border-strong);
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
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.node-subtitle {
  font-size: 12px;
  opacity: 0.9;
  margin-bottom: 4px;
}

.node-card.client .node-subtitle {
  color: var(--color-muted);
}

.node-alias {
  font-size: 12px;
  font-weight: 600;
  margin-bottom: 4px;
  word-break: break-word;
}

.node-card.client .node-alias {
  color: var(--color-text);
}

.node-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: var(--radius-md);
  font-size: 10px;
  font-weight: 600;
}

.node-card.tier2 .node-badge {
  background: rgba(255, 255, 255, 0.25);
}

.node-badge.client-badge {
  background: var(--color-border);
  color: var(--color-text);
}

.expand-btn {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--color-surface);
  border: 3px solid var(--color-border-strong);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.3s ease;
  font-size: 16px;
  font-weight: 700;
  color: var(--color-text);
  flex-shrink: 0;
  z-index: 10;
}

.expand-btn:hover {
  background: var(--color-brand-solid);
  border-color: var(--color-brand);
  color: white;
  transform: scale(1.1);
}

.expand-btn.expanded {
  background: var(--color-brand-solid);
  border-color: var(--color-brand);
  color: white;
}

.node-card.tier2 .expand-btn {
  border-color: rgba(255, 255, 255, 0.4);
  background: rgba(255, 255, 255, 0.15);
  color: white;
}

.node-card.tier2 .expand-btn:hover,
.node-card.tier2 .expand-btn.expanded {
  background: rgba(255, 255, 255, 0.3);
  border-color: white;
}

.network-children {
  display: none;
  flex-direction: column;
  gap: 16px;
  margin-left: 40px;
  position: relative;
  padding-left: 20px;
  border-left: 2px solid var(--color-border-strong);
}

.network-children.expanded {
  display: flex;
}
</style>
