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
  width: 38px;
  min-width: 38px;
  height: 38px;
  padding: 0;
  color: #fff;
  background: transparent;
  border: 1px solid #475467;
  border-radius: 50%;
  cursor: pointer;
  transition:
    background-color 0.2s ease,
    border-color 0.2s ease,
    transform 0.15s ease;
}

.theme-toggle:hover {
  background: var(--color-sidebar-raised);
  border-color: var(--color-accent);
}

.theme-toggle:active {
  transform: scale(0.94);
}

.theme-toggle:focus-visible {
  outline: 2px solid var(--color-accent);
  outline-offset: 2px;
}

.theme-toggle i {
  font-size: 14px;
}
</style>
