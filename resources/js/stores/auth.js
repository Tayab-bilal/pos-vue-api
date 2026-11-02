import { defineStore } from 'pinia';
import api from '../services/api';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        ready: false, // true once we know whether someone is signed in
    }),

    getters: {
        isLoggedIn: (state) => !!state.user,
        initial: (state) => (state.user?.name ?? '?').trim().charAt(0).toUpperCase(),
    },

    actions: {
        // Asks the server who is signed in. Used on page load and right after login.
        async fetchUser() {
            try {
                const { data } = await api.get('/api/user');
                this.user = data;
            } catch {
                this.user = null;
            } finally {
                this.ready = true;
            }
        },

        async login(email, password, remember = false) {
            // A fresh security cookie first, then the login itself
            await api.get('/sanctum/csrf-cookie');
            await api.post('/login', { email, password, remember });
            await this.fetchUser();

            if (!this.user) {
                throw new Error('Signed in, but the session was not kept. Open the site at http://localhost:8000 and try again.');
            }
        },

        async logout() {
            try {
                await api.post('/logout');
            } catch {
                // The session may already be gone, which is fine
            } finally {
                this.user = null;
            }
        },
    },
});