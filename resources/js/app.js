import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './app.vue';
import router from './router';
import { useAuthStore } from './stores/auth';
import { useUiStore } from './stores/ui';

import '../css/app.css';

const app = createApp(App);
const pinia = createPinia();
app.use(pinia);
app.use(router);

// Apply the saved text size before anything is drawn
useUiStore(pinia).applyTextSize();

// When the server says the session has ended (for example after a long break), go back to the login page
window.addEventListener('auth:expired', () => {
    const auth = useAuthStore();
    if (!auth.isLoggedIn) return; // already handled

    auth.user = null;
    router.replace({ path: '/login', query: { redirect: router.currentRoute.value.fullPath, expired: '1' } });
});

app.mount('#app');