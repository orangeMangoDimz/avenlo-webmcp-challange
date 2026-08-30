const LANGUAGE_TO_FLAG = {
  en: "us",
  zh: "cn",
};

/**
 * Resolve the country flag used by the client language controls.
 * Unknown language packs intentionally fall back to English, matching the
 * admin language switcher instead of rendering a generic globe icon.
 */
export const languageFlagCode = (languageCode) => {
  const baseCode = String(languageCode || "")
    .trim()
    .toLowerCase()
    .split(/[-_]/)[0];

  return LANGUAGE_TO_FLAG[baseCode] || "us";
};
