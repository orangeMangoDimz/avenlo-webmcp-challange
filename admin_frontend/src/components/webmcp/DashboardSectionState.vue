<template>
  <div v-if="status && status !== 'ready'" class="dashboard-section-state" :class="`is-${status}`">
    <i :class="icon" aria-hidden="true"></i>
    <strong>{{ title }}</strong>
    <span>{{ message }}</span>
  </div>
</template>

<script setup>
import { computed } from "vue";

const props = defineProps({
  status: { type: String, default: "loading" },
  label: { type: String, default: "section" },
});
const icon = computed(() => ({
  loading: "fas fa-circle-notch fa-spin",
  restricted: "fas fa-lock",
  error: "fas fa-triangle-exclamation",
})[props.status] || "fas fa-circle-info");
const title = computed(() => ({
  loading: `Loading ${props.label}`,
  restricted: "Outside your permission scope",
  error: `${props.label.charAt(0).toUpperCase()}${props.label.slice(1)} unavailable`,
})[props.status] || "Section unavailable");
const message = computed(() => props.status === "restricted"
  ? "Protected values remain hidden."
  : props.status === "error"
    ? "Refresh to try this section again."
    : "Reading live, scoped records…");
</script>

<style scoped>
.dashboard-section-state {
  display: grid;
  min-height: 235px;
  place-items: center;
  align-content: center;
  gap: 8px;
  color: var(--color-muted);
  text-align: center;
}
.dashboard-section-state i { color: var(--color-brand); font-size: 22px; }
.dashboard-section-state.is-error i { color: var(--color-danger); }
.dashboard-section-state strong { color: var(--color-ink); font-size: 13px; }
.dashboard-section-state span { font-size: 11px; }
</style>
