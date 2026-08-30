<template>
  <div class="user-menu" :class="{ active: isOpen }" ref="userMenuRef">
    <div class="user-menu-trigger" @click="toggleMenu">
      <div class="user-avatar" :style="{ background: avatarColor }">
        {{ userInitials }}
      </div>
      <div class="user-info">
        <div class="user-name">{{ userName }}</div>
        <div class="user-role">{{ userRole }}</div>
      </div>
      <i class="fas fa-chevron-down user-menu-arrow"></i>
    </div>
    <div class="user-dropdown">
      <div class="user-dropdown-header">
        <div class="user-name">{{ userName }}</div>
        <div class="user-email">{{ userEmail }}</div>
      </div>
      <a href="#" class="user-dropdown-item" @click.prevent="openProfileModal">
        <i class="fas fa-user"></i>
        <span>My Profile</span>
      </a>
      <a
        href="#"
        class="user-dropdown-item"
        @click.prevent="openChangePasswordModal"
      >
        <i class="fas fa-key"></i>
        <span>Change Password</span>
      </a>
      <div class="user-dropdown-divider"></div>
      <a
        href="#"
        class="user-dropdown-item danger"
        @click.prevent="handleLogout"
      >
        <i class="fas fa-sign-out-alt"></i>
        <span>Logout</span>
      </a>
    </div>
  </div>

  <!-- Profile Modal -->
  <Teleport to="body">
    <div
      class="modal"
      :class="{ active: showProfileModal }"
      @click="closeProfileModal"
    >
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h2><i class="fas fa-user"></i> My Profile</h2>
          <button class="modal-close" @click="closeProfileModal">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="saveProfile">
            <div class="form-group">
              <label>Full Name *</label>
              <input
                type="text"
                v-model="profileForm.fullName"
                placeholder="Enter full name"
                required
              />
            </div>
            <div class="form-group">
              <label>Email Address *</label>
              <input
                type="email"
                v-model="profileForm.email"
                placeholder="Enter email address"
                required
              />
            </div>
            <div class="form-group">
              <label>Phone Number</label>
              <input
                type="tel"
                v-model="profileForm.phone"
                placeholder="Enter phone number"
              />
            </div>
            <div class="form-group">
              <label>Department</label>
              <input
                type="text"
                v-model="profileForm.department"
                placeholder="Enter department"
              />
            </div>
            <div class="form-group">
              <label>Role</label>
              <input
                type="text"
                :value="userRole"
                disabled
                style="
                  background: var(--color-surface-soft);
                  color: var(--color-muted);
                "
              />
            </div>
            <div class="info-box">
              <p>
                <i class="fas fa-info-circle"></i> <strong>Note:</strong> Role
                and account status can only be modified by super administrators.
              </p>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="closeProfileModal">
            Cancel
          </button>
          <button class="btn btn-primary" @click="saveProfile">
            <i class="fas fa-save"></i> Save Changes
          </button>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- Change Password Modal -->
  <Teleport to="body">
    <div
      class="modal"
      :class="{ active: showPasswordModal }"
      @click="closePasswordModal"
    >
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h2><i class="fas fa-key"></i> Change Password</h2>
          <button class="modal-close" @click="closePasswordModal">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="savePassword">
            <div class="form-group">
              <label>Current Password *</label>
              <div class="password-input-wrapper">
                <input
                  :type="showCurrentPassword ? 'text' : 'password'"
                  v-model="passwordForm.currentPassword"
                  placeholder="Enter current password"
                  required
                  class="password-input"
                />
                <button
                  type="button"
                  class="password-toggle-btn"
                  @click="toggleCurrentPassword"
                  tabindex="-1"
                >
                  <i
                    :class="
                      showCurrentPassword ? 'fas fa-eye-slash' : 'fas fa-eye'
                    "
                  ></i>
                </button>
              </div>
            </div>
            <div class="form-group">
              <label>New Password *</label>
              <div class="password-input-wrapper">
                <input
                  :type="showNewPassword ? 'text' : 'password'"
                  v-model="passwordForm.newPassword"
                  placeholder="Enter new password"
                  required
                  class="password-input"
                />
                <button
                  type="button"
                  class="password-toggle-btn"
                  @click="toggleNewPassword"
                  tabindex="-1"
                >
                  <i
                    :class="showNewPassword ? 'fas fa-eye-slash' : 'fas fa-eye'"
                  ></i>
                </button>
              </div>
              <div style="margin-top: 8px">
                <small style="color: var(--color-muted); font-size: 14px">
                  <i class="fas fa-info-circle"></i> Password must be at least 8
                  characters long and contain uppercase, lowercase, number, and
                  special character.
                </small>
              </div>
            </div>
            <div class="form-group">
              <label>Confirm New Password *</label>
              <div class="password-input-wrapper">
                <input
                  :type="showConfirmPassword ? 'text' : 'password'"
                  v-model="passwordForm.confirmPassword"
                  placeholder="Confirm new password"
                  required
                  class="password-input"
                />
                <button
                  type="button"
                  class="password-toggle-btn"
                  @click="toggleConfirmPassword"
                  tabindex="-1"
                >
                  <i
                    :class="
                      showConfirmPassword ? 'fas fa-eye-slash' : 'fas fa-eye'
                    "
                  ></i>
                </button>
              </div>
            </div>
            <div class="info-box">
              <p>
                <i class="fas fa-shield-alt"></i>
                <strong>Security Tip:</strong> Use a strong password that you
                don't use for other accounts.
              </p>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="closePasswordModal">
            Cancel
          </button>
          <button class="btn btn-primary" @click="savePassword">
            <i class="fas fa-check"></i> Update Password
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth";

