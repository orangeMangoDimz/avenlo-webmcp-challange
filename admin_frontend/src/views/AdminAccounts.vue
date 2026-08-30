<template>
  <div class="container">
    <!-- Page Header -->
    <div class="page-header">
      <div class="page-title">
        <h1>{{ t("page_adminAccounts_title") }}</h1>
        <p>{{ t("page_adminAccounts_sub") }}</p>
      </div>
      <div class="page-actions">
        <PageHeaderActions />
      </div>
    </div>

    <!-- Toolbar (style reference: IB List) -->
    <div class="toolbar">
      <div class="toolbar-left">
        <div class="search-box">
          <i class="fas fa-search"></i>
          <input
            type="text"
            v-model="searchQuery"
            :placeholder="t('adminAccounts_search_placeholder')"
            @keyup.enter="onSearch"
          />
        </div>
        <button type="button" class="btn btn-search" @click="onSearch">
          <i class="fas fa-search"></i> {{ t("adminAccounts_btn_search") }}
        </button>
      </div>
      <div class="toolbar-right">
        <div class="rows-select">
          <label class="rows-select__label">{{
            t("adminAccounts_showRows")
          }}</label>
          <select
            v-model="perPage"
            class="rows-select__select"
            @change="onPageSizeChange"
          >
            <option :value="5">{{ t("adminAccounts_rows_5") }}</option>
            <option :value="10">{{ t("adminAccounts_rows_10") }}</option>
            <option :value="20">{{ t("adminAccounts_rows_20") }}</option>
            <option value="all">{{ t("adminAccounts_rows_all") }}</option>
          </select>
        </div>
        <button
          v-if="hasCreatePermission"
          class="btn btn-primary"
          @click="openAddAccountModal"
        >
          <i class="fas fa-plus"></i>
          {{ t("adminAccounts_btn_add") }}
        </button>
      </div>
    </div>

    <!-- Accounts Table -->
    <div class="accounts-card">
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>{{ t("adminAccounts_th_account") }}</th>
              <th>{{ t("adminAccounts_th_username") }}</th>
              <th>{{ t("adminAccounts_th_role") }}</th>
              <th>{{ t("adminAccounts_th_department") }}</th>
              <th>{{ t("adminAccounts_th_position") }}</th>
              <th>{{ t("adminAccounts_th_status") }}</th>
              <th>{{ t("adminAccounts_th_lastLogin") }}</th>
              <th>{{ t("adminAccounts_th_createdDate") }}</th>
              <th>{{ t("adminAccounts_th_actions") }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="9" style="text-align: center; padding: 40px">
                <i
                  class="fas fa-spinner fa-spin"
                  style="font-size: 24px; color: var(--color-brand)"
                ></i>
                <p style="margin-top: 10px; color: var(--color-muted)">
                  {{ t("adminAccounts_loading") }}
                </p>
              </td>
            </tr>
            <tr v-else-if="accounts.length === 0">
              <td colspan="9" style="text-align: center; padding: 40px">
                <i
                  class="fas fa-inbox"
                  style="font-size: 48px; color: var(--color-border-strong)"
                ></i>
                <p style="margin-top: 10px; color: var(--color-muted)">
                  {{ t("adminAccounts_empty") }}
                </p>
              </td>
            </tr>
            <tr v-for="account in accounts" :key="account.id" v-else>
              <td>
                <div class="account-info">
                  <div
                    class="account-avatar"
                    :style="{
                      background:
                        account.avatarColor ||
                        'linear-gradient(135deg, var(--color-brand) 0%, var(--color-brand-strong) 100%)',
                    }"
                  >
                    {{ account.avatarInitials }}
                  </div>
                  <div class="account-details">
                    <div class="account-name">{{ account.fullName }}</div>
                    <div class="account-email">{{ account.email }}</div>
                  </div>
                </div>
              </td>
              <td>
                <div class="username-cell">
                  <i class="fas fa-user"></i>
                  <span>{{ account.username }}</span>
                </div>
              </td>
              <td>
                <span class="role-badge">
                  {{ account.roleDisplayName }}
                </span>
              </td>
              <td>
                <span class="department-name">
                  {{ getDepartmentName(account.departmentId) || "-" }}
                </span>
              </td>
              <td>
                <span class="position-name">
                  {{ getPositionName(account.positionId) || "-" }}
                </span>
              </td>
              <td>
                <span class="status-badge" :class="account.status">
                  {{
                    account.status === "active"
                      ? t("adminAccounts_status_active")
                      : t("adminAccounts_status_inactive")
                  }}
                </span>
              </td>
              <td>{{ formatDate(account.lastLoginAt) }}</td>
              <td>{{ formatDate(account.createdAt) }}</td>
              <td>
                <div class="action-buttons">
                  <button
                    v-if="hasEditPermission"
                    class="btn-icon btn-edit"
                    @click="editAccount(account)"
                    :title="t('adminAccounts_title_editAccount')"
                  >
                    <i class="fas fa-edit"></i>
                  </button>
                  <button
                    v-if="hasDeletePermission"
                    class="btn-icon btn-delete"
                    @click="deleteAccount(account)"
                    :title="t('adminAccounts_title_deleteAccount')"
                  >
                    <i class="fas fa-trash"></i>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <!-- Pagination (IB List style) -->
      <div class="pagination" v-if="pagination.total > 0">
        <span class="pagination__info">{{ paginationInfo }}</span>
        <div class="pagination__btns">
          <button
            type="button"
            class="btn btn-pagination"
            :disabled="pagination.page <= 1"
            @click="goToPage(pagination.page - 1)"
          >
            <i class="fas fa-chevron-left"></i>
            {{ t("adminAccounts_pagination_prev") }}
          </button>
          <span class="pagination__page">{{
            tParams("adminAccounts_pagination_pageOf", "", {
              current: pagination.page,
              total: totalPagesText,
            })
          }}</span>
          <button
            type="button"
            class="btn btn-pagination"
            :disabled="pagination.page >= pagination.total_pages"
            @click="goToPage(pagination.page + 1)"
          >
            {{ t("adminAccounts_pagination_next") }}
            <i class="fas fa-chevron-right"></i>
          </button>
        </div>
      </div>
    </div>

    <!-- Add/Edit Account Modal -->
    <Teleport to="body">
      <div
        class="modal"
        :class="{ active: showAccountModal }"
        @click="closeAccountModal"
      >
        <div class="modal-content" @click.stop>
          <div class="modal-header">
            <h2>
              <i
                :class="isEditMode ? 'fas fa-user-edit' : 'fas fa-user-plus'"
              ></i>
              {{
                isEditMode
                  ? t("adminAccounts_modal_editTitle")
                  : t("adminAccounts_modal_addTitle")
              }}
            </h2>
            <button class="modal-close" @click="closeAccountModal">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <form @submit.prevent="saveAccount">
              <div class="form-group">
                <label>{{ t("adminAccounts_label_fullName") }}</label>
                <input
                  type="text"
                  v-model="accountForm.fullName"
                  :placeholder="t('adminAccounts_ph_fullName')"
                  required
                />
              </div>

              <div class="form-group" v-if="!isEditMode">
                <label>{{ t("adminAccounts_label_username") }}</label>
                <input
                  type="text"
                  v-model="accountForm.username"
                  :placeholder="t('adminAccounts_ph_username')"
                  required
                />
              </div>

              <div class="form-group">
                <label>{{ t("adminAccounts_label_email") }}</label>
                <input
                  type="email"
                  v-model="accountForm.email"
                  :placeholder="t('adminAccounts_ph_email')"
                  required
                />
              </div>

              <div class="form-group">
                <label
                  >{{ t("adminAccounts_label_password")
                  }}{{ isEditMode ? "" : " *" }}</label
                >
                <div class="password-input-wrapper">
                  <input
                    :type="showPassword ? 'text' : 'password'"
                    v-model="accountForm.password"
                    :placeholder="
                      isEditMode
                        ? t('adminAccounts_ph_password_edit')
                        : t('adminAccounts_ph_password_new')
                    "
                    :required="!isEditMode"
                    class="password-input"
                  />
                  <button
                    type="button"
                    class="password-toggle-btn"
                    @click="showPassword = !showPassword"
                    :title="
                      showPassword
                        ? t('adminAccounts_password_hide')
                        : t('adminAccounts_password_show')
                    "
                  >
                    <i
                      :class="showPassword ? 'fas fa-eye-slash' : 'fas fa-eye'"
                    ></i>
                  </button>
                </div>
                <small
                  v-if="!isEditMode || accountForm.password"
                  class="password-hint"
                >
                  <i class="fas fa-info-circle"></i>
                  {{ t("adminAccounts_password_hint") }}
                </small>
              </div>

              <div class="form-group">
                <label>{{ t("adminAccounts_label_role") }}</label>
                <select v-model="accountForm.roleId" required>
                  <option value="">{{ t("adminAccounts_select_role") }}</option>
                  <option v-for="role in roles" :key="role.id" :value="role.id">
                    {{ role.roleDisplayName }}
                  </option>
                </select>
              </div>

              <div class="form-group">
                <label>{{ t("adminAccounts_label_department") }}</label>
                <select v-model="accountForm.departmentId">
                  <option :value="0">
                    {{ t("adminAccounts_select_department") }}
                  </option>
                  <option
                    v-for="dept in departments"
                    :key="dept.id"
                    :value="dept.id"
                  >
                    {{ dept.name }}
                  </option>
                </select>
              </div>

              <div class="form-group">
                <label>{{ t("adminAccounts_label_position") }}</label>
                <select v-model="accountForm.positionId">
                  <option :value="0">
                    {{ t("adminAccounts_select_position") }}
                  </option>
                  <option
                    v-for="pos in positions"
                    :key="pos.id"
                    :value="pos.id"
                  >
                    {{ pos.name }}
                  </option>
                </select>
              </div>

              <div class="form-group">
                <label>{{ t("adminAccounts_label_accountStatus") }}</label>
                <div class="toggle-option">
                  <div class="toggle-option-info">
                    <h3>{{ t("adminAccounts_activeAccount_title") }}</h3>
                    <p>{{ t("adminAccounts_activeAccount_desc") }}</p>
                  </div>
                  <label class="toggle-switch">
                    <input type="checkbox" v-model="accountForm.isActive" />
                    <span class="toggle-slider"></span>
                  </label>
                </div>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" @click="closeAccountModal">
              {{ t("adminAccounts_btn_cancel") }}
            </button>
            <button
              class="btn btn-primary"
              @click="saveAccount"
              :disabled="saving"
            >
              <i class="fas fa-save"></i>
              {{
                saving ? t("adminAccounts_saving") : t("adminAccounts_btn_save")
              }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";

import { ref, computed, onMounted } from "vue";
import { useAuthStore } from "@/stores/auth";
import { useAccountsStore } from "@/stores/accounts";
import accountService from "@/services/accountService";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t, tParams } = useAdminI18n();

