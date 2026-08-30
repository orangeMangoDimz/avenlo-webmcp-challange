import { defineStore } from "pinia";
import { ref } from "vue";
import accountService from "@/services/accountService";

export const useAccountsStore = defineStore("accounts", () => {
  const accounts = ref([]);
  const roles = ref([]);
  const permissions = ref([]);
  const loading = ref(false);
  const error = ref(null);

  async function fetchAccounts() {
    loading.value = true;
    error.value = null;
    try {
      const response = await accountService.getAccounts();
      // console.log('Raw API response:', response)

      // 后端返回格式: { success: true, data: { items: [...], pagination: {...} } }
      const responseData = response.data || response;
      // console.log('Response data:', responseData)
      // console.log('Items:', responseData.items)

      accounts.value = responseData.items || responseData || [];
      // console.log('Fetched accounts:', accounts.value.length, 'accounts')
      if (accounts.value.length > 0) {
        // console.log('First account:', accounts.value[0])
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

  async function fetchRoles() {
    try {
      const response = await accountService.getRoles();
      const responseData = response.data || response;
      roles.value = responseData.items || responseData || [];
      return { success: true };
    } catch (err) {
      error.value = err.response?.data?.message || "Failed to fetch roles";
      return { success: false, error: error.value };
    }
  }

  async function fetchPermissions() {
    try {
      const response = await accountService.getPermissions();
      const responseData = response.data || response;
      permissions.value = responseData.items || responseData || [];
      return { success: true };
    } catch (err) {
      error.value =
        err.response?.data?.message || "Failed to fetch permissions";
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
    permissions,
    loading,
    error,
    fetchAccounts,
    fetchRoles,
    fetchPermissions,
    createAccount,
    updateAccount,
    deleteAccount,
    searchAccounts,
  };
});
