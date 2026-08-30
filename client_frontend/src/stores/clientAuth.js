import { defineStore } from "pinia";
import { ref, computed } from "vue";
import clientAuthService from "@/services/clientAuthService";
import ibInvitationApi from "@/services/ibInvitationApi";

/** 仅当非预览入口页时才从 localStorage 读取 token，避免预览窗口沿用同源下的正常登录态 */
function getInitialToken() {
  if (typeof window === "undefined") return null;
  const hash = (window.location.hash || "").toLowerCase();
  if (hash.includes("/preview")) return null;
  return localStorage.getItem("clientToken") || null;
}

export const useClientAuthStore = defineStore("clientAuth", () => {
  const user = ref(null);
  const token = ref(getInitialToken());
  const kycStatus = ref(null);
  // IB状态信息
  const ibStatus = ref(null);
  /** View as client 预览模式：仅内存态，不写 localStorage */
  const isPreviewMode = ref(false);
  const previewToken = ref(null);

  const isAuthenticated = computed(
    () =>
      (!!token.value && !!user.value) || (isPreviewMode.value && !!user.value),
  );

  // KYC 状态相关计算属性
  // 已通过 KYC 的判定以 user.kycStatus 为权威 —— 这是审批/反审批写回的字段，
  // 不会被"用户提交了一条新 draft"反向翻掉。
  // submissionStatus 反映的是最新一条 submission 的生命周期，approved 用户若存在 post-approval
  // 的 draft，这里会是 'draft'，不能因此判用户未通过（否则 /app/kyc 会把已通过用户带进表单流程，
  // 进而再 createNewSubmission，形成雪球）。
  const isKycApproved = computed(() => {
    if (user.value?.kycStatus === "approved") return true;
    if (kycStatus.value?.isApproved === true) return true;
    if (kycStatus.value?.submissionStatus === "approved") return true;
    return false;
  });
  const canOpenNewAccount = computed(
    () => isAuthenticated.value && isKycApproved.value,
  );

  // IB 审批状态相关计算属性
  // isIbApproved: 用户已通过审批成为 IB 代理（可以访问 IB Dashboard 和 Commission Report）
  // showIbProgram: 用户通过 IB 邀请或已通过审批（显示 IB Program 菜单）
  const showIbProgram = computed(() => {
    if (ibStatus.value?.showIbProgram === true) return true;
    if (user.value?.ibStatus?.showIbProgram === true) return true;
    // 预览模式下不使用 localStorage，避免受正常登录影响
    if (isPreviewMode.value) return false;
    const cachedValue = localStorage.getItem("clientShowIbProgram");
    return cachedValue === "true";
  });

  const isIbApproved = computed(() => {
    if (
      ibStatus.value?.isApproved === true ||
      ibStatus.value?.hasApprovedApplication === true
    )
      return true;
    if (user.value?.ibStatus?.isApproved === true) return true;
    if (user.value?.ibStatus?.hasApprovedApplication === true) return true;
    if (user.value?.ibApproved === true) return true;
    if (user.value?.ibApplicationStatus === "approved") return true;
    // 预览模式下不使用 localStorage，避免受正常登录影响
    if (isPreviewMode.value) return false;
    const cachedValue = localStorage.getItem("clientIsIbApproved");
    return cachedValue === "true";
  });

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
      if (loginData.kycStatus) {
        kycStatus.value = loginData.kycStatus;
      } else if (loginData.user?.kycStatus) {
        kycStatus.value = {
          status: loginData.user.kycStatus,
          submissionStatus: loginData.user.kycStatus,
          isApproved: loginData.user.kycStatus === "approved",
          hasSubmission: false,
        };
      }

      // 先保存 token，确保后续 API 调用能携带 token
      localStorage.setItem("clientToken", loginData.token);

      // 保存IB状态（如果登录接口返回了）
      if (loginData.ibStatus) {
        ibStatus.value = loginData.ibStatus;
        const ibProgramValue = loginData.ibStatus.showIbProgram || false;
        localStorage.setItem("clientShowIbProgram", ibProgramValue.toString());
        // 保存 isIbApproved 状态到 localStorage（用于快速判断）
        if (
          loginData.ibStatus.isApproved ||
          loginData.ibStatus.hasApprovedApplication
        ) {
          localStorage.setItem("clientIsIbApproved", "true");
        } else {
          localStorage.setItem("clientIsIbApproved", "false");
        }
      } else {
        // 如果登录接口没有返回IB状态，则调用 my-status 接口获取
        try {
          const ibStatusResponse = await ibInvitationApi.getMyStatus();
          const ibStatusData = ibStatusResponse.data || ibStatusResponse;
          if (ibStatusData) {
            // 后端仅依据 ibPartners：有记录且 status===approved 才是已成为了 IB；有记录即 showIbProgram
            const approved =
              ibStatusData.isApproved ??
              (ibStatusData.hasApplication &&
                ibStatusData.applicationStatus === "approved");
            const showProgram =
              ibStatusData.showIbProgram ?? !!ibStatusData.hasApplication;
            ibStatus.value = {
              hasApprovedApplication: approved,
              isApproved: approved,
              showIbProgram: showProgram,
            };
            if (ibStatusData.showIbProgram !== undefined) {
              localStorage.setItem(
                "clientShowIbProgram",
                ibStatusData.showIbProgram.toString(),
              );
            }
            // 保存 isIbApproved 状态
            if (ibStatus.value.isApproved) {
              localStorage.setItem("clientIsIbApproved", "true");
            } else {
              localStorage.setItem("clientIsIbApproved", "false");
            }
          }
        } catch (error) {
          // 如果获取失败，保持默认值（false）
        }
      }
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
      kycStatus.value = null;
      ibStatus.value = null;
      localStorage.removeItem("clientToken");
      localStorage.removeItem("clientRememberMe");
      localStorage.removeItem("clientShowIbProgram");
      localStorage.removeItem("clientIsIbApproved");
    }
  }

  async function fetchUser() {
    try {
      const response = await clientAuthService.getProfile();
      const payload = response.data || response;
      const profile = payload.user || payload;
      user.value = profile;
      if (payload.kycStatus) {
        kycStatus.value = payload.kycStatus;
      } else if (profile?.kycStatus) {
        kycStatus.value = {
          status: profile.kycStatus,
          submissionStatus: profile.kycStatus,
          isApproved: profile.kycStatus === "approved",
          hasSubmission: false,
        };
      }
      // 保存IB状态到内存和 localStorage
      if (payload.ibStatus) {
        ibStatus.value = payload.ibStatus;
        const ibProgramValue = payload.ibStatus.showIbProgram || false;
        localStorage.setItem("clientShowIbProgram", ibProgramValue.toString());
        // 保存 isIbApproved 状态到 localStorage
        if (
          payload.ibStatus.isApproved ||
          payload.ibStatus.hasApprovedApplication
        ) {
          localStorage.setItem("clientIsIbApproved", "true");
        } else {
          localStorage.setItem("clientIsIbApproved", "false");
        }
      }
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

  async function forgotPassword(email) {
    try {
      const response = await clientAuthService.forgotPassword(email);
      return { success: true, data: response.data };
    } catch (error) {
      // 处理不同的错误格式
      const errorMessage =
        error.message ||
        error.error ||
        error.response?.data?.message ||
        "Failed to send reset link";
      return {
        success: false,
        error: errorMessage,
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
      const errorMessage =
        error?.message ||
        error?.error ||
        error?.response?.data?.message ||
        "Password change failed";

      return {
        success: false,
        error: errorMessage,
      };
    }
  }

  async function updateProfile(data) {
    try {
      const response = await clientAuthService.updateProfile(data);
      const payload = response.data || response;
      if (payload.user) {
        user.value = payload.user;
      }
      return { success: true, data: payload };
    } catch (error) {
      // API 拦截器会将响应数据作为错误抛出，所以 error 可能是响应数据对象
      // 需要同时检查 error.message（响应数据）和 error.response?.data?.message（axios 错误）
      const errorMessage =
        error.message ||
        error.response?.data?.message ||
        "Profile update failed";
      return {
        success: false,
        error: errorMessage,
      };
    }
  }

  // 更新 KYC 状态的方法
  function updateKycStatus(status) {
    kycStatus.value = status;
  }

  /** 设置预览会话（View as client），不写入 token，仅内存；刷新恢复用 sessionStorage */
  function setPreviewSession(userInfo, tokenValue) {
    user.value = userInfo;
    token.value = null;
    isPreviewMode.value = true;
    previewToken.value = tokenValue;
    if (tokenValue && typeof sessionStorage !== "undefined") {
      sessionStorage.setItem("previewToken", tokenValue);
    }
  }

  /** 清除预览会话并回收 token；同时清除 sessionStorage */
  function clearPreviewSession() {
    isPreviewMode.value = false;
    const t = previewToken.value;
    previewToken.value = null;
    user.value = null;
    if (typeof sessionStorage !== "undefined") {
      sessionStorage.removeItem("previewToken");
    }
    return t;
  }

  /** 预览模式下拉取 me() 以填充 kycStatus、ibStatus（用于侧栏 IB Program 等显示） */
  async function loadPreviewProfile() {
    if (!isPreviewMode.value || !previewToken.value) return false;
    try {
      const response = await clientAuthService.getProfile();
      const payload = response?.data ?? response;
      const profile = payload?.user ?? payload;
      if (profile) user.value = profile;
      if (payload?.kycStatus) kycStatus.value = payload.kycStatus;
      if (payload?.ibStatus) ibStatus.value = payload.ibStatus;
      return true;
    } catch (_) {
      return false;
    }
  }

  return {
    user,
    token,
    kycStatus,
    ibStatus,
    isPreviewMode,
    previewToken,
    showIbProgram,
    isAuthenticated,
    isKycApproved,
    isIbApproved,
    canOpenNewAccount,
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
    updateProfile,
    updateKycStatus,
    setPreviewSession,
    clearPreviewSession,
    loadPreviewProfile,
  };
});
