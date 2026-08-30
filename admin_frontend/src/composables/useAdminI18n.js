import { useLanguageStore } from "@/stores/language";

export function useAdminI18n() {
  const languageStore = useLanguageStore();
  const t = (key, fallback = "") => languageStore.t(key, fallback);
  /** Replace {name} in translated string with params.name */
  const tParams = (key, fallback, params = {}) => {
    let s = t(key, fallback);
    Object.keys(params).forEach((k) => {
      s = s.replace(new RegExp(`\\{${k}\\}`, "g"), String(params[k]));
    });
    return s;
  };
  return { t, tParams, languageStore };
}
