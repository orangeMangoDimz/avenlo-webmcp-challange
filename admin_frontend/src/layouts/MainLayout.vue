<template>
  <div
    class="workspace-shell"
    :class="{ 'sidebar-pinned': sidebarPinned }"
    @keydown.esc="closeNavigation"
  >
    <header
      class="workspace-topbar"
      :inert="navigationModalOpen"
      :aria-hidden="navigationModalOpen || undefined"
    >
      <div class="workspace-brand" aria-label="Avenlo control center">
        <span class="workspace-brand-monogram" aria-hidden="true">A</span>
        <span class="workspace-brand-copy">
          <strong>Avenlo</strong>
          <small>Control center</small>
        </span>
      </div>
      <button
        ref="menuButton"
        type="button"
        class="workspace-navigate-button"
        aria-controls="sidebar"
        :aria-expanded="isNavigationOpen || sidebarPinned"
        @click="openNavigation"
      >
        <i class="fas fa-bars" aria-hidden="true"></i>
        <span>Menu</span>
      </button>
      <PageHeaderActions topbar class="workspace-header-actions" />
    </header>
    <button
      type="button"
      class="workspace-nav-backdrop"
      :class="{ 'is-visible': navigationModalOpen }"
      aria-controls="sidebar"
      aria-label="Close navigation"
      aria-hidden="true"
      tabindex="-1"
      @click="closeNavigation"
    ></button>
    <Sidebar
      :open="isNavigationOpen"
      :pinned="sidebarPinned"
      @close="closeNavigation"
    />
    <main
      id="workspace-main"
      class="workspace-main"
      :inert="navigationModalOpen"
      :aria-hidden="navigationModalOpen || undefined"
    >
      <router-view :key="viewKey" />
    </main>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import Sidebar from "@/components/layout/Sidebar.vue";
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";
import { registerAdminClientWebMcpTools } from "@/services/adminClientWebMcp";
import {
  isWebMcpEnabled,
  subscribeWebMcpEnabled,
} from "@/services/adminWebMcpSettings";
import {
  toggleSidebarFromMenu,
  useSidebarPinned,
} from "@/composables/useSidebarPinned";

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const isNavigationOpen = ref(false);
const menuButton = ref(null);
const {
  pinned: sidebarPinnedPreference,
  isDesktop,
  effectivePinned: sidebarPinned,
  setPinned,
} = useSidebarPinned();
const navigationModalOpen = computed(
  () => isNavigationOpen.value && !sidebarPinned.value,
);
let unregisterAdminClientWebMcp = () => {};
let unsubscribeWebMcpSetting = () => {};

const syncWebMcpRegistration = (enabled = isWebMcpEnabled()) => {
  unregisterAdminClientWebMcp();
  unregisterAdminClientWebMcp = () => {};

  if (enabled) {
    unregisterAdminClientWebMcp = registerAdminClientWebMcpTools({
      authStore,
      router,
    });
  }
};

const closeNavigation = async () => {
  if (!isNavigationOpen.value) return;
  isNavigationOpen.value = false;
  await nextTick();
  menuButton.value?.focus();
};
const openNavigation = () => {
  isNavigationOpen.value = toggleSidebarFromMenu({
    pinned: sidebarPinnedPreference.value,
    open: isNavigationOpen.value,
    isDesktop: isDesktop.value,
    setPinned,
  });
};
const viewKey = computed(() => {
  const name = String(route.name || "");
  if (name.startsWith("custom-report")) return route.fullPath;
  return name;
});

onMounted(() => {
  syncWebMcpRegistration();
  unsubscribeWebMcpSetting = subscribeWebMcpEnabled((enabled) => {
    syncWebMcpRegistration(enabled);
  });
});

onUnmounted(() => {
  unsubscribeWebMcpSetting();
  unregisterAdminClientWebMcp();
});
</script>