const accountsStore = useAccountsStore();
const authStore = useAuthStore();

// 权限检查
const hasReadonlyPermission = computed(() =>
  authStore.hasPermission("page_accountmanagement_readonly"),
);
const hasCreatePermission = computed(() =>
  authStore.hasPermission("page_accountmanagement_create"),
);
const hasEditPermission = computed(() =>
  authStore.hasPermission("page_accountmanagement_edit"),
);
const hasDeletePermission = computed(() =>
  authStore.hasPermission("page_accountmanagement_delete"),
);

const searchQuery = ref("");
const perPage = ref(10);
const showAccountModal = ref(false);
const isEditMode = ref(false);
const saving = ref(false);
const showPassword = ref(false);
const departments = ref([]);
const positions = ref([]);
const accountForm = ref({
  id: null,
  fullName: "",
  username: "",
  email: "",
  password: "",
  roleId: "",
  departmentId: 0,
  positionId: 0,
  isActive: true,
});

const loading = computed(() => accountsStore.loading);
const accounts = computed(() => accountsStore.accounts);
const roles = computed(() => accountsStore.roles);
const pagination = computed(() => accountsStore.pagination);

const totalPagesText = computed(() => {
  const total = pagination.value.total_pages;
  return total <= 0 ? "1" : String(total);
});

