<template>
  <button
    type="button"
    class="theme-toggle"
    :aria-label="toggleLabel"
    :title="toggleLabel"
    :aria-pressed="isDark"
    @click="toggleTheme"
  >
    <i :class="isDark ? 'fas fa-sun' : 'fas fa-moon'" aria-hidden="true"></i>
  </button>
</template>

<script setup>
import { computed } from "vue";
import { useTheme } from "@/composables/useTheme";

const { isDark, toggleTheme } = useTheme();
const toggleLabel = computed(() =>
  isDark.value ? "Switch to light mode" : "Switch to dark mode",
);
</script>

<style scoped>
.theme-toggle {
  display: inline-grid;
  place-items: center;
  width: 44px;
  min-width: 44px;
  height: 44px;
  padding: 0;
  color: var(--color-ink);
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  cursor: pointer;
  transition:
    background-color 0.2s ease,
    border-color 0.2s ease,
    transform 0.15s ease;
}

.theme-toggle:hover {
  color: var(--color-brand);
  background: var(--color-brand-soft);
  border-color: var(--color-brand);
}

.theme-toggle:active {
  transform: scale(0.94);
}

.theme-toggle i {
  font-size: 18px;
}
</style>
