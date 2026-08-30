import { defineStore } from "pinia";
import { ref, computed } from "vue";

/** Admin UI: English + 简体中文 only; independent from client portal (clientLanguage). */
const STORAGE_KEY = "adminLanguage";

const ADMIN_LOCALES = [
  { languageCode: "en", languageName: "English", flagEmoji: "🇺🇸" },
  { languageCode: "zh", languageName: "简体中文", flagEmoji: "🇨🇳" },
];

const publicAssetUrl = (filename) => import.meta.env.BASE_URL + filename;

function packUrlForCode(code) {
  const c = code === "zh-CN" ? "zh" : code;
  const filename =
    c === "en" || !c
      ? "language-pack-template.json"
      : "language-pack-zh-template.json";
  return `${import.meta.env.BASE_URL}${filename}`;
}

function normalizeAdminLang(code) {
  if (!code || code === "en") return "en";
  if (String(code).toLowerCase().startsWith("zh")) return "zh";
  return "en";
}

/** BCP 47 tag for <html lang> and native controls (e.g. input[type=date] picker format). */
export function adminLocaleBcp47(code) {
  const c = normalizeAdminLang(code);
  return c === "zh" ? "zh-CN" : "en-US";
}

function syncDocumentHtmlLang(code) {
  if (typeof document === "undefined") return;
  document.documentElement.lang = adminLocaleBcp47(code);
}

export const useLanguageStore = defineStore("adminLanguage", () => {
  const currentLanguage = ref("en");
  const translations = ref({});
  const loading = ref(false);

  const enabledLanguages = computed(() => ADMIN_LOCALES);

  const currentLanguageName = computed(() => {
    const lang = ADMIN_LOCALES.find(
      (l) => l.languageCode === currentLanguage.value,
    );
    return lang ? lang.languageName : "English";
  });

  const currentLanguageFlag = computed(() => {
    const lang = ADMIN_LOCALES.find(
      (l) => l.languageCode === currentLanguage.value,
    );
    return lang ? lang.flagEmoji : "🇺🇸";
  });

  const t = (key, fallback = "") => {
    if (translations.value[key]) {
      return translations.value[key];
    }
    if (fallback !== undefined && fallback !== null && fallback !== "") {
      return fallback;
    }
    return key;
  };

  const loadAdminTranslations = async (langCode) => {
    const code = normalizeAdminLang(langCode);
    loading.value = true;
    try {
      const url = packUrlForCode(code);
      const res = await fetch(url);
      if (!res.ok) {
        return;
      }
      const data = await res.json();
      translations.value = data.translations || {};
    } catch (err) {
      console.error("Failed to load admin language pack:", err);
    } finally {
      loading.value = false;
    }
  };

  const changeLanguage = async (langCode) => {
    const code = normalizeAdminLang(langCode);
    currentLanguage.value = code;
    localStorage.setItem(STORAGE_KEY, code);
    syncDocumentHtmlLang(code);
    await loadAdminTranslations(code);
  };

  const initLanguage = async () => {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved) {
      currentLanguage.value = normalizeAdminLang(saved);
    } else {
      currentLanguage.value = "en";
    }
    syncDocumentHtmlLang(currentLanguage.value);
    await loadAdminTranslations(currentLanguage.value);
  };

  return {
    currentLanguage,
    translations,
    loading,
    enabledLanguages,
    currentLanguageName,
    currentLanguageFlag,
    t,
    loadAdminTranslations,
    changeLanguage,
    initLanguage,
  };
});
