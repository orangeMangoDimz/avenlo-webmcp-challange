<template>
  <article class="operations-metric-card" :class="[`is-${tone}`, `is-${status}`]">
    <div class="metric-card-rail" aria-hidden="true"></div>
    <div class="metric-card-heading">
      <span class="metric-card-icon" aria-hidden="true"><i :class="icon"></i></span>
      <span v-if="status === 'ready'" class="metric-card-status">Live</span>
      <span v-else-if="status === 'restricted'" class="metric-card-status is-locked">
        <i class="fas fa-lock" aria-hidden="true"></i> Scoped
      </span>
    </div>

    <template v-if="status === 'loading'">
      <div class="metric-card-skeleton is-title"></div>
      <div class="metric-card-skeleton is-value"></div>
      <div class="metric-card-skeleton is-detail"></div>
    </template>
    <template v-else-if="status === 'restricted'">
      <h2>{{ title }}</h2>
      <strong class="metric-card-restricted">Outside your permission scope</strong>
      <p>Protected records remain hidden.</p>
    </template>
    <template v-else-if="status === 'error'">
      <h2>{{ title }}</h2>
      <strong class="metric-card-restricted">Temporarily unavailable</strong>
      <p>{{ detail || "Refresh to try this section again." }}</p>
    </template>
    <template v-else>
      <h2>{{ title }}</h2>
      <strong class="metric-card-value">{{ value }}</strong>
      <p>{{ detail }}</p>
    </template>

    <div v-if="status === 'ready'" class="metric-card-actions">
      <button v-if="canInvestigate" type="button" @click="$emit('investigate')">
        Investigate
      </button>
      <button v-if="canOpen" type="button" @click="$emit('open')">Open</button>
      <button v-if="canExport" type="button" @click="$emit('export')">Export</button>
    </div>
  </article>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
  icon: { type: String, default: "fas fa-circle" },
  tone: { type: String, default: "brand" },
  status: { type: String, default: "loading" },
  value: { type: String, default: "—" },
  detail: { type: String, default: "" },
  canInvestigate: { type: Boolean, default: true },
  canOpen: { type: Boolean, default: true },
  canExport: { type: Boolean, default: false },
});

defineEmits(["investigate", "open", "export"]);
</script>

<style scoped>
.operations-metric-card {
  position: relative;
  display: flex;
  flex-direction: column;
  min-width: 0;
  min-height: 214px;
  overflow: hidden;
  padding: 20px 20px 16px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 8px;
  box-shadow: 0 12px 28px rgba(15, 23, 42, 0.055);
}

.metric-card-rail {
  position: absolute;
  inset: 0 auto 0 0;
  width: 4px;
  background: var(--metric-color, var(--color-brand));
}

.operations-metric-card.is-success { --metric-color: var(--color-success); }
.operations-metric-card.is-warning { --metric-color: var(--color-warning); }
.operations-metric-card.is-danger { --metric-color: var(--color-danger); }
.operations-metric-card.is-info { --metric-color: var(--color-info); }
.operations-metric-card.is-restricted,
.operations-metric-card.is-error { --metric-color: var(--color-border-strong); }

.metric-card-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 20px;
}

.metric-card-icon {
  display: grid;
  width: 36px;
  height: 36px;
  place-items: center;
  color: var(--metric-color, var(--color-brand));
  background: color-mix(in srgb, var(--metric-color, var(--color-brand)) 12%, transparent);
  border-radius: 6px;
}

.metric-card-status {
  color: var(--color-success);
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.09em;
  text-transform: uppercase;
}

.metric-card-status.is-locked { color: var(--color-muted); }

h2 {
  margin: 0;
  color: var(--color-muted);
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.08em;
  line-height: 1.25;
  min-height: 30px;
  text-transform: uppercase;
}

.metric-card-value,
.metric-card-restricted {
  display: block;
  margin-top: 9px;
  min-height: 34px;
  color: var(--color-ink-strong);
  font-size: clamp(22px, 2.6vw, 30px);
  line-height: 1.1;
  letter-spacing: -0.035em;
}

.metric-card-restricted {
  max-width: 210px;
  color: var(--color-muted);
  font-size: 15px;
  line-height: 1.35;
  letter-spacing: 0;
}

p {
  min-height: 38px;
  margin: 8px 0 0;
  color: var(--color-muted);
  font-size: 12px;
  line-height: 1.5;
}

.metric-card-actions {
  display: flex;
  gap: 5px;
  margin: auto -6px -4px;
  padding-top: 12px;
  border-top: 1px solid var(--color-border);
}

.metric-card-actions button {
  padding: 5px 7px;
  color: var(--color-brand);
  background: transparent;
  border: 0;
  border-radius: 4px;
  font: inherit;
  font-size: 12px;
  font-weight: 750;
  cursor: pointer;
}

.metric-card-actions button:hover { background: var(--color-brand-soft); }
.metric-card-actions button:focus-visible { outline: 2px solid var(--color-focus-ring); }

.metric-card-skeleton {
  background: linear-gradient(90deg, var(--color-surface-muted), var(--color-surface-soft), var(--color-surface-muted));
  background-size: 200% 100%;
  border-radius: 4px;
  animation: metric-shimmer 1.4s infinite;
}
.metric-card-skeleton.is-title { width: 54%; height: 12px; }
.metric-card-skeleton.is-value { width: 68%; height: 30px; margin-top: 15px; }
.metric-card-skeleton.is-detail { width: 82%; height: 12px; margin-top: 13px; }

@keyframes metric-shimmer { to { background-position: -200% 0; } }

@media (prefers-reduced-motion: reduce) {
  .metric-card-skeleton { animation: none; }
}
</style>
