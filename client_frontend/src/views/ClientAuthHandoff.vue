<template>
  <div class="handoff-entry">
    <div v-if="loading" class="handoff-loading">
      <i class="fas fa-spinner fa-spin"></i>
      <p>Signing you in…</p>
    </div>
    <div v-else-if="error" class="handoff-error">
      <i class="fas fa-exclamation-circle"></i>
      <p>{{ error }}</p>
      <router-link to="/client/login" class="handoff-link"
        >Go to login</router-link
      >
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useClientAuthStore } from "@/stores/clientAuth";
import clientAuthService from "@/services/clientAuthService";

const route = useRoute();
const router = useRouter();
const clientAuthStore = useClientAuthStore();

const loading = ref(true);
const error = ref(null);

const FALLBACK_REDIRECT = "/client/transactions";

// 同一 code 在本页生命周期内只允许真正发一次请求。
// 防御 dev 模式 HMR 双挂载 / WebView 双加载。
const inflight = new Map();

function safeRedirect(raw) {
  if (typeof raw !== "string" || raw === "") return FALLBACK_REDIRECT;
  if (raw[0] !== "/") return FALLBACK_REDIRECT;
  if (raw.startsWith("//")) return FALLBACK_REDIRECT;
  if (/^\/[a-z][a-z0-9+\-.]*:/i.test(raw)) return FALLBACK_REDIRECT;
  return raw;
}

async function exchangeOnce(code) {
  if (inflight.has(code)) return inflight.get(code);
  const p = clientAuthService.exchangeHandoff(code);
  inflight.set(code, p);
  return p;
}

onMounted(async () => {
  const code = route.query.code;

  if (!code) {
    error.value = "Handoff code is missing.";
    loading.value = false;
    return;
  }

  try {
    const res = await exchangeOnce(code);
    const data = res?.data ?? res;

    const token = data?.token;
    const user = data?.user;
    if (!token || !user) {
      error.value = "Invalid handoff response.";
      loading.value = false;
      return;
    }

    clientAuthStore.token = token;
    clientAuthStore.user = user;
    localStorage.setItem("clientToken", token);

    // 把 handoff URL 上的 lang 透传到最终 redirect，让目标 /app/* 路由的 guard 也能拿到，
    // 否则用户直接刷新 /app/deposit 时语言会丢失（store 也跟着重置）
    let finalRedirect = safeRedirect(data?.redirect);
    const lang = route.query.lang;
    if (lang && !/[?&]lang=/.test(finalRedirect)) {
      finalRedirect +=
        (finalRedirect.includes("?") ? "&" : "?") +
        "lang=" +
        encodeURIComponent(String(lang));
    }
    router.replace(finalRedirect);
  } catch (e) {
    error.value =
      e?.response?.data?.message ||
      e?.message ||
      e?.data?.message ||
      "Invalid or expired handoff code.";
    loading.value = false;
  }
});
</script>

<style scoped>
.handoff-entry {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f5f5f5;
}
.handoff-loading,
.handoff-error {
  text-align: center;
  padding: 2rem;
}
.handoff-loading i,
.handoff-error i {
  font-size: 2rem;
  color: #666;
  margin-bottom: 1rem;
}
.handoff-error i {
  color: #c00;
}
.handoff-error p {
  color: #333;
  margin-bottom: 1rem;
}
.handoff-link {
  display: inline-block;
  padding: 0.5rem 1rem;
  border-radius: 4px;
  background: #1f6feb;
  color: #fff;
  text-decoration: none;
}
</style>
