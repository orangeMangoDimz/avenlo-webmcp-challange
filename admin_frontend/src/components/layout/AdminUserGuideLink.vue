<template>
  <a
    class="user-guide-btn"
    :href="guideUrl"
    target="_blank"
    rel="noopener noreferrer"
    :aria-label="guideLabel"
  >
    <i class="fas fa-book-open"></i>
    <span class="user-guide-tooltip" role="tooltip">{{ guideLabel }}</span>
  </a>
</template>

<script setup>
import { computed } from "vue";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t, languageStore } = useAdminI18n();

const guideLabel = computed(() =>
  t("common_crmAdminUserGuide", "CRM 管理后台使用指南"),
);

const guideUrl = computed(() => {
  const file =
    languageStore.currentLanguage === "zh"
      ? "Finance_Pro_CRM_Admin_User_Guide_Chinese.pdf"
      : "Finance_Pro_CRM_Admin_User_Guide.pdf";
  return `${import.meta.env.BASE_URL}docs/${file}`;
});
</script>

<style scoped>
.user-guide-btn {
  position: relative;
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  width: 44px;
  height: 44px;
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.3s ease;
  font-size: 18px;
  color: var(--color-text);
  text-decoration: none;
  flex-shrink: 0;
}

.user-guide-btn:hover {
  border-color: var(--color-brand);
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.user-guide-tooltip {
  position: absolute;
  top: calc(100% + 10px);
  right: 0;
  padding: 8px 12px;
  background: var(--color-ink);
  color: #fff;
  font-size: 13px;
  font-weight: 500;
  line-height: 1.4;
  white-space: nowrap;
  border-radius: var(--radius-md);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  opacity: 0;
  visibility: hidden;
  transform: translateY(-4px);
  transition:
    opacity 0.2s ease,
    transform 0.2s ease,
    visibility 0.2s ease;
  pointer-events: none;
  z-index: 2100;
}

.user-guide-tooltip::after {
  content: "";
  position: absolute;
  bottom: 100%;
  right: 14px;
  border: 6px solid transparent;
  border-bottom-color: var(--color-ink);
}

.user-guide-btn:hover .user-guide-tooltip,
.user-guide-btn:focus-visible .user-guide-tooltip {
  opacity: 1;
  visibility: visible;
  transform: translateY(0);
}
</style>
