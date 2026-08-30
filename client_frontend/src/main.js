import { createApp } from "vue";
import { createPinia } from "pinia";
import App from "./App.vue";
import router from "./router";
import "./assets/styles/main.css";
import "./assets/fonts/fonts.css";
import "flag-icons/css/flag-icons.min.css";
import { useAuthStore } from "./stores/auth";
import { initializeTheme } from "./composables/useTheme";

initializeTheme();

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);

// 在应用启动时恢复用户状态
const authStore = useAuthStore();
const token = localStorage.getItem("token");

if (token) {
  // console.log('Found token in localStorage, fetching user info...')
  authStore.fetchUser().then((success) => {
    if (success) {
      // console.log('User info restored successfully')
    } else {
      console.log("Failed to restore user info, clearing token");
      localStorage.removeItem("token");
    }
  });
}

app.mount("#app");
