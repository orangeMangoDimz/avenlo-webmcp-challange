<template>
  <div class="workspace-shell">
    <a
      class="skip-link"
      href="#workspace-main"
      :inert="navigationOpen ? '' : undefined"
      :aria-hidden="navigationOpen ? 'true' : undefined"
      >{{ t("skipToContent", "Skip to content") }}</a
    >
    <ClientSidebar
      :mobile-open="navigationOpen"
      @close-mobile="closeNavigation"
    />

    <header
      class="workspace-topbar"
      :inert="navigationOpen ? '' : undefined"
      :aria-hidden="navigationOpen ? 'true' : undefined"
    >
      <div class="workspace-brand">
        <span class="workspace-brand-mark">A</span
        ><span>{{ t("clientPortal", "Client Portal") }}</span>
      </div>
      <button
        ref="navigationButton"
        type="button"
        class="workspace-navigate-button"
        :aria-expanded="navigationOpen"
        aria-controls="clientSidebar"
        @click="openNavigation"
      >
        <i class="fas fa-bars" aria-hidden="true"></i
        >{{ t("navigate", "Navigate") }}
      </button>
      <div class="workspace-context">
        <span>{{ t("workspace", "Workspace") }}</span
        ><strong>{{ pageTitle }}</strong>
      </div>
      <div class="workspace-actions">
        <div class="language-switcher">
          <button
            type="button"
            class="workspace-action language-switcher-trigger"
            :aria-expanded="showLanguageDropdown"
            @click="showLanguageDropdown = !showLanguageDropdown"
          >
            <i class="fas fa-globe" aria-hidden="true"></i
            ><span>{{ languageStore.currentLanguageName }}</span>
          </button>
          <div v-if="showLanguageDropdown" class="language-dropdown">
            <button
              v-for="lang in languageStore.enabledLanguages"
              :key="lang.languageCode"
              type="button"
              class="language-option"
              :class="{
                active: languageStore.currentLanguage === lang.languageCode,
              }"
              @click="changeLanguage(lang.languageCode)"
            >
              {{ lang.languageName }}
            </button>
          </div>
        </div>
        <ThemeToggle />
        <ClientNotification />
        <ClientUserDropdown />
      </div>
    </header>

    <div
      v-if="clientAuthStore.isPreviewMode"
      class="workspace-preview-banner"
      :inert="navigationOpen ? '' : undefined"
      :aria-hidden="navigationOpen ? 'true' : undefined"
    >
      <i class="fas fa-eye" aria-hidden="true"></i> Preview mode (read-only) —
      View as client
    </div>
    <main
      id="workspace-main"
      class="workspace-main"
      tabindex="-1"
      :inert="navigationOpen ? '' : undefined"
      :aria-hidden="navigationOpen ? 'true' : undefined"
    >
      <div class="workspace-page-context">
        <i :class="pageIcon" aria-hidden="true"></i>
        <div>
          <h1>{{ pageTitle }}</h1>
          <p v-if="pageSubtitle">{{ pageSubtitle }}</p>
        </div>
        <span
          v-if="showIbStatusBadge && ibTierDisplayName"
          class="workspace-tier"
          >{{ ibTierDisplayName }}</span
        >
      </div>
      <router-view />
    </main>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from "vue";
import { useRoute } from "vue-router";
import { useLanguageStore } from "@/stores/language";
import { useClientAuthStore } from "@/stores/clientAuth";
import ClientSidebar from "@/components/client/ClientSidebar.vue";
import ClientNotification from "@/components/client/ClientNotification.vue";
import ClientUserDropdown from "@/components/client/ClientUserDropdown.vue";
import ThemeToggle from "@/components/layout/ThemeToggle.vue";

const route = useRoute();
const languageStore = useLanguageStore();
const clientAuthStore = useClientAuthStore();
const navigationOpen = ref(false);
const navigationButton = ref(null);
const showLanguageDropdown = ref(false);
const t = (key, fallback = "") => languageStore.t(key, fallback);

