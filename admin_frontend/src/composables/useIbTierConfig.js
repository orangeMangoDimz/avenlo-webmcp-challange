import { ref } from "vue";
import ibSettingsApi from "@/services/ibSettingsApi";

const maxTierLevelCount = ref(5);
let loadPromise = null;

function parseMaxTierLevelCount(raw) {
  const n = parseInt(raw, 10);
  return Number.isFinite(n) && n >= 1 ? n : 5;
}

export function useIbTierConfig() {
  async function loadMaxTierLevelCount(force = false) {
    if (!force && loadPromise) {
      return loadPromise;
    }
    loadPromise = (async () => {
      try {
        const response = await ibSettingsApi.getSetting("max_tier_level_count");
        const setting = response?.data ?? response;
        maxTierLevelCount.value = parseMaxTierLevelCount(setting?.settingValue);
      } catch {
        maxTierLevelCount.value = 5;
      }
      return maxTierLevelCount.value;
    })();
    return loadPromise;
  }

  function setMaxTierLevelCountLocal(n) {
    maxTierLevelCount.value = parseMaxTierLevelCount(n);
  }

  return {
    maxTierLevelCount,
    loadMaxTierLevelCount,
    setMaxTierLevelCountLocal,
  };
}
