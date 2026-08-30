import clientApi from "./clientApi";

/**
 * 品牌配置服务
 * 获取应用品牌配置信息（公开接口，无需认证）
 * 使用localStorage缓存，首次加载后使用缓存，通过版本号确保缓存是最新的
 */
const brandingApi = {
  // localStorage 键名
  CACHE_KEY: "brandingConfig",
  VERSION_KEY: "brandingVersion",

  /**
   * 获取品牌配置（带缓存机制）
   * @param {boolean} forceRefresh - 是否强制刷新（忽略缓存）
   * @returns {Promise} 品牌配置信息（不包含_version字段）
   */
  async getBranding(forceRefresh = false) {
    try {
      // 如果不是强制刷新，先检查缓存
      if (!forceRefresh) {
        const cached = this.getCachedBranding();
        if (cached) {
          // 缓存存在，先异步检查版本（不阻塞返回）
          this.checkVersionAsync();
          return cached;
        }
      }

      // 从API加载
      // 如果 clientApi 的 baseURL 包含 index.php?path=，则只需要 /branding
      // 否则需要完整的路径
      const baseURL = clientApi.defaults.baseURL || "";
      const endpoint = baseURL.includes("index.php?path=")
        ? "/branding"
        : "/index.php?path=api/branding";
      const response = await clientApi.get(endpoint);
      const data = response?.data ?? response ?? {};

      // 提取版本号和配置数据
      const version = data._version;
      const branding = { ...data };
      delete branding._version; // 移除版本号字段

      // 保存到缓存
      if (version) {
        this.setCachedBranding(branding, version);
      }

      return branding;
    } catch (error) {
      console.error("Failed to load branding config:", error);

      // 如果API失败，尝试使用缓存
      const cached = this.getCachedBranding();
      if (cached) {
        console.warn("Using cached branding config due to API error");
        return cached;
      }

      // 返回默认值
      return {
        logoText: "CRM",
        companyName: "Trading Platform",
        companyShortName: "Platform",
        teamName: "The Team",
        copyrightText: "Trading Platform",
        supportEmail: "support.demo@gmail.com",
      };
    }
  },

  /**
   * 从缓存获取品牌配置
   * @returns {Object|null} 品牌配置或null
   */
  getCachedBranding() {
    try {
      const cached = localStorage.getItem(this.CACHE_KEY);
      const version = localStorage.getItem(this.VERSION_KEY);

      if (cached && version) {
        return JSON.parse(cached);
      }
    } catch (error) {
      console.error("Failed to read cached branding:", error);
    }
    return null;
  },

  /**
   * 保存品牌配置到缓存
   * @param {Object} branding - 品牌配置
   * @param {string} version - 版本号
   */
  setCachedBranding(branding, version) {
    try {
      localStorage.setItem(this.CACHE_KEY, JSON.stringify(branding));
      localStorage.setItem(this.VERSION_KEY, version);
    } catch (error) {
      console.error("Failed to cache branding:", error);
    }
  },

  /**
   * 异步检查版本号（后台检查，不阻塞）
   * 如果版本号变化，更新缓存
   */
  async checkVersionAsync() {
    try {
      const cachedVersion = localStorage.getItem(this.VERSION_KEY);
      if (!cachedVersion) return;

      // 后台检查版本
      // 如果 clientApi 的 baseURL 包含 index.php?path=，则只需要 /branding
      // 否则需要完整的路径
      const baseURL = clientApi.defaults.baseURL || "";
      const endpoint = baseURL.includes("index.php?path=")
        ? "/branding"
        : "/index.php?path=api/branding";
      const response = await clientApi.get(endpoint);
      const data = response?.data ?? response ?? {};
      const currentVersion = data._version;

      // 如果版本号不同，更新缓存
      if (currentVersion && currentVersion !== cachedVersion) {
        const branding = { ...data };
        delete branding._version;
        this.setCachedBranding(branding, currentVersion);
        console.log("Branding config updated from server");
      }
    } catch (error) {
      // 静默失败，不影响使用
      console.debug("Version check failed:", error);
    }
  },

  /**
   * 清除缓存
   */
  clearCache() {
    try {
      localStorage.removeItem(this.CACHE_KEY);
      localStorage.removeItem(this.VERSION_KEY);
    } catch (error) {
      console.error("Failed to clear branding cache:", error);
    }
  },
};

export default brandingApi;
