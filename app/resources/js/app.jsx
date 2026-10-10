import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { ConfirmProvider } from './Components/ConfirmDialog';
import ToastHost from './Components/ToastHost';
import '../css/app.css';

// Daftarkan service worker dengan scope root agar mengontrol seluruh halaman
// (berkasnya di /build/, jadi server mengirim header Service-Worker-Allowed: /).
// Tanpa ini, prompt "Install aplikasi" tidak akan pernah muncul.
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker
            .register('/build/sw.js', { scope: '/' })
            .then((reg) => {
                // autoUpdate: cek versi baru tiap jam + saat tab kembali fokus
                const check = () => reg.update().catch(() => {});
                setInterval(check, 60 * 60 * 1000);
                document.addEventListener('visibilitychange', () => {
                    if (!document.hidden) check();
                });
                reg.addEventListener('updatefound', () => {
                    const sw = reg.installing;
                    if (!sw) return;
                    sw.addEventListener('statechange', () => {
                        if (sw.state === 'installed' && navigator.serviceWorker.controller) {
                            window.location.reload();
                        }
                    });
                });
            })
            .catch(() => {});
    });
}

createInertiaApp({
    resolve: async (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });
        const module = pages[`./Pages/${name}.jsx`];
        if (!module) {
            console.error(`Page not found: ./Pages/${name}.jsx`);
            return module;
        }
        const page = module.default;
        // wrap tiap page dengan providers global (toast + confirm) di dalam
        // Inertia context supaya usePage bisa dipakai di ToastHost.
        page.layout = page.layout || ((children) => (
            <ConfirmProvider>
                <ToastHost />
                {children}
            </ConfirmProvider>
        ));
        return module;
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: {
        color: '#1E3A8A',
        showSpinner: true,
    },
});
