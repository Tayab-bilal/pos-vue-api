import axios from 'axios';

const api = axios.create({
    baseURL: '/',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

// These calls handle their own errors, so they must not trigger the "session ended" handling
const isAuthCall = (url = '') =>
    url.includes('/login') || url.includes('/logout') || url.includes('/sanctum/') || url.endsWith('/api/user');

api.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error.response?.status;

        // 401 = not signed in, 419 = the page's security token expired. Either way the session is over.
        if ((status === 401 || status === 419) && !isAuthCall(error.config?.url)) {
            window.dispatchEvent(new CustomEvent('auth:expired'));
        }

        return Promise.reject(error);
    },
);

export default api;