const normalizeTransactionResultType = (type) => {
  const normalized = String(type || "")
    .trim()
    .toLowerCase();
  if (normalized === "withdraw" || normalized === "withdrawal")
    return "withdrawal";
  if (normalized === "transfer" || normalized === "internal_transfer")
    return "internal_transfer";
  return "deposit";
};
const transactionResultStatus = computed(
  () =>
    ({
      "client-transactions-success": "success",
      "client-transactions-fail": "fail",
      "client-transactions-pending": "pending",
    })[route.name] || "",
);
const transactionResultType = computed(() =>
  normalizeTransactionResultType(route.query.type),
);
const customResult = computed(
  () =>
    transactionResultStatus.value && transactionResultType.value !== "deposit",
);
const transactionCopy = computed(() => {
  const status = transactionResultStatus.value;
  if (transactionResultType.value === "withdrawal") {
    if (status === "success")
      return {
        title: t("transWithdrawalSubmitted", "Withdrawal Submitted!"),
        subtitle: t(
          "transWithdrawalSubmittedMessage",
          "Your withdrawal request has been submitted successfully and is being processed.",
        ),
      };
    if (status === "pending")
      return {
        title: t("transWithdrawalPendingTitle", "Withdrawal Pending"),
        subtitle: t(
          "transWithdrawalPendingMessage",
          "Your withdrawal request has been submitted and is waiting for the payment channel response.",
        ),
      };
    return {
      title: t("transWithdrawalFailedTitle", "Withdrawal Failed"),
      subtitle: t(
        "transWithdrawalFailedMessage",
        "Your withdrawal request was not completed.",
      ),
    };
  }
  if (status === "success")
    return {
      title: t("transInternalTransferSubmitted", "Transfer Submitted!"),
      subtitle: t(
        "transAlertTransferSuccess",
        "Your internal transfer request has been submitted successfully and is being processed.",
      ),
    };
  if (status === "pending")
    return {
      title: t("transTransferPendingTitle", "Transfer Pending"),
      subtitle: t(
        "transTransferPendingMessage",
        "Your internal transfer request has been submitted and is waiting to be processed.",
      ),
    };
  return {
    title: t("transTransferFailedTitle", "Transfer Failed"),
    subtitle: t(
      "transTransferFailedMessage",
      "Your internal transfer request was not completed.",
    ),
  };
});
const pageTitle = computed(() =>
  customResult.value
    ? transactionCopy.value.title
    : t(`pageTitle_${route.name}`, route.meta.title || "Client Portal"),
);
const pageSubtitle = computed(() =>
  customResult.value
    ? transactionCopy.value.subtitle
    : t(`pageSubtitle_${route.name}`, route.meta.subtitle || ""),
);
const pageIcon = computed(() => route.meta.icon || "fas fa-home");
const showIbStatusBadge = computed(
  () =>
    route.name === "client-ib-dashboard-active" && clientAuthStore.isIbApproved,
);
const ibTierDisplayName = computed(() =>
  clientAuthStore.ibStatus?.tierLevel == null ||
  clientAuthStore.ibStatus?.tierLevel === ""
    ? ""
    : `Tier ${clientAuthStore.ibStatus.tierLevel}`,
);
const changeLanguage = async (langCode) => {
  showLanguageDropdown.value = false;
  await languageStore.changeLanguage(langCode);
};
const handleClickOutside = (event) => {
  if (!event.target.closest(".language-switcher"))
    showLanguageDropdown.value = false;
};
const openNavigation = () => {
  navigationOpen.value = true;
};
const closeNavigation = async () => {
  navigationOpen.value = false;
  await nextTick();
  navigationButton.value?.focus();
};

onMounted(async () => {
  if (!languageStore.enabledLanguages.length)
    await languageStore.initLanguage();
  else await languageStore.loadClientPortalTranslations();
  document.addEventListener("click", handleClickOutside);
});
onUnmounted(() => document.removeEventListener("click", handleClickOutside));
</script>
