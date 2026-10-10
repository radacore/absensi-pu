import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { ConfirmProvider } from './Components/ConfirmDialog';
import PwaUpdateBanner from './Components/PwaUpdateBanner';
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
                        // Ada versi baru siap. Jangan reload sendiri — biarkan
                        // PwaUpdateBanner yang menawarkan, supaya isian
                        // formulir yang sedang diketik tidak hilang.
                        if (sw.state === 'installed' && navigator.serviceWorker.controller) {
                            window.dispatchEvent(new Event('bbws:update-ready'));
                        }
                    });
                });
            })
            .catch(() => {});
    });
}

// Inertia v3 memancarkan event DOM `inertia:httpException` untuk setiap respons
// >= 400. Galat validasi (422) sudah otomatis muncul karena ToastHost membaca
// props.errors, tetapi kegagalan lain (403/419/429/500/503) hanya akan terlihat
// sebagai halaman error. Teruskan ke `bbws:toast` supaya alasannya jelas.
document.addEventListener('inertia:httpException', (event) => {
    const status = event.detail && event.detail.response ? event.detail.response.status : null;
    const pesan = {
        403: 'Akses ditolak untuk tindakan ini.',
        419: 'Sesi sudah kedaluwarsa. Muat ulang halaman lalu coba lagi.',
        429: 'Terlalu banyak permintaan. Coba lagi sebentar lagi.',
        500: 'Terjadi gangguan pada server.',
        503: 'Layanan sedang tidak tersedia.',
    }[status];
    if (!pesan) return;
    window.dispatchEvent(new CustomEvent('bbws:toast', { detail: { kind: 'error', msg: pesan } }));
});

createInertiaApp({
    resolve: async (name) => {
        // Lazy: tiap halaman jadi chunk terpisah dan hanya diunduh saat
        // dikunjungi. Sebelumnya `eager: true` membundel SEMUA halaman ke
        // dalam satu berkas sehingga muat pertama berat.
        const pages = import.meta.glob('./Pages/**/*.jsx');
        const load = pages[`./Pages/${name}.jsx`];
        if (!load) {
            console.error(`Page not found: ./Pages/${name}.jsx`);
            return;
        }
        const module = await load();
        const page = module.default;
        // wrap tiap page dengan providers global (toast + confirm) di dalam
        // Inertia context supaya usePage bisa dipakai di ToastHost.
        page.layout = page.layout || ((children) => (
            <ConfirmProvider>
                <ToastHost />
                <PwaUpdateBanner />
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
