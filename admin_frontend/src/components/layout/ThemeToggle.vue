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
  color: var(--color-text);
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  cursor: pointer;
  flex-shrink: 0;
  transition: all 0.3s ease;
}

.theme-toggle:hover {
  color: var(--color-brand);
  background: var(--color-brand-soft);
  border-color: var(--color-brand);
}

.theme-toggle:focus-visible {
  outline: 2px solid var(--color-brand);
  outline-offset: 2px;
}

.theme-toggle i {
  font-size: 18px;
}
</style>