const paginationInfo = computed(() => {
  const total = pagination.value.total;
  if (total === 0) return t("adminAccounts_pagination_noRecords");
  if (perPage.value === "all")
    return tParams("adminAccounts_pagination_totalRecords", "", { total });
  const per = Number(pagination.value.per_page) || 10;
  const from = (pagination.value.page - 1) * per + 1;
  const to = Math.min(pagination.value.page * per, total);
  return tParams("adminAccounts_pagination_showing", "", { from, to, total });
});

const loadAccounts = (page) => {
  const p = page !== undefined ? page : pagination.value.page;
  accountsStore.fetchAccounts({
    page: p,
    per_page: perPage.value,
    search: searchQuery.value || undefined,
  });
};

const onSearch = () => {
  loadAccounts(1);
};

const onPageSizeChange = () => {
  loadAccounts(1);
};

const goToPage = (page) => {
  if (page < 1 || page > pagination.value.total_pages) return;
  loadAccounts(page);
};

const formatDate = (dateString) => {
  if (!dateString) return "-";
  const date = new Date(dateString);
  return date.toLocaleString("en-US", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
  });
};

const getDepartmentName = (departmentId) => {
  if (!departmentId) return null;
  const dept = departments.value.find((d) => d.id == departmentId);
  return dept ? dept.name : null;
};

