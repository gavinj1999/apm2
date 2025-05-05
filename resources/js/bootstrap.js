import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;

// Fetch CSRF token on app load
axios.get('/sanctum/csrf-cookie').then(() => {
    console.log('CSRF token fetched');
}).catch(error => {
    console.error('Error fetching CSRF token:', error);
});
