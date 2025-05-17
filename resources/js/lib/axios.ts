import axios from 'axios';

const api = axios.create({
    baseURL: window.location.origin,
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Content-Type': 'application/json',
    },
    withCredentials: true,
});

// Enable automatic XSRF-TOKEN cookie handling
api.defaults.withXSRFToken = true;

api.interceptors.response.use(
    (response) => response,
    (error) => {
        // Handle 401 (Unauthorized) - could redirect to login page
        if (error.response && error.response.status === 401) {
            const currentPath = window.location.pathname;
            //Checking the current page and not currently fetching user
            if (currentPath !== '/login' && !error.config.url.includes('/api/user')) {
                window.location.href = '/login';
            }
        }

        // Handle 419 (CSRF token mismatch) - could refresh the token
        if (error.response && error.response.status === 419) {
            console.error('CSRF token mismatch. Please refresh the page.');
        }

        return Promise.reject(error);
    },
);

export default api;
