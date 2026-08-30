/**
 * Menu Settings Store
 * 管理菜单显示相关的设置状态和缓存
 */

import { defineStore } from "pinia";
import { ref, computed } from "vue";
import menuSettingsApi from "@/services/menuSettingsApi";

export const useMenuSettingsStore = defineStore("menuSettings", () => {
  // 状态
  const settings = ref({
    enable_ib_program: true, // 默认值
  });

  const loading = ref(false);
  const lastFetchTime = ref(null);
  const cacheExpiry = 5 * 60 * 1000; // 5分钟缓存

  // 计算属性
  const isIbProgramEnabled = computed(() => settings.value.enable_ib_program);

  const isCacheValid = computed(() => {
    if (!lastFetchTime.value) return false;
    return Date.now() - lastFetchTime.value < cacheExpiry;
  });

  /**
   * 从localStorage加载缓存
   */
  function loadFromCache() {
    try {
      const cached = localStorage.getItem("menuSettingsCache");
      if (cached) {
        const cacheData = JSON.parse(cached);
        // 检查缓存是否过期
        if (
          cacheData.timestamp &&
          Date.now() - cacheData.timestamp < cacheExpiry
        ) {
          settings.value = cacheData.settings;
          lastFetchTime.value = cacheData.timestamp;
          return true;
        }
      }
    } catch (error) {
      console.error("Failed to load menu settings from cache:", error);
    }
    return false;
  }

  /**
   * 保存到localStorage缓存
   */
  function saveToCache() {
    try {
      const cacheData = {
        settings: settings.value,
        timestamp: Date.now(),
      };
      localStorage.setItem("menuSettingsCache", JSON.stringify(cacheData));
    } catch (error) {
      console.error("Failed to save menu settings to cache:", error);
    }
  }

  /**
   * 清除缓存
   */
  function clearCache() {
    localStorage.removeItem("menuSettingsCache");
    lastFetchTime.value = null;
  }

  /**
   * 加载菜单设置
   * @param {boolean} forceRefresh - 是否强制刷新
   */
  async function loadSettings(forceRefresh = false) {
    // 如果不强制刷新且缓存有效，直接返回
    if (!forceRefresh && isCacheValid.value) {
      return;
    }

    // 如果不强制刷新，先尝试从localStorage加载
    if (!forceRefresh && loadFromCache()) {
      return;
    }

    try {
      loading.value = true;
      const response = await menuSettingsApi.getMenuSettings(forceRefresh);

      if (response.success && response.data) {
        settings.value = { ...settings.value, ...response.data };
        lastFetchTime.value = Date.now();
        saveToCache();
      }
    } catch (error) {
      console.error("Failed to load menu settings:", error);
      // 如果API失败，尝试使用缓存
      if (!loadFromCache()) {
        // 如果缓存也没有，使用默认值
        console.warn("Using default menu settings");
      }
    } finally {
      loading.value = false;
    }
  }

  /**
   * 刷新菜单设置（强制从服务器获取）
   */
  async function refreshSettings() {
    clearCache();
    await loadSettings(true);
  }

  /**
   * 更新设置（本地更新，用于响应式更新）
   */
  function updateSettings(newSettings) {
    settings.value = { ...settings.value, ...newSettings };
    saveToCache();
    lastFetchTime.value = Date.now();
  }

  return {
    // 状态
    settings,
    loading,

    // 计算属性
    isIbProgramEnabled,
    isCacheValid,

    // 方法
    loadSettings,
    refreshSettings,
    updateSettings,
    clearCache,
  };
});
