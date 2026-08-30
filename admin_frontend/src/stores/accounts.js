import { defineStore } from "pinia";
import { ref } from "vue";
import accountService from "@/services/accountService";

export const useAccountsStore = defineStore("accounts", () => {
  const accounts = ref([]);
  const roles = ref([]);
  const loading = ref(false);
  const error = ref(null);
  /** 分页信息，与 IB List 一致：{ total, page, per_page, total_pages } */
  const pagination = ref({
    total: 0,
    page: 1,
    per_page: 10,
    total_pages: 1,
  });

  /**
   * 获取账户列表，支持分页与搜索
   * @param {Object} [params] - { page, per_page, search }
   */
  async function fetchAccounts(params = {}) {
    loading.value = true;
    error.value = null;
    let perPage = params.per_page !== undefined ? params.per_page : 10;
    if (perPage === "all") perPage = 10000;
    const query = {
      page: params.page !== undefined ? params.page : 1,
      per_page: perPage,
      search:
        params.search !== undefined && params.search !== ""
          ? params.search
          : undefined,
    };
    try {
      const response = await accountService.getAccounts(query);
      const responseData = response.data || response;
      const data = responseData.data || responseData;
      const items = data?.items ?? data ?? [];
      accounts.value = Array.isArray(items) ? items : [];

      const pag = data?.pagination || responseData.pagination;
      if (pag) {
        pagination.value = {
          total: Number(pag.total) || 0,
          page: Number(pag.page) || 1,
          per_page: Number(pag.per_page) || 10,
          total_pages: Math.max(1, Number(pag.total_pages) || 1),
        };
      }
      return { success: true };
    } catch (err) {
      error.value = err.response?.data?.message || "Failed to fetch accounts";
      console.error("Fetch accounts error:", err);
      return { success: false, error: error.value };
    } finally {
      loading.value = false;
    }
  }

  /**
   * 获取激活的角色列表（仅用于Account Management页面）
   * 只返回 isActive = 1 的角色，用于创建/编辑账户时选择角色
   */
  async function fetchActiveRoles() {
    try {
      const response = await accountService.getActiveRoles();
      const responseData = response.data || response;
      roles.value = responseData.items || responseData || [];
      return { success: true };
    } catch (err) {
      error.value =
        err.response?.data?.message || "Failed to fetch active roles";
      return { success: false, error: error.value };
    }
  }

  async function createAccount(accountData) {
    loading.value = true;
    error.value = null;
    try {
      const response = await accountService.createAccount(accountData);
      const accountData_result = response.data || response;
      console.log("Account created successfully:", accountData_result);

      // 重新加载列表以获取完整数据
      await fetchAccounts();

      return { success: true, data: accountData_result };
    } catch (err) {
      // 处理验证错误（422）
      // 现在所有错误都返回200 HTTP状态码，需要检查响应体中的statusCode
      if (err.statusCode === 422 && err.errors) {
        const errors = err.errors;
        const errorMessages = Object.entries(errors)
          .map(([field, messages]) => `${field}: ${messages.join(", ")}`)
          .join("\n");
        error.value = errorMessages;
      } else {
        error.value = err.message || "Failed to create account";
      }
      console.error("Create account error:", err);
      console.error("Validation errors:", err.errors);
      return { success: false, error: error.value };
    } finally {
      loading.value = false;
    }
  }

  async function updateAccount(id, accountData) {
    loading.value = true;
    error.value = null;
    try {
      const response = await accountService.updateAccount(id, accountData);
      const index = accounts.value.findIndex((acc) => acc.id === id);
      if (index !== -1) {
        accounts.value[index] = { ...accounts.value[index], ...response.data };
      }
      return { success: true, data: response.data };
    } catch (err) {
      error.value = err.response?.data?.message || "Failed to update account";
      return { success: false, error: error.value };
    } finally {
      loading.value = false;
    }
  }

  async function deleteAccount(id) {
    loading.value = true;
    error.value = null;
    try {
      await accountService.deleteAccount(id);
      accounts.value = accounts.value.filter((acc) => acc.id !== id);
      return { success: true };
    } catch (err) {
      error.value = err.response?.data?.message || "Failed to delete account";
      return { success: false, error: error.value };
    } finally {
      loading.value = false;
    }
  }

  function searchAccounts(query) {
    if (!query) return accounts.value;

    const lowerQuery = query.toLowerCase();
    return accounts.value.filter((account) => {
      return (
        account.fullName?.toLowerCase().includes(lowerQuery) ||
        account.email?.toLowerCase().includes(lowerQuery) ||
        account.username?.toLowerCase().includes(lowerQuery) ||
        account.roleDisplayName?.toLowerCase().includes(lowerQuery)
      );
    });
  }

  return {
    accounts,
    roles,
    loading,
    error,
    pagination,
    fetchAccounts,
    fetchActiveRoles, // 获取激活的角色（仅用于Account Management页面）
    createAccount,
    updateAccount,
    deleteAccount,
    searchAccounts,
  };
});
