<template>
  <div v-if="topbar" class="page-header-actions ui-page-actions">
    <AdminLanguageSwitcher />
    <ThemeToggle />
    <AdminUserGuideLink />
    <Notification />
    <UserDropdown />
  </div>
</template>

<script setup>
import AdminUserGuideLink from "@/components/layout/AdminUserGuideLink.vue";
import AdminLanguageSwitcher from "@/components/layout/AdminLanguageSwitcher.vue";
import ThemeToggle from "@/components/layout/ThemeToggle.vue";
import Notification from "@/components/layout/Notification.vue";
import UserDropdown from "@/components/layout/UserDropdown.vue";

defineProps({
  topbar: {
    type: Boolean,
    default: false,
  },
});
</script>

<style scoped>
.page-header-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  justify-content: flex-end;
}
</style>