const getPositionName = (positionId) => {
  if (!positionId) return null;
  const pos = positions.value.find((p) => p.id == positionId);
  return pos ? pos.name : null;
};

const openAddAccountModal = () => {
  isEditMode.value = false;
  accountForm.value = {
    id: null,
    fullName: "",
    username: "",
    email: "",
    password: "",
    roleId: "",
    departmentId: 0,
    positionId: 0,
    isActive: true,
  };
  showAccountModal.value = true;
};

const editAccount = (account) => {
  isEditMode.value = true;
  accountForm.value = {
    id: account.id,
    fullName: account.fullName,
    username: account.username,
    email: account.email,
    password: "",
    roleId: account.roleId,
    departmentId: account.departmentId || 0,
    positionId: account.positionId || 0,
    isActive: account.status === "active",
  };
  showAccountModal.value = true;
};

const closeAccountModal = () => {
  showAccountModal.value = false;
  showPassword.value = false;
};

const saveAccount = async () => {
  // Validate form
  if (
    !accountForm.value.fullName ||
    !accountForm.value.email ||
    !accountForm.value.roleId
  ) {
    alert(t("adminAccounts_alert_requiredFields"));
    return;
  }

  if (!isEditMode.value && !accountForm.value.username) {
    alert(t("adminAccounts_alert_usernameRequired"));
    return;
  }

  if (!isEditMode.value && !accountForm.value.password) {
    alert(t("adminAccounts_alert_passwordRequired"));
    return;
  }

  // Password length validation
  if (accountForm.value.password && accountForm.value.password.length < 8) {
    alert(t("adminAccounts_alert_passwordLength"));
    return;
  }

  // Email validation
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!emailRegex.test(accountForm.value.email)) {
    alert(t("adminAccounts_alert_emailInvalid"));
    return;
  }

  // Department validation
  if (accountForm.value.departmentId === 0) {
    alert(t("adminAccounts_alert_departmentRequired"));
    return;
  }

  // Position validation
  if (accountForm.value.positionId === 0) {
    alert(t("adminAccounts_alert_positionRequired"));
    return;
  }

  saving.value = true;
  try {
    const data = {
      fullName: accountForm.value.fullName,
      email: accountForm.value.email,
      roleId: accountForm.value.roleId,
      departmentId:
        accountForm.value.departmentId === 0
          ? null
          : accountForm.value.departmentId,
      positionId:
        accountForm.value.positionId === 0
          ? null
          : accountForm.value.positionId,
      status: accountForm.value.isActive ? "active" : "inactive",
    };

    if (!isEditMode.value) {
      data.username = accountForm.value.username;
      data.password = accountForm.value.password;
      const result = await accountsStore.createAccount(data);
      if (result.success) {
        alert(t("adminAccounts_alert_createSuccess"));
        closeAccountModal();
        loadAccounts(1);
      } else {
        alert(
          tParams("adminAccounts_err_createWithMsg", "", { msg: result.error }),
        );
      }
    } else {
      if (accountForm.value.password) {
        data.password = accountForm.value.password;
      }
      const result = await accountsStore.updateAccount(
        accountForm.value.id,
        data,
      );
      if (result.success) {
        alert(t("adminAccounts_alert_updateSuccess"));
        closeAccountModal();
        loadAccounts(pagination.value.page);
      } else {
        alert(
          tParams("adminAccounts_err_updateWithMsg", "", { msg: result.error }),
        );
      }
    }
  } catch (error) {
    alert(t("adminAccounts_alert_genericError"));
  } finally {
    saving.value = false;
  }
};

