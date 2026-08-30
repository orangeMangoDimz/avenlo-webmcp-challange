/**
 * Menu Settings API Service
 * 菜单设置相关API接口
 */

import api from "./api";

/**
 * 获取菜单设置
 * @param {boolean} forceRefresh - 是否强制刷新缓存
 */
export const getMenuSettings = (forceRefresh = false) => {
  const params = {};
  if (forceRefresh) {
    // 如果强制刷新，先调用refresh接口
    return api.post("/menu-settings/refresh").then(() => {
      return api.get("/menu-settings");
    });
  }
  return api.get("/menu-settings");
};

/**
 * 强制刷新菜单设置缓存
 */
export const refreshMenuSettings = () => {
  return api.post("/menu-settings/refresh");
};

export default {
  getMenuSettings,
  refreshMenuSettings,
};
