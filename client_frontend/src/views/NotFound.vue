<template>
  <div class="not-found-page">
    <div class="not-found-container">
      <div class="error-code">404</div>
      <h1>{{ t("pageNotFound", "Page Not Found") }}</h1>
      <p>
        {{
          t(
            "pageNotFoundDesc",
            "The page you're looking for doesn't exist or has been moved.",
          )
        }}
      </p>
      <div class="action-buttons">
        <router-link to="/" class="btn btn-primary">
          <i class="fas fa-home"></i>
          {{ t("goToDashboard", "Go to Dashboard") }}
        </router-link>
        <button class="btn btn-secondary" @click="goBack">
          <i class="fas fa-arrow-left"></i>
          {{ t("goBack", "Go Back") }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useRouter } from "vue-router";
import { useLanguageStore } from "@/stores/language";

const router = useRouter();
const languageStore = useLanguageStore();
const t = (key, fallback = "") => languageStore.t(key, fallback);

const goBack = () => {
  router.back();
};
</script>

<style scoped>
.not-found-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: var(--color-canvas);
}

.not-found-container {
  text-align: center;
  max-width: 600px;
}

.error-code {
  font-size: 120px;
  font-weight: 700;
  background: var(--color-brand);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  margin-bottom: 20px;
}

h1 {
  font-size: 32px;
  color: var(--color-ink);
  margin-bottom: 15px;
}

p {
  font-size: 16px;
  color: var(--color-muted);
  margin-bottom: 40px;
}

.action-buttons {
  display: flex;
  gap: 15px;
  justify-content: center;
  flex-wrap: wrap;
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
  text-decoration: none;
}

.btn-primary {
  background: var(--color-brand);
  color: white;
  box-shadow: 0 4px 15px rgba(var(--color-brand-rgb), 0.4);
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(var(--color-brand-rgb), 0.5);
}

.btn-secondary {
  background: white;
  color: var(--color-text);
  border: 2px solid var(--color-border);
}

.btn-secondary:hover {
  border-color: var(--color-brand);
  color: var(--color-brand);
  background: var(--color-brand-soft);
}
</style>
