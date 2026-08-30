<template>
  <div class="ib-detail-node-branch">
    <div class="ib-detail-node">
      <span
        v-if="member.hasChildren"
        class="ib-detail-node-toggle"
        @click="$emit('toggle', member.id)"
      >
        {{ expandedNodes[member.id] ? "−" : "+" }}
      </span>
      <span
        v-else
        class="ib-detail-node-toggle ib-detail-node-toggle--empty"
      ></span>
      <div
        :class="[
          'ib-detail-node-card',
          member.type === 'ib'
            ? 'ib-detail-node-card--ib'
            : 'ib-detail-node-card--client',
        ]"
      >
        <div class="ib-detail-node-avatar">{{ member.initials }}</div>
        <div class="ib-detail-node-info">
          <div class="ib-detail-node-name">{{ member.name }}</div>
          <div class="ib-detail-node-code">{{ member.code }}</div>
          <span v-if="member.type === 'ib'" class="ib-detail-node-badge"
            >Sub-IB</span
          >
          <span v-else class="ib-detail-node-badge ib-detail-node-badge--client"
            >Client</span
          >
        </div>
      </div>
    </div>
    <div
      v-if="member.hasChildren && expandedNodes[member.id]"
      class="ib-detail-node-children ib-detail-node-children--nested"
    >
      <IbDetailNetworkNode
        v-for="child in member.children"
        :key="child.id"
        :member="child"
        :expanded-nodes="expandedNodes"
        @toggle="$emit('toggle', $event)"
      />
    </div>
  </div>
</template>

<script setup>
defineProps({
  member: { type: Object, required: true },
  expandedNodes: { type: Object, default: () => ({}) },
});

defineEmits(["toggle"]);
</script>

<style scoped>
.ib-detail-node-branch {
  margin-bottom: 8px;
}

.ib-detail-node {
  display: flex;
  align-items: flex-start;
  gap: 6px;
  margin-bottom: 6px;
}

.ib-detail-node-toggle {
  flex-shrink: 0;
  width: 22px;
  height: 22px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: 700;
  color: var(--color-brand);
  cursor: pointer;
  border-radius: 4px;
  user-select: none;
  margin-top: 14px;
}

.ib-detail-node-toggle:hover:not(.ib-detail-node-toggle--empty) {
  background: var(--color-brand-soft);
}

.ib-detail-node-toggle--empty {
  visibility: hidden;
  cursor: default;
}

.ib-detail-node-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
  flex: 1;
}

.ib-detail-node-card--ib {
  background: var(--color-warning-soft);
  border-color: #fcd34d;
}

.ib-detail-node-card--client {
  background: var(--color-success-soft);
  border-color: #6ee7b7;
}

.ib-detail-node-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: var(--color-brand-solid);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 14px;
  flex-shrink: 0;
}

.ib-detail-node-card--ib .ib-detail-node-avatar {
  background: var(--color-warning-solid);
}

.ib-detail-node-card--client .ib-detail-node-avatar {
  background: var(--color-success-solid);
}

.ib-detail-node-info {
  flex: 1;
  min-width: 0;
}

.ib-detail-node-name {
  font-weight: 600;
  color: var(--color-ink);
  font-size: 14px;
}

.ib-detail-node-code {
  font-size: 14px;
  color: var(--color-muted);
  font-family: "Courier New", monospace;
  margin-top: 2px;
}

.ib-detail-node-badge {
  display: inline-block;
  margin-top: 4px;
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 14px;
  font-weight: 600;
  background: var(--color-brand-solid);
  color: white;
}

.ib-detail-node-badge--client {
  background: var(--color-success-solid);
}

.ib-detail-node-children--nested {
  margin-left: 28px;
  margin-top: 6px;
  padding-left: 12px;
  border-left: 2px solid var(--color-border);
}
</style>
