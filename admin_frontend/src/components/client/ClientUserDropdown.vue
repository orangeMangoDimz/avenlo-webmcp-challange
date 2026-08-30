<template>
  <div class="user-menu" :class="{ active: isActive }" ref="userMenuRef">
    <div class="user-menu-trigger" @click="toggleMenu">
      <div class="user-avatar">{{ userInitials }}</div>
      <div class="user-info">
        <div class="user-name">{{ user.name }}</div>
        <div class="user-role">{{ user.role }}</div>
      </div>
      <i class="fas fa-chevron-down user-menu-arrow"></i>
    </div>

    <div v-if="isActive" class="user-dropdown">
      <div class="user-dropdown-header">
        <div class="user-name">{{ user.name }}</div>
        <div class="user-email">{{ user.email }}</div>
      </div>

      <router-link
        to="/client/profile"
        class="user-dropdown-item"
        @click="closeMenu"
      >
        <i class="fas fa-user"></i>
        <span>My Profile</span>
      </router-link>

      <router-link
        to="/client/settings"
        class="user-dropdown-item"
        @click="closeMenu"
      >
        <i class="fas fa-cog"></i>
        <span>Account Settings</span>
      </router-link>

      <router-link
        to="/client/security"
        class="user-dropdown-item"
        @click="closeMenu"
      >
        <i class="fas fa-key"></i>
        <span>Change Password</span>
      </router-link>

      <div class="user-dropdown-divider"></div>

      <router-link
        to="/client/help"
        class="user-dropdown-item"
        @click="closeMenu"
      >
        <i class="fas fa-question-circle"></i>
        <span>Help & Support</span>
      </router-link>

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
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import { useRouter } from "vue-router";
import { useClientAuthStore } from "@/stores/clientAuth";

const router = useRouter();
const clientAuthStore = useClientAuthStore();
const isActive = ref(false);
const userMenuRef = ref(null);

// Get user data from store
const user = computed(() => {
  if (clientAuthStore.user) {
    return {
      name:
        `${clientAuthStore.user.firstName || ""} ${clientAuthStore.user.lastName || ""}`.trim() ||
        "Client",
      email: clientAuthStore.user.email || "N/A",
      role: "Client Account",
    };
  }
  return {
    name: "Client",
    email: "N/A",
    role: "Client Account",
  };
});

const userInitials = computed(() => {
  return clientAuthStore.userInitials || "CL";
});

const toggleMenu = () => {
  isActive.value = !isActive.value;
};

const closeMenu = () => {
  isActive.value = false;
};

const handleLogout = async () => {
  if (confirm("Are you sure you want to logout?")) {
    // Use store logout method
    await clientAuthStore.logout();

    // Redirect to login page
    router.push("/client/login");
  }
};

const handleClickOutside = (event) => {
  if (userMenuRef.value && !userMenuRef.value.contains(event.target)) {
    isActive.value = false;
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
  border: 2px solid var(--color-border);
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
  background: var(--color-brand-solid);
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
  z-index: 1000;
  overflow: hidden;
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

@media (max-width: 768px) {
  .user-info {
    display: none;
  }
}
</style>
