import { defineStore } from "pinia";
import { ref, computed } from "vue";
import clientAuthService from "@/services/clientAuthService";

export const useClientAuthStore = defineStore("clientAuth", () => {
  const user = ref(null);
  const token = ref(localStorage.getItem("clientToken") || null);

  const isAuthenticated = computed(() => !!token.value && !!user.value);

  const userInitials = computed(() => {
    if (!user.value) return "U";
    const firstName = user.value.firstName || "";
    const lastName = user.value.lastName || "";
    if (firstName && lastName) {
      return `${firstName[0]}${lastName[0]}`.toUpperCase();
    }
    return (user.value.email || "U")[0].toUpperCase();
  });

  async function register(data) {
    try {
      const response = await clientAuthService.register(data);
      return { success: true, data: response.data };
    } catch (error) {
      return {
        success: false,
        error:
          error.response?.data?.message ||
          error.message ||
          "Registration failed",
      };
    }
  }

  async function login(credentials) {
    try {
      const response = await clientAuthService.login(credentials);
      const loginData = response.data || response;

      token.value = loginData.token;
      user.value = loginData.user;

      localStorage.setItem("clientToken", loginData.token);
      if (credentials.rememberMe) {
        localStorage.setItem("clientRememberMe", "true");
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
      await clientAuthService.logout();
    } catch (error) {
      console.error("Logout error:", error);
    } finally {
      user.value = null;
      token.value = null;
      localStorage.removeItem("clientToken");
      localStorage.removeItem("clientRememberMe");
    }
  }

  async function fetchUser() {
    try {
      const response = await clientAuthService.getProfile();
      const userData = response.data || response;
      user.value = userData;
      return true;
    } catch (error) {
      console.error("Failed to fetch user:", error);
      // Clear invalid token and user data
      user.value = null;
      token.value = null;
      localStorage.removeItem("clientToken");
      localStorage.removeItem("clientRememberMe");
      return false;
    }
  }

  async function verifyEmail(verificationToken) {
    try {
      const response = await clientAuthService.verifyEmail(verificationToken);
      const data = response.data || response;

      // 如果后端返回了 token 和用户信息，说明要自动登录
      if (data.autoLogin && data.token && data.user) {
        token.value = data.token;
        user.value = data.user;
        localStorage.setItem("clientToken", data.token);
        localStorage.setItem("clientRememberMe", "true");

        return {
          success: true,
          autoLogin: true,
          message: data.message,
        };
      }

      return { success: true, message: data.message };
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.message || "Verification failed",
      };
    }
  }

  async function resendVerification(email) {
    try {
      await clientAuthService.resendVerification(email);
      return { success: true };
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.message || "Failed to resend verification",
      };
    }
  }

  async function forgotPassword(email, options = {}) {
    try {
      const response = await clientAuthService.forgotPassword(email, options);
      return { success: true, data: response.data };
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.message || "Failed to send reset link",
      };
    }
  }

  async function resetPassword(data) {
    try {
      await clientAuthService.resetPassword(data);
      return { success: true };
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.message || "Password reset failed",
      };
    }
  }

  async function changePassword(data) {
    try {
      await clientAuthService.changePassword(data);
      return { success: true };
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.message || "Password change failed",
      };
    }
  }

  return {
    user,
    token,
    isAuthenticated,
    userInitials,
    register,
    login,
    logout,
    fetchUser,
    verifyEmail,
    resendVerification,
    forgotPassword,
    resetPassword,
    changePassword,
  };
});
