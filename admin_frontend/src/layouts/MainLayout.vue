<template>
  <div class="workspace-shell" @keydown.esc="closeNavigation">
    <header
      class="workspace-topbar"
      :inert="isNavigationOpen"
      :aria-hidden="isNavigationOpen || undefined"
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
        :aria-expanded="isNavigationOpen"
        @click="isNavigationOpen = true"
      >
        <i class="fas fa-bars" aria-hidden="true"></i>
        <span>Menu</span>
      </button>
      <PageHeaderActions topbar class="workspace-header-actions" />
    </header>
    <button
      type="button"
      class="workspace-nav-backdrop"
      :class="{ 'is-visible': isNavigationOpen }"
      aria-controls="sidebar"
      aria-label="Close navigation"
      aria-hidden="true"
      tabindex="-1"
      @click="closeNavigation"
    ></button>
    <Sidebar :open="isNavigationOpen" @close="closeNavigation" />
    <main
      id="workspace-main"
      class="workspace-main"
      :inert="isNavigationOpen"
      :aria-hidden="isNavigationOpen || undefined"
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

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const isNavigationOpen = ref(false);
const menuButton = ref(null);
let unregisterAdminClientWebMcp = () => {};

const closeNavigation = async () => {
  if (!isNavigationOpen.value) return;
  isNavigationOpen.value = false;
  await nextTick();
  menuButton.value?.focus();
};
const viewKey = computed(() => {
  const name = String(route.name || "");
  if (name.startsWith("custom-report")) return route.fullPath;
  return name;
});

onMounted(() => {
  unregisterAdminClientWebMcp = registerAdminClientWebMcpTools({
    authStore,
    router,
  });
});

onUnmounted(() => {
  unregisterAdminClientWebMcp();
});
</script>
