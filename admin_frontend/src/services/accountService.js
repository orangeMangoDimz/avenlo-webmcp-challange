import api from "./api";
import { getSubModuleKey } from "@/config/operationLogPages";

/** 每次请求时解析，避免模块顶层缓存空值（HMR / 加载顺序） */
const accountsLogSubModuleKey = () => getSubModuleKey("page_accounts");

const withAccountsLog = (payload = {}) => ({
  ...payload,
  logSubModuleKey: accountsLogSubModuleKey(),
});

const deleteLogParams = () => {
  const key = accountsLogSubModuleKey();
  return key ? { logSubModuleKey: key } : {};
};

const rolesLogSubModuleKey = () => getSubModuleKey("page_role_management");

const withRolesLog = (payload = {}) => ({
  ...payload,
  logSubModuleKey: rolesLogSubModuleKey(),
});

const accountService = {
  /**
   * 获取用户列表
   * @param {Object} params - 查询参数
   * @param {number} params.page - 页码
   * @param {number} params.per_page - 每页数量
   * @param {string} params.search - 搜索关键词
   * @param {string} params.role - 角色筛选
   * @param {string} params.status - 状态筛选
   */
  async getAccounts(params = {}) {
    return await api.get("/users", { params });
  },

  /**
   * 获取单个用户信息
   * @param {number} id - 用户ID
   */
  async getAccount(id) {
    return await api.get(`/users/${id}`);
  },

  /**
   * 获取部门列表
   */
  async getDepartments() {
    return await api.get("/users/getdepartments");
  },

  /**
   * 获取职位列表
   */
  async getPositions() {
    return await api.get("/users/getpositions");
  },

  /**
   * 创建新用户
   * @param {Object} accountData - 用户数据
   */
  async createAccount(accountData) {
    return await api.post(
      "/users",
      withAccountsLog({
        username: accountData.username,
        email: accountData.email,
        password: accountData.password,
        fullName: accountData.fullName,
        roleId: accountData.roleId,
        departmentId: accountData.departmentId,
        positionId: accountData.positionId,
        phone: accountData.phone,
        department: accountData.department,
        status: accountData.status || "active",
      }),
    );
  },

  /**
   * 更新用户信息
   * @param {number} id - 用户ID
   * @param {Object} accountData - 用户数据
   */
  async updateAccount(id, accountData) {
    const updateData = {
      email: accountData.email,
      fullName: accountData.fullName,
      roleId: accountData.roleId,
      departmentId: accountData.departmentId,
      positionId: accountData.positionId,
      phone: accountData.phone,
      department: accountData.department,
      status: accountData.status,
    };
    // 如果提供了密码，则包含在更新数据中
    if (accountData.password) {
      updateData.password = accountData.password;
    }
    return await api.put(`/users/${id}`, withAccountsLog(updateData));
  },

  /**
   * 删除用户
   * @param {number} id - 用户ID
   */
  async deleteAccount(id) {
    return await api.delete(`/users/${id}`, { params: deleteLogParams() });
  },

  /**
   * 切换用户锁定状态
   * @param {number} id - 用户ID
   */
  async toggleLock(id) {
    return await api.post(`/users/${id}/toggle-lock`);
  },

  /**
   * 重置用户密码
   * @param {number} id - 用户ID
   * @param {string} newPassword - 新密码
   */
  async resetPassword(id, newPassword) {
    return await api.post(`/users/${id}/reset-password`, {
      newPassword: newPassword,
    });
  },

  /**
   * 获取角色列表
   * @param {Object} params - 查询参数
   */
  async getRoles(params = {}) {
    return await api.get("/roles", { params });
  },

  /**
   * 获取激活的角色列表（用于Account Management页面）
   */
  async getActiveRoles() {
    return await api.get("/roles/active");
  },

  /**
   * 获取单个角色信息
   * @param {number} id - 角色ID
   */
  async getRole(id) {
    return await api.get(`/roles/${id}`);
  },

  /**
   * 创建新角色
   * @param {Object} roleData - 角色数据
   */
  async createRole(roleData) {
    return await api.post("/roles/create", withRolesLog(roleData));
  },

  /**
   * 更新角色
   * @param {number} id - 角色ID
   * @param {Object} roleData - 角色数据
   */
  async updateRole(id, roleData) {
    return await api.post(`/roles/update/${id}`, withRolesLog(roleData));
  },

  /**
   * 删除角色
   * @param {number} id - 角色ID
   */
  async deleteRole(id) {
    return await api.delete(`/roles/${id}`);
  },

  /**
   * 获取角色权限
   * @param {number} id - 角色ID
   */
  async getRolePermissions(id) {
    return await api.get(`/roles/${id}/permissions`);
  },

  /**
   * 更新角色权限
   * @param {number} id - 角色ID
   * @param {Array} permissionIds - 权限ID数组
   */
  async updateRolePermissions(id, permissionIds) {
    return await api.post(`/roles/${id}/update-permissions`, {
      permissionIds: permissionIds,
    });
  },

  /**
   * 获取特殊角色 ID（Sales Manager、Sales），用于 Role Management 仅编辑权限的弹窗
   * @returns {Promise<{ salesManagerRoleId: number, salesRoleId: number }>}
   */
  async getSpecialRoleIds() {
    const res = await api.get("/sales/special-roles");
    const data = res && res.data ? res.data : res;
    return {
      salesManagerRoleId: data.salesManagerRoleId ?? 0,
      salesRoleId: data.salesRoleId ?? 0,
    };
  },

  /**
   * 获取权限列表
   * @param {Object} params - 查询参数
   */
  async getPermissions(params = {}) {
    return await api.get("/permissions", { params });
  },

  /**
   * 获取用户权限
   * @param {number} userId - 用户ID
   */
  async getUserPermissions(userId) {
    return await api.get(`/permissions/user/${userId}`);
  },

  /**
   * 检查用户权限
   * @param {string} permission - 权限代码
   */
  async checkPermission(permission) {
    return await api.post("/permissions/check", {
      permission: permission,
    });
  },
};

export default accountService;
