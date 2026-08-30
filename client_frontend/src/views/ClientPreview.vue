<template>
  <div class="client-preview-entry">
    <div v-if="loading" class="preview-loading">
      <i class="fas fa-spinner fa-spin"></i>
      <p>Loading preview...</p>
    </div>
    <div v-else-if="error" class="preview-error">
      <i class="fas fa-exclamation-circle"></i>
      <p>{{ error }}</p>
      <router-link to="/client/login" class="preview-link"
        >Go to login</router-link
      >
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useClientAuthStore } from "@/stores/clientAuth";
import { getPreviewSession } from "@/services/previewSessionService";

const route = useRoute();
const router = useRouter();
const clientAuthStore = useClientAuthStore();

const loading = ref(true);
const error = ref(null);

onMounted(async () => {
  const token = route.query.token;
  if (!token) {
    error.value = "Preview token is missing.";
    loading.value = false;
    return;
  }
  try {
    const res = await getPreviewSession(token);
    const data = res?.data ?? res;
    const user = data.user;
    if (!user || !data.clientUserId) {
      error.value = "Invalid preview session response.";
      loading.value = false;
      return;
    }
    clientAuthStore.setPreviewSession(user, token);
    await clientAuthStore.loadPreviewProfile();
    router.replace("/client/dashboard");
  } catch (e) {
    error.value =
      e?.message || e?.data?.message || "Invalid or expired preview token.";
    loading.value = false;
  }
});
</script>

<style scoped>
.client-preview-entry {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-canvas);
}
.preview-loading,
.preview-error {
  text-align: center;
  padding: 2rem;
}
.preview-loading i,
.preview-error i {
  font-size: 2rem;
  color: var(--color-muted);
  margin-bottom: 1rem;
}
.preview-error i {
  color: var(--color-danger);
}
.preview-error p {
  color: var(--color-ink);
  margin-bottom: 1rem;
}
.preview-link {
  color: var(--color-brand);
  text-decoration: none;
}
.preview-link:hover {
  text-decoration: underline;
}
</style>
