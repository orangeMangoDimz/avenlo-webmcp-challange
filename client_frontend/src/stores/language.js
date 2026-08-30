import { defineStore } from "pinia";
import { ref, computed } from "vue";
import loginSettingsService from "@/services/loginSettingsService";

export const useLanguageStore = defineStore("language", () => {
  // State
  const currentLanguage = ref(null);
  const translations = ref({});
  const enabledLanguages = ref([]);
  const loading = ref(false);

  // Computed
  const currentLanguageName = computed(() => {
    const lang = enabledLanguages.value.find(
      (l) => l.languageCode === currentLanguage.value,
    );
    return lang ? lang.languageName : "English";
  });

  const currentLanguageFlag = computed(() => {
    const lang = enabledLanguages.value.find(
      (l) => l.languageCode === currentLanguage.value,
    );
    return lang ? lang.flagEmoji : "🇺🇸";
  });

  // Translation function with fallback support
  const t = (key, fallback = "") => {
    // 如果有翻译值，返回翻译
    if (translations.value[key]) {
      return translations.value[key];
    }
    // 如果提供了 fallback，返回 fallback
    if (fallback) {
      return fallback;
    }
    // 否则返回 key 本身（用于调试）
    return key;
  };

  const detectLanguages = async () => {
    try {
      const response = await loginSettingsService.getIpDetectionLanguage();
      if (response.success && response.data && response.data["lang"]) {
        localStorage.setItem("clientLanguage", response.data["lang"]);
        currentLanguage.value = response.data["lang"];
      }
    } catch (error) {
      console.error("Failed to detect language:", error);
    }
  };

  // Actions
  const loadEnabledLanguages = async () => {
    try {
      const response = await loginSettingsService.getEnabledLanguagePacks();
      if (response.success && response.data && Array.isArray(response.data)) {
        enabledLanguages.value = response.data;

        // 如果当前语言不在启用列表中，设置为第一个启用的语言
        if (enabledLanguages.value.length > 0) {
          let currentLangEnabled = null;

          if (currentLanguage.value !== null) {
            currentLangEnabled = enabledLanguages.value.find(
              (lang) => lang.languageCode === currentLanguage.value,
            );
          }

          if (!currentLangEnabled) {
            const defaultLang = enabledLanguages.value.find(
              (lang) => lang.isDefault,
            );

            currentLanguage.value = defaultLang
              ? defaultLang.languageCode
              : enabledLanguages.value[0].languageCode;
          }
        }
      }
    } catch (err) {
      console.error("Failed to load enabled languages:", err);
    }
  };

  /**
   * 根据当前选择的语言，从前端配置文件加载所有前台文案（登录页、侧边栏、各页面）。
   * 这是唯一的翻译来源：以前登录页还会额外请求后端语言包，但结果总是被本方法覆盖，
   * 而后端接口每次都要传输 50KB+ 的翻译内容，在高延迟链路上代价很高。
   */
  const getClientPortalPackPath = (code) => {
    const baseUrl = import.meta.env.BASE_URL;
    if (code === "en") return baseUrl + "language-pack-template.json";
    return baseUrl + "language-pack-" + code + "-template.json";
  };

  const loadClientPortalTranslations = async () => {
    const langCode = currentLanguage.value;
    if (!langCode) return;

    loading.value = true;
    try {
      let res = await fetch(getClientPortalPackPath(langCode));
      // 后台可以启用没有对应静态语言包的语言，此时退回英文，避免残留上一个语言的文案
      if (!res.ok && langCode !== "en") {
        res = await fetch(getClientPortalPackPath("en"));
      }
      if (!res.ok) {
        return;
      }
      const data = await res.json();
      translations.value = data.translations || {};
    } catch (err) {
      console.error("Failed to load client portal translations:", err);
    } finally {
      loading.value = false;
    }
  };

  const changeLanguage = async (langCode) => {
    currentLanguage.value = langCode;

    // 保存到 localStorage
    localStorage.setItem("clientLanguage", langCode);

    // 加载翻译（用 public 下的静态语言包，见 loadClientPortalTranslations 说明）
    await loadClientPortalTranslations();
  };

  const initLanguage = async () => {
    // 1. 从 localStorage 恢复语言设置
    const savedLanguage = localStorage.getItem("clientLanguage");
    if (savedLanguage) {
      currentLanguage.value = savedLanguage;
    } else {
      await detectLanguages();
    }

    // 2. 加载启用的语言列表（可能会把 currentLanguage 修正为默认语言）
    await loadEnabledLanguages();

    // 3. 加载当前语言的翻译。
    //    这里不再调用后端 loadTranslations()：所有调用方随后都会用 public 下的静态
    //    语言包覆盖 translations，后端那次请求的结果从未被使用，纯粹是无谓的往返。
    await loadClientPortalTranslations();
  };

  return {
    // State
    currentLanguage,
    translations,
    enabledLanguages,
    loading,

    // Computed
    currentLanguageName,
    currentLanguageFlag,

    // Methods
    t,
    loadEnabledLanguages,
    loadClientPortalTranslations,
    changeLanguage,
    initLanguage,
  };
});
