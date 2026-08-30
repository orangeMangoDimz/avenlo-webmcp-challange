import { defineStore } from "pinia";
import { ref, computed } from "vue";
import authService from "@/services/authService";

export const useAuthStore = defineStore("auth", () => {
  const user = ref(null);
  const token = ref(localStorage.getItem("token") || null);
  const permissions = ref([]);

  const isAuthenticated = computed(() => !!token.value && !!user.value);

  const userInitials = computed(() => {
    if (!user.value || !user.value.fullName) return "AD";
    const names = user.value.fullName.split(" ");
    if (names.length >= 2) {
      return `${names[0][0]}${names[names.length - 1][0]}`.toUpperCase();
    }
    return user.value.fullName.substring(0, 2).toUpperCase();
  });

  async function login(credentials) {
    try {
      const response = await authService.login(credentials);

      // 后端返回结构: { success: true, data: { token, user }, message: '...' }
      const loginData = response.data || response;

      token.value = loginData.token;
      user.value = loginData.user;
      permissions.value =
        loginData.permissions || loginData.user?.permissions || [];

      localStorage.setItem("token", loginData.token);
      if (credentials.rememberMe) {
        localStorage.setItem("rememberMe", "true");
      }

      return { success: true };
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.message || error.message || "Login failed",
      };
    }
  }

  async function logout() {
    try {
      await authService.logout();
    } catch (error) {
      console.error("Logout error:", error);
    } finally {
      user.value = null;
      token.value = null;
      permissions.value = [];
      localStorage.removeItem("token");
      localStorage.removeItem("rememberMe");
    }
  }

  async function fetchUser() {
    try {
      const response = await authService.getProfile();
      const userData = response.data || response;

      user.value = userData.user || userData;
      permissions.value =
        userData.permissions || userData.user?.permissions || [];
      return true;
    } catch (error) {
      console.error("Failed to fetch user:", error);
      // 只清理本地状态，不要调用logout API（避免循环调用）
      user.value = null;
      token.value = null;
      permissions.value = [];
      localStorage.removeItem("token");
      localStorage.removeItem("rememberMe");
      return false;
    }
  }

  async function updateProfile(data) {
    try {
      if (!user.value?.id) {
        throw new Error("User not authenticated");
      }

      const response = await authService.updateProfile(user.value.id, data);
      const userData = response.data || response;

      user.value = { ...user.value, ...(userData.user || userData) };
      return { success: true };
    } catch (error) {
      // API 拦截器会将响应数据作为错误抛出，所以 error 可能是响应数据对象
      // 需要同时检查 error.message（响应数据）和 error.response?.data?.message（axios 错误）
      const errorMessage =
        error.message || error.response?.data?.message || "Update failed";
      return {
        success: false,
        error: errorMessage,
      };
    }
  }

  async function changePassword(data) {
    try {
      await authService.changePassword(data);
      return { success: true };
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.message || "Password change failed",
      };
    }
  }

  async function forgotPassword(email) {
    try {
      await authService.requestPasswordReset(email);
      return { success: true };
    } catch (error) {
      return {
        success: false,
        error:
          error.response?.data?.message ||
          error.message ||
          "Failed to send reset link",
      };
    }
  }

  async function resetPassword(token, password, confirmPassword) {
    try {
      await authService.resetPassword(token, password, confirmPassword);
      return { success: true };
    } catch (error) {
      return {
        success: false,
        error:
          error.response?.data?.message ||
          error.message ||
          "Password reset failed",
      };
    }
  }

  function hasPermission(permissionKey) {
    // 超级管理员（roleId == 1）默认拥有所有权限
    if (user.value && user.value.roleId == 1) {
      return true;
    }

    // 其他角色按正常权限检查
    return permissions.value.some((p) => p.permissionKey === permissionKey);
  }

  return {
    user,
    token,
    permissions,
    isAuthenticated,
    userInitials,
    login,
    logout,
    fetchUser,
    updateProfile,
    changePassword,
    forgotPassword,
    resetPassword,
    hasPermission,
  };
});
