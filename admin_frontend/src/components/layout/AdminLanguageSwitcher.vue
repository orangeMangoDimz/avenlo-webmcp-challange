<template>
  <div class="language-switcher" @click.stop="toggleLanguageDropdown">
    <AdminLangFlagIcon
      class="language-trigger-flag"
      :language-code="languageStore.currentLanguage"
    />
    <span class="language-text">{{ languageStore.currentLanguageName }}</span>
    <span class="language-arrow">▼</span>
    <div v-if="showLanguageDropdown" class="language-dropdown">
      <div
        v-for="lang in languageStore.enabledLanguages"
        :key="lang.languageCode"
        class="language-option"
        :class="{ active: languageStore.currentLanguage === lang.languageCode }"
        @click.stop="changeLanguage(lang.languageCode)"
      >
        <AdminLangFlagIcon :language-code="lang.languageCode" />
        <span class="line1">{{ lang.languageName }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from "vue";
import { useLanguageStore } from "@/stores/language";
import AdminLangFlagIcon from "@/components/layout/AdminLangFlagIcon.vue";

const languageStore = useLanguageStore();
const showLanguageDropdown = ref(false);

const toggleLanguageDropdown = () => {
  showLanguageDropdown.value = !showLanguageDropdown.value;
};

const changeLanguage = async (langCode) => {
  showLanguageDropdown.value = false;
  await languageStore.changeLanguage(langCode);
};

const handleClickOutside = (e) => {
  if (!e.target.closest(".language-switcher")) {
    showLanguageDropdown.value = false;
  }
};

onMounted(() => {
  document.addEventListener("click", handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener("click", handleClickOutside);
});
</script>

<style scoped>
.language-switcher {
  position: relative;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  cursor: pointer;
  font-size: 14px;
  color: var(--color-ink);
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
  user-select: none;
}

.language-switcher:hover {
  border-color: var(--color-border-strong);
  background: var(--color-surface-soft);
}

.language-trigger-flag {
  flex-shrink: 0;
}

.language-text {
  font-weight: 500;
}

.language-arrow {
  font-size: 10px;
  color: var(--color-muted);
}

.language-dropdown {
  position: absolute;
  top: calc(100% + 6px);
  right: 0;
  min-width: 180px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
  z-index: 2000;
  overflow: hidden;
}

.language-option {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  cursor: pointer;
  font-size: 14px;
  color: var(--color-ink);
}

.language-option:hover {
  background: var(--color-surface-soft);
}

.language-option.active {
  background: var(--color-surface-muted);
  font-weight: 600;
}
</style>