const router = useRouter();
const authStore = useAuthStore();

const isOpen = ref(false);
const userMenuRef = ref(null);
const showProfileModal = ref(false);
const showPasswordModal = ref(false);

const profileForm = ref({
  fullName: "",
  email: "",
  phone: "",
  department: "",
});

const passwordForm = ref({
  currentPassword: "",
  newPassword: "",
  confirmPassword: "",
});

// Password visibility toggles
const showCurrentPassword = ref(false);
const showNewPassword = ref(false);
const showConfirmPassword = ref(false);

const userName = computed(() => authStore.user?.fullName || "");
const userEmail = computed(() => authStore.user?.email || "");
const userRole = computed(() => authStore.user?.roleDisplayName || "");
const userInitials = computed(() => authStore.userInitials);
const avatarColor = computed(
  () =>
    authStore.user?.avatarColor ||
    "linear-gradient(135deg, var(--color-brand) 0%, var(--color-brand-strong) 100%)",
);

const toggleMenu = () => {
  isOpen.value = !isOpen.value;
};

const closeMenu = () => {
  isOpen.value = false;
};

const openProfileModal = () => {
  profileForm.value = {
    fullName: authStore.user?.fullName || "",
    email: authStore.user?.email || "",
    phone: authStore.user?.phone || "",
    department: authStore.user?.department || "",
  };
  showProfileModal.value = true;
  closeMenu();
};

const closeProfileModal = () => {
  showProfileModal.value = false;
};

const openChangePasswordModal = () => {
  passwordForm.value = {
    currentPassword: "",
    newPassword: "",
    confirmPassword: "",
  };
  // Reset password visibility states
  showCurrentPassword.value = false;
  showNewPassword.value = false;
  showConfirmPassword.value = false;
  showPasswordModal.value = true;
  closeMenu();
};

const toggleCurrentPassword = () => {
  showCurrentPassword.value = !showCurrentPassword.value;
};

const toggleNewPassword = () => {
  showNewPassword.value = !showNewPassword.value;
};

const toggleConfirmPassword = () => {
  showConfirmPassword.value = !showConfirmPassword.value;
};

const closePasswordModal = () => {
  showPasswordModal.value = false;
};

const saveProfile = async () => {
  if (!profileForm.value.fullName || !profileForm.value.email) {
    alert("Please fill in all required fields.");
    return;
  }

  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!emailRegex.test(profileForm.value.email)) {
    alert("Please enter a valid email address.");
    return;
  }

  const result = await authStore.updateProfile(profileForm.value);
  if (result.success) {
    alert("✓ Profile updated successfully!");
    closeProfileModal();
  } else {
    alert(`Failed to update profile: ${result.error}`);
  }
};

const savePassword = async () => {
  if (
    !passwordForm.value.currentPassword ||
    !passwordForm.value.newPassword ||
    !passwordForm.value.confirmPassword
  ) {
    alert("Please fill in all required fields.");
    return;
  }

  if (passwordForm.value.newPassword !== passwordForm.value.confirmPassword) {
    alert("New password and confirm password do not match.");
    return;
  }

  const passwordRegex =
    /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
  if (!passwordRegex.test(passwordForm.value.newPassword)) {
    alert(
      "Password must be at least 8 characters long and contain:\n- At least one uppercase letter\n- At least one lowercase letter\n- At least one number\n- At least one special character (@$!%*?&)",
    );
    return;
  }

  if (passwordForm.value.currentPassword === passwordForm.value.newPassword) {
    alert("New password must be different from current password.");
    return;
  }

  const result = await authStore.changePassword(passwordForm.value);
  if (result.success) {
    alert(
      "✓ Password updated successfully!\n\nPlease use your new password for future logins.",
    );
    closePasswordModal();
  } else {
    alert(`Failed to change password: ${result.error}`);
  }
};

