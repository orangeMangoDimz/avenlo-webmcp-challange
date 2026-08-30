import { createApp } from "vue";
import { createPinia } from "pinia";
import App from "./App.vue";
import router from "./router";
import "./assets/styles/main.css";
import "./assets/fonts/fonts.css";
import "flag-icons/css/flag-icons.min.css";
import { useAuthStore } from "./stores/auth";
import { useLanguageStore } from "./stores/language";
import { setAdminTranslator } from "./i18n/adminI18nBridge";
import { useMenuSettingsStore } from "./stores/menuSettings";
import { initializeTheme } from "./composables/useTheme";

initializeTheme();

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);

const languageStore = useLanguageStore();
setAdminTranslator((key, fb) => languageStore.t(key, fb));
app.config.globalProperties.$adminT = (key, fb) => languageStore.t(key, fb);

async function bootstrap() {
  await languageStore.initLanguage();
  app.use(router);

  // 在应用启动时恢复用户状态
  const authStore = useAuthStore();
  const token = localStorage.getItem("token");

  if (token) {
    authStore.fetchUser().then(async (success) => {
      if (success) {
        const menuSettingsStore = useMenuSettingsStore();
        menuSettingsStore.loadSettings().catch((err) => {
          console.error("Failed to load menu settings on app init:", err);
        });
      } else {
        console.log("Failed to restore user info, clearing token");
        localStorage.removeItem("token");
      }
    });
  }

  app.mount("#app");
}

bootstrap();
