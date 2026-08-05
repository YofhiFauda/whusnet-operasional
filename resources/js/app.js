import NProgress from 'nprogress';

window.NProgress = NProgress;
NProgress.configure({ showSpinner: false, minimum: 0.1 });
NProgress.done();

window.addEventListener('beforeunload', () => {
    NProgress.start();
});

import './bootstrap';