const handleLogout = async () => {
  if (confirm("Are you sure you want to logout?")) {
    await authStore.logout();
    router.push("/login");
  }
};

const handleClickOutside = (event) => {
  if (userMenuRef.value && !userMenuRef.value.contains(event.target)) {
    closeMenu();
  }
};

onMounted(() => {
  document.addEventListener("click", handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener("click", handleClickOutside);
});
</script>

<style scoped>
.user-menu {
  position: relative;
}

.user-menu-trigger {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 12px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  cursor: pointer;
  transition: all 0.3s ease;
}

.user-menu-trigger:hover {
  border-color: var(--color-brand);
  background: var(--color-brand-soft);
}

.user-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: 600;
  font-size: 14px;
}

.user-info {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
}

.user-name {
  font-weight: 600;
  font-size: 14px;
  color: var(--color-ink);
}

.user-role {
  font-size: 14px;
  color: var(--color-muted);
}

.user-menu-arrow {
  color: var(--color-faint);
  /* @font-floor-exempt: visual-only dropdown glyph */
  font-size: 12px;
  transition: transform 0.3s ease;
}

.user-menu.active .user-menu-arrow {
  transform: rotate(180deg);
}

.user-dropdown {
  position: absolute;
  top: calc(100% + 10px);
  right: 0;
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
  min-width: 220px;
  display: none;
  z-index: 1000;
  overflow: hidden;
}

.user-menu.active .user-dropdown {
  display: block;
  animation: slideDown 0.3s ease;
}

@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.user-dropdown-header {
  padding: 15px 20px;
  background: var(--color-surface-soft);
  border-bottom: 1px solid var(--color-border);
}

.user-dropdown-header .user-name {
  font-size: 15px;
  margin-bottom: 3px;
}

.user-dropdown-header .user-email {
  font-size: 14px;
  color: var(--color-muted);
}

.user-dropdown-item {
  padding: 12px 20px;
  display: flex;
  align-items: center;
  gap: 12px;
  color: var(--color-text);
  text-decoration: none;
  transition: all 0.2s ease;
  cursor: pointer;
  font-size: 14px;
}

.user-dropdown-item:hover {
  background: var(--color-surface-soft);
  color: var(--color-brand);
}

.user-dropdown-item i {
  width: 20px;
  text-align: center;
  color: var(--color-faint);
}

.user-dropdown-item:hover i {
  color: var(--color-brand);
}

.user-dropdown-divider {
  height: 1px;
  background: var(--color-border);
  margin: 5px 0;
}

.user-dropdown-item.danger {
  color: var(--color-danger);
}

.user-dropdown-item.danger:hover {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.user-dropdown-item.danger i {
  color: var(--color-danger-border);
}

.user-dropdown-item.danger:hover i {
  color: var(--color-danger);
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
  border-bottom: 1px solid var(--color-border);
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

.form-group input {
  width: 100%;
  padding: 12px 16px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  transition: all 0.3s ease;
  background: var(--color-surface);
}

.form-group input:focus {
  border-color: var(--color-brand);
  box-shadow: none;
}

.info-box {
  background: var(--color-brand-soft);
  border-left: 1px solid var(--color-brand);
  padding: 15px 20px;
  border-radius: var(--radius-md);
  margin-top: 15px;
}

.info-box p {
  color: var(--color-text);
  font-size: 14px;
  line-height: 1.6;
}

.info-box i {
  margin-right: 6px;
  color: var(--color-brand);
}

.modal-footer {
  padding: 20px 30px;
  border-top: 1px solid var(--color-border);
  display: flex;
  justify-content: flex-end;
  gap: 12px;
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

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(var(--color-brand-rgb), 0.5);
}

.btn-secondary {
  background: var(--color-surface);
  color: var(--color-text);
  border: 1px solid var(--color-border);
}

.btn-secondary:hover {
  border-color: var(--color-brand);
  color: var(--color-brand);
  background: var(--color-brand-soft);
}

/* Password input with toggle button */
.password-input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.password-input {
  width: 100%;
  padding: 12px 45px 12px 16px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  transition: all 0.3s ease;
  background: var(--color-surface);
}

.password-input:focus {
  border-color: var(--color-brand);
  box-shadow: none;
}

.password-toggle-btn {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  cursor: pointer;
  padding: 4px 8px;
  color: var(--color-muted);
  font-size: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
  border-radius: 4px;
  width: 32px;
  height: 32px;
}

.password-toggle-btn:hover {
  color: var(--color-brand);
  background: var(--color-brand-soft);
}

.password-toggle-btn:focus {
  color: var(--color-brand);
  background: var(--color-brand-soft);
}

.password-toggle-btn i {
  pointer-events: none;
}

@media (max-width: 768px) {
  .user-info {
    display: none;
  }
}
</style>
