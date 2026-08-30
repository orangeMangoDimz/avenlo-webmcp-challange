<template>
  <div class="container">
    <!-- Page Header -->
    <div class="page-header">
      <div class="page-title">
        <h1>{{ t("page_roleManagement_title") }}</h1>
        <p>{{ t("page_roleManagement_sub") }}</p>
      </div>
      <div class="page-actions">
        <PageHeaderActions />
      </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
      <div class="search-box">
        <i class="fas fa-search"></i>
        <input
          type="text"
          v-model="searchQuery"
          :placeholder="t('roleMgmt_search_placeholder')"
        />
      </div>
      <div class="toolbar-actions">
        <button class="btn btn-secondary" @click="handleFilter">
          <i class="fas fa-filter"></i>
          {{ t("roleMgmt_btn_filter") }}
        </button>
        <button
          v-if="hasCreatePermission"
          class="btn btn-primary"
          @click="openAddRoleModal"
        >
          <i class="fas fa-plus"></i>
          {{ t("roleMgmt_btn_add") }}
        </button>
      </div>
    </div>

    <!-- Roles Table -->
    <div class="roles-card">
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>{{ t("roleMgmt_th_roleName") }}</th>
              <th>{{ t("roleMgmt_th_description") }}</th>
              <th>{{ t("roleMgmt_th_users") }}</th>
              <th>{{ t("roleMgmt_th_status") }}</th>
              <th>{{ t("roleMgmt_th_createdDate") }}</th>
              <th>{{ t("roleMgmt_th_actions") }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="6" style="text-align: center; padding: 40px">
                <i
                  class="fas fa-spinner fa-spin"
                  style="font-size: 24px; color: var(--color-brand)"
                ></i>
                <p style="margin-top: 10px; color: var(--color-muted)">
                  {{ t("roleMgmt_loading") }}
                </p>
              </td>
            </tr>
            <tr v-else-if="filteredRoles.length === 0">
              <td colspan="6" style="text-align: center; padding: 40px">
                <i
                  class="fas fa-inbox"
                  style="font-size: 48px; color: var(--color-border-strong)"
                ></i>
                <p style="margin-top: 10px; color: var(--color-muted)">
                  {{ t("roleMgmt_empty") }}
                </p>
              </td>
            </tr>
            <tr v-for="role in filteredRoles" :key="role.id" v-else>
              <td>
                <div class="role-info">
                  <span class="role-badge">
                    {{ role.roleName }}
                  </span>
                </div>
              </td>
              <td>{{ role.description || "-" }}</td>
              <td>
                <span class="user-count">{{ role.userCount || 0 }}</span>
              </td>
              <td>
                <span
                  class="status-badge"
                  :class="role.isActive ? 'active' : 'inactive'"
                >
                  {{
                    role.isActive
                      ? t("roleMgmt_status_active")
                      : t("roleMgmt_status_inactive")
                  }}
                </span>
              </td>
              <td>{{ formatDate(role.createdAt) }}</td>
              <td>
                <div class="action-buttons" v-if="role.id != 1">
                  <button
                    v-if="hasEditPermission"
                    class="btn-icon btn-edit"
                    @click="editRole(role)"
                    :title="
                      isSpecialRole(role)
                        ? t('roleMgmt_title_editPermissions')
                        : t('roleMgmt_title_editRole')
                    "
                  >
                    <i class="fas fa-edit"></i>
                  </button>
                  <button
                    v-if="hasDisablePermission && !isSpecialRole(role)"
                    class="btn-icon btn-delete"
                    @click="toggleRoleStatus(role)"
                    :title="
                      role.isActive
                        ? t('roleMgmt_title_disableRole')
                        : t('roleMgmt_title_enableRole')
                    "
                  >
                    <i
                      class="fas"
                      :class="role.isActive ? 'fa-toggle-on' : 'fa-toggle-off'"
                    ></i>
                  </button>
                </div>
                <span v-else class="no-actions-text">-</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Add/Edit Role Modal -->
    <Teleport to="body">
      <div
        class="modal"
        :class="{ active: showRoleModal }"
        @click="closeRoleModal"
      >
        <div class="modal-content role-modal-content" @click.stop>
          <div class="modal-header">
            <h2>
              <i :class="isEditMode ? 'fas fa-edit' : 'fas fa-plus'"></i>
              {{
                isEditPermissionsOnly
                  ? t("roleMgmt_modal_editPermissions")
                  : isEditMode
                    ? t("roleMgmt_modal_editRole")
                    : t("roleMgmt_modal_addRole")
              }}
            </h2>
            <button class="modal-close" @click="closeRoleModal">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <form @submit.prevent="saveRole">
              <!-- Role Basic Information（特殊角色仅显示可编辑的 Description） -->
              <div class="form-section">
                <h3 class="section-title">
                  {{ t("roleMgmt_section_basicInfo") }}
                </h3>
                <div class="form-group" v-if="!isEditPermissionsOnly">
                  <label>{{ t("roleMgmt_label_roleName") }}</label>
                  <input
                    type="text"
                    v-model="roleForm.roleName"
                    :placeholder="t('roleMgmt_ph_roleName')"
                    required
                  />
                </div>

                <div class="form-group">
                  <label>{{ t("roleMgmt_label_description") }}</label>
                  <textarea
                    v-model="roleForm.description"
                    :placeholder="t('roleMgmt_ph_description')"
                    rows="3"
                  ></textarea>
                </div>

                <div class="form-group" v-if="!isEditPermissionsOnly">
                  <label>{{ t("roleMgmt_label_status") }}</label>
                  <select v-model="roleForm.isActive" required>
                    <option :value="true">
                      {{ t("roleMgmt_option_active") }}
                    </option>
                    <option :value="false">
                      {{ t("roleMgmt_option_inactive") }}
                    </option>
                  </select>
                </div>
              </div>

              <!-- Permissions Tree -->
              <div class="form-section">
                <h3 class="section-title">
                  {{ t("roleMgmt_section_permissions") }}
                </h3>
                <div class="permissions-tree" v-if="permissionsTree.length > 0">
                  <div
                    v-for="group in permissionsTree"
                    :key="group.id"
                    class="permission-group"
                  >
                    <div class="permission-group-header">
                      <label class="permission-checkbox">
                        <input
                          type="checkbox"
                          :ref="(el) => setGroupCheckboxRef(el, group.id)"
                          :checked="isGroupChecked(group)"
                          @change="toggleGroup(group, $event.target.checked)"
                        />
                        <span class="checkmark"></span>
                        <span class="permission-label">{{
                          permissionLabel(group)
                        }}</span>
                      </label>
                    </div>
                    <div
                      class="permission-pages"
                      v-if="group.children && group.children.length > 0"
                    >
                      <div
                        v-for="page in group.children"
                        :key="page.id"
                        class="permission-page"
                      >
                        <div class="permission-page-header">
                          <label class="permission-checkbox">
                            <input
                              type="checkbox"
                              :ref="(el) => setPageCheckboxRef(el, page.id)"
                              :checked="isPageChecked(page)"
                              @change="togglePage(page, $event.target.checked)"
                            />
                            <span class="checkmark"></span>
                            <span class="permission-label">{{
                              permissionLabel(page)
                            }}</span>
                          </label>
                        </div>
                        <div
                          class="permission-actions"
                          v-if="page.children && page.children.length > 0"
                        >
                          <label
                            v-for="action in page.children"
                            :key="action.id"
                            class="permission-checkbox action-checkbox"
                          >
                            <input
                              type="checkbox"
                              :checked="isPermissionSelected(action.id)"
                              @change="
                                togglePermission(
                                  action.id,
                                  $event.target.checked,
                                )
                              "
                            />
                            <span class="checkmark"></span>
                            <span class="permission-label">{{
                              permissionLabel(action)
                            }}</span>
                          </label>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div v-else class="permissions-loading">
                  <i class="fas fa-spinner fa-spin"></i>
                  <p>{{ t("roleMgmt_loading_permissions") }}</p>
                </div>
              </div>

              <div class="modal-footer">
                <button
                  type="button"
                  class="btn btn-secondary"
                  @click="closeRoleModal"
                >
                  {{ t("roleMgmt_btn_cancel") }}
                </button>
                <button
                  type="submit"
                  class="btn btn-primary"
                  :disabled="saving"
                >
                  <i v-if="saving" class="fas fa-spinner fa-spin"></i>
                  {{
                    saving
                      ? t("roleMgmt_btn_saving")
                      : isEditPermissionsOnly
                        ? t("roleMgmt_btn_updatePermissions")
                        : isEditMode
                          ? t("roleMgmt_btn_updateRole")
                          : t("roleMgmt_btn_createRole")
                  }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";

import { ref, computed, onMounted, watch } from "vue";
import { useAuthStore } from "@/stores/auth";
import accountService from "@/services/accountService";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t, tParams, languageStore } = useAdminI18n();

const permissionLabel = (perm) => {
  if (!perm) return "";
  if (languageStore.currentLanguage === "zh" && perm.permissionDisplayNameZh) {
    return perm.permissionDisplayNameZh;
  }
  return perm.permissionDisplayName || perm.permissionName || "";
};

const authStore = useAuthStore();

// 权限检查
const hasReadonlyPermission = computed(() =>
  authStore.hasPermission("page_rolemanagement_readonly"),
);
const hasCreatePermission = computed(() =>
  authStore.hasPermission("page_rolemanagement_create"),
);
const hasEditPermission = computed(() =>
  authStore.hasPermission("page_rolemanagement_edit"),
);
const hasDisablePermission = computed(() =>
  authStore.hasPermission("page_rolemanagement_disable"),
);

const searchQuery = ref("");
const loading = ref(false);
const roles = ref([]);
const specialRoleIds = ref({ salesManagerRoleId: 0, salesRoleId: 0 });

// Modal state
const showRoleModal = ref(false);
const isEditMode = ref(false);
const saving = ref(false);
const currentRoleId = ref(null);

// Role form
const roleForm = ref({
  roleName: "",
  description: "",
  isActive: true,
});

// Permissions
const allPermissions = ref([]);
const permissionsTree = ref([]);
const selectedPermissionIds = ref([]);

// Checkbox refs for indeterminate state
const groupCheckboxRefs = ref({});
const pageCheckboxRefs = ref({});

const filteredRoles = computed(() => {
  if (!searchQuery.value) return roles.value;
  const lowerQuery = searchQuery.value.toLowerCase();
  return roles.value.filter((role) => {
    return (
      role.roleName?.toLowerCase().includes(lowerQuery) ||
      role.description?.toLowerCase().includes(lowerQuery)
    );
  });
});

const isSpecialRole = (role) => {
  if (!role || !role.id) return false;
  const id = Number(role.id);
  const { salesManagerRoleId, salesRoleId } = specialRoleIds.value;
  return id === Number(salesManagerRoleId) || id === Number(salesRoleId);
};

const isEditPermissionsOnly = computed(() => {
  if (!currentRoleId.value) return false;
  const id = Number(currentRoleId.value);
  const { salesManagerRoleId, salesRoleId } = specialRoleIds.value;
  return id === Number(salesManagerRoleId) || id === Number(salesRoleId);
});

const formatDate = (dateString) => {
  if (!dateString) return "-";
  const date = new Date(dateString);
  return date.toLocaleString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
};

const fetchRoles = async () => {
  loading.value = true;
  try {
    const response = await accountService.getRoles();
    const responseData = response.data || response;
    roles.value = responseData.items || responseData || [];
  } catch (error) {
    console.error("Failed to fetch roles:", error);
    alert(t("roleMgmt_err_loadRoles"));
  } finally {
    loading.value = false;
  }
};

// Build permissions tree structure
const buildPermissionsTree = (permissions) => {
  const tree = [];
  const permissionMap = new Map();

  // First pass: create map of all permissions
  permissions.forEach((perm) => {
    permissionMap.set(perm.id, {
      ...perm,
      children: [],
    });
  });

  // Second pass: build tree structure
  permissions.forEach((perm) => {
    const node = permissionMap.get(perm.id);

    // Group title: is_menu = 1, parentId = 0
    if (perm.is_menu == 1 && (!perm.parentId || perm.parentId == 0)) {
      tree.push(node);
    }
    // Page menu: is_menu = 1, parentId > 0, route exists
    else if (perm.is_menu == 1 && perm.parentId > 0 && perm.route) {
      const parent = permissionMap.get(perm.parentId);
      if (parent) {
        parent.children.push(node);
      }
    }
    // Action: is_menu = 0, parentId > 0
    else if (perm.is_menu == 0 && perm.parentId > 0) {
      const parent = permissionMap.get(perm.parentId);
      if (parent) {
        parent.children.push(node);
      }
    }
  });

  // Sort by sortOrder (descending - higher value first)
  const sortByOrder = (arr) => {
    arr.sort((a, b) => (b.sortOrder || 0) - (a.sortOrder || 0));
    arr.forEach((item) => {
      if (item.children && item.children.length > 0) {
        sortByOrder(item.children);
      }
    });
  };
  sortByOrder(tree);

  return tree;
};

// Fetch permissions
const fetchPermissions = async () => {
  try {
    const response = await accountService.getPermissions();
    const responseData = response.data || response;
    allPermissions.value = Array.isArray(responseData)
      ? responseData
      : responseData.items || [];
    permissionsTree.value = buildPermissionsTree(allPermissions.value);
  } catch (error) {
    console.error("Failed to fetch permissions:", error);
    alert(t("roleMgmt_err_loadPermissions"));
  }
};

// Permission selection helpers
const isPermissionSelected = (permissionId) => {
  return selectedPermissionIds.value.includes(permissionId);
};

const isPageChecked = (page) => {
  if (!page.children || page.children.length === 0) {
    return isPermissionSelected(page.id);
  }
  const pageActions = page.children.filter((child) => child.is_menu == 0);
  if (pageActions.length === 0) return isPermissionSelected(page.id);
  const pageSelected = isPermissionSelected(page.id);
  const allActionsSelected = pageActions.every((action) =>
    isPermissionSelected(action.id),
  );
  return pageSelected && allActionsSelected;
};

const isPageIndeterminate = (page) => {
  if (!page.children || page.children.length === 0) {
    return false;
  }
  const pageActions = page.children.filter((child) => child.is_menu == 0);
  if (pageActions.length === 0) return false;

  const pageSelected = isPermissionSelected(page.id);
  const selectedActionsCount = pageActions.filter((action) =>
    isPermissionSelected(action.id),
  ).length;

  // Partially selected: some but not all actions are selected
  return selectedActionsCount > 0 && selectedActionsCount < pageActions.length;
};

const isGroupChecked = (group) => {
  if (!group.children || group.children.length === 0) {
    return isPermissionSelected(group.id);
  }
  const pages = group.children.filter(
    (child) => child.is_menu == 1 && child.route,
  );
  if (pages.length === 0) return false;

  // Check if all pages and their actions are selected
  return pages.every((page) => {
    const pageSelected = isPermissionSelected(page.id);
    if (!page.children || page.children.length === 0) {
      return pageSelected;
    }
    const pageActions = page.children.filter((child) => child.is_menu == 0);
    if (pageActions.length === 0) return pageSelected;
    return (
      pageSelected &&
      pageActions.every((action) => isPermissionSelected(action.id))
    );
  });
};

const isGroupIndeterminate = (group) => {
  if (!group.children || group.children.length === 0) {
    return false;
  }
  const pages = group.children.filter(
    (child) => child.is_menu == 1 && child.route,
  );
  if (pages.length === 0) return false;

  let fullySelectedCount = 0;
  let partiallySelectedCount = 0;
  let unselectedCount = 0;

  pages.forEach((page) => {
    const pageSelected = isPermissionSelected(page.id);
    let pageFullySelected = pageSelected;
    let pagePartiallySelected = false;

    if (page.children && page.children.length > 0) {
      const pageActions = page.children.filter((child) => child.is_menu == 0);
      if (pageActions.length > 0) {
        const allActionsSelected = pageActions.every((action) =>
          isPermissionSelected(action.id),
        );
        const someActionsSelected = pageActions.some((action) =>
          isPermissionSelected(action.id),
        );
        pageFullySelected = pageSelected && allActionsSelected;
        pagePartiallySelected = someActionsSelected && !allActionsSelected;
      }
    }

    if (pageFullySelected) {
      fullySelectedCount++;
    } else if (pagePartiallySelected || (pageSelected && !pageFullySelected)) {
      partiallySelectedCount++;
    } else {
      unselectedCount++;
    }
  });

  // Indeterminate if some pages are selected (fully or partially) but not all
  return (
    (fullySelectedCount > 0 || partiallySelectedCount > 0) &&
    (fullySelectedCount < pages.length || partiallySelectedCount > 0)
  );
};

// Set checkbox refs for indeterminate state
const setGroupCheckboxRef = (el, groupId) => {
  if (el) {
    groupCheckboxRefs.value[groupId] = el;
    // Use setTimeout to ensure DOM is ready
    setTimeout(() => {
      updateGroupIndeterminate(groupId);
    }, 0);
  }
};

const setPageCheckboxRef = (el, pageId) => {
  if (el) {
    pageCheckboxRefs.value[pageId] = el;
    // Use setTimeout to ensure DOM is ready
    setTimeout(() => {
      updatePageIndeterminate(pageId);
    }, 0);
  }
};

// Update indeterminate state for checkboxes
const updatePageIndeterminate = (pageId) => {
  const checkbox = pageCheckboxRefs.value[pageId];
  if (!checkbox) return;

  // Find the page in the tree
  let targetPage = null;
  for (const group of permissionsTree.value) {
    if (group.children) {
      targetPage = group.children.find((p) => p.id == pageId);
      if (targetPage) break;
    }
  }

  if (targetPage) {
    checkbox.indeterminate = isPageIndeterminate(targetPage);
  }
};

const updateGroupIndeterminate = (groupId) => {
  const checkbox = groupCheckboxRefs.value[groupId];
  if (!checkbox) return;

  const group = permissionsTree.value.find((g) => g.id == groupId);
  if (group) {
    checkbox.indeterminate = isGroupIndeterminate(group);
  }
};

// Update all indeterminate states
const updateAllIndeterminateStates = () => {
  // Update all page checkboxes
  Object.keys(pageCheckboxRefs.value).forEach((pageId) => {
    updatePageIndeterminate(parseInt(pageId));
  });

  // Update all group checkboxes
  Object.keys(groupCheckboxRefs.value).forEach((groupId) => {
    updateGroupIndeterminate(parseInt(groupId));
  });
};

const togglePermission = (permissionId, checked) => {
  if (checked) {
    if (!selectedPermissionIds.value.includes(permissionId)) {
      selectedPermissionIds.value.push(permissionId);
    }
  } else {
    selectedPermissionIds.value = selectedPermissionIds.value.filter(
      (id) => id != permissionId,
    );
  }

  // Update indeterminate states after permission change
  setTimeout(() => {
    updateAllIndeterminateStates();
  }, 0);
};

const togglePage = (page, checked) => {
  // Toggle page itself
  togglePermission(page.id, checked);

  // Toggle all actions under this page
  if (page.children && page.children.length > 0) {
    page.children.forEach((action) => {
      if (action.is_menu == 0) {
        togglePermission(action.id, checked);
      }
    });
  }

  // Update indeterminate states
  setTimeout(() => {
    updatePageIndeterminate(page.id);
    // Update parent group
    for (const group of permissionsTree.value) {
      if (group.children && group.children.some((p) => p.id == page.id)) {
        updateGroupIndeterminate(group.id);
        break;
      }
    }
  }, 0);
};

const toggleGroup = (group, checked) => {
  // Toggle group itself (if it's selectable)
  if (group.is_menu == 1 && (!group.parentId || group.parentId == 0)) {
    // Group titles are not selectable, skip
  }

  // Toggle all pages and their actions
  if (group.children && group.children.length > 0) {
    group.children.forEach((page) => {
      if (page.is_menu == 1 && page.route) {
        togglePage(page, checked);
      }
    });
  }

  // Update indeterminate states
  setTimeout(() => {
    updateGroupIndeterminate(group.id);
  }, 0);
};

// Modal functions
const openAddRoleModal = async () => {
  isEditMode.value = false;
  currentRoleId.value = null;
  roleForm.value = {
    roleName: "",
    description: "",
    isActive: true,
  };
  selectedPermissionIds.value = [];

  if (permissionsTree.value.length === 0) {
    await fetchPermissions();
  }

  showRoleModal.value = true;
};

const editRole = async (role) => {
  if (role.id == 1) {
    alert(t("roleMgmt_alert_superAdminNoEdit"));
    return;
  }

  isEditMode.value = true;
  currentRoleId.value = role.id;

  // Load role details
  try {
    const response = await accountService.getRole(role.id);
    const responseData = response.data || response;
    const roleData = responseData.role || responseData;

    roleForm.value = {
      roleName: roleData.roleName || "",
      description: roleData.description || "",
      isActive: roleData.isActive != undefined ? roleData.isActive : true,
    };

    // Load role permissions
    const permResponse = await accountService.getRolePermissions(role.id);
    const permData = permResponse.data || permResponse;
    const permissions = Array.isArray(permData)
      ? permData
      : permData.permissions || permData.items || [];
    selectedPermissionIds.value = permissions.map(
      (p) => p.id || p.permissionId,
    );

    if (permissionsTree.value.length === 0) {
      await fetchPermissions();
    }

    showRoleModal.value = true;

    // Update indeterminate states after modal is shown
    setTimeout(() => {
      updateAllIndeterminateStates();
    }, 100);
  } catch (error) {
    console.error("Failed to load role details:", error);
    alert(t("roleMgmt_err_loadRoleDetails"));
  }
};

const closeRoleModal = () => {
  showRoleModal.value = false;
  roleForm.value = {
    roleName: "",
    description: "",
    isActive: true,
  };
  selectedPermissionIds.value = [];
  currentRoleId.value = null;
};

const saveRole = async () => {
  saving.value = true;
  try {
    if (isEditPermissionsOnly.value) {
      await accountService.updateRole(currentRoleId.value, {
        description: roleForm.value.description ?? null,
        permissionIds: selectedPermissionIds.value,
      });
      alert(t("roleMgmt_alert_permUpdateSuccess"));
    } else {
      const roleData = {
        roleName: roleForm.value.roleName,
        roleDisplayName: roleForm.value.roleName,
        description: roleForm.value.description || null,
        isActive: roleForm.value.isActive ? 1 : 0,
        permissionIds: selectedPermissionIds.value,
      };
      if (isEditMode.value) {
        await accountService.updateRole(currentRoleId.value, roleData);
        alert(t("roleMgmt_alert_roleUpdated"));
      } else {
        await accountService.createRole(roleData);
        alert(t("roleMgmt_alert_roleCreated"));
      }
    }

    closeRoleModal();
    await fetchRoles();
  } catch (error) {
    console.error("Failed to save role:", error);
    const fromApi = error.response?.data?.message || error.message;
    alert(
      fromApi
        ? tParams("roleMgmt_err_saveWithMsg", "", { msg: fromApi })
        : t("roleMgmt_err_saveFallback"),
    );
  } finally {
    saving.value = false;
  }
};

const toggleRoleStatus = async (role) => {
  if (role.id == 1) {
    alert(t("roleMgmt_alert_superAdminNoDisable"));
    return;
  }

  const wasActive = role.isActive;
  if (
    !confirm(
      wasActive
        ? tParams("roleMgmt_confirm_disableRole", "", { name: role.roleName })
        : tParams("roleMgmt_confirm_enableRole", "", { name: role.roleName }),
    )
  ) {
    return;
  }

  try {
    // 切换 isActive 状态：1 变 0，0 变 1
    const newStatus = role.isActive ? 0 : 1;
    await accountService.updateRole(role.id, {
      isActive: newStatus,
    });

    alert(
      wasActive
        ? tParams("roleMgmt_alert_roleDisabledOk", "", { name: role.roleName })
        : tParams("roleMgmt_alert_roleEnabledOk", "", { name: role.roleName }),
    );
    // 刷新角色列表
    await fetchRoles();
  } catch (error) {
    console.error("Failed to toggle role status:", error);
    alert(
      tParams("roleMgmt_err_toggleWithMsg", "", {
        msg:
          error.response?.data?.message ||
          error.message ||
          t("roleMgmt_err_unknown"),
      }),
    );
  }
};

const handleFilter = () => {
  alert(t("roleMgmt_alert_filterStub"));
};

onMounted(async () => {
  try {
    const ids = await accountService.getSpecialRoleIds();
    specialRoleIds.value = {
      salesManagerRoleId: ids.salesManagerRoleId || 0,
      salesRoleId: ids.salesRoleId || 0,
    };
  } catch (e) {
    console.warn("Failed to load special role IDs:", e);
  }
  await fetchRoles();
  await fetchPermissions();
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

/* Toolbar */
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

.search-box {
  flex: 1;
  min-width: 250px;
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

/* Roles Table */
.roles-card {
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
  font-size: 13px;
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

.role-info {
  display: flex;
  align-items: center;
  gap: 10px;
}

.role-badge {
  padding: 6px 12px;
  border-radius: var(--radius-sm);
  font-size: 13px;
  font-weight: 600;
  display: inline-block;
}

.role-badge.admin {
  background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
  color: white;
}

.role-badge.manager {
  background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
  color: white;
}

.role-badge.operator {
  background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
  color: white;
}

.role-badge.viewer {
  background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
  color: white;
}

.role-badge.default {
  background: var(--color-border);
  color: var(--color-text);
}

.super-admin-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 8px;
  background: var(--color-warning-soft);
  color: var(--color-warning);
  border-radius: 4px;
  font-size: 11px;
  font-weight: 600;
}

.user-count {
  font-weight: 600;
  color: var(--color-brand);
}

.status-badge {
  padding: 6px 12px;
  border-radius: var(--radius-sm);
  font-size: 12px;
  font-weight: 600;
  display: inline-block;
}

.status-badge.active {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.status-badge.inactive {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.action-buttons {
  display: flex;
  gap: 8px;
  align-items: center;
}

.btn-icon {
  width: 36px;
  height: 36px;
  border-radius: var(--radius-sm);
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
  font-size: 14px;
}

.btn-icon:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.btn-edit {
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.btn-edit:hover:not(:disabled) {
  background: var(--color-brand-solid);
  color: white;
}

.btn-delete {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.btn-delete:hover:not(:disabled) {
  background: var(--color-danger-solid);
  color: white;
}

.no-actions-text {
  color: var(--color-faint);
  font-style: italic;
}

/* Modal Styles */
.modal {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  opacity: 0;
  visibility: hidden;
  transition: all 0.3s ease;
}

.modal.active {
  opacity: 1;
  visibility: visible;
}

.role-modal-content {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  width: 90%;
  max-width: 900px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  transform: scale(0.9);
  transition: transform 0.3s ease;
}

.modal.active .role-modal-content {
  transform: scale(1);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 24px 30px;
  border-bottom: 2px solid var(--color-border);
}

.modal-header h2 {
  font-size: 22px;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0;
}

.modal-close {
  background: none;
  border: none;
  font-size: 24px;
  color: var(--color-muted);
  cursor: pointer;
  padding: 5px;
  transition: color 0.2s ease;
}

.modal-close:hover {
  color: var(--color-ink);
}

.modal-body {
  padding: 30px;
  overflow-y: auto;
  flex: 1;
}

.form-section {
  margin-bottom: 30px;
}

.section-title {
  font-size: 16px;
  font-weight: 700;
  color: var(--color-ink);
  margin-bottom: 20px;
  padding-bottom: 10px;
  border-bottom: 2px solid var(--color-border);
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
  margin-bottom: 8px;
}

.form-group input,
.form-group textarea,
.form-group select {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  transition: all 0.3s ease;
  font-family: inherit;
  background-color: var(--color-surface);
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.form-group select {
  cursor: pointer;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%234a5568' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 12px center;
  padding-right: 40px;
}

.form-group textarea {
  resize: vertical;
  min-height: 80px;
}

/* Permissions Tree */
.permissions-tree {
  max-height: 400px;
  overflow-y: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 15px;
}

.permission-group {
  margin-bottom: 20px;
}

.permission-group:last-child {
  margin-bottom: 0;
}

.permission-group-header {
  margin-bottom: 10px;
}

.permission-page {
  margin-left: 20px;
  margin-bottom: 15px;
  padding-left: 15px;
  border-left: 2px solid var(--color-border);
}

.permission-page-header {
  margin-bottom: 8px;
}

.permission-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-left: 20px;
  margin-top: 8px;
}

.permission-checkbox {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  user-select: none;
}

.permission-checkbox input[type="checkbox"] {
  width: 18px;
  height: 18px;
  cursor: pointer;
  accent-color: var(--color-brand);
  flex-shrink: 0;
}

.permission-checkbox input[type="checkbox"]:indeterminate {
  background-color: var(--color-brand-solid);
  border-color: var(--color-brand);
  position: relative;
}

.permission-checkbox input[type="checkbox"]:indeterminate::before {
  content: "";
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 10px;
  height: 2px;
  background-color: var(--color-surface);
  border-radius: 1px;
}

.permission-label {
  font-size: 14px;
  color: var(--color-ink);
}

.permission-group-header .permission-label {
  font-weight: 700;
  font-size: 15px;
  color: var(--color-ink);
}

.permission-page-header .permission-label {
  font-weight: 600;
  color: var(--color-text);
}

.action-checkbox .permission-label {
  font-weight: 400;
  color: var(--color-muted);
}

.permissions-loading {
  text-align: center;
  padding: 40px;
  color: var(--color-muted);
}

.permissions-loading i {
  font-size: 24px;
  margin-bottom: 10px;
  color: var(--color-brand);
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding-top: 20px;
  border-top: 2px solid var(--color-border);
  margin-top: 20px;
}
</style>
