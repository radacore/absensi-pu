import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { ConfirmProvider } from './Components/ConfirmDialog';
import ToastHost from './Components/ToastHost';
import '../css/app.css';

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
                <ToastHost position="top" />
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