const deleteAccount = async (account) => {
  if (
    confirm(
      tParams("adminAccounts_confirm_delete", "", { name: account.fullName }),
    )
  ) {
    const result = await accountsStore.deleteAccount(account.id);
    if (result.success) {
      alert(
        tParams("adminAccounts_alert_deleteSuccess", "", {
          name: account.fullName,
        }),
      );
      loadAccounts(pagination.value.page);
    } else {
      alert(
        tParams("adminAccounts_err_deleteWithMsg", "", { msg: result.error }),
      );
    }
  }
};

const handleFilter = () => {
  alert(t("adminAccounts_alert_filterStub"));
};

const loadDepartments = async () => {
  try {
    const response = await accountService.getDepartments();
    if (response.success) {
      departments.value = response.data || [];
    }
  } catch (error) {
    console.error("Failed to load departments:", error);
  }
};

const loadPositions = async () => {
  try {
    const response = await accountService.getPositions();
    if (response.success) {
      positions.value = response.data || [];
    }
  } catch (error) {
    console.error("Failed to load positions:", error);
  }
};

onMounted(async () => {
  await loadAccounts(1);
  await accountsStore.fetchActiveRoles(); // 获取激活的角色，用于选择角色下拉框
  await loadDepartments();
  await loadPositions();
});
</script>

<style scoped>
.container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 40px 20px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 2px solid var(--color-border);
}

.page-title h1 {
  font-size: 28px;
  color: var(--color-ink);
  margin-bottom: 5px;
}

.page-title p {
  font-size: 14px;
  color: var(--color-muted);
}

.page-actions {
  display: flex;
  align-items: center;
  gap: 20px;
}

/* Toolbar (IB List style) */
.toolbar {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: 20px 25px;
  margin-bottom: 25px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 15px;
}

.toolbar-left {
  display: flex;
  align-items: center;
  gap: 10px;
  flex: 1;
  min-width: 280px;
}

.toolbar-right {
  display: flex;
  align-items: center;
  gap: 15px;
}

.btn-search {
  padding: 12px 20px;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: var(--color-brand-solid);
  color: white;
}

.btn-search:hover {
  background: var(--color-brand-strong);
}

