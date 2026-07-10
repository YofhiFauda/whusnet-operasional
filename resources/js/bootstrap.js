import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Add NProgress to Axios if NProgress is loaded globally
window.axios.interceptors.request.use((config) => {
    if (typeof window.NProgress !== 'undefined') window.NProgress.start();
    return config;
}, (error) => {
    if (typeof window.NProgress !== 'undefined') window.NProgress.done();
    return Promise.reject(error);
});

window.axios.interceptors.response.use((response) => {
    if (typeof window.NProgress !== 'undefined') window.NProgress.done();
    return response;
}, (error) => {
    if (typeof window.NProgress !== 'undefined') window.NProgress.done();
    return Promise.reject(error);
});

import './echo';