.rows-select {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  color: var(--color-text);
}

.rows-select__label {
  white-space: nowrap;
}

.rows-select__select {
  padding: 8px 12px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  font-size: 14px;
  cursor: pointer;
}

.search-box {
  flex: 1;
  min-width: 200px;
  position: relative;
}

.search-box input {
  width: 100%;
  padding: 12px 16px 12px 45px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  transition: all 0.3s ease;
}

.search-box input:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.search-box i {
  position: absolute;
  left: 16px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-faint);
  font-size: 16px;
}

/* Pagination (IB List style) */
.pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 15px;
  padding: 16px 24px;
  background: var(--color-surface-soft);
  border-top: 1px solid var(--color-border);
}

.pagination__info {
  font-size: 14px;
  color: var(--color-text);
}

.pagination__btns {
  display: flex;
  align-items: center;
  gap: 12px;
}

.pagination__page {
  font-size: 14px;
  color: var(--color-text);
}

.btn-pagination {
  padding: 8px 14px;
  font-size: 14px;
  background: var(--color-border);
  color: var(--color-text);
  border: none;
  border-radius: var(--radius-md);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.btn-pagination:hover:not(:disabled) {
  background: var(--color-brand-solid);
  color: white;
}

.btn-pagination:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.toolbar-actions {
  display: flex;
  gap: 12px;
  align-items: center;
}

.btn {
  padding: 12px 24px;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.btn-primary {
  background: var(--color-brand-solid);
  color: white;
  box-shadow: 0 4px 15px rgba(var(--color-brand-rgb), 0.4);
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(var(--color-brand-rgb), 0.5);
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-secondary {
  background: var(--color-surface);
  color: var(--color-text);
  border: 2px solid var(--color-border);
}

.btn-secondary:hover {
  border-color: var(--color-brand);
  color: var(--color-brand);
  background: var(--color-brand-soft);
}

/* Accounts Table */
.accounts-card {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

.table-wrapper {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
}

thead {
  background: var(--color-surface-soft);
}

thead th {
  padding: 18px 20px;
  text-align: left;
  font-size: 14px;
  font-weight: 700;
  color: var(--color-text);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 2px solid var(--color-border);
}

tbody tr {
  transition: all 0.2s ease;
  border-bottom: 1px solid #f1f3f5;
}

tbody tr:hover {
  background: var(--color-surface-soft);
}

tbody td {
  padding: 20px;
  color: var(--color-ink);
  font-size: 14px;
}

.account-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.account-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: 600;
  font-size: 14px;
  flex-shrink: 0;
}

.account-details {
  display: flex;
  flex-direction: column;
}

.account-name {
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 3px;
}

.account-email {
  font-size: 14px;
  color: var(--color-muted);
}

.username-cell {
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--color-text);
  font-weight: 500;
  font-size: 14px;
}

.username-cell i {
  font-size: 14px;
  color: var(--color-muted);
}

.role-badge {
  display: inline-block;
  padding: 6px 12px;
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: 600;
  text-transform: uppercase;
}

.role-badge.admin {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.role-badge.manager {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.role-badge.operator {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.role-badge.viewer {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: 600;
}

.status-badge.active {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.status-badge.active::before {
  content: "";
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--color-success-solid);
}

.status-badge.inactive {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.status-badge.inactive::before {
  content: "";
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--color-danger-solid);
}

/* 状态列：中文「启用/禁用」保持单行（窄屏下列宽不足时避免折行） */
.accounts-card .table-wrapper table thead th:nth-child(6),
.accounts-card .table-wrapper table tbody td:nth-child(6) {
  white-space: nowrap;
  vertical-align: middle;
}

.accounts-card .table-wrapper table tbody td:nth-child(6) .status-badge {
  white-space: nowrap;
  word-break: keep-all;
}

.action-buttons {
  display: flex;
  gap: 8px;
}

.btn-icon {
  width: 36px;
  height: 36px;
  border-radius: var(--radius-sm);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
  border: none;
  font-size: 14px;
}

.btn-edit {
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.btn-edit:hover {
  background: var(--color-brand-solid);
  color: white;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.3);
}

.btn-delete {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.btn-delete:hover {
  background: var(--color-danger-solid);
  color: white;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(229, 62, 62, 0.3);
}

/* Modal Styles */
.modal {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  z-index: 1000;
  animation: fadeIn 0.3s ease;
}

.modal.active {
  display: flex;
  align-items: center;
  justify-content: center;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.modal-content {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  width: 90%;
  max-width: 600px;
  max-height: 90vh;
  overflow-y: auto;
  animation: slideUp 0.3s ease;
}

@keyframes slideUp {
  from {
    transform: translateY(50px);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

.modal-header {
  padding: 25px 30px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h2 {
  font-size: 22px;
  color: var(--color-ink);
}

.modal-header h2 i {
  margin-right: 10px;
  color: var(--color-brand);
}

.modal-close {
  width: 36px;
  height: 36px;
  border-radius: var(--radius-sm);
  background: var(--color-surface-soft);
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
  font-size: 18px;
  color: var(--color-text);
}

.modal-close:hover {
  background: var(--color-border);
  color: var(--color-ink);
}

.modal-body {
  padding: 30px;
}

.form-group {
  margin-bottom: 25px;
}

.form-group label {
  display: block;
  margin-bottom: 10px;
  color: var(--color-ink);
  font-weight: 600;
  font-size: 14px;
}

.form-group input,
.form-group select {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  transition: all 0.3s ease;
  background: var(--color-surface);
}

.form-group input:focus,
.form-group select:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.toggle-switch {
  position: relative;
  display: inline-block;
  width: 60px;
  height: 32px;
}

.toggle-switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.toggle-slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: var(--color-border-strong);
  transition: 0.4s;
  border-radius: 32px;
}

.toggle-slider:before {
  position: absolute;
  content: "";
  height: 24px;
  width: 24px;
  left: 4px;
  bottom: 4px;
  background-color: var(--color-surface);
  transition: 0.4s;
  border-radius: 50%;
}

input:checked + .toggle-slider {
  background: var(--color-brand-solid);
}

input:checked + .toggle-slider:before {
  transform: translateX(28px);
}

.toggle-option {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 15px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-md);
}

.toggle-option-info h3 {
  font-size: 15px;
  color: var(--color-ink);
  margin-bottom: 3px;
}

.toggle-option-info p {
  font-size: 14px;
  color: var(--color-muted);
}

.modal-footer {
  padding: 20px 30px;
  border-top: 2px solid var(--color-border);
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

.password-input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.password-input {
  flex: 1;
  padding-right: 40px;
}

.password-toggle-btn {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  cursor: pointer;
  padding: 5px;
  color: var(--color-muted);
  font-size: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: color 0.2s;
  z-index: 1;
}

.password-toggle-btn:hover {
  color: var(--color-brand);
}

.password-toggle-btn:focus {
  outline: none;
  color: var(--color-brand);
}

.password-hint {
  display: block;
  margin-top: 8px;
  color: var(--color-muted);
  font-size: 14px;
  font-style: normal;
}

.password-hint i {
  margin-right: 6px;
  color: var(--color-brand);
}

@media (max-width: 768px) {
  .container {
    padding: 20px 15px;
  }

  .page-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 15px;
  }

  .toolbar {
    flex-direction: column;
    align-items: stretch;
  }

  .search-box {
    width: 100%;
  }

  .toolbar-actions {
    width: 100%;
    flex-direction: column;
  }

  .toolbar-actions .btn {
    width: 100%;
    justify-content: center;
  }

  table {
    font-size: 14px;
  }

  tbody td {
    padding: 15px 10px;
  }

  .modal-content {
    width: 95%;
    margin: 10px;
  }
}
</style>